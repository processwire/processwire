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
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
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
		$config = $this->wire()->config;
		$version = $config->version;
		$debug = $config->debug;
		$modulePath = $config->paths->$class;
		$moduleUrl = $config->urls->$class;
		$useVersionUrls = $config->useVersionUrls;
		$assets = [];
		
		if($this->loadStyles) $assets['css'] = "$class.css";
		if($this->loadScripts && !$debug) $assets['jsMin'] = "$class.min.js";
		if($this->loadScripts) $assets['js'] = "$class.js";
		
		foreach($assets as $key => $basename) {
			$file = $modulePath . $basename;
			if(!is_file($file)) unset($assets[$key]); 
		}
		
		if(isset($assets['jsMin'])) unset($assets['js']); 
		
		foreach($assets as $key => $basename) {
			$file = $modulePath . $basename;
			$fileUrl = $moduleUrl . $basename;
			if($useVersionUrls === null) {
				if($debug) $version = filemtime($file);
				$fileUrl .= "?v=$version";
			}
			if($key === 'css') {
				$config->styles->add($fileUrl);
			} else {
				$config->scripts->add($fileUrl);
			}
		}
	
		if(count($this->requested)) {
			foreach($this->requested as $name) {
				$fileUrl = $this->components[$name]; 
				if(strpos($fileUrl, '/') === false) {
					if($debug && $useVersionUrls === null) {
						$version = filemtime($modulePath . $fileUrl);
					}
					$fileUrl = $moduleUrl . $fileUrl;
				}
				if($useVersionUrls === null) $fileUrl .= "?v=$version";
				$config->scripts->add($fileUrl);
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

		$class = $this->className();
		$config = $this->wire()->config;
		
		if(!ctype_alnum($name)) $name = $this->wire()->sanitizer->name($name);

		if(!isset($this->components[$name])) {
			$this->error("Unrecognized $class component requested: $name");
			return $this;
		}

		if($this->initialized) {
			$url = $this->components[$name];
			$version = $config->version;
			if(strpos($url, '/') === false) {
				$file = $config->paths->$class . $url;
				$url = $config->urls->$class . $url;
				if($config->debug && $config->useVersionUrls === null) $version = filemtime($file);
			}
			if($config->useVersionUrls === null) $url .= "?v=$version";
			$config->scripts->add($url);
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
