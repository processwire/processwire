<?php namespace ProcessWire;

/**
 * ProcessWire WireSaveableItemsLookup
 *
 * Provides same functionality as WireSaveableItems except that this class includes joining/modification of a related lookup table
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 */

abstract class WireSaveableItemsLookup extends WireSaveableItems {

	/**
	 * If a lookup table should be left joined, this method should return the table name
	 *
	 */
	abstract public function getLookupTable();

	/**
	 * Cache of value returned from getLookupField() method
	 * 
	 * @var string|null
	 * 
	 */
	protected $lookupField = null;

	/**
	 * If a lookup table should be left joined, this method returns the name of the array field in $data that contains multiple values
	 * 
	 * i.e. roles_permissions becomes permissions_id if getTable() returns roles
	 * Does not need to be overridden unless the table naming structure doesn't follow existing logic.
	 *
	 */
	public function getLookupField() { 
		if($this->lookupField) return $this->lookupField;
		$lookupTable = $this->getLookupTable();
		if(!$lookupTable) return ''; 
		$this->lookupField = preg_replace('/_?' . $this->getTable() . '_?/', '', $lookupTable) . '_id';
		return $this->lookupField;
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
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->getTable());
		$lookupTable = $database->escapeTable($this->getLookupTable());	
		$lookupField = $database->escapeCol($this->getLookupField()); 
		$query->select("$lookupTable.$lookupField"); // QA 
		$query->leftjoin("$lookupTable ON $lookupTable.{$table}_id=$table.id ")->orderby("sort");
		// $query->leftjoin("$lookupTable ON $lookupTable.{$table}_id=$table.id ")->orderby("$table.id, $lookupTable.sort");
		return $query; 
	}

	/**
	 * Load items from the database table and return them in the same type class that getAll() returns
	 *
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
		$query = $this->getLoadQuery($selectors);
		$sql = $query->getQuery();
		
		$this->getLookupField(); // preload
		
		$stmt = $database->prepare($sql);
		$stmt->execute();
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		
		// note: non-use of lazyNameIndex/lazyIdIndex is intentional
	
		foreach($rows as $row) {
			if($useLazy) {
				$this->lazyItems[] = $row;
			} else {
				/** @var HasLookupItems $item */
				$this->initItem($row, $items);
			}
		}

		$stmt->closeCursor();
		$items->setTrackChanges(true);
		
		return $items; 
	}

	/**
	 * Create a new Saveable/Lookup item from a raw array ($row) and add it to $items
	 *
	 * @param array $row
	 * @param WireArray|null $items
	 * @return Saveable|HasLookupItems|WireData|Wire
	 * @since 3.0.194
	 *
	 */
	protected function initItem(array &$row, ?WireArray $items = null) {
		
		$lookupField = $this->getLookupField();
		$lookupValue = $row[$lookupField];
		$item = $this->makeBlankItem(); /** @var HasLookupItems $item */
		
		if($items === null) $items = $this->getWireArray();
		
		unset($row[$lookupField]);
		
		$item->addLookupItem($lookupValue, $row);

		foreach($row as $key => $value) {
			$item->$key = $value;
		}
		
		if($this->useLazy) {
			$items->add($item);
			foreach($this->lazyItems as $key => $a) {
				if($a['id'] != $row['id']) continue;
				if(!isset($a[$lookupField])) continue;
				$lookupValue = $a[$lookupField];
				unset($a[$lookupField]); 
				$item->addLookupItem($lookupValue, $a);
				unset($this->lazyItems[$key]);
			}

		} else if($items->has($item)) {
			// LEFT JOIN is adding more elements of the same item, i.e. from lookup table
			// if the item is already present in $items, then use the existing one rather 
			// and throw out the one we just created
			$item = $items->get($item);
			$item->addLookupItem($lookupValue, $row);
		} else {
			// add a new item
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
		if($key == $this->getLookupField()) return false; 
		return parent::saveItemKey($key); 
	}

	/**
	 * Save the provided item to database
	 * 
	 * @param Saveable $item
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function ___save(Saveable $item) {

		if(!$item instanceof HasLookupItems) {
			$class = $this->className();
			throw new WireException("$class::save() requires an item that implements HasLookupItems interface");
		}
	
		$database = $this->wire()->database; 	
		$lookupTable = $database->escapeTable($this->getLookupTable());
		$lookupField = $database->escapeCol($this->getLookupField());
		$table = $database->escapeTable($this->getTable());
		$item_id = (int) $item->id; 

		if($item_id) {
			$query = $database->prepare("DELETE FROM $lookupTable WHERE {$table}_id=:item_id");
			$query->bindValue(":item_id", $item_id, \PDO::PARAM_INT);
			$query->execute();
		}
			
		$result = parent::___save($item); 
		$item_id = (int) $item->id; // reload, in case it was 0 before

		$sort = 0; 
		if($item_id) {
			$sql = "INSERT INTO $lookupTable SET {$table}_id=:item_id, $lookupField=:value_id, sort=:sort";
			$query = $database->prepare($sql);
			foreach($item->getLookupItems() as $value) {
				$value_id = (int) $value->id;
				$query->bindValue(":item_id", $item_id, \PDO::PARAM_INT);
				$query->bindValue(":value_id", $value_id, \PDO::PARAM_INT);
				$query->bindValue(":sort", $sort, \PDO::PARAM_INT);
				$query->execute();
				$sort++;
			}
			$this->resetTrackChanges();
		}

		return $result;	
	}

	/** 
	 * Delete the provided item from the database
	 *
	 * @param Saveable $item
	 * @return bool
	 * 
	 */
	public function ___delete(Saveable $item) {
		$database = $this->wire()->database;
		$lookupTable = $database->escapeTable($this->getLookupTable()); 
		$table = $database->escapeTable($this->getTable()); 
		$item_id = (int) $item->id; 
		$query = $database->prepare("DELETE FROM $lookupTable WHERE {$table}_id=:item_id"); // QA
		$query->bindValue(":item_id", $item_id, \PDO::PARAM_INT);
		$query->execute();
		return parent::___delete($item); 
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
		$info = parent::__debugInfo();
		$info['loaded'] = array_unique($info['loaded']);
		$info['notLoaded'] = array_unique($info['notLoaded']);
		return $info;
	}

}
