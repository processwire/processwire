<?php namespace ProcessWire;

/**
 * PDOException with a MySQL-equivalent SQLSTATE code and errorInfo, used by the SQLite dialect
 *
 * ProcessWire checks for MySQL SQLSTATE codes such as 42S02 (table not found) and 42S22
 * (column not found). See WireDatabaseDialectSQLite::mysqlException().
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 */

class WireDatabaseSQLiteException extends \PDOException {

	/**
	 * Set the MySQL-equivalent SQLSTATE code and errorInfo
	 *
	 * @param string $state SQLSTATE, i.e. '42S22'
	 * @param array $errorInfo [SQLSTATE, MySQL error number, message]
	 *
	 */
	public function setMySQLError($state, array $errorInfo) {
		$this->code = $state;
		$this->errorInfo = $errorInfo;
	}
}
