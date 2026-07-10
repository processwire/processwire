# Fieldtypes / $fieldtypes

The `$fieldtypes` API variable is a collection of every installed Fieldtype module
in the system. It provides a central place to look up, iterate, and retrieve
`Fieldtype` instances by their class name or a short alias.

```php
// Get the text fieldtype and its corresponding Inputfield for a field
$text = $fieldtypes->get('text');
$inputfield = $text->getInputfield($page, $fields->get('body'));
```

For the shared collection API — adding, removing, finding, sorting, slicing,
and iterating — see [[WireArray]]. `Fieldtypes` is a `WireArray` subclass
indexed by each fieldtype's class name.

---

## Getting a fieldtype

### `get($name)`

Return a `Fieldtype` module for the given name. The name can be provided with
or without the `Fieldtype` prefix, and module-name matching is case-insensitive.

```php
// All of these return the same FieldtypeText instance
$ft = $fieldtypes->get('FieldtypeText');
$ft = $fieldtypes->get('text');

// Other examples
$ft = $fieldtypes->get('FieldtypePage');
$ft = $fieldtypes->get('page');
$ft = $fieldtypes->get('image'); // FieldtypeImage
```

If the requested fieldtype is installed but not yet loaded, `get()` resolves the
module automatically. Like `$modules->get()`, it can also install an available
but uninstalled Fieldtype module. It returns `null` when no matching module can
be loaded or installed.

Because `Fieldtypes` implements magic `__get()`, you can also access common
fieldtypes as object properties:

```php
$text = $fieldtypes->FieldtypeText;
$text = $fieldtypes->text;
$file = $fieldtypes->file;
$url  = $fieldtypes->URL;
```

### `has($name)`

Check whether the collection contains a fieldtype. Pass its full class name;
unlike `get()`, this method does not resolve short aliases or load modules.

```php
if($fieldtypes->has('FieldtypeComments')) {
    // Comments fieldtype is available
}
```

### Iterating all fieldtypes

```php
foreach($fieldtypes as $className => $fieldtype) {
    echo "<li>$className</li>";
}
```

---

## Common fieldtype aliases

The PHPDoc for `Fieldtypes` exposes shorthand properties for the most common
fieldtypes. Some properties have a `|null` type because the corresponding
bundled Fieldtype module can be unavailable when it has not been installed.
This indicates availability, not a Pro module.

| Property / alias | Fieldtype class        | Notes                              |
|------------------|------------------------|------------------------------------|
| `checkbox`       | `FieldtypeCheckbox`    | Boolean checkbox                   |
| `comments`       | `FieldtypeComments`    | May be `null` when not installed   |
| `datetime`       | `FieldtypeDatetime`    | Date/time picker                   |
| `email`          | `FieldtypeEmail`       | Email address                      |
| `file`           | `FieldtypeFile`        | One or more files                  |
| `float`          | `FieldtypeFloat`       | Floating-point number              |
| `image`          | `FieldtypeImage`       | One or more images                 |
| `integer`        | `FieldtypeInteger`     | Whole number                       |
| `module`         | `FieldtypeModule`      | Reference to another module        |
| `options`        | `FieldtypeOptions`     | May be `null` when not installed   |
| `page`           | `FieldtypePage`        | Page reference                     |
| `pageTable`      | `FieldtypePageTable`   | Tabular page references            |
| `pageTitle`      | `FieldtypePageTitle`   | Page title (multi-language aware)  |
| `password`       | `FieldtypePassword`    | Password hash                      |
| `repeater`       | `FieldtypeRepeater`    | May be `null` when not installed   |
| `selector`       | `FieldtypeSelector`    | May be `null` when not installed   |
| `text`           | `FieldtypeText`        | Single-line text                   |
| `textarea`       | `FieldtypeTextarea`    | Multi-line text                    |
| `toggle`         | `FieldtypeToggle`      | May be `null` when not installed   |
| `URL`            | `FieldtypeURL`         | URL                                |

You can still access any installed fieldtype by its full class name even if it
is not listed above.

---

## Notes

- **API variable:** `$fieldtypes`
- **Source file:** `wire/core/Fieldtypes/Fieldtypes.php`
- **Extends:** [[WireArray]]
- **Instantiation:** ProcessWire builds this collection during bootstrap via
  `$fieldtypes->init()`; you normally access it through the `$fieldtypes` API
  variable rather than constructing it yourself.
- **Indexed by:** each fieldtype's `className()` value (e.g. `FieldtypeText`).
- **Auto-resolution:** `get()` loads an installed module on demand and may
  install an available but uninstalled module.
- **Related:** [[Fieldtype]], [[FieldtypeMulti]], [[Fields]], [[Field]]
