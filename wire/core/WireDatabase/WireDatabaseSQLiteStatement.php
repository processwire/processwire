<?php namespace ProcessWire;

/**
 * ProcessWire SQLite PDO statement
 *
 * PDO's SQLite driver always returns 0 from rowCount() for SELECT statements, whereas
 * MySQL returns the number of rows in the (buffered) result set. ProcessWire and its
 * modules commonly rely on the MySQL behavior, so this class emulates it by running a
 * COUNT(*) query with the same bound parameters when rowCount() is called on a SELECT.
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-internal
 *
 */

class WireDatabaseSQLiteStatement extends WireDatabasePDOStatement {

	/**
	 * This class records schema changes itself, since its deferred statements bypass parent::execute()
	 *
	 * @var bool
	 *
	 */
	protected $recordsSchemaItself = true;

	/**
	 * Bound values, indexed by parameter name or position
	 *
	 * @var array
	 *
	 */
	protected $boundValues = [];

	/**
	 * Snapshot of bound values at the last successful execute(), used by rowCount()
	 *
	 * @var array
	 *
	 */
	protected $executedValues = [];

	/**
	 * Multiple translated statements to execute (atomically) when execute() is called
	 *
	 * @var array
	 *
	 */
	protected $deferredStatements = [];

	/**
	 * Number of rows affected by deferred statements
	 *
	 * @var int|null
	 *
	 */
	protected $deferredRowCount = null;

	/**
	 * Executed SELECT statements whose cursors may still be open (WeakReference when available)
	 *
	 * @var array Indexed by spl_object_id()
	 *
	 */
	protected static $active = [];

	/**
	 * Close the cursors of all executed SELECT statements that may still be open
	 *
	 * SQLite cannot drop (or rebuild) a table while a statement reading it is unfinished, which is
	 * common with PDO (i.e. after fetching one row). MySQL buffers results, so has no such issue.
	 * Called when a statement fails with SQLITE_LOCKED, before retrying it.
	 *
	 * @param WireDatabaseSQLiteStatement|null $except Statement to leave open
	 * @return int Number of cursors closed
	 *
	 */
	public static function closeActive($except = null) {
		$qty = 0;
		foreach(self::$active as $id => $ref) {
			$statement = $ref instanceof \WeakReference ? $ref->get() : $ref;
			if(!$statement || $statement === $except) continue;
			$statement->closeCursor();
			$qty++;
		}
		self::$active = [];
		if($except) self::$active[spl_object_id($except)] = self::weakRef($except);
		return $qty;
	}

	/**
	 * @param WireDatabaseSQLiteStatement $statement
	 * @return \WeakReference|WireDatabaseSQLiteStatement
	 *
	 */
	protected static function weakRef($statement) {
		return class_exists('\\WeakReference', false) ? \WeakReference::create($statement) : $statement;
	}

	/**
	 * Is given exception SQLITE_LOCKED (i.e. table in use by an unfinished statement)?
	 *
	 * @param \PDOException $e
	 * @return bool
	 *
	 */
	public static function isLockedException(\PDOException $e) {
		return isset($e->errorInfo[1]) && (int) $e->errorInfo[1] === 6;
	}

	/**
	 * Remember this statement as one that may have an open cursor
	 *
	 * Statements created by PDO::query() do not call this class's execute() method, so they
	 * have to be registered separately (see WireDatabaseDialectSQLite::queryStatement).
	 *
	 */
	public function setActive() {
		if(count(self::$active) > 500) self::$active = array_slice(self::$active, -250, null, true);
		self::$active[spl_object_id($this)] = self::weakRef($this);
	}

	/**
	 * @return bool
	 *
	 */
	public function closeCursor(): bool {
		unset(self::$active[spl_object_id($this)]);
		return parent::closeCursor();
	}

	/**
	 * Cached row count for last execute(), or null if not yet determined
	 *
	 * @var int|null
	 *
	 */
	protected $selectRowCount = null;

	/**
	 * Exception from prepare() to throw at execute(), to match MySQL behavior
	 *
	 * @var \PDOException|null
	 *
	 */
	protected $deferredException = null;

	/**
	 * Set exception that occurred during prepare(), to be thrown on execute()
	 *
	 * SQLite reports unknown tables/columns when preparing, whereas MySQL (with emulated
	 * prepares) reports them when executing.
	 *
	 * @param \PDOException $e
	 *
	 */
	public function setDeferredException(\PDOException $e) {
		$this->deferredException = $e;
	}

	/**
	 * Set multiple translated statements to execute (atomically) when execute() is called
	 *
	 * Used when one MySQL statement translates to multiple SQLite statements, so that
	 * nothing is executed at prepare() time.
	 *
	 * @param array $statements
	 *
	 */
	public function setDeferredStatements(array $statements) {
		$this->deferredStatements = $statements;
	}

	/**
	 * @return string|null
	 *
	 */
	public function errorCode(): ?string {
		if($this->deferredException) return $this->deferredException->getCode();
		return parent::errorCode();
	}

	/**
	 * @return array
	 *
	 */
	public function errorInfo(): array {
		if($this->deferredException) return $this->deferredException->errorInfo;
		return parent::errorInfo();
	}

	/**
	 * @param string|int $parameter
	 * @param mixed $value
	 * @param int $data_type
	 * @return bool
	 *
	 */
	public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR): bool {
		if(!$this->usesParameter($parameter)) return true;
		$this->boundValues[$parameter] = [$value, $data_type];
		if($this->deferredException) return true;
		return parent::bindValue($parameter, $value, $data_type);
	}

	/**
	 * Does the SQL use given parameter?
	 *
	 * A value bound to a named parameter the SQL does not use is ignored, as MySQL does, rather than failing the
	 * execute. DatabaseQuery binds all of its values, and a query built from another (i.e. a count of a query
	 * with fulltext scores) can leave some of them out.
	 *
	 * @param string|int $parameter
	 * @return bool
	 *
	 */
	protected function usesParameter($parameter) {
		if(!is_string($parameter)) return true; // positional
		if(count($this->deferredStatements)) return true; // not prepared yet: execute() says bound parameters are not supported
		$name = $parameter[0] === ':' ? $parameter : ":$parameter";
		return preg_match('/' . preg_quote($name, '/') . '(?![A-Za-z0-9_])/', $this->queryString) === 1;
	}

	/**
	 * @param string|int $parameter
	 * @param mixed $variable
	 * @param int $data_type
	 * @param int|null $length
	 * @param mixed $driver_options
	 * @return bool
	 *
	 */
	public function bindParam($parameter, &$variable, $data_type = \PDO::PARAM_STR, $length = null, $driver_options = null): bool {
		if(!$this->usesParameter($parameter)) return true;
		$this->boundValues[$parameter] = [&$variable, $data_type];
		if($this->deferredException) return true;
		return parent::bindParam($parameter, $variable, $data_type, (int) $length, $driver_options);
	}

	/**
	 * @param array|null $input_parameters
	 * @return bool
	 *
	 */
	public function execute($input_parameters = null): bool {
		$this->selectRowCount = null;
		if(is_array($input_parameters)) {
			foreach($input_parameters as $key => $value) {
				if(!$this->usesParameter($key)) {
					unset($input_parameters[$key]); // see usesParameter()
					continue;
				}
				if(is_int($key)) $key++; // positional parameters are 1-based in bindValue
				$this->boundValues[$key] = [$value, \PDO::PARAM_STR];
			}
		}
		if($this->deferredException) throw $this->deferredException;
		if(count($this->deferredStatements)) {
			if(count($this->boundValues)) {
				throw new \PDOException('Bound parameters are not supported for SQL that translates to multiple SQLite statements');
			}
			$dialect = $this->database->dialect(); /** @var WireDatabaseDialectSQLite $dialect */
			$this->deferredRowCount = $dialect->execStatements($this->database->pdo(), $this->deferredStatements);
			$this->recordSchema();
			return true;
		}
		// snapshot values (bindParam() binds by reference) so rowCount() counts what was executed
		$this->executedValues = [];
		foreach($this->boundValues as $key => $item) $this->executedValues[$key] = [$item[0], $item[1]];
		try {
			try {
				$result = parent::execute($input_parameters);
			} catch(\PDOException $e) {
				if(!self::isLockedException($e)) throw $e;
				// table in use by an unfinished SELECT: close open cursors and retry once
				$this->closeCursor();
				self::closeActive($this);
				$result = parent::execute($input_parameters);
			}
			if(stripos(ltrim($this->queryString), 'SELECT') === 0 || stripos(ltrim($this->queryString), 'WITH') === 0) {
				if(count(self::$active) > 500) self::$active = array_slice(self::$active, -250, null, true);
				self::$active[spl_object_id($this)] = self::weakRef($this);
			}
			if($result) $this->recordSchema();
			return $result;
		} catch(\PDOException $e) {
			// pdo_sqlite does not reset a statement whose first execute() failed, making later
			// executes fail with "bad parameter or other API misuse"; closeCursor() resets it
			$this->closeCursor();
			if($this->database) $this->database->dialect()->logQueryError($this->queryString, '', $e);
			throw WireDatabaseDialectSQLite::mysqlException($e);
		}
	}

	/**
	 * Returns number of rows affected, or for SELECT the number of rows in the result
	 *
	 * @return int
	 *
	 */
	public function rowCount(): int {
		if($this->selectRowCount !== null) return $this->selectRowCount;
		if($this->deferredRowCount !== null) return $this->deferredRowCount;
		$sql = ltrim($this->queryString);
		$word = strtoupper(substr($sql, 0, 6));
		if($word !== 'SELECT' && strtoupper(substr($sql, 0, 4)) !== 'WITH') {
			return parent::rowCount();
		}
		$pdo = $this->database->pdo();
		// plain PDOStatement so this internal query is not logged or counted as a separate query
		$query = $pdo->prepare('SELECT COUNT(*) FROM (' . rtrim($sql, "; \n\r\t") . ')', [\PDO::ATTR_STATEMENT_CLASS => ['\\PDOStatement']]);
		foreach($this->executedValues as $key => $item) {
			$query->bindValue($key, $item[0], $item[1]);
		}
		$query->execute();
		$this->selectRowCount = (int) $query->fetchColumn();
		$query->closeCursor();
		return $this->selectRowCount;
	}
}
