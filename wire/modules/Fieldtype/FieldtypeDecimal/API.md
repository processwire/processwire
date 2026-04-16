# FieldtypeDecimal

Stores an exact decimal number using MySQL's `DECIMAL` column type. Unlike `FieldtypeFloat`, there are no floating-point rounding errors. The value is always returned as a string to preserve precision.

---

## Value type

`string` — e.g. `"123.45"`. Returns `"0.00"` (or the equivalent zero at configured precision)
when blank, not `''`. Zero and blank are equivalent due to `zeroNotEmpty` behavior (see Notes).

---

## Getting and setting values

```php
// Get
$page->decimal_field    // string, e.g. "123.45" (or "0.00" when blank)

// Set
$page->decimal_field = '123.45';
$page->decimal_field = 99;     // int/float accepted, converted to string
$page->decimal_field = '';     // clear the value
$page->save('decimal_field');
```

---

## Selectors

```php
$pages->find('decimal_field=123.45');
$pages->find('decimal_field>100');
$pages->find('decimal_field=""');
$pages->find('decimal_field>=10.00, decimal_field<=99.99');
```

> **zeroNotEmpty**: by default `"0.00"` and blank are equivalent — there is no way to distinguish
> "no value" from "zero" unless `zeroNotEmpty` is disabled in the field settings.

---

## Notes

- Value is always a string (e.g. `"9.99"`) to preserve exact decimal representation.
- Configure `digits` (total digits including before and after decimal, default 10) and `precision` (digits after decimal, default 2). Example: `DECIMAL(10,2)` supports values up to `99999999.99`.
- Schema is updated automatically when `digits` or `precision` settings change.
- Compatible fieldtypes: `FieldtypeDecimal`, `FieldtypeInteger`, `FieldtypeFloat`.
- Database column: `DECIMAL(digits,precision)`.
