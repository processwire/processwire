# PageFinder

PageFinder translates ProcessWire selector strings into SQL queries and
executes them against the database. It is the core engine behind
`$pages->find()`, `$pages->get()`, `$pages->count()`, and all other
page-retrieval operations. Given a selector string, array, or
[[Selectors]] object, PageFinder builds a parameterized
`DatabaseQuerySelect` — including joins, sub-selects, and access-control
WHERE clauses — and returns raw database rows (page IDs, parent IDs,
template IDs, scores, or full table columns), **not** Page objects.

Most code will never call PageFinder directly; the [[Pages]] API wraps
it and hydrates the results into [[Page]] / [[PageArray]] objects.
However, PageFinder is useful when you need raw page IDs or columns
without the overhead of loading full Page objects, or when you want to
inspect or hook the generated SQL query.

```php
$finder = new PageFinder();

// Return a simple array of matching page IDs
$ids = $finder->findIDs('template=blog-post, limit=10');

// 'status< unpublished|hidden' is NOT necessary — every find() automatically
// excludes hidden and unpublished pages unless you opt in via options
// or the 'include=' selector.

// Return verbose data: arrays with id, parent_id, templates_id, and score
$rows = $finder->find('template=blog-post, sort=-created, limit=10');

// Access the total count (respecting limit) after a find()
$total = $finder->getTotal();
```

**Source file:** wire/core/PageFinder/PageFinder.php

---

## Properties

| Property     | Type      | Description                                                                 |
|--------------|-----------|-----------------------------------------------------------------------------|
| `includeMode`  | `string` | Include mode from the last `find()`: `''`, `'hidden'`, `'unpublished'`, `'trash'`, or `'all'`. Read-only via `__get`. |
| `checkAccess`  | `bool`   | Whether template access-control checking is active for the current find. `false` when `include=all` or `check_access=0`. Read-only via `__get`. |

---

## Finding pages

### find($selectors, $options)

Main method. Accepts a selector string, array, or [[Selectors]] object,
builds the SQL query, executes it, and returns matching data. The return
value depends on the `returnVerbose`, `returnParentIDs`,
`returnTemplateIDs`, and `returnAllCols` options — by default, an array of
arrays containing `id`, `parent_id`, `templates_id`, and `score` keys.

```php
// Default: returns verbose array like:
// [ ['id' => 1234, 'parent_id' => 1000, 'templates_id' => 50, 'score' => 0], ... ]
$rows = $finder->find('template=blog-post, sort=-created, limit=10');

// Return a simple array of page IDs
$ids = $finder->find('template=blog-post, limit=10', ['returnVerbose' => false]);

// Return only the DatabaseQuerySelect object without executing it
$query = $finder->find('template=blog-post', ['returnQuery' => true]);
echo $query->getQuery(); // inspect the generated SQL
```

Throws `PageFinderException` on database errors and
`PageFinderSyntaxException` on selector syntax errors.

### findIDs($selectors, $options)

Convenience wrapper around `find()` that returns a simple array of
page IDs (no parent, template, or score data).

```php
$ids = $finder->findIDs('parent=/about/, template=staff-member');
// [1023, 1024, 1025, ...]
```

### findVerboseIDs($selectors, $options)

Returns an array of all columns from the `pages` table, indexed by page
ID. Supports joining additional fields, sortfields, child counts,
page paths, and Unix timestamps via options. *(3.0.153+)*

```php
// Return all pages-table columns indexed by page ID
$rows = $finder->findVerboseIDs('template=article, limit=10');

// [ 1234 => ['id'=>1234, 'name'=>'hello-world', 'status'=>1, ...], ... ]

// Join additional custom fields and the sortfield column
$rows = $finder->findVerboseIDs('template=article, limit=10', [
    'joinFields' => ['headline', 'summary'], // join these field tables
    'joinSortfield' => true,                  // include pages_sortfields.sortfield
    'getNumChildren' => true,                 // include numChildren per page
    'unixTimestamps' => true,                 // return dates as Unix timestamps
]);
```

### findParentIDs($selectors, $options)

Returns an array of parent IDs for each matched page (duplicates
removed via `GROUP BY`). Requires `returnVerbose=false`.

```php
$parentIDs = $finder->findParentIDs('template=product, limit=100');
```

### findTemplateIDs($selectors, $options)

Returns an array of `pageID => templateID` pairs. *(3.0.152+)*

```php
$pairs = $finder->findTemplateIDs('parent=/blog/');
// [ 1234 => 50, 1235 => 51, ... ]
```

### count($selectors, $options)

Returns the total number of matching pages without loading page data.
Uses `COUNT(*)` by default rather than `SQL_CALC_FOUND_ROWS`, making it
efficient. *(3.0.121+)*

```php
$total = $finder->count('template=blog-post');
$total = $finder->count('template=blog-post, created>today');
```

---

## Post-find metadata

The following methods return information about the most recent `find()`
or `count()` call. Call them only after a find operation.

| Method            | Returns    | Description                                                              |
|-------------------|------------|--------------------------------------------------------------------------|
| `getTotal()`      | `int`      | Total matches without the limit applied. Only populated when `getTotal` option is enabled (default on for paginated finds). |
| `getLimit()`      | `int`      | The limit from the last find, or `0` if no limit was set.               |
| `getStart()`      | `int`      | The start/offset from the last find, or `0` if none.                    |
| `getParentID()`   | `int`      | Parent ID when the selector contained a single `parent=`, otherwise `0`/`null`. |
| `getTemplatesID()` | `int\|null` | Template ID when the selector contained a single `template=`, otherwise `null`. |
| `getOptions()`    | `array`    | Full options array (merged defaults + runtime overrides) from the last find. |
| `getSelectors()`  | `Selectors\|null` | Fully parsed final Selectors object from the last find. *(3.0.146+)*  |
| `getPageArrayData($pageArray)` | `array` | Data that should be populated onto a resulting [[PageArray]]. Pass a PageArray to populate it directly. |

```php
$ids = $finder->findIDs('template=article, limit=10, start=20');
echo $finder->getTotal(); // e.g. 137
echo $finder->getLimit(); // 10
echo $finder->getStart(); // 20
```

### getTotalTime() *(static)*

Returns the cumulative execution time (in seconds) for all PageFinder
`find()` operations during the current request. Only accumulates when the
`testMode` option is enabled. *(3.0.257+)*

```php
$finder->find('template=article, limit=10', ['testMode' => true]);
echo PageFinder::getTotalTime(); // e.g. 0.0023
```

---

## Options reference

All options are passed as the second argument to `find()` and related
methods. They are merged with the defaults defined in the
`$defaultOptions` property and any `$config->PageFinder` overrides.

### Inclusion and access control

| Option           | Type  | Default | Description |
|------------------|-------|---------|-------------|
| `findHidden`     | `bool` | `false` | Include hidden pages. Also enabled by `include=hidden` in selector. |
| `findUnpublished`| `bool` | `false` | Include hidden and unpublished pages. Also enabled by `include=unpublished`. |
| `findTrash`     | `bool` | `false` | Include hidden, unpublished, and trashed pages. Also enabled by `include=trash`. |
| `findAll`       | `bool` | `false` | Include everything — unpublished, trash, system, no-access. Also enabled by `include=all`. |
| `alwaysAllowIDs` | `array` | `[]` | Page IDs that are never excluded, regardless of inclusion/access settings. |

### Query shape and return type

| Option            | Type       | Default | Description |
|-------------------|------------|---------|-------------|
| `findOne`         | `bool`     | `false` | Optimize for a single result (forces `limit=1`, `start=0`). |
| `loadPages`       | `bool`     | `true`  | When `false`, skips row retrieval — useful when you only need the total. |
| `returnVerbose`   | `bool`     | `true`  | When `true`, returns arrays with `id`, `parent_id`, `templates_id`, `score`. When `false`, returns simple array of page IDs. |
| `returnParentIDs` | `bool`     | `false` | Return parent IDs instead of page IDs. Requires `returnVerbose=false`. |
| `returnTemplateIDs` | `bool`   | `false` | Return `[pageID => templateID]` pairs. Cannot combine with other `return*` options. *(3.0.152+)* |
| `returnAllCols`   | `bool`     | `false` | Return all `pages` table columns indexed by page ID. Cannot combine with other `return*` options. *(3.0.153+)* |
| `returnAllColsOptions` | `array` | `[]` | Sub-options when `returnAllCols=true`. See below. *(3.0.172+)* |
| `returnQuery`     | `bool`     | `false` | When `true`, returns the unexecuted `DatabaseQuerySelect` object. |

#### returnAllColsOptions sub-options

These are nested inside `returnAllColsOptions` when passed to `find()`. When using
`findVerboseIDs()`, they may also be passed flat at the top level of `$options` —
`findVerboseIDs()` automatically moves them into `returnAllColsOptions` internally.

| Key              | Type   | Default | Description |
|------------------|--------|---------|-------------|
| `joinFields`     | `array` | `[]`  | Names of additional custom fields to auto-join into columns. |
| `joinSortfield`  | `bool`  | `false` | Include `sortfield` from the `pages_sortfields` table. |
| `joinPath`       | `bool`  | `false` | Include `path` from the `pages_paths` table (requires [[PagePaths]] module). |
| `getNumChildren` | `bool`  | `false` | Include `numChildren` via a sub-select count. |
| `unixTimestamps` | `bool` | `false` | Return `created`/`modified`/`published` as Unix timestamps instead of ISO-8601. |

### Pagination and totals

| Option        | Type        | Default | Description |
|---------------|-------------|---------|-------------|
| `getTotal`    | `bool\|null` | `null`  | `null` = auto (disabled when `limit=1`, enabled otherwise). `true` = always calculate total. `false` = never. |
| `getTotalType`| `string`    | `'calc'` | `'calc'` uses `SQL_CALC_FOUND_ROWS`. `'count'` uses a separate `COUNT(*)` query. |

### ID-based cursor control

| Option         | Type  | Default | Description |
|----------------|-------|---------|-------------|
| `startAfterID` | `int` | `0`     | Skip all pages until this page ID is encountered, then collect the rest. The page with this ID is excluded. |
| `stopBeforeID` | `int` | `0`     | Stop collecting once this page ID is found. The page with this ID is also excluded. |
| `softLimit`    | `int` | `0`     | Internal: used with `startAfterID`/`stopBeforeID` combined with a `limit` selector. |

### Sorting and misc

| Option          | Type   | Default | Description |
|-----------------|--------|---------|-------------|
| `reverseSort`   | `bool` | `false`  | Reverse whatever sort is specified. |
| `allowCustom`   | `bool` | `false`  | Allow `_custom='selector string'` embedded sub-selectors. |
| `useSortsAfter` | `bool` | `false`  | Experimental: let PageFinder defer sorting to the caller (see `getSortsAfter()`). Not recommended for production. |
| `bindOptions`   | `array` | `[]`   | Options passed through to `DatabaseQuery::bindOptions()` for the primary query. |
| `testMode`      | `bool` | `false`  | Enable exact timing accumulation, accessible via `PageFinder::getTotalTime()`. |

---

## Hooks

PageFinder extends [[Wire]], so all of its `___`-prefixed methods are
hookable. The most commonly hooked methods:

| Hook | When | Arguments | Purpose |
|------|------|-----------|---------|
| `PageFinder::find` | Replace or wrap the main find operation | `$selectors`, `$options` | Modify the selectors or options before the query runs, or replace the return value. |
| `PageFinder::getQuery` | When building the SQL query | `$selectors`, `$options` | Modify the query-building process or the `DatabaseQuerySelect` output. |
| `PageFinder::getQueryUnknownField` | When a selector references a field name that does not exist as a ProcessWire Field | `$fieldName`, `$data` | Map unknown field names to known fields, API variables, or custom handling. Return a `Field` object, `true` (handled), `1` (API var match), or `false` (no match). |
| `PageFinder::getQueryJoinPath` | When a selector uses `path=` or `url=` | `$query`, `$selector` | Modify how path/URL matching joins the query (e.g., for custom URL resolution). |
| `PageFinder::getQueryAllowedTemplatesWhere` | When building access-control WHERE clauses | `$query`, `$where` | Modify the SQL WHERE clause used for template access control. |

### Example: handle unknown field names

```php
/**
 * Map 'region' to a Page reference field named 'geographic_region'
 */
$wire->addHookAfter('PageFinder::getQueryUnknownField', function($event) {
    $fieldName = $event->arguments(0); // string
    $data = $event->arguments(1);      // array

    if($fieldName === 'region') {
        $field = $event->wire('fields')->get('geographic_region');
        if($field) $event->return = $field; // map to a real Field object
    }
});
```

### Example: adjust access-control WHERE clause

```php
/**
 * Allow access to products template for all users
 */
$wire->addHookAfter('PageFinder::getQueryAllowedTemplatesWhere', function($event) {
    /** @var DatabaseQuerySelect $query */
    $query = $event->arguments(0);
    $where = $event->arguments(1); // string

    // Append additional conditions
    $event->return = $where;
});
```

---

## Exceptions

PageFinder throws two exception types:

| Exception | Extends | When thrown |
|-----------|---------|-------------|
| `PageFinderException` | `WireException` | Database errors, query execution failures. |
| `PageFinderSyntaxException` | `PageFinderException` | Malformed selectors: unrecognized include modes, unsupported operators, missing fields, invalid field combinations, etc. |

```php
try {
    $ids = $finder->findIDs('some-invalid@selector');
} catch(PageFinderSyntaxException $e) {
    echo "Syntax error: " . $e->getMessage();
} catch(PageFinderException $e) {
    echo "Find error: " . $e->getMessage();
}
```

The `syntaxError($message)` method is the public entry point that
throws `PageFinderSyntaxException`. You can call it from hooks.

---

## Notes

- **How to get an instance:** `new PageFinder()` or `$pages->getPageFinder()`
  to get the instance used internally by [[Pages]].

- **Site-wide option overrides via `$config->PageFinder`:** You can
  permanently override any default option by setting `$config->PageFinder`
  to an array in `site/config.php`. These overrides are merged before
  per-call options, so per-call options still win.
  ```php
  // site/config.php — always run in testMode to accumulate timing data
  $config->PageFinder = ['testMode' => true];
  ```

- **Page status filtering is automatic.** Every `find()` call modifies
  the selectors to add a `status < statusHidden` condition by default.
  Use `include=hidden`, `include=unpublished`, `include=trash`,
  `include=all` in the selector, or the corresponding `findHidden`,
  `findUnpublished`, `findTrash`, `findAll` options to relax the
  restriction.

- **Access control is on by default.** Non-superusers see only
  templates they have view access to. Disable with `check_access=0` in
  the selector (must be separate from OR conditions) or `findAll=true`.

- **Return values are raw data, not Page objects.** PageFinder returns
  arrays of IDs or row arrays. The [[Pages]] class turns these into
  [[Page]] and [[PageArray]] objects. If you need Page objects, use
  `$pages->find()` instead.

- **Sub-selectors and OR groups.** PageFinder handles embedded
  selectors `[...]` and OR groups `(...)` by recursively executing
  sub-queries to resolve page IDs, then injecting them into the main
  query. See [[Selectors]] for syntax details.

- **Fieldtype-specific query building.** For each custom field,
  PageFinder delegates to the Fieldtype's `getMatchQuery()` method,
  passing a `PageFinderDatabaseQuerySelect` (an abstract subclass of
  `DatabaseQuerySelect`) that carries the originating Field, Selector,
  and PageFinder instance as properties. This allows fieldtypes to
  build their own joins and WHERE conditions.

- **Source file:** `wire/core/PageFinder/PageFinder.php`
  (also `Exceptions.php` and `PageFinderDatabaseQuerySelect.php` in the
  same directory)


