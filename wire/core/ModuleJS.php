<?php namespace ProcessWire;

/**
 * ProcessWire ModuleJS
 *
 * An abstract module intended as a base for modules needing to autoload JS or CSS files. 
 *
 * If you extend this, double check that the default isSingular() and isAutoload() methods 
 * are doing what you want -- you may want to override them. 
 * 
 * See the Module interface (Module.php) for details about each method. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @method ModuleJS use(string $name)
 *
 */

abstract class ModuleJS extends WireData implements Module {

	/**
	 * Per the Module interface, return an array of information about the Module
	 *
 	 */
	public static function getModuleInfo() {
		return array(
			'title' => '',		// printable name/title of module
			'version' => 1, 	// version number of module
			'summary' => '', 	// 1 sentence summary of module
			'href' => '', 		// URL to more information (optional)
			'permanent' => false, 	// true if module is permanent and thus not uninstallable
			); 
	}


	/**
	 * Array of component names to filenames
	 *
	 * @var array
	 *
	 */
	protected $components = array();

	/**
	 * Components that have been requested
	 *
	 * @var array
	 *
	 */
	protected $requested = array();

	/**
	 * True after module has been init'd, required by add()
	 *
	 * @var bool
	 *
	 */
	protected $initialized = false;

	/**
	 * Whether to automatically load CSS files with the same name as this module
	 * 
	 * @var bool
	 * 
	 */
	protected $loadStyles = true;
	
	/**
	 * Whether to automatically load JS files with the same name as this module
	 *
	 * @var bool
	 *
	 */
	protected $loadScripts = true; 
	
	/**
	 * Add an optional component that can be used with this module
	 *
	 * @param string $name
	 * @param string $file
	 * @return $this
	 *
	 */
	public function addComponent($name, $file) {
		$this->components[$name] = $file;
		return $this;
	}

	/**
	 * Add an array of optional components
	 *
	 * @param array $components
	 * @return $this
	 *
	 */
	public function addComponents(array $components) {
		$this->components = array_merge($this->components, $components);
		return $this;
	}

	/**
	 * Per the Module interface, Initialize the Process, loading any related CSS or JS files
	 *
	 */
	public function init() {
		
		$class = $this->className();
		$config = $this->wire('config');
	
		$file = $config->paths->$class . "$class.css";
		if($this->loadStyles && is_file($file)) {
			$mtime = filemtime($file);
			$this->config->styles->add($config->urls->$class . "$class.css?v=$mtime");
		}
		
		$file = $config->paths->$class . "$class.js"; 
		$mtime = 0;
		if($this->loadScripts && is_file($file)) {
			$minFile = $config->paths->$class . "$class.min.js";
			if(!$config->debug && is_file($minFile)) {
				$mtime = filemtime($minFile);
				$config->scripts->add($config->urls->$class . "$class.min.js?v=$mtime");
			} else {
				$mtime = filemtime($file);
				$config->scripts->add($config->urls->$class . "$class.js?v=$mtime");
			}
		}

		if(count($this->requested)) {
			foreach($this->requested as $name) {
				$url = $this->components[$name]; 
				if(strpos($url, '/') === false) {
					$mtime = filemtime($config->paths->$class . $url);
					$url = $config->urls->$class . $url;
				}
				$url .= "?v=$mtime";
				$this->wire('config')->scripts->add($url);
			}
			$this->requested = array();
		}

		$this->initialized = true;
	}

	/**
	 * Use an extra named component
	 *
	 * @param $name
	 * @return $this
	 *
	 */
	public function ___use($name) {

		$name = $this->wire('sanitizer')->name($name);
		$class = $this->className();
		$config = $this->wire('config');

		if(!isset($this->components[$name])) {
			$this->error("Unrecognized $class component requested: $name");
			return $this;
		}

		if($this->initialized) {
			$url = $this->components[$name];
			$mtime = 0;
			if(strpos($url, '/') === false) {
				$file = $config->paths->$class . $url;
				$url = $config->urls->$class . $url;
				$mtime = filemtime($file);
			}
			$config->scripts->add($url . "?v=$mtime");
		} else {
			$this->requested[$name] = $name;
		}

		return $this;
	}

	public function ___install() { }
	public function ___uninstall() { }
	public function isSingular() { return true; }	
	public function isAutoload() { return false; }
}

