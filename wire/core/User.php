<?php namespace ProcessWire;

/**
 * ProcessWire UserPage
 *
 * A type of Page used for storing an individual User
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 * #pw-summary The $user API variable is a type of page representing the current user, and the User class is Page type used for all users.
 *
 * @link http://processwire.com/api/variables/user/ Offical $user API variable Documentation
 *
 * @property string $email Get or set email address for this user.
 * @property string|Password $pass Set the user’s password. 
 * @property PageArray $roles Get the roles this user has. #pw-group-common #pw-group-access
 * @property Language $language User language, applicable only if LanguageSupport installed. #pw-group-languages
 * @property string $admin_theme Admin theme class name (when applicable).
 * 
 * @method bool hasPagePermission($name, Page $page = null) #pw-internal
 * @method bool hasTemplatePermission($name, $template) #pw-internal
 * 
 * Additional notes regarding the $user->pass property: 
 * Note that when getting, this returns a hashed version of the password, so it is not typically useful to get this property. 
 * However, it is useful to set this property if you want to change the password. When you change a password, it is assumed 
 * to be the non-hashed/non-encrypted version. ProcessWire will hash it automatically when the user is saved.
 *
 */

class User extends Page {

	/**
	 * Cached value for $user->isSuperuser() checks
	 * 
	 * @var null|bool
	 * 
	 */
	protected $isSuperuser = null;

	/**
	 * Create a new User page in memory. 
	 *
	 * @param Template|null $tpl Template object this page should use. 
	 *
	 */
	public function __construct(?Template $tpl = null) {
		if(!$tpl) $this->template = $this->wire()->templates->get('user');
		$this->_parent_id = $this->wire()->config->usersPageID; 
		parent::__construct($tpl); 
	}

	/**
	 * Wired to API
	 * 
	 * #pw-internal
	 * 
	 */
	public function wired() {
		parent::wired();
		// intentionally duplicated from __construct() in case a multi-instance environment
		// and we got the wrong instance in __construct()
		$template = $this->wire()->templates->get('user');
		if($template !== $this->template && (!$this->template || $this->template->name === 'user')) {
			$this->template = $template;
		}
		$this->_parent_id = $this->wire()->config->usersPageID; 
	}
	
	/**
	 * Does this user have the given Role? 
	 * 
	 * #pw-group-access
	 * 
	 * ~~~~~
	 * if($user->hasRole('editor')) {
	 *   // user has the editor role
	 * }
	 * ~~~~~
	 *
	 * @param string|Role|int $role May be Role name, object or ID. 
	 * @return bool
	 *
	 */
	public function hasRole($role) {
	
		/** @var PageArray $roles */
		$roles = $this->get('roles');
		$has = false; 
		
		if(empty($roles)) {
			// do nothing
			
		} else if($role instanceof Page) {
			$has = $roles->has($role); 
			
		} else if(ctype_digit("$role")) {
			$role = (int) $role; 
			foreach($roles as $r) {
				if(((int) $r->id) === $role) {
					$has = true; 
					break;
				}
			}
			
		} else if(is_string($role)) {
			foreach($roles as $r) {
				if($r->name === $role) {
					$has = true;
					break;
				}
			}
		}
		
		return $has;
	}

	/**
	 * Add Role to this user 
	 *
	 * This is the same as `$user->roles->add($role)` except this one will also accept ID or name.
	 * 
	 * ~~~~~
	 * // Add the "editor" role to the $user
	 * $user->addRole('editor');
	 * $user->save();
	 * ~~~~~
	 * 
	 * #pw-group-access
	 *
	 * @param string|int|Role $role May be Role name, object, or ID. 
	 * @return bool Returns false if role not recognized, true otherwise
	 *
	 */
	public function addRole($role) {
		if(is_string($role) || is_int($role)) {
			$role = $this->wire()->roles->get($role);
		}
		if($role instanceof Role) {
			$this->get('roles')->add($role); 
			return true; 
		}
		return false;
	}

	/**
	 * Remove Role from this user
	 *
	 * This is the same as `$user->roles->remove($role)` except this one will accept ID or name.
	 * 
	 * ~~~~~
	 * // Remove the "editor" role from the $user
	 * $user->removeRole('editor');
	 * $user->save();
	 * ~~~~~
	 * 
	 * #pw-group-access
	 *
	 * @param string|int|Role $role May be Role name, object or ID. 
	 * @return bool false if role not recognized, true otherwise
	 *
	 */
	public function removeRole($role) {
		if(is_string($role) || is_int($role)) {
			$role = $this->wire()->roles->get($role);
		}
		if($role instanceof Role) {
			$this->get('roles')->remove($role); 
			return true; 
		}
		return false;
	}

	/**
	 * Does the user have the given permission? 
	 * 
	 * - Optionally accepts a `Page` or `Template` context for the permission.
	 * - This method accounts for the user's permissions across all their roles.  
	 * 
	 * ~~~~~
	 * if($user->hasPermission('page-publish')) {
	 *   // user has the page-publish permission in one of their roles
	 * }
	 * if($user->hasPermission('page-publish', $page)) {
	 *   // user has page-publish permission for $page
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-access
	 * 
	 * @param string|Permission $name Permission name, object or id. 
	 * @param Page|Template|bool|string $context Page or Template... 
	 *  - or specify boolean true to return if user has permission OR if it was added at any template
	 *  - or specify string "templates" to return array of Template objects where user has permission
	 * @return bool|array
	 *
	 */
	public function hasPermission($name, $context = null) {
		// This method serves as the public interface to the hasPagePermission and hasTemplatePermission methods.
		$hooks = $this->wire()->hooks;
		
		if($context === null || $context instanceof Page) {
			$hook = $hooks->isHooked('hasPagePermission()');
			return $hook ? $this->hasPagePermission($name, $context) : $this->___hasPagePermission($name, $context);
		} 
		
		$hook = $hooks->isHooked('hasTemplatePermission()');
		
		if($context instanceof Template) {
			return $hook ? $this->hasTemplatePermission($name, $context) : $this->___hasTemplatePermission($name, $context);
		}
		
		if($context === true || $context === 'templates') {
			$addedTemplates = array();
			foreach($this->wire()->templates as $t) {
				if(!$t->useRoles) continue;
				$has = $hook ? $this->hasTemplatePermission($name, $t) : $this->___hasTemplatePermission($name, $t);
				if($has) $addedTemplates[] = $t;
				if($has && $context === true) break; // we only need to know if there is at least one, so break now
			}
			return $context === true ? count($addedTemplates) > 0 : $addedTemplates;	
		}
		
		return false;
	}

	/**
	 * Does this user have named permission for the given Page?
	 *
	 * This is a basic permission check and it is recommended that you use those from the PagePermissions module instead. 
	 * You use the PagePermissions module by calling the editable(), addable(), etc., functions on a page object. 
	 * The PagePermissions does use this function for some of it's checking. 
	 * 
	 * #pw-group-access
	 *
	 * @param string|Permission
	 * @param Page|null $page Optional page to check against
	 * @return bool
	 *
	 */
	protected function ___hasPagePermission($name, ?Page $page = null) {

		if($this->isSuperuser()) return true; 
		$permissions = $this->wire()->permissions;

		// convert $name to a Permission object (if it isn't already)
		if($name instanceof Page) {
			$permission = $name;
		} else if(ctype_digit("$name")) {
			$permission = $permissions->get((int) $name);
		} else if($name == 'page-rename') {
			// optional permission that, if not installed, page-edit is substituted for
			if($permissions->has('page-rename')) {
				$permission = $permissions->get('page-rename');
			} else {
				$permission = $permissions->get('page-edit');
			}
		} else {
			if($name == 'page-add' || $name == 'page-create') {
				// page-add and page-create don't actually exist in the DB, so we substitute page-edit for them 
				// code later on will make sure they exist in the template's addRoles/createRoles
				$p = 'page-edit';
			} else if(!$permissions->has($name)) {
				if($page) {
					$method = $permissions->getDelegatedMethod($name, $page);
					if($method) return $page->$method(); // i.e. $page->editable()
				}
				$delegated = $permissions->getDelegatedPermissions();
				$p = isset($delegated[$name]) ? $delegated[$name] : $name;
			} else {
				$p = $name;
			}
			$permission = $permissions->get($p); 
		}

		if(!$permission || !$permission->id) return false;

		/** @var PageArray $userRoles */
		$userRoles = $this->getUnformatted('roles'); 
		if(empty($userRoles) || !$userRoles instanceof PageArray) return false; 
		$has = false; 
		$accessTemplate = is_null($page) ? false : $page->getAccessTemplate($permission->name);
		if(is_null($accessTemplate)) return false;

		foreach($userRoles as $role) {
			/** @var Role $role */

			if(!$role || !$role->id) continue; 
			$context = null;

			if($page !== null) {
				// @todo some of this logic has been duplicated in Role::hasPermission, so code within this if() may be partially redundant
				if(!$page->id) continue;  

				// if page doesn't have the 'view' role, then no access
				if(!$page->hasAccessRole($role, $name)) continue; 

				// all page- permissions except page-view and page-add require page-edit access on $page, so check against that
				if(strpos($name, 'page-') === 0 && $name != 'page-view' && $name != 'page-add') {
					if($accessTemplate && !in_array($role->id, $accessTemplate->editRoles)) continue; 
				}

				// check against addRoles, createRoles if the permission requires it
				if($name == 'page-add') {
					if($accessTemplate && !in_array($role->id, $accessTemplate->addRoles)) continue;
				} else if($name == 'page-create') {
					if($accessTemplate && !in_array($role->id, $accessTemplate->createRoles)) continue;
				} else {
					// some other page-* permission, check against context of access template
					$context = $accessTemplate ? $accessTemplate : $page;
				}
			}

			if($role->hasPermission($permission, $context)) { 
				$has = true;
				break;
			}
		}
	
		return $has; 
	}


	/**
	 * Does this user have the given permission on the given template?
	 * 
	 * #pw-group-access
	 *
	 * @param string|Permission $name Permission name
	 * @param Template|int|string $template Template object, name or ID
	 * @return bool
	 * @throws WireException
	 *
	 */
	protected function ___hasTemplatePermission($name, $template) {
		
		if($this->isSuperuser()) return true;

		if(is_object($name)) $name = $name->name; 

		if($template instanceof Template) {
			// fantastic then
		} else if(is_string($template) || is_int($template)) {
			$template = $this->wire()->templates->get($this->wire()->sanitizer->name($template)); 
			if(!$template) return false;
		} else {
			return false;
		}

		// if the template is not defining roles, we have to say 'no' to permission
		// because we don't have any page context to inherit from at this point
		// if(!$template->useRoles) return false; 

		/** @var PageArray $userRoles */
		$userRoles = $this->get('roles'); 
		if(empty($userRoles)) return false; 
		$has = false;

		foreach($userRoles as $role) {
			/** @var Role $role */
			
			// @todo much of this logic has been duplicated in Role::hasPermission, so code within this foreach() may be partially redundant

			if(!$template->hasRole($role)) continue; 

			if($name == 'page-create') { 
				if(!in_array($role->id, $template->createRoles)) continue; 
				$name = 'page-edit'; // swap permission to page-edit since create managed at template and requires page-edit
			}
			
			if($name == 'page-edit' && !in_array($role->id, $template->editRoles)) {
				continue;
			}

			if($name == 'page-add') {
				if(!in_array($role->id, $template->addRoles)) continue;
				$name = 'page-edit';
			}

			$context = null;
			if($name != 'page-edit' && $name != 'page-add' && $name != 'page-create' && $name != 'page-view') {
				if(strpos($name, "page-") === 0) $context = $template;
			}

			if($role->hasPermission($name, $context)) {
				$has = true;
				break;
			}
		}

		return $has; 
	}

	/**
	 * Get this user’s permissions, optionally within the context of a Page.
	 * 
	 * ~~~~~
	 * // Get all permissions the user has across their roles
	 * $permissions = $user->getPermissions(); 
	 * 
	 * // Get all permissions the user has for $page
	 * $permissions = $user->getPermissions($page); 
	 * ~~~~~
	 * 
	 * #pw-group-access
	 *
	 * @param Page|null $page Optional page to check against
	 * @return PageArray of Permission objects
	 *
	 */
	public function getPermissions(?Page $page = null) {
		// Does not currently include page-add or page-create permissions (runtime).
		if($this->isSuperuser()) return $this->wire()->permissions->getIterator(); // all permissions
		$userPermissions = $this->wire()->pages->newPageArray();
		/** @var PageArray $userRoles */
		$userRoles = $this->get('roles'); 
		if(empty($userRoles)) return $userPermissions; 
		foreach($userRoles as $role) {
			if($page && !$page->hasAccessRole($role)) continue; 
			foreach($role->permissions as $permission) { 
				if($page && $permission->name == 'page-edit') {
					$accessTemplate = $page->getAccessTemplate('edit');
					if(!$accessTemplate) continue;
					if(!in_array($role->id, $accessTemplate->editRoles)) continue; 
				}
				$userPermissions->add($permission); 
			}
		}
		return $userPermissions; 
	}

	/**
	 * Does this user have the superuser role?
	 *
	 * Same as calling `$user->roles->has('name=superuser');` but potentially faster.
	 * 
	 * #pw-group-access
	 *
	 * @return bool
	 *
	 */
	public function isSuperuser() {
		if(is_bool($this->isSuperuser)) return $this->isSuperuser;
		$config = $this->wire()->config;
		if($this->id === $config->superUserPageID) {
			$is = true;
		} else if($this->id === $config->guestUserPageID) {
			$is = false;
		} else {
			$superuserRoleID = (int) $config->superUserRolePageID;
			/** @var PageArray $userRoles */
			$userRoles = $this->getUnformatted('roles');
			if(empty($userRoles)) return false; // no cache intentional
			$is = false;
			foreach($userRoles as $role) {
				/** @var Role $role */
				if(((int) $role->id) === $superuserRoleID) {
					$is = true;
					break;
				}
			}
		}
		$this->isSuperuser = $is;
		return $is;
	}

	/**
	 * Is this the non-logged in guest user?
	 * 
	 * #pw-group-access
	 *
	 * @return bool
	 *
	 */ 
	public function isGuest() {
		return $this->id === $this->wire()->config->guestUserPageID; 
	}

	/**
	 * Is the current $user logged in and the same as this user?
	 * 
	 * When this method returns true, it means the current $user (API variable) is 
	 * this user and that they are logged in.
	 * 
	 * #pw-group-access
	 *
	 * @return bool
	 *
	 */
	public function isLoggedin() {
		if($this->isGuest()) return false;
		$user = $this->wire()->user;
		$userId = $user ? $user->id : 0;
		return $userId && "$userId" === "$this->id";
	}

	/**
	 * Set language for user (quietly)
	 * 
	 * - Sets the language without tracking it as a change to the user. 
	 * - If language support is not installed this method silently does nothing.
	 * 
	 * #pw-group-languages
	 * 
	 * @param Language|string|int $language Language object, name, or ID
	 * @return self
	 * @throws WireException if language support is installed and given an invalid/unknown language
	 * @since 3.0.186
	 * 
	 */
	public function setLanguage($language) {
		
		if(!is_object($language)) {
			$languages = $this->wire()->languages;
			// if multi-language support not available exit now
			if(!$languages) return $this; 
			// convert string or int to Language object
			$language = $languages->get($language);
			if(!is_object($language)) $language = null;
		}
		
		if($language && ($language->className() === 'Language' || wireInstanceOf($language, 'Language'))) {
			return $this->setQuietly('language', $language);
		} else {
			throw new WireException("Unknown language set to user $this->name");
		}
	}

	/**
	 * Get value
	 * 
	 * @param string $key
	 * @return null|mixed
	 *
	 */
	public function get($key) {
		$value = parent::get($key);
		if(!$value && $key === 'language') {
			$languages = $this->wire()->languages;
			if($languages) $value = $languages->getDefault();
		}
		return $value;
	}

	/**
	 * Return the URL necessary to edit this user
	 * 
	 * In this case we adjust the default page editor URL to ensure users are edited
	 * only from the Access section. 
	 *
	 * #pw-internal
	 *
	 * @param array|bool $options Specify boolean true to force URL to include scheme and hostname, or use $options array:
	 *  - `http` (bool): True to force scheme and hostname in URL (default=auto detect).
	 * @return string URL for editing this user
	 *
	 */
	public function editUrl($options = array()) {
		return str_replace('/page/edit/', '/access/users/edit/', parent::editUrl($options));
	}

	/**
	 * Set the Process module (WirePageEditor) that is editing this User
	 * 
	 * We use this to detect when the User is being edited somewhere outside of /access/users/
	 * 
	 * #pw-internal
	 * 
	 * @param WirePageEditor $editor
	 * 
	 */
	public function ___setEditor(WirePageEditor $editor) {
		parent::___setEditor($editor); 
		if(!$editor instanceof ProcessUser) $this->wire()->session->redirect($this->editUrl());
	}

	/**
	 * Return the API variable used for managing pages of this type
	 * 
	 * #pw-internal
	 *
	 * @return Users
	 *
	 */
	public function getPagesManager() {
		return $this->wire()->users;
	}

	/**
	 * Does user have two-factor authentication (Tfa) enabled? (and what type?)
	 *
	 * - Returns boolean false if not enabled. 
	 * - Returns string with Tfa module name (string) if enabled.
	 * - When `$getInstance` argument is true, returns Tfa module instance rather than module name.
	 * 
	 * The benefit of using this method is that it can identify if Tfa is enabled without fully 
	 * initializing a Tfa module that would attach hooks, etc. So when you only need to know if 
	 * Tfa is enabled for a user, this method is more efficient than accessing `$user->tfa_type`. 
	 * 
	 * When using `$getInstance` to return module instance, note that the module instance might not 
	 * be initialized (hooks not added, etc.). To retrieve an initialized instance, you can get it 
	 * from `$user->tfa_type` rather than calling this method. 
	 * 
	 * #pw-group-access
	 * 
	 * @param bool $getInstance Get Tfa module instance when available? (default=false) 
	 * @return bool|string|Tfa
	 * @since 3.0.162
	 * 
	 */
	public function hasTfa($getInstance = false) {
		return Tfa::getUserTfaType($this, $getInstance); 
	}

	/**
	 * Hook called when field has changed
	 * 
	 * #pw-internal
	 * 
	 * @param string $what
	 * @param mixed $old
	 * @param mixed $new
	 * 
	 */
	public function ___changed($what, $old = null, $new = null) {
		if($what === 'roles' && is_bool($this->isSuperuser)) $this->isSuperuser = null;
		parent::___changed($what, $old, $new); 
	}

}
