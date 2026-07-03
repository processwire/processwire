# InputfieldSelector

Visual selector builder for ProcessWire page-finding selectors. It renders an
admin UI where users choose fields, operators, values, modifiers, subfields, and
groups rather than typing raw selector strings.

```php
$f = $modules->get('InputfieldSelector');
$f->name = 'filter';
$f->label = 'Filter pages';
$f->value = 'title%=processwire';
$form->add($f);
```

The editable `value` is a selector string. If `initValue` is configured, it acts
as a locked selector prefix and the combined selector is available as
`lastSelector` after assignment or processing.

For selector syntax, see [[Selectors]]. For shared Inputfield behavior, see
[[Inputfield]].

## Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `value` | `string` | `''` | Editable selector string shown in the visual builder. |
| `initValue` | `string` | `''` | Locked selector prefix. |
| `lastSelector` | `string` | `''` | Last full selector built from `initValue` and editable `value`. |
| `initTemplate` | `Template|null` | `null` | Context template used to scope fields. |
| `addIcon` | `string` | `'plus-circle'` | Icon for the add-row link. |
| `addLabel` | `string` | `'Add Field'` | Label for the add-row link. |
| `preview` | `bool` | `true` | Show the live selector preview. |
| `counter` | `bool` | `true` | Show the ajax match counter. |
| `allowAddRemove` | `bool` | `true` | Allow selector rows to be added and removed. |
| `allowSystemCustomFields` | `bool` | `false` | Include custom fields marked as system. |
| `allowSystemNativeFields` | `bool` | `true` | Include native fields such as `id`, `name`, and `template`. |
| `allowSystemTemplates` | `bool` | `false` | Allow system templates in template options. |
| `allowSubselectors` | `bool` | `true` | Allow subselector values such as `children=[title%=x]`. |
| `allowSubfields` | `bool` | `true` | Allow subfield selection such as `page_ref.title`. |
| `allowSubfieldGroups` | `bool` | `true` | Allow grouped page-reference subfields such as `@field.title`. |
| `allowModifiers` | `bool` | `true` | Allow modifier fields such as `include`, `limit`, and `sort`. |
| `allowBlankValues` | `bool` | `false` | Preserve blank values as `""` rather than omitting them. |
| `showFieldLabels` | `bool|int` | `false` | Show field labels instead of names; `2` shows both. |
| `showOptgroups` | `bool` | `true` | Group field choices into system, fields, subfields, groups, modifiers, and adjustments. |
| `limitFields` | `array` | `[]` | Whitelist of selectable field names. |
| `exclude` | `string|array` | `''` | Fields to exclude from selection. |
| `parseVars` | `bool` | `true` | Parse selector variables such as `[user.id]`. |
| `previewColumns` | `string|array` | `[]` | Columns used in Lister preview bookmarks. |
| `maxUsers` | `int` | `20` | Use text input for user fields when user count reaches this threshold. |
| `maxSelectOptions` | `int` | `100` | Use autocomplete for Page reference fields with more selectable pages than this. |
| `selectClass` | `string` | `''` | Extra class for generated selects. |
| `inputClass` | `string` | `''` | Extra class for generated inputs. |
| `checkboxClass` | `string` | `''` | Extra class for generated checkboxes. |

Date/time settings:

| Property | Default |
|----------|---------|
| `dateFormat` | `'Y-m-d'` |
| `datePlaceholder` | `'yyyy-mm-dd'` |
| `timeFormat` | `'H:i'` |
| `timePlaceholder` | `'hh:mm'` |

## Settings

### getDefaultSettings()

Return factory defaults for configurable settings:

```php
$defaults = $f->getDefaultSettings();
```

### getSettings()

Return effective settings after merging defaults and instance values:

```php
$f->preview = false;
$settings = $f->getSettings();
echo $settings['preview']; // false
```

The `ready()` method also applies optional global classes from
`$config->InputfieldSelector`:

```php
$config->InputfieldSelector = [
	'selectClass' => 'uk-select',
	'inputClass' => 'uk-input',
	'checkboxClass' => 'uk-checkbox',
];
```

## Selector Info

### setup()

Build internal operator, field, and modifier metadata. `render()` calls this
automatically. Call it manually before using `getSelectorInfo()` for system or
modifier fields.

```php
$f->setup();
```

### getSelectorInfo($field)

Return selector metadata for a system field, modifier field, field name, or
`Field` object.

```php
$f->setup();

$info = $f->getSelectorInfo('template');
$info = $f->getSelectorInfo($fields->get('title'));
```

The returned array can include keys such as `input`, `label`, `operators`,
`options`, `sanitizer`, and `subfields`.

## Sanitizing Selectors

### sanitizeSelectorString($selectorString, $parseVars = true)

Normalize a selector string, prepend `initValue`, optionally parse variables,
resolve username values for `created_users_id` / `modified_users_id`, and enforce
the `allowSubselectors` setting.

```php
$f->initValue = 'template=blog-post';
$selector = $f->sanitizeSelectorString('title%=api', false);
// template=blog-post, title%=api
```

When `allowSubselectors` is false, submitted subselectors are removed, an error is
recorded, and a forced non-match selector is added.

```php
$f->allowSubselectors = false;
$selector = $f->sanitizeSelectorString('children=[title%=api]', false);
// id<0
```

## Processing Input

### processInput(WireInputData $input)

Process submitted selector input and sanitize it. With no `initValue`, `value`
contains the processed selector.

```php
$form->processInput($input->post);
$selector = $f->val();
```

When `initValue` is populated, `value` stores the editable portion and
`lastSelector` stores the full selector:

```php
$f->initValue = 'template=blog-post';
$form->processInput($input->post);

$editable = $f->val();
$full = $f->lastSelector;
```

Use `sanitizeSelectorString()` directly when you need an immediate full selector
from raw input.

## Rendering

### render()

Render the full visual selector builder:

```php
echo $f->render();
```

Rendered output includes:

- A `<ul class="selector-list">` containing selector rows.
- A hidden input containing the editable selector value.
- Add/remove controls when `allowAddRemove` is enabled.
- Preview and counter elements unless disabled.

### renderRow($select, $subfield, $opval, $class = '')

Render one selector row wrapper from pre-rendered control markup.

```php
$row = $f->renderRow($selectHtml, $subfieldHtml, $opvalHtml, 'custom-row');
```

This is mainly a small extension point for row markup.

## Notes

- Get an instance with `$modules->get('InputfieldSelector')`.
- Intended for admin/process-module contexts, not general public forms.
- Ajax endpoints require a logged-in user and a valid rendered inputfield
  session state.
- `limitFields` may include subfields like `page_ref.title`.
- `initValue` is not rendered as editable rows; it is stored separately and
  represented in preview data.
- Source file: `wire/modules/Inputfield/InputfieldSelector/InputfieldSelector.module`.

