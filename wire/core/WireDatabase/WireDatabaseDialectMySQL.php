<?php namespace ProcessWire;

/**
 * ProcessWire MySQL/MariaDB database dialect
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireDatabaseDialectMySQL extends WireDatabaseDialect {

	/**
	 * Substitute variable names according to engine as used by getVariable() method
	 *
	 * @var array
	 *
	 */
	protected $subVars = array(
		'myisam' => array(),
		'innodb' => array(
			'ft_min_word_len' => 'innodb_ft_min_token_size',
			'ft_max_word_len' => 'innodb_ft_max_token_size',
		),
	);

	/**
	 * Cached values from getVariable method
	 *
	 * @var array associative of name => value
	 *
	 */
	protected $variableCache = array();

	/**
	 * Get dialect name
	 *
	 * @return string
	 *
	 */
	public function name() {
		return 'mysql';
	}

	/**
	 * Are transactions available with current DB engine (or table)?
	 *
	 * @param string $table
	 * @return bool
	 *
	 */
	public function supportsTransaction($table = '') {
		$engine = '';
		if($table) {
			$query = $this->database->prepare('SHOW TABLE STATUS WHERE name=:name');
			$query->bindValue(':name', $table);
			$query->execute();
			if($query->rowCount()) {
				$row = $query->fetch(\PDO::FETCH_ASSOC);
				$engine = empty($row['Engine']) ? (empty($row['engine']) ? '' : $row['engine']) : $row['Engine'];
			}
			$query->closeCursor();
		} else {
			$engine = $this->database->getEngine();
		}
		return strtoupper($engine) === 'INNODB';
	}

	/**
	 * Get array of all tables in this database
	 *
	 * @return array
	 *
	 */
	public function getTables() {
		$tables = array();
		$query = $this->database->query("SHOW TABLES");
		/** @noinspection PhpAssignmentInConditionInspection */
		while($col = $query->fetchColumn()) $tables[] = $col;
		return $tables;
	}

	/**
	 * Get all columns from given table
	 *
	 * @param string $table
	 * @param bool|int|string $verbose
	 * @return array
	 *
	 */
	public function getColumns($table, $verbose = false) {
		$columns = array();
		$table = $this->database->escapeTable($table);
		if($verbose === 3) {
			$query = $this->database->query("SHOW CREATE TABLE $table");
			if(!$query->rowCount()) return array();
			$row = $query->fetch(\PDO::FETCH_NUM);
			$query->closeCursor();
			if(!preg_match_all('/`([_a-z0-9]+)`\s+([a-z][^\r\n]+)/i', $row[1], $matches)) return array();
			foreach($matches[1] as $key => $name) {
				$columns[$name] = trim(rtrim($matches[2][$key], ','));
			}
			return $columns;
		}
		$getColumn = $verbose && is_string($verbose) ? $verbose : '';
		if(strpos($table, '.')) list($table, $getColumn) = explode('.', $table, 2);
		$sql = "SHOW COLUMNS FROM $table " . ($getColumn ? 'WHERE Field=:column' : '');
		$query = $this->database->prepare($sql);
		if($getColumn) $query->bindValue(':column', $getColumn);
		$query->execute();
		while($col = $query->fetch(\PDO::FETCH_ASSOC)) {
			$name = $col['Field'];
			if($verbose === 2) {
				$columns[$name] = $col;
			} else if($verbose) {
				$columns[$name] = array(
					'name' => $name,
					'type' => $col['Type'],
					'null' => (strtoupper($col['Null']) === 'YES' ? true : false),
					'default' => $col['Default'],
					'extra' => $col['Extra'],
				);
			} else {
				$columns[] = $name;
			}
		}
		$query->closeCursor();
		if($getColumn) return isset($columns[$getColumn]) ? $columns[$getColumn] : array();
		return $columns;
	}

	/**
	 * Get all indexes from given table
	 *
	 * @param string $table
	 * @param bool|int|string $verbose
	 * @return array
	 *
	 */
	public function getIndexes($table, $verbose = false) {
		$indexes = array();
		$getIndex = $verbose && is_string($verbose) ? $verbose : '';
		if($verbose === 'primary') $verbose = 'PRIMARY';
		if(strpos($table, '.')) list($table, $getIndex) = explode('.', $table, 2);
		$table = $this->database->escapeTable($table);
		$sql = "SHOW INDEX FROM `$table` " . ($getIndex ? 'WHERE Key_name=:name' : '');
		$query = $this->database->prepare($sql);
		if($getIndex) $query->bindValue(':name', $getIndex);
		$query->execute();
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$name = $row['Key_name'];
			if($verbose === 2) {
				$indexes[] = $row;
			} else if($verbose) {
				if(!isset($indexes[$name])) $indexes[$name] = array(
					'name' => $name,
					'type' => $row['Index_type'],
					'unique' => (((int) $row['Non_unique']) ? false : true),
					'columns' => array(),
				);
				$seq = ((int) $row['Seq_in_index']) - 1;
				$indexes[$name]['columns'][$seq] = $row['Column_name'];
			} else {
				$indexes[] = $name;
			}
		}
		$query->closeCursor();
		if($getIndex) return isset($indexes[$getIndex]) ? $indexes[$getIndex] : array();
		return $indexes;
	}

	/**
	 * Does the given table exist?
	 *
	 * @param string $table
	 * @return bool
	 *
	 */
	public function tableExists($table) {
		$query = $this->database->prepare('SHOW TABLES LIKE ?');
		$query->execute(array($table));
		$result = $query->fetchColumn();
		return !empty($result);
	}

	/**
	 * Does the given column exist in given table?
	 *
	 * @param string $table
	 * @param string $column
	 * @param bool $getInfo
	 * @return bool|array
	 * @throws WireDatabaseException
	 *
	 */
	public function columnExists($table, $column = '', $getInfo = false) {
		if(strpos($table, '.')) {
			list($table, $col) = explode('.', $table, 2);
			if(empty($column) || !is_string($column)) $column = $col;
		}
		if(empty($column)) throw new WireDatabaseException('No column specified');
		$exists = false;
		$table = $this->database->escapeTable($table);
		try {
			$query = $this->database->prepare("SHOW COLUMNS FROM `$table` WHERE Field=:column");
			$query->bindValue(':column', $column, \PDO::PARAM_STR);
			$query->execute();
			$numRows = (int) $query->rowCount();
			if($numRows) $exists = $getInfo ? $query->fetch(\PDO::FETCH_ASSOC) : true;
			$query->closeCursor();
		} catch(\Exception $e) {
			// most likely given table does not exist
			$exists = false;
		}
		return $exists;
	}

	/**
	 * Does table have an index with given name?
	 *
	 * @param string $table
	 * @param string $indexName
	 * @param bool $getInfo
	 * @return bool|array
	 *
	 */
	public function indexExists($table, $indexName, $getInfo = false) {
		$table = $this->database->escapeTable($table);
		$query = $this->database->prepare("SHOW INDEX FROM `$table` WHERE Key_name=:name");
		$query->bindValue(':name', $indexName, \PDO::PARAM_STR);
		try {
			$query->execute();
			$numRows = (int) $query->rowCount();
			if($numRows && $getInfo) {
				$exists = array();
				while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
					$exists[] = $row;
				}
			} else {
				$exists = $numRows > 0;
			}
			$query->closeCursor();
		} catch(\Exception $e) {
			// most likely given table does not exist
			$exists = false;
		}
		return $exists;
	}

	/**
	 * Rename table columns without changing type
	 *
	 * @param string $table
	 * @param array $columns
	 * @return int
	 *
	 */
	public function renameColumns($table, array $columns) {
		$qty = 0;

		if(version_compare($this->database->getVersion(true), '8.0.0', '>=')) {
			$mysql8 = $this->database->getServerType() === 'MySQL';
		} else {
			$mysql8 = false;
		}

		$table = $this->database->escapeTable($table);
		$colTypes = $mysql8 ? array() : $this->database->getColumns($table, 3);

		foreach($columns as $oldName => $newName) {
			$oldName = $this->database->escapeCol($oldName);
			$newName = $this->database->escapeCol($newName);
			if(empty($oldName) || empty($newName)) continue;
			if($mysql8) {
				$sql = "ALTER TABLE `$table` RENAME COLUMN `$oldName` TO `$newName`";
			} else if(isset($colTypes[$oldName])) {
				$colType = $colTypes[$oldName];
				$sql = "ALTER TABLE `$table` CHANGE `$oldName` `$newName` $colType";
			} else {
				continue;
			}
			if($this->database->exec($sql) !== false) $qty++;
		}

		return $qty;
	}

	/**
	 * Get the value of a MySQL variable
	 *
	 * @param string $name
	 * @param bool $cache
	 * @param bool $sub
	 * @return string|null
	 *
	 */
	public function getVariable($name, $cache = true, $sub = true) {
		$engine = $this->database->getEngine();
		if($sub && isset($this->subVars[$engine][$name])) $name = $this->subVars[$engine][$name];
		if($cache && isset($this->variableCache[$name])) return $this->variableCache[$name];
		$query = $this->database->prepare('SHOW VARIABLES WHERE Variable_name=:name');
		$query->bindValue(':name', $name);
		$query->execute();
		if($query->rowCount()) {
			list(,$value) = $query->fetch(\PDO::FETCH_NUM);
			$this->variableCache[$name] = $value;
		} else {
			$value = null;
		}
		$query->closeCursor();
		return $value;
	}

	/**
	 * Get MySQL/MariaDB version
	 *
	 * @param bool $getNumberOnly
	 * @return string
	 *
	 */
	public function getVersion($getNumberOnly = false) {
		$version = $this->getVariable('version', true, false);
		if($getNumberOnly && preg_match('/^([\d.]+)/', $version, $matches)) $version = $matches[1];
		return $version;
	}

	/**
	 * Get server type, one of MySQL, MariaDB, Percona, etc.
	 *
	 * @return string
	 *
	 */
	public function getServerType() {
		$serverType = '';
		$serverTypes = array('MariaDB', 'Percona', 'OurDelta', 'Drizzle', 'MySQL');
		foreach(array('version', 'version_comment') as $name) {
			$value = $this->getVariable($name);
			if($value === null) continue;
			foreach($serverTypes as $type) {
				if(stripos($value, $type) !== false) $serverType = $type;
				if($serverType) break;
			}
			if($serverType) break;
		}
		return $serverType ? $serverType : 'MySQL';
	}

	/**
	 * Get the regular expression engine used by database
	 *
	 * @return string
	 *
	 */
	public function getRegexEngine() {
		$version = $this->getVersion();
		$name = 'MySQL';
		if(strpos($version, '-')) list($version, $name) = explode('-', $version, 2);
		if(strpos($name, 'mariadb') === false) {
			if(version_compare($version, '8.0.4', '>=')) return 'ICU';
		}
		return 'HenrySpencer';
	}

	/**
	 * Get max length allowed for a fully indexed varchar column in ProcessWire
	 *
	 * @return int
	 *
	 */
	public function getMaxIndexLength() {
		$max = 250;
		if($this->database->getCharset() === 'utf8mb4') {
			if($this->database->getEngine() === 'innodb') {
				$max = 191;
			}
		}
		return $max;
	}

	/**
	 * Get SQL mode, set SQL mode, add to existing SQL mode, or remove from existing SQL mode
	 *
	 * @param string $action
	 * @param string $mode
	 * @param string $minVersion
	 * @param \PDO|null $pdo
	 * @return string|bool
	 * @throws WireException
	 *
	 */
	public function sqlMode($action = 'get', $mode = '', $minVersion = '', $pdo = null) {

		$result = true;
		$modes = array();

		if($pdo === null) $pdo = $this->database->pdo();
		if(empty($action)) $action = 'get';

		if($action !== 'get' && $minVersion) {
			$serverVersion = $this->database->getAttribute(\PDO::ATTR_SERVER_VERSION);
			if(version_compare($serverVersion, $minVersion, '<')) return false;
		}

		if($mode) {
			foreach(explode(',', $mode) as $m) {
				$modes[] = $this->database->escapeStr(strtoupper($this->wire()->sanitizer->fieldName($m)));
			}
		}

		switch($action) {
			case 'get':
				$query = $pdo->query("SELECT @@sql_mode");
				$result = $query->fetchColumn();
				$query->closeCursor();
				break;
			case 'set':
				$modes = implode(',', $modes);
				$result = $modes;
				$pdo->exec("SET sql_mode='$modes'");
				break;
			case 'add':
				foreach($modes as $m) {
					$pdo->exec("SET sql_mode=(SELECT CONCAT(@@sql_mode,',$m'))");
				}
				break;
			case 'remove':
				foreach($modes as $m) {
					$pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'$m',''))");
				}
				break;
			default:
				throw new WireException("Unknown action '$action'");
		}

		return $result;
	}

	/**
	 * Get current date/time ISO-8601 string or UNIX timestamp according to database
	 *
	 * @param bool $getTimestamp
	 * @return string|int
	 *
	 */
	public function getTime($getTimestamp = false) {
		$query = $this->database->query('SELECT ' . ($getTimestamp ? 'UNIX_TIMESTAMP()' : 'NOW()'));
		$value = $query->fetchColumn();
		return $getTimestamp ? (int) $value : $value;
	}
}
