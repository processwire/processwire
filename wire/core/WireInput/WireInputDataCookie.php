<?php namespace ProcessWire;

/**
 * Provides methods for managing cookies via the $input->cookie API variable
 * 
 * #pw-summary Enables getting, setting and removing cookies from the ProcessWire API using `$input->cookie`.
 * 
 * #pw-body =
 * 
 * - Whether getting or setting, cookie values are always strings.
 * - Values retrieved from `$input->cookie` are user input (like PHP’s $_COOKIE) and need to be sanitized and validated by you.
 * - When removing/unsetting cookies, the path, domain, secure, and httponly options must be the same as when the cookie was set,
 *   as a result, it’s good to have these things predefined in `$config->cookieOptions` rather than setting during runtime.
 * - Note that this class does not manage PW’s session cookies. 
 *
 * ~~~~~
 * // setting cookies
 * $input->cookie->foo = 'bar';
 * $input->cookie->set('foo', 'bar'); // same as above
 * $input->cookie['foo'] = 'bar'; // same as above
 * 
 * // setting cookies, with options
 * $input->cookie->set('foo', 'bar', 86400); // live for 1 day
 * $input->cookie->options('age', 3600); // any further set() cookies live for 1 hour (3600s)
 * $input->cookie->set('foo', 'bar'); // uses setting from above options() call
 * 
 * // getting cookies 
 * $bar = $input->cookie->foo;
 * $bar = $input->cookie['foo']; // same as above
 * $bar = $input->cookie('foo'); // same as above
 * $bar = $input->cookie->get('foo'); // same as above
 * $bar = $input->cookie->text('foo'); // sanitize with text() sanitizer
 * 
 * // removing cookies
 * unset($input->cookie->foo);
 * $input->cookie->remove('foo'); // same as above
 * $input->cookie->set('foo', null); // same as above
 * $input->cookie->removeAll(); // remove all cookies
 * 
 * // to modify default cookie settings, add this to your /site/config.php file and edit:
 * $config->cookieOptions = [
 * 
 *   // Max age of cookies in seconds or 0 to expire with session 
 *   // 3600=1 hour, 86400=1 day, 604800=1 week, 2592000=30 days, etc.
 *   'age' => 604800,
 * 
 *   // Cookie path/URL or null for PW installation’s root URL 
 *   'path' => null,
 * 
 *   // Cookie domain: null for current hostname, true for all subdomains of current domain, 
 *   // domain.com for domain and all subdomains (same as true), www.domain.com for www subdomain
 *   // and additional hosts off www subdomain (i.e. dev.www.domain.com)
 *   'domain' => null,
 * 
 *   // Transmit cookies only over secure HTTPS connection? 
 *   // Specify true, false, or null to auto-detect (uses true for cookies set when HTTPS).
 *   'secure' => null,
 * 
 *   // Cookie SameSite value: When set to “Lax” cookies are preserved on GET requests to this site
 *   // originated from external links. May also be “Strict” or “None”. The 'secure' option is
 *   // required for “None”. Default value is “Lax”. Available in PW 3.0.178+.
 *   'samesite' => 'Lax',
 * 
 *   // Make cookies accessible by HTTP (ProcessWire/PHP) only? 
 *   // When true, cookie is http/server-side only and not visible to client-side JS code.
 *   'httponly' => false,
 * 
 *   // If set cookie fails (perhaps due to output already sent), 
 *   // attempt to set at beginning of next request? 
 *   'fallback' => true, 
 * ];
 * ~~~~~
 * 
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 */ 

class WireInputDataCookie extends WireInputData {

	/**
	 * Are we initialized?
	 * 
	 * @var bool
	 * 
	 */
	protected $init = false;

	/**
	 * Default cookie options
	 * 
	 * @var array
	 * 
	 */
	protected $defaultOptions = array(
		'age' => 0, 
		'expire' => null, 
		'path' => null,
		'domain' => null,
		'secure' => null,
		'httponly' => false, 
		'samesite' => 'Lax',
		'fallback' => true,
	);

	/**
	 * Cookie options specifically set at runtime
	 * 
	 * @var array
	 * 
	 */
	protected $options = array();

	/**
	 * Cookie names not allowed to be set or removed (i.e. session cookies)
	 * 
	 * @var array
	 * 
	 */
	protected $skipCookies = array();
	
	/**
	 * Construct
	 *
	 * @param array $input Associative array of variables to store
	 * @param bool $lazy Use lazy loading?
	 *
	 */
	public function __construct(&$input = array(), $lazy = false) {
		if($lazy) {} // lazy option not used by cookie
		parent::__construct($input, false);
	}

	/**
	 * Initialize and set any pending cookies from previous request
	 * 
	 * #pw-internal
	 * 
	 * @since 3.0.141
	 * 
	 */
	public function init() {
		$this->init = true;
		$session = $this->wire()->session;
		$cookies = $session->getFor($this, '');
		if(!empty($cookies)) {
			$this->setArray($cookies);
			$session->removeAllFor($this);
		}
	}

	/**
	 * Get or set cookie options
	 *
	 * - Omit all arguments to get current options.
	 * - Specify string for $key (and omit $value) to get the value of one option.
	 * - Specify both $key and $value arguments to set one option.
	 * - Specify associative array for $key (and omit $value) to set multiple options.
	 * 
	 * Options you can get or set: 
	 * 
	 * - `age` (int): Max age of cookies in seconds or 0 to expire with session. For example: 3600=1 hour, 86400=1 day,
	 *    604800=1 week, 2592000=30 days, etc. Note that the default may be overridden by `$config->cookieOptions` setting from
	 *    your /site/config.php file. (default=86400)
	 * - `expire` (int|string): If you prefer to use an expiration date rather than the `age` option, specify a unix timestamp (int),
	 *    ISO-8601 date string, or any date string recognized by PHP’s strtotime(), like "2020-11-03" or +1 WEEK", etc. (default=null).
	 *    Please note the expire option was added in 3.0.159, previous versions should use the `age` option only.
	 * - `path` (string|null): Cookie path/URL or null for PW installation’s root URL. (default=null)
	 * - `secure` (bool|null): Transmit cookies only over secure HTTPS connection? Specify true or false, or use null to auto-detect,
	 *    which uses true for cookies set when HTTPS is detected. (default=null)
	 * - `httponly` (bool): When true, cookie is visible to PHP/ProcessWire only and not visible to client-side JS code. (default=false)
	 * - `fallback` (bool): If set cookie fails (perhaps due to output already sent), attempt to set at beginning of next request? (default=true)
	 * - `domain` (string|bool|null): Cookie domain, specify one of the following: `null` or blank string for current hostname [default],
	 *    boolean `true` for all subdomains of current domain, `domain.com` for domain.com and *.domain.com [same as true], `www.domain.com`
	 *    for www subdomain and and hostnames off of it, like dev.www.domain.com. (default=null, current hostname)
	 *
	 * @param string|array|null $key
	 * @param string|array|int|float|null $value
	 * @return string|array|int|float|null|$this
	 * @since 3.0.141
	 *
	 */
	public function options($key = null, $value = null) {
		if($key === null) {
			// get all
			return $this->options;
		} else if(is_array($key) && $value === null) {
			// set multiple
			$this->options = array_merge($this->options, $key);
		} else if($value === null) {
			// get one
			return isset($this->options[$key]) ? $this->options[$key] : null;
		} else {
			// set one
			$this->options[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set a cookie (directly)
	 * 
	 * To set options for setting cookie, use $input->cookie->options(key, value); or $config->cookieOptions(key, value);
	 * Note that options set from $input->cookie->options take precedence over those set to $config. 
	 * 
	 * @param string $key Cookie name
	 * @param array|float|int|null|string $value Cookie value
	 * 
	 */
	public function __set($key, $value) {
		
		if(!$this->init) {
			// initial set of existing cookies that are present from constructor
			parent::__set($key, $value);
			return;
		}
		
		$this->setCookie($key, $value, array()); 
	}
	
	/**
	 * Get a cookie value
	 * 
	 * Gets a previously set cookie value or null if cookie not present or expired.
	 * Cookies are a type of user input, so always sanitize (and validate where appropriate) any values. 
	 * 
	 * ~~~~~
	 * $val = $input->cookie->foo;
	 * $val = $input->cookie->get('foo'); // same as above
	 * $val = $input->cookie->text('foo'); // get and use text sanitizer
	 * ~~~~~
	 * 
	 * @param string $key Name of cookie to get
	 * @param array|int|string $options Options not currently used, but available for descending classes or future use
	 * @return string|int|float|array|null $value
	 *
	 */
	public function get($key, $options = array()) {
		return parent::get($key, $options); 
	}
	
	/**
	 * Set a cookie (optionally with options)
	 * 
	 * The defaults or previously set options from an `options()` method call are used for any `$options` not specified.
	 * 
	 * ~~~~~
	 * $input->cookie->foo = 'bar'; // set with default options (expires with session)
	 * $input->cookie->set('foo', 'bar'); // same as above
	 * $input->cookie->set('foo', 'bar', 86400); // expire after 86400 seconds (1 day)
	 * $input->cookie->set('foo', 'bar', [ // set with options
	 *   'age' => 86400, 
	 *   'path' => $page->url,
	 *   'httponly' => true, 
	 * ]); 
	 * ~~~~~
	 *
	 * @param string $key Cookie name
	 * @param string $value Cookie value
	 * @param array|int|string $options Specify int for `age` option, string for `expire` option, or array for multiple options:
	 * - `age` (int): Max age of cookies in seconds or 0 to expire with session. For example: 3600=1 hour, 86400=1 day,
	 *    604800=1 week, 2592000=30 days, etc. Note that the default may be overridden by `$config->cookieOptions` setting from
	 *    your /site/config.php file. (default=86400)
	 * - `expire` (int|string): If you prefer to use an expiration date rather than the `age` option, specify a unix timestamp (int),
	 *    ISO-8601 date string, or any date string recognized by PHP’s strtotime(), like "2020-11-03" or +1 WEEK", etc. (default=null).
	 *    Please note the expire option was added in 3.0.159, previous versions should use the `age` option only.
	 * - `path` (string|null): Cookie path/URL or null for PW installation’s root URL. (default=null)
	 * - `secure` (bool|null): Transmit cookies only over secure HTTPS connection? Specify true or false, or use null to auto-detect,
	 *    which uses true for cookies set when HTTPS is detected. (default=null)
	 * - `httponly` (bool): When true, cookie is visible to PHP/ProcessWire only and not visible to client-side JS code. (default=false)
	 * - `fallback` (bool): If set cookie fails (perhaps due to output already sent), attempt to set at beginning of next request? (default=true)
	 * - `domain` (string|bool|null): Cookie domain, specify one of the following: `null` or blank string for current hostname [default],
	 *    boolean `true` for all subdomains of current domain, `domain.com` for domain.com and *.domain.com [same as true], `www.domain.com`
	 *    for www subdomain and and hostnames off of it, like dev.www.domain.com. (default=null, current hostname)
	 * @return $this
	 * @since 3.0.141 
	 *
	 */
	public function set($key, $value, $options = array()) {
		
		if(!$this->init) {
			parent::__set($key, $value);
			return $this;
		}
		
		if(!is_array($options)) { 
			if(is_int($options) || ctype_digit("$options")) {
				$age = (int) $options;
				$options = array('age' => $age);
			} else if(!empty($options) && is_string($options)) {
				$expire = $options;
				$options = array('expire' => $expire);
			} else {
				$options = array();
			}
		}
		
		$this->setCookie($key, $value, $options);
		
		return $this;
	}

	/**
	 * Remove a cookie value by name
	 *
	 * @param string $key Name of cookie variable to remove value for
	 * @return WireInputDataCookie|WireInputData|$this
	 *
	 */
	public function remove($key) {
		return parent::remove($key);
	}

	/**
	 * Remove all cookies (other than those required for current session)
	 *
	 * @return $this|WireInputData
	 *
	 */
	public function removeAll() {
		foreach($this as $key => $value) {
			$this->offsetUnset($key);
		}
		return $this;
	}

	/**
	 * Set a cookie with options and return success state
	 * 
	 * This is the same as the `set()` mehod except for the following: 
	 * 
	 *  - It returns a boolean (success state) rather than reference to $this.
	 *  - An $options array argument is required.
	 *  - It does not accept a max age in place of $options argument. 
	 *
	 * #pw-internal
	 * 
	 * @param string $key Name of cookie to set
	 * @param string|array|int|float $value Value to place in cookie
	 * @param array $options Optionally override options from $config->cookieOptions and any previously set from an options() call:
	 * - `age` (int): Max age of cookies in seconds or 0 to expire with session. For example: 3600=1 hour, 86400=1 day,
	 *    604800=1 week, 2592000=30 days, etc. Note that the default may be overridden by `$config->cookieOptions` setting from
	 *    your /site/config.php file. (default=86400)
	 * - `expire` (int|string): If you prefer to use an expiration date rather than the `age` option, specify a unix timestamp (int), 
	 *    ISO-8601 date string, or any date string recognized by PHP’s strtotime(), like "2020-11-03" or +1 WEEK", etc. (default=null).
	 *    Please note the expire option was added in 3.0.159, previous versions should use the `age` option only. 
	 * - `path` (string|null): Cookie path/URL or null for PW installation’s root URL. (default=null)
	 * - `secure` (bool|null): Transmit cookies only over secure HTTPS connection? Specify true or false, or use null to auto-detect, 
	 *    which uses true for cookies set when HTTPS is detected. (default=null)
	 * - `samesite` (string): SameSite value, one of 'Lax' (default), 'Strict' or 'None'. (default='Lax') 3.0.178+
	 * - `httponly` (bool): When true, cookie is visible to PHP/ProcessWire only and not visible to client-side JS code. (default=false)
	 * - `fallback` (bool): If set cookie fails (perhaps due to output already sent), attempt to set at beginning of next request? (default=true)
	 * - `domain` (string|bool|null): Cookie domain, specify one of the following: `null` or blank string for current hostname [default],
	 *    boolean `true` for all subdomains of current domain, `domain.com` for domain.com and *.domain.com [same as true], `www.domain.com` 
	 *    for www subdomain and and hostnames off of it, like dev.www.domain.com. (default=null)
	 * @return bool Returns true on success or false if cookie could not be set in this request and has been queued for next request
	 * @since 3.0.159
	 *
	 */
	public function setCookie($key, $value, array $options) {
		
		$config = $this->wire()->config;
		$options = array_merge($this->defaultOptions, $config->cookieOptions, $this->options, $options);
	
		$path = $options['path'] === null || $options['path'] === true ? $config->urls->root : $options['path'];
		$secure = $options['secure'] === null ? (bool) $config->https : (bool) $options['secure'];
		$httponly = (bool) $options['httponly'];
		$domain = $options['domain'];
		$remove = $value === null;
		$expires = null;
		$samesite = $options['samesite'] ? ucfirst(strtolower($options['samesite'])) : 'Lax';
		
		if($samesite === 'None') {
			$secure = true;
		} else if(!in_array($samesite, array('Lax', 'Strict', 'None'), true)) {
			$samesite = 'Lax';
		}
		
		if(!empty($options['expire'])) {
			if(is_string($options['expire']) && !ctype_digit($options['expire'])) {
				$expires = strtotime($options['expire']);
			} else {
				$expires = (int) $options['expire'];
			}
		}
		
		if(empty($expires)) {
			$expires = $options['age'] ? time() + (int) $options['age'] : 0;
		}
		
		if(!$this->allowSetCookie($key)) return false;

		// determine what to use for the domain argument
		if($domain === null) {
			// use current/origin http host only
			// http://www.faqs.org/rfcs/rfc6265.html - 4.1.2.3.
			// “If the server omits the Domain attribute, the user agent will return the cookie only to the origin server.”
			$domain = '';
		} else if($domain === true) {
			// allow all subdomains off current domain
			$parts = explode('.', $config->httpHost);
			$domain = count($parts) > 1 ? implode('.', array_slice($parts, -2)) : $config->httpHost;
		}

		// remove port from domain, as it is not compatible with setcookie()
		if(strpos($domain, ':') !== false) list($domain,) = explode(':', $domain, 2);

		// check if cookie should be deleted
		if($remove) list($value, $expires) = array('', 1); 

		// set the cookie
		if(PHP_VERSION_ID < 70300) {
			$result = setcookie($key, $value, $expires, "$path; SameSite=$samesite", $domain, $secure, $httponly);
		} else {
			$result = setcookie($key, $value, array(
				'expires' => $expires,
				'path' => $path,
				'domain' => $domain,
				'secure' => $secure,
				'httponly' => $httponly,
				'samesite' => $samesite,
			));
		}

		if($result === false && $options['fallback']) {
			// output must have already started, set at construct on next request
			$this->wire()->session->setFor($this, $key, $value);
		}

		if($remove) {
			parent::offsetUnset($key);
			unset($_COOKIE[$key]); 
		} else {
			parent::__set($key, $value);
			$_COOKIE[$key] = $value;
		}
		
		return $result;
	}
	
	/**
	 * Unset a cookie value
	 * 
	 * #pw-internal
	 * 
	 * @param mixed $key
	 * 
	 */
	#[\ReturnTypeWillChange] 
	public function offsetUnset($key) {
		if(!$this->allowSetCookie($key)) return;
		parent::offsetUnset($key);
		$this->setCookie($key, null, array());
		unset($_COOKIE[$key]);
	}

	/**
	 * Allow cookie with given name to be set or unset?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	protected function allowSetCookie($name) {
		if(empty($this->skipCookies)) $this->skipCookies = $this->wire()->session->getCookieNames();
		return in_array($name, $this->skipCookies) ? false : true;
	}
}
