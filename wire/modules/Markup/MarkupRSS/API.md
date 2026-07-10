# MarkupRSS

Renders an RSS 2.0 feed from a PageArray of pages. Designed to be called directly from a template file — set the feed properties, pass your pages, call `render()`, and `exit`.

```php
$rss = $modules->get('MarkupRSS');
$rss->setArray([
    'title' => 'Latest updates',
    'description' => 'The most recently updated pages',
    'itemTitleField' => 'title',
    'itemDateField' => 'modified',
    'itemDescriptionField' => 'summary',
    'itemDescriptionLength' => 1000,
    'itemContentField' => 'body',     // optional full HTML content
    'itemAuthorField' => 'createdUser',
]);
$rss->render($pages->find('template=blog-post, limit=10, sort=-modified'));
exit;
```

The module is configurable — all settings can be set via the module configuration screen (Admin → Modules → MarkupRSS) and serve as defaults, or you can override them per feed at runtime via `setArray()`, `set()`, or direct property access.

Extends [[WireData]]. Implements `Module` and `ConfigurableModule`.

## Properties

All properties are read/write and can be set via `$rss->set('key', $value)`, `$rss->key = $value`, or `$rss->setArray([...])`.

| Property                    | Type               | Default                              | Description |
|-----------------------------|--------------------|--------------------------------------|-------------|
| `title`                     | `string`           | `'Untitled RSS Feed'`                | Feed title (`<channel><title>`) |
| `url`                       | `string`           | `''`                                 | Feed URL (`<channel><link>`); falls back to current page's `httpUrl` when empty |
| `description`               | `string`           | `''`                                 | Feed description (`<channel><description>`) |
| `copyright`                 | `string`           | `''`                                 | Optional copyright notice; omitted from XML when empty |
| `ttl`                       | `int`              | `60`                                 | Time-to-live in minutes; how long the feed may be cached before refreshing |
| `xsl`                       | `string`           | `''`                                 | Optional URL to an XSL stylesheet for browser display |
| `css`                       | `string`           | `''`                                 | Optional URL to a CSS stylesheet |
| `header`                    | `string`           | `'Content-Type: application/xml; charset=utf-8;'` | HTTP header sent by `render()` |
| `itemTitleField`            | `string`           | `'title'`                            | Page field to use for each item's `<title>` |
| `itemDateField`             | `string`           | `'created'`                           | Field for `<pubDate>` — a datetime field, or `'created'`, `'modified'`, `'published'` |
| `itemDescriptionField`      | `string`           | `'summary'`                          | Page field for `<description>` |
| `itemDescriptionLength`     | `int`              | `1024`                                | Max description length; set to `0` to allow HTML with no truncation |
| `itemContentField`          | `string`           | `''`                                  | Optional page field for full HTML in `<content:encoded>` |
| `itemAuthorField`           | `string`           | `''`                                  | Optional page field for author (text, Page, or PageArray) |
| `itemAuthorElement`         | `string`           | `'dc:creator'`                        | XML element for author — `'dc:creator'` or `'author'` |
| `stripTags`                 | `bool`             | `true`                                | Strip HTML tags from description when `itemDescriptionLength > 0` |
| `feedPages`                 | `array\|PageArray` | `[]`                                  | The pages to render; typically passed to `render()` or `renderFeed()` |

## Methods

### render(?PageArray $feedPages = null)

Sends the `Content-Type: application/xml` HTTP header, renders the feed, echoes it, and returns `true`. Pass a PageArray directly, or set `feedPages` beforehand. Call `exit` after this to prevent further output from corrupting the XML.

```php
$rss = $modules->get('MarkupRSS');
$rss->title = 'My Feed';
$rss->render($pages->find('template=blog-post, limit=10, sort=-created'));
exit;
```

### renderFeed(?PageArray $feedPages = null)

Renders the complete RSS feed XML and returns it as a string — without sending HTTP headers or echoing. Use this when you need control over output (custom headers, caching, logging, etc.).

```php
$xml = $rss->renderFeed($pages->find('template=news, limit=20, sort=-created'));
header('Content-Type: application/xml; charset=utf-8');
echo $xml;
exit;
```

### getModuleConfigInputfields(array $data)

Returns the module configuration form used in Admin → Modules → MarkupRSS → Settings. Populates select fields with actual text, textarea, and datetime fields from the site's field list.

## Item rendering behavior

### Titles

The item title comes from `itemTitleField` (default `title`). HTML tags are stripped. If a page has an empty title, the entire item is skipped from the feed.

### Dates

Publish date uses `getUnformatted()` on `itemDateField` to obtain a raw Unix timestamp, then formats it as RFC 2822 (`DATE_RSS`). Built-in options: `'created'`, `'modified'`, `'published'`, or any datetime field name. If the timestamp evaluates to falsy (e.g., empty field), no `<pubDate>` is emitted for that item.

### Descriptions

Two modes depending on `itemDescriptionLength`:

- **Length > 0 (default `1024`):** HTML entities are decoded, tags are stripped (unless `stripTags` is `false`), and the text is intelligently truncated — it tries to end at punctuation (`.`, `?`, `!`, `,`, `;`) or at a word boundary. The result is XML-escaped.
- **Length == 0:** The raw field value is passed through as-is within a CDATA section, and relative URLs in `href`/`src` attributes are converted to absolute. This preserves HTML formatting.

```php
// Truncated text descriptions (default behavior)
$rss->itemDescriptionLength = 500;

// Full HTML descriptions (no truncation, no tag stripping)
$rss->itemDescriptionLength = 0;
```

### Full content

When `itemContentField` is set, the field's HTML is included in a `<content:encoded>` CDATA element alongside the `<description>`. The relative-to-absolute conversion converts `href="/..."` and `src="/..."` to absolute URLs using the site root, and `href="#..."` anchors to absolute page URLs with fragments.

```php
$rss->itemContentField = 'body'; // include full article body as <content:encoded>
```

### Authors

When `itemAuthorField` is set, the module supports:
- A **text field** — value used directly.
- A **Page field** — uses the page's `title|name` (first non-empty).
- A **PageArray field** — joins titles with `, `.

The XML element is `dc:creator` by default. Change to `'author'` via `itemAuthorElement` (use `'author'` when the value is an email address to comply with RSS spec).

```php
$rss->itemAuthorField = 'createdUser';  // use page author
$rss->itemAuthorElement = 'author';     // use <author> element instead of <dc:creator>
```

### Viewability filtering

Pages that are not viewable (`$page->viewable()` returns `false`) are automatically skipped — the module respects page status, access control, and any `viewable` hooks.

## Common setup patterns

### Blog feed with full content

```php
$rss = $modules->get('MarkupRSS');
$rss->setArray([
    'title' => $page->title,
    'description' => $page->summary,
    'itemDescriptionField' => 'summary',
    'itemDescriptionLength' => 1000,
    'itemContentField' => 'body',
    'itemDateField' => 'created',
    'itemAuthorField' => 'createdUser',
    'copyright' => '© ' . date('Y') . ' My Site',
]);
$rss->render($pages->find('template=blog-post, limit=20, sort=-created'));
exit;
```

### Pre-configured from module settings

When the module is already configured in Admin → Modules → MarkupRSS, just pass pages:

```php
$modules->get('MarkupRSS')->render($pages->find('template=blog-post, limit=20, sort=-created'));
exit;
```

### Return-only (no output)

```php
$rss = $modules->get('MarkupRSS');
$rss->title = 'News Feed';
$xml = $rss->renderFeed($pages->find('template=news, limit=10, sort=-modified'));
// cache it, send custom headers, log it, etc.
header('Content-Type: application/xml; charset=utf-8');
echo $xml;
exit;
```

## Notes

- Always call `exit` after `render()` in a template file — any further output will corrupt the XML.
- The `url` property defaults to the current page's `httpUrl` when left blank (set in `renderHeader()`).
- XML namespaces declared: `xmlns:atom`, `xmlns:dc`, and `xmlns:content` (the latter only when `itemContentField` is set).
- Entity encoding follows [W3C feed validator](https://validator.w3.org/feed/) recommendations: `<`, `>`, `&`, `"`, `'` are emitted as hexadecimal entities (`&#x0003E;`, `&#x00026;`, etc.).
- CDATA `<![CDATA[` and `]]>` markers in source HTML content are escaped to prevent breaking the outer CDATA wrapper.
- The `itemAuthorField`, `itemAuthorElement`, and `stripTags` properties are not exposed in the module config screen — set them via the API only.
- **Source file:** `wire/modules/Markup/MarkupRSS.module`
- **Extends:** [[WireData]] — all WireData property access (`get`, `set`, `setArray`, etc.) is available.

