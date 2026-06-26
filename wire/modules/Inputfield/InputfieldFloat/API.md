# InputfieldFloat

Floating point number input. Extends [[InputfieldInteger]] — all integer properties
(`inputType`, `min`, `max`, `size`, `placeholder`, `initValue`, `defaultValue`, range
validation, empty-vs-zero behavior, etc.) are inherited and documented there.

```php
$f = $modules->get('InputfieldFloat');
$f->name = 'price';
$f->label = 'Price';
$f->precision = 2;
$f->min = 0;
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API.

## Properties

| Property    | Type          | Default | Description                                                                                      |
|-------------|---------------|---------|--------------------------------------------------------------------------------------------------|
| `precision` | `int\|null`   | `2`     | Decimal places to round to; `-1` or `null` disables rounding (3.0.193+)                          |
| `digits`    | `int`         | `0`     | Total significant digits for fixed-decimal (MySQL DECIMAL) mode                                  |
| `noE`       | `bool`        | `false` | Convert scientific notation (`1.23E-4`) to a plain decimal in the input (3.0.193+)               |
| `step`      | `int\|string` | `'any'` | HTML5 `step`; defaults to `'any'` (allows any decimal); only applies when `inputType = 'number'` |

## Precision

`precision` controls how many decimal places the value is rounded to when set:

```php
$f->precision = 2;  // 3.14159 → 3.14 (default)
$f->precision = 0;  // 3.14   → 3    (integer-like, but float type)
$f->precision = -1; // no rounding applied (3.0.193+)
```

When `inputType = 'number'` and `step` is `'any'`, the step attribute is automatically
derived from `precision` at render time (e.g. `precision = 2` → `step = ".01"`).

## Scientific notation

Some locales or data sources produce values in scientific notation (`1.5E-3`). By
default these are passed through as-is. Set `noE = true` to convert them to plain
decimals in the rendered `<input>`:

```php
$f->noE = true; // 1.5E-3 → 0.0015 in the input element
```

## Locale handling

When `inputType = 'number'`, the value is converted to use `.` as the decimal
separator regardless of locale, because the HTML5 number input requires it.
For `inputType = 'text'`, locale-specific separators are preserved.

## Notes

- `isEmpty()` (inherited from [[InputfieldInteger]]) returns `true` only for empty
  string — the float `0.0` is a present, non-empty value.
- **Source file:** `wire/modules/Inputfield/InputfieldFloat/InputfieldFloat.module`
