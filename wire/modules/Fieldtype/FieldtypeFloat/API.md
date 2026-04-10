# FieldtypeFloat

Stores a floating-point number. Supports single-precision (`float`) or double-precision (`double`) database columns.

---

## Value type

`float` when a value is present, empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->float_field         // float, or '' when blank
(float) $page->float_field // always float (blank becomes 0.0)

// Set
$page->float_field = 3.14;
$page->float_field = '';   // clear the value
$page->save('float_field');
```

---

## Selectors

```php
$pages->find('float_field=3.14');
$pages->find('float_field>1.0');
$pages->find('float_field=""');  // no value
$pages->find('float_field>=1.5, float_field<=9.5'); // range
```

> Same **zeroNotEmpty** behavior as `FieldtypeInteger` — by default `0` and blank are equivalent.

---

## Notes

- Default precision is 2 decimal places. Set `precision` to a different integer, or `-1` to disable rounding.
- Column type is `float` (single-precision) by default. Switch to `double` for higher precision when needed.
- Compatible fieldtypes: `FieldtypeFloat`, `FieldtypeInteger`, `FieldtypeDecimal`, `FieldtypeText`.
- Database column: `float NOT NULL` or `double NOT NULL`.
