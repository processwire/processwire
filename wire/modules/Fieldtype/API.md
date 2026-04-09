# FieldtypeCheckbox

Stores an ON/OFF toggle as an integer. The checked (ON) value is `1`; the unchecked (OFF) value is `0`.

---

## Value type

`int` — always `1` or `0`.

---

## Getting and setting values

```php
// Get
$page->checkbox_field       // int: 1 or 0
(bool) $page->checkbox_field  // cast to bool if needed

// Set
$page->checkbox_field = 1;  // check it
$page->checkbox_field = 0;  // uncheck it
$page->checkbox_field = true;   // also valid — sanitized to 1
$page->checkbox_field = false;  // also valid — sanitized to 0
$page->save('checkbox_field');
```

---

## Selectors

```php
// Find pages where checkbox is checked
$pages->find('checkbox_field=1');

// Find pages where checkbox is NOT checked
// Note: unchecked rows are not stored in the database, so this uses a LEFT JOIN
$pages->find('checkbox_field!=1');

// Equivalent to the above (unchecked = 0)
$pages->find('checkbox_field=0');
```

> **Note:** Unchecked pages do not have a row in the field's database table. The `!=1` selector
> handles this via a LEFT JOIN to correctly include pages that were never checked.

---

## Output / markup

`$page->renderValue('checkbox_field')` renders differently depending on context:

- **In the admin:** renders a FontAwesome checkbox icon (`fa-check-square-o` or `fa-square-o`)
- **Outside the admin:** renders a disabled HTML `<input type="checkbox">`

In both cases a hidden `<span>` with a UTF-8 checkbox character (`☒` or `☐`) is included so
the value survives tag-stripping.

Other common use cases: 
```php
if($page->checkbox_field) {
  echo "Yes";
} else {
  echo "No";
}

echo $page->checkbox_field ? 'Yes' : 'No';

echo $page->if('checkbox_field', 'Yes', 'No');
```

---

## Notes

- The blank/default value is `0` (unchecked).
- Any truthy PHP value set on the field is sanitized to `1`; any falsy value to `0`.
- Compatible fieldtypes: `FieldtypeCheckbox`, `FieldtypeToggle` (if installed).
- Database column: `tinyint NOT NULL`, indexed.

---

# FieldtypeText

Stores a single line of plain text. 

---

## Value type

Always a string, whether blank or populated. No newlines. 

---

## Getting and setting values

```php
// Get
$page->text_field; // string
$page->get('text_field'); // string
$page->getUnformatted('text_field'); // get without Textformatters applied
$page->getFormatted('text_field'); // get with Textformatters applied

// Set 
$page->text_field = 'Hello World'; // set value
$page->text_field = '';  // set blank
$page->save('text_field'); // save
$page->setAndSave('text_field', 'Foo bar'); // set and save together
```
Note that output formatting should be OFF when saving text fields (or any 
fields for that matter):
```php
$page->of(false); // turn off output formatting
$page->text_field = 'foobar';
$page->save();
$page->of(true); // turn back on when applicable
```
The `$page->setAndSave()` does not require that output formatting is off,
so be careful not to set and save an already formatted value with it:
```php
// output formatting on and value is entity encoded
$value = $page->get('text_field'); // value='This &amp; That'
$page->setAndSave('text_field', "$value oops!"); 
echo $page->get('text_field'); // outputs corrupted value: This &amp;amp; That oops!'
```
---

## Selectors

```php
// Find pages where text equals value 'foobar'
$pages->find('text_field=foobar');

// Find pages where text_field is empty
$pages->find('text_field=""'); 

// Find pages where text contains 'foobar' 
$pages->find('text_field*=foobar'); // fulltext match
$pages->find('text_field%=foobar'); // LIKE match

// Find pages where text starts with 'foobar'
$pages->find('text_field^=foobar'); 

// Find pages where text has independent word 'foobar'
$pages->find('text_field~=foobar'); 

// Find pages where text has word 'foo' or word 'bar'
$pages->find('text_field~=foo|bar'); 

// Find pages where text has word 'foo' AND word 'bar' in the value
$pages->find('text_field~=foo, text_field~=bar'); 

// Find pages where $value originated from user input
$value = $sanitizer->selectorValue($input->get('foo')); 
$pages->find("text_field=$value"); 
```
Above are just a few examples. Many other operator usage cases are possible with 
FieldtypeText. Please see [ProcessWire Selector Operators](https://processwire.com/docs/selectors/operators/)
for details on all the operators that can be used with FieldtypeText. 

## Output / markup

When outputting a text field in HTML please make sure that the text field has the `TextformatterEntities`
or `TextformatterMarkdownExtra` Textformatter and that the accessed `$page` output formatting is ON.
Though note that output formatting is ON by default when responding to a non-admin HTTP request. 

```php
$page->of(true);  // output formatting ON (usually on by default)
echo $page->text_field; // outputs: This &amp; That

$page->of(false);
echo $page->text_field; // outputs: This & That
```

---

## Text field settings 

### textformatters

The `textformatters` setting is an array of Textformatter module class names thare applied
to the text_field value during output formatting.

```php
/** @var Field $field */
$field = $fields->get('text_field');

/** @var array $textformatters */
$textformatters = $field->get('textformatters'); 
// This example adds TextformatterEntities if not already in field setting:
if(!in_array('TextformatterEntities', $textformatters)) {
  $textformatters[] = 'TextformatterEntities';
  $field->set('textformatters', $textformatters); 
  $field->save();
}
```
In the above example `$textformatters` is an array of `Textformatter` module names.
When output formatting is ON, these Textformatter modules are applied to `text_field` value 
every time `$page->text_field` or `$page->get('text_field')` is accessed.  All of the core
Textformatter modules can be found in: `/wire/modules/Textformatter/`

### inputfieldClass
```php
/** @var Field $field */
$field = $fields->get('text_field');
// optionally tell it to use Inputfield other than InputfieldText
$field->set('inputfieldClass', 'InputfieldName'); 
```
### Other settings (via InputfieldText)

```php
/** @var Field $field */
$field = $fields->get('text_field');
$field->set('size', 30); // sets <input> size attribute
$field->set('maxlength', 128); // sets <input> maxlength attribute
$field->set('minlength', 0); // sets <input> minlength attribute
$field->set('placeholder', 'Hello world'); // sets <input> placeholder attribute
$field->set('pattern', '^[a-zA-Z0-9]*$'); // sets <input> pattern attribute
$field->set('required', true); // makes field required
$field->set('requiredAttr', 1); // makes it use HTML5 required attribute
$field->set('stripTags', true); // makes it strip tags from input
$field->set('noTrim', true); // tells it not to trim whitespace from input value
$field->set('showCount', 1); // makes input show a character counter
$field->set('showCount', 2); // makes input show a word counter
$field->save();
```
---

## Notes

- The blank/default value is an empty string. 
- Compatible fieldtypes: Any Fieldtype that extends FieldtypeText. 
- Database column: `text NOT NULL`, indexed.

---

# FieldtypeInteger

Stores a whole number. The blank/unset value is an empty string `''`, not `0`.

---

## Value type

`int` when a value is present, empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->int_field         // int, or '' when blank
(int) $page->int_field   // always int (blank becomes 0)

// Set
$page->int_field = 42;
$page->int_field = -10;
$page->int_field = '';   // clear the value
$page->save('int_field');
```

---

## Selectors

```php
// Exact match
$pages->find('int_field=42');

// No value
$pages->find('int_field=""');

// Comparison
$pages->find('int_field>100');
$pages->find('int_field<0');
$pages->find('int_field>=10, int_field<=100'); // range
```

> **Note on zero vs blank:** By default `0` and blank `""` are equivalent in selectors.
> Enable the **zeroNotEmpty** field setting to make them distinct — then `field=0` matches only
> pages with the value 0, and `field=""` matches only pages with no value.

---

## Notes

- The blank/default value is `''` (empty string), not `0`.
- Setting `zeroNotEmpty=1` makes 0 and blank distinct in selectors.
- Setting `defaultValue` assigns a fallback for pages with no value entered.
- Compatible fieldtypes: `FieldtypeInteger`, `FieldtypeFloat`, `FieldtypeDecimal`, `FieldtypeText`.
- Database column: `int NOT NULL`.

---

# FieldtypeFloat

Stores a floating-point number. Supports single-precision (`float`) or double-precision (`double`) database columns.

---

## Value type

`float` when a value is present, empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->float_field         // float, or '' when blank
(float) $page->float_field // always float (blank becomes 0.0)

// Set
$page->float_field = 3.14;
$page->float_field = '';   // clear the value
$page->save('float_field');
```

---

## Selectors

```php
$pages->find('float_field=3.14');
$pages->find('float_field>1.0');
$pages->find('float_field=""');  // no value
$pages->find('float_field>=1.5, float_field<=9.5'); // range
```

> Same **zeroNotEmpty** behavior as `FieldtypeInteger` — by default `0` and blank are equivalent.

---

## Notes

- Default precision is 2 decimal places. Set `precision` to a different integer, or `-1` to disable rounding.
- Column type is `float` (single-precision) by default. Switch to `double` for higher precision when needed.
- Compatible fieldtypes: `FieldtypeFloat`, `FieldtypeInteger`, `FieldtypeDecimal`, `FieldtypeText`.
- Database column: `float NOT NULL` or `double NOT NULL`.

---

# FieldtypeDecimal

Stores an exact decimal number using MySQL's `DECIMAL` column type. Unlike `FieldtypeFloat`, there are no floating-point rounding errors. The value is always returned as a string to preserve precision.

---

## Value type

`string` (e.g. `"123.45"`) when a value is present, empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->decimal_field    // string, e.g. "123.45" or '' when blank

// Set
$page->decimal_field = '123.45';
$page->decimal_field = 99;     // int/float accepted, converted to string
$page->decimal_field = '';     // clear the value
$page->save('decimal_field');
```

---

## Selectors

```php
$pages->find('decimal_field=123.45');
$pages->find('decimal_field>100');
$pages->find('decimal_field=""');
$pages->find('decimal_field>=10.00, decimal_field<=99.99');
```

> Same **zeroNotEmpty** behavior as `FieldtypeInteger`.

---

## Notes

- Value is always a string (e.g. `"9.99"`) to preserve exact decimal representation.
- Configure `digits` (total digits including before and after decimal, default 10) and `precision` (digits after decimal, default 2). Example: `DECIMAL(10,2)` supports values up to `99999999.99`.
- Schema is updated automatically when `digits` or `precision` settings change.
- Compatible fieldtypes: `FieldtypeDecimal`, `FieldtypeInteger`, `FieldtypeFloat`.
- Database column: `DECIMAL(digits,precision)`.

---

# FieldtypeEmail

Stores a validated email address. Extends `FieldtypeText`.

---

## Value type

`string` — a valid email address, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->email_field  // string, e.g. "user@example.com"

// Set
$page->email_field = 'user@example.com';
$page->email_field = '';  // clear
$page->save('email_field');
```

Values are sanitized through `$sanitizer->email()` — invalid addresses become blank.

---

## Selectors

Supports the same string operators as `FieldtypeText`:

```php
$pages->find('email_field=user@example.com');
$pages->find('email_field=""');           // no email
$pages->find('email_field*=example.com'); // contains
$pages->find('email_field$=.org');        // ends with
```

---

## Output / markup

```php
echo '<a href="mailto:' . $page->email_field . '">' . $page->email_field . '</a>';
```

Apply `TextformatterEntities` (recommended) or entity-encode manually before HTML output.

---

## Notes

- Invalid email addresses are sanitized to blank.
- `allowIDN=1` field setting permits internationalized domain names.
- A unique index option prevents duplicate email addresses across pages.
- Database column: `varchar NOT NULL` (length = DB max index length, typically 191–255).
- Compatible fieldtypes: any fieldtype extending `FieldtypeText`.

---

# FieldtypeURL

Stores a URL — local/relative or absolute. Extends `FieldtypeText`.

---

## Value type

`string` — a valid URL, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->url_field   // string, e.g. "https://example.com/path/"

// Set
$page->url_field = 'https://example.com/path/';
$page->url_field = '/local/path/';  // relative URL (if allowed by field setting)
$page->url_field = '';  // clear
$page->save('url_field');
```

Values are sanitized through `$sanitizer->url()` — invalid URLs become blank.

---

## Selectors

```php
$pages->find('url_field=""');           // no URL
$pages->find('url_field^=https://');    // starts with
$pages->find('url_field*=example.com'); // contains
```

---

## Output / markup

```php
// With TextformatterEntities applied and output formatting ON:
echo '<a href="' . $page->url_field . '">Link</a>';
```

If the `addRoot` field setting is enabled, relative URLs (starting with `/`) are automatically
prepended with the site's root path when output formatting is on.

---

## Notes

- `noRelative=1` disables relative/local URLs; only full protocol URLs are accepted.
- `allowIDN=1` permits internationalized domain names.
- `allowQuotes=1` allows quote characters in URLs (always entity-encode output when used).
- `addRoot=1` prepends the site's root path to relative URLs during output formatting — useful for subdirectory installs.
- Strongly recommended: apply `TextformatterEntities` to any URL field used in HTML output.
- Database column: `text NOT NULL` (inherits from `FieldtypeText`).
- Compatible fieldtypes: any fieldtype extending `FieldtypeText`.

---

# FieldtypeToggle

A yes/no/other toggle with an optional "unknown" (no-selection) state. Unlike `FieldtypeCheckbox`,
Toggle can distinguish between a "no" selection and no selection at all.

---

## Value type

`int|string` — one of: `1` (yes), `0` (no), `2` (other, if enabled), or `''` (unknown / no selection).

Constants: `FieldtypeToggle::valueYes` (1), `::valueNo` (0), `::valueOther` (2), `::valueUnknown` ('').

---

## Getting and setting values

```php
// Get with output formatting OFF (always int or ''):
$page->of(false);
$page->toggle_field;  // 1, 0, 2, or ''

// Get with output formatting ON (type depends on formatType field setting):
$page->of(true);
$page->toggle_field;  // int, bool, or string label

// Set — accepts int, keyword string, or bool:
$page->toggle_field = 1;         // yes
$page->toggle_field = 0;         // no
$page->toggle_field = 2;         // other (if enabled for field)
$page->toggle_field = '';        // unknown / no-selection
$page->toggle_field = 'yes';     // same as 1
$page->toggle_field = 'no';      // same as 0
$page->toggle_field = 'unknown'; // same as ''
$page->toggle_field = true;      // same as 1
$page->toggle_field = false;     // same as 0
$page->save('toggle_field');
```

---

## Selectors

```php
// Yes selected
$pages->find('toggle_field=1');
$pages->find('toggle_field=yes');

// No selected
$pages->find('toggle_field=0');
$pages->find('toggle_field=no');

// No selection (unknown)
$pages->find('toggle_field=""');
$pages->find('toggle_field=unknown');

// Yes or no (any selection made)
$pages->find('toggle_field=1|0');

// No or no-selection
$pages->find('toggle_field=0|""');
```

> **Note:** `0` (no) and `''` (unknown/no-selection) are distinct values. `toggle_field=0` will not
> match pages with no selection, and vice versa.

---

## Output / markup

Depends on the **formatType** field setting (configured under the Details tab):

| formatType | Output |
|---|---|
| `0` Integer (default) | `1`, `0`, `2`, or `''` |
| `1` Boolean | `true` / `false` (no-selection stays `''`) |
| `2` String | Label text (e.g. "Yes", "No") |
| `3` Entities | Label text, HTML-entity encoded |

```php
// formatType=1 (Boolean), output formatting ON:
if($page->toggle_field === true) echo "Yes";
if($page->toggle_field === false) echo "No";
if($page->toggle_field === '') echo "No selection";

// formatType=2 (String), output formatting ON:
echo $page->toggle_field; // outputs "Yes", "No", or custom label
```

---

## Notes

- Four states: `1`=yes, `0`=no, `2`=other (optional), `''`=unknown/no-selection.
- With output formatting OFF, always returns int or blank string regardless of formatType.
- Compatible fieldtypes: `FieldtypeToggle`, `FieldtypeCheckbox`.
- Database column: `tinyint NOT NULL`, indexed.

---

# FieldtypeDatetime

Stores a date and optionally a time. The internal value is always a Unix timestamp (`int`).
Output formatting converts the timestamp to a string using a configurable
[PHP date() format](https://www.php.net/manual/en/datetime.format.php).

---

## Value type

Empty string `''` when blank. When populated, value depends on whether the Page
output formatting is ON or OFF:

- `int` (Unix timestamp) when output formatting is OFF and non-empty value is present.

- `string` (formatted date/time string) when output formatting is ON and non-empty value is present. 

---

## Getting and setting values

```php
// Get (output formatting OFF — raw timestamp):
$page->of(false);
$page->date_field   // int (Unix timestamp) or '' when blank

// Get (output formatting ON — formatted string):
$page->of(true);
$page->date_field   // string, e.g. "8 April 2026" per dateOutputFormat setting

// Set — accepts Unix timestamp, PHP \DateTime, or strtotime-compatible string:
$page->date_field = time();
$page->date_field = strtotime('2026-04-08');
$page->date_field = '2026-04-08 14:30:00';  // strtotime-compatible string
$page->date_field = new \DateTime('2026-04-08');
$page->date_field = '';  // clear the value
$page->save('date_field');
```

---

## Selectors

Comparison operators accept a Unix timestamp, a `strtotime()`-compatible date string, or a
MySQL `Y-m-d H:i:s` string:

```php
// Exact date match
$pages->find('date_field=2026-04-08');

// No value
$pages->find('date_field=""');

// Comparison
$pages->find('date_field>2026-01-01');
$pages->find('date_field<' . time());   // before now
$pages->find('date_field>=2026-01-01, date_field<=2026-12-31'); // within a year

// Not empty (has any date)
$pages->find('date_field!=""');

// Partial date string match
$pages->find('date_field^=2026');        // starts with 2026 (any date in 2026)
$pages->find('date_field^=2026-04');     // starts with 2026-04 (April 2026)
$pages->find('date_field%=2026-04-08'); // contains this date string
```

> **Note on partial matching (`^=`, `%=`):** These match against the stored `Y-m-d H:i:s` MySQL
> string, not the formatted output value. Use `^=YYYY` or `^=YYYY-MM` for year/month-based ranges.

---

## Output / markup

```php
// output formatting ON (default in front-end):
echo $page->date_field;  // formatted string, e.g. "8 April 2026 2:30 pm"

// output formatting OFF:
echo date('Y-m-d', $page->date_field);  // manual formatting from timestamp

// Using WireDateTime for formatting:
echo $datetime->formatDate($page->date_field, 'j F Y');
```

---

## Notes

- The value is stored as a MySQL `datetime` column (`Y-m-d H:i:s`) and loaded as a Unix timestamp.
- Output format is controlled by the `dateOutputFormat` field setting (PHP `date()` format string).
- Per-language output formats are supported when LanguageSupport is installed.
- The default output format is `Y-m-d` (configurable per field instance).
- `dateOutputFormat` can combine date and time: e.g. `'j F Y g:i a'`.
- Compatible fieldtypes: `FieldtypeDatetime` only (and language variants).
- Database column: `datetime NOT NULL`, indexed.

---

# FieldtypeTextarea

Stores multi-line text, optionally as HTML/Markup. Extends `FieldtypeText`.

---

## Value type

`string` — multi-line text or HTML markup, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->body   // string (formatted when output formatting is on)
$page->getUnformatted('body') // raw value, no Textformatters applied

// Set
$page->body = 'Some <b>HTML</b> content.';
$page->save('body');
```

---

## Selectors

Supports the same operators as `FieldtypeText` (fulltext, LIKE, etc.):

```php
$pages->find('body*=keyword');  // fulltext match
$pages->find('body%=keyword');  // LIKE match
$pages->find('body=""');        // no content
```

---

## Output / markup

```php
// output formatting ON (default in front-end):
echo $page->body;  // Textformatters applied, MarkupQA corrections applied for HTML

// output formatting OFF:
echo $page->getUnformatted('body');  // raw stored value
```

When `contentType` is set to HTML, the field automatically corrects `href` and `src` attributes
at save/load time so that URLs remain valid if the site moves to a different subdirectory or domain.

---

## Notes

- `contentType` setting: `0`=plain text (default), `1`=HTML/Markup, `2`=HTML with image management.
- `inputfieldClass` setting: the Inputfield used for editing. Common values: `InputfieldTextarea` (default),
  `InputfieldCKEditor`, `InputfieldTinyMCE`.
- `htmlOptions` setting: array of flags for HTML content type — link abstraction, image alt management,
  removing inaccessible images, lazy loading. Only relevant when `contentType >= 1`.
- Database column: `mediumtext NOT NULL`, fulltext indexed.
- Compatible fieldtypes: any fieldtype extending `FieldtypeText`.

---

# FieldtypePageTitle

Stores a page title. Functionally equivalent to `FieldtypeText` but reserved for use as a
title field. Extends `FieldtypeText`. Inherits all `TextField` settings.

---

## Value type

`string` — the page title, or empty string `''` when blank.

---

## Getting and setting values

```php
// Get
$page->title  // string

// Set
$page->title = 'My Page Title';
$page->save('title');
```

---

## Selectors

Supports the same string operators as `FieldtypeText`:

```php
$pages->find('title=About Us');
$pages->find('title*=keyword');   // fulltext
$pages->find('title^=Welcome');   // starts with
$pages->find('title=""');         // no title
```

---

## Notes

- Compatible only with fieldtypes implementing `FieldtypePageTitleCompatible` (e.g. `FieldtypePageTitleLanguage`).
- Inherits all settings from `TextField` (textformatters, maxlength, etc.).
- Database column: `text NOT NULL`, indexed.

---

# FieldtypePassword

Stores a hashed and salted password. The value is always a `Password` object — the raw hash is
never exposed. Setting a new password is done by assigning a plain-text string.

---

## Value type

`Password` object. The `Password` class provides:
- `$field->pass` (write-only) — set a new plain-text password
- `$field->hash` — the stored hash (read-only)
- `$field->matches('plain')` — verify a plain-text password against the stored hash

---

## Getting and setting values

```php
// Set a new password
$user->pass = 'NewSecurePassword123!';
$user->save('pass');

// Verify a password
if($user->pass->matches('enteredPassword')) {
    // correct password
}

// Check if a password has been set
if($user->pass->hash) {
    // password is set
}
```

Do not read `$user->pass` and re-assign it — doing so may corrupt or double-hash the stored value.

---

## Selectors

Password fields cannot be used in selectors. The hash is not searchable and the plain-text
value is never stored.

---

## Notes

- The password is hashed using bcrypt. The plain-text value is never stored.
- No compatible fieldtypes (password cannot be converted to another type).
- Database columns: `char(40)` for hash, `char(32)` for salt.