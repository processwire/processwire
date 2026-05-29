# WireArray

The base collection class for all ProcessWire iterable sets. Nearly every collection
you encounter — pages, fields, templates, modules, roles, and more — is a `WireArray`
subclass. Its API is jQuery-inspired: most mutation methods return `$this` for fluent
chaining, and both `foreach` and array access work out of the box.

`WireArray` is not itself an API variable. You encounter it through the collections
returned by the API: `$page->children()`, `$pages->find()`, `$modules`, and many
more — all of these are `WireArray` subclasses.

---

## Adding items

```php
// Append to end — all three are equivalent
$items->add($item);
$items->append($item);
$items[] = $item;           // array access

// Prepend to beginning
$items->prepend($item);

// Insert relative to an existing item
$items->insertBefore($newItem, $existingItem);
$items->insertAfter($newItem, $existingItem);

// Import multiple items (array or WireArray)
$items->import($otherArray);

// Swap two items, or replace one with the other
$items->replace($itemA, $itemB);
```

---

## Retrieving items

### `get($key)`

The primary retrieval method. Accepts many forms:

```php
// By array key
$item = $items->get('template-name'); // by name key
$item = $items->get(1234);            // by numeric key

// By numeric position (0-based), negative counts from end
$item = $items->eq(0);   // first item
$item = $items->eq(-1);  // last item

// First and last
$first = $items->first();
$last  = $items->last();

// First item matching a selector
$item = $items->get("name=foo-bar");

// Pipe fallback — returns first item found by key/name
$module = $modules->get("MarkupPageArray|MarkupAdminDataTable");

// Get an array of values from each item via "property[]" syntax
$titles = $items->get("title[]"); // returns plain PHP array

// Populate a template string with item properties
$html = $items->get("Total: {count}, First: {first}");

// Get multiple items by passing an array of keys
$subset = $items->get([1, 2, 3]); // returns associative array
```

### `getArray()`, `getValues()`, `getKeys()`

```php
$assoc   = $items->getArray();  // PHP array, original keys preserved
$values  = $items->getValues(); // PHP array, re-indexed from 0
$keys    = $items->getKeys();   // PHP array of keys only
```

### `slice($start, $limit)` and `eq($n)`

```php
$first3 = $items->slice(0, 3);  // returns new WireArray
$last3  = $items->slice(-3);

$third  = $items->eq(2);        // returns single item (0-based)
$last   = $items->eq(-1);       // last item
```

### Random selection

```php
$one   = $items->getRandom();      // single random item
$three = $items->getRandom(3);     // new WireArray of 3 random items
$three = $items->findRandom(3);    // always returns WireArray

// Seeded random — same result for the same day (useful for rotating featured items)
$three = $items->findRandomTimed(3);         // daily seed (default)
$three = $items->findRandomTimed(3, 'YmdH'); // hourly seed
$three = $items->findRandomTimed(3, 42);     // fixed integer seed
```

---

## Checking and counting

```php
// Count — both forms work
$n = $items->count();
$n = count($items);
$n = $items->count; // property

// Check if item, key, or selector matches anything
if($items->has($item))           { /* item object is present */ }
if($items->has('foo-bar'))       { /* item with name 'foo-bar' is present */ }
if($items->has("status>0"))      { /* at least one item matches selector */ }

// Check identity
if($items->isIdentical($other))      { /* same items, order, and data */ }
if($items->isIdentical($other, false)) { /* same items and order only */ }
```

---

## Finding items (non-destructive)

These return a new `WireArray` and leave the original unchanged.

```php
// All items matching a selector
$matches = $items->find("featured=1");

// First item matching a selector (returns item or false)
$match = $items->findOne("name=foo");
$match = $items->findOne("status>0, sort=-created");

// Reversed copy
$reversed = $items->reverse();

// Unique items only
$unique = $items->unique();

// Get adjacent items
$next = $items->getNext($item);
$prev = $items->getPrev($item);

// Divide into N equal slices (returns PHP array of WireArray objects)
$slices = $items->slices(3);
```

---

## Filtering items (destructive)

These modify the WireArray in place. Use `find()` instead if you need a copy.

```php
// Keep only items matching a selector
$items->filter("featured=1");

// Remove items matching a selector
$items->not("status=0");

// Sort by one or more properties (CSV or array)
$items->sort("last_name, first_name");
$items->sort("-created");     // descending
$items->sort("sort, title");  // by multiple fields

// Randomise order in place
$items->shuffle();
```

Sort supports dot-notation for sub-properties (`sort("parent.title")`), and the
special value `"random"` as an alias for `shuffle()`.

---

## Removing items

```php
// Remove by item object, key, or selector
$items->remove($item);
$items->remove('foo-bar');          // by name key
$items->remove("status=0");         // by selector (3.0.196+)

// Remove multiple
$items->removeItems([$itemA, $itemB]);

// Remove all
$items->removeAll();

// Pop/shift (remove and return)
$last  = $items->pop();   // removes and returns last item
$first = $items->shift();  // removes and returns first item
```

---

## Iterating and rendering

### `foreach`

```php
foreach($items as $item) {
    echo $item->title;
}

foreach($items as $key => $item) {
    echo "$key: $item->title\n";
}
```

### `each()`

The most versatile iteration method. Accepts a callable, a template string, a
property name, or an array of property names.

```php
// Callable — return strings to build a concatenated result
echo $items->each(function($item) {
    return "<li>$item->title</li>";
});

// With key as first argument when function has 2 parameters
echo $items->each(function($key, $item) {
    return "<li>$key: $item->title</li>";
});

// Template string with {tags}
echo $items->each("<li><a href='{url}'>{title}</a></li>");

// Property name — returns PHP array of values
$titles = $items->each("title");

// Multiple properties — returns array of associative arrays
$data = $items->each(["title", "url"]);
```

### `implode()` — build a delimited string

```php
// Comma-separated list of titles
echo $items->implode(", ", "title");

// Template string with {tags}
echo $items->implode(' / ', '<a href="{url}">{title}</a>');

// Custom function per item
echo $items->implode(", ", function($item) {
    return "$item->title ($item->id)";
});

// With options
echo $items->implode(", ", "title", ['skipEmpty' => true, 'prepend' => 'Items: ']);
```

### `explode()` — extract a property as a PHP array

```php
$titles = $items->explode("title");    // ['Title A', 'Title B', ...]

// Multiple properties: [['title' => 'Title A', 'url' => '/foo/'], ...]
$data = $items->explode(["title", "url"]);

// With a custom key: [ 'foo-bar' => 'Foo Bar', ...]
$byName = $items->explode("title", ['key' => 'name']); // keyed by 'name' property
```

### Magic property dispatch

Calling an unknown method on a `WireArray` automatically delegates to `explode()`
or `implode()` for a property having the name you specified as a method: 

```php
// same as $items->explode('title'): [ 'Foo', 'Bar', 'Baz', ...]
$titles = $items->title();

// same as $items->implode(", ", "title"): "Foo, Bar, Baz"
$str = $items->title(", ");
```
---

## Extra data storage

`WireArray` carries a separate `$extraData` store (not the items themselves) for
arbitrary metadata you want to attach to the collection — similar to jQuery's
`.data()`:

```php
// Set
$items->data('totalCount', 500);
$items->data(['page' => 2, 'limit' => 10]);

// Get
$total = $items->data('totalCount');
$all   = $items->data();

// Remove
$items->removeData('totalCount');
$items->data(false, 'totalCount'); // alternative unset form

// Replace all extra data
$items->data(['page' => 3], true);
```

---

## Creating new instances

```php
// Global shortcut function — most concise form
$a = WireArray();              // empty
$a = WireArray('foo');         // one item
$a = WireArray(['foo', 'bar']); // from PHP array

// Static factory — works on any WireArray subclass (PHP 7+)
$a = WireArray::new();             // empty
$a = WireArray::new($item);        // one item
$a = WireArray::new([$a, $b]);     // from PHP array
$a = WireArray::new($otherWireArray);

// Blank instance of the same concrete type (useful in subclasses)
$copy = $items->makeNew();

// Full clone (same type, same items)
$copy = clone $items;
$copy = $items->makeCopy(); // equivalent
```

`WireData()` and `PageArray()` are analogous shortcut functions for those types
(also in `wire/core/Functions.php`).

---

## Change tracking

`WireArray` tracks which items were added or removed while change tracking is on,
in addition to the property-level change tracking inherited from `Wire`.

```php
$items->setTrackChanges(true);

$items->add($newItem);
$items->remove($oldItem);

$added   = $items->getItemsAdded();   // array of added items
$removed = $items->getItemsRemoved(); // array of removed items

$items->resetTrackChanges(); // clears added/removed lists and disables tracking
```

---

## Notes

- **Source file:** `wire/core/WireArray/WireArray.php`
- **Extends:** `Wire` — all hook and API-variable access from `Wire` is available
- **Implements:** `IteratorAggregate`, `ArrayAccess`, `Countable`
- **Common subclasses:** `PageArray`, `Modules`, `Fieldtypes`, and many module-defined
  collection types. Note that `Fields`, `Templates`, `Fieldgroups`, `Roles`, `Users`,
  and `Permissions` are iterable `Wire` objects but extend `WireSaveableItems` or
  `PagesType`, not `WireArray` directly.
- **Destructive vs non-destructive:** `filter()`, `not()`, and `sort()` modify the
  collection in place; `find()`, `findOne()`, `reverse()`, `unique()`, and `slice()`
  return new collections
- **Duplicate checking:** subclasses that use named keys prevent duplicate items automatically; 
  plain `WireArray` instances do not, unless you call `setDuplicateChecking(true)`. 
- **`get()` vs array access:** `get($key)` returns `null` for missing items; array
  access `$items[$key]` returns `false`
- **`each()` return value:** returns `$this` when the callback returns nothing (for
  chaining), or a concatenated string when callbacks return strings
- **`sort()` special values:** prepend hyphen (`-`) to any property name for
  descending order (`"-created"`); use `"random"` as the sole property to shuffle
- **String representation:** casting a `WireArray` to string returns its items
  joined by `|` (using each item's own `__toString()`)
