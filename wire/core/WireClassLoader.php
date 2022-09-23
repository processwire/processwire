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
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
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
	 * Class name prefixes to paths
	 *
	 * @var array Indexed by prefix, values are arrays of paths
	 *
	 */
	protected $prefixes = array();

	/**
	 * Class name suffixes to paths
	 * 
	 * @var array Indexed by suffix, values are arrays of paths
	 * 
	 */
	protected $suffixes = array();

	/**
	 * True when finding file, string when file found, false when not active
	 * 
	 * @var string|bool
	 * 
	 */
	protected $findFile = false;

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
	 * Normalize a path
	 * 
	 * @param string $path
	 * @return string
	 * @since 3.0.152
	 * 
	 */
	protected function path($path) {
		if(DIRECTORY_SEPARATOR !== '/') $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
		return rtrim($path, '/') . '/';
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
	 * Map a class suffix to a path
	 * 
	 * This is used as a helper/fallback and class is not required to be in given path,
	 * but the path will be added as another to check when not found in namespace path(s).
	 * 
	 * @param string $suffix Case sensitive suffix specific to class name (not namespace). 
	 * @param string $path
	 * 
	 */
	public function addSuffix($suffix, $path) {
		if(!isset($this->suffixes[$suffix])) $this->suffixes[$suffix] = array();
		if(!empty($path) && is_dir($path)) $this->suffixes[$suffix][] = $this->path($path);
	}
	
	/**
	 * Map a class prefix to a path
	 * 
	 * This is used as a helper/fallback and class is not required to be in given path,
	 * but the path will be added as another to check when not found in namespace path(s).
	 *
	 * @param string $prefix Case sensitive prefix specific to class name (not namespace). 
	 * @param string $path
	 *
	 */
	public function addPrefix($prefix, $path) {
		if(!isset($this->prefixes[$prefix])) $this->prefixes[$prefix] = array();
		if(!empty($path) && is_dir($path)) $this->prefixes[$prefix][] = $this->path($path);
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
		$path = $this->path($path);
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
	 * Find filename for given class name (primarily for API testing/debugging purposes)
	 *
	 * @param string $className Class name with namespace
	 * @return bool|string Returns file on success or boolean false when not found
	 * @since 3.0.152
	 *
	 */
	public function findClassFile($className) {
		$this->findFile = true;
		$this->loadClass($className);
		$file = is_string($this->findFile) ? $this->findFile : false;
		$this->findFile = false;
		return $file;
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
		
		if($this->modules === null && $this->wire) {
			$this->modules = $this->wire->wire()->modules;
		}
		
		if($this->debug === null && $this->wire) {
			$this->debug = $this->wire->wire()->config->debug;
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
		} else {
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
				if($this->findFile === true) {
					$this->findFile = $this->modules->getModuleFile($name); 
				}
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
			$found = $this->findClassInPaths($name, $paths, $dir); 
		}
	
		if(!$found && $this->modules && $_namespace) {
			// if namespace is for a known module, see if we can find a file in that module’s directory
			// with the same name as the request class
			// @todo psr-4 support for these
			$path = $this->modules->getNamespacePath($_namespace);
			if($path) $found = $this->findClassInPaths($name, $path); 
		}
		
		if(!$found && (!empty($this->prefixes) || !empty($this->suffixes))) {
			$found = $this->findInPrefixSuffixPaths($name);
		}
		
		if($found) {
			/** @noinspection PhpIncludeInspection */
			include_once($found);
			if($this->debug) {
				$file = $this->wire ? str_replace($this->wire->wire()->config->paths->root, '/', $found) : $found;
				$this->debugLog[$_className] = $file . $levelHistoryStr;
			}
			if($this->findFile === true && $level === 1) {
				$this->findFile = $found;
			}
		} else if($this->debug) {
			$this->debugLog[$_className] = "Unable to locate file for this class" . $levelHistoryStr;
		}
		
		$level--;
		if($this->debug) array_pop($levelHistory);
	}

	/**
	 * Find class file among given paths and return full pathname to file if found
	 *
	 * @param string $name Class name without namespace
	 * @param string|array $paths Path(s) to check
	 * @param string $dir Optional directory string to append to each path, must not start with slash but must end with slash, i.e. "dir/"
	 * @return string|bool Returns full path+filename when found or boolean false when not found
	 * @since 3.0.152
	 *
	 */
	protected function findClassInPaths($name, $paths, $dir = '') {
		$found = false;
		if(!is_array($paths)) $paths = array($paths);
		foreach($paths as $path) {
			foreach($this->extensions as $ext) {
				$file = "$path$dir$name$ext";
				if(!is_file($file)) continue;
				$found = $file;
				break;
			}
			if($found) break;
		}
		return $found;
	}

	/**
	 * Check prefix and suffix definition paths for given class name and return file if found
	 * 
	 * @param string $name Class name without namespace
	 * @return bool|string Returns filename on success or boolean false if not found
	 * @since 3.0.152
	 * 
	 */
	protected function findInPrefixSuffixPaths($name) {
		$found = false;
		
		foreach(array('prefixes', 'suffixes') as $type) {
			
			foreach($this->$type as $fix => $paths) {
				
				// if class exactly matches prefix/suffix, it is the full class name and not allowed
				if($name === $fix || empty($fix)) continue; 
				
				// determine where the prefix/suffix appears in the class name
				$pos = strpos($name, $fix);
				
				// prefix/suffix does not appear in class name
				if($pos === false) continue; 
				
				if($type === 'prefixes') {
					// prefixes: class name must begin with prefix
					if($pos !== 0) continue; 
				} else {
					// suffixes: class name must end with suffix
					if(substr($name, -1 * strlen($fix)) !== $fix) continue; 
				}
				
				// if still here then we have a class name that matches a prefix/suffix, check if in path
				$found = $this->findClassInPaths($name, $paths);
				if($found) break;
			}
			
			if($found) break;
		}
		
		return $found;
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

