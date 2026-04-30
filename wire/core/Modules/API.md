# Modules

The `$modules` API variable loads, manages, installs and configures modules in ProcessWire.
Agents: The `wire/core/Modules.php` and `wire/core/Modules/*.php` contain the full methods reference when needed.

## Getting modules

```php
// Get a module by name — returns null if not found
$m = $modules->get('MarkupPagerNav');
$m = $modules->MarkupPagerNav; // alternate property-access form

// Get with options
$m = $modules->getModule('ModuleName', [
    'noInit'             => true,  // don't call module init()
    'noInstall'          => true,  // don't auto-install if uninstalled
    'noPermissionCheck'  => true,  // skip permission check
    'noThrow'            => true,  // return null instead of throwing
    'configData'         => [...], // extra config data to merge in
]);
```

### getModule() options

| Option              | Default | Description                                                    |
|---------------------|---------|----------------------------------------------------------------|
| `noPermissionCheck` | `false` | Skip module permission check (and resulting exception)         |
| `noInstall`         | `false` | Don't auto-install uninstalled modules                         |
| `noInit`            | `false` | Don't call module `init()` — see also `configOnly`             |
| `configOnly`        | `false` | Populate config data but don't call `init()`                   |
| `configData`        | `[]`    | Extra config data merged with module's stored config           |
| `noSubstitute`      | `false` | Don't fall back to a substitute module                         |
| `noCache`           | `false` | Don't cache the resolved module instance                       |
| `noThrow`           | `false` | Return `null` instead of throwing on permission/fatal errors   |
| `returnError`       | `false` | Return error string instead of `null` on failure               |

## Finding modules

Return value is an array indexed by module class name and the contents of the returned array is 
dictated by the `$load` argument. All three find methods accept the same `$load` argument:

| `$load` value | What is returned                             |
|---------------|----------------------------------------------|
| `false`       | Array of module names (default)              |
| `true`        | Array of instantiated module objects         |
| `1`           | Array of module info arrays                  |
| `2`           | Array of verbose module info arrays          |

```php
// Find modules whose class name starts with a prefix
$inputfields = $modules->findByPrefix('Inputfield');
$inputfields = $modules->findByPrefix('Inputfield', true);  // load=true: get instances

// Find modules that have a given flag set (fastest method)
$cliModules  = $modules->findByFlag(Modules::flagsCli);
$autoloaders = $modules->findByFlag(Modules::flagsAutoload, 1); // load=1: get info arrays

// Find modules by a module info property or selector
$autoloads = $modules->findByInfo('autoload');             // non-empty 'autoload'
$matches   = $modules->findByInfo('autoload=1, core=1');   // selector string
$matches   = $modules->findByInfo('author*=Ryan, core=0'); // partial match 'Ryan' 
$matches   = $modules->findByInfo(['autoload' => 1]);      // array match
```

## Module status checks

```php
// Is module installed?
if($modules->isInstalled('ModuleName')) { ... }

// Is module installable (file on disk, not yet installed)?
if($modules->isInstallable('ModuleName')) { ... }
if($modules->isInstallable('ModuleName', true)) { ... } // true = all deps also available now

// Does the module load automatically at boot?
if($modules->isAutoload($module)) { ... }

// Does the module support only a single instance at runtime?
if($modules->isSingular($module)) { ... }

// Is the module interactively configurable?
if($modules->isConfigurable('ModuleName')) { ... }
```

## Installing and uninstalling

```php
// Install a module — also installs dependencies by default
$module = $modules->install('ModuleName');
$module = $modules->install('ModuleName', [
    'dependencies' => true,  // also install uninstalled dependencies (default=true)
    'resetCache'   => true,  // reset module info cache after install (default=true)
    'force'        => false, // install even if dependencies can't be met (default=false)
]);

// Uninstall a module (returns bool)
$modules->uninstall('ModuleName');

// Physically delete a module's files from disk (must be uninstalled first)
$modules->delete('ModuleName');

// Refresh modules list — picks up new, moved, or changed module files on disk
$modules->refresh();
$modules->refresh(true); // show admin notice messages about what changed
```

## Module info

`getModuleInfo()` returns an associative array with at least these properties:

| Key               | Type        | Description                                               |
|-------------------|-------------|-----------------------------------------------------------|
| `id`              | int         | Database ID                                               |
| `name`            | string      | Module class name                                         |
| `title`           | string      | Module title                                              |
| `version`         | int         | Module version integer                                    |
| `icon`            | string      | Optional icon name (Font Awesome, without "fa-" prefix)   |
| `requires`        | array       | Module class names required by this module                |
| `requiresVersions`| array       | Required modules with operator+version, keyed by name     |
| `installs`        | array       | Module class names this module auto-installs              |
| `permission`      | string      | Permission name required to execute this module           |
| `autoload`        | bool        | Does the module load at boot?                             |
| `singular`        | bool        | Single instance at runtime?                               |
| `created`         | int         | Unix timestamp of when module was installed               |
| `installed`       | bool        | Is the module currently installed?                        |
| `configurable`    | bool or int | Is the module configurable? (see `isConfigurable()`)      |
| `namespace`       | string      | PHP namespace the module class lives in                   |

`getModuleInfoVerbose()` additionally returns the following: 

| Key                  | Type               | Description                                                 |
|----------------------|--------------------|-------------------------------------------------------------|
| `versionStr`         | string             | Version in string format, i.e. `0.1.7`                      |
| `summary`            | string             | Short summary of what the module does.                      |
| `author`             | string             | The module author name(s).                                  |
| `href`               | string             | URL for more information.                                   |
| `file`               | string             | Full disk path/file for the module.                         |
| `core`               | bool or int        | Non-empty if this is a core module.                         |
| `permissions`        | array              | Permissions the module installs/uninstalls (example below). |
| `searchable`         | bool or null       | True when module implements `SearchableModule`              |


- `permissions` value example:  `['permission-name' => 'Description description']`

### getModuleInfo usage examples

```php
// Get common module info
$info = $modules->getModuleInfo('ModuleName');
echo $info['title'];
echo $modules->formatVersion($info['version']); // e.g. "1.2.3"

// Get verbose module info (includes summary, author, file, core, etc.)
$info = $modules->getModuleInfoVerbose('ModuleName');

// Get a single module info property (uses cache, fast)
$version = $modules->getModuleInfoProperty('ModuleName', 'version');

// Get abbreviated info for all installed modules, indexed by module ID
$all = $modules->getModuleInfo('*');

// Get a blank info template array (all keys with default values)
$template = $modules->getModuleInfo('info');
```
### getModuleInfo for Process modules

Modules of type `Process` also may optionally include the following in their verbose module info.
Please see the `Process` module interface `wire/core/Module/Process/Process.php` for details.

| Key                 | Type               | Description                                                                             |
|---------------------|--------------------|-----------------------------------------------------------------------------------------|
| `page`              | array              | Info for page to create on install (and remove on uninstall).†                          |
| `nav`               | array              | Admin navigation definition.                                                            |
| `useNavJSON`        | bool               | Whether the module implements an `___executeNavJSON()` method for AJAX JSON navigation. |

**`page` value example:**

```php 
'page' => [ 'name' => 'foo', 'parent' => 'setup', 'title' => 'Foo' ],
   ```

**`nav` value example:**
```php
'nav' => [ 
    [ 'url' => '', 'label' => 'Foo', 'icon' => 'smile-o' ], 
    [ 'url' => 'bar/', 'label' => 'Bar', 'icon' => 'home' ],
    [ 'url' => 'baz/', 'label' => 'Baz', 'icon' => 'sliders' ]
],
```
## Module configuration

```php
// Get all config data for a module
$data = $modules->getConfig('HelloWorld');

// Get a single config property
$apiKey = $modules->getConfig('HelloWorld', 'apiKey');

// Save all config data
$data = $modules->getConfig('HelloWorld');
$data['greeting'] = 'Hello!';
$modules->saveConfig('HelloWorld', $data);

// Save a single config property (key, value form)
$modules->saveConfig('HelloWorld', 'greeting', 'Hello!');

// Get URL to the module's config/edit screen in the admin
$url = $modules->getModuleEditUrl('ModuleName');

// Get URL to install a module (or its edit screen if already installed)
$url = $modules->getModuleInstallUrl('ModuleName');
```

## Hooks

Hook before or after any of the following methods: 

| Hook                            | When fired                                  |
|---------------------------------|---------------------------------------------|
| `Modules::install`              | When a module is installed                  |
| `Modules::uninstall`            | When a module is uninstalled                |
| `Modules::delete`               | When a module's files are deleted from disk |
| `Modules::refresh`              | When the modules list is refreshed          |
| `Modules::saveConfig`           | When module config data is saved            |
| `Modules::moduleVersionChanged` | When a module's version changes on load     |

```php
// Example: log after a module is installed
$wire->addHookAfter('Modules::install', function(HookEvent $e) {
    $class  = $e->arguments(0); // module class name (string)
    $module = $e->return;       // installed Module object
    $this->log("Installed module: $class");
});

// Example: hook directly to $modules, before config is saved
// and display an notification of what is being saved
$modules->addHookBefore('saveConfig', function(HookEvent $e) {
    $module = $e->arguments(0); // Module class or instance
    $class = $module instanceof Module ? $module->className() : $module;
    $data = $e->arguments(1); // array of data, or property name string
    if(is_array($data)) {
        // saving entire module config
        $e->message([ "Saving $class config data" => $data ]); 
    } else {
        // saving just a property of the config
        $property = $data;
        $value = $e->arguments(2);
        $e->message([ "Saving module $class $property" => $value ]); 
    }
}); 
```

## Helper classes (wire/core/Modules/)

| Property              | Class              | Purpose                                    |
|-----------------------|--------------------|--------------------------------------------|
| `$modules->info`      | `ModulesInfo`      | Module info cache; `getModuleInfo()` etc.  |
| `$modules->loader`    | `ModulesLoader`    | Boot loading; `init`/`ready` triggers      |
| `$modules->flags`     | `ModulesFlags`     | Module flags in-memory cache               |
| `$modules->files`     | `ModulesFiles`     | File discovery and inclusion               |
| `$modules->configs`   | `ModulesConfigs`   | Config data get/save                       |
| `$modules->installer` | `ModulesInstaller` | Install, uninstall, delete (lazy-loaded)   |

## Notes

- `$modules->get()` and property access auto-install a module if its file is present but
  not yet installed. Pass `'noInstall' => true` to `getModule()` to prevent this.
- When iterating `$modules` directly, items may be `ModulePlaceholder` instances rather
  than real modules. Call `$modules->get($name)` to get the real instantiated module.
- All write methods (`install`, `uninstall`, `delete`, `saveConfig`, `refresh`) are hookable
  via the triple-underscore `___methodName()` pattern.
- `getConfig()` / `saveConfig()` replaced the older `getModuleConfigData()` /
  `saveModuleConfigData()` names in ProcessWire 3.0.16. Both names still work.

## More about modules and how to make them

Please see the [Module](../Module/API.md) class documentation for details on the Module 
interface, the different types of modules, and to learn more about how to create modules.