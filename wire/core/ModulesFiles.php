<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Files
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class ModulesFiles extends ModulesClass {
	
	/**
	 * Core module types that are isolated by directory
	 *
	 * @var array
	 *
	 */
	protected $coreTypes = array(
		'AdminTheme',
		'Fieldtype',
		'Inputfield',
		'Jquery',
		'LanguageSupport',
		'Markup',
		'Process',
		'Session',
		'System',
		'Textformatter',
	);
	
	/**
	 * Module file extensions indexed by module name where value 1=.module, and 2=.module.php
	 *
	 * @var array
	 *
	 */
	protected $moduleFileExts = array();

	/**
	 * Get or set module file extension type (1 or 2)
	 * 
	 * @param string $class Module class name
	 * @param int $setValue 1 for '.module' or 2 for '.module.php', or omit to get current value
	 * @return int
	 * 
	 */
	public function moduleFileExt($class, $setValue = null) {
		if($setValue !== null) {
			$this->moduleFileExts[$class] = (int) $setValue;
			return $setValue;
		}
		return isset($this->moduleFileExts[$class]) ? $this->moduleFileExts[$class] : 0;
	}

	/**
	 * Find new module files in the given $path
	 *
	 * If $readCache is true, this will perform the find from the cache
	 *
	 * @param string $path Path to the modules
	 * @param bool $readCache Optional. If set to true, then this method will attempt to read modules from the cache.
	 * @param int $level For internal recursive use.
	 * @return array Array of module files
	 *
	 */
	public function findModuleFiles($path, $readCache = false, $level = 0) {

		static $startPath;
		static $prependFiles = array();

		$config = $this->wire()->config;
		$cacheName = '';

		if($level == 0) {
			$startPath = $path;
			$cacheName = "Modules." . str_replace($config->paths->root, '', $path);
			if($readCache) {
				$cacheContents = $this->modules->getCache($cacheName);
				if($cacheContents) return explode("\n", trim($cacheContents));
			}
		}

		$files = array();
		$autoloadOrders = $this->modules->loader->getAutoloadOrders();

		if(count($autoloadOrders) && $path !== $config->paths->modules) {
			// ok
		} else {
			$autoloadOrders = null;
		}

		try {
			$dir = new \DirectoryIterator($path);
		} catch(\Exception $e) {
			$this->trackException($e, false, true);
			$dir = null;
		}

		if($dir) foreach($dir as $file) {

			if($file->isDot()) continue;

			$filename = $file->getFilename();
			$pathname = $file->getPathname();

			if(DIRECTORY_SEPARATOR != '/') {
				$pathname = str_replace(DIRECTORY_SEPARATOR, '/', $pathname);
			}

			if(strpos($pathname, '/.') !== false) {
				$pos = strrpos(rtrim($pathname, '/'), '/');
				if($pathname[$pos+1] == '.') continue; // skip hidden files and dirs
			}

			// if it's a directory with a .module file in it named the same as the dir, then descend into it
			if($file->isDir() && ($level < 1 || (is_file("$pathname/$filename.module") || is_file("$pathname/$filename.module.php")))) {
				$files = array_merge($files, $this->findModuleFiles($pathname, false, $level + 1));
			}

			// if the filename doesn't end with .module or .module.php, then stop and move onto the next
			$extension = $file->getExtension();
			if($extension !== 'module' && $extension !== 'php') continue;
			list($moduleName, $extension) = explode('.', $filename, 2);
			if($extension !== 'module'  && $extension !== 'module.php') continue;

			$pathname = str_replace($startPath, '', $pathname);

			if($autoloadOrders !== null && isset($autoloadOrders[$moduleName])) {
				$prependFiles[$pathname] = $autoloadOrders[$moduleName];
			} else {
				$files[] = $pathname;
			}
		}

		if($level == 0 && $dir !== null) {
			if(!empty($prependFiles)) {
				// one or more non-core modules must be loaded first in a specific order
				arsort($prependFiles);
				$files = array_merge(array_keys($prependFiles), $files);
				$prependFiles = array();
			}
			if($cacheName) {
				$this->modules->saveCache($cacheName, implode("\n", $files));
			}
		}

		return $files;
	}
	
	/**
	 * Get the path + filename (or optionally URL) for module
	 *
	 * @param string|Module $class Module class name or object instance
	 * @param array|bool $options Options to modify default behavior:
	 * 	- `getURL` (bool): Specify true if you want to get the URL rather than file path (default=false).
	 * 	- `fast` (bool): Specify true to omit file_exists() checks (default=false).
	 *  - `guess` (bool): Manufacture/guess a module location if one cannot be found (default=false) 3.0.170+
	 * 	- Note: If you specify a boolean for the $options argument, it is assumed to be the $getURL property.
	 * @return bool|string Returns string of module file, or false on failure.
	 *
	 */
	public function getModuleFile($class, $options = array()) {

		$config = $this->wire()->config;
		$className = $class;
		
		if(is_bool($options)) $options = array('getURL' => $options);
		if(!isset($options['getURL'])) $options['getURL'] = false;
		if(!isset($options['fast'])) $options['fast'] = false;

		$file = false;

		// first see it's an object, and if we can get the file from the object
		if(is_object($className)) {
			$module = $className;
			if($module instanceof ModulePlaceholder) $file = $module->file;
			$moduleName = $module->className();
			$className = $module->className(true);
		} else {
			$moduleName = wireClassName($className, false);
		}

		$hasDuplicate = $this->modules->duplicates()->hasDuplicate($moduleName);

		if(!$hasDuplicate) {
			// see if we can determine it from already stored paths
			$path = $config->paths($moduleName);
			if($path) {
				$file = $path . $moduleName . ($this->moduleFileExt($moduleName) === 2 ? '.module.php' : '.module');
				if(!$options['fast'] && !file_exists($file)) $file = false;
			}
		}

		// next see if we've already got the module filename cached locally
		if(!$file) { 
			$installableFile = $this->modules->installableFile($moduleName);
			if($installableFile && !$hasDuplicate) {
				$file = $installableFile;
				if(!$options['fast'] && !file_exists($file)) $file = false;
			}
		}

		if(!$file) {
			$dupFile = $this->modules->duplicates()->getCurrent($moduleName);
			if($dupFile) {
				$rootPath = $config->paths->root;
				$file = rtrim($rootPath, '/') . $dupFile;
				if(!file_exists($file)) {
					// module in use may have been deleted, find the next available one that exists
					$file = '';
					$dups = $this->modules->duplicates()->getDuplicates($moduleName);
					foreach($dups['files'] as $pathname) {
						$pathname = rtrim($rootPath, '/') . $pathname;
						if(file_exists($pathname)) $file = $pathname;
						if($file) break;
					}
				}
			}
		}

		if(!$file) {
			// see if it's a predefined core type that can be determined from the type
			// this should only come into play if module has moved or had a load error
			foreach($this->coreTypes as $typeName) {
				if(strpos($moduleName, $typeName) !== 0) continue;
				$checkFiles = array(
					"$typeName/$moduleName/$moduleName.module",
					"$typeName/$moduleName/$moduleName.module.php",
					"$typeName/$moduleName.module",
					"$typeName/$moduleName.module.php",
				);
				$path1 = $config->paths->modules;
				foreach($checkFiles as $checkFile) {
					$file1 = $path1 . $checkFile;
					if(file_exists($file1)) $file = $file1;
					if($file) break;
				}
				if($file) break;
			}
			if(!$file) {
				// check site modules
				$checkFiles = array(
					"$moduleName/$moduleName.module",
					"$moduleName/$moduleName.module.php",
					"$moduleName.module",
					"$moduleName.module.php",
				);
				$path1 = $config->paths->siteModules;
				foreach($checkFiles as $checkFile) {
					$file1 = $path1 . $checkFile;
					if(file_exists($file1)) $file = $file1;
					if($file) break;
				}
			}
		}

		if(!$file) {
			// if all the above failed, try to get it from Reflection
			try {
				// note we don't call getModuleClass() here because it may result in a circular reference
				if(strpos($className, "\\") === false) {
					$moduleID = $this->moduleID($moduleName);
					$namespace = $this->modules->info->moduleInfoCache($moduleID, 'namespace');
					if(!empty($namespace)) {
						$className = rtrim($namespace, "\\") . "\\$moduleName";
					} else {
						$className = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\$moduleName" : $moduleName;
					}
				}
				$reflector = new \ReflectionClass($className);
				$file = $reflector->getFileName();

			} catch(\Exception $e) {
				$file = false;
			}
		}

		if(!$file && !empty($options['guess'])) {
			// make a guess about where module would be if we had been able to find it
			$file = $config->paths('siteModules') . "$moduleName/$moduleName.module";
		}

		if($file) {
			if(DIRECTORY_SEPARATOR != '/') $file = str_replace(DIRECTORY_SEPARATOR, '/', $file);
			if($options['getURL']) $file = str_replace($config->paths->root, '/', $file);
		}

		return $file;
	}

	/**
	 * Include the given filename
	 *
	 * @param string $file
	 * @param string $moduleName
	 * @return bool
	 *
	 */
	public function includeModuleFile($file, $moduleName) {

		$wire1 = ProcessWire::getCurrentInstance();
		$wire2 = $this->wire();

		// check if there is more than one PW instance active
		if($wire1 !== $wire2) {
			// multi-instance is active, don't autoload module if class already exists
			// first do a fast check, which should catch any core modules 
			if(class_exists(__NAMESPACE__ . "\\$moduleName", false)) return true;
			// next do a slower check, figuring out namespace
			$ns = $this->modules->info->getModuleNamespace($moduleName, array('file' => $file));
			if($ns === null) {
				// unable to determine module namespace, likely file does not exist
				$ns = (string) $ns;
			}
			$className = trim($ns, "\\") . "\\$moduleName";
			if(class_exists($className, false)) return true;
			// if this point is reached, module is not yet in memory in either instance
			// temporarily set the $wire instance to 2nd instance during include()
			ProcessWire::setCurrentInstance($wire2);
		}

		// get compiled version (if it needs compilation)
		$file = $this->compile($moduleName, $file);

		if($file) {
			/** @noinspection PhpIncludeInspection */
			$success = @include_once($file);
		} else {
			$success = false;
		}
		
		if(!$success) {
			// handle case where module has moved from /modules/Foo.module to /modules/Foo/Foo.module
			// which can only occur during upgrades from much older versions. 
			// examples are FieldtypeImage and FieldtypeText which moved to their own directories.
			$file2 = preg_replace('!([/\\\\])([^/\\\\]+)(\.module(?:\.php)?)$!', '$1$2$1$2$3', $file);
			if($file !== $file2) $success = @include_once($file2);
		}

		// set instance back, if multi-instance
		if($wire1 !== $wire2) ProcessWire::setCurrentInstance($wire1);

		return (bool) $success;
	}
	
	/**
	 * Compile and return the given file for module, if allowed to do so
	 *
	 * @param Module|string $moduleName
	 * @param string $file Optionally specify the module filename as an optimization
	 * @param string|null $namespace Optionally specify namespace as an optimization
	 * @return string|bool
	 *
	 */
	public function compile($moduleName, $file = '', $namespace = null) {

		static $allowCompile = null;
		
		if($allowCompile === null) $allowCompile = $this->wire()->config->moduleCompile;

		// if not given a file, track it down
		if(empty($file)) $file = $this->modules->getModuleFile($moduleName);

		// don't compile when module compilation is disabled
		if(!$allowCompile) return $file;

		// don't compile core modules
		if(strpos($file, $this->modules->coreModulesDir) !== false) return $file;

		// if namespace not provided, get it
		if(is_null($namespace)) {
			if(is_object($moduleName)) {
				$className = $moduleName->className(true);
				$namespace = wireClassName($className, 1);
			} else if(is_string($moduleName) && strpos($moduleName, "\\") !== false) {
				$namespace = wireClassName($moduleName, 1);
			} else {
				$namespace = $this->modules->info->getModuleNamespace($moduleName, array('file' => $file));
			}
		}

		// determine if compiler should be used
		if(__NAMESPACE__) {
			$compile = $namespace === '\\' || empty($namespace);
		} else {
			$compile = trim($namespace, '\\') === 'ProcessWire';
		}

		// compile if necessary
		if($compile) {
			/** @var FileCompiler $compiler */
			$compiler = $this->wire(new FileCompiler(dirname($file)));
			$compiledFile = $compiler->compile(basename($file));
			if($compiledFile) $file = $compiledFile;
		}

		return $file;
	}

	/**
	 * Find modules that are missing their module file on the file system
	 *
	 * Return value is array:
	 * ~~~~~
	 * [
	 *   'ModuleName' => [
	 *     'id' => 123,
	 *     'name' => 'ModuleName',
	 *     'file' => '/path/to/expected/file.module'
	 *   ],
	 *   'ModuleName' => [
	 *     ...
	 *   ]
	 * ];
	 * ~~~~~
	 *
	 * #pw-internal
	 *
	 * @return array
	 * @since 3.0.170
	 *
	 */
	public function findMissingModules() {

		$missing = array();
		$unflags = array();

		$sql = "SELECT id, class FROM modules WHERE flags & :flagsNoFile ORDER BY class";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':flagsNoFile', Modules::flagsNoFile, \PDO::PARAM_INT);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {

			$class = $row['class'];
			if(strpos($class, '.') === 0) continue;

			$file = $this->getModuleFile($class, array('fast' => true));

			if($file && file_exists($file)) {
				$unflags[] = $class;
				continue;
			}

			$fileAlt = $this->getModuleFile($class, array('fast' => false));

			if($fileAlt) {
				$file = $fileAlt;
				if(file_exists($file)) continue;
			}

			if(!$file) {
				$file = $this->getModuleFile($class, array('fast' => true, 'guess' => true));
			}

			$missing[$class] = array(
				'id' => $row['id'],
				'name' => $class,
				'file' => $file,
			);
		}

		foreach($unflags as $name) {
			$this->modules->flags->setFlag($name, Modules::flagsNoFile, false);
		}

		return $missing;
	}

	/**
	 * Load module related CSS and JS files (where applicable)
	 *
	 * - Applies only to modules that carry class-named CSS and/or JS files, such as Process, Inputfield and ModuleJS modules.
	 * - Assets are populated to `$config->styles` and `$config->scripts`.
	 *
	 * #pw-internal
	 *
	 * @param Module|int|string $module Module object or class name
	 * @return int Returns number of files that were added
	 *
	 */
	public function loadModuleFileAssets($module) {

		$class = $this->modules->getModuleClass($module);
		static $classes = array();
		if(isset($classes[$class])) return 0; // already loaded
		$config = $this->wire()->config;
		$path = $config->paths($class);
		$url = $config->urls($class);
		$debug = $config->debug;
		$coreVersion = $config->version;
		$moduleVersion = 0;
		$cnt = 0;

		foreach(array('styles' => 'css', 'scripts' => 'js') as $type => $ext) {
			$fileURL = '';
			$file = "$path$class.$ext";
			$fileVersion = $coreVersion;
			$minFile = "$path$class.min.$ext";
			if(!$debug && is_file($minFile)) {
				$fileURL = "$url$class.min.$ext";
			} else if(is_file($file)) {
				$fileURL = "$url$class.$ext";
				if($debug) $fileVersion = filemtime($file);
			}
			if($fileURL) {
				if(!$moduleVersion) {
					$info = $this->modules->info->getModuleInfo($module, array('verbose' => false));
					$moduleVersion = (int) isset($info['version']) ? $info['version'] : 0;
				}
				$config->$type->add("$fileURL?v=$moduleVersion-$fileVersion");
				$cnt++;
			}
		}

		$classes[$class] = true;

		return $cnt;
	}

	/**
	 * Get module language translation files
	 *
	 * @param Module|string $module
	 * @return array Array of translation files including full path, indexed by basename without extension
	 * @since 3.0.181
	 *
	 */
	public function getModuleLanguageFiles($module) {

		$module = $this->modules->getModuleClass($module);
		if(empty($module)) return array();

		$path = $this->wire()->config->paths($module);
		if(empty($path)) return array();

		$pathHidden = $path . '.languages/';
		$pathVisible = $path . 'languages/';

		if(is_dir($pathVisible)) {
			$path = $pathVisible;
		} else if(is_dir($pathHidden)) {
			$path = $pathHidden;
		} else {
			return array();
		}

		$items = array();
		$options = array(
			'extensions' => array('csv'),
			'recursive' => false,
			'excludeHidden' => true,
		);

		foreach($this->wire()->files->find($path, $options) as $file) {
			$basename = basename($file, '.csv');
			$items[$basename] = $file;
		}

		return $items;
	}

	/**
	 * Setup entries in config->urls and config->paths for the given module
	 *
	 * @param string $moduleName
	 * @param string $path
	 *
	 */
	public function setConfigPaths($moduleName, $path) {
		$config = $this->wire()->config;
		$rootPath = $config->paths->root;
		if(strpos($path, $rootPath) === 0) {
			// if root path included, strip it out
			$path = substr($path, strlen($config->paths->root));
		}
		$path = rtrim($path, '/') . '/';
		$config->paths->set($moduleName, $path);
		$config->urls->set($moduleName, $path);
	}

	/**
	 * Get the namespace used in the given .php or .module file
	 *
	 * #pw-internal
	 *
	 * @param string $file
	 * @return string Includes leading and trailing backslashes where applicable
	 *
	 */
	public function getFileNamespace($file) {
		$namespace = $this->wire()->files->getNamespace($file);
		if($namespace !== "\\") $namespace = "\\" . trim($namespace, "\\") . "\\";
		return $namespace;
	}
	
	/**
	 * Get the class defined in the file (or optionally the 'extends' or 'implements')
	 *
	 * #pw-internal
	 *
	 * @param string $file
	 * @return array Returns array with these indexes:
	 * 	'class' => string (class without namespace)
	 * 	'className' => string (class with namespace)
	 * 	'extends' => string
	 * 	'namespace' => string
	 * 	'implements' => array
	 *
	 */
	public function getFileClassInfo($file) {

		$value = array(
			'class' => '',
			'className' => '',
			'extends' => '',
			'namespace' => '',
			'implements' => array()
		);

		if(!is_file($file)) return $value;
		$data = file_get_contents($file);
		if(!strpos($data, 'class')) return $value;
		if(!preg_match('/^\s*class\s+(.+)$/m', $data, $matches)) return $value;

		if(strpos($matches[1], "\t") !== false) $matches[1] = str_replace("\t", " ", $matches[1]);
		$parts = explode(' ', trim($matches[1]));

		foreach($parts as $key => $part) {
			if(empty($part)) unset($parts[$key]);
		}

		$className = array_shift($parts);
		if(strpos($className, '\\') !== false) {
			$className = trim($className, '\\');
			$a = explode('\\', $className);
			$value['className'] = "\\$className\\";
			$value['class'] = array_pop($a);
			$value['namespace'] = '\\' . implode('\\', $a) . '\\';
		} else {
			$value['className'] = '\\' . $className;
			$value['class'] = $className;
			$value['namespace'] = '\\';
		}

		while(count($parts)) {
			$next = array_shift($parts);
			if($next == 'extends') {
				$value['extends'] = array_shift($parts);
			} else if($next == 'implements') {
				$implements = array_shift($parts);
				if(strlen($implements)) {
					$implements = str_replace(' ', '', $implements);
					$value['implements'] = explode(',', $implements);
				}
			}
		}

		return $value;
	}

	public function getDebugData() {
		return array(
			'moduleFileExts' => $this->moduleFileExts
		);
	}

}
