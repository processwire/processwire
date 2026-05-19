# Fieldgroups / $fieldgroups

`$fieldgroups` is the API variable that manages all `Fieldgroup` instances. A `Fieldgroup`
is a named collection of `Field` objects that is attached to one or more `Template`s.
In the admin UI, fieldgroups are invisible — it appears that fields are attached directly
to templates. In the API they are distinct: `$template->fieldgroup` gives you the
`Fieldgroup` for a template.

`$fieldgroups` is accessible in templates as `$fieldgroups` or `wire()->fieldgroups`;
and in modules as `$this->wire()->fieldgroups`.

---

## Getting fieldgroups

### $fieldgroups->get($name)

Get a fieldgroup by name or ID.

- **Arguments:** `get(string|int $name)`
- **Returns:** `Fieldgroup|null`

~~~~~php
$fieldgroup = $fieldgroups->get('basic-page');
$fieldgroup = $fieldgroups->get(12); // by ID
~~~~~

`$fieldgroups` is also directly iterable:

~~~~~php
foreach($fieldgroups as $fieldgroup) {
    echo "$fieldgroup->name (" . $fieldgroup->getNumTemplates() . " templates)\n";
}
~~~~~

Access a template's fieldgroup directly via the template:

~~~~~php
$fieldgroup = $templates->get('basic-page')->fieldgroup;
~~~~~

---

### $fieldgroups->getFieldNames($fieldgroup)

Get all field names used by a fieldgroup without loading the fieldgroup or its Field
objects. Returns an array of field names indexed by field ID.

- **Arguments:** `getFieldNames(string|int|Fieldgroup $fieldgroup)`
- **Returns:** `array` — `[fieldId => fieldName, ...]`

~~~~~php
$names = $fieldgroups->getFieldNames('basic-page');
// [3 => 'title', 7 => 'body', 12 => 'images']
~~~~~

---

## Creating fieldgroups

### $fieldgroups->new($name)

Create, save, and return a new Fieldgroup. Throws a `WireException` if a fieldgroup
with the given name already exists. Available since 3.0.263.

- **Arguments:** `new(string $name, array $addFields = [])`
- **Returns:** `Fieldgroup`

~~~~~php
// create new fieldgroup, add fields title and body, then save
$fieldgroup = $fieldgroups->new('my-fieldgroup', [ 'title', 'body' ]);
~~~~~

---

### $fieldgroups->newFieldgroup($name)

Create a new Fieldgroup in memory without saving it. Available since 3.0.263.

- **Arguments:** `newFieldgroup(string $name, array $addFields = [])`
- **Returns:** `Fieldgroup` (unsaved)

~~~~~php
$fieldgroup = $fieldgroups->newFieldgroup('my-fieldgroup', [ 'title', 'body' ]);
$fieldgroup->save(); // save at your convenience
~~~~~

The more verbose syntax below works in any version of ProcessWire:

~~~~~php
$fieldgroup = new Fieldgroup();
$fieldgroup->name = 'my-fieldgroup';
$fieldgroup->add('title'); // use Field instance, name or id
$fieldgroup->add('body');
$fieldgroup->save();
~~~~~


---

### $fieldgroups->save($fieldgroup)

Save a fieldgroup to the database, including any changes to field membership and order.
Also handles data deletion for any fields removed via `Fieldgroup::remove()`.

- **Arguments:** `save(Fieldgroup $fieldgroup)`
- **Returns:** `bool`

~~~~~php
$fieldgroups->save($fieldgroup);
// or equivalently:
$fieldgroup->save();
~~~~~

---

## Deleting and cloning fieldgroups

### $fieldgroups->delete($fieldgroup)

Delete a fieldgroup. Throws a `WireException` if the fieldgroup is in use by any template.

- **Arguments:** `delete(Fieldgroup $fieldgroup)`
- **Returns:** `bool`

~~~~~php
if(!$fieldgroup->getNumTemplates()) {
    $fieldgroups->delete($fieldgroup);
}
~~~~~

---

### $fieldgroups->clone($fieldgroup, $name)

Clone a fieldgroup including its field membership and context data.

- **Arguments:** `clone(Fieldgroup $fieldgroup, string $name = '')`
- **Returns:** `Fieldgroup|false`

~~~~~php
$fieldgroup = $fieldgroups->get('basic-page');
$fieldgroupCopy = $fieldgroups->clone($fieldgroup, 'basic-page-2');
~~~~~

---

## Fieldgroup field membership

A `Fieldgroup` extends `WireArray` and holds its `Field` objects directly. Field order
in the array determines field order on the page editor.

### $fieldgroup->add($field)

Add a field to this fieldgroup. The field must already be saved. Call `$fieldgroup->save()`
after to persist.

- **Arguments:** `add(Field|string|int $field)`
- **Returns:** `$this`

~~~~~php
$fieldgroup->add($fields->get('body'));
$fieldgroup->add('images'); // by name
$fieldgroup->save();
~~~~~

---

### $fieldgroup->remove($field)

Queue a field for removal from this fieldgroup. **Destructive:** when `$fieldgroup->save()`
is called, the field's data is permanently deleted from the database for all pages using
any template that references this fieldgroup. This can be a slow operation.

- **Arguments:** `remove(Field|string|int $field)`
- **Returns:** `bool`

~~~~~php
$fieldgroup->remove('old_field');
$fieldgroup->save(); // executes removal and data deletion
~~~~~

---

### $fieldgroup->softRemove($field)

Remove a field from the in-memory fieldgroup without queuing data deletion. When the
fieldgroup is saved, the field is simply excluded — no data is deleted. Use this when
moving a field between fieldsets within the same fieldgroup.

- **Arguments:** `softRemove(Field|string|int $field)`
- **Returns:** `bool|WireArray`

---

### $fieldgroup->hasField($key)

Return `true` if this fieldgroup contains the given field.

- **Arguments:** `hasField(Field|string|int $key)`
- **Returns:** `bool`

~~~~~php
if($fieldgroup->hasField('body')) {
    // fieldgroup has a body field
}
~~~~~

---

### $fieldgroup->getField($key)

Get a field from this fieldgroup. Preferred over `get()` for retrieving fields, since
`get()` can also return fieldgroup properties. Returns `null` if the field is not in
this fieldgroup.

- **Arguments:** `getField(Field|string|int $key, bool|string $useFieldgroupContext = false)`
- **Returns:** `Field|null`

~~~~~php
$field = $fieldgroup->getField('body');
~~~~~

Pass `true` as the second argument to get the field with context data applied (same as
`getFieldContext()`).

---

### Field order

`Fieldgroup` inherits `WireArray` methods for reordering. After reordering, save the
fieldgroup to persist the new order.

~~~~~php
$body  = $fieldgroup->getField('body');
$title = $fieldgroup->getField('title');

// Insert body after title
$fieldgroup->insertAfter($body, $title);
$fieldgroup->save();
~~~~~

---

## Fieldgroup and template relationships

Each `Template` has exactly one `Fieldgroup`. Access it via `$template->fieldgroup`.
While rarely used, a fieldgroup can (in principle) serve multiple templates — `getTemplates()` returns all of them.

### $fieldgroup->getTemplates()

Return all templates that use this fieldgroup.

- **Returns:** `TemplatesArray`

~~~~~php
foreach($fieldgroup->getTemplates() as $template) {
    echo $template->name . "\n";
}
~~~~~

---

### $fieldgroup->getNumTemplates()

Return the number of templates using this fieldgroup.

- **Returns:** `int`

~~~~~php
if($fieldgroup->getNumTemplates() === 0) {
    $fieldgroups->delete($fieldgroup); // safe to delete
}
~~~~~

---

## When to save — the key distinction

There are two independent ways to persist fieldgroup changes. Using the wrong one will
either silently do nothing or overwrite the wrong layer.

**Save the Fieldgroup** when you change field membership or field order:

~~~~~php
// Adding a field, removing a field, or reordering
$fieldgroup->add($fields->get('summary'));
$fieldgroup->save(); // ← saves membership/order to DB
~~~~~

**Save via `$fieldgroup->saveFieldContext()`** when you change per-fieldgroup field
settings (label, columnWidth, collapsed, required, etc.). This method
enables you to maintain unique settings for a Field that apply only when it appears
in the fieldgroup. In ProcessWire this is commonly referred to as field-template context,
though it is technically field-fieldgroup context.

~~~~~php
// Override a field's label within this fieldgroup only
$fieldgroup = $fieldgroups->get('blog-post');
$field = $fieldgroup->getFieldContext('body');
$field->label = 'Post Body';
$fieldgroup->saveFieldContext($field); // ← saves context only
~~~~~

`$fieldgroup->save()` saves field membership and order only — it preserves existing
context data, but it does not persist changes made to contextual Field clones. Use
`saveFieldContext()` to save context changes. These two operations are independent.

---

## Field context

A Fieldgroup may store per-fieldgroup overrides for any field it contains. These context
values override the global Field settings **only within that Fieldgroup**. For example,
the `body` field might have a `columnWidth` of 100 globally, but 50 when used in a
specific fieldgroup.

> A Fieldgroup contains references to global Field definitions, but may also carry
> per-fieldgroup context values that override selected Field properties only within
> that Fieldgroup.

### $fieldgroup->getFieldContext($key)

Get a field with its context data applied. Returns a **clone** of the global Field with
context values merged in. The clone is marked with `Field::flagFieldgroupContext` to
signal that it is a contextual copy, not the global definition.

- **Arguments:** `getFieldContext(Field|string|int $key, string $namespace = '')`
- **Returns:** `Field|null`

~~~~~php
$field = $fieldgroup->getFieldContext('body');

// Field::flagFieldgroupContext is set on the returned clone
if($field->flags & Field::flagFieldgroupContext) {
    // this is a contextual field — changes do not affect the global Field
}

// Modify context values
$field->columnWidth = 50;
$field->label = 'Page Body';

// Persist the context changes (use in PW 3.0.263+)
$fieldgroup->saveFieldContext($field);
~~~~~
*If you need to maintain compatibility with ProcessWire versions prior to 3.0.263,
use `$fields->saveFieldgroupContext($field, $fieldgroup)` rather than
`$fieldgroup->saveFieldContext($field)`.*

The returned clone is **not** the original Field. Modifying its properties has no effect
on the global Field definition. Only `$fieldgroup->saveFieldContext()`
and `$fields->saveFieldgroupContext()` persist the changes.

---

### $fieldgroup->hasFieldContext($field, $namespace)

Return `true` if the given field has any context overrides in this fieldgroup.

- **Arguments:** `hasFieldContext(Field|string|int $field, string $namespace = '')`
- **Returns:** `bool`

~~~~~php
if($fieldgroup->hasFieldContext('body')) {
    $field = $fieldgroup->getFieldContext('body');
}
~~~~~

---

### Properties commonly overridden in context

Technically almost any built-in or custom property of a Field can be overridden with
Fieldgroup context, but below are some common examples of built-in settings that
are often overridden:

| Property      | Description                                                   |
|---------------|---------------------------------------------------------------|
| `label`       | Override the field's admin label for this fieldgroup          |
| `description` | Override the description shown in the editor                  |
| `notes`       | Override the notes shown below the Inputfield                 |
| `columnWidth` | Inputfield column width in percent (10–100)                   |
| `collapsed`   | Inputfield collapsed state                                    |
| `required`    | Whether input is required                                     |

---

### Saving context

**Single field** — use `$fields->saveFieldgroupContext()`:

~~~~~php
$field = $fieldgroup->getFieldContext('body');
$field->columnWidth = 50;
$fieldgroup->saveFieldContext($field); // 3.0.263+
// $fields->saveFieldgroupContext($field, $fieldgroup); // any version
~~~~~

**Existing internal contexts** — use `$fieldgroup->saveContext()`:

~~~~~php
// Re-save all context arrays currently stored on the Fieldgroup
$fieldgroup->saveContext();
~~~~~

`saveContext()` is not a batch saver for modified Field objects returned by
`getFieldContext()`. For normal API code, call `saveFieldContext()` for each contextual
Field you modify.

---

### Namespace context (advanced)

A named context can be stored alongside the default context. This is intended for use
by modules that need to store their own per-fieldgroup field overrides without colliding
with the default layer (such as the RepeaterMatrix Fieldtype).

~~~~~php
// Get a field in a specific namespace context
$field = $fieldgroup->getFieldContext('body', 'my-module');
$field->someModuleSetting = 'value';
$fieldgroup->saveFieldContext($field, 'my-module');
~~~~~

Most code should use the default context (no namespace). Use namespaces only when you
need module-private context that must not interfere with the shared default layer.
When a namespace is requested, ProcessWire applies that namespace's context values;
it does not merge the default context and namespace context together.

---

## Notes

- Source files: `wire/core/Fieldgroups/Fieldgroups.php` (the `$fieldgroups` manager)
  and `wire/core/Fieldgroup/Fieldgroup.php` (the `Fieldgroup` object).
- `$fieldgroup->remove()` only **queues** the removal. The actual data deletion (across
  all pages on all templates using the fieldgroup) happens on `$fieldgroup->save()`.
  This can be slow and is irreversible — confirm before calling.
- `$fieldgroup->softRemove()` removes the field from the in-memory WireArray without
  queuing data deletion. It is safe for rearranging fields across fieldsets.
- `Field::flagFieldgroupContext` on a field object indicates it is a contextual clone.
  It is not saveable via `$fields->save()` — save its context via
  `$fieldgroup->saveFieldContext()` or `$fields->saveFieldgroupContext()` instead.
- Fields with `Field::flagGlobal` or `Field::flagPermanent` cannot be removed from a
  fieldgroup via `remove()`, unless the flag is also removed.
- `$fieldgroups->new()` is available since 3.0.263. It accepts the same style of
  reserved-keyword workaround as `$fields->new()`.
- Access a template's fieldgroup via `$template->fieldgroup` (property, not method).
