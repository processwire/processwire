# Page

Many types of content in ProcessWire are represented by the `Page` class and descending classes. 
Pages live in a tree hierarchy and carry typed field values defined by their template. 
The `$page` API variable refers to the current requested page. And the `$pages` 
API variable provides methods for retrieving pages from the tree.

Helper classes loaded by `Page` (in `wire/core/Page/`): `PageTraversal`, `PageValues`,
`PageAccess`, `PageComparison`, `PageProperties`. Custom user-defined page classes live in 
`site/classes/`. 

---

## Native properties

These properties are available on every page regardless of template:

| Property       | Type      | Description                                                |
|----------------|-----------|------------------------------------------------------------|
| `id`           | int       | Database ID (0 for unsaved pages, 0 for NullPage)          |
| `name`         | string    | URL segment name — unique among siblings                   |
| `title`        | string    | Human-readable title (custom field, almost always present) |
| `status`       | int       | Bitmask of status flags (see status constants below)       |
| `statusStr`    | string    | Space-separated string of status names active on page.     |
| `template`     | Template  | The page's template object                                 |
| `parent`       | Page      | Immediate parent page                                      |
| `parents`      | PageArray | All ancestor pages, root first                             |
| `rootParent`   | Page      | Top-level parent (child of homepage)                       |
| `children`     | PageArray | Immediate children (published, visible, with access)       |
| `path`         | string    | URL path from root, e.g. `/about/team/`                    |
| `url`          | string    | Full URL (same as path if no subdirectory install)         |
| `httpUrl`      | string    | Absolute URL including scheme and hostname                 |
| `editUrl`      | string    | Admin edit URL for this page                               |
| `created`      | int       | Unix timestamp of creation                                 |
| `createdStr`   | string    | Created date/time as ISO-8601 string                       |
| `modified`     | int       | Unix timestamp of last modification                        |
| `modifiedStr`  | string    | Last modified date/time as ISO-8601 string                 |
| `published`    | int       | Unix timestamp of publication                              |
| `publishedStr` | string    | Publication date/time as ISO-8601 string                   |
| `createdUser`  | User      | User who created the page                                  |
| `modifiedUser` | User      | User who last modified the page                            |
| `numChildren`  | int       | Number of direct children                                  |
| `sort`         | int       | Sort order relative to siblings                            |
| `sortfield`    | string    | Field used to sort children                                |

For a full list of page properties, see the phpdoc header of the `Page` class.

---

## Getting and setting values

```php
// Get a field value (output formatting ON by default in template context)
echo $page->title;
echo $page->body;
echo $page->get('title'); // same as $page->title

// Set a field value (turn off output formatting first)
$page->of(false);
$page->title = 'New Title';
$page->save('title'); // save just one field
$page->save();        // save all changed fields
$page->setAndSave('title', 'New Title'); // combined set + save 

// Get raw unformatted value regardless of output formatting state
$raw = $page->getUnformatted('body');

// Get formatted value regardless of output formatting state
$formatted = $page->getFormatted('body');

// Get multiple values at once (returns array keyed by field name)
$values = $page->getMultiple(['title', 'body', 'images']);
```

### Output formatting: of()

Output formatting determines whether field values pass through runtime text formatters
(e.g. Markdown, HTML entities, etc.) 

```php
$page->of(false); // turn OFF — use when modifying values
$page->of(true);  // turn ON  — use for front-end output (default in template context)
$current = $page->of(); // returns current state (bool) without changing it
```

**Always call `$page->of(false)` before setting and saving values.** And if you will be
making modifications to an existing value, make sure to `$page->of(false)` before getting
the value you will modify. You do not want to have a formatted value get saved in the 
database.

### Flexible get() syntax

`get()` accepts several formats beyond a plain field name:

```php
// OR-pipe: return first non-empty value among listed fields
$page->get('summary|body|title');

// Dot notation: traverse into sub-objects
$page->get('category.title');       // title of related page
$page->get('images.first.url');     // url of first image

// Bracket notation: access items within a multi-value field
$page->get('images[]');             // all items as array
$page->get('images[0]');            // first item
$page->get('images[tag=hero]');     // item matching selector

// Format string: fill a pattern with property values
$page->get('{title} ({id})');
```

---

## Saving and deleting

```php
// Save all changed fields
$page->save();

// Save a specific field only
$page->save('title');
$page->saveFields(['title', 'body']); // save several fields

// Save without updating modified user and timestamp
$page->save(['quiet' => true]);

// Set value and save in one call, works even if output formatting on 
$page->setAndSave('headline', 'Hello World'); 

// Set and save multiple fields
$page->setAndSave([
    'title' => 'It is Friday again',
    'subtitle' => 'The best day of the week is Friday',
    'body' => '<p>This is my short blog post...</p>'
]);

// Trash a page (moves to trash, recoverable)
$page->trash();

// Delete a page permanently
$page->delete();

// Delete a page and all its children
$page->delete(true);
```

---

## Traversal

### Children and descendants

When we refer to "accessible" pages below, it means pages that are published,
not hidden, and that the curent user has access to. 

```php
// Direct accessible children (published + visible + current user has access)
foreach($page->children() as $child) { ... }
$page->children('sort=title, limit=10'); // with selector

// First accessible child 
$first = $page->child();

// First created and accessible child 
$first = $page->child('sort=created');

// First created child with no exclusions (skips access and status checks)
$first = $page->child('sort=created, include=all');

// Last created child with no exclusions 
$last = $page->child('sort=-created, include=all');

// Find among all accessible descendants, like $pages->find() but scoped to this page
$results = $page->find('template=product, featured=1');
$result  = $page->findOne('sku=ABC123');

// Number of children and descendants
$n = $page->numChildren();             // number of children with no exclusions
$n = $page->numChildren;               // same as above but as property
$n = $page->numChildren(true);         // number of accessible children
$n = $page->numChildren("foo=bar");    // count of children matching selector
$n = $page->numDescendants();          // number of descendants, no exclusions
$n = $page->numDescendants(true);      // number of accessible descendants
$n = $page->numDescendants("foo=bar"); // number of descendants matching selector
```

### Parents and ancestors

All of the following method calls without arguments can also be accessed as properties,
i.e. `$page->parent()` can also be accessed as `$page->parent`. 

```php
$parent = $page->parent();                     // immediate parent 
$parents = $page->parents();                   // all ancestors, root first
$parents = $page->parents('template=section'); // filtered ancestors
$root = $page->rootParent();                   // top-level ancestor
$n = $page->numParents();                      // depth in tree
```

### Siblings

```php
$siblings = $page->siblings();                 // all siblings including self
$siblings = $page->siblings(false);            // all siblings excluding self
$siblings = $page->siblings('sort=title');     // sorted siblings (or any selector)
$siblings = $page->siblings('foo=bar', false); // filtered siblings (excluding self)

$next = $page->next();                         // next sibling
$prev = $page->prev();                         // previous sibling
$next = $page->next('template=product');       // next matching sibling

$after  = $page->nextAll();                    // all following siblings
$after  = $page->nextAll('foo=bar');           // all following siblings, filtered
$before = $page->prevAll();                    // all preceding siblings

// All siblings up to (not including) first one matching the selector
$until = $page->nextUntil('template=divider');
$until = $page->prevUntil('template=divider');
```

### Position

```php
$n = $page->index();        // 0-based position among accessible siblings
$n = $page->index(true);    // same as above but with no exclusions
```

---

## Status

### Status constants

| Constant              | Value   | Meaning                                          |
|-----------------------|---------|--------------------------------------------------|
| `statusLocked`        | 4       | Protected from editing in the admin              |
| `statusHidden`        | 1024    | Hidden from front-end listings                   |
| `statusUnpublished`   | 2048    | Unpublished — not publicly accessible            |
| `statusTrash`         | 8192    | Page is in the trash                             |
| `statusDeleted`       | 16384   | Deleted (runtime only, not saved)                |
| `statusSystemID`      | 32768   | System page that cannot be renamed               |
| `statusSystem`        | 65536   | System page that cannot be deleted or renamed    |
| `statusCorrupted`     | 536870912 | Page data is corrupt — do not save             |

### Checking status

```php
// Preferred: named methods
if($page->isHidden()) { ... }
if($page->isUnpublished()) { ... }
if($page->isLocked()) { ... }
if($page->isTrash()) { ... }
if($page->isPublic()) { ... } // published and guest-viewable

// Generic check
if($page->hasStatus(Page::statusHidden)) { ... }
if($page->hasStatus('hidden')) { ... }            // string form

// is() — checks status name, template name, or selector
if($page->is('hidden')) { ... }
if($page->is('template=product')) { ... }

// Numeric and bitwise status comparisons 
if($page->status < Page::statusUnpublished) { ... } // page is published
if($page->status < Page::statusHidden) { ... }      // page published and not hidden
if($page->status & Page::statusHidden) { ... }      // page is hidden
```

### Modifying status

```php
$page->addStatus('hidden');                     // add by name
$page->addStatus(Page::statusHidden);           // add by constant
$page->removeStatus('hidden');                  // remove by name
$page->removeStatus(Page::statusHidden);        // remove by constant

// Set status directly (replaces current status)
$page->status = Page::statusHidden | Page::statusUnpublished; // bitwise
$page->status('hidden, unpublished');                         // string form
$page->status(['hidden', 'unpublished']);                     // array form

// Get array of current status names
$names = $page->status(true); // e.g. ['hidden', 'unpublished']
```

---

## Matching and comparison

```php
// Test page against a selector in memory (no DB query)
if($page->matches('created>=today')) { ... }
if($page->matches('template=product, featured=1')) { ... }

// Test page against a selector via database query
if($page->matchesDatabase('title=Hello')) { ... }

// Conditional output shorthand
echo $page->if('featured', '<b>Featured</b>');
echo $page->if('featured=1', '<b>Yes</b>', '<b>No</b>');
```

---

## URL and path methods

The following may be accessed as methods or properties. 

```php
$page->url();                 // URL path (e.g. /about/team/)
$page->path();                // same as url in most installs
$page->httpUrl();             // with scheme+host (e.g. https://example.com/about/team/)
$page->editUrl();             // admin edit URL

// Get all URL variants (useful for language or multi-domain setups)
$urls = $page->urls();        // returns object with url, httpUrl, edit, etc.

// urlOptions used internally by traversal methods
```

---

## Field introspection

```php
// Does the page's template include this field?
if($page->hasField('body')) { ... }

// Get the Field object for a field
$field = $page->getField('body');

// Get all Field objects for this page's template
$fields = $page->getFields();
foreach($fields as $field) { echo $field->name; }
```

---

## Access and permissions

The following methods are added at runtime by the `PagePermissions` module (installed
by default). They respect the current `$user` and the page's access template.

```php
if($page->viewable()) { ... }         // is page viewable by current user?
if($page->editable()) { ... }         // is page editable by current user?
if($page->publishable()) { ... }      // can current user publish this page?
if($page->listable()) { ... }         // is page listable by current user?
if($page->deleteable()) { ... }       // can current user delete this page?
if($page->trashable()) { ... }        // can current user trash this page?
if($page->addable()) { ... }          // can current user add children?
if($page->addable('product')) { ... } // can current user add a child of given template?
if($page->moveable()) { ... }         // can current user move this page?
if($page->sortable()) { ... }         // can current user change sort of this page?
if($page->cloneable()) { ... }        // can current user clone this page?
```

Lower-level access methods (always available):

If a page's template does not define access control then the page will inherit its 
access control from one of its parents. The following methods return the page that 
the current one inherits its access control from, whether the page itself, or a parent. 

```php
$accessTemplate = $page->getAccessTemplate();    // Template that controls access
$accessParent   = $page->getAccessParent();      // Page that provides access settings
$roles          = $page->getAccessRoles();       // WireArray of Roles with view access
if($page->hasAccessRole($role)) { ... }
```

---

## Output and rendering

```php
// Render page using its template file (returns string)
echo $page->render();

// Render a field using a site/templates/fields/ file
echo $page->renderField('body');
echo $page->renderField('images', 'gallery'); // with custom file
echo $page->render('images');                 // alias of renderField

// Render field via property syntax
echo $page->render->images;
echo $page->_images_;               // double-underscore shorthand

// Get markup for a field (uses Fieldtype::markupValue)
echo $page->getMarkup('body');

// Get plain text (applies entity decoding when output formatting on)
$text = $page->getText('body');
$text = $page->getText('body', true); // require 1-line value 
$text = $page->getText('body', true, false); // 1-line value, entities off

// Front-end editable field (requires PageFrontEdit module)
echo $page->edit('body');

// getMarkup() with format string — fills {tag} placeholders from page fields
echo $page->getMarkup('{title} — {summary}');
```

---

## Page references and links

```php
// Pages that reference this page via a Page-type field
$refs = $page->references();
$refs = $page->references('template=news');     // filtered

// Pages that this page references via Page-type fields
$linking = $page->referencing();
$linking = $page->referencing('template=category');

// Pages linked from this page's text/HTML content (where possible to detect)
$linked = $page->links();
$linked = $page->links('template=product');
```

---

## Preloading

```php
// Preload one or more field values into this page (avoids repeated lazy-load queries)
$page->preload('body');
$page->preload(['body', 'images', 'sidebar']);
```

---

## NullPage

When `$pages->get()` or traversal methods find no result, they return a `NullPage`
rather than `null`. A `NullPage` has `id === 0`.

```php
$page = $pages->get('template=product, sku=MISSING');
if($page->id) {
    // found
} else {
    // not found — $page is a NullPage
}

if($page instanceof NullPage) {
    // not found — $page is a NullPage
}

// Do NOT do:
if($page) { ... }      // Page object is always truthy — even NullPage
```

## Custom page classes

To implement your own Page type, create a class file in `/site/classes/` using the
following format: `[TemplateName]Page.php` . For example:

| Template     | Class           | File                             |
|--------------|-----------------|----------------------------------|
| `product`    | `ProductPage`   | `site/classes/ProductPage.php`   |
| `basic-page` | `BasicPagePage` | `site/classes/BasicPagePage.php` |
| `home`       | `HomePage`      | `site/classes/HomePage.php`      |
| `blog-post`  | `BlogPostPage`  | `site/classes/BlogPostPage.php`  |

The custom class should be in the `ProcessWire` namespace and extend `Page` or
another class that extends `Page`. For example:

```php
<?php namespace ProcessWire;

/**
 * Product Page
 * 
 * @property string $title
 * @property int $qty_available
 * @property PageArray $categories
 * @property Pageimages $photos
 * @property string $body
 * 
 */
class ProductPage extends Page {
    // example of custom function
    public function inStock() {
      return $this->qty_available > 0; 
    }
}
```
Once implemented, all pages using the template named `product` will be 
instantiated as `ProductPage` instances rather than `Page` instances. 

We recommend documenting the fields used by the template in the custom page
class phpdoc header, as shown in the example above. 

Note that to use custom page classes you must have the following in 
your `/site/config.php` file (enabled by default on new installations):
```php
$config->usePageClasses = true;
```

## Identifying page type

A page's type is typically identified by its template…
```php
if($page->template->name === 'product') {
    // Page is a 'product' page
}
```
…but when using custom page classes, you can also identify a page by its class type:
```php
if($page instanceof ProductPage) {
    // Page is a 'product' page
}
```

## Other core page types

In addition to the `Page` class, the core uses Page objects for the following classes, which descend
from the Page class: 

| Class        | Description                                                                | 
|--------------|----------------------------------------------------------------------------|
| `User`       | Represents users in the system, with `$user` being the current user.       |
| `Role`       | Represents user roles, assigned to users and templates for access control. |
| `Permission` | Individual named permission that are assigned to Role pages.               |
| `Language`   | When multi-language support is installed, represents a language.           |

---
## Hookable page methods

Hook before or after any of these methods using `$wire->addHookBefore('Page::methodName', …)`
or `$wire->addHookAfter('Page::methodName', …)`. Hook the class to intercept all pages, or
hook a specific instance to intercept just one page. You can also hook a custom page class to 
capture only instances of that page type, i.e. `$wire->addHookAfter('ProductPage::added', …)`. 

| Method | Description |
|---|---|
| `getUnknown($key)` | Called when getting an unrecognized property; hook after and set `$event->return` to inject custom property values |
| `path()` | Returns the page's URL path; hook after to rewrite or modify the path |
| `loaded()` | Called after the page is fully loaded from the database |
| `editReady(InputfieldWrapper $form)` | Called when page is loaded into the admin page editor; hook to modify the editing form |
| `saveReady(array $changes, $name)` | Called right before the page is saved; `$name` is a field name string when saving a single field, or `false` when saving the whole page |
| `saved(array $changes, $name)` | Called right after the page is saved; same `$name` convention as `saveReady` |
| `addReady()` | Called when a new page is about to be saved to the database for the first time |
| `added()` | Called after a new page has been saved to the database for the first time |
| `moveReady(Page $oldParent, Page $newParent)` | Called when the page is about to be moved to a different parent |
| `moved(Page $oldParent, Page $newParent)` | Called after the page has been moved to a different parent |
| `deleteReady()` | Called right before the page is permanently deleted |
| `deleted()` | Called after the page has been permanently deleted |
| `cloneReady(Page $copy)` | Called right before the page is cloned; `$copy` is the new page being created |
| `cloned(Page $copy)` | Called right after the page has been cloned; `$copy` is the new page |
| `renameReady(string $oldName, string $newName)` | Called right before the page's name is changed |
| `renamed(string $oldName, string $newName)` | Called right after the page's name has been changed |
| `addStatusReady(string $name, int $value)` | Called when a status flag is about to be added but not yet saved; hook before to cancel or modify |
| `addedStatus(string $name, int $value)` | Called after a status flag has been added and saved |
| `removeStatusReady(string $name, int $value)` | Called when a status flag is about to be removed but not yet saved; hook before to cancel or modify |
| `removedStatus(string $name, int $value)` | Called after a status flag has been removed and saved |

For a full list of hookable Page methods, see the phpdoc header of the `Page` class.

---

## Notes

- `$page->of(false)` must be called before setting or saving field values; skipping it
  can corrupt formatted values (e.g. saving HTML-encoded entities back to the DB).
- `$page->children()`, `$page->find()` and similar methods exclude hidden, unpublished, and inaccessible
  pages by default. Add `include=hidden`, `include=unpublished`, or `include=all` to
  the selector to override.
- Status is a bitmask integer. Multiple statuses are combined with `|`: 
  `Page::statusHidden | Page::statusUnpublished`.
- `statusCorrupted` is a runtime-only flag. If present, ProcessWire refuses to save the
  page to protect against writing corrupt data.
- The `Page` class delegates most of its method implementations to helper classes in
  `wire/core/Page/`. The main class file is `wire/core/Page/Page.php`.
