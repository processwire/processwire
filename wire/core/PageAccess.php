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
	 * @var ProcessWire
	 * 
	 */
	protected $wire;
	
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
			$name = $name->name;
		}
		
		if(strpos($name, 'page-') === 0) $name = str_replace('page-', '', $name);
		
		// all non-recognized names inherit the 'edit' type
		return in_array($name, $this->types) ? $name : 'edit';
	}

	/**
	 * Returns the parent page that has the template from which we get our role/access settings from
	 *
	 * @param Page $page
	 * @param string $type Type, one of 'view', 'edit', 'create' or 'add' (default='view')
	 * @param int $level Recursion level for internal use only
	 * @return Page|NullPage Returns NullPage if none found
	 *
	 */
	public function getAccessParent(Page $page, $type = 'view', $level = 0) {
		if(!$page->id) return $page->wire('pages')->newNullPage();
		if(!in_array($type, $this->types)) $type = $this->getType($type);
		
		if($page->id === 1 || $page->template->useRoles) {
			// found an access parent
			if($type != 'view' && $level > 0 && $page->template->noInherit != 0) {
				// access parent prohibits inheritance of edit-related permissions
				return $page->wire('pages')->newNullPage();
			}
			return $page;
		}
		
		$parent = null;

		if($type === 'edit' && $page->isTrash() && $page->id != $page->wire('config')->trashPageID) {
			// pages in trash have an edit access parent as whatever it was prior to being trashed
			$info = $page->wire('pages')->trasher()->parseTrashPageName($page->name);
			if(!empty($info['parent_id'])) $parent = $page->wire('pages')->get((int) $info['parent_id']);
		}
		
		if(!$parent || !$parent->id) $parent = $page->parent();	
		if($parent->id) return $this->getAccessParent($parent, $type, $level + 1);
		
		return $page->wire('pages')->newNullPage();
	}

	/**
	 * Returns the template from which we get our role/access settings from
	 *
	 * @param Page $page
	 * @param string $type Type, one of 'view', 'edit', 'create' or 'add' (default='view')
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
	 * @param string $type Default is 'view', but you may specify 'create' or 'add' as well
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
	
	/**
	 * Get or inject a ProcessWire API variable or fuel a new object instance
	 *
	 * See Wire::wire() for explanation of all options.
	 *
	 * @param string|WireFuelable $name Name of API variable to retrieve, set, or omit to retrieve entire Fuel object.
	 * @param null|mixed $value Value to set if using this as a setter, otherwise omit.
	 * @param bool $lock When using as a setter, specify true if you want to lock the value from future changes (default=false)
	 * @return mixed|Fuel
	 * @throws WireException
	 *
	 */
	public function wire($name = '', $value = null, $lock = false) {
		if(!is_null($value)) return $this->wire->wire($name, $value, $lock);
			else if($name instanceof WireFuelable && $this->wire) $name->setWire($this->wire);
			else if($name) return $this->wire->wire($name); 
		return $this->wire; 
	}

	/**
	 * Set the ProcessWire instance
	 *
	 * @param ProcessWire $wire
	 *
	 */
	public function setWire(ProcessWire $wire) {
		$this->wire = $wire; 
	}

	/**
	 * Get the ProcessWire instance
	 *
	 * @return ProcessWire
	 *
	 */
	public function getWire() {
		return $this->wire;
	}
}
