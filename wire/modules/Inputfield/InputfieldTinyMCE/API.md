# InputfieldTinyMCE

TinyMCE 6 rich text editor Inputfield for [[FieldtypeTextarea]] fields. It
extends [[InputfieldTextarea]], renders either a classic `<textarea>` editor or
an inline editor, and exposes ProcessWire-specific integration for HTML
Purifier, image uploads, links, style formats, paste filtering, and module/field
configuration layers.

```php
$f = $modules->get('InputfieldTinyMCE');
$f->attr('name', 'body');
$f->label = 'Body';
$f->toolbar = 'styles bold italic bullist numlist link';
$form->add($f);
```

For shared Inputfield behavior such as labels, attributes, rendering,
processing, errors, collapsed states, and visibility selectors, see
[[Inputfield]]. For textarea-specific behavior, see [[InputfieldTextarea]].

## Configuration Layers

TinyMCE settings are built from several layers. Later layers win:

1. Built-in defaults from `defaults.json`.
2. Module-level defaults from `defaultsFile` and `defaultsJSON`.
3. Per-field `settingsFile` and `settingsJSON`.
4. Field/UI settings such as `toolbar`, `plugins`, `features`, `inlineMode`,
   `toggles`, and `headlines`.
5. Runtime settings applied in `renderReady()`, such as disabling pasted
   data-images when no image field is available.

JSON override settings support prefixes:

| Prefix | Behavior |
| --- | --- |
| none | Replace the upstream setting. |
| `replace_` | Explicitly replace the upstream setting. |
| `add_` | Merge/append to the upstream setting. |
| `append_` | Alias of `add_`. |

```php
$f->settingsJSON = json_encode([
    'replace_toolbar' => 'styles bold italic',
    'add_plugins' => 'wordcount',
]);
```

The string value `default` means "use the upstream default" for settings that
support it.

## Properties

### TinyMCE Settings

| Property | Type | Description |
| --- | --- | --- |
| `plugins` | `string` | Space-separated TinyMCE plugin names. |
| `toolbar` | `string` | Space-separated toolbar tools; `|` separates groups. |
| `contextmenu` | `string` | Right-click context menu tools. |
| `removed_menuitems` | `string` | Menubar items to hide. |
| `invalid_styles` | `string\|array` | Inline styles to disallow. |
| `menubar` | `string` | Top-level menubar menus. |
| `height` | `int` | Editor height in pixels. |
| `external_plugins` | `array` | TinyMCE external plugin map. |

### Field/Inputfield Settings

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `inlineMode` | `int` | `0` | `0` classic editor, `1` inline variable height, `2` inline fixed height. |
| `lazyMode` | `int` | `1` | `0` load immediately, `1` lazy when visible, `2` lazy when clicked. |
| `toggles` | `array` | `[]` | Markup cleanup toggles using the `toggle*` constants. |
| `features` | `array` | common features | Enabled editor features; see below. |
| `headlines` | `array` | `h1` to `h6` | Allowed heading tags for block/style formats. |
| `settingsFile` | `string` | `''` | Root-relative URL to per-field JSON settings. |
| `settingsField` | `string` | `''` | Another TinyMCE field to inherit settings from. |
| `settingsJSON` | `string` | `''` | Per-field JSON override string. |
| `styleFormatsCSS` | `string` | `''` | CSS parsed into `style_formats` and `content_style`. |
| `extPlugins` | `array` | `[]` | External plugin URLs selected for this field. |

### Module Settings

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `skin` | `string` | `oxide` | TinyMCE UI skin. |
| `skin_url` | `string` | `''` | Custom skin URL when `skin=custom`. |
| `content_css` | `string` | `wire` | Built-in content CSS name, or `custom`. |
| `content_css_url` | `string` | `''` | Custom content CSS URL. |
| `extPluginOpts` | `string` | `''` | Newline-separated external plugin `.js` URLs. |
| `defaultsFile` | `string` | `''` | Root-relative JSON defaults file. |
| `defaultsJSON` | `string` | `''` | Module-level JSON defaults. |
| `optionals` | `array` | `['settingsJSON']` | Settings configurable per-field rather than globally. |
| `debugMode` | `bool\|int` | `false` | Enables verbose JavaScript logging. |
| `extraCSS` | `string` | `''` | CSS appended to editor `content_style`. |
| `pasteFilter` | `string` | `default` | Paste-filter whitelist. |
| `imageFields` | `array` | `[]` | Image fields allowed for drag-and-drop uploads. |
| `lang_<name>` | `string` | auto | TinyMCE language pack code for a ProcessWire language. |

### Runtime Helpers

| Property | Type | Description |
| --- | --- | --- |
| `configName` | `string` | JavaScript settings key under `ProcessWire.config.InputfieldTinyMCE.settings`. |
| `readonly` | `bool` | Read-only state, set during render-value mode. |
| `initialized` | `bool` | True after `init()` has run. |
| `settings` | `InputfieldTinyMCESettings` | Defaults and render settings helper. |
| `configs` | `InputfieldTinyMCEConfigs` | Field/module configuration helper. |
| `tools` | `InputfieldTinyMCETools` | JSON, purifier, image-field, link, and paste-filter helper. |
| `formats` | `InputfieldTinyMCEFormats` | Style format and invalid-style parser helper. |

## Constants

| Constant | Description |
| --- | --- |
| `mceVersion` | Bundled TinyMCE version, currently `6.8.2`. |
| `toggleCleanDiv` | Convert/remove div markup during save cleanup. |
| `toggleCleanP` | Remove empty paragraph tags. |
| `toggleCleanNbsp` | Convert `&nbsp;` to regular spaces. |
| `toggleRemoveStyles` | Remove all `style` attributes. |
| `defaultPasteFilter` | Default paste-filter whitelist string. |

## Features

The `features` array is queried with `useFeature($name)`.

| Feature | Meaning |
| --- | --- |
| `toolbar` | Show toolbar buttons. |
| `menubar` | Show top menubar. |
| `statusbar` | Show bottom statusbar. |
| `stickybars` | Use sticky toolbar/menubar. |
| `spellcheck` | Enable browser spellcheck. |
| `purifier` | Run HTML Purifier during save/render workflows. |
| `document` | Use document-style content CSS. |
| `imgUpload` | Enable image drag/drop upload when an image field is available. |
| `imgResize` | Allow image resize handles to generate variations. |
| `pasteFilter` | Filter pasted markup through the whitelist. |
| `inline` | Special query name; true when `inlineMode > 0`. |

```php
if($f->useFeature('purifier')) {
    // HTML Purifier is enabled for this editor.
}
```

## Methods

### useFeature($name)

Return whether a named feature is enabled. Passing `inline` checks
`inlineMode > 0`.

### mcePath($getUrl = false)

Return the disk path to the bundled TinyMCE directory, or its URL when
`$getUrl` is true.

```php
$path = $f->mcePath();
$url = $f->mcePath(true);
```

### setConfigName($name) / getConfigName()

Set or get the JavaScript settings key for this instance.

```php
$f->setConfigName('body_compact');
echo $f->getConfigName();
```

### configurable($set = null)

Get or set whether this field is independently configurable. A non-configurable
field inherits settings from `settingsField`.

### getSettingNames($types)

Return setting names for one or more setting groups: `tinymce`, `field`,
`module`, or `optionals`.

```php
$names = $f->getSettingNames('tinymce field');
```

Throws `WireException` for unknown groups.

### addPlugin($file) / removePlugin($file)

Add or remove an external plugin `.js` file in module configuration. `$file` is
relative to the ProcessWire root, such as `/site/templates/mce/plugin.js`.
`addPlugin()` requires the file to exist.

### getDirectionality()

Return `ltr` or `rtl`. The value is translatable with context
`language-direction`.

### renderReady($parent = null, $renderValueMode = false)

Load TinyMCE assets once per request, compute runtime settings, set `configName`,
detect usable image fields, and prepare JavaScript settings.

### render()

Render the editor. Classic mode renders a textarea plus init script. Inline mode
renders a contenteditable div when HTML Purifier is available.

### renderValue()

Render a sanitized non-editable value outside ProcessPageEdit, or defer to
normal render behavior while ProcessPageEdit is rendering value mode.

### processInput(WireInputData $input)

Process submitted markup, purify it when enabled, restore inline-mode field
names after processing, and track value changes.

## Helper Methods

The helper objects are public for advanced use, but most sites should use the
Inputfield properties and methods above.

Useful examples:

```php
$defaults = $f->settings->getDefaults();
$parsed = $f->formats->invalidStylesStrToArray('color a=background|border');
$cssUrl = $f->settings->getContentCssUrl();
```

## Notes

- Set a textarea field's `inputfieldClass` to `InputfieldTinyMCE` for normal
  field usage.
- The editor requires `MarkupHTMLPurifier`; ProcessWire declares this module as
  a requirement.
- `toolbar` values containing commas are ignored because they look like legacy
  CKEditor toolbar syntax.
- `invalid_styles` supports global styles like `color` and tag-specific styles
  like `a=background|background-color` or `table|td=height`.
- Image upload integration requires a real page editing context and an available
  multi-image [[FieldtypeImage]] field.
- External plugins configured with `addPlugin()` are stored in module config;
  selecting them for one field uses the `extPlugins` field setting.
- The bundled TinyMCE directory is `tinymce-6.8.2/`.

**Source files:** `wire/modules/Inputfield/InputfieldTinyMCE/InputfieldTinyMCE.module.php`
and companion helper files in the same directory.
