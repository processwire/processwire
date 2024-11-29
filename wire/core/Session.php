<?php namespace ProcessWire;

/**
 * ProcessWire Session
 *
 * Start a session with login/logout capability 
 * 
 * #pw-summary Maintains sessions in ProcessWire, authentication, persistent variables, notices and redirects.
 * #pw-order-groups redirects,get,set,remove,info,notices,authentication,advanced,hooker
 *
 * This should be used instead of the $_SESSION superglobal, though the $_SESSION superglobal can still be 
 * used, but it's in a different namespace than this. A value set in $_SESSION won't appear in $session
 * and likewise a value set in $session won't appear in $_SESSION.  It's also good to use this class
 * over the $_SESSION superglobal just in case we ever need to replace PHP's session handling in the future.
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 * @see https://processwire.com/api/ref/session/ Session documentation
 *
 * @method User login() login($name, $pass, $force = false) Login the user identified by $name and authenticated by $pass. Returns the user object on successful login or null on failure.
 * @method Session logout() logout() Logout the current user, and clear all session variables.
 * @method void redirect() redirect($url, $http301 = true) Redirect this session to the specified URL. 
 * @method void init() Initialize session (called automatically by constructor) #pw-hooker
 * @method bool authenticate(User $user, $pass) #pw-hooker
 * @method bool isValidSession($userID) #pw-hooker
 * @method bool allowLoginAttempt($name) #pw-hooker
 * @method bool allowLogin($name, User $user = null) #pw-hooker
 * @method void loginSuccess(User $user) #pw-hooker
 * @method void loginFailure($name, $reason) #pw-hooker
 * @method void logoutSuccess(User $user) #pw-hooker
 * 
 * @property SessionCSRF $CSRF 
 *
 * Expected $config variables include: 
 * ===================================
 * string $config->sessionName Name of session on http
 * string $config->sessionNameSecure Name of session on https
 * int $config->sessionExpireSeconds Number of seconds of inactivity before session expires
 * bool $config->sessionChallenge True if a separate challenge cookie should be used for validating sessions
 * bool $config->sessionFingerprint True if a fingerprint should be kept of the user's IP & user agent to validate sessions
 * bool $config->sessionCookieSecure Use secure cookies or session? (default=true)
 * 
 * @todo enable login/forceLogin to recognize non-HTTP use of login, when no session needs to be maintained
 * @todo add a default $config->apiUser to be used when non-HTTP/bootstrap usage
 *
 */

class Session extends Wire implements \IteratorAggregate {

	/**
	 * Fingerprint bitmask: Use remote addr (recommended)
	 * 
	 */
	const fingerprintRemoteAddr = 2;
	
	/**
	 * Fingerprint bitmask: Use client provided addr
	 *
	 */
	const fingerprintClientAddr = 4;
	
	/**
	 * Fingerprint bitmask: Use user agent (recommended)
	 *
	 */
	const fingerprintUseragent = 8;
	
	/**
	 * Fingerprint bitmask: Use “accept” content-types header
	 *
	 * @since 3.0.159
	 *
	 */
	const fingerprintAccept = 16;

	/**
	 * Suffix applied to challenge cookies
	 * 
	 * @since 3.0.141
	 * 
	 */
	const challengeSuffix = '_challenge';

	/**
	 * Reference to ProcessWire $config object
	 *
 	 * For convenience, since our __get() does not reference the Fuel, unlike other Wire derived classes.
	 *
	 */
	protected $config; 

	/**
	 * Instance of the SessionCSRF protection class, instantiated when requested from $session->CSRF.
	 *
	 */
	protected $CSRF = null;

	/**
	 * Set to true when maintenance should be skipped
	 * 
	 * @var bool
	 * 
	 */
	protected $skipMaintenance = false;

	/**
	 * Has the Session::init() method been called?
	 * 
	 * @var bool
	 * 
	 */
	protected $sessionInit = false;

	/**
	 * Are sessions allowed?
	 * 
	 * @var bool|null
	 * 
	 */
	protected $sessionAllow = null;

	/**
	 * Instance of custom session handler module, when in use (null when not)
	 * 
	 * @var WireSessionHandler|null
	 * 
	 */
	protected $sessionHandler = null;

	/**
	 * Name of key/index within $_SESSION where PW keeps its session data
	 * 
	 * @var string
	 * 
	 */
	protected $sessionKey = '';

	/**
	 * Data storage when no session initialized 
	 * 
	 * @var array
	 * 
	 */
	protected $data = array();

	/**
	 * True if there is an external session provider
	 * 
	 * @var bool
	 * 
	 */
	protected $isExternal = false;

	/**
	 * True if this is a secondary instance of ProcessWire
	 * 
	 * @var bool
	 * 
	 */
	protected $isSecondary = false;

	/**
	 * Start the session and set the current User if a session is active
	 *
	 * Assumes that you have already performed all session-specific ini_set() and session_name() calls 
	 * 
	 * @param ProcessWire $wire
	 * @throws WireException
	 *
	 */
	public function __construct(ProcessWire $wire) {
		
		parent::__construct();
		$wire->wire($this);
		
		$users = $wire->wire()->users;
		$this->config = $wire->wire()->config;
		$this->sessionKey = $this->className();
		
		$instanceID = $wire->getProcessWireInstanceID();
		if($instanceID) {
			$this->isSecondary = true; 
			$this->sessionKey .= $instanceID;
		}

		$user = null;
		$sessionAllow = $this->config->sessionAllow;
		
		if(is_null($sessionAllow)) {
			$sessionAllow = true;
		} else if(is_bool($sessionAllow)) {
			// okay, keep as-is
		} else if(is_callable($sessionAllow)) {
			// call function that returns boolean
			$sessionAllow = call_user_func_array($sessionAllow, array($this));
			if(!is_bool($sessionAllow)) throw new WireException("\$config->sessionAllow callable must return boolean");
		} else {
			$sessionAllow = true;
		}
		
		$this->sessionAllow = $sessionAllow;
		
		if($sessionAllow) {
			$this->init();
			if(empty($_SESSION[$this->sessionKey])) $_SESSION[$this->sessionKey] = array();
			$userID = $this->get('_user', 'id');
			if($userID) {
				if($this->isValidSession($userID)) {
					$user = $users->get($userID);
				} else {
					$this->logout();
				}
			}
		}

		if(!$user || !$user->id) $user = $users->getGuestUser();
		$users->setCurrentUser($user); 	

		if($sessionAllow) $this->wakeupNotices();
		$this->setTrackChanges(true);
	}

	/**
	 * Are session cookie(s) present?
	 * 
	 * #pw-group-info
	 * 
	 * @param bool $checkLogin Specify true to check instead for challenge cookie (which indicates login may be active). 
	 * @return bool Returns true if session cookie present, false if not. 
	 * 
	 */
	public function hasCookie($checkLogin = false) {
		if($this->config->https && $this->config->sessionCookieSecure) {
			$name = $this->config->sessionNameSecure;
			if(!$name) $name = $this->config->sessionName . 's';
		} else {
			$name = $this->config->sessionName;
		}
		if($checkLogin) $name .= self::challengeSuffix;
		return !empty($_COOKIE[$name]);
	}

	/**
	 * Is a session login cookie present?
	 * 
	 * This only indicates the user was likely logged in at some point, and may not indicate an active login. 
	 * This method is more self describing version of `$session->hasCookie(true);`
	 * 
	 * #pw-group-info
	 * 
	 * @return bool
	 * @since 3.0.175
	 * 
	 */
	public function hasLoginCookie() {
		return $this->hasCookie(true);
	}

	/**
	 * Start the session
	 *
	 * Provided here in any case anything wants to hook in before session_start()
	 * is called to provide an alternate save handler.
	 * 
	 * #pw-hooker
	 * 
	 */
	public function ___init() {

		if($this->sessionInit || !$this->sessionAllow) return;
		if(!$this->config->sessionName) return;
		$this->sessionInit = true;

		if(function_exists("\\session_status")) {
			// abort session init if there is already a session active	
			// note: there is no session_status() function prior to PHP 5.4
			if(session_status() === PHP_SESSION_ACTIVE) {
				// use a more unique sessionKey when there is an external session provider
				$this->isExternal = true; 
				$this->sessionKey = str_replace($this->className(), 'ProcessWire', $this->sessionKey);
				return;
			}
		}

		if($this->config->https && $this->config->sessionCookieSecure) {
			ini_set('session.cookie_secure', 1); // #1264
			if($this->config->sessionNameSecure) {
				session_name($this->config->sessionNameSecure);
			} else {
				session_name($this->config->sessionName . 's');
			}
		} else {
			session_name($this->config->sessionName);
		}
		
		ini_set('session.use_cookies', true);
		ini_set('session.use_only_cookies', 1);
		ini_set('session.cookie_httponly', 1);
		ini_set('session.gc_maxlifetime', $this->config->sessionExpireSeconds);
		
		if($this->config->sessionCookieDomain) {
			ini_set('session.cookie_domain', $this->config->sessionCookieDomain);
		}

		if(ini_get('session.save_handler') == 'files') {
			if(ini_get('session.gc_probability') == 0) {
				// Some debian distros replace PHP's gc without fully implementing it,
				// which results in broken garbage collection if the save_path is set. 
				// As a result, we avoid setting the save_path when this is detected. 
			} else {
				ini_set("session.save_path", rtrim($this->config->paths->sessions, '/'));
			}
		} else {
			if(!ini_get('session.gc_probability')) ini_set('session.gc_probability', 1);
			if(!ini_get('session.gc_divisor')) ini_set('session.gc_divisor', 100);
		}
		
		$options = array();
		$cookieSameSite = $this->sessionCookieSameSite();
		
		if(PHP_VERSION_ID < 70300) {
			$cookiePath = ini_get('session.cookie_path');
			if(empty($cookiePath)) $cookiePath = '/';
			$options['cookie_path'] = "$cookiePath; SameSite=$cookieSameSite";
		} else {
			$options['cookie_samesite'] = $cookieSameSite;
		}

		@session_start($options);
		
		if(!empty($this->data)) {
			foreach($this->data as $key => $value) $this->set($key, $value);
		}
	}

	/**
	 * Checks if the session is valid based on a challenge cookie and fingerprint
	 *
	 * These items may be disabled at the config level, in which case this method always returns true
	 * 
	 * #pw-hooker
 	 *
	 * @param int $userID
	 * @return bool
	 *
	 */
	protected function ___isValidSession($userID) {

		$valid = true; 
		$reason = '';
		$sessionName = session_name();

		// check challenge cookie
		if($this->config->sessionChallenge) {
			$cookieName = $sessionName . self::challengeSuffix;
			if(empty($_COOKIE[$cookieName]) || ($this->get('_user', 'challenge') != $_COOKIE[$cookieName])) {
				$valid = false; 
				$reason = "Error: Invalid challenge value";
			}
		}	

		// check fingerprint
		if(!$this->isValidFingerprint()) {
			$reason = "Error: Session fingerprint changed (IP address or useragent)";
			$valid = false;
		}
	
		// check session expiration
		if($this->config->sessionExpireSeconds) {
			$ts = (int) $this->get('_user', 'ts');
			if($ts < (time() - $this->config->sessionExpireSeconds)) {
				// session time expired
				$valid = false;
				$this->error($this->_('Session timed out'));
				$reason = "Session timed out (session older than {$this->config->sessionExpireSeconds} seconds)";
			}
		}

		if($valid) {
			// if valid, update last request time
			$this->set('_user', 'ts', time());
			
		} else if($reason && $userID && $userID != $this->config->guestUserPageID) {
			// otherwise log the invalid session
			$user = $this->wire()->users->get((int) $userID);
			if($user && $user->id) $reason = "User '$user->name' - $reason";
			$reason .= " (IP: " . $this->getIP() . ")";
			$this->log($reason);
		}

		return $valid; 
	}

	/**
	 * Returns whether or not the current session fingerprint is valid
	 * 
	 * @return bool
	 * 
	 */
	protected function isValidFingerprint() {
		$fingerprint = $this->getFingerprint();
		if($fingerprint === false) return true; // fingerprints off
		if($fingerprint !== $this->get('_user', 'fingerprint')) return false;
		return true; 
	}

	/**
	 * Generate a session fingerprint
	 *
	 * If the `$mode` argument is omitted, the mode is pulled from `$config->sessionFingerprint`. 
	 * If using the mode argument, specify one of the following:
	 * 
	 * - 2: Remote IP
	 * - 4: Forwarded/client IP (can be spoofed)
	 * - 8: Useragent
	 * - 16: Accept header
	 *
	 * To use the custom `$mode` settings above, select one or more of those you want
	 * to fingerprint, note the numbers, and determine the `$mode` like this:
	 * ~~~~~~
	 * // to fingerprint just remote IP
	 * $mode = 2;
	 *
	 * // to fingerprint remote IP and useragent:
	 * $mode = 2 | 8;
	 *
	 * // to fingerprint remote IP, useragent and accept header:
	 * $mode = 2 | 8 | 16;
	 * ~~~~~~
	 * If using fingerprint in an environment where the user’s IP address may
	 * change during the session, you should fingerprint only the useragent
	 * and/or accept header, or disable fingerprinting.
	 *
	 * If using fingerprint with an AWS load balancer, you should use one of
	 * the options that uses the “client IP” rather than the “remote IP”,
	 * fingerprint only useragent and/or accept header, or disable fingerprinting.
	 * 
	 * #pw-internal
	 * 
	 * @param int|bool|null $mode Optionally specify fingerprint mode (default=$config->sessionFingerprint)
	 * @param bool $debug Return non-hashed fingerprint for debugging purposes? (default=false)
	 * @return bool|string Returns false if fingerprints not enabled. Returns string if enabled.
	 * 
	 */
	public function getFingerprint($mode = null, $debug = false) {
	
		$debugInfo = array();
		$useFingerprint = $mode === null ? $this->config->sessionFingerprint : $mode;
		
		if(!$useFingerprint) return false;
		
		if($useFingerprint === true || $useFingerprint === 1 || "$useFingerprint" === "1") {
			// default (boolean true or int 1)
			$useFingerprint = self::fingerprintRemoteAddr | self::fingerprintUseragent;
			if($debug) $debugInfo[] = 'default';
		}

		$fingerprint = '';
		
		if($useFingerprint & self::fingerprintRemoteAddr) {
			$fingerprint .= $this->getIP(true);
			if($debug) $debugInfo[] = 'remote-addr';
		}
		
		if($useFingerprint & self::fingerprintClientAddr) {
			$fingerprint .= $this->getIP(false, 2);
			if($debug) $debugInfo[] = 'client-addr';
		}
		
		if($useFingerprint & self::fingerprintUseragent) {
			$fingerprint .= isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
			if($debug) $debugInfo[] = 'useragent';
		}
		
		if($useFingerprint & self::fingerprintAccept) {
			$fingerprint .= isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
			if($debug) $debugInfo[] = 'accept';
		}
		
		if($debug) {
			$fingerprint = implode(',', $debugInfo) . ': ' . $fingerprint;
		} else {
			$fingerprint = md5($fingerprint);
		}
		
		return $fingerprint;
	}

	/**
	 * Get a session variable
	 * 
	 * - This method returns the value of the requested session variable, or NULL if it's not present. 
	 * - You can optionally use a namespace with this method, to avoid collisions with other session variables. 
	 *   But if using namespaces we recommended using the dedicated getFor() and setFor() methods instead. 
	 * - You can also get or set non-namespaced session values directly (see examples). 
	 * 
	 * ~~~~~
	 * // Set value "Bob" to session variable named "firstName"
	 * $session->set('firstName', 'Bob'); 
	 * 
	 * // You can retrieve the firstName now, or any later request
	 * $firstName = $session->get('firstName');
	 * 
	 * // outputs: Hello Bob
	 * echo "Hello $firstName"; 
	 * ~~~~~
	 * ~~~~~
	 * // Setting and getting a session value directly
	 * $session->firstName = 'Bob';
	 * $firstName = $session->firstName;
	 * ~~~~~
	 * 
	 * #pw-group-get
	 * 
	 * @param string|object $key Name of session variable to retrieve (or object if using namespaces)
	 * @param string $_key Name of session variable to get if first argument is namespace, omit otherwise.
	 * @return mixed Returns value of seession variable, or NULL if not found. 
	 *
	 */
	public function get($key, $_key = null) {
		if($key === 'CSRF') {
			return $this->CSRF();
		} else if($_key !== null) {
			// namespace
			return $this->getFor($key, $_key);
		}
		if($this->sessionInit) {
			$value = isset($_SESSION[$this->sessionKey][$key]) ? $_SESSION[$this->sessionKey][$key] : null;
		} else {
			if($key === 'config') return $this->config;
			$value = isset($this->data[$key]) ? $this->data[$key] : null;
		}
	
		/*
		if(is_null($value) && is_null($_key) && strpos($key, '_user_') === 0) {
			// for backwards compatiblity with non-core modules or templates that may be checking _user_[property]
			// not currently aware of any instances, but this is just a precaution
			return $this->get('_user', str_replace('_user_', '', $key)); 
		}
		*/
		
		return $value; 
	}

	/**
	 * Get a session variable or return $val argument if session value not present
	 * 
	 * This is the same as get() except that it lets you specify a fallback return value in the method call. 
	 * For a namespace version use `Session::getValFor()` instead. 
	 * 
	 * #pw-group-get
	 * 
	 * @param string $key Name of session variable to retrieve.
	 * @param mixed $val Fallback value to return if session does not have it.
	 * @return mixed Returns value of seession variable, or NULL if not found. 
	 * @since 3.0.133
	 * 
	 */
	public function getVal($key, $val = null) {
		$value = $this->get($key);
		if($value === null) $value = $val;
		return $value;
	}

	/**
	 * Get all session variables in an associative array
	 * 
	 * #pw-group-get
 	 *
	 * @param object|string $ns Optional namespace
	 * @return array
	 *
	 */
	public function getAll($ns = null) {
		if(!is_null($ns)) return $this->getFor($ns, '');
		if($this->sessionInit) {
			return $_SESSION[$this->sessionKey];
		} else {
			return $this->data;
		}
	}

	/**
	 * Get all session variables for given namespace and return associative array
	 * 
	 * #pw-group-get
	 * 
	 * @param string|Wire $ns
	 * @return array
	 * @since 3.0.141 Method added for consistency, but any version can do this with $session->getFor($ns, '');
	 * 
	 */
	public function getAllFor($ns) {
		return $this->getFor($ns, '');
	}

	/**
	 * Set a session variable
	 * 
	 * - You can optionally use a namespace with this method, to avoid collisions with other session variables.
	 *   But if using namespaces we recommended using the dedicated getFor() and setFor() methods instead.
	 * - You can also get or set non-namespaced session values directly (see examples).
	 *
	 * ~~~~~
	 * // Set value "Bob" to session variable named "firstName"
	 * $session->set('firstName', 'Bob');
	 *
	 * // You can retrieve the firstName now, or any later request
	 * $firstName = $session->get('firstName');
	 *
	 * // outputs: Hello Bob
	 * echo "Hello $firstName";
	 * ~~~~~
	 * ~~~~~
	 * // Setting and getting a session value directly
	 * $session->firstName = 'Bob';
	 * $firstName = $session->firstName;
	 * ~~~~~
	 * 
	 * #pw-group-set
	 * 
	 * @param string|object $key Name of session variable to set (or object for namespace)
	 * @param string|mixed $value Value to set (or name of variable, if first argument is namespace)
	 * @param mixed $_value Value to set if first argument is namespace. Omit otherwise.
 	 * @return $this
	 *
	 */
	public function set($key, $value, $_value = null) {
		if(!is_null($_value)) return $this->setFor($key, $value, $_value);
		$oldValue = $this->get($key); 
		if($value !== $oldValue) $this->trackChange($key, $oldValue, $value);
		if($this->sessionInit) {
			$_SESSION[$this->sessionKey][$key] = $value;
		} else {
			$this->data[$key] = $value;
		}
		return $this; 
	}

	/**
	 * Get a session variable within a given namespace
	 * 
	 * ~~~~~
	 * // Retrieve namespaced session value
	 * $firstName = $session->getFor($this, 'firstName'); 
	 * ~~~~~
	 * 
	 * #pw-group-get
	 *
	 * @param string|object $ns Namespace string or object
	 * @param string $key Specify variable name to retrieve, or blank string to return all variables in the namespace.
	 * @return mixed
	 *
	 */
	public function getFor($ns, $key) {
		$ns = $this->getNamespace($ns); 
		$data = $this->get($ns); 
		if(!is_array($data)) $data = array();
		if($key === '') return $data;
		return isset($data[$key]) ? $data[$key] : null;
	}
	
	/**
	 * Get a session variable or return $val argument if session value not present
	 *
	 * This is the same as get() except that it lets you specify a fallback return value in the method call.
	 * For a namespace version use `Session::getValFor()` instead.
	 * 
	 * #pw-group-get
	 *
	 * @param string|object $ns Namespace string or object
	 * @param string $key Specify variable name to retrieve
	 * @param mixed $val Fallback value if session does not have one
	 * @return mixed
	 * @since 3.0.133
	 *
	 */
	public function getValFor($ns, $key, $val = null) {
		$value = $this->getFor($ns, $key);
		if($value === null) $value = $val;
		return $value;
	}

	/**
	 * Set a session variable within a given namespace
	 * 
	 * To remove a namespace, use `$session->remove($namespace)`.
	 * 
	 * ~~~~~
	 * // Set a session value for a namespace
	 * $session->setFor($this, 'firstName', 'Bob'); 
	 * ~~~~~
	 * 
	 * #pw-group-set
	 *
	 * @param string|object $ns Namespace string or object.
	 * @param string $key Name of session variable you want to set.
	 * @param mixed $value Value you want to set, or specify null to unset.
	 * @return $this
	 *
	 */
	public function setFor($ns, $key, $value) {
		$ns = $this->getNamespace($ns); 
		$data = $this->get($ns); 
		if(!is_array($data)) $data = array();
		if(is_null($value)) {
			unset($data[$key]);
		} else {
			$data[$key] = $value;
		}
		return $this->set($ns, $data); 
	}

	/**
	 * Unset a session variable
	 * 
	 * ~~~~~
	 * // Unset a session var
	 * $session->remove('firstName'); 
	 * 
	 * // Unset a session var in a namespace
	 * $session->remove($this, 'firstName');
	 * 
	 * // Unset all session vars in a namespace
	 * $session->remove($this, true); 
	 * ~~~~~
	 * 
	 * #pw-group-remove
	 *
 	 * @param string|object $key Name of session variable you want to remove (or namespace string/object)
	 * @param string|bool|null $_key Omit this argument unless first argument is a namespace. Otherwise specify one of: 
	 *  - If first argument is namespace and you want to remove a property from the namespace, provide key here. 
	 * 	- If first argument is namespace and you want to remove all properties from the namespace, provide boolean TRUE. 
	 * @return $this
	 *
	 */
	public function remove($key, $_key = null) {
		if($this->sessionInit) {
			if(is_null($_key)) {
				unset($_SESSION[$this->sessionKey][$key]);
			} else if(is_bool($_key)) {
				unset($_SESSION[$this->sessionKey][$this->getNamespace($key)]);
			} else {
				unset($_SESSION[$this->sessionKey][$this->getNamespace($key)][$_key]);
			}
		} else {
			if(is_null($_key)) {
				unset($this->data[$key]);
			} else if(is_bool($_key)) {
				unset($this->data[$this->getNamespace($key)]);
			} else {
				unset($this->data[$this->getNamespace($key)][$_key]);
			}
		}
		return $this; 
	}

	/**
	 * Unset a session variable within a namespace
	 * 
	 * #pw-group-remove
	 * 
	 * @param string|object $ns Namespace
	 * @param string $key Provide name of variable to remove, or boolean true to remove all in namespace. 
	 * @return $this
	 * 
	 */
	public function removeFor($ns, $key) {
		return $this->remove($ns, $key);
	}

	/**
	 * Remove all session variables in given namespace
	 * 
	 * #pw-group-remove
	 * 
	 * @param string|object $ns
	 * @return $this
	 * 
	 */
	public function removeAllFor($ns) {
		$this->remove($ns, true); 
		return $this;
	}

	/**
	 * Given a namespace object or string, return the namespace string
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param object|string $ns
	 * @return string
	 * @throws WireException if given invalid namespace type
	 *
	 */
	protected function getNamespace($ns) {
		if(is_object($ns)) {
			if($ns instanceof Wire) $ns = $ns->className();
				else $ns = wireClassName($ns, false);
		} else if(is_string($ns)) {
			// good
		} else {
			throw new WireException("Session namespace must be string or object"); 
		}
		return $ns; 
	}

	/**
	 * Provide non-namespaced $session->variable get access
	 * 
	 * @param string $name
	 * @return SessionCSRF|mixed|null
	 *
	 */
	public function __get($name) {
		return $this->get($name); 
	}

	/**
	 * Provide non-namespaced $session->variable = variable set access
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 *
	 */
	public function __set($key, $value) {
		return $this->set($key, $value); 
	}

	/**
	 * Allow iteration of session variables
	 * 
	 * ~~~~~
	 * foreach($session as $key => $value) {
     *    echo "<li>$key: $value</li>";	
	 * } 
	 * ~~~~~
	 * 
	 * #pw-internal
	 * 
	 * @return \ArrayObject
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function getIterator() {
		$data = $this->sessionInit ? $_SESSION[$this->sessionKey] : $this->data;
		return new \ArrayObject($data); 
	}

	/**
	 * Get the IP address of the current user
	 *
	 * ~~~~~
	 * $ip = $session->getIP();
	 * echo $ip; // outputs 111.222.333.444
	 * ~~~~~
	 * 
	 * #pw-group-info
	 *
	 * @param bool $int Return as a long integer? (default=false)
	 *  - IPv6 addresses cannot be represented as an integer, so please note that using this int option makes it return a CRC32
	 *    integer when using IPv6 addresses (3.0.184+).
	 * @param bool|int $useClient Give preference to client headers for IP? HTTP_CLIENT_IP and HTTP_X_FORWARDED_FOR (default=false)
	 * 	- Specify integer 2 to include potential multiple CSV separated IPs (when provided by client).
	 * @return string|int Returns string by default, or integer if $int argument indicates to.
	 *
	 */
	public function getIP($int = false, $useClient = false) {

		$ip = $this->config->sessionForceIP;
		$ipv6 = false;

		if(!empty($ip)) {
			// use IP address specified in $config->sessionForceIP and disregard other options
			$useClient = false;

		} else if(empty($_SERVER['REMOTE_ADDR'])) {
			// when accessing via CLI Interface, $_SERVER['REMOTE_ADDR'] isn't set and trying to get it, throws a php-notice
			$ip = '127.0.0.1';

		} else if($useClient) {
			if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			// It's possible for X_FORWARDED_FOR to have more than one CSV separated IP address, per @tuomassalo
			if(strpos($ip, ',') !== false && $useClient !== 2) {
				list($ip) = explode(',', $ip);
			}
			// sanitize: if IP contains something other than digits, periods, commas, spaces, 
			// then don't use it and instead fallback to the REMOTE_ADDR. 
			$test = str_replace(array('.', ',', ' '), '', $ip);
			if(!ctype_digit("$test")) {
				if(strpos($test, ':') !== false) {
					// ipv6 allowed
					$test = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
					$ip = $test === false ? $_SERVER['REMOTE_ADDR'] : $test;
				} else {
					$ip = $_SERVER['REMOTE_ADDR'];
				}
			}

		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		if(strpos($ip, ':') !== false) {
			// attempt to identify an IPv4 version when an integer required for return value
			if($int && $ip === '::1') {
				$ip = '127.0.0.1';
			} else if($int && strpos($ip, '.') && preg_match('!(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})!', $ip, $m)) {
				$ip = $m[1]; // i.e. 0:0:0:0:0:ffff:192.1.56.10 => 192.1.56.10
			} else {
				$ipv6 = true;
			}
		}

		if($useClient === 2 && strpos($ip, ',') !== false) {
			// return multiple IPs
			$ips = array();
			foreach(explode(',', $ip) as $ip) {
				if($ipv6) {
					$ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
					if($ip !== false && $int) $ip = crc32($ip);
				} else {
					$ip = ip2long(trim($ip));
					if(!$int) $ip = long2ip($ip);
				}
				if($ip !== false) $ips[] = $ip;
			}
			$ip = implode(',', $ips);

		} else if($ipv6) {
			$ip = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
			if($ip === false) {
				$ip = $int ? 0 : '0.0.0.0';
			} else if($int) {
				$ip = crc32($ip);
			}

		} else {
			// sanitize by converting to and from integer
			$ip = ip2long(trim($ip));
			if(!$int) $ip = long2ip($ip);
		}

		return $ip;
	}
	
	/**
	 * Login a user with the given name and password
	 *
	 * Also sets them to the current user.
	 * 
	 * ~~~~~
	 * $u = $session->login('bob', 'laj3939$a');
	 * if($u) {
	 *   echo "Welcome Bob";
	 * } else {
	 *   echo "Sorry Bob";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-authentication
	 *
	 * @param string|User $name May be user name or User object.
	 * @param string $pass Raw, non-hashed password.
	 * @param bool $force Specify boolean true to login user without requiring a password ($pass argument can be blank, or anything).
	 * 	You can also use the `$session->forceLogin($user)` method to force a login without a password. 
	 * @return User|null Return the $user if the login was successful or null if not. 
	 * @throws WireException
	 *
	 */
	public function ___login($name, $pass, $force = false) {
	
		/** @var User|null $user */
		$user = null;
		$sanitizer = $this->wire()->sanitizer;
		$users = $this->wire()->users;
		$guestUserID = $this->wire()->config->guestUserPageID;

		$fail = true;
		$failReason = '';
		
		if($name instanceof User) {
			$user = $name;
			$name = $user->name;
		} else {
			$name = $sanitizer->pageNameUTF8($name);
		}
		
		if(!strlen($name)) return null;
		
		$allowAttempt = $this->allowLoginAttempt($name); 
		
		if($allowAttempt && is_null($user)) {
			$user = $users->get('name=' . $sanitizer->selectorValue($name));
		}
		
		if(!$allowAttempt) {
			$failReason = 'Blocked login attempt';

		} else if(!$user || !$user->id) {
			$failReason = 'Unknown user';
			
		} else if($user->id == $guestUserID) {
			$failReason = 'Guest user may not login';
			
		} else if(!$this->allowLogin($name, $user)) {
			$failReason = 'Login not allowed';
			
		} else if($force === true || $this->authenticate($user, $pass)) { 

			$this->trackChange('login', $this->wire()->user, $user); 
			session_regenerate_id(true);
			$this->set('_user', 'id', $user->id); 
			$this->set('_user', 'ts', time());

			if($this->config->sessionChallenge) {
				// create new challenge
				$rand = new WireRandom();
				$challenge = $rand->base64(32);
				$this->set('_user', 'challenge', $challenge); 
				$secure = $this->config->sessionCookieSecure ? (bool) $this->config->https : false;
				// set challenge cookie to last 30 days (should be longer than any session would feasibly last)
				$this->setCookie(
					session_name() . self::challengeSuffix,
					$challenge,
					time() + 60*60*24*30,
					'/',
					$this->config->sessionCookieDomain,
					$secure,
					true,
					$this->config->sessionCookieSameSite
				);
			}

			if($this->config->sessionFingerprint) { 
				// remember a fingerprint that tracks the user's IP and user agent
				$this->set('_user', 'fingerprint', $this->getFingerprint()); 
			}

			$this->wire('user', $user); 
			$this->CSRF()->resetAll();
			$this->loginSuccess($user); 
			$fail = false;

		} else {
			// authentication failed
			$failReason = 'Invalid password';
		}
		
		if($fail) {
			$this->loginFailure($name, $failReason);
			$user = null;
		}

		return $user; 
	}

	/**
	 * Login a user without requiring a password
	 * 
	 * ~~~~~
	 * // login bob without knowing his password
	 * $u = $session->forceLogin('bob'); 
	 * ~~~~~
	 * 
	 * #pw-group-authentication
	 * 
	 * @param string|User $user Username or User object
	 * @return User|null Returns User object on success, or null on failure
	 * 
	 */
	public function forceLogin($user) {
		return $this->login($user, '', true);
	}

	/**
	 * Login success method for hooks
	 * 
	 * #pw-hooker
	 *
	 * @param User $user
	 *
	 */
	protected function ___loginSuccess(User $user) { 
		$this->log("Successful login for '$user->name'"); 
	}

	/**
	 * Login failure method for hooks
	 * 
	 * #pw-hooker
	 * 
	 * @param string $name Attempted login name
	 * @param string $reason Reason for login failure
	 * 
	 */
	protected function ___loginFailure($name, $reason) { 
		$this->log("Error: Failed login for '$name' - $reason"); 
	}

	/**
	 * Allow the user $name to login? Provided for use by hooks. 
	 *
	 * #pw-hooker
	 * 
	 * @param string $name User login name
	 * @param User|null $user User object
	 * @return bool True if allowed to login, false if not (hooks may modify this)
	 *
	 */
	public function ___allowLogin($name, $user = null) {
		$allow = true; 
		if(!strlen($name)) return false;
		if(!$user instanceof User) {
			$sanitizer = $this->wire()->sanitizer;
			$name = $sanitizer->pageNameUTF8($name);
			$user = $this->wire()->users->get('name=' . $sanitizer->selectorValue($name));
		}
		if(!$user instanceof User || !$user->id) return false;
		if($user->isGuest() || $user->isUnpublished()) return false;
		$xroles = $this->config->loginDisabledRoles;
		if(!is_array($xroles) && !empty($xroles)) {
			$xroles = array($xroles);
		}
		if(is_array($xroles)) {
			foreach($xroles as $xrole) {
				if($user->hasRole($xrole)) {
					$allow = false;
					break;
				}
			}
		}
		return $allow; 
	}

	/**
	 * Allow login attempt for given name at all?
	 * 
	 * This method does nothing and is purely for hooks to modify return value. 
	 * 
	 * #pw-hooker
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function ___allowLoginAttempt($name) {
		return strlen($name) > 0;
	}

	/**
	 * Return true or false whether the user authenticated with the supplied password
	 * 
	 * #pw-hooker
	 *
	 * @param User $user User attempting to login
	 * @param string $pass Password they are attempting to login with
	 * @return bool
	 *
	 */
	public function ___authenticate(User $user, $pass) {
		return $user->pass->matches($pass);
	}

	/**
	 * Logout the current user, and clear all session variables
	 * 
	 * ~~~~~
	 * // logout user when "?logout=1" in URL query string
	 * if($input->get('logout')) {
	 *   $session->logout();
	 *	 // good to redirect somewhere else after a login or logout
	 *   $session->redirect('/'); 
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-authentication
	 *
	 * @param bool $startNew Start a new session after logout? (default=true)
	 * @return $this
	 * @throws WireException if session is disabled
	 *
	 */
	public function ___logout($startNew = true) {
		$sessionName = session_name();
		if($this->sessionInit) {
			if(!$this->isExternal && !$this->isSecondary) {
				$_SESSION = array();
			}
		} else {
			$this->data = array();
		}
		$this->removeCookies();
		$this->sessionInit = false;
		if($startNew) {
			session_destroy();
			session_name($sessionName);
			$this->init();
			session_regenerate_id(true);
			$_SESSION[$this->sessionKey] = array();
		}
		$user = $this->wire()->user;
		$users = $this->wire()->users;
		if($user) $this->logoutSuccess($user); 
		$guest = $users->getGuestUser();
		if($this->wire()->languages && "$user->language" != "$guest->language") {
			$guest->language = $user->language;
		}
		$users->setCurrentUser($guest);
		$this->trackChange('logout', $user, $guest); 
		return $this; 
	}

	/**
	 * Add a SetCookie response header
	 *
	 * @param string $name
	 * @param string|null|false $value
	 * @param int $expires
	 * @param string $path
	 * @param string|null $domain
	 * @param bool $secure
	 * @param bool $httponly
	 * @param string $samesite One of 'Strict', 'Lax', 'None'
	 * @return bool
	 * @since 3.0.178
	 * 
	 */
	protected function setCookie($name, $value, $expires = 0, $path = '/', $domain = null, $secure = false, $httponly = false, $samesite = 'Lax') {
		
		if(empty($path)) $path = '/';
	
		$samesite = $this->sessionCookieSameSite($samesite);
		
		if($samesite === 'None') $secure = true;

		if(PHP_VERSION_ID < 70300) {
			return setcookie($name, $value, $expires, "$path; SameSite=$samesite", $domain, $secure, $httponly);
		}

		// PHP 7.3+ supports $options array
		return setcookie($name, $value, array(
			'expires' => $expires,
			'path' => $path,
			'domain' => $domain,
			'secure' => $secure,
			'httponly' => $httponly,
			'samesite' => $samesite,
		));
	}


	/**
	 * Remove all cookies used by the session
	 * 
	 */
	protected function removeCookies() {
		$sessionName = session_name();
		$challengeName = $sessionName . self::challengeSuffix;
		$time = time() - 42000;
		$domain = $this->config->sessionCookieDomain;
		$secure = $this->config->sessionCookieSecure ? (bool) $this->config->https : false;
		$samesite = $this->sessionCookieSameSite();
		
		if(isset($_COOKIE[$sessionName])) {
			$this->setCookie($sessionName, '', $time, '/', $domain, $secure, true, $samesite);
		}
		
		if(isset($_COOKIE[$challengeName])) {
			$this->setCookie($challengeName, '', $time, '/', $domain, $secure, true, $samesite);
		}
	}

	/**
	 * Get 'SameSite' value for session cookie
	 * 
	 * @param string|null $value
	 * @return string
	 * @since 3.0.178
	 * 
	 */
	protected function sessionCookieSameSite($value = null) {
		$samesite = $value === null ? $this->config->sessionCookieSameSite : $value;
		$samesite = empty($samesite) ? 'Lax' : ucfirst(strtolower($samesite));
		if(!in_array($samesite, array('Strict', 'Lax', 'None'), true)) $samesite = 'Lax';
		return $samesite;
	}

	/**
	 * Get the names of all cookies managed by Session
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * @since 3.0.141
	 * 
	 */
	public function getCookieNames() {
		$name = $this->config->sessionName;
		$nameSecure = $this->config->sessionNameSecure;
		if(empty($nameSecure)) $nameSecure = $this->config->sessionName . 's';
		$a = array($name, $nameSecure); 
		if($this->config->sessionChallenge) {
			$a[] = $name . self::challengeSuffix;
			$a[] = $nameSecure . self::challengeSuffix;
		}
		return $a;
	}

	/**
	 * Logout success method for hooks
	 * 
	 * #pw-hooker 
	 *
	 * @param User $user User that logged in
	 *
	 */
	protected function ___logoutSuccess(User $user) { 
		$this->log("Logout for '$user->name'"); 
	}

	/**
	 * Redirect this session to another URL.
	 * 
	 * Execution halts within this function after redirect has been issued. 
	 * 
	 * ~~~~~
	 * // redirect to homepage
	 * $session->redirect('/'); 
	 * ~~~~~
	 * 
	 * #pw-group-redirects
	 * 
	 * @param string $url URL to redirect to
	 * @param bool|int $status One of the following (or omit for 301):
	 * - `true` (bool): Permanent redirect (same as 301).
	 * - `false` (bool): Temporary redirect (same as 302).
	 * - `301` (int): Permanent redirect using GET. (3.0.166+)
	 * - `302` (int): “Found”, Temporary redirect using GET. (3.0.166+)
	 * - `303` (int): “See other”, Temporary redirect using GET. (3.0.166+)
	 * - `307` (int): Temporary redirect using current request method such as POST (repeat that request). (3.0.166+)
	 * @see Session::location()
	 *
	 */
	public function ___redirect($url, $status = 301) {
		
		$page = $this->wire()->page;

		if($status === true || "$status" === "301" || "$status" === "1") {
			$status = 301;
		} else if($status === false || "$status" === "302" || "$status" === "0") {
			$status = 302;
		} else {
			$status = (int) $status;
			// if invalid redirect http status code, fallback to 302
			if($status < 300 || $status > 399) $status = 302;
		}

		// if there are notices, then queue them so that they aren't lost
		if($this->sessionInit) {
			$notices = $this->wire()->notices;
			if($notices && count($notices)) {
				foreach($notices as $notice) {
					$this->queueNotice($notice);
				}
			}
		}

		// perform the redirect
		if($page) {
			$process = $this->wire()->process; 
			if("$process" !== "ProcessPageView") {
				$process = $this->wire()->modules->get('ProcessPageView');
			}
			/** @var ProcessPageView $process */
			if($process) {
				// ensure ProcessPageView is properly closed down
				$process->setResponseType(ProcessPageView::responseTypeRedirect);
				$process->finished();
				// retain modal=1 get variables through redirects (this can be moved to a hook later)
				$input = $this->wire()->input;
				if($page->template == 'admin' && $input && $input->get('modal') && strpos($url, '//') === false) {
					if(!strpos($url, 'modal=')) $url .= (strpos($url, '?') !== false ? '&' : '?') . 'modal=1';
				}
			}
		}
		
		$this->wire()->setStatus(ProcessWire::statusFinished, array(
			'redirectUrl' => $url,
			'redirectType' => $status, 
		));
		
		// note for 302 redirects we send no header other than 'Location: url'
		$http = new WireHttp();
		$this->wire($http);
		$http->sendStatusHeader($status);
		$http->sendHeader("Location: $url");
		
		exit(0);
	}

	/**
	 * Perform a temporary redirect
	 * 
	 * This is an alias of `$session->redirect($url, false);` that sends only the
	 * location header, which translates to a 302 redirect.
	 * 
	 * #pw-group-redirects
	 * 
	 * @param string $url
	 * @param int $status One of the following HTTP status codes, or omit for 302 (added 3.0.192): 
	 * - `302` (int): “Found”, Temporary redirect using GET. (default)
	 * - `303` (int): “See other”, Temporary redirect using GET.
	 * - `307` (int): Temporary redirect using current request method such as POST (repeat that request).
	 * @since 3.0.166 
	 * @see Session::redirect()
	 * 
	 */
	public function location($url, $status = 302) {
		$this->redirect($url, $status); 
	}

	/**
	 * Manually close the session, before program execution is done
	 * 
	 * A user session is limited to rendering one page at a time, unless the session is closed
	 * early. Use this when you have a request that may take awhile to render (like a request 
	 * rendering a sitemap, etc.) and you don't need to get/save session data. By closing the session 
	 * before starting a render, you can release the session to be available for the user to view
	 * other pages while the slower page render continues. 
	 * 
	 */
	public function close() {
		if($this->sessionInit) session_write_close();
	}

	/**
	 * Queue notice text to be shown the next time this session class is instantiated
	 * 
	 * #pw-internal
	 * 
	 * @param string $text
	 * @param string $type One of "messages", "errors" or "warnings"
	 * @param int $flags
	 * 
	 */
	protected function queueNoticeText($text, $type, $flags) {
		if(!$this->sessionInit) return;
		$items = $this->getFor('_notices', $type);
		if(is_null($items)) $items = array();
		$item = array('text' => $text, 'flags' => $flags); 
		$items[] = $item;
		$this->setFor('_notices', $type, $items); 
	}

	/**
	 * Queue a Notice object to be shown the next time this session class is instantiated
	 *
	 * #pw-internal
	 *
	 * @param Notice $notice
	 *
	 */
	protected function queueNotice(Notice $notice) {
		if(!$this->sessionInit) return;
		$type = $notice->getName();
		$items = $this->getFor('_notices', $type);
		if(is_null($items)) $items = array();
		$items[] = $notice->getArray();
		$this->setFor('_notices', $type, $items); 
	}

	/**
	 * Pull queued notices and convert them to notices for this request
	 * 
	 * #pw-internal
	 * 
	 */
	protected function wakeupNotices() {

		$notices = $this->wire()->notices;
		if(!$notices) return;
		
		$types = array(
			'messages' => 'NoticeMessage',
			'errors' => 'NoticeError',
			'warnings' => 'NoticeWarning',
		);
		
		foreach($types as $type => $className) {
			$items = $this->getFor('_notices', $type);
			if(!is_array($items)) continue;
			
			foreach($items as $item) {
				if(!isset($item['text'])) continue;
				$class = wireClassName($className, true);
				$notice = $this->wire(new $class(''));
				$notice->setArray($item);
				$notices->add($notice);
			}
		}
	}


	/**
	 * Queue a message to appear on the next pageview
	 * 
	 * #pw-group-notices
	 * 
	 * @param string $text Message to queue
	 * @param int $flags Optional flags, See Notice::flags
	 * @return $this
	 *
	 */
	public function message($text, $flags = 0) {
		$this->queueNoticeText($text, 'messages', $flags); 
		return $this;
	}

	/**
	 * Queue an error to appear on the next pageview
	 * 
	 * #pw-group-notices
	 *
	 * @param string $text Error to queue
	 * @param int $flags See Notice::flags
	 * @return $this
	 * 
	 */
	public function error($text, $flags = 0) {
		$this->queueNoticeText($text, 'errors', $flags); 
		return $this; 
	}

	/**
	 * Queue a warning to appear the next pageview
	 * 
	 * #pw-group-notices
	 *
	 * @param string $text Warning to queue
	 * @param int $flags See Notice::flags
	 * @return $this
	 *
	 */
	public function warning($text, $flags = 0) {
		$this->queueNoticeText($text, 'warnings', $flags);
		return $this;
	}

	/**
	 * Session maintenance
	 * 
	 * This is automatically called by ProcessWire at the end of the request,
	 * no need to call it on your own. 
	 *
	 * Keep track of session history, if $config->sessionHistory is used.
	 * It can be retrieved with the $session->getHistory() method.
	 * 
	 * #pw-internal
	 * 
	 * @todo add extra gc checks
	 *
	 */
	public function maintenance() {

		if($this->skipMaintenance || !$this->sessionInit) return;
		
		// prevent multiple calls, just in case
		$this->skipMaintenance = true; 
	
		$historyCnt = (int) ($this->config ? $this->config->sessionHistory : 0);
		
		if($historyCnt) {
		
			$sanitizer = $this->wire()->sanitizer;
			$input = $this->wire()->input;
			$page = $this->wire()->page;
			
			if(!$sanitizer || !$input || !$page) return;
			
			$history = $this->get('_user', 'history');
			if(!is_array($history)) $history = array();

			$item = array(
				'time' => time(),
				'url'  => $sanitizer->entities($input->httpUrl()),
				'page' => $page->id,
			);

			$cnt = count($history); 
			if($cnt) {
				end($history);
				$lastKey = key($history);
				$nextKey = $lastKey+1;
				if($cnt >= $historyCnt) { 
					if($historyCnt > 1) {
						$history = array_slice($history, -1 * ($historyCnt - 1), null, true);
					} else {
						$history = array();
					}
				}
			} else {
				$nextKey = 0;
			}

			$history[$nextKey] = $item;
			$this->set('_user', 'history', $history);
		}
	}

	/**
	 * Get the session history (if enabled)
	 * 
	 * Applicable only if `$config->sessionHistory > 0`.
	 * 
	 * ~~~~~
	 * $history = $session->getHistory();
	 * print_r($history);
	 * // outputs the following:
	 * array(
	 *   0  => array(
	 * 		'url' => 'http://domain.com/path/to/page/', // URL
	 * 		'page' => 1234, // page ID
	 * 		'time' => 234993498, // unix timestamp 
	 *   ), 
	 *   1 => array(
	 *      // ... 
	 *   ),
	 *   2 => array(
	 *      // ...
	 *   ), 
	 *   ...
	 * );
	 * ~~~~~
	 * 
	 * #pw-group-advanced
	 * 
	 * @return array Array of arrays containing history entries. 
	 * 
	 */
	public function getHistory() {
		$value = $this->get('_user', 'history'); 
		if(!is_array($value)) $value = array();
		return $value; 
	}

	/**
	 * Remove queued notices
	 * 
	 * Call this after displaying queued message, error or warning notices. 
	 * This prevents them from re-appearing on the next request.
	 * 
	 * #pw-group-notices
	 * 
	 */
	public function removeNotices() {
		$this->removeAllFor('_notices');
	}

	/**
	 * Return an instance of ProcessWire’s CSRF object, which provides an API for cross site request forgery protection.
	 * 
	 * ~~~~
	 * // output somewhere in <form> markup when rendering a form
	 * echo $session->CSRF->renderInput();
	 * ~~~~
	 * ~~~~ 
	 * // when processing form (POST request), check to see if token is present
	 * if($session->CSRF->hasValidToken()) {
	 *   // form submission is valid
	 *   // okay to process
	 * } else {
	 *   // form submission is NOT valid
	 *   throw new WireException('CSRF check failed!');
	 * }
	 * ~~~~
	 * 
	 * #pw-group-advanced
	 * 
	 * @return SessionCSRF
	 * @see SessionCSRF::renderInput(), SessionCSRF::validate(), SessionCSRF::hasValidToken()
	 * 
	 */
	public function CSRF() {
		if(!$this->sessionInit) $this->init(); // init required for CSRF
		if(is_null($this->CSRF)) $this->CSRF = $this->wire(new SessionCSRF());
		return $this->CSRF; 
	}
	
	/**
	 * Get or set current session handler instance (WireSessionHandler)
	 *
	 * #pw-internal
	 *
	 * @param WireSessionHandler|null $sessionHandler Specify only when setting, omit to get session handler. 
	 * @return null|WireSessionHandler Returns WireSessionHandler instance, or…
	 *   returns null when session handler is not yet known or is PHP (file system)
	 * @since 3.0.166
	 *
	 */
	public function sessionHandler(?WireSessionHandler $sessionHandler = null) {
		if($sessionHandler) $this->sessionHandler = $sessionHandler;
		return $this->sessionHandler;
	}


}
