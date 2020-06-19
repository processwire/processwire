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
		'matchContains' => array('*=', '*+=', '**=', '**+=', '^=', '$='),
		'matchWords' => array('~=', '!~=', '~+='), 
		'matchContainsWords' => array('~*=', '~~=', '~|=', '~|*='),
		'matchWordsLIKE' => array('~%=', '~|%='),
		'matchLIKE' => array('%='),
		'matchStartLIKE' => array('%^='),
		'matchEndLIKE' => array('%$='),
		'matchCommands' => array('#='), 
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
		$str = str_replace(array('@', '+', '-', '*', '~', '<', '>', '(', ')', ':', '"', '&', '|', '=', '.'), ' ', $str);
		while(strpos($str, '  ')) $str = str_replace('  ', ' ', $str);
		return $str;
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
		
		if(strpos($this->operator, '~') !== false) {
			throw new WireException("Operator $this->operator is not supported for $this->fieldName with OR value condition");
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
	protected function matchStartLIKE($value) {
		$this->query->where("$this->tableField LIKE ?", $this->escapeLIKE($value) . '%');
	}

	/**
	 * Match ends-with
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchEndLIKE($value) {
		$this->query->where("$this->tableField LIKE ?", '%' . $this->escapeLIKE($value));
	}

	/**
	 * Match full words
	 *
	 * @param string $value
	 *
	 */
	protected function matchWords($value) {
		$partial = $this->operator === '~*=';
		// note: ft_min_word_len is automatically changed to InnoDB’s equivalent when applicable
		$minWordLength = (int) $this->database->getVariable('ft_min_word_len');
		$words = $this->words($value);
		$mb = function_exists('mb_strlen');
		foreach($words as $word) {
			$word = trim($word, '-.,');
			$len = $mb ? mb_strlen($word) : strlen($word);
			if($len < (int) $minWordLength || $this->database->isStopword($word)) {
				// word is stop-word or is too short to use fulltext index
				$this->matchWordLIKE($word, $partial);
			} else {
				$this->matchContains($word, $partial);
			}
		}
		// force it not to match if no words
		if(!count($words)) $this->query->where("1>2");
	}

	/**
	 * Match words (plural) LIKE
	 * 
	 * @param string $value
	 * @since 3.0.160
	 * 
	 */
	protected function matchWordsLIKE($value) {
		$type = strpos($this->operator, '!') === 0 ? 'NOT LIKE' : 'LIKE';
		$any = strpos($this->operator, '|') !== false;
		//$texts = preg_split('/[-\s,@]/', $value, -1, PREG_SPLIT_NO_EMPTY);
		$words = $this->words($value); 
		$binds = array(); // used only in $any mode
		$wheres = array(); // used only in $any mode
		foreach($words as $word) {
			$word = $this->escapeLIKE($word);
			if(!strlen($word)) continue;
			if($any) {
				$bindKey = $this->query->getUniqueBindKey();
				$wheres[] = "($this->tableField $type $bindKey)";
				$binds[$bindKey] = "%$word%";
			} else {
				$this->query->where("($this->tableField $type ?)", "%$word%");
			}
		}
		// force it not to match if no words
		if(!count($words)) {
			$this->query->where("1>2");
		} else if($any) {
			$this->query->where(implode(' OR ', $wheres)); 
			$this->query->bindValues($binds); 
		}
	}

	/**
	 * Match contains partial words
	 * 
	 * @param string $value
	 * @since 3.0.160
	 * 
	 */
	protected function matchContainsWords($value) {
		$tableField = $this->tableField();
		$operator = $this->operator;
		$required = strpos($operator, '|') === false;
		$partial = $operator != '~|=';
		$booleanValue = $this->getBooleanQueryValueWords($value, $required, $partial);
		$not = strpos($operator, '!') === 0;
		$match = $not ? 'NOT MATCH' : 'MATCH';
		$bindKey = $this->query->bindValueGetKey($booleanValue);
		$where = "$match($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
		$this->query->where($where);
	}

	/**
	 * Match contains string
	 * 
	 * @param string $value
	 * @param bool|null $partial
	 * 
	 */
	protected function matchContains($value, $partial = null) {

		$tableField = $this->tableField();
		$operator = $this->operator;
		$not = strpos($operator, '!') === 0;
		$match = $not ? 'NOT MATCH' : 'MATCH';
		$wheres = array();
		$operator = ltrim($operator, '!');
		$scoreField = $this->getScoreFieldName();
		$expandAgainst = (strpos($operator, '+') !== false ? ' WITH QUERY EXPANSION' : '');
		$booleanValue = '';
		$required = true;
		$likeType = '';
		$like = '';
		
		if($partial === null) {
			$partial = strpos($operator, '~') === false || $operator === '~*=' || $operator === '~~=';
		}

		if(strpos($operator, '**') !== false || strpos($operator, '+') !== false) {
			// match or expand
			$value = implode(' ', $this->words($value, array('pluralize' => true))); 
		} else if($operator === '^=' || $operator === '$=') {
			// starts with or ends with
		} else {
			// boolean value query
			$booleanValue = $this->getBooleanQueryValueWords($value, $required, $partial);
		}

		$against = $this->escapeAGAINST($value);
		$bindKey = $this->query->bindValueGetKey($against);
		$matchAgainst = "$match($tableField) AGAINST($bindKey$expandAgainst)";
		$select = "$matchAgainst AS $scoreField ";
		$this->query->select($select);
		$this->query->orderby("$scoreField DESC");

		//$query->select("LOCATE('$against', $tableField) AS $locateField"); 
		//$query->orderby("$locateField=1 DESC"); 

		if($booleanValue == '') {
			$wheres[] = $matchAgainst;
		} else {
			$bindKey = $this->query->bindValueGetKey($booleanValue);
			$wheres[] = "$match($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
		}

		// determine if we need to add LIKE conditions as a secondary qualifier to narrow
		// search after rows have already been identified by the MATCH/AGAINST
		if($operator === '^=' || $operator === '$=') {
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

		} else if($operator === '*=') { 
			// cointains phrase
			if(!count($wheres) || preg_match('/[-\s]/', $against)) {
				// contains *= with word separators, or no existing where (boolean) conditions
				$likeType = $not ? 'NOT LIKE' : 'LIKE';
				$likeText = $this->escapeLIKE($value);
				$like = "%$likeText%";
			}
		}

		if($like) {
			// LIKE is used as a secondary qualifier, so it’s not a bottleneck
			$bindKey = $this->query->bindValueGetKey($like);
			$wheres[] = "($tableField $likeType $bindKey)";
		}

		if(count($wheres)) {
			$this->query->where(implode(' AND ', $wheres));
		}
	}
	

	/**
	 * Match a whole word using MySQL LIKE/REGEXP
	 * 
	 * This is useful primarily for short whole words that can't be indexed due to MySQL ft_min_word_len, 
	 * or for words that are stop words. It uses a slower REGEXP rather than fulltext index.
	 * 
	 * @param string $word
	 * @param bool $partial
	 * 
	 */
	protected function matchWordLIKE($word, $partial = false) {
		$word = preg_quote($word);
		$regex = "([[:blank:]]|[[:punct:]]|[[space]]|^)$word";
		if(!$partial) $regex .= "([[:blank:]]|[[:punct:]]|[[space]]|$)"; // match full word at boundary
		$type = strpos($this->operator, '!') === 0 ? 'NOT REGEXP' : 'REGEXP';
		$this->query->where("($this->tableField $type ?)", $regex);
	}
	
	/**
	 * Match text using LIKE
	 *
	 * @param string $text
	 * @since 3.0.160
	 *
	 */
	protected function matchTextLIKE($text) {
		$text = $this->escapeLIKE($text);
		$type = strpos($this->operator, '!') === 0 ? 'NOT LIKE' : 'LIKE';
		$this->query->where("($this->tableField $type ?)", $text);
	}

	/**
	 * Match text using boolean mode commands
	 *
	 * @param string $text
	 * @since 3.0.160
	 *
	 */
	protected function matchCommands($text) {
		$tableField = $this->tableField();
		$scoreField = $this->getScoreFieldName();
		$against = $this->getBooleanQueryValueCommands($text);
		$bindKey = $this->query->bindValueGetKey($against);
		$matchAgainst = "MATCH($tableField) AGAINST($bindKey IN BOOLEAN MODE) ";
		$select = "$matchAgainst AS $scoreField ";
		$this->query->select($select);
		$this->query->orderby("$scoreField DESC");
		$this->query->where($matchAgainst);
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
	protected function getBooleanQueryValueWords($value, $required = true, $partial = true) {

		$operator = $this->operator;
		$booleanValue = '';
		$value = $this->escapeAGAINST($value);
		$lastWord = '';
		$searchStopwords = false;
		
		if($operator === '~~=' || $operator === '^=') {
			// contains full words and partial last word (live search or starts with)
			$words = $this->words($value, array());
			$lastWord = trim(array_pop($words));
			$partial = false;
			$searchStopwords = true;
		} else if($partial && $operator !== '*=') {
			// contains partial words
			$searchStopwords = true;
			$words = $this->words($value, array(
				'pluralize' => strpos($operator, '+') !== false, 
				// 'singularize' => true
			));
		} else {
			$words = $this->words($value);
		}
		
		foreach($words as $key => $word) {
			//$word = trim($word, '-~+*,.<>@"\' ');
			if(!strlen($word)) continue; 
			if(!$searchStopwords && $this->database->isStopword($word)) continue;
			$booleanValue .= $required ? "+$word" : "$word";
			if($partial) $booleanValue .= "*";
			$booleanValue .= " ";
		}
		
		if($lastWord !== '') {
			if($required) $booleanValue .= '+';
			$booleanValue .= $lastWord . '*';
		}
		
		return trim($booleanValue); 
	}

	/**
	 * Generate boolean query value for matching exact phrase in order (no partials)
	 * 
	 * @param string $value
	 * @param string $action Phrase action of blank, '+', '-' or '~' (default='')
	 * @return string
	 * 
	 */
	protected function getBooleanQueryValueExactPhrase($value, $action = '') {
		$value = $this->escapeAGAINST($value);
		$words = $this->words($value);
		$phrase = implode(' ', $words);
		$booleanValue = '"' . $phrase . '"';
		if($action === '+' || $action === '-' || $action === '~') {
			$booleanValue = $action . $booleanValue;
		}
		return $booleanValue;
	}

	/**
	 * Get boolean query value where "+" and "-" and "*" and '"' are allowed in query to affect results
	 * 
	 * @param string $value
	 * @return string
	 * 
	 */
	protected function getBooleanQueryValueCommands($value) {
		
		$booleanValues = array();
		$value = str_replace(array('“', '”'), '"', $value);
		
		if(strpos($value, '"') !== false && preg_match_all('![-~+]?"([^"]+)"!', $value, $matches)) {
			// find all quoted phrases
			foreach($matches[0] as $key => $fullMatch) {
				$action = strpos($fullMatch, '"') === 0 ? '' : substr($fullMatch, 0, 1);
				$phrase = trim($matches[1][$key]);
				if(empty($phrase)) continue;
				$phrase = $this->getBooleanQueryValueExactPhrase($phrase, $action);
				if(strlen($phrase)) $booleanValues[] = $phrase;
				$value = str_replace($fullMatch, ' ', $value);
			}
		}

		$value = str_replace('"', '', $value);
		$words = $this->words($value);
		$value = " $value ";
		
		foreach($words as $word) {
			$w = $this->escapeAGAINST($word);
			$pregWord = preg_quote($w, '!');
			if(stripos($value, "+$word*")) {
				$booleanValues[] = "+$w*";
			} else if(stripos($value, "+$word") && preg_match('!\+' . $pregWord . '\b!i', $value)) {
				$booleanValues[] = "+$w";
			} else if(stripos($value, "-$word*")) {
				$booleanValues[] = "-$w*";
			} else if(stripos($value, "-$word") && preg_match('!-' . $pregWord . '\b!i', $value)) {
				$booleanValues[] = "-$w";
			} else if(stripos($value, "$word*") && preg_match('!\b' . $pregWord . '\*!i', $value)) {
				$booleanValues[] = "$w*";
			} else {
				$booleanValues[] = $w; // optional
			}
		}

		return implode(' ', $booleanValues);
	}

	/**
	 * Get array of words from given value
	 * 
	 * @param string $value
	 * @param array $options
	 * @return array
	 * 
	 */
	protected function words($value, array $options = array()) {
		
		$defaults = array(
			'keepNumberFormat' => false, 
			'singularize' => false,
			'pluralize' => false, 
			'boolean' => false, // not currently used
		);
		
		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		$words = $this->wire()->sanitizer->wordsArray($value, $options);
		$plural = strtolower($this->_('s')); // Suffix(es) that when appended to a word makes it plural // Separate multiple with a pipe "|" or to disable specify uppercase "X"
		$plurals = strpos($plural, '|') ? explode('|', $plural) : array($plural);
		
		if($options['pluralize']) {
			// add additional pluralized or singularized words
			$addWords = array();
			foreach($words as $key => $word) {
				$word = strtolower($word); 
				$wordLen = strlen($word);
				foreach($plurals as $suffix) {
					$suffixLen = strlen($suffix);
					$w = '';
					if($wordLen > $suffixLen && substr($word, -1 * $suffixLen) === $suffix) {
						if($options['singularize']) $w = substr($word, 0, $wordLen - $suffixLen); 
					} else {
						// pluralize
						$w = $word . $suffix;
					}
					if($w) {
						if($options['boolean']) $w = "<$w";
						$addWords[$w] = $w;
					}
				}
			}
			if(count($addWords)) $words = array_merge($words, $addWords); 
			
		} else if($options['singularize']) {
			// singularize only by replacement
			foreach($words as $key => $word) {
				$word = strtolower($word);
				$wordLen = strlen($word); 
				foreach($plurals as $suffix) {
					if(stripos($word, $suffix) === false) continue;
					$suffixLen = strlen($suffix);
					if($wordLen <= $suffixLen) continue;
					if(substr($word, -1 * $suffixLen) === $suffix) {
						$word = substr($word, 0, $wordLen - $suffixLen); 
						if($options['boolean']) $word = "<$word";
						$words[$key] = $word;
					}
				}
			}
		}
	
		return $words; 
	}
		
	/**
	 * Get unique score field name
	 * 
	 * @return string
	 * @since 3.0.160
	 * 
	 */
	protected function getScoreFieldName() {
		$n = 0;
		do {
			$scoreField = "_score_{$this->tableName}_{$this->fieldName}" . (++$n);
			// $locateField = "_locate_{$tableName}_{$fieldName}$n";
		} while(isset(self::$scoreFields[$scoreField]));
		self::$scoreFields[$scoreField] = 1;
		return $scoreField;
	}
}
