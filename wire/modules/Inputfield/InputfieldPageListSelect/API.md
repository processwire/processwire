# InputfieldPageListSelect

Tree-based page selection from a ProcessWire page list — renders a clickable 
page tree where users browse, expand, and select pages visually. Available as a 
single-selection (`InputfieldPageListSelect`) and a multi-selection variant 
(`InputfieldPageListSelectMultiple`) with drag-to-reorder support.

Unlike [[InputfieldSelect]] or [[InputfieldPageAutocomplete]], this Inputfield
does not pre-load options into a dropdown. Instead, it leverages the 
`ProcessPageList` JavaScript widget to let users navigate the actual page tree 
and choose pages directly. This makes it ideal for large page trees where a 
flat dropdown would be unwieldy.

```php
// Single page selection
$f = $modules->get('InputfieldPageListSelect');
$f->name = 'parent_page';
$f->label = 'Parent page';
$f->parent_id = $pages->get('/articles/')->id;
$f->labelFieldName = 'title';
$form->add($f);

// Multiple page selection with sorting
$f = $modules->get('InputfieldPageListSelectMultiple');
$f->name = 'related_articles';
$f->label = 'Related articles';
$f->parent_id = $pages->get('/articles/')->id;
$f->labelFieldName = 'title';
$form->add($f);
```

These Inputfields are commonly used as delegates by [[InputfieldPage]] when the
`inputfield` property is set to `InputfieldPageListSelect` or 
`InputfieldPageListSelectMultiple`. They can also be used standalone.

For shared Inputfield behavior, see the main [[Inputfield]] API documentation.

## Properties

### InputfieldPageListSelect

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `parent_id` | `int` | `0` | Root page ID for the selectable tree. Must be set for the inputfield to render. |
| `labelFieldName` | `string` | `title` | Field name used for page labels in the tree (e.g. `title`, `name`). |
| `showPath` | `bool` | `false` | Show the path of each page in the tree alongside the label. |
| `startLabel` | `string` | `Change` | Label for the button/link that opens the page tree for selection. |
| `cancelLabel` | `string` | `Cancel` | Label for the cancel/close button in the tree selector. |
| `selectLabel` | `string` | `Select` | Label for the button that confirms the current selection. |
| `unselectLabel` | `string` | `Unselect` | Label for the button that clears the current selection. |
| `moreLabel` | `string` | `more` | Label for the link that reveals additional child pages. |

### InputfieldPageListSelectMultiple

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `parent_id` | `int` | `0` | Root page ID for the selectable tree. Must be set for the inputfield to render. |
| `labelFieldName` | `string` | `title` | Field name used for page labels in the tree (e.g. `title`, `name`). |
| `startLabel` | `string` | `Add` | Label for the button/link that opens the page tree for adding pages. |
| `cancelLabel` | `string` | `Close` | Label for the cancel/close button in the tree selector. |
| `selectLabel` | `string` | `Select` | Label for the button that adds the selected page. |
| `selectedLabel` | `string` | `Selected` | Label shown on tree items that are already selected. |
| `unselectLabel` | `string` | `Unselect` | Label for removing a selection from the tree. |
| `moreLabel` | `string` | `more` | Label for the link that reveals additional child pages. |
| `removeLabel` | `string` | `Remove` | Label/title for the remove button on selected items. |

## Value handling

### Single selection (InputfieldPageListSelect)

The value is a single integer page ID. `setAttribute('value', ...)` accepts 
a `Page` object, an integer ID, a numeric string, or an array (first element 
is used). All values are cast to `int`.

```php
// Set by page ID
$f->attr('value', 1234);

// Set by Page object
$f->attr('value', $pages->get('/some-page/'));

// Get current value
$id = $f->val(); // int, e.g. 1042
```

`isEmpty()` returns `true` when the value is less than `1` (i.e. no page 
selected).

### Multiple selection (InputfieldPageListSelectMultiple)

The value is an array of integer page IDs. The array preserves the user's 
sort order (implements `InputfieldHasSortableValue`). Submitted values arrive 
as a comma-separated string which is split and cast to integers during 
`processInput()`.

```php
// Set by array of page IDs
$f->attr('value', [1042, 1055, 1099]);

// Get current values
$ids = $f->val(); // array, e.g. [1042, 1055, 1099]
```

The multi-select variant implements `InputfieldHasArrayValue` — its value is 
always an array, even when empty (`[]`).

## Methods

### renderMarkupValue($value)

Renders the selected page label(s) as non-editable markup for display contexts 
(e.g. when the page is not being edited). Called internally by `___renderValue()`.

```php
// Single: renders a <p> with the page label
// Multiple: renders a <ul> with <li> per selected page
echo $f->renderMarkupValue($f->val());
```

### getPageLabel(Page $page)

Returns a sanitized, entity-encoded label string for a given page. Uses the 
configured `labelFieldName`, falling back to the page's name if the label field 
is empty. If the page is not viewable, returns a "Page N not viewable" message.

```php
$label = $f->getPageLabel($page);
```

When the Inputfield is used as a delegate of [[InputfieldPage]] (i.e. 
`$f->hasInputfield` is an `InputfieldPage` instance), this method delegates to 
`InputfieldPage::getPageLabel()` for consistent label formatting.

### renderParentError()

Returns an error message string when `parent_id` is not configured. Called 
automatically by `___render()` when `parent_id` is empty or `0`.

```php
// Returns: "<p class='error'>Unable to render this field due to missing parent page in field settings.</p>"
```

### pageListReady($name, $labelFieldName)

Initializes the `ProcessPageList` module and configures it with the given field 
name and label field. Called internally during `renderReady()`. If already 
initialized (lazy-loaded), subsequent calls are no-ops.

## Rendering

Both variants render a hidden `<input type="text">` element that holds the 
current value(s), combined with JavaScript-driven page tree markup provided 
by `ProcessPageList`. The input element carries `data-` attributes that 
configure the tree widget:

| data attribute | Purpose |
| --- | --- |
| `data-root` | Root page ID for the tree |
| `data-showPath` | (single) Whether to show page paths |
| `data-allowUnselect` | (single) Whether unselect is allowed (opposite of `required`) |
| `data-start` | Label for opening the tree |
| `data-select` | Label for the select button |
| `data-unselect` | Label for the unselect button |
| `data-more` | Label for the "more" link |
| `data-cancel` | Label for the cancel button |
| `data-selected` | (multiple) Label for already-selected items |
| `data-labelName` | Field attribute `name` value |
| `data-href` | (multiple) CSS selector for the selected-items container |

The multi-select variant additionally renders an `<ol>` containing `<li>` 
elements for each selected page, with sort handles and remove buttons.

## Interfaces

- `InputfieldPageListSelection` — marker interface indicating the Inputfield 
  provides tree-based page selection capabilities (shared by both variants).
- `InputfieldHasArrayValue` — implemented by `InputfieldPageListSelectMultiple`; 
  value is always an array.
- `InputfieldHasSortableValue` — implemented by `InputfieldPageListSelectMultiple`; 
  selected page order is user-sortable and preserved.

## Hooks

| Hook | When | Arguments |
| --- | --- | --- |
| `InputfieldPageListSelect::render` | Before rendering the single-select input | none |
| `InputfieldPageListSelect::renderValue` | Before rendering the display-only value | none |
| `InputfieldPageListSelect::processInput` | After processing submitted input | `$input` (WireInputData) |
| `InputfieldPageListSelectMultiple::render` | Before rendering the multi-select input | none |
| `InputfieldPageListSelectMultiple::renderValue` | Before rendering the display-only value | none |
| `InputfieldPageListSelectMultiple::processInput` | After processing submitted input | `$input` (WireInputData) |

```php
// Hook after multi-select processing to add custom validation
$wire->addHookAfter('InputfieldPageListSelectMultiple::processInput', function(HookEvent $event) {
    $inputfield = $event->object;
    $selectedIds = $inputfield->val();
    if(count($selectedIds) > 5) {
        $inputfield->error('You may select at most 5 pages.');
    }
});
```

## Notes

- `parent_id` must be configured for the inputfield to render. An empty or `0` 
  value displays an error message in the admin.
- The `parent_id` here refers to the **root** of the selectable tree, not a 
  direct parent — this is the semantic defined by the 
  `InputfieldPageListSelection` interface.
- Both variants depend on the `ProcessPageList` module, which is loaded 
  automatically during `renderReady()`.
- The label field is used both in the tree during selection and in the 
  display-only rendered value.
- When used as a delegate of `InputfieldPage`, labels and selectable-page 
  logic are handled by the parent `InputfieldPage` rather than the 
  page-list widget itself.
- The multi-select variant uses jQuery UI sortable for drag-to-reorder; the 
  output list is populated from the page tree via `ProcessPageList` events.
- **Source files:**
  - `wire/modules/Inputfield/InputfieldPageListSelect/InputfieldPageListSelect.module`
  - `wire/modules/Inputfield/InputfieldPageListSelect/InputfieldPageListSelectMultiple.module`
  - `wire/modules/Inputfield/InputfieldPageListSelect/InputfieldPageListSelectCommon.php`


