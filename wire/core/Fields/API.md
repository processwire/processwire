# Fields / $fields

`$fields` is the API variable that manages all custom fields in ProcessWire, independently
of any Fieldgroup or Template. Each item in the collection is a `Field` object.

`$fields` is accessible in templates as `$fields` or `wire()->fields`; and in modules
as `$this->wire()->fields`.

---

## Getting and iterating fields

### $fields->get($name)

Get a single field by name or ID.

- **Arguments:** `get(string|int $name)`
- **Returns:** `Field|null`

~~~~~php
$field = $fields->get('title');
$field = $fields->get('body');
$field = $fields->get(123); // by ID
~~~~~

---

`$fields` is also directly iterable:

~~~~~php
foreach($fields as $field) {
    echo "$field->name ($field->type->shortName): $field->label\n";
}
~~~~~

---

### $fields->fieldNameExists($name)

Check whether a custom field with the given name exists. Unlike `get()`, this does not
trigger lazy loading of the field.

- **Returns:** `bool`

~~~~~php
if($fields->fieldNameExists('my_field')) {
    $field = $fields->get('my_field');
}
~~~~~

---

### $fields->getAllNames($indexType)

Get all field names as an array.

- **Arguments:** `getAllNames(string $indexType = '')`
- **Returns:** `array`
- Pass `''` (default) for a plain indexed array of names.
- Pass `'name'` for an associative array where both keys and values are field names.
- Pass `'id'` for an associative array keyed by field ID.

~~~~~php
$names = $fields->getAllNames();         // ['title', 'body', 'images', ...]
$byId  = $fields->getAllNames('id');     // [1 => 'title', 2 => 'body', ...]
~~~~~

---

### $fields->getAllIds()

Get all field IDs indexed by their field name.

- **Returns:** `array` — `['fieldName' => id, ...]`

~~~~~php
$ids = $fields->getAllIds(); // ['title' => 1, 'body' => 2, ...]
~~~~~

---

## Creating fields

### $fields->new($type, $name, $options)

Create, save, and return a new field. This is the recommended way to create a field
programmatically.

- **Arguments:** `new(string|array $type, string $name = '', string|array $options = [])`
- **Returns:** `Field`
- Throws `WireException` if the fieldtype is not found or the field cannot be saved.
- `$type` accepts full class name (`'FieldtypeText'`), or short names (`'Text'`, `'text'`).
- `$options` can be a label string, or an associative array of property/value pairs.
- Pass an array as the first argument to specify all settings at once (must include `type` and `name` keys).
- Available since 3.0.258.

~~~~~php
// Create a text field (TextField object)
$field = $fields->new('text', 'my_text', 'My Text Field');

// Create new textarea field with options array (TextareaField object)
$field = $fields->new('textarea', 'body', [
    'label'          => 'Body',
    'textformatters' => ['TextformatterEntities'],
    'columnWidth'    => 100,
]);

// Using an array as first argument (all-in-one TextField)
$field = $fields->new([
    'type'  => 'text',
    'name'  => 'summary',
    'label' => 'Summary',
]);
~~~~~

> One benefit of using `$fields->new()` or `$fields->newField()` relative to 
the older `new Field()` syntax is that the returned Field object will be a `[Type]Field` object
(where implemented) rather than just a generic/base `Field` object. For instance, the above example
creating a text field would return a `TextField` object rather than a `Field` object.

---

### $fields->newField($type, $name, $options)

Create a new Field instance without saving it to the database. Use this when you need
to configure a field further before saving, or when you want to save it yourself.

- **Arguments:** `newField(string|array $type, string $name = '', string|array $options = [])`
- **Returns:** `Field` (unsaved)
- Accepts the same arguments as `$fields->new()`.
- Call `$fields->save($field)` after configuring it.

~~~~~php
$field = $fields->newField('integer', 'sort_order', 'Sort Order');
$field->min = 0;
$field->max = 999;
$fields->save($field);
~~~~~

---

### $fields->save($field)

Save a field to the database. Creates it if new, updates it if existing.

- **Arguments:** `save(Field $field)`
- **Returns:** `bool`

~~~~~php
$field = $fields->get('body');
$field->label = 'Page Body';
$fields->save($field);
~~~~~

When saving a new field that has a `Field::flagGlobal` flag set, the field is
automatically added to all existing fieldgroups that don't already contain it.

---

## Deleting and cloning fields

### $fields->delete($field)

Delete a field from the database. Throws a `WireException` if the field is currently
assigned to any fieldgroup, or if it is a system field.

- **Arguments:** `delete(Field $field)`
- **Returns:** `bool`

~~~~~php
$field = $fields->get('old_field');
if($field && !$field->numFieldgroups()) {
    $fields->delete($field);
}
~~~~~

---

### $fields->clone($field, $name)

Clone a field and return the clone. The clone is saved automatically.

- **Arguments:** `clone(Field $field, string $name = '')`
- **Returns:** `Field` — the new clone, or `false` on failure
- System, permanent, and global flags are not copied to the clone.
- If `$name` is omitted, a name is auto-generated (e.g. `my_field_2`).

~~~~~php
$original = $fields->get('body');
$clone = $fields->clone($original, 'body_sidebar');
~~~~~

---

## Finding fields

### $fields->findByTag($tag, $getFieldNames)

Find all fields that have a given tag.

- **Arguments:** `findByTag(string $tag, bool $getFieldNames = false)`
- **Returns:** `array` — associative array keyed by field name; values are `Field` objects by default, or field name strings if `$getFieldNames` is `true`.

~~~~~php
// Get Field objects for fields tagged 'seo'
$seoFields = $fields->findByTag('seo');

// Get just the field names
$seoFieldNames = $fields->findByTag('seo', true);
// ['meta_title' => 'meta_title', 'meta_description' => 'meta_description']
~~~~~

---

### $fields->findByType($type, $options)

Find all fields using a given Fieldtype.

- **Arguments:** `findByType(string|Fieldtype $type, array $options = [])`
- **Returns:** `array`

**Options:**

| Option      | Description                                                                     |
|-------------|---------------------------------------------------------------------------------|
| `inherit`   | Also find fields using types that inherit from the given type? (default=`true`) |
| `valueType` | What to return per match: `'field'` (default), `'name'`, or `'id'`              |
| `indexType` | Array index: `'name'` (default), `'id'`, or `''` for non-associative            |

~~~~~php
// Get all text fields (including FieldtypeTextarea, etc.)
$textFields = $fields->findByType('FieldtypeText');

// Exact type only, just names
$textFields = $fields->findByType('FieldtypeText', [
    'inherit'   => false,
    'valueType' => 'name',
]);

// Get image fields by full or short type name
$imageFields = $fields->findByType('FieldtypeImage');
~~~~~

---

### $fields->getTags($getFieldNames)

Get all tags currently used across all fields.

- **Arguments:** `getTags(bool|string $getFieldNames = false)`
- **Returns:** `array`
- Default: returns `['tagName' => 'tagName', ...]`.
- Pass `true` to return `['tagName' => ['field1', 'field2'], ...]`.
- Pass `'reset'` to clear the internal tag cache.
- This is a hookable method (`___getTags()`).

~~~~~php
$tags = $fields->getTags();
// ['seo' => 'seo', 'layout' => 'layout', ...]

$tagFields = $fields->getTags(true);
// ['seo' => ['meta_title', 'meta_description'], ...]
~~~~~

---

## Field usage

### $fields->getNumPages($field, $options)

Return the number of pages that have a value populated for the given field.

- **Arguments:** `getNumPages(Field $field, array $options = [])`
- **Returns:** `int`, or `array` of page IDs if `getPageIDs` option is set.

**Options:**

| Option       | Description                                                              |
|--------------|--------------------------------------------------------------------------|
| `template`   | Limit count to pages using this Template (object, ID, or name)          |
| `page`       | Limit count to a specific Page (object, ID, or path)                    |
| `getPageIDs` | If `true`, return an array of matching Page IDs instead of a count      |

~~~~~php
$field = $fields->get('body');

// Total pages with a body value
$total = $fields->getNumPages($field);

// Pages using a specific template
$total = $fields->getNumPages($field, ['template' => 'basic-page']);

// Get the actual page IDs
$ids = $fields->getNumPages($field, ['getPageIDs' => true]);
~~~~~

---

### $fields->getNumRows($field, $options)

Return the number of database rows populated for the given field. For single-value
fields this equals the page count; for multi-value fields (e.g. images, page references)
it may be higher.

- Accepts the same options as `getNumPages()`, plus `countPages` (bool, default=`false`)
  to return a page count instead of a row count.
- **Returns:** `int`, or `array` if `getPageIDs` is set.

~~~~~php
$rows = $fields->getNumRows($fields->get('images'));
~~~~~

---

### $fields->getFieldtype($name)

Get a Fieldtype module by name. Accepts both full class names and short names.

- **Arguments:** `getFieldtype(string $name)`
- **Returns:** `Fieldtype|null`
- Short names are case-insensitive: `'text'`, `'Text'`, and `'FieldtypeText'` all work.

~~~~~php
$type = $fields->getFieldtype('text');       // FieldtypeText
$type = $fields->getFieldtype('FieldtypeImage'); // FieldtypeImage
~~~~~

---

### $fields->isNative($name)

Check whether a field name is reserved by the system and therefore not available for
use as a custom field name.

- **Returns:** `bool`

~~~~~php
$fields->isNative('name');     // true — reserved
$fields->isNative('title');    // false — 'title' is a custom field, not a native one
$fields->isNative('my_field'); // false
~~~~~

---

## Field properties

A `Field` object has the following commonly used properties:

| Property      | Type          | Description                                                         |
|---------------|---------------|---------------------------------------------------------------------|
| `id`          | `int`         | Numeric database ID                                                 |
| `name`        | `string`      | Field name (unique, lowercase, `[-_a-z0-9]`)                        |
| `label`       | `string`      | Admin label used in Inputfield headers                              |
| `description` | `string`      | Optional longer description                                         |
| `notes`       | `string`      | Optional additional notes                                           |
| `icon`        | `string`      | FontAwesome icon name (without `fa-`)                               |
| `tags`        | `string`      | Space-separated tag list                                            |
| `tagList`     | `array`       | Same as `tags` but as an array (read-only)                          |
| `type`        | `Fieldtype`   | Fieldtype module object                                             |
| `flags`       | `int`         | Bitmask of active `Field::flag*` constants                          |
| `flagsStr`    | `string`      | Active flags as a space-separated string (read-only)                |
| `required`    | `bool`        | Whether input is required                                           |
| `requiredIf`  | `string`      | Selector that makes input conditionally required                    |
| `showIf`      | `string`      | Selector that controls Inputfield visibility                        |
| `columnWidth` | `int`         | Inputfield column width in percent (10–100)                         |
| `collapsed`   | `int`         | Inputfield collapsed state (see `Inputfield::collapsed*` constants) |
| `useRoles`    | `bool`        | Whether access control is enabled for this field                    |
| `editRoles`   | `array`       | Role IDs with edit access (when `useRoles` is true)                 |
| `viewRoles`   | `array`       | Role IDs with view access (when `useRoles` is true)                 |

~~~~~php
$field = $fields->get('body');
echo $field->name;         // "body"
echo $field->label;        // "Body"
echo $field->type->className(); // "FieldtypeTextarea"
echo $field->flagsStr;     // "autojoin" (if autojoin flag set)
~~~~~

---

## Field flags

Flags are bitmask constants defined on the `Field` class. Check and compare them via
`$field->flags`:

| Constant                    | Value  | Description                                                       |
|-----------------------------|--------|-------------------------------------------------------------------|
| `Field::flagAutojoin`       | 1      | Field value is automatically joined when a page is loaded         |
| `Field::flagGlobal`         | 4      | Field required to be present on all fieldgroups/templates         |
| `Field::flagSystem`         | 8      | System field — cannot be deleted or renamed                       |
| `Field::flagPermanent`      | 16     | Cannot be removed from fieldgroups that contain it                |
| `Field::flagAccess`         | 32     | Access-controlled (respects `viewRoles`/`editRoles`)              |
| `Field::flagAccessAPI`      | 64     | Value remains API-accessible even when output formatting hides it |
| `Field::flagAccessEditor`   | 128    | Value is viewable in the editor even without edit access          |
| `Field::flagUnique`         | 256    | Enforces a unique index on the field's data column                |

~~~~~php
// Check for a flag
if($field->flags & Field::flagAutojoin) {
    echo "Field '$field->name' is autojoined";
}

// Add/remove flags via helper methods
$field->addFlag(Field::flagAutojoin);
$field->removeFlag(Field::flagGlobal);
$hasFlag = $field->hasFlag(Field::flagSystem); // bool
~~~~~

### Runtime field flags (not saved in DB)

| Constant                       | Value | Description                                                                     |
|--------------------------------|-------|---------------------------------------------------------------------------------|
| `Field::flagFieldgroupContext` | 2048  | Field instance is contextual to a specific fieldgroup and is no longer saveable |
| `Field::flagSystemOverride`    | 32768 | Enables system/permanent flags to be removed in separate set operation          |

Removal of system or permanent flags is intentionally verbose in order to prevent accidental removals. 
Below are examples of how not to do it, and then how to do it: 
```php
// Get a field with flagSystem
$field = $fields->get('email');

// Don't remove flagSystem without flagSystemOverride
$field->removeFlag(Field::flagSystem); // this fails :(

// Do it like this instead:
$field->addFlag(Field::flagSystemOverride); // add override flag
$field->removeFlag(Field::flagSystem); // now it works :)
$field->removeFlag(Field::flagSystemOverride); // also remove this

```

---

## Field methods

| Method                              | Description                                                        |
|-------------------------------------|--------------------------------------------------------------------|
| `$field->save()`                    | Save the field (shortcut for `$fields->save($field)`)              |
| `$field->addFlag($flag)`            | Add a flag by constant value                                       |
| `$field->removeFlag($flag)`         | Remove a flag by constant value                                    |
| `$field->hasFlag($flag)`            | Return `true` if the flag is set                                   |
| `$field->numFieldgroups()`          | Return the number of fieldgroups using this field                  |
| `$field->getFieldgroups()`          | Return a `FieldgroupsArray` of fieldgroups using this field        |
| `$field->getTemplates()`            | Return a `TemplatesArray` of templates using this field            |
| `$field->getTemplates(true)`        | Return a count of templates using this field                       |
| `$field->getTags()`                 | Return an array of tags assigned to this field                     |
| `$field->hasTag($tag)`              | Return `true` if this field has the given tag                      |
| `$field->addTag($tag)`              | Add a tag to this field (call `save()` to persist)                 |
| `$field->removeTag($tag)`           | Remove a tag (call `save()` to persist)                            |
| `$field->viewable($page)`           | Whether the field is viewable on `$page` by current user           |
| `$field->viewable($page, $user)`    | Whether the field is viewable on `$page` by `$user`                |
| `$field->editable($page)`           | Whether the field is editable on `$page` by current user           |
| `$field->editable($page, $user)`    | Whether the field is editable on `$page` by `$user`                |
| `$field->getInputfield($page)`      | Get the `Inputfield` instance for this field in context of `$page` |
| `$field->getLabel($language)`       | Get label for given language (or current language if omitted)      |
| `$field->getDescription($language)` | Get description for given language                                 |
| `$field->getNotes($language)`       | Get notes for given language                                       |
| `$field->editUrl()`                 | Get the admin edit URL for this field                              |

~~~~~php
$field = $fields->get('body');

// Is this field used by any fieldgroups/templates?
if($field->numFieldgroups()) { ... }

// Render names of templates that have this field
foreach($field->getTemplates() as $template) echo $template->name . "\n";

// Can the current user edit the field on a given page?
$page = $pages->get('/about/');
if($field->editable($page)) {
    // user can edit the body field on this page
}
~~~~~

---

## Fieldgroup context

Template/fieldgroup context enables a field to have unique settings or overrides
when in the context of a particular Fieldgroup. 

### $fields->saveFieldgroupContext($field, $fieldgroup, $namespace)

Save fieldgroup-specific context data for a field — the per-fieldgroup settings such
as label/description/notes overrides, columnWidth, collapsed, access flags, and any other
built-in or custom settings (most are supported). 

- **Arguments:** `saveFieldgroupContext(Field $field, Fieldgroup $fieldgroup, string $namespace = '')`
- **Returns:** `bool`
- `$field` must be in fieldgroup context (i.e. retrieved via `$fieldgroup->getFieldContext($field)`)
  before this is called.

---

## Notes

- Source files: `wire/core/Fields/Fields.php` (the `$fields` manager) and
  `wire/core/Field/Field.php` (the `Field` object).
- `$fields->get()` accepts a field name (string) or field ID (int).
- Fields created with `$fields->new()` are automatically saved. Use `$fields->newField()`
  if you need to configure the field further before saving.
- Deleting a field that is still assigned to a fieldgroup throws a `WireException`. Call
  `$field->numFieldgroups()` first to check.
- The `flagGlobal` flag causes a field to be automatically added to all existing
  fieldgroups on save, unless the fieldgroup's template has `noGlobal` set.
- Field tags are space-separated in the `tags` property; read them as an array via
  `tagList` or `$field->getTags()`.
- `$fields->getFieldtype($name)` is a convenient shortcut for `$fieldtypes->get($name)`
  that also accepts abbreviated type names such as `'text'` or `'image'`.
- Each Fieldtype module may define additional field properties that appear on the `Field`
  object; see the individual Fieldtype's `API.md` for details.
