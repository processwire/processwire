# FieldtypeRepeater

Stores a collection of repeating field groups. Each item in the collection is a `RepeaterPage`
(extends `Page`), and the collection is a `RepeaterPageArray` (extends `PageArray`). Items are 
stored as real pages in the ProcessWire admin page tree under page/repeaters/ (relative to your 
admin root, which is /processwire/ by default but is configurable).

## Value type

`RepeaterPageArray` (extends `PageArray`). Each item is a `RepeaterPage` (extends `Page`).

When output formatting is on (default), only published/visible items are returned.
When off (`$page->of(false)`), all items including unpublished/hidden "ready" items are included.

---

## Getting and setting values

```php
// Iterate items
foreach($page->repeater_field as $item) {
    echo $item->title;
    echo $item->some_field;
}

// Check if populated
if($page->repeater_field->count()) { ... }

// Get first item
$first = $page->repeater_field->first();

// Get item by index (0-based)
$item = $page->repeater_field->eq(0);

// Add a new item programmatically
$item = $page->repeater_field->getNewItem();
$item->title = 'New item';
$item->save();
$page->save('repeater_field');

// Remove an item
$page->repeater_field->remove($item);
$page->save('repeater_field');

// Get the owner page (field repeater lives on) and field from a repeater item
$ownerPage  = $item->getForPage();
$ownerField = $item->getForField();

// For nested repeaters: get the root-level (non-repeater) owner page and field
// For non-nested repeaters this is the same as getForPage() and getForField().
$rootPage  = $item->getForPageRoot();
$rootField = $item->getForFieldRoot();

// Depth (when repeaterDepth > 0 is configured on the field)
$depth = $item->depth;  // int, 0 = top level
```

---

## Selectors

```php
// Has at least one item
$pages->find('repeater_field.count>0');

// Has no items
$pages->find('repeater_field.count=0');

// Exactly 3 items
$pages->find('repeater_field.count=3');

// Match by a subfield value in the repeater items
$pages->find('repeater_field.title*=keyword');
$pages->find('repeater_field.some_field=value');
$pages->find('repeater_field.some_date>2024-01-01');
```

> Subfield matching performs an internal `$pages->find()` on the repeater template,
> then checks which pages reference those results. This is powerful but can be slower
> on large datasets.

---

## Output / markup

```php
// Iterate and output
foreach($page->repeater_field as $item) {
    echo "<h3>{$item->headline}</h3>";
    echo "<p>{$item->body}</p>";
}

// Template syntax with each()
echo $page->repeater_field->each("<div><h3>{headline}</h3><p>{body}</p></div>");

// Depth-aware output (when repeaterDepth field setting is enabled)
foreach($page->repeater_field as $item) {
    $indent = str_repeat('  ', $item->depth);
    echo $indent . $item->title . "\n";
}
```

---

## Notes

- Items are stored as real pages under `/admin-url/page/repeaters/` (i.e. `/processwire/page/repeaters/`)
  in the page tree. Each repeater field gets a dedicated parent page and template for its items.
- Formatted value excludes unpublished and hidden items. Unformatted value includes all
  items, including internal "ready" (pre-created) items.
- **`getNewItem()`** recycles existing ready items rather than always creating new pages.
  Always call `$item->save()` followed by `$page->save('field_name')` after adding items.
- **Depth**: when `repeaterDepth > 0` is configured, items can be indented. `$item->depth`
  returns the item's depth level (0 = top). This is independent of nested/matrix repeaters.
- **`repeaterLoading` constants** (field configuration for admin page editor behavior):
  - `FieldtypeRepeater::loadingNew` (0) — ajax-load newly added items only
  - `FieldtypeRepeater::loadingAll` (1) — ajax-load all items
  - `FieldtypeRepeater::loadingOff` (2) — no ajax loading
- **`repeaterCollapse` constants** (field configuration for admin page editor behavior):
  - `FieldtypeRepeater::collapseExisting` (0) — collapse existing items (default)
  - `FieldtypeRepeater::collapseAll` (1) — collapse all items including new
  - `FieldtypeRepeater::collapseNone` (3) — do not collapse any items
- Database columns: `pages_id INT`, `data TEXT` (CSV of repeater item page IDs), `count INT`, `parent_id INT`.
- Compatible fieldtypes: `FieldtypeRepeater` and extending types only.

---

# FieldtypeFieldsetPage

Stores a fixed set of fields as a single "fieldset" page, providing namespace isolation so
the same field names can be reused across multiple templates without conflict. Extends
`FieldtypeRepeater` but always contains exactly one item.

---

## Value type

`FieldsetPage` (extends `RepeaterPage`, which extends `Page`). Always a single object —
never a `PageArray`. The fieldset page is created automatically when the field is first used.

---

## Getting and setting values

```php
// Access fields on the fieldset page
echo $page->fieldset_field->title;
echo $page->fieldset_field->some_other_field;

// Set a field value
$page->fieldset_field->title = 'New value';
$page->save('fieldset_field');

// Check if a subfield is populated
if($page->fieldset_field->title) { ... }

// Access the owner page and field
$ownerPage  = $page->fieldset_field->getForPage();
$ownerField = $page->fieldset_field->getForField();
$ownerTitle = $page->fieldset_field->getForPage()->title;
```

---

## Selectors

Supports the same subfield selectors as `FieldtypeRepeater`:

```php
$pages->find('fieldset_field.title*=keyword');
$pages->find('fieldset_field.some_field=value');
```

---

## Notes

- The value is always a single `FieldsetPage` instance, never a `PageArray`.
- Fields on the fieldset page exist in their own namespace, so `title` on one fieldset
  is independent of `title` on another — even if both fieldsets use the same field.
- The fieldset page mirrors the output formatting (`of()`) state of its owner page automatically.
- Compatible fieldtypes: `FieldtypeFieldsetPage` only.