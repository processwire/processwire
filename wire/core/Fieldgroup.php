<?php namespace ProcessWire;

/**
 * ProcessWire Fieldgroup
 *
 * A group of fields that is ultimately attached to a Template.
 * 
 * #pw-summary Fieldgroup is a type of WireArray that holds a group of Field objects for template(s). 
 * #pw-body For full details on all methods available in a Fieldgroup, be sure to also see the `WireArray` class. 
 * 
 * The existance of Fieldgroups is hidden at the ProcessWire web admin level
 * as it appears that fields are attached directly to Templates. However, they
 * are separated in the API in case want want to have fieldgroups used by 
 * multiple templates in the future (like ProcessWire 1.x).
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @property int $id Fieldgroup database ID #pw-group-retrieval
 * @property string $name Fieldgroup name #pw-group-retrieval
 * @property array $fields_id Array of all field IDs in this Fieldgroup
 * @property null|FieldsArray $removedFields Null when there are no removed fields, or FieldsArray when there are. 
 *
 */
class Fieldgroup extends WireArray implements Saveable, Exportable, HasLookupItems {

	/**
	 * Prefix for namespaced field contexts
	 * 
	 */
	const contextNamespacePrefix = 'NS_';

	/**
	 * Permanent/common settings for a Fieldgroup, fields in the database
	 *
	 */
	protected $settings = array(
		'id' => 0, 
		'name' => '', 
	);

	/**
	 * Any fields that were removed from this instance are noted so that Fieldgroups::save() can delete unused data
	 * 
	 * @var FieldsArray|null
	 *
	 */
	protected $removedFields = null;

	/**
	 * Array indexed by field_id containing an array of variables specific to the context of that field in this fieldgroup
	 *
	 * This context overrides the values set in the field when it doesn't have context. 
	 *
	 */
	protected $fieldContexts = array();

	/**
	 * Per WireArray interface, items added must be instances of Field
	 * 
	 * #pw-internal
	 * 
	 * @param $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Field; 
	}

	/**
	 * Per WireArray interface, keys must be numeric
	 * 
	 * #pw-internal
	 * 
	 * @param int|string $key
	 * @return bool
	 *
	 */
	public function isValidKey($key) {
		return is_int($key) || ctype_digit("$key"); 
	}

	/**
	 * Per WireArray interface, the item key is it's ID
	 * 
	 * #pw-internal
	 * 
	 * @param $item
	 * @return int
	 *
	 */
	public function getItemKey($item) {
		/** @var Field $item */
		return $item->id; 
	}

	/**
	 * Per WireArray interface, return a blank item 
	 * 
	 * #pw-internal
	 * 
	 * @return Wire|Field
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Field());
	}

	/**
	 * Add a field to this Fieldgroup
	 * 
	 * ~~~~~
	 * $field = $fields->get('body');
	 * $fieldgroup->add($field); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Field|string $item Field object, field name or id. 
	 * @return $this
	 * @throws WireException
	 *
	 */
	public function add($item) {
		$field = $item;
		if(!is_object($field)) $field = $this->wire()->fields->get($field); 

		if($field instanceof Field) {
			if(!$field->id) {
				throw new WireException("You must save field '$field' before adding to Fieldgroup '$this->name'");
			}
			parent::add($field); 
		} else {
			// throw new WireException("Unable to add field '$field' to Fieldgroup '{$this->name}'"); 
		}

		return $this; 
	}

	/**
	 * Remove a field from this fieldgroup
	 * 
	 * Note that this must be followed up with a `$fieldgroup->save()` before it does anything destructive. 
	 * This method does nothing more than queue the removal.
	 *
	 * _Technical Details_   
	 * Performs a deletion by finding all templates using this fieldgroup, then finding all pages using the template, then 
	 * calling upon the Fieldtype to delete them one at a time. This is a potentially expensive/time consuming method, and
	 * may need further consideration. 
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Field|string $key Field object or field name, or id. 
	 * @return bool True on success, false on failure.
	 *
	 */
	public function remove($key) {
		
		$field = $key;
		if(!is_object($field)) $field = $this->wire()->fields->get($field); 
		if(!$this->getField($field->id)) return false; 
		if(!$field) return true; 

		// Make note of any fields that were removed so that Fieldgroups::save()
		// can delete data for those fields
		if(is_null($this->removedFields)) $this->removedFields = $this->wire(new FieldsArray());
		$this->removedFields->add($field); 
		$this->trackChange("remove:$field", $field, null); 

		// parent::remove($field->id); replaced with finishRemove() method below

		return true; 
	}

	/**
	 * Intended to be called by Fieldgroups::save() to complete the field removal
	 *
	 * This completes the removal process. The remove() method above only queues the removal but doesn't execute it.
	 * Instead, Fieldgroups::save() calls this method to finish the removal. This is necessary because if remove()
	 * removes the data from memory, then save() won't still have access to determine what related assets should
	 * be removed. 
	 *
	 * This method is for use by Fieldgroups::save() and not intended for API usage. 
	 * 
	 * #pw-internal
	 * 
	 * @param Field $field
	 * @return Fieldgroup|WireArray $this
	 *
	 */
	public function finishRemove(Field $field) {
		return parent::remove($field->id); 
	}

	/**
	 * Remove a field without queueing it to be removed from database
	 * 
	 * Removes a field from the fieldgroup without deleting any associated field data when fieldgroup 
	 * is saved to the database. This is useful in the API when you want to move a field around within 
	 * a fieldgroup, like when moving a field to a Fieldset within the Fieldgroup. 
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Field|string|int $field Field object, name or id. 
	 * @return bool|Fieldgroup|WireArray
	 *
	 */
	public function softRemove($field) {

		if(!is_object($field)) $field = $this->wire()->fields->get($field); 
		if(!$this->getField($field->id)) return false; 
		if(!$field) return true; 

		return parent::remove($field->id); 
	}

	/**
	 * Clear all removed fields, for use by Fieldgroups::save
	 * 
	 * #pw-internal
	 *
	 */
	public function resetRemovedFields() {
		$this->removedFields = null;
	}

	/**
	 * Get a field that is part of this fieldgroup
	 *
	 * Same as `Fieldgroup::get()` except that it only checks fields, not other properties of a fieldgroup.
	 * Meaning, this is the preferred way to retrieve a Field from a Fieldgroup. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|int|Field $key Field object, name or id. 
	 * @param bool|string $useFieldgroupContext Optionally specify one of the following (default=false):
	 *   - `true` (boolean) Returned Field will be a clone of the original with context data set.
	 *   - Specify a namespace (string) to retrieve context within that namespace. 
	 * @return Field|null Field object when present in this Fieldgroup, or null if not. 
	 *
	 */
	public function getField($key, $useFieldgroupContext = false) {
		if($key instanceof Field) $key = $key->id;
		if(is_string($key) && ctype_digit("$key")) $key = (int) $key;

		if($this->isValidKey($key)) {
			$value = parent::get($key); 

		} else {

			$value = null;
			foreach($this as $field) {
				/** @var Field $field */
				if($field->name == $key) {
					$value = $field;
					break;
				}
			}
		}

		if($value && $useFieldgroupContext) { 
			$value = clone $value;	
			if(isset($this->fieldContexts[$value->id])) {
				$context = $this->fieldContexts[$value->id];
				$namespace = is_string($useFieldgroupContext) ? self::contextNamespacePrefix . $useFieldgroupContext : "";
				if($namespace && isset($context[$namespace]) && is_array($context[$namespace])) $context = $context[$namespace];	
				foreach($context as $k => $v) {
					// if(strpos($k, self::contextNamespacePrefix) === 0) continue;
					$value->set($k, $v); 
				}
			}
		}

		if($useFieldgroupContext && $value) {
			$value->flags = $value->flags | Field::flagFieldgroupContext;
			$value->setQuietly('_contextFieldgroup', $this); 
		}

		return $value; 
	}

	/**
	 * Does the given Field have context data available in this fieldgroup?
	 * 
	 * A Field with context data is one that overrides one or more settings present with the Field 
	 * when it is outside the context of this Fieldgroup. For example, perhaps a Field has a
	 * columnWidth setting of 100% in its global settings, but only 50% when used in this Fieldgroup.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param int|string|Field $field Field object, name or id
	 * @param string $namespace Optional namespace string for context
	 * @return bool True if additional context information is available, false if not. 
	 * 
	 */
	public function hasFieldContext($field, $namespace = '') {
		if($field instanceof Field) $field = $field->id;
		if(is_string($field) && !ctype_digit($field)) {
			$field = $this->wire()->fields->get($field);
			$field = $field && $field->id ? $field->id : 0;
		}
		if(isset($this->fieldContexts[(int) $field])) {
			if($namespace) return isset($this->fieldContexts[(int) $field][self::contextNamespacePrefix . $namespace]);
			return true;
		}
		return false;
	}

	/**
	 * Get a Field that is part of this Fieldgroup, in the context of this Fieldgroup. 
	 * 
	 * Returned Field will be a clone of the original with additional context data
	 * already populated to it. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|int|Field $key Field object, name or id. 
	 * @param string $namespace Optional namespace string for context
	 * @return Field|null
	 *
	 */
	public function getFieldContext($key, $namespace = '') {
		return $this->getField($key, $namespace ? $namespace : true); 
	}

	/**
	 * Does this fieldgroup have the given field?
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|int|Field $key Field object, name or id. 
	 * @return bool True if this Fieldgroup has the field, false if not. 
	 *
	 */
	public function hasField($key) {
		return $this->getField($key) !== null;
	}

	/**
	 * Get a Fieldgroup property or a Field. 
	 *
	 * It is preferable to use `Fieldgroup::getField()` to retrieve fields from the Fieldgroup because 
	 * the scope of this `get()` method means it can return more than just Field object. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|int $key Property name to retrieve, or Field name
	 * @return Field|string|int|null|array
	 *
	 */
	public function get($key) {
		if($key == 'fields') return $this;
		if($key == 'fields_id') {
			$values = array();
			foreach($this as $field) {
				/** @var Field $field */
				$values[] = $field->id;
			}
			return $values; 
		}
		if($key == 'removedFields') return $this->removedFields; 
		if(isset($this->settings[$key])) return $this->settings[$key];
		$value = parent::get($key);
		if($value !== null) return $value; 
		return $this->getField($key); 
	}

	/**
	 * Per HasLookupItems interface, add a Field to this Fieldgroup
	 * 
	 * #pw-internal
	 * 
	 * @param Saveable|Field $item
	 * @param array $row
	 * @return $this
	 *
	 */
	public function addLookupItem($item, array &$row) {
		if($item) $this->add($item); 
		if(!empty($row['data'])) {
			// set field context for this fieldgroup
			$data = $row['data'];
			if(is_string($data)) $data = wireDecodeJSON($data); 
			if(!is_array($data)) $row['data'] = array();
			$this->fieldContexts[(int) "$item"] = $data;
		}
		return $this; 
	}


	/**
	 * Set a fieldgroup property
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $key Name of property to set
	 * @param string|int|object $value Value of property
	 * @return Fieldgroup|WireArray $this
	 * @throws WireException if passed invalid data
	 *
	 */
	public function set($key, $value) {

		if($key == 'data') return $this; // we don't have a data field here

		if($key === 'id') {
			$value = (int) $value; 
			
		} else if($key === 'name') {
			$value = $this->wire()->sanitizer->templateName($value);
		}

		if(isset($this->settings[$key])) {
			if($this->trackChanges && $this->settings[$key] !== $value) {
				$this->trackChange($key, $this->settings[$key], $value);
			}
			$this->settings[$key] = $value; 

		} else {
			return parent::set($key, $value); 
		}
		
		return $this; 	
	}


	/**
	 * Save this Fieldgroup to the database
	 *
	 * To hook into this, hook to `Fieldgroups::save()` instead.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @return $this
	 *
	 */
	public function save() {
		$this->wire()->fieldgroups->save($this); 
		return $this;
	}

	/**
	 * Fieldgroups always return their name when dereferenced as a string
	 *	
	 */
	public function __toString() {
		return $this->name; 
	}

	/**
	 * Per Saveable interface, get an array of data associated with the database table
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 *
	 */
	public function getTableData() {
		return $this->settings; 
	}

	/**
	 * Per Saveable interface: return data for external storage
	 * 
	 * #pw-internal
	 *
	 */
	public function getExportData() {
		$fieldgroups = $this->wire()->fieldgroups;
		return $fieldgroups->getExportData($this); 
	}

	/**
	 * Given an export data array, import it back to the class and return what happened
	 * 
	 * Changes are not committed until the item is saved
	 * 
	 * #pw-internal
	 *
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
	public function setImportData(array $data) {
		/** @var Fieldgroups $fieldgroups */
		$fieldgroups = $this->wire('fieldgroups');
		return $fieldgroups->setImportData($this, $data); 
	}

	/**
	 * Per HasLookupItems interface, get a WireArray of Field instances associated with this Fieldgroup
	 *	
	 * #pw-internal
	 * 
	 */ 
	public function getLookupItems() {
		return $this; 
	}

	/**
	 * Get all of the Inputfields for this Fieldgroup associated with the provided Page and populate them.
	 * 
	 * #pw-group-retrieval
	 *
	 * @param Page $page Page that the Inputfields will be for. 
	 * @param string|array $contextStr Optional context string to append to all the Inputfield names, OR array of options. 
	 *  - Optional context string is helpful for things like repeaters.
	 *  - Or associative array with any of these options:
	 *  - `contextStr` (string): Context string to append to all Inputfield names. 
	 *  - `fieldName` (string|array): Limit to particular fieldName(s) or field ID(s). See $fieldName argument for details.
	 *  - `namespace` (string): Additional namespace for Inputfield context. 
	 *  - `flat` (bool): Return all Inputfields in a flattened InputfieldWrapper?
	 *  - `populate` (bool): Populate page values to Inputfields? (default=true) since 3.0.208
	 *  - `container` (InputfieldWrapper): The InputfieldWrapper element to add fields into, or omit for new. since 3.0.239
	 * @param string|array $fieldName Limit to a particular fieldName(s) or field IDs (optional).
	 *  - If specifying a single field (name or ID) and it refers to a fieldset, then all fields in that fieldset will be included. 
	 *  - If specifying an array of field names/IDs the returned InputfieldWrapper will maintain the requested order. 
	 * @param string $namespace Additional namespace for the Inputfield context (optional).
	 * @param bool $flat Returns all Inputfields in a flattened InputfieldWrapper (default=true). 
	 * @return InputfieldWrapper Returns an InputfieldWrapper that acts as a container for multiple Inputfields.
	 *
	 */
	public function getPageInputfields(Page $page, $contextStr = '', $fieldName = '', $namespace = '', $flat = true) {
		
		if(is_array($contextStr)) {
			// 2nd argument is instead an array of options
			$defaults = array(
				'contextStr' => '', 	
				'fieldName' => $fieldName, 
				'namespace' => $namespace,
				'flat' => $flat, 
				'populate' => true, // populate page values?
				'container' => null, 
			);
			$options = $contextStr;
			$options = array_merge($defaults, $options);
			$contextStr = $options['contextStr'];
			$fieldName = $options['fieldName'];
			$namespace = $options['namespace'];
			$populate = (bool) $options['populate'];
			$flat = $options['flat'];
			$container = $options['container'];
		} else {
			$populate = true;
			$container = null;
		}
	
		if(!$container instanceof InputfieldWrapper) {
			$container = $this->wire(new InputfieldWrapper());
		}
		
		$containers = array();
		$inFieldset = false;
		$inHiddenFieldset = false;
		$inModalGroup = '';
	
		// for multiple named fields
		$multiMode = false;
		$fieldInputfields = array();
		if(is_array($fieldName)) {
			// an array was specified for $fieldName
			if(count($fieldName) == 1) {
				// single field requested, revert to single field
				$fieldName = reset($fieldName);
			} else if(count($fieldName) == 0) {
				// blank array, no field name requested
				$fieldName = '';
			} else {
				// multiple field names asked for, setup for retaining requested order
				$multiMode = true;
				foreach($fieldName as $name) {
					$field = $this->getField($name);
					if($field) $fieldInputfields[$field->id] = false; // placeholder
				}
				$fieldName = '';
			}
		}

		foreach($this as $field) {
			/** @var Field $field */
		
			// for named multi-field retrieval
			if($multiMode && !isset($fieldInputfields[$field->id])) continue;
			
			// get a clone in the context of this fieldgroup, if it has contextual settings
			if(isset($this->fieldContexts[$field->id])) $field = $this->getFieldContext($field->id, $namespace); 
			
			if($inModalGroup) {
				// we are in a modal group that should be skipped since all the inputs require the modal
				if($field->name == $inModalGroup . "_END") {
					// exit modal group
					$inModalGroup = false; 
				} else {
					// skip field
					continue; 
				}
			}
			if($inHiddenFieldset) {
				// we are in a modal group that should be skipped since all the inputs require the modal
				if($field->name == $inHiddenFieldset . "_END") {
					$inHiddenFieldset = false;
				} else {
					continue;
				}
			}
			
			if($fieldName) {
				// limit to specific field name
				if($inFieldset) {
					// allow the field
					if($field->type instanceof FieldtypeFieldsetClose && $field->name == $fieldName . "_END") {
						// stop, as we've got all the fields we need
						break;
					}
					// allow
					
				} else if($field->name == $fieldName || (ctype_digit("$fieldName") && $field->id == $fieldName)) {
					// start allow fields
					if($field->type instanceof FieldtypeFieldsetOpen) {
						$container = $field->getInputfield($page, $contextStr);
						$inFieldset = true;
						continue; 
					} else {
						// allow 1 field
					}
				} else {
					// disallow
					continue; 
				}
				
			} else if($field->get('modal') && $field->type instanceof FieldtypeFieldsetOpen) {
				// field requires modal
				$inModalGroup = $field->name;

			} else if($field->type instanceof FieldtypeFieldsetOpen && $field->collapsed == Inputfield::collapsedHidden) {
				$inHiddenFieldset = $field->name;
				continue;

			} else if(!$flat && $field->type instanceof FieldtypeFieldsetOpen) {
				// new fieldset in non-flat mode
				if($field->type instanceof FieldtypeFieldsetClose) {
					// restore back to previous container
					if(count($containers)) $container = array_pop($containers);
				} else {
					// start a new container
					$inputfield = $field->getInputfield($page, $contextStr);
					if(!$inputfield) $inputfield = $this->wire(new InputfieldWrapper());
					/** @var Inputfield|InputfieldWrapper $inputfield */
					if($inputfield->collapsed == Inputfield::collapsedHidden) continue;
					$container->add($inputfield);
					$containers[] = $container;
					$container = $inputfield; // container is now the child InputfieldWrapper
				}
				continue;
			}

			$inputfield = $field->getInputfield($page, $contextStr);
			if(!$inputfield) continue;
			if($inputfield->collapsed == Inputfield::collapsedHidden) continue;

			if($populate && !$page instanceof NullPage) {
				$value = $page->get($field->name);
				$inputfield->setAttribute('value', $value);
			}
			
			if($multiMode) {
				$fieldInputfields[$field->id] = $inputfield;
			} else {
				$container->add($inputfield);
			}
		}		
		
		if($multiMode) {
			// add to container in requested order
			foreach($fieldInputfields as /* $fieldID => */ $inputfield) {
				if($inputfield) $container->add($inputfield);
			}
		}

		return $container; 
	}

	/**
	 * Get a list of all templates using this Fieldgroup
	 * 
	 * #pw-group-retrieval
	 *
	 * @return TemplatesArray
	 *
	 */
	public function getTemplates() {
		/** @var Fieldgroups $fieldgroups */
		$fieldgroups = $this->wire('fieldgroups');
		return $fieldgroups->getTemplates($this); 
	}

	/**
	 * Get the number of templates using this Fieldgroup
	 * 
	 * #pw-group-retrieval
	 *
	 * @return int
	 *
	 */
	public function getNumTemplates() {
		return $this->wire()->fieldgroups->getNumTemplates($this); 
	}

	/**
	 * Alias of getNumTemplates()
	 * 
	 * #pw-internal
	 *
	 * @return int
	 *
	 */
	public function numTemplates() {
		return $this->getNumTemplates();
	}

	/**
	 * Return an array of context data for the given field ID
	 * 
	 * #pw-internal
	 *
	 * @param int|null $field_id Field ID or omit to return all field contexts
	 * @param string $namespace Optional namespace
	 * @return array 
	 *
	 */
	public function getFieldContextArray($field_id = null, $namespace = '') {
		if(is_null($field_id)) return $this->fieldContexts;
		if(isset($this->fieldContexts[$field_id])) {
			if($namespace) {
				$namespace = self::contextNamespacePrefix . $namespace;
				if(isset($this->fieldContexts[$field_id][$namespace])) {
					return $this->fieldContexts[$field_id][$namespace];
				}
				return array();
			} else if(isset($this->fieldContexts[$field_id])) {
				return $this->fieldContexts[$field_id];
			}
		}
		return array();
	}

	/**
	 * Set an array of context data for the given field ID
	 * 
	 * #pw-internal
	 * 
	 * @param int $field_id Field ID
	 * @param array $data
	 * @param string $namespace Optional namespace
	 * 
	 */
	public function setFieldContextArray($field_id, $data, $namespace = '') {
		if($namespace) {
			if(!isset($this->fieldContexts[$field_id])) $this->fieldContexts[$field_id] = array();	
			$namespace = self::contextNamespacePrefix . $namespace;
			$this->fieldContexts[$field_id][$namespace] = $data;
		} else {
			$this->fieldContexts[$field_id] = $data;
		}
	}

	/**
	 * Save field contexts for this fieldgroup
	 * 
	 * #pw-group-manipulation
	 * 
	 * @return int Number of contexts saved
	 * 
	 */
	public function saveContext() {
		return $this->wire()->fieldgroups->saveContext($this); 
	}

}
