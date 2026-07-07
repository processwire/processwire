# PageFrontEdit

Front-end page editing module that allows authorized users to edit page fields directly
on the front-end of a ProcessWire site — without visiting the admin. Supports both
inline editing (for text and integer fields) and modal editing (for all other field types).

The module is autoloaded and hooks into `Page::render`, `Page::edit`, and
`Fieldtype::formatValue` to inject editing capabilities. It is configured in the module
settings, and editable regions can be marked up using four different methods (Options A–D).

```php
// The module is typically configured in the admin and used via its hooks.
// Retrieve the module instance:
$pfe = $modules->get('PageFrontEdit');

// You can also enable or disable editing programmatically:
$page->edit(true);  // enable editing for $page
$page->edit(false); // disable editing for $page
```

---

## How it works

1. On page render, the module checks whether the current user has `page-edit-front`
   permission and whether the page is editable.
2. If allowed, it hooks into `FieldtypeText::formatValue` and
   `FieldtypeInteger::formatValue` to wrap formatted field values in editable markup.
3. It also hooks into `Page::render` to inject JavaScript/CSS assets and UI buttons,
   and to process `<edit>` tags and `edit` attributes in the rendered markup.
4. Double-clicking an editable region opens it for editing inline (or launches a modal).
5. Saving sends an AJAX POST back to the page URL, which the module intercepts and
   processes via `ProcessWire::ready`.

---

## Properties

These properties are set via the module configuration screen and are accessible
on the module instance.

| Property                | Type          | Default    | Description |
|-------------------------|---------------|------------|-------------|
| `inlineEditFields`      | `array`       | `[]`       | Field IDs to make automatically editable inline (Option A) |
| `inlineLimitPage`       | `bool\|int`   | `1`        | When `1`, limit inline editing to fields on the rendered page only. When `0`, fields from any page are editable. |
| `inlineAllowFieldtypes` | `array`       | `['FieldtypeText', 'FieldtypeInteger']` | Fieldtype classes permitted for inline editing; others use modal |
| `editRegionAttr`        | `string`      | `'edit'`   | HTML attribute name for marking modal editable regions (Option D) |
| `editRegionTag`         | `string`      | `'edit'`   | HTML tag name for marking modal editable regions (Option C) |
| `buttonLocation`        | `string`      | `'auto'`   | Position of save/cancel buttons: `auto`, `nw`, `ne`, `sw`, `se` |
| `buttonType`            | `string`      | `'auto'`   | Button display style: `auto`, `both`, `text`, `icon` |

---

## Methods

### Retrieving the editor state

### getPage()

Returns the `Page` object currently being edited, or `null` if not yet set.
Hookable (method name: `___getPage`).

```php
$page = $pfe->getPage();
if($page) echo "Editing: " . $page->title;
```

### getAjaxPostUrl()

Returns the URL that AJAX save requests should POST to. By default this is the
URL of the page being rendered. Hookable (method name: `___getAjaxPostUrl`).
Available since 3.0.242.

```php
$postUrl = $pfe->getAjaxPostUrl();
```

### Setting the edited page

### setPage(Page $page)

Sets the page that will be used for front-end editing. Called automatically during
`ready()` but may be called manually in advanced use cases.

```php
$pfe->setPage($page);
```

---

### Checking editability

### inlineIsEditable(Page $page, Field $field)

Returns `true` if the given field on the given page is both configured for inline
editing and the current user has permission to edit it.

```php
if($pfe->inlineIsEditable($page, $field)) {
    // field can be edited inline
}
```

### inlineIsSaveable(Page $page, Field $field)

Returns `true` if the given field on the given page can be saved via front-end
editing, considering both permissions and whether the field type supports inline mode.

```php
if($pfe->inlineIsSaveable($page, $field)) {
    // field is saveable via front-end editor
}
```

### inlineSupported(Field $field)

Returns `true` if the field's Fieldtype is in the `inlineAllowFieldtypes` list
and its blank value is not an object. Non-inline-supported fields use modal editing
instead.

```php
if($pfe->inlineSupported($field)) {
    // field can be edited inline rather than in a modal
}
```

---

### Rendering assets

### renderAssets()

Returns the HTML markup for JavaScript includes, CSS, CSRF token, and the
save/cancel button bar. Called automatically by `hookPageRender()`; useful
when building custom rendering pipelines.

```php
echo $pfe->renderAssets();
```

---

## The Page::edit() method

PageFrontEdit adds an `edit()` method to every `Page` object via hooks on
`Page::edit` and `Page::editor`. This method serves multiple purposes:

**Check editor status (no arguments):**
```php
$active = $page->edit(); // true if editor is active, false otherwise
```

**Enable or disable the editor:**
```php
$page->edit(true);  // enable front-end editing
$page->edit(false); // disable front-end editing
```

**Get an editable formatted value (Option B):**
```php
// Returns formatted value wrapped in inline or modal editor markup
echo $page->edit('title');
```

**Get a non-editable formatted value:**
```php
// Pass false as the second argument to disable editor for this call only
echo $page->edit('title', false);
```

**Make custom markup editable (Option B with markup):**
```php
// Wrap arbitrary markup for the given field in an editor region
echo $page->edit('body', '<h1>' . $page->title . '</h1>' . $page->body);

// Force modal editing (3rd argument = true) even if inline is supported
echo $page->edit('images', $page->images->each('<img src="{url}">'), true);
```

When `$page->edit('field_name')` is called and the field is not an actual Field
(e.g., a runtime property), the call delegates to `$page->get('field_name')`.

---

## Marking editable regions

There are four ways to enable front-end editing for a field. The module
configuration screen provides inline help for all four options.

### Option A: Automatic (module settings)

Check the fields you want to be editable in the module settings under
"Option A: front-edit editable fields". Any output of those fields'
formatted values is automatically wrapped in inline editor markup.

No code changes required — just configure and go.

### Option B: API method call

Use `$page->edit('field_name')` in your template files instead of
`$page->field_name`. This works for text and textarea fields (including
CKEditor and TinyMCE).

```php
// Instead of:
echo $page->title;

// Use:
echo $page->edit('title');
```

### Option C: HTML `<edit>` tags

Wrap field output in `<edit>` tags in your template markup. Supports single
fields, multiple comma-separated fields, and cross-page editing.

```html
<!-- Single field, current page -->
<edit title>
    <h1><?= $page->title ?></h1>
</edit>

<!-- With named attribute -->
<edit field="title">
    <h1><?= $page->title ?></h1>
</edit>

<!-- Multiple fields -->
<edit field="title,body">
    <h1><?= $page->title ?></h1>
    <?= $page->body ?>
</edit>

<!-- Specific page by ID or path -->
<edit field="1001.title">
    <h1><?= $pages->get(1001)->title ?></h1>
</edit>
<edit page="1001" field="title">
    <h1><?= $pages->get(1001)->title ?></h1>
</edit>
```

When a single text-based field is specified, inline editing is used. When
multiple fields or a non-text field is specified, modal editing is used.

### Option D: HTML `edit` attributes

Add an `edit` attribute (or whatever `editRegionAttr` is configured to) to
any existing HTML element that wraps your editable content. The modal editor
is always used with this method.

```html
<div edit="title">
    <h1><?= $page->title ?></h1>
</div>

<!-- Cross-page editing -->
<div edit="1001.title">
    <h1><?= $pages->get(1001)->title ?></h1>
</div>
```

---

## Hooks

### Hookable methods

These methods can be hooked via `addHook()`, `addHookBefore()`, or `addHookAfter()`.

| Method             | Description                                      | Since   |
|--------------------|--------------------------------------------------|---------|
| `getPage()`        | Returns the Page being edited                    | 3.0.208 |
| `getAjaxPostUrl()` | Returns the URL for AJAX save POSTs              | 3.0.242 |

```php
// Customize the AJAX post URL
$wire->addHookAfter('PageFrontEdit::getAjaxPostUrl', function(HookEvent $event) {
    $url = $event->return;
    $event->return = $url . '?custom_param=1';
});
```

### Internal hooks added by the module

The module adds these hooks automatically during `ready()`. You can hook
before/after them if needed, but this is an advanced use case.

| Hook                          | When                                        |
|-------------------------------|---------------------------------------------|
| `Page::edit` (before)         | When `$page->edit()` is called              |
| `Page::editor`                | When `$page->editor()` is called            |
| `FieldtypeText::formatValue`  | After formatting a text field value         |
| `FieldtypeInteger::formatValue` | After formatting an integer field value   |
| `Page::render` (after)        | After page markup is rendered               |
| `ProcessWire::ready` (after)  | For processing AJAX save requests           |

---

## Constants

| Constant | Value   | Description                                       |
|----------|---------|---------------------------------------------------|
| `debug`  | `false` | Debug mode; should always be `false` in production |

---

## Notes

- **Access:** The module is autoloaded — access it via `$modules->get('PageFrontEdit')`.
- **Permissions:** Requires the `page-edit-front` permission, plus `page-edit` for the
  specific page and field being edited.
- **Admin theme:** The editor UI inherits styles from the user's admin theme. If no admin
  theme is assigned, `AdminThemeDefault` is used.
- **Non-HTML content types:** The module skips rendering if the page template's
  `contentType` is not HTML or `text/html`.
- **HTTPS enforcement:** If the admin template enforces HTTPS, the front-end page is
  redirected to HTTPS when the editor is active.
- **Live preview (PWPD):** The module disables itself when `?livepreview` is in the URL
  to avoid interference with the ProDrafts live preview feature.
- **Repeater fields:** Fields inside Repeater pages are supported; the module checks edit
  permissions against the repeater's "for" page and field.
- **TinyMCE inline mode:** When TinyMCE is used, the module enables inline mode
  automatically and passes required `data-` attributes to the editable element.
- **CKEditor:** CKEditor inline mode is supported automatically when
  `InputfieldCKEditor` is installed — the module loads CKEditor and initialises
  it on the editable element on double-click.
- **Cross-references:** See [[Page]] for the `edit()` method API, [[FieldtypeText]]
  and [[FieldtypeTextarea]] for field type details, and
  [[InputfieldCKEditor]]/[[InputfieldTinyMCE]] for rich text editor configuration.
- **Source file:** `wire/modules/Page/PageFrontEdit/PageFrontEdit.module`

