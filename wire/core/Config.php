<?php namespace ProcessWire;

/**
 * ProcessWire Config
 *
 * Handles ProcessWire configuration data
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Holds ProcessWire configuration settings as defined in /wire/config.php and /site/config.php. 
 *
 *
 * @see /wire/config.php for more detailed descriptions of all config properties. 
 *
 * @property bool $ajax If the current request is an ajax (asynchronous javascript) request, this is set to true. #pw-group-runtime
 * @property string $httpHost Current HTTP host name. #pw-group-HTTP-and-input
 * @property bool $https If the current request is an HTTPS request, this is set to true. #pw-group-runtime
 * @property string $version Current ProcessWire version string (i.e. "2.2.3") #pw-group-system #pw-group-runtime
 * @property int $systemVersion System version, used by SystemUpdater to determine when updates must be applied. #pw-group-system #pw-group-runtime
 * 
 * @property FilenameArray $styles Array used by ProcessWire admin to keep track of what stylesheet files its template should load. It will be blank otherwise. Feel free to use it for the same purpose in your own sites. #pw-group-runtime
 * @property FilenameArray $scripts Array used by ProcessWire admin to keep track of what javascript files its template should load. It will be blank otherwise. Feel free to use it for the same purpose in your own sites. #pw-group-runtime
 * 
 * @property Paths $urls Items from $config->urls reflect the http path one would use to load a given location in the web browser. URLs retrieved from $config->urls always end with a trailing slash. #pw-group-runtime
 * @property Paths $paths All of what can be accessed from $config->urls can also be accessed from $config->paths, with one important difference: the returned value is the full disk path on the server. There are also a few items in $config->paths that aren't in $config->urls. All entries in $config->paths always end with a trailing slash. #pw-group-runtime
 * 
 * @property string $templateExtension Default is 'php' #pw-group-template-files
 * 
 * @property string $dateFormat Default system date format, preferably in sortable string format. Default is 'Y-m-d H:i:s' #pw-group-date-time
 * 
 * @property bool $protectCSRF Enables CSRF (cross site request forgery) protection on all PW forms, recommended for security. #pw-group-HTTP-and-input
 * 
 * @property array $imageSizerOptions Default value is array('upscaling' => true, 'cropping' => true, 'quality' => 90) #pw-group-images
 * 
 * @property bool $pagefileSecure When used, files in /site/assets/files/ will be protected with the same access as the page. Routines files through a passthrough script. #pw-group-files
 * @property string $pagefileSecurePathPrefix One or more characters prefixed to the pathname of protected file dirs. This should be some prefix that the .htaccess file knows to block requests for. #pw-group-files
 * 
 * @property array $contentTypes Array of extensions and the associated MIME type for each (for template file output). #pw-group-template-files
 * @property array $fileContentTypes Array of extensions and the associated MIME type for each (for file output). See /wire/config.php for details and defaults. #pw-group-files
 * @property array $fileCompilerOptions Array of options for FileCompiler class. See /wire/config.php for details and defaults. #pw-group-files
 * 
 * @property string $chmodDir Octal string permissions assigned to directories created by ProcessWire #pw-group-files
 * @property string $chmodFile Octal string permissions assigned to files created by ProcessWire #pw-group-files
 * @property bool $chmodWarn Set to false to suppress warnings about 0666/0777 file permissions that are potentially too loose #pw-group-files
 * 
 * @property string $timezone Current timezone using PHP timeline options: http://php.net/manual/en/timezones.php #pw-group-date-time
 * 
 * @property string $sessionName Default session name to use (default='wire') #pw-group-session
 * @property string $sessionNameSecure Session name when on HTTPS. Used when the sessionCookieSecure option is enabled (default). When blank (default), it will assume sessionName + 's'. #pw-group-session
 * @property bool|int $sessionCookieSecure Use secure cookies when on HTTPS? When enabled, separate sessions will be maintained for HTTP vs. HTTPS. Good for security but tradeoff is login session may be lost when switching (default=1 or true). #pw-group-session
 * @property null|string $sessionCookieDomain Domain to use for sessions, which enables a session to work across subdomains, or NULL to disable (default/recommended). #pw-group-session
 * @property bool|callable $sessionAllow Are sessions allowed? Typically boolean true, unless provided a callable function that returns boolean. See /wire/config.php for an example.  #pw-group-session
 * @property int $sessionExpireSeconds How many seconds of inactivity before session expires? #pw-group-session
 * @property bool $sessionChallenge Should login sessions have a challenge key? (for extra security, recommended) #pw-group-session
 * @property bool $sessionFingerprint Should login sessions be tied to IP and user agent? May conflict with dynamic IPs. #pw-group-session
 * @property int $sessionHistory Number of session entries to keep (default=0, which means off). #pw-group-session
 * 
 * @property string $prependTemplateFile PHP file in /site/templates/ that will be loaded before each page's template file (default=none) #pw-group-template-files
 * @property string $appendTemplateFile PHP file in /site/templates/ that will be loaded after each page's template file (default=none) #pw-group-template-files
 * @property bool $templateCompile Allow use of compiled templates? #pw-group-template-files
 * 
 * @property string $uploadUnzipCommand Shell command to unzip archives, used by WireUpload class. @deprecated #pw-group-deprecated
 * @property string $uploadTmpDir Optionally override PHP's upload_tmp_dir with your own. Should include a trailing slash. #pw-group-files
 * @property string $uploadBadExtensions Space separated list of file extensions that are always disallowed from uploads. #pw-group-files
 * 
 * @property string $adminEmail Email address to send fatal error notifications to. #pw-group-system
 * 
 * @property string $pageNameCharset Character set for page names, must be 'ascii' (default, lowercase) or 'UTF8' (uppercase). #pw-group-URLs
 * @property string $pageNameWhitelist Whitelist of characters allowed in UTF8 page names. #pw-group-URLs
 * @property string $pageNumUrlPrefix Prefix used for pagination URLs. Default is "page", resulting in "/page1", "/page2", etc. #pw-group-URLs
 * @property array $pageNumUrlPrefixes Multiple prefixes that may be used for detecting pagination (internal use, for multi-language) #pw-group-URLs
 * @property int $maxUrlSegments Maximum number of extra stacked URL segments allowed in a page's URL (including page numbers)  #pw-group-URLs
 * @property int $maxUrlDepth Maximum URL/path slashes (depth) for request URLs. (Min=10, Max=60) #pw-group-URLs
 * @property string $wireInputOrder Order that variables with the $input API var are handled when you access $input->var. #pw-group-HTTP-and-input
 * 
 * @property bool $advanced Special mode for ProcessWire system development. Not recommended for regular site development or production use. #pw-group-system
 * @property bool $demo Special mode for demonstration use that causes POST requests to be disabled. Applies to core, but may not be safe with 3rd party modules. #pw-group-system
 * @property bool|int $debug Special mode for use when debugging or developing a site. Recommended TRUE when site is in development and FALSE when not. Or set to Config::debugVerbose for verbose debug mode. #pw-group-system
 * @property string $debugIf Enable debug mode if condition is met #pw-group-system
 * @property array $debugTools Tools, and their order, to show in debug mode (admin) #pw-group-system
 * 
 * @property string $ignoreTemplateFileRegex Regular expression to ignore template files #pw-group-template-files
 * @property bool $pagefileExtendedPaths Use extended file mapping? #pw-group-files
 * @property array $adminThumbOptions Admin thumbnail image options #pw-group-images
 * @property array $httpHosts HTTP hosts For added security, specify the host names ProcessWire should recognize. #pw-group-HTTP-and-input
 * @property int $maxPageNum Maximum number of recognized paginations #pw-group-URLs
 * 
 * @property string $dbHost Database host #pw-group-database
 * @property string $dbName Database name #pw-group-database
 * @property string $dbUser Database user #pw-group-database
 * @property string $dbPass Database password #pw-group-database
 * @property string $dbPort Database port (default=3306) #pw-group-database
 * @property string $dbCharset Default is 'utf8' but 'utf8mb4' is also supported. #pw-group-database
 * @property string $dbEngine Database engine (MyISAM or InnoDB) #pw-group-database
 * @property string $dbSocket Optional DB socket config for sites that need it.  #pw-group-database
 * @property bool $dbCache Whether to allow MySQL query caching. #pw-group-database
 * @property bool $dbLowercaseTables Force any created field_* tables to be lowercase. #pw-group-database
 * @property string $dbPath MySQL database exec path (Path to mysqldump) #pw-group-database
 * @property array $dbOptions Any additional driver options to pass as $options argument to "new PDO(...)". #pw-group-database
 * @property array $dbSqlModes Set or adjust SQL mode per MySQL version, where array keys are MySQL version and values are SQL mode command(s). #pw-group-database
 * @property int $dbQueryLogMax Maximum number of queries WireDatabasePDO will log in memory, when debug mode is enabled (default=1000). #pw-group-database
 * @property string $dbInitCommand Database init command, for PDO::MYSQL_ATTR_INIT_COMMAND. Note placeholder {charset} gets replaced with $config->dbCharset. #pw-group-database
 * $property array $dbSqlModes Set, add or remove SQL mode based on MySQL version. See default in /wire/config.php for details. #pw-group-database
 * 
 * @property array $pageList Settings specific to Page lists. #pw-group-modules
 * @property array $pageEdit Settings specific to Page editors. #pw-group-modules
 * @property string $moduleServiceURL URL where the modules web service can be accessed #pw-group-modules
 * @property string $moduleServiceKey API key for modules web service #pw-group-modules
 * @property bool $moduleCompile Allow use of compiled modules? #pw-group-modules
 * 
 * @property array $substituteModules Associative array with names of substitutute modules for when requested module doesn't exist #pw-group-modules
 * @property array $logs Additional core logs to keep #pw-group-admin
 * @property string $defaultAdminTheme Default admin theme: AdminThemeDefault or AdminThemeReno #pw-group-admin
 * @property string $fatalErrorHTML HTML used for fatal error messages in HTTP mode. #pw-group-system
 * @property array $modals Settings for modal windows #pw-group-admin
 * @property array $preloadCacheNames Cache names to preload at beginning of request #pw-group-system
 * @property bool $allowExceptions Allow Exceptions to propagate? (default=false, specify true only if you implement your own exception handler) #pw-group-system
 * @property bool $usePoweredBy Use the x-powered-by header? Set to false to disable. #pw-group-system
 * @property bool $useFunctionsAPI Allow most API variables to be accessed as functions? (see /wire/core/FunctionsAPI.php) #pw-group-system
 * @property bool $useMarkupRegions Enable support for front-end markup regions? #pw-group-system
 * @property int $lazyPageChunkSize Chunk size for for $pages->findMany() calls. #pw-group-system
 * 
 * @property string $userAuthSalt Salt generated at install time to be used as a secondary/non-database salt for the password system. #pw-group-session
 * @property string $userAuthHashType Default is 'sha1' - used only if Blowfish is not supported by the system. #pw-group-session
 * 
 * @property bool $internal This is automatically set to FALSE when PW is externally bootstrapped. #pw-group-runtime
 * @property bool $external This is automatically set to TRUE when PW is externally bootstrapped. #pw-internal
 * @property bool $cli This is automatically set to TRUE when PW is booted as a command line (non HTTP) script. #pw-group-runtime
 * @property string $versionName This is automatically populated with the current PW version name (i.e. 2.5.0 dev) #pw-group-runtime
 * @property int $inputfieldColumnWidthSpacing Used by some admin themes to commmunicate to InputfieldWrapper at runtime. #pw-internal
 * @property bool $debugMarkupQA Set to true to make the MarkupQA class report verbose debugging messages (to superusers). #pw-internal
 * 
 * @property int $rootPageID ID of homepage (usually 1) #pw-group-system-IDs
 * @property int $adminRootPageID ID of admin root page #pw-group-system-IDs
 * @property int $trashPageID #pw-group-system-IDs
 * @property int $loginPageID #pw-group-system-IDs
 * @property int $http404PageID #pw-group-system-IDs
 * @property int $usersPageID #pw-group-system-IDs
 * @property array $usersPageIDs Populated if multiple possible users page IDs (parent for users pages) #pw-group-system-IDs
 * @property int $rolesPageID #pw-group-system-IDs
 * @property int $permissionsPageID #pw-group-system-IDs
 * @property int $guestUserPageID #pw-group-system-IDs
 * @property int $superUserPageID #pw-group-system-IDs
 * @property int $guestUserRolePageID #pw-group-system-IDs
 * @property int $superUserRolePageID #pw-group-system-IDs
 * @property int $userTemplateID #pw-group-system-IDs
 * @property array $userTemplateIDs Array of template IDs when multiple allowed for users.  #pw-group-system-IDs
 * @property int $roleTemplateID #pw-group-system-IDs
 * @property int $permissionTemplateID #pw-group-system-IDs
 * @property int $externalPageID ID of page assigned to $page API variable when externally bootstrapped #pw-group-system-IDs
 * @property array $preloadPageIDs IDs of pages that will be preloaded at beginning of request #pw-group-system-IDs
 * @property int $installed Timestamp of when this PW was installed, set automatically for compatibility detection. #pw-group-system
 *
 */
class Config extends WireData {

	/**
	 * Constant for verbose debug mode (uses more memory)
	 * 
	 */
	const debugVerbose = 2;

	/**
	 * Get URL for requested resource or module
	 * 
	 * `$config->url('something')` is a shorter alternative for `$config->urls->get('something')`.
	 * 
	 * ~~~~~
	 * // Get the admin URL
	 * $url = $config->url('admin'); 
	 * 
	 * // Same thing, using alternate syntax
	 * $url = $config->urls->admin; 
	 * ~~~~~
	 * 
	 * @param string $for Predefined ProcessWire URLs property or module name
	 * @return string|null
	 * 
	 */
	public function url($for) {
		return $this->urls->get($for);
	}

	/**
	 * Alias for the url() method
	 * 
	 * #pw-internal
	 * 
	 * @param string $for Predefined ProcessWire URLs property or module name
	 * @return null|string
	 * 
	 */
	public function urls($for) { return $this->url($for); }

	/**
	 * Get disk path for requested resource or module
	 * 
	 * `$config->path('something')` is a shorter alternative for `$config->paths->get('something')`.
	 * 
	 * ~~~~~
	 * // Get the PW installation root disk path
	 * $path = $config->path('root');
	 *
	 * // Same thing, using alternate syntax
	 * $path = $config->paths->root;
	 * ~~~~~
	 * 
	 * @param string $for Predefined ProcessWire paths property or module class name
	 * @return null|string
	 * 
	 */
	public function path($for) {
		return $this->paths->get($for);
	}

	/**
	 * Alias for the path() method
	 *
	 * #pw-internal
	 *
	 * @param string $for Predefined ProcessWire paths property or module name
	 * @return null|string
	 *
	 */
	public function paths($for) { return $this->path($for); }

	/**
	 * List of config keys that are also exported in javascript
	 *
	 */
	protected $jsFields = array();

	/**
	 * Set or retrieve a config value to be shared with javascript
	 * 
	 * Values are set to the Javascript variable `ProcessWire.config[key]`. 
	 * 
	 * 1. Specify a $key and $value to set a JS config value. 
	 *
	 * 2. Specify only a $key and omit the $value in order to retrieve an existing set value.
	 *    The $key may also be an array of properties, which will return an array of values. 
	 * 
	 * 3. Specify boolean true for $value to share the $key with the JS side. If the $key 
	 *    specified does not exist then $key=true will be added to the JS config (which can later 
	 *    be overwritten with another value, which will still be shared with the JS config). 
	 *    The $key property may also be an array of properties to specify multiple. 
	 * 
	 * 4. Specify no params to retrieve in array of all existing set values.
	 * 
	 * ~~~~~
	 * // Set a property from PHP
	 * $config->js('mySettings', [
	 *   'foo' => 'bar', 
	 *   'bar' => 123,
	 * ]);
	 * 
	 * // Get a property (from PHP)
	 * $mySettings = $config->js('mySettings'); 
	 * ~~~~~
	 * ~~~~~
	 * // Get a property (from Javascript):
	 * var mySettings = ProcessWire.config.mySettings;
	 * console.log(mySettings.foo);
	 * console.log(mySettings.bar); 
	 * ~~~~~
	 *
	 * @param string|array $key Property or array of properties
	 * @param mixed $value
	 * @return array|mixed|null|$this
 	 *
	 */
	public function js($key = null, $value = null) {

		if(is_null($key)) {
			// return array of all keys and values
			$data = array();
			foreach($this->jsFields as $field) {
				$data[$field] = $this->get($field); 
			}
			return $data; 

		} else if(is_null($value)) {
			// return a value or values
			if(is_array($key)) {
				// return values for multiple keys
				$a = array();
				foreach($key as $k) {
					$a[$k] = $this->js($k);
				}
				return $a;
			} else {
				// return value for just one key
				return in_array($key, $this->jsFields) ? $this->get($key) : null;
			}
			
		} else if($value === true) {
			// share an already present value or set a key=true
			if(is_array($key)) {
				// sharing multiple keys
				foreach($key as $k) $this->js($k, true); 
				return $this;
			} else if($this->get($key) !== null) {
				// share existing config $key with JS side
				$this->jsFields[] = $key;
				return $this;
			} else {
				// will set $key=true to JS config, which may be overwritten
				// with a different value during runtime, or maybe true is the 
				// literal value they want stored, in which case it will remain.
			}
		}

		$this->jsFields[] = $key; 
		return parent::set($key, $value); 
	}
	
	/**
	 * Allow for getting/setting config properties via method call
	 * 
	 * This is primarily useful for getting or setting config properties that consist of associative arrays. 
	 * 
	 * ~~~~~
	 * 
	 * // Enable debug mode, same as $config->debug = true; 
	 * $config->debug(true);
	 * 
	 * // Set a specific property in options array
	 * $config->fileCompilerOptions('siteOnly', true);
	 * 
	 * // Get a specific property in options array
	 * $value = $config->fileCompilerOptions('siteOnly');
	 * 
	 * // Set multiple properties in options array (leaving other existing properties alone)
	 * $config->fileCompilerOptions([
	 *   'siteOnly' => true, 
	 *   'cachePath' => $config->paths->root . '.my-cache/'
	 * ]);
	 * ~~~~~
	 *
	 * #pw-internal
	 *
	 * @param string $method Requested method name
	 * @param array $arguments Arguments provided
	 * @return null|mixed Return value of method (if applicable)
	 * @throws WireException
	 *
	 */
	protected function ___callUnknown($method, $arguments) {
		//$this->message("callUnknown($method)");
		$value = parent::get($method);
		if($value === null) return parent::___callUnknown($method, $arguments);
		$numArgs = count($arguments);
		if($numArgs === 0) return $value; // no arguments? just return value
	
		if(is_array($value)) {
			// existing value is an array
			$property = $arguments[0];
			if($numArgs === 1) {
				// just a property name (get) or array (set) provided
				if(is_string($property)) {
					// just return property value from array
					return isset($value[$property]) ? $value[$property] : null;
				} else if(is_array($property)) {
					// set multiple named properties
					$value = array_merge($value, $property);
					parent::set($method, $value);
				} else {
					throw new WireException("Invalid argument, array or string expected");
				}
			} else {
				// property and value provided
				$value[$property] = $arguments[1];
				parent::set($method, $value);
			}
		} else if($numArgs === 1) {
			// just set a value
			parent::set($method, $arguments[0]);
		}
		
		return $this;
	}
}

