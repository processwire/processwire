<?php namespace ProcessWire;

/**
 * ProcessWire Page Access
 *
 * Provides implementation for Page access functions.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class PageAccess {

	/**
	 * Allowed types for page access
	 * 
	 * Compatible with Template::getRoles() and all methods in this class
	 * 
	 * @var array
	 * 
	 */
	protected $types = array(
		'view',
		'edit',
		'add',
		'create',
	);

	/**
	 * Normalize a permission name type
	 * 
	 * Converts things like 'page-view' to 'view', and more.
	 * 
	 * @param string|int|Permission $name
	 * @return string
	 * 
	 */
	public function getType($name) {
		if(is_string($name)) {
			// good
		} else if(is_int($name)) {
			$permission = $this->wire('permissions')->get($name);
			$name = $permission ? $permission->name : 'edit';
		} else if($name instanceof Permission) {
			$name = $permission->name;
		}
		
		if(strpos($name, 'page-') === 0) $name = str_replace('page-', '', $name);
		
		// all non-recognized names inherit the 'edit' type
		return in_array($name, $this->types) ? $name : 'edit';
	}

	/**
	 * Returns the parent page that has the template from which we get our role/access settings from
	 *
	 * @param Page $page
	 * @param string Type, one of 'view', 'edit', 'create' or 'add' (default='view')
	 * @param int $level Recursion level for internal use only
	 * @return Page|NullPage Returns NullPage if none found
	 *
	 */
	public function getAccessParent(Page $page, $type = 'view', $level = 0) {
		if(!in_array($type, $this->types)) $type = $this->getType($type);
		if($page->template->useRoles || $page->id === 1) {
			// found an access parent
			if($type != 'view' && $level > 0 && $page->template->noInherit != 0) {
				// access parent prohibits inheritance of edit-related permissions
				return $page->wire('pages')->newNullPage();
			}
			return $page;
		}
		$parent = $page->parent();	
		if($parent->id) return $this->getAccessParent($parent, $type, $level+1); 
		return $page->wire('pages')->newNullPage();
	}

	/**
	 * Returns the template from which we get our role/access settings from
	 *
	 * @param Page $page
	 * @param string Type, one of 'view', 'edit', 'create' or 'add' (default='view')
	 * @return Template|null Returns null if none	
	 *
	 */
	public function getAccessTemplate(Page $page, $type = 'view') {
		$parent = $this->getAccessParent($page, $type);
		if(!$parent->id) return null;
		return $parent->template; 
	}
	
	/**
	 * Return the PageArray of roles that have access to this page
	 *
	 * This is determined from the page's template. If the page's template has roles turned off, 
	 * then it will go down the tree till it finds usable roles to use. 
	 *
	 * @param Page $page
	 * @param string $type Default is 'view', but you may specify 'edit', 'create' or 'add' to retrieve that type
	 * @return PageArray
	 *
	 */
	public function getAccessRoles(Page $page, $type = 'view') {
		$template = $this->getAccessTemplate($page, $type);
		if($template) return $template->getRoles($this->getType($type)); 
		return $page->wire('pages')->newPageArray();
	}

	/**
	 * Returns whether this page has the given access role
	 *
	 * Given access role may be a role name, role ID or Role object
	 *
	 * @param Page $page
	 * @param string|int|Role $role 
	 * @param string Default is 'view', but you may specify 'create' or 'add' as well
	 * @return bool
	 *
	 */
	public function hasAccessRole(Page $page, $role, $type = 'view') {
		$roles = $this->getAccessRoles($page, $type);
		if(is_string($role)) return $roles->has("name=$role"); 
		if($role instanceof Role) return $roles->has($role); 
		if(is_int($role)) return $roles->has("id=$role"); 
		return false;
	}
}
