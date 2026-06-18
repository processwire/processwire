# WireMarkupRegions

Implements the Markup Regions output strategy, where template files inject fragments
of markup into named regions within a shared layout file.

Enable in `/site/config.php`:

```php
$config->useMarkupRegions = true;
```

Once enabled, any HTML output from a template file that appears **before** the first
`<!DOCTYPE>` declaration is treated as a set of region updates rather than direct
output. ProcessWire matches each element to a region in the layout file by `id`,
`pw-id`, or `data-pw-id`, then applies the specified action.

---

## Template file usage (the common case)

The Markup Regions system is driven by `pw-*` attributes in your template output.
No PHP calls to `WireMarkupRegions` are needed — you just output HTML with these
attributes and ProcessWire handles the rest.

### Actions via pw-* attributes

```html
<!-- Replace the #content region entirely -->
<div pw-replace="content"><p>New content here</p></div>
<!-- or: match by id attribute on the element itself -->
<div id="content" pw-replace><p>New content</p></div>

<!-- Append to #content -->
<p pw-append="content">This paragraph gets appended.</p>

<!-- Prepend to #content -->
<p pw-prepend="content">This paragraph gets prepended.</p>

<!-- Insert before the #sidebar element -->
<aside pw-before="sidebar">Goes before sidebar</aside>

<!-- Insert after the #sidebar element -->
<aside pw-after="sidebar">Goes after sidebar</aside>

<!-- Remove the #promo region entirely -->
<div pw-remove="promo"></div>
```

### Merging attributes onto the target

When you include other attributes on a region element, they are **merged onto the
target element** in the layout — not just applied to your inserted content:

```html
<!-- Append content AND add class "has-sidebar" to #main in the layout -->
<div id="main" class="has-sidebar" pw-append>
    <aside>Sidebar content</aside>
</div>

<!-- Append content AND remove class "no-js" from #body in the layout -->
<div id="body" class="-no-js" pw-append></div>
```

Prefix a class name with `-` to remove it from the target.

### Optional regions

Mark a region with `pw-optional` (or `data-pw-optional`) to have it automatically
removed from the final output if it ends up empty:

```html
<div id="promo" pw-optional>
    <?php if($page->promo_text): ?>
        <p><?= $page->promo_text ?></p>
    <?php endif ?>
</div>
```

---

## Programmatic usage

`WireMarkupRegions` can also be used directly for general-purpose HTML manipulation,
independent of the template system.

```php
$regions = new WireMarkupRegions();
```

### find($selector, $markup, $options)

Locate elements in an HTML document matching a selector.

```php
$results = $regions->find('#content', $html);
$results = $regions->find('.sidebar', $html);
$results = $regions->find('<footer>', $html);       // by tag name
$results = $regions->find('data-region=main', $html); // by attribute value
$results = $regions->find('[pw-action]', $html);    // any pw-* action attribute
```

Selector formats:

| Selector               | Matches                                                        |
|------------------------|----------------------------------------------------------------|
| `#name`                | `id`, `pw-id`, or `data-pw-id` equals "name"                  |
| `.name`                | `class` attribute contains "name"                              |
| `.name*`               | `class` attribute starts with "name" (prefix wildcard)         |
| `tag.name`             | Specific tag with class "name"                                 |
| `<tag>`                | All instances of a specific HTML tag                           |
| `attribute=value`      | Attribute with exact value                                     |
| `tag[attribute=value]` | Specific tag with that attribute value                         |
| `[pw-action]`          | Any element with a `pw-*` action attribute                     |
| `attribute`            | Element with attribute present (any value)                     |

Options:

| Option     | Default | Description                                                    |
|------------|---------|----------------------------------------------------------------|
| `single`   | `false` | Return only the first match's markup string (not array)        |
| `verbose`  | `false` | Return detailed info array per region                          |
| `wrap`     | `null`  | Include wrapping tags (auto: `true` for class, `false` for id) |
| `max`      | `500`   | Maximum regions to find                                        |
| `exact`    | `false` | Return region markup exactly as-is                             |
| `leftover` | `false` | Include a `'leftover'` key with unmatched markup               |

Return shapes:

- Default: array of matched region strings.
- `single=true`: first matched region string, or an empty string when not found.
- `verbose=true`: array of region info arrays with keys such as `name`, `pwid`,
  `open`, `close`, `attrs`, `classes`, `action`, `actionTarget`, `region`, and `html`.
- `leftover=true`: includes a `leftover` array entry containing markup not consumed
  by matched regions.

### update($selector, $content, $markup, $options)

Update matching regions with content using a specified action.

```php
$result = $regions->update('#main', '<p>New content</p>', $html, ['action' => 'replace']);
$result = $regions->update('.sidebar', '<p>Extra</p>', $html, ['action' => 'append']);
```

Actions: `replace`, `update`, `append`, `prepend`, `before`, `after`, `remove`, `auto`.

Convenience wrappers call `update()` with the matching action:

```php
$html = $regions->replace('#main', '<p>New</p>', $html);
$html = $regions->append('#main', '<p>After existing content</p>', $html);
$html = $regions->prepend('#main', '<p>Before existing content</p>', $html);
$html = $regions->before('#main', '<aside>Before element</aside>', $html);
$html = $regions->after('#main', '<aside>After element</aside>', $html);
$html = $regions->remove('#promo', $html);
```

### populate(&$htmlDocument, $htmlRegions, $options)

The primary internal method — matches template output to layout regions and applies
all pending updates. Called automatically by ProcessWire; you rarely need to call
this directly.

```php
$numUpdates = $regions->populate($mainHtml, $templateOutput);
```

---

## Tag utilities

### mergeTags($htmlTag, $mergeTag)

Merge attributes from one HTML tag string into another. The tag name from `$htmlTag`
is kept; class attributes are merged (not replaced); other attributes in `$mergeTag`
are added or overwrite those in `$htmlTag`.

```php
$tag = $regions->mergeTags(
    '<div id="main" class="old">',
    '<div class="new extra" title="hello">'
);
// '<div id="main" class="old new extra" title="hello">'
```

Class merge operators:

```php
// Add a class (force-add even if previously removed)
$regions->mergeTags($tag, '<div class="+highlight">');
// Remove a class
$regions->mergeTags($tag, '<div class="-old-class">');
// Remove all classes matching a prefix
$regions->mergeTags($tag, '<div class="-col-*">');
```

### getTagInfo($tag)

Parse an HTML opening tag and return an info array including `name`, `id`, `pwid`,
`classes`, `attrs`, `action`, `actionTarget`, and `close`.

```php
$info = $regions->getTagInfo('<div id="main" class="wrap" pw-append>');
// ['name' => 'div', 'id' => 'main', 'action' => 'append', 'actionTarget' => 'main', ...]
```

### renderAttributes($attrs, $encode, $quote)

Render an associative array as an HTML attribute string. Boolean `true` values render
as standalone attributes (e.g. `checked`); array values are joined with spaces.

```php
$str = $regions->renderAttributes(['id' => 'main', 'class' => 'wrap', 'checked' => true]);
// 'id="main" class="wrap" checked'
```

### hasAttribute($name, $value, &$html)

Check whether an HTML attribute appears anywhere in the given markup.

```php
if($regions->hasAttribute('id', 'main', $html)) { ... }
if($regions->hasAttribute('class', 'sidebar', $html)) { ... }
```

### removeRegionTags(&$html)

Remove `<region>` and `<pw-region>` wrapper tags from markup and strip `pw-id` /
`data-pw-id` attributes from remaining tags. Returns `true` when the markup changed.

```php
if($regions->removeRegionTags($html)) {
    // $html was modified in place
}
```

### hasRegions(&$html) / hasRegionActions(&$html)

Fast checks for markup that contains region identifiers or `pw-*` actions.

```php
if($regions->hasRegions($html)) { ... }
if($regions->hasRegionActions($html)) { ... }
```

---

## Stripping

### stripRegions($tag, $markup, $getRegions)

Strip non-nested tags (comments, scripts, styles) from markup, or extract them.

```php
$clean    = $regions->stripRegions('<!--', $markup);      // remove HTML comments
$clean    = $regions->stripRegions('<script', $markup);   // remove script tags
$stripped = $regions->stripRegions('<!--', $markup, true); // extract comments instead
```

### stripOptional($markup)

Remove elements with `pw-optional` / `data-pw-optional` that are empty; strip the
attribute from non-empty ones.

```php
$clean = $regions->stripOptional($markup);
```

---

## Notes

- **Source file:** `wire/core/Tools/WireMarkupRegions/WireMarkupRegions.php`
- Enable with `$config->useMarkupRegions = true` in `/site/config.php`.
- Template output before `<!DOCTYPE>` is treated as region updates; output after is the layout document.
- `pw-id` and `data-pw-id` attributes work like `id` for region targeting, without affecting the rendered DOM `id`.
- All `pw-*` action attributes are stripped from the final output.
- `<!--#name-->` HTML comments serve as fast-match hints for closing tags, improving parsing performance on large documents.
- Single-use tags (`<html>`, `<head>`, `<body>`, `<title>`, `<main>`, `<base>`) can be targeted by tag name alone without an id.
- The `^` prefix on an action target (`pw-append="^footer"`) matches by tag name rather than id.
