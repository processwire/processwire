# Module interface and classes

`Module` is the PHP interface that every ProcessWire module must implement. It requires only
a `className()` method — all other methods are optional and called by ProcessWire when present.
See `wire/core/Module/Module.php` for the full interface definition.

## Minimal module class

```php
<?php namespace ProcessWire;

class HelloWorld extends WireData implements Module {

    public static function getModuleInfo() {
        return [
            'title'   => 'Hello World',
            'version' => 1,
            'summary' => 'Demonstrates a minimal module.',
        ];
    }
}
```

Modules should extend `WireData` (or a recognized base type — see [Module types](#module-types)
below). The `className()` method is inherited from `Wire`, so no need to implement it yourself.

## Module info

ProcessWire needs basic information about each module. Provide it via one of:

**1. Static `getModuleInfo()` method:**
```php
public static function getModuleInfo() {
    return [
        'title'    => 'Hello World',
        'version'  => 1,
        'summary'  => 'One sentence description.',
        'author'   => 'Your Name',
        'href'     => 'https://example.com/module-info',
        'autoload' => false,
        'singular' => true,
        'requires' => ['OtherModule>=1.0.0', 'PHP>=8.0', 'ProcessWire>=3.0.200'],
        'installs' => ['SubModuleA', 'SubModuleB'],
    ];
}
```

**2. `ModuleName.info.php` file** (populates `$info` array, same structure as above):
```php
$info = [
    'title' => 'Hello World', 
    'version' => 1, 
    /* ... */
];
```

**3. `ModuleName.info.json` file:**
```json
{
  "title": "Hello World", 
  "version": 1
}
```

### Should you use getModuleInfo() or ModuleName.info.php (or json)?
In cases where the module has PHP or ProcessWire version requirements, or other module dependencies 
in the `requires` module info property, it is preferable to use a `ModuleName.info.php` or 
`ModuleName.info.json` file rather than a static `getModuleInfo()` method. This is because with a 
`ModuleName.info.php` (or `json`) file, the dependencies can be determined before loading the actual 
module file. 

For example, the `CliModule` interface was added in ProcessWire 3.0.259 — if a user tried to install 
a `CliModule` in a version of ProcessWire prior to that, they would get an "unknown class: CliModule" 
error as soon as ProcessWire reads the module file. Using a `ModuleName.info.php` (or `json`) file 
ensures that these dependencies can be determined and resolved before ProcessWire attempts
to read the module file. 

If a module does not need to populate anything to the `requires` module info property, then it is 
fine to use a static `getModuleInfo()` method. 

### Module info properties

| Property      | Type                  | Required | Description                                                        |
|---------------|-----------------------|----------|--------------------------------------------------------------------|
| `title`       | string                | yes      | Human-readable module name                                         |
| `version`     | int or string         | yes      | Version number — integer preferred (e.g. `101` = 1.0.1)           |
| `summary`     | string                | yes      | One-sentence description                                           |
| `author`      | string                | —        | Author name(s)                                                     |
| `href`        | string                | —        | URL for more information                                           |
| `autoload`    | bool/string/callable/int | —     | Load at boot? See autoload values below. (default=false)           |
| `singular`    | bool                  | —        | Single instance? (default=auto-detected from base type)            |
| `requires`    | array or string       | —        | Required modules, PHP or ProcessWire versions (CSV or array)       |
| `installs`    | array or string       | —        | Modules this module installs and uninstalls (CSV or array)         |
| `permission`  | string                | —        | Permission required to execute this module                         |
| `permissions` | array                 | —        | Permissions to auto-install: `['perm-name' => 'Description']`      |
| `icon`        | string                | —        | Font Awesome icon name, without the "fa-" prefix                   |
| `permanent`   | bool                  | —        | When true, module cannot be uninstalled (core modules only)        |
| `searchable`  | string                | —        | Implement `SearchableModule`; value is the search result group name|

#### `autoload` values

| Value              | Meaning                                                                |
|--------------------|------------------------------------------------------------------------|
| `true`             | Always load at boot                                                    |
| `false`            | Load only when requested via `$modules->get()` (default)              |
| selector string    | Load only when the current page matches the selector, e.g. `template=admin` |
| callable           | Load only when the callable returns true                               |
| int ≥ 2            | Load at boot before other autoload modules (higher = earlier)         |

#### `requires` version syntax

May be an array… 
```php
'requires' => [
    'OtherModule>=2.0.0',    // module with minimum version
    'PHP>=8.1',              // PHP version
    'ProcessWire>=3.0.200',  // ProcessWire version
],
```
…or a selector string: 
```php
'requires' => 'OtherModule>=2.0.0, PHP>=8.1, ProcessWire>=3.0.259',
```


## Module methods

All methods are optional. ProcessWire calls them when present:

| Method                       | Called when                                                                                |
|------------------------------|--------------------------------------------------------------------------------------------|
| `__construct()`              | Module instantiated — config data not yet populated. Must not have any required arguments. |
| `wired()`                    | Called after dependency injection (instance wired to ProcessWire's API)                    |
| `init()`                     | After config data populated — good place to attach hooks                                   |
| `ready()`                    | API fully ready — autoload modules only; `$page` is available                              |
| `install()`                  | Module installed — typically hookable `___install()`                                       |
| `uninstall()`                | Module uninstalled — typically hookable `___uninstall()`                                   |
| `upgrade($fromVersion, $to)` | Version change detected — typically hookable `___upgrade()`                                |
| `isSingular()`               | Is module a singleton? Overrides `singular` info property when present                     |
| `isAutoload()`               | Does module autoload at boot? Overrides `autoload` info property when present              |

```php
public function init() {
    // called after config populated — attach hooks here
    $this->addHookAfter('Pages::saved', $this, 'onPageSaved');
}

public function ready() {
    // autoload modules only — $page is available here
    if($this->wire()->page->template == 'admin') { ... }
}

public function ___install() {
    // create fields, templates, pages, etc.
    // throw WireException if install cannot proceed
}

public function ___uninstall() {
    // undo everything install() did
    // throw WireException if uninstall cannot proceed
}

public function ___upgrade($fromVersion, $toVersion) {
    // migrate data or settings between versions
}
```

When a module is instianted, the method call order is: `__construct()`, `wired()`, `init()` and `ready()` (when applicable).

## Module configuration

Configurable modules have their configuration values populated directly to the module 
automatically. This happens after `__construct()` and before `init()` and `ready()` (when applicable).


To be configurable, a module must: 

1. Implement the `ConfigurableModule` interface and extend the `WireData` class (or another module base class):

```php 
class MyModule extends WireData implements Module, ConfigurableModule {
```
2. Establish default values for its configurable settings in the constructor (optional but recommended):

```php
public function __construct() {
    parent::__construct();
    $this->set('greeting', 'Hello'); // set default value
}
```
3. Provide the form fields for collecting input by 
implementing a `getModuleConfigInputfields()`method (option A), or a `getModuleConfigArray()` method (option B), or
a `ModuleName.config.php` file with a class that extends the `ModuleConfig` class (option C). Each of these
options is outlined below. 

**Option A — `getModuleConfigInputfields()` method** (most common method):
```php
public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
    $f = $inputfields->InputfieldText;
    // or $f = $this->wire()->modules->get('InputfieldText');
    $f->attr('name', 'greeting');
    $f->label = 'Greeting';
    $f->val($this->greeting); // current value
    $inputfields->add($f);
    
    $f = $inputfields->InputfieldText;
    $f->name = 'first_name'; // alternate syntax, attr assumed
    $f->label = 'First name';
    $f->value = $this->first_name; // current value (alternate syntax)
    $inputfields->add($f);
}
```

**Option B — `getModuleConfigArray()` method** (shorthand array format):

```php
public static function getModuleConfigArray() {
    return [
        'greeting' => [
            'type'  => 'text', // 'InputfieldText' or just 'text'
            'label' => 'Greeting',
            'value' => 'Hello', // default value
        ],
        'first_name' => [
            'type'  => 'text', // 'InputfieldText' or just 'text'
            'label' => 'First name',
            'value' => '', // default value
        ],
    ];
}
```

**Option C — external `ModuleName.config.php` file** implementing a `ModuleConfig`-based class.
```php
class ModuleNameConfig extends ModuleConfig {
    public function __construct() {
        parent::__construct();
        $this->add([ 
            [ 
                'type' => 'text', // 'InputfieldText' or just 'text'
                'name' => 'greeting', 
                'label' => 'Greeting', 
                'value' => 'Hello' // default value
            ], [
                'type' => 'text', 
                'name' => 'first_name', 
                'label' => 'First name', 
                'value' => ''  // default value
            ]     
        ]);
    }
}
```    
4. Document your configuration properties (optional but recommended): 

```php
/**
 * My Module
 * 
 * @property string $greeting The greeting text
 * @property string $first_name First name
 * 
 */
class MyModule extends WireData implements Module, ConfigurableModule {
```

Module config data is also retrievable at runtime via `$modules->getConfig('ModuleName')`.
See [Modules API](../Modules/API.md) for `getConfig()` / `saveConfig()` usage.

## Module types

Extend a base type instead of plain `WireData` when your module fits a recognized pattern.
Base types set defaults for `singular`, `autoload`, and provide additional structure.

| Base class (A-Z)      | Location                            | Purpose                                       |
|-----------------------|-------------------------------------|-----------------------------------------------|
| `AdminThemeFramework` | `wire/core/Admin/AdminThemeFramework.php`                  | Admin interface themes (autoload, singular)   |
| `Fieldtype`           | `wire/core/Fieldtype/Fieldtype.php`                        | Field value storage and retrieval             |
| `FieldtypeMulti`      | `wire/core/Fieldtype/FieldtypeMulti.php`                   | Multi-value field value storage and retrieval |
| `FileValidatorModule` | `wire/core/Module/FileValidatorModule/FileValidatorModule.php` | Validates file uploads of specified types  |
| `Inputfield`          | `wire/core/Inputfield/Inputfield.php`                      | Input UI for field values (non-singular)      |
| `ModuleJS`            | `wire/core/Module/ModuleJS/ModuleJS.php`                   | Modules that load JS/CSS assets in admin      |
| `Process`             | `wire/core/Module/Process/Process.php`                     | Admin applications; execute via URL segments  |
| `Textformatter`       | `wire/core/Module/Textformatter/Textformatter.php`         | Output text formatting (singular)             |
| `Tfa`                 | `wire/core/Module/Tfa/Tfa.php`                             | Two-factor (or multi-factor) auth modules     |
| `WireSessionHandler`  | `wire/core/Session/WireSessionHandler.php`                 | Custom session storage backends               |
| `WireMail`            | `wire/core/WireMail/WireMail.php`                          | Email delivery adapters                       |

In addition, modules may implement these interfaces for optional behaviors:

| Interface             | Location                           | Purpose                                       |
|-----------------------|------------------------------------|-----------------------------------------------|
| `CliModule`           | `wire/core/Module/CliModule/CliModule.php`          | Expose commands via `php index.php <command>` |
| `SearchableModule`    | `wire/core/Module/SearchableModule/SearchableModule.php` | Participate in the admin search engine   |
| `ConfigurableModule`  | `wire/core/Module/ConfigurableModule.php`           | Enables PW to recognize configurable modules  |

### Process modules

Process modules are admin applications accessed via a page in the admin tree. They respond to
URL segments via `execute*()` methods:

```php
<?php namespace ProcessWire;

class ProcessHello extends Process {

    public static function getModuleInfo() {
        return [
            'title'   => 'Hello',
            'version' => 1,
            'summary' => 'A simple Process module.',
            'page'    => ['name' => 'hello', 'parent' => 'setup', 'title' => 'Hello'],
            'nav' => [ // optional navigation for admin theme dropdowns:
                ['url' => '',       'label' => 'Hello', 'icon' => 'smile-o'],
                ['url' => 'world/', 'label' => 'World', 'icon' => 'globe'],
            ],
        ];
    }

    // Handles /setup/hello/  (default)
    public function ___execute() {
        return '<p>Hello! See the <a href="./world/">world</a></p>';
    }

    // Handles /setup/hello/world/
    public function ___executeWorld() {
        return '<p>Here is the world: 🌐</p>';
    }
}
```

The `page` info property causes ProcessWire to auto-create the admin page on install and
remove it on uninstall. See `wire/core/Module/Process/Process.php` for the full Process API.

### CliModule interface

Modules implementing `CliModule` expose commands runnable from the command line:

```php
class MyModule extends WireData implements Module, CliModule {

    public static function getModuleInfo() {
        return [
            'title' => 'My Module', 
            'version' => 1, 
            'summary' => '...', 
            'cli' => 'foobar'
        ];
    }

    // called via: php index.php foobar [args...]
    public function executeCli(array $args) {
        if(empty($args)) return;
        if($args[0] === 'run') {
            if(isset($args[1]) && $args[1] === '--dry-run') {
                echo "Preview of main task";
            } else {
                echo "Running main task";
            } 
        } else if($args[0] === 'hi') {
            echo "Hello there!";
        }
    }

    public function getCliCommands() {
        return [
            'run' => 'Run the main task',
            'run --dry-run' => 'Preview without making changes',
            'hi' => 'Say hello',
        ];
    }
}
```

## Notes

- A module's `__construct()` fires before config data is populated — use `init()` for anything
  requiring config values.
- `ready()` is only called for `autoload` modules. Non-autoload modules do not have a `ready()` 
  method because the API is already assumed to be ready in their `init()` method.
- Modules that attach hooks should generally be `singular => true` to avoid duplicate hooks.
- Autoload modules are almost always also singular — an autoload non-singular module would
  create a new instance on every `$modules->get()` call while still loading at boot.
- If your `install()` or `uninstall()` method needs to call the parent class version
  (e.g. for a `Process` module that uses the `page` auto-install feature), do so explicitly:
  `parent::___install()` / `parent::___uninstall()`.
