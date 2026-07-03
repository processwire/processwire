# InputfieldIcon

Font Awesome icon picker. Renders a searchable `<select>` where users choose an
icon from the active admin icon set. The stored value is a Font Awesome class
name such as `fa-star`.

```php
$f = $modules->get('InputfieldIcon');
$f->name = 'feature_icon';
$f->label = 'Feature icon';
$f->value = 'fa-bolt';
$form->add($f);
```

For shared Inputfield behavior, including labels, attributes, rendering,
processing, and validation, see [[Inputfield]].

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `prefixValue` | `bool` | `true` | Whether `value` / `val()` return values include the `fa-` prefix. |

The inherited `icon` property controls the icon shown in the inputfield header.
When a selected value is rendered, `InputfieldIcon` updates this header icon to
match the selected icon.

## Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `InputfieldIcon::prefix` | `'fa-'` | Font Awesome class prefix. |

## Value Normalization

Set values with or without the `fa-` prefix:

```php
$f->value = 'star';
echo $f->value; // fa-star

$f->value = 'fa-bolt';
echo $f->value; // fa-bolt
```

Invalid icon names are cleared:

```php
$f->value = 'not-an-icon';
echo $f->value; // ''
```

Set `prefixValue` to `false` when you want reads without the prefix:

```php
$f->value = 'fa-user';
$f->prefixValue = false;

echo $f->value; // user
echo $f->val(); // user
```

Internally, assigned values are accepted only when they exist in the active icon
list.

## Rendering

### render()

Render the icon picker:

```php
echo $f->render();
```

The rendered output includes:

- A `<select>` with all available icon options.
- A search input for filtering icons in the browser.
- A hidden `.InputfieldIconAll` container populated by JavaScript with clickable
  icon tiles.

When the field is not required, an empty `<option>` is included so the selected
icon can be cleared. Required fields omit that empty option.

## Font Awesome Version

The icon list is chosen automatically from `$config->adminIcons['version']`:

- Font Awesome 6 or newer uses `icons6.inc`.
- Older admin icon sets use `icons.inc`.

This is an internal detail detected during `init()` so the picker matches the
active admin theme.

## Processing Input

`InputfieldIcon` uses inherited [[Inputfield]] input processing. Submitted values
are simple strings and still pass through the same value normalization as API
assignments.

```php
$form->processInput($input->post);
$icon = $f->val(); // e.g. fa-star
```

## Notes

- Get an instance with `$modules->get('InputfieldIcon')`.
- The JavaScript behavior lives in `InputfieldIcon.js`.
- Styling lives in `InputfieldIcon.css`.
- Icon list files are `icons.inc` and `icons6.inc`.
- Unknown/custom icon class names are not preserved unless they exist in the
  bundled list for the active icon set.
- Source file: `wire/modules/Inputfield/InputfieldIcon/InputfieldIcon.module`.

