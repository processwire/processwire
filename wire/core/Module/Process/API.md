# Process

Base class for Process modules — self-contained applications that run in the
ProcessWire admin panel. Every admin page (Pages, Setup, Modules, Access, etc.)
is powered by a Process module. Extends [[WireData]] and implements the
[[Module]] interface.

```php
// Access a Process module via $modules:
$process = $modules->get('ProcessPageEdit');
echo $process->execute();

// Your own Process modules extend this class:
class ProcessHello extends Process {
    public function ___execute() {
        return "Hello World";
    }
}
```

All Process modules are **singular** (only one instance exists in memory) and are
**not autoloaded** — they are loaded on demand when requested via the API or when
their admin page is accessed.

## Getting started

A minimal Process module:

```php
<?php namespace ProcessWire;

class ProcessHello extends Process {

    public static function getModuleInfo() {
        return [
            'title' => 'Hello',
            'version' => 1,
            'summary' => 'A simple hello world Process module',
            'page' => [
                'name' => 'hello',
                'parent' => 'setup',
                'title' => 'Hello',
            ],
        ];
    }

    public function ___execute() {
        $this->headline('Hello World');
        $this->browserTitle('Hello');
        $this->breadcrumb('./', 'Hello');
        return '<p>Hello World</p>';
    }
}
```

## Module info properties

The `getModuleInfo()` method returns an array. Properties specific to Process modules:

| Property           | Type              | Description                                                              |
|--------------------|-------------------|--------------------------------------------------------------------------|
| `page`             | `array\|string\|bool` | Auto-install a page for this Process. See [Page installation](#page-installation). |
| `permission`       | `string`           | Permission name required to execute. Defaults to class name if omitted.  |
| `permissions`      | `array`            | Additional permissions to install. See [[Module]] for details.            |
| `permissionMethod` | `string`           | Static method name for custom permission checks.                         |
| `useNavJSON`       | `bool`             | Enable JSON navigation support. See [Navigation](#navigation).           |
| `nav`              | `array`            | Navigation items for admin theme dropdowns.                              |
| `icon`             | `string`           | Font-awesome icon name (e.g. `'cog'`, `'folder-o'`).                     |

### Navigation items (`nav`)

Each nav item is an array:

```php
'nav' => [
    [
        'url'        => 'action/',
        'label'      => 'Some Action',
        'permission' => 'some-permission',  // optional
        'icon'       => 'folder-o',          // optional
        'navJSON'    => 'navJSON/?custom=1', // optional
    ],
],
```

### Permission method

The optional `permissionMethod` should be a static method that receives an array and
returns `true` or `false`:

```php
public static function permissionCheck(array $data) {
    // $data['wire']    - ProcessWire instance
    // $data['user']    - Current User
    // $data['page']    - Page the Process lives on
    // $data['info']    - Module info array
    // $data['method']  - Requested method name
    return true;
}
```

Specify it in module info: `'permissionMethod' => 'permissionCheck'`.

## Execute methods

The core of every Process module. When a Process page is accessed, the URL determines
which `execute` method is called.

| URL                          | Method called       |
|------------------------------|---------------------|
| `/processwire/setup/hello/`  | `execute()`         |
| `/processwire/setup/hello/foo/` | `executeFoo()`   |
| `/processwire/setup/hello/bar-baz/` | `executeBarBaz()` |

```php
public function ___execute() {
    // Called when no URL segments are present
    return "Default view";
}

public function ___executeFoo() {
    // Called for URL segment "foo"
    return "Foo view";
}

public function ___executeBarBaz() {
    // Called for URL segment "bar-baz"
    return "Bar Baz view";
}
```

### Return values

Execute methods can return:

- **`string`** — Rendered as direct output in the admin.
- **`array`** — An associative array of variables passed to a view file.
- A string beginning with `{` — Treated as JSON and output with JSON content-type header.

```php
// Direct output
public function ___execute() {
    return "<h2>Hello World</h2>";
}

// View variables (requires a view file)
public function ___execute() {
    return [
        'items' => $pages->find('template=basic-page'),
        'title' => 'All pages',
    ];
}
```

### executeUnknown()

Add this method to your Process module for a catch-all when no matching execute method
is found. The requested URL segment is available via `$input->urlSegment1`.

```php
public function ___executeUnknown() {
    $name = $this->wire()->input->urlSegment1;
    return "No handler for: $name";
}
```

*Available since 3.0.133.*

### Executed hook

After every execute method completes, `___executed($method)` is called with the
name of the method that ran. Hook this to run code after any execute method:

```php
$wire->addHookAfter('ProcessHello::executed', function(HookEvent $event) {
    $method = $event->arguments(0);
    // $method is 'execute', 'executeFoo', etc.
});
```

## Admin UI helpers

Methods for setting the admin interface chrome: headline, browser title, and breadcrumbs.

### headline($headline)

Set the primary `<h1>` headline in the admin interface.

```php
$this->headline('Edit: ' . $page->title);
```

Returns `$this` for chaining.

### browserTitle($title)

Set the `<title>` tag shown in the browser tab.

```php
$this->browserTitle('My Process » ' . $page->title);
```

Returns `$this` for chaining.

### breadcrumb($href, $label)

Add a breadcrumb link to the admin breadcrumb trail.

```php
$this->breadcrumb('./', 'Overview');
$this->breadcrumb('../', 'Parent section');
```

Returns `$this` for chaining. Arguments can be passed in either order — the method
detects which is the URL and which is the label.

## Views

Process modules can use external view files for output. When an execute method returns
an array, ProcessWire looks for a corresponding `.php` view file.

### Default view resolution

| Execute method     | Default view file                         |
|--------------------|-------------------------------------------|
| `execute()`        | `views/execute.php`                       |
| `executeFoo()`     | `views/execute-foo.php`                   |
| `executeBarBaz()`  | `views/execute-bar-baz.php`               |

All paths are relative to the Process module's directory.

```php
// ProcessHello/ProcessHello.module.php
public function ___execute() {
    return [
        'greeting' => 'Hello World',
        'items'    => ['apple', 'banana', 'cherry'],
    ];
}
```

```php
// ProcessHello/views/execute.php
echo "<h2>$greeting</h2>"; // variables from the returned array are available
echo "<ul>";
foreach($items as $item) {
    echo "<li>$item</li>";
}
echo "</ul>";
```

### Custom view files

Override the default view resolution with `setViewFile()`:

```php
public function ___execute() {
    $this->setViewFile('views/custom.php');
    return ['data' => $someData];
}
```

### setViewFile($file)

Set the view file to use instead of the default. Path is relative to the module directory.
Returns `$this`. Throws WireException if the file doesn't exist.

```php
$this->setViewFile('views/my-custom-output.php');
```

### getViewFile()

Returns the full path of the current view file, or an empty string if none has been set.

### setViewVars($key, $value = null)

Set variables for the view file programmatically, instead of or in addition to
returning them from execute. Accepts a key/value pair or an associative array.

```php
$this->setViewVars('title', 'Hello');
$this->setViewVars([
    'title' => 'Hello',
    'items' => $items,
]);
```

Returns `$this`.

### getViewVars()

Returns all variables that have been set for the output view as an associative array.

```php
$vars = $this->getViewVars();
```

## Page installation

Process modules can automatically create and trash admin pages on install/uninstall.
Configure this in `getModuleInfo()` with the `page` property.

```php
'page' => [
    'name'   => 'hello',       // page name (or string shortcut)
    'parent' => 'setup',       // parent name, path, ID, or Page object
    'title'  => 'Hello World', // page title (defaults to module title)
    // Any additional properties are set on the page:
    'status' => Page::statusHidden,
],
```

Shorthand using a string:

```php
'page' => 'hello', // installs under admin root
```

Set to `true` to auto-derive the page name from the class name:

```php
'page' => true, // ProcessPageEdit → "page-edit"
```

### installPage($name, $parent, $title, $template, $extras)

Creates and assigns a page for this Process. Typically called automatically from
`___install()`. Available as a hookable method for custom page setup.

```php
// In your ___install():
public function ___install() {
    // Create a page under Setup with custom extras
    $this->installPage('my-process', 'setup', 'My Process', 'admin', [
        'status' => Page::statusHidden,
    ]);
    parent::___install();
}
```

Returns the created Page. Throws WireException on failure.

### uninstallPage()

Trashes all pages using this Process module. Called automatically from `___uninstall()`.
Returns the number of pages trashed.

### getProcessPage()

Returns the Page object that this Process is currently running on.

```php
$page = $this->getProcessPage();
echo "Running on: $page->path";
```

Returns a [[Page]] or [[NullPage]].

## Navigation

Process modules that manage lists of items can expose them as JSON for admin theme
navigation (flyout menus, sidebar trees).

### Enabling

Set `useNavJSON` to `true` in module info and implement `___executeNavJSON()`:

```php
'useNavJSON' => true,
```

### executeNavJSON($options)

Override this to provide navigable items. Receives an `$options` array and returns
a JSON string (or array if `getArray` is true).

```php
public function ___executeNavJSON(array $options = []) {
    $options['items'] = $this->getMyItems(); // array of objects or arrays
    $options['edit'] = 'edit?id={id}';
    $options['add'] = 'add';
    $options['itemLabel'] = 'title';
    $options['iconKey'] = 'icon';
    return parent::___executeNavJSON($options);
}
```

**Options:**

| Option         | Type      | Default          | Description                                        |
|----------------|-----------|------------------|----------------------------------------------------|
| `items`        | `array`   | `[]`             | Array of objects or arrays to list                  |
| `itemLabel`    | `string`  | `'name'`         | Property/field to use as item label                 |
| `itemLabel2`   | `string`  | `''`             | Property for secondary (smaller) label              |
| `edit`         | `string`  | `'edit?id={id}'`  | URL pattern for edit link. `{id}` and `{name}` replaced. |
| `add`          | `string`  | `'add'`          | URL segment for add action. Set empty to hide.      |
| `addLabel`     | `string`  | `'Add New'`      | Label for the add button                            |
| `addIcon`      | `string`  | `'plus-circle'`  | Icon for the add button                             |
| `iconKey`      | `string`  | `'icon'`         | Property containing per-item icon name              |
| `icon`         | `string`  | `''`             | Default icon for all items                          |
| `classKey`     | `string`  | `'_class'`       | Property for per-item CSS class (e.g. `'separator'`) |
| `labelClassKey`| `string`  | `'_labelClass'`  | Property for per-item label wrapper class           |
| `sort`         | `bool`    | `true`           | Sort items alphabetically                           |
| `getArray`     | `bool`    | `false`          | Return array instead of JSON                        |

## Login redirect

### getAfterLoginUrl(Page $page)

Static method. Override to return a URL to redirect to after an unauthenticated
user logs in. ProcessLogin calls this after authentication. Return `false` if your
module doesn't support login redirects.

```php
public static function getAfterLoginUrl(Page $page) {
    // Gather GET vars and URL segments, sanitize, reconstruct URL
    $input = wire()->input;
    $id = (int) $input->get('id');
    if($id) return $page->url . "edit/?id=$id";
    return false;
}
```

*Available since 3.0.167.*

## Module interface methods

Process implements the [[Module]] interface. Key methods you may override:

| Method                              | Description                                          |
|-------------------------------------|------------------------------------------------------|
| `init()`                            | Initialize, load CSS/JS assets                       |
| `ready()`                           | Called after all modules and page are ready          |
| `install()`                         | Install the module, create permissions and pages     |
| `uninstall()`                       | Uninstall the module, trash pages                    |
| `upgrade($fromVersion, $toVersion)` | Handle version upgrades                              |

```php
public function ___install() {
    parent::___install(); // Installs permission and auto-creates page
    // Your custom install logic
}

public function ___upgrade($fromVersion, $toVersion) {
    if($fromVersion < 2) {
        // Migrate data for version 2
    }
}
```

By default, `___install()` installs a permission matching the class name and
automatically creates a page if a `page` property is defined in module info.
Override it if you need custom install logic, but call `parent::___install()`
to retain the default behavior.

## Hooks

All hookable methods use the `___` prefix convention.

| Hook                              | When                                             | Arguments                          |
|-----------------------------------|--------------------------------------------------|------------------------------------|
| `Process::execute`                | Before executing the Process                     |                                    |
| `Process::executed`               | After any execute method completes               | `$method` (string)                 |
| `Process::headline`               | When headline is being set                       | `$headline` (string)               |
| `Process::browserTitle`           | When browser title is being set                  | `$title` (string)                 |
| `Process::breadcrumb`             | When a breadcrumb is added                       | `$href`, `$label`                 |
| `Process::install`                | During module installation                       |                                    |
| `Process::uninstall`              | During module uninstallation                     |                                    |
| `Process::upgrade`                | When module version changes                      | `$fromVersion`, `$toVersion`       |
| `Process::installPage`            | When a page is being created for this Process    | `$name`, `$parent`, `$title`, `$template`, `$extras` |
| `Process::uninstallPage`          | When pages are being trashed for this Process    |                                    |
| `Process::executeNavJSON`         | When JSON navigation data is requested           | `$options` (array)                 |

### Example hooks

```php
// Add custom data to every execute view
$wire->addHookBefore('Process::execute', function(HookEvent $event) {
    $process = $event->object;
    $process->setViewVars('currentUser', wire()->user);
});

// Log after any execute method
$wire->addHookAfter('Process::executed', function(HookEvent $event) {
    $method = $event->arguments(0);
    wire()->log->save('process', "Executed $method");
});

// Modify headline
$wire->addHookAfter('Process::headline', function(HookEvent $event) {
    $headline = $event->arguments(0);
    $event->return = "[DEV] $headline";
});
```

## Notes

- **Access:** Process modules are accessed via `$modules->get('ProcessName')`. They are singular
  (one instance) and not autoloaded.
- **Security:** The `permission` property controls access. Defaults to the class name.
  Users must have this permission to execute the Process.
- **`__toString()`:** Not applicable — Process objects are not intended for string conversion.
- **URL segments:** Additional URL segments beyond the base Process page URL are mapped to
  `execute[Segment]()` methods using camelCase conversion (`hello-world` → `executeHelloWorld`).
- Process modules inherit all [[WireData]] methods for property storage and retrieval.
- See the [[Module]] interface for full details on `init()`, `ready()`, `install()`,
  `uninstall()`, `upgrade()`, and module info configuration.
- The `execute()` method must return something — an empty string is valid if no output is needed.
- When an execute method returns an array, it must not be empty — an empty array cannot be
  distinguished from a scalar value.
- **Source file:** `wire/core/Module/Process/Process.php`

