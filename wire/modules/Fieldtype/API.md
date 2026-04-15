> Each Fieldtype subdirectory (e.g. `FieldtypeText/`, `FieldtypeImage/`) has its own
> `API.md`. This file covers only the flat Fieldtype modules that have no subdirectory:
> `FieldtypeFieldsetOpen`, `FieldtypeFieldsetTabOpen`, and `FieldtypeFieldsetClose`.

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

- **Adding** the open field (in the Fields list or in a template) also creates and adds the
  matching close field, named `{fieldname}_END`.
- **Renaming** the open field also renames the close field.
- **Deleting** the open field also deletes the close field.

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
- Do not rename or delete close fields directly — manage them via the open field.
