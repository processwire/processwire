<?php namespace ProcessWire;

/**
 * The Roles class serves as the $roles API variable. 
 * 
 * #pw-summary Provides management of all Role pages for access control. 
 *	
 * @method PageArray find($selector) Return the Role(s) matching the the given selector.
 * @method Role add($name) Add new Role with the given name and return it.
 * @method bool save(Role $role) Save given role.
 * @method bool delete(Role $role) Delete the given role.
 * @method array saveReady(Page $page) Hook called just before a Role is saved #pw-hooker
 * @method void saved(Page $page, array $changes = [], $values = []) Hook called after a role has been saved #pw-hooker
 * @method void added(Page $page) Hook called just after a Role is added #pw-hooker
 * @method void deleteReady(Page $page) Hook called before a Role is deleted #pw-hooker
 * @method void deleted(Page $page) Hook called after a Role is deleted #pw-hooker
 * @method Role new($options = []) Create new Role instance in memory (3.0.249+)
 *
 */ 

class Roles extends PagesType {

	/**
	 * @var Role|null 
	 * 
	 */
	protected $guestRole = null;

	/**
	 * Get the 'guest' role 
	 * 
	 * This is a performance optimized version of `$roles->get('guest')`.
	 * 
	 * #pw-internal
	 * 
	 * @return Role
	 * @throws WireException
	 * 
	 */
	public function getGuestRole() {
		if($this->guestRole) return $this->guestRole; 
		$this->guestRole = parent::get((int) $this->wire()->config->guestUserRolePageID); 
		return $this->guestRole; 
	}

	/**
	 * Get a Role by name, numeric ID or selector
	 * 
	 * @param string $selectorString Role name or selector 
	 * @return Role|NullPage|null
	 *
	 */
	public function get($selectorString) {
		if($selectorString === 'guest') return $this->getGuestRole();
		return parent::get($selectorString);
	}
	
	/**
	 * Save a Role
	 *
	 * #pw-group-manipulation
	 *
	 * @param Role|Page $page
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Page $page) {
		return parent::___save($page);
	}

	/**
	 * Permanently delete a Role
	 *
	 * #pw-group-manipulation
	 *
	 * @param Role|Page $page Permission to delete
	 * @param bool $recursive If set to true, then this will attempt to delete any pages below the Permission too.
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___delete(Page $page, $recursive = false) {
		return parent::___delete($page, $recursive);
	}

	/**
	 * Add a new Role with the given name and return it
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $name Name of role you want to add, i.e. "hello-world"
	 * @return Role|NullPage Returns a Role page on success, or a NullPage on error
	 *
	 */
	public function ___add($name) {
		/** @var Role|NullPage $role */
		$role = parent::___add($name);
		return $role;
	}

	/**
	 * Ensure that every role has at least 'page-view' permission
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 *
	 */
	protected function loaded(Page $page) {
		$hasPageView = false;
		foreach($page->permissions as $permission) {
			if($permission->name === 'page-view') $hasPageView = true;
			if($hasPageView) break;
		}
		if(!$hasPageView) {
			$pageView = $this->wire()->permissions->get('page-view');
			$page->permissions->add($pageView);
		}
	}
	
	/**
	 * Hook called when a page and its data have been deleted
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 *
	 */
	public function ___deleted(Page $page) { 
		foreach($this->wire()->templates as $template) {
			/** @var Template $template */
			if(!$template->useRoles) continue;
			$template->removeRole($page, 'all'); 
			if($template->isChanged()) $template->save();
		}

		parent::___deleted($page);
	}
}
