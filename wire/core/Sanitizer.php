<?php namespace ProcessWire;

/**
 * ProcessWire Sanitizer
 *
 * Sanitizer provides shared sanitization functions as commonly used throughout ProcessWire core and modules
 * 
 * #pw-summary Provides methods for sanitizing and validating user input, preparing data for output, and more. 
 * #pw-use-constants
 * #pw-body = 
 * Sanitizer is useful for sanitizing input or any other kind of data that you need to match a particular type or format. 
 * The Sanitizer methods are accessed from the `$sanitizer` API variable and/or `sanitizer()` API variable/function.
 * For example:
 * ~~~~~~
 * $cleanValue = $sanitizer->text($dirtyValue); 
 * ~~~~~~
 * You can replace the `text()` call above with any other sanitizer method. Many sanitizer methods also accept additional
 * arguments—see each individual method for details. 
 * 
 * ### Sanitizer and input
 * 
 * Sanitizer methods are most commonly used with user input. As a result, the methods in this class are also accessible
 * from the `$input->get`, `$input->post` and `$input->cookie` API variables, in the same manner that they are here. 
 * This is a useful shortcut for instances where you don’t need to provide additional arguments to the sanitizer method.
 * Below are a few examples of this usage:
 * ~~~~~
 * // get GET variable 'id' as integer
 * $id = $input->get->int('id');
 * 
 * // get POST variable 'name' as 1-line plain text
 * $name = $input->post->text('name');
 * 
 * // get POST variable 'comments' as multi-line plain text
 * $comments = $input->post->textarea('comments'); 
 * ~~~~~
 * In ProcessWire 3.0.125 and newer you can also perform the same task as the above with one less `->` level like the
 * example below: 
 * ~~~~~
 * $comments = $input->post('comments','textarea'); 
 * ~~~~~
 * This is more convenient in some IDEs because it’ll never be flagged as an unrecognized function call. Though outside
 * of that it makes little difference how you call it, as they both do the same thing. 
 * 
 * See the `$input` API variable for more details on how to call sanitizers directly from $input.
 * 
 * ### Adding your own sanitizers
 *
 * You can easily add your own new sanitizers via ProcessWire hooks. Hooks are commonly added in a /site/ready.php file, 
 * or from a Module, though you may add them wherever you want. The following example adds a sanitizer method called 
 * `zip()` which enforces a 5 digit zip code:
 * ~~~~~
 * $sanitizer->addHook('zip', function(HookEvent $event) {
 *   $sanitizer = $event->object;
 *   $value = $event->arguments(0); // get first argument given to method
 *   $value = $sanitizer->digits($value, 5); // allow only digits, max-length 5
 *   if(strlen($value) < 5) $value = ''; // if fewer than 5 digits, it is not a zip
 *   $event->return = $value;
 * });
 *
 * // now you can use your zip sanitizer
 * $dirtyValue = 'Decatur GA 30030';
 * $cleanValue = $sanitizer->zip($dirtyValue);
 * echo $cleanValue; // outputs: 30030
 * ~~~~~
 *
 * ### Additional options (3.0.125 or newer)
 * 
 * In ProcessWire 3.0.125+ you can also combine sanitizer methods in a single call. These are defined by separating each 
 * sanitizer method with an understore. The example below runs the value through the text sanitizer and then through the 
 * entities sanitizer:
 * ~~~~~
 * $cleanValue = $sanitizer->text_entities($dirtyValue);
 * ~~~~~
 * If you append a number to any sanitizer call that returns a string, it is assumed to be maximum allowed length. For 
 * example, the following would sanitize the value to be text of no more than 20 characters:
 * ~~~~~
 * $cleanValue = $sanitizer->text20($dirtyValue); 
 * ~~~~~
 * The above technique also works for any user-defined sanitizers you’ve added via hooks. We like this strategy for 
 * storage of sanitizer calls that are executed at some later point, like those you might store in a module config. It
 * essentially enables you to define loose data types for sanitization. In addition, if there are other cases where you
 * need multiple sanitizers to clean a particular value, this strategy can do it with a lot less code than you would 
 * with multiple sanitizer calls. 
 * 
 * Most methods in the Sanitizer class focus on sanitization rather than validation, with a few exceptions. You can 
 * convert a sanitizer call to validation call by calling the `validate()` method with the name of the sanitizer and the
 * value. A validation call simply implies that if the value is modified by sanitization then it is considered invalid
 * and thus it’ll return a non-value rather than a sanitized value. See the `Sanitizer::validate()` and 
 * `Sanitizer::valid()` methods for usage details. 
 * 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 * @link https://processwire.com/api/variables/sanitizer/ Offical $sanitizer API variable Documentation
 * 
 * @method array($value, $sanitizer = null, array $options = array())
 * @method array testAll($value)
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
	 * ASCII alpha chars
	 * 
	 * @var string
	 * 
	 */
	protected $alphaASCII = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
	 * ASCII digits chars
	 * 
	 * @var string
	 * 
	 */
	protected $digitASCII = '0123456789';

	/**
	 * @var null|WireTextTools
	 * 
	 */
	protected $textTools = null;
	
	/**
	 * @var null|WireNumberTools
	 *
	 */
	protected $numberTools = null;

	/**
	 * Runtime caches
	 * 
	 * @var array 
	 * 
	 */
	protected $caches = array();

	/**
	 * UTF-8 whitespace hex codes
	 * 
	 * @var array
	 * 
	 */
	protected $whitespaceUTF8 = array(
		'0000', // null byte
		'0009', // character tab
		'000A', // line feed
		'000B', // line tab (vertical tab)
		'000C', // form feed
		'000D', // carriage return
		'0020', // space
		'0085', // next line
		'00A0', // non-breaking space
		'1680', // ogham space mark
		'180E', // mongolian vowel separator
		'2000', // en quad
		'2001', // em quad
		'2002', // en space
		'2003', // em space
		'2004', // three per em space
		'2005', // four per em space
		'2006', // six per em space
		'2007', // figure space
		'2008', // punctuation space
		'2009', // thin space
		'200A', // hair space
		'200B', // zero width space
		'200C', // zero width non-join
		'200D', // zero width join
		'2028', // line seperator
		'2029', // paragraph seperator
		'202F', // narrow non-breaking space
		'205F', // medium mathematical space   
		'2060', // word join
		'3000', // ideographic space
		'FEFF', // zero width non-breaking space
	);

	/**
	 * HTML entities representing whitespace
	 * 
	 * Note that this array is populated with all decimal/hex entities after a call to 
	 * getWhitespaceArray() method with the $html option as true. 
	 * 
	 * @var array
	 * 
	 */
	protected $whitespaceHTML = array(
		'&nbsp;', // non-breaking space
		'&ensp;', // en space
		'&emsp;', // em space
		'&thinsp;', // thin space
		'&zwnj;', // zero width non-join
		'&zwj;', // zero width join
	);

	/**
	 * Characters blacklisted from UTF-8 page names
	 * 
	 * @var string[] 
	 * 
	 */
	protected $pageNameBlacklist = array(
		'/', '\\', '%', '"', "'", '<', '>', '?', '!', '#', '@', ':', ';', ',', 
		'+', '=', '*', '^', '$', '(', ')', '[', ']', '{', '}', '|', '&',
	);

	/**
	 * Sanitizer method names (A-Z) and type(s) they return 
	 * 
	 * a: array
	 * b: boolean
	 * f: float
	 * i: integer
	 * m: mixed
	 * n: null
	 * s: string
	 * 
	 * @var array
	 * 
	 */
	protected $sanitizers = array(
		'alpha' => 's',
		'alphanumeric' => 's',
		'array' => 'a',
		'arrayVal' => 'a',
		'attrName' => 's',
		'bit' => 'i',
		'bool' => 'b',
		'camelCase' => 's',
		'chars' => 's',
		'checkbox' => 'b',
		'date' => 'ins',
		'digits' => 's',
		'email' => 's',
		'emailHeader' => 's',
		'entities' => 's',
		'entities1' => 's',
		'entitiesA' => 'asifb',
		'entitiesA1' => 'asifb',
		'entitiesMarkdown' => 's',
		'fieldName' => 's',
		'fieldSubfield' => 's',
		'filename' => 's',
		'flatArray' => 'a',
		'float' => 'f',
		'htmlClass' => 's', 
		'htmlClasses' => 's',
		'httpUrl' => 's',
		'hyphenCase' => 's',
		'int' => 'i',
		'intArray' => 'a',
		'intArrayVal' => 'a',
		'intSigned' => 'i',
		'intUnsigned' => 'i',
		'kebabCase' => 's',
		'line' => 's',
		'lines' => 's',
		'markupToLine' => 's',
		'markupToText' => 's',
		'max' => 'fi',
		'maxBytes' => 's',
		'maxLength' => 'afis',
		'minLength' => 's',
		'min' => 'fi',
		'minArray' => 'a',
		'name' => 's',
		'names' => 'as',
		'normalizeWhitespace' => 's',
		'pageName' => 's',
		'pageNameTranslate' => 's',
		'pageNameUTF8' => 's',
		'pagePathName' => 's',
		'pagePathNameUTF8' => 's',
		'pascalCase' => 's',
		'path' => 'bs',
		'purify' => 's',
		'range' => 'fi',
		'reduceWhitespace' => 's',
		'removeMB4' => 'ams',
		'removeNewlines' => 's',
		'removeWhitespace' => 's',
		'sanitize' => 'm',
		'selectorField' => 's',
		'selectorValue' => 's',
		'selectorValueAdvanced' => 's',
		'snakeCase' => 's',
		'string' => 's',
		'templateName' => 's',
		'text' => 's',
		'textarea' => 's',
		'textdomain' => 's', 
		'trim' => 's',
		'truncate' => 's',
		'unentities' => 's',
		'url' => 's',
		'valid' => 'b',
		'validate' => 'm',
		'varName' => 's',
		'word' => 's', 
		'words' => 's', 
		'wordsArray' => 'a',
	);

	/**
	 * Construct the sanitizer
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->multibyteSupport = function_exists("mb_internal_encoding"); 
		if($this->multibyteSupport) mb_internal_encoding("UTF-8");
		$this->allowedASCII = str_split($this->alphaASCII . $this->digitASCII);
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
		
		if(!is_string($value)) $value = $this->string($value);
		$allowed = array_merge($this->allowedASCII, $allowedExtras); 
		$needsWork = strlen(str_replace($allowed, '', $value));
		$extras = implode('', $allowedExtras);

		if($beautify && $needsWork) {
			if($beautify === self::translate && $this->multibyteSupport) {
				$value = mb_strtolower($value);
				$replacements = array();

				if(empty($this->caches['nameFilterReplace'])) {
					$modules = $this->wire()->modules;
					if($modules) {
						$replacements = $this->wire()->modules->getConfig('InputfieldPageName', 'replacements');
						if(empty($replacements)) $replacements = InputfieldPageName::$defaultReplacements;
						$this->caches['nameFilterReplace'] = $replacements;
					}
				} else {
					$replacements = $this->caches['nameFilterReplace']; 
				}
			
				if(count($replacements)) {
					$value = str_replace(array_keys($replacements), array_values($replacements), $value);
					$needsWork = strlen(str_replace($allowed, '', $value));
				}
			}

			if($needsWork && function_exists("\\iconv")) {
				$v = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $value);
				if($v) $value = $v;
				$needsWork = strlen(str_replace($allowed, '', $value));
			}
		}

		if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength); 
		
		if($needsWork) {
			$value = str_replace(array("'", '"'), '', $value); // blank out any quotes
			$_value = $value;
			$filters = FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_BACKTICK;
			$value = filter_var($value, FILTER_UNSAFE_RAW, $filters);
			if(!strlen($value)) {
				// if above filter blanked out the string, try with brackets already replaced
				$value = str_replace(array('<', '>', '«', '»', '‹', '›'), $replacementChar, $_value);
				$value = filter_var($value, FILTER_UNSAFE_RAW, $filters);
			}
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
			if($replacementChar !== null && strlen($replacementChar)) {
				if(strpos($extras, $replacementChar) === false) $extras .= $replacementChar;
			}
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
					$value = preg_replace('![' . $allowedExtrasStr . ']{2,}!', $replacement, $value);
				}

				if(empty($options['allowDoubledReplacement'])) {
					// replace double'd replacements
					$r = "$replacement$replacement";
					while(strpos($value, $r) !== false) $value = str_replace($r, $replacement, $value);
				}
	
				// replace double dots
				while(strpos($value, '..') !== false) $value = str_replace('..', '.', $value);
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
		$value = str_replace($replace, ' ', "$value");
		$allowedExtras[] = ' ';
		$value = $this->nameFilter($value, $allowedExtras, $replacementChar, $beautify, 8192);
		if($delimeter != ' ') $value = str_replace(' ', $delimeter, $value); 
		while(strpos($value, "$delimeter$delimeter") !== false) {
			$value = str_replace("$delimeter$delimeter", $delimeter, $value);
		}
		$value = trim($value, $delimeter); 
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
		$value = $this->nameFilter($value, array('_'), '_'); 
		if(!ctype_alpha($value)) $value = ltrim($value, $this->digitASCII); // vars cannot begin with numbers
		return $value; 
	}

	/**
	 * Sanitize to an ASCII-only HTML attribute name
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value
	 * @param int $maxLength
	 * @return string
	 * @since 3.0.133
	 * 
	 */
	public function attrName($value, $maxLength = 255) {
	
		$value = $this->string($value);
		$value = trim($value); // force as trimmed string
		if(ctype_alpha($value) && strlen($value) <= $maxLength) return $value; // simple 1-word attributes

		// remove any non ":_a-zA-Z" characters from beginning of attribute name
		while(strlen($value) && strpos(":_$this->alphaASCII", substr($value, 0, 1)) === false) {
			$value = substr($value, 1); 
		}
		
		if(ctype_alnum(str_replace(array('-', '_', ':', '.'), '', $value))) {
			// names with HTML valid separators
			if(strlen($value) <= $maxLength) return $value; 
		}
		
		// at this point attribute name contains something unusual
		if(!ctype_graph($value)) {
			// contains non-visible characters
			$value = preg_replace('/[\s\r\n\t]+/', '-', $value);
			if(!ctype_graph($value)) $value = ''; // fail
		}
		
		if($value !== '') {
			// replace non-word, non-digit, non-punct characters
			$value = preg_replace('/[^-_.:\w\d]+/', '-', $value);
			$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
		}
		
		if($value === 'data-') $value = ''; // data attribute with no name is disallowed
		
		if(strlen($value) > $maxLength) {
			$value = substr($value, 0, $maxLength);
		}
		
		return $value;
	}

	/**
	 * Sanitize string to ASCII-only HTML class attribute value
	 * 
	 * Note that this does not support all possible characters in an HTML class attribute 
	 * and instead focuses on the most commonly used ones. Characters allowed in HTML class
	 * attributes from this method include: `-_:@a-zA-Z0-9`. This method does not allow
	 * values that have no letters or digits.
	 * 
	 * @param string $value
	 * @return string 
	 * @since 3.0.212
	 * 
	 */
	public function htmlClass($value) {
		$value = trim("$value");
		if(empty($value)) return '';
		$extras = array('-', '_', ':', '@');
		$value = $this->nameFilter($value, $extras, '-');
		$value = ltrim($value, '0123456789'); // cannot begin with digit
		if(trim($value, implode('', $extras)) === '') $value = ''; // do not allow extras-only class
		return $value;
	}

	/**
	 * Sanitize string to ASCII-only space-separated HTML class attribute values with no duplicates
	 * 
	 * See additional notes in `Sanitizer::htmlClass()` method. 
	 *
	 * @param string|array $value
	 * @param bool $getArray Get array rather than string? (default=false)
	 * @return string|array
	 * @since 3.0.212
	 *
	 */
	public function htmlClasses($value, $getArray = false) {
		if(is_array($value)) $value = implode(' ', $value);
		$value = str_replace(array("\n", "\r", "\t", ",", "."), ' ', $value);
		$value = trim("$value");
		if(empty($value)) return $getArray ? array() : '';
		$a = array();
		foreach(explode(' ', $value) as $c) {
			$c = $this->htmlClass($c);
			if(!empty($c)) $a[$c] = $c;
		}
		if($getArray) return array_values($a);
		return count($a) ? implode(' ', $a) : '';
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
	 * echo $sanitizer->fieldName($test); // outputs: Hello_world
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
	 * Sanitize as a field name but with optional subfield(s) like “field.subfield”
	 * 
	 * - Periods must be present to indicate subfield(s), otherwise behaves same as fieldName() sanitizer.
	 * - By default allows just one subfield. To allow more, increase the $limit argument. 
	 * - To allow any quantity of subfields, specify -1. 
	 * - To reduce a `field.subfield...` combo to just `field` specify 0 for limit argument. 
	 * - Maximum length of returned string is (128 + ($limit * 128)).
	 * 
	 * ~~~~~~
	 * echo $sanitizer->fieldSubfield('a.b.c'); // outputs: a.b (default behavior)
	 * echo $sanitizer->fieldSubfield('a.b.c', 2); // outputs: a.b.c
	 * echo $sanitizer->fieldSubfield('a.b.c', 0); // outputs: a
	 * echo $sanitizer->fieldSubfield('a.b.c', -1); // outputs: a.b.c (any quantity)
	 * echo $sanitizer->fieldSubfield('foo bar.baz'); // outputs: foo_bar.baz
	 * echo $sanitizer->fieldSubfield('foo bar baz'); // outputs: foo_bar_baz
	 * ~~~~~~
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value Value to sanitize
	 * @param int $limit Max allowed quantity of subfields, or use -1 for any quantity (default=1). 
	 * @return string
	 * @since 3.0.126
	 * 
	 */
	public function fieldSubfield($value, $limit = 1) {
		$value = $this->string($value);
		if(!strlen($value)) return '';
		if(!strpos($value, '.')) return $this->fieldName($value);
		$parts = array();
		foreach(explode('.', trim($value, '.')) as $part) {
			$part = $this->fieldName($part);
			if(!strlen($part)) break;
			$parts[] = $part;
			if($limit > -1 && count($parts) - 1 >= $limit) break;
		}
		$cnt = count($parts); 
		if(!$cnt) return '';
		return $cnt === 1 ? $parts[0] : implode('.', $parts);
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
	 *  - `$options` (array): You can optionally specify the $options array for this argument instead.
	 *  - `Sanitizer::translate` (constant): This will make it translate non-ASCII letters based on *InputfieldPageName* module config settings. 
	 *  - `Sanitizer::toAscii` (constant): Convert UTF-8 characters to punycode ASCII.
	 *  - `Sanitizer::toUTF8` (constant): Convert punycode ASCII to UTF-8.
	 *  - `Sanitizer::okUTF8` (constant): Allow UTF-8 characters to appear in path (implied if $config->pageNameCharset is 'UTF8'). 
	 * @param int|array $maxLength Maximum number of characters allowed in the name.
	 *  You may also specify the $options array for this argument instead. 
	 * @param array $options Array of options to modify default behavior. See Sanitizer::name() method for available options, plus:
	 *  - `punycodeVersion` (int): Punycode version to use with UTF-8 page names, see Sanitizer::getPunycodeVersion() method for details.
	 * @return string
	 * @see Sanitizer::name()
	 *
	 */
	public function pageName($value, $beautify = false, $maxLength = 128, array $options = array()) {
	
		$value = $this->string($value);
		if(!strlen($value)) return '';
		
		$defaults = array(
			'charset' => $this->wire()->config->pageNameCharset,
			'punycodeVersion' => 0, 
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
				
				$tt = $this->getTextTools();
				$max = $maxLength;
				
				do {
					// encode value
					$value = $this->punyEncodeName($_value, $options['punycodeVersion']);
					// if result stayed within our allowed character limit, then good, we're done
					if(strlen($value) <= $maxLength) break;
					// continue loop until encoded value is equal or less than allowed max length
					$_value = $tt->substr($_value, 0, $max--);
				} while(true);
				
				// if encode was necessary and successful, return with no further processing
				if(strpos($value, 'xn-') === 0) {
					return $value;
				} else {
					if(strlen($value) && ctype_alnum(str_replace(array('-', '_', '.'), '', $value))) {
						if($this->getPunycodeVersion($options['punycodeVersion']) > 1) return $value;
					}
						
					// can't be encoded, send to regular name sanitizer
					$value = $_value;
				}
			}
			
		} else if($beautify === self::toUTF8) {
			// convert ascii IDN/punycode to UTF8
			$beautify = self::okUTF8;
			if(strpos($value, 'xn-') === 0) {
				// found something to convert
				$value = $this->punyDecodeName($value, $options['punycodeVersion']);
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
	 * @param int $maxLength Maximum number of characters allowed
	 * @return string Sanitized value
	 *
	 */
	public function pageNameUTF8($value, $maxLength = 128) {
	
		$value = $this->string($value);
		if(!strlen($value)) return '';
		
		$config = $this->wire()->config;
		$keepGoing = true;
		
		// if UTF8 module is not enabled then delegate this call to regular pageName sanitizer
		if($config->pageNameCharset != 'UTF8') return $this->pageName($value, false, $maxLength);
		
		$tt = $this->getTextTools();
	
		// we don't allow UTF8 page names to be prefixed with "xn-"
		if(strpos($value, 'xn-') === 0) $value = substr($value, 3);

		// word separators that we always allow 
		$separators = array('.', '-', '_');

		// whitelist of allowed characters and blacklist of disallowed characters
		$whitelist = $config->pageNameWhitelist;
		if(!strlen($whitelist)) $whitelist = false;
		
		$value = str_replace($this->pageNameBlacklist, '-', $value);
		
		// we let regular pageName handle chars like these, if they appear without other UTF-8
		$extras = array('.', '-', '_', ',', ';', ':', '(', ')', '!', '?', '&', '%', '$', '#', '@');
		if($whitelist === false || strpos($whitelist, ' ') === false) $extras[] = ' ';

		// proceed only if value has some non-ascii characters
		if(ctype_alnum(str_replace($extras, '', $value))) {
			$k = 'pageNameUTF8.whitelistIsLowercase';
			if(!isset($this->caches[$k])) {
				$this->caches[$k] = $whitelist !== false && $tt->strtolower($whitelist) === $whitelist;
			}
			if($this->caches[$k] || $tt->strtolower($value) === $value) {
				// whitelist supports only lowercase OR value is all lowercase 
				// let regular pageName sanitizer handle this
				$value = $this->pageName($value, false, $maxLength);
				// maintain old behavior for existing installations
				if($this->getPunycodeVersion() < 2) return $value; 
				$keepGoing = false;
			}
		}

		if($keepGoing) {
			// validate that all characters are in our whitelist
			$replacements = array();

			for($n = 0; $n < $tt->strlen($value); $n++) {
				$c = $tt->substr($value, $n, 1);
				if($c === '-') continue;
				$inWhitelist = $whitelist !== false && $tt->strpos($whitelist, $c) !== false;
				if($inWhitelist) {
					// in whitelist
				} else if(!strlen(trim($c)) || ctype_cntrl($c)) {
					// character does not resolve to something visible 
					$replacements[] = $c;
				} else if($whitelist === false) {
					// whitelist disabled: allow everything that is not blacklisted
				} else {
					// character that is not in whitelist, double check case variants
					$cLower = $tt->strtolower($c);
					$cUpper = $tt->strtoupper($c);
					if($cLower !== $c && $tt->strpos($whitelist, $cLower) !== false) {
						// allow character and convert to lowercase variant
						$value = $tt->substr($value, 0, $n) . $cLower . $tt->substr($value, $n + 1);
					} else if($cUpper !== $c && $tt->strpos($whitelist, $cUpper) !== false) {
						// allow character and convert to uppercase variant
						$value = $tt->substr($value, 0, $n) . $cUpper . $tt->substr($value, $n + 1);
					} else {
						// queue character to be replaced
						$replacements[] = $c;
					}
				}
			}

			// replace disallowed characters with "-"
			if(count($replacements)) $value = str_replace($replacements, '-', $value);
		}
		
		// replace doubled word separators
		foreach($separators as $c) {
			while(strpos($value, "$c$c") !== false) {
				$value = str_replace("$c$c", $c, $value);
			}
		}

		// trim off any remaining separators/extras
		$value = trim($value, '-_.');
		
		if($tt->strlen($value) > $maxLength) $value = $tt->substr($value, 0, $maxLength); 
		
		return $value;
	}

	/**
	 * Decode a PW-punycode'd name value
	 * 
	 * @param string $value
	 * @param int $version 0=auto-detect, 1=original/buggy, 2=punycode library, 3=php idn function
	 * @return string
	 * 
	 */
	protected function punyDecodeName($value, $version = 0) {
		// exclude values that we know can't be converted
		if(strlen($value) < 4 || strpos($value, 'xn-') !== 0) return $value;
		$version = $this->getPunycodeVersion($version);
		
		if(strpos($value, '__')) {
			// as used by punycode version 1 to split long strings
			$_value = $value;
			$parts = explode('__', $_value);
			foreach($parts as $n => $part) {
				$parts[$n] = $this->punyDecodeName($part, $version);
			}
			$value = implode('', $parts);
			return $value; 
		}
		
		$_value = $value; 
		
		// convert "xn-" single hyphen to recognized punycode "xn--" double hyphen
		if(strpos($value, 'xn--') !== 0) $value = 'xn--' . substr($value, 3);
		
		if($version >= 3) {
			// PHP IDN function
			// 32=IDNA_NONTRANSITIONAL_TO_UNICODE
			$info = array();
			$value = idn_to_utf8($value, 32, INTL_IDNA_VARIANT_UTS46, $info); 
			if(empty($value)) $value = $info['result'];
			
		} else if($version === 2) {
			// Punycode library
			$pc = new Punycode();
			$value = $pc->decode($value);
			
		} else {
			// PHP IDN with old/buggy behavior post PHP 7.4
			$value = @idn_to_utf8($value);
		}
		
		// if utf8 conversion failed, restore original value
		if($value === false || !strlen($value)) $value = $_value;
		
		return $value;
	}

	/**
	 * Encode a name value to PW-punycode
	 * 
	 * @param string $value
	 * @param int $version 0=auto-detect, 1=original/buggy, 2=punycode library, 3=php idn function
	 * @return string
	 * 
	 */
	protected function punyEncodeName($value, $version = 0) {
		
		$tt = $this->getTextTools();
		$version = $this->getPunycodeVersion($version);
		
		if(strpos($value, 'xn-') === 0) {
			if(ctype_alnum(str_replace(array('.', '-', '_'), '', $value))) {
				return $value;
			}
		}

		if($version > 1) {
			$whitelist = $this->wire()->config->pageNameWhitelist;
			$value = str_replace($this->pageNameBlacklist, '-', $value);
			$v = '';
			for($n = 0; $n < $tt->strlen($value); $n++) {
				$c = $tt->substr($value, $n, 1);
				if($tt->stripos($whitelist, $c) === false) {
					$c = $this->pageName($c, self::translate);
					if(empty($c) || $tt->stripos($whitelist, $c) === false) {
						$c = '-';
					}
				}
				$v .= $c;
			}
			while(strpos($v, '--') !== false) $v = str_replace('--', '-', $v);
			$value = $tt->trim($v, '-');
		}
		
		if(ctype_alnum(str_replace(array('.', '-', '_'), '', $value))) {
			$value = $this->pageName(trim($value), true);
			return $value;
		}

		while(strpos($value, '__') !== false) {
			$value = str_replace('__', '_', $value);
		}

		if($version > 1) {
			// version 2, 3
			while(strpos($value, '--') !== false) {
				$value = str_replace('--', '-', $value);
			}
			$value = trim($value, '-');
			
		} else if(strlen($value) >= 50) {
			// version 1
			$_value = $value;
			$parts = array();
			while(strlen($_value)) {
				$part = $tt->substr($_value, 0, 12);
				$_value = $tt->substr($_value, 12);
				$parts[] = $this->punyEncodeName($part, $version);
			}
			$value = implode('__', $parts);
			return $value;
		}

		$_value = $value;

		if($version >= 3) {
			// PHP 7.4+ idn_to_ascii
			$info = array();
			// 16=IDNA_NONTRANSITIONAL_TO_ASCII
			idn_to_ascii($value, 16, INTL_IDNA_VARIANT_UTS46, $info); 
			// IDN return value fails on longer strings, but populates result correctly
			$value = $info['result'];
			
		} else if($version === 2) {
			// Punycode library
			$pc = new Punycode();
			$value = $pc->encode($value);
			
		} else {
			// buggy behavior in PHP 7.4+ but pages may already be present with it
			// INTL_IDNA_VARIANT_2003 is default prior to PHP 7.4
			// substr() is also not right here but kept for v1 compatibility
			$value = substr(@idn_to_ascii($value), 3);
		}
		
		if(strpos($value, 'xn-') === 0) $value = substr($value, 3);
		
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
	 * Get internal Punycode version to use
	 *
	 * 0: Auto-detect from current environment.
	 * 1: PHP IDN function used by all PW versions prior to 3.0.244, but buggy PHP 7.4+.
	 * 2: Dedicated Punycode PHP library (no known issues at present).
	 * 3: PHP IDN function call updated for PHP 7.4+ (default in new installations after January 2025).
	 *
	 * @param int $version
	 * @return int 1=PHP DN but buggy after PHP 7.4+, 2=Punycode library, 3=PHP IDN function PHP 7.4+
	 * @since 3.0.244
	 *
	 */
	protected function getPunycodeVersion($version = 0) {
		$config = $this->wire()->config;
		if(!$version) {
			$whitelist = $config->pageNameWhitelist;
			for($n = 3; $n > 0; $n--) {
				if(strpos($whitelist, "v$n") !== false) $version = $n;
				if($version) break;
			}
		}
		if(!$version) $version = $config->installedAfter('2025-01-10') ? 3 : 1;
		if(!function_exists('idn_to_utf8')) $version = 2;
		if($version >= 3 && version_compare(phpversion(), '7.4.0', '<')) $version = 2;
		return $version;
	}

	/**
	 * @return Punycode
	 * 
	 */
	protected function punycode() {
		return new Punycode();
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
	 * ASCII-alphanumeric (a-z A-Z 0-9), hyphens, underscores and periods. Note that 
	 * filenames may contain mixed case (a-z A-Z) so if you require lowercase then
	 * run the return value through a `strtolower()` function. 
	 * 
	 * ~~~~~
	 * // outputs: FileName.jpg
	 * echo $sanitizer->filename('©®™FileName.jpg');
	 * 
	 * // outputs: c_r_tmfilename.jpg
	 * echo strtolower($sanitizer->filename('©®™filename.jpg', Sanitizer::translate));
	 * ~~~~~
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
			$tt = $this->getTextTools();
			$pathinfo = pathinfo($value);
			$extLen = $tt->strlen($pathinfo['extension']) + 1; // +1 includes period
			$basename = $tt->substr($pathinfo['filename'], 0, $maxLength - $extLen);
			$value = "$basename.$pathinfo[extension]";
		}
		
		$value = $this->name($value, $beautify, $maxLength, '_', array(
			'allowAdjacentExtras' => true, // language translation filenames require doubled "--" chars, others may too
		));
		
		while(strpos($value, '..') !== false) $value = str_replace('..', '', $value);
		
		return $value;
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
	 * @param bool|int $beautify Beautify the value? (default=false). Maybe any of the following:
	 * - `true` (bool): Beautify the individual page names in the path to remove redundant and trailing punctuation and more.
	 * - `false` (bool): Do not perform any conversion or attempt to make it more pretty, just sanitize (default). 
	 * - `Sanitizer::translate` (constant): Translate UTF-8 characters to visually similar ASCII (using InputfieldPageName module settings).
	 * - `Sanitizer::toAscii` (constant): Convert UTF-8 characters to punycode ASCII. 
	 * - `Sanitizer::toUTF8` (constant): Convert punycode ASCII to UTF-8. 
	 * - `Sanitizer::okUTF8` (constant): Allow UTF-8 characters to appear in path (implied if $config->pageNameCharset is 'UTF8'). 
	 * @param int $maxLength Maximum length (default=2048)
	 * @return string Sanitized path name
	 *
	 */
	public function pagePathName($value, $beautify = false, $maxLength = 2048) {

		$value = $this->string($value);
		if(!strlen($value)) return '';
		
		$extras = array('/', '-', '_', '.');
		$utf8 = $this->wire()->config->pageNameCharset === 'UTF8';

		if($beautify === self::toAscii && $utf8) {
			// convert UTF8 to punycode when applicable
			if(ctype_alnum(str_replace($extras, '', $value))) {
				// value needs no ascii conversion
			} else {
				// convert UTF8 to ascii value
				$parts = explode('/', $value);
				foreach($parts as $n => $part) {
					if(!strlen($part) || ctype_alnum($part)) continue;
					$b = (ctype_alnum(str_replace($extras, '', $part)) ? false : self::toAscii);
					$parts[$n] = $this->pageName($part, $b, $maxLength);
				}
				$value = implode('/', $parts);
			}

		} else if($beautify === self::okUTF8 && $utf8) {
			// UTF8 path
			$value = $this->pagePathNameUTF8($value);

		} else if($beautify === self::toUTF8 && $utf8 && strpos($value, 'xn-') !== false) {
			// ASCII to UTF8 conversion, when requested
			$parts = explode('/', $value);
			foreach($parts as $n => $part) {
				if(!strlen($part)) continue;
				$b = strpos($part, 'xn-') === 0 ? self::toUTF8 : false;
				$parts[$n] = $this->pageName($part, $b, $maxLength);
			}
			$value = implode('/', $parts);
			$value = $this->pagePathNameUTF8($value);

		} else {
			// ASCII path standard
			$b = $beautify;
			if($b === self::okUTF8 || $b === self::toUTF8 || $b === self::toAscii) $b = false;
			$parts = explode('/', $value);
			foreach($parts as $n => $part) {
				if(!strlen($part)) continue;
				$parts[$n] = $this->pageName($part, $b, $maxLength);
			}

			$value = implode('/', $parts);
		}

		// no double-slash, double-dot or slash-dot
		$reps = array('//' => '/', '..' => '.', '/.' => '/');
		foreach($reps as $find => $replace) {
			while(strpos($value, $find) !== false) {
				$value = str_replace(array_keys($reps), array_values($reps), $value);
			}
		}
		
		// truncate if needed
		if($maxLength && strlen($value) > $maxLength) {
			$slash = substr($value, -1) === '/';
			$value = substr($value, 0, $maxLength);
			$pos = strrpos($value, '/');
			if($pos) $value = substr($value, 0, $pos);
			if($slash) $value = rtrim($value, '/') . '/';
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
		if($this->wire()->config->pageNameCharset !== 'UTF8') return $this->pagePathName($value);
		$value = $this->string($value);
		if(!strlen($value)) return '';
		$parts = explode('/', $value);
		foreach($parts as $n => $part) {
			$parts[$n] = $this->pageName($part, self::okUTF8);
		}
		$value = implode('/', $parts);
		$disallow = array('..', '/.', './', '//');
		foreach($disallow as $x) {
			while(strpos($value, $x) !== false) {
				$value = str_replace($disallow, '', $value);
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
		$value = $this->alphanumeric($value, $beautify, $maxLength * 10);
		if(!ctype_alpha($value)) {
			$value = str_replace(str_split($this->digitASCII), '', $value);
			if(!ctype_alpha($value)) $value = preg_replace('/[^a-zA-Z]+/', '', $value);
		}	
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
	 * #pw-group-numbers
	 *
	 * @param string $value Value to sanitize
	 * @param int $maxLength Maximum length of returned value (default=1024)
	 * @return string
	 *
	 */
	public function digits($value, $maxLength = 1024) {
		$value = $this->nameFilter($value, array('_'), '_', false, $maxLength * 10);
		if(!ctype_digit($value)) {
			$value = str_replace(str_split('_' . $this->alphaASCII), '', $value);
			if(!ctype_digit($value)) $value = preg_replace('/[^\d]+/', '', $value);
		}
		if(strlen($value) > $maxLength) $value = substr($value, 0, $maxLength);
		return $value; 
	}

	/**
	 * Sanitize and validate an email address
	 *
	 * Returns valid email address, or blank string if it isn’t valid.
	 *
	 * #pw-group-strings
	 * #pw-group-validate
	 *
	 * @param string $value Email address to sanitize and validate.
	 * @param array $options All options require 3.0.208+
	 *  - `allowIDN` (bool|int): Allow internationalized domain names? (default=false)
	 *     Specify int 2 to also allow UTF-8 in local-part of email [SMTPUTF8] (i.e. `bøb`).
	 *  - `getASCII` (bool): Returns ASCII encoded version of email when host is IDN (default=false)
	 *     Does not require the allowIDN option since returned email host will be only ASCII.
	 *     Not meant to be combined with allowIDN=2 option since local-part of email does not ASCII encode.
	 *  - `getUTF8` (bool): Converts ASCII-encoded IDNs to UTF-8, when present (default=false)
	 *  - `checkDNS` (bool): Check that host part of email has a valid DNS record? (default=false)
	 *     Warning: this slows things down a lot and should not be used in time sensitive cases.
	 *  - `throw` (bool): Throw WireException on fail with details on why it failed (default=false)
	 * @return string Sanitized, valid email address, or blank string on failure.
	 *
	 */
	public function email($value, array $options = array()) {
		
		if(empty($value)) return '';
		
		$defaults = array(
			'allowIDN' => false, 
			'getASCII' => false, 
			'getUTF8' => false, 
			'checkDNS' => false, 
			'throw' => false, 
			'_debug' => false,
		);
		
		$options = array_merge($defaults, $options);
		$debug = $options['_debug'];
		
		if($options['throw']) {
			unset($options['throw']); 
			$value = $this->email($value, array_merge($options, array('_debug' => true)));
			if(!strpos($value, '@')) throw new WireException($value);
			return $value;
		}
		
		if($options['checkDNS']) {
			unset($options['checkDNS']);
			$valueASCII = $this->email($value, array_merge($options, array('getASCII' => true)));
			if(strpos($valueASCII, '@') === false) return $valueASCII; // fail
			list(,$host) = explode('@', $value, 2);
			$dns = dns_get_record($host, DNS_MX | DNS_A | DNS_CNAME | DNS_AAAA);
			if(empty($dns)) return ($debug ? 'Failed DNS check' : ''); 
			if($options['getASCII']) return $valueASCII;
			return $this->email($value, $options);
		}
		
		$value = trim(trim((string) $value), '.@');
		
		if(!strlen($value)) return ($debug ? 'Trimmed value is empty' : '');
		if(!strpos($value, '@')) return ($debug ? 'Missing at symbol' : '');
		if(strpos($value, ' ')) $value = str_replace(' ', '', $value);
		
		if($options['getUTF8'] && strpos($value, 'xn-') !== false && function_exists('\idn_to_utf8')) {
			list($addr, $host) = explode('@', $value, 2);
			if(strpos($host, 'xn-') !== false) {
				$host = idn_to_utf8($host);
				if($host !== false) $value = "$addr@$host";
			}
		}

		if(filter_var($value, FILTER_VALIDATE_EMAIL)) return $value; // valid
		
		$pos = strpos($value, '<');
		if($pos !== false && strpos($value, '>') > $pos+3) {
			// John Smith <jsmith@domain.com> => jsmith@domain.com
			list(,$value) = explode('<', $value, 2);
			list($value,) = explode('>', $value, 2);
			return $this->email($value, $options);
		}
		
		// all following code for processing IDN emails
		if(!$options['allowIDN'] && !$options['getASCII']) return ($debug ? 'Invalid+allowIDN/getASCII=0' : '');
		if(preg_match('/^[-@_.a-z0-9]+$/i', $value)) return ($debug ? 'Invalid and not IDN' : '');
		
		$parts = explode('@', $value);
		if(count($parts) !== 2) return ($debug ? 'More than one at symbol' : '');

		$tt = $this->getTextTools();
		list($addr, $host) = $parts;
		if($tt->strlen($addr) > 64) return ($debug ? 'Local part exceeds 64 max length' : '');
		if($tt->strlen($host) > 255) return ($debug ? 'Host part exceeds 255 max length' : '');
		
		if(function_exists('\idn_to_ascii')) {
			// if email doesn't survive IDN conversions then not valid
			$email = $value;
			$hostASCII = idn_to_ascii($host);
			if($hostASCII === false) return ($debug ? 'Fail UTF8-to-ASCII' : '');
			$test = ($options['allowIDN'] === 2 ? 'bob' : $addr) . "@$hostASCII";
			if(!filter_var($test, FILTER_VALIDATE_EMAIL)) return ($debug ? 'Fail validate post IDN-to-ASCII' : '');
			$hostUTF8 = idn_to_utf8($hostASCII);
			if($hostUTF8 === false) return ($debug ? 'Fail IDN-to-UTF8 conversion' : '');
			$value = "$addr@$hostUTF8";
			if($email !== $value) return ($debug ? 'Modified by IDN conversion' : '');
			if($options['getASCII']) return "$addr@$hostASCII";
		} else if($options['getASCII']) {
			return ($debug ? 'getASCII requested and idn_to_ascii not available' : ''); 
		}
		
		$regex = // regex adapted from Validators::isEmail() in https://github.com/nette/utils/
			'@^' . 
				'("([ !#-[\]-~]*|\\\[ -~])+"|LOCAL+(\.LOCAL+)*)\@' . // local-part
				'([\dALPHA]([-\dALPHA]{0,61}[\dALPHA])?\.)+' . // domain
				'[ALPHA]([-\dALPHA]{0,17}[ALPHA])?' . // TLD
			'$@Di';
		
		$local = "-a-z\d!#$%&'*+/=?^_`{|}~" . ($options['allowIDN'] === 2 ? "\x80-\xFF" : '');
		$regex = str_replace('LOCAL', "[$local]", $regex); // // RFC5322 unquoted characters
		$regex = str_replace('ALPHA', "a-z\x80-\xFF", $regex); // superset of IDN
		
		if(!preg_match($regex, $value)) return ($debug ? 'Fail IDN regex' : ''); 
		
		return $value;
	}

	/**
	 * Returns a value that may be used in an email header 
	 * 
	 * This method is designed to prevent one email header from injecting into another. 
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value
	 * @param bool $headerName Sanitize a header name rather than header value? (default=false) Since 3.0.132
	 * @return string
	 *
	 */ 
	public function emailHeader($value, $headerName = false) {
		if(!is_string($value)) return '';
		$a = array("\n", "\r", "<CR>", "<LF>", "0x0A", "0x0D", "%0A", "%0D"); // newlines
		$value = trim(str_ireplace($a, ' ', stripslashes($value)));
		if($headerName) $value = trim(preg_replace('/[^-_a-zA-Z0-9]/', '-', trim($value, ':')), '-'); 
		return $value;
	}
	
	/**
	 * Return first word in given string
	 *
	 * #pw-group-strings
	 *
	 * @param string $value String containing one or more words
	 * @param array $options Options to adjust behavior: 
	 *  - `keepNumbers` (bool): Allow numbers as return value? (default=true)
	 *  - `keepNumberFormat` (bool): Keep minus/comma/period in numbers rather than splitting into words? Also requires keepNumbers==true. (default=false)
	 *  - `keepUnderscore` (bool): Keep underscores as part of words? (default=false)
	 *  - `keepHyphen` (bool): Keep hyphenated words? (default=false)
	 *  - `keepChars` (array): Specify any of these to also keep as part of words ['.', ',', ';', '/', '*', ':', '+', '<', '>', '_', '-' ] (default=[])
	 *  - `minWordLength` (int): Minimum word length (default=1)
	 *  - `maxWordLength` (int): Maximum word length (default=80)
	 *  - `maxWords` (int): Maximum words (default=1 or 99 if a seperator option is specified)
	 *  - `maxLength` (int): Maximum returned string length (default=1024)
	 *  - `stripTags` (bool): Strip markup tags so they don’t contribute to returned word? (default=true)
	 *  - `separator' (string): Merge multiple words into one word split by this character? (default='', disabled) 3.0.195+
	 *  - `ascii` (bool): Allow only ASCII word characters? (default=false)
	 *  - `beautify` (bool): Make ugly strings more pretty? This collapses and trims redundant separators (default=false)
	 * @return string
	 * @see Sanitizer::wordsArray()
	 * @since 3.0.162
	 *
	 */
	public function word($value, array $options = array()) {
		
		if(!is_string($value)) $value = $this->string($value);
		
		$separator = isset($options['separator']) ? $options['separator'] : null;
		$keepChars = isset($options['keepChars']) ? $options['keepChars'] : array();
		$maxLength = isset($options['maxLength']) ? (int) $options['maxLength'] : 1024;
		$minWordLength = isset($options['minWordLength']) ? $options['minWordLength'] : 1;
	
		if(empty($options['maxWords'])) $options['maxWords'] = $separator !== null ? 99 : 1;
		if(!empty($options['keepHyphen']) && !in_array('-', $keepChars)) $keepChars[] = '-';
		if(!empty($options['keepUnderscore']) && !in_array('_', $keepChars)) $keepChars[] = '_';
		
		$options['keepChars'] = $keepChars;

		$a = $this->wordsArray($value, $options);
		$count = count($a);
		if(!$count) return '';
		
		if($separator !== null && $count > 1) {
			$value = implode($separator, $a);
		} else {
			$value = reset($a); 
		}
		
		if(!empty($options['ascii'])) {
			$sep = $separator === null ? '' : $separator;
			$value = $this->nameFilter($value, $keepChars, $sep, Sanitizer::translate, $maxLength); 
		} else if($maxLength) {
			$length = $this->multibyteSupport ? mb_strlen($value) : strlen($value);
			if($length > $maxLength) {
				$value = $this->multibyteSupport ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
			}
		}
		
		if(!empty($options['beautify'])) {
			foreach($keepChars as $s) {
				while(strpos($value, "$s$s") !== false) $value = str_replace("$s$s", $s, $value);
			}
			$value = trim($value, implode('', $keepChars));
		}
		
		if($minWordLength > 1 && strlen($value) < $minWordLength) $value = '';
		
		return $value;
	}

	/**
	 * Given string return a new string containing only words
	 * 
	 * #pw-group-strings
	 * 
	 * @param $value
	 * @param array $options
	 *  - `separator` (string): String to use to separate words (default=' ')
	 *  - `ascii` (string): Only allow ASCII characters in words? (default=false)
	 *  - `keepUnderscore` (bool): Keep underscores as part of words? (default=false)
	 *  - `keepHyphen` (bool): Keep hyphenated words? (default=false)
	 *  - `keepChars` (array): Additional non word characters to keep (default=[]) 
	 *  - `maxWordLength` (int): Maximum word length (default=80)
	 *  - `minWordLength` (int): Minimum word length (default=1)
	 *  - `maxLength` (int): Maximum return value length (default=1024)
	 *  - `beautify` (bool): Make ugly strings more pretty? This collapses and trims redundant separators (default=true)
	 * @since 3.0.195
	 * @return string
	 * 
	 */
	public function words($value, array $options = array()) {
		
		$defaults = array(
			'ascii' => false,
			'separator' => ' ', 
			'keepHyphen' => true, 
			'keepUnderscore' => true, 
			'keepChars' => array(),
			'maxWordLength' => 255,
			'maxLength' => 1024, 
			'beautify' => true, 
		);
		
		$options = array_merge($defaults, $options);
		$value = $this->word($value, $options);
		
		return $value;
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
	 * - `stripMB4` (bool): strip emoji and other 4-byte UTF-8? (default=false).
	 * - `stripQuotes` (bool): strip out any "quote" or 'quote' characters? Specify true, or character to replace with. (default=false)
	 * - `stripSpace` (bool|string): strip whitespace? Specify true or character to replace whitespace with (default=false). Since 3.0.105
	 * - `reduceSpace` (bool|string): reduce consecutive whitespace to single? Specify true or character to reduce to (default=false). 
	 *    Note that the reduceSpace option is an alternative to the stripSpace option, they should not be used together. Since 3.0.105
	 * - `allowableTags` (string): markup tags that are allowed, if stripTags is true (use same format as for PHP's `strip_tags()` function.
	 * - `multiLine` (bool): allow multiple lines? if false, then $newlineReplacement below is applicable (default=false).
	 * - `convertEntities` (bool): convert HTML entities to equivalent character(s)? (default=false). Since 3.0.105
	 * - `newlineReplacement` (string): character to replace newlines with, OR specify boolean true to remove extra lines (default=" ").
	 * - `truncateTail` (bool): if truncate necessary for maxLength, truncate from end/tail? Use false to truncate head (default=true). Since 3.0.105
	 * - `inCharset` (string): input character set (default="UTF-8").
	 * - `outCharset` (string): output character set (default="UTF-8").
	 * @return string
	 * @see Sanitizer::textarea(), Sanitizer::line()
	 *
	 */
	public function text($value, $options = array()) {

		$defaultOptions = array(
			'maxLength' => 255, // maximum characters allowed, or 0=no max
			'maxBytes' => 0,  // maximum bytes allowed (0 = default, which is maxLength*4)
			'stripTags' => true, // strip markup tags
			'stripMB4' => false, // strip Emoji and 4-byte characters? 
			'stripQuotes' => false, // strip quote characters? Specify true, or character to replace them with
			'stripSpace' => false, // remove/replace whitespace? If yes, specify character to replace with, or true for blank
			'reduceSpace' => false, // reduce whitespace to single? If yes, specify character to replace with or true for ' '.
			'allowableTags' => '', // tags that are allowed, if stripTags is true (use same format as for PHP's strip_tags function)
			'multiLine' => false, // allow multiple lines? if false, then $newlineReplacement below is applicable
			'convertEntities' => false, // convert HTML entities to equivalent characters?
			'newlineReplacement' => ' ', // character to replace newlines with, OR specify boolean TRUE to remove extra lines
			'inCharset' => 'UTF-8', // input charset
			'outCharset' => 'UTF-8',  // output charset
			'truncateTail' => true, // if truncate necessary for maxLength, remove chars from tail? False to truncate from head.
			'trim' => true, // trim whitespace from beginning/end, or specify character(s) to trim, or false to disable
		);
		
		static $alwaysReplace = null;
		$truncated = false;
		$options = array_merge($defaultOptions, $options);
		if(isset($options['multiline'])) $options['multiLine'] = $options['multiline']; // common case error
		if(isset($options['maxlength'])) $options['maxLength'] = $options['maxlength']; // common case error
		if($options['maxLength'] < 0) $options['maxLength'] = 0;
		if($options['maxBytes'] < 0) $options['maxBytes'] = 0;
		
		if($alwaysReplace === null) {
			$alwaysReplace = array(
				html_entity_decode('&#8232;', ENT_QUOTES, 'UTF-8') => '', // line-seperator that is sometimes copy/pasted
			);
		}
		
		if($options['reduceSpace'] !== false && $options['stripSpace'] === false) {
			// if reduceSpace option is used then provide necessary value for stripSpace option
			$options['stripSpace'] = is_string($options['reduceSpace']) ? $options['reduceSpace'] : ' ';
		}
		
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

		if($options['stripTags']) {
			$value = strip_tags($value, $options['allowableTags']);
		}

		if($options['inCharset'] != $options['outCharset']) {
			$value = iconv($options['inCharset'], $options['outCharset'], $value);
		}
		
		if($options['convertEntities']) {
			$value = $this->unentities($value, true, $options['outCharset']); 
		}
		
		foreach($alwaysReplace as $find => $replace) {
			if(strpos($value, $find) === false) continue;
			$value = str_replace($find, $replace, $value);
		}

		if($options['stripSpace'] !== false) {
			$c = is_string($options['stripSpace']) ? $options['stripSpace'] : '';
			$allow = $options['multiLine'] ? array("\n") : array();
			$value = $this->removeWhitespace($value, array('replace' => $c, 'allow' => $allow));
		}

		if($options['stripMB4']) {
			$value = $this->removeMB4($value);
		}
		
		if($options['stripQuotes']) {
			$value = str_replace(array('"', "'"), (is_string($options['stripQuotes']) ? $options['stripQuotes'] : ''), $value);
		}
		
		if($options['trim']) {
			$value = is_string($options['trim']) ? trim($value, $options['trim']) : trim($value);
		}
		
		if($options['maxLength']) {
			if(empty($options['maxBytes'])) $options['maxBytes'] = $options['maxLength'] * 4;
			if($this->multibyteSupport) {
				if(mb_strlen($value, $options['outCharset']) > $options['maxLength']) {
					$truncated = true;
					if($options['truncateTail']) {
						$value = mb_substr($value, 0, $options['maxLength'], $options['outCharset']);
					} else {
						$value = mb_substr($value, -1 * $options['maxLength'], null, $options['outCharset']);
					}
				}
			} else {
				if(strlen($value) > $options['maxLength']) {
					$truncated = true;
					if($options['truncateTail']) {
						$value = substr($value, 0, $options['maxLength']);
					} else {
						$value = substr($value, -1 * $options['maxLength']); 
					}
				}
			}
		}

		if($options['maxBytes']) {
			$n = $options['maxBytes'];
			while(strlen($value) > $options['maxBytes']) {
				$truncated = true;
				$n--;
				if($this->multibyteSupport) {
					if($options['truncateTail']) {
						$value = mb_substr($value, 0, $n, $options['outCharset']);
					} else {
						$value = mb_substr($value, $n, null, $options['outCharset']); 
					}
				} else {
					if($options['truncateTail']) {
						$value = substr($value, 0, $n);
					} else {
						$value = substr($value, $n); 
					}
				}
			}
		}
		
		if($truncated && $options['trim']) {
			// secondary trim after truncation
			$value = is_string($options['trim']) ? trim($value, $options['trim']) : trim($value);
		}

		return $value;
	}

	/**
	 * Sanitize input string as multi-line text without HTML tags
	 * 
	 * - This sanitizer is useful for user-submitted text from a plain-text `<textarea>` field, 
	 *   or any other kind of string value that might have multiple-lines.
	 * 
	 * - Don’t use this sanitizer for values where you want to allow HTML (like rich text fields). 
	 *   For those values you should instead use the `$sanitizer->purify()` method. 
	 * 
	 * - If using returned value for front-end output, be sure to run it through `$sanitizer->entities()` first.
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value String value to sanitize
	 * @param array $options Options to modify default behavior
	 * - `maxLength` (int): maximum characters allowed, or 0=no max (default=16384 or 16kb).
	 * - `maxBytes` (int): maximum bytes allowed (default=0, which implies maxLength*4 or 64kb).
	 * - `stripTags` (bool): strip markup tags? (default=true).
	 * - `stripMB4` (bool): strip emoji and other 4-byte UTF-8? (default=false).
	 * - `stripIndents` (bool): Remove indents (space/tabs) at the beginning of lines? (default=false). Since 3.0.105
	 * - `reduceSpace` (bool|string): reduce consecutive whitespace to single? Specify true or character to reduce to (default=false). Since 3.0.105
	 * - `allowableTags` (string): markup tags that are allowed, if stripTags is true (use same format as for PHP's `strip_tags()` function.
	 * - `convertEntities` (bool): convert HTML entities to equivalent character(s)? (default=false). Since 3.0.105
	 * - `truncateTail` (bool): if truncate necessary for maxLength, truncate from end/tail? Use false to truncate head (default=true). Since 3.0.105
	 * - `allowCRLF` (bool): allow CR+LF newlines (i.e. "\r\n")? (default=false, which means "\r\n" is replaced with "\n"). 
	 * - `inCharset` (string): input character set (default="UTF-8").
	 * - `outCharset` (string): output character set (default="UTF-8").
	 * @return string
	 * @see Sanitizer::text(), Sanitizer::purify()
	 * 
	 *
	 */
	public function textarea($value, $options = array()) {
		
		if(!is_string($value)) $value = $this->string($value);

		if(!isset($options['multiLine'])) $options['multiLine'] = true; 	
		if(!isset($options['maxLength'])) $options['maxLength'] = 16384; 
		if(!isset($options['maxBytes'])) $options['maxBytes'] = $options['maxLength'] * 4; 
	
		// convert \r\n to just \n
		if(empty($options['allowCRLF']) && strpos($value, "\r\n") !== false) {
			$value = str_replace("\r\n", "\n", $value);
		}

		$value = $this->text($value, $options); 
		
		if(!empty($options['stripIndents'])) {
			$value = preg_replace('/^[ \t]+/m', '', $value); 
		}
		
		return $value;
	}

	/**
	 * Sanitize any string of text to single line, no HTML, and no specific max-length (unless given)
	 *
	 * This is the same as the text() sanitizer but does not impose a maximum character length (or
	 * byte length) unless given one in the `$maxLength` argument. This is useful in cases where the
	 * text sanitizer’s built in 255 character max length (1020 max bytes) is not enough, or when you
	 * want to specify a max length as part of the method arguments.
	 *
	 * Please note that like with the text sanitizer, the max length refers to a maximum number of
	 * characters, not bytes. The maxBytes is automatically set to the maxLength * 4, or can be
	 * specifically set via the `maxBytes` option.
	 *
	 * #pw-group-strings
	 *
	 * @param string $value String to sanitize
	 * @param int|array $maxLength Maximum length in characters, omit (0) for no max-length, or substitute $options array
	 * @param array $options Options to modify behavior, see text() sanitizer for all options.
	 * @return string
	 * @see Sanitizer::text(), Sanitizer::lines()
	 * @since 3.0.157
	 *
	 */
	public function line($value, $maxLength = 0, array $options = array()) {
		if(is_array($maxLength)) {
			$options = $maxLength;
			if(!isset($options['maxLength'])) $options['maxLength'] = 0;
		} else {
			$options['maxLength'] = $maxLength;
		}
		return $this->text($value, $options);
	}

	/**
	 * Sanitize input string as multi-line text, no HTML tags, and no specific max length (unless given)
	 * 
	 * This is the same as the textarea() sanitizer but does not impose a maximum character length (or
	 * byte length) unless given one in the `$maxLength` argument. This is useful in cases where the
	 * textarea sanitizer’s built in 16kb character max length (64kb max bytes) is not enough, or when you
	 * want to specify a max length as part of the method arguments.
	 *
	 * Please note that like with the textarea sanitizer, the max length refers to a maximum number of
	 * characters, not bytes. The maxBytes is automatically set to the maxLength * 4, or can be
	 * specifically set via the `maxBytes` option. 
	 *
	 * #pw-group-strings
	 *
	 * @param string $value String value to sanitize
	 * @param int|array $maxLength Maximum length in characters, omit (0) for no max-length, or substitute $options array
	 * @param array $options Options to modify behavior, see textarea() sanitizer for all options. 
	 * @return string
	 * @see Sanitizer::textarea(), Sanitizer::purify(), Sanitizer::line()
	 * @since 3.0.157
	 *
	 */
	public function lines($value, $maxLength = 0, $options = array()) {
		if(is_array($maxLength)) {
			$options = $maxLength;
			if(!isset($options['maxLength'])) $options['maxLength'] = 0;
		} else {
			$options['maxLength'] = $maxLength;
		}
		return $this->textarea($value, $options);
	}

	/**
	 * Convert a string containing markup or entities to be plain text
	 * 
	 * This is one implementation but there is also a better one that you may prefer with the
	 * `WireTextTools::markupToText()` method. Try both to determine which suits your needs
	 * best: 
	 * 
	 * ~~~~~
	 * $markup = '<html>a bunch of HTML here</html>';
	 * // try both to see what you prefer:
	 * $text1 = $sanitizer->markupToText($html);
	 * $text2 = $sanitizer->getTextTools()->markupToText(); 
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value String you want to convert
	 * @param array $options Options to modify default behavior: 
	 *   - `newline` (string): Character(s) to replace newlines with (default="\n").
	 *   - `separator` (string): Character(s) to separate HTML `<li>` items with (default="\n").
	 *   - `entities` (bool): Entity encode returned value? (default=false). 
	 *   - `trim` (string): Character(s) to trim from beginning and end of value (default=" -,:;|\n\t").
	 * @return string Converted string of text
	 * @see WireTextTools::markupToText(), Sanitizer::markupToLine()
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
		$value = $this->string($value);
		
		if(!is_string($newline) || !strlen($newline)) $newline = ' ';

		if(strpos($value, "\r") !== false) {
			// normalize newlines
			$value = str_replace(array("\r\n", "\r"), "\n", $value);
		}

		// remove entities
		$value = $this->unentities($value);

		if(strpos($value, '<') !== false) {
			// tag replacements before strip_tags()
			if(stripos($value, '</ul>') || stripos($value, '</ol>')) {
				$regex = '!<(?:/?(?:ul|ol)(?:>|\s[^><]*))>!i';
				$value = preg_replace($regex, '', $value);
			}
			if(stripos($value, '</p>') || stripos($value, '</h') || stripos($value, '</div>')) {
				$regex =
					'!<(?:' .
						'/?(?:p|h\d|div)(?:>|\s[^><]*)' .
						'|' .
						'(?:br[\s/]*)' .
					')>!is';
				$value = preg_replace($regex, $newline, $value);
			}
			if(stripos($value, '</li>')) {
				$value = preg_replace('!</li>\s*<li!is', "$options[separator]<li", $value);
			}
		}

		// replace single less than sign that's not accompanied with a greater than sign
		// to something that looks like it, but that strip_tags() won’t strip.
		// this is to prevent something like "5<10" from getting converted to "5"
		if(strpos($value, '<') !== false && strpos($value, '>') === false) {
			$value = preg_replace('/<([\w\d])/', '≺$1', $value);
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
		if(!isset($options['newline'])) $options['newline'] = " "; 
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
	 * #pw-group-validate
	 *
	 * @param string $value URL to validate
	 * @param bool|array $options Array of options to modify default behavior, including:
	 *  - `allowRelative` (boolean): Whether to allow relative URLs, i.e. those without domains (default=true).
	 *  - `allowIDN` (boolean): Whether to allow internationalized domain names (default=false).
	 *  - `allowQuerystring` (boolean): Whether to allow query strings (default=true).
	 *  - `allowSchemes` (array): Array of allowed schemes, lowercase (default=[] any).
	 *  - `disallowSchemes` (array): Array of disallowed schemes, lowercase (default=['file']).
	 *  - `requireScheme` (bool): Specify true to require a scheme in the URL, if one not present, it will be added to non-relative URLs (default=true).
	 *  - `convertEncoded` (boolean): Convert most encoded hex characters characters (i.e. “%2F”) to non-encoded? (default=true)
	 *  - `encodeSpace` (boolean): Encoded space to “%20” or allow “%20“ in URL? Only useful if convertEncoded is true. (default=false)
	 *  - `stripTags` (bool): Specify false to prevent tags from being stripped (default=true).
	 *  - `stripQuotes` (bool): Specify false to prevent quotes from being stripped (default=true).
	 *  - `maxLength` (int): Maximum length in bytes allowed for URLs (default=4096).
	 *  - `throw` (bool): Throw exceptions on invalid URLs (default=false).
	 * @return string Returns a valid URL or blank string if it can’t be made valid.
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
			'reduceScheme' => false, // reduce "scheme://" to "scheme:" in return value? (internal use only)
			'convertEncoded' => true, 
			'encodeSpace' => false, 
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
		if(is_string($scheme) && strlen($scheme)) {
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
			} else {
				if(strpos($value, '://') === false && stripos($value, "$_scheme:") === 0) {
					// URL is in "scheme:value" format
					if(!in_array($scheme, array('http', 'https', 'ftp', 'tel', 'mailto'))) {
						// add scheme in "scheme://" format temporarily so filter_var won’t throw it out
						$value = "$scheme://" . substr($value, strlen("$_scheme:"));
						$options['reduceScheme'] = true;
					}
				}
				if($_scheme !== $scheme) {
					$value = str_replace("$_scheme://", "$scheme://", $value); // lowercase scheme
				}
			}
		}

		// separate scheme+domain+path from query string temporarily
		if(strpos($value, '?') !== false) {
			list($domainPath, $queryString) = explode('?', $value, 2);
			if(!$options['allowQuerystring']) $queryString = '';
		} else {
			$domainPath = $value;
			$queryString = '';
		}

		$pathIsEncoded = $options['convertEncoded'] && strpos($domainPath, '%') !== false;
		$pathModifiedByFilter = filter_var($domainPath, FILTER_SANITIZE_URL) !== $domainPath;
		
		if($pathIsEncoded || $pathModifiedByFilter) {
			// the domain and/or path contains extended characters not supported by FILTER_SANITIZE_URL
			// Example: https://de.wikipedia.org/wiki/Linkshänder
			// OR it is already rawurlencode()'d
			// Example: https://de.wikipedia.org/wiki/Linksh%C3%A4nder
			// we convert the URL to be FILTER_SANITIZE_URL compatible
			// if already encoded, first remove encoding: 
			if($pathIsEncoded) $domainPath = rawurldecode($domainPath);
			// Next, encode it, for example: https%3A%2F%2Fde.wikipedia.org%2Fwiki%2FLinksh%C3%A4nder
			$domainPath = rawurlencode($domainPath);
			// restore characters allowed in domain/path
			$domainPath = str_replace(array('%2F', '%3A'), array('/', ':'), $domainPath);
			// restore value that is now FILTER_SANITIZE_URL compatible
			$pathIsEncoded = true;
		}
		
		$value = $domainPath . (strlen($queryString) ? "?$queryString" : "");

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
				$regex = '{^([^\s_.]+\.)?[^-_\s.][^\s_.]+\.([a-z]{2,6})([./:#]|$)}i';
				if($dotPos && $slashPos > $dotPos && preg_match($regex, $value, $matches)) {
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
		} else if($scheme !== 'tel') {
			// URL already has a scheme
			$value = $this->filterValidateURL($value, $options);
		}

		if($pathIsEncoded && strlen($value)) {
			// restore to non-encoded, UTF-8 version 
			if(strpos($value, '?') !== false) {
				list($domainPath, $queryString) = explode('?', $value);
			} else {
				$domainPath = $value;
				$queryString = '';
			}
			$domainPath = rawurldecode($domainPath);
			if(strpos($domainPath, '%') !== false) {
				// if any apparently encoded characters remain afer rawurldecode, remove them
				$domainPath = preg_replace('/%[0-9ABCDEF]{1,2}/i', '', $domainPath);
				$domainPath = str_replace('%', '', $domainPath);
			}
			$domainPath = $this->text($domainPath, $textOptions);
			$value = $domainPath . (strlen($queryString) ? "?$queryString" : "");
		}
		
		if($scheme === 'tel' && !preg_match('/^tel:\+?\d+$/', $value)) {
			// tel: scheme is not supported by filter_var 
			$value = str_replace(' ', '', $value);
			/** @noinspection PhpUnusedLocalVariableInspection */
			list($tel, $num) = explode(':', $value);
			$value = 'tel:';
			if(strpos($num, '+') === 0) $value .= '+';
			$value .= preg_replace('/[^\d]/', '', $num);
		}

		if(!strlen($value)) return '';
		
		if($options['stripTags']) {
			if(stripos($value, '%3') !== false) {
				$value = str_ireplace(array('%3C', '%3E'), array('!~!<', '>!~!'), $value); // convert encoded to placeholders to strip
				$value = strip_tags($value);
				$value = str_ireplace(array('!~!<', '>!~!', '!~!'), array('%3C', '%3E', ''), $value); // restore, in case valid/non-tag
			} else {
				$value = strip_tags($value);
			}
		}
		
		if($options['stripQuotes']) {
			$value = str_replace(array('"', "'", "%22", "%27"), '', $value);
		}
		
		if($options['encodeSpace'] && strpos($value, ' ')) {
			$value = str_replace(' ', '%20', $value);
		}
		
		if($options['reduceScheme']) {
			list($scheme, $value) = explode('://', $value, 2);
			$value = "$scheme:$value";
		}
		
		return $value;
	}

	/**
	 * URL with http or https scheme required
	 * 
	 * #pw-group-strings
	 * #pw-group-validate
	 * 
	 * @param string $value URL to validate
	 * @param array $options See the url() method for all options.
	 * @return string Returns valid URL or blank string if it cannot be made valid. 
	 * @since 3.0.129
	 * 
	 */
	public function httpUrl($value, $options = array()) {
		$options['requireScheme'] = true;
		$options['allowRelative'] = false;
		if(empty($options['allowSchemes'])) $options['allowSchemes'] = array('http', 'https'); 
		return $this->url($value, $options);
	}

	/**
	 * Implementation of PHP's FILTER_VALIDATE_URL with IDN and underscore support (will convert to valid)
	 *
	 * Example: http://трикотаж-леко.рф
	 *
	 * @param string $url
	 * @param array $options Specify ('allowIDN' => false) to disallow internationalized domain names
	 * @return string
	 *
	 */
	protected function filterValidateURL($url, array $options) {
	
		// placeholders are characters known to be rejected by FILTER_VALIDATE_URL that should not be
		$placeholders = array();
		
		if(strpos($url, '_') !== false && strpos(parse_url($url, PHP_URL_HOST), '_') !== false) {
			// hostname contains an underscore and FILTER_VALIDATE_URL does not support them in hostnames
			do {
				$placeholder = 'UNDER' . mt_rand() . 'SCORE';
			} while(strpos($url, $placeholder) !== false);
			$url = str_replace('_', $placeholder, $url);
			$placeholders[$placeholder] = '_';
		}
		
		$_url = $url;
		$url = filter_var($url, FILTER_VALIDATE_URL);
		if($url !== false && strlen($url)) {
			// if filter_var returns a URL, then we know there is no IDN present and we can exit now
			if(count($placeholders)) {
				$url = str_replace(array_keys($placeholders), array_values($placeholders), $url);
			}
			return $url;
		}

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
			$pc = function_exists("idn_to_ascii") ? false : $this->punycode();
			$domain = $pc ? $pc->encode($domain) : @idn_to_ascii($domain);
			if($domain === false || !strlen($domain)) return '';
			$url = $scheme . $domain . $rest;
			$url = filter_var($url, FILTER_VALIDATE_URL);
			if(strlen($url)) {
				// convert back to utf8 domain
				$domain = $pc ? $pc->decode($domain) : @idn_to_utf8($domain);
				if($domain === false) return '';
				$url = $scheme . $domain . $rest;
			}
		}
		
		if(count($placeholders)) {
			$url = str_replace(array_keys($placeholders), array_values($placeholders), $url); 
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
	 *
	 * // In 3.0.127 you can also provide an array for the $value argument
	 * $val = $sanitizer->selectorValue([ 'foo', 'bar', 'baz' ]); 
	 * echo $val; // outputs: foo|bar|baz
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string|array $value String value to sanitize (assumed to be UTF-8), 
	 *   or in 3.0.127+ you may use an array and it will be sanitized to an OR value string. 
	 * @param array|int $options Options to modify behavior. Note version 1 supports only `maxLength` and `useQuotes` options.
	 *   - `version` (int): Version 1 or 2 (default=2). Version 2 available in 3.0.156+. Note option is remembered between calls.
	 *   - `maxLength` (int): Maximum number of allowed characters (default=100). This may also be specified instead of $options array.
	 *   - `useQuotes` (bool): Allow selectorValue() function to add quotes if it deems them necessary? (default=true)
	 *   - All following options are only supported in version 2 (available in 3.0.156+): 
	 *   - `allowArray` (bool): Allow arrays to convert to OR-strings? If false, only 1st item in arrays is used. (default=true)
	 *   - `allowSpace` (bool): Allow spaces? False to remove or true to allow (default=true) 3.0.168+
	 *   - `operator` (string): Operator being used in selector, optionally apply for operator-specific filtering. 
	 *   - `emptyValue` (string): Value to return if selector reduced to blank. Optionally use this to return something 
	 *      that could never match, or return something for you to evaluate yourself, like boolean false. (default=blank string)
	 *   - `blacklist` (array): Additional characters you want to disallow. (default=[])
	 *   - `whitelist` (array): Characters that are in default blacklist that you still want to allow. (default=[])
	 *   - `quotelist` (array): Additional characters that should always trigger quoted value. (default=[])
	 *   - If an integer is specified for $options, it is assumed to be the maxLength value. 
	 * @return string|int|bool|mixed Value ready to be used as the value component in a selector string. 
	 *   Always returns string unless you specify something different for 'emptyValue' option.
	 * 
	 */
	public function selectorValue($value, $options = array()) {
		
		static $version = 2;
		
		if(is_int($options)) {
			$options = array('maxLength' => $options);
		} else if(!is_array($options)) {
			$options = array();
		}
		
		if(isset($options['version'])) $version = (int) $options['version'];
		
		return $version > 1 ? $this->selectorValueV2($value, $options) : $this->selectorValueV1($value, $options);
	}
	
	/**
	 * Sanitize selector value for advanced text search operator (#=)
	 * 
	 * The [advanced text search operator](https://processwire.com/docs/selectors/operators/#contains-advanced) 
	 * `#=` supports some characters that are typically excluded from selector values, so this method enables 
	 * you to prepare a selector value for use with it. This method should not be used for sanitizing any other 
	 * kinds of selector values. 
	 * 
	 * Characters that have meaning to the advanced text search operator include `+-*()"` and thus their 
	 * appearance in the `$value` argument is assumed to be a command rather than text to search for. Though 
	 * note that non-matching double quotes or parenthesis are removed. 
	 * 
	 * *Note: If double quotes are used in your selector value, this method will convert them to matching 
	 * parenthesis, i.e. `+"phrase"` gets converted to `+(phrase)`.*
	 * 
	 * #pw-group-strings
	 * 
	 * @param string|array $value
	 * @param array $options See options for Sanitizer::selectorValue() method
	 * @return bool|mixed|string
	 * @since 3.0.182
	 * @see Sanitizer::selectorValue()
	 * @see https://processwire.com/docs/selectors/operators/#contains-advanced
	 *
	 */
	public function selectorValueAdvanced($value, array $options = array()) {
		$options['operator'] = '#=';
		return $this->selectorValueV2($value, $options);
	}


	/**
	 * Wrapper for selectorValueV2() when it receives an array
	 * 
	 * @param array $value
	 * @param array $options See options for selectorValue()
	 * @return string Always returns string unless you specify something different for 'emptyValue'
	 * 
	 */
	protected function selectorValueArray(array $value, $options = array()) {
		$a = array();
		$allowArray = isset($options['allowArray']) ? $options['allowArray'] : true;
		if(count($value) < 2 || !$allowArray) {
			// if array has 1 or 0 items, or arrays not allowed, return only first item in array
			$value = reset($value);
			$value = $this->string($value);
			return $this->selectorValueV2($value, $options);
		}
		$options['useQuotes'] = true; // must be allowed to use quotes when needed in OR condition
		foreach($value as $v) {
			$v = $this->selectorValueV2($v, $options);
			if(!strlen($v)) $v = '""'; // required blank value in OR condition
			$a[] = $v;
		}
		return implode('|', $a);
	}

	/**
	 * Sanitize selector value (version 2, 3.0.156+)
	 * 
	 * This version is a little more thorough and has more options than version 1. 
	 *
	 * @param string|array $value
	 * @param array $options
	 * @return bool|mixed|string Always returns string unless you specify something different for 'emptyValue'
	 *
	 */
	protected function selectorValueV2($value, $options = array()) {
		
		// characters we remove from selector strings
		$blacklist = array(
			'"', "\\0", "\\", "`", "|", '=', '*', '%', '~', '^', '$', '#',
			'<', '>', '[', ']', '{', '}', "\r", "\n", "\t", 
		);
	
		// characters that trigger quotes around selector value
		$quotelist = array(
			"'", ",", "!", ":", ";", "(", ")", "*", "+", 
		);
	
		$defaults = array(
			'allowArray' => true, 
			'allowSpace' => true,
			'maxLength' => 100, 
			'maxBytes' => 400,
			'useQuotes' => true,
			'emptyValue' => '',
			'quoteEmpty' => false, 
			'operator' => '', 
			'whitelist' => array(),
			'blacklist' => $blacklist,
			'quotelist' => $quotelist, 
		);

		// if given an array, convert to an OR selector string
		if(is_array($value)) return $this->selectorValueArray($value, $options);

		// append rather than replace blacklist and quotelist
		if(!empty($options['blacklist'])) $options['blacklist'] = array_merge($blacklist, $options['blacklist']);
		if(!empty($options['quotelist'])) $options['quotelist'] = array_merge($quotelist, $options['quotelist']);
	
		// prepare options and settings
		$options = array_merge($defaults, $options);
		$useQuotes = $options['useQuotes'];
		$hadQuotes = false;
		$needsQuotes = false;
		$maxLength = $options['maxLength'];
		$maxBytes = $options['maxBytes'];
		$emptyValue = $options['emptyValue'];
		$blacklist = $options['blacklist'];
		$quotelist = $options['quotelist'];
		$op = $options['operator'];
		$trims = '+,'; // non-whitespace chars to trim from beginning and end
		
		if($emptyValue === '' && $options['quoteEmpty']) $emptyValue = '""';
		
		// identify any operator-specific blacklist items
		if($op && (strpos($op, '~') !== false || strpos($op, '*') !== false) || strpos($op, '#') !== false) {
			$blacklist[] = '@'; // @ not supported by fulltext match/against in InnoDB
			if($op === '#=') {
				// advanced search operator allows command characters
				foreach(array('*', '+', '(', ')') as $c) {
					$k = array_search($c, $blacklist);
					if($k !== false) unset($blacklist[$k]);
					$trims = str_replace($c, '', $trims);
				}
				$value = trim($value);	
				if(strpos($value, '+') === 0 || strpos($value, '-') === 0) $needsQuotes = true;
				if(strpos($value, '(') !== false || strpos($value, ')') !== false) {
					// if there aren't matching quantities of open/close parens then remove them
					if(substr_count($value, '(') !== substr_count($value, ')')) {
						$value = str_replace(array('(', ')'), ' ', $value);
					}
				}
				if(strpos($value, '"') !== false) {
					if(substr_count($value, '"') % 2 === 0) {
						// equal number of quotes, convert to parenthesis
						$value = preg_replace('/"([^"]+)"/s', '($1)', $value);
						$needsQuotes = true;
					}
					// remove any remaining/unmatched quotes
					$value = str_replace('"', ' ', $value);
				}
				if(!$needsQuotes && strpos($value, '(') !== false) $needsQuotes = true;
			}
		}
	
		if(count($options['whitelist'])) {
			// remove from blacklist that which is present in whitelist		
			$blacklist = array_diff($blacklist, $options['whitelist']);
			// add to quotelist that which is present in both whitelist and blacklist
			$quotelist = array_merge($quotelist, array_intersect($options['whitelist'], $options['blacklist'])); 
		}
	
		// ensure value is a string that is trimmed of whitespace
		if(!is_string($value)) $value = $this->string($value);
		$value = trim($value);
		if(!strlen($value)) return $emptyValue; 
	
		// check if value is already in quotes
		if($value[0] === '"' || $value[0] === "'") {
			$hadQuotes = substr($value, -1) === $value[0] ? $value[0] : false;
		}
	
		// replace any characters in the blacklist (and not in whitelist) with space
		$value = str_replace($blacklist, ' ', $value);
	
		// test if any of the above resulted in an empty value and exit early if so
		$value = trim($value);
		if(!strlen($value)) return $emptyValue;

		// remove other types of whitespace
		$whitespace = $this->getWhitespaceArray(false);
		$value = trim(str_replace($whitespace, ($options['allowSpace'] ? ' ' : ''), $value));
		if(!strlen($value)) return $emptyValue;

		if($value[0] == "'") { 
			// value starts with single quote/apostrophe
			if(substr($value, -1) === "'") {
				// value starts and ends with single quote/apostrophe, remove them
				$value = trim($value, "' "); 
			} else {
				// value only starts with single quote/apostrophe
				$value = ltrim($value, "' "); 
				// note: it’s okay if value ends with an apostrophe if it does not start with one
			}
		}

		// selector value is limited to a maximum length (in characters)
		if($maxLength > 0 && strlen($value) > $maxLength) {
			if($this->multibyteSupport) {
				if(mb_strlen($value) > $maxLength) {
					$value = mb_substr($value, 0, $maxLength, 'UTF-8');
				}
			} else {
				$value = substr($value, 0, $maxLength);
			}
		}
	
		// selector value limited by maximum bytes 
		if($maxBytes > 0 && strlen($value) > $maxBytes) { 
			if($this->multibyteSupport) {
				$len = mb_strlen($value);
				while(strlen($value) > $maxBytes) {
					$len--;
					$value = mb_substr($value, 0, $len);
				}
			} else {
				$value = substr($value, 0, $maxBytes);
			}	
		}

		// see if we can avoid the preg_match and do a quick filter
		if(!ctype_alnum(str_replace(array(',', ' ', '-', '_', '/', '.', "'"), '', $value))) {
			// value needs more filtering, replace all non-alphanumeric, non-single-quote and space chars
			// See: http://php.net/manual/en/regexp.reference.unicode.php
			// See: http://www.regular-expressions.info/unicode.html
			$value = preg_replace('/[^[:alnum:]\pL\pN\pP\pM\p{S} \'\/]/u', ' ', $value); 

			// replace multiple space characters in sequence with just 1
			$value = preg_replace('/\s\s+/u', ' ', $value); 
		}

		// reductions and replacements
		$reductions = array('..' => '.', './' => ' ', '  ' => ' ');
		foreach($reductions as $f => $r) {
			if(strpos($value, $f) === false) continue;
			if(in_array($f, $options['whitelist'])) continue;
			do {
				$value = str_replace($f, $r, $value);
			} while(strpos($value, $f) !== false);
		}
		
		$value = trim($value); // trim any kind of whitespace
		$value = trim($value, $trims); // chars to remove from begin and end 
		$value = trim($value); // in case whitespace introduced by above

		// RETURN NOW if quotes are disallowed or value is empty		
		if(!strlen($value)) return $emptyValue;
		if(!$useQuotes) {
			return $hadQuotes && strpos($value, $hadQuotes) === false ? "$hadQuotes$value$hadQuotes" : $value;
		}

		// if value started quoted, we keep it quoted, otherwise we determine if it needs them
		if(!$needsQuotes) $needsQuotes = $hadQuotes ? true : false;
	
		if(!$needsQuotes) {
			// see if any always-quote character triggers are present
			foreach($quotelist as $char) {
				if(strpos($value, $char) === false) continue;
				$needsQuotes = true;
				break;
			}
		}
		
		if(!$needsQuotes) {
			// check if string begins or ends with allowed chars that are non-alphanumeric, non-slash
			$a = substr($value, 0, 1);
			$b = substr($value, -1);
			if(!ctype_alnum($a) && $a !== '/') {
				// starts with non-alphanumeric character that is not a slash (not a beginning of path)
				$needsQuotes = true;
			} else if(!ctype_alnum($b) && $b !== '/') {
				// ends with non-alphanumeric character that is not a slash (not an ending of path)
				$needsQuotes = true;
			} else if($a === '/') {
				// if not a path then we prefer it quoted
				$needsQuotes = !ctype_alnum(str_replace(array('/', '-', '_', '.'), '', $value));
			}
		}
		
		if($needsQuotes) $value = '"' . $value . '"';
		
		return $value;
	}

	/**
	 * Sanitize selector value (original, version 1) 
	 * 
	 * @param $value
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function selectorValueV1($value, $options = array()) {

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

		// if given an array, convert to an OR selector string
		if(is_array($value)) {
			$a = array();
			foreach($value as $v) {
				$v = $this->selectorValue($v, $options);
				if($options['useQuotes'] && !strlen($v)) $v = '""';
				$a[] = $v;
			}
			return implode('|', $a);
		}

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
		
			// values with parenthesis should preferably be quoted
			if(strpos($value, '(') !== false || strpos($value, ')') !== false) $needsQuotes = true;

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
		$value = trim($value, '+,'); // chars to remove from begin and end 
		if(strpos($value, '!') !== false) $needsQuotes = true;

		if(!$needsQuotes && $options['useQuotes'] && strlen($value)) {
			$a = substr($value, 0, 1);
			$b = substr($value, -1);
			if((!ctype_alnum($a) && $a != '/') || (!ctype_alnum($b) && $b != '/')) $needsQuotes = true;
		}
		if($needsQuotes && $options['useQuotes']) $value = $quoteChar . $value . $quoteChar;
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
	 * Entity encode with support for [A]rrays and other non-string values
	 * 
	 * This is similar to the existing entities() method with the following differences:
	 * 
	 * - Array values that are strings are encoded recursively to any depth and array is returned. 
	 * - Associative array keys (strings) are entity encoded, integer keys are left as-is.
	 * - Objects that implement __toString() are converted to string and entity encoded. 
	 * - Objects that do not implement __toString() are converted to a class name.
	 * - If given an int, float, bool, array or string, that is also the type returned.
	 * 
	 * #pw-group-arrays
	 * #pw-group-strings
	 * 
	 * @param array|string|int|float|object|bool $value
	 * @param int $flags
	 * @param string $encoding
	 * @param bool $doubleEncode
	 * @return array|string|int|float|bool
	 * @since 3.0.194
	 * @see Sanitizer::entitiesA1(), Sanitizer::entities()
	 * 
	 */
	public function entitiesA($value, $flags = ENT_QUOTES, $encoding = 'UTF-8', $doubleEncode = true) {
		
		if(!is_array($value)) {
			if(is_string($value)) {
				// value will be encoded below
			} else if(is_object($value)) {
				$value = method_exists($value, '__toString') ? "$value" : get_class($value);
			} else if(is_int($value) || is_float($value) || is_bool($value)) {
				// leave int, float, bool values as they are
				return $value; 
			}
			return $this->entities($value, $flags, $encoding, $doubleEncode);
		}
		
		$a = array();
		
		foreach($value as $k => $v) {
			if(is_string($k)) $k = $this->entities($k, $flags, $encoding, $doubleEncode);
			if(isset($a[$k])) continue;
			$a[$k] = $this->entitiesA($v, $flags, $encoding, $doubleEncode);
		}
		
		return $a;
	}

	/**
	 * Same as entitiesA() but does not double encode
	 * 
	 * #pw-group-arrays
	 * #pw-group-strings
	 * 
	 * @param array|string|int|float|object|bool $value
	 * @param int $flags
	 * @param string $encoding
	 * @return array|string|int|float|bool
	 * @since 3.0.194
	 * @see Sanitizer::entitiesA(), Sanitizer::entities1()
	 * 
	 */
	public function entitiesA1($value, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
		return $this->entitiesA($value, $flags, $encoding, false);
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
			'allow' => array('a', 'strong', 'em', 'code', 's', 'span', 'u', 'small', 'i', 'br'),
			'disallow' => array(),
			'linkMarkup' => '<a href="{url}" rel="noopener noreferrer nofollow" target="_blank">{text}</a>',
			'escapableChars' => array('*', '[', ']', '(', ')', '`', '_', '~'), // for basic markdown or brackets modes
		);

		if($options === true || (is_int($options) && $options > 0)) $defaults['fullMarkdown'] = $options;
		if(!is_array($options)) $options = array();
		$options = array_merge($defaults, $options);
		$findReplace = array();
		$str = $this->string($str);

		if($options['fullMarkdown']) {
			// full markdown
			/** @var TextformatterMarkdownExtra $markdown */	
			$markdown = $this->wire()->modules->get('TextformatterMarkdownExtra');
			if(is_int($options['fullMarkdown'])) {
				$markdown->flavor = $options['fullMarkdown'];
			} else {
				$markdown->flavor = TextformatterMarkdownExtra::flavorParsedown;
			}
			$markdown->format($str);
			
		} else {
			// basic (inline) markdown
			
			if(strpos($str, '\\') !== false) {
				// allow certain escaped markdown characters to be ignored by our regexps i.e. "\*" or "\[", etc.
				$findReplace = $this->getTextTools()->findReplaceEscapeChars($str, $options['escapableChars']); 
			}

			$str = $this->entities($str, $options['flags'], $options['encoding'], $options['doubleEncode']);
			
			if(strpos($str, '](') && in_array('a', $options['allow']) && !in_array('a', $options['disallow'])) {
				// link
				$linkMarkup = str_replace(array('{url}', '{text}'), array('$2', '$1'), $options['linkMarkup']);
				$str = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', $linkMarkup, $str);
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

		if($options['allowBrackets']) {
			$str = $this->bracketTagsToHtml($str, $options);
		}
		
		if(count($findReplace)) {
			$str = str_replace(array_keys($findReplace), array_values($findReplace), $str);
		}
		
		return $str;
	}

	/**
	 * Convert HTML bracket tags [tag]...[/tag] to HTML - helper method for entitiesMarkdown()
	 * 
	 * @param string $str String containing bracket tags, should be entity encoded ahead of time
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function bracketTagsToHtml($str, array $options) {

		if(strpos($str, '[') === false || strpos($str, ']') === false) return $str;
		
		if(empty($options['allow'])) return $str;

		if(!isset($options['disallow'])) $options['disallow'] = array();

		// bracket tags that require no closing bracket
		$singletons = array('br', 'hr', 'wbr');
		foreach($singletons as $tag) {
			if(strpos($str, "[$tag") === false) continue;
			if(!in_array($tag, $options['allow'])) continue;
			if(in_array($tag, $options['disallow'])) continue;
			$str = str_replace(array("[$tag]", "[$tag/]", "[$tag /]"), "<$tag />", $str);
		}
	
		// all other bracket tags require a closing bracket
		if(!strpos($str, '[/')) return $str;
		
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
		
		return $str;
	}
	
	/**
	 * Remove entity encoded characters from a string. 
	 * 
	 * Wrapper for PHP's `html_entity_decode()` function that contains typical ProcessWire usage defaults.
	 *
	 * The arguments used here are identical to those for PHP’s (except `$flags` can be boolean true):  
	 * [html_entity_decode](http://www.php.net/manual/en/function.html-entity-decode.php) function.
	 * 
	 * For the `$flags` argument, specify boolean `true` if you want to perform a more comprehensive entity
	 * decode than what PHP does. That will make it convert all UTF-8 entities (including decimal and hex numbered
	 * entities), and it will remove any remaining entity sequences if the could not be converted, ensuring there
	 * are no entities possible in returned value. 
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to remove entities from
	 * @param int|bool $flags See PHP html_entity_decode function for flags, 
	 *   OR specify boolean true to convert all entities and remove any that cannot be converted (since 3.0.105). 
	 * @param string $encoding Encoding (default="UTF-8").
	 * @return string String with entities removed.
	 * @see Sanitizer::entities()
	 *
	 */
	public function unentities($str, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
		if(!is_string($str)) $str = $this->string($str);
		$str = html_entity_decode($str, ($flags === true ? ENT_QUOTES : $flags), $encoding);
		if($flags !== true || strpos($str, '&') === false) return $str;
		// flags is true and at least one "&" remains, so we are doing a full entity removal
		// first, replace common entities that can possibly remain
		$entities = array('&apos;' => "'");
		$str = str_ireplace(array_keys($entities), array_values($entities), $str);
		if(strpos($str, '&#') !== false && $this->multibyteSupport) {
			// manually convert decimal and hex entities (when possible)
			$str = preg_replace_callback('/(&#[0-9A-F]+;)/i', function($matches) use($encoding) {
				return mb_convert_encoding($matches[1], $encoding, "HTML-ENTITIES");
			}, $str);
		}
		if(strpos($str, '&') !== false) {
			// strip out any entities that remain
			$str = preg_replace('/&(?:#[0-9A-F]|[A-Z]+);/i', ' ', $str);
		}
		return $str;
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
		/** @var MarkupHTMLPurifier $purifier */
		$purifier = $this->wire()->modules->get('MarkupHTMLPurifier');
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
	public function removeNewlines($str, $replacement = ' ') {
		$str = $this->string($str);
		return str_replace(array("\r\n", "\r", "\n"), $replacement, $str);
	}

	/**
	 * Remove or replace all whitespace from string
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to remove whitespace from
	 * @param array|string $options Options to modify behavior, or specify string for `replace` option:
	 *  - `replace` (string): Character(s) to replace whitespace with (default='').
	 *  - `collapse` (bool): If using replace, collapse consecutive replace chars to single? (default=true)
	 *  - `trim` (bool): If using replace, trim it from beginning and end? (default=true)
	 *  - `html` (bool): Remove/replace HTML whitespace entities too? (default=true)
	 *  - `allow` (array): Array of whitespace characters that may remain. (default=[])
	 * @return string
	 * @since 3.0.105
	 * 
	 */
	public function removeWhitespace($str, $options = array()) {
		$defaults = array(
			'replace' => '',
			'collapse' => true,
			'trim' => true,
			'html' => true,
			'allow' => array(), 
		);
		if(!is_array($options)) {
			$defaults['replace'] = $options;
			$options = $defaults;
		} else {
			$options = array_merge($defaults, $options);
		}
		$str = $this->string($str);
		if($options['html'] && strpos($str, '&') === false) $options['html'] = false;
		$whitespace = $this->getWhitespaceArray($options['html']); 
		foreach($options['allow'] as $c) {
			$key = array_search($c, $whitespace); 
			if($key !== false) unset($whitespace[$key]);
		}
		$rep = $options['replace'];
		if($options['html']) {
			$str = str_ireplace($whitespace, $rep, $str);
		} else {
			$str = str_replace($whitespace, $rep, $str);
		}
		if(strlen($rep)) {
			if($options['collapse']) {
				while(strpos($str, "$rep$rep") !== false) {
					$str = str_replace("$rep$rep", $rep, $str);
				}
			}
			if($options['trim']) {
				$str = trim($str, $rep);
				if(count($options['allow'])) $str = trim($str, implode('', $options['allow']));
			}
		}
		return $str;
	}

	/**
	 * Reduce whitespace to minimum required to maintain intended separation
	 *
	 * This is a variation of the removeWhitespace() function that converts whitespace to be all of the same type
	 * and collapses all consequitive whitespace to single whitespace.
	 * 
	 * If `multiline` option is specified then newlines allowed to remain as-is. 
	 * 
	 * #pw-internal
	 *
	 * @param string $str
	 * @param array $options
	 *  - `replace` (string): Character(s) to replace whitespace with (default=' ' i.e. single space).
	 *  - `collapse` (bool): Collapse consecutive replace chars to single? (default=true)
	 *  - `trim` (bool): Trim allowed whitespace beginning and end? (default=true)
	 *  - `html` (bool): Remove/replace HTML whitespace entities too? (default=false)
	 *  - `allow` (array): Array of whitespace characters that may remain. (default=[" "])
	 *  - `multiline` (bool): Allow newlines? This adds "\n" to the allow list. (default=false)
	 * @return string
	 * @since 3.0.123
	 *
	 */
	public function reduceWhitespace($str, $options = array()) {
		$defaults = array(
			'replace' => ' ',
			'collapse' => true,
			'trim' => true,
			'html' => false,
			'allow' => array(' '),
			'multiline' => false,
		);
		$options = array_merge($defaults, $options); 
		return $this->normalizeWhitespace($str, $options);
	}

	/**
	 * Normalize whitespace in the string to be all of the same type
	 * 
	 * This for instance replaces all UTF-8 whitespace variants, tabs and newlines to a regular ASCII space. 
	 * If `multiline` option is specified then newlines allowed to remain as-is. 
	 * 
	 * This is a variation of the removeWhitespace() function that converts whitespace to be all of the same type
	 * rather than removing the whitespace. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $str
	 * @param array $options
	 *  - `replace` (string): Character(s) to replace whitespace with (default=' ' i.e. single space).
	 *  - `collapse` (bool): Cllapse consecutive replace chars to single? (default=false)
	 *  - `trim` (bool): Trim whitespace from beginning and end? (default=false)
	 *  - `html` (bool): Remove/replace HTML whitespace entities too? (default=false)
	 *  - `allow` (array): Array of whitespace characters that may remain. (default=[" "])
	 *  - `multiline` (bool): Allow newlines? This adds "\n" to the allow list. (default=false)
	 * @return string
	 * @since 3.0.123
	 * 
	 */
	public function normalizeWhitespace($str, $options = array()) {
		$defaults = array(
			'replace' => ' ',
			'collapse' => false, 
			'trim' => false, 
			'html' => false, 
			'allow' => array(' '), 
			'multiline' => false, 
		);
		$options = array_merge($defaults, $options);
		if(!$options['multiline'] && in_array("\n", $options['allow'])) {
			$options['multiline'] = true;
		}
		if($options['multiline']) {
			$options['allow'][] = "\n";
			if(strpos($str, "\r") !== false) $str = str_replace(array("\r\n", "\r"), "\n", $str);
		}
		$str = $this->removeWhitespace($str, $options);
		return $str; 
	}

	/**
	 * Trim off all known UTF-8 whitespace types (or given chars) from beginning and ending of string
	 * 
	 * Like PHP’s trim() but works with multibyte strings and recognizes all types of UTF-8 whitespace
	 * as well as HTML whitespace entities. This method also optionally accepts an array for $chars argument
	 * which enables you to trim out string sequences greater than one character long. 
	 * 
	 * If you do not need an extensive multibyte trim, use PHP’s trim() instead because this takes more overhead.
	 * PHP multibyte support (mb_string) is strongly recommended if using this function. 
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str
	 * @param string|array $chars Array or string of chars to trim, or omit (blank string) for all whitespace (includes UTF-8 and HTML-entity whitespace too). 
	 * @param string $method Trim method, one of "trim" (both), "rtrim" (right-only) or "ltrim" (left-only). Or just "t", "r", "l" is also fine. 3.0.168+
	 * @return string
	 * @since 3.0.124
	 * 
	 */
	public function trim($str, $chars = '', $method = 'trim') {

		$str = $this->string($str);
		$tt = $this->getTextTools();
		$len = $tt->strlen($str);
		
		if(!$len) return '';

		$method = strtoupper($method[0]);  // T, R or L
		$trims = array();
		$str2 = '';
		
		if(is_array($chars) && !count($chars)) $chars = '';

		// setup trim
		if($chars === '') {
			// default whitespace characters
			$trims = $this->getWhitespaceArray(true);
			// let PHP default whitespace trim run first
			switch($method) {
				case 'R': $str = rtrim($str); break;
				case 'L': $str = ltrim($str); break;
				default: $str = trim($str); break;
			}
			$str2 = $str; // remember what it looked like here in $str2
			
		} else {
			// user-specified characters
			if(is_array($chars)) {
				$trims = $chars;
			} else {
				for($n = 0; $n < $tt->strlen($chars); $n++) {
					$trim = $tt->substr($chars, $n, 1);
					$trimLen = $tt->strlen($trim);
					if($trimLen) $trims[] = $trim;
				}
			}
		}

		// begin trim
		do {
			$numRemovedStart = 0; // num removed from start
			$numRemovedEnd = 0; // num removed from end
			
			foreach($trims as $trimKey => $trim) {
				$trimPos = $tt->strpos($str, $trim);
		
				// if trim not present anywhere in string it can be removed from our trims list
				if($trimPos === false) {
					unset($trims[$trimKey]);
					continue;
				}
				
				// at this point we know the trim character is present somewhere in the string
				$trimLen = $tt->strlen($trim);
				
				// while this trim character matches at beginning of string, remove it (left trim)
				if($method !== 'R') {
					while($trimPos === 0) {
						$str = $tt->substr($str, $trimLen);
						$trimPos = $tt->strpos($str, $trim);
						$numRemovedStart++;
					}
				}
				
				// trim from end (right trim)
				if($trimPos > 0 && $method !== 'L') do {
					$x = 0; // qty removed only in this do/while iteration
					$trimPos = $tt->strrpos($str, $trim);
					if($trimPos === false) break;
					$strLen = $tt->strlen($str);
					if($trimPos + $trimLen >= $strLen) {
						$str = $tt->substr($str, 0, $trimPos);
						$numRemovedEnd++;
						$x++;
					}
				} while($x > 0);
			
				// if trim no longer present, remove it
				if($trimPos === false) unset($trims[$trimKey]); 
				
			} // foreach
			
			$strLen = $tt->strlen($str);
			
		} while($numRemovedStart + $numRemovedEnd > 0 && $strLen > 0);
	
		// if a default behavior trim and $str was modified by trimming UTF-8 or entities
		// whitespaces then follow-up with a regular PHP trim, just in case
		if($chars === '' && $str !== $str2) {
			switch($method) {
				case 'R': $str = rtrim($str); break;
				case 'L': $str = ltrim($str); break;
				default: $str = trim($str); break;
			}
		}
		
		return $str;
	}

	/**
	 * Get array of all characters (including UTF-8) that can be used as whitespace in strings
	 * 
	 * #pw-internal
	 * 
	 * @param bool|int $html Also include HTML entities that represent whitespace? false=no, true=both, 1=only-html (default=false)
	 * @return array
	 * @since 3.0.105
	 * 
	 */
	public function getWhitespaceArray($html = false) {
	
		static $whitespaceUTF8 = array();
		static $whitespaceHTML = array();
		
		if(empty($whitespaceUTF8)) {
			// json_decode can handle conversion of \u0000 sequences regardless of PHP version
			$whitespaceUTF8 = json_decode('["\u' . implode('","\u', $this->whitespaceUTF8) . '"]', true); 
		}
		
		if($html) {
			if(empty($whitespaceHTML)) {
				$whitespaceHTML = $this->whitespaceHTML;
				foreach($this->whitespaceUTF8 as $value) {
					$whitespaceHTML[] = "&#x$value;"; // hex entity
					$whitespaceHTML[] = "&#" . hexdec($value) . ';'; // decimal entity
				}
			}
			$whitespace = $html === 1 ? $whitespaceHTML : array_merge($whitespaceUTF8, $whitespaceHTML); 
		} else {
			$whitespace = $whitespaceUTF8;
		}
		
		return $whitespace;
	}
	
	/**
	 * Truncate string to given maximum length without breaking words
	 *
	 * This method can truncate between words, sentences, punctuation or blocks (like paragraphs).
	 * See the `type` option for details on how it should truncate. By default it truncates between
	 * words. Description of types:
	 *
	 * - word: truncate to closest word.
	 * - punctuation: truncate to closest punctuation within sentence.
	 * - sentence: truncate to closest sentence.
	 * - block: truncate to closest block of text (like a paragraph or headline).
	 *
	 * Note that if your specified `type` is something other than “word”, and it cannot be matched
	 * within the maxLength, then it will attempt a different type. For instance, if you specify
	 * “sentence” as the type, and it cannot match a sentence, it will try to match to “punctuation”
	 * instead. If it cannot match that, then it will attempt “word”.
	 *
	 * HTML will be stripped from returned string. If you want to keep some tags use the `keepTags` or `keepFormatTags`
	 * options to specify what tags are allowed to remain. The `keepFormatTags` option that, when true, will make it
	 * retain all HTML inline text formatting tags.
	 *
	 * ~~~~~~~
	 * // Truncate string to closest word within 150 characters
	 * $s = $sanitizer->truncate($str, 150);
	 *
	 * // Truncate string to closest sentence within 300 characters
	 * $s = $sanitizer->truncate($str, 300, 'sentence');
	 *
	 * // Truncate with options
	 * $s = $sanitizer->truncate($str, [
	 *   'type' => 'punctuation',
	 *   'maxLength' => 300,
	 *   'visible' => true,
	 *   'more' => '…'
	 * ]);
	 * ~~~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string $str String to truncate
	 * @param int|array $maxLength Maximum length of returned string, or specify $options array here.
	 * @param array|string $options Options array, or specify `type` option (string).
	 *  - `type` (string): Preferred truncation type of word, punctuation, sentence, or block. (default='word')
	 *       This is a “preferred type”, not an absolute one, because it will adjust to match what it can within your maxLength.
	 *  - `maxLength` (int): Max characters for truncation, used only if $options array substituted for $maxLength argument.
	 *  - `maximize` (bool): Include as much as possible within specified type and max-length? (default=true)
	 *       If you specify false for the maximize option, it will truncate to first word, puncutation, sentence or block.
	 *  - `visible` (bool): When true, invisible text (markup, entities, etc.) does not count towards string length. (default=false)
	 *  - `trim` (string): Characters to trim from returned string. (default=',;/ ')
	 *  - `noTrim` (string): Never trim these from end of returned string. (default=')]>}”»')
	 *  - `more` (string): Append this to truncated strings that do not end with sentence punctuation. (default='…')
	 *  - `keepTags` (array): HTML tags that should be kept in returned string. (default=[])
	 *  - `keepFormatTags` (bool): Keep HTML text-formatting tags? Simpler alternative to keepTags option. (default=false)
	 *  - `collapseLinesWith` (string): String to collapse lines with where the first is not punctuated. (default=' … ')
	 *  - `convertEntities` (bool): Convert HTML entities to non-entity characters? (default=false)
	 *  - `noEndSentence` (string): Strings that sentence may not end with, space-separated values (default='Mr. Mrs. …')
	 * @return string
	 * @since 3.0.101
	 *
	 */
	public function truncate($str, $maxLength = 300, $options = array()) {
		$str = $this->string($str);
		return $this->getTextTools()->truncate($str, $maxLength, $options);
	}

	/**
	 * Truncate string to given maximum length without breaking words and with no added visible extras
	 *
	 * This is a shortcut to the truncate() sanitizer, sanitizing to nearest word with the `more` option 
	 * disabled and the `collapseLinesWith` set to 1 space (rather than ellipsis).
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $str String to truncate
	 * @param int|array $maxLength Maximum allowed length in characters, or substitute $options argument here
	 * @param array $options See options for truncate() method or specify `type` option (word, punctuation, sentence, block).
	 * @return string
	 * @since 3.0.157
	 * 
	 */
	public function trunc($str, $maxLength = 300, $options = array()) {
		$str = $this->string($str);
		if(is_array($maxLength)) $options = $maxLength;
		if(!isset($options['type'])) $options['type'] = 'word';
		if(!isset($options['more'])) $options['more'] = '';
		if(!isset($options['collapseLinesWith'])) $options['collapseLinesWith'] = ' ';
		return $this->getTextTools()->truncate($str, $maxLength, $options);
	}

	/**
	 * Removes 4-byte UTF-8 characters (like emoji) that produce error with with MySQL regular “UTF8” encoding
	 * 
	 * Returns the same value type that it is given. If given something other than a string or array, it just
	 * returns it without modification. 
	 * 
	 * #pw-group-strings
	 * 
	 * @param string|array $value String or array containing strings
	 * @param array $options Options to modify behavior, 3.0.169+ only:
	 *  - `replaceWith` (string): Replace MB4+ characters with this character, may not be blank (default='�')
	 *  - `version` (int): Replacement method version (default=2)
	 * @return string|array
	 * 
	 */
	public function removeMB4($value, array $options = array()) {
		$defaults = array(
			'replaceWith' => "\xEF\xBF\xBD", // Default unicode replacement character: U+FFFD aka �
			'version' => 2, 
		);
		$options = array_merge($defaults, $options);
		if($options['replaceWith'] === '') $options['replaceWidth'] = $defaults['replaceWith'];
		if(is_array($value)) {
			if(!count($value)) return array();
			// process array recursively, looking for strings to convert
			foreach($value as $key => $val) {
				if(is_string($val) || is_array($val)) $value[$key] = $this->removeMB4($val, $options);
			}
		} else if(is_string($value)) {
			if($options['version'] >= 2) {
				$value = preg_replace('/[\x{10000}-\x{10FFFF}]/u', $options['replaceWith'], $value);
			} else {
				if(strlen($value) > 3 && max(array_map('ord', str_split($value))) >= 240) {
					// string contains 4-byte characters
					$regex =
						'!(?:' .
						'\xF0[\x90-\xBF][\x80-\xBF]{2}' .
						'|[\xF1-\xF3][\x80-\xBF]{3}' .
						'|\xF4[\x80-\x8F][\x80-\xBF]{2}' .
						')!s';
					$value = preg_replace($regex, $options['replaceWith'], $value);
				}
			}
		} else {
			// not a string or an array, leave as-is
		}
		return $value;
	}

	/**
	 * Convert string to be all hyphenated-lowercase (aka kabab-case, hyphen-case, dash-case, etc.)
	 * 
	 * For example, "Hello World" or "helloWorld" becomes "hello-world".
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value
	 * @param array $options
	 *  - `hyphen` (string): Character to use as the hyphen (default='-')
	 *  - `allow` (string): Characters to allow or range of characters to allow, for placement in regex (default='a-z0-9').
	 *  - `allowUnderscore` (bool): Allow underscores? (default=false)
	 * @return string
	 * 
	 */
	public function hyphenCase($value, array $options = array()) {
		
		$defaults = array(
			'hyphen' => '-', 
			'allow' => 'a-z0-9', 
			'allowUnderscore' => false,
		);

		$options = array_merge($defaults, $options);
		$value = $this->string($value);
		$hyphen = $options['hyphen'];
	
		// if value is empty then exit now
		if(!strlen($value)) return '';
		
		if($options['allowUnderscore']) $options['allow'] .= '_';
	
		// check if value is already in the right format, and return it if so
		if(strtolower($value) === $value) {
			if($options['allow'] === $defaults['allow']) {
				if(ctype_alnum(str_replace($hyphen, '', $value))) return $value;
			} else {
				if(preg_match('/^[' . $hyphen . $options['allow'] . ']+$/', $value)) return $value;
			}
		}
		
		// don’t allow apostrophes to be separators
		$value = str_replace(array("'", "’"), '', $value);
		// some initial whitespace conversions to reduce workload on preg_replace
		$value = str_replace(array(" ", "\r", "\n", "\t"), $hyphen, $value);	
		// convert everything not allowed to hyphens
		$value = preg_replace('/[^' . $options['allow'] . ']+/i', $hyphen, $value);
		// convert camel case to hyphenated
		$value = preg_replace('/([[:lower:]])([[:upper:]])/', '$1' . $hyphen . '$2', $value);
		// prevent doubled hyphens
		$value = preg_replace('/' . $hyphen . $hyphen . '+/', $hyphen, $value);
		
		if($options['allowUnderscore']) {
			$value = str_replace(array('-_', '_-'), '_', $value);
		}
		
		return strtolower(trim($value, $hyphen)); 
	}
	
	/**
	 * Alias of hyphenCase()
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value
	 * @param array $options See hyphenCase()
	 * @return string
	 *
	 */
	public function kebabCase($value, array $options = array()) {
		return $this->hyphenCase($value, $options);
	}

	/**
	 * Convert string to be all snake_case (lowercase and underscores)
	 *
	 * For example, "Hello World" or "hello-world" becomes "hello_world".
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value
	 * @param array $options
	 *  - `allow` (string): Characters to allow or range of characters to allow, for placement in regex (default='a-z0-9').
	 *  - `hyphen` (string): Character to use as the hyphen (default='-')
	 * @return string
	 *
	 */
	public function snakeCase($value, array $options = array()) {
		$options['hyphen'] = '_';
		return $this->hyphenCase($value, $options);
	}

	/**
	 * Convert string to be all camelCase
	 * 
	 * For example, "Hello World" becomes "helloWorld" or "foo-bar-baz" becomes "fooBarBaz".
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value
	 * @param array $options
	 *  - `allow` (string): Characters to allow or range of characters to allow, for placement in regex (default='a-zA-Z0-9').
	 *  - `allowUnderscore` (bool): Allow underscore characters? (default=false)
	 *  - `startLowercase` (bool): Always start return value with lowercase character? (default=true)
	 *  - `startNumber` (bool): Allow return value to begin with a number? (default=false)
	 * @return string
	 * 
	 */
	public function camelCase($value, array $options = array()) {
		
		$defaults = array(
			'allow' => 'a-zA-Z0-9',
			'allowUnderscore' => false, 
			'startLowercase' => true, 
			'startNumber' => false, 
		);
		
		$options = array_merge($defaults, $options);
		$value = $this->string($value);
		$allow = $options['allow'] . ($options['allowUnderscore'] ? '_' : ''); 
		$needsWork = true;
		
		if($allow === $defaults['allow']) {
			if(ctype_alnum($value)) $needsWork = false;
		} else {
			if(preg_match('/^[' . $allow . ']+$/', $value)) $needsWork = false;
		}
	
		if($needsWork) {
			$value = preg_replace('/([^' . $allow . ' ]+)([' . $allow . ']+)/', '$1 $2', $value);
			$value = preg_replace('/[^' . $allow . ' ]+/', '', $value);

			$parts = explode(' ', $value);
			$value = '';

			foreach($parts as $n => $part) {
				if(empty($part)) continue;
				$value .= $n ? ucfirst($part) : $part;
			}
		}
		
		if($options['startLowercase'] && isset($value[0])) {
			$value[0] = strtolower($value[0]);
		}
		
		if(!$options['startNumber']) {
			$value = ltrim($value, $this->digitASCII); 
		}
		
		return $value;
	}

	/**
	 * Convert string to PascalCase (like camelCase, but first letter always uppercase)
	 * 
	 * For example, "hello world" becomes "HelloWorld" or "foo-bar-baz" becomes "FooBarBaz".
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value
	 * @param array $options See options for camelCase() method
	 * @return string
	 * 
	 */
	public function pascalCase($value, array $options = array()) {
		$options['startLowercase'] = false;
		$value = $this->camelCase($value, $options);
		return ucfirst($value);
	}
	
	/**
	 * Sanitize string value to have only the given characters 
	 * 
	 * You must provide a string of allowed characters in the `$allow` argument. If not provided then 
	 * the only [ a-z A-Z 0-9 ] are allowed. You may optionally specify `[alpha]` to refer to any 
	 * ASCII alphabet character, or `[digit]` to refer to any digit. 
	 * 
	 * ~~~~~
	 * echo $sanitizer->chars('foo123barBaz456', 'barz1'); // Outputs: 1baraz
	 * echo $sanitizer->chars('(800) 555-1234', '[digit]', '.');  // Outputs: 800.555.1234
	 * echo $sanitizer->chars('Decatur, GA 30030', '[alpha]', '-'); // Outputs: Decatur-GA
	 * echo $sanitizer->chars('Decatur, GA 30030', '[alpha][digit]', '-'); // Outputs: Decatur-GA-30030
	 * ~~~~~
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value Value to sanitize
	 * @param string|array $allow Allowed characters string. If omitted then only alphanumeric [ a-z A-Z 0-9 ] are allowed.
	 *  Use shortcut `[alpha]` to refer to any “a-z A-Z” char or `[digit]` to refer to any digit. 
	 * @param string $replacement Replace disallowed chars with this char or string, or omit for blank. (default='')
	 * @param bool $collapse Collapse multiple $replacement chars to one and trim from return value? (default=true)
	 * @param bool|null $mb Specify bool to force use of multibyte on or off, or omit to auto-detect. (default=null)
	 * @return string
	 * @since 3.0.126
	 *
	 */
	public function chars($value, $allow = '', $replacement = '', $collapse = true, $mb = null) {

		$value = $this->string($value);
		
		if(is_array($allow)) $allow = implode('', $allow);
		
		if(!strlen($allow)) {
			$allow = $this->alphaASCII . $this->digitASCII;
		} else {
			if(stripos($allow, '[alpha]') !== false) $allow = str_ireplace('[alpha]', $this->alphaASCII, $allow);
			if(stripos($allow, '[digit]') !== false) $allow = str_ireplace('[digit]', $this->digitASCII, $allow);
		}
		if($mb === null) $mb = $this->multibyteSupport ? !mb_check_encoding($allow . $value, 'ASCII') : false;
		
		$result = '';
		$lastChar = '';
		$length = $mb ? mb_strlen($value) : strlen($value);
		$hasReplacement = false;
		
		for($n = 0; $n < $length; $n++) {
			if($mb) {
				$char = mb_substr($value, $n, 1);
				$ok = mb_strpos($allow, $char) !== false; 
			} else {
				$char = $value[$n];
				$ok = strpos($allow, $char) !== false;
			}
			if($collapse && $char === $replacement) {
				$hasReplacement = true;
				if($char === $lastChar) continue;
			}
			if($ok) {
				$result .= $char;
				$lastChar = $char;
			} else if($replacement !== '') {
				if(!$collapse || $replacement !== $lastChar) $result .= $replacement;
				$lastChar = $replacement;
				$hasReplacement = true;
			}
		}
		
		if($collapse && $hasReplacement && $replacement !== '') {
			$result = $mb ? $this->trim($result, $replacement) : trim($result, $replacement); 
		}
		
		return $result;
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
		if(is_string($value)) {
			if($sanitizer === null) return $value;
		} else if(is_object($value)) {
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
		} else {
			$value = (string) $value;
		}
		if($sanitizer && is_string($sanitizer)) { 
			if(method_exists($this, $sanitizer) || method_exists($this, "___$sanitizer")) {
				$value = $this->$sanitizer($value);
				if(!is_string($value)) $value = (string) $value;
			}
		}
		return $value;
	}

	/**
	 * Sanitize a date or date/time string, making sure it is valid, and return it
	 *
	 * - If no date $format is specified, date will be returned as a unix timestamp.
	 * - If given date in invalid format and can’t be made valid, or date is empty, NULL will be returned.
	 * - If $value is an integer or string of all numbers, it is always assumed to be a unix timestamp.
	 * - If $format and “strict” option specified, date will also validate for format and no out-of-bounds values will be converted.
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
	 *  - `strict` (bool): Force dates that don’t match given $format, or out of bounds, to fail. Requires $format. (default=false)
	 * @return string|int|null
	 *
	 */
	public function date($value, $format = null, array $options = array()) {
		$defaults = array(
			'returnFormat' => $format, // date format to return in, if different from $format
			'min' => '', // Minimum date allowed (in $dateFormat format, or a unix timestamp) 
			'max' => '', // Maximum date allowed (in $dateFormat format, or a unix timestamp)
			'default' => null, // Default value, if date didn't resolve
			'strict' => false,
		);
		$options = array_merge($defaults, $options);
		$datetime = $this->wire()->datetime;
		$iso8601 = 'Y-m-d H:i:s';
		$_value = trim($this->string($value)); // original value string
		if(empty($value) && !is_int($value) && !strlen("$value")) return $options['default'];
		if(!is_string($value) && !is_int($value)) $value = $this->string($value);
		if(ctype_digit("$value")) {
			// value is in unix timestamp format
			// make sure it resolves to a valid date
			$value = strtotime(date($iso8601, (int) $value));
		} else {
			/** @var WireDateTime $datetime */
			$value = $datetime->stringToTimestamp($value, $format); 
		}
		// value is now a unix timestamp
		if($value === false) return null;
		// if format is provided and in strict mode, validate for the format and bounds
		if($format && $options['strict']) {
			$test = $datetime->date($format, $value);
			if($test !== $_value) return null;
		}
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
		return ($value === null || $value === false) ? null : $value;
	}

	/**
	 * Sanitize as language textdomain
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value
	 * @return string
	 * @since 3.0.181
	 * 
	 */
	public function textdomain($value) {
		
		$value = $this->line($value, 1024); 
		$value = trim(strtolower($value));
		$slash = false;
		$dot = false;
		
		if(!strlen($value)) return $value;
		
		if(strpos($value, '\\') !== false) {
			$value = str_replace('\\', '/', $value);
		}
		
		if(strpos($value, '/') !== false) {
			$slash = true;
			$config = $this->wire()->config;
			$value = str_replace(ltrim($config->paths->root, '/'), '', $value);
			$value = trim($value, '/');
		}
		
		if(strpos($value, '.') !== false) {
			$dot = true;
			while(strpos($value, '..') !== false) {
				$value = str_replace('..', '.', $value);
			}
		}

		if($dot) {
			$value = str_replace(array('/.', './', '-.', '.-'), array('/', '/', '.', '.'), $value);
		}
		
		if($slash || $dot) {
			$value = str_replace(array('/', '.'), array('--', '-'), $value);
		}
		
		while(strpos($value, '---') !== false) {
			$value = str_replace('---', '--', $value);
		}
		
		if(!ctype_alnum(str_replace(array('-', '_'), '', $value))) {
			$value = preg_replace('/[^-_a-z0-9]/', '_', $value);
		}
		
		return trim($value, '-');
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
	 * Sanitize value to be within the given min and max range
	 * 
	 * If float or decimal string specified for $min or $max arguments, return value will be a float,
	 * otherwise an integer is returned.
	 * 
	 * ~~~~~
	 * $n = 10;
	 * $sanitizer->range($n, 100, 200); // returns 100
	 * $sanitizer->range($n, 0, 1.0); // returns 1.0
	 * $sanitizer->range($n, 1.1, 100.5); // returns 10.0
	 * ~~~~~
	 *
	 * #pw-group-numbers
	 *
	 * @param int|float|string $value
	 * @param int|float|string|null $min Minimum allowed value or null for no minimum (default=null)
	 * @param int|float|string|null $max Maximum allowed value or null for no maximum (default=null)
	 * @return int|float
	 * @since 3.0.125
	 *
	 */
	public function range($value, $min = null, $max = null) {
		if(is_string($min)) $min = ctype_digit($min) ? (float) $min : (int) $min;
		if(is_string($max)) $max = ctype_digit($max) ? (float) $max : (int) $max;
		$value = is_float($min) || is_float($max) ? (float) $value : (int) $value;
		if($min === null) {
			$min = is_float($value) && defined('PHP_FLOAT_MIN') ? constant('PHP_FLOAT_MIN') : PHP_INT_MIN;
		}
		if($max === null) {
			$max = is_float($value) && defined('PHP_FLOAT_MAX') ? constant('PHP_FLOAT_MAX') : PHP_INT_MAX;
		}
		if($min > $max) list($min, $max) = array($max, $min); // swap args if necessary
		if($value < $min) {
			$value = $min;
		} else if($value > $max) {
			$value = $max;
		}
		return $value;
	}

	/**
	 * Sanitize to have a minimum value
	 * 
	 * If float or decimal string specified for $min argument, return value will be a float, 
	 * otherwise an integer is returned.
	 * 
	 * ~~~~~
	 * $n = 10;
	 * $sanitizer->min(100); // returns 100
	 * $sanitizer->min(5); // returns 10
	 * $sanitizer->min(1.0); // returns 10.0
	 * ~~~~~
	 * 
	 * #pw-group-numbers
	 * 
	 * @param int|float|string $value
	 * @param int|float|string $min Minimum allowed value 
	 * @return int|float
	 * @since 3.0.125
	 * @see Sanitizer::max()
	 * 
	 */
	public function min($value, $min = PHP_INT_MIN) {
		return $this->range($value, $min, null); 
	}

	/**
	 * Sanitize to have a maximuim value
	 *
	 * If float or decimal string specified for $max argument, return value will be a float,
	 * otherwise an integer is returned.
	 * 
	 * ~~~~~
	 * $n = 10;
	 * $sanitizer->max(5); // returns 5
	 * $sanitizer->max(100); // returns 10
	 * $sanitizer->max(100.0); // returns 10.0
	 * ~~~~~
	 *
	 * #pw-group-numbers
	 *
	 * @param int|float|string $value
	 * @param int|float|string $max Maximum allowed value
	 * @return int|float
	 * @since 3.0.125
	 * @see Sanitizer::min()
	 *
	 */
	public function max($value, $max = PHP_INT_MAX) {
		return $this->range($value, null, $max); 
	}

	/**
	 * Sanitize to floating point value
	 * 
	 * Values for `getString` argument:
	 * 
	 * - `false` (bool): do not return string value (default). 3.0.171+
	 * - `true` (bool): locale aware floating point number string. 3.0.171+
	 * - `f` (string): locale aware floating point number string (same as true). 3.0.193+
	 * - `F` (string): non-locale aware floating point number string. 3.0.193+
	 * - `e` (string): lowercase scientific notation (e.g. 1.2e+2). 3.0.193+
	 * - `E` (string): uppercase scientific notation (e.g. 1.2E+2). 3.0.193+
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
	 *  - `getString (bool|string): Return a string rather than float value? 3.0.171+ (default=false). See value options in method description. 
	 * @return float|string
	 * 
	 */
	public function float($value, array $options = array()) {
		
		$defaults = array(
			'precision' => null, // Optional number of digits to round to 
			'mode' => PHP_ROUND_HALF_UP, // Mode to use for rounding precision (default=PHP_ROUND_HALF_UP)
			'blankValue' => 0.0, // Value to return (whether float or non-float) if provided $value is an empty non-float (default=0.0)
			'min' => null, // Minimum allowed value (excluding blankValue)
			'max' => null, // Maximum allowed value (excluding blankValue)
			'getString' => false, // Return a string rather than float value? bool or f, F, e, E
		);
		
		$options = array_merge($defaults, $options);
	
		if($value === null || $value === false) return $options['blankValue'];
		if(!is_float($value) && !is_string($value)) $value = $this->string($value);
		$e = 0;

		if(is_string($value)) {
			
			$str = trim($value);
			$prepend = '';
			$append = '';
			
			$c = substr($str, 0, 1);
			while($c !== '' && $c !== '-' && $c !== '.' && $c !== ',' && !ctype_digit($c)) {
				// trim off leading non-number content like currency symbols, names, etc.
				$str = ltrim($str, $c);
				$c = substr($str, 0, 1);
			}
		
			if($c === '-') {
				$prepend = '-';
				$str = ltrim($str, '-');
			}

			if(stripos($str, 'E') && preg_match('/^([-]?[0-9., ]*\d)(E[-+]?\d+)/i', $str, $m)) {
				$str = $m[1];
				$append = $m[2];
				$e = ((int) ltrim($append, '-+eE')); 
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
				if(!ctype_digit("$value")) $value = preg_replace('/[^0-9]/', '', $str);
			}

			if($pos !== null) {
				$value =
					// part before dot
					preg_replace('/[^0-9]/', '', substr($str, 0, $pos)) . '.' .
					// part after dot
					preg_replace('/[^0-9]/', '', substr($str, $pos + 1));
			}

			$value = $prepend . $value . $append;
			if(!$options['getString']) $value = floatval($value);
			
		} else if(is_float($value)) {
			$str = strtoupper("$value"); 
			if(strpos($str, 'E')) $e = (int) ltrim(stristr("$str", 'E'), 'E-+'); 
		}	
		
		if($options['precision'] === null && $e) {
			$options['precision'] = $e;
			if(strpos("$value", '.') !== false && preg_match('!\.(\d+)!', $value, $m)) {
				$options['precision'] += strlen($m[1]);
			}
		}
		
		if(!$options['getString'] && !is_float($value)) $value = (float) $value;
		if(!is_null($options['min']) && ((float) $value) < ((float) $options['min'])) $value = $options['min'];
		if(!is_null($options['max']) && ((float) $value) > ((float) $options['max'])) $value = $options['max'];
		if(!is_null($options['precision'])) $value = round((float) $value, (int) $options['precision'], (int) $options['mode']);
		$value = (float) $value;
		
		if($options['getString']) {
			$f = $options['getString'];
			$f = is_string($f) && in_array($f, array('f', 'F', 'e', 'E')) ? $f : 'f';
			if($options['precision'] === null) {
				$value = stripos("$value", 'E') ? rtrim(sprintf("%.15$f", (float) $value), '0') : "$value";
			} else {
				$value = rtrim(sprintf("%.$options[precision]$f", (float) $value), '0');
			}
			$value = rtrim($value, '.');
		}
		
		return $value;
	}

	/***********************************************************************************************************************
	 * ARRAY SANITIZERS
	 * 
	 */

	/**
	 * Sanitize array or CSV string to array of values, optionally sanitized by given method
	 *
	 * If given a string, delimiter may be pipe ("|"), or comma (","), unless overridden with the `delimiter`
	 * or `delimiters` options. 
	 * 
	 * #pw-group-arrays
	 *
	 * @param array|string|mixed $value Accepts an array or CSV string. 
	 *   If given something else, it becomes first item in array.
	 * @param string|array $sanitizer Sanitizer method to apply to items in the array or omit/null for none,
	 *   or in 3.0.165+ optionally substitute the $options argument here instead (default=null).
	 * @param array $options Optional modifications to default behavior:
	 * 	- `maxItems` (int): Maximum items allowed in each array (default=0, which means no limit)
	 *  - `maxDepth` (int): Max nested array depth (default=0, which means no nesting allowed) Since 3.0.160
	 *  - `trim` (bool): Trim whitespace from front/back of each string item in array? (default=true) Since 3.0.190 
	 * 	- `sanitizer` (string): Optionally specify sanitizer for array values as option rather than argument (default='') Since 3.0.165
	 * 	- `keySanitizer` (string): Optionally sanitize associative array keys with this method (default='') Since 3.0.167
	 * 	- The following options are only used if the provided $value is a string: 
	 *  - `csv` (bool): Allow conversion of delimited string to array? (default=true) Since 3.0.165
	 * 	- `delimiter` (string): Single delimiter to use to identify CSV strings. Overrides the 'delimiters' option when specified (default=null)
	 * 	- `delimiters` (array): Delimiters to identify CSV strings. First found delimiter will be used, default=array("|", ",")
	 * 	- `enclosure` (string): Enclosure to use for CSV strings (default=double quote, i.e. `"`)
	 * 	- `escape` (string): Escape to use for CSV strings (default=backslash, i.e. "\\")
	 * @return array
	 * @throws WireException if an unknown $sanitizer method is given
	 *
	 */
	public function ___array($value, $sanitizer = null, array $options = array()) {
		
		static $depth = 0;
		
		$defaults = array(
			'maxItems' => 0,
			'maxDepth' => 0,
			'csv' => true,
			'delimiter' => null, 
			'delimiters' => array('|', ','),
			'enclosure' => '"',
			'escape' => "\\", 
			'trim' => true, 
			'sanitizer' => null, 
			'keySanitizer' => null,
		);
		
		if(is_array($sanitizer) && empty($options)) list($options, $sanitizer) = array($sanitizer, null);
		if(empty($sanitizer) && !empty($options['sanitizer'])) $sanitizer = $options['sanitizer'];
		
		$options = array_merge($defaults, $options);
		$clean = array();
		
		if($value === null) {
			return array();
			
		} else if(!is_array($value)) {
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
				if($options['trim']) $value = trim($value);
				if(!strlen($value)) {
					return array();
				} else if($options['csv']) {
					$hasDelimiter = null;
					$delimiters = is_null($options['delimiter']) ? $options['delimiters'] : array($options['delimiter']);
					foreach($delimiters as $delimiter) {
						if(strpos($value, $delimiter)) {
							$hasDelimiter = $delimiter;
							break;
						}
					}
					if($hasDelimiter !== null) {
						$value = str_getcsv($value, $hasDelimiter, $options['enclosure'], $options['escape']);
					} else {
						$value = array($value);
					}
				}
			}	
			if(!is_array($value)) {
				$value = array($value);
			}	
		}

		$depth++;
		foreach($value as $k => $v) {
			if(is_array($v)) {
				if($depth <= $options['maxDepth']) {
					// sanitize nested array recursively
					$value[$k] = $this->___array($v, $sanitizer, $options);
				} else {
					// remove nested array
					unset($value[$k]);
				}
			} else if(is_string($v)) {
				if($options['trim']) $value[$k] = trim($v);
			}
		}
		$depth--;
		
		if($options['maxItems'] && count($value) > $options['maxItems']) {
			$value = array_slice($value, 0, abs($options['maxItems']));	
		}
	
		$keySanitizer = $options['keySanitizer'];
		
		if($sanitizer || $keySanitizer) {
			foreach(array($sanitizer, $keySanitizer) as $method) {
				if($method && !method_exists($this, $method) && !method_exists($this, "___$method")) {
					throw new WireException("Unknown sanitizer method: $method");
				}
			}
			foreach($value as $k => $v) {
				if($keySanitizer && !is_int($k)) {
					$k = $this->$keySanitizer($k);
					if(!strlen($k)) continue;
				}
				if($options['maxDepth'] > 0 && is_array($v)) {
					$clean[$k] = $v; // array already sanitized by recursive call
				} else {
					$clean[$k] = $this->$sanitizer($v);
				}
			}
		} else {
			$clean = $value;
		}
		
		return $keySanitizer ? $clean : array_values($clean);
	}

	/**
	 * Simply sanitize value to array with no conversions
	 * 
	 * This is the same as the `array()` sanitizer except that it does not attempt to convert 
	 * delimited/csv strings to arrays. Meaning, a delimited string would simply become an array
	 * with the first item being that delimited string. 
	 * 
	 * #pw-group-arrays
	 * 
	 * @param mixed $value
	 * @param array $options
	 * 	- `maxItems` (int): Maximum items allowed in each array (default=0, which means no limit)
	 *  - `maxDepth` (int): Max nested array depth (default=0, which means no nesting allowed)
	 * 	- `sanitizer` (string): Optionally specify sanitizer method name to apply to items (default='')
	 * 	- `keySanitizer` (string): Optionally sanitize associative array keys with this method (default='') Since 3.0.167
	 * @return array
	 * @throws WireException
	 * @since 3.0.165
	 * 
	 */
	public function arrayVal($value, $options = array()) {
		$defaults = array(
			'maxItems' => 0, 
			'maxDepth' => 0,
			'sanitizer' => is_string($options) ? $options : null,
			'keySanitizer' => null,
			'csv' => false,
		);
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		return $this->___array($value, $options);
	}
	
	/**
	 * Sanitize array or CSV string to array of unsigned integers (or signed integers if specified $min is less than 0)
	 *
	 * If string specified, string delimiter may be comma (","), or pipe ("|"), or you may override with the 'delimiter' option.
	 * 
	 * #pw-group-arrays
	 * #pw-group-numbers
	 *
	 * @param array|string|mixed $value Accepts an array or CSV string. If given something else, it becomes first value in array.
	 * @param array|bool $options Optional options (see `Sanitizer::array()` and `Sanitizer::int()` methods for options), plus these two: 
	 * 	- `min` (int): Minimum allowed value (default=0)
	 * 	- `max` (int): Maximum allowed value (default=PHP_INT_MAX)
	 *  - `strict` (bool): Remove rather than convert any values that are not all digits or fall outside min/max range? (default=false) Since 3.0.157+
	 * 	- `maxItems` (int): Maximum items allowed in each array (default=0, which means no limit)
	 *  - `maxDepth` (int): Max nested array depth (default=0, which means no nesting allowed) Since 3.0.160
	 *  - You may specify boolean true for $options argument to use just the `strict` option. (3.0.157+)
	 * 	- The following options are only used if the provided $value is a string:
	 *  - `csv` (bool): Allow conversion of delimited string to array? (default=true) Since 3.0.165
	 * 	- `delimiter` (string): Single delimiter to use to identify CSV strings. Overrides the 'delimiters' option when specified (default=null)
	 * 	- `delimiters` (array): Delimiters to identify CSV strings. First found delimiter will be used, default=array("|", ",")
	 * 	- `enclosure` (string): Enclosure to use for CSV strings (default=double quote, i.e. `"`)
	 * @return array Array of integers
	 *
	 */
	public function intArray($value, $options = array()) {
		if(is_bool($options)) {
			$options = array('strict' => $options);
		} else if(!is_array($options)) {
			$options = array();
		}
		if(!is_array($value)) {
			$value = $this->___array($value, null, $options);
		}
		$clean = array();
		$strict = isset($options['strict']) ? $options['strict'] : false;
		foreach($value as $v) {
			if($strict) {
				$isInt = is_int($v);
				$isStr = !$isInt && is_string($v); 
				if(!$isInt && !$isStr) continue;
				if($isStr && !ctype_digit($v)) continue;
				if($v === '') continue;
				$vBefore = (int) $v;
				$vAfter = $this->int($v, $options);
				if($vBefore === $vAfter) $clean[] = $vAfter;
			} else {
				$clean[] = $this->int($v, $options);
			}
		}
		return $clean;
	}

	/**
	 * Sanitize array to be all unsigned integers with no conversions
	 * 
	 * This is the same as the `intArray()` method except for the following: 
	 * 
	 *  - The `csv` delimited string conversion option is disabled by default.
	 *  - The `strict` option default is true, meaning non-integer numbers or those outside allowed range 
	 *    are removed rather than converted. 
	 *
	 * #pw-group-arrays
	 * #pw-group-numbers
	 *
	 * @param array|string|mixed $value Accepts an array or CSV string. If given something else, it becomes first value in array.
	 * @param array|bool $options Options to modify behavior or specify bool for `strict` option:
	 * 	- `min` (int): Minimum allowed value (default=0)
	 * 	- `max` (int): Maximum allowed value (default=PHP_INT_MAX)
	 * 	- `maxItems` (int): Maximum items allowed in each array (default=0, which means no limit)
	 *  - `maxDepth` (int): Max nested array depth (default=0, which means no nesting allowed) Since 3.0.160
	 *  - `strict` (bool): Remove rather than convert any values that are not all digits or fall outside min/max range? (default=true) 
	 *     Note that this default for the strict option is different from the one on the intArray() method. 
	 * @return array Array of integers
	 * @since 3.0.165
	 *
	 */
	public function intArrayVal($value, $options = array()) {
		$defaults = array(
			'strict' => is_bool($options) ? $options : true,
			'csv' => false,
		);
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		return $this->intArray($value, $options);
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
	 * Given a potentially multi-dimensional array, return a flat 1-dimensional array
	 * 
	 * #pw-group-arrays
	 * 
	 * @param array $value
	 * @param array $options
	 *  - `preserveKeys` (bool): Preserve associative array keys where possible? (default=false)
	 *  - `maxDepth` (int): Max depth of nested arrays to flatten into value, after which they are discarded (default=0).
	 *     The default value of 0 removes any nested arrays, so specify 1 or higher to include them. 
	 * @return array
	 * @since 3.0.160
	 * 
	 */
	public function flatArray($value, $options = array()) {
		
		static $depth = 0;
		
		$defaults = array(
			'preserveKeys' => is_bool($options) ? $options : false,
			'maxDepth' => 0,
		);
		
		if(!is_array($value)) return array($value);
	
		$flat = array();
		$isFlat = true;
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$preserveKeys = $options['preserveKeys'];
		
		foreach($value as $val) {
			if(is_array($val)) $isFlat = false;
			if(!$isFlat) break;
		}	
		
		if($isFlat) return $preserveKeys ? $value : array_values($value);
		
		$depth++;
		
		foreach($value as $key => $val) {
			
			$hasStringKey = $preserveKeys && is_string($key);
			
			if(!is_array($val)) {
				// not an array value
				if($hasStringKey) {
					// associative key
					list($n, $kk) = array(0, $key);
					// this while loop likely is not needed
					while(isset($flat[$kk])) $kk = "$key-" . (++$n);
					$flat[$kk] = $val;
				} else {
					// integer key
					$flat[] = $val;
				}
				continue;
			}
	
			/** @var array $val At this point val is known to be an array */
			
			if($depth > $options['maxDepth']) {
				// skip over arrays when when we are at the max recursion depth
				continue;
			}
			
			if(!$preserveKeys) {
				// if keys are not preserved then we can take a shortcut
				$flat = array_merge($flat, $this->flatArray($val, $options));
				continue;
			}
		
			// array value with preserved keys
			foreach($this->flatArray($val, $options) as $k => $v) {
				if(is_int($k) || ctype_digit("$k")) {
					// integer keys in nested array
					$k = (int) $k;
					if($hasStringKey) {
						// parent array is associative and preserveKeys is true
						do {
							$kk = "$key.$k"; // parent key + incrementing child key
							$k++;
						} while(isset($flat[$kk]) || isset($value[$kk]));
						$flat[$kk] = $v;
					} else {
						// parent array is non-associative
						$flat[] = $v;
					}
				} else if(isset($value[$k]) || isset($flat[$k])) {
					// associative key already exists
					// create new key that marries parent and child keys
					$n = -1;
					do {
						$kk = $key . '.' . $k;
						// no match on first-round, start incrementing
						if($n > -1) $kk .= '-' . $n; 
						$n++;
					} while(isset($value[$kk]) || isset($flat[$kk])); 
					$flat[$kk] = $v;
				} else {
					// associative key that is not already taken
					$flat[$k] = $v;
				}
			}
		}

		$depth--;
		
		return $flat;
	}

	/**
	 * Return array of all words in given value (excluding punctuation and other non-word characters)
	 * 
	 * #pw-group-arrays
	 *
	 * @param string|array $value String containing words
	 * @param array $options
	 *  - `keepNumbers` (bool): Keep number-only words in return value? (default=true)
	 *  - `keepNumberFormat` (bool): Keep minus/comma/period in numbers rather than splitting into words? Also requires keepNumbers==true. (default=false)
	 *  - `keepUnderscore` (bool): Keep underscores as part of words? (default=false)
	 *  - `keepHyphen` (bool): Keep hyphenated words? (default=false)
	 *  - `keepApostrophe` (bool): Keep apostrophe as part of words? (default=true) 3.0.168+
	 *  - `keepChars` (array): Specify any of these to also keep as part of words ['.', ',', ';', '/', '*', ':', '+', '<', '>', '_', '-' ] (default=[])
	 *  - `minWordLength` (int): Minimum word length (default=1)
	 *  - `maxWordLength` (int): Maximum word length (default=80)
	 *  - `maxWords` (int): Maximum number of words allowed (default=0, no limit)
	 *  - `stripTags` (bool): Strip markup tags so they don’t contribute to returned word list? (default=true)
	 *  - `truncate` (bool): Truncate rather than remove words that exceed maxWordLength? (default=false) 3.0.250+
	 * @return array
	 * @since 3.0.160
	 *
	 */
	public function wordsArray($value, array $options = array()) {

		$defaults = array(
			'minWordLength' => 1,
			'maxWordLength' => 80,
			'maxWords' => 0,
			'keepHyphen' => false, 
			'keepUnderscore' => false,
			'keepApostrophe' => true,
			'keepNumbers' => true,
			'keepNumberFormat' => true, 
			'keepChars' => array(),
			'stripTags' => true,
			'truncate' => false,
		);

		$options = array_merge($defaults, $options);
		$minLength = (int) $options['minWordLength'];
		$maxLength = (int) $options['maxWordLength'];
		$replacements = array();
		$replacementPrefix = 'REP';
		
		if(is_array($value)) {
			$value = $this->flatArray($value);
			$value = implode(' ', $value);
		} else if(!is_string($value)) {
			$value = $this->string($value);
		}
	
		// prevents non-bracketed tag names from also becoming words
		if($options['stripTags']) $value = strip_tags($value);
		if($options['keepHyphen']) $options['keepChars'][] = '-';
		if($options['keepUnderscore']) $options['keepChars'][] = '_';
	
		// option to let apostrophe be a word separator
		if(!$options['keepApostrophe']) {
			$value = str_replace(array("'", "’"), ' ', $value);
		}
		
		if(!strlen($value)) return array();
		
		if(!$options['keepNumbers']) {
			$options['keepNumberFormat'] = false;
			if(!ctype_alpha($value)) $value = preg_replace('/\d+[-–\d,. ]*/', ' ', $value);
		} else if($options['keepNumberFormat']) {
			$replacements = $this->wordsArrayNumberReplacements($value, $replacementPrefix);
		}
		
		if(count($options['keepChars'])) {
			$n = 0;
			foreach($options['keepChars'] as $c) {
				if(strpos($value, $c) === false) continue;
				do {
					$token = "$n{$replacementPrefix}CHR$n";
				} while(strpos($value, $token) !== false && ++$n); 
				$value = str_replace($c, $token, $value);
				$replacements[$token] = $c;
			}
		}
	
		// https://www.php.net/manual/en/regexp.reference.unicode.php
		// pZ=Separator (line, paragraph or space)
		// pS=Symbol (all)
		// pC=Other (control, format, surrogate)
		// p{Pd}=Dash punctuation
		// pP=Punctuation (all)
		// pPs=Open punctuation
		// pPe=Close punctuation
		// pPf=Final punctuation
		// pPo=Other punctuation
		// pPi=Initial punctuation
		// pM=Mark
		// pMc=Spacing mark
		// pMe=Enclosing mark
		// pMn=Non-spacing mark

		//$splitWith = '.,;/*:+<>\s\pZ\pS\pC\p{Pd}\\\\';
		$splitWith = '.,;/*:+<>\s\pZ\pS\pC\p{Pd}\p{Ps}\p{Pe}\p{Pf}\p{Pi}\p{Po}\\\\';
		$regex = '!\pP*[' . $splitWith . ']\pP*!u';
		$words = preg_split($regex, "$value ", -1, PREG_SPLIT_NO_EMPTY);

		if($words === false) $words = array();
		
		$hasReplacements = count($replacements);
		$keepChars = $hasReplacements && count($options['keepChars']) ? implode('', $options['keepChars']) : '';
		$numWords = 0;
		
		foreach($words as $key => $word) {
			if(!strlen(trim($word))) {
				unset($words[$key]); 
				continue;
			}
			if($options['maxWords'] && $numWords >= $options['maxWords']) {
				unset($words[$key]);
				continue;
			}
			if($hasReplacements && strpos($word, $replacementPrefix) !== false) {
				$word = str_replace(array_keys($replacements), array_values($replacements), $word);
				$words[$key] = $word;
			}
			if(!$options['keepNumbers'] && ctype_digit($word)) {
				// remove numbers
				unset($words[$key]); 
				continue;
			}
			$length = $this->multibyteSupport ? mb_strlen($word) : strlen($word);
			if($length > $maxLength) {
				// remove or truncate any words that are too long
				if($options['truncate']) {
					$word = $this->multibyteSupport ? mb_substr($word, 0, $maxLength) : substr($word, 0, $maxLength);
					$words[$key] = $word;
				} else {
					unset($words[$key]);
					continue;
				}
			} else if($length < $minLength) {
				// remove any words that are are not long enough
				unset($words[$key]);
				continue;
			} else if($keepChars !== '' && !strlen(trim($word, $keepChars))) {
				// remove any words that consist only of keepChars
				unset($words[$key]);
				continue;
			}
			$numWords++;
		}
		
		if($options['maxWords'] && count($words) > $options['maxWords']) {
			// may be impossible to reach but here as a backup
			$words = array_slice($words, 0, $options['maxWords']); 
		}

		return $words;
	}

	/**
	 * Identify decimals, minus signs and commas in numbers, replace them, and return the replacements array
	 * 
	 * @param string $value
	 * @param string $prefix
	 * @return array
	 * 
	 */
	protected function wordsArrayNumberReplacements(&$value, $prefix = 'REP') {
		
		// keep floating point, negative, or thousands-separator numbers together
		$replacements = array();
		$hasPeriod = strpos($value, '.') !== false;
		$hasComma = strpos($value, ',') !== false;
		$hasHyphen = strpos($value, '-') !== false;
		$hasMinus = $hasHyphen || strpos($value, '−') !== false;
		$hasNumber = ($hasPeriod || $hasComma || $hasHyphen) && preg_match('![-.,]\d!', $value); 
		
		if(!$hasNumber) return array();
		
		if($hasPeriod && preg_match_all('!(\b|\d*)\.(\d+)\b!', $value, $matches)) {
			// keep floating point numbers together
			list($n, $decimal) = array(0, "0{$prefix}DEC0X");
			while(strpos($value, $decimal) !== false && ++$n) $decimal = "{$n}{$prefix}DEC{$n}X";
			foreach($matches[1] as $key => $n1) {
				$n2 = $matches[2][$key];
				$value = str_replace("$n1.$n2", "{$n1}$decimal{$n2}", $value);
			}
			$replacements[$decimal] = '.';
		}
		
		if($hasMinus && preg_match_all('!([-−])(\d+)!', $value, $matches)) {
			// prevent negative numbers from losing their minus sign
			list($n, $minus) = array(0, "0{$prefix}MIN0");
			while(strpos($value, $minus) !== false && ++$n) $minus = "{$n}{$prefix}MIN{$n}";
			foreach($matches[2] as $key => $digits) {
				$sign = $matches[1][$key];
				$minusKey = $sign === '-' ? "{$minus}D" : "{$minus}M";
				$value = str_replace("$sign$digits", " $minusKey$digits", $value);
				$replacements[$minusKey] = $sign;
			}
		}
		
		if($hasComma && preg_match_all('!(\d*,)(\d+)!', $value, $matches)) {
			// keep commas that appear around digits
			list($n, $comma) = array(0, "0{$prefix}COM0");
			while(strpos($value, $comma) !== false && ++$n) $comma = "{$n}{$prefix}COM{$n}";
			foreach($matches[1] as $key => $digits1) {
				$digits1 = rtrim($digits1, ',');
				$digits2 = $matches[2][$key];
				$value = str_replace("$digits1,$digits2", "$digits1{$comma}$digits2", $value);
				$replacements[$comma] = ',';
			}
		}
		
		return $replacements;
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
	public function option($value, array $allowedValues = array()) {
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
	public function options(array $values, array $allowedValues = array()) {
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
			return true; 
		} else if(is_object($value)) {
			$value = $this->string($value);
		} else if(is_array($value)) {
			$value = count($value) ? true : false;
		}
		return (bool) $value;
	}
	
	/**
	 * Sanitize to a bit, returning only integer 0 or 1
	 * 
	 * This works the same as the bool sanitizer except that it returns 0 or 1 rather than false or true.
	 * 
	 * #pw-group-other
	 * #pw-group-numbers
	 *
	 * @param string|int|array $value
	 * @return int
	 * @see Sanitizer::bool()
	 * @since 3.0.125
	 *
	 */
	public function bit($value) {
		return $this->bool($value) ? 1 : 0;
	}

	/**
	 * Sanitize checkbox value
	 * 
	 * #pw-group-other
	 * 
	 * @param int|bool|string|mixed|null $value Value to check
	 * @param int|bool|string|mixed|null $yes Value to return if checked (default=true)
	 * @param int|bool|string|mixed|null $no Value to return if not checked (default=false)
	 * @return int|bool|string|mixed|null Return value, based on $checked or $unchecked argument
	 * @since 3.0.128
	 * @see Sanitizer::bool(), Sanitizer::bit()
	 * 
	 */
	public function checkbox($value, $yes = true, $no = false) {
		if($value === '' || $value === '0' || $value === null || $value === false) {
			return $no;
		} else if(empty($value)) {
			return $no; // array or other empty value
		}
		return $yes;
	}

	/**
	 * Limit length of given value to that specified
	 * 
	 * - For strings, this limits the length to that many characters. 
	 * - For arrays, the maxLength is assumed to be the max allowed array items.
	 * - For integers maxLength is assumed to be the max allowed digits. 
	 * - For floats, maxLength is assumed to be max allowed digits (including decimal point).
	 * - Returns the same type it is given: string, array, int or float
	 * 
	 * #pw-group-other
	 * #pw-group-strings
	 * 
	 * @param string|int|array|float $value
	 * @param int $maxLength Maximum length (default=128)
	 * @param null|int $maxBytes Maximum allowed bytes (used for string types only)
	 * @return array|float|int|string
	 * @since 3.0.125
	 * @see Sanitizer::minLength()
	 * 
	 */
	public function maxLength($value, $maxLength = 128, $maxBytes = null) {
		if($maxLength < 0) $maxLength = abs($maxLength);
		if(is_array($value)) {
			if(count($value) > $maxLength) {
				$value = $maxLength ? array_slice($value, 0, $maxLength) : array();
			}
		} else if(is_int($value)) {
			$n = $maxLength;
			while(strlen("$value") > $maxLength && $n) {
				$value = (int) substr("$value", 0, $n);
				$n--;
			}
		} else if(is_float($value)) {
			$n = $maxLength;
			while(strlen("$value") > $maxLength && $n) {
				$value = (float) substr("$value", 0, $n);
				$n--;
			}
		} else {
			if(!is_string($value)) $value = $this->string($value);
			if($this->multibyteSupport) {
				if(mb_strlen($value) > $maxLength) {
					$value = mb_substr($value, 0, $maxLength);
				}
				if($maxBytes) {
					while(strlen($value) > $maxBytes) {
						$value = mb_substr($value, 0, mb_strlen($value)-1);
					}
				}
			} else {
				if(strlen($value) > $maxLength) {
					$value = substr($value, 0, $maxLength);
				}
			}
		}
		return $value;
	}

	/**
	 * Validate or sanitize a string to have a minimum length
	 * 
	 * If string meets minimum length it is returned as-is. 
	 * 
	 * Note that the default behavior of this function is to validate rather than sanitize the value. 
	 * Meaning, it will return blank if the string does not meet the minimum length. Specify the `$padChar`
	 * argument to change that behavior. 
	 * 
	 * If string does not meet minimum length, blank will be returned, unless a `$padChar` is defined in which
	 * case the string will be padded with as many copies of that $padChar are necessary to meet the minimum
	 * length. By default it padds to the right, but you can specify `true` for the `$padLeft` argument to 
	 * make it pad to the left instead. 
	 * 
	 * ~~~~~~
	 * $value = $sanitizer->minLength('foo'); // returns "foo"
	 * $value = $sanitizer->minLength('foo', 3); // returns "foo"
	 * $value = $sanitizer->minLength('foo', 5); // returns blank string
	 * $value = $sanitizer->minLength('foo', 5, 'o'); // returns "foooo"
	 * $value = $sanitizer->minLength('foo', 5, 'o', true); // returns "oofoo"
	 * ~~~~~~
	 * 
	 * #pw-group-strings
	 * 
	 * @param string $value Value to enforcer a minimum length for
	 * @param int $minLength Minimum allowed length
	 * @param string $padChar Pad string with this character if it does not meet minimum length (default='')
	 * @param bool $padLeft Pad to left rather than right? (default=false)
	 * @return string
	 * @see Sanitizer::maxLength()
	 * 
	 */
	public function minLength($value, $minLength = 1, $padChar = '', $padLeft = false) {
		
		$value = $this->string($value);
		$length = $this->multibyteSupport ? mb_strlen($value) : strlen($value);
		
		if($length >= $minLength) return $value; 
		if(!strlen($padChar)) return '';
		
		while($length < $minLength) {
			if($padLeft) {
				$value = $padChar . $value;
			} else {
				$value .= $padChar;
			}
			$length = $this->multibyteSupport ? mb_strlen($value) : strlen($value);
		}
	
		return $value;
	}

	/**
	 * Limit bytes used by given string to max specified
	 *
	 * - This function will not break multibyte characters so long as PHP has mb_string. 
	 * - This function works only with strings and if given a non-string it will be converted to one.
	 * 
	 * #pw-group-strings
	 *
	 * @param string $value
	 * @param int $maxBytes
	 * @return string
	 * @since 3.0.125
	 *
	 */
	public function maxBytes($value, $maxBytes = 128) {
		if(!is_string($value)) $value = $this->string($value);
		return $this->maxLength($value, $maxBytes, $maxBytes); 
	}

	/**
	 * Run value through all sanitizers, return array indexed by sanitizer name and resulting value
	 * 
	 * Used for debugging and testing purposes. 
	 * 
	 * #pw-group-other
	 * 
	 * @param mixed $value
	 * @return array
	 * 
	 */
	public function ___testAll($value) {
		$results = array();
		$fails = array();
		foreach($this->sanitizers as $method => $types) {
			$v = $this->$method($value);
			$results[$method] = $v;
			if(strpos($types, 'm') !== false) continue; // allows any type (m=mixed)
			$type = strtolower(gettype($v));
			$type = $type[0] === 'd' ? 'f' : $type[0];
			if(strpos($types, $type) === false) $fails[$method] = "$type!=$types";
		}
		if(count($fails)) $results['FAILS'] = $fails;
		return $results;
	}

	/**
	 * Get all sanitizer method names and optionally types they return
	 * 
	 * #pw-group-other
	 * 
	 * @param bool $getReturnTypes Get array where method names are keys and values are return types?
	 * @return array
	 * @since 3.0.165
	 * 
	 */
	public function getAll($getReturnTypes = false) {
		return $getReturnTypes ? $this->sanitizers : array_keys($this->sanitizers); 
	}

	/**
	 * Get instance of WireTextTools
	 * 
	 * #pw-group-strings
	 * #pw-group-other
	 * 
	 * @return WireTextTools
	 * @since 3.0.101
	 * 
	 */
	public function getTextTools() {
		if(!$this->textTools) {
			$this->textTools = new WireTextTools();
			$this->wire($this->textTools);
		}
		return $this->textTools;
	}
	
	/**
	 * Get instance of WireNumberTools
	 *
	 * #pw-group-numbers
	 * #pw-group-other
	 *
	 * @return WireNumberTools
	 * @since 3.0.214
	 *
	 */
	public function getNumberTools() {
		if(!$this->numberTools) {
			$this->numberTools = new WireNumberTools();
			$this->wire($this->numberTools);
		}
		return $this->numberTools;
	}

	/**********************************************************************************************************************
	 * FILE VALIDATORS
	 *
	 */

	/**
	 * Validate and sanitize a file using FileValidator modules
	 *
	 * This is intended for validating file data, not file names. Depending on the FileValidator
	 * modules that are used, they may sanitize the file in order ot make it valid. 
	 *
	 * IMPORTANT: This method returns NULL if it can’t find a validator for the file. This does
	 * not mean the file is invalid, just that it didn't have the tools to validate it. If the
	 * getArray option is specified then it would return a blank array rather than null.
	 * 
	 * **getArray option** (3.0.167+):  
	 * When specifying true for the `getArray` option this method will return an associative array 
	 * of validation results indexed by module name. The values for each module name will be either 
	 * true (file validates as-is), 1 (file valid after it was sanitized), or false (file not valid 
	 * and cannot be sanitized). A blank array is returned if no modules could perform the validation.
	 * 
	 * **dryrun option** (3.0.167+):   
	 * When specifying true for the `dryrun` option please note that no validation is performed and 
	 * instead the method returns true or false as to whether or not the file can be validated. It 
	 * only looks at the file extension, so the file need not exist. Meaning it’s also okay to specify 
	 * filename like “test.jpg” without path, when using this option. If using the dryrun option with 
	 * the `getArray` option then it will return an array of module names that would perform the 
	 * validation for the given file type (or blank array if none). 
	 * 
	 * #pw-group-files
	 *
	 * @param string $filename Full path and filename to validate
	 * @param array $options When available, provide array with any one or all of the following:
	 *  - `page` (Page): Page object associated with $filename. (default=null)
	 *  - `field` (Field): Field object associated with $filename. (default=null)
	 *  - `pagefile` (Pagefile): Pagefile object associated with $filename. (default=null)
	 *  - `getArray` (bool): Return array of results rather than a boolean? (default=false) Added 3.0.167
	 *  - `dryrun` (bool|int): Specify true to only return if the file can be validated with this method,
	 *     without actually performing any validation. (default=false). Added 3.0.167
	 * @return bool|array|null Returns one of the following, depending on use of dryrun and getArray options:
	 *  - Boolean true if valid, false if not.
	 *  - NULL if no validator available for given file type or file does not exist.
	 *  - If dryrun option is used, returns boolean (or array of strings if getArray option is true).
	 *  - If getArray option is used, returns associative array of results or blank array if no validators.
	 *
	 */
	public function validateFile($filename, array $options = array()) {
		
		$defaults = array(
			'page' => null,
			'field' => null,
			'pagefile' => null,
			'dryrun' => false,
			'getArray' => false,
		);
		
		$options = array_merge($defaults, $options);
		$filename = (string) $filename;
		$modules = $this->wire()->modules;
		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		$validatorNames = array();
		$validatorResults = array();
		$getArray = $options['getArray'];
		$dryrun = $options['dryrun'] || !empty($options['dryRun']);
		$numFailed = 0;
		$numPassed = 0;

		if(!strlen($extension) || (!$dryrun && !is_file($filename))) {
			return $getArray ? array() : null;
		}

		// find modules that can validate extension
		foreach($modules->findByPrefix('FileValidator', false) as $validatorName) {
			$info = $modules->getModuleInfoVerbose($validatorName);
			if(empty($info) || empty($info['validates'])) continue;

			foreach($info['validates'] as $ext) {
				if($ext === $extension) {
					$validatorNames[$validatorName] = $validatorName;
				} else if($ext[0] === '/' && preg_match($ext, $extension)) {
					$validatorNames[$validatorName] = $validatorName;
				} else {
					// module does not validate extension
				}
			}
		
			// when doing a dryrun we only need to know if at least one module can run
			if($dryrun && !$getArray && count($validatorNames)) break;
		}
		
		// if doing a dryrun then just return whether or not validation is possible
		if($dryrun) return ($getArray ? $validatorNames : count($validatorNames) > 0);

		// if no validators can validate extension then early exit
		if(empty($validatorNames)) return ($getArray ? array() : null);
		
		// execute modules that can validate extension and get results
		foreach($validatorNames as $validatorName) {
			/** @var FileValidatorModule $validator */
			$validator = $modules->get($validatorName);
			if(!$validator) continue; // not likely
			
			if(!empty($options['page'])) $validator->setPage($options['page']);
			if(!empty($options['field'])) $validator->setField($options['field']);
			if(!empty($options['pagefile'])) $validator->setPagefile($options['pagefile']);
			
			$valid = $validator->isValid($filename);
			$validatorResults[$validatorName] = $valid; // false, true or 1
			
			if($valid) {
				// true (bool): file is valid as-is
				// 1 (int): file is valid as a result of sanitization
				// in either case, continue on to the next applicable FileValidator module
				$numPassed++;
				
			} else {
				// at this point we’ve determined file is not valid
				$numFailed++;

				// move errors to Sanitizer class so they can be retrieved
				foreach($validator->errors('clear array') as $error) {
					$this->wire()->log->error($error);
					$this->error($error);
				}

				// unless we are returning an array of results, we can stop now for invalid files
				if(!$getArray) break;
			}
		}
	
		// return array result of all validations
		if($getArray) return $validatorResults;
		
		// return null if no validators could be used
		if(!$numPassed && !$numFailed) return null;

		// if passsed 1+ validators and failed 0, return true
		if($numPassed > 0 && $numFailed === 0) return true;
		
		return false;
	}

	/**********************************************************************************************************************
	 * CLASS HELPERS
	 *
	 */

	public function __toString() {
		return "Sanitizer";
	}
	
	/**
	 * Parse method name into methods array and arguments
	 * 
	 * #pw-internal
	 * 
	 * @param string $method
	 * @param string $delim Multi-method delimiter or omit to auto-detect (underscore, comma or space)
	 * @return array
	 * @since 3.0.125
	 * 
	 */
	protected function parseMethod($method, $delim = '') {
	
		static $cache = array();
		if(isset($cache[$method])) return $cache[$method];
		
		$methods = array();
		if(!ctype_alnum($method)) {
			// may contain delimiter, determine what it is
			$method = trim($method);
			if($delim === '') {
				// no delimiter specified in arguments
				foreach(array('_', ',', ' ') as $d) {
					if(strpos($method, $d) === false) continue;
					$delim = $d;
					break;
				}
			} else if(!strpos($method, $delim)) {
				// delimiter specified, but is not present
				$delim = '';
			}
			$parts = $delim === '' ? array($method) : explode($delim, $method);
		} else {
			// single "method" or "method123"
			$parts = array($method);
		}
		
		foreach($parts as $name) {
	
			$name = trim($name);
			if(empty($name)) continue;
			$maxLength = 0;
			
			if(ctype_digit($name)) {
				// number-only assumed to be maxLength method
				$exists = true;
				$maxLength = (int) $name;
				$name = 'maxLength';
				
			} else if($this->methodExists($name, false)) {
				// method exists already
				$exists = true;
				
			} else {
				// method name needs parsing
				$n = 0;
				$s = '';
				do {
					$maxLength = $s;
					$s = substr($name, -1 * (++$n));
				} while(ctype_digit($s));
				if(ctype_digit($maxLength)) {
					$name = substr($name, 0, -1 * strlen($maxLength));
					$maxLength = (int) $maxLength;
				} else {
					$maxLength = 0;
				}
				$exists = $this->methodExists($name, false);
			}
			
			$methods[] = array(
				'name' => $name, 
				'maxLength' => $maxLength,
				'exists' => $exists,
			);
		}
		
		$cache[$method] = $methods;
	
		return $methods;
	}

	/**
	 * Does the given sanitizer method name exist?
	 * 
	 * #pw-internal
	 * 
	 * @param string $name
	 * @param bool $allowCombos Allow check to include combo-methods that combine multiple sanitizers and/or max-length? (default=true)
	 * @return bool
	 * @since 3.0.125
	 * 
	 */
	public function methodExists($name, $allowCombos = true) {
		$exists = method_exists($this, $name) || method_exists($this, "___$name") || $this->hasHook($name);
		if(!$exists && $allowCombos) {
			$methods = $this->parseMethod($name, '_');
			if(empty($methods)) return false;
			$exists = true;
			foreach($methods as $method) {
				if($method['exists']) continue;
				$exists = false;
				break;
			}
		}
		return $exists;
	}

	/**
	 * Call a sanitizer method indirectly where method name can contain combined/combo methods
	 * 
	 * This method is primarily here to support predefined sanitizers in strings, like those that might 
	 * be specified in settings for a module or field. For regular use, you probably want to call the
	 * sanitizer methods directly rather than through this method. 
	 * 
	 * ~~~~~
	 * // sanitize with text then entities sanitizers
	 * $value = $sanitizer->sanitize($value, 'text,entities'); 
	 * 
	 * // numbers appended to text sanitizers imply max length
	 * $value = $sanitizer->sanitize($value, 'text128,entities'); 
	 * ~~~~~
	 * 
	 * #pw-group-other
	 * 
	 * @param mixed $value
	 * @param string $method Method name "method", or combined method name(s) "method1,method2,method3"
	 * @return string|int|array|float|null
	 * @since 3.0.125
	 * 
	 */
	public function sanitize($value, $method = 'text') {
		$maxLengthMethods = array(
			'maxLength' => 4,
			// method($val, $beautify, $maxLength)
			'name' => 3,
			'fieldName' => 3,
			'templateName' => 3,
			'pageName' => 3,
			'filename' => 3,
			'pagePathName' => 3,
			'alpha' => 3,
			'alphanumeric' => 3,

			// method($val, $maxLength)
			'attrName' => 2, 
			'pageNameTranslate' => 2,
			'pageNameUTF8' => 2,
			'digits' => 2,
			'truncate' => 2,
			'min' => 2, // min123 where 123 is minimum allowed value
			'max' => 2, // max123 where 123 is maximum allowed value
			'minLength' => 2, // refers to minLength argument
			'fieldSubfield' => 2, // maxLength is $limit argument

			// method($val, options[ 'maxLength' => 123 ])
			'path' => 1,
			'text' => 1,
			'textarea' => 1,
			'url' => 1,
			'selectorValue' => 1,
		);
		
		$methods = $this->parseMethod($method); 
		
		foreach($methods as $method) {
			
			$methodName = $method['name'];
			if(!$method['exists']) throw new WireException("Unknown sanitizer: $methodName"); 
			
			if(!empty($method['maxLength'])) {
				$maxLength = $method['maxLength'];
				$n = isset($maxLengthMethods[$methodName]) ? $maxLengthMethods[$methodName] : 0;
				switch($n) {
					case 4: $value = $this->maxLength($value, $maxLength); break;
					case 3: $value = $this->$methodName($value, false, $maxLength); break;
					case 2: $value = $this->$methodName($value, $maxLength); break;
					case 1: $value = $this->$methodName($value, array('maxLength' => $maxLength)); break;
					default: $value = $this->maxLength($this->$methodName($value), $maxLength);
				}
			} else {
				$value = $this->$methodName($value);
			}
		}
		
		return $value;
	}

	/**
	 * Validate that value remains unchanged by given sanitizer method, or return null if not
	 * 
	 * If change is just a type conversion change or surrounding whitespace (that gets trimmed) 
	 * then this is still considered valid. 
	 * 
	 * Returns NULL or given $fallback value if value does not validate. Note that if results like
	 * 0, false or blank string are considered valid values, then this method can return them. So for
	 * cases like that you should compare the return value with NULL (or whatever your $fallback is).
	 * 
	 * things like 0 or false (if that is a valid value) compare the return value with null before
	 * assuming a value is not valid. 
	 * 
	 * #pw-group-validate
	 * 
	 * ~~~~~
	 * $sanitizer->validate('abc', 'alpha'); // valid: returns 'abc'
	 * $sanitizer->validate('abc123', 'alpha'); invalid: returns null
	 * ~~~~~
	 * 
	 * @param string|int|array|float $value Value to validate
	 * @param string $method Saniatizer method name or CSV names combo
	 * @param null|mixed mixed $fallback Optionally return this fallback value (rather than null) if value does not validate
	 * @return null|mixed Returns sanitized value if it validates or null (or given fallback) if value does not validate
	 * @since 3.0.125
	 * 
	 */
	public function validate($value, $method = 'text', $fallback = null) {
		$valid = $this->sanitize($value, $method); 
		if(is_array($valid)) {
			if(is_array($value) && $valid == $value) return $valid;
		} else if(is_bool($valid)) {
			if($valid == $value) return $valid;	
		} else {
			if(is_string($value)) $value = trim($value);
			if(is_string($valid)) $valid = trim($valid);
			if($valid == $value && strlen("$valid") == strlen("$value")) return $valid;
		}
		return $fallback;
	}

	/**
	 * Is given value valid? (i.e. unchanged by given sanitizer method)
	 * 
	 * ~~~~~~
	 * if($sanitizer->valid('abc123', 'alphanumeric')) {
	 *  // value is valid
	 * }
	 * ~~~~~~
	 * 
	 * #pw-group-validate
	 * 
	 * @param string|int|array|float $value Value to check if valid
	 * @param string $method Method name or CSV method names
	 * @param bool $strict When true, sanitized value must be identical in type to the one given
	 * @return bool
	 * @since 3.0.125
	 * 
	 */
	public function valid($value, $method = 'text', $strict = false) {
		$valid = $this->validate($value, $method);
		if($valid === null) return false;
		if($strict && $value !== $valid) return false;
		return true;
	}
	
	/**
	 * Map to sanitizers
	 *
	 * @param $method
	 * @param $arguments
	 * 
	 * #pw-internal
	 *
	 * @return string|int|array|float|null Returns null when input variable does not exist
	 * @throws WireException
	 *
	 */
	public function ___callUnknown($method, $arguments) {
		if($this->methodExists($method) && count($arguments)) {
			return $this->sanitize($arguments[0], $method); 
		} else {
			return parent::___callUnknown($method, $arguments);
		}
	}

}
