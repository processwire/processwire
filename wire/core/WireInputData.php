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
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
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
 * @method string text($varName, $options = array()) Sanitize to single line of text up to 255 characters (1024 bytes max), HTML markup is removed
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
	 * Are we working with lazy data (data by reference)?
	 * 
	 * @var bool
	 * 
	 */
	protected $lazy = false;

	/**
	 * When lazy mode is active, these are keys of values set in a non-lazy way
	 * 
	 * @var array
	 * 
	 */
	protected $unlazyKeys = array();

	/**
	 * Construct
	 * 
	 * @param array $input Associative array of variables to store
	 * @param bool $lazy Use lazy loading?
	 * 
	 */
	public function __construct(&$input = array(), $lazy = false) {
		parent::__construct();
		$this->useFuel(false);
		if(version_compare(PHP_VERSION, '5.4.0', '<') && function_exists('get_magic_quotes_gpc')) {
			$this->stripSlashes = get_magic_quotes_gpc();
		}
		if(!empty($input)) {
			if($lazy) {
				$this->data = &$input;
				$this->lazy = true;
			} else {
				$this->setArray($input);
			}
		}
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
		if($this->lazy) {
			$data = array();
			foreach($this->data as $key => $value) {
				if(isset($this->unlazyKeys[$key])) {
					$data[$key] = $value;
				} else {
					$data[$key] = $this->__get($key);
				}
			}
			return $data;
		} else {
			return $this->data;
		}
	}

	/**
	 * Set an input value
	 * 
	 * @param string $key
	 * @param mixed $value
	 *
	 */
	public function __set($key, $value) {
		if(is_string($value)) {
			if($this->stripSlashes) $value = stripslashes($value);
		} else if(is_array($value)) {
			$value = $this->cleanArray($value);
		}
		$this->data[$key] = $value;
		if($this->lazy) $this->unlazyKeys[$key] = $key;
	}

	/**
	 * Set a value 
	 * 
	 * @param string $key
	 * @param string|int|float|array|null $value
	 * @return $this
	 * @param array|int|string $options Options not currently used, but available for descending classes or future use
	 * @since 3.0.141 You can also use __set() or set directly for compatibility with all versions
	 * 
	 */
	public function set($key, $value, $options = array()) {
		if($options) {} // not currently used by this class
		$this->__set($key, $value);
		return $this;
	}

	/**
	 * Get a value
	 * 
	 * @param string $key
	 * @param array|int|string $options Options not currently used, but available for descending classes or future use
	 * @return string|int|float|array|null $value
	 * @since 3.0.141 You can also get directly or use __get(), both of which are compatible with all versions
	 * 
	 */
	public function get($key, $options = array()) {
		if($options) {} // not currently used by this class
		return $this->__get($key);
	}

	/**
	 * Find one input var that matches given pattern in name (or optionally value)
	 *
	 * @param string $pattern Wildcard string or PCRE regular expression
	 * @param array|int|string $options
	 *  - `type` (string): Specify "value" to match input value (rather input name), OR prefix pattern with "value=".
	 *  - `sanitizer` (string): Name of sanitizer to run values through (default='', none)
	 *  - `arrays` (bool): Also find on input varibles that are arrays? (default=false)
	 * @return string|int|float|array|null $value Returns value if found or null if not. 
	 * @since 3.0.163
	 *
	 */
	public function findOne($pattern, $options = array()) {
		if(!strlen($pattern)) return null;
		if(ctype_alnum(str_replace(array('_', '-', '.'), '', $pattern))) return $this->__get($pattern);
		$options['limit'] = 1;
		$value = $this->find($pattern, array_merge($options, $options));
		return array_shift($value); // returns null if empty
	}

	/**
	 * Find all input vars that match given pattern in name (or optionally value)
	 * 
	 * ~~~~~
	 * // find all input vars having name beginning with "title_" (i.e. title_en, title_de, title_es)
	 * $values = $input->post->find('title_*');
	 * 
	 * // find all input vars having name with "title" anywhere in it (i.e. title, subtitle, titles, title_de)
	 * $values = $input->post->find('*title*');
	 * 
	 * // find all input vars having value with the term "wire" anywhere, regardless of case
	 * $values = $input->post->find('/wire/i', [ 'type' => 'value' ]); 
	 * 
	 * // example of result from above find operation:
	 * $values = [
	 *   'title' => 'ProcessWire CMS', 
	 *   'subtitle' => 'Have plenty of caffeine to make sure you are wired', 
	 *   'sidebar' => 'Learn how to rewire a flux capacitor...',
	 *   'summary' => 'All about the $wire API variable',
	 * ];
	 * ~~~~~
	 * 
	 * @param string $pattern Wildcard string or PCRE regular expression
	 * @param array $options
	 *  - `type` (string): Specify "value" to match input value (rather input name), OR prefix pattern with "value=".
	 *  - `limit` (int): Maximum number of items to return (default=0, no limit)
	 *  - `sanitizer` (string): Name of sanitizer to run values through (default='', none)
	 *  - `arrays` (bool): Also find on input varibles that are arrays? (default=false)
	 * @return array Returns associative array of values `[ name => value ]` if found, or empty array if none found.
	 * @since 3.0.163
	 * 
	 */
	public function find($pattern, array $options = array()) {
		
		$defaults = array(
			'type' => 'name', // match on 'name' or 'value' (default='name')
			'limit' => 0, // max allowed matches in return value
			'values' => $this, // use these values rather than those from this input class
			'sanitizer' => '', // sanitizer name to apply found values
			'arrays' => false, // also find on input vars that are arrays?
		);
		
		if(!strlen($pattern)) return array();
		
		$options = array_merge($defaults, $options);
		$sanitizer = $this->wire()->sanitizer;
		$isRE = in_array($pattern[0], array('/', '!', '%', '#', '@'));
		$items = array();
		$count = 0;
		$type = $options['type'];
		$tests = array();
		
		if(strpos($pattern, '=')) {
			// pattern indicates "value=pattern" or "name=pattern"
			list($type, $pattern) = explode('=', $pattern, 2);
		}
		
		if(!$isRE && strpos($pattern, '*') !== false) {
			// wildcard, convert to regex
			$a = explode('*', $pattern);
			foreach($a as $k => $v) {
				if(!strlen($v)) continue;
				$a[$k] = preg_quote($v);
				$tests[] = $v;
			}
			$isRE = true;
			$pattern = '/^' . implode('.*', $a) . '$/';
		}
	
		if(!count($tests)) $tests = false;
		
		foreach($options['values'] as $name => $value) {
			
			if($options['limit'] && $count >= $options['limit']) break;
			
			$isArray = is_array($value);
			
			if($isArray && !$options['arrays']) {
				continue;
			} else if($isArray && $type === 'value') {
				$v = $this->find($pattern, array_merge($options, array('values' => $value)));
				if(count($v)) list($items[$name], $count) = array($v, $count + 1); 
				continue;
			} else if($type === 'value') {
				$match = $value;
			} else {
				$match = $name;
			}
			
			if($tests) {
				// tests to confirm a preg_match is necessary (wildcard mode only)
				$passes = true;
				foreach($tests as $test) {
					$passes = strpos($match, $test) !== false;
					if(!$passes) break;
				}
				if(!$passes) continue;
			}
			
			if($isRE) {
				if(!preg_match($pattern, $match)) continue;
			} else {
				if(strpos($match, $pattern) === false) continue;
			}
			
			if($options['sanitizer']) {
				$value = $sanitizer->sanitize($value, $options['sanitizer']);
			}
			
			$items[$name] = $value;
			$count++;
		}
		
		return $items;
	}

	/**
	 * Clean an array of data
	 * 
	 * Support multi-dimensional arrays consistent with `$config->wireInputArrayDepth` 
	 * setting (3.0.178+) and remove slashes if applicable/necessary. 
	 * 
	 * @param array $a
	 * @return array
	 * 
	 */
	protected function cleanArray(array $a) {
		static $depth = 1;

		$maxDepth = (int) $this->wire()->config->wireInputArrayDepth;
		if($maxDepth < 1) $maxDepth = 1;
		
		$clean = array();
		
		foreach($a as $key => $value) {
			if(is_array($value)) {
				if($depth >= $maxDepth) {
					// max dimension reached
					$value = null; 
				} else {
					// allow another dimension
					$depth++;
					$value = $this->cleanArray($value);
					$depth--;
					// empty arrays not possible in input vars past 1st dimension
					if(!count($value)) $value = null; 
				}
			} else if(is_string($value)) {
				if($this->stripSlashes) $value = stripslashes($value);
			}
			
			if($value !== null) {
				$clean[$key] = $value;
			}
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
		
		if(strpos($key, '|')) {
			$value = null;
			foreach(explode('|', $key) as $k) {
				$value = $this->__get($k);
				if($value !== null) break;
			}
			return $value;
			
		} else if(isset($this->data[$key])) {
			$value = $this->data[$key];
			if($this->lazy && !isset($this->unlazyKeys[$key])) {
				// in lazy mode, value is not cleaned until it is accessed
				if(is_string($value)) {
					if($this->stripSlashes) $value = stripslashes($value);
				} else if(is_array($value)) {
					$value = $this->cleanArray($value);
				}
			}
			
		} else {
			$value = null;
		}
		
		return $value;
	}

	#[\ReturnTypeWillChange] 
	public function getIterator() {
		if($this->lazy) {
			$data = $this->getArray();
			return new \ArrayObject($data);
		} else {
			return new \ArrayObject($this->data);
		}
	}

	#[\ReturnTypeWillChange] 
	public function offsetExists($key) {
		return isset($this->data[$key]);
	}

	#[\ReturnTypeWillChange] 
	public function offsetGet($key) {
		return $this->__get($key);
	}

	#[\ReturnTypeWillChange] 
	public function offsetSet($key, $value) {
		$this->__set($key, $value);
	}

	#[\ReturnTypeWillChange] 
	public function offsetUnset($key) {
		unset($this->data[$key]);
		if($this->lazy && isset($this->unlazyKeys[$key])) unset($this->unlazyKeys[$key]); 
	}

	#[\ReturnTypeWillChange] 
	public function count() {
		return count($this->data);
	}

	/**
	 * Remove a value from input 
	 * 
	 * @param string $key Name of input variable to remove value for 
	 * @return $this
	 * 
	 */
	public function remove($key) {
		$this->offsetUnset($key);
		return $this;
	}

	/**
	 * Remove all values from input
	 * 
	 * @return $this
	 * 
	 */
	public function removeAll() {
		$this->data = array();
		$this->lazy = false;
		$this->unlazyKeys = array();
		return $this;
	}

	public function __isset($key) {
		return $this->offsetExists($key);
	}

	public function __unset($key) {
		$this->offsetUnset($key);
	}

	/**
	 * Return a query string of all input values
	 * 
	 * Please note returned query string contains non-sanitized/non-validated variables, so this method
	 * should only be used for specific cases where all input is known to be safe/valid. If that is not
	 * an option then use PHP’s `http_build_query()` function on your own with known safe/valid values.
	 * 
	 * #pw-internal
	 * 
	 * @param array $overrides Associative array of [ name => value ] containing values to override/replace
	 * @param string $separator String to separate values with, i.e. '&' or '&amp;' (default='&')
	 * @return string
	 * @since 3.0.163
	 * 
	 */
	public function queryString($overrides = array(), $separator = '&') {
		return http_build_query(array_merge($this->getArray(), $overrides), '', $separator); 
	}

	/**
	 * Maps to Sanitizer functions
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return string|int|array|float|null Returns null when input variable does not exist
	 * @throws WireException
	 *
	 */
	public function ___callUnknown($method, $arguments) {
		$sanitizer = $this->wire()->sanitizer;
		if(!$sanitizer->methodExists($method)) {
			try {
				return parent::___callUnknown($method, $arguments);
			} catch(\Exception $e) {
				throw new WireException("Unknown method '$method' - specify a valid Sanitizer name or WireInputData method"); 
			}
		}
		if(!isset($arguments[0])) {
			throw new WireException("For method '$method' specify an input variable name for first argument");
		}
		// swap input name with input value in arguments array
		$arguments[0] = $this->__get($arguments[0]);
		if($arguments[0] === null) {
			// value is not present in input at all, accommodate potential fallback value?
		}
		if(count($arguments) > 1) {
			// more than one argument to sanitizer method
			return call_user_func_array(array($sanitizer, $method), $arguments);
		} else {
			// single argument, pass along to sanitize method
			return $sanitizer->sanitize($arguments[0], $method); 
		}
	}
	
	public function __debugInfo() {
		return $this->data;
	}
}

