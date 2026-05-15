# WireCache / $cache

`$cache` is the API variable for ProcessWire's built-in persistent cache. It stores and
retrieves strings, arrays, and PageArray objects, with configurable expiration based on
time, page saves, or template saves.

`$cache` is accessible in templates as `$cache`, `wire()->cache`, or `cache()` (if
functions API enabled); and in modules as `$this->wire()->cache`.

---

## Getting and saving

### $cache->get($name, $expire, $func)

Get a cached value by name.

- **Arguments:** `get(string|array $name, int|string|callable|null $expire = null, callable $func = null)`
- **Returns:** `string|array|PageArray|mixed|null` — cached value, or `null` if not found
- When `$func` is provided and the cache is missing or expired, the function is called to
  generate a new value, which is automatically saved and returned.
- `$expire` is used as the save expiration when `$func` is provided, not as a filter.
- Pass an array of names to retrieve multiple caches at once (returns associative array).
  Requested names that do not exist are present in the returned array with a blank string value.
- Wildcard names (`"MyModule*"`) retrieve all matching caches.

~~~~~php
// Get a cache (returns null if not found or expired)
$value = $cache->get('my-cache');

// Get with max-age check (returns null if older than 1 hour)
$value = $cache->get('my-cache', 3600);

// Get with generation function (most common pattern)
$value = $cache->get('my-cache', 3600, function() {
    return "expensive result";
});

// Get multiple caches at once
$values = $cache->get(['cache-a', 'cache-b']);
// $values['cache-a'] and $values['cache-b']

// Get all caches matching a wildcard
$values = $cache->get('MyModule*');
~~~~~

---

### $cache->save($name, $data, $expire)

Save a value to the cache.

- **Arguments:** `save(string $name, string|array|PageArray $data, int|string|Page|Template $expire = WireCache::expireDaily)`
- **Returns:** `bool`
- Default expiration is `WireCache::expireDaily` (24 hours).
- `$data` may be a string, a flat array (no nested objects), or a PageArray.
- To expire when any page using a given template is saved, pass a `Page` or `Template` object as `$expire`.
  Passing a `Page` uses that page's template — it does not expire on saves of that specific page alone.
- To expire when any page matching a selector is saved, pass the selector as a string.

~~~~~php
// Save with 1-hour expiration
$cache->save('my-cache', 'Hello world', 3600);

// Save with array data (default daily expiration)
$cache->save('my-data', ['foo' => 'bar', 'num' => 42]);

// Save until any page is saved
$cache->save('nav-html', $navMarkup, WireCache::expireSave);

// Save until any page using $page's template is saved (not just $page itself)
$cache->save('page-nav', $html, $page);

// Save until any page using a specific template is saved
$cache->save('blog-list', $html, $templates->get('blog-post'));

// Expire when a page matching a selector is saved (pass selector as string)
$cache->save('blog-list', $html, 'template=blog-post');

// Never expire
$cache->save('site-config', $data, WireCache::expireNever);
~~~~~

---

## Namespaced caches

Use the `For` variants to automatically scope caches to a module or object namespace,
which keeps cache names unique and makes bulk deletion easy.

### $cache->getFor($ns, $name, $expire, $func)

Get a namespaced cache. Equivalent to `get("NamespaceName__$name", ...)`.

- **Arguments:** `getFor(string|object $ns, string $name, int|string|callable|null $expire = null, callable $func = null)`
- **Returns:** `string|array|PageArray|mixed|null`
- `$ns` may be a string or any object (its class name is used).

~~~~~php
// In a module, use $this as the namespace
$value = $cache->getFor($this, 'results', 3600, function() {
    return $this->buildResults();
});

// String namespace
$value = $cache->getFor('MyModule', 'results', 3600);
~~~~~

---

### $cache->saveFor($ns, $name, $data, $expire)

Save a namespaced cache.

- **Arguments:** `saveFor(string|object $ns, string $name, string|array|PageArray $data, int|Page $expire = WireCache::expireDaily)`
- **Returns:** `bool`

~~~~~php
$cache->saveFor($this, 'results', $data, 3600);
$cache->saveFor('MyModule', 'settings', $settingsArray, WireCache::expireNever);
~~~~~

---

### $cache->deleteFor($ns, $name)

Delete a namespaced cache, or all caches in a namespace.

- **Arguments:** `deleteFor(string|object $ns, string $name = '')`
- **Returns:** `bool`
- Omit `$name` to delete all caches in the namespace.

~~~~~php
// Delete a specific namespaced cache
$cache->deleteFor($this, 'results');

// Delete all caches for this namespace
$cache->deleteFor($this);
~~~~~

---

### $cache->preloadFor($ns, $expire)

Preload all caches in a namespace into memory in a single query (see Preloading below).

- **Arguments:** `preloadFor(string|object $ns, int|string|null $expire = null)`

~~~~~php
$cache->preloadFor($this);
~~~~~

---

## Deleting and expiring

### $cache->delete($name)

Delete a cache by name, or all caches matching a wildcard.

- **Arguments:** `delete(string $name)`
- **Returns:** `bool`

~~~~~php
$cache->delete('my-cache');

// Delete all caches starting with "MyModule__"
$cache->delete('MyModule*');
~~~~~

---

### $cache->deleteAll()

Delete all caches (except those with `expireReserved` status, used internally).

- **Returns:** `int` — number of caches deleted

~~~~~php
$cache->deleteAll();
~~~~~

---

### $cache->expireAll()

Delete all caches with normal date/time expirations. Caches using `expireNever`,
`expireSave`, selector expiration, template/page-save expiration, or `expireReserved`
are not affected.

- **Returns:** `int` — number of caches expired

~~~~~php
$cache->expireAll();
~~~~~

---

## Preloading

Preloading methods are legacy/deprecated helpers. They still work, but new code usually
does not need them.

### $cache->preload($names, $expire)

Preload multiple caches from the database in a single query so subsequent `get()` calls
for those names return immediately from memory.

- **Arguments:** `preload(array $names, int|string|null $expire = null)`

~~~~~php
// Preload several caches before using them
$cache->preload(['header-html', 'nav-html', 'footer-html']);

// Now each get() is served from memory, no DB query
$header = $cache->get('header-html');
$nav    = $cache->get('nav-html');
$footer = $cache->get('footer-html');
~~~~~

---

## Expiration constants

These constants are defined on the `WireCache` class and can be passed as the `$expire`
argument to `get()` and `save()`.

| Constant                    | Value    | Description |
|-----------------------------|----------|---|
| `WireCache::expireNow`      | `0`      | Expire immediately; on `save()` clears any existing cache with that name |
| `WireCache::expireHourly`   | `3600`   | Expires after 1 hour |
| `WireCache::expireDaily`    | `86400`  | Expires after 1 day (**default for `save()`**) |
| `WireCache::expireWeekly`   | `604800` | Expires after 1 week |
| `WireCache::expireMonthly`  | `2419200`| Expires after ~28 days |
| `WireCache::expireNever`    | —        | Never expires (must be deleted manually) |
| `WireCache::expireSave`     | —        | Expires whenever any page is saved or deleted |
| `WireCache::expireSelector` | —        | Internal marker for selector expiration; pass a selector string to `save()` |
| `WireCache::expireIgnore`   | `false`  | On `get()`: return cached value regardless of whether it has expired |

---

## Notes

- Accessible as `$cache` API variable; source: `wire/core/WireCache/WireCache.php`.
- Data must be a string, a flat array (no nested objects), or a `PageArray`. Other
  objects are not supported unless they implement `__toString()`.
- Cache names should be 190 characters or fewer. Use `cacheName()` for long or generated
  names. Use `*` as a wildcard suffix in `get()` and `delete()`.
- The `getFor()`/`saveFor()` namespace is just a `"NamespaceName__"` prefix on the name.
  You can inspect or delete namespaced caches with the wildcard form
  `$cache->delete("MyModule*")`.
- `expireSave` and `expireSelector` caches are expired by the `maintenance()` hook that
  runs automatically on every page save/delete — no manual call needed.
- `expireNever` caches persist across all page saves and must be removed with `delete()`
  or `deleteFor()`. Use sparingly.
- The default cache backend is database (`WireCacheDatabase`). An alternative backend can
  be installed as a separate module implementing `WireCacheInterface` — for example,
  the [WireCacheFilesystem](https://github.com/ryancramerdesign/WireCacheFilesystem) module.
