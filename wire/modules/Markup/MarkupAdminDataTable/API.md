# MarkupAdminDataTable

Renders HTML `<table>` elements for the ProcessWire admin — supports header/footer rows,
sortable columns, resizable columns, responsive (mobile) layouts, action buttons, and
more. Though designed for the admin, it can be used anywhere: the default CSS classes
come from the active admin theme (e.g., `uk-table` for AdminThemeUikit).

```php
$table = $modules->get('MarkupAdminDataTable');
$table->setSortable(true);
$table->headerRow([ 'First name', 'Last name', 'Email' ]);
$table->row([ 'Ryan', 'Cramer', 'ryan@processwire.com' ]);
$table->row([ 'Pete', 'Karges', 'pete@processwire.com' ]);
echo $table->render();
```

Extends [[ModuleJS]] (which extends [[WireData]]), so it inherits the `set()`, `get()`,
`setArray()`, and other WireData methods. The module is **not** singular and **not**
autoload — each `$modules->get('MarkupAdminDataTable')` call returns a fresh instance
with empty rows.

## Constants

| Constant          | Value | Description                                                          |
|-------------------|-------|----------------------------------------------------------------------|
| `responsiveNo`    | `0`   | Responsive mode off — table keeps its layout on all screen sizes     |
| `responsiveYes`   | `1`   | Default — on mobile each `<td>` becomes a row with `<th>` beside it  |
| `responsiveAlt`   | `2`   | On mobile, `<th>` and `<td>` stack vertically instead of side-by-side |

## Properties

Read-only properties (internal state arrays):

| Property      | Type    | Description                                         |
|---------------|---------|-----------------------------------------------------|
| `headerRow`   | `array` | Header row labels as passed to `headerRow()`        |
| `footerRow`   | `array` | Footer row data as prepared from `footerRow()`      |
| `rows`        | `array` | All body rows added via `row()`                     |
| `rowClasses`  | `array` | CSS class strings per row, indexed by row number    |
| `rowAttrs`    | `array` | Attribute arrays per row, indexed by row number     |
| `actions`     | `array` | Action buttons (label => URL) from `action()`      |

Read/write properties (settable via `$table->property = $value` or `$table->set()`):

| Property         | Type             | Default                  | Description                                                       |
|------------------|------------------|--------------------------|-------------------------------------------------------------------|
| `encodeEntities` | `bool`           | `true`                   | Entity-encode cell content (set `false` if pre-encoded or raw HTML)|
| `sortable`       | `bool`           | `true`                   | Enable client-side column sorting (requires a header row)          |
| `resizable`      | `bool`           | `false`                  | Enable client-side column resizing                                |
| `class`          | `string`         | `''`                     | Additional CSS class(es) for the `<table>`                        |
| `caption`        | `string`         | `''`                     | Content for the `<caption>` element                               |
| `responsive`     | `int`            | `responsiveYes` (`1`)     | Responsive mode — use a class constant                             |
| `id`             | `string`         | auto (`AdminDataTableN`)  | HTML `id` attribute (auto-generated when empty)                   |
| `border`         | `int\|string`    | `''`                     | HTML `border` attribute (empty = omitted)                          |
| `settings`       | `array`          | (see init)               | Internal settings array (class names, load flags, etc.)           |

## Adding content

### headerRow(array $labels)

Set the table's header row (`<thead>`). Each element becomes a `<th>`. An element may
itself be a two-element array where index `0` is the label and index `1` is a CSS class
for that `<th>`.

```php
// Simple headers
$table->headerRow([ 'First name', 'Last name', 'Email' ]);

// 4th header cell with a custom class (e.g. right-align it)
$table->headerRow([ 'First name', 'Last name', 'Email', ['Status', 'actions'] ]);
```

Returns `$this` for chaining.

### footerRow(array $labels)

Set the table's footer row (`<tfoot>`). Each element becomes a `<td>`. Values may be
plain strings.

```php
$table->footerRow([ 'Total', '', '$5,200' ]);
```

Returns `$this` for chaining.

### row(array $cells, array $options = [])

Add one body row. Each element of `$cells` becomes a `<td>`. Elements may take several
forms:

| Element form                   | HTML result                                          |
|--------------------------------|------------------------------------------------------|
| `'string'`                     | `<td>string</td>`                                    |
| `['key' => 'value']`           | `<td><a href='value'>key</a></td>`                   |
| `['label' => 'url']` (count 1) | `<td><a href='url'>label</a></td>`                  |
| `['label', 'class']`           | `<td class='class'>label</td>`                       |
| `true`                         | Skip this column — the preceding column expands (colspan) |

The `$options` array supports:

| Option       | Type     | Description                                                         |
|--------------|----------|---------------------------------------------------------------------|
| `separator`  | `bool`   | Show a stronger border above this row (adds `AdminDataListSeparator`) |
| `class`      | `string` | CSS class(es) for the `<tr>`                                        |
| `attrs`      | `array`  | Key/value attributes for the `<tr>` (e.g. `['data-id' => '42']`)   |

```php
// Plain row
$table->row(['Ryan', 'Cramer', 'ryan@processwire.com']);

// First column is a link, second has a CSS class, third is plain
$table->row([
    ['Ryan' => $pages->get(1)->editUrl],
    ['Active', 'uk-text-success'],
    'ryan@processwire.com'
]);

// Separator row with custom class and a data attribute
$table->row(
    ['Q1', '$1,200', '$1,500'],
    ['separator' => true, 'class' => 'highlight', 'attrs' => ['data-quarter' => '1']]
);

// Colspan: 'Summary' expands into the next column (via true)
$table->row(['Summary', true, '$3,000']);
```

Returns `$this` for chaining.

### action(array $action)

Add action button(s) beneath the table. The array maps button labels to URLs
(`['label' => 'url']`). Each button is rendered with [[InputfieldButton]].

```php
$table->action([ 'Continue' => '../next/' ]);
$table->action([ 'Export CSV' => './export/', 'Import' => './import/' ]);
```

Returns `$this` for chaining.

## Settings

### setSortable(bool $sortable)

Enable or disable client-side column sorting (via jQuery TableSorter). When enabled,
a header row is required. Sorting is toggled by clicking `<th>` labels.

```php
$table->setSortable(true);   // enable (default)
$table->setSortable(false);  // disable
```

### setResizable(bool $resizable)

Enable or disable client-side column resizing. When enabled, jQuery TableSorter's
"resizable" widget is loaded.

```php
$table->setResizable(true);
```

### setResponsive(int|bool $mode)

Set the responsive (mobile) behavior using one of the class constants:

```php
$table->setResponsive(MarkupAdminDataTable::responsiveNo);   // off
$table->setResponsive(MarkupAdminDataTable::responsiveYes);   // side-by-side (default)
$table->setResponsive(MarkupAdminDataTable::responsiveAlt);   // stacked
$table->setResponsive(false);                                // same as responsiveNo
```

### setEncodeEntities(bool $encodeEntities = true)

Enable or disable HTML entity encoding of cell content. Enabled by default — set
`false` when content is already entity-encoded or contains intentional HTML markup.

```php
$table->setEncodeEntities(false);
$table->row(['<strong>Bold</strong>', 'Plain text']);
```

### setCaption(string $caption)

Set the content for the `<caption>` element.

```php
$table->setCaption('Page list — sorted by created date');
```

## Column attributes

### setColNotSortable(int $index)

Mark a column (0-indexed) as non-sortable. The `<th>` for that column gets the
`sorter-false` class so jQuery TableSorter ignores it.

```php
// Columns 0 and 1 are checkbox and image — don't sort them
$table->setColNotSortable(0);
$table->setColNotSortable(1);
```

## Table attributes

### setClass(string $class)

Replace the additional CSS class(es) on the `<table>` element. Default CMS classes
like `AdminDataList` are always applied; this sets extra classes on top of them.

```php
$table->setClass('my-custom-table');
```

### addClass(string $class)

Add a CSS class to the `<table>` without replacing existing ones.

```php
$table->addClass('highlight-rows');
$table->addClass('no-stripes');
```

### removeClass(string $class)

Remove a CSS class from the `<table>`. Accepts multiple space-separated classes.
Applied at render time.

```php
$table->removeClass('AdminDataTableSortable');
```

### setID(string $id)

Set the HTML `id` attribute for the `<table>`. If omitted, an auto-generated id
(`AdminDataTable1`, `AdminDataTable2`, …) is assigned.

```php
$table->setID('my-page-list');
```

## Rendering

### render()

Render the full HTML — `<table>` with optional `<caption>`, `<thead>`, `<tfoot>`,
`<tbody>`, optional action buttons, and a `<script>` tag for responsive initialization
when applicable.

```php
echo $table->render();
```

If no rows were added, `render()` returns an empty string (or just the action buttons
if any were defined).

## Hooks

The `render()` method is hookable (`___render()`), allowing you to alter the output:

```php
$wire->addHookAfter('MarkupAdminDataTable::render', function(HookEvent $event) {
    // Add a footnote after every table
    $event->return .= "\n<p class='note'>Updated weekly.</p>";
});
```

## Configurable settings

Default CSS class names and whether to load CSS/JS can be customised via
`$config->MarkupAdminDataTable` (e.g. in `site/config.php`):

```php
$config->MarkupAdminDataTable = [
    'class'             => 'AdminDataTable AdminDataList',
    'addClass'          => '',
    'responsiveClass'   => 'AdminDataTableResponsive',
    'responsiveAltClass'=> 'AdminDataTableResponsiveAlt',
    'sortableClass'     => 'AdminDataTableSortable',
    'resizableClass'    => 'AdminDataTableResizable',
    'loadStyles'        => true,
    'loadScripts'       => true,
];
```

## Notes

- **Instantiation:** `$modules->get('MarkupAdminDataTable')` — the module is not
  singular, so each call returns a fresh, empty instance.
- **CSS & JS files** are registered automatically via [[ModuleJS]] on `init()` and
  appear in `$config->styles` and `$config->scripts`.
- **Client-side sorting, resizing, and responsive behaviour** require jQuery and
  jQuery TableSorter, both bundled with the admin theme. The bundled
  `MarkupAdminDataTable.js` also adds **shift-click** range selection for checkbox
  columns.
- **Entity encoding** is on by default. Set `encodeEntities = false` if you need
  raw HTML in cells.
- **Source file:** `wire/modules/Markup/MarkupAdminDataTable/MarkupAdminDataTable.module`


