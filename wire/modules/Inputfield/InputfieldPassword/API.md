# InputfieldPassword

`InputfieldPassword` renders password inputs with confirmation, optional
old-password verification, live strength feedback, and server-side password
validation. It extends `InputfieldText`, so it inherits standard text-input and
Inputfield behavior, but hides or overrides several text settings because
password handling is more specialized.

```php
$f = $modules->get('InputfieldPassword');
$f->name = 'pass';
$f->label = 'Set Password';
$f->attr('minlength', 8);
$f->requirements = [
	InputfieldPassword::requireUpperLetter,
	InputfieldPassword::requireLowerLetter,
	InputfieldPassword::requireDigit,
	InputfieldPassword::requireOther,
];
$form->add($f);
```

For shared Inputfield behavior such as attributes, labels, collapsed states,
`showIf`, rendering, and processing, see the main `Inputfield` API documentation.

## Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `requirements` | `array` | `[ 'letter', 'digit' ]` | Active character-class requirements. |
| `requirementsLabels` | `array` | labels | Human-readable labels for requirements. |
| `complexifyBanMode` | `string` | `'loose'` | Client-side common-password ban mode: `'loose'` or `'strict'`. |
| `complexifyFactor` | `float|string` | `0.7` | Client-side strength factor. Lower numbers allow weaker passwords. |
| `requireOld` | `int` | `0` | Whether the current password is required before changes. |
| `showPass` | `bool` | `false` | Whether existing password values may be rendered/repopulated. |
| `unmask` | `bool` | `false` | Whether to render a show/hide password toggle. |
| `defaultLabel` | `string` | `'Set Password'` | Default label assigned during `init()`. |
| `oldPassLabel` | `string` | `'Current password'` | Label/placeholder for the old-password input. |
| `newPassLabel` | `string` | `'New password'` | Label/placeholder for the new-password input. |
| `confirmLabel` | `string` | `'Confirm'` | Label/placeholder for the confirm-password input. |

Constructor defaults include `type='password'`, `size=30`, `maxlength=256`, and
`minlength=6`.

## Constants

Password character-class requirements:

| Constant | Value | Meaning |
| --- | --- | --- |
| `requireLetter` | `'letter'` | At least one Unicode letter. |
| `requireLowerLetter` | `'lower'` | At least one lowercase Unicode letter. |
| `requireUpperLetter` | `'upper'` | At least one uppercase Unicode letter. |
| `requireDigit` | `'digit'` | At least one Unicode digit. |
| `requireOther` | `'other'` | At least one punctuation or symbol character. |
| `requireNone` | `'none'` | Disable character-class requirements. Minimum length and invalid whitespace are still checked. |

Old-password requirement constants:

| Constant | Value | Meaning |
| --- | --- | --- |
| `requireOldAuto` | `0` | Auto/default. `processInput()` does not enforce old password by itself. |
| `requireOldYes` | `1` | Require old password when a logged-in user changes the value. |
| `requireOldNo` | `-1` | Do not require old password. |

## Rendering

`render()` outputs the new-password input, confirmation input, live strength
feedback markup, and optionally an old-password input or show/hide toggle.

```php
$f = $modules->get('InputfieldPassword');
$f->name = 'pass';
$f->unmask = true;
echo $f->render();
```

When `showPass` is `false`, the new-password input value is cleared during render
so existing passwords are not exposed in markup. The value is restored to the
Inputfield object after rendering.

When `requireOld > 0` and the current user is logged in, `render()` also outputs
an old-password input named `"_old_$name"`.

## renderValue()

`renderValue()` returns masked output by default:

```php
$f->value = 'secret';
echo $f->renderValue(); // <p>******</p>
```

If `showPass` is `true`, the actual value is entity-encoded and rendered instead.

## processInput(WireInputData $input)

Processes submitted password and confirmation values. The confirmation input name
is the main name prefixed with `_`.

```php
$f = $modules->get('InputfieldPassword');
$f->name = 'pass';
$input = new WireInputData([
	'pass' => 'GoodPass123!',
	'_pass' => 'GoodPass123!',
]);
$f->processInput($input);
```

Processing checks:

- required password presence
- old-password match when `requireOld > 0` and a user is logged in
- new/confirm password match
- `isValidPassword()` requirements

On any error, the value is cleared and change tracking is reset.

## isValidPassword($value)

Validates a candidate password against invalid whitespace, minimum length, and
configured character-class requirements. Returns `true` when valid and records
errors when invalid.

```php
if(!$f->isValidPassword('abc')) {
	foreach($f->getErrors() as $error) {
		echo $sanitizer->entities($error);
	}
}
```

`requireNone` skips only the character-class checks. Minimum length and invalid
tab/newline whitespace are still enforced.

## setPage(Page $page)

Associates the input with a page being edited. When the page is unpublished, the
password field becomes required. Later attempts to set `collapsed` are forced to
`Inputfield::collapsedNo` so an initial password field stays visible.

```php
$f->setPage($userPage);
```

## Configuration

`getConfigInputfields()` removes several inherited text-input settings from the
config UI and exposes password-specific settings:

- `requirements`
- `complexifyBanMode`
- `complexifyFactor`
- `minlength`
- `showPass` for standalone usage
- `requireOld`
- `unmask`

## Notes

- Access this inputfield with `$modules->get('InputfieldPassword')`.
- The class validates password input but does not hash or persist passwords.
  Persistence is handled by password fieldtypes and user password APIs.
- Client-side strength feedback depends on bundled JavaScript, but server-side
  validation still runs without it.
- Browser autofill is discouraged by rendering `autocomplete="new-password"` in
  admin/logged-in contexts.
- The module is permanent and cannot be uninstalled.

**Source file:** `wire/modules/Inputfield/InputfieldPassword/InputfieldPassword.module`
