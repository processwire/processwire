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
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
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
	 * @var DatabaseQuerySelect|PageFinderDatabaseQuerySelect
	 *
	 */
	protected $query;

	/**
	 * @var string
	 *
	 */
	protected $tableName = '';

	/**
	 * Current field/column name
	 * 
	 * @var $fieldName
	 *
	 */
	protected $fieldName = '';

	/**
	 * All field/column names (if more than one)
	 * 
	 * @var array
	 * 
	 */
	protected $fieldNames = array();

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
	 * Is it a NOT operator? 
	 * 
	 * This is not used by PageFinder originating queries, which handles NOT internally.
	 * 
	 * @var bool
	 * 
	 */
	protected $not = false;

	/**
	 * Cached minimum word length
	 * 
	 * @var int|null
	 * 
	 */
	protected $minWordLength = null;

	/**
	 * Allow adding 'ORDER BY' to query?
	 * 
	 * @var bool|null 
	 * 
	 */
	protected $allowOrder = null;

	/**
	 * Allow fulltext searches to fallback to LIKE searches to match stopwords?
	 * 
	 * @var bool
	 * 
	 */
	protected $allowStopwords = true;

	/**
	 * @var array
	 * 
	 */
	static protected $scoreCnts = array();

	/**
	 * Method names to operators they handle
	 *
	 * @var array
	 *
	 */
	protected $methodOperators = array(
		'matchEquals' => array('=', '!=', '>', '<', '>=', '<='),
		'matchPhrase' => array('*='),
		'matchPhraseExpand' => array('*+='),
		'matchRegular' => array('**=', '**+='),
		'matchStartEnd' => array('^=', '$='),
		'matchWords' => array('~=', '~+=', '~*=', '~~=', '~|=', '~|*=', '~|+='),
		'matchLikeWords' => array('~%=', '~|%='),
		'matchLikePhrase' => array('%='),
		'matchLikeStartEnd' => array('%^=', '%$='),
		'matchCommands' => array('#='), 
	);
	
	/**
	 * Alternate operators to substitute when LIKE match is forced due to no FULLTEXT index
	 * 
	 * @var array of operator to replacement operator
	 * 
	 */
	protected $likeAlternateOperators = array(
		'*=' => '%=',
		'^=' => '%^=', 
		'$=' => '%$=', 
		'~=' => '~%=', 
		'~|=' => '~|%=',
	);

	/**
	 * Force use of LIKE?
	 * 
	 * @var bool
	 * 
	 */
	protected $forceLike = false;

	/**
	 * Construct
	 *
	 * @param DatabaseQuerySelect|PageFinderDatabaseQuerySelect $query
	 *
	 */
	public function __construct(DatabaseQuerySelect $query) {
		parent::__construct();
		$query->wire($this);
		$this->query = $query;
	}

	/**
	 * @param string $name
	 * @return mixed|string
	 *
	 */
	public function __get($name) {
		if($name === 'tableField') return $this->tableField();
		return parent::__get($name);
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
		$fieldName = $this->fieldName;
		if(!$fieldName) $fieldName = 'data';
		return "$this->tableName.$fieldName";
	}

	/**
	 * Get or set whether or not 'ORDER BY' statements are allowed to be added
	 * 
	 * @param null|bool $allow Specify bool to set or omit to get
	 * @return bool|null Returns bool when known or null when not yet known
	 * @since 3.0.162
	 * 
	 */
	public function allowOrder($allow = null) {
		if($allow !== null) $this->allowOrder = $allow ? true : false;
		return $this->allowOrder;
	}

	/**
	 * Get or set whether fulltext searches can fallback to LIKE searches to match stopwords
	 *
	 * @param null|bool $allow Specify bool to set or omit to get
	 * @return bool
	 * @since 3.0.162
	 *
	 */
	public function allowStopwords($allow = null) {
		if($allow !== null) $this->allowStopwords = $allow ? true : false;
		return $this->allowStopwords;
	}

	/**
	 * @return string
	 * 
	 */
	protected function matchType() {
		return "\n  " . ($this->not ? 'NOT MATCH' : 'MATCH');
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
	protected function escapeLike($str) {
		return str_replace(array('%', '_'), array('\\%', '\\_'), "$str");
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
	protected function escapeAgainst($str) {
		$str = str_replace(array('@', '+', '-', '*', '~', '<', '>', '(', ')', ':', '"', '&', '|', '=', '.'), ' ', "$str");
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
		$value = trim("$value");
		if(strlen($value) < $maxLength && strpos($value, "\n") === false && strpos($value, "\r") === false) return $value;
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
		$allowOrder = true;
		
		if(strpos($operator, '!') === 0 && $operator !== '!=') {
			$this->not = true;
			$operator = ltrim($operator, '!');
		} else {
			// disable orderby statements when calling object will be negating whatever we do
			$selector = $this->query->selector;
			if($selector instanceof Selector && $selector->not) $allowOrder = false;
		}

		// if allowOrder has not been specifically set, then set value now
		if($this->allowOrder === null) $this->allowOrder = $allowOrder; 
		
		if($this->forceLike && isset($this->likeAlternateOperators[$operator])) {
			$operator = $this->likeAlternateOperators[$operator];
		}
		
		$this->operator = $operator;
		
		foreach($this->methodOperators as $name => $operators) {
			if(in_array($operator, $operators)) $this->method = $name;
			if($this->method) break;
		}
		
		if(!$this->method) {
			throw new WireException("Unimplemented operator in $this::match()");
		}
		
		if(is_array($fieldName) && count($fieldName) < 2) {
			$fieldName = reset($fieldName);
		}

		if(is_array($fieldName)) {
			$this->matchArrayFieldName($fieldName, $value);
		} else {
			$this->matchFieldName($fieldName, $value);
		}

		if(!count($this->query->where) && (strpos($operator, '~') !== false || $operator === '*+=')) {
			$this->query->where('(1>2)'); // force non-match 
		}
		
		return $this;
	}
	
	protected function matchFieldName($fieldName, $value) {
		$this->fieldName = $this->database->escapeCol($fieldName);
		if(is_array($value)) {
			$this->matchArrayValue($value);
		} else {
			$value = $this->value($value);
			$method = $this->method;
			if(strlen($value)) {
				$this->$method($value);
			} else {
				// empty value
				if($this->not || $this->operator === '!=') {
					$this->matchIsNotEmpty();
				} else {
					$this->matchIsEmpty();
				}
			}
		}
	}

	/**
	 * Match when given $fieldName is an array
	 *
	 * @param array $fieldNames
	 * @param mixed $value
	 * @since 3.0.169
	 *
	 */
	protected function matchArrayFieldName(array $fieldNames, $value) {
		$query = $this->query;
		$query->bindOption('global', true);
		
		$this->query = new DatabaseQuerySelect();
		$this->wire($this->query);
		$this->query->bindOption(true, $query->bindOption(true));
		
		foreach($fieldNames as $fieldName) {
			$this->matchFieldName($fieldName, $value);
		}
		
		$query->where('((' . implode(') OR (', $this->query->where) . '))');
		$this->query->copyBindValuesTo($query);
		$this->query = $query;
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
	
		/*
		if(strpos($this->operator, '~') !== false) {
			throw new WireException("Operator $this->operator is not supported for $this->fieldName with OR value condition");
		}
		*/
		
		// convert *= operator to %= to make the query possible (avoiding matchContains method)
		// if($this->operator === '*=') $this->operator = '%='; 
		
		$query = $this->query;
		$query->bindOption('global', true);
		$this->query = new DatabaseQuerySelect();
		$this->wire($this->query);
		$this->query->bindOption(true, $query->bindOption(true)); 
		$method = $this->method;
		
		foreach($value as $v) {
			$v = $this->value("$v"); 
			if(strlen($v)) $this->$method($v);
		}
		
		// @todo need to get anything else from substitute query?
		$query->where('((' . implode(') OR (', $this->query->where) . '))');
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
		$op = $this->wire()->database->escapeOperator($this->operator, WireDatabasePDO::operatorTypeComparison); 
		$this->query->where("$this->tableField$op?", $value);
	}

	/**
	 * Match is an empty empty string, null or not present
	 * 
	 */
	protected function matchIsEmpty() {
		$this->query->where("($this->tableField='' OR $this->tableField IS NULL)");
	}

	/**
	 * Match is present, not null and not an empty string
	 * 
	 */
	protected function matchIsNotEmpty() {
		$this->query->where("($this->tableField IS NOT NULL AND $this->tableField!='')");
	}

	/**
	 * Match LIKE phrase
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchLikePhrase($value) {
		$likeType = $this->not ? 'NOT LIKE' : 'LIKE';
		$this->query->where("$this->tableField $likeType ?", '%' . $this->escapeLike($value) . '%');
	}

	/**
	 * Match starts-with or ends-with using only LIKE (no match/against index)
	 * 
	 * Does not ignore whitespace, closing tags or punctutation at start/end the way that the
	 * matchStartEnd() method does, so this can be used to perform more literal start/end matches.
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchLikeStartEnd($value) {
		$likeType = $this->not ? 'NOT LIKE' : 'LIKE';
		if(strpos($this->operator, '^') !== false) {
			$this->query->where("$this->tableField $likeType ?", $this->escapeLike($value) . '%');
		} else {
			$this->query->where("$this->tableField $likeType ?", '%' . $this->escapeLike($value));
		}
	}

	/**
	 * Match words (plural) LIKE, given words can appear in full or in any part of a word
	 * 
	 * @param string $value
	 * @since 3.0.160
	 * 
	 */
	protected function matchLikeWords($value) {
		
		// ~%=  Match all words LIKE
		// ~|%= Match any words LIKE
		
		$likeType = $this->not ? 'NOT LIKE' : 'LIKE';
		$any = strpos($this->operator, '|') !== false;
		$words = $this->words($value); 
		$binds = array(); // used only in $any mode
		$wheres = array(); // used only in $any mode
		
		foreach($words as $word) {
			$word = $this->escapeLike($word);
			if(!strlen($word)) continue;
			if($any) {
				$bindKey = $this->query->getUniqueBindKey();
				$wheres[] = "($this->tableField $likeType $bindKey)";
				$binds[$bindKey] = "%$word%";
			} else {
				$this->query->where("($this->tableField $likeType ?)", "%$word%");
			}
		}
		
		if($any && count($words)) {
			$this->query->where('(' . implode(' OR ', $wheres) . ')'); 
			$this->query->bindValues($binds); 
		}
	}

	/**
	 * Match contains words (full, any or partial)
	 * 
	 * @param string $value
	 * @since 3.0.160
	 * 
	 */
	protected function matchWords($value) {
	
		// ~=   Contains all full words
		// !~=  Does not contain all full words
		// ~+=  Contains all full words + expand 
		// ~*=  Contains all partial words 
		// ~~=  Contains all words live (all full words + partial last word)
		// ~|=  Contains any full words
		// ~|*= Contains any partial words
		// ~|+= Contains any words + expand

		$tableField = $this->tableField();
		$operator = $this->operator;
		$required = strpos($operator, '|') === false;
		$partial = strpos($operator, '*') !== false;
		$partialLast = $operator === '~~=';
		$expand = strpos($operator, '+') !== false;
		$matchType = $this->matchType();
		$scoreField = $this->getScoreFieldName();
		$matchAgainst = '';
		$wheres = array();
		
		$data = $this->getBooleanModeWords($value, array(
			'required' => $required, 
			'partial' => $partial, 
			'partialLast' => $partialLast,
			'partialLess' => ($partial || $expand),
			'alternates' => $expand, 
		));
		
		if(empty($data['value'])) {
			// query contains no indexable words: force non-match
			//$this->query->where('1>2');
			//return;
			// TEST OUT: title|summary~|+=beer
		}

		if($expand) {
			if(!empty($data['booleanValue']) && $this->allowOrder) {
				// ensure full matches are above expanded matches
				$preScoreField = $this->getScoreFieldName(); 
				$bindKey = $this->query->bindValueGetKey($data['booleanValue']);
				$this->query->select("$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE) + 111.1 AS $preScoreField");
				$this->query->orderby("$preScoreField DESC");
			}
			if(!empty($data['matchValue'])) {
				$bindValue = trim($data['matchValue']); 
				$bindKey = $this->query->bindValueGetKey($this->escapeAgainst($bindValue));
				$matchAgainst = "$matchType($tableField) AGAINST($bindKey WITH QUERY EXPANSION)";
			}
			
		} else if(!empty($data['booleanValue'])) {
			$bindKey = $this->query->bindValueGetKey($data['booleanValue']);
			$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
		}
		
		if($matchAgainst) {
			$wheres[] = $matchAgainst;
			// $this->query->where($matchAgainst);
			if($this->allowOrder) {
				$this->query->select("$matchAgainst AS $scoreField");
				$this->query->orderby("$scoreField DESC");
			}
		} else if(!$this->allowStopwords) {
			// no match possible
			// $this->query->where('1>2'); 
			$wheres[] = '1>2';
		}
		
		if(!empty($data['likeWords'])) {
			// stopwords or words that were too short to use fulltext index
			$likeType = $this->not ? 'NOT RLIKE' : 'RLIKE';
			$orLikes = array();
			$andLikes = array();
			foreach($data['likeWords'] as $word) {
				$isStopword = isset($data['stopWords'][$word]);
				if($isStopword && !$this->allowStopwords) continue;
				if(!strlen($word)) continue;
				if($partial || ($partialLast && $word === $data['lastWord'])) {
					// just match partial word from beginning
					$likeValue = $this->rlikeValue($word);
				} else {
					// match to word-end
					$likeValue = $this->rlikeValue($word, array('partial' => false));
				}
				$bindKey = $this->query->bindValueGetKey($likeValue);
				$likeWhere = "($tableField $likeType $bindKey)";
				if(!$required || ($isStopword && $expand)) {
					$orLikes[] = $likeWhere;
				} else {
					$andLikes[] = $likeWhere;
				}
			}
			$whereLike = '';
			if(count($orLikes)) {
				$whereLike .= '(' . implode(' OR ', $orLikes) . ')';
				if(count($andLikes)) $whereLike .= $required ? ' AND ' : ' OR ';
			}
			if(count($andLikes)) {
				$whereLike .= implode(' AND ', $andLikes);
			}
			if($whereLike) $wheres[] = $whereLike;
		}
		
		if(count($wheres)) {
			$and = $required ? ' AND ' : ' OR ';
			$this->query->where('(' . implode($and, $wheres) . ')');
		}
	}

	/**
	 * Match contains entire phrase/string (*=)
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchPhrase($value) {
		
		$tableField = $this->tableField();
		$likeValue = '';
		$useLike = false;
		$words = $this->words($value);
		$lastWord = count($words) > 1 ? array_pop($words) : '';
		$badWords = array();
		$goodWords = array();
		
		foreach($words as $word) {
			if($this->isIndexableWord($word)) {
				$goodWords[$word] = $word;
			} else {
				$badWords[$word] = $word;
			}
		}
		
		if(count($badWords)) $useLike = true;

		if(!count($goodWords)) {
			// 0 good words to search: do not use match/against
			$againstValue = '';
		} else if(count($goodWords) === 1) {
			// 1 word left: non-quoted word only, partial match if no last word
			$word = reset($goodWords);
			$againstValue = '+' . $this->escapeAgainst($word);
			if($lastWord === '') $againstValue .= '*';
		} else if(!count($badWords)) {
			// no bad words, okay to match all in phrase format
			$againstValue = '+"' . $this->escapeAgainst(implode(' ', $words)) . '"'; 
		} else {
			// combination of good and bad words, match the good words in any order
			// and let the LIKE match them as a phrase
			$againstValue = $this->escapeAgainst(implode(' ', $goodWords));
			$useLike = true;
		}
		
		if($useLike || $lastWord !== '' || !strlen($againstValue)) {
			// match entire phrase with LIKE as secondary qualifier that includes last word
			// so that we can perform a partial match on the last word only. This is necessary
			// because we can’t use partial match qualifiers in or out of quoted phrases.
			$lastWord = strlen($lastWord) ? $this->escapeAgainst($lastWord) : '';
			if(strlen($lastWord) && !$this->isStopword($lastWord)) {
				// if word is indexable let it contribute to final score
				// expand the againstValue to include the last word as a required partial match
				$againstValue = trim("$againstValue +$lastWord*");
			}
			$likeValue = $this->rlikeValue($value); 
		}
		
		if(strlen($againstValue)) {
			// use MATCH/AGAINST
			$bindKey = $this->query->bindValueGetKey($againstValue);
			$matchType = $this->matchType();
			$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
			$this->query->where($matchAgainst);
		
			if($this->allowOrder) {
				$scoreField = $this->getScoreFieldName();
				$this->query->select("$matchAgainst AS $scoreField");
				$this->query->orderby("$scoreField DESC");
			}
		}

		if(strlen($likeValue)) {
			// LIKE is used as a secondary qualifier to MATCH/AGAINST so that it is
			// performed only on rows already identified from FULLTEXT index, unless 
			// no MATCH/AGAINST could be created due to stopwords or too-short words
			$likeType = $this->not ? 'NOT RLIKE' : 'RLIKE';
			$this->query->where("($tableField $likeType ?)", $likeValue);
		}
	}

	/**
	 * Match phrase with query expansion (*+=)
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchPhraseExpand($value) {
		
		$tableField = $this->tableField();
		$matchType = $this->matchType();
		$words = $this->words($value, array('indexable' => true));
		$wordsAlternates = array();
		
		$phraseWords = $this->words($value); // including non-indexable
		$lastPhraseWord = (string) array_pop($phraseWords);
		$scoreField = $this->getScoreFieldName();
		$againstValues = array();
		$matchAgainst = null;
		
		// BOOLEAN PHRASE: full phrase matches come before expanded matches
		if(count($phraseWords)) {
			$phrases = array();
			$phrase = array();
			foreach($phraseWords as $word) {
				if($this->isIndexableWord($word)) {
					$phrase[] = $word;
				} else {
					if(count($phrase)) {
						$phrases[] = $phrase;
						$phrase = array();
					}
					$againstValues[] = $this->escapeAgainst($word) . '*';
				}
			}
			if(count($phrase)) $phrases[] = $phrase;
			if(count($phrases)) {
				foreach($phrases as $phrase) {
					$phraseStr = $this->escapeAgainst(implode(' ', $phrase));
					if(count($phrase) > 1) $phraseStr = '"' . $phraseStr . '"';
					$againstValues[] = "+$phraseStr";
				}
			}
		}
	
		if(strlen($lastPhraseWord)) {
			$againstValues[] = ($this->isIndexableWord($lastPhraseWord) ? '+' : '') . $this->escapeAgainst($lastPhraseWord) . '*';
			$bindKey = $this->query->bindValueGetKey(implode(' ', $againstValues));
			$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
			if($this->allowOrder) {
				$this->query->select("$matchAgainst + 333.3 AS $scoreField");
				$this->query->orderby("$scoreField DESC");
			}
		}
		
		if(!count($words)) {
			// no words to work with for query expansion (not likely, unless stopwords or too-short)
			if($matchAgainst) $this->query->where($matchAgainst);
			return;
		}
		
		// BOOLEAN WEIGHTED WORDS: word matches above query expansion matches
		$againstValue = '';
		$scoreField = $this->getScoreFieldName();
		foreach($words as $word) {
			$wordAlternates = array();
			foreach($this->getWordAlternates($word) as $w) {
				if($w === $word || !$this->isIndexableWord($w)) continue; 
				$wordAlternates[$w] = $w; // alternates for just this word
				$wordsAlternates[$w] = $w; // alternates for all words
			}
			$word = $this->escapeAgainst($word);
			// full word match carries more weight than partial or alternate word match,
			// but at least one must be there in order to have a good score
			$againstValue .= "+(";
			$againstValue .= ">$word $word*"; 
			if(count($wordAlternates)) {
				$againstValue .= ' ' . $this->escapeAgainst(implode(' ', $wordAlternates));
			}
			$wordRoot = $this->getWordRoot($word); 
			if($wordRoot && $wordRoot !== $word) {
				$againstValue .= ' ' . $this->escapeAgainst($wordRoot) . '*';
			}
			$againstValue .= ") ";
		}
		
		if($this->allowOrder && strlen($againstValue)) {
			$bindKey = $this->query->bindValueGetKey(trim($againstValue));
			$this->query->select("$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE) + 222.2 AS $scoreField");
			$this->query->orderby("$scoreField DESC");
		}
		
		// QUERY EXPANSION: regular match/against words with query expansion
		$words = array_unique(array_merge($words, $wordsAlternates));	
		$againstValue = $this->escapeAgainst(implode(' ', $words));
		$bindKey = $this->query->bindValueGetKey($againstValue);
		$matchAgainst = "$matchType($tableField) AGAINST($bindKey WITH QUERY EXPANSION)";
		$this->query->where($matchAgainst);
		
		$scoreField = $this->getScoreFieldName();
		$this->query->select("$matchAgainst AS $scoreField");
		
		if($this->allowOrder) {
			$this->query->orderby("$scoreField DESC");
		}
	}

	/**
	 * Perform a regular scored MATCH/AGAINST query (non-boolean)
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchRegular($value) {
		
		// **=  Contains match
		// **+= Contains match + expand
		
		$tableField = $this->tableField();
		$expand = strpos($this->operator, '+') !== false;
		$matchType = $this->matchType();

		if($expand && $this->allowOrder) {
			// boolean mode query for sorting purposes
			$scoreField = $this->getScoreFieldName();
			$data = $this->getBooleanModeWords($value, array(
				'partialLess' => true, 
				'required' => false,
				'alternates' => true, 
			));
			if(!empty($data['booleanValue'])) {
				$againstValue = $data['booleanValue'];
				$bindKey = $this->query->bindValueGetKey($againstValue);
				$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
				$this->query->select("$matchAgainst + 111.1 AS $scoreField");
				$this->query->orderby("$scoreField DESC");
			}
		}
		
		// standard MATCH/AGAINST with optional query expansion
		$scoreField = $this->getScoreFieldName();
		$words = $this->words($value, array('indexable' => true, 'alternates' => $expand));
		$againstValue = $this->escapeAgainst(implode(' ', $words));
		
		if(!count($words) || !strlen(trim($againstValue))) {
			// query contains no indexable words: force non-match
			if(strlen($value)) $this->query->where('1>2');
			return;
		}
		
		$bindKey = $this->query->bindValueGetKey($againstValue);
		$againstType = $expand ? 'WITH QUERY EXPANSION' : '';
		$where = "$matchType($tableField) AGAINST($bindKey $againstType)";
		$this->query->where($where);
		if($this->allowOrder) {
			$this->query->select("$where AS $scoreField");
			$this->query->orderby("$scoreField DESC");
		}
	}

	/**
	 * Match phrase at start or end of field value (also uses fulltext index when possible)
	 * 
	 * Ignores whitespace, punctuation and opening/closing tags, enabling it to match 
	 * start/end words or phrases surrounded by non-word characters.
	 * 
	 * @param $value
	 * 
	 */
	protected function matchStartEnd($value) {
		
		// ^=   Starts with
		// $=   Ends with
	
		$tableField = $this->tableField();
		$matchStart = strpos($this->operator, '^') !== false;
		$againstValue = '';
		
		
		$words = $this->words($value, array('indexable' => true));
		if(count($words)) {
			if($matchStart) {
				$lastWord = $this->escapeAgainst(array_pop($words));
				$againstValue = count($words) ? '+' . $this->escapeAgainst(implode(' +', $words)) : '';
				$againstValue = trim("$againstValue +$lastWord*"); // 'partial*' match last word
			} else {
				array_shift($words); // skip first word since '*partial' match not possible with fulltext
				$againstValue = count($words) ? '+' . $this->escapeAgainst(implode(' +', $words)) : '';
			}
		}
	
		if(strlen($againstValue)) {
			// use MATCH/AGAINST to pre-filter before RLIKE when possible
			$bindKey = $this->query->bindValueGetKey($againstValue);
			$matchType = $this->matchType();
			$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
			$scoreField = $this->getScoreFieldName();
			$this->query->where($matchAgainst);
			if($this->allowOrder) {
				$this->query->select("$matchAgainst AS $scoreField");
				$this->query->orderby("$scoreField DESC");
			}
		}

		$likeType = $this->not ? 'NOT RLIKE' : 'RLIKE';
		
		if($matchStart) {
			// starts with phrase, [optional non-visible html or whitespace] plus query text
			$likeValue = $this->rlikeValue($value, array('start' => true)); 
		} else {
			// ends with phrase, [optional punctuation and non-visible HTML/whitespace]
			$likeValue = $this->rlikeValue($value, array('end' => true)); 
		}

		$this->query->where("($tableField $likeType ?)", $likeValue);
	}

	/**
	 * Match text using boolean mode commands (Advanced search)
	 *
	 * @param string $text
	 * @since 3.0.160
	 *
	 */
	protected function matchCommands($text) {
		$tableField = $this->tableField();
		$scoreField = $this->getScoreFieldName();
		$against = $this->getBooleanModeCommands($text);
		$bindKey = $this->query->bindValueGetKey($against);
		$matchType = $this->matchType();
		$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE) ";
		$this->query->where($matchAgainst);
		if($this->allowOrder) {
			$select = "$matchAgainst AS $scoreField ";
			$this->query->select($select);
			$this->query->orderby("$scoreField DESC");
		}
	}

	/**
	 * Get verbose data array of words identified and prepared for boolean mode
	 *
	 * @param string $value
	 * @param array $options
	 *  - `required` (bool): Are given words required in the query? (default=true)
	 *  - `partial` (bool): Is it okay to match a partial value? i.e. can "will" match "willy" (default=false)
	 *  - `partialLast` (bool): Use partial only for last word? (default=null, auto-detect)
	 *  - `partialLess` (bool): Weight partial match words less than full word match? (default=false)
	 *  - `phrase` (bool): Is entire $value a full phrase to match? (default=auto-detect)
	 *  - `useStopwords` (bool): Allow inclusion of stopwords? (default=null, auto-detect)
	 *  - `alternates` (bool): Get word alternates? (default=null, auto-detect)
	 * @return array Value provided to the function with boolean operators added, or verbose array.
	 *
	 */
	protected function getBooleanModeWords($value, array $options = array()) {
		
		$expand = strpos($this->operator, '+') !== false;
		
		$defaults = array(
			'required' => true, 
			'partial' => false, 
			'partialLast' => ($this->operator === '~~=' || $this->operator === '^='),
			'partialLess' => false,
			'useStopwords' => null,
			'useShortwords' => null, 
			'alternates' => $expand,
		);

		$options = array_merge($defaults, $options);
		$minWordLength = (int) $this->database->getVariable('ft_min_word_len');
		$originalValue = $value;
		$value = $this->escapeAgainst($value);
		$booleanValues = array();
		$partial = $options['partial'] ? '*' : '';
		$required = $options['required'] ? '+' : '';
		$useStopwords = is_bool($options['useStopwords']) ? $options['useStopwords'] : $partial === '*';
		$useShortwords = is_bool($options['useShortwords']) ? $options['useShortwords'] : $partial === '*';
		$lastWord = null;
		$goodWords = array();
		$stopWords = array();
		$shortWords = array();
		$likeWords = array();
		$altWords = array();
		$joinWords = array();
		$joiners = array('->', '-', '.', ':');
		
		// get all words
		$allWords = $this->words($value);

		foreach(explode(' ', $originalValue) as $word) {
			foreach($joiners as $joiner) {
				if(strpos($word, $joiner)) {
					$joinWords[$word] = $word;
					$likeWords[$word] = $word;
					break;
				}
			}
		}

		if($options['partialLast']) {
			// treat last word separately (partial last word for live or starts-with searches)
			// only last word is partial
			$lastWord = end($allWords);
			$partial = '';
		}
		
		// iterate through all words to build boolean query values
		foreach($allWords as $word) {
			
			$length = strlen($word);
			if(!$length || isset($booleanValues[$word])) continue;
			
			if($this->isStopword($word)) {
				// handle stop-word
				$stopWords[$word] = $word;
				if($useStopwords && $partial) $booleanValues[$word] = "<$word*";
				if($required) $likeWords[$word] = $word;
				continue; // do nothing further with stopwords
				
			} else if($length < $minWordLength) {
				// handle too-short word
				$shortWords[$word] = $word;
				if($useShortwords && $partial) $booleanValues[$word] = "$word*";
				if($required) $likeWords[$word] = $word;
				continue; // do nothing further with short words
				
			} else if($options['partialLess']) {
				// handle regular word and match full word (more weight), or partial word (less weight)
				$booleanValues[$word] = $required ? "+(>$word $word*)" : "$word*";
				$goodWords[$word] = $word;
				
			} else {
				// handle regular word
				$booleanValues[$word] = $required . $word . $partial;
				$goodWords[$word] = $word;
			}
			
			if($options['alternates']) {
				$booleanValue = $booleanValues[$word];
				$alternates = $this->getBooleanModeAlternateWords($word, $booleanValue, $minWordLength, $options);
				if($booleanValue !== $booleanValues[$word]) {
					$booleanValues[$word] = $booleanValue;
					$altWords = array_merge($altWords, $alternates);
					$allWords = array_merge($allWords, $altWords);
				}
			}
		}
		
		if(strlen("$lastWord")) {
			// only last word allowed to be a partial match word
			$lastRequired = isset($stopWords[$lastWord]) || isset($shortWords[$lastWord]) ? '' : $required;
			$booleanValues[$lastWord] = $lastRequired . $lastWord . '*';
		}
		
		if($useStopwords && !$required && count($stopWords) && count($goodWords)) {
			// increase weight of non-stopwords
			foreach($goodWords as $word) {
				$booleanWord = $booleanValues[$word];
				if(!in_array($booleanWord[0], array('(', '+', '<', '>', '-', '~', '"'))) {
					$booleanValues[$word] = ">$booleanWord";
				}
			}
		}

		$badWords = array_merge($stopWords, $shortWords);
		
		if(count($stopWords)) {
			$numOkayWords = count($goodWords) + count($shortWords);
			foreach($stopWords as $word) {
				$likeWords[$word] = $word;
				if($numOkayWords && isset($booleanValues[$word])) {
					// make word non-required in boolean query
					$booleanValues[$word] = ltrim($booleanValues[$word], '+'); 
				} else {
					// boolean query requires at least one good word to work,
					// so if there aren't any, remove this word from boolean query
					unset($booleanValues[$word]);
				}
			}
		}
	
		return array(
			'value' => trim(implode(' ', $allWords)), 
			'originalValue' => $originalValue,
			'matchValue' => trim(implode(' ', $goodWords) . ' ' . implode(' ', $altWords)), // indexable words only
			'booleanValue' => trim(implode(' ', $booleanValues)),
			'booleanWords' => $booleanValues,
			'likeWords' => $likeWords,
			'allWords' => $allWords,
			'goodWords' => $goodWords,
			'badWords' => $badWords, 
			'stopWords' => $stopWords, 
			'shortWords' => $shortWords, 
			'altWords' => $altWords, 
			'joinWords' => $joinWords,
			'lastWord' => $lastWord, 
			'minWordLength' => $minWordLength, 
		);
	}

	/**
	 * Helper for getBooleanModeWords to handle population of alternate words in boolean value
	 * 
	 * @param string $word Word to find alternates for
	 * @param string &$booleanValue Existing boolean value which will be updated
	 * @param int $minWordLength
	 * @param array $options
	 * @return array
	 * @since 3.0.162
	 * 
	 */
	protected function getBooleanModeAlternateWords($word, &$booleanValue, $minWordLength, array $options) {

		$required = strpos($booleanValue, '+') === 0 ? '+' : '';
		$alternateWords = $this->getWordAlternates($word);
		$rootWord = $this->getWordRoot($word);
		
		if($rootWord) {
			if(!in_array($rootWord, $alternateWords)) {
				$alternateWords[] = $rootWord;
			} else {
				$rootWord = '';
			}
		}
		
		$alternateWords = array_unique($alternateWords);
		$booleanWords = $alternateWords;

		// prepare alternate words for inclusion in boolean value and remove any that aren’t indexable
		foreach($alternateWords as $key => $alternateWord) {
			$alternateWord = $this->escapeAgainst($alternateWord);
			$length = $this->strlen($alternateWord);

			if($alternateWord === $rootWord && $length > 1) {
				// root word is always partial match. weight less if there are other alternates to match
				$less = count($booleanWords) > 1 && !empty($options['partialLess']) ? '<' : '';
				$booleanWords[$key] = $less . $alternateWord . '*';
				if($length >= $minWordLength && $length >= 3) $booleanWords[] = $less . $alternateWord;
				unset($alternateWords[$key]); 

			} else if($length < $minWordLength || $this->isStopword($alternateWord)) {
				// alternate word not indexable, remove it
				unset($alternateWords[$key]);
				unset($booleanWords[$key]);

			} else {
				// replace with escaped version
				$alternateWords[$key] = $alternateWord;
				$booleanWords[$key] = $alternateWord;
			}
		}
		
		if(!count($booleanWords)) return array();

		// rebuild boolean value to include alternates: "+(word word)" or "+word" or ""
		if($required) $booleanValue = ltrim($booleanValue, '+');
		
		// remove parens from boolean value, if present
		$booleanValue = trim($booleanValue, '()');
		
		// assign higher weight to existing first word, if not already
		if($booleanValue && strpos($booleanValue, '>') !== 0) $booleanValue = ">$booleanValue";
		
		// append alternate words
		$booleanValue = trim($booleanValue . ' ' . implode(' ', $booleanWords));
		
		// package boolean value into parens and optional "+" prefix (indicating required)
		$booleanValue = "$required($booleanValue)";
		
		return $alternateWords;
	}

	/**
	 * Get boolean query value where "+" and "-" and "*" and '"' are allowed in query to affect results
	 * 
	 * @param string $value
	 * @return string
	 * 
	 */
	protected function getBooleanModeCommands($value) {
		$booleanValues = array();
		$value = str_replace(array('“', '”'), '"', $value);
		/** @var SelectorContainsAdvanced $selector */
		$selector = Selectors::getSelectorByOperator('#=');
		$commands = $selector->valueToCommands($value);
		foreach($commands as $command) {
			$booleanValue = $this->escapeAgainst($command['value']); 
			if($command['phrase']) $booleanValue = '"' . $booleanValue . '"'; 
			if($command['type']) $booleanValue = $command['type'] . $booleanValue;
			if($command['partial']) $booleanValue .= '*';
			$booleanValues[] = $booleanValue;
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
			'keepApostrophe' => false, 
			'minWordLength' => 1, // minimum allowed length or true for ft_min_word_len
			'stopwords' => true, // allow stopwords
			'indexable' => false, // include only indexable words?
			'alternates' => false, // include alternate versions of words?
			'truncate' => true, 
		);
		
		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		if($options['minWordLength'] === true) $options['minWordLength'] = (int) $this->database->getVariable('ft_min_word_len');
		$words = $this->wire()->sanitizer->wordsArray($value, $options);
		
		if($options['alternates']) {
			foreach($words as $word) {
				$alts = $this->getWordAlternates($word);
				foreach($alts as $alt) {
					if(!in_array($alt, $words)) $words[] = $alt;
				}
			}
		}
	
		if($options['indexable']) {
			foreach($words as $key => $word) {
				if(!$this->isIndexableWord($word)) unset($words[$key]); 
			}
		} else if(!$options['stopwords']) {
			foreach($words as $key => $word) {
				if($this->isStopword($word)) unset($words[$key]); 
			}
		}
		
		return $words; 
	}

	/**
	 * Prepare a word or phrase for use in an RLIKE statement
	 * 
	 * @param string $value
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function rlikeValue($value, array $options = array()) {
		
		$defaults = array(
			'start' => false, 
			'end' => false,
			'partial' => true, // partial match at end of 
		);
		
		$options = array_merge($defaults, $options);
		
		// consider hyphen and space the same for matching purposes (must be before preg_quote)
		$value = str_replace('-', ' ', $value);
	
		// escape characters used in regular expressions
		$likeValue = preg_quote($value);
		
		if(strpos($likeValue, "'") !== false || strpos($likeValue, "’") !== false) {
			// match either straight or curly apostrophe
			$likeValue = str_replace([ "'", "’" ], "('|’)", $likeValue);
			// if word ends with apostrophe then apostrophe is optional
			$likeValue = rtrim(str_replace("('|’) ", "('|’)? ", "$likeValue "));
		}

		if(strpos($likeValue, ' ') !== false) {
			// collapse multiple spaces to just one
			while(strpos($likeValue, '  ') !== false) $likeValue = str_replace('  ', ' ', $likeValue);
			// hyphen/space can match space or hyphen in any quantity
			$likeValue = str_replace(' ', '[- ]+', $likeValue);
		}
		
		if($options['start']) {
			// given value must match at beginning
			$likeValue = '^[[:space:]]*(<[^>]+>)*[[:space:]]*' . $likeValue;
			
		} else if($options['end']) {
			// given value must match at end
			$likeValue .= '[[:space:]]*[[:punct:]]*[[:space:]]*(<[^>]+>)*[[:space:]]*$';
			
		} else {
			// given value can match at beginning of any word boundary in value
			// depending on engine, different characters identify word boundaries
			$regexEngine = $this->wire()->database->getRegexEngine();
			if($regexEngine === 'ICU') {
				// ICU (MySQL 8+)
				list($a, $b) = [ "\\b", "\\b" ];
			} else {
				// HenrySpencer
				list($a, $b) = [ '[[:<:]]', '[[:>:]]' ];
			}
			$likeValue = $a . $likeValue;
			if(!$options['partial']) $likeValue .= $b; 
		}

		return $likeValue;
	}
	
	/**
	 * @param string $value
	 * @return int
	 * 
	 */
	protected function strlen($value) {
		$value = (string) $value;
		if(function_exists('mb_strlen')) {
			return mb_strlen($value);
		} else {
			return strlen($value);
		}
	}

	/**
	 * Is given word a stopword?
	 * 
	 * @param string $word
	 * @return bool
	 * 
	 */
	protected function isStopword($word) {
		if($this->strlen($word) < 2) return true;
		return $this->wire()->database->isStopword($word); 
	}

	/**
	 * Is word too short for fulltext index?
	 * 
	 * @param string $word
	 * @return bool
	 * 
	 */
	protected function isShortword($word) {
		$minWordLength = $this->getMinWordLength();
		if($minWordLength && $this->strlen($word) < $minWordLength) return true;
		return false;
	}

	/**
	 * Is given word not a stopword and long enough to be indexed?
	 * 
	 * @param string $word
	 * @return bool
	 * 
	 */
	protected function isIndexableWord($word) {
		if($this->isShortword($word)) return false;
		if($this->isStopword($word)) return false;
		return true;
	}
		
	/**
	 * Get unique score field name
	 * 
	 * @return string
	 * @since 3.0.160
	 * 
	 */
	protected function getScoreFieldName() {
		$key = $this->tableName . '_' . $this->fieldName;
		self::$scoreCnts[$key] = isset(self::$scoreCnts[$key]) ? self::$scoreCnts[$key] + 1 : 0;
		return '_score_' . $key . self::$scoreCnts[$key];
	}
	
	/**
	 * Get minimum allowed indexable word length
	 *
	 * @return int
	 *
	 */
	protected function getMinWordLength() {
		// note: ft_min_word_len is automatically changed to InnoDB’s equivalent when applicable
		if($this->minWordLength !== null) return $this->minWordLength;
		$this->minWordLength = (int) $this->database->getVariable('ft_min_word_len');
		return $this->minWordLength;
	}

	/**
	 * Get other variations of given word to search (such as plural, singular, lemmas, etc.)
	 * 
	 * @param string $word
	 * @param int|null $minLength Minimum length for returned words
	 * @return array
	 * 
	 */
	protected function getWordAlternates($word, $minLength = null) {
		if($minLength === null) $minLength = $this->getMinWordLength();
		return $this->wire()->sanitizer->getTextTools()->getWordAlternates($word, array(
			'operator' => $this->operator, 
			'lowercase' => true, 
			'minLength' => $minLength,
		));
	}

	/**
	 * Get root of word (currently not implemented)
	 * 
	 * @param string $word
	 * @return string
	 * 
	 */
	protected function getWordRoot($word) {
		if($word) {}
		return '';
	}

	/**
	 * Call forceLike(true) to force use of LIKE, or omit argument to get current setting
	 * 
	 * This forces LIKE only for matching operators that have a LIKE equivalent.
	 * This includes these operators: `*=`, `^=`, `$=`, `~=`, `~|=`.
	 * 
	 * @param bool|null $forceLike
	 * @return bool
	 * @since 3.0.182
	 * 
	 */
	public function forceLike($forceLike = null) {
		if(is_bool($forceLike)) $this->forceLike = $forceLike;
		return $this->forceLike;
		
	}
}
