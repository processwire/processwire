# InputfieldPageName

`InputfieldPageName` is the text input used for ProcessWire page `name` values.
It extends `InputfieldName` and adds a live URL preview, page-name sanitization,
multi-language page-name support, and configurable character replacements.

```php
$f = $modules->get('InputfieldPageName');
$f->attr('name', 'name');
$f->label = 'Page name';
$f->parentPage = $pages->get('/about/');
$form->add($f);
```

For shared Inputfield behavior such as attributes, labels, collapsed states,
`showIf`, rendering, and processing, see the main `Inputfield` API documentation.
For inherited text/name validation behavior, see `InputfieldName` and
`InputfieldText`.

## Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `parentPage` | `Page|null` | `null` | Parent of the page being edited; used to build the URL preview. |
| `editPage` | `Page|null` | `null` | Page being edited; used for language/template context. |
| `sanitizeMethod` | `string` | `pageName` or `pageNameUTF8` | Sanitizer method used when setting the value. Chosen automatically from `$config->pageNameCharset`. |
| `slashUrls` | `bool|int` | `1` | Whether the URL preview displays a trailing slash. |
| `languageSupportLabel` | `string` | `''` | Label shown above the input when multi-language page names are enabled. |
| `checkboxName` | `string` | `''` | Optional checkbox name rendered next to the input; blank disables it. |
| `checkboxLabel` | `string` | `''` | Optional checkbox label. |
| `checkboxValue` | `string` | `''` | Optional checkbox value. |
| `checkboxSuffix` | `string` | `''` | Suffix appended to the optional checkbox name. |
| `checkboxChecked` | `bool` | `false` | Whether the optional checkbox is checked by default. |
| `replacements` | `array` | defaults | Runtime character replacements used in ASCII page-name mode. |

The default `maxlength` is `Pages::nameMaxLength` and the input is required by
default through inherited `InputfieldName` behavior.

## URL Preview

When `parentPage` is set, rendered output includes a live preview of the resulting
URL path. The preview is updated by `InputfieldPageName.js` as the user types.

```php
$parent = $pages->get('/about/');
$page = $pages->get('/about/team/');

$f = $modules->get('InputfieldPageName');
$f->attr('name', 'name');
$f->attr('value', $page->name);
$f->parentPage = $parent;
$f->editPage = $page;
echo $f->render();
```

Set `slashUrls` to `false` or `0` to omit the trailing slash from the preview.

```php
$f->slashUrls = false;
```

## Sanitization

The value is sanitized server-side when assigned or processed:

- ASCII mode (`$config->pageNameCharset !== 'UTF8'`) uses
  `$sanitizer->pageName()`.
- UTF8 mode (`$config->pageNameCharset === 'UTF8'`) uses
  `$sanitizer->pageNameUTF8()`.

The client-side JavaScript mirrors this behavior for preview purposes, but
server-side sanitization is authoritative.

```php
$f = $modules->get('InputfieldPageName');
$f->attr('name', 'name');
$f->value = 'Hello World!';
echo $f->value; // hello-world
```

## Character Replacements

In ASCII page-name mode, characters outside the allowed range are replaced using
a configurable map. The default map transliterates common accented and Cyrillic
characters to ASCII equivalents.

Global replacements are configured from **Modules > Configure > InputfieldPageName**.
Each line uses `key=value` format:

```text
ä=a
ö=o
ü=u
```

Malformed lines without `=` are ignored by `replacementStringToArray()`.

## Methods

### getDefaultReplacements()

Returns the built-in default replacement array.

```php
$replacements = InputfieldPageName::getDefaultReplacements();
```

### replacementStringToArray($str)

Converts a multi-line `key=value` string to a replacement array. Lines without
`=` are ignored.

```php
$str = "ä=a\nö=o\ninvalid line";
$replacements = InputfieldPageName::replacementStringToArray($str);
// [ 'ä' => 'a', 'ö' => 'o' ]
```

### replacementArrayToString(array $a)

Converts a replacement array back to one `key=value` pair per line.

```php
$str = InputfieldPageName::replacementArrayToString([ 'ä' => 'a', 'ö' => 'o' ]);
// "ä=a\nö=o"
```

### render()

Renders the text input, URL preview, JavaScript configuration, and optional
language-support markup. Most code calls this indirectly through `$form->render()`.

### processInput(WireInputData $input)

Processes submitted input and sanitizes the value through inherited
`InputfieldName` / `InputfieldText` behavior. When `LanguageSupportPageNames` is
installed, it respects per-language editability.

## Multi-Language Page Names

When `LanguageSupportPageNames` is installed, this input can render a separate
input for each language. `languageSupportLabel` labels the per-language input,
and the `checkbox*` properties can render an optional action checkbox.

```php
$f->languageSupportLabel = 'English URL';
$f->checkboxName = 'use_default';
$f->checkboxLabel = 'Use default';
$f->checkboxValue = '1';
```

When `editPage` is set and its template has `noLang` enabled, language-specific
page-name behavior is disabled for that input.

## Notes

- Access this inputfield with `$modules->get('InputfieldPageName')`.
- Do not rely on client-side sanitization for security; server-side sanitization
  always runs.
- The URL preview is generated only when `parentPage` is set.
- In UTF8 mode, allowed characters are controlled by `$config->pageNameWhitelist`.
- The module is permanent and cannot be uninstalled.

**Source file:** `wire/modules/Inputfield/InputfieldPageName/InputfieldPageName.module`
