# PagePathHistory

Tracks historical URLs for pages and automatically performs 301 (permanent) redirects
when a past URL is requested. Whenever a page is moved or renamed, PagePathHistory records
the previous URL and redirects visitors to the new location — preventing broken links and
preserving SEO value.

```php
// The module is autoloaded and available via $modules:
$pathHistory = $modules->get('PagePathHistory');

// Or from any Wire-derived object:
$pathHistory = $wire->modules->get('PagePathHistory');
```

PagePathHistory hooks into `Pages::moved`, `Pages::renamed`, and `ProcessPageView::pageNotFound`
automatically. Most of the time you don't need to call it directly — it works silently in the
background. Use the API methods when you need to query history, manually record paths, or
integrate custom redirect logic.

---

## Properties

| Property      | Type          | Default | Description                                                              |
|---------------|---------------|---------|--------------------------------------------------------------------------|
| `minimumAge`  | `int`         | `120`   | Minimum age in seconds a page must have before its previous path is recorded. |
| `rootSegments`| `array\|bool` | `false` | Cached list of all root-level path segments ever recorded. Automatically rebuilt. |

---

## Constants

| Constant        | Value                  | Description                                                  |
|-----------------|------------------------|--------------------------------------------------------------|
| `dbTableName`   | `'page_path_history'`  | Name of the database table used by this module               |
| `minimumAge`    | `120`                  | Default minimum age (seconds) before tracking a page's path  |
| `maxSegments`   | `10`                   | Maximum path segments for recursive lookups                  |

---

## Retrieval

### getPathHistory($page, $options = [])

Returns an array of all paths a page has previously had, ordered oldest to newest.

```php
// Get all historical paths for a page as strings
$paths = $pathHistory->getPathHistory($page);
// ['/old-blog/my-post/', '/blog/old-slug/', '/blog/my-post/']

// Limit to a specific language
$paths = $pathHistory->getPathHistory($page, $languages->get('de'));

// Get verbose info (dates, languages)
$paths = $pathHistory->getPathHistory($page, ['verbose' => true]);
foreach($paths as $p) {
    echo $p['path'] . ' — ' . $p['date'] . "\n";
}

// Shortcut: boolean true enables verbose mode
$paths = $pathHistory->getPathHistory($page, true);
```

**Options array:**

| Option     | Type                         | Default | Description                                               |
|------------|------------------------------|---------|-----------------------------------------------------------|
| `language` | `Language\|int\|string`      | `null`  | Limit results to this language. `null` = all languages.   |
| `verbose`  | `bool`                       | `false` | Return associative array with `path`, `date`, and `language` keys. |
| `virtual`  | `bool`                       | `true`  | Include auto-determined virtual entries from parent history. |

When `virtual` is `true` (default), the method also includes paths resulting from parent
page moves — URLs that would have been valid for the page even though the page itself
didn't move. This gives a complete picture of all URLs that once led to the page.

### getPathInfo($path, array $options = [])

Returns detailed information about a path if it exists in the history. Returns an
associative array with the following keys:

| Key             | Type     | Description                                                    |
|-----------------|----------|----------------------------------------------------------------|
| `id`            | `int`    | ID of the matched page, or `0` if no match                     |
| `path`          | `string` | The matched path                                               |
| `language_id`   | `int`    | Language ID, if applicable                                     |
| `templates_id`  | `int`    | Template ID of the matched page                                |
| `parent_id`     | `int`    | Parent ID of the matched page                                  |
| `status`        | `int`    | Status flags of the matched page                               |
| `created`       | `string` | ISO-8601 date string when the entry was created                |
| `name`          | `string` | Page name (in default language)                                |
| `matchType`     | `string` | `'exact'` for exact match, `'partial'` for URL segments match, or `''` for no match |
| `urlSegmentStr` | `string` | URL segments portion of the path (only for partial matches)    |

```php
$info = $pathHistory->getPathInfo('/old-url/');

if($info['id']) {
    echo "Found page ID {$info['id']} at {$info['path']}\n";
}

// Allow partial matches with URL segments
$info = $pathHistory->getPathInfo('/old-url/segment1/segment2/', [
    'allowUrlSegments' => true
]);
if($info['matchType'] === 'partial') {
    echo "URL segments: {$info['urlSegmentStr']}\n";
}
```

Only pages with templates that allow URL segments are considered for partial matches.

### getPage($path, $level = 0)

Given a historical path, returns the `Page` object that once lived there, or a `NullPage`
if no match is found. If the path is language-specific, the returned page will have a
`_language` property set to the corresponding `Language` object.

```php
$page = $pathHistory->getPage('/old-blog/my-post/');

if($page->id) {
    // Check if this redirect is for a specific language
    $language = $page->get('_language');
    if($language) {
        echo "Redirecting to {$language->title} version: {$page->url}\n";
    } else {
        echo "Redirecting to: {$page->url}\n";
    }
}
```

This method traverses path segments recursively (up to `maxSegments` levels) to find
matches even when parent paths have changed multiple times.

### getRootSegments()

Returns an array of all root-level segments (first path component after `/`) that have
ever appeared in the path history. Useful for efficient preliminary checks before
performing deeper lookups.

```php
$segments = $pathHistory->getRootSegments();
// ['blog', 'about', 'products', 'old-site', …]
```

### isRootSegment($segment)

Returns `true` if the given segment has ever existed as a root-level path component
in the history. Accepts either a single segment or a full path (the root segment is
extracted automatically).

```php
if($pathHistory->isRootSegment('blog')) {
    // 'blog' has existed at the root level before
}

// Also accepts full paths:
if($pathHistory->isRootSegment('/old-site/some/page')) {
    // '/old-site/' is a known root segment
}
```

---

## Manipulation

### setPathHistory(Page $page, $path, $language = null)

Records a historical path for a page and removes any existing entries for the page's
*current* path (since those are no longer applicable). Returns `true` on success,
or `false` if the path is already consumed in history.

```php
// Record a path for the default language
$pathHistory->setPathHistory($page, '/old-path/');

// Record a language-specific path
$pathHistory->setPathHistory($page, '/de/alter-pfad/', $languages->get('de'));
```

This is the preferred method for recording history. It calls `addPathHistory()` internally
and then cleans up overlapping current-path entries.

### addPathHistory(Page $page, $path, $language = null)

Adds a historical path without cleaning up current-path entries. Returns `true` if the
path was added, or `false` if it overlaps with an existing live path (checked via
`PagePaths` when available).

```php
$pathHistory->addPathHistory($page, '/some-historical-path/');
```

Note: The path cannot collide with an existing page's current URL. If a live page
already occupies that path, the method returns `false` and does not record the entry.

### deletePathHistory(Page $page, $path)

Deletes a single historical path entry for the given page. Returns the number of
rows deleted (0 or 1).

```php
$deleted = $pathHistory->deletePathHistory($page, '/old-url/');
if($deleted) echo "Path removed from history\n";
```

### deleteAllPathHistory($page)

Deletes all historical paths. Pass a `Page` object to delete history for one page,
or `true` to delete all history for all pages.

```php
// Delete all history for one page
$pathHistory->deleteAllPathHistory($page);

// Delete all history for every page
$pathHistory->deleteAllPathHistory(true);
```

---

## Hooks

PagePathHistory listens to several core hooks and also provides hookable methods
for its own lifecycle.

### Hooks added by the module

| Hook                                | When                                                  |
|-------------------------------------|-------------------------------------------------------|
| `Pages::moved`                      | After a page is moved to a different parent           |
| `Pages::renamed`                    | After a page is renamed                               |
| `Pages::deleted`                    | After a page is deleted (cleans up history)           |
| `ProcessPageView::pageNotFound`     | When a 404 occurs (performs automatic redirect)       |
| `Page::addUrl($url, $language)`     | When adding a custom URL via `$page->addUrl()`        |
| `Page::removeUrl($url, $language)`  | When removing a custom URL via `$page->removeUrl()`   |

After installation, `$page->addUrl()` and `$page->removeUrl()` become available
as methods on every Page object:

```php
// Add a custom historical URL for the page
$page->addUrl('/custom-redirect/', $languages->get('de'));

// Remove a previously added URL
$page->removeUrl('/custom-redirect/');
```

### Hookable module methods

| Hook                   | When                            | Arguments                  |
|------------------------|---------------------------------|----------------------------|
| `PagePathHistory::install`   | Module is installed        | —                          |
| `PagePathHistory::uninstall` | Module is uninstalled      | —                          |
| `PagePathHistory::upgrade`   | Module is upgraded         | `$fromVersion`, `$toVersion` |

```php
// Hook after install to seed initial history
$wire->addHookAfter('PagePathHistory::install', function(HookEvent $event) {
    $pathHistory = $event->object;
    // Populate history from existing pages...
});
```

---

## Configuration

The module provides one configurable setting via the admin UI:

**Minimum age (seconds)** — Pages must exist for at least this many seconds before
their previous paths are recorded. Default is 120 seconds (2 minutes). This prevents
recording history for pages that are immediately moved or renamed during initial setup.

```php
// Set via API
$pathHistory = $modules->get('PagePathHistory');
$pathHistory->minimumAge = 300; // 5 minutes
$modules->saveConfig('PagePathHistory', 'minimumAge', 300);
```

The module configuration screen also allows deleting all recorded historical paths.

---

## Notes

- **Autoload:** This is an autoload module — it initializes on every request.
- **Automatic redirects:** When a 404 occurs, the module looks up the requested path
  in its history. If found, it issues a `301 Moved Permanently` redirect to the
  page's current URL, including the correct language when applicable.
- **Trash exclusion:** Paths involving pages in the trash are not recorded.
- **Recovery format exclusion:** Paths matching the trash recovery format
  (e.g. `/blog/5134.3096.83_page-name`) are excluded.
- **Multi-language:** Supports [[LanguageSupportPageNames]] and records language-specific
  historical paths. When a language-specific redirect is triggered, the visitor is
  redirected to the correct language version of the page.
- **URL segments:** `getPathInfo()` can match paths with additional URL segments,
  but only when the matched page's template has URL segments enabled.
- **`$page->addUrl()` and `$page->removeUrl()`** are added to every Page object
  by this module, enabling programmatic URL history management.
- **Related:** See [[PagePaths]] for the runtime path cache that PagePathHistory
  queries to avoid recording paths that are currently in use.
- **Source file:** `wire/modules/Page/PagePathHistory/PagePathHistory.module`

