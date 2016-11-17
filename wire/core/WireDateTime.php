<?php namespace ProcessWire;

/**
 * ProcessWire Date/Time Tools ($datetime API variable)
 * 
 * #pw-summary The $datetime API variable provides helpers for working with dates/times and conversion between formats.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
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
		'j' => array(	'$-d', 	'd', 	'\d{1,2}'),			// Day without leading zeros		1
		'd' => array(	'$d', 	'dd', 	'\d{2}'),			// Day with leading zeros		01
		'n' => array(	'$-m', 	'm', 	'\d{1,2}'),			// Month without leading zeros		4
		'm' => array(	'$m', 	'mm', 	'\d{2}'),			// Month with leading zeros		04
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
		if(ctype_digit(ltrim($str, '-'))) return (int) $str;

		$format = trim($format);
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
		if(!strlen($format) || $format == 'U' || $format == '%s') return (int) $value; // unix timestamp
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
			$value = strftime($format, $value);
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
		if($format == '') $format = $this->wire('config')->dateFormat;
		if($format == 'relative') $value = $this->relativeTimeStr($ts);
			else if($format == 'relative-') $value = $this->relativeTimeStr($ts, false, false);
			else if($format == 'rel') $value = $this->relativeTimeStr($ts, true);
			else if($format == 'rel-') $value = $this->relativeTimeStr($ts, true, false);
			else if($format == 'r') $value = $this->relativeTimeStr($ts, 1);
			else if($format == 'r-') $value = $this->relativeTimeStr($ts, 1, false);
			else if($format == 'ts') $value = $ts;
			else if(strpos($format, '%') !== false) $value = strftime($format, $ts);
			else $value = date($format, $ts);
		return $value;
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
	public function relativeTimeStr($ts, $abbreviate = false, $useTense = true) {
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

			$periodsSingular = array(
				$this->_("s"),
				$this->_("m"),
				$this->_("hr"),
				$this->_("d"),
				$this->_("wk"),
				$this->_("mon"),
				$this->_("yr"),
				$this->_("decade")
			);

			$periodsPlural = array(
				$this->_("s"),
				$this->_("m"),
				$this->_("hr"),
				$this->_("d"),
				$this->_("wks"),
				$this->_("mths"),
				$this->_("yrs"),
				$this->_("decades")
			);

		} else if($abbreviate === true) {
			// standard abbreviations

			$justNow = $this->_('now');
			$fromNow = '';
			$prependFromNow = $this->_('in') . ' ';

			$periodsSingular = array(
				$this->_("sec"),
				$this->_("min"),
				$this->_("hr"),
				$this->_("day"),
				$this->_("week"),
				$this->_("month"),
				$this->_("year"),
				$this->_("decade")
			);

			$periodsPlural = array(
				$this->_("secs"),
				$this->_("mins"),
				$this->_("hrs"),
				$this->_("days"),
				$this->_("weeks"),
				$this->_("months"),
				$this->_("years"),
				$this->_("decades")
			);

		} else {
			// no abbreviations

			$periodsSingular = array(
				$this->_("second"),
				$this->_("minute"),
				$this->_("hour"),
				$this->_("day"),
				$this->_("week"),
				$this->_("month"),
				$this->_("year"),
				$this->_("decade")
			);

			$periodsPlural = array(
				$this->_("seconds"),
				$this->_("minutes"),
				$this->_("hours"),
				$this->_("days"),
				$this->_("weeks"),
				$this->_("months"),
				$this->_("years"),
				$this->_("decades")
			);

			if(is_array($abbreviate)) {
				// possible user specified abbreviations for replacements
				$keys1 = array('second', 'minute', 'hour',  'day', 'week', 'month', 'year', 'decade');
				$keys2 = array('seconds', 'minutes', 'hours',  'days', 'weeks', 'months', 'years', 'decades');
				foreach($keys1 as $key => $term) {
					if(isset($abbreviate[$term])) $periodsSingular[$key] = $abbreviate[$term];
				}
				foreach($keys2 as $key => $term) {
					if(isset($abbreviate[$term])) $periodsPlural[$key] = $abbreviate[$term];
				}
				if(isset($abbreviate['just now'])) $justNow = $abbreviate['just now'];
				if(isset($abbreviate['from now'])) $fromNow = $abbreviate['from now'];
				if(isset($abbreviate['ago'])) $ago = $abbreviate['ago'];
			}
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

		$periods = $difference != 1 ? $periodsPlural : $periodsSingular;
		$period = $periods[$j];

		// return sprintf('%s%d%s%s %s', $prepend, (int) $difference, $space, $period, $tense); // i.e. 2 days ago (d=qty, 2=period, 3=tense)
		$quantity = $prepend . $difference . $space;
		$format = $this->_('Q P T'); // Relative time order: Q=Quantity, P=Period, T=Tense (i.e. 2 Days Ago)
		$format = str_replace(array('Q', 'P', 'T'), array('{Q}', '{P}', '{T}'), $format);
		$out = str_replace(array('{Q}', '{P}', '{T}'), array(" $quantity", " $period", " $tense"), $format);
		if($abbreviate === 1) $out = str_replace(" ", "", $out); // no space when extra-abbreviate is active
		else if(strpos($out, '  ') !== false) $out = preg_replace('/\s\s+/', ' ', $out);
		return trim($out);
	}


}