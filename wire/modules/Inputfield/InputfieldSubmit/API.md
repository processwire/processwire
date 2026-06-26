# InputfieldSubmit

Form submit button. Renders a `<button type="submit">` element. Extends [[Inputfield]].

```php
$f = $modules->get('InputfieldSubmit');
$f->name = 'save';
$f->value = 'Save Changes';
$f->showInHeader();
$form->add($f);
```

After `processInput()`, check whether the button was clicked via `submitValue`:

```php
if($f->submitValue !== false) {
    // this button was clicked
}
```

See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, rendering,
processing, etc.).

## Properties

| Property      | Type           | Default            | Description                                                                    |
|---------------|----------------|--------------------|--------------------------------------------------------------------------------|
| `value`       | `string`       | `'Submit'`         | Button label text and the value submitted when clicked                         |
| `text`        | `string`       | `''`               | Label text (overrides `value` for display; `value` is still submitted)         |
| `html`        | `string`       | `''`               | Inner HTML (overrides `text` and `value` for display; 3.0.134+)                |
| `icon`        | `string`       | `''`               | Icon name (e.g. `'save'`) prepended to the button label                        |
| `textClass`   | `string`       | `'ui-button-text'` | CSS class for the inner `<span>` wrapping the label                            |
| `header`      | `bool`         | `false`            | Also render the button in the page header                                      |
| `secondary`   | `bool`         | `false`            | Render as a secondary (slightly faded) button                                  |
| `small`       | `bool`         | `false`            | Render as a smaller button                                                     |
| `submitValue`       | `string\|false`| `false`              | Read-only: submitted value if clicked, `false` if not (after `processInput()`)   |
| `dropdownInputName` | `string`       | `'_action_value'`    | Name of the hidden `<input>` that receives the selected dropdown item value      |
| `dropdownSubmit`    | `bool`         | `true`               | When `true`, the selected dropdown value also becomes the submit button's value  |
| `dropdownRequired`  | `bool`         | `false`              | When `true`, clicking the button without a dropdown selection opens the dropdown instead of submitting (3.0.180+) |

## Fluent methods

These methods return `$this` for chaining and are equivalent to setting the same-named
properties directly:

```php
$f->showInHeader();    // also render button in the page header
$f->setSecondary();    // make button secondary (slightly faded)
$f->setSmall();        // make button smaller
```

All three accept an optional `bool` argument (defaults to `true`); pass `false` to undo.

## Dropdown actions

A dropdown can be appended to a submit button with additional actions. Each item is
either a link or a value that is submitted with the form.

```php
// submits _action_value='save_and_close' when selected
$f->addActionValue('save_and_close', 'Save and Close', 'times-circle');

// navigates directly to the URL when selected
$f->addActionLink('/admin/page/list/', 'Cancel', 'ban');
```

`addActionValue($value, $label, $icon = '')`
: Adds a dropdown item that populates a hidden input before submitting. Icon is a
  Font Awesome name without the `fa-` prefix.

`addActionLink($url, $label, $icon = '')`
: Adds a dropdown item that navigates to `$url` when selected.

After `processInput()`, `$f->submitValue` holds the value that was submitted — either
the main button's `value` or the selected dropdown item's value.

### Dropdown properties

By default (`dropdownSubmit = true`), the selected dropdown item's value becomes the
submit button's submitted value (i.e. it appears in `$_POST[$f->name]`) **and** is
also copied to `$_POST[$f->dropdownInputName]` (default `'_action_value'`). Set
`dropdownSubmit = false` to only populate the hidden input, leaving the button's own
submitted value unaffected:

```php
$f->dropdownSubmit = false;
// Now: $_POST['submit'] == 'Save Changes' (the button value, always)
//      $_POST['_action_value'] == 'save_and_close' (the dropdown choice)
```

Change `dropdownInputName` if `'_action_value'` conflicts with another field:

```php
$f->dropdownInputName = 'my_action';
// Selected item value will be in $_POST['my_action']
```

Require a dropdown selection before the form can be submitted:

```php
$f->dropdownRequired = true;
// Clicking the button without selecting a dropdown item opens the dropdown instead
```

## Multiple submit buttons

When a form has more than one submit button, give each a unique `name`:

```php
$save = $modules->get('InputfieldSubmit');
$save->name = 'btn_save';
$save->value = 'Save';

$delete = $modules->get('InputfieldSubmit');
$delete->name = 'btn_delete';
$delete->value = 'Delete';
$delete->setSecondary();
```

After `processInput()`, check `submitValue` on each to see which was clicked.

## Notes

- `submitValue` is `false` before `processInput()`. After, it is a string (possibly
  empty) when the button was clicked, and `false` when it was not.
- The `name` attribute defaults to `'submit'`. Always change it when using multiple
  submit buttons in one form.
- **Source file:** `wire/modules/Inputfield/InputfieldSubmit/InputfieldSubmit.module`
