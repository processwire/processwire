# InputfieldHidden

`InputfieldHidden` renders an `<input type="hidden">` element for carrying values
through a form without displaying them to the user. Use it for submitted state,
tokens, IDs, or computed values that should be present in form data but not edited
directly.

```php
$f = $modules->get('InputfieldHidden');
$f->name = 'referrer';
$f->value = $input->get('ref');
$form->add($f);
```

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `renderValueAsInput` | `bool` | When `true`, `renderValue()` renders the hidden input rather than display text. Default is `false`. |
| `initValue` | `string` | Fallback value used when the `value` attribute is empty. Default is `''`. |

## Methods

### render()

Returns the hidden input markup built from the field attributes.

```php
$f = $modules->get('InputfieldHidden');
$f->name = 'token';
$f->value = 'abc123';
echo $f->render(); // <input type="hidden" name="token" value="abc123" />
```

### renderValue()

Returns display-mode markup. By default this uses the parent `Inputfield`
`renderValue()` behavior. When `renderValueAsInput` is `true`, it renders the
hidden input instead.

```php
$f = $modules->get('InputfieldHidden');
$f->name = 'token';
$f->value = 'abc123';
$f->renderValueAsInput = true;
echo $f->renderValue(); // hidden input markup
```

### getAttributes()

Returns the input attributes. If the `value` attribute is empty and `initValue`
is non-empty, `initValue` is used as the rendered value.

```php
$f = $modules->get('InputfieldHidden');
$f->name = 'mode';
$f->initValue = 'edit';
echo $f->render(); // value="edit"
```

## Notes

- Access this inputfield with `$modules->get('InputfieldHidden')`.
- `collapsed` and `columnWidth` are intentionally removed from its configuration
  screen because a hidden input has no visible wrapper layout.
- For shared Inputfield behavior such as attributes, `name`, `value`, `showIf`,
  processing, and validation, see the main `Inputfield` API documentation.

**Source file:** `wire/modules/Inputfield/InputfieldHidden/InputfieldHidden.module`
