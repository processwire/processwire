# FieldtypeCache

Caches the serialized values of one or more other fields into a single database column.
Two primary use cases: (1) **search aggregation** — combine multiple text fields so they
can all be searched with a single selector; (2) **query reduction** — pre-load field values
from the cache column instead of issuing individual field queries at runtime.

---

## Value type

`array` — an array of the cached field names (e.g. `['body', 'sidebar', 'intro']`).
This is metadata, not the cached data itself. The actual field values are silently
injected into the page object during load — access them normally via `$page->body`, etc.

---

## How it works

On every page save the field serializes the values of all configured fields into its
`data` column as a JSON blob. On wakeup it deserializes that blob and populates any
of those fields on the page that have not already been loaded, eliminating the need for
separate per-field queries.

```php
// Trigger the cache load (required if the field is not set to autojoin)
$fieldNames = $page->cache_field;

// After the cache loads, access the cached fields as normal
echo $page->body;
echo $page->sidebar;
```

If the field is set to **autojoin** in its advanced settings, the cache loads automatically
when the page is loaded — no explicit access needed.

---

## Selectors

Because all cached field values are stored together in a single FULLTEXT-indexed column,
you can search across all of them with one selector:

```php
// Search body, sidebar, and intro all at once
$results = $pages->find('cache_field*=search term');

// Pages with any non-empty cached content
$pages->find('cache_field!=""');
```

This is the main performance advantage over separate per-field fulltext queries.

---

## Notes

- **No inputfield**: this field type has no editor input — it is invisible on the page
  edit form. Values are managed automatically on every page save.
- **Autojoin recommended**: set the field to autojoin (field advanced settings) so the
  cache loads automatically on page load. Without autojoin, access `$page->cache_field`
  explicitly before accessing the cached fields.
- **Cache population**: the cache is written on every page save. When adding a cache
  field to templates that already have existing pages, use the "Regenerate Cache" button
  in the field's configuration to populate it for all existing pages.
- `cacheFields` (array): the field names whose values are stored in the cache.
- `cacheDisabled` (bool): when true, the cache is bypassed entirely — useful for
  testing or debugging.
- Database column: `data MEDIUMTEXT NOT NULL`, FULLTEXT indexed.
- Compatible fieldtypes: `FieldtypeCache` only.
