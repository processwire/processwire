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
	 * Sanitize value to unix timestamp integer or blank string (to represent no value)
	 * 
	 * @param string|int $value
	 * @return int|string
	 * 
	 */
	public function sanitizeValue($value) {
		if(is_int($value) || ctype_digit("$value")) return (int) $value; 
		if(empty($value)) return '';
		return strtotime($value);
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