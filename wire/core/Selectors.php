<?php namespace ProcessWire;

require_once(PROCESSWIRE_CORE_PATH . "Selector.php"); 

/**
 * ProcessWire Selectors
 *
 * #pw-summary Processes a selector string into a WireArray of Selector objects. 
 * #pw-summary-static-helpers Static helper methods useful in analyzing selector strings outside of this class. 
 * #pw-body = 
 * This Selectors class is used internally by ProcessWire to provide selector string (and array) matching throughout the core.
 * 
 * ~~~~~
 * $selectors = new Selectors(); 
 * $selectors->init("sale_price|retail_price>100, currency=USD|EUR");
 * if($selectors->matches($page)) {
 *   // selector string matches the given $page (which can be any Wire-derived item)
 * }
 * ~~~~~
 * ~~~~~
 * // iterate and display what's in this Selectors object
 * foreach($selectors as $selector) {
 *   echo "<p>";
 *   echo "Field(s): " . implode('|', $selector->fields) . "<br>"; 
 *   echo "Operator: " . $selector->operator . "<br>"; 
 *   echo "Value(s): " . implode('|', $selector->values) . "<br>";
 *   echo "</p>";
 * }
 * ~~~~~
 * #pw-body
 * 
 * @link https://processwire.com/api/selectors/ Official Selectors Documentation
 * @method Selector[] getIterator()
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @todo Move static helper methods to dedicated API var/class so this class can be more focused
 * @todo Determine whether Selector array handling methods would be better in separate/descending class
 *
 */

class Selectors extends WireArray {

	/**
	 * Maximum length for a selector operator
	 *
	 */
	const maxOperatorLength = 10; 

	/**
	 * Static array of Selector types of $operator => $className
	 *
	 */
	static $selectorTypes = array();

	/**
	 * Array of all individual characters used by operators
	 *
	 */
	static $operatorChars = array();

	/**
	 * Original saved selector string, used for debugging purposes
	 *
	 */
	protected $selectorStr = '';

	/**
	 * Whether or not variables like [user.id] should be converted to actual value
	 * 
	 * In most cases this should be true. 
	 * 
	 * @var bool
	 *
	 */
	protected $parseVars = true;

	/**
	 * API variable names that are allowed to be parsed
	 * 
	 * @var array
	 * 
	 */
	protected $allowedParseVars = array(
		'session', 
		'page', 
		'user',
	);

	/**
	 * Types of quotes selector values may be surrounded in
	 *
	 */
	protected $quotes = array(
		// opening => closing
		'"' => '"',
		"'" => "'",
		'[' => ']',
		'{' => '}',
		'(' => ')',
	);
	
	/**
	 * Given a selector string, extract it into one or more corresponding Selector objects, iterable in this object.
	 * 
	 * @param string|null|array $selector Please omit this argument and use a separate init($selector) call instead. 
	 *
	 */
	public function __construct($selector = null) {
		parent::__construct();
		$this->usesNumericKeys = false;
		$this->indexedByName = false;
		if(!is_null($selector)) $this->init($selector);
	}

	/**
	 * Set the selector string or array (if not set already from the constructor)
	 * 
	 * ~~~~~
	 * $selectors = new Selectors();
	 * $selectors->init("sale_price|retail_price>100, currency=USD|EUR");
	 * ~~~~~
	 * 
	 * @param string|array $selector
	 * 
	 */
	public function init($selector) {
		if(is_array($selector)) {
			$this->setSelectorArray($selector);
		} else if($selector instanceof Selector) {
			$this->add($selector);
		} else {
			$this->setSelectorString($selector);
		}
	}

	/**
	 * Set the selector string 
	 * 
	 * #pw-internal
	 * 
	 * @param string $selectorStr
	 * 
	 */
	public function setSelectorString($selectorStr) {
		$selectorStr = (string) $selectorStr;
		$this->selectorStr = $selectorStr;
		$this->extractString(trim($selectorStr)); 
	}
	
	/**
	 * Import items into this WireArray.
	 * 
	 * #pw-internal
	 * 
	 * @throws WireException
	 * @param string|WireArray $items Items to import.
	 * @return WireArray This instance.
	 *
	 */
	public function import($items) {
		if(is_string($items)) {
			$this->extractString($items); 	
			return $this;
		} else {
			return parent::import($items); 
		}
	}

	/**
	 * Per WireArray interface, return true if the item is a Selector instance
	 * 
	 * #pw-internal
	 * 
	 * @param Selector $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Selector; 
	}

	/**
	 * Per WireArray interface, return a blank Selector
	 * 
	 * #pw-internal
	 * 
	 * @return Selector
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new SelectorEqual('',''));
	}

	/**
	 * Create a new Selector object from a field name, operator, and value
	 * 
	 * This is mostly for internal use, as the Selectors object already does this when you pass it
	 * a selector string in the constructor or init() method. 
	 * 
	 * #pw-group-advanced
	 *
	 * @param string $field Field name or names (separated by a pipe)
	 * @param string $operator Operator, i.e. "="
	 * @param string|array $value Value or values (separated by a pipe)
	 * @return Selector Returns the correct type of `Selector` object that corresponds to the given `$operator`.
	 * @throws WireException
	 *
	 */
	public function create($field, $operator, $value) {
		$not = false;
		if(!isset(self::$selectorTypes[$operator])) {
			// unrecognized operator, see if it's an alternate placement for NOT "!" statement
			$op = ltrim("$operator", '!');
			if(isset(self::$selectorTypes[$op])) {
				$operator = $op;
				$not = true;
			} else {
				if(is_array($value)) $value = implode('|', $value);
				if(is_array($field)) $field = implode('|', $field);
				$debug = $this->wire()->config->debug ? "field='$field', value='$value', selector: '$this->selectorStr'" : "";
				if(empty($operator)) $operator = '[empty]';
				throw new WireException("Unknown Selector operator: '$operator' -- was your selector value properly escaped? $debug");
			}
		}
		$class = wireClassName(self::$selectorTypes[$operator], true); 
		/** @var Selector $selector */
		$selector = $this->wire(new $class($field, $value)); 
		if($not) $selector->not = true;
		return $selector; 		
	}

	/**
	 * Given a selector string, populate to Selector objects in this Selectors instance
	 *
	 * @param string $str The string containing a selector (or multiple selectors, separated by commas)
	 *
	 */
	protected function extractString($str) {

		while(strlen($str)) {

			$not = false;
			$quote = '';	
			if(strpos($str, '!') === 0) {
				$str = ltrim($str, '!');
				$not = true; 
			}
			$group = $this->extractGroup($str); 	
			$field = $this->extractField($str); 
			$operators = $this->extractOperators($str);
			$operator = array_shift($operators);
			$value = $this->extractValue($str, $quote); 

			if($this->parseVars && $quote === '[' && $this->valueHasVar($value)) {
				// parse an API variable property to a string value
				$v = $this->parseValue($value); 
				if($v !== null) {
					$value = $v;
					$quote = '';
				}
			}

			if($field || $value || strlen("$value")) {
				$selector = $this->create($field, $operator, $value);
				if(!is_null($group)) $selector->group = $group; 
				if($quote) $selector->quote = $quote; 
				if($not) $selector->not = true; 
				if(count($operators)) $selector->altOperators = $operators;
				$this->add($selector); 
			}
		}

	}
	
	/**
	 * Given a string like name@field=... or @field=... extract the part that comes before the @
	 *
	 * This part indicates the group name, which may also be blank to indicate grouping with other blank grouped items
	 *
	 * @param string $str
	 * @return null|string
	 *
	 */
	protected function extractGroup(&$str) {
		$group = null;
		$pos = strpos($str, '@'); 
		if($pos === false) return null; 
		if($pos === 0) {
			$group = '';
			$str = substr($str, 1); 
		} else if(preg_match('/^([-_a-zA-Z0-9]*)@(.*)/', $str, $matches)) {
			$group = $matches[1]; 
			$str = $matches[2];
		}
		return $group; 
	}

	/**
	 * Given a string starting with a field, return that field, and remove it from $str. 
	 * 
	 * @param string $str
	 * @return string
	 *
	 */
	protected function extractField(&$str) {
		$field = '';
		
		if(strpos($str, '(') === 0) {
			// OR selector where specification of field name is optional and = operator is assumed
			$str = '=(' . substr($str, 1); 
			return $field; 
		}

		if(preg_match('/^(!?[_|.a-zA-Z0-9]+)(.*)/', $str, $matches)) {

			$field = trim($matches[1], '|'); 
			$str = $matches[2];

			if(strpos($field, '|')) {
				$field = explode('|', $field); 
			}

		}
		return $field; 
	}


	/**
	 * Given a string starting with an operator, return that operator, and remove it from $str. 
	 * 
	 * @param string $str
	 * @param array $operatorChars
	 * @return string
	 * @deprecated Replaced by extractOperators()
	 * @todo this method can be removed once confirmed nothing else uses it
	 *
	 */
	protected function extractOperator(&$str, array $operatorChars) {
		$n = 0;
		$operator = '';
		$lastOperator = '';
		while(isset($str[$n]) && in_array($str[$n], $operatorChars) && $n < self::maxOperatorLength) {
			$operator .= $str[$n]; 
			if(self::isOperator($operator)) {
				$lastOperator = $operator;
			} else if($lastOperator) {
				$operator = $lastOperator;
				break;
			}
			$n++; 
		}
		if($operator) $str = substr($str, $n); 
		return $operator; 
	}
	
	/**
	 * Given a string starting with an operator, return that operator, and remove it from $str.
	 *
	 * @param string $str
	 * @return array
	 *
	 */
	protected function extractOperators(&$str) {
		
		$n = 0;
		$not = false;
		$operator = '';
		$lastOperator = '';
		$operators = array();
		$operatorChars = self::getOperatorChars();
	
		while(isset($str[$n]) && isset($operatorChars[$str[$n]])) {
			$c = $str[$n];
			if($operator === '!' && $c !== '=') {
				// beginning of operator negation that’s not "!="
				$not = true;
				$operator = ltrim($operator, '!');
			}
			$operator .= $c;
			if(self::isOperator($operator)) {
				$lastOperator = $operator;
			} else if($lastOperator) {
				if($not) $lastOperator = "!$lastOperator";
				$operators[$lastOperator] = $lastOperator;
				$lastOperator = '';
				$operator = $c;
				$not = false;
			}
			$n++; 
		}
		
		if($lastOperator) {
			if($not) $lastOperator = "!$lastOperator";
			$operators[$lastOperator] = $lastOperator;
		}
		
		if(count($operators)) {
			$str = substr($str, $n);
		}
		
		if($operator && !isset($operators[$lastOperator])) {
			// leftover characters in $operator, maybe from operator in wrong order
			$fail = true;
			if(!count($operators)) {
				// check if operator has a typo we can fix
				// isOperator with 2nd argument true allows for and corrects some order mixups
				$op = self::isOperator($operator, true);
				if($op) {
					if($not) $op = "!$op";
					$operators[$op] = $op;
					$str = substr($str, $n);
					$fail = false;
				}
			}
			if($fail) {
				throw new WireException("Unrecognized operator: $operator"); 
			}
		}
		
		return $operators;
	}

	/**
	 * Early-exit optimizations for extractValue
	 * 
	 * @param string $str String to extract value from, $str will be modified if extraction successful
	 * @param string $openingQuote Opening quote character, if string has them, blank string otherwise
	 * @param string $closingQuote Closing quote character, if string has them, blank string otherwise
	 * @return false|string|string[] Returns found value if successful, boolean false if not
	 *
	 */
	protected function extractValueQuick(&$str, $openingQuote, $closingQuote) {
		
		// determine where value ends
		$offset = 0;
		if($openingQuote) $offset++; // skip over leading quote
		$commaPos = strpos("$str,", $closingQuote . ',', $offset); // "$str," just in case value is last and no trailing comma
		
		if($commaPos === false && $closingQuote) {
			// if closing quote and comma didn't match, try to match just comma in case of "something"<space>,
			$str1 = substr($str, 1);
			$commaPos = strpos($str1, ',');
			if($commaPos !== false) {
				$closingQuotePos = strpos($str1, $closingQuote); 
				if($closingQuotePos > $commaPos) {
					// comma is in quotes and thus not one we want to work with
					return false;
				} else {
					// increment by 1 since it was derived from a string at position 1 (rather than 0)
					$commaPos++;
				}
			}
		}

		if($commaPos === false) {
			// value is the last one in $str
			$commaPos = strlen($str); 
			
		} else if($commaPos && $str[$commaPos-1] === '//') {
			// escaped comma or closing quote means no optimization possible here
			return false; 
		}
		
		// extract the value for testing
		$value = substr($str, 0, $commaPos);
	
		// if there is an operator present, it might be a subselector or OR-group
		if(self::stringHasOperator($value)) return false;
	
		if($openingQuote) {
			// if there were quotes, trim them out
			$value = trim($value, $openingQuote . $closingQuote); 
		}

		// determine if there are any embedded quotes in the value
		$hasEmbeddedQuotes = false; 
		foreach($this->quotes as $open => $close) {
			if(strpos($value, $open)) $hasEmbeddedQuotes = true; 
		}
		
		// if value contains quotes anywhere inside of it, abort optimization
		if($hasEmbeddedQuotes) return false;
	
		// does the value contain possible OR conditions?
		if(strpos($value, '|') !== false) {
			
			// if there is an escaped pipe, abort optimization attempt
			if(strpos($value, '\\' . '|') !== false) return false; 
		
			// if value was surrounded in "quotes" or 'quotes' abort optimization attempt
			// as the pipe is a literal value rather than an OR
			if($openingQuote == '"' || $openingQuote == "'") return false;
		
			// we have valid OR conditions, so convert to an array
			$value = explode('|', $value); 
		}

		// if we reach this point we have a successful extraction and can remove value from str
		// $str = $commaPos ? trim(substr($str, $commaPos+1)) : '';
		$str = trim(substr($str, $commaPos+1));

		// successful optimization
		return $value; 
	}

	/**
	 * Given a string starting with a value, return that value, and remove it from $str. 
	 *
	 * @param string $str String to extract value from
	 * @param string $quote Automatically populated with quote type, if found
	 * @return array|string Found values or value (excluding quotes)
	 *
	 */
	protected function extractValue(&$str, &$quote) {
		
		$sanitizer = $this->wire()->sanitizer;

		$str = trim($str); 
		if(!strlen($str)) return '';
		
		if(isset($this->quotes[$str[0]])) {
			$openingQuote = $str[0]; 
			$closingQuote = $this->quotes[$openingQuote];
			$quote = $openingQuote; 
			$n = 1; 
		} else {
			$openingQuote = '';
			$closingQuote = '';
			$n = 0; 
		}
		
		$value = $this->extractValueQuick($str, $openingQuote, $closingQuote); // see if we can do a quick exit
		if($value !== false) return $value; 

		$value = '';
		$lastc = '';
		$quoteDepth = 0;
		$inDoubleQuote = false; // applies only if openingQuote is populated and not itself a double quote

		do {
			if(!isset($str[$n])) break;

			$c = $str[$n]; 

			if($openingQuote) {
				// we are in a quoted value string

				if($c === $closingQuote && !$inDoubleQuote) { 
					// closing quote for previously opened quote
					
					if($lastc !== '\\') {
						// same quote that opened, and not escaped or double quoted
						// means the end of the value
						
						if($quoteDepth > 0) {
							// closing of an embedded quote
							$quoteDepth--;
						} else {
							$n++; // skip over quote 
							$quote = $openingQuote;
							break;
						}

					} else {
						// this is an intentionally escaped quote, remove the escape
						$value = rtrim($value, '\\'); 
					}
					
				} else if($c === $openingQuote && $openingQuote !== $closingQuote) {
					// another opening quote of the same type encountered while already in a quote
					if(!$inDoubleQuote) $quoteDepth++;
					
				} else if($c === '"') {
					// double quote char 
					// not reachable if openingQuote was a double quote
					if($inDoubleQuote) {
						// closing a previously opened double quote
						$inDoubleQuote = false;
					} else {
						// potentially applicable double quote
						list($on, $op) = array($n, '', '');
						// check if an operator came before the quote
						while($on > 0 && isset(self::$operatorChars[$str[--$on]])) {
							$op = self::$operatorChars[$str[$on]] . $op;
						}
						// if something valid does prefix the operator, cancel the operator
						if(!$on || !$sanitizer->fieldName($str[$on])) $op = '';
						// if an operator came before the quote, and it closes somewhere,
						// we will allow the embedded double quote
						if(strlen($op) && self::isOperator($op) && strrpos($str, '"') > $n) {
							// opening a double quote after an operator
							$inDoubleQuote = true;
						} else {
							// abandon the double quote
							$c = null;
						}
					}
				}

			} else {
				// we are in an un-quoted value string

				if($c == ',' || $c == '|') {
					if($lastc != '\\') {
						// a non-quoted, non-escaped comma terminates the value
						break;

					} else {
						// an intentionally escaped comma
						// so remove the escape
						$value = rtrim($value, '\\'); 
					}
				}
			}

			if($c !== null) {
				$value .= $c;
				$lastc = $c;
			}

		} while(++$n);
		
		if($inDoubleQuote) $value .= '"'; // close double quote
		
		$len = strlen("$value");
		if($len) {
			$str = substr($str, $n);
			// if($len > self::maxValueLength) $value = substr($value, 0, self::maxValueLength);
		}

		$str = ltrim($str, ' ,"\']})'); // should be executed even if blank value

		// check if a pipe character is present next, indicating an OR value may be provided
		if(strlen($str) > 1 && substr($str, 0, 1) == '|') {
			$str = substr($str, 1); 
			// perform a recursive extract to account for all OR values
			$v = $this->extractValue($str, $quote); 
			$quote = ''; // we don't support separately quoted OR values
			$value = array($value); 
			if(is_array($v)) $value = array_merge($value, $v); 
				else $value[] = $v; 
		}

		return $value; 
	}

	/**
	 * Given a value string with an "api_var" or "api_var.property" return the string value of the property
	 * 
	 * #pw-internal
	 *
	 * @param string $value var or var.property
	 * @return null|string Returns null if it doesn't resolve to anything or a string of the value it resolves to
	 *
	 */
	public function parseValue($value) {
		if(!preg_match('/^\$?[_a-zA-Z0-9]+(?:\.[_a-zA-Z0-9]+)?$/', $value)) return null;
		$property = '';
		if(strpos($value, '.')) list($value, $property) = explode('.', $value); 
		if(!in_array($value, $this->allowedParseVars)) return null; 
		$value = $this->wire($value); 
		if(is_null($value)) return null; // does not resolve to API var
		if(empty($property)) return (string) $value;  // no property requested, just return string value 
		if(!is_object($value)) return null; // property requested, but value is not an object
		return (string) $value->$property; 
	}
	
	/**
	 * Set whether or not vars should be parsed
	 *
	 * By default this is true, so only need to call this method to disable variable parsing.
	 * 
	 * #pw-internal
	 *
	 * @param bool $parseVars
	 *
	 */
	public function setParseVars($parseVars) {
		$this->parseVars = $parseVars ? true : false;
	}

	/**
	 * Does the given Selector value contain a parseable value?
	 * 
	 * #pw-internal
	 * 
	 * @param Selector $selector
	 * @return bool
	 * 
	 */
	public function selectorHasVar(Selector $selector) {
		if($selector->quote != '[') return false; 
		$has = false;
		foreach($selector->values as $value) {
			if($this->valueHasVar($value)) {
				$has = true; 
				break;
			}
		}
		return $has;
	}

	/**
	 * Does the given value contain an API var reference?
	 * 
	 * It is assumed the value was quoted in "[value]", and the quotes are not there now. 
	 * 
	 * #pw-internal
	 *
	 * @param string $value The value to evaluate
	 * @return bool
	 *
	 */
	public function valueHasVar($value) {
		if(self::stringHasOperator($value)) return false;
		if(strpos($value, '.') !== false) {
			list($name, $subname) = explode('.', $value);
		} else {
			$name = $value;
			$subname = '';
		}
		if(!in_array($name, $this->allowedParseVars)) return false;
		if(strlen($subname) && $this->wire()->sanitizer->fieldName($subname) !== $subname) return false;
		return true; 
	}

	/**
	 * Return array of all field names referenced in all of the Selector objects here
	 * 
	 * @param bool $subfields Default is to allow "field.subfield" fields, or specify false to convert them to just "field".
	 * @return array Returned array has both keys and values as field names (same)
	 * 
	 */
	public function getAllFields($subfields = true) {
		$fields = array();
		foreach($this as $selector) {
			$field = $selector->field;
			if(!is_array($field)) $field = array($field);
			foreach($field as $f) {
				if(!$subfields && strpos($f, '.')) {
					list($f, $subfield) = explode('.', $f, 2);
					if($subfield) {} // ignore
				}
				$fields[$f] = $f;
			}
		}
		return $fields;
	}

	/**
	 * Return array of all values referenced in all Selector objects here
	 * 
	 * @return array Returned array has both keys and values as field values (same)
	 * 
	 */
	public function getAllValues() {
		$values = array();
		foreach($this as $selector) {
			$value = $selector->value;
			if(!is_array($value)) $value = array($value);
			foreach($value as $v) {
				$values[$v] = $v;
			}
		}
		return $values;
	}

	/**
	 * Does the given Wire match these Selectors?
	 * 
	 * @param Wire $item
	 * @return bool
	 * 
	 */ 
	public function matches(Wire $item) {

		// if item provides it's own matches function (like Page), then let it have control
		if($item instanceof WireMatchable) return $item->matches($this);

		$orGroups = array();
		$matches = true;

		foreach($this as $selector) {
			if($selector->quote === '(' && self::stringHasOperator($selector->value())) {
				$name = $selector->field();
				if(!isset($orGroups[$name])) $orGroups[$name] = array();
				$orGroups[$name][] = $selector->value;
			} else {
				$matches = $this->matchesSelector($selector, $item);
				if(!$matches) break;
			}
		}

		if($matches && count($orGroups)) {
			$matches = $this->matchesOrGroups($orGroups, $item);
		}

		return $matches;
	}

	/**
	 * Does the given Wire match these Selector (single)?
	 *
	 * @param Selector $selector
	 * @param Wire $item
	 * @return bool
	 * @since 3.0.330
	 *
	 */
	protected function matchesSelector(Selector $selector, Wire $item) {
		$value = array();
		
		foreach($selector->fields as $property) {
			if(strpos($property, '.') && $item instanceof WireData) {
				$v = $item->getDot($property);
			} else {
				$v = $item->$property;
			}
			if(is_array($v)) {
				$value = array_merge($value, $v);
			} else {
				$value[] = (string) $v;
			}
		}
	
		$matches = $selector->matches($value);
		if($matches) return true;
		
		// attempt any alternate operators, if present
		foreach($selector->altOperators as $altOperator) {
			$altSelector = self::getSelectorByOperator($altOperator);
			if(!$altSelector) continue;
			$this->wire($altSelector);
			$selector->copyTo($altSelector);
			$matches = $altSelector->matches($value);
			if($matches) break;
		}

		return $matches;
	}
	
	/**
	 * Do the given OR-groups match the given Wire?
	 *
	 * @param array|string[]|array[] $orGroups
	 * @param Wire $item
	 * @return bool
	 * @since 3.0.330
	 *
	 */
	protected function matchesOrGroups(array $orGroups, Wire $item) {
		$matches = true;
		foreach($orGroups as $selectorStrings) {
			$orGroupMatches = false;
			foreach($selectorStrings as $s) {
				/** @var Selectors $orGroupSelectors */
				$orGroupSelectors = $this->wire(new Selectors($s));
				if(!$orGroupSelectors->matches($item)) continue;
				$orGroupMatches = true;
				break;
			}
			if(!$orGroupMatches) {
				$matches = false;
				break;
			}
		}
		return $matches;
	}

	/**
	 * Return string indicating given data type for use in selector arrays
	 * 
	 * @param int|string|array $data
	 * @return string
	 * 
	 */
	protected function getSelectorArrayType($data) {
		$dataType = '';
		if(is_int($data)) {
			$dataType = 'int';
		} else if(is_string($data)) {
			$dataType = 'string';
		} else if(is_array($data)) {
			$dataType = ctype_digit(implode('', array_keys($data))) ? 'array' : 'assoc';
			if($dataType == 'assoc' && isset($data['field'])) $dataType = 'verbose';
		} 
		return $dataType;	
	}

	/**
	 * Extract and return operator from end of field name, as used by selector arrays
	 * 
	 * @param string $field
	 * @return array
	 * 
	 */
	protected function getOperatorsFromField(&$field) {
		
		$operators = array_keys(self::$selectorTypes);
		$operatorsStr = implode('', $operators);
		$c = substr($field, -1);
		if(ctype_alnum($c)) return array('=');

		$op = '';
		while(strpos($operatorsStr, $c) !== false && strlen($field)) {
			$op = $c . $op;
			$field = substr($field, 0, -1);
			$c = substr($field, -1); 
		}
		
		if(empty($op)) return array('='); 
		
		$operators = $this->extractOperators($op);
		
		return $operators;
	}

	/**
	 * Create this Selectors object from an array
	 * 
	 * #pw-internal
	 *
	 * @param array $a
	 * @throws WireException
	 *
	 */
	public function setSelectorArray(array $a) {
		
		$groupCnt = 0;
		
		// fields that may only appear once in a selector
		$singles = array(
			'start' => '',
			'limit' => '',
			'end' => '',
		);
		
		foreach($a as $key => $data) {
			
			$keyType = $this->getSelectorArrayType($key);
			$dataType = $this->getSelectorArrayType($data);
			
			if($keyType == 'int' && $dataType == 'assoc') {
				// OR-group
				$groupCnt++;
				
				foreach($data as $k => $v) {
					$s = $this->makeSelectorArrayItem($k, $v);
					$selector1 = $this->create($s['field'], $s['operator'], $s['value']);
					if(!empty($s['altOperators'])) $selector1->altOperators = $s['altOperators'];
					$selector2 = $this->create("or$groupCnt", "=", $selector1);
					$selector2->quote = '(';
					$this->add($selector2);
				}
				
			} else {
				
				$s = $this->makeSelectorArrayItem($key, $data, $dataType);
				$field = $s['field'];
				
				if(!is_array($field) && isset($singles[$field])) {
					if(empty($singles[$field])) {
						// mark it as present
						$singles[$field] = true;
					} else {
						// skip, because this 'single' field has already appeared
						continue;
					}
				}
				
				$selector = $this->create($field, $s['operator'], $s['value']);
				
				if($s['not']) $selector->not = true;
				if($s['group']) $selector->group = $s['group'];
				if($s['quote']) $selector->quote = $s['quote'];
				if(!empty($s['altOperators'])) $selector->altOperators = $s['altOperators'];
				
				$this->add($selector);
			}
		}
	}

	/**
	 * Return an array of an individual Selector info, for use by setSelectorArray() method
	 * 
	 * @param string|int $key
	 * @param array $data
	 * @param string $dataType One of 'string', 'array', 'assoc', or 'verbose'
	 * @return array
	 * @throws WireException
	 * 
	 */
	protected function makeSelectorArrayItem($key, $data, $dataType = '') {
		
		$sanitizer = $this->wire()->sanitizer;
		$sanitize = 'selectorValue';
		$fields = array();
		$values = array();
		$operators = array('=');
		$whitelist = null;
		$not = false;
		$group = '';
		$find = ''; // sub-selector
		$quote = '';
		
		if(empty($dataType)) $dataType = $this->getSelectorArrayType($data);

		if(is_int($key) && $dataType == 'verbose') {

			// Verbose selector with associative array of properties, in this expected format: 
			// 
			// $data = array(
			//  'field' => array|string, // field name, or field names
			//  'value' => array|string|number|object, // value or values, or omit if using 'find' 
			//  ---the following are optional---
			//  'operator' => '=>', // operator, '=' is the default
			//  'not' => false, // specify true to make this a NOT condition (default=false)
			//  'sanitize' => 'selectorValue', // sanitizer method to use on value(s), 'selectorValue' is default
			//  'find' => array(...), // sub-selector to use instead of 'value'
			//  'whitelist' => null|array, // whitelist of allowed values, NULL is default, which means ignore. 
			//  );

			if(isset($data['fields']) && !isset($data['field'])) $data['field'] = $data['fields']; // allow plural alternate
			if(!isset($data['field'])) {
				throw new WireException("Invalid selectors array, lacks 'field' property for index $key");
			}

			if(isset($data['values']) && !isset($data['value'])) $data['value'] = $data['values']; // allow plural alternate
			if(!isset($data['value']) && !isset($data['find'])) {
				throw new WireException("Invalid selectors array, lacks 'value' property for index $key");
			}

			if(isset($data['sanitizer']) && !isset($data['sanitize'])) $data['sanitize'] = $data['sanitizer']; // allow alternate
			if(isset($data['sanitize'])) $sanitize = $sanitizer->fieldName($data['sanitize']);

			if(!empty($data['operator'])) $operators = $this->extractOperators($data['operator']);
			if(!empty($data['not'])) $not = (bool) $data['not'];

			// may use either 'group' or 'or' to specify or-group
			if(!empty($data['group'])) {
				$group = $sanitizer->fieldName($data['group']);
			} else if(!empty($data['or'])) {
				$group = $sanitizer->fieldName($data['or']);
			}

			if(!empty($data['find'])) {
				if(isset($data['value'])) throw new WireException("You may not specify both 'value' and 'find' at the same time");
				// if(!is_array($data['find'])) throw new WireException("Selector 'find' property must be specified as array"); 
				$find = $data['find'];
				$data['value'] = array();
			}

			if(isset($data['whitelist'])) {
				$whitelist = $data['whitelist'];
				if($whitelist instanceof WireArray) $whitelist = explode('|', (string) $whitelist);
				if(!is_array($whitelist)) $whitelist = array($whitelist);
			}

			if($sanitize && $sanitize != 'selectorValue' && !method_exists($sanitizer, $sanitize)) {
				throw new WireException("Unrecognized sanitize method: " . $sanitizer->name($sanitize));
			}

			$_fields = is_array($data['field']) ? $data['field'] : array($data['field']);
			$_values = is_array($data['value']) ? $data['value'] : array($data['value']);
			
		} else if(is_string($key)) {
			
			// Non-verbose selector, where $key is the field name and $data is the value
			// The $key field name may have an optional operator appended to it
		
			$operators = $this->getOperatorsFromField($key);
			$_fields = strpos($key, '|') ? explode('|', $key) : array($key);
			$_values = is_array($data) ? $data : array($data);
			
		} else if($dataType == 'array') {
			
			// selector in format: array('field', 'operator', 'value', 'sanitizer_method')
			// or array('field', 'operator', 'value', array('whitelist value1', 'whitelist value2', 'etc'))
			// or array('field', 'operator', 'value')
			// or array('field', 'value') where '=' is assumed operator
			$field = '';
			$value = array();
			
			if(count($data) == 4) {
				list($field, $operator, $value, $_sanitize) = $data;
				$operators = $this->extractOperators($operator); 
				if(is_array($_sanitize)) {
					$whitelist = $_sanitize;
				} else {
					$sanitize = $sanitizer->name($_sanitize);
				}

			} else if(count($data) == 3) {
				list($field, $operator, $value) = $data;
				$operators = $this->extractOperators($operator);
				
			} else if(count($data) == 2) {
				list($field, $value) = $data;
				$operators = $this->getOperatorsFromField($field);
			}
		
			if(is_array($field)) {
				$_fields = $field;
			} else {
				$_fields = strpos($field, '|') ? explode('|', $field) : array($field);
			}
			
			$_values = is_array($value) ? $value : array($value);
			
		} else {
			throw new WireException("Unable to resolve selector array");	
		}
	
		// make sure operator is valid
		foreach($operators as $operator) {
			if(!isset(self::$selectorTypes[$operator])) {
				throw new WireException("Unrecognized selector operator '$operator'");
			}
		}
	
		// determine field(s)
		foreach($_fields as $name) {
			if(strpos($name, '.') !== false) {
				// field name with multiple.named.parts, sanitize them separately
				$parts = explode('.', $name);
				foreach($parts as $n => $part) {
					$parts[$n] = $sanitizer->fieldName($part);
				}
				$_name = implode('.', $parts);
			} else {
				$_name = $sanitizer->fieldName($name);
			}
			if($_name !== $name) {
				throw new WireException("Invalid Selectors field name (sanitized value '$_name' did not match specified value)");
			}
			$fields[] = $_name;
		}

		// convert WireArray types to an array of $_values
		if(count($_values) === 1) {
			$value = reset($_values);
			if($value instanceof WireArray) {
				$_values = explode('|', (string) $value);
			}
		}

		// determine value(s)
		foreach($_values as $value) {
			$_sanitize = $sanitize;
			if(is_array($value)) $value = 'array'; // we don't allow arrays here
			if(is_object($value)) $value = (string) $value;
			if(is_int($value) || (ctype_digit("$value") && strpos($value, '0') !== 0)) {
				$value = (int) $value;
				if($_sanitize == 'selectorValue') $_sanitize = ''; // no need to sanitize integer to string
			}
			if(is_array($whitelist) && !in_array($value, $whitelist)) {
				$fieldsStr = implode('|', $fields);
				throw new WireException("Value given for '$fieldsStr' is not in provided whitelist");
			}
			if($_sanitize === 'selectorValue') {
				$value = $sanitizer->selectorValue($value, array('useQuotes' => false)); 
			} else if($_sanitize) {
				$value = $sanitizer->$_sanitize($value);
			}
			$values[] = $value;
		}

		if($find) {
			// sub-selector find
			$quote = '[';
			$values = new Selectors($find);
			
		} else if($group) {
			// groups use quotes '()'
			$quote = '(';
		}
		
		return array(
			'field' => count($fields) > 1 ? $fields : reset($fields), 
			'value' => count($values) > 1 ? $values : reset($values), 
			'operator' => array_shift($operators), 
			'altOperators' => $operators,
			'not' => $not,
			'group' => $group,
			'quote' => $quote, 
		);
	}

	/**
	 * Get the first selector that uses given field name
	 * 
	 * This is useful for quickly retrieving values of reserved properties like "include", "limit", "start", etc. 
	 * 
	 * Using **$or:** By default this excludes selectors that have fields in an OR expression, like "a|b|c". 
	 * So if you specified field "a" it would not be matched. If you wanted it to still match, specify true 
	 * for the $or argument.
	 * 
	 * Using **$all:** By default only the first matching selector is returned. If you want it to return all 
	 * matching selectors in an array, then specify true for the $all argument. This changes the return value
	 * to always be an array of Selector objects, or a blank array if no match. 
	 * 
	 * @param string $fieldName Name of field to return value for (i.e. "include", "limit", etc.)
	 * @param bool $or Allow fields that appear in OR expressions? (default=false)
	 * @param bool $all Return an array of all matching Selector objects? (default=false)
	 * @return Selector|array|null Returns null if field not present in selectors (or blank array if $all mode)
	 * 
	 */
	public function getSelectorByField($fieldName, $or = false, $all = false) {
		
		$selector = null;
		$matches = array();
		
		foreach($this as $sel) {
			if($or) {
				if(!in_array($fieldName, $sel->fields)) continue;
			} else {
				if($sel->field() !== $fieldName) continue;
			}
			if($all) {
				$matches[] = $sel;
			} else {
				$selector = $sel;
				break;
			}
		}
		
		return $all ? $matches : $selector;
	}

	/**
	 * Get the first selector that uses given field name AND has the given value
	 *
	 * Using **$or:** By default this excludes selectors that have fields or values in an OR expression, like "a|b|c".
	 * So if you specified field "a" it would not be matched. If you wanted it to still match, specify true
	 * for the $or argument.
	 *
	 * Using **$all:** By default only the first matching selector is returned. If you want it to return all
	 * matching selectors in an array, then specify true for the $all argument. This changes the return value
	 * to always be an array of Selector objects, or a blank array if no match.
	 *
	 * @param string $fieldName Name of field to match
	 * @param string|int $value Value that must match
	 * @param bool $or Allow fields and values that appear in OR expressions? (default=false)
	 * @param bool $all Return an array of all matching Selector objects? (default=false)
	 * @return Selector|array|null Returns null if field not present in selectors (or blank array if $all mode)
	 * @since 3.0.142
	 *
	 */
	public function getSelectorByFieldValue($fieldName, $value, $or = false, $all = false) {
		
		$selectors = $this->getSelectorByField($fieldName, $or, true); 
		$matches = array();
		
		foreach($selectors as $sel) {
			/** @var Selector $sel */
			if($or) {
				if(in_array($value, $sel->values())) $matches[] = $sel;
			} else {
				if($sel->value() == $value) $matches[] = $sel;
			}
			if(!$all && count($matches)) break;
		}
		
		if($all) return $matches;
		
		return count($matches) ? $matches[0] : null;
	}

	/**
	 * Value when typecast as string
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		$str = '';
		foreach($this as $selector) {
			$str .= $selector->str . ", ";
		}
		return rtrim($str, ", ");
	}

	/**
	 * Debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		$info['string'] = $this->__toString();
		return $info;
	}

	/**
	 * Debug info for Selector item
	 * 
	 * @param Selector|mixed $item
	 * @return array|mixed|null|string
	 * 
	 */
	public function debugInfoItem($item) {
		if($item instanceof Selector) return $item->__debugInfo();
		return parent::debugInfoItem($item);
	}

	/*** STATIC HELPERS *******************************************************************************/
	
	/**
	 * Add a Selector type that processes a specific operator
	 *
	 * Static since there may be multiple instances of this Selectors class at runtime.
	 * See Selector.php
	 *
	 * #pw-internal
	 *
	 * @param string $operator
	 * @param string $class
	 *
	 */
	static public function addType($operator, $class) {
		self::$selectorTypes[$operator] = $class;
		for($n = 0; $n < strlen($operator); $n++) {
			$c = $operator[$n];
			self::$operatorChars[$c] = $c;
		}
	}

	/**
	 * Get all operators allowed by selectors
	 *
	 * #pw-group-static-helpers
	 *
	 * @param array $options
	 *  - `operator` (string): Return info for only this operator. When specified, only value is returned (default='').
	 *  - `compareType` (int): Return only operators matching given `Selector::compareType*` constant (default=0).
	 *  - `getIndexType` (string): Index type to use in returned array: 'operator', 'className', 'class', or 'none' (default='class')
	 *  - `getValueType` (string): Value type to use in returned array: 'operator', 'class', 'className', 'label', 'description', 'compareType', 'verbose' (default='operator').
	 *     If 'verbose' option used then assoc array returned for each operator containing 'class', 'className', 'operator', 'compareType', 'label', 'description'.
	 * @return array|string|int Returned array where values are operators and keys are class names (or requested 'getIndexType or 'getValueType' options)
	 *   If 'operator' option specified, return value is string, int or array (of requested 'getValueType'), and there is no index.
	 * @since 3.0.154
	 *
	 */
	static public function getOperators(array $options = array()) {

		$defaults = array(
			'operator' => '',
			'getIndexType' => 'class',
			'getValueType' => 'operator',
			'compareType' => 0,
		);

		$options = array_merge($defaults, $options);
		$operators = array();
		$compareType = (int) $options['compareType'];
		$indexType = $options['getIndexType'];
		$valueType = $options['getValueType'];
		$selectorTypes = self::$selectorTypes;

		if(!empty($options['operator'])) {
			$operator = $options['operator'];
			if($operator[0] === '!' && $operator !== '!=') {
				// negated operator
				$operator = ltrim($operator, '!');
			}
			if(!isset($selectorTypes[$operator])) {
				// operator does not exist
				if($valueType === 'compareType') return 0;
				return $valueType === 'verbose' ? array() : '';
			}
			$selectorTypes = array($operator => $selectorTypes[$operator]);
		}

		foreach($selectorTypes as $operator => $typeName) {
			$className = __NAMESPACE__ . "\\$typeName";
			if($compareType) {
				/** @var Selector $className */
				if(!($className::getCompareType() & $options['compareType'])) continue;
			}
			if($valueType === 'class') {
				$value = $typeName;
			} else if($valueType === 'className') {
				$value = $className;
			} else if($valueType === 'label') {
				$value = $className::getLabel();
			} else if($valueType === 'description') {
				$value = $className::getDescription();
			} else if($valueType === 'compareType') {
				$value = $className::getCompareType();
			} else if($valueType === 'verbose') {
				$value = array(
					'operator' => $operator,
					'class' => $typeName,
					'className' => $className,
					'compareType' => $className::getCompareType(),
					'label' => $className::getLabel(),
					'description' => $className::getDescription(), 
				);
			} else {
				$value = $operator;
			}
			if($indexType === 'none') {
				$key = '';
			} else if($indexType === 'class') {
				$key = $typeName;
			} else if($indexType === 'className') {
				$key = $className;
			} else {
				$key = $operator;
			}
			if($key === '') {
				$operators[] = $value;
			} else {
				$operators[$key] = $value;
			}
		}

		if(!empty($options['operator'])) return reset($operators);

		return $operators;
	}

	/**
	 * Return array of all valid operator characters
	 *
	 * #pw-group-static-helpers
	 *
	 * @return array
	 *
	 */
	static public function getOperatorChars() {
		return self::$operatorChars;
	}

	/**
	 * Return array of other characters that have meaning in a selector outside of operators
	 *
	 * #pw-group-static-helpers
	 *
	 * @return array
	 * @since 3.0.156
	 *
	 */
	static public function getReservedChars() {
		return array(
			'or' => '|', // title|body=foo, summary=bar|baz
			'not' => '!', // !body*=suchi tobiko
			'separator' => ',', // foo=bar, bar=baz
			'match-same-1' => '@', // @foo.bar=123, @foo.baz=456
			'quote-value' => '"', // foo="bar"
			'or-group-open' => '(', // id>0, (title=foo), (body=bar)
			'or-group-close' => ')',
			'sub-selector-open' => '[', // foo=[bar>0, baz%=text]
			'sub-selector-close' => ']',
			'api-var-open' => '[', // [page], [page.id], [user.id], etc. 
			'api-var-close' => ']',
		);
	}

	/**
	 * Return a string indicating the type of operator that it is, or false if not an operator
	 *
	 * #pw-group-static-helpers
	 *
	 * @param string $operator Operator to check
	 * @param bool $is Change return value to just boolean true or false.
	 * @return bool|string
	 * @since 3.0.108
	 *
	 */
	static public function getOperatorType($operator, $is = false) {
		if(!isset(self::$selectorTypes[$operator])) return false;
		$type = self::$selectorTypes[$operator];
		// now double check that we can map it back, in case PHP filters anything in the isset()
		$op = array_search($type, self::$selectorTypes);
		if($op === $operator) {
			if($is) return true;
			// Convert types like "SelectorEquals" to "Equals"
			if(strpos($type, 'Selector') === 0) list(,$type) = explode('Selector', $type, 2);
			return $type;
		}
		return false;
	}

	/**
	 * Given an operator, return Selector instance (or other requested Selector property)
	 *
	 * When getting a Selector instance, be sure to populate its `field` and `value` properties after retrieving it.
	 *
	 * #pw-group-static-helpers
	 *
	 * @param string $operator Operator to get Selector instance for
	 * @param string $property One of 'instance,', 'label', 'compareType', 'class', 'className' (default='instance')
	 * @return Selector|int|string|false Returns false if operator or property not recognized
	 * @since 3.0.160
	 *
	 */
	static public function getSelectorByOperator($operator, $property = 'instance') {
		if(!isset(self::$selectorTypes[$operator])) return false;
		$typeName = self::$selectorTypes[$operator];
		/** @var Selector $className */
		$className = __NAMESPACE__ . "\\$typeName";
		if($property === 'instance' || $property === '') return new $className('', null);
		if($property === 'compareType') return $className::getCompareType();
		if($property === 'className') return $className;
		if($property === 'label') return $className::getLabel();
		if($property === 'class') return $typeName;
		return false;
	}

	/**
	 * Returns true if given string is a recognized operator, or false if not
	 *
	 * #pw-group-static-helpers
	 *
	 * @param string $operator
	 * @param bool $returnOperator Return the operator rather than bool? When true, corrects minor typos, like mixed up
	 *   order, returning correct found operator string if possible, false otherwise. Added 3.0.162. (default=false)
	 * @return bool|string
	 * @since 3.0.108
	 *
	 */
	static public function isOperator($operator, $returnOperator = false) {
		$is = self::getOperatorType($operator, true);
		if(!$returnOperator || strlen($operator) < 3) return $is;
		if($is) return $operator;
		$op = strrev(trim($operator, '=')) . '=';
		return self::getOperatorType($op, true) ? $op : false;
	}

	/**
	 * Does the given string have an operator in it?
	 *
	 * #pw-group-static-helpers
	 *
	 * @param string $str String that might contain an operator
	 * @param bool $getOperator Specify true to return the operator that was found, or false if not (since 3.0.108)
	 * @return bool
	 *
	 */
	static public function stringHasOperator($str, $getOperator = false) {

		static $letters = 'abcdefghijklmnopqrstuvwxyz';
		static $digits = '_0123456789';

		$has = false;
		$str = (string) $str;

		foreach(self::$selectorTypes as $operator => $unused) {

			if($operator == '&') continue; // this operator is too common in other contexts

			$pos = strpos($str, $operator);
			if(!$pos) continue; // if pos is 0 or false, move onto the next

			// possible match: confirm that field name precedes an operator
			// if(preg_match('/\b[_a-zA-Z0-9]+' . preg_quote($operator) . '/', $str)) {

			$c = $str[$pos-1]; // letter before the operator

			if(stripos($letters, $c) !== false) {
				// if a letter appears as the character before operator, then we're good
				$has = true;

			} else if(strpos($digits, $c) !== false) {
				// if a digit appears as the character before operator, we need to confirm there is at least one letter
				// as there can't be a field named 123, for example, which would mean the operator is likely something 
				// to do with math equations, which we would refuse as a valid selector operator
				$n = $pos-1;
				while($n > 0) {
					$c = $str[--$n];
					if(stripos($letters, $c) !== false) {
						// if found a letter, then we've got something valid
						$has = true;
						break;

					} else if(strpos($digits, $c) === false) {
						// if we've got a non-digit (and non-letter) then definitely not valid
						break;
					}
				}
			}

			if($has) {
				if($getOperator) $getOperator = $operator;
				break;
			}
		}

		if($has && $getOperator) return $getOperator;

		return $has;
	}

	/**
	 * Is the given string a Selector string?
	 *
	 * #pw-group-static-helpers
	 *
	 * @param string $str String to check for selector(s)
	 * @return bool
	 *
	 */
	static public function stringHasSelector($str) {

		if(!self::stringHasOperator($str)) return false;

		$has = false;
		$alphabet = 'abcdefghijklmnopqrstuvwxyz';

		// replace characters that are allowed but aren't useful here
		if(strpos($str, '=(') !== false) $str = str_replace('=(', '=1,', $str);
		$str = str_replace(array('!', '(', ')', '@', '.', '|', '_'), '', trim(strtolower($str)));

		// flatten sub-selectors
		$pos = strpos($str, '[');
		if($pos && strrpos($str, ']') > $pos) {
			$str = str_replace(array(']', '=[', '<[', '>['), array('', '=1,', '<2,', '>3,'), $str);
		}
		$str = rtrim($str, ", ");

		// first character must match alphabet
		if(strpos($alphabet, substr($str, 0, 1)) === false) return false;

		$operatorChars = implode('', self::getOperatorChars());

		if(strpos($str, ',')) {
			// split the string into all key=value components and check each individually
			$inQuote = '';
			$cLast = '';
			// replace comments in quoted values so that they aren't considered selector boundaries
			for($n = 0; $n < strlen($str); $n++) {
				$c = $str[$n];
				if($c === ',') {
					// commas in quoted values are replaced with semicolons
					if($inQuote) $str[$n] = ';';
				} else if(($c === '"' || $c === "'") && $cLast != "\\") {
					if($inQuote && $inQuote === $c) {
						$inQuote = ''; // end quote
					} else if(!$inQuote) {
						$inQuote = $c; // start quote
					}
				}
				$cLast = $c;
			}
			$parts = explode(',', $str);
		} else {
			// outside of verbose mode, only the first apparent selector is checked
			$parts = array($str);
		}

		// check each key=value component
		foreach($parts as $part) {
			$has = preg_match('/^[a-z][a-z0-9]*([' . $operatorChars . ']+)(.*)$/', trim($part), $matches);
			if($has) {
				$operator = $matches[1];
				$value = $matches[2];
				if(!isset(self::$selectorTypes[$operator])) {
					$has = false;
				} else if(self::stringHasOperator($value) && $value[0] != '"' && $value[0] != "'") {
					// operators not allowed in values unless quoted
					$has = false;
				}
			}
			if(!$has) break;
		}

		return $has;
	}

	/**
	 * Does given selector have given field (and optionally operator and/or value)?
	 * 
	 * #pw-group-static-helpers
	 * 
	 * @param string|array|Selectors $selectors Selector string, array or Selectors object to look in
	 * @param string|array $fieldName Field name string to match or array of them to match any one of them
	 * @param array $options
	 *  - `verbose` (bool): Return associative array with verbose result? See return value. (default=false)
	 *  - `operator` (string): Require this operator (default='' for any) 
	 *  - `value` (string|int): Require this value (default=null for any)
	 *  - `remove` (bool): Remove matched Selector from Selectors returned in verbose result? (default=false)
	 * @return array|bool True if has field, false if not, or array with the following, if 'verbose' option requested:
	 *  - `result` (bool): Did it match (true or false)
	 *  - `selector` (Selector|null): Selector object that matched (only if result is true)
	 *  - `selectors` (Selectors|null): Selectors object that was analyzed or null if not needed
	 *  - `field` (string): Field name that matched
	 *  - `operator` (string): Operator that matched
	 *  - `value` (string|null): Value that matched or null if not applicable
	 * @since 3.0.174
	 * 
	 */
	static public function selectorHasField($selectors, $fieldName, array $options = array()) { 
		
		$defaults = array(
			'operator' => '', // require this operator
			'value' => null, // require this value
			'verbose' => false, // return verbose information?
			'remove' => false, // remove matched Selector from Selectors (when/if applicable)
		);
		
		$result = array(
			'result' => false, // true if field found, false if not
			'selectors' => null,  // Selectors object when used
			'selector' => null, // first Selector that matched
			'field' => '', // field name that matched
			'operator' => '', // operator that matched
			'value' => null, // value that matched or null if not applicable
		);

		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		$fail = false;
		
		if(is_array($selectors)) {
			$selectors = new Selectors($selectors);
			
		} else if(is_string($selectors)) {
			if(is_array($fieldName)) {
				foreach($fieldName as $key => $name) {
					if(strpos($selectors, $name) === false) unset($fieldName[$key]);
				}
				$count = count($fieldName);
				$fail = $count === 0;
				if($count === 1) $fieldName = reset($fieldName); // simplify 1-item array to string
			} else if(strpos($selectors, $fieldName) === false) {
				$fail = true;
			}
			
		} else if(!$selectors instanceof Selectors) {
			$fail = true;
		}
		
		if($fail) {
			return ($options['verbose'] ? $result : $result['result']);
		}
		
		if(!$selectors instanceof Selectors) {
			$selectors = new Selectors($selectors);
		}
		
		/** @var Selectors $selectors */
		foreach($selectors as $selector) {
			
			if($options['operator'] && $selector->operator() !== $options['operator']) continue;
			
			$field = $selector->field;
		
			// require specific field or one of array of fields to match
			if(is_string($field)) {
				// field is string
				if(is_array($fieldName)) {
					// find field in fieldName array
					if(!in_array($field, $fieldName)) continue;
				} else {
					// both field and fieldName are strings
					if($field !== $fieldName) continue;
				}
			} else if(is_array($field)) {
				// field is array
				if(is_array($fieldName)) {
					// both field and fieldName are arrays
					$has = false;
					foreach($fieldName as $name) {
						$has = in_array($name, $field) ? $name : false;
						if($has) break;
					}
					if(!$has) continue;
					$field = $has;
				} else {
					// find fieldName in field array
					$key = array_search($fieldName, $field);
					if($key === false) continue;
					$field = $field[$key];
				}
			} else {
				// field in unrecognized format (should not be reachable)
				continue;
			}
			
			if($options['value'] !== null) {
				// require specific value to match
				$value = $selector->value;
				if(is_array($value)) {
					if(!in_array($options['value'], $value)) continue;
					// match success
					$result['value'] = $options['value'];
				} else {
					if("$value" !== "$options[value]") continue;
					// match success
					$result['value'] = $value;
				}
			} else {
				// match success
				$result['value'] = $selector->value;
			}
			
			if($options['remove']) $selectors->remove($selector);
			
			$result = array_merge($result, array(
				'result' => true, 
				'selectors' => $selectors, 
				'selector' => $selector, 
				'field' => $field, 
				'operator' => $selector->operator(), 
			));
			
			break;
		}
		
		return ($options['verbose'] ? $result : $result['result']);
	}

	/**
	 * Simple "a=b, c=d" selector-style string conversion to associative array, for fast/simple needs
	 *
	 * - The only supported operator is "=".
	 * - Each key=value statement should be separated by a comma.
	 * - Do not use quoted values.
	 * - If you need a literal comma, use a double comma ",,".
	 * - If you need a literal equals, use a double equals "==".
	 *
	 * #pw-group-static-helpers
	 *
	 * @param string $s
	 * @return array
	 *
	 */
	static public function keyValueStringToArray($s) {

		if(strpos($s, '~~COMMA') !== false) $s = str_replace('~~COMMA', '', $s);
		if(strpos($s, '~~EQUAL') !== false) $s = str_replace('~~EQUAL', '', $s);

		$hasEscaped = false;

		if(strpos($s, ',,') !== false) {
			$s = str_replace(',,', '~~COMMA', $s);
			$hasEscaped = true;
		}
		if(strpos($s, '==') !== false) {
			$s = str_replace('==', '~~EQUAL', $s);
			$hasEscaped = true;
		}

		$a = array();
		$parts = explode(',', $s);
		foreach($parts as $part) {
			if(!strpos($part, '=')) continue;
			list($key, $value) = explode('=', $part);
			if($hasEscaped) $value = str_replace(array('~~COMMA', '~~EQUAL'), array(',', '='), $value);
			$a[trim($key)] = trim($value);
		}

		return $a;
	}

	/**
	 * Given an assoc array, convert to a key=value selector-style string
	 *
	 * #pw-group-static-helpers
	 *
	 * @param array $a
	 * @return string
	 *
	 */
	static public function arrayToKeyValueString($a) {
		$s = '';
		foreach($a as $key => $value) {
			if(strpos($value, ',') !== false) $value = str_replace(array(',,', ','), ',,', $value);
			if(strpos($value, '=') !== false) $value = str_replace('=', '==', $value);
			$s .= "$key=$value, ";
		}
		return rtrim($s, ", ");
	}


}

Selector::loadSelectorTypes();
