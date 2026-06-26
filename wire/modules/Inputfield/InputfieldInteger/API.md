# InputfieldInteger

Integer input. Extends [[Inputfield]] directly (not InputfieldText). Accepts positive
and negative integers; decimals and comma-formatted numbers are rounded to the nearest
integer.

```php
$f = $modules->get('InputfieldInteger');
$f->name = 'quantity';
$f->label = 'Quantity';
$f->min = 1;
$f->max = 100;
$form->add($f);
```

[[InputfieldFloat]] extends this class and adds decimal support.

See [[Inputfield]] for the shared Inputfield API.

## Properties

| Property       | Type           | Default  | Description                                                               |
|----------------|----------------|----------|---------------------------------------------------------------------------|
| `inputType`    | `string`       | `'text'` | Input type: `'text'` or `'number'` (HTML5 numeric input)                  |
| `min`          | `int\|float`   | `''`     | Minimum allowed value; enforced when setting value attribute              |
| `max`          | `int\|float`   | `''`     | Maximum allowed value; enforced when setting value attribute              |
| `step`         | `int\|float`   | `''`     | HTML5 `step` attribute; only applied when `inputType = 'number'`          |
| `size`         | `int`          | `10`     | Input `size` attribute; `0` renders full-width                            |
| `placeholder`  | `string`       | `''`     | Input placeholder text                                                    |
| `initValue`    | `int\|float`   | `''`     | Default value when used as a standalone Inputfield (not with a Fieldtype) |
| `defaultValue` | `int\|float`   | `''`     | Default value when used with [[FieldtypeInteger]]                         |

## Input type

```php
$f->inputType = 'text';   // plain text input — no browser numeric UI (default)
$f->inputType = 'number'; // HTML5 numeric input — browser adds up/down arrows,
                          // and min/max/step are enforced client-side
```

The `text` type is the default because it avoids browser-specific numeric UI quirks.
Use `number` when you want native browser validation or the spinner UI.

## Range and step

`min` and `max` are enforced at the PHP level regardless of `inputType`. If a submitted
value falls outside the range, an error notice is added and the previous value is
restored.

```php
$f->min = 0;
$f->max = 999;
$f->step = 5; // only affects the browser UI when inputType='number'
```

## Empty vs. zero

`isEmpty()` returns `true` only when the value is an empty string — the integer `0`
is considered a present, non-empty value:

```php
$f->val(0);  // isEmpty() → false
$f->val(''); // isEmpty() → true
```

## Sanitization

On input, the value is stripped of non-digit characters (except `.`, `,`, and a
leading `-`). Decimals and comma-formatted numbers are rounded: `1,234.7` → `1235`.
Negative numbers are preserved: `-42` → `-42`.

## Notes

- [[InputfieldFloat]] extends this class and overrides `sanitizeValue()` and
  `typeValue()` to allow decimal values.
- **Source file:** `wire/modules/Inputfield/InputfieldInteger/InputfieldInteger.module`
