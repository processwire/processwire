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

		if(is_string($s) || is_int($s)) {
			if(ctype_digit("$s")) $s = (int) $s; 
			if(is_string($s)) {
				// exit early for simple path comparison
				if(substr($s, 0, 1) == '/' && $page->path() == (rtrim($s, '/') . '/')) return true; 
				if(!Selectors::stringHasOperator($s)) return false;
				$selectors = $page->wire(new Selectors($s)); 
				
			} else if(is_int($s)) {
				// exit early for simple ID comparison
				return $page->id == $s; 
			}

		} else if($s instanceof Selectors) {
			$selectors = $s; 

		} else { 
			return false;
		}

		$matches = false;

		foreach($selectors as $selector) {
			
			$name = $selector->field;
			if(in_array($name, array('limit', 'start', 'sort', 'include'))) continue; 
			$matches = true; 
			$value = $page->getUnformatted($name); 
			
			if(is_object($value)) {
				// if the current page value resolves to an object
				if($value instanceof Page) {
					// if it's a Page, get both the ID and path as allowed comparison values
					$value = array($value->id, $value->path); 
				} else if($value instanceof PageArray) {
					// if it's a PageArray, then get the ID and path of all of them
					// @todo add support for @ selectors
					$_value = array();
					foreach($value as $v) {
						$_value[] = $v->id; 
						$_value[] = $v->path; 
					}
					$value = $_value;
				} else if($value instanceof Template) {
					$value = array($value->id, $value->name); 
				} else {
					// otherwise just get the string value of the object
					$value = "$value";
				}
						
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

}

