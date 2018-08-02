<?php namespace ProcessWire;

/**
 * ProcessWire class autoloader
 * 
 * Similar to a PSR-4 autoloader but with knowledge of modules. 
 * 
 * #pw-summary The ProcessWire $classLoader API variable handles autoloading of classes and modules.
 * #pw-body = 
 * This class loader is similar to a PSR-4 autoloader but with knowledge of modules.
 * #pw-body
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireClassLoader {

	/**
	 * @var Modules|null
	 * 
	 */
	protected $modules = null;

	/**
	 * @var null|ProcessWire
	 * 
	 */
	protected $wire = null;

	/**
	 * Extensions allowed for autoload files
	 * 
	 * @var array
	 * 
	 */
	protected $extensions = array(
		'.php',
	);

	/**
	 * @var array
	 * 
	 */
	static protected $namespaces = array();

	/**
	 * Log of autoload activity when debug mode is on
	 * 
	 * @var array
	 * 
	 */
	protected $debugLog = array();

	/**
	 * Whether we are using debug mode
	 * 
	 * @var bool
	 * 
	 */
	protected $debug = null;

	/**
	 * @param ProcessWire $wire
	 * 
	 */
	public function __construct($wire = null) {
		if($wire) $this->wire = $wire;
		spl_autoload_register(array($this, 'loadClass'));
	}

	/**
	 * Add a recognized file extension for PHP files
	 * 
	 * Note: ".php" is already assumed, so does not need to be added.
	 * 
	 * #pw-advanced
	 * 
	 * @param string $ext
	 * 
	 */
	public function addExtension($ext) {
		if(strpos($ext, '.') !== 0) $ext = ".$ext";
		if(!in_array($ext, $this->extensions)) $this->extensions[] = $ext;
	}

	/**
	 * Add a namespace to point to a path root
	 * 
	 * Multiple root paths may be specified for a single namespace by calling this method more than once.
	 * 
	 * ~~~~~
	 * $classLoader->addNamespace('HelloWorld', '/path/to/that/');
	 * ~~~~~
	 * 
	 * @param string $namespace
	 * @param string $path Full system path
	 * 
	 */
	public function addNamespace($namespace, $path) {
		if(!isset(self::$namespaces[$namespace])) self::$namespaces[$namespace] = array();
		if(DIRECTORY_SEPARATOR !== '/') $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
		$path = rtrim($path, '/') . '/';
		if(!in_array($path, self::$namespaces[$namespace])) self::$namespaces[$namespace][] = $path;
	}

	/**
	 * Return array of paths for the given namespace, or empty array if none found
	 * 
	 * @param string $namespace
	 * @return array of paths or empty array if none found
	 * 
	 */
	public function getNamespace($namespace) {
		return isset(self::$namespaces[$namespace]) ? self::$namespaces[$namespace] : array();
	}

	/**
	 * Return true if namespace is defined with paths or false if not
	 * 
	 * @param string $namespace
	 * @return bool
	 * 
	 */
	public function hasNamespace($namespace) {
		return isset(self::$namespaces[$namespace]);
	}

	/**
	 * Remove defined paths (or single path) for given namespace
	 * 
	 * @param string $namespace
	 * @param string $path Optionally specify path to remove (default=remove all)
	 * 
	 */
	public function removeNamespace($namespace, $path = '') {
		if(strlen($path)) {
			$key = array_search($path, self::$namespaces[$namespace]);
			if($key !== false) unset(self::$namespaces[$namespace][$key]);
		} else {
			unset(self::$namespaces[$namespace]);
		}
	}

	/**
	 * Load the file for the given class
	 * 
	 * #pw-advanced
	 * 
	 * @param string $className
	 * 
	 */
	public function loadClass($className) {
		
		static $level = 0;
		static $levelHistory = array();
		$level++;
		
		if(is_null($this->modules)) {
			if($this->wire) $this->modules = $this->wire->wire('modules');
		}
		if(is_null($this->debug)) {
			if($this->wire) $this->debug = $this->wire->wire('config')->debug;
		}
		
		if($this->debug) {
			$_className = str_replace(__NAMESPACE__ . '\\', '', $className);
			$levelHistoryStr = count($levelHistory) ? ' (via ' . implode(' > ', $levelHistory) . ')' : '';
			$levelHistory[] = $_className;
		} else {
			$levelHistoryStr = '';
			$_className = '';
		}
		
		$found = false;
		$_parts = array();
		
		if(__NAMESPACE__) {
			$parts = explode("\\", $className);
			$name = array_pop($parts);
			$namespace = implode("\\", $parts);
			$_namespace = $namespace; // original and unmodified namespace
		} else {
			$_parts = array();
			if(strpos($className, "\\") !== false) {
				$parts = explode("\\", $className);
				$name = array_pop($parts);
				$namespace = implode("\\", $parts);
			} else {
				$name = $className;
				$namespace = "\\";
			}
		}
		
		$_namespace = $namespace; // original and unmodified namespace
	
		if($this->modules && $this->modules->isModule($className)) {
			if($this->modules->includeModule($name)) {
				// success, and Modules class just included it
				if($this->debug) {
					$this->debugLog[$_className] = "Handled by modules loader" . $levelHistoryStr;
					array_pop($levelHistory);
				}
				$level--;
				return;
			}
		}
		
		while($namespace && !isset(self::$namespaces[$namespace])) {
			$_parts[] = array_pop($parts);
			$namespace = implode("\\", $parts);
		}

		if($namespace) {
			$paths = self::$namespaces[$namespace];
			$dir = count($_parts) ? implode('/', array_reverse($_parts)) . '/' : '';
			foreach($this->extensions as $ext) {
				foreach($paths as $path) {
					$file = "$path$dir$name$ext";
					if(is_file($file)) {
						$found = $file;
						break;
					}
				}
				if($found) break;
			}
		}
	
		if(!$found && $this->modules && $_namespace) {
			$path = $this->modules->getNamespacePath($_namespace);
			if($path) {
				// if namespace is for a known module, see if we can find a file in that module's directory
				// with the same name as the request class
				// @todo psr-4 support for these
				foreach($this->extensions as $ext) {
					$file = "$path$name$ext";
					if(is_file($file)) {
						$found = $file;
						break;
					}
				}
			}
		}
		
		if($found) {
			/** @noinspection PhpIncludeInspection */
			include_once($found);
			if($this->debug) {
				$file = $this->wire ? str_replace($this->wire->wire('config')->paths->root, '/', $found) : $found;
				$this->debugLog[$_className] = $file . $levelHistoryStr;
			}
		} else if($this->debug) {
			$this->debugLog[$_className] = "Unable to locate file for this class" . $levelHistoryStr;
		}
		
		$level--;
		if($this->debug) array_pop($levelHistory);
	}

	/**
	 * Enable or disable debug mode
	 * 
	 * #pw-internal
	 * 
	 * @param bool $debug
	 * 
	 */
	public function setDebug($debug) {
		$this->debug = (bool) $debug; 
	}

	/**
	 * Get log of debug events
	 * 
	 * #pw-internal
	 * 
	 * @return array of strings
	 * 
	 */
	public function getDebugLog() {
		return $this->debugLog;
	}
}

