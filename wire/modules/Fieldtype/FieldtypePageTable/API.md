# FieldtypePageTable

Stores references to a group of pages that are edited inline (in a modal window) from the page editor. Items are
real pages in the ProcessWire page tree — either as children of the page being edited, or under
a separately configured parent page. Unlike Repeater, PageTable items have their own URLs and
are independently addressable via the pages API.

---

## Value type

`PageTableArray` (extends `PageArray`). Each item is a regular `Page` using whichever
template(s) are configured on the field.

Formatted value (output formatting on) excludes hidden and unpublished items.
Unformatted value includes all items regardless of status.

---

## Getting and setting values

```php
// Iterate items
foreach($page->pagetable_field as $item) {
    echo $item->title;
    echo $item->some_field;
}

// Add a new item (template and parent determined from field configuration)
$item = $page->pagetable_field->getNewItem();
$item->title = 'New item';
$item->save();
$page->save('pagetable_field');

// Add an existing page as an item
// ($item must have correct template and parent or it will be refused)
$item = $pages->get('/path/to/item/');
$page->pagetable_field->add($item);
$page->save('pagetable_field');

// Remove an item from the field value (does not delete the page)
$page->pagetable_field->remove($item);
$page->save('pagetable_field');

// Delete an item page permanently
$pages->delete($item);
$page->save('pagetable_field');
```

---

## Selectors

PageTable supports subfield matching, delegating to `FieldtypePage` for query resolution.
Direct field-value matching (e.g. `pagetable_field=1234`) is not supported — use subfields.

```php
// Pages that have at least one item with a matching title
$pages->find('pagetable_field.title*=keyword');

// Match by a custom field on the items
$pages->find('pagetable_field.some_field=value');

// Match by item template
$pages->find('pagetable_field.template=product');
```

---

## Output / markup

```php
// Iterate and output
foreach($page->pagetable_field as $item) {
    echo "<h3>{$item->title}</h3>";
    echo "<p>{$item->body}</p>";
}

// Template syntax via each()
echo $page->pagetable_field->each("<div><h3>{title}</h3><p>{body}</p></div>");

// Link directly to an item page (items have their own URLs)
foreach($page->pagetable_field as $item) {
    echo "<a href='$item->url'>$item->title</a>";
}
```

---

## Notes

- PageTable items are real pages with URLs, unlike Repeater items which are stored in a
  hidden admin structure. Items can be found and accessed via `$pages->find()` independently.
- If `parent_id` is configured on the field, all items live under that shared parent page.
  If not set, items are created as children of the page being edited.
- `template_id` may be a single int or an array of ints when multiple templates are allowed.
- Items added to a `PageTableArray` are validated against the field's configured template(s)
  and parent. Items that don't match are silently rejected by `add()`.
- When the owning page is deleted, trashed, or unpublished, the configured `trashOnDelete`,
  `unpubOnTrash`, and `unpubOnUnpub` settings control what happens to the item pages.
- Cloning a page with a PageTable field automatically clones all of its PageTable items.
- Database columns: `pages_id INT`, `data INT` (item page ID), `sort INT`.
- Compatible fieldtypes: `FieldtypePageTable` only.