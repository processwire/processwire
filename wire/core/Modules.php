<?php namespace ProcessWire;

/**
 * ProcessWire Modules
 *
 * Loads and manages all runtime modules for ProcessWire
 *
 * Note that when iterating, find(), or calling any other method that returns module(s), excepting get(), a ModulePlaceholder may be
 * returned rather than a real Module. ModulePlaceholders are used in instances when the module may or may not be needed at runtime
 * in order to save resources. As a result, anything iterating through these Modules should check to make sure it's not a ModulePlaceholder
 * before using it. If it's a ModulePlaceholder, then the real Module can be instantiated/retrieved by $modules->get($className).
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Loads and manages all modules in ProcessWire. 
 * #pw-body = 
 * The `$modules` API variable is most commonly used for getting individual modules to use their API. 
 * ~~~~~
 * // Getting a module by name
 * $m = $modules->get('MarkupPagerNav');
 * 
 * // Getting a module by name (alternate)
 * $m = $modules->MarkupPagerNav;
 * ~~~~~
 * 
 * #pw-body
 * 
 * @todo Move all module information methods to a ModulesInfo class
 * @todo Move all module loading methods to a ModulesLoad class
 * 
 * @method void refresh() Refresh the cache that stores module files by recreating it
 * @method null|Module install($class, $options = array())
 * @method bool|int delete($class)
 * @method bool uninstall($class)
 * @method bool saveModuleConfigData($className, array $configData) Alias of saveConfig() method #pw-internal
 * @method bool saveConfig($class, $data, $value = null)
 * @method InputfieldWrapper|null getModuleConfigInputfields($moduleName, InputfieldWrapper $form = null)  #pw-internal
 * @method void moduleVersionChanged(Module $module, $fromVersion, $toVersion) #pw-internal
 *
 */

class Modules extends WireArray {
	
	/**
	 * Whether or not module debug mode is active
	 *
	 */
	protected $debug = false;

	/**
	 * Flag indicating the module may have only one instance at runtime. 
	 *
	 */
	const flagsSingular = 1; 

	/**
	 * Flag indicating that the module should be instantiated at runtime, rather than when called upon. 
	 *
	 */
	const flagsAutoload = 2;

	/**
	 * Flag indicating the module has more than one copy of it on the file system. 
	 * 
	 */
	const flagsDuplicate = 4;

	/**
	 * When combined with flagsAutoload, indicates that the autoload is conditional 
	 * 
	 */
	const flagsConditional = 8;

	/**
	 * When combined with flagsAutoload, indicates that the module's autoload state is temporarily disabled
	 * 
	 */
	const flagsDisabled = 16;

	/**
	 * Indicates module that maintains a configurable interface but with no interactive Inputfields
	 * 
	 */
	const flagsNoUserConfig = 32;

	/**
	 * Filename for module info cache file
	 *
	 */
	const moduleInfoCacheName = 'Modules.info';
	
	/**
	 * Filename for verbose module info cache file
	 *
	 */
	const moduleInfoCacheVerboseName = 'ModulesVerbose.info';
	
	/**
	 * Filename for uninstalled module info cache file
	 *
	 */
	const moduleInfoCacheUninstalledName = 'ModulesUninstalled.info';

	/**
	 * Cache name for module version change cache
	 * 
	 */
	const moduleLastVersionsCacheName = 'ModulesVersions.info';

	/**
	 * Array of modules that are not currently installed, indexed by className => filename
	 *
	 */
	protected $installable = array(); 

	/**
	 * An array of module database IDs indexed by: class => id 
	 *
	 * Used internally for database operations
	 *
	 */
	protected $moduleIDs = array();

	/**
	 * Full system paths where modules are stored
	 * 
	 * index 0 must be the core modules path (/i.e. /wire/modules/)
	 *
	 */
	protected $paths = array();

	/**
	 * Cached module configuration data indexed by module ID
	 * 
	 * Values are integer 1 for modules that have config data but data is not yet loaded.
	 * Values are an array for modules have have config data and has been loaded. 
	 *
	 */
	protected $configData = array();
	
	/**
	 * Module created dates indexed by module ID
	 *
	 */
	protected $createdDates = array();

	/**
	 * Have the modules been init'd() ?
	 *
	 */
	protected $initialized = false;

	/**
	 * Becomes an array if debug mode is on
	 *
	 */
	protected $debugLog = array();

	/**
	 * Array of moduleName => condition
	 * 
	 * Condition can be either an anonymous function or a selector string to be evaluated at ready().
	 *
	 */
	protected $conditionalAutoloadModules = array();

	/**
	 * Cache of module information
	 *
	 */
	protected $moduleInfoCache = array();
	
	/**
	 * Cache of module information (verbose text) including: summary, author, href, file, core
	 *
	 */
	protected $moduleInfoCacheVerbose = array();
	
	/**
	 * Cache of uninstalled module information (verbose for uninstalled) including: summary, author, href, file, core
	 * 
	 * Note that this one is indexed by class name rather than by ID (since uninstalled modules have no ID)
	 *
	 */
	protected $moduleInfoCacheUninstalled = array();

	/**
	 * Cache of module information from DB used across multiple calls temporarily by load() method
	 *
	 */
	protected $modulesTableCache = array();

	/**
	 * Cache of namespace => path for unique module namespaces
	 * 
	 * @var array|null Becomes an array once populated
	 * 
	 */
	protected $moduleNamespaceCache = null;
	
	/**
	 * Last known versions of modules, for version change tracking
	 *
	 * @var array of ModuleName (string) => last known version (integer|string)
	 *
	 */
	protected $modulesLastVersions = array();

	/**
	 * Array of module ID => flags (int)
	 * 
	 * @var array
	 * 
	 */
	protected $moduleFlags = array();
	
	/**
	 * Array of moduleName => substituteModuleName to be used when moduleName doesn't exist
	 * 
	 * Primarily for providing backwards compatiblity with modules assumed installed that 
	 * may no longer be in core. 
	 * 
	 * see setSubstitutes() method
	 *
	 */
	protected $substitutes = array();

	/**
	 * Instance of ModulesDuplicates
	 * 
	 * @var ModulesDuplicates
	 * 
	 */	
	protected $duplicates;

	/**
	 * Module file extensions indexed by module name where value 1=.module, and 2=.module.php
	 * 
	 * @var array
	 * 
	 */
	protected $moduleFileExts = array();

	/**
	 * Dir for core modules relative to root path, i.e. '/wire/modules/'
	 * 
	 * @var string
	 * 
	 */
	protected $coreModulesDir = '';

	/**
	 * Array of moduleName => order to indicate autoload order when necessary
	 * 
	 * @var array
	 * 
	 */
	protected $autoloadOrders = array();

	/**
	 * Are we currently refreshing?
	 * 
	 * @var bool
	 * 
	 */
	protected $refreshing = false;

	/**
	 * Properties that only appear in 'verbose' moduleInfo
	 * 
	 * @var array
	 * 
	 */
	protected $moduleInfoVerboseKeys = array(
		'summary', 
		'author', 
		'href', 
		'file', 
		'core', 
		'versionStr',
		'permissions',
		'searchable', 
		'page',
		);

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
	 * Construct the Modules
	 *
	 * @param string $path Core modules path (you may add other paths with addPath method)
	 *
	 */
	public function __construct($path) {
		$this->addPath($path); 
		$this->coreModulesDir = '/' . $this->wire('config')->urls->data('modules');
	}

	/**
	 * Get the ModulesDuplicates instance
	 * 
	 * #pw-internal
	 * 
	 * @return ModulesDuplicates
	 * 
	 */
	public function duplicates() {
		if(is_null($this->duplicates)) $this->duplicates = $this->wire(new ModulesDuplicates());
		return $this->duplicates; 
	}

	/**
	 * Add another modules path, must be called before init()
	 * 
	 * #pw-internal
	 *
	 * @param string $path 
	 *
	 */
	public function addPath($path) {
		$this->paths[] = $path;
	}

	/**
	 * Return all assigned module root paths
	 * 
	 * #pw-internal
	 *
	 * @return array of modules paths, with index 0 always being the core modules path.
	 *
	 */
	public function getPaths() {
		return $this->paths; 
	}

	/**
	 * Initialize modules
	 * 
	 * Must be called after construct before this class is ready to use
	 * 
	 * #pw-internal
	 * 
	 * @see load()
	 * 
	 */
	public function init() {
		$this->setTrackChanges(false);
		$this->loadModuleInfoCache();
		$this->loadModulesTable();
		foreach($this->paths as $path) {
			$this->load($path);
		}
		$this->modulesTableCache = array(); // clear out data no longer needed
	}

	/**
	 * Modules class accepts only Module instances, per the WireArray interface
	 * 
	 * #pw-internal
	 * 
	 * @param Wire $item
	 * @return bool
 	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Module;
	}

	/**
	 * The key/index used for each module in the array is it's class name, per the WireArray interface
	 * 
	 * #pw-internal
	 * 
	 * @param Wire $item
	 * @return int|string
 	 *
	 */
	public function getItemKey($item) {
		return $this->getModuleClass($item); 
	}

	/**
	 * There is no blank/generic module type, so makeBlankItem returns null
	 * 
	 * #pw-internal
 	 *
	 */
	public function makeBlankItem() {
		return null; 
	}

	/**
	 * Make a new/blank WireArray
	 * 
	 * #pw-internal
 	 *
	 */
	public function makeNew() {
		// ensures that find(), etc. operations don't initalize a new Modules() class
		return $this->wire(new WireArray());
	}

	/**
	 * Make a new populated copy of a WireArray containing all the modules
	 * 
	 * #pw-internal
	 *
	 * @return WireArray
 	 *
	 */
	public function makeCopy() {
		// ensures that find(), etc. operations don't initalize a new Modules() class
		$copy = $this->makeNew();
		foreach($this->data as $key => $value) $copy[$key] = $value; 
		$copy->resetTrackChanges($this->trackChanges()); 
		return $copy; 
	}

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
			$debugKey = $this->debugTimerStart("triggerInit$level");
			$this->message("triggerInit(level=$level)"); 
		}
		
		$queue = array();
		if(is_null($modules)) $modules = $this;

		foreach($modules as $class => $module) {
		
			if($module instanceof ModulePlaceholder) {
				// skip modules that aren't autoload and those that are conditional autoload
				if(!$module->autoload) continue;
				if(isset($this->conditionalAutoloadModules[$class])) continue;
			}
			
			if($this->debug) $debugKey2 = $this->debugTimerStart("triggerInit$level($class)"); 
			
			$info = $this->getModuleInfo($module); 
			$skip = false;

			// module requires other modules
			foreach($info['requires'] as $requiresClass) {
				if(in_array($requiresClass, $completed)) continue; 
				$dependencyInfo = $this->getModuleInfo($requiresClass);
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
					if($info['autoload'] === true || $this->isAutoload($module)) {
						$this->initModule($module);
					}
				}
				$completed[] = $class;
			}
			
			if($this->debug) $this->debugTimerStop($debugKey2); 
		}

		// if there is a dependency queue, go recursive till the queue is completed
		if(count($queue) && $level < 3) {
			$this->triggerInit($queue, $completed, $level + 1);
		}

		$this->initialized = true;
		
		if($this->debug) if($debugKey) $this->debugTimerStop($debugKey);
		
		if(!$level && (empty($this->moduleInfoCache))) { // || empty($this->moduleInfoCacheVerbose))) {
			if($this->debug) $this->message("saveModuleInfoCache from triggerInit"); 
			$this->saveModuleInfoCache();
		}
	}

	/**
	 * Given a class name, return the constructed module
	 * 
	 * @param string $className Module class name
	 * @return Module|null
	 *
	 */
	protected function newModule($className) {
		$moduleName = wireClassName($className, false);
		$className = wireClassName($className, true);
		$debugKey = $this->debug ? $this->debugTimerStart("newModule($moduleName)") : null;
		if(!class_exists($className, false)) $this->includeModule($moduleName);
		if(!class_exists($className, false)) {
			// attempt 2.x module in dedicated namespace or root namespace
			$className = $this->getModuleNamespace($moduleName) . $moduleName;
		}

		if(ProcessWire::getNumInstances() > 1) {
			// in a multi-instance environment, ensures that anything happening during
			// the module __construct is using the right instance. necessary because the
			// construct method runs before the wire instance is set to the module
			$wire1 = ProcessWire::getCurrentInstance();
			$wire2 = $this->wire();
			if($wire1 !== $wire2) {
				ProcessWire::setCurrentInstance($wire2);
			} else {
				$wire1 = null;
			}
		} else {
			$wire1 = null;
			$wire2 = null;
		}
		
		try {
			$module = $this->wire(new $className());
		} catch(\Exception $e) {
			$this->error(sprintf($this->_('Failed to construct module: %s'), $className) . " - " . $e->getMessage());
			$module = null;
		}
		if($this->debug) $this->debugTimerStop($debugKey);
		if($wire1) ProcessWire::setCurrentInstance($wire1);
		return $module; 
	}

	/**
	 * Return a new ModulePlaceholder for the given className
	 * 
	 * @param string $className Module class this placeholder will stand in for
	 * @param string $ns Module namespace
	 * @param string $file Full path and filename of $className
	 * @param bool $singular Is the module a singular module?
	 * @param bool $autoload Is the module an autoload module?
	 * @return ModulePlaceholder
	 *
	 */
	protected function newModulePlaceholder($className, $ns, $file, $singular, $autoload) { 
		$module = $this->wire(new ModulePlaceholder());
		$module->setClass($className);
		$module->setNamespace($ns);
		$module->singular = $singular;
		$module->autoload = $autoload;
		$module->file = $file;
		return $module; 
	}

	/**
	 * Initialize a single module
	 * 
	 * @param Module $module
	 * @param array $options
	 *  - `clearSettings` (bool): When true, module settings will be cleared when appropriate to save space. (default=true)
	 *  - `throw` (bool): When true, exceptions will be allowed to pass through. (default=false)
	 * @return bool True on success, false on fail
	 * @throws \Exception Only if the `throw` option is true. 
	 *
	 */
	protected function initModule(Module $module, array $options = array()) {
		
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
		$this->setModuleConfigData($module);
		
		$moduleName = wireClassName($module, false);
		$moduleID = isset($this->moduleIDs[$moduleName]) ? $this->moduleIDs[$moduleName] : 0;
		
		if($moduleID && isset($this->modulesLastVersions[$moduleID])) {
			$this->checkModuleVersion($module);
		}
		
		if(method_exists($module, 'init')) {
			
			if($this->debug) {
				$debugKey = $this->debugTimerStart("initModule($moduleName)"); 
			}
	
			try {
				$module->init();
			} catch(\Exception $e) {
				if($throw) throw($e);
				$this->error(sprintf($this->_('Failed to init module: %s'), $moduleName) . " - " . $e->getMessage());
				$result = false;
			}
			
			if($this->debug) {
				$this->debugTimerStop($debugKey);
			}
		}
		
		// if module is autoload (assumed here) and singular, then
		// we no longer need the module's config data, so remove it
		if($clearSettings && $this->isSingular($module)) {
			if(!$moduleID) $moduleID = $this->getModuleID($module);
			if(isset($this->configData[$moduleID])) $this->configData[$moduleID] = 1;
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
	protected function readyModule(Module $module) {
		$result = true;
		if(method_exists($module, 'ready')) {
			$debugKey = $this->debug ? $this->debugTimerStart("readyModule(" . $module->className() . ")") : null; 
			try {
				$module->ready();
			} catch(\Exception $e) {
				$this->error(sprintf($this->_('Failed to ready module: %s'), $module->className()) . " - " . $e->getMessage());
				$result = false;
			}
			if($this->debug) {
				$this->debugTimerStop($debugKey);
				static $n = 0;
				$this->message("readyModule (" . (++$n) . "): " . wireClassName($module));
			}
		}
		return $result;
	}

	/**
	 * Init conditional autoload modules, if conditions allow
	 * 
	 * @return array of skipped module names
	 * 
	 */
	protected function triggerConditionalAutoload() {
		
		// conditional autoload modules that are skipped (className => 1)
		$skipped = array();

		// init conditional autoload modules, now that $page is known
		foreach($this->conditionalAutoloadModules as $className => $func) {

			if($this->debug) {
				$moduleID = $this->getModuleID($className);
				$flags = $this->moduleFlags[$moduleID];
				$this->message("Conditional autoload: $className (flags=$flags, condition=" . (is_string($func) ? $func : 'func') . ")");
			}

			$load = true;

			if(is_string($func)) {
				// selector string
				if(!$this->wire('page')->is($func)) $load = false;
			} else {
				// anonymous function
				if(!is_callable($func)) $load = false;
					else if(!$func()) $load = false;
			}

			if($load) {
				$module = $this->newModule($className);
				if($module) {
					$this->set($className, $module);
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
		
		$debugKey = $this->debug ? $this->debugTimerStart("triggerReady") : null; 
		
		$skipped = $this->triggerConditionalAutoload();
		
		// trigger ready method on all applicable modules
		foreach($this as $module) {
			/** @var Module $module */
			if($module instanceof ModulePlaceholder) continue;
			
			// $info = $this->getModuleInfo($module); 
			// if($info['autoload'] === false) continue; 
			// if(!$this->isAutoload($module)) continue; 
			
			$class = $this->getModuleClass($module); 
			if(isset($skipped[$class])) continue; 
			
			$id = $this->moduleIDs[$class];
			if(!($this->moduleFlags[$id] & self::flagsAutoload)) continue;
			
			if(!method_exists($module, 'ready')) continue;
			
			$this->readyModule($module);
		}
		
		if($this->debug) $this->debugTimerStop($debugKey); 
	}

	/**
	 * Retrieve the installed module info as stored in the database
	 *
	 */
	protected function loadModulesTable() {
		$this->autoloadOrders = array();
		$database = $this->wire('database');
		// we use SELECT * so that this select won't be broken by future DB schema additions
		// Currently: id, class, flags, data, with created added at sysupdate 7
		$query = $database->prepare("SELECT * FROM modules ORDER BY class", "modules.loadModulesTable()"); // QA
		$query->execute();

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			
			$moduleID = (int) $row['id'];
			$flags = (int) $row['flags'];
			$class = $row['class'];
			$this->moduleIDs[$class] = $moduleID;
			$this->moduleFlags[$moduleID] = $flags;
			$autoload = $flags & self::flagsAutoload;
			$loadSettings = $autoload || ($flags & self::flagsDuplicate) || ($class == 'SystemUpdater');
			
			if($loadSettings) {
				// preload config data for autoload modules since we'll need it again very soon
				$data = strlen($row['data']) ? wireDecodeJSON($row['data']) : array();
				$this->configData[$moduleID] = $data;
				// populate information about duplicates, if applicable
				if($flags & self::flagsDuplicate) $this->duplicates()->addFromConfigData($class, $data); 
				
			} else if(!empty($row['data'])) {
				// indicate that it has config data, but not yet loaded
				$this->configData[$moduleID] = 1; 
			}
			
			if(isset($row['created']) && $row['created'] != '0000-00-00 00:00:00') {
				$this->createdDates[$moduleID] = $row['created']; 
			}
			
			if($autoload && !empty($this->moduleInfoCache[$moduleID]['autoload'])) {
				$autoload = $this->moduleInfoCache[$moduleID]['autoload'];
				if(is_int($autoload) && $autoload > 1) {
					// autoload specifies an order > 1, indicating it should load before others
					$this->autoloadOrders[$class] = $autoload;
				}
			}
			
			unset($row['data']); // info we don't want stored in modulesTableCache
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
	protected function load($path) {

		$config = $this->wire('config');
		$debugKey = $this->debug ? $this->debugTimerStart("load($path)") : null; 
		$installed =& $this->modulesTableCache;
		$modulesLoaded = array();
		$modulesDelayed = array();
		$modulesRequired = array();
		$rootPath = $config->paths->root;
		$basePath = substr($path, strlen($rootPath));

		foreach($this->findModuleFiles($path, true) as $pathname) {
	
			$pathname = trim($pathname);
			if(empty($pathname)) continue;
			$basename = basename($pathname);
			list($moduleName, $ext) = explode('.', $basename, 2); // i.e. "module.php" or "module"
			
			$this->moduleFileExts[$moduleName] = $ext === 'module' ? 1 : 2;
			// @todo next, remove the 'file' property from verbose module info since it is redundant
			
			$requires = array();
			$name = $moduleName;
			$moduleName = $this->loadModule($path, $pathname, $requires, $installed);
			if(!$config->paths->get($name)) $this->setConfigPaths($name, dirname($basePath . $pathname));
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
		
		if($this->debug) $this->debugTimerStop($debugKey); 
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
	protected function loadModule($basepath, $pathname, array &$requires, array &$installed) {
	
		$pathname = $basepath . $pathname;
		$dirname = dirname($pathname);
		$filename = basename($pathname);
		$basename = basename($filename, '.php');
		$basename = basename($basename, '.module');
		$requires = array();
		$duplicates = $this->duplicates();
		$moduleInfo = null;
		
		// check if module has duplicate files, where one to use has already been specified to use first
		$currentFile = $duplicates->getCurrent($basename); // returns the current file in use, if more than one
		if($currentFile) {
			// there is a duplicate file in use
			$file = rtrim($this->wire('config')->paths->root, '/') . $currentFile;
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
			$module = parent::get($basename);
			$dir = rtrim($this->wire('config')->paths->$basename, '/');
			if($module && $dir && $dirname != $dir) {
				$duplicates->recordDuplicate($basename, $pathname, "$dir/$filename", $installed);
				return '';
			}
			if($module) return $basename;
		}

		// if the filename doesn't end with .module or .module.php, then stop and move onto the next
		if(!strpos($filename, '.module') || (substr($filename, -7) !== '.module' && substr($filename, -11) !== '.module.php')) return false;
		
		//  if the filename doesn't start with the requested path, then continue
		if(strpos($pathname, $basepath) !== 0) return ''; 

		// if the file isn't there, it was probably uninstalled, so ignore it
		if(!file_exists($pathname)) return '';

		// if the module isn't installed, then stop and move on to next
		if(!array_key_exists($basename, $installed)) {
			$this->installable[$basename] = $pathname;
			return '';
		}

		$info = $installed[$basename];
		$this->setConfigPaths($basename, $dirname);
		$module = null;
		$autoload = false;

		if($info['flags'] & self::flagsAutoload) {
			
			// this is an Autoload module. 
			// include the module and instantiate it but don't init() it,
			// because it will be done by Modules::init()
			$moduleInfo = $this->getModuleInfo($basename);

			// determine if module has dependencies that are not yet met
			if(count($moduleInfo['requires'])) {
				foreach($moduleInfo['requires'] as $requiresClass) {
					$nsRequiresClass = $this->getModuleClass($requiresClass, true);
					if(!wireClassExists($nsRequiresClass, false)) {
						$requiresInfo = $this->getModuleInfo($requiresClass); 
						if(!empty($requiresInfo['error']) 
							|| $requiresInfo['autoload'] === true 
							|| !$this->isInstalled($requiresClass)) {	
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
			if(!isset($moduleInfo['autoload'])) $moduleInfo['autoload'] = true;
			/** @var bool|string|callable $autoload */
			$autoload = $moduleInfo['autoload'];
			if($autoload === 'function') {
				// function is stored by the moduleInfo cache to indicate we need to call a dynamic function specified with the module itself
				$i = $this->getModuleInfoExternal($basename); 
				if(empty($i)) {
					$this->includeModuleFile($pathname, $basename);
					$className = $moduleInfo['namespace'] . $basename;
					if(method_exists($className, 'getModuleInfo')) {
						$i = $className::getModuleInfo();
					} else {
						$i = array();
					}
				}
				$autoload = isset($i['autoload']) ? $i['autoload'] : true;
				unset($i);
			}
			// check for conditional autoload
			if(!is_bool($autoload) && (is_string($autoload) || is_callable($autoload)) && !($info['flags'] & self::flagsDisabled)) {
				// anonymous function or selector string
				$this->conditionalAutoloadModules[$basename] = $autoload;
				$this->moduleIDs[$basename] = $info['id'];
				$autoload = true;
			} else if($autoload) {
				$this->includeModuleFile($pathname, $basename);
				if(!($info['flags'] & self::flagsDisabled)) {
					$module = $this->refreshing ? parent::get($basename) : null;
					if(!$module) $module = $this->newModule($basename);
				}
			}
		}

		if(is_null($module)) {
			// placeholder for a module, which is not yet included and instantiated
			if(!$moduleInfo) $moduleInfo = $this->getModuleInfo($basename);
			$module = $this->newModulePlaceholder($basename, $moduleInfo['namespace'], $pathname, $info['flags'] & self::flagsSingular, $autoload);
		}

		$this->moduleIDs[$basename] = $info['id'];
		$this->set($basename, $module);
		
		return $basename; 
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
	protected function findModuleFiles($path, $readCache = false, $level = 0) {

		static $startPath;
		static $callNum = 0;
		static $prependFiles = array();

		$callNum++;
		$config = $this->wire('config');
		$cache = $this->wire('cache'); 
		$cacheName = '';

		if($level == 0) {
			$startPath = $path;
			$cacheName = "Modules." . str_replace($config->paths->root, '', $path);
			if($readCache && $cache) {
				$cacheContents = $cache->get($cacheName); 
				if($cacheContents !== null) {
					if(empty($cacheContents) && $callNum === 1) {
						// don't accept empty cache for first path (/wire/modules/)
					} else {
						$cacheContents = explode("\n", trim($cacheContents));
						return $cacheContents;
					}
				}
			}
		}

		$files = array();
		$autoloadOrders = null;
		
		if(count($this->autoloadOrders) && $path !== $config->paths->modules) {
			$autoloadOrders = &$this->autoloadOrders;
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
			if($cache && $cacheName) {
				$cache->save($cacheName, implode("\n", $files), WireCache::expireReserved);
			}
		}

		return $files;
	}


	/**
	 * Setup entries in config->urls and config->paths for the given module
	 *
	 * @param string $moduleName
	 * @param string $path
	 *
	 */
	protected function setConfigPaths($moduleName, $path) {
		$config = $this->wire('config'); 
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
	 * Get the requested Module 
	 *
	 * - If the module is not installed, but is installable, it will be installed, instantiated, and initialized.
	 *   If you don't want that behavior, call `$modules->isInstalled('ModuleName')` as a conditional first. 
	 * - You can also get/load a module by accessing it directly, like `$modules->ModuleName`.
	 * - To get a module with additional options, use `$modules->getModule($name, $options)` instead. 
	 * 
	 * ~~~~~
	 * // Get the MarkupAdminDataTable module
	 * $table = $modules->get('MarkupAdminDataTable'); 
	 * 
	 * // You can also do this
	 * $table = $modules->MarkupAdminDataTable;
	 * ~~~~~
	 *
	 * @param string|int $key Module name (also accepts database ID)
	 * @return Module|_Module|null Returns a Module or null if not found
	 * @throws WirePermissionException If module requires a particular permission the user does not have
	 * @see Modules::getModule(), Modules::isInstalled()
	 *
	 */
	public function get($key) {
		// If the module is a ModulePlaceholder, then it will be converted to the real module (included, instantiated, initialized).
		return $this->getModule($key);
	}

	/**
	 * Attempt to find a substitute for moduleName and return module if found or null if not
	 * 
	 * @param $moduleName
	 * @param array $options See getModule() options
	 * @return Module|null
	 * 
	 */
	protected function getSubstituteModule($moduleName, array $options = array()) {
		
		$module = null;
		$options['noSubstitute'] = true; // prevent recursion
		
		while(isset($this->substitutes[$moduleName]) && !$module) {
			$substituteName = $this->substitutes[$moduleName];
			$module = $this->getModule($substituteName, $options);
			if(!$module) $moduleName = $substituteName;
		}
		
		return $module;
	}

	/**
	 * Get the requested Module (with options)
	 * 
	 * This is the same as `$modules->get()` except that you can specify additional options to modify default behavior.
	 * These are the options you can specify in the `$options` array argument:
	 * 
	 *  - `noPermissionCheck` (bool): Specify true to disable module permission checks (and resulting exception). (default=false)
	 *  - `noInstall` (bool): Specify true to prevent a non-installed module from installing from this request. (default=false)
	 *  - `noInit` (bool): Specify true to prevent the module from being initialized. (default=false)
	 *  - `noSubstitute` (bool): Specify true to prevent inclusion of a substitute module. (default=false)
	 *  - `noCache` (bool): Specify true to prevent module instance from being cached for later getModule() calls. (default=false)
	 *  - `noThrow` (bool): Specify true to prevent exceptions from being thrown on permission or fatal error. (default=false)
	 *  - `returnError` (bool): Return an error message (string) on error, rather than null. (default=false)
	 * 
	 * If the module is not installed, but is installable, it will be installed, instantiated, and initialized.
	 * If you don't want that behavior, call `$modules->isInstalled('ModuleName')` as a condition first, OR specify 
	 * true for the `noInstall` option in the `$options` argument.
	 * 
	 * @param string|int $key Module name or database ID.
	 * @param array $options Optional settings to change load behavior, see method description for details. 
	 * @return Module|_Module|null|string Returns ready-to-use module or NULL|string if not found (string if `returnError` option used).
	 * @throws WirePermissionException|\Exception If module requires a particular permission the user does not have
	 * @see Modules::get()
	 *
	 */
	public function getModule($key, array $options = array()) {
	
		$module = null;
		$needsInit = false;
		$error = '';
		
		if(empty($key)) {
			return empty($options['returnError']) ? null : "No module specified";
		}

		// check for optional module ID and convert to classname if found
		if(ctype_digit("$key")) {
			$moduleID = (int) $key;
			if(!$key = array_search($key, $this->moduleIDs)) {
				return empty($options['returnError']) ? null : "Unable to find module ID $moduleID";
			}
		} else {
			$key = wireClassName($key, false);
		}
		
		$module = parent::get($key);
		
		if(!$module) { 
			if(empty($options['noSubstitute'])) {
				if($this->isInstallable($key) && empty($options['noInstall'])) {
					// module is on file system and may be installed, no need to substitute
				} else {
					$module = $this->getSubstituteModule($key, $options);
					if($module) return $module; // returned module is ready to use
				}
			} else {
				$error = "Module '$key' not found and substitute not allowed (noSubstitute=true)";	
			}
		}
		
		if($module) {
			// check if it's a placeholder, and if it is then include/instantiate/init the real module 
			// OR check if it's non-singular, so that a new instance is created
			if($module instanceof ModulePlaceholder || !$this->isSingular($module)) {
				$placeholder = $module;
				$class = $this->getModuleClass($placeholder);
				try {
					if($module instanceof ModulePlaceholder) $this->includeModule($module);
					$module = $this->newModule($class);
				} catch(\Exception $e) {
					if(empty($options['noThrow'])) throw $e;
					return empty($options['returnError']) ? null : "Module '$key' - " . $e->getMessage();
				}
				// if singular, save the instance so it can be used in later calls
				if($module && $this->isSingular($module) && empty($options['noCache'])) $this->set($key, $module);
				$needsInit = true;
			}

		} else if(empty($options['noInstall'])) { 
			// module was not available to get, see if we can install it
			if(array_key_exists($key, $this->getInstallable())) {
				// check if the request is for an uninstalled module 
				// if so, install it and return it 
				try {
					$module = $this->install($key);
				} catch(\Exception $e) {
					if(empty($options['noThrow'])) throw $e;
					if(!empty($options['returnError'])) return "Module '$key' install failed: " . $e->getMessage();
				}
				$needsInit = true;
				if(!$module) $error = "Module '$key' not installed and install failed";
			} else {
				$error = "Module '$key' is not present or listed as installable";
			}
		} else {
			$error = "Module '$key' is not present and not installable (noInstall=true)";
		}
		
		if(!$module) {
			if(!$error) $error = "Unable to get module '$key'";
			return empty($options['returnError']) ? null : $error;
		}
		
		if(empty($options['noPermissionCheck'])) {
			// check that user has permission required to use module
			if(!$this->hasPermission($module, $this->wire('user'), $this->wire('page'))) {
				$error = $this->_('You do not have permission to execute this module') . ' - ' . wireClassName($module);
				if(empty($options['noThrow'])) throw new WirePermissionException($error);
				return empty($options['returnError']) ? null : $error;
			}
		}

		// skip autoload modules because they have already been initialized in the load() method
		// unless they were just installed, in which case we need do init now
		if($needsInit && empty($options['noInit'])) {
			// if the module is configurable, then load it's config data
			// and set values for each before initializing the module
			try {
				if(!$this->initModule($module, array('clearSettings' => false, 'throw' => true))) {
					return empty($options['returnError']) ? null : "Module '$module' failed init";
				}
			} catch(\Exception $e) {
				if(empty($options['noThrow'])) throw $e; 
				return empty($options['returnError']) ? null : "Module '$module' throw Exception on init - " . $e->getMessage();
			}
		}
	
		return $module; 
	}

	/**
	 * Check if user has permission for given module
	 * 
	 * #pw-internal
	 * 
	 * @param string|object $moduleName Module instance or module name
	 * @param User $user Optionally specify different user to consider than current.
	 * @param Page $page Optionally specify different page to consider than current.
	 * @param bool $strict If module specifies no permission settings, assume no permission.
	 *   - Default (false) is to assume permission when module doesn't say anything about it. 
	 *   - Process modules (for instance) generally assume no permission when it isn't specifically defined 
	 *     (though this method doesn't get involved in that, leaving you to specify $strict instead). 
	 * 
	 * @return bool
	 * 
	 */
	public function hasPermission($moduleName, User $user = null, Page $page = null, $strict = false) {
		
		if(is_object($moduleName)) {
			$module = $moduleName;
			$className = $module->className(true);
			$moduleName = $module->className(false);
		} else {
			$module = null;
			// $className = wireClassName($moduleName, true);
			$className = $this->getModuleClass($moduleName, true); // ???
			$moduleName = wireClassName($moduleName, false);
		}

		$info = $this->getModuleInfo($module ? $module : $moduleName);
		if(empty($info['permission']) && empty($info['permissionMethod'])) return $strict ? false : true;
		
		if(is_null($user)) $user = $this->wire('user'); 	
		if($user && $user->isSuperuser()) return true;
		
		if(!empty($info['permission'])) {
			if(!$user->hasPermission($info['permission'])) return false;
		}
		
		if(!empty($info['permissionMethod'])) {
			// module specifies a static method to call for permission
			if(is_null($page)) $page = $this->wire('page');
			$data = array(
				'wire' => $this->wire(),
				'page' => $page, 
				'user' => $user, 
				'info' => $info, 
			);
			$method = $info['permissionMethod'];
			$this->includeModule($moduleName);
			return $className::$method($data);
		}
		
		return true; 
	}

	/**
	 * Get the requested module and reset cache + install it if necessary.
	 *
	 * This is exactly the same as get() except that this one will rebuild the modules cache if
	 * it doesn't find the module at first. If the module is on the file system, this
	 * one will return it in some instances that a regular get() can't. 
	 * 
	 * #pw-internal
	 *
	 * @param string|int $key Module className or database ID
	 * @return Module|null
	 *
	 */
	public function getInstall($key) {
		$module = $this->get($key); 
		if(!$module) {
			$this->refresh();
			$module = $this->getModule($key); 
		}
		return $module; 
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
		
		if(is_string($module)) {
			$className = $module;
		} else if(is_object($module)) {
			if($module instanceof ModulePlaceholder) {
				$className = $module->className();
			} else if($module instanceof Module) {
				return true; // already included
			}
		} else {
			$className = $this->getModuleClass($module);
		}
		
		if(!$className) return false;
		
		if(class_exists($className, false)) {
			// already included
			return true; 
		}
	
		// determine if namespace was requested with module
		$namespace = wireClassName($className, 1);
	
		// moduleName is className without namespace
		$moduleName = $namespace === null ? $className : wireClassName($className, false);
		
		// attempt to retrieve module
		$module = parent::get($moduleName);
		
		if($module) {
			// module found, check to make sure it actually points to a module	
			if(!$module instanceof Module) $module = false;

		} else if($moduleName) {
			// unable to retrieve module, may be an uninstalled module
			if(!$file) {
				$file = $this->getModuleFile($moduleName, array('fast' => true));
				if(!$file) $file = $this->getModuleFile($moduleName, array('fast' => false));
			}
			if($file) {
				$this->includeModuleFile($file, $moduleName);
				// now check to see if included file resulted in presence of module class
				if(class_exists($className)) {
					$module = true; 
				} else {
					if(!$namespace) $namespace = $this->getModuleNamespace($moduleName, array('file' => $file));
					$nsClassName = trim($namespace, "\\") . "\\$moduleName";
					if(class_exists($nsClassName, false)) {
						// successful include module
						$module = true;
					}
				}
			}
		}
		
		if($module === true) {
			// great
			return true; 
			
		} else if(!$module) {
			return false;
			
		} else if($module instanceof ModulePlaceholder) {
			$this->includeModuleFile($module->file, $moduleName);
			return true; 
			
		} else if($module instanceof Module) {
			// it's already been included, since we have a real module
			return true; 
			
		} else {
			return false;
		}
	}

	/**
	 * Include the given filename 
	 * 
	 * @param string $file
	 * @param string $moduleName
	 * 
	 */
	protected function includeModuleFile($file, $moduleName) {
		
		$wire1 = ProcessWire::getCurrentInstance();
		$wire2 = $this->wire();
		
		// check if there is more than one PW instance active
		if($wire1 !== $wire2) {
			// multi-instance is active, don't autoload module if class already exists
			// first do a fast check, which should catch any core modules 
			if(class_exists(__NAMESPACE__ . "\\$moduleName", false)) return;
			// next do a slower check, figuring out namespace
			$ns = $this->getModuleNamespace($moduleName, array('file' => $file));
			$className = trim($ns, "\\") . "\\$moduleName";
			if(class_exists($className, false)) return;
			// if this point is reached, module is not yet in memory in either instance
			// temporarily set the $wire instance to 2nd instance during include()
			ProcessWire::setCurrentInstance($wire2);
		}

		// get compiled version (if it needs compilation)
		$file = $this->compile($moduleName, $file);

		if($file) {
			/** @noinspection PhpIncludeInspection */
			include_once($file);
		}
	
		// set instance back, if multi-instance
		if($wire1 !== $wire2) ProcessWire::setCurrentInstance($wire1);
	}

	/**
	 * Find modules based on a selector string 
	 * 
	 * #pw-internal Almost always recommend using findByPrefix() instead
	 * 
	 * @param string $selector Selector string
	 * @return WireArray of found modules, instantiated and ready-to-use
	 *	
	 */
	public function find($selector) {
		// ensures any ModulePlaceholders are loaded in the returned result.
		$a = parent::find($selector); 
		if($a) {
			foreach($a as $key => $value) {
				$a[$key] = $this->get($value->className()); 
			}
		}
		return $a; 
	}

	/**
	 * Find modules matching the given prefix (i.e. “Inputfield”)
	 * 
	 * By default this method returns module class names matching the given prefix. 
	 * To instead retrieve instantiated (ready-to-use) modules, specify boolean true
	 * for the second argument. 
	 * 
	 * ~~~~~
	 * // Retrieve array of all Textformatter module names
	 * $items = $modules->findByPrefix('Textformatter'); 
	 * 
	 * // Retrieve array of all Textformatter modules (ready to use)
	 * $items = $modules->findByPrefix('Textformatter', true); 
	 * ~~~~~
	 * 
	 * @param string $prefix Specify prefix, i.e. "Process", "Fieldtype", "Inputfield", etc.
	 * @param bool|int $load Specify one of the following:
	 *  - Boolean true to return array of instantiated modules.
	 *  - Boolean false to return array of module names (default).
	 *  - Integer 1 to return array of module info for each matching module.
	 *  - Integer 2 to return array of verbose module info for each matching module. 
	 * @return array Returns array of module class names or Module objects. In either case, array indexes are class names.
	 * 
	 */
	public function findByPrefix($prefix, $load = false) {
		$results = array();
		foreach($this as $key => $value) {
			$className = wireClassName($value->className(), false);
			if(strpos($className, $prefix) !== 0) continue;
			if($load === 1) {
				$results[$className] = $this->getModuleInfo($className); 
			} else if($load === 2) {
				$results[$className] = $this->getModuleInfoVerbose($className); 
			} else if($load === true) {
				$results[$className] = $this->getModule($className);
			} else {
				$results[$className] = $className;
			}
		}
		return $results;
	}

	/**
	 * Find modules by matching a property or properties in their module info 
	 * 
	 * @param string|array $selector Specify one of the following: 
	 *  - Selector string to match module info. 
	 *  - Array of [ 'property' => 'value' ] to match in module info (this is not a selector array). 
	 *  - Name of property that will match module if not empty in module info. 
	 * @param bool|int $load Specify one of the following: 
	 *  - Boolean true to return array of instantiated modules.
	 *  - Boolean false to return array of module names (default).
	 *  - Integer 1 to return array of module info for each matching module.
	 *  - Integer 2 to return array of verbose module info for each matching module. 
	 * @return array Array of modules, module names or module info arrays, indexed by module name.
	 * 
	 */
	public function findByInfo($selector, $load = false) {
		
		$selectors = null;
		$infos = null;
		$keys = null;
		$results = array();
		$verbose = $load === 2;
		$properties = array();
		$has = '';
		
		if(is_array($selector)) {
			// find matching all values in array
			$keys = $selector;
			$properties = array_keys($keys);
		} if(!ctype_alnum($selector) && Selectors::stringHasOperator($selector)) {
			// find by selectors
			$selectors = new Selectors($selector);
			if(!$verbose) foreach($selectors as $s) {
				$properties = array_merge($properties, $s->fields()); 
			}
		} else {
			// find non-empty property
			$has = $selector;
			$properties[] = $has;
		}
	
		// check if any verbose properties are part of the find
		if(!$verbose) foreach($properties as $property) {
			if(!in_array($property, $this->moduleInfoVerboseKeys)) continue;
			$verbose = true;
			break;
		}
		
		$moduleInfoOptions = array(
			'verbose' => $verbose,
			'minify' => false
		);
		
		foreach($this->getModuleInfo('*', $moduleInfoOptions) as $info) {
			$isMatch = false;
			
			if($has) {
				// simply check if property is non-empty
				if(!empty($info[$has])) $isMatch = true;
				
			} else if($selectors) {
				// match selector
				$total = 0;
				$n = 0;
				foreach($selectors as $selector) {
					$total++;
					$values = array();
					foreach($selector->fields() as $property) {
						if(isset($info[$property])) $values[] = $info[$property];
					}
					if($selector->matches($values)) $n++;
				}
				if($n && $n === $total) $isMatch = true;
				
			} else if($keys) {
				// match all values in $keys array
				$n = 0;
				foreach($keys as $key => $value) {
					if($value === '*') {
						// match any non-empty value
						if(!empty($info[$key])) $n++;
					} else {
						// match exact value
						if(isset($info[$key]) && $value == $info[$key]) $n++;
					}
				}
				if($n && $n === count($keys)) $isMatch = true;
			}
			
			if($isMatch) {
				$moduleName = $info['name'];
				if(is_int($load)) {
					$results[$moduleName] = $info;
				} else if($load === true) {
					$results[$moduleName] = $this->getModule($moduleName);
				} else {
					$results[$moduleName] = $moduleName;
				}
			}
		}
		
		return $results;
	}

	/**
	 * Get an associative array [name => path] for all modules that aren’t currently installed.
	 * 
	 * #pw-internal
	 *
	 * @return array Array of elements with $moduleName => $pathName
	 *
	 */
	public function getInstallable() {
		return $this->installable; 
	}

	/**
	 * Is the given module name installed?
	 *
	 * @param string $class Just a module class name, or optionally: `ModuleClassName>=1.2.3` (operator and version)
	 * @return bool True if installed, false if not
	 *
	 */
	public function isInstalled($class) {

		if(is_object($class)) $class = $this->getModuleClass($class);

		$operator = null;
		$requiredVersion = null;
		$currentVersion = null;
		
		if(!ctype_alnum($class)) {
			// class has something other than just a classname, likely operator + version
			if(preg_match('/^([a-zA-Z0-9_]+)\s*([<>=!]+)\s*([\d.]+)$/', $class, $matches)) {
				$class = $matches[1];
				$operator = $matches[2];
				$requiredVersion = $matches[3];
			}
		}
		
		if($class === 'PHP' || $class === 'ProcessWire') {
			$installed = true; 
			if(!is_null($requiredVersion)) {
				$currentVersion = $class === 'PHP' ? PHP_VERSION : $this->wire('config')->version; 
			}
		} else {
			$installed = parent::get($class) !== null;
			if($installed && !is_null($requiredVersion)) {
				$info = $this->getModuleInfo($class); 
				$currentVersion = $info['version'];
			}
		}
	
		if($installed && !is_null($currentVersion)) {
			$installed = $this->versionCompare($currentVersion, $requiredVersion, $operator); 
		}
	
		return $installed;
	}


	/**
	 * Is the given module name installable? (i.e. not already installed)
	 * 
	 * #pw-internal
	 *
	 * @param string $class Module class name
	 * @param bool $now Is module installable RIGHT NOW? This makes it check that all dependencies are already fulfilled (default=false)
	 * @return bool True if module is installable, false if not
 	 *
	 */
	public function isInstallable($class, $now = false) {
		$installable = array_key_exists($class, $this->installable); 
		if(!$installable) return false;
		if($now) {
			$requires = $this->getRequiresForInstall($class); 
			if(count($requires)) return false;
		}
		return $installable;
	}
	
	/**
	 * Install the given module name
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @param array|bool $options Optional associative array that can contain any of the following:
	 *  - `dependencies` (boolean): When true, dependencies will also be installed where possible. Specify false to prevent installation of uninstalled modules. (default=true)
	 *  - `resetCache` (boolean): When true, module caches will be reset after installation. (default=true)
	 *  - `force` (boolean): Force installation, even if dependencies can't be met. 
	 * @return null|Module Returns null if unable to install, or ready-to-use Module object if successfully installed. 
	 * @throws WireException
	 *
	 */
	public function ___install($class, $options = array()) {
		
		$defaults = array(
			'dependencies' => true, 
			'resetCache' => true, 
			'force' => false, 
			);
		if(is_bool($options)) { 
			// dependencies argument allowed instead of $options, for backwards compatibility
			$dependencies = $options; 
			$options = array('dependencies' => $dependencies);
		} 
		$options = array_merge($defaults, $options); 
		$dependencyOptions = $options; 
		$dependencyOptions['resetCache'] = false; 

		if(!$this->isInstallable($class)) return null; 

		$requires = $this->getRequiresForInstall($class); 
		if(count($requires)) {
			$error = '';
			$installable = false; 
			if($options['dependencies']) {
				$installable = true; 
				foreach($requires as $requiresModule) {
					if(!$this->isInstallable($requiresModule)) $installable = false;
				}
				if($installable) {
					foreach($requires as $requiresModule) {
						if(!$this->install($requiresModule, $dependencyOptions)) {
							$error = $this->_('Unable to install required module') . " - $requiresModule. "; 
							$installable = false;
							break;
						}
					}
				}
			}
			if(!$installable) {
				$error = sprintf($this->_('Module %s requires: %s'), $class, implode(', ', $requires)) . ' ' . $error;
				if($options['force']) {
					$this->warning($this->_('Warning!') . ' ' . $error);
				} else {
					throw new WireException($error);
				}
			}
		}
		
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();

		$pathname = $this->installable[$class];
		$this->includeModuleFile($pathname, $class);
		$this->setConfigPaths($class, dirname($pathname)); 

		$module = $this->newModule($class);
		if(!$module) return null;
		$flags = 0;
		$database = $this->wire('database');
		$moduleID = 0;
		
		if($this->isSingular($module)) $flags = $flags | self::flagsSingular; 
		if($this->isAutoload($module)) $flags = $flags | self::flagsAutoload; 

		$sql = "INSERT INTO modules SET class=:class, flags=:flags, data=''";
		if($this->wire('config')->systemVersion >=7) $sql .= ", created=NOW()";
		$query = $database->prepare($sql, "modules.install($class)"); 
		$query->bindValue(":class", $class, \PDO::PARAM_STR); 
		$query->bindValue(":flags", $flags, \PDO::PARAM_INT); 
		
		try {
			if($query->execute()) $moduleID = (int) $database->lastInsertId();
		} catch(\Exception $e) {
			if($languages) $languages->unsetDefault();
			$this->trackException($e, false, true); 
			return null;
		}
		
		$this->moduleIDs[$class] = $moduleID;

		$this->add($module); 
		unset($this->installable[$class]);
		
		// note: the module's install is called here because it may need to know it's module ID for installation of permissions, etc. 
		if(method_exists($module, '___install') || method_exists($module, 'install')) {
			try {
				/** @var _Module $module */
				$module->install();
				
			} catch(\PDOException $e) {
				$error = $this->_('Module reported error during install') . " ($class): " . $e->getMessage();
				$this->error($error);
				$this->trackException($e, false, $error);

			} catch(\Exception $e) {
				// remove the module from the modules table if the install failed
				$moduleID = (int) $moduleID;
				$error = $this->_('Unable to install module') .  " ($class): " . $e->getMessage();
				$ee = null;
				try {
					$query = $database->prepare('DELETE FROM modules WHERE id=:id LIMIT 1'); // QA
					$query->bindValue(":id", $moduleID, \PDO::PARAM_INT);
					$query->execute();
				} catch(\Exception $ee) {
					$this->trackException($e, false, $error)->trackException($ee, true);
				}
				if($languages) $languages->unsetDefault();
				if(is_null($ee)) $this->trackException($e, false, $error);
				return null;
			}
		}

		$info = $this->getModuleInfoVerbose($class, array('noCache' => true)); 
	
		// if this module has custom permissions defined in its getModuleInfo()['permissions'] array, install them 
		foreach($info['permissions'] as $name => $title) {
			$name = $this->wire('sanitizer')->pageName($name); 
			if(ctype_digit("$name") || empty($name)) continue; // permission name not valid
			$permission = $this->wire('permissions')->get($name); 
			if($permission->id) continue; // permision already there
			try {
				$permission = $this->wire('permissions')->add($name); 
				$permission->title = $title; 
				$this->wire('permissions')->save($permission);
				if($languages) $languages->unsetDefault(); 
				$this->message(sprintf($this->_('Added Permission: %s'), $permission->name)); 
			} catch(\Exception $e) {
				if($languages) $languages->unsetDefault(); 
				$error = sprintf($this->_('Error adding permission: %s'), $name);
				$this->trackException($e, false, $error); 
			}
		}

		// check if there are any modules in 'installs' that this module didn't handle installation of, and install them
		$label = $this->_('Module Auto Install');
		
		foreach($info['installs'] as $name) {
			if(!$this->isInstalled($name)) {
				try { 
					$this->install($name, $dependencyOptions); 
					$this->message("$label: $name"); 
				} catch(\Exception $e) {
					$error = "$label: $name - " . $e->getMessage();
					$this->trackException($e, false, $error); 
				}
			}
		}

		$this->log("Installed module '$module'"); 
		if($languages) $languages->unsetDefault();
		if($options['resetCache']) $this->clearModuleInfoCache();

		return $module; 
	}

	/**
	 * Returns whether the module can be uninstalled
	 * 
	 * #pw-internal
	 *
	 * @param string|Module $class
	 * @param bool $returnReason If true, the reason why it can't be uninstalled with be returned rather than boolean false.
	 * @return bool|string 
	 *
	 */
	public function isUninstallable($class, $returnReason = false) {

		$reason = '';
		$reason1 = $this->_("Module is not already installed");
		$namespace = $this->getModuleNamespace($class);
		$class = $this->getModuleClass($class); 

		if(!$this->isInstalled($class)) {
			$reason = $reason1 . ' (a)';

		} else {
			$this->includeModule($class); 
			if(!wireClassExists($namespace . $class, false)) {
				$reason = $reason1 . " (b: $namespace$class)";
			}
		}

		if(!$reason) { 
			// if the moduleInfo contains a non-empty 'permanent' property, then it's not uninstallable
			$info = $this->getModuleInfo($class); 
			if(!empty($info['permanent'])) {
				$reason = $this->_("Module is permanent"); 
			} else {
				$dependents = $this->getRequiresForUninstall($class); 	
				if(count($dependents)) $reason = $this->_("Module is required by other modules that must be removed first"); 
			}

			if(!$reason && in_array('Fieldtype', wireClassParents($namespace . $class))) {
				foreach($this->wire('fields') as $field) {
					$fieldtype = wireClassName($field->type, false);
					if($fieldtype == $class) { 
						$reason = $this->_("This module is a Fieldtype currently in use by one or more fields");
						break;
					}
				}
			}
		}
		
		if($returnReason && $reason) return $reason;
	
		return $reason ? false : true; 	
	}

	/**
	 * Returns whether the module can be deleted (have it's files physically removed)
	 * 
	 * #pw-internal
	 *
	 * @param string|Module $class
	 * @param bool $returnReason If true, the reason why it can't be removed will be returned rather than boolean false.
	 * @return bool|string 
	 *
	 */
	public function isDeleteable($class, $returnReason = false) {

		$reason = '';
		$class = $this->getModuleClass($class); 

		$filename = isset($this->installable[$class]) ? $this->installable[$class] : null;
		$dirname = dirname($filename); 

		if(empty($filename) || $this->isInstalled($class)) {
			$reason = "Module must be uninstalled before it can be deleted.";

		} else if(is_link($filename) || is_link($dirname) || is_link(dirname($dirname))) {
			$reason = "Module is linked to another location";

		} else if(!is_file($filename)) {
			$reason = "Module file does not exist";

		} else if(strpos($filename, $this->paths[0]) === 0) {
			$reason = "Core modules may not be deleted.";

		} else if(!is_writable($filename)) {
			$reason = "We have no write access to the module file, it must be removed manually.";
		}

		if($returnReason && $reason) return $reason;
	
		return $reason ? false : true; 	
	}

	/**
	 * Delete the given module, physically removing its files
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @return bool|int
	 * @throws WireException If module can't be deleted, exception will be thrown containing reason. 
	 *
	 */
	public function ___delete($class) {

		$class = $this->getModuleClass($class); 
		$success = false;
		$reason = $this->isDeleteable($class, true); 
		if($reason !== true) throw new WireException($reason); 
		$siteModulesPath = $this->wire('config')->paths->siteModules;

		$filename = $this->installable[$class];
		$basename = basename($filename); 

		// double check that $class is consistent with the actual $basename	
		if($basename === "$class.module" || $basename === "$class.module.php") {
			// good, this is consistent with the format we require
		} else {
			throw new WireException("Unrecognized module filename format"); 
		}

		// now determine if module is the owner of the directory it exists in
		// this is the case if the module class name is the same as the directory name

		$path = dirname($filename); // full path to directory, i.e. .../site/modules/ProcessHello
		$name = basename($path); // just name of directory that module is, i.e. ProcessHello
		$parentPath = dirname($path); // full path to parent directory, i.e. ../site/modules
		$backupPath = $parentPath . "/.$name"; // backup path, in case module is backed up

		// first check that we are still in the /site/modules/ (or another non core modules path)
		$inPath = false; // is module somewhere beneath /site/modules/ ?
		$inRoot = false; // is module in /site/modules/ root? i.e. /site/modules/ModuleName.module
		
		foreach($this->paths as $key => $modulesPath) {
			if($key === 0) continue; // skip core modules path
			if(strpos("$parentPath/", $modulesPath) === 0) $inPath = true; 
			if($modulesPath === $path) $inRoot = true; 
		}

		$basename = basename($basename, '.php');
		$basename = basename($basename, '.module'); 
		
		$files = array(
			"$basename.module",
			"$basename.module.php",
			"$basename.info.php",
			"$basename.info.json",
			"$basename.config.php", 
			"{$basename}Config.php",
			);
		
		if($inPath) { 
			// module is in /site/modules/[ModuleName]/
			
			$numOtherModules = 0; // num modules in dir other than this one
			$numLinks = 0; // number of symbolic links
			$dirs = array("$path/"); 
			
			do {
				$dir = array_shift($dirs); 
				$this->message("Scanning: $dir", Notice::debug); 
				
				foreach(new \DirectoryIterator($dir) as $file) {
					if($file->isDot()) continue;
					if($file->isLink()) {
						$numLinks++;
						continue; 
					}
					if($file->isDir()) {
						$dirs[] = $file->getPathname();
						continue; 
					}
					if(in_array($file->getBasename(), $files)) continue; // skip known files
					if(strpos($file->getBasename(), '.module') && preg_match('{(\.module|\.module\.php)$}', $file->getBasename())) {
						// another module exists in this dir, so we don't want to delete that
						$numOtherModules++;
					}
					if(preg_match('{^(' . $basename . '\.[-_.a-zA-Z0-9]+)$}', $file->getBasename(), $matches)) {
						// keep track of potentially related files in case we have to delete them individually
						$files[] = $matches[1]; 
					}
				}
			} while(count($dirs)); 
			
			if(!$inRoot && !$numOtherModules && !$numLinks) {
				// the modulePath had no other modules or directories in it, so we can delete it entirely
				$success = wireRmdir($path, true); 
				if($success) {
					$this->message("Removed directory: $path", Notice::debug);
					if(is_dir($backupPath)) {
						if(wireRmdir($backupPath, true)) $this->message("Removed directory: $backupPath", Notice::debug); 
					}
					$files = array();
				} else {
					$this->error("Failed to remove directory: $path", Notice::debug); 
				}
			}
		}

		// remove module files individually 
		foreach($files as $file) {
			$file = "$path/$file";
			if(!file_exists($file)) continue;
			if($this->wire('files')->unlink($file, $siteModulesPath)) {
				$this->message("Removed file: $file", Notice::debug);
			} else {
				$this->error("Unable to remove file: $file", Notice::debug);
			}
		}
		
		if($success) $this->log("Deleted module '$class'"); 
			else $this->error("Failed to delete module '$class'"); 
		
		return $success; 
	}


	/**
	 * Uninstall the given module name
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function ___uninstall($class) {

		$class = $this->getModuleClass($class); 
		$reason = $this->isUninstallable($class, true); 
		if($reason !== true) {
			// throw new WireException("$class - Can't Uninstall - $reason"); 
			return false;
		}
		
		// check if there are any modules still installed that this one says it is responsible for installing
		foreach($this->getUninstalls($class) as $name) {

			// catch uninstall exceptions at this point since original module has already been uninstalled
			$label = $this->_('Module Auto Uninstall');
			try {
				$this->uninstall($name);
				$this->message("$label: $name");

			} catch(\Exception $e) {
				$error = "$label: $name - " . $e->getMessage();
				$this->trackException($e, false, $error);
			}
		}

		$info = $this->getModuleInfoVerbose($class); 
		$module = $this->getModule($class, array(
			'noPermissionCheck' => true, 
			'noInstall' => true,
			// 'noInit' => true
		)); 
		if(!$module) return false;
		
		// remove all hooks attached to this module
		$hooks = $module instanceof Wire ? $module->getHooks() : array();
		foreach($hooks as $hook) {
			if($hook['method'] == 'uninstall') continue;
			$this->message("Removed hook $class => " . $hook['options']['fromClass'] . " $hook[method]", Notice::debug);
			$module->removeHook($hook['id']);
		}

		// remove all hooks attached to other ProcessWire objects
		$hooks = array_merge($this->getHooks('*'), $this->wire('hooks')->getAllLocalHooks());
		foreach($hooks as $hook) {
			/** @var Wire $toObject */
			$toObject = $hook['toObject'];
			$toClass = wireClassName($toObject, false);
			$toMethod = $hook['toMethod'];
			if($class === $toClass && $toMethod != 'uninstall') {
				$toObject->removeHook($hook['id']);
				$this->message("Removed hook $class => " . $hook['options']['fromClass'] . " $hook[method]", Notice::debug);
			}
		}
		
		if(method_exists($module, '___uninstall') || method_exists($module, 'uninstall')) {
			// note module's uninstall method may throw an exception to abort the uninstall
			/** @var _Module $module */
			$module->uninstall();
		}
		$database = $this->wire('database'); 
		$query = $database->prepare('DELETE FROM modules WHERE class=:class LIMIT 1'); // QA
		$query->bindValue(":class", $class, \PDO::PARAM_STR); 
		$query->execute();
	
		// add back to the installable list
		if(class_exists("ReflectionClass")) {
			$reflector = new \ReflectionClass($this->getModuleClass($module, true));
			$this->installable[$class] = $reflector->getFileName(); 
		}

		unset($this->moduleIDs[$class]);
		$this->remove($module);
	
		// delete permissions installed by this module
		if(isset($info['permissions']) && is_array($info['permissions'])) {
			foreach($info['permissions'] as $name => $title) {
				$name = $this->wire('sanitizer')->pageName($name); 
				if(ctype_digit("$name") || empty($name)) continue; 
				$permission = $this->wire('permissions')->get($name); 
				if(!$permission->id) continue; 
				try { 
					$this->wire('permissions')->delete($permission); 
					$this->message(sprintf($this->_('Deleted Permission: %s'), $name)); 
				} catch(\Exception $e) {
					$error = sprintf($this->_('Error deleting permission: %s'), $name);
					$this->trackException($e, false, $error);
				}
			}
		}

		$this->log("Uninstalled module '$class'"); 
		$this->refresh();

		return true; 
	}

	/**
	 * Get flags for the given module
	 * 
	 * #pw-internal
	 * 
	 * @param int|string|Module $class Module to add flag to
	 * @return int|false Returns integer flags on success, or boolean false on fail
	 * 
	 */
	public function getFlags($class) {
		$id = ctype_digit("$class") ? (int) $class : $this->getModuleID($class);
		if(isset($this->moduleFlags[$id])) return $this->moduleFlags[$id]; 
		if(!$id) return false;
		$query = $this->wire('database')->prepare('SELECT flags FROM modules WHERE id=:id');
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		$query->execute();
		if(!$query->rowCount()) return false;
		list($flags) = $query->fetch(\PDO::FETCH_NUM);
		$flags = (int) $flags; 
		$this->moduleFlags[$id] = $flags;
		return $flags; 
	}

	/**
	 * Set module flags
	 * 
	 * #pw-internal
	 * 
	 * @param $class
	 * @param $flags
	 * @return bool
	 * 
	 */
	public function setFlags($class, $flags) {
		$flags = (int) $flags; 
		$id = ctype_digit("$class") ? (int) $class : $this->getModuleID($class);
		if(!$id) return false;
		if($this->moduleFlags[$id] === $flags) return true; 
		$query = $this->wire('database')->prepare('UPDATE modules SET flags=:flags WHERE id=:id');
		$query->bindValue(':flags', $flags);
		$query->bindValue(':id', $id);
		if($this->debug) $this->message("setFlags(" . $this->getModuleClass($class) . ", " . $this->moduleFlags[$id] . " => $flags)");
		$this->moduleFlags[$id] = $flags;
		return $query->execute();
	}

	/**
	 * Add or remove a flag from a module
	 * 
	 * #pw-internal
	 * 
	 * @param $class int|string|Module $class Module to add flag to
	 * @param $flag int Flag to add (see flags* constants)
	 * @param $add bool $add Specify true to add the flag or false to remove it
	 * @return bool True on success, false on fail
	 * 
	 */
	public function setFlag($class, $flag, $add = true) {
		$id = ctype_digit("$class") ? (int) $class : $this->getModuleID($class);
		if(!$id) return false;
		$flag = (int) $flag; 
		if(!$flag) return false;
		$flags = $this->getFlags($id); 
		if($add) {
			if($flags & $flag) return true; // already has the flag
			$flags = $flags | $flag;
		} else {
			if(!($flags & $flag)) return true; // doesn't already have the flag
			$flags = $flags & ~$flag;
		}
		$this->setFlags($id, $flags); 
		return true; 	
	}

	/**
	 * Return an array of other module class names that are uninstalled when the given one is
	 * 
	 * #pw-internal
	 * 
	 * The opposite of this function is found in the getModuleInfo array property 'installs'. 
	 * Note that 'installs' and uninstalls may be different, as only modules in the 'installs' list
	 * that indicate 'requires' for the installer module will be uninstalled.
	 * 
	 * @param $class
	 * @return array
	 * 
	 */
	public function getUninstalls($class) {
		
		$uninstalls = array();
		$class = $this->getModuleClass($class);
		if(!$class) return $uninstalls;
		$info = $this->getModuleInfoVerbose($class);
		
		// check if there are any modules still installed that this one says it is responsible for installing
		foreach($info['installs'] as $name) {

			// if module isn't installed, then great
			if(!$this->isInstalled($name)) continue;

			// if an 'installs' module doesn't indicate that it requires this one, then leave it installed
			$i = $this->getModuleInfo($name);
			if(!in_array($class, $i['requires'])) continue; 
			
			// add it to the uninstalls array
			$uninstalls[] = $name;
		}
		
		return $uninstalls;
	}

	/**
	 * Returns the database ID of a given module class, or 0 if not found
	 * 
	 * #pw-internal
	 *
	 * @param string|Module $class Module or module name
	 * @return int
	 *
	 */
	public function getModuleID($class) {
		
		$id = 0;

		if(is_object($class)) {
			if($class instanceof Module) {
				$class = $this->getModuleClass($class); 
			} else {
				// Class is not a module
				return $id; 
			}
		}

		if(isset($this->moduleIDs[$class])) {
			$id = (int) $this->moduleIDs[$class];
			
		} else foreach($this->moduleInfoCache as $key => $info) {	
			if($info['name'] == $class) {
				$id = (int) $key;
				break;
			}
		}
		
		return $id; 
	}

	/**
	 * Returns the module's class name. 
	 *
	 * - Given a numeric database ID, returns the associated module class name or false if it doesn't exist
	 * - Given a Module or ModulePlaceholder instance, returns the Module's class name. 
	 *  
	 * If the module has a className() method then it uses that rather than PHP's get_class().
	 * This is important because of placeholder modules. For example, get_class would return 
	 * 'ModulePlaceholder' rather than the correct className for a Module.
	 * 
	 * #pw-internal
	 *
	 * @param string|int|Module
	 * @param bool $withNamespace Specify true to include the namespace in the class
	 * @return string|bool The Module's class name or false if not found. 
	 *	Note that 'false' is only possible if you give this method a non-Module, or an integer ID 
	 * 	that doesn't correspond to a module ID. 
	 *
	 */
	public function getModuleClass($module, $withNamespace = false) {
		
		$className = '';
		$namespace = '';

		if($module instanceof Module) {
			if(wireMethodExists($module, 'className')) {
				if($withNamespace) return $module->className(true);
				return $module->className();
			} else {
				return wireClassName($module, $withNamespace);
			}

		} else if(is_int($module) || ctype_digit("$module")) {
			$className = array_search((int) $module, $this->moduleIDs); 

		} else if(is_string($module)) {
			
			if(strpos($module, "\\") !== false) {
				$namespace = wireClassName($module, 1);
				$className = wireClassName($module, false);
			}

			// remove extensions if they were included in the module name
			if(strpos($module, '.') !== false) {
				$module = basename(basename($module, '.php'), '.module');
			}
			
			if(array_key_exists($module, $this->moduleIDs)) {
				$className = $module;
			} else if(array_key_exists($module, $this->installable)) {
				$className = $module;
			}
		}
		
		if($className) {
			if($withNamespace) {
				if($namespace) {
					$className = "$namespace\\$className";
				} else {
					$className = $this->getModuleNamespace($className) . $className;
				}
			}
			return $className;
		} 

		return false; 
	}

	/**
	 * Retrieve module info from ModuleName.info.json or ModuleName.info.php
	 * 
	 * @param string $moduleName
	 * @return array
	 * 
	 */
	protected function getModuleInfoExternal($moduleName) {
		// if($this->debug) $this->message("getModuleInfoExternal($moduleName)"); 
		
		// ...attempt to load info by info file (Module.info.php or Module.info.json)
		if(!empty($this->installable[$moduleName])) {
			$path = dirname($this->installable[$moduleName]) . '/';
		} else {
			$path = $this->wire('config')->paths->$moduleName;
		}
		
		if(empty($path)) return array();

		// module exists and has a dedicated path on the file system
		// we will try to get info from a PHP or JSON info file
		$filePHP = $path . "$moduleName.info.php";
		$fileJSON = $path . "$moduleName.info.json";

		$info = array();
		if(file_exists($filePHP)) {
			/** @noinspection PhpIncludeInspection */
			include($filePHP); // will populate $info automatically
			if(!is_array($info) || !count($info)) $this->error("Invalid PHP module info file for $moduleName"); 
			
		} else if(file_exists($fileJSON)) {
			$info = file_get_contents($fileJSON);
			$info = json_decode($info, true);
			if(!$info) {
				$info = array();
				$this->error("Invalid JSON module info file for $moduleName");
			}
		} 
		
		return $info; 
	}

	/**
	 * Retrieve module info from internal getModuleInfo function in the class
	 * 
	 * @param Module|string $module
	 * @param string $namespace
	 * @return array
	 * 
	 */
	protected function getModuleInfoInternal($module, $namespace = '') {
		// if($this->debug) $this->message("getModuleInfoInternal($module)"); 
		
		$info = array();
		
		if($module instanceof ModulePlaceholder) {
			$this->includeModule($module); 
			$module = $module->className();
		}
		
		if($module instanceof Module) {
			if(method_exists($module, 'getModuleInfo')) {
				$info = $module::getModuleInfo(); 
			}
			
		} else if($module) {
			if(empty($namespace)) $namespace = $this->getModuleNamespace($module);
			$className = wireClassName($namespace . $module, true); 
			if(!class_exists($className)) $this->includeModule($module);
			if(is_callable("$className::getModuleInfo")) {
				$info = call_user_func(array($className, 'getModuleInfo'));
			}
		}
		
		return $info; 
	}
	
	/**
	 * Pull module info directly from the module file's getModuleInfo without letting PHP parse it
	 * 
	 * Useful for getting module info from modules that extend another module not already on the file system.
	 *
	 * @param $className
	 * @return array Only includes module info specified in the module file itself.
	 *
	protected function getModuleInfoInternalSafe($className) {
		// future addition
		// load file, preg_split by /^\s*(public|private|protected)[^;{]+function\s*([^)]*)[^{]*{/
		// isolate the one that starts has getModuleInfo in matches[1]
		// parse data from matches[2]
		return array();
	}
	 */

	/**
	 * Retrieve module info for system properties: PHP or ProcessWire
	 * 
	 * @param $moduleName
	 * @return array
	 * 
	 */
	protected function getModuleInfoSystem($moduleName) {

		$info = array();
		if($moduleName === 'PHP') {
			$info['id'] = 0; 
			$info['name'] = $moduleName;
			$info['title'] = $moduleName;
			$info['version'] = PHP_VERSION;
			return $info;

		} else if($moduleName === 'ProcessWire') {
			$info['id'] = 0; 
			$info['name'] = $moduleName;
			$info['title'] = $moduleName;
			$info['version'] = $this->wire('config')->version;
			$info['namespace'] = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\" : "";
			$info['requiresVersions'] = array(
				'PHP' => array('>=', '5.3.8'),
				'PHP_modules' => array('=', 'PDO,mysqli'),
				'Apache_modules' => array('=', 'mod_rewrite'),
				'MySQL' => array('>=', '5.0.15'),
			);
			$info['requires'] = array_keys($info['requiresVersions']);
		} else {
			return array();
		}
		
		$info['versionStr'] = $info['version'];
		
		return $info;

	}

	/**
	 * Returns an associative array of information for a Module
	 * 
	 * The array returned by this method includes the following: 
	 * 
	 *  - `id` (int): module database ID.
	 *  - `name` (string): module class name.
	 *  - `title` (string): module title.
	 *  - `version` (int): module version.
	 *  - `icon` (string): Optional icon name (excluding the "fa-") part.
	 *  - `requires` (array): module names required by this module.
	 *  - `requiresVersions` (array): required module versions–module name is key, value is array($operator, $version).
	 *  - `installs` (array): module names that this module installs.
	 *  - `permission` (string): permission name required to execute this module.
	 *  - `autoload` (bool): true if module is autoload, false if not.
	 *  - `singular` (bool): true if module is singular, false if not.
	 *  - `created` (int): unix-timestamp of date/time module added to system (for uninstalled modules, it is the file date).
	 *  - `installed` (bool): is the module currently installed? (boolean, or null when not determined)
	 *  - `configurable` (bool|int): true or positive number when the module is configurable.
	 *  - `namespace` (string): PHP namespace that module lives in.
	 *
	 * The following properties are also included when "verbose" mode is requested. When not in verbose mode, these 
	 * properties are present but blank:
	 *
	 *  - `versionStr` (string): formatted module version string.
	 *  - `file` (string): module filename from PW installation root, or false when it can't be found.
	 *  - `core` (bool): true when module is a core module, false when not.
	 *  - `author` (string): module author, when specified.
	 *  - `summary` (string): summary of what this module does.
	 *  - `href` (string): URL to module details (when specified).
	 *  - `permissions` (array): permissions installed by this module, associative array ('permission-name' => 'Description').
	 *  - `page` (array): definition of page to create for Process module (see Process class)
	 *
	 * The following properties appear only for "Process" modules, and only if specified by module. 
	 * See the Process class for more details:
	 *
	 *  - `nav` (array): navigation definition
	 *  - `useNavJSON` (bool): whether the Process module provides JSON navigation
	 *  - `permissionMethod` (string|callable): method to call to determine permission
	 *  - `page` (array): definition of page to create for Process module
	 * 
	 * ~~~~~
	 * // example of getting module info
	 * $moduleInfo = $modules->getModuleInfo('InputfieldCKEditor'); 
	 * 
	 * // example of getting verbose module info
	 * $moduleInfo = $modules->getModuleInfoVerbose('MarkupAdminDataTable');
	 * ~~~~~
	 * 
	 * @param string|Module|int $class Specify one of the following:
	 *  - Module object instance 
	 *  - Module class name (string)
	 *  - Module ID (int)
	 *  - To get info for ALL modules, specify `*` or `all`.
	 *  - To get system information, specify `ProcessWire` or `PHP`.
	 *  - To get a blank module info template, specify `info`.
	 * @param array $options Optional options to modify behavior of what gets returned
	 *  - `verbose` (bool): Makes the info also include verbose properties, which are otherwise blank. (default=false)
	 *  - `minify` (bool): Remove non-applicable and properties that match defaults? (default=false, or true when getting `all`)
	 *  - `noCache` (bool): prevents use of cache to retrieve the module info. (default=false)
	 * @return array Associative array of module information
	 * @throws WireException when a module exists but has no means of returning module info
	 * @see Modules::getModuleInfoVerbose()
	 * @todo move all getModuleInfo methods to their own ModuleInfo class and break this method down further. 
	 *	
	 */
	public function getModuleInfo($class, array $options = array()) {
		
		$getAll = $class === '*' || $class === 'all';
		$getSystem = $class === 'ProcessWire' || $class === 'PHP' || $class === 'info';
		
		$defaults = array(
			'verbose' => false,
			'minify' => $getAll,
			'noCache' => false, 
			'noInclude' => false,
		);
		
		$options = array_merge($defaults, $options);
		$info = array();
		$module = $class;
		$moduleName = '';
		$moduleID = 0;
		$fromCache = false;  // was the data loaded from cache?
	
		if(!$getAll && !$getSystem) {
			$moduleName = $this->getModuleClass($module);
			$moduleID = (string) $this->getModuleID($module); // typecast to string for cache
		}
		
		static $infoTemplate = array(
			// module database ID
			'id' => 0, 
			// module class name 
			'name' => '',
			// module title
			'title' => '',
			// module version
			'version' => 0,
			// module version (always formatted string)
			'versionStr' => '0.0.0', 
			// who authored the module? (included in 'verbose' mode only)
			'author' => '',
			// summary of what this module does (included in 'verbose' mode only)
			'summary' => '',
			// URL to module details (included in 'verbose' mode only)
			'href' => '',
			// Optional name of icon representing this module (currently font-awesome icon names, excluding the "fa-" portion)
			'icon' => '', 
			// this method converts this to array of module names, regardless of how the module specifies it
			'requires' => array(),
			// module name is key, value is array($operator, $version). Note 'requiresVersions' index is created by this function.
			'requiresVersions' => array(),
			// array of module class names
			'installs' => array(),
			// permission required to execute this module
			'permission' => '',
			// permissions automatically installed/uninstalled with this module. array of ('permission-name' => 'Description')
			'permissions' => array(),
			// true if module is autoload, false if not. null=unknown
			'autoload' => null,
			// true if module is singular, false if not. null=unknown
			'singular' => null,
			// unix-timestamp date/time module added to system (for uninstalled modules, it is the file date)
			'created' => 0, 
			// is the module currently installed? (boolean, or null when not determined)
			'installed' => null,
			// this is set to true when the module is configurable, false when it's not, and null when it's not determined
			'configurable' => null,
			// verbose mode only: true when module implements SearchableModule interface, or null|false when not
			'searchable' => null, 
			// namespace that module lives in (string)
			'namespace' => null,
			// verbose mode only: this is set to the module filename (from PW installation root), false when it can't be found, null when it hasn't been determined
			'file' => null, 
			// verbose mode only: this is set to true when the module is a core module, false when it's not, and null when it's not determined
			'core' => null, 
			
			// other properties that may be present, but are optional, for Process modules:
			// 'nav' => array(), // navigation definition: see Process.php
			// 'useNavJSON' => bool, // whether the Process module provides JSON navigation
			// 'page' => array(), // page to create for Process module: see Process.php
			// 'permissionMethod' => string or callable // method to call to determine permission: see Process.php
			);
		
		if($getAll) {
			if(empty($this->moduleInfoCache)) $this->loadModuleInfoCache();
			$modulesInfo = $this->moduleInfoCache;
			if($options['verbose']) {
				if(empty($this->moduleInfoCacheVerbose)) $this->loadModuleInfoCacheVerbose();
				foreach($this->moduleInfoCacheVerbose as $moduleID => $moduleInfoVerbose) {
					$modulesInfo[$moduleID] = array_merge($modulesInfo[$moduleID], $moduleInfoVerbose);
				}
			}
			if(!$options['minify']) {
				foreach($modulesInfo as $moduleID => $info) {
					$modulesInfo[$moduleID] = array_merge($infoTemplate, $info); 
				}
			}
			return $modulesInfo;

		} else if($getSystem) {
			// module is a system 
			if($class === 'info') return $infoTemplate;
			$info = $this->getModuleInfoSystem($module);
			return $options['minify'] ? $info : array_merge($infoTemplate, $info);

		} else if($module instanceof Module) {
			// module is an instance
			// $moduleName = method_exists($module, 'className') ? $module->className() : get_class($module); 
			// return from cache if available
			
			if(empty($options['noCache']) && !empty($this->moduleInfoCache[$moduleID])) {
				$info = $this->moduleInfoCache[$moduleID]; 
				$fromCache = true; 
			} else {
				$info = $this->getModuleInfoExternal($moduleName); 
				if(!count($info)) $info = $this->getModuleInfoInternal($module); 
			}
			
		} else {
			
			// module is a class name or ID
			if(ctype_digit("$module")) $module = $moduleName;
			
			// return from cache if available (as it almost always should be)
			if(empty($options['noCache']) && !empty($this->moduleInfoCache[$moduleID])) {
				$info = $this->moduleInfoCache[$moduleID];
				$fromCache = true; 
				
			} else if(empty($options['noCache']) && $moduleID == 0) {
				// uninstalled module
				if(!count($this->moduleInfoCacheUninstalled)) $this->loadModuleInfoCacheVerbose(true);
				if(isset($this->moduleInfoCacheUninstalled[$moduleName])) {
					$info = $this->moduleInfoCacheUninstalled[$moduleName];
					$fromCache = true; 
				}
			}
			
			if(!$fromCache) { 
				$namespace = $this->getModuleNamespace($moduleName); 
				if(class_exists($namespace . $moduleName, false)) {
					// module is already in memory, check external first, then internal
					$info = $this->getModuleInfoExternal($moduleName);
					if(!count($info)) $info = $this->getModuleInfoInternal($moduleName, $namespace);
					
				} else {
					// module is not in memory, check external first, then internal
					$info = $this->getModuleInfoExternal($moduleName);
					if(!count($info)) {
						if(isset($this->installable[$moduleName])) $this->includeModuleFile($this->installable[$moduleName], $moduleName);
						// info not available externally, attempt to locate it interally
						$info = $this->getModuleInfoInternal($moduleName, $namespace);
					}
				}
			}
		}
		
		if(!$fromCache && !count($info)) {
			$info = $infoTemplate; 
			$info['title'] = $module;
			$info['summary'] = 'Inactive';
			$info['error'] = 'Unable to locate module';
			return $info;
		}
		
		if(!$options['minify']) $info = array_merge($infoTemplate, $info); 
		$info['id'] = (int) $moduleID;

		if($fromCache) {
			// since cache is loaded at init(), this is the most common scenario

			if($options['verbose']) { 
				if(empty($this->moduleInfoCacheVerbose)) $this->loadModuleInfoCacheVerbose();
				if(!empty($this->moduleInfoCacheVerbose[$moduleID])) {
					$info = array_merge($info, $this->moduleInfoCacheVerbose[$moduleID]); 
				}
			}
		
			// populate defaults for properties omitted from cache 
			if(is_null($info['autoload'])) $info['autoload'] = false;
			if(is_null($info['singular'])) $info['singular'] = false;
			if(is_null($info['configurable'])) $info['configurable'] = false;
			if(is_null($info['core'])) $info['core'] = false;
			if(is_null($info['installed'])) $info['installed'] = true;
			if(is_null($info['namespace'])) $info['namespace'] = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\" : "";
			if(!empty($info['requiresVersions'])) $info['requires'] = array_keys($info['requiresVersions']);
			if($moduleName == 'SystemUpdater') $info['configurable'] = 1; // fallback, just in case
			
			// we skip everything else when module comes from cache since we can safely assume the checks below 
			// are already accounted for in the cached module info
		
		} else {
			// not from cache, only likely to occur when refreshing modules info caches

			// if $info[requires] isn't already an array, make it one
			if(!is_array($info['requires'])) {
				$info['requires'] = str_replace(' ', '', $info['requires']); // remove whitespace
				if(strpos($info['requires'], ',') !== false) {
					$info['requires'] = explode(',', $info['requires']);
				} else {
					$info['requires'] = array($info['requires']);
				}
			}
	
			// populate requiresVersions
			foreach($info['requires'] as $key => $class) {
				if(!ctype_alnum($class)) {
					// has a version string
					list($class, $operator, $version) = $this->extractModuleOperatorVersion($class); 
					$info['requires'][$key] = $class; // convert to just class
				} else {
					// no version string
					$operator = '>=';
					$version = 0; 
				}
				$info['requiresVersions'][$class] = array($operator, $version); 
			}

			// what does it install?
			// if $info[installs] isn't already an array, make it one
			if(!is_array($info['installs'])) {
				$info['installs'] = str_replace(' ', '', $info['installs']); // remove whitespace
				if(strpos($info['installs'], ',') !== false) {
					$info['installs'] = explode(',', $info['installs']);
				} else {
					$info['installs'] = array($info['installs']);
				}
			}

			// misc
			if($options['verbose']) $info['versionStr'] = $this->formatVersion($info['version']); // versionStr
			$info['name'] = $moduleName; // module name

			// module configurable?
			$configurable = $this->isConfigurable($moduleName, false);
			if($configurable === true || is_int($configurable) && $configurable > 1) {
				// configurable via ConfigurableModule interface
				// true=static, 2=non-static, 3=non-static $data, 4=non-static wrap,
				// 19=non-static getModuleConfigArray, 20=static getModuleConfigArray
				$info['configurable'] = $configurable; 
			} else if($configurable) {
				// configurable via external file: ModuleName.config.php or ModuleNameConfig.php file
				$info['configurable'] = basename($configurable); 
			} else {
				// not configurable
				$info['configurable'] = false;
			}
			
			// created date
			if(isset($this->createdDates[$moduleID])) $info['created'] = strtotime($this->createdDates[$moduleID]);
			$info['installed'] = isset($this->installable[$moduleName]) ? false : true;
			if(!$info['installed'] && !$info['created'] && isset($this->installable[$moduleName])) {
				// uninstalled modules get their created date from the file or dir that they are in (whichever is newer)
				$pathname = $this->installable[$moduleName];
				$filemtime = (int) filemtime($pathname);
				$dirname = dirname($pathname);
				$dirmtime = substr($dirname, -7) == 'modules' || strpos($dirname, $this->paths[0]) !== false ? 0 : (int) filemtime($dirname);
				$info['created'] = $dirmtime > $filemtime ? $dirmtime : $filemtime;
			}
			
			// namespace
			if($info['core']) {
				// default namespace, assumed since all core modules are in default namespace
				$info['namespace'] = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\" : ""; 
			} else {
				$info['namespace'] = $this->getModuleNamespace($moduleName, array(
					'file' => $info['file'],
					'noCache' => $options['noCache']
				));
			}
			
			if(!$options['verbose']) foreach($this->moduleInfoVerboseKeys as $key) unset($info[$key]); 
		} 
		
		if(is_null($info['namespace'])) {
			$info['namespace'] = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\" : "";
		}
		
		if(empty($info['created']) && isset($this->createdDates[$moduleID])) {
			$info['created'] = strtotime($this->createdDates[$moduleID]);
		}
		
		if($options['verbose']) {
			// the file property is not stored in the verbose cache, but provided as a verbose key
			$info['file'] = $this->getModuleFile($moduleName);
			if($info['file']) $info['core'] = strpos($info['file'], $this->coreModulesDir) !== false; // is it core?
		} else {
			// module info may still contain verbose keys with undefined values	
		}
		
		if($options['minify']) {
			// when minify, any values that match defaults from infoTemplate are removed
			if(!$options['verbose']) foreach($this->moduleInfoVerboseKeys as $key) unset($info[$key]); 
			foreach($info as $key => $value) {
				if(!array_key_exists($key, $infoTemplate)) continue;
				if($value !== $infoTemplate[$key]) continue;
				unset($info[$key]); 
			}
		}
		
		// if($this->debug) $this->message("getModuleInfo($moduleName) " . ($fromCache ? "CACHE" : "NO-CACHE")); 
		
		return $info;
	}

	/**
	 * Returns a verbose array of information for a Module
	 * 
	 * This is the same as what’s returned by `Modules::getModuleInfo()` except that it has the following additional properties:
	 * 
	 *  - `versionStr` (string): formatted module version string.
	 *  - `file` (string): module filename from PW installation root, or false when it can't be found.
	 *  - `core` (bool): true when module is a core module, false when not.
	 *  - `author` (string): module author, when specified.
	 *  - `summary` (string): summary of what this module does.
	 *  - `href` (string): URL to module details (when specified).
	 *  - `permissions` (array): permissions installed by this module, associative array ('permission  - name' => 'Description').
	 *  - `page` (array): definition of page to create for Process module (see Process class)
	 *
	 * @param string|Module|int $class May be class name, module instance, or module ID
	 * @param array $options Optional options to modify behavior of what gets returned:
	 *  - `noCache` (bool): prevents use of cache to retrieve the module info
	 *  - `noInclude` (bool): prevents include() of the module file, applicable only if it hasn't already been included
	 * @return array Associative array of module information
	 * @throws WireException when a module exists but has no means of returning module info
	 * @see Modules::getModuleInfo()
	 *
	 */
	public function getModuleInfoVerbose($class, array $options = array()) {
		$options['verbose'] = true; 
		$info = $this->getModuleInfo($class, $options); 
		return $info;
	}

	/**
	 * Get just a single property of module info
	 * 
	 * @param Module|string $class Module instance or module name
	 * @param string $property Name of property to get
	 * @param array $options Additional options (see getModuleInfo method for options)
	 * @return mixed|null Returns value of property or null if not found
	 * 
	 */
	public function getModuleInfoProperty($class, $property, array $options = array()) {
		if(in_array($property, $this->moduleInfoVerboseKeys)) {
			$info = $this->getModuleInfoVerbose($class, $options);
			$info['verbose'] = true;
		} else {
			$info = $this->getModuleInfo($class, $options);
		}
		if(!isset($info[$property]) && empty($info['verbose'])) {
			// try again, just in case we can find it in verbose data
			$info = $this->getModuleInfoVerbose($class, $options);
		}
		return isset($info[$property]) ? $info[$property] : null;
	}

	/**
	 * Get an array of all unique, non-default, non-root module namespaces mapped to directory names
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function getNamespaces() {
		if(!is_null($this->moduleNamespaceCache)) return $this->moduleNamespaceCache;
		$defaultNamespace = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\" : "";
		$namespaces = array();
		foreach($this->moduleInfoCache as $moduleID => $info) {
			if(!isset($info['namespace']) || $info['namespace'] === $defaultNamespace || $info['namespace'] === "\\") continue;
			$moduleName = $info['name'];
			$namespaces[$info['namespace']] = $this->wire('config')->paths->$moduleName;
		}
		$this->moduleNamespaceCache = $namespaces; 
		return $namespaces; 
	}

	/**
	 * Get the namespace for the given module
	 * 
	 * #pw-internal
	 * 
	 * @param string|Module $moduleName
	 * @param array $options
	 * 	- `file` (string): Known module path/file, as an optimization.
	 * 	- `noCache` (bool): Specify true to force reload namespace info directly from module file.
	 * @return null|string Returns namespace, or NULL if unable to determine. Namespace is ready to use in a string (i.e. has trailing slashes)
	 * 
	 */
	public function getModuleNamespace($moduleName, $options = array()) {
		
		$defaults = array(
			'file' => null,
			'noCache' => false,
		);
		
		$namespace = null;
		$options = array_merge($defaults, $options);
	
		if(is_object($moduleName) || strpos($moduleName, "\\") !== false) {
			$className = is_object($moduleName) ? get_class($moduleName) : $moduleName;	
			$parts = explode("\\", $className);
			array_pop($parts);
			$namespace = count($parts) ? implode("\\", $parts) : "";
			$namespace = $namespace == "" ? "\\" : "\\$namespace\\";
			return $namespace;
		}
		
		if(empty($options['noCache'])) {
			$moduleID = $this->getModuleID($moduleName);
			$info = isset($this->moduleInfoCache[$moduleID]) ? $this->moduleInfoCache[$moduleID] : null;
			if($info && isset($info['namespace'])) {
				return $info['namespace'];
			}
		}
		
		if(empty($options['file'])) {
			$options['file'] = $this->getModuleFile($moduleName);
		}
		
		if(strpos($options['file'], $this->coreModulesDir) !== false) {
			// all core modules use \ProcessWire\ namespace
			$namespace = strlen(__NAMESPACE__) ? __NAMESPACE__ . "\\" : "";
			return $namespace;
		}
		
		if(!$options['file'] || !file_exists($options['file'])) {
			return null;
		}

		$namespace = $this->getFileNamespace($options['file']);
			
		return $namespace;
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
		$namespace = $this->wire('files')->getNamespace($file); 
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

	/**
	 * Alias of getConfig() for backwards compatibility
	 * 
	 * #pw-internal
	 * 
	 * @param string|Module $className
	 * @return array
	 * 
	 */
	public function getModuleConfigData($className) {
		return $this->getConfig($className);
	}

	/**
	 * Return the URL where the module can be edited, configured or uninstalled
	 * 
	 * If module is not installed, it just returns the URL to ProcessModule.
	 * 
	 * #pw-group-configuration
	 * 
	 * @param string|Module $className
	 * @param bool $collapseInfo
	 * @return string
	 * 
	 */
	public function getModuleEditUrl($className, $collapseInfo = true) {
		if(!is_string($className)) $className = $this->getModuleClass($className);
		$url = $this->wire('config')->urls->admin . 'module/';
		if(empty($className) || !$this->isInstalled($className)) return $url;
		$url .= "edit/?name=$className";
		if($collapseInfo) $url .= "&collapse_info=1";
		return $url;
	}
	
	/**
	 * Given a module name, return an associative array of configuration data for it
	 * 
	 * - Applicable only for modules that support configuration.
	 * - Configuration data is stored encoded in the database "modules" table "data" field.
	 * 
	 * ~~~~~~
	 * // Getting, modifying and saving module config data
	 * $data = $modules->getConfig('HelloWorld');
	 * $data['greeting'] = 'Hello World! How are you today?';
	 * $modules->saveConfig('HelloWorld', $data);
	 * 
	 * // Getting just one property 'apiKey' from module config data
	 * @apiKey = $modules->getConfig('HelloWorld', 'apiKey'); 
	 * ~~~~~~
	 *
	 * #pw-group-configuration
	 * #pw-changelog 3.0.16 Changed from more verbose name `getModuleConfigData()`, which can still be used. 
	 * 
	 * @param string|Module $class
	 * @param string $property Optionally just get value for a specific property (omit to get all config)
	 * @return array|string|int|float Module configuration data, returns array unless a specific $property was requested
	 * @see Modules::saveConfig() 
	 * @since 3.0.16 Use method getModuleConfigData() with same arguments for prior versions (can also be used on any version).
	 *
	 */
	public function getConfig($class, $property = '') {

		$emptyReturn = $property ? null : array();
		$className = $class;
		if(is_object($className)) $className = wireClassName($className->className(), false);
		if(!isset($this->moduleIDs[$className])) return $emptyReturn;
		$id = $this->moduleIDs[$className];
		if(!$id) return $emptyReturn;
		if(!isset($this->configData[$id])) return $emptyReturn; // module has no config data
		
		if(is_array($this->configData[$id])) {
			$data = $this->configData[$id];
		} else {
			// first verify that module doesn't have a config file
			$configurable = $this->isConfigurable($className);
			if(!$configurable) return $emptyReturn;
			$database = $this->wire('database');
			$query = $database->prepare("SELECT data FROM modules WHERE id=:id", "modules.getConfig($className)"); // QA
			$query->bindValue(":id", (int) $id, \PDO::PARAM_INT);
			$query->execute();
			$data = $query->fetchColumn();
			$query->closeCursor();
			if(strlen($data)) $data = wireDecodeJSON($data);
			if(empty($data)) $data = array();
			$this->configData[$id] = $data;
		}
		
		if($property) return isset($data[$property]) ? $data[$property] : null;

		return $data; 	
	}
	
	/**
	 * Get the path + filename (or optionally URL) for this module
	 * 
	 * @param string|Module $class Module class name or object instance
	 * @param array|bool $options Options to modify default behavior:
	 * 	- `getURL` (bool): Specify true if you want to get the URL rather than file path (default=false). 
	 * 	- `fast` (bool): Specify true as optimization to omit file_exists() checks (default=false). 
	 * 	- Note: If you specify a boolean for the $options argument, it is assumed to be the $getURL property.
	 * @return bool|string Returns string of module file, or false on failure. 
	 * 
	 */
	public function getModuleFile($class, $options = array()) {

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
		
		$hasDuplicate = $this->duplicates()->hasDuplicate($moduleName);
		
		if(!$hasDuplicate) {
			// see if we can determine it from already stored paths
			$path = $this->wire('config')->paths->$moduleName;
			if($path) {
				$file = $path . $moduleName . ($this->moduleFileExts[$moduleName] === 2 ? '.module.php' : '.module');
				if(!$options['fast'] && !file_exists($file)) $file = false;
			}
		}

		// next see if we've already got the module filename cached locally
		if(!$file && isset($this->installable[$moduleName]) && !$hasDuplicate) {
			$file = $this->installable[$moduleName];
			if(!$options['fast'] && !file_exists($file)) $file = false;
		} 
		
		if(!$file) {
			$dupFile = $this->duplicates()->getCurrent($moduleName);
			if($dupFile) {
				$rootPath = $this->wire('config')->paths->root;
				$file = rtrim($rootPath, '/') . $dupFile;
				if(!file_exists($file)) {
					// module in use may have been deleted, find the next available one that exist
					$file = '';
					$dups = $this->duplicates()->getDuplicates($moduleName); 
					foreach($dups['files'] as $pathname) {
						$pathname = rtrim($rootPath, '/') . $pathname;
						if(file_exists($pathname)) {
							$file = $pathname;
							break;
						}
					}
				}
			}
		}
		
		if(!$file) {
			// see if it's a predefined core type that can be determined from the type
			// this should only come into play if something has gone wrong with the modules loader
			foreach($this->coreTypes as $typeName) {
				if(strpos($moduleName, $typeName) !== 0) continue;
				$checkFiles = array(
					"$typeName/$moduleName/$moduleName.module",
					"$typeName/$moduleName/$moduleName.module.php",
					"$typeName/$moduleName.module",
					"$typeName/$moduleName.module.php",
				);
				$path1 = $this->wire('config')->paths->modules;
				foreach($checkFiles as $checkFile) {
					$file1 = $path1 . $checkFile;
					if(is_file($file1)) {
						$file = $file1;
						break;
					}
				}
				if($file) break;
			}
		}

		if(!$file) {
			// if all the above failed, try to get it from Reflection
			try {
				// note we don't call getModuleClass() here because it may result in a circular reference
				if(strpos($className, "\\") === false) {
					$moduleID = $this->getModuleID($moduleName);
					if(!empty($this->moduleInfoCache[$moduleID]['namespace'])) {
						$className = rtrim($this->moduleInfoCache[$moduleID]['namespace'], "\\") . "\\$moduleName";
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

		if($file) {
			if(DIRECTORY_SEPARATOR != '/') $file = str_replace(DIRECTORY_SEPARATOR, '/', $file);
			if($options['getURL']) $file = str_replace($this->wire('config')->paths->root, '/', $file);
		}

		return $file;
	}

	/**
	 * Is the given module configurable?
	 * 
	 * This method can be used to simply determine if a module is configurable (yes or no), or more specifically
	 * how it is configurable. 
	 * 
	 * ~~~~~
	 * // Determine IF a module is configurable
	 * if($modules->isConfigurable('HelloWorld')) {
	 *   // Module is configurable
	 * } else {
	 *   // Module is NOT configurable
	 * }
	 * ~~~~~
	 * ~~~~~
	 * // Determine HOW a module is configurable
	 * $configurable = $module->isConfigurable('HelloWorld');
	 * if($configurable === true) {
	 *   // configurable in a way compatible with all past versions of ProcessWire
	 * } else if(is_string($configurable)) {
	 *   // configurable via an external configuration file
	 *   // file is identifed in $configurable variable
	 * } else if(is_int($configurable)) {
	 *   // configurable via a method in the class
	 *   // the $configurable variable contains a number with specifics
	 * } else {
	 *   // module is NOT configurable
	 * }
	 * ~~~~~
	 * 
	 * ### Return value details
	 * 
	 * #### If module is configurable via external configuration file:
	 * 
	 * - Returns string of full path/filename to `ModuleName.config.php` file 
	 * 
	 * #### If module is configurable because it implements a configurable module interface:
	 * 
	 * - Returns boolean `true` if module is configurable via the static `getModuleConfigInputfields()` method.
	 *   This particular method is compatible with all past versions of ProcessWire. 
	 * - Returns integer `2` if module is configurable via the non-static `getModuleConfigInputfields()` and requires no arguments.
	 * - Returns integer `3` if module is configurable via the non-static `getModuleConfigInputfields()` and requires `$data` array.
	 * - Returns integer `4` if module is configurable via the non-static `getModuleConfigInputfields()` and requires `InputfieldWrapper` argument.
	 * - Returns integer `19` if module is configurable via non-static `getModuleConfigArray()` method.
	 * - Returns integer `20` if module is configurable via static `getModuleConfigArray()` method.
	 * 
	 * #### If module is not configurable:
	 * 
	 * - Returns boolean `false` if not configurable
	 * 
	 * *This method is named isConfigurableModule() in ProcessWire versions prior to to 3.0.16.*
	 * 
	 * #pw-group-configuration
	 * 
	 * @param Module|string $class Module name
	 * @param bool $useCache Use caching? This accepts a few options: 
	 * 	- Specify boolean `true` to allow use of cache when available (default behavior). 
	 * 	- Specify boolean `false` to disable retrieval of this property from getModuleInfo (forces a new check).
	 * 	- Specify string `interface` to check only if module implements ConfigurableModule interface. 
	 * 	- Specify string `file` to check only if module has a separate configuration class/file.
	 * @return bool|string|int See details about return values in method description. 
	 * @since 3.0.16
	 * 
	 * @todo all ConfigurableModule methods need to be split out into their own class (ConfigurableModules?)
	 * @todo this method has two distinct parts (file and interface) that need to be split in two methods.
	 * 
	 */
	public function isConfigurable($class, $useCache = true) {

		$className = $class;
		$moduleInstance = null;
		$namespace = $this->getModuleNamespace($className);
		if(is_object($className)) {
			$moduleInstance = $className;
			$className = $this->getModuleClass($moduleInstance);
		}
		$nsClassName = $namespace . $className;
		
		if($useCache === true || $useCache === 1 || $useCache === "1") {
			$info = $this->getModuleInfo($className);
			// if regular module info doesn't have configurable info, attempt it from verbose module info
			// should only be necessary for transition period between the 'configurable' property being 
			// moved from verbose to non-verbose module info (i.e. this line can be deleted after PW 2.7)
			if($info['configurable'] === null) $info = $this->getModuleInfoVerbose($className);
			if(!$info['configurable']) {
				if($moduleInstance && $moduleInstance instanceof ConfigurableModule) {
					// re-try because moduleInfo may be temporarily incorrect for this request because of change in moduleInfo format
					// this is due to reports of ProcessChangelogHooks not getting config data temporarily between 2.6.11 => 2.6.12
					$this->error("Configurable module check failed for $className, retrying...", Notice::debug);
					$useCache = false; 
				} else {
					return false;
				}
			} else {
				if($info['configurable'] === true) return $info['configurable'];
				if($info['configurable'] === 1 || $info['configurable'] === "1") return true;
				if(is_int($info['configurable']) || ctype_digit("$info[configurable]")) return (int) $info['configurable'];
				if(strpos($info['configurable'], $className) === 0) {
					if(empty($info['file'])) $info['file'] = $this->getModuleFile($className);
					if($info['file']) {
						return dirname($info['file']) . "/$info[configurable]";
					}
				}
			}
		}

		if($useCache !== "interface") {
			// check for separate module configuration file
			$dir = dirname($this->getModuleFile($className));
			if($dir) {
				$files = array(
					"$dir/{$className}Config.php", 
					"$dir/$className.config.php"
				); 
				$found = false;
				foreach($files as $file) {
					if(!is_file($file)) continue;
					$config = null; // include file may override
					$this->includeModuleFile($file, $className);
					$classConfig = $nsClassName . 'Config';
					if(class_exists($classConfig, false)) {
						$parents = wireClassParents($classConfig, false);
						if(is_array($parents) && in_array('ModuleConfig', $parents)) {
							$found = $file;
							break;
						}
					} else {
						// bypass include_once, because we need to read $config every time
						if(is_null($config)) {
							$classInfo = $this->getFileClassInfo($file);
							if($classInfo['class']) {
								// not safe to include because this is not just a file with a $config array
							} else {
								$ns = $this->getFileNamespace($file);
								$file = $this->compile($className, $file, $ns);
								if($file) {
									/** @noinspection PhpIncludeInspection */
									include($file);
								}
							}
						}
						if(!is_null($config)) {
							// included file specified a $config array
							$found = $file;
							break;
						}
					}
				}
				if($found) return $found;
			}
		}

		// if file-only check was requested and we reach this point, exit with false now
		if($useCache === "file") return false;
	
		// ConfigurableModule interface checks
		
		$result = false;
		
		foreach(array('getModuleConfigArray', 'getModuleConfigInputfields') as $method) {
			
			$configurable = false;
		
			// if we have a module instance, use that for our check
			if($moduleInstance && $moduleInstance instanceof ConfigurableModule) {
				if(method_exists($moduleInstance, $method)) {
					$configurable = $method;
				} else if(method_exists($moduleInstance, "___$method")) {
					$configurable = "___$method";
				}
			}

			// if we didn't have a module instance, load the file to find what we need to know
			if(!$configurable) {
				if(!wireClassExists($nsClassName, false)) {
					$this->includeModule($className);
				}
				$interfaces = wireClassImplements($nsClassName, false);
				if(is_array($interfaces) && in_array('ConfigurableModule', $interfaces)) {
					if(wireMethodExists($nsClassName, $method)) {
						$configurable = $method;
					} else if(wireMethodExists($nsClassName, "___$method")) {
						$configurable = "___$method";
					}
				}
			}
			
			// if still not determined to be configurable, move on to next method
			if(!$configurable) continue;
			
			// now determine if static or non-static
			$ref = new \ReflectionMethod(wireClassName($nsClassName, true), $configurable);
			
			if($ref->isStatic()) {
				// config method is implemented as a static method
				if($method == 'getModuleConfigInputfields') {
					// static getModuleConfigInputfields
					$result = true;
				} else {
					// static getModuleConfigArray
					$result = 20; 
				}
				
			} else if($method == 'getModuleConfigInputfields') {
				// non-static getModuleConfigInputfields
				// we allow for different arguments, so determine what it needs
				$parameters = $ref->getParameters();
				if(count($parameters)) {
					$param0 = reset($parameters);
					if(strpos($param0, 'array') !== false || strpos($param0, '$data') !== false) {
						// method requires a $data array (for compatibility with non-static version)
						$result = 3;
					} else if(strpos($param0, 'InputfieldWrapper') !== false || strpos($param0, 'inputfields') !== false) {
						// method requires an empty InputfieldWrapper (as a convenience)
						$result = 4;
					}
				}
				// method requires no arguments
				if(!$result) $result = 2;
				
			} else {
				// non-static getModuleConfigArray
				$result = 19;
			}
		
			// if we make it here, we know we already have a result so can stop now
			break;
		}
		
		return $result;
	}

	/**
	 * Alias of isConfigurable() for backwards compatibility
	 * 
	 * #pw-internal
	 * 
	 * @param $className
	 * @param bool $useCache
	 * @return mixed
	 * 
	 */
	public function isConfigurableModule($className, $useCache = true) {
		return $this->isConfigurable($className, $useCache); 
	}

	/**
	 * Populate configuration data to a ConfigurableModule
	 *
	 * If the Module has a 'setConfigData' method, it will send the array of data to that. 
	 * Otherwise it will populate the properties individually. 
	 *
	 * @param Module $module
	 * @param array $data Configuration data (key = value), or omit if you want it to retrieve the config data for you.
	 * @return bool True if configured, false if not configurable
	 * 
	 */
	protected function setModuleConfigData(Module $module, $data = null) {

		$configurable = $this->isConfigurable($module); 
		if(!$configurable) return false;
		if(!is_array($data)) $data = $this->getConfig($module);

		$nsClassName = $module->className(true);
		$moduleName = $module->className(false);
		
		if(is_string($configurable) && is_file($configurable) && strpos(basename($configurable), $moduleName) === 0) {
			// get defaults from ModuleConfig class if available
			$className = $nsClassName . 'Config';
			$config = null; // may be overridden by included file
			// $compile = strrpos($className, '\\') < 1 && $this->wire('config')->moduleCompile;
			$configFile = '';
			
			if(!class_exists($className, false)) {
				$configFile = $this->compile($className, $configurable); 
				// $configFile = $compile ? $this->wire('files')->compile($configurable) : $configurable;
				if($configFile) {
					/** @noinspection PhpIncludeInspection */
					include_once($configFile);
				}
			}
			
			if(wireClassExists($className)) {
				$parents = wireClassParents($className, false);
				if(is_array($parents) && in_array('ModuleConfig', $parents)) { 
					$moduleConfig = $this->wire(new $className());
					if($moduleConfig instanceof ModuleConfig) {
						$defaults = $moduleConfig->getDefaults();
						$data = array_merge($defaults, $data);
					}
				}
			} else {
				// the file may have already been include_once before, so $config would not be set
				// so we try a regular include() next. 
				if(is_null($config)) {
					if(!$configFile) {
						$configFile = $this->compile($className, $configurable);
						// $configFile = $compile ? $this->wire('files')->compile($configurable) : $configurable;
					}
					if($configFile) {
						/** @noinspection PhpIncludeInspection */
						include($configFile);
					}
				}
				if(is_array($config)) {
					// alternatively, file may just specify a $config array
					$moduleConfig = $this->wire(new ModuleConfig());
					$moduleConfig->add($config);
					$defaults = $moduleConfig->getDefaults();
					$data = array_merge($defaults, $data);
				}
			}
		}

		if(method_exists($module, 'setConfigData') || method_exists($module, '___setConfigData')) {
			/** @var _Module $module */
			$module->setConfigData($data); 
			return true;
		}

		foreach($data as $key => $value) {
			$module->$key = $value; 
		}
		
		return true; 
	}

	/**
	 * Alias of saveConfig() for backwards compatibility
	 * 
	 * #pw-internal
	 * 
	 * @param $className
	 * @param array $configData
	 * @return mixed
	 * 
	 */
	public function ___saveModuleConfigData($className, array $configData) {
		return $this->saveConfig($className, $configData);
	}

	/**
	 * Save provided configuration data for the given module
	 * 
	 * - Applicable only for modules that support configuration.
	 * - Configuration data is stored encoded in the database "modules" table "data" field.
	 * 
	 * ~~~~~~
	 * // Getting, modifying and saving module config data
	 * $data = $modules->getConfig('HelloWorld');
	 * $data['greeting'] = 'Hello World! How are you today?';
	 * $modules->saveConfig('HelloWorld', $data);
	 * ~~~~~~
	 * 
	 * #pw-group-configuration
	 * #pw-group-manipulation
	 * #pw-changelog 3.0.16 Changed name from the more verbose saveModuleConfigData(), which will still work.
	 *
	 * @param string|Module $class Module or module name
	 * @param array|string $data Associative array of configuration data, or name of property you want to save.
	 * @param mixed|null $value If you specified a property in previous arg, the value for the property.
	 * @return bool True on success, false on failure
	 * @throws WireException
	 * @see Modules::getConfig()
	 * @since 3.0.16 Use method saveModuleConfigData() with same arguments for prior versions (can also be used on any version).
	 *
	 */
	public function ___saveConfig($class, $data, $value = null) {
		$className = $class;
		if(is_object($className)) $className = $className->className();
		$moduleName = wireClassName($className, false);
		if(!$id = $this->moduleIDs[$moduleName]) throw new WireException("Unable to find ID for Module '$moduleName'");
		
		if(is_string($data)) {
			// a property and value have been provided
			$property = $data;	
			$data = $this->getConfig($class);
			if(is_null($value)) {
				// remove the property
				unset($data[$property]);
			} else {
				// populate the value for the property
				$data[$property] = $value;
			}
		} else {
			// data must be an associative array of configuration data
			if(!is_array($data)) return false;
		}

		// ensure original duplicates info is retained and validate that it is still current
		$data = $this->duplicates()->getDuplicatesConfigData($moduleName, $data); 
		
		$this->configData[$id] = $data; 
		$json = count($data) ? wireEncodeJSON($data, true) : '';
		$database = $this->wire('database'); 	
		$query = $database->prepare("UPDATE modules SET data=:data WHERE id=:id", "modules.saveConfig($moduleName)"); // QA
		$query->bindValue(":data", $json, \PDO::PARAM_STR);
		$query->bindValue(":id", (int) $id, \PDO::PARAM_INT); 
		$result = $query->execute();
		$this->log("Saved module '$moduleName' config data");
		
		return $result;
	}

	/**
	 * Get the Inputfields that configure the given module or return null if not configurable
	 * 
	 * #pw-internal
	 * 
	 * @param string|Module|int $moduleName
	 * @param InputfieldWrapper|null $form Optionally specify the form you want Inputfields appended to.
	 * @return InputfieldWrapper|null
	 * 
	 */
	public function ___getModuleConfigInputfields($moduleName, InputfieldWrapper $form = null) {
		
		$moduleName = $this->getModuleClass($moduleName);
		$configurable = $this->isConfigurable($moduleName);
		if(!$configurable) return null;
		
		if(is_null($form)) $form = $this->wire(new InputfieldWrapper());
		$data = $this->getConfig($moduleName);
		$fields = null;
		
		// check for configurable module interface
		$configurableInterface = $this->isConfigurable($moduleName, "interface");
		if($configurableInterface) {
			if(is_int($configurableInterface) && $configurableInterface > 1 && $configurableInterface < 20) {
				// non-static 
				/** @var ConfigurableModule|Module|_Module $module */
				if($configurableInterface === 2) {
					// requires no arguments
					$module = $this->getModule($moduleName);
					$fields = $module->getModuleConfigInputfields();
				} else if($configurableInterface === 3) {
					// requires $data array
					$module = $this->getModule($moduleName, array('noInit' => true, 'noCache' => true));
					$this->setModuleConfigData($module);
					$fields = $module->getModuleConfigInputfields($data);
				} else if($configurableInterface === 4) {
					// requires InputfieldWrapper
					// we allow for option of no return statement in the method
					$module = $this->getModule($moduleName);
					$fields = $this->wire(new InputfieldWrapper());
					$fields->setParent($form);
					$_fields = $module->getModuleConfigInputfields($fields);
					if($_fields instanceof InputfieldWrapper) $fields = $_fields;
					unset($_fields);
				} else if($configurableInterface === 19) {
					// non-static getModuleConfigArray method
					$module = $this->getModule($moduleName);
					$fields = $this->wire(new InputfieldWrapper());
					$fields->importArray($module->getModuleConfigArray());
					$fields->populateValues($module);
				}
			} else if($configurableInterface === 20) {
				// static getModuleConfigArray method
				$fields = $this->wire(new InputfieldWrapper());
				$fields->importArray(call_user_func(array(wireClassName($moduleName, true), 'getModuleConfigArray')));
				$fields->populateValues($data);
			} else if($configurableInterface) {
				// static getModuleConfigInputfields method
				$nsClassName = $this->getModuleNamespace($moduleName) . $moduleName;
				$fields = call_user_func(array($nsClassName, 'getModuleConfigInputfields'), $data);
			}
			if($fields instanceof InputfieldWrapper) {
				foreach($fields as $field) {
					$form->append($field);
				}
			} else if($fields instanceof Inputfield) {
				$form->append($fields);
			} else {
				$this->error("$moduleName.getModuleConfigInputfields() did not return InputfieldWrapper");
			}
		}
		
		// check for file-based config
		$file = $this->isConfigurable($moduleName, "file");
		if(!$file || !is_string($file) || !is_file($file)) {
			// config is not file-based
		} else {
			// file-based config
			$config = null;
			$ns = $this->getModuleNamespace($moduleName);
			$configClass = $ns . $moduleName . "Config";
			if(!class_exists($configClass)) {
				$configFile = $this->compile($moduleName, $file, $ns);
				if($configFile) {
					/** @noinspection PhpIncludeInspection */
					include_once($configFile);
				}
			}
			$configModule = null;

			if(wireClassExists($configClass)) {
				// file contains a ModuleNameConfig class
				$configModule = $this->wire(new $configClass());

			} else {
				if(is_null($config)) {
					$configFile = $this->compile($moduleName, $file, $ns);
					// if(!$configFile) $configFile = $compile ? $this->wire('files')->compile($file) : $file;
					if($configFile) {
						/** @noinspection PhpIncludeInspection */
						include($configFile); // in case of previous include_once 
					}
				}
				if(is_array($config)) {
					// file contains a $config array
					$configModule = $this->wire(new ModuleConfig());
					$configModule->add($config);
				}
			}

			if($configModule && $configModule instanceof ModuleConfig) {
				$defaults = $configModule->getDefaults();
				$data = array_merge($defaults, $data);
				$configModule->setArray($data);
				$fields = $configModule->getInputfields();
				if($fields instanceof InputfieldWrapper) {
					foreach($fields as $field) {
						$form->append($field);
					}
					foreach($data as $key => $value) {
						$f = $form->getChildByName($key);
						if(!$f) continue;
						if($f instanceof InputfieldCheckbox && $value) {
							$f->attr('checked', 'checked');
						} else {
							$f->attr('value', $value);
						}
					}
				} else {
					$this->error("$configModule.getInputfields() did not return InputfieldWrapper");
				}
			}
		} // file-based config
		
		if($form) {
			// determine how many visible Inputfields there are in the module configuration
			// for assignment or removal of flagsNoUserConfig flag when applicable
			$numVisible = 0;
			foreach($form->getAll() as $inputfield) {
				if($inputfield instanceof InputfieldHidden || $inputfield instanceof InputfieldWrapper) continue;
				$numVisible++;
			}
			$flags = $this->getFlags($moduleName);
			if($numVisible) {
				if($flags & self::flagsNoUserConfig) {
					$info = $this->getModuleInfoVerbose($moduleName);
					if(empty($info['addFlag']) || !($info['addFlag'] & self::flagsNoUserConfig)) {
						$this->setFlag($moduleName, self::flagsNoUserConfig, false); // remove flag
					}
				}
			} else {
				if(!($flags & self::flagsNoUserConfig)) {
					if(empty($info['removeFlag']) || !($info['removeFlag'] & self::flagsNoUserConfig)) {
						$this->setFlag($moduleName, self::flagsNoUserConfig, true); // add flag
					}
				}
			}
		}
		
		return $form;
	}
	

	/**
	 * Is the given module Singular (single instance)?
	 *
	 * isSingular and isAutoload Module methods have been deprecated. So this method, and isAutoload() 
	 * exist in part to enable singular and autoload properties to be set in getModuleInfo, rather than 
	 * with methods. 
 	 *
	 * Note that isSingular() and isAutoload() are not deprecated for ModulePlaceholder, so the Modules
	 * class isn't going to stop looking for them. 
	 * 
	 * #pw-internal
	 *
	 * @param Module|string $module Module instance or class name
	 * @return bool 
 	 *
	 */
	public function isSingular($module) {
		$info = $this->getModuleInfo($module); 
		if(isset($info['singular']) && $info['singular'] !== null) return $info['singular'];
		if(is_object($module)) {
			if(method_exists($module, 'isSingular')) return $module->isSingular();
		} else {
			// singular status can't be determined if module not installed and not specified in moduleInfo
			if(isset($this->installable[$module])) return null;
			$this->includeModule($module); 
			$module = wireClassName($module, true);
			if(method_exists($module, 'isSingular')) {
				/** @var Module|_Module $moduleInstance */
				$moduleInstance = $this->wire(new $module());
				return $moduleInstance->isSingular();
			}
		}
		return false;
	}

	/**
	 * Is the given module Autoload (automatically loaded at runtime)?
	 * 
	 * #pw-internal
	 *
	 * @param Module|string $module Module instance or class name
	 * @return bool|string|null Returns string "conditional" if conditional autoload, true if autoload, or false if not. Or null if unavailable. 
 	 *
	 */
	public function isAutoload($module) {
		
		$info = $this->getModuleInfo($module); 
		$autoload = null;
		
		if(isset($info['autoload']) && $info['autoload'] !== null) {
			// if autoload is a string (selector) or callable, then we flag it as autoload
			if(is_string($info['autoload']) || wireIsCallable($info['autoload'])) return "conditional"; 
			$autoload = $info['autoload'];
			
		} else if(!is_object($module)) {
			if(isset($this->installable[$module])) {
				// module is not installed
				// we are not going to be able to determine if this is autoload or not
				$flags = $this->getFlags($module); 
				if($flags !== null) {
					$autoload = $flags & self::flagsAutoload;
				} else {
					// unable to determine
					return null;
				}
			} else {
				// include for method exists call
				$this->includeModule($module);
				$module = wireClassName($module, true);
				$module = $this->wire(new $module());
			}
		}
	
		if($autoload === null && is_object($module) && method_exists($module, 'isAutoload')) {
			/** @var module $module */
			$autoload = $module->isAutoload();
		}
	
		return $autoload; 
	}
	
	/**
	 * Returns whether the modules have been initialized yet
	 * 
	 * #pw-internal
	 *
 	 * @return bool
	 *
	 */
	public function isInitialized() {
		return $this->initialized; 
	}

	/**
	 * Does the given module name resolve to a module in the system (installed or uninstalled)
	 * 
	 * If given module name also includes a namespace, then that namespace will be validated as well. 
	 * 
	 * #pw-internal
	 * 
	 * @param string|Module $moduleName With or without namespace
	 * @return bool
	 * 
	 */
	public function isModule($moduleName) {
		
		if(!is_string($moduleName)) {
			if(is_object($moduleName)) {
				if($moduleName instanceof Module) return true;
				return false;
			}
			$moduleName = $this->getModuleClass($moduleName);
		}
		/** @var string $moduleName */
		
		if(strpos($moduleName, "\\") !== false) {
			$namespace = wireClassName($moduleName, 1);
			$moduleName = wireClassName($moduleName, false);
		} else {
			$namespace = false;
		}
		
		if(isset($this->moduleIDs[$moduleName])) {
			$isModule = true;
		} else if(isset($this->installable[$moduleName])) {
			$isModule = true;
		} else {
			$isModule = false;
		}
		
		if($isModule && $namespace) {
			$actualNamespace = $this->getModuleNamespace($moduleName);
			if(trim($namespace, '\\') != trim($actualNamespace, '\\')) {
				$isModule = false;
			}
		}
		
		return $isModule;
	}

	/**
	 * Is the given namespace a unique recognized module namespace? If yes, returns the path to it. If not, returns boolean false;
	 * 
	 * #pw-internal
	 * 
	 * @param string $namespace
	 * @return bool|string
	 * 
	 */
	public function getNamespacePath($namespace) {
		if(is_null($this->moduleNamespaceCache)) $this->getNamespaces();
		$namespace = "\\" . trim($namespace, "\\") . "\\";
		return isset($this->moduleNamespaceCache[$namespace]) ? $this->moduleNamespaceCache[$namespace] : false;	
	}
	
	/**
	 * Refresh the modules cache
	 * 
	 * This forces the modules file and information cache to be re-created. 
	 * 
	 * #pw-group-manipulation
	 *
	 */
	public function ___refresh() {
		if($this->wire('config')->systemVersion < 6) {
			return;
		}
		$this->refreshing = true;
		$this->clearModuleInfoCache();
		$this->loadModulesTable();
		foreach($this->paths as $path) $this->findModuleFiles($path, false); 
		foreach($this->paths as $path) $this->load($path);
		if($this->duplicates()->numNewDuplicates() > 0) $this->duplicates()->updateDuplicates(); // PR#1020
		$this->refreshing = false;
	}

	/**
	 * Alias of refresh method for backwards compatibility
	 * 
	 * #pw-internal
	 *
	 */
	public function resetCache() {
		$this->refresh();
	}

	/**
	 * Return an array of module class names that require the given one
	 * 
	 * #pw-internal
	 * 
	 * @param string $class
	 * @param bool $uninstalled Set to true to include modules dependent upon this one, even if they aren't installed.
	 * @param bool $installs Set to true to exclude modules that indicate their install/uninstall is controlled by $class.
	 * @return array()
	 *
	 */
	public function getRequiredBy($class, $uninstalled = false, $installs = false) {

		$class = $this->getModuleClass($class); 
		$info = $this->getModuleInfo($class); 
		$dependents = array();

		foreach($this as $module) {
			$c = $this->getModuleClass($module); 	
			if(!$uninstalled && !$this->isInstalled($c)) continue; 
			$i = $this->getModuleInfo($c); 
			if(!count($i['requires'])) continue; 
			if($installs && in_array($c, $info['installs'])) continue; 
			if(in_array($class, $i['requires'])) $dependents[] = $c; 
		}

		return $dependents; 
	}

	/**
	 * Return an array of module class names required by the given one
	 * 
	 * Default behavior is to return all listed requirements, whether they are currently met by
	 * the environment or not. Specify TRUE for the 2nd argument to return only requirements
	 * that are not currently met. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $class
	 * @param bool $onlyMissing Set to true to return only required modules/versions that aren't 
	 * 	yet installed or don't have the right version. It excludes those that the class says it 
	 * 	will install (via 'installs' property of getModuleInfo)
	 * @param null|bool $versions Set to true to always include versions in the returned requirements list. 
	 * 	Set to null to always exclude versions in requirements list (so only module class names will be there).
	 * 	Set to false (which is the default) to include versions only when version is the dependency issue.
	 * 	Note versions are already included when the installed version is not adequate.
	 * @return array of strings each with ModuleName Operator Version, i.e. "ModuleName>=1.0.0"
	 *
	 */
	public function getRequires($class, $onlyMissing = false, $versions = false) {
		
		$class = $this->getModuleClass($class); 
		$info = $this->getModuleInfo($class); 
		$requires = $info['requires']; 
		$currentVersion = 0;

		// quick exit if arguments permit it 
		if(!$onlyMissing) {
			if($versions) foreach($requires as $key => $value) {
				list($operator, $version) = $info['requiresVersions'][$value]; 
				if(empty($version)) continue; 
				if(ctype_digit("$version")) $version = $this->formatVersion($version); 
				if(!empty($version)) $requires[$key] .= "$operator$version";
			}
			return $requires; 
		}

		foreach($requires as $key => $requiresClass) {

			if(in_array($requiresClass, $info['installs'])) {
				// if this module installs the required class, then we can stop now
				// and we assume it's installing the version it wants
				unset($requires[$key]); 
			}

			list($operator, $requiresVersion) = $info['requiresVersions'][$requiresClass];
			$installed = true; 

			if($requiresClass == 'PHP') {
				$currentVersion = PHP_VERSION; 

			} else if($requiresClass == 'ProcessWire') { 
				$currentVersion = $this->wire('config')->version; 

			} else if($this->isInstalled($requiresClass)) {
				if(!$requiresVersion) {
					// if no version is specified then requirement is already met
					unset($requires[$key]); 
					continue; 
				}
				$i = $this->getModuleInfo($requiresClass, array('noCache' => true)); 
				$currentVersion = $i['version'];
			} else {
				// module is not installed
				$installed = false; 
			}

			if($installed && $this->versionCompare($currentVersion, $requiresVersion, $operator)) {
				// required version is installed
				unset($requires[$key]); 

			} else if(empty($requiresVersion)) {
				// just the class name is fine
				continue; 
				
			} else if(is_null($versions)) {
				// request is for no versions to be included (just class names)
				$requires[$key] = $requiresClass; 

			} else {
				// update the requires string to clarify what version it requires
				if(ctype_digit("$requiresVersion")) $requiresVersion = $this->formatVersion($requiresVersion); 
				$requires[$key] = "$requiresClass$operator$requiresVersion";
			}
		}

		return $requires; 
	}


	/**
	 * Compare one module version to another, returning TRUE if they match the $operator or FALSE otherwise
	 * 
	 * #pw-internal
	 *
	 * @param int|string $currentVersion May be a number like 123 or a formatted version like 1.2.3
	 * @param int|string $requiredVersion May be a number like 123 or a formatted version like 1.2.3
	 * @param string $operator
	 * @return bool
	 *
	 */
	public function versionCompare($currentVersion, $requiredVersion, $operator) {
		
		if(ctype_digit("$currentVersion") && ctype_digit("$requiredVersion")) {
			// integer comparison is ok
			$currentVersion = (int) $currentVersion;
			$requiredVersion = (int) $requiredVersion;
			$result = false;
			
			switch($operator) {
				case '=': $result = ($currentVersion == $requiredVersion); break;
				case '>': $result = ($currentVersion > $requiredVersion); break;
				case '<': $result = ($currentVersion < $requiredVersion); break;
				case '>=': $result = ($currentVersion >= $requiredVersion); break;
				case '<=': $result = ($currentVersion <= $requiredVersion); break;
				case '!=': $result = ($currentVersion != $requiredVersion); break;
			}
			return $result;
		}

		// if either version has no periods or only one, like "1.2" then format it to stanard: "1.2.0"
		if(substr_count($currentVersion, '.') < 2) $currentVersion = $this->formatVersion($currentVersion);
		if(substr_count($requiredVersion, '.') < 2) $requiredVersion = $this->formatVersion($requiredVersion); 
		
		return version_compare($currentVersion, $requiredVersion, $operator); 
	}

	/**
	 * Return array of ($module, $operator, $requiredVersion)
	 *
	 * $version will be 0 and $operator blank if there are no requirements.
	 * 
	 * @param string $require Module class name with operator and version string
	 * @return array of array($moduleClass, $operator, $version)
	 *
	 */
	protected function extractModuleOperatorVersion($require) {

		if(ctype_alnum($require)) {
			// no version is specified
			return array($require, '', 0); 
		}

		$operators = array('<=', '>=', '<', '>', '!=', '='); 
		$operator = '';
		foreach($operators as $o) {
			if(strpos($require, $o)) {
				$operator = $o;
				break;
			}
		}

		// if no operator found, then no version is being specified
		if(!$operator) return array($require, '', 0); 

		// extract class and version
		list($class, $version) = explode($operator, $require); 

		// make version an integer if possible
		if(ctype_digit("$version")) $version = (int) $version; 
	
		return array($class, $operator, $version); 
	}

	/**
	 * Return an array of module class names required by the given one to be installed before this one.
	 *
	 * Excludes modules that are required but already installed. 
	 * Excludes uninstalled modules that $class indicates it handles via it's 'installs' getModuleInfo property.
	 * 
	 * #pw-internal
	 * 
	 * @param string $class
	 * @return array()
	 *
	 */
	public function getRequiresForInstall($class) {
		return $this->getRequires($class, true); 
	}

	/**
	 * Return an array of module class names required by the given one to be uninstalled before this one.
	 *
	 * Excludes modules that the given one says it handles via it's 'installs' getModuleInfo property.
	 * Module class names in returned array include operator and version in the string. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $class
	 * @return array()
	 *
	 */
	public function getRequiresForUninstall($class) {
		return $this->getRequiredBy($class, false, true); 
	}
	
	/**
	 * Return array of dependency errors for given module name
	 * 
	 * #pw-internal
	 *
	 * @param $moduleName
	 * @return array If no errors, array will be blank. If errors, array will be of strings (error messages)
	 *
	 */
	public function getDependencyErrors($moduleName) {

		$moduleName = $this->getModuleClass($moduleName);
		$info = $this->getModuleInfo($moduleName);
		$errors = array();

		if(empty($info['requires'])) return $errors;

		foreach($info['requires'] as $requiresName) {
			$error = '';

			if(!$this->isInstalled($requiresName)) {
				$error = $requiresName;

			} else if(!empty($info['requiresVersions'][$requiresName])) {
				list($operator, $version) = $info['requiresVersions'][$requiresName];
				$info2 = $this->getModuleInfo($requiresName); 
				$requiresVersion = $info2['version'];
				if(!empty($version) && !$this->versionCompare($requiresVersion, $version, $operator)) {
					$error = "$requiresName $operator $version";
				}
			}

			if($error) $errors[] = sprintf($this->_('Failed module dependency: %s requires %s'), $moduleName, $error);
		}

		return $errors;
	}


	/**
	 * Given a module version number, format it in a consistent way as 3 parts: 1.2.3 
	 * 
	 * #pw-internal
	 * 
	 * @param $version int|string
	 * @return string
	 * 
	 */
	public function formatVersion($version) {
		
		$version = trim($version);

		if(!ctype_digit(str_replace('.', '', $version))) {
			// if version has some characters other than digits or periods, remove them
			$version = preg_replace('/[^\d.]/', '', $version); 
		}
		
		if(ctype_digit("$version")) {
			// version contains only digits
			// make sure version is at least 3 characters in length, left padded with 0s
			$len = strlen($version); 

			if($len < 3) {
				$version = str_pad($version, 3, "0", STR_PAD_LEFT);

			} else if($len > 3) {
				// they really need to use a string for this type of version, 
				// as we can't really guess, but we'll try, converting 1234 to 1.2.34
			}

			// $version = preg_replace('/(\d)(?=\d)/', '$1.', $version); 
			$version = 
				substr($version, 0, 1) . '.' . 
				substr($version, 1, 1) . '.' . 
				substr($version, 2); 
			
		} else if(strpos($version, '.') !== false) {
			// version is a formatted string
			if(strpos($version, '.') == strrpos($version, '.')) {
				// only 1 period, like: 2.0, convert that to 2.0.0
				if(preg_match('/^\d\.\d$/', $version)) $version .= ".0";
			}
			
		} else {
			// invalid version?
		}
		
		if(!strlen($version)) $version = '0.0.0';
		
		return $version;
	}
	
	/**
	 * Load the module information cache
	 * 
	 * @return bool
	 * 
	 */
	protected function loadModuleInfoCache() {
		$data = $this->wire('cache')->get(self::moduleInfoCacheName); 	
		if($data) { 
			// if module class name keys in use (i.e. ProcessModule) it's an older version of 
			// module info cache, so we skip over it to force its re-creation
			if(is_array($data) && !isset($data['ProcessModule'])) $this->moduleInfoCache = $data; 
			$data = $this->wire('cache')->get(self::moduleLastVersionsCacheName);
			if(is_array($data)) $this->modulesLastVersions = $data;
			return true;
		}
		return false;
	}
	
	/**
	 * Load the module information cache (verbose info: summary, author, href, file, core)
	 *
	 * @param bool $uninstalled If true, it will load the uninstalled verbose cache.
	 * @return bool
	 *
	 */
	protected function loadModuleInfoCacheVerbose($uninstalled = false) {
		$name = $uninstalled ? self::moduleInfoCacheUninstalledName : self::moduleInfoCacheVerboseName;
		$data = $this->wire('cache')->get($name);
		if($data) {
			if(is_array($data)) {
				if($uninstalled) $this->moduleInfoCacheUninstalled = $data; 
					else $this->moduleInfoCacheVerbose = $data;
			}
			return true;
		}
		return false;
	}

	/**
	 * Clear the module information cache
	 * 
	 */
	protected function clearModuleInfoCache() {
	
		// record current module versions currently in moduleInfo
		$moduleVersions = array();
		foreach($this->moduleInfoCache as $id => $moduleInfo) {
			if(isset($this->modulesLastVersions[$id])) {
				$moduleVersions[$id] = $this->modulesLastVersions[$id];
			} else {
				$moduleVersions[$id] = $moduleInfo['version'];
			}
			// $moduleVersions[$id] = $moduleInfo['version'];
		}
	
		// delete the caches
		$this->wire('cache')->delete(self::moduleInfoCacheName);
		$this->wire('cache')->delete(self::moduleInfoCacheVerboseName);
		$this->wire('cache')->delete(self::moduleInfoCacheUninstalledName);
		
		$this->moduleInfoCache = array();
		$this->moduleInfoCacheVerbose = array();
		$this->moduleInfoCacheUninstalled = array();
	
		// save new moduleInfo cache
		$this->saveModuleInfoCache();

		$versionChanges = array();
		$newModules = array();
		// compare new moduleInfo versions with the previous ones, looking for changes
		foreach($this->moduleInfoCache as $id => $moduleInfo) {
			if(!isset($moduleVersions[$id])) {
				$newModules[] = $moduleInfo['name']; 
				continue;
			}
			if($moduleVersions[$id] != $moduleInfo['version']) {
				$fromVersion = $this->formatVersion($moduleVersions[$id]);
				$toVersion = $this->formatVersion($moduleInfo['version']);
				$versionChanges[] = "$moduleInfo[name]: $fromVersion => $toVersion";
				$this->modulesLastVersions[$id] = $moduleVersions[$id];
				if(strpos($moduleInfo['name'], 'Fieldtype') === 0) {
					// apply update now, to Fieldtype modules only (since they are loaded differently)
					$this->getModule($moduleInfo['name']);
				}
			}
		}
	
		// report on any changes
		if(count($newModules)) {
			$this->message(
				sprintf($this->_n('Detected %d new module: %s', 'Detected %d new modules: %s', count($newModules)), 
					count($newModules), '<pre>' . implode("\n", $newModules)) . '</pre>', 
				Notice::allowMarkup);
		}
		if(count($versionChanges)) {
			$this->message(
				sprintf($this->_n('Detected %d module version change', 'Detected %d module version changes', 
					count($versionChanges)), count($versionChanges)) . 
				' (' . $this->_('will be applied the next time each module is loaded') . '):' . 
				'<pre>' . implode("\n", $versionChanges) . '</pre>', 
				Notice::allowMarkup | Notice::debug);
		}
		
		$this->updateModuleVersionsCache();
	}

	/**
	 * Update the cache of queued module version changes
	 * 
	 */
	protected function updateModuleVersionsCache() {
		foreach($this->modulesLastVersions as $id => $version) {
			// clear out stale data, if present
			if(!in_array($id, $this->moduleIDs)) unset($this->modulesLastVersions[$id]);
		}
		if(count($this->modulesLastVersions)) {
			$this->wire('cache')->save(self::moduleLastVersionsCacheName, $this->modulesLastVersions, WireCache::expireReserved);
		} else {
			$this->wire('cache')->delete(self::moduleLastVersionsCacheName);
		}
	}

	/**
	 * Check the module version to make sure it is consistent with our moduleInfo
	 * 
	 * When not consistent, this triggers the moduleVersionChanged hook, which in turn
	 * triggers the $module->___upgrade($fromVersion, $toVersion) method. 
	 * 
	 * @param Module $module
	 * 
	 */
	protected function checkModuleVersion(Module $module) {
		$id = $this->getModuleID($module);
		$moduleInfo = $this->getModuleInfo($module);
		$lastVersion = isset($this->modulesLastVersions[$id]) ? $this->modulesLastVersions[$id] : null;
		if(!is_null($lastVersion)) { 
			if($lastVersion != $moduleInfo['version']) {
				$this->moduleVersionChanged($module, $lastVersion, $moduleInfo['version']);	
				unset($this->modulesLastVersions[$id]);
			}
			$this->updateModuleVersionsCache();
		}
	}

	/**
	 * Hook called when a module's version changes
	 * 
	 * This calls the module's ___upgrade($fromVersion, $toVersion) method. 
	 * 
	 * @param Module|_Module $module
	 * @param int|string $fromVersion
	 * @param int|string $toVersion
	 * 
	 */
	protected function ___moduleVersionChanged(Module $module, $fromVersion, $toVersion) {
		$moduleName = wireClassName($module, false);
		$moduleID = $this->getModuleID($module);
		$fromVersionStr = $this->formatVersion($fromVersion);
		$toVersionStr = $this->formatVersion($toVersion);
		$this->message($this->_('Upgrading module') . " ($moduleName: $fromVersionStr => $toVersionStr)");
		try {
			if(method_exists($module, '___upgrade')) {
				$module->upgrade($fromVersion, $toVersion);
			}
			unset($this->modulesLastVersions[$moduleID]);
		} catch(\Exception $e) {
			$this->error("Error upgrading module ($moduleName): " . $e->getMessage());
		}
	}

	/**
	 * Update module flags if any happen to differ from what's in the given moduleInfo
	 * 
	 * @param $moduleID
	 * @param array $info
	 * 
	 */
	protected function updateModuleFlags($moduleID, array $info) {
		
		$flags = (int) $this->getFlags($moduleID); 
		
		if($info['autoload']) {
			// module is autoload
			if(!($flags & self::flagsAutoload)) {
				// add autoload flag
				$this->setFlag($moduleID, self::flagsAutoload, true);
			}
			if(is_string($info['autoload'])) {
				// requires conditional flag
				// value is either: "function", or the conditional string (like key=value)
				if(!($flags & self::flagsConditional)) $this->setFlag($moduleID, self::flagsConditional, true);
			} else {
				// should not have conditional flag
				if($flags & self::flagsConditional) $this->setFlag($moduleID, self::flagsConditional, false);
			}
			
		} else if($info['autoload'] !== null) {
			// module is not autoload
			if($flags & self::flagsAutoload) {
				// remove autoload flag
				$this->setFlag($moduleID, self::flagsAutoload, false);
			}
			if($flags & self::flagsConditional) {
				// remove conditional flag
				$this->setFlag($moduleID, self::flagsConditional, false);
			}
		}
		
		if($info['singular']) {
			if(!($flags & self::flagsSingular)) $this->setFlag($moduleID, self::flagsSingular, true); 
		} else {
			if($flags & self::flagsSingular) $this->setFlag($moduleID, self::flagsSingular, false); 
		}

		// handle addFlag and removeFlag moduleInfo properties
		foreach(array(0 => 'removeFlag', 1 => 'addFlag') as $add => $flagsType) {
			if(empty($info[$flagsType])) continue;
			if($flags & $info[$flagsType]) {
				// already has the flags
				if(!$add) {
					// remove the flag(s)
					$this->setFlag($moduleID, $info[$flagsType], false);
				}
			} else {
				// does not have the flags
				if($add) {
					// add the flag(s)
					$this->setFlag($moduleID, $info[$flagsType], true);
				}
			}
		}
	}

	/**
	 * Save the module information cache
	 * 
	 */
	protected function saveModuleInfoCache() {
		
		if($this->debug) {
			static $n = 0;
			$this->message("saveModuleInfoCache (" . (++$n) . ")"); 
		}
		
		$this->moduleInfoCache = array();
		$this->moduleInfoCacheVerbose = array();
		$this->moduleInfoCacheUninstalled = array();
		
		$user = $this->wire('user'); 
		$languages = $this->wire('languages'); 
		$language = null;
		
		if($languages) {
			// switch to default language to prevent caching of translated title/summary data
			$language = $user->language; 
			try { 
				if($language && $language->id && !$language->isDefault()) $user->language = $languages->getDefault(); // save
			} catch(\Exception $e) {
				$this->trackException($e, false, true); 
			}
		}
	
		foreach(array(true, false) as $installed) { 
			
			$items = $installed ? $this : array_keys($this->installable);	
			
			foreach($items as $module) {
				
				$class = is_object($module) ? $module->className() : $module;
				$class = wireClassName($class, false);
				$info = $this->getModuleInfo($class, array('noCache' => true, 'verbose' => true));
				$moduleID = (int) $info['id']; // note ID is always 0 for uninstalled modules
				
				if(!empty($info['error'])) {
					if($this->debug) $this->warning("$class reported error: $info[error]"); 
					continue;
				}
				
				if(!$moduleID && $installed) {
					if($this->debug) $this->warning("No module ID for $class"); 
					continue;
				}
				
				if(!$this->debug) unset($info['id']); // no need to double store this property since it is already the array key
				
				if(is_null($info['autoload'])) {
					// module info does not indicate an autoload state
					$info['autoload'] = $this->isAutoload($module); 
					
				} else if(!is_bool($info['autoload']) && !is_string($info['autoload']) && wireIsCallable($info['autoload'])) {
					// runtime function, identify it only with 'function' so that it can be recognized later as one that
					// needs to be dynamically loaded
					$info['autoload'] = 'function';
				}
			
				if(is_null($info['singular'])) {
					$info['singular'] = $this->isSingular($module); 
				}
			
				if(is_null($info['configurable'])) {
					$info['configurable'] = $this->isConfigurable($module, false);
				}
				
				if($moduleID) $this->updateModuleFlags($moduleID, $info);
			
				if($installed) { 
					
					$verboseKeys = $this->moduleInfoVerboseKeys; 
					$verboseInfo = array();
					
					foreach($verboseKeys as $key) {
						if(!empty($info[$key])) $verboseInfo[$key] = $info[$key]; 
						unset($info[$key]); // remove from regular moduleInfo 
					}
					
					$this->moduleInfoCache[$moduleID] = $info; 
					$this->moduleInfoCacheVerbose[$moduleID] = $verboseInfo;
					
				} else {
					$this->moduleInfoCacheUninstalled[$class] = $info; 
				}
			}
		}
	
		$caches = array(
			self::moduleInfoCacheName => 'moduleInfoCache', 
			self::moduleInfoCacheVerboseName => 'moduleInfoCacheVerbose',
			self::moduleInfoCacheUninstalledName => 'moduleInfoCacheUninstalled',
		);
		
		foreach($caches as $cacheName => $varName) {
			$data = $this->$varName;
			foreach($data as $moduleID => $moduleInfo) {
				foreach($moduleInfo as $key => $value) {
					// remove unpopulated properties
					if($key == 'installed') {
						// no need to store an installed==true property
						if($value) unset($data[$moduleID][$key]);
						
					} else if($key == 'requires' && !empty($value) && !empty($data[$moduleID]['requiresVersions'])) {
						// requiresVersions has enough info to re-construct requires, so no need to store it
						unset($data[$moduleID][$key]);
						
					} else if(($key == 'created' && empty($value))
						|| ($value === 0 && ($key == 'singular' || $key == 'autoload' || $key == 'configurable'))
						|| ($value === null || $value === "" || $value === false) 
						|| (is_array($value) && !count($value))) {
						// no need to store these false, null, 0, or blank array properties
						unset($data[$moduleID][$key]);
						
					} else if(($key == 'namespace' && $value == "\\" . __NAMESPACE__ . "\\") || (!strlen(__NAMESPACE__) && empty($value))) {
						// no need to cache default namespace in module info
						unset($data[$moduleID][$key]);
						
					} else if($key == 'file') {
						// file property is cached elsewhere so doesn't need to be included in this cache
						unset($data[$moduleID][$key]);
					}
				}
			}
			$this->wire('cache')->save($cacheName, $data, WireCache::expireReserved); 
		}
	
		$this->log('Saved module info caches'); 
		
		if($languages && $language) $user->language = $language; // restore
	}

	/**
	 * Start a debug timer, only works when module debug mode is on ($this->debug)
	 * 
	 * @param $note
	 * @return int|null Returns a key for the debug timer
	 * 
	 */
	protected function debugTimerStart($note) {
		if(!$this->debug) return null;
		$key = count($this->debugLog);
		while(isset($this->debugLog[$key])) $key++;
		$this->debugLog[$key] = array(
			0 => Debug::timer("Modules$key"),
			1 => $note
		);
		return $key;
	}

	/**
	 * Stop a debug timer, only works when module debug mode is on ($this->debug)
	 *
	 * @param int $key The key returned by debugTimerStart
	 *
	 */
	protected function debugTimerStop($key) {
		if(!$this->debug) return;
		$log = $this->debugLog[$key];
		$timerKey = $log[0];
		$log[0] = Debug::timer($timerKey);
		$this->debugLog[$key] = $log;
		Debug::removeTimer($timerKey);
	}

	/**
	 * Return a log of module construct, init and ready times, active only when debug mode is on ($this->debug)
	 * 
	 * #pw-internal
	 *
	 * @return array
	 *
	 */
	public function getDebugLog() {
		return $this->debugLog;
	}

	/**
	 * Substitute one module for another, to be used only when $moduleName doesn't exist. 
	 * 
	 * #pw-internal
	 *
	 * @param string $moduleName Module class name that may need a substitute
	 * @param string $substituteName Module class name you want to substitute when $moduleName isn't found.
	 * 	Specify null to remove substitute.
	 *
	 */
	public function setSubstitute($moduleName, $substituteName = null) {
		if(is_null($substituteName)) {
			unset($this->substitutes[$moduleName]);
		} else {
			$this->substitutes[$moduleName] = $substituteName; 
		}
	}

	/**
	 * Substitute modules for other modules, to be used only when $moduleName doesn't exist.
	 * 
	 * This appends existing entries rather than replacing them. 
	 * 
	 * #pw-internal
	 *
	 * @param array $substitutes Array of module name => substitute module name
	 *
	 */
	public function setSubstitutes(array $substitutes) {
		$this->substitutes = array_merge($this->substitutes, $substitutes); 
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

		$class = $this->getModuleClass($module);
		static $classes = array();
		if(isset($classes[$class])) return 0; // already loaded
		$info = null;
		$config = $this->wire('config');
		$path = $config->paths->$class;
		$url = $config->urls->$class;
		$debug = $config->debug;
		$version = 0; 
		$cnt = 0;

		foreach(array('styles' => 'css', 'scripts' => 'js') as $type => $ext) {
			$fileURL = '';
			$modified = 0;
			$file = "$path$class.$ext";
			$minFile = "$path$class.min.$ext";
			if(!$debug && is_file($minFile)) {
				$fileURL = "$url$class.min.$ext";
				$modified = filemtime($minFile);
			} else if(is_file($file)) {
				$fileURL = "$url$class.$ext";
				$modified = filemtime($file);
			}
			if($fileURL) {
				if(!$version) {
					$info = $this->getModuleInfo($module, array('verbose' => false));
					$version = (int) isset($info['version']) ? $info['version'] : 0;
				}
				$config->$type->add("$fileURL?v=$version-$modified");
				$cnt++;
			}
		}
		
		$classes[$class] = true; 
		
		return $cnt;
	}

	/**
	 * Enables use of $modules('ModuleName')
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __invoke($key) {
		return $this->get($key);
	}

	/**
	 * Save to the modules log
	 * 
	 * #pw-internal
	 * 
	 * @param string $str Message to log
	 * @param string $moduleName
	 * @return WireLog
	 * 
	 */	
	public function log($str, $moduleName = '') {
		if(!in_array('modules', $this->wire('config')->logs)) return $this->___log();
		if(!is_string($moduleName)) $moduleName = (string) $moduleName; 
		if($moduleName && strpos($str, $moduleName) === false) $str .= " (Module: $moduleName)";
		return $this->___log($str, array('name' => 'modules')); 
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
		$this->log($text); 
		return parent::error($text, $flags); 
	}

	/**
	 * Compile and return the given file for module, if allowed to do so
	 * 
	 * #pw-internal
	 * 
	 * @param Module|string $moduleName
	 * @param string $file Optionally specify the module filename as an optimization
	 * @param string|null $namespace Optionally specify namespace as an optimization
	 * @return string|bool
	 * 
	 */
	public function compile($moduleName, $file = '', $namespace = null) {
		
		// if not given a file, track it down
		if(empty($file)) $file = $this->getModuleFile($moduleName);

		// don't compile when module compilation is disabled
		if(!$this->wire('config')->moduleCompile) return $file;
	
		// don't compile core modules
		if(strpos($file, $this->coreModulesDir) !== false) return $file;
	
		// if namespace not provided, get it
		if(is_null($namespace)) {
			if(is_object($moduleName)) {
				$className = $moduleName->className(true);
				$namespace = wireClassName($className, 1);
			} else if(is_string($moduleName) && strpos($moduleName, "\\") !== false) {
				$namespace = wireClassName($moduleName, 1);
			} else {
				$namespace = $this->getModuleNamespace($moduleName, array('file' => $file));
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
			$compiler = new FileCompiler(dirname($file));
			$compiledFile = $compiler->compile(basename($file));
			if($compiledFile) $file = $compiledFile;
		}
	
		return $file;
	}
	
}

