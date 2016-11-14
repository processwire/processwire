<?php namespace ProcessWire; 

/**
 * WireInputData manages one of GET, POST, COOKIE, or whitelist
 * 
 * WireInputData and the WireInput class together form a simple
 * front end to PHP's $_GET, $_POST, and $_COOKIE superglobals.
 *
 * Vars retrieved from here will not have to consider magic_quotes.
 * No sanitization or filtering is done, other than disallowing 
 * multi-dimensional arrays in input.
 *
 * WireInputData specifically manages one of: get, post, cookie or 
 * whitelist, whereas the WireInput class provides access to the 3 
 * InputData instances.
 *
 * Each WireInputData is not instantiated unless specifically asked for.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * @link http://processwire.com/api/ref/input/ Offical $input API variable documentation
 *
 * @method string name($varName) Sanitize to ProcessWire name format
 * @method string varName($varName) Sanitize to PHP variable name format
 * @method string fieldName($varName) Sanitize to ProcessWire Field name format
 * @method string templateName($varName) Sanitize to ProcessWire Template name format
 * @method string pageName($varName) Sanitize to ProcessWire Page name format
 * @method string pageNameTranslate($varName) Sanitize to ProcessWire Page name format with translation of non-ASCII characters to ASCII equivalents
 * @method string filename($varName) Sanitize to valid file basename as used by filenames in ProcessWire
 * @method string pagePathName($varName) Sanitize to what could be a valid page path in ProcessWire
 * @method string email($varName) Sanitize email address, converting to blank if invalid
 * @method string emailHeader($varName) Sanitize string for use in an email header
 * @method string text($varName) Sanitize to single line of text up to 255 characters (1024 bytes max), HTML markup is removed
 * @method string textarea($varName) Sanitize to multi-line text up to 16k characters (48k bytes), HTML markup is removed
 * @method string url($varName) Sanitize to a valid URL, or convert to blank if it can't be sanitized
 * @method string selectorField($varName) Sanitize a field name for use in a selector string
 * @method string selectorValue($varName) Sanitize a value for use in a selector string
 * @method string entities($varName) Return an entity encoded version of the value
 * @method string purify($varName) Return a value run through HTML Purifier (value assumed to contain HTML)
 * @method string string($varName) Return a value guaranteed to be a string, regardless of what type $varName is. Does not sanitize.
 * @method string date($varName, $dateFormat) Validate and return $varName in the given PHP date() or strftime() format.
 * @method int int($varName, $min = 0, $max = null) Sanitize value to integer with optional min and max. Unsigned if max >= 0, signed if max < 0.
 * @method int intUnsigned($varName, $min = null, $max = null) Sanitize value to unsigned integer with optional min and max.
 * @method int intSigned($varName, $min = null, $max = null) Sanitize value to signed integer with optional min and max.
 * @method float float($varName, $min = null, $max = null, $precision = null) Sanitize value to float with optional min and max values.
 * @method array array($varName, $sanitizer = null) Sanitize array or CSV String to an array, optionally running elements through specified $sanitizer.
 * @method array intArray($varName, $min = 0, $max = null) Sanitize array or CSV string to an array of integers with optional min and max values.
 * @method string|null option($varName, array $allowedValues) Return value of $varName only if it exists in $allowedValues.
 * @method array options($varName, array $allowedValues) Return all values in array $varName that also exist in $allowedValues.
 * @method bool bool($varName) Sanitize value to boolean (true or false)
 *
 *
 */
class WireInputData extends Wire implements \ArrayAccess, \IteratorAggregate, \Countable {

	/**
	 * Whether or not slashes should be stripped
	 * 
	 * @var bool|int
	 * 
	 */
	protected $stripSlashes = false;

	/**
	 * Input data container
	 * 
	 * @var array
	 * 
	 */
	protected $data = array();

	/**
	 * Construct
	 * 
	 * @param array $input Associative array of variables to store
	 * 
	 */
	public function __construct(array $input = array()) {
		$this->useFuel(false);
		$this->stripSlashes = get_magic_quotes_gpc();
		$this->setArray($input);
	}

	/**
	 * Set associative array of variables to store
	 * 
	 * @param array $input
	 * @return $this
	 * 
	 */
	public function setArray(array $input) {
		foreach($input as $key => $value) $this->__set($key, $value);
		return $this;
	}

	/**
	 * Get associative array of all input variables
	 * 
	 * @return array
	 * 
	 */
	public function getArray() {
		return $this->data;
	}

	/**
	 * Set an input value
	 * 
	 * @param string $key
	 * @param mixed $value
	 *
	 */
	public function __set($key, $value) {
		if(is_string($value) && $this->stripSlashes) $value = stripslashes($value);
		if(is_array($value)) $value = $this->cleanArray($value);
		$this->data[$key] = $value;
	}

	/**
	 * Clean an array of data
	 * 
	 * Removes multi-dimensional arrays and slashes (if applicable) 
	 * 
	 * @param array $a
	 * @return array
	 * 
	 */
	protected function cleanArray(array $a) {
		$clean = array();
		foreach($a as $key => $value) {
			if(is_array($value)) continue; // we only allow one dimensional arrays
			if(is_string($value) && $this->stripSlashes) $value = stripslashes($value);
			$clean[$key] = $value;
		}
		return $clean;
	}

	/**
	 * Set whether or not slashes should be stripped
	 * 
	 * @param $stripSlashes
	 * 
	 */
	public function setStripSlashes($stripSlashes) {
		$this->stripSlashes = $stripSlashes ? true : false;
	}

	/**
	 * Get an input value
	 * 
	 * @param string $key
	 * @return mixed|null
	 *
	 */
	public function __get($key) {
		// if($key == 'whitelist') return $this->whitelist;
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function getIterator() {
		return new \ArrayObject($this->data);
	}

	public function offsetExists($key) {
		return isset($this->data[$key]);
	}

	public function offsetGet($key) {
		return $this->__get($key);
	}

	public function offsetSet($key, $value) {
		$this->__set($key, $value);
	}

	public function offsetUnset($key) {
		unset($this->data[$key]);
	}

	public function count() {
		return count($this->data);
	}
	
	public function remove($key) {
		unset($this->data[$key]);
		return $this;
	}

	public function removeAll() {
		$this->data = array();
		return $this;
	}

	public function __isset($key) {
		return $this->offsetExists($key);
	}

	public function __unset($key) {
		$this->offsetUnset($key);
	}

	public function queryString($overrides = array()) {
		return http_build_query(array_merge($this->getArray(), $overrides)); 
	}

	/**
	 * Maps to Sanitizer functions
	 *
	 * @param $method
	 * @param $arguments
	 *
	 * @return string|int|array|float|null Returns null when input variable does not exist
	 * @throws WireException
	 *
	 */
	public function __call($method, $arguments) {
		$sanitizer = $this->wire('sanitizer');
		$method = ltrim($method, '_');
		if(!method_exists($sanitizer, $method)) {
			$method = "___$method";
			if(!method_exists($sanitizer, $method)) {
				$method = ltrim($method, "_");
				throw new WireException("Unknown method '$method' - Specify a valid Sanitizer or WireInputData method.");
			}
		}
		if(!isset($arguments[0])) {
			throw new WireException("For method '$method' specify an input variable name for first argument");
		}
		$arguments[0] = $this->__get($arguments[0]);
		if(is_null($arguments[0])) {
			// value is not present in input at all
			// @todo do you want to provide an alternate means of handling this situation?
		}
		return call_user_func_array(array($sanitizer, $method), $arguments);
	}
}

