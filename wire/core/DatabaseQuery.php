<?php namespace ProcessWire;

/**
 * ProcessWire DatabaseQuery
 *
 * Serves as a base class for other DatabaseQuery classes
 *
 * The intention behind these classes is to have a query that can safely
 * be passed between methods and objects that add to it without knowledge
 * of what other methods/objects have done to it. It also means being able
 * to build a complex query without worrying about correct syntax placement.
 * 
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @property array $where
 * @property array $bindValues
 * @property array $bindIndex 
 *
 */
abstract class DatabaseQuery extends WireData {

	/**
	 * Bound parameters of name => value
	 * 
	 * @var array
	 * 
	 */
	protected $bindValues = array();

	/**
	 * Bound parameter types of name => \PDO::PARAM_* type constant
	 * 
	 * Populated only when a type is provided to the bindValue() call
	 * 
	 * @var array
	 * 
	 */
	protected $bindTypes = array();

	/**
	 * Index of bound values per originating method (deprecated)
	 * 
	 * Indexed by originating method, with values as the bound parameter names as in $bindValues.
	 * This is populated by the setupBindValues() method. The purpose of this is for one part of
	 * one query is imported to another, the appropriate bound values are also imported. This is
	 * deprecated because it does not work if values are bound independently of `$q->where()`, etc. 
	 * 
	 * @var array 
	 * 
	 */
	protected $bindIndex = array();
	
	/**
	 * @var int
	 * 
	 */
	protected $instanceNum = 0;

	/**
	 * @var int
	 * 
	 */
	static $uniq = 0;

	/**
	 * @var int
	 * 
	 */
	static $numInstances =  0;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		self::$numInstances++;
		$this->instanceNum = self::$numInstances;
		parent::__construct();
	}

	/**
	 * Bind a parameter value
	 * 
	 * @param string $key Parameter name
	 * @param mixed $value Parameter value
	 * @param null|int|string Optionally specify value type: string, int, bool, null or PDO::PARAM_* constant. 
	 * @return $this
	 * 
	 */
	public function bindValue($key, $value, $type = null) {
		if(strpos($key, ':') !== 0) $key = ":$key";
		$this->bindValues[$key] = $value; 
		if($type !== null) $this->setBindType($key, $type);
		return $this; 
	}
	
	/**
	 * Bind multiple parameter values
	 *
	 * #pw-internal
	 *
	 * @param array $bindValues
	 * @return $this
	 * @since 3.0.156
	 *
	 */
	public function bindValues(array $bindValues) {
		foreach($bindValues as $key => $value) {
			$this->bindValue($key, $value);
		}
		return $this;
	}

	/**
	 * Set bind type
	 * 
	 * @param string $key
	 * @param int|string $type
	 * 
	 */
	protected function setBindType($key, $type) {
		
		if(is_int($type) || ctype_digit("$type")) {
			$this->bindTypes[$key] = (int) $type;
		}
		
		switch(strtolower(substr($type, 0, 3))) {
			case 'str': $type = \PDO::PARAM_STR; break;
			case 'int': $type = \PDO::PARAM_INT; break;
			case 'boo': $type = \PDO::PARAM_BOOL; break;
			case 'nul': $type = \PDO::PARAM_NULL; break;
			default: $type = null;
		}
		
		if($type !== null) $this->bindTypes[$key] = $type;
	}

	/**
	 * Get a unique key to use for bind value
	 * 
	 * @param string $key Preferred bind key or prefix, or omit to auto-generate
	 * @return string Returns bind key/name in format ":name" (with leading colon)
	 * @since 3.0.156
	 * 
	 */
	public function getUniqueBindKey($key = '') {
		$key = ltrim($key, ':');
		if(!ctype_alnum(str_replace('_', '', $key))) {
			$key = $this->wire('database')->escapeCol($key);
		}
		if(!strlen($key) || ctype_digit($key[0])) $key = "pwbk$key";
		do {
			$name = ':' . $key . '_' . $this->instanceNum . '_' . (++self::$uniq);
		} while(isset($this->bindValues[$name])); 
		return $name;
	}

	/**
	 * Get bound parameter values (or populate to given query)
	 * 
	 * - If given a string for $options argument it assumed to be the `method` option. 
	 * - If given a \PDOStatement or DatabaseQuery, it is assumed to be the `query` option. 
	 * 
	 * Note: The $options argument was added in 3.0.156, prior to this it was a $method argument, 
	 * which is the same as the `method` option (string). 
	 * 
	 * @param string|\PDOStatement|DatabaseQuery|array $options Optionally specify an option:
	 *  - `method` (string): Get bind values just for this DatabaseQuery method name (default='') deprecated
	 *  - `query` (\PDOStatement|DatabaseQuery): Populate bind values to this query object (default=null)
	 * @return array Associative array in format [ ":column" => "value" ] where each "value" is int, string or NULL. 
	 * 
	 */
	public function getBindValues($options = array()) {
		
		$defaults = array(
			'method' => is_string($options) ? $options : '',
			'query' => is_object($options) ? $options : null, 
		);
		
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$query = $options['query'];
		$method = $options['method'];
		
		if($method) {
			if(!isset($this->bindIndex[$method])) return array();
			$names = isset($this->bindIndex[$method]) ? $this->bindIndex[$method] : array();
			$values = array();
			foreach($names as $name) {
				$name = ':' . ltrim($name, ':');
				$values[$name] = $this->bindValues[$name];
			}
		} else {
			$values = $this->bindValues;
		}
		
		if($query && $query instanceof \PDOStatement) {
			foreach($values as $k => $v) {
				$type = $this->pdoParamType($v);
				$query->bindValue($k, $v, $type);
			}
		} else if($query && $query instanceof DatabaseQuery && $query !== $this) {
			$query->bindValues($values);
		}
		
		return $values;	
	}

	/**
	 * Enables calling the various parts of a query as functions for a fluent interface.
	 * 
	 * Examples (all in context of DatabaseQuerySelect): 
	 * 
	 *   $query->select("id")->from("mytable")->orderby("name"); 
	 * 
	 * To bind parameters, specify associative array as second argument: 
	 * 
	 *   $query->where("name=:name", [ ':name' => $page->name ]); 
	 * 
	 * To import query/method and bound values from another DatabaseQuery: 
	 * 
	 *   $query->select($anotherQuery); 
	 * 
	 * The "select" may be any method supported by the class. 
	 * 
	 * @param string $method
	 * @param array $args
	 * @return $this
	 *
	 */
	public function __call($method, $args) {
		
		if(!$this->has($method)) return parent::__call($method, $args); 
		if(empty($args[0])) return $this;
		
		$curValue = $this->get($method); 
		$value = $args[0]; 
	
		if(!is_array($curValue)) $curValue = array();
		if(empty($value)) return $this;
		
		if(is_object($value) && $value instanceof DatabaseQuery) {
			// if we've been given another DatabaseQuery, load from its $method
			$query = $value;
			$value = $query->$method; 
			if(!is_array($value) || !count($value)) return $this; // nothing to import
			$params = $query->getBindValues($method);
		} else {
			$params = isset($args[1]) && is_array($args[1]) ? $args[1] : null;
		}
		
		if(!empty($params)) {
			$this->methodBindValues($value, $params, $method);
		}
		
		if(is_array($value)) {
			$curValue = array_merge($curValue, $value);
		} else {
			$curValue[] = trim($value, ", ");
		}
		
		$this->set($method, $curValue); 
		
		return $this; 
	}

	/**
	 * Setup bound parameters for the given SQL provided to method call
	 * 
	 * This is only used when params are provided as part of a method call like: 
	 * $query->where("foo=:bar", [ ":bar" => "baz" ]); 
	 * 
	 * #pw-internal
	 * 
	 * @param string|array $sql
	 * @param array $values Associative array of bound values
	 * @param string $method Method name that the bound values are for
	 * 
	 */
	protected function methodBindValues(&$sql, array $values, $method) {
		
		foreach($values as $name => $value) {
			$name = ':' . ltrim($name, ':');
			
			if(isset($this->bindValues[$name])) {
				// bind key already in use, use a different unique one instead
				$newName = $this->getUniqueBindKey($name); 
				if(is_array($sql)) {
					foreach($sql as $k => $v) {
						if(strpos($v, $name) === false) continue;
						$sql[$k] = preg_replace('/' . $name . '\b/', $newName, $v);
					}
				} else if(strpos($sql, $name) !== false) {
					$sql = preg_replace('/' . $name . '\b/', $newName, $sql);
				}
				$name = $newName;
			}
			
			$this->bindValue($name, $value);
			if(!isset($this->bindIndex[$method])) $this->bindIndex[$method] = array();
			$this->bindIndex[$method][] = $name;
		}
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * 
	 */
	public function __set($key, $value) {
		if(is_array($this->$key)) $this->__call($key, array($value)); 
	}

	/**
	 * @param string $key
	 * @return array|mixed|null
	 * 
	 */
	public function __get($key) {
		
		if($key === 'query') {
			return $this->getQuery();
		} else if($key === 'bindValues') {
			return $this->bindValues;
		} else if($key === 'bindIndex') {
			return $this->bindIndex;
		} 
		
		return parent::__get($key); 
	}

	/**
	 * Merge the contents of current query with another (experimental/incomplete)
	 * 
	 * #pw-internal
	 * 
	 * @internal
	 * @param DatabaseQuery $query
	 * @return $this
	 *
	 */
	public function merge(DatabaseQuery $query) {
		foreach($query as $key => $value) {
			$this->$key = $value; 	
		}
		return $this; 
	}

	/** 
	 * Generate the SQL query based on everything set in this DatabaseQuery object
	 *
	 */
	abstract public function getQuery();

	/**
	 * Get the WHERE portion of the query
	 *
	 */
	protected function getQueryWhere() {
		if(!count($this->where)) return '';
		$where = $this->where; 
		$sql = "\nWHERE " . array_shift($where) . " ";
		foreach($where as $s) $sql .= "\nAND $s ";
		return $sql;
	}

	/**
	 * Prepare and return a PDOStatement
	 * 
	 * @return \PDOStatement
	 * 
	 */
	public function prepare() {
		$query = $this->wire('database')->prepare($this->getQuery()); 
		foreach($this->bindValues as $key => $value) {
			$type = isset($this->bindTypes[$key]) ? $this->bindTypes[$key] : $this->pdoParamType($value);
			$query->bindValue($key, $value, $type); 
		}
		return $query; 
	}

	/**
	 * Get the PDO::PARAM_* type for given value
	 * 
	 * @param string|int|null $value
	 * @return int
	 * 
	 */
	protected function pdoParamType($value) {
		if(is_int($value)) {
			$type = \PDO::PARAM_INT;
		} else if($value === null) {
			$type = \PDO::PARAM_NULL;
		} else {
			$type = \PDO::PARAM_STR;
		}
		return $type;
	}

	/**
	 * Execute the query with the current database handle
	 * 
	 * @return \PDOStatement
	 * @throws WireDatabaseQueryException|\PDOException
	 *
	 */
	public function execute() {
		$database = $this->wire('database');
		try { 
			$query = $this->prepare();
			$query->execute();
		} catch(\Exception $e) {
			$msg = $e->getMessage();
			if(stripos($msg, 'MySQL server has gone away') !== false) $database->closeConnection();
			if($this->wire('config')->allowExceptions) throw $e; // throw original
			throw new WireDatabaseQueryException($msg, $e->getCode(), $e); 
		}
		return $query;
	}

}

