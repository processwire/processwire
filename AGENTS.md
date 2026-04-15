# ProcessWire – AI Agent Orientation

ProcessWire is a PHP CMS/CMF built around a flexible page tree where every page can carry
any set of fields. It has a clean, consistent API designed to be readable and predictable.
This file orients AI agents working on ProcessWire-based projects.

---

## Core Concepts

| Concept | Description |
|---|---|
| **Page** | Every piece of content is a `Page` object in a tree hierarchy. |
| **Template** | Defines which fields a page has, and maps to a PHP template file in `site/templates/`. |
| **Field** | A named data container (text, image, page reference, etc.) assigned to templates. |
| **Fieldgroup** | The set of fields attached to a template (usually same name as template). |
| **Module** | Plugin class that extends ProcessWire. Autoloaded or loaded on demand. |
| **Wire** | Base class for most PW objects; provides access to the API via `$this->wire()`. |

---

## The API

The ProcessWire API is available globally in template files and as `$wire` in other contexts.
Key API variables:

```php
$pages      // Page retrieval and manipulation
$page       // The current page (in template context)
$fields     // All fields defined in the system
$templates  // All templates defined in the system
$modules    // Module loading and management
$user       // The current user
$users      // All users (find, get, save)
$input      // GET/POST/URL input
$sanitizer  // Value sanitization
$session    // Session read/write, flash messages
$cache      // WireCache — store and retrieve cached values
$database   // WireDatabasePDO — direct database access
$datetime   // Date formatting and parsing
$files      // File system operations (copy, move, mkdir, etc.)
$log        // WireLog — write to log files
$languages  // Installed languages (present only if LanguageSupport module is installed)
$config     // System configuration, paths, URLs
```

### Accessing API variables

The following examples all access the `$pages` API variable   
(substitute any API variable for `$pages` in this section):
```php
$pages->get('/'); // when $pages in scope (most common usage)
pages()->get('/'); // supported if $config->useFunctionsAPI===true; self-documenting
wire()->pages->get('/'); // always in scope, self-documenting
wire('pages')->get('/'); // always in scope
$foo->wire()->pages->get('/'); // where $foo is any Wire-derived class instance
$this->wire()->pages->get('/'); // inside any Wire-derived class instance
```
Custom API variables created by the user or a /site/modules/ module might not be available 
as a `varName()` function, even if `$config->useFunctionsAPI` is `true`.

When in scope, it is preferable to use `$pages` or `$this->wire()->pages`, as they
are more likely to be connected to the correct ProcessWire instance in a multi-instance
environment (if used). 

When inside a Wire-derived class instance, give preference to `$this->wire()->pages`. 
For multiple API calls from the same API variable in the same method (especially in loops), 
consider creating a locally scoped API variable like `$pages = $this->wire()->pages;` at 
the top of the method, and use the locally scoped variable, being careful not to 
overwrite it. 

---

## Finding Pages

ProcessWire uses selector strings to find pages — similar to CSS selectors but for database queries:

```php
// Find pages by template
$items = $pages->find('template=product, sort=-created, limit=10');

// Find by field value
$items = $pages->find('template=product, price<100, stock>0');

// Fulltext search
$items = $pages->find('template=product, body*=organic');

// Find by template + include all (hidden/unpublished/no-access)
$items = $pages->find("template=product, include=all"); 

// Find a single page, excludes hidden/unpublished/no-access pages, like find() 
$product = $pages->findOne('template=product, sku=ABC123');

// Get a single page, also includes hidden/unpublished/no-access pages
$product = $pages->get('template=product, sku=ABC123');

// Get by path or ID, also works with get() and find() methods
$about = $pages->findOne('/about/');
$page  = $pages->findOne(1234);
```

See `wire/core/Selectors.php` and `wire/core/PageFinder.php` for selector internals.

Note that `$pages->find()` and `$pages->findOne()` excludes hidden, unpublished,
and pages that user lacks access to. This can be overridden by providing 
`include=[mode]` in your selector, where `[mode]` is: `hidden`, `unpublished`, `trash`, 
or `all` (no exclusions). 

---

## Working with Field Values

```php
// Read field values
echo $page->title;
echo $page->body;
echo $page->price;

// Images and files
$image = $page->images->first();
echo $image->url;
echo $image->size(800, 600)->url;

foreach($page->images as $img) {
    $t = $img->width(200); 
    echo "<a href='$img->url'>" . 
         "<img src='$t->url' alt='$t->description' width='$t->width'>" . 
         "</a>";
}

// Page reference fields (single)
$category = $page->category;  // returns a Page
echo $category->title;

// Page reference fields (multiple: returns PageArray)
foreach($page->related_products as $related) {
    echo $related->title;
}

// Page reference fields (using 'each' syntax of PageArray)
echo $page->related_projects->each("<li><a href='{url}'>{title}</a>"); 

// Save a field value
$page->of(false);  // turn off output formatting before setting values
$page->title = 'New Title';
$page->save('title');  // save a single field
// or
$page->save();  // save all changed fields
// or
$page->setAndSave('title', 'New Title'); // combined set and save
```

---

## API Documentation for Field Types

Each core Fieldtype has an `API.md` in its module directory:

```
wire/modules/Fieldtype/FieldtypeText/API.md
wire/modules/Fieldtype/FieldtypeImage/API.md
wire/modules/Fieldtype/FieldtypePage/API.md
wire/modules/Fieldtype/FieldtypeRepeater/API.md
... (one per Fieldtype subdirectory)
wire/modules/Fieldtype/API.md  (FieldtypeFieldset* — no subdirectory)
```

First-party non-core Fieldtype modules (in `site/modules/`) also have `API.md` files
in their module directory when available. Third-party modules may or may not include them.

Most Fieldtype modules also have a `[Type]Field.php` class (e.g. `TextField.php`, `ImageField.php`)
with `@property` PHPDoc annotations for all configurable field settings — useful for
understanding what settings a field type supports.

---

## Hooks

ProcessWire uses a hook system for extending and modifying behavior:

```php
// Hook after Pages::save
$wire->addHookAfter('Pages::save', function(HookEvent $event) {
    $page = $event->arguments(0);
    // do something after any page is saved
});

// Hook a specific method's return value
$wire->addHookAfter('Page::render', function(HookEvent $event) {
    $event->return .= '<!-- rendered -->';
});

// Before hook (can modify arguments or cancel)
$wire->addHookBefore('Pages::delete', function(HookEvent $event) {
    $page = $event->arguments(0);
    if($page->template == 'protected') $event->replace = true; // cancel
});
```

- Hookable methods are prefixed with `___` in the source (e.g. `___save()` is hookable as `save`).
- In order to support hooks, a class must extend the `Wire` class or one of its descending classes. 
- Hookable methods are documented in the phpdoc header of each PHP class file with `@method` tags. 
  For example, see the phpdoc header of wire/core/Pages.php

---

## Orienting on a Site

When working on an existing ProcessWire site, start here:

1. **`site/templates/`** — PHP template files; one per template, named `{template}.php`.
2. **`site/modules/`** — Installed or installable non-core modules (third-party + custom).
3. **`site/config.php`** — Database credentials, debug mode, and site configuration.
4. **Admin → Setup → Templates** — Which fields each template has.
5. **Admin → Setup → Fields** — All fields and their types.

If the [AgentTools](https://github.com/ryancramerdesign/AgentTools) module is installed,
run its `--at-sitemap-generate` CLI command to produce `site/assets/at/site-map.json` — a single JSON
file describing all templates, fields, and a sample of the page tree. You may also want to run its
`--at-sitemap-generate-schema` command, which generates a `site/assets/at/site-map-schema.json` file
covering the details of fields and templates. This is the fastest way to orient on an unfamiliar site.

AgentTools syntax (index.php is ProcessWire’s root index file):
~~~~
php index.php --at-sitemap-generate
php index.php --at-sitemap-generate-schema
~~~~

---

## Common Patterns

```php
// Check if page has a field with a value
if($page->images->count()) { ... } // multiple images field
if($page->image) { ... } // single image field
if($page->get('body')) { ... } // textarea field
if($page->body) { ... } // alias of above

// Render a list of child pages
foreach($page->children('sort=title') as $child) {
    echo "<li><a href='$child->url'>$child->title</a></li>";
}

// Render a list of child pages (alternate syntax)
echo $page->children('sort=title')
    ->each("<li><a href='{url}'>{title}</a></li>"); 

// Create a new page
$p = new Page();
$p->template = 'product';
$p->parent = $pages->get('/products/'); // or: $p->parent = '/products/';
$p->title = 'New Product';
$p->save();

// Create a new page (alternate syntax, includes save)
$p = $pages->new([
    'template' => 'product',
    'parent' => '/products/',
    'title' => 'New product', 
]); 

// Output formatting
$page->of(false);  // off — raw values (use when setting and saving values)
$page->of(true);   // on  — formatted values for output (default in template context)
```
