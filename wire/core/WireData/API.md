# WireData

The base data-storage class used throughout ProcessWire. Extends `Wire` with a built-in
key-value store: properties are held in a protected `$data` array and exposed through
`get()` and `set()` methods, direct property access, and array access — all interchangeable.

`WireData` is the base class for `Page`, `Field`, `Template`, many `Module` classes, and many 
other ProcessWire objects, so its API is available on many of the objects you work with.
You can also extend it directly to create lightweight data-container classes, or use it
standalone as a simple key-value store.

`WireData` is not itself an API variable. Access it as the base of the objects you already
use (`$page`, `$field`, `$template`, etc.), or in modules as
`$item = new WireData(); $this->wire($item);`.

---

## Getting and setting properties

All three access styles are equivalent and fully interchangeable for normal
stored values:

```php
// Set
$item->set('color', 'blue');
$item->color = 'blue';
$item['color'] = 'blue';

// Get
$color = $item->get('color');
$color = $item->color;
$color = $item['color'];
```

When a value is missing or explicitly `null`, `get()` and property access return
`null`, while array access returns `false`.

### `set($key, $value)`

Sets a named property. Participates in change tracking if enabled (see below).
Returns `$this` for chaining.

```php
$item->set('title', 'Hello')->set('status', 1);
```

### `setQuietly($key, $value)`

Same as `set()` but bypasses change tracking. Useful when updating internal state
that should not be considered a user-initiated change.

```php
$item->setQuietly('_cache', $computedValue);
```

### `setArray(array $data)`

Sets multiple properties at once from an associative array.

```php
$item->setArray([
    'title'  => 'Hello',
    'color'  => 'blue',
    'weight' => 42,
]);
```

### `get($key)`

Returns the value of a property, or `null` if not set. Also falls through to API
variable lookup (via the parent `Wire` class) when the key is not in `$data`.

```php
$value = $item->get('color'); // 'blue', or null if not set
```

**Pipe syntax — first non-empty value:**

Pass multiple property names separated by `|` and `get()` returns the first
non-empty value it finds. Useful as a fallback chain:

```php
// Return localTitle if set, otherwise fall back to title
$label = $item->get('localTitle|title');
```

### `has($key)`

Returns `true` if the property exists and its value is non-null. Because `get()`
falls through to API-variable lookup, `has()` can also return true for available
API variable names.

```php
if($item->has('color')) {
    echo $item->color;
}
```

### `getArray()`

Returns the full `$data` array as an associative array.

```php
$all = $item->getArray(); // ['title' => 'Hello', 'color' => 'blue', ...]
```

---

## Dot syntax

`getDot()` retrieves a value using dot-separated key chains, traversing nested
`WireData` or `WireArray` objects automatically.

```php
// Equivalent to $page->get('parent')->get('title')
$parentTitle = $page->getDot('parent.title');

// Three levels deep
$value = $item->getDot('a.b.c');
```

Dot syntax is used internally by `Page::get()`, so `$page->get('parent.title')`
already works on Page objects without calling `getDot()` directly.

---

## Iteration

Iterating a `WireData` object yields all properties in `$data` as key-value pairs:

```php
foreach($item as $key => $value) {
    echo "$key: $value\n";
}
```

---

## Removing properties

### `remove($key)`

Removes a property. Participates in change tracking.

```php
$item->remove('color');
unset($item->color);   // equivalent
unset($item['color']); // equivalent
```

### `removeQuietly($key)` — 3.0.262+

Removes a property without change tracking.

```php
$item->removeQuietly('_cache');
```

---

## Low-level data access

`data()` reads and writes directly from/to the `$data` array, bypassing any extra
logic a subclass may have added to `get()` or `set()`. Useful in subclasses or
when you need to work with raw stored values.

```php
// Get a single value (bypasses overridden get())
$raw = $item->data('color');

// Set a single value (bypasses overridden set())
$item->data('color', 'red');

// Get the entire $data array
$all = $item->data();

// Replace the entire $data array
$item->data(['title' => 'Hello', 'color' => 'blue'], true);

// Merge into $data (without true, it merges rather than replaces)
$item->data(['weight' => 42]);
```

---

## The and() method

`and()` combines the current object with another item or collection and returns a
new `WireArray`. Useful for applying filters or checks across a set of items that
don't yet exist in a single collection:

```php
// Does this page or any of its parents have featured=1?
if($page->and($page->parents)->has("featured=1")) {
    // ...
}

// Combine with a WireArray
$combined = $item->and($otherCollection);

// Wrap this item alone in a WireArray
$array = $item->and();
```

---

## Change tracking

When change tracking is enabled (it is by default on `Page` and other saveable
objects), `set()` and `remove()` record which properties changed since the last
save. `setQuietly()` and `removeQuietly()` bypass this.

```php
$item->setTrackChanges(true);

$item->set('color', 'red');         // tracked
$item->setQuietly('color', 'red');  // not tracked

$changed = $item->getChanges(); // ['color']
```

Change tracking is inherited from `Wire`. The full API: `trackChanges()`,
`setTrackChanges()`, `getChanges()`, and `resetTrackChanges()`.

---

## Notes

- **Source file:** `wire/core/WireData/WireData.php`
- **Extends:** `Wire` — all hook and API-variable access from `Wire` is available
- **Implements:** `IteratorAggregate`, `ArrayAccess`
- **Common subclasses:** `Page`, `Field`, `Template`, `Module`, `WireInputData`,
  `HookEvent`, and many other ProcessWire objects
- **Extending WireData:** subclasses typically override `get()` and/or `set()` to
  add type coercion, lazy loading, or other logic; use `data()` to access the raw
  array from within those overrides
- **`data` as a key:** calling `$item->set('data', $array)` is equivalent to
  `$item->setArray($array)` — it merges the given array into `$data` rather than
  storing it under the key `'data'`
- **Pipe syntax limitation:** `get('a|b')` returns the first non-empty (truthy)
  value, not merely the first non-null one — a value of `0` or `''` is skipped
