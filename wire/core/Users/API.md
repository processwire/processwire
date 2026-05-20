# Users / $users, Roles / $roles, Permissions / $permissions

Users, roles, and permissions form ProcessWire's role-based access control system.
The `$users`, `$roles`, and `$permissions` API variables are manager classes that each
extend `PagesType` — a `$pages`-like API scoped to a single page type. The `User`,
`Role`, and `Permission` objects extend `Page`.

`$users` is accessible in templates as `$users` or `wire()->users`; in modules as
`$this->wire()->users`. Similarly for `$roles` and `$permissions`.

---

## PagesType — shared manager API

`$users`, `$roles`, and `$permissions` (and `$languages`) all extend `PagesType`. The
following methods are common to all three access-control managers.

### find($selectorString)

Find all items of this type matching the given selector.

- **Arguments:** `find(string $selectorString, array $options = [])`
- **Returns:** `PageArray`
- Access control is bypassed (`findAll=true` implied); all matching items are returned
  regardless of the current user's access level.
- Template and parent constraints for the type are added automatically.

~~~~~php
$editors = $users->find('roles=editor');
$customRoles = $roles->find('name!=guest, name!=superuser');
~~~~~

---

### findIDs($selectorString)

Find matching page IDs without loading the pages.

- **Arguments:** `findIDs(string $selectorString, array $options = [])`
- **Returns:** `array`

~~~~~php
$userIds = $users->findIDs('roles=editor');
~~~~~

---

### get($selectorString)

Get a single item by ID, name, or selector string.

- **Arguments:** `get(string|int $selectorString)`
- **Returns:** `User|Role|Permission|NullPage`
- Returns a `NullPage` if not found.
- A bare integer or digit string is treated as a page ID.
- A string with no `=` and no `/` is treated as a page name.

~~~~~php
$admin = $users->get('admin');         // by name
$admin = $users->get(41);             // by ID
$role = $roles->get('name=editor');  // by selector
~~~~~

All three managers support direct iteration:

~~~~~php
foreach($users as $u) echo $u->name . "\n";
foreach($roles as $role) echo $role->name . "\n";
foreach($permissions as $permission) echo $permission->name . "\n";
~~~~~

---

### add($name)

Create, save, and return a new item with the given name.

- **Arguments:** `add(string $name)`
- **Returns:** `User|Role|Permission|NullPage` — returns `NullPage` on error
- This is a hookable method (`___add()`).

~~~~~php
$john = $users->add('john');
$editor = $roles->add('editor');
$permission = $permissions->add('page-publish');
~~~~~

---

### new($name, $options)

Create a new item, optionally saving it. Available since 3.0.263.

- **Arguments:** `new(string|array $nameOrOptions = null, array $options = [])`
- **Returns:** `User|Role|Permission`
- This is a hookable method (`___new()`).
- Pass a **string name** as the first argument to create and save in one step.
- Pass an **array** or nothing to create in memory only (call `save()` yourself).

~~~~~php
// Create and save immediately (string name)
$john = $users->new('john');
$editor = $roles->new('editor');
$mike = $users->new('mike', ['email' => 'mike@example.com']);

// Create in memory only (set fields, then save manually)
$john = $users->new();
$john->name = 'john';
$john->pass = 'secret123';
$john->addRole('editor');
$john->save();
~~~~~

---

### save($page)

Save a user, role, or permission.

- **Arguments:** `save(Page $page)`
- **Returns:** `bool`
- Equivalent to calling `$page->save()`.
- This is a hookable method (`___save()`).

~~~~~php
$users->save($someUser);
// or equivalently:
$u->save();
~~~~~

---

### delete($page)

Permanently delete a user, role, or permission.

- **Arguments:** `delete(Page $page, bool $recursive = false)`
- **Returns:** `bool`
- This is a hookable method (`___delete()`).

~~~~~php
$users->delete($someUser);
~~~~~

---

### count($selectorString)

Return the number of items, optionally filtered by a selector.

- **Arguments:** `count(string $selectorString = '', array $options = [])`
- **Returns:** `int`

~~~~~php
$total = $users->count();
$numEditors = $users->count('roles=editor');
~~~~~

---

### Hooks

`User`, `Role`, and `Permission` inherit the full set of `Page` lifecycle hooks. Use the
class name to register a static hook that fires for all instances of that type:

~~~~~php
$wire->addHookAfter('User::saved', function(HookEvent $event) {
    $user = $event->object; // User object
    $changes = $event->arguments(0); // array of changed field names
});

$wire->addHookAfter('Role::deleted', function(HookEvent $event) {
    $role = $event->object; // Role object
});
~~~~~

Available hooks (all inherited from `Page`):

| Hook                | Arguments                               | Fires                                                                                           |
|---------------------|-----------------------------------------|-------------------------------------------------------------------------------------------------|
| `saveReady`         | `array $changes, string or false $name` | Just before saving; `$name` is a field name when saving a single field, `false` for a full save |
| `saved`             | `array $changes, string or false $name` | After saving                                                                                    |
| `addReady`          | —                                       | Just before a new page is added                                                                 |
| `added`             | —                                       | After a new page is added                                                                       |
| `deleteReady`       | `array $options`                        | Before deleting                                                                                 |
| `deleted`           | `array $options`                        | After deleting                                                                                  |
| `renameReady`       | `string $oldName, string $newName`      | Before a name change                                                                            |
| `renamed`           | `string $oldName, string $newName`      | After a name change                                                                             |
| `moveReady`         | `Page $oldParent, Page $newParent`      | Before moving to a new parent                                                                   |
| `moved`             | `Page $oldParent, Page $newParent`      | After moving to a new parent                                                                    |
| `cloneReady`        | `Page $copy`                            | Before cloning                                                                                  |
| `cloned`            | `Page $copy`                            | After cloning                                                                                   |
| `editReady`         | `InputfieldWrapper $form`               | When the admin edit form is being built                                                         |
| `addStatusReady`    | `string $name, int $value`              | Before a status flag is added                                                                   |
| `addedStatus`       | `string $name, int $value`              | After a status flag is added                                                                    |
| `removeStatusReady` | `string $name, int $value`              | Before a status flag is removed                                                                 |
| `removedStatus`     | `string $name, int $value`              | After a status flag is removed                                                                  |

---

## Users / $users

`Users` extends `PagesType` and manages all `User` objects. It also maintains the
current user (`$user` API variable).

### $users->get($selectorString)

Get a user by name, ID, or selector string.

- **Returns:** `User|NullPage`

~~~~~php
$admin = $users->get('admin');
$found = $users->get('email=ryan@example.com');
~~~~~

---

### $users->getCurrentUser()

Return the currently logged-in user (same as the `$user` API variable).

- **Returns:** `User`
- Returns the guest user when no user is logged in.

~~~~~php
$current = $users->getCurrentUser();
// equivalent to: $user
~~~~~

---

### $users->getGuestUser()

Return the guest user account (the non-logged-in user, cached).

- **Returns:** `User`

~~~~~php
$guest = $users->getGuestUser();
~~~~~

---

### $users->setAdminThemeByRole($adminTheme, $role)

Set the admin theme for all users that have the given role.

- **Arguments:** `setAdminThemeByRole(AdminTheme|string $adminTheme, Role $role)`
- **Returns:** `int` — number of users updated
- Throws `WireException` if `$adminTheme` is not a valid admin theme module.
- Available since 3.0.176.

~~~~~php
$role = $roles->get('editor');
$qty = $users->setAdminThemeByRole('AdminThemeUikit', $role);
echo "Updated $qty users";
~~~~~

---

## User / $user

`User` extends `Page` and represents an individual user. The `$user` API variable (an instance of `User`) holds
the current user. Every `User` is guaranteed to have at least the `guest` role. Users that are not logged-in
are represented by the user named `guest` with `id` of `$config->guestUserPageID` (default=`40`). 

### Built-in fields/properties

| Property              | Type                  | Description                                                                                                    |
|-----------------------|-----------------------|----------------------------------------------------------------------------------------------------------------|
| `$user->id`           | `int`                 | User ID                                                                                                        
| `$user->name`         | `string`              | Username                                                                                                       |
| `$user->email`        | `string`              | Email address                                                                                                  |
| `$user->pass`         | `Password`            | Set to a plain-text string to change the password; getting returns the hashed value (not useful to read)       |
| `$user->roles`        | `PageArray`           | Roles assigned to this user                                                                                    |
| `$user->language`     | `Language` or `null`  | User's language; falls back to the default language when not set or `null` when LanguageSupport not installed |
| `$user->admin_theme`  | `string`              | Admin theme class name                                                                                         |

In addition to the built-in fields/properties above, `User` has all of the built-in fields/properties of `Page`, and any custom
fields added to the `user` template. 

**Changing a password:**

~~~~~php
$u = $users->get('john');
$u->setAndSave('pass', 'new-plain-text-password'); // hashes on save
~~~~~

---

### $user->hasRole($role)

Return whether the user has the given role.

- **Arguments:** `hasRole(string|int|Role $role)` — name, ID, or Role object
- **Returns:** `bool`

~~~~~php
if($user->hasRole('editor')) {
    // user has the editor role
}
~~~~~

---

### $user->addRole($role) / $user->removeRole($role)

Add or remove a role. Remember to call `save()` to persist the change.

- **Arguments:** Role name, ID, or `Role` object
- **Returns:** `bool` — `false` if the role was not recognized

~~~~~php
$user->addRole('editor');
$user->removeRole('subscriber');
$user->save();
~~~~~

---

### $user->hasPermission($name, $context)

Return whether the user has the given permission, accounting for all their roles.

- **Arguments:** `hasPermission(string|int|Permission $name, Page|Template|bool|string $context = null)`
- **Returns:** `bool|array`
- Superusers return `true` for boolean permission checks. When `$context` is `'templates'`,
  superusers return the matching template array like other users.

~~~~~php
// Global permission check (across all roles)
if($user->hasPermission('page-publish')) { ... }

// Check permission in the context of a specific page
if($user->hasPermission('page-edit', $page)) { ... }

// Check permission in the context of a specific template
if($user->hasPermission('page-edit', $templates->get('blog-post'))) { ... }

// Return true if the user has the permission or permission added on any template
if($user->hasPermission('page-edit', true)) { ... }

// Return an array of Template objects where the user has the permission
$tpls = $user->hasPermission('page-edit', 'templates');
~~~~~

---

### $user->getPermissions($page)

Get all permissions this user has, optionally scoped to a page.

- **Arguments:** `getPermissions(Page $page = null)`
- **Returns:** `PageArray` of `Permission` objects
- Returns all permissions for superusers.

~~~~~php
$allPermissions = $user->getPermissions();
$pagePermissions = $user->getPermissions($page);
foreach($pagePermissions as $permission) {
    echo $permission->name . "\n";
}
~~~~~

---

### $user->isSuperuser()

Return whether the user has the superuser role.

- **Returns:** `bool`
- Result is cached per request.

~~~~~php
if($user->isSuperuser()) {
    // unrestricted access
}
~~~~~

---

### $user->isGuest()

Return whether this is the non-logged-in guest user.

- **Returns:** `bool`

~~~~~php
if($user->isGuest()) {
    // user is not logged in
}
~~~~~

---

### $user->isLoggedin()

Return whether this `User` object is the current logged-in user.

- **Returns:** `bool`
- Returns `false` for the guest user.
- Useful when you have a `User` object obtained from `$users->get()` and need to know
  whether it is the same person who is currently browsing the site.

~~~~~php
$someUser = $users->get(123);
if($someUser->isLoggedin()) {
    // this user is currently logged in
}
~~~~~

---

### $user->setLanguage($language)

Set the user's language without tracking it as a change (it will not be saved).
This is how ProcessWire sets the current user's language when the URL or page dictates the language.

- **Arguments:** `setLanguage(Language|string|int $language)` — Language object, name, or ID
- **Returns:** `$this`
- Silently does nothing if LanguageSupport is not installed.
- Throws `WireException` for an unrecognized language when LanguageSupport is installed.
- Available since 3.0.186.

~~~~~php
$user->setLanguage('default');
$user->setLanguage($languages->get('de'));
~~~~~

---

### $user->hasTfa($getInstance)

Return whether two-factor authentication is enabled for this user.

- **Arguments:** `hasTfa(bool $getInstance = false)`
- **Returns:** `false|string|Tfa` — `false` if not enabled; the TFA module name (string)
  if enabled; a `Tfa` module instance if `$getInstance = true`
- Available since 3.0.162.

~~~~~php
$tfa = $user->hasTfa();
if($tfa) {
    echo "TFA enabled: $tfa"; // e.g. "TfaEmail"
}
~~~~~

---

## Roles / $roles

`Roles` extends `PagesType` and manages all `Role` objects. All common `PagesType`
methods apply (`find`, `get`, `add`, `save`, `delete`, `count`, iteration, hooks).

### $roles->get($selectorString)

Get a role by name, ID, or selector.

- **Returns:** `Role|NullPage`
- The name `'guest'` is optimized to return the cached guest role directly.

~~~~~php
$editor = $roles->get('editor');
$guest  = $roles->get('guest');
~~~~~

---

### $roles->getGuestRole()

Return the guest role (cached).

- **Returns:** `Role`

~~~~~php
$guestRole = $roles->getGuestRole();
~~~~~

**Note:** When a role is deleted, ProcessWire automatically removes it from all template
access settings (`editRoles`, `viewRoles`, `addRoles`, `createRoles`).

---

## Role

`Role` extends `Page` and represents a named group of permissions that can be assigned
to users.

### $role->permissions

A `PageArray` of `Permission` objects assigned to this role. Every role is guaranteed
to have at least the `page-view` permission.

~~~~~php
foreach($role->permissions as $permission) {
    echo $permission->name . "\n";
}
~~~~~

---

### $role->hasPermission($permission, $context)

Return whether this role has the given permission, optionally within a Page or Template
context.

- **Arguments:** `hasPermission(string|int|Permission $permission, Page|Template $context = null)`
- **Returns:** `bool`
- Without `$context`, checks the role's own permission list.
- With a `Page` or `Template` context, also considers template access settings
  (`editRoles`, `viewRoles`, etc.).

~~~~~php
if($role->hasPermission('page-edit')) {
    // role has the page-edit permission
}
if($role->hasPermission('page-edit', $page)) {
    // role has page-edit access on this page's access template
}
~~~~~

---

### $role->addPermission($permission) / $role->removePermission($permission)

Add or remove a permission from this role. Remember to call `save()` to persist.

- **Arguments:** Permission name, ID, or `Permission` object
- **Returns:** `bool` — `false` if the permission was not recognized

~~~~~php
$role->addPermission('page-publish');
$role->removePermission('page-delete');
$role->save();
~~~~~

---

## Permissions / $permissions

`Permissions` extends `PagesType` and manages all `Permission` objects. All common
`PagesType` methods apply (`find`, `get`, `add`, `save`, `delete`, `count`, iteration,
hooks).

### $permissions->has($name)

Return whether a permission with the given name is installed.

- **Arguments:** `has(string $name)`
- **Returns:** `bool`
- Always returns `true` for `'page-add'` and `'page-create'` — these are runtime-only
  permissions managed through template access settings, not database records.

~~~~~php
if($permissions->has('page-publish')) {
    // the page-publish permission is installed
}
~~~~~

---

### $permissions->getPermissionNameIds($namePrefix)

Return all installed permission names and their IDs.

- **Arguments:** `getPermissionNameIds(string $namePrefix = '')`
- **Returns:** `array` — `[name => id, ...]`
- Results are cached. Pass a name prefix to filter, e.g. `'page-'` or `'user-'`.
- Available since 3.0.223.

~~~~~php
$all = $permissions->getPermissionNameIds();
// ['page-view' => 36, 'page-edit' => 37, ...]

$pagePerms = $permissions->getPermissionNameIds('page-');
~~~~~

---

## Permission

`Permission` extends `Page` and represents an individual permission.

### Properties

| Property             | Type     | Description                         |
|----------------------|----------|-------------------------------------|
| `$permission->id`    | `int`    | Numeric page ID of the permission   |
| `$permission->name`  | `string` | Permission name, e.g. `'page-edit'` |
| `$permission->title` | `string` | Short description of the permission |

~~~~~php
$permission = $permissions->get('page-publish');
echo $permission->name;  // page-publish
echo $permission->title; // "Publish or unpublish pages"
~~~~~

---

## Notes

- Source files: `wire/core/Users/User.php`, `Users.php`, `Role.php`, `Roles.php`,
  `Permission.php`, `Permissions.php`, and `wire/core/Pages/PagesType.php`.
- `User`, `Role`, and `Permission` all extend `Page` — all `Page` properties and
  methods are available on them.
- `$users`, `$roles`, and `$permissions` all extend `PagesType`. `$languages` does too;
  see the Languages API.md for language-specific methods.
- Every `User` is guaranteed to have at least the `guest` role (added automatically on
  load if missing).
- Every `Role` is guaranteed to have at least the `page-view` permission (added
  automatically on load if missing).
- Checking permissions via `$user->hasPermission()` is preferred over
  `$role->hasPermission()` because it accounts for all of the user's roles at once.
- For page-level access checks, the `Page` methods `editable()`, `viewable()`,
  `publishable()`, etc. are usually more convenient than calling `hasPermission()` directly.
  The Page methods are also preferable since they can be hooked. 
- To hook saves or deletes at the `$pages` level (catching both direct and typed saves),
  use type-matching hooks: `User::saveReady`, `Role::deleted`, etc.
- Outside of CLI mode, users, roles and permissions may have output formatting enabled.
  If enabled, output formatting should be turned off `->of(false)` on User/Role/Permission objects
  before modifying and saving, and restored afterwards. Use `->setAndSave($field, $value)` if
  you don't want to consider output formatting states. 
