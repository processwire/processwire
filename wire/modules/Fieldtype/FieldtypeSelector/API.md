# FieldtypeSelector

Stores a selector string, built visually using a selector builder input. The primary use
case is allowing editors to configure a page-finding query per page — essentially a
"saved search" that can be executed at runtime.

---

## Value type

`string` — a ProcessWire selector string (e.g. `"template=product, sort=-created, limit=10"`).
Empty value is an empty string `''`.

---

## Getting and setting values

```php
// Get the stored selector and use it to find pages
$selector = $page->selector_field;
if($selector) {
    $results = $pages->find($selector);
    foreach($results as $result) {
        echo $result->title;
    }
}

// Set a selector string programmatically
$page->selector_field = 'template=product, sort=-created, limit=10';
$page->save('selector_field');

// Clear the value
$page->selector_field = '';
$page->save('selector_field');
```

---

## Selectors

Stores text and supports fulltext/text-matching operators, the same as `FieldtypeText`:

```php
// Find pages whose selector field contains a specific term
$pages->find('selector_field*="template=product"');

// Find pages with a non-empty selector field
$pages->find('selector_field!=""');
```

---

## Notes

- `initValue`: an enforced prefix selector that is always prepended to the stored value at
  runtime. For example, if `initValue` is `'template=product'`, the field always returns
  `"template=product, [user portion]"`. The prefix is stripped before saving and
  re-prepended on wakeup — `$page->selector_field` always returns the full combined selector.
- `previewColumns`: CSV of field names shown in the selector builder preview
  (e.g. `'name, template, parent'`).
- Compatible fieldtypes: `FieldtypeSelector` and `FieldtypeText`.
- Database column: `data TEXT NOT NULL`, FULLTEXT indexed.

### InputfieldSelector settings

These settings are available on the `SelectorField` instance and are passed through to
`InputfieldSelector`. The most commonly used ones:

| Property | Default | Description |
|---|---|---|
| `preview` | `true` | Show a live selector preview below the input |
| `counter` | `true` | Show live match-count indicator |
| `allowAddRemove` | `true` | Allow adding/removing condition rows |
| `allowModifiers` | `true` | Allow modifiers like `limit`, `sort`, `include` |
| `allowSubselectors` | `true` | Allow use of subselectors |
| `allowSubfields` | `true` | Allow use of subfields |
| `allowSystemNativeFields` | `true` | Allow native system fields (id, name, etc.) |
| `allowSystemCustomFields` | `false` | Allow system custom fields |
| `allowSystemTemplates` | `false` | Allow selection of system templates |
| `allowBlankValues` | `false` | Include rows with no value in the selector |
| `showFieldLabels` | `false` | Show field labels (`true`), names (`false`), or both (`2`) |
| `showOptgroups` | `true` | Group fields by type in the select dropdown |
| `limitFields` | `[]` | Whitelist of selectable field names; empty = all |
| `exclude` | `''` | CSV of field names to exclude from selection |
| `parseVars` | `true` | Parse and resolve variables in the selector |
| `maxUsers` | `20` | Max users in select before switching to text input |
| `maxSelectOptions` | — | Max select options before switching to autocomplete for page refs |
| `dateFormat` | `'Y-m-d'` | PHP date format for date fields |
| `timeFormat` | `'H:i'` | PHP time format for time portion of date fields |
| `addLabel` | `'Add Field'` | Label for the "add condition" link |
| `addIcon` | `'plus-circle'` | Icon for the "add condition" link |
| `optgroupsOrder` | `'system,field,subfield,group,modifier,adjust'` | Order of field option groups |
