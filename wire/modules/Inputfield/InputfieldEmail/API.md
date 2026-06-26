# InputfieldEmail

Email address input with built-in sanitization and validation. Extends [[InputfieldText]]
— text properties (`minlength`, `maxlength`, `placeholder`, `pattern`, etc.) are
inherited and documented there.

```php
$f = $modules->get('InputfieldEmail');
$f->name = 'email';
$f->label = 'Your email address';
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API.

## Properties

| Property       | Type      | Default      | Description                                                                          |
|----------------|-----------|--------------|--------------------------------------------------------------------------------------|
| `confirm`      | `int`     | `0`          | Set to `1` to render a second input requiring the user to re-enter the email         |
| `confirmLabel` | `string`  | `'Confirm'`  | Placeholder/aria-label for the confirmation input                                    |
| `allowIDN`     | `int`     | `0`          | IDN support level: `0`=ASCII only, `1`=IDN domain, `2`=IDN domain + UTF-8 local part |
| `maxlength`    | `int`     | `250`        | Maximum allowed length (inherited HTML attribute)                                    |

## Confirmation input

When `confirm = 1`, a second input is rendered beneath the main one. Both values must
match (case-insensitive) or `processInput()` records an error and blanks the value.

```php
$f->confirm = 1;
$f->confirmLabel = 'Re-enter email'; // custom placeholder text
```

## Internationalized email addresses (IDN)

By default only ASCII-standard emails are accepted. `allowIDN` controls whether
internationalized addresses are permitted:

```php
$f->allowIDN = 0; // bob@domain.com only (default, broadest compatibility)
$f->allowIDN = 1; // bob@dømain.com  — IDN domain allowed
$f->allowIDN = 2; // bøb@dømain.com  — IDN domain + UTF-8 local part allowed
```

Note: `allowIDN = 2` cannot use `<input type="email">` (HTML5 does not support UTF-8
local parts), so the field falls back to `type="text"` with a regex pattern attribute.

## Sanitization and validation

On `setAttribute('value', …)` and `processInput()`, the value is run through
`$sanitizer->email()`. An invalid address triggers an error notice and the value is
set to an empty string. The previous valid value is preserved on `processInput()`.

## Notes

- When used with a `FieldtypeEmail` field that has the unique-value flag set,
  `processInput()` also verifies that the address is not already in use on another page.
- `stripTags` and `pattern` config options are removed (not applicable to email inputs).
- **Source file:** `wire/modules/Inputfield/InputfieldEmail/InputfieldEmail.module`
