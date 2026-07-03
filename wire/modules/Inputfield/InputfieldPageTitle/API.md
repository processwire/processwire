# InputfieldPageTitle

Single-line text input for page title values. It extends [[InputfieldText]] and
inherits text-input behavior such as `maxlength`, `placeholder`, `pattern`,
`required`, `showCount`, multi-language support, rendering, and processing.

Its main purpose is the bundled JavaScript that helps auto-populate a page-name
input from the title while creating pages.

```php
$f = $modules->get('InputfieldPageTitle');
$f->name = 'title';
$f->label = 'Title';
$form->add($f);
```

For the inherited text-input API, see [[InputfieldText]]. For the target page
name input, see [[InputfieldPageName]].

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `nameField` | `string` | `''` | Custom-mode target input name. Empty means use default page-add behavior. |
| `nameDelimiter` | `string` | `''` | Custom-mode word delimiter, commonly `'-'` or `'_'`. |
| `nameReplacements` | `array` | `[]` | Custom-mode character replacement map. Empty falls back to page-name replacements. |

These properties are used only in custom mode, when `nameField` is non-empty.

## Default Page-Add Mode

On the normal ProcessWire page-add screen, the title input cooperates with the
standard [[InputfieldPageName]] input. As the user types a title, the raw title is
copied to the page-name field and the page-name field handles sanitization.

Once the user edits the page-name field manually, automatic updates stop so the
manual page name is preserved.

This mode requires no configuration.

## Custom Mode

Set `nameField` to target a non-standard name input:

```php
$f = $modules->get('InputfieldPageTitle');
$f->name = 'title';
$f->nameField = 'custom_name';
$f->nameDelimiter = '-';
$f->nameReplacements = [
	'ä' => 'ae',
	'ö' => 'oe',
	'ü' => 'ue',
	'ß' => 'ss',
];
```

When `renderReady()` runs, custom mode:

- Adds wrapper class `InputfieldPageTitleCustom`.
- Adds `data-name-field` and `data-name-delimiter` wrapper attributes.
- Publishes `InputfieldPageTitle.replacements` to JavaScript config.

The JavaScript then converts title text into the target input value. With a
delimiter and strict generation, ASCII letters are lowercased, repeated
delimiters are collapsed, and trailing delimiters are trimmed.

Custom mode skips setup in the browser when the target input already has a value,
so existing names are not overwritten.

## Replacement Fallback

If `nameReplacements` is empty, custom mode falls back to:

1. The `InputfieldPageName` module `replacements` configuration.
2. `InputfieldPageName::getDefaultReplacements()`.

This keeps custom title-to-name behavior aligned with the site's page-name
replacement rules.

## Notes

- Get an instance with `$modules->get('InputfieldPageTitle')`.
- It is a permanent core module.
- This class adds no server-side title validation beyond [[InputfieldText]].
- `renderReady()` performs custom setup only when `nameField` is populated.
- The bundled JavaScript depends on jQuery and is intended for forms that include
  the matching page-name input.
- Source file:
  `wire/modules/Inputfield/InputfieldPageTitle/InputfieldPageTitle.module`.

