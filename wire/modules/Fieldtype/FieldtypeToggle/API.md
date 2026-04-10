# FieldtypeToggle

A yes/no/other toggle with an optional "unknown" (no-selection) state. Unlike `FieldtypeCheckbox`,
Toggle can distinguish between a "no" selection and no selection at all.

---

## Value type

`int|string` — one of: `1` (yes), `0` (no), `2` (other, if enabled), or `''` (unknown / no selection).

Constants: `FieldtypeToggle::valueYes` (1), `::valueNo` (0), `::valueOther` (2), `::valueUnknown` ('').

---

## Getting and setting values

```php
// Get with output formatting OFF (always int or ''):
$page->of(false);
$page->toggle_field;  // 1, 0, 2, or ''

// Get with output formatting ON (type depends on formatType field setting):
$page->of(true);
$page->toggle_field;  // int, bool, or string label

// Set — accepts int, keyword string, or bool:
$page->toggle_field = 1;         // yes
$page->toggle_field = 0;         // no
$page->toggle_field = 2;         // other (if enabled for field)
$page->toggle_field = '';        // unknown / no-selection
$page->toggle_field = 'yes';     // same as 1
$page->toggle_field = 'no';      // same as 0
$page->toggle_field = 'unknown'; // same as ''
$page->toggle_field = true;      // same as 1
$page->toggle_field = false;     // same as 0
$page->save('toggle_field');
```

---

## Selectors

```php
// Yes selected
$pages->find('toggle_field=1');
$pages->find('toggle_field=yes');

// No selected
$pages->find('toggle_field=0');
$pages->find('toggle_field=no');

// No selection (unknown)
$pages->find('toggle_field=""');
$pages->find('toggle_field=unknown');

// Yes or no (any selection made)
$pages->find('toggle_field=1|0');

// No or no-selection
$pages->find('toggle_field=0|""');
```

> **Note:** `0` (no) and `''` (unknown/no-selection) are distinct values. `toggle_field=0` will not
> match pages with no selection, and vice versa.

---

## Output / markup

Depends on the **formatType** field setting (configured under the Details tab):

| formatType | Output |
|---|---|
| `0` Integer (default) | `1`, `0`, `2`, or `''` |
| `1` Boolean | `true` / `false` (no-selection stays `''`) |
| `2` String | Label text (e.g. "Yes", "No") |
| `3` Entities | Label text, HTML-entity encoded |

```php
// formatType=1 (Boolean), output formatting ON:
if($page->toggle_field === true) echo "Yes";
if($page->toggle_field === false) echo "No";
if($page->toggle_field === '') echo "No selection";

// formatType=2 (String), output formatting ON:
echo $page->toggle_field; // outputs "Yes", "No", or custom label
```

---

## Notes

- Four states: `1`=yes, `0`=no, `2`=other (optional), `''`=unknown/no-selection.
- With output formatting OFF, always returns int or blank string regardless of formatType.
- Compatible fieldtypes: `FieldtypeToggle`, `FieldtypeCheckbox`.
- Database column: `tinyint NOT NULL`, indexed.
