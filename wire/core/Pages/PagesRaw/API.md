# PagesRaw / $pages->findRaw()

Read this when you need raw page data from `$pages->findRaw()`, `$pages->getRaw()`, or `$pages->raw` without loading Page objects or applying output formatting. `PagesRaw` is useful for fast read-only data exports, API responses, reports, and selectors where you only need specific fields, native page columns, page-reference subfields, meta data, or reference lookups.

## Common use

```php
// Find many pages and return raw values, indexed by page ID
$rows = $pages->findRaw("template=blog-post", ['title', 'date']);

// Same as above but with no exclusions
$rows = $pages->findRaw("template=blog-post, include=all", ['title', 'date']);
$rows = $pages->findRaw("template=blog-post", ['title', 'date'], ['include' => 'all']); // alternate syntax

// Get one page's raw values by ID, path, or selector
$row = $pages->getRaw(1234, ['title', 'summary']);
$row = $pages->getRaw('/blog/my-post/', ['title', 'summary']);
$row = $pages->getRaw('template=blog-post, name=my-post', ['title']);

// Omit the field argument to get all native columns and page fields
$row = $pages->getRaw(1234); // 3.0.261+
$rows = $pages->findRaw("template=blog-post, limit=10");

// Direct helper access also works
$rows = $pages->raw()->find("template=blog-post", ['title', 'date']);
$rows = $pages->raw()->find("template=product", ['title', 'price'], ['include' => 'unpublished']);
$row = $pages->raw()->get(1234, ['title']);
```

`findRaw()` returns an array of rows. By default the outer array is indexed by page ID.
Like with `$pages->find()` the `$pages->findRaw()` method excludes hidden, unpublished,
and no-access pages (for current user) by default. You can override this by specifying
an `include=...` in your selector or in the `$options` argument. Examples of `include=`
in the selector are: `include=hidden` (allow hidden pages), `include=unpublished` 
(allow hidden and unpublished pages) or `include=all` (no exclusions). 

`getRaw()` returns the first matching row, or boolean `false` when no page matches.
Like with the `$pages->get()` method, there are no exclusions for page status or user access.

## Raw Values

Values are read directly from database tables, without Page objects and without output formatting.

```php
// Raw title data from field_title.data
$titles = $pages->findRaw("template=blog-post", "title");

// Native pages table column
$names = $pages->findRaw("template=blog-post", "name");

// Multiple values per row
$rows = $pages->findRaw("template=blog-post", ['id', 'name', 'title']);
```

When `$field` is a single string with one field, each result value is the field value:

```php
$titles = $pages->findRaw("template=blog-post", "title");
// [1234 => "First post", 1235 => "Second post"]
```

When `$field` is an array or CSV string, each result value is an associative array:

```php
$rows = $pages->findRaw("template=blog-post", ['title', 'date']);
// [1234 => ['title' => 'First post', 'date' => '2026-01-01']]
```

## Selectors

The selector argument accepts normal ProcessWire selectors (string), page IDs, page 
paths, arrays of page IDs, and `Selectors` objects.  *Prior to 3.0.261, scalar ID or 
path selectors required one or more fields in the second argument.*

```php
$row = $pages->getRaw(1234); // 3.0.261+
$row = $pages->getRaw('/about/contact/'); // 3.0.261+
$rows = $pages->findRaw([1234, 1235, 1236], ['title']);
$rows = $pages->findRaw("parent=/blog/, sort=-created, limit=20", ['title']);
```

`findRaw()` uses the same access/status selector rules as `$pages->find()` unless you specify an `include` mode:

```php
$rows = $pages->findRaw("template=blog-post, include=hidden", ['title']);
$rows = $pages->findRaw("template=blog-post, include=all", ['title']);
```

`getRaw()` sets `findAll` by default, so it behaves more like `$pages->get()` and does not apply normal find exclusions unless overridden.

## Field Argument

```php
// Single field or native column
$rows = $pages->findRaw("template=blog-post", "title");
$rows = $pages->findRaw("template=blog-post", "name");

// CSV string
$rows = $pages->findRaw("template=blog-post", "title,date,summary");

// Array of fields
$rows = $pages->findRaw("template=blog-post", ['title', 'date', 'summary']);

// Rename returned fields
$rows = $pages->findRaw("template=blog-post", [
    'title' => 'headline',
    'summary' => 'description',
]);
```

The field list may also be specified in the selector:

```php
$rows = $pages->findRaw("template=blog-post, field=title|summary");
$rows = $pages->findRaw("template=blog-post, fields=title|date");
```

## Subfields

Use `field.subfield` or `field[subfield]` to request a specific database column or supported external subfield.

```php
// Database columns from a field table
$rows = $pages->findRaw("template=event", ['date', 'price.amount']);

// All columns from a field table
$rows = $pages->findRaw("template=villa", "rates_table.*");

// Multiple subfields for one field
$rows = $pages->findRaw("template=blog-post", [
    'categories' => ['id', 'title'],
]);
```

Unknown columns on ordinary field tables throw `WireException` (3.0.261+). Page reference, 
Repeater, and Options fields (as examples) support special subfield lookups. Many non-core
Fieldtypes also support them as well. 

### Page References And Repeaters

For Page reference and Repeater fields, subfields are loaded from the referenced pages.

```php
// category IDs and titles
$rows = $pages->findRaw("template=blog-post", ['title', 'categories.title']);

// Multiple referenced-page fields
$rows = $pages->findRaw("template=blog-post", [
    'title',
    'categories' => ['id', 'name', 'title'],
]);
```

### Options Fields

Options fields can return selected option IDs, titles, values, or all option properties.

```php
// Selected option IDs
$rows = $pages->findRaw("template=product", "color");

// Selected option titles or values
$rows = $pages->findRaw("template=product", ['color.title', 'color.value']);

// All selected option properties
$rows = $pages->findRaw("template=product", "color.*");
```

## Special Fields

### Parent

Use `parent.field` to include data from each result page's parent.

```php
$rows = $pages->findRaw("template=blog-post", ['title', 'parent.title']);
```

### Template

Use `template.property` to include template properties.

```php
$rows = $pages->findRaw("template=blog-post", ['title', 'template.name']);
```

### URL And Path

`url` and `path` are runtime values and require the `PagePaths` module.

```php
$rows = $pages->findRaw("template=blog-post", ['title', 'url', 'path']);
```

### Meta

Use `meta` for all page meta data, or `meta.name` for specific meta names.

```php
$rows = $pages->findRaw("template=blog-post", ['title', 'meta']);
$rows = $pages->findRaw("template=blog-post", ['title', 'meta.my_setting']);
```

### References

Use `references` to include pages that reference each found page through Page reference fields.

```php
// Referencing page IDs
$rows = $pages->findRaw("template=category", ['title', 'references']);

// Referencing page titles
$rows = $pages->findRaw("template=category", ['title', 'references.title']);

// Group references by field name
$rows = $pages->findRaw("template=category", ['title', 'references.field']);
```

## Options

Options may be passed as the third argument or, for common options, in the selector.

```php
$rows = $pages->findRaw("template=blog-post", ['title'], [
    'indexed' => false,
    'entities' => ['title'],
    'nulls' => true,
]);

$rows = $pages->findRaw("template=blog-post, field=title|summary, entities=title");
$rows = $pages->findRaw("template=blog-post, field=title|summary, options=objects|entities");
```

| Option     | Default | Description |
|------------|---------|-------------|
| `indexed`  | `true` for `findRaw`, `false` for `getRaw` | Index outer result array by page ID? |
| `objects`  | `false` | Convert associative row arrays to objects. |
| `entities` | `false` | Entity encode string values. Use `true` for all strings or an array/list of field names. |
| `nulls`    | `false` | Populate missing requested fields with `null`. |
| `flat`     | `false` | Flatten nested arrays to `field.subfield` keys. Use a string to set the delimiter. |

`objects` and `flat` are not intended to be used together.

## Native Column Helpers

`$pages->raw()->col()` and `$pages->raw()->cols()` read native `pages` table columns only.

```php
// One column for one page
$name = $pages->raw()->col(1234, 'name');

// One column for multiple pages, indexed by page ID
$names = $pages->raw()->col([1234, 1235], 'name');

// Multiple columns for one page
$row = $pages->raw()->cols(1234, ['id', 'name', 'status']);

// Multiple columns for multiple pages
$rows = $pages->raw()->cols([1234, 1235], ['id', 'name', 'templates_id']);
```

These methods only accept native `pages` table columns such as `id`, `parent_id`, `templates_id`, `name`, `status`, `created`, `modified`, `created_users_id`, and `modified_users_id`.

## Notes

- Use `$pages->findRaw()` and `$pages->getRaw()` for public API code. `$pages->raw()` exposes the helper directly.
- Raw field values may differ from `$page->field` because output formatting and Fieldtype formatting are not applied.
- Single-field requests return scalar values per page; array field requests return row arrays per page.
- `getRaw()` returns `false` when no matching page is found.
- `findRaw()` and `getRaw()` do not load Page objects, so it can be faster and use less memory for read-only data access.
- Use normal `$pages->find()` or `$pages->get()` when you need Page objects, formatted values, hooks around page access, or Fieldtype runtime behavior.
