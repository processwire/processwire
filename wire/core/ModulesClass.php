<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Class
 * 
 * Base for Modules helper classes. 
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

abstract class ModulesClass extends Wire {

	/**
	 * @var Modules
	 *
	 */
	protected $modules;

	/**
	 * Debug mode?
	 *
	 * @var bool
	 *
	 */
	protected $debug = false;

	/**
	 * Construct
	 *
	 * @param Modules $modules
	 */
	public function __construct(Modules $modules) {
		$this->modules = $modules;
		$modules->wire($this);
		parent::__construct();
	}

	/**
	 * Convert given value to module ID
	 *
	 * @param string|int|Module $name
	 * @return int Returns 0 if module not found
	 *
	 */
	protected function moduleID($name) {
		return $this->modules->moduleID($name);
	}

	/**
	 * Convert given value to module name
	 *
	 * @param int|string|Module $id
	 * @return string Returns blank string if not found
	 *
	 */
	protected function moduleName($id) {
		return $this->modules->moduleName($id);
	}
	
	/**
	 * Save to the modules log
	 *
	 * @param string $str Message to log
	 * @param array|string $options Specify module name (string) or options array
	 * @return WireLog
	 *
	 */
	public function log($str, $options = array()) {
		return $this->modules->log($str, $options);
	}
	
	/**
	 * Record and log error message
	 *
	 * #pw-internal
	 *
	 * @param array|Wire|string $text
	 * @param int $flags
	 * @return Modules|WireArray
	 *
	 */
	public function error($text, $flags = 0) {
		return $this->modules->error($text, $flags);
	}

	public function getDebugData() {
		return array();
	}
}
