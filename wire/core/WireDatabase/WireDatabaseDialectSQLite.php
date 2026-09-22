<?php namespace ProcessWire;

/**
 * ProcessWire SQLite database dialect
 *
 * ProcessWire issues MySQL-syntax SQL, which this dialect translates to SQLite (see
 * WireDatabaseSQLiteTranslator). SQLite support is currently experimental.
 *
 * This extends the MySQL dialect so that its introspection methods (getColumns, getIndexes,
 * columnExists, etc.) can be reused through the translator's SHOW emulation.
 * @todo extend WireDatabaseDialect directly with PRAGMA-based introspection
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireDatabaseDialectSQLite extends WireDatabaseDialectMySQL {

	/**
	 * @var WireDatabaseSQLiteTranslator|null
	 *
	 */
	protected $translator = null;

	/**
	 * Minimum required SQLite version
	 *
	 */
	const minVersion = '3.35.0';

	/**
	 * Number of savepoints used by execStatements(), for unique savepoint names
	 *
	 * @var int
	 *
	 */
	protected $savepointNum = 0;

	/**
	 * Get dialect name
	 *
	 * @return string
	 *
	 */
	public function name() {
		return 'sqlite';
	}

	/**
	 * Get the SQL translator
	 *
	 * @return WireDatabaseSQLiteTranslator
	 *
	 */
	public function translator() {
		if($this->translator === null) {
			$database = $this->database;
			$this->translator = new WireDatabaseSQLiteTranslator(
				function() use($database) { return $database->pdo(); },
				pathinfo(self::databaseFile($this->wire()->config), PATHINFO_FILENAME)
			);
		}
		return $this->translator;
	}

	/**
	 * Translate SQL (in MySQL syntax) to one or more SQLite statements
	 *
	 * @param string $sql
	 * @return array
	 *
	 */
	public function translateSql($sql) {
		return $this->translator()->translateStatements($sql);
	}

	/**
	 * This dialect translates SQL from MySQL syntax
	 *
	 * @return bool
	 *
	 */
	public function translatesSql() {
		return true;
	}

	/**
	 * Quote a string MySQL-style (the translator converts it to an SQLite literal)
	 *
	 * @param string $str
	 * @return string
	 *
	 */
	public function quote($str) {
		return WireDatabaseSQLiteTranslator::quote($str);
	}

	/**
	 * Get PDO connection configuration from given $config
	 *
	 * @param Config $config
	 * @param array $options
	 * @return array
	 * @throws WireException If the database file location is not allowed
	 *
	 */
	public static function connectionConfig(Config $config, array $options) {
		$file = self::databaseFile($config);
		self::protectLocation($file, $config);
		return [
			'dsn' => "sqlite:$file",
			'user' => null,
			'pass' => null,
			'options' => $options,
		];
	}

	/**
	 * Get full path to SQLite database file from $config->dbFile
	 *
	 * - Blank: `site/assets/database/site.sqlite`
	 * - Leading slash (or Windows drive letter): absolute path
	 * - Anything else (filename or relative path): relative to `site/assets/database/`,
	 *   which may not contain `..` (use an absolute path for other locations)
	 *
	 * @param Config $config
	 * @return string
	 * @throws WireException If a relative path contains `..`
	 *
	 */
	public static function databaseFile(Config $config) {
		$dir = $config->paths->assets . 'database/';
		$file = str_replace('\\', '/', trim((string) $config->dbFile));
		if($file === '') return $dir . 'site.sqlite';
		if(strpos($file, '/') === 0 || preg_match('!^[A-Za-z]:/!', $file)) return self::normalizePath($file);
		if(in_array('..', explode('/', $file), true)) {
			throw new WireException('$config->dbFile may not contain ".." (use an absolute path for locations outside site/assets/database/)');
		}
		if(strpos($file, './') === 0) $file = substr($file, 2);
		return $dir . $file;
	}

	/**
	 * Normalize an absolute path, resolving "." and ".." segments
	 *
	 * @param string $path
	 * @return string
	 *
	 */
	protected static function normalizePath($path) {
		$prefix = preg_match('!^[A-Za-z]:/!', $path) ? substr($path, 0, 3) : '/';
		$parts = [];
		foreach(explode('/', substr($path, strlen($prefix))) as $part) {
			if($part === '' || $part === '.') continue;
			if($part === '..') {
				array_pop($parts);
			} else {
				$parts[] = $part;
			}
		}
		return $prefix . implode('/', $parts);
	}

	/**
	 * Ensure the database file location is allowed and protected from web access
	 *
	 * A database file inside the web root must be in `site/assets/database/`, which is blocked by the
	 * root .htaccess and gets its own deny-all .htaccess file. Locations outside the web root are
	 * not web accessible, so need no protection.
	 *
	 * @param string $file
	 * @param Config $config
	 * @throws WireException If the file is inside the web root but not in site/assets/database/
	 *
	 */
	protected static function protectLocation($file, Config $config) {
		$dir = dirname($file) . '/';
		$root = $config->paths->root;
		$dbDir = $config->paths->assets . 'database/';
		$realDir = is_dir($dir) ? rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/' : $dir;
		$realRoot = rtrim(str_replace('\\', '/', (string) realpath($root)), '/') . '/';
		$inRoot = strpos($dir, $root) === 0 || strpos($realDir, $realRoot) === 0;
		if(!$inRoot) {
			if(!is_dir($dir)) @mkdir($dir, 0755, true);
			return;
		}
		if(strpos($dir, $dbDir) !== 0) {
			throw new WireException(
				'An SQLite database file inside the web root must be in site/assets/database/ ' .
				'(or use an absolute $config->dbFile path outside the web root)'
			);
		}
		if(!is_dir($dbDir) && !@mkdir($dbDir, 0755, true)) return;
		$htaccess = $dbDir . '.htaccess';
		$contents = is_file($htaccess) ? (string) file_get_contents($htaccess) : '';
		if(stripos($contents, 'Require all denied') !== false && stripos($contents, 'Deny from all') !== false) return;
		@file_put_contents($htaccess, ($contents === '' ? '' : rtrim($contents) . "\n\n") .
			"# Deny all web access to database files (ProcessWire)\n" .
			"<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n" .
			"<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
		);
	}

	/**
	 * Initialize a new PDO connection
	 *
	 * @param \PDO $pdo
	 *
	 */
	public function initConnection(\PDO $pdo) {
		$version = $pdo->query('SELECT sqlite_version()')->fetchColumn();
		if(version_compare($version, self::minVersion, '<')) {
			throw new WireDatabaseException(
				"SQLite $version is not supported, ProcessWire requires SQLite " . self::minVersion . ' or newer'
			);
		}
		// custom statement class (rowCount emulation, MySQL error behaviors), replaces debug mode statement class
		$pdo->setAttribute(
			\PDO::ATTR_STATEMENT_CLASS,
			[__NAMESPACE__ . "\\WireDatabaseSQLiteStatement", [$this->database]]
		);
		$pdo->exec('PRAGMA journal_mode=WAL');
		$pdo->exec('PRAGMA synchronous=NORMAL');
		$pdo->exec('PRAGMA busy_timeout=5000');
		$pdo->exec('PRAGMA foreign_keys=OFF');
		WireDatabaseSQLiteTranslator::registerFunctions($pdo, self::databaseFile($this->wire()->config));
	}

	/**
	 * Prepare translated SQL
	 *
	 * @param \PDO $pdo
	 * @param string $sql
	 * @param array $options
	 * @return \PDOStatement
	 *
	 */
	public function prepareStatement(\PDO $pdo, $sql, array $options = []) {
		// the SQLite statement class is required (i.e. for rowCount), so a requested base class is ignored
		unset($options[\PDO::ATTR_STATEMENT_CLASS]);
		return $pdo->prepare($sql, $options);
	}

	/**
	 * Prepare multiple translated statements, to be executed (atomically) when execute() is called
	 *
	 * @param \PDO $pdo
	 * @param array $statements
	 * @return \PDOStatement
	 *
	 */
	public function prepareStatements(\PDO $pdo, array $statements) {
		/** @var WireDatabaseSQLiteStatement $statement */
		$statement = $pdo->prepare('SELECT 1 WHERE 0');
		$statement->setDeferredStatements($statements);
		return $statement;
	}

	/**
	 * Execute a single translated statement (retrying once if a table is locked by an unfinished SELECT)
	 *
	 * @param \PDO $pdo
	 * @param string $sql
	 * @return int|false
	 * @throws \PDOException
	 *
	 */
	public function execStatement(\PDO $pdo, $sql) {
		try {
			return $pdo->exec($sql);
		} catch(\PDOException $e) {
			if(!WireDatabaseSQLiteStatement::isLockedException($e)) throw $e;
			WireDatabaseSQLiteStatement::closeActive();
			return $pdo->exec($sql);
		}
	}

	/**
	 * Execute multiple translated statements atomically
	 *
	 * Statements run within a savepoint, which is rolled back if any statement fails, so that
	 * multi-statement translations (i.e. table rebuilds) never leave partial changes behind.
	 *
	 * @param \PDO $pdo
	 * @param array $statements
	 * @return int Number of rows affected (sum)
	 * @throws \PDOException
	 *
	 */
	public function execStatements(\PDO $pdo, array $statements) {
		$savepoint = 'pw_statements_' . (++$this->savepointNum);
		for($attempt = 1; $attempt <= 2; $attempt++) {
			$pdo->exec("SAVEPOINT $savepoint");
			$qty = 0;
			try {
				foreach($statements as $sql) {
					$result = $pdo->exec($sql);
					if($result !== false) $qty += $result;
				}
				$pdo->exec("RELEASE $savepoint");
				return $qty;
			} catch(\PDOException $e) {
				try {
					$pdo->exec("ROLLBACK TO $savepoint");
					$pdo->exec("RELEASE $savepoint");
				} catch(\PDOException $e2) {
					// connection-level failure: original exception is more useful
				}
				if($attempt > 1 || !WireDatabaseSQLiteStatement::isLockedException($e)) throw $e;
				// table in use by an unfinished SELECT: close open cursors and retry once
				WireDatabaseSQLiteStatement::closeActive();
			}
		}
		return 0;
	}

	/**
	 * Handle an exception from a translated query(), exec() or prepare()
	 *
	 * @param \PDOException $e
	 * @param string $method
	 * @param string $sql
	 * @param string $translated
	 * @param \PDO $pdo
	 * @return \PDOException|\PDOStatement
	 *
	 */
	public function queryException(\PDOException $e, $method, $sql, $translated, \PDO $pdo) {
		$this->logQueryError($sql, $translated, $e);
		$e = self::mysqlException($e);
		if($method === 'prepare' && $e instanceof WireDatabaseSQLiteException) {
			// MySQL reports unknown tables/columns at execute() rather than prepare()
			$statement = $pdo->prepare('SELECT 1');
			if($statement instanceof WireDatabaseSQLiteStatement) {
				$statement->setDeferredException($e);
				return $statement;
			}
		}
		return $e;
	}

	/**
	 * Log a failed query to site/assets/logs/sqlite-errors.txt (debug mode only)
	 *
	 * #pw-internal
	 *
	 * @param string $sql Original SQL
	 * @param string $translated Translated SQL
	 * @param \Exception $e
	 *
	 */
	public function logQueryError($sql, $translated, $e) {
		$config = $this->wire()->config;
		if(!$config || !$config->debug) return;
		$root = $config->paths->root;
		$trace = [];
		foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $t) {
			if(empty($t['file']) || strpos($t['file'], 'WireDatabase') !== false) continue;
			$trace[] = str_replace($root, '', $t['file']) . ':' . $t['line'];
			if(count($trace) >= 8) break;
		}
		$entry = date('Y-m-d H:i:s') . "\n" .
			"ERROR: " . $e->getMessage() . "\n" .
			"ORIGINAL: " . trim($sql) . "\n" .
			($translated !== '' && $translated !== $sql ? "TRANSLATED: " . trim($translated) . "\n" : '') .
			"TRACE: " . implode(' < ', $trace) . "\n" .
			str_repeat('-', 60) . "\n";
		@file_put_contents($config->paths->logs . 'sqlite-errors.txt', $entry, FILE_APPEND);
	}

	/**
	 * @return bool
	 *
	 */
	public function supportsFoundRows() {
		return false;
	}

	/**
	 * @return bool
	 *
	 */
	public function supportsFulltext() {
		return false;
	}

	/**
	 * @return bool
	 *
	 */
	public function supportsUpdateOrderBy() {
		return false;
	}

	/**
	 * Convert SQLite "no such table/column" exceptions to their MySQL equivalents
	 *
	 * ProcessWire checks for SQLSTATE 42S02 (table not found) and 42S22 (column not found).
	 *
	 * @param \PDOException $e
	 * @return \PDOException
	 *
	 */
	public static function mysqlException(\PDOException $e) {
		$message = $e->getMessage();
		if(preg_match('/no such column: ([^\s]+)/', $message, $m)) {
			$state = '42S22';
			$info = [$state, 1054, "Unknown column '$m[1]' in 'field list'"];
		} else if(preg_match('/no such table: ([^\s]+)/', $message, $m)) {
			$state = '42S02';
			$info = [$state, 1146, "Table '$m[1]' doesn't exist"];
		} else {
			return $e;
		}
		$ex = new WireDatabaseSQLiteException("SQLSTATE[$state]: $info[2] ($message)", 0, $e);
		$ex->setMySQLError($state, $info);
		return $ex;
	}

	/**
	 * Are transactions available?
	 *
	 * @param string $table
	 * @return bool
	 *
	 */
	public function supportsTransaction($table = '') {
		return true;
	}

	/**
	 * Rename columns
	 *
	 * @param string $table
	 * @param array $columns
	 * @return int
	 *
	 */
	public function renameColumns($table, array $columns) {
		$qty = 0;
		$table = $this->database->escapeTable($table);
		foreach($columns as $oldName => $newName) {
			$oldName = $this->database->escapeCol($oldName);
			$newName = $this->database->escapeCol($newName);
			if(empty($oldName) || empty($newName)) continue;
			if($this->database->exec("ALTER TABLE `$table` RENAME COLUMN `$oldName` TO `$newName`") !== false) $qty++;
		}
		return $qty;
	}

	/**
	 * Get MySQL-equivalent variable value
	 *
	 * @param string $name
	 * @param bool $cache
	 * @param bool $sub
	 * @return string|null
	 *
	 */
	public function getVariable($name, $cache = true, $sub = true) {
		switch($name) {
			case 'version': return $this->database->pdo()->query('SELECT sqlite_version()')->fetchColumn();
			case 'version_comment': return 'SQLite';
			case 'ft_min_word_len':
			case 'innodb_ft_min_token_size': return '1';
			case 'ft_max_word_len':
			case 'innodb_ft_max_token_size': return '84';
		}
		return null;
	}

	/**
	 * Get SQLite version
	 *
	 * @param bool $getNumberOnly
	 * @return string
	 *
	 */
	public function getVersion($getNumberOnly = false) {
		return $this->getVariable('version');
	}

	/**
	 * @return string
	 *
	 */
	public function getServerType() {
		return 'SQLite';
	}

	/**
	 * REGEXP is implemented with PCRE, which is closest to ICU
	 *
	 * @return string
	 *
	 */
	public function getRegexEngine() {
		return 'ICU';
	}

	/**
	 * SQLite has no index length limit
	 *
	 * @return int
	 *
	 */
	public function getMaxIndexLength() {
		return 250;
	}

	/**
	 * SQL modes do not apply to SQLite
	 *
	 * @param string $action
	 * @param string $mode
	 * @param string $minVersion
	 * @param \PDO|null $pdo
	 * @return bool|string
	 *
	 */
	public function sqlMode($action = 'get', $mode = '', $minVersion = '', $pdo = null) {
		return $action === 'get' ? '' : true;
	}

	/**
	 * @param bool $getTimestamp
	 * @return int|string
	 *
	 */
	public function getTime($getTimestamp = false) {
		return $getTimestamp ? time() : date('Y-m-d H:i:s');
	}

	/**
	 * @param \PDOException $e
	 * @return string
	 *
	 */
	public function getRetryableErrorType(\PDOException $e) {
		$errno = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
		if($errno === 5 || $errno === 6) return 'deadlock'; // SQLITE_BUSY, SQLITE_LOCKED
		return '';
	}
}
