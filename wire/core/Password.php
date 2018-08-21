<?php namespace ProcessWire;
/**
 * ProcessWire Password Fieldtype
 *
 * Class to hold combined password/salt info. Uses Blowfish when possible.
 * Specially used by FieldtypePassword.
 * 
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 * 
 * @method setPass($value)
 *
 */

class Password extends Wire {

	/**
	 * @var array
	 * 
	 */
	protected $data = array(
		'salt' => '', 
		'hash' => '',
		);

	/**
	 * @var WireRandom|null
	 * 
	 */
	protected $random = null;

	/**
	 * Does this Password match the given string?
	 *
	 * @param string $pass Password to compare
	 * @return bool
	 *
	 */
	public function matches($pass) {

		if(!strlen($pass)) return false;
		$hash = $this->hash($pass); 
		if(!strlen($hash)) return false;
		$updateNotify = false;

		if($this->isBlowfish($hash)) {
			$hash = substr($hash, 29);

		} else if($this->supportsBlowfish()) {
			// notify user they may want to change their password
			// to take advantage of blowfish hashing
			$updateNotify = true; 
		}

		if(strlen($hash) < 29) return false;

		$matches = ($hash === $this->data['hash']);

		if($matches && $updateNotify) {
			$this->message($this->_('The password system has recently been updated. Please change your password to complete the update for your account.'));
		}

		return $matches; 
	}

	/**
	 * Get a property via direct access ('salt' or 'hash')
	 * 
	 * #pw-group-internal
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __get($key) {
		if($key == 'salt' && !$this->data['salt']) $this->data['salt'] = $this->salt();
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	/**
	 * Set a property 
	 * 
	 * #pw-group-internal
	 * 
	 * @param string $key
	 * @param mixed $value
	 *
	 */
	public function __set($key, $value) {

		if($key == 'pass') {
			// setting the password
			$this->setPass($value);

		} else if(array_key_exists($key, $this->data)) { 
			// something other than pass
			$this->data[$key] = $value; 
		}
	}

	/**
	 * Set the 'pass' to the given value
	 * 
	 * @param string $value
	 * @throws WireException if given invalid $value
	 *
	 */
	protected function ___setPass($value) {

		// if nothing supplied, then don't continue
		if(!strlen($value)) return;
		if(!is_string($value)) throw new WireException("Password must be a string"); 

		// first check to see if it actually changed
		if($this->data['salt'] && $this->data['hash']) {
			$hash = $this->hash($value);
			if($this->isBlowfish($hash)) $hash = substr($hash, 29);
			// if no change then return now
			if($hash === $this->data['hash']) return; 
		}

		// password has changed
		$this->trackChange('pass');

		// force reset by clearing out the salt, hash() will gen a new salt
		$this->data['salt'] = ''; 

		// generate the new hash
		$hash = $this->hash($value);

		// if it's a blowfish hash, separate the salt from the hash
		if($this->isBlowfish($hash)) {
			$this->data['salt'] = substr($hash, 0, 29); // previously 28
			$this->data['hash'] = substr($hash, 29);
		} else {
			$this->data['hash'] = $hash;
		}
	}

	/**
	 * Generate a random salt for the given hashType
	 *
	 * @return string
	 *
	 */
	protected function salt() {

		// if system doesn't support blowfish, return old style salt
		if(!$this->supportsBlowfish()) return md5($this->randomBase64String(44)); 

		// blowfish assumed from this point forward
		// use stronger blowfish mode if PHP version supports it 
		$salt = (version_compare(PHP_VERSION, '5.3.7') >= 0) ? '$2y' : '$2a';

		// cost parameter (04-31)
		$salt .= '$11$';
		// 22 random base64 characters
		$salt .= $this->randomBase64String(22);
		// plus trailing $
		$salt .= '$'; 

		return $salt;
	}

	/**
	 * Generate a truly random base64 string of a certain length
	 *
	 * See WireRandom::base64() for details
	 *
	 * @param int $requiredLength Length of string you want returned (default=22)
	 * @param array|bool $options Specify array of options or boolean to specify only `fast` option.
	 *  - `fast` (bool): Use fastest, not cryptographically secure method (default=false). 
	 *  - `test` (bool|array): Return tests in a string (bool true), or specify array(true) to return tests array (default=false).
	 *    Note that if the test option is used, then the fast option is disabled. 
	 * @return string|array Returns only array if you specify array for $test argument, otherwise returns string
	 *
	 */
	public function randomBase64String($requiredLength = 22, $options = array()) {
		return $this->random()->base64($requiredLength, $options);
	}

	/**
 	 * Returns whether the given string is blowfish hashed
	 *
	 * @param string $str
	 * @return bool
	 *
	 */
	public function isBlowfish($str = '') {
		if(!strlen($str)) $str = $this->data['salt'];
		$prefix = substr($str, 0, 3); 
		return $prefix === '$2a' || $prefix === '$2x' || $prefix === '$2y'; 
	}

	/**
 	 * Returns whether the current system supports Blowfish
	 *
	 * @return bool
	 *
	 */
	public function supportsBlowfish() {
		return version_compare(PHP_VERSION, '5.3.0') >= 0 && defined("CRYPT_BLOWFISH") && CRYPT_BLOWFISH;
	}

	/**
	 * Given an unhashed password, generate a hash of the password for database storage and comparison
	 *
	 * Note: When blowfish, returns the entire blowfish string which has the salt as the first 28 characters. 
	 *
	 * @param string $pass Raw password
	 * @return string
	 * @throws WireException
	 *
	 */
	protected function hash($pass) {

		// if there is no salt yet, make one (for new pass or reset pass)
		if(strlen($this->data['salt']) < 28) $this->data['salt'] = $this->salt();

		// if system doesn't support blowfish, but has a blowfish salt, then reset it 
		if(!$this->supportsBlowfish() && $this->isBlowfish($this->data['salt'])) $this->data['salt'] = $this->salt();

		// salt we made (the one ultimately stored in DB)
		$salt1 = $this->data['salt'];

		// static salt stored in config.php
		$salt2 = (string) $this->wire('config')->userAuthSalt; 

		// auto-detect the hash type based on the format of the salt
		$hashType = $this->isBlowfish($salt1) ? 'blowfish' : $this->wire('config')->userAuthHashType;

		if(!$hashType) {
			// If there is no defined hash type, and the system doesn't support blowfish, then just use md5 (ancient backwards compatibility)
			$hash = md5($pass); 

		} else if($hashType == 'blowfish') {
			if(!$this->supportsBlowfish()) {
				throw new WireException("This version of PHP is not compatible with the passwords. Did passwords originate on a newer version of PHP?"); 
			}
			// our preferred method
			$hash = crypt($pass . $salt2, $salt1);

		} else {
			// older style, non-blowfish support
			// split the password in two
			$splitPass = str_split($pass, (strlen($pass) / 2) + 1); 
			// generate the hash
			$hash = hash($hashType, $salt1 . $splitPass[0] . $salt2 . $splitPass[1], false); 
		}

		if(!is_string($hash) || strlen($hash) <= 13) throw new WireException("Unable to generate password hash"); 

		return $hash; 
	}

	/**
	 * Return a pseudo-random alpha or alphanumeric character
	 * 
	 * This method may be deprecated at some point, so it is preferable to use the 
	 * `randomLetters()` or `randomAlnum()` methods instead, when you can count on 
	 * the PW version being 3.0.109 or higher. 
	 * 
	 * @param int $qty Number of random characters requested
	 * @param bool $alphanumeric Specify true to allow digits in return value
	 * @param array $disallow Characters that may not be used in return value
	 * @return string
	 * @deprecated use WireRandom::alpha() instead
	 *
	 */
	public function randomAlpha($qty = 1, $alphanumeric = false, $disallow = array()) {
		$letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$digits = '0123456789';
		if($alphanumeric) $letters .= $digits;
		if($alphanumeric === 1) $letters = $digits; // digits only
		foreach($disallow as $c) {
			$letters = str_replace($c, '', $letters);
		}
		$value = '';
		for($x = 0; $x < $qty; $x++) {
			$n = mt_rand(0, strlen($letters) - 1);
			$value .= $letters[$n];
		}
		return $value;
	}

	/**
	 * Return cryptographically secure random alphanumeric, alpha or numeric string
	 * 
	 * @param int $length Required length of string, or 0 for random length
	 * @param array $options See WireRandom::alphanumeric() for options
	 * @return string
	 * @throws WireException
	 * @since 3.0.109
	 * @deprecated use WireRandom::alphanumeric() instead
	 * 
	 */
	public function randomAlnum($length = 0, array $options = array()) {
		return $this->random()->alphanumeric($length, $options); 
	}

	/**
	 * Return string of random letters
	 *
	 * @param int $length Required length of string or 0 for random length
	 * @param array $options See options for randomAlnum() method
	 * @return string
	 * @since 3.0.109
	 * @deprecated use WireRandom::alpha() instead.
	 *
	 */
	public function randomLetters($length = 0, array $options = array()) {
		return $this->random()->alpha($length, $options);
	}

	/**
	 * Return string of random digits
	 * 
	 * @param int $length Required length of string or 0 for random length
	 * @param array $options See WireRandom::numeric() method
	 * @return string
	 * @since 3.0.109
	 * @deprecated Use WireRandom::numeric() instead
	 * 
	 */
	public function randomDigits($length = 0, array $options = array()) {
		return $this->random()->numeric($length, $options);
	}

	/**
	 * Generate and return a random password
	 * 
	 * See WireRandom::pass() method for details. 
	 * 
	 * @param array $options See WireRandom::pass() for options
	 * @return string
	 * 
	 */
	public function randomPass(array $options = array()) {
		return $this->random()->pass($options);
	}

	/**
	 * @return WireRandom
	 * 
	 */
	protected function random() {
		if($this->random === null) $this->random = $this->wire(new WireRandom());
		return $this->random;
	}
	
	public function __toString() {
		return (string) $this->data['hash'];
	}

}

