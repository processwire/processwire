# WireTempDir

Manages the creation, tracking, and automatic cleanup of temporary directories under ProcessWire's cache path. Each instance creates a uniquely-named directory that is automatically removed when the object is destroyed (or at the next maintenance cycle if `setRemove(false)` was called). It is used throughout the core for short-lived scratch space — image resizing, file extraction, module downloads, and similar operations.

```php
$td = new WireTempDir();
$tempDir = $td->get();              // returns path like /site/assets/cache/WireTempDir/.SomeName/0/
$td->setRemove(false);             // opt out of auto-removal
// ...write files into $tempDir...
$td->remove();                      // manually clean up
```

You can also instantiate with a name (for shared/access-by-name temp directories):

```php
$td = wire(new WireTempDir($user));  // name = 'User'
$path = $td->get();                  // path under .User/
// directory persists across requests (with the same $name) until it expires
```

## Directory layout

```
/site/assets/cache/WireTempDir/         ← class root (shared by all instances)
  .User/                                ← name root (all temp dirs for $name = 'User')
    0/                                  ← individual temp dir (numeric or custom $id)
      .wtd                              ← hidden marker file (contains timestamp)
    1/
  .MyRandomNameRabc123/                ← randomized name root (auto-generated)
    0/
```

The `.wtd` hidden file is written into every directory created by WireTempDir; it serves as a marker so the class can distinguish its own directories from any manually created ones.

## Properties

| Property         | Type      | Description |
|------------------|-----------|-------------|
| `tempDirMaxAge`   | `int`     | Maximum age in seconds for files in a named temp dir before they are considered expired (default 120). |
| `cleanMaxAge`     | `int`     | Age in seconds after which directories are assumed abandoned and cleaned up during maintenance (default 86400 / 24h). |
| `autoRemove`      | `bool`    | Whether the temp directory should be automatically removed in `__destruct()` (default true). Set with `setRemove()`. |

## Constants

| Constant          | Type     | Description |
|-------------------|----------|-------------|
| `hiddenFileName`  | `string` | Name of the marker file placed in every created temp directory (value: `.wtd`). |

## Methods

### init($name, $basePath)

Initialize the temp-directory root for this instance. Should be called only once. If a `$name` was provided to the constructor, `init()` runs automatically.

```php
$td = new WireTempDir();
$td->init('MyFeature');
$root = $td->get();
```

Passing an object as `$name` uses the class name (minus namespace).

```php
$td->init($page);    // name = 'Page'
```

- `$basePath` – Optional override base path; must be within ProcessWire assets and be writable. Defaults to `$config->paths->cache`.
- Returns the temp-root path string.
- Throws `WireException` if the directory has already been initialized.

### get($id)

Returns a usable temporary directory path, creating it if necessary. Subsequent calls within the same request return the same path (cached).

```php
$path = $td->get();         // autogenerate numeric id
$files->filePutContents($path . 'test.txt', 'hello');
```

Using a custom identifier:

```php
$path = $td->get('backup'); // path under .MyName/backup/
```

- If `$id` collides with an existing directory, a numeric suffix is appended automatically.
- Cascading retries: if `mkdir()` fails, the method retries (up to 5 levels) with a modified name; a `WireException` is thrown after that.

### setMaxAge($tempDirMaxAge, $cleanMaxAge)

Update the maximum-age thresholds (in seconds). Chainable.

```php
$td->setMaxAge(300, 86400);   // tempDirMaxAge = 5 min, cleanMaxAge = 1 day
```

### setRemove($remove)

When `false` is passed, the temp directory will *not* be auto-removed when the object is destroyed; you take responsibility for calling `remove()` yourself.  Chainable.

```php
$td->setRemove(false);
// ...use the directory...
$td->remove();
```

### remove()

Removes the temporary directory created by this instance, plus its parent name-root if unique to this instance. Also performs one-time maintenance cleanup of expired directories under the class root. Returns `bool` indicating success.

```php
$ok = $td->remove();
```

### removeAll()

Clears *all* temporary directories created by WireTempDir on this site (the entire class-root tree). Use with caution — this is destructive and affects other instances/feature areas.

```php
$td->removeAll();
```

### maintenance()

Static one-time cleanup pass that removes abandoned directories under the class root (default 24h threshold). This is invoked automatically the first time any temp directory is created or removed during a request, but you can call it manually as well.

```php
$td->maintenance();
```

### createName($prefix)

Generates a random name for a runtime/scoped temp dir. Exposed publicly so you can preview the name or designate a custom prefix.

```php
$name = $td->createName('bw'); // e.g. 'bwT16903567870Rxyz…'
```

### __toString()

Returns the result of `get()`, making it convenient to string-cast the object directly.

```php
$td = new WireTempDir();
$path = (string) $td;
// or just:
$file = $td . '/test.txt';
```

## Notes

- **Instantiation:** `new WireTempDir()` or `wire(new WireTempDir($objectOrName))`. The constructor accepts a deprecated `$name` + `$basePath` argument list; prefer calling `init()` separately instead.
- **Auto-removal:** By default the temp directory is removed in `__destruct()`. Use `setRemove(false)` to keep it across multiple requests.
- **Shared vs. scoped directories:** If the name is a fixed string (or derived from an object's class name), directories persist across requests until expired. If auto-generated via `createName()`, they are unique to the instance and therefore safe to **only** remove at destruction.
- **Security:** `rmdir()` refuses to remove directories that are not under the class root. Created directories receive a `.wtd` marker file, but the removal safety check is based on the class-root path. `init()` replaces an out-of-tree `$basePath` with the default cache path.
- **Class root:** by default, files live under `$config->paths->cache . 'WireTempDir/'`.
- **`__toString()`** returns the current temp-directory path, equivalent to calling `get()`.
- **Source file:** `wire/core/Tools/WireTempDir/WireTempDir.php`
- Extends [[Wire]].

