# WireClassLoader / $classLoader

ProcessWire's class autoloader, similar to a PSR-4 autoloader but with built-in knowledge
of modules. It registers itself with PHP's `spl_autoload_register()` at boot time and
handles loading all ProcessWire core classes, modules, and any custom classes you register.

Most developers never call `$classLoader` directly — ProcessWire and its modules handle
their own registration automatically. You would use it when building a module that includes
helper classes outside of the main module file, but only if you want them autoloaded rather than
using `require_once()` or `include_once()` statements. Though note that manual require/include 
calls are typically more efficient than autoloading.

`$classLoader` is accessible in templates as `$classLoader` or `wire()->classLoader`, and
in modules as `$this->wire()->classLoader`.

---

## Registering namespaces

The primary way to register a directory of classes for autoloading. All classes in the
given namespace will be looked up in the registered path.

### `addNamespace($namespace, $path)`

```php
// Register a namespace pointing to a directory
$classLoader->addNamespace('ProcessWire', '/path/to/classes/');

// Multiple paths can be registered for a single namespace by calling again
$classLoader->addNamespace('ProcessWire', '/path/to/more-classes/');
```

All ProcessWire core classes and modules use the `ProcessWire` namespace. You can
optionally register additional paths under `ProcessWire` if needed, or you can 
register your own namespace. 

```php
// In a module's init() or __construct(), register the module's own directory
public function init() {
    $classLoader = $this->wire()->classLoader;
    
    // add directory to ProcessWire namespace
    $classLoader->addNamespace('ProcessWire', __DIR__);
    
    // register a custom namespace directory
    $classLoader->addNamespace('MyNamespace', __DIR__);
}
```

Once registered, any `ProcessWire\ClassName` (or `MyNamespace\ClassName`) class will be 
found automatically in the registered path when first referenced — no manual `require_once` needed.

### `getNamespace()`

Returns the registered paths for a namespace, or an empty array if not found.

```php
$paths = $classLoader->getNamespace('ProcessWire');
// ['/path/to/wire/core/', '/path/to/site/modules/', ...]
```

### `hasNamespace()`

Returns `true` if the namespace has at least one registered path.

```php
if($classLoader->hasNamespace('MyLibrary')) {
    // namespace is registered
}
```

### `removeNamespace()`

Removes a namespace registration, optionally for a single path only.

```php
// Remove all paths for a namespace
$classLoader->removeNamespace('MyLibrary');

// Remove a single path only
$classLoader->removeNamespace('MyLibrary', '/path/to/remove/');
```

---

## Registering class maps

A class map provides an explicit `ClassName => file` mapping for classes in the
`ProcessWire` namespace and is the fastest lookup method since it requires no directory
scanning. Added in 3.0.260.

### `addClassMap()`

```php
$classLoader->addClassMap([
    'MyHelper'   => 'helpers/MyHelper.php',
    'MyRenderer' => 'renderers/MyRenderer.php',
], __DIR__ . '/');
```

If a file entry ends with `/`, the filename is assumed to be `ClassName.php` in that
directory. If it ends with `>`, it looks for `ClassName/ClassName.php`.

```php
$classLoader->addClassMap([
    'MyHelper'   => 'helpers/',  // resolves to helpers/MyHelper.php
    'MyRenderer' => '>',         // resolves to MyRenderer/MyRenderer.php
], __DIR__ . '/');
```

---

## Prefix and suffix paths

Prefix and suffix registrations are a fallback mechanism — the class is not required to be
in the given path, but the path is added as an additional location to check when the class
is not found via namespace or class map lookup.

```php
// Any class starting with "My" will also be searched in this path
$classLoader->addPrefix('My', '/path/to/classes/');

// Any class ending with "Validator" will also be searched in this path
$classLoader->addSuffix('Validator', '/path/to/validators/');
```

---

## Locating class files

### `findClassFile()`

Returns the full path to the file where a class would be loaded from, or `false` if not
found. Useful for debugging autoload configuration.

```php
$file = $classLoader->findClassFile('ProcessWire\\Wire');
// '/path/to/wire/core/Wire.php'

$file = $classLoader->findClassFile('ProcessWire\\UnknownClass');
// false
```

---

## Notes

- **Source file:** `wire/core/WireClassLoader/WireClassLoader.php`
- **API variable:** `$classLoader` (also `wire()->classLoader` or `$this->wire()->classLoader`)
- **Default namespace:** All ProcessWire and module classes use the `ProcessWire` namespace;
  registering additional paths under it is the most common use case
- **PSR-4 similarity:** Lookup follows namespace-to-path mapping like PSR-4, but with module
  awareness — if a class is a known module, the Modules loader handles it instead
- **Module autoloading:** Modules are loaded automatically via the Modules system; `$classLoader`
  is only a consideration for additional helper classes that are not themselves modules
- **File extensions:** `.php` is supported by default; additional extensions can be registered
  with `addExtension()` if needed
