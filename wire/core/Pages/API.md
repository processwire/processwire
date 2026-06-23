# Pages / $pages

The `$pages` API variable loads, creates, saves and deletes Page objects to and from the database.
It is the most frequently used API variable in ProcessWire. This document covers the most commonly
used methods of `$pages` but it does not cover them all. See `/wire/core/Pages/Pages.php` and 
in `/wire/core/Pages/*.php` phpdoc for full methods reference, or the online 
[Pages API reference](https://processwire.com/api/ref/pages/#api-reference). 

## Finding pages

```php
// Find all pages matching a selector — returns PageArray
$items = $pages->find("template=blog-post, sort=-created, limit=10");

// Find one page — applies same access/status filtering as find()
$p = $pages->findOne("template=blog-post, sort=random");

// Get a page with no access/status exclusions
$p = $pages->get(1234);           // by ID
$p = $pages->get('/about/');      // by path
$p = $pages->get('name=contact'); // by selector

// Returns NullPage (id=0) when nothing found — always check before use
if($p->id) { /* found */ }

// Count matching pages without loading them
$n = $pages->count("template=blog-post, date>=2024-01-01");

// Check existence with no access/status exclusions
// Returns first matching page ID or 0 if not found
$id = $pages->has("template=blog-post, name=foo");

// Iterate any number of pages without running out of memory
foreach($pages->findMany("template=product") as $p) {
    // pages are loaded/unloaded in chunks as you iterate
}

```

### Selectors

Selector format: `field=value` — combine multiple with commas for AND match:

```php
$pages->find("template=blog-post, date>=2024-01-01, title~=processwire");
```
Use pipe `|` on field for OR-field matches or value for OR-value matches:
```php
// match word "processwire" in title, summary or body
$pages->find("title|summary|body~=processwire"); 

// match word "process" or "wire" or "processwire" in title
$pages->find("title~=process|wire|processwire"); 
```

Most common operators: `=`, `!=`, `<`, `<=`, `>`, `>=`, `~=` (contains word)
`%=` (contains using LIKE), `*=` (contains using index), `^=` (starts), `$=` (ends). 
See [Selector Operators](https://processwire.com/docs/selectors/operators/) for a full list. 

Include modes:

```php
$pages->find("template=blog-post, include=hidden");      // include hidden
$pages->find("template=blog-post, include=unpublished"); // include unpublished + hidden
$pages->find("template=blog-post, include=all");         // include all + bypass access control
```

### find() modifiers in selector

Common selector modifiers for `find()`, `findOne()`, `get()`, and related methods:

| Option           | Default | Description                                                                       |
|------------------|---------|-----------------------------------------------------------------------------------|
| `include=status` | —       | Status may be `hidden`, `unpublished`, or `all` — include non-visible pages       |
| `limit=n`        | `0`     | Max pages to return or `0` for no limit (also enables `$items->getTotal()` count) |
| `start=n`        | `0`     | Pagination offset/start value                                                     |
| `sort=name`      | —       | Field name to sort by, prefix name with `-` to reverse                            |
| `join=fields`    | —       | Fields should be pipe-separated list of field names to autojoin (where supported) |

### Specialized find methods

```php
// Returns IDs only (faster when you don't need Page objects)
$ids = $pages->findIDs("template=product");

// Verbose: returns array of ['id', 'parent_id', 'templates_id'] per match
$ids = $pages->findIDs("template=product", true);

// Specify which fields to autojoin (overriding field autojoin settings)
$posts = $pages->findJoin("template=blog-post", ['title', 'date', 'summary']);

// No autojoin at all
$posts = $pages->findJoin("template=blog-post", false);

// Raw DB values — no Page objects, no output formatting
$a = $pages->findRaw("template=blog-post", ['title', 'date']);
$a = $pages->getRaw(1234, ['title', 'summary']);

// Get a non-cached copy of a page (fresh from DB, skips memory cache)
$copy = $pages->getFresh($page);
```

## Creating pages

Please note when creating new pages that the created page does not have
unpublished status unless you specifically assign `status` of "unpublished" 
(aka `Page::statusUnpublished`) in the method arguments.

```php
// Quick method — template, parent, optional name/title/values
$p = $pages->add('blog-post', '/blog/', 'My First Post');

// With field values
$p = $pages->add('blog-post', '/blog/', [
    'title' => 'My First Post',
    'body'  => 'Hello world.',
    'status' => 'unpublished',
]);

// Selector-style interface — saves to DB immediately
$p = $pages->new("template=blog-post, parent=/blog/, title=My First Post");
$p = $pages->new('/blog/my-first-post'); // path implies parent+name+template (if family allows)
$p = $pages->new([
    'template' => 'blog-post',
    'parent'   => '/blog/',
    'title'    => 'My First Post',
    'status'   => 'unpublished',
]);

// Create a Page object without saving to DB
$p = $pages->newPage(['template' => 'blog-post', 'parent' => '/blog/']);

// Published by default:
$p = $pages->add('product', '/products/', 'Foo Bar Widget');

// Explicitly unpublished:
$p = $pages->add('product', '/products/', [
    'title' => 'Page Title', 
    'status' => Page::statusUnpublished // or string 'unpublished'
]);
```
`$pages->new()` auto-detects template from parent family settings (and vice versa), derives
`name` from `title` or `path`, and appends a numeric suffix if the name is already taken.

The above examples using `new()` or `add()` methods are a convenient shorthand for creating a page manually 
(the old fashioned way), like this:
```php
$p = new Page();
$p->template = 'product'; 
$p->parent = '/blog/';
$p->title = 'My first post';
$p->name = 'my-first-post';
$p->addStatus('unpublished');
$pages->save($p); // or $page->save()
```

## Saving pages

```php
$p = $pages->get(1234);
$p->of(false);               // turn off output formatting before editing
$p->title = 'Updated title';
$pages->save($p);            // or: $p->save()

// Save only specific fields (faster)
$pages->saveField($p, 'title');
$pages->saveFields($p, ['title', 'body', 'summary']); // 3.0.242+
$pages->saveFields($p, 'title, body, summary');        // CSV string also ok

// Clone a page (recursive by default — also clones children and file assets)
$copy = $pages->clone($p);
$copy = $pages->clone($p, $newParent, false); // non-recursive

// Update modification time to now
$pages->touch($p);

// Set sort order
$pages->sort($p, 3); // move to position 3 among siblings
$pages->insertBefore($p, $beforePage);
$pages->insertAfter($p, $afterPage);
```

### save() options

| Option              | Default  | Description                                                         |
|---------------------|----------|---------------------------------------------------------------------|
| `uncacheAll`        | `true`   | Clear memory cache after save                                       |
| `resetTrackChanges` | `true`   | Reset page's change tracking                                        |
| `quiet`             | `false`  | Skip updating modified date/user                                    |
| `adjustName`        | `true`   | Auto-adjust name to be unique within parent                         |
| `ignoreFamily`      | `false`  | Bypass family/parent restriction checks                             |
| `noHooks`           | `false`  | Skip before/after save hooks                                        |
| `noFields`          | `false`  | Save only native page properties, not fields                        |
| `saveAll`           | `true`   | Call savePageField() on all fields, even those not marked changed. 3.0.265+ |
| `throw`             | `true`   | Throw exceptions on errors rather than returning false. 3.0.265+   |
| `getVerbose`        | `false`  | Return a verbose array describing the save instead of a bool. 3.0.265+ |

When `getVerbose` is `true`, `$pages->save()` returns an array instead of a boolean:

| Key             | Type     | Description                                              |
|-----------------|----------|----------------------------------------------------------|
| `result`        | `bool`   | Whether the save succeeded                               |
| `id`            | `int`    | Page ID after save                                       |
| `path`          | `string` | Page path after save                                     |
| `isNew`         | `bool`   | Whether this was a newly created page                    |
| `user`          | `string` | Name of the user who performed the save                  |
| `changes`       | `array`  | Field names that were changed (from `$page->getChanges()`) |
| `savedFields`   | `array`  | Field names that were saved to the database              |
| `changedFields` | `array`  | Field names detected as changed during save              |
| `skippedFields` | `array`  | Field names skipped (not saved)                          |
| `natives`       | `array`  | Native page column values written to the `pages` table   |
| `messages`      | `array`  | Log of informational messages from the save process      |
| `errors`        | `array`  | Errors encountered during the save                       |
| `hooks`         | `array`  | Hook methods that were called during the save            |
| `options`       | `array`  | The resolved options array used for the save             |

```php
$result = $pages->save($p, ['getVerbose' => true]);

if($result['result']) {
    echo "Saved page $result[id] at $result[path]\n";
    echo "Fields saved: " . implode(', ', $result['savedFields']) . "\n";
} else {
    echo "Save failed: " . implode(', ', $result['errors']) . "\n";
}
```

## Deleting and trashing pages

```php
// Move to trash (recoverable)
$pages->trash($p);

// Restore from trash to original location
$pages->restore($p);

// Empty the entire trash (permanent)
$pages->emptyTrash();

// Permanently delete (non-recoverable)
$pages->delete($p);
$pages->delete($p, true); // recursive — also deletes children
```

## Cache

ProcessWire maintains an in-memory cache of loaded pages keyed by ID. This cache is consulted
before hitting the database on any `get()`/`find()` call.

```php
// Remove specific pages from the memory cache
$pages->uncache($p);
$pages->uncache($pageArray);
$pages->uncache([1234, 1235, 1236]); // array of IDs (3.0.259+)

// Clear entire memory cache — useful when processing large sets
$pages->uncacheAll();
```

`uncacheAll()` is typically used inside pagination loops that process thousands of pages, so that
previous chunks can be freed:

```php
$start = 0;
$limit = 200;
do {
    $chunk = $pages->find("template=product, start=$start, limit=$limit");
    if(!$chunk->count()) break;
    foreach($chunk as $p) { /* process */ }
    unset($chunk);
    $pages->uncacheAll();
    $start += $limit;
} while(true);
```

## Creating page/array instances

```php
$p    = $pages->newPage();                      // new unsaved Page (no template)
$p    = $pages->newPage(['template' => 'foo']); // with template/parent pre-set
$pa   = $pages->newPageArray();                 // empty PageArray
$null = $pages->newNullPage();                  // new NullPage instance
```

## Hooks

Useful hooks on `$pages`:

| Hook                          | When fired                                        |
|-------------------------------|---------------------------------------------------|
| `Pages::found`                | After find completes, receives the full PageArray |
| `Pages::saveReady`            | Just before a page is saved                       |
| `Pages::saved`                | After a page is successfully saved                |
| `Pages::savePageOrFieldReady` | Before a page or a field is saved                 |
| `Pages::savedPageOrField`     | After a page or a field is saved                  |
| `Pages::addReady`             | Before a new page is added                        |
| `Pages::added`                | After a new page is added                         |
| `Pages::deleteReady`          | Before a page is deleted                          |
| `Pages::deleted`              | After a page is deleted                           |
| `Pages::trashReady`           | Before a page is trashed                          |
| `Pages::trashed`              | After a page is trashed                           |
| `Pages::restoreReady`         | Before a page is restored from trash              |
| `Pages::restored`             | After a page is restored from trash               |
| `Pages::cloneReady`           | Before a page is cloned                           |
| `Pages::cloned`               | After a page is cloned                            |

Other useful `Pages` hooks include: `moveReady`, `moved`, `sorted`, 
`templateChanged`, `renameReady`, `renamed`, `publishReady`, `published`, 
`unpublishReady`, `unpublished`, `saveFieldReady`, and `savedField`.
In addition, all `$pages` write methods can also be hooked directly with 
`addHookBefore()` or `addHookAfter()`.


```php
// Example: update field value and status on every newly added page
$wire->addHookAfter('Pages::added', function(HookEvent $e) {
    $page = $e->arguments(0); /** @var Page $page */
    if($page->template->name === 'blog-post') {
        $page->categories->add('/categories/pending-review');
        $page->addStatus('hidden');
        $page->save();
    }
});

// $pages can also be hooked directly like this
$pages->addHookAfter('added', function(HookEvent $e) {
    // ...
}); 
```

## Helper classes (wire/core/Pages/)

The `$pages` object delegates to several helper classes. These are lazy-loaded and 
should not be accessed directly from the public API unless they provide a method not 
available on `$pages`, or if otherwise directed. 

| Property             | Class               | Purpose                              |
|----------------------|---------------------|--------------------------------------|
| `$pages->loader`     | `PagesLoader`       | All find/get/load operations         |
| `$pages->editor`     | `PagesEditor`       | save, add, delete, clone             |
| `$pages->cacher`     | `PagesLoaderCache`  | In-memory page cache                 |
| `$pages->trasher`    | `PagesTrash`        | trash, restore, emptyTrash           |
| `$pages->names`      | `PagesNames`        | Page name generation and uniqueness  |
| `$pages->raw`        | `PagesRaw`          | findRaw / getRaw                     |
| `$pages->pathFinder` | `PagesPathFinder`   | Path-to-page resolution              |
| `$pages->request`    | `PagesRequest`      | Current HTTP request page resolution |
| `$pages->parents`    | `PagesParents`      | Ancestor/parent tree queries         |
| `$pages->porter`     | `PagesExportImport` | Import/export                        |

## Notes

- `$pages->get()` never excludes pages by status or access — it returns whatever matches,
  including hidden, unpublished, and admin pages. Use `$pages->findOne()` if you need
  access/status filtering.
- Output formatting is **on** for front-end requests and **off** in the admin. Always call
  `$page->of(false)` before modifying and saving a page that may have been loaded in a
  front-end context.
- `$pages->newNullPage()` returns a new NullPage instance.
- All `$pages` write methods (`save`, `add`, `delete`, `trash`, etc.) are hookable via the
  triple-underscore `___methodName()` pattern.
