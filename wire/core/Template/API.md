# Template

A `Template` connects a set of fields (via a `Fieldgroup`) to pages, determines how those
pages are rendered (via a template file in `/site/templates/`), and controls URL behavior,
access control, caching, and editor behavior.

Template objects are retrieved from the `$templates` API variable:

~~~~~php
$template = $templates->get('basic-page');
~~~~~

See also Templates / $templates API.md file, or 
[Templates documentation](https://processwire.com/api/ref/templates/) 
for creating, saving, deleting, and cloning templates.

---

## Identification

| Property          | Type     | Description                                                                |
|-------------------|----------|----------------------------------------------------------------------------|
| `id`              | `int`    | Numeric database ID                                                        |
| `name`            | `string` | Template name (also used as the template filename base)                    |
| `label`           | `string` | Optional admin label                                                       |
| `flags`           | `int`    | Bitmask of `Template::flag*` constants                                     |
| `modified`        | `int`    | Unix timestamp of last modification to template or file                    |
| `ns`              | `string` | PHP namespace detected in the template file                                |
| `pageClass`       | `string` | Custom Page class for pages using this template; blank means `Page`        |
| `pageLabelField`  | `string` | Field name(s) or markup displayed in the page list; may also embed an icon |

### $template->getLabel($language)

Return the template label for the current (or specified) language. Falls back to
`$template->name` when no label is set.

- **Arguments:** `getLabel(Language|string|int|null $language = null)`
- **Returns:** `string`

~~~~~php
echo $template->getLabel(); // current language label or template name
echo $template->getLabel('spanish'); 
~~~~~

### $template->setLabel($label, $language)

Set the template label for the default (or specified) language. 

- **Arguments:** `setLabel(string $label, Language|string|int|null $language = null)`
- **Returns:** `self`
- **Note:** If no language is specified, the language named `default` is used.

~~~~~php
$template->setLabel('Basic Page'); // default language (not necessarily current)
$template->setLabel('Página básica', 'spanish'); 
~~~~~

---

### $template->getIcon($prefix)

Return the icon name used by this template, as stored in `pageLabelField`.

- **Arguments:** `getIcon(bool $prefix = false)`
- **Returns:** `string` — FontAwesome icon name without `fa-` prefix by default

~~~~~php
$icon = $template->getIcon(); // e.g. "star"
$icon = $template->getIcon(true); // e.g. "fa-star"
~~~~~

---

### $template->setIcon($icon)

Set the icon for this template. The icon is stored as part of `pageLabelField`.

- **Arguments:** `setIcon(string $icon)`
- **Returns:** `$this`

~~~~~php
$template->setIcon('star'); // font-awesome icon name
$template->save();
~~~~~

---

### $template->getNumPages()

Return the number of pages using this template.

- **Returns:** `int`

~~~~~php
echo $template->getNumPages();
~~~~~

---

### $template->getPageClass($withNamespace)

Get the PHP class name used for pages with this template. May differ from `$template->pageClass`
when a custom class is auto-detected at runtime (e.g. `BlogPostPage` for template `blog-post`).

- **Arguments:** `getPageClass(bool $withNamespace = true)`
- **Returns:** `string`
- Available since 3.0.152.

~~~~~php
$class = $template->getPageClass(); // e.g. "ProcessWire\BlogPostPage"
$class = $template->getPageClass(false); // e.g. "BlogPostPage"
~~~~~

#### To create a page class that the template will use: 

1. Ensure that `$config->usePageClasses = true;` in `/site/config.php`.
2. Create a PHP class file in `/site/classes/` using the template name in Pascal Case
   followed by the word `Page`, e.g. template `blog-post` would use `BlogPostPage.php`
3. Create the class in the file you created above:
   ```php
   <?php namespace ProcessWire;  
   class BlogPostPage extends Page {}
   ```
4. Pages using the template `blog-post` will now use class `BlogPostPage` rather than `Page`.
---

### $template->editUrl($http)

Return the admin URL to edit this template's settings.

- **Arguments:** `editUrl(bool $http = false)`
- **Returns:** `string`

~~~~~php
echo $template->editUrl(); // /processwire/setup/template/edit?id=12
~~~~~

---

## Fieldgroup and fields

A Template always has exactly one `Fieldgroup`. Accessing `$template->fieldgroup` (or
the alias `$template->fields`) gives you the `Fieldgroup`, which you can iterate to
access the template's fields.

~~~~~php
// Iterate fields in this template
foreach($template->fieldgroup as $field) {
    echo $field->name . "\n";
}

// Check if template has a specific field
if($template->hasField('body')) {
    $field = $template->fieldgroup->getField('body');
}
~~~~~

### $template->hasField($name)

Return `true` if this template's fieldgroup contains the given field.

- **Arguments:** `hasField(Field|string|int $name)`
- **Returns:** `bool`

---

### $template->setFieldgroup($fieldgroup)

Assign a different `Fieldgroup` to this template. Save the template afterward to persist.
Throws a `WireException` if the template is a system template or has permanent fields.

- **Arguments:** `setFieldgroup(Fieldgroup $fieldgroup)`
- **Returns:** `$this`

~~~~~php
$template->setFieldgroup($fieldgroups->get('other-fieldgroup'));
$template->save();
~~~~~
*This is rarely used in practice as most templates have a matching (by name) fieldgroup.*

---

## Access control

Access control is enabled per-template by setting `$template->useRoles = 1` and then
assigning roles. When `useRoles` is `0` (the default), the template inherits access from
the closest parent page with a template that defines access control.

| Property           | Type        | Description                                                                                                     |
|--------------------|-------------|-----------------------------------------------------------------------------------------------------------------|
| `useRoles`         | `int\|bool` | Enable role-based access control for this template (0=no, 1=yes)                                                |
| `roles`            | `PageArray` | Roles with page-view access (PageArray of Role objects)                                                         |
| `editRoles`        | `array`     | Role IDs that may edit pages                                                                                    |
| `addRoles`         | `array`     | Role IDs that may add children to pages                                                                         |
| `createRoles`      | `array`     | Role IDs that may create new pages                                                                              |
| `rolesPermissions` | `array`     | Per-role permission overrides: `[roleId => [permId, -permId, ...]]` where positive ID adds, negative ID removes |
| `noInherit`        | `int`       | Prevent edit/add/create access from inheriting to non-access-controlled children (0=inherit, 1=no inherit)      |
| `redirectLogin`    | `int`       | Behavior on access denied: 0=404, 1=login page, or page ID/URL to redirect to                                   |
| `guestSearchable`  | `int`       | Pages appear in search results even when guest has no view access (0=no, 1=yes)                                 |

### $template->getRoles($type)

Return a `PageArray` of roles assigned to this template for the given access type.

- **Arguments:** `getRoles(string $type = 'view')`
- **Returns:** `PageArray`
- `$type` may be `'view'` (default), `'edit'`, `'create'`, or `'add'`.

~~~~~php
$viewRoles = $template->getRoles(); // view roles
$editRoles = $template->getRoles('edit');
~~~~~

---

### $template->hasRole($role, $type)

Return `true` if this template has the given role for the specified access type.

- **Arguments:** `hasRole(Role|string|int $role, string $type = 'view')`
- **Returns:** `bool`

~~~~~php
if($template->hasRole('guest')) {
    // guest can view pages using this template
}
if($template->hasRole('editor', 'edit')) {
    // editors can edit
}
~~~~~

---

### $template->addRole($role, $type)

Add a role to this template for the given access type. Save the template afterward.

- **Arguments:** `addRole(Role|int|string $role, string $type = 'view')`
- **Returns:** `$this`

~~~~~php
$template->addRole('guest'); // add view access for guest
$template->addRole('editor', 'edit'); // add edit access for editor
$template->save();
~~~~~

---

### $template->removeRole($role, $type)

Remove a role from this template for the given access type. Specify `'all'` for `$type`
to remove the role from every access type at once.

- **Arguments:** `removeRole(Role|int|string $role, string $type = 'view')`
- **Returns:** `$this`

~~~~~php
$template->removeRole('editor', 'edit');
$template->removeRole('old-role', 'all'); // remove from all access types
$template->save();
~~~~~

---

### $template->addPermissionByRole($permission, $role, $test)

Add a permission override that applies to users with a specific role on pages using
this template. Does not affect access to the `page-view` or `page-edit` permissions
managed through `addRole()`/`removeRole()` — this is for custom permission overrides.

- **Arguments:** `addPermissionByRole(Permission|int|string $permission, Role|int|string $role, bool $test = false)`
- **Returns:** `bool` — `true` if an update was (or would be) made, `false` if not. 
- Specify `true` for `$test` to only test if an update would be made (see return value), without changing anything.

~~~~~php
$template->addPermissionByRole('page-publish', 'editor');
$template->save();
~~~~~

---

### $template->revokePermissionByRole($permission, $role, $test)

Revoke a permission for users with a specific role.

- **Arguments:** `revokePermissionByRole(Permission|int|string $permission, Role|int|string $role, bool $test = false)`
- **Returns:** `bool`
- Specify `true` for `$test` to only test if an update would be made (see return value), without changing anything.

---

## Family settings

Family settings control the parent/child relationships between pages.

| Property          | Type      | Description                                                               |
|-------------------|-----------|---------------------------------------------------------------------------|
| `noChildren`      | `int`     | Prevent child pages (0=children allowed, 1=no children)                   |
| `noParents`       | `int`     | 0=any parent, 1=no new pages, -1=only one page of this template may exist |
| `childTemplates`  | `int[]`   | IDs of templates allowed for children; empty array = any                  |
| `parentTemplates` | `int[]`   | IDs of templates allowed for parents; empty array = any                   |
| `sortfield`       | `string`  | Field name to sort children by; blank = page decides; `sort` = manual     |
| `childNameFormat` | `string`  | Auto-name format for new child pages (date format or `title` or counter)  |

### $template->childTemplates($setValue)

Get or set the templates allowed for children of pages using this template.

- **Arguments:** `childTemplates(array|TemplatesArray|null $setValue = null)`
- **Returns:** `TemplatesArray`
- Applies only if the `noChildren` setting is `0` (children allowed).
- Available since 3.0.153.

~~~~~php
// Get child templates
$allowed = $template->childTemplates();
foreach($allowed as $t) echo $t->name . "\n";

// Set child templates (by name, ID, or Template object)
$template->childTemplates(['blog-post', 'event']);
$template->save();

// Allow any template for children (clear restrictions)
$template->childTemplates([]);
$template->save();
~~~~~

---

### $template->parentTemplates($setValue)

Get or set the templates allowed for parents of pages using this template.

- **Arguments:** `parentTemplates(array|TemplatesArray|null $setValue = null)`
- **Returns:** `TemplatesArray`
- Applies only if the `noParents` setting is `0` or `-1`. 
- Available since 3.0.153.

~~~~~php
$template->parentTemplates(['blog']); // only pages with 'blog' template may be parents
$template->save();
~~~~~

---

### $template->allowNewPages()

Return `true` if new pages using this template may be created, based on `noParents`.

- **Returns:** `bool`

~~~~~php
if($template->allowNewPages()) {
    // safe to create a new page with this template
}
~~~~~

---

### $template->getParentPage($checkAccess)

Return the defined parent page for new pages using this template (based on family
settings). Returns `null` if no parent is defined, `NullPage` if multiple parents match.

- **Arguments:** `getParentPage(bool $checkAccess = false)`
- **Returns:** `Page|NullPage|null`

~~~~~php
$parent = $template->getParentPage();
if($parent && $parent->id) {
    echo "New pages go under: $parent->path";
}
~~~~~

---

### $template->getParentPages($checkAccess)

Return all defined parent pages for this template.

- **Arguments:** `getParentPages(bool $checkAccess = false)`
- **Returns:** `PageArray`

---

## URL settings

| Property           | Type          | Description                                                          |
|--------------------|---------------|----------------------------------------------------------------------|
| `allowPageNum`     | `int`         | Allow page number URL segments (0=no, 1=yes)                         |
| `urlSegments`      | `int\|array`  | Allow URL segments: 0=no, 1=any, or array of allowed segment strings |
| `https`            | `int`         | HTTPS enforcement: 0=either, 1=HTTPS only, -1=HTTP only              |
| `slashUrls`        | `int`         | Trailing slash on page URLs (1=yes, 0=no)                            |
| `slashPageNum`     | `int\|string` | Trailing slash on page number segments (0=either, 1=yes, -1=no)      |
| `slashUrlSegments` | `int\|string` | Trailing slash on last URL segment (0=either, 1=yes, -1=no)          |

### $template->urlSegments($value)

Get or set the URL segments setting.

- **Arguments:** `urlSegments(array|int|bool|string $value = '~')`
- **Returns:** `array|int` — array of specific allowed segments, or `0` if disabled, or `1` if any allowed
- Omit the argument to get the current value.
- Pass an array of specific allowed segments, `true`/`1` to allow all, or `0`/`false` to disable.
- Segment strings may be literal or include `'regex:your-pattern'` entries.

~~~~~php
// Allow any URL segments
$template->urlSegments(1);

// Only allow specific segments
$template->urlSegments(['archive', 'feed', 'regex:[0-9]{4}']);

// Disable URL segments
$template->urlSegments(0);

$template->save();
~~~~~

---

### $template->isValidUrlSegmentStr($urlSegmentStr)

Check whether a URL segment string is valid for this template's settings.

- **Arguments:** `isValidUrlSegmentStr(string $urlSegmentStr)`
- **Returns:** `bool`
- Available since 3.0.186.

~~~~~php
if($template->isValidUrlSegmentStr('foo/bar/baz')) {
    // URL segment string is permitted
}
~~~~~

---

## File settings

| Property                | Type     | Description                                                            |
|-------------------------|----------|------------------------------------------------------------------------|
| `filename`              | `string` | Full path to the template file (auto-generated from name)              |
| `altFilename`           | `string` | Alternate filename if not based on template name                       |
| `contentType`           | `string` | Content-type header or key from `$config->contentTypes`                |
| `noPrependTemplateFile` | `int`    | Disable auto-prepend of `$config->prependTemplateFile` (0=use, 1=skip) |
| `noAppendTemplateFile`  | `int`    | Disable auto-append of `$config->appendTemplateFile` (0=use, 1=skip)   |
| `prependFile`           | `string` | File to prepend (relative to `/site/templates/`)                       |
| `appendFile`            | `string` | File to append (relative to `/site/templates/`)                        |
| `pagefileSecure`        | `int`    | Secure file serving: 0=off, 1=yes for non-public pages, 2=always       |

### $template->filename($filename)

Get or set the template filename. When getting, it auto-generates the path from the
template name if not already set, and also detects file modifications and namespace.

- **Arguments:** `filename(string|null $filename = null)`
- **Returns:** `string` — full path to the template file
- You may pass a basename (e.g. `'home.php'`) or a full path. Available for setting since 3.0.143.

~~~~~php
$file = $template->filename(); // full path, e.g. /site/templates/basic-page.php
$template->filename('custom-file.php'); // use /site/templates/custom-file.php
~~~~~

---

### $template->filenameExists()

Return `true` if the template file exists on disk.

- **Returns:** `bool`

~~~~~php
if($template->filenameExists()) {
    // template has a PHP file
}
~~~~~

---

## Cache settings

Template caching stores rendered page output for a specified time. A `cacheTime` of `0`
disables caching. Negative values are reserved for external caching engines (e.g. ProCache).

| Property              | Type     | Description                                                                            |
|-----------------------|----------|----------------------------------------------------------------------------------------|
| `cacheTime`           | `int`    | Seconds to cache page output; 0=disabled; negative=external cache                      |
| `useCacheForUsers`    | `int`    | 0=guest users only, 1=all users                                                        |
| `noCacheGetVars`      | `string` | Space-separated GET vars that bypass the cache                                         |
| `noCachePostVars`     | `string` | Space-separated POST vars that bypass the cache                                        |
| `cacheExpire`         | `int`    | How to expire the cache when a page is saved — see `Template::cacheExpire*` constants  |
| `cacheExpirePages`    | `array`  | Page IDs to expire when `cacheExpire === Template::cacheExpireSpecific`                |
| `cacheExpireSelector` | `string` | Selector matching pages to expire when `cacheExpire === Template::cacheExpireSelector` |

### Cache expiration constants

| Constant                        | Value  | Description                                        |
|---------------------------------|--------|----------------------------------------------------|
| `Template::cacheExpirePage`     | `0`    | Expire only the saved page                         |
| `Template::cacheExpireSite`     | `1`    | Expire the entire site cache                       |
| `Template::cacheExpireParents`  | `2`    | Expire the saved page and its parents              |
| `Template::cacheExpireSpecific` | `3`    | Expire specific pages listed in `cacheExpirePages` |
| `Template::cacheExpireSelector` | `4`    | Expire pages matching `cacheExpireSelector`        |
| `Template::cacheExpireNone`     | `-1`   | Do not expire anything on save                     |

~~~~~php
$template->cacheTime = 3600; // cache for 1 hour
$template->useCacheForUsers = 1; // cache for all users
$template->cacheExpire = Template::cacheExpireSite; // expire full cache on save
$template->save();
~~~~~
For a more complete page caching solution see [ProCache](https://processwire.com/store/pro-cache/).

---

## Behaviors

Boolean-style settings that govern page behavior. All are `0` (off) by default unless
noted.

| Property             | Description                                                                   |
|----------------------|-------------------------------------------------------------------------------|
| `noGlobal`           | Ignore the `global` flag on fields (0=respect global, 1=ignore)               |
| `noMove`             | Prevent pages from being moved to a different parent                          |
| `noTrash`            | Prevent pages from being trashed (they are deleted instead)                   |
| `noChangeTemplate`   | Prevent pages from changing their template                                    |
| `noUnpublish`        | Prevent pages from existing in an unpublished state                           |
| `noShortcut`         | Hide this template from the "Add new page" shortcut menu                      |
| `noLang`             | Disable multi-language support for pages using this template                  |
| `allowChangeUser`    | Allow the `createdUser` field to be changed via API or admin (superuser only) |
| `compile`            | PHP compilation: 0=off, 1=file only, 2=file+includes, 3=auto (default=3)      |

~~~~~php
$template->noTrash = 1; // pages using this template cannot be trashed
$template->noMove = 1; // pages cannot be moved
$template->noLang = 1; // single-language only, even if LanguageSupport active
$template->save();
~~~~~
*Note: the `compile` option applies only if `$config->templateCompile` is true.*

---

## Page editor settings

Settings that affect the admin page editor for pages using this template.

| Property          | Type     | Description                                                                                               |
|-------------------|----------|-----------------------------------------------------------------------------------------------------------|
| `nameContentTab`  | `int`    | Show the page name field on the Content tab (0=no, 1=yes)                                                 |
| `tabContent`      | `string` | Override label for the Content tab                                                                        |
| `tabChildren`     | `string` | Override label for the Children tab                                                                       |
| `nameLabel`       | `string` | Override label for the "Name" field                                                                       |
| `errorAction`     | `int`    | Action when a required field is empty on a published page save: 0=notify, 1=restore previous, 2=unpublish |

### $template->getTabLabel($tab, $language)

Return the overridden label for a page editor tab, or blank if not overridden.

- **Arguments:** `getTabLabel(string $tab, Language|string|int|null $language = null)`
- **Returns:** `string`
- `$tab` should be `'content'` or `'children'`.

~~~~~php
$label = $template->getTabLabel('content'); // blank if not overridden
~~~~~

---

### $template->setTabLabel($tab, $label, $language)

Set label for a page editor tab, optionally for a specific language. 

- **Arguments:** `setTabLabel(string $tab, string $label, Language|string|int|null $language = null)`
- **Returns:** `self`
- `$tab` should be `'content'` or `'children'`.

~~~~~php
$template->setTabLabel('content', 'Main');
~~~~~
---

### $template->getNameLabel($language)

Return the overridden page name input label, or blank if not overridden.

- **Arguments:** `getNameLabel(Language|string|int|null $language = null)`
- **Returns:** `string`

---

### $template->setNameLabel($label, $language)

Set the page name input label, optionally for a specific language. 

- **Arguments:** `setNameLabel(string $label, Language|string|int|null $language = null)`
- **Returns:** `self`

---

## Tags

Tags are space-separated strings stored in `$template->tags`. They are used to group
templates in the admin template list.

### $template->getTags()

Return the template's tags as an associative array keyed and valued by tag name.

- **Returns:** `array` — `[tagName => tagName, ...]`

~~~~~php
$tags = $template->getTags(); // ['marketing' => 'marketing', 'blog' => 'blog']
~~~~~

---

### $template->hasTag($tag)

Return `true` if this template has the given tag.

- **Arguments:** `hasTag(string $tag)`
- **Returns:** `bool`

---

### $template->addTag($tag) / $template->removeTag($tag)

Add or remove a tag. Remember to save the template afterward.

- **Returns:** `$this`

~~~~~php
$template->addTag('marketing');
$template->removeTag('old-tag');
$template->save();
~~~~~

---

## Languages

### $template->getLanguages()

Return a `PageArray` of languages allowed for pages using this template. Returns `null`
if LanguageSupport is not installed. When `$template->noLang` is set, returns only the
default language.

- **Returns:** `PageArray|Languages|null`

~~~~~php
$languages = $template->getLanguages();
if($languages) {
    foreach($languages as $lang) echo $lang->name . "\n";
}
~~~~~

---

## Saving

### $template->save()

Save this template to the database. Equivalent to `$templates->save($template)`.

- **Returns:** `Template|false` — returns the Template on success, `false` on failure

~~~~~php
$template->label = 'New Label';
$template->save();
~~~~~

---

## Flag constants

| Constant                       | Value   | Description                                                                           |
|--------------------------------|---------|---------------------------------------------------------------------------------------|
| `Template::flagSystem`         | `8`     | Template is a system template; name/ID cannot be changed and it cannot be deleted     |
| `Template::flagSystemOverride` | `32768` | Temporary override for system flag — set first, then remove `flagSystem` in two steps |

To remove the system flag from a template:

~~~~~php
$template->flags = $template->flags | Template::flagSystemOverride;
$template->flags = $template->flags & ~Template::flagSystem;
$template->flags = $template->flags & ~Template::flagSystemOverride;
$template->save();
~~~~~

---

## Notes

- Source file: `wire/core/Template/Template.php`.
- `$template->name` is used as the template filename base: `basic-page` → `/site/templates/basic-page.php`.
- `$template->fieldgroup` and `$template->fields` are aliases for the same `Fieldgroup` object.
- `$template->cacheTime` is a camelCase alias for the underlying `cache_time` setting.
- The `noChildren`, `noParents` properties may be `null`, a blank string or `0` when empty — check with
  `(int)` cast if comparing numerically.
- `$template->__toString()` returns `$template->name`, so templates in string context
  produce their name.
