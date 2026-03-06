<?php namespace ProcessWire;
/**
 * ProcessWire DatabaseQuerySelect
 *
 * A wrapper for SELECT SQL queries.
 *
 * The intention behind these classes is to have a query that can safely
 * be passed between methods and objects that add to it without knowledge
 * of what other methods/objects have done to it. It also means being able
 * to build a complex query without worrying about correct syntax placement.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 * 
 * @property array $select
 * @property array $join
 * @property array $from
 * @property array $leftjoin
 * @property array $where
 * @property array $orderby
 * @property array $groupby
 * @property array $limit
 * @property string $comment Comments for query
 * 
 * @method $this select($sql, $params = array())
 * @method $this from($sql)
 * @method $this join($sql, $params = array())
 * @method $this leftjoin($sql, $params = array())
 * @method $this where($sql, $params = array())
 * @method $this groupby($sql)
 * @method $this limit($sql)
 *
 * Below are Properties populated by DatabaseQuerySelect objects created by PageFinder.
 * This is what gets passed to Fieldtype::getMatchQuery() method calls as properties
 * available from the $query argument. 
 * 
 * @property Field $field Field object that is referenced by this query.
 * @property string $group Selector group (for OR-groups) if applicable.
 * @property Selector $selector Selector object referenced by this query.
 * @property Selectors $selectors Original selectors (all) that $selector is part of. 
 * @property DatabaseQuerySelect $parentQuery Parent query object, if applicable.
 *
 */
class DatabaseQuerySelect extends DatabaseQuery {

	/**
	 * DB cache setting from $config
	 * 
	 * @var null|bool
	 * 
	 */
	static $dbCache = null;
	
	/**
	 * Indent level for debug logging
	 * 
	 * @var int 
	 * 
	 */
	protected $indentLevel = 0;
	
	/**
	 * Cached SQL from getQuery()
	 * 
	 * @var null|string
	 * 
	 */
	protected $sql = null;

	/**
	 * Setup the components of a SELECT query
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->addQueryMethod('select', 'SELECT ', ', ');
		$this->addQueryMethod('from', " \nFROM `", '`,`', '` ');
		$this->addQueryMethod('join', " \nJOIN ", " \nJOIN ");
		$this->addQueryMethod('leftjoin', " \nLEFT JOIN ", " \nLEFT JOIN ");
		$this->addQueryMethod('orderby', " \nORDER BY ", ",");
		$this->addQueryMethod('groupby', " \nGROUP BY ", ',');
		$this->addQueryMethod('limit', " \nLIMIT ", ',');
		$this->set('comment', ''); 
	}
	
	public function __call($method, $arguments) {
		$this->sql = null;
		return parent::__call($method, $arguments);
	}
	
	public function set($key, $value) {
		if(isset($this->queryMethods[$key])) $this->sql = null;
		return parent::set($key, $value);
	}
	
	public function __set($key, $value) {
		if(isset($this->queryMethods[$key])) $this->sql = null;
		return parent::__set($key, $value);
	}

	/**
	 * Return the resulting SQL ready for execution with the database
 	 *
	 */
	public function getQuery() {
		if($this->sql !== null) return $this->sql;
		$debug = $this->wire()->config->debug;

		$sql = trim(	
			$this->getQueryMethod('select') . 
			$this->getQueryMethod('from') . 
			$this->getQueryMethod('join') . 
			$this->getQueryMethod('leftjoin') . 
			$this->getQueryMethod('where') . 
			$this->getQueryMethod('groupby') . 
			$this->getQueryMethod('orderby') . 
			$this->getQueryMethod('limit')
		) . ' ';
		
		if($debug && $this->get('comment')) {
			// NOTE: PDO thinks ? and :str param identifiers in /* comments */ are real params
			// so we str_replace them out of the comment, and only support comments in debug mode
			$comment = str_replace(array('*/', '?', ':'), '', $this->comment); 
			$sql .= "/* $comment */";
		}
		
		if($debug && $this->indentLevel) {
			$indent = str_repeat("\t", $this->indentLevel); 
			$sql = $indent . str_replace("\n", "\n$indent", $sql);
		}
		
		$this->sql = $sql;
			
		return $sql; 
	}

	/**
	 * Add an ORDER BY section to the query
	 *
	 * @param string|array $value
	 * @param bool $prepend Should the value be prepended onto the existing value? default is to append rather than prepend.
	 * 	Note that $prepend is applicable only when you pass this method a string. $prepend is ignored if you pass an array. 
	 * @return $this
	 *
	 */
	public function orderby($value, $prepend = false) {
		
		$this->sql = null;
		
		if($value === null) {
			$this->set('orderby', []); 
			return $this;
		}
	
		if(is_object($value)) {
			if($value instanceof DatabaseQuerySelect) {
				$value = $value->orderby;
			} else {
				// invalid
				return $this;
			}
		}
		$oldValue = $this->get('orderby'); 

		if(is_array($value)) {
			$this->set('orderby', array_merge($oldValue, $value)); 

		} else if($prepend) { 
			array_unshift($oldValue, $value); 
			$this->set('orderby', $oldValue); 

		} else {
			$oldValue[] = $value;
			$this->set('orderby', $oldValue); 
		}

		return $this; 
	}

	/**
	 * Get SELECT portion of SQL 
	 * 
	 * @return string
	 * 
	 */
	protected function getQuerySelect() {
		
		if(self::$dbCache === null) {
			self::$dbCache = $this->wire()->config->dbCache === false ? false : true;
		}

		$select = $this->select; 
		$sql = '';

		// ensure that an SQL_CALC_FOUND_ROWS request comes first
		while(($key = array_search("SQL_CALC_FOUND_ROWS", $select)) !== false) {
			if(!$sql) $sql = "SELECT SQL_CALC_FOUND_ROWS ";	
			unset($select[$key]); 
		}
		if(!$sql) $sql = "SELECT ";
		if(self::$dbCache === false) $sql .= "SQL_NO_CACHE "; 
		
		return $sql . implode(',', $select) . ' ';
	}

	/**
	 * Get GROUP BY section of SQL
	 * 
	 * @return string
	 * 
	 */
	protected function getQueryGroupby() {
		if(!count($this->groupby)) return '';
	
		$sql = [ 'GROUP BY' ];
		$groups = [];
		$haves = [];
		
		foreach($this->groupby as $s) {
			// if it starts with 'HAVING' then we will determine placement
			// this is a shortcut to combine multiple HAVING statements with ANDs
			if(stripos($s, 'HAVING ') === 0) {
				$have = substr($s, 7);
				$haves[] = $have;
			} else {
				$groups[] = $s;
			}
		}
		
		$sql[] = implode(',', $groups);

		if(count($haves)) {
			// place in any having statements that weren't placed
			$sql[] = 'HAVING ' . implode(' AND ', $haves);
		}

		return "\n" . implode(' ', $sql) . " ";
	}

	/**
	 * Get LIMIT section of SQL
	 * 
	 * @return string
	 * 
	 */
	protected function getQueryLimit() {
		if(!count($this->limit)) return '';
		$limit = $this->limit; 
		$limit = reset($limit);
		if(strpos($limit, ',') !== false) {
			list($start, $limit) = explode(',', $limit);
			$start = (int) trim($start);
			$limit = (int) trim($limit); 
			$limit = "$start,$limit";
		} else {
			$limit = (int) $limit;
		}
		return "\nLIMIT $limit ";
	}
	
	/**
	 * Set the indent level for debug logging
	 * 
	 * #pw-internal
	 * 
	 * @param int $level
	 * @since 3.0.257
	 * 
	 */
	public function setIndentLevel($level) {
		$this->indentLevel = (int) $level;
	}
}

