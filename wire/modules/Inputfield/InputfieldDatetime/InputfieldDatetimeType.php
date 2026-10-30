<?php namespace ProcessWire;

abstract class InputfieldDatetimeType extends WireData {

	/**
	 * @var InputfieldDatetime
	 * 
	 */
	protected $inputfield;

	/**
	 * Construct
	 * 
	 * @param InputfieldDatetime $inputfield
	 * 
	 */
	public function __construct(InputfieldDatetime $inputfield) {
		$this->inputfield = $inputfield;
		parent::__construct();
	}

	/**
	 * Get name for this type
	 * 
	 * @return string
	 * 
	 */
	public function getTypeName() {
		return strtolower(str_replace('InputfieldDatetime', '', $this->className()));
	}

	/**
	 * Get type label 
	 * 
	 * @return string
	 * 
	 */
	public function getTypeLabel() {
		return str_replace('InputfieldDatetime', '', $this->className());
	}

	/**
	 * Get attribute
	 * 
	 * @param string $key
	 * @return string|null
	 * 
	 */
	public function getAttribute($key) {
		return $this->inputfield->getAttribute($key);
	}

	/**
	 * Get attribute
	 * 
	 * @param string $key
	 * @param string $value
	 * @return self
	 * 
	 */
	public function setAttribute($key, $value) {
		$this->inputfield->setAttribute($key, $value);
		return $this;
	}
	
	/**
	 * Get setting
	 * 
	 * @param string $key
	 * @return mixed
	 * 
	 */
	public function getSetting($key) {
		return $this->inputfield->getSetting($key);
	}

	/**
	 * Get setting or attribute or API var
	 * 
	 * @param string $key
	 * @return mixed|null
	 * 
	 */
	public function get($key) {
		return $this->inputfield->get($key);
	}
	
	/**
	 * Get array of default settings
	 *
	 * @return array
	 *
	 */
	public function getDefaultSettings() {
		return array();
	}

	/**
	 * @return string
	 *
	 */
	public function renderValue() {
		return '';
	}

	/**
	 * Sanitize value to UNIX timestamp integer, blank string, or false when unparseable
	 * 
	 * @param string|int $value
	 * @return int|string|bool
	 * 
	 */
	public function sanitizeValue($value) {
		if(is_int($value) || ctype_digit("$value")) return (int) $value; 
		if(empty($value)) return '';
		return strtotime($value);
	}

	/**
	 * Parse submitted input without the timestamp shortcut or flexible strtotime fallback.
	 *
	 * @param string $value
	 * @param string $format PHP date() format
	 * @return int|false Timestamp, or false for invalid input
	 *
	 */
	protected function parseInputValue($value, $format) {
		if(!strlen($format) || strpos($value, "\0") !== false) return false;

		// Expand composite date() formats and escape literals that have a special parser meaning.
		$parseFormat = '';
		$fullYear = false;
		for($n = 0; $n < strlen($format); $n++) {
			$c = $format[$n];
			if($c === '\\' && isset($format[$n + 1])) {
				$parseFormat .= $c . $format[++$n];
			} else if($c === 'c') {
				$parseFormat .= 'Y-m-d\\TH:i:sP';
				$fullYear = true;
			} else if($c === 'r') {
				$parseFormat .= 'D, d M Y H:i:s O';
				$fullYear = true;
			} else {
				$parseFormat .= strpos('!|+?*#', $c) === false ? $c : "\\$c";
				if($c === 'Y') $fullYear = true;
			}
		}

		$parts = date_parse_from_format($parseFormat, $value);
		if($parts['error_count'] || $parts['warning_count']) return false;
		// PHP accepts short years for Y, turning "26" into year 0026 rather than 2026.
		if($fullYear && $parts['year'] !== false && $parts['year'] < 1000) return false;
		if($parts['year'] === false && $parts['month'] === false && $parts['day'] === false) {
			// Keep text time-only input relative to today rather than the Unix epoch.
			$parseFormat = 'Y-m-d ' . $parseFormat;
			$value = date('Y-m-d') . ' ' . $value;
		} else if($parts['year'] === false) {
			// Formats that omit the year continue to use the current year.
			$parseFormat = 'Y ' . $parseFormat;
			$value = date('Y') . ' ' . $value;
		}
		$date = \DateTimeImmutable::createFromFormat($parseFormat . '|', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		if($date === false || ($errors && ($errors['error_count'] || $errors['warning_count']))) return false;

		// Also reject silent weekday adjustments and nonexistent local times (DST gaps).
		foreach(['year' => 'Y', 'month' => 'n', 'day' => 'j', 'hour' => 'G', 'minute' => 'i', 'second' => 's'] as $part => $token) {
			if($parts[$part] !== false && (int) $date->format($token) !== $parts[$part]) return false;
		}

		return $date->getTimestamp();
	}

	/**
	 * Report invalid submitted input and retain the existing value.
	 *
	 * @param string $format Expected PHP date() format
	 * @param string|null $value Submitted value, or null for malformed non-scalar input
	 * @return int|string
	 *
	 */
	protected function invalidInput($format, $value = null) {
		$example = $this->wire()->datetime->date($format);
		if($value === null) {
			$message = sprintf($this->_('Invalid date/time. Please use this format: %s'), $example);
		} else {
			$value = function_exists('mb_substr') ? mb_substr($value, 0, 200, 'UTF-8') : substr($value, 0, 200);
			$value = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			// Quote the input without allowing HTML or Markdown in formatted field errors.
			$value = strtr($value === false ? '"?"' : $value, ['[' => '\\u005B', ']' => '\\u005D', '`' => '\\u0060', '*' => '\\u002A', '_' => '\\u005F', '~' => '\\u007E', '://' => '\\u003A//']);
			$message = sprintf($this->_('Invalid date/time %1$s. Please use this format: %2$s'), $value, $example);
		}
		$this->inputfield->error($message);
		return $this->getAttribute('value');
	}

	/**
	 * Render ready
	 * 
	 */
	abstract public function renderReady();

	/**
	 * @return string
	 * 
	 */
	abstract public function render();

	/**
	 * Process input
	 * 
	 * @param WireInputData $input
	 * @return int|string|bool Int for UNIX timestamp date, blank string for no date, or boolean false if InputfieldDatetime should process input
	 * 
	 */
	abstract public function processInput(WireInputData $input);

	/**
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	abstract public function getConfigInputfields(InputfieldWrapper $inputfields);
	
}
