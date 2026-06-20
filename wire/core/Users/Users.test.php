<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $users, $roles and $permissions API variables
 *
 */
class WireTest_Users extends WireTest {

	protected $prefix = 'wiretests_rbac';
	protected $userNames = array();
	protected $roleNames = array();
	protected $permissionNames = array();

	public function init() {
		$this->cleanup();
	}

	public function execute() {
		$this->testManagersAndBuiltIns();
		$this->testPagesTypeApi();
		$this->testRolePermissionAssignments();
		$this->testPermissionHelpers();
		$this->testUserPermissionsAndIdentity();
		$this->testAdminThemeByRole();
	}

	public function finish() {
		$this->cleanup();
	}

	protected function testManagersAndBuiltIns() {
		$users = $this->wire()->users;
		$roles = $this->wire()->roles;
		$permissions = $this->wire()->permissions;
		$config = $this->wire()->config;

		$this->check('$users is Users', true, $users instanceof Users);
		$this->check('$roles is Roles', true, $roles instanceof Roles);
		$this->check('$permissions is Permissions', true, $permissions instanceof Permissions);
		$this->check('$users extends PagesType', true, $users instanceof PagesType);
		$this->check('$roles extends PagesType', true, $roles instanceof PagesType);
		$this->check('$permissions extends PagesType', true, $permissions instanceof PagesType);

		$guestUser = $users->getGuestUser();
		$guestRole = $roles->getGuestRole();
		$pageView = $permissions->get('page-view');
		$superuser = $users->get($config->superUserPageID);

		$this->check('getGuestUser() returns guest user', $config->guestUserPageID, $guestUser->id);
		$this->check('getGuestRole() returns guest role', $config->guestUserRolePageID, $guestRole->id);
		$this->check('$roles->get(guest) returns cached guest role', $guestRole->id, $roles->get('guest')->id);
		$this->check('$permissions->get(page-view) returns Permission', true, $pageView instanceof Permission);
		$this->check('guest user has guest role', true, $guestUser->hasRole($guestRole));
		$this->check('guest user isGuest()', true, $guestUser->isGuest());
		$this->check('guest user is not logged in', false, $guestUser->isLoggedin());
		$this->check('superuser isSuperuser()', true, $superuser->isSuperuser());
		$this->check('current user matches $user API variable', $this->wire()->user->id, $users->getCurrentUser()->id);
	}

	protected function testPagesTypeApi() {
		$users = $this->wire()->users;
		$roles = $this->wire()->roles;
		$permissions = $this->wire()->permissions;

		$unsavedUser = $users->new(array(
			'name' => $this->userName('unsaved'),
			'email' => 'unsaved@example.com',
		));
		$this->check('$users->new(array) returns User', true, $unsavedUser instanceof User);
		$this->check('$users->new(array) does not save', 0, $unsavedUser->id);
		$this->check('$users->new(array) sets user template', 'user', $unsavedUser->template->name);
		$this->check('$users->new(array) sets users parent', $this->wire()->config->usersPageID, $unsavedUser->parent_id);

		$unsavedRole = $roles->new(array('name' => $this->roleName('unsaved')));
		$this->check('$roles->new(array) returns Role', true, $unsavedRole instanceof Role);
		$this->check('$roles->new(array) does not save', 0, $unsavedRole->id);
		$this->check('$roles->new(array) sets role template', 'role', $unsavedRole->template->name);

		$unsavedPermission = $permissions->new(array(
			'name' => $this->permissionName('unsaved'),
			'title' => 'Unsaved permission',
		));
		$this->check('$permissions->new(array) returns Permission', true, $unsavedPermission instanceof Permission);
		$this->check('$permissions->new(array) does not save', 0, $unsavedPermission->id);
		$this->check('$permissions->new(array) sets permission template', 'permission', $unsavedPermission->template->name);

		$savedUser = $this->createUser('saved', array('email' => 'saved@example.com'));
		$savedRole = $this->createRole('saved');
		$savedPermission = $this->createPermission('saved', 'Saved permission');

		$this->check('$users->new(name, options) creates and saves user', true, $savedUser->id > 0);
		$this->check('$users->new(name, options) applies email', 'saved@example.com', $users->get($savedUser->id)->email);
		$this->check('$roles->new(name) creates and saves role', true, $savedRole->id > 0);
		$this->check('$permissions->new(name, options) creates and saves permission', true, $savedPermission->id > 0);
		$this->check('$users->get(name) returns saved user', $savedUser->id, $users->get($savedUser->name)->id);
		$this->check('$users->get(id) returns saved user', $savedUser->name, $users->get($savedUser->id)->name);
		$this->check('$users->get(selector) returns saved user', $savedUser->id, $users->get("email=saved@example.com")->id);
		$this->check('$roles->get(name) returns saved role', $savedRole->id, $roles->get($savedRole->name)->id);
		$this->check('$permissions->get(name) returns saved permission', $savedPermission->id, $permissions->get($savedPermission->name)->id);
		$this->check('$users->get(missing) returns NullPage', true, $users->get($this->userName('missing')) instanceof NullPage);

		$this->check('$users->find() includes saved user', true, $users->find("name=$savedUser->name")->has($savedUser));
		$this->check('$users->findIDs() includes saved user ID', true, in_array($savedUser->id, $users->findIDs("name=$savedUser->name")));
		$this->check('$users->count(selector) counts saved user', 1, $users->count("name=$savedUser->name"));
		$this->check('$roles is iterable over Role objects', true, $this->firstRoleIsRole());
		$this->check('$permissions is iterable over Permission objects', true, $this->firstPermissionIsPermission());

		$addUser = $users->add($this->userName('add'));
		$this->userNames[] = $addUser->name;
		$addRole = $roles->add($this->roleName('add'));
		$this->roleNames[] = $addRole->name;
		$addPermission = $permissions->add($this->permissionName('add'));
		$this->permissionNames[] = $addPermission->name;

		$this->check('$users->add() creates User', true, $addUser instanceof User && $addUser->id > 0);
		$this->check('$roles->add() creates Role', true, $addRole instanceof Role && $addRole->id > 0);
		$this->check('$permissions->add() creates Permission', true, $addPermission instanceof Permission && $addPermission->id > 0);

		$savedUser->of(false);
		$savedUser->email = 'saved-updated@example.com';
		$this->check('$users->save() updates user', true, $users->save($savedUser));
		$this->check('$users->save() persisted updated email', 'saved-updated@example.com', $users->get($savedUser->id)->email);
	}

	protected function testRolePermissionAssignments() {
		$roles = $this->wire()->roles;
		$permissions = $this->wire()->permissions;
		$role = $this->createRole('assigned');
		$permission = $this->createPermission('assigned', 'Assigned permission');

		$role = $roles->get($role->id);
		$this->check('loaded Role has page-view permission', true, $role->hasPermission('page-view'));
		$this->check('Role::addPermission(name) returns true', true, $role->addPermission($permission->name));
		$role->save();
		$role = $roles->get($role->id);
		$this->check('Role::hasPermission(name) detects added permission', true, $role->hasPermission($permission->name));
		$this->check('Role::hasPermission(id) detects added permission', true, $role->hasPermission($permission->id));
		$this->check('Role::hasPermission(object) detects added permission', true, $role->hasPermission($permission));
		$this->check('Role::addPermission(missing) returns false', false, $role->addPermission($this->permissionName('missing')));
		$this->check('Role::removePermission(name) returns true', true, $role->removePermission($permission->name));
		$role->save();
		$role = $roles->get($role->id);
		$this->check('Role::removePermission() persists removal', false, $role->hasPermission($permission->name));
		$this->check('Role::removePermission(missing) returns false', false, $role->removePermission($this->permissionName('missing')));

		$this->check('Role::addPermission(object) restores permission', true, $role->addPermission($permission));
		$role->save();
		$role = $roles->get($role->id);
		$this->check('Role permission restore persisted', true, $role->hasPermission($permission->name));
		$this->check('Permission::getParentPermission() returns NullPage for custom permission', true, $permission->getParentPermission() instanceof NullPage);
		$this->check('Permission::getRootParentPermission() returns NullPage for custom permission', true, $permission->getRootParentPermission() instanceof NullPage);

		$pagePublish = $permissions->get('page-publish');
		if($pagePublish->id) {
			$this->check('Permission::getParentPermission() detects page-edit parent', 'page-edit', $pagePublish->getParentPermission()->name);
			$this->check('Permission::getRootParentPermission() detects page-edit root', 'page-edit', $pagePublish->getRootParentPermission()->name);
		}
	}

	protected function testPermissionHelpers() {
		$permissions = $this->wire()->permissions;
		$permission = $this->createPermission('helpers', 'Helpers permission');

		$nameIds = $permissions->getPermissionNameIds();
		$pageNameIds = $permissions->getPermissionNameIds('page-');
		$optional = $permissions->getOptionalPermissions(false);
		$reducers = $permissions->getReducerPermissions();
		$delegated = $permissions->getDelegatedPermissions();

		$this->check('$permissions->has(page-view) returns true', true, $permissions->has('page-view'));
		$this->check('$permissions->has(page-add) returns true for runtime permission', true, $permissions->has('page-add'));
		$this->check('$permissions->has(page-create) returns true for runtime permission', true, $permissions->has('page-create'));
		$this->check('$permissions->has(missing) returns false', false, $permissions->has($this->permissionName('missing')));
		$this->check('getPermissionNameIds() includes custom permission', $permission->id, $nameIds[$permission->name]);
		$this->check('getPermissionNameIds(prefix) includes page-view', true, isset($pageNameIds['page-view']));
		$this->check('getPermissionNameIds(prefix) filters names', true, $this->allKeysStartWith($pageNameIds, 'page-'));
		$this->check('getOptionalPermissions(false) includes page-hide', true, isset($optional['page-hide']));
		$this->check('getReducerPermissions() includes page-hide', 'page-hide', $reducers['page-hide']);
		$this->check('getDelegatedPermissions() maps page-publish to page-edit', 'page-edit', $delegated['page-publish']);

		$delegatedName = $permissions->has('page-publish') ? '' : 'page-edit';
		$this->check('getDelegatedPermission() returns expected page-publish delegate', $delegatedName, $permissions->getDelegatedPermission('page-publish'));
	}

	protected function testUserPermissionsAndIdentity() {
		$users = $this->wire()->users;
		$role = $this->createRole('userrole');
		$permission = $this->createPermission('userpermission', 'User permission');
		$user = $this->createUser('member', array('email' => 'member@example.com'));

		$role->addPermission($permission);
		$role->save();

		$user->of(false);
		$this->check('User::addRole(name) returns true', true, $user->addRole($role->name));
		$user->save();
		$user = $users->get($user->id);

		$this->check('new User has guest role after save/load', true, $user->hasRole('guest'));
		$this->check('User::hasRole(name) detects added role', true, $user->hasRole($role->name));
		$this->check('User::hasRole(id) detects added role', true, $user->hasRole($role->id));
		$this->check('User::hasRole(object) detects added role', true, $user->hasRole($role));
		$this->check('User::addRole(missing) returns false', false, $user->addRole($this->roleName('missing')));
		$this->check('User::hasPermission() aggregates role permissions', true, $user->hasPermission($permission->name));
		$this->check('User::getPermissions() includes role permission', true, $user->getPermissions()->has($permission));
		$this->check('temporary user is not guest', false, $user->isGuest());
		$this->check('temporary user is not current logged-in user', false, $user->isLoggedin());
		$this->check('temporary user is not superuser', false, $user->isSuperuser());
		$this->check('User::editUrl() points at access users editor', '/access/users/edit/', $user->editUrl(), '*=');
		$this->check('User::hasTfa() returns false when not configured', false, $user->hasTfa());

		$this->check('User::removeRole(name) returns true', true, $user->removeRole($role->name));
		$user->save();
		$user = $users->get($user->id);
		$this->check('User::removeRole() persists removal', false, $user->hasRole($role->name));
		$this->check('User::removeRole(missing) returns false', false, $user->removeRole($this->roleName('missing')));
	}

	protected function testAdminThemeByRole() {
		$users = $this->wire()->users;
		$role = $this->createRole('admintheme');
		$user = $this->createUser('admintheme', array('email' => 'admintheme@example.com'));

		$user->of(false);
		$user->addRole($role);
		$user->save();

		$threw = false;
		try {
			$users->setAdminThemeByRole('NotAnAdminTheme', $role);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('$users->setAdminThemeByRole() rejects invalid admin theme', true, $threw);

		$qty = $users->setAdminThemeByRole('AdminThemeUikit', $role);
		$this->check('$users->setAdminThemeByRole() updates user with role', 1, $qty);
		$this->wire()->pages->uncache($user);
		$user = $users->get($user->id);
		$this->check('$users->setAdminThemeByRole() persisted admin_theme', 'AdminThemeUikit', $user->admin_theme);
	}

	protected function createUser($suffix, array $options = array()) {
		$users = $this->wire()->users;
		$name = $this->userName($suffix);
		$user = $users->get($name);
		if(!$user->id) {
			$options = array_merge(array('email' => "$name@example.com"), $options);
			$user = $users->new($name, $options);
		}
		if(!in_array($name, $this->userNames)) $this->userNames[] = $name;
		return $user;
	}

	protected function createRole($suffix) {
		$roles = $this->wire()->roles;
		$name = $this->roleName($suffix);
		$role = $roles->get($name);
		if(!$role->id) $role = $roles->new($name);
		if(!in_array($name, $this->roleNames)) $this->roleNames[] = $name;
		return $role;
	}

	protected function createPermission($suffix, $title) {
		$permissions = $this->wire()->permissions;
		$name = $this->permissionName($suffix);
		$permission = $permissions->get($name);
		if(!$permission->id) {
			$permission = $permissions->new($name, array('title' => $title));
		}
		if(!in_array($name, $this->permissionNames)) $this->permissionNames[] = $name;
		return $permission;
	}

	protected function firstRoleIsRole() {
		foreach($this->wire()->roles as $role) return $role instanceof Role;
		return false;
	}

	protected function firstPermissionIsPermission() {
		foreach($this->wire()->permissions as $permission) return $permission instanceof Permission;
		return false;
	}

	protected function allKeysStartWith(array $items, $prefix) {
		foreach($items as $key => $value) {
			if(strpos($key, $prefix) !== 0) return false;
		}
		return true;
	}

	protected function userName($suffix) {
		return $this->prefix . '_user_' . $suffix;
	}

	protected function roleName($suffix) {
		return $this->prefix . '_role_' . $suffix;
	}

	protected function permissionName($suffix) {
		return $this->prefix . '_permission_' . $suffix;
	}

	protected function cleanup() {
		$users = $this->wire()->users;
		$roles = $this->wire()->roles;
		$permissions = $this->wire()->permissions;

		foreach($this->allUserNames() as $name) {
			$user = $users->get($name);
			if(!$user->id) continue;
			try {
				$users->delete($user);
			} catch(\Exception $e) {
				// Leave remaining users visible to the next run if cleanup cannot delete them.
			}
		}

		foreach($this->allRoleNames() as $name) {
			$role = $roles->get($name);
			if(!$role->id) continue;
			foreach($users->find("roles=$role, include=all") as $user) {
				/** @var User $user */
				$user->of(false);
				$user->removeRole($role);
				$user->save();
			}
		}

		foreach($this->allPermissionNames() as $name) {
			$permission = $permissions->get($name);
			if(!$permission->id) continue;
			foreach($roles->find("permissions=$permission, include=all") as $role) {
				/** @var Role $role */
				$role->of(false);
				$role->removePermission($permission);
				$role->save();
			}
		}

		foreach($this->allRoleNames() as $name) {
			$role = $roles->get($name);
			if(!$role->id) continue;
			try {
				$roles->delete($role);
			} catch(\Exception $e) {
				// Leave cleanup failures visible when they affect assertions.
			}
		}

		foreach($this->allPermissionNames() as $name) {
			$permission = $permissions->get($name);
			if(!$permission->id) continue;
			try {
				$permissions->delete($permission);
			} catch(\Exception $e) {
				// Leave cleanup failures visible when they affect assertions.
			}
		}
	}

	protected function allUserNames() {
		return array_unique(array_merge($this->userNames, array(
			$this->userName('saved'),
			$this->userName('add'),
			$this->userName('member'),
			$this->userName('admintheme'),
		)));
	}

	protected function allRoleNames() {
		return array_unique(array_merge($this->roleNames, array(
			$this->roleName('saved'),
			$this->roleName('add'),
			$this->roleName('assigned'),
			$this->roleName('userrole'),
			$this->roleName('admintheme'),
		)));
	}

	protected function allPermissionNames() {
		return array_unique(array_merge($this->permissionNames, array(
			$this->permissionName('saved'),
			$this->permissionName('add'),
			$this->permissionName('assigned'),
			$this->permissionName('helpers'),
			$this->permissionName('userpermission'),
		)));
	}
}
