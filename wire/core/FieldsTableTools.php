<?php namespace ProcessWire;
/**
 * ProcessWire Fields Table and Index tools
 *
 * #pw-summary Methods for managing DB tables and indexes for fields, and related methods. Accessed from `$fields->tableTools()`.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.150
 * 
 * #pw-internal
 * 
 */ 
class FieldsTableTools extends Wire {
	
	/**
	 * Find duplicate rows for a specific column in a field’s table
	 *
	 * #pw-internal
	 *
	 * @param Field $field
	 * @param array $options
	 *  - `column` (string): Name of column to find duplicate values in (default='data')
	 *  - `value` (bool|string): Value to find duplicates of, or false to find all duplicate values (default=false)
	 *  - `verbose` (bool): Include entire DB rows in returned result? (default=false)
	 * @return array Returns array of arrays where each item contains indexes of 'count' (int) and 'value' (int|string), plus,
	 *  if the `verbose` option is true, returned value also adds a `rows` index (array) containing contents of entire matching DB rows.
	 *
	 */
	public function findDuplicateRows(Field $field, array $options = array()) {

		$defaults = array(
			'column' => 'data',
			'value' => false,
			'verbose' => false,
		);

		$options = array_merge($defaults, $options);
		$result = array();

		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		$col = $database->escapeCol($options['column']);
		$sql = "SELECT $col, COUNT($col) FROM $table ";

		if($options['value'] !== false) {
			if($options['value'] === null) {
				$sql .= "WHERE $col IS NULL ";
			} else {
				$sql .= "WHERE $col=:val ";
			}
		}

		$sql .= "GROUP BY $col HAVING COUNT($col) > 1";
		$query = $database->prepare($sql);

		if($options['value'] !== false && $options['value'] !== null) {
			$query->bindValue(':val', $options['value']);
		}

		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			$result[] = array('value' => $row[0], 'count' => (int) $row[1]);
		}

		$query->closeCursor();

		if($options['verbose']) {
			foreach($result as $key => $item) {
				$result[$key]['rows'] = array();
				$sql = "SELECT * FROM $table WHERE $col=:val";
				$query = $database->prepare($sql);
				$query->bindValue(':val', $item['value']);
				$query->execute();
				while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
					$result[$key]['rows'][] = $row;
				}
				$query->closeCursor();
			}
		}

		return $result;
	}
	
	/**
	 * Add or remove a unique index for a field on its 'data' column
	 *
	 * #pw-internal
	 *
	 * @param Field $field
	 * @param bool $add Specify false to remove index rather than add (default=true)
	 * @return bool|int Returns one of the following when adding index:
	 *  - `true` (bool): When index successfully added.
	 *  - `false` (bool): Index cannot be added because there are non-unique rows already present (not allowed).
	 *  - `1` (int): Unique index already present so was not necessary (not needed).
	 *  - `0` (int): Requested column does not exist in table so cannot be added as index (not allowed).
	 *  Returns one of the following when removing index:
	 *  - `true` (bool): When index successfully removed.
	 *  - `false` (bool): When index failed to remove.
	 *  - `1` (int): When remove index but there is no unique index to remove (not needed).
	 *  - `0` (int): When remove index that is not one we have previously added (not allowed).
	 * @throws \PDOException When given invalid column name or unknown error condition
	 *
	 */
	public function setUniqueIndex(Field $field, $add = true) { 

		$database = $this->wire()->database;
		$col = 'data';
		$table = $database->escapeTable($field->getTable());
		$uniqueIndexName = $this->hasUniqueIndex($field, $col);
		$requireIndexName = $database->escapeCol($col . '_unique');
		$action = ''; // whether to 'add' or 'remove' flag and property from Field 

		if($uniqueIndexName) {
			// already has unique index for indicated column
			if($add) {
				// already has unique index name
				$result = 1;
				$action = 'add';
				
			} else {
				// remove requested
				if($uniqueIndexName === $requireIndexName) {
					// remove the unique index
					$sql = "ALTER TABLE $table DROP INDEX `$requireIndexName`";
					try {
						$result = $database->exec($sql) !== false;
						if($result) $action = 'remove';
					} catch(\Exception $e) {
						$result = false;
					}
				} else {
					// unique index present but it’s not one we previously added
					$result = 0;
					$action = 'remove';
				}
			}

		} else if($add) {
			// no unique index yet exists for column, so add one
			$col = $database->escapeCol($col);
			$sql = "ALTER TABLE $table ADD UNIQUE `$requireIndexName` (`$col`)";
			try {
				$result = $database->exec($sql) !== false;
				if($result) $action = 'add';
			} catch(\Exception $e) {
				$action = 'remove';
				if($e->getCode() == 23000) {
					// non unique rows already present
					$result = false;
				} else if($e->getCode() == 42000) {
					// requested column does not exist
					$result = 0;
				} else {
					throw $e;
				}
			}
			
		} else {
			// remove properties indicating unique
			if($field->hasFlag(Field::flagUnique) || $field->flagUnique) $action = 'remove';
			$result = 1;
		}
		
		if($action) {
			$save = false;
			if($action === 'add') {
				if(!$field->hasFlag(Field::flagUnique)) $save = $field->addFlag(Field::flagUnique);
				if(!$field->flagUnique) $save = $field->set('flagUnique', true);
			} else if($action === 'remove') {
				if($field->hasFlag(Field::flagUnique)) $save = $field->removeFlag(Field::flagUnique);
				if($field->flagUnique) $save = $field->remove('flagUnique');
			}
			if($save) $field->save();
		}
		
		return $result;
	}

	/**
	 * Does given field have a unique index on column?
	 *
	 * #pw-internal
	 *
	 * @param Field $field
	 * @param string $col
	 * @return bool|string Returns index name when present, or boolean false when not
	 *
	 */
	public function hasUniqueIndex(Field $field, $col = 'data') {
		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		$sql = "SHOW INDEX FROM $table";
		$query = $database->prepare($sql);
		$query->execute();
		$has = false;
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			if($row['Column_name'] === $col && !$row['Non_unique']) {
				$has = $row['Key_name'];
				break;
			}
		}
		$query->closeCursor();
		return $has;
	}

	/**
	 * Check state of field unique 'data' index and update as needed
	 * 
	 * @param Field $field
	 * @param bool $verbose Show messages when changes made? (default=true)
	 * @throws WireException
	 * 
	 */
	public function checkUniqueIndex(Field $field, $verbose = true) {
		
		static $checking = false;
		if($checking) return;
		
		$database = $this->wire()->database;
		
		$col = 'data';

		// is unique index requested?
		$useUnique = (bool) $field->get('flagUnique');
		
		// is unique index already present?
		$hasUnique = (bool) $field->hasFlag(Field::flagUnique);
		
		if($useUnique === $hasUnique) return;
		
		if(!$database->tableExists($field->getTable())) return;
	
		$checking = true;

		if($useUnique && !$hasUnique) {
			// add unique index
			$qty = $this->deleteEmptyRows($field, $col);
			
			if($qty && $verbose) {
				$this->message(sprintf($this->_('Deleted %d empty row(s) for field %s'), $qty, $field->name));
			}
			
			$result = $this->setUniqueIndex($field, true);
			
			if($result === false && $verbose) {
				$pageEditUrl = $this->wire()->config->urls->admin . 'page/edit/?id=';
				$msg = $this->_('Unique index cannot be added yet because there are already non-unique row(s) present:') . ' ';
				$rows = $this->findDuplicateRows($field, array('verbose' => true, 'column' => $col));
				foreach($rows as $row) {
					$ids = array();
					foreach($row['rows'] as $a) {
						$ids[] = '[' . $a['pages_id'] . '](' . $pageEditUrl . $a['pages_id'] . ')';
					}
					$msg .= "[br]• $row[value] — " . 
						sprintf($this->_('Appears %d times'), $row['count']) . ' ' . 
						sprintf($this->_('(page IDs: %s)'), implode(', ', $ids)) . ' ';
				}
				$this->error($msg, Notice::noGroup | Notice::allowMarkdown);
				
			} else if($result && $verbose) {
				$this->message($this->_('Added unique index') . " ($field->name)", Notice::noGroup);
			}

		} else if($hasUnique && !$useUnique) {
			// remove unique index
			$result = $this->setUniqueIndex($field, false);
			if($result && $verbose) $this->message($this->_('Removed unique index') . " ($field->name)", Notice::noGroup);
		}
	
		$checking = false;
	}

	/**
	 * Delete rows having empty column value
	 * 
	 * @param Field $field
	 * @param string $col Column name (default='data')
	 * @param bool $strict When true, delete not allowed if there are columns other than one given and 'pages_id' (default=true)
	 * @return bool|int Returns false if delete not allowed, otherwise returns int with # of rows deleted
	 * @throws WireException
	 * 
	 */
	public function deleteEmptyRows(Field $field, $col = 'data', $strict = true) {
		
		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		$fieldtype = $field->type;
		$schema = $fieldtype->getDatabaseSchema($field);
		$wheres = array();
		
		$types = array(
			'INT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
			'TEXT', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT',
			'DATE', 'TIME', 'DATETIME', 'TIMESTAMP',
			'CHAR', 'VARCHAR', 
		);
		
		unset($schema['keys'], $schema['pages_id'], $schema['xtra']);

		if(!isset($schema[$col])) return false; // if there's no schema for this column, fail
		if($strict && count($schema) > 1) return false; // if there are other columns too, fail

		$type = strtoupper($schema[$col]);
		$allowNull = strpos($type, 'NOT NULL') === false;
		
		if(strpos($type, ' ')) list($type,) = explode(' ', $type, 2);
		if(strpos($type, '(')) list($type,) = explode('(', $type, 2);
		
		if(!in_array(trim($type), $types)) return false; // if not in allowed col types, fail
		
		if($col !== 'data') {
			$col = $database->escapeCol($this->wire()->sanitizer->fieldName($col));
			if(empty($col)) return false;
		}

		if(strpos($type, 'INT') !== false) {
			if($fieldtype->isEmptyValue($field, 0)) {
				$wheres[] = "$col=0";
			}
		} else if($fieldtype->isEmptyValue($field, '')) {
			$wheres[] = "$col=''";
		}
		
		if($allowNull) {
			$wheres[] = "$col IS NULL";
		}
	
		if(count($wheres)) {
			// delete empty rows matching our conditions
			$sql = "DELETE FROM $table WHERE " . implode(' OR ', $wheres);
			$query = $database->prepare($sql);
			$result = $query->execute() ? $query->rowCount() : 0;
			$query->closeCursor();
		} else {
			// no empty rows possible
			$result = true;
		}
		
		return $result;
	}

	/**
	 * Create a checkbox Inputfield to configure unique value state
	 * 
	 * @param Field $field
	 * @return InputfieldCheckbox
	 * 
	 */
	public function getUniqueIndexInputfield(Field $field) {
	
		$col = 'data';
		$modules = $this->wire()->modules;
		
		if((bool) $field->flagUnique != $field->hasFlag(Field::flagUnique)) {
			$this->checkUniqueIndex($field, true);
		}

		$f = $modules->get('InputfieldCheckbox'); /** @var InputfieldCheckbox $f */
		$f->attr('name', "flagUnique");
		$f->label = $this->_('Unique');
		$f->icon = 'hand-stop-o';
		$f->description = $this->_('When checked, a given value may not be used more than once in this field, and thus may not appear on more than one page.');
		
		if($this->hasUniqueIndex($field, $col)) {
			$f->attr('checked', 'checked');
			if(!$field->hasFlag(Field::flagUnique)) $field->addFlag(Field::flagUnique);
			if(!$field->flagUnique) $field->flagUnique = true;
		}
	
		return $f;
	}

	/**
	 * Does given value exist anywhere in field table?
	 * 
	 * @param Field $field
	 * @param string|int $value
	 * @param string $col
	 * @return int Returns page ID where value exists, if found. Otherwise returns 0. 
	 * @throws WireException
	 * 
	 */
	public function valueExists(Field $field, $value, $col = 'data') {
		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		if($col !== 'data') $col = $database->escapeCol($this->wire()->sanitizer->fieldName($col)); 
		$sql = "SELECT pages_id FROM $table WHERE $col=:val LIMIT 1";
		$query = $database->prepare($sql);
		$query->bindValue(':val', $value); 
		$query->execute();
		$pageId = $query->rowCount() ? (int) $query->fetchColumn() : 0;
		$query->closeCursor();
		return $pageId;
	}
	
}
