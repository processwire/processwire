<?php namespace ProcessWire;

/**
 * ProcessWire Textformatter
 *
 * Provides the base class for Textformatting Modules
 * 
 * #pw-summary A simple module type that provides formatting of text fields. 
 * #pw-body Please see the base `Module` interface for all potential methods that a Textformatter module can have. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 */

abstract class Textformatter extends WireData implements Module {

	/**
	 * Return an array of module information
	 *
	 * Array is associative with the following fields: 
	 * - title: An alternate title, if you don't want to use the class name.
	 * - version: an integer that indicates the version number, 101 = 1.0.1
	 * - summary: a summary of the module (1 paragraph max)
	 *
	 * @return array
	 *
	public static function getModuleInfo() {
		// just an example, should be overridden
		return array(
			'title' => 'Unknown Textformatter', 
			'version' => 0, 
			'summary' => '', 
			); 
	}
	 */

	/**
	 * Format the given text string, outside of specific Page or Field context.
	 * 
	 * @param string $str String is provided as a reference, so is modified directly (not returned). 
	 *
	 */
	public function format(&$str) { }

	/**
	 * Format the given text string with Page and Field provided.
	 *
	 * Module developers may override this function completely when providing your own text formatter. No need to call the parent.
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param string|mixed $value Value is provided as a reference, so is modified directly (not returned). 
	 *
	 */
	public function formatValue(Page $page, Field $field, &$value) {
		$this->format($value); 
	}

	/**
	 * Optional method to initialize the module. 
	 *
	 * This is called after ProcessWire's API is fully ready for use and hooks
	 * 
	 * #pw-internal
	 *
	 */
	public function init() { }

	/**
	 * Perform any installation procedures specific to this module, if needed. 
	 * 
	 * #pw-internal
	 *
	 */
	public function ___install() { }

	/**
	 * Perform any uninstall procedures specific to this module, if needed. 
	 * 
	 * #pw-internal
	 *
	 */
	public function ___uninstall() { }

	/**
	 * Only one instatance of a textformatter is loaded at runtime
	 * 
	 * #pw-internal
	 *
	 */
	public function isSingular() {
		return true; 
	}

	/**
	 * Textformatters are not autoload, in that they don't load until requested by the api. 
	 * 
	 * #pw-internal
	 *
	 */
	public function isAutoload() {
		return false; 
	}
}
