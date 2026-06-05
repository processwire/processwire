# FieldtypeFieldsetOpen

Opens a collapsible, bordered group of fields in the page editor. Fields placed between
the open and its paired close field are visually grouped together.

---

## Value type

None — fieldset fields store no data and return no value. `$page->fieldset_name` returns
an empty string. They exist only as layout markers in the page editor.

---

## How it works

A fieldset always consists of a **pair**: an open field and a matching close field.
ProcessWire manages this pair automatically:

- **Saving** the open field creates or repairs the matching close field, named
  `{fieldname}_END`.
- **Adding** the open field in the admin also adds the matching close field to the
  template/fieldgroup.
- **Renaming** the open field also renames the close field.
- **Deleting** the open field in the admin also deletes the close field.

Place the close field wherever you want the group to end in the template's field order.

```
Template field order:

  title              ← regular field
  my_fieldset        ← FieldtypeFieldsetOpen  (group starts here)
  body
  summary
  my_fieldset_END    ← FieldtypeFieldsetClose (group ends here)
  images             ← regular field
```

---

## Creating fields programmatically

Create the opener with `$fields->new()` or `$fields->newField()`. Do not manually create
the close field as an independent field unless you are repairing old data; let the opener
fieldtype create the paired close field.

```php
// Create and save a fieldset opener named "details"
/** @var Field $open */
$open = $fields->new('FieldsetOpen', 'details', 'Details');

// Saving the opener creates/repairs the paired close field: details_END
/** @var Field $close */
$close = $fields->get('details_END');
```

The same pattern works for tabs:

```php
/** @var Field $tab */
$tab = $fields->new('FieldsetTabOpen', 'seo', [
    'label' => 'SEO',
    'modal' => false,
]);

/** @var Field $tabClose */
$tabClose = $fields->get('seo_END');
```

If you use `$fields->newField()` because you need to configure before saving, save the
opener before expecting the close field to exist:

```php
$open = $fields->newField('FieldsetOpen', 'details', 'Details');
$open->collapsed = Inputfield::collapsedYes;
$fields->save($open);

$close = $fields->get('details_END');
```

### Adding to a fieldgroup

When adding a fieldset to a template or fieldgroup in code, add fields in the exact visual
order you want:

1. Add the open field.
2. Add the content fields that belong inside the fieldset.
3. Add the close field.

```php
$fieldgroup = $templates->get('product')->fieldgroup;
$open = $fields->get('details');
$close = $fields->get('details_END');

$fieldgroup->add($open);
$fieldgroup->add('summary');
$fieldgroup->add('body');
$fieldgroup->add($close);
$fieldgroup->save();
```

Do not add only the opener and assume the close field will be positioned correctly for a
programmatic fieldgroup change. The admin UI has convenience hooks for this, but migration
code should add the opener, inner fields, and closer explicitly.

### Repairing a missing close field

If a previous migration or partial failure left an opener without its `{name}_END` field,
saving the opener repairs it:

```php
$open = $fields->get('details');
$fields->save($open);
$close = $fields->get('details_END');
```

You can also call the fieldtype helper directly when you need the close field immediately:

```php
/** @var FieldtypeFieldsetOpen $fieldtype */
$fieldtype = $open->type;
$close = $fieldtype->getFieldsetCloseField($open, true);
```

After repairing a missing close field, make sure the fieldgroup contains the close field in
the correct position. A valid fieldgroup order is always opener, inner fields, closer.

### Deleting programmatically

When deleting a fieldset in code, remove both fields yourself. Remove them from any
fieldgroups before deleting the field definitions.

```php
$open = $fields->get('details');
$close = $fields->get('details_END');

foreach([ $open, $close ] as $field) {
    if(!$field || !$field->id) continue;
    foreach($field->getFieldgroups() as $fieldgroup) {
        $fieldgroup->remove($field);
        $fieldgroup->save();
    }
}

if($open && $open->id) $fields->delete($open);
if($close && $close->id) $fields->delete($close);
```

---

## Notes

- Fieldset fields cannot be set to autojoin or global — those options are hidden in the
  field's advanced settings.
- Compatible fieldtypes: `FieldtypeFieldsetOpen` only (including its subclasses).
- The close field name is always `{opener_name}_END`.

---

# FieldtypeFieldsetTabOpen

Same as `FieldtypeFieldsetOpen` except that the group of fields is rendered as a **tab**
in the page editor rather than a bordered fieldset.

---

## Value type

None — same as `FieldtypeFieldsetOpen`.

---

## How it works

Works identically to `FieldtypeFieldsetOpen` — the open field automatically creates a
paired `{fieldname}_END` close field. The visual difference is that the grouped fields
appear in a tab rather than a collapsible fieldset.

For programmatic creation, repair, fieldgroup ordering, and deletion, use the same
patterns documented above in **FieldtypeFieldsetOpen → Creating fields programmatically**.
Use `FieldsetTabOpen` as the fieldtype name instead of `FieldsetOpen`.

```
Template field order:

  title              ← regular field (not in any tab)
  my_tab             ← FieldtypeFieldsetTabOpen (tab starts here)
  body
  summary
  my_tab_END         ← FieldtypeFieldsetClose   (tab ends here)
```

---

## Settings

| Property | Type | Description |
|---|---|---|
| `modal` | bool | Open the tab in its own modal window. Useful for large forms where loading all tabs at once is slow. Default: `false`. |

The `collapsed` option for tab fieldsets is limited to: always visible, or AJAX-loaded
(the tab content loads only when the tab is clicked).

---

## Notes

- Tab fieldsets do not support `showIf` conditions or `columnWidth`.
- The `modal` setting can be set per-template context (via the template's field settings).

---

# FieldtypeFieldsetClose

Marks the end of a fieldset or tab group opened by `FieldtypeFieldsetOpen` or
`FieldtypeFieldsetTabOpen`. This field type is managed automatically and rarely needs
to be interacted with directly.

---

## Value type

None — stores no data.

---

## How it works

Close fields are created, renamed, and deleted automatically alongside their paired open
field. The close field is always named `{opener_name}_END`.

You only interact with close fields to **position** them within a template's field order —
drag them to wherever you want the group to end.

---

## Notes

- Close fields appear under the "Advanced" filter in the Fields list.
- In the admin UI, do not rename or delete close fields directly — manage them via the
  open field. In migration code, you may need to reference the close field directly for
  ordering, repair, or explicit cleanup.
