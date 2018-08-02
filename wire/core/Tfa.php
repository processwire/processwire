<?php namespace ProcessWire;

/**
 * Tfa - Two Factor Authentication module base class
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
 *   // the above code performs a redirect if TFA is active for the user
 *   // place your regular code to login user here, which will be used if TFA is not active for the user
 * }
 * ~~~~~~
 * 
 * @method install()
 * @property int $codeLength Required length for authentication code (default=6)
 * @property int $codeExpire Codes expire after this many seconds (default=180)
 * @property int $codeType Type of TFA code to use, see codeType constants (default=0, which is Tfa::codeTypeDigits)
 * 
 */
class Tfa extends WireData implements Module, ConfigurableModule {

	/**
	 * Code type: digits only
	 * 
	 */
	const codeTypeDigits = 0;
	
	/**
	 * Code type: alphabetical letters only
	 */
	const codeTypeAlpha = 1;

	/**
	 * Code type: alphanumeric (letters and digits)
	 * 
	 */
	const codeTypeAlnum = 2;

	/**
	 * Name used for GET variable when TFA is active
	 * 
	 * @var string
	 * 
	 */
	protected $keyName = 'tfa';

	/**
	 * User that authenticated login and pass, but not necessarily code yet
	 * 
	 * @var User|null
	 * 
	 */
	protected $authUser = null;
	
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
	 * Cached result of getUserConfigInputfields()
	 * 
	 * @var InputfieldFieldset|null
	 * 
	 */
	protected $userConfigInputfields = null;

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		
		parent::__construct();
		
		if(!$this->wire('fields')->get($this->userFieldName)) {
			$this->install();
		}
		
		$this->initHooks();
		
		$this->set('codeLength', 6); 
		$this->set('codeExpire', 180); 
		$this->set('codeType', self::codeTypeDigits);
	}

	/**
	 * Start 2-factor authentication
	 * 
	 * On successful authentication of user, this method performs a redirect to the next step.
	 * If user does not exist, they are not allowed to login, or fails to authenticate, this method
	 * returns a boolean false. If user authenticates but simply does not have 2FA enabled,
	 * then this method returns true. 
	 * 
	 * If preferred, you can ignore the return value, as this method will perform redirects whenever
	 * it needs to move on to the next 2FA step. 
	 * 
	 * @param string $name
	 * @param string $pass
	 * @return bool
	 * 
	 */
	public function start($name, $pass) {

		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		/** @var Session $session */
		$session = $this->wire('session');
		$name = $sanitizer->pageName($name);
		$this->sessionReset();

		/** @var User $user */
		$user = $this->wire('users')->get("name=" . $sanitizer->selectorValue($name));
		
		// unknown user
		if(!$user || !$user->id) return false;
		
		// check if user is not allowed to login
		if(!$session->allowLogin($user->name, $user)) return false;
		
		// check if user exists but does not have 2FA enabled
		$tfaModule = $user->get($this->userFieldName);
		if(!$tfaModule || !$tfaModule->enabled($user)) return true;

		// check if user name and pass authenticate
		if(!$session->authenticate($user, $pass)) return false;

		// at this point user has successfully authenticated with given name and pass
		$this->authUser = $user;

		// generate new authentication code for user
		$code = $tfaModule->generateUserCode($user);
		
		if(strlen($code) && $tfaModule->sendUserCode($user, $code)) {
			$key = $this->getSessionKey(true);
			$this->sessionSet(array(
				'id' => $user->id,
				'name' => $user->name,
				'type' => $tfaModule->className(), 
				'time' => time(),
			));
			$session->redirect("./?$this->keyName=$key"); 
		} else {
			$this->error('Error creating or sending authentication code');
			$session->redirect('./');
		}
		
		return false; // note: statement cannot be reached due to redirects above
	}

	/**
	 * Returns true if TFA is active and process() should be called
	 * 
	 * @return bool
	 * 
	 */
	public function active() {
		return $this->wire('input')->get($this->keyName) === $this->getSessionKey();
	}

	/**
	 * Is TFA enabled for given user?
	 * 
	 * This method should be implemented by descending module to perform whatever
	 * check is needed to verify that the user has enabled TFA. 
	 * 
	 * @param User $user
	 * @return bool
	 * 
	 */
	public function enabled(User $user) {
		$settings = $this->getUserSettings($user);
		$enabled = empty($settings['enabled']) ? false : true;
		return $enabled;
	}

	/**
	 * Returns true when TFA has successfully completed
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
		if($user) return $user->get($this->userFieldName);
		$moduleName = $this->sessionGet('type');
		if($moduleName) {
			if($moduleName === $this->className()) return $this;
			return $this->wire('modules')->getModule($moduleName);
		} else {
			$user = $this->getUser();
			if($user) return $user->get($this->userFieldName);
		}
		return null;
	}

	/**
	 * Create unique two-factor authentication code for given $user
	 * 
	 * @param User $user
	 * @return string
	 * 
	 */
	public function generateUserCode(User $user) {
		$pass = new Password();
		
		if($this->codeType == self::codeTypeAlpha) {
			$code = $pass->randomAlnum($this->codeLength, array('numeric' => false));
		} else if($this->codeType == self::codeTypeAlnum) {
			$code = $pass->randomAlnum($this->codeLength);
		} else {
			$code = $pass->randomDigits($this->codeLength);
		}
		
		$expires = time() + $this->codeExpire;
		$this->saveUserCode($user, $code, $expires);
		
		return $code;
	}

	/**
	 * Send code to user, if applicable to the 2FA authentication method
	 * 
	 * @param User $user User to send to
	 * @param string $code Code to send
	 * @return bool Return true on success, false on fail
	 * 
	 */
	public function sendUserCode(User $user, $code) {
		if($user && $code) {} // ignore
		return true;
	}

	/**
	 * Save code to valid codes list in session
	 * 
	 * @param User $user
	 * @param string $code
	 * @param int $expires Omit to use module configured expires time
	 * 
	 */
	public function saveUserCode(User $user, $code, $expires = 0) {
		if($user) {} // ignore
		if(empty($expires)) $expires = $this->codeExpire;
		$codes = $this->sessionGet('codes');
		if(!is_array($codes)) $codes = array();
		$codes[] = array(
			'code' => $code,
			'expires' => $expires,
		);
		$this->sessionSet('codes', $codes);
	}

	/**
	 * Get array of codes that are valid (and not yet expired) that can be used for TFA for user
	 * 
	 * Note: if you implement your own isValidUserCode() method that does not need to call this method, 
	 * then this method will not be used and can be ignored. 
	 * 
	 * @param User $user
	 * @return array
	 * 
	 */
	protected function getValidUserCodes(User $user) {
		if($user) {} // ignore
		$time = time();
		$codes = $this->sessionGet('codes', array());
		$valid = array();
		foreach($codes as $key => $info) {
			if($time >= $info['expires']) {
				unset($codes[$key]);	
			} else if(!empty($info['code'])) {
				$valid[] = $info['code'];
			}
		}
		$this->sessionSet('codes', $codes);
		return $valid;
	}
	
	/**
	 * Return true if code is valid or false if not
	 *
	 * @param User $user
	 * @param string|int $code
	 * @return bool
	 *
	 */
	public function isValidUserCode(User $user, $code) {
		if($user) {} // ignore
		if(empty($code)) return false;
		$valid = false;
		foreach($this->getValidUserCodes($user) as $validCode) {
			if($validCode && $code === $validCode) {
				$valid = true;
				break;
			}
		}
		return $valid;
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
			$pass = new Password();
			$key = $pass->randomAlnum(20);
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
	protected function buildAuthCodeForm() {
		
		if($this->authCodeForm) return $this->authCodeForm;
		
		/** @var Modules $modules */
		$modules = $this->wire('modules');
		
		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		
		$form->attr('action', "./?$this->keyName=" . $this->getSessionKey(true));
		$form->attr('id', 'ProcessLoginForm');

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'tfa_code');
		$f->attr('id', 'login_name');
		$f->label = $this->_('Authentication Code');
		$f->attr('required', 'required');
		$f->collapsed = Inputfield::collapsedNever;
		$form->add($f);

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit');
		$f->attr('name', 'tfa_submit');
		$f->attr('id', 'Inputfield_login_submit');
		$form->add($f);

		$form->appendMarkup = "<p><a href='./'>" . $this->_('Cancel') . "</a></p>";
		$this->authCodeForm = $form;

		return $form;
	}

	/**
	 * Render the code input form
	 * 
	 * @return string
	 * 
	 */
	public function render() {
		$this->message($this->_('Please enter your authentication code to complete login.'));
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
	public function process() {

		/** @var WireInput $input */
		$input = $this->wire('input');
		
		/** @var Session $session */
		$session = $this->wire('session');
		
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

		// at this point, a code has been submitted
		$this->sessionSet('tries', ++$numTries);

		// validate code
		if($this->isValidUserCode($user, $code) === true) {
			// code is validated, so do a forced login since user is already authenticated
			$this->sessionReset();
			$user = $session->forceLogin($user);
			if($user && $user->id && $user->id == $userID) {
				// code successfully validated and user is now logged in
				return $user;
			} else {
				// not likely for login to fail here, since they were already authenticated before
				$session->redirect('./');
			}
		} else {
			// failed validation
			$this->error($this->_('Invalid code'));
			// will ask them to try again
			$session->redirect("./?$this->keyName=" . $this->getSessionKey());
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
	public function getUserConfigInputfields(User $user, InputfieldWrapper $fieldset, $settings) {
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
	public function processUserConfigInputfields(User $user, InputfieldWrapper $fieldset, $settings, $settingsPrev) {
		if($user || $fieldset || $settings || $settingsPrev) {} // ignore
		return $settings;
	}

	/**
	 * Module configuration 
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function ___getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		$inputfields->new('integer', 'codeLength', $this->_('Authentication code length'))
			->val($this->codeLength)
			->columnWidth(50);
		$inputfields->new('integer', 'codeExpire', $this->_('Code expiration (seconds)'))
			->val($this->codeExpire)
			->columnWidth(50);
		$inputfields->new('radios', 'codeType', $this->_('Type of code to use'))
			->val($this->codeType)
			->addOption(self::codeTypeDigits, $this->_('Digits [0-9]'))
			->addOption(self::codeTypeAlpha, $this->_('Alpha [A-Z]'))
			->addOption(self::codeTypeAlnum, $this->_('Alphanumeric [A-Z 0-9]'));
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
		if($redirectURL) $this->wire('session')->redirect($redirectURL);
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
			'enabled' => false
		);
	}
	
	/**
	 * Get TFA data for given user from user_tfa field
	 *
	 * @param User $user
	 * @return array
	 *
	 */
	public function getUserSettings(User $user) {
		
		$defaults = $this->getDefaultUserSettings($user);
		
		$field = $this->wire('fields')->get($this->userFieldName);
		if(!$field) return $defaults;
		
		$value = $user->get($field->name);
		if(empty($value)) return $defaults;
		
		$table = $field->getTable();
		$sql = "SELECT `settings` FROM `$table` WHERE pages_id=:user_id";
		$query = $this->wire('database')->prepare($sql);
		$query->bindValue(':user_id', $user->id, \PDO::PARAM_INT);
		$query->execute();
		$data = $query->fetchColumn();
		$query->closeCursor();
		
		if(empty($data)) {
			$settings = $defaults;
		} else {
			$settings = json_decode($data, true);
			if(!is_array($settings)) $settings = array();
			$settings = array_merge($defaults, $settings);
		}
		
		return $settings;
	}

	/**
	 * Save TFA data for given user to user_tfa field
	 *
	 * @param User $user
	 * @param array $settings
	 * @return bool
	 *
	 */
	public function saveUserSettings(User $user, array $settings) {
		$field = $this->wire('fields')->get($this->userFieldName);
		if(!$field) return false;
		if(!$user->get($field->name)) return false; // no module selected
		$table = $field->getTable();
		$json = json_encode($settings);
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
	protected function getUser() {

		$user = null;

		if($this->authUser) {
			// user that authenticated	
			$user = $this->authUser;

		} else if($this->wire('user')->isLoggedin()) {
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

		// fieldset for TFA settings
		if($this->userConfigInputfields) {
			$fieldset = $this->userConfigInputfields;
		} else {
			$fieldset = new InputfieldWrapper();
			$settings = $this->getUserSettings($user);
			$this->getUserConfigInputfields($user, $fieldset, $settings);
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
		$settingsPrev = $this->getUserSettings($user);
		$settings = $settingsPrev;
		$changes = array();

		foreach($fieldset->getAll() as $f) {
			$name = $f->attr('name');
			if(strpos($name, '_tfa_') === 0) list(,$name) = explode('_tfa_', $name);
			$settings[$name] = $f->val();
		}
		
		$settings = $this->processUserConfigInputfields($user, $fieldset, $settings, $settingsPrev);
		
		foreach($settings as $name => $value) {
			if(!isset($settingsPrev[$name]) || $settingsPrev[$name] !== $settings[$name]) {
				$changes[$name] = $name;
			}
		}
		foreach($settingsPrev as $name => $value) {
			if(!isset($settings[$name])) $changes[$name] = $name;
		}
		
		if(count($changes)) {
			$this->message("TFA settings changed: " . implode(', ', $changes), Notice::debug);
			$this->saveUserSettings($user, $settings);
		}
	}

	/**
	 * Hook before InputfieldForm::render()
	 * 
	 * This method adds the fields configured in getUserConfigInputfields() and adds
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
		$user = $this->getUser();
		$settings = $this->getUserSettings($user);
		
		if($this->userConfigInputfields && false) {
			$fieldset = $this->userConfigInputfields;
		} else {
			/** @var InputfieldFieldset $fieldset */
			$fieldset = $modules->get('InputfieldFieldset');
			$fieldset->label = $modules->getModuleInfoProperty($this, 'title');
			$fieldset->showIf = "$this->userFieldName=" . $this->className();
			$this->getUserConfigInputfields($user, $fieldset, $settings);
		}
		
		if(!$this->enabled($user) && !$this->wire('input')->requestMethod('POST')) {
			$this->warning($this->_('Please configure your two-factor authentication settings'));
		}

		$inputfield->getParent()->insertAfter($fieldset, $inputfield);

		foreach($fieldset->getAll() as $f) {
			$name = $f->attr('name');
			if(isset($settings[$name])) $f->val($settings[$name]);
			$f->attr('name', "_tfa_$name");
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
			$field->icon = 'user-secret';
			$field->set('moduleTypes', array('Tfa'));
			$field->set('instantiateModule', 1);
			$field->set('showNoneOption', 1);
			$field->set('labelField', 'title');
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