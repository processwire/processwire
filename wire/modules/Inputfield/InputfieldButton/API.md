# InputfieldButton

Non-submitting button that can optionally render as a hyperlink. Extends
[[InputfieldSubmit]] — all submit-button properties (`value`, `html`, `text`, `icon`,
`header`, `secondary`, `small`, `showInHeader()`, `setSecondary()`, `setSmall()`,
dropdown actions, etc.) are inherited and documented there.

Renders `<button type="button">` (not a submit button). When `href` is set, the button
is wrapped in an `<a>` tag.

```php
$f = $modules->get('InputfieldButton');
$f->value = 'View Page';
$f->href = $page->url;
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API.

## Properties

| Property    | Type     | Default | Description                                                                |
|-------------|----------|---------|----------------------------------------------------------------------------|
| `href`      | `string` | `''`    | URL; wraps button in `<a href="...">` when set                             |
| `aclass`    | `string` | `''`    | Additional CSS class(es) for the `<a>` element                             |
| `target`    | `string` | `''`    | Link target attribute (e.g. `'_blank'`)                                    |
| `linkInner` | `bool`   | `false` | Place `<a>` inside `<button>` rather than wrapping it outside (3.0.184+)   |

## Linking

When `href` is set, the button is wrapped in an `<a>` tag by default:

```php
$f->href = '/admin/page/edit/?id=1234';
$f->aclass = 'pw-modal';   // open in a ProcessWire modal
$f->target = '_blank';     // open in new tab
```

`linkInner = true` places the `<a>` inside the `<button>` instead — useful in
non-admin contexts where the outside-wrap breaks CSS:

```php
$f->linkInner = true;
```

## Notes

- `InputfieldButton` does not submit the form; it is for navigation or JS-driven actions.
- Default `name` is `'button'` and default `value` is `'Button'`.
- **Source file:** `wire/modules/Inputfield/InputfieldButton/InputfieldButton.module`
