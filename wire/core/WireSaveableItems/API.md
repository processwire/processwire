# WireSaveableItems

Abstract base class that manages collections of saveable items backed by a database table. It provides
the CRUD (Create, Read, Update, Delete) and find operations shared by ProcessWire's
[`$fields`]([[Fields]]) and [`$templates`]([[Templates]]) API variables. You won't
instantiate this class directly — it powers the collections you already use.

```php
// $fields and $templates are both WireSaveableItems instances
$field = $fields->get('body');
$template = $templates->get('basic-page');

// Find by selector string
$textFields = $fields->find('type=text');

// Iterate (implements IteratorAggregate)
foreach($fields as $field) {
    echo "$field->name: $field->label\n";
}
```

`WireSaveableItems` extends `[[Wire]]` and implements `\IteratorAggregate`. Items in the collection
must implement the `Saveable` interface (providing `save()`, `getTableData()`, and `id`/`name` properties).

---

## Getting an instance

You don't create `WireSaveableItems` directly. Access the concrete subclasses:

```php
$fields    = $fields;    // API variable — Fields instance
$templates = $templates; // API variable — Templates instance
$fields    = $wire->fields;
$templates = $wire->templates;
```

---

## Core methods

### get($key)

Retrieve an item by its `id` (int) or `name` (string). With lazy loading enabled, triggers
a single-item load from the database if the item hasn't been preloaded. Returns `null` if not found.

```php
$field = $fields->get('body');       // by name
$field = $fields->get(123);          // by id
$template = $templates->get('home'); // by name
```

The class also supports `__invoke`, so on API variables you can use shorthand:

```php
$field = $fields('body');
$template = $templates('home');
```

### has($item)

Check whether an item exists in the collection. Accepts the same arguments as `get()` (id, name, or item object).

```php
if($fields->has('body')) {
    // field 'body' exists
}
if($fields->has($someField)) {
    // the given Field object is in the collection
}
```

### find($selectors)

Find items matching a selector string or `Selectors` object. This delegates to the internal
`WireArray`'s `find()`. When lazy loading is active, all items are loaded first.

```php
$textFields = $fields->find('type=FieldtypeText');
$systemTemplates = $templates->find('flags&' . Template::flagSystem);
```

### save(Saveable $item)

Save (insert or update) the given item to the database. If the item has an `id`, it's updated;
otherwise it's inserted and assigned a new `id`. Returns `true` on success, `false` on failure.

```php
$field = $fields->get('body');
$field->label = 'New Label';
$fields->save($field);
```

Triggers `saveReady` before the write and `saved` after. For new items, also triggers `added`.

### delete(Saveable $item)

Delete the item from the database and remove it from the collection. Returns `true` on success,
`false` on failure. The item's `id` is set to `0` after deletion.

```php
$template = $templates->get('obsolete');
$templates->delete($template);
```

Triggers `deleteReady` before the delete and `deleted` after.

### clone(Saveable $item, $name = '')

Create and save a clone of the given item. If the item uses a `name` field, it automatically
appends a numeric suffix to ensure uniqueness (e.g. `body_1`, `body_2`). You may optionally
specify a new name.

```php
$original = $fields->get('body');
$copy = $fields->clone($original, 'body_clone');
if($copy) {
    echo "Cloned to: $copy->name";
}
```

Triggers `cloneReady` before the clone and `cloned` after.

### getRaw($key)

Get the raw database row for an item by ID or name, bypassing any cache and without creating
a fully-initialized object. Returns an associative array of column values, or `null` if not found.

```php
$row = $fields->getRaw('body');
// $row = ['id' => 123, 'name' => 'body', 'label' => 'Body', ...]
```

### getFresh($key)

Load and return a fresh instance of an item from the database, bypassing any cache. The returned
item is not added to the collection. Returns `null` if not found.

```php
$freshField = $fields->getFresh('body');
// $freshField is a standalone Field object, not in $fields collection
```

### getWireArray()

Return the internal `WireArray` container without triggering lazy loads. This is the guaranteed
no-side-effect version of `getAll()`. Marked `#pw-internal` — prefer `get()` or `find()` for
normal use.

```php
$items = $fields->getWireArray();
// Raw WireArray — won't trigger lazy loads
```

---

## Iteration

`WireSaveableItems` implements `\IteratorAggregate`, so you can iterate directly with `foreach`.
If lazy loading is active, iterating triggers loading of all remaining items.

```php
foreach($fields as $field) {
    echo "$field->name: $field->type\n";
}
```

---

## Hooks

`WireSaveableItems` provides a rich set of hookable lifecycle methods. These are the key
extension points for reacting to changes in fields and templates.

### Lifecycle hooks table

| Hook                           | When                                     | Arguments                          |
|--------------------------------|------------------------------------------|------------------------------------|
| `Fields::saveReady`            | Confirmed item will be saved             | `$item`                            |
| `Fields::saved`                | After item has been saved                | `$item`, `$changes`                |
| `Fields::added`                | After a new item has been added          | `$item`                            |
| `Fields::deleteReady`          | Confirmed item will be deleted           | `$item`                            |
| `Fields::deleted`              | After item has been deleted              | `$item`                            |
| `Fields::cloneReady`           | Confirmed item will be cloned            | `$item`, `$copy`                   |
| `Fields::cloned`               | After item has been cloned               | `$item`, `$copy`                   |
| `Fields::renameReady`          | Item is about to be renamed              | `$item`, `$oldName`, `$newName`    |
| `Fields::renamed`              | After item has been renamed              | `$item`, `$oldName`, `$newName`    |

*Replace `Fields` with `Templates` to hook the templates collection. Hooks on `WireSaveableItems`
itself won't fire — hook the concrete class.*

### Hook examples

```php
// Log every field save
$wire->addHookAfter('Fields::saved', function(HookEvent $event) {
    $fields = $event->object;    /** @var Fields $fields */
    $field = $event->arguments(0); /** @var Field $field */
    $changes = $event->arguments(1);
    $wire = $event->wire();
    $wire->log->save('fields', "Field '$field->name' saved. Changes: " . implode(', ', $changes));
});

// Enforce a naming convention on new templates
$wire->addHookBefore('Templates::saveReady', function(HookEvent $event) {
    $item = $event->arguments(0);
    if(!$item->id && !str_starts_with($item->name, 'tpl_')) {
        $item->name = 'tpl_' . $item->name;
    }
});
```

---

## Lazy loading

When enabled via `$config->useLazyLoading`, `WireSaveableItems` loads item data from the
database on demand rather than all at once. By default in ProcessWire 3.x, lazy loading is
enabled for both `$fields` and `$templates`.

- Items are loaded individually when accessed via `get()` by name or ID
- Iteration (`foreach`) triggers a full load of all remaining items
- `find()` triggers a full load before filtering
- Use `getWireArray()` to inspect the collection without triggering loads
- `getRaw()` and `getFresh()` bypass the cache entirely and always query the database

---

## WireSaveableItemsLookup

For collections backed by a lookup table (many-to-many relationship), ProcessWire provides
the `WireSaveableItemsLookup` subclass. It overrides `save()` and `delete()` to also
manage records in the lookup table. Items must implement the `HasLookupItems` interface.

This is used internally by classes like `Fieldgroups` (template-fieldgroup relationships).

---

## Notes

- **API variables:** `$fields` and `$templates` are the two concrete instances of this class.
  Their docs at [[Fields]] and [[Templates]] cover the class-specific additions on top of
  this shared base.
- **Saveable interface:** All items managed by this class must implement `Saveable`, providing
  `getTableData()` (returns data matching DB columns), `save()`, and `id`/`name` properties.
- **No fuel scoping:** `useFuel()` returns `false`, so within subclasses of `WireSaveableItems`,
  API variables are not accessible as `$this->apivar`. Use `$this->wire('apivar')` or
  `$this->wire()->apivar` instead.
- **Database table:** Each subclass defines its own table via `getTable()`. Columns must match
  the keys returned by the item's `getTableData()`.
- **Source file:** `wire/core/WireSaveableItems/WireSaveableItems.php`
- **Lookup subclass:** `wire/core/WireSaveableItems/WireSaveableItemsLookup.php`
- **Related interfaces:** `wire/core/WireSaveableItems/Interfaces.php`


