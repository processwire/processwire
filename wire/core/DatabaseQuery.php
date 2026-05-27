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
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @property array $where
 * @property array $bindValues
 * @property array $bindKeys
 * @property array $bindOptions
 * @property string $query
 * @property string $sql
 * 
 * @method $this where($sql, array $params = array())
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
	 * @var array
	 *
	 */
	protected $bindKeys = array();

	/**
	 * Method names for building DB queries
	 * 
	 * @var array
	 * 
	 */
	protected $queryMethods = array();

	/**
	 * @var int
	 * 
	 */
	protected $instanceNum = 0;

	/**
	 * @var int
	 * 
	 */
	protected $keyNum = 0;

	/**
	 * @var array
	 * 
	 */
	protected $bindOptions = array(
		'prefix' => 'pw', // prefix for auto-generated global keys
		'suffix' => 'X', // 1-character suffix for auto-generated keys
		'global' => false // globally unique among all bind keys in all instances?
	);
	
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
		$this->addQueryMethod('where', " \nWHERE ", " \nAND ");
		parent::__construct();
	}

	/**
	 * Add a query method
	 * 
	 * #pw-internal
	 * 
	 * @param string $name
	 * @param string $prepend Prepend first statement with this
	 * @param string $split Split multiple statements with this
	 * @param string $append Append this to last statement (if needed)
	 * @since 3.0.157
	 * 
	 */
	protected function addQueryMethod($name, $prepend = '', $split = '', $append = '') {
		$this->queryMethods[$name] = array($prepend, $split, $append);
		$this->set($name, array());
	}
	
	/**
	 * Get or set a bind option
	 *
	 * @param string|bool $optionName One of 'prefix' or 'global', boolean true to get/set all 
	 * @param null|int|string|array $optionValue Omit when getting, Specify option value to set, or array when setting all
	 * @return string|int|array
	 * @since 3.0.157
	 *
	 */
	public function bindOption($optionName, $optionValue = null) {
		if($optionName === true) {
			if(is_array($optionValue)) $this->bindOptions = array_merge($this->bindOptions, $optionValue);
			return $this->bindOptions;
		} else if($optionValue !== null) {
			$this->bindOptions[$optionName] = $optionValue;
		}
		return isset($this->bindOptions[$optionName]) ? $this->bindOptions[$optionName] : null;
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
		$this->bindKeys[$key] = $key;
		if($type !== null) $this->setBindType($key, $type);
		return $this; 
	}

	/**
	 * Bind value and get unique key that refers to it in one step
	 * 
	 * @param string|int|float $value
	 * @param null|int|string $type
	 * @return string
	 * @since 3.0.157
	 * 
	 */
	public function bindValueGetKey($value, $type = null) {
		$key = $this->getUniqueBindKey(array('value' => $value)); 
		$this->bindValue($key, $value, $type);
		return $key;
	}
	
	/**
	 * Get or set multiple parameter values
	 *
	 * #pw-internal
	 *
	 * @param array|null $bindValues Omit to get or specify array to set
	 * @return $this|array Returns array when getting or $this when setting
	 * @since 3.0.156
	 *
	 */
	public function bindValues($bindValues = null) {
		if(is_array($bindValues)) {
			foreach($bindValues as $key => $value) {
				$this->bindValue($key, $value);
			}
			return $this;
		} else {
			return $this->bindValues;
		}
	}

	/**
	 * Set bind type
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @param int|string $type
	 * 
	 */
	public function setBindType($key, $type) {
		
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
	 * Get or set all bind types
	 * 
	 * #pw-internal
	 * 
	 * @param array|null $bindTypes Omit to get, or specify associative array of [ ":bindKey" => int ] to set
	 * @return array|$this Returns array when getting or $this when setting
	 * @since 3.0.157
	 * 
	 */
	public function bindTypes($bindTypes = null) {
		if(is_array($bindTypes)) {
			$this->bindTypes = array_merge($this->bindTypes, $bindTypes); // set
			return $this;
		} 
		return $this->bindTypes; // get
	}

	/**
	 * Get a unique key to use for bind value
	 * 
	 * Note if you given a `key` option, it will only be used if it is determined unique,
	 * otherwise it’ll auto-generate one. When using your specified key, it is the only
	 * option that applies, unless it is not unique and the method has to auto-generate one.
	 * 
	 * @param array $options 
	 *  - `key` (string): Preferred bind key, or omit (blank) to auto-generate (digit only keys not accepted)
	 *  - `value` (string|int): Value to use as part of the generated key
	 *  - `prefix` (string): Prefix to override default
	 *  - `global` (bool): Require globally unique among all instances?
	 * @return string Returns bind key/name in format ":name" (with leading colon)
	 * @since 3.0.156
	 * 
	 */
	public function getUniqueBindKey(array $options = array()) {
		
		if(empty($options['key'])) {
			// auto-generate key
			$key = ':';
			$prefix = (isset($options['prefix']) ? $options['prefix'] : $this->bindOptions['prefix']);
			$suffix = isset($option['suffix']) && $options['suffix'] ? $options['suffix'] : $this->bindOptions['suffix'];
			$value = isset($options['value']) ? $options['value'] : null;
			$global = isset($options['global']) ? $options['global'] : $this->bindOptions['global'];
			
			if($global) $key .= $prefix . $this->instanceNum;
			
			if($value !== null) {
				if(is_int($value)) {
					$key .= "i";
				} else if(is_string($value)) {
					$key .= "s";
				} else if(is_array($value)) {
					$key .= "a";
				} else {
					$key .= "o";
				}
			} else if($prefix && !$global) {
				$key .= $prefix;
			} else {
				$key .= "v";
			}
			
			$n = 0;
			$k = $key;
			$key = $k . '0' . $suffix;
			
			while(isset($this->bindKeys[$key]) && ++$n) {
				$key = $k . $n . $suffix;
			}
			
		} else {
			// provided key, make sure it is valid and unique (this part is not typically used)
			$key = ltrim($options['key'], ':') . 'X';
			if(!ctype_alnum(str_replace('_', '', $key))) $key = $this->wire()->database->escapeCol($key);
			if(empty($key) || ctype_digit($key[0]) || isset($this->bindKeys[":$key"])) {
				// if key is not valid, then auto-generate one instead
				unset($options['key']);
				$key = $this->getUniqueBindKey($options);
			} else {
				$key = ":$key";
			}
		}
		
		$this->bindKeys[$key] = $key;
		
		return $key;
	}

	/**
	 * Get bind values, with options
	 * 
	 * - If given a \PDOStatement or DatabaseQuery, it is assumed to be the `query` option. 
	 * - When copying, you may prefer to use the copyBindValuesTo() method instead (more readable).
	 * 
	 * Note: The $options argument was added in 3.0.156, prior to this it was a $method argument, 
	 * which was never used so has been removed. 
	 * 
	 * @param string|\PDOStatement|DatabaseQuery|array $options Optionally specify an option:
	 *  - `query` (\PDOStatement|DatabaseQuery): Copy bind values to this query object (default=null)
	 *  - `count` (bool): Get a count of values rather than array of values (default=false) 3.0.157+
	 *  - `inSQL` (string): Only get bind values referenced in this given SQL statement
	 * @return array|int Returns one of the following:
	 *  - Associative array in format [ ":column" => "value" ] where each "value" is int, string or NULL. 
	 *  - if `count` option specified as true then it returns a count of values instead. 
	 * 
	 */
	public function getBindValues($options = array()) {
		
		$defaults = array(
			'query' => is_object($options) ? $options : null, 
			'count' => false,
			'inSQL' => '', 
		);
		
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$query = $options['query'];
		$bindValues = $this->bindValues;
		
		if(!empty($options['inSQL'])) {
			foreach(array_keys($bindValues) as $bindKey) {
				if(strpos($options['inSQL'], $bindKey) === false) {
					unset($bindValues[$bindKey]);
				} else if(!preg_match('/' . $bindKey . '\b/', $options['inSQL'])) {
					unset($bindValues[$bindKey]);
				}
			}
		}
	
		if(is_object($query)) {
			if($query instanceof \PDOStatement) {
				foreach($bindValues as $k => $v) {
					$type = isset($this->bindTypes[$k]) ? $this->bindTypes[$k] : $this->pdoParamType($v);
					$query->bindValue($k, $v, $type);
				}
			} else if($query instanceof DatabaseQuery && $query !== $this) {
				$query->bindValues($bindValues);
				$query->bindTypes($this->bindTypes);
			}
		}
		
		return $options['count'] ? count($bindValues) : $bindValues;	
	}

	/**
	 * Copy bind values from this query to another given DatabaseQuery or \PDOStatement
	 * 
	 * This is a more readable interface to the getBindValues() method and does the same 
	 * thing as passing a DatabaseQuery or PDOStatement to the getBindValues() method. 
	 * 
	 * @param DatabaseQuery|\PDOStatement $query
	 * @param array $options Additional options
	 *  - `inSQL` (string): Only copy bind values that are referenced in given SQL string
	 * @return int Number of bind values that were copied
	 * @since 3.0.157
	 * 
	 */
	public function copyBindValuesTo($query, array $options = array()) {
		$options['query'] = $query;
		if(!isset($options['count'])) $options['count'] = true;
		return $this->getBindValues($options);
	}

	/**
	 * Copy queries from this DatabaseQuery to another DatabaseQuery 
	 * 
	 * If you want to copy bind values you should also call copyBindValuesTo($query) afterwards.
	 * 
	 * @param DatabaseQuery $query Query to copy data to
	 * @param array $methods Optionally specify the names of methods to copy, otherwise all are copied
	 * @return int Total items copied
	 * @since 3.0.157
	 * 
	 */
	public function copyTo(DatabaseQuery $query, array $methods = array()) {
		
		$numCopied = 0;
		if($query === $this) return 0;
		if(!count($methods)) $methods = array_keys($this->queryMethods); 
		
		foreach($methods as $method) {
			if($method === 'bindValues') continue;
			$fromValues = $this->$method; // array
			if(!is_array($fromValues)) continue; // nothing to import
			$toValues = $query->$method; 
			if(!is_array($toValues)) continue; // query does not have this method
			$query->set($method, array_merge($toValues, $fromValues)); 
			$numCopied += count($fromValues);
		}
		
		return $numCopied;
	}
	
	/**
	 * Enables calling the various parts of a query as functions for a fluent interface.
	 * 
	 * Examples (all in context of DatabaseQuerySelect): 
	 * ~~~~~
	 * $query->select("id")->from("mytable")->orderby("name"); 
	 * ~~~~~
	 * To bind one or more named parameters, specify associative array as second argument: 
	 * ~~~~~
	 * $query->where("name=:name", [ ':name' => $page->name ]); 
	 * ~~~~~
	 * To bind one or more implied parameters, use question marks and specify regular array:
	 * ~~~~~
	 * $query->where("name=?, id=?", [ $page->name, $page->id ]);
	 * ~~~~~
	 * When there is only one implied parameter, specifying an array is optional:
	 * ~~~~~
	 * $query->where("name=?", $page->name); 
	 * ~~~~~
	 * 
	 * The "select" or "where" methods above may be any method supported by the class. 
	 * Implied parameters (using "?") was added in 3.0.157. 
	 * 
	 * @param string $method
	 * @param array $arguments
	 * @return $this
	 *
	 */
	public function __call($method, $arguments) {
		$args = &$arguments;
		
		// if(!$this->has($method)) return parent::__call($method, $args);
		if(!isset($this->queryMethods[$method])) return parent::__call($method, $args);
		if(!count($args)) return $this;
		$curValue = $this->get($method);
		if(!is_array($curValue)) $curValue = array();
		$value = $args[0];
		
		if($value instanceof DatabaseQuery) {
			// if we've been given another DatabaseQuery, load from its $method
			// note that if using bindValues you should also copy them separately
			// behavior deprecated in 3.l0.157+, please use the copyTo() method instead
			$query = $value;
			$value = $query->$method; // array
			if(!is_array($value) || !count($value)) return $this; // nothing to import
			
		} else if(is_string($value)) {
			// value is SQL string, number or array
			$params = isset($args[1]) ? $args[1] : null;
			if($params !== null && !is_array($params)) $params = array($params);
			if(is_array($params) && count($params)) $value = $this->methodBindValues($value, $params);
			
		} else if(!empty($args[1])) {
			throw new WireException("Argument error in $this::$method('string required here when using bind values', [ bind values ])"); 
		}
		
		if(is_array($value)) {
			$curValue = array_merge($curValue, $value);
		} else {
			$curValue[] = trim("$value", ", ");
		}
		
		$this->set($method, $curValue); 
		
		return $this; 
	}

	/**
	 * Setup bind params for the given SQL provided to method call
	 * 
	 * This is only used when params are provided as part of a method call like: 
	 * ~~~~~
	 * $query->where("foo=:bar", [ ":bar" => "baz" ]); // named
	 * $query->where("foo=?", [ "baz" ]);  // implied
	 * ~~~~~
	 * 
	 * #pw-internal
	 * 
	 * @param string $sql
	 * @param array $values Bind values
	 * @return string
	 * @throws WireException
	 * 
	 */
	protected function methodBindValues($sql, array $values) {
		
		$numImplied = 0;
		$numNamed = 0;
		$_sql = $sql;
		
		if(!is_string($sql)) {
			throw new WireException('methodBindValues requires a string for $sql argument');
		}
		
		foreach($values as $name => $value) {
			if(is_int($name)) {
				// implied parameter 
				$numImplied++;
				if(strpos($sql, '?') === false) {
					throw new WireException("No place for given param $name in: $_sql"); 
				}
				do {
					$name = $this->getUniqueBindKey(array('value' => $value));
				} while(strpos($sql, $name) !== false); // highly unlikely, but just in case
				list($a, $b) = explode('?', $sql, 2);
				$sql = $a . $name . $b;
				
			} else {
				// named parameter
				$numNamed++;
				if(strpos($name, ':') !== 0) $name = ":$name";
				if(strpos($sql, $name) === false) {
					throw new WireException("Param $name not found in: $_sql"); 
				}
			}
			$this->bindValue($name, $value);
		}
		
		if($numImplied && strpos($sql, '?') !== false) {
			throw new WireException("Missing implied “?” param in: $_sql"); 
		} else if($numImplied && $numNamed) {
			throw new WireException("You may not mix named and implied params in: $_sql"); 
		}
		
		return $sql;
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
		
		if($key === 'query' || $key === 'sql') {
			return $this->getQuery();
		} else if($key === 'bindValues') {
			return $this->bindValues;
		} else if($key === 'bindOptions') {
			return $this->bindOptions;
		} else if($key === 'bindKeys') {
			return $this->bindKeys;
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
	 * @deprecated
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
	 * @return string
	 *
	 */
	abstract public function getQuery();

	/**
	 * Get SQL query with bind params populated for debugging purposes (not to be used as actual query)
	 * 
	 * @return string
	 * 
	 */
	public function getDebugQuery() {
		$sql = $this->getQuery();
		$suffix = $this->bindOptions['suffix'];
		$database = $this->wire()->database;
		foreach($this->bindValues as $bindKey => $bindValue) {
			if(is_string($bindValue)) $bindValue = $database->quote($bindValue);
			if($bindKey[strlen($bindKey)-1] === $suffix) {
				$sql = strtr($sql, array($bindKey => $bindValue));
			} else {
				$sql = preg_replace('/' . $bindKey . '\b/', $bindValue, $sql);
			}
		}
		return $sql;
	}

	/**
	 * Return generated SQL for entire query or specific method
	 *
	 * @param string $method Optionally specify method name to get SQL for
	 * @return string
	 * @since 3.0.157
	 *
	 */
	public function getSQL($method = '') {
		return $method ? $this->getQueryMethod($method) : $this->getQuery();
	}

	/**
	 * Return the generated SQL for specific query method
	 *
	 * @param string $method Specify method name to get SQL for, or blank string for entire query
	 * @return string
	 * @since 3.0.157
	 *
	 */
	public function getQueryMethod($method) {
		
		if(!$method) return $this->getQuery();
		if(!isset($this->queryMethods[$method])) return '';
		
		$methodName = 'getQuery' . ucfirst($method);
		
		if(method_exists($this, $methodName)) {
			$sql = $this->$methodName();
		} else {
			list($prepend, $split, $append) = $this->queryMethods[$method];
			$values = $this->$method;
			if(!is_array($values)) return ''; 
			foreach($values as $key => $value) {
				if(!strlen(trim($value))) unset($values[$key]); // remove any blank values
			}
			if(!count($values)) return '';
			$sql = trim(implode($split, $values)); 
			if(!strlen($sql) || $sql === trim($split)) return '';
			$sql = $prepend . $sql . $append;
		}
		
		return $sql;	
	}

	/**
	 * Get the WHERE portion of the query
	 * 
	protected function getQueryWhere() {
		$where = $this->where; 
		if(!count($where)) return '';
		$sql = "\nWHERE " . implode(" \nAND ", $where)  . " ";
		return $sql;
	}
	 */

	/**
	 * Prepare and return a PDOStatement
	 * 
	 * @return \PDOStatement
	 * 
	 */
	public function prepare() {
		$query = $this->wire()->database->prepare($this->getQuery()); 
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
	 * @param array $options
	 *  - `throw` (bool): Throw exceptions? (default=true)
	 *  - `maxTries` (int): Max times to retry if connection lost during query. (default=3)
	 *  - `returnQuery` (bool): Return PDOStatement query? If false, returns bool result of execute. (default=true)
	 * @return \PDOStatement|bool
	 * @throws WireDatabaseQueryException|\PDOException
	 *
	 */
	public function execute(array $options = array()) {
		
		$defaults = array(
			'throw' => true, 
			'maxTries' => 3, 
			'returnQuery' => true,
		);
	
		$options = array_merge($defaults, $options);
		$numTries = 0;
		
		do {
			$retry = false;
			$exception = null;
			$result = false;
			$query = null;
			
			try {
				$query = $this->prepare();
				$result = $query->execute();
			} catch(\PDOException $e) {
				$msg = $e->getMessage();
				$code = (int) $e->getCode();
				$retry = $code === 2006 || stripos($msg, 'MySQL server has gone away') !== false;
				if($retry && $numTries < $options['maxTries']) {
					$this->wire()->database->closeConnection(); // note: it reconnects automatically
					$numTries++;
				} else {
					$exception = $e;
					$retry = false;
				}
			}
		} while($retry);
		
		if($exception && $options['throw']) {
			if($this->wire()->config->allowExceptions) throw $exception; // throw original
			WireException([ 'class' => 'WireDatabaseQueryException', 'previous' => $exception ]); 
		}
		
		return $options['returnQuery'] ? $query : $result;
	}

}
