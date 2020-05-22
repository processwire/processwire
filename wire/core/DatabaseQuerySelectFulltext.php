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
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 * 
 * @property-read $tableField
 *
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
	 * @var string
	 *
	 */
	protected $tableName = '';

	/**
	 * @var $fieldName
	 *
	 */
	protected $fieldName = '';

	/**
	 * @var string
	 *
	 */
	protected $operator = '';

	/**
	 * @var string
	 *
	 */
	protected $method = '';

	/**
	 * Method names to operators they handle
	 *
	 * @var array
	 *
	 */
	protected $methodOperators = array(
		'matchEquals' => array('=', '!=', '>', '<', '>=', '<='),
		'matchContains' => array('*='),
		'matchWords' => array('~=', '!~='),
		'matchLIKE' => array('%='),
		'matchStart' => array('^=', '%^='),
		'matchEnd' => array('$=', '%$='),
	);

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
	 * @param string $key
	 *
	 * @return mixed|string
	 *
	 */
	public function __get($key) {
		if($key === 'tableField') return $this->tableField();
		return parent::__get($key);
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
	 * Get 'tableName.fieldName' string
	 * 
	 * @return string
	 * 
	 */
	protected function tableField() {
		return "$this->tableName.$this->fieldName";
	}

	/**
	 * Escape string for use in a MySQL LIKE
	 * 
	 * When applicable, $database->escapeStr() should be applied before this.
	 * 
	 * @param string $str
	 * @return string
 	 *
	 */
	protected function escapeLIKE($str) {
		return str_replace(array('%', '_'), array('\\%', '\\_'), $str);
	}

	/**
	 * Additional escape for use in a MySQL AGAINST
	 * 
	 * When applicable, $database->escapeStr() must also be applied (before or after). 
	 * 
	 * @param string $str
	 * @return string
	 *
	 */
	protected function escapeAGAINST($str) {
		return str_replace(array('@', '+', '-', '*', '~', '<', '>', '(', ')', ':', '"', '&', '|', '=', '.'), ' ', $str);
	}

	/**
	 * @param string $value
	 * @return string
	 * 
	 */
	protected function value($value) {
		$maxLength = self::maxQueryValueLength;
		$value = trim($value);
		if(strlen($value) < $maxLength && strpos($value, "\n") === false) return $value;
		$value = $this->sanitizer->trunc($value, $maxLength); 
		return $value;
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
		
		$this->tableName = $this->database->escapeTable($tableName); 
		$this->fieldName = $this->database->escapeCol($fieldName); 
		$this->operator = $operator;
		
		foreach($this->methodOperators as $name => $operators) {
			if(in_array($operator, $operators)) $this->method = $name;
			if($this->method) break;
		}
		
		if(!$this->method) {
			throw new WireException("Unimplemented operator in $this::match()");
		}

		if(is_array($value)) {
			$this->matchArrayValue($value);
		} else {
			$method = $this->method;
			$this->$method($this->value($value));
		}
		
		return $this;
	}

	/**
	 * Match when given $value is an array
	 * 
	 * Note: PageFinder uses its own array-to-value conversion, so this case applies only to other usages outside PageFinder,
	 * such as FieldtypeMulti::getLoadQueryWhere()
	 * 
	 * @param array $value
	 * @since 3.0.141
	 * @throws WireException
	 * 
	 */
	protected function matchArrayValue(array $value) {
		
		if($this->operator === '~=') {
			throw new WireException("Operator ~= is not supported for $this->fieldName with OR value condition");
		}
		
		// convert *= operator to %= to make the query possible (avoiding matchContains method)
		if($this->operator === '*=') $this->operator = '%='; 
		
		$query = $this->query;
		$this->query = $this->wire(new DatabaseQuerySelect());
		$this->query->bindOption(true, $query->bindOption(true)); 
		$method = $this->method;
		
		foreach($value as $v) {
			$this->$method($this->value("$v"));
		}
		
		// @todo need to get anything else from substitute query?
		$query->where(implode(' OR ', $this->query->where));
		$this->query->copyBindValuesTo($query);
		$this->query = $query;
	}

	/**
	 * Match equals, not equals, less, greater, etc.
	 *
	 * @param string $value
	 *
	 */
	protected function matchEquals($value) {
		$this->query->where("$this->tableField$this->operator?", $value);
	}

	/**
	 * Match LIKE
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchLIKE($value) {
		$this->query->where("$this->tableField LIKE ?", '%' . $this->escapeLIKE($value) . '%');
	}

	/**
	 * Match starts-with
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchStart($value) {
		$this->query->where("$this->tableField LIKE ?", $this->escapeLIKE($value) . '%');
	}

	/**
	 * Match ends-with
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchEnd($value) {
		$this->query->where("$this->tableField LIKE ?", '%' . $this->escapeLIKE($value));
	}

	/**
	 * Match words
	 *
	 * @param string $value
	 *
	 */
	protected function matchWords($value) {
		$words = preg_split('/[-\s,@]/', $value, -1, PREG_SPLIT_NO_EMPTY);
		foreach($words as $word) {
			$len = function_exists('mb_strlen') ? mb_strlen($word) : strlen($word);
			if(DatabaseStopwords::has($word) || $len < (int) $this->database->getVariable('ft_min_word_len')) {
				// word is stop-word or has too short to use fulltext index
				$this->matchWordLIKE($word);
			} else {
				$this->matchContains($word);
			}
		}
		// force it not to match if no words
		if(!count($words)) $this->query->where("1>2");
	}


	/**
	 * Match contains string
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchContains($value) {

		$tableField = $this->tableField();
		$tableName = $this->tableName;
		$fieldName = $this->fieldName;
		$operator = $this->operator;
		$partial = strpos($operator, '~') === false;
		$not = strpos($operator, '!') === 0;
		$match = $not ? 'NOT MATCH' : 'MATCH';
		$wheres = array();
		$against = $this->escapeAGAINST($value);
		$booleanValue = $this->getBooleanQueryValue($value, true, $partial);
		$operator = ltrim($operator, '!');
		$likeType = '';
		$like = '';
		$n = 0;

		do {
			$scoreField = "_score_{$tableName}_{$fieldName}" . (++$n);
			// $locateField = "_locate_{$tableName}_{$fieldName}$n";
		} while(in_array($scoreField, self::$scoreFields));

		self::$scoreFields[] = $scoreField;

		$bindKey = $this->query->bindValueGetKey($against); 
		$this->query->select("$match($tableField) AGAINST($bindKey) AS $scoreField");
		$this->query->orderby("$scoreField DESC");

		//$query->select("LOCATE('$against', $tableField) AS $locateField"); 
		//$query->orderby("$locateField=1 DESC"); 

		if($booleanValue) {
			$bindKey = $this->query->bindValueGetKey($booleanValue);
			$wheres[] = "$match($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
		}

		if($operator == '^=' || $operator == '$=') {
			// starts or ends with
			$likeType = $not ? 'NOT RLIKE' : 'RLIKE';
			$likeText = preg_quote($value);

			if($operator === '^=') {
				// starts with [optional non-visible html or whitespace] plus query text
				$like = '^[[:space:]]*(<[^>]+>)*[[:space:]]*' . $likeText;
			} else {
				// ends with query text, [optional punctuation and non-visible HTML/whitespace]
				$like = $likeText . '[[:space:]]*[[:punct:]]*[[:space:]]*(<[^>]+>)*[[:space:]]*$';
			}

		} else if($operator === '*=' && (!count($wheres) || preg_match('/[-\s]/', $against))) {
			// contains *= with word separators, or no existing where (boolean) conditions
			$likeType = $not ? 'NOT LIKE' : 'LIKE';
			$likeText = $this->escapeLIKE($value);
			$like = "%$likeText%";
		}

		if($like) {
			// LIKE is used as a secondary qualifier, so it's not a bottleneck
			$bindKey = $this->query->bindValueGetKey($like);
			$wheres[] = "($tableField $likeType $bindKey)";
		}

		if(count($wheres)) $this->query->where(implode(' AND ', $wheres));
	}
	

	/**
	 * Match a whole word using MySQL LIKE/REGEXP
	 * 
	 * This is useful primarily for short whole words that can't be indexed due to MySQL ft_min_word_len, 
	 * or for words that are stop words. It uses a slower REGEXP rather than fulltext index.
	 * 
	 * @param string $word
	 * 
	 */
	protected function matchWordLIKE($word) {
		$word = preg_quote($word);
		//$regex = "([[:blank:][:punct:]]|^)$v([[:blank:][:punct:]]|$)";
		$regex = "([[:blank:]]|[[:punct:]]|[[space]]|^)$word([[:blank:]]|[[:punct:]]|[[space]]|$)";
		$type = strpos($this->operator, '!') === 0 ? 'NOT REGEXP' : 'REGEXP';
		$this->query->where("($this->tableField $type ?)", $regex);
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
		$value = $this->escapeAGAINST($value);
		$words = preg_split('/[\s,!?;]+/', $value); 
		
		foreach($words as $k => $v) {
			$v = trim($v);
			if(!strlen($v) || DatabaseStopwords::has($v)) continue;
			$newValue .= $required ? "+$v" : "$v";
			if($partial) $newValue .= "*";
			$newValue .= " ";
		}
		return trim($newValue); 
	}
}
