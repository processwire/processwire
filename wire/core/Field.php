<?php namespace ProcessWire;

/**
 * ProcessWire Field
 *
 * The Field class corresponds to a record in the fields database table 
 * and is managed by the 'Fields' class.
 * 
 * #pw-summary Field represents a custom field that is used on a Page.
 * #pw-var $field
 * #pw-instantiate $field = $fields->get('field_name');
 * #pw-body Field objects are managed by the `$fields` API variable. 
 * #pw-use-constants
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 * @property int $id Numeric ID of field in the database #pw-group-properties
 * @property string $name Name of field  #pw-group-properties
 * @property string $table Database table used by the field #pw-group-properties
 * @property string $prevTable Previously database table (if field was renamed) #pw-group-properties
 * @property string $prevName Previously used name (if field was renamed), 3.0.164+ #pw-group-properties
 * @property Fieldtype|null $type Fieldtype module that represents the type of this field #pw-group-properties
 * @property Fieldtype|null $prevFieldtype Previous Fieldtype, if type was changed #pw-group-properties
 * @property int $flags Bitmask of flags used by this field #pw-group-properties
 * @property-read string $flagsStr Names of flags used by this field (readonly) #pw-group-properties
 * @property string $label Text string representing the label of the field #pw-group-properties
 * @property string $description Longer description text for the field #pw-group-properties
 * @property string $notes Additional notes text about the field #pw-group-properties
 * @property string $icon Icon name used by the field, if applicable #pw-group-properties
 * @property string $tags Tags that represent this field, if applicable (space separated string). #pw-group-properties
 * @property-read array $tagList Same as $tags property, but as an array. #pw-group-properties
 * @property bool $useRoles Whether or not access control is enabled #pw-group-access
 * @property array $editRoles Role IDs with edit access, applicable only if access control is enabled. #pw-group-access
 * @property array $viewRoles Role IDs with view access, applicable only if access control is enabled. #pw-group-access
 * @property array|null $orderByCols Columns that WireArray values are sorted by (default=null), Example: "sort" or "-created". #pw-internal
 * @property int|null $paginationLimit Used by paginated WireArray values to indicate limit to use during load. #pw-internal
 * @property array $allowContexts Names of settings that are custom configured to be allowed for context. #pw-group-properties
 * @property bool|int|null $flagUnique Non-empty value indicates request for, or presence of, Field::flagUnique flag. #pw-internal
 * @property Fieldgroup|null $_contextFieldgroup Fieldgroup field is in context for or null if not in context. #pw-internal
 * @property true|null $distinctAutojoin When true and flagAutojoin is set, a distinct autojoin will be used. 3.0.208+ #pw-internal
 *
 * Common Inputfield properties that Field objects store:  
 * @property int|bool|null $required Whether or not this field is required during input #pw-group-properties
 * @property string|null $requiredIf A selector-style string that defines the conditions under which input is required #pw-group-properties
 * @property string|null $showIf A selector-style string that defines the conditions under which the Inputfield is shown #pw-group-properties
 * @property int|null $columnWidth The Inputfield column width (percent) 10-100. #pw-group-properties
 * @property int|null $collapsed The Inputfield 'collapsed' value (see Inputfield collapsed constants). #pw-group-properties
 * @property int|null $textFormat The Inputfield 'textFormat' value (see Inputfield textFormat constants). #pw-group-properties
 * 
 * @method bool viewable(Page $page = null, User $user = null) Is the field viewable on the given $page by the given $user? #pw-group-access
 * @method bool editable(Page $page = null, User $user = null) Is the field editable on the given $page by the given $user? #pw-group-access
 * @method Inputfield getInputfield(Page $page, $contextStr = '') Get instance of the Inputfield module that collects input for this field. 
 * @method InputfieldWrapper getConfigInputfields() Get Inputfields needed to configure this field in the admin. 
 * 
 * @todo add modified date property
 *
 */
class Field extends WireData implements Saveable, Exportable {

	/**
	 * Field should be automatically joined to the page at page load time
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagAutojoin = 1;

	/**
	 * Field used by all fieldgroups - all fieldgroups required to contain this field
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagGlobal = 4;

	/**
	 * Field is a system field and may not be deleted, have it's name changed, or be converted to non-system
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagSystem = 8;

	/**
	 * Field is permanent in any fieldgroups/templates where it exists - it may not be removed from them
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagPermanent = 16;

	/**
	 * Field is access controlled
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagAccess = 32;

	/**
	 * If field is access controlled, this flag says that values are still front-end API accessible
	 * 
	 * Without this flag, non-viewable values are made blank when output formatting is ON.
	 * 
	 * #pw-group-flags
	 * 
	 */
	const flagAccessAPI = 64;

	/**
	 * If field is access controlled and user has no edit access, they can still view in the editor (if they have view permission)
	 * 
	 * Without this flag, non-editable values are simply not shown in the editor at all.
	 * 
	 * #pw-group-flags
	 * 
	 */
	const flagAccessEditor = 128;

	/**
	 * Field requires that the same value is not repeated more than once in its table 'data' column (when supported by Fieldtype)
	 * 
	 * When this flag is set and there is a non-empty $flagUnique property on the field, then it indicates a unique index 
	 * is currently present. When only this flag is present (no property), it indicates a request to remove the index and flag. 
	 * When only the property is present (no flag), it indicates a pending request to add unique index and flag. 
	 * 
	 * #pw-group-flags
	 * @since 3.0.150
	 * 
	 */
	const flagUnique = 256;

	/**
	 * Field has been placed in a runtime state where it is contextual to a specific fieldgroup and is no longer saveable
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagFieldgroupContext = 2048;

	/**
	 * Set this flag to override system/permanent flags if necessary - once set, system/permanent flags can be removed, but not in the same set().
	 * 
	 * #pw-group-flags
	 *
	 */
	const flagSystemOverride = 32768;

	/**
	 * Prefix for database tables
	 * 
	 * #pw-internal
	 * 
	 */
	const tablePrefix = 'field_';

	/**
	 * Permanent/native settings to an individual Field
	 *
	 * id: Numeric ID corresponding with id in the fields table.
	 * type: Fieldtype object or NULL if no Fieldtype assigned.
	 * label: String text label corresponding to the <label> field during input.
	 * flags:
	 * - autojoin: True if the field is automatically joined with the page, or False if it's value is loaded separately.
	 * - global: Is this field required by all Fieldgroups?
	 *
	 */
	protected $settings = array(
		'id'    => 0,
		'name'  => '',
		'label' => '',
		'flags' => 0,
		'type'  => null,
	);

	/**
	 * If the field name changed, this is the name of the previous table so that it can be renamed at save time
	 *
	 */
	protected $prevTable;

	/**
	 * If the field name changed, this is the previous name
	 * 
	 * @var string
	 * 
	 */
	protected $prevName = '';

	/**
	 * If the field type changed, this is the previous fieldtype so that it can be changed at save time
	 *
	 */
	protected $prevFieldtype;
	
	/**
	 * A specifically set table name by setTable() for override purposes
	 *
	 * @var string
	 *
	 */
	protected $setTable = '';

	/**
	 * Accessed properties, becomes array when set to true, null when set to false
	 *
	 * Used for keeping track of which properties are accessed during a request, to help determine which
	 * $data properties might no longer be in use.
	 *
	 * @var null|array
	 *
	 */
	protected $trackGets = null;

	/**
	 * Array of Role IDs referring to roles that are allowed to view contents of this field (on pages)
	 *
	 * Applicable only if the flagAccess flag is set
	 *
	 * @var array
	 *
	 */
	protected $viewRoles = array();

	/**
	 * Array of Role IDs referring to roles that are allowed to edit contents of this field (on pages)
	 *
	 * Applicable only if the flagAccess flag is set
	 *
	 * @var array
	 *
	 */
	protected $editRoles = array();

	/**
	 * Optional key=value runtime settings to provide to Inputfield (see: inputfieldSetting method)
	 * 
	 * This are runtime only and not stored in the DB.
	 * 
	 * @var array
	 * 
	 */
	protected $inputfieldSettings = array();

	/**
	 * Tags assigned to this field, keys are lowercase version of tag, values can possibly contain mixed case
	 * 
	 * @var null|array
	 * 
	 */
	protected $tagList = null;

	/**
	 * Setup name to apply when field is saved 
	 * 
	 * Set via $field->type = 'FieldtypeName.setupName'; 
	 * or applySetup() method
	 * 
	 * @var string 
	 * @since 3.0.213
	 * 
	 */
	protected $setupName = '';

	/**
	 * True if lowercase tables should be enforce, false if not (null = unset). Cached from $config
	 *
	 */
	static protected $lowercaseTables = null;

	/**
	 * Set a native setting or a dynamic data property for this Field
	 * 
	 * This can also be used directly via `$field->name = 'company';`
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $key Property name to set
	 * @param mixed $value
	 * @return Field|WireData
	 *
	 */
	public function set($key, $value) {
		
		switch($key) {
			case 'id': $this->settings['id'] = (int) $value; return $this;
			case 'name': return $this->setName($value);
			case 'data': return empty($value) ? $this : parent::set($key, $value);
			case 'type': return ($value ? $this->setFieldtype($value) : $this);
			case 'label': $this->settings['label'] = $value; return $this;
			case 'prevTable': $this->prevTable = $value; return $this;
			case 'prevName': $this->prevName = $value; return $this;
			case 'prevFieldtype': $this->prevFieldtype = $value; return $this;
			case 'flags': $this->setFlags($value); return $this;
			case 'flagsAdd': return $this->addFlag($value);
			case 'flagsDel': return $this->removeFlag($value);
			case 'icon': $this->setIcon($value); return $this;
			case 'editRoles': $this->setRoles('edit', $value); return $this; 
			case 'viewRoles': $this->setRoles('view', $value); return $this;
		}
		
		if(isset($this->settings[$key])) {
			$this->settings[$key] = $value;
		} else if($key === 'useRoles') {
			$flags = $this->flags;
			if($value) {
				$flags = $flags | self::flagAccess; // add flag
			} else {
				$flags = $flags & ~self::flagAccess; // remove flag
			}
			$this->setFlags($flags);
		} else {
			return parent::set($key, $value);
		}

		return $this;
	}

	/**
	 * Set raw setting or other value with no validation/processing
	 * 
	 * This is for use when a field is loading and needs no validation.
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @since 3.0.194
	 * 
	 */
	public function setRawSetting($key, $value) {
		if($key === 'data') {
			if(!empty($value)) parent::set($key, $value);
		} else {
			$this->settings[$key] = $value;
		}
	}

	/**
	 * Set the bitmask of flags for the field
	 * 
	 * @param int $value
	 *
	 */
	protected function setFlags($value) {
		// ensure that the system flag stays set
		$value = (int) $value;
		$override = $this->settings['flags'] & Field::flagSystemOverride;
		if(!$override) {
			if($this->settings['flags'] & Field::flagSystem) $value = $value | Field::flagSystem;
			if($this->settings['flags'] & Field::flagPermanent) $value = $value | Field::flagPermanent;
		}
		$this->settings['flags'] = $value;
	}

	/**
	 * Add the given bitmask flag
	 * 
	 * #pw-group-flags
	 * 
	 * @param int $flag
	 * @return $this
	 * 
	 */
	public function addFlag($flag) {
		$flag = (int) $flag;
		$this->setFlags($this->settings['flags'] | $flag);
		return $this;
	}

	/**
	 * Remove the given bitmask flag
	 * 
	 * #pw-group-flags
	 * 
	 * @param int $flag
	 * @return $this
	 * 
	 */
	public function removeFlag($flag) {
		$flag = (int) $flag;
		$this->setFlags($this->settings['flags'] & ~$flag);
		return $this;
	}

	/**
	 * Does this field have the given bitmask flag?
	 * 
	 * #pw-group-flags
	 * 
	 * @param int $flag
	 * @return bool
	 * 
	 */
	public function hasFlag($flag) {
		$flag = (int) $flag;
		return ($this->settings['flags'] & $flag) ? true : false;
	}

	/**
	 * Get a Field setting or dynamic data property
	 * 
	 * This can also be accessed directly, i.e. `$fieldName = $field->name;`. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
	
		if($key === 'type') { 
			if(!empty($this->settings['type'])) {
				$value = $this->settings['type'];
				if($value) $value->setLastAccessField($this);
				return $value;
			}
			return null;
		} 
	
		switch($key) {
			case 'id':
			case 'name':	
			case 'type':	
			case 'flags':	
			case 'label': return $this->settings[$key];
			case 'table': return $this->getTable();
			case 'flagsStr': return $this->wire()->fields->getFlagNames($this->settings['flags'], true);
			case 'viewRoles': 
			case 'editRoles': return $this->$key;
			case 'useRoles': return ($this->settings['flags'] & self::flagAccess) ? true : false;
			case 'prevTable':	
			case 'prevName':	
			case 'prevFieldtype': return $this->$key;
			case 'icon': return $this->getIcon(true);
			case 'tags': return $this->getTags(true);
			case 'tagList':	return $this->getTags();
		}

		if(isset($this->settings[$key])) return $this->settings[$key];
		$value = parent::get($key);
		
		if($key === 'allowContexts' && !is_array($value)) $value = array();
		if($this->trackGets && is_array($this->trackGets)) $this->trackGets($key);
		
		return $value;
	}

	/**
	 * Turn on tracking of accessed properties
	 * 
	 * #pw-internal
	 *
	 * @param bool|string $key
	 *    Omit to retrieve current trackGets value.
	 *    Specify true to enable Get tracking.
	 *    Specify false to disable (and reset) Get tracking.
	 *    Specify string key to track.
	 *
	 * @return bool|array Returns current state of trackGets when no arguments provided.
	 *    Otherwise it just returns true.
	 *
	 */
	public function trackGets($key = null) {
		if(is_null($key)) {
			// return current value
			return array_keys($this->trackGets);
		} else if($key === true) {
			// enable tracking
			if(!is_array($this->trackGets)) $this->trackGets = array();
		} else if($key === false) {
			// disable tracking
			$this->trackGets = null;
		} else if(!is_int($key) && is_array($this->trackGets)) {
			// track a key
			$this->trackGets[$key] = 1;
		}
		return true;
	}


	/**
	 * Return a key=value array of the data associated with the database table per Saveable interface
	 * 
	 * #pw-internal
	 *
	 * @return array
	 *
	 */
	public function getTableData() {
		$a = $this->settings;
		$a['data'] = $this->data;
		foreach($a['data'] as $key => $value) {
			// remove runtime data (properties beginning with underscore)
			if(strpos($key, '_') === 0) unset($a['data'][$key]);
		}
		if($this->settings['flags'] & self::flagAccess) {
			$a['data']['editRoles'] = $this->editRoles;
			$a['data']['viewRoles'] = $this->viewRoles;
		} else {
			unset($a['data']['editRoles'], $a['data']['viewRoles']); // just in case
		}
		return $a;
	}

	/**
	 * Per Saveable interface: return data for external storage
	 * 
	 * #pw-internal
	 *
	 */
	public function getExportData() {

		if($this->type) {
			$data = $this->getTableData();
			$data['type'] = $this->type->className();
		} else {
			$data['type'] = '';
		}

		if(isset($data['data'])) $data = array_merge($data, $data['data']); // flatten
		unset($data['data']);

		if($this->type) {
			$typeData = $this->type->exportConfigData($this, $data);
			foreach($typeData as $key => $value) {
				if($value === null && isset($data[$key])) {
					// prevent null from overwriting non-null, alternative for #1638
					unset($typeData[$key]);
				}
			}
			// $data = array_merge($typeData, $data); // argument order reversed per #1638...
			$data = array_merge($data, $typeData); // ...and later un-reversed per #1792
		}

		// remove named flags from data since the 'flags' property already covers them
		$flagOptions = array('autojoin', 'global', 'system', 'permanent');
		foreach($flagOptions as $name) unset($data[$name]);

		$data['flags'] = $this->flags;

		foreach($data as $key => $value) {
			// exclude properties beginning with underscore as they are assumed to be for runtime use only
			if(strpos($key, '_') === 0) unset($data[$key]);
		}

		// convert access roles from IDs to names
		if($this->useRoles) {
			$roles = $this->wire()->roles;
			foreach(array('viewRoles', 'editRoles') as $roleType) {
				if(!is_array($data[$roleType])) $data[$roleType] = array();
				$roleNames = array();
				foreach($data[$roleType] as $roleID) {
					$role = $roles->get($roleID);
					if(!$role || !$role->id) continue;
					$roleNames[] = $role->name;
				}
				$data[$roleType] = $roleNames;
			}
		}

		return $data;
	}

	/**
	 * Given an export data array, import it back to the class and return what happened
	 * 
	 * #pw-internal
	 *
	 * @param array $data
	 * @return array Returns array(
	 *    [property_name] => array(
	 *
	 *        // old value (in string comparison format)
	 *        'old' => 'old value',
	 *
	 *        // new value (in string comparison format)
	 *        'new' => 'new value',
	 *
	 *        // error message (string) or messages (array)
	 *        'error' => 'error message or blank if no error' ,
	 *    )
	 *
	 */
	public function setImportData(array $data) {

		$changes = array();
		$data['errors'] = array();
		$_data = $this->getExportData();

		// compare old data to new data to determine what's changed
		foreach($data as $key => $value) {
			if($key == 'errors') continue;
			$data['errors'][$key] = '';
			$old = isset($_data[$key]) ? $_data[$key] : '';
			if(is_array($old)) $old = wireEncodeJSON($old, true);
			$new = is_array($value) ? wireEncodeJSON($value, true) : $value;
			if($old === $new || (empty($old) && empty($new)) || (((string) $old) === ((string) $new))) continue;
			$changes[$key] = array(
				'old'   => $old,
				'new'   => $new,
				'error' => '', // to be populated by Fieldtype::importConfigData when applicable
			);
		}

		// prep data for actual import
		if(!empty($data['type']) && ((string) $this->type) != $data['type']) {
			$this->type = $this->wire()->fieldtypes->get($data['type']);
		}

		if(!$this->type) {
			if(!empty($data['type'])) $this->error("Unable to locate field type: $data[type]"); 
			$this->type = $this->wire()->fieldtypes->get('FieldtypeText');
		}

		$data = $this->type->importConfigData($this, $data);

		// populate import data
		foreach($changes as $key => $change) {
			$this->errors('clear all');
			if(isset($data[$key])) $this->set($key, $data[$key]);
			if(!empty($data['errors'][$key])) {
				$error = $data['errors'][$key];
				// just in case they switched it to an array of multiple errors, convert back to string
				if(is_array($error)) $error = implode(" \n", $error);
			} else {
				$error = $this->errors('last');
			}
			$changes[$key]['error'] = $error ? $error : '';
		}
		$this->errors('clear all');

		return $changes;
	}

	/**
	 * Set the field’s name
	 * 
	 * This method will throw a WireException when field name is a reserved word, is already in use, 
	 * is a system field, or is in some format not accepted for a field name.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $name
	 * @return Field $this
	 * @throws WireException 
	 *
	 */
	public function setName($name) {

		$fields = $this->wire()->fields;
		
		if($fields) {
			if(!ctype_alnum("$name")) {
				$name = $this->wire()->sanitizer->fieldName($name);
			}
			if($fields->isNative($name)) {
				throw new WireException("Field may not be named '$name' because it is a reserved word");
			}
			if(($f = $fields->get($name)) && $f->id != $this->id) {
				throw new WireException("Field may not be named '$name' because it is already used by another field ($f->id: $f->name)");
			}
			if(strpos($name, '__') !== false) {
				throw new WireException("Field name '$name' may not have double underscores because this usage is reserved by the core");
			}
		}
		
		if(!empty($this->settings['name']) && $this->settings['name'] != $name) {
			if($this->settings['name'] && ($this->settings['flags'] & Field::flagSystem)) {
				throw new WireException("You may not change the name of field '{$this->settings['name']}' because it is a system field.");
			}
			$this->trackChange('name');
			if($this->settings['name']) {
				$this->prevName = $this->settings['name'];
				$this->prevTable = $this->getTable(); // so that Fields can perform a table rename
			}
		}

		$this->settings['name'] = $name;
		
		return $this;
	}

	/**
	 * Set what type of field this is (Fieldtype). 
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string|Fieldtype $type Type should be either a Fieldtype object or the string name of a Fieldtype object.
	 * @return Field $this
	 * @throws WireException
	 *
	 */
	public function setFieldtype($type) {

		if($type instanceof Fieldtype) {
			// good for you

		} else if(is_string($type)) {
			if(strpos($type, '.')) {
				// FieldtypeName.setupName
				list($type, $setupName) = explode('.', $type, 2);
				$this->setSetupName($setupName);
			}
			$typeStr = $type;
			$type = $this->wire()->fieldtypes->get($type);
			if(!$type) {
				$this->error("Fieldtype '$typeStr' does not exist");
				return $this;
			}
		} else {
			throw new WireException("Invalid field type in call to Field::setFieldType");
		}

		$thisType = $this->settings['type'];
			
		if($thisType && "$thisType" != "$type") {
			if($this->trackChanges) $this->trackChange("type:$type");
			$this->prevFieldtype = $thisType;
		}
		
		$this->settings['type'] = $type;

		return $this;
	}

	/**
	 * Return the Fieldtype module representing this field’s type.
	 * 
	 * Can also be accessed directly via `$field->type`. 
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return Fieldtype|null|string
	 * @since 3.0.16 Added for consistency, but all versions can still use $field->type. 
	 * 
	 */
	public function getFieldtype() {
		return $this->type; 
	}

	/**
	 * Get this field in context of a Page/Template
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param Page|Template|Fieldgroup|string $for Specify Page, Template, or template name string
	 * @param string $namespace Optional namespace (internal use)
	 * @param bool $has Return boolean rather than Field to check if context exists? (default=false)
	 * @return Field|bool
	 * @since 3.0.162
	 * @see Fieldgroup::getFieldContext(), Field::hasContext()
	 * 
	 */
	public function getContext($for, $namespace = '', $has = false) {
		/** @var Fieldgroup|null $fieldgroup */
		$fieldgroup = null;
		if(is_string($for)) {
			$for = $this->wire()->templates->get($for);
		}
		if($for instanceof Page) {
			/** @var Page $context */
			$template = $for instanceof NullPage ? null : $for->template;
			if(!$template) throw new WireException('Page must have template to get context');
			$fieldgroup = $template->fieldgroup;
		} else if($for instanceof Template) {
			/** @var Template $context */
			$fieldgroup = $for->fieldgroup;
		} else if($for instanceof Fieldgroup) {
			$fieldgroup = $for;
		}
		if(!$fieldgroup) throw new WireException('Cannot get Fieldgroup for field context'); 
		
		if($has) return $fieldgroup->hasFieldContext($this->id, $namespace);

		return $fieldgroup->getFieldContext($this->id, $namespace);
	}

	/**
	 * Does this field have context settings for given Page/Template?
	 *
	 * #pw-group-retrieval
	 *
	 * @param Page|Template|Fieldgroup|string $for Specify Page, Template, or template name string
	 * @param string $namespace Optional namespace (internal use)
	 * @return Field|bool
	 * @since 3.0.163
	 * @see Field::getContext()
	 *
	 */
	public function hasContext($for, $namespace = '') {
		return $this->getContext($for, $namespace, true);
	}

	/**
	 * Get all contexts this field is used in
	 * 
	 * @return array Array of 'fieldgroup-name' => [ contexts ]
	 * @since 3.0.182
	 * 
	 */
	public function getContexts() {
		$contexts = array();
		foreach($this->wire()->fieldgroups as $fieldgroup) {
			/** @var Fieldgroup $fieldgroup */
			$context = $fieldgroup->getFieldContextArray($this->id);
			if(empty($context)) continue;
			$contexts[$fieldgroup->name] = $context;
		}
		return $contexts;	
	}

	/**
	 * Set the roles that are allowed to view or edit this field on pages.
	 *
	 * Applicable only if the `Field::flagAccess` is set to this field's flags.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $type Must be either "view" or "edit"
	 * @param PageArray|array|null $roles May be a PageArray of Role objects or an array of Role IDs.
	 * @throws WireException if given invalid argument
	 *
	 */
	public function setRoles($type, $roles) {
		if(empty($roles)) $roles = array();
		if(!WireArray::iterable($roles)) {
			throw new WireException("setRoles expects PageArray or array of Role IDs");
		}
		$ids = array();
		foreach($roles as $role) {
			if(is_int($role) || (is_string($role) && ctype_digit("$role"))) {
				$ids[] = (int) $role;
			} else if($role instanceof Role) {
				$ids[] = (int) $role->id;
			} else if(is_string($role) && strlen($role)) {
				$rolePage = $this->wire()->roles->get($role); 
				if($rolePage instanceof Role && $rolePage->id) {
					$ids[] = $rolePage->id;
				} else {
					$this->error("Unknown role '$role'"); 
				}
			} else {
				// invalid
			}
		}
		if($type == 'view') {
			$guestID = $this->wire()->config->guestUserRolePageID;
			// if guest is present, then that's inclusive of all, no need to store others in viewRoles
			if(in_array($guestID, $ids)) $ids = array($guestID); 
			if($this->viewRoles != $ids) {
				$this->viewRoles = $ids;
				$this->trackChange('viewRoles');
			}
		} else if($type == 'edit') {
			if($this->editRoles != $ids) {
				$this->editRoles = $ids;
				$this->trackChange('editRoles');
			}
		} else {
			throw new WireException("setRoles expects either 'view' or 'edit' (arg 0)");
		}
	}

	/**
	 * Is this field viewable?
	 * 
	 * #pw-group-access
	 *
	 * - To maximize efficiency check that `$field->useRoles` is true before calling this.  
	 * - If you have already verified that the page is viewable, omit or specify null for $page argument.
	 * - **Please note:** this does not check that the provided $page itself is viewable. If you want that 
	 *   check, then use `$page->viewable($field)` instead.
	 * 
	 * @param Page|null $page Optionally specify a Page for context (i.e. Is field viewable on $page?)
	 * @param User|null $user Optionally specify a different user for context (default=current user)
	 * @return bool True if viewable, false if not
	 * 
	 */
	public function ___viewable(?Page $page = null, ?User $user = null) {
		return $this->wire()->fields->_hasPermission($this, 'view', $page, $user);
	}

	/**
	 * Is this field editable?
	 * 
	 * - To maximize efficiency check that `$field->useRoles` is true before calling this.
	 * - If you have already verified that the page is editable, omit or specify null for $page argument.
	 * - **Please note:** this does not check that the provided $page itself is editable. If you want that 
	 *   check, then use `$page->editable($field)` instead.
	 * 
	 * #pw-group-access
	 *
	 * @param Page|null $page Optionally specify a Page for context
	 * @param User|null $user Optionally specify a different user (default = current user)
	 * @return bool
	 *
	 */
	public function ___editable(?Page $page = null, ?User $user = null) {
		return $this->wire()->fields->_hasPermission($this, 'edit', $page, $user);
	}
	
	/**
	 * Save this field’s settings and data in the database. 
	 *
	 * To hook this save, hook to `Fields::save()` instead.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @return bool
	 *
	 */
	public function save() {
		return $this->wire()->fields->save($this); 
	}

	/**
	 * Return the number of Fieldgroups this field is used in.
	 *
	 * Primarily used to check if the Field is deletable. 
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return int
	 *
	 */ 
	public function numFieldgroups() {
		return $this->getFieldgroups(true); 
	}

	/**
	 * Return the list of Fieldgroups using this field.
	 * 
	 * #pw-group-retrieval
	 *
	 * @param bool $getCount Get count rather than FieldgroupsArray? (default=false) 3.0.182+
	 * @return FieldgroupsArray|int WireArray of Fieldgroup objects or count if requested
	 *
	 */ 
	public function getFieldgroups($getCount = false) {
		return $this->wire()->fields->getFieldgroups($this, $getCount);
	}

	/**
	 * Return the list of of Templates using this field.
	 * 
	 * #pw-group-retrieval
	 *
	 * @param bool $getCount Get count rather than FieldgroupsArray? (default=false) 3.0.182+
	 * @return TemplatesArray|int WireArray of Template objects or count when requested. 
	 *
	 */ 
	public function getTemplates($getCount = false) {
		return $this->wire()->fields->getTemplates($this, $getCount);
	}

	/**
	 * Return the default value for this field (if set), or null otherwise. 
	 * 
	 * #pw-internal
	 * 
	 * @deprecated Use $field->type->getDefaultValue($page, $field) instead. 
	 *
	 */
	public function getDefaultValue() {
		$value = $this->get('default'); 
		if($value) return $value; 
		return null;
	}

	/**
	 * Get the Inputfield module used to collect input for this field.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param Page $page Page that the Inputfield is for. 
	 * @param string $contextStr Optional context string to append to the Inputfield's name/id (for repeaters and such). 
	 * @return Inputfield|null 
	 *
	 */
	public function ___getInputfield(Page $page, $contextStr = '') {

		if(!$this->type) return null;
		
		// check access control
		$locked = false;
		if($this->useRoles && !$this->editable($page)) {
			// $this->message("not editable: " . $this->name);
			if(($this->flags & self::flagAccessEditor) && $this->viewable($page)) {
				// Inputfield is viewable but not editable
				$locked = true;
			} else {
				// Inputfield is neither editable nor viewable
				$locked = 'hidden';
			}
		}
		
		$inputfield = $this->type->getInputfield($page, $this);
		if(!$inputfield) return null; 

		// predefined field settings
		$inputfield->attr('name', $this->name . $contextStr); 
		$inputfield->set('label', $this->label);
		if($contextStr) {
			// keep track of original field name in Inputfields that are are renamed by context
			if(!$inputfield->attr('data-field-name')) $inputfield->attr('data-field-name', $this->name);
		}

		// just in case an Inputfield needs to know its Fieldtype/Field context, or lack of it
		$inputfield->set('hasFieldtype', $this->type);
		$inputfield->set('hasField', $this);
		$inputfield->set('hasPage', $page); 
		
		// custom field settings
		foreach($this->data as $key => $value) {
			if($inputfield instanceof InputfieldWrapper) {
				$has = $inputfield->hasSetting($key) || $inputfield->hasAttribute($key);
			} else {
				$has = $inputfield->has($key);
			}
			if($has) {
				if(is_array($this->trackGets)) $this->trackGets($key); 
				$inputfield->set($key, $value); 
			}
		}

		if($locked === 'hidden') {
			// Inputfield should not be shown
			$inputfield->collapsed = Inputfield::collapsedHidden;
		} else if($locked) {
			// Inputfield is locked as a result of access control
			$collapsed = $inputfield->getSetting('collapsed'); 
			$ignoreCollapsed = array(
				Inputfield::collapsedNoLocked, 
				Inputfield::collapsedBlankLocked, 
				Inputfield::collapsedYesLocked, 
				Inputfield::collapsedHidden
			);
			if(!in_array($collapsed, $ignoreCollapsed)) {
				// Inputfield is not already locked or hidden, convert to locked equivalent
				if($collapsed == Inputfield::collapsedYes) {
					$collapsed = Inputfield::collapsedYesLocked;
				} else if($collapsed == Inputfield::collapsedBlank) {
					$collapsed = Inputfield::collapsedBlankLocked;
				} else if($collapsed == Inputfield::collapsedNo) {
					$collapsed = Inputfield::collapsedNoLocked;
				} else {
					$collapsed = Inputfield::collapsedYesLocked;
				}
				$inputfield->collapsed = $collapsed;
			}
		}
	
		if(count($this->inputfieldSettings)) {
			// runtime-only settings to Inputfield (these are not stored in DB)
			foreach($this->inputfieldSettings as $name => $value) {
				$inputfield->set($name, $value);
			}
		}
		
		if($contextStr) {
			// update dependency strings for the context 
			foreach(array('showIf', 'requiredIf') as $depType) {
				$theIf = $inputfield->getSetting($depType);
				if(empty($theIf)) continue;
				$theIf = preg_replace('/([_|a-zA-Z0-9]+)*([-._|a-zA-Z0-9]*)([=!%*<>]+)/', '$1' . $contextStr . '$2$3', $theIf);
				if(stripos($theIf, 'forpage.') !== false) {
					// de-contextualize if the field name starts with 'forpage.' as used by 
					// repeaters (or others) referring to page in editor rather than item page
					$theIf = preg_replace('/forpage\.([_.|a-z0-9]+)' . $contextStr . '([=!%*<>]+)/i', '$1$2', $theIf);
				}
				$inputfield->set($depType, $theIf);
			}
		}

		return $inputfield; 
	}

	/**
	 * Get or set a runtime-only setting that will be sent to the Inputfield during the getInputfield() call
	 * 
	 * #pw-internal
	 * 
	 * @param string $name Specify setting name to get or set, or '*' to get all.
	 * @param null|mixed $value Specify value, or 'clear' to clear setting(s) described in $name argument.
	 * @return null|array|bool|mixed Returns setting value, null if not found, true if set or clear requested, or array if all settings requested.
	 * 
	 */
	public function inputfieldSetting($name, $value = null) {
		if($name === '*') {
			// get or clear ALL settings
			if($value === 'clear') {
				$this->inputfieldSettings = array();
				return true;
			} else {
				return $this->inputfieldSettings;
			}
		} else if(is_null($value)) {
			// get a setting, or return null if not found
			return isset($this->inputfieldSettings[$name]) ? $this->inputfieldSettings[$name] : null;
		} else if($value === 'clear') {
			// clear a setting
			unset($this->inputfieldSettings[$name]);	
			return true;
		} else {
			// set a named setting
			$this->inputfieldSettings[$name] = $value;
			return true;
		}
	}

	/**
	 * Get any Inputfields needed to configure the field in the admin.
	 * 
	 * #pw-group-retrieval
	 *
	 * @return InputfieldWrapper
	 *
	 */
	public function ___getConfigInputfields() {

		$wrapper = $this->wire(new InputfieldWrapper());
		$fieldgroupContext = $this->flags & Field::flagFieldgroupContext; 
		
		if($fieldgroupContext) {
			$allowContext = $this->type->getConfigAllowContext($this); 
			if(!is_array($allowContext)) $allowContext = array();
			$allowContext = array_merge($allowContext, $this->allowContexts); 
		} else {
			$allowContext = array();
		}

		if(!$fieldgroupContext || count($allowContext)) {
		
			/** @var InputfieldWrapper $inputfields */
			$inputfields = $this->wire(new InputfieldWrapper());
			if(!$fieldgroupContext) $inputfields->head = $this->_('Field type details');
			$inputfields->attr('title', $this->_('Details'));
			$inputfields->attr('id+name', 'fieldtypeConfig');
			$remainingNames = array();
			foreach($allowContext as $name) $remainingNames[$name] = $name;

			try {
				$fieldtypeInputfields = $this->type->getConfigInputfields($this); 
				if(!$fieldtypeInputfields) $fieldtypeInputfields = $this->wire(new InputfieldWrapper());
				$configArray = $this->type->getConfigArray($this); 
				if(count($configArray)) {
					/** @var InputfieldWrapper $w */
					$w = $this->wire(new InputfieldWrapper());
					$w->importArray($configArray);
					$w->populateValues($this);
					$fieldtypeInputfields->import($w);
				}
				foreach($fieldtypeInputfields as $inputfield) {
					/** @var Inputfield $inputfield */
					if($fieldgroupContext && !in_array($inputfield->name, $allowContext)) continue;
					$inputfields->append($inputfield);
					unset($remainingNames[$inputfield->name]);
				}
				// now capture those that may have been stuck in a fieldset
				if($fieldgroupContext) {
					foreach($remainingNames as $name) {
						if($inputfields->getChildByName($name)) continue;
						$inputfield = $fieldtypeInputfields->getChildByName($name);
						if(!$inputfield) continue;
						$inputfields->append($inputfield);
						unset($remainingNames[$inputfield->name]);
					}
				}
				
			} catch(\Exception $e) {
				$this->trackException($e, false, true); 
			}

			if(count($inputfields)) $wrapper->append($inputfields); 
		}

		/** @var InputfieldWrapper $inputfields */
		$inputfields = $this->wire(new InputfieldWrapper());
		$dummyPage = $this->wire()->pages->get('/'); // only using this to satisfy param requirement 

		$inputfield = $this->getInputfield($dummyPage);
		if($inputfield) {
			if($fieldgroupContext) {
				$allowContext = array('visibility', 'collapsed', 'columnWidth', 'required', 'requiredIf', 'showIf');
				$allowContext = array_merge($allowContext, $this->allowContexts, $inputfield->getConfigAllowContext($this)); 
			} else {
				$allowContext = array();
				$inputfields->head = $this->_('Input field settings');
			}
			$remainingNames = array();
			foreach($allowContext as $name) {
				$remainingNames[$name] = $name;
			}
			$inputfields->attr('title', $this->_('Input')); 
			$inputfields->attr('id+name', 'inputfieldConfig');
			$inputfieldInputfields = $inputfield->getConfigInputfields();
			if(!$inputfieldInputfields) {
				/** @var InputfieldWrapper $inputfieldInputfields */
				$inputfieldInputfields = $this->wire(new InputfieldWrapper());
			}
			$configArray = $inputfield->getConfigArray(); 
			if(count($configArray)) {
				/** @var InputfieldWrapper $w */
				$w = $this->wire(new InputfieldWrapper());
				$w->importArray($configArray);
				$w->populateValues($this);
				$inputfieldInputfields->import($w);
			}
			foreach($inputfieldInputfields as $i) { 
				/** @var Inputfield $i */
				if($fieldgroupContext && !in_array($i->name, $allowContext)) continue; 
				$inputfields->append($i); 
				unset($remainingNames[$i->name]); 
			}
			if($fieldgroupContext) {
				foreach($remainingNames as $name) {
					if($inputfields->getChildByName($name)) continue;
					$inputfield = $inputfieldInputfields->getChildByName($name);
					if(!$inputfield) continue;
					$inputfields->append($inputfield);
					unset($remainingNames[$inputfield->name]);
				}
			}
		}

		$wrapper->append($inputfields); 

		return $wrapper; 
	}

	/**
	 * Get the database table used by this field.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return string
	 * @throws WireException
	 * 
	 */
	public function getTable() {
		if(self::$lowercaseTables === null) {
			self::$lowercaseTables = $this->wire()->config->dbLowercaseTables ? true : false;
		}
		if(!empty($this->setTable)) {
			$table = $this->setTable;
		} else {
			$name = $this->settings['name'];
			$length = strlen($name);
			if(!$length) throw new WireException("Field 'name' is required");
			if($length > 58) $name = substr($name, 0, 58); // 'field_' + 58 = 64 max
			$table = self::tablePrefix . $name;
		}
		if(self::$lowercaseTables) $table = strtolower($table); 
		return $table;
	}

	/**
	 * Set an override table name, or omit (or null) to restore default table name
	 * 
	 * #pw-group-advanced
	 * 
	 * @param null|string $table
	 * 
	 */
	public function setTable($table = null) {
		$table = empty($table) ? '' : $this->wire()->sanitizer->fieldName($table);
		if(strlen($table) > 64) $table = substr($table, 0, 64);
		$this->setTable = $table;
	}

	/**
	 * The string value of a Field is always it's name
	 *
	 */
	public function __toString() {
		return $this->settings['name']; 
	}

	/**
	 * Isset
	 * 
	 * @param string $key
	 * @return bool
	 * 
	 */
	public function __isset($key) {
		if(parent::__isset($key)) return true; 
		return isset($this->settings[$key]); 
	}
	
	/**
	 * Return field label, description or notes for language
	 *
	 * @param string $property Specify either label, description or notes
	 * @param Page|Language $language Optionally specify a language. If not specified user's current language is used.
	 * @return string
	 *
	 */
	protected function getText($property, $language = null) {
		if(is_null($language)) {
			$language = $this->wire()->languages ? $this->wire()->user->language : null;
		}
		if($language) {
			$value = (string) $this->get("$property$language");
			if(!strlen($value)) $value = (string) $this->$property;
		} else {
			$value = (string) $this->$property;
		}
		if($property === 'label' && !strlen($value)) $value = $this->name;
		return $value;
	}
	
	/**
	 * Set a field label, description or notes for language
	 *
	 * @param string $property Specify either label, description or notes
	 * @param string $value Text to set for property
	 * @param Page|Language $language Optionally specify a language. If not specified default language is used. 
	 *
	 */
	protected function setText($property, $value, $language = null) {
		$languages = $this->wire()->languages;
		if($languages && $language != null) {
			if(is_string($language) || is_int($language)) $language = $languages->get($language);
			if($language && (!$language->id || $language->isDefault())) $language = null;
		} else {
			$language = null;
		}
		if(is_null($language)) $language = '';
		$this->set("$property$language", $value); 
	}

	/**
	 * Get field label for current language, or another specified language.
	 *
	 * This is different from `$field->label` in that it knows about languages (when installed).
	 * 
	 * #pw-group-retrieval
	 *
	 * @param Page|Language $language Optionally specify a language. If not specified user's current language is used.
	 * @return string
	 *
	 */
	public function getLabel($language = null) {
		return $this->getText('label', $language);
	}

	/**
	 * Return field description for current language, or another specified language.
	 *
	 * This is different from `$field->description` in that it knows about languages (when installed).
	 * 
	 * #pw-group-retrieval
	 *
	 * @param Page|Language $language Optionally specify a language. If not specified user's current language is used.
	 * @return string
	 *
	 */
	public function getDescription($language = null) {
		return $this->getText('description', $language);
	}

	/**
	 * Return field notes for current language, or another specified language. 
	 *
	 * This is different from `$field->notes` in that it knows about languages (when installed).
	 * 
	 * #pw-group-retrieval
	 *
	 * @param Page|Language $language Optionally specify a language. If not specified user's current language is used.
	 * @return string
	 *
	 */
	public function getNotes($language = null) {
		return $this->getText('notes', $language);
	}

	/**
	 * Return the icon used by this field, or blank if none.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param bool $prefix Whether or not you want the icon prefix included (i.e. "fa-")
	 * @return mixed|string
	 * 
	 */
	public function getIcon($prefix = false) {
		$icon = parent::get('icon'); 
		if(empty($icon)) return '';
		if(strpos($icon, 'fa-') === 0) $icon = str_replace('fa-', '', $icon);
		if(strpos($icon, 'icon-') === 0) $icon = str_replace('icon-', '', $icon); 
		return $prefix ? "fa-$icon" : $icon;
	}
	
	/**
	 * Set label, optionally for a specific language
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $text Text to set
	 * @param Language|string|int|null $language Language to use
	 * @since 3.0.16 Added for consistency, all versions can still set property directly. 
	 *
	 */
	public function setLabel($text, $language = null) {
		$this->setText('label', $text, $language);
	}

	/**
	 * Set description, optionally for a specific language
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $text Text to set
	 * @param Language|string|int|null $language Language to use
	 * @since 3.0.16 Added for consistency, all versions can still set property directly.
	 *
	 */
	public function setDescription($text, $language = null) {
		$this->setText('description', $text, $language);
	}
	
	/**
	 * Set notes, optionally for a specific language
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $text Text to set
	 * @param Language|string|int|null $language Language to use
	 * @since 3.0.16 Added for consistency, all versions can still set property directly.
	 *
	 */
	public function setNotes($text, $language = null) {
		$this->setText('notes', $text, $language);
	}

	/**
	 * Set the icon for this field
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $icon Icon name
	 * @return $this
	 * 
	 */
	public function setIcon($icon) {
		// store the non-prefixed version
		if(strlen("$icon")) {
			if(strpos($icon, 'icon-') === 0) $icon = str_replace('icon-', '', $icon);
			if(strpos($icon, 'fa-') === 0) $icon = str_replace('fa-', '', $icon);
			$icon = $this->wire()->sanitizer->pageName($icon);
		}
		parent::set('icon', "$icon"); 
		return $this; 
	}

	/**
	 * Get tags
	 * 
	 * @param bool|string $getString Optionally specify true for space-separated string, or delimiter string (default=false)
	 * @return array|string Returns array of tags unless $getString option is requested
	 * @since 3.0.106
	 * 
	 */
	public function getTags($getString = false) {
		if($this->tagList === null) {
			$tagList = $this->setTags(parent::get('tags'));
		} else {
			$tagList = $this->tagList;
		}
		if($getString !== false) {
			$delimiter = $getString === true ? ' ' : $getString;
			return implode($delimiter, $tagList);
		}
		return $tagList;
	}

	/**
	 * Set all tags
	 * 
	 * #pw-internal
	 * 
	 * @param array|string $tagList Array of tags to add (or space-separated string)
	 * @param bool $reindex Set to false to set given $tagsList exactly as-is (assumes it's already in correct format)
	 * @return array Array of tags that were set
	 * @since 3.0.106
	 * 
	 */
	public function setTags($tagList, $reindex = true) {
		$textTools = $this->wire()->sanitizer->getTextTools();
		if($tagList === null || $tagList === '') {
			$tagList = array();
		} else if(!is_array($tagList)) {
			$tagList = explode(' ', $tagList);
		}
		if($reindex && count($tagList)) {
			$tags = array();
			foreach($tagList as $tag) {
				$tag = trim($tag);
				if(strlen($tag)) $tags[$textTools->strtolower($tag)] = $tag;
			}
			$tagList = $tags;
		}
		if($this->tagList !== $tagList) {
			$this->tagList = $tagList;
			parent::set('tags', implode(' ', $tagList)); 
			$this->wire()->fields->getTags('reset');
		}
		return $tagList;
	}

	/**
	 * Add one or more tags
	 * 
	 * @param string $tag
	 * @return array Returns current tag list
	 * @since 3.0.106
	 * 
	 */
	public function addTag($tag) {
		$textTools = $this->wire()->sanitizer->getTextTools();
		$tagList = $this->getTags();
		$tagList[$textTools->strtolower($tag)] = $tag;
		$this->setTags($tagList, false);
		return $tagList;
	}

	/**
	 * Return true if this field has the given tag or false if not
	 * 
	 * @param string $tag
	 * @return bool
	 * @since 3.0.106
	 * 
	 */
	public function hasTag($tag) {
		$textTools = $this->wire()->sanitizer->getTextTools();
		$tagList = $this->getTags();
		return isset($tagList[$textTools->strtolower(trim(ltrim($tag, '-')))]);
	}

	/**
	 * Remove a tag
	 * 
	 * @param string $tag
	 * @return array Returns current tag list
	 * @since 3.0.106
	 * 
	 */
	public function removeTag($tag) {
		$textTools = $this->wire()->sanitizer->getTextTools();
		$tagList = $this->getTags();
		$tag = $textTools->strtolower($tag);
		if(!isset($tagList[$tag])) return $tagList;
		unset($tagList[$tag]); 
		return $this->setTags($tagList, false);
	}

	/**
	 * Get URL to edit field in the admin
	 * 
	 * @param array|bool|string $options Specify array of options, string for find option, or bool for http option.
	 *  - `find` (string): Name of field to find in editor form 
	 *  - `http` (bool): True to force inclusion of scheme and hostname
	 * @return string
	 * @since 3.0.151
	 * 
	 */
	public function editUrl($options = array()) {
		if(is_string($options)) $options = array('find' => $options);
		if(is_bool($options)) $options = array('http' => $options);
		if(!is_array($options)) $options = array();
		$url = $this->wire()->config->urls(empty($options['http']) ? 'admin' : 'httpAdmin');
		$url .= "setup/field/edit?id=$this->id";
		if(!empty($options['find'])) $url .= '#find-' . $this->wire()->sanitizer->fieldName($options['find']);
		return $url;
	}

	/**
	 * Set setup name from Fieldtype to apply when field is saved 
	 * 
	 * @param string $setupName Setup name or omit to instead get the current value
	 * @return string Returns current value
	 * 
	 */
	public function setSetupName($setupName = null) {
		if($setupName !== null) $this->setupName = $setupName;
		return $this->setupName;
	}

	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * This is used when you print_r() an object instance.
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		$info = $this->settings;
		$info['flags'] = $info['flags'] ? "$this->flagsStr ($info[flags])" : "";
		$info = array_merge($info, parent::__debugInfo());
		if($this->prevTable) $info['prevTable'] = $this->prevTable;
		if($this->prevName) $info['prevName'] = $this->prevName;
		if($this->prevFieldtype) $info['prevFieldtype'] = (string) $this->prevFieldtype;
		if(!empty($this->trackGets)) $info['trackGets'] = $this->trackGets;
		if($this->useRoles) {
			$info['viewRoles'] = $this->viewRoles;
			$info['editRoles'] = $this->editRoles; 
		}
		return $info; 
	}
	
	public function debugInfoSmall() {
		return array(
			'id' => $this->id, 
			'name' => $this->name,
			'label' => $this->getLabel(), 
			'type' => $this->type ? wireClassName($this->type) : '',
		);
	}
	
}
