<?php namespace ProcessWire;

/**
 * ProcessWire SQLite database dialect
 *
 * ProcessWire issues MySQL-syntax SQL, which this dialect translates to SQLite (see
 * WireDatabaseSQLiteTranslator). SQLite support is currently experimental.
 *
 * Introspection (getTables, getColumns, getIndexes, etc.) queries SQLite's PRAGMA functions
 * directly rather than going through the translator's SHOW emulation, which remains available
 * for third party code that issues SHOW or DESCRIBE queries of its own.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireDatabaseDialectSQLite extends WireDatabaseDialect {

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
	 * Does PDO start transactions with BEGIN IMMEDIATE itself? (PHP 8.5+ Pdo\Sqlite::ATTR_TRANSACTION_MODE)
	 *
	 * @var bool
	 *
	 */
	protected $pdoImmediate = false;

	/**
	 * Is a transaction active that this dialect started? (when PDO cannot use BEGIN IMMEDIATE itself)
	 *
	 * @var bool
	 *
	 */
	protected $inTransaction = false;

	/**
	 * Does this SQLite build have JSON support? (null until checked)
	 *
	 * @var bool|null
	 *
	 */
	protected $supportsJson = null;

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
		unset($options['sqlite']); // ProcessWire settings rather than PDO driver options
		return [
			'dsn' => "sqlite:$file",
			'user' => null,
			'pass' => null,
			'options' => $options,
		];
	}

	/**
	 * Get a setting from $config->dbOptions['sqlite']
	 *
	 * @param string $name
	 * @param mixed $default Value to return if setting not present
	 * @return mixed
	 *
	 */
	public function setting($name, $default = null) {
		$options = $this->wire()->config->dbOptions;
		if(!is_array($options) || !isset($options['sqlite'])) return $default;
		$options = $options['sqlite'];
		if(!is_array($options) || !array_key_exists($name, $options)) return $default;
		return $options[$name];
	}

	/**
	 * Supports MySQL's JSON functions?
	 *
	 * SQLite provides most of them under the same names (and the translator registers the rest),
	 * but its JSON support was only compiled in by default as of SQLite 3.38, so this checks.
	 *
	 * @return bool
	 *
	 */
	public function supportsJson() {
		if($this->supportsJson === null) {
			try {
				$this->database->pdo()->query("SELECT json_valid('{}')")->fetchColumn();
				$this->supportsJson = true;
			} catch(\Exception $e) {
				$this->supportsJson = false;
			}
		}
		return $this->supportsJson;
	}

	/**
	 * Get collation for text comparisons
	 *
	 * SQLite text columns use the built-in NOCASE collation, which ignores case for the letters
	 * A-Z only. Values containing other characters get the pw_ci collation instead, so that (for
	 * example) `title=apfel` matches "Äpfel" as it would on MySQL. ASCII-only values keep NOCASE,
	 * since that lets SQLite use an index.
	 *
	 * @param string|int|float|null $value
	 * @return string
	 *
	 */
	public function compareCollation($value = null) {
		if($value === null || is_int($value) || is_float($value)) return '';
		return preg_match('/[\x80-\xFF]/', (string) $value) ? 'pw_ci' : '';
	}

	/**
	 * Get collation for text ORDER BY terms
	 *
	 * Off unless enabled with `$config->dbOptions['sqlite']['unicodeSort'] = true;` because
	 * SQLite has to call back into PHP for every comparison it makes while sorting, and cannot
	 * use an index to avoid the sort.
	 *
	 * @return string
	 *
	 */
	public function sortCollation() {
		return $this->setting('unicodeSort', false) ? 'pw_ci' : '';
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
	 * Get the PDO class to use for connections
	 *
	 * PHP 8.4+ provides Pdo\Sqlite, whose createFunction() replaces PDO::sqliteCreateFunction()
	 * (deprecated in PHP 8.5).
	 *
	 * @return string
	 *
	 */
	public function pdoClass() {
		// note: no autoload for PHP's own class (PW's autoloader may query the database while connecting)
		return class_exists('\\Pdo\\Sqlite', false) ? '\\Pdo\\Sqlite' : '\\PDO';
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
		// transactions start with BEGIN IMMEDIATE (see beginTransaction())
		$this->inTransaction = false;
		$this->pdoImmediate = false;
		if(class_exists('\\Pdo\\Sqlite', false) && $pdo instanceof \Pdo\Sqlite && defined('\\Pdo\\Sqlite::ATTR_TRANSACTION_MODE')) {
			$this->pdoImmediate = $pdo->setAttribute(
				constant('\\Pdo\\Sqlite::ATTR_TRANSACTION_MODE'),
				constant('\\Pdo\\Sqlite::TRANSACTION_MODE_IMMEDIATE')
			);
		}
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
	 * Begin a transaction with BEGIN IMMEDIATE
	 *
	 * PDO's default BEGIN (deferred) starts a read transaction that is upgraded on the first write.
	 * If another connection writes in between, the upgrade fails right away with "database is locked"
	 * (the busy timeout cannot help), which would happen to any transaction that reads before it writes.
	 * BEGIN IMMEDIATE takes the write lock up front, so concurrent writers wait for each other instead.
	 *
	 * PHP 8.5+ does this through Pdo\Sqlite::ATTR_TRANSACTION_MODE. For earlier versions this dialect
	 * starts and ends the transaction itself, since PDO does not recognize transactions it did not start.
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function beginTransaction(\PDO $pdo) {
		if($this->pdoImmediate) return $pdo->beginTransaction();
		if($this->inTransaction) throw new \PDOException('There is already an active transaction');
		$pdo->exec('BEGIN IMMEDIATE');
		$this->inTransaction = true;
		return true;
	}

	/**
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function inTransaction(\PDO $pdo) {
		if($this->pdoImmediate) return (bool) $pdo->inTransaction();
		return $this->inTransaction;
	}

	/**
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function commit(\PDO $pdo) {
		if($this->pdoImmediate) return $pdo->commit();
		return $this->endTransaction($pdo, 'COMMIT');
	}

	/**
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function rollBack(\PDO $pdo) {
		if($this->pdoImmediate) return $pdo->rollBack();
		return $this->endTransaction($pdo, 'ROLLBACK');
	}

	/**
	 * End a transaction started by beginTransaction() (when PDO cannot use BEGIN IMMEDIATE itself)
	 *
	 * @param \PDO $pdo
	 * @param string $sql COMMIT or ROLLBACK
	 * @return bool
	 * @throws \PDOException
	 *
	 */
	protected function endTransaction(\PDO $pdo, $sql) {
		if(!$this->inTransaction) throw new \PDOException('There is no active transaction');
		try {
			$pdo->exec($sql);
		} catch(\PDOException $e) {
			// SQLite may already have rolled back the transaction (i.e. after certain errors)
			if(stripos($e->getMessage(), 'no transaction is active') === false) throw $e;
		}
		$this->inTransaction = false;
		return true;
	}

	/**
	 * Run a query() and register the statement so its cursor can be closed if a table is locked
	 *
	 * @param \PDO $pdo
	 * @param string $sql
	 * @return \PDOStatement|false
	 * @throws \PDOException
	 *
	 */
	public function queryStatement(\PDO $pdo, $sql) {
		try {
			$query = $pdo->query($sql);
		} catch(\PDOException $e) {
			if(!WireDatabaseSQLiteStatement::isLockedException($e)) throw $e;
			WireDatabaseSQLiteStatement::closeActive();
			$query = $pdo->query($sql);
		}
		if($query instanceof WireDatabaseSQLiteStatement) $query->setActive();
		return $query;
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
	 * Get the clause of an upsert() statement that updates the existing row
	 *
	 * SQLite uses `ON CONFLICT ... DO UPDATE SET`, with the inserted values available as
	 * `excluded.column`. The conflict target is optional in SQLite 3.35+ but is included when
	 * known, since it is what the translator cannot supply on its own.
	 *
	 * @param array $updates
	 * @param array $conflict
	 * @return string
	 *
	 */
	protected function upsertUpdateClause(array $updates, array $conflict) {
		$sets = array();
		foreach($updates as $name => $expr) {
			$col = $this->quoteIdentifier($name);
			$sets[] = $col . '=' . ($expr === null ? "excluded.$col" : $expr);
		}
		$target = '';
		if(count($conflict)) {
			$names = array();
			foreach($conflict as $name) $names[] = $this->quoteIdentifier($name);
			$target = '(' . implode(', ', $names) . ') ';
		}
		return "ON CONFLICT {$target}DO UPDATE SET " . implode(', ', $sets);
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

	/*********************************************************************************
	 * INTROSPECTION
	 *
	 * These use SQLite's PRAGMA functions directly (no translation), but return the same
	 * MySQL-shaped values that the MySQL dialect returns from SHOW and DESCRIBE.
	 *
	 */

	/**
	 * Execute a query on the SQLite connection, bypassing translation
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array Rows indexed by column name
	 *
	 */
	protected function pragma($sql, array $params = array()) {
		$query = $this->database->pdo()->prepare($sql);
		$query->execute($params);
		$rows = $query->fetchAll(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		return $rows;
	}

	/**
	 * Get columns of given table as SHOW COLUMNS rows
	 *
	 * @param string $table
	 * @return array
	 *
	 */
	protected function tableInfo($table) {

		$rows = array();
		$createSql = null;

		foreach($this->pragma('SELECT * FROM pragma_table_info(?)', array($table)) as $row) {

			$pk = (int) $row['pk'];
			$type = (string) $row['type'];
			$extra = '';

			if($pk > 0 && strtolower($type) === 'integer') {
				// AUTOINCREMENT is only visible in the table's own CREATE TABLE statement
				if($createSql === null) {
					$createSql = (string) $this->database->pdo()->query(
						"SELECT sql FROM sqlite_master WHERE type='table' AND name=" .
						$this->database->pdo()->quote($table)
					)->fetchColumn();
				}
				if(stripos($createSql, 'AUTOINCREMENT') !== false) $extra = 'auto_increment';
			}

			$rows[] = array(
				'Field' => $row['name'],
				'Type' => $type,
				'Null' => ((int) $row['notnull']) ? 'NO' : 'YES',
				'Key' => $pk > 0 ? 'PRI' : '',
				'Default' => $row['dflt_value'],
				'Extra' => $extra,
			);
		}

		return $rows;
	}

	/**
	 * Get indexes of given table as SHOW INDEX rows (one row per indexed column)
	 *
	 * @param string $table
	 * @return array
	 *
	 */
	protected function indexInfo($table) {

		$rows = array();
		$prefix = $table . WireDatabaseSQLiteTranslator::indexSeparator;
		$hasPrimary = false;

		foreach($this->pragma('SELECT * FROM pragma_index_list(?)', array($table)) as $index) {

			$name = (string) $index['name'];
			$origin = (string) $index['origin'];

			if($origin === 'pk') {
				$keyName = 'PRIMARY';
				$hasPrimary = true;
			} else if(strpos($name, $prefix) === 0) {
				// indexes created through ProcessWire are prefixed with the table name
				$keyName = substr($name, strlen($prefix));
			} else {
				$keyName = $name;
			}

			$seq = 0;

			foreach($this->pragma('SELECT * FROM pragma_index_info(?)', array($name)) as $col) {
				if($col['name'] === null) continue; // indexed expression rather than a column
				$rows[] = array(
					'Table' => $table,
					'Key_name' => $keyName,
					'Non_unique' => ((int) $index['unique']) ? 0 : 1,
					'Seq_in_index' => ++$seq,
					'Column_name' => $col['name'],
					'Index_type' => 'BTREE',
				);
			}
		}

		if(!$hasPrimary) {
			// an INTEGER PRIMARY KEY is the table's rowid, so it has no index to list
			$seq = 0;
			foreach($this->tableInfo($table) as $col) {
				if($col['Key'] !== 'PRI') continue;
				$rows[] = array(
					'Table' => $table,
					'Key_name' => 'PRIMARY',
					'Non_unique' => 0,
					'Seq_in_index' => ++$seq,
					'Column_name' => $col['Field'],
					'Index_type' => 'BTREE',
				);
			}
		}

		usort($rows, function($a, $b) {
			$result = strcmp($a['Key_name'], $b['Key_name']);
			return $result === 0 ? $a['Seq_in_index'] - $b['Seq_in_index'] : $result;
		});

		return $rows;
	}

	/**
	 * Get all table names
	 *
	 * @return array
	 *
	 */
	public function getTables() {
		$sql =
			"SELECT name FROM sqlite_master " .
			"WHERE type='table' AND name NOT LIKE 'sqlite\_%' ESCAPE '\' " .
			"ORDER BY name";
		$query = $this->database->pdo()->query($sql);
		$tables = $query->fetchAll(\PDO::FETCH_COLUMN);
		$query->closeCursor();
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

		if($verbose === 3) {
			// MySQL-syntax column definitions, as SHOW CREATE TABLE would give
			$sql = WireDatabaseSQLiteTranslator::mysqlCreateTable($this->database->pdo(), $table);
			if(!$sql) return array();
			if(!preg_match_all('/`([_a-z0-9]+)`\s+([a-z][^\r\n]+)/i', $sql, $matches)) return array();
			foreach($matches[1] as $key => $name) {
				$columns[$name] = trim(rtrim($matches[2][$key], ','));
			}
			return $columns;
		}

		$getColumn = $verbose && is_string($verbose) ? $verbose : '';
		if(strpos($table, '.')) list($table, $getColumn) = explode('.', $table, 2);

		foreach($this->tableInfo($table) as $col) {
			$name = $col['Field'];
			if($getColumn !== '' && $name !== $getColumn) continue;
			if($verbose === 2) {
				$columns[$name] = $col;
			} else if($verbose) {
				$columns[$name] = array(
					'name' => $name,
					'type' => $col['Type'],
					'null' => $col['Null'] === 'YES',
					'default' => $col['Default'],
					'extra' => $col['Extra'],
				);
			} else {
				$columns[] = $name;
			}
		}

		if($getColumn !== '') return isset($columns[$getColumn]) ? $columns[$getColumn] : array();

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
		if(strpos($table, '.')) list($table, $getIndex) = explode('.', $table, 2);

		foreach($this->indexInfo($table) as $row) {
			$name = $row['Key_name'];
			if($getIndex !== '' && strcasecmp($name, $getIndex) !== 0) continue;
			if($verbose === 2) {
				$indexes[] = $row;
			} else if($verbose) {
				if(!isset($indexes[$name])) $indexes[$name] = array(
					'name' => $name,
					'type' => $row['Index_type'],
					'unique' => (((int) $row['Non_unique']) ? false : true),
					'columns' => array(),
				);
				$indexes[$name]['columns'][((int) $row['Seq_in_index']) - 1] = $row['Column_name'];
			} else {
				$indexes[] = $name;
			}
		}

		if($getIndex !== '') return isset($indexes[$getIndex]) ? $indexes[$getIndex] : array();

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
		$rows = $this->pragma("SELECT name FROM sqlite_master WHERE type='table' AND name=?", array($table));
		return count($rows) > 0;
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

		foreach($this->tableInfo($table) as $row) {
			if($row['Field'] !== $column) continue;
			return $getInfo ? $row : true;
		}

		return false;
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
		$rows = array();
		foreach($this->indexInfo($table) as $row) {
			if(strcasecmp($row['Key_name'], $indexName) === 0) $rows[] = $row;
		}
		return $getInfo && count($rows) ? $rows : count($rows) > 0;
	}
}
