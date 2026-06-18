# Fieldtype

Abstract base class for all Fieldtype modules in ProcessWire. A Fieldtype defines how a
field's data is stored, retrieved, sanitized, formatted, and queried. The Fieldtype class
is not instantiated directly — it is extended to create new field types. Descending Fieldtype
modules are also not instantiated directly, they are instead instantiated and used by the 
`Field` class to represent their `type` property. 

`FieldtypeMulti` is a subclass of `Fieldtype` for field types that hold multiple values.
These types of Fieldtype modules extend `FieldtypeMulti` rather than `Fieldtype`. 
(arrays or WireArray objects). See [FieldtypeMulti](#fieldtypemulti) below.

---

## Minimum implementation

Only `sanitizeValue()` is technically required, but most Fieldtypes will also implement
`getDatabaseSchema()`, `getInputfield()`, `getBlankValue()`, `wakeupValue()`, and
`sleepValue()`.

```php
<?php namespace ProcessWire;

class FieldtypeColor extends Fieldtype {

    public static function getModuleInfo() {
        return [
            'title' => 'Color',
            'version' => 1,
            'summary' => 'Stores a hex color value.',
        ];
    }

    public function sanitizeValue(Page $page, Field $field, $value) {
        $value = ltrim($value, '#');
        return ctype_xdigit($value) && strlen($value) === 6 ? $value : '';
    }

    public function getBlankValue(Page $page, Field $field) {
        return '';
    }

    public function getDatabaseSchema(Field $field) {
        $schema = parent::getDatabaseSchema($field);
        $schema['data'] = 'varchar(6) NOT NULL';
        $schema['keys']['data'] = 'KEY data (data)';
        return $schema;
    }

    public function getInputfield(Page $page, Field $field) {
        $f = $this->wire()->modules->get('InputfieldText');
        $f->maxlength = 6;
        return $f;
    }
}
```

---

## Value lifecycle

A. When a value is assigned to a page field (`$page->color = $value`):

```
$value  →  sanitizeValue()  →  [runtime value on $page]
```

B. When `$page->field` is accessed and not yet loaded: 

```
DB row  →  loadPageField()  →  wakeupValue()  →  [runtime value on $page]
```
- When page output formatting is ON the value also passes through the
  Fieldtype `formatValue()` method before returning it to the caller.
- Most field values are lazy-loaded on a page and the loading life cycle 
  only happens on the first access. Whereas for most Fieldtypes, output
  formatting occurs on every access.


C. When a page is saved and a field has changed, this occurs for each changed field:

```
[runtime value on $page]  →  sleepValue()  →  savePageField()  →  DB
```

### sanitizeValue()

**Required.** Called every time a value is assigned to a page field. Should strip or
reject anything invalid. If the value cannot be made valid, return the blank value.

```php
public function sanitizeValue(Page $page, Field $field, $value) {
    return (int) $value; // coerce to integer
}
```

### getBlankValue()

The starting value for new or empty fields. Default if not implemented is blank string.

```php
public function getBlankValue(Page $page, Field $field) {
    return 0;
}
```

### wakeupValue()

Converts the raw database value to a runtime PHP value (e.g. string → object).
Called after `loadPageField()`. Default implementation returns the value unchanged.

```php
public function ___wakeupValue(Page $page, Field $field, $value) {
    // example: convert a stored JSON string to an array
    return $value ? json_decode($value, true) : [];
}
```

### sleepValue()

Converts a runtime PHP value back to a scalar or array suitable for database storage.
Called before `savePageField()`. Must return a string, int, float, or array.

```php
public function ___sleepValue(Page $page, Field $field, $value) {
    return json_encode($value);
}
```

### formatValue()

Applies output formatting when `$page->of(true)` (output formatting on). Only implement
this when the field needs transformation for output (e.g. Markdown → HTML, units, etc.).
Default returns the value unchanged.

```php
public function ___formatValue(Page $page, Field $field, $value) {
    return htmlspecialchars($value);
}
```

---

## Database schema

`getDatabaseSchema()` returns an array describing the field's table columns, indexes,
and engine options. Always call `parent::getDatabaseSchema()` to get the base schema
(which includes `pages_id` and default keys), then customize the `data` column and add
any additional columns or indexes needed.

```php
public function getDatabaseSchema(Field $field) {
    $schema = parent::getDatabaseSchema($field);

    // Override the 'data' column (required minimum)
    $schema['data'] = 'varchar(255) NOT NULL';
    $schema['keys']['data'] = 'KEY data (data(50))';

    // Add additional columns if needed
    $schema['description'] = 'text NOT NULL';

    return $schema;
}
```

Schema array structure:

```php
[
    'pages_id'    => 'int UNSIGNED NOT NULL',        // always present (from parent)
    'data'        => 'int NOT NULL',                 // required: override the type
    'my_col'      => 'varchar(255) NOT NULL',        // optional: extra columns
    'keys'        => [
        'primary' => 'PRIMARY KEY (`pages_id`)',     // always present (from parent)
        'data'    => 'KEY data (`data`)',             // required: add an index for data
        'my_col'  => 'KEY my_col (`my_col`)',         // optional: index for extra cols
    ],
    'xtra'        => [
        'append'  => 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',  // optional
        'all'     => true,  // false if field has external storage (files, etc.)
    ],
]
```

The table name is always `field_{fieldname}`. Never hard-code it — use `$field->table`.
It's not necessary to specify your own `$xtra['append']` unless you want to override the
configured defaults in `$config->dbEngine` and `$config->dbCharset`. 

---

## Inputfield

`getInputfield()` must return the Inputfield module used to edit this field's value in
the admin. The `$page` and `$field` arguments are available if needed, but most
Inputfields don't require them here (the Field class populates standard Inputfield
attributes automatically). Though `FieldtypeFile` is an example of one that does use 
the `$page` and `$field`. If a Fieldtype accepts no interactive input then it should 
return `null`. 

```php
public function getInputfield(Page $page, Field $field) {
    /** @var InputfieldText $f */
    $f = $this->wire()->modules->get('InputfieldText');
    return $f;
}
```

---

## Field configuration

These methods add settings to the field editor in the admin (Setup → Fields → edit field).

### getConfigInputfields()

Returns an `InputfieldWrapper` containing Inputfields for the field's "Details" tab.
Call `parent::getConfigInputfields()` to get the wrapper, then append your Inputfields.

Inputfield names starting with `_` (underscore) are runtime-only and not saved to the DB.

```php
public function ___getConfigInputfields(Field $field) {
    $inputfields = parent::___getConfigInputfields($field);

    $f = $inputfields->InputfieldSelect; // or get from $modules API var
    $f->attr('name', 'palette');
    $f->label = $this->_('Color palette');
    $f->addOptions(['basic', 'extended', 'full']);
    $f->attr('value', $field->get('palette') ?: 'basic');
    $inputfields->add($f);

    return $inputfields;
}
```

### getConfigArray()

Array-based alternative to `getConfigInputfields()`. If both are implemented, both are
used. Most modules implement one or the other. See `InputfieldWrapper::importArray()`
for the format.

### getConfigAllowContext()

Returns an array of Inputfield names from `getConfigInputfields()` / `getConfigArray()`
that are allowed to have template-specific overrides (i.e. different values per
fieldgroup/template context).

```php
public function ___getConfigAllowContext(Field $field) {
    return ['palette', 'defaultValue'];
}
```

---

## Selector matching

`getMatchQuery()` is called by PageFinder when a selector targets this field. The base
`Fieldtype` implementation handles the standard DB comparison operators (`=`, `!=`, `<>`,
`<`, `>`, `<=`, `>=`) and bitwise AND (`&`) against the `data` column. Override if you
need to support additional operators, subfields, or custom SQL.

```php
public function getMatchQuery($query, $table, $subfield, $operator, $value) {
    /** @var PageFinderDatabaseQuerySelect $query */
    // support "color=red" type selectors that convert to hex color code
    $colors = [ 'red' => 'ff0000', 'green' => '00ff00', 'blue' => '0000ff' ];
    $value = strtolower($value);
    if(isset($colors[$value])) $value = $colors[$value];
    return parent::getMatchQuery($query, $table, $subfield, $operator, $value);
}
```

To describe what selectors your field supports (used by admin selector builders),
implement `getSelectorInfo()` — the default implementation covers most single-value types.

---

## Custom Field class

To expose field settings as typed properties with IDE autocompletion, implement
`getFieldClass()` and create a companion `[Type]Field.php` file. The class must extend
`Field` and ideally also contains `@property` PHPDoc annotations, along with any 
other custom methods specific to the field. 

```php
public function getFieldClass(array $a = []) {
    return 'ColorField';
}
```

```php
// ColorField.php
/**
 * @property string $palette 'basic'|'extended'|'full'
 * 
 */
class ColorField extends Field {
    // ...optional custom methods...
}
```

Load the file at the bottom of your Fieldtype module, after the class definition.

```php
require_once(__DIR__ . '/ColorField.php');
```

---

# FieldtypeMulti

Extends `Fieldtype` for field types that hold multiple values. The runtime value is a
`WireArray` (or subclass), and each item is stored as a separate DB row with a `sort`
column for order.

## When to extend FieldtypeMulti

Extend `FieldtypeMulti` when:
- The field can hold more than one value per page (e.g. multiple page references, multiple files, tags)
- The value should be a `WireArray`, `PageArray`, `Pagefiles`, or similar collection type

Extend plain `Fieldtype` when:
- The field holds exactly one value per page (text, integer, date, toggle, etc.)

## Key differences from Fieldtype

- `getDatabaseSchema()` adds a `sort INT UNSIGNED NOT NULL` column and changes the
  primary key to `(pages_id, sort)`.
- `getBlankValue()` returns a `WireArray` (or subclass) instead of a scalar.
- `sanitizeValue()` must return a `WireArray`.
- `sleepValue()` must return an array (each element becomes one DB row).
- `wakeupValue()` receives an array and must return a `WireArray`.
- `savePageField()` Non-paginated: deletes all rows for the page then re-inserts them.
   Paginated: Save/updates current pagination of rows.
- The `count` subfield is supported in selectors automatically: `field.count>2`.

## Minimum FieldtypeMulti implementation

```php
class FieldtypeColorList extends FieldtypeMulti {

    public static function getModuleInfo() {
        return [
            'title' => 'Color List', 
            'version' => 1, 
            'summary' => 'Multiple color values.'
        ];
    }

    public function getBlankValue(Page $page, Field $field) {
        return $this->wire(new WireArray());
    }

    public function sanitizeValue(Page $page, Field $field, $value) {
        if(is_string($value)) $value = explode("\n", $value); 
        $cleanValue = $this->getBlankValue($page, $field);
        if($value instanceof WireArray || is_array($value)) {
            foreach($value as $v) {
                $v = ltrim($v, '#');
                if(ctype_xdigit($v) && strlen($v) === 6) {
                    $cleanValue->add($v);
                }
            }
        }
        return $cleanValue;
    }

    public function ___wakeupValue(Page $page, Field $field, $value) {
        $wakeupValue = $this->getBlankValue($page, $field);
        foreach((array) $value as $v) {
            $wakeupValue->add(ltrim((string) $v, '#'));
        }
        $wakeupValue->resetTrackChanges(true);
        return $wakeupValue;
    }

    public function ___sleepValue(Page $page, Field $field, $value) {
        $sleepValue = [];
        foreach($value as $v) $sleepValue[] = (string) $v;
        return $sleepValue;
    }

    public function getDatabaseSchema(Field $field) {
        $schema = parent::getDatabaseSchema($field); // includes sort column
        $schema['data'] = 'varchar(6) NOT NULL';
        $schema['keys']['data'] = 'KEY data (data)';
        return $schema;
    }

    public function getInputfield(Page $page, Field $field) {
        // in reality you'd likely also have an InputfieldColorList
        return $this->wire()->modules->get('InputfieldTextarea');
    }
}
```

## Sorting and pagination

`FieldtypeMulti` can optionally support automatic sorting and pagination. Both are
opt-in and are set in `__construct()` before calling `parent::__construct()`:

```php
public function __construct() {
    parent::__construct();
    $this->set('useOrderByCols', true); // enable automatic sort column configuration
    $this->set('usePagination', true);  // enable pagination (requires useOrderByCols)
}
```

### useOrderByCols

When `useOrderByCols` is true, a "Sorting and Pagination" fieldset appears in the field
editor. The admin user selects one or more columns to sort by, and the selection is stored
in `$field->orderByCols` (array). Column names are plain strings; prefix with `-` for
descending order; the special value `random` randomizes results.

```php
// Example: what $field->orderByCols might contain
['date', '-title']   // sort by date ASC, then title DESC
['-id']              // sort by id DESC
['random']           // random order
```

`FieldtypeMulti::getLoadQuery()` reads `$field->orderByCols` and applies the ORDER BY
automatically — no override needed in most cases.

### usePagination

When `usePagination` is true (and `useOrderByCols` is also true), a "Pagination limit"
field appears in the field editor alongside the sort selector. The configured limit is
stored in `$field->paginationLimit` (int, 0 = no limit).

When `paginationLimit` is set, rows are loaded in pages driven by `$input->pageNum()`.
The loaded value array includes three extra keys that `FieldtypeMulti::wakeupValue()`
reads automatically:

- `_pagination_limit` — the configured items-per-page limit
- `_pagination_start` — the index of the first loaded item
- `_pagination_total` — the total number of rows across all pages

These are stripped from the array and applied to the WireArray target via
`setLimit()`, `setStart()`, and `setTotal()` — but only if it implements `WirePaginatable`.
Make sure your blank value class implements `WirePaginatable` if you want pagination
to work end-to-end (e.g. `PaginatedArray` and `PageArray` both do).

When `paginationLimit` is active, `savePageField()` automatically delegates to
`savePageFieldRows()` instead of the delete-and-reinsert default, so only the currently
loaded rows are saved rather than wiping rows that weren't loaded.

Also update your `getDatabaseSchema()` to set `xtra.all = false` when pagination is
active, so the system knows not all data may be present in the loaded value:

```php
public function getDatabaseSchema(Field $field) {
    $schema = parent::getDatabaseSchema($field);
    // ... your column definitions ...
    if($field->get('paginationLimit')) {
        $schema['xtra']['all'] = false;
    }
    return $schema;
}
```

---

## Fieldtype interfaces

These interfaces are defined in `Interfaces.php` and can be added to any Fieldtype class.
They signal capability to other parts of the system.

| Interface                      | Purpose                                                                                        |
|--------------------------------|------------------------------------------------------------------------------------------------|
| `FieldtypeHasFiles`            | Fieldtype manages files; must implement `hasFiles()`, `getFiles()`, `getFilesPath()`           |
| `FieldtypeHasPagefiles`        | Fieldtype manages Pagefile objects; must implement `getPagefiles()`                            |
| `FieldtypeHasPageimages`       | Fieldtype manages Pageimage objects; must implement `getPageimages()`                          |
| `FieldtypeDoesVersions`        | Fieldtype manages its own versioning; must implement get/save/restore/deletePageFieldVersion() |
| `FieldtypeLanguageInterface`   | Symbolic only — marks the field as supporting multi-language values                            |

```php
class FieldtypeMyFiles extends FieldtypeMulti implements FieldtypeHasFiles {
    public function hasFiles(Page $page, Field $field) { ... }
    public function getFiles(Page $page, Field $field) { ... }
    public function getFilesPath(Page $page, Field $field) { ... }
}
```

---

## Notes

- Fieldtype modules are **singular** (one instance shared across all uses) and
  **not autoloaded** (loaded only when a field of that type is accessed).
- The `data` column and a key for it are always required in `getDatabaseSchema()`.
- `pages_id` (Fieldtype and FieldtypeMulti) and `sort` (FieldtypeMulti only) are reserved column names.
- Inputfield names starting with `_` in `getConfigInputfields()` are not persisted to DB.
- Field settings saved by `getConfigInputfields()` / `getConfigArray()` are accessible on
  the `$field` object: `$field->get('mySettingName')`.
- `savePageField()` on the base `Fieldtype` uses INSERT ... ON DUPLICATE KEY UPDATE,
  so it handles both inserts and updates in a single query.
- `FieldtypeMulti::savePageField()` deletes all rows for the page then re-inserts them
  on every save; use `savePageFieldRows()` / `deletePageFieldRows()` for targeted
  updates when the table has a single unique primary key.
