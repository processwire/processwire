<?php namespace ProcessWire;

/**
 * Random generators for ProcessWire 
 * 
 * Includes methods for random strings, numbers, arrays and passwords. 
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.111
 * 
 * Usage example
 * ~~~~~
 * $random = new WireRandom();
 * $s = $random->alphanumeric(10);
 * $i = $random->integer(0, 10); 
 * ~~~~~
 * 
 */

class WireRandom extends Wire {
	
	/**
	 * Return random alphanumeric, alpha or numeric string
	 * 
	 * This method uses cryptographically secure random generation unless you specify `true` for
	 * the `fast` option, in which case it will use cryptographically secure method only if PHP is
	 * version 7+ or the mcrypt library is available. 
	 * 
	 * **Note about the `allow` option:**
	 * If this option is used, it overrides the `alpha` and `numeric` options and creates a
	 * string that has only the given characters. If given characters are not ASCII alpha or
	 * numeric, then the `fast` option is always used, as the crypto-secure option does not
	 * support non-alphanumeric characters. When the `allow` option is used, the `strict`
	 * option does not apply.
	 *
	 * @param int $length Required length of string, or 0 for random length
	 * @param array $options Options to modify default behavior:
	 *  - `alpha` (bool): Allow ASCII alphabetic characters? (default=true)
	 *  - `upper` (bool): Allow uppercase ASCII alphabetic characters? (default=true)
	 *  - `lower` (bool): Allow lowercase ASCII alphabetic characters? (default=true)
	 *  - `numeric` (bool): Allow numeric characters 0123456789? (default=true)
	 *  - `strict` (bool): Require that at least 1 character representing each true option above is present? (default=false)
	 *  - `allow` (array|string): Only allow these ASCII alpha or digit characters, see notes. (default='')
	 *  - `disallow` (array|string): Do not allow these characters. (default='')
	 *  - `require` (array|string): Require that these character(s) are present. (default='')
	 *  - `extras` (array|string): Also allow these non-alphanumeric extra characters. (default='')
	 *  - `minLength` (int): If $length argument is 0, minimum length of returned string. (default=10)
	 *  - `maxLength` (int): If $length argument is 0, maximum length of returned string. (default=40)
	 *  - `noRepeat` (bool): Prevent same character from appearing more than once in sequence? (default=false)
	 *  - `noStart` (string|array): Do not start string with these characters. (default='')
	 *  - 'noEnd` (string|array): Do not end string with these characters. (default='')
	 *  - `fast` (bool): Use faster method? (default=true if PHP7 or mcrypt available, false if not)
	 * @return string
	 * @throws WireException
	 * @since 3.0.111
	 *
	 */
	public function alphanumeric($length = 0, array $options = array()) {

		$defaults = array(
			'alpha' => true,
			'upper' => true,
			'lower' => true,
			'numeric' => true,
			'strict' => false,
			'allow' => '',
			'disallow' => array(),
			'extras' => array(),
			'require' => array(),
			'minLength' => 10,
			'maxLength' => 40,
			'noRepeat' => false,
			'noStart' => array(),
			'noEnd' => array(),
			'fast' => $this->cryptoSecure(),
		);

		$alphaUpperChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$alphaLowerChars = 'abcdefghijklmnopqrstuvwxyz';
		$numericChars = '0123456789';

		$options = array_merge($defaults, $options);
		$allowed = '';

		if($length < 1) {
			$length = $this->integer($options['minLength'], $options['maxLength']);
		}

		// some options can be specified as strings, but we want them as arrays
		foreach(array('disallow', 'extras', 'require', 'noStart', 'noEnd') as $name) {
			$val = $options[$name];
			if(is_array($val)) continue;
			if(strlen($val)) {
				$options[$name] = str_split($val);
			} else {
				$options[$name] = array();
			}
		}

		// some options can be specified as arrays, but we want them as strings
		foreach(array('allow', 'noStart', 'noEnd') as $name) {
			if(is_array($options[$name])) $options[$name] = implode('', $options[$name]);
		}

		if(strlen($options['allow'])) {
			// only fast option supports non-alphanumeric characters specified in allow option
			if(!ctype_alnum($options['allow'])) $options['fast'] = true;
			$allowed = $options['allow'];

		} else {
			if($options['alpha']) {
				if($options['upper']) $allowed .= $alphaUpperChars;
				if($options['lower']) $allowed .= $alphaLowerChars;
			}
			if($options['numeric']) {
				$allowed .= $numericChars;
			}
		}

		if(count($options['extras'])) {
			$allowed .= implode('', $options['extras']);
		}

		if(count($options['disallow'])) {
			$allowed = str_replace($options['disallow'], '', $allowed);
		}

		foreach($options['require'] as $c) {
			if(strpos($allowed, $c) === false) $allowed = '';
		}

		if(!strlen($allowed)) {
			throw new WireException("Specified options prevent any alphanumeric string from being created");
		}

		do {
			if($options['fast']) {
				$value = $this->string1($length, $allowed, $options);
			} else {
				$value = $this->string2($length, $allowed, $options);
			}

			// check that all required characters are present
			if(count($options['require'])) {
				$n = 0;
				foreach($options['require'] as $c) {
					if(strpos($value, $c) === false) $n++;
				}
				if($n) continue;
			}

			// enforce returned value having at least one of each requested type (alpha, upper, lower, numeric)
			if($options['strict'] && !strlen($options['allow'])) {
				if($options['alpha'] && $options['upper'] && !preg_match('/[A-Z]/', $value)) continue;
				if($options['alpha'] && $options['lower'] && !preg_match('/[a-z]/', $value)) continue;
				if($options['numeric'] && !preg_match('/[0-9]/', $value)) continue;
			}

			if(strlen($value) > $length) $value = substr($value, 0, $length);
			if(strlen($options['noStart'])) $value = ltrim($value, $options['noStart']);
			if(strlen($options['noEnd'])) $value = rtrim($value, $options['noEnd']);

		} while(strlen($value) < $length);

		return $value;
	}

	/**
	 * Generate a random string using faster method
	 * 
	 * @param int $length Required length
	 * @param string $allowed Characters allowed
	 * @param array $options
	 *  - `noRepeat` (bool): True if two of the same character may not be repeated in sequence.
	 * @return string
	 * 
	 */
	protected function string1($length, $allowed, array $options) {
		$defaults = array(
			'noRepeat' => false,
		);
		$options = array_merge($defaults, $options);
		$value = '';
		$lastChar = '';
		for($x = 0; $x < $length; $x++) {
			$n = $this->integer(0, strlen($allowed) - 1);
			$c = $allowed[$n];
			if($options['noRepeat'] && $c === $lastChar) {
				$x--;
				continue;
			}
			$value .= $c;
			$lastChar = $c;
		}
		return $value; 
	}

	/**
	 * Generate random string using method that pulls from the base64 method
	 * 
	 * @param int $length Required length
	 * @param string $allowed Allowed characters
	 * @param array $options See options for alphanumeric() method
	 *
	 * @return string
	 * 
	 */
	protected function string2($length, $allowed, array $options) {
		
		$defaults = array(
			'extras' => array(),
			'alpha' => true,
			'lower' => true, 
			'upper' => true, 
			'noRepeat' => false,
		);
		
		$options = array_merge($defaults, $options);
		$qty = 0;
		$value = '';
		$numExtras = count($options['extras']);
		$base64Extras = array('slash' => '/', 'period' => '.');
		
		do {
			$baseLen = strlen($allowed) < 50 ? $length * 3 : $length * 2;
			$baseStr = $this->base64($baseLen);
			
			if($numExtras && !ctype_alnum($baseStr)) {
				// base64 string includes "/" or "." characters and we have substitutions (extras)
				$r = 0; // non-zero if we need to perform replacements at the ed
				foreach($base64Extras as $name => $c) {
					while(strpos($baseStr, $c) !== false) {
						list($a, $b) = explode($c, $baseStr, 2);
						$n = $numExtras > 1 ? $this->integer(0, $numExtras-1) : 0;
						$x = $options['extras'][$n];
						if(in_array($x, $base64Extras)) {
							$x = $name;
							$r++;
						}
						$baseStr = $a . $x . $b;
					}
				}
				
				if($r) {
					// replacements necessary
					$baseStr = str_replace(array_keys($base64Extras), array_values($base64Extras), $baseStr);
				}
				
				unset($a, $b, $c, $r, $x);
			}
			
			if($options['alpha']) {
				if($options['lower'] && !$options['upper']) {
					$baseStr = strtolower($baseStr);
				} else if($options['upper'] && !$options['lower']) {
					$baseStr = strtoupper($baseStr);
				}
			}
			
			$lastChar = '';
			for($n = 0; $n < strlen($baseStr); $n++) {
				$c = $baseStr[$n];
				if(strpos($allowed, $c) === false) continue;
				if($options['noRepeat'] && $c === $lastChar) continue;
				$value .= $c;
				$lastChar = $c;
				if(++$qty >= $length) break;
			}
			
		} while($qty < $length);
		
		return $value;
	}
	
	/**
	 * Return string of random ASCII alphabetical letters
	 *
	 * @param int $length Required length of string or 0 for random length
	 * @param array $options See options for alphanumeric() method
	 * @return string
	 * @since 3.0.111
	 *
	 */
	public function alpha($length = 0, array $options = array()) {
		if(!isset($options['numeric'])) $options['numeric'] = false;
		return $this->alphanumeric($length, $options);
	}

	/**
	 * Return string of random numbers/digits
	 *
	 * @param int $length Required length of string or 0 for random length
	 * @param array $options See options for alphanumeric() method
	 * @return string
	 * @since 3.0.111
	 *
	 */
	public function numeric($length = 0, array $options = array()) {
		$options['alpha'] = false;
		return $this->alphanumeric($length, $options);
	}

	/**
	 * Get a random integer
	 *
	 * @param int $min Minimum allowed value (default=0). 
	 * @param int $max Maximum allowed value (default=PHP_INT_MAX). 
	 * @param array $options
	 *  - `info` (bool): Return array of [value, type] indicating what type of random generator was used? (default=false).
	 *  - `cryptoSecure` (bool): Throw WireException if cryptographically secure type not available (default=false).
	 * @return int|array Returns integer, or will return array if $info option specified. 
	 * @throws WireException
	 *
	 */
	public function integer($min = 0, $max = PHP_INT_MAX, array $options = array()) {

		$defaults = array(
			'info' => false,
			'cryptoSecure' => false,
		);
		
		if(is_array($min)) {
			$options = $min;
			$min = isset($options['min']) ? (int) $options['min'] : 0;
		} else if(is_array($max)) {
			$options = $max;
			$max = isset($options['max']) ? (int) $options['max'] : PHP_INT_MAX;
		}
		
		$options = array_merge($defaults, $options);
		
		if($max == $min) return $max;
		if($max < $min) throw new WireException('Max may not be less than min');
		
		if(function_exists('random_int')) {
			// PHP 7 has random_int, previous versions do not
			$value = random_int($min, $max);
			$type = 'random_int';

		} else if(function_exists('mcrypt_create_iv')) {
			// via user contributed notes at: http://php.net/manual/en/function.random-int.php
			$range = $counter = $max - $min;
			$bits = 1;
			while($counter >>= 1) ++$bits;
			$bytes = (int) max(ceil($bits / 8), 1);
			$bitmask = pow(2, $bits) - 1;
			if($bitmask >= PHP_INT_MAX) $bitmask = PHP_INT_MAX;
			do {
				$result = hexdec(bin2hex(mcrypt_create_iv($bytes, MCRYPT_DEV_URANDOM))) & $bitmask;
			} while($result > $range);
			$value = $result + $min;
			$type = 'mcrypt';

		} else if($options['cryptoSecure']) {
			throw new WireException('cryptoSecure required and neither PHP7 random_int() or mcrypt available');

		} else {
			// mt_rand (not cryptographically secure)
			$value = mt_rand($min, $max);
			$type = 'mt_rand';
		}

		if($options['info']) return array($value, $type);

		return $value;
	}

	/**
	 * Get a random item (or items, key or keys) from the given array 
	 * 
	 * - Given array may be regular or associative.
	 * 
	 * - If given a `qty` other than 1 (default) then the `getArray` option is assumed true, unless a
	 *   different value for the `getArray` option was manually specified.
	 * 
	 * - When using the `getArray` option, returned array will have keys retained, except when `qty` 
	 *   option exceeds the number of items in given array `$a`, then keys will not be retained. 
	 * 
	 * @param array $a Array to get random item from
	 * @param array $options Options to modify behavior:
	 *  - `qty` (int): Return this quantity of item(s) (default=1). 
	 *  - `getKey` (bool): Return item key(s) rather than values. 
	 *  - `getArray` (bool): Return array (with original keys) rather than value (default=false if qty==1, true if not).
	 * @return mixed|array|null
	 * 
	 */
	protected function arrayItem(array $a, array $options = array()) {
		
		$defaults = array(
			'qty' => 1, 
			'getKey' => false, 
			'getArray' => null,  // null=not specified
		);
	
		$options = array_merge($defaults, $options);
		$count = count($a);
		$keys = array_keys($a);
		$items = array();
		$item = null;
		$keepKeys = true;

		// if getArray option not specified, auto determine from qty
		if($options['getArray'] === null) {
			$options['getArray'] = $options['qty'] === 1 ? false : true;
		}
		
		// if given an empty array, return an empty value
		if(!$count) return $options['getArray'] ? array() : null;
	
		// if impossible qty requested, adjust according to what is present
		if($options['qty'] < 1) $options['qty'] = $count;
		
		do {
			$keysIndex = $this->integer(0, count($keys) - 1);
			$key = $keys[$keysIndex];
			$item = $options['getKey'] ? $key : $a[$key];
			if($keepKeys) {
				// if getting more than one item, ensure it’s not the same one we already got
				if(array_key_exists($key, $items)) continue;
				$items[$key] = $item;
			} else {
				// they are requesting a quantity larger than what’s in the array, so disregard keys or duplicates
				$items[] = $item;
			}
			// if more items requested than in original array, and we’ve got all of them, stop keeping track of keys
			if($options['qty'] > $count && count($items) === $count) {
				$keepKeys = false;
				$items = array_values($items);
			}
		} while(count($items) < $options['qty']);
	
		if($options['getArray']) {
			// if requesting a qty greater than what’s in array, the first $count items will be unique
			// so run them through a shuffle() to prevent that predictable behavior
			if($options['qty'] > $count) shuffle($items);
			return $items;
		} else {
			return $item;
		}
	}

	/**
	 * Get a random value from given array
	 * 
	 * @param array $a Array to get random value from
	 * @return mixed|null
	 * 
	 */
	public function arrayValue(array $a) {
		return $this->arrayItem($a);
	}

	/**
	 * Return a random version of given array or a quantity of random items
	 * 
	 * Array keys are retained in return value, unless requested $qty exceeds
	 * the quantity of items in given array. 
	 * 
	 * @param array $a Array to get random items from. 
	 * @param int $qty Quantity of items, or 0 to return all (default=0). 
	 * @return array
	 * 
	 */
	public function arrayValues(array $a, $qty = 0) {
		return $this->arrayItem($a, array('getArray' => true, 'qty' => $qty)); 
	}

	/**
	 * Get a random key from given array
	 * 
	 * @param array $a
	 * @return string|int
	 * 
	 */
	public function arrayKey(array $a) {
		$options['getKey'] = true;
		return $this->arrayItem($a, array('getKey' => true));
	}

	/**
	 * Get a random version of all keys in given array (or a specified quantity of them)
	 * 
	 * @param array $a Array to get random keys from. 
	 * @param int $qty Quantity of unique keys to return or 0 for all (default=0)
	 * @return array
	 * 
	 */
	public function arrayKeys(array $a, $qty = 0) {
		return $this->arrayItem($a, array('getKey' => true, 'getArray' => true, 'qty' => $qty));
	}

	/**
	 * Shuffle a string or an array
	 * 
	 * Unlike PHP’s shuffle() function, this method:
	 * 
	 * - Accepts strings or arrays and returns the same type.
	 * - Maintains array keys, if given an array. 
	 * - Returns a copy of the value rather than modifying the given value directly. 
	 * - Is cryptographically secure if PHP7 or mcrypt available. 
	 * 
	 * @param string|array $value
	 * @return string|array
	 * 
	 */
	public function shuffle($value) {
		
		$isArray = is_array($value);
		
		if(!$isArray) {
			if(function_exists('mb_substr')) {
				$a = array();
				for($n = 0; $n < mb_strlen($value); $n++) {
					$c = mb_substr($value, $n, 1);
					$a[] = $c;
				}
				$value = $a;
			} else {
				$value = str_split((string) $value);
			}
		}
		
		$value = $this->arrayValues($value);
		if(!$isArray) $value = implode('', $value);
		
		return $value; 
	}

	/**
	 * Generate and return a random password
	 *
	 * Default settings of this method are to generate a random but readable password without characters that
	 * tend to have readability issues, and using only ASCII characters (for broadest keyboard compatibility).
	 *
	 * @param array $options Specify any of the following options (all optional):
	 *  - `minLength` (int): Minimum lenth of returned value (default=7).
	 *  - `maxLength` (int): Maximum lenth of returned value, will be exceeded if needed to meet other options (default=15).
	 *  - `minLower` (int): Minimum number of lowercase characters required (default=1).
	 *  - `minUpper` (int): Minimum number of uppercase characters required (default=1).
	 *  - `maxUpper` (int): Maximum number of uppercase characters allowed (0=any, -1=none, default=3).
	 *  - `minDigits` (int): Minimum number of digits required (default=1).
	 *  - `maxDigits` (int): Maximum number of digits allowed (0=any, -1=none, default=0).
	 *  - `minSymbols` (int): Minimum number of non-alpha, non-digit symbols required (default=0).
	 *  - `maxSymbols` (int): Maximum number of non-alpha, non-digit symbols to allow (0=any, -1=none, default=3).
	 *  - `useSymbols` (array): Array of characters to use as "symbols" in returned value (see method for default).
	 *  - `disallow` (array): Disallowed characters that may be confused with others (default=O,0,I,1,l).
	 *
	 * @return string
	 *
	 */
	public function pass(array $options = array()) {

		$defaults = array(
			'minLength' => 7,
			'maxLength' => 15,
			'minUpper' => 1,
			'maxUpper' => 3,
			'minLower' => 1,
			'minDigits' => 1,
			'maxDigits' => 0,
			'minSymbols' => 0,
			'maxSymbols' => 3,
			'useSymbols' => array('@', '#', '$', '%', '^', '*', '_', '-', '+', '?', '(', ')', '!', '.', '=', '/'),
			'disallow' => array('O', '0', 'I', '1', 'l'),
		);
		
		$options = array_merge($defaults, $options);
		
		// check if we need to increase maxLength to accommodate given options
		$minLength = $options['minUpper'] + $options['minLower'] + $options['minDigits'] + $options['minSymbols'];
		if($minLength > $options['maxLength']) $options['maxLength'] = $minLength;
		$value = $this->passCreate($options);
		if(strlen($value) > $options['maxLength']) $value = $this->passTrunc($value, $options);
		
		return $value;
	}

	/**
	 * Create a password (for password method)
	 * 
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function passCreate(array $options) {
		
		$length = $this->integer($options['minLength'], $options['maxLength']);
		$base64Symbols = array('/' , '.');
		$disallow = $options['disallow'];
		$disallowCase = array(); // with both upper and lower versions

		foreach($disallow as $c) {
			$c = strtolower($c);
			$disallowCase[$c] = $c;
			$c = strtoupper($c);
			$disallowCase[$c] = $c;
		}

		// build foundation of password using base64 string
		do {
			$value = $this->base64($length);
			$valid = preg_match('/[A-Z]/i', $value) && preg_match('/[0-9]/', $value);
		} while(!$valid);

		// limit amount of characters that are too common in base64 string
		foreach($base64Symbols as $char) {
			do {
				$pos = strpos($value, $char);
				if($pos === false) break;
				$value[$pos] = $this->alphanumeric(1, array('disallow' => $disallow));
			} while(1);
		}

		// manage quantity of symbols
		if($options['maxSymbols'] > -1) {
			// ensure there are a certain quantity of symbols present
			if($options['maxSymbols'] === 0) {
				$numSymbols = $this->integer($options['minSymbols'], floor(strlen($value) / 2));
			} else {
				$numSymbols = $this->integer($options['minSymbols'], $options['maxSymbols']);
			}
			$symbols = $options['useSymbols'];
			shuffle($symbols);
			for($n = 0; $n < $numSymbols; $n++) {
				$symbol = array_shift($symbols);
				$value .= $symbol;
			}
		} else {
			// no symbols, remove those commonly added in base64 string
			foreach($base64Symbols as $char) {
				$disallow[] = $char;
				$disallowCase[$char] = $char;
			}
		}

		// manage quantity of uppercase characters
		if($options['maxUpper'] > 0 || ($options['minUpper'] > 0 && $options['maxUpper'] > -1)) {
			// limit or establish the number of uppercase characters
			if(!$options['maxUpper']) $options['maxUpper'] = floor(strlen($value) / 2);
			$numUpper = $this->integer($options['minUpper'], $options['maxUpper']);
			if($numUpper) {
				$value = strtolower($value);
				$test = $this->wire('sanitizer')->alpha($value);
				if(strlen($test) < $numUpper) {
					// there aren't enough characters present to meet requirements, so add some	
					$value .= $this->alpha($numUpper - strlen($test), array('disallow' => $disallow));
				}
				for($i = 0; $i < strlen($value); $i++) {
					$c = strtoupper($value[$i]);
					if(in_array($c, $disallow)) continue;
					if($c !== $value[$i]) $value[$i] = $c;
					if($c >= 'A' && $c <= 'Z') $numUpper--;
					if(!$numUpper) break;
				}
				// still need more? append new characters as needed
				if($numUpper) $value .= strtoupper($this->alpha($numUpper, array('disallow' => $disallowCase)));
			}

		} else if($options['maxUpper'] < 0) {
			// disallow upper
			$value = strtolower($value);
		}

		// manage quantity of lowercase characters
		if($options['minLower'] > 0) {
			$test = preg_replace('/[^a-z]/', '', $value);
			if(strlen($test) < $options['minLower']) {
				// needs more lowercase
				$value .= strtolower($this->alpha($options['minLower'] - strlen($test), array('disallow' => $disallowCase)));
			}
		}

		// manage quantity of required digits
		if($options['minDigits'] > 0) {
			$test = $this->wire('sanitizer')->digits($value);
			$test = str_replace($options['disallow'], '', $test);
			$numDigits = $options['minDigits'] - strlen($test);
			if($numDigits > 0) {
				$value .= $this->numeric($numDigits, array('disallow' => $disallow));
			}
		}

		if($options['maxDigits'] > 0 || $options['maxDigits'] == -1) {
			// a maximum number of digits specified
			$numDigits = 0;
			for($n = 0; $n < strlen($value); $n++) {
				$c = $value[$n];
				$isDigit = ctype_digit($c);
				if($isDigit) $numDigits++;
				if($isDigit && $numDigits > $options['maxDigits']) {
					// convert digit to alpha
					$value[$n] = strtolower($this->alpha(1, array('disallow' => $disallowCase)));
				}
			}
		}

		// replace any disallowed characters
		foreach($disallow as $char) {
			$n = 0;
			do {
				$pos = strpos($value, $char);
				if($pos === false) break;
				if(ctype_digit($char)) {
					// find a different digit
					$c = $this->numeric(1, array('disallow' => $disallow));
				} else if(strtoupper($char) === $char) {
					// find a different uppercase char
					$c = strtoupper($this->alpha(1, array('disallow' => $disallowCase)));
				} else {
					// find a different lowercase char
					$c = strtolower($this->alpha(1, array('disallow' => $disallowCase)));
				}
				while(in_array($c, $disallow)) {
					// insurance fallback, not likely (impossible?) to occur
					$c = $this->alphanumeric(1);
				}
				$value[$pos] = $c;
			} while(++$n < 100);
		}

		// randomize, in case any operations above need it
		$value = $this->shuffle($value);
		
		return $value;
	}

	/**
	 * Truncate password to requested maxLength without removing required options (for password method)
	 * 
	 * @param string $value
	 * @param array $options See options from password() method
	 * @return string
	 * 
	 */
	protected function passTrunc($value, array $options) {
		
		$chars = array(
			'minLower' => array(),
			'minUpper' => array(),
			'minDigits' => array(),
			'minSymbols' => array(),
		);

		$value = str_split($value);

		for($n = 0; $n < count($value); $n++) {
			$c = $value[$n];
			if($c >= 'a' && $c <= 'z') {
				$chars['minLower'][$n] = $c;
			} else if($c >= 'A' && $c <= 'Z') {
				$chars['minUpper'][$n] = $c;
			} else if($c >= '0' && $c <= '9') {
				$chars['minDigits'][$n] = $c;
			} else if(in_array($c, $options['useSymbols'])) {
				$chars['minSymbols'][$n] = $c;
			}
		}

		$cnt = 0;
		$max = 100;

		while(count($value) > $options['maxLength'] && ++$cnt <= $max) {
			$key = $this->arrayKey($chars);
			if(count($chars[$key]) > $options[$key]) {
				$n = $this->arrayKey($chars[$key]);
				unset($chars[$key][$n]);
				unset($value[$n]);
			}
		}

		if($cnt >= $max) {
			// impossible to accommodate length request with given options
		}

		return implode('', $value);
	}
	
	/**
	 * Generate a truly random base64 string of a certain length
	 *
	 * This is largely taken from Anthony Ferrara's password_compat library:
	 * https://github.com/ircmaxell/password_compat/blob/master/lib/password.php
	 * Modified for camelCase, variable names, and function-based context by Ryan.
	 *
	 * @param int $requiredLength Length of string you want returned (default=22)
	 * @param array|bool $options Specify array of options or boolean to specify only `fast` option.
	 *  - `fast` (bool): Use fastest, not cryptographically secure method (default=false).
	 *  - `test` (bool|array): Return tests in a string (bool true), or specify array(true) to return tests array (default=false).
	 *    Note that if the test option is used, then the fast option is disabled.
	 * @return string|array Returns only array if you specify array for $test argument, otherwise returns string
	 *
	 */
	public function base64($requiredLength = 22, $options = array()) {

		$defaults = array(
			'fast' => false,
			'test' => false,
		);

		if(is_array($options)) {
			$options = array_merge($defaults, $options);
		} else {
			if(is_bool($options)) $defaults['fast'] = $options;
			$options = $defaults;
		}

		$buffer = '';
		$valid = false;
		$tests = array();
		$test = $options['test'];

		if($options['fast'] && !$test) {
			// fast mode for non-password use, uses only mt_rand() generated characters		
			$rawLength = $requiredLength;

		} else {
			// for password use, slower
			$rawLength = (int) ($requiredLength * 3 / 4 + 1);

			// mcrypt_create_iv 
			if((!$valid || $test) && function_exists('mcrypt_create_iv') && !defined('PHALANGER')) {
				// @operator added for PHP 7.1 which throws deprecated notice on this function call
				$buffer = @mcrypt_create_iv($rawLength, MCRYPT_DEV_URANDOM);
				if($buffer) $valid = true;
				if($test) $tests['mcrypt_create_iv'] = $buffer;
			} else if($test) {
				$tests['mcrypt_create_iv'] = '';
			}

			// PHP7 random_bytes
			if((!$valid || $test) && function_exists('random_bytes')) {
				try {
					$buffer = random_bytes($rawLength);
					if($buffer) $valid = true;
				} catch(\Exception $e) {
					$valid = false;
				}
				if($test) $tests['random_bytes'] = $buffer;
			} else if($test) {
				$tests['random_bytes'] = '';
			}

			// openssl_random_pseudo_bytes
			if((!$valid || $test) && function_exists('openssl_random_pseudo_bytes')) {
				$good = false;
				$buffer = openssl_random_pseudo_bytes($rawLength, $good);
				if($test) $tests['openssl_random_pseudo_bytes'] = $buffer . "\tNOTE=" . ($good ? 'strong' : 'NOT strong');
				if(!$good) $buffer = '';
				if($buffer) $valid = true;
			} else if($test) {
				$tests['openssl_random_pseudo_bytes'] = '';
			}

			// read from /dev/urandom
			if((!$valid || $test) && @is_readable('/dev/urandom')) {
				$f = fopen('/dev/urandom', 'r');
				$readLength = 0;
				if($test) $buffer = '';
				while($readLength < $rawLength) {
					$buffer .= fread($f, $rawLength - $readLength);
					$readLength = $this->_strlen($buffer);
				}
				fclose($f);
				if($readLength >= $rawLength) $valid = true;
				if($test) $tests['/dev/urandom'] = $buffer;
			} else if($test) {
				$tests['/dev/urandom'] = '';
			}
		}

		$bufferLength = $this->_strlen($buffer);

		// randomInteger or mt_rand() fast
		if(!$valid || $test || $bufferLength < $rawLength) {
			for($i = 0; $i < $rawLength; $i++) {
				if($i < $bufferLength) {
					$buffer[$i] = $buffer[$i] ^ chr($this->integer(0, 255));
				} else {
					$buffer .= chr($this->integer(0, 255));
				}
			}
			if($test) $tests['randomInteger'] = $buffer;
		}

		if($test) {
			// test mode
			$salt = '';
			foreach($tests as $name => $value) {
				$note = '';
				if(strpos($value, "\tNOTE=")) list($value, $note) = explode("\tNOTE=", $value);
				$value = empty($value) ? 'N/A' : $this->randomBufferToSalt($value, $requiredLength);
				$_name = str_pad($name, 28, ' ', STR_PAD_LEFT);
				$tests[$name] = $value;
				$salt .= "\n$_name: $value $note";
			}
			$salt = is_array($test) ? $tests : ltrim($salt, "\n");
		} else {
			// regular random string mode
			$salt = $this->randomBufferToSalt($buffer, $requiredLength);
		}

		return $salt;
	}
	
	/**
	 * Given random buffer string of bytes return base64 encoded salt
	 *
	 * @param string $buffer
	 * @param int $requiredLength
	 * @return string
	 *
	 */
	protected function randomBufferToSalt($buffer, $requiredLength) {
		$c1 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/'; // base64
		$c2 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'; // bcrypt64
		$salt = rtrim(base64_encode($buffer), '=');
		$salt = strtr($salt, $c1, $c2);
		$salt = substr($salt, 0, $requiredLength);
		return $salt;
	}

	/**
	 * Is a crypto secure method of generating numbers available?
	 * 
	 * @return bool
	 * 
	 */
	public function cryptoSecure() {
		return function_exists('random_int') || function_exists('mcrypt_create_iv');
	}
	
	/**
	 * Return string length, using mb_strlen() when available, or strlen() when not
	 *
	 * @param string $s
	 * @return int
	 *
	 */
	protected function _strlen($s) {
		return function_exists('mb_strlen') ? mb_strlen($s, '8bit') : strlen($s);
	}
}