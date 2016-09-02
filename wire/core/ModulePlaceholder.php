<?php namespace ProcessWire;

/**
 * ProcessWire ModulePlaceholder
 *
 * Holds the place for a Module until it is included and instantiated.
 * As used by the Modules class. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://.processwire.com
 * 
 * @property bool $autoload
 * @property bool $singular
 * @property string $file
 * @property string $className
 * @property string $class alias of className
 * @property string $name alias of className
 *
 */

class ModulePlaceholder extends WireData implements Module {

	protected $class = '';
	protected $ns = '';
	protected $moduleInfo = array();

	public function __construct() {
		$this->set('autoload', false); 
		$this->set('singular', true); 
		$this->set('file', ''); 
	}

	static public function getModuleInfo() {
		return array(
			'title' => 'ModulePlaceholder: call $modules->get(class) to replace this placeholder.',  
			'version' => 0, 
			'summary' => '', 
			);
	}

	public function init() { }
	public function ___install() { }
	public function ___uninstall() { }

	public function setClass($class) {
		$this->class = $class; 
	}
	
	public function setNamespace($ns) {
		$this->ns = $ns;
	}

	public function get($key) {
		if($key == 'className' || $key == 'class' || $key == 'name') return $this->class;
		return parent::get($key); 
	}

	public function isSingular() {
		return $this->singular; 
	}

	public function isAutoload() {
		return false; 
	}

	public function className($options = null) {
		if($options === true || !empty($options['namespace'])) {
			return trim($this->ns, '\\') . '\\' . $this->class;
		}
		return $this->class; 
	}

}

