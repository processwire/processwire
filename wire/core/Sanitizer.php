<?php namespace ProcessWire;

/**
 * ProcessWire Sanitizer
 *
 * Sanitizer provides shared sanitization functions as commonly used throughout ProcessWire core and modules
 * 
 * #pw-summary Provides methods for sanitizing and validating user input, preparing data for output, and more. 
 * #pw-use-constants
 *
 * Modules may also add methods to the Sanitizer as needed i.e. $this->sanitizer->addHook('myMethod', $myClass, 'myMethod'); 
 * See the Wire class definition for more details about the addHook method. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * @link http://processwire.com/api/variables/sanitizer/ Offical $sanitizer API variable Documentation
 * 
 * @method array($value, $sanitizer = null, array $options = array())
 *
 */

class Sanitizer extends Wire {

	/**
	 * Constant used for the $beautify argument of name sanitizer methods to indicate transliteration may be used. 
	 *
	 */
	const translate = 2;

	/**
	 * Beautify argument for pageName() to IDN encode UTF8 to ascii
	 * #pw-internal
	 * 
	 */
	const toAscii = 4;

	/**
	 * Beautify argument for pageName() to allow decode IDN ascii to UTF8
	 * #pw-internal
	 * 
	 */
	const toUTF8 = 8;

	/**
	 * Beautify argument for pageName() to indicate that UTF8 (in whitelist) is allowed
	 * 
	 * Unlike the toUTF8 option, no ascii to UTF8 conversion is allowed. 
	 * #pw-internal
	 * 
	 */
	const okUTF8 = 16;

	/**
	 * Caches the status of multibyte support.
	 *
	 */
	protected $multibyteSupport = false;

	/**
	 * Array of allowed ascii characters for name filters
	 *
	 */
	protected $allowedASCII = array();

	/**
	 * Construct the sanitizer
	 *
	 */
	public function __construct() {
		$this->multibyteSupport = function_exists("mb_internal_encoding"); 
		if($this->multibyteSupport) mb_internal_encoding("UTF-8");
		$this->allowedASCII = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
	}

	/*************************************************************************************************************
	 * STRING SANITIZERS
	 * 
	 */

	/**
	 * Internal filter used by other name filtering methods in this class
	 * 
	 * #pw-internal
	 *
	 * @param string $value Value to filter
	 * @param array $allowedExtras Additional characters that are allowed in the value
	 * @param string 1 character replacement value for invalid characters
	 * @param bool $beautify Whether to beautify the string, specify `Sanitizer::translate` to perform transliteration. 
	 * @param int $maxLength
	 * @return string
	 *
	 */
	public function nameFilter($value, array $allowedExtras, $replacementChar, $beautify = false, $maxLength = 128) {
		
		static $replacements = array();

		if(!is_string($value)) $value = $this->string($value);
		$allowed = array_merge($this->allowedASCII, $allowedExtras); 
		$needsWork = strlen(str_replace($allowed, '', $value));
		$extras = implode('', $allowedExtras);

		if($beautify && $needsWork) {
			if($beautify === self::translate && $this->multibyteSupport) {
				$value = mb_strtolower($value);

				if(empty($replacements)) {
					$configData = $this->wire('modules')->getModuleConfigData('InputfieldPageName');
					$replacements = empty($configData['replacements']) ? InputfieldPageName::$defaultReplacements : $configData['replacements'];
				}

				foreach($replacements as $from => $to) {
					if(mb_strpos($value, $from) !== false) {
						$value = mb_eregi_replace($from, $to, $value);
					}
				}
			}

			$v = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value);
			if($v) $value = $v;
			$needsWork = strlen(str_replace($allowed, '', $value)); 
		}

		if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength); 
		
		if($needsWork) {
			$value = str_replace(array("'", '"'), '', $value); // blank out any quotes
			$value = filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_NO_ENCODE_QUOTES);
			$hyphenPos = strpos($extras, '-');
			if($hyphenPos !== false && $hyphenPos !== 0) {
				// if hyphen present, ensure it's first (per PCRE requirements)
				$extras = '-' . str_replace('-', '', $extras);
			}
			$chars = $extras . 'a-zA-Z0-9';
			$value = preg_replace('{[^' . $chars . ']}', $replacementChar, $value);
		}

		// remove leading or trailing dashes, underscores, dots
		if($beautify) {
			if(strpos($extras, $replacementChar) === false) $extras .= $replacementChar;
			$value = trim($value, $extras);
		}

		return $value; 
	}

	/**
	 * Sanitize in "name" format (ASCII alphanumeric letters/digits, hyphens, underscores, periods)
	 * 
	 * Default behavior: 
	 *
	 * - Allows both upper and lowercase ASCII letters. 
	 * - Limits maximum length to 128 characters.  
	 * - Replaces non-name format characters with underscore "_". 
	 * 
	 * ~~~~~
	 * $test = "Foo+Bar Baz-123"
	 * echo $sanitizer->name($test); // outputs: Foo_Bar_Baz-123
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value Value that you want to convert to name format. 
	 * @param bool|int $beautify Beautify the returned name?
	 *  - Beautify makes returned name prettier by getting rid of doubled punctuation, leading/trailing punctuation and such. 
	 *  - Should be TRUE when creating a resource using the name for the first time (default is FALSE). 
	 *  - You may also specify the constant `Sanitizer::translate` (or integer 2) for the this argument, which will make it 
	 *    translate letters based on name format settings in ProcessWire. 
	 * @param int $maxLength Maximum number of characters allowed in the name (default=128). 
	 * @param string $replacement Replacement character for invalid characters. Should be either "_", "-" or "." (default="_").
	 * @param array $options Extra options to replace default 'beautify' behaviors
	 *  - `allowAdjacentExtras` (bool): Whether to allow [-_.] characters next to each other (default=false).
	 *  - `allowDoubledReplacement` (bool): Whether to allow two of the same replacement chars [-_] next to each other (default=false).
	 *  - `allowedExtras (array): Specify extra allowed characters (default=`['-', '_', '.']`).
	 * @return string Sanitized value in name format
	 * @see Sanitizer::pageName()
	 * 
	 */
	public function name($value, $beautify = false, $maxLength = 128, $replacement = '_', $options = array()) {
	
		if(!empty($options['allowedExtras']) && is_array($options['allowedExtras'])) {
			$allowedExtras = $options['allowedExtras']; 
			$allowedExtrasStr = implode('', $allowedExtras); 
		} else {
			$allowedExtras = array('-', '_', '.');
			$allowedExtrasStr = '-_.';
		}
	
		$value = $this->nameFilter($value, $allowedExtras, $replacement, $beautify, $maxLength);
		
		if($beautify) {
			
			$hasExtras = false;
			foreach($allowedExtras as $c) {
				$hasExtras = strpos($value, $c) !== false;
				if($hasExtras) break;
			}
			
			if($hasExtras) {
				
				if(empty($options['allowAdjacentExtras'])) {
					// replace any of '-_.' next to each other with a single $replacement
					$value = preg_replace('/[' . $allowedExtrasStr . ']{2,}/', $replacement, $value);
				}

				if(empty($options['allowDoubledReplacement'])) {
					// replace double'd replacements
					$r = "$replacement$replacement";
					if(strpos($value, $r) !== false) $value = preg_replace('/' . $r . '+/', $replacement, $value);
				}
	
				// replace double dots
				if(strpos($value, '..') !== false) $value = preg_replace('/\.\.+/', '.', $value);
			}
			
			if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength); 
		}
		
		return $value; 
	}

	/**
	 * Sanitize a string or array containing multiple names
	 * 
	 * - Default behavior is to sanitize to ASCII alphanumeric and hyphen, underscore, and period.
	 * - If given a string, multiple names may be separated by a delimeter (which is a space by default). 
	 * - Return value will be of the same type as the given value (i.e. string or array). 
	 * 
	 * #pw-group-strings
	 * 
	 * @param string|array $value Value(s) to sanitize to name format.
	 * @param string $delimeter Character that delimits values, if $value is a string (default=" ").
	 * @param array $allowedExtras Additional characters that are allowed in the value (default=['-', '_', '.']).
	 * @param string $replacementChar Single character replacement value for invalid characters (default='_').
	 * @param bool $beautify Whether or not to beautify returned values (default=false). See Sanitizer::name() for beautify options.
	 * @return string|array Returns string if given a string for $value, returns array if given an array for $value.
	 *
	 */
	public function names($value, $delimeter = ' ', $allowedExtras = array('-', '_', '.'), $replacementChar = '_', $beautify = false) {
		$isArray = false;
		if(is_array($value)) {
			$isArray = true; 
			$value = implode(' ', $value); 
		}
		$replace = array(',', '|', '  ');
		if($delimeter != ' ' && !in_array($delimeter, $replace)) $replace[] = $delimeter; 
		$value = str_replace($replace, ' ', $value);
		$allowedExtras[] = ' ';
		$value = $this->nameFilter($value, $allowedExtras, $replacementChar, $beautify, 8192);
		if($delimeter != ' ') $value = str_replace(' ', $delimeter, $value); 
		if($isArray) $value = explode($delimeter, $value); 
		return $value;
	}


	/**
	 * Sanitizes a string to be consistent with PHP variable names (not including '$'). 
	 * 
	 * Allows upper and lowercase ASCII letters, digits and underscore. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $value String you want to sanitize
	 * @return string Sanitized string
	 *
	 */
	public function varName($value) {
		return $this->nameFilter($value, array('_'), '_'); 
	}

	/**
	 * Sanitize consistent with names used by ProcessWire fields and/or PHP variables
	 *
	 * - Allows upper and lowercase ASCII letters, digits and underscore. 
	 * - ProcessWire field names follow the same conventions as PHP variable names, though digits may lead. 
	 * - This method is the same as the varName() sanitizer except that it supports beautification and max length. 
	 * - Unlike other name formats, hyphen and period are excluded because they aren't allowed characters in PHP variables.
	 * 
	 * ~~~~~
	 * $test = "Hello world";
	 * echo $sanitizer->varName($test); // outputs: Hello_world
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value Value you want to sanitize 
	 * @param bool|int $beautify Should be true when using the name for a new field (default=false).
	 *  You may also specify constant `Sanitizer::translate` (or number 2) for the $beautify param, which will make it translate letters
	 *  based on the system page name translation settings. 
	 * @param int $maxLength Maximum number of characters allowed in the name (default=128).
	 * @return string Sanitized string
	 *
	 */
	public function fieldName($value, $beautify = false, $maxLength = 128) {
		return $this->nameFilter($value, array('_'), '_', $beautify, $maxLength); 
	}
	
	/**
	 * Name filter as used by ProcessWire Templates
	 * 
	 * #pw-internal
	 *
	 * @param string $value
	 * @param bool|int $beautify Should be true when creating a name for the first time. Default is false.
	 *	You may also specify Sanitizer::translate (or number 2) for the $beautify param, which will make it translate letters
	 *	based on the InputfieldPageName custom config settings.
	 * @param int $maxLength Maximum number of characters allowed in the name
	 * @return string
	 *
	 */
	public function templateName($value, $beautify = false, $maxLength = 128) {
		return $this->nameFilter($value, array('_', '-'), '-', $beautify, $maxLength);
	}

	/**
	 * Sanitize as a ProcessWire page name
	 *
	 * - Page names by default support lowercase ASCII letters, digits, underscore, hyphen and period. 
	 * 
	 * - Because page names are often generated from a UTF-8 title, UTF-8 to ASCII conversion will take place when `$beautify` is enabled.
	 * 
	 * - You may optionally omit the `$beautify` and/or `$maxLength` arguments and substitute the `$options` array instead.
	 * 
	 * - When substituted, the beautify and maxLength options can be specified in $options as well.
	 * 
	 * - If `$config->pageNameCharset` is "UTF8" then non-ASCII page names will be converted to punycode ("xn-") ASCII page names,
	 *   rather than converted, regardless of `$beautify` setting. 
	 * 
	 * ~~~~~
	 * $test = "Hello world!";
	 * echo $sanitizer->pageName($test, true); // outputs: hello-world
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 * #pw-group-pages
	 *
	 * @param string $value Value to sanitize as a page name
	 * @param bool|int|array $beautify This argument accepts a few different possible values (default=false): 
	 *  - `true` (boolean): Make it pretty. Use this when using a pageName for the first time. 
	 *  - `Sanitizer::translate` (constant): This will make it translate non-ASCII letters based on *InputfieldPageName* module config settings. 
	 *  - `$options` (array): You can optionally specify the $options array for this argument instead. 
	 * @param int|array $maxLength Maximum number of characters allowed in the name.
	 *  You may also specify the $options array for this argument instead. 
	 * @param array $options Array of options to modify default behavior. See Sanitizer::name() method for available options. 
	 * @return string
	 * @see Sanitizer::name()
	 *
	 */
	public function pageName($value, $beautify = false, $maxLength = 128, array $options = array()) {
	
		if(!strlen($value)) return '';
		
		$defaults = array(
			'charset' => $this->wire('config')->pageNameCharset
		);
		
		if(is_array($beautify)) {
			$options = array_merge($beautify, $options);
			$beautify = isset($options['beautify']) ? $options['beautify'] : false;
			$maxLength = isset($options['maxLength']) ? $options['maxLength'] : 128;
		} else if(is_array($maxLength)) {
			$options = array_merge($maxLength, $options);
			$maxLength = isset($options['maxLength']) ? $options['maxLength'] : 128;
		} else {
			$options = array_merge($defaults, $options);
		}
		
		if($options['charset'] !== 'UTF8' && is_int($beautify) && $beautify > self::translate) {
			// UTF8 beautify modes aren't available if $config->pageNameCharset is not UTF8
			if(in_array($beautify, array(self::toAscii, self::toUTF8, self::okUTF8))) {
				// if modes aren't supported, disable 
				$beautify = false;
			}
		}
		
		if($beautify === self::toAscii) {
			// convert UTF8 to ascii (IDN/punycode)
			$beautify = false;
			if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength);
			$_value = $value;
			
			if(!ctype_alnum($value)
				&& !ctype_alnum(str_replace(array('-', '_', '.'), '', $value)) 
				&& strpos($value, 'xn-') !== 0) {
				
				do {
					// encode value
					$value = $this->punyEncodeName($_value);
					// if result stayed within our allowed character limit, then good, we're done
					if(strlen($value) <= $maxLength) break;
					// continue loop until encoded value is equal or less than allowed max length
					$_value = substr($_value, 0, strlen($_value) - 1);
				} while(true);
				
				// if encode was necessary and successful, return with no further processing
				if(strpos($value, 'xn-') === 0) {
					return $value;
				} else {
					// can't be encoded, send to regular name sanitizer
					$value = $_value;
				}
			}
			
		} else if($beautify === self::toUTF8) {
			// convert ascii IDN/punycode to UTF8
			$beautify = self::okUTF8;
			if(strpos($value, 'xn-') === 0) {
				// found something to convert
				$value = $this->punyDecodeName($value);
				// now it will run through okUTF8
			}
		}
		
		if($beautify === self::okUTF8) {
			return $this->pageNameUTF8($value);
		}
		
		return strtolower($this->name($value, $beautify, $maxLength, '-', $options));
	}

	/**
	 * Name filter for ProcessWire Page names with transliteration
	 *
	 * This is the same as calling pageName with the `Sanitizer::translate` option for the `$beautify` argument.
	 * 
	 * #pw-group-strings
	 * #pw-group-pages
	 *
	 * @param string $value Value to sanitize
	 * @param int $maxLength Maximum number of characters allowed in the name
	 * @return string Sanitized value
	 *
	 */
	public function pageNameTranslate($value, $maxLength = 128) {
		return $this->pageName($value, self::translate, $maxLength);
	}

	/**
	 * Sanitize and allow for UTF-8 characters in page name
	 * 
	 * - If `$config->pageNameCharset` is not `UTF8` then this function just passes control to the regular page name sanitizer.
	 * - Allowed UTF-8 characters are determined from `$config->pageNameWhitelist`.
	 * - This method does not convert to or from UTF-8, it only sanitizes it against the whitelist. 
	 * - If given a value that has only ASCII characters, this will pass control to the regular page name sanitizer. 
	 * 
	 * #pw-group-strings
	 * #pw-group-pages
	 * 
	 * @param string $value Value to sanitize
	 * @return string Sanitized value
	 *
	 */
	public function pageNameUTF8($value) {
		
		if(!strlen($value)) return '';
		
		// if UTF8 module is not enabled then delegate this call to regular pageName sanitizer
		if($this->wire('config')->pageNameCharset != 'UTF8') return $this->pageName($value);
	
		// we don't allow UTF8 page names to be prefixed with "xn-"
		if(strpos($value, 'xn-') === 0) $value = substr($value, 3);

		// word separators that we always allow 
		$separators = array('.', '-', '_'); 
	
		// we let regular pageName handle chars like these, if they appear without other UTF-8
		$extras = array('.', '-', '_', ' ', ',', ';', ':', '(', ')', '!', '?', '&', '%', '$', '#', '@');

		// proceed only if value has some non-ascii characters
		if(ctype_alnum(str_replace($extras, '', $value))) return $this->pageName($value);

		// validate that all characters are in our whitelist
		$whitelist = $this->wire('config')->pageNameWhitelist;
		if(!strlen($whitelist)) $whitelist = false;
		$blacklist = '/\\%"\'<>?#@:;,+=*^$()[]{}|&';
		$replacements = array();

		for($n = 0; $n < mb_strlen($value); $n++) {
			$c = mb_substr($value, $n, 1);
			if(!strlen(trim($c)) || ctype_cntrl($c)) {
				// character does not resolve to something visible
				$replacements[] = $c;
			} else if(mb_strpos($blacklist, $c) !== false || strpos($blacklist, $c) !== false) {
				// character that is in blacklist
				$replacements[] = $c;
			} else if($whitelist !== false && mb_strpos($whitelist, $c) === false) {
				// character that is not in whitelist, double check case variants
				$cLower = mb_strtolower($c);
				$cUpper = mb_strtoupper($c);
				if($cLower !== $c && mb_strpos($whitelist, $cLower) !== false) {
					// allow character and convert to lowercase variant
					$value = mb_substr($value, 0, $n) . $cLower . mb_substr($value, $n+1);
				} else if($cUpper !== $c && mb_strpos($whitelist, $cUpper) !== false) {
					// allow character and convert to uppercase varient
					$value = mb_substr($value, 0, $n) . $cUpper . mb_substr($value, $n+1);
				} else {
					// queue character to be replaced
					$replacements[] = $c;
				}
			}
		}

		// replace disallowed characters with "-"
		if(count($replacements)) $value = str_replace($replacements, '-', $value);
		
		// replace doubled word separators
		foreach($separators as $c) {
			while(strpos($value, "$c$c") !== false) {
				$value = str_replace("$c$c", $c, $value);
			}
		}

		// trim off any remaining separators/extras
		$value = trim($value, '-_.');
		
		return $value;
	}

	/**
	 * Decode a PW-punycode'd name value
	 * 
	 * @param string $value
	 * @return string
	 * 
	 */
	protected function punyDecodeName($value) {
		// exclude values that we know can't be converted
		if(strlen($value) < 4 || strpos($value, 'xn-') !== 0) return $value;
		
		if(strpos($value, '__')) {
			$_value = $value;
			$parts = explode('__', $_value);
			foreach($parts as $n => $part) {
				$parts[$n] = $this->punyDecodeName($part);
			}
			$value = implode('', $parts);
			return $value; 
		}
		
		$_value = $value; 
		// convert "xn-" single hyphen to recognized punycode "xn--" double hyphen
		if(strpos($value, 'xn--') !== 0) $value = 'xn--' . substr($value, 3);
		if(function_exists('idn_to_utf8')) {
			// use native php function if available
			$value = idn_to_utf8($value);
		} else {
			// otherwise use Punycode class
			$pc = new Punycode();
			$value = $pc->decode($value);
		}
		// if utf8 conversion failed, restore original value
		if($value === false || !strlen($value)) $value = $_value;
		return $value;
	}

	/**
	 * Encode a name value to PW-punycode
	 * 
	 * @param string $value
	 * @return string
	 * 
	 */
	protected function punyEncodeName($value) {
		// exclude values that don't need to be converted
		if(strpos($value, 'xn-') === 0) return $value;
		if(ctype_alnum(str_replace(array('.', '-', '_'), '', $value))) return $value;

		while(strpos($value, '__') !== false) {
			$value = str_replace('__', '_', $value);
		}
		
		if(strlen($value) >= 50) {
			$_value = $value;
			$parts = array();
			while(strlen($_value)) {
				$part = mb_substr($_value, 0, 12);
				$_value = mb_substr($_value, 12);
				$parts[] = $this->punyEncodeName($part);
			}
			$value = implode('__', $parts);
			return $value; 
		}
		
		$_value = $value;
		
		if(function_exists("idn_to_ascii")) {
			// use native php function if available
			$value = substr(idn_to_ascii($value), 3);
		} else {
			// otherwise use Punycode class
			$pc = new Punycode();
			$value = substr($pc->encode($value), 3);
		}
		if(strlen($value) && $value !== '-') {
			// in PW the xn- prefix has one fewer hyphen than in native Punycode
			// for compatibility with pageName sanitization and beautification
			$value = "xn-$value";
		} else {
			// fallback to regular 'name' sanitization on failure, ensuring that
			// return value is always ascii
			$value = $this->name($_value);
		}
		return $value;
	}

	/**
	 * Format required by ProcessWire user names
	 * 
	 * #pw-internal
	 *
	 * @deprecated, use pageName instead.
	 * @param string $value
	 * @return string
	 *
	 */
	public function username($value) {
		return $this->pageName($value); 
	}

	/**
	 * Name filter for ProcessWire filenames (basenames only, not paths)
	 * 
	 * This sanitizes a filename to be consistent with the name format in ProcessWire, 
	 * ASCII-alphanumeric, hyphens, underscores and periods.
	 * 
	 * #pw-group-strings
	 * #pw-group-files
	 *
	 * @param string $value Filename to sanitize
	 * @param bool|int $beautify Should be true when creating a file's name for the first time. Default is false.
	 *  You may also specify Sanitizer::translate (or number 2) for the $beautify param, which will make it translate letters
	 *  based on the InputfieldPageName custom config settings.
	 * @param int $maxLength Maximum number of characters allowed in the filename
	 * @return string Sanitized filename
	 *
	 */
	public function filename($value, $beautify = false, $maxLength = 128) {

		if(!is_string($value)) return '';
		$value = basename($value); 
		
		if(strlen($value) > $maxLength) {
			// truncate, while keeping extension in tact
			$pathinfo = pathinfo($value);
			$extLen = strlen($pathinfo['extension']) + 1; // +1 includes period
			$basename = substr($pathinfo['filename'], 0, $maxLength - $extLen);
			$value = "$basename.$pathinfo[extension]";
		}
		
		return $this->name($value, $beautify, $maxLength, '_', array(
			'allowAdjacentExtras' => true, // language translation filenames require doubled "--" chars, others may too
			)
		); 
	}

	/**
	 * Hookable alias of filename method for case consistency with other name methods (preferable to use filename)
	 * 
	 * #pw-internal
	 * 
	 * @param string $value
	 * @param bool|int $beautify Should be true when creating a file's name for the first time. Default is false.
	 *	You may also specify Sanitizer::translate (or number 2) for the $beautify param, which will make it translate letters
	 *	based on the InputfieldPageName custom config settings.
	 * @param int $maxLength Maximum number of characters allowed in the name
	 * @return string
	 *
	 */
	public function ___fileName($value, $beautify = false, $maxLength = 128) {
		return $this->filename($value, $beautify, $maxLength); 
	}

	/**
	 * Validate the given path, return path if valid, or false if not valid
	 * 
	 * Returns the given path if valid, or boolean false if not.
	 *  
	 * Path is validated per ProcessWire "name" convention of ascii only [-_./a-z0-9]
	 * As a result, this function is primarily useful for validating ProcessWire paths,
	 * and won't always work with paths outside ProcessWire.
	 *   
	 * This method validates only and does not sanitize. See `$sanitizer->pagePathName()` for a similar
	 * method that does sanitiation. 
	 * 
	 * #pw-group-strings
	 * #pw-group-pages
	 * 
	 * @param string $value Path to validate
	 * @param int|array $options Options to modify behavior, or maxLength (int) may be specified.
	 *  - `allowDotDot` (bool): Whether to allow ".." in a path (default=false)
	 *  - `maxLength` (int): Maximum length of allowed path (default=1024)
	 * @return bool|string Returns false if invalid, actual path (string) if valid.
	 * @see Sanitizer::pagePathName()
	 *
	 */
	public function path($value, $options = array()) {
		if(!is_string($value)) return false;
		if(is_int($options)) $options = array('maxLength' => $options); 
		$defaults = array(
			'allowDotDot' => false,
			'maxLength' => 1024
		);
		$options = array_merge($defaults, $options);
		if(DIRECTORY_SEPARATOR != '/') $value = str_replace(DIRECTORY_SEPARATOR, '/', $value); 
		if(strlen($value) > $options['maxLength']) return false;
		if(strpos($value, '/./') !== false || strpos($value, '//') !== false) return false;
		if(!$options['allowDotDot'] && strpos($value, '..') !== false) return false;
		if(!preg_match('{^[-_./a-z0-9]+$}iD', $value)) return false;
		return $value;
	}

	/**
	 * Sanitize a page path name
	 * 
	 * Returned path is not guaranteed to be valid or match a page, just sanitized. 
	 * 
	 * #pw-group-strings
	 * #pw-group-pages
	 *
	 * @param string $value Value to sanitize
	 * @param bool $beautify Beautify the value? (default=false)
	 * @param int $maxLength Maximum length (default=1024)
	 * @return string Sanitized path name
	 *
	 */
	public function pagePathName($value, $beautify = false, $maxLength = 1024) {
	
		$extras = array('/', '-', '_', '.');
		$options = array('allowedExtras' => $extras);
		$charset = $this->wire('config')->pageNameCharset;
	
		if($charset === 'UTF8' && $beautify === self::toAscii) {
			// convert UTF8 to punycode when applicable
			if(!ctype_alnum(str_replace($extras, '', $value))) {
				$parts = explode('/', $value);
				foreach($parts as $n => $part) {
					if(!strlen($part) || ctype_alnum($part)) continue;
					if(!ctype_alnum(str_replace($extras, '', $part))) {
						$parts[$n] = $this->pageName($part, self::toAscii);
					}
				}
				$value = implode('/', $parts);
			}
		}
		
		if($charset === 'UTF8' && $beautify === self::okUTF8) {
			$value = $this->pagePathNameUTF8($value);
		} else {
			if(in_array($beautify, array(self::okUTF8, self::toUTF8, self::toAscii))) $beautify = false;
			// regular ascii path
			$value = $this->name($value, $beautify, $maxLength, '-', $options);
		}
		
		// disallow double slashes
		while(strpos($value, '//') !== false) $value = str_replace('//', '/', $value); 
		
		// disallow relative paths
		while(strpos($value, '..') !== false) $value = str_replace('..', '.', $value);
		
		// disallow names that start with a period
		while(strpos($value, '/.') !== false) $value = str_replace('/.', '/', $value);
	
		// ascii to UTF8 conversion, when requested
		if($charset === 'UTF8' && $beautify === self::toUTF8) {
			if(strpos($value, 'xn-') === false) return $value;
			$parts = explode('/', $value);
			foreach($parts as $n => $part) {
				if(strpos($part, 'xn-') !== 0) continue;
				$parts[$n] = $this->pageName($part, self::toUTF8);
			}
			$value = implode('/', $parts);
			$value = $this->pagePathNameUTF8($value);
		}

		return $value; 
	}

	/**
	 * Sanitize a UTF-8 page path name (does not perform ASCII/UTF8 conversions)
	 * 
	 * - If `$config->pageNameCharset` is not `UTF8` then this does the same thing as `$sanitizer->pagePathName()`.
	 * - Returned path is not guaranteed to be valid or match a page, just sanitized.
	 * 
	 * #pw-group-strings
	 * #pw-group-pages
	 * 
	 * @param string $value Path name to sanitize
	 * @return string
	 * @see Sanitizer::pagePathName()
	 * 
	 */
	public function pagePathNameUTF8($value) {
		if($this->wire('config')->pageNameCharset !== 'UTF8') return $this->pagePathName($value);
		$parts = explode('/', $value);
		foreach($parts as $n => $part) {
			$parts[$n] = $this->pageName($part, self::okUTF8);
		}
		$value = implode('/', $parts);
		$disallow = array('..', '/.', '//');
		foreach($disallow as $x) {
			while(strpos($value, $x) !== false) {
				$value = str_replace($x, '', $value);
			}
		}
		return $value; 
	}

	/**
	 * Sanitize to ASCII alpha (a-z A-Z)
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value Value to sanitize
	 * @param bool|int $beautify Whether to beautify (See Sanitizer::translate option too)
	 * @param int $maxLength Maximum length of returned value (default=1024)
	 * @return string
	 * 
	 */
	public function alpha($value, $beautify = false, $maxLength = 1024) {
		$value = $this->alphanumeric($value, $beautify, 8192);
		$numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
		$value = str_replace($numbers, '', $value);
		if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength);
		return $value;
	}

	/**
	 * Sanitize to ASCII alphanumeric (a-z A-Z 0-9)
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value Value to sanitize
	 * @param bool|int $beautify Whether to beautify (See Sanitizer::translate option too)
	 * @param int $maxLength Maximum length of returned value (default=1024)
	 * @return string
	 *
	 */
	public function alphanumeric($value, $beautify = false, $maxLength = 1024) {
		$value = $this->nameFilter($value, array('_'), '_', $beautify, $maxLength * 10);
		$value = str_replace('_', '', $value);
		if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength);
		return $value;
	}

	/**
	 * Sanitize string to contain only ASCII digits (0-9)
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value Value to sanitize
	 * @param int $maxLength Maximum length of returned value (default=1024)
	 * @return string
	 *
	 */
	public function digits($value, $maxLength = 1024) {
		$letters = str_split('_abcdefghijklmnopqrstuvwxyz');
		$value = strtolower($this->nameFilter($value, array('_'), '_', false, $maxLength * 10));
		$value = str_replace($letters, '', $value);
		if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength);
		return $value; 
	}

	/**
	 * Sanitize and validate an email address
	 * 
	 * Returns valid email address, or blank string if it isn't valid.
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value Email address to sanitize and validate.
	 * @return string Sanitized, valid email address, or blank string on failure. 
	 * 
	 */ 
	public function email($value) {
		$value = filter_var($value, FILTER_SANITIZE_EMAIL); 
		if(filter_var($value, FILTER_VALIDATE_EMAIL)) return $value;
		return '';
	}

	/**
	 * Returns a value that may be used in an email header
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value
	 * @return string
	 *
	 */ 
	public function emailHeader($value) {
		if(!is_string($value)) return '';
		$a = array("\n", "\r", "<CR>", "<LF>", "0x0A", "0x0D", "%0A", "%0D", 'content-type:', 'bcc:', 'cc:', 'to:', 'reply-to:'); 
		return trim(str_ireplace($a, ' ', $value));
	}

	/**
	 * Sanitize short string of text to single line without HTML
	 * 
	 * - This sanitizer is useful for short strings of input text like like first and last names, street names, search queries, etc.
	 * 
	 * - Please note the default 255 character max length setting. 
	 * 
	 * - If using returned value for front-end output, be sure to run it through `$sanitizer->entities()` first. 
	 * 
	 * ~~~~~
	 * $str = "
	 *   <strong>Hello World</strong>
	 *   How are you doing today?
	 * ";
	 * 
	 * echo $sanitizer->text($str);
	 * // outputs: Hello World How are you doing today?
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value String value to sanitize
	 * @param array $options Options to modify default behavior:
	 * - `maxLength` (int): maximum characters allowed, or 0=no max (default=255).
	 * - `maxBytes` (int): maximum bytes allowed (default=0, which implies maxLength*4).
	 * - `stripTags` (bool): strip markup tags? (default=true).
	 * - `allowableTags` (string): markup tags that are allowed, if stripTags is true (use same format as for PHP's `strip_tags()` function.
	 * - `multiLine` (bool): allow multiple lines? if false, then $newlineReplacement below is applicable (default=false).
	 * - `newlineReplacement` (string): character to replace newlines with, OR specify boolean TRUE to remove extra lines (default=" ").
	 * - `inCharset` (string): input character set (default="UTF-8").
	 * - `outCharset` (string): output character set (default="UTF-8").
	 * @return string
	 * @see Sanitizer::textarea()
	 *
	 */
	public function text($value, $options = array()) {

		$defaultOptions = array(
			'maxLength' => 255, // maximum characters allowed, or 0=no max
			'maxBytes' => 0,  // maximum bytes allowed (0 = default, which is maxLength*4)
			'stripTags' => true, // strip markup tags
			'allowableTags' => '', // tags that are allowed, if stripTags is true (use same format as for PHP's strip_tags function)
			'multiLine' => false, // allow multiple lines? if false, then $newlineReplacement below is applicable
			'newlineReplacement' => ' ', // character to replace newlines with, OR specify boolean TRUE to remove extra lines
			'inCharset' => 'UTF-8', // input charset
			'outCharset' => 'UTF-8',  // output charset
			);

		$options = array_merge($defaultOptions, $options); 
		
		if(!is_string($value)) $value = $this->string($value);

		if(!$options['multiLine']) {
			if(strpos($value, "\r") !== false) {
				$value = str_replace("\r", "\n", $value); // normalize to LF
			}
			$pos = strpos($value, "\n"); 
			if($pos !== false) {
				if($options['newlineReplacement'] === true) {
					// remove extra lines
					$value = rtrim(substr($value, 0, $pos));
				} else {
					// remove linefeeds
					$value = str_replace(array("\n\n", "\n"), $options['newlineReplacement'], $value);
				}
			}
		}

		if($options['stripTags']) $value = strip_tags($value, $options['allowableTags']); 

		if($options['inCharset'] != $options['outCharset']) $value = iconv($options['inCharset'], $options['outCharset'], $value); 

		if($options['maxLength']) {
			if(empty($options['maxBytes'])) $options['maxBytes'] = $options['maxLength'] * 4;
			if($this->multibyteSupport) {
				if(mb_strlen($value, $options['outCharset']) > $options['maxLength']) {
					$value = mb_substr($value, 0, $options['maxLength'], $options['outCharset']);
				}
			} else {
				if(strlen($value) > $options['maxLength']) {
					$value = substr($value, 0, $options['maxLength']);
				}
			}
		}

		if($options['maxBytes']) {
			$n = $options['maxBytes'];
			while(strlen($value) > $options['maxBytes']) {
				$n--;
				if($this->multibyteSupport) {
					$value = mb_substr($value, 0, $n, $options['outCharset']);
				} else {
					$value = substr($value, 0, $n);
				}
			}
		}

		return trim($value); 	
	}

	/**
	 * Sanitize input string as multi-line text without no HTML tags
	 * 
	 * - This sanitizer is useful for user-submitted text from a plain-text `<textarea>` field, 
	 *   or any other kind of string value that might have multiple-lines.
	 * 
	 * - Don't use this sanitizer for values where you want to allow HTML (like rich text fields). 
	 *   For those values you should instead use the `$sanitizer->purify()` method. 
	 * 
	 * - If using returned value for front-end output, be sure to run it through `$sanitizer->entities()` first.
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value String value to sanitize
	 * @param array $options Options to modify default behavior
	 *  - `maxLength` (int): maximum characters allowed, or 0=no max (default=16384 or 16kb).
	 *  - `maxBytes` (int): maximum bytes allowed (default=0, which implies maxLength*3 or 48kb).
	 *  - `stripTags` (bool): strip markup tags? (default=true).
	 *  - `allowableTags` (string): markup tags that are allowed, if stripTags is true (use same format as for PHP's `strip_tags()` function.
	 *  - `allowCRLF` (bool): allow CR+LF newlines (i.e. "\r\n")? (default=false, which means "\r\n" is replaced with "\n"). 
	 *  - `inCharset` (string): input character set (default="UTF-8").
	 *  - `outCharset` (string): output character set (default="UTF-8").
	 * @return string
	 * @see Sanitizer::text(), Sanitizer::purify()
	 * 
	 *
	 */
	public function textarea($value, $options = array()) {
		
		if(!is_string($value)) $value = $this->string($value);

		if(!isset($options['multiLine'])) $options['multiLine'] = true; 	
		if(!isset($options['maxLength'])) $options['maxLength'] = 16384; 
		if(!isset($options['maxBytes'])) $options['maxBytes'] = $options['maxLength'] * 3; 
	
		// convert \r\n to just \n
		if(empty($options['allowCRLF']) && strpos($value, "\r\n") !== false) $value = str_replace("\r\n", "\n", $value); 

		return $this->text($value, $options); 
	}

	/**
	 * Convert a string containing markup or entities to be plain text
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value String you want to convert
	 * @param array $options Options to modify default behavior: 
	 *   - `newline` (string): Character(s) to replace newlines with (default="\n").
	 *   - `separator` (string): Character(s) to separate HTML <li> items with (default="\n").
	 *   - `entities` (bool): Entity encode returned value? (default=false). 
	 *   - `trim` (string): Character(s) to trim from beginning and end of value (default=" -,:;|\n\t").
	 * @return string Converted string of text
	 * 
	 */	
	public function markupToText($value, array $options = array()) {

		$defaults = array(
			'newline' => "\n", // character(s) to replace newlines with
			'separator' => "\n", // character(s) to separate list items with
			'entities' => false,
			'trim' => " -,:;|\n\t ", // character(s) to trim from beginning and end
		);

		$options = array_merge($defaults, $options);
		$newline = $options['newline'];

		if(strpos($value, "\r") !== false) {
			// normalize newlines
			$value = str_replace(array("\r\n", "\r"), "\n", $value);
		}

		// remove entities
		$value = $this->wire('sanitizer')->unentities($value);

		if(strpos($value, '<') !== false) {
			// tag replacements before strip_tags()
			$regex =
				'!<(?:' .
					'/?(?:ul|ol|p|h\d|div)(?:>|\s[^><]*)' .
					'|' . 
					'(?:br[\s/]*)' .
				')>!is';
			$value = preg_replace($regex, $newline, $value);
			if(stripos($value, '</li>')) {
				$value = preg_replace('!</li>\s*<li!is', "$options[separator]<li", $value);
			}
		}
	
		// remove tags
		$value = trim(strip_tags($value));

		if($newline != "\n") {
			// if newline is not "\n", don't allow them to be repeated together
			$value = str_replace("\n", $newline, $value);
			$test = "$newline$newline";
			$repl = "$newline";
		} else {
			// if newline is whitespace (i.e. "\n") then only allow max of 2 together
			$test = "$newline$newline$newline";
			$repl = "$newline$newline";
		}

		while(strpos($value, $test) !== false) {
			// limit quantity of newlines
			$value = str_replace($test, $repl, $value);
		}
	
		// entity-encode text value, if requested
		if($options['entities']) {
			$value = $this->entities($value);
			$options['trim'] = str_replace(';', '', $options['trim']);
		}
	
		// trim characters from beginning and end
		$_value = trim($value, $options['trim'] . $options['newline']);
		if(strlen($_value)) $value = $_value;
		
		return $value;
	}

	/**
	 * Convert a string containing markup or entities to be a single line of plain text
	 * 
	 * This is the same as the `$sanitizer->markupToText()` method except that the return 
	 * value is always just a single line. 
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value Value to convert
	 * @param array $options Options to modify default behavior:
	 *   - `newline` (string): Character(s) to replace newlines with (default=" ").
	 *   - `separator` (string): Character(s) to separate HTML <li> items with (default=", ").
	 *   - `entities` (bool): Entity encode returned value? (default=false).
	 *   - `trim` (string): Character(s) to trim from beginning and end of value (default=" -,:;|\n\t").
	 * @return string Converted string of text on a single line
	 * 
	 */
	public function markupToLine($value, array $options = array()) {
		if(!isset($options['newline'])) $options['newline'] = $options['newline'] = " "; 
		if(!isset($options['separator'])) $options['separator'] = ", ";
		return $this->markupToText($value, $options);
	}

	/**
	 * Sanitize and validate given URL or return blank if it can’t be made valid
	 *
	 * - Performs some basic sanitization like adding a scheme to the front if it's missing, but leaves alone local/relative URLs.
	 * - URL is not required to conform to ProcessWire conventions unless a relative path is given.
	 * - Please note that URLs should always be entity encoded in your output. Many evil things are technically allowed in a valid URL, 
	 *   so your output should always entity encoded any URLs that came from user input.
	 * 
	 * ~~~~~~
	 * $url = $sanitizer->url('processwire.com/api/'); 
	 * echo $sanitizer->entities($url); // outputs: http://processwire.com/api/
	 * ~~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value URL to validate
	 * @param bool|array $options Array of options to modify default behavior, including:
	 *  - `allowRelative` (boolean): Whether to allow relative URLs, i.e. those without domains (default=true).
	 *  - `allowIDN` (boolean): Whether to allow internationalized domain names (default=false).
	 *  - `allowQuerystring` (boolean): Whether to allow query strings (default=true).
	 *  - `allowSchemes` (array): Array of allowed schemes, lowercase (default=[] any).
	 *  - `disallowSchemes` (array): Array of disallowed schemes, lowercase (default=['file']).
	 *  - `requireScheme` (bool): Specify true to require a scheme in the URL, if one not present, it will be added to non-relative URLs (default=true).
	 *  - `stripTags` (bool): Specify false to prevent tags from being stripped (default=true).
	 *  - `stripQuotes` (bool): Specify false to prevent quotes from being stripped (default=true).
	 *  - `maxLength` (int): Maximum length in bytes allowed for URLs (default=4096).
	 *  - `throw` (bool): Throw exceptions on invalid URLs (default=false).
	 * @return string Returns a valid URL or blank string if it can't be made valid.
	 * @throws WireException on invalid URLs, only if `$options['throw']` is true.
	 *
	 */
	public function url($value, $options = array()) {
		// Previously the $options argument was the boolean $allowRelative, and that usage will still work for backwards compatibility.

		$defaultOptions = array(
			'allowRelative' => true,
			'allowIDN' => false,
			'allowQuerystring' => true,
			'allowSchemes' => array(),
			'disallowSchemes' => array('file', 'javascript'),
			'requireScheme' => true,
			'stripTags' => true,
			'stripQuotes' => true,
			'maxLength' => 4096,
			'throw' => false,
		);

		if(!is_array($options)) {
			$defaultOptions['allowRelative'] = (bool) $options; // backwards compatibility with old API
			$options = array();
		}

		$options = array_merge($defaultOptions, $options);
		$textOptions = array(
			'stripTags' => $options['stripTags'],
			'maxLength' => $options['maxLength'],
			'newlineReplacement' => true,
		);

		$value = $this->text($value, $textOptions);
		if(!strlen($value)) return '';

		$scheme = parse_url($value, PHP_URL_SCHEME);
		if($scheme !== false && strlen($scheme)) {
			$_scheme = $scheme;
			$scheme = strtolower($scheme);
			$schemeError = false;
			if(!empty($options['allowSchemes']) && !in_array($scheme, $options['allowSchemes'])) $schemeError = true;
			if(!empty($options['disallowSchemes']) && in_array($scheme, $options['disallowSchemes'])) $schemeError = true;
			if($schemeError) {
				$error = sprintf($this->_('URL: Scheme "%s" is not allowed'), $scheme);
				if($options['throw']) throw new WireException($error);
				$this->error($error);
				$value = str_ireplace(array("$scheme:///", "$scheme://"), '', $value);
			} else if($_scheme !== $scheme) {
				$value = str_replace("$_scheme://", "$scheme://", $value); // lowercase scheme
			}
		}

		// separate scheme+domain+path from query string temporarily
		if(strpos($value, '?') !== false) {
			list($domainPath, $queryString) = explode('?', $value);
			if(!$options['allowQuerystring']) $queryString = '';
		} else {
			$domainPath = $value;
			$queryString = '';
		}

		$pathIsEncoded = strpos($domainPath, '%') !== false;
		if($pathIsEncoded || filter_var($domainPath, FILTER_SANITIZE_URL) !== $domainPath) {
			// the domain and/or path contains extended characters not supported by FILTER_SANITIZE_URL
			// Example: https://de.wikipedia.org/wiki/Linkshänder
			// OR it is already rawurlencode()'d
			// Example: https://de.wikipedia.org/wiki/Linksh%C3%A4nder
			// we convert the URL to be FILTER_SANITIZE_URL compatible
			// if already encoded, first remove encoding: 
			if(strpos($domainPath, '%') !== false) $domainPath = rawurldecode($domainPath);
			// Next, encode it, for example: https%3A%2F%2Fde.wikipedia.org%2Fwiki%2FLinksh%C3%A4nder
			$domainPath = rawurlencode($domainPath);
			// restore characters allowed in domain/path
			$domainPath = str_replace(array('%2F', '%3A'), array('/', ':'), $domainPath);
			// restore value that is now FILTER_SANITIZE_URL compatible
			$value = $domainPath . (strlen($queryString) ? "?$queryString" : "");
			$pathIsEncoded = true;
		}

		// this filter_var sanitizer just removes invalid characters that don't appear in domains or paths
		$value = filter_var($value, FILTER_SANITIZE_URL);

		if(!$scheme) {
			// URL is missing scheme/protocol, or is local/relative

			if(strpos($value, '://') !== false) {
				// apparently there is an attempted, but unrecognized scheme, so remove it
				$value = preg_replace('!^[^?]*?://!', '', $value);
			}

			if($options['allowRelative']) {
				// determine if this is a domain name 
				// regex legend:       (www.)?      company.         com       ( .uk or / or end)
				$dotPos = strpos($value, '.');
				$slashPos = strpos($value, '/');
				if($slashPos === false) $slashPos = $dotPos+1;
				// if the first slash comes after the first dot, the dot is likely part of a domain.com/path/
				// if the first slash comes before the first dot, then it's likely a /path/product.html
				if($dotPos && $slashPos > $dotPos && preg_match('{^([^\s_.]+\.)?[^-_\s.][^\s_.]+\.([a-z]{2,6})([./:#]|$)}i', $value, $matches)) {
					// most likely a domain name
					// $tld = $matches[3]; // TODO add TLD validation to confirm it's a domain name
					$value = $this->filterValidateURL("http://$value", $options); // add scheme for validation

				} else if($options['allowQuerystring']) {
					// we'll construct a fake domain so we can use FILTER_VALIDATE_URL rules
					$fake = 'http://processwire.com/';
					$slash = strpos($value, '/') === 0 ? '/' : '';
					$value = $fake . ltrim($value, '/');
					$value = $this->filterValidateURL($value, $options);
					$value = str_replace($fake, $slash, $value);

				} else {
					// most likely a relative path
					$value = $this->path($value);
				}

			} else {
				// relative urls aren't allowed, so add the scheme/protocol and validate
				$value = $this->filterValidateURL("http://$value", $options);
			}

			if(!$options['requireScheme']) {
				// if a scheme was added above (for filter_var validation) and it's not required, remove it
				$value = str_replace('http://', '', $value);
			}
		} else if($scheme == 'tel') {
			// tel: scheme is not supported by filter_var 
			if(!preg_match('/^tel:\+?\d+$/', $value)) {
				$value = str_replace(' ', '', $value);
				/** @noinspection PhpUnusedLocalVariableInspection */
				list($tel, $num) = explode(':', $value);
				$value = 'tel:';
				if(strpos($num, '+') === 0) $value .= '+';
				$value .= preg_replace('/[^\d]/', '', $num);
			}
		} else {
			// URL already has a scheme
			$value = $this->filterValidateURL($value, $options);
		}

		if($pathIsEncoded && strlen($value)) {
			// restore to non-encoded, UTF-8 version 
			if(strpos('?', $value) !== false) {
				list($domainPath, $queryString) = explode('?', $value);
			} else {
				$domainPath = $value;
				$queryString = '';
			}
			$domainPath = rawurldecode($domainPath);
			if(strpos($domainPath, '%') !== false) {
				$domainPath = preg_replace('/%[0-9ABCDEF]{1,2}/i', '', $domainPath);
				$domainPath = str_replace('%', '', $domainPath);
			}
			$domainPath = $this->text($domainPath, $textOptions);
			$value = $domainPath . (strlen($queryString) ? "?$queryString" : "");
		}

		if(strlen($value)) {
			if($options['stripTags']) {
				if(stripos($value, '%3') !== false) {
					$value = str_ireplace(array('%3C', '%3E'), array('!~!<', '>!~!'), $value);
					$value = strip_tags($value);
					$value = str_ireplace(array('!~!<', '>!~!', '!~!'), array('%3C', '%3E', ''), $value); // restore, in case valid/non-tag
				} else {
					$value = strip_tags($value);
				}
			}
			if($options['stripQuotes']) {
				$value = str_replace(array('"', "'", "%22", "%27"), '', $value);
			}
			return $value;
		}

		return '';
	}

	/**
	 * Implementation of PHP's FILTER_VALIDATE_URL with IDN support (will convert to valid)
	 *
	 * Example: http://трикотаж-леко.рф
	 *
	 * @param string $url
	 * @param array $options Specify ('allowIDN' => false) to disallow internationalized domain names
	 * @return string
	 *
	 */
	protected function filterValidateURL($url, array $options) {

		$_url = $url;
		$url = filter_var($url, FILTER_VALIDATE_URL);
		if($url !== false && strlen($url)) return $url;

		// if allowIDN was specifically set false, don't proceed further
		if(isset($options['allowIDN']) && !$options['allowIDN']) return $url;

		// extract scheme
		if(strpos($_url, '//') !== false) {
			list($scheme, $_url) = explode('//', $_url, 2);
			$scheme .= '//';
		} else {
			$scheme = '';
		}

		// extract domain, and everything else (rest)
		if(strpos($_url, '/') > 0) {
			list($domain, $rest) = explode('/', $_url, 2);
			$rest = "/$rest";
		} else {
			$domain = $_url;
			$rest = '';
		}

		if(strpos($domain, '%') !== false) {
			// domain is URL encoded
			$domain = rawurldecode($domain);
		}

		// extract port, if present, and prepend to $rest
		if(strpos($domain, ':') !== false && preg_match('/^([^:]+):(\d+)$/', $domain, $matches)) {
			$domain = $matches[1];
			$rest = ":$matches[2]$rest";
		}

		if($this->nameFilter($domain, array('-', '.'), '_', false, 1024) === $domain) {
			// domain contains no extended characters
			$url = $scheme . $domain . $rest;
			$url = filter_var($url, FILTER_VALIDATE_URL);

		} else {
			// domain contains utf8
			$pc = function_exists("idn_to_ascii") ? false : new Punycode();
			$domain = $pc ? $pc->encode($domain) : idn_to_ascii($domain);
			if($domain === false || !strlen($domain)) return '';
			$url = $scheme . $domain . $rest;
			$url = filter_var($url, FILTER_VALIDATE_URL);
			if(strlen($url)) {
				// convert back to utf8 domain
				$domain = $pc ? $pc->decode($domain) : idn_to_utf8($domain);
				if($domain === false) return '';
				$url = $scheme . $domain . $rest;
			}
		}

		return $url;
	}

	/**
	 * Field name filter as used by ProcessWire Fields
	 * 
	 * Note that dash and dot are excluded because they aren't allowed characters in PHP variables
	 * 
	 * #pw-internal
	 * 
	 * @param string $value
	 * @return string
	 *
	 */
	public function selectorField($value) {
		return $this->nameFilter($value, array('_'), '_'); 
	}


	/**
	 * Sanitizes a string value that needs to go in a ProcessWire selector
	 * 
	 * Always use this to sanitize any string values you are inserting in selector strings. 
	 * This ensures that the value can't be confused for another component of the selector string. 
	 * This method may remove characters, escape characters, or surround the string in quotes. 
	 * 
	 * ~~~~~
	 * // Sanitize text for a search on title and body fields
	 * $q = $input->get->text('q'); // text search query
	 * $results = $pages->find("title|body%=" . $sanitizer->selectorValue($q)); 
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value String value to sanitize (assumed to be UTF-8). 
	 * @param array|int $options Options to modify behavior: 
	 *   - `maxLength` (int): Maximum number of allowed characters (default=100). This may also be specified instead of $options array.
	 *   - `useQuotes` (bool): Allow selectorValue() function to add quotes if it deems them necessary? (default=true)
	 *   - If an integer is specified for $options, it is assumed to be the maxLength value. 
	 * @return string Value ready to be used as the value component in a selector string. 
	 * 
	 */
	public function selectorValue($value, $options = array()) {
		
		$defaults = array(
			'maxLength' => 100, 
			'useQuotes' => true, 
		);

		if(is_int($options)) {
			$options = array('maxLength' => $options);
		} else if(!is_array($options)) {
			$options = array();
		}
		$options = array_merge($defaults, $options);
		if(!is_string($value)) $value = $this->string($value);
		$value = trim($value); 
		$quoteChar = '"';
		$needsQuotes = false; 
		$maxLength = $options['maxLength'];

		if($options['useQuotes']) {
			// determine if value is already quoted and set initial value of needsQuotes
			// also pick out the initial quote style
			if(strlen($value) && ($value[0] == "'" || $value[0] == '"')) {
				$needsQuotes = true;
			}

			// trim off leading or trailing quotes
			$value = trim($value, "\"'");

			// if an apostrophe is present, value must be quoted
			if(strpos($value, "'") !== false) $needsQuotes = true;

			// if commas are present, then the selector needs to be quoted
			if(strpos($value, ',') !== false) $needsQuotes = true;

			// disallow double quotes -- remove any if they are present
			if(strpos($value, '"') !== false) $value = str_replace('"', '', $value);
		}

		// selector value is limited to 100 chars
		if(strlen($value) > $maxLength) {
			if($this->multibyteSupport) $value = mb_substr($value, 0, $maxLength, 'UTF-8'); 
				else $value = substr($value, 0, $maxLength); 
		}

		// disallow some characters in selector values
		// @todo technically we only need to disallow at begin/end of string
		$value = str_replace(array('*', '~', '`', '$', '^', '|', '<', '>', '=', '[', ']', '{', '}'), ' ', $value);
	
		// disallow greater/less than signs, unless they aren't forming a tag
		// if(strpos($value, '<') !== false) $value = preg_replace('/<[^>]+>/su', ' ', $value); 

		// more disallowed chars, these may not appear anywhere in selector value
		$value = str_replace(array("\r", "\n", "#", "%"), ' ', $value);

		// see if we can avoid the preg_matches and do a quick filter
		$test = str_replace(array(',', ' ', '-'), '', $value); 

		if(!ctype_alnum($test)) {
		
			// value needs more filtering, replace all non-alphanumeric, non-single-quote and space chars
			// See: http://php.net/manual/en/regexp.reference.unicode.php
			// See: http://www.regular-expressions.info/unicode.html
			$value = preg_replace('/[^[:alnum:]\pL\pN\pP\pM\p{S} \'\/]/u', ' ', $value); 

			// replace multiple space characters in sequence with just 1
			$value = preg_replace('/\s\s+/u', ' ', $value); 
		}

		$value = trim($value); // trim any kind of whitespace
		$value = trim($value, '+!,'); // chars to remove from begin and end 
		
		if(!$needsQuotes && $options['useQuotes'] && strlen($value)) {
			$a = substr($value, 0, 1); 
			$b = substr($value, -1); 
			if((!ctype_alnum($a) && $a != '/') || (!ctype_alnum($b) && $b != '/')) $needsQuotes = true;
		}
		if($needsQuotes) $value = $quoteChar . $value . $quoteChar; 
		return $value;

	}

	/**
	 * Entity encode a string for output
	 *
	 * Wrapper for PHP's `htmlentities()` function that contains typical ProcessWire usage defaults
	 *
	 * The arguments used here are identical to those for
	 * [PHP's htmlentities](http://www.php.net/manual/en/function.htmlentities.php) function,
	 * except that the ProcessWire defaults for encoding quotes and using UTF-8 are already populated. 
	 * 
	 * ~~~~~
	 * $test = "ain't <em>nothing</em> perfect but our brokenness";
	 * echo $sanitizer->entities($test); 
	 * // result: ain&apos;t &lt;em&gt;nothing&lt;/em&gt; perfect but our brokenness
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to entity encode
	 * @param int|bool $flags See PHP htmlentities() function for flags. 
	 * @param string $encoding Encoding of string (default="UTF-8").
	 * @param bool $doubleEncode Allow double encode? (default=true).
	 * @return string Entity encoded string
	 * @see Sanitizer::entities1(), Sanitizer::unentities()
	 *
	 */
	public function entities($str, $flags = ENT_QUOTES, $encoding = 'UTF-8', $doubleEncode = true) {
		if(!is_string($str)) $str = $this->string($str);
		return htmlentities($str, $flags, $encoding, $doubleEncode); 
	}
	
	/**
	 * Entity encode a string and don’t double encode it if already encoded
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to entity encode
	 * @param int|bool $flags See PHP htmlentities() function for flags.
	 * @param string $encoding Encoding of string (default="UTF-8").
	 * @return string Entity encoded string
	 * @see Sanitizer::entities(), Sanitizer::unentities()
	 * 
	 *
	 */
	public function entities1($str, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
		if(!is_string($str)) $str = $this->string($str);
		return htmlentities($str, $flags, $encoding, false);
	}
	
	/**
	 * Entity encode while translating some markdown tags to HTML equivalents
	 * 
	 * If you specify boolean TRUE for the `$options` argument, full markdown is applied. Otherwise,
	 * only basic markdown allowed, as outlined in the examples. 
	 * 
	 * The primary reason to use this over full-on Markdown is that it has less overhead
	 * and is faster then full-blown Markdown, for when you don't need it. It's also safer
	 * for text coming from user input since it doesn't allow any other HTML. But if you just
	 * want full markdown, then specify TRUE for the `$options` argument. 
	 * 
	 * Basic allowed markdown currently includes: 
	 * - `**strong**`
	 * - `*emphasis*`
	 * - `[anchor-text](url)`
	 * - `~~strikethrough~~`
	 * - code surrounded by backticks
	 * 
	 * ~~~~~
	 * // basic markdown
	 * echo $sanitizer->entitiesMarkdown($str); 
	 * 
	 * // full markdown
	 * echo $sanitizer->entitiesMarkdown($str, true); 
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to apply markdown to
	 * @param array|bool|int $options Options include the following, or specify boolean TRUE to apply full markdown.
	 *  - `fullMarkdown` (bool): Use full markdown rather than basic? (default=false) when true, most options no longer apply.
	 *    Note: A markdown flavor integer may also be supplied for the fullMarkdown option.
	 *  - `flags` (int): PHP htmlentities() flags. Default is ENT_QUOTES. 
	 *  - `encoding` (string): PHP encoding type. Default is 'UTF-8'. 
	 *  - `doubleEncode` (bool): Whether to double encode (if already encoded). Default is true. 
	 *  - `allow` (array): Only markdown that translates to these tags will be allowed. Default is most inline HTML tags. 
	 *  - `disallow` (array): Specified tags (in the default allow list) that won't be allowed. Default=[] empty array.
	 *    (Note: The 'disallow' is an alternative to the default 'allow'. No point in using them both.) 
	 *  - `linkMarkup` (string): Markup to use for links. Default=`<a href="{url}" rel="nofollow" target="_blank">{text}</a>`.
	 *  - `allowBrackets` (bool): Allow some inline-level bracket tags, i.e. `[span.detail]text[/span]` ? (default=false)
	 * @return string Formatted with a flavor of markdown
	 *
	 */
	public function entitiesMarkdown($str, $options = array()) {
		
		$defaults = array(
			'fullMarkdown' => false, 
			'flags' => ENT_QUOTES,
			'encoding' => 'UTF-8',
			'doubleEncode' => true,
			'allowBrackets' => false, // allow [bracket] tags?
			'allow' => array('a', 'strong', 'em', 'code', 's', 'span', 'u', 'small', 'i'),
			'disallow' => array(),
			'linkMarkup' => '<a href="{url}" rel="noopener noreferrer nofollow" target="_blank">{text}</a>',
		);

		if($options === true || (is_int($options) && $options > 0)) $defaults['fullMarkdown'] = $options;
		if(!is_array($options)) $options = array();
		$options = array_merge($defaults, $options); 

		if($options['fullMarkdown']) {
			
			$markdown = $this->wire('modules')->get('TextformatterMarkdownExtra');
			if(is_int($options['fullMarkdown'])) {
				$markdown->flavor = $options['fullMarkdown'];
			} else {
				$markdown->flavor = TextformatterMarkdownExtra::flavorParsedown;
			}
			$markdown->format($str);
			
		} else {

			$str = $this->entities($str, $options['flags'], $options['encoding'], $options['doubleEncode']);

			if(strpos($str, '](') && in_array('a', $options['allow']) && !in_array('a', $options['disallow'])) {
				// link
				$linkMarkup = str_replace(array('{url}', '{text}'), array('$2', '$1'), $options['linkMarkup']);
				$str = preg_replace('/\[(.+?)\]\(([^)]+)\)/', $linkMarkup, $str);
			}

			if(strpos($str, '**') !== false && in_array('strong', $options['allow']) && !in_array('strong', $options['disallow'])) {
				// strong
				$str = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $str);
			}

			if(strpos($str, '*') !== false && in_array('em', $options['allow']) && !in_array('em', $options['disallow'])) {
				// em
				$str = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $str);
			}

			if(strpos($str, "`") !== false && in_array('code', $options['allow']) && !in_array('code', $options['disallow'])) {
				// code
				$str = preg_replace('/`+([^`]+)`+/', '<code>$1</code>', $str);
			}

			if(strpos($str, '~~') !== false && in_array('s', $options['allow']) && !in_array('s', $options['disallow'])) {
				// strikethrough
				$str = preg_replace('/~~(.+?)~~/', '<s>$1</s>', $str);
			}
		}

		if($options['allowBrackets'] && strpos($str, '[/')) {
			// support [bracketed] inline-level tags, optionally with id "#" or class "." attributes (ascii-only)
			// example: [span.detail]some text[/span] or [strong#someid.someclass]text[/strong] or [em.class1.class2]text[/em]
			$tags = implode('|', $options['allow']);
			$reps = array();
			if(preg_match_all('!\[(' . $tags . ')((?:[.#][-_a-zA-Z0-9]+)*)\](.*?)\[/\\1\]!', $str, $matches)) {
				foreach($matches[0] as $key => $full) {
					$tag = $matches[1][$key];
					$attr = $matches[2][$key];
					$text = $matches[3][$key];
					if(in_array($tag, $options['disallow']) || $tag == 'a') continue;
					$class = '';
					$id = '';
					if(strlen($attr)) {
						foreach(explode('.', $attr) as $c) {
							if(strpos($c, '#') !== false) list($c, $id) = explode('#', $c, 2);
							if(!empty($c)) $class .= "$c ";
						}
					}
					$reps[$full] = "<$tag" . ($id ? " id='$id'" : '') . ($class ? " class='$class'" : '') . ">$text</$tag>";
				}
			}
			if(count($reps)) $str = str_replace(array_keys($reps), array_values($reps), $str);
		}
		
		return $str;
	}

	/**
	 * Remove entity encoded characters from a string. 
	 * 
	 * Wrapper for PHP's `html_entity_decode()` function that contains typical ProcessWire usage defaults.
	 *
	 * The arguments used here are identical to those for PHP's 
	 * [html_entity_decode](http://www.php.net/manual/en/function.html-entity-decode.php) function.
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to remove entities from
	 * @param int|bool $flags See PHP html_entity_decode function for flags. 
	 * @param string $encoding Encoding (default="UTF-8").
	 * @return string String with entities removed.
	 *
	 */
	public function unentities($str, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
		if(!is_string($str)) $str = $this->string($str);
		return html_entity_decode($str, $flags, $encoding); 
	}

	/**
	 * Alias for unentities
	 * 
	 * #pw-internal
	 * 
	 * @param $str
	 * @param $flags
	 * @param $encoding
	 * @return string
	 * @deprecated
	 * 
	 */
	public function removeEntities($str, $flags, $encoding) {
		return $this->unentities($str, $flags, $encoding); 
	}

	/**
	 * Purify HTML markup using HTML Purifier
	 *
	 * See: [htmlpurifier.org](http://htmlpurifier.org)
	 * 
	 * #pw-group-strings
	 *
	 * @param string $str String to purify
	 * @param array $options See [config options](http://htmlpurifier.org/live/configdoc/plain.html).
	 * @return string Purified markup string.
	 * @throws WireException if given something other than a string
	 *
	 */
	public function purify($str, array $options = array()) {
		static $purifier = null;
		static $_options = array();
		if(!is_string($str)) $str = $this->string($str);
		if(is_null($purifier) || print_r($options, true) != print_r($_options, true)) {
			$purifier = $this->purifier($options);
			$_options = $options;
		}
		return $purifier->purify($str);
	}

	/**
	 * Return a new HTML Purifier instance
	 *
	 * See: [htmlpurifier.org](http://htmlpurifier.org)
	 * 
	 * #pw-group-other
	 *
	 * @param array $options See [config options](http://htmlpurifier.org/live/configdoc/plain.html).
	 * @return MarkupHTMLPurifier
	 *
	 */
	public function purifier(array $options = array()) {
		$purifier = $this->wire('modules')->get('MarkupHTMLPurifier');
		foreach($options as $key => $value) $purifier->set($key, $value);
		return $purifier;
	}

	/**
	 * Remove newlines from the given string and return it
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to remove newlines from
	 * @param string $replacement Character to replace newlines with (default=" ")
	 * @return string String without newlines
	 *
	 */
	function removeNewlines($str, $replacement = ' ') {
		return str_replace(array("\r\n", "\r", "\n"), $replacement, $str);
	}

	/**
	 * Sanitize value to string
	 *
	 * Note that this makes no assumptions about what is a "safe" string, so you should always apply another
	 * sanitizer to it.
	 * 
	 * #pw-group-strings
	 *
	 * @param string|int|array|object|bool|float $value Value to sanitize as string
	 * @param string|null Optional sanitizer method (from this class) to apply to the string before returning
	 * @return string
	 *
	 */
	public function string($value, $sanitizer = null) {
		if(is_object($value)) {
			if(method_exists($value, '__toString')) {
				$value = (string) $value;
			} else {
				$value = get_class($value);
			}
		} else if(is_null($value)) {
			$value = "";
		} else if(is_bool($value)) {
			$value = $value ? "1" : "";
		} else if(is_array($value)) {
			$value = "array-" . count($value);
		} else if(!is_string($value)) {
			$value = (string) $value;
		}
		if(!is_null($sanitizer) && is_string($sanitizer) && (method_exists($this, $sanitizer) || method_exists($this, "___$sanitizer"))) {
			$value = $this->$sanitizer($value);
			if(!is_string($value)) $value = (string) $value;
		}
		return $value;
	}

	/**
	 * Sanitize a date or date/time string, making sure it is valid, and return it
	 *
	 * - If no date $format is specified, date will be returned as a unix timestamp.
	 * - If given date is invalid or empty, NULL will be returned.
	 * - If $value is an integer or string of all numbers, it is always assumed to be a unix timestamp.
	 * 
	 * #pw-group-strings
	 * #pw-group-numbers
	 *
	 * @param string|int $value Date string or unix timestamp
	 * @param string|null $format Format of date string ($value) in any wireDate(), date() or strftime() format.
	 * @param array $options Options to modify behavior:
	 *  - `returnFormat` (string): wireDate() format to return date in. If not specified, then the $format argument is used.
	 *  - `min` (string|int): Minimum allowed date in $format or unix timestamp format. Null is returned when date is less than this.
	 *  - `max` (string|int): Maximum allowed date in $format or unix timestamp format. Null is returned when date is more than this.
	 *  - `default` (mixed): Default value to return if no value specified.
	 * @return string|int|null
	 *
	 */
	public function date($value, $format = null, array $options = array()) {
		$defaults = array(
			'returnFormat' => $format, // date format to return in, if different from $dateFormat
			'min' => '', // Minimum date allowed (in $dateFormat format, or a unix timestamp) 
			'max' => '', // Maximum date allowed (in $dateFormat format, or a unix timestamp)
			'default' => null, // Default value, if date didn't resolve
		);
		$options = array_merge($defaults, $options);
		if(empty($value)) return $options['default'];
		if(!is_string($value) && !is_int($value)) $value = $this->string($value);
		if(ctype_digit("$value")) {
			// value is in unix timestamp format
			// make sure it resolves to a valid date
			$value = strtotime(date('Y-m-d H:i:s', (int) $value));
		} else {
			$value = strtotime($value);
		}
		// value is now a unix timestamp
		if(empty($value)) return null;
		if(!empty($options['min'])) {
			// if value is less than minimum required, return null/error
			$min = ctype_digit("$options[min]") ? (int) $options['min'] : (int) wireDate('ts', $options['min']);
			if($value < $min) return null;
		}
		if(!empty($options['max'])) {
			// if value is more than max allowed, return null/error
			$max = ctype_digit("$options[max]") ? (int) $options['max'] : (int) wireDate('ts', $options['max']);
			if($value > $max) return null;
		}
		if(!empty($options['returnFormat'])) $value = wireDate($options['returnFormat'], $value);
		return empty($value) ? null : $value;
	}

	/**
	 * Validate that given value matches regex pattern. 
	 * 
	 * If given value matches, value is returned. If not, blank is returned.
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value Value to match
	 * @param string $regex PCRE regex pattern (same as you would provide to PHP's `preg_match()`)
	 * @return string Value you supplied if it matches, or blank string if it doesn't
	 * 
	 */
	public function match($value, $regex) {
		if(!is_string($value)) $value = $this->string($value);
		return preg_match($regex, $value) ? $value : '';
	}

	/*************************************************************************************************************************
	 * NUMBER SANITIZERS
	 * 
	 */

	/**
	 * Sanitized an integer (unsigned, unless you specify a negative minimum value)
	 * 
	 * #pw-group-numbers
	 * 
	 * @param mixed $value Value you want to sanitize as an integer
	 * @param array $options Optionally specify any one or more of the following to modify behavior: 
	 * 	- `min` (int|null): Minimum allowed value (default=0)
	 *  - `max` (int|null): Maximum allowed value (default=PHP_INT_MAX)
	 * 	- `blankValue` (mixed): Value that you want to use when provided value is null or blank string (default=0)
	 * @return int Returns integer, or specified blankValue (which doesn't necessarily have to be an integer)
	 * 
	 */	
	public function int($value, array $options = array()) {
		$defaults = array(
			'min' => 0, 
			'max' => PHP_INT_MAX,
			'blankValue' => 0,
		);
		$options = array_merge($defaults, $options);
		if(is_null($value) || $value === "") return $options['blankValue'];
		if(is_object($value)) $value = 1;
		$value = (int) $value; 
		if(!is_null($options['min']) && $value < $options['min']) {
			$value = (int) $options['min'];
		} else if(!is_null($options['max']) && $value > $options['max']) {
			$value = (int) $options['max'];
		}
		return $value;
	}

	/**
	 * Sanitize to unsigned (0 or positive) integer
	 * 
	 * This is an alias to the int() method with default min/max arguments.
	 * 
	 * #pw-group-numbers
	 * 
	 * @param mixed $value
	 * @param array $options Optionally specify any one or more of the following to modify behavior:
	 * 	- `min` (int|null): Minimum allowed value (default=0)
	 *  - `max` (int|null): Maximum allowed value (default=PHP_INT_MAX)
	 * 	- `blankValue` (mixed): Value that you want to use when provided value is null or blank string (default=0)
	 * @return int Returns integer, or specified blankValue (which doesn't necessarily have to be an integer)
	 * @return int
	 * 
	 */
	public function intUnsigned($value, array $options = array()) {
		return $this->int($value, $options);
	}

	/**
	 * Sanitize to signed integer (negative or positive)
	 * 
	 * #pw-group-numbers
	 *
	 * @param mixed $value
	 * @param array $options Optionally specify any one or more of the following to modify behavior:
	 * 	- `min` (int|null): Minimum allowed value (default=negative PHP_INT_MAX)
	 *  - `max` (int|null): Maximum allowed value (default=PHP_INT_MAX)
	 * 	- `blankValue` (mixed): Value that you want to use when provided value is null or blank string (default=0)
	 * @return int
	 *
	 */
	public function intSigned($value, array $options = array()) {
		if(!isset($options['min'])) $options['min'] = PHP_INT_MAX * -1;
		return $this->int($value, $options);
	}

	/**
	 * Sanitize to floating point value
	 * 
	 * #pw-group-numbers
	 * 
	 * @param float|string|int $value
	 * @param array $options Optionally specify one or more options in an associative array:
	 * 	- `precision` (int|null): Optional number of digits to round to (default=null)
	 * 	- `mode` (int): Mode to use for rounding precision (default=PHP_ROUND_HALF_UP);
	 * 	- `blankValue` (null|int|string|float): Value to return (whether float or non-float) if provided $value is an empty non-float (default=0.0)
	 * 	- `min` (float|null): Minimum allowed value, excluding blankValue (default=null)
	 * 	- `max` (float|null): Maximum allowed value, excluding blankValue (default=null)
	 * @return float
	 * 
	 */
	public function float($value, array $options = array()) {
		
		$defaults = array(
			'precision' => null, // Optional number of digits to round to 
			'mode' => PHP_ROUND_HALF_UP, // Mode to use for rounding precision (default=PHP_ROUND_HALF_UP)
			'blankValue' => 0.0, // Value to return (whether float or non-float) if provided $value is an empty non-float (default=0.0)
			'min' => null, // Minimum allowed value (excluding blankValue)
			'max' => null, // Maximum allowed value (excluding blankValue)
		);
		
		$options = array_merge($defaults, $options);
	
		if($value === null || $value === false) return $options['blankValue'];
		if(!is_float($value) && !is_string($value)) $value = $this->string($value);

		if(is_string($value)) {
			
			$str = trim($value);
			$prepend = '';
			if(strpos($str, '-') === 0) {
				$prepend = '-';
				$str = ltrim($str, '-');
			}
		
			if(!strlen($str)) return $options['blankValue'];

			$dotPos = strrpos($str, '.');
			$commaPos = strrpos($str, ',');
			$decimalType = substr(floatval("9.9"), 1, 1);
			$pos = null;

			if($dotPos === 0 || ($commaPos === 0 && $decimalType == ',')) {
				// .123 or ,123
				$value = "0." . ltrim($str, ',.');

			} else if($dotPos > $commaPos) {
				// 123123.123
				// 123,123.123
				// dot assumed to be decimal
				$pos = $dotPos;

			} else if($commaPos > $dotPos) {
				// 123,123
				// 123123,123
				// 123.123,123
				if($dotPos === false && $decimalType === '.' && preg_match('/^\d+(,\d{3})+([^,]|$)/', $str)) {
					// US or GB style thousands separator with commas separating 3 digit sequences
					$pos = strlen($str);
				} else {
					// the rest of the world 
					$pos = $commaPos;
				}

			} else {
				$value = preg_replace('/[^0-9]/', '', $str);
			}

			if($pos !== null) {
				$value =
					// part before dot
					preg_replace('/[^0-9]/', '', substr($str, 0, $pos)) . '.' .
					// part after dot
					preg_replace('/[^0-9]/', '', substr($str, $pos + 1));
			}

			$value = floatval($prepend . $value);
		}
		
		if(!is_float($value)) $value = (float) $value;
		if(!is_null($options['min']) && $value < $options['min']) $value = $options['min'];
		if(!is_null($options['max']) && $value > $options['max']) $value = $options['max'];
		if(!is_null($options['precision'])) $value = round($value, (int) $options['precision'], (int) $options['mode']);
		
		return $value;
	}

	/***********************************************************************************************************************
	 * ARRAY SANITIZERS
	 * 
	 */

	/**
	 * Sanitize array or CSV string to array of strings
	 *
	 * If string specified, string delimiter may be pipe ("|"), or comma (","), unless overridden with the 'delimiter'
	 * or 'delimiters' option. 
	 * 
	 * #pw-group-arrays
	 *
	 * @param array|string|mixed $value Accepts an array or CSV string. If given something else, it becomes first item in array.
	 * @param string $sanitizer Optional Sanitizer method to apply to items in the array (default=null, aka none).
	 * @param array $options Optional modifications to default behavior:
	 * 	`maxItems` (int): Maximum items allowed in array (default=0, which means no limit)  
	 * 	The following options are only used if the provided $value is a string: 
	 * 	- `delimiter` (string): Single delimiter to use to identify CSV strings. Overrides the 'delimiters' option when specified (default=null)
	 * 	- `delimiters` (array): Delimiters to identify CSV strings. First found delimiter will be used, default=array("|", ",")
	 * 	- `enclosure` (string): Enclosure to use for CSV strings (default=double quote, i.e. ")
	 * @return array
	 * @throws WireException if an unknown $sanitizer method is given
	 *
	 */
	public function ___array($value, $sanitizer = null, array $options = array()) {
		$defaults = array(
			'delimiter' => null, 
			'delimiters' => array('|', ','),
			'enclosure' => '"', 
			'maxItems' => 0, 
		);
		$options = array_merge($defaults, $options);
		if(!is_array($value)) {
			if(is_null($value)) return array();
			if(is_object($value)) {
				// value is object: convert to string or array
				if(method_exists($value, '__toString')) {
					$value = (string) $value;
				} else {
					$value = array(get_class($value));
				}
			}
			if(is_string($value)) {
				// value is string
				$hasDelimiter = null;
				$delimiters = is_null($options['delimiter']) ? $options['delimiters'] : array($options['delimiter']);
				foreach($delimiters as $delimiter) {
					if(strpos($value, $delimiter)) {
						$hasDelimiter = $delimiter;
						break;
					}
				}
				if($hasDelimiter !== null) {
					$value = str_getcsv($value, $hasDelimiter, $options['enclosure']);
				} else {
					$value = array($value);
				}
			}
			if(!is_array($value)) $value = array($value);
		}
		if($options['maxItems']) {
			if(count($value) > $options['maxItems']) $value = array_slice($value, 0, abs($options['maxItems']));	
		}
		$clean = array();
		if(!is_null($sanitizer)) {
			if(!method_exists($this, $sanitizer) && !method_exists($this, "___$sanitizer")) {
				throw new WireException("Unknown sanitizer method: $sanitizer");
			}
			foreach($value as $k => $v) {
				$clean[$k] = $this->$sanitizer($v);
			}
		} else {
			$clean = $value;
		}
		return array_values($clean);
	}
	
	/**
	 * Sanitize array or CSV string to array of unsigned integers (or signed if specified $min is less than 0)
	 *
	 * If string specified, string delimiter may be comma (","), or pipe ("|"), or you may override with the 'delimiter' option.
	 * 
	 * #pw-group-arrays
	 * #pw-group-numbers
	 *
	 * @param array|string|mixed $value Accepts an array or CSV string. If given something else, it becomes first value in array.
	 * @param array $options Optional options (see `Sanitizer::array()` and `Sanitizer::int()` methods for options), plus these two: 
	 * 	- `min` (int): Minimum allowed value (default=0)
	 * 	- `max` (int): Maximum allowed value (default=PHP_INT_MAX)
	 * @return array Array of integers
	 *
	 */
	public function intArray($value, array $options = array()) {
		if(!is_array($value)) {
			$value = $this->___array($value, null, $options);
		}
		$clean = array();
		foreach($value as $k => $v) {
			$clean[$k] = $this->int($v, $options);
		}
		return array_values($clean);
	}

	/**
	 * Minimize an array to remove empty values
	 * 
	 * #pw-group-arrays
	 *
	 * @param array $data Array to reduce
	 * @param bool|array $allowEmpty Should empty values be allowed in the encoded data? Specify any of the following:
	 *  - `false` (bool): to exclude all empty values (this is the default if not specified).
	 *  - `true` (bool): to allow all empty values to be retained (thus no point in calling this function).
	 *  - Specify array of keys (from data) that should be retained if you want some retained and not others.
	 *  - Specify array of literal empty value types to retain, i.e. [ 0, '0', array(), false, null ]
	 *  - Specify the digit `0` to retain values that are 0, but not other types of empty values.
	 * @param bool $convert Perform type conversions where appropriate? i.e. convert digit-only string to integer (default=false). 
	 * @return array
	 *
	 */
	public function minArray($data, $allowEmpty = false, $convert = false) {
		
		if(!is_array($data)) {
			$data = $this->___array($data, null);
		}
	
		$allowEmptyTypes = array();
		if(is_array($allowEmpty)) {
			foreach($allowEmpty as $emptyType) {
				if(!empty($emptyType)) continue;
				$allowEmptyTypes[] = $emptyType;
			}
		}

		foreach($data as $key => $value) {

			if($convert && is_string($value)) {
				// make sure ints are stored as ints
				if(ctype_digit("$value") && $value <= PHP_INT_MAX) {
					if($value === "0" || $value[0] != '0') { // avoid octal conversions (leading 0)
						$value = (int) $value;
					}
				}
			} else if(is_array($value) && count($value)) {
				$value = $this->minArray($value, $allowEmpty, $convert);
			}

			$data[$key] = $value;

			// if value is not empty, no need to continue further checks
			if(!empty($value)) continue;
			
			$typeMatched = false;
			if(count($allowEmptyTypes)) {
				foreach($allowEmptyTypes as $emptyType) {
					if($value === $emptyType) {
						$typeMatched = true;
						break;
					}
				}
			}
			
			if($typeMatched) {
				// keep it because type matched an allowEmptyTypes

			} else if($allowEmpty === 0 && $value === 0) {
				// keep it because $allowEmpty === 0 means to keep 0 values only
				
			} else if(is_array($allowEmpty) && !in_array($key, $allowEmpty)) {
				// remove it because it's not specifically allowed in allowEmpty
				unset($data[$key]);

			} else if(!$allowEmpty) {
				// remove the empty value
				unset($data[$key]);
			}
		}

		return $data;
	}

	/**
	 * Return $value if it exists in $allowedValues, or null if it doesn't
	 * 
	 * #pw-group-arrays
	 *
	 * @param string|int $value
	 * @param array $allowedValues Whitelist of option values that are allowed
	 * @return string|int|null
	 *
	 */
	public function option($value, array $allowedValues) {
		$key = array_search($value, $allowedValues);
		if($key === false) return null;
		return $allowedValues[$key];
	}

	/**
	 * Return given values that that also exist in $allowedValues whitelist
	 * 
	 * #pw-group-arrays
	 *
	 * @param array $values
	 * @param array $allowedValues Whitelist of option values that are allowed
	 * @return array
	 *
	 */
	public function options(array $values, array $allowedValues) {
		$a = array();
		foreach($values as $value) {
			$key = array_search($value, $allowedValues);
			if($key !== false) $a[] = $allowedValues[$key];
		}
		return $a;
	}

	/****************************************************************************************************************************
	 * OTHER SANITIZERS
	 * 
	 */

	/**
	 * Convert the given value to a boolean
	 * 
	 * This differs from regular boolean type conversion in the following ways: 
	 * 
	 * - This method will recognize things like the strings "false" or "0" representing a boolean false.
	 * - If given an object, it will convert the object to a string before determining what boolean value it should represent.
	 * - If given an array, it returns false if the array contains zero items. 
	 * 
	 * #pw-group-other
	 * 
	 * @param $value
	 * @return bool
	 * 
	 */
	public function bool($value) {
		if(is_string($value)) {
			$value = trim(strtolower($value));
			$length = strlen($value);
			if(!$length) return false;
			if($value === "0") return false;
			if($value === "1") return true; 
			if($value === "false") return false;
			if($value === "true") return true;
			if($length) return true; 
		} else if(is_object($value)) {
			$value = $this->string($value);
		} else if(is_array($value)) {
			$value = count($value) ? true : false;
		}
		return (bool) $value;
	}

	/**
	 * Run value through all sanitizers, return array indexed by sanitizer name and resulting value
	 * 
	 * Used for debugging and testing purposes. 
	 * 
	 * #pw-group-other
	 * 
	 * @param $value
	 * @return array
	 * 
	 */
	public function testAll($value) {
		$sanitizers = array(
			'alpha', 
			'alphanumeric',
			'array',
			'bool',
			'date',
			'digits', 
			'email',
			'emailHeader',
			'entities',
			'entities1',
			'entitiesMarkdown',
			'fieldName',
			'filename',
			'float',
			'int',
			'intArray',
			'intSigned',
			'intUnsigned',
			'markupToLine',
			'markupToText',
			'minArray',
			'name',
			'names',
			'pageName',
			'pageNameTranslate',
			'pageNameUTF8',
			'pagePathName',
			'pagePathNameUTF8',
			'path',
			'purify',
			'removeNewlines',
			'selectorField',
			'selectorValue',
			'string',
			'templateName',
			'text',
			'textarea',
			'unentities',
			'url',
			'varName',
		);
		$results = array();
		foreach($sanitizers as $method) {
			$results[$method] = $this->$method($value);
		}
		return $results;
	}

	/**********************************************************************************************************************
	 * FILE VALIDATORS
	 *
	 */

	/**
	 * Validate a file using FileValidator modules
	 *
	 * Note that this is intended for validating file data, not file names.
	 *
	 * IMPORTANT: This method returns NULL if it can't find a validator for the file. This does
	 * not mean the file is invalid, just that it didn't have the tools to validate it.
	 * 
	 * #pw-group-files
	 *
	 * @param string $filename Full path and filename to validate
	 * @param array $options When available, provide array with any one or all of the following:
	 *  - `page` (Page): Page object associated with $filename.
	 *  - `field` (Field): Field object associated with $filename.
	 *  - `pagefile` (Pagefile): Pagefile object associated with $filename.
	 * @return bool|null Returns TRUE if valid, FALSE if not, or NULL if no validator available for given file type.
	 *
	 */
	public function validateFile($filename, array $options = array()) {
		$defaults = array(
			'page' => null,
			'field' => null,
			'pagefile' => null,
		);
		$options = array_merge($defaults, $options);
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$validators = $this->wire('modules')->findByPrefix('FileValidator', false);
		$isValid = null;
		foreach($validators as $validatorName) {
			$info = $this->wire('modules')->getModuleInfoVerbose($validatorName);
			if(empty($info) || empty($info['validates'])) continue;
			foreach($info['validates'] as $ext) {
				if($ext[0] == '/') {
					if(!preg_match($ext, $extension)) continue;
				} else if($ext !== $extension) {
					continue;
				}
				$validator = $this->wire('modules')->get($validatorName);
				if(!$validator) continue;
				if(!empty($options['page'])) $validator->setPage($options['page']);
				if(!empty($options['field'])) $validator->setField($options['field']);
				if(!empty($options['pagefile'])) $validator->setPagefile($options['pagefile']);
				$isValid = $validator->isValid($filename);
				if(!$isValid) {
					// move errors to Sanitizer class so they can be retrieved
					foreach($validator->errors('clear array') as $error) {
						$this->wire('log')->error($error);
						$this->error($error);
					}
					break;
				}
			}
		}
		return $isValid;
	}


	public function __toString() {
		return "Sanitizer";
	}

}

