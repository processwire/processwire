<?php namespace ProcessWire;

/**
 * ProcessWire PDO Statement
 *
 * Serves as a wrapper to PHP’s PDOStatement class, purely for debugging purposes.
 * When ProcessWire is not in debug mode, this class is not used at present.
 * 
 * The primary thing this class does is log queries with the bind parameters 
 * populated into the SQL query string, purely for readability purposes. These
 * populated queries are not ever used for actual database queries, just for logs.
 * 
 * Note that this class only tracks bindValue() and does not track bindParam(). 
 *
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireDatabasePDOStatement extends \PDOStatement {

	/** 
	 * @var WireDatabasePDO 
	 * 
	 */
	protected $database = null;

	/**
	 * Debug params in format [ ":param_name" => "param value" ]
	 * 
	 * @var array
	 * 
	 */
	protected $debugParams = array();

	/**
	 * Debug params that require PCRE, in format [ "/:param_name\b/" => "param value" ]
	 * 
	 * @var array
	 * 
	 */
	protected $debugParamsPCRE = array();

	/**
	 * Quantity of debug params
	 * 
	 * @var int
	 * 
	 */
	protected $debugParamsQty = 0;

	/**
	 * Debug note
	 * 
	 * @var string
	 * 
	 */
	protected $debugNote = '';

	/**
	 * Debug mode?
	 * 
	 * @var bool
	 * 
	 */
	protected $debugMode = false;

	/**
	 * Schema change this statement makes, in MySQL syntax (see setSchemaSql())
	 *
	 * @var string|null
	 *
	 */
	protected $schemaSql = null;

	/**
	 * Does a subclass call recordSchema() itself, at its own point of success?
	 *
	 * @var bool
	 *
	 */
	protected $recordsSchemaItself = false;
	
	/**
	 * Construct
	 * 
	 * PDO requires the PDOStatement constructor to be protected for some reason
	 * 
	 * @param WireDatabasePDO $database
	 * 
	 */
	protected function __construct(WireDatabasePDO $database) { 
		$this->database = $database;
		$this->debugMode = $database->debugMode;
	}

	/**
	 * Set debug note
	 * 
	 * @param string $note
	 * 
	 */
	public function setDebugNote($note) {
		$this->debugNote = $note;
	}

	/**
	 * Set a named debug parameter
	 * 
	 * @param string $parameter
	 * @param int|string|null $value
	 * @param int|null $data_type \PDO::PARAM_* type
	 * 
	 */
	public function setDebugParam($parameter, $value, $data_type = null) {
		if($data_type === \PDO::PARAM_INT) {
			$value = (int) $value;
		} else if($data_type === \PDO::PARAM_NULL) {
			$value = 'NULL';
		} else {
			$value = $this->database->quote($value);
		}
		if($parameter[strlen($parameter)-1] !== 'X') {
			// user-specified param name: partial name collisions possible, so use boundary
			$this->debugParamsPCRE['/' . $parameter . '\b/'] = $value;
		} else {
			// auto-generated param name: already protected against partial name collisions
			$this->debugParams[$parameter] = $value;
		}
		$this->debugParamsQty++;
	}

	/**
	 * Bind a value for this statement
	 * 
	 * @param string|int $parameter
	 * @param mixed $value
	 * @param int $data_type
	 * @return bool
	 * 
	 */
	#[\ReturnTypeWillChange] 
	public function bindValue($parameter, $value, $data_type = \PDO::PARAM_STR) {
		$result = parent::bindValue($parameter, $value, $data_type);
		if($this->debugMode && strpos($parameter, ':') === 0) {
			$this->setDebugParam($parameter, $value, $data_type);
		} else {
			// note we do not handle index/question-mark parameters for debugging
		}
		return $result;
	}
	
	/**
	 * Execute prepared statement
	 *
	 * @param array|null $input_parameters
	 * @return bool
	 * @throws \PDOException
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function execute($input_parameters = NULL) {
		if($this->debugMode) {
			$result = $this->executeDebug($input_parameters);
		} else {
			$result = parent::execute($input_parameters);
		}
		if($result && !$this->recordsSchemaItself) $this->recordSchema();
		return $result;
	}

	/**
	 * Set the schema change (in MySQL syntax) that this statement makes, to record when it executes
	 *
	 * #pw-internal
	 *
	 * @param string $sql
	 * @since 3.0.273
	 *
	 */
	public function setSchemaSql($sql) {
		$this->schemaSql = $sql;
	}

	/**
	 * Record this statement's schema change in the schema log, if it makes one
	 *
	 * Called once the statement has executed successfully.
	 *
	 * @since 3.0.273
	 *
	 */
	protected function recordSchema() {
		if($this->schemaSql !== null && $this->database) $this->database->schemaLog()->record($this->schemaSql);
	}

	/**
	 * Execute prepared statement when in debug mode only
	 * 
	 * @param array|null $input_parameters
	 * @return bool
	 * @throws \PDOException
	 * 
	 */
	public function executeDebug($input_parameters = NULL) {
	
		$timer = Debug::startTimer();
		$exception = null;
		
		try {
			$result = parent::execute($input_parameters);
		} catch(\PDOException $e) {
			$exception = $e;
			$result = false;
		}
		
		$timer = Debug::stopTimer($timer, 'ms');
		
		if(!$this->database) {
			if($exception) throw $exception;
			return $result;
		}
		
		if(is_array($input_parameters)) {
			foreach($input_parameters as $key => $value) {
				if(is_string($key)) $this->setDebugParam($key, $value);
			}
		}
	
		$debugNote = trim("$this->debugNote [$timer]");
		if($exception) $debugNote .= ' FAIL SQLSTATE[' . $exception->getCode() . ']';
		
		if($this->debugParamsQty) {
			$sql = $this->queryString;
			if(count($this->debugParams)) {
				$sql = strtr($sql, $this->debugParams); 
			}
			if(count($this->debugParamsPCRE)) {
				$sql = preg_replace(
					array_keys($this->debugParamsPCRE), 
					array_values($this->debugParamsPCRE), 
					$sql
				);
			}
			$this->database->queryLog($sql, $debugNote);
		} else {
			$this->database->queryLog($this->queryString, $debugNote); 
		}
		
		if($exception) throw $exception;
		
		return $result;
	}

}
