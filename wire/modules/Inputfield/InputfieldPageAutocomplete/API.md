# InputfieldPageAutocomplete

Autocomplete page selection input. It connects a text input to the
`ProcessPageSearch` ajax API and stores selected pages as an array of page IDs.
It is most often used as a delegate inputfield for [[InputfieldPage]] Page
reference fields, but can also be configured directly.

```php
$f = $modules->get('InputfieldPageAutocomplete');
$f->name = 'related_articles';
$f->label = 'Related articles';
$f->parent_id = $pages->get('/articles/')->id;
$f->template_id = $templates->get('article')->id;
$f->searchFields = 'title body';
$form->add($f);
```

For shared Inputfield behavior, see [[Inputfield]]. For Page reference field
configuration, see [[InputfieldPage]].

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `parent_id` | `int` | `0` | Limit ajax results to this parent ID. With `findPagesSelector`, it becomes a `has_parent` root constraint. |
| `template_id` | `int` | `0` | Limit ajax results and selected-page lookup to one template ID. |
| `template_ids` | `array` | `[]` | Limit ajax results to multiple template IDs. |
| `labelFieldName` | `string` | `'title'` | Field used for result and selected-item labels. |
| `labelFieldFormat` | `string` | `''` | Format string for labels; overrides `labelFieldName` when populated. |
| `searchFields` | `string` | `'title'` | Space-separated fields searched by autocomplete. |
| `operator` | `string` | `'%='` | Selector operator used for autocomplete matching. |
| `findPagesSelector` | `string` | `''` | Additional selector used for ajax page search. |
| `maxSelectedItems` | `int` | `0` | Maximum selected pages. `0` means unlimited; `1` enables single-select mode. |
| `useList` | `bool` | `true` | Render a sortable selected-items list below the search input. |
| `allowAnyValue` | `bool` | `false` | Allow unmatched typed text to remain in the visible input. |
| `disableChars` | `string` | `''` | Characters that prevent autocomplete from triggering. |
| `useAndWords` | `bool` | `false` | Search each word separately, similar to all-words matching. |
| `lang_id` | `int` | `0` | Force a language ID for ajax results. |
| `allowUnpub` | `null|bool|int` | `null` | Whether ajax results may include unpublished pages. `false` adds a published-status constraint. |
| `usePageEdit` | `string` | `''` | Optional edit-link mode: `small`, `medium`, `large`, `tab`, or blank. |
| `hideDeleted` | `bool` | `false` | Hide deleted selected items immediately instead of marking them with undo state. |

`InputfieldPage` usually sets most page-selection properties for you. Set them
directly only when using this inputfield outside that delegate context.

## Selected Pages

### getSelectedPages()

Return selected page IDs as a `PageArray`.

```php
$selected = $f->getSelectedPages();
foreach($selected as $page) {
	echo $page->title;
}
```

The lookup accepts the field value as an array or pipe-separated string. A single
`template_id` and `parent_id` are used as lookup restrictions when present. When
multiple `template_ids` are configured, selected-page lookup loads by ID without
a single-template restriction.

## Ajax URL

### getAjaxUrl()

Build and return the `ProcessPageSearch` ajax URL used by the JavaScript
autocomplete.

```php
$url = $f->getAjaxUrl();
```

The selector stored for `ProcessPageSearch` is built from:

- `parent_id`
- `template_id` or `template_ids`
- `findPagesSelector`
- `allowUnpub`

If no selector constraints are configured, the selector falls back to `id>0`.
Unless the selector already contains `limit=`, the URL adds `limit=50`.

When `labelFieldFormat` is populated, `getAjaxUrl()` stores the format with
`ProcessPageSearch` and adds a `format_name` query parameter. Otherwise it adds
`get=<labelFieldName>`.

## Rendering

### render()

Render the complete autocomplete widget. Output includes:

- A hidden input containing selected page IDs as comma-separated values.
- A visible text input for autocomplete searching.
- A selected-items list when `useList` is enabled.

```php
echo $f->render();
```

Use the base field name for `$f->name`; `render()` appends `[]` to the hidden
input name automatically.

```php
$f->name = 'related_pages'; // renders name='related_pages[]'
```

Do not include `[]` yourself, or the rendered name becomes `related_pages[][]`.

When `maxSelectedItems` is `1`, the input switches to single-select mode and
`useList` is forced to `false`.

### renderList()

Render the selected-items list as an `<ol>`.

```php
echo $f->renderList();
```

### renderListItem($label, $value, $class = '', Page $page = null)

Render one selected item. Hook this method to customize selected-list markup.

```php
$wire->addHookAfter('InputfieldPageAutocomplete::renderListItem', function(HookEvent $event) {
	$page = $event->arguments(3);
	if($page && $page->template == 'article') {
		$event->return = str_replace('</span>', ' <em>article</em></span>', $event->return);
	}
});
```

When `usePageEdit` is set and the current user can edit the page, selected-item
labels link to the page editor.

## Processing Input

### processInput(WireInputData $input)

Normalize submitted hidden input values into an array of integer page IDs.

```php
// Submitted value: ['123,456']
$form->processInput($input->post);
$ids = $f->val(); // [123, 456]
```

Blank submitted values become an empty array.

## Configuration

### getConfigInputfields()

Return module configuration fields for:

- Autocomplete search operator.
- Fields to query.
- Click-to-edit mode.
- Delete behavior.

### install() / uninstall()

Install and uninstall register or remove this inputfield from
[[InputfieldPage]]'s available selection widgets.

## Hooks

Hookable methods:

| Method | Purpose |
|--------|---------|
| `getSelectedPages()` | Return selected pages as a `PageArray`. |
| `getAjaxUrl()` | Return the ajax search URL. |
| `render()` | Render the autocomplete widget. |
| `renderList()` | Render selected-items list markup. |
| `renderListItem($label, $value, $class = '', Page $page = null)` | Render one selected item. |
| `processInput(WireInputData $input)` | Process submitted values. |
| `install()` | Register with `InputfieldPage`. |
| `uninstall()` | Remove from `InputfieldPage`. |
| `getConfigInputfields()` | Return config fields. |

## Notes

- Access with `$modules->get('InputfieldPageAutocomplete')`.
- Implements `InputfieldHasArrayValue`; values are arrays of page IDs.
- Implements `InputfieldHasSortableValue`; selected page IDs preserve sort order.
- `getAjaxUrl()` requires the current user to be able to execute
  `ProcessPageSearch`, which is normally true in the Page editor context.
- `hideDeleted` adds a wrapper class in `renderReady()` and a list class in
  `renderList()`.
- Source file:
  `wire/modules/Inputfield/InputfieldPageAutocomplete/InputfieldPageAutocomplete.module`.

