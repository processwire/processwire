# InputfieldURL

URL input with built-in sanitization and validation. Extends [[InputfieldText]] —
text properties (`minlength`, `maxlength`, `placeholder`, etc.) are inherited and
documented there.

```php
$f = $modules->get('InputfieldURL');
$f->name = 'website';
$f->label = 'Website URL';
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API.

## Properties

| Property      | Type   | Default | Description                                                                    |
|---------------|--------|---------|--------------------------------------------------------------------------------|
| `noRelative`  | `bool` | `false` | When `true`, relative URLs are rejected (only absolute URLs accepted)          |
| `addRoot`     | `bool` | `false` | When `true`, prepends the site root to relative URLs                           |
| `allowIDN`    | `bool` | `false` | Allow internationalized domain names (e.g. `http://dømain.com`)                |
| `allowQuotes` | `bool` | `false` | Allow quote characters (`'` and `"`) in URLs                                   |

## Sanitization and validation

When the `value` attribute is set, the value is run through `$sanitizer->url()`. If the
result is empty or cannot be reconciled with the input, an error notice is added and
the previous value is restored. If a scheme (`http://` or `https://`) was automatically
prepended, a message notice is added.

## Properties in practice

```php
// Require absolute URLs only
$f->noRelative = true;

// Allow IDN URLs
$f->allowIDN = true;

// Allow quote characters (rarely needed)
$f->allowQuotes = true;
```

When `addRoot = true` and the site runs from a subdirectory, a note is automatically
added to the field describing how to enter local URLs without the subdirectory prefix.

## Notes

- Default `maxlength` is `1024`.
- The `stripTags` config option is removed (not applicable to URL inputs).
- **Source file:** `wire/modules/Inputfield/InputfieldURL/InputfieldURL.module`
