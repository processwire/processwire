<?php namespace ProcessWire;

/**
 * ProcessWire Date/Time Tools ($datetime API variable)
 * 
 * #pw-summary The $datetime API variable provides helpers for working with dates/times and conversion between formats.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @method string relativeTimeStr($ts, $abbreviate = false, $useTense = true)
 * 
 */

class WireDateTime extends Wire {
	
	/**
	 * Date formats in date() format
	 *
	 */
	static protected $dateFormats = array(

		// Gregorian little-endian, starting with day (used by most of world)
		'l, j F Y', // Monday, 1 April 2012
		'j F Y',	// 1 April 2012
		'd-M-Y',	// 01-Apr-2012
		'dMy',		// 01Apr12
		'd/m/Y',	// 01/04/2012
		'd.m.Y',	// 01.04.2012
		'd/m/y',	// 01/04/12
		'd.m.y',	// 01.04.12
		'j/n/Y',	// 1/4/2012
		'j.n.Y',	// 1.4.2012
		'j/n/y',	// 1/4/12
		'j.n.y',	// 1.4.12

		// Gregorian big-endian, starting with year (used in Asian countries, Hungary, Sweden, and US armed forces)
		'Y-m-d',	// 2012-04-01 (ISO-8601)
		'Y/m/d',	// 2012/04/01 (ISO-8601)
		'Y.n.j',	// 2012.4.1 (common in China)
		'Y/n/j',	// 2012/4/1 
		'Y F j',	// 2012 April 1
		'Y-M-j, l',	// 2012-Apr-1, Monday
		'Y-M-j',	// 2012-Apr-1
		'YMj',		// 2012Apr1

		// Middle-endian, starting with month (US and some Canada)
		'l, F j, Y',	// Monday, April 1, 2012
		'F j, Y',	// April 1, 2012
		'M j, Y',	// Apr 1, 2012
		'm/d/Y',	// 04/01/2012 
		'm.d.Y',	// 04.01.2012 
		'm/d/y',	// 04/01/12 
		'm.d.y',	// 04.01.12 
		'n/j/Y',	// 4/1/2012 
		'n.j.Y',	// 4.1.2012 
		'n/j/y',	// 4/1/12 
		'n.j.y',	// 4.1.12 

		// Other
		//'%x', 	// 04/01/12 (locale based date)
		//'%c', 	// Tue Feb 5 00:45:10 2012 (locale based date/time)
		// 'U',		// Unix Timestamp

	);

	static protected $timeFormats = array(
		'g:i a',	// 5:01 pm
		'h:i a',	// 05:01 pm
		'g:i A',	// 5:01 PM
		'h:i A',	// 05:01 PM
		'H:i',		// 17:01 (with leading zeros)
		'H:i:s',	// 17:01:01 (with leading zeros)

		// Relative 
		'!relative',
		'!relative-',
		'!rel',
		'!rel-',
		'!r',
		'!r-',
	);

	/**
	 * Date/time translation table from PHP date() to strftime() and JS/jQuery
	 *
	 */
	static protected $dateConversion = array(
		// date  	strftime js    	regex
		'l' => array(	'%A', 	'DD', 	'\w+'), 			// Name of day, long			Monday
		'D' => array(	'%a', 	'D', 	'\w+'),				// Name of day, short			Mon
		'F' => array(	'%B', 	'MM', 	'\w+'),				// Name of month, long			April
		'M' => array(	'%b', 	'M', 	'\w+'),				// Name of month, short			Apr
		'j' => array(	'%-d', 	'd', 	'\d{1,2}'),			// Day without leading zeros		1
		'd' => array(	'%d', 	'dd', 	'\d{2}'),			// Day with leading zeros		01
		'n' => array(	'%-m', 	'm', 	'\d{1,2}'),			// Month without leading zeros		4
		'm' => array(	'%m', 	'mm', 	'\d{2}'),			// Month with leading zeros		04
		'y' => array(	'%y', 	'y', 	'\d{2}'),			// Year 2 character			12
		'Y' => array(	'%Y', 	'yy', 	'\d{4}'),			// Year 4 character			2012
		'N' => array(	'%u', 	'', 	'[1-7]'),			// Day of the week (1-7)		1
		'w' => array(	'%w', 	'', 	'[0-6]'),			// Zero-based day of week (0-6)		0
		'S' => array(	'', 	'', 	'\w{2}'),			// Day ordinal suffix			st, nd, rd, th
		'z' => array(	'%-j', 	'o', 	'\d{1,3}'),			// Day of the year (0-365)		123
		'W' => array(	'%W', 	'', 	'\d{1,2}'),			// Week # of the year			42
		't' => array(	'', 	'', 	'(28|29|30|31)'),		// Number of days in month		28
		'o' => array(	'%V', 	'', 	'\d{1,2}'),			// ISO-8601 week number			42
		'a' => array(	'%P',	'tt',	'[aApP][mM]'),			// am or pm				am
		'A' => array(	'%p',	'TT',	'[aApP][mM]'),			// AM or PM				AM
		'g' => array(	'%-I',	'h', 	'\d{1,2}'),			// 12-hour format, no leading zeros	5
		'h' => array(	'%I', 	'hh', 	'\d{2}'),			// 12-hour format, leading zeros	05
		'G' => array(	'%-H', 	'h24', 	'\d{1,2}'),			// 24-hour format, no leading zeros	5
		'H' => array(	'%H', 	'hh24', '\d{2}'),			// 24-hour format, leading zeros	05
		'i' => array(	'%M', 	'mm', 	'[0-5][0-9]'),			// Minutes				09
		's' => array(	'%S', 	'ss', 	'[0-5][0-9]'), 			// Seconds				59
		'e' => array(	'', 	'', 	'\w+'),				// Timezone Identifier			UTC, GMT, Atlantic/Azores
		'I' => array(	'', 	'', 	'[01]'),			// Daylight savings time?		1=yes, 0=no
		'O' => array(	'', 	'', 	'[-+]\d{4}'),			// Difference to Greenwich time/hrs	+0200
		'P' => array(	'', 	'', 	'[-+]\d{2}:\d{2}'),		// Same as above, but with colon	+02:00
		'T' => array(	'', 	'', 	'\w+'),				// Timezone abbreviation		MST, EST
		'Z' => array(	'', 	'', 	'-?\d+'),			// Timezone offset in seconds		-43200 through 50400
		'c' => array(	'', 	'', 	'[-+:T\d]{19,25}'),		// ISO-8601 date			2004-02-12T15:19:21+00:00
		'r' => array(	'', 	'',		'\w+, \d+ \w+ \d{4}'),		// RFC-2822 date			Thu, 21 Dec 2000 16:01:07 +0200
		'U' => array(	'%s', 	'@', 	'\d+'),				// Unix timestamp			123344556	
	);
	
	protected $caches = array();

	/**
	 * Return all predefined PHP date() formats for use as dates
	 * 
	 * ~~~~~
	 * // output all date formats
	 * $formats = $datetime->getDateFormats();
	 * echo "<pre>" . print_r($formats, true) . "</pre>";
	 * ~~~~~
	 * 
	 * #pw-advanced
	 * 
	 * @return array Returns an array of strings containing recognized date formats
	 *
	 */
	public function getDateFormats() { return self::$dateFormats; }
	public static function _getDateFormats() { return self::$dateFormats; }

	/**
	 * Return all predefined PHP date() formats for use as times
	 *
	 * ~~~~~
	 * // output all time formats
	 * $formats = $datetime->getTimeFormats();
	 * echo "<pre>" . print_r($formats, true) . "</pre>";
	 * ~~~~~
	 * 
	 * #pw-advanced
	 *
	 * @return array Returns an array of strings containing recognized time formats
	 *
	 */
	public function getTimeFormats() { return self::$timeFormats; }
	public static function _getTimeFormats() { return self::$timeFormats; }

	/**
	 * Given a date/time string and expected format, convert it to a unix timestamp
	 *
	 * @param string $str Date/time string
	 * @param string $format Format of the date/time string in PHP date syntax
	 * @return int Unix timestamp
	 *
	 */
	public function stringToTimestamp($str, $format) {

		if(empty($str)) return '';

		// already a timestamp
		if(ctype_digit(ltrim("$str", '-'))) return (int) $str;

		$format = trim("$format");
		if(!strlen($format)) return strtotime($str);

		// use PHP 5.3's date parser if its available
		if(function_exists('date_parse_from_format')) {
			// PHP 5.3+
			$a = date_parse_from_format($format, $str);
			if(isset($a['warnings']) && count($a['warnings'])) {
				foreach($a['warnings'] as $warning) {
					$this->warning($warning . " (value='$str', format='$format')");
				}
			}
			if($a['year'] && $a['month'] && $a['day']) {
				return mktime($a['hour'], $a['minute'], $a['second'], $a['month'], $a['day'], $a['year']);
			}
		}

		$regex = '!^' . $this->convertDateFormat($format, 'regex') . '$!';
		if(!preg_match($regex, $str, $m)) {
			// wire('pages')->message("'$format' - '$regex' - '$str'"); 
			// if we can't match it, then just send it to strtotime
			return strtotime($str);
		}

		$s = '';

		// month
		if(isset($m['n'])) $s .= $m['n'] . '/';
			else if(isset($m['m'])) $s .= $m['m'] . '/';
			else if(isset($m['F'])) $s .= $m['F'] . ' ';
			else if(isset($m['M'])) $s .= $m['M'] . ' ';
			else return strtotime($str);

		// separator character
		$c = substr($s, -1);

		// day
		if(isset($m['j'])) $s .= $m['j'] . $c;
			else if(isset($m['d'])) $s .= $m['d'] . $c;
			else $s .= '1' . $c;

		// year
		if(isset($m['y'])) $s .= $m['y'];
			else if(isset($m['Y'])) $s .= $m['Y'];
			else $s .= date('Y');

		$s .= ' ';
		$useTime = true;

		// hour 
		if(isset($m['g'])) $s .= $m['g'] . ':';
			else if(isset($m['h'])) $s .= $m['h'] . ':';
			else if(isset($m['G'])) $s .= $m['G'] . ':';
			else if(isset($m['H'])) $s .= $m['H'] . ':';
			else $useTime = false;

		// time
		if($useTime) {
			// minute
			if(isset($m['i'])) {
				$s .= $m['i'];
				// second
				if(isset($m['s'])) $s .= ':' . $m['s'];
			}
			// am/pm
			if(isset($m['a'])) $s .= ' ' . $m['a'];
				else if(isset($m['A'])) $s .= ' ' . $m['A'];
		}

		return strtotime($s);
	}

	/**
	 * Format a date with the given PHP date() or PHP strftime() format
	 * 
	 * This method will accept either [PHP date](http://php.net/manual/en/function.date.php) 
	 * or [PHP strftime](http://php.net/manual/en/function.strftime.php) compatible formats, 
	 * determine what kind it is, and return the timestamp formatted with it. 
	 * 
	 * ~~~~~~
	 * // Output the current date/time in ISO-8601 like format
	 * echo $datetime->formatDate(time(), 'Y-m-d H:i a'); 
	 * ~~~~~~
	 * 
	 * #pw-internal
	 *
	 * @param int $value Unix timestamp of date/time
	 * @param string $format PHP date() or strftime() format string to use for formatting
	 * @return string Formatted date string
	 *
	 */
	public function formatDate($value, $format) {

		if(!$value) return '';
		if(!strlen("$format") || $format === 'U' || $format === '%s') return (int) $value; // unix timestamp
		$relativeStr = '';

		if(strpos($format, '!') !== false) {
			if(preg_match('/([!][relativ]+-?)/', $format, $matches)) {
				$relativeStr = $this->date(ltrim($matches[1], '!'), $value);
				$format = str_replace($matches[1], '///', $format);
			}
		}

		if(strpos($format, '%') !== false) {
			// use strftime() if format string contains a %
			if(strpos($format, '%-') !== false) {
				// not all systems support the '%-' option in strftime to trim leading zeros
				// so we are doing our own implementation here
				$TRIM0 = true;
				$format = str_replace('%-', 'TRIM0%', $format);
			} else {
				$TRIM0 = false;
			}
			$value = $this->strftime($format, $value);
			if($TRIM0) $value = str_replace(array('TRIM00', 'TRIM0'), '', $value);

		} else {
			$value = $this->date($format, $value);
		}

		if(strlen($relativeStr)) $value = str_replace('///', $relativeStr, $value);

		return $value;
	}

	/**
	 * Given a PHP date() format, convert it to either 'js', 'strftime' or 'regex' format
	 * 
	 * #pw-advanced
	 *
	 * @param string $format PHP date() format
	 * @param string $type New format to convert to: either 'js', 'strftime', or 'regex'
	 * @return string
	 *
	 */
	public function convertDateFormat($format, $type) {

		$newFormat = '';
		$lastc = '';

		for($n = 0; $n < strlen($format); $n++) {

			$c = $format[$n];

			if($c == '\\') {
				// begin escaped character
				$lastc = $c;
				continue;
			}

			if($lastc == '\\') {
				// literal character, not translated
				$lastc = $c;
				$newFormat .= $c;
				continue;
			}

			if(!isset(self::$dateConversion[$c])) {
				// unknown character
				if($type == 'regex' && in_array($c, array('.', '[', ']', '(', ')', '*', '+', '?'))) {
					$c = '\\' . $c; // escape regex chars
				}
				$newFormat .= $c;
				continue;
			}

			list($strftime, $js, $regex) = self::$dateConversion[$c];
			if($type == 'js') {
				$newFormat .= $js;
			} else if($type == 'strftime') {
				$newFormat .= $strftime;
			} else if($type == 'regex') {
				$newFormat .= '\b(?<' . $c . '>' . $regex . ')\b'; // regex captured with name of date() format char
			} else {
				$newFormat .= $c;
			}

			$lastc = $c;
		}

		return $newFormat;
	}


	/**
	 * Format a date, using PHP date(), strftime() or other special strings (see arguments).
	 *
	 * This is designed to work the same way as PHP's `date()` but be able to accept any common format
	 * used in ProcessWire. This is helpful for reducing code in places where you might have logic
	 * determining when to use `date()`, `strftime()`, `wireRelativeTimeStr()` or some other date 
	 * formatting function. 
	 * 
	 * ~~~~~~
	 * // Output the current date/time in relative format
	 * echo $datetime->date('relative');
	 * ~~~~~~
	 *
	 * @param string|int $format Use one of the following:
	 *  - [PHP date](http://php.net/manual/en/function.date.php) format
	 *  - [PHP strftime](http://php.net/manual/en/function.strftime.php) format (detected by presence of a '%' somewhere in it)
	 *  - `relative` for a relative date/time string.
	 *  - `relative-` for a relative date/time string with no tense.
	 *  - `rel` for an abbreviated relative date/time string.
	 *  - `rel-` for an abbreviated relative date/time string with no tense.
	 *  - `r` for an extra-abbreviated relative date/time string.
	 *  - `r-` for an extra-abbreviated relative date/time string with no tense.
	 *  - `ts` makes it return a unix timestamp
	 *  - blank string makes it use the system date format ($config->dateFormat)
	 *  - If given an integer and no second argument specified, it is assumed to be the second ($ts) argument.
	 * @param int|string|null $ts Optionally specify the date/time stamp or strtotime() compatible string. If not specified, current time is used.
	 * @return string|bool Formatted date/time, or boolean false on failure
	 *
	 */
	function date($format = '', $ts = null) {
		if(is_null($ts)) {
			// ts not specified, or it was specified in $format
			if(ctype_digit("$format")) {
				// ts specified in format
				$ts = (int) $format;
				$format = '';
			} else {
				// use current timestamp
				$ts = time();
			}
		} else if(is_string($ts) && ctype_digit("$ts")) {
			// ts is a digit string, convert to integer
			$ts = (int) $ts;
		} else if(is_string($ts)) {
			// ts is a non-integer string, we assume to be a strtotime() compatible formatted date
			$ts = strtotime($ts);
		}
		if($format == '') $format = $this->wire()->config->dateFormat;
		if($format == 'relative') {
			$value = $this->relativeTimeStr($ts);
		} else if($format == 'relative-') {
			$value = $this->relativeTimeStr($ts, false, false);
		} else if($format == 'rel') {
			$value = $this->relativeTimeStr($ts, true);
		} else if($format == 'rel-') {
			$value = $this->relativeTimeStr($ts, true, false);
		} else if($format == 'r') {
			$value = $this->relativeTimeStr($ts, 1);
		} else if($format == 'r-') {
			$value = $this->relativeTimeStr($ts, 1, false);
		} else if($format == 'ts') {
			$value = $ts;
		} else if(strpos($format, '%') !== false) {
			$value = $this->strftime($format, $ts);
		} else {
			$value = date($format, $ts);
			$value = $this->localizeDateText($value, $format);
		}
			
		return $value; 
	}

	/**
	 * Parse about any English textual datetime description into a Unix timestamp using PHP’s strtotime()
	 * 
	 * This function behaves the same as PHP’s version except that it optionally accepts an `$options` array 
	 * and lets you specify the return value for empty or zeroed dates like 0000-00-00. If given a zero’d date
	 * then it returns null by default (rather than throwing an error as PHP8 does). As of 3.0.238+ this method
	 * also lets you optionally specify an input format should the given date string not be strtotime compatible.
	 * 
	 * @param string $str Date/time string 
	 * @param array|int $options Options to modify behavior, or specify int for the `baseTimestamp` option, or string for `inputFormat` option.
	 *  - `emptyReturnValue` (int|null|false): Value to return for empty or zero-only date strings (default=null)
	 *  - `baseTimestamp` (int|null): The timestamp which is used as a base for the calculation of relative dates.
	 *  - `inputFormat` (string): Optional date format that given $str is in, if not strtotime() compatible. (3.0.238+)
	 *  - `outputFormat` (string): Optionally return value in this date format rather than unix timestamp (3.0.238+)
	 * @return false|int|null
	 * @see https://www.php.net/manual/en/function.strtotime.php
	 * @since 3.0.178
	 * 
	 */
	function strtotime($str, $options = array()) {
		$defaults = array(
			'emptyReturnValue' => null, 
			'baseTimestamp' => null,
			'inputFormat' => '',
			'outputFormat' => '', 
		);
		if(is_int($options)) $defaults['baseTimestamp'] = $options; 
		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		if(!empty($options['outputFormat'])) return $this->strtodate($str, $options);
		$str = trim($str);
		if(empty($str)) return $options['emptyReturnValue'];
		if(strpos($str, '00') === 0) {
			$test = trim(preg_replace('/[^\d]/', '', $str), '0');
			if(!strlen($test)) return $options['emptyReturnValue'];
		}
		if($options['inputFormat']) {
			$value = \DateTimeImmutable::createFromFormat($options['inputFormat'], $str);
			$value = $value ? $value->getTimestamp() : false;
		} else {
			$value = strtotime($str, $options['baseTimestamp']) ;
		}
		if($value === false) $value = $options['emptyReturnValue'];
		return $value;
	}

	/**
	 * Parse English textual datetime description into a formatted date string, or blank if not a date
	 * 
	 * @param string $str Date/time string to parse
	 * @param string|array $format Output format to use, or array for $options. 
	 *  - Omit or boolean true for default 'Y-m-d H:i:s'.
	 *  - Specify date format string, see [formats](https://www.php.net/manual/en/datetime.format.php).
	 *  - Specify boolean false for unix timestamp.
	 *  - Specify array of options.
	 * @param array $options Can also be specified as 2nd argument. Options include:
	 *  - `emptyReturnValue` (int|null|false): Value to return for empty or zero-only date strings (default='')
	 *  - `baseTimestamp` (int|null): The timestamp which is used as a base for the calculation of relative dates.
	 *  - `inputFormat` (string): Optional date format that given $str is in, if not strtotime() compatible.
	 *  - `outputFormat` (string|bool): Format to return date string in, used only if $options specified for $format argument.
	 *  - `format` (string|bool) Optional alias of outputFormat, used only if $options specified for $format argument.
	 * @return string Return string, returns blank string on fail. 
	 * @since 3.0.238
	 * 
	 */
	public function strtodate($str, $format = true, array $options = array()) {
		$defaults = array(
			'emptyReturnValue' => '',
			'baseTimestamp' => null,
			'outputFormat' => 'Y-m-d H:i:s',
			'inputFormat' => '', 
		);
		
		if(is_array($format)) {
			$options = array_merge($defaults, $format);
			if(isset($options['format'])) $options['outputFormat'] = $options['format'];
			$format = $options['outputFormat'];
		} else {
			$options = array_merge($defaults, $options);
		}
		
		$is = false;
		$str = trim((string) $str);
		$len = strlen($str);
		
		if($format === true) $format = $defaults['outputFormat'];
		
		if(!$len || $len > 30) {
			return $options['emptyReturnValue'];
			
		} else if(ctype_digit($str) && strpos($str, '0') !== 0) {
			if($len === 1) $str = "0$str";
			if($len === 2) {
				$value = strtotime("20$str-01-01");
			} else if($len === 4) {
				$value = strtotime("$str-01-01");
			} else {
				// unix timestamp
				$value = (int) $str;
				if(is_int($options['baseTimestamp'])) $value += $options['baseTimestamp'];
			}

		} else {
			// i.e. '+1 DAY', '10/10/2024', 'April 8 2024'
			$chars = array('-', '/', '+', '.', ' ');
			foreach($chars as $c) {
				if(strpos($str, $c) !== false) $is = true;
				if($is) break;
			}
			if(!$is) $is = ctype_alnum($str); // word string with 0 space, i.e. "tomorrow"
			if(!$is) return $options['emptyReturnValue'];
			unset($options['outputFormat']);
			$value = $this->strtotime($str, $options);
		}
	
		if($value !== $options['emptyReturnValue']) {
			if($format === $defaults['outputFormat']) {
				$value = date($format, $value);
			} else if(empty($format) || $format === 'ts' || $format === 'U') {
				// timestamp, keep as-is
			} else {
				$value = $this->date($format, $value);
			}
		}
		
		if(empty($value)) $value = $options['emptyReturnValue'];
		
		return (string) $value;
	}

	/**
	 * strftime() replacement function that works in PHP 8.1+ (though not locale aware)
	 * 
	 * @param string $format
	 * @param null|int|string $timestamp
	 * @return string|false
	 * @since 3.0.197
	 * 
	 */
	public function strftime($format, $timestamp = null) {
		
		$format = (string) $format;
		
		if(is_string($timestamp)) {
			if(empty($timestamp)) {
				$timestamp = null;
			} else if(ctype_digit($timestamp)) {
				$timestamp = (int) $timestamp;
			} else {
				$timestamp = $this->strtotime($timestamp);
			}
		}
		
		if($timestamp === null) $timestamp = time();
		
		if(version_compare(PHP_VERSION, '8.1.0', '<')) {
			return strftime($format, $timestamp);
		}
		
		$format = $this->strftimeToDateFormat($format);

		$value = date($format, $timestamp);
		$value = $this->localizeDateText($value, $format);
		
		return $value;
	}

	/**
	 * Convert strftime() format to date() format
	 * 
	 * @param string $format
	 * @return string
	 * @since 3.0.197
	 * 
	 */
	protected function strftimeToDateFormat($format) {
		
		// replacements, in addition to those specified in self::$dateConversion
		// strftime format => date format
		$replacements = array(
			'%e' => 'j', // Day of the month without leading zeros
			'%j' => 'z', // Day of the year, 3 digits with leading zeros
			'%U' => '_', // Week number of the given year, starting with the first Sunday as the first week (not implemented)
			'%h' => 'M', // Abbreviated month name
			'%C' => '_', // Two digit representation of the century (year divided by 100, truncated to an integer) (not implemented)
			'%g' => 'y', // Two digit representation of the year going by ISO-8601:1988 standards (see %V)
			'%G' => 'Y', // 4 digit year
			'%k' => 'G', // Hour in 24-hour format
			'%l' => 'g', // Hour in 12-hour format
			'%r' => 'h:i:s A', // Example: 09:34:17 PM
			'%R' => 'G:i', // Example: 00:35 for 12:35 AM
			'%T' => 'G:i:s', // Example: 21:34:17 for 09:34:17 PM
			'%X' => 'G:i:s', // Preferred time representation based on locale, without the date, Example: 03:59:16 or 15:59:16
			'%Z' => 'T', // The time zone abbreviation. Example: EST for Eastern Time
			'%c' => 'Y-m-d H:i:s', // Preferred date and time stamp based on locale
			'%D' => 'm/d/y', // Example: 02/05/09 for February 5, 2009
			'%F' => 'Y-m-d', // Example: 2009-02-05 for February 5, 2009
			'%n' => '\\n', // newline
			'%t' => '\\t', // tab
			'%%' => '%', // literal percent
		);

		foreach(self::$dateConversion as $dateFormat => $formats) {
			$strftimeFormat = $formats[0];
			if(empty($strftimeFormat)) continue;
			if(strpos($format, $strftimeFormat) !== false) $replacements[$strftimeFormat] = $dateFormat;
		}
		
		return strtr($format, $replacements);
	}


	/**
	 * Given a unix timestamp (or date string), returns a formatted string indicating the time relative to now
	 *
	 * For example: 
	 * 
	 * - 2 years ago
	 * - 3 months ago
	 * - 1 day ago
	 * - 30 seconds ago
	 * - Just now
	 * - 1 day from now
	 * - 5 months from now
	 * - 3 years from now
	 * 
	 * This method also supports multi-language and will output in the current user's language, so long as the 
	 * phrases in /wire/core/WireDateTime.php are translated in the language pack. 
	 * 
	 * @param int|string $ts Unix timestamp or date string
	 * @param bool|int|array $abbreviate Whether to use abbreviations for shorter strings.
	 *  - Specify boolean TRUE for abbreviations (abbreviated where common, not always different from non-abbreviated)
	 *  - Specify integer 1 for extra short abbreviations (all terms abbreviated into shortest possible string)
	 *  - Specify boolean FALSE or omit for no abbreviations.
	 *  - Specify associative array of key=value pairs of terms to use for abbreviations. The possible keys are:
	 * 	  just now, ago, from now, never, second, minute, hour, day, week, month, year, decade, seconds, minutes, 
	 *    hours, days, weeks, months, years, decades
	 * @param bool $useTense Whether to append a tense like "ago" or "from now".
	 *  - May be ok to disable in situations where all times are assumed in future or past.
	 *  - In abbreviate=1 (shortest) mode, this removes the leading "+" or "-" from the string.
	 * @return string Formatted relative time string
	 *
	 */
	public function ___relativeTimeStr($ts, $abbreviate = false, $useTense = true) {
		// Originally based upon: <http://www.php.net/manual/en/function.time.php#89415>

		if(empty($ts)) {
			if(is_array($abbreviate) && isset($abbreviate['never'])) return $abbreviate['never'];
			return $this->_('Never');
		}

		$justNow = $this->_('just now');
		$ago = $this->_('ago');
		$prependAgo = '';
		$fromNow = $this->_('from now');
		$prependFromNow = '';
		$space = ' ';
		
		if($abbreviate === 1) {
			// extra short abbreviations
			$justNow = $this->_('now');
			$ago = '';
			$prependAgo = '-';
			$fromNow = '';
			$prependFromNow = '+';
			$space = '';
			
		} else if($abbreviate === true) {
			// standard abbreviations
			$justNow = $this->_('now');
			$fromNow = '';
			$prependFromNow = $this->_('in') . ' ';

		} else if(is_array($abbreviate)) {
			// user substitutions
			if(isset($abbreviate['just now'])) $justNow = $abbreviate['just now'];
			if(isset($abbreviate['from now'])) $fromNow = $abbreviate['from now'];
			if(isset($abbreviate['ago'])) $ago = $abbreviate['ago'];
		}

		$lengths = array("60","60","24","7","4.35","12","10");
		$now = time();
		if(!ctype_digit("$ts")) $ts = strtotime($ts);
		if(empty($ts)) return "";

		// is it future date or past date
		if($now > $ts) {
			$difference = $now - $ts;
			$tense = $ago;
			$prepend = $prependAgo;
		} else {
			$difference = $ts - $now;
			$tense = $fromNow;
			$prepend = $prependFromNow;
		}

		if(!$useTense) {
			$prepend = '';
			$tense = '';
		}

		for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
			$difference /= $lengths[$j];
		}

		$difference = round($difference);
		if(!$difference) return $justNow;

		$periods = $difference != 1 ? $this->getPeriods($abbreviate, true) : $this->getPeriods($abbreviate, false);
		$period = $periods[$j];

		// return sprintf('%s%d%s%s %s', $prepend, (int) $difference, $space, $period, $tense); // i.e. 2 days ago (d=qty, 2=period, 3=tense)
		$quantity = $prepend . $difference . $space;
		$format = $this->_('Q P T'); // Relative time order: Q=Quantity, P=Period, T=Tense (i.e. 2 Days Ago)
		$format = str_replace(array('Q', 'P', 'T'), array('{Q}', '{P}', '{T}'), $format);
		$out = str_replace(array('{Q}', '{P}', '{T}'), array(" $quantity", " $period", " $tense"), $format);
		
		if($abbreviate === 1) {
			$out = str_replace(" ", "", $out); // no space when extra-abbreviate is active
		} else if(strpos($out, '  ') !== false) {
			$out = preg_replace('/\s\s+/', ' ', $out);
		}
		
		return trim($out);
	}

	/**
	 * Render an elapsed time string
	 * 
	 * If the `$stop` argument is omitted then it is assumed to be the current time. 
	 * The maximum period used is weeks, as months and years are not fixed length periods. 
	 * 
	 * ~~~~~
	 * $start = '2023-09-08 08:33:52';
	 * $stop = '2023-09-09 10:47:23';
	 * 
	 * // Regular: 1 day 2 hours 13 minutes 31 seconds
	 * echo $datetime->elapsedTimeStr($start, $stop);
	 *
	 * // Abbreviated: 1 day 2 hrs 13 mins 31 secs 
	 * echo $datetime->elapsedTimeStr($start, $stop, true);
	 * 
	 * // Abbreviated with exclusions: 1 day 2 hrs
	 * echo $datetime->elapsedTimeStr($start, $stop, true, [ 'exclude' => 'minutes seconds' ]);
	 * 
	 * // Optional 3.0.227+ usage and inclusions: 26 hours 13 minutes
	 * echo $datetime->elapsedTimeStr($start, [ 'stop' => $stop, 'include' => 'hours minutes' ]);
	 * ~~~~~
	 * 
	 * @param int|string $start Starting timestamp or date/time string.
	 * @param int|string|array $stop Ending timestamp or date/time string, omit for “now”, or: 
	 *  - In 3.0.227+ you may optionally substitute the `$options` array argument here. When doing so, 
	 *    all remaining arguments are ignored and the `stop` and `abbreviate` (if needed) may be 
	 *    specified in the given options array, along with any other options.
	 * @param bool|int|array $abbreviate
	 *  - Specify boolean FALSE for verbose elapsed time string without abbreviations (default). 
	 *  - Specify boolean TRUE for abbreviations (abbreviated where common, not always different from non-abbreviated).
	 *  - Specify integer `1` for extra short abbreviations (all terms abbreviated into shortest possible string).
	 *  - Specify integer `0` for digital elapsed time string like “00:01:12” referring to hours:minutes:seconds.
	 *  - Note that when using `0` no options apply except except for `exclude[seconds]` option.
	 * @param array $options Additional options:
	 *  - `delimiter` (string): String to separate time periods (default=' ').
	 *  - `getArray` (bool): Return an array of integers indexed by period name (rather than a string)? 3.0.227+
	 *  - `exclude` (array|string): Exclude these periods, one or more of: 'seconds', 'minutes', 'hours', 'days', 'weeks' (default=[])
	 *  - `include` (array|string): Include only these periods, one or more of: 'seconds', 'minutes', 'hours', 'days', 'weeks' (default=[]) 3.0.227+
	 *  - Note the exclude and include options should not be used together, and the include option requires 3.0.227+. 
	 * @return string|array Returns array only if the `getArray` option is true, otherwise returns a string.
	 * @since 3.0.129
	 * 
	 */
	public function elapsedTimeStr($start, $stop = null, $abbreviate = false, array $options = array()) {
		
		$defaults = array(
			'delimiter' => ' ', 
			'exclude' => array(),
			'include' => array(),
			'getArray' => false, 
		);
		
		if(is_array($stop)) {
			// options specified in $stop argument 
			$options = $stop;
			$stop = isset($options['stop']) ? $options['stop'] : null;
			$abbreviate = isset($options['abbreviate']) ? $options['abbreviate'] : false;
		}

		$options = array_merge($defaults, $options);
		$periodNames = array('weeks', 'days', 'hours', 'minutes', 'seconds');
		$usePeriods = array();
		$negative = false;
		
		foreach(array('exclude', 'include') as $key) {
			if(is_string($options[$key])) {
				$value = trim($options[$key]);
				$options[$key] = $value ? explode(' ', $value) : array();
			} else if(!is_array($options[$key])) {
				$options[$key] = array();
			}
			foreach($options[$key] as $k => $v) {
				if(!in_array($v, $periodNames)) unset($options[$key][$k]); 
			}
		}
		
		$include = count($options['include']) && $abbreviate !== 0 ? $options['include'] : null;
		$exclude = count($options['exclude']) ? $options['exclude'] : null;
		
		foreach($periodNames as $name) {
			if($include && !in_array($name, $include)) continue;
			if($exclude && in_array($name, $exclude)) continue;
			$usePeriods[$name] = $name;
		}
		
		if($stop === null) $stop = time();
		if(!ctype_digit("$start")) $start = strtotime($start);
		if(!ctype_digit("$stop")) $stop = strtotime($stop);
		
		if($start > $stop) {
			list($start, $stop) = array($stop, $start);
			$negative = true;
		}

		$times = array();
		$seconds = $stop - $start;
	
		if($seconds >= 604800 && $abbreviate !== 0 && isset($usePeriods['weeks'])) {
			$weeks = floor($seconds / 604800);
			$seconds = $seconds - ($weeks * 604800);
			$key = $weeks == 1 ? 'week' : 'weeks';
			$times[$key] = $weeks;
		} else {
			$times['weeks'] = 0;
		}

		if($seconds >= 86400 && $abbreviate !== 0 && isset($usePeriods['days'])) {
			$days = floor($seconds / 86400); 
			$seconds = $seconds - ($days * 86400); 
			$key = $days == 1 ? 'day' : 'days';
			$times[$key] = $days;
		} else {
			$times['days'] = 0;
		}

		if($seconds >= 3600 && isset($usePeriods['hours'])) {
			$hours = floor($seconds / 3600);
			$seconds = $seconds - ($hours * 3600);
			$key = $hours == 1 ? 'hour' : 'hours';
			$times[$key] = $hours;
		} else {
			$times['hours'] = 0;
			$hours = 0;
		}

		if($seconds >= 60 && isset($usePeriods['minutes'])) {
			$minutes = floor($seconds / 60);
			$seconds = $seconds - ($minutes * 60);
			$key = $minutes == 1 ? 'minute' : 'minutes';
			$times[$key] = $minutes;
		} else {
			$times['minutes'] = 0;
			$minutes = 0;
		}

		if(($seconds > 0 || empty($times)) && isset($usePeriods['seconds'])) {
			$key = $seconds == 1 ? 'second' : 'seconds';
			$times[$key] = $seconds;
		} else {
			$times['seconds'] = 0;
			$seconds = 0;
		}
		
		if($abbreviate === 0 && !$options['getArray']) {
			if(strlen($hours) < 2) $hours = "0$hours";
			if(strlen($minutes) < 2) $minutes = "0$minutes";
			if(strlen($seconds) < 2) $seconds = "0$seconds";
			$value = "$hours:$minutes";
			if(isset($usePeriods['seconds'])) $value .= ":$seconds";
		} else {
			$periods = $this->getPeriods($abbreviate); 
			$a = array();
			$getArray = array();
			foreach($times as $key => $qty) {
				$pluralKey = rtrim($key, 's') . 's';
				if(empty($qty) && ($pluralKey !== 'seconds' || count($a))) continue;
				if(!isset($usePeriods[$pluralKey])) continue;
				$sep = $abbreviate === 1 ? '' : ' ';
				$str = $qty . $sep . $periods[$key];
				$a[] = $str;
				$getArray[$pluralKey] = $qty;
				$getArray[$pluralKey . 'Text'] = $str;
			}
			$value = implode($options['delimiter'], $a);
			if($negative) $value = "-$value";
			if($options['getArray']) {
				$getArray['negative'] = $negative;
				$getArray['text'] = $value;
				$value = $getArray;
			}
		}

		return $value;
	}

	/**
	 * Get named time periods
	 * 
	 * Returns regular array(s) of periods in this order: 
	 * seconds, minutes, hours, days, weeks, months, years decades
	 * 
	 * If $plural argument is null (or omitted) it instead returns an array
	 * indexed by period name including both singular and plural periods. 
	 * 
	 * @param $abbreviate
	 *  - Specify 1 to get shortest possible abbreviations 
	 *  - Specify true to get standard/medium abbreviations
	 *  - Specify false to get large/full terms (no abbreviations)
	 *  - Specify associative array to get large/full terms and substitute your own
	 * @param null|true|int $plural 
	 *  - Specify true to get plural, 
	 *  - Specify false to get singular, 
	 *  - Specify 1 to get array where [ 0 => [singulars], 1 => [plurals] ] 
	 *  - Omit (or null) to get all in an indexed array
	 * @return array
	 * 
	 */
	protected function getPeriods($abbreviate, $plural = null) {
		
		static $definitions = array();
		if(empty($definitions)) $definitions = array(
			'keys-singular' => array(
				'second', 
				'minute', 
				'hour', 
				'day', 
				'week', 
				'month', 
				'year', 
				'decade'
			),
			'keys-plural' => array(
				'seconds', 
				'minutes', 
				'hours', 
				'days', 
				'weeks', 
				'months', 
				'years', 
				'decades'
			),
			'short-singular' => array(
				$this->_("s"),
				$this->_("m"),
				$this->_("hr"),
				$this->_("d"),
				$this->_("wk"),
				$this->_("mon"),
				$this->_("yr"),
				$this->_("decade")
			),
			'short-plural' => array(
				$this->_("s"),
				$this->_("m"),
				$this->_("hr"),
				$this->_("d"),
				$this->_("wks"),
				$this->_("mths"),
				$this->_("yrs"),
				$this->_("decades")
			),
			'medium-singular' => array(
				$this->_("sec"),
				$this->_("min"),
				$this->_("hr"),
				$this->_("day"),
				$this->_("week"),
				$this->_("month"),
				$this->_("year"),
				$this->_("decade")
			),
			'medium-plural' => array(
				$this->_("secs"),
				$this->_("mins"),
				$this->_("hrs"),
				$this->_("days"),
				$this->_("weeks"),
				$this->_("months"),
				$this->_("years"),
				$this->_("decades")
			),
			'large-singular' => array(
				$this->_("second"),
				$this->_("minute"),
				$this->_("hour"),
				$this->_("day"),
				$this->_("week"),
				$this->_("month"),
				$this->_("year"),
				$this->_("decade")
			),
			'large-plural' => array(
				$this->_("seconds"),
				$this->_("minutes"),
				$this->_("hours"),
				$this->_("days"),
				$this->_("weeks"),
				$this->_("months"),
				$this->_("years"),
				$this->_("decades")
			),
		);
		
		if($abbreviate === 1) {
			// extra short abbreviations
			$periodsPlural = $definitions['short-plural'];
			$periodsSingular = $definitions['short-singular'];
		} else if($abbreviate === true) {
			// standard abbreviations
			$periodsPlural = $definitions['medium-plural'];	
			$periodsSingular = $definitions['medium-singular'];
		} else {
			// no abbreviations
			$periodsPlural = $definitions['large-plural'];
			$periodsSingular = $definitions['large-singular'];
			// merge in any user-supplied abbreviations
			if(is_array($abbreviate)) {
				foreach($definitions['keys-plural'] as $key => $term) {
					if(isset($abbreviate[$term])) $periodsPlural[$key] = $abbreviate[$term];
				}
				foreach($definitions['keys-singular'] as $key => $term) {
					if(isset($abbreviate[$term])) $periodsSingular[$key] = $abbreviate[$term];
				}
			}
		}
		
		if($plural === true) return $periodsPlural;
		if($plural === false) return $periodsSingular;
	
		// get all indexed by term
		$periods = array();
		foreach($definitions['keys-plural'] as $key => $term) {
			$periods[$term] = $periodsPlural[$key];
		}
		foreach($definitions['keys-singular'] as $key => $term) {
			$periods[$term] = $periodsSingular[$key];
		}
		
		return $periods;
	}

	/**
	 * Localize a date's month and day names, when present
	 *
	 * @param string $value Date that is already formated with $format
	 * @param string $format Format that was used to format the date
	 * @return string
	 * 
	 */
	protected function localizeDateText($value, $format) {
		
		if(!$this->wire()->languages) return $value; 

		$findReplace = [];
		$f = $format;
		$keys = array();
		$ltrKeys = array(
			'F' => 'monthNames',
			'M' => 'monthAbbrs',
			'l' => 'dayNames',
			'D' => 'dayAbbrs',
			'a' => 'meridiums',
			'A' => 'meridiums',
		);
		
		foreach($ltrKeys as $ltr => $key) {
			$a = self::$dateConversion[$ltr];
			if(strpos($f, $ltr) !== false || strpos($f, $a[0]) !== false || strpos($f, $a[1]) !== false) {
				$keys[] = $key;
			}
		}
		
		foreach($keys as $key) {
			$findReplace = array_merge($findReplace, $this->dateWords($key));
		}

		if(count($findReplace)) {
			$find = array_keys($findReplace);
			$replace = array_values($findReplace);
			if($find != $replace) {
				$value = str_replace($find, $replace, $value);
			}
		}

		return $value;
	}

	/**
	 * Get translated month/day words indexed by English words
	 * 
	 * @param string $key One of: monthNames, monthAbbrs, dayNames, dayAbbrs, meridiums
	 * @return array
	 * 
	 */
	protected function dateWords($key) {
		$k = $key . $this->wire()->user->language->id;
		switch($key) {
			case 'monthNames':
				if(empty($this->caches[$k])) $this->caches[$k] = array(
					'January' => $this->_('January'),
					'February' => $this->_('February'),
					'March' => $this->_('March'),
					'April' => $this->_('April'),
					'May' => $this->_('May'),
					'June' => $this->_('June'),
					'July' => $this->_('July'),
					'August' => $this->_('August'),
					'September' => $this->_('September'),
					'October' => $this->_('October'),
					'November' => $this->_('November'),
					'December' => $this->_('December'),
				);
				return $this->caches[$k];
			case 'monthAbbrs':
				if(empty($this->caches[$k])) $this->caches[$k] = array(
					'Jan' => $this->_x('Jan', 'month-abbr'),
					'Feb' => $this->_x('Feb', 'month-abbr'),
					'Mar' => $this->_x('Mar', 'month-abbr'),
					'Apr' => $this->_x('Apr', 'month-abbr'),
					'May' => $this->_x('May', 'month-abbr'),
					'Jun' => $this->_x('Jun', 'month-abbr'),
					'Jul' => $this->_x('Jul', 'month-abbr'),
					'Aug' => $this->_x('Aug', 'month-abbr'),
					'Sep' => $this->_x('Sep', 'month-abbr'),
					'Oct' => $this->_x('Oct', 'month-abbr'),
					'Nov' => $this->_x('Nov', 'month-abbr'),
					'Dec' => $this->_x('Dec', 'month-abbr'),
				);
				return $this->caches[$k];
			case 'dayNames':
				if(empty($this->caches[$k])) $this->caches[$k] = array(
					'Monday' => $this->_('Monday'),
					'Tuesday' => $this->_('Tuesday'),
					'Wednesday' => $this->_('Wednesday'),
					'Thursday' => $this->_('Thursday'),
					'Friday' => $this->_('Friday'),
					'Saturday' => $this->_('Saturday'),
					'Sunday' => $this->_('Sunday'),
				);
				return $this->caches[$k];
			case 'dayAbbrs':
				if(empty($this->caches[$k])) $this->caches[$k] = array(
					'Mon' => $this->_x('Mon', 'day-abbr'),
					'Tue' => $this->_x('Tue', 'day-abbr'),
					'Wed' => $this->_x('Wed', 'day-abbr'),
					'Thu' => $this->_x('Thu', 'day-abbr'),
					'Fri' => $this->_x('Fri', 'day-abbr'),
					'Sat' => $this->_x('Sat', 'day-abbr'),
					'Sun' => $this->_x('Sun', 'day-abbr'),
				);
				return $this->caches[$k];
			case 'meridiums':
				if(empty($this->caches[$k])) $this->caches[$k] = array(
					'am' => $this->_x('am', 'ante-meridium-lowercase'),
					'pm' => $this->_x('pm', 'post-meridium-lowercase'),
					'AM' => $this->_x('AM', 'ante-meridium-uppercase'),
					'PM' => $this->_x('PM', 'post-meridium-uppercase'),
				);
				return $this->caches[$k];
		}
		return array();
	}

	public function isStrtotime($str) {
	}
}
