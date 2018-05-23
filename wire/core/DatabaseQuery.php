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
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
	 * Index of bound values per originating method
	 * 
	 * Indexed by originating method, with values as the bound parameter names as in $bindValues.
	 * This is populated by the setupBindValues() method
	 * 
	 * @var array 
	 * 
	 */
	protected $bindIndex = array();

	/**
	 * Bind a parameter value
	 * 
	 * @param string $key Parameter name
	 * @param mixed $value Parameter value
	 * @return $this
	 * 
	 */
	public function bindValue($key, $value) {
		$this->bindValues[$key] = $value; 
		return $this; 
	}

	/**
	 * Get bound parameter values, optionally for a specific method call
	 * 
	 * @param string $method
	 * @return array
	 * 
	 */
	public function getBindValues($method = '') {
		if(empty($method)) return $this->bindValues;
		if(!isset($this->bindIndex[$method])) return array();
		$names = $this->bindIndex[$method];
		$values = array();
		foreach($names as $name) {
			$values[$name] = $this->bindValues[$name];
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
	 *   $query->where("name=:name", array(':name' => $page->name)); 
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
		$curValue = $this->get($method); 
		$value = $args[0]; 
		if(empty($value)) return $this;
		if(is_object($value) && $value instanceof DatabaseQuery) {
			// if we've been given another DatabaseQuery, load from it's $method
			$query = $value;
			$value = $query->$method; 
			if(!is_array($value) || !count($value)) return $this; // nothing to import
			$params = $query->getBindValues($method);
		} else {
			$params = isset($args[1]) && is_array($args[1]) ? $args[1] : null;
		}
		if(!empty($params)) {
			$value = $this->setupBindValues($value, $params, $method);
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
	 * Setup bound parameters for the given query, returning an updated $value if any renames needed to be made
	 * 
	 * @param string|array $sql
	 * @param array $params
	 * @param string $method Method name that the bound values are for
	 * @return string
	 * 
	 */
	public function setupBindValues($sql, array $params, $method) {
		foreach($params as $name => $value) {
			if(strpos($name, ':') !== 0) $name = ":$name";
			$newName = $name;
			$n = 0;
			while(isset($this->bindValues[$newName])) {
				$newName = $name . (++$n);
			}
			if($n) {
				if(is_array($sql)) {
					foreach($sql as $k => $v) {
						if(strpos($v, $name) === false) continue;
						$sql[$k] = preg_replace('/' . $name . '\b/', $newName, $v);
					}
				} else {
					$sql = preg_replace('/' . $name . '\b/', $newName, $sql);
				}
				$name = $newName;
			}
			$this->bindValue($name, $value);
			if(!isset($this->bindIndex[$method])) $this->bindIndex[$method] = array();
			$this->bindIndex[$method][] = $name;
		}
		return $sql;	
	}

	public function __set($key, $value) {
		if(is_array($this->$key)) $this->__call($key, array($value)); 
	}

	public function __get($key) {
		if($key == 'query') return $this->getQuery();
			else if($key == 'bindValues') return $this->bindValues;	
			else if($key == 'bindIndex') return $this->bindIndex;
			else return parent::__get($key); 
	}

	/**
	 * Merge the contents of current query with another (experimental/incomplete)
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
			$query->bindValue($key, $value); 
		}
		return $query; 
	}

	/**
	 * Execute the query with the current database handle
	 * 
	 * @return \PDOStatement
	 * @throws WireException|\Exception|\PDOException
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
			throw new WireException($msg); // throw WireException
		}
		return $query;
	}

}

