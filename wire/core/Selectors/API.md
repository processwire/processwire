# Selectors

`Selectors` parses a ProcessWire selector string (or selector array) into an
iterable collection of `Selector` objects, and can test whether a given
`Wire`-derived item matches those conditions. It is the engine behind selector
matching used throughout the core (`$pages->find()`, `WireArray::find()`,
`$page->matches()`, etc.).

Two classes live in this directory:

- **`Selectors`** (`Selectors.php`) — extends `WireArray`; holds a set of
  `Selector` objects, parses strings/arrays into them, and matches items.
- **`Selector`** (`Selector.php`) — the abstract base for a single condition
  (field + operator + value), with one concrete subclass per operator
  (`SelectorEqual`, `SelectorContains`, `SelectorGreaterThan`, etc.). The
  operator is fixed by the subclass and cannot be changed on an existing
  instance.

Most code never constructs these directly — you pass selector strings to API
methods like `$pages->find('template=basic-page, limit=10')`. Use this class
directly when you need to parse, inspect, or match selectors yourself (for
example in a module that filters arbitrary `Wire` objects).

```php
$selectors = new Selectors();
$selectors->init("sale_price|retail_price>100, currency=USD|EUR");

if($selectors->matches($page)) {
    // $page matches the selector
}
```

The constructor also accepts the selector directly, though a separate `init()`
call is the documented preference:

```php
$selectors = new Selectors("template=basic-page, limit=5");
```

---

## Parsing a selector string

Pass a selector string to the constructor or `init()`. The string is split into
individual `Selector` objects (one per comma-separated condition), each
exposing its field(s), operator, and value(s).

```php
$selectors = new Selectors("template=basic-page, title%=about, sort=-modified, limit=5");

foreach($selectors as $selector) {
    echo $selector->field();    // e.g. "template"
    echo $selector->operator(); // e.g. "="
    echo $selector->value();    // e.g. "basic-page"
}
```

### OR conditions with the pipe

A pipe `|` in a field or value indicates an OR condition. The corresponding
`field()`/`value()` accessors return a string for a single item, or an array
when multiple are present. Use the always-array forms `fields()` and `values()`
when you don't want to special-case that.

```php
$selectors = new Selectors("title|body|summary%=foo|bar");
$s = $selectors->first();
print_r($s->fields()); // ['title', 'body', 'summary']
print_r($s->values()); // ['foo', 'bar']
```

### Casting back to a string

```php
$selectors = new Selectors("template=basic-page, limit=5");
echo (string) $selectors; // "template=basic-page, limit=5"
```

---

## Parsing a selector array

`init()` (and the constructor) also accept an array. This is useful when values
come from variables, because each value is sanitized automatically. Several
formats are supported and may be mixed in one array.

```php
// Associative: field => value (operator defaults to "=")
$selectors = new Selectors([
    'template' => 'basic-page',
    'title'    => 'About Us',
]);

// Operator appended to the key
$selectors = new Selectors([
    'title%=' => 'about',     // title %= about
    'created>' => '2024-01-01',
]);

// Indexed verbose entries
$selectors = new Selectors([
    [ 'field' => 'title', 'operator' => '%=', 'value' => 'about' ],
    [ 'field' => 'price', 'operator' => '>',  'value' => 100 ],
]);

// Self-contained "key=value" strings (also mixable with the above)
$selectors = new Selectors([
    "template=article",
    "sort=-date",
    "limit" => 3,
]);
```

### OR groups in selector arrays

An indexed associative sub-array represents one OR group. At least one selector
inside the group must match:

```php
$selectors = new Selectors([
    [
        'title%=' => 'processwire',
        'summary%=' => 'processwire',
    ],
    'template' => 'article',
]);
```

Multiple indexed associative sub-arrays are multiple OR groups, and each group
must have at least one matching selector.

### Verbose array options

A verbose entry is an associative array that supports these keys:

- `field` (or `fields`) — field name or array of names. **Required.**
- `value` (or `values`) — value or array of values. Required unless `find` used.
- `operator` — defaults to `=`.
- `not` — boolean; makes it a NOT condition.
- `sanitize` (or `sanitizer`) — sanitizer method applied to each value
  (default `selectorValue`).
- `whitelist` — array of allowed values; a value not in the list throws a
  `WireException`.
- `group` (or `or`) — OR-group name.
- `find` — a sub-selector array used instead of `value`.

```php
$selectors = new Selectors([
    [
        'field'     => 'status',
        'operator'  => '=',
        'value'     => $userInput,
        'whitelist' => ['active', 'pending', 'closed'],
    ],
]);
```

Field names in array form are validated with `$sanitizer->fieldName()`; an
invalid field name throws a `WireException`. Array input is the safest way to
build selectors from user-supplied data.

---

## Matching items

`matches(Wire $item)` returns true if the item satisfies every condition
(conditions are ANDed; pipes within a condition are ORed).

```php
$selectors = new Selectors("color=blue, qty>3");

$item = new WireData();
$item->color = 'blue';
$item->qty = 5;

$selectors->matches($item); // true
```

If the item implements `WireMatchable` (as `Page` does), matching is delegated
to that object's own `matches()` method. For other `WireData` objects, dot
syntax field names (e.g. `parent.title`) are resolved via `getDot()`.

You can also match a single condition directly with a `Selector`:

```php
$s = new SelectorEqual('title', 'About Us');
if($s->matches($page)) {
    // $page->title === 'About Us'
}
```

---

## Inspecting selectors

### `getSelectorByField($fieldName, $or = false, $all = false)`

Returns the first `Selector` whose field matches the given name, or `null`.
Handy for reading reserved properties such as `limit`, `start`, or `include`.

```php
$selectors = new Selectors("template=basic-page, limit=5");
$limit = $selectors->getSelectorByField('limit');
echo $limit ? $limit->value() : 'no limit'; // "5"
```

- `$or` — also consider fields that appear inside an OR expression (`a|b|c`).
- `$all` — return an array of all matching selectors instead of just the first
  (the return type becomes an array, empty if none).

### `getSelectorByFieldValue($fieldName, $value, $or = false, $all = false)`

Like the above, but also requires the value to match. (3.0.142+)

### `getAllFields($subfields = true)`

Returns an array (keyed by field name) of every field referenced across all selectors.
Pass `false` to collapse `field.subfield` to just `field`.

```php
$selectors = new Selectors("title%=foo, parent.name=bar");
print_r($selectors->getAllFields());        // ['title' => 'title', 'parent.name' => 'parent.name']
print_r($selectors->getAllFields(false));   // ['title' => 'title', 'parent' => 'parent']
```

### `getAllValues()`

Returns an array (keyed by value) of every value referenced across all selectors.

```php
$selectors = new Selectors("template=basic-page, status=1|2");
print_r($selectors->getAllValues()); // ['basic-page' => 'basic-page', '1' => '1', '2' => '2']
```

---

## Static helpers

These analyze selector/operator strings without needing a `Selectors`
instance. All are static.

### `Selectors::stringHasOperator($str, $getOperator = false)`

True if the string appears to contain a selector operator preceded by a valid
field name. Pass `true` for `$getOperator` to return the operator string
instead of a boolean. Math-like strings (e.g. `1+1`) are not treated as
selectors.

```php
Selectors::stringHasOperator("title=foo");   // true
Selectors::stringHasOperator("1+1");          // false
Selectors::stringHasOperator("title%=foo", true); // "%="
```

### `Selectors::stringHasSelector($str)`

Stricter check that the whole string parses as one or more valid selectors.

### `Selectors::isOperator($operator, $returnOperator = false)`

True if the given string is a recognized operator. With `$returnOperator`,
returns the corrected operator string (fixing minor mix-ups like reversed
order) or `false`.

```php
Selectors::isOperator('%=');   // true
Selectors::isOperator('xyz');  // false
```

### `Selectors::getOperatorType($operator, $is = false)`

Returns a short type name for the operator (e.g. `'='` → `"Equal"`,
`'*='` → `"Contains"`), or `false` if unrecognized. Pass `true` for `$is` to get
a boolean.

### `Selectors::getOperators(array $options = [])`

Returns information about all available operators. Options control indexing and
value type:

- `operator` — restrict to a single operator (return value is then a single
  value, not an array).
- `compareType` — filter to operators matching a `Selector::compareType*`
  constant.
- `getIndexType` — `'operator'`, `'className'`, `'class'`, or `'none'`
  (default `'class'`).
- `getValueType` — `'operator'`, `'class'`, `'className'`, `'label'`,
  `'description'`, `'compareType'`, or `'verbose'` (default `'operator'`).

```php
// All operators as a simple list
$ops = Selectors::getOperators(['getIndexType' => 'none']);

// Verbose info keyed by operator
$info = Selectors::getOperators([
    'getIndexType' => 'operator',
    'getValueType' => 'verbose',
]);
```

### `Selectors::getOperatorChars()` / `Selectors::getReservedChars()`

Return the set of characters used by operators, and the special characters with
meaning in selectors (`|`, `!`, `,`, `@`, quotes, group/sub-selector
delimiters).

### `Selectors::newSelector($field, $operator, $value)` (3.0.260+)

Construct the correct `Selector` subclass for an operator. Throws if the
operator is unrecognized.

```php
$s = Selectors::newSelector('title', '%=', 'about');
```

### `Selectors::getSelectorByOperator($operator, $property = 'instance')` (3.0.160+)

Returns a blank `Selector` instance for the operator (populate its `field` and
`value` afterward), or a single requested property: `'label'`, `'compareType'`,
`'class'`, or `'className'`.

---

## Working with a single Selector

A `Selector` exposes its parts as methods and as magic properties.

```php
$s = $selectors->first();

$s->field();       // string: one field, or multiple fields joined with "|"
$s->field(false);  // string if one field, array if multiple
$s->fields();      // always an array of field names
$s->operator();    // operator string, e.g. "%="
$s->value();       // string: one value, or multiple values joined with "|"
$s->value(false);  // string if one value, array if multiple
$s->values();      // always an array of values

// Magic properties
$s->field; $s->fields; $s->value; $s->values;
$s->operator;        // read-only; fixed by the subclass
$s->not;             // bool, is this a NOT condition
$s->group;           // OR-group name or null
$s->quote;           // quote char the value was wrapped in, or ''
$s->str;             // string form of this selector, e.g. "title%=about"
```

Per-subclass metadata is available statically: `getOperator()`, `getLabel()`,
`getDescription()`, and `getCompareType()`.

---

## Notes

- **Source files:** `wire/core/Selectors/Selectors.php` and
  `wire/core/Selectors/Selector.php`.
- **Selector language docs:** for broader page-finding selector syntax and
  examples, see <https://processwire.com/docs/selectors/>.
- **Operator reference:** for detailed selector operator behavior, see
  <https://processwire.com/docs/selectors/operators/>.
- **`Selectors` extends `WireArray`**, so all `WireArray` methods (iteration,
  `first()`, `count()`, `each()`, etc.) are available on a parsed selector set.
- **Conditions are ANDed; pipes are ORed.** Multiple comma-separated selectors
  must all match; pipe-separated fields or values within one selector match if
  any one does.
- **Operator is immutable on a `Selector`** — it is determined by the concrete
  subclass. To change an operator, create a new `Selector` (e.g. with
  `Selectors::newSelector()`).
- **Prefer array input for user data.** When building selectors from untrusted
  input, use the array form so values are sanitized (default `selectorValue`)
  and can be constrained with a `whitelist`. If building strings manually,
  sanitize values yourself with `$sanitizer->selectorValue()`.
- **API variable references** in `[...]`-quoted values are resolved at parse time:
  `template=[page.template]` expands to the current page's template name. Variables
  available by default: `page`, `user`, `session`, plus any registered via
  `setCustomVariableValue()`.
- **`field()`/`value()` vs `fields()`/`values()`:** the singular method forms
  always return a string by default — multiple items are joined with `"|"`. Pass
  `false` to get string-or-array instead (same as the magic property `$s->field`).
  The plural `fields()`/`values()` always return arrays regardless.
- **Values containing commas** must be quoted or supplied via the array form, since
  commas are the condition separator in selector strings:
  `title="hello, world"` or `['title' => 'hello, world']`.
