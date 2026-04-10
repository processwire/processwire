# FieldtypeEmail

Stores a validated email address. Extends `FieldtypeText`.

---

## Value type

`string` — a valid email address, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->email_field  // string, e.g. "user@example.com"

// Set
$page->email_field = 'user@example.com';
$page->email_field = '';  // clear
$page->save('email_field');
```

Values are sanitized through `$sanitizer->email()` — invalid addresses become blank.

---

## Selectors

Supports the same string operators as `FieldtypeText`:

```php
$pages->find('email_field=user@example.com');
$pages->find('email_field=""');           // no email
$pages->find('email_field*=example.com'); // contains
$pages->find('email_field$=.org');        // ends with
```

---

## Output / markup

```php
echo '<a href="mailto:' . $page->email_field . '">' . $page->email_field . '</a>';
```

Apply `TextformatterEntities` (recommended) or entity-encode manually before HTML output.

---

## Notes

- Invalid email addresses are sanitized to blank.
- `allowIDN=1` field setting permits internationalized domain names.
- A unique index option prevents duplicate email addresses across pages.
- Database column: `varchar NOT NULL` (length = DB max index length, typically 191–255).
- Compatible fieldtypes: any fieldtype extending `FieldtypeText`.
