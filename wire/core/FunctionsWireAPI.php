<?php namespace ProcessWire;

/**
 * ProcessWire functions API maps function names to common API variables
 *
 * Provides an alternative to the API variables by providing functions of the same
 * name, with these benefits:
 *
 * - They are always in scope
 * - Makes life simpler in an IDE that recognizes phpdoc, as it can more easily
 *   recognize the types an return values.
 * - In some cases it makes for shorter API calls.
 *
 * The primary drawback is that the function calls are not mapped to a specific
 * instance, so in a multi-instance environment it's possible these function calls
 * may not be referring to the correct ProcessWire instance. For this reason, we
 * think these functions are primarily useful for front-end/website usages, and
 * not as useful for back-end and module development.
 * 
 * Shorter versions of these functions (without the leading "wire") can be found in 
 * FunctionsAPI.php file, which is used only if $config->useFunctionsAPI is true. 
 * The functions in this file are always available regardless of that setting. 
 *
 *
 */

/**
 * Common helper for API functions dealing with pages
 * 
 * @param $_apiVar
 * @param $selector
 * @return null|NullPage|Page|PageArray|Pages|PagesType
 * 
 */
function _wirePagesAPI($_apiVar, $selector) {
	
	/** @var Pages|PagesType $pages */
	$pages = is_object($_apiVar) ? $_apiVar : wire($_apiVar);
	if(!$pages) return null;
	if(!$selector) return $pages;

	if(is_array($selector) || is_object($selector)) {
		return $pages->find($selector);

	} else if(ctype_digit("$selector")) {
		// i.e. "123"
		return $pages->get((int) $selector);

	} else if(wireSanitizer('pageName', $selector) === $selector) {
		// i.e. "contact"
		return $pages->get("name=$selector");

	} else if(strpos($selector, '/') !== false && wireSanitizer('pagePathName', $selector) === $selector) {
		// i.e. "/path/to/page/"
		return $pages->get($selector);

	} else {
		return $pages->find($selector);
	}
}

/**
 * Common helper for API functions dealing with WireData objects
 * 
 * @param $_apiVar
 * @param $key
 * @param $value
 * @return mixed|null|WireData|Page
 * 
 */
function _wireDataAPI($_apiVar, $key, $value) {
	/** @var WireData $item */
	$item = wire($_apiVar);
	if(!$item) return null;
	if(strlen($key)) {
		if(is_null($value)) {
			return $item->get($key);
		} else {
			$item->set($key, $value);
		}
	}
	return $item;
}

/**
 * Access the $pages API variable as a function
 *
 * ~~~~
 * // A call with no arguments returns the $pages API variable
 * $pages = pages();
 * $pageArray = pages()->find("selector");
 * $page = pages()->get(123);
 *
 * // Providing selector as argument maps to $pages->find()
 * $pageArray = pages("template=basic-page");
 *
 * // Providing argument of single page ID, path or name maps to $pages->get()
 * $page = pages(123);
 * $page = pages("/path/to/page/");
 * $page = pages("page-name");
 * ~~~~
 *
 * @param string|array $selector Specify one of the following:
 *  - Nothing, makes it return the $pages API variable.
 *  - Selector (string) to find matching pages, makes function return PageArray - equivalent to $pages->find("selector");
 *  - Page ID (int) to return a single matching Page - equivalent to $pages->get(123);
 *  - Page name (string) to return a single page having the given name - equivalent to $pages->get("name");
 * @return Pages|PageArray|Page|NullPage
 *
 */
function wirePages($selector = '') {
	return _wirePagesAPI('pages', $selector);
}

/**
 * Access the $page API variable as a function
 *
 * ~~~~
 * $page = page(); // Simply get $page API var
 * $body = page()->body; // Get body field value
 * $body = page('body'); // Same as above
 * $headline = page('headline|title'); // Get headline or title
 * page('headline', 'Setting headline value'); // Set headline
 * ~~~~
 *
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return Page|mixed
 *
 */
function wirePage($key = '', $value = null) {
	return _wireDataAPI('page', $key, $value);
}

/**
 * Access the $config API variable as a function
 *
 * ~~~~~
 * $config = config(); // Simply get $config API var
 * $debug = config()->debug; // Get value of debug
 * $debug = config('debug'); // Same as above
 * config()->debug = true; // Set value of debug
 * config('debug', true);  // Same as above
 * ~~~~~
 *
 * @param string $key
 * @param null $value
 * @return Config|mixed
 *
 */
function wireConfig($key = '', $value = null) {
	return _wireDataAPI('config', $key, $value);
}

/**
 * Access the $modules API variable as a function
 *
 * ~~~~~
 * $modules = modules(); // Simply get $modules API var
 * $module = modules()->get('ModuleName'); // Get a module
 * $module = modules('ModuleName'); // Shortcut to get a module
 * ~~~~~
 *
 * @param string $name Optionally retrieve the given module name
 * @return Modules|Module|ConfigurableModule|null
 *
 */
function wireModules($name = '') {
	/** @var Modules $modules */
	$modules = wire('modules');
	return strlen($name) ? $modules->getModule($name) : $modules;
}

/**
 * Access the $user API variable as a function
 *
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return User|mixed
 *
 */
function wireUser($key = '', $value = null) {
	return _wireDataAPI('user', $key, $value);
}

/**
 * Access the $users API variable as a function
 *
 * See the pages() function for full usage details.
 *
 * @param string|array $selector Optional selector to send to find() or get()
 * @return Users|PageArray|User|mixed
 * @see pages()
 *
 */
function wireUsers($selector = '') {
	return _wirePagesAPI('users', $selector);
}

/**
 * Access the $session API variable as a function
 *
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return Session|mixed
 *
 */
function wireSession($key = '', $value = null) {
	return _wireDataAPI('session', $key, $value);
}

/**
 * Access the $fields API variable as a function
 *
 * @param string $name Optional field name to retrieve
 * @return Fields|Field|null
 *
 */
function wireFields($name = '') {
	/** @var Fields $fields */
	$fields = wire('fields');
	return strlen($name) ? $fields->get($name) : $fields;
}

/**
 * Access the $templates API variable as a function
 *
 * @param string $name Optional template to retrieve
 * @return Templates|Template|null
 *
 */
function wireTemplates($name = '') {
	/** @var Templates $templates */
	$templates = wire('templates');
	return strlen($name) ? $templates->get($name) : $templates;
}

/**
 * Access the $database API variable as a function
 *
 * @return WireDatabasePDO
 *
 */
function wireDatabase() {
	return wire('database');
}

/**
 * Access the $permissions API varaible as a function
 *
 * See the pages() function for usage details.
 *
 * @param string $selector
 * @return Permissions|Permission|PageArray|null|NullPage
 *
 */
function wirePermissions($selector = '') {
	return _wirePagesAPI('permissions', $selector);
}

/**
 * Access the $roles API varaible as a function
 *
 * See the pages() function for usage details.
 *
 * @param string $selector
 * @return Roles|Role|PageArray|null|NullPage
 *
 */
function wireRoles($selector = '') {
	return _wirePagesAPI('roles', $selector);
}

/**
 * Access the $sanitizer API variable as a function
 *
 * ~~~~~
 * // Example usages
 * $clean = sanitizer()->pageName($dirty);
 * $clean = sanitizer('pageName', $dirty); // same as above
 * ~~~~~
 *
 * @param string $name Optionally enter a sanitizer function name
 * @param string $value If $name populated, enter the value to sanitize
 * @return Sanitizer|string|int|array|null|mixed
 *
 */
function wireSanitizer($name = '', $value = '') {
	$sanitizer = wire('sanitizer');
	return strlen($name) ? $sanitizer->$name($value) : $sanitizer;
}

/**
 * Access the $datetime API variable as a function
 *
 * ~~~~~
 * // Example usages
 * $str = datetime()->relativeTimeStr('2016-10-10');
 * $str = datetime('Y-m-d');
 * $str = datetime('Y-m-d', time());
 * ~~~~~
 *
 * @param string $format Optional date format
 * @param string|int $value Optional date to format
 * @return WireDateTime|string|int
 *
 */
function wireDatetime($format = '', $value = '') {
	/** @var WireDateTime $datetime */
	$datetime = wire('datetime');
	return strlen($format) ? $datetime->formatDate($value ? $value : time(), $format) : $datetime;
}

/**
 * Access the $files API variable as a function
 *
 * @return WireFileTools
 *
 */
function wireFiles() {
	return wire('files');
}

/**
 * Access the $cache API variable as a function
 *
 * If called with no arguments it returns the $cache API variable.
 * If called with arguments, it can be used the same as `WireCache::get()`.
 *
 * @param string $name
 * @param callable|int|string|null $expire
 * @param callable|int|string|null $func
 * @return WireCache|string|array|PageArray|null
 * @see WireCache::get()
 *
 */
function wireCache($name = '', $expire = null, $func = null) {
	/** @var WireCache $cache */
	$cache = wire('cache');
	return strlen($name) ? $cache->get($name, $expire, $func) : $cache;
}

/**
 * Access the $languages API variable as a function
 *
 * Returns the $languages API variable, or a Language object if given a language name.
 *
 * ~~~~
 * // Examples
 * $languages = languages(); // Languages if active, null if not
 * $en = languages()->getDefault();
 * $de = languages('de');
 * ~~~~
 *
 * @param string|int $name Optional Language name or ID for language to retrieve
 * @return Languages|Language|NullPage|null
 *
 */
function wireLanguages($name = '') {
	/** @var Languages $languages */
	$languages = wire('languages');
	if(!$languages) return null;
	if(strlen($name)) return $languages->get($name);
	return $languages;
}

/**
 * Access the $input API variable as a function
 *
 * - Default behavior is to return the $input API var.
 * - If given just a $type (like "get" or "post"), it will return a WireInputData object for that type.
 * - If given a $type and $key it will return the input variable.
 * - If all arguments given, the returned value will also be run through the given sanitizer.
 *
 * ~~~~~
 * // Examples
 * $input = wireInput(); // Returns $input API var (WireInput)
 * $post = wireInput('post'); // Returns $input->post (WireInputData)
 * $post = wireInput()->post(); // Same as above
 * $value = wireInput('get', 'sort'); // Returns $input->get('sort');
 * $value = wireInput('get', 'sort', 'fieldName'); // Returns $input->get('sort') run through $sanitizer->fieldName().
 * $value = wireInput('get', 'sort', 'fieldName', 'title'); // Same as above but fallback to 'title' if no sort is present (3.0.125)
 * $value = wireInput('get', 'sort', ['title', 'created', 'likes'], 'title'); // Require value to be one given or fallback to 'title' (3.0.125+)
 * $value = wireInput()->get('sort', ['title', 'created', 'likes'], 'title'); // Same as above (3.0.125)
 * ~~~~~
 *
 * @param string $type Optionally indicate "get", "post", "cookie" or "whitelist"
 * @param string $key If getting a value, specify name of property containing value
 * @param string $sanitizer Optionally specify sanitizer name to run value through, or array containing whitelist of allowed values (3.0.125).
 * @param mixed $fallback Fallback value to return rather than null if value not present or does not validate (3.0.125+)
 * @return WireInput|WireInputData|array|string|int|null
 *
 */
function wireInput($type = '', $key = '', $sanitizer = null, $fallback = null) {
	/** @var WireInput $input */
	$input = wire('input');
	if(!strlen($type)) return $input;
	$type = strtolower($type);
	if(!strlen($key)) return $input->$type;
	$value = $input->$type($key, $sanitizer, $fallback);
	return $value;
}

/**
 * Access the $input->get API variable as a function
 *
 * This is the same as the input() function except that the $type "get" is already implied.
 *
 * @param string $key Name of input variable to get
 * @param string $sanitizer Optionally specify sanitizer name to run value through, or array containing whitelist of allowed values (3.0.125+).
 * @param mixed $fallback Fallback value to return rather than null if value not present or does not validate (3.0.125+)
 * @return WireInputData|string|int|array|null
 *
 */
function wireInputGet($key = '', $sanitizer = null, $fallback = null) {
	return wireInput('get', $key, $sanitizer, $fallback);
}

/**
 * Access the $input->post API variable as a function
 *
 * This is the same as the input() function except that the $type "post" is already implied.
 *
 * @param string $key Name of input variable to get
 * @param string $sanitizer Optionally specify sanitizer name to run value through, or array containing whitelist of allowed values (3.0.125).
 * @param mixed $fallback Fallback value to return rather than null if value not present or does not validate (3.0.125+)
 * @return WireInputData|string|int|array|null
 *
 */
function wireInputPost($key = '', $sanitizer = null, $fallback = null) {
	return wireInput('post', $key, $sanitizer, $fallback);
}

/**
 * Access the $input->cookie API variable as a function
 *
 * This is the same as the input() function except that the $type "cookie" is already implied.
 *
 * @param string $key Name of input variable to get
 * @param string $sanitizer Optionally specify sanitizer name to run value through, or array containing whitelist of allowed values (3.0.125+).
 * @param mixed $fallback Fallback value to return rather than null if value not present or does not validate (3.0.125+)
 * @return WireInputData|string|int|array|null
 *
 */
function wireInputCookie($key = '', $sanitizer = null, $fallback = null) {
	return wireInput('cookie', $key, $sanitizer, $fallback);
}

/**
 * Access the $log API variable as a function
 * 
 * Default behavior is to return the $log API variable. 
 * If both arguments are provided, it assumes you want to log a message. 
 * 
 * @param string $logName If logging a message, specify the name of the log. 
 * @param string $message If logging a message, specify the message text. 
 * @return WireLog|bool Returns bool if saving log entry, WireLog otherwise. 
 * 
 */
function wireLog($logName = '', $message = '') {
	/** @var WireLog $log */
	$log = wire('log');
	if(strlen($message)) {
		if(!strlen($logName)) $logName = 'unknown';
		return $log->save($logName, $message);
	}
	return $log;
}

/**
 * Start or stop a profiler event or return WireProfilerInterface instance
 *
 * @param string|array|object|null $name Name of event to start or event to stop
 * @param null|object|string $source If starting an event, optional source of event (object)
 * @param array $data Optional extra data as associative array
 * @return null|array|object
 *
 */
function wireProfiler($name = null, $source = null, $data = array()) {
	$profiler = wire('profiler');
	if(is_null($name)) return $profiler;
	if(!$profiler) return null;
	if(is_string($name)) {
		return $profiler->start($name, $source, $data);
	} else {
		return $profiler->stop($name);
	}
}

/**
 * Function that returns a $config->urls->[name] value o
 * 
 * @param string $key
 * @return null|Paths|string
 * 
 */
function wireUrls($key = '') {
	if(empty($key)) return wire('config')->urls;
	return wire('config')->urls($key);
}

/**
 * Function that returns a $config->paths->[name] value o
 *
 * @param string $key
 * @return null|Paths|string
 *
 */
function wirePaths($key = '') {
	if(empty($key)) return wire('config')->paths;
	return wire('config')->paths($key);
}

/**
 * Get or set a runtime site setting
 *
 * This is a simple helper function for maintaining runtime settings in a site profile.
 * It simply sets and gets settings that you define. It is preferable to using ProcessWire’s
 * `$config` or `config()` API var/function because it is not used to store anything else for
 * ProcessWire. It is also preferable to using a variable (or variables) because it is always
 * in scope and accessible anywhere in your template files, even within existing functions.
 *
 * ~~~~~
 * // set a setting named “foo” to value “bar”
 * setting('foo', 'bar');
 *
 * // get a setting named “foo”
 * $value = setting('foo');
 *
 * // set or replace multiple settings
 * setting([
 *   'foo' => 'value',
 *   'bar' => 123,
 *   'baz' => [ 'foo', 'bar', 'baz' ]
 * ]);
 *
 * // get all settings in associative array
 * $a = setting();
 *
 * // to unset a setting
 * setting(false, 'foo');
 * ~~~~~
 *
 * @param string|array $name Setting name, or array to set multiple
 * @param string|int|array|float|mixed $value Value to set, or omit if getting value of $name (default=null)
 * @return array|string|int|bool|mixed|null
 *
 */
function wireSetting($name = '', $value = null) {
	static $settings = array();
	if($name === '') return $settings;
	if(is_array($name)) return $settings = array_merge($settings, $name);
	if($name === false) { unset($settings[(string) $value]); return null; }
	if($value !== null) $settings[$name] = $value;
	return isset($settings[$name]) ? $settings[$name] : null;
}

/**
 * Return array of functions available from the functions API
 * 
 * Returned array is shortVersion => longVersion
 * 
 * @return array
 * 
 */
function _wireFunctionsAPI() {
	$names = array(
		'cache' => 'wireCache',
		'config' => 'wireConfig',
		'database' => 'wireDatabase', 
		'datetime' => 'wireDatetime',
		'fields' => 'wireFields',
		'files' => 'wireFiles',
		'input' => 'wireInput',
		'inputGet' => 'wireInputGet',
		'inputPost' => 'wireInputPost',
		'inputCookie' => 'wireInputCookie',
		'languages' => 'wireLanguages', 
		'modules' => 'wireModules', 
		'page' => 'wirePage',
		'pages' => 'wirePages', 
		'paths' => 'wirePaths',
		'permissions' => 'wirePermissions',
		'profiler' => 'wireProfiler',
		'region' => 'wireRegion', 
		'roles' => 'wireRoles',
		'sanitizer' => 'wireSanitizer', 
		'setting' => 'wireSetting',
		'session' => 'wireSession', 
		'templates' => 'wireTemplates',
		'urls' => 'wireUrls', 
		'user' => 'wireUser', 
		'users' => 'wireUsers', 
	);
	return $names;
}

