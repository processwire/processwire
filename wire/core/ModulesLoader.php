<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Loader
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */ 

class ModulesLoader extends ModulesClass {
	
	/**
	 * Array of moduleName => order to indicate autoload order when necessary
	 *
	 * @var array
	 *
	 */
	protected $autoloadOrders = array();
	
	/**
	 * Array of moduleName => condition
	 *
	 * Condition can be either an anonymous function or a selector string to be evaluated at ready().
	 *
	 */
	protected $conditionalAutoloadModules = array();

	/**
	 * Cache of module information from DB used across multiple calls temporarily by loadPath() method
	 *
	 */
	protected $modulesTableCache = array();
	
	/**
	 * Module created dates indexed by module ID
	 *
	 */
	protected $createdDates = array();
	
	/**
	 * Initialize all the modules that are loaded at boot
	 *
	 * #pw-internal
	 *
	 * @param null|array|Modules $modules
	 * @param array $completed
	 * @param int $level
	 *
	 */
	public function triggerInit($modules = null, $completed = array(), $level = 0) {

		$debugKey = null;
		$debugKey2 = null;
		
		if($this->debug) {
			$debugKey = $this->modules->debugTimerStart("triggerInit$level");
			$this->message("triggerInit(level=$level)");
		}

		$queue = array();
		
		if($modules === null) $modules = $this->modules;

		foreach($modules as $class => $module) {

			if($module instanceof ModulePlaceholder) {
				// skip modules that aren't autoload and those that are conditional autoload
				if(!$module->autoload) continue;
				if(isset($this->conditionalAutoloadModules[$class])) continue;
			}

			if($this->debug) $debugKey2 = $this->modules->debugTimerStart("triggerInit$level($class)");

			$info = $this->modules->getModuleInfo($module);
			$skip = false;

			// module requires other modules
			foreach($info['requires'] as $requiresClass) {
				if(in_array($requiresClass, $completed)) continue;
				$dependencyInfo = $this->modules->getModuleInfo($requiresClass);
				if(empty($dependencyInfo['autoload'])) {
					// if dependency isn't an autoload one, there's no point in waiting for it
					if($this->debug) $this->warning("Autoload module '$module' requires a non-autoload module '$requiresClass'");
					continue;
				} else if(isset($this->conditionalAutoloadModules[$requiresClass])) {
					// autoload module requires another autoload module that may or may not load
					if($this->debug) $this->warning("Autoload module '$module' requires a conditionally autoloaded module '$requiresClass'");
					continue;
				}
				// dependency is autoload and required by this module, so queue this module to init later
				$queue[$class] = $module;
				$skip = true;
				break;
			}

			if(!$skip) {
				if($info['autoload'] !== false) {
					if($info['autoload'] === true || $this->modules->isAutoload($module)) {
						$this->initModule($module);
					}
				}
				$completed[] = $class;
			}

			if($this->debug) $this->modules->debugTimerStop($debugKey2);
		}

		// if there is a dependency queue, go recursive till the queue is completed
		if(count($queue) && $level < 3) {
			$this->triggerInit($queue, $completed, $level + 1);
		}

		$this->modules->isInitialized(true);

		if($this->debug) if($debugKey) $this->modules->debugTimerStop($debugKey);

		if(!$level && $this->modules->info->moduleInfoCacheEmpty()) {
			if($this->debug) $this->message("saveModuleInfoCache from triggerInit");
			$this->modules->info->saveModuleInfoCache();
		}
	}
	
	/**
	 * Initialize a single module
	 *
	 * @param Module $module
	 * @param array $options
	 *  - `clearSettings` (bool): When true, module settings will be cleared when appropriate to save space. (default=true)
	 *  - `configOnly` (bool): When true, module init() method NOT called, but config data still set (default=false) 3.0.169+
	 *  - `configData` (array): Extra config data merge with module’s config data (default=[]) 3.0.169+
	 *  - `throw` (bool): When true, exceptions will be allowed to pass through. (default=false)
	 * @return bool True on success, false on fail
	 * @throws \Exception Only if the `throw` option is true.
	 *
	 */
	public function initModule(Module $module, array $options = array()) {

		$result = true;
		$debugKey = null;
		$clearSettings = isset($options['clearSettings']) ? (bool) $options['clearSettings'] : true;
		$throw = isset($options['throw']) ? (bool) $options['throw'] : false;

		if($this->debug) {
			static $n = 0;
			$this->message("initModule (" . (++$n) . "): " . wireClassName($module));
		}

		// if the module is configurable, then load its config data
		// and set values for each before initializing the module
		$extraConfigData = isset($options['configData']) ? $options['configData'] : null;
		$this->modules->configs->setModuleConfigData($module, null, $extraConfigData);

		$moduleName = wireClassName($module, false);
		$moduleID = $this->modules->moduleID($moduleName);

		if($moduleID && $this->modules->info->modulesLastVersions($moduleID)) {
			$this->modules->info->checkModuleVersion($module);
		}

		if(method_exists($module, 'init') && empty($options['configOnly'])) {

			if($this->debug) {
				$debugKey = $this->modules->debugTimerStart("initModule($moduleName)");
			}

			try {
				$module->init();
			} catch(\Exception $e) {
				if($throw) throw($e);
				$this->error(sprintf($this->_('Failed to init module: %s'), $moduleName) . " - " . $e->getMessage());
				$result = false;
			}

			if($this->debug) {
				$this->modules->debugTimerStop($debugKey);
			}
		}

		// if module is autoload (assumed here) and singular, then
		// we no longer need the module's config data, so remove it
		if($clearSettings && $this->modules->isSingular($module)) {
			if(!$moduleID) $moduleID = $this->modules->getModuleID($module);
			if($moduleID && $this->modules->configs->configData($moduleID) !== null) {
				$this->modules->configs->configData($moduleID, 1);
			}
		}

		return $result;
	}

	/**
	 * Call ready for a single module
	 *
	 * @param Module $module
	 * @return bool
	 *
	 */
	public function readyModule(Module $module) {
		$result = true;
		if(method_exists($module, 'ready')) {
			$debugKey = $this->debug ? $this->modules->debugTimerStart("readyModule(" . $module->className() . ")") : null;
			try {
				$module->ready();
			} catch(\Exception $e) {
				$this->error(sprintf($this->_('Failed to ready module: %s'), $module->className()) . " - " . $e->getMessage());
				$result = false;
			}
			if($this->debug) {
				$this->modules->debugTimerStop($debugKey);
				static $n = 0;
				$this->message("readyModule (" . (++$n) . "): " . wireClassName($module));
			}
		}
		return $result;
	}
	
	/**
	 * Trigger all modules 'ready' method, if they have it.
	 *
	 * This is to indicate to them that the API environment is fully ready and $page is in fuel.
	 *
	 * This is triggered by ProcessPageView::ready
	 *
	 * #pw-internal
	 *
	 */
	public function triggerReady() {

		$debugKey = $this->debug ? $this->modules->debugTimerStart("triggerReady") : null;
		$skipped = $this->triggerConditionalAutoload();

		// trigger ready method on all applicable modules
		foreach($this->modules as $module) {
			/** @var Module $module */
			if($module instanceof ModulePlaceholder) continue;

			$class = $this->modules->getModuleClass($module);
			
			if(isset($skipped[$class])) continue;

			$id = $this->modules->moduleID($class);
			$flags = $this->modules->flags->moduleFlags($id);
			
			if($flags & Modules::flagsAutoload) $this->readyModule($module);
		}

		if($this->debug) $this->modules->debugTimerStop($debugKey);
	}


	/**
	 * Init conditional autoload modules, if conditions allow
	 *
	 * @return array of skipped module names
	 *
	 */
	public function triggerConditionalAutoload() {

		// conditional autoload modules that are skipped (className => 1)
		$skipped = array();

		// init conditional autoload modules, now that $page is known
		foreach($this->conditionalAutoloadModules as $className => $func) {

			if($this->debug) {
				$moduleID = $this->modules->getModuleID($className);
				$flags = $this->modules->flags->moduleFlags($moduleID);
				$this->message("Conditional autoload: $className (flags=$flags, condition=" . (is_string($func) ? $func : 'func') . ")");
			}

			$load = true;

			if(is_string($func)) {
				// selector string
				if(!$this->wire()->page->is($func)) $load = false;
			} else {
				// anonymous function
				if(!is_callable($func)) $load = false;
				else if(!$func()) $load = false;
			}

			if($load) {
				$module = $this->modules->newModule($className);
				if($module) {
					$this->modules->set($className, $module);
					if($this->initModule($module)) {
						if($this->debug) $this->message("Conditional autoload: $className LOADED");
					} else {
						if($this->debug) $this->warning("Failed conditional autoload: $className");
					}
				}

			} else {
				$skipped[$className] = $className;
				if($this->debug) $this->message("Conditional autoload: $className SKIPPED");
			}
		}

		// clear this out since we don't need it anymore
		$this->conditionalAutoloadModules = array();

		return $skipped;
	}
	
	/**
	 * Retrieve the installed module info as stored in the database
	 *
	 */
	public function loadModulesTable() {

		$this->autoloadOrders = array();
		$database = $this->wire()->database;

		// skip loading dymanic caches at this stage
		$skipCaches = array(
			ModulesInfo::moduleInfoCacheUninstalledName,
			ModulesInfo::moduleInfoCacheVerboseName
		);

		$query = $database->query(
		// Currently: id, class, flags, data, with created added at sysupdate 7
			"SELECT * FROM modules " .
			"WHERE class NOT IN('" . implode("','", $skipCaches) . "') " .
			"ORDER BY class",
			"modules.loadModulesTable()"
		);

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {

			$moduleID = (int) $row['id'];
			$flags = (int) $row['flags'];
			$class = $row['class'];

			if($flags & Modules::flagsSystemCache) {
				// system cache names are prefixed with a '.' so they load first
				$this->modules->memcache(ltrim($class, '.'), $row['data']);
				continue;
			}

			$this->modules->moduleID($class, $moduleID);
			$this->modules->moduleName($moduleID, $class);
			$this->modules->flags->moduleFlags($moduleID, $flags);

			$autoload = $flags & Modules::flagsAutoload;
			$loadSettings = $autoload || ($flags & Modules::flagsDuplicate) || ($class === 'SystemUpdater');

			if($loadSettings) {
				// preload config data for autoload modules since we'll need it again very soon
				$data = $row['data'] ? json_decode($row['data'], true) : array();
				$this->modules->configs->configData($moduleID, $data);
				// populate information about duplicates, if applicable
				if($flags & Modules::flagsDuplicate) $this->modules->duplicates()->addFromConfigData($class, $data);

			} else if(!empty($row['data'])) {
				// indicate that it has config data, but not yet loaded
				$this->modules->configs->configData($moduleID, 1);
			}

			if(isset($row['created']) && $row['created'] != '0000-00-00 00:00:00') {
				$this->createdDates[$moduleID] = $row['created'];
			}

			if($autoload) {
				$value = $this->modules->info->moduleInfoCache($moduleID, 'autoload');
				if(!empty($value)) {
					$autoload = $value;
					$disabled = $flags & Modules::flagsDisabled;
					if(is_int($autoload) && $autoload > 1 && !$disabled) {
						// autoload specifies an order > 1, indicating it should load before others
						$this->autoloadOrders[$class] = $autoload;
					}
				}
			}

			unset($row['data'], $row['created']); // info we don't want stored in modulesTableCache
			$this->modulesTableCache[$class] = $row;
		}

		$query->closeCursor();
	}

	/**
	 * Given a disk path to the modules, determine all installed modules and keep track of all uninstalled (installable) modules.
	 *
	 * @param string $path
	 *
	 */
	public function loadPath($path) {

		$config = $this->wire()->config;
		$debugKey = $this->debug ? $this->modules->debugTimerStart("loadPath($path)") : null;
		$installed =& $this->modulesTableCache;
		$modulesLoaded = array();
		$modulesDelayed = array();
		$modulesRequired = array();
		$modulesFiles = $this->modules->files;
		$rootPath = $config->paths->root;
		$basePath = substr($path, strlen($rootPath));

		foreach($modulesFiles->findModuleFiles($path, true) as $pathname) {

			$pathname = trim($pathname);
			if(empty($pathname)) continue;
			$basename = basename($pathname);
			list($moduleName, $ext) = explode('.', $basename, 2); // i.e. "module.php" or "module"

			$modulesFiles->moduleFileExt($moduleName, $ext === 'module' ? 1 : 2);
			// @todo next, remove the 'file' property from verbose module info since it is redundant

			$requires = array();
			$name = $moduleName;
			$moduleName = $this->loadModule($path, $pathname, $requires, $installed);
			if(!$config->paths->__isset($name)) $modulesFiles->setConfigPaths($name, dirname($basePath . $pathname));
			if(!$moduleName) continue;

			if(count($requires)) {
				// module not loaded because it required other module(s) not yet loaded
				foreach($requires as $requiresModuleName) {
					if(!isset($modulesRequired[$requiresModuleName])) $modulesRequired[$requiresModuleName] = array();
					if(!isset($modulesDelayed[$moduleName])) $modulesDelayed[$moduleName] = array();
					// queue module for later load
					$modulesRequired[$requiresModuleName][$moduleName] = $pathname;
					$modulesDelayed[$moduleName][] = $requiresModuleName;
				}
				continue;
			}

			// module was successfully loaded
			$modulesLoaded[$moduleName] = 1;
			$loadedNames = array($moduleName);

			// now determine if this module had any other modules waiting on it as a dependency
			/** @noinspection PhpAssignmentInConditionInspection */
			while($moduleName = array_shift($loadedNames)) {
				// iternate through delayed modules that require this one
				if(empty($modulesRequired[$moduleName])) continue;

				foreach($modulesRequired[$moduleName] as $delayedName => $delayedPathName) {
					$loadNow = true;
					if(isset($modulesDelayed[$delayedName])) {
						foreach($modulesDelayed[$delayedName] as $requiresModuleName) {
							if(!isset($modulesLoaded[$requiresModuleName])) {
								$loadNow = false;
							}
						}
					}
					if(!$loadNow) continue;
					// all conditions satisified to load delayed module
					unset($modulesDelayed[$delayedName], $modulesRequired[$moduleName][$delayedName]);
					$unused = array();
					$loadedName = $this->loadModule($path, $delayedPathName, $unused, $installed);
					if(!$loadedName) continue;
					$modulesLoaded[$loadedName] = 1;
					$loadedNames[] = $loadedName;
				}
			}
		}

		if(count($modulesDelayed)) {
			foreach($modulesDelayed as $moduleName => $requiredNames) {
				$this->error("Module '$moduleName' dependency not fulfilled for: " . implode(', ', $requiredNames), Notice::debug);
			}
		}

		if($this->debug) $this->modules->debugTimerStop($debugKey);
	}

	/**
	 * Load a module into memory (companion to load bootstrap method)
	 *
	 * @param string $basepath Base path of modules being processed (path provided to the load method)
	 * @param string $pathname
	 * @param array $requires This method will populate this array with required dependencies (class names) if present.
	 * @param array $installed Array of installed modules info, indexed by module class name
	 * @return string Returns module name (classname)
	 *
	 */
	public function loadModule($basepath, $pathname, array &$requires, array &$installed) {

		$pathname = $basepath . $pathname;
		$dirname = dirname($pathname);
		$filename = basename($pathname);
		$basename = basename($filename, '.php');
		$basename = basename($basename, '.module');
		$requires = array();
		$duplicates = $this->modules->duplicates();

		// check if module has duplicate files, where one to use has already been specified to use first
		$currentFile = $duplicates->getCurrent($basename); // returns the current file in use, if more than one
		if($currentFile) {
			// there is a duplicate file in use
			$file = rtrim($this->wire()->config->paths->root, '/') . $currentFile;
			if(file_exists($file) && $pathname != $file) {
				// file in use is different from the file we are looking at
				// check if this is a new/yet unknown duplicate
				if(!$duplicates->hasDuplicate($basename, $pathname)) {
					// new duplicate
					$duplicates->recordDuplicate($basename, $pathname, $file, $installed);
				}
				return '';
			}
		}

		// check if module has already been loaded, or maybe we've got duplicates
		if(wireClassExists($basename, false)) {
			$module = $this->modules->offsetGet($basename);
			$dir = rtrim((string) $this->wire()->config->paths($basename), '/');
			if($module && $dir && $dirname != $dir) {
				$duplicates->recordDuplicate($basename, $pathname, "$dir/$filename", $installed);
				return '';
			}
			if($module) return $basename;
		}

		// if the filename doesn't end with .module or .module.php, then stop and move onto the next
		if(strpos($filename, '.module') === false) return false;
		list(, $ext) = explode('.module', $filename, 2);
		if(!empty($ext) && $ext !== '.php') return false;

		//  if the filename doesn't start with the requested path, then skip
		if(strpos($pathname, $basepath) !== 0) return '';

		// if the file isn't there, it was probably uninstalled, so ignore it
		// if(!file_exists($pathname)) return ''; 

		// if the module isn't installed, then stop and move on to next
		if(!isset($installed[$basename])) {
			// array_key_exists is used as secondary to check the null case
			$this->modules->installableFile($basename, $pathname);
			return '';
		}

		$info = $installed[$basename];
		$this->modules->files->setConfigPaths($basename, $dirname);
		$module = null;
		$autoload = false;

		if($info['flags'] & Modules::flagsAutoload) {

			// this is an Autoload module. 
			// include the module and instantiate it but don't init() it,
			// because it will be done by Modules::init()

			// determine if module has dependencies that are not yet met
			$requiresClasses = $this->modules->info->getModuleInfoProperty($basename, 'requires');
			if(!empty($requiresClasses)) {
				foreach($requiresClasses as $requiresClass) {
					$nsRequiresClass = $this->modules->getModuleClass($requiresClass, true);
					if(!wireClassExists($nsRequiresClass, false)) {
						$requiresInfo = $this->modules->getModuleInfo($requiresClass);
						if(!empty($requiresInfo['error'])
							|| $requiresInfo['autoload'] === true
							|| !$this->modules->isInstalled($requiresClass)) {
							// we only handle autoload===true since load() only instantiates other autoload===true modules
							$requires[] = $requiresClass;
						}
					}
				}
				if(count($requires)) {
					// module has unmet requirements
					return $basename;
				}
			}
			// if not defined in getModuleInfo, then we'll accept the database flag as enough proof
			// since the module may have defined it via an isAutoload() function
			/** @var bool|string|callable $autoload */
			$autoload = $this->modules->info->moduleInfoCache($basename, 'autoload');
			if(empty($autoload)) $autoload = true;
			if($autoload === 'function') {
				// function is stored by the moduleInfo cache to indicate we need to call a dynamic function specified with the module itself
				$i = $this->modules->info->getModuleInfoExternal($basename);
				if(empty($i)) {
					$this->modules->files->includeModuleFile($pathname, $basename);
					$namespace = $this->modules->info->getModuleNamespace($basename);
					$className = $namespace . $basename;
					if(method_exists($className, 'getModuleInfo')) {
						$i = $className::getModuleInfo();
					} else {
						$i = $this->modules->getModuleInfo($className);
					}
				}
				$autoload = isset($i['autoload']) ? $i['autoload'] : true;
				unset($i);
			}
			// check for conditional autoload
			if(!is_bool($autoload) && (is_string($autoload) || is_callable($autoload)) && !($info['flags'] & Modules::flagsDisabled)) {
				// anonymous function or selector string
				$this->conditionalAutoloadModules[$basename] = $autoload;
				$this->modules->moduleID($basename, (int) $info['id']); 
				$this->modules->moduleName((int) $info['id'], $basename);
				$autoload = true;
			} else if($autoload) {
				$this->modules->files->includeModuleFile($pathname, $basename);
				if(!($info['flags'] & Modules::flagsDisabled)) {
					if($this->modules->refreshing) {
						$module = $this->modules->offsetGet($basename);
					} else if(isset($this->autoloadOrders[$basename]) && $this->autoloadOrders[$basename] >= 10000) {
						$module = $this->modules->offsetGet($basename); // preloaded module
					}
					if(!$module) $module = $this->modules->newModule($basename);
				}
			}
		}

		if($module === null) {
			// placeholder for a module, which is not yet included and instantiated
			$ns = $this->modules->info->moduleInfoCache($basename, 'namespace');
			if(empty($ns)) $ns = __NAMESPACE__ . "\\";
			$singular = $info['flags'] & Modules::flagsSingular;
			$module = $this->newModulePlaceholder($basename, $ns, $pathname, $singular, $autoload);
		}

		$this->modules->moduleID($basename, (int) $info['id']);
		$this->modules->moduleName((int) $info['id'], $basename);
		$this->modules->set($basename, $module);

		return $basename;
	}
	
	/**
	 * Include the file for a given module, but don't instantiate it
	 *
	 * #pw-internal
	 *
	 * @param ModulePlaceholder|Module|string Expects a ModulePlaceholder or className
	 * @param string $file Optionally specify the module filename if you already know it
	 * @return bool true on success, false on fail or unknown
	 *
	 */
	public function includeModule($module, $file = '') {

		$className = '';
		$moduleName = '';

		if(is_string($module)) {
			$moduleName = ctype_alnum($module) ? $module : wireClassName($module);
			$className = wireClassName($module, true);
		} else if(is_object($module)) {
			if($module instanceof ModulePlaceholder) {
				$moduleName = $module->className();
				$className = $module->className(true);
			} else if($module instanceof Module) {
				return true; // already included
			}
		} else {
			$moduleName = $this->modules->getModuleClass($module, false);
			$className = $this->modules->getModuleClass($module, true);
		}

		if(!$className) return false;

		// already included
		if(class_exists($className, false)) return true;

		// attempt to retrieve module
		$module = $this->modules->offsetGet($moduleName); 

		if($module) {
			// module found, check to make sure it actually points to a module	
			if(!$module instanceof Module) $module = false;

		} else if($moduleName) {
			// This is reached for any of the following:
			// 1. an uninstalled module
			// 2. an installed module that has changed locations
			// 3. a module outside the \ProcessWire\ namespace
			// 4. a module that does not exist
			$fast = true;
			if(!$file) {
				// determine module file, if not already provided to the method
				$file = $this->modules->getModuleFile($moduleName, array('fast' => true));
				if(!$file) {
					$fast = false;
					$file = $this->modules->getModuleFile($moduleName, array('fast' => false));
				}
				// still can't figure out what file is? fail
				if(!$file) return false;
			}

			if(!$this->modules->files->includeModuleFile($file, $moduleName)) {
				// module file failed to include(), try to identify and include file again
				if($fast) {
					$filePrev = $file;
					$file = $this->modules->getModuleFile($moduleName, array('fast' => false));
					if($file && $file !== $filePrev) {
						if($this->modules->files->includeModuleFile($file, $moduleName)) {
							// module is missing a module file
							return false;
						}
					}
				} else {
					// we already tried this earlier, no point in doing it again
				}
			}

			// now check to see if included file resulted in presence of module class
			if(class_exists($className)) {
				// module in ProcessWire namespace
				$module = true;
			} else {
				// module in root namespace or some other namespace
				$namespace = (string) $this->modules->info->getModuleNamespace($moduleName, array('file' => $file));
				$className = trim($namespace, "\\") . "\\$moduleName";
				if(class_exists($className, false)) {
					// successful include module
					$module = true;
				}
			}
		}

		if($module === true) {
			// great
			return true;

		} else if(!$module) {
			// darn
			return false;

		} else if($module instanceof ModulePlaceholder) {
			// the ModulePlaceholder indicates what file to load
			return $this->modules->files->includeModuleFile($module->file, $moduleName);

		} else if($module instanceof Module) {
			// it's already been included, since we have a real module
			return true;

		} else {
			return false;
		}
	}

	/**
	 * Check if user has permission for given module
	 *
	 * #pw-internal
	 *
	 * @param string|object $moduleName Module instance or module name
	 * @param User|null $user Optionally specify different user to consider than current.
	 * @param Page|null $page Optionally specify different page to consider than current.
	 * @param bool $strict If module specifies no permission settings, assume no permission.
	 *   - Default (false) is to assume permission when module doesn't say anything about it.
	 *   - Process modules (for instance) generally assume no permission when it isn't specifically defined
	 *     (though this method doesn't get involved in that, leaving you to specify $strict instead).
	 *
	 * @return bool
	 *
	 */
	public function hasPermission($moduleName, ?User $user = null, ?Page $page = null, $strict = false) {

		if(is_object($moduleName)) {
			$module = $moduleName;
			$className = $module->className(true);
			$moduleName = $module->className(false);
		} else {
			$module = null;
			$className = $this->modules->getModuleClass($moduleName, true); // ???
			$moduleName = wireClassName($moduleName, false);
		}

		$info = $this->modules->getModuleInfo($module ? $module : $moduleName);
		
		if(empty($info['permission']) && empty($info['permissionMethod'])) {
			return ($strict ? false : true);
		}

		if(!$user instanceof User) $user = $this->wire()->user;
		if($user && $user->isSuperuser()) return true;

		if(!empty($info['permission'])) {
			if(!$user->hasPermission($info['permission'])) return false;
		}

		if(!empty($info['permissionMethod'])) {
			// module specifies a static method to call for permission
			if(is_null($page)) $page = $this->wire()->page;
			$data = array(
				'wire' => $this->wire(),
				'page' => $page,
				'user' => $user,
				'info' => $info,
			);
			$method = $info['permissionMethod'];
			$this->includeModule($moduleName);
			
			return method_exists($className, $method) ? $className::$method($data) : false;
		}

		return true;
	}
	
	/**
	 * Include site preload modules
	 *
	 * Preload modules load before all other modules, including core modules. In order
	 * for a module to be a preload module, it must meet the following conditions:
	 *
	 * - Module info `autoload` value is integer of 10000 or greater, i.e. `[ 'autoload' => 10000 ]`
	 * - Module info `singular` value must be non-empty, i.e. `[ 'singular' => true ]`
	 * - Module file is located in: /site/modules/ModuleName/ModuleName.module.php
	 * - Module cannot load any other modules at least until ready() method called.
	 * - Module cannot have any `requires` dependencies to any other modules.
	 *
	 * Please note the above is specifically stating that the module must be in its
	 * own “site/ModuleName/” directory and have the “.module.php” extension. Using
	 * just the “.module” extension is not supported for preload modules.
	 *
	 * @param string $path
	 * @since 3.0.173
	 *
	 */
	public function preloadModules($path) {
		
		if(empty($this->autoloadOrders)) return;
		
		arsort($this->autoloadOrders);
		
		foreach($this->autoloadOrders as $moduleName => $order) {
			if($order < 10000) break;
			$info = $this->modules->info->moduleInfoCache($moduleName);
			if(empty($info)) continue;
			if(empty($info['singular'])) continue;
			$file = $path . "$moduleName/$moduleName.module.php";
			if(!file_exists($file) || !$this->modules->files->includeModuleFile($file, $moduleName)) continue;
			if(!isset($info['namespace'])) $info['namespace'] = '';
			$className = $info['namespace'] . $moduleName;
			$module = $this->modules->newModule($className, $moduleName);
			if($module) {
				$this->modules->offsetSet($moduleName, $module);
			}
		}
	}


	/**
	 * Get or set created date for given module ID
	 *
	 * #pw-internal
	 *
	 * @param int $moduleID Module ID or omit to get all
	 * @param string $setValue Set created date value
	 * @return string|array|null
	 * @since 3.0.219
	 *
	 */
	public function createdDate($moduleID = null, $setValue = null) {
		if($moduleID === null) return $this->createdDates;
		if($setValue) {
			$this->createdDates[$moduleID] = $setValue;
			return $setValue;
		}
		return isset($this->createdDates[$moduleID]) ? $this->createdDates[$moduleID] : null;
	}

	/**
	 * Return a new ModulePlaceholder for the given className
	 *
	 * #pw-internal
	 *
	 * @param string $className Module class this placeholder will stand in for
	 * @param string $ns Module namespace
	 * @param string $file Full path and filename of $className
	 * @param bool $singular Is the module a singular module?
	 * @param bool $autoload Is the module an autoload module?
	 * @return ModulePlaceholder
	 *
	 */
	public function newModulePlaceholder($className, $ns, $file, $singular, $autoload) {
		/** @var ModulePlaceholder $module */
		$module = $this->wire(new ModulePlaceholder());
		$module->setClass($className);
		$module->setNamespace($ns);
		$module->singular = $singular;
		$module->autoload = $autoload;
		$module->file = $file;
		return $module;
	}

	/**
	 * Called by Modules class when init has finished
	 * 
	 */
	public function loaded() {
		$this->modulesTableCache = array();
	}

	/**
	 * Get the autoload orders
	 * 
	 * @return array Array of [ moduleName (string => order (int) ]
	 * 
	 */
	public function getAutoloadOrders() {
		return $this->autoloadOrders;
	}

	public function getDebugData() {
		return array(
			'autoloadOrders' => $this->autoloadOrders,
			'conditionalAutoloadModules' => $this->conditionalAutoloadModules,
			'modulesTableCache' => $this->modulesTableCache,
			'createdDates' => $this->createdDates,
		);
	}

}
