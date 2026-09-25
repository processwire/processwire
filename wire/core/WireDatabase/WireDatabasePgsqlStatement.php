<?php namespace ProcessWire;

/**
 * ProcessWire PostgreSQL PDO statement
 *
 * Adds two things the translating dialect needs: deferred execution of SQL that translated to
 * several statements (i.e. CREATE TABLE plus its indexes, prepared before execution), and
 * MySQL-equivalent SQLSTATE codes on failure (see WireDatabaseDialectPgsql::mysqlException()).
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 */

class WireDatabasePgsqlStatement extends WireDatabasePDOStatement {

	/**
	 * This class records schema changes itself, since its deferred statements bypass parent::execute()
	 * and its follow-up statements run after it
	 *
	 * @var bool
	 *
	 */
	protected $recordsSchemaItself = true;

	/**
	 * Multiple translated statements to execute (atomically) when execute() is called
	 *
	 * @var array
	 *
	 */
	protected $deferredStatements = [];

	/**
	 * Exception to throw at execute() (MySQL reports unknown tables/columns at execute rather than prepare)
	 *
	 * @var \PDOException|null
	 *
	 */
	protected $deferredException = null;

	/**
	 * Were any values bound? (not supported with deferred statements)
	 *
	 * @var bool
	 *
	 */
	protected $hasBoundValues = false;

	/**
	 * Statements to run after a successful execute()
	 *
	 * @var array
	 *
	 */
	protected $followUpStatements = [];

	/**
	 * MySQL-style error info of the last failed execute(), when its error was mapped
	 *
	 * @var array|null
	 *
	 */
	protected $mysqlErrorInfo = null;

	/**
	 * @param array $statements
	 *
	 */
	public function setDeferredStatements(array $statements) {
		$this->deferredStatements = $statements;
	}

	/**
	 * @param \PDOException $e
	 *
	 */
	public function setDeferredException(\PDOException $e) {
		$this->deferredException = $e;
	}

	public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR): bool {
		$this->hasBoundValues = true;
		return parent::bindValue($parameter, $value, $data_type);
	}

	public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null): bool {
		$this->hasBoundValues = true;
		return parent::bindParam($parameter, $variable, $data_type, $length, $driver_options);
	}

	public function execute($input_parameters = null): bool {
		if($this->deferredException) throw $this->deferredException;
		if(count($this->deferredStatements)) {
			if($this->hasBoundValues || is_array($input_parameters)) {
				throw new \PDOException('Bound parameters are not supported for SQL that translates to multiple PostgreSQL statements');
			}
			/** @var WireDatabaseDialectPgsql $dialect */
			$dialect = $this->database->dialect();
			$dialect->execStatements($this->database->pdo(), $this->deferredStatements);
			$this->recordSchema();
			return true;
		}
		/** @var WireDatabaseDialectPgsql $dialect */
		$dialect = $this->database->dialect();
		$pdo = $this->database->pdo();
		$this->mysqlErrorInfo = null;
		// a failed statement aborts a PostgreSQL transaction; a savepoint keeps the transaction
		// usable afterwards, as it is on MySQL, for code that catches the exception and carries on
		$savepoint = $dialect->savepointBegin($pdo);
		try {
			$result = parent::execute($input_parameters);
			foreach($this->followUpStatements as $sql) $pdo->exec($sql);
			$dialect->savepointRelease($pdo, $savepoint);
			if($result) $this->recordSchema(); // only once the follow-up statements have also succeeded
			return $result;
		} catch(\PDOException $e) {
			$dialect->savepointRollback($pdo, $savepoint);
			$dialect->logQueryError($this->queryString, '', $e);
			$mapped = WireDatabaseDialectPgsql::mysqlException($e);
			if($mapped !== $e) $this->mysqlErrorInfo = $mapped->errorInfo;
			throw $mapped;
		}
	}

	/**
	 * Set statements without parameters to run after this one executes (i.e. sequence moves after an INSERT)
	 *
	 * @param array $statements
	 *
	 */
	public function setFollowUpStatements(array $statements) {
		$this->followUpStatements = $statements;
	}

	/**
	 * MySQL's SQLSTATE after a failed execute(), as the exception reports it
	 *
	 * WireDatabasePDO::execute() checks it for 42S22 to repair missing columns (i.e. language columns).
	 *
	 * @return string|null
	 *
	 */
	#[\ReturnTypeWillChange]
	public function errorCode() {
		if($this->deferredException) return $this->deferredException->getCode();
		if($this->mysqlErrorInfo !== null) return $this->mysqlErrorInfo[0];
		return parent::errorCode();
	}

	/**
	 * MySQL-style error info after a failed execute(): [ SQLSTATE, MySQL error number, message ]
	 *
	 * @return array
	 *
	 */
	#[\ReturnTypeWillChange]
	public function errorInfo() {
		if($this->deferredException) return $this->deferredException->errorInfo;
		if($this->mysqlErrorInfo !== null) return $this->mysqlErrorInfo;
		return parent::errorInfo();
	}
}
