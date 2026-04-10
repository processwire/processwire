# FieldtypeCheckbox

Stores an ON/OFF toggle as an integer. The checked (ON) value is `1`; the unchecked (OFF) value is `0`.

---

## Value type

`int` — always `1` or `0`.

---

## Getting and setting values

```php
// Get
$page->checkbox_field       // int: 1 or 0
(bool) $page->checkbox_field  // cast to bool if needed

// Set
$page->checkbox_field = 1;  // check it
$page->checkbox_field = 0;  // uncheck it
$page->checkbox_field = true;   // also valid — sanitized to 1
$page->checkbox_field = false;  // also valid — sanitized to 0
$page->save('checkbox_field');
```

---

## Selectors

```php
// Find pages where checkbox is checked
$pages->find('checkbox_field=1');

// Find pages where checkbox is NOT checked
// Note: unchecked rows are not stored in the database, so this uses a LEFT JOIN
$pages->find('checkbox_field!=1');

// Equivalent to the above (unchecked = 0)
$pages->find('checkbox_field=0');
```

> **Note:** Unchecked pages do not have a row in the field's database table. The `!=1` selector
> handles this via a LEFT JOIN to correctly include pages that were never checked.

---

## Output / markup

`$page->renderValue('checkbox_field')` renders differently depending on context:

- **In the admin:** renders a FontAwesome checkbox icon (`fa-check-square-o` or `fa-square-o`)
- **Outside the admin:** renders a disabled HTML `<input type="checkbox">`

In both cases a hidden `<span>` with a UTF-8 checkbox character (`☒` or `☐`) is included so
the value survives tag-stripping.

Other common use cases: 
```php
if($page->checkbox_field) {
  echo "Yes";
} else {
  echo "No";
}

echo $page->checkbox_field ? 'Yes' : 'No';

echo $page->if('checkbox_field', 'Yes', 'No');
```

---

## Notes

- The blank/default value is `0` (unchecked).
- Any truthy PHP value set on the field is sanitized to `1`; any falsy value to `0`.
- Compatible fieldtypes: `FieldtypeCheckbox`, `FieldtypeToggle` (if installed).
- Database column: `tinyint NOT NULL`, indexed.
