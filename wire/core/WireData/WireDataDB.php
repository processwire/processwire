<?php namespace ProcessWire;

/**
 * WireData with database storage
 * 
 * A WireData object that maintains its data in a database table rather than just in memory.
 * An example of usage is the `$page->meta()` method.
 * 
 * ProcessWire 3.x, Copyright 2023
 * https://processwire.com
 *
 */
class WireDataDB extends WireData implements \Countable {

	/**
	 * True when all data from the table has been loaded (a call to getArray will trigger this)
	 * 
	 * @var bool
	 * 
	 */
	protected $fullyLoaded = false;

	/**
	 * ID of the source object for this WireData
	 * 
	 * @var int
	 * 
	 */
	protected $sourceID = 0;

	/**
	 * Name of the table that data will be stored in
	 * 
	 * @var string
	 * 
	 */
	protected $table = '';
	
	/**
	 * Construct
	 *
	 * @param int $sourceID ID of the source item this WireData is maintaining/persisting data for. 
	 * @param string $tableName Name of the table to store data in. If it does not exist, it will be created.
	 *
	 */
	public function __construct($sourceID, $tableName) {
		$this->table($tableName);
		$this->sourceID($sourceID);
		parent::__construct();
	}

	/**
	 * Get the value for a specific property/name/key
	 * 
	 * @param string $key
	 * @return array|mixed|null
	 * @throws WireException
	 * 
	 */
	public function get($key) {
		$value = parent::get($key);
		if($value !== null) return $value;
		$value = $this->load($key);
		parent::set($key, $value);
		return $value;
	}
	
	/**
	 * Get and/or set cached value 
	 * 
	 * Sets value from callable function and caches it so that later calls do not 
	 * call the callable function again until it expires.
	 * 
	 * ~~~~~
	 * // returns same random number for 1 hour (3600 seconds)
	 * echo $page->meta->getCache('my_rand_num', 3600, function() {
	 *   return mt_rand();
	 * }); 
	 * ~~~~~
	 * 
	 * @param string $key Name of property to get or set
	 * @param int $maxAge Maximum age in seconds
	 * @param callable $func Function that returns the value when creating (when new or after expiration)
	 * @return array|mixed|null
	 * @since 3.0.258
	 * 
	 */
	public function getCache(string $key, int $maxAge, callable $func) {
		
		$getValue = $this->get($key);
	
		if(is_array($getValue)) {
			$v = $this->decodeCacheValue($getValue, $maxAge); 
			if($v === null) {
				// expired
				$setValue = $func();
				$getValue = $setValue;
			} else {
				$getValue = $v;
				$setValue = null;
			}
		} else {
			$setValue = $func();
			$getValue = $setValue;
		}
		
		if($setValue !== null) {
			$setValue = $this->encodeCacheValue($setValue, $maxAge);
			$this->set($key, $setValue);
		}
		
		return $getValue;
	}
	
	/**
	 * Get all values in an associative array 
	 * 
	 * @return array|mixed|null
	 * @throws WireException
	 * 
	 */
	public function getArray() {
		return $this->load(true);
	}

	/**
	 * Set and save a value for a specific property/name/key
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return self
	 * @throws WireException
	 * 
	 */
	public function set($key, $value) {
		if(parent::get($key) === $value) return $this; // no change
		if($value === null) return $this->remove($key);  // remove
		$this->save($key, $value); // set
		parent::set($key, $value);
		return $this;
	}

	/**
	 * Remove value for a specific property/name/key 
	 * 
	 * @param string $key
	 * @return self
	 * @throws WireException
	 * 
	 */
	public function remove($key) {
		$this->delete("$key");
		parent::remove($key);
		return $this;
	}

	/**
	 * Remove all values for sourceID from the DB
	 * 
	 * @return $this
	 * 
	 */
	public function removeAll() {
		$this->delete(true);
		$this->reset();
		return $this;
	}

	/**
	 * Reset all loaded data so that it will re-load from DB on next access
	 * 
	 * @return $this
	 * 
	 */
	public function reset() {
		$this->data = array();
		$this->fullyLoaded = false;
		return $this;
	}

	/**
	 * Delete meta value or all meta values (if you specify true)
	 * 
	 * @param string|bool $name Meta property name to delete or specify boolean true for all
	 * @return int Number of rows deleted
	 * @throws WireException
	 * 
	 */
	protected function delete($name) {
		if(empty($name)) return 0;
		$table = $this->table();
		$sql = "DELETE FROM `$table` WHERE source_id=:source_id ";
		if($name !== true) $sql .= "AND name=:name";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':source_id', $this->sourceID(), \PDO::PARAM_INT);
		if($name !== true) $query->bindValue(':name', $name);
		try {
			$query->execute();
			$result = $query->rowCount();
			$query->closeCursor();
		} catch(\Exception $e) {
			$result = 0;
		}
		return $result;
	}

	/**
	 * Load a value or all values
	 * 
	 * @param string|bool $name Property name to load or boolean true to load all
	 * @return array|mixed|null
	 * @throws WireException
	 * 
	 */
	protected function load($name) {
		if(empty($name)) return null;
		$loadAll = $name === true; 
		if($this->fullyLoaded) {
			return ($loadAll ? parent::getArray() : parent::get($name));
		}
		$table = $this->table();
		$sql = "SELECT name, data FROM `$table` WHERE source_id=:source_id ";
		if(!$loadAll) $sql .= "AND name=:name ";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':source_id', $this->sourceID(), \PDO::PARAM_INT);
		if(!$loadAll) $query->bindValue(':name', $name);
		try {
			$query->execute();
		} catch(\Exception $e) {
			return $loadAll ? array() : null;
		}
		if($query->rowCount()) {
			$meta = array();
			while($row = $query->fetch(\PDO::FETCH_NUM)) {
				list($key, $data) = $row;
				$meta[$key] = json_decode($data, true);
				parent::set($key, $meta[$key]);
				if(!$loadAll) {
					// make meta just the requested property value
					$meta = $meta[$name];
					break;
				}
			}
		} else {
			// no rows found
			$meta = $loadAll ? [] : null;
		}
		if($loadAll) $this->fullyLoaded = true;
		$query->closeCursor();
		return $meta;
	}

	/**
	 * Save a value 
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @param bool $recursive
	 * @return bool
	 * @throws WireException
	 * 
	 */
	protected function save($name, $value, $recursive = false) {
		if(is_object($value)) return false; // we do not currently save objects
		$data = json_encode($value);
		$table = $this->table();
		$sourceID = $this->sourceID();
		if(!$sourceID) return false;
		$sql =
			"INSERT INTO `$table` (source_id, name, data) VALUES(:source_id, :name, :data) " .
			"ON DUPLICATE KEY UPDATE source_id=VALUES(source_id), name=VALUES(name), data=VALUES(data)";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':source_id', $this->sourceID(), \PDO::PARAM_INT);
		$query->bindValue(':name', $name);
		$query->bindValue(':data', $data);
		try {
			$query->execute();
			$result = $query->rowCount();
		} catch(\Exception $e) {
			if($recursive) throw $e;
			// table might not yet exist, try to create and save() again
			$result = $this->install() ? $this->save($name, $value, true) : false;
		}
		return $result ? true : false;
	}
	
	/**
	 * Get or set the the source ID for this instance
	 * 
	 * @param int|null $id
	 * @return int
	 * @throws WireException
	 * 
	 */
	public function sourceID($id = null) {
		if(!is_int($id)) return $this->sourceID;
		// commented out because could interfere with some page clone operations:
		// if($id < 1) throw new WireException($this->className() . ' sourceID must be greater than 0');
		$this->sourceID = $id;
		return $this->sourceID;
	}

	/**
	 * Count the number of rows this WireDataDB maintains in the database for source ID. 
	 * 
	 * This implements the \Countable interface. 
	 * 
	 * @return int
	 * 
	 */
	#[\ReturnTypeWillChange] 
	public function count() {
		$table = $this->table();
		$sql = "SELECT COUNT(*) FROM `$table` WHERE source_id=:source_id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':source_id', $this->sourceID(), \PDO::PARAM_INT); 
		try {
			$query->execute();
			$count = (int) $query->fetchColumn();
		} catch(\Exception $e) {
			$count = 0;
		}
		return $count; 
	}

	/**
	 * Copy all data to a new source ID
	 * 
	 * Useful to call on the source object after a clone has been created from it. 
	 * 
	 * @param int $newSourceID
	 * @throws WireException
	 * @return int Number of rows copied
	 * 
	 */
	public function copyTo($newSourceID) {
		if(!$this->count()) return 0;
		$sourceID = $this->sourceID;
		if($newSourceID == $sourceID) return 0;
		$data = $this->getArray();
		$this->sourceID($newSourceID); // temporarily set new
		foreach($data as $key => $value) {
			$this->save($key, $value);
		}
		$this->sourceID($sourceID); // set back
		return count($data);
	}
	
	/**
	 * Encode a cache value for saving
	 *
	 * @param array|string $value
	 * @param int $maxAge
	 * @return array
	 * @since 3.0.258
	 *
	 */
	protected function encodeCacheValue($value, $maxAge) {
		return [
			'_cre' => time(), // created
			'_exp' => time() + $maxAge, // expires
			'_val' => $value, // value 
		];
	}
	
	/**
	 * Decode/extract cache value, returning null if it is expired
	 *
	 * @param array $a Array in format: [ '_cre' => int, '_exp' => int, '_val' => mixed ];
	 * @param int|null $maxAge
	 * @return mixed|null
	 * @since 3.0.258
	 *
	 */
	protected function decodeCacheValue(array $a, $maxAge = null) {
		if(!isset($a['_cre']) || !isset($a['_exp']) || !array_key_exists('_val', $a)) return null;
		list($created, $expires, $val) = [ $a['_cre'], $a['_exp'], $a['_val'] ];
		if($maxAge === null && $expires < time()) return null; // expired
		if(is_int($maxAge) && $created + $maxAge < time()) return null; // expired
		return $val;
	}
	
	
	/**
	 * Get the current table name
	 * 
	 * @param string $tableName
	 * @return string
	 * 
	 */
	public function table($tableName = '') {
		if($tableName === '') return $this->table;
		if(!ctype_alnum(str_replace('_', '', $tableName))) {
			$tableName = preg_replace('/[^_a-zA-Z0-9]/', '_', $tableName);
		}
		$this->table = strtolower($tableName);
		return $this->table;
	}

	/**
	 * Get DB schema in an array
	 * 
	 * @return array
	 * 
	 */
	protected function schema() {
		return array(
			"source_id INT UNSIGNED NOT NULL",
			"name VARCHAR(128) NOT NULL",
			"data MEDIUMTEXT NOT NULL",
			"PRIMARY KEY (source_id, name)",
			"INDEX name (name)",
			"FULLTEXT KEY data (data)"
		);
	}

	/**
	 * Install the table
	 * 
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function install() {
		$config = $this->wire()->config;
		$database = $this->wire()->database;
		$engine = $config->dbEngine;
		$charset = $config->dbCharset;
		$table = $this->table();
		if($database->tableExists($table)) return false;
		$schema = implode(', ', $this->schema());
		$sql = "CREATE TABLE `$table` ($schema) ENGINE=$engine DEFAULT CHARSET=$charset";
		$database->exec($sql);
		$this->message("Added '$table' table to database");
		return true;
	}

	/**
	 * Uninstall the table
	 * 
	 * @return bool
	 * @throws WireException
	 * 
	 */
	public function uninstall() {
		$table = $this->table();
		$this->wire()->database->exec("DROP TABLE `$table`"); 
		return true;
	}

	#[\ReturnTypeWillChange] 
	public function getIterator() {
		return new \ArrayObject($this->getArray());
	}

}
