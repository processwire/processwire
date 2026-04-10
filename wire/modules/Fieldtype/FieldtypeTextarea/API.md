# FieldtypeTextarea

Stores multi-line text, optionally as HTML/Markup. Extends `FieldtypeText`.

---

## Value type

`string` — multi-line text or HTML markup, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->body   // string (formatted when output formatting is on)
$page->getUnformatted('body') // raw value, no Textformatters applied

// Set
$page->body = 'Some <b>HTML</b> content.';
$page->save('body');
```

---

## Selectors

Supports the same operators as `FieldtypeText` (fulltext, LIKE, etc.):

```php
$pages->find('body*=keyword');  // fulltext match
$pages->find('body%=keyword');  // LIKE match
$pages->find('body=""');        // no content
```

---

## Output / markup

```php
// output formatting ON (default in front-end):
echo $page->body;  // Textformatters applied, MarkupQA corrections applied for HTML

// output formatting OFF:
echo $page->getUnformatted('body');  // raw stored value
```

When `contentType` is set to HTML, the field automatically corrects `href` and `src` attributes
at save/load time so that URLs remain valid if the site moves to a different subdirectory or domain.

---

## Notes

- `contentType` setting: `0`=plain text (default), `1`=HTML/Markup, `2`=HTML with image management.
- `inputfieldClass` setting: the Inputfield used for editing. Common values: `InputfieldTextarea` (default),
  `InputfieldCKEditor`, `InputfieldTinyMCE`.
- `htmlOptions` setting: array of flags for HTML content type — link abstraction, image alt management,
  removing inaccessible images, lazy loading. Only relevant when `contentType >= 1`.
- Database column: `mediumtext NOT NULL`, fulltext indexed.
- Compatible fieldtypes: any fieldtype extending `FieldtypeText`.
