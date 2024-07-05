<?php namespace ProcessWire;

/**
 * ProcessWire Debug 
 *
 * Provides methods useful for debugging or development. 
 *
 * Currently only provides timer capability. 
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 * 
 * ~~~~~
 * $timer = Debug::startTimer();
 * execute_some_code();
 * $elapsed = Debug::stopTimer($timer);
 * ~~~~~
 *
 */

class Debug {

	/**
	 * Current timers
	 * 
	 * @var array
	 * 
	 */
	static protected $timers = array();

	/**
	 * Timers that have been saved
	 * 
	 * @var array
	 * 
	 */
	static protected $savedTimers = array();

	/**
	 * Notes for saved timers
	 * 
	 * @var array
	 * 
	 */
	static protected $savedTimerNotes = array();

	/**
	 * Use hrtime()?
	 * 
	 * @var null|bool
	 * 
	 */
	static protected $useHrtime = null;

	/**
	 * Key of last started timer
	 * 
	 * @var string
	 * 
	 */
	static protected $lastTimerKey = '';

	/**
	 * Timer precision (digits after decimal)
	 * 
	 * @var int
	 * 
	 */
	static protected $timerSettings = array(
		'useMS' => false, // use milliseconds?
		'precision' => 4, 
		'precisionMS' => 1,
		'useHrtime' => null,
		'suffix' => '',
		'suffixMS' => 'ms',
	);

	/**
	 * Measure time between two events
	 *
	 * First call should be to $key = Debug::timer() with no params, or provide your own key that's not already been used
	 * Second call should pass the key given by the first call to get the time elapsed, i.e. $time = Debug::timer($key).
	 * Note that you may make multiple calls back to Debug::timer() with the same key and it will continue returning the 
	 * elapsed time since the original call. If you want to reset or remove the timer, call removeTimer or resetTimer.
	 *
	 * @param string $key 
	 * 	Leave blank to start timer. 
	 *	Specify existing key (string) to return timer. 
	 *	Specify new made up key to start a named timer. 
	 * @param bool $reset If the timer already exists, it will be reset when this is true. 
	 * @return string|int
	 *
	 */
	static public function timer($key = '', $reset = false) {
		
		// returns number of seconds elapsed since first call
		if($reset && $key) self::removeTimer($key);

		if(!$key || !isset(self::$timers[$key])) {
			$value = self::startTimer($key);
		} else {
			$value = self::stopTimer($key, null, false);
		}
		
		return $value; 
	}

	/**
	 * Start a new timer
	 * 
	 * @param string $key Optionally specify name for new timer
	 * @return string
	 * 
	 */
	static public function startTimer($key = '') {
		
		if(self::$timerSettings['useHrtime'] === null) {
			self::$timerSettings['useHrtime'] = function_exists("\\hrtime");
		}

		$startTime = self::$timerSettings['useHrtime'] ? hrtime(true) : -microtime(true);
		
		if($key === '') {
			$key = (string) $startTime;
			while(isset(self::$timers[$key])) $key .= "0";
		}
		
		$key = (string) $key;
		
		self::$timers[$key] = $startTime;
		self::$lastTimerKey = $key;
		
		return $key;
	}

	/**
	 * Get elapsed time for given timer and stop
	 * 
	 * @param string $key Timer key returned by startTimer(), or omit for last started timer
	 * @param null|int|string $option Specify override precision (int), suffix (string), or "ms" for milliseconds and suffix.
	 * @param bool $clear Also clear the timer? (default=true)
	 * @return string
	 * @since 3.0.158
	 * 
	 */
	static public function stopTimer($key = '', $option = null, $clear = true) {
	
		if(empty($key) && self::$lastTimerKey) $key = self::$lastTimerKey;
		if(!isset(self::$timers[$key])) return '';

		$value = self::$timers[$key];
		$useMS = $option === 'ms' || (self::$timerSettings['useMS'] && $option !== 's');
		$suffix = $useMS ? self::$timerSettings['suffixMS'] : self::$timerSettings['suffix'];
		$precision = $useMS ? self::$timerSettings['precisionMS'] : self::$timerSettings['precision'];
		
		if(self::$timerSettings['useHrtime']) {
			// existing hrtime timer
			$value = ((hrtime(true) - $value) / 1e+6) / 1000;
		} else {
			// existing microtime timer
			$value = $value + microtime(true);
		}
		
		if($option === null) {
			// no option specified
		} else if(is_int($option)) {
			// precision override
			$precision = $option;
		} else if(is_string($option)) {
			// suffix specified
			$suffix = $option;
		}
		
		if($useMS) {
			$value = round($value * 1000, $precision);
		} else {
			$value = number_format($value, $precision);
		}
		
		if($clear) self::removeTimer($key);
		if($suffix) $value .= $suffix;
		
		return $value;
	}

	/**
	 * Get or set timer setting
	 * 
	 * ~~~~~~
	 * // Example of changing precision to 2
	 * Debug::timerSetting('precision', 2); 
	 * ~~~~~~
	 * 
	 * @param string $key
	 * @param mixed|null $value
	 * @return mixed
	 * @since 3.0.154
	 * 
	 */
	static public function timerSetting($key, $value = null) {
		if($value !== null) self::$timerSettings[$key] = $value;
		return self::$timerSettings[$key];
	}

	/**
	 * Save the current time of the given timer which can be later retrieved with getSavedTimer($key)
	 * 
	 * Note this also stops/removes the timer. 
	 * 
	 * @param string $key
	 * @param string $note Optional note to include in getSavedTimer
	 * @return bool|string Returns elapsed time, or false if timer didn't exist
	 *
	 */
	static public function saveTimer($key, $note = '') {
		if(!isset(self::$timers[$key])) return false;
		self::$savedTimers[$key] = self::stopTimer($key); 
		if($note) self::$savedTimerNotes[$key] = $note; 
		return self::$savedTimers[$key]; 
	}
	
	/**
	 * Return the time recorded in the saved timer $key
	 * 
	 * @param string $key
	 * @return string Blank if timer not recognized
	 *
	 */
	static public function getSavedTimer($key) {
		$value = isset(self::$savedTimers[$key]) ? self::$savedTimers[$key] : null;	
		if(!is_null($value) && isset(self::$savedTimerNotes[$key])) $value = "$value - " . self::$savedTimerNotes[$key];
		return (string) $value; 
	}

	/**
	 * Return all saved timers in associative array indexed by key
	 *
	 * @return array
	 *
	 */
	static public function getSavedTimers() {
		$timers = self::$savedTimers;
		arsort($timers); 
		foreach($timers as $key => $timer) {
			$timers[$key] = self::getSavedTimer($key); // to include notes
		}
		return $timers; 
	}

	/**
	 * Remove a previously saved timer
	 * 
	 * @param string $key
	 * @since 3.0.202
	 * 
	 */
	static public function removeSavedTimer($key) {
		unset(self::$savedTimers[$key]); 
		unset(self::$savedTimerNotes[$key]); 
	}

	/**
	 * Remove all saved timers
	 * 
	 * @since 3.0.202
	 * 
	 */
	static public function removeSavedTimers() {
		self::$savedTimers = array();
		self::$savedTimerNotes = array();
	}

	/**
	 * Reset a timer so that it starts timing again from right now
	 * 
	 * @param string $key
	 * @return string|int
	 *
	 */
	static public function resetTimer($key) {
		self::removeTimer($key); 
		return self::timer($key); 
	}

	/**
	 * Remove a timer completely
	 * 
	 * @param string $key
	 *
	 */
	static public function removeTimer($key) {
		unset(self::$timers[$key]); 
	}

	/**
	 * Remove all active timers
	 *
	 */
	static public function removeAll() {
		self::$timers = array();
	}
	
	/**
	 * Get all active timers in array with timer name (key) and start time (value)
	 *
	 * @return array
	 * @since 3.0.158
	 *
	 */
	static public function getAll() {
		return self::$timers;
	}

	/**
	 * Return a backtrace array that is simpler and more PW-specific relative to PHP’s debug_backtrace
	 * 
	 * @param array $options
	 * - `limit` (int): The limit for the backtrace or 0 for no limit. (default=0)
	 * - `flags` (int): Flags as used by PHP’s debug_backtrace() function. (default=DEBUG_BACKTRACE_PROVIDE_OBJECT)
	 * - `showHooks` (bool): Show inernal methods for hook calls? (default=false)
	 * - `getString` (bool): Get newline separated string rather than array? (default=false)
	 * - `getCnt` (bool): Get index number count, used for getString option only. (default=true)
	 * - `getFile` (bool|string): Get filename? Specify one of true, false or 'basename'. (default=true)
	 * - `maxCount` (int): Max size for arrays (default=10)
	 * - `maxStrlen` (int): Max length for strings (default=100)
	 * - `maxDepth` (int): Max allowed recursion depth when converting variables to strings.  (default=5)
	 * - `ellipsis` (string): Show this ellipsis when a long value is truncated (default='…')
	 * - `skipCalls` (array): Method/function calls to skip. 
	 * @return array|string
	 * @since 3.0.136
	 * 
	 */
	static public function backtrace(array $options = array()) {
		
		$defaults = array(
			'limit' => 0, // the limit argument for the debug_backtrace call
			'flags' => DEBUG_BACKTRACE_PROVIDE_OBJECT, // flags for PHP debug_backtrace function
			'showHooks' => false, // show internal methods for hook calls?
			'getString' => false, // get newline separated string rather than array?
			'getCnt' => true, // get index number count (for getString only)
			'getFile' => true,  // get filename? true, false or 'basename'
			'maxCount' => 10, // max size for arrays
			'maxStrlen' => 100, // max length for strings
			'maxDepth' => 5, // max allowed recursion depth when converting variables to strings
			'ellipsis' => ' …', // show this ellipsis when a long value is truncated
			'skipCalls' => array(), // method/function calls to skip
		);
		
		$options = array_merge($defaults, $options);
		if($options['limit']) $options['limit']++;
		$traces = @debug_backtrace($options['flags'], $options['limit']);
		$config = wire()->config;
		$rootPath = ProcessWire::getRootPath(true);
		$rootPath2 = $config && $config->paths ? $config->paths->root : $rootPath;
		array_shift($traces); // shift of the simpleBacktrace call, which is not needed
		$apiVars = array();
		$result = array();
		$cnt = 0;
		
		foreach(wire('all') as $name => $value) {
			if(!is_object($value)) continue;
			$apiVars[wireClassName($value)] = '$' . $name;
		}
		
		foreach($traces as $trace) {
			
			if(!is_array($trace) || !isset($trace['function']) || !isset($trace['file'])) {
				continue;
			} else if(count($options['skipCalls']) && in_array($trace['function'], $options['skipCalls'])) {
				continue;
			}

			$obj = null;
			$class = '';
			$type = '';
			$args = isset($trace['args']) ? $trace['args'] : array();
			$argStr = '';
			$file = $trace['file'];
			$basename = basename($file); 
			$function = $trace['function'];	
			$isHookableCall = false;
			
			if(isset($trace['object'])) {
				$obj = $trace['object'];
				$class = wireClassName($obj);
			} else if(isset($trace['class'])) {
				$class = wireClassName($trace['class']); 
			}
			
			if($class) {
				$type = isset($trace['type']) ? $trace['type'] : '.';
			}

			if(!$options['showHooks']) {
				if($basename === 'Wire.php' && !wireMethodExists('Wire', $function)) continue;
				if($class === 'WireHooks' || $basename === 'WireHooks.php') continue;
			}
			
			if(strpos($function, '___') === 0) {
				$isHookableCall = '___';
			} else if($obj && !method_exists($obj, $function) && method_exists($obj, "___$function")) {
				$isHookableCall = true;
			}
			
			if($type === '->' && isset($apiVars[$class])) {
				// use API var name when available
				if(strtolower($class) === strtolower(ltrim($apiVars[$class], '$'))) {
					$class = $apiVars[$class];
				} else {
					$class = "$class " . $apiVars[$class];
				}
			}
			
			if($basename === 'Wire.php' && $class !== 'Wire') {
				$ref = new \ReflectionClass($trace['class']);
				$file = $ref->getFileName();
			}

			// rootPath and rootPath2 can be different if one of them represented by a symlink
			$file = str_replace($rootPath, '/', $file);
			if($rootPath2 !== $rootPath) $file = str_replace($rootPath2, '/', $file);
			
			if(($function === '__call' || $function == '_callMethod') && count($args)) {
				$function = array_shift($args); 
			}
			
			if(!$options['showHooks'] && $isHookableCall === '___') {
				$function = substr($function, 3);
			}
			
			if(!empty($args)) {
				$newArgs = array();
				if($isHookableCall && count($args) === 1 && is_array($args[0])) {
					$newArgs = $args[0];
				}
				foreach($args as $arg) {
					if(is_object($arg)) {
						$arg = wireClassName($arg) . ' $obj';
					} else if(is_array($arg)) { 
						$count = count($arg); 
						if($count < 4) {
							$arg = $count ? self::traceStr($arg, array('maxDepth' => 2)) : '[]';
						} else {
							$arg = 'array(' . count($arg) . ')';
						}
					} else if(is_string($arg)) {
						if(strlen("$arg") > $options['maxStrlen']) $arg = substr($arg, 0, $options['maxStrlen']) . ' …';
						$arg = '"' . $arg . '"';
					} else if(is_bool($arg)) {
						$arg = $arg ? 'true' : 'false';
					} else if($arg === null) {
						$arg = 'null';
					} else {
						// leave as-is (int, float, etc.)
					}
					$newArgs[] = $arg;
				}
				$argStr = implode(', ', $newArgs); 
				if($argStr === '[]') $argStr = '';
			}

			if($options['getFile'] === 'basename') $file = basename($file);
			
			$call = "$class$type$function($argStr)";
			$file = "$file:$trace[line]";
			
			if($options['getString']) {
				$str = '';
				if($options['getCnt']) $str .= "$cnt. ";
				$str .= "$file » $call";
				$result[] = $str;
			} else {
				$result[] = array(
					'file' => $file,
					'call' => $call,
				);
			}
			
			$cnt++;
		}
		
		if($options['getString']) $result = implode("\n", $result); 
		
		return $result;
	}

	/**
	 * Convert value to string for backtrace method
	 * 
	 * @param $value
	 * @param array $options
	 * @return null|string
	 * 
	 */
	static protected function traceStr($value, array $options = array()) {
		
		$defaults = array(
			'maxCount' => 10, // max size for arrays
			'maxStrlen' => 100, // max length for strings
			'maxDepth' => 5,
			'ellipsis' => ' …'
		);

		static $depth = 0;
		$options = count($options) ? array_merge($defaults, $options) : $defaults; 
		$depth++;
		
		if(is_object($value)) {
			// object
			$str = wireClassName($value);
			if($str === 'HookEvent') {
				$str .= ' $event';
			} else if(method_exists($value, '__toString')) {
				$value = (string) $value;
				if($value !== $str) {
					if(strlen($value) > $options['maxStrlen']) {
						$value = substr($value, 0, $options['maxStrlen']) . $options['ellipsis'];
					}
					$str .= "($value)";
				}
			}
		} else if(is_array($value)) {
			// array
			if(empty($value)) {
				$str = '[]';
			} else if($depth >= $options['maxDepth']) {
				$str = "array(" . count($value) . ")";
			} else {
				$suffix = '';
				if(count($value) > $options['maxCount']) {
					$value = array_slice($value, 0, $options['maxCount']);
					$suffix = $options['ellipsis'];
				}
				foreach($value as $k => $v) {
					if(is_string($k) && strlen($k)) {
						$value[$k] = "$$k => " . self::traceStr($v, $options);
					} else {
						$value[$k] = self::traceStr($v, $options);
					}
				}
				$str = '[ ' . implode(', ', $value) . $suffix . ' ]';
			}
		} else if(is_string($value)) {
			// string
			if(strlen($value) > $options['maxStrlen']) {
				$value = substr($value, 0, $options['maxStrlen']) . $options['ellipsis'];
			}
			$hasDQ = strpos($value, '"') !== false;
			$hasSQ = strpos($value, "'") !== false;
			if(($hasDQ && $hasSQ) || $hasSQ) {
				$value = str_replace('"', '\\"', $value);
				$str = '"' . $value . '"';
			} else {
				$str = "'$value'";
			}
		} else if(is_bool($value)) {
			// true or false
			$str = $value ? 'true' : 'false';
		} else {
			// int, float or other
			$str = $value;
		}
		
		$depth--;
		
		return $str;
	}

	/**
	 * Dump any variable to a debug string
	 * 
	 * @param int|float|object|string|array $value
	 * @param array $options
	 *  - `method` (string): Dump method to use, one of: json_encode, var_dump, var_export, print_r (default=json_encode)
	 *  - `html` (bool): Return output-ready HTML string? (default=false)
	 * @return string
	 * @since 3.0.208
	 * 
	 */
	static public function toStr($value, array $options = array()) {
		
		$defaults = array(
			'method' => 'json_encode',
			'html' => false,
		);
		
		$options = array_merge($defaults, $options);
		$method = $options['method'];
		$prepend = '';
		
		if(is_object($value)) { 
			// we format objects to arrays or strings
			$className = wireClassName($value);
			$classInfo = "object:$className";
			$objectValue = $value;
			if($objectValue instanceof \Countable) {
				$classInfo .= '(' . count($objectValue) . ')';
			}
			if($value instanceof Wire) {
				$value = $value->debugInfoSmall();
			} else if(method_exists($value, '__debugInfo')) {
				$value = $value->__debugInfo();
			} else if(method_exists($value, '__toString')) {
				$value = $classInfo . ":\"$value\"";
			} else {
				$value = $classInfo;
			}
			if(is_array($value)) {
				if(empty($value)) {
					$value = $classInfo;
					if(method_exists($objectValue, '__toString')) {
						$stringValue = (string) $objectValue;
						if($stringValue != $className) $value .= ":\"$stringValue\"";
					}
				} else {
					$prepend = "$classInfo ";
				}
			}
			if(is_string($value)) {
				$method = '';
			}
		} else if(is_int($value)) {
			$prepend = 'int:';
		} else if(is_float($value)) {
			$prepend = 'float:';
		} else if(is_string($value)) {
			$prepend = 'string:';
		} else if(is_callable($value)) {
			$prepend = 'callable:';
		} else if(is_resource($value)) {
			$prepend = 'resource:';
		}
		
		switch($method) {
			case 'json_encode':
				$value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$value = str_replace('    ', '  ', $value);
				if(strpos($value, '\\"') !== false) $value = str_replace('\\"', "'", $value);
				break;
			case 'var_export':
				$value = var_export($value, true);
				break;
			case 'var_dump':	
				ob_start();
				var_dump($value);
				$value = ob_get_contents();
				ob_end_clean();
				break;
			case 'print_r':	
				$value = print_r($value, true);
				break;
			default:	
				$value = (string) $value;
		}
		
		if($method && $method != 'json_encode') {
			// array is obvious and needs no label
			if(stripos($value, 'array') === 0) $value = trim(substr($value, 5));
		}
		
		if($prepend) $value = $prepend . trim($value);
		if($options['html']) $value = '<pre>' . wire()->sanitizer->entities($value) . '</pre>';

		return $value;
	}
}
