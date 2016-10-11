<?php namespace ProcessWire;

/**
 * ProcessWire Page Comparison
 *
 * Provides implementation for Page comparison functions.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class PageComparison {

	/** 
	 * Does this page have the specified status number or template name? 
 	 *
 	 * See status flag constants at top of Page class
	 *
	 * @param Page $page
	 * @param int|string|Selectors $status Status number or Template name or selector string/object
	 * @return bool
	 *
	 */
	public function is(Page $page, $status) {

		if(is_int($status)) {
			return ((bool) ($page->status & $status)); 

		} else if(is_string($status) && $page->wire('sanitizer')->name($status) == $status) {
			// valid template name or status name
			if($page->template->name == $status) return true; 

		} else if($page->matches($status)) { 
			// Selectors object or selector string
			return true; 
		}

		return false;
	}

	/**
	 * Given a Selectors object or a selector string, return whether this Page matches it
	 *
	 * @param Page $page
	 * @param string|Selectors $s
	 * @return bool
	 *
	 */
	public function matches(Page $page, $s) {
		
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
					// early exit for simple name atch
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

		} else { 
			// unknown data type to match
			return false;
		}

		$matches = false;
		$ignores = array('limit', 'start', 'sort', 'include');

		foreach($selectors as $selector) {
			
			$property = $selector->field;
			$subproperty = '';
			
			if(is_array($property)) $property = reset($property);
			if(strpos($property, '.')) list($property, $subproperty) = explode('.', $property, 2);
			if(in_array($property, $ignores)) continue;
			
			$matches = true; 
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
			
			if(!$selector->matches($value)) {
				$matches = false; 
				break;
			}
		}

		return $matches; 
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

}

