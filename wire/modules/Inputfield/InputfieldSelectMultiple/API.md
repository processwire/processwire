# InputfieldSelectMultiple

Multiple selection via `<select multiple>`. Extends [[InputfieldSelect]] — all option
management, querying, and attribute methods are inherited. Returns an array value.

```php
$f = $modules->get('InputfieldSelectMultiple');
$f->name = 'colors';
$f->label = 'Favorite colors';
$f->addOptions(['red' => 'Red', 'green' => 'Green', 'blue' => 'Blue']);
$f->val(['red', 'blue']); // pre-select multiple values
$form->add($f);
```

See [[InputfieldSelect]] for options management.
See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, showIf,
rendering, processing, etc.).

## Properties

| Property | Type  | Default | Description                              |
|----------|-------|---------|------------------------------------------|
| `size`   | `int` | `10`    | Number of visible rows in the select box |

## Constants

| Constant      | Value | Description                          |
|---------------|-------|--------------------------------------|
| `defaultSize` | `10`  | Default number of visible rows       |

## Notes

- Value is always an array. Use `$f->val(['a', 'b'])` to set multiple selections.
- Blank options are silently ignored — multi-select has no concept of a "blank" selection.
- Implements `InputfieldHasArrayValue`. After `processInput()`, `$f->val()` returns an array of selected values.
- **Source file:** `wire/modules/Inputfield/InputfieldSelectMultiple.module`
