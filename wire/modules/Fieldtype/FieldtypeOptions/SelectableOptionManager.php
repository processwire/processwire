<?php namespace ProcessWire;

/**
 * ProcessWire Selectable Option Manager, for FieldtypeOptions
 * 
 * Handles management of the fieldtype_options table and related field_[name] table
 * to assist FieldtypeOptions module. 
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 */

class SelectableOptionManager extends Wire {

	/**
	 * DB table name managed by this class
	 * 
	 */
	const optionsTable = 'fieldtype_options';
	
	/**
	 * Whether or not we are using multi-language support
	 *
	 * @var bool
	 *
	 */
	protected $useLanguages = false;

	/**
	 * Cache of loaded options
	 * 
	 * @var array
	 * 
	 */
	protected $optionsCache = array();

	/**
	 * Options removed that have not yet been deleted
	 * 
	 * Populated after a call to $this->setOptions() with the $allowDelete argument false.
	 * 
	 * @var array()
	 * 
	 */
	protected $removedOptionIDs = array();
	
	public function wired() {
		if($this->wire()->modules->isInstalled('LanguageSupportFields')) {
			$this->useLanguages = true;
			$this->addHookAfter('Languages::updated', $this, 'updateLanguages');
		}
		parent::wired();
	}

	/**
	 * Return the option IDs found to have been removed from the last setOptions() call. 
	 * 
	 * These are for options not yet deleted, and that should be deleted after confirmation.
	 * They can be deleted with this $this->deleteOptionIDs() method. 
	 * 
	 * @return array
	 * 
	 */
	public function getRemovedOptionIDs() {
		return $this->removedOptionIDs;
	}

	/**
	 * Whether or not multi-language support is in use
	 * 
	 * @return bool
	 * 
	 */
	public function useLanguages() {
		return $this->useLanguages; 
	}

	/**
	 * Shortcut to get options by ID number
	 * 
	 * @param Field $field
	 * @param array $ids
	 * @return SelectableOptionArray|SelectableOption[]
	 * 
	 */
	public function getOptionsByID(Field $field, array $ids) {
		/** @var SelectableOptionArray|SelectableOption[] $value */
		$value = $this->getOptions($field, array('id' => $ids)); 
		return $value; 
	}
	
	/**
	 * Return array of current options for $field
	 *
	 * Returned array is indexed by "id$option_id" associative, which is used
	 * as a way to identify existing options vs. new options
	 *
	 * @param Field $field
	 * @param array $filters Any of array(property => array) where property is 'id', 'title' or 'value'. 
	 * @return SelectableOptionArray|SelectableOption[]
	 * @throws WireException
	 *
	 */
	public function getOptions(Field $field, array $filters = array()) {
	
		$database = $this->wire()->database;
		$hasFilters = count($filters) > 0;
		
		if(isset($this->optionsCache[$field->id]) && !$hasFilters) {
			return $this->optionsCache[$field->id];
		}
		
		$defaults = array(
			'id' => array(),
			'title' => array(),
			'value' => array(),
			'or' => false, // change conditions from AND to OR?
		);

		$sortKey = true;
		$sorted = array();
		$filters = array_merge($defaults, $filters);
		$wheres = array();

		// make sure that all filters are arrays
		foreach($defaults as $key => $unused) {
			if(!is_array($filters[$key])) $filters[$key] = array($filters[$key]); 
		}

		if(count($filters['id'])) {
			$s = 'option_id IN(';
			foreach($filters['id'] as $id) {
				$id = (int) $id;
				$s .= "$id,";
				$sorted[$id] = ''; // placeholder
			}
			$s = rtrim($s, ',') . ')';
			$sortKey = 'filters-id';
			$wheres[] = $s;
		} 

		foreach(array('title', 'value') as $property) {
			if(!count($filters[$property])) continue;
			$s = "`$property` IN(";
			foreach($filters[$property] as $val) {
				$s .= $database->quote($val) . ',';
				$sorted[$val] = ''; // placeholder
			}
			$s = rtrim($s, ',') . ')';
			$sortKey = "filters-$property";
			$wheres[] = $s;
		}

		$sql = 'SELECT * FROM ' . self::optionsTable . ' WHERE fields_id=:fields_id ';
		if(count($wheres) > 1) {
			$andOr = $filters['or'] ? ' OR ' : ' AND ';
			$sql .= 'AND (' . implode($andOr, $wheres) . ') ';
		} else if(count($wheres) === 1) {
			$sql .= 'AND ' . reset($wheres);
		}
		
		if($sortKey === true) $sql .= 'ORDER BY sort ASC';

		$query = $database->prepare($sql);
		$query->bindValue(':fields_id', $field->id);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
		
			$option = $this->arrayToOption($row); 

			if(count($sorted)) {
				// sort by the order they were in the filters
				if($sortKey == 'filters-id') $key = $option->id; 
					else if($sortKey == 'filters-value') $key = $option->value;
					else $key = $option->title;
				
				$sorted[$key] = $option;
				
			} else {
				// sorted by DB sort order
				$sorted[] = $option;
			}
		}
		
		$query->closeCursor();

		/** @var SelectableOptionArray $options */
		$options = $this->wire(new SelectableOptionArray());
		$options->setField($field); 
		foreach($sorted as $option) {
			if(!empty($option)) $options->add($option);
		}
		
		$options->resetTrackChanges();
		
		if(!$hasFilters) $this->optionsCache[$field->id] = $options; 

		return $options;
	}

	/**
	 * Perform a partial match on title of options
	 * 
	 * @param Field $field
	 * @param string $property Either 'title' or 'value'. May also be blank (to imply 'either') if operator is '=' or
	 *     '!='
	 * @param string $operator
	 * @param string $value Value to find
	 * @return SelectableOptionArray
	 * 
	 */
	public function findOptionsByProperty(Field $field, $property, $operator, $value) {
		
		if($operator == '=' || $operator == '!=') {
			// no need to use fulltext matching if operator is not a partial match operator
			return $this->getOptions($field, array($property => $value));
		}
	
		/** @var DatabaseQuerySelect $query */
		$query = $this->wire(new DatabaseQuerySelect());
		$query->select(self::optionsTable . '.*'); 
		$query->from(self::optionsTable); 
		$query->where("fields_id=:fields_id"); 
		$query->bindValue(':fields_id', $field->id); 
	
		/** @var DatabaseQuerySelectFulltext $ft */
		$ft = $this->wire(new DatabaseQuerySelectFulltext($query));
		$ft->match(self::optionsTable, $property, $operator, $value);
	
		$result = $query->execute();
		/** @var SelectableOptionArray $options */
		$options = $this->wire(new SelectableOptionArray());
		$options->setField($field); 
		
		while($row = $result->fetch(\PDO::FETCH_ASSOC)) {
			$option = $this->arrayToOption($row); 
			$options->add($option); 
		}
		
		$options->resetTrackChanges();
		
		return $options; 
	}

	/**
	 * Given an array of option data, populate an Option object and return it 
	 * 
	 * @param array $a
	 * @return SelectableOption
	 * 
	 */
	protected function arrayToOption(array $a) {
		$option = $this->wire(new SelectableOption());
		if(isset($a['id'])) $option->set('id', (int) $a['id']); 
		if(isset($a['option_id'])) $option->set('id', (int) $a['option_id']);
		if(isset($a['title'])) $option->set('title', $a['title']);
		if(isset($a['value'])) $option->set('value', $a['value']);
		if(isset($a['sort'])) $option->set('sort', (int) $a['sort']);
		if($this->useLanguages) foreach($this->wire()->languages as $language) {
			if($language->isDefault()) continue;
			if(isset($a["title$language"])) $option->set("title$language", $a["title$language"]);
			if(isset($a["value$language"])) $option->set("value$language", $a["value$language"]);
		}
		return $option; 
	}

	/**
	 * Given a newline separated options string, convert it to an array
	 * 
	 * @param $value
	 * @return array
	 * @throws WireException
	 * 
	 */
	protected function optionsStringToArray($value) {
		
		if(!is_string($value)) throw new WireException("value must be string");
		$optionsArray = array();
		
		foreach(explode("\n", $value) as $line) {

			if(empty($line)) continue;

			$pos = strpos($line, '=');
			
			if($pos === false) {
				// new option
				$id = 0;
				$title = trim($line); 

			} else {
				// an equals sign is present
				$id = trim(substr($line, 0, $pos));
				$title = trim(substr($line, $pos+1));

				if(ctype_digit("$id")) {
					// existing option
					$id = (int) $id; 

				} else {
					// new option that has an equals sign in it
					$id = 0;
				}
			}
		
			// determine if there are separate title and value
			$pos = strpos($title, '|');
			if($pos !== false) {
				$optionValue = trim(substr($title, 0, $pos)); 
				$title = trim(substr($title, $pos+1)); 
			} else {
				$optionValue = '';
			}
			
			$option = array(
				'id' => $id, 
				'title' => $title, 
				'value' => $optionValue, 
			);
			
			if($id) {
				// existing option
				$optionsArray["id$id"] = $option;
			} else {
				// new option
				$optionsArray[] = $option;
			}
		}
		
		return $optionsArray;
	}

	/**
	 * Convert an array of option arrays, to a SelectableOptionArray of SelectableOption objects
	 * 
	 * @param array $value
	 * @return SelectableOptionArray
	 * @throws WireException
	 * 
	 */
	protected function optionsArrayToObjects(array $value) {
		$options = $this->wire(new SelectableOptionArray());
		foreach($value as $o) {
			$option = $this->wire(new SelectableOption());
			foreach($o as $k => $v) {
				$option->set($k, $v); 
			}
			$options->add($option);
		}
		return $options; 
	}

	/**
	 * Get the options input string used for 
	 * 
	 * @param SelectableOptionArray $options
	 * @param int|string|Language $language Language id, object, or name, if applicable
	 * @return string
	 * @throws WireException if given invalid language
	 * 
	 */
	public function getOptionsString(SelectableOptionArray $options, $language = '') {
		
		if($language && $this->useLanguages()) {
			if(is_string($language) || is_int($language)) {
				$language = $this->wire()->languages->get($language);
				if(!$language->id) throw new WireException("Unknown language: $language"); 
			}
			if(is_object($language) && $language->isDefault()) $language = '';
		} else {
			$language = '';
		}
		
		$out = '';
		
		foreach($options as $option) {
			/** @var SelectableOption $option */
			$title = trim((string) $option->get("title$language"));
			$value = trim((string) $option->get("value$language"));
			$titleLength = strlen($title);
			$valueLength = strlen($value);
			if(!$titleLength && !$valueLength) continue;
			if($titleLength && $valueLength) $title = "$value|$title";
			$out .= "$option->id=$title\n";
		}
		
		return trim($out);
	}

	/**
	 * Set an options string
	 * 
	 * Should adhere to the format 
	 *
	 * One option per line in the format: 123=title or 123=value|title
	 * where 123 is the option ID, 'value' is an optional value,
	 * and 'title' is a required title. 
	 * 
	 * For new options, specify just the option title 
	 * (or value|title) on its own line. Options should
	 * be in the desired sort order.
	 *
	 * @param Field $field
	 * @param string $value
	 * @param bool $allowDelete Allow removed lines in the string to result in deleted options?
	 * 	If false, no options will be affected but you can call the getRemovedOptionIDs() method
	 * 	to retrieve them for confirmation. 
	 * @return array containing ('added' => cnt, 'updated' => cnt, 'deleted' => cnt, 'marked' => cnt)
	 * 	note: 'marked' means marked for deletion
	 *
	 */
	public function setOptionsString(Field $field, $value, $allowDelete = true) {
		$a = $this->optionsStringToArray($value); 
		$options = $this->optionsArrayToObjects($a); 
		$options->setField($field); 
		return $this->setOptions($field, $options, $allowDelete);
	}

	/**
	 * Set options string, but for each language
	 * 
	 * @param Field $field
	 * @param array $values Array of ($languageID => string), one for each language
	 * @param bool $allowDelete Allow removed lines in the string to result in deleted options?
	 * 	If false, no options will be affected but you can call the getRemovedOptionIDs() method
	 * 	to retrieve them for confirmation. 
	 * @throws WireException
	 * 
	 */
	public function setOptionsStringLanguages(Field $field, array $values, $allowDelete = true) {
		if(!$this->useLanguages) throw new WireException("Language support not active"); 
		$arrays = array();
		$default = array();
		$languages = $this->wire()->languages;
		foreach($values as $languageID => $value) {
			$language = $languages->get($languageID); 
			if(!$language || !$language->id) {
				$this->error("Unknown language: $language");
				continue; 
			}
			$a = $this->optionsStringToArray($value); 
			if($language->isDefault()) {
				$default = $a; 
			} else {
				$arrays[$languageID] = $a;
			}
		}
		$options = $this->optionsArrayToObjects($default); 
		$options->setField($field); 
		foreach($options as $option) {
			/** @var SelectableOption $option */ 
			foreach($arrays as $languageID => $a) {
				$key = "id$option->id";
				if(!isset($a[$key])) continue;
				$o = $a[$key]; 
				$option->set("title$languageID", $o['title']);
				$option->set("value$languageID", $o['value']); 
			}
		}
		$this->setOptions($field, $options, $allowDelete); 
	}

	/**
	 * Set current options for $field, identify and acting on added, deleted, updated options
	 *
	 * @param Field $field
	 * @param array|SelectableOptionArray $options Array of SelectableOption objects
	 * 	For new options specify 0 for the 'id' property.
	 * @param bool $allowDelete Allow options to be deleted? If false, the options marked for
	 * 	deletion can be retrieved via $this->getRemovedOptions($field);
	 * @return array containing ('added' => cnt, 'updated' => cnt, 'deleted' => cnt, 'marked' => cnt) 
	 * 	note: 'marked' means marked for deletion
	 * @throws WireException
	 *
	 */
	public function setOptions(Field $field, $options, $allowDelete = true) {

		$existingOptions = $this->getOptions($field);
		$updatedOptions = array();
		$deletedOptionIDs = array();
		$addedOptions = array();
		$result = array(
			'added' => 0,
			'updated' => 0,
			'deleted' => 0,
			'marked' => 0
		);

		// iterate through new options
		$sort = 0;

		foreach($options as $option) {
			/** @var SelectableOption $option */

			$option->set('sort', $sort);

			if(!$option->id) {
				// new option to add 
				$addedOptions[] = $option;

			} else {
				// existing option
				$o = null;
				foreach($existingOptions as $existingOption) {
					if($existingOption->id == $option->id) {
						$o = $existingOption;
						break;
					}
				}
				if($o) {
					// found option with same id, has anything changed?
					if($o->values(true) != $option->values(true)) {
						$updatedOptions[] = $option;
					}
				} else {
					// user must have specified their own id
					$addedOptions[] = $option;
				}
			}

			$sort++;
		}

		// iterate through existing options to determine which of them
		// are no longer present and thus should be deleted
		foreach($existingOptions as $existingOption) {
			$found = false;
			foreach($options as $option) {
				if($option->id == $existingOption->id || $existingOption->id && !$option->id) {
					$found = true;
					break;
				}
			}
			if(!$found) {
				$deletedOptionIDs[] = (int) $existingOption->id;
			}
		}

		// insert new options
		if(count($addedOptions)) {
			$result['added'] = $this->addOptions($field, $addedOptions);
		}

		// delete options
		if(count($deletedOptionIDs)) {
			if($allowDelete) {
				$result['deleted'] = $this->deleteOptionsByID($field, $deletedOptionIDs);
			} else {
				$result['marked'] = $this->removedOptionIDs = $deletedOptionIDs; 
			}
		}

		// update options
		if(count($updatedOptions)) {
			$result['updated'] = $this->updateOptions($field, $updatedOptions);
		}
		
		return $result;
	}

	/**
	 * Update options for field
	 *
	 * @param Field $field
	 * @param array|SelectableOptionArray $options
	 * @return int Number of options updated
	 *
	 */
	public function updateOptions(Field $field, $options) {
		
		unset($this->optionsCache[$field->id]); 

		$database = $this->wire()->database;
		$sql = "UPDATE " . self::optionsTable . " SET sort=:sort, title=:title, `value`=:value ";
		$bindCols = array();
		
		if($this->useLanguages) foreach($this->wire()->languages as $language) {
			if($language->isDefault()) continue; 
			foreach(array('title', 'value') as $name) {
				$name .= (int) $language->id;
				$sql .= ", $name=:$name ";
				$bindCols[] = $name;
			}
		}
		
		$sql .=	"WHERE fields_id=:fields_id AND option_id=:option_id";

		$cnt = 0;
		$query = $database->prepare($sql);

		foreach($options as $option) {
			if(!$option instanceof SelectableOption) continue;
			if(!$option->id) continue;
			$query->bindValue(':fields_id', $field->id);
			$query->bindValue(':option_id', $option->id);
			$query->bindValue(':sort', $option->sort);
			$query->bindValue(':title', $option->title);
			$query->bindValue(':value', $option->value); 
			foreach($bindCols as $name) {
				$value = $option->get($name); 
				$query->bindValue(":$name", $value); 
			}
			try {
				if($query->execute()) $cnt++;
			} catch(\Exception $e) {
				$this->error("Option $option->id '$option->title': " . $e->getMessage());
				if(strpos($e->getMessage(), '42S22')) $this->updateLanguages();
			}
		}
		
		$this->message(sprintf($this->_n('Updated %d option', 'Updated %d options', $cnt), $cnt));

		return $cnt;
	}

	/**
	 * Delete the given options for $field
	 *
	 * @param Field $field
	 * @param array|SelectableOptionArray $options
	 * @return int Number of options deleted
	 *
	 */
	public function deleteOptions(Field $field, $options) {
		unset($this->optionsCache[$field->id]); 
		$ids = array();
		foreach($options as $option) {
			if(!$option instanceof SelectableOption) continue;
			$id = (int) $option->id;
			if($id) $ids[] = $id;
		}
		if(count($ids)) return $this->deleteOptionsByID($field, $ids);
		return 0;
	}

	/**
	 * Delete the given option IDs
	 *
	 * @param Field $field
	 * @param array $ids
	 * @return int Number of options deleted
	 *
	 */
	public function deleteOptionsByID(Field $field, array $ids) {

		unset($this->optionsCache[$field->id]); 
		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		$cleanIDs = array();

		foreach($ids as $id) {
			$cleanIDs[] = (int) $id;
		}

		// convert to SQL ready string
		$cleanIDs = implode(',', $cleanIDs);

		// delete from field_[fieldName] table
		$sql = "DELETE FROM `$table` WHERE data IN($cleanIDs)";
		$query = $database->prepare($sql);
		$query->execute();
		$cnt = $query->rowCount();
		$this->message("Deleted $cnt rows from table $table", Notice::debug);

		// delete from fieldtype_options table
		$table = self::optionsTable;
		$sql = "DELETE FROM `$table` WHERE fields_id=:fields_id AND option_id IN($cleanIDs)";
		$query = $database->prepare($sql);
		$query->bindValue(':fields_id', $field->id);
		$query->execute();
		$cnt = $query->rowCount();
		$this->message("Deleted $cnt rows from table $table", Notice::debug);
		
		$this->message(sprintf($this->_n('Deleted %d option', 'Deleted %d options', $cnt), $cnt));

		return $cnt;
	}

	/**
	 * Delete all options for given field
	 * 
	 * @param Field $field
	 * @return int
	 * @throws WireException
	 * 
	 */
	public function deleteAllOptionsForField(Field $field) {
		$database = $this->wire()->database;
		$table = self::optionsTable;
		$sql = "DELETE FROM `$table` WHERE fields_id=:fields_id";
		$query = $database->prepare($sql);
		$query->bindValue(':fields_id', $field->id, \PDO::PARAM_INT);
		$query->execute();
		$cnt = $query->rowCount();
		$this->message("Deleted $cnt row(s) from table $table", Notice::debug);
		return $cnt;
	}

	/**
	 * Add the given option titles for $field
	 *
	 * @param Field $field
	 * @param array|SelectableOptionArray $options
	 * @return int Number of options added
	 *
	 */
	public function addOptions(Field $field, $options) {
		
		$database = $this->wire()->database;
		
		// options that have pre-assigned IDs
		$optionsByID = array();

		unset($this->optionsCache[$field->id]);

		// determine if any added options already have IDs
		foreach($options as $option) {
			if(!$option instanceof SelectableOption || !strlen($option->title)) continue;
			if($option->id > 0) $optionsByID[(int) $option->id] = $option;
		}
		
		if(count($options) > count($optionsByID)) {
			// Determine starting value (max) for auto-assigned IDs
			$sql =
				"SELECT MAX(option_id) FROM " . self::optionsTable . " " .
				"WHERE fields_id=:fields_id";

			$query = $database->prepare($sql);
			$query->bindValue(':fields_id', $field->id);
			$query->execute();

			list($max) = $query->fetch(\PDO::FETCH_NUM);
			$query->closeCursor();
		} else {
			// there are no auto-assigned IDs
			$max = 0;
		}

		$sql = 	
			"INSERT INTO " . self::optionsTable . " " .
			"SET fields_id=:fields_id, option_id=:option_id, " . 
			"sort=:sort, title=:title, `value`=:value";

		$cnt = 0;
		$query = $database->prepare($sql);

		foreach($options as $option) {
			if(!$option instanceof SelectableOption || !strlen($option->title)) continue;
			if($option->id > 0) {
				$id = $option->id;
			} else {
				$id = ++$max;
				while(isset($optionsByID[$id])) $id++;
			}
			$query->bindValue(':fields_id', $field->id, \PDO::PARAM_INT);
			$query->bindValue(':option_id', $id, \PDO::PARAM_INT);
			$query->bindValue(':sort', $option->sort, \PDO::PARAM_INT);
			$query->bindValue(':title', $option->title);
			$query->bindValue(':value', $option->value); 
			
			try {
				if($query->execute()) $cnt++;
				$option->id = $database->lastInsertId();

			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		}
		
		$this->message(sprintf($this->_n('Added %d option', 'Added %d options', $cnt), $cnt));

		return $cnt;
	}

	/**
	 * Hook method called when a language is added or deleted
	 * 
	 * Also called when module is installed
	 * 
	 * @param HookEvent|null $event
	 * 
	 */
	public function updateLanguages(?HookEvent $event = null) {
		
		if(!$this->useLanguages || !$this->wire()->languages) return;
		
		if($event) {
			$language = $event->arguments(0); /** @var Language $language */
			$updateType = $event->arguments(1); /** @var string $updateType one of 'added' or 'deleted' */
		} else {
			$language = null;
			$updateType = '';
		}
		
		if($updateType === 'deleted') {
			$sqls = $this->checkLanguagesDeleted($language);
		} else if($updateType === 'added') {
			$sqls = $this->checkLanguagesAdded($language);
		} else {
			$sqls = array_merge($this->checkLanguagesAdded(), $this->checkLanguagesDeleted());
		}

		$database = $this->wire()->database;
		
		foreach($sqls as $sql) {
			try {
				$database->exec($sql);
			} catch(\Exception $e) {
				$this->error("$sql -- " . $e->getMessage());
			}
		}
	}

	/**
	 * Check for added languages
	 * 
	 * @param Language|null $languageAdded
	 * @return array SQL statements to add language when appropriate
	 * 
	 */
	protected function checkLanguagesAdded($languageAdded = null) {
		
		$database = $this->wire()->database;
		$table = self::optionsTable;
		$maxLen = $database->getMaxIndexLength();
		$sqls = array();
		$languages = $languageAdded ? array($languageAdded) : $this->wire()->languages;
		
		if(strtolower($this->wire()->config->dbCharset) == 'utf8mb4') $maxLen -= 20;
		
		foreach($languages as $language) {
			/** @var Language $language */
			if($language->isDefault()) continue;
			$titleCol = "title" . (int) $language->id;
			$valueCol = "value" . (int) $language->id;
			if($database->columnExists($table, $valueCol)) continue;
			$this->message("FieldtypeOptions: Add language $language->name (id=$language)", Notice::debug);
			$sqls[] = "ALTER TABLE $table ADD $titleCol TEXT";
			$sqls[] = "ALTER TABLE $table ADD UNIQUE $titleCol ($titleCol($maxLen), fields_id)";
			$sqls[] = "ALTER TABLE $table ADD $valueCol VARCHAR($maxLen)";
			$sqls[] = "ALTER TABLE $table ADD INDEX $valueCol ($valueCol($maxLen), fields_id)";
			$sqls[] = "CREATE FULLTEXT INDEX {$titleCol}_ft ON $table($titleCol)";
			$sqls[] = "CREATE FULLTEXT INDEX {$valueCol}_ft ON $table($valueCol)";
		}
		
		return $sqls;
	}

	/**
	 * Check for deleted languages
	 * 
	 * @param Language|null $languageDeleted
	 * @return array SQL statements to delete language when appropriate
	 * 
	 */
	protected function checkLanguagesDeleted($languageDeleted = null) {
		
		$database = $this->wire()->database;
		$table = self::optionsTable;
		$languages = $this->wire()->languages;
		$indexes = $database->getIndexes($table, true);
		
		$query = $database->prepare("SHOW COLUMNS FROM $table LIKE 'title%'");
		$query->execute();
		$rows = array();
		$sqls = array();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) $rows[] = $row;
		$query->closeCursor();

		foreach($rows as $row) {
			$name = $row['Field'];
			if($name === 'title') continue;
			$id = (int) str_replace('title', '', $name);
			if($languageDeleted) {
				// language specified and if it matches column name then allow it
				if($languageDeleted->id !== $id) continue;
			} else {
				// check if language exists and if yes then skip it 
				$language = $languages->get($id);
				if($language && $language->id) continue;
			}
			$this->message("FieldtypeOptions: Delete language $id", Notice::debug);
			
			$titleCol = "title$id";
			$valueCol = "value$id";
		
			// Drop unique index: title+fields_id
			if(isset($indexes[$titleCol])) $sqls[] = "ALTER TABLE $table DROP INDEX $titleCol";
			if(isset($indexes[$valueCol])) $sqls[] = "ALTER TABLE $table DROP INDEX $valueCol";
		
			// Drop fulltext index
			if(isset($indexes[$titleCol . '_ft'])) $sqls[] = "ALTER TABLE $table DROP INDEX {$titleCol}_ft";
			if(isset($indexes[$valueCol . '_ft'])) $sqls[] = "ALTER TABLE $table DROP INDEX {$valueCol}_ft";
			
			// Drop older style combined index if present	
			if(isset($indexes["{$titleCol}_$valueCol"])) $sqls[] = "ALTER TABLE $table DROP INDEX {$titleCol}_$valueCol"; 
		
			// drop column
			$sqls[] = "ALTER TABLE $table DROP $titleCol";
			$sqls[] = "ALTER TABLE $table DROP $valueCol";
		}
		
		return $sqls;
	}

	/**
	 * Upgrade fieldtype_options table
	 * 
	 * @param string $fromVersion
	 * @param string $toVersion
	 * @throws WireException
	 * 
	 */
	public function upgrade($fromVersion, $toVersion) {
		
		if($fromVersion && $toVersion) {} // ignore
		
		$database = $this->wire()->database;
		$table = self::optionsTable;
		
		if(!$database->tableExists($table)) return;

		$indexes = $database->getIndexes($table, true);
		
		if(isset($indexes['title_value'])) {
			// removed combined title+value indexes created prior to 3.0.182
			// and replace with separate fulltext indexes for title and value
			foreach($indexes as $name => $info) {
				if(strpos($name, 'title') !== 0) continue;
				if(!strpos($name, '_value')) continue;
				// i.e. title_value or title123_value123
				$database->exec("ALTER TABLE $table DROP INDEX `$name`");
				$this->message("Dropped index $table.$name", Notice::debug);
				foreach($info['columns'] as $col) {
					try {
						$sql = "CREATE FULLTEXT INDEX {$col}_ft ON $table($col)";
						$database->exec($sql);
						$this->message("Added fulltext index for $table.$col", Notice::debug);
					} catch(\Exception $e) {
						$this->error("$sql -- " . $e->getMessage());
					}
				}
			}
		}
	}

	/**
	 * Install 
	 * 
	 */
	public function install() {

		$database = $this->wire()->database; 
		$maxLen = $database->getMaxIndexLength();
		
		if(!$database->tableExists(self::optionsTable)) {
			$config = $this->wire()->config;
			$engine = $config->dbEngine;
			$charset = $config->dbCharset;
			$table = self::optionsTable;
			if(strtolower($charset) == 'utf8mb4') $maxLen -= 20;
			$sql =
				"CREATE TABLE $table (" .
				"fields_id INT UNSIGNED NOT NULL, " .
				"option_id INT UNSIGNED NOT NULL, " .
				"`title` TEXT, " .
				"`value` VARCHAR($maxLen), " .
				"sort INT UNSIGNED NOT NULL, " .
				"PRIMARY KEY (fields_id, option_id), " .
				"UNIQUE title (title($maxLen), fields_id), " .
				"INDEX `value` (`value`($maxLen), fields_id), " .
				"INDEX sort (sort, fields_id) " .
				") ENGINE=$engine DEFAULT CHARSET=$charset";
			$database->exec($sql);
			try {
				$database->exec("CREATE FULLTEXT INDEX title_ft ON $table(`title`)");
				$database->exec("CREATE FULLTEXT INDEX value_ft ON $table(`value`)");
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		}
		
		if($this->useLanguages) $this->updateLanguages();
	}

	/**
	 * Uninstall
	 *
	 */
	public function uninstall() {
		try {
			$this->wire()->database->exec("DROP TABLE " . self::optionsTable);
		} catch(\Exception $e) {
			$this->warning($e->getMessage());
		}
	}
}
