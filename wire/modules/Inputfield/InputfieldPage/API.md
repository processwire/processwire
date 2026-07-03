# InputfieldPage

`InputfieldPage` is the input controller for selecting one or more ProcessWire
pages as relational references. It is most often used by `FieldtypePage`, which
creates and configures it automatically for Page reference fields.

`InputfieldPage` does not render a selection UI by itself. Its `inputfield`
property names a delegate Inputfield, such as `InputfieldSelect`,
`InputfieldAsmSelect`, `InputfieldPageListSelect`, or
`InputfieldPageAutocomplete`. `InputfieldPage` determines selectable pages,
generates option labels, validates submitted selections, and normalizes selected
IDs into a `PageArray` or `Page`.

```php
$f = $modules->get('InputfieldPage');
$f->name = 'category';
$f->label = 'Category';
$f->inputfield = 'InputfieldSelect';
$f->parent_id = $pages->get('/categories/')->id;
$f->labelFieldName = 'title';
$form->add($f);
```

For shared Inputfield behavior, see the main `Inputfield` API documentation.

## Value

The `value` attribute is a `PageArray` by default, even for a single selection.
When `derefAsPage` is enabled, the value is a single `Page`.

```php
$value = $f->attr('value'); // PageArray by default
foreach($value as $page) {
	echo $page->title;
}
```

Accepted assignment formats include:

```php
$f->attr('value', 1234);          // page ID
$f->attr('value', '123|456|789'); // pipe-separated page IDs
$f->attr('value', $page);         // Page
$f->attr('value', $pageArray);    // PageArray
```

Integer IDs and pipe-separated ID strings are converted to a `PageArray`. Passing
a `Page` or `PageArray` stores that object as the value.

## Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `inputfield` | `string` | `''` | Delegate Inputfield class used for selection. Required for rendering. |
| `parent_id` | `int` | `0` | Selectable pages must be children of this parent ID. |
| `template_id` | `int` | `0` | Selectable pages must use this template ID. |
| `template_ids` | `array` | `[]` | Additional selectable template IDs. |
| `findPagesSelector` | `string` | `''` | Selector string used to find selectable pages. |
| `findPagesSelect` | `string` | `''` | Selector string built by `InputfieldSelector`; fallback for `findPagesSelector`. |
| `findPagesCode` | `string` | `''` | Deprecated PHP code path for selectable pages. Hook `getSelectablePages()` instead. |
| `labelFieldName` | `string` | `''` | Field used for option labels. `'.'` means use `labelFieldFormat`. |
| `labelFieldFormat` | `string` | `''` | Markup format string passed to page markup APIs when `labelFieldName='.'`. |
| `derefAsPage` | `int` | `0` | When truthy, `value` is a single `Page` rather than a `PageArray`. |
| `addable` | `int|bool` | `false` | Allow inline creation of new selectable pages. |
| `allowUnpub` | `int|bool` | `false` | Allow unpublished pages to be selected. |
| `inputfieldClasses` | `array` | defaults | Delegate Inputfield classes offered in configuration. |
| `inputfieldClass` | `string` | read-only setting | Resolved delegate class name, available through `$f->getSetting('inputfieldClass')`. |

At least one selectable-page constraint should usually be configured:
`parent_id`, `template_id` / `template_ids`, `findPagesSelector`, or
`findPagesSelect`.

## Selectable Pages

### getSelectablePages($page, $filterSelector = '')

Returns a `PageArray` of pages selectable for the page being edited. This is the
preferred hook point for custom selectable-page logic.

```php
$wire->addHookAfter('InputfieldPage::getSelectablePages', function(HookEvent $event) {
	$inputfield = $event->object; /** @var InputfieldPage $inputfield */
	$page = $event->arguments(0);
	if($inputfield->hasField == 'related_products') {
		$event->return = $event->pages->find('template=product, featured=1');
	}
});
```

The optional `$filterSelector` argument was added in ProcessWire 3.0.245 and
narrows the result with an additional selector.

Selectable pages can come from, in broad precedence order:

1. `findPagesSelector` / `findPagesSelect`
2. deprecated `findPagesCode`
3. children of `parent_id`
4. pages matching `template_id` / `template_ids`

The page currently being edited is removed from the selectable set to prevent a
page from referencing itself.

## Labels

### getPageLabel(Page $page, $allowMarkup = false)

Returns the option label for a page, using `labelFieldName` or
`labelFieldFormat`. Unpublished pages receive an `(unpublished)` suffix.

```php
$label = $f->getPageLabel($somePage);
```

### renderPageLabel(Page $page, $allowMarkup = false)

Hookable implementation used by `getPageLabel()` when hooks are present.

```php
$wire->addHookAfter('InputfieldPage::renderPageLabel', function(HookEvent $event) {
	$page = $event->arguments(0);
	$event->return = $page->title . ' (' . $page->parent->title . ')';
});
```

## Selectors

### createFindPagesSelector(array $options = [])

Builds the effective selector from configured properties.

```php
$selector = $f->createFindPagesSelector([ 'page' => $editPage ]);
// Example: parent_id=1042, templates_id=29, include=hidden
```

Options:

| Option | Type | Description |
| --- | --- | --- |
| `page` | `Page|null` | Page context for dynamic `page.field` selector values. |
| `findRaw` | `bool` | Add `field` information for `$pages->findRaw()` usage. |
| `getArray` | `bool` | Return an associative array instead of a selector string. |

### getTemplateIDs($getString = false)

Returns configured template IDs as an array, or as a `1|2|3` string when
`$getString` is true.

```php
$ids = $f->getTemplateIDs();
$str = $f->getTemplateIDs(true);
```

## Dynamic Selectors

`findPagesSelector` and `findPagesSelect` can reference values from the page
being edited:

```text
parent=page.section, template=article
```

`page.field` and `item.field` placeholders are populated at render/process time.
When the placeholder refers to a page ID value and that value is empty, the value
is replaced with `-1` so the selector intentionally matches nothing. Empty
non-ID subfields are replaced with an empty value.

## Delegate Inputfield

### getInputfield()

Returns the configured delegate Inputfield instance, populated with current value,
selectable options, selector information, and relevant settings. Returns `null`
if `inputfield` does not name a valid Inputfield module.

## Rendering and Processing

### render()

Renders the delegate Inputfield and inline-add UI when enabled. If no valid
delegate is configured, it returns the input name and records an error during
`renderReady()`.

### renderValue()

Renders selected page labels for non-editable display. Multiple values render as
a `<ul class="PageArray pw-bullets">`.

### processInput(WireInputData $input)

Delegates processing to the configured inputfield, validates submitted page IDs,
normalizes the value to `PageArray` or `Page`, and processes inline-added pages
when enabled.

### isValidPage(Page $page, $field, ?Page $editPage = null)

Static validation helper used primarily by `FieldtypePage`. It validates parent,
template, selector, and self-reference constraints, but does not validate
deprecated `findPagesCode` results.

```php
if(InputfieldPage::isValidPage($page, $field, $editPage)) {
	// page may be selected
}
```

## Inline Page Creation

When `addable` is enabled, `parent_id` and `template_id` are configured, and
`labelFieldName` is empty or `title`, the rendered input may include controls for
creating new selectable pages. Access is checked against the parent and new page.
Existing sibling pages with matching titles are reused rather than duplicated.

## Notes

- Access this inputfield with `$modules->get('InputfieldPage')`.
- It is normally created by `FieldtypePage`; manual construction is uncommon.
- The `inputfield` property must identify a compatible delegate Inputfield.
- `findPagesCode` is deprecated; hook `getSelectablePages()` instead.
- `isEmpty()` returns true when no page is selected.
- Companion classes include `FieldtypePage`, `InputfieldSelect`,
  `InputfieldAsmSelect`, `InputfieldPageListSelect`,
  `InputfieldPageAutocomplete`, and `InputfieldTextTags`.

**Source file:** `wire/modules/Inputfield/InputfieldPage/InputfieldPage.module`
