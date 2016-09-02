<?php namespace ProcessWire;

/**
 * ProcessWire Permission Page
 *
 * #pw-summary Permission is a Page type used for storing an individual permission. 
 * #pw-body = 
 * One or more Permission objects are attached to `Role` objects, which are then attached to `User` objects 
 * and `Template` objects, forming ProcessWire's role-based access control system. Outside of roles, 
 * Permission objects are managed with the `$permissions` API variable. 
 * #pw-body
 * 
 * @property int $id Numeric page ID of the permission. 
 * @property string $name Name of the permission. 
 * @property string $title Short description of what the permission is for. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 */

class Permission extends Page {

	/**
	 * Static relations between permissions
	 * 
	 * All other relations follow the name format (i.e. page-edit-created assumes page-edit as parent)
	 * 
	 * @var array
	 * 
	 */
	static protected $parentPermissions = array(
		'page-view' => 'none',
		'page-lister' => 'none',
		'page-edit' => 'none', 
		'user-admin' => 'page-edit',
		'lang-edit' => 'page-edit',
		'page-lock' => 'page-edit',
		'page-hide' => 'page-edit',
		'page-clone' => 'page-edit',
		'page-move' => 'page-edit',
		'page-template' => 'page-edit',
		'page-sort' => 'page-edit',
		'page' => 'page-edit', // all page-* permissions
		);

	/**
	 * Create a new Permission page in memory. 
	 *
	 * @param Template $tpl Template object this page should use. 
	 *
	 */
	public function __construct(Template $tpl = null) {
		parent::__construct($tpl); 
		if(is_null($tpl)) $this->template = $this->wire('templates')->get('permission'); 
		$this->parent = $this->wire('pages')->get($this->wire('config')->permissionsPageID); 
	}

	/**
	 * Return the immediate parent permission of this permission or NullPage if no parent permission.
	 * 
	 * For permissions, parents relations are typically by name. For instance, page-edit is the parent of page-edit-created.
	 * But all page-* permissions are assumed to have page-edit as parent, except for page-view. 
	 * 
	 * #pw-internal
	 * 
	 * @return Permission|NullPage
	 * 
	 */
	public function getParentPermission() {
		
		$name = $this->name; 
		$permissions = $this->wire('permissions');
		$permission = null;
		
		do {
			// first check if we have a static definition for this permission
			if(isset(self::$parentPermissions[$name])) {
				$parentName = self::$parentPermissions[$name];
				if($parentName == 'none') break; // NullPage
				$permission = $permissions->get($parentName); 
				if($permission->id) break;
			} 
			// reduce permission by one part, to a potential parent name
			$parts = explode('-', $name); 
			array_pop($parts); 
			if(!count($parts)) break;
			$name = implode('-', $parts);
			$permission = $permissions->get($name); 
		} while(!$permission->id); 
		
		if(is_null($permission)) $permission = $this->wire('pages')->newNullPage();
		
		return $permission;
	}

	/**
	 * Get the root parent permission
	 * 
	 * #pw-internal
	 * 
	 * @return Permission|NullPage
	 * @throws WireException
	 * 
	 */
	public function getRootParentPermission() {
		if(isset(self::$parentPermissions[$this->name])) {
			$name = self::$parentPermissions[$this->name]; 
			if($name == 'none') return $this->wire('pages')->newNullPage();
		}
		$parts = explode('-', $this->name);	
		if(count($parts) < 2) return $this->wire('pages')->newNullPage();
		$name = "$parts[0]-$parts[1]";
		if(isset(self::$parentPermissions[$name])) {
			$name = self::$parentPermissions[$name];
			if($name == 'none') return $this->wire('pages')->newNullPage();
			return $this->wire('permissions')->get($name);
		}
		if($parts[0] == 'page') $name = 'page-edit';
		return $this->wire('permissions')->get($name);
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
		return $this->wire('permissions');
	}
}


