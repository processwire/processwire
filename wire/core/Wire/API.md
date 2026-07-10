# Wire

The base class for almost every object in ProcessWire. `Wire` provides the
shared infrastructure that derived classes rely on: access to ProcessWire API
variables, the hook system, change tracking, runtime notices, translation
helpers, and instance identification.

You never instantiate `Wire` directly — it is an abstract class. Instead, you
work with subclasses such as `Page`, `Field`, `WireData`, `WireArray`, or your own
module/class that extends `Wire`. Every method documented here is available on
all of those objects.

```php
class MyWidget extends Wire {
  public function hello() {
    // Access any API variable as if it were a local property
    return "Hello " . $this->user->name;
  }
}

$widget = new MyWidget();
$wire->wire($widget); // "wire" the object so it can reach the API

echo $widget->hello();
```

---

## API variable access

Every `Wire` object can reach ProcessWire's API variables through the `wire()`
method or through magic property access. The two approaches are usually
interchangeable.

### `wire($name = '', $value = null, $lock = false)`

Get, set, or inject dependencies. This is the core method used by all `Wire`
derived objects to talk to the ProcessWire instance.

```php
// Get the current ProcessWire instance
$pw = $this->wire();

// Get an API variable by name
$pages  = $this->wire('pages');
$config = $this->wire('config');
$user   = $this->wire('user');

// Alternate syntax: wire()->{var}
$pages = $this->wire()->pages;

// Get all API variables as a Fuel object
$fuel = $this->wire('all');

// Create a new API variable
$this->wire('widgets', $widgets);

// Create a locked API variable (cannot be overwritten)
$this->wire('widgets', $widgets, true);

// Wire (inject) a new object so it can access the API
$newObject = $this->wire(new MyWidget());
```

### Magic property access

When `$useFuel` is enabled (the default), API variables are also accessible as
object properties. `Wire` resolves them through `__get()`.

```php
$pages  = $this->pages;
$config = $this->config;
$user   = $this->user;

// The special 'wire' property returns the ProcessWire instance
$pw = $this->wire;
```

If a property name does not match a real class property or an API variable,
`Wire` checks whether a hook property with that name has been added (see
[Hooks](#hooks)). If nothing matches, `null` is returned.

---

## Identification

### `className($options = null)`

Return the object's class name.

```php
$page = $pages->get('/about/');

echo $page->className();      // "Page"
echo $page->className(true);  // "ProcessWire\Page"

// Hyphenated lowercase, useful for CSS classes or log file names
echo $page->className(['lowercase' => true]); // "page"
```

### `getInstanceNum($getTotal = false)`

Return a unique numeric ID for this `Wire` instance, or the total number of
`Wire` instances created in the current request when `$getTotal` is `true`.

```php
echo $page->getInstanceNum();       // e.g. 1234
echo $page->getInstanceNum(true);   // total instance count
```

### `__toString()`

When a `Wire` object is used as a string, it returns `className()` by default.
Subclasses often override this to return something more meaningful (for
example, `Page` returns its path).

```php
$obj = new WireData();
$this->wire($obj);
echo (string) $obj; // "WireData"
```

---

## Hooks

`Wire` is the gateway to ProcessWire's hook system. A hookable method is defined
in a subclass by prefixing it with three underscores: `___myMethod()`. Other
code can then attach logic before or after that method executes, or even add
brand-new methods and properties to existing classes.

### `addHookBefore($method, $toObject, $toMethod = null, array $options = [])`

Attach code to run before a hookable method. The hook can inspect and modify
arguments, or set `$event->replace = true` to skip the original method.

```php
$pages->addHookBefore('find', function(HookEvent $event) {
  $selector = $event->arguments(0);
  // modify $selector before Pages::find() sees it
  $event->arguments(0, $selector . ', limit=10');
});
```

### `addHookAfter($method, $toObject, $toMethod = null, array $options = [])`

Attach code to run after a hookable method. The hook can read and modify the
return value through `$event->return`.

```php
$pages->addHookAfter('find', function(HookEvent $event) {
  $event->return->shuffle();
});
```

### `addHookProperty($property, $toObject, $toMethod = null, array $options = [])`

Add a computed property to an object or class. It can be read like a property
and also called like a method.

```php
$pages->addHookProperty('Page::wordCount', function(HookEvent $event) {
  $page = $event->object;
  $event->return = str_word_count(strip_tags($page->body));
});

// Use as a property
echo $page->wordCount;

// Also callable as a method
echo $page->wordCount();
```

### `addHookMethod($method, $toObject, $toMethod = null, array $options = [])`

Add a brand-new public method to an object or class.

```php
$pages->addHookMethod('Page::isChildOf', function(HookEvent $event) {
  $page   = $event->object;
  $parent = $event->arguments(0);
  $event->return = $page->parent->id === $parent->id;
});

if($page->isChildOf($home)) { /* ... */ }
```

### `addHook($method, $toObject, $toMethod = null, array $options = [])`

Low-level hook method. `addHookBefore`, `addHookAfter`, `addHookProperty`, and
`addHookMethod` are convenience wrappers around this method.

### `removeHook($hookId)`

Remove a previously attached hook using the ID returned by any `addHook*`
method.

```php
$hookID = $pages->addHookAfter('save', function($event) { /* ... */ });
$pages->removeHook($hookID);
```

A hook can also remove itself from within its own callback:

```php
$hookID = $pages->addHookAfter('find', function($event) {
  $event->removeHook(null);
});
```

### `getHooks($method = '', $type = 0)`

Return all hooks attached to this instance or method. The `$type` argument can
limit the result to local or static hooks using constants from
[[WireHooks]].

```php
$hooks = $page->getHooks();
$hooks = $page->getHooks('path');
```

### `hasHook($name)`

Return `true` if this object instance has a hook for the given method or
property. Use `method()` for methods and `property` for properties.

```php
if($page->hasHook('path')) { /* Page::path is hooked */ }
if($page->hasHook('wordCount')) { /* hook property exists */ }
```

---

## Change tracking

`Wire` can keep a record of which properties have changed. This is used heavily
by saveable objects such as `Page` and `Field` so that `$page->save()` knows
what to write to the database.

### Constants

| Constant             | Value | Description                                      |
|----------------------|-------|--------------------------------------------------|
| `trackChangesOn`     | `2`   | Track only the names of changed properties       |
| `trackChangesValues` | `4`   | Track names and previous values of changes       |

### `setTrackChanges($trackChanges = true)`

Enable or disable change tracking, optionally with value tracking.

```php
$page->setTrackChanges(true);                  // track names only
$page->setTrackChanges(Wire::trackChangesValues); // also remember old values
```

### `trackChanges($getMode = false)`

Return whether change tracking is enabled. Pass `true` to get the full bitmask.

```php
if($page->trackChanges()) { /* tracking is on */ }
```

### `resetTrackChanges($trackChanges = true)`

Clear all tracked changes and optionally turn tracking back on.

```php
$page->resetTrackChanges();
```

### `isChanged($what = '')`

Return `true` if the given property has changed, or if any property has changed
when called with no argument.

```php
if($page->isChanged('title')) { /* title changed */ }
if($page->isChanged()) { /* any property changed */ }
```

### `trackChange($what, $old = null, $new = null)`

Record that a property changed. Descending classes call this internally when
mutating tracked values.

```php
$this->trackChange('status', 0, 1);
```

### `untrackChange($what)`

Remove a property from the tracked-changes list.

```php
$page->untrackChange('title');
```

### `getChanges($getValues = false)`

Return changed property names. Pass `true` to get previous values (requires
`trackChangesValues`), or `2` to get an associative array with names as both
keys and values.

```php
$names  = $page->getChanges();      // ['title', 'body']
$values = $page->getChanges(true);  // ['title' => ['Old title'], ...]
```

---

## Notices and logging

Any `Wire` object can record messages, warnings, and errors that are surfaced
to the user or saved to a log. These are typically shown in the admin UI.

### `message($text, $flags = 0)`

Record an informational/success notice.

```php
$this->message("Settings saved");
$this->message("Also written to log", Notice::log);
$this->message("Debug only", Notice::debug);
```

### `warning($text, $flags = 0)`

Record a non-fatal warning.

```php
$this->warning("Deprecated option used", Notice::debug);
```

### `error($text, $flags = 0)`

Record a non-fatal error. For fatal errors, throw a `WireException` instead.

```php
$this->error("Upload failed");
```

### `messages($options = [])`

Return or manage messages recorded by this object. The `$options` argument can
be a string or array of flags: `first`, `last`, `all`, `clear`, `array`,
`string`.

```php
$messages = $this->messages();              // Notices collection
$first    = $this->messages('first');       // first NoticeMessage
$all      = $this->messages('all');         // all messages system-wide
$texts    = $this->messages('clear array'); // clear and return array of strings
```

### `warnings($options = [])`

Same as `messages()` but for warnings.

```php
$warnings = $this->warnings('all');
```

### `errors($options = [])`

Same as `messages()` but for errors.

```php
$errors = $this->errors('clear all');
```

### `log($str = '', array $options = [])`

Write a message to a log file. By default the log file name is derived from the
class name in hyphenated lowercase (for example, `my-widget.txt`).

```php
$this->log("Something happened");

// Write to a specific log file
$this->log("Something happened", ['name' => 'my-custom-log']);
```

---

## Translation

`Wire` implements the `WireTranslatable` interface and exposes the standard
ProcessWire translation helpers. These methods pass context to `__()`, `_x()`,
and `_n()` so translations can be scoped to the class.

### `_($text)`

Translate a string.

```php
echo $this->_("Hello");
```

### `_x($text, $context)`

Translate a string with context, used when the same English word maps to
different translations.

```php
echo $this->_x("Order", "commerce");
```

### `_n($textSingular, $textPlural, $count)`

Translate singular or plural form based on count.

```php
echo $this->_n("One item", "%d items", $count);
```

---

## Hookable methods

These methods exist primarily so subclasses and hooks can override or react to
them. You generally do not call the triple-underscore form directly.

| Method                              | Triggered when …                                |
|-------------------------------------|-------------------------------------------------|
| `___changed($what, $old, $new)`     | A tracked property changes                      |
| `___callUnknown($method, $args)`    | An unknown method is called                     |
| `___trackException($e, $severe, $text)` | An exception or error is caught            |
| `___log($str, array $options)`      | `log()` is called                               |

---

## Notes

- `Wire` is **abstract** — instantiate subclasses such as `WireData`, `Page`, or
  your own module/class instead.
- All `Wire` objects must be "wired" to a ProcessWire instance before they can
  access API variables. Objects created through the API (`$modules->get()`,
  `$pages->get()`, etc.) are wired automatically. Objects created with `new`
  must be passed through `$this->wire($object)` or `wire($object)`.
- API variables are resolved in this order: real class property → API variable
  (fuel) → hook property.
- The `[[WireData]]`, `[[WireArray]]`, `[[Page]]`, and `[[Field]]` classes all
  extend `Wire` and inherit everything documented here.
- For the full hook system, see [[WireHooks]] and
  [Hooks in the ProcessWire docs](https://processwire.com/docs/modules/hooks/).
- **Source file:** `wire/core/Wire.php`

