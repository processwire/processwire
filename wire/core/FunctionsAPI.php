<?php namespace ProcessWire;

/**
 * ProcessWire functions API maps function names to common API variables
 * 
 * #pw-summary-Functions-API = 
 * Provides an alternative to the API variables by providing functions of the same
 * name, with these benefits: 
 * 
 * - They are always in scope whether inside a function or outside of it. 
 * - They are self documenting with your IDE, unlike API $variables. 
 * - They cannot be accidentally overwritten the way variables can be. 
 * - They provider greater contrast between what are API-calls and variables.
 * - In some cases it makes for shorter API calls. 
 * - Some of the functions provide arguments for useful shortcuts.
 * 
 * These functions always refer to the current ProcessWire instance, so are intended
 * primarily for front-end usage in template files (not for modules).
 * 
 * If these functions are not working for you, you can enable them by setting 
 * `$config->useFunctionsAPI=true;` in your /site/config.php file. 
 * 
 * Regardless of whether the Functions API is enabled or not, you can also access
 * any of these functions by prefixing the word `wire` to them and using the format
 * `wireFunction()` i.e. `wirePages()`, `wireUser()`, etc. 
 * Or, if you do not
 * #pw-summary-Functions-API
 * 
 */

/**
 * Retrieve or save pages ($pages API variable as a function)
 * 
 * Accessing `pages()` is exactly the same as accessing `$pages`. Though there are a couple of optional
 * shortcuts available by providing an argument to this function. 
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
 * #pw-group-Functions-API
 * 
 * @param string|array|int $selector Specify one of the following: 
 *  - Nothing, makes it return the $pages API variable. 
 *  - Selector (string) to find matching pages, makes function return PageArray - equivalent to $pages->find("selector");
 *  - Page ID (int) to return a single matching Page - equivalent to $pages->get(123);
 *  - Page name (string) to return a single page having the given name - equivalent to $pages->get("name"); 
 * @return Pages|PageArray|Page|NullPage
 * @see Pages
 * 
 */
function pages($selector = '') {
	return wirePages($selector);
}

/**
 * Returns the current Page being viewed ($page API variable as a function)
 * 
 * This function behaves the same as the `$page` API variable, though does support optional
 * arguments as shortcuts for getting from the page or setting values to it. 
 * 
 * ~~~~
 * $page = page(); // Simply get $page API var 
 *
 * // Get the “body” field 
 * $body = page()->body; // direct syntax
 * $body = page()->get('body'); // regular syntax
 * $body = page('body'); // shortcut syntax
 * 
 * // Get the “headline” field or fallback to “title’
 * $headline = page()->get('headline|title'); // regular syntax
 * $headline = page('headline|title'); // shortcut syntax
 * 
 * // Set the “headline” field
 * page()->headline = 'My headline'; // direct syntax
 * page()->set('headline', 'My headline'); // regular syntax
 * page('headline', 'My headline'); // shortcut syntax
 * ~~~~
 *
 * #pw-group-Functions-API
 * 
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return Page|mixed
 * @see Page
 * 
 */
function page($key = '', $value = null) {
	return wirePage($key, $value);
}

/**
 * Return id for given page or false if it’s not a page
 *
 * Returns positive int (page id) for page that exists, 0 for NullPage,
 * or false if given $value is not a Page.
 * 
 * #pw-group-Functions-API
 *
 * @param Page|mixed $value
 * @return int|false
 * @since 3.0.224
 *
 */
function pageId($value) {
	return wirePageId($value);
}

/**
 * Access a ProcessWire configuration setting ($config API variable as a function)
 * 
 * This function behaves the same as the `$config` API variable, though does support 
 * optional shortcut arguments for getting/setting values. 
 * 
 * ~~~~~
 * $config = config(); // Simply get $config API var
 * $debug = config()->debug; // Get value of debug 
 * $debug = config('debug'); // Same as above, shortcut syntax
 * config()->debug = true; // Set value of debug
 * config('debug', true);  // Same as above, shortcut syntax
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $key
 * @param null $value
 * @return Config|mixed
 * @see Config
 * 
 */
function config($key = '', $value = null) {
	return wireConfig($key, $value);
}

/**
 * Get a module, get module information, and much more ($modules API variable as a function)
 * 
 * This function behaves the same as the `$modules` API variable, though does support
 * an optional shortcut argument for getting a module.
 * 
 * ~~~~~
 * $modules = modules(); // Simply get $modules API var
 * $module = modules()->get('ModuleName'); // Get a module
 * $module = modules('ModuleName'); // Shortcut to get a module
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $name Optionally retrieve the given module name
 * @return Modules|Module|ConfigurableModule|null
 * @see Modules
 * 
 */
function modules($name = '') {
	return wireModules($name);
}

/**
 * Get the currently logged in user ($user API variable as a function)
 * 
 * This function behaves the same as the `$user` API variable, though does support
 * optional shortcut arguments for getting or setting values. 
 * 
 * #pw-group-Functions-API
 * 
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return User|mixed
 * @see User
 * 
 */
function user($key = '', $value = null) {
	return wireUser($key, $value);
}

/**
 * Get, find or save users ($users API variable as a function)
 * 
 * This function behaves the same as the `$users` API variable, though does support
 * an optional shortcut argument for getting a single user or finding multiple users.
 * 
 * ~~~~~~
 * // Get a single user (regular and shortcut syntax)
 * $u = users()->get('karen'); 
 * $u = users('karen'); 
 * 
 * // Find multiple users (regular and shortcut syntax)
 * $us = users()->find('roles.name=editor'); 
 * $us = users('roles.name=editor'); 
 * ~~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string|array|int $selector Optional selector to send to find() or get()
 *  - Specify user name or ID to get and return that User 
 *  - Specify a selector string to find all users matching selector (PageArray)
 * @return Users|PageArray|User|mixed
 * @see pages(), Users
 * 
 */
function users($selector = '') {
	return wireUsers($selector);
}

/**
 * Get or set values in the current user session ($session API variable as a function)
 * 
 * This function behaves the same as the `$session` API variable, though does support
 * optional shortcut arguments for getting or setting values. 
 * 
 * ~~~~~
 * // Get a value from the session
 * $foo = session()->foo; // direct syntax
 * $foo = session()->get('foo'); // regular syntax
 * $foo = session('foo'); // shortcut syntax
 * 
 * // Set a value to the session
 * session()->foo = 'bar'; // direct syntax
 * session()->set('foo', 'bar');  // regular syntax
 * session('foo', 'bar'); // shortcut syntax
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $key Optional property to get or set
 * @param null $value Optional value to set
 * @return Session|null|string|array|int|float
 * @see Session
 * 
 */
function session($key = '', $value = null) {
	return wireSession($key, $value);
}

/**
 * Get or save fields independent of templates ($fields API variable as as function)
 * 
 * This function behaves the same as the `$fields` API variable, though does support
 * an optional shortcut argument for getting a single field. 
 * 
 * ~~~~~
 * $field = fields()->get('title'); // regular syntax
 * $field = fields('title'); // shortcut syntax
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $name Optional field name to retrieve
 * @return Fields|Field|null
 * @see Fields
 * 
 */
function fields($name = '') {
	return wireFields($name);
}

/**
 * Get or save templates ($templates API variable as a function)
 * 
 * This function behaves the same as the `$templates` API variable, though does support
 * an optional shortcut argument for getting a single template. 
 * 
 * ~~~~~~
 * $t = templates()->get('basic-page'); // regular syntax
 * $t = templates('basic-page'); // shortcut syntax
 * ~~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $name Optional template to retrieve
 * @return Templates|Template|null 
 * @see Templates
 * 
 */
function templates($name = '') {
	return wireTemplates($name);
}

/**
 * Create and execute PDO database queries ($database API variable as a function)
 * 
 * #pw-group-Functions-API
 * 
 * @return WireDatabasePDO
 * @see WireDatabasePDO
 * 
 */
function database() {
	return wireDatabase();
}

/**
 * Get, find or save permissions ($permissions API variable as a function)
 * 
 * Accessing `permissions()` is exactly the same as accessing `$permissions`. Though there are a couple of optional
 * shortcuts available by providing an argument to this function. 
 * 
 * ~~~~~
 * // Get a permission
 * $p = permissions()->get('page-edit'); // regular syntax
 * $p = permissions('page-edit'); // shortcut syntax
 * 
 * // Find permissions
 * $ps = permissions()->find('name^=page'); // regular syntax
 * $ps = permissions('name^=page'); // shortcut syntax
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string|int $selector
 *  - Specify permission name or ID to retrieve that Permission (Permission)
 *  - Specify a selector string to return all permissions matching selector (PageArray)
 * @return Permissions|Permission|PageArray|null|NullPage
 * @see Permissions
 * 
 */
function permissions($selector = '') {
	return wirePermissions($selector);
}

/**
 * Get, find or save roles ($roles API variable as a function)
 * 
 * Accessing `roles()` is exactly the same as accessing `$roles`. Though there are a couple of optional
 * shortcuts available by providing an argument to this function. 
 *
 * #pw-group-Functions-API
 *
 * @param string|int $selector
 *  - Specify name or ID of role to get (Role object)
 *  - Specify selector string matching roles to find (PageArray object)
 * @return Roles|Role|PageArray|null|NullPage
 * @see Roles
 *
 */
function roles($selector = '') {
	return wireRoles($selector);
}

/**
 * Sanitize variables and related string functions ($sanitizer API variable as a function)
 * 
 * This behaves the same as the `$sanitizer` API variable but supports arguments as optional shortcuts.
 * 
 * ~~~~~
 * $clean = sanitizer()->pageName($dirty); // regular syntax
 * $clean = sanitizer('pageName', $dirty); // shortcut syntax
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $name Optionally enter a sanitizer function name
 * @param string $value If $name populated, enter the value to sanitize
 * @return Sanitizer|string|int|array|null|mixed
 * @see Sanitizer
 * 
 */
function sanitizer($name = '', $value = '') {
	return wireSanitizer($name, $value);
}

/**
 * Access date and time related tools ($datetime API variable as a function)
 * 
 * This behaves the same as the `$datetime` API variable except that you can optionally provide
 * arguments as a shortcut to the `$datetime->formatDate()` method. 
 * 
 * ~~~~~
 * $str = datetime()->relativeTimeStr('2016-10-10');
 * $str = datetime('Y-m-d');  // shortcut to formatDate method
 * $str = datetime('Y-m-d', time()); // shortcut to formatDate method
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $format Optional date format 
 * @param string|int $value Optional date to format
 * @return WireDateTime|string|int
 * @see WireDateTime
 * 
 */
function datetime($format = '', $value = '') {
	return wireDatetime($format, $value);
}

/**
 * Access tools for working on the file system ($files API variable as a function)
 * 
 * This behaves identically to the `$files` API variable and as no optional arguments. 
 * 
 * #pw-group-Functions-API
 * 
 * @return WireFileTools
 * @see WireFileTools
 * 
 */
function files() {
	return wireFiles();
}

/**
 * Get and save caches ($cache API variable as a function)
 * 
 * This behaves the same as the $cache API variable but does support arguments as a 
 * shortcut for the `$cache->get()` method. 
 * 
 * - If called with no arguments it returns the $cache API variable. 
 * - If called with arguments, it can be used the same as `WireCache::get()`.
 * 
 * #pw-group-Functions-API
 * 
 * @param string $name
 * @param callable|int|string|null $expire
 * @param callable|int|string|null $func
 * @return WireCache|string|array|PageArray|null
 * @see WireCache, WireCache::get()
 * 
 */
function cache($name = '', $expire = null, $func = null) {
	return wireCache($name, $expire, $func);
}

/**
 * Access all installed languages in multi-language environment ($languages API variable as a function)
 * 
 * Returns the `$languages` API variable, or a `Language` object if given a language name or ID. 
 * 
 * ~~~~
 * $languages = languages(); // Languages if active, null if not
 * $en = languages()->getDefault(); // Get default language
 * $de = languages()->get('de'); // Get another language
 * $de = languages('de'); // Get another language (shorcut syntax)
 * ~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string|int $name Optional Language name or ID for language to retrieve
 * @return Languages|Language|NullPage|null
 * @see Languages, Languages::get(), Language
 * 
 */
function languages($name = '') {
	return wireLanguages($name);
}

/**
 * Access GET, POST or COOKIE input variables and more ($input API variable as a function)
 * 
 * - Default behavior with no arguments is to return the `$input` API variable.
 * - If given just a `$type` argument (like “get” or “post”), it will return a `WireInputData` object for that type.
 * - If given a `$type` and `$key` arguments, it will return the value, or null if not present. 
 * - If `$sanitizer` argument given, the returned value will also be run through the given sanitizer.
 * - If the `$sanitizer` argument is an array, the returned input value must be within the given list, or null if not (3.0.125+).
 * - If `$fallback` argument given, it will return the fallback value if input value was not present or not valid (3.0.125+).
 * - See the `WireInput::get()` method for all options.
 * 
 * ~~~~~
 * // Can be used the same way as the $input API variable
 * // In examples below the “post” can also be “get” or “cookie”
 * $input = input(); // Returns $input API var (WireInput)
 * $post = input()->post(); // Returns $input->post (WireInputData instance)
 * $foo = input()->post('foo'); // Returns POST variable “foo”
 * $bar = input()->post('bar', 'text'); // Returns “bar” after text sanitizer (3.0.125+)
 * $s = input()->post('s', ['foo', 'bar', 'baz']); // POST var “s” must match given list (3.0.125+)
 * 
 * // You can also move the arguments all to the function call if you prefer:
 * $s = input('get', 'sort'); // Returns GET var “sort”
 * $s = input('get', 'sort', 'fieldName'); // Returns “sort” after “fieldName” sanitizer
 * $s = input('get', 'sort', ['title', 'created']); // Require sort to be one in given array (3.0.125+)
 * $s = input('get', 'sort', ['title', 'created'], 'title'); // Same as above, fallback to 'title' (3.0.125+)
 * ~~~~~
 * 
 * #pw-group-Functions-API
 * 
 * @param string $type Optionally indicate "get", "post", "cookie" or "whitelist"
 * @param string $key If getting a value, specify name of input property containing value
 * @param string $sanitizer Optionally specify sanitizer name to run value through, or in 3.0.125+ may also be an array of allowed values.
 * @param string|int|null $fallback Value to fallback to if input not present or invalid
 * @return WireInput|WireInputData|array|string|int|null
 * @see WireInput
 * 
 */
function input($type = '', $key = '', $sanitizer = null, $fallback = null) {
	return wireInput($type, $key, $sanitizer, $fallback);
}

/**
 * Access the $input->get API variable as a function
 * 
 * This is the same as the input() function except that the $type "get" is already implied.
 * 
 * #pw-internal
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
 * #pw-internal
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
 * #pw-internal
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
 * Get one of any named system URLs (shortcut to the $config API variable “urls” property)
 * 
 * URLs always have a trailing slash. 
 * 
 * ~~~~~
 * // you can use either syntax below, where “templates” can be the name for any system URL
 * $url = urls()->templates;
 * $url = urls('templates');
 * ~~~~~
 * 
 * #pw-group-Functions-API
 *
 * @param string $key
 * @return null|Paths|string
 * @see Config::urls()
 *
 */
function urls($key = '') {
	return wireUrls($key);
}

/**
 * Get one of any named server disk paths (shortcut to the $config API variable “paths” property)
 * 
 * Paths always have a trailing slash.
 * 
 * ~~~~~
 * // you can use either syntax below, where “templates” can be the name for any system URL
 * $path = paths()->templates;
 * $path = paths('templates');
 * ~~~~~
 * 
 * #pw-group-Functions-API
 *
 * @param string $key
 * @return null|Paths|string
 * @see Config::paths()
 * 
 */
function paths($key = '') {
	return wirePaths($key);
}

/**
 * Start or stop a profiler event or return WireProfilerInterface instance
 * 
 * #pw-internal
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
 * Get or set an output region (primarily for front-end output usage)
 * 
 * This function is an convenience for storing markup that ultimately gets output in a _main.php file
 * (or whatever file `$config->appendTemplateFile` is set to). It is an alternative to passing variables
 * between included files and provides an interface for setting, appending, prepending and ultimately
 * getting markup (or other strings) for output. It’s designed for use the the “Delayed Output” strategy,
 * though does not necessarily require it.
 * 
 * This function can also be accessed as `wireRegion()`, and that function is always available
 * regardless of whether the Functions API is enabled or not.
 *
 * *Note: unlike other functions in the Functions API, this function is not related to API variables.* 
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
 * ~~~~~
 *
 * #pw-group-Functions-API
 *
 * @param string $key Name of region to get or set.
 *  - Specify "*" to retrieve all defined regions in an array.
 *  - Prepend a "+" to the region name to have it prepend your given value to any existing value.
 *  - Append a "+" to the region name to have it append your given value to any existing value.
 *  - Prepend a "++" to region name to make future calls without "+" automatically prepend.
 *  - Append a "++" to region name to make future calls without "+" to automatically append.
 * @param null|string $value If setting a region, the text that you want to set.
 * @return string|null|bool|array Returns string of text when getting a region, NULL if region not set, or TRUE if setting region.
 *
 */
function region($key = '', $value = null) {
	return wireRegion($key, $value);
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
 * *Note: unlike other functions in the Functions API, this function is not related to API variables.* 
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
 * #pw-group-Functions-API
 *
 * @param string|array $name Setting name, or array to set multiple
 * @param string|int|array|float|mixed $value Value to set, or omit if getting value of $name (default=null)
 * @return array|string|int|bool|mixed|null
 *
 */
function setting($name = '', $value = null) {
	return wireSetting($name, $value);
}
