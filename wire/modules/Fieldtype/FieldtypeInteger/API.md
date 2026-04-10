# FieldtypeInteger

Stores a whole number. The blank/unset value is an empty string `''`, not `0`.

---

## Value type

`int` when a value is present, empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->int_field         // int, or '' when blank
(int) $page->int_field   // always int (blank becomes 0)

// Set
$page->int_field = 42;
$page->int_field = -10;
$page->int_field = '';   // clear the value
$page->save('int_field');
```

---

## Selectors

```php
// Exact match
$pages->find('int_field=42');

// No value
$pages->find('int_field=""');

// Comparison
$pages->find('int_field>100');
$pages->find('int_field<0');
$pages->find('int_field>=10, int_field<=100'); // range
```

> **Note on zero vs blank:** By default `0` and blank `""` are equivalent in selectors.
> Enable the **zeroNotEmpty** field setting to make them distinct — then `field=0` matches only
> pages with the value 0, and `field=""` matches only pages with no value.

---

## Notes

- The blank/default value is `''` (empty string), not `0`.
- Setting `zeroNotEmpty=1` makes 0 and blank distinct in selectors.
- Setting `defaultValue` assigns a fallback for pages with no value entered.
- Compatible fieldtypes: `FieldtypeInteger`, `FieldtypeFloat`, `FieldtypeDecimal`, `FieldtypeText`.
- Database column: `int NOT NULL`.
