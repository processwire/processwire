<?php namespace ProcessWire;

/**
 * ProcessWire Role Page
 *
 * #pw-summary Role is a type of Page used for grouping permissions to users. 
 * #pw-body = 
 * Any given User will have one or more roles, each with zero or more permissions assigned to it.
 * Note that most public API-level access checking is typically performed from the User rather than 
 * the Role(s), as it accounts for the combined roles. Please also see `User`, `Permission` and the 
 * access related methods on `Page`. 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property PageArray $permissions PageArray of permissions assigned to Role.
 * @property string $name Name of role. 
 * @property int $id Numeric page ID of role. 
 *
 */

class Role extends Page { 

	/**
	 * Create a new Role page in memory. 
	 * 
	 * @param Template $tpl
	 *
	 */
	public function __construct(Template $tpl = null) {
		parent::__construct($tpl); 
		if(is_null($tpl)) $this->template = $this->getPredefinedTemplate();
		$this->parent = $this->getPredefinedParent();
	}

	/**
	 * Get predefined template (template method)
	 * 
	 * @return Template
	 *
	 */
	protected function getPredefinedTemplate() {
		return $this->wire('templates')->get('role'); 
	}

	/**
	 * Get predefined parent page (template method)
	 * 
	 * @return Page
	 *
	 */
	protected function getPredefinedParent() {
		return $this->wire('pages')->get($this->wire('config')->rolesPageID); 
	}

	/**
	 * Does this role have the given permission name, id or object?
	 * 
	 * @param string|int|Permission $permission Permission object, name, or id. 
	 * @param Page|Template|null $context Optional Page or Template context.
	 * @return bool
	 * @see User::hasPermission()
	 *
	 */
	public function hasPermission($permission, $context = null) {
	
		$name = $permission;
		$permission = null;
		$has = false; 
		
		if(empty($name)) {	
			// do nothing
			return $has;
		
		} else if($name instanceof Page) {
			$permission = $name;
			$has = $this->permissions->has($permission); 

		} else if(ctype_digit("$name")) {
			$name = (int) $name;
			foreach($this->permissions as $p) {
				if(((int) $p->id) === $name) {
					$permission = $p;
					$has = true;
					break;
				}
			}
			
		} else if($name == "page-add" || $name == "page-create") {
			// runtime permissions that don't have associated permission pages
			if(empty($context)) return false;
			$permission = $this->wire(new Permission());
			$permission->name = $name;

		} else if(is_string($name)) {
			if(!$this->wire('permissions')->has($name)) {
				if(!ctype_alnum(str_replace('-', '', $name))) $name = $this->wire('sanitizer')->pageName($name);
				$delegated = $this->wire('permissions')->getDelegatedPermissions();
				if(isset($delegated[$name])) $name = $delegated[$name];
			}
			foreach($this->permissions as $p) {
				if($p->name === $name) {
					$permission = $p;
					$has = true;
					break;
				}
			}
		}

		if($context !== null && ($context instanceof Page || $context instanceof Template)) {
			if(!$permission) $permission = $this->wire('permissions')->get($name);
			if($permission) {
				$has = $this->hasPermissionContext($has, $permission, $context);
			}
		}
		
		return $has; 
	}

	/**
	 * Return whether the role has the permission within the context of a Page or Template
	 * 
	 * @param bool $has Result from the hasPermission() method
	 * @param Permission $permission Permission to check
	 * @param Wire $context Must be a Template or Page
	 * @return bool
	 * 
	 */
	protected function hasPermissionContext($has, Permission $permission, Wire $context) {
		
		if(strpos($permission->name, "page-") !== 0) return $has;
		$type = str_replace('page-', '', $permission->name);
		if(!in_array($type, array('view', 'edit', 'add', 'create'))) $type = 'edit';
		
		$accessTemplate = $context instanceof Page ? $context->getAccessTemplate($type) : $context;
		if(!$accessTemplate) return false;
		if(!$accessTemplate->useRoles) return $has;
		
		if($permission->name == 'page-view') {
			if(!$has) return false;
			$has = $accessTemplate->hasRole($this);
			return $has;
		}
	
		if($permission->name == 'page-edit' && !$has) return false;
		
		switch($permission->name) {
			case 'page-edit':
				$has = in_array($this->id, $accessTemplate->editRoles);
				break;
			case 'page-create':
				$has = in_array($this->id, $accessTemplate->createRoles);
				break;
			case 'page-add':
				$has = in_array($this->id, $accessTemplate->addRoles);
				break;
			default:
				// some other page-* permission
				$rolesPermissions = $accessTemplate->rolesPermissions; 
				if(!isset($rolesPermissions["$this->id"])) return $has;
				foreach($rolesPermissions["$this->id"] as $permissionID) {
					$revoke = strpos($permissionID, '-') === 0;
					if($revoke) $permissionID = ltrim($permissionID, '-');
					$permissionID = (int) $permissionID;	
					if($permission->id != $permissionID) continue;
					if($has) {
						if($revoke) $has = false;
					} else {
						if(!$revoke) $has = true;
					}
					break;
				}
		}
		
		return $has;
	}

	/**
	 * Add the given Permission string, id or object.
	 *
	 * This is the same as `$role->permissions->add($permission)` except this one will accept ID or name.
	 *
	 * @param string|int|Permission $permission Permission object, name or id. 
	 * @return bool Returns false if permission not recognized, true otherwise
	 *
	 */
	public function addPermission($permission) {
		if(is_string($permission) || is_int($permission)) $permission = $this->wire('permissions')->get($permission); 
		if(is_object($permission) && $permission instanceof Permission) {
			$this->permissions->add($permission); 
			return true; 
		}
		return false;
	}

	/**
	 * Remove the given permission string, id or object.
	 *
	 * This is the same as `$role->permissions->remove($permission)` except this one will accept ID or name.
	 *
	 * @param string|int|Permission $permission Permission object, name or id. 
	 * @return bool false if permission not recognized, true otherwise
	 *
	 */
	public function removePermission($permission) {
		if(is_string($permission) || is_int($permission)) $permission = $this->wire('permissions')->get($permission); 
		if(is_object($permission) && $permission instanceof Permission) {
			$this->permissions->remove($permission); 
			return true; 
		}
		return false;
	}

	/**
	 * Return the API variable used for managing pages of this type
	 * 
	 * #pw-internal
	 *
	 * @return Pages|PagesType
	 *
	 */
	public function getPagesManager() {
		return $this->wire('roles');
	}

}

