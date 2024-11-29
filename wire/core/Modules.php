<?php namespace ProcessWire;

/**
 * ProcessWire Modules
 *
 * Loads and manages all runtime modules for ProcessWire
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
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
 * Note that when iterating, find(), or calling any other method that returns module(s), excepting get(), a ModulePlaceholder may be
 * returned rather than a real Module. ModulePlaceholders are used in instances when the module may or may not be needed at runtime
 * in order to save resources. As a result, anything iterating through these Modules should check to make sure it's not a ModulePlaceholder
 * before using it. If it's a ModulePlaceholder, then the real Module can be instantiated/retrieved by $modules->get($className).
 *
 * @method void refresh($showMessages = false) Refresh the cache that stores module files by recreating it
 * @method null|Module install($class, $options = array())
 * @method bool|int delete($class)
 * @method bool uninstall($class)
 * @method bool saveModuleConfigData($className, array $configData) Alias of saveConfig() method #pw-internal
 * @method bool saveConfig($class, $data, $value = null)
 * @method InputfieldWrapper|null getModuleConfigInputfields($moduleName, InputfieldWrapper $form = null)  #pw-internal
 * @method void moduleVersionChanged(Module $module, $fromVersion, $toVersion) #pw-internal
 * @method bool|string isUninstallable($class, $returnReason = false) hookable in 3.0.181+ #pw-internal 
 * 
 * @property-read array $installableFiles
 * @property-read string $coreModulesDir
 * @property-read string $coreModulesPath
 * @property-read string $siteModulesPath
 * @property-read array $moduleIDs
 * @property-read array $moduleNames
 * @property-read ModulesInfo $info
 * @property-read ModulesLoader $loader
 * @property-read ModulesFlags $flags
 * @property-read ModulesFiles $files
 * @property-read ModulesInstaller $installer
 * @property-read ModulesConfigs $configs
 * @property-read bool $refreshing
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
	 * Module where no file could be located
	 * 
	 */
	const flagsNoFile = 64;
	
	/**
	 * Indicates row is for Modules system cache use and not an actual module
	 *
	 * @since 3.0.218
	 *
	 */
	const flagsSystemCache = 8192;

	/**
	 * Array of modules that are not currently installed, indexed by className => filename
	 *
	 */
	protected $installableFiles = array(); 

	/**
	 * An array of module database IDs indexed by module name/class
	 *
	 */
	protected $moduleIDs = array();

	/**
	 * Array of module names/classes indexed by module ID
	 * 
	 */
	protected $moduleNames = array();

	/**
	 * Full system paths where modules are stored
	 * 
	 * index 0 must be the core modules path (/i.e. /wire/modules/)
	 * 
	 */
	protected $paths = array();

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
	protected $duplicates = null;

	/**
	 * Dir for core modules relative to root path, i.e. '/wire/modules/'
	 * 
	 * @var string
	 * 
	 */
	protected $coreModulesDir = '';

	/**
	 * Are we currently refreshing?
	 * 
	 * @var bool
	 * 
	 */
	protected $refreshing = false;

	/**
	 * Runtime caches
	 * 
	 * @var array 
	 * @since 3.0.218
	 * 
	 */
	protected $caches = array();

	/**
	 * @var ModulesInfo
	 * 
	 */
	protected $info;
	
	/**
	 * @var ModulesFlags
	 *
	 */
	protected $flags;
	
	/**
	 * @var ModulesFiles
	 *
	 */
	protected $files;
	
	/**
	 * @var ModulesConfigs
	 *
	 */
	protected $configs;
	
	/**
	 * @var ModulesInstaller|null
	 *
	 */
	protected $installer = null;
	
	/**
	 * @var ModulesLoader
	 *
	 */
	protected $loader = null;

	/**
	 * Construct the Modules
	 *
	 * @param string $path Core modules path (you may add other paths with addPath method)
	 *
	 */
	public function __construct($path) {
		parent::__construct();
		$this->nameProperty = 'className';
		$this->usesNumericKeys = false;
		$this->indexedByName = true; 
		$this->addPath($path); // paths[0] is always core modules path
	}

	/**
	 * Wired to API
	 * 
	 * #pw-internal
	 * 
	 */
	public function wired() {
		$this->coreModulesDir = '/' . $this->wire()->config->urls->data('modules');
		parent::wired();
		$this->info = new ModulesInfo($this);
		$this->flags = new ModulesFlags($this);
		$this->files = new ModulesFiles($this);
		$this->configs = new ModulesConfigs($this);
		$this->loader = new ModulesLoader($this);
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
		if($this->duplicates === null) $this->duplicates = $this->wire(new ModulesDuplicates());
		return $this->duplicates; 
	}
	
	/**
	 * Get the ModulesInstaller instance
	 *
	 * #pw-internal
	 *
	 * @return ModulesInstaller
	 *
	 */
	public function installer() {
		if($this->installer === null) $this->installer = new ModulesInstaller($this);
		return $this->installer;
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
		$this->loader->loadModulesTable();
		$this->info->loadModuleInfoCache();
		if(isset($this->paths[1])) {
			$this->loader->preloadModules($this->paths[1]); // site modules path
		}
		foreach($this->paths as $path) {
			$this->loader->loadPath($path);
		}
		$this->loader->loaded();
	}
	
	/**
	 * Trigger 'init' on all autoload modules, which calls their init() methods
	 *
	 * #pw-internal
	 *
	 */
	public function triggerInit() {
		$this->loader->triggerInit();
	}

	/**
	 * Trigger 'ready' on all autoload modules, which calls their ready() methods
	 *
	 * This is to indicate to them that the API environment is fully ready.
	 *
	 * #pw-internal
	 *
	 */
	public function triggerReady() {
		$this->loader->triggerReady();
	}

	/**********************************************************************************************
	 * WIREARRAY OVERRIDES
	 *
	 */

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
	 * @return string
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

	/**********************************************************************************************
	 * GETTING/LOADING MODULES
	 *
	 */

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
	 * Get the requested Module (with options)
	 * 
	 * This is the same as `$modules->get()` except that you can specify additional options to modify default behavior.
	 * These are the options you can specify in the `$options` array argument:
	 * 
	 *  - `noPermissionCheck` (bool): Specify true to disable module permission checks (and resulting exception). (default=false)
	 *  - `noInstall` (bool): Specify true to prevent a non-installed module from installing from this request. (default=false)
	 *  - `noInit` (bool): Specify true to prevent the module from being initialized or configured. (default=false). See `configOnly` as alternative.
	 *  - `noSubstitute` (bool): Specify true to prevent inclusion of a substitute module. (default=false)
	 *  - `noCache` (bool): Specify true to prevent module instance from being cached for later getModule() calls. (default=false)
	 *  - `noThrow` (bool): Specify true to prevent exceptions from being thrown on permission or fatal error. (default=false)
	 *  - `returnError` (bool): Return an error message (string) on error, rather than null. (default=false)
	 *  - `configOnly` (bool): Populate module config data but do not call its init() method. (default=false) 3.0.169+. Alternative to `noInit`.
	 *  - `configData` (array): Associative array of additional config data to populate to module. (default=[]) 3.0.169+
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
	
		$needsInit = false;
		$noInit = !empty($options['noInit']); // force cancel of Module::init() call?
		$initOptions = array(); // options for initModule() call
		$find = false; // try to find new location of module file?
		$error = '';
		
		if(empty($key)) {
			return empty($options['returnError']) ? null : "No module specified";
		}

		// check for optional module ID and convert to classname if found
		if(ctype_digit("$key")) {
			$moduleID = (int) $key;
			if(!isset($this->moduleNames[$moduleID])) {
				return empty($options['returnError']) ? null : "Unable to find module ID $moduleID";
			}
			$key = $this->moduleNames[$moduleID];
		} else {
			$moduleID = 0;
			$key = wireClassName($key, false);
		}
		
		$module = parent::get($key);
		
		if(!$module && !$moduleID) {
			// make non case-sensitive for module name ($key)
			$lowerKey = strtolower($key);
			foreach($this->moduleNames as $className) {
				if(strtolower($className) !== $lowerKey) continue;
				$module = parent::get($className);
				break;
			}
		}
		
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
				$find = true;
			}
		} else {
			$error = "Module '$key' is not present and not installable (noInstall=true)";
			$find = true;
		}
		
		if(!$module && $find) {
			// This is reached if module has moved elsewhere in file system, like from:
			// site/modules/ModuleName.module to site/modules/ModuleName/ModuleName.module
			// Code below tries to find the file to keep it working, but modules need Refresh.
			try {
				if($this->includeModule($key)) {
					$module = $this->newModule($key);
				}
			} catch(\Exception $e) {
				if(empty($options['noThrow'])) throw $e;
				$error .= ($error ? " - " : "Module '$key' - ") . $e->getMessage();
				return empty($options['returnError']) ? null : $error;
			}
		}
		
		if(!$module) {
			if(!$error) $error = "Unable to get module '$key'";
			return empty($options['returnError']) ? null : $error;
		}
		
		if(empty($options['noPermissionCheck'])) {
			// check that user has permission required to use module
			$page = $this->wire()->page;
			$user = $this->wire()->user;
			if(!$this->hasPermission($module, $user, $page)) {
				$error = $this->_('You do not have permission to execute this module') . ' - ' . 
					wireClassName($module) . " (page=$page)";
				if(empty($options['noThrow'])) throw new WirePermissionException($error);
				return empty($options['returnError']) ? null : $error;
			}
		}

		if($needsInit && $noInit) {
			// forced cancel of init() call
			$needsInit = false; 
		}
		
		if(!$needsInit && (!empty($options['configData']) || !empty($options['configOnly']))) {
			// if config data was supplied in options then we have to init()
			$needsInit = true;
			if(!empty($options['configData'])) $initOptions['configData'] = $options['configData'];
			// if forced noInit then tell initModule() to only config and not call Module::init()
			if($noInit || !empty($options['configOnly'])) $initOptions['configOnly'] = true;
		}

		// skip autoload modules because they have already been initialized in the load() method
		// unless they were just installed, in which case we need do init now
		if($needsInit) {
			// if the module is configurable, then load its config data
			// and set values for each before initializing the module
			$initOptions['clearSettings'] = false;
			$initOptions['throw'] = true;
			try {
				if(!$this->loader->initModule($module, $initOptions)) {
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
	 * Get the requested module and reset cache + install it if necessary.
	 *
	 * This is exactly the same as getModule() except that this one will rebuild the modules cache if
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
		$module = $this->getModule($key);
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
		return $this->loader->includeModule($module, $file);
	}
	
	/**
	 * Create a new Module instance
	 *
	 * Given a class name, return the constructed module or null
	 *
	 * #pw-internal
	 *
	 * @param string $className Module class name
	 * @param string $moduleName Optional module name only (no namespace)
	 * @return Module|null
	 *
	 */
	public function newModule($className, $moduleName = '') {

		if(!$moduleName) {
			$moduleName = wireClassName($className, false);
			$className = wireClassName($className, true);
		}

		$debugKey = $this->debug ? $this->debugTimerStart("newModule($moduleName)") : null;

		if(!class_exists($className, false)) {
			$result = $this->includeModule($moduleName);
			if(!$result) return null;
		}

		if(!class_exists($className, false)) {
			// attempt 2.x module in dedicated namespace or root namespace
			$className = $this->info->getModuleNamespace($moduleName) . $moduleName;
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
		return $this->loader->hasPermission($moduleName, $user, $page, $strict);
	}
	
	/**********************************************************************************************
	 * FINDER METHODS
	 *
	 */

	/**
	 * Find modules matching the given prefix (i.e. “Inputfield”)
	 * 
	 * By default this method returns module class names matching the given prefix. 
	 * To instead retrieve instantiated (ready-to-use) modules, specify boolean true
	 * for the second argument. Regardless of `$load` argument all returned arrays
	 * are indexed by module name.
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
	 * @param bool|int $load Specify one of the following (all indexed by module name):
	 *  - Boolean true to return array of instantiated modules.
	 *  - Boolean false to return array of module names (default).
	 *  - Integer 1 to return array of module info for each matching module.
	 *  - Integer 2 to return array of verbose module info for each matching module. 
	 *  - Integer 3 to return array of Module or ModulePlaceholder objects (whatever current state is). Added 3.0.146.
	 * @return array Returns array of module class names, module info arrays, or Module objects. In all cases, array indexes are class names.
	 * 
	 */
	public function findByPrefix($prefix, $load = false) {
		$results = array();
		foreach($this as $moduleName => $value) {
			if(stripos($moduleName, $prefix) !== 0) continue;
			if($load === false) {
				$results[$moduleName] = $moduleName;
			} else if($load === true) {
				$results[$moduleName] = $this->getModule($moduleName);
			} else if($load === 1) {
				$results[$moduleName] = $this->getModuleInfo($moduleName); 
			} else if($load === 2) {
				$results[$moduleName] = $this->getModuleInfoVerbose($moduleName);
			} else if($load === 3) {
				$results[$moduleName] = $value;
			} else {
				$results[$moduleName] = $moduleName;
			}
		}
		ksort($results);
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
		$keys = null;
		$results = array();
		$verbose = $load === 2;
		$properties = array();
		$has = '';
		
		if(is_array($selector)) {
			// find matching all values in array
			$keys = $selector;
			$properties = array_keys($keys);
		} else if(!ctype_alnum($selector) && Selectors::stringHasOperator($selector)) {
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
		
		if(!$verbose) {
			$moduleInfoVerboseKeys = $this->info->moduleInfoVerboseKeys;
			foreach($properties as $property) {
				if(!in_array($property, $moduleInfoVerboseKeys)) continue;
				$verbose = true;
				break;
			}
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

	/**********************************************************************************************
	 * INSTALLER METHODS
	 *
	 */

	/**
	 * Get an associative array [name => filename] for all modules that aren’t currently installed.
	 * 
	 * #pw-internal
	 *
	 * @return array Array of elements with $moduleName => $pathName
	 *
	 */
	public function getInstallable() {
		return $this->installableFiles; 
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
		
		$class = (string) $class;
		
		if(empty($class)) return false;

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
			if($requiredVersion !== null) {
				$currentVersion = $class === 'PHP' ? PHP_VERSION : $this->wire()->config->version; 
			}
		} else {
			$installed = isset($this->data[$class]); 
			if($installed && $requiredVersion !== null) {
				$info = $this->info->getModuleInfo($class); 
				$currentVersion = $info['version'];
			}
		}

		if($installed && $currentVersion !== null) {
			$installed = $this->installer()->versionCompare($currentVersion, $requiredVersion, $operator);
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
		return $this->installer()->isInstallable($class, $now);
	}

	/**
	 * Return installable file pathname for given class or null if not present
	 * 
	 * #pw-internal
	 * 
	 * @param string|null $class Module class name or omit to get all in array
	 * @param null|string|bool Specify string to set, boolean false to unset, otherwise omit
	 * @return string|null|array
	 * @since 3.0.219
	 * 
	 */
	public function installableFile($class = null, $setPath = null) {
		if($class === null) return $this->installableFiles;
		if($setPath !== null) {
			if($setPath === false) {
				unset($this->installableFiles[$class]);
			} else if(is_string($setPath)) {
				$this->installableFiles[$class] = $setPath;
			}
			return $setPath;
		}
		return isset($this->installableFiles[$class]) ? $this->installableFiles[$class] : null;
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
		return $this->installer()->install($class, $options);
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
	public function ___isUninstallable($class, $returnReason = false) {
		return $this->installer()->isUninstallable($class, $returnReason);
	}

	/**
	 * Returns whether the module can be deleted (have its files physically removed)
	 * 
	 * #pw-internal
	 *
	 * @param string|Module $class
	 * @param bool $returnReason If true, the reason why it can't be removed will be returned rather than boolean false.
	 * @return bool|string 
	 *
	 */
	public function isDeleteable($class, $returnReason = false) {
		return $this->installer()->isDeleteable($class, $returnReason);
	}

	/**
	 * Delete the given module, physically removing its files
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @return bool
	 * @throws WireException If module can't be deleted, exception will be thrown containing reason. 
	 *
	 */
	public function ___delete($class) {
		return $this->installer()->delete($class);
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
		return $this->installer()->uninstall($class);
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
		return $this->installer()->getUninstalls($class);
	}

	/**********************************************************************************************
	 * MODULE FLAGS
	 * 
	 */

	/**
	 * Get flags for the given module
	 * 
	 * #pw-internal
	 * 
	 * @param int|string|Module $class Module to add flag to
	 * @return int|false Returns integer flags on success, or boolean false on fail
	 * @deprecated
	 * 
	 */
	public function getFlags($class) {
		return $this->flags->getFlags($class);
	}

	/**
	 * Does module have flag?
	 *
	 * #pw-internal
	 *
	 * @param int|string|Module $class Module ID, class name or instance
	 * @param int $flag
	 * @return bool 
	 * @since 3.0.170
	 * @deprecated
	 *
	 */
	public function hasFlag($class, $flag) {
		return $this->flags->hasFlag($class, $flag);
	}

	/**
	 * Set module flags
	 * 
	 * #pw-internal
	 * 
	 * @param $class
	 * @param $flags
	 * @return bool
	 * @deprecated
	 * 
	 */
	public function setFlags($class, $flags) {
		return $this->flags->setFlags($class, $flags);
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
	 * @deprecated
	 * 
	 */
	public function setFlag($class, $flag, $add = true) {
		return $this->flags->setFlag($class, $flag, $add);
	}
	
	/**********************************************************************************************
	 * MODULE INFO
	 *
	 */
	
	/**
	 * Get data from the module info cache
	 *
	 * Returns array of module info if given a module ID or name.
	 * If module does not exist or info is not available, returns a blank array.
	 * If not given a module ID or name, it returns an array of all modules info.
	 * Returns value of property if given a property name, or null if not available.
	 *
	 * #pw-internal
	 *
	 * @param string|int|null $moduleID Module ID or name or omit to get info for all modules
	 * @param string $property
	 * @param bool $verbose
	 * @return array|mixed|null
	 * @since 3.0.218
	 *
	 */
	public function moduleInfoCache($moduleID = null, $property = '', $verbose = false) {
		return $this->info->moduleInfoCache($moduleID, $property, $verbose);
	}

	/**
	 * Get data from the verbose module info cache
	 *
	 * #pw-internal
	 *
	 * @param int|string|null $moduleID
	 * @param string $property
	 * @return array|mixed|null
	 *
	 */
	public function moduleInfoCacheVerbose($moduleID = null, $property = '') {
		return $this->info->moduleInfoCacheVerbose($moduleID, $property);
	}


	/**
	 * Get or set module ID from name/class
	 * 
	 * #pw-internal
	 * 
	 * @param string|Module $name
	 * @param int|null|false $setID Optionally set module ID or false to unset
	 * @return int
	 *
	 */
	public function moduleID($name, $setID = null) {
		if($name instanceof Module) $name = $name->className();
		if(strpos("$name", '\\') !== false) $name = wireClassName($name, false);
		if($setID !== null) {
			if($setID === false) {
				unset($this->moduleIDs[$name]);
			} else {
				$setID = (int) $setID;
				$this->moduleIDs[$name] = $setID;
			}
			return $setID;
		}
		if(ctype_digit("$name")) return (int) $name; 
		if(!is_string($name)) return $this->getModuleID($name);
		return isset($this->moduleIDs[$name]) ? $this->moduleIDs[$name] : 0;
	}

	/**
	 * Get or set module name from ID
	 * 
	 * #pw-internal
	 * 
	 * @param int|string|Module $id
	 * @param string|null $setName
	 * @return string
	 * 
	 */
	public function moduleName($id, $setName = null) {
		if($id instanceof Module) {
			$name = $id->className();
			if($setName === null) return $name;
			$id = $this->getModuleID($name);
		} else if(!ctype_digit("$id")) {
			if(strpos("$id", '\\') !== false) $id = wireClassName($id, false);
			if($setName === null && is_string($id)) return $id;
			$id = $this->getModuleID($id);
		}
		$id = (int) $id;
		if($setName) {
			$this->moduleNames[$id] = (string) $setName;
			return $setName;
		}
		return isset($this->moduleNames[$id]) ? $this->moduleNames[$id] : '';
	}

	/**
	 * Returns the database ID of a given module class, or 0 if not found
	 * 
	 * @param string|int|Module $class Module, module name or ID
	 * @return int
	 *
	 */
	public function getModuleID($class) {
		
		$id = 0;
		
		if(ctype_digit("$class")) return (int) $class;
		if(isset($this->moduleIDs["$class"])) return (int) $this->moduleIDs["$class"];
		
		if(is_object($class)) {
			if(!$class instanceof Module) return 0; // class is not a  module
			$class = $this->getModuleClass($class);
		} else if(strpos("$class", '\\') !== false) {
			$class = wireClassName($class, false);
		}
		
		if(isset($this->moduleIDs["$class"])) return (int) $this->moduleIDs["$class"];

		foreach($this->info->moduleInfoCache as $key => $info) {	
			if(is_string($info)) {
				$info = $this->info->moduleInfoCache($key); // json to array
			}
			if($info['name'] === $class) {
				$id = (int) $key;
				$this->moduleIDs[$class] = $id;
				break;
			}
		}
		
		return $id; 
	}

	/**
	 * Returns the module's class/name, optionally with namespace
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

		} else if(ctype_digit("$module")) {
			$className = $this->moduleName((int) $module);

		} else if(is_string($module)) {
			
			if(strpos($module, "\\") !== false) {
				$namespace = wireClassName($module, 1);
				$className = wireClassName($module, false);
			}

			// remove extensions if they were included in the module name
			if(strpos($module, '.') !== false) {
				$module = basename(basename($module, '.php'), '.module');
			}
		
			if(isset($this->data[$module])) {
				$className = $module;
			} else if(array_key_exists($module, $this->moduleIDs)) {
				$className = $module;
			} else if(array_key_exists($module, $this->installableFiles)) {
				$className = $module;
			}
		}
		
		if(!$className) return false;
		
		if($withNamespace) {
			if($namespace) {
				$className = "$namespace\\$className";
			} else {
				$className = $this->info->getModuleNamespace($className) . $className;
			}
		}
		return $className;
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
	 * properties may be present but with empty values:
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
	 * On error, an `error` index in returned array contains error message. You can also identify errors 
	 * such as a non-existing module by the returned module info having an `id` index of `0`
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
	 * @return array Associative array of module information. 
	 *  - On error, an `error` index is also populated with an error message. 
	 *  - When requesting a module that does not exist its `id` value will be `0` and its `name` will be blank.
	 * @see Modules::getModuleInfoVerbose()
	 * @todo move all getModuleInfo methods to their own ModuleInfo class and break this method down further.
	 *	
	 */
	public function getModuleInfo($class, array $options = array()) {
		return $this->info->getModuleInfo($class, $options);
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
	 * @see Modules::getModuleInfo()
	 *
	 */
	public function getModuleInfoVerbose($class, array $options = array()) {
		return $this->info->getModuleInfoVerbose($class, $options);
	}

	/**
	 * Get just a single property of module info
	 * 
	 * @param Module|string $class Module instance or module name
	 * @param string $property Name of property to get
	 * @param array $options Additional options (see getModuleInfo method for options)
	 * @return mixed|null Returns value of property or null if not found
	 * @since 3.0.107
	 * 
	 */
	public function getModuleInfoProperty($class, $property, array $options = array()) {
		return $this->info->getModuleInfoProperty($class, $property, $options);
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
		return $this->info->getNamespaces();
	}
	
	/**
	 * Get the namespace for the given module
	 *
	 * #pw-internal
	 *
	 * @param string|Module $moduleName
	 * @param array $options
	 * 	- `file` (string): Known module path/file, as an optimization.
	 * 	- `noCache` (bool): Specify true to force reload namespace info directly from module file. (default=false)
	 *  - `noLoad` (bool): Specify true to prevent loading of file for namespace discovery. (default=false) Added 3.0.170
	 * @return null|string Returns namespace, or NULL if unable to determine. Namespace is ready to use in a string (i.e. has trailing slashes)
	 *
	 */
	public function getModuleNamespace($moduleName, $options = array()) {
		return $this->info->getModuleNamespace($moduleName, $options);
	}
	
	/**
	 * Is the given namespace a unique recognized module namespace? If yes, returns the path to it. If not, returns boolean false.
	 *
	 * #pw-internal
	 *
	 * @param string $namespace
	 * @return bool|string
	 *
	 */
	public function getNamespacePath($namespace) {
		return $this->info->getNamespacePath($namespace);
	}
	
	/**********************************************************************************************
	 * MODULE CONFIG
	 *
	 */

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
		return $this->configs->getConfig($class, $property);
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
		return $this->configs->saveConfig($class, $data, $value);
	}

	/**
	 * Is the given module interactively configurable?
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
	 */
	public function isConfigurable($class, $useCache = true) {
		return $this->configs->isConfigurable($class, $useCache);
	}
	
	/**
	 * Indicates whether module accepts config settings, whether interactively or API only
	 * 
	 * - Returns false if module does not accept config settings. 
	 * - Returns integer `30` if module accepts config settings but is not interactively configurable.
	 * - Returns true, int or string if module is interactively configurable, see `Modules::isConfigurable()` return values.
	 * 
	 * #pw-internal
	 * 
	 * @param string|Module $class
	 * @param bool $useCache
	 * @return bool|int|string
	 * @since 3.0.179
	 *
	 */
	public function isConfigable($class, $useCache = true) {
		return $this->configs->isConfigable($class, $useCache);
	}

	/**
	 * Alias of isConfigurable() for backwards compatibility
	 * 
	 * #pw-internal
	 * 
	 * @param $className
	 * @param bool $useCache
	 * @return bool|string|int
	 * @deprecated Please use isConfigurable() method instead
	 * 
	 */
	public function isConfigurableModule($className, $useCache = true) {
		return $this->configs->isConfigurable($className, $useCache); 
	}

	/**
	 * Alias of saveConfig() for backwards compatibility
	 * 
	 * 
	 * #pw-internal
	 * 
	 * @param $className
	 * @param array $configData
	 * @return bool
	 * @deprecated Please use saveConfig() method instead
	 * 
	 */
	public function ___saveModuleConfigData($className, array $configData) {
		return $this->saveConfig($className, $configData);
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
	public function ___getModuleConfigInputfields($moduleName, ?InputfieldWrapper $form = null) {
		return $this->configs->getModuleConfigInputfields($moduleName, $form);
	}

	/**
	 * Alias of getConfig() for backwards compatibility
	 *
	 * #pw-internal
	 *
	 * @param string|Module $className
	 * @return array
	 * @deprecated Please use getConfig() instead
	 *
	 */
	public function getModuleConfigData($className) {
		return $this->configs->getConfig($className);
	}

	/**
	 * Return the URL where the module can be edited, configured or uninstalled
	 *
	 * If module is not installed, it returns URL to install the module.
	 *
	 * #pw-group-configuration
	 *
	 * @param string|Module $className
	 * @param bool $collapseInfo
	 * @return string
	 *
	 */
	public function getModuleEditUrl($className, $collapseInfo = true) {
		return $this->configs->getModuleEditUrl($className, $collapseInfo);
	}

	/**
	 * Get URL where an administrator can install given module name
	 *
	 * If module is already installed, it returns the URL to edit the module.
	 *
	 * #pw-group-configuration
	 *
	 * @param string $className
	 * @return string
	 * @since 3.0.216
	 *
	 */
	public function getModuleInstallUrl($className) {
		return $this->installer()->getModuleInstallUrl($className);
	}


	/************************************************************************************************
	 * TOOLS
	 * 
	 */
	
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
		$info = $this->info->getModuleInfo($module); 
		if(isset($info['singular'])) return $info['singular'];
		if(is_object($module)) {
			if(method_exists($module, 'isSingular')) return $module->isSingular();
		} else {
			// singular status can't be determined if module not installed and not specified in moduleInfo
			if(isset($this->installableFiles[$module])) return null;
			$this->loader->includeModule($module); 
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
		
		$info = $this->info->getModuleInfo($module); 
		$autoload = null;
		
		if(isset($info['autoload'])) {
			// if autoload is a string (selector) or callable, then we flag it as autoload
			if(is_string($info['autoload']) || wireIsCallable($info['autoload'])) return "conditional"; 
			$autoload = $info['autoload'];
			
		} else if(!is_object($module)) {
			if(isset($this->installableFiles[$module])) {
				// module is not installed
				// we are not going to be able to determine if this is autoload or not
				$flags = $this->flags->getFlags($module); 
				if($flags !== null) {
					$autoload = $flags & Modules::flagsAutoload;
				} else {
					// unable to determine
					return null;
				}
			} else {
				// include for method exists call
				$this->loader->includeModule($module);
				$module = wireClassName($module, true);
				$module = $this->wire(new $module());
			}
		}
	
		if($autoload === null && is_object($module) && method_exists($module, 'isAutoload')) {
			/** @var Module $module */
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
	public function isInitialized($set = null) {
		if($set !== null) $this->initialized = $set ? true : false;
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
		} else if(isset($this->installableFiles[$moduleName])) {
			$isModule = true;
		} else {
			$isModule = false;
		}
		
		if($isModule && $namespace) {
			$actualNamespace = $this->info->getModuleNamespace($moduleName);
			if(trim("$namespace", '\\') != trim("$actualNamespace", '\\')) {
				$isModule = false;
			}
		}
		
		return $isModule;
	}

	/**
	 * Refresh the modules cache
	 * 
	 * This forces the modules file and information cache to be re-created. 
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param bool $showMessages Show notification messages about what was found? (default=false) 3.0.172+
	 *
	 */
	public function ___refresh($showMessages = false) {
		if($this->wire()->config->systemVersion < 6) return;
		$this->refreshing = true;
		$this->info->clearModuleInfoCache($showMessages);
		$this->loader->loadModulesTable();
		foreach($this->paths as $path) $this->files->findModuleFiles($path, false); 
		foreach($this->paths as $path) $this->loader->loadPath($path);
		if($this->duplicates()->numNewDuplicates() > 0) $this->duplicates()->updateDuplicates(); // PR#1020
		$this->loader->loaded();
		$this->refreshing = false;
	}

	/**
	 * Alias of refresh() method for backwards compatibility
	 * 
	 * #pw-internal
	 * 
	 * @deprecated Use refresh() method instead
	 * 
	 */
	public function resetCache() {
		$this->refresh();
	}
	
	/*************************************************************************************
	 * DEPENDENCIES
	 *
	 */

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
		return $this->installer()->getRequiredBy($class, $uninstalled, $installs);
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
		return $this->installer()->getRequires($class, $onlyMissing, $versions);
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
		return $this->installer()->versionCompare($currentVersion, $requiredVersion, $operator);
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
		return $this->installer()->getRequiresForInstall($class);
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
		return $this->installer()->getRequiresForInstall($class);
	}
	
	/**
	 * Return array of dependency errors for given module name
	 * 
	 * #pw-internal
	 *
	 * @param string $moduleName
	 * @return array If no errors, array will be blank. If errors, array will be of strings (error messages)
	 *
	 */
	public function getDependencyErrors($moduleName) {
		return $this->installer()->getDependencyErrors($moduleName);
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
		return $this->files->findMissingModules();
	}

	/**
	 * Remove entry for module from modules table 
	 * 
	 * #pw-internal
	 * 
	 * @param string|int $class Module class or ID
	 * @return bool
	 * @since 3.0.170
	 * 
	 */
	public function removeModuleEntry($class) {
		$database = $this->wire()->database;
		if(ctype_digit("$class")) {
			$query = $database->prepare('DELETE FROM modules WHERE id=:id LIMIT 1'); 
			$query->bindValue(':id', (int) $class, \PDO::PARAM_INT);
		} else {
			$query = $database->prepare('DELETE FROM modules WHERE class=:class LIMIT 1');
			$query->bindValue(':class', $class, \PDO::PARAM_STR);
		}
		$result = $query->execute() ? $query->rowCount() > 0 : false;
		$query->closeCursor();
		return $result;	
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
	 * Hook called when a module's version changes
	 * 
	 * This calls the module's ___upgrade($fromVersion, $toVersion) method. 
	 * 
	 * #pw-internal
	 * 
	 * @param Module|_Module $module
	 * @param int|string $fromVersion
	 * @param int|string $toVersion
	 * 
	 */
	public function ___moduleVersionChanged(Module $module, $fromVersion, $toVersion) {
		$this->info->moduleVersionChanged($module, $fromVersion, $toVersion);
	}
	
	/*************************************************************************************
	 * SUBSTITUTES
	 *
	 */

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
	 * Attempt to find a substitute for moduleName and return module if found or null if not
	 * 
	 * #pw-internal
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

	/*************************************************************************************
	 * FILES
	 *
	 */
	
	/**
	 * Get the path + filename (or optionally URL) for this module
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
		return $this->files->getModuleFile($class, $options);
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
		return $this->files->loadModuleFileAssets($module);
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
		return $this->files->getModuleLanguageFiles($module);
	}
	
	/**
	 * Get the namespace used in the given .php or .module file
	 *
	 * #pw-internal
	 *
	 * @param string $file
	 * @return string Includes leading and trailing backslashes where applicable
	 * @deprecated
	 *
	 */
	public function getFileNamespace($file) {
		return $this->files->getFileNamespace($file);
	}

	/*************************************************************************************
	 * DEBUG AND CLASS SUPPORT
	 *
	 */

	/**
	 * Enables use of $modules('ModuleName')
	 *
	 * @param string $key
	 * @return Module|null
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
	 * @param array|string $options Specify module name (string) or options array
	 * @return WireLog
	 * 
	 */	
	public function log($str, $options = array()) {
		$moduleName = is_string($options) ? $options : '';
		if(!is_array($options)) $options = array();
		if(!in_array('modules', $this->wire()->config->logs)) return $this->___log();
		if(!is_string($moduleName)) $moduleName = (string) $moduleName; 
		if($moduleName && strpos($str, $moduleName) === false) $str .= " (Module: $moduleName)";
		$options['name'] = 'modules';
		return $this->___log($str, $options); 
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
		if(is_string($text)) $this->log($text); 
		return parent::error($text, $flags); 
	}
	
	/**
	 * Start a debug timer, only works when module debug mode is on ($this->debug)
	 * 
	 * #pw-internal
	 *
	 * @param $note
	 * @return int|null Returns a key for the debug timer
	 *
	 */
	public function debugTimerStart($note) {
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
	 * #pw-internal
	 *
	 * @param int $key The key returned by debugTimerStart
	 *
	 */
	public function debugTimerStop($key) {
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
	
	public function getDebugData() {
		return array(
			'installableFiles' => $this->installableFiles,
			'moduleIDs' => $this->moduleIDs,
			'moduleNames' => $this->moduleNames,
			'paths' => $this->paths,
			'substitutes' => $this->substitutes,
			'caches' => $this->caches,
			'loader' => $this->loader->getDebugData(),
			'configs' => $this->configs->getDebugData(),
			'installer' => $this->installer()->getDebugData(),
			'files' => $this->files->getDebugData(),
			'flags' => $this->flags->getDebugData(),
			'info' => $this->info->getDebugData(),
			'duplicates' => $this->duplicates()->getDebugData()
		);
	}


	/*************************************************************************************
	 * CACHES
	 * 
	 */

	/**
	 * Set a runtime memory cache
	 * 
	 * @param string $name
	 * @param mixed $setValue
	 * @return bool|array|mixed|null
	 * 
	 */
	public function memcache($name, $setValue = null) {
		if($setValue) {
			$this->caches[$name] = $setValue;
			return true;
		}
		return isset($this->caches[$name]) ? $this->caches[$name] : null;
	}

	/**
	 * Save cache
	 * 
	 * #pw-internal
	 *
	 * @param string $cacheName
	 * @param string|array $data
	 * @return bool
	 * @since 3.0.218
	 *
	 */
	public function saveCache($cacheName, $data) {
		$database = $this->wire()->database;
		if(!$this->saveCacheReady) {
			$this->saveCacheReady = true;
			$col = $database->getColumns('modules', 'data');
			if(strtolower($col['type']) === 'text') {
				try {
					// increase size of data column for cache storage in 3.0.218
					$database->exec("ALTER TABLE modules MODIFY `data` MEDIUMTEXT NOT NULL");
					$this->message("Updated modules.data to mediumtext", Notice::debug);
				} catch(\Exception $e) {
					$this->error($e->getMessage());
				}
			}
		}
		$cache = $this->wire()->cache;
		if($cache) $cache->save($cacheName, $data, WireCache::expireReserved);
		if(is_array($data)) $data = json_encode($data);
		$sql = "INSERT INTO modules SET class=:name, data=:data, flags=:flags ON DUPLICATE KEY UPDATE data=VALUES(data)";
		$query = $database->prepare($sql);
		$query->bindValue(':name', ".$cacheName");
		$query->bindValue(':data', $data);
		$query->bindValue(':flags', Modules::flagsSystemCache);
		return $query->execute();
	}

	protected $saveCacheReady = false;

	/**
	 * Get cache
	 * 
	 * #pw-internal
	 *
	 * @param string $cacheName
	 * @return string|array|bool
	 * @since 3.0.218
	 *
	 */
	public function getCache($cacheName) {
		$data = null;
		if(isset($this->caches[$cacheName])) {
			$data = $this->caches[$cacheName];
			unset($this->caches[$cacheName]);
		}
		if(empty($data)) {
			$sql = "SELECT data FROM modules WHERE class=:name";
			$query = $this->wire()->database->prepare($sql);
			$query->bindValue(':name', ".$cacheName");
			$query->execute();
			$data = $query->fetchColumn();
			$query->closeCursor();
		}
		if(empty($data)) {
			// fallback to $cache API var, necessary only temporarily
			$data = $this->wire()->cache->get($cacheName);
			if($data) return $data;
		}
		if(is_string($data) && (strpos($data, '{') === 0 || strpos($data, '[') === 0)) {
			$data = json_decode($data, true);
		}
		return $data;
	}

	/**
	 * Delete cache by name
	 * 
	 * #pw-internal
	 *
	 * @param string $cacheName
	 * @since 3.0.218
	 *
	 */
	public function deleteCache($cacheName) {
		$this->wire()->cache->delete($cacheName);
		unset($this->caches[$cacheName]);
	}

	/**
	 * Direct read-only properties
	 * 
	 * @param string $name
	 * @return mixed
	 * 
	 */
	public function __get($name) {
		switch($name) {
			case 'loader': return $this->loader;
			case 'info': return $this->info;
			case 'configs': return $this->configs;
			case 'flags': return $this->flags;
			case 'files': return $this->files;
			case 'installableFiles': return $this->installableFiles;
			case 'coreModulesDir': return $this->coreModulesDir;
			case 'coreModulesPath': return $this->paths[0];
			case 'siteModulesPath': return isset($this->paths[1]) ? $this->paths[1] : '';
			case 'moduleIDs': return $this->moduleIDs;
			case 'moduleNames': return $this->moduleNames;
			case 'refreshing': return $this->refreshing;
		}
		return parent::__get($name);
	}
}
