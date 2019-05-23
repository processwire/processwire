<?php namespace ProcessWire;

/**
 * ProcessWire Configuration File
 *
 * Configuration options for ProcessWire
 * 
 * To override any of these options, copy the option you want to modify to
 * /site/config.php and adjust as you see fit. Options in /site/config.php
 * override those in this file. 
 * 
 * You may also make up your own configuration options by assigning them 
 * in /site/config.php 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * 
 * TABLE OF CONTENTS
 * ===============================
 * 1. System modes
 * 2. Dates and times
 * 3. Session
 * 4. Template files
 * 5. Files and assets
 * 6. HTTP and input 
 * 7. Database
 * 8. Modules
 * 9. Misc
 * 10. Runtime 
 * 11. System
 * 
 * @var Config $config
 * 
 */

if(!defined("PROCESSWIRE")) die();

/*** 1. SYSTEM MODES ****************************************************************************/

/**
 * Enable debug mode?
 * 
 * Debug mode causes additional info to appear for use during site development and debugging. 
 * This is almost always recommended for sites in development. However, you should
 * always have this disabled for live/production sites since it reveals more information
 * than is advisible for security. 
 * 
 * You may also set this to the constant `Config::debugVerbose` to enable verbose debug mode,
 * which uses more memory and time. 
 * 
 * #notes This enables debug mode for ALL requests. See the debugIf option for an alternative.
 * 
 * @var bool
 *
 */
$config->debug = false;

/**
 * Enable debug mode if condition is met
 *
 * Set debug mode to be false above, and then specify any one of the following here:
 * 1) IP address of user required to enable debug mode;
 * 2) Your own callable function name (i.e. "debug_mode") in /site/config.php that returns
 * true or false for debug mode;
 * 3) PCRE regular expression to match IP address of user (must start and end with a "/"
 * slash). If IP address matches, then debug mode is enabled. Regular expression
 * example: /^123\.456\.789\./ would match all IP addresses that started with 123.456.789.
 * 
 * #notes When used, this overrides $config->debug, changing it at runtime automatically. 
 * @var string
 *
 */
$config->debugIf = '';

/**
 * Tools, and their order, to show in debug mode (admin)
 * 
 * Options include: pages, api, session, modules, hooks, database, db, timers, user, input, cache, autoload
 * 
 * @var array
 * 
 */
$config->debugTools = array(
	'pages',
	'api',
	'session',
	'modules',
	'hooks',
	'database', // PDO
	'db', // mysqli
	'timers',
	'user',
	'input',
	'cache',
	'autoload',
);

/**
 * Enable ProcessWire advanced development mode?
 * 
 * Turns on additional options in ProcessWire Admin that aren't applicable in all instances.
 * Be careful with this as some options configured in advanced mode cannot be removed once
 * set (at least not without going directly into the database). 
 * 
 * #notes Recommended mode is false, except occasionally during ProcessWire core or module development.
 * @var bool
 *
 */
$config->advanced = false;

/**
 * Enable demo mode?
 * 
 * If true, disables save functions in Process modules (admin).
 * 
 * @var bool
 * 
 */
$config->demo = false;

/**
 * Enable core API variables to be accessed as function calls?
 * 
 * Benefits are better type hinting, always in scope, and potentially shorter API calls.
 * See the file /wire/core/FunctionsAPI.php for details on these functions.
 * 
 * @var bool
 * 
 */
$config->useFunctionsAPI = false;

/**
 * Enable use of front-end markup regions?
 *
 * When enabled, HTML elements with an "id" attribute that are output before the opening 
 * `<!doctype>` or `<html>` tag can replace elements in the document that have the same id. 
 * Also supports append, prepend, replace, remove, before and after options. 
 *
 * @var bool
 *
 */
$config->useMarkupRegions = false;

/**
 * Disable all HTTPS requirements?
 * 
 * Use this option only for development or staging environments, on sites where you are 
 * otherwise requiring HTTPS. By setting this option to something other than false, you
 * can disable any HTTPS requirements specified per-template, enabling you to use your 
 * site without SSL during development or staging, etc.
 * 
 * The following options are available:
 * - boolean true: Disable HTTPS requirements globally
 * - string containing hostname: Disable HTTPS requirements only for specified hostname.
 * - array containing hostnames: Disable HTTPS requirements for specified hostnames. 
 * 
 * @var bool|string|array
 *
 */
$config->noHTTPS = false;



/*** 2. DATES & TIMES *************************************************************************/

/**
 * Default time zone
 * 
 * Must be a [PHP timezone string](http://php.net/manual/en/timezones.php)
 *
 * #input timezone
 * @var string 
 * 
 */
$config->timezone = 'America/New_York'; 

/**
 * System date format
 *
 * Default system date format. Preferably in a format that is string sortable.
 *
 * #notes This should be a [PHP date string](http://www.php.net/manual/en/function.date.php).
 *
 * @var string
 *
 */
$config->dateFormat = 'Y-m-d H:i:s';




/*** 3. SESSION *********************************************************************************/

/**
 * Session name
 * 
 * Default session name as used in session cookie. You may wish to change this if running
 * multiple ProcessWire installations on the same server. By giving each installation a unique
 * session name, you can stay logged into multiple installations at once. 
 * 
 * #notes Note that changing this will automatically logout any current sessions. 
 * @var string
 *
 */
$config->sessionName = 'wire';

/**
 * Session name when on HTTPS
 * 
 * Same as session name but when on HTTPS. This is only used when the sessionCookieSecure
 * option is enabled (default). When blank (default), it will be sessionName + 's'.
 * 
 * @var string
 * 
 */
$config->sessionNameSecure = '';

/**
 * Session expiration seconds
 * 
 * How many seconds of inactivity before session expires
 * 
 * @var int
 *
 */
$config->sessionExpireSeconds = 86400;

/**
 * Are sessions allowed?
 * 
 * Use this to determine at runtime whether or not a session is allowed for the current request. 
 * Otherwise, this should always be boolean TRUE. When using this option, we recommend 
 * providing a callable function like below. Make sure that you put in some logic to enable
 * sessions on admin pages at minimum. The callable function receives a single $wire argument
 * which is the ProcessWire instance. 
 * 
 * Note that the API is not fully ready when this function is called, so the current $page and
 * the current $user are not yet known, nor is the $input API variable available. 
 * 
 * Also note that if anything in the request calls upon $session->CSRF, then a session is 
 * automatically enabled. 
 *
 * ~~~~~
 * $config->sessionAllow = function($session) {
 * 
 *   // if there is a session cookie, a session is likely already in use so keep it going
 *   if($session->hasCookie()) return true;
 * 
 *   // if URL is an admin URL, allow session (replace /processwire/ with your admin URL)
 *   if(strpos($_SERVER['REQUEST_URI'], '/processwire/) === 0) return true;
 * 
 *   // otherwise disallow session
 *   return false;
 * };
 * ~~~~~
 * 
 * @var bool|callable Should be boolean, or callable that returns boolean. 
 * 
 */
$config->sessionAllow = true; 


/**
 * Use session challenge?
 * 
 * Should login sessions have a challenge key? (for extra security, recommended)
 *
 * @var bool
 *
 */
$config->sessionChallenge = true;

/**
 * Use session fingerprint?
 * 
 * Should login sessions be tied to IP and user agent?
 * IP fingerprinting may be problematic on dynamic IPs. 
 * Below are the possible values: 
 * 
 * 	0 or false: Fingerprint off
 * 	1 or true: Fingerprint on with default/recommended setting (currently 10). 
 * 	2: Fingerprint only the remote IP
 * 	4: Fingerprint only the forwarded/client IP (can be spoofed)
 * 	8: Fingerprint only the useragent
 * 	10: Fingerprint the remote IP and useragent (default)
 * 	12: Fingerprint the forwarded/client IP and useragent
 * 	14: Fingerprint the remote IP, forwarded/client IP and useragent (all). 
 * 
 * If using fingerprint in an environment where the user’s 
 * IP address may change during the session, you should
 * fingerprint only the useragent, or disable fingerprinting.
 *
 * If using fingerprint with an AWS load balancer, you should 
 * use one of the options that uses the “client IP” rather than 
 * the “remote IP”, fingerprint only the useragent, or disable 
 * fingerprinting.
 * 
 * @var int
 *
 */
$config->sessionFingerprint = 1;

/**
 * Force current session IP address (overriding auto-detect)
 * 
 * This overrides the return value of `$session->getIP()` method.
 * Use this property only for setting the IP address. To get the IP address
 * always use the `$session->getIP()` method instead. 
 * 
 * This is useful if you are in an environment where the remote IP address 
 * comes from some property other than the REMOTE_ADDR in $_SERVER. For instance,
 * if you are using a load balancer, what’s usually detected as the IP address is
 * actually the IP address between the load balancer and the server, rather than
 * the client IP address. So in that case, you’d want to set this property as
 * follows:
 * ~~~~~
 * $config->sessionForceIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
 * ~~~~~
 * If you don’t have a specific need to override the IP address of the user
 * then you should leave this blank.
 * 
 * @var string
 * 
 */
$config->sessionForceIP = '';

/**
 * Use secure cookies when on HTTPS?
 * 
 * When enabled, separate sessions will be maintained for
 * HTTP vs. HTTPS. This ensures the session is secure on HTTPS.
 * The tradeoff is that switching between HTTP and HTTPS means
 * that you may be logged in on one and not the other. 
 * 
 * 0 or false: secure cookies off
 * 1 or true: secure cookies on (default)
 * 
 * @var int
 * 
 */
$config->sessionCookieSecure = 1; 

/**
 * Cookie domain for sessions
 * 
 * Enables a session to traverse multiple subdomains.
 * Specify a string having “.domain.com” (with leading period) or NULL to disable (default/recommended). 
 * 
 * @var string|null
 *
 */
$config->sessionCookieDomain = null;

/**
 * Number of session history entries to record.
 *
 * When enabled (with a value > 0) a history of pageviews will be recorded in the
 * session. These can be retrieved with $session->getHistory().
 *
 * @var int
 *
 */
$config->sessionHistory = 0; 

/**
 * Hash method to use for passwords.
 *
 * Can be any available with your PHP's hash() installation. For instance, you may prefer
 * to use something like sha256 if supported by your PHP installation.
 *
 * @deprecated Only here for backwards compatibility.
 *
 */
$config->userAuthHashType = 'sha1';

/**
 * Names (string) or IDs (int) of roles that are not allowed to login
 *
 * Note that you must create these roles yourself in the admin. When a user has
 * one of these named roles, $session->login() will not accept a login from them.
 * This affects the admin login form and any other login forms that use ProcessWire’s
 * session system.
 * 
 * The default value specifies a role name of "login-disabled", meaning if you create
 * a role with that name, and assign it to a user, that user will no longer be able
 * to login. 
 *
 * @var array
 *
 */
$config->loginDisabledRoles = array(
	'login-disabled'
);


/*** 4. TEMPLATE FILES **************************************************************************/

/**
 * Allow template files to be compiled?
 * 
 * Set to false do disable the option for compiled template files. 
 * When set to true, it will be used unless a given template's 'compile' option is set to 0.
 * 
 * @var bool
 * 
 */
$config->templateCompile = strlen(__NAMESPACE__) > 0; 

/**
 * Prepend template file 
 * 
 * PHP file in /site/templates/ that will be loaded before each page's template file.
 *
 * #notes Example: _init.php
 * @var string
 *
 */
$config->prependTemplateFile = '';

/**
 * Append template file 
 * 
 * PHP file in /site/templates/ that will be loaded after each page's template file.
 * 
 * #notes Example: _main.php
 * @var string
 *
 */
$config->appendTemplateFile = '';

/**
 * Regular expression to ignore template files
 *
 * When checking for new template files, files matching this PCRE regex will be ignored.
 *
 * #notes The default setting of /^_/ ignores all files that begin with an underscore.
 * @var string
 *
 */
$config->ignoreTemplateFileRegex = '/^_/';

/**
 * Expected extension for template files (we don't recommend changing this)
 *
 */
$config->templateExtension = 'php';




/*** 5. FILES & ASSETS ************************************************************************/

/**
 * Directory mode
 *
 * Octal string permissions assigned to directories created by ProcessWire.
 * Please avoid 0777 if at all possible as that is too open for most installations. 
 * Note that changing this does not change permissions for existing directories, 
 * only newly created directories. 
 * 
 * #notes See [chmod man page](http://ss64.com/bash/chmod.html).
 * #pattern /^0[0-9]{3}$/
 * @var string
 *
 */
$config->chmodDir = "0755";

/**
 * File mode
 *
 * Octal string permissions assigned to files created by ProcessWire.
 * Please avoid 0666 if at all possible as that is too open for most installations. 
 * Note that changing this does not change permissions for existing files, only newly 
 * created/uploaded files.
 * 
 * #notes See [chmod man page](http://ss64.com/bash/chmod.html).
 * #pattern /^0[0-9]{3}$/
 * @var string
 *
 */
$config->chmodFile = "0644";

/**
 * Set this to false if you want to suppress warnings about 0666/0777 permissions that are too open
 * 
 * @var bool 
 * 
 */
$config->chmodWarn = true;

/**
 * Bad file extensions for uploads
 * 
 * File extensions that are always disallowed from uploads (each separated by a space).
 * 
 * @var string
 *
 */
$config->uploadBadExtensions = 'php php3 phtml exe cfm shtml asp pl cgi sh vbs jsp';

/**
 * Secure page files?
 *
 * When, true, prevents http access to file assets of access protected pages.
 *
 * Set to true if you want files on non-public or unpublished pages to be
 * protected from direct URL access.
 *
 * When used, such files will be delivered at a URL that is protected from public access.
 *
 * @var bool
 *
 */
$config->pagefileSecure = false;

/**
 * String that prefixes filenames in PW URLs, becoming a shortcut to a page's file's URL
 *
 * This must be at the end of the URL. For the prefix "-/", a files URL would look like this:
 * /path/to/page/-/filename.jpg => same as: /site/assets/files/123/filename.jpg
 *
 * This should be a prefix that is not the same as any page name, as it takes precedence.
 *
 * This prefix is deprecated. Insert this into your /site/config.php as a temporary fix only if you
 * have broken images from <img> tags placed in textarea fields.
 *
 * @deprecated
 *
 * $config->pagefileUrlPrefix = '-/';
 * 
 */

/**
 * Prefix for secure page files
 *
 * One or more characters prefixed to the pathname of secured file dirs.
 *
 * If use of this feature originated with a pre-2.3 install, this may need to be 
 * specified as "." rather than "-". 
 *
 */
$config->pagefileSecurePathPrefix = '-';

/**
 * Use extended file mapping?
 * 
 * Enable this if you expect to have >30000 pages in your site.
 * 
 * Set to true in /site/config.php if you want files to live in an extended path mapping system
 * that limits the number of directories per path to under 2000.
 *
 * Use this on large sites living on file systems with hard limits on quantity of directories
 * allowed per directory level. For example, ext2 and its 30000 directory limit.
 *
 * Please note that for existing sites, this applies only for new pages created from this
 * point forward.
 *
 * #notes Warning: The extended file mapping feature is not yet widely tested, so consider it beta.
 * @var bool
 *
 */
$config->pagefileExtendedPaths = false;

/**
 * Allowed content types for output by template files
 * 
 * When one of these options is selected for a template, the header will be sent 
 * automatically regardless of whether request is live or cached. 
 * 
 * The keys of the array are file extensions. They are used for identification 
 * and storage purposes. In ProCache, they are used as a file extension which 
 * connects a configured Apache MIME type to the appropriate content type header. 
 * 
 * @var array
 * 
 */
$config->contentTypes = array(
	'html' => 'text/html',
	'txt' => 'text/plain', 
	'json' => 'application/json',
	'xml' => 'application/xml', 
	);

/**
 * File content types
 * 
 * Connects file extentions to content-type headers, used by file passthru functions.
 *
 * Any content types that should be force-download should be preceded with a plus sign.
 * The '?' index must be present to represent a default for all not present.
 * 
 * @var array
 *
 */
$config->fileContentTypes = array(
	'?' => '+application/octet-stream',
	'pdf' => '+application/pdf',
	'doc' => '+application/msword',
	'docx' => '+application/msword',
	'xls' => '+application/excel',
	'xlsx' => '+application/excel',
	'rtf' => '+application/rtf',
	'gif' => 'image/gif',
	'jpg' => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png' => 'image/x-png',
	'svg' => 'image/svg+xml'
	);


/**
 * Image sizer options
 *
 * Default ImageSizer options, as used by $page->image->size(w, h), for example.
 * 
 * #property bool upscaling Upscale if necessary to reach target size? (1=true, 0=false)
 * #property bool cropping Crop if necessary to reach target size? (1=true, 0=false)
 * #property bool autoRotation Automatically correct orientation? (1=true, 0=false)
 * #property bool interlace Use interlaced JPEGs by default? Recommended. (1=true, 0=false)
 * #property string sharpening Sharpening mode, enter one of: none, soft, medium, strong
 * #property int quality Image quality, enter a value between 1 and 100, where 100 is highest quality (and largest files)
 * #property float defaultGamma Default gamma of 0.5 to 4.0 or -1 to disable gamma correction (default=2.0)
 * 
 * @var array
 *
 */
$config->imageSizerOptions = array(
	'upscaling' => true, // upscale if necessary to reach target size?
	'cropping' => true, // crop if necessary to reach target size?
	'autoRotation' => true, // automatically correct orientation?
	'interlace' => false, // use interlaced JPEGs by default? (recommended)
	'sharpening' => 'soft', // sharpening: none | soft | medium | strong
	'quality' => 90, // quality: 1-100 where higher is better but bigger
	'hidpiQuality' => 60, // Same as above quality setting, but specific to hidpi images
	'defaultGamma' => 2.0, // defaultGamma: 0.5 to 4.0 or -1 to disable gamma correction (default=2.0)
	'webpAdd' => false, // set this to true, if the imagesizer engines should create a Webp copy with every (new) image variation
	'webpQuality' => 90, // webpQuality: 1-100 where higher is better but bigger
	);

/**
 * Admin thumbnail image options
 * 
 * Controls the output of the thumbnail images used in image fields presented in the admin.
 * 
 * #property int width Width of thumbnails or 0 for proportional to height (default=0).
 * #property int height Height of thumbnails or 0 for proportional to width (default=100).
 * #property float scale Width/height scale (1=auto detect, 0.5=always hidpi, 1.0=force non-hidpi)
 * #property bool upscaling Upscale if necessary to reach target size? (1=true, 0=false).
 * #property bool cropping Crop if necessary to reach target size? (1=true, 0=false).
 * #property bool autoRotation Automatically correct orientation? (1=true, 0=false).
 * #property string sharpening Sharpening mode, enter one of: none, soft, medium, strong (default=soft).
 * #property int quality Image quality, enter a value between 1 and 100, where 100 is highest quality, and largest files (default=90).
 * #property string suffix Suffix to append to all thumbnail images (1-word of a-z 0-9, default=blank)
 * 
 * @var array
 * 
 */
$config->adminThumbOptions = array(
	'width' => 0, // max width of admin thumbnail or 0 for proportional to height (@deprecated, for legacy use)
	'height' => 100, // max height of admin thumbnail or 0 for proportional to width (@deprecated, for legacy use)
	'gridSize' => 130, // Squared grid size for images (replaces the 'width' and 'height' settings) 
	'scale' => 1, // admin thumb scale (1=allow hidpi, 0.5=always hidpi, 1.0=force non-hidpi)
	'upscaling' => false,
	'cropping' => true,
	'autoRotation' => true, // automatically correct orientation?
	'sharpening' => 'soft', // sharpening: none | soft | medium | strong
	'quality' => 90,
	'suffix' => '', 
	);

/**
 * File compiler options (as used by FileCompiler class)
 *
 * Enables modification of file compiler behaviors. See also $config->moduleCompile
 * and $config->templateCompile settings. 
 *
 * #property bool siteOnly Specify true to prevent compiler from attempting compilation outside files in /site/ (default=false).
 * #property bool showNotices Show notices in admin about compiled files to superuser when logged in (default=true).
 * #property bool logNotices Log notices about compiled files and maintenance to file-compiler.txt log (default=true). 
 * #property string chmodFile Mode to use for created files, i.e. "0644" (uses $config->chmodFile setting by default).
 * #property string chmodDir Mode to use for created dirs, i.e. "0755" (uses $config->chmodDir setting by default). 
 * #property array exclusions Exclude paths that exist within any of these paths (default includes $config->paths->wire).
 * #property array extensions File extensions that we compile (default=php, module, inc).
 * #property string cachePath Path where compiled files are stored (default is $config->paths->cache . 'FileCompiler/')
 *
 * @var array
 *
 */
$config->fileCompilerOptions = array(
	'siteOnly' => false,  // only allow compilation of files in /site/ directory
	'showNotices' => true, // show notices about compiled files to superuser when logged in
	'logNotices' => true, // log notices about compiled files and maintenance to file-compiler.txt log.
	'chmodFile' => '', // mode to use for created files, i.e. "0644"
	'chmodDir' => '',  // mode to use for created directories, i.e. "0755"
	'exclusions' => array(), // exclude filenames or paths that start with any of these
	'extensions' => array('php', 'module', 'inc'), // file extensions we compile
	'cachePath' => $config->paths->cache . 'FileCompiler/', // path where compiled files are stored
	);

/**
 * Temporary directory for uploads
 * 
 * Optionally override PHP's upload_tmp_dir with your own.
 * 
 * @var string
 * 
 * $config->uploadTmpDir = dirname(__FILE__) . '/assets/uploads/'; // example
 *
 */


/*** 6. HTTP & INPUT **************************************************************************/

/**
 * HTTP hosts
 *
 * For added security, specify the host names ProcessWire should recognize.
 *
 * If your site may be accessed from multiple hostnames, you'll also want to use this setting.
 * If left empty, the httpHost will be determined automatically, but use of this whitelist
 * is recommended for production environments.
 *
 * If your hostname uses a port other than 80, make sure to include that as well.
 * For instance "localhost:8888".
 *
 * @var array
 *
 */
$config->httpHosts = array(); 

/**
 * Runtime HTTP host
 * 
 * This is set automatically by ProcessWire at runtime, consisting of one of the values 
 * specified in $config->httpHosts. However, if you set a value for this, it will override
 * ProcessWire's runtime value. 
 * 
 */
$config->httpHost = '';

/**
 * Protect CSRF?
 *
 * Enables CSRF (cross site request forgery) protection on all PW forms, recommended for improved security.
 *
 * @var bool
 *
 */
$config->protectCSRF = true;

/**
 * Maximum URL segments
 * 
 * Maximum number of extra stacked URL segments allowed in a page's URL (including page numbers).
 *
 * i.e. /path/to/page/s1/s2/s3 where s1, s2 and s3 are URL segments that don't resolve to a page, but can be
 * checked in the API via $input->urlSegment1, $input->urlSegment2, $input->urlSegment3, etc.
 * To use this, your template settings (under the URL tab) must take advantage of it. Only change this
 * number if you need more (or fewer) URL segments for some reason.
 * 
 * @var int
 *
 */
$config->maxUrlSegments = 4;

/**
 * Maximum length for any individual URL segment (default=128)
 * 
 * @var int
 * 
 */
$config->maxUrlSegmentLength = 128;

/**
 * Maximum URL/path slashes (depth) for request URLs
 * 
 * The maximum number of slashes that any path requested from ProcessWire may have.
 * Maximum possible value is 60. Minimum recommended value is 10. 
 * 
 * @var int
 * 
 */
$config->maxUrlDepth = 30; 

/**
 * Pagination URL prefix
 *
 * Default prefix used for pagination, i.e. "page2", "page3", etc.
 *
 * If using multi-language page names, please use the setting in LanguageSupportPageNames module settings instead.
 *
 * @var string
 *
 */
$config->pageNumUrlPrefix = 'page';

/**
 * Multiple prefixes that may be used for detecting pagination
 *
 * Typically used for multi-language support and populated automatically at runtime by
 * multi-language support modules. When populated, they override $config->pageNumUrlPrefix.
 *
 * @internal
 *
 * $config->pageNumUrlPrefixes = array();
 *
 */

/**
 * Character set for page names
 * 
 * Set to 'UTF8' (uppercase) to allow for non-ascii word characters in page names.
 * You must also update the .htaccess file to allow non-ascii characters through. 
 * See also $config->pageNameWhitelist, which is used if pageNameCharset is UTF8. 
 * 
 * @var string
 * 
 * #notes Value may be either 'ascii' (lowercase) or 'UTF8' (uppercase).
 * 
 */
$config->pageNameCharset = 'ascii';

/**
 * If 'pageNameCharset' is 'UTF8' then specify the whitelist of allowed characters here
 * 
 * To allow any characters, you can make this blank, however using a whitelist is strongly recommended.
 * Please note this whitelist is only used if pageNameCharset is 'UTF8'. 
 * 
 * @var string
 * 
 */ 
$config->pageNameWhitelist = '-_.abcdefghijklmnopqrstuvwxyz0123456789æåäßöüđжхцчшщюяàáâèéëêěìíïîõòóôøùúûůñçčćďĺľńňŕřšťýžабвгдеёзийклмнопрстуфыэęąśłżź';

/**
 * Name to use for untitled pages
 * 
 * When page has this name, the name will be changed automatically (to a field like title) when it is possible to do so.
 * 
 * @var string
 * 
 */
$config->pageNameUntitled = "untitled";

/**
 * Maximum paginations
 *
 * Maxmum number of supported paginations when using page numbers.
 *
 * @var int
 *
 */
$config->maxPageNum = 999;

/**
 * Input variable order
 *
 * Order that variables with the $input API var are handled when you access $input->some_var.
 *
 * This does not affect the dedicated $input->[get|post|cookie|whitelist] variables/functions.
 * To disable $input->some_var from considering get/post/cookie, make this blank.
 *
 * #notes Possible values are a combination of: "get post cookie whitelist" in any order, separated by 1 space.
 * 
 * @var string
 *
 */
$config->wireInputOrder = 'get post';

/**
 * Lazy-load get/post/cookie input into $input API var?
 * 
 * This is an experimental option for reduced memory usage when a lot of input data is present. 
 * 
 * This prevents PW from keeping separate copies of get/post/cookie data, and it instead works
 * directly from the PHP $_GET, $_POST and $_COOKIE vars.
 * 
 * This option is also useful in that anything you SET to PW’s $input->get/post/cookie also gets
 * set to the equivalent PHP $_GET, $_POST and $_COOKIE. 
 * 
 * @var bool
 * 
 */
$config->wireInputLazy = false;


/*** 7. DATABASE ********************************************************************************/

/**
 * Database name
 *
 */
$config->dbName = '';

/**
 * Database username
 *
 */
$config->dbUser = '';

/**
 * Database password
 *
 */
$config->dbPass = '';

/**
 * Database host
 *
 */
$config->dbHost = '';

/**
 * Database port
 *
 */
$config->dbPort = 3306;

/**
 * Database character set
 * 
 * utf8 is the only recommended value for this. 
 *
 * Note that you should probably not add/change this on an existing site. i.e. don't add this to 
 * an existing ProcessWire installation without asking how in the ProcessWire forums. 
 *
 */
$config->dbCharset = 'utf8';

/**
 * Database engine
 * 
 * May be 'InnoDB' or 'MyISAM'. Avoid changing this after install.
 * 
 */
$config->dbEngine = 'MyISAM';

/**
 * Allow MySQL query caching?
 * 
 * Set to false to to disable query caching. This will make everything run slower so should
 * only used for DB debugging purposes.
 * 
 * @var bool
 *
 */
$config->dbCache = true;

/**
 * MySQL database exec path
 * 
 * Path to mysql/mysqldump commands on the file system
 *
 * This enables faster backups and imports when available.
 *
 * Example: /usr/bin/
 * Example: /Applications/MAMP/Library/bin/
 * 
 * @param string
 *
 */
$config->dbPath = '';

/**
 * Force lowercase tables?
 * 
 * Force any created field_* tables to be lowercase.
 * Recommend value is true except for existing installations that already have mixed case tables.
 * 
 */
$config->dbLowercaseTables = true;

/**
 * Database init command (PDO::MYSQL_ATTR_INIT_COMMAND)
 *
 * Note: Placeholder "{charset}" gets automatically replaced with $config->dbCharset.
 * 
 * @var string
 *
 */
$config->dbInitCommand = "SET NAMES '{charset}'";

/**
 * Set or adjust SQL mode per MySQL version
 * 
 * Array indexes are minimum MySQL version mode applies to. Array values are 
 * the names of the mode(s) to apply. If value is preceded with "remove:" the mode will 
 * be removed, or if preceded with "add:" the mode will be added. If neither is present 
 * then the mode will be set exactly as given. To specify more than one SQL mode for the
 * value, separate them by commas (CSV). To specify multiple statements for the same 
 * version, separate them with a slash "/".
 * 
 * ~~~~~
 * array("5.7.0" => "remove:STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY/add:NO_ZERO_DATE")
 * ~~~~~
 * 
 * @var array
 * 
 */
$config->dbSqlModes = array(
	"5.7.0" => "remove:STRICT_TRANS_TABLES,ONLY_FULL_GROUP_BY"
);

/**
 * A key=>value array of any additional driver-specific connection options.
 * 
 * @var array
 * 
 */
$config->dbOptions = array();

/**
 * Optional DB socket config for sites that need it (for most you should exclude this)
 * 
 * @var string
 *
 */
$config->dbSocket = '';

/**
 * Maximum number of queries WireDatabasePDO will log in memory (when $config->debug is enabled)
 * 
 * @var int
 * 
 */
$config->dbQueryLogMax = 500;

/**
 * Remove 4-byte characters (like emoji) when dbEngine is not utf8mb4?
 * 
 * When charset is not “utf8mb4” and this value is true, 4-byte UTF-8 characters are stripped
 * out of inserted values when possible. Note that this can add some overhead to INSERTs. 
 * 
 * @var bool
 * 
 */
$config->dbStripMB4 = false;



/*** 8. MODULES *********************************************************************************/

/**
 * Use compiled modules?
 *
 * Set to false to disable the use of compiled modules.
 * Set to true to enable PW to compile modules when it determines it is necessary.
 * We recommend keeping this set to true unless all modules in use support PW 3.x natively. 
 *
 * @var bool
 *
 */
$config->moduleCompile = true; 

/**
 * Modules service URL
 * 
 * URL to modules directory service JSON feed.
 *
 * @var string
 *
 */
$config->moduleServiceURL = 'http://modules.processwire.com/export-json/';

/**
 * Modules service API key
 * 
 * API key for modules directory service.
 *
 * @var string
 *
 */
$config->moduleServiceKey = (__NAMESPACE__ ? 'pw300' : 'pw280');

/**
 * Substitute modules
 *
 * Names of substitutute modules for when requested module doesn't exist
 *
 * #notes Specify ModuleName = ReplacementModuleName
 * @var array
 *
 */
$config->substituteModules = array(
	// TinyMCE replaced with CKEditor as default RTE in 2.4.9+
	'InputfieldTinyMCE' => 'InputfieldCKEditor'
);

/**
 * WireMail module(s) default settings
 * 
 * Note you can add any other properties to the wireMail array that are supported by WireMail settings
 * like we’ve done with from, fromName and headers here. Any values set here become defaults for the 
 * WireMail module. 
 *
 * Blacklist property
 * ==================
 * The blacklist property lets you specify email addresses, domains, partial host names or regular
 * expressions that prevent sending to certain email addresses. This is demonstrated by example:
 * ~~~~~
 * // Example of blacklist definition
 * $config->wireMail('blacklist', [
 *   'email@domain.com', // blacklist this email address
 *   '@host.domain.com', // blacklist all emails ending with @host.domain.com
 *   '@domain.com', // blacklist all emails ending with @domain.com
 *   'domain.com', // blacklist any email address ending with domain.com (would include mydomain.com too).
 *   '.domain.com', // blacklist any email address at any host off domain.com (domain.com, my.domain.com, but NOT mydomain.com).
 *   '/something/', // blacklist any email containing "something". PCRE regex assumed when "/" is used as opening/closing delimiter.
 *   '/.+@really\.bad\.com$/', // another example of using a PCRE regular expression (blocks all "@really.bad.com").
 * ]);
 *
 * // Test out the blacklist
 * $email = 'somebody@bad-domain.com';
 * $result = $mail->isBlacklistEmail($email, [ 'why' => true ]);
 * if($result === false) {
 *   echo "<p>Email address is not blacklisted</p>";
 * } else {
 *   echo "<p>Email is blacklisted by rule: $result</p>";
 * }
 * ~~~~~
 * 
 * #property string module Name of WireMail module to use or blank to auto-detect. (default='')
 * #property string from Default from email address, when none provided at runtime. (default=$config->adminEmail)
 * #property string fromName Default from name string, when none provided at runtime. (default='')
 * #property string newline What to use for newline if different from RFC standard of "\r\n" (optional). 
 * #property array headers Default additional headers to send in email, key=value. (default=[])
 * #property array blacklist Email blacklist addresses or rules. (default=[])
 * 
 * @var array
 * 
 */
$config->wireMail = array(
	'module' => '', 
	'from' => '', 
	'fromName' => '', 
	'headers' => array(), 
	'blacklist' => array()
);

/**
 * PageList default settings
 * 
 * Note that 'limit' and 'speed' can also be overridden in the ProcessPageList module settings.
 * The 'useHoverActions' are currently only known compatible with AdminThemeDefault.
 * 
 * #property int limit Number of items to show per pagination (default=50)
 * #property int speed Animation speed in ms for opening/closing lists (default=200)
 * #property bool useHoverActions Show page actions when page is hovered? (default=false)
 * #property int hoverActionDelay Delay in ms between hovering a page and showing the actions (default=100)
 * #property int hoverActionFade Time in ms to spend fading in or out the actions (default=100)
 * 
 * @var array
 * 
 */
$config->pageList = array(
	'limit' => 50, 
	'speed' => 200, 
	'useHoverActions' => true,
	'hoverActionDelay' => 100, 
	'hoverActionFade' => 100
);

/**
 * PageEdit default settings
 * 
 * #property bool viewNew Specify true to force the "view" link to open pages in a new window. 
 * #property bool confirm Notify user if they attempt to navigate away from unsaved changes?
 * #property bool ajaxChildren Whether to load the 'children' tab via ajax 
 * #property bool ajaxParent Whether to load the 'parent' field via ajax
 * #property bool editCrumbs Whether or not breadcrumbs load page editor (false=load page list). 
 * 
 * @var array
 * 
 */
$config->pageEdit = array(
	'viewNew' => false,
	'confirm' => true, 
	'ajaxChildren' => true, 
	'ajaxParent' => true,
	'editCrumbs' => false,
);

/**
 * PageAdd default settings
 * 
 * #property string noSuggestTemplates Disable suggestions for new pages (1=disable all, or specify template names separated by space)
 * 
 */
$config->pageAdd = array(
	'noSuggestTemplates' => '', 
);

/**
 * MarkupQA (markup quality assurance) optional settings
 * 
 * This is used by Textarea Fieldtype when enabled and using content-type HTML.
 *
 * #property array ignorePaths Paths that begin with any of these will be ignored and left alone (not abstracted), i.e. [ '/a/b/', '/c/d/' ]
 * #property bool debug Show debugging info to superusers? (default=false). May also be specified in $config->debugMarkupQA.
 * 
 * @var array
 * 
 */
$config->markupQA = array(
	// 'ignorePaths' => [ "/some/path/", "/another/path/", "/and/so/on/" ],
	// 'debug' => true,
);

/*** 9. MISC ************************************************************************************/

/**
 * Additional core logs
 * 
 * All activities from the API functions corresponding with the given log names will be logged. 
 * Options that can be specified are: pages, fields, templates, modules, exceptions, deprecated.
 * 
 * Use log "deprecated" to log deprecated calls (during development only).
 * 
 * @var array
 * 
 */
$config->logs = array(
	'modules',
	'exceptions',
);

/**
 * Include IP address in logs, when applicable?
 * 
 * @var bool
 * 
 */
$config->logIP = false;

/**
 * Default admin theme
 * 
 * Module name of default admin theme for guest and users that haven't already selected one
 *
 * Core options include: **AdminThemeDefault** or **AdminThemeReno** or **AdminThemeUikit**.
 * Additional options will depend on what other 3rd party AdminTheme modules you have installed.
 *
 * @var string
 *
 */
$config->defaultAdminTheme = 'AdminThemeDefault';

/**
 * Admin email address
 *
 * Optional email address to send fatal error notifications to.
 *
 * #input email
 * @var string
 *
 */
$config->adminEmail = '';

/**
 * Fatal error HTML
 * 
 * HTML used for fatal error messages in HTTP mode.
 *
 * This should use inline styles since no guarantee stylesheets are present when these are displayed. 
 * String should contain two placeholders: {message} and {why}
 * 
 * #input textarea
 * @var string
 * 
 */
$config->fatalErrorHTML = "<p style='background:crimson;color:white;padding:0.5em;font-family:sans-serif;'><b>{message}</b><br /><br /><small>{why}</small></p>";

/**
 * Settings for modal windows
 * 
 * Most PW modals use the "large" setting. The comma separated dimensions represent: 
 *
 * 1. Start at pixels from top
 * 2. Start at pixels from left
 * 3. Width: 100% minus this many pixels
 * 4. Height: 100% minus this many pixels
 * 
 * Following that you may optionally specify any of the following, in any order. 
 * They must continue to be in CSV format, i.e. "key=value,key=value,key=value".
 * 
 * 5. modal=true (whether dialog will have modal behavior, specify false to disable)
 * 6. draggable=false (whether dialog is draggable, specify true to enable)*
 * 7. resizable=true (whether dialog is resizable, specify false to disable)
 * 8. hideOverflow=true (whether overflow in parent should be hidden, specify false to disable)
 * 9. hide=250 (number of ms to fade out window after closing, default=250)
 * 10. show=100 (number of ms to fade in window when opening, default=100)
 * 11. closeOnEscape=false (whether hitting the ESC key should close the window, specify true to enable)
 * 
 * The "large" modal option below demonstrates a few of these. 
 * 
 * *Note the draggable option does not work well unless the modal will open at the top of the
 * page. Do not use on modals that may be triggered further down the page.
 * 
 * @var array
 * #property string large Settings for large modal windows (most common)
 * #property string medium Settings for medium modal windows
 * #property string small Settings for small modal windows
 * #property string full Settings for full-screen modal windows
 * 
 */
$config->modals = array(
	'large' => "15,15,30,30,draggable=false,resizable=true,hide=250,show=100", 
	'medium' => "50,49,100,100", 
	'small' => "100,100,200,200",
	'full' => "0,0,0,0",
);

/**
 * Cache names to preload
 * 
 * Consists of the cache name/token for any caches that we want to be preloaded at boot time.
 * This is an optimization that can reduce some database overhead. 
 *
 * @var array
 *
 */
$config->preloadCacheNames = array(
	'Modules.info',
	//'ModulesVerbose.info',
	'ModulesVersions.info',
	'Modules.wire/modules/',
	'Modules.site/modules/',
);

/**
 * Allow Exceptions to propagate?
 * 
 * When true, ProcessWire will not capture Exceptions and will instead let them fall
 * through in their original state. Use only if you are running ProcessWire with your
 * own Exception handler. Most installations should leave this at false.
 * 
 * @var bool
 * 
 */
$config->allowExceptions = false;

/**
 * X-Powered-By header behavior
 *
 * - true: Sends the generic PW header, replacing any other powered-by headers (recommended). 
 * - false: Sends blank powered-by, replacing any other powered-by headers.
 * - null: Sends no powered-by, existing server powered-by headers will pass through.
 * 
 * @var bool|null
 * 
 */
$config->usePoweredBy = true;

/**
 * Chunk size for lazy-loaded pages used by $pages->findMany()
 * 
 * @var int
 * 
 */
$config->lazyPageChunkSize = 250;

/**
 * Settings specific to InputfieldWrapper class
 *
 * Setting useDependencies to false may enable to use depencencies in some places where
 * they aren't currently supported, like files/images and repeaters. Note that setting it
 * to false only disables it server-side. The javascript dependencies work either way. 
 *
 * Uncomment and paste into /site/config.php if you want to use this
 * 
 * $config->InputfieldWrapper = array(
 *	'useDependencies' => true,
 * 	'requiredLabel' => 'Missing required value', 
 *	);
 * 
 */


/*** 10. RUNTIME ********************************************************************************
 * 
 * The following are runtime-only settings and cannot be changed from /site/config.php
 * 
 */

/**
 * https: This is automatically set to TRUE when the request is an HTTPS request, null when not determined.
 *
 */
$config->https = null;

/**
 * ajax: This is automatically set to TRUE when the request is an AJAX request.
 *
 */
$config->ajax = false;

/**
 * modal: This is automatically set to TRUE when request is in a modal window. 
 * 
 */
$config->modal = false;

/**
 * external: This is automatically set to TRUE when PW is externally bootstrapped.
 *
 */
$config->external = false;

/**
 * cli: This is automatically set to TRUE when PW is booted as a command line (non HTTP) script.
 *
 */
$config->cli = false;

/**
 * version: This is automatically populated with the current PW version string (i.e. 2.5.0)
 *
 */
$config->version = '';


/**
 * versionName: This is automatically populated with the current PW version name (i.e. 2.5.0 dev)
 *
 */
$config->versionName = '';

/**
 * column width spacing for inputfields: used by some admin themes to communicate to InputfieldWrapper
 * 
 * Value is null, 0, or 1 or higher. This should be kept at null in this file. 
 *
 */
$config->inputfieldColumnWidthSpacing = null;

/**
 * Populated to contain <link rel='next|prev'.../> tags for document head
 * 
 * This is populated only after a MarkupPagerNav::render() has rendered pagination and is
 * otherwise null. 
 *
 * $config->pagerHeadTags = '';
 * 
 */

/*** 11. SYSTEM *********************************************************************************
 * 
 * Values in this section are not meant to be changed
 *
 */

$config->rootPageID = 1;
$config->adminRootPageID = 2;
$config->trashPageID = 7;
$config->loginPageID = 23;
$config->http404PageID = 27;
$config->usersPageID = 29;
$config->usersPageIDs = array(29); // if multiple needed
$config->rolesPageID = 30;
$config->externalPageID = 27;
$config->permissionsPageID = 31;
$config->guestUserPageID = 40;
$config->superUserPageID = 41;
$config->guestUserRolePageID = 37;
$config->superUserRolePageID = 38;
$config->userTemplateID = 3;
$config->userTemplateIDs = array(3); // if multiple needed
$config->roleTemplateID = 4;
$config->permissionTemplateID = 5;

/**
 * Page IDs that will be preloaded with every request
 *
 * This reduces number of total number of queries by reducing some on-demand queries
 *
 */
$config->preloadPageIDs = array(
	1, // root/homepage
	2, // admin
	28, // access
	29, // users
	30, // roles
	37, // guest user role
	38, // super user role
	40, // guest user
);

/**
 * Unix timestamp of when this ProcessWire installation was installed
 * 
 * This is set in /site/config.php by the installer. It is used for auto-detection
 * of when certain behaviors must remain backwards compatible. When this value is 0
 * then it is assumed that all behaviors must remain backwards compatible. Once 
 * established in /site/config.php, this value should not be changed. If your site
 * config file does not specify this setting, then you should not add it.
 * 
 */
$config->installed = 0;
