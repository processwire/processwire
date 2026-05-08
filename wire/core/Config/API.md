# Config

`$config` is the API variable for ProcessWire configuration. It exposes all settings
defined in `/wire/config.php` and `/site/config.php`, plus runtime state set automatically
at boot. It also provides helper methods for paths and URLs, JavaScript config sharing,
asset version strings, version checks, and request introspection.

`$config` is accessible in template files as `$config`, `wire()->config`, or `config()`
(if the functions API is enabled); and in modules or other Wire-derived objects as
`$this->wire()->config`.

All settings in `/wire/config.php` can be overridden by placing them in `/site/config.php`.
You may also define your own custom properties there and read them from `$config` at runtime.

---

## Paths and URLs

### $config->paths and $config->urls

Two `Paths` objects that expose the server disk path and web URL for every named location
in ProcessWire. All values end with a trailing slash.

~~~~~php
// Server disk paths
$config->paths->root        // /var/www/html/
$config->paths->site        // /var/www/html/site/
$config->paths->templates   // /var/www/html/site/templates/
$config->paths->assets      // /var/www/html/site/assets/
$config->paths->files       // /var/www/html/site/assets/files/
$config->paths->cache       // /var/www/html/site/assets/cache/
$config->paths->logs        // /var/www/html/site/assets/logs/
$config->paths->modules     // /var/www/html/wire/modules/
$config->paths->siteModules // /var/www/html/site/modules/

// Web URLs (relative from domain root)
$config->urls->root        // / (or /subdir/ if PW is in a subdirectory)
$config->urls->site        // /site/
$config->urls->templates   // /site/templates/
$config->urls->assets      // /site/assets/
$config->urls->files       // /site/assets/files/
$config->urls->modules     // /wire/modules/
$config->urls->admin       // /processwire/ (or your custom admin URL)

// Full http/https absolute URLs (available on $config->urls only)
$config->urls->httpRoot         // https://domain.com/
$config->urls->httpAssets       // https://domain.com/site/assets/
$config->urls->httpFiles        // https://domain.com/site/assets/files/
$config->urls->httpTemplates    // https://domain.com/site/templates/
~~~~~

---

### $config->url($for) and $config->path($for)

Get a single URL or disk path by name. Shorthand for `$config->urls->get($for)` and
`$config->paths->get($for)`.

~~~~~php
$url  = $config->url('admin');      // "/processwire/"
$path = $config->path('templates'); // "/var/www/html/site/templates/"
~~~~~

---

### $config->urls($for) and $config->paths($for)

Get a single value (like `url()` / `path()`) or, when called with no argument, return
the whole `Paths` object.

~~~~~php
$paths = $config->paths();         // Paths object (all paths)
$path  = $config->paths('files');  // "/var/www/html/site/assets/files/"
$path  = $config->paths->files;    // alias of above, better in IDEs
~~~~~

---

### $config->setLocation($for, $dir, $url)

Update both the disk path and web URL for a named location.

- **Arguments:** `setLocation(string $for, string $dir, string|bool $url = '')`
- **Returns:** `$this`
- `$for` is any of: `cache`, `logs`, `files`, `tmp`, `templates`, or a custom name.
- Pass `false` for `$url` to update the path only (leaving URL unchanged).
- Paths relative to PW root should omit the leading slash.

~~~~~php
// Redirect the templates path and URL to an alternate directory for one user
if($user->name === 'karen') {
    $config->setLocation('templates', 'site/dev-templates/');
}
~~~~~

---

### $config->setPath($for, $path) and $config->setUrl($for, $url)

Update just the disk path or just the web URL for a named location.

~~~~~php
$config->setPath('files', '/mnt/shared/files/'); // absolute path outside web root
$config->setUrl('files', 'https://cdn.example.com/');
~~~~~

---

## Scripts and styles

`$config->scripts` and `$config->styles` are `FilenameArray` instances. ProcessWire's
admin themes use them to track CSS and JS files to include in the document `<head>`.
You can use the same mechanism for your own front-end templates.

~~~~~php
// Add a file
$config->styles->add($config->urls->templates . 'css/main.css');
$config->scripts->add($config->urls->templates . 'js/main.js');

// Prepend (insert before existing entries)
$config->scripts->prepend($config->urls->templates . 'js/vendor.js');

// Remove a file
$config->scripts->remove($config->urls->templates . 'js/main.js');

// Replace one file with another
$config->scripts->replace(
    $config->urls->templates . 'js/old.js',
    $config->urls->templates . 'js/new.js'
);

// Iterate and render tags
foreach($config->styles as $url) {
    echo "<link rel='stylesheet' href='$url'>\n";
}
foreach($config->scripts as $url) {
    echo "<script src='$url'></script>\n";
}
~~~~~

`FilenameArray` silently deduplicates: adding the same URL twice has no effect.

---

### $config->versionUrls($urls, $useVersion)

Return an array of URLs with cache-busting query strings appended. Used internally by
admin themes; also available for your own scripts/styles.

- **Arguments:** `versionUrls(array|FilenameArray $urls, bool|null|string $useVersion = null)`
- **Returns:** `array`

~~~~~php
// Render styles with cache-busting version strings
foreach($config->versionUrls($config->styles) as $url) {
    echo "<link rel='stylesheet' href='$url'>\n";
}

// Shortcut on FilenameArray itself (equivalent)
foreach($config->styles->urls() as $url) {
    echo "<link rel='stylesheet' href='$url'>\n";
}
~~~~~

| `$useVersion` value       | Behavior                                             |
|---------------------------|------------------------------------------------------|
| `null` (default)          | filemtime in debug/dev, `$config->version` otherwise |
| `true`                    | Always use filemtime                                 |
| `false`                   | Always use `$config->version`                        |
| `'1.2.3'` (string)        | Use that exact string as version                     |
| `'?v=abc'` (query string) | Use as-is                                            |

Set a site-wide default via `$config->useVersionUrls` in `/site/config.php`.

---

### $config->versionUrl($url, $useVersion)

Single-URL variant of `versionUrls()`.

~~~~~php
$url = $config->versionUrl($config->urls->templates . 'js/app.js');
echo "<script src='$url'></script>";
~~~~~

---

## JavaScript config

Share PHP configuration values with client-side JavaScript. Values are exposed as
`ProcessWire.config[key]`.

### $config->js($key, $value)

Share an existing `$config` property or set a new value for the JS side.

- **Arguments:** `js(string|array|null $key = null, mixed $value = null)`
- **Returns:** `mixed|array|$this`

~~~~~php
// Set a value that is shared with JS
$config->js('myModule', [
    'baseUrl' => $config->urls->templates,
    'debug'   => $config->debug,
]);

// Share an existing $config property with JS (pass true as value)
$config->js('debug', true);        // exposes $config->debug to JS as-is
$config->js(['debug', 'version'], true); // share multiple properties at once

// Get a shared value from PHP
$val = $config->js('myModule');    // returns the array set above

// Get all shared values (returns array)
$all = $config->js();
~~~~~

---

### $config->jsConfig($key, $value)

Like `js()`, but values are JS-only — they cannot be read back as `$config->key`.
Preferred for new properties in ProcessWire 3.0.173+.

~~~~~php
$config->jsConfig('mySettings', ['foo' => 'bar']);
$val = $config->jsConfig('mySettings'); // ['foo' => 'bar']
$all = $config->jsConfig();             // all JS-config-only values
~~~~~

---

## Version and environment checks

### $config->version($minVersion)

Check whether the current ProcessWire version meets a minimum. Pass no argument to
get the version string.

~~~~~php
if($config->version('3.0.200')) {
    // running PW 3.0.200 or newer
}
echo $config->version; // e.g. "3.0.258"
~~~~~

---

### $config->phpVersion($minVersion)

Check whether the current PHP version meets a minimum.

~~~~~php
if($config->phpVersion('8.1.0')) {
    // running PHP 8.1 or newer
}
~~~~~

---

### $config->installedAfter($date) and $config->installedBefore($date)

Check the site installation timestamp. Useful in upgrade code that must run only
for installations created after a particular date.

~~~~~php
if($config->installedAfter('2024-01-01')) {
    // site was installed after 2024
}
~~~~~

---

## Request introspection

These helpers are available before the API `$input` variable is ready, making them
useful in `/site/config.php` and boot files.

### $config->requestUrl($match, $get)

Get the current request URL (without query string). Pass a string or array of strings
to test whether the URL contains any of them.

~~~~~php
$url = $config->requestUrl();              // e.g. "/products/2024/"
if($config->requestUrl('/processwire/')) { // true if admin URL
    // ...
}
if($config->requestUrl(['foo', 'bar'])) {  // true if either string appears
    // ...
}
// Get query string only
$qs = $config->requestUrl('', 'query');   // e.g. "page=2&sort=title"
~~~~~

---

### $config->requestPath($match)

Like `requestUrl()` but strips any subdirectory prefix, returning just the path
relative to the PW installation root.

~~~~~php
$path = $config->requestPath();           // e.g. "/products/2024/"
if($config->requestPath('/processwire/')) { ... }
~~~~~

---

### $config->requestMethod($match)

Get or match the HTTP request method.

~~~~~php
if($config->requestMethod('post')) { ... } // case-insensitive match
$method = $config->requestMethod();        // "GET", "POST", "PUT", etc.
~~~~~

---

## Runtime properties

These properties are set automatically by ProcessWire at boot time and are read-only
in normal usage.

| Property                  | Type            | Description |
|---------------------------|-----------------|---|
| `$config->ajax`           | `bool`          | `true` if current request is an XHR/AJAX request |
| `$config->https`          | `bool`          | `true` if current request is HTTPS |
| `$config->admin`          | `bool` or `int` | `true` if current request is in the admin |
| `$config->cli`            | `bool`          | `true` if PW was booted from the command line |
| `$config->modal`          | `bool` or `int` | Positive int when request is in a modal window |
| `$config->internal`       | `bool`          | `false` when PW is externally bootstrapped |
| `$config->httpHost`       | `string`        | Current HTTP hostname |
| `$config->serverProtocol` | `string`        | e.g. `"HTTP/1.1"` or `"HTTP/2"` |
| `$config->version`        | `string`        | ProcessWire version, e.g. `"3.0.258"` |
| `$config->versionName`    | `string`        | Version with suffix, e.g. `"3.0.258 dev"` |
| `$config->urls`           | `Paths`         | Web URLs for named locations (see above) |
| `$config->paths`          | `Paths`         | Disk paths for named locations (see above) |
| `$config->styles`         | `FilenameArray` | CSS files queued for the current request |
| `$config->scripts`        | `FilenameArray` | JS files queued for the current request |

---

## Configurable settings

All settings below come from `/wire/config.php`, where each is documented with full
descriptions and defaults. Override any of them in `/site/config.php`.

### System modes

| Property                    | Default   | Description                                             |
|-----------------------------|-----------|---------------------------------------------------------|
| `$config->debug`            | `false`   | Enable debug mode; use `true` during development        |
| `$config->debugIf`          | `''`      | Enable debug mode for a specific IP, regex, or callable |
| `$config->advanced`         | `false`   | Advanced mode for PW core/module development            |
| `$config->demo`             | `false`   | Demo mode — disables POST saves                         |
| `$config->useFunctionsAPI`  | `false`   | Allow API variables as global functions                 |
| `$config->useMarkupRegions` | `false`   | Enable front-end markup regions                         |
| `$config->usePageClasses`   | `false`   | Enable custom page classes in `/site/classes/`          |
| `$config->useLazyLoading`   | `true`    | Lazy-load fields/templates for faster boot              |

Note: `useFunctionsAPI`, `useMarkupRegions` and `usePageClasses` may have a default value of
`false` in the /wire/config.php file but ProcessWire's default site-blank profile has them all
enabled by default. Meaning, most installations will have these settings enabled by default as well.

### Dates and times

| Property              | Default              | Description                |
|-----------------------|----------------------|----------------------------|
| `$config->timezone`   | `'America/New_York'` | PHP timezone string        |
| `$config->dateFormat` | `'Y-m-d H:i:s'`      | Default system date format |

### Session

| Property                        | Default  | Description                                                |
|---------------------------------|----------|------------------------------------------------------------|
| `$config->sessionName`          | `'wire'` | Session cookie name                                        |
| `$config->sessionExpireSeconds` | `86400`  | Inactivity expiry (seconds)                                |
| `$config->sessionAllow`         | `true`   | Bool or callable to allow/deny sessions per request        |
| `$config->sessionChallenge`     | `true`   | Use challenge key for extra security                       |
| `$config->sessionFingerprint`   | `1`      | Fingerprinting level (0=off, see `/wire/config.php`)       |
| `$config->sessionHistory`       | `0`      | Number of history entries to keep; 0 = disabled            |

### Template files

| Property                        | Default | Description                                           |
|---------------------------------|---------|-------------------------------------------------------|
| `$config->prependTemplateFile`  | `''`    | File loaded before each template (e.g. `_init.php`)   |
| `$config->appendTemplateFile`   | `''`    | File loaded after each template (e.g. `_main.php`)    |
| `$config->templateCompile`      | `true`  | Allow compiled template files                         |

### Files and assets

| Property                         | Default         | Description                                        |
|----------------------------------|-----------------|----------------------------------------------------|
| `$config->chmodDir`              | `'0755'`        | Octal permissions for newly created directories    |
| `$config->chmodFile`             | `'0644'`        | Octal permissions for newly created files          |
| `$config->uploadBadExtensions`   | `'php exe ...'` | Space-separated disallowed upload extensions       |
| `$config->pagefileSecure`        | `false`         | Protect files on access-restricted pages           |
| `$config->pagefileExtendedPaths` | `false`         | Extended path mapping for sites with >30,000 pages |

### HTTP and input

| Property                  | Default             | Description                                    |
|---------------------------|---------------------|------------------------------------------------|
| `$config->httpHosts`      | `[]`                | Whitelist of recognized hostnames (security)   |
| `$config->protectCSRF`    | `true`              | Enable CSRF protection on PW forms             |
| `$config->wireInputOrder` | `'get post cookie'` | Order `$input->var` searches                   |
| `$config->noHTTPS`        | `false`             | Disable HTTPS requirements (dev environments)  |

### Database

| Property             | Description              |
|----------------------|--------------------------|
| `$config->dbHost`    | Database hostname        |
| `$config->dbName`    | Database name            |
| `$config->dbUser`    | Database username        |
| `$config->dbPass`    | Database password        |
| `$config->dbCharset` | `'utf8'` or `'utf8mb4'`  |
| `$config->dbEngine`  | `'MyISAM'` or `'InnoDB'` |

### System IDs

These integer properties hold the page/template IDs assigned during installation.
Rarely needed in template code but useful in modules.

| Property                   | Description                  |
|----------------------------|------------------------------|
| `$config->rootPageID`      | Homepage page ID (usually 1) |
| `$config->adminRootPageID` | Admin root page ID           |
| `$config->trashPageID`     | Trash page ID                |
| `$config->http404PageID`   | 404 page ID                  |
| `$config->superUserPageID` | Superuser page ID            |
| `$config->guestUserPageID` | Guest user page ID           |

---

## Array-property method calls

Config properties that hold associative arrays can be read and updated using a method-call
syntax. This lets you get or set individual keys without overwriting the whole array.

~~~~~php
// Get one key from an array property
$siteOnly = $config->fileCompilerOptions('siteOnly');

// Set one key (other keys untouched)
$config->fileCompilerOptions('siteOnly', true);

// Set multiple keys at once
$config->fileCompilerOptions([
    'siteOnly'  => true,
    'cachePath' => $config->paths->root . '.my-cache/',
]);

// Unset a key (pass null as first argument, key name as second)
$config->fileCompilerOptions(null, 'siteOnly');
~~~~~

This works for any array-type config property: `fileCompilerOptions`, `imageSizerOptions`,
`webpOptions`, `wireMail`, `contentTypes`, `fileContentTypes`, `dbOptions`, `pageList`,
`pageEdit`, `AdminThemeUikit`, and others.

---

## Notes

- `$config` extends `WireData`, so any value not defined in `wire/config.php` or
  `site/config.php` will simply return `null`.
- You can define your own site-specific settings in `/site/config.php` and read them
  from `$config` anywhere in your templates.
- Changes to `$config` at runtime (e.g. in `/site/ready.php`) affect all subsequent
  code in the same request only — they are not persisted.
- The canonical reference for all configurable settings, their defaults, and inline
  documentation is `/wire/config.php`.
- Source files: `wire/core/Config/Config.php`, `wire/core/Config/Paths.php`,
  `wire/core/Config/FilenameArray.php`.
