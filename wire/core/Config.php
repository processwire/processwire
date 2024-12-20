<?php namespace ProcessWire;

/**
 * ProcessWire Config
 *
 * Handles ProcessWire configuration data
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Holds ProcessWire configuration settings as defined in /wire/config.php and /site/config.php. 
 * #pw-summary-js Javascript configuration
 * #pw-body =
 * For more detailed descriptions of these $config properties, including default values, see the
 * [/wire/config.php](https://github.com/processwire/processwire/blob/master/wire/config.php) file. 
 * #pw-body
 *
 *
 * @see /wire/config.php for more detailed descriptions of all config properties. 
 *
 * @property bool $ajax If the current request is an ajax (asynchronous javascript) request, this is set to true. #pw-group-runtime
 * @property bool|int $modal If the current request is in a modal window, this is set to a positive number. False if not. #pw-group-runtime
 * @property bool|int $admin Is current request for logged-in user in admin? True, false, or 0 if not yet known. @since 3.0.142 #pw-group-runtime
 * @property string $httpHost Current HTTP host name. #pw-group-HTTP-and-input
 * @property bool $https If the current request is an HTTPS request, this is set to true. #pw-group-runtime
 * @property string $version Current ProcessWire version string (i.e. "2.2.3") #pw-group-system #pw-group-runtime
 * @property int $systemVersion System version, used by SystemUpdater to determine when updates must be applied. #pw-group-system #pw-group-runtime
 * 
 * @property FilenameArray $styles Array used by ProcessWire admin to keep track of what stylesheet files its template should load. It will be blank otherwise. Feel free to use it for the same purpose in your own sites. #pw-group-runtime
 * @property FilenameArray $scripts Array used by ProcessWire admin to keep track of what javascript files its template should load. It will be blank otherwise. Feel free to use it for the same purpose in your own sites. #pw-group-runtime
 * 
 * @property Paths $urls Items from $config->urls reflect the http path one would use to load a given location in the web browser. URLs retrieved from $config->urls always end with a trailing slash. This is the same as the $urls API variable. #pw-group-runtime #pw-group-URLs
 * @property Paths $paths All of what can be accessed from $config->urls can also be accessed from $config->paths, with one important difference: the returned value is the full disk path on the server. There are also a few items in $config->paths that aren't in $config->urls. All entries in $config->paths always end with a trailing slash. #pw-group-paths #pw-group-runtime
 * 
 * @property string $templateExtension Default is 'php' #pw-group-template-files
 * 
 * @property string $dateFormat Default system date format, preferably in sortable string format. Default is 'Y-m-d H:i:s' #pw-group-date-time
 * 
 * @property bool $protectCSRF Enables CSRF (cross site request forgery) protection on all PW forms, recommended for security. #pw-group-HTTP-and-input
 * 
 * @property array $imageSizes Predefined image sizes (and options) indexed by name. See /wire/config.php for example. #pw-group-images @since 3.0.151
 * @property array $imageSizerOptions Options to set image sizing defaults. Please see the /wire/config.php file for all options and defaults. #pw-group-images
 * @property array $webpOptions Options for webp images. Please see /wire/config.php for all options. #pw-group-images
 * 
 * @property bool $pagefileSecure When used, files in /site/assets/files/ will be protected with the same access as the page. Routes files through a passthrough script. Note if applying to existing site it may not affect existing pages and file/image fields until they are accessed or saved. #pw-group-files
 * @property string $pagefileSecurePathPrefix One or more characters prefixed to the pathname of protected file dirs. This should be some prefix that the .htaccess file knows to block requests for. #pw-group-files
 * @property string $pagefileUrlPrefix Deprecated property that was a string that prefixes filenames in PW URLs, becoming a shortcut to a page’s file’s URL (do not use, here for backwards compatibility only). #pw-internal
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
 * @property string $sessionCookieSameSite Cookie “SameSite” value for sessions - “Lax” (default) or “Strict”. #pw-group-session @since 3.0.178
 * @property bool|callable $sessionAllow Are sessions allowed? Typically boolean true, unless provided a callable function that returns boolean. See /wire/config.php for an example.  #pw-group-session
 * @property int $sessionExpireSeconds How many seconds of inactivity before session expires? #pw-group-session
 * @property bool $sessionChallenge Should login sessions have a challenge key? (for extra security, recommended) #pw-group-session
 * @property int|bool $sessionFingerprint Should login sessions be tied to IP and user agent? 0 or false: Fingerprint off. 1 or true: Fingerprint on with default/recommended setting (currently 10). 2: Fingerprint only the remote IP. 4: Fingerprint only the forwarded/client IP (can be spoofed). 8: Fingerprint only the useragent. 10: Fingerprint the remote IP and useragent (default). 12: Fingerprint the forwarded/client IP and useragent. 14: Fingerprint the remote IP, forwarded/client IP and useragent (all). #pw-group-session
 * @property int $sessionHistory Number of session entries to keep (default=0, which means off). #pw-group-session
 * @property string $sessionForceIP Force the client IP address returned by $session->getIP() to be this rather than auto-detect (useful with load balancer). Use for setting value only. #pw-group-session
 * @property array $loginDisabledRoles Array of role name(s) or ID(s) of roles where login is disallowed. #pw-group-session
 * 
 * @property string $prependTemplateFile PHP file in /site/templates/ that will be loaded before each page's template file (default=none) #pw-group-template-files
 * @property string $appendTemplateFile PHP file in /site/templates/ that will be loaded after each page's template file (default=none) #pw-group-template-files
 * @property bool $templateCompile Allow use of compiled templates? #pw-group-template-files
 * 
 * @property string $uploadUnzipCommand Shell command to unzip archives, used by WireUpload class (deprecated, no longer in use). #pw-internal
 * @property string $uploadTmpDir Optionally override PHP's upload_tmp_dir with your own. Should include a trailing slash. #pw-group-files
 * @property string $uploadBadExtensions Space separated list of file extensions that are always disallowed from uploads. #pw-group-files
 * 
 * @property string $adminEmail Email address to send fatal error notifications to. #pw-group-system
 * @property array $adminTemplates Names of templates that ProcessWire should consider exclusive to the admin. #pw-group-system @since 3.0.142
 * 
 * @property string $pageNameCharset Character set for page names, must be 'ascii' (default, lowercase) or 'UTF8' (uppercase). #pw-group-URLs
 * @property string $pageNameWhitelist Whitelist of characters allowed in UTF8 page names. #pw-group-URLs
 * @property string $pageNameUntitled Name to use for untitled pages (default="untitled"). #pw-group-URLs
 * @property string $pageNumUrlPrefix Prefix used for pagination URLs. Default is "page", resulting in "/page1", "/page2", etc. #pw-group-URLs
 * @property array $pageNumUrlPrefixes Multiple prefixes that may be used for detecting pagination (internal use, for multi-language) #pw-group-URLs
 * @property int $maxUrlSegments Maximum number of extra stacked URL segments allowed in a page's URL (including page numbers)  #pw-group-URLs
 * @property int $maxUrlSegmentLength Maximum length of any individual URL segment (default=128). #pw-group-URLs
 * @property int $maxUrlDepth Maximum URL/path slashes (depth) for request URLs. (Min=10, Max=60) #pw-group-URLs
 * @property int $longUrlResponse Response code when URL segments, depth or length exceeds max allowed. #pw-group-URLs @since 3.0.243
 * @property string $wireInputOrder Order that variables with the $input API var are handled when you access $input->var. #pw-group-HTTP-and-input
 * @property bool $wireInputLazy Specify true for $input API var to load input data in a lazy fashion and potentially use less memory. Default is false. #pw-group-HTTP-and-input
 * @property int $wireInputArrayDepth Maximum multi-dimensional array depth for input variables accessed from $input or 1 to only allow single dimension arrays. #pw-group-HTTP-and-input @since 3.0.178
 * @property array $cookieOptions Options for setting cookies from $input->cookie #pw-group-HTTP-and-input
 * 
 * @property bool $advanced Special mode for ProcessWire system development. Not recommended for regular site development or production use. #pw-group-system
 * @property bool $demo Special mode for demonstration use that causes POST requests to be disabled. Applies to core, but may not be safe with 3rd party modules. #pw-group-system
 * @property bool|int|string $debug Special mode for use when debugging or developing a site. Recommended TRUE when site is in development and FALSE when not. Or set to `Config::debug*` constant. #pw-group-system
 * @property string|callable|array $debugIf Enable debug mode if condition is met. One of IP address to match, regex to match IP, array of IPs to match, or callable function that returns true|false. #pw-group-system
 * @property array $debugTools Tools, and their order, to show in debug mode (admin) #pw-group-system
 * 
 * @property string $ignoreTemplateFileRegex Regular expression to ignore template files #pw-group-template-files
 * @property bool $pagefileExtendedPaths Use extended file mapping? #pw-group-files
 * @property array $adminThumbOptions Admin thumbnail image options #pw-group-images
 * @property array $httpHosts HTTP hosts For added security, specify the host names ProcessWire should recognize. #pw-group-HTTP-and-input
 * @property int $maxPageNum Maximum number of recognized paginations #pw-group-URLs
 * @property bool|string|array $noHTTPS When boolean true, pages requiring HTTPS will not enforce it (useful for dev environments). May also specify hostname (string) or hostnames (array) to disable HTTPS for. #pw-group-HTTP-and-input
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
 * @property bool $dbStripMB4 When dbEngine is not utf8mb4 and this is true, we will attempt to remove 4-byte characters (like emoji) from inserts when possible. Note that this adds some overhead. #pw-group-database
 * @property array|null $dbReader Configuration values for read-only database connection (if available). #pw-group-database @since 3.0.175
 * 
 * @property array $pageList Settings specific to Page lists. #pw-group-modules
 * @property array $pageEdit Settings specific to Page editors. #pw-group-modules
 * @property array $pageAdd Settings specific to Page adding. #pw-group-modules
 * @property string $moduleServiceURL URL where the modules web service can be accessed #pw-group-modules
 * @property string $moduleServiceKey API key for modules web service #pw-group-modules
 * @property bool $moduleCompile Allow use of compiled modules? #pw-group-modules
 * @property array $wireMail Default WireMail module settings. See /wire/config.php $config->wireMail for details. #pw-group-system
 * @property array $moduleInstall Admin module install options you allow. #pw-group-modules
 * 
 * @property array $substituteModules Associative array with names of substitute modules for when requested module doesn't exist #pw-group-modules
 * @property array $logs Additional core logs to keep #pw-group-admin
 * @property bool $logIP Include IP address in logs, when applicable? #pw-group-admin
 * @property string $defaultAdminTheme Default admin theme: AdminThemeUikit, AdminThemeDefault or AdminThemeReno. #pw-group-admin
 * @property array $AdminThemeUikit Settings specific to AdminThemeUikit module (see this setting in /wire/config.php). #pw-group-admin @since 3.0.179
 * @property string $fatalErrorHTML HTML used for fatal error messages in HTTP mode. #pw-group-system
 * @property int $fatalErrorCode HTTP code to send on fatal error (typically 500 or 503). #pw-group-system
 * @property array $modals Settings for modal windows #pw-group-admin
 * @property array $preloadCacheNames Cache names to preload at beginning of request #pw-group-system
 * @property bool $allowExceptions Allow Exceptions to propagate? (default=false, specify true only if you implement your own exception handler) #pw-group-system
 * @property bool $usePoweredBy Use the x-powered-by header? Set to false to disable. #pw-group-system
 * @property bool $useFunctionsAPI Allow most API variables to be accessed as functions? (see /wire/core/FunctionsAPI.php) #pw-group-system
 * @property bool $useMarkupRegions Enable support for front-end markup regions? #pw-group-system
 * @property bool|array $useLazyLoading Delay loading of fields (and templates/fieldgroups) till requested? Can improve performance on systems with lots of fields or templates. #pw-group-system @since 3.0.193
 * @property bool $usePageClasses Use custom Page classes in `/site/classes/[TemplateName]Page.php`? #pw-group-system @since 3.0.152
 * @property bool|int|string|null $useVersionUrls Default value for $useVersion argument of $config->versionUrls() method #pw-group-system @since 3.0.227
 * @property int $lazyPageChunkSize Chunk size for for $pages->findMany() calls. #pw-group-system
 * 
 * @property string $userAuthSalt Salt generated at install time to be used as a secondary/non-database salt for the password system. #pw-group-session
 * @property string $userAuthHashType Default is 'sha1' - used only if Blowfish is not supported by the system. #pw-group-session
 * @property bool $userOutputFormatting Enable output formatting for current $user API variable at boot? (default=false) #pw-group-session @since 3.0.241
 * @property string $tableSalt Additional hash for other (non-authentication) purposes. #pw-group-system @since 3.0.164
 * 
 * @property bool $internal This is automatically set to FALSE when PW is externally bootstrapped. #pw-group-runtime
 * @property bool $external This is automatically set to TRUE when PW is externally bootstrapped. #pw-internal
 * @property bool $cli This is automatically set to TRUE when PW is booted as a command line (non HTTP) script. #pw-group-runtime
 * @property string $serverProtocol Current server protocol, one of: HTTP/1.1, HTTP/1.0, HTTP/2, or HTTP/2.0. #pw-group-runtime @since 3.0.166
 * @property string $versionName This is automatically populated with the current PW version name (i.e. 2.5.0 dev) #pw-group-runtime
 * @property int $inputfieldColumnWidthSpacing Used by some admin themes to commmunicate to InputfieldWrapper at runtime. #pw-internal
 * @property array InputfieldWrapper Settings specific to InputfieldWrapper class #pw-internal
 * @property bool $debugMarkupQA Set to true to make the MarkupQA class report verbose debugging messages (to superusers). #pw-internal
 * @property array $markupQA Optional settings for MarkupQA class used by FieldtypeTextarea module. #pw-group-modules
 * @property string|null $pagerHeadTags Populated at runtime to contain `<link rel=prev|next />` tags for document head, after pagination has been rendered by MarkupPagerNav module. #pw-group-runtime 
 * @property array $statusFiles File inclusions for ProcessWire’s runtime statuses/states. #pw-group-system @since 3.0.142
 * @property int $status Value of current system status/state corresponding to ProcessWire::status* constants. #pw-internal
 * @property null|bool $disableUnknownMethodException Disable the “Method does not exist or is not callable in this context” exception. (default=null) #pw-internal
 * @property string|null $phpMailAdditionalParameters Additional params to pass to PHP’s mail() function (when used), see $additional_params argument at https://www.php.net/manual/en/function.mail.php #pw-group-system
 * 
 * @property int $rootPageID Page ID of homepage (usually 1) #pw-group-system-IDs
 * @property int $adminRootPageID Page ID of admin root page #pw-group-system-IDs
 * @property int $trashPageID Page ID of the trash page. #pw-group-system-IDs
 * @property int $loginPageID Page ID of the admin login page. #pw-group-system-IDs
 * @property int $http404PageID Page ID of the 404 “page not found” page. #pw-group-system-IDs
 * @property int $usersPageID Page ID of the page having users as children. #pw-group-system-IDs
 * @property array $usersPageIDs Populated if multiple possible users page IDs (parent for users pages) #pw-group-system-IDs
 * @property int $rolesPageID Page ID of the page having roles as children. #pw-group-system-IDs
 * @property int $permissionsPageID Page ID of the page having permissions as children. #pw-group-system-IDs
 * @property int $guestUserPageID Page ID of the guest (default/not-logged-in) user. #pw-group-system-IDs
 * @property int $superUserPageID Page ID of the original superuser (created during installation). #pw-group-system-IDs
 * @property int $guestUserRolePageID Page ID of the guest user role (inherited by all users, not just guest). #pw-group-system-IDs
 * @property int $superUserRolePageID Page ID of the superuser role. #pw-group-system-IDs
 * @property int $userTemplateID Template ID of the user template. #pw-group-system-IDs
 * @property array $userTemplateIDs Array of template IDs when multiple allowed for users.  #pw-group-system-IDs
 * @property int $roleTemplateID Template ID of the role template. #pw-group-system-IDs
 * @property int $permissionTemplateID Template ID of the permission template. #pw-group-system-IDs
 * @property int $externalPageID Page ID of page assigned to $page API variable when externally bootstrapped #pw-group-system-IDs
 * @property array $preloadPageIDs Page IDs of pages that will always be preloaded at beginning of request #pw-group-system-IDs
 * @property int $installed Timestamp of when this PW was installed, set automatically by the installer for future compatibility detection. #pw-group-system
 * 
 * @method array|string wireMail($key = '', $value = null)
 * @method array imageSizes($key = '', $value = null)
 * @method array|bool|string|int|float imageSizerOptions($key = '', $value = null)
 * @method array|int|bool webpOptions($key = '', $value = null)
 * @method array|string contentTypes($key = '', $value = null)
 * @method array|string fileContentTypes($key = '', $value = null)
 * @method array|string|bool fileCompilerOptions($key = '', $value = null)
 * @method array|string|string[] dbOptions($key = '', $value = null)
 * @method array|string|string[] dbSqlModes($key = '', $value = null)
 * @method array|int|bool pageList($key = '', $value = null)
 * @method array|bool pageEdit($key = '', $value = null)
 * @method array|string pageAdd($key = '', $value = null)
 * @method array|string moduleInstall($key = '', $value = null)
 * @method array|string substituteModules($key = '', $value = null)
 * @method array|string|bool AdminThemeUikit($key = '', $value = null)
 * @method array|string modals($key = '', $value = null)
 * @method array|bool markupQA($key = '', $value = null)
 * @method array|string statusFiles($key = '', $value = null)
 *
 */
class Config extends WireData {

	/**
	 * Constant for verbose debug mode (uses more memory)
	 * 
	 */
	const debugVerbose = 2;

	/**
	 * Constant for core development debug mode (makes it use newer JS libraries in some cases)
	 * 
	 */
	const debugDev = 'dev';

	/**
	 * Get config property
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return string|array|int|bool|object|callable|null
	 * 
	 */
	public function get($key) {
		$value = parent::get($key);
		if($value === null) {
			// runtime properties
			if($key === 'serverProtocol') {
				$value = $this->serverProtocol();
			} else if($key === 'tableSalt') {
				$value = parent::get('installed');
				if(!$value) $value = @filemtime($this->paths->assets . 'active.php'); 
				$this->data['tableSalt'] = $value;
			}
		}
		return $value;
	}

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
	 * #pw-group-URLs
	 * 
	 * @param string|Wire $for Predefined ProcessWire URLs property or module name
	 * @return string|null
	 * 
	 */
	public function url($for) {
		return $this->urls->get($for);
	}

	/**
	 * Get URL for requested resource or module or get all URLs if no argument
	 * 
	 * #pw-group-URLs
	 * 
	 * @param string|Wire $for Predefined ProcessWire URLs property or module name
	 * @return null|string|Paths
	 * @since 3.0.130
	 * 
	 */
	public function urls($for = '') { 
		return $for === '' ? $this->urls : $this->url($for); 
	}

	/**
	 * Given a directory to a named location, updates $config->paths and $config->urls for it
	 * 
	 * - Paths relative to PW installation root should omit the leading slash, i.e. use `site/templates/` and NOT `/site/templates/`.
	 * 
	 * - If specifying just the `$dir` argument, it updates both `$config->paths` and `$config->urls` for it.
	 * 
	 * - If specifying both `$dir` and `$url` arguments, then `$dir` refers to `$config->paths` and `$url` refers to `$config->urls`.
	 * 
	 * - The `$for` argument can be: `cache`, `logs`, `files`, `tmp`, `templates`, or one of your own. Other named locations may
	 *   also work, but since they can potentially be used before PW’s “ready” state, they may not be reliable.
	 * 
	 * - **Warning:** anything that changes a system URL may make the URL no longer have the protection of the root .htaccess file.
	 *   As a result, if you modify system URLs for anything on a live server, you should also update your .htaccess file to
	 *   reflect your changes (while leaving existing rules for original URL in place).
	 * 
	 * The following example would be in /site/init.php or /site/ready.php (or equivalent module method). In this example we
	 * are changing the location (path and URL) of our /site/templates/ to use a new version of the files in /site/dev-templates/
	 * so that we can test them out with user 'karen', while all other users on the site get our regular templates. 
	 * ~~~~~
	 * // change templates path and URL to /site/dev-templates/ when user name is 'karen'
	 * if($user->name == 'karen') {
	 *   $config->setLocation('templates', 'site/dev-templates/'); 
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URLs
	 * #pw-group-paths
	 * 
	 * @param string $for Named location from `$config->paths` or `$config->urls`, one of: `cache`, `logs`, `files`, `tmp`, `templates`, or your own. 
	 * @param string $dir Directory or URL to the location. Should be either a path or URL relative to current installation root (recommended), 
	 *   or an absolute disk path that resolves somewhere in current installation root. If specifying an absolute path outside of the installation
	 *   root, then you’ll also want to provide the $url argument since PW won’t know it. You may also specify a blank string for this argument 
	 *   if you only want to set the $url argument. 
	 * @param string|bool $url If the $dir argument represents both the path and URL relative to site root, you can omit this argument. 
	 *   If path and URL cannot be derived from one another, or you only want to modify the $url (leaving $dir blank), you 
	 *   can specify the URL in this argument. Specify boolean false if you only want to set the $dir (path) and not detect the $url from it. 
	 * @return self
	 * @throws WireException If request cannot be accommodated
	 * @since 3.0.141
	 * 
	 */
	public function setLocation($for, $dir, $url = '') {
		
		if($for === 'root') throw new WireException('Root path can only be changed at boot');
		
		if(!empty($dir)) {
			$rootPath = $this->paths->get('root');
			
			// make sure path uses unix-style slashes
			$dir = Paths::normalizeSeparators($dir);

			// if given path is inclusive of root path, make path relative to site root
			if(strpos($dir, $rootPath) === 0) $dir = substr($dir, strlen($rootPath));

			// ensure trailing slash
			if(substr($dir, -1) !== '/') $dir .= '/';
		}
		
		// now determine the URL to set
		if($url === false) {
			// arguments say to skip setting URL
		} else if(empty($url)) {
			// URL and path are the same relative to site root
			if(!empty($dir)) $url = $dir;
		} else {
			// given a custom URL
			$rootUrl = $this->urls->get('root');
			// if URL begins at PW installation root, remove the root part of the URL
			if(strpos($url, $rootUrl) === 0) $url = substr($url, strlen($rootUrl)); 
			// ensure trailing slash
			if(substr($url, -1) !== '/' && strpos($url, '?') === false && strpos($url, '#') === false) $url .= '/';
		}
		
		if(!empty($dir)) $this->paths->set($for, $dir);
		if(!empty($url)) $this->urls->set($for, $url);
		
		return $this;
	}

	/**
	 * Change or set just the server disk path for the named location (leaving URL as-is)
	 * 
	 * - If you want to update both disk path and URL at the same time, or if URL and path are going to be the same relative to 
	 *   installation root, use the `setLocation()` method instead.
	 * 
	 * - Paths relative to PW installation root should omit the leading slash, i.e. use `site/templates/` and NOT `/site/templates/`.
	 * 
	 * - The `$for` argument can be: `cache`, `logs`, `files`, `tmp`, `templates`, or one of your own. Other named locations may
	 *   also work, but since they can potentially be used before PW’s “ready” state, they may not be reliable.
	 * 
	 * #pw-group-paths
	 * 
	 * @param string $for Named location from `$config->paths`, one of: `cache`, `logs`, `files`, `tmp`, `templates`, or your own. 
	 * @param string $path Path relative to PW installation root (no leading slash), or absolute path if not. 
	 * @return self
	 * @throws WireException
	 * @since 3.0.141
	 * 
	 */
	public function setPath($for, $path) {
		return $this->setLocation($for, $path, false);
	}

	/**
	 * Change or set just the URL for the named location (leaving server disk path as-is)
	 * 
	 * - If you want to update both disk path and URL at the same time, or if URL and path are going to be the same relative to 
	 *   installation root, use the `setLocation()` method instead.
	 *
	 * - Paths relative to PW installation root should omit the leading slash, i.e. use `site/templates/` and NOT `/site/templates/`.
	 *
	 * - The `$for` argument can be: `cache`, `logs`, `files`, `tmp`, `templates`, or one of your own. Other named locations may
	 *   also work, but since they can potentially be used before PW’s “ready” state, they may not be reliable.
	 * 
	 * - **Warning:** anything that changes a system URL may make the URL no longer have the protection of the root .htaccess file.
	 *   As a result, if you modify system URLs for anything on a live server, you should also update your .htaccess file to
	 *   reflect your changes (while leaving existing rules for original URL in place).
	 * 
	 * The following examples would go in /site/ready.php. 
	 * 
	 * Let’s say we created a symbolic link in our web root `/tiedostot/` (Finnish for “files”) that points to /site/assets/files/.
	 * We want all of our file URLs to appear as “/tiedostot/1234/img.jpg” rather than “/site/assets/files/1234/img.jpg”. We would
	 * change the URL for ProcessWire’s `$config->urls->files` to point there like this example below. (Please also see warning above)
	 * ~~~~~
	 * if($page->template != 'admin') {
	 *   $config->setUrl('files', 'tiedostot/'); 
	 * }
	 * ~~~~~
	 * In this next example, we are changing all of our file URLs on the front-end to point a cookieless subdomain that maps all 
	 * requests to the root path of https://files.domain.com to /site/assets/files/. The example works for CDNs as well. 
	 * ~~~~~
	 * if($page->template != 'admin) {
	 *   $config->setUrl('files', 'https://files.domain.com/'); 
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URLs
	 *
	 * @param string $for Named location from `$config->urls`, one of: `cache`, `logs`, `files`, `tmp`, `templates`, or your own. 
	 * @param string $url URL relative to PW installation root (no leading slash) or absolute URL if not (optionally including scheme and domain).
	 * @return self
	 * @throws WireException
	 * @since 3.0.141
	 *
	 */
	public function setUrl($for, $url) {
		return $this->setLocation($for, '', $url);
	}

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
	 * #pw-group-paths
	 * 
	 * @param string $for Predefined ProcessWire paths property or module class name
	 * @return null|string
	 * 
	 */
	public function path($for) {
		return $this->paths->get($for);
	}

	/**
	 * Get disk path for requested resource or module or get all paths if no argument
	 *
	 * #pw-group-paths
	 *
	 * @param string $for Predefined ProcessWire paths property or module name
	 * @return null|string|Paths
	 * @since 3.0.130
	 *
	 */
	public function paths($for = '') { 
		return $for === '' ? $this->paths : $this->path($for); 
	}

	/**
	 * List of config keys that are also exported in javascript
	 *
	 */
	protected $jsFields = array();
	
	/**
	 * Values $config->jsConfig()
	 *
	 * @var array
	 *
	 */
	protected $jsData = array();

	/**
	 * Set or retrieve a config value to be shared with javascript
	 * 
	 * Values are set to the Javascript variable `ProcessWire.config[key]`.
	 * 
	 * Note: In ProcessWire 3.0.173+ when setting new values, it is preferable to use 
	 * $config->jsConfig() instead, unless your intended use is to share an 
	 * existing $config property with JS. 
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
	 * #pw-group-js
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
			$data = array_merge($data, $this->jsData);
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
				if(isset($this->jsData[$key])) return $this->jsData[$key];
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
	 * Set or retrieve a config value exclusive to Javascript (ProcessWire.config)
	 *
	 * Values are set to the Javascript variable `ProcessWire.config[key]`.
	 * 
	 * Unlike $config->js(), values get or set are exclusive to JS config only. 
	 * 
	 * Values set with this method can be retrieved via $config->js() or $config->jsConfig(),
	 * but they cannot be retrieved from $config->['key'] or $config->get('key').
	 * 
	 * If setting a new property for the JS config it is recommended that you use this
	 * method rather than $config->js() in ProcessWire 3.0.173+. If backwards compatibility
	 * is needed then you should still use $config->js().
	 *
	 * 1. Specify a $key and $value to set a JS config value.
	 *
	 * 2. Specify only a $key and omit the $value in order to retrieve an existing set value.
	 *
	 * 3. Specify no params to retrieve in array of all existing set values.
	 *
	 * ~~~~~
	 * // Set a property from PHP
	 * $config->jsConfig('mySettings', [
	 *   'foo' => 'bar',
	 *   'bar' => 123,
	 * ]);
	 *
	 * // Get a property (from PHP)
	 * $mySettings = $config->jsConfig('mySettings');
	 * ~~~~~
	 * ~~~~~
	 * // Get a property (from Javascript):
	 * var mySettings = ProcessWire.config.mySettings;
	 * console.log(mySettings.foo);
	 * console.log(mySettings.bar);
	 * ~~~~~
	 * 
	 * #pw-group-js
	 *
	 * @param string $key Name of property to get or set or omit to return all data
	 * @param mixed|null $value Specify value to set or omit (null) to get
	 * @return mixed|null|array|self Returns null if $key not found, value when getting, self when setting, or array when getting all
	 * @since 3.0.173
	 *
	 */
	public function jsConfig($key = null, $value = null) {
		if($key === null) return $this->jsData; // get all
		if($value === null) return isset($this->jsData[$key]) ? $this->jsData[$key] : null; // get property
		$this->jsData[$key] = $value; // set property
		return $this;
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
	 * 
	 * // To unset a property specify null for first argument and property to unset as second argument
	 * $config->fileCompilerOptions(null, 'siteOnly'); 
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
				if($property === null && is_string($arguments[1])) {
					// unset property
					$property = $arguments[1];
					unset($value[$property]); 
				} else {
					// set property with value
					$value[$property] = $arguments[1];
				}
				parent::set($method, $value);
			}
		} else if($numArgs === 1) {
			// just set a value
			parent::set($method, $arguments[0]);
		}
		
		return $this;
	}

	/**
	 * Return true if current PHP version is equal to or newer than the given version 
	 * 
	 * ~~~~~
	 * if($config->phpVersion('7.0.0')) {
	 *   // PHP version is 7.x
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-tools
	 * 
	 * @param string|null $minVersion
	 * @return bool
	 * @since 3.0.101
	 * 
	 */
	public function phpVersion($minVersion) {
		return version_compare(PHP_VERSION, $minVersion) >= 0;
	}

	/**
	 * Check if current ProcessWire version is equal to or newer than given version, or return current version
	 * 
	 * If no version argument is given, it simply returns the current ProcessWire version (3.0.130+)
	 * 
	 * ~~~~~
	 * if($config->version('3.0.100')) {
	 *   // ProcessWire version is 3.0.100 or newer
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-tools
	 * 
	 * @param string $minVersion Specify version string if you want to compare against current version
	 * @return bool|string Returns current version if no argument given (3.0.130+), OR boolean if given a version argument: 
	 *  - If given version is older than current, returns false.
	 *  - If given version is equal to or newer than current, returns true.
	 * @since 3.0.106 with no-argument behavior added in 3.0.130
	 * 
	 */
	public function version($minVersion = '') {
		if($minVersion === '') return $this->version;
		return version_compare($this->version, $minVersion) >= 0;
	}

	/**
	 * Was this ProcessWire installation installed after a particular date?
	 * 
	 * #pw-group-tools
	 * 
	 * @param int|string $date Unix timestamp or strtotime() compatible date string
	 * @return bool
	 * @see Config::installedBefore(), Config::installed
	 * @since 3.0.129
	 * 
	 */
	public function installedAfter($date) {
		if(!ctype_digit("$date")) $date = strtotime($date);
		return $this->installed > $date; 
	}

	/**
	 * Was this ProcessWire installation installed before a particular date?
	 * 
	 * #pw-group-tools
	 *
	 * @param int|string $date Unix timestamp or strtotime() compatible date string
	 * @return bool
	 * @see Config::installedAfter(), Config::installed
	 * @since 3.0.129
	 *
	 */
	public function installedBefore($date) {
		if(!ctype_digit("$date")) $date = strtotime($date);
		return $this->installed < $date; 
	}

	/**
	 * Get current server protocol (for example: "HTTP/1.1")
	 * 
	 * This can be accessed by property `$config->serverProtocol`
	 * 
	 * #pw-group-tools
	 * #pw-group-runtime
	 * 
	 * @return string
	 * @since 3.0.166
	 * 
	 */
	protected function serverProtocol() {
		$protos = array('HTTP/1.1', 'HTTP/1.0', 'HTTP/2', 'HTTP/2.0');
		$proto = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		return $protos[(int) array_search($proto, $protos, true)];
	}

	/**
	 * Current unsanitized request URL
	 * 
	 * - This is an alternative to `$input->url()` that’s available prior to API ready state.
	 * - Useful if you need to know request URL from /site/config.php or other boot file.
	 * - Returned value does not include query string, if present. 
	 * - Returned value includes installation subdirectory, if present. 
	 * 
	 * ~~~~~
	 * if($config->requestUrl() === '/products/2021/') {
	 *   // current request URL is exactly “/products/2021/”
	 * }
	 * if($config->requestUrl('/products/2021/')) {
	 *   // current request matches “/products/2021/” somewhere in URL
	 * }
	 * if($config->requestUrl([ 'foo', 'bar', 'baz' ])) {
	 *   // current request has one or more of 'foo', 'bar', 'baz' in the URL
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URLs
	 * #pw-group-tools 
	 * #pw-group-runtime
	 *
	 * @param string|array $match Optionally return URL only if some part matches given string(s) (default='')
	 * @param string $get Specify 'path' to get and/or match path, 'query' to get and/or match query string, or omit for URL (default='')
	 * @return string Returns URL string or blank string if $match argument used and doesn’t match.
	 * @since 3.0.175
	 * 
	 */
	public function requestUrl($match = '', $get = '') {
		if(empty($_SERVER['REQUEST_URI'])) return '';
		$url = $_SERVER['REQUEST_URI'];
		$query = '';
		if(strpos($url, '?') !== false) {
			list($url, $query) = explode('?', $url, 2);
		}
		if($get === 'query') {
			$url = $query;
		} else if($get === 'path') {
			$rootUrl = $this->urls->root;
			if($rootUrl !== '/' && strpos($url, $rootUrl) === 0) {
				$url = substr($url, strlen($rootUrl) - 1);
			}
		}
		if(!strlen($url)) return '';
		if(is_array($match)) {
			$found = false;
			foreach($match as $m) {
				if(strpos($url, $m) !== false) $found = true;
				if($found) break;
			}
			if(count($match) && !$found) $url = '';
		} else if(strlen($match)) {
			if(strpos($url, $match) === false) $url = '';
		}
		return $url;
	}

	/**
	 * Current unsanitized request path (URL sans ProcessWire installation subdirectory, if present)
	 * 
	 * This excludes any subdirectories leading to ProcessWire installation root, if present.
	 * Useful if you need to know request path from /site/config.php or other boot file.
	 * 
	 * ~~~~~
	 * if(strpos($config->requestPath(), '/processwire/') === 0) {
	 *   // current request path starts with “/processwire/”
	 * }
	 * if($config->requestPath('/processwire/')) {
	 *   // the text “/processwire/” appears somewhere in current request path
	 * }
	 * if($config->requestPath([ 'foo', 'bar', 'baz' ])) {
	 *   // current request has one or more of 'foo', 'bar', 'baz' in the path
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URLs
	 * #pw-group-tools
	 * #pw-group-runtime
	 *
	 * @param string|array $match Optionally return path only if some part matches given string(s) (default='')
	 * @return string Returns path string or blank string if $match argument used and doesn’t match.
	 * @since 3.0.175
	 *
	 */
	public function requestPath($match = '') {
		return $this->requestUrl($match, 'path');
	}

	/**
	 * Current request method
	 * 
	 * This is an alternative to `$input->requestMethod()` that’s available prior to API ready state.
	 * Useful if you need to match request method from /site/config.php or other boot file.
	 * 
	 * ~~~~~
	 * if($config->requestMethod('post')) { 
	 *   // request method is POST
	 * }
	 * if($config->requestMethod() === 'GET') {
	 *   // request method is GET
	 * }
	 * $method = $config->requestMethod([ 'POST', 'get' ]);
	 * if($method) {
	 *   // method is either 'POST' or 'GET'
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-tools
	 * #pw-group-runtime
	 * 
	 * @param string|array $match Return found method if request method equals one given (blank if not), not case sensitive (default='')
	 * @return string Returns one of GET, POST, HEAD, PUT, DELETE, OPTIONS, PATCH, OTHER or blank string if no match
	 * @since 3.0.175
	 * 
	 */
	public function requestMethod($match = '') {
		$methods = array('GET', 'POST', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'PATCH');
		$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
		$key = array_search($method, $methods);
		$method = $key === false ? 'OTHER' : $methods[$key];
		if(is_array($match)) {
			$found = '';
			foreach($match as $m) {
				$m = strtoupper($m);
				if($m === $method) $found = $method;
				if($found) break;
			}
			return $found;
		}
		return ($match ? strtoupper($match) === $method : $method);
	}
	
	/**
	 * Set the current ProcessWire instance for this object
	 *
	 * #pw-internal
	 *
	 * @param ProcessWire $wire
	 *
	 */
	public function setWire(ProcessWire $wire) {
		parent::setWire($wire);
		foreach(array('paths', 'urls', 'styles', 'scripts') as $key) {
			$value = $this->get($key);
			if($value instanceof Wire) $value->setWire($wire);
		}
	}

	/**
	 * Given array of file asset URLs return them with cache-busting version strings
	 *
	 * URLs that aready have query strings or URLs with scheme (i.e. https://) are ignored,
	 * except for URLs that already have a core version query string, i.e. `?v=3.0.227`
	 * may be converted to a different version string when appropriate. 
	 * 
	 * URLs that do not resolve to a physical file on the file system, relative URLs, or
	 * URLs that are outside of ProcessWire’s web root, are only eligible to receive a
	 * common/shared version in the URL (like the core version).
	 * 
	 * To set a different default value for the `$useVersion` argument, you can populate
	 * the `$config->useVersionUrls` setting in your /site/config.php with the default
	 * value you want to substitute. 
	 *
	 * ~~~~~
	 * foreach($config->versionUrls($config->styles) as $url) {
	 *   echo "<link rel='stylesheet' href='$url' />";
	 * }
	 * // there is also this shortcut for the above
	 * foreach($config->styles->urls() as $url) {
	 *   echo "<link rel='stylesheet' href='$url' />";
	 * }
	 * ~~~~~
	 *
	 * #pw-group-URLs
	 * #pw-group-tools
	 *
	 * @param array|FilenameArray|WireArray|\ArrayObject $urls Array of URLs to file assets such as JS/CSS files.
	 * @param bool|null|string $useVersion What to use for the version string (`null` is default):
	 *  - `true` (bool): Get version from filemtime.
	 *  - `false` (bool): Never get file version, just use $config->version.
	 *  - `null` (null): Auto-detect: use file version in debug mode or dev branch only, $config->version otherwise.
	 *  - `foobar` (string): Specify any string to be the version to use on all URLs needing it.
	 * `- ?foo=bar` (string): Optionally specify your own query string variable=value.
	 *  - The default value (null) can be overridden by the `$config->useVersionUrls` setting. 
	 * @return array Array of URLs updated with version strings where needed
	 * @since 3.0.227
	 *
	 */
	public function versionUrls($urls, $useVersion = null) {

		$a = array();
		$rootUrl = $this->urls->root;
		$rootPath = $this->paths->root;
		$coreVersionStr = "?v=$this->version";

		if($useVersion === null) {
			// if useVersion argument not specified pull from $config->useVersionUrls
			$useVersion = $this->useVersionUrls;
			if($useVersion === null) {
				// if null or still not specified, auto-detect what to use
				$useVersion = ($this->debug || ProcessWire::versionSuffix === 'dev');
			}
		}
		
		if(is_string($useVersion)) {
			// custom version string specified 
			if(!ctype_alnum(str_replace(array('.', '-', '_', '?', '='), '', $useVersion))) {
				// if it fails sanitization then fallback to core version
				$useVersion = false;
				$versionStr = $coreVersionStr;
			} else {
				// use custom version str
				$versionStr = $useVersion;
				if(strpos($versionStr, '?') === false) $versionStr = "?v=$versionStr";
			}
		} else {
			// use core version when appropriate
			$versionStr = $coreVersionStr;
		}

		foreach($urls as $url) {
			if(strpos($url, $coreVersionStr)) {
				// url already has core version present in it
				if($useVersion === false) {
					// use as-is since this is already what's requested
					$a[] = $url;
					continue;
				}
				// remove existing core-version query string
				list($u, $r) = explode($coreVersionStr, $url, 2);
				if(!strlen($r)) $url = $u;
			}
			if(strpos($url, '?') !== false || strpos($url, '//') !== false) {
				// leave URL with query string or scheme:// alone
				$a[] = $url;
			} else if($useVersion === true && strpos($url, $rootUrl) === 0) {
				// use filemtime based version
				$f = $rootPath . substr($url, strlen($rootUrl));
				if(is_readable($f)) {
					$a[] = "$url?" . base_convert((int) filemtime($f), 10, 36);
				} else {
					$a[] = $url . $versionStr;
				}
			} else {
				// use standard or specified versino string
				$a[] = $url . $versionStr;
			}
		}

		return $a;
	}

	/**
	 * Given a file asset URLs return it with cache-busting version string
	 *
	 * URLs that aready have query strings are left alone.
	 *
	 * #pw-group-URLs
	 * #pw-group-tools
	 *
	 * @param string $url URL to a file asset (such as JS/CSS file)
	 * @param bool|null|string $useVersion See versionUrls() method for description of this argument.
	 * @return string URL updated with version strings where necessary
	 * @since 3.0.227
	 * @see Config::versionUrls()
	 *
	 */
	public function versionUrl($url, $useVersion = null) {
		$a = $this->versionUrls(array($url), $useVersion);
		return isset($a[0]) ? $a[0] : $url;
	}

}
