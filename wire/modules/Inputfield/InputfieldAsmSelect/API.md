# InputfieldAsmSelect

Sortable multiple-selection widget. Extends [[InputfieldSelectMultiple]] — all option
management methods (`addOption()`, `addOptions()`, `setOptions()`, `addOptionsString()`,
etc.) are inherited and documented in [[InputfieldSelect]]. Returns an array value
where order is preserved (implements `InputfieldHasSortableValue`).

```php
$f = $modules->get('InputfieldAsmSelect');
$f->name = 'categories';
$f->label = 'Categories';
$f->addOptions([1 => 'News', 2 => 'Events', 3 => 'Blog']);
$f->val([2, 1]); // pre-select Events and News, in that order
$form->add($f);
```

See [[Inputfield]] for the shared Inputfield API (labels, collapsed states, showIf,
rendering, processing, etc.).

## Properties

| Property                   | Type           | Default           | Description                                                                                         |
|----------------------------|----------------|-------------------|-----------------------------------------------------------------------------------------------------|
| `sortable`                 | `bool`         | `true`            | Allow drag-to-reorder of selected items                                                             |
| `addable`                  | `bool`         | `true`            | Allow adding items to the selection                                                                 |
| `deletable`                | `bool`         | `true`            | Allow removing items from the selection                                                             |
| `hideDeleted`              | `bool`         | `true`            | Remove items immediately when deleted; when `false`, marks for deletion                             |
| `deletedPrepend`           | `string`       | `'-'`             | Character prepended to deleted item values when `hideDeleted` is `false`                            |
| `hideWhenEmpty`            | `bool`         | `false`           | Hide the select when no options are available                                                       |
| `editLink`                 | `string`       | `''`              | URL pattern for item edit links, e.g. `/admin/page/edit/?id={value}`                                |
| `editLabel`                | `string`       | (icon)            | Label/icon for the edit link                                                                        |
| `editLinkModal`            | `string\|bool` | `true`            | Open edit link in modal (`true`), `false` to open inline, or `'longclick'` for longclick-only modal |
| `editLinkOnlySelected`     | `bool`         | `true`            | Show edit link only for previously selected items, not newly added ones                             |
| `editLinkButtonSelector`   | `string`       | `''`              | CSS selector for buttons that become modal buttons in the edit link                                 |
| `usePageEdit`              | `int\|bool`    | `false`           | When used with FieldtypePage, auto-enables page editor links (modal)                                |
| `addItemTarget`            | `string`       | `'bottom'`        | Where new selections appear: `'top'` or `'bottom'`                                                  |
| `animate`                  | `bool`         | `false`           | Animate adding/removing of items                                                                    |
| `removeLabel`              | `string`       | (icon)            | Label/icon for the remove button                                                                    |
| `sortLabel`                | `string`       | (icon)            | Label/icon for the sort handle                                                                      |
| `debugMode`                | `bool`         | `false`           | Keep original `<select>` visible for debugging                                                      |

## Setting asmSelect options

Properties in the table above can be set directly on the Inputfield:

```php
$f->sortable = false;      // disable drag-to-reorder
$f->addable = false;       // prevent adding new selections
$f->addItemTarget = 'top'; // new selections appear at the top
```

For setting multiple options at once, use `setAsmSelectOptions()`:

```php
$f->setAsmSelectOptions([
    'sortable' => false,
    'animate' => true,
    'addItemTarget' => 'top',
]);
```

## Edit links

The `editLink` property adds an edit icon next to each selected item, linking to a
URL where `{value}` is replaced by the item's option value.

```php
$f->editLink = '/admin/page/edit/?id={value}';
$f->editLinkModal = true; // open in modal window (default)
```

When used with a `FieldtypePage` field and `usePageEdit` is enabled, edit links are
configured automatically:

```php
$f->usePageEdit = 1; // enables modal page editor links for selected pages
```

## Deletion behavior

By default (`hideDeleted = true`), removing an item immediately hides it from the
selected list. When `hideDeleted` is `false`, deleted items remain visible but are
marked for deletion, with their submitted values prepended with `deletedPrepend`
(default `'-'`) so the receiving code can identify them.

```php
$f->hideDeleted = false;
$f->deletedPrepend = '-'; // deleted item 'abc' is submitted as '-abc'
```

## Notes

- Selected item order is preserved after `processInput()` — `$f->val()` returns values in the order the user arranged them.
- Requires jQuery and jQuery UI (loaded automatically).
- The `size` attribute from `InputfieldSelectMultiple` is not used.
- **Source file:** `wire/modules/Inputfield/InputfieldAsmSelect/InputfieldAsmSelect.module`
