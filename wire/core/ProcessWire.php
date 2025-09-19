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
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 * Default API vars (A-Z) 
 * ======================
 * @property AdminTheme|AdminThemeFramework|null $adminTheme
 * @property WireCache $cache
 * @property WireClassLoader $classLoader
 * @property Config $config
 * @property WireDatabasePDO $database
 * @property WireDateTime $datetime
 * @property Fieldgroups $fieldgroups
 * @property Fields $fields
 * @property Fieldtypes $fieldtypes
 * @property WireFileTools $files
 * @property Fuel $fuel
 * @property WireHooks $hooks
 * @property WireInput $input
 * @property Languages $languages (present only if LanguageSupport installed)
 * @property WireLog $log
 * @property WireMailTools $mail
 * @property Modules $modules
 * @property Notices $notices
 * @property Page $page
 * @property Pages $pages
 * @property Permissions $permissions
 * @property Process|ProcessPageView $process
 * @property WireProfilerInterface $profiler
 * @property Roles $roles
 * @property Sanitizer $sanitizer
 * @property Session $session
 * @property Templates $templates
 * @property Paths $urls
 * @property User $user
 * @property Users $users
 * @property ProcessWire $wire
 * @property WireShutdown $shutdown
 * @property PagesVersions|null $pagesVersions
 * 
 * @method init()
 * @method ready()
 * @method finished(array $data)
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
	const versionRevision = 252;

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
	const htaccessVersion = 301;

	/**
	 * Status prior to boot (no API variables available)
	 * 
	 */
	const statusNone = 0;

	/**
	 * Status when system is booting
	 * 
	 * API variables available: $wire, $hooks, $config, $classLoader 
	 * 
	 */
	const statusBoot = 1;

	/**
	 * Status when system and modules are initializing
	 * 
	 * All API variables available except for $page
	 * 
	 */
	const statusInit = 2;

	/**
	 * Status when system, $page and all API variables are ready
	 * 
	 * All API variables available
	 * 
	 */
	const statusReady = 4;

	/**
	 * Status when the current $page’s template file is being rendered, set right before render
	 * 
	 * All API variables available
	 * 
	 */
	const statusRender = 8;

	/**
	 * Status when current request will send a file download to client and exit (rather than rendering a page template file)
	 * 
	 * All API variables available
	 * 
	 */
	const statusDownload = 32;

	/**
	 * Status when the request has been fully delivered (but before a redirect)
	 * 
	 * All API variables available
	 * 
	 */
	const statusFinished = 128;

	/**
	 * Status when the request has finished abnormally (like a manual exit)
	 * 
	 * @since 3.0.180
	 *
	 */
	const statusExited = 256;

	/**
	 * Status when the request failed due to an Exception or 404
	 * 
	 * API variables should be checked for availability before using. 
	 * 
	 */
	const statusFailed = 1024;

	/**
	 * Current status/state
	 * 
	 * @var int
	 * 
	 */
	protected $status = self::statusNone;

	/**
	 * Names for each of the system statuses
	 * 
	 * @var array
	 * 
	 */
	protected $statusNames = array(
		self::statusNone => '',
		self::statusBoot => 'boot',
		self::statusInit => 'init',
		self::statusReady => 'ready',
		self::statusRender => 'render',
		self::statusDownload => 'download',
		self::statusFinished => 'finished',
		self::statusExited => 'exited',
		self::statusFailed => 'failed',
	);

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
	 * Saved file, for includeFile() method
	 * 
	 * @var string
	 * 
	 */
	protected $fileSave = '';

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
		parent::__construct();
	
		if(empty($config)) $config = getcwd();
		if(is_string($config)) $config = self::buildConfig($config, $rootURL);
		if(!$config instanceof Config) throw new WireException("No configuration information available");
		
		// this is reset in the $this->setConfig() method based on current debug mode
		ini_set('display_errors', true);
		error_reporting(E_ALL);

		$config->setWire($this);
		
		$this->debug = $this->setConfigDebug($config);
		if($this->debug) Debug::timer('all');
		$this->instanceID = self::addInstance($this);
		$this->setWire($this);
		
		$this->fuel = new Fuel();
		$this->fuel->set('wire', $this, true);

		/** @var WireClassLoader $classLoader */
		$classLoader = $this->wire('classLoader', new WireClassLoader($this), true);
		$classLoader->addNamespace((strlen(__NAMESPACE__) ? __NAMESPACE__ : "\\"), PROCESSWIRE_CORE_PATH);

		if($config->usePageClasses) {
			$classLoader->addSuffix('Page', $config->paths->classes);
		}

		$this->wire('hooks', new WireHooks($this, $config), true);

		$this->setConfig($config);
		$this->shutdown = $this->wire(new WireShutdown($config));
		$this->setStatus(self::statusBoot);
		$this->load($config);
		
		if(self::getNumInstances() > 1) {
			// this instance is not handling the request and needs a mock $page API var and pageview
			/** @var ProcessPageView $view */
			$view = $this->fuel->get('modules')->get('ProcessPageView');
			$view->execute(false);
		}
	}

	/**
	 * Destruct
	 * 
	 */
	public function __destruct() {
		if($this->status < self::statusFinished) {
			// call finished hook if it wasn’t already
			$this->status = self::statusExited;
			$this->finished(array(
				'prevStatus' => $this->status,
				'exited' => true, 
			));
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
		$config->admin = 0; // 0=not known, determined during ready state
		
		$version = self::versionMajor . "." . self::versionMinor . "." . self::versionRevision; 
		$config->version = $version;
		$config->versionName = trim($version . " " . self::versionSuffix);
		$config->moduleServiceKey .= str_replace('.', '', $version);
		
		if($config->useFunctionsAPI && !function_exists("\\ProcessWire\\pages")) {
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
	 * Determine whether debug mode should be enabled
	 * 
	 * @param Config $config
	 * @return bool|int Returns determined debug mode value
	 * @since 3.0.212
	 * 
	 */
	protected function setConfigDebug(Config $config) {
		$debug = $config->debug;
		if($debug) {
			// use as-is
		} else {
			$debugIf = $config->debugIf;
			if(empty($debugIf)) {
				// no processing needed
			} else if(is_callable($debugIf)) {
				// callable function
				$debug = $debugIf();
			} else {
				$ip = $config->sessionForceIP;
				if(empty($ip)) $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
				if(is_string($debugIf) && strlen($debugIf) && !empty($ip)) {
					// match exact IP address or regex matching IP address(es)
					$debugIf = trim($debugIf);
					if(strpos($debugIf, '/') === 0) {
						$debug = (bool) @preg_match($debugIf, $ip); // regex IPs
					} else {
						$debug = $debugIf === $ip; // exact IP match
					}
				} else if(is_array($debugIf) && !empty($ip)) {
					// match IP address in array
					$debug = in_array($ip, $debugIf);
				}
			}
			if($debug) $config->debug = $debug;
		}
		
		if($debug) {
			// If debug mode is on then echo all errors
			error_reporting(E_ALL);
			ini_set('display_errors', 1);
		} else {
			// disable all error reporting
			error_reporting(0);
			ini_set('display_errors', 0);
		}
		
		return $debug;
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

			} else if(isset($_SERVER['HTTP_HOST'])) {
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
		
		$notices = new Notices();
		$this->wire('notices', $notices, true); // first so any API var can send notices
		$this->wire('urls', $config->urls); // shortcut API var
		$this->wire('log', new WireLog(), true);
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
	
		$this->wire('cache', new WireCache(), true); 
		
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
			$modules->refresh();
			$this->updater = $modules->get('SystemUpdater');
		}

		$fieldtypes = $this->wire('fieldtypes', new Fieldtypes(), true);
		$fields = $this->wire('fields', new Fields(), true);
		$fieldgroups = $this->wire('fieldgroups', new Fieldgroups(), true);
		$templates = $this->wire('templates', new Templates($fieldgroups), true); 
		$pages = $this->wire('pages', new Pages($this), true);

		$this->initVar('fieldtypes', $fieldtypes);
		if($this->debug) Debug::timer('init.fields.templates.fieldgroups');
		$this->initVar('fields', $fields);
		$this->initVar('fieldgroups', $fieldgroups);
		$this->initVar('templates', $templates);
		if($this->debug) Debug::saveTimer('init.fields.templates.fieldgroups');
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
		$user = $users->getCurrentUser();
		if($config->userOutputFormatting) $user->of(true);
		$this->wire('user', $user);
		
		$input = $this->wire('input', new WireInput(), true); 
		if($config->wireInputLazy) $input->setLazy(true);

		// populate admin URL before modules init()
		$config->urls->admin = $config->urls->root . ltrim($pages->getPath($config->adminRootPageID), '/');

		$notices->init();
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
	 * @param int $status
	 * @param array $data Associative array of any extra data to pass along to include files as locally scoped vars (3.0.142+)
	 * 
	 */
	public function setStatus($status, array $data = array()) {
		
		/** @var Config $config */
		$config = $this->fuel->get('config');
		
		// don’t re-trigger if this state has already been triggered
		// except that a failed status can be backtracked
		if($this->status >= $status && $this->status != self::statusFailed) return;
		
		$name = isset($this->statusNames[$status]) ? $this->statusNames[$status] : 'unknown';
		$path = $config->paths->site;
		$files = $config->statusFiles;

		if($status == self::statusReady || $status == self::statusInit) {
			// before status include file, i.e. "readyBefore" or "initBefore"
			$nameBefore = $name . 'Before';
			$file = empty($files[$nameBefore]) ? null : $path . basename($files[$nameBefore]);
			if($file !== null) $this->includeFile($file, $data);
		}

		// set status to config
		$prevStatus = $this->status;
		$this->status = $status;
		$config->status = $status;
	
		// call any relevant internal methods
		if($status == self::statusInit) {
			$this->init();
		} else if($status == self::statusReady) {
			$config->admin = $this->isAdmin();
			$this->ready();
			if($this->debug) Debug::saveTimer('boot', 'includes all boot timers');
		} 
	
		// after status include file, names like 'init', 'ready', etc.
		$file = empty($files[$name]) ? null : $path . basename($files[$name]);
		if($file !== null) $this->includeFile($file, $data);

		if($status == self::statusFinished) {
			// internal finished always runs after any included finished file
			$data['prevStatus'] = $prevStatus;
			$data['maintenance'] = true;
			$data['exited'] = false;
			$this->finished($data);
		} else if($status == self::statusReady) {
			// additional 'admin' or 'site' options for ready status
			if(!empty($files['readyAdmin']) && $config->admin === true) {
				$this->includeFile($path . basename($files['readyAdmin']), $data);
			} else if(!empty($files['readySite']) && $config->admin === false) {
				$this->includeFile($path . basename($files['readySite']), $data);
			}
		}
	}
	
	/**
	 * Set internal runtime status to failed, with additional info
	 * 
	 * #pw-internal
	 *
	 * @param \Throwable $e Exception or Error
	 * @param string $reason
	 * @param null $page
	 * @param string $url
	 * @since 3.0.142
	 *
	 */
	public function setStatusFailed($e, $reason = '', $page = null, $url = '') {
		static $lastThrowable = null;
		if($lastThrowable === $e) return;
		$isException = $e instanceof \Exception;
		if(!$page instanceof Page) $page = new NullPage();
		$this->setStatus(ProcessWire::statusFailed, array(
			'throwable' => $e, 
			'exception' => $isException ? $e : null,
			'error' => $isException ? null : $e, 
			'failPage' => $page,
			'reason' => $reason,
			'url' => $url,
		));
		$lastThrowable = $e;
	}

	/**
	 * Is the current request for a logged-in user within the admin control panel?
	 * 
	 * #pw-internal
	 * 
	 * @return bool|int Returns boolean true or false, or 0 if not yet able to tell
	 * @since 3.0.142
	 * 
	 */
	protected function isAdmin() {

		/** @var Config $config */
		$config = $this->fuel->get('config'); 
		$admin = $config->admin;
		if(is_bool($admin)) return $admin;
		$admin = 0;

		/** @var Page $page */
		$page = $this->fuel->get('page'); 
		if(!$page || !$page->id) return 0;
		
		if(in_array($page->template->name, $config->adminTemplates)) {
			/** @var User $user */
			$user = $this->fuel->get('user'); 
			if($user) $admin = $user->isLoggedin() ? true : false;
		} else {
			$admin = false;
		}

		return $admin;
	}

	/**
	 * Get the current runtime status/state
	 * 
	 * #pw-internal
	 * 
	 * @param bool $getName Get the name of the status rather than the integer value? (default=false)
	 * @return int|string
	 * @since 3.0.142
	 * 
	 */
	public function getStatus($getName = false) {
		if(!$getName) return $this->status;
		return isset($this->statusNames[$this->status]) ? $this->statusNames[$this->status] : 'unknown';
	}

	/**
	 * Hookable init for anyone that wants to hook immediately before any autoload modules initialized or after all modules initialized
	 * 
	 * #pw-hooker
	 * 
	 */
	protected function ___init() {
		if($this->debug) Debug::timer('boot.modules.autoload.init'); 
		$this->fuel->get('modules')->triggerInit();
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
		$this->fuel->get('modules')->triggerReady();
		$this->updater->ready();
		unset($this->updater);
		if($this->debug) Debug::saveTimer('boot.modules.autoload.ready'); 
	}

	/**
	 * Hookable ready for anyone that wants to hook when the request is finished
	 * 
	 * @param array $data Additional data for hooks (3.0.147+ only):
	 *  - `maintenance` (bool): Allow maintenance to run? (default=true)
	 *  - `prevStatus` (int): Previous status before finished status (render, download or failed).
	 *  - `exited` (bool): True if request was exited before finished (ProcessWire instance destructed before expected). 3.0.180+
	 *  - `redirectUrl` (string): Contains redirect URL only if request ending with redirect (not present otherwise). 
	 *  - `redirectType` (int): Contains redirect type 301 or 302, only if requestUrl property is also present.
	 * 
	 * #pw-hooker
	 *
	 */
	protected function ___finished(array $data = array()) {
		
		$config = $this->fuel->get('config'); /** @var Config $config */
		$session = $this->fuel->get('session'); /** @var Session $session */
		$cache = $this->fuel->get('cache'); /** @var WireCache $cache */
		$profiler = $this->fuel->get('profiler'); /** @var WireProfilerInterface $profiler */
		$exited = !empty($data['exited']);
		
		if($data) {} // data for hooks
	
		// if a hook cancelled maintenance then exit early 
		if(isset($data['maintenance']) && $data['maintenance'] === false) return;
		
		if($session && !$exited) $session->maintenance();
		if($cache && !$exited) $cache->maintenance();
		if($profiler) $profiler->maintenance();

		if($config && !$exited) {
			if($config->templateCompile) {
				$compiler = new FileCompiler($config->paths->templates);
				$this->wire($compiler);
				$compiler->maintenance();
			}
			if($config->moduleCompile) {
				$compiler = new FileCompiler($config->paths->siteModules);
				$this->wire($compiler);
				$compiler->maintenance();
			}
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

	/**
	 * Get API var directly
	 * 
	 * @param string $name
	 * @return mixed
	 * 
	 */
	public function __get($name) {
		if($name === 'fuel') return $this->fuel;
		if($name === 'shutdown') return $this->shutdown;
		if($name === 'instanceID') return $this->instanceID;
		$value = $this->fuel->get($name);
		if($value !== null) return $value;
		return parent::__get($name);
	}

	/**
	 * Include a PHP file, giving it all PW API varibles in scope
	 * 
	 * File is executed in the directory where it exists.
	 * 
	 * @param string $file Full path and filename
	 * @param array $data Associative array of any extra data to pass along to include file as locally scoped vars
	 * @return bool True if file existed and was included, false if not.
	 * 
	 */
	protected function includeFile($file, array $data = array()) {
		if(!file_exists($file)) return false;
		$this->fileSave = $file; // to prevent any possibility of extract() vars from overwriting
		$config = $this->fuel->get('config'); /** @var Config $config */
		if($this->status > self::statusBoot && $config->templateCompile) {
			$files = $this->fuel->get('files'); /** @var WireFileTools $files */
			if($files) $this->fileSave = $files->compile($file, array('skipIfNamespace' => true));
		}
		$this->pathSave = getcwd();
		chdir(dirname($this->fileSave));
		if(count($data)) extract($data);
		$fuel = $this->fuel->getArray();
		extract($fuel);
		/** @noinspection PhpIncludeInspection */
		include($this->fileSave);
		chdir($this->pathSave);
		$this->fileSave = '';
		return true; 
	}

	/**
	 * Call method
	 * 
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 * @throws WireException
	 * 
	 */
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

	/**
	 * Called if any Wire-derived object makes API calls before being wired
	 * 
	 * This is for debugging purposes only and is not called unless `ProcessWire::objectNotWired` is hooked. 
	 * It is called only once per non-wired object. Uncomment code within to use. 
	 * 
	 * #pw-internal
	 * 
	 * @param Wire $obj Object that accessed API var without being assigned ProcessWire instance
	 * @param string|Wire The $name argument that was passed to $obj->wire($name, $value)
	 * @param mixed $value The $value argument passed to $object->wire($name, $value)
	 * @since 3.0.158
	 * 
	 */
	public function _objectNotWired(Wire $obj, $name, $value) { 
		// Uncomment code below to enable (use in admin)
		/*
		if(is_string($name) && $this->wire($name)) {
			$msg = $obj->className() . " accessed API var \$$name before being wired";
			$this->warning("$msg\n" . Debug::backtrace(array(
				'limit' => 2, 
				'getString' => true,
				'getCnt' => false,
				'getFile' => 'basename',
			)));
		}
		*/
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
	 *   …or specify boolean true to get absolute root path, which disregards any symbolic links to core. 
	 * @return string
	 * 
	 */
	public static function getRootPath($rootPath = '') {
		
		if($rootPath !== true && strpos($rootPath, '..') !== false) {
			$rootPath = realpath($rootPath);
		}

		if(empty($rootPath) && !empty($_SERVER['SCRIPT_FILENAME'])) {
			// first try to determine from the script filename
			$parts = explode(DIRECTORY_SEPARATOR, $_SERVER['SCRIPT_FILENAME']);
			array_pop($parts); // most likely: index.php
			$rootPath = implode('/', $parts) . '/';
			if(!file_exists($rootPath . 'wire/core/ProcessWire.php')) $rootPath = '';
		}
		
		if(empty($rootPath) || $rootPath === true) {
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
		$cfg['paths']->data('classes', $cfg['paths']->site . "classes/");

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
