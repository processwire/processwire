# InputfieldName

Text input that stores values sanitized as ProcessWire names. Extends
[[InputfieldText]], so normal single-line text input behavior, rendering,
validation, labels, collapsed state, `showIf`, and form processing are
inherited.

The difference from [[InputfieldText]] is that incoming values are sanitized
with a configurable [[Sanitizer]] method before they are stored. By default the
method is `$sanitizer->name()`.

```php
$f = $modules->get('InputfieldName');
$f->name = 'field_name';
$f->label = 'Field name';
$form->add($f);
```

For framework-level Inputfield behavior, see [[Inputfield]]. For inherited
single-line text options such as `minlength`, `maxlength`, `placeholder`,
`pattern`, `stripTags`, `noTrim`, and `showCount`, see [[InputfieldText]].

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `sanitizeMethod` | `string` | `'name'` | Name of the [[Sanitizer]] method used to sanitize incoming values. |

## Defaults

`init()` installs these defaults:

| Setting | Default |
|---------|---------|
| `type` attribute | `'text'` |
| `maxlength` attribute | `Pages::nameMaxLength` (`128`) |
| `size` attribute | `0` |
| `name` attribute | `'name'` |
| `required` | `true` |
| `label` | `'Name'` |
| `description` | Explains that spaces and unsupported characters become underscores |
| `sanitizeMethod` | `'name'`, set in the constructor |

## Sanitization

Any value assigned through `val()`, `attr('value', ...)`, or `processInput()`
is passed through `$sanitizer->{$sanitizeMethod}($value)` before storage.

Default `$sanitizer->name()` behavior:

- Preserves case.
- Allows letters, numbers, underscores, hyphens, and periods.
- Converts spaces and unsupported characters to underscores.
- Does not lowercase values.

```php
$f = $modules->get('InputfieldName');

$f->val('Hello World! Foo');
echo $f->val(); // Hello_World__Foo

$f->val('My.Field-Name 99 (x!)');
echo $f->val(); // My.Field-Name_99__x__
```

The sanitized value is also truncated to the field's `maxlength` attribute when
`maxlength` is greater than zero. This enforces the rendered input length limit
on the server as well. The selected sanitizer method may still apply its own
limit first; for example, `$sanitizer->name()` defaults to a 128-character
maximum.

## Custom Sanitizer Method

Set `sanitizeMethod` to another callable method on [[Sanitizer]] when a related
name format is needed:

```php
$f = $modules->get('InputfieldName');
$f->sanitizeMethod = 'pageName';
$f->val('Hello World!');

echo $f->val(); // hello-world
```

For real page names, prefer [[InputfieldPageName]]. It extends this class and
adds page-name specific behavior such as URL preview, language support, and
configurable character replacements.

## Processing Input

Use it like any other Inputfield:

```php
$f = $modules->get('InputfieldName');
$f->name = 'my_name';
$form->add($f);

if($form->processInput($input->post)) {
	$name = $f->val(); // already sanitized
}
```

## Notes

- Get an instance with `$modules->get('InputfieldName')`.
- It is a permanent core module.
- Unsanitized values cannot be stored on this field; sanitization happens on
  assignment.
- For page names, use [[InputfieldPageName]] rather than changing only
  `sanitizeMethod`.
- This class defines no hookable (`___`-prefixed) methods; hookable behavior is
  inherited from [[Inputfield]] and [[InputfieldText]].
- Source file: `wire/modules/Inputfield/InputfieldName/InputfieldName.module`.
