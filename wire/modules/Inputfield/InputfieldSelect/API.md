# InputfieldSelect

Single selection `<select>` dropdown. Base class for all selection-based Inputfields,
including [[InputfieldSelectMultiple]], [[InputfieldCheckboxes]], [[InputfieldRadios]],
and [[InputfieldAsmSelect]]. All option management methods documented here are available
in those subclasses as well.

```php
$f = $modules->get('InputfieldSelect');
$f->name = 'color';
$f->label = 'Favorite color';
$f->addOption('red', 'Red');
$f->addOption('green', 'Green');
$f->addOption('blue', 'Blue');
$f->val('green'); // pre-select green
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, showIf,
rendering, processing, etc.).

## Properties

| Property         | Type     | Default | Description                                                                           |
|------------------|----------|---------|---------------------------------------------------------------------------------------|
| `defaultValue`   | `string` | `''`    | Value to select when field is required and empty, see [Default value](#default-value) |
| `valueAddOption` | `bool`   | `false` | When true, values set via API that are not existing options are added as options      |
| `options`        | `array`  | `[]`    | Get or set all options as `[value => label]`. Also accepts options string.            |

## Adding options

### addOption($value, $label = null, $attributes = null)

Add a single option. If `$label` is omitted, the value is used as the label. Returns `$this`.

```php
$f->addOption('red', 'Red');
$f->addOption('none'); // value and label are both 'none'
```

To add a **disabled** option, pass `['disabled' => 'disabled']` as attributes:

```php
$f->addOption('tbd', 'To be decided', ['disabled' => 'disabled']);
```

To pre-select an option, use `val()` after adding options:

```php
$f->addOption('green', 'Green');
$f->val('green'); // pre-select green
```

To add an **optgroup**, pass the group label as `$value` and an array of `[value => label]` as `$label`:

```php
$f->addOption('Warm colors', ['red' => 'Red', 'orange' => 'Orange', 'yellow' => 'Yellow']);
$f->addOption('Cool colors', ['blue' => 'Blue', 'green' => 'Green']);
```

To add a **separator** (horizontal rule), pass a string of three or more dashes (3.0.236+):

```php
$f->addOption('---');
```

### addOptions(array $options, $assoc = true)

Add multiple options at once. By default, treats keys as values and values as labels.

```php
$f->addOptions([
    'red'   => 'Red',
    'green' => 'Green',
    'blue'  => 'Blue',
]);
```

When `$assoc` is `false`, the array values are used for both value and label (keys ignored):

```php
$f->addOptions(['Red', 'Green', 'Blue'], false);
// adds options where value and label are both 'Red', 'Green', 'Blue'
```

### setOptions(array $options, $assoc = true)

Replace all existing options with the given array. Same signature as `addOptions()`.

```php
$f->setOptions(['a' => 'Option A', 'b' => 'Option B']);
```

### addOptionsString($value)

Add options from a multi-line string. One option per line. Useful when options come
from a textarea config field or a text block.

```php
$f->addOptionsString("
Red
Green
Blue
");
```
With separate values and labels, a blank option, and 'Green' pre-selected:
```php
$f->addOptionsString("
=
r=Red
+g=Green
b=Blue
");
```

**Options string format:**

| Syntax                  | Effect                                        |
|-------------------------|-----------------------------------------------|
| `Label`                 | Value and label are both `Label`              |
| `value=Label`           | Separate value and label                      |
| `=`                     | Blank option (empty value, empty label)       |
| `+Label`                | Pre-selected option                           |
| `++Label`               | Literal `+Label` (escaped leading plus)       |
| `disabled:Label`        | Disabled option                               |
| `---`                   | Separator / horizontal rule (3.0.236+)        |
| `==` in value or label  | Literal `=` (escaped equals sign)             |

**Optgroups:** indent options with 3 or more spaces after a non-indented line:

```
Warm colors
   r=Red
   o=Orange
Cool colors
   b=Blue
   g=Green
```

### removeOption($value)

Remove an option by its value. Returns `$this`.

```php
$f->removeOption('red');
```

### replaceOption($oldValue, $newValue, $newLabel = null, $newAttributes = null)

Replace an existing option, preserving its position in the list. Returns `true` if
found and replaced, `false` if not found.

```php
$f->replaceOption('tbd', 'pending', 'Pending');
```

### insertOptionsBefore(array $options, $existingValue = null)

Insert new options before an existing option, or prepend to the beginning if
`$existingValue` is omitted. Returns `$this`.

```php
$f->insertOptionsBefore(['none' => 'None'], 'red');
// inserts 'none' before 'red'
```

### insertOptionsAfter(array $options, $existingValue = null)

Insert new options after an existing option, or append to the end if `$existingValue`
is omitted. Returns `$this`.

```php
$f->insertOptionsAfter(['other' => 'Other']);
// appends 'other' to the end
```

### getOptions()

Returns all options as an associative array `[value => label]`.

```php
$options = $f->getOptions();
```

## Querying options

### isOption($value)

Returns `true` if the given value is a valid option (including within optgroups).
Separator values are never considered valid options.

```php
if($f->isOption('red')) {
    // 'red' is available
}
```

### isOptionSelected($value)

Returns `true` if the given option value is currently selected.

```php
if($f->isOptionSelected('green')) { ... }
```

### isOptionDisabled($value)

Returns `true` if the given option value has the `disabled` attribute set.

```php
if($f->isOptionDisabled('tbd')) { ... }
```

## Option labels

### optionLabel($key, $label = null)

Get or set the label for a given option value (default language).

```php
// get label
$label = $f->optionLabel('red'); // 'Red'

// set label
$f->optionLabel('red', 'Dark red');
```

Returns the label string, or `false` if the option is not found.

### addOptionLabel($value, $label, $language = null)

Add a translated label for an option value in a specific language (3.0.176+).
If specified, `$language` can be a Language object, name or ID. 

```php
$f->addOptionLabel('red', 'Rojo', 'es');
```

### optionLanguageLabel($language, $key = null, $label = null)

Low-level get/set for language-specific option labels. Accepts Language object, ID,
or name string.

```php
// set one label
$f->optionLanguageLabel('es', 'red', 'Rojo');

// set multiple labels for a language at once
$f->optionLanguageLabel('es', ['red' => 'Rojo', 'green' => 'Verde']);

// get all labels for a language
$labels = $f->optionLanguageLabel('es');

// remove all labels for a language
$f->optionLanguageLabel('es', false);
```

## Option attributes

### optionAttributes($key = null, $attributes = null, $append = false)

Combined get/set for option attributes. Without arguments, returns all option
attributes. With `$key` only, returns attributes for that option. With `$attributes`
array, sets (or appends) attributes for that option.

```php
// get all option attributes
$all = $f->optionAttributes();

// get attributes for one option
$attrs = $f->optionAttributes('red'); // e.g. ['disabled' => 'disabled']

// set attributes for one option
$f->optionAttributes('red', ['class' => 'highlight']);

// append attributes (merge, don't replace)
$f->optionAttributes('red', ['data-hex' => '#ff0000'], true);
```

### setOptionAttributes($key, array $attrs)

Set (replace) the entire attributes array for an option. Pass an associative array
as `$key` to replace ALL option attributes at once.

### addOptionAttributes($key, array $attrs)

Merge attributes into an option's existing attributes without replacing them.

### getOptionAttributes($key = null)

Get attributes for a specific option, or all option attributes if `$key` is omitted.

## Value handling

### Default value

When `defaultValue` is set and the field is `required` and currently empty, the
default value is applied automatically during `render()` and after `processInput()`.

```php
$f->required = true;
$f->defaultValue = 'green';
// if no value is selected, 'green' is used
```

### valueAddOption

When `valueAddOption` is `true`, setting a value via the API that is not already
a defined option automatically adds it as an option. Applies only to values set
from code — not to user-submitted input (security).

```php
$f->valueAddOption = true;
$f->val('purple'); // 'purple' is added as an option and selected
```

### isEmpty()

Returns `true` when no option is selected. The value `'0'` is considered empty only
when `'0'` is not a defined option.

```php
if($f->isEmpty()) {
    // nothing selected
}
```

## Security

`processInput()` validates all submitted values against the defined options list and
silently removes any that are not present. This prevents injection of arbitrary
values through manipulated form submissions.

## Notes

- Subclasses that support multiple selections implement the `InputfieldHasArrayValue` interface. `isOptionSelected()` and `processInput()` detect this automatically.
- Optgroups are supported only in `InputfieldSelect` and `InputfieldSelectMultiple` — not in `InputfieldCheckboxes` or `InputfieldRadios`.
- A blank/empty first option is added automatically for single-select unless the first defined option already has an empty key, or the field is `required` and already has a value set.
- **Source file:** `wire/modules/Inputfield/InputfieldSelect.module`

---

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
