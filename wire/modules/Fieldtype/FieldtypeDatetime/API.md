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
