<?php namespace ProcessWire;

/**
 * ProcessWire Fieldgroups
 *
 * #pw-summary Maintains collections of Fieldgroup object instances and represents the `$fieldgroups` API variable.
 * #pw-body For full details on all methods available in a Fieldgroup, be sure to also see the `WireArray` class.
 * #pw-var $fieldgroups
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @method int saveContext(Fieldgroup $fieldgroup)
 * @method array getExportData(Fieldgroup $fieldgroup)
 * @method array setImportData(Fieldgroup $fieldgroup, array $data)
 *
 *
 */

class Fieldgroups extends WireSaveableItemsLookup {

	/**
	 * Instances of FieldgroupsArray
	 * 
	 * @var FieldgroupsArray
	 *
	 */
	protected $fieldgroupsArray; 
	
	public function init() {
		$this->fieldgroupsArray = $this->wire(new FieldgroupsArray());
		$this->load($this->fieldgroupsArray);
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
		$lookupTable = $this->wire('database')->escapeTable($this->getLookupTable()); 
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
		return count($this->getTemplates($fieldgroup)); 
	}

	/**
	 * Given a Fieldgroup, return a TemplatesArray of all templates using the Fieldgroup
	 *
	 * @param Fieldgroup $fieldgroup
	 * @return TemplatesArray
	 *
	 */
	public function getTemplates(Fieldgroup $fieldgroup) {
		$templates = $this->wire(new TemplatesArray());
		foreach($this->wire('templates') as $tpl) {
			if($tpl->fieldgroup->id == $fieldgroup->id) $templates->add($tpl); 
		}
		return $templates; 
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

		$database = $this->wire('database');
		
		/** @var Fieldgroup $item */

		if($item->id && $item->removedFields) {

			foreach($this->wire('templates') as $template) {
				if($template->fieldgroup->id !== $item->id) continue; 
				foreach($item->removedFields as $field) {
					// make sure the field is valid to delete from this template
					$error = $this->isFieldNotRemoveable($field, $item, $template);
					if($error !== false) throw new WireException("$error Save of fieldgroup changes aborted.");
					if($field->type) $field->type->deleteTemplateField($template, $field); 
					$item->finishRemove($field); 
				}
			}

			$item->resetRemovedFields();
		}

		$contextData = array();
		if($item->id) { 
			// save context data
			$query = $database->prepare("SELECT fields_id, data FROM fieldgroups_fields WHERE fieldgroups_id=:item_id"); 
			$query->bindValue(":item_id", (int) $item->id, \PDO::PARAM_INT); 
			$query->execute();
			/** @noinspection PhpAssignmentInConditionInspection */
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$contextData[$row['fields_id']] = $row['data'];
			}
			$query->closeCursor();
		}

		$result = parent::___save($item); 

		if(count($contextData)) {
			// restore context data
			foreach($contextData as $fields_id => $data) {
				$fieldgroups_id = (int) $item->id; 
				$fields_id = (int) $fields_id; 
				$query = $database->prepare("UPDATE fieldgroups_fields SET data=:data WHERE fieldgroups_id=:fieldgroups_id AND fields_id=:fields_id"); // QA
				$query->bindValue(":data", $data, \PDO::PARAM_STR); 
				$query->bindValue(":fieldgroups_id", $fieldgroups_id, \PDO::PARAM_INT);
				$query->bindValue(":fields_id", $fields_id, \PDO::PARAM_INT); 
				$query->execute();
			}
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
		foreach($this->wire('templates') as $template) {
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
		$database = $this->wire('database'); 
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
	 * @return bool|Saveable $item Returns the new clone on success, or false on failure
	 * @return Saveable|Fieldgroup
	 *
	 */
	public function ___clone(Saveable $item, $name = '') {
		return parent::___clone($item, $name);
		// @TODO clone the field context data
		/*
		$id = $item->id; 
		$item = parent::___clone($item);
		if(!$item) return false;
		return $item; 	
		*/
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
			if($this->wire('fields')->saveFieldgroupContext($field, $fieldgroup)) $numSaved++;
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
					$fieldNames[$field->name] = $field->name;
					if(!in_array($field->name, $data['fields'])) {
						$fieldgroup->remove($field);
						$label = "-$field->name";
						$return['fields']['new'] .= $label . "\n";;
						$rmFields[] = $field->name;
					}
				}

				// figure out which fields should be added
				foreach($data['fields'] as $name) {
					$field = $this->wire('fields')->get($name);
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
	 * @param Template $template
	 * @param Fieldgroup $fieldgroup
	 * @return bool|string Returns error message string if not removeable or boolean false if it is removeable
	 *
	 */
	public function isFieldNotRemoveable(Field $field, Fieldgroup $fieldgroup, Template $template = null) {
		
		if(is_null($template)) $template = $this->wire('templates')->get($fieldgroup->name);

		if(($field->flags & Field::flagGlobal) && (!$template || !$template->noGlobal)) {
			return
				"Field '$field' may not be removed from fieldgroup '$fieldgroup->name' " . 
				"because it is globally required (Field::flagGlobal).";
		}

		if($field->flags & Field::flagPermanent) {
			return 
				"Field '$field' may not be removed from fieldgroup '$fieldgroup->name' " . 
				"because it is permanent (Field::flagPermanent).";
		}

		return false;
	}

}

