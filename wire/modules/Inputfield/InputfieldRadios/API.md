# InputfieldRadios

Radio button group for single-item selection. Extends [[InputfieldSelect]] — all
option management methods (`addOption()`, `addOptions()`, `setOptions()`,
`addOptionsString()`, etc.) are inherited and documented there.

```php
$f = $modules->get('InputfieldRadios');
$f->name = 'size';
$f->label = 'T-shirt size';
$f->addOptions(['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'XL']);
$f->val('m'); // pre-select Medium
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, showIf,
rendering, processing, etc.).

## Properties

| Property        | Type     | Default | Description                                                                     |
|-----------------|----------|---------|---------------------------------------------------------------------------------|
| `optionColumns` | `int`    | `0`     | Layout columns: `0`=stacked, `1`=inline, `2`–`10`=N columns (see Layout below)  |
| `optionWidth`   | `string` | `''`    | Fixed CSS width per option, e.g. `'150px'` or `'10em'` (3.0.184+)               |

## Layout

By default, radio buttons render as a stacked vertical list. The layout can be
changed with `optionColumns` or `optionWidth`. These work identically to the same
properties in [[InputfieldCheckboxes]].

```php
$f->optionColumns = 0; // default: stacked vertical list
$f->optionColumns = 1; // inline: options flow side-by-side
$f->optionColumns = 3; // 3 equal-width percentage columns (values 2–10 supported)

$f->optionWidth = '150px'; // fixed-width columns, wraps responsively
$f->optionWidth = '1';     // auto-calculate width from longest label
```

When both `optionColumns` and `optionWidth` are set, `optionWidth` takes precedence.

## Global config

Two rendering behaviors can be changed globally via `$config->InputfieldRadios`
(typically set in `config.php`):

```php
$config->InputfieldRadios = [
    'wbr' => true,         // insert <wbr> tags in labels for long-word breaking
    'noSelectLabels' => false, // allow text selection on radio labels (default: true = no selection)
];
```

## Notes

- Returns a single value (string), not an array. Use `$f->val()` to get or set.
- Optgroups are not supported.
- **Source file:** `wire/modules/Inputfield/InputfieldRadios/InputfieldRadios.module`
