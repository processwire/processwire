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
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
 * @method $this select($sql, array $params = array())
 * @method $this from($sql)
 * @method $this join($sql, array $params = array())
 * @method $this leftjoin($sql, array $params = array())
 * @method $this where($sql, array $params = array())
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
	 * Setup the components of a SELECT query
	 *
	 */
	public function __construct() {
		$this->set('select', array()); 
		$this->set('join', array()); 
		$this->set('from', array()); 
		$this->set('leftjoin', array()); 
		$this->set('where', array()); 
		$this->set('orderby', array()); 
		$this->set('groupby', array()); 
		$this->set('limit', array()); 
		$this->set('comment', ''); 
	}

	/**
	 * Return the resulting SQL ready for execution with the database
 	 *
	 */
	public function getQuery() {

		$sql = 	
			$this->getQuerySelect() . 
			$this->getQueryFrom() . 
			$this->getQueryJoin($this->join, "JOIN") . 
			$this->getQueryJoin($this->leftjoin, "LEFT JOIN") . 
			$this->getQueryWhere() . 
			$this->getQueryGroupby() . 
			$this->getQueryOrderby() . 
			$this->getQueryLimit(); 

		if($this->get('comment') && $this->wire('config')->debug) {
			// NOTE: PDO thinks ? and :str param identifiers in /* comments */ are real params
			// so we str_replace them out of the comment, and only support comments in debug mode
			$comment = str_replace(array('*/', '?', ':'), '', $this->comment); 
			$sql .= "/* $comment */";
		}

		return $sql; 
	}

	/**
	 * Add an 'order by' element to the query
	 *
	 * @param string|array $value
	 * @param bool $prepend Should the value be prepended onto the existing value? default is to append rather than prepend.
	 * 	Note that $prepend is applicable only when you pass this method a string. $prepend is ignored if you pass an array. 
	 * @return $this
	 *
	 */
	public function orderby($value, $prepend = false) {
	
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

	protected function getQuerySelect() {

		$sql = '';
		$select = $this->select; 

		// ensure that an SQL_CALC_FOUND_ROWS request comes first
		while(($key = array_search("SQL_CALC_FOUND_ROWS", $select)) !== false) {
			if(!$sql) $sql = "SELECT SQL_CALC_FOUND_ROWS ";	
			unset($select[$key]); 
		}
		if(!$sql) $sql = "SELECT ";

		// $config->dbCache option for debugging purposes
		if($this->wire('config')->dbCache === false) $sql .= "SQL_NO_CACHE "; 

		foreach($select as $s) $sql .= "$s,";
		$sql = rtrim($sql, ",") . " "; 
		return $sql;
	}

	protected function getQueryFrom() {
		$sql = "\nFROM ";
		foreach($this->from as $s) $sql .= "`$s`,";	
		$sql = rtrim($sql, ",") . " "; 
		return $sql; 
	}

	protected function getQueryJoin(array $join, $type) {
		$sql = '';
		foreach($join as $s) $sql .= "\n$type $s ";
		return $sql;
	}

	protected function getQueryOrderby() {
		if(!count($this->orderby)) return '';
		$sql = "\nORDER BY ";
		foreach($this->orderby as $s) $sql .= "$s,";
		$sql = rtrim($sql, ",") . " ";
		return $sql;
	}

	protected function getQueryGroupby() {
		if(!count($this->groupby)) return '';
		$sql = "\nGROUP BY ";
		$having = array();
		foreach($this->groupby as $s) {
			// if it starts with 'HAVING' then we will determine placement
			// this is a shortcut to combine multiple HAVING statements with ANDs
			if(stripos($s, 'HAVING ') === 0) {
				$having[] = substr($s, 7); 
				continue; 
			}
			$sql .= "$s,";
		}

		if(count($having)) {
			// place in any having statements that weren't placed
			$sql = rtrim($sql, ",") . " HAVING ";
			foreach($having as $n => $h) {
				if($n > 0) $sql .= " AND ";
				$sql .= $h;
				
			}
		}

		$sql = rtrim($sql, ",") . " ";


		return $sql;
	}

	protected function getQueryLimit() {
		if(!count($this->limit)) return '';
		$limit = $this->limit; 
		$sql = "\nLIMIT " . reset($limit) . " ";
		return $sql; 
	}

	
}

