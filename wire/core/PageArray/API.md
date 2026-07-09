# PageArray

`PageArray` is the collection type returned whenever ProcessWire fetches more than one page.
`$pages->find()`, `$page->children()`, `$page->siblings()`, and similar methods all return
a `PageArray`. It extends `PaginatedArray` which extends `WireArray`, so all methods
described here include the full inherited behavior.

`PageArray` only accepts `Page` objects as items. Default behavior is that duplicate pages (by ID) are silently
ignored when adding.

---

## Creating a PageArray

```php
// Empty PageArray
$a = new PageArray();

// Shorthand (3.0.123+)
$a = PageArray();                          // blank
$a = PageArray($page);                     // with one page
$a = PageArray([$page1, $page2, $page3]);  // with several pages

// Add a page returned from $pages->get()
$a = new PageArray();
$a->add($page);

// Create a PageArray "wired" to the current ProcessWire instance
// (Preferable in multi-instance environments)
$a = $pages->newPageArray(); 
```
---

## Adding and removing pages

All manipulation methods return `$this`, so they can be chained.

```php
// Add to end
$a->add($page);
$a->add($pages->find("template=product")); // merge another PageArray
$a->add(1234);                             // add by page ID
$a->append($page);                         // alias of add()
$a->push($page);                           // alias of add()

// Add to beginning
$a->prepend($page);
$a->unshift($page);                        // alias of prepend()

// Insert relative to an existing item
$a->insertBefore($newPage, $existingPage);
$a->insertAfter($newPage, $existingPage);

// Replace one page with another
$a->replace($existingPage, $newPage);

// Remove
$a->remove($page);                         // remove by object
$a->remove("template=product");            // remove by selector
$a->removeItems([$page1, $page2]);         // remove several at once
$a->removeAll();                           // clear everything

// Pop and shift (mutate, return the removed page)
$last  = $a->pop();                        // remove and return last item
$first = $a->shift();                      // remove and return first item
```

---

## Iterating

`PageArray` implements `IteratorAggregate`, `ArrayAccess`, and `Countable`.

```php
// Standard foreach
foreach($a as $page) {
    echo "<li>$page->title</li>";
}

// Array-style access
$first = $a[0];
$a[]   = $page;   // append

// Count
$n = $a->count();
$n = count($a);   // same thing
```

---

## Retrieving items

```php
$page = $a->first();           // first page or false if empty
$page = $a->last();            // last page or false if empty
$page = $a->eq(0);             // nth page (0-based), negative counts from end
$page = $a->eq(-1);            // last page

// get() — very flexible
$page = $a->get("name=about"); // first match by property selector
$page = $a->get("about");      // first page with name "about"
$page = $a->get(1234);         // by key index (not page ID)

// getPage() — like get() but always returns Page|NullPage (never null/false)
$page = $a->getPage("name=about");
$page = $a->getPageByID(1234);    // by page ID, always Page|NullPage
$page = $a->getPageByName("about"); // by page name, always Page|NullPage

// Random selection
$page  = $a->getRandom();      // 1 random page (or null if empty)
$pages = $a->getRandom(3);     // 3 random pages as a new PageArray
$pages = $a->findRandom(3);    // same, always returns PageArray

// Time-seeded random (same result within a given time period)
$pages = $a->findRandomTimed(3);          // same 3 per day
$pages = $a->findRandomTimed(3, 'YmdH'); // same 3 per hour

// Slice
$pages = $a->slice(0, 5);      // first 5 pages as a new PageArray
$pages = $a->index(2);         // single-item PageArray at position 2

// All keys / all values
$keys   = $a->getKeys();       // array of internal numeric keys
$values = $a->getValues();     // re-indexed PHP array of pages
$native = $a->getArray();      // PHP array with original keys preserved
```

---

## Checking membership

```php
if($a->has($page)) { ... }             // check by Page object
if($a->has("name=about")) { ... }      // check by selector
if($a->has("about")) { ... }           // check by name

if($a->isIdentical($otherPageArray)) { ... }         // strict comparison
if($a->isIdentical($otherPageArray, false)) { ... }  // value-only comparison
```

---

## Finding and filtering

These methods operate **in memory** — no database query is performed.

```php
// find() — non-destructive, returns a new PageArray
$products = $a->find("template=product");
$featured  = $a->find("featured=1, sort=-modified");

// findOne() — returns first match or false (WireArray contract)
$page = $a->findOne("name=about");

// findOnePage() — like findOne() but returns Page|NullPage (never false)
// Use this when you need consistent return types with $pages->findOne()
$page = $a->findOnePage("name=about");

// filter() — destructive, keeps only matching pages
$a->filter("template=product");

// not() — destructive, removes matching pages
$a->not("template=product");
```
When possible, it is preferable to filter from `$pages->find()` (which
queries the database) rather than loading a large set of pages into memory 
and then filtering afterwards from the PageArray.

### Selectors supported in memory

Most selector operators work in memory, but `include=` and `check_access=` are ignored
(they only apply at the database layer). Use dot notation for sub-properties:

```php
$a->find("parent.template=section");
$a->find("created>=today");
$a->find("sort=title, limit=5");
```

---

## Sorting

Sorting is **in-place** — modifies the current PageArray.

```php
$a->sort("title");             // by title A-Z
$a->sort("-created");          // by created date newest first
$a->sort("parent.title, name"); // multi-field, dot notation
$a->sort("random");            // randomize
$a->shuffle();                 // randomize (alias)

// Reverse order, returns a NEW PageArray
$reversed = $a->reverse();
```

---

## Output helpers

### each()

`each()` is the most common output helper. It accepts four forms:

```php
// 1. Callable — iterate with a function
$a->each(function($page) {
    echo "<li>$page->title</li>";
});

// Callable with string return — concatenated result returned
$html = $a->each(function($page) {
    return "<li><a href='$page->url'>$page->title</a></li>";
});

// 2. Template string — {tag} placeholders filled from each page's properties
$html = $a->each("<li><a href='{url}'>{title}</a></li>");
echo "<ul>$html</ul>";

// 3. Property name string — returns PHP array of values
$titles = $a->each("title");             // ['Home', 'About', ...]

// 4. Array of property names — returns array of associative arrays
$data = $a->each(["title", "url"]);      // [['title'=>'Home','url'=>'/'], ...]
```

### implode()

```php
// Combine a property from all pages into a delimited string
echo $a->implode(", ", "title");   // "Home, About, Contact"
echo $a->implode(" | ", "url");    // "/about/ | /contact/ | ..."

// Use a callable to build each segment
echo $a->implode(", ", function($page) {
    return "$page->title ($page->id)";
});
```

### explode()

```php
// Return a plain PHP array of a property from all pages
$titles = $a->explode("title");           // ['Home', 'About', ...]
$ids    = $a->explode("id");

// Multiple properties
$data = $a->explode(["title", "url"]);
```

---

## Pagination

When `$pages->find()`, `$page->children()`, and similar DB-querying methods, uses a `limit`, 
the returned PageArray carries full pagination metadata.

```php
$results = $pages->find("template=product, limit=10");

$results->count();                  // pages in this result set (up to limit)
$results->getTotal();               // total matching pages across all paginations
$results->getLimit();               // the limit value used
$results->getStart();               // starting offset (0-based)

$results->hasPagination();          // true if more than one page of results
$results->hasNextPagination();      // true if a next page exists
$results->hasPrevPagination();      // true if a previous page exists

// "Items 1 to 10 of 50" style string
echo $results->getPaginationString("Items");

// "Page 1 of 5" style string
echo $results->getPaginationString("Page", true);

// Render pagination links (requires core MarkupPagerNav module installed)
echo $results->renderPagination(); // requires 3.0.260+
echo $results->renderPager(); // renderPager() works with all versions of PW

// Render pagination links with options (~30 customization options available)
echo $results->renderPagination([ 'numPageLinks' => 5 ]);
```
For custom options that you can send to the `renderPagination()` or `renderPager()` 
method see the `wire/core/PageArray/pagination-options.md` file or see the online 
[renderPagination() API reference](https://processwire.com/api/ref/page-array/render-pagination/) page.

---

## Selector retrieval

The selector string that was used to find the PageArray is stored on the result.

```php
$results = $pages->find("template=product, featured=1");
echo $results->getSelectors();         // "template=product, featured=1" (string)
$selectors = $results->getSelectors(); // Selectors object (parsed)
$str = $results->getSelectors(true);   // always return string
```

---

## String conversion

When cast to string, a PageArray becomes a pipe-separated list of page IDs, which is
compatible with ProcessWire's selector OR syntax.

```php
// find featured categories
$categories = $pages->find("template=category, featured=1");

// find products in featured categories 
$products = $pages->find("template=product, categories=$categories"); 

// example of string value and why it works in selectors like above
echo $categories; // e.g. "1001|1002|1003"
```

---

## Extra data storage

PageArray inherits jQuery-style `data()` for storing arbitrary metadata alongside the collection.

```php
$a->data("myKey", "myValue");   // set
$val = $a->data("myKey");       // get
$all = $a->data();              // get all
```

---

## Getting the next/previous item

```php
$nextPage = $a->getNext($page);   // page after $page in the array
$prevPage = $a->getPrev($page);   // page before $page in the array
```

---

## Notes

- `PageArray` prevents duplicate pages — adding a page already present is silently ignored.
- `count()` returns pages currently in the set. `getTotal()` returns the total across all
  paginations (only differs from `count()` when a `limit` was used in the find).
- `find()` and `filter()` operate on the in-memory set only, no database access.
  To perform database queries scoped to a parent, use `$page->find()` instead.
- Methods like `$a->first()` and `$a->last()` return `false` if the PageArray is empty
  (not a NullPage). Use `$a->getPage("…")` or any of the `getPage*()` methods if you need
  a `Page|NullPage` guarantee.
- `get()` and `findOne()` follow the WireArray contract and may return `null` or `false`
  when no match is found. For code that needs to work identically whether the source is
  `$pages->find()` (database) or an in-memory PageArray, use the Page-typed variants:
  `getPage()`, `getPageByID()`, `getPageByName()`, and `findOnePage()` — these always
  return `Page|NullPage`, matching the return type of the database-layer methods.
- Source files: `wire/core/PageArray/PageArray.php`, `wire/core/WireArray/WireArray.php`,
  `wire/core/WireArray/PaginatedArray.php`.
