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
	}
	
	/**
	 * Get the user by name, ID or selector string
	 * 
	 * @param string $selectorString
	 * @return Page|NullPage|null
	 */
	public function get($selectorString) {
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
		
		$hasGuest = false;
		$guestRoleID = $this->wire('config')->guestUserRolePageID; 
		
		if($user->roles) foreach($user->roles as $role) {
			if($role->id == $guestRoleID) {
				$hasGuest = true; 	
				break;
			}
		}
		
		if(!$hasGuest && $user->roles) {
			$guestRole = $this->wire('roles')->getGuestRole();
			$user->roles->add($guestRole);
		}
		
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
		static $guestID = null;
		if(is_null($guestID)) $guestID = $this->wire('config')->guestUserRolePageID; 
		$roles = $page->get('roles'); 
		if(!$roles->has("id=$guestID")) $page->get('roles')->add($this->wire('roles')->getGuestRole());
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
		$this->guestUser = $this->get($this->config->guestUserPageID); 
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
		return $this->wire()->pages->newPage(array(
			'template' => 'user',
			'pageClass' => $this->getPageClass()
		));
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
		if(!$user->id && $user instanceof User) {
			// add guest role if user doesn't already have it
			$role = $this->wire('roles')->get($this->wire('config')->guestUserRolePageID);
			if($role->id && !$user->hasRole($role)) $user->addRole($role);
		}
		return parent::___saveReady($user);
	}

}
