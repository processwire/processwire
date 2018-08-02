<?php namespace ProcessWire;

/**
 * The Permissions class serves as the $permissions API variable. 
 * 
 * #pw-summary Provides management of all Permission pages independent of users, for access control. 
 * 
 * @method PageArray find($selector) Return the permissions(s) matching the the given selector query.
 * @method Permission|NullPage get($selector) Return permission by given name, numeric ID or a selector string.
 * @method array getOptionalPermissions($omitInstalled = true) #pw-internal
 * @method array saveReady(Page $page) Hook called just before a Permission is saved #pw-hooker
 * @method void saved(Page $page, array $changes = array(), $values = array()) #pw-hooker
 * @method void added(Page $page) Hook called just after a Permission is added #pw-hooker
 * @method void deleteReady(Page $page) Hook called before a Permission is deleted #pw-hooker
 * @method void deleted(Page $page) Hook called after a permission is deleted #pw-hooker
 *
 */
class Permissions extends PagesType {
	
	const cacheName = 'Permissions.names';

	/**
	 * Array of permissions name => id, for runtime caching purposes
	 * 
	 * @var array
	 * 
	 */
	protected $permissionNames = array();

	/**
	 * Optional permission names that when not installed, are delegated to another
	 * 
	 * Does not include runtime-only permissions (page-add, page-create) which are delegated to page-edit
	 * 
	 * @var array of permission name => delegated permission name
	 * 
	 */
	protected $delegatedPermissions = array(
		'page-publish' => 'page-edit',
		'page-hide' => 'page-edit',
		'page-lock' => 'page-edit',
		'page-edit-created' => 'page-edit',
		'page-edit-trash-created' => 'page-delete',
		'page-edit-images' => 'page-edit',
		'page-rename' => 'page-edit',
		'user-admin-all' => 'user-admin',
	);

	/**
	 * Permissions that can reduce existing access upon installation
	 * 
	 * @var array
	 * 
	 */
	protected $reducerPermissions = array(
		'page-hide',
		'page-publish',
		'page-edit-created',
		'page-edit-images',
		'page-rename',
		'page-edit-lang-',
		'page-edit-lang-none',
		'user-admin-',
		'user-admin-all',
		'page-lister-',
	);

	/**
	 * Does the system have a permission with the given name?
	 * 
	 * ~~~~~
	 * // Check if page-publish permission is available
	 * if($permissions->has('page-publish')) {
	 *   // system has the page-publish permission installed
	 * }
	 * ~~~~~
	 * 
	 * @param string $name Name of permission
	 * @return bool True if system has a permission with this name, or false if not. 
	 * 
	 */
	public function has($name) {
		
		if($name == 'page-add' || $name == 'page-create') return true; // runtime only permissions
		
		if(empty($this->permissionNames)) {

			$cache = $this->wire('cache');
			$names = $cache->get(self::cacheName);

			if(empty($names)) {
				$names = array();
				foreach($this as $permission) {
					$names[$permission->name] = $permission->id;
				}
				$cache->save(self::cacheName, $names, WireCache::expireNever);
			}

			$this->permissionNames = $names;
		}
			
		return isset($this->permissionNames[$name]);
	}


	/**
	 * Save a Permission
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Permission|Page $page
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Page $page) {
		return parent::___save($page);
	}

	/**
	 * Permanently delete a Permission
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Permission|Page $page Permission to delete
	 * @param bool $recursive If set to true, then this will attempt to delete any pages below the Permission too. 
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___delete(Page $page, $recursive = false) {
		return parent::___delete($page, $recursive);
	}

	/**
	 * Add a new Permission with the given name and return it
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $name Name of permission you want to add, i.e. "hello-world"
	 * @return Permission|Page|NullPage Returns a Permission page on success, or a NullPage on error
	 *
	 */
	public function ___add($name) {
		return parent::___add($name);
	}


	/**
	 * Get an associative array of all optional permissions
	 * 
	 * Returned permissions are an associative array in the format `['name' => 'label']`. 
	 * 
	 * #pw-internal
	 * 
	 * @param bool $omitInstalled Specify false to include all optional permissions, whether already installed or not.
	 * @return array
	 *
	 */
	public function ___getOptionalPermissions($omitInstalled = true) {

		$a = array(
			'page-hide' => $this->_('Hide/unhide pages'),
			'page-publish' => $this->_('Publish/unpublish pages or edit already published pages'),
			'page-edit-created' => $this->_('Edit only pages user has created'),
			'page-edit-trash-created' => $this->_('User can trash pages they created (without page-delete permission).'), 
			'page-edit-images' => $this->_('Use the image editor to manipulate (crop, resize, etc.) images'),
			'page-rename' => $this->_('Change the name of published pages they are allowed to edit'),
			'user-admin-all' => $this->_('Administer users in any role (except superuser)'),
		);

		foreach($this->wire('roles') as $role) {
			if($role->name == 'guest' || $role->name == 'superuser') continue;
			$a["user-admin-$role->name"] = sprintf($this->_('Administer users in role: %s'), $role->name);
		}

		$languages = $this->wire('languages');
		if($languages) {
			$label = $this->_('Edit fields on a page in language: %s');
			$alsoLabel = $this->_('(also required to create or delete pages)');
			$a["page-edit-lang-default"] = sprintf($label, 'default') . ' ' . $alsoLabel;
			$a["page-edit-lang-none"] = $this->_('Edit single-language fields on multi-language page'); 
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$a["page-edit-lang-$language->name"] = sprintf($label, $language->name);
			}
			if(!$this->has('lang-edit')) {
				$a["lang-edit"] = $this->_('Administer languages and static translation files');
			}
		}

		if($omitInstalled) {
			// remove permissions that are already in the system
			foreach($a as $name => $label) {
				if($this->has($name)) unset($a[$name]);
			}
		}

		ksort($a);

		return $a;
	}

	/**
	 * Get permission names that can reduce existing access, when installed
	 * 
	 * Returned permission names that end with a "-" indicate that given permission name is a prefix
	 * that applies for anything that appears after it. 
	 * 
	 * @return array Array of permission names where both index and value are permission name
	 * 
	 */
	public function getReducerPermissions() {
		$a = $this->reducerPermissions; 
		$languages = $this->wire('languages');
		if($languages && $this->wire('modules')->isInstalled('LanguageSupportFields')) {
			foreach($languages as $language) {
				$a[] = "page-edit-lang-$language->name";
			}
		}
		foreach($this->wire('roles') as $role) {
			$a[] = "user-admin-$role->name";
		}
		$a = array_flip($a);
		foreach($a as $k => $v) $a[$k] = $k;
		return $a;
	}
	

	/**
	 * Return array of permission names that are delegated to another when not installed
	 * 
	 * #pw-internal
	 * 
	 * @return array of permission name => delegated permission name
	 * 
	 */
	public function getDelegatedPermissions() {
		return $this->delegatedPermissions;
	}

	/**
	 * Returns all installed Permission pages and enables foreach() iteration of $permissions
	 * 
	 * ~~~~~
	 * // Example of listing all permissions
	 * foreach($permissions as $permission) {
	 *   echo "<li>$permission->name</li>";
	 * }
	 * ~~~~~
	 *
	 * @return \ArrayObject
	 *
	 */
	public function getIterator() {
		return parent::getIterator();
	}

	/**
	 * Hook called when a permission is saved
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was saved
	 * @param array $changes Array of changed field names
	 * @param array $values Array of changed field values indexed by name (when enabled)
	 * @throws WireException
	 * 
	 */

	public function ___saved(Page $page, array $changes = array(), $values = array()) {
		$this->wire('cache')->delete(self::cacheName);
		parent::___saved($page, $changes, $values);
	}

	/**
	 * Hook called when a permission is deleted
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page Page that was deleted
	 * @throws WireException
	 * 
	 */
	public function ___deleted(Page $page) {
		$this->wire('cache')->delete(self::cacheName);
		parent::___deleted($page);
	}
}
