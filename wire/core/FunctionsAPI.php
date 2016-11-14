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
 * Note: This file is only used if $config->useFunctionsAPI == true; 
 * 
 */

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
function pages($selector = '') {
	return wirePages($selector);
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
function page($key = '', $value = null) {
	return wirePage($key, $value);
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
function config($key = '', $value = null) {
	return wireConfig($key, $value);
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
function modules($name = '') {
	return wireModules($name);
}

/**
 * Access the $user API variable as a function
 * 
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return User|mixed
 * 
 */
function user($key = '', $value = null) {
	return wireUser($key, $value);
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
function users($selector = '') {
	return wireUsers($selector);
}

/**
 * Access the $session API variable as a function
 * 
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return Session|mixed
 * 
 */
function session($key = '', $value = null) {
	return wireSession($key, $value);
}

/**
 * Access the $fields API variable as a function
 * 
 * @param string $name Optional field name to retrieve
 * @return Fields|Field|null
 * 
 */
function fields($name = '') {
	return wireFields($name);
}

/**
 * Access the $templates API variable as a function
 * 
 * @param string $name Optional template to retrieve
 * @return Templates|Template|null 
 * 
 */
function templates($name = '') {
	return wireTemplates($name);
}

/**
 * Access the $database API variable as a function
 * 
 * @return WireDatabasePDO
 * 
 */
function database() {
	return wireDatabase();
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
function permissions($selector = '') {
	return wirePermissions($selector);
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
function roles($selector = '') {
	return wireRoles($selector);
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
function sanitizer($name = '', $value = '') {
	return wireSanitizer($name, $value);
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
function datetime($format = '', $value = '') {
	return wireDatetime($format, $value);
}

/**
 * Access the $files API variable as a function
 * 
 * @return WireFileTools
 * 
 */
function files() {
	return wireFiles();
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
function cache($name = '', $expire = null, $func = null) {
	return wireCache($name, $expire, $func);
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
function languages($name = '') {
	return wireLanguages($name);
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
 * $input = input(); // Returns $input API var (WireInput)
 * $post = input('post'); // Returns $input->post (WireInputData)
 * $value = input('get', 'sort'); // Returns $input->get('sort');
 * $value = input('get', 'sort', 'fieldName'); // Returns $input->get('sort') run through $sanitizer->fieldName().
 * ~~~~~
 * 
 * @param string $type Optionally indicate "get", "post", "cookie" or "whitelist"
 * @param string $key If getting a value, specify name of property containing value
 * @param string $sanitizer Optionally specify sanitizer name to run value through
 * @return WireInput|WireInputData array|string|int|null
 * 
 */
function input($type = '', $key = '', $sanitizer = '') {
	return wireInput($type, $key, $sanitizer);
}

/**
 * Access the $input->get API variable as a function
 * 
 * This is the same as the input() function except that the $type "get" is already implied. 
 * 
 * @param string $key
 * @param string $sanitizer
 * @return WireInputData|string|int|array|null
 * 
 */
function inputGet($key = '', $sanitizer = '') {
	return wireInputGet($key, $sanitizer);
}

/**
 * Access the $input->post API variable as a function
 *
 * This is the same as the input() function except that the $type "post" is already implied.
 *
 * @param string $key
 * @param string $sanitizer
 * @return WireInputData|string|int|array|null
 *
 */
function inputPost($key = '', $sanitizer = '') {
	return wireInputPost($key, $sanitizer);
}

/**
 * Access the $input->cookie API variable as a function
 *
 * This is the same as the input() function except that the $type "cookie" is already implied.
 *
 * @param string $key
 * @param string $sanitizer
 * @return WireInputData|string|int|array|null
 *
 */
function inputCookie($key = '', $sanitizer = '') {
	return wireInputCookie($key, $sanitizer);
}

/**
 * Function that returns a $config->urls->[name] value o
 *
 * @param string $key
 * @return null|Paths|string
 *
 */
function urls($key = '') {
	return wireUrls($key);
}

/**
 * Function that returns a $config->paths->[name] value o
 *
 * @param string $key
 * @return null|Paths|string
 *
 */
function paths($key = '') {
	return wirePaths($key);
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
function profiler($name = null, $source = null, $data = array()) {
	return wireProfiler($name, $source, $data);
}

/**
 * Get or set a region for front-end output
 *
 * ~~~~~
 * // define a region
 * region('content', '<p>this is some content</p>');
 *
 * // prepend some text to region
 * region('+content', '<h2>Good morning</h2>');
 *
 * // append some text to region
 * region('content+', '<p><small>Good night</small></p>');
 *
 * // output a region
 * echo region('content');
 *
 * // get all regions in an array
 * $regions = region('*');
 *
 * // clear the 'content' region
 * region('content', '');
 *
 * // clear all regions
 * region('*', '');
 *
 * ~~~~~
 *
 * @param string $key Name of region to get or set.
 *  - Specify "*" to retrieve all defined regions in an array.
 *  - Prepend a "+" to the region name to have it prepend your given value to any existing value.
 *  - Append a "+" to the region name to have it append your given value to any existing value.
 * @param null|string $value If setting a region, the text that you want to set.
 * @return string|null|bool|array Returns string of text when getting a region, NULL if region not set, or TRUE if setting region.
 *
 */
function region($key = '', $value = null) {
	return wireRegion($key, $value);
}

