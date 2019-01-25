<?php namespace ProcessWire;

/**
 * ProcessWire WireInputData and WireInput
 *
 * WireInputData and the WireInput class together form a simple 
 * front end to PHP's $_GET, $_POST, and $_COOKIE superglobals.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Manages the group of GET, POST, COOKIE and whitelist vars, each of which is a WireInputData object.
 * 
 * #pw-summary Provides a means to get user input from URLs, GET, POST, and COOKIE variables and more.
 *
 * @link http://processwire.com/api/variables/input/ Offical $input API variable Documentation
 * 
 * @property array|string[] $urlSegments Retrieve all URL segments (array). This requires url segments are enabled on the template of the requested page. You can turn it on or off under the url tab when editing a template. #pw-group-URL-segments
 * @property WireInputData $post POST variables
 * @property WireInputData $get GET variables
 * @property WireInputData $cookie COOKIE variables
 * @property WireInputData $whitelist Whitelisted variables
 * @property int $pageNum Current page number (where 1 is first) #pw-group-URLs
 * @property string $urlSegmentsStr String of current URL segments, separated by slashes, i.e. a/b/c  #pw-internal
 * @property string $urlSegmentStr Alias of urlSegmentsStr #pw-group-URL-segments
 * @property string $url Current requested URL including page numbers and URL segments, excluding query string. #pw-group-URLs
 * @property string $httpUrl Like $url but includes the scheme/protcol and hostname. #pw-group-URLs
 * @property string $queryString Current query string #pw-group-URLs
 * @property string $scheme Current scheme/protcol, i.e. http or https #pw-group-URLs
 * 
 * @property string $urlSegment1 First URL segment #pw-group-URL-segments
 * @property string $urlSegment2 Second URL segment #pw-group-URL-segments
 * @property string $urlSegment3 Third URL segment, and so on... #pw-group-URL-segments
 *
 */
class WireInput extends Wire {

	/**
	 * @var WireInputData|null
	 * 
	 */
	protected $getVars = null;
	
	/**
	 * @var WireInputData|null
	 *
	 */
	protected $postVars = null;
	
	/**
	 * @var WireInputData|null
	 *
	 */
	protected $cookieVars = null;
	
	/**
	 * @var WireInputData|null
	 *
	 */
	protected $whitelist = null;

	/**
	 * @var array
	 * 
	 */
	protected $urlSegments = array();

	/**
	 * @var int
	 * 
	 */
	protected $pageNum = 1;

	/**
	 * Use lazy loading method for get/post/cookie?
	 * 
	 * @var bool
	 * 
	 */
	protected $lazy = false;

	/**
	 * @var array
	 * 
	 */
	protected $requestMethods = array(
		'GET' => 'GET',
		'POST' => 'POST',
		'HEAD' => 'HEAD',
		'PUT' => 'PUT',
		'DELETE' => 'DELETE',
		'OPTIONS' => 'OPTIONS',
	);

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->useFuel(false);
		$this->unregisterGLOBALS();
	}

	/**
	 * Set for lazy loading
	 * 
	 * Must be called before accessing any get/post/cookie input
	 * 
	 * @param bool $lazy
	 * 
	 */
	public function setLazy($lazy = true) {
		$this->lazy = (bool) $lazy;
	}
	
	/**
	 * Retrieve a named GET variable value, or all GET variables (from URL query string)
	 * 
	 * Always sanitize (and validate where appropriate) any values from user input. 
	 * 
	 * The following optional features are available in ProcessWire version 3.0.125 and newer: 
	 * 
	 * - Provide a sanitization method as the 2nd argument to include sanitization.
	 * - Provide an array of valid values as the 2nd argument to limit input to those values. 
	 * - Provide a callback function that receives the value and returns a validated value. 
	 * - Provide a fallback value as the 3rd argument to use if value not present or invalid.
	 * - Append “[]” to the 1st argument to always force return value to be an array, i.e “colors[]”.
	 *
	 * Note that the `$valid` and `$fallback` arguments are only applicable if a `$key` argument is provided. 
	 * 
	 * ~~~~~
	 * // Retrieve a "q" GET variable, sanitize and output
	 * // Example request URL: domain.com/path/to/page/?q=TEST
	 * $q = $input->get('q'); // retrieve value
	 * $q = $sanitizer->text($q); // sanitize input as 1-line text
	 * echo $sanitizer->entities($q); // sanitize for output, outputs "TEST"
	 * 
	 * // You can also combine $input and one $sanitizer call, replacing
	 * // the "text" method call with any $sanitizer method: 
	 * $q = $input->get->text('q'); 
	 *
	 * // like the previous example, but specify sanitizer method as second argument (3.0.125+):
	 * $q = $input->get('q', 'text'); 
	 * 
	 * // if you want more than one sanitizer, specify multiple in a CSV string (3.0.125+):
	 * $q = $input->get('q', 'text,entities'); 
	 * 
	 * // you can provide a whitelist array of allowed values instead of a sanitizer method (3.0.125+):
	 * $color = $input->get('color', [ 'red', 'blue', 'green' ]); 
	 * 
	 * // an optional 3rd argument lets you specify a fallback value to use if valid value not present or 
	 * // empty in input, and it will return this value rather than null/empty (3.0.125+):
	 * $qty = $input->get('qty', 'int', 1); // return 1 if no qty provided
	 * $color = $input->get('color', [ 'red', 'blue', 'green' ], 'red'); // return red if no color selected
	 * 
	 * // you may optionally provide a callback function to sanitize/validate with (3.0.125+):
	 * $isActive = $input->get('active', function($val) { return $val ? true : false; }); 
	 * ~~~~~
	 * 
	 * @param string $key Name of GET variable you want to retrieve. 
	 *  - If populated, returns the value corresponding to the key or NULL if it doesn’t exist.
	 *  - If blank, returns reference to the WireDataInput containing all GET vars. 
	 * @param array|string|int|callable|null $valid Omit for no validation/sanitization, or provide one of the following (3.0.125+ only):
	 *  - String name of Sanitizer method to to sanitize value with before returning it.
	 *  - CSV string of multiple sanitizer names to process the value, in order. 
	 *  - Array of allowed values (aka whitelist), where input value must be one of these, otherwise null (or fallback value) will returned. 
	 *    Values in the array may be any string or integer.
	 *  - Callback function to sanitize and validate the value. 
	 *  - Integer if a specific number is the only allowed value other than fallback value (i.e. like a checkbox toggle).
	 * @param mixed|null Optional fallback value to return if input value is not present or does not validate (3.0.125+ only). 
	 * @return null|mixed|WireInputData Returns one of the following:
	 *  - If given no `$key` argument, returns `WireInputData` with all unsanitized GET vars.
	 *  - If given no `$valid` argument, returns unsanitized value or NULL if not present. 
	 *  - If given a Sanitizer name for `$valid` argument, returns value sanitized with that Sanitizer method (3.0.125+).
	 *  - If given an array of allowed values for `$valid` argument, returns value from that array if it was in the input, or null if not (3.0.125+).
	 *  - If given a callable function for `$valid` argument, returns the value returned by that function (3.0.125+).
	 *  - If given a `$fallback` argument, returns that value when it would otherwise return null (3.0.125+). 
	 * @throws WireException if given unknown Sanitizer method for $valid argument
	 *
	 */
	public function get($key = '', $valid = null, $fallback = null) {
		if(is_null($this->getVars)) {
			$this->getVars = $this->wire(new WireInputData($_GET, $this->lazy));
			$this->getVars->offsetUnset('it');
		}
		if(!strlen($key)) return $this->getVars;
		if($valid === null && $fallback === null && !strpos($key, '[]')) return $this->getVars->__get($key);
		return $this->getValidInputValue($this->getVars, $key, $valid, $fallback);
	}

	/**
	 * Retrieve a named POST variable value, or all POST variables
	 * 
	 * Always sanitize (and validate where appropriate) any values from user input.
	 * 
	 * The following optional features are available in ProcessWire version 3.0.125 and newer:
	 *
	 * - Provide a sanitization method as the 2nd argument to include sanitization.
	 * - Provide an array of valid values as the 2nd argument to limit input to those values.
	 * - Provide a callback function that receives the value and returns a validated value.
	 * - Provide a fallback value as the 3rd argument to use if value not present or invalid.
	 * - Append “[]” to the 1st argument to always force return value to be an array, i.e “colors[]”.
	 *
	 * Note that the `$valid` and `$fallback` arguments are only applicable if a `$key` argument is provided.
	 * 
	 * 
	 * ~~~~~
	 * // Retrieve a "comments" POST variable, sanitize and output it
	 * $comments = $input->post('comments'); 
	 * $comments = $sanitizer->textarea($comments); // sanitize input as multi-line text with no HTML
	 * echo $sanitizer->entities($comments); // sanitize for output
	 * 
	 * // You can also combine $input and one $sanitizer call like this,
	 * // replacing "text" with name of any $sanitizer method: 
	 * $comments = $input->post->textarea('comments');
	 * 
	 * // like the previous example, but specify sanitizer method as second argument (3.0.125+):
	 * $comments = $input->post('comments', 'textarea');
	 *
	 * // if you want more than one sanitizer, specify multiple in a CSV string (3.0.125+):
	 * $comments = $input->post('comments', 'textarea,entities');
	 *
	 * // you can provide a whitelist array of allowed values instead of a sanitizer method (3.0.125+):
	 * $color = $input->post('color', [ 'red', 'blue', 'green' ]);
	 *
	 * // an optional 3rd argument lets you specify a fallback value to use if valid value not present or
	 * // empty in input, and it will return this value rather than null/empty (3.0.125+):
	 * $qty = $input->post('qty', 'int', 1); // return 1 if no qty provided
	 * $color = $input->post('color', [ 'red', 'blue', 'green' ], 'red'); // return red if no color selected
	 *
	 * // you may optionally provide a callback function to sanitize/validate with (3.0.125+):
	 * $isActive = $input->post('active', function($val) { return $val ? true : false; }); 
	 * ~~~~~
	 * 
	 * @param string $key Name of POST variable you want to retrieve. 
	 *  - If populated, returns the value corresponding to the key or NULL if it doesn't exist.
	 *  - If blank, returns reference to the WireDataInput containing all POST vars.
	 * @param array|string|int|callable|null $valid Omit for no validation/sanitization, or provide one of the following:
	 *  - String name of Sanitizer method to to sanitize value with before returning it.
	 *  - CSV string of multiple sanitizer names to process the value, in order.
	 *  - Array of allowed values (aka whitelist), where input value must be one of these, otherwise null (or fallback value) will returned.
	 *    Values in the array may be any string or integer.
	 *  - Callback function to sanitize and validate the value.
	 *  - Integer if a specific number is the only allowed value other than fallback value (i.e. like a checkbox toggle).
	 * @param mixed|null Optional Fallback value to return if input value is not present or does not validate.
	 * @return null|mixed|WireInputData Returns one of the following:
	 *  - If given no `$key` argument, returns `WireInputData` with all unsanitized POST vars.
	 *  - If given no `$valid` argument, returns unsanitized value or NULL if not present.
	 *  - If given a Sanitizer name for `$valid` argument, returns value sanitized with that Sanitizer method (3.0.125+).
	 *  - If given an array of allowed values for `$valid` argument, returns value from that array if it was in the input, or null if not (3.0.125+).
	 *  - If given a callable function for `$valid` argument, returns the value returned by that function (3.0.125+).
	 *  - If given a `$fallback` argument, returns that value when it would otherwise return null (3.0.125+).
	 * @throws WireException if given unknown Sanitizer method for $valid argument
	 *
	 */
	public function post($key = '', $valid = null, $fallback = null) {
		if(is_null($this->postVars)) $this->postVars = $this->wire(new WireInputData($_POST, $this->lazy));
		if(!strlen($key)) return $this->postVars;
		if($valid === null && $fallback === null && !strpos($key, '[]')) return $this->postVars->__get($key);
		return $this->getValidInputValue($this->postVars, $key, $valid, $fallback);
	}

	/**
	 * Retrieve a named COOKIE variable value or all COOKIE variables
	 * 
	 * Always sanitize (and validate where appropriate) any values from user input.
	 * 
	 * The following optional features are available in ProcessWire version 3.0.125 and newer:
	 *
	 * - Provide a sanitization method as the 2nd argument to include sanitization.
	 * - Provide an array of valid values as the 2nd argument to limit input to those values.
	 * - Provide a callback function that receives the value and returns a validated value.
	 * - Provide a fallback value as the 3rd argument to use if value not present or invalid.
	 * - Append “[]” to the 1st argument to always force return value to be an array, i.e “colors[]”.
	 *
	 * Note that the `$valid` and `$fallback` arguments are only applicable if a `$key` argument is provided.
	 * See the `WireInput::get()` method for usage examples (get method works the same as cookie method).
	 *
	 * @param string $key Name of the COOKIE variable you want to retrieve. 
	 *  - If populated, returns the value corresponding to the key or NULL if it doesn't exist.
	 *  - If blank, returns reference to the WireDataInput containing all COOKIE vars.
	 * @param array|string|int|callable|null $valid Omit for no validation/sanitization, or provide one of the following:
	 *  - String name of Sanitizer method to to sanitize value with before returning it.
	 *  - CSV string of multiple sanitizer names to process the value, in order.
	 *  - Array of allowed values (aka whitelist), where input value must be one of these, otherwise null (or fallback value) will returned.
	 *    Values in the array may be any string or integer.
	 *  - Callback function to sanitize and validate the value.
	 *  - Integer if a specific number is the only allowed value other than fallback value (i.e. like a checkbox toggle).
	 * @param mixed|null Optional Fallback value to return if input value is not present or does not validate.
	 * @return null|mixed|WireInputData Returns one of the following:
	 *  - If given no `$key` argument, returns `WireInputData` with all unsanitized COOKIE vars.
	 *  - If given no `$valid` argument, returns unsanitized value or NULL if not present.
	 *  - If given a Sanitizer name for `$valid` argument, returns value sanitized with that Sanitizer method (3.0.125+).
	 *  - If given an array of allowed values for `$valid` argument, returns value from that array if it was in the input, or null if not (3.0.125+).
	 *  - If given a callable function for `$valid` argument, returns the value returned by that function (3.0.125+).
	 *  - If given a `$fallback` argument, returns that value when it would otherwise return null (3.0.125+).
	 * @throws WireException if given unknown Sanitizer method for $valid argument
	 *
	 */
	public function cookie($key = '', $valid = null, $fallback = null) {
		if(is_null($this->cookieVars)) $this->cookieVars = $this->wire(new WireInputData($_COOKIE, $this->lazy));
		if(!strlen($key)) return $this->cookieVars;
		if($valid === null && $fallback === null && !strpos($key, '[]')) return $this->cookieVars->__get($key);
		return $this->getValidInputValue($this->cookieVars, $key, $valid, $fallback);
	}

	/**
	 * Get or set a whitelist variable
	 *	
	 * Whitelist variables are used by modules and templates and assumed to be sanitized.
	 * Only place variables in the whitelist that you have already sanitized. 
	 * 
	 * The whitelist is a list of variables specifically set by the application as sanitized for use elsewhere in the application.
	 * This whitelist is not specifically used by ProcessWire unless you populate it from your templates or the API. 
	 * When populated, it is used by the MarkupPagerNav module (for instance) to ensure that sanitizedd query string (GET) variables 
	 * are maintained across paginations. 
	 * 
	 * ~~~~~
	 * // Retrieve a GET variable, sanitize/validate it, and populate to whitelist
	 * $limit = (int) $input->get('limit'); 
	 * if($limit < 10 || $limit > 100) $limit = 25; // validate
	 * $input->whitelist('limit', $limit); 
	 * ~~~~~
	 * ~~~~~
	 * // Retrieve a variable from the whitelist
	 * $limit = $input->whitelist('limit'); 
	 * ~~~~~
	 *
	 * @param string $key Whitelist variable name that you want to get or set. 
	 *  - If $key is blank, it assumes you are asking to return the entire whitelist. 
	 *  - If $key and $value are populated, it adds the value to the whitelist.
	 *  - If $key is an array, it adds all the values present in the array to the whitelist.
	 *  - If $value is omitted, it assumes you are asking for a value with $key, in which case it returns it. 
	 * @param mixed $value Value you want to set (if setting a value). See explanation for the $key param.
	 * @return null|mixed|WireInputData Returns whitelist variable value if getting a value (null if it doesn't exist).
	 *
	 */
	public function whitelist($key = '', $value = null) {
		if(is_null($this->whitelist)) $this->whitelist = $this->wire(new WireInputData()); 
		if(!$key) return $this->whitelist; 
		if(is_array($key)) return $this->whitelist->setArray($key); 
		if(is_null($value)) return $this->whitelist->__get($key); 
		$this->whitelist->__set($key, $value); 
		return $this->whitelist; 
	}

	/**
	 * Retrieve the URL segment with the given index (starting from 1)
	 *
	 * - URL segments must be enabled in the template settings (for template used by the page).
	 * - The index is 1 based (not 0 based).
	 * - If no index is provided, 1 is assumed. 
	 * - The maximum segments allowed can be adjusted in your `$config->maxUrlSegments` setting.
	 * - URL segments are populated by ProcessWire automatically on each request. 
	 * - URL segments are already sanitized as page names. 
	 * 
	 * ~~~~~
	 * // Produce different output in template depending on URL segment
	 * $action = $input->urlSegment(1); 
	 * if($action == 'photos') {
	 *   // display photos
	 * } else if($action == 'map') {
	 *   // display map
	 * } else if(strlen($action)) {
	 *   // unknown action, throw a 404
	 *   throw new Wire404Exception();
	 * } else {
	 *   // default or display main page
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URL-segments
	 *
	 * @param int $num Retrieve the n'th URL segment (default=1). 
	 * @return string Returns URL segment value or a blank string if the specified index is not found.
	 * @see WireInput::urlSegmentStr()
	 *
	 */
	public function urlSegment($num = 1) {
		if($num < 1) $num = 1; 
		return isset($this->urlSegments[$num]) ? $this->urlSegments[$num] : '';
	}

	/**
	 * Retrieve array of all URL segments
	 * 
	 * - URL segments must be enabled in the template settings (for template used by the page).
	 * - The maximum segments allowed can be adjusted in your `$config->maxUrlSegments` setting.
	 * - URL segments are populated by ProcessWire automatically on each request.
	 * - URL segments are already sanitized as page names. 
	 * 
	 * #pw-group-URL-segments
	 * 
	 * @return array Returns an array of strings, or an empty array if no URL segments available.
	 * 
	 */
	public function urlSegments() {
		return $this->urlSegments; 
	}

	/**
	 * Set a URL segment value 
	 *
	 * - This is typically only used by the core. 
	 * - To unset, specify NULL as the value. 
	 * 
	 * #pw-group-URL-segments
	 *
	 * @param int $num Number of this URL segment (1 based)
	 * @param string|null $value Value to set, or NULL to unset. 
	 *
	 */
	public function setUrlSegment($num, $value) {
		$num = (int) $num; 
		$maxLength = $this->wire('config')->maxUrlSegmentLength;
		if($maxLength < 1) $maxLength = 128;
		if(is_null($value)) {
			// unset
			$n = 0;
			$urlSegments = array();
			foreach($this->urlSegments as $k => $v) {
				if($k == $num) continue;
				$urlSegments[++$n] = $v;
			}
			$this->urlSegments = $urlSegments;
		} else {
			// sanitize to standard PW name format
			$urlSegment = $this->wire('sanitizer')->name($value, false, $maxLength);
			// if UTF-8 mode and value changed during name sanitization, try pageNameUTF8 instead
			if($urlSegment !== $value && $this->wire('config')->pageNameCharset == 'UTF8') {
				$urlSegment = $this->wire('sanitizer')->pageNameUTF8($value, $maxLength);
			}
			$this->urlSegments[$num] = $urlSegment;
		}
		
	}
	
	/**
	 * Get the string of URL segments separated by slashes
	 *
	 * - Note that return value lacks leading or trailing slashes.
	 * - URL segments must be enabled in the template settings (for template used by the page).
	 * - The maximum segments allowed can be adjusted in your `$config->maxUrlSegments` setting.
	 * - URL segments are populated by ProcessWire automatically on each request.
	 * - URL segments are already sanitized as page names. 
	 * - The URL segment string can also be accessed by property: `$input->urlSegmentStr`.
	 * 
	 * ~~~~~
	 * // Adjust output according to urlSegmentStr
	 * // In this case our urlSegmentStr is 2 URL segments
	 * $s = $input->urlSegmentStr();
	 * if($s == 'photos/large') {
	 *   // show large photos
	 * } else if($s == 'photos/small') {
	 *   // show small photos
	 * } else if($s == 'map') {
	 *   // show map
	 * } else if(strlen($s)) {
	 *   // something we don't recognize
	 *   throw new Wire404Exception();
	 * } else {
	 *   // no URL segments present, do some default behavior
	 *   echo $page->body;
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URL-segments
	 *
	 * @param bool $verbose Include pagination number (pageNum) and trailing slashes, when appropriate? (default=false)
	 *  - Use this option for a more link-ready version of the URL segment string (since 3.0.106). 
	 * @param array $options Options to adjust behavior (since 3.0.106):
	 *  - `segments` (array|null): Optionally specify URL segments to use, rather than those from current request. (default=null)
	 *  - `pageNum` (int): Optionally specify page number to use rather than current. (default=current page number)
	 *  - `page` (Page): Optionally specify Page to use for context. (default=current page)
	 *  - *NOTE* the `pageNum` and `page` options are not applicable unless the $verbose argument is true. 
	 * @return string URL segment string, i.e. `segment1/segment2/segment3` or blank if none
	 * @see WireInput::urlSegment()
	 *
	 */
	public function urlSegmentStr($verbose = false, array $options = array()) {
	
		if(isset($options['segments']) && is_array($options['segments'])) {
			$segments = $options['segments'];
		} else {
			$segments = $this->urlSegments;
		}
		
		$str = implode('/', $segments);
	
		// regular mode exits here
		if(!$verbose) return $str;
	
		// verbose mode takes page number, slash settings, and other $options into account
		$page = isset($options['page']) && $options['page'] instanceof Page ? $options['page'] : $this->wire('page');
		$template = $page->template;
		
		if(isset($options['pageNum'])) {
			$pageNum = (int) $options['pageNum']; 
		} else if($template->allowPageNum) {
			$pageNum = $this->pageNum();
		} else {
			$pageNum = 0;
		}
		
		if($pageNum > 1) {
			if(strlen($str)) $str .= '/';
			$str .= $this->pageNumStr($pageNum);
			if($template->slashPageNum) $str .= '/';
		} else if($template->slashUrlSegments && strlen($str)) {
			$str .= '/';
		}
			
		return $str;
	}

	/**
	 * Return the current pagination/page number (starting from 1)
	 *
	 * - Page numbers must be enabled in the template settings (for template used by the page).
	 * - The current page number affects all paginated page finding operations. 
	 * - First page number is 1 (not 0). 
	 * 
	 * ~~~~~
	 * // Adjust output according to page number
	 * if($input->pageNum == 1) {
	 *   echo $page->body; 
	 * } else {
	 *   echo "<a href='$page->url'>Return to first page</a>";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-URL-segments
	 *
	 * @return int Current pagination number
	 *
	 */
	public function pageNum() {
		return $this->pageNum; 	
	}
	
	/**
	 * Return the string that represents the page number URL segment
	 * 
	 * Returns blank when page number is 1, since page 1 is assumed when no pagination number present in URL. 
	 * 
	 * This is the string that gets appended to the URL and typically looks like `page123`,
	 * but can be changed by modifying the `$config->pageNumUrlPrefix` setting, or specifying
	 * language-specific page number settings in the LanguageSupportPageNames module. 
	 * 
	 * #pw-group-URL-segments
	 * 
	 * @param int $pageNum Optionally specify page number to use (default=0, which means use current page number)
	 * @return string
	 * @since 3.0.106
	 * 
	 */
	public function pageNumStr($pageNum = 0) {
		$pageNumStr = '';
		$pageNum = (int) $pageNum;
		if($pageNum < 1) $pageNum = $this->pageNum();
		if($pageNum > 1) $pageNumStr = $this->wire('config')->pageNumUrlPrefix . $pageNum;
		return $pageNumStr;
	}

	/**
	 * Set the current page number. 
	 *
	 * - This is typically used only by the core. 
	 * - Note that the first page should be 1 (not 0).
	 * 
	 * #pw-group-URL-segments
	 *
	 * @param int $num
	 *
	 */
	public function setPageNum($num) {
		$this->pageNum = (int) $num;	
	}

	/**	
	 * Retrieve the get, post, cookie or whitelist vars using a direct reference, i.e. $input->cookie
	 *
	 * Can also be used with URL segments, i.e. $input->urlSegment1, $input->urlSegment2, $input->urlSegment3, etc. 
	 * And can also be used for $input->pageNum.
	 *
	 * @param string $key
	 * @return string|int|null
	 *
	 */
	public function __get($key) {

		if($key == 'pageNum') return $this->pageNum; 
		if($key == 'urlSegments') return $this->urlSegments; 
		if($key == 'urlSegmentsStr' || $key == 'urlSegmentStr') return $this->urlSegmentStr();
		if($key == 'url') return $this->url();
		if($key == 'httpUrl' || $key == 'httpURL') return $this->httpUrl();
		if($key == 'fragment') return $this->fragment();
		if($key == 'queryString') return $this->queryString();
		if($key == 'scheme') return $this->scheme();

		if(strpos($key, 'urlSegment') === 0) {
			if(strlen($key) > 10) $num = (int) substr($key, 10); 
				else $num = 1; 
			return $this->urlSegment($num);
		}

		$value = null;
		$gpc = array('get', 'post', 'cookie', 'whitelist'); 

		if(in_array($key, $gpc)) {
			$value = $this->$key(); 

		} else {
			// Like PHP's $_REQUEST where accessing $input->var considers get/post/cookie/whitelist
			// what it actually considers depends on what's set in the $config->wireInputOrder variable
			$order = (string) $this->wire('config')->wireInputOrder; 
			if(!$order) return null;
			$types = explode(' ', $order); 
			foreach($types as $t) {
				if(!in_array($t, $gpc)) continue; 	
				$value = $this->$t($key); 
				if(!is_null($value)) break;
			}
		}
		return $value; 
	}

	public function __isset($key) {
		return $this->__get($key) !== null;
	}

	/**
	 * Get the URL that initiated the current request, including URL segments and page numbers
	 * 
	 * - This should be the same as `$page->url` except that it includes URL segments and page numbers, when present.
	 * 
	 * - Note that this does not include query string unless requested (see arguments). 
	 * 
	 * - WARNING: if query string requested, it can contain undefined/unsanitized user input. If you use it for output
	 *   make sure that you entity encode first (by running through `$sanitizer->entities()` for instance).
	 * 
	 * ~~~~~~
	 * $url = $input->url(); 
	 * $url = $sanitizer->entities($url); // entity encode for output
	 * echo "You accessed this page at: $url";
	 * ~~~~~~
	 * 
	 * #pw-group-URLs
	 * 
	 * @param bool $withQueryString Include the query string as well? (if present, default=false)
	 * @return string
	 * @see WireInput::httpUrl(), Page::url()
	 * 
	 */
	public function url($withQueryString = false) {

		$url = '';
		/** @var Page $page */
		$page = $this->wire('page'); 
		$config = $this->wire('config');
		$sanitizer = $this->wire('sanitizer');
		
		if($page && $page->id) {
			// pull URL from page
			$url = $page->url();
			$segmentStr = $this->urlSegmentStr();
			$pageNum = $this->pageNum();
			if(strlen($segmentStr) || $pageNum > 1) {
				if($segmentStr) $url = rtrim($url, '/') . '/' . $segmentStr;
				if($pageNum > 1) $url = rtrim($url, '/') . '/' . $this->pageNumStr($pageNum); 
				if(isset($_SERVER['REQUEST_URI'])) {
					$info = parse_url($_SERVER['REQUEST_URI']);
					if(!empty($info['path']) && substr($info['path'], -1) == '/') $url .= '/'; // trailing slash
				}
				if($pageNum > 1) {
					if($page->template->slashPageNum == 1) {
						if(substr($url, -1) != '/') $url .= '/';
					} else if($page->template->slashPageNum == -1) {
						if(substr($url, -1) == '/') $url = rtrim($url, '/');
					}
				} else if(strlen($segmentStr)) {
					if($page->template->slashUrlSegments == 1) {
						if(substr($url, -1) != '/') $url .= '/';
					} else if($page->template->slashUrlSegments == -1) {
						if(substr($url, -1) == '/') $url = rtrim($url, '/');
					}
				}
			}
			
		} else if(isset($_SERVER['REQUEST_URI'])) {
			// page not yet available, attempt to pull URL from request uri
			$info = parse_url($_SERVER['REQUEST_URI']);
			$parts = explode('/', $info['path']);
			$charset = $config ? $config->pageNameCharset : '';
			foreach($parts as $i => $part) {
				if($i > 0) $url .= "/";
				$url .= ($charset === 'UTF8' ? $sanitizer->pageNameUTF8($part) : $sanitizer->pageName($part, false));
			}
			if(!empty($info['path']) && substr($info['path'], -1) == '/') {
				$url = rtrim($url, '/') . '/'; // trailing slash
			}
		}
		
		if($withQueryString) {
			$queryString = $this->queryString();
			if(strlen($queryString)) {
				$url .= "?$queryString";
			}
		}
		
		return $url;
	}

	/**
	 * Get the http URL that initiated the current request, including scheme, URL segments and page numbers
	 * 
	 * - This should be the same as `$page->httpUrl` except that it includes URL segments and page numbers, when present.
	 *
	 * - Note that this does not include query string unless requested (see arguments).
	 *
	 * - WARNING: if query string requested, it can contain undefined/unsanitized user input. If you use it for output
	 *   make sure that you entity encode first (by running through `$sanitizer->entities()` for instance).
	 * 
	 * ~~~~~~
	 * $url = $input->httpUrl();
	 * $url = $sanitizer->entities($url); // entity encode for output
	 * echo "You accessed this page at: $url";
	 * ~~~~~~
	 * 
	 * #pw-group-URLs
	 * 
	 * @param bool $withQueryString Include the query string? (default=false) 
	 * @return string
	 * @see WireInput::url(), Page::httpUrl()
	 * 
	 */
	public function httpUrl($withQueryString = false) {
		return $this->httpHostUrl() . $this->url($withQueryString);
	}

	/**
	 * Same as httpUrl() method but always uses https scheme, rather than current request scheme
	 * 
	 * See httpUrl() method for argument and usage details. 
	 * 
	 * @param bool $withQueryString
	 * @return string
	 * @see WireInput::httpUrl()
	 * 
	 */
	public function httpsUrl($withQueryString = false) {
		return $this->httpHostUrl(true) . $this->url($withQueryString);
	}

	/**
	 * Get current scheme and URL for hostname without any path or query string
	 * 
	 * For example: `https://www.domain.com`
	 * 
	 * #pw-group-URLs
	 * 
	 * @param string|bool|null Optionally specify this argument to force a particular scheme (rather than using current):
	 *  - boolean true to force “https”
	 *  - boolean false to force “http”
	 *  - string with scheme you want to use
	 *  - blank string or "//" for no scheme, i.e. URL begins with "//" which refers to current scheme. 
	 *  - omit argument or null to use current request scheme (default behavior). 
	 * @return string
	 * 
	 */
	public function httpHostUrl($scheme = null) {
		if($scheme === true) {
			$scheme = 'https://';
		} else if($scheme === false) {
			$scheme = 'http://';
		} else if(is_string($scheme)) {
			if(strlen($scheme)) {
				if(strpos($scheme, '//') === false) $scheme = "$scheme://";
			} else {
				$scheme = '//';
			}
		} else {
			$scheme = $this->scheme() . '://';
		}
		return $scheme . $this->wire('config')->httpHost;
	}

	/**
	 * Anchor/fragment for current request (i.e. #fragment)
	 * 
	 * Note that this is not sanitized. Fragments generally can't be seen
	 * by the server, so this function may be useless.
	 * 
	 * #pw-internal
	 *
	 * @return string
	 *
	 */
	public function fragment() {
		if(strpos($_SERVER['REQUEST_URI'], '#') === false) return '';
		$info = parse_url($_SERVER['REQUEST_URI']);
		return empty($info['fragment']) ? '' : $info['fragment']; 
	}

	/**
	 * Return the unsanitized query string that was part of this request, or blank if none
	 * 
	 * Note that the returned query string is not sanitized, so if you use it in any output
	 * be sure to run it through `$sanitizer->entities()` first. An optional assoc array
	 * param can be used to add new GET params or override existing ones.
	 * 
	 * #pw-group-URLs
	 * 
	 * @param array $overrides Optional assoc array for overriding or adding GET params
	 * @return string Returns the unsanitized query string
	 * 
	 */
	public function queryString($overrides = array()) {
		return $this->get()->queryString($overrides);
	}

	/**
	 * Return the current access scheme/protocol 
	 *
	 * Note that this is only useful for http/https, as we don't detect other schemes.
	 * 
	 * #pw-group-URLs
	 *
	 * @return string Return value is either "https" or "http"
	 *
	 */
	public function scheme() {
		return $this->wire('config')->https ? 'https' : 'http'; 
	}

	/**
	 * Return the current request method (i.e. GET, POST, etc.) or blank if not known
	 * 
	 * Possible return values are:
	 * - GET
	 * - POST
	 * - HEAD
	 * - PUT
	 * - DELETE
	 * - OPTIONS
	 * - or blank if not known
	 * 
	 * @param string $method Optionally enter the request method to return bool if current method matches
	 * @return string|bool
	 * @since 3.0.39
	 * 
	 */
	public function requestMethod($method = '') {
		if(isset($_SERVER['REQUEST_METHOD'])) {
			$m = strtoupper($_SERVER['REQUEST_METHOD']);
			$requestMethod = isset($this->requestMethods[$m]) ? $this->requestMethods[$m] : '';
		} else {
			$requestMethod = '';
		}
		if($method) return strtoupper($method) === $requestMethod;
		return $requestMethod; 
	}
	
	/**
	 * Provides the implementation for get/post/cookie method validation and fallback features
	 *
	 * @param WireInputData $input 
	 * @param string $key Name of variable to pull from $input
	 * @param array|string|callable|mixed|null $valid String containing name of Sanitizer method, or array of allowed values.
	 * @param string|array|int|mixed $fallback Return this value rather than null if input value is not present or not valid.
	 * @return array|int|mixed|null|WireInputData|string
	 * @throws WireException if given unknown Sanitizer method or some other invalid arguments.
	 *
	 */
	protected function getValidInputValue(WireInputData $input, $key, $valid, $fallback) {

		if(!strlen($key)) return $input; // return all
		
		if(strpos($key, '[]')) {
			$key = trim($key, '[]');
			$forceArray = true;
		} else {
			$forceArray = false;
		}

		$value = $input->__get($key);
		$cleanValue = null;

		if($value === null && $fallback !== null) {
			// no value present, use fallback
			$cleanValue = $fallback;
			
		} else if($value === null && $valid === null && $fallback === null) {
			// everything null
			$cleanValue = null;
			
		} else if($valid === null) {
			// no sanitization/validation requested
			$cleanValue = $value === null ? $fallback : $value;

		} else if(is_string($valid)) {
			// sanitizer "name" or multiple "name1,name2,name3" specified for $valid argument
			$cleanValue = $this->sanitizeValue($valid, $value, ($forceArray || is_array($fallback)));
			if(empty($value) && $fallback !== null) $cleanValue = $fallback;

		} else if(is_array($valid)) {
			// whitelist provided for $valid argument
			$cleanValue = $this->filterValue($value, $valid, ($forceArray || is_array($fallback))); 

		} else if(is_callable($valid)) {
			// callable function provided for sanitization and validation
			$cleanValue = call_user_func($valid, $value);

		} else if(is_int($valid)) {
			// single integer provided as only allowed value
			if(ctype_digit("$value")) {
				$value = (int) $value;
				if($valid === $value) $cleanValue = $valid;
			}
		}

		if(($cleanValue === null || ($forceArray && empty($cleanValue))) && $fallback !== null) {
			$cleanValue = $fallback;
		}
		if($forceArray && !is_array($cleanValue)) {
			$cleanValue = ($cleanValue === null ? array() : array($cleanValue));
		}

		return $cleanValue;
	}

	/**
	 * Filter value against given $valid whitelist
	 * 
	 * @param string|array $value
	 * @param array $valid Whitelist of valid values
	 * @param bool $getArray Filter to allow multiple values (array)?
	 * @return array|string|null
	 * @throws WireException If given a multidimensional array for $valid argument
	 * 
	 */
	protected function filterValue($value, array $valid, $getArray) {
		
		$cleanValue = $getArray ? array() : null;
		
		if($getArray) {
			if(!is_array($value)) {
				// array expected but input value is not an array, so convert it to one
				$value = ($value === null ? array() : array($value));
			}
		} else while(is_array($value)) {
			$value = reset($value);
		}
		
		foreach($valid as $validValue) {
			if(is_array($validValue)) {
				throw new WireException('Array of arrays not supported for valid value whitelist');
			}
			if($getArray) {
				// input value is an array, as is the fallback, so array is expected return value
				foreach($value as $dirtyValue) {
					if(is_array($dirtyValue)) continue;
					// multiple items allowed
					if("$validValue" === "$dirtyValue") $cleanValue[] = $validValue;
				}
			} else if("$value" === "$validValue") {
				$cleanValue = $validValue;
				break; // stop at one
			}
		}
		
		return $cleanValue;
	}

	/**
	 * Sanitize the given value with the given method(s)
	 * 
	 * @param string $method Sanitizer method name or CSV string of sanitizer method names
	 * @param string|array|null $value
	 * @param bool $getArray
	 * @return array|mixed|null
	 * @throws WireException If given unknown sanitizer method
	 * 
	 */
	protected function sanitizeValue($method, $value, $getArray) {
		
		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		//$values = is_array($value) ? $value : ($value === null ? array() : array($value));
		$values = is_array($value) ? $value : array($value);
		$methods = strpos($method, ',') === false ? array($method) : explode(',', $method);
		$cleanValues = array();
		
		foreach($values as $value) {
			foreach($methods as $method) {
				$method = trim($method);
				if(empty($method)) continue;
				if($sanitizer->methodExists($method)) {
					$value = $sanitizer->sanitize($value, $method); 
				} else {
					throw new WireException("Unknown sanitizer method: $method");
				}
			}
			$cleanValues[] = $value;
		}
		
		$cleanValue = $getArray ? $cleanValues : reset($cleanValues);
		if($cleanValue === false) $cleanValue = null;
		
		return $cleanValue; 
	}


	/**
	 * Emulate register globals OFF
	 *
	 * Should be called after session_start()
	 * 
	 * #pw-internal
	 *
	 * This function is from the PHP documentation at:
	 * http://www.php.net/manual/en/faq.misc.php#faq.misc.registerglobals
	 *
	 */
	protected function unregisterGLOBALS() {

		if(!ini_get('register_globals')) {
			return;
		}

		if(isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS'])) {
			unset($_GET['GLOBALS'], $_POST['GLOBALS'], $_COOKIE['GLOBALS'], $_FILES['GLOBALS']);
		}

		// Variables that shouldn't be unset
		$noUnset = array('GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');

		$input = array_merge($_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES, isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array());

		foreach ($input as $k => $v) {
			if(!in_array($k, $noUnset) && isset($GLOBALS[$k])) {
				unset($GLOBALS[$k]);
			}
		}
	}
	
	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * This is used when you print_r() an object instance.
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		$info['get'] = $this->getVars ? $this->getVars->getArray() : null;
		$info['post'] = $this->postVars ? $this->postVars->getArray() : null;
		$info['cookie'] = $this->cookieVars ? $this->cookieVars->getArray() : null;
		$info['whitelist'] = $this->whitelist ? $this->whitelist->getArray() : null;
		$info['urlSegments'] = $this->urlSegments;
		$info['pageNum'] = $this->pageNum;
		return $info;
	}
}

