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
	 * Is it a NOT operator?
	 * 
	 * @var bool
	 * 
	 */
	protected $not = false;

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
		'matchWords' => array('~=', '~+=', '~*=', '~~=', '~|=', '~|*='),
		'matchLikeWords' => array('~%=', '~|%='),
		'matchLikePhrase' => array('%='),
		'matchLikeStartEnd' => array('%^=', '%$='),
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
		$this->fieldName = $this->database->escapeCol($fieldName);
		
		if(strpos($operator, '!') === 0 && $operator !== '!=') {
			$this->not = true;
			$operator = ltrim($operator, '!');
		}
		
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
			$value = $this->value($value);
			$method = $this->method;
			if(strlen($value)) $this->$method($value);
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
		// if($this->operator === '*=') $this->operator = '%='; 
		
		$query = $this->query;
		$this->query = $this->wire(new DatabaseQuerySelect());
		$this->query->bindOption(true, $query->bindOption(true)); 
		$method = $this->method;
		
		foreach($value as $v) {
			$v = $this->value("$v"); 
			if(strlen($v)) $this->$method($v);
		}
		
		// @todo need to get anything else from substitute query?
		$query->where('(' . implode(') OR (', $this->query->where) . ')');
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
	 * Match LIKE phrase
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchLikePhrase($value) {
		$likeType = $this->not ? 'NOT LIKE' : 'LIKE';
		$this->query->where("$this->tableField $likeType ?", '%' . $this->escapeLIKE($value) . '%');
	}

	/**
	 * Match starts-with or ends-with using only LIKE 
	 * 
	 * @param string $value
	 * 
	 */
	protected function matchLikeStartEnd($value) {
		$likeType = $this->not ? 'NOT LIKE' : 'LIKE';
		if(strpos($this->operator, '^') !== false) {
			$this->query->where("$this->tableField $likeType ?", $this->escapeLIKE($value) . '%');
		} else {
			$this->query->where("$this->tableField $likeType ?", '%' . $this->escapeLIKE($value));
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
			$word = $this->escapeLIKE($word);
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

		$tableField = $this->tableField();
		$operator = $this->operator;
		$required = strpos($operator, '|') === false;
		$partial = strpos($operator, '*') !== false;
		$partialLast = $operator === '~~=';
		$expand = $operator === '~+=';
		$matchType = $this->not ? 'NOT MATCH' : 'MATCH';
		$scoreField = $this->getScoreFieldName();
		$matchAgainst = '';
		
		$data = $this->getBooleanModeWords($value, array(
			'required' => $required, 
			'partial' => $partial, 
			'partialLast' => $partialLast
		));
	
		if($expand) {
			$bindKey = $this->query->bindValueGetKey($this->escapeAGAINST($data['value']));
			$matchAgainst = "$matchType($tableField) AGAINST($bindKey WITH QUERY EXPANSION)";
		} else if(!empty($data['booleanValue'])) {
			$bindKey = $this->query->bindValueGetKey($data['booleanValue']);
			$matchAgainst = "$matchType($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
		}
		
		if($matchAgainst) {
			$this->query->where($matchAgainst);
			$this->query->select("$matchAgainst AS $scoreField");
			$this->query->orderby("$scoreField DESC");
		}
		
		if(!empty($data['likeWords'])) {
			// stopwords or words that were too short to use fulltext index
			$wheres = array();
			$likeType = $this->not ? 'NOT RLIKE' : 'RLIKE';
			foreach($data['likeWords'] as $word) {
				$word = $this->escapeLIKE($word);
				if(!strlen($word)) continue;
				$likeValue = '([[:blank:]]|[[:punct:]]|[[:space:]]|>|^)' . preg_quote($word);
				if($partial || ($partialLast && $word === $data['lastWord'])) {
					// just match partial word from beginning
				} else {
					// match to word-end
					$likeValue .= '([[:blank:]]|[[:punct:]]|[[:space:]]|<|$)';
				}
				$bindKey = $this->query->bindValueGetKey($likeValue); 
				$wheres[] = "($tableField $likeType $bindKey)";
			}
			if(count($wheres)) {
				$and = $required ? ' AND ' : ' OR ';
				$this->query->where(implode($and, $wheres)); 
			}
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
		$not = strpos($this->operator, '!') === 0;
		$likeValue = '';
		$words = $this->words($value);
		$lastWord = count($words) > 1 ? array_pop($words) : '';
		$numWords = count($words);
		$numGoodWords = 0;
		
		foreach($words as $word) {
			if(!$this->isStopword($word)) $numGoodWords++;
		}
	
		if($numGoodWords === 0) {
			// 0 non-stopwords to search: do not use match/against
			$againstValue = '';
		} else if($numWords === 1) {
			// 1 word search: non-quoted word only, partial match
			$againstValue = '+' . $this->escapeAgainst(reset($words)) . '*';
		} else {
			// 2+ words and at least one is good (non-stopword), use quoted phrase 
			$againstValue = '+"' . $this->escapeAgainst(implode(' ', $words)) . '"'; 
		}
		
		if($lastWord !== '' || !strlen($againstValue)) {
			// match entire phrase with LIKE as secondary qualifier that includes last word
			// so that we can perform a partial match on the last word only. This is necessary
			// because we can’t use partial match qualifiers in or out of quoted phrases
			// if word is indexable let it contribute to final score
			$lastWord = strlen($lastWord) ? $this->escapeAgainst($lastWord) : '';
			if(strlen($lastWord) && $this->isIndexableWord($lastWord)) {
				// expand the againstValue to include the last word as a required partial match
				$againstValue = trim("$againstValue +$lastWord*");
			}
			$likeValue = '([[:blank:]]|[[:punct:]]|[[:space:]]|>|^)' . preg_quote($value);
		}
		
		if(strlen($againstValue)) {
			// use MATCH/AGAINST
			$bindKey = $this->query->bindValueGetKey($againstValue);
			$match = $not ? 'NOT MATCH' : 'MATCH';
			$matchAgainst = "$match($tableField) AGAINST($bindKey IN BOOLEAN MODE)";
			$scoreField = $this->getScoreFieldName();
			$this->query->select("$matchAgainst AS $scoreField");
			$this->query->where($matchAgainst);
			$this->query->orderby("$scoreField DESC");
		}

		if(strlen($likeValue)) {
			// LIKE is used as a secondary qualifier to MATCH/AGAINST so that it is
			// performed only on rows already identified from FULLTEXT index, unless 
			// no MATCH/AGAINST could be created due to stopwords or too-short words
			$likeType = $not ? 'NOT RLIKE' : 'RLIKE';
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
		
		// *+= phrase match with query expansion: use MATCH/AGAINST and confirm with LIKE
		
		$tableField = $this->tableField();
		$not = strpos($this->operator, '!') === 0;
		$words = $this->words($value, array('indexable' => true));
		$againstValue = $this->escapeAGAINST(implode(' ', $words));
		$wheres = array();
		
		if(count($words) && strlen($againstValue)) {
			// use MATCH/AGAINST as pre-filter
			$match = $not ? 'NOT MATCH' : 'MATCH';
			$bindKey = $this->query->bindValueGetKey($againstValue);
			$matchAgainst = "$match($tableField) AGAINST($bindKey WITH QUERY EXPANSION)";
			$scoreField = $this->getScoreFieldName();
			$wheres[] = $matchAgainst;
			$this->query->select("$matchAgainst AS $scoreField");
			$this->query->orderby("$scoreField DESC");
		}
		
		$likeType = $not ? 'NOT RLIKE' : 'RLIKE';
		$likeValue = '([[:blank:]]|[[:punct:]]|[[:space:]]|>|^)' . preg_quote($value);
		$bindKey = $this->query->bindValueGetKey($likeValue);
		$wheres[] = "($tableField $likeType $bindKey)";
		
		$this->query->where(implode(' OR ', $wheres)); 
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
		$not = strpos($this->operator, '!') === 0;
		$scoreField = $this->getScoreFieldName();
		
		// standard MATCH/AGAINST with optional query expansion
		$words = $this->words($value, array('indexable' => true));
		$againstValue = $this->escapeAGAINST(implode(' ', $words));
		
		if(!count($words) || !strlen(trim($againstValue))) {
			// query contains no indexbale words: force non-match
			if(strlen($value)) $this->query->where('1>2');
			return;
		}
		
		$match = $not ? 'NOT MATCH' : 'MATCH';
		$bindKey = $this->query->bindValueGetKey($againstValue);
		$againstType = strpos($this->operator, '+') ? 'WITH QUERY EXPANSION' : '';
		$where = "$match($tableField) AGAINST($bindKey $againstType)";
		$this->query->select("$where AS $scoreField");
		$this->query->where($where); 
		$this->query->orderby("$scoreField DESC");
	}

	/**
	 * Match phrase at start or end of field value
	 * 
	 * @param $value
	 * 
	 */
	protected function matchStartEnd($value) {
		
		// ^=   Starts with
		// $=   Ends with
	
		$tableField = $this->tableField();
		$not = strpos($this->operator, '!') === 0;
		
		$words = $this->words($value, array('indexable' => true));
		$againstValue = count($words) ? $this->escapeAGAINST(implode(' ', $words)) : '';
	
		if(strlen($againstValue)) {
			// use MATCH/AGAINST to pre-filter before RLIKE when possible
			$bindKey = $this->query->bindValueGetKey($againstValue);
			$match = $not ? 'NOT MATCH' : 'MATCH';
			$matchAgainst = "$match($tableField) AGAINST($bindKey)";
			$scoreField = $this->getScoreFieldName();
			$this->query->select("$matchAgainst AS $scoreField");
			$this->query->where($matchAgainst);
			$this->query->orderby("$scoreField DESC");
		}

		$likeType = $not ? 'NOT RLIKE' : 'RLIKE';
		$likeValue = preg_quote($value);
		
		if(strpos($this->operator, '^') !== false) {
			// starts with phrase, [optional non-visible html or whitespace] plus query text
			$likeValue = '^[[:space:]]*(<[^>]+>)*[[:space:]]*' . $likeValue;
		} else {
			// ends with phrase, [optional punctuation and non-visible HTML/whitespace]
			$likeValue .= '[[:space:]]*[[:punct:]]*[[:space:]]*(<[^>]+>)*[[:space:]]*$';
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
		$matchAgainst = "MATCH($tableField) AGAINST($bindKey IN BOOLEAN MODE) ";
		$select = "$matchAgainst AS $scoreField ";
		$this->query->select($select);
		$this->query->orderby("$scoreField DESC");
		$this->query->where($matchAgainst);
	}

	/**
	 * Get verbose data array of words identified and prepared for boolean mode
	 *
	 * @param string $value
	 * @param array $options
	 *  - `required` (bool): Are given words required in the query? (default=true)
	 *  - `partial` (bool): Is it okay to match a partial value? i.e. can "will" match "willy" (default=false)
	 *  - `partialLast` (bool): Use partial only for last word? (default=null, auto-detect)
	 *  - `phrase` (bool): Is entire $value a full phrase to match? (default=auto-detect)
	 *  - `useStopwords` (bool): Allow inclusion of stopwords? (default=null, auto-detect)
	 * @return string|array Value provided to the function with boolean operators added, or verbose array.
	 *
	 */
	protected function getBooleanModeWords($value, array $options = array()) {
		
		$defaults = array(
			'required' => true, 
			'partial' => false, 
			'partialLast' => ($this->operator === '~~=' || $this->operator === '^='),
			'useStopwords' => true,
		);

		$options = array_merge($defaults, $options);
		$minWordLength = (int) $this->database->getVariable('ft_min_word_len');
		$value = $this->escapeAGAINST($value);
		$booleanValues = array();
		$partial = $options['partial'] ? '*' : '';
		$required = $options['required'] ? '+' : '';
		$useStopwords = is_bool($options['useStopwords']) ? $options['useStopwords'] : $partial === '*';
		$lastWord = null;
		$goodWords = array();
		$stopWords = array();
		$shortWords = array();
		$likeWords = array();

		// get all words
		$words = $this->words($value);
	
		if($options['partialLast']) {
			// treat last word separately (partial last word for live or starts-with searches)
			// only last word is partial
			$lastWord = end($words);
			$partial = '';
		}
		
		// iterate through all words to build boolean query values
		foreach($words as $key => $word) {
			$length = strlen($word);
			if(!$length) continue;
			
			if($this->isStopword($word)) {
				// handle stop-word
				$stopWords[$word] = $word;
				if($useStopwords) $booleanValues[$word] = $word . $partial;
			} else if($length < $minWordLength) {
				// handle too-short word
				$booleanValues[$word] = $required . $word . $partial;
				$shortWords[$word] = $word;
			} else {
				// handle regular word
				$booleanValues[$word] = $required . $word . $partial;
				$goodWords[$word] = $word;
			}
		}
		
		if(strlen($lastWord)) {
			// only last word allowed to be a partial match word
			$lastRequired = isset($stopWords[$lastWord]) ? '' : $required;
			$booleanValues[$lastWord] = $lastRequired . $lastWord . '*';
		}

		$badWords = array_merge($stopWords, $shortWords);
		
		if(count($stopWords)) {
			$numOkayWords = count($goodWords) + count($shortWords);
			foreach($stopWords as $word) {
				$likeWords[$word] = $word;
				if($numOkayWords) {
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
			'value' => trim(implode(' ', $words)), 
			'booleanValue' => trim(implode(' ', $booleanValues)),
			'booleanWords' => $booleanValues,
			'likeWords' => $likeWords,
			'allWords' => $words,
			'goodWords' => $goodWords,
			'badWords' => $badWords, 
			'stopWords' => $stopWords, 
			'shortWords' => $shortWords, 
			'lastWord' => $lastWord, 
			'minWordLength' => $minWordLength, 
		);
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
			'minWordLength' => 1, // minimum allowed length or true for ft_min_word_len
			'stopwords' => true, // allow stopwords
			'indexable' => false, // include only indexable words?
		);
		
		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		if($options['minWordLength'] === true) $options['minWordLength'] = (int) $this->database->getVariable('ft_min_word_len');
		$words = $this->wire()->sanitizer->wordsArray($value, $options);
	
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
	 * @param string $value
	 * @return int
	 * 
	 */
	protected function strlen($value) {
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
	 * Is given word not a stopword and long enough to be indexed?
	 * 
	 * @param string $word
	 * @return bool
	 * 
	 */
	protected function isIndexableWord($word) {
		static $minWordLength = null;
		// note: ft_min_word_len is automatically changed to InnoDB’s equivalent when applicable
		if($minWordLength === null) $minWordLength = (int) $this->database->getVariable('ft_min_word_len');
		if($minWordLength && $this->strlen($word) < $minWordLength) return false;
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
		$n = 0;
		do {
			$scoreField = "_score_{$this->tableName}_{$this->fieldName}" . (++$n);
			// $locateField = "_locate_{$tableName}_{$fieldName}$n";
		} while(isset(self::$scoreFields[$scoreField]));
		self::$scoreFields[$scoreField] = 1;
		return $scoreField;
	}
}
