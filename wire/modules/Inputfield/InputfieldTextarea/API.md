# InputfieldTextarea

Multi-line text input. Extends `InputfieldText`, rendering a `<textarea>` element
instead of `<input>`. This is also the base class for rich text editors in
ProcessWire (like TinyMCE and CKEditor).

```php
$f = $modules->get('InputfieldTextarea');
$f->attr('name', 'description');
$f->label = 'Description';
$f->rows = 10;
```

Inherits all properties and validation from [[InputfieldText]]
(minlength, maxlength, stripTags, noTrim, pattern, showCount, etc.). See also
[[Inputfield]] for the shared Inputfield API.

## What's different from InputfieldText

InputfieldTextarea inherits everything from InputfieldText but overrides several
behaviors for multi-line text:

- Renders `<textarea>` instead of `<input>`
- Supports `rows` property (default 5)
- Removes `size` and `pattern` from config (not applicable to textareas)
- Handles HTML content types via `contentType` property
- `maxlength` behavior differs depending on context (see below)

## Properties

Only properties unique to InputfieldTextarea are listed here. See
[[InputfieldText]] for inherited properties.

| Property      | Type  | Default | Description                                                              |
|---------------|-------|---------|--------------------------------------------------------------------------|
| `rows`        | `int` | `5`     | Number of rows shown for the textarea                                    |
| `contentType` | `int` | `0`     | Content type when used with FieldtypeTextarea (see constants below)      |

## Constants

| Constant      | Value | Description                              |
|---------------|-------|------------------------------------------|
| `defaultRows` | 5     | Default number of rows for the textarea  |

The `contentType` property uses constants from `FieldtypeTextarea`:

| Constant                                    | Value | Description                                  |
|---------------------------------------------|-------|----------------------------------------------|
| `FieldtypeTextarea::contentTypeUnknown`     | 0     | Unknown or plain text content type           |
| `FieldtypeTextarea::contentTypeHTML`        | 1     | HTML content type                            |
| `FieldtypeTextarea::contentTypeImageHTML`   | 2     | HTML content type with image options enabled |

## Rendering

### render()

Renders a `<textarea>` element with the current value entity-encoded inside.

```php
$f->val('Hello World');
echo $f->render();
// <textarea name="my_field" rows="5">Hello World</textarea>
```

### renderValue()

Renders the value for display (no input). Behavior depends on content type:

- **Plain text** (default): Converts newlines to `<br>` and entity-encodes content.
- **HTML content type**: Purifies the HTML via `$sanitizer->purify()` and wraps in a div.

```php
$f->val("Line 1\nLine 2");
echo $f->renderValue();
// Line 1<br />\nLine 2
```

### isContentTypeHTML()

Returns `true` if the current `contentType` is `contentTypeHTML` or `contentTypeImageHTML`.

```php
if($f->isContentTypeHTML()) {
    // value contains HTML
}
```

## Maxlength behavior

InputfieldTextarea handles `maxlength` differently depending on context:

**Standalone forms** (`hasFieldtype === false`): The value is truncated to
`maxlength` characters during `val()`, just like InputfieldText. The `maxlength`
attribute is included in the rendered HTML.

**Page editor context** (has a Fieldtype): The value is NOT truncated — instead,
`maxlength` is rendered as a `data-maxlength` attribute and `processInput()`
reports an error if the value exceeds it, but does not truncate. This allows
rich text editors to manage their own content length.

```php
// Standalone: truncates
$f = $modules->get('InputfieldTextarea');
$f->attr('maxlength', 100);
$f->val(str_repeat('a', 200)); // truncated to 100

// Page field: does not truncate, warns instead
// (maxlength is set automatically by FieldtypeTextarea)
```

The default `maxlength` for standalone forms is 32768 (32KB). For page fields,
the default is 0 (no limit).

## Multi-language

InputfieldTextarea supports multi-language just like InputfieldText. See
the multi-language sections of [[InputfieldText]] and [[Inputfield]] for details.

## Usage with FieldtypeTextarea

When used with a Page field (via FieldtypeTextarea), the Inputfield's settings
are configured on the Field object and applied automatically. The `contentType`
property is typically managed by FieldtypeTextarea based on the field's
configuration (e.g. whether a rich text editor is selected).

For standalone forms, create and configure the Inputfield directly:

```php
/** @var InputfieldTextarea $f */
$f = $modules->get('InputfieldTextarea');
$f->attr('name', 'bio');
$f->label = 'Biography';
$f->rows = 8;
$f->maxlength = 500;
$f->showCount = InputfieldText::showCountChars;
```

## Notes

- Extends `InputfieldText`, inheriting all its properties and validation.
- The `size` and `pattern` config fields are removed (not applicable to textareas).
- When `contentType` is HTML, `renderValue()` uses `$sanitizer->purify()` for safe output.
- Rich text editors (TinyMCE, CKEditor) extend this class and may override rendering entirely.
- The `rows` attribute controls the visual height of the textarea. The browser adds a scrollbar when content exceeds the visible area.
- **Source file:** `wire/modules/Inputfield/InputfieldTextarea/InputfieldTextarea.module`.
