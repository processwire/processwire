<?php namespace ProcessWire;

/**
 * ProcessWire DatabaseQuerySelectFulltext
 *
 * A wrapper for SELECT SQL queries using FULLTEXT indexes
 * 
 * Decorates a DatabaseQuerySelect object by providing the WHERE and 
 * ORDER parts for a fulltext query based on the table, field, operator 
 * and value you are searching. 
 *
 * Assumes that you are providing at least the SELECT and FROM portions 
 * of the query. 
 *
 * The intention behind these classes is to have a query that can safely
 * be passed between methods and objects that add to it without knowledge
 * of what other methods/objects have done to it. It also means being able
 * to build a complex query without worrying about correct syntax placement.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 *
 *
 */
class DatabaseQuerySelectFulltext extends Wire {

	/**
	 * Max length that we allow for a query
	 * 
	 */
	const maxQueryValueLength = 500;

	/**
	 * @var DatabaseQuerySelect
	 * 
	 */
	protected $query;

	/**
	 * Keep track of field names used for scores so that the same one isn't ever used more than once
	 * 
	 * @var array
	 * 
	 */
	static $scoreFields = array();

	/**
	 * Construct
	 * 
	 * @param DatabaseQuerySelect $query
	 * 
	 */
	public function __construct(DatabaseQuerySelect $query) {
		$this->query = $query; 
	}

	/**
	 * Escape string for use in a MySQL LIKE
	 * 
	 * @param string $str
	 * @return string
 	 *
	 */
	protected function escapeLIKE($str) {
		return preg_replace('/([%_])/', '\\\$1', $str); 
	}

	/**
	 * Update the query (provided to the constructor) to match the given arguments
	 * 
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string $operator
	 * @param string|int|array $value Value to match. Array value support added 3.0.141 (not used by PageFinder)
	 * @return $this
	 * @throws WireException If given $operator argument is not implemented here
	 * 
	 */
	public function match($tableName, $fieldName, $operator, $value) {
		
		if(is_array($value)) return $this->matchArrayValue($tableName, $fieldName, $operator, $value);

		$database = $this->wire('database');
		$query = $this->query; 
		$value = substr(trim($value), 0, self::maxQueryValueLength); 
		$tableName = $database->escapeTable($tableName); 
		$fieldName = $database->escapeCol($fieldName); 
		$tableField = "$tableName.$fieldName";

		switch($operator) {

			case '=':
			case '!=':
			case '>':
			case '<':
			case '<=':
			case '>=': 
				$v = $database->escapeStr($value); 
				$query->where("$tableField$operator'$v'");
				// @todo, bound values can be used instead for many cases, update to use them like this:
				// $query->where("$tableField$operator:value", array(':value' => $value)); 
				break;	

			case '*=':
				$this->matchContains($tableName, $fieldName, $operator, $value); 
				break;

			case '~=':
			case '!~=':	
				$words = preg_split('/[-\s,]/', $value, -1, PREG_SPLIT_NO_EMPTY); 
				foreach($words as $word) {
					$len = function_exists('mb_strlen') ? mb_strlen($word) : strlen($word);
					if(DatabaseStopwords::has($word) || $len < $database->getVariable('ft_min_word_len')) {
						$this->matchWordLIKE($tableName, $fieldName, $operator, $word);
					} else {
						$this->matchContains($tableName, $fieldName, $operator, $word);
					}
				}
				if(!count($words)) $query->where("1>2"); // force it not to match if no words
				break;

			case '%=':
				$v = $database->escapeStr($value); 
				$v = $this->escapeLIKE($v); 
				$query->where("$tableField LIKE '%$v%'"); // SLOW, but assumed
				break;

			case '^=':
			case '%^=': // match at start using only LIKE (no index)
				$v = $database->escapeStr($value);
				$v = $this->escapeLIKE($v); 
				$query->where("$tableField LIKE '$v%'"); 
				break;

			case '$=':
			case '%$=': // RCD match at end using only LIKE (no index)
				$v = $database->escapeStr($value);
				$v = $this->escapeLIKE($v); 
				$query->where("$tableField LIKE '%$v'"); 
				break;

			default:
				throw new WireException("Unimplemented operator in " . get_class($this) . "::match()"); 
		}

		return $this; 
	}

	/**
	 * Match when given $value is an array
	 * 
	 * Note: PageFinder uses its own array-to-value conversion, so this case applies only to other usages outside PageFinder,
	 * such as FieldtypeMulti::getLoadQueryWhere()
	 * 
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string $operator
	 * @param array $value
	 * @return $this
	 * @since 3.0.141
	 * @throws WireException
	 * 
	 */
	protected function matchArrayValue($tableName, $fieldName, $operator, $value) {
		if($operator === '~=') {
			throw new WireException("Operator ~= is not supported for $fieldName with OR value condition");
		}
		// convert *= operator to %= to make the query possible (avoiding matchContains method)
		if($operator === '*=') $operator = '%='; 
		$query = $this->query;
		$this->query = $this->wire(new DatabaseQuerySelect());
		foreach($value as $v) {
			$this->match($tableName, $fieldName, $operator, "$v");
		}
		$query->where(implode(" OR ", $this->query->where));
		$this->query = $query;
		return $this;
	}

	/**
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string $operator
	 * @param string $value
	 * 
	 */
	protected function matchContains($tableName, $fieldName, $operator, $value) {

		$query = $this->query; 
		$tableField = "$tableName.$fieldName";
		$database = $this->wire('database');
		$v = $database->escapeStr($value); 
		$not = strpos($operator, '!') === 0;
		if($not) $operator = ltrim($operator, '!');

		$n = 0; 
		do {
			$scoreField = "_score_{$tableName}_{$fieldName}" . (++$n);
		} while(in_array($scoreField, self::$scoreFields)); 
		self::$scoreFields[] = $scoreField;
		
		$match = $not ? 'NOT MATCH' : 'MATCH';
		$query->select("$match($tableField) AGAINST('$v') AS $scoreField"); 
		$query->orderby($scoreField . " DESC");

		$partial = $operator != '~=' && $operator != '!~=';
		$booleanValue = $database->escapeStr($this->getBooleanQueryValue($value, true, $partial));
		if($booleanValue) {
			$j = "$match($tableField) AGAINST('$booleanValue' IN BOOLEAN MODE) ";
		} else {
			$j = '';
		}
			
		if($operator == '^=' || $operator == '$=' || ($operator == '*=' && (!$j || preg_match('/[-\s]/', $v)))) { 
			// if $operator is a ^begin/$end, or if there are any word separators in a *= operator value

			if($operator == '^=' || $operator == '$=') {
				$type = $not ? 'NOT RLIKE' : 'RLIKE';
				$v = $database->escapeStr(preg_quote($value)); // note $value not $v
				$like = "[[:space:]]*(<[^>]+>)*[[:space:]]*"; 
				if($operator == '^=') {
					$like = "^" . $like . $v; 
				} else {
					$like = $v . '[[:space:]]*[[:punct:]]*' . $like . '$';
				}

			} else {
				$type = $not ? 'NOT LIKE' : 'LIKE';
				$v = $this->escapeLIKE($v); 
				$like = "%$v%";
			}

			$j = trim($j); 
			$j .= (($j ? "AND " : '') . "($tableField $type '$like')"); // note the LIKE is used as a secondary qualifier, so it's not a bottleneck
		}

		$query->where($j); 
	}

	/**
	 * Match a whole word using MySQL LIKE/REGEXP
	 * 
	 * This is useful primarily for short whole words that can't be indexed due to MySQL ft_min_word_len, 
	 * or for words that are stop words. It uses a slower REGEXP rather than fulltext index.
	 * 
	 * @param string $tableName
	 * @param string $fieldName
	 * @param string $operator
	 * @param $word
	 * 
	 */
	protected function matchWordLIKE($tableName, $fieldName, $operator, $word) {
		$tableField = "$tableName.$fieldName";
		$database = $this->wire('database');
		$v = $database->escapeStr(preg_quote($word)); 
		$regex = "([[[:blank:][:punct:]]|^)$v([[:blank:][:punct:]]|$)";
		$type = strpos($operator, '!') === 0 ? 'NOT REGEXP' : 'REGEXP';
		$where = "($tableField $type '$regex')"; 
		$this->query->where($where);
	}

	/**
	 * Get the query that was provided to the constructor
	 * 
	 * @return DatabaseQuerySelect
	 * 
	 */
	public function getQuery() {
		return $this->query; 
	}

	/**
	 * Generate a boolean query value for use in an SQL MATCH/AGAINST statement. 
	 *
	 * @param string $value
	 * @param bool $required Is the given value required in the query?
	 * @param bool $partial Is it okay to match a partial value? i.e. can "will" match "willy"
	 * @return string Value provided to the function with boolean operators added. 
	 *
	 */
	protected function getBooleanQueryValue($value, $required = true, $partial = true) {
		$newValue = '';
		//$a = preg_split('/[-\s,+*!.?()=;]+/', $value); 
		$a = preg_split('/[-\s,+*!?()=;]+/', $value); 
		foreach($a as $k => $v) {
			$v = trim($v);
			if(!strlen($v)) continue;
			if(DatabaseStopwords::has($v)) {
				continue; 
			}
			if($required) $newValue .= "+$v"; else $newValue .= "$v";
			if($partial) $newValue .= "*";
			$newValue .= " ";
		}
		return trim($newValue); 
	}
}
