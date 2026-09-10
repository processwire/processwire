<?php namespace ProcessWire;

/**
 * ProcessWire CSRF Protection
 *
 * #pw-summary Provides an API for cross site request forgery protection.
 * #pw-body = 
 * ~~~~
 * // output somewhere in form markup when rendering a form
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
 * ~~~~
 * // this alternative to hasValidToken() throws WireCSRFException when invalid
 * $session->CSRF->validate();
 * ~~~~
 *
 * Tokens come in two modes, selected by `$config->csrfMode`:
 *
 * - `session` (default): random tokens stored in the session. A token is only valid
 *   for the session that created it.
 *
 * - `signed`: tokens derived (HMAC-SHA256, keyed by `$config->userAuthSalt`) from a
 *   long-lived, httpOnly binding cookie that is independent of the session. Tokens stay
 *   valid across session expiration and re-creation, and on pages restored from the
 *   browser back/forward cache, for as long as the binding cookie lasts. The cookie is
 *   rotated at login (via resetAll) and whenever tokens are reset. Single-use tokens
 *   are always session-based.
 *
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 */
class SessionCSRF extends Wire {

	/**
	 * Suffix appended to session_name() for the signed-mode binding cookie name
	 *
	 */
	const bindingCookieSuffix = '_csrf';

	/**
	 * Lifetime (seconds) of the signed-mode binding cookie
	 *
	 */
	const bindingCookieAge = 31536000;

	/**
	 * Cached binding cookie value for signed mode (blank when not yet resolved)
	 *
	 * @var string
	 *
	 */
	protected $bindingCookie = '';

	/**
	 * Get a CSRF Token name, or create one if it doesn't yet exist
	 * 
	 * #pw-group-initiating
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	public function getTokenName($id = '') {
		$tokenName = $this->wire()->session->get($this, "name$id");
		if($tokenName) return $tokenName; // existing session-based token (i.e. single-use)
		if($this->signedMode()) return $this->signedTokenName($id);
		return $this->sessionTokenName($id);
	}

	/**
	 * Get a CSRF Token value as stored in the session, or create one if it doesn't yet exist
	 * 
	 * #pw-group-initiating
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	public function getTokenValue($id = '') {
		$session = $this->wire()->session;
		if(!$session->get($this, "name$id") && $this->signedMode()) return $this->signedTokenValue($id);
		return $this->sessionTokenValue($id);
	}

	/**
	 * Get a CSRF Token timestamp
	 * 
	 * #pw-group-initiating
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return int
	 *
	 */
	public function getTokenTime($id = '') {
		$name = $this->getTokenName($id);
		$time = (int) substr($name, strrpos($name, 'X')+1); 
		return $time; 
	}

	/**
	 * Get a CSRF Token name and value
	 * 
	 * #pw-group-initiating
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return array ("name" => "token name", "value" => "token value", "time" => created timestamp)
	 *
	 */
	public function getToken($id = '') {
		return array(
			'name' => $this->getTokenName($id), 
			'value' => $this->getTokenValue($id),
			'time' => $this->getTokenTime($id)
		); 
	}

	/**
	 * Get a CSRF Token name and value that can only be used once
	 * 
	 * Note that a single call to hasValidToken($id) or validate($id) will invalidate the single use token.
	 * So call them once and store your result if you need the result multiple times.
	 *
	 * Single-use tokens require per-token server-side state, so they are always
	 * session-based, regardless of `$config->csrfMode`.
	 *
	 * #pw-group-initiating
	 *
	 * @param int|string $id Optional unique ID/name for this token (of omitted one is generated automatically)
	 * @return array ("id' => "token ID", "name" => "token name", "value" => "token value", "time" => created timestamp)
	 *
	 */
	public function getSingleUseToken($id = '') {
		$session = $this->wire()->session;
		if(!strlen($id)) $id = (string) mt_rand();
		$name = $this->sessionTokenName($id);
		$time = $this->getTokenTime($id);
		$value = $this->sessionTokenValue($id);
		$singles = $session->get($this, 'singles'); 
		$singles[$name] = $value; 
		$session->set($this, 'singles', $singles); 	
		return array(
			'id' => $id, 
			'name' => $name,
			'value' => $value,
			'time' => $time
		);
	}

	/**
	 * Returns true if the current POST request contains a valid CSRF token, false if not
	 * 
	 * #pw-group-validating
	 *
	 * @param int|string|null $id Optional unique ID for this token, but required if checking a single use token.
	 * @param bool|null Reset after checking? Or omit (null) for auto (which resets if single-use token, and not otherwise). 
	 * @return bool
	 *
	 */
	public function hasValidToken($id = '', $reset = null) {
	
		$session = $this->wire()->session;
		$config = $this->wire()->config;
		$input = $this->wire()->input;
		
		$tokenName = $this->getTokenName($id);
		$tokenValue = $this->getTokenValue($id);
		$headerName = "HTTP_X_$tokenName";
		$valid = false;
		
		if(strlen($id)) {
			$singles = $session->get($this, 'singles'); 
			if(is_array($singles) && isset($singles[$tokenName])) {
				// remove single use token
				unset($singles[$tokenName]); 
				$session->set($this, 'singles', $singles); 
				if($reset !== false) $reset = true; 
			}
		}
	
		
		$headerValue = isset($_SERVER[$headerName]) ? $_SERVER[$headerName] : null;
		$postValue = $input->post($tokenName);

		if($config->ajax && is_string($headerValue) && hash_equals($tokenValue, $headerValue)) {
			$valid = true;
		} else if(is_string($postValue) && hash_equals($tokenValue, $postValue)) {
			$valid = true;
		}

		if($reset) $this->resetToken($id);
		
		return $valid; 
	}

	/**
	 * Throws an exception if the token is invalid
	 * 
	 * #pw-group-validating
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @throws WireCSRFException if token not valid
	 * @return bool Always returns true or throws exception
	 * 
	 */
	public function validate($id = '') {
		if(!$this->wire()->config->protectCSRF) return true; 
		if($this->hasValidToken($id)) return true;
		$this->resetToken();
		throw new WireCSRFException($this->_('This request was aborted because it appears to be forged.')); 
	}

	/**
	 * Clear out token value
	 *
	 * In signed mode, resetting a token that has no session-based state rotates the
	 * binding cookie, which invalidates all signed tokens previously issued to the client.
	 *
	 * #pw-group-resetting
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 *
	 */
	public function resetToken($id = '') {
		$session = $this->wire()->session;
		$tokenName = $session->get($this, "name$id");
		if(!$tokenName && $this->signedMode()) {
			// signed tokens have no per-token state: invalidate by rotating the binding cookie
			$this->rotateBindingCookie();
			return;
		}
		if(!$tokenName) $tokenName = $this->sessionTokenName($id);
		$session->remove($this, "name$id");
		$session->remove($this, $tokenName);
	}

	/**
	 * Clear out all saved token values
	 *
	 * In signed mode this also rotates the binding cookie, invalidating all signed
	 * tokens previously issued to the client (login calls this method).
	 *
	 * #pw-group-resetting
	 *
	 */
	public function resetAll() {
		$this->wire()->session->remove($this, true);
		if($this->signedMode()) $this->rotateBindingCookie();
	}
	
	/**
	 * Render a form input[hidden] containing the token name and value, as looked for by hasValidToken()
	 * 
	 * ~~~~~
	 * <form method='post'>
	 *   <input type='submit'>
	 *   <?php echo $session->CSRF->renderInput(); ?> 
	 * </form>
	 * ~~~~~
	 * 
	 * #pw-group-initiating
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	public function renderInput($id = '') {
		$tokenName = $this->getTokenName($id);
		$tokenValue = $this->getTokenValue($id);
		return "<input type='hidden' name='$tokenName' value='$tokenValue' class='_post_token' />";
	}

	/**
	 * Get a session-based CSRF token name, or create one if it doesn't yet exist
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	protected function sessionTokenName($id = '') {
		$session = $this->wire()->session;
		$tokenName = $session->get($this, "name$id");
		if(!$tokenName) {
			$tokenName = 'TOKEN' . mt_rand() . "X" . time(); // token name always ends with timestamp
			$session->set($this, "name$id", $tokenName);
		}
		return $tokenName;
	}

	/**
	 * Get a session-based CSRF token value, or create one if it doesn't yet exist
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	protected function sessionTokenValue($id = '') {
		$session = $this->wire()->session;
		$tokenName = $this->sessionTokenName($id);
		$tokenValue = $session->get($this, $tokenName);
		if(empty($tokenValue)) {
			// $tokenValue = md5($this->page->path() . mt_rand() . microtime()) . md5($this->page->name . $this->config->userAuthSalt . mt_rand());
			$rand = new WireRandom();
			$tokenValue = $rand->base64(32, array('fast' => true));
			$session->set($this, $tokenName, $tokenValue);
		}
		return $tokenValue;
	}

	/**
	 * Are tokens in signed (cookie-derived) mode rather than session mode?
	 *
	 * @return bool
	 *
	 */
	protected function signedMode() {
		return strtolower((string) $this->wire()->config->csrfMode) === 'signed';
	}

	/**
	 * Get the name of the signed-mode binding cookie
	 *
	 * @return string
	 *
	 */
	protected function bindingCookieName() {
		return session_name() . self::bindingCookieSuffix;
	}

	/**
	 * Get the signed-mode binding cookie value, creating the cookie if not yet present
	 *
	 * @return string
	 *
	 */
	protected function bindingCookieValue() {
		if($this->bindingCookie !== '') return $this->bindingCookie;
		$name = $this->bindingCookieName();
		$value = isset($_COOKIE[$name]) ? (string) $_COOKIE[$name] : '';
		if(!preg_match('/^\d+x[A-Za-z0-9]{40}$/', $value)) $value = $this->rotateBindingCookie();
		$this->bindingCookie = $value;
		return $value;
	}

	/**
	 * Replace the signed-mode binding cookie with a new one, invalidating all signed tokens
	 *
	 * @return string New cookie value
	 *
	 */
	protected function rotateBindingCookie() {
		$config = $this->wire()->config;
		$rand = new WireRandom();
		$name = $this->bindingCookieName();
		$value = time() . 'x' . $rand->alphanumeric(40);
		$secure = $config->sessionCookieSecure ? (bool) $config->https : false;
		$samesite = (string) $config->sessionCookieSameSite;
		$samesite = empty($samesite) ? 'Lax' : ucfirst(strtolower($samesite));
		if(!in_array($samesite, array('Strict', 'Lax', 'None'), true)) $samesite = 'Lax';
		if($samesite === 'None') $secure = true;
		if(!headers_sent()) {
			setcookie($name, $value, array(
				'expires' => time() + self::bindingCookieAge,
				'path' => '/',
				'domain' => (string) $config->sessionCookieDomain,
				'secure' => $secure,
				'httponly' => true,
				'samesite' => $samesite,
			));
		}
		$_COOKIE[$name] = $value;
		$this->bindingCookie = $value;
		return $value;
	}

	/**
	 * Get an HMAC hash for the given use string, bound to the binding cookie
	 *
	 * @param string $use
	 * @return string
	 *
	 */
	protected function signedHash($use) {
		$config = $this->wire()->config;
		return hash_hmac('sha256', $use . ':' . $this->bindingCookieValue(), (string) $config->userAuthSalt);
	}

	/**
	 * Get a signed-mode token name (derived from the binding cookie, not stored)
	 *
	 * Keeps the same TOKEN<digits>X<timestamp> shape as session-mode token names,
	 * with the timestamp portion coming from the binding cookie's creation time.
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	protected function signedTokenName($id = '') {
		$cookie = $this->bindingCookieValue();
		$time = (int) substr($cookie, 0, strpos($cookie, 'x'));
		$digits = hexdec(substr($this->signedHash("name$id"), 0, 12));
		return "TOKEN{$digits}X$time";
	}

	/**
	 * Get a signed-mode token value (derived from the binding cookie, not stored)
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * @return string
	 *
	 */
	protected function signedTokenValue($id = '') {
		return $this->signedHash("value$id");
	}

}
