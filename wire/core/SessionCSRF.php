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
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 */
class SessionCSRF extends Wire {
	
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
		$session = $this->wire()->session;
		$tokenName = $session->get($this, "name$id"); 
		if(!$tokenName) { 
			$tokenName = 'TOKEN' . mt_rand() . "X" . time(); // token name always ends with timestamp
			$session->set($this, "name$id", $tokenName);
		}
		return $tokenName; 
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
		$tokenName = $this->getTokenName($id);
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
	 * #pw-group-initiating
	 *
	 * @param int|string $id Optional unique ID/name for this token (of omitted one is generated automatically)
	 * @return array ("id' => "token ID", "name" => "token name", "value" => "token value", "time" => created timestamp)
	 *
	 */
	public function getSingleUseToken($id = '') {
		$session = $this->wire()->session;
		if(!strlen($id)) $id = (string) mt_rand();
		$name = $this->getTokenName($id);
		$time = $this->getTokenTime($id); 
		$value = $this->getTokenValue($id); 
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
	
		
		if($config->ajax && isset($_SERVER[$headerName]) && $_SERVER[$headerName] === $tokenValue) {
			$valid = true;
		} else if($input->post($tokenName) === $tokenValue) {
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
	 * #pw-group-resetting
	 *
	 * @param int|string|null $id Optional unique ID for this token
	 * 
	 */
	public function resetToken($id = '') {
		$tokenName = $this->getTokenName($id);
		$session = $this->wire()->session;
		$session->remove($this, "name$id"); 
		$session->remove($this, $tokenName); 
	}

	/**
	 * Clear out all saved token values
	 * 
	 * #pw-group-resetting
	 *
	 */
	public function resetAll() {
		$this->wire()->session->remove($this, true); 
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

}
