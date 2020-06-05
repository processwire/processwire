<?php namespace ProcessWire;

/**
 * Tfa - ProcessWire Two Factor Authentication module base class
 * 
 * This class is for “Tfa” modules to extend. See the TfaEmail and TfaTotp modules as examples. 
 * 
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 * 
 *
 * USAGE
 * ~~~~~~
 * $tfa = new Tfa();
 * 
 * if($tfa->success()) {
 *   $session->redirect('after/login/url/');
 * 
 * } else if($tfa->active()) {
 *   echo $tfa->render();
 * 
 * } else if($input->post('submit_login')) {
 *   $name = $input->post('name');
 *   $pass = $input->post('pass');
 *   $tfa->start($name, $pass); 
 * 
 *   // the start() method performs a redirect if TFA is active for the user
 *   // place your regular code to login user here, which will be used if TFA is not active for the user
 * 
 * } else {
 *   // render login form
 * }
 * ~~~~~~
 * 
 * @property int $codeLength Required length for authentication code (default=6)
 * @property int $codeExpire Codes expire after this many seconds (default=180)
 * @property int $codeType Type of TFA code to use, see codeType constants (default=0, which is Tfa::codeTypeDigits)
 * @property string $startUrl URL we are operating from (default='./')
 * @property int $rememberDays Number of days to "remember this browser", 0 to disable option, or -1 for no limit? (default=0)
 * @property array $rememberFingerprints Fingerprints to remember: agent,agentVL,accept,scheme,host,ip,fwip (default=agentVL,accept,scheme,host)
 * @property array $formAttrs Form <form> element attributes
 * @property array $inputAttrs Code <input> element attributes
 * @property string $inputLabel Label for code <input> element
 * @property array $submitAttrs Submit button attributes
 * @property string $submitLabel Label for submit button
 * @property bool $showCancel Show a cancel link under authentication code form? (default=true)
 * @property string $cancelLabel Label to use for Cancel link (default='Cancel', translatable)
 * @property string $rememberLabel Label for "remember this browser" option 
 * 
 * 
 * @method bool start($name, $pass)
 * @method InputfieldForm buildAuthCodeForm()
 * @method string render()
 * @method User|bool process()
 * @method void getUserSettingsInputfields(User $user, InputfieldWrapper $fieldset, $settings) 
 * @method array processUserSettingsInputfields(User $user, InputfieldWrapper $fieldset, $settings, $settingsPrev) 
 * @method install()
 * @method uninstall()
 * 
 */
class Tfa extends WireData implements Module, ConfigurableModule {

	/**
	 * Name used for GET variable when TFA is active
	 * 
	 * @var string
	 * 
	 */
	protected $keyName = 'tfa';

	/**
	 * Form used for code input
	 * 
	 * @var InputfieldForm|null
	 * 
	 */
	protected $authCodeForm = null;

	/**
	 * Prefix for field names on user template to store TFA data
	 * 
	 * @var string
	 * 
	 */
	protected $userFieldName = 'tfa_type';

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		$this->set('codeExpire', 180);
		$this->set('startUrl', './');
		$this->set('rememberDays', 0);
		$this->set('rememberLabel', $this->_('Remember this computer?'));
		$this->set('rememberFingerprints', array('agentVL', 'accept', 'scheme', 'host')); 
		$this->set('formAttrs', array('id' => 'ProcessLoginForm', 'class' => 'pw-tfa'));
		$this->set('inputAttrs', array('id' => 'login_name', 'autofocus' => 'autofocus'));
		$this->set('inputLabel', $this->_('Authentication Code'));
		$this->set('submitAttrs', array('id' => 'Inputfield_login_submit'));
		$this->set('submitLabel', '');
		$this->set('showCancel', true);
		$this->set('cancelLabel', $this->_('Cancel'));
		parent::__construct();
	}

	/**
	 * Called when assigned to ProcessWire instance
	 * 
	 */
	public function wired() {
		if(!$this->wire()->fields->get($this->userFieldName)) $this->install();
		if($this->className() != 'Tfa') $this->initHooks();
		parent::wired();
	}

	/**
	 * Access the RememberTfa instance 
	 * 
	 * #pw-internal
	 * 
	 * @param User $user
	 * @param array $settings
	 * @return RememberTfa
	 * 
	 */
	public function remember(User $user, array $settings) {
		$remember = $this->wire(new RememberTfa($this, $user, $settings));
		$remember->setDays($this->rememberDays);
		$remember->setFingerprints($this->rememberFingerprints);
		return $remember;
	}

	/**
	 * Get the start URL and optionally append query string
	 * 
	 * @param string $queryString
	 * @return string
	 * 
	 */
	protected function url($queryString = '') {
		$url = $this->startUrl;
		if(empty($queryString)) return $url;
		$queryString = ltrim($queryString, '?&');
		$url .= (strpos($url, '?') === false ? '?' : '&');
		$url .= $queryString;
		return $url;
	}

	/**
	 * Redirect to URL
	 * 
	 * @param string $url
	 * 
	 */
	protected function redirect($url) {
		if(strpos($url, '/') === false) $url = $this->url($url);
		$this->session->redirect($url, false);
	}

	/**
	 * Start 2-factor authentication
	 * 
	 * On successful authentication of user, this method performs a redirect to the next step.
	 * If user does not exist, they are not allowed to login, or fails to authenticate, this method
	 * returns a boolean false. If user does not have 2FA enabled, or is remembered from a previous 
	 * TFA login, then this method returns true, but user still needs to be authenticated. 
	 * 
	 * If preferred, you can ignore the return value, as this method will perform redirects whenever
	 * it needs to move on to the next 2FA step. 
	 * 
	 * @param string $name
	 * @param string $pass
	 * @return bool
	 * 
	 */
	public function ___start($name, $pass) {

		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		/** @var Session $session */
		$session = $this->wire('session');
		$name = $sanitizer->pageName($name);
		$this->sessionReset();

		/** @var User $user */
		$user = $this->wire()->users->get('name=' . $sanitizer->selectorValue($name));
		
		// unknown user
		if(!$user || !$user->id) return false;
		
		// check if user is not allowed to login
		if(!$session->allowLogin($user->name, $user)) return false;
		
		// check if user exists but does not have 2FA enabled
		$tfaModule = $this->getModule($user); /** @var Tfa $tfaModule */
		if(!$tfaModule) return true;
		
		$settings = $tfaModule->getUserSettings($user);
		if(!$tfaModule->enabledForUser($user, $settings)) return true;
		
		// check if user name and pass authenticate
		if(!$session->authenticate($user, $pass)) return false;
		
		if($tfaModule->rememberDays && $tfaModule->remember($user, $settings)->remembered()) {
			if($this->wire()->config->debug) {
				$this->message(
					$this->_('Code was not required because the browser was recognized from a previous login.'),
					Notice::noGroup
				);
			}
			return true;
		}

		// at this point user has successfully authenticated with given name and pass
		if($tfaModule->startUser($user, $settings)) {
			$key = $this->getSessionKey(true);
			$this->redirect("$this->keyName=$key"); 
		} else {
			$this->error($this->_('Error creating or sending authentication code'));
			$this->redirect($this->startUrl);
		}
		
		return false; // note: statement cannot be reached due to redirects above
	}

	/**
	 * Start two-factor authentication for User
	 * 
	 * Modules must implement this method unless they do not need to generate or send 
	 * authentication codes to the user. Below are details on how to implement this 
	 * method:
	 * 
	 * A. For modules that generate and validate their own authentication codes:
	 *    1. Generate an authentication code for user
	 *    2. Save the code to session
	 *    3. Send the code to the user via whatever TFA channel is used
	 *    4. Call parent::startUser($user)
	 *    5. Return true (if no errors)
	 * 
	 * B. For modules that use an external service to generate, send and validate codes:
	 *    1. Call on the external service to generate and the code to user
	 *    2. Call parent::startUser($user)
	 *    3. Return true (if no errors)
	 * 
	 * C. Modules that do not generate or send codes, but only validate them (i.e. TOTP): 
	 *    You can omit implementation, leaving just the built-in one below. 
	 *    But if you do implement it, make sure you call the parent::startUser($user).
	 * 
	 * @param User $user
	 * @param array $settings Settings configured by user
	 * @return bool True on success, false on fail
	 * 
	 */
	public function startUser(User $user, array $settings) {
		if($settings) {} // ignore
		$this->sessionSet(array(
			'id' => $user->id,
			'name' => $user->name,
			'type' => $this->className(),
			'time' => time(),
		));
		return true; 
	}
	
	/**
	 * Return true if code is valid or false if not
	 *
	 * Modules MUST implement this method.
	 *
	 * @param User $user
	 * @param string|int $code
	 * @param array $settings User configured TFA settings
	 * @return bool|int Returns true if valid, false if not, or optionally integer 0 if code was valid but is now expired
	 * @throws WireException
	 *
	 */
	public function isValidUserCode(User $user, $code, array $settings) {
		if($user && $code && $settings) throw new WireException('Modules should not call this method');
		return false;
	}

	/**
	 * Returns true if a TFA process is currently active
	 * 
	 * - This method should be called if $tfa->success() returns false. 
	 * - If this method returns true, you should `echo $tfa->render()` which will
	 *   render the auth code form. 
	 * - If this method returns false and login/pass submitted, then call `$tfa->start()`,
	 *   or if login not submitted, then render login form. 
	 * 
	 * @return bool
	 * 
	 */
	public function active() {
		return $this->wire('input')->get($this->keyName) === $this->getSessionKey();
	}

	/**
	 * Programmatically enable this TFA module for given user
	 * 
	 * This can only be used if the Tfa module supports it (i.e. TfaEMail does but TfaTotp cannot).
	 * Returns false when not supported by the module. When a module supports it, it should include
	 * a parent::enableForUser() call that includes the $settings argument. 
	 * 
	 * @param User $user
	 * @param array $settings For internal use, should only be specified on a parent::setEnabledForUser() call.
	 * @return bool
	 * @since 3.0.159
	 * 
	 */
	public function enableForUser(User $user, array $settings = array()) {
		$moduleName = $this->className();
		if($moduleName === 'Tfa') throw new WireException('This method may only be called on a Tfa module');
		if(empty($settings)) return false; // module lacks auto-configure support for this
		$user->setAndSave($this->userFieldName, $moduleName); 
		$settings['enabled'] = true;
		$this->saveUserSettings($user, $settings); 
		return true;
	}

	/**
	 * Programmatically disable this TFA module for given user
	 * 
	 * @param User $user
	 * @return bool
	 * @throws WireException
	 * @since 3.0.159
	 * 
	 */
	public function disableForUser(User $user) {
		$moduleName = $this->className();
		if($moduleName === 'Tfa') throw new WireException('This method may only be called on a Tfa module');
		if($user->get($this->userFieldName) != $moduleName) return false;
		$user->setAndSave($this->userFieldName, ''); 
		$settings = array('enabled' => false); 
		$this->saveUserSettings($user, $settings);
		return true;
	}

	/**
	 * Is TFA enabled for given user?
	 * 
	 * This method should be implemented by descending module to perform whatever
	 * check is needed to verify that the user has enabled TFA. 
	 * 
	 * @param User $user
	 * @param array $settings
	 * @return bool
	 * 
	 */
	public function enabledForUser(User $user, array $settings) {
		if($user) {} // ignore
		$enabled = empty($settings['enabled']) ? false : true;
		return $enabled;
	}
	
	/**
	 * Returns true when TFA has successfully completed and user is now logged in
	 * 
	 * Note that this method functions as part of the TFA flow control and will
	 * perform redirects during processing. 
	 * 
	 * @return bool
	 * 
	 */
	public function success() {
		if(!$this->active()) return false;
		/** @var Tfa $module */
		$module = $this->getModule();
		if(!$module) return false;
		$result = $module->process(); // redirects may occur
		if($result && $result instanceof User && $result->id) return true; 
		return false;
	}

	/**
	 * Get the TFA module for given user or current session
	 * 
	 * @param User $user Optionally specify user
	 * @return Tfa|null
	 * 
	 */
	public function getModule(User $user = null) {
	
		$module = null;
		$moduleName = $this->sessionGet('type');
		
		if($user) {
			$module = $user->get($this->userFieldName);
		} else if($moduleName && $moduleName === $this->className()) {
			$module = $this;
		} else if($moduleName) {
			$module = $this->wire()->modules->getModule($moduleName);
		} else {
			$user = $this->getUser();
			if($user) $module = $user->get($this->userFieldName);
		}
		
		if($module && !$module instanceof Tfa) $module = null;
		
		if($module) {
			/** @var Tfa $module */
			$module->rememberDays = $this->rememberDays;
			$module->rememberFingerprints = $this->rememberFingerprints;
		}
			
		return $module;
	}


	/**
	 * Get a unique key that can be used in the “tfa” GET variable used by this module
	 * 
	 * @param bool $reset Reset to new key?
	 * @return string
	 *
	 */
	protected function getSessionKey($reset = false) {
		$key = $this->sessionGet('key');
		if(empty($key) || $reset) {
			$rand = new WireRandom();
			$key = $rand->alphanumeric(20);
			$this->sessionSet('key', $key);
		}
		return $key;
	}
	
	/**
	 * Build the form used for two-factor authentication
	 * 
	 * This form typically appears on the screen after the user has submitted their login info
	 * 
	 * At minimum it must have an Inputfield with name “tfa_code”
	 *
	 * @return InputfieldForm
	 *
	 */
	public function ___buildAuthCodeForm() {
		
		if($this->authCodeForm) return $this->authCodeForm;
		
		/** @var Modules $modules */
		$modules = $this->wire('modules');
		
		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		foreach($this->formAttrs as $name => $value) {
			$form->attr($name, $value);
		}
		
		$form->attr('action', $this->url($this->keyName . '=' . $this->getSessionKey(true)));

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		foreach($this->inputAttrs as $name => $value) {
			$f->attr($name, $value);
		}
		$f->attr('name', 'tfa_code');
		$f->label = $this->inputLabel; // Authentication code
		$f->attr('required', 'required');
		$f->collapsed = Inputfield::collapsedNever;
		$form->add($f);
		
		if($this->rememberDays) {
			$f = $modules->get('InputfieldCheckbox');
			$f->attr('id+name', 'tfa_remember');
			$f->attr('value', $this->rememberDays);
			$f->label = $this->rememberLabel;
			$f->collapsed = Inputfield::collapsedNever;
			$form->add($f);
		}

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit');
		foreach($this->submitAttrs as $name => $value) {
			$f->attr($name, $value);
		}
		$f->attr('name', 'tfa_submit');
		if($this->submitLabel) $f->val($this->submitLabel);
		$form->add($f);

		if($this->showCancel) {
			$cancelUrl = $this->url();
			$cancelLabel = $this->sanitizer->entities1($this->cancelLabel);
			$form->appendMarkup .= "<p><a href='$cancelUrl'>$cancelLabel</a></p>";
		}
	
		/*
		 * replaced with input autofocus attribute
		$form->appendMarkup .= 
			"<script>if(typeof jQuery!='undefined') jQuery(document).ready(function(){jQuery('#login_name').focus();});</script>";
		*/
		
		$this->authCodeForm = $form;

		return $form;
	}

	/**
	 * Render the code input form
	 * 
	 * @return string
	 * 
	 */
	public function ___render() {
		// $this->message($this->_('Please enter your authentication code to complete login.'));
		if($this->className() == 'Tfa') {
			// make sure we call the render from the module that implements TFA
			$module = $this->getModule();
			if($module) return $module->render();
		}
		$form = $this->buildAuthCodeForm();
		return $form->render();
	}

	/**
	 * Process two-factor authentication 
	 * 
	 * This method processes the submission of the form containing “tfa_code”.
	 * Note that this method will perform redirects as needed. 
	 * 
	 * @return User|bool Returns logged-in user object on successful code completion, or false on fail
	 * 
	 */
	public function ___process() {

		/** @var WireInput $input */
		$input = $this->wire('input');
		
		/** @var Session $session */
		$session = $this->wire('session');
	
		/** @var Config $config */
		$config = $this->wire('config');
	
		/** @var string|null $key */
		$key = $input->get($this->keyName);
		
		// invalid key, abort 
		if(empty($key) || $key !== $this->getSessionKey() || $this->wire('user')->isLoggedin()) {
			return $this->sessionReset('./');
		}
		
		unset($key);
		$form = $this->buildAuthCodeForm();
		$userID = (int) $this->sessionGet('id');
		$userName = $this->sessionGet('name');
		$user = $userID ? $this->wire('users')->get($userID) : null;
		$initTime = (int) $this->sessionGet('time');
		$numTries = (int) $this->sessionGet('tries');
		$maxTries = 3;
		$fail = false;

		if(!$user || !$userID || !$user->id || $userID !== $user->id || $user->name !== $userName) {
			// unable to find user or name did not match (not likely to ever occur, but just in case)
			$fail = true;
		} else if($numTries > $maxTries) {
			// user has exceeded the max allowed attempts for this login
			$this->error($this->_('Max attempts reached'));
			$fail = true;
		} else if(!$initTime || (time() - $initTime > 180)) {
			// more than 3 minutes have passed since authentication, so make them start over
			$this->error($this->_('Time limit reached'));
			$fail = true;
		}

		// if fail, exit and remove any 2FA related session variables that were set 
		if($fail) return $this->sessionReset('./');

		// no code submitted, caller should call $tfa->render()
		if(!$input->post('tfa_code')) return false;
		
		// code submitted: validate code and set to blank if not valid format
		$form->processInput($input->post);
		$code = $form->getChildByName('tfa_code')->val();
		$codeHash = sha1($code . $user->id . substr($config->userAuthSalt, 0, 9));

		// at this point, a code has been submitted
		$this->sessionSet('tries', ++$numTries);

		// validate code
		$settings = $this->getUserSettings($user);
		$valid = $this->isValidUserCode($user, $code, $settings); 
		
		if($valid === true && isset($settings['last_code'])) { 
			if($codeHash === $settings['last_code']) {
				// do not allow same code to be reused, just in case traffic is being intercepted
				// and chosen Tfa module does not already handle this case
				$valid = 0;
			}
		}
		
		if($valid === true) {
			// code is validated, so do a forced login since user is already authenticated
			$this->sessionReset();
			$user = $session->forceLogin($user);
			if($user && $user->id && "$user->id" === "$userID") {
				// code successfully validated and user is now logged in
				$settings = $this->getUserSettings($user); // get fresh
				$settings['last_code'] = $codeHash;
				if($this->rememberDays && $input->post('tfa_remember') == $this->rememberDays) {
					if($this->remember($user, $settings)->enable()) {
						$this->message(
							sprintf($this->_('This computer/browser is now remembered for up to %d days.'), $this->rememberDays) . ' ' . 
							$this->_('Changes to browser and/or location may require a new code.'),
							Notice::noGroup);
					}
				} else {
					$this->saveUserSettings($user, $settings);
				}
				return $user;
			} else {
				// not likely for login to fail here, since they were already authenticated before
				$this->redirect($this->startUrl);
			}
		} else {
			// failed validation
			if($valid === 0) {
				$this->error($this->_('Expired code'));
			} else {
				$this->error($this->_('Invalid code'));
			}
			// will ask them to try again
			$this->redirect("$this->keyName=" . $this->getSessionKey());
		}

		return false;
	}
	
	/*** CONFIG ********************************************************************************************/

	/**
	 * Get fields needed for a user to configure and confirm TFA from their user profile
	 * 
	 * This method should be implemented by each TFA module
	 * 
	 * @param User $user
	 * @param InputfieldWrapper $fieldset
	 * @param array $settings
	 * 
	 */
	public function ___getUserSettingsInputfields(User $user, InputfieldWrapper $fieldset, $settings) {
		if($user || $fieldset || $settings) {} // ignore
		$fieldset->icon = 'user-secret';
		$fieldset->attr('id+name', '_tfa_settings');
		/*
		$f = $this->modules->get('InputfieldMarkup');
		$f->attr('name', 'test');
		$f->label = 'Hello world';
		$f->value = "<p>This is a test.</p>";
		$fieldset->add($f);
		*/
	}

	/**
	 * Called when the user config fieldset has been processed but before $settings have been saved
	 * 
	 * @param User $user
	 * @param InputfieldWrapper $fieldset
	 * @param array $settings Associative array of new/current settings after processing
	 * @param array $settingsPrev Associative array of previous settings
	 * @return array Return $newSettings array (modified as needed)
	 * 
	 */
	public function ___processUserSettingsInputfields(User $user, InputfieldWrapper $fieldset, $settings, $settingsPrev) {
		if($user || $fieldset || $settings || $settingsPrev) {} // ignore
		return $settings;
	}

	/**
	 * Module configuration 
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		if($inputfields) {} // ignore
	}
	
	/*** SESSION *******************************************************************************************/

	/**
	 * Get a session variable for this module
	 * 
	 * @param string $key
	 * @param mixed $blankValue Optionally provide replacement blank value if session var does not exist.
	 * @return mixed|null
	 * 
	 */
	protected function sessionGet($key, $blankValue = null) {
		$value = $this->wire('session')->getFor($this->keyName, $key);
		if($value === null) $value = $blankValue;
		return $value;
	}

	/**
	 * Set a session variable only for this module
	 * 
	 * Optionally set several variables at once by specifying just $key as an associative array.
	 * 
	 * @param string|array $key
	 * @param mixed $value
	 * 
	 */
	protected function sessionSet($key, $value = null) {
		/** @var Session $session */
		$session = $this->wire('session');
		$values = is_array($key) ? $key : array($key => $value);
		foreach($values as $k => $v) {
			$session->setFor($this->keyName, $k, $v);
		}
	}

	/**
	 * Remove all session variables set for this module
	 * 
	 * @param string $redirectURL Optionally redirect to URL after reset
	 * @return bool|string|int
	 * 
	 */
	protected function sessionReset($redirectURL = '') {
		$this->wire('session')->removeAllFor($this->keyName);
		if($redirectURL) $this->redirect($redirectURL);
		return false;
	}
	
	
	/*** USER AND SETTINGS *******************************************************************************/

	/**
	 * Get default/blank user settings
	 * 
	 * Descending modules should implement this method. 
	 * 
	 * @param User $user
	 * @return array
	 * 
	 */
	protected function getDefaultUserSettings(User $user) {
		if($user) {}
		return array(
			'enabled' => false, // whether user has this auth method enabled
		);
	}
	
	/**
	 * Get TFA data for given user from user_tfa field
	 *
	 * @param User $user
	 * @return array
	 * @throws WireException
	 *
	 */
	public function getUserSettings(User $user) {
		$className = $this->className();
		
		if($className === 'Tfa') {
			throw new WireException('getUserSettings should only be called from Module instance');
		}
	
		$tfaSettings = $user->get('_tfa_settings');
		if(!empty($tfaSettings[$className])) return $tfaSettings[$className];
		
		$defaults = $this->getDefaultUserSettings($user);
		
		$field = $this->wire('fields')->get($this->userFieldName);
		if(!$field) return $defaults;
		
		$value = $user->get($field->name);
		if(empty($value)) return $defaults; // no tfa_type is selected by user
		
		$table = $field->getTable();
		$sql = "SELECT `settings` FROM `$table` WHERE pages_id=:user_id";
		$query = $this->wire('database')->prepare($sql);
		$query->bindValue(':user_id', $user->id, \PDO::PARAM_INT);
		$query->execute();
		$data = $query->fetchColumn();
		$query->closeCursor();
		
		if(empty($data)) {
			$tfaSettings = array($className => $defaults);
		} else {
			$tfaSettings = json_decode($data, true);
			if(!is_array($tfaSettings)) $tfaSettings = array();
			if(isset($tfaSettings[$className])) {
				$tfaSettings[$className] = array_merge($defaults, $tfaSettings[$className]);
			} else {
				$tfaSettings[$className] = $defaults;
			}
		}

		$user->setQuietly('_tfa_settings', $tfaSettings);
		
		return $tfaSettings[$className]; 
	}

	/**
	 * Save TFA data for given user to user_tfa field
	 *
	 * @param User $user
	 * @param array $settings
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function saveUserSettings(User $user, array $settings) {
		$className = $this->className();
		if($className === 'Tfa') throw new WireException('Method may only be called from module');
		if(!empty($settings[$className])) $settings = $settings[$className]; // just in case it is $tfaSettings
		$tfaSettings = array($className => $settings);
		$user->setQuietly('_tfa_settings', $tfaSettings);
		$field = $this->wire('fields')->get($this->userFieldName);
		if(!$field) return false;
		if(!$user->get($field->name)) return false; // no module selected
		$table = $field->getTable();
		$json = json_encode($tfaSettings);
		$sql = "UPDATE `$table` SET `settings`=:json WHERE pages_id=:user_id";
		$query = $this->wire('database')->prepare($sql);
		$query->bindValue(':user_id', $user->id, \PDO::PARAM_INT);
		$query->bindValue(':json', $json);
		return $query->execute();
	}
	
	/**
	 * Get current user for TFA
	 *
	 * @return User
	 *
	 */
	public function getUser() {

		$user = null;

		if($this->wire('user')->isLoggedin()) {
			// if user is logged in, user can be current user or one being edited
			$process = $this->wire('process');

			// if process API variable not adequate, attempt to get from current page
			if(!$process || $process == 'ProcessPageView') {
				$page = $this->wire('page');
				$process = $page->get('process');
			}

			// check if we have a process
			if($process && $process instanceof Process) {
				// user being edited like in ProcessUser, ProcessProfile or ProcessPageEdit
				if($process instanceof WirePageEditor) {
					$user = $process->getPage();
				}
			}

		} else {
			// user is not yet logged in, get from session data if available
			$userID = $this->sessionGet('id');
			$userName = $this->sessionGet('name');
			if($userID && $userName) {
				$user = $this->wire('users')->get((int) $userID);
				if($user && (!$user->id || $user->name !== $userName)) $user = null;
			}
		}

		// if not a user being edited, user can only be current user
		if(!$user || !$user instanceof User || !$user->id) {
			$user = $this->wire('user');
		}

		return $user;
	}


	/*** HOOKS **********************************************************************************************/

	/**
	 * Attach/initialize hooks used by this module
	 * 
	 */
	protected function initHooks() {
		$this->addHookBefore('InputfieldForm::render', $this, 'hookInputfieldFormRender');
		$this->addHookBefore('InputfieldForm::processInput', $this, 'hookBeforeInputfieldFormProcess');
		$this->addHookAfter('InputfieldForm::processInput', $this, 'hookAfterInputfieldFormProcess');
	}
	
	/**
	 * Hook before InputfieldForm::processInput()
	 *
	 * @param HookEvent $event
	 *
	 */
	public function hookBeforeInputfieldFormProcess(HookEvent $event) {

		/** @var InputfieldForm $form */
		$form = $event->object;
		
		// if this is some other form that does not have a “tfa_type” field, then exit
		$inputfield = $form->getChildByName($this->userFieldName);
		if(!$inputfield) return;
		
		$user = $this->getUser();
		if($user->isGuest()) {
			$inputfield->val(0);
			return;
		}

		// fieldset for TFA settings
		$fieldset = new InputfieldWrapper();
		$this->wire($fieldset);
		$settings = $this->getUserSettings($user);
		if(!$this->enabledForUser($user, $settings)) {
			$this->getUserSettingsInputfields($user, $fieldset, $settings);
		}
		
		if($this->wire()->input->post('_tfa_clear_remember')) {
			unset($settings['remember']);
			$changes['remember'] = 'remember';
			$this->remember($user, $settings)->disableAll();
			$this->message($this->_('Cleared remembered browsers'));
		}

		foreach($fieldset->getAll() as $f) {
			$name = $f->attr('name');
			if(strpos($name, '_tfa_') === 0) list(,$name) = explode('_tfa_', $name);
			$f->attr('name', "_tfa_$name");
		}
		
		$form->insertAfter($fieldset, $inputfield); 
	}

	/**
	 * Hook after InputfieldForm::processInput() 
	 * 
	 * This method grabs data from the TFA related fields added by our render() hooks,
	 * and saves them in the user’s “tfa_type” field “settings” column.
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookAfterInputfieldFormProcess(HookEvent $event) {

		/** @var InputfieldForm $form */
		$form = $event->object;

		// if this is some other form that does not have a “tfa_type” field, then exit
		$inputfield = $form->getChildByName($this->userFieldName);
		if(!$inputfield) return;
		if(!$inputfield->val()) {
			// reset settinge
			return;
		}
	
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $form->getChildByName('_tfa_settings');
		$user = $this->getUser();
		if($user->isGuest()) {
			$inputfield->val(0);
			return;
		}
		
		$settingsPrev = $this->getUserSettings($user);
		$settings = $settingsPrev;
		$changes = array();
		
		if($this->enabledForUser($user, $settings)) return;

		foreach($fieldset->getAll() as $f) {
			$name = $f->attr('name');
			if(strpos($name, '_tfa_') === 0) list(,$name) = explode('_tfa_', $name);
			$settings[$name] = $f->val();
		}
		
		$settings = $this->processUserSettingsInputfields($user, $fieldset, $settings, $settingsPrev);
		
		foreach($settings as $name => $value) {
			if(!isset($settingsPrev[$name]) || $settingsPrev[$name] !== $settings[$name]) {
				$changes[$name] = $name;
			}
		}
		foreach($settingsPrev as $name => $value) {
			if(!isset($settings[$name])) $changes[$name] = $name;
		}
		
		if(count($changes)) {
			// $this->message("TFA settings changed: " . implode(', ', $changes), Notice::debug);
			$this->saveUserSettings($user, $settings);
		}
	}

	/**
	 * Hook before InputfieldForm::render()
	 * 
	 * This method adds the fields configured in getUserSettingsInputfields() and adds
	 * them to the form being rendered, but only if the form already has a field
	 * named “tfa_type”. It also pulls the settings stored in that field, and 
	 * populates the module-specific configuration fields. 
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookInputfieldFormRender(HookEvent $event) {
		
		/** @var InputfieldWrapper $inputfields */
		$inputfields = $event->object;
		
		// if form does not have a “tfa_type” field, then exit
		$inputfield = $inputfields->getChildByName($this->userFieldName);
		if(!$inputfield) return;
		if(!$inputfield->val()) return;
		
		/** @var Modules $modules */
		$modules = $event->wire('modules');
		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		$user = $this->getUser();
		
		if($user->isGuest()) {
			$inputfield->val(0);
			return;
		}

		$tfaTitle = $modules->getModuleInfoProperty($this, 'title');
		$settings = $this->getUserSettings($user);
		$enabled = $this->enabledForUser($user, $settings);
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $tfaTitle;
		$fieldset->showIf = "$this->userFieldName=" . $this->className();
	
		if($enabled) {
			$fieldset->label .= ' - ' . $this->_('ENABLED'); 
			$fieldset->icon = 'user-secret';
			$fieldset->description = 
				$this->_('Two factor authentication enabled!') . ' ' . 
				$this->_('To disable or change settings, select the “None” option above and save.');
			$fieldset->collapsed = Inputfield::collapsedYes;
		
			if(!empty($settings['remember'])) {
				/** @var InputfieldCheckbox $f */
				$f = $modules->get('InputfieldCheckbox');
				$f->attr('name', '_tfa_clear_remember');
				$f->label = $this->_('Clear remembered browsers that skip entering authentication code');
				$fieldset->add($f);
			}
			
			$this->wire('session')->removeFor('_user', 'requireTfa'); // set by ProcessLogin
		} else {
			/** @var InputfieldFieldset $fieldset */
			$this->getUserSettingsInputfields($user, $fieldset, $settings);
			if(!$this->wire('input')->requestMethod('POST')) {
				$this->warning(
					'<strong>' . $sanitizer->entities1($this->_('Please configure')) . '</strong> ' .   
					wireIconMarkup('angle-right') . ' ' . 
					"<a href='#wrap_Inputfield_tfa_type'>" . $sanitizer->entities1($tfaTitle) . "</a>",
					Notice::allowMarkup 
				);
			}
		}
		
		$inputfield->getParent()->insertAfter($fieldset, $inputfield);

		foreach($fieldset->getAll() as $f) {
			$name = $f->attr('name');
			if(isset($settings[$name])) $f->val($settings[$name]);
			if(strpos($name, '_tfa_') !== 0) $f->attr('name', "_tfa_$name");
		}
	}

	/*** INSTALL AND UNINSTALL ******************************************************************************/

	/**
	 * Module module and other assets required to execute it
	 * 
	 */
	public function ___install() {
		
		$fieldName = $this->userFieldName;
		$field = $this->wire('fields')->get($fieldName);
		
		if(!$field) {
			$field = new Field();
			$this->wire($field);
			$field->name = $fieldName;
			$field->label = $this->_('2-factor authentication type');
			$field->type = $this->wire('fieldtypes')->get('FieldtypeModule');
			$field->flags = Field::flagSystem;
			$field->description = 'After making or changing a selection, submit the form and return here to configure it.';
			$field->icon = 'user-secret';
			$field->set('moduleTypes', array('Tfa'));
			$field->set('instantiateModule', 1);
			$field->set('showNoneOption', 1);
			$field->set('labelField', 'title-summary');
			$field->set('inputfieldClass', 'InputfieldRadios');
			$field->set('blankType', 'zero');
			$this->wire('fields')->save($field);
			$this->message("Added field: $field->name", Notice::debug);
			// add a custom “settings” column to the field
			$table = $field->getTable();
			$this->wire('database')->exec("ALTER TABLE `$table` ADD `settings` MEDIUMTEXT");
		}
	
		// add user_tfa field to all user template fieldgroups
		foreach($this->wire('config')->userTemplateIDs as $templateID) {
			$template = $this->wire('templates')->get($templateID);
			if(!$template) continue;
			if($template->fieldgroup->hasField($field)) continue;
			$template->fieldgroup->add($field);
			$template->fieldgroup->save();
		}
	
		// add user_tfa as field editable in user profile
		$data = $this->wire('modules')->getConfig('ProcessProfile');
		if(!isset($data['profileFields'])) $data['profileFields'] = array();
		if(!in_array($fieldName, $data['profileFields'])) {
			$data['profileFields'][] = $fieldName;
			$this->wire('modules')->saveConfig('ProcessProfile', $data);
		}
	}

	/**
	 * Uninstall
	 * 
	 */
	public function ___uninstall() {
		
		$tfaModules = $this->wire('modules')->findByPrefix('Tfa');	
		unset($tfaModules[$this->className()]); 
		if(count($tfaModules)) return;
		
		// no more TFA modules installed, so assets can be removed
		$fieldName = $this->userFieldName;
		$field = $this->wire('fields')->get($fieldName);
		if(!$field) return;
		$field->addFlag(Field::flagSystemOverride);
		$field->removeFlag(Field::flagSystem);

		// remove user_tfa field from all user template fieldgroups
		foreach($this->wire('config')->userTemplateIDs as $templateID) {
			$template = $this->wire('templates')->get($templateID);
			if(!$template) continue;
			if(!$template->fieldgroup->hasField($field)) continue;
			$template->fieldgroup->remove($field);
			$template->fieldgroup->save();
			$this->message("Removed $field from $template", Notice::debug);
		}

		// completely delete the user_tfa field
		$this->wire('fields')->delete($field);

		// remove user_tfa as field editable in user profile
		$data = $this->wire('modules')->getConfig('ProcessProfile');
		if(!empty($data) && is_array($data['profileFields'])) {
			$key = array_search($fieldName, $data['profileFields']);
			if($key !== false) {
				unset($data['profileFields'][$key]);
				$this->wire('modules')->saveConfig('ProcessProfile', $data);
				$this->message("Removed $fieldName from user profile editable fields", Notice::debug);
			}
		}
	}
	
}

/**
 * Manages the “remember me” feature for Tfa class
 * 
 * Accessed from $tfaInstance->remember($user, $settings)->method().
 * This class is kept in Tfa.php because it cannot be instantiated without
 * a Tfa instance. 
 * 
 * @method array getFingerprintArray($getLabels = false)
 * 
 * #pw-internal
 * 
 */
class RememberTfa extends Wire {

	/**
	 * Shows debug info in warning messages, only for development
	 * 
	 */
	const debug = false;

	/**
	 * Max browsers to remember for any user
	 * 
	 */
	const maxItems = 10; 

	/**
	 * @var Tfa
	 * 
	 */
	protected $tfa;

	/**
	 * @var User|null
	 * 
	 */
	protected $user = null;

	/**
	 * @var array
	 * 
	 */
	protected $settings = array();

	/**
	 * @var array
	 * 
	 */
	protected $remember = array();

	/**
	 * Days to remember
	 * 
	 * @var int
	 * 
	 */
	protected $days = 90;

	/**
	 * Means by which to fingerprint user (extras on top of random remembered cookie)
	 * 
	 * Options: agent, agentVL, accept, scheme, host, ip, fwip
	 * 
	 * @var array
	 * 
	 */
	protected $fingerprints = array(
		'agentVL', 
		'accept', 
		'scheme', 
		'host',
	);

	/**
	 * Construct
	 *
	 * @param User $user
	 * @param Tfa $tfa
	 * @param array $settings
	 * 
	 */
	public function __construct(Tfa $tfa, User $user, array $settings) {
		$this->tfa = $tfa;
		$tfa->wire($this);
		$this->user = $user;
		$this->settings = $settings;
		if(isset($settings['remember'])) $this->remember = $settings['remember'];
		parent::__construct();
	}

	/**
	 * Set days to remember between logins
	 * 
	 * @param int $days
	 * 
	 */
	public function setDays($days) {
		$this->days = (int) $days;
	}

	/**
	 * Fingerprints to use for newly created "remember" items
	 * 
	 * @param array $fingerprints
	 * 
	 */
	public function setFingerprints(array $fingerprints) {
		$this->fingerprints = $fingerprints;
	}

	/**
	 * Save Tfa 'remember' settings
	 * 
	 * @return bool
	 * 
	 */
	protected function saveRemember() {
		if(count($this->remember)) {
			$this->settings['remember'] = $this->remember;
		} else {
			unset($this->settings['remember']); 
		}
		return $this->tfa->saveUserSettings($this->user, $this->settings);
	}

	/**
	 * Set combination of user/browser/host/page as remembered and allowed to skip TFA
	 *
	 * @return bool
	 *
	 */
	public function enable() {
		
		if(!$this->days) return false;
	
		$rand = new WireRandom();
		$this->wire($rand);
		$cookieValue = $rand->alphanumeric(0, array('minLength' => 40, 'maxLength' => 256));
		$qty = count($this->remember);
		
		if($qty > self::maxItems) {
			$this->remember = array_slice($this->remember, $qty - self::maxItems);
		}
		
		do {
			$name = $rand->alpha(0, array('minLength' => 3, 'maxLength' => 7));
		} while(isset($this->remember[$name]) || $this->getCookie($name) !== null);
		
		$this->remember[$name] = array(
			'fingerprint' => $this->getFingerprintString(),
			'created' => time(),
			'expires' => strtotime("+$this->days DAYS"),
			'value' => $this->serverValue($cookieValue), 
			'page' => $this->wire()->page->id,
		);
		
		$this->debugNote("Enabled new remember: $name"); 
		$this->debugNote($this->remember[$name]); 

		$result = $this->saveRemember();
		if($result) $this->setCookie($name, $cookieValue);

		return $result;
	}

	/**
	 * Is current user/browser/host/URL one that is remembered and TFA can be skipped?
	 *
	 * @param bool $getName Return remembered cookie name rather than true? (default=false)
	 * @return bool|string
	 *
	 */
	public function remembered($getName = false) {
		
		if(!$this->days) return false;

		$page = $this->wire()->page;
		$fingerprint = $this->getFingerprintString();
		$valid = false;
		$validName = '';
		$disableNames = array();
		
		foreach($this->remember as $name => $item) {
		
			// skip any that do not match current page
			if("$item[page]" !== "$page->id") {
				$this->debugNote("Skipping $name because page: $item[page] != $page->id"); 
				continue;
			}
			
			if(!empty($item['expires']) && time() >= $item['expires']) {
				$this->debugNote("Skipping $name because it has expired (expires=$item[expires])");
				$disableNames[] = $name;
				continue;
			}

			$cookieValue = $this->getCookie($name);

			// skip any where cookie value isn't present
			if(empty($cookieValue)) {
				// if cookie not present on this browser skip it because likely for another browser the user has
				$this->debugNote("Skipping $name because cookie not present"); 
				continue;
			}
			
			// skip and remove any that do not match current browser fingerprint
			if(!$this->fingerprintStringMatches($item['fingerprint'])) {
				$fingerprintTypes = $this->getFingerprintTypes($item['fingerprint']); 
				if(!isset($fingerprintTypes['ip']) && !isset($fingerprintTypes['fwip'])) {
					// if IP isn't part of fingerprint then it is okay to remove this entry because browser can no longer match
					$disableNames[] = $name;
				}
				$this->debugNote("Skipping $name because fingerprint: $item[fingerprint] != $fingerprint");
				continue;
			}

			// cookie found, now validate it
			$valid = $item['value'] === $this->serverValue($cookieValue);

			if($valid) {
				// cookie is valid, now refresh it, resetting its expiration
				$this->debugNote("Valid remember: $name"); 
				$this->setCookie($name, $cookieValue);
				$validName = $name;
				break;
			} else {
				// clear because cookie value populated but is not correct
				$this->debugNote("Skipping $name because cookie does not authenticate with server value"); 
				$disableNames[] = $name;
			}
		}
		
		if(count($disableNames)) $this->disable($disableNames); 

		return ($getName && $valid ? $validName : $valid);
	}
	
	/**
	 * Disable one or more cookie/remembered client by name(s)
	 * 
	 * @param array|string $names
	 * @return bool
	 * 
	 */
	public function disable($names) {
		if(!is_array($names)) $names = array($names); 
		$qty = 0;
		foreach($names as $name) {
			$found = isset($this->remember[$name]);
			if($found) unset($this->remember[$name]);
			if($this->clearCookie($name)) $found = true;
			if($found) $qty++;
			if($found) $this->debugNote("Disabling: $name");
		}
		if($qty) $this->saveRemember();
		return $qty;
	}

	/**
	 * Disable all stored "remember me" data for user 
	 *
	 * @return bool
	 *
	 */
	public function disableAll() {
		// remove cookies
		foreach($this->remember as $name => $item) {
			$this->clearCookie($name);
		}
		// remove from user settings
		$this->remember = array();
		$this->debugNote("Disabled all"); 
		return $this->saveRemember();
	}

	/**
	 * Get a "remember me" cookie value
	 * 
	 * @param string $name
	 * @return string|null
	 * 
	 */
	protected function getCookie($name) {
		$name = $this->cookieName($name);
		return $this->wire()->input->cookie->get($name);
	}
	
	/**
	 * Set the "remember me" cookie
	 *
	 * @param string $cookieName
	 * @param string $cookieValue
	 * @return WireInputData
	 *
	 */
	protected function setCookie($cookieName, $cookieValue) {
		$cookieOptions = array(
			'age' => ($this->days > 0 ? $this->days * 86400 : 31536000),
			'httponly' => true,
			'domain' => '',
		);
		if($this->config->https) $options['secure'] = true;
		$cookieName = $this->cookieName($cookieName);
		$this->debugNote("Setting cookie: $cookieName=$cookieValue"); 
		return $this->wire()->input->cookie->set($cookieName, $cookieValue, $cookieOptions);
	}

	/**
	 * Get cookie prefix
	 * 
	 * @return string
	 * 
	 */
	protected function cookiePrefix() {
		$config = $this->wire()->config;
		$cookiePrefix = $config->https ? $config->sessionNameSecure : $config->sessionName;
		if(empty($cookiePrefix)) $cookiePrefix = 'wire';
		return $cookiePrefix . '_';
	}

	/**
	 * Given name return cookie name
	 * 
	 * @param string $name
	 * @return string
	 * 
	 */
	protected function cookieName($name) {
		$prefix = $this->cookiePrefix();
		if(strpos($name, $prefix) !== 0) $name = $prefix . $name;
		return $name;
	}

	/**
	 * Clear cookie
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	protected function clearCookie($name) {
		$name = $this->cookiePrefix() . $name;
		$cookies = $this->wire()->input->cookie;
		if($cookies->get($name) === null) return false;
		$cookies->set($name, null, array()); // remove
		$this->debugNote("Clearing cookie: $name"); 
		return true;
	}

	/**
	 * Given a cookie value return equivalent expected server value 
	 * 
	 * @param string $cookieValue
	 * @param User|null $user
	 * @return string
	 * 
	 */
	protected function serverValue($cookieValue, User $user = null) {
		if($user === null) $user = $this->user;
		return sha1(
			$user->id . $user->name . $user->email . 
			substr(((string) $user->pass), 0, 15) . 
			substr($this->wire()->config->userAuthSalt, 0, 10) . 
			$cookieValue
		);
	}

	/**
	 * Get fingerprint of current browser, host and URL
	 * 
	 * Note that this is not guaranted unique, so is only a secondary security measure to
	 * ensure that remember-me record is married to an agent, scheme, and http host.
	 *
	 * @return array
	 *
	 */
	public function ___getFingerprintArray() {
		
		$agent = isset($_SERVER['HTTP_USER_AGENT']) ?  $_SERVER['HTTP_USER_AGENT'] : 'noagent';
		
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$fwip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else if(isset($_SERVER['HTTP_CLIENT_IP'])) {
			$fwip = $_SERVER['HTTP_CLIENT_IP'];
		} else {
			$fwip = 'nofwip';
		}
		
		$fingerprints = array(
			'agent' => $agent,
			'agentVL' => preg_replace('![^a-zA-Z]!', '', $agent), // versionless agent
			'accept' => (isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : 'noaccept'),
			'scheme' => ($this->config->https ? 'HTTPS' : 'http'),
			'host' => $this->config->httpHost,
			'ip' => (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'noip'),
			'fwip' => $fwip,
		);
		
		$fingerprint = array();
		
		foreach($this->fingerprints as $type) {
			$fingerprint[$type] = $fingerprints[$type];
		}
		
		$this->debugNote($fingerprint);
		
		return $fingerprint;
	}

	/**
	 * Get fingerprint string
	 *
	 * @param array $types Fingerprints to use, or omit when creating new
	 * @return string
	 *
	 */
	public function getFingerprintString(array $types = null) {
		if($types === null) $types = $this->fingerprints;
		return implode(',', $types) . ':' . sha1(implode("\n", $this->getFingerprintArray())); 
	}

	/**
	 * Does given fingerprint match one determined from current request?
	 * 
	 * @param string $fpstr Fingerprint to compare
	 * @return bool
	 * 
	 */
	protected function fingerprintStringMatches($fpstr) {
		$types = $this->getFingerprintTypes($fpstr);
		$fpnow = $types ? $this->getFingerprintString($types) : '';
		return ($fpstr && $fpnow && $fpstr === $fpnow);
	}

	/**
	 * Get the types used in given fingerprint string
	 * 
	 * @param string $fpstr
	 * @return array|bool
	 * 
	 */
	protected function getFingerprintTypes($fpstr) {
		if(strpos($fpstr, ':') === false) return false;
		list($types,) = explode(':', $fpstr, 2);
		$a = explode(',', $types);
		$types = array();
		foreach($a as $type) $types[$type] = $type;
		return $types;
	}

	/**
	 * Display debug note
	 * 
	 * @param string|array $note
	 * 
	 */
	protected function debugNote($note) {
		if(self::debug) $this->warning($note);
	}
	
}