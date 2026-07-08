# InputfieldJson

JSON viewer and editor powered by [jsoneditor](https://github.com/josdejong/jsoneditor)
(v10.4.3). Supports five display/edit modes. The value is always stored and returned
as a JSON string, but may be set as a PHP array or object (auto-encoded on assignment).

```php
$f = $modules->get('InputfieldJson');
$f->attr('name', 'my_json');
$f->label = 'My JSON';
$f->mode = InputfieldJson::modeTree; // editable tree (default)
$f->val('{"key":"value"}');
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API.

## Value model

The value is always a JSON string. Setting it as an array or object auto-encodes it.
An invalid JSON string is silently rejected and the previous value is kept.

```php
// Set as a string
$f->val('{"a":1,"b":2}');

// Set as an array (auto-encoded)
$f->val(['a' => 1, 'b' => 2]);

// Get always returns a string
$json = $f->val(); // '{"a":1,"b":2}'
```

When a form is submitted, the incoming value is validated with `json_decode()`. If
parsing fails, an error is added and the previous value is restored.

## Modes

| Constant                    | Value    | Description                                   |
|-----------------------------|----------|-----------------------------------------------|
| `InputfieldJson::modeTree`  | `'tree'` | Editable tree with add/remove/move (default)  |
| `InputfieldJson::modeForm`  | `'form'` | Editable form view of the tree                |
| `InputfieldJson::modeText`  | `'text'` | Raw text editor                               |
| `InputfieldJson::modeCode`  | `'code'` | Raw text editor with syntax highlighting      |
| `InputfieldJson::modeView`  | `'view'` | Read-only tree (also used by `renderValue()`) |

```php
$f->mode = InputfieldJson::modeCode;  // raw editor with syntax help
$f->mode = InputfieldJson::modeView;  // read-only tree
```

## Properties

| Property           | Type   | Default  | Description                                                    |
|--------------------|--------|----------|----------------------------------------------------------------|
| `mode`             | string | `'tree'` | Editor mode — one of the mode constants above                  |
| `useMainMenuBar`   | bool   | `false`  | Show the jsoneditor main menu bar                              |
| `useNavigationBar` | bool   | `false`  | Show the jsoneditor navigation bar                             |
| `useSearch`        | bool   | `false`  | Enable search (requires `useMainMenuBar`; avoid in PW admin)   |

## View-only rendering

`renderValue()` always uses `modeView` (read-only tree) regardless of the configured
mode, making it safe to use in contexts where editing is not intended.

```php
echo $f->renderValue();
```

## Notes

- The editor validates that submitted input is valid JSON, but does not sanitize the
  content. Treat values from editable instances as untrusted input.
- `useSearch` requires `useMainMenuBar` to be enabled and does not render well in the
  ProcessWire admin due to z-index conflicts.
- **Source file:** `wire/modules/Inputfield/InputfieldJson/InputfieldJson.module.php`
