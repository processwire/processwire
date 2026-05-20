# TemplateFile

`TemplateFile` loads a PHP file, executes it, and returns the rendered output as a string.
It extends `WireData`, so variables set on it are automatically available inside the PHP
file during rendering. All ProcessWire API variables (`$pages`, `$config`, etc.) are also
available in the rendered file.

`TemplateFile` is primarily used internally by ProcessWire to render template files, but
it is also a useful utility for rendering any PHP file in a controlled way.

~~~~~php
$t = new TemplateFile($config->paths->templates . 'partials/sidebar.php');
$t->set('items', $pages->find('template=product'));
echo $t->render();
~~~~~

---

## Shortcut: $files->render() and wireRenderFile()

For the common case of rendering a single file with a set of variables, the
`$files->render()` method and its procedural alias `wireRenderFile()` are more concise
than constructing a `TemplateFile` directly:

~~~~~php
// Using the $files API variable
echo $files->render('partials/card.php', ['page' => $page]);

// Using the procedural function
echo wireRenderFile('partials/card.php', ['page' => $page]);
~~~~~

Relative paths passed to these shortcuts are resolved from `/site/templates/` by default.
Absolute paths are allowed only when they resolve under ProcessWire-approved locations
such as `/site/templates/`, `/site/modules/`, or `/wire/modules/`. Both internally create
and configure a `TemplateFile` instance. See `WireFileTools` (`$files` API variable) for
the full signature and options.

---

## Constructing and setting files

### new TemplateFile($filename)

Construct a TemplateFile, optionally with the filename to render.

- **Arguments:** `__construct(string $filename = '')`

~~~~~php
$t = new TemplateFile($config->paths->templates . 'partials/card.php');
// or set the filename later:
$t = new TemplateFile();
$t->setFilename($config->paths->templates . 'partials/card.php');
~~~~~

---

### $t->setFilename($filename)

Set the primary PHP file to render.

- **Arguments:** `setFilename(string $filename)`
- **Returns:** `bool` — `true` on success
- Expects an absolute filesystem path.
- Throws `WireException` if the file does not exist (unless `setThrowExceptions(false)` was called).

~~~~~php
$t->setFilename($config->paths->templates . 'partials/nav.php');
~~~~~

---

### $t->setPrependFilename($filename)

Add a PHP file to prepend before the main file at render time. May be called multiple
times to prepend multiple files; they are rendered in the order added.

- **Arguments:** `setPrependFilename(string $filename)`
- **Returns:** `bool`
- Expects an absolute filesystem path.
- Throws `WireException` if the file does not exist (unless exceptions are disabled).

~~~~~php
$t->setPrependFilename($config->paths->templates . 'partials/header.php');
~~~~~

---

### $t->setAppendFilename($filename)

Add a PHP file to append after the main file at render time. May be called multiple
times; they are rendered in the order added.

- **Arguments:** `setAppendFilename(string $filename)`
- **Returns:** `bool`
- Expects an absolute filesystem path.

~~~~~php
$t->setAppendFilename($config->paths->templates . 'partials/footer.php');
~~~~~

---

## Variables

Because `TemplateFile` extends `WireData`, variables are set with `set()` and become
available as local variables inside the rendered PHP file. All ProcessWire API variables
are also in scope automatically.

### $t->set($name, $value)

Set a variable that will be available in the rendered PHP file.

- **Arguments:** `set(string $name, mixed $value)`
- **Returns:** `$this`

~~~~~php
$t->set('headline', 'Hello World');
$t->set('items', $pages->find('template=product, limit=10'));
// Inside card.php, $headline and $items are available as local variables.
~~~~~

Variables can also be set using WireData's array-style shortcut via `->`:

~~~~~php
$t->headline = 'Hello World';
$t->items = $pages->find('template=product');
~~~~~

---

### $t->get($name)

Get a variable or TemplateFile property. Values set to `0`, `false`, or an empty string
are returned as stored; `null` means the name was not found.

- **Arguments:** `get(string $name)`
- **Returns:** `mixed|null`

~~~~~php
$t->set('enabled', false);
var_dump($t->get('enabled')); // bool(false)
~~~~~

---

### $t->getArray()

Return all variables that will be available to the rendered PHP file, including both
custom variables set on this instance and all ProcessWire API variables.

- **Returns:** `array`

---

## Rendering

### $t->render()

Execute the PHP file(s) and return the rendered output.

- **Returns:** `string|array|mixed`
- Renders any prepend files first, then the main file, then append files.
- Returns the captured output as a string. Leading and trailing whitespace is trimmed
  by default (see `setTrim()`).
- If the main file returns a value and the captured output is empty, that return value is
  returned instead of a string — unless the return value is integer `1`, which PHP uses
  as the default `require` return when a file has no explicit `return` statement.
- Throws `WireException` if the main file does not exist (unless exceptions are disabled).
- This is a hookable method (`___render()`).

~~~~~php
$t = new TemplateFile($config->paths->templates . 'partials/card.php');
$t->set('page', $pages->get('/products/widget/'));
$html = $t->render();
~~~~~

**Return value from file:** A template file can `return` a value directly. If the file
produces no output (empty string after trimming) and returns something other than `1` (the
default `require` return value), that return value is passed back as the result of
`render()`. This is useful for files that build and return a data structure rather than
echo output:

~~~~~php
// In the PHP file:
return ['title' => $page->title, 'url' => $page->url];

// In calling code:
$data = $t->render(); // $data is an array
~~~~~

---

## Halting

### $t->halt($halt)

Stop further rendering without halting the PHP process. Preferred over `exit`/`die` from
inside a template file, as it only stops the template rendering and lets ProcessWire
continue normally.

- **Arguments:** `halt(bool|string $halt = true)`
- **Returns:** `$this`
- Pass a string to output that string and then halt (since 3.0.239).
- The typical usage from inside a template file is `return $this->halt();`.

~~~~~php
// In a template file — stop rendering if a condition is met:
if($someCondition) return $this->halt();

// Halt with a final output string:
return $this->halt('Access denied.');
~~~~~

Setting `$t->halt = true` externally also triggers halting at the next file boundary.

---

## Configuration

### $t->setThrowExceptions($throwExceptions)

Control whether a `WireException` is thrown when a file does not exist.

- **Arguments:** `setThrowExceptions(bool $throwExceptions)`
- Default is `true`.

~~~~~php
$t->setThrowExceptions(false); // silently skip missing files
~~~~~

---

### $t->setTrim($trim)

Set whether leading/trailing whitespace is trimmed from the rendered output.

- **Arguments:** `setTrim(bool $trim)`
- Default is `true` (whitespace is trimmed).
- Available since 3.0.154.

~~~~~php
$t->setTrim(false); // preserve leading/trailing whitespace
~~~~~

---

### $t->setChdir($chdir)

Set the working directory to change to before rendering.

- **Arguments:** `setChdir(string|bool $chdir)`
- By default, the working directory is changed to the directory that contains the main
  template file (`dirname($filename)`).
- Pass a specific path to change to that directory instead.
- Pass `false` to disable any directory change (available since 3.0.154).

~~~~~php
$t->setChdir(false); // do not change working directory during render
$t->setChdir('/some/other/path'); // change to specific directory
~~~~~

---

## Static methods

### TemplateFile::getRenderStack()

Return the stack of PHP files currently being rendered, from first to last. Useful for
debugging or for determining which file is the outermost caller.

- **Returns:** `array` — file paths in render order

~~~~~php
$stack = TemplateFile::getRenderStack();
// e.g. ['/site/templates/_init.php', '/site/templates/basic-page.php']
~~~~~

---

### TemplateFile::clearAll()

Clear all output buffers opened since the first `TemplateFile` was instantiated.
Useful for error recovery when rendering fails partway through.

- **Returns:** `int` — number of output buffers cleared
- Available since 3.0.175.

~~~~~php
$cleared = TemplateFile::clearAll();
~~~~~

---

## Hooks

### fileFailed($filename, $e)

Hookable method called when a PHP file throws an exception during rendering.

- **Arguments:** `fileFailed(string $filename, \Exception $e)`
- **Returns:** `bool` — return `true` to re-throw the exception, `false` to ignore it
- Default behavior is to re-throw the exception (`return true`).
- This is a hookable method (`___fileFailed()`).

~~~~~php
$t->addHook('fileFailed', function(HookEvent $event) {
    $filename = $event->arguments(0);
    $exception = $event->arguments(1);
    $this->log("Render failed for $filename: " . $exception->getMessage());
    $event->return = false; // suppress the exception
});
~~~~~

---

## Notes

- Source file: `wire/core/TemplateFile/TemplateFile.php`.
- `TemplateFile` extends `WireData` — use `set()`/`get()` to pass variables, or access
  them as properties.
- All ProcessWire API variables are automatically in scope inside the rendered file
  (extracted from `$fuel`).
- `$t->__toString()` returns the filename, or the class name if no filename is set.
- The `$t->halt` property and `$t->halt()` method both work from outside or inside a
  template file. From inside a file, the `return $this->halt()` pattern is preferred
  because it also exits the `require` call cleanly.
- `setGlobal()` is deprecated and should not be used.
