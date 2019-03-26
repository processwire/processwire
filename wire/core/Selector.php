<?php namespace ProcessWire;

/**
 * ProcessWire Selector base type and implementation for various Selector types
 *
 * Selectors hold a field, operator and value and are used in finding things
 *
 * This file provides the base implementation for a Selector, as well as implementation
 * for several actual Selector types under the main Selector class. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
 * - `SelectorStarts`
 * - `SelectorStartsLike`
 * - `SelectorEnds`
 * - `SelectorEndsLike`
 * - `SelectorBitwiseAnd`
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
 * @property null|bool $forceMatch When boolean, it forces match (true) or non-match (false). (default=null) #pw-group-properties
 * 
 */
abstract class Selector extends WireData {

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

		$not = false; 
		if(!is_array($field) && isset($field[0]) && $field[0] == '!') {
			$not = true; 
			$field = ltrim($field, '!'); 
		}

		$this->set('field', $field); 	
		$this->set('value', $value); 
		$this->set('not', $not); 
		$this->set('group', null); // group name identified with 'group_name@' before a field name
		$this->set('quote', ''); // if $value in quotes, this contains either: ', ", [, {, or (, indicating quote type (set by Selectors class)
		$this->set('forceMatch', null); // boolean true to force match, false to force non-match
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
				$value = $this->wire('sanitizer')->selectorValue($value); 
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
		if($key == 'operator') return $this->operator();
		if($key == 'str') return $this->__toString();
		if($key == 'values') return $this->values();
		if($key == 'fields') return $this->fields();
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
			if(is_array($value)) $value = $this->wire('sanitizer')->selectorValue($value);
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
	 * Set a property of the Selector
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return Selector|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key == 'fields') return parent::set('field', $value);
		if($key == 'values') return parent::set('value', $value); 
		if($key == 'operator') {
			$this->error("You cannot set the operator on a Selector: $this");
			return $this;
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
			if($this->wire('languages') && $value instanceof LanguagesValueInterface) $value = (string) $value; 
				else if($value instanceof WireData) $value = $value->get($field);
				else if($value instanceof WireArray && is_string($field) && !strpos($field, '.')) $value = (string) $value; // 123|456|789, etc.
				else if($value instanceof Wire) $value = $value->$field; 
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
		Selectors::addType(SelectorEqual::getOperator(), 'SelectorEqual'); 
		Selectors::addType(SelectorNotEqual::getOperator(), 'SelectorNotEqual'); 
		Selectors::addType(SelectorGreaterThan::getOperator(), 'SelectorGreaterThan'); 
		Selectors::addType(SelectorLessThan::getOperator(), 'SelectorLessThan'); 
		Selectors::addType(SelectorGreaterThanEqual::getOperator(), 'SelectorGreaterThanEqual'); 
		Selectors::addType(SelectorLessThanEqual::getOperator(), 'SelectorLessThanEqual'); 
		Selectors::addType(SelectorContains::getOperator(), 'SelectorContains'); 
		Selectors::addType(SelectorContainsLike::getOperator(), 'SelectorContainsLike'); 
		Selectors::addType(SelectorContainsWords::getOperator(), 'SelectorContainsWords'); 
		Selectors::addType(SelectorStarts::getOperator(), 'SelectorStarts'); 
		Selectors::addType(SelectorStartsLike::getOperator(), 'SelectorStartsLike'); 
		Selectors::addType(SelectorEnds::getOperator(), 'SelectorEnds'); 
		Selectors::addType(SelectorEndsLike::getOperator(), 'SelectorEndsLike'); 
		Selectors::addType(SelectorBitwiseAnd::getOperator(), 'SelectorBitwiseAnd'); 
	}
}

/**
 * Selector that matches equality between two values
 *
 */
class SelectorEqual extends Selector {
	public static function getOperator() { return '='; }
	protected function match($value1, $value2) { return $this->evaluate($value1 == $value2); }
}

/**
 * Selector that matches two values that aren't equal
 *
 */
class SelectorNotEqual extends Selector {
	public static function getOperator() { return '!='; }
	protected function match($value1, $value2) { return $this->evaluate($value1 != $value2); }
}

/**
 * Selector that matches one value greater than another
 *
 */
class SelectorGreaterThan extends Selector { 
	public static function getOperator() { return '>'; }
	protected function match($value1, $value2) { return $this->evaluate($value1 > $value2); }
}

/**
 * Selector that matches one value less than another
 *
 */
class SelectorLessThan extends Selector { 
	public static function getOperator() { return '<'; }
	protected function match($value1, $value2) { return $this->evaluate($value1 < $value2); }
}

/**
 * Selector that matches one value greater than or equal to another
 *
 */
class SelectorGreaterThanEqual extends Selector { 
	public static function getOperator() { return '>='; }
	protected function match($value1, $value2) { return $this->evaluate($value1 >= $value2); }
}

/**
 * Selector that matches one value less than or equal to another
 *
 */
class SelectorLessThanEqual extends Selector { 
	public static function getOperator() { return '<='; }
	protected function match($value1, $value2) { return $this->evaluate($value1 <= $value2); }
}

/**
 * Selector that matches one string value (phrase) that happens to be present in another string value
 *
 */
class SelectorContains extends Selector { 
	public static function getOperator() { return '*='; }
	protected function match($value1, $value2) { return $this->evaluate(stripos($value1, $value2) !== false); }
}

/**
 * Same as SelectorContains but serves as operator placeholder for SQL LIKE operations
 *
 */
class SelectorContainsLike extends SelectorContains { 
	public static function getOperator() { return '%='; }
}

/**
 * Selector that matches one string value that happens to have all of it's words present in another string value (regardless of individual word location)
 *
 */
class SelectorContainsWords extends Selector { 
	public static function getOperator() { return '~='; }
	protected function match($value1, $value2) { 
		$hasAll = true; 
		$words = preg_split('/[-\s]/', $value2, -1, PREG_SPLIT_NO_EMPTY);
		foreach($words as $key => $word) if(!preg_match('/\b' . preg_quote($word) . '\b/i', $value1)) {
			$hasAll = false;
			break;
		}
		return $this->evaluate($hasAll); 
	}
}

/**
 * Selector that matches if the value exists at the beginning of another value
 *
 */
class SelectorStarts extends Selector { 
	public static function getOperator() { return '^='; }
	protected function match($value1, $value2) { return $this->evaluate(stripos(trim($value1), $value2) === 0); }
}

/**
 * Selector that matches if the value exists at the beginning of another value (specific to SQL LIKE)
 *
 */
class SelectorStartsLike extends SelectorStarts {
	public static function getOperator() { return '%^='; }
}

/**
 * Selector that matches if the value exists at the end of another value
 *
 */
class SelectorEnds extends Selector { 
	public static function getOperator() { return '$='; }
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
}

/**
 * Selector that matches a bitwise AND '&'
 *
 */
class SelectorBitwiseAnd extends Selector { 
	public static function getOperator() { return '&'; }
	protected function match($value1, $value2) { return $this->evaluate(((int) $value1) & ((int) $value2)); }
}

