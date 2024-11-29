<?php namespace ProcessWire;

/**
 * ProcessWire WireSaveableItems
 *
 * Wire Data Access Object, provides reusable capability for loading, saving, creating, deleting, 
 * and finding items of descending class-defined types. 
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * @method WireArray load(WireArray $items, $selectors = null)
 * @method bool save(Saveable $item)
 * @method bool delete(Saveable $item)
 * @method WireArray find($selectors)
 * @method void saveReady(Saveable $item) #pw-hooker
 * @method void deleteReady(Saveable $item) #pw-hooker
 * @method void cloneReady(Saveable $item, Saveable $copy) #pw-hooker
 * @method array saved(Saveable $item, array $changes = array()) #pw-hooker
 * @method void added(Saveable $item) #pw-hooker
 * @method void deleted(Saveable $item) #pw-hooker
 * @method void cloned(Saveable $item, Saveable $copy) #pw-hooker
 * @method void renameReady(Saveable $item, $oldName, $newName)
 * @method void renamed(Saveable $item, $oldName, $newName)
 *
 * 
 */

abstract class WireSaveableItems extends Wire implements \IteratorAggregate {

	/**
	 * Return the WireArray that this DAO stores it's items in
	 * 
	 * @return WireArray
	 *
	 */
	abstract public function getAll();

	/**
	 * Return a new blank item 
	 * 
	 * @return Saveable|Wire
	 *
	 */
	abstract public function makeBlankItem();

	/**
	 * Get WireArray container that items are stored in 
	 * 
	 * This is the same as the getAll() method except that it is guaranteed not to load
	 * additional items as part of the call. 
	 * 
	 * #pw-internal
	 * 
	 * @return WireArray
	 * @since 3.0.194
	 * 
	 */
	public function getWireArray() {
		return $this->getAll();
	}

	/**
	 * Make an item and populate with given data
	 * 
	 * @param array $a Associative array of data to populate
	 * @return Saveable|WireData|Wire
	 * @throws WireException
	 * @since 3.0.146
	 * 
	 */
	public function makeItem(array $a = array()) {
		$item = $this->makeBlankItem();
		$this->wire($item);
		foreach($a as $key => $value) {
			$item->$key = $value;
		}
		$item->resetTrackChanges(true);
		return $item;
	}

	/**
	 * Return the name of the table that this DAO stores item records in
	 * 
	 * @return string
	 *
	 */
	abstract public function getTable();
	
	/**
	 * Return the default name of the field that load() should sort by (default is none)
	 *
	 * This is overridden by selectors if applied during the load method
	 * 
	 * @return string
	 *
	 */
	public function getSort() { return ''; }

	/**
	 * Provides additions to the ___load query for when selectors or selector string are provided
	 * 
	 * @param Selectors $selectors
	 * @param DatabaseQuerySelect $query
	 * @throws WireException
	 * @return DatabaseQuerySelect
	 *
	 */
	protected function getLoadQuerySelectors($selectors, DatabaseQuerySelect $query) {

		$database = $this->wire()->database; 

		if($selectors instanceof Selectors) {
			// iterable selectors
		} else if($selectors && is_string($selectors)) {
			// selector string, convert to iterable selectors
			$selectorString = $selectors;
			/** @var Selectors $selectors */
			$selectors = $this->wire(new Selectors()); 
			$selectors->init($selectorString);

		} else {
			// nothing provided, load all assumed
			return $query; 
		}
	
		// Note: ProcessWire core does not appear to ever reach this point as the
		// core does not use selectors to load any of its WireSaveableItems

		$functionFields = array(
			'sort' => '', 
			'limit' => '', 
			'start' => '',
		);
		
		$item = $this->makeBlankItem();
		$fields = array_keys($item->getTableData());

		foreach($selectors as $selector) {

			if(!$database->isOperator($selector->operator)) {
				throw new WireException("Operator '$selector->operator' may not be used in {$this->className}::load()");
			}
			
			if(isset($functionFields[$selector->field])) {
				$functionFields[$selector->field] = $selector->value;
				continue;
			}

			if(!in_array($selector->field, $fields)) {
				throw new WireException("Field '$selector->field' is not valid for {$this->className}::load()");
			}

			$selectorField = $database->escapeTableCol($selector->field); 
			$query->where("$selectorField$selector->operator?", $selector->value); // QA
		}

		$sort = $functionFields['sort'];
		if($sort && in_array($sort, $fields)) {
			$query->orderby($database->escapeCol($sort));
		}
		
		$limit = (int) $functionFields['limit'];
		if($limit) {
			$start = $functionFields['start'];
			$query->limit(($start ? ((int) $start) . ',' : '') . $limit);
		}

		return $query; 

	}

	/**
	 * Get the DatabaseQuerySelect to perform the load operation of items
	 *
	 * @param Selectors|string|null $selectors Selectors or a selector string to find, or NULL to load all. 
	 * @return DatabaseQuerySelect
	 *
	 */
	protected function getLoadQuery($selectors = null) {

		$item = $this->makeBlankItem();
		$fields = array_keys($item->getTableData());
		$database = $this->wire()->database; 
		
		$table = $database->escapeTable($this->getTable());
		
		foreach($fields as $k => $v) {
			$v = $database->escapeCol($v);
			$fields[$k] = "$table.$v"; 
		}

		/** @var DatabaseQuerySelect $query */
		$query = $this->wire(new DatabaseQuerySelect());
		$query->select($fields)->from($table);
		if($sort = $this->getSort()) $query->orderby($sort); 
		$this->getLoadQuerySelectors($selectors, $query); 

		return $query; 

	}

	/**
	 * Load items from the database table and return them in the same type class that getAll() returns
	 
	 * A selector string or Selectors may be provided so that this can be used as a find() by descending classes that don't load all items at once.  
	 *
	 * @param WireArray $items
	 * @param Selectors|string|null $selectors Selectors or a selector string to find, or NULL to load all. 
	 * @return WireArray Returns the same type as specified in the getAll() method.
	 *
	 */
	protected function ___load(WireArray $items, $selectors = null) {

		$useLazy = $this->useLazy();
		$database = $this->wire()->database;
		$sql = $this->getLoadQuery($selectors)->getQuery();

		$query = $database->prepare($sql);
		$query->execute();
		$rows = $query->fetchAll(\PDO::FETCH_ASSOC);
		$n = 0;
		
		$this->loadRowsReady($rows);
		
		foreach($rows as $row) {
			if($useLazy) {
				$this->lazyItems[$n] = $row;
				$this->lazyNameIndex[$row['name']] = $n;
				$this->lazyIdIndex[$row['id']] = $n;
				$n++;
			} else {
				$this->initItem($row, $items);
			}
		}
		
		$query->closeCursor();
		$items->setTrackChanges(true); 
		
		return $items; 
	}

	/**
	 * Called after rows loaded from DB but before populated to this instance
	 * 
	 * @param array $rows
	 * 
	 */
	protected function loadRowsReady(array &$rows) { }

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

		if(!empty($row['data'])) {
			if(is_string($row['data'])) $row['data'] = $this->decodeData($row['data']);
		} else {
			unset($row['data']);
		}
		
		if($items === null) $items = $this->getWireArray();
		
		$item = $this->makeItem($row);
		
		if($item) {
			if($this->useLazy() && $item->id) $this->unsetLazy($item);
			$items->add($item);
		}

		return $item;
	}

	/**
	 * Should the given item key/field be saved in the database?
	 *
	 * Template method used by ___save()
	 * 
	 * @param string $key
	 * @return bool
	 *
	 */
	protected function saveItemKey($key) {
		if($key === 'id') return false;
		return true; 
	}

	/**
	 * Save the provided item to database
	 *
	 * @param Saveable $item The item to save
	 * @return bool Returns true on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Saveable $item) {

		$blank = $this->makeBlankItem();
		
		if(!$item instanceof $blank) {
			$className = $blank->className();
			throw new WireException("WireSaveableItems::save(item) requires item to be of type: $className");
		}

		$database = $this->wire()->database; 
		$table = $database->escapeTable($this->getTable());
		$sql = "`$table` SET ";
		$id = (int) $item->id;
		$this->saveReady($item); 
		$data = $item->getTableData();
		$binds = array();
		$namePrevious = false;
		
		if($id && $item->isChanged('name')) {
			$query = $database->prepare("SELECT name FROM `$table` WHERE id=:id");
			$query->bindValue(':id', $id, \PDO::PARAM_INT);
			$query->execute();
			$oldName = $query->fetchColumn();
			$query->closeCursor();
			if($oldName != $item->name) $namePrevious = $oldName;
			if($namePrevious) $this->renameReady($item, $namePrevious, $item->name);
		}

		foreach($data as $key => $value) {
			if(!$this->saveItemKey($key)) continue; 
			if($key === 'data') $value = is_array($value) ? $this->encodeData($value) : '';
			$key = $database->escapeTableCol($key);
			$bindKey = $database->escapeCol($key);
			$binds[":$bindKey"] = $value; 
			$sql .= "`$key`=:$bindKey, ";
		}

		$sql = rtrim($sql, ", "); 

		if($id) {
			
			$query = $database->prepare("UPDATE $sql WHERE id=:id");
			foreach($binds as $key => $value) {
				$query->bindValue($key, $value); 
			}
			$query->bindValue(":id", $id, \PDO::PARAM_INT);
			$result = $query->execute();
			
		} else {
			
			$query = $database->prepare("INSERT INTO $sql"); 
			foreach($binds as $key => $value) {
				$query->bindValue($key, $value); 
			}
			$result = $query->execute();
			if($result) {
				$item->id = (int) $database->lastInsertId();
				$this->getWireArray()->add($item);
				$this->added($item);
			}
		}

		if($result) {
			if($namePrevious) $this->renamed($item, $namePrevious, $item->name);
			$this->saved($item); 
			$this->resetTrackChanges();
		} else {
			$this->error("Error saving '$item'"); 
		}
		
		return $result;
	}


	/** 
	 * Delete the provided item from the database
	 *
	 * @param Saveable $item Item to save
	 * @return bool Returns true on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___delete(Saveable $item) {
		$blank = $this->makeBlankItem();
		if(!$item instanceof $blank) {
			$typeName = $blank->className();
			throw new WireException("WireSaveableItems::delete(item) requires item to be of type '$typeName'");
		}
		
		$id = (int) $item->id;
		if(!$id) return false; 
		
		$database = $this->wire()->database; 
		
		$this->deleteReady($item);
		$this->getWireArray()->remove($item); 
		$table = $database->escapeTable($this->getTable());
		
		$query = $database->prepare("DELETE FROM `$table` WHERE id=:id LIMIT 1"); 
		$query->bindValue(":id", $id, \PDO::PARAM_INT); 
		$result = $query->execute();
		
		if($result) {
			$this->deleted($item);
			$item->id = 0; 
		} else {
			$this->error("Error deleting '$item'"); 
		}
		
		return $result;	
	}

	/**
	 * Create and return a cloned copy of this item
	 *
	 * If no name is specified and the new item uses a 'name' field, it will contain a number at the end to make it unique
	 *
	 * @param Saveable $item Item to clone
	 * @param string $name Optionally specify new name
	 * @return bool|Saveable $item Returns the new clone on success, or false on failure
	 *
	 */
	public function ___clone(Saveable $item, $name = '') {

		$original = $item;
		$item = clone $item;

		if(array_key_exists('name', $item->getTableData())) {
			// this item uses a 'name' field for identification, so we want to ensure it's unique
			$n = 0;
			if(!strlen($name)) $name = $item->name; 
			// ensure the new name is unique
			while($this->get($name)) $name = rtrim($item->name, '_') . '_' . (++$n); 
			$item->name = $name; 
		}

		// id=0 forces the save() to create a new field
		$item->id = 0;
		$this->cloneReady($original, $item); 
		if($this->save($item)) {
			$this->cloned($original, $item); 
			return $item;
		}
		return false; 
	}

	/**
	 * Find items based on Selectors or selector string
	 *
	 * This is a delegation to the WireArray associated with this DAO.
	 * This method assumes that all items are loaded. Desecending classes that don't load all items should 
	 * override this to the ___load() method instead. 
	 *
	 * @param Selectors|string $selectors 
	 * @return WireArray 
	 *
	 */
	public function ___find($selectors) {
		if($this->useLazy()) $this->loadAllLazyItems();
		return $this->getAll()->find($selectors); 
	}

	#[\ReturnTypeWillChange] 
	public function getIterator() {
		if($this->useLazy()) $this->loadAllLazyItems();
		return $this->getAll();
	}

	/**
	 * Get an item
	 * 
	 * @param string|int $key
	 * @return array|mixed|null|Page|Saveable|Wire|WireData
	 * 
	 */
	public function get($key) {
		$value = $this->getWireArray()->get($key);
		if($value === null && $this->useLazy() && $key !== null) $value = $this->getLazy($key);
		return $value;
	}

	public function __get($name) {
		$value = $this->get($name);
		if($value === null) $value = parent::__get($name);
		return $value; 
	}

	/**
	 * Do we have the given item or item by given key?
	 * 
	 * @param string|int|Saveable|WireData $item
	 * @return bool
	 * 
	 */
	public function has($item) {
		if($this->useLazy() && !empty($this->lazyItems)) $this->get($item); // ensure lazy item present
		return $this->getAll()->has($item);
	}

	/**
	 * Isset
	 * 
	 * @param string|int $key
	 * @return bool
	 * 
	 */
	public function __isset($key) {
		return $this->get($key) !== null;	
	}

	/**
	 * Get all property values for items 
	 * 
	 * This is useful for getting all property values without triggering lazy loaded items to load. 
	 * 
	 * #pw-internal
	 *
	 * @param string $valueType|array Name of property value you want to get, or array of them, i.e. 'id', 'name', etc. (default='id')
	 * @param string $indexType One of 'name', 'id' or blank string for no index (default='')
	 * @param string $matchType Optionally match this property, also requires $matchValue argument (default='')
	 * @param string|int|array $matchValue Match this value for $matchType property, use array for OR values (default=null)
	 * @return array
	 * @since 3.0.194
	 *
	 */
	public function getAllValues($valueType = 'id', $indexType = '', $matchType = '', $matchValue = null) {
		
		$values = array();
		$useValueArray = is_array($valueType);
		$matchArray = is_array($matchValue) ? array_flip($matchValue) : false;
		$items = $this->getWireArray();
		
		if($this->useLazy()) {
			foreach($this->lazyItems as $row) {
				$index = null;
				if($matchValue !== null) {
					if($matchArray) {
						$v = isset($row[$matchType]) ? $row[$matchType] : null;
						if(!$v === null || !isset($matchArray[$v])) continue;
					} else {
						if($row[$matchType] != $matchValue) continue;
					}
				}
				if($indexType) {
					$index = isset($row[$indexType]) ? $row[$indexType] : $row['id'];
				}
				if($useValueArray) {
					/** @var array $valueType */
					$value = array();
					foreach($valueType as $key) {
						$value[$key] = isset($row[$key]) ? $row[$key] : null;
					}
				} else {
					$value = isset($row[$valueType]) ? $row[$valueType] : null;
				}
				if($index !== null) {
					$values[$index] = $value;
				} else {
					$values[] = $value;
				}
			}
		}
		
		foreach($items as $field) {
			/** @var WireData $field */
			$index = null;
			if($matchValue !== null) {
				if($matchArray) {
					$v = $field->get($matchType); 
					if($v === null || !isset($matchArray[$v])) continue;
				} else {
					if($field->get($matchType) != $matchValue) continue;
				}
			}
			if($indexType) {
				$index = $field->get($indexType);
			}
			if($useValueArray) {
				/** @var array $valueType */
				$value = array();
				foreach($valueType as $key) {
					$value[$key] = $field->get($key);
				}
			} else {
				$value = $field->get($valueType);
			}
			if($index !== null) {
				$values[$index] = $value;
			} else {
				$values[] = $value;
			}
		}
		
		return $values;
	}

	/**
	 * Encode the 'data' portion of the table.
	 * 	
	 * This is a front-end to wireEncodeJSON so that it can be overridden if needed.
	 * 
	 * @param array $value
	 * @return string
	 *
	 */
	protected function encodeData(array $value) {
		return wireEncodeJSON($value); 
	}

	/**
	 * Decode the 'data' portion of the table.
	 * 	
	 * This is a front-end to wireDecodeJSON that it can be overridden if needed.
	 * 
	 * @param string $value
	 * @return array
	 *
	 */
	protected function decodeData($value) {
		return wireDecodeJSON($value);
	}

	/**
	 * Enforce no locally-scoped fuel for this class
	 * 
	 * @param bool|null $useFuel
	 * @return bool
	 *
	 */
	public function useFuel($useFuel = null) {
		return false;
	}
	
	/**************************************************************************************
	 * HOOKERS
	 *
	 */

	/**
	 * Hook that runs right before item is to be saved.
	 * 
	 * Unlike before(save), when this runs, it has already been confirmed that the item will indeed be saved.
	 * 
	 * @param Saveable $item
	 * 
	 */
	public function ___saveReady(Saveable $item) { }
	
	/**
	 * Hook that runs right before item is to be deleted.
	 *
	 * Unlike before(delete), when this runs, it has already been confirmed that the item will indeed be deleted.
	 *
	 * @param Saveable $item
	 *
	 */
	public function ___deleteReady(Saveable $item) { }
	
	/**
	 * Hook that runs right before item is to be cloned.
	 *
	 * @param Saveable $item
	 * @param Saveable $copy
	 *
	 */
	public function ___cloneReady(Saveable $item, Saveable $copy) { }
	
	/**
	 * Hook that runs right before item is to be renamed.
	 *
	 * @param Saveable $item
	 * @param string $oldName
	 * @param string $newName
	 *
	 */
	public function ___renameReady(Saveable $item, $oldName, $newName) { }
	
	/**
	 * Hook that runs right after an item has been saved. 
	 *
	 * Unlike after(save), when this runs, it has already been confirmed that the item has been saved (no need to error check).
	 *
	 * @param Saveable $item
	 * @param array $changes
	 *
	 */
	public function ___saved(Saveable $item, array $changes = array()) {
		if(count($changes)) {
			$this->log("Saved '$item->name', Changes: " . implode(', ', $changes)); 
		} else {
			$this->log("Saved", $item);
		}
	}
	
	/**
	 * Hook that runs right after a new item has been added. 
	 *
	 * @param Saveable $item
	 *
	 */
	public function ___added(Saveable $item) {
		$this->log("Added", $item);
	}
	
	/**
	 * Hook that runs right after an item has been deleted. 
	 * 
	 * Unlike after(delete), it has already been confirmed that the item was indeed deleted.
	 *
	 * @param Saveable $item
	 *
	 */
	public function ___deleted(Saveable $item) { 
		$this->log("Deleted", $item);
	}

	/**
	 * Hook that runs right after an item has been cloned. 
	 *
	 * @param Saveable $item
	 * @param Saveable $copy
	 *
	 */
	public function ___cloned(Saveable $item, Saveable $copy) {
		$this->log("Cloned '$item->name' to '$copy->name'", $item); 
	}
	
	/**
	 * Hook that runs right after an item has been renamed.
	 *
	 * @param Saveable $item
	 * @param string $oldName
	 * @param string $newName
	 *
	 */
	public function ___renamed(Saveable $item, $oldName, $newName) {
		$this->log("Renamed $oldName to $newName", $item);
	}

	
	/**************************************************************************************
	 * OTHER
	 *
	 */

	/**
	 * Enables use of $apivar('name') or wire()->apivar('name')
	 * 
	 * @param $key
	 * @return Wire|null
	 * 
	 */
	public function __invoke($key) {
		return $this->get($key); 
	}

	/**
	 * Save to activity log, if enabled in config
	 *
	 * @param $str
	 * @param Saveable|null Item to log
	 * @return WireLog
	 *
	 */
	public function log($str, ?Saveable $item = null) {
		$logs = $this->wire()->config->logs;
		$name = $this->className(array('lowercase' => true)); 
		if($logs && in_array($name, $logs)) {
			if($item && strpos($str, "'$item->name'") === false) $str .= " '$item->name'";
			return parent::___log($str, array('name' => $name));
		}
		return parent::___log(); 
	}

	/**
	 * Record an error
	 *
	 * @param string $text
	 * @param int|bool $flags See Notices::flags
	 * @return Wire|WireSaveableItems
	 *
	 */
	public function error($text, $flags = 0) {
		$this->log($text); 
		return parent::error($text, $flags); 
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
		$info = array(); // parent::__debugInfo();
		$info['loaded'] = array();
		$info['notLoaded'] = array();
		foreach($this->getWireArray() as $item) {
			/** @var WireData|Saveable $item */
			$when = $item->get('_lazy');
			$value = $item->get('name|id');
			$value = $value ? "$value ($when)" : $item;
			$info['loaded'][] = $value;
		}
		foreach($this->lazyItems as $row) {
			$value = null;
			if(isset($row['name'])) $value = $row['name'];
			if(!$value && isset($row['id'])) $value = $row['id'];
			if(!$value) $value = &$row;
			$info['notLoaded'][] = $value;
		}
		return $info;
	}
	
	/**************************************************************************************
	 * LAZY LOADING
	 * 
	 */
	
	/**
	 * Lazy loaded raw item data from database
	 *
	 * @var array
	 *
	 */
	protected $lazyItems = array(); // [ 0 => [ ... ], 1 => [ ... ], etc. ]
	protected $lazyNameIndex = array(); // [ 'name' => 123 ] where 123 is key in $lazyItems
	protected $lazyIdIndex = array(); // [ 3 => 123 ] where 3 is ID and 123 is key in $lazyItems

	/**
	 * @var bool|null
	 *
	 */
	protected $useLazy = null;


	/**
	 * Use lazy loading for this type?
	 *
	 * @return bool
	 * @since 3.0.194
	 *
	 */
	public function useLazy() {
		if($this->useLazy !== null) return $this->useLazy;
		$this->useLazy = $this->wire()->config->useLazyLoading;
		if(is_array($this->useLazy)) $this->useLazy = in_array(strtolower($this->className()), $this->useLazy);
		return $this->useLazy;
	}

	/**
	 * Remove item from lazy loading data/indexes
	 * 
	 * @param Saveable $item
	 * @return bool
	 * 
	 */
	protected function unsetLazy(Saveable $item) {
		if(!isset($this->lazyIdIndex[$item->id])) return false;
		$key = $this->lazyIdIndex[$item->id];
		unset($this->lazyItems[$key], $this->lazyNameIndex[$item->name], $this->lazyIdIndex[$item->id]);
		return true;
	}

	/**
	 * Load all pending lazy-loaded items
	 *
	 * #pw-internal
	 *
	 */
	public function loadAllLazyItems() {

		if(!$this->useLazy()) return;
		if(empty($this->lazyItems)) return;

		$debug = $this->wire()->config->debug;
		$items = $this->getWireArray();
		$sortable = !empty($this->lazyNameIndex);

		foreach(array_keys($this->lazyItems) as $key) {
			if(!isset($this->lazyItems[$key])) continue; // required
			$row = &$this->lazyItems[$key];
			$item = $this->initItem($row, $items);
			if($debug) $item->setQuietly('_lazy', '*');
		}
		
		if($sortable) $items->sort('name'); // a-z

		$this->lazyItems = array();
		$this->lazyNameIndex = array();
		$this->lazyIdIndex = array();
		
		// if you want to identify what triggered a “load all”, uncomment one of below:
		// bd(Debug::backtrace());
		// $this->warning(Debug::backtrace());
	}

	/**
	 * Lazy load items by property value
	 * 
	 * #pw-internal
	 *
	 * @param string $key i.e. fieldgroups_id
	 * @param string|int $value
	 * @todo I don't think we need this method, but leaving it here temporarily for reference
	 * @deprecated
	 *
	 */
	private function loadLazyItemsByValue($key, $value) {

		$debug = $this->wire()->config->debug;
		$items = $this->getWireArray();

		foreach($this->lazyItems as $lazyKey => $lazyItem) {
			if($lazyItem[$key] != $value) continue;
			$item = $this->initItem($lazyItem, $items);
			unset($this->lazyItems[$lazyKey]);
			if($debug) $item->setQuietly('_lazy', '=');
		}
	}

	/**
	 * Get a lazy loaded item, companion to get() method
	 *
	 * #pw-internal
	 *
	 * @param string|int $value
	 * @return Saveable|Wire|WireData|null
	 * @since 3.0.194
	 *
	 */
	protected function getLazy($value) {

		$property = ctype_digit("$value") ? 'id' : 'name';
		$value = $property === 'id' ? (int) $value : "$value";
		$item = null;
		$lazyItem = null;
		$lazyKey = null;

		if(!empty($this->lazyIdIndex)) {
			if($property === 'id') {
				$index = &$this->lazyIdIndex;
			} else {
				$index = &$this->lazyNameIndex;
			}
			if(isset($index[$value])) {
				$lazyKey = $index[$value];
				$lazyItem = $this->lazyItems[$lazyKey];
			}
		} else {
			foreach($this->lazyItems as $key => $row) {
				if(!isset($row[$property]) || $row[$property] != $value) continue;
				$lazyKey = $key;
				$lazyItem = $row;
				break;
			}
		}

		if($lazyItem) {
			$item = $this->initItem($lazyItem);
			$this->getWireArray()->add($item);
			unset($this->lazyItems[$lazyKey]);
			if($this->wire()->config->debug) $item->setQuietly('_lazy', '1');
		}

		if($item === null && $property === 'name' && !ctype_alnum($value)) {
			if(Selectors::stringHasOperator("$value") || strpos("$value", '|')) {
				$this->loadAllLazyItems();
				$item = $this->getWireArray()->get($value);
			}
		}

		return $item;
	}


}
