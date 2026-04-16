# FieldtypeURL

Stores a URL — local/relative or absolute. Extends `FieldtypeText`.

---

## Value type

`string` — a valid URL, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->url_field   // string, e.g. "https://example.com/path/"

// Set
$page->url_field = 'https://example.com/path/';
$page->url_field = '/local/path/';  // relative URL (if allowed by field setting)
$page->url_field = '';  // clear
$page->save('url_field');
```

Values are sanitized through `$sanitizer->url()` — dangerous URL schemes (e.g. `javascript:`) are
stripped to blank, but arbitrary non-URL strings are passed through as-is. Validate URL format
separately if your use case requires it.

---

## Selectors

```php
$pages->find('url_field=""');           // no URL
$pages->find('url_field^=https://');    // starts with
$pages->find('url_field*=example.com'); // contains
```

---

## Output / markup

```php
// With TextformatterEntities applied and output formatting ON:
echo '<a href="' . $page->url_field . '">Link</a>';
```

If the `addRoot` field setting is enabled, relative URLs (starting with `/`) are automatically
prepended with the site's root path when output formatting is on.

---

## Notes

- `noRelative=1` disables relative/local URLs; only full protocol URLs are accepted.
- `allowIDN=1` permits internationalized domain names.
- `allowQuotes=1` allows quote characters in URLs (always entity-encode output when used).
- `addRoot=1` prepends the site's root path to relative URLs during output formatting — useful for subdirectory installs.
- Strongly recommended: apply `TextformatterEntities` to any URL field used in HTML output.
- Database column: `text NOT NULL` (inherits from `FieldtypeText`).
- Compatible fieldtypes: any fieldtype extending `FieldtypeText`.
