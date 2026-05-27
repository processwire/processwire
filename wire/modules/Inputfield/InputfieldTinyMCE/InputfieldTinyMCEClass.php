<?php namespace ProcessWire;

/**
 * InputfieldTinyMCEClass
 *
 * Helper for managing TinyMCE settings and defaults
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @property InputfieldTinyMCE $inputfield
 *
 */
abstract class InputfieldTinyMCEClass extends Wire {

	/**
	 * @var InputfieldTinyMCE
	 *
	 */
	protected $inputfield;

	/**
	 * Construct
	 *
	 * @param InputfieldTinyMCE $inputfield
	 *
	 */
	public function __construct(InputfieldTinyMCE $inputfield) {
		$this->inputfield = $inputfield;
		$inputfield->wire($this);
		parent::__construct();
	}
	
	/**
	 * Get
	 *
	 * @param $key
	 * @return array|mixed|string|null
	 *
	 */
	public function __get($name) {
		switch($name) {
			case 'inputfield': return $this->inputfield;
			case 'tools':
			case 'settings':
			case 'configs':
			case 'formats': return $this->inputfield->helper($name);
		}
		return parent::get($name);
	}


	/**
	 * @return InputfieldTinyMCETools
	 * 
	 */
	public function tools() {
		return $this->inputfield->helper('tools');
	}

	/**
	 * @return InputfieldTinyMCEConfigs
	 *
	 */
	public function configs() {
		return $this->inputfield->helper('configs');
	}

	/**
	 * @return InputfieldTinyMCESettings
	 *
	 */
	public function settings() {
		return $this->inputfield->helper('settings');
	}

	/**
	 * @return InputfieldTinyMCEFormats
	 *
	 */
	public function formats() {
		return $this->inputfield->helper('formats');
	}
}
