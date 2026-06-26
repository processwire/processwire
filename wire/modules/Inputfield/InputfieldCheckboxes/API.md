# InputfieldCheckboxes

Multiple checkbox toggles. Extends [[InputfieldSelectMultiple]] (which extends
[[InputfieldSelect]]) — all option management methods (`addOption()`, `addOptions()`,
`setOptions()`, `addOptionsString()`, etc.) are inherited and documented there.
Returns an array value.

```php
$f = $modules->get('InputfieldCheckboxes');
$f->name = 'colors';
$f->label = 'Favorite colors';
$f->addOptions(['red' => 'Red', 'green' => 'Green', 'blue' => 'Blue']);
$f->val(['red', 'blue']); // pre-check red and blue
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, showIf,
rendering, processing, etc.).

## Properties

| Property        | Type          | Default | Description                                                                    |
|-----------------|---------------|---------|--------------------------------------------------------------------------------|
| `optionColumns` | `int`         | `0`     | Layout columns: `0`=stacked, `1`=inline, `2`–`10`=N columns (see Layout below) |
| `optionWidth`   | `string`      | `''`    | Fixed CSS width per option, e.g. `'150px'` or `'10em'` (3.0.184+)              |
| `table`         | `bool`        | `false` | Render as a table instead of a list                                            |
| `thead`         | `string`      | `''`    | Pipe-separated column headings for table mode, e.g. `'Name\|Description'`      |

## Layout

By default, checkboxes render as a stacked vertical list. The layout can be changed
with `optionColumns` or `optionWidth`.

### optionColumns

```php
$f->optionColumns = 0; // default: stacked vertical list
$f->optionColumns = 1; // inline: options flow side-by-side
$f->optionColumns = 3; // 3 equal-width columns (values 2–10 supported)
```

Values of `1` or greater than `10` both produce an inline/floated layout. Values
`2`–`10` produce that many percentage-width columns.

### optionWidth

An alternative to `optionColumns` that gives each option a fixed CSS width, producing
responsive columns that wrap at narrow viewports.

```php
$f->optionWidth = '200px'; // each option is 200px wide
$f->optionWidth = '12em';  // each option is 12em wide
$f->optionWidth = '1';     // auto-calculate width based on longest option label
```

When both `optionColumns` and `optionWidth` are set, `optionWidth` takes precedence.

## Table mode

When `table` is `true`, checkboxes render in a `<table>` using [[MarkupAdminDataTable]] instead of
a list. This is useful when options have multiple related columns.

```php
$f->table = true;
$f->thead = 'Product|SKU|Price';
$f->addOption('a', 'Widget|WDG-001|$9.99');
$f->addOption('b', 'Gadget|GDG-002|$24.99');
```

The `|` character in option labels becomes a column separator in the table. The
`thead` string defines the column headers using the same `|` separator.

## Notes

- The `size` attribute from `InputfieldSelectMultiple` is not used and is set to `null`.
- Optgroups are not supported (unlike `InputfieldSelect`).
- After `processInput()`, `$f->val()` returns an array of checked option values.
- **Source file:** `wire/modules/Inputfield/InputfieldCheckboxes/InputfieldCheckboxes.module`
