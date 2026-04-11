# FieldtypeModule

Stores a reference to a ProcessWire module. The value is either a module class name string
or a live module instance, depending on the `instantiateModule` field setting. This is an
advanced fieldtype, visible in the field editor only when advanced mode is enabled.

---

## Value type

Depends on the `instantiateModule` field setting:

| `instantiateModule` | Value when set | Value when empty |
|---|---|---|
| `false` (default) | `string` — module class name | see `blankType` |
| `true` | `Module` — instantiated module object | see `blankType` |

The empty/blank value is controlled by the `blankType` field setting:

| `blankType` | Empty value |
|---|---|
| `'null'` (default) | `null` |
| `'zero'` | `0` |
| `'false'` | `false` |
| `'placeholder'` | `ModulePlaceholder` instance |

---

## Getting and setting values

```php
// When instantiateModule=false (default): value is a class name string
$className = $page->module_field;  // e.g. 'InputfieldText' or null if not set
if($className) {
    $module = $modules->get($className);
}

// When instantiateModule=true: value is a live Module instance
$module = $page->module_field;
if($module instanceof Module) {
    // use the module directly
}

// Set by class name (works regardless of instantiateModule setting)
$page->module_field = 'InputfieldText';
$page->save('module_field');

// Set by Module instance (also works regardless of setting)
$page->module_field = $modules->get('InputfieldText');
$page->save('module_field');

// Clear the value
$page->module_field = null;
$page->save('module_field');
```

---

## Selectors

```php
// Match by module class name
$pages->find('module_field=InputfieldText');
$pages->find('module_field!=InputfieldText');
```

Supports `=` and `!=` operators only. Both module class names and numeric module IDs are
accepted as selector values.

---

## Notes

- This fieldtype is only visible in advanced mode in the field editor (`isAdvanced() === true`).
- `moduleTypes`: array of type prefixes or class names to filter which modules are selectable.
- `matchType`: `'prefix'` matches modules by name prefix (faster); `'verbose'` matches by
  class inheritance (more flexible).
- `instantiateModule`: when `true`, the field value is a live `Module` instance; when `false`
  (default), it is the module class name string.
- `blankType`: controls what an empty/unset field returns — `null` (default), `0`, `false`,
  or a `ModulePlaceholder` instance.
- Database column: `data INT NOT NULL` (stores the module's numeric ID).
- Compatible fieldtypes: `FieldtypeModule` only.
