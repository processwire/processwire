<?php namespace ProcessWire;

/**
 * WireInputDataCookie class represents the $input->cookie API variable
 * 
 * #pw-summary Enables setting and getting cookies from the ProcessWire API using $input->cookie.
 * 
 * #pw-body =
 * 
 * - Whether getting or setting, cookie values are always strings.
 * - Values retrieved from `$input->cookie` are user input (like PHP’s $_COOKIE) and should be sanitized and validated.
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
 * $input->cookie->set('foo', bar', 86400); // live for 1 day
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
 *   // 3600=1hr, 86400=1day, 604800=1week, 2592000=30days, etc.
 *   'age' => 604800,
 * 
 *   // Cookie path/URL or null for PW installation’s root URL 
 *   'path' => null,
 * 
 *   // Cookie domain: null for current hostname, true for all subdomains of current domain, 
 *   // domain.com for domain and all subdomains, www.domain.com for www subdomain.
 *   'domain' => null,
 * 
 *   // Transmit cookies only over secure HTTPS connection? 
 *   // Specify true, false, or null to auto-detect (uses true for cookies set when HTTPS).
 *   'secure' => null,
 * 
 *   // Make cookies accessible by HTTP only? 
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
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
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
		'path' => null,
		'domain' => null,
		'secure' => null,
		'httponly' => false, 
		'fallback' => true,
	);

	/**
	 * Cookie options specifically set at runtime
	 * @var array
	 * 
	 */
	protected $options = array();

	/**
	 * Cookie names not be allowed to be removed
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
		/** @var Session $session */
		$session = $this->wire('session');
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
	 * Set a cookie (optionally with options)
	 *
	 * @param string $key Cookie name
	 * @param string $value Cookie value
	 * @param array|int|string $options Optionally specify max age in seconds (int) or array with any of the following options:
	 * - `age` (int): Max age of cookies in seconds or 0 to expire with session (3600=1hr, 86400=1day, 604800=1week, 2592000=30days, etc.)
	 * - `path` (string|null): Cookie path/URL or null for PW installation’s root URL.
	 * - `domain` (string|bool|null): Cookie domain: null for current hostname, true for all subdomains of current domain, domain.com for domain and all subdomains, www.domain.com for www subdomain.
	 * - `secure` (bool|null): Transmit cookies only over secure HTTPS connection? (true, false, or null to auto-detect, substituting true for cookies set when HTTPS is active).
	 * - `httponly` (bool): When true, cookie is http/server-side only and not visible to client-side JS code.
	 * - `fallback` (bool): If set cookie fails (perhaps due to output already sent), attempt to set at beginning of next request?
	 * @return $this
	 * @since 3.0.141 
	 *
	 */
	public function set($key, $value, $options = array()) {
		
		if(!$this->init) {
			parent::__set($key, $value);
			return $this;
		}
		
		if(!is_array($options) && ctype_digit("$options")) {
			$age = (int) $options;
			$options = array('age' => $age); 
		}
		
		$this->setCookie($key, $value, $options);
		
		return $this;
	}

	/**
	 * Set a cookie (internal)
	 * 
	 * @param string $key
	 * @param string|array|int|float $value
	 * @param array $options Optionally override options from $config->cookieOptions and any previously set from an options() call:
	 * - `age` (int): Max age of cookies in seconds or 0 to expire with session (3600=1hr, 86400=1day, 604800=1week, 2592000=30days, etc.)
	 * - `path` (string|null): Cookie path/URL or null for PW installation’s root URL.
	 * - `domain` (string|bool|null): Cookie domain: null for current hostname, true for all subdomains of current domain, domain.com for domain and all subdomains, www.domain.com for www subdomain.
	 * - `secure` (bool|null): Transmit cookies only over secure HTTPS connection? (true, false, or null to auto-detect, substituting true for cookies set when HTTPS is active).
	 * - `httponly` (bool): When true, cookie is http/server-side only and not visible to client-side JS code.
	 * - `fallback` (bool): If set cookie fails (perhaps due to output already sent), attempt to set at beginning of next request? 
	 * @return bool
	 *
	 */
	protected function setCookie($key, $value, array $options) {
		
		/** @var Config $config */
		$config = $this->wire('config');
		$options = array_merge($this->defaultOptions, $config->cookieOptions, $this->options, $options);
		
		$expires = $options['age'] ? time() + (int) $options['age'] : 0;
		$path = $options['path'] === null || $options['path'] === true ? $config->urls->root : $options['path'];
		$secure = $options['secure'] === null ? (bool) $config->https : (bool) $options['secure'];
		$httponly = (bool) $options['httponly'];
		$domain = $options['domain'];
		$remove = $value === null;
		
		if(!$this->allowSetCookie($key)) return false;

		// determine what to use for the domain argument
		if($domain === null) {
			// use current http host
			$domain = $config->httpHost;
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
		$result = setcookie($key, $value, $expires, $path, $domain, $secure, $httponly);

		if($result === false && $options['fallback']) {
			// output must have already started, set at construct on next request
			$this->wire('session')->setFor($this, $key, $value);
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
		if(empty($this->skipCookies)) $this->skipCookies = $this->wire('session')->getCookieNames();
		return in_array($name, $this->skipCookies) ? false : true;
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

}


