<?php namespace ProcessWire;

/**
 * ProcessWire Fieldgroups
 *
 * #pw-summary Maintains collections of Fieldgroup object instances and represents the `$fieldgroups` API variable.
 * #pw-body For full details on all methods available in a Fieldgroup, be sure to also see the `WireArray` class.
 * #pw-var $fieldgroups
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 * @method Fieldgroup clone(Saveable $item, $name = '')
 * @method int saveContext(Fieldgroup $fieldgroup)
 * @method array getExportData(Fieldgroup $fieldgroup)
 * @method array setImportData(Fieldgroup $fieldgroup, array $data)
 * 
 * @method void fieldRemoved(Fieldgroup $fieldgroup, Field $field)
 * @method void fieldAdded(Fieldgroup $fieldgroup, Field $field)
 *
 *
 */

class Fieldgroups extends WireSaveableItemsLookup {

	/**
	 * Instance of FieldgroupsArray
	 * 
	 * @var FieldgroupsArray
	 *
	 */
	protected $fieldgroupsArray = null;

	/**
	 * Init
	 * 
	 */
	public function init() {
		$this->getWireArray();
	}

	/**
	 * Get the DatabaseQuerySelect to perform the load operation of items
	 *
	 * @param Selectors|string|null $selectors Selectors or a selector string to find, or NULL to load all. 
	 * @return DatabaseQuerySelect
	 *
	 */
	protected function getLoadQuery($selectors = null) {
		$query = parent::getLoadQuery($selectors); 
		$lookupTable = $this->wire()->database->escapeTable($this->getLookupTable()); 
		$query->select("$lookupTable.data"); // QA
		return $query; 
	}

	/**
	 * Load all the Fieldgroups from the database
	 *
	 * The loading is delegated to WireSaveableItems.
	 * After loaded, we check for any 'global' fields and add them to the Fieldgroup, if not already there.
	 *
	 * @param WireArray $items
	 * @param Selectors|string|null $selectors Selectors or a selector string to find, or NULL to load all. 
	 * @return WireArray Returns the same type as specified in the getAll() method.
	 *
	 */
	protected function ___load(WireArray $items, $selectors = null) {
		$items = parent::___load($items, $selectors); 	
		return $items; 
	}

	/**
	 * Per WireSaveableItems interface, return all available Fieldgroup instances
	 * 
	 * @return FieldgroupsArray
	 *
	 */
	public function getAll() {
		if($this->useLazy()) $this->loadAllLazyItems();
		return $this->getWireArray();
	}
	
	/**
	 * @return WireArray|FieldgroupsArray
	 *
	 */
	public function getWireArray() {
		if($this->fieldgroupsArray === null) {
			$this->fieldgroupsArray = new FieldgroupsArray();
			$this->wire($this->fieldgroupsArray);
			$this->load($this->fieldgroupsArray);
		}
		return $this->fieldgroupsArray;
	}

	/**
	 * Per WireSaveableItems interface, create a blank instance of a Fieldgroup
	 * 
	 * @return Fieldgroup
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Fieldgroup()); 
	}

	/**
	 * Per WireSaveableItems interface, return the name of the table that Fieldgroup instances are stored in
	 * 
	 * @return string
	 *
	 */
	public function getTable() {
		return 'fieldgroups';
	}

	/**
	 * Per WireSaveableItemsLookup interface, return the name of the table that Fields are linked to Fieldgroups 
	 * 
	 * @return string
	 *
	 */
	public function getLookupTable() {
		return 'fieldgroups_fields';
	}

	/**
	 * Get the number of templates using the given fieldgroup. 
	 *
	 * Primarily used to determine if the Fieldgroup is deleteable. 
	 *
	 * @param Fieldgroup $fieldgroup
	 * @return int
	 *
	 */
	public function getNumTemplates(Fieldgroup $fieldgroup) {
		$templates = $this->wire()->templates;
		$num = 0;
		
		foreach($templates->getAllValues('fieldgroups_id', 'id') as /* $templateId => */ $fieldgroupId) {
			if($fieldgroupId == $fieldgroup->id) $num++;
		}
		
		return $num;
	}

	/**
	 * Given a Fieldgroup, return a TemplatesArray of all templates using the Fieldgroup
	 *
	 * @param Fieldgroup $fieldgroup
	 * @return TemplatesArray
	 *
	 */
	public function getTemplates(Fieldgroup $fieldgroup) {
		$templates = $this->wire()->templates;
		$items = $this->wire(new TemplatesArray()); /** @var TemplatesArray $items */
		
		foreach($templates->getAllValues('fieldgroups_id', 'id') as $templateId => $fieldgroupId) {
			if($fieldgroupId == $fieldgroup->id) {
				$template = $templates->get($templateId);
				$items->add($template);
			}
		}
		
		return $items; 
	}

	/**
	 * Get all field names used by given fieldgroup
	 * 
	 * Use this when you want to identify the field names (or IDs) without loading the fieldgroup or fields in it.
	 * 
	 * @param string|int|Fieldgroup $fieldgroup Fieldgroup name, ID or object
	 * @return array Returned array of field names indexed by field ID
	 * @since 3.0.194
	 * 
	 */
	public function getFieldNames($fieldgroup) {
		$fieldNames = array();
		$useLazy = $this->useLazy();
		if(!$useLazy && !is_object($fieldgroup)) $fieldgroup = $this->get($fieldgroup);
		if($fieldgroup instanceof Fieldgroup) {
			foreach($fieldgroup as $field) {
				/** @var Field $field */
				$fieldNames[$field->id] = $field->name;
			}
			return $fieldNames;
		}
		$fieldIds = array();
		if(ctype_digit("$fieldgroup") && $useLazy) {
			foreach(array_keys($this->lazyItems) as $key) {
				$row = &$this->lazyItems[$key];
				if("$row[id]" === "$fieldgroup" && $row['fields_id']) {
					$fieldIds[] = (int) $row['fields_id'];
				}
			}
		} else if($fieldgroup) {
			foreach(array_keys($this->lazyItems) as $key) {
				$row = &$this->lazyItems[$key];
				if("$row[name]" === "$fieldgroup" && $row['fields_id']) {
					$fieldIds[] = (int) $row['fields_id'];
				}
			}
		}
		if(count($fieldIds)) {
			$fieldNames = $this->wire()->fields->getAllValues('name', 'id', 'id', $fieldIds);
		}
		return $fieldNames;
	}

	/**
	 * Save the Fieldgroup to DB
	 *
	 * If fields were removed from the Fieldgroup, then track them down and remove them from the associated field_* tables
	 *
	 * @param Saveable $item Fieldgroup to save
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Saveable $item) {

		$database = $this->wire()->database;

		/** @var Fieldgroup $fieldgroup */
		$fieldgroup = $item;
		$datas = array();
		$fieldsAdded = array();
		$fieldsRemoved = array();
		
		if($fieldgroup->id && $fieldgroup->removedFields) {

			foreach($this->wire()->templates as $template) {
				if($template->fieldgroup->id !== $fieldgroup->id) continue; 
				foreach($fieldgroup->removedFields as $field) {
					/** @var Field $field */
					// make sure the field is valid to delete from this template
					$error = $this->isFieldNotRemoveable($field, $fieldgroup, $template);
					if($error !== false) throw new WireException("$error Save of fieldgroup changes aborted.");
					/** @var Fieldtype $fieldtype */
					$fieldtype = $field->type;
					if($fieldtype) $fieldtype->deleteTemplateField($template, $field); 
					$fieldgroup->finishRemove($field); 
					$fieldsRemoved[] = $field;
				}
			}

			$fieldgroup->resetRemovedFields();
		}

		if($fieldgroup->id) { 
			// load context data to populate back after fieldgroup save
			$sql = 'SELECT fields_id, data FROM fieldgroups_fields WHERE fieldgroups_id=:fieldgroups_id'; 
			$query = $database->prepare($sql); 
			$query->bindValue(':fieldgroups_id', (int) $fieldgroup->id, \PDO::PARAM_INT); 
			$query->execute();
			/** @noinspection PhpAssignmentInConditionInspection */
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$fields_id = (int) $row['fields_id'];
				$datas[$fields_id] = $row['data'];
			}
			$query->closeCursor();
		}
		
		$result = parent::___save($fieldgroup);
		
		// identify any fields added
		foreach($fieldgroup as $field) {
			if(!array_key_exists($field->id, $datas)) {
				$fieldsAdded[] = $field;
			}
		}

		if(count($datas)) {
			// restore context data
			$fieldgroups_id = (int) $fieldgroup->id; 
			foreach($datas as $fields_id => $data) {
				$sql = "UPDATE fieldgroups_fields SET data=:data WHERE fieldgroups_id=:fieldgroups_id AND fields_id=:fields_id";
				$query = $database->prepare($sql);
				if($data === null) {
					$query->bindValue(":data", null, \PDO::PARAM_NULL);
				} else {
					$query->bindValue(":data", $data, \PDO::PARAM_STR);
				}
				$query->bindValue(":fieldgroups_id", $fieldgroups_id, \PDO::PARAM_INT);
				$query->bindValue(":fields_id", $fields_id, \PDO::PARAM_INT); 
				$query->execute();
			}
		}

		// trigger any fields added
		foreach($fieldsAdded as $field) {
			$this->fieldAdded($fieldgroup, $field);
		}
		// trigger any fieldsl removed
		foreach($fieldsRemoved as $field) {
			$this->fieldRemoved($fieldgroup, $field);
		}

		return $result;
	}

	/**
	 * Delete the given fieldgroup from the database
	 *
	 * Also deletes the references in fieldgroups_fields table
	 *
	 * @param Saveable|Fieldgroup $item
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function ___delete(Saveable $item) {

		$templates = array();
		foreach($this->wire()->templates as $template) {
			/** @var Template $template */
			if($template->fieldgroup->id == $item->id) $templates[] = $template->name; 
		}

		if(count($templates)) {
			throw new WireException(
				"Can't delete fieldgroup '{$item->name}' because it is in use by template(s): " . 
				implode(', ', $templates)
			); 
		}

		return parent::___delete($item); 
	}

	/**
	 * Delete the entries in fieldgroups_fields for the given Field
	 *
	 * @param Field $field
	 * @return bool
	 *
	 */
	public function deleteField(Field $field) {
		$database = $this->wire()->database; 
		$query = $database->prepare("DELETE FROM fieldgroups_fields WHERE fields_id=:fields_id"); // QA
		$query->bindValue(":fields_id", $field->id, \PDO::PARAM_INT);
		$result = $query->execute();
		return $result;
	}

	/**
	 * Create and return a cloned copy of this item
	 *
	 * If the new item uses a 'name' field, it will contain a number at the end to make it unique
	 *
	 * @param Saveable $item Item to clone
	 * @param string $name
	 * @return Fieldgroup|false $item Returns the new clone on success, or false on failure
	 *
	 */
	public function ___clone(Saveable $item, $name = '') {
		if(!$item instanceof Fieldgroup) return false;
		
		$database = $this->wire()->database;
		
		/** @var Fieldgroup|false $fieldgroup */
		$fieldgroup = parent::___clone($item, $name);
		if(!$fieldgroup) return false;
		
		$sql = 
			'SELECT fields_id, sort, data FROM fieldgroups_fields ' . 
			'WHERE fieldgroups_id=:fieldgroups_id ' . 
			'AND data IS NOT NULL';
		
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':fieldgroups_id', $item->id, \PDO::PARAM_INT);
		$query->execute();
		
		$rows = $query->fetchAll(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		
		$sql = 
			'UPDATE fieldgroups_fields SET data=:data ' . 
			'WHERE fieldgroups_id=:fieldgroups_id ' . 
			'AND fields_id=:fields_id AND sort=:sort';
		
		$query = $database->prepare($sql);
		
		foreach($rows as $row) {
			$query->bindValue(':data', $row['data']); 
			$query->bindValue(':fieldgroups_id', (int) $fieldgroup->id, \PDO::PARAM_INT);
			$query->bindValue(':fields_id', (int) $row['fields_id'], \PDO::PARAM_INT);
			$query->bindValue(':sort', (int) $row['sort'], \PDO::PARAM_INT);
			$query->execute();
		}
		
		return $fieldgroup;
	}

	/**
	 * Save contexts for all fields in the given fieldgroup 
	 * 
	 * @param Fieldgroup $fieldgroup
	 * @return int Number of field contexts saved
	 * 
	 */
	public function ___saveContext(Fieldgroup $fieldgroup) {
		$contexts = $fieldgroup->getFieldContextArray();
		$numSaved = 0;
		foreach($contexts as $fieldID => $context) {
			$field = $fieldgroup->getFieldContext((int) $fieldID); 
			if(!$field) continue;
			if($this->wire()->fields->saveFieldgroupContext($field, $fieldgroup)) $numSaved++;
		}
		return $numSaved; 
	}
	
	/**
	 * Export config data for the given fieldgroup
	 * 
	 * @param Fieldgroup $fieldgroup
	 * @return array
	 *
	 */
	public function ___getExportData(Fieldgroup $fieldgroup) {
		$data = $fieldgroup->getTableData();
		$fields = array();
		$contexts = array();
		foreach($fieldgroup as $field) {
			/** @var Field $field */
			$fields[] = $field->name;
			$fieldContexts = $fieldgroup->getFieldContextArray();
			if(isset($fieldContexts[$field->id])) {
				$contexts[$field->name] = $fieldContexts[$field->id];
			} else {
				$contexts[$field->name] = array();
			}
		}
		$data['fields'] = $fields;
		$data['contexts'] = $contexts;
		return $data;
	}

	/**
	 * Given an export data array, import it back to the class and return what happened
	 *
	 * Changes are not committed until the item is saved
	 *
	 * @param Fieldgroup $fieldgroup
	 * @param array $data
	 * @return array Returns array(
	 * 	[property_name] => array(
	 * 		'old' => 'old value',	// old value, always a string
	 * 		'new' => 'new value',	// new value, always a string
	 * 		'error' => 'error message or blank if no error'
	 * 	)
	 * @throws WireException if given invalid data
	 *
	 */
	public function ___setImportData(Fieldgroup $fieldgroup, array $data) {

		$return = array(
			'fields' => array(
				'old' => '',
				'new' => '',
				'error' => array()
			),
			'contexts' => array(
				'old' => '',
				'new' => '',
				'error' => array()
			),
		);

		$fieldgroup->setTrackChanges(true);
		$fieldgroup->errors("clear");
		$_data = $this->getExportData($fieldgroup);
		$rmFields = array();

		if(isset($data['fields'])) {
			// field data
			$old = "\n" . implode("\n", $_data['fields']) . "\n";
			$new = "\n" . implode("\n", $data['fields']) . "\n";

			if($old !== $new) {

				$return['fields']['old'] = $old;
				$return['fields']['new'] = $new;

				// figure out which fields should be removed
				foreach($fieldgroup as $field) {
					/** @var Field $field */
					$fieldNames[$field->name] = $field->name;
					if(!in_array($field->name, $data['fields'])) {
						$fieldgroup->remove($field);
						$label = "-$field->name";
						$return['fields']['new'] .= $label . "\n";
						$rmFields[] = $field->name;
					}
				}

				// figure out which fields should be added
				foreach($data['fields'] as $name) {
					$field = $this->wire()->fields->get($name);
					if(in_array($name, $rmFields)) continue;
					if(!$field) {
						$error = sprintf($this->_('Unable to find field: %s'), $name);
						$return['fields']['error'][] = $error;
						$label = str_replace("\n$name\n", "\n?$name\n", $return['fields']['new']);
						$return['fields']['new'] = $label;
						continue;
					}
					if(!$fieldgroup->hasField($field)) {
						$label = str_replace("\n$field->name\n", "\n+$field->name\n", $return['fields']['new']);
						$return['fields']['new'] = $label;
						$fieldgroup->add($field);
					} else {
						$field = $fieldgroup->getField($field->name, true); // in context
						$fieldgroup->add($field);
						$label = str_replace("\n$field->name\n", "\n$field->name\n", $return['fields']['new']);
						$return['fields']['new'] = $label;
					}
				}

			}

			$return['fields']['new'] = trim($return['fields']['new']);
			$return['fields']['old'] = trim($return['fields']['old']);
		}

		if(isset($data['contexts'])) {
			// context data
			foreach($data['contexts'] as $key => $value) {
				// remove items where they are both empty
				if(empty($value) && empty($_data['contexts'][$key])) {
					unset($data['contexts'][$key], $_data['contexts'][$key]);
				}
			}

			foreach($_data['contexts'] as $key => $value) {
				// remove items where they are both empty
				if(empty($value) && empty($data['contexts'][$key])) {
					unset($data['contexts'][$key], $_data['contexts'][$key]);
				}
			}

			$old = wireEncodeJSON($_data['contexts'], true, true);
			$new = wireEncodeJSON($data['contexts'], true, true);

			if($old !== $new) {

				$return['contexts']['old'] = trim($old);
				$return['contexts']['new'] = trim($new);

				foreach($data['contexts'] as $name => $context) {
					$field = $fieldgroup->getField($name, true); // in context
					if(!$field) {
						if(!empty($context)) $return['contexts']['error'][] = sprintf($this->_('Unable to find field to set field context: %s'), $name);
						continue;
					}
					$id = $field->id;
					$fieldContexts = $fieldgroup->getFieldContextArray();
					if(isset($fieldContexts[$id]) || !empty($context)) {
						$fieldgroup->setFieldContextArray($id, $context); 
						$fieldgroup->trackChange('fieldContexts');
					}
				}
			}
		}

		// other data
		foreach($data as $key => $value) {
			if($key == 'fields' || $key == 'contexts') continue;
			$old = isset($_data[$key]) ? $_data[$key] : null;
			if(is_array($old)) $old = wireEncodeJSON($old, true, false);
			$new = is_array($value) ? wireEncodeJSON($value, true, false) : $value;
			if($old == $new) continue;
			$fieldgroup->set($key, $value);
			$error = (string) $fieldgroup->errors("first clear");
			$return[$key] = array(
				'old' => $old,
				'new' => $value,
				'error' => $error,
			);
		}
		
		if(count($rmFields)) {
			$return['fields']['error'][] = sprintf($this->_('Warning, all data in these field(s) will be permanently deleted (please confirm): %s'), implode(', ', $rmFields));
		}

		$fieldgroup->errors('clear');

		return $return;
	}

	/**
	 * Is the given Field not allowed to be removed from given Template?
	 *
	 * #pw-internal
	 *
	 * @param Field $field
	 * @param Fieldgroup $fieldgroup
	 * @param Template|null $template
	 * @return bool|string Returns error message string if not removeable or boolean false if it is removeable
	 *
	 */
	public function isFieldNotRemoveable(Field $field, Fieldgroup $fieldgroup, ?Template $template = null) {
		
		if(is_null($template)) $template = $this->wire()->templates->get($fieldgroup->name);

		if(($field->flags & Field::flagGlobal) && (!$template || !$template->noGlobal)) {
			if($template && $template->getConnectedField()) {
				// if template has a 1-1 relationship with a field, noGlobal is not enforced
				return false;
			} else {
				return
					"Field '$field' may not be removed from fieldgroup '$fieldgroup->name' " .
					"because it is globally required (Field::flagGlobal).";
			}
		}

		if($field->flags & Field::flagPermanent) {
			return 
				"Field '$field' may not be removed from fieldgroup '$fieldgroup->name' " . 
				"because it is permanent (Field::flagPermanent).";
		}

		return false;
	}

	/**
	 * Hook called when field has been added to fieldgroup
	 * 
	 * #pw-hooker
	 * 
	 * @param Fieldgroup $fieldgroup
	 * @param Field $field
	 * @since 3.0.193
	 * 
	 */
	public function ___fieldAdded(Fieldgroup $fieldgroup, Field $field) { }

	/**
	 * Hook called when field has been removed from fieldgroup
	 * 
	 * #pw-hooker
	 *
	 * @param Fieldgroup $fieldgroup
	 * @param Field $field
	 * @since 3.0.193
	 *
	 */
	public function ___fieldRemoved(Fieldgroup $fieldgroup, Field $field) { }
}
