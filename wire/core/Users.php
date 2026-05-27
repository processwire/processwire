<?php namespace ProcessWire;

/**
 * The Users class serves as the $users API variable. 
 * 
 * #pw-summary Manages all users (User objects) in ProcessWire. 
 *
 * @method PageArray find($selector) Return the User(s) matching the the given selector query.
 * @method User add($name) Add new User with the given name and return it.
 * @method bool save($user) Save given User.
 * @method bool delete($user) Delete the given User.
 * @method array saveReady($user) Hook called just before a User is saved #pw-hooker
 * @method void saved($user, array $changes) Hook called after a User has been saved #pw-hooker
 * @method void added($user) Hook called just after a User is added #pw-hooker
 * @method void deleteReady($user) Hook called before a User is deleted #pw-hooker
 * @method void deleted($user) Hook called after a User is deleted #pw-hooker
 * @method User new($options = []) Create new User instance in memory (3.0.249+)
 *  
 *
 */

class Users extends PagesType {

	/**
	 * Current user
	 * 
	 * @var User|null
	 * 
	 */
	protected $currentUser = null;

	/**
	 * Cached guest user
	 * 
	 * @var User|null
	 * 
	 */
	protected $guestUser = null;

	/**
	 * Cached guest role id
	 * 
	 * @var int|null
	 * 
	 */
	protected $guestRoleId = 0;

	/**
	 * Validated custom page class cache for getPageClass method
	 * 
	 * @var string
	 * 
	 */
	protected $validPageClass = '';

	/**
	 * Construct
	 * 
	 * @param ProcessWire $wire
	 * @param array $templates
	 * @param array $parents
	 * 
	 */
	public function __construct(ProcessWire $wire, $templates = array(), $parents = array()) {
		parent::__construct($wire, $templates, $parents);
		$this->setPageClass('User'); 
		$this->guestRoleId = (int) $wire->config->guestUserRolePageID;
	}
	
	/**
	 * Get the user by name, ID or selector string
	 * 
	 * @param string $selectorString
	 * @return User|NullPage|null
	 * 
	 */
	public function get($selectorString) {
		/** @var User|NullPage|null $user */
		$user = parent::get($selectorString);
		return $user; 
	}

	/**
	 * Set the current system user (the $user API variable)
	 *
	 * @param User $user
	 *
	 */
	public function setCurrentUser(User $user) {
		$this->checkGuestRole($user);
		$this->currentUser = $user; 
		$this->wire('user', $user); 
	}

	/**
	 * Ensure that every user loaded has at least the 'guest' role
	 * 
	 * @param Page $page
	 *
	 */
	protected function loaded(Page $page) {
		if($page instanceof User) $this->checkGuestRole($page);
	}

	/**
	 * Check that given user has guest role and add it if not 
	 * 
	 * @param User $user
	 * @since 3.0.198
	 * 
	 */
	protected function checkGuestRole(User $user) {
		$hasGuestRole = false;
		$userRoles = $user->roles;
		if(!$userRoles) return;
		foreach($userRoles as $role) {
			if($role->id != $this->guestRoleId) continue;
			$hasGuestRole = true;
			break;
		}
		if(!$hasGuestRole) {
			$user->addRole($this->wire()->roles->getGuestRole());
		}
	}

	/**
	 * Returns the current user object
	 *
	 * @return User
	 *
	 */
	public function getCurrentUser() {
		if($this->currentUser) return $this->currentUser; 
		return $this->getGuestUser();
	}

	/**
	 * Get the 'guest' user account
	 *
	 * @return User
	 *
	 */
	public function getGuestUser() {
		if($this->guestUser) return $this->guestUser; 
		$this->guestUser = $this->get($this->wire()->config->guestUserPageID); 
		if(defined("PROCESSWIRE_UPGRADE") && !$this->guestUser || !$this->guestUser->id) {
			$this->guestUser = $this->newUser(); // needed during upgrade
		}
		return $this->guestUser; 
	}

	/**
	 * Return new User instance
	 * 
	 * #pw-internal
	 * 
	 * @return User
	 * 
	 */
	public function newUser() {
		$config = $this->wire()->config;
		/** @var User $user */
		$user = $this->wire()->pages->newPage(array(
			'template' => $this->wire()->templates->get($config->userTemplateID),
			'parent' => $config->usersPageID, 
			'pageClass' => $this->getPageClass()
		));
		return $user;
	}

	/**
	 * Get the PHP class name used by Page objects of this type
	 *
	 * #pw-internal
	 *
	 * @return string
	 *
	 */
	public function getPageClass() {
		$pageClass = parent::getPageClass();
		if($pageClass !== 'User' && $pageClass !== 'ProcessWire\User' && $pageClass !== $this->validPageClass) {
			if(wireInstanceOf($pageClass, 'User')) {
				$this->validPageClass = $pageClass;
			} else {
				$this->error("Class '$pageClass' disregarded because it does not extend 'User'"); 
				$pageClass = 'User';
			}
		}
		return $pageClass;
	}

	/**
	 * Set admin theme for all users having role
	 * 
	 * @param AdminTheme|string $adminTheme Admin theme instance or class/module name
	 * @param Role $role
	 * @return int Number of users set for admin theme
	 * @throws WireException
	 * @since 3.0.176
	 * 
	 */
	public function setAdminThemeByRole($adminTheme, Role $role) {
		if(strpos("$adminTheme", 'AdminTheme') !== 0) throw new WireException('Invalid admin theme');
		$moduleId = $this->wire()->modules->getModuleID($adminTheme); 
		if(!$moduleId) throw new WireException('Unknown admin theme');
		$userTemplateIds = implode('|', $this->wire()->config->userTemplateIDs);
		$userIds = $this->wire()->pages->findIDs("templates_id=$userTemplateIds, roles=$role, include=all");
		if(!count($userIds)) return 0;
		$field = $this->wire()->fields->get('admin_theme');
		$table = $field->getTable();
		$sql = "INSERT INTO `$table` (pages_id, data) VALUES(:pages_id, :data) ON DUPLICATE KEY UPDATE pages_id=VALUES(pages_id), data=VALUES(data)";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':data', (int) $moduleId, \PDO::PARAM_INT);
		$qty = 0;
		foreach($userIds as $userId) {
			$query->bindValue(':pages_id', $userId, \PDO::PARAM_INT);
			$query->execute();
			$qty++;
		}
		return $qty;
	}
	
	/**
	 * Save a User 
	 *
	 * - This is the same as calling $user->save()
	 * - If the user is new, it will be inserted. If existing, it will be updated.
	 * - If you want to just save a particular field for the user, use `$user->save($fieldName)` instead.
	 *
	 * **Hook note:**  
	 * If you want to hook this method, please hook the `Users::saveReady`, `Users::saved`, or any one of 
	 * the `Pages::save*` hook methods instead, as this method will not capture users saved directly 
	 * through `$pages->save($user)`. 
	 * ~~~~~
	 * // Example of hooking $pages->save() on User objects only
	 * $wire->addHookBefore('Pages::save(<User>)', function(HookEvent $e) {
	 *   $user = $event->arguments(0);
	 * });
	 * ~~~~~
	 *
	 * @param Page $page
	 * @return bool True on success
	 * @throws WireException
	 *
	 */
	public function ___save(Page $page) {
		return parent::___save($page);
	}

	/**
	 * Hook called just before a user is saved
	 *
	 * #pw-hooker
	 *
	 * @param Page $page The user about to be saved
	 * @return array Optional extra data to add to pages save query.
	 *
	 */
	public function ___saveReady(Page $page) {
		/** @var User $user */
		$user = $page;
		// add guest role if user doesn't already have it
		if(!$user->id && $user instanceof User) $this->checkGuestRole($user);
		return parent::___saveReady($user);
	}

}
