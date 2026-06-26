# InputfieldToggle

On/off toggle with a configurable label pair (Yes/No, On/Off, True/False, etc.) and
an optional third "other" state. Renders as toggle buttons by default; can delegate
to [[InputfieldRadios]] or [[InputfieldSelect]].

```php
$f = $modules->get('InputfieldToggle');
$f->name = 'active';
$f->label = 'Active?';
$f->val(InputfieldToggle::valueYes);
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API.

## Value model

The value is always one of four constants:

| Constant                         | Integer | Meaning           |
|----------------------------------|---------|-------------------|
| `InputfieldToggle::valueYes`     | `1`     | Yes / On / True   |
| `InputfieldToggle::valueNo`      | `0`     | No / Off / False  |
| `InputfieldToggle::valueOther`   | `2`     | Other (3rd state) |
| `InputfieldToggle::valueUnknown` | `''`    | No selection      |

`isEmpty()` returns `true` only when the value is `valueUnknown` (empty string). The
integer `0` (`valueNo`) is a present, non-empty value.

`sanitizeValue()` accepts flexible input and normalises it to one of the four
constants above: booleans, integers `0`/`1`/`2`, strings `'yes'`/`'no'`/`'on'`/
`'off'`/`'true'`/`'false'`, or any label matching the current `labelType`.

## Properties

| Property          | Type      | Default   | Description                                                                                |
|-------------------|-----------|-----------|--------------------------------------------------------------------------------------------|
| `labelType`       | `int`     | `0`       | Which label pair to display (see Label types below)                                        |
| `yesLabel`        | `string`  | `'✓'`     | Custom yes/on label (used when `labelType = labelTypeCustom`)                              |
| `noLabel`         | `string`  | `'✗'`     | Custom no/off label (used when `labelType = labelTypeCustom`)                              |
| `otherLabel`      | `string`  | `'?'`     | Label for the optional third option                                                        |
| `useOther`        | `bool`    | `false`   | Show a third "other" option                                                                |
| `useReverse`      | `bool`    | `false`   | Reverse the order (No before Yes)                                                          |
| `useVertical`     | `bool`    | `false`   | Vertical layout (applies only when `inputfieldClass = 'InputfieldRadios'`)                 |
| `useDeselect`     | `bool`    | `false`   | Allow clicking the selected option to deselect it (requires `defaultOption = 'none'`)      |
| `defaultOption`   | `string`  | `'none'`  | Pre-selected option: `'yes'`, `'no'`, `'other'`, or `'none'`                               |
| `inputfieldClass` | `string`  | `''`      | Delegate rendering to `'InputfieldRadios'` or `'InputfieldSelect'`; blank = toggle buttons |

## Label types

Five built-in label pairs are available via constants:

```php
$f->labelType = InputfieldToggle::labelTypeYes;     // Yes / No      (default)
$f->labelType = InputfieldToggle::labelTypeTrue;    // True / False
$f->labelType = InputfieldToggle::labelTypeOn;      // On / Off
$f->labelType = InputfieldToggle::labelTypeEnabled; // Enabled / Disabled
$f->labelType = InputfieldToggle::labelTypeCustom;  // use yesLabel / noLabel
```

For custom labels:

```php
$f->labelType = InputfieldToggle::labelTypeCustom;
$f->yesLabel = 'Yes please';
$f->noLabel = 'No thanks';
```

Icon names from the admin icon set may be embedded in custom labels:

```php
$f->yesLabel = 'icon-check Yes';
$f->noLabel = 'icon-times No';
```

## Third "other" option

```php
$f->useOther = true;
$f->otherLabel = 'Not sure'; // default: '?'
```

After processing, check for it with:

```php
if($f->val() === InputfieldToggle::valueOther) { ... }
```

## Rendering

By default, toggle buttons are rendered. To use radios or a select instead:

```php
$f->inputfieldClass = 'InputfieldRadios'; // render as radio buttons
$f->inputfieldClass = 'InputfieldSelect'; // render as a <select>
$f->useVertical = true; // vertical layout (radios only)
```

## Custom options

`addOption()` and `setOptions()` replace the built-in Yes/No/Other model entirely
with an arbitrary set of options. Values must be integers in the range -128–127.
**Not available when used with `FieldtypeToggle`.**

```php
$f->addOption(1, 'Approved');
$f->addOption(2, 'Pending');
$f->addOption(0, 'Rejected');

// or all at once:
$f->setOptions([1 => 'Approved', 2 => 'Pending', 0 => 'Rejected']);
```

## Helper methods

`getValueLabel($value = null, $labelType = null, $language = null)`
: Returns the display label for a given value (or the current value if omitted).

`getLabels($labelType = null, $language = null)`
: Returns an array with keys `'yes'`, `'no'`, `'other'`, `'unknown'` for the given
  label type and language.

`getOptions()`
: Returns `[value => label]` array of currently configured options.

## Multi-language

`yesLabel`, `noLabel`, and `otherLabel` support per-language overrides when
ProcessWire's LanguageSupport module is active. Language variants are set as
`yesLabel{languageId}`, `noLabel{languageId}`, etc.

## Notes

- `isEmpty()` returns `true` only for `valueUnknown` (`''`). `valueNo` (`0`) is not empty.
- **Source file:** `wire/modules/Inputfield/InputfieldToggle/InputfieldToggle.module`
