<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Info
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 * @property-read array $moduleInfoCache
 * @property-read array $moduleInfoCacheVerbose
 * @property-read array $moduleInfoCacheUninstalled
 * @property-read array $moduleInfoVerboseKeys
 * @property-read array $modulesLastVersions
 * 
 */
class ModulesInfo extends ModulesClass {
	
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
	 * Default namespace 
	 * 
	 */
	const defaultNamespace = "\\ProcessWire\\";


	protected $debug = false;

	/**
	 * @var Modules 
	 * 
	 */
	protected $modules;

	/**
	 * Cache of module information
	 *
	 */
	public $moduleInfoCache = array();

	/**
	 * Cache of module information (verbose text) including: summary, author, href, file, core
	 *
	 */
	public $moduleInfoCacheVerbose = array();

	/**
	 * Cache of uninstalled module information (verbose for uninstalled) including: summary, author, href, file, core
	 *
	 * Note that this one is indexed by class name rather than by ID (since uninstalled modules have no ID)
	 *
	 */
	public $moduleInfoCacheUninstalled = array();
	
	/**
	 * Last known versions of modules, for version change tracking
	 *
	 * @var array of ModuleName (string) => last known version (integer|string)
	 *
	 */
	protected $modulesLastVersions = array();
	
	/**
	 * Cache of namespace => path for unique module namespaces (memory cache only)
	 *
	 * @var array|null Becomes an array once populated
	 *
	 */
	protected $moduleNamespaceCache = null;


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
		'license',
		// 'languages',
	);

	/**
	 * Template for individual module info
	 * 
	 * @var array 
	 * 
	 */
	protected $infoTemplate = array(
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
		// verbose mode only: any translations supplied with the module
		// 'languages' => null,

		// other properties that may be present, but are optional, for Process modules:
		// 'nav' => array(), // navigation definition: see Process.php
		// 'useNavJSON' => bool, // whether the Process module provides JSON navigation
		// 'page' => array(), // page to create for Process module: see Process.php
		// 'permissionMethod' => string or callable // method to call to determine permission: see Process.php
	);

	/**
	 * Replacement/default values to use when property is null
	 * 
	 * @var array 
	 * 
	 */
	protected $infoNullReplacements = array(
		'autoload' => false, 
		'singular' => false, 
		'configurable' => false, 
		'core' => false, 
		'installed' => true, 
		'namespace' => "\\ProcessWire\\",
	);

	/**
	 * Is the module info cache empty?
	 * 
	 * @return bool
	 * 
	 */
	public function moduleInfoCacheEmpty() {
		return empty($this->moduleInfoCache);
	}

	/**
	 * Does the module info cache have an entry for given module ID?
	 * 
	 * @param int $moduleID
	 * @return bool
	 * 
	 */
	public function moduleInfoCacheHas($moduleID) {
		return isset($this->moduleInfoCache[$moduleID]);
	}
	
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
		if($verbose) {
			if(empty($this->moduleInfoCacheVerbose)) $this->loadModuleInfoCacheVerbose();
			$infos = &$this->moduleInfoCacheVerbose;
		} else {
			if(empty($this->moduleInfoCache)) $this->loadModuleInfoCache();
			$infos = &$this->moduleInfoCache;
		}
		if($moduleID === null) {
			// get all
			foreach($infos as $moduleID => $info) {
				if(empty($info)) {
					$info = array();
				} else if(is_array($info)) {
					continue;
				} else {
					$info = json_decode($info, true);
				}
				$infos[$moduleID] = $info;
			}
			return $infos;
		} else if($moduleID === 0) {
			return $property ? null : array();
		}
		if(!ctype_digit("$moduleID")) {
			// convert module name to module id
			$moduleID = $this->moduleID($moduleID);
			if(!$moduleID) return ($property ? null : array());
		}
		$moduleID = (int) $moduleID;
		if(!isset($infos[$moduleID])) return ($property ? null : array());
		$info = $infos[$moduleID];
		if(empty($info)) return ($property ? null : array());
		if(is_string($info)) {
			$info = json_decode($info, true);
			if(!is_array($info)) $info = array();
			$infos[$moduleID] = $info;
		}
		if($property) return isset($info[$property]) ? $info[$property] : null;
		return $info;
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
		return $this->moduleInfoCache($moduleID, $property, true);
	}

	/**
	 * Retrieve module info from ModuleName.info.json or ModuleName.info.php
	 *
	 * @param string $moduleName
	 * @return array
	 *
	 */
	public function getModuleInfoExternal($moduleName) {

		// ...attempt to load info by info file (Module.info.php or Module.info.json)
		$path = $this->modules->installableFile($moduleName);
		if(!empty($path)) {
			$path = dirname($path) . '/';
		} else {
			$path = $this->wire()->config->paths($moduleName);
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
	public function getModuleInfoInternal($module, $namespace = '') {

		$info = array();

		if($module instanceof ModulePlaceholder) {
			$this->modules->includeModule($module);
			$module = $module->className();
		}

		if($module instanceof Module) {
			if(method_exists($module, 'getModuleInfo')) {
				$info = $module::getModuleInfo();
			}

		} else if($module) {
			if(empty($namespace)) $namespace = $this->getModuleNamespace($module);
			$className = wireClassName($namespace . $module, true);
			if(!class_exists($className)) $this->modules->includeModule($module);
			if(is_callable("$className::getModuleInfo")) {
				$info = call_user_func(array($className, 'getModuleInfo'));
			}
		}

		return $info;
	}

	/**
	 * Retrieve module info for system properties: PHP or ProcessWire
	 *
	 * @param string $moduleName
	 * @param array $options
	 * @return array
	 *
	 */
	public function getModuleInfoSystem($moduleName, array $options = array()) {

		$info = array();
		
		if($moduleName === 'PHP') {
			$info['id'] = 0;
			$info['name'] = $moduleName;
			$info['title'] = $moduleName;
			$info['version'] = PHP_VERSION;

		} else if($moduleName === 'ProcessWire') {
			$info['id'] = 0;
			$info['name'] = $moduleName;
			$info['title'] = $moduleName;
			$info['version'] = $this->wire()->config->version;
			$info['namespace'] = self::defaultNamespace;
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

		if(empty($options['minify'])) $info = array_merge($this->infoTemplate, $info);

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
	 * @see self::getModuleInfoVerbose()
	 * @todo move all getModuleInfo methods to their own ModuleInfo class and break this method down further.
	 *
	 */
	public function getModuleInfo($class, array $options = array()) {

		if($class === 'info') return $this->infoTemplate;
		if($class === '*' || $class === 'all') return $this->getModuleInfoAll($options);
		if($class === 'ProcessWire' || $class === 'PHP') return $this->getModuleInfoSystem($class, $options);
		
		$defaults = array(
			'verbose' => false,
			'minify' => false,
			'noCache' => false,
			'noInclude' => false,
		);

		$options = array_merge($defaults, $options);
		$info = array();
		$module = $class;
		$fromCache = false;  // was the data loaded from cache?
		$moduleName = $this->moduleName($module);
		$moduleID = (string) $this->moduleID($moduleName); // typecast to string for cache
		
		if($module instanceof Module) {
			// module is an instance
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
				$info = $this->moduleInfoCache($moduleID);
				$fromCache = true;

			} else if(empty($options['noCache']) && $moduleID == 0) {
				// uninstalled module
				if(empty($this->moduleInfoCacheUninstalled)) {
					$this->loadModuleInfoCacheVerbose(true);
				}
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
					if(empty($info)) $info = $this->getModuleInfoInternal($moduleName, $namespace);

				} else {
					// module is not in memory, check external first, then internal
					$info = $this->getModuleInfoExternal($moduleName);
					if(empty($info)) {
						$installableFile = $this->modules->installableFile($moduleName);
						if($installableFile) {
							$this->modules->files->includeModuleFile($installableFile, $moduleName);
						}
						// info not available externally, attempt to locate it interally
						$info = $this->getModuleInfoInternal($moduleName, $namespace);
					}
				}
			}
		}

		if(!$fromCache && empty($info)) {
			return array_merge($this->infoTemplate, array(
				'title' => $module,
				'summary' => 'Inactive',
				'error' => 'Unable to locate module',
			));
		}

		$info['id'] = (int) $moduleID;
		
		if(!$options['minify']) $info = array_merge($this->infoTemplate, $info);

		if($fromCache) {
			// since cache is loaded at init(), this is the most common scenario

			if($options['verbose']) {
				if(empty($this->moduleInfoCacheVerbose)) {
					$this->loadModuleInfoCacheVerbose();
				}
				if(!empty($this->moduleInfoCacheVerbose[$moduleID])) {
					$info = array_merge($info, $this->moduleInfoCacheVerbose($moduleID));
				}
			}

			// populate defaults for properties omitted from cache 
			foreach($this->infoNullReplacements as $key => $value) {
				if($info[$key] === null) $info[$key] = $value;
			}
			
			if(!empty($info['requiresVersions'])) $info['requires'] = array_keys($info['requiresVersions']);
			if($moduleName === 'SystemUpdater') $info['configurable'] = 1; // fallback, just in case

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
			if($options['verbose']) {
				$info['versionStr'] = $this->modules->formatVersion($info['version']); // versionStr
			}
			
			$info['name'] = $moduleName; // module name

			// module configurable?
			$configurable = $this->modules->isConfigurable($moduleName, false);
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
			$createdDate = $this->modules->loader->createdDate($moduleID);
			if($createdDate) $info['created'] = strtotime($createdDate);
			
			$installableFile = $this->modules->installableFile($moduleName);
			$info['installed'] = $installableFile ? false : true;
			
			if(!$info['installed'] && !$info['created'] && $installableFile) {
				// uninstalled modules get their created date from the file or dir that they are in (whichever is newer)
				$pathname = $installableFile;
				$filemtime = @filemtime($pathname);
				if($filemtime === false) {
					$info['created'] = 0;
				} else {
					$dirname = dirname($pathname);
					$coreModulesPath = $this->modules->coreModulesPath;
					$dirmtime = substr($dirname, -7) == 'modules' || strpos($dirname, $coreModulesPath) !== false ? 0 : (int) filemtime($dirname);
					$info['created'] = $dirmtime > $filemtime ? $dirmtime : $filemtime;
				}
			}

			// namespace
			if($info['core']) {
				// default namespace, assumed since all core modules are in default namespace
				$info['namespace'] = self::defaultNamespace;
			} else {
				$info['namespace'] = $this->getModuleNamespace($moduleName, array(
					'file' => $info['file'],
					'noCache' => $options['noCache']
				));
			}

			if(!$options['verbose']) {
				foreach($this->moduleInfoVerboseKeys as $key) unset($info[$key]);
			}
		}

		if($info['namespace'] === null) $info['namespace'] = self::defaultNamespace;

		if(empty($info['created'])) {
			$createdDate = $this->modules->loader->createdDate($moduleID);
			if($createdDate) {
				$info['created'] = strtotime($createdDate);
			}
		}

		if($options['verbose']) {
			// the file property is not stored in the verbose cache, but provided as a verbose key
			$info['file'] = $this->modules->getModuleFile($moduleName);
			if($info['file']) $info['core'] = strpos($info['file'], $this->modules->coreModulesDir) !== false; // is it core?
		} else {
			// module info may still contain verbose keys with undefined values	
		}

		if($options['minify']) {
			// when minify, any values that match defaults from infoTemplate are removed
			if(!$options['verbose']) {
				foreach($this->moduleInfoVerboseKeys as $key) unset($info[$key]);
				foreach($info as $key => $value) {
					if(!array_key_exists($key, $this->infoTemplate)) continue;
					if($value !== $this->infoTemplate[$key]) continue;
					unset($info[$key]);
				}
			}
		}

		return $info;
	}

	/**
	 * Get info arrays for all modules indexed by module name
	 * 
	 * @param array $options See options for getModuleInfo() method
	 * @return array
	 * 
	 */
	public function getModuleInfoAll(array $options = array()) {
		$defaults = array(
			'verbose' => false, 
			'noCache' => false, 
			'minify' => true,
		);
		$options = array_merge($defaults, $options);
		if(!count($this->moduleInfoCache)) $this->loadModuleInfoCache();
		$modulesInfo = $this->moduleInfoCache();
		if($options['verbose']) {
			foreach($this->moduleInfoCacheVerbose() as $moduleID => $moduleInfoVerbose) {
				if($options['noCache']) {
					$modulesInfo[$moduleID] = $this->getModuleInfo($moduleID, $options);
				} else {
					$modulesInfo[$moduleID] = array_merge($modulesInfo[$moduleID], $moduleInfoVerbose);
				}
			}
		} else if($options['noCache']) {
			foreach(array_keys($modulesInfo) as $moduleID) {
				$modulesInfo[$moduleID] = $this->getModuleInfo($moduleID, $options);
			}
		}
		if(!$options['minify']) {
			foreach($modulesInfo as $moduleID => $info) {
				$modulesInfo[$moduleID] = array_merge($this->infoTemplate, $info);
			}
		}
		return $modulesInfo;
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
	 * @since 3.0.107
	 *
	 */
	public function getModuleInfoProperty($class, $property, array $options = array()) {

		if(empty($options['noCache'])) {
			// shortcuts where possible
			switch($property) {
				case 'namespace':
					return $this->getModuleNamespace($class);
				case 'requires':
					$v = $this->moduleInfoCache($class, 'requiresVersions'); // must be 'requiredVersions' here
					if(empty($v)) return array(); // early exit when known not to exist
					break; // fallback to calling getModuleInfo
			}
		}

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
	 * Load the module information cache
	 *
	 * #pw-internal
	 *
	 * @return bool
	 *
	 */
	public function loadModuleInfoCache() {

		if(empty($this->modulesLastVersions)) {
			$name = self::moduleLastVersionsCacheName;
			$data = $this->modules->getCache($name);
			if(is_array($data)) $this->modulesLastVersions = $data;
		}

		if(empty($this->moduleInfoCache)) {
			$name = self::moduleInfoCacheName;
			$data = $this->modules->getCache($name);
			// if module class name keys in use (i.e. ProcessModule) it's an older version of 
			// module info cache, so we skip over it to force its re-creation
			if(is_array($data) && !isset($data['ProcessModule'])) {
				$this->moduleInfoCache = $data;
				return true;
			}
			return false;
		}

		return true;
	}

	/**
	 * Load the module information cache (verbose info: summary, author, href, file, core)
	 *
	 * #pw-internal
	 *
	 * @param bool $uninstalled If true, it will load the uninstalled verbose cache.
	 * @return bool
	 *
	 */
	public function loadModuleInfoCacheVerbose($uninstalled = false) {

		$name = $uninstalled ? self::moduleInfoCacheUninstalledName : self::moduleInfoCacheVerboseName;

		$data = $this->modules->getCache($name);

		if($data) {
			if(is_array($data)) {
				if($uninstalled) {
					$this->moduleInfoCacheUninstalled = $data;
				} else {
					$this->moduleInfoCacheVerbose = $data;
				}
			}
			return true;
		}

		return false;
	}
	
	/**
	 * Save the module information cache
	 *
	 */
	public function saveModuleInfoCache() {

		if($this->debug) {
			static $n = 0;
			$this->message("saveModuleInfoCache (" . (++$n) . ")");
		}

		$this->moduleInfoCache = array();
		$this->moduleInfoCacheVerbose = array();
		$this->moduleInfoCacheUninstalled = array();

		$user = $this->wire()->user;
		$languages = $this->wire()->languages;
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
		
		$installableFiles = $this->modules->installableFiles;

		foreach(array(true, false) as $installed) {

			$items = $installed ? $this->modules : array_keys($installableFiles);

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
					$info['autoload'] = $this->modules->isAutoload($module);

				} else if(!is_bool($info['autoload']) && !is_string($info['autoload']) && wireIsCallable($info['autoload'])) {
					// runtime function, identify it only with 'function' so that it can be recognized later as one that
					// needs to be dynamically loaded
					$info['autoload'] = 'function';
				}

				if(is_null($info['singular'])) {
					$info['singular'] = $this->modules->isSingular($module);
				}

				if(is_null($info['configurable'])) {
					$info['configurable'] = $this->modules->isConfigurable($module, false);
				}

				if($moduleID) $this->modules->flags->updateModuleFlags($moduleID, $info);

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

		$defaultTrimNS = trim(self::defaultNamespace, "\\");

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

					} else if($key === 'namespace' && (empty($value) || trim($value, "\\") === $defaultTrimNS)) {
						// no need to cache default namespace in module info
						unset($data[$moduleID][$key]);

					} else if($key === 'file') {
						// file property is cached elsewhere so doesn't need to be included in this cache
						unset($data[$moduleID][$key]);
					}
				}
			}
			$this->modules->saveCache($cacheName, $data);
		}

		// $this->log('Saved module info caches'); 

		if($languages && $language) $user->language = $language; // restore
	}

	/**
	 * Clear the module information cache
	 *
	 * @param bool|null $showMessages Specify true to show message notifications
	 *
	 */
	public function clearModuleInfoCache($showMessages = false) {
		
		$sanitizer = $this->wire()->sanitizer;
		$config = $this->wire()->config;

		$versionChanges = array();
		$editLinks = array();
		$newModules = array();
		$moveModules = array();
		$missModules = array();

		// record current module versions currently in moduleInfo
		$moduleVersions = array();
		foreach($this->moduleInfoCache() as $id => $moduleInfo) {
			if(isset($this->modulesLastVersions[$id])) {
				$moduleVersions[$id] = $this->modulesLastVersions[$id];
			} else {
				$moduleVersions[$id] = $moduleInfo['version'];
			}
		}

		// delete the caches
		$this->modules->deleteCache(self::moduleInfoCacheName);
		$this->modules->deleteCache(self::moduleInfoCacheVerboseName);
		$this->modules->deleteCache(self::moduleInfoCacheUninstalledName);

		$this->moduleInfoCache = array();
		$this->moduleInfoCacheVerbose = array();
		$this->moduleInfoCacheUninstalled = array();

		// save new moduleInfo cache
		$this->saveModuleInfoCache();
		
		// compare new moduleInfo versions with the previous ones, looking for changes
		foreach($this->moduleInfoCache() as $id => $moduleInfo) {
			$moduleName = $moduleInfo['name'];
			if(!isset($moduleVersions[$id])) {
				if($this->modules->moduleID($moduleName)) {
					$moveModules[] = $moduleName;
				} else {
					$newModules[] = $moduleName;
				}
				continue;
			}
			if($moduleVersions[$id] != $moduleInfo['version']) {
				$fromVersion = $this->modules->formatVersion($moduleVersions[$id]);
				$toVersion = $this->modules->formatVersion($moduleInfo['version']);
				$versionChanges[$moduleName] = "$fromVersion => $toVersion: $moduleName";
				$editUrl = $this->modules->configs->getModuleEditUrl($moduleName, false) . '&upgrade=1';
				$this->modulesLastVersions[$id] = $moduleVersions[$id];
				if(strpos($moduleName, 'Fieldtype') === 0) {
					// apply update now, to Fieldtype modules only (since they are loaded differently)
					$this->modules->getModule($moduleName);
				} else {
					$editLinks[$moduleName] = "<a class='pw-modal' target='_blank' href='$editUrl'>" . 
						$sanitizer->entities1($this->_('Apply')) . "</a>";
				}
			}
		}

		foreach($this->modules->moduleIDs as $moduleName => $moduleID) {
			if(isset($this->moduleInfoCache[$moduleID])) {
				// module is present in moduleInfo
				if($this->modules->flags->hasFlag($moduleID, Modules::flagsNoFile)) {
					$file = $this->modules->getModuleFile($moduleName, array('fast' => false));
					if($file) {
						// remove flagsNoFile if file is found
						$this->modules->flags->setFlag($moduleID, Modules::flagsNoFile, false);
					}
				}
			} else {
				// module is missing moduleInfo
				$file = $this->modules->getModuleFile($moduleName, array('fast' => false));
				if(!$file) {
					$file = $this->modules->getModuleFile($moduleName, array('fast' => true, 'guess' => true));
					// add flagsNoFile if file cannot be located
					$missModules[$moduleName] = "$moduleName => $file";
					$editUrl = $this->modules->configs->getModuleEditUrl($moduleName, false) . '&missing=1';
					$editLinks[$moduleName] = "<a class='pw-modal' target='_blank' href='$editUrl'>" .
						$sanitizer->entities1($this->_('Edit')) . "</a>";
					$this->modules->flags->setFlag($moduleID, Modules::flagsNoFile, true);
				}
			}
		}

		$this->updateModuleVersionsCache();

		// report detected changes
		$reports = array(
			array(
				'label' => $this->_('Found %d new module(s):'),
				'items' => $newModules,
			),
			/*
			array(
				'label' => $this->_('Found %d moved module(s):'),
				'items' => $moveModules, 
			),
			*/
			array(
				'label' => $this->_('Found %d module(s) missing file:'),
				'items' => $missModules,
			),
			array(
				'label' => $this->_('Found %d module version changes (applied when each module is loaded):'),
				'items' => $versionChanges,
			),
		);
		
		$qty = 0;

		foreach($reports as $report) {
			if(!count($report['items'])) continue;
			if($showMessages) {
				$items = array();
				foreach($report['items'] as $moduleName => $item) {
					$item = $sanitizer->entities($item);
					if(isset($editLinks[$moduleName])) $item .= " - " . $editLinks[$moduleName];
					$items[] = $item;
				}
				$itemsStr = implode("\n", $items);
				$itemsStr = str_replace($config->paths->root, $config->urls->root, $itemsStr);
				$this->message(
					$sanitizer->entities1(sprintf($report['label'], count($items))) . 
					"<pre>$itemsStr</pre>",
					'icon-plug markup nogroup'
				);
				$qty++;
			}
			$this->log(
				sprintf($report['label'], count($report['items'])) . ' ' .
				implode(', ', $report['items'])
			);
		}
		if($qty) {
			/** @var JqueryUI $jQueryUI */
			$jQueryUI = $this->modules->getModule('JqueryUI');
			if($jQueryUI) $jQueryUI->use('modal');
		}
	}

	/**
	 * Update the cache of queued module version changes
	 *
	 */
	protected function updateModuleVersionsCache() {
		$moduleIDs = $this->modules->moduleIDs;
		foreach($this->modulesLastVersions as $id => $version) {
			// clear out stale data, if present
			if(!in_array($id, $moduleIDs)) unset($this->modulesLastVersions[$id]);
		}
		$this->modules->saveCache(self::moduleLastVersionsCacheName, $this->modulesLastVersions);
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
	public function checkModuleVersion(Module $module) {
		$id = (string) $this->modules->getModuleID($module);
		$moduleInfo = $this->getModuleInfo($module);
		if(!isset($this->modulesLastVersions[$id])) return;
		$lastVersion = $this->modulesLastVersions[$id];
		if($lastVersion === $moduleInfo['version']) return;
		// calling the one from $modules rather than $this is intentional
		$this->modules->moduleVersionChanged($module, $lastVersion, $moduleInfo['version']);
	}

	/**
	 * @param int|null $id
	 * @return string|null|array
	 * 
	 */
	public function modulesLastVersions($id = null) {
		if($id === null) return $this->modulesLastVersions;
		return isset($this->modulesLastVersions[$id]) ? $this->modulesLastVersions[$id] : null;
	}
	
	/**
	 * Module version changed 
	 * 
	 * This calls the module's ___upgrade($fromVersion, $toVersion) method.
	 *
	 * @param Module|_Module $module
	 * @param int|string $fromVersion
	 * @param int|string $toVersion
	 *
	 */
	public function moduleVersionChanged(Module $module, $fromVersion, $toVersion) {
		$moduleName = wireClassName($module, false);
		$moduleID = $this->modules->getModuleID($module);
		$fromVersionStr = $this->modules->formatVersion($fromVersion);
		$toVersionStr = $this->modules->formatVersion($toVersion);
		$this->message($this->_('Upgrading module') . " ($moduleName: $fromVersionStr => $toVersionStr)");
		try {
			if(method_exists($module, '___upgrade') || method_exists($module, 'upgrade')) {
				$module->upgrade($fromVersion, $toVersion);
			}
			unset($this->modulesLastVersions[$moduleID]);
			$this->updateModuleVersionsCache();
		} catch(\Exception $e) {
			$this->error("Error upgrading module ($moduleName): " . $e->getMessage());
		}
	}
	
	/**
	 * Get an array of all unique, non-default, non-root module namespaces mapped to directory names
	 *
	 * @return array
	 *
	 */
	public function getNamespaces() {
		$config = $this->wire()->config;
		if(!is_null($this->moduleNamespaceCache)) return $this->moduleNamespaceCache;
		$defaultNamespace = strlen(__NAMESPACE__) ? "\\" . __NAMESPACE__ . "\\" : "";
		$namespaces = array();
		foreach($this->moduleInfoCache() as /* $moduleID => */ $info) {
			if(!isset($info['namespace']) || $info['namespace'] === $defaultNamespace || $info['namespace'] === "\\") continue;
			$moduleName = $info['name'];
			$namespaces[$info['namespace']] = $config->paths($moduleName);
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
	 * 	- `noCache` (bool): Specify true to force reload namespace info directly from module file. (default=false)
	 *  - `noLoad` (bool): Specify true to prevent loading of file for namespace discovery. (default=false) Added 3.0.170
	 * @return null|string Returns namespace, or NULL if unable to determine. Namespace is ready to use in a string (i.e. has trailing slashes)
	 *
	 */
	public function getModuleNamespace($moduleName, $options = array()) {

		$defaults = array(
			'file' => null,
			'noLoad' => false,
			'noCache' => false,
		);

		$namespace = null;

		if(is_object($moduleName) || strpos($moduleName, "\\") !== false) {
			$className = is_object($moduleName) ? get_class($moduleName) : $moduleName;
			if(strpos($className, "ProcessWire\\") === 0) return "ProcessWire\\";
			if(strpos($className, "\\") === false) return "\\";
			$parts = explode("\\", $className);
			array_pop($parts);
			$namespace = count($parts) ? implode("\\", $parts) : "";
			$namespace = $namespace == "" ? "\\" : "\\$namespace\\";
			return $namespace;
		}

		if(empty($options['noCache'])) {
			$moduleID = $this->modules->getModuleID($moduleName);
			$info = isset($this->moduleInfoCache[$moduleID]) ? $this->moduleInfoCache($moduleID) : null;
			if($info) {
				if(isset($info['namespace'])) {
					if("$info[namespace]" === "1") return __NAMESPACE__ . "\\";
					return $info['namespace'];
				} else {
					// if namespace not present in info then use default namespace
					return __NAMESPACE__ . "\\";
				}
			}
		}

		$options = array_merge($defaults, $options);

		if(empty($options['file'])) {
			$options['file'] = $this->modules->getModuleFile($moduleName);
		}

		if(strpos($options['file'], $this->modules->coreModulesDir) !== false) {
			// all core modules use \ProcessWire\ namespace
			$namespace = strlen(__NAMESPACE__) ? __NAMESPACE__ . "\\" : "";
			return $namespace;
		}

		if(!$options['file'] || !file_exists($options['file'])) {
			return null;
		}

		if(empty($options['noLoad'])) {
			$namespace = $this->modules->files->getFileNamespace($options['file']);
		}

		return $namespace;
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
		if($namespace === 'ProcessWire') return false; // not unique module namespace
		if(is_null($this->moduleNamespaceCache)) $this->getNamespaces();
		$namespace = "\\" . trim($namespace, "\\") . "\\";
		return isset($this->moduleNamespaceCache[$namespace]) ? $this->moduleNamespaceCache[$namespace] : false;
	}

	public function __get($name) {
		switch($name) {
			case 'moduleInfoCache': return $this->moduleInfoCache;
			case 'moduleInfoCacheVerbose': return $this->moduleInfoCacheVerbose;
			case 'moduleInfoCacheUninstalled': return $this->moduleInfoCacheUninstalled;
			case 'moduleInfoVerboseKeys': return $this->moduleInfoVerboseKeys;
			case 'modulesLastVersions': return $this->modulesLastVersions;
		}
		return parent::__get($name);
	}
	
	public function getDebugData() {
		return array(
			'moduleInfoCache' => $this->moduleInfoCache,
			'moduleInfoCacheVerbose' => $this->moduleInfoCacheVerbose,
			'moduleInfoCacheUninstalled' => $this->moduleInfoCacheUninstalled,
			'modulesLastVersions' => $this->modulesLastVersions,
			'moduleNamespaceCache' => $this->moduleNamespaceCache
		);
	}
}
