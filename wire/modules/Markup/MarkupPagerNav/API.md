# MarkupPagerNav

`MarkupPagerNav` generates pagination navigation markup for `PageArray`,
`PaginatedArray`, and any other object implementing `WirePaginatable`.

Most site code does not instantiate `MarkupPagerNav` directly. The common path is:

1. Get a limited result set, usually with `$pages->find("selector, limit=N")`.
2. Render the items.
3. Call `$items->renderPager()` or `$items->renderPagination()`.

```php
$items = $pages->find("template=blog-post, limit=10");

foreach($items as $item) {
    echo "<article><h2>$item->title</h2></article>";
}

echo $items->renderPagination();
```

`limit=N` is what makes a result paginated. It gives the returned `PageArray`
pagination metadata such as `getTotal()`, `getLimit()`, and `getStart()`. Without
a limit, ProcessWire returns the full result set and there is usually nothing for
`MarkupPagerNav` to render.

> Note that to use `/page2/` style pagination URLs on a rendered page, the
> Template used by the rendering Page must have its `allowPageNum` property set
> to `1`. In the admin this is in: Setup > Templates > your-template > URLs >
> Allow Page Numbers. When page numbers are not allowed, `MarkupPagerNav` falls
> back to query-string pagination like `?page=2`.

---

## PageArray and PaginatedArray

### renderPager($options)

```php
$items = $pages->find("template=blog-post, limit=10");
echo $items->renderPager([
    'numPageLinks' => 5,
    'listClass' => 'pagination',
]);
```

`renderPager()` delegates to `MarkupPagerNav` when the module is installed.

### renderPagination($options)

Alias of `renderPager()`.

```php
echo $items->renderPagination([
    'baseUrl' => $page->url,
]);
```

### getPaginationString($label, $usePageNum)

Returns text describing the current pagination position.

```php
echo $items->getPaginationString('Items');      // Items 1 to 10 of 100
echo $items->getPaginationString('Page', true); // Page 1 of 10

echo $items->getPaginationString([
    'label' => 'Items',
    'zeroLabel' => 'No items found',
]);
```

For the full shared option reference used by `renderPager()` and
`renderPagination()`, see `wire/core/PageArray/pagination-options.md`.

---

## Direct MarkupPagerNav Usage

Use the module directly when you need to render pagination for a custom
`WirePaginatable` object, or when you want direct access to pager state after render.

```php
$items = $pages->find("template=blog-post, limit=10");

$pager = $modules->get('MarkupPagerNav');
echo $pager->render($items, [
    'numPageLinks' => 5,
    'listClass' => 'pagination',
]);

if($pager->isLastPage()) {
    // The rendered pagination was on the last page.
}
```

### render(WirePaginatable $items, array $options = [])

Renders pagination markup. Returns an empty string when there is no pagination to
render, such as when total items are less than or equal to the current page limit.

---

## Customizing Output

Pass an options array to `render()`, `renderPager()`, or `renderPagination()` to
override defaults. Only specify what you want to change.

```php
echo $items->renderPagination([
    'numPageLinks' => 5,
    'listClass' => 'uk-pagination',
    'currentItemClass' => 'uk-active',
    'currentLinkMarkup' => "<span>{out}</span>",
    'separatorItemLabel' => '<span>&hellip;</span>',
    'separatorItemClass' => 'uk-disabled',
    'nextItemLabel' => '<i class="fa fa-angle-double-right"></i>',
    'previousItemLabel' => '<i class="fa fa-angle-double-left"></i>',
    'nextItemClass' => '',
    'previousItemClass' => '',
    'lastItemClass' => '',
]);
```

---

## General Options

| Option         | Default | Description                                                                                                           |
|----------------|---------|-----------------------------------------------------------------------------------------------------------------------|
| `numPageLinks` | `10`    | Number of page links shown. Values of 5 or more are recommended.                                                      |
| `baseUrl`      | `''`    | Base URL for pagination links. Auto-detected from the current page when empty.                                        |
| `getVars`      | `[]`    | GET variables to append to pagination URLs.                                                                           |
| `page`         | `null`  | Current `Page`, or `null` to auto-detect from `$page`.                                                                |
| `arrayToCSV`   | `true`  | Convert array GET values to CSV in URLs, such as `?tags=a,b`. When false, array values use `tags[]=a&tags[]=b` style. |

Pagination URLs are built from `$input->whitelist()` values automatically when
`getVars` is empty. Use `getVars` when you need to include specific query-string
parameters regardless of whitelist state. In either case, make sure variables are
validated before including them in the pagination URLs.

---

## Markup Options

| Option                | Default                                                                      | Description                                             |
|-----------------------|------------------------------------------------------------------------------|---------------------------------------------------------|
| `listMarkup`          | `<ul class='{class}' role='navigation' aria-label='{aria-label}'>{out}</ul>` | Container markup.                                       |
| `itemMarkup`          | `<li aria-label='{aria-label}' class='{class}' {attr}>{out}</li>`            | Item markup.                                            |
| `linkMarkup`          | `<a href='{url}'><span>{out}</span></a>`                                     | Link markup.                                            |
| `currentLinkMarkup`   | `<a href='{url}'><span>{out}</span></a>`                                     | Current-page link markup.                               |
| `separatorItemMarkup` | `null`                                                                       | Separator markup. Falls back to `itemMarkup` when null. |

Tokens available in markup templates:

| Token          | Available in                      | Description                                                         |
|----------------|-----------------------------------|---------------------------------------------------------------------|
| `{out}`        | all markup options                | Item content/label.                                                 |
| `{url}`        | `linkMarkup`, `currentLinkMarkup` | Link href URL.                                                      |
| `{class}`      | `itemMarkup`, `listMarkup`        | CSS class attribute.                                                |
| `{aria-label}` | `itemMarkup`, `listMarkup`        | ARIA label text.                                                    |
| `{attr}`       | `itemMarkup`                      | Extra attributes, such as `aria-current` for the current page item. |

---

## Class Options

| Option                 | Default                     | Description                       |
|------------------------|-----------------------------|-----------------------------------|
| `listClass`            | `'MarkupPagerNav'`          | CSS class for the list container. |
| `currentItemClass`     | `'MarkupPagerNavOn'`        | Current page item.                |
| `nextItemClass`        | `'MarkupPagerNavNext'`      | Next button item.                 |
| `previousItemClass`    | `'MarkupPagerNavPrevious'`  | Previous button item.             |
| `firstItemClass`       | `'MarkupPagerNavFirst'`     | First item in the rendered pager. |
| `lastItemClass`        | `'MarkupPagerNavLast'`      | Last item in the rendered pager.  |
| `firstNumberItemClass` | `'MarkupPagerNavFirstNum'`  | First numbered page item.         |
| `lastNumberItemClass`  | `'MarkupPagerNavLastNum'`   | Last numbered page item.          |
| `separatorItemClass`   | `'MarkupPagerNavSeparator'` | Separator item.                   |

---

## Label and ARIA Options

| Option                  | Default                    | Description                                              |
|-------------------------|----------------------------|----------------------------------------------------------|
| `nextItemLabel`         | `'Next'`                   | Next button label.                                       |
| `previousItemLabel`     | `'Prev'`                   | Previous button label.                                   |
| `separatorItemLabel`    | `'&hellip;'`               | Separator label.                                         |
| `listAriaLabel`         | `'Pagination links'`       | List ARIA label.                                         |
| `itemAriaLabel`         | `'Page {n}'`               | Page item ARIA label.                                    |
| `currentItemAriaLabel`  | `'Page {n}, current page'` | Current page ARIA label.                                 |
| `currentItemExtraAttr`  | `aria-current='true'`      | Extra attributes applied to the current page item.       |
| `nextItemAriaLabel`     | `'Next page'`              | Next button ARIA label.                                  |
| `previousItemAriaLabel` | `'Previous page'`          | Previous button ARIA label.                              |
| `lastItemAriaLabel`     | `'Page {n}, last page'`    | Last page ARIA label.                                    |

---

## Notes

- `MarkupPagerNav` is not autoloaded; load it with `$modules->get('MarkupPagerNav')`
  when using it directly without a `PaginatedArray` or `PageArray` object. 
- `PageArray` and `PaginatedArray` are usually the best public API surface:
  `$items->renderPager()`, `$items->renderPagination()`, and `$items->getPaginationString()`.
- `MarkupPageArray` is an autoload module that hooks `PaginatedArray::renderPager()`
  and uses `MarkupPagerNav` internally.
- `render()` updates `$config->urls->next`, `$config->urls->prev`, and
  `$config->pagerHeadTags` when next/previous pages exist.
- **Source file:** `wire/modules/Markup/MarkupPagerNav/MarkupPagerNav.module`
