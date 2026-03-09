<?php namespace ProcessWire;

/**
 * Database cache handler for WireCache
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 2.0.218
 *
 */
class WireCacheDatabase extends Wire implements WireCacheInterface {
	
	/**
	 * Find caches by names or expirations and return requested values
	 * 
	 * @param array $options
	 *  - `names` (array): Names of caches to find (OR condition), optionally appended with wildcard `*`. 
	 *  - `expires` (array): Expirations of caches to match in ISO-8601 date format, prefixed with operator and space (see expiresMode mode below). 
	 *  - `expiresMode` (string): Whether it should match any one condition 'OR', or all conditions 'AND' (default='OR')
	 *  - `get` (array): Properties to get in return value, one or more of [ `name`, `expires`, `data`, `size` ] (default=all)
	 * @return array Returns array of associative arrays, each containing requested properties 
	 * 
	 */
	public function find(array $options) {

		$defaults = array(
			'names' => array(),
			'expires' => array(),
			'expiresMode' => 'OR',
			'get' => array('name', 'expires', 'data'),
		);
		
		$database = $this->wire()->database;
		$options = array_merge($defaults, $options);
		$where = array();
		$whereNames = array();
		$whereExpires = array();
		$binds = array();
		$cols = array();

		if(count($options['names'])) {
			$n = 0;
			foreach($options['names'] as $name) {
				$n++;
				if(strpos($name, '*') !== false) {
					$name = str_replace('*', '%', $name);
					$whereNames[] = "name LIKE :name$n";
				} else {
					$whereNames[] = "name=:name$n";
				}
				$binds[":name$n"] = $name;
			}
		}

		if(count($options['expires'])) {
			$n = 0;
			foreach($options['expires'] as $expires) {
				$operator = '=';
				if(strpos($expires, ' ')) {
					// string in format: '>= YYYY-MM-DD HH:MM:SS'
					list($op, $expires) = explode(' ', $expires, 2);
					if($database->isOperator($op)) $operator = $op;
				}
				$n++;
				$whereExpires[] = "expires$operator:expires$n";
				$binds[":expires$n"] = $expires;
			}
		}

		if(count($whereNames)) {
			$where[] = '(' . implode(' OR ', $whereNames) . ')';
		}
		
		if(count($whereExpires)) {
			$mode = strtoupper($options['expiresMode']) === 'AND' ? 'AND' : 'OR';
			$where[] = '(' . implode(" $mode ", $whereExpires) . ')';
		}

		foreach($options['get'] as $col) {
			if($col === 'name' || $col === 'expires' || $col === 'data') $cols[] = $col;
			if($col === 'size') $cols[] = 'LENGTH(data) AS size';
		}
		
		if(empty($cols)) return array();

		$sql = 'SELECT ' . implode(',', $cols) . ' FROM caches ';
		if(count($where)) {
			$sql .= 'WHERE ' . implode(' AND ', $where);
		} else {
			// getting all 
			$sql .= 'ORDER BY name';
		}

		$query = $database->prepare($sql);
		
		foreach($binds as $bindKey => $bindValue) {
			$query->bindValue($bindKey, $bindValue);
		}

		if(!$this->executeQuery($query)) return array();
		
		$rows = $query->fetchAll(\PDO::FETCH_ASSOC);
		$query->closeCursor();

		return $rows;
	}

	/**
	 * Save a cache
	 * 
	 * @param string $name Name of cache
	 * @param string $data Data to save in cache
	 * @param string $expire String in ISO-8601 date format
	 * @return bool
	 * 
	 */
	public function save($name, $data, $expire) {
	
		$sql =
			'INSERT INTO caches (`name`, `data`, `expires`) VALUES(:name, :data, :expires) ' .
			'ON DUPLICATE KEY UPDATE `data`=VALUES(`data`), `expires`=VALUES(`expires`)';

		$query = $this->wire()->database->prepare($sql, "cache.save($name)");
		$query->bindValue(':name', $name);
		$query->bindValue(':data', $data);
		$query->bindValue(':expires', $expire);

		$result = $this->executeQuery($query);

		return $result;
	}

	/**
	 * Delete a cache by name
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function delete($name) {
		$sql = 'DELETE FROM caches WHERE name=:name';
		$query = $this->wire()->database->prepare($sql, "cache.delete($name)");
		$query->bindValue(':name', $name);
		if(!$this->executeQuery($query)) return false;
		$query->closeCursor();
		return true;
	}

	/**
	 * Delete all caches (except those reserved by the system)
	 * 
	 * @return int
	 * 
	 */
	public function deleteAll() {
		return $this->_deleteAll();
	}

	/**
	 * Expire all caches (except those that should never expire)
	 * 
	 * @return int
	 * 
	 */
	public function expireAll() {
		return $this->_deleteAll(true);
	}

	/**
	 * Implementation for deleteAll and expireAll methods
	 * 
	 * @param bool $expireAll
	 * @return int
	 * @throws WireException
	 * 
	 */
	protected function _deleteAll($expireAll = false) {
		$sql = 'DELETE FROM caches WHERE ' . ($expireAll ? 'expires>:expires' : 'expires!=:expires');
		$query = $this->wire()->database->prepare($sql, "cache.deleteAll()");
		$query->bindValue(':expires', ($expireAll ? WireCache::expireNever : WireCache::expireReserved));
		if(!$this->executeQuery($query)) return 0;
		$qty = $query->rowCount();
		$query->closeCursor();
		return $qty;
	}

	/**
	 * Execute query
	 * 
	 * @param \PDOStatement $query
	 * @return bool
	 * 
	 */
	protected function executeQuery(\PDOStatement $query) {
		$install = false;
		try {
			$result = $query->execute();
		} catch(\PDOException $e) {
			$result = false;
			$install = $e->getCode() === '42S02'; // table does not exist
			if(!$install) throw $e;
		}
		if($install) $this->install();
		return $result;
	}

	/**
	 * Database cache maintenance (every 10 minutes)
	 * 
	 * @param Template|Page $obj
	 * @return bool
	 * @throws WireException
	 * @since 3.0.242
	 * 
	 */
	public function maintenance($obj) {
		
		if($obj) return false; // let WireCache handle when object value is provided
		
		$sql = 
			'DELETE FROM caches ' . 
			'WHERE (expires<=:now AND expires>:never) ' . 
			'OR expires<:then';
		
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':now', date(WireCache::dateFormat, time())); 
		$query->bindValue(':never', WireCache::expireNever);
		$query->bindValue(':then', '1974-10-10 10:10:10');
		$query->execute();
		$qty = $query->rowCount();

		if($qty) $this->wire->cache->log(
			sprintf('DB cache maintenance expired %d cache(s)', $qty)
		);

		return $qty > 0;
	}

	/**
	 * Create the caches table if it happens to have been deleted
	 * 
	 */
	protected function install() {
		$database = $this->wire()->database;
		$config = $this->wire()->config;
		$dbEngine = $config->dbEngine;
		$dbCharset = $config->dbCharset;
		if($database->tableExists('caches')) return;
		try {
			$this->wire()->database->exec("
				CREATE TABLE caches (
					`name` VARCHAR(191) NOT NULL PRIMARY KEY,
					`data` MEDIUMTEXT NOT NULL, 
					`expires` DATETIME NOT NULL, 
					INDEX `expires` (`expires`)
				) ENGINE=$dbEngine DEFAULT CHARSET=$dbCharset;
			");
			$this->message("Re-created 'caches' table");
		} catch(\Exception $e) {
			$this->error("Unable to create 'caches' table");
		}
	}
}
