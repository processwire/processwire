# Hooks / HookEvent

In addition to modules, ProcessWire's hook system is one of its most important extension mechanisms. Any method whose name begins
with three underscores (`___`) is hookable — meaning other code can attach functions that run
before or after it, inspect or modify its arguments and return value, or replace it entirely.
Core ProcessWire methods are hookable throughout, and any module can make its own methods
hookable the same way.

Hooks are attached via `addHookBefore()`, `addHookAfter()`, and related methods available on
every `Wire`-derived object (which is essentially every ProcessWire object). The `$hooks` API
variable (`WireHooks`) manages the hook registry internally and is not typically called directly.

---

## Where to attach hooks

| Location                           | When to use                                                            |
|------------------------------------|------------------------------------------------------------------------|
| `/site/init.php`                   | Hooks that must be in place early in the boot process                  |
| `/site/ready.php`                  | Most site-wide hooks — full API is available here                      |
| `/site/templates/admin.php`        | Hooks that apply only to admin requests                                |
| `/site/templates/_init.php`        | Hooks for front-end templates only (or `$config->prependTemplateFile`) |
| `/site/templates/any-template.php` | Hooks scoped to a single template's logic or output                    |
| A module's `init()` or `ready()`   | Hooks added by autoload modules — called automatically during boot     |

A common pattern is to keep hook code in a dedicated `/site/templates/_hooks.php` file and
`require_once` it from `ready.php` or `_init.php`.

Note that "URL/path hooks" must be added during "init" or "ready" states, so will not work if placed in 
`/site/templates/` (it's too late). 

---

## Adding hooks

All `addHook*()` methods are available on any `Wire` object. In templates and procedural code
use `$wire->addHook…`; in modules or classes use `$this->addHook…`. To hook just a single object or API
variable instance, replace `$wire` or `$this` with the object instance.

### `addHookAfter()` — run after the method (default)

Use when you want to act on or modify the return value after a method runs. This is the most
common hook type.

```php
// Hook all Pages::saved events (static hook — fires for all $pages instances)
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    $page = $event->arguments(0);
    $event->message("Saved: $page->path");
});

// Modify the return value of Pages::find()
$wire->addHookAfter('Pages::find', function(HookEvent $event) {
    $items = $event->return; // PageArray
    // ... filter or modify $items ...
    $event->return = $items;
});

// Local hook — fires only for this $page instance
$page->addHookAfter('render', function(HookEvent $event) {
    $event->return = str_replace('foo', 'bar', $event->return);
});
```

### `addHookBefore()` — run before the method

Use when you want to inspect or modify arguments before the method runs, or conditionally
skip the original method entirely.

```php
// Modify a Page before save — object modifications take effect without writing back
$wire->addHookBefore('Pages::save', function(HookEvent $event) {
    $page = $event->arguments(0);
    if(!$page->summary) $page->summary = substr(strip_tags($page->body), 0, 200);
});

// Append a condition to every front-end find() — strings must be written back
$wire->addHookBefore('Pages::find', function(HookEvent $event) {
    if(wire()->config->admin) return;
    $selector = $event->arguments(0);
    if(is_string($selector)) {
        $event->arguments(0, "$selector, status!=trash");
    }
});
```

### `addHookProperty()` — add a new property

Adds a new property to every instance of a class (or a single instance).

```php
// Add a $page->byline property to all Page objects
$wire->addHookProperty('Page::byline', function(HookEvent $event) {
    $page = $event->object;
    $event->return = $page->author . ' — ' . $page->date('F j, Y');
});

echo $page->byline; // "Jane Smith — April 1, 2024"
```

Use `addHookMethod()` instead when you want method-call syntax like `$page->byline()`.

### `addHookMethod()` — add a new method

Adds a new callable method to every instance of a class (or a single instance).

```php
// Add a $page->hasAncestor($ancestor) method to all Page objects
$wire->addHookMethod('Page::hasAncestor', function(HookEvent $event) {
    $page = $event->object;
    $ancestor = $event->arguments(0);
    $event->return = $page->parents->has($ancestor);
});

if($page->hasAncestor($pages->get('/products/'))) {
    // ...
}
```

### Hooking multiple methods at once

Any `addHook*()` method accepts a CSV string or array as its first argument to attach the
same handler to multiple methods in one call. The return value is a CSV of hook IDs, accepted
as-is by `removeHook()`.

```php
$hookId = $wire->addHookAfter('Pages::saved, Pages::deleted', function(HookEvent $event) {
    // runs after both saved and deleted
});

$wire->removeHook($hookId); // removes all hooks in the CSV
```

### Callback forms

All `addHook*()` methods accept three callback forms:

```php
// 1. Inline closure (most common)
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    // ...
});

// 2. Object + method name (common in classes and modules — $this is usually the object)
$wire->addHookAfter('Pages::saved', $this, 'onPageSaved');
// calls $this->onPageSaved($event) when the hook fires

// 3. Procedural function name (rarely used)
$wire->addHookAfter('Pages::saved', null, 'myHookFunction');
// calls myHookFunction($event) when the hook fires
```

---

## The method argument

The first argument to any `addHook*()` method identifies what to hook.

| Format                               | Meaning                                              |
|--------------------------------------|------------------------------------------------------|
| `'Pages::saved'`                     | All instances of `Pages` — static hook               |
| `'saved'` (on instance)              | Only this object instance — local hook               |
| `'Pages::save*'`                     | All hookable methods on `Pages` starting with `save` |
| `'Pages::*'`                         | All hookable methods on `Pages`                      |
| `'Pages::saved, Pages::deleted'`     | Multiple methods (CSV)                               |
| `['Pages::saved', 'Pages::deleted']` | Multiple methods (array)                             |

**Static vs local hooks:** calling `$wire->addHookAfter('Pages::saved', ...)` fires for every
`$pages` instance. Calling `$page->addHookAfter('render', ...)` fires only for that one
`$page` instance. Use the `Class::method` form (static) during `init` or `ready` states,
or in template files, when you want site-wide behaviour.

*Note: the wildcard hook options, i.e. `Pages::save*`, `Pages::*` are intended for debugging 
and development purposes.*

---

## Removing hooks

`addHook*()` methods return a hook ID string. Pass it to `removeHook()` to remove the hook.

```php
$hookId = $wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    // ...
});

// Remove it later
$wire->removeHook($hookId);

// Or remove it from inside the hook itself
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    // ...
    $event->removeHook(null); // removes this hook; won't fire again
});
```

---

## Hook options

All `addHook*()` methods accept an `$options` array as the last argument.

| Option         | Type       | Default    | Description                                                                       |
|----------------|------------|------------|-----------------------------------------------------------------------------------|
| `before`       | bool       | false      | Execute before the method? (`addHookBefore` sets this automatically)              |
| `after`        | bool       | true       | Execute after the method? (`addHookAfter` sets this automatically)                |
| `type`         | string     | `'method'` | `'method'`, `'property'`, or `'either'`                                           |
| `priority`     | int\|float | 100        | Execution order — lower numbers run first; decimals (`100.1`, `100.2`) break ties |
| `allInstances` | bool       | false      | Hook all instances (set automatically for `Class::method` form)                   |
| `fromClass`    | string     | `''`       | Class containing the hooked method (set automatically)                            |

```php
// Lower priority = runs before other hooks at default (100) priority
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    // runs first
}, ['priority' => 50]);

// Decimal priority to order hooks registered at the same integer priority
$wire->addHookAfter('Pages::saved', $handler1, ['priority' => 100.1]);
$wire->addHookAfter('Pages::saved', $handler2, ['priority' => 100.2]);
```

---

## HookEvent

Every hook function receives a `HookEvent` instance as its only argument. It carries
information about the hooked call and provides methods for reading/writing arguments and the
return value.

### Properties

| Property      | Type           | Access     | Description                                                                                   |
|---------------|----------------|------------|-----------------------------------------------------------------------------------------------|
| `object`      | `Wire`         | read       | The object instance the hooked method was called on                                           |
| `method`      | `string`       | read       | The name of the hooked method (without `___` prefix)                                          |
| `arguments`   | `array`        | read/write | Numerically indexed array of arguments passed to the method                                   |
| `return`      | `mixed`        | read/write | Return value of the method (for `after` hooks); set to change it                              |
| `replace`     | `bool`         | write      | Set to `true` in a `before` hook to skip the original method entirely, use carefully!         |
| `when`        | `string`       | read       | `'before'` or `'after'` — which timing is currently executing                                 |
| `id`          | `string`       | read       | Hook ID string (same value returned by `addHook*()`)                                          |
| `eid`         | `int`          | read       | Unique integer ID for this `HookEvent` instance (3.0.258+)                                    |
| `cancelHooks` | `bool\|string` | write      | `true` cancels all remaining hooks; `'before'` or `'after'` cancels only that type (3.0.258+) |
| `options`     | `array`        | read       | The options array for this hook, including any custom data passed at hook-add time            |

### `arguments()`

Read or write individual arguments or the full argument list.

```php
// Get first argument (index 0)
$page = $event->arguments(0);

// Get argument by name (uses reflection on the hooked method's parameter names)
$page = $event->arguments('page');

// Get all arguments as a numerically indexed array
$args = $event->arguments();

// Set an argument (only meaningful in before hooks)
$event->arguments(0, $modifiedPage);
$event->arguments('page', $modifiedPage);
```

### `argumentsByName()`

Returns all arguments indexed by parameter name, or a single named argument.

```php
// All arguments indexed by name
$named = $event->argumentsByName();
// ['page' => $page, 'options' => [...]]

// Single argument by name
$page = $event->argumentsByName('page');
```

`$event->arguments('name')` is a shorter synonym for `$event->argumentsByName('name')`.

### `removeHook(null)`

Removes the current hook from inside the hook function itself.

```php
$wire->addHookAfter('Pages::saved', function(HookEvent $event) {
    // do something once, then stop
    $event->removeHook(null);
});
```

### Custom data

Any key/value pairs set on `$event` that are not standard properties are stored as custom
data and carried forward to subsequent hook events for the same method call. This lets
earlier hooks pass data to later hooks.

```php
// Before hook: stash a value
$wire->addHookBefore('Pages::save', function(HookEvent $event) {
    $event->myFlag = 'hello';
});

// After hook: read the stashed value
$wire->addHookAfter('Pages::save', function(HookEvent $event) {
    echo $event->myFlag; // "hello"
});
```

---

## Conditional hooks

Conditions can be embedded in the method argument string so a hook only fires when specified
conditions are met. Multiple conditions can be combined in a single hook.

### Object match

Append a selector in parentheses after the class name to only fire when the object matches.

```php
// Only fires when the Page being saved uses the 'product' template
$wire->addHookAfter('Page(template=product)::saved', function(HookEvent $event) {
    // ...
});
```

### Argument match

Append a selector in parentheses after the method name to only fire when argument 0 matches.

```php
// Only fires when argument 0 is an array/object that matches "template=product"
$wire->addHookAfter('Pages::find(template=product)', function(HookEvent $event) {
    // ...
});
// Example call that matches: $pages->find(['template' => 'product']);

// Match specific argument positions: 0:selector, 1:selector
$wire->addHookAfter('Pages::find(0:template=product, 1:limit=10)', function(HookEvent $event) {
    // ...
});
// Example call that matches: $pages->find(['template' => 'product'], ['limit' => 10]);
```

These selector matches apply to array or object argument values. If argument 0 is a raw
selector string like `"template=product, limit=10"`, it is not parsed and matched as a
selector by this hook syntax.

### Argument type match

Use angle brackets `<Type>` in the argument match to fire only when the argument is an
instance of the specified class. Pipe-separate multiple types for an OR match.

```php
// Only fires when the argument to Pages::saveReady() is a User, Role, or Permission
$wire->addHookAfter('Pages::saveReady(<User|Role|Permission>)', function(HookEvent $event) {
    // ...
});
```

### Return value match

A colon before the parentheses or angle brackets matches against the return value of the
method rather than an argument. Use a selector for property matching or angle brackets for
type matching.

```php
// Only fires when the returned Inputfield's label contains "Currency"
$wire->addHookAfter('Field::getInputfield:(label*=Currency)', function(HookEvent $event) {
    // ...
});

// Only fires when the returned Inputfield is a radios or checkboxes type
$wire->addHookAfter('Field::getInputfield:<InputfieldRadios|InputfieldCheckboxes>', function(HookEvent $event) {
    // ...
});
```

### Combining conditions

Object match, argument match, and return value match can all be applied in a single hook.

```php
// Field(name!=title)          — field is not named "title"
// ::getInputfield(template=product) — called in context of "product" template  
// :(label*=description)       — return value's label contains "description"
$wire->addHookAfter(
    'Field(name!=title)::getInputfield(template=product):(label*=description)',
    function(HookEvent $event) {
        // ...
    }
);
```

---

## Introspection

### `Wire::hasHook()`

Check whether a specific method or property is hooked on the current object instance.
Considers both static and local hooks, including parent class hooks.

```php
if($pages->hasHook('find()')) {
    // Pages::find() is hooked
}

if($page->hasHook('title')) {
    // Page::title property is hooked
}
```

---

## URL/path hooks

A URL/path hook fires when the current request URL matches a given path or pattern, independently
of the page tree. Path hooks are useful for custom URL endpoints, API routes, short redirects,
and similar use cases that don't require a page to exist in the content tree. They must be
attached during the `init` or `ready` boot state.

*Note: we are using the terms "URL" and "path" interchangeably here.*

A hook whose first argument starts with `/`, `!`, `@`, `#`, `%`, `.`, `(`, `[`, or `^` is
treated as a URL/path hook 

### Where to place URL/path hooks

URL/path hooks are best placed in `/site/init.php` or `/site/ready.php`. They can also be
placed in an autoload module's `init()` method or `ready()` method. The `init()` method is
preferable (when possible) because it is called earlier. 

### Examples

```php
// Exact path match
$wire->addHook('/hello/world/', function(HookEvent $event) {
    return 'Hello World';
});

// Named segments with curly braces — accessible via $event->arguments('name')
$wire->addHook('/products/{category}/{id}/', function(HookEvent $event) {
    $category = $event->arguments('category');
    $id = $event->arguments('id');
    // ...
});

// Restricted segment — only fires for "earth", "mars", or "jupiter"
$wire->addHook('/planets/(planet:earth|mars|jupiter)/', function(HookEvent $event) {
    $planet = $event->arguments('planet');
});

// Regex pattern (avoid / as delimiter since URLs use slashes)
$wire->addHook('!^/items/(\d+)/$!', function(HookEvent $event) {
    // ...
});

// Trailing slash optional — matches both /about and /about/
$wire->addHook('/about/?', function(HookEvent $event) {
    // ...
});

// Pagination — $event->pageNum holds the current page number
$wire->addHook('/news/{pageNum}', function(HookEvent $event) {
    $pageNum = $event->pageNum;
});
```
For more details on URL/path hooks please see:
<https://processwire.com/blog/posts/url-path-hooks/>

### Return values

The hook's return value determines how the request is handled.

| Return type         | Behaviour                                                         |
|---------------------|-------------------------------------------------------------------|
| `string`            | Output sent directly to browser                                   |
| `Page`              | ProcessWire renders that page                                     |
| `array`             | Converted to JSON response with appropriate headers               |
| `true`              | Hook handles output directly; ProcessWire takes no further action |
| `false` / no return | 404 response                                                      |

---

## Notes

- **Source files:** `wire/core/WireHooks/WireHooks.php`, `wire/core/WireHooks/HookEvent.php`
- **$hooks API variable:** `$hooks` is available as `wire()->hooks` or `$this->wire()->hooks`
  in modules, but direct use is rarely needed — all developer-facing hook methods live on `Wire`
- **Hookable method convention:** any method prefixed with `___` (three underscores) is hookable;
  e.g. `___find()` is the hookable implementation of `find()`
- **Default timing is "after":** `addHook()` and `addHookAfter()` both default to running after
  the method; `addHookBefore()` is an explicit opt-in for before timing
- **"Replace" hooks are before hooks:** setting `$event->replace = true` inside `addHookBefore()`
  prevents the original method from running — there is no separate "replace hook" type
- **Static hooks survive instance changes:** static hooks registered via `Class::method` fire for
  every instance of that class, including new instances created after the hook was added
