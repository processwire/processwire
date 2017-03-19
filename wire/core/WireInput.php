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
	 * Retrieve a named GET variable value, or all GET variables (from URL query string)
	 * 
	 * Always sanitize (and validate where appropriate) any values from user input. 
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
	 * ~~~~~
	 *
	 * @param string $key Name of GET variable you want to retrieve. 
	 * - If populated, returns the value corresponding to the key or NULL if it doesn't exist.
	 * - If blank, returns reference to the WireDataInput containing all GET vars. 
	 * @return null|mixed|WireInputData Returns unsanitized value or NULL if not present. If no $key given, returns WireInputData with all GET vars. 
	 *
	 */
	public function get($key = '') {
		if(is_null($this->getVars)) {
			$this->getVars = $this->wire(new WireInputData($_GET));
			$this->getVars->offsetUnset('it');
		}
		return $key ? $this->getVars->__get($key) : $this->getVars; 
	}

	/**
	 * Retrieve a named POST variable value, or all POST variables
	 * 
	 * Always sanitize (and validate where appropriate) any values from user input.
	 * 
	 * ~~~~~
	 * // Retrieve a "comments" POST variable, sanitize and output it
	 * $comments = $input->post('comments'); 
	 * $comments = $sanitizer->text($comments); // sanitize input as 1-line text
	 * echo $sanitizer->entities($comments); // sanitize for output
	 * 
	 * // You can also combine $input and one $sanitizer call like this,
	 * // replacing "text" with name of any $sanitizer method: 
	 * $comments = $input->post->text('comments'); 
	 * ~~~~~
	 * 
	 * @param string $key Name of POST variable you want to retrieve. 
	 *  - If populated, returns the value corresponding to the key or NULL if it doesn't exist.
	 *  - If blank, returns reference to the WireDataInput containing all POST vars. 
	 * @return null|mixed|WireInputData Returns unsanitized value or NULL if not present. If no $key given, returns WireInputData with all POST vars. 
	 *
	 */
	public function post($key = '') {
		if(is_null($this->postVars)) $this->postVars = $this->wire(new WireInputData($_POST)); 
		return $key ? $this->postVars->__get($key) : $this->postVars; 
	}

	/**
	 * Retrieve a named COOKIE variable value or all COOKIE variables
	 * 
	 * Always sanitize (and validate where appropriate) any values from user input.
	 *
	 * @param string $key Name of the COOKIE variable you want to retrieve. 
	 *  - If populated, returns the value corresponding to the key or NULL if it doesn't exist.
	 *  - If blank, returns reference to the WireDataInput containing all COOKIE vars. 
	 * @return null|mixed|WireInputData Returns unsanitized value or NULL if not present. If no $key given, returns WireInputData with all COOKIE vars.
	 *
	 */
	public function cookie($key = '') {
		if(is_null($this->cookieVars)) $this->cookieVars = $this->wire(new WireInputData($_COOKIE)); 
		return $key ? $this->cookieVars->__get($key) : $this->cookieVars; 
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
		if(is_null($value)) {
			// unset
			$n = 0;
			$urlSegments = array();
			foreach($this->urlSegments as $k => $v) {
				if($k == $num) continue;
				$urlSegments[++$n] = $v;
			}
			$this->urlSegments = $urlSegments;
		} else if($this->wire('config')->pageNameCharset == 'UTF8') {
			// set UTF8
			$this->urlSegments[$num] = $this->wire('sanitizer')->pageNameUTF8($value);
		} else {
			// set ascii
			$this->urlSegments[$num] = $this->wire('sanitizer')->name($value);
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
	 * @return string URL segment string, i.e. `segment1/segment2/segment3` or blank if none
	 * @see WireInput::urlSegment()
	 *
	 */
	public function urlSegmentStr() {
		return implode('/', $this->urlSegments);
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
				if($pageNum > 1) $url = rtrim($url, '/') . '/' . $config->pageNumUrlPrefix . $pageNum;
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
			$charset = $config->pageNameCharset;
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
		return $this->scheme() . '://' . $this->wire('config')->httpHost . $this->url($withQueryString);
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
}

