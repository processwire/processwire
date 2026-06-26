# InputfieldCheckbox

Single checkbox input. Renders an `<input type="checkbox">` element. Unlike most
Inputfields, the `value` attribute controls what is *submitted* when checked — not
whether the checkbox is checked. Use the `checked` attribute or the `checked()`
method to control checked state.

```php
$f = $modules->get('InputfieldCheckbox');
$f->name = 'agree';
$f->label = 'Terms and conditions';
$f->label2 = 'I agree to the terms and conditions';
$f->attr('checked', 'checked'); // make it checked by default
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, showIf,
rendering, processing, etc.).

## Properties

| Property         | Type           | Default | Description                                                                |
|------------------|----------------|---------|----------------------------------------------------------------------------|
| `checkedValue`   | `string\|int`  | `1`     | Value submitted (and stored) when checkbox is checked                      |
| `uncheckedValue` | `string\|int`  | `''`    | Value stored when checkbox is not checked                                  |
| `autocheck`      | `int`          | `0`     | When `1`, setting a non-empty value also sets the checkbox as checked      |
| `label2`         | `string`       | `''`    | Label displayed next to the checkbox (API usage)                           |
| `checkboxLabel`  | `string`       | `''`    | Label displayed next to the checkbox (admin config usage, same as label2)  |
| `checkboxOnly`   | `bool`         | `false` | Render checkbox without any label text beside it (3.0.144+)                |
| `labelAttrs`     | `array`        | `[]`    | Optional HTML attributes for the `<label>` element (3.0.141+)              |

## Constants

| Constant                | Value  | Description                       |
|-------------------------|--------|-----------------------------------|
| `checkedValueDefault`   | `1`    | Default value when checked        |
| `uncheckedValueDefault` | `''`   | Default value when not checked    |

## Checked state

The `checked` attribute and `checked()` method control whether the checkbox is
checked. They are independent of the `value` attribute.

### checked($checked = null)

Get or set the checked state. Returns `bool`.

```php
// get checked state
$isChecked = $f->checked(); // true or false

// set checked (two equivalent ways)
$f->checked(true);
$f->attr('checked', 'checked');

// unset checked (two equivalent ways)
$f->checked(false);
$f->removeAttr('checked');
```

### isEmpty()

Returns `true` when the checkbox is not checked.

```php
if($f->isEmpty()) {
    // checkbox is unchecked
}
```

## Value behavior

### Standard behavior (default)

This mirrors how HTML checkboxes work. The `value` attribute holds what gets
submitted if the checkbox is checked. Setting `value` does not affect checked state.

```php
$f = $modules->get('InputfieldCheckbox');
$f->name = 'notify';
$f->label = 'Email notifications';

// set what value is stored when checked (default is 1)
$f->attr('value', 'yes');

// separately control whether it starts checked
$f->attr('checked', 'checked');
```

After `processInput()`, `$f->val()` returns `checkedValue` (`'yes'`) if checked,
or `uncheckedValue` (`''`) if not.

### autocheck mode

When `autocheck` is `1`, setting a non-empty value also makes the checkbox checked.
This is useful for standalone forms where you want to pre-populate a checked state
by setting a value directly.

```php
$f->autocheck = 1;
$f->val('yes'); // sets checkedValue to 'yes' AND makes it checked
```

Without `autocheck`, that same `val('yes')` call would set the checkedValue but
leave the checkbox unchecked.

### checkedValue and uncheckedValue

These control what gets stored after the form is processed.

```php
$f->checkedValue = 'active';
$f->uncheckedValue = 'inactive';

// after processInput():
// $f->val() === 'active'  when checked
// $f->val() === 'inactive' when not checked
```

When `checkedValue` is set to anything other than `1`, it is also used as the
label displayed next to the checkbox (if no `label2` or `checkboxLabel` is set).

```php
$f->checkedValue = 'Subscribe to newsletter';
// renders: [✓] Subscribe to newsletter
```

## Label options

The label displayed next to the checkbox is resolved in this order:

1. `checkboxLabel` (from admin config, supports multi-language)
2. `label2` (set via API)
3. `checkedValue` (when set to something other than `1`)
4. `label` (the main field label, as fallback)

Set `checkboxOnly = true` to suppress the label entirely and render only the
checkbox input.

```php
// separate field label from checkbox label
$f->label = 'Email notifications';       // shown above the field
$f->label2 = 'Send me email updates';    // shown next to the checkbox

// or show no label next to checkbox
$f->checkboxOnly = true;
```

## Notes

- After `processInput()`, `$f->val()` returns `checkedValue` or `uncheckedValue` — not `true`/`false`.
- The `skipLabel` behavior is set automatically: `skipLabelFor` when a checkbox label or description is present, `skipLabelHeader` otherwise.
- `label2` and `checkboxLabel` do the same thing. Use `label2` when configuring via API; `checkboxLabel` is the admin-configurable version.
- `checkedValue` and `uncheckedValue` are only available in the admin config when `hasFieldtype` is false (standalone forms). When used with a Fieldtype (e.g. FieldtypeCheckbox), those values are managed by the Fieldtype.
- **Source file:** `wire/modules/Inputfield/InputfieldCheckbox/InputfieldCheckbox.module`
