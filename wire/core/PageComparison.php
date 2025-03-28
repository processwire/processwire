<?php namespace ProcessWire;

/**
 * ProcessWire Page Comparison
 *
 * Provides implementation for Page comparison functions.
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class PageComparison {

	/**
	 * Selector properties that the matches() method ignores
	 * 
	 * @var string[] 
	 * 
	 */
	protected $matchesIgnores = array('limit', 'start', 'sort', 'include');

	/** 
	 * Is this page of the given type? (status, template, etc.)
 	 *
	 * @param Page $page
	 * @param int|string|array|Selectors|Page|Template $status One of the following: 
	 *  - Status expressed as int (using Page::status* constants) 
	 *  - Status expressed as string/name, i.e. "hidden" (3.0.150+)
	 *  - Template name, indicating page type
	 *  - Page or Template object (3.0.150+)
	 *  - Selector string or Selectors object that must match
	 *  - Array of any of the above where all have to match (3.0.150+)
	 * @return bool
	 *
	 */
	public function is(Page $page, $status) {
	
		$is = false;
		
		if(is_string($status) && ctype_digit($status)) {
			$status = (int) $status;
		}

		if(is_int($status)) {
			// status flag integer
			$is = $page->status & $status; 
			
		} else if(is_object($status)) {
			// Page, Template or Selectors object
			if($status instanceof Page && $status->id === $page->id) {
				$is = true;
			} else if($status instanceof Template && "$page->template" === "$status") {
				$is = true;
			} else if($status instanceof Selectors || $status instanceof Selector) {
				$is = $page->matches($status);
			}
			
		} else if(is_array($status)) {
			// array where all items have to match an is() call
			$n = 0;
			foreach($status as $val) {
				if(is_array($val)) break; // no multi-dimensional
				if($this->is($page, $val)) $n++;
			}
			if($n && count($status) === $n) $is = true;

		} else if(is_string($status) && $page->wire()->sanitizer->name($status) === $status) {
			// name string (status name or template name)
			$statuses = Page::getStatuses();
			if(isset($statuses[$status])) {
				// status name
				$is = $page->status & $statuses[$status];

			} else if("$page->template" === $status) {
				// template name
				$is = true;
			}
			
		} else if(is_string($status) && strpos($status, 'Page::status') === 0) {
			// literal constant name in string
			$status = __NAMESPACE__ . "\\$status";
			$status = constant($status);
			$is = $page->status & $status; 

		} else if($page->matches($status)) { 
			// Selectors object or selector string
			$is = true; 
		}

		return (bool) $is;
	}

	/**
	 * If value is available for $key return or call $yes condition (with optional $no condition)
	 *
	 * This merges the capabilities of an if() statement, get() and getMarkup() methods in one,
	 * plus some useful PW type-specific logic, providing a useful output shortcut.
	 * 
	 * See phpdoc in `Page::if()` for full details.
	 *
	 * @param Page $page
	 * @param string|bool|int $key Name of field to check, selector string to evaluate, or boolean/int to evalute
	 * @param string|callable|mixed $yes If value for $key is present, return or call this
	 * @param string|callable|mixed $no If value for $key is empty, return or call this
	 * @return mixed|string|bool
	 * @since 3.0.126
	 *
	 */
	public function _if(Page $page, $key, $yes = '', $no = '') {

		$sanitizer = $page->wire()->sanitizer;

		// if only given a key argument, we will be returning a boolean
		if($yes === '' && $no === '') list($yes, $no) = array(true, false);
		
		if(is_string($key)) $key = trim($key);

		if(is_bool($key) || is_int($key)) {
			// boolean or int
			$val = $key;
			$action = empty($val) ? $no : $yes;
		} else if(is_array($key)) {
			// PHP array 
			$val = $key;
			$action = count($val) ? $no : $yes;
		} else if(ctype_digit(ltrim("$key", '-'))) {
			// integer or string value of digits, or Wire instance string value of digits
			$val = (int) "$key";
			$action = empty($val) ? $no : $yes;
		} else if(is_string($key) && wireEmpty($key)) {
			// empty string
			$val = $key;
			$action = $no;
		} else if(!ctype_alnum("$key") && Selectors::stringHasOperator($key)) {
			// selector string
			$val = $page->matches($key) ? 1 : 0;
			$action = $val ? $yes : $no;
		} else {
			// field name or other format string accepted by $page->get()
			$val = $page->get($key);
			$action = wireEmpty($val) ? $no : $yes;
		}

		if(is_string($action)) {
			// action is a string
			$getValue = false;
			$tools = $sanitizer->getTextTools();
			if(($action === 'value' || $action === 'val') && !$page->template->fieldgroup->hasField($action)) {
				// implicit 'value' or 'val' maps back to name specified in $key argument
				$getValue = $key;
			}
			if(empty($action)) {
				$result = $action;
				
			} else if($getValue) {
				$result = $page->get($getValue);
				
			} else if($tools->hasPlaceholders($action)) {
				// action is a getMarkup() string
				$keyIsFieldName = $sanitizer->fieldName($key) === $key;
				$act = $action;
				// if value placeholders present, replace them with field name placeholders
				foreach(array('{value}', '{val}') as $tag) {
					// string with {val} or {value} has that tag replaced with the {field_name}
					if(strpos($action, $tag) === false) continue;
					// if val or value is actually the name of a field in the system, then do not override it
					if($page->hasField(trim($tag, '{}'))) continue;
					$action = str_replace($tag, ($keyIsFieldName ? '{' . $key . '}' : $val), $action);
				}
				$result = $act === $action || $tools->hasPlaceholders($action) ? $page->getMarkup($action) : $action;
				
			} else if($sanitizer->fieldSubfield($action, -1) === $action && $page->hasField($sanitizer->fieldSubfield($action, 0))) {
				// action is another field name that we want to get the value for
				$result = $page->get($action);
			} else {
				// action is just a string to return
				$result = $action;
			}

		} else if(is_callable($action) && (!is_object($action) || $action instanceof \Closure)) {
			// action is callable
			$result = call_user_func_array($action, array($val, $key, $page));
			
		} else {
			// action is a number, array or object
			$result = $action;
		}

		return $result;
	}

	/**
	 * Given a Selectors object or a selector string, return whether this Page matches it
	 *
	 * @param Page $page
	 * @param string|array|Selectors $s
	 * @param array $options Options to modify behavior (3.0.225+ only):
	 *  - `useDatabase` (bool|null): Use database for matching rather than in-memory? (default=false)
	 * @return bool
	 *
	 */
	public function matches(Page $page, $s, array $options = array()) {
	
		$selectors = array();

		if(is_string($s) || is_int($s)) {
			if(ctype_digit("$s")) $s = (int) $s; 
			if(is_string($s)) {
				if(!strlen($s)) {
					// blank string matches nothing
					return false;
				} else if(substr($s, 0, 1) == '/' && $page->path() == (rtrim($s, '/') . '/')) {
					// exit early for simple path comparison
					return true;
				} else if($page->name === $s) {
					// early exit for simple name match
					return true;
				} else if(Selectors::stringHasOperator($s)) {
					// selectors string
					$selectors = $page->wire(new Selectors($s));
				} else {
					// some other type of string
					return false;
				}
				
			} else if(is_int($s)) {
				// exit early for simple ID comparison
				return $page->id == $s; 
			}

		} else if($s instanceof Selectors) {
			$selectors = $s;

		} else if(is_array($s)) {
			$selectors = $page->wire(new Selectors($s));

		} else { 
			// unknown data type to match
			return false;
		}
		
		if(!empty($options['useDatabase'])) {
			$selectors->add(new SelectorEqual('id', $page->id))->add(new SelectorEqual('include', 'all'));
			return $page->wire()->pages->count($selectors) > 0;
		}

		$matchFail = false;
		$groupSelectors = array(); // same (1) item match group selectors
		$orGroupSelectors = array(); // OR-group selectors

		foreach($selectors as $selector) {
			
			if($selector->quote === '(') {
				// OR-groups are handled below this loop
				$orGroup = $selector->field() . '.';
				if(!isset($orGroupSelectors[$orGroup])) $orGroupSelectors[$orGroup] = array();
				$orGroupSelectors[$orGroup][] = $selector;
				continue;
			}
			
			if(!$this->selectorMatches($page, $selector)) {
				$matchFail = true;
				break;
			}
			
			if($selector->group !== null) {
				// validate that same (1) item matches from these later
				$groupName = $selector->group;
				if(!isset($groupSelectors[$groupName])) $groupSelectors[$groupName] = array();
				$groupSelectors[$groupName][] = $selector;
			}
		}
		
		// OR-groups
		if(!$matchFail && count($orGroupSelectors)) {
			foreach($orGroupSelectors as /* $orGroupName => */ $selectors) {
				$orGroupMatches = false;
				foreach($selectors as $selector) {
					if($this->matches($page, $selector->value)) {
						// OR-group selector matches
						$orGroupMatches = true;
						break;
					}
				}
				if(!$orGroupMatches) {
					$matchFail = true;
					break;
				}
			}
		}
	
		// same (1) item match groups
		if(!$matchFail && count($groupSelectors)) {
			foreach($groupSelectors as /* $groupName => */ $selectors) {
				$matchGroupKeys = null;
				foreach($selectors as $selector) {
					$keys = $selector->get('_matchGroupKeys'); // populated by selectorMatchesProperty
					if($matchGroupKeys === null) {
						$matchGroupKeys = $keys;
					} else {
						$matchGroupKeys = array_intersect($matchGroupKeys, $keys);
					}
				}
				if(empty($matchGroupKeys)) {
					$matchFail = true;
					break;
				}
			}
		}
		
		return !$matchFail;
	}

	/**
	 * Return whether individual Selector object matches Page
	 * 
	 * @param Page $page
	 * @param Selector $selector
	 * @return bool
	 * @since 3.0.231
	 * 
	 */
	protected function selectorMatches(Page $page, Selector $selector) {
		$match = false;
		$properties = $selector->fields();
		foreach($properties as $property) {
			$match = $this->selectorMatchesProperty($page, $selector, $property);
			if($match) break;
		}
		return $match;
	}

	/**
	 * Return whether single property from individual Selector matches Page
	 *
	 * @param Page $page
	 * @param Selector $selector
	 * @param string $property
	 * @return bool
	 * @since 3.0.231
	 *
	 */
	protected function selectorMatchesProperty(Page $page, Selector $selector, $property) {
		
		$subproperty = '';
		if(strpos($property, '.')) list($property, $subproperty) = explode('.', $property, 2);
		if(in_array($property, $this->matchesIgnores)) return true;

		if($selector->quote === '[' && Selectors::stringHasOperator($selector->value())) {
			$selector->value = $page->wire()->pages->findIDs($selector->value());
		}
		
		$value = $page->getUnformatted($property);

		if(is_object($value)) {
			// convert object to array value(s)
			$value = $this->getObjectValueArray($value, $subproperty);

		} else if(is_array($value)) {
			// ok: selector matches will accept an array

		} else {
			// convert to a string value, whatever it may be
			$value = "$value";
		}
		
		if(!$selector->matches($value)) return false;
		
		if($selector->group !== null && is_array($value)) {
			// see which individual values match and record their keys for later comparison
			$matchGroupKeys = array();
			foreach($value as $key => $val) {
				if($selector->matches($val)) $matchGroupKeys[] = $key;
			}
			$selector->setQuietly('_matchGroupKeys', $matchGroupKeys); // used by matches() method
		}
		
		return true;
	}
	
	/**
	 * Given an object, return the value(s) it represents (optionally from a property in the object)
	 * 
	 * This method is setup for the matches() method above this. It will go recursive when given a property
	 * that resolves to another object. 
	 * 
	 * @param Wire|object $object
	 * @param string $property Optional property to pull from object (may also be property.subproperty, and so on)
	 * @return array Always returns an array, which may be empty or populated
	 * 
	 */
	protected function getObjectValueArray($object, $property = '') {
		
		$value = array();
		$_property = $property; // original
		$subproperty = '';
		if(strpos($property, '.')) list($property, $subproperty) = explode('.', $property, 2);
		
		// if the current page value resolves to an object
		if($object instanceof Page) {
			// object is a Page
			if($property) {
				// pull specific property from page
				$v = $object->getUnformatted($property);
				if(is_object($v)) {
					$value = $this->getObjectValueArray($v, $subproperty);
				} else if(!is_null($v)) {
					$value = array($v);
				}
			} else {
				// if no property, get id, name and path as allowed comparison values
				$value[] = $object->id;
				$value[] = $object->path;
				$value[] = $object->name;
			}

		} else if($object instanceof WireArray) {
			// it's a WireArray|PageArray

			if($property === 'count') {
				// quick exit for count property
				return array(count($object));
			}
			
			// iterate and get value of each item present
			foreach($object as $v) {
				if(is_object($v)) {
					$v = $this->getObjectValueArray($v, $_property); // use original property.subproperty
					if(count($v)) $value = array_merge($value, $v);
				} else {
					$value[] = $v;
				}
			}

		} else if($object instanceof Template) {
			// Template object, compare to id and name
			if($property) {
				$v = $object->get($property);
				if(!is_null($v)) $value[] = $v;
			} else {
				$value[] = $object->id;
				$value[] = $object->name;
			}
			
		} else if($object instanceof WireData) {
			// some other type of WireData object
			if($property) {
				$v = $object->get($property);
				if(is_object($v)) {
					$value = $this->getObjectValueArray($v, $subproperty);
				} else if(!is_null($v)) {
					$value = array($v);
				}
				
			} else {
				// no property present, so we'll find some other way to identify the object
				// get string value of object as a potential comparison
				$v = (string) $object;
				// string value that doesn't match class name identifies the object in some way
				if($v !== wireClassName($object)) $value[] = $v;
				// if the object uses the common 'id' or 'name' properties, consider those as well
				foreach(array('id', 'name') as $key) {
					$v = $object->get($key);
					if(!is_null($v)) $value[] = $v;
				}
			}

		} else if($property && method_exists($object, '__get')) {
			// some other object, property is present, object has a __get method that we can pull it from
			$v = $object->__get($property);
			if(is_object($v)) {
				$value = $this->getObjectValueArray($v, $subproperty);
			} else if(!is_null($v)) {
				$value = array($v);
			}
				
		} else if(!$property && method_exists($object, '__toString')) {
			// items in WireArray are some type of Wire, use string value if not className
			if(wireClassName($object) != (string) $object) $value[] = (string) $object;

		} else {
			// property present with some kind of value that we don't know how to pull from 
		}
		
		return $value;
	}
	
	/**
	 * Is $value1 equal to $value2?
	 *
	 * @param string $key Name of the key that triggered the check (see WireData::set)
	 * @param mixed $value1
	 * @param mixed $value2
	 * @return bool
	 *
	 */
	public function isEqual(Page $page, $key, $value1, $value2) {

		$isEqual = $value1 === $value2;

		if(!$isEqual && $value1 instanceof WireArray && $value2 instanceof WireArray) {
			// ask WireArray to compare itself to another
			$isEqual = $value1->isIdentical($value2, true);
		}

		if($isEqual) {
			if($value1 instanceof Wire && ($value1->isChanged() || $value2->isChanged())) {
				$page->trackChange($key, $value1, $value2);
			}
		}

		return $isEqual;
	}


}
