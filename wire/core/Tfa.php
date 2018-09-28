<?php namespace ProcessWire;

/**
 * Tfa - ProcessWire Two Factor Authentication module base class
 * 
 * This class is for “Tfa” modules to extend. See the TfaEmail and TfaTotp modules as examples. 
 * 
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
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
		if(!$this->wire('fields')->get($this->userFieldName)) $this->install();
		if($this->className() != 'Tfa') $this->initHooks();
		$this->set('codeExpire', 180);
		parent::__construct();
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
	public function ___start($name, $pass) {

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
		if(!$tfaModule) return true;
		
		$settings = $tfaModule->getUserSettings($user);
		if(!$tfaModule->enabledForUser($user, $settings)) return true;

		// check if user name and pass authenticate
		if(!$session->authenticate($user, $pass)) return false;

		// at this point user has successfully authenticated with given name and pass
		if($tfaModule->startUser($user, $settings)) {
			$key = $this->getSessionKey(true);
			$session->redirect("./?$this->keyName=$key"); 
		} else {
			$this->error($this->_('Error creating or sending authentication code'));
			$session->redirect('./');
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
		if($user && $code && $settings) {} // ignore
		throw new WireException('Modules should not call this method');
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
	public function ___buildAuthCodeForm() {
		
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

		$form->appendMarkup = 
			"<p><a href='./'>" . $this->_('Cancel') . "</a></p>" . 
			"<script>if(typeof jQuery!='undefined') jQuery(document).ready(function(){jQuery('#login_name').focus();});</script>";
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
		$settings = $this->getUserSettings($user);
		$valid = $this->isValidUserCode($user, $code, $settings); 
		
		if($valid === true) {
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
			if($valid === 0) {
				$this->error($this->_('Expired code'));
			} else {
				$this->error($this->_('Invalid code'));
			}
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
			'enabled' => false // whether user has this auth method enabled
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
		$settings = $this->getUserSettings($user);
		if(!$this->enabledForUser($user, $settings)) {
			$this->getUserSettingsInputfields($user, $fieldset, $settings);
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