<?php namespace ProcessWire;

/**
 * ProcessWire FieldtypeMulti
 *
 * Interface and some functionality for Fieldtypes that can contain multiple values.
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @method bool savePageFieldRows(Page $page, Field $field, $value)
 * @method int deletePageFieldRows(Page $page, Field $field, $value)
 * 
 * @property bool|null $usePagination Set this to true in __construct() to allow pagination features. Requires orderByCols. #pw-internal
 * @property bool|null $useOrderByCols Set this to true in __construct() to allow alternate sorting features. #pw-internal
 * 
 * To support automatic “order by” sorting: The `$useOrderByCols` property of this Fieldtype must be set to boolean true, 
 * indicating that the Fieldtype supports sorting. The actual columns to order by are an array of 'col' or '-col' specified
 * with the Field object in an $orderByCols property (array). 
 * 
 * To support pagination: Both the `$useOrderByCols` and the `$usePagination` properties of this Fieldtype must be set to 
 * boolean true, indicating the Fieldtype supports pagination (and sorting). When enabled, the wakeupValue() method will receive
 * pagination information in the value it is given. All other aspects of pagination must be handled by the individual Fieldtype.
 * 
 *
 */
abstract class FieldtypeMulti extends Fieldtype {

	/**
	 * Separator for multi values when using GROUP_CONCAT()
 	 *
	 * TODO sanitize set() values from ever containing this separator
	 *
	 */
	const multiValueSeparator = "\0,";

	/**
	 * For internal use to count the number of calls to getMatchQuery
	 *
	 * Used for creating unique table names to the same field in the same query
	 *
	 */
	protected static $getMatchQueryCount = 0;

	/**
	 * Do we currently have a locked table?
	 * 
	 * @var bool
	 * 
	 */
	protected $lockedTable = false;

	/**
	 * Modify the default schema provided by Fieldtype to include a 'sort' field, and integrate that into the primary key.
	 * 
	 * @param Field $field
	 * @return array
	 *
	 */
	public function getDatabaseSchema(Field $field) {
		$schema = parent::getDatabaseSchema($field); 
		$schema['sort'] = 'int unsigned NOT NULL'; 
		$schema['keys']['primary'] = 'PRIMARY KEY (pages_id, sort)'; 
		return $schema; 
	}

	/**
	 * Return array with information about what properties and operators can be used with this field
	 * 
	 * @param Field $field
	 * @param array $data Array of extra data, when/if needed
	 * @return array
	 *
	 */
	public function ___getSelectorInfo(Field $field, array $data = array()) {
		$info = parent::___getSelectorInfo($field, $data); 
		$info['subfields']['count'] = array(
			'name' => 'count',
			'label' => $this->_('count'), 
			'operators' => array('=', '!=', '<', '>', '<=', '>='), 
			'input' => 'number'
		);
		return $info; 
	}

	/**
	 * Get an array of Fieldtypes that are compatible with this one (i.e. ones the user may change the type to)
	 *
	 * @param Field $field Just in case it's needed
	 * @return Fieldtypes|null
	 *
	 */
	public function ___getCompatibleFieldtypes(Field $field) {
		$fieldtypes = $this->wire(new Fieldtypes());
		foreach($this->wire()->fieldtypes as $fieldtype) {
			if($fieldtype instanceof FieldtypeMulti) $fieldtypes->add($fieldtype); 
		}
		return $fieldtypes; 
	}

	/**
	 * Per Fieldtype interface, return a blank value of this Fieldtype
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @return WireArray
	 *
	 */
	public function getBlankValue(Page $page, Field $field) {
		return $this->wire(new WireArray());
	}

	/**
	 * Per the Fieldtype interface, sanitize the combined value for use in a Page
	 *
	 * In this case, make sure that it's a WireArray (able to hold multiple values)
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param mixed $value
	 * @return WireArray
	 *
	 */
	public function sanitizeValue(Page $page, Field $field, $value) {
		return $value instanceof WireArray ? $value : $this->wire(new WireArray());
	}

	/**
	 * Process the value to convert it from array to whatever object it needs to be
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param array $value
	 * @return WireArray
	 *
	 */ 
	public function ___wakeupValue(Page $page, Field $field, $value) {
		
		$target = $this->getBlankValue($page, $field);
		$targetIsObject = is_object($target); 
		
		if(!is_array($value)) $value = array($value);
	
		// if pagination information was passed in, populate it to the WirePaginatable value
		if(isset($value['_pagination_limit'])) {
			if($target instanceof WirePaginatable) {
				$target->setLimit($value['_pagination_limit']);
				$target->setStart($value['_pagination_start']);
				$target->setTotal($value['_pagination_total']);
			} else {
				// probably should not have been pagination information here
				// since the Fieldtype value does not support it 
			}
			unset($value['_pagination_limit'], $value['_pagination_start'], $value['_pagination_total']);
		}
		
		if(isset($value['_filters']) && $value['_filters'] instanceof Selectors) {
			$target->data('filters', $value['_filters']);
			unset($value['_filters']);
		}
		
		foreach($value as $val) {
			if($targetIsObject) {
				$target->add($val);
			} else {
				$target[] = $val;
			}
		}
		
		if($targetIsObject) $target->resetTrackChanges(true);
		
		return $target; 
	}

	/**
	 * Given an 'awake' value, as set by wakeupValue, convert the value back to a basic type for storage in DB. 
	 *
	 * FieldtypeMulti::savePageField expects values as an array, so we convert the $value object to an array
	 *
	 * Note that FieldtypeMulti is designed around potentially supporting more than just the 'data' field in 
	 * the table, so other fieldtypes may want to override this and return an array of associative arrays containing a 'data' field
	 * and any other fields that map to the table. i.e. $values[] = array('data' => $data, 'description' => $description), etc. 
	 * See FieldtypePagefiles module class for an example of this. 
	 *              
	 * @param Page $page
	 * @param Field $field
	 * @param WireArray $value
	 * @return array
	 *
	 */
	public function ___sleepValue(Page $page, Field $field, $value) {
		$values = array();
		if(!$value instanceof WireArray) {
			if(is_array($value)) return $value; 
			return $values;
		}
		foreach($value as $v) {
			// note $v is typecast as string, which calls __toString if it's an object
			$values[] = "$v";
		}
		return $values; 
	}
	
	/**
	 * Per the Fieldtype interface, Save the given Field from the given Page to the database
	 *
	 * Because the number of values may have changed, this method plays it safe and deletes all the old values
	 * and reinserts them as new.
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return bool
	 * @throws \PDOException|WireException|WireDatabaseQueryException on failure
	 *
	 */
	public function ___savePageField(Page $page, Field $field) {

		if(!$page->id || !$field->id) return false;

		$database = $this->wire()->database;
		$config = $this->wire()->config;
		
		$useTransaction = $database->allowTransaction();
		$values = $page->get($field->name);

		if(is_object($values)) {
			if(!$values->isChanged() && !$page->isChanged($field->name)) return true;
		} else if(!$page->isChanged($field->name)) {
			return true;
		}

		if($field->get('paginationLimit')) {
			// if any kind of pagination is in use, only allow use of savePageFieldRows()
			return $this->savePageFieldRows($page, $field, $values);
		}

		$values = $this->sleepValue($page, $field, $values);
		$table = $database->escapeTable($field->table);
		$page_id = (int) $page->id;
		$schema = $this->getDatabaseSchema($field);
		$useSort = isset($schema['sort']); 

		// use transaction when possible
		if($useTransaction) $database->beginTransaction();

		try {
			// since we don't manage IDs of existing values for multi fields, we delete the existing data and insert all of it again
			$query = $database->prepare("DELETE FROM `$table` WHERE pages_id=:page_id"); // QA
			$query->bindValue(":page_id", $page_id, \PDO::PARAM_INT);
			$query->execute();
		} catch(\Exception $e) {
			if($useTransaction) $database->rollBack();
			if($config->allowExceptions) throw $e; // throw original
			WireException([ 'class' => 'WireDatabaseQueryException', 'previous' => $e ]); 
		}
		
		if(!count($values)) {
			// no values to insert, exit early
			if($useTransaction) $database->commit();
			return true;
		}

		// if the first value is not an associative (key indexed) array, then force it to be with 'data' as the key.
		// this is to allow for this method to be able to save fields that have more than just a 'data' field,
		// even though most instances will probably just use only the data field

		$value = reset($values); // first value to find definitions
		if(is_array($value)) {
			unset($value['pages_id'], $value['sort']); // likely not present, but just in case
			$keys = array_keys($value);
			foreach($keys as $k => $v) $keys[$k] = $database->escapeTableCol($v);
		} else {
			$keys = array('data');
		}
	
		// $keys is just the columns unique to the Fieldtype
		// whereas $cols is same as keys except it also has pages_id and sort

		$cols = array('pages_id');
		if($useSort) $cols[] = 'sort';
		foreach($keys as $col) $cols[] = $col;
		$intCols = $this->trimDatabaseSchema($schema, array('findType' => '*int', 'trimDefault' => false)); 
		$nullers = false;

		$sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . "`) VALUES(:" . implode(', :', $cols) . ")";
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page_id, \PDO::PARAM_INT);
		$sort = 0;
		$result = true;
		$exception = false;

		// cycle through the values to generate the query
		foreach($values as $value) {
			
			if($useSort) {
				$query->bindValue(':sort', $sort, \PDO::PARAM_INT);
			}

			// if the value is not an associative array, then force it to be one
			if(!is_array($value)) {
				$value = array('data' => $value);
			}

			// cycle through the keys, which represent DB fields (i.e. data, description, etc.) and generate the insert query
			foreach($keys as $key) {
				$val = isset($value[$key]) ? $value[$key] : null;
				
				if($val === null) {
					// null column
					// some SQL modes require NULL for auto_increment primary key (rather than blank)
					if(isset($schema[$key]) && $nullers === false) $nullers = array_merge(
						$this->trimDatabaseSchema($schema, array('findDefaultNULL' => true)),
						$this->trimDatabaseSchema($schema, array('findAutoIncrement' => true))
					);
					if($nullers && isset($nullers[$key])) {
						$query->bindValue(":$key", null, \PDO::PARAM_NULL); 
					} else {
						$query->bindValue(":$key", '');
					}
					
				} else if(isset($intCols[$key])) {
					// integer column
					$query->bindValue(":$key", (int) $val, \PDO::PARAM_INT); 
					
				} else {
					// string column
					$query->bindValue(":$key", $val); 
				}
			}
			
			try {
				$result = $query->execute();
			} catch(\Exception $e) {
				$exception = $e;
			}
			
			if($exception) break;

			$sort++;
		}
	
		if($exception) {
			/** @var \PDOException $exception */
			if($useTransaction) $database->rollBack();
			if($config->allowExceptions) throw $exception; // throw original
			WireException([ 'class' => 'WireDatabaseQueryException', 'previous' => $exception ]);
		} else {
			if($useTransaction) $database->commit();
		}

		return $result;
	}

	/**
	 * Load the given page field from the database table and return the value.
	 *
	 * - Return NULL if the value is not available, or array when it is. 
	 * - Return the value as it exists in the database (as an array), without further processing.
	 * - This is intended only to be called by Page objects on an as-needed basis.
	 * - Typically this is only called for fields that don't have 'autojoin' turned on.
	 * - Any actual conversion of the value should be handled by the `Fieldtype::wakeupValue()` method.
	 * 
	 * If pagination is active, the following extra properties are populated to the returned array value:
	 * 
	 * - `_pagination_limit` (int): The specified limit of items per pagination. 
	 * - `_pagination_start` (int): The starting index of the pagination.
	 * - `_pagination_total` (int): The total number of items across all paginations.
	 *
	 * #pw-group-loading
	 *
	 * @param Page $page Page object to save.
	 * @param Field $field Field to retrieve from the page.
	 * @return array|null
	 *
	 */
	public function ___loadPageField(Page $page, Field $field) {

		if(!$page->id || !$field->id) return null;

		$database = $this->wire()->database;
		$schema = $this->getDatabaseSchema($field);
		$table = $database->escapeTable($field->table);
		$stmt = null;

		/** @var DatabaseQuerySelect $query */
		$query = $this->wire(new DatabaseQuerySelect());
		$query = $this->getLoadQuery($field, $query);
		$bindKey = $query->bindValueGetKey($page->id); 
		$query->where("$table.pages_id=$bindKey");
		$query->from($table);
		
		try {
			$stmt = $query->prepare();
			$result = $database->execute($stmt);
		} catch(\Exception $e) {
			$result = false;
			$this->trackException($e, false, true);
		}

		if(!$result) return null;

		$fieldName = $database->escapeCol($field->name);
		$schema = $this->trimDatabaseSchema($schema);
		$values = array();

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$value = array();
			foreach($schema as $k => $unused) {
				$key = $fieldName . '__' . $k;
				$value[$k] = $row[$key];
			}
			// if there is just one 'data' field here, then don't bother with the array, just make data the value
			if(count($value) == 1 && isset($value['data'])) $value = $value['data'];
			$values[] = $value;
		}

		$stmt->closeCursor();

		// the $query object has '_limit' and '_start' properties set if pagination is requested 
		// these properties are set by the FieldtypeMulti::getLoadQuery() method
		if((int) $query->data('_limit') > 0) {
			// accommodate paginated value by collecting and passing in pagination details from $query
			// determine total number of results
			$query->set('select', array('COUNT(*) as _total'));
			$query->set('limit', array()); // clear
			$query->set('orderby', array()); // clear
			$stmt = $query->prepare();
			$stmt->execute();
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$values['_pagination_start'] = (int) $query->data('_start');
			$values['_pagination_limit'] = (int) $query->data('_limit');
			$values['_pagination_total'] = (int) $row['_total'];
		}
		
		$filters = $this->getLoadPageFieldFilters($field);	
		if(!empty($filters)) $values['_filters'] = $filters;

		return $values;
	}
	
	/**
	 * Return the query used for loading all parts of the data from this field.
	 * 
	 * #pw-group-loading
	 *
	 * @param Field $field
	 * @param DatabaseQuerySelect $query
	 * @return DatabaseQuerySelect
	 * @throws WireException
	 *
	 */
	public function getLoadQuery(Field $field, DatabaseQuerySelect $query) {

		$database = $this->wire()->database;
		$sanitizer = $this->wire()->sanitizer;
		
		$table = $database->escapeTable($field->table);
		$schemaAll = $this->getDatabaseSchema($field);
		$schema = $this->trimDatabaseSchema($schemaAll);
		$fieldName = $database->escapeCol($field->name);
		$orderByCols = array();
		$start = null;
		$limit = null;
		
		// now load any extra components (if applicable) in a 'fieldName__column' format.
		foreach($schema as $k => $v) {
			$query->select("$table.$k AS `{$fieldName}__$k`"); // QA
		}
	
		$filters = $this->getLoadPageFieldFilters($field);
		
		if(!empty($filters)) {
			// apply filters to the load query
			
			// the following are provided in $query in case needed by getLoadQueryWhere()
			// the core does not currently need them though
			$query->data('_schema', $schema);
			$query->data('_field', $field);
			$query->data('_table', $table);
			$query->data('_filters', $filters);
			
			foreach($filters as $selector) {
				
				$col = $selector->field;
				$op = $selector->operator;
				$value = $selector->value;
			
				if(is_array($col)) {
					foreach($col as $k => $v) $col[$k] = $sanitizer->fieldName($v);
				} else {
					$col = $sanitizer->fieldName($col);
				}
				
				if($col === 'sort') {
					$desc = strpos($value, '-') === 0 ? '-' : '';
					$sort = $sanitizer->fieldName(ltrim($value, '-'));
					if(isset($schema[$sort])) {
						$orderByCols[] = $desc . $sort;
					} else if($sort === 'random') {
						$orderByCols[] = $sort;
					}
					
				} else if($col === 'limit') {
					$value = (int) $value;
					if($value > -1) $limit = $value;
					
				} else if($col === 'start') {
					$value = (int) $value;
					if($value >= 0) $start = $value;
					
				} else {
					// property other than: sort, limit or start
					$query = $this->getLoadQueryWhere($field, $query, $col, $op, $value);
				}
			}
		}
	
		if(empty($orderByCols)) {
			$orderByCols = $this->get('useOrderByCols') ? $field->get('orderByCols') : array();
		}
		
		if(empty($orderByCols)) {
			// if there are no orderByCols defined, pagination & sorting not supported
			// default sort for FieldtypeMulti fields is by column 'sort'
			if(isset($schemaAll['sort'])) $query->orderby("$table.sort");

		} else {
			// one or more orderByCols is defined, enabling sorting and potential pagination
			$sorts = array();
			foreach($orderByCols as $key => $col) {
				$desc = strpos($col, '-') === 0 ? ' DESC' : '';
				$col = $sanitizer->fieldName(ltrim($col, '-'));
				if($col === 'random') {
					$sorts = array('RAND()');
					break;
				} else {
					if(!array_key_exists($col, $schema)) continue;
					$sorts[$key] = $database->escapeCol($col) . $desc;
				}
			}
			$query->orderby = $sorts;
			$query->data('_orderByCols', $orderByCols); // just in case needed elsewhere

			// determine if pagination is in use
			if(is_null($limit)) {
				$limit = count($orderByCols) && $this->get('usePagination') ? $field->get('paginationLimit') : 0;
			}
		}

		if($limit > 0) {
			// paginate the rows that will be loaded
			if(is_null($start)) {
				$pageNum = $this->wire()->input->pageNum() - 1;
				$start = $pageNum * $limit;
			}
			$start = (int) $start;
			$limit = (int) $limit;
			$query->limit("$start,$limit");
			
			// populate data about the pagination so that can be picked by getLoadQuery or wherever else
			$query->data('_start', $start);
			$query->data('_limit', $limit);
		}

		return $query;
	}

	/**
	 * Apply a where condition to a load query (used by getLoadQuery method)
	 * 
	 * @param Field $field
	 * @param DatabaseQuerySelect $query
	 * @param string $col The column name
	 * @param string $operator The comparison operator
	 * @param mixed $value The value to find
	 * @return DatabaseQuery $query
	 * @throws WireException if given invalid or unrecognized arguments
	 * 
	 */
	protected function getLoadQueryWhere(Field $field, DatabaseQuerySelect $query, $col, $operator, $value) {
		$database = $this->wire()->database;
		$table = $query->data('_table');
		if(empty($table)) $table = $field->getTable();
		$table = $database->escapeTable($table);
		// note the Fulltext class can handle non-text values as well (when using non-partial text matching operators)
		$ft = new DatabaseQuerySelectFulltext($query);
		$ft->match($table, $col, $operator, $value);
		return $query;
	}
	
	/**
	 * Prepare rows for save or delete
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param $value
	 * @return WireArray
	 * @throws WireException
	 * 
	 */
	protected function setupPageFieldRows(Page $page, Field $field, $value) {
	
		$blankValue = $this->getBlankValue($page, $field);
	
		if(!$value instanceof Wire) {
			throw new WireException("Value must be derived from Wire, WireData or WireArray");
		}
	
		if(!$blankValue instanceof WireArray) {
			throw new WireException("Blank value for '$field->name' ($this) must be derived from WireArray");
		}
	
		if($value instanceof WireArray) {
	
			$cna = $value->className();
			$cnb = $blankValue->className();
	
			if($cna === $cnb) {
				// both blankValue and value have same class name, so it is the target WireArray already
				return $value;

			} else if(in_array($cna, wireClassParents($blankValue))) {
				// value is a derivative of blankValue, so it's okay to use
				return $value; 
	
			} else {
				// It's a WireArray but we don't know if valid as an item. Will try adding to $blankValue
				// which should throw WireException if it's not valid.
			}
	
		} else {
			// Single item: WireException will be thrown by add() below if item is not valid
		}
	
		$blankValue->add($value);
	
		return $blankValue; 
	}
	
	/**
	 * Save just the given rows (or single row) for fields that have a single unique primary key.
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param WireArray|Wire $value WireArray containing items you want to save or may also be an individual item
	 * @return int Number of rows saved
	 * @throws WireException
	 * 
	 */
	public function ___savePageFieldRows(Page $page, Field $field, $value) {
	
		$info = $this->getDatabaseSchemaVerbose($field);
		$schema = $info['schema'];
		$primaryKeys = $info['primaryKeys'];
		if(count($primaryKeys) !== 1) throw new WireException("savePageFieldRows() can only be used on fieldtypes with 1 primary key");
		
		$value = $this->setupPageFieldRows($page, $field, $value);
		$database = $this->wire()->database;
		$table = $database->escapeTable($info['table']);
		$primaryKey = $database->escapeCol(reset($primaryKeys));
		$hasInserts = false;
		$sort = null;
		$numSaved = 0;
		$locked = false;
		
		// sleep the values for storage
		$sleepValue = $this->sleepValue($page, $field, $value);
	
		if(isset($schema['sort'])) {
			// determine if there are any INSERTs and what the next sort value(s) should be
			// this is because "pages_id,sort" are generally a unique index with FieldtypeMulti
			$maxSort = 0; 
			foreach($sleepValue as $v) {
				if(!is_array($v)) continue;
				$id = isset($v[$primaryKey]) ? $v[$primaryKey] : 0;
				if(!$id) $hasInserts = true;
				if(isset($v['sort']) && $v['sort'] > $maxSort) $maxSort = $v['sort'];
			}
			if($hasInserts) {
				// we will need a locked table for inserts
				if(!$locked) $locked = $this->lockForWriting($field);
				// determine max sort value for new items inserted
				$sort = $this->getMaxColumnValue($page, $field, 'sort', -1);
				if($maxSort > $sort) $sort = $maxSort;
			}
		}
	
		// update or insert each row
		foreach($sleepValue as $item) {

			$keys = array_keys($item);
			$binds = array(':pages_id' => (int) $page->id);
			$sqls = array('pages_id=:pages_id');
			$id = isset($item[$primaryKey]) ? $item[$primaryKey] : 0;
			
			foreach($keys as $key) {
				$key = $database->escapeCol($key);
				if($key === $primaryKey) continue;
				$sqls[] = "`$key`=:$key";
				$binds[":$key"] = $item[$key];
			}
			
			if(!$id && is_int($sort)) {
				$sqls[] = "sort=:sort";
				$binds[":sort"] = ++$sort;
			}
			
			$sql = implode(', ', $sqls);
			
			if($id) {
				$binds[':primaryKey'] = $id;
				$sql = "UPDATE `$table` SET $sql WHERE `$primaryKey`=:primaryKey LIMIT 1";
			} else {
				$sql = "INSERT INTO `$table` SET $sql";
			}
			
			$query = $database->prepare($sql);
			
			foreach($binds as $bindKey => $bindValue) {
				$query->bindValue($bindKey, $bindValue);
			}
		
			try {
				if($query->execute()) $numSaved++;
			} catch(\Exception $e) {
				$this->trackException($e, false);
				if($this->wire()->user->isSuperuser()) {
					$this->error($e->getMessage(), Notice::log);
				} else {
					$this->error($e->getMessage(), Notice::logOnly);
				}
			}
		}
	
		if($locked) $this->unlockForWriting();

		return $numSaved;
	}

	/**
	 * Lock field table for writing
	 * 
	 * @param Field $field
	 * @return bool
	 * 
	 */
	protected function lockForWriting(Field $field) {
		
		$database = $this->wire()->database;
		$table = $database->escapeTable($field->getTable());
		$locked = false;
		$numAttempts = 0;
		$maxAttempts = 100;
		$lastException = null;
		
		do {
			try {
				// attempt lock if possible
				if($database->exec("LOCK TABLES `$table` WRITE") !== false) {
					$this->lockedTable = true;
					$locked = true;
				}
			} catch(\Exception $e) {
				$lastException = $e;
			}
		} while(!$locked && $numAttempts++ < $maxAttempts);
		
		if(!$locked && $lastException) $this->trackException($lastException, false);
		
		return $locked;
	}

	/**
	 * Unlock for writing
	 * 
	 * @return bool
	 * 
	 */
	protected function unlockForWriting() {
		$result = false;
		if($this->lockedTable) try {
			$this->wire()->database->exec("UNLOCK TABLES");
			$this->lockedTable = false;
			$result = true;
		} catch(\Exception $e) {
			$this->trackException($e, false);
		}
		return $result;
	}

	/**
	 * Get max value of column for given Page and Field or boolean false (or specified $noValue) if no rows present
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param string $column
	 * @param int|bool $noValue Return this value if there are no rows to count from (default=false)
	 * @return int|bool|mixed
	 * @throws WireException
	 * @since 3.0.154
	 * 
	 */
	protected function getMaxColumnValue(Page $page, Field $field, $column, $noValue = false) {
		$database = $this->wire()->database; 
		$table = $database->escapeTable($field->getTable());
		$column = $database->escapeCol($column);
		$sql = "SELECT MAX($column) FROM `$table` WHERE pages_id=:pages_id";
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$value = $query->fetchColumn();
		$query->closeCursor();
		if($value === null) return $noValue;
		if(!is_int($value) && ctype_digit(ltrim($value, '-'))) $value = (int) $value;
		return $value;
	}

	/**
	 * Delete specific rows (for tables with single primary key)
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param WireArray|Wire $value WireArray of items or a single item
	 * @return int Number of rows deleted
	 * @throws WireException
	 * 
	 */
	public function ___deletePageFieldRows(Page $page, Field $field, $value) {

		$database = $this->wire()->database;
		$info = $this->getDatabaseSchemaVerbose($field);
		$primaryKeys = $info['primaryKeys'];
		
		if(count($primaryKeys) !== 1) {
			throw new WireException("deletePageFieldRows() can only be used on fields with 1 primary key");
		}
		
		$value = $this->setupPageFieldRows($page, $field, $value);
		$table = $database->escapeTable($info['table']);
		$primaryKey = $database->escapeCol(reset($primaryKeys));
		$ids = array();

		foreach($this->sleepValue($page, $field, $value) as $item) {
			$id = $item[$primaryKey];
			if(empty($id) || !ctype_digit("$id")) continue;
			$ids[$id] = (int) $id;
		}
		
		$sql = "DELETE FROM `$table` WHERE $primaryKey IN(" . implode(',', $ids) . ")";
		$query = $database->prepare($sql);
		
		return $database->execute($query) ? count($ids) : 0;
	}
	
	/**
	 * Return the query used for Autojoining this field (if different from getLoadQuery) or NULL if autojoin not allowed. 
	 *
	 * @param Field $field
	 * @param DatabaseQuerySelect $query
	 * @return DatabaseQuerySelect|NULL
	 *
	 */
	public function getLoadQueryAutojoin(Field $field, DatabaseQuerySelect $query) {
		$database = $this->wire()->database;
		if($this->get('useOrderByCols')) {
			// autojoin is not used if sorting or pagination is active
			$orderByCols = $field->get('orderByCols');
			if(count($orderByCols) > 0) return null;
		}
		$table = $database->escapeTable($field->table);	
		$schemaAll = $this->getDatabaseSchema($field);
		$schema = $this->trimDatabaseSchema($schemaAll); 
		$fieldName = $database->escapeCol($field->name); 
		$separator = self::multiValueSeparator; 
		$orderBy = '';
		if($field->distinctAutojoin) {
			if(isset($schemaAll['sort'])) $orderBy = "ORDER BY $table.sort";
			$table = "DISTINCT $table";
		}
		foreach($schema as $key => $unused) {
			$query->select("GROUP_CONCAT($table.$key $orderBy SEPARATOR '$separator') AS `{$fieldName}__$key`"); // QA
		}		
		return $query; 
	}

	/**
	 * Get the query that matches a Fieldtype table's data with a given value
	 *
	 * Possible template method: If overridden, children should NOT call this parent method. 
	 *
	 * @param PageFinderDatabaseQuerySelect $query
	 * @param string $table The table name to use
	 * @param string $subfield Name of the field (typically 'data', unless selector explicitly specified another)
	 * @param string $operator The comparison operator
	 * @param mixed $value The value to find
	 * @return PageFinderDatabaseQuerySelect|DatabaseQuerySelect $query
	 *
	 */
	public function getMatchQuery($query, $table, $subfield, $operator, $value) {

		self::$getMatchQueryCount++;
		$n = self::$getMatchQueryCount;

		$field = $query->field;
		$database = $this->wire()->database; 
		$table = $database->escapeTable($table);

		if($subfield === 'count' 
			&& (empty($value) || ctype_digit(ltrim("$value", '-'))) 
			&& $database->isOperator($operator, WireDatabasePDO::operatorTypeComparison)) {

			$value = (int) $value;
			$t = $table . "_" . $n;
			$c = $database->escapeTable($this->className()) . "_" . $n;
			$operator = $database->escapeOperator($operator); 

			$query->select("$t.num_$t AS num_$t");
			$query->leftjoin(
				"(" .
				"SELECT $c.pages_id, COUNT($c.pages_id) AS num_$t " .
				"FROM " . $database->escapeTable($field->table) . " AS $c " .
				"GROUP BY $c.pages_id " .
				") $t ON $t.pages_id=pages.id"
			);

			if( (in_array($operator, array('<', '<=', '!=')) && $value) || 
				(in_array($operator, array('>', '>=')) && $value < 0) ||
				(in_array($operator, array('=', '>=')) && !$value)) {
				// allow for possible zero values	
				$bindKey = $query->bindValueGetKey($value);
				$query->where("(num_$t{$operator}$bindKey OR num_$t IS NULL)"); // QA
			} else {
				// non zero values
				$bindKey = $query->bindValueGetKey($value);
				$query->where("num_$t{$operator}$bindKey"); // QA
			}

			// only allow matches using templates with the requested field
			$templates = $field->getTemplates();
			if(count($templates)) {
				$ids = array();
				foreach($templates as $template) {
					/** @var Template $template */
					$ids[] = (int) $template->id;
				}
				$sql = 'pages.templates_id IN(' . implode(',', $ids) . ')'; // QA
			} else {
				$sql = 'pages.templates_id=0';
			}
			$query->where($sql); // QA

		} else {
			$query = parent::getMatchQuery($query, $table, $subfield, $operator, $value);
		}

		return $query;
	}

	/**
	 * Get Inputfields for advanced settings of the Field and Fieldtype
	 *
	 * Inputfields returned from this appear under the "Advanced" tab rather than the "Details" tab,
	 * in the Field editor.
	 *
	 * In most cases, you will want to implement the getConfigInputfields() or getConfigArray() rather than this method.
	 *
	 * NOTE: Inputfields with a name that starts with an underscore, i.e. "_myname" are assumed to be for runtime
	 * use and are NOT stored in the database.
	 *
	 * #pw-group-configuration
	 *
	 * @param Field $field
	 * @return InputfieldWrapper
	 *
	 */
	public function ___getConfigInputfields(Field $field) {
		
		$inputfields = parent::___getConfigInputfields($field);
		
		if($this->get('useOrderByCols')) {

			try {
				$info = $this->getDatabaseSchemaVerbose($field);
				$primaryKeys = $info['primaryKeys'];
				$schema = $info['schema'];
			} catch(\Exception $e) {
				$schema = array();
				$primaryKeys = array();
			}
			
			if(!empty($schema)) {
				$modules = $this->wire()->modules;
			
				/** @var InputfieldFieldset $fieldset */
				$fieldset = $modules->get('InputfieldFieldset');
				$fieldset->attr('name', '_FieldtypeMultiExtras');
				$fieldset->label = $this->_('Sorting and Pagination');
				$fieldset->description = $this->_('These settings apply to both front-end (site) and back-end (editor).');
				$fieldset->collapsed = Inputfield::collapsedYes;
				$fieldset->icon = 'sliders';
				$inputfields->add($fieldset);

				$sorts = array();
				$sortsReverse = array();
				foreach(array_keys($this->trimDatabaseSchema($schema)) as $sort) {
					$sorts[$sort] = $sort;
					$sortsReverse["-$sort"] = "-$sort " . $this->_('(reverse)');
				}
				$sorts = array_merge($sorts, $sortsReverse);

				/** @var InputfieldAsmSelect $f */
				$f = $modules->get('InputfieldAsmSelect');
				$f->attr('name', 'orderByCols');
				$f->label = $this->_('Automatic sorting');
				$f->description = $this->_('Select one or more fields to sort by below. For manual sort, leave this setting blank.');
				foreach($sorts as $sort => $label) $f->addOption($sort, $label);
				$value = $field->get('orderByCols');
				$f->attr('value', $value);
				$f->columnWidth = 50;
				$fieldset->add($f);
				
				// pagination support
				if($this->get('usePagination') && count($primaryKeys) === 1) {
					/** @var InputfieldInteger $f */
					$f = $modules->get('InputfieldInteger');
					$f->attr('name', 'paginationLimit');
					$f->label = $this->_('Pagination limit / items per page');
					$f->description = $this->_('This limits the number of items loaded/edited per pagination. The value “0” indicates no limit (default).');
					$f->description .= ' ' . $this->_('Pagination is recommended if you will be working with hundreds to thousands of items (or more).');
					$f->notes = $this->_('This setting does not take effect unless a selection is made for “Automatic sorting”.');
					$f->attr('value', $field->paginationLimit ? (int) $field->paginationLimit : 0);
					$f->columnWidth = 50;
					$fieldset->add($f);
				}
			}

		}
		
		return $inputfields;
	}

}
