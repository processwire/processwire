<?php namespace ProcessWire;

/**
 * ProcessWire Fuel
 *
 * Fuel maintains a single instance each of multiple objects used throughout the application.
 * The objects contained in fuel provide access to the ProcessWire API. For instance, $pages,
 * $users, $fields, and so on. The fuel is required to keep the system running, so to speak.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property ProcessWire $wire
 * @property Database $db
 * @property WireDatabasePDO $database
 * @property Session $session
 * @property Notices $notices
 * @property Sanitizer $sanitizer
 * @property Fields $fields
 * @property Fieldtypes $fieldtypes
 * @property Fieldgroups $fieldgroups
 * @property Templates $templates
 * @property Pages $pages
 * @property Page $page
 * @property Process $process
 * @property Modules $modules
 * @property Permissions $permissions
 * @property Roles $roles
 * @property Users $users
 * @property User $user
 * @property WireCache $cache
 * @property WireInput $input
 * @property Languages $languages If LanguageSupport installed
 * @property Config $config
 * @property Fuel $fuel
 * @property WireProfilerInterface $profiler 
 *
 */
class Fuel implements \IteratorAggregate {

	/**
	 * Fuel items indexed by name
	 * 
	 * @var array
	 * 
	 */
	protected $data = array();

	/**
	 * Array where name is item name, and value is bool as to whether it's locked or not
	 * 
	 * @var array
	 * 
	 */
	protected $lock = array();

	/**
	 * API vars that require specific interfaces
	 * 
	 * @var array
	 * 
	 */
	protected $requiredInterfaces = array(
		'profiler' => 'WireProfilerInterface'
	);
	
	/**
	 * @param string $key API variable name to set - should be valid PHP variable name.
	 * @param object|mixed $value Value for the API variable.
	 * @param bool $lock Whether to prevent this API variable from being overwritten in the future.
	 * @return $this
	 * @throws WireException When you try to set a previously locked API variable, a WireException will be thrown.
	 * 
	 */
	public function set($key, $value, $lock = false) {
		if(isset($this->lock[$key]) && $value !== $this->data[$key]) {
			throw new WireException("API variable '$key' is locked and may not be set again"); 
		}
		if(isset($this->requiredInterfaces[$key])) {
			$requiredInterface = $this->requiredInterfaces[$key];
			$hasInterfaces = wireClassImplements($value, false);
			if(!isset($hasInterfaces[$requiredInterface]) && !in_array($requiredInterface, $hasInterfaces)) {
				throw new WireException("API variable '$key' must implement interface: $requiredInterface"); 
			}
		}
		$this->data[$key] = $value; 
		if($lock) $this->lock[$key] = true;
		return $this;
	}

	/**
	 * Remove an API variable from the Fuel
	 * 
	 * @param $key
	 * @return bool Returns true on success
	 * 
	 */
	public function remove($key) {
		if(isset($this->data[$key])) {
			unset($this->data[$key]);
			unset($this->lock[$key]);
			return true;
		}
		return false;
	}

	public function __get($key) {
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function getIterator() {
		return new \ArrayObject($this->data); 
	}

	public function getArray() {
		return $this->data; 
	}
	
}
