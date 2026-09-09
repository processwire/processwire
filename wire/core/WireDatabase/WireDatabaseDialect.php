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
}
