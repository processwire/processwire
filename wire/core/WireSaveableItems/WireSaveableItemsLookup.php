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
		$item = $this->makeBlankItem(); /** @var HasLookupItems $item */
		
		if($items === null) $items = $this->getWireArray();

		// The LEFT JOIN returns one row per lookup item, i.e. one row per field in a fieldgroup.
		// When lazy loading, claim all of this item's rows from lazyItems now, before anything
		// below can trigger a load of this same item. Adding a lookup item can do that: resolving
		// a field may load its Fieldtype module for the first time, and a module's init() or 
		// upgrade() may request templates or fieldgroups. Such a load must not find this item's
		// rows half consumed, or it builds a second copy of the item with some of them missing.
		$rows = array($row);
		if($this->useLazy) {
			foreach($this->lazyItems as $key => $a) {
				if($a['id'] != $row['id']) continue;
				unset($this->lazyItems[$key]);
				if($a != $row) $rows[] = $a; // the given row may itself be in lazyItems
			}
		}

		foreach($row as $key => $value) {
			if($key === $lookupField) continue;
			$item->$key = $value;
		}

		if($items->has($item)) {
			// item is already present, i.e. when not lazy loading, each row of the LEFT JOIN arrives
			// separately, so add this row's lookup item to the existing item and discard the new one
			$item = $items->get($item);
		} else {
			// add the item before adding its lookup items, so that a load of the same item triggered
			// while adding them finds this one rather than creating another
			$items->add($item);
		}

		foreach($rows as $a) {
			$lookupValue = isset($a[$lookupField]) ? $a[$lookupField] : null;
			unset($a[$lookupField]);
			$item->addLookupItem($lookupValue, $a);
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

	/**
	 * Not supported for lookup-table classes — the JOIN structure makes single-row retrieval incomplete
	 *
	 * @param int|string $key
	 * @return null
	 *
	 */
	public function getRaw($key) {
		return null;
	}

	/**
	 * Not supported for lookup-table classes — the JOIN structure makes single-row retrieval incomplete
	 *
	 * @param int|string $key
	 * @return null
	 *
	 */
	public function getFresh($key) {
		return null;
	}

}
