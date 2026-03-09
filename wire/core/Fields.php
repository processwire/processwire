<?php namespace ProcessWire;

/**
 * ProcessWire Fields
 *
 * Manages collection of ALL Field instances, not specific to any particular Fieldgroup
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Manages all custom fields in ProcessWire, independently of any Fieldgroup. 
 * #pw-var $fields
 * #pw-body = 
 * Each field returned is an object of type `Field`. The $fields API variable is iterable: 
 * ~~~~~
 * foreach($fields as $field) {
 *   echo "<p>Name: $field->name, Type: $field->type, Label: $field->label</p>";
 * }
 * ~~~~~
 * #pw-body
 * 
 * @method Field|null get($key) Get a field by name or id
 * @method bool changeFieldtype(Field $field1, $keepSettings = false)
 * @method bool saveFieldgroupContext(Field $field, Fieldgroup $fieldgroup, $namespace = '') 
 * @method bool deleteFieldDataByTemplate(Field $field, Template $template) #pw-hooker
 * @method void changedType(Saveable $item, Fieldtype $fromType, Fieldtype $toType) #pw-hooker
 * @method void changeTypeReady(Saveable $item, Fieldtype $fromType, Fieldtype $toType) #pw-hooker
 * @method bool|Field clone(Field $item, $name = '') Clone a field and return it or return false on fail. 
 * @method array getTags($getFieldNames = false) Get tags for all fields (3.0.179+) #pw-advanced
 * @method bool applySetupName(Field $field, $setupName = '')
 *
 */

class Fields extends WireSaveableItems {

	/**
	 * Instance of FieldsArray
	 * 
	 * @var FieldsArray
	 *
	 */
	protected $fieldsArray = null;

	/**
	 * Field names that are native/permanent to the system and thus treated differently in several instances. 
	 *
	 * For example, a Field can't be given one of these names. 
	 *
	 */
	static protected $nativeNamesSystem = array(
		'child',
		'children',
		'count',
		'check_access',
		'created_users_id',
		'created',
		'createdUser',
		'createdUserID',
		'createdUsersID',
		'data',
		'description',
		'editUrl',
		'end',
		'fieldgroup',
		'fields',
		'find',
		'flags',
		'get',
		'has_parent',
		'hasParent',
		'httpUrl',
		'id',
		'include',
		'isNew',
		'limit',
		'modified_users_id',
		'modified',
		'modifiedUser',
		'modifiedUserID',
		'modifiedUsersID',
		'name',
		'num_children',
		'numChildren',
		'parent_id',
		'parent', 
		'parents',
		'path',
		'published',
		'rootParent',
		'siblings',
		'sort',
		'sortfield',
		'start',
		'status',
		'template',
		'templatePrevious',
		'templates_id',
		'url',
		'_custom',
	);

	/**
	 * Flag names in format [ flagInt => 'flagName' ]
	 * 
	 * @var array
	 * 
	 */
	protected $flagNames = array();

	/**
	 * Flags to field IDs
	 * 
	 * @var array 
	 */
	protected $flagsToIds = array();

	/**
	 * Field names that are native/permanent to this instance of ProcessWire (configurable at runtime)
	 * 
	 * Array indexes are the names and values are all boolean true. 
	 *
	 */
	protected $nativeNamesLocal = array();

	/**
	 * Cache of all tags for all fields, populated to array when asked for the first time
	 * 
	 * @var array|null
	 * 
	 */
	protected $tagList = null;

	/**
	 * @var FieldsTableTools|null
	 * 
	 */
	protected $tableTools = null;

	/** 
	 * @var Fieldtypes|null  
	 * 
	 */
	protected $fieldtypes = null;

	/**
	 * Construct
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->flagNames = array(
			Field::flagAutojoin => 'autojoin',
			Field::flagGlobal => 'global',
			Field::flagSystem => 'system',
			Field::flagPermanent => 'permanent',
			Field::flagAccess => 'access',
			Field::flagAccessAPI => 'access-api',
			Field::flagAccessEditor => 'access-editor',
			Field::flagFieldgroupContext => 'fieldgroup-context',
			Field::flagSystemOverride => 'system-override',
		);
		// convert so that keys are names so that isset() can be used rather than in_array()
		if(isset(self::$nativeNamesSystem[0])) {
			self::$nativeNamesSystem = array_flip(self::$nativeNamesSystem);
		}
	}
	
	public function getCacheItemName() {
		return array('roles', 'permissions', 'title', 'process'); 
	}

	/**
	 * Construct and load the Fields
	 * 
	 * #pw-internal
	 *
	 */
	public function init() {
		$this->getWireArray();
	}

	/**
	 * Per WireSaveableItems interface, return a blank instance of a Field
	 * 
	 * #pw-internal
	 * 
	 * @return Field
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Field());
	}

	/**
	 * Called after rows loaded from DB but before populated to this instance
	 *
	 * @param array $rows
	 *
	 */
	protected function loadRowsReady(array &$rows) { 
		for($flag = 1; $flag <= 256; $flag *= 2) {
			$this->flagsToIds[$flag] = array();
		}
		foreach($rows as $row) {
			$flags = (int) $row['flags'];
			if(empty($flags)) continue;
			foreach($this->flagsToIds as $flag => $ids) {
				if($flags & $flag) $this->flagsToIds[$flag][] = (int) $row['id'];
			}
		}
	}

	/**
	 * Given field ID return native property
	 * 
	 * This avoids loading the field if the property can be obtained natively. 
	 * 
	 * #pw-internal
	 * 
	 * @param int $id
	 * @param string $property
	 * @return array|bool|mixed|string|null
	 * @since 3.0.243
	 * 
	 */
	public function fieldIdToProperty($id, $property) {
		$id = (int) $id;
		if(isset($this->lazyIdIndex[$id])) {
			$n = $this->lazyIdIndex[$id];
			if(isset($this->lazyItems[$n][$property])) {
				return $this->lazyItems[$n][$property];
			}
		}
		$field = $this->get($id);
		return $field ? $field->get($property) : null;
	}

	/**
	 * Make an item and populate with given data
	 *
	 * @param array $a Associative array of data to populate
	 * @return Saveable|Wire
	 * @throws WireException
	 * @since 3.0.146
	 *
	 */
	public function makeItem(array $a = array()) {
		
		if(empty($a['type'])) return parent::makeItem($a);
		if($this->fieldtypes === null) $this->fieldtypes = $this->wire()->fieldtypes;
		if(!$this->fieldtypes) return parent::makeItem($a);
		
		/** @var Fieldtype $fieldtype */
		$fieldtype = $this->fieldtypes->get($a['type']);
		if(!$fieldtype) {
			if($this->useLazy) {
				$this->error("Fieldtype module '$a[type]' for field '$a[name]' is missing");
				$fieldtype = $this->fieldtypes->get('FieldtypeText');
			} else {
				return parent::makeItem($a);
			}
		}
		
		$a['type'] = $fieldtype;
		$a['id'] = (int) $a['id'];
		$a['flags'] = (int) $a['flags'];
	
		$class = $fieldtype->getFieldClass($a);
		
		if(empty($class) || $class === 'Field') {
			$class = '';
		} else if(strpos($class, "\\") === false) {
			$class = wireClassName($class, true);
			if(!class_exists($class)) $class = '';
		}
		
		if(empty($class)) {
			$field = new Field();
		} else {
			$field = new $class(); /** @var Field $field */
		}
	
		$this->wire($field);
		$field->setTrackChanges(false);
		
		foreach($a as $key => $value) {
			$field->setRawSetting($key, $value);
		}
		
		$field->resetTrackChanges(true);
		
		return $field;
	}
	
	/**
	 * Create a new Saveable item from a raw array ($row) and add it to $items
	 *
	 * @param array $row
	 * @param WireArray|null $items
	 * @return Saveable|WireData|Wire
	 * @since 3.0.194
	 *
	 */
	protected function initItem(array &$row, ?WireArray $items = null) {
		/** @var Field $item */
		$item = parent::initItem($row, $items);
		$fieldtype = $item ? $item->type : null;
		if($fieldtype) $fieldtype->initField($item);
		return $item;
	}

	/**
	 * Per WireSaveableItems interface, return all available Field instances
	 * 
	 * #pw-internal
	 * 
	 * @return FieldsArray|WireArray
	 *
	 */
	public function getAll() {
		if($this->useLazy()) $this->loadAllLazyItems();
		return $this->getWireArray();
	}

	/**
	 * Get WireArray container that items are stored in
	 *
	 * @return WireArray
	 * @since 3.0.194
	 *
	 */
	public function getWireArray() {
		if($this->fieldsArray === null) {
			$this->fieldsArray = new FieldsArray();
			$this->wire($this->fieldsArray);
			$this->load($this->fieldsArray); 
		}
		return $this->fieldsArray;
	}

	/**
	 * Per WireSaveableItems interface, return the table name used to save Fields
	 * 
	 * #pw-internal
	 *
	 */
	public function getTable() {
		return "fields";
	}

	/**
	 * Return the name that fields should be initially sorted by
	 * 
	 * #pw-internal
	 *
	 */
	public function getSort() {
		return $this->getTable() . ".name";
	}

	/**
	 * Save a Field to the database
	 * 
	 * ~~~~~
	 * // Modify a field label and save it
	 * $field = $fields->get('title');
	 * $field->label = 'Title or Headline';
	 * $fields->save($field); 
	 * ~~~~~
	 *
	 * @param Field $item The field to save
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Saveable $item) {

		if($item->flags & Field::flagFieldgroupContext) throw new WireException("Field $item is not saveable because it is in a specific context"); 
		if(!strlen($item->name)) throw new WireException("Field name is required"); 

		$database = $this->wire()->database;
		$isNew = $item->id < 1;
		$prevTable = $database->escapeTable($item->prevTable);
		$table = $database->escapeTable($item->getTable());
		
		if(!$isNew && $prevTable && $prevTable != $table) {
			// note that we rename the table twice in order to force MySQL to perform the rename 
			// even if only the case has changed. 
			$schema = $item->type->getDatabaseSchema($item);
			if(!empty($schema)) {
				list(,$tmpTable) = explode(Field::tablePrefix, $table, 2);
				$tmpTable = "tempf_$tmpTable";
				foreach(array($table, $tmpTable) as $t) {
					if(!$database->tableExists($t)) continue;
					throw new WireException("Cannot rename to '$item->name' because table `$table` already exists");
				}
				$database->exec("RENAME TABLE `$prevTable` TO `$tmpTable`"); // QA
				$database->exec("RENAME TABLE `$tmpTable` TO `$table`"); // QA
			}
			$item->prevTable = '';
		}
		
		if(!$isNew && $item->prevName && $item->prevName != $item->name) {
			$item->type->renamedField($item, $item->prevName);
			$item->prevName = '';
		}

		if($item->prevFieldtype && $item->prevFieldtype->name != $item->type->name) {
			if(!$this->changeFieldtype($item)) {
				$item->type = $item->prevFieldtype; 
				$this->error("Error changing fieldtype for '$item', reverted back to '{$item->type}'"); 
			} else {
				$item->prevFieldtype = null;
			}
		}

		if(!$item->type) throw new WireException("Can't save a Field that doesn't have it's 'type' property set to a Fieldtype"); 
		$item->type->saveFieldReady($item);
		if(!parent::___save($item)) return false;
		if($isNew) $item->type->createField($item); 

		$setupName = $item->setSetupName();
		if($setupName || $isNew) {
			if($this->applySetupName($item, $setupName)) {
				$item->setSetupName('');
				parent::___save($item);
			}
		}

		if($item->flags & Field::flagGlobal) {
			// make sure that all template fieldgroups contain this field and add to any that don't. 
			foreach($this->wire()->templates as $template) {
				if($template->noGlobal) continue; 
				$fieldgroup = $template->fieldgroup; 
				if(!$fieldgroup->hasField($item)) {
					$fieldgroup->add($item); 
					$fieldgroup->save();
					$this->message("Added field '{$item->name}' to template/fieldgroup '$fieldgroup->name'", Notice::debug); 
				}
			}	
		}
		
		if($item->type) $item->type->savedField($item);
		
		$this->getTags('reset');

		return true; 
	}

	/**
	 * Check that the given Field's table exists and create it if it doesn't
	 * 
 	 * @param Field $field
	 *
	 */
	protected function checkFieldTable(Field $field) {
		$database = $this->wire()->database; 
		$table = $database->escapeTable($field->getTable());
		if(empty($table)) return;
		$exists = $database->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
		if($exists) return;
		try {
			if($field->type && count($field->type->getDatabaseSchema($field))) {
				if($field->type->createField($field)) $this->message("Created table '$table'"); 
			}
		} catch(\Exception $e) {
			$this->trackException($e, false, $e->getMessage() . " (checkFieldTable)"); 
		}
	}

	/**
	 * Check that all fields in the system have their tables installed
	 *
	 * This enables you to re-create field tables when migrating over entries from the Fields table manually (via SQL dumps or the like)
	 * 
	 * #pw-internal
	 *
	 */
	public function checkFieldTables() {
		foreach($this as $field) $this->checkFieldTable($field); 
	}

	/**
	 * Delete a Field from the database
	 * 
	 * This method will throw a WireException if you attempt to delete a field that is currently in use (i.e. assigned to one or more fieldgroups). 
	 *
	 * @param Field $item Field to delete
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___delete(Saveable $item) {

		if(!$this->getWireArray()->isValidItem($item)) {
			throw new WireException("Fields::delete(item) only accepts items of type Field");
		}

		// if the field doesn't have an ID, so it's not one that came from the DB
		if(!$item->id) {
			$table = $item->getTable();
			throw new WireException("Unable to delete from '$table' for field that doesn't exist in fields table");
		}

		// if it's in use by any fieldgroups, then we don't allow it to be deleted
		if($item->numFieldgroups()) {
			$names = $item->getFieldgroups()->implode("', '", (string) "name");
			throw new WireException("Unable to delete field '$item->name' because it is in use by these fieldgroups: '$names'");
		}

		// if it's a system field, it may not be deleted
		if($item->flags & Field::flagSystem) {
			throw new WireException("Unable to delete field '$item->name' because it is a system field.");
		}

		// delete entries in fieldgroups_fields table. Not really necessary since the above exception prevents this, but here in case that changes. 
		$this->wire()->fieldgroups->deleteField($item); 

		// drop the field's table
		if($item->type) $item->type->deleteField($item); 

		return parent::___delete($item); 
	}


	/**
	 * Create and return a cloned copy of the given Field
	 * 
	 * @param Field $item Field to clone
	 * @param string $name Optionally specify name for new cloned item
	 * @return Field $item Returns the new clone on success, or false on failure
	 *
	 */
	public function ___clone(Saveable $item, $name = '') {
	
		$item = $item->type->cloneField($item); 
	
		// don't clone system flags	
		if($item->flags & Field::flagSystem || $item->flags & Field::flagPermanent) {
			$item->flags = $item->flags | Field::flagSystemOverride; 
			if($item->flags & Field::flagSystem) $item->flags = $item->flags & ~Field::flagSystem;
			if($item->flags & Field::flagPermanent) $item->flags = $item->flags & ~Field::flagPermanent;
			$item->flags = $item->flags & ~Field::flagSystemOverride;
		}

		// don't clone the 'global' flag
		if($item->flags & Field::flagGlobal) $item->flags = $item->flags & ~Field::flagGlobal;
	
		/** @var Field $item */
		$item = parent::___clone($item, $name);
		if($item) {
			$item->prevTable = null;
			$item->prevName = ''; // prevent renamed hook
		}
		
		return $item;
	}

	/**
	 * Save the context of the given field for the given fieldgroup
	 * 
	 * #pw-advanced
	 *
	 * @param Field $field Field to save context for
	 * @param Fieldgroup $fieldgroup Context for when field is in this fieldgroup
	 * @param string $namespace An optional namespace for additional context
	 * @return bool True on success
	 * @throws WireException
	 *
	 */
	public function ___saveFieldgroupContext(Field $field, Fieldgroup $fieldgroup, $namespace = '') {

		// get field without context
		$fieldOriginal = $this->get($field->name);
		$data = array();

		// make sure given field and fieldgroup are valid
		if(!($field->flags & Field::flagFieldgroupContext)) {
			throw new WireException("Field must be in fieldgroup context before its context can be saved");
		}
		
		if(!$fieldgroup->has($fieldOriginal)) {
			throw new WireException("Fieldgroup $fieldgroup does not contain field $field");
		}

		$field_id = (int) $field->id;
		$fieldgroup_id = (int) $fieldgroup->id; 
		$database = $this->wire()->database;

		$newValues = $field->getArray();
		$oldValues = $fieldOriginal->getArray();

		// 0 is the same as 100 for columnWidth, so we specifically set it just to prevent this from being saved when it doesn't need to be
		if(!isset($oldValues['columnWidth'])) $oldValues['columnWidth'] = 100;

		// add the built-in fields
		foreach(array('label', 'description', 'notes', 'viewRoles', 'editRoles') as $key) {
			$newValues[$key] = $field->$key;
			$oldValues[$key] = $fieldOriginal->$key;
		}

		// account for flags that may be applied as part of context
		$flags = $field->flags & ~Field::flagFieldgroupContext;
		if($flags != $fieldOriginal->flags) {
			$flagsAdd = 0;
			$flagsDel = 0;
			// flags that are allowed to be set via context
			$contextFlags = array(
				Field::flagAccess, 
				Field::flagAccessAPI, 
				Field::flagAccessEditor
			);
			foreach($contextFlags as $flag) {
				if($fieldOriginal->hasFlag($flag) && !$field->hasFlag($flag)) $flagsDel = $flagsDel | $flag;
				if(!$fieldOriginal->hasFlag($flag) && $field->hasFlag($flag)) $flagsAdd = $flagsAdd | $flag;
			}
			if($flagsAdd) $data['flagsAdd'] = $flagsAdd; 
			if($flagsDel) $data['flagsDel'] = $flagsDel;
		}

		// cycle through and determine which values should be saved
		foreach($newValues as $key => $value) {
			$oldValue = empty($oldValues[$key]) ? '' : $oldValues[$key]; 

			// if both old and new are empty, then don't store a blank value in the context
			if(empty($oldValue) && empty($value)) continue; 

			// if old and new value are the same, then don't duplicate the value in the context
			if($value == $oldValue) continue; 

			// $value differs from $oldValue and should be saved
			$data[$key] = $value;
		}

		// remove runtime properties (those that start with underscore)
		foreach($data as $key => $value) {
			if(strpos($key, '_') === 0) unset($data[$key]);
		}

		// keep all in the same order so that it's easier to compare (by eye) in the DB
		ksort($data);

		if($namespace) {
			// get existing data and move everything here into a namespace within that data
			$query = $database->prepare('SELECT data FROM fieldgroups_fields WHERE fields_id=:field_id AND fieldgroups_id=:fieldgroup_id'); 
			$query->bindValue(':field_id', $field_id, \PDO::PARAM_INT);
			$query->bindValue(':fieldgroup_id', $fieldgroup_id, \PDO::PARAM_INT);
			$query->execute();
			list($existingData) = $query->fetch(\PDO::FETCH_NUM);
			$existingData = strlen($existingData) ? json_decode($existingData, true) : array();
			if(!is_array($existingData)) $existingData = array();
			foreach($data as $k => $v) {
				// disallow namespace within namespace
				if(strpos($k, Fieldgroup::contextNamespacePrefix) === 0) unset($data[$k]);
			}
			$existingData[Fieldgroup::contextNamespacePrefix . $namespace] = $data;
			$data = $existingData;
		}
		
		// inject updated context back into model
		$fieldgroup->setFieldContextArray($field->id, $data);

		// if there is something in data, then JSON encode it. If it's empty then make it null.
		$data = count($data) ? wireEncodeJSON($data, true) : null;

		$query = $database->prepare('UPDATE fieldgroups_fields SET data=:data WHERE fields_id=:field_id AND fieldgroups_id=:fieldgroup_id');
		if(empty($data)) {
			$query->bindValue(':data', null, \PDO::PARAM_NULL); 
		} else {
			$query->bindValue(':data', $data, \PDO::PARAM_STR); 
		}
		$query->bindValue(':field_id', $field_id, \PDO::PARAM_INT);
		$query->bindValue(':fieldgroup_id', $fieldgroup_id, \PDO::PARAM_INT); 
		$result = $query->execute();

		return $result; 
	}


	/**
	 * Change a field's type
	 * 
	 * #pw-hooker
	 * 
	 * @param Field $field1 Field with the new type already assigned
	 * @param bool $keepSettings Whether or not to keep custom $data settings (default=false)
	 * @throws WireException
	 * @return bool
	 *
	 */
	protected function ___changeFieldtype(Field $field1, $keepSettings = false) {

		if(!$field1->prevFieldtype) {
			throw new WireException("changeFieldType requires that the given field has had a type change");
		}

		if(	($field1->type instanceof FieldtypeMulti && !$field1->prevFieldtype instanceof FieldtypeMulti) || 
			($field1->prevFieldtype instanceof FieldtypeMulti && !$field1->type instanceof FieldtypeMulti)) {
			throw new WireException("Cannot convert between single and multiple value field types"); 
		}

		$fromType = $field1->prevFieldtype;
		$toType = $field1->type;

		$this->changeTypeReady($field1, $fromType, $toType);

		$field2 = clone $field1; 
		$flags = $field2->flags; 
		if($flags & Field::flagSystem) {
			$field2->flags = $flags | Field::flagSystemOverride; 
			$field2->flags = 0; // intentional overwrite after above line
		}
		$field2->name = $field2->name . "_PWTMP";
		$field2->prevFieldtype = $field1->type;
		$field2->type->createField($field2); 
		$field1->type = $field1->prevFieldtype;

		$schema1 = array();
		$schema2 = array();

		$database = $this->wire()->database; 
		$table1 = $database->escapeTable($field1->table); 
		$table2 = $database->escapeTable($field2->table);

		$query = $database->prepare("DESCRIBE `$table1`"); // QA
		$query->execute();
		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) $schema1[] = $row['Field'];

		$query = $database->prepare("DESCRIBE `$table2`"); // QA
		$query->execute();
		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) $schema2[] = $row['Field'];
			
		foreach($schema1 as $key => $value) {
			if(!in_array($value, $schema2)) {
				$this->message("changeFieldType loses table field '$value'", Notice::debug); 
				unset($schema1[$key]); 
			}
		}

		$sql = 	"INSERT INTO `$table2` (`" . implode('`,`', $schema1) . "`) " . 
				"SELECT `" . implode('`,`', $schema1) . "` FROM `$table1` ";
		
		$error = '';
		$exception = null;

		try {
			$result = $database->exec($sql);
			if($result === false || $query->errorCode() > 0) {
				$errorInfo = $query->errorInfo();
				$error = !empty($errorInfo[2]) ? $errorInfo[2] : 'Unknown Error'; 
			}
		} catch(\Exception $e) {
			$exception = $e;
			$error = $e->getMessage();
		}

		if($exception) {
			$this->error("Field type change failed. Database reports: $error"); 
			$database->exec("DROP TABLE `$table2`"); // QA
			$severe = $this->wire()->process != 'ProcessField';
			$this->trackException($exception, $severe); 
			return false; 
		}

		$database->exec("DROP TABLE `$table1`"); // QA
		$database->exec("RENAME TABLE `$table2` TO `$table1`"); // QA

		$field1->type = $field2->type; 

		if(!$keepSettings) {
			// clear out the custom data, which contains settings specific to the Inputfield and Fieldtype
			foreach($field1->getArray() as $key => $value) {
				// skip fields that may be shared among any fieldtype
				if(in_array($key, array('description', 'required', 'collapsed', 'notes'))) continue; 
				// skip over language labels/descriptions
				if(preg_match('/^(description|label|notes)\d+/', $key)) continue; 
				// remove the custom field
				$field1->remove($key); 
			}
		}

		$this->changedType($field1, $fromType, $toType); 

		return true; 	
	}


	/**
	 * Physically delete all field data (from the database) used by pages of a given template
	 *
	 * This is for internal API use only. This method is intended only to be called by 
	 * Fieldtype::deleteTemplateField
	 * 
	 * If you need to remove a field from a Fieldgroup, use Fieldgroup::remove(), and this
	 * method will be call automatically at the appropriate time when save the fieldgroup. 
	 * 
	 * #pw-hooker
	 *
	 * @param Field $field
	 * @param Template $template
	 * @return bool Whether or not it was successful
	 * @throws WireException when given a situation where deletion is not allowed
	 *
	 */
	public function ___deleteFieldDataByTemplate(Field $field, Template $template) {

		// first we need to determine if the $field->type module has its own
		// deletePageField method separate from base: Fieldtype/FieldtypeMulti
		$reflector = new \ReflectionClass($field->type->className(true));
		$hasDeletePageField = false;

		foreach($reflector->getMethods() as $method) {
			$methodName = $method->getName();
			if(strpos($methodName, '___deletePageField') === false) continue;
			try {
				new \ReflectionMethod($reflector->getParentClass()->getName(), $methodName);
				if(!in_array($method->getDeclaringClass()->getName(), array(
					'Fieldtype', 
					'FieldtypeMulti', 
					__NAMESPACE__ . "\\Fieldtype", 
					__NAMESPACE__ . "\\FieldtypeMulti"))) {
					$hasDeletePageField = true;
				}

			} catch(\Exception $e) {
				// not there
			}
			break;
		}

		$numPages = $this->getNumPages($field, array('template' => $template)); 
		$numRows = $this->getNumRows($field, array('template' => $template)); 
		$success = true;

		if($numPages <= 200 || $hasDeletePageField) {
			$deleteType = $this->_('page-by-page'); 
			
			// not many pages to operate on, OR fieldtype has a custom deletePageField method, 
			// so use verbose/slow method to delete the field from pages
			
			$ids = $this->getNumPages($field, array('template' => $template, 'getPageIDs' => true)); 
			$items = $this->wire()->pages->getById($ids, $template); 
			
			foreach($items as $page) {
				set_time_limit(600);
				try {
					$field->type->deletePageField($page, $field);

				} catch(\Exception $e) {
					$this->trackException($e, false, true);
					$success = false;
				}
			}

		} else {
			$deleteType = $this->_('single-query'); 
			
			// large number of pages to operate on: use fast method
			
			$database = $this->wire()->database;
			$table = $database->escapeTable($field->getTable());
			$sql = 	"DELETE $table FROM $table " .
					"INNER JOIN pages ON pages.id=$table.pages_id " .
					"WHERE pages.templates_id=:templates_id";
			$query = $database->prepare($sql);
			$query->bindValue(':templates_id', $template->id, \PDO::PARAM_INT);
			try {
				$query->execute();
			} catch(\Exception $e) {
				$this->error($e->getMessage(), Notice::log);
				$this->trackException($e);
				$success = false;
			}
		}
		
		if($success) {
			$this->message(
				sprintf($this->_('Deleted field "%1$s" data in %2$d row(s) from %3$d page(s) using template "%4$s".'), 
					$field->name, $numRows, $numPages, $template->name) . " [$deleteType]",
				Notice::log
			);
		} else {
			$this->error(
				sprintf($this->_('Error deleting field "%1$s" data, %2$d row(s), %3$d page(s) using template "%4$s".'), 
					$field->name, $numRows, $numPages, $template->name) . " [$deleteType]",
				Notice::log
			);
		}
		
		return $success;
	}

	/**
	 * Return a count of pages containing populated values for the given field
	 *
	 * @param Field $field
	 * @param array $options Optionally specify one of the following options:
	 *  - `template` (template|int|string): Specify a Template object, ID or name to isolate returned rows specific to pages using that template.
	 *  - `page` (Page|int|string): Specify a Page object, ID or path to isolate returned rows specific to that page.
	 *  - `getPageIDs` (bool): Specify boolean true to make it return an array of matching Page IDs rather than a count. 
	 * @return int|array Returns array only if getPageIDs option set, otherwise returns count of pages. 
	 * @throws WireException If given option for page or template doesn't resolve to actual page/template.
	 *
	 */
	public function getNumPages(Field $field, array $options = array()) {
		$options['countPages'] = true; 
		return $this->getNumRows($field, $options); 
	}

	/**
	 * Return a count of database rows populated the given field
	 *
	 * @param Field $field
	 * @param array $options Optionally specify any of the following options:
	 *  - `template` (Template|int|string): Specify a Template object, ID or name to isolate returned rows specific to pages using that template. 
	 *  - `page` (Page|int|string): Specify a Page object, ID or path to isolate returned rows specific to that page. 
	 *  - `countPages` (bool): Specify boolean true to make it return a page count rather than a row count (default=false). 
	 * 	  There will only be potential difference between rows and pages counts with multi-value fields. 
	 *  - `getPageIDs` (bool): Specify boolean true to make it return an array of matching Page IDs rather than a count (overrides countPages).
	 * @return int|array Returns array only if getPageIDs option set, otherwise returns a count of rows. 
	 * @throws WireException If given option for page or template doesn't resolve to actual page/template.
	 *
	 */
	public function getNumRows(Field $field, array $options = array()) {

		$defaults = array(
			'template' => 0,
			'page' => 0,
			'countPages' => false,
			'getPageIDs' => false, 
		);
		
		if(!$field->type) return 0;

		$options = array_merge($defaults, $options);
		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		$useRowCount = false;
		$schema = $field->type->getDatabaseSchema($field);
		
		if(empty($schema)) {
			// field has no schema or table (example: FieldtypeConcat)
			if($options['getPageIDs']) return array();
			return 0; 
		}

		if($options['template']) {
			// count by pages using specific template

			if($options['template'] instanceof Template) {
				$template = $options['template'];
			} else {
				$template = $this->wire()->templates->get($options['template']);
			}

			if(!$template) throw new WireException("Unknown template: $options[template]");
			
		
			if($options['getPageIDs']) {
				$sql = 	"SELECT DISTINCT $table.pages_id FROM $table ".
						"JOIN pages ON pages.templates_id=:templateID AND pages.id=pages_id ";

			} else if($options['countPages']) {
				$sql = 	"SELECT COUNT(DISTINCT pages_id) FROM $table ". 
						"JOIN pages ON pages.templates_id=:templateID AND pages.id=pages_id ";
			} else {
				$sql = 	"SELECT COUNT(*) FROM pages " . 
						"JOIN $table ON $table.pages_id=pages.id " .
						"WHERE pages.templates_id=:templateID ";
			}
			$query = $database->prepare($sql);
			$query->bindValue(':templateID', $template->id, \PDO::PARAM_INT);

		} else if($options['page']) {
			// count by specific page

			if(is_int($options['page'])) {
				$pageID = $options['page'];
			} else {
				$page = $this->wire()->pages->get($options['page']);
				$pageID = $page->id;
			}

			if(!$pageID) throw new WireException("Unknown page: $options[page]");
			
			if($options['countPages']) {
				// is there any the point to  this?
				$sql = "SELECT COUNT(DISTINCT pages_id) FROM $table WHERE pages_id=:pageID ";
			} else {
				$sql = "SELECT COUNT(*) FROM $table WHERE pages_id=:pageID ";
			}

			$query = $database->prepare($sql);
			$query->bindValue(':pageID', $pageID, \PDO::PARAM_INT);

		} else {
			// overall total count
			
			if($options['getPageIDs']) {
				$sql = "SELECT DISTINCT $table.pages_id FROM $table";
			} else if($options['countPages']) {
				$sql = "SELECT COUNT(DISTINCT pages_id) FROM $table";
			} else {
				$sql = "SELECT COUNT(*) FROM $table";
			}
			$query = $database->prepare($sql);
		}

		$return = $options['getPageIDs'] ? array() : 0;	
		
		try {
			$query->execute();
			if($options['getPageIDs']) {
				/** @noinspection PhpAssignmentInConditionInspection */
				while($id = $query->fetchColumn()) {
					$return[] = (int) $id;
				}
			} else if($useRowCount) {
				$return = (int) $query->rowCount();
			} else {
				list($return) = $query->fetch(\PDO::FETCH_NUM);
				$return = (int) $return;
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage() . " (getNumRows)");
			$this->trackException($e, false);
		}

		return $return;
	}

	/**
	 * Is the given field name native/permanent to the database?
	 * 
	 * This is deprecated, please us $fields->isNative($name) instead. 
	 * 
	 * #pw-internal
	 *
	 * @param string $name
	 * @return bool
	 * @deprecated
	 *
	 */
	public static function isNativeName($name) {
		$fields = wire()->fields;
		return $fields->isNative($name);
	}

	/**
	 * Is the given field name native/permanent to the database?
	 * 
	 * Such fields are disallowed from being used for custom field names. 
	 *
	 * @param string $name Field name you want to check
	 * @return bool True if field is native (and thus should not be used) or false if it's okay to use
	 *
	 */
	public function isNative($name) {
		if(isset(self::$nativeNamesSystem[$name])) return true;
		if(isset($this->nativeNamesLocal[$name])) return true; 
		return false; 
	}

	/**
	 * Add a new name to be recognized as a native field name
	 * 
	 * #pw-internal
	 *
	 * @param string $name
	 *
	 */
	public function setNative($name) {
		$this->nativeNamesLocal[$name] = true; 
	}

	/**
	 * Get list of all tags used by fields
	 * 
	 * - By default it returns an array of tag names where both keys and values are the tag names. 
	 * - If you specify true for the `$getFields` argument, it returns an array where the keys are 
	 *   tag names and the values are arrays of field names in the tag. 
	 * - If you specify "reset" for the `$getFields` argument it returns a blank array and resets 
	 *   internal tags cache.
	 * 
	 * #pw-advanced
	 * 
	 * @param bool|string $getFieldNames Specify true to return associative array where keys are tags and values are field names
	 *   …or specify the string "reset" to force getTags() to reset its cache, forcing it to reload on the next call. 
	 * @return array Both keys and values are tags in return value
	 * @since 3.0.106 + made hookable in 3.0.179
	 * 
	 */
	public function ___getTags($getFieldNames = false) {
		
		if($getFieldNames === 'reset') {
			$this->tagList = null;
			return array();
		}
		
		if($this->tagList === null) {
			$tagList = array();
			foreach($this as $field) {
				/** @var Field $field */
				$fieldTags = $field->getTags();
				foreach($fieldTags as $tag) {
					if(!isset($tagList[$tag])) $tagList[$tag] = array();
					$tagList[$tag][] = $field->name;
				}
			}
			ksort($tagList);
			$this->tagList = $tagList;
		}
		
		if($getFieldNames) return $this->tagList;
		
		$tagList = array();
		foreach($this->tagList as $tag => $fieldNames) {
			$tagList[$tag] = $tag;
		}
		
		return $tagList;
	}

	/**
	 * Return all fields that have the given $tag
	 * 
	 * Returns an associative array of `['field_name' => 'field_name']` if `$getFieldNames` argument is true, 
	 * or `['field_name => Field instance]` if not (which is the default). 
	 * 
	 * @param string $tag Tag to find fields for
	 * @param bool $getFieldNames If true, returns array of field names rather than Field objects (default=false). 
	 * @return array Array of Field objects, or array of field names if requested. Array keys are always field names.
	 * @since 3.0.106
	 * 
	 */
	public function findByTag($tag, $getFieldNames = false) {
		$tags = $this->getTags(true);
		$items = array();
		if(!isset($tags[$tag])) return $items;
		foreach($tags[$tag] as $fieldName) {
			$items[$fieldName] = ($getFieldNames ? $fieldName : $this->get($fieldName));
		}
		ksort($items);
		return $items;
	}

	/**
	 * Find fields by flag
	 * 
	 * #pw-internal
	 * 
	 * @param int $flag
	 * @param bool $getFieldNames
	 * @return array|Field[]
	 * @since 3.0.243
	 * 
	 */
	public function findByFlag($flag, $getFieldNames = false) {
		if(!isset($this->flagsToIds[$flag])) return array();
		$items = [];
		foreach($this->flagsToIds[$flag] as $id) {
			if($getFieldNames) {
				$items[] = $this->fieldIdToProperty($id, 'name');
			} else {
				$items[] = $this->get($id); 
			}
		}
		return $items;
	}

	/**
	 * Find fields by type
	 * 
	 * @param string|Fieldtype $type Fieldtype class name or object
	 * @param array $options
	 *  - `inherit` (bool): Also find types that inherit from given type? (default=true) 
	 *  - `valueType` (string): Value type to return, one of 'field', 'id', or 'name' (default='field')
	 *  - `indexType` (string): Index type to use, one of 'name', 'id', or '' blank for non-associative array (default='name')
	 * @return array|Field[]
	 * @since 3.0.194
	 * 
	 */
	public function findByType($type, array $options = array()) {
		
		$defaults = array(
			'inherit' => true, // also find fields using type inherited from given type or interface?
			'valueType' => 'field', // one of 'field', 'id', or 'name'
			'indexType' => 'name', // one of 'name', 'id', or '' blank for non associative array
		);
		
		$options = array_merge($defaults, $options);
		$valueType = $options['valueType'];
		$indexType = $options['indexType'];
		$inherit = $options['inherit'];
		$matchTypes = array();
		$matches = array();
		
		if($inherit) {
			$typeName = wireClassName($type, true);
			foreach($this->wire()->fieldtypes as $fieldtype) {
				if($fieldtype instanceof $typeName) $matchTypes[$fieldtype->className()] = true;
			}
		} else {
			$typeName = wireClassName($type);
			$matchTypes[$typeName] = true;
		}
		
		foreach($this->getWireArray() as $field) {
			/** @var Field $field */
			$fieldtype = $field->type;

			if(!$fieldtype) continue;
			if(!isset($matchTypes[$fieldtype->className()])) continue;

			if($valueType === 'field') {
				$value = $field;
			} else if($valueType === 'name') {
				$value = $field->name;
			} else {
				$value = $field->id;
			}
			if($indexType) {
				$index = $field->get($options['indexType']);
				$matches[$index] = $value;
			} else {
				$matches[] = $value;
			}
		}

		if($this->useLazy()) {
			foreach(array_keys($this->lazyItems) as $key) {
				if(!isset($this->lazyItems[$key])) continue;
				$row = $this->lazyItems[$key];
				if(empty($row['type'])) continue;
				$type = $row['type'];
				if(!isset($matchTypes[$type])) continue;
				if($valueType === 'field') {
					$value = $this->getLazy((int) $row['id']);
				} else if($valueType === 'name') {
					$value = $row['name'];
				} else {
					$value = $row['id'];
				}
				if($indexType) {
					$index = isset($data[$indexType]) ? $row[$indexType] : $row['id'];
					$matches[$index] = $value;
				} else {
					$matches[] = $value;
				}
			}
		}
	
		return $matches;
	}

	/**
	 * Get all field names
	 *
	 * @param string $indexType One of 'name', 'id' or blank string for no index (default='')
	 * @return array
	 * @since 3.0.194
	 *
	 */
	public function getAllNames($indexType = '') {
		return $this->getAllValues('name', $indexType); 
	}

	/**
	 * Get all flag names or get all flag names for given flags or Field
	 * 
	 * #pw-internal
	 * 
	 * @param int|Field|null $flags Specify flags or Field or omit to get all flag names
	 * @param bool $getString Get a string of flag names rather than array? (default=false)
	 * @return array|string When array is returned, array is of strings indexed by flag value (int)
	 * 
	 */
	public function getFlagNames($flags = null, $getString = false) {
		if($flags === null) {
			$a = $this->flagNames;
		} else {
			$a = array();
			if($flags instanceof Field) $flags = $flags->flags;
			foreach($this->flagNames as $flag => $name) {
				if($flags & $flag) $a[$flag] = $name;
			}
		}
		return $getString ? implode(' ', $a) : $a;	
	}

	/**
	 * Overridden from WireSaveableItems to retain keys with 0 values and remove defaults we don't need saved
	 * 
	 * #pw-internal
	 * 
	 * @param array $value
	 * @return string of JSON
	 *
	 */
	protected function encodeData(array $value) {
		if(isset($value['collapsed']) && $value['collapsed'] === 0) unset($value['collapsed']); 	
		if(isset($value['columnWidth']) && (empty($value['columnWidth']) || $value['columnWidth'] == 100)) unset($value['columnWidth']); 
		return wireEncodeJSON($value, 0); 	
	}

	/**
	 * Does user have 'view' or 'edit' permission for this field? (internal use only)
	 * 
	 * PLEASE NOTE: this does not check that the provided $page itself is viewable or editable. 
	 * If you want that check, then use $page->viewable($field) or $page->editable($field) instead.
	 *
	 * This provides the back-end to the Field::viewable() and Field::editable() methods.
	 * This method is for internal use, please instead use the Field::viewable() or Field::editable() methods.
	 * 
	 * #pw-internal
	 * 
	 * @param Field Field to check
	 * @param string $permission Specify either 'view' or 'edit'
	 * @param Page|null $page Optionally specify a page for context
	 * @param User|null $user Optionally specify a user for context (default=current user)
	 * @return bool
	 * @throws WireException if given invalid arguments
	 *
	 */
	public function _hasPermission(Field $field, $permission, ?Page $page = null, ?User $user = null) {
		if($permission != 'edit' && $permission != 'view') {
			throw new WireException('Specify either "edit" or "view"');
		}
		if(is_null($user)) $user = $this->wire()->user;
		if($user->isSuperuser()) return true;
		if($page && $page->template && $page->template->fieldgroup->hasField($field)) {
			// make sure we have a copy of $field that is in the context of $page
			$_field = $page->template->fieldgroup->getFieldContext($field);
			if($_field) $field = $_field;
		}
		if($field->useRoles) {
			// field is access controlled
			$has = false;
			$roles = $permission == 'edit' ? $field->editRoles : $field->viewRoles;
			if($permission == 'view' && in_array($this->wire()->config->guestUserRolePageID, $roles)) {
				// if guest has view permission, then all have view permission
				$has = true; 
			} else {
				foreach($roles as $roleID) {
					if($user->hasRole($roleID)) {
						$has = true;
						break;
					}
				}
			}
		} else {
			// field is not access controlled
			$has = $permission == 'view' ? true : $user->hasPermission("page-edit");
		}
		return $has;
	}

	/**
	 * Hook called when a field has changed type
	 * 
	 * #pw-hooker
	 * 
	 * @param Field $item
	 * @param Fieldtype $fromType
	 * @param Fieldtype $toType
	 * 
	 */
	public function ___changedType(Saveable $item, Fieldtype $fromType, Fieldtype $toType) { }

	/**
	 * Hook called right before a field is about to change type
	 * 
	 * #pw-hooker
	 * 
	 * @param Field $item
	 * @param Fieldtype $fromType
	 * @param Fieldtype $toType
	 * 
	 */
	public function ___changeTypeReady(Saveable $item, Fieldtype $fromType, Fieldtype $toType) { }

	/**
	 * Get Fieldtypes compatible (for type change) with given Field
	 * 
	 * #pw-internal
	 *
	 * @param Field $field
	 * @return Fieldtypes 
	 * @since 3.0.140
	 *
	 */
	public function getCompatibleFieldtypes(Field $field) {
		$fieldtype = $field->type;
		if($fieldtype) {
			// ask fieldtype what is compatible
			/** @var Fieldtypes $fieldtypes */
			$fieldtypes = $fieldtype->getCompatibleFieldtypes($field);
			if(!$fieldtypes instanceof WireArray) {
				$fieldtypes = $this->wire(new Fieldtypes());
			}
			// ensure original is present
			$fieldtypes->prepend($fieldtype);
		} else {
			// allow all
			$fieldtypes = $this->wire()->fieldtypes;
		}
		return $fieldtypes;
	}

	/**
	 * Get FieldsIndexTools instance
	 * 
	 * #pw-internal
	 * 
	 * @return FieldsTableTools
	 * @since 3.0.150
	 * 
	 */
	public function tableTools() {
		if($this->tableTools === null) $this->tableTools = $this->wire(new FieldsTableTools());
		return $this->tableTools;
	}
	
	/**
	 * Return the list of Fieldgroups using given field.
	 *
	 * #pw-internal
	 *
	 * @param Field|int|string Field to get fieldgroups for
	 * @param bool $getCount Get count rather than FieldgroupsArray? (default=false) 3.0.182+
	 * @return FieldgroupsArray|int WireArray of Fieldgroup objects or count if requested
	 *
	 */
	public function getFieldgroups($field, $getCount = false) {

		$fieldId = $this->_fieldId($field);
		$fieldgroups = $this->wire()->fieldgroups;
		/** @var FieldgroupsArray $items */
		$items = $getCount ? null : $this->wire(new FieldgroupsArray()); 
		$ids = array();
		$count = 0;

		$sql = "SELECT fieldgroups_id FROM fieldgroups_fields WHERE fields_id=:fields_id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':fields_id', $fieldId, \PDO::PARAM_INT);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			$id = (int) $row[0];
			$ids[$id] = $id;
		}

		$query->closeCursor();

		foreach($ids as $id) {
			$fieldgroup = $fieldgroups->get($id);
			if(!$fieldgroup) continue;
			if($items) $items->add($fieldgroup);
			$count++;
		}

		return $getCount ? $count : $items;
	}

	/**
	 * Return the list of of Templates using given field.
	 *
	 * #pw-internal
	 *
	 * @param Field|int|string Field to get templates for
	 * @param bool $getCount Get count rather than FieldgroupsArray? (default=false)
	 * @return TemplatesArray|int WireArray of Template objects or count when requested.
	 * @since 3.0.195
	 *
	 */ 
	public function getTemplates($field, $getCount = false) {
		
		$fieldId = $this->_fieldId($field);
		$templates = $this->wire()->templates;
		$items = $getCount ? null : $this->wire(new TemplatesArray()); /** @var TemplatesArray $items */
		$count = 0;
		$ids = array();
		
		if(!$fieldId) return $items;

		$sql =
			"SELECT fieldgroups_fields.fieldgroups_id, templates.id AS templates_id " .
			"FROM fieldgroups_fields " .
			"JOIN templates ON templates.fieldgroups_id=fieldgroups_fields.fieldgroups_id " .
			"WHERE fieldgroups_fields.fields_id=:fields_id";

		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':fields_id', $fieldId, \PDO::PARAM_INT);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$id = (int) $row['templates_id'];
			$ids[$id] = $id;
		}

		$query->closeCursor();

		foreach($ids as $id) {
			$template = $templates->get($id);
			if(!$template) continue;
			if($items) $items->add($template);
			$count++;
		}

		return $getCount ? $count : $items;
	}

	/**
	 * Setup a new field using predefined setup name(s) from the Field’s fieldtype
	 * 
	 * If no setupName is provided then this method doesn’t do anything, but hooks to it might.
	 * 
	 * @param Field $field Newly created field
	 * @param string $setupName Setup name to apply
	 * @return bool True if setup was appled, false if not
	 * @since 3.0.213
	 * 
	 */
	protected function ___applySetupName(Field $field, $setupName = '') {
		
		$setups = $field->type->getFieldSetups();
		$setup = isset($setups[$setupName]) ? $setups[$setupName] : null;
		
		if(!$setup) return false;
		
		$title = isset($setup['title']) ? $setup['title'] : $setupName;
		$func = isset($setup['setup']) ? $setup['setup'] : null;
		
		foreach($setup as $property => $value) {
			if($property === 'title' || $property === 'setup') continue;
			$field->set($property, $value);
		}
		
		if($func && is_callable($func)) {
			$func($field);
		}
		
		$this->message("Applied setup: $title", Notice::debug | Notice::noGroup);
		
		return true;
	}

	/**
	 * Return field ID for given value (Field, field name, field ID) or 0 if none
	 * 
	 * #pw-internal
	 * 
	 * @param Field|string|int $field
	 * @return int
	 * @since 3.0.195
	 * 
	 */
	public function _fieldId($field) {
		if($field instanceof Field) {
			$fieldId = $field->id;
		} else if(ctype_digit("$field")) {
			$fieldId = (int) $field;
		} else {
			$field = $this->get($field);
			$fieldId = $field ? $field->id : 0;
		}
		return $fieldId;
	}

}
