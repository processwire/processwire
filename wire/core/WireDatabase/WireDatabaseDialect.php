<?php namespace ProcessWire;

/**
 * ProcessWire database dialect base class
 *
 * Database dialects provide vendor-specific SQL used by WireDatabasePDO while
 * keeping the public database API on WireDatabasePDO.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

abstract class WireDatabaseDialect extends Wire {

	/**
	 * Database instance using this dialect
	 *
	 * @var WireDatabasePDO
	 *
	 */
	protected $database;

	/**
	 * Construct dialect
	 *
	 * @param WireDatabasePDO $database
	 *
	 */
	public function __construct(WireDatabasePDO $database) {
		parent::__construct();
		$this->database = $database;
	}

	/**
	 * Get dialect name
	 *
	 * @return string
	 *
	 */
	abstract public function name();

	/**
	 * Are transactions available with current DB engine (or table)?
	 *
	 * @param string $table
	 * @return bool
	 *
	 */
	abstract public function supportsTransaction($table = '');

	/**
	 * Get array of all tables in this database
	 *
	 * @return array
	 *
	 */
	abstract public function getTables();

	/**
	 * Get all columns from given table
	 *
	 * @param string $table
	 * @param bool|int|string $verbose
	 * @return array
	 *
	 */
	abstract public function getColumns($table, $verbose = false);

	/**
	 * Get all indexes from given table
	 *
	 * @param string $table
	 * @param bool|int|string $verbose
	 * @return array
	 *
	 */
	abstract public function getIndexes($table, $verbose = false);

	/**
	 * Does the given table exist?
	 *
	 * @param string $table
	 * @return bool
	 *
	 */
	abstract public function tableExists($table);

	/**
	 * Does the given column exist in given table?
	 *
	 * @param string $table
	 * @param string $column
	 * @param bool $getInfo
	 * @return bool|array
	 *
	 */
	abstract public function columnExists($table, $column = '', $getInfo = false);

	/**
	 * Does table have an index with given name?
	 *
	 * @param string $table
	 * @param string $indexName
	 * @param bool $getInfo
	 * @return bool|array
	 *
	 */
	abstract public function indexExists($table, $indexName, $getInfo = false);

	/**
	 * Rename table columns without changing type
	 *
	 * @param string $table
	 * @param array $columns
	 * @return int
	 *
	 */
	abstract public function renameColumns($table, array $columns);

	/**
	 * Get database variable value
	 *
	 * @param string $name
	 * @param bool $cache
	 * @param bool $sub
	 * @return string|null
	 *
	 */
	abstract public function getVariable($name, $cache = true, $sub = true);

	/**
	 * Get database server version
	 *
	 * @param bool $getNumberOnly
	 * @return string
	 *
	 */
	abstract public function getVersion($getNumberOnly = false);

	/**
	 * Get database server type
	 *
	 * @return string
	 *
	 */
	abstract public function getServerType();

	/**
	 * Get regular expression engine used by database
	 *
	 * @return string
	 *
	 */
	abstract public function getRegexEngine();

	/**
	 * Get max length allowed for a fully indexed varchar column
	 *
	 * @return int
	 *
	 */
	abstract public function getMaxIndexLength();

	/**
	 * Get, set, add, or remove SQL mode
	 *
	 * @param string $action
	 * @param string $mode
	 * @param string $minVersion
	 * @param \PDO|null $pdo
	 * @return string|bool
	 *
	 */
	abstract public function sqlMode($action = 'get', $mode = '', $minVersion = '', $pdo = null);

	/**
	 * Get current date/time ISO-8601 string or UNIX timestamp according to database
	 *
	 * @param bool $getTimestamp
	 * @return string|int
	 *
	 */
	abstract public function getTime($getTimestamp = false);

	/**
	 * Classify a query exception as a transient error that may be resolved by retrying
	 *
	 * Since error codes are specific to the database engine, classification lives here in
	 * the dialect. Returns one of the following, or blank string when the error is not a
	 * known transient condition:
	 *
	 * - `deadlock`: Server chose this statement as a deadlock victim or could not serialize
	 *    it, and has already rolled back the entire transaction. No reconnect is needed, so
	 *    it is safe to retry when no transaction is open (otherwise the whole transaction
	 *    must be retried instead).
	 * - `gone-away`: Server closed an idle connection. Retry requires a reconnect, which
	 *    discards any open transaction.
	 * - `comm-failure`: Connection to server failed mid-query. Retry requires a reconnect,
	 *    which discards any open transaction.
	 *
	 * @param \PDOException $e
	 * @return string One of 'deadlock', 'gone-away', 'comm-failure', or blank string
	 * @since 3.0.272
	 *
	 */
	abstract public function getRetryableErrorType(\PDOException $e);

	/*********************************************************************************
	 * Connection
	 *
	 */

	/**
	 * Get PDO connection configuration from given $config
	 *
	 * Returns array with 'dsn', 'user', 'pass', 'options' and optionally 'reader'
	 * (see WireDatabasePDO constructor).
	 *
	 * @param Config $config
	 * @param array $options PDO driver options already determined by WireDatabasePDO
	 * @return array
	 * @throws WireException
	 *
	 */
	public static function connectionConfig(Config $config, array $options) {
		throw new WireException(static::class . ' does not implement connectionConfig()');
	}

	/**
	 * Get the PDO class to use for connections
	 *
	 * @return string
	 *
	 */
	public function pdoClass() {
		return '\\PDO';
	}

	/**
	 * Initialize a newly established PDO connection
	 *
	 * Called by WireDatabasePDO after the connection is established and debug mode is configured.
	 *
	 * @param \PDO $pdo
	 *
	 */
	public function initConnection(\PDO $pdo) {
	}

	/*********************************************************************************
	 * Transactions
	 *
	 * WireDatabasePDO's transaction methods delegate to these, so that a dialect can change
	 * how transactions are started (i.e. SQLite uses BEGIN IMMEDIATE).
	 *
	 */

	/**
	 * Begin a transaction
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function beginTransaction(\PDO $pdo) {
		return $pdo->beginTransaction();
	}

	/**
	 * Is a transaction active?
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function inTransaction(\PDO $pdo) {
		return (bool) $pdo->inTransaction();
	}

	/**
	 * Commit the active transaction
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function commit(\PDO $pdo) {
		return $pdo->commit();
	}

	/**
	 * Roll back the active transaction
	 *
	 * @param \PDO $pdo
	 * @return bool
	 *
	 */
	public function rollBack(\PDO $pdo) {
		return $pdo->rollBack();
	}

	/*********************************************************************************
	 * SQL translation
	 *
	 * Dialects for databases other than MySQL may translate the MySQL syntax used by
	 * ProcessWire (and its modules) before it is executed. The methods in this section
	 * are only called when translatesSql() returns true.
	 *
	 */

	/**
	 * Does this dialect translate SQL before executing it?
	 *
	 * @return bool
	 *
	 */
	public function translatesSql() {
		return false;
	}

	/**
	 * Translate SQL (in MySQL syntax) for this database
	 *
	 * Returns one or more statements. Multiple statements are executed in order and atomically
	 * (see execStatements() and prepareStatements()).
	 *
	 * @param string $sql
	 * @return array
	 *
	 */
	public function translateSql($sql) {
		return [$sql];
	}

	/**
	 * Quote a string for use in SQL (in MySQL syntax) that will be translated
	 *
	 * @param string $str
	 * @return string
	 *
	 */
	public function quote($str) {
		return $this->database->pdo()->quote((string) $str);
	}

	/**
	 * Prepare translated SQL
	 *
	 * @param \PDO $pdo
	 * @param string $sql Translated SQL
	 * @param array $options Driver options
	 * @return \PDOStatement
	 *
	 */
	public function prepareStatement(\PDO $pdo, $sql, array $options = array()) {
		return $pdo->prepare($sql, $options);
	}

	/**
	 * Prepare multiple translated statements, to be executed when the returned statement is executed
	 *
	 * @param \PDO $pdo
	 * @param array $statements
	 * @return \PDOStatement
	 * @throws \PDOException
	 *
	 */
	public function prepareStatements(\PDO $pdo, array $statements) {
		throw new \PDOException(static::class . ' does not support preparing multiple statements');
	}

	/**
	 * Execute a single translated statement
	 *
	 * @param \PDO $pdo
	 * @param string $sql
	 * @return int|false
	 * @throws \PDOException
	 *
	 */
	public function execStatement(\PDO $pdo, $sql) {
		return $pdo->exec($sql);
	}

	/**
	 * Run a query() and return its statement
	 *
	 * Provided so that a dialect can do its own bookkeeping for statements that PDO creates
	 * (and executes) itself, since those do not pass through prepare() or execute().
	 *
	 * @param \PDO $pdo
	 * @param string $sql Already translated, if the dialect translates
	 * @return \PDOStatement|false
	 * @throws \PDOException
	 * @since 3.0.273
	 *
	 */
	public function queryStatement(\PDO $pdo, $sql) {
		return $pdo->query($sql);
	}

	/**
	 * Execute multiple translated statements atomically
	 *
	 * @param \PDO $pdo
	 * @param array $statements
	 * @return int Number of rows affected
	 * @throws \PDOException
	 *
	 */
	public function execStatements(\PDO $pdo, array $statements) {
		$qty = 0;
		foreach($statements as $sql) $qty += (int) $pdo->exec($sql);
		return $qty;
	}

	/**
	 * Handle an exception from a translated query(), exec() or prepare()
	 *
	 * Returns the exception to throw, or (for prepare) a statement to return instead.
	 *
	 * @param \PDOException $e
	 * @param string $method One of 'query', 'exec' or 'prepare'
	 * @param string $sql Original SQL
	 * @param string $translated Translated SQL (blank if translation itself failed)
	 * @param \PDO $pdo
	 * @return \PDOException|\PDOStatement
	 *
	 */
	public function queryException(\PDOException $e, $method, $sql, $translated, \PDO $pdo) {
		return $e;
	}

	/*********************************************************************************
	 * Capabilities
	 *
	 * Check these rather than which database is in use, i.e. `$database->dialect()->supportsFulltext()`
	 *
	 */

	/**
	 * Supports SQL_CALC_FOUND_ROWS and FOUND_ROWS()?
	 *
	 * @return bool
	 *
	 */
	public function supportsFoundRows() {
		return true;
	}

	/**
	 * Supports FULLTEXT indexes and MATCH/AGAINST?
	 *
	 * @return bool
	 *
	 */
	public function supportsFulltext() {
		return true;
	}

	/**
	 * Supports ORDER BY in UPDATE statements, applied row-by-row for unique key checks?
	 *
	 * This is what makes `UPDATE t SET sort=sort+1 WHERE ... ORDER BY sort DESC` possible
	 * on a unique/primary key that includes the sort column.
	 *
	 * @return bool
	 *
	 */
	public function supportsUpdateOrderBy() {
		return true;
	}

	/**
	 * Supports MySQL's JSON functions (JSON_EXTRACT, JSON_SET, JSON_CONTAINS, etc.)?
	 *
	 * @return bool
	 * @since 3.0.273
	 *
	 */
	public function supportsJson() {
		return true;
	}

	/**
	 * Get collation to apply to text comparisons, or blank string if none needed
	 *
	 * Returns a collation name to append to a text comparison as `COLLATE name`, making it
	 * behave like MySQL's default case- and accent-insensitive collation. MySQL needs no such
	 * collation on individual comparisons, as its columns already have one, so it returns blank.
	 *
	 * The `$value` argument lets a dialect decide based on what is being compared, so that the
	 * collation can be limited to values that actually need it.
	 *
	 * ~~~~~
	 * $collate = $database->dialect()->compareCollation($value);
	 * $query->where("$table.data=?" . ($collate ? " COLLATE $collate" : ''), $value);
	 * ~~~~~
	 *
	 * @param string|int|float|null $value Value being compared, when known
	 * @return string
	 * @since 3.0.273
	 *
	 */
	public function compareCollation($value = null) {
		return '';
	}

	/**
	 * Get collation to apply to text ORDER BY terms, or blank string if none needed
	 *
	 * Like compareCollation(), but for sorting rather than comparison, and applicable only to
	 * terms known to contain text.
	 *
	 * @return string
	 * @since 3.0.273
	 *
	 */
	public function sortCollation() {
		return '';
	}
}
