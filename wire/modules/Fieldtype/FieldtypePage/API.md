# FieldtypePage

Stores references to one or more ProcessWire pages. The value type depends on the
`derefAsPage` field setting:

| `derefAsPage` constant | Value when populated | Value when empty |
|---|---|---|
| `FieldtypePage::derefAsPageArray` (0, default) | `PageArray` | empty `PageArray` |
| `FieldtypePage::derefAsPageOrNullPage` (2) | `Page` | `NullPage` |
| `FieldtypePage::derefAsPageOrFalse` (1) | `Page` | `false` |

---

## Value type

`PageArray` by default (when `derefAsPage=0`), or `Page`/`NullPage`/`false` for single-page mode.
Unpublished pages are excluded from the value unless `allowUnpub=1` is set on the field.

---

## Getting and setting values

```php
// --- PageArray mode (derefAsPage=0, default) ---

// Get
$items = $page->page_field;         // PageArray (possibly empty)
$first = $page->page_field->first(); // first Page or boolean false if empty

// Iterate
foreach($page->page_field as $item) {
    echo $item->title;
}

// Set (replaces entire value)
$page->page_field = $pages->find('template=product');
$page->save('page_field');

// Add a page
$page->page_field->add($pages->get(1234));
$page->page_field->add(1234); // same as above
$page->save('page_field');

// Add multiple pages at once
$items = $pages->find('sort=-modified, limit=3'); 
$page->page_field->add($items);
$page->save('page_field');

// Remove a page
$page->page_field->remove($pages->get(1234));
$page->save('page_field');

// Remove page(s) with selector
$page->page_field->remove('name=foo'); 

// Remove multiple pages
$items = $page->page_field->find('status>=' . Page::statusHidden); 
$page->page_field->remove($items);


// --- Single-page mode (derefAsPage=1 or 2) ---

// Get
$item = $page->page_field;         // Page, NullPage (or false) when empty
if($page->page_field) { ... }      // check if a page was selected when derefAsPage=1
if($page->page_field->id) { ... }  // check if a page was selected when derefAsPage=2

// Set
$page->page_field = $pages->get(1234);  // Page object
$page->page_field = 1234;               // page ID
$page->page_field = '/path/to/page/';   // page path
$page->page_field = null;               // clear
$page->save('page_field');
```
---

## Selectors

Page reference fields support matching by **page ID**, **page name**, **page path**, **count**,
and **subfields** of the referenced pages (both native page properties and custom fields):

```php
// Match by page ID
$pages->find('page_field=1234');
$pages->find('page_field=1234|5678');  // either page

// Match by page name
$pages->find('page_field=some-page-name');

// Match by page path
$pages->find('page_field=/path/to/page/');

// Empty / not empty
$pages->find('page_field=""');    // no page selected
$pages->find('page_field!=""');   // at least one page selected

// Count of referenced pages
$pages->find('page_field.count>0');   // has at least one
$pages->find('page_field.count=3');   // exactly 3

// Native page property subfields
$pages->find('page_field.template=product');
$pages->find('page_field.parent=/products/');
$pages->find('page_field.name=some-name');
$pages->find('page_field.status=published');
$pages->find('page_field.created>2026-01-01');

// Custom field subfields (matches pages whose referenced pages have matching custom fields)
$pages->find('page_field.price>100');
$pages->find('page_field.color=red');
$pages->find('page_field.title~=keyword');
```

> **Note on subfield matching:** When using a custom field subfield (e.g. `page_field.price>100`),
> ProcessWire performs a nested `$pages->find()` internally to resolve which referenced pages match,
> then checks which pages on the current template reference them. This is powerful but can be slower
> on large datasets.

---

## Output / markup

```php
// PageArray mode (derefAsPage=0) — iterate or use WireArray methods:
foreach($page->page_field as $item) {
    echo "<a href='$item->url'>$item->title</a>";
}

// Same output as above with alternate syntax
echo $page->page_field->each("<a href='{url}'>{title}</a>"); 

// Render titles as comma-separated list:
echo $page->page_field->implode(', ', 'title');

// Single-page mode (derefAsPage=1):
if($page->page_field) {
	echo $page->page_field->title;
	echo "<a href='{$page->page_field->url}'>{$page->page_field->title}</a>";
}

// Single-page mode (derefAsPage=2):
if($page->page_field->id) {
	echo $page->page_field->title;
	echo "<a href='{$page->page_field->url}'>{$page->page_field->title}</a>";
}

// String cast to check if value present, works in multi-page mode and both single modes
if("$page->page_field") {
  // value is populated
} else {
  // value is empty
}

// Page fields can be used in selectors
$pages->find("categories=$page->page_field"); 
```

---

## Creating a page field programmatically

```php
/** @var PageField $field */
$field = $fields->new('page', 'related_products', 'Related Products');

// Restrict selectable pages to a single template
$field->setTemplate('product');

// Or restrict to multiple templates
$field->setTemplates(['product', 'service']);

// Restrict selectable pages to children of a specific parent
$field->setParent('/products/');

// Set the Inputfield type used in the page editor
$field->setInputfield('checkboxes');   // InputfieldCheckboxes
$field->setInputfield('select');       // InputfieldSelect
$field->setInputfield('asmSelect');    // InputfieldAsmSelect (default for multi)

// Return single page rather than PageArray
$field->set('derefAsPage', FieldtypePage::derefAsPageOrNullPage);

$fields->save($field);

// Add the field to a template
$template = $templates->get('your-template');
$template->fieldgroup->add($field);
$template->fieldgroup->save();
```

---

## Notes

- `derefAsPage` setting controls whether the value is a `PageArray` (0), single `Page`/`false` (1),
  or single `Page`/`NullPage` (2). Default is `PageArray` (0).
- `allowUnpub=1` allows unpublished pages to be included in the value.
- `template_id` / `template_ids`: restrict selectable pages to specific template(s).
- `parent_id`: restrict selectable pages to children of a specific parent page.
- `findPagesSelector`: custom selector string for finding selectable pages.
- `labelFieldName`: the field used as the label in the input (default is `title`).
- `labelFieldFormat`: a `$page->getMarkup()` format string to use instead of `labelFieldName`.
- `addable=1`: allows editors to create new pages inline from the field input.
- Circular page references (a page referencing itself) are automatically prevented.
- Compatible fieldtypes: `FieldtypePage` and classes extending it only.
- Database column: `int NOT NULL` per row, multi-value table (one row per referenced page).
