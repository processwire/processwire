<?php namespace ProcessWire;

/**
 * ProcessWire PostgreSQL database dialect
 *
 * ProcessWire issues MySQL-syntax SQL, which this dialect translates to PostgreSQL (see
 * WireDatabasePgsqlTranslator). PostgreSQL support is currently experimental.
 *
 * Introspection (getTables, getColumns, getIndexes, etc.) queries information_schema and
 * pg_catalog directly rather than going through the translator's SHOW emulation, which remains
 * available for third party code that issues SHOW or DESCRIBE queries of its own.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireDatabaseDialectPgsql extends WireDatabaseDialect {

	/**
	 * Minimum required PostgreSQL version
	 *
	 */
	const minVersion = '16.0';

	/**
	 * @var WireDatabasePgsqlTranslator|null
	 *
	 */
	protected $translator = null;

	/**
	 * Number of savepoints used by execStatements(), for unique savepoint names
	 *
	 * @var int
	 *
	 */
	protected $savepointNum = 0;

	/**
	 * Do the pw_fold() functions exist on this connection (see initConnection())?
	 *
	 * @var bool
	 *
	 */
	protected $foldAvailable = false;

	/**
	 * Do the pw_json_*() functions exist on this connection (see initConnection())?
	 *
	 * @var bool
	 *
	 */
	protected $jsonAvailable = false;

	/**
	 * Do the full text search functions (pw_tsvector(), pw_tsquery()) exist on this connection (see initConnection())?
	 *
	 * @var bool
	 *
	 */
	protected $fulltextAvailable = false;

	/**
	 * File of translations kept between requests, or blank when not used (see initTranslationCache())
	 *
	 * @var string
	 *
	 */
	protected $translationCacheFile = '';

	/**
	 * Is saveTranslationCache() registered to run at shutdown?
	 *
	 * @var bool
	 *
	 */
	protected $translationCacheShutdown = false;

	/**
	 * Did the open transaction change the schema? (the schema version moves on at commit, see schemaChanged())
	 *
	 * @var bool
	 *
	 */
	protected $schemaChangePending = false;

	/**
	 * Cached values from getVariable()
	 *
	 * @var array
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
		return 'pgsql';
	}

	/**
	 * Get the SQL translator
	 *
	 * @return WireDatabasePgsqlTranslator
	 *
	 */
	public function translator() {
		if($this->translator === null) {
			$database = $this->database;
			$this->translator = new WireDatabasePgsqlTranslator(function() use($database) { return $database->pdo(); });
			$this->translator->setTrigramAvailable($this->setting('trigram', true));
			$this->translator->setFoldAvailable($this->foldAvailable);
			$this->translator->setJsonAvailable($this->jsonAvailable);
			$this->translator->setFulltextAvailable($this->fulltextAvailable);
			if($this->translationCacheFile !== '') $this->loadTranslationCache();
		}
		return $this->translator;
	}

	/**
	 * Translate SQL (in MySQL syntax) to one or more PostgreSQL statements
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
	 * Quote a string MySQL-style (the translator converts it to a PostgreSQL literal)
	 *
	 * @param string $str
	 * @return string
	 *
	 */
	public function quote($str) {
		return WireDatabasePgsqlTranslator::quote($str);
	}

	/**
	 * Quote a table or column name for PostgreSQL
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	public function quoteIdentifier($name) {
		return '"' . $this->database->escapeTable($name) . '"';
	}

	/**
	 * Get PDO connection configuration from given $config
	 *
	 * Uses dbName, dbUser, dbPass, dbHost and dbPort. When dbSocket is set it is the directory
	 * containing the server's Unix socket (pdo_pgsql accepts a directory as host); dbPort still
	 * applies then, since socket files are named by port. Value types come back as pdo_pgsql
	 * returns them (integers as ints, the rest as strings), matching pdo_mysql on PHP 8.1+.
	 *
	 * @param Config $config
	 * @param array $options
	 * @return array
	 *
	 */
	public static function connectionConfig(Config $config, array $options) {
		unset($options['pgsql']); // ProcessWire settings rather than PDO driver options
		// note: no ATTR_STRINGIFY_FETCHES here. pdo_pgsql returns integers as PHP ints and everything else
		// as strings, which is what pdo_mysql does on PHP 8.1+ (the versions ProcessWire runs on), so
		// core sees the same value types it sees with MySQL.
		$parts = array();
		$parts[] = 'host=' . ($config->dbSocket ? $config->dbSocket : ($config->dbHost ? $config->dbHost : 'localhost'));
		if($config->dbPort) $parts[] = 'port=' . (int) $config->dbPort;
		$parts[] = 'dbname=' . $config->dbName;
		return array(
			'dsn' => 'pgsql:' . implode(';', $parts),
			'user' => $config->dbUser,
			'pass' => $config->dbPass,
			'options' => $options,
		);
	}

	/**
	 * Get a setting from $config->dbOptions['pgsql']
	 *
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 *
	 */
	public function setting($name, $default = null) {
		$config = $this->wire()->config;
		$options = $config ? $config->dbOptions : null;
		if(!is_array($options) || !isset($options['pgsql'])) return $default;
		$options = $options['pgsql'];
		if(!is_array($options) || !array_key_exists($name, $options)) return $default;
		return $options[$name];
	}

	/**
	 * Get the PDO class to use for connections
	 *
	 * @return string
	 *
	 */
	public function pdoClass() {
		// note: no autoload for PHP's own class (PW's autoloader may query the database while connecting)
		return class_exists('\\Pdo\\Pgsql', false) ? '\\Pdo\\Pgsql' : '\\PDO';
	}

	/**
	 * Initialize a new PDO connection
	 *
	 * @param \PDO $pdo
	 * @throws WireDatabaseException
	 *
	 */
	public function initConnection(\PDO $pdo) {
		$schema = (string) $this->setting('schema', '');
		if($schema !== '') $pdo->exec('SET search_path TO ' . $this->quoteIdentifier($schema) . ', public');
		// one round trip: version, session settings, and whether the fold and full text search functions exist
		// - the translator and upsertRowValue() write literals with only quotes doubled, so backslashes must be literal
		// - MySQL's NOW() uses the server's time zone; PHP's zone is the closest equivalent here
		// - random_page_cost: PostgreSQL's default (4) assumes spinning disks, which makes the planner prefer reading
		//   whole tables over index scans that would be several times faster on SSD or cached data (i.e. MATCH on a
		//   common word); set only when the server has not been configured
		$timezone = (string) date_default_timezone_get();
		$pageCost = $this->setting('randomPageCost', 1.1);
		$row = $pdo->query(
			"SELECT current_setting('server_version') AS version, " .
			"set_config('standard_conforming_strings', 'on', false) AS scs, " .
			($timezone !== '' ? "set_config('TimeZone', " . $pdo->quote($timezone) . ', false) AS tz, ' : '') .
			(is_numeric($pageCost) ? "(SELECT set_config('random_page_cost', " . $pdo->quote((string) (float) $pageCost) . ", false) " .
				"FROM pg_settings WHERE name = 'random_page_cost' AND source = 'default') AS rpc, " : '') .
			"to_regprocedure('pw_fold(text)') IS NOT NULL AND to_regprocedure('pw_unaccent(text)') IS NOT NULL AS fold, " .
			"to_regprocedure('" . WireDatabasePgsqlTranslator::jsonVersionFunction . "()') IS NOT NULL AS json, " .
			"to_regprocedure('" . WireDatabasePgsqlTranslator::fulltextMarker . "()') IS NOT NULL AS fulltext, " .
			"(SELECT last_value FROM pg_sequences WHERE schemaname = current_schema() AND sequencename = 'pw_schema_version') AS schema_version"
		)->fetch(\PDO::FETCH_ASSOC);
		$version = (string) $row['version'];
		if(version_compare(preg_replace('/[^0-9.].*$/', '', $version), self::minVersion, '<')) {
			throw new WireDatabaseException(
				"PostgreSQL $version is not supported, ProcessWire requires PostgreSQL " . self::minVersion . ' or newer'
			);
		}
		// custom statement class (deferred multi-statement DDL, MySQL error codes), replaces debug mode statement class
		$pdo->setAttribute(
			\PDO::ATTR_STATEMENT_CLASS,
			array(__NAMESPACE__ . "\\WireDatabasePgsqlStatement", array($this->database))
		);
		// PDO fills in bound values itself, as pdo_mysql does, rather than preparing each statement on the
		// server: that is a round trip per statement (about twice the time of a small query)
		if($this->setting('emulatePrepares', true)) $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
		$fold = false;
		if($this->setting('fold', true)) {
			$fold = in_array($row['fold'], array(true, 't', '1', 1), true);
			if(!$fold) {
				// first connection after install or upgrade: create them (see setupFold())
				$result = WireDatabasePgsqlTranslator::setupFold(
					function($sql) use($pdo) { $pdo->exec($sql); },
					function($sql) use($pdo) { return $pdo->query($sql)->fetchColumn(); }
				);
				$fold = $result['fold'];
				if($result['error'] !== '') $this->logSetupError($result['error']);
			}
		}
		$this->foldAvailable = $fold;
		if($this->translator !== null) $this->translator->setFoldAvailable($fold);
		// MySQL's JSON functions, as pw_json_*() (created on the first connection after install or upgrade)
		$json = in_array($row['json'], array(true, 't', '1', 1), true);
		if(!$json) {
			$result = WireDatabasePgsqlTranslator::setupJson(
				function($sql) use($pdo) { $pdo->exec($sql); },
				function($sql) use($pdo) { return $pdo->query($sql)->fetchColumn(); },
				function($sql) use($pdo) { return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC); }
			);
			$json = $result['json'];
			if($result['error'] !== '') $this->logSetupError($result['error']);
		}
		$this->jsonAvailable = $json;
		if($this->translator !== null) $this->translator->setJsonAvailable($json);

		$fulltext = false;
		if($this->setting('fulltext', true)) {
			$fulltext = in_array($row['fulltext'], array(true, 't', '1', 1), true);
			if(!$fulltext) {
				// first connection after install or upgrade: set up pw_search (see setupFulltext())
				$result = WireDatabasePgsqlTranslator::setupFulltext(
					function($sql) use($pdo) { $pdo->exec($sql); },
					function($sql) use($pdo) { return $pdo->query($sql)->fetchColumn(); },
					function($sql) use($pdo) { return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC); }
				);
				$fulltext = $result['fulltext'];
				if($result['error'] !== '') $this->logSetupError($result['error']);
			}
		}
		$this->fulltextAvailable = $fulltext;
		if($this->translator !== null) $this->translator->setFulltextAvailable($fulltext);
		$this->initTranslationCache($pdo, $row['schema_version']);
	}

	/**
	 * Keep translations between requests? (dbOptions pgsql translationCache: true, false, or null for automatic)
	 *
	 * Automatic: when OPcache is enabled, which keeps the compiled cache file in memory. Without it, reading the
	 * file on each request costs about as much as translating the statements again.
	 *
	 * @return bool
	 *
	 */
	protected function translationCacheEnabled() {
		$setting = $this->setting('translationCache', null);
		if(is_bool($setting)) return $setting;
		if(!function_exists('opcache_get_status')) return false;
		$status = @opcache_get_status(false);
		return is_array($status) && !empty($status['opcache_enabled']);
	}

	/**
	 * Set up the cache of translations kept between requests
	 *
	 * Translating ProcessWire's SQL costs about 0.1 ms per statement, and each request translates its statements
	 * again. Translations are kept in a PHP file per schema version: the pw_schema_version sequence, which
	 * schemaChanged() moves on after every schema change made through ProcessWire (so all requests on all servers
	 * then use a new file), plus the translator's version and what else a translation depends on (the fold,
	 * fulltext and JSON functions). Schema changes
	 * made outside ProcessWire need clearTranslationCache().
	 *
	 * @param \PDO $pdo
	 * @param int|string|null $version Current schema version, or null when the sequence does not exist yet
	 *
	 */
	protected function initTranslationCache(\PDO $pdo, $version) {
		$this->translationCacheFile = '';
		if(!$this->translationCacheEnabled()) return;
		try {
			if($version === null) {
				// first connection: every change from here on moves the version on
				$pdo->exec('CREATE SEQUENCE IF NOT EXISTS pw_schema_version');
				$version = $pdo->query("SELECT nextval('pw_schema_version')")->fetchColumn();
			}
		} catch(\Exception $e) {
			$this->logSetupError('Translations are not kept between requests, since pw_schema_version could not be created: ' . $e->getMessage());
			return;
		}
		$config = $this->wire()->config;
		$key = implode('|', array(
			$version, @filemtime(__DIR__ . '/WireDatabasePgsqlTranslator.php'), $config->dbHost, $config->dbPort, $config->dbSocket,
			$config->dbName, $this->setting('schema', ''), (int) $this->setting('trigram', true), (int) $this->foldAvailable,
			(int) $this->fulltextAvailable, (int) $this->jsonAvailable,
		));
		$this->translationCacheFile = $config->paths->cache . 'WireDatabasePgsql/translations-' . md5($key) . '.php';
		if($this->translator !== null) $this->loadTranslationCache();
		if(!$this->translationCacheShutdown) {
			register_shutdown_function(array($this, 'saveTranslationCache'));
			$this->translationCacheShutdown = true;
		}
	}

	/**
	 * Give the translator the translations kept for the current schema version
	 *
	 */
	protected function loadTranslationCache() {
		$entries = is_file($this->translationCacheFile) ? @include($this->translationCacheFile) : array();
		$this->translator->loadPersistentCache(is_array($entries) ? $entries : array());
	}

	/**
	 * Add this request's new translations to the cache file (at shutdown)
	 *
	 * Written to a temporary file and renamed, so that readers never see a partial file. A request that loses a
	 * race with another loses only its additions. Stops adding at dbOptions pgsql translationCacheMax entries
	 * (default 2000), since statements with literal values (i.e. id lists) would otherwise add without end.
	 *
	 * #pw-internal
	 *
	 */
	public function saveTranslationCache() {
		if($this->translationCacheFile === '' || $this->translator === null) return;
		$additions = $this->translator->persistentCacheAdditions();
		if(!count($additions)) return;
		$file = $this->translationCacheFile;
		$max = (int) $this->setting('translationCacheMax', 2000);
		$existing = is_file($file) ? @include($file) : null;
		$isNew = !is_array($existing);
		if($isNew) $existing = array();
		if(count($existing) >= $max) return;
		$merged = $existing + array_slice($additions, 0, $max - count($existing), true);
		$dir = dirname($file) . '/';
		$files = $this->wire()->files;
		if(!is_dir($dir) && !$files->mkdir($dir, true)) return;
		$tmp = $file . '.' . getmypid() . '.tmp';
		if(@file_put_contents($tmp, '<?php return ' . var_export($merged, true) . ";\n") === false) return;
		$files->chmod($tmp);
		if(!@rename($tmp, $file)) {
			@unlink($tmp);
			return;
		}
		if(function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
		if($isNew) {
			// files of earlier schema versions are not used again
			foreach(glob($dir . 'translations-*.php') ?: array() as $old) {
				if($old !== $file) @unlink($old);
			}
		}
	}

	/**
	 * Note a schema change: the schema version moves on, now or when the open transaction commits
	 *
	 * Called after every schema change made through ProcessWire (see isSchemaStatement()). Whether or not this
	 * connection keeps translations, others may.
	 *
	 * #pw-internal
	 *
	 * @param \PDO $pdo
	 *
	 */
	public function schemaChanged(\PDO $pdo) {
		if($pdo->inTransaction()) {
			$this->schemaChangePending = true;
		} else {
			$this->bumpSchemaVersion($pdo);
		}
	}

	/**
	 * Stop using the translations kept so far, on all connections (i.e. after changing the schema outside ProcessWire)
	 *
	 */
	public function clearTranslationCache() {
		$this->bumpSchemaVersion($this->database->pdo());
		if($this->translator !== null) $this->translator->loadPersistentCache(array());
	}

	/**
	 * Move the schema version on (a no-op when the sequence does not exist)
	 *
	 * @param \PDO $pdo
	 *
	 */
	protected function bumpSchemaVersion(\PDO $pdo) {
		try {
			$pdo->query("SELECT nextval(to_regclass('pw_schema_version'))");
		} catch(\Exception $e) {
			// i.e. no privilege on the sequence: translations of this site's other connections may be stale until it moves on
			$this->logSetupError('Could not move pw_schema_version on after a schema change: ' . $e->getMessage());
		}
	}

	/**
	 * Commit, then move the schema version on if the transaction changed the schema
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function commit(\PDO $pdo) {
		$result = parent::commit($pdo);
		if($this->schemaChangePending) {
			$this->schemaChangePending = false;
			$this->bumpSchemaVersion($pdo);
		}
		return $result;
	}

	/**
	 * Roll back (a schema change in the transaction is undone too)
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function rollBack(\PDO $pdo) {
		$this->schemaChangePending = false;
		return parent::rollBack($pdo);
	}

	/**
	 * Are text comparisons case- and accent-insensitive (the pw_fold() functions exist)?
	 *
	 * @return bool
	 *
	 */
	public function foldAvailable() {
		return $this->foldAvailable;
	}

	/**
	 * Log why a feature needing SQL functions (folding, JSON, full text search) is not (fully) available
	 *
	 * @param string $message
	 *
	 */
	protected function logSetupError($message) {
		$log = $this->wire()->log;
		if($log) $log->save('pgsql-errors', $message);
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
	public function prepareStatement(\PDO $pdo, $sql, array $options = array()) {
		// the pgsql statement class is required (deferred statements, error mapping), so a requested base class is ignored
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
		$followUps = array_slice($statements, 1);
		if(count($followUps) && count(preg_grep('/^SELECT setval\(/', $followUps)) === count($followUps)) {
			// INSERT with explicit identity values plus the sequence moves that follow it: the INSERT may
			// have bound values, and the follow-ups (which have none) run after it executes
			/** @var WireDatabasePgsqlStatement $statement */
			$statement = $this->prepareStatement($pdo, $statements[0]);
			$statement->setFollowUpStatements($followUps);
			return $statement;
		}
		/** @var WireDatabasePgsqlStatement $statement */
		$statement = $pdo->prepare('SELECT 1 WHERE 1=0');
		$statement->setDeferredStatements($statements);
		return $statement;
	}

	/**
	 * Begin a savepoint around one statement, when inside a transaction
	 *
	 * On PostgreSQL any failed statement aborts the whole transaction until it is rolled back;
	 * MySQL keeps the transaction usable. ProcessWire code (i.e. PagesParents) catches expected
	 * failures such as duplicate keys and carries on inside page-save transactions, so each
	 * statement executed inside a transaction gets its own savepoint. Can be turned off with
	 * `$config->dbOptions['pgsql']['savepoints'] = false`.
	 *
	 * @param \PDO $pdo
	 * @return string Savepoint name, or blank string when none was made
	 *
	 */
	public function savepointBegin(\PDO $pdo) {
		if(!$pdo->inTransaction() || !$this->setting('savepoints', true)) return '';
		$name = 'pw_stmt_' . (++$this->savepointNum);
		$pdo->exec("SAVEPOINT $name");
		return $name;
	}

	/**
	 * Release a statement savepoint
	 *
	 * @param \PDO $pdo
	 * @param string $name
	 *
	 */
	public function savepointRelease(\PDO $pdo, $name) {
		if($name === '') return;
		try {
			$pdo->exec("RELEASE SAVEPOINT $name");
		} catch(\PDOException $e) {
			// transaction already ended by the statement itself (i.e. COMMIT/ROLLBACK issued as SQL)
		}
	}

	/**
	 * Roll back to a statement savepoint after a failure, leaving the transaction usable
	 *
	 * @param \PDO $pdo
	 * @param string $name
	 *
	 */
	public function savepointRollback(\PDO $pdo, $name) {
		if($name === '') return;
		try {
			$pdo->exec("ROLLBACK TO SAVEPOINT $name");
			$pdo->exec("RELEASE SAVEPOINT $name");
		} catch(\PDOException $e) {
			// connection-level failure: the original exception is more useful
		}
	}

	/**
	 * Execute a single translated statement, within a savepoint when in a transaction
	 *
	 * @param \PDO $pdo
	 * @param string $sql
	 * @return int|false
	 * @throws \PDOException
	 *
	 */
	public function execStatement(\PDO $pdo, $sql) {
		$savepoint = $this->savepointBegin($pdo);
		try {
			$result = $pdo->exec($sql);
			$this->savepointRelease($pdo, $savepoint);
			if($result !== false && WireDatabaseSchemaLog::isSchemaStatement($sql)) $this->schemaChanged($pdo);
			return $result;
		} catch(\PDOException $e) {
			$this->savepointRollback($pdo, $savepoint);
			throw $e;
		}
	}

	/**
	 * Run a query(), within a savepoint when in a transaction
	 *
	 * @param \PDO $pdo
	 * @param string $sql
	 * @return \PDOStatement|false
	 * @throws \PDOException
	 *
	 */
	public function queryStatement(\PDO $pdo, $sql) {
		$savepoint = $this->savepointBegin($pdo);
		try {
			$result = $pdo->query($sql);
			$this->savepointRelease($pdo, $savepoint);
			if($result !== false && WireDatabaseSchemaLog::isSchemaStatement($sql)) $this->schemaChanged($pdo);
			return $result;
		} catch(\PDOException $e) {
			$this->savepointRollback($pdo, $savepoint);
			throw $e;
		}
	}

	/**
	 * Execute multiple translated statements atomically
	 *
	 * Inside a transaction the statements run within a savepoint; outside one they run in their
	 * own transaction. Either way a failure leaves no partial changes behind.
	 *
	 * @param \PDO $pdo
	 * @param array $statements
	 * @return int Number of rows affected (sum)
	 * @throws \PDOException
	 *
	 */
	public function execStatements(\PDO $pdo, array $statements) {
		$qty = 0;
		if($pdo->inTransaction()) {
			$savepoint = 'pw_statements_' . (++$this->savepointNum);
			$pdo->exec("SAVEPOINT $savepoint");
			try {
				foreach($statements as $sql) {
					$result = $pdo->exec($sql);
					if($result !== false) $qty += $result;
				}
				$pdo->exec("RELEASE SAVEPOINT $savepoint");
			} catch(\PDOException $e) {
				try {
					$pdo->exec("ROLLBACK TO SAVEPOINT $savepoint");
					$pdo->exec("RELEASE SAVEPOINT $savepoint");
				} catch(\PDOException $e2) {
					// connection-level failure: original exception is more useful
				}
				throw self::mysqlException($e);
			}
		} else {
			$pdo->beginTransaction();
			try {
				foreach($statements as $sql) {
					$result = $pdo->exec($sql);
					if($result !== false) $qty += $result;
				}
				$pdo->commit();
			} catch(\PDOException $e) {
				try {
					$pdo->rollBack();
				} catch(\PDOException $e2) {
					// connection-level failure: original exception is more useful
				}
				throw self::mysqlException($e);
			}
		}
		if(count(array_filter($statements, array(__NAMESPACE__ . '\\WireDatabaseSchemaLog', 'isSchemaStatement')))) $this->schemaChanged($pdo);
		return $qty;
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
		if($method === 'prepare' && $e instanceof WireDatabasePgsqlException && !$pdo->inTransaction()) {
			// MySQL reports unknown tables/columns at execute() rather than prepare()
			$statement = $pdo->prepare('SELECT 1 WHERE 1=0');
			if($statement instanceof WireDatabasePgsqlStatement) {
				$statement->setDeferredException($e);
				return $statement;
			}
		}
		return $e;
	}

	/**
	 * Log a failed query to site/assets/logs/pgsql-errors.txt (debug mode only)
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
		$trace = array();
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
		@file_put_contents($config->paths->logs . 'pgsql-errors.txt', $entry, FILE_APPEND);
	}

	/**
	 * Convert PostgreSQL exceptions ProcessWire checks for to their MySQL equivalents
	 *
	 * ProcessWire checks for SQLSTATE 42S02 (table not found), 42S22 (column not found) and
	 * 23000 (duplicate key). Other exceptions are returned unchanged.
	 *
	 * @param \PDOException $e
	 * @return \PDOException
	 *
	 */
	public static function mysqlException(\PDOException $e) {
		$state = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : (string) $e->getCode();
		$message = $e->getMessage();
		$map = array(
			'42P01' => array('42S02', 1146, 'Base table or view not found', '/relation "([^"]+)" does not exist/', "Table '%s' doesn't exist"),
			'42703' => array('42S22', 1054, 'Column not found', '/column ("?)([^" ]+)\1 (?:of relation "[^"]+" )?does not exist/', "Unknown column '%s' in 'field list'"),
			'23505' => array('23000', 1062, 'Integrity constraint violation', '/violates unique constraint "([^"]+)"/', "Duplicate entry for key '%s'"),
			'23503' => array('23000', 1452, 'Integrity constraint violation', '/violates foreign key constraint "([^"]+)"/', "Cannot add or update a child row: a foreign key constraint fails ('%s')"),
			'42P07' => array('42S01', 1050, 'Base table or view already exists', '/relation "([^"]+)" already exists/', "Table '%s' already exists"),
			'42701' => array('42S21', 1060, 'Column already exists', '/column "([^"]+)"/', "Duplicate column name '%s'"),
		);
		if(!isset($map[$state])) return $e;
		list($mysqlState, $errno, $class, $regex, $format) = $map[$state];
		$name = preg_match($regex, $message, $m) ? end($m) : '';
		$info = sprintf($format, $name);
		// shaped like pdo_mysql's message, since some code matches on it (i.e. for '1054')
		$ex = new WireDatabasePgsqlException("SQLSTATE[$mysqlState]: $class: $errno $info ($message)", 0, $e);
		$ex->setMySQLError($mysqlState, array($mysqlState, $errno, $info));
		return $ex;
	}

	/**
	 * Build an INSERT that updates the existing row on a key conflict (see WireDatabaseDialect::upsert())
	 *
	 * The result is PostgreSQL SQL that the translator passes through untouched, so the caller's
	 * expressions (MySQL syntax, like all SQL given to ProcessWire) are translated here first: MySQL
	 * string escapes, functions such as IF() and IFNULL(), VALUES(col), and bare column names in
	 * update expressions, which mean the existing row's value.
	 *
	 * @param string $table
	 * @param array $columns
	 * @param array $update
	 * @param array $options
	 * @return string
	 * @throws WireDatabaseException
	 *
	 */
	public function upsert($table, array $columns, array $update, array $options = array()) {
		$translator = $this->translator();
		$names = array();
		foreach(array($columns, $update) as $list) {
			foreach($list as $key => $value) $names[] = is_int($key) ? (string) $value : (string) $key;
		}
		if(!empty($options['conflict'])) $names = array_merge($names, $options['conflict']);
		$translatedColumns = array();
		foreach($columns as $key => $value) {
			if(is_int($key)) {
				$translatedColumns[] = $value;
			} else {
				$translatedColumns[$key] = $translator->upsertValueSql($value);
			}
		}
		$translatedUpdate = array();
		foreach($update as $key => $value) {
			if(is_int($key)) {
				$translatedUpdate[] = $value;
			} else {
				$translatedUpdate[$key] = $translator->upsertUpdateSql($value, $table, $names);
			}
		}
		return parent::upsert($table, $translatedColumns, $translatedUpdate, $options);
	}

	/**
	 * Get a literal for one value of the upsert() 'rows' option
	 *
	 * upsert() output is already PostgreSQL SQL and is not translated, so strings cannot use
	 * $database->quote(), which escapes MySQL-style for the translator to convert. With
	 * standard_conforming_strings (set on connect) only single quotes need doubling. NUL bytes,
	 * which PostgreSQL text cannot hold, are dropped as the translator drops them.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws WireDatabaseException
	 *
	 */
	protected function upsertRowValue($value) {
		if(is_string($value)) return "'" . str_replace(array("\0", "'"), array('', "''"), $value) . "'";
		return parent::upsertRowValue($value);
	}

	/**
	 * Get the clause of an upsert() statement that updates the existing row
	 *
	 * PostgreSQL requires a conflict target. When the caller does not give one, the table's
	 * primary key is used; when that is unknown too, an exception is thrown.
	 *
	 * @param array $updates
	 * @param array $conflict
	 * @param string $table
	 * @return string
	 * @throws WireDatabaseException
	 *
	 */
	protected function upsertUpdateClause(array $updates, array $conflict, $table = '') {
		if(!count($conflict)) {
			try {
				$primary = $this->getIndexes($table, 'PRIMARY');
			} catch(\Exception $e) {
				$primary = array(); // not connected to PostgreSQL (i.e. dialect constructed for tests), or no such table
			}
			$conflict = empty($primary['columns']) ? array() : $primary['columns'];
		}
		if(!count($conflict)) {
			throw new WireDatabaseException(
				"upsert() on '$table' requires a conflict target (primary or unique key columns) on PostgreSQL and none was given or found"
			);
		}
		$sets = array();
		foreach($updates as $name => $expr) {
			// expressions were translated (and bare columns qualified) by upsert()
			$col = $this->quoteIdentifier($name);
			$sets[] = $col . '=' . ($expr === null ? "excluded.$col" : $expr);
		}
		$names = array();
		foreach($conflict as $name) $names[] = $this->quoteIdentifier($name);
		return 'ON CONFLICT (' . implode(', ', $names) . ') DO UPDATE SET ' . implode(', ', $sets);
	}

	/*********************************************************************************
	 * Capabilities
	 *
	 */

	public function supportsFoundRows() { return false; }
	public function supportsFulltext() { return $this->fulltextAvailable; }

	/**
	 * No stopwords: the pw_search configuration indexes every word (see WireDatabasePgsqlTranslator::setupFulltext())
	 *
	 * @return array
	 *
	 */
	public function fulltextStopwords() {
		return array();
	}
	public function supportsUpdateOrderBy() { return false; }
	public function supportsJson() { return $this->jsonAvailable; } // MySQL's JSON functions, as pw_json_*()

	/**
	 * Transactions are always available
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
			case 'version':
				if($cache && isset($this->variableCache['version'])) return $this->variableCache['version'];
				$version = (string) $this->database->pdo()->query("SELECT current_setting('server_version')")->fetchColumn();
				$this->variableCache['version'] = $version;
				return $version;
			case 'version_comment': return 'PostgreSQL';
			case 'ft_min_word_len':
			case 'innodb_ft_min_token_size': return '1';
			case 'ft_max_word_len':
			case 'innodb_ft_max_token_size': return '84';
		}
		return null;
	}

	/**
	 * Get PostgreSQL version
	 *
	 * @param bool $getNumberOnly
	 * @return string
	 *
	 */
	public function getVersion($getNumberOnly = false) {
		$version = (string) $this->getVariable('version');
		if($getNumberOnly && preg_match('/^([\d.]+)/', $version, $matches)) $version = $matches[1];
		return $version;
	}

	/**
	 * @return string
	 *
	 */
	public function getServerType() {
		return 'PostgreSQL';
	}

	/**
	 * Regular expression engine name, for code that builds word-boundary patterns
	 *
	 * PostgreSQL's ARE syntax supports the [[:<:]] and [[:>:]] word boundaries that the
	 * HenrySpencer engine (MySQL before 8) uses; \b means backspace here, so ICU syntax would not work.
	 *
	 * @return string
	 *
	 */
	public function getRegexEngine() {
		return 'HenrySpencer';
	}

	/**
	 * Max length for a fully indexed varchar column
	 *
	 * @return int
	 *
	 */
	public function getMaxIndexLength() {
		return 250;
	}

	/**
	 * SQL modes do not apply to PostgreSQL
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
		$sql = $getTimestamp ? 'SELECT extract(epoch from now())::bigint' : "SELECT to_char(now(), 'YYYY-MM-DD HH24:MI:SS')";
		$value = $this->database->pdo()->query($sql)->fetchColumn();
		return $getTimestamp ? (int) $value : $value;
	}

	/**
	 * @param \PDOException $e
	 * @return string
	 *
	 */
	public function getRetryableErrorType(\PDOException $e) {
		$state = isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : '';
		if($state === '40P01' || $state === '40001') return 'deadlock';
		if($state === '57P01' || $state === '57P02' || $state === '57P03') return 'gone-away';
		if(strpos($state, '08') === 0) return 'comm-failure';
		// libpq reports a lost or refused connection as a general error rather than an 08 state
		$message = $e->getMessage();
		if(stripos($message, 'server closed the connection') !== false || stripos($message, 'no connection to the server') !== false
			|| stripos($message, 'terminating connection') !== false || stripos($message, 'connection to server was lost') !== false) {
			return 'gone-away';
		}
		if(stripos($message, 'could not connect to server') !== false || stripos($message, 'connection to server at') !== false) {
			return 'comm-failure';
		}
		// MySQL-shaped errors (codes 1213, 2006, 2013 and their messages) as classified by the MySQL dialect,
		// for code that constructs or forwards them regardless of the database in use
		$mysql = new WireDatabaseDialectMySQL($this->database);
		return $mysql->getRetryableErrorType($e);
	}

	/*********************************************************************************
	 * INTROSPECTION
	 *
	 * These query information_schema and pg_catalog directly (no translation), but return the
	 * same MySQL-shaped values that the MySQL dialect returns from SHOW and DESCRIBE.
	 *
	 */

	/**
	 * Execute a query on the connection, bypassing translation
	 *
	 * @param string $sql
	 * @param array $params
	 * @return array Rows indexed by column name
	 *
	 */
	protected function catalog($sql, array $params = array()) {
		$query = $this->database->pdo()->prepare($sql);
		$query->execute($params);
		$rows = $query->fetchAll(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		return $rows;
	}

	/**
	 * Get primary key column names of a table
	 *
	 * @param string $table
	 * @return array
	 *
	 */
	protected function primaryColumns($table) {
		$rows = $this->catalog(
			"SELECT a.attname FROM pg_index i " .
			"JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) " .
			"JOIN pg_class c ON c.oid = i.indrelid JOIN pg_namespace n ON n.oid = c.relnamespace " .
			"WHERE c.relname = ? AND n.nspname = current_schema() AND i.indisprimary " .
			"ORDER BY array_position(i.indkey, a.attnum)",
			array($table)
		);
		$columns = array();
		foreach($rows as $row) $columns[] = $row['attname'];
		return $columns;
	}

	/**
	 * Get columns of given table as SHOW COLUMNS rows
	 *
	 * @param string $table
	 * @return array
	 *
	 */
	protected function tableInfo($table) {
		$primary = $this->primaryColumns($table);
		$rows = array();
		$sql =
			"SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, " .
			"is_nullable, column_default, is_identity FROM information_schema.columns " .
			// stored tsvector columns of FULLTEXT keys (see WireDatabasePgsqlTranslator::vectorColumnSql()) are internal
			"WHERE table_schema = current_schema() AND table_name = ? AND NOT (data_type = 'tsvector' AND is_generated = 'ALWAYS') " .
			"ORDER BY ordinal_position";
		foreach($this->catalog($sql, array($table)) as $row) {
			$type = $row['data_type'];
			switch($type) {
				case 'character varying': $type = 'varchar(' . $row['character_maximum_length'] . ')'; break;
				case 'character': $type = 'char(' . $row['character_maximum_length'] . ')'; break;
				case 'integer': $type = 'int'; break;
				case 'timestamp without time zone': $type = 'datetime'; break;
				case 'timestamp with time zone': $type = 'timestamp'; break;
				case 'double precision': $type = 'double'; break;
				case 'real': $type = 'float'; break;
				case 'numeric': $type = 'decimal(' . $row['numeric_precision'] . ',' . $row['numeric_scale'] . ')'; break;
				case 'jsonb': $type = 'json'; break;
				case 'bytea': $type = 'blob'; break;
			}
			$rows[] = array(
				'Field' => $row['column_name'],
				'Type' => $type,
				'Null' => $row['is_nullable'] === 'YES' ? 'YES' : 'NO',
				'Key' => in_array($row['column_name'], $primary, true) ? 'PRI' : '',
				'Default' => $row['column_default'],
				'Extra' => $row['is_identity'] === 'YES' ? 'auto_increment' : '',
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
		$sql =
			"SELECT i.relname AS index_name, ix.indisunique AS is_unique, ix.indisprimary AS is_primary, " .
			"k.ord AS seq, a.attname AS column_name, pg_get_indexdef(ix.indexrelid, k.ord::int, true) AS expr, " .
			"obj_description(i.oid, 'pg_class') AS comment " .
			"FROM pg_index ix JOIN pg_class t ON t.oid = ix.indrelid JOIN pg_class i ON i.oid = ix.indexrelid " .
			"JOIN pg_namespace n ON n.oid = t.relnamespace " .
			"CROSS JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY AS k(attnum, ord) " .
			"LEFT JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = k.attnum " .
			"WHERE t.relname = ? AND n.nspname = current_schema() ORDER BY i.relname, k.ord";
		$rows = array();
		foreach($this->catalog($sql, array($table)) as $row) {
			$name = (string) $row['index_name'];
			if($row['column_name'] === null) {
				// indexed expression, i.e. pw_fold(data) or left(data, 250): report the column it is on, as MySQL would
				$expr = preg_replace('/^(?:\(?"?\w+"?\()+/', '', ltrim((string) $row['expr'], '('));
				if(!preg_match('/^"?([A-Za-z0-9_]+)"?/', $expr, $m)) continue;
				$row['column_name'] = $m[1];
			}
			$primary = in_array($row['is_primary'], array(true, 't', '1', 1), true);
			$unique = in_array($row['is_unique'], array(true, 't', '1', 1), true);
			if($primary) {
				$keyName = 'PRIMARY';
			} else {
				$keyName = WireDatabasePgsqlTranslator::mysqlIndexName($table, $name, $row['comment']);
				if($keyName === null) $keyName = $name;
			}
			// folded companion of a unique or primary key and tsvector index of a FULLTEXT key (see
			// WireDatabasePgsqlTranslator::createIndexSql()), and the indexes of a jsonb column (see jsonIndexSql()): internal
			foreach(array(WireDatabasePgsqlTranslator::foldIndexSuffix, WireDatabasePgsqlTranslator::jsonIndexSuffix, WireDatabasePgsqlTranslator::jsonNestedIndexSuffix, WireDatabasePgsqlTranslator::fulltextIndexSuffix) as $suffix) {
				if(substr($keyName, -strlen($suffix)) === $suffix) continue 2;
			}
			$rows[] = array(
				'Table' => $table,
				'Key_name' => $keyName,
				'Non_unique' => $unique ? 0 : 1,
				'Seq_in_index' => (int) $row['seq'],
				'Column_name' => $row['column_name'],
				'Index_type' => 'BTREE',
			);
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
		$tables = array();
		$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name";
		foreach($this->catalog($sql) as $row) $tables[] = $row['table_name'];
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
		$getColumn = $verbose && is_string($verbose) ? $verbose : '';
		if(strpos($table, '.')) list($table, $getColumn) = explode('.', $table, 2);
		foreach($this->tableInfo($table) as $col) {
			$name = $col['Field'];
			if($getColumn !== '' && $name !== $getColumn) continue;
			if($verbose === 3) {
				// MySQL-syntax column definition, as SHOW CREATE TABLE would give
				$def = $col['Type'];
				if($col['Null'] === 'NO') $def .= ' NOT NULL';
				if($col['Extra'] === 'auto_increment') $def .= ' AUTO_INCREMENT';
				if($col['Default'] !== null && $col['Extra'] !== 'auto_increment') $def .= ' DEFAULT ' . $col['Default'];
				$columns[$name] = $def;
			} else if($verbose === 2) {
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
		$rows = $this->catalog(
			"SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?",
			array($table)
		);
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
		foreach($this->tableInfo($table) as $col) {
			if($col['Field'] === $column) return $getInfo ? $col : true;
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
		if(!count($rows)) return false;
		return $getInfo ? $rows : true;
	}
}
