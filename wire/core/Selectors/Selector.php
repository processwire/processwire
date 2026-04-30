<?php namespace ProcessWire;

/**
 * ProcessWire Selector base type and implementation for various Selector types
 *
 * Selectors hold a field, operator and value and are used in finding things
 *
 * This file provides the base implementation for a Selector, as well as implementation
 * for several actual Selector types under the main Selector class. 
 * 
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-summary Selector maintains a single selector consisting of field name, operator, and value.
 *
 * #pw-body =
 * - Serves as the base class for the different Selector types (`SelectorEqual`, `SelectorNotEqual`, `SelectorLessThan`, etc.)
 * - The constructor requires `$field` and `$value` properties which may either be an array or string. 
 *   An array indicates multiple items in an OR condition. Multiple items may also be specified by
 *   pipe “|” separated strings.  
 * - Operator is determined by the Selector class name, and thus may not be changed without replacing
 *   the entire Selector. 
 * 
 * ~~~~~
 * // very basic usage example
 * // constructor takes ($field, $value) which can be strings or arrays
 * $s = new SelectorEqual('title', 'About Us');
 * // $page can be any kind of Wire-derived object
 * if($s->matches($page)) {
 *   // $page has title "About Us"
 * }
 * ~~~~~
 * ~~~~~
 * // another usage example
 * $s = new SelectorContains('title|body|summary', 'foo|bar'); 
 * if($s->matches($page)) {
 *   // the title, body or summary properties of $page contain either the text "foo" or "bar" 
 * }
 * ~~~~~
 * 
 * ### List of core selector-derived classes
 * 
 * - `SelectorEqual`
 * - `SelectorNotEqual`
 * - `SelectorGreaterThan`
 * - `SelectorLessThan`
 * - `SelectorGreaterThanEqual`
 * - `SelectorLessThanEqual`
 * - `SelectorContains`
 * - `SelectorContainsLike`
 * - `SelectorContainsWords`
 * - `SelectorContainsWordsPartial` (3.0.160+)
 * - `SelectorContainsWordsLive` (3.0.160)
 * - `SelectorContainsWordsLike` (3.0.160)
 * - `SelectorContainsWordsExpand` (3.0.160)
 * - `SelectorContainsAnyWords` (3.0.160)
 * - `SelectorContainsAnyWordsPartial` (3.0.160)
 * - `SelectorContainsAnyWordsLike` (3.0.160)
 * - `SelectorContainsExpand` (3.0.160)
 * - `SelectorContainsMatch` (3.0.160)
 * - `SelectorContainsMatchExpand` (3.0.160)
 * - `SelectorContainsAdvanced` (3.0.160)
 * - `SelectorStarts`
 * - `SelectorStartsLike`
 * - `SelectorEnds`
 * - `SelectorEndsLike`
 * - `SelectorBitwiseAnd`
 * 
 * 
 * #pw-body
 * 
 * @property array $fields Fields that were present in selector (same as $field, but always an array).
 * @property string|array $field Field or fields present in the selector (string if single, or array of strings if multiple). Preferable to use $fields property instead.
 * @property-read string $operator Operator used by the selector.
 * @property array $values Values that were present in selector (same as $value, but always array).
 * @property string|array $value Value or values present in the selector (string if single, or array of strings if multiple). Preferable to use $values property instead.
 * @property bool $not Is this a NOT selector? Indicates the selector returns the opposite if what it would otherwise. #pw-group-properties
 * @property string|null $group Group name for this selector (if field was prepended with a "group_name@"). #pw-group-properties
 * @property string $quote Type of quotes value was in, or blank if it was not quoted. One of: '"[{( #pw-group-properties
 * @property-read string $str String value of selector, i.e. “a=b”. #pw-group-properties
 * @property null|bool $forceMatch When boolean, forces match (true) or force non-match (false). (default=null) #pw-group-properties
 * @property array $altOperators Alternate operators to use when primary fails match, supported only by compareTypeFind. Since 3.0.161 (default=[]) #pw-group-properties
 * 
 */
abstract class Selector extends WireData {

	/**
	 * Comparison type: Exact (value equals this or value does not equal this)
	 * 
	 */
	const compareTypeExact = 1;

	/**
	 * Comparison type: Sort (matches based on how it would sort among given value)
	 * 
	 */
	const compareTypeSort = 2;

	/**
	 * Comparison type: Find (text value is found within another text value)
	 * 
	 */
	const compareTypeFind = 4;

	/**
	 * Comparison type: Like (text value is like another, combined with compareTypeFind)
	 * 
	 */
	const compareTypeLike = 8; 

	/**
	 * Comparison type: Bitwise 
	 *
	 */
	const compareTypeBitwise = 16;

	/**
	 * Comparison type: Expand (value can be expanded to include other results when supported)
	 * 
	 */
	const compareTypeExpand = 32;
	
	/**
	 * Comparison type: Command (value can contain additional commands interpreted by the Selector)
	 *
	 */
	const compareTypeCommand = 64;
	
	/**
	 * Comparison type: Database (Selector is only applicable for database-driven comparisons)
	 *
	 */
	const compareTypeDatabase = 128;

	/**
	 * Comparison type: Fulltext index required when used with database queries
	 *
	 */
	const compareTypeFulltext = 256;

	/**
	 * Comparison type: Perform phrase match (1+ words in order)
	 */
	const compareTypePhrase = 512;

	/**
	 * Comparison type: Match as words independent of order (opposite of phrase)
	 * 
	 */
	const compareTypeWords = 1024;

	/**
	 * Comparison type: Partial matches allowed, such as partial words or phrases
	 * 
	 */
	const compareTypePartial = 2048;

	/**
	 * Comparison type: If multiple items in query, ANY of them may match 
	 * 
	 */
	const compareTypeAny = 4096;

	/**
	 * Comparison type: If multiple items in query, ALL of them may match
	 *
	 */
	const compareTypeAll = 8192;

	/**
	 * Comparison type: Matches at boundary (start or end)
	 * 
	 */
	const compareTypeBoundary = 16384; 
	
	/**
	 * Given a field name and value, construct the Selector. 
	 *
	 * If the provided $field is an array or pipe "|" separated string, Selector may match any of them (OR field condition)
	 * If the provided $value is an array of pipe "|" separated string, Selector may match any one of them (OR value condition).
	 * 
	 * If only one field is provided as a string, and that field is prepended by an exclamation point, i.e. !field=something
	 * then the condition is reversed. 
	 *
	 * @param string|array $field 
	 * @param string|int|array $value 
	 *
	 */
	public function __construct($field, $value) {
		parent::__construct();
		$this->set('not', false);
		$this->set('group', null); // group name identified with 'group_name@' before a field name
		$this->set('quote', ''); // if $value in quotes, this contains either: ', ", [, {, or (, indicating quote type (set by Selectors class)
		$this->set('forceMatch', null); // boolean true to force match, false to force non-match
		parent::set('altOperators', array()); // optional alternate operators
		$this->setField($field);
		$this->setValue($value);
	}

	/**
	 * Return the operator used by this Selector
	 * 
	 * @return string
	 * @since 3.0.42 Prior versions just supported the 'operator' property.
	 * 
	 */
	public function operator() {
		return static::getOperator();
	}

	/**
	 * Get the field(s) of this Selector
	 * 
	 * Note that if calling this as a property (rather than a method) it can return either a string or an array.
	 * 
	 * @param bool|int $forceString Specify one of the following:
	 *  - `true` (bool): to only return a string, where multiple-fields will be split by pipe "|". (default)
	 *  - `false` (bool): to return string if 1 field, or array of multiple fields (same behavior as field property).
	 *  - `1` (int): to return only the first value (string).
	 * @return string|array|null
	 * @since 3.0.42 Prior versions only supported the 'field' property. 
	 * @see Selector::fields()
	 * 
	 */
	public function field($forceString = true) {
		$field = parent::get('field');
		if($forceString && is_array($field)) {
			if($forceString === 1) {
				$field = reset($field);
			} else {
				$field = implode('|', $field);
			}
		} 
		return $field;
	}

	/**
	 * Return array of field(s) for this Selector
	 * 
	 * @return array
	 * @see Selector::field()
	 * @since 3.0.42 Prior versions just supported the 'fields' property. 
	 * 
	 */
	public function fields() {
		$field = parent::get('field');
		if(is_array($field)) return $field;
		if(!strlen($field)) return array();
		return array($field); 
	}

	/**
	 * Get the value(s) of this Selector
	 *
	 * Note that if calling this as a property (rather than a method) it can return either a string or an array.
	 *
	 * @param bool|int $forceString Specify one of the following:
	 *  - `true` (bool): to only return a string, where multiple-values will be split by pipe "|". (default)
	 *  - `false` (bool): to return string if 1 value, or array of multiple values (same behavior as value property).
	 *  - `1` (int): to return only the first value (string).
	 * @return string|array|null
	 * @since 3.0.42 Prior versions only supported the 'value' property.
	 * @see Selector::values()
	 *
	 */
	public function value($forceString = true) {
		$value = parent::get('value');
		if($forceString && is_array($value)) {
			if($forceString === 1) {
				$value = reset($value);
			} else {
				$value = $this->wire()->sanitizer->selectorValue($value); 
			}
		}
		return $value;
	}

	/**
	 * Return array of value(s) for this Selector
	 *
	 * @param bool $nonEmpty If empty array will be returned, forces it to return array with one blank item instead (default=false). 
	 * @return array
	 * @see Selector::value()
	 * @since 3.0.42 Prior versions just supported the 'values' property. 
	 *
	 */
	public function values($nonEmpty = false) {
		$values = parent::get('value');
		if(is_array($values)) {
			// ok
		} else if(is_string($values)) {
			$values = strlen($values) ? array($values) : array();
		} else if(is_object($values)) {
			$values = $values instanceof WireArray ? $values->getArray() : array($values);
		} else if($values) {
			$values = array($values);
		} else {
			$values = array();
		}
		if($nonEmpty && !count($values)) $values = array('');
		return $values; 
	}

	/**
	 * Get a property 
	 * 
	 * @param string $key Property name
	 * @return array|mixed|null|string Property value
	 * 
	 */
	public function get($key) {
		if($key === 'operator') return $this->operator();
		if($key === 'str') return $this->__toString();
		if($key === 'values') return $this->values();
		if($key === 'fields') return $this->fields();
		if($key === 'label') return $this->getLabel();
		return parent::get($key); 
	}

	/**
	 * Returns the selector field(s), optionally forcing as string or array
	 * 
	 * #pw-internal
	 * 
	 * @param string $type Omit for automatic, or specify 'string' or 'array' to force return in that type
	 * @return string|array
	 * @throws WireException if given invalid type
	 * 
	 */
	public function getField($type = '') {
		$field = $this->field;
		if($type == 'string') {
			if(is_array($field)) $field = implode('|', $field);
		} else if($type == 'array') {
			if(!is_array($field)) $field = array($field);
		} else if($type) {
			throw new WireException("Unknown type '$type' specified to getField()");
		}
		return $field;
	}

	/**
	 * Set field or fields
	 * 
	 * @param string|array $field
	 * @return self
	 * @since 3.0.160
	 * 
	 */
	public function setField($field) {
		if(is_array($field)) $field = implode('|', $field);
		$field = (string) $field;
		$not = strpos($field, '!') === 0;
		if($not) $field = ltrim($field, '!');
		if(strpos($field, '|') !== false) $field = explode('|', $field);
		parent::set('field', $field);
		parent::set('not', $not);
		return $this;
	}

	/**
	 * Returns the selector value(s) with additional processing and forced type options
	 * 
	 * When the $type argument is not specified, this method may return a string, array or Selectors object. 
	 * A Selectors object is only returned if the value happens to contain an embedded selector. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $type Omit for automatic, or specify 'string' or 'array' to force return in that type
	 * @return string|array|Selectors
	 * @throws WireException if given invalid type
	 * 
	 */
	public function getValue($type = '') {
		$value = $this->value; 
		if($type == 'string') {
			if(is_array($value)) $value = $this->wire()->sanitizer->selectorValue($value);
		} else if($type == 'array') {
			if(!is_array($value)) $value = array($value);
		} else if($this->quote == '[') {
			if(is_string($value) && Selectors::stringHasSelector($value)) {
				$value = $this->wire(new Selectors($value));
			} else if($value instanceof Selectors) {
				// okay
			}
		} else if($type) {
			throw new WireException("Unknown type '$type' specified to getValue()");
		}
		return $value;
	}

	/**
	 * Set selector value(s)
	 * 
	 * @param string|int|array|mixed $value
	 * @return self
	 * @since 3.0.160
	 * 
	 */
	public function setValue($value) {
		parent::set('value', $value);
		return $this;
	}

	/**
	 * Set a property of the Selector
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return Selector|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'fields' || $key === 'field') return $this->setField($value);
		if($key === 'values' || $key === 'value') return $this->setValue($value);
		if($key === 'operator') {
			$this->error("You cannot set the operator on a Selector: $this");
			return $this;
		}
		if($key === 'altOperators') {
			if(!is_array($value)) $value = array();
			$operator = $this->operator();
			foreach($value as $k => $v) {
				// don’t allow current operator to be an altOperator
				if($operator === $v) unset($value[$k]); 
			}
		}
		return parent::set($key, $value); 
	}

	/**
	 * Return the operator used by this Selector
	 *
	 * Strict standards don't let us make static abstract methods, so this one throws an exception if it's not reimplemented.
	 * 
	 * #pw-internal
	 *
	 * @return string
	 * @throws WireException
	 *
	 */
	public static function getOperator() {
		throw new WireException("This getOperator method must be implemented"); 
	}

	/**
	 * What type of comparson does Selector perform?
	 *
	 * @return int Returns a Selector::compareType* constant or 0 if not defined
	 * @since 3.0.154
	 *
	 */
	public static function getCompareType() {
		return 0;
	}

	/*
	public static function getCompareTypeArray() {
		$types = array(
			self::compareTypeExact => 'exact',
			self::compareTypeSort => 'sort',
			self::compareTypeFind => 'find',
			self::compareTypeLike => 'like',
			self::compareTypeBitwise => 'bitwise',
			self::compareTypeExpand => 'expand',
			self::compareTypeCommand => 'command',
			self::compareTypeDatabase => 'database',
			self::compareTypeFulltext => 'fulltext',
			self::compareTypePhrase => 'phrase',
			self::compareTypeWords => 'words',
			self::compareTypePartial => 'partial',
			self::compareTypeAny => 'any',
			self::compareTypeAll => 'all',
			self::compareTypeBoundary => 'boundary',
		);
		$compareType = self::getCompareType();
		$compareTypes = array();
		foreach($types as $flag => $type) {
			if($compareType & $flag; 
		}
	}
	*/
	
	/**
	 * Get short label that describes this Selector
	 *
	 * @return string
	 * @since 3.0.160
	 *
	 */
	public static function getLabel() {
		return '';
	}
	
	/**
	 * Get longer description that describes this Selector
	 *
	 * @return string
	 * @since 3.0.160
	 *
	 */
	public static function getDescription() {
		return '';
	}

	/**
	 * Does $value1 match $value2?
	 *
	 * @param mixed $value1 Dynamic comparison value
	 * @param string $value2 User-supplied value to compare against
	 * @return bool
	 *
	 */
	abstract protected function match($value1, $value2);

	/**
	 * Does this Selector match the given value?
	 *
	 * If the value held by this Selector is an array of values, it will check if any one of them matches the value supplied here. 
	 *
	 * @param string|int|Wire|array $value If given a Wire, then matches will also operate on OR field=value type selectors, where present
	 * @return bool
	 *
	 */
	public function matches($value) {

		$forceMatch = $this->get('forceMatch');
		if(is_bool($forceMatch)) return $forceMatch;
		
		$matches = false;
		$values1 = is_array($this->value) ? $this->value : array($this->value); 
		$field = $this->field; 
		$operator = $this->operator();

		// prepare the value we are comparing
		if(is_object($value)) {
			if($this->wire()->languages && $value instanceof LanguagesValueInterface) {
				$value = (string) $value;
			} else if($value instanceof WireData) {
				$value = $value->get($field);
			} else if($value instanceof WireArray && is_string($field) && !strpos($field, '.')) {
				$value = (string) $value; // 123|456|789, etc.
			} else if($value instanceof Wire) {
				$value = $value->$field;
			}
			$value = (string) $value; 
		}

		if(is_string($value) && strpos($value, '|') !== false) $value = explode('|', $value); 
		if(!is_array($value)) $value = array($value);
		$values2 = $value; 
		unset($value);

		// now we're just dealing with 2 arrays: $values1 and $values2
		// $values1 is the value stored by the selector
		// $values2 is the value passed into the matches() function

		$numMatches = 0;
		$numMatchesRequired = 1; 
		if(($operator === '!=' && !$this->not) || ($this->not && $operator !== '!=')) {
			$numMatchesRequired = count($values1) * count($values2);
		} 
		
		$fields = is_array($field) ? $field : array($field); 
		
		foreach($fields as $field) {
	
			foreach($values1 as $v1) {
	
				if(is_object($v1)) {
					if($v1 instanceof WireData) $v1 = $v1->get($field);
						else if($v1 instanceof Wire) $v1 = $v1->$field; 
				}

				foreach($values2 as $v2) {
					if(empty($v2) && empty($v1)) {
						// normalize empty values so that they will match if both considered "empty"
						$v2 = '';
						$v1 = '';
					}
					if($this->match($v2, $v1)) {
						$numMatches++;
					}
				}
	
				if($numMatches >= $numMatchesRequired) {
					$matches = true;
					break;
				}
			}
			if($matches) break;
		}

		return $matches; 
	}

	/**
	 * Provides the opportunity to override or NOT the condition
	 *
	 * Selectors should include a call to this in their matches function
	 *
	 * @param bool $matches
	 * @return bool
	 *
	 */
	protected function evaluate($matches) {
		$forceMatch = $this->get('forceMatch');
		if(is_bool($forceMatch)) $matches = $forceMatch;
		if($this->not) return !$matches; 
		return $matches; 
	}

	/**
	 * Copy all data from this selector to another
	 * 
	 * #pw-internal
	 * 
	 * @param Selector $selector
	 * @since 3.0.161
	 * 
	 */
	public function copyTo(Selector $selector) {
		$selector->setField($this->field); 
		$selector->setValue($this->value);
		$selector->not = $this->not;
		if($this->group) $selector->group = $this->group; 
		if($this->quote) $selector->quote = $this->quote;
		if(is_bool($this->forceMatch)) $selector->forceMatch = $this->forceMatch;
		if(count($this->altOperators)) $selector->altOperators = $this->altOperators;
	}
	
	/**
	 * Sanitize field name
	 *
	 * @param string|array $fieldName
	 * @return string|array
	 * @todo This needs testing and then to be used by this class
	 *
	 */
	protected function sanitizeFieldName($fieldName) {
		if(strpos($fieldName, '|') !== false) {
			$fieldName = explode('|', $fieldName);
		}
		if(is_array($fieldName)) {
			$fieldNames = array();
			foreach($fieldName as $name) {
				$name = $this->sanitizeFieldName($name);
				if($name !== '') $fieldNames[] = $name;
			}
			return $fieldNames;
		}
		$fieldName = trim($fieldName, '. ');
		if($fieldName === '') return $fieldName;
		if(ctype_alnum($fieldName)) return $fieldName;
		if(ctype_alnum(str_replace(array('.', '_'), '', $fieldName))) return $fieldName;
		return '';
	}

	/**
	 * The string value of Selector is always the selector string that it originated from
	 *
	 */
	public function __toString() {
		
		$openingQuote = $this->quote; 
		$closingQuote = $openingQuote; 
		
		if($openingQuote) {
			if($openingQuote == '[') $closingQuote = ']'; 	
				else if($openingQuote == '{') $closingQuote = '}';
				else if($openingQuote == '(') $closingQuote = ')';
		}
		
		$value = $this->value();
		if($openingQuote) $value = trim($value, $openingQuote . $closingQuote);
		$value = $openingQuote . $value . $closingQuote;
		
		$str = 	
			($this->not ? '!' : '') . 
			(is_null($this->group) ? '' : $this->group . '@') . 
			(is_array($this->field) ? implode('|', $this->field) : $this->field) . 
			$this->operator() . $value;
		
		return $str; 
	}

	/**
	 * Debug info
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		$info = array(
			'field' => $this->field,
			'operator' => $this->operator,
			'value' => $this->value,
		);
		if($this->not) $info['not'] = true;
		if($this->forceMatch) $info['forceMatch'] = true;
		if($this->group) $info['group'] = $this->group; 
		if($this->quote) $info['quote'] = $this->quote;
		$info['string'] = $this->__toString();
		return $info;
	}


	/**
	 * Add all individual selector types to the runtime Selectors
	 * 
	 * #pw-internal
	 *
	 */
	static public function loadSelectorTypes() { 
		$types = array(
			'Equal',
			'NotEqual',
			'GreaterThan',
			'LessThan',
			'GreaterThanEqual',
			'LessThanEqual',
			'Contains',
			'ContainsLike',
			'ContainsWords',
			'ContainsWordsPartial',
			'ContainsWordsLive',
			'ContainsWordsLike',
			'ContainsWordsExpand',
			'ContainsAnyWords',
			'ContainsAnyWordsPartial',
			'ContainsAnyWordsLike',
			'ContainsAnyWordsExpand',
			'ContainsExpand',
			'ContainsMatch',
			'ContainsMatchExpand',
			'ContainsAdvanced',
			'Starts',
			'StartsLike',
			'Ends',
			'EndsLike',
			'BitwiseAnd',
		);
		foreach($types as $type) {
			$class = "Selector$type";
			/** @var Selector $className */
			$className = __NAMESPACE__ . "\\$class";
			$operator = $className::getOperator();
			Selectors::addType($operator, $class);
		}
	}
}

/**
 * Selector that matches equality between two values
 *
 */
class SelectorEqual extends Selector {
	public static function getOperator() { return '='; }
	public static function getCompareType() { return Selector::compareTypeExact; }
	public static function getLabel() { return __('Equals', __FILE__); }
	public static function getDescription() { return __('Given value is the same as value compared to.', __FILE__); }
	protected function match($value1, $value2) { return $this->evaluate($value1 == $value2); }
}

/**
 * Selector that matches two values that aren't equal
 *
 */
class SelectorNotEqual extends Selector {
	public static function getOperator() { return '!='; }
	public static function getCompareType() { return Selector::compareTypeExact; }
	public static function getLabel() { return __('Not equals', __FILE__); }
	public static function getDescription() { return __('Given value is not the same as value compared to.', __FILE__); }
	protected function match($value1, $value2) { return $this->evaluate($value1 != $value2); }
}

/**
 * Selector that matches one value greater than another
 *
 */
class SelectorGreaterThan extends Selector { 
	public static function getOperator() { return '>'; }
	public static function getCompareType() { return Selector::compareTypeSort; }
	public static function getLabel() { return __('Greater than', __FILE__); }
	public static function getDescription() { return __('Compared value is greater than given value.', __FILE__); }
	protected function match($value1, $value2) { return $this->evaluate($value1 > $value2); }
}

/**
 * Selector that matches one value less than another
 *
 */
class SelectorLessThan extends Selector { 
	public static function getOperator() { return '<'; }
	public static function getCompareType() { return Selector::compareTypeSort; }
	public static function getLabel() { return __('Less than', __FILE__); }
	public static function getDescription() { return __('Compared value is less than given value.', __FILE__); }
	protected function match($value1, $value2) { return $this->evaluate($value1 < $value2); }
}

/**
 * Selector that matches one value greater than or equal to another
 *
 */
class SelectorGreaterThanEqual extends Selector { 
	public static function getOperator() { return '>='; }
	public static function getCompareType() { return Selector::compareTypeSort; }
	public static function getLabel() { return __('Greater than or equal', __FILE__); }
	public static function getDescription() { return __('Compared value is greater than or equal to given value.', __FILE__); }
	protected function match($value1, $value2) { return $this->evaluate($value1 >= $value2); }
}

/**
 * Selector that matches one value less than or equal to another
 *
 */
class SelectorLessThanEqual extends Selector { 
	public static function getOperator() { return '<='; }
	public static function getCompareType() { return Selector::compareTypeSort; }
	public static function getLabel() { return __('Less than or equal', __FILE__); }
	public static function getDescription() { return __('Compared value is less than or equal to given value.', __FILE__); }
	protected function match($value1, $value2) { return $this->evaluate($value1 <= $value2); }
}

/**
 * Selector that matches one string value (phrase) that happens to be present in another string value
 *
 */
class SelectorContains extends Selector { 
	public static function getOperator() { return '*='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypePartial |
			Selector::compareTypePhrase | 
			Selector::compareTypeFulltext;
	}
	public static function getLabel() { return __('Contains phrase', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase fulltext'); }
	protected function match($value1, $value2) { 
		$matches = stripos($value1, $value2) !== false && preg_match('/\b' . preg_quote($value2) . '/i', $value1); 
		return $this->evaluate($matches);
	}

	/**
	 * Build description from predefined keys for SelectorContains* classes
	 * 
	 * @param array|string $keys
	 * @return string
	 * 
	 */
	public static function buildDescription($keys) {
		$a = array();
		if(!is_array($keys)) $keys = explode(' ', $keys);
		foreach($keys as $key) {
			switch($key) {
				case 'text': $a[] = __('Given text appears in value compared to.', __FILE__); break;
				case 'phrase': $a[] = __('Given phrase or word appears in value compared to.', __FILE__); break;
				case 'phrase-start': $a[] = __('Given word or phrase appears at beginning of compared value.', __FILE__); break;
				case 'phrase-end': $a[] = __('Given word or phrase appears at end of compared value.', __FILE__); break;
				case 'expand': $a[] = __('Expand to include potentially related terms and word variations.', __FILE__); break;
				case 'words-all': $a[] = __('All given words appear in compared value, in any order.', __FILE__); break;
				case 'words-any': $a[] = __('Any given words appear in compared value, in any order.', __FILE__); break;
				case 'words-match': $a[] = __('Any given words match against compared value.', __FILE__); break;
				case 'words-whole': $a[] = __('Matches whole words.', __FILE__); break;
				case 'words-partial': $a[] = __('Matches whole or partial words.', __FILE__); break;
				case 'words-partial-any': $a[] = __('Partial matches anywhere within words.', __FILE__); break;
				case 'words-partial-begin': $a[] = __('Partial matches from beginning of words.', __FILE__); break;
				case 'words-partial-last': $a[] = __('Partial matches last word in given value.', __FILE__); break;
				case 'fulltext': $a[] = __('Uses “fulltext” index.', __FILE__); break;
				case 'like': $a[] = __('Matches using “like”.', __FILE__); break;
				case 'like-words': $a[] = __('Matches without regard to word boundaries (using “like”).', __FILE__); break;
				default: $a[] = "UNKNOWN:$key";
			}
		}
		return implode(' ', $a);
	}
}

/**
 * Same as SelectorContains but query expansion when used for database searching
 *
 */
class SelectorContainsExpand extends SelectorContains {
	public static function getOperator() { return '*+='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypePartial |
			Selector::compareTypePhrase | 
			Selector::compareTypeExpand | 
			Selector::compareTypeDatabase | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains phrase expand', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase expand fulltext'); }
}

/**
 * Same as SelectorContains but serves as operator placeholder for SQL LIKE operations
 *
 */
class SelectorContainsLike extends SelectorContains {
	public static function getOperator() { return '%='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypePartial |
			Selector::compareTypePhrase |
			Selector::compareTypeLike;
	}
	public static function getLabel() { return __('Contains text like', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase like'); }
	protected function match($value1, $value2) { return $this->evaluate(stripos($value1, $value2) !== false); }
}

/**
 * Selector that matches one string value that happens to have all of its words present in another string value (regardless of individual word location)
 *
 */
class SelectorContainsWords extends Selector { 
	public static function getOperator() { return '~='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypeWords | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains all words', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-all words-whole fulltext'); }
	protected function match($value1, $value2) { 
		$hasAll = true; 
		$words = $this->wire()->sanitizer->wordsArray($value2); 
		foreach($words as $word) if(!preg_match('/\b' . preg_quote($word) . '\b/i', $value1)) {
			$hasAll = false;
			break;
		}
		return $this->evaluate($hasAll); 
	}
}

/**
 * Selector that matches all given words in whole or in part starting with
 *
 */
class SelectorContainsWordsPartial extends Selector {
	public static function getOperator() { return '~*='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypePartial |
			Selector::compareTypeWords | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains all partial words', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-all words-partial words-partial-begin fulltext'); }
	protected function match($value1, $value2) {
		$hasAll = true;
		$words = $this->wire()->sanitizer->wordsArray($value2); 
		foreach($words as $word) {
			if(!preg_match('/\b' . preg_quote($word) . '/i', $value1)) {
				$hasAll = false;
				break;
			}
		}
		return $this->evaluate($hasAll);
	}
}

/**
 * Selector that matches partial words at either beginning or ending
 *
 */
class SelectorContainsWordsLike extends Selector {
	public static function getOperator() { return '~%='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypePartial |
			Selector::compareTypeWords |
			Selector::compareTypeLike;
	}
	public static function getLabel() { return __('Contains all words like', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-all words-partial words-partial-any like'); }
	protected function match($value1, $value2) {
		$hasAll = true;
		$words = $this->wire()->sanitizer->wordsArray($value2);
		foreach($words as $word) {
			if(stripos($value1, $word) === false) {
				$hasAll = false;
				break;
			}
		}
		return $this->evaluate($hasAll);
	}
}

/**
 * Selector that matches entire words except for last word, which must start with
 * 
 * Useful in matching "live" search results where someone is typing and last word may be partial.
 *
 */
class SelectorContainsWordsLive extends Selector {
	public static function getOperator() { return '~~='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAll | 
			Selector::compareTypeWords |
			Selector::compareTypePartial |
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains all words live', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-all words-partial-last fulltext'); }
	protected function match($value1, $value2) {
		$hasAll = true;
		$words = $this->wire()->sanitizer->wordsArray($value2); 
		$lastWord = array_pop($words);
		foreach($words as $word) {
			if(!preg_match('/\b' . preg_quote($word) . '\b/i', $value1)) {
				// full-word match
				$hasAll = false;
				break;
			}
		}
		// last word only needs to match beginning of word
		$hasAll = $hasAll && preg_match('\b' . preg_quote($lastWord) . '/i', $value1);
		return $this->evaluate($hasAll);
	}
}

/**
 * Selector that matches all words with query expansion
 *
 */
class SelectorContainsWordsExpand extends SelectorContainsWords {
	public static function getOperator() { return '~+='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypeWords |
			Selector::compareTypeExpand | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains all words expand', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-all words-whole expand fulltext'); }
}

/**
 * Selector that has any of the given whole words (only 1 needs to match)
 *
 */
class SelectorContainsAnyWords extends Selector {
	public static function getOperator() { return '~|='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny | 
			Selector::compareTypeWords |
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains any words', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-any words-whole fulltext'); }
	protected function match($value1, $value2) {
		$hasAny = false;
		$words = $this->wire()->sanitizer->wordsArray($value2);
		foreach($words as $word) {
			if(stripos($value1, $word) !== false) {
				if(preg_match('!\b' . preg_quote($word) . '\b!i', $value1)) {
					$hasAny = true;
					break;
				}
			}
		}
		return $this->evaluate($hasAny);
	}
}

/**
 * Selector that has any of the given partial words (starting with, only 1 needs to match)
 *
 */
class SelectorContainsAnyWordsPartial extends Selector {
	public static function getOperator() { return '~|*='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny |
			Selector::compareTypePartial | 
			Selector::compareTypeWords |
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains any partial words', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-any words-partial words-partial-begin fulltext'); }
	protected function match($value1, $value2) {
		$hasAny = false;
		$words = $this->wire()->sanitizer->wordsArray($value2);
		foreach($words as $word) {
			if(stripos($value1, $word) !== false) {
				if(preg_match('!\b' . preg_quote($word) . '!i', $value1)) {
					$hasAny = true;
					break;
				}
			}
		}
		return $this->evaluate($hasAny);
	}
}

/**
 * Selector that has any words like any of those given (only 1 needs to match)
 *
 */
class SelectorContainsAnyWordsLike extends Selector {
	public static function getOperator() { return '~|%='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny |
			Selector::compareTypePartial |
			Selector::compareTypeWords | 
			Selector::compareTypeLike;
	}
	public static function getLabel() { return __('Contains any words like', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-any words-partial words-partial-any like'); }
	protected function match($value1, $value2) {
		$hasAny = false;
		$words = $this->wire()->sanitizer->wordsArray($value2);
		foreach($words as $word) {
			if(stripos($value1, $word) !== false) {
				$hasAny = true;
				break;
			}
		}
		return $this->evaluate($hasAny);
	}
}

/**
 * Selector that matches any words with query expansion
 *
 */
class SelectorContainsAnyWordsExpand extends SelectorContainsAnyWords {
	public static function getOperator() { return '~|+='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny |
			Selector::compareTypeWords | 
			Selector::compareTypeExpand | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains any words expand', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-any expand fulltext'); }
	protected function match($value1, $value2) {
		$hasAny = false;
		$textTools = $this->wire()->sanitizer->getTextTools();
		$words = $this->wire()->sanitizer->wordsArray($value2);
		foreach($words as $word) {
			if(stripos($value1, $word) !== false && preg_match('/\b' . preg_quote($word) . '\b/i', $value1)) {
				$hasAny = true;
				break;
			}
			$alternates = $textTools->getWordAlternates($word); 
			foreach($alternates as $alternate) {
				if(stripos($value1, $alternate) && preg_match('/\b' . preg_quote($alternate) . '\b/i', $value1)) {
					$hasAny = true;
					break;
				}
			}
			if($hasAny) break;
		}
		return $this->evaluate($hasAny);
	}
}


/**
 * Selector that uses standard MySQL MATCH/AGAINST behavior with implied DB-score sorting
 *
 * This selector is only useful for database $pages->find() queries. 
 *
 */
class SelectorContainsMatch extends SelectorContainsAnyWords {
	public static function getOperator() { return '**='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny | 
			Selector::compareTypeWords | 
			Selector::compareTypeDatabase | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains match', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-match words-whole fulltext'); }
}

/**
 * Selector that uses standard MySQL MATCH/AGAINST behavior with implied DB-score sorting
 *
 * This selector is only useful for database $pages->find() queries. 
 *
 */
class SelectorContainsMatchExpand extends SelectorContainsMatch {
	public static function getOperator() { return '**+='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny |
			Selector::compareTypeWords | 
			Selector::compareTypeExpand | 
			Selector::compareTypeDatabase | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Contains match expand', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('words-match words-whole expand fulltext'); }
}

/**
 * Selector for advanced text searches that interprets specific search commands
 * 
 * - `foo` Optional word has no prefix.
 * - `+foo` Required word has a "+" prefix.
 * - `+foo*` Required words starting with "foo" (i.e. "fool", "foobar", etc.) has "+" prefix and "*" wildcard suffix.
 * - `-bar` Disallowed word has a "-" prefix.
 * - `-bar*` Disallowed words starting with "bar" (i.e. "barn", "barbell", etc.) has "-" prefix and "*" wildcard suffix.
 * - `"foo bar baz"` Optional phrase surrounded in quotes.
 * - `+"foo bar baz"` Required phrase with "+" prefix followed by double-quoted value. 
 * - `-"foo bar baz"` Disallowed phrase with "-" prefix followed by double-quoted value. 
 * 
 * Note that to designate a phrase, it must be in "double quotes" (not 'single quotes'). 
 *
 */
class SelectorContainsAdvanced extends SelectorContains {
	public static function getOperator() { return '#='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAny | 
			Selector::compareTypeWords |
			Selector::compareTypePhrase |
			Selector::compareTypeCommand | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Advanced text search', __FILE__); }
	public static function getDescription() {
		return 
			__('Match values with commands: +Word MUST appear, -Word MUST NOT appear, and unprefixed Word may appear.', __FILE__) . ' ' . 
			__('Add asterisk for partial match: Bar* or +Bar* matches bar, barn, barge; while -Bar* prevents matching them.') . ' ' . 
			__('Use quotes to match phrases: +(Must Match), -(Must Not Match), or (May Match).'); 
	}

	/**
	 * Return array of advanced search commands from given value
	 * 
	 * @param string $value
	 * @return array
	 * 
	 */
	public function valueToCommands($value) {
		$commands = array();
		$hasQuotes = strrpos($value, '"') || strrpos($value, '”') || strrpos($value, ')') || strrpos($value, '}');
		$substr = function_exists('\\mb_substr') ? '\\mb_substr' : '\\substr';
		$re = '/[-+]?("[^"]+"|\([^)]+\))\*?/';
		if($hasQuotes && preg_match_all($re, $value, $matches)) {
			// find all quoted phrases
			foreach($matches[0] as $key => $fullMatch) {
				$type = substr($fullMatch, 0, 1);
				$partial = substr($fullMatch, -1) === '*';
				if($type !== '+' && $type !== '-') $type = '';
				$phrase = $matches[1][$key];
				$phrase = trim($phrase, $substr($phrase, 0, 1) . $substr($phrase, -1)); // remove quotes
				$phrase = str_replace('+', '', trim($phrase, '-')); 
				if(strpos($phrase, '-') !== false) $phrase = preg_replace('/([^\w\d])-(.)/', '$1 $2', $phrase); 
				$value = str_replace($fullMatch, ' ', $value);
				while(strpos($phrase, '  ') !== false) $phrase = str_replace('  ', ' ', $phrase);
				if(!strlen($phrase)) continue;
				$phrase = str_replace('"', '', $phrase);
				$query = $type . '"' . $phrase . '"' . ($partial ? '*' : ''); 
				$a = array('type' => $type, 'value' => $phrase, 'query' => $query, 'partial' => $partial, 'phrase' => true);
				$commands[] = $a;
			}
		}
		$words = $this->wire()->sanitizer->wordsArray($value, array(
			'keepChars' => array('+', '-', '*')
		));
		foreach($words as $word) {
			$type = substr($word, 0, 1);
			$partial = substr($word, -1) === '*';
			if($type !== '+' && $type !== '-') $type = '';
			$word = trim($word, '+-*');
			$query = $type . $word . ($partial ? '*' : ''); 
			$a = array('type' => $type, 'value' => $word, 'query' => $query, 'partial' => $partial, 'phrase' => false);
			$commands[] = $a;
		}
		return $commands;
	}
	
	protected function match($value1, $value2) {
		$fail = false;
		$numMatch = 0;
		$numOptional = 0; 
		$commands = $this->valueToCommands($value2);
		foreach($commands as $command) {
			$re = '/\b' . preg_quote($command['value']) . ($command['partial'] ? '' : '\b') . '/i';
			$match = preg_match($re, $value1);
			if($command['type'] === '+') {
				// value must be present (+)
				if(!$match) $fail = true;
				if(!$fail) $numMatch++;
			} else if($command['type'] === '-') {
				// value must not be present (-)
				if($match) $fail = true;
				if(!$fail) $numMatch++;
			} else { 
				// value may be present (blank type)
				if($match) $numMatch++;
				$numOptional++;
			}
			if($fail) break;
		}
		if(!$fail && $numOptional && !$numMatch) $fail = true;
		return $this->evaluate(!$fail);
	}
}

/**
 * Selector that matches if the value exists at the beginning of another value
 *
 */
class SelectorStarts extends Selector { 
	public static function getOperator() { return '^='; }
	public static function getCompareType() { 
		return 
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypePhrase |
			Selector::compareTypeBoundary | 
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Starts with', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase-start fulltext'); }
	protected function match($value1, $value2) { 
		return $this->evaluate(stripos(trim($value1), $value2) === 0); 
	}
}

/**
 * Selector that matches if the value exists at the beginning of another value (specific to SQL LIKE)
 *
 */
class SelectorStartsLike extends SelectorStarts {
	public static function getOperator() { return '%^='; }
	public static function getCompareType() {
		return
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypePhrase |
			Selector::compareTypeBoundary |
			Selector::compareTypeLike; 
	}
	public static function getLabel() { return __('Starts like', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase-start like'); }
}

/**
 * Selector that matches if the value exists at the end of another value
 *
 */
class SelectorEnds extends Selector { 
	public static function getOperator() { return '$='; }
	public static function getCompareType() {
		return
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypePhrase |
			Selector::compareTypeBoundary |
			Selector::compareTypeFulltext; 
	}
	public static function getLabel() { return __('Ends with', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase-end fulltext'); }
	protected function match($value1, $value2) { 
		$value2 = trim($value2); 
		$value1 = substr($value1, -1 * strlen($value2));
		return $this->evaluate(strcasecmp($value1, $value2) == 0);
	}
}

/**
 * Selector that matches if the value exists at the end of another value (specific to SQL LIKE)
 *
 */
class SelectorEndsLike extends SelectorEnds {
	public static function getOperator() { return '%$='; }
	public static function getCompareType() {
		return
			Selector::compareTypeFind |
			Selector::compareTypeAll |
			Selector::compareTypePhrase |
			Selector::compareTypeBoundary |
			Selector::compareTypeLike; 
	}
	public static function getLabel() { return __('Ends like', __FILE__); }
	public static function getDescription() { return SelectorContains::buildDescription('phrase-end like'); }
}

/**
 * Selector that matches a bitwise AND '&'
 *
 */
class SelectorBitwiseAnd extends Selector { 
	public static function getOperator() { return '&'; }
	public static function getCompareType() { return Selector::compareTypeBitwise; }
	public static function getLabel() { return __('Bitwise AND', __FILE__); }
	public static function getDescription() {
		return __('Given integer value matches bitwise AND with compared integer value.', __FILE__);
	}
	protected function match($value1, $value2) { return $this->evaluate(((int) $value1) & ((int) $value2)); }
}

