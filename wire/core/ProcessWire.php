<?php namespace ProcessWire;

require_once(__DIR__ . '/boot.php');

/**
 * ProcessWire API Bootstrap
 *
 * #pw-summary Represents an instance of ProcessWire connected with a set of API variables. 
 * #pw-summary-instances Methods for managing ProcessWire instances. Note that most of these methods are static. 
 * #pw-use-constants
 * #pw-use-constructor
 * #pw-body = 
 * This class boots a ProcessWire instance. The current ProcessWire instance is represented by the `$wire` API variable. 
 * ~~~~~
 * // To create a new ProcessWire instance
 * $wire = new ProcessWire('/server/path/', 'https://hostname/url/');
 * ~~~~~
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 * 
 * @method init()
 * @method ready()
 * @method finished()
 * 
 * 
 */
class ProcessWire extends Wire {

	/**
	 * Major version number
	 * 
	 */
	const versionMajor = 3;
	
	/**
	 * Minor version number
	 * 
	 */
	const versionMinor = 0;
	
	/**
	 * Reversion revision number
	 * 
	 */
	const versionRevision = 132;

	/**
	 * Version suffix string (when applicable)
	 * 
	 */
	const versionSuffix = '';

	/**
	 * Minimum required index.php version, represented by the PROCESSWIRE define
	 * 
	 */
	const indexVersion = 300;

	/**
	 * Minimum required .htaccess file version
	 * 
	 */
	const htaccessVersion = 300;

	/**
	 * Status when system is booting
	 * 
	 */
	const statusBoot = 0;

	/**
	 * Status when system and modules are initializing
	 * 
	 */
	const statusInit = 2;

	/**
	 * Systus when system, $page and API variables are ready
	 * 
	 */
	const statusReady = 4;

	/**
	 * Status when the current $page’s template file is being rendered
	 * 
	 */
	const statusRender = 8;

	/**
	 * Status when the request has been fully delivered
	 * 
	 */
	const statusFinished = 16;

	/**
	 * Status when the request failed due to an Exception or 404
	 * 
	 */
	const statusFailed = 1024; 

	/**
	 * Whether debug mode is on or off
	 * 
	 * @var bool
	 * 
	 */
	protected $debug = false;

	/**
	 * Fuel manages ProcessWire API variables
	 * 
	 * This will replace the static $fuel from the Wire class in PW 3.0.
	 * Currently it is just here as a placeholder.
	 *
	 * @var Fuel|null
	 *
	 */
	protected $fuel = null;

	/**
	 * Saved path, for includeFile() method
	 * 
	 * @var string
	 * 
	 */
	protected $pathSave = '';

	/**
	 * @var SystemUpdater|null
	 * 
	 */
	protected $updater = null;

	/**
	 * ID for this instance of ProcessWire
	 * 
	 * @var int
	 * 
	 */
	protected $instanceID = 0;

	/**
	 * @var WireShutdown
	 * 
	 */
	protected $shutdown = null;
	
	
	/**
	 * Create a new ProcessWire instance
	 * 
	 * ~~~~~
	 * // A. Current directory assumed to be root of installation
	 * $wire = new ProcessWire(); 
	 * 
	 * // B: Specify a Config object as returned by ProcessWire::buildConfig()
	 * $wire = new ProcessWire($config); 
	 * 
	 * // C: Specify where installation root is
	 * $wire = new ProcessWire('/server/path/');
	 * 
	 * // D: Specify installation root path and URL
	 * $wire = new ProcessWire('/server/path/', '/url/');
	 * 
	 * // E: Specify installation root path, scheme, hostname, URL
	 * $wire = new ProcessWire('/server/path/', 'https://hostname/url/'); 
	 * ~~~~~
	 * 
	 * @param Config|string|null $config May be any of the following: 
	 *  - A Config object as returned from ProcessWire::buildConfig(). 
	 *  - A string path to PW installation.
	 *  - You may optionally omit this argument if current dir is root of PW installation. 
	 * @param string $rootURL URL or scheme+host to installation. 
	 *  - This is only used if $config is omitted or a path string.
	 *  - May also include scheme & hostname, i.e. "http://hostname.com/url" to force use of scheme+host.
	 *  - If omitted, it is determined automatically. 
	 * @throws WireException if given invalid arguments
 	 *
	 */ 
	public function __construct($config = null, $rootURL = '/') {
	
		if(empty($config)) $config = getcwd();
		if(is_string($config)) $config = self::buildConfig($config, $rootURL);
		if(!$config instanceof Config) throw new WireException("No configuration information available");
		
		// this is reset in the $this->setConfig() method based on current debug mode
		ini_set('display_errors', true);
		error_reporting(E_ALL | E_STRICT);

		$config->setWire($this);
		
		$this->debug = $config->debug; 
		$this->instanceID = self::addInstance($this);
		$this->setWire($this);
		
		$this->fuel = new Fuel();
		$this->fuel->set('wire', $this, true);

		$classLoader = $this->wire('classLoader', new WireClassLoader($this), true);
		$classLoader->addNamespace((strlen(__NAMESPACE__) ? __NAMESPACE__ : "\\"), PROCESSWIRE_CORE_PATH);

		$this->wire('hooks', new WireHooks($this, $config), true);

		$this->setConfig($config);
		$this->shutdown = $this->wire(new WireShutdown($config));
		$this->setStatus(self::statusBoot);
		$this->load($config);
		
		if(self::getNumInstances() > 1) {
			// this instance is not handling the request and needs a mock $page API var and pageview
			/** @var ProcessPageView $view */
			$view = $this->wire('modules')->get('ProcessPageView');
			$view->execute(false);
		}
	}

	public function __toString() {
		$str = $this->className() . " ";
		$str .= self::versionMajor . "." . self::versionMinor . "." . self::versionRevision; 
		if(self::versionSuffix) $str .= " " . self::versionSuffix;
		if(self::getNumInstances() > 1) $str .= " #$this->instanceID";
		return $str;
	}

	/**
	 * Populate ProcessWire's configuration with runtime and optional variables
 	 *
	 * @param Config $config
 	 *
	 */
	protected function setConfig(Config $config) {

		$this->wire('config', $config, true); 
		$this->wire($config->paths);
		$this->wire($config->urls);
		
		// If debug mode is on then echo all errors, if not then disable all error reporting
		if($config->debug) {
			error_reporting(E_ALL | E_STRICT);
			ini_set('display_errors', 1);
		} else {
			error_reporting(0);
			ini_set('display_errors', 0);
		}

		ini_set('date.timezone', $config->timezone);
		ini_set('default_charset','utf-8');

		if(!$config->templateExtension) $config->templateExtension = 'php';
		if(!$config->httpHost) $config->httpHost = $this->getHttpHost($config); 

		if($config->https === null) {
			$config->https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
				|| (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
				|| (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https'); // AWS LOAD BALANCER
		}
		
		$config->ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest');
		$config->cli = (!isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (isset($_SERVER['argc']) && $_SERVER['argc'] > 0 && is_numeric($_SERVER['argc']))));
		$config->modal = empty($_GET['modal']) ? false : abs((int) $_GET['modal']); 
		
		$version = self::versionMajor . "." . self::versionMinor . "." . self::versionRevision; 
		$config->version = $version;
		$config->versionName = trim($version . " " . self::versionSuffix);
		
		// $config->debugIf: optional setting to determine if debug mode should be on or off
		if($config->debugIf && is_string($config->debugIf)) {
			$debugIf = trim($config->debugIf);
			$ip = $config->sessionForceIP;
			if(empty($ip)) $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
			if(strpos($debugIf, '/') === 0 && !empty($ip)) {
				$debugIf = (bool) @preg_match($debugIf, $ip); // regex IPs
			} else if(is_callable($debugIf)) {
				$debugIf = $debugIf(); // callable function to determine debug mode for us 
			} else if(!empty($ip)) {
				$debugIf = $debugIf === $ip; // exact IP match
			} else {
				$debugIf = false;
			}
			unset($ip);
			$config->debug = $debugIf;
		}

		if($config->useFunctionsAPI) {
			$file = $config->paths->core . 'FunctionsAPI.php';
			/** @noinspection PhpIncludeInspection */
			include_once($file);
		}

		if($config->installed >= 1547136020) {
			// installations Jan 10, 2019 and onwards:
			// make the __('text') translation function return entity encoded text, whether translated or not
			__(true, 'entityEncode', true);
		}

		// check if noHTTPS option is using conditional hostname
		if($config->noHTTPS && $config->noHTTPS !== true) {
			$noHTTPS = $config->noHTTPS;
			$httpHost = $config->httpHost;
			if(is_string($noHTTPS)) $noHTTPS = array($noHTTPS);
			if(is_array($noHTTPS)) {
				$config->noHTTPS = false;
				foreach($noHTTPS as $host) {
					if($host === $httpHost) {
						$config->noHTTPS = true;
						break;
					}
				}
			}
		}
	}

	/**
	 * Safely determine the HTTP host
	 * 
	 * @param Config $config
	 * @return string
	 *
	 */
	protected function getHttpHost(Config $config) {

		$httpHosts = $config->httpHosts; 
		$port = isset($_SERVER['SERVER_PORT']) ? (int) $_SERVER['SERVER_PORT'] : 80;
		$port = ($port === 80 || $port === 443 ? "" : ":$port");
		$host = '';

		if(is_array($httpHosts) && count($httpHosts)) {
			// validate from an allowed whitelist of http hosts
			$key = false; 
			if(isset($_SERVER['SERVER_NAME'])) {
				$key = array_search(strtolower($_SERVER['SERVER_NAME']) . $port, $httpHosts, true); 
			}
			if($key === false && isset($_SERVER['HTTP_HOST'])) {
				$key = array_search(strtolower($_SERVER['HTTP_HOST']), $httpHosts, true); 
			}
			if($key === false) {
				// no valid host found, default to first in whitelist
				$host = reset($httpHosts);
			} else {
				// found a valid host
				$host = $httpHosts[$key];
			}

		} else {
			// pull from server_name or http_host and sanitize
			
			if(isset($_SERVER['SERVER_NAME']) && $host = $_SERVER['SERVER_NAME']) {
				// no whitelist available, so defer to server_name
				$host .= $port; 

			} else if(isset($_SERVER['HTTP_HOST']) && $host = $_SERVER['HTTP_HOST']) {
				// fallback to sanitized http_host if server_name not available
				// note that http_host already includes port if not 80
				$host = $_SERVER['HTTP_HOST'];
			}

			// sanitize since it did not come from a whitelist
			if(!preg_match('/^[-a-zA-Z0-9.:]+$/D', $host)) $host = ''; 
		}

		return $host; 
	}

	/**
	 * Load’s ProcessWire using the supplied Config and populates all API fuel
	 * 
	 * #pw-internal
 	 *
	 * @param Config $config
	 * @throws WireDatabaseException|WireException on fatal error
 	 *
	 */
	public function load(Config $config) {
		
		if($this->debug) {
			Debug::timer('boot'); 
			Debug::timer('boot.load'); 
		}

		$this->wire('urls', $config->urls); // shortcut API var
		$this->wire('log', new WireLog(), true); 
		$this->wire('notices', new Notices(), true); 
		$this->wire('sanitizer', new Sanitizer()); 
		$this->wire('datetime', new WireDateTime());
		$this->wire('files', new WireFileTools());
		$this->wire('mail', new WireMailTools());

		try {
			/** @noinspection PhpUnusedLocalVariableInspection */
			$database = $this->wire('database', WireDatabasePDO::getInstance($config), true);
			/** @noinspection PhpUnusedLocalVariableInspection */
			$db = $this->wire('db', new DatabaseMysqli($config), true);
		} catch(\Exception $e) {
			// catch and re-throw to prevent DB connect info from ever appearing in debug backtrace
			$this->trackException($e, true, 'Unable to load WireDatabasePDO');
			throw new WireDatabaseException($e->getMessage()); 
		}
	
		/** @var WireCache $cache */
		$cache = $this->wire('cache', new WireCache(), true); 
		$cache->preload($config->preloadCacheNames); 
		
		$modules = null;
		try { 		
			if($this->debug) Debug::timer('boot.load.modules');
			$modules = $this->wire('modules', new Modules($config->paths->modules), true);
			$modules->addPath($config->paths->siteModules);
			$modules->setSubstitutes($config->substituteModules); 
			$modules->init();
			if($this->debug) Debug::saveTimer('boot.load.modules');
		} catch(\Exception $e) {
			$this->trackException($e, true, 'Unable to load Modules');
			if(!$modules) throw new WireException($e->getMessage()); 	
			$this->error($e->getMessage()); 
		}
		$this->updater = $modules->get('SystemUpdater'); 
		if(!$this->updater) {
			$modules->resetCache();
			$this->updater = $modules->get('SystemUpdater');
		}

		$fieldtypes = $this->wire('fieldtypes', new Fieldtypes(), true);
		$fields = $this->wire('fields', new Fields(), true);
		$fieldgroups = $this->wire('fieldgroups', new Fieldgroups(), true);
		$templates = $this->wire('templates', new Templates($fieldgroups, $config->paths->templates), true); 
		$pages = $this->wire('pages', new Pages($this), true);

		$this->initVar('fieldtypes', $fieldtypes);
		$this->initVar('fields', $fields);
		$this->initVar('fieldgroups', $fieldgroups);
		$this->initVar('templates', $templates);
		$this->initVar('pages', $pages); 
	
		if($this->debug) Debug::timer('boot.load.permissions'); 
		if(!$t = $templates->get('permission')) throw new WireException("Missing system template: 'permission'");
		/** @noinspection PhpUnusedLocalVariableInspection */
		$permissions = $this->wire('permissions', new Permissions($this, $t, $config->permissionsPageID), true); 
		if($this->debug) Debug::saveTimer('boot.load.permissions');

		if($this->debug) Debug::timer('boot.load.roles'); 
		if(!$t = $templates->get('role')) throw new WireException("Missing system template: 'role'");
		/** @noinspection PhpUnusedLocalVariableInspection */
		$roles = $this->wire('roles', new Roles($this, $t, $config->rolesPageID), true); 
		if($this->debug) Debug::saveTimer('boot.load.roles');

		if($this->debug) Debug::timer('boot.load.users'); 
		$users = $this->wire('users', new Users($this, $config->userTemplateIDs, $config->usersPageIDs), true); 
		if($this->debug) Debug::saveTimer('boot.load.users'); 

		// the current user can only be determined after the session has been initiated
		$session = $this->wire('session', new Session($this), true); 
		$this->initVar('session', $session);
		$this->wire('user', $users->getCurrentUser()); 
		$input = $this->wire('input', new WireInput(), true); 
		if($config->wireInputLazy) $input->setLazy(true);

		// populate admin URL before modules init()
		$config->urls->admin = $config->urls->root . ltrim($pages->getPath($config->adminRootPageID), '/');

		if($this->debug) Debug::saveTimer('boot.load', 'includes all boot.load timers');
		$this->setStatus(self::statusInit);
	}

	/**
	 * Initialize the given API var
	 * 
	 * @param string $name
	 * @param Fieldtypes|Fields|Fieldgroups|Templates|Pages|Session $value
	 * 
	 */
	protected function initVar($name, $value) {
		if($this->debug) Debug::timer("boot.load.$name");
		if($name != 'session') $value->init();
		if($this->debug) Debug::saveTimer("boot.load.$name"); 
	}

	/**
	 * Set the system status to one of the ProcessWire::status* constants
	 * 
	 * This also triggers init/ready functions for modules, when applicable.
	 * 
	 * @param $status
	 * 
	 */
	public function setStatus($status) {
		$config = $this->wire('config');
		// don't re-trigger if this state has already been triggered
		if($config->status >= $status) return;
		$config->status = $status;
		$sitePath = $this->wire('config')->paths->site;
		
		if($status == self::statusInit) {
			$this->init();
			$this->includeFile($sitePath . 'init.php');
			
		} else if($status == self::statusReady) {
			$this->ready();
			if($this->debug) Debug::saveTimer('boot', 'includes all boot timers');
			$this->includeFile($sitePath . 'ready.php');
			
		} else if($status == self::statusFinished) {
			$this->includeFile($sitePath . 'finished.php');
			$this->finished();
		}
	}

	/**
	 * Hookable init for anyone that wants to hook immediately before any autoload modules initialized or after all modules initialized
	 * 
	 * #pw-hooker
	 * 
	 */
	protected function ___init() {
		if($this->debug) Debug::timer('boot.modules.autoload.init'); 
		$this->wire('modules')->triggerInit();
		if($this->debug) Debug::saveTimer('boot.modules.autoload.init');
	}

	/**
	 * Hookable ready for anyone that wants to hook immediately before any autoload modules ready or after all modules ready
	 * 
	 * #pw-hooker
	 *
	 */
	protected function ___ready() {
		if($this->debug) Debug::timer('boot.modules.autoload.ready'); 
		$this->wire('modules')->triggerReady();
		$this->updater->ready();
		unset($this->updater);
		if($this->debug) Debug::saveTimer('boot.modules.autoload.ready'); 
	}

	/**
	 * Hookable ready for anyone that wants to hook when the request is finished
	 * 
	 * #pw-hooker
	 *
	 */
	protected function ___finished() {
		
		$config = $this->wire('config');
		$session = $this->wire('session');
		$cache = $this->wire('cache'); 
		$profiler = $this->wire('profiler');
		
		if($session) $session->maintenance();
		if($cache) $cache->maintenance();
		if($profiler) $profiler->maintenance();

		if($config->templateCompile) {
			$compiler = new FileCompiler($this->wire('config')->paths->templates);
			$compiler->maintenance();
		}
		
		if($config->moduleCompile) {
			$compiler = new FileCompiler($this->wire('config')->paths->siteModules);
			$compiler->maintenance();
		}
		
	}

	/**
	 * Set a new API variable
	 * 
	 * Alias of $this->wire(), but for setting only, for syntactic convenience.
	 * i.e. $this->wire()->set($key, $value); 
	 * 
	 * @param string $key API variable name to set
	 * @param Wire|mixed $value Value of API variable
	 * @param bool $lock Whether to lock the value from being overwritten
	 * @return $this
	 */
	public function set($key, $value, $lock = false) {
		$this->wire($key, $value, $lock);
		return $this;
	}
	
	public function __get($key) {
		if($key == 'shutdown') return $this->shutdown;
		if($key == 'instanceID') return $this->instanceID;
		return parent::__get($key);
	}

	/**
	 * Include a PHP file, giving it all PW API varibles in scope
	 * 
	 * File is executed in the directory where it exists.
	 * 
	 * @param string $file Full path and filename
	 * @return bool True if file existed and was included, false if not.
	 * 
	 */
	protected function includeFile($file) {
		if(!file_exists($file)) return false;
		$file = $this->wire('files')->compile($file, array('skipIfNamespace' => true));
		$this->pathSave = getcwd();
		chdir(dirname($file));
		$fuel = $this->fuel->getArray();
		extract($fuel);
		/** @noinspection PhpIncludeInspection */
		include($file);
		chdir($this->pathSave);
		return true; 
	}
	
	public function __call($method, $arguments) {
		if(method_exists($this, "___$method")) return parent::__call($method, $arguments); 
		$value = $this->__get($method);
		if(is_object($value)) return call_user_func_array(array($value, '__invoke'), $arguments); 
		return parent::__call($method, $arguments);
	}

	/**
	 * Get an API variable
	 * 
	 * #pw-internal
	 * 
	 * @param string $name Optional API variable name
	 * @return mixed|null|Fuel
	 * 
	 */
	public function fuel($name = '') {
		if(empty($name)) return $this->fuel;
		return $this->fuel->$name;
	}
	
	/*** MULTI-INSTANCE *************************************************************************************/
	
	/**
	 * Instances of ProcessWire
	 *
	 * @var array
	 *
	 */
	static protected $instances = array();

	/**
	 * Current ProcessWire instance
	 * 
	 * @var null
	 * 
	 */
	static protected $currentInstance = null;

	/**
	 * Instance ID of this ProcessWire instance
	 * 
	 * #pw-group-instances
	 * 
	 * @return int
	 * 
	 */
	public function getProcessWireInstanceID() {
		return $this->instanceID;
	}

	/**
	 * Add a ProcessWire instance and return the instance ID
	 * 
	 * #pw-group-instances
	 * 
	 * @param ProcessWire $wire
	 * @return int
	 * 
	 */
	protected static function addInstance(ProcessWire $wire) {
		$id = 0;
		while(isset(self::$instances[$id])) $id++;
		self::$instances[$id] = $wire;
		return $id;
	}

	/**
	 * Get all ProcessWire instances
	 * 
	 * #pw-group-instances
	 * 
	 * @return array
	 * 
	 */
	public static function getInstances() {
		return self::$instances;
	}

	/**
	 * Return number of instances
	 * 
	 * #pw-group-instances
	 * 
	 * @return int
	 * 
	 */
	public static function getNumInstances() {
		return count(self::$instances);
	}

	/**
	 * Get a ProcessWire instance by ID
	 * 
	 * #pw-group-instances
	 * 
	 * @param int|null $instanceID Omit this argument to return the current instance
	 * @return null|ProcessWire
	 * 
	 */
	public static function getInstance($instanceID = null) {
		if(is_null($instanceID)) return self::getCurrentInstance();
		return isset(self::$instances[$instanceID]) ? self::$instances[$instanceID] : null;
	}
	
	/**
	 * Get the current ProcessWire instance
	 * 
	 * #pw-group-instances
	 * 
	 * @return ProcessWire|null
	 * 
	 */
	public static function getCurrentInstance() {
		if(is_null(self::$currentInstance)) {
			$wire = reset(self::$instances);
			if($wire) self::setCurrentInstance($wire);
		}
		return self::$currentInstance;
	}

	/**
	 * Set the current ProcessWire instance
	 * 
	 * #pw-group-instances
	 * 
	 * @param ProcessWire $wire
	 * 
	 */
	public static function setCurrentInstance(ProcessWire $wire) {
		self::$currentInstance = $wire;	
	}

	/**
	 * Remove a ProcessWire instance
	 * 
	 * #pw-group-instances
	 * 
	 * @param ProcessWire $wire
	 * 
	 */
	public static function removeInstance(ProcessWire $wire) {
		foreach(self::$instances as $key => $instance) {
			if($instance === $wire) {
				unset(self::$instances[$key]);
				if(self::$currentInstance === $wire) self::$currentInstance = null;
				break;
			}
		}
	}

	/**
	 * Get root path, check it, and optionally auto-detect it if not provided
	 * 
	 * @param bool|string $rootPath Root path if already known, in which case we’ll just modify as needed
	 * @return string
	 * 
	 */
	protected static function getRootPath($rootPath = '') {
		
		if(strpos($rootPath, '..') !== false) {
			$rootPath = realpath($rootPath);
		}

		if(empty($rootPath) && !empty($_SERVER['SCRIPT_FILENAME'])) {
			// first try to determine from the script filename
			$parts = explode(DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_FILENAME']);
			array_pop($parts); // most likely: index.php
			$rootPath = implode('/', $parts) . '/';
			if(!file_exists($rootPath . 'wire/core/ProcessWire.php')) $rootPath = '';
		}
		
		if(empty($rootPath)) {
			// if unable to determine from script filename, attempt to determine from current file
			$parts = explode(DIRECTORY_SEPARATOR, __FILE__);
			$parts = array_slice($parts, 0, -3); // removes "ProcessWire.php", "core" and "wire"
			$rootPath = implode('/', $parts) . '/';
		}
		
		if(DIRECTORY_SEPARATOR != '/') {
			$rootPath = str_replace(DIRECTORY_SEPARATOR, '/', $rootPath);
		}

		return $rootPath; 
	}

	/**
	 * Static method to build a Config object for booting ProcessWire
	 * 
	 * @param string $rootPath Path to root of installation where ProcessWire's index.php file is located.
	 * @param string $rootURL Should be specified only for secondary ProcessWire instances. 
	 *   May also include scheme & hostname, i.e. "http://hostname.com/url" to force use of scheme+host. 
	 * @param array $options Options to modify default behaviors (experimental): 
	 *  - `siteDir` (string): Name of "site" directory in $rootPath that contains site's config.php, no slashes (default="site").
	 * @return Config
	 * 
	 */
	public static function buildConfig($rootPath = '', $rootURL = null, array $options = array()) {
	
		$rootPath = self::getRootPath($rootPath);
		$httpHost = '';
		$scheme = '';
		$siteDir = isset($options['siteDir']) ? $options['siteDir'] : 'site';
		$cfg = array('dbName' => '');
		
		if($rootURL && strpos($rootURL, '://')) {
			// rootURL is specifying scheme and hostname
			list($scheme, $httpHost) = explode('://', $rootURL);
			if(strpos($httpHost, '/')) {
				list($httpHost, $rootURL) = explode('/', $httpHost, 2);	
				$rootURL = "/$rootURL";
			} else {
				$rootURL = '/';
			}
			$scheme = strtolower($scheme);
			$httpHost = strtolower($httpHost);
		}
		
		$rootPath = rtrim($rootPath, '/');
		$_rootURL = $rootURL;
		if(is_null($rootURL)) $rootURL = '/';
		
		// check what rootPath is referring to
		if(strpos($rootPath, "/$siteDir")) {
			$parts = explode('/', $rootPath);
			$testDir = array_pop($parts);
			if(($testDir === $siteDir || strpos($testDir, 'site-') === 0) && is_file("$rootPath/config.php")) {
				// rootPath was given as a /site/ directory rather than root directory
				$rootPath = implode('/', $parts); // remove siteDir from rootPath
				$siteDir = $testDir; // set proper siteDir
			}
		} 

		if(isset($_SERVER['HTTP_HOST'])) {
			$host = $httpHost ? $httpHost : strtolower($_SERVER['HTTP_HOST']);

			// when serving pages from a web server
			if(is_null($_rootURL)) $rootURL = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\") . '/';
			$realScriptFile = empty($_SERVER['SCRIPT_FILENAME']) ? '' : realpath($_SERVER['SCRIPT_FILENAME']);
			$realIndexFile = realpath($rootPath . "/index.php");

			// check if we're being included from another script and adjust the rootPath accordingly
			$sf = empty($realScriptFile) ? '' : dirname($realScriptFile);
			$f = dirname($realIndexFile);
			if($sf && $sf != $f && strpos($sf, $f) === 0) {
				$x = rtrim(substr($sf, strlen($f)), '/');
				if(is_null($_rootURL)) $rootURL = substr($rootURL, 0, strlen($rootURL) - strlen($x));
			}
			unset($sf, $f, $x);
		
			// when internal is true, we are not being called by an external script
			$cfg['internal'] = strtolower($realIndexFile) == strtolower($realScriptFile);

		} else {
			// when included from another app or command line script
			$cfg['internal'] = false;
			$host = '';
		}
		
		// Allow for an optional /index.config.php file that can point to a different site configuration per domain/host.
		$indexConfigFile = $rootPath . "/index.config.php";

		if(is_file($indexConfigFile) 
			&& !function_exists("\\ProcessWire\\ProcessWireHostSiteConfig")
			&& !function_exists("\\ProcessWireHostSiteConfig")) {
			// optional config file is present in root
			$hostConfig = array();
			/** @noinspection PhpIncludeInspection */
			@include($indexConfigFile);
			if(function_exists("\\ProcessWire\\ProcessWireHostSiteConfig")) {
				$hostConfig = ProcessWireHostSiteConfig();
			} else if(function_exists("\\ProcessWireHostSiteConfig")) {
				$hostConfig = \ProcessWireHostSiteConfig();
			}
			if($host && isset($hostConfig[$host])) {
				$siteDir = $hostConfig[$host];
			} else if(isset($hostConfig['*'])) {
				$siteDir = $hostConfig['*']; // default override
			}
		}

		// other default directories
		$sitePath = $rootPath . "/$siteDir/";
		$wireDir = "wire";
		$coreDir = "$wireDir/core";
		$assetsDir = "$siteDir/assets";
		$adminTplDir = 'templates-admin';
	
		// create new Config instance
		$cfg['urls'] = new Paths($rootURL);
		$cfg['urls']->data(array(
			'wire' => "$wireDir/",
			'site' => "$siteDir/",
			'modules' => "$wireDir/modules/",
			'siteModules' => "$siteDir/modules/",
			'core' => "$coreDir/",
			'assets' => "$assetsDir/",
			'cache' => "$assetsDir/cache/",
			'logs' => "$assetsDir/logs/",
			'files' => "$assetsDir/files/",
			'tmp' => "$assetsDir/tmp/",
			'templates' => "$siteDir/templates/",
			'fieldTemplates' => "$siteDir/templates/fields/",
			'adminTemplates' => "$wireDir/$adminTplDir/",
		), true);
		
		$cfg['paths'] = clone $cfg['urls'];
		$cfg['paths']->set('root', $rootPath . '/');
		$cfg['paths']->data('sessions', $cfg['paths']->assets . "sessions/");

		// Styles and scripts are CSS and JS files, as used by the admin application.
	 	// But reserved here if needed by other apps and templates.
		$cfg['styles'] = new FilenameArray();
		$cfg['scripts'] = new FilenameArray();
		
		$config = new Config();
		$config->setTrackChanges(false);
		$config->data($cfg, true);

		// Include system config defaults
		/** @noinspection PhpIncludeInspection */
		require("$rootPath/$wireDir/config.php");

		// Include site-specific config settings
		$configFile = $sitePath . "config.php";
		$configFileDev = $sitePath . "config-dev.php";
		if(is_file($configFileDev)) {
			/** @noinspection PhpIncludeInspection */
			@require($configFileDev);
		} else if(is_file($configFile)) {
			/** @noinspection PhpIncludeInspection */
			@require($configFile);
		}
		
		if($httpHost) {
			$config->httpHost = $httpHost;
			if(!in_array($httpHost, $config->httpHosts)) $config->httpHosts[] = $httpHost;
		}
		
		if($scheme) $config->https = ($scheme === 'https'); 
		
		return $config;
	}

}


