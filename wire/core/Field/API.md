# Field / $field

Represents a custom field that is used on a Page. Field objects are managed by the
`$fields` API variable. Every field has a name, a type (Fieldtype), and optional
settings that control how it behaves in the admin and on the front-end.

```php
// Get a field
$field = $fields->get('body');
echo $field->name;   // body
echo $field->type;   // FieldtypeTextarea
echo $field->label;  // Body
```

## Constants

Flags are bitmask values that can be combined and checked with `hasFlag()`, `addFlag()`,
and `removeFlag()`.

| Constant                | Value | Description                                                            |
|-------------------------|-------|------------------------------------------------------------------------|
| `flagAutojoin`          | 1     | Field is automatically joined to the page at load time (if supported)  |
| `flagGlobal`            | 4     | Field used by all fieldgroups — all fieldgroups required to contain it |
| `flagSystem`            | 8     | Field is a system field — cannot be deleted, renamed, or converted     |
| `flagPermanent`         | 16    | Field is permanent in any fieldgroups/templates where it exists        |
| `flagAccess`            | 32    | Field is access controlled                                             |
| `flagAccessAPI`         | 64    | Access-controlled values are still front-end API accessible            |
| `flagAccessEditor`      | 128   | Non-editable values can still be viewed in the editor                  |
| `flagUnique`            | 256   | Field requires unique values in its data column (since 3.0.150)        |
| `flagFieldgroupContext` | 2048  | Field is in runtime context for a specific fieldgroup                  |
| `flagSystemOverride`    | 32768 | Override that allows removal of system/permanent flags                 |

## Native properties

| Property        | Type                  | Description                                          |
|-----------------|-----------------------|------------------------------------------------------|
| `id`            | `int`                 | Numeric ID of field in the database                  |
| `name`          | `string`              | Name of field                                        |
| `table`         | `string`              | Database table used by the field (read-only)         |
| `type`          | `Fieldtype` or `null` | Fieldtype module representing the type of this field |
| `flags`         | `int`                 | Bitmask of flags                                     |
| `flagsStr`      | `string`              | Names of flags as space-separated string (read-only) |
| `label`         | `string`              | Text label for the field                             |
| `description`   | `string`              | Longer description text                              |
| `notes`         | `string`              | Additional notes text                                |
| `icon`          | `string`              | Icon name                                            |
| `tags`          | `string`              | Space-separated tags string                          |
| `tagList`       | `array`               | Tags as an array (read-only)                         |
| `prevName`      | `string`              | Previous name (if field was renamed)                 |
| `prevTable`     | `string`              | Previous table name (if field was renamed)           |
| `prevFieldtype` | `Fieldtype` or `null` | Previous Fieldtype (if type was changed)             |

Inputfield native and custom properties are also stored on Field objects, as are custom configuration properties
from the Fieldtype. Below are examples of some common native properties from Inputfield:

| Property        | Type                      | Description                                                        |
|-----------------|---------------------------|--------------------------------------------------------------------|
| `required`      | `int` or `bool` or `null` | Whether the field is required during input                         |
| `requiredIf`    | `string` or `null`        | Selector string defining conditions when input is required         |
| `showIf`        | `string` or `null`        | Selector string defining conditions when Inputfield is shown       |
| `columnWidth`   | `int` or `null`           | Inputfield column width (percent) 10-100                           |
| `collapsed`     | `int` or `null`           | Inputfield collapsed value (see Inputfield constants)              |

## Retrieval

### get($key)

Get a field setting or dynamic data property.

```php
$name = $field->get('name');
$label = $field->get('label');
```

### getFieldtype()

Return the Fieldtype module for this field. Same as `$field->type`.

```php
$fieldtype = $field->getFieldtype();
```

### getTable()

Get the database table name used by this field. The table name is `field_` prefixed
with the field name, truncated to 64 characters max.

```php
echo $field->getTable(); // field_body
```

### getLabel($language = null) / getDescription($language = null) / getNotes($language = null)

Get the label, description, or notes for the current language (or a specified language
in multi-language environments). Unlike `$field->label`, these methods are
language-aware.

```php
echo $field->getLabel();
echo $field->getDescription();
echo $field->getNotes();
```

### getIcon($prefix = false)

Get the icon name. When `$prefix` is true, returns with `fa-` prefix.

```php
echo $field->getIcon();       // cog
echo $field->getIcon(true);   // fa-cog
```

### getFieldgroups()

Return the list of Fieldgroups using this field.

```php
$fieldgroups = $field->getFieldgroups();
```

### getTemplates()

Return the list of Templates using this field.

```php
$templates = $field->getTemplates();
```

### numFieldgroups()

Return the number of Fieldgroups this field is used in.

```php
$count = $field->numFieldgroups();
```

### getContext($for, $namespace = '')

Get this field in context of a Page, Template, or Fieldgroup.

```php
$fieldContext = $field->getContext($template);
```

### hasContext($for, $namespace = '')

Check if this field has context settings for the given Page, Template, or Fieldgroup.

```php
if($field->hasContext($template)) {
    // field has per-template context settings
}
```

### getInputfield(Page $page, $contextStr = '')

Get the Inputfield module used to collect input for this field on the given page.

```php
$inputfield = $field->getInputfield($page);
```

### getConfigInputfields()

Get Inputfields needed to configure this field in the admin. Returns an
`InputfieldWrapper` (a fieldset containing Inputfield instances).

```php
$configInputfields = $field->getConfigInputfields();
```

### editUrl($options = [])

Get URL to edit this field in the admin.

```php
echo $field->editUrl(); // /admin/setup/field/edit?id=123
echo $field->editUrl('description'); // with #find-description anchor
echo $field->editUrl(['http' => true]); // with full scheme and hostname
```

## Manipulation

### set($key, $value)

Set a native setting or dynamic data property. Also available via property assignment.

```php
$field->set('label', 'New Label');
// or
$field->label = 'New Label';
```

### setName($name)

Set the field's name. Throws `WireException` for reserved words, duplicate names,
system fields, or invalid formats. When renaming, the previous name is tracked in
`prevName` and the previous table in `prevTable`.

```php
$field->setName('new_name');
```

### setFieldtype($type)

Set the field's type. Accepts a Fieldtype object or a string class name. Supports
setup names via `FieldtypeName.setupName` syntax. When changing type, the previous
Fieldtype is tracked in `prevFieldtype`.

```php
$field->setFieldtype('FieldtypeText');
$field->setFieldtype('FieldtypeImage.lightbox'); // with setup name
```

### save()

Save the field's settings and data to the database. To hook this, hook to
`Fields::save()` instead.

```php
$field->save();
```

### setLabel($text, $language = null) / setDescription($text, $language = null) / setNotes($text, $language = null)

Set label, description, or notes, optionally for a specific language in multi-language
environments.

```php
$field->setLabel('My Label');
$field->setDescription('Long description', $language);
```

### setIcon($icon)

Set the icon for this field. The `fa-` or `icon-` prefix is stripped automatically.

```php
$field->setIcon('fa-cog');
$field->setIcon('icon-home');
$field->setIcon('user'); // all equivalent
```

### setTable($table = null)

Set an override table name, or pass `null` to restore the default. The table name is
sanitized and truncated to 64 characters.

```php
$field->setTable('custom_table');
$field->setTable(null); // restore default
```

## Flags

### addFlag($flag)

Add a bitmask flag to the field. Returns `$this` for chaining.

```php
$field->addFlag(Field::flagAutojoin);
```

### removeFlag($flag)

Remove a bitmask flag from the field. System and permanent flags cannot be removed
without first adding `flagSystemOverride`. Returns `$this` for chaining.

```php
$field->removeFlag(Field::flagAutojoin);
```

To remove a system or permanent flag, add `flagSystemOverride` first:

```php
$field->addFlag(Field::flagSystemOverride);
$field->removeFlag(Field::flagSystem);
$field->removeFlag(Field::flagSystemOverride);
$field->save();
```

This is intentionally indirect to prevent accidental removal of system field protections.

### hasFlag($flag)

Check if the field has the given bitmask flag.

```php
if($field->hasFlag(Field::flagSystem)) {
    echo "This is a system field";
}
```

## Access control

### $useRoles

Boolean property that enables or disables access control. Setting to `true` adds
`flagAccess`; setting to `false` removes it.

```php
$field->useRoles = true;  // enable access control
$field->useRoles = false; // disable access control
```

### $editRoles / $viewRoles

Arrays of Role IDs with edit or view access. Applicable only when `useRoles` is true.

```php
$field->editRoles = [1, 2]; // role IDs
$field->viewRoles = [1];
```

### setRoles($type, $roles)

Set the roles allowed to view or edit this field. `$type` is `'view'` or `'edit'`.
Accepts an array of Role IDs, Role objects, or role names.

```php
$field->setRoles('edit', [1, 2]);
$field->setRoles('view', ['guest']);
```

### viewable(Page $page = null, User $user = null)

Check if the field is viewable on the given page by the given user. To maximize
efficiency, check `$field->useRoles` first. Note: this does not check that the page
itself is viewable — use `$page->viewable()` for that. Note there is also 
`$page->viewable($field)`.

```php
if($field->viewable($page)) {
    echo $page->get($field->name);
}
```

### editable(Page $page = null, User $user = null)

Check if the field is editable on the given page by the given user. To maximize
efficiency, check `$field->useRoles` first. Note: this does not check that the page
itself is editable — use `$page->editable()` or `$page->editable($field)` for that.

```php
if($field->editable($page)) {
    // user can edit this field on this page
}
```

## Tags

Tags are space-separated strings stored on the field. Tag lookups are
case-insensitive.

### $tags / $tagList

The `tags` property returns a space-separated string. The `tagList` property returns
an array (read-only).

```php
echo $field->tags;   // "foo bar"
print_r($field->tagList); // ['foo' => 'foo', 'bar' => 'bar']
```

### getTags($getString = false)

Get tags as an array, or as a space-separated string when `$getString` is true.

```php
$tags = $field->getTags();        // array
$tagsStr = $field->getTags(true); // "foo bar"
```

### setTags($tagList, $reindex = true)

Set all tags at once. Accepts an array or space-separated string.

```php
$field->setTags(['foo', 'bar']);
$field->setTags('foo bar');
```

### addTag($tag)

Add a single tag. Returns the current tag list.

```php
$field->addTag('featured');
```

### removeTag($tag)

Remove a tag. Returns the current tag list.

```php
$field->removeTag('featured');
```

### hasTag($tag)

Check if the field has the given tag (case-insensitive).

```php
if($field->hasTag('featured')) {
    // field has the "featured" tag
}
```

## Hooks

Hookable methods on Field itself:

| Hook                              | When                                            | Arguments               |
|-----------------------------------|-------------------------------------------------|-------------------------|
| `Field::viewable`                 | Before checking if field is viewable            | `$page`, `$user`        |
| `Field::editable`                 | Before checking if field is editable            | `$page`, `$user`        |
| `Field::getInputfield`            | When building the Inputfield for a page         | `$page`, `$contextStr`  |
| `Field::getConfigInputfields`     | When building config Inputfields for admin      | —                       |

Hooks `Fields` / `$fields` (WireSaveableItems) which receive Field objects: 

| Hook                  | When                                          | Arguments                        |
|-----------------------|-----------------------------------------------|----------------------------------|
| `Fields::saveReady`   | Right before field is saved (confirmed)       | `$field`                         |
| `Fields::saved`       | Right after field has been saved              | `$field`, `$changes`             |
| `Fields::added`       | Right after a new field has been added        | `$field`                         |
| `Fields::deleteReady` | Right before field is deleted (confirmed)     | `$field`                         |
| `Fields::deleted`     | Right after field has been deleted            | `$field`                         |
| `Fields::renameReady` | Right before field is renamed                 | `$field`, `$oldName`, `$newName` |
| `Fields::renamed`     | Right after field has been renamed            | `$field`, `$oldName`, `$newName` |
| `Fields::cloneReady`  | Right before field is cloned                  | `$field`, `$copy`                |
| `Fields::cloned`      | Right after field has been cloned             | `$field`, `$copy`                |

```php
// Example: log when any field is saved
$wire->addHookAfter('Fields::saved', function(HookEvent $event) {
    $field = $event->arguments(0);
    $changes = $event->arguments(1);
    // ...
});
```

## Notes

- Field objects are managed by the `$fields` API variable (`$fields->get()`, `$fields->save()`, etc.).
- Manipulations to a Field are not saved until `$field->save()` is called. 
- The `__toString()` method returns the field name.
- System fields (with `flagSystem`) cannot be renamed or deleted. Use `flagSystemOverride` to override.
- The database table name is `field_` + field name, truncated to 64 characters.
- Setting `useRoles` to true/false automatically adds/removes `flagAccess`.
- Tag operations are case-insensitive — adding "Foo" when "foo" exists will not duplicate.
- `getLabel()` returns the field name when no label is set.
- **Source file:** `wire/core/Field/Field.php`.
