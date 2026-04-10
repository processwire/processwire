# FieldtypePageTitle

Stores a page title. Functionally equivalent to `FieldtypeText` but reserved for use as a
title field. Extends `FieldtypeText`. Inherits all `TextField` settings.

---

## Value type

`string` — the page title, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->title  // string

// Set
$page->title = 'My Page Title';
$page->save('title');
```

---

## Selectors

Supports the same string operators as `FieldtypeText`:

```php
$pages->find('title=About Us');
$pages->find('title*=keyword');   // fulltext
$pages->find('title^=Welcome');   // starts with
$pages->find('title=""');         // no title
```

---

## Notes

- Compatible only with fieldtypes implementing `FieldtypePageTitleCompatible` (e.g. `FieldtypePageTitleLanguage`).
- Inherits all settings from `TextField` (textformatters, maxlength, etc.).
- Database column: `text NOT NULL`, indexed.
