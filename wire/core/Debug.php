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
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
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
			// start new timer
			$startTime = -microtime(true);
			if(!$key) {
				$key = (string) $startTime; 
				while(isset(self::$timers[$key])) $key .= "0";
			}
			self::$timers[(string) $key] = $startTime; 
			$value = $key; 
		} else {
			// return existing timer
			$value = number_format(self::$timers[$key] + microtime(true), 4);
		}

		return $value; 
	}

	/**
	 * Save the current time of the given timer which can be later retrieved with getSavedTimer($key)
	 * 
	 * Note this also stops/removes the timer. 
	 * 
	 * @param string $key
	 * @param string $note Optional note to include in getSavedTimer
	 * @return bool Returns false if timer didn't exist in the first place
	 *
	 */
	static public function saveTimer($key, $note = '') {
		if(!isset(self::$timers[$key])) return false;
		self::$savedTimers[$key] = self::timer($key); 
		self::removeTimer($key); 
		if($note) self::$savedTimerNotes[$key] = $note; 
		return true; 
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
		return $value; 
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
	 * Return a backtrace array that is simpler and more PW-specific relative to PHP’s debug_backtrace
	 * 
	 * @param array $options
	 * @return array|string
	 * @since 3.0.136
	 * 
	 */
	static public function backtrace(array $options = array()) {
		
		$defaults = array(
			'limit' => 0, // the limit argument for the debug_backtrace call
			'flags' => DEBUG_BACKTRACE_PROVIDE_OBJECT, // flags for PHP debug_backtrace method
			'showHooks' => false, // show internal methods for hook calls?
			'getString' => false, // get newline separated string rather than array?
			'maxCount' => 10, // max size for arrays
			'maxStrlen' => 100, // max length for strings
			'maxDepth' => 5, // max allowed recursion depth when converting variables to strings
			'ellipsis' => ' …', // show this ellipsis when a long value is truncated
			'skipCalls' => array(), // method/function calls to skip
		);
		
		$options = array_merge($defaults, $options);
		if($options['limit']) $options['limit']++;
		$traces = @debug_backtrace($options['flags'], $options['limit']);
		$config = wire('config');
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
		
		foreach($traces as $n => $trace) {
			
			if(!is_array($trace) || !isset($trace['function']) || !isset($trace['file'])) {
				continue;
			} else if(count($options['skipCalls']) && in_array($trace['function'], $options['skipCalls'])) {
				continue;
			}

			$obj = null;
			$class = '';
			$type = '';
			$args = $trace['args'];
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
							$arg = $count ? self::toStr($arg, array('maxDepth' => 2)) : '[]';
						} else {
							$arg = 'array(' . count($arg) . ')';
						}
					} else if(is_string($arg)) {
						if(strlen("$arg") > $options['maxStrlen']) $arg = substr($arg, 0, $options['maxStrlen']) . ' …';
						$arg = '"' . $arg . '"';
					} else if(is_bool($arg)) {
						$arg = $arg ? 'true' : 'false';
					} else {
						// leave as-is
					}
					$newArgs[] = $arg;
				}
				$argStr = implode(', ', $newArgs); 
				if($argStr === '[]') $argStr = '';
			}

			$call = "$class$type$function($argStr)";
			$file = "$file:$trace[line]";
			
			if($options['getString']) {
				$result[] = "$cnt. $file » $call";
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
	static protected function toStr($value, array $options = array()) {
		
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
					$value[$k] = self::toStr($v, $options); 
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
}
