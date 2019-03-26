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
		return $this->wire('pages')->newPage(array(
			'template' => 'user',
			'pageClass' => 'User'
		));
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
