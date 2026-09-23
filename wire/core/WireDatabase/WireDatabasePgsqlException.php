<?php namespace ProcessWire;

/**
 * PDOException with a MySQL-equivalent SQLSTATE code and errorInfo, used by the PostgreSQL dialect
 *
 * ProcessWire checks for MySQL SQLSTATE codes such as 42S02 (table not found), 42S22
 * (column not found) and 23000 (duplicate key). See WireDatabaseDialectPgsql::mysqlException().
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 */

class WireDatabasePgsqlException extends \PDOException {

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
