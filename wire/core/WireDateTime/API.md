# WireDateTime

Provides date and time formatting, parsing, and conversion tools.
PHP's built-in date functions cover the basics, but `$datetime` fills the gaps that come up
in real applications: rendering timestamps as human-friendly relative strings ("3 hours ago",
"in 5 months"), expressing elapsed time between two points ("1 day 2 hours 30 minutes"),
and translating month names, day names, and relative-time vocabulary into the current user's
language when LanguageSupport is installed. It also provides a unified `date()` method that
accepts PHP `date()` formats, `strftime()` formats, and special keywords all in one call,
a safer `strtotime()` that handles empty and zero dates without errors, and a drop-in
`strftime()` replacement for PHP 8.1+ where the built-in was deprecated.

`WireDateTime` is accessible in templates as `$datetime` or `wire()->datetime`, and in modules
as `$this->wire()->datetime`.

---

## Formatting dates

### `date()`

The primary date-formatting method. Accepts PHP `date()` format strings, `strftime()` format
strings (detected by a `%` character), or one of the special keywords below.

```php
// PHP date() format
echo $datetime->date('Y-m-d H:i', $page->created); // "2024-04-01 17:32"

// strftime() format (% detected automatically)
echo $datetime->date('%A, %B %-d, %Y', $page->created); // "Monday, April 1, 2024"

// Omit timestamp to use current time
echo $datetime->date('F j, Y'); // "April 1, 2024"

// Pass a strtotime()-compatible string as the timestamp
echo $datetime->date('Y-m-d', 'next Monday');

// Blank format string uses $config->dateFormat
echo $datetime->date('', $page->created);
```

**Special format keywords:**

| Keyword     | Past example                      | Future example      |
|-------------|-----------------------------------|---------------------|
| `relative`  | "2 days ago"                      | "5 months from now" |
| `relative-` | "2 days"                          | "5 months"          |
| `rel`       | "2 days ago"                      | "in 5 months"       |
| `rel-`      | "2 days"                          | "5 months"          |
| `r`         | "-2d"                             | "+5mo"              |
| `r-`        | "2d"                              | "5mo"               |
| `ts`        | Returns unix timestamp as integer |                     |
| `''`        | Uses `$config->dateFormat`        |                     |

```php
echo $datetime->date('relative', $page->modified); // "3 hours ago"
echo $datetime->date('r', $page->created);          // "-14d"
$ts = $datetime->date('ts', '2024-04-01');          // 1711929600
```

### `strftime()`

A drop-in replacement for PHP's `strftime()`, compatible with PHP 8.1+ where the built-in
`strftime()` was deprecated. Translates month/day names when LanguageSupport is installed.

```php
echo $datetime->strftime('%A, %B %-d, %Y', $page->created);
// "Monday, April 1, 2024"
```

---

## Parsing date strings

### `strtotime()`

An enhanced version of PHP's `strtotime()`. Returns `null` for empty or zero-only dates
(like `0000-00-00`) instead of throwing errors as PHP 8 does. Added in 3.0.178.

```php
// Basic usage — same as PHP's strtotime()
$ts = $datetime->strtotime('April 1, 2024');

// Empty and zero dates return null by default (not false/error)
$ts = $datetime->strtotime('');           // null
$ts = $datetime->strtotime('0000-00-00'); // null

// Custom return value for empty/zero dates
$ts = $datetime->strtotime('', ['emptyReturnValue' => 0]); // 0

// Specify input format when string is not strtotime()-compatible (3.0.238+)
$ts = $datetime->strtotime('01/04/2024', ['inputFormat' => 'd/m/Y']);

// Return as formatted string instead of timestamp (3.0.238+)
$str = $datetime->strtotime('April 1, 2024', ['outputFormat' => 'Y-m-d']); // "2024-04-01"

// Base timestamp for relative date strings
$ts = $datetime->strtotime('+7 days', ['baseTimestamp' => $page->created]);
```

**Options:**

| Option             | Type             | Default | Description                                                         |
|--------------------|------------------|---------|---------------------------------------------------------------------|
| `emptyReturnValue` | int\|null\|false | `null`  | Return value for empty or zero-only date strings                    |
| `baseTimestamp`    | int\|null        | `null`  | Base timestamp for relative date calculations                       |
| `inputFormat`      | string           | `''`    | Format for `$str` when not strtotime()-compatible                   |
| `outputFormat`     | string           | `''`    | Return formatted string in this format (delegates to `strtodate()`) |

### `strtodate()`

Like `strtotime()` but returns a formatted date string instead of a unix timestamp.
Returns a blank string on failure (not `null` or `false`). Added in 3.0.238.

```php
// Default format is Y-m-d H:i:s
echo $datetime->strtodate('April 1, 2024');          // "2024-04-01 00:00:00"

// Specify output format
echo $datetime->strtodate('04/01/2024', 'F j, Y');   // "April 1, 2024"

// Specify input format when string is not strtotime()-compatible
echo $datetime->strtodate('01/04/2024', 'F j, Y', ['inputFormat' => 'd/m/Y']);
// "April 1, 2024"

// 4-digit year expands to January 1 of that year
echo $datetime->strtodate('2024', 'Y-m-d');          // "2024-01-01"

// Returns blank on failure
echo $datetime->strtodate('not-a-date');              // ""
```

### `stringToTimestamp()`

Convert a date string with a known PHP `date()` format to a unix timestamp. Uses
`date_parse_from_format()` when available, with a regex fallback.

```php
$ts = $datetime->stringToTimestamp('04/01/2024', 'm/d/Y');

// Already a timestamp — returned as-is
$ts = $datetime->stringToTimestamp('1711929600', 'Y-m-d'); // 1711929600

// Returns empty string for empty input
$ts = $datetime->stringToTimestamp('', 'Y-m-d'); // ''
```

---

## Relative and elapsed time

### `relativeTimeStr()`

Returns a human-readable string expressing a timestamp relative to now. Hookable.
Multi-language aware via ProcessWire translations.

```php
echo $datetime->relativeTimeStr($page->created);              // "2 days ago"
echo $datetime->relativeTimeStr(strtotime('+5 days'));         // "5 days from now"
echo $datetime->relativeTimeStr(time());                       // "just now"

// Medium abbreviations (true): slightly shorter phrasing, "in X" instead of "X from now"
echo $datetime->relativeTimeStr($page->created, true);         // "2 days ago"
echo $datetime->relativeTimeStr(strtotime('+5 days'), true);   // "in 5 days"

// Extra-short abbreviations (integer 1): prefix sign, no spaces
echo $datetime->relativeTimeStr($page->created, 1);            // "-2d"
echo $datetime->relativeTimeStr(strtotime('+5 days'), 1);      // "+5d"

// Without tense
echo $datetime->relativeTimeStr($page->created, false, false); // "2 days"
echo $datetime->relativeTimeStr($page->created, 1, false);     // "2d"

// Custom term substitutions
echo $datetime->relativeTimeStr($page->created, ['ago' => 'back', 'days' => 'dys']);
// "2 dys back"
```

### `elapsedTimeStr()`

Returns a human-readable string expressing elapsed time between two timestamps.
Maximum period used is weeks (months and years are not fixed-length). Added in 3.0.129.

```php
// Elapsed time from a timestamp to now
echo $datetime->elapsedTimeStr($page->created);
// "5 days 3 hours 21 minutes 4 seconds"

// Between two timestamps or date strings
echo $datetime->elapsedTimeStr('2024-01-01 08:00:00', '2024-01-02 10:30:00');
// "1 day 2 hours 30 minutes"

// Medium abbreviations (true)
echo $datetime->elapsedTimeStr($start, $stop, true); // "1 day 2 hrs 30 mins"

// Extra-short abbreviations (integer 1)
echo $datetime->elapsedTimeStr($start, $stop, 1);    // "1d 2hr 30m"

// Digital clock format (integer 0)
echo $datetime->elapsedTimeStr($start, $stop, 0);    // "26:30:00"

// Options-array form (3.0.227+): specify $stop and include/exclude inside options
echo $datetime->elapsedTimeStr($start, [
    'stop'    => $stop,
    'include' => 'hours minutes', // show only these periods
]);

// Exclude specific periods
echo $datetime->elapsedTimeStr($start, $stop, true, ['exclude' => 'seconds']);

// Return as array (3.0.227+)
$data = $datetime->elapsedTimeStr($start, $stop, false, ['getArray' => true]);
// [
//   'days' => 1, 'daysText' => '1 day',
//   'hours' => 2, 'hoursText' => '2 hours',
//   'minutes' => 30, 'minutesText' => '30 minutes',
//   'negative' => false,
//   'text' => '1 day 2 hours 30 minutes'
// ]
```

**Options:**

| Option      | Type          | Default | Description                                                                  |
|-------------|---------------|---------|------------------------------------------------------------------------------|
| `delimiter` | string        | `' '`   | Separator between time periods                                               |
| `exclude`   | array\|string | `[]`    | Periods to exclude: `'seconds'`, `'minutes'`, `'hours'`, `'days'`, `'weeks'` |
| `include`   | array\|string | `[]`    | Periods to include only (3.0.227+)                                           |
| `getArray`  | bool          | `false` | Return array instead of string (3.0.227+)                                    |
| `stop`      | int\|string   | `null`  | Ending timestamp or date string when using options-array form (3.0.227+)     |

---

## Format conversion

### `convertDateFormat()`

Converts a PHP `date()` format string to an equivalent format for JavaScript, `strftime()`,
or a regular expression. Useful when integrating with front-end date pickers or validating
date input.

```php
// Convert to JavaScript (jQuery UI datepicker) format
$js = $datetime->convertDateFormat('Y-m-d', 'js');        // "yy-mm-dd"

// Convert to strftime() format
$sf = $datetime->convertDateFormat('Y-m-d', 'strftime');  // "%Y-%m-%d"

// Convert to regex for validating date strings
$re = $datetime->convertDateFormat('Y-m-d', 'regex');
// Named capture groups: (?<Y>\d{4})-(?<m>\d{2})-(?<d>\d{2})
if(preg_match("!^$re$!", $input)) { /* valid */ }
```

### `getDateFormats()` / `getTimeFormats()`

Return arrays of all predefined date and time format strings (in PHP `date()` syntax).
These are the same formats shown in date/time field configuration dropdowns in the admin.

```php
$dateFormats = $datetime->getDateFormats(); // e.g. ['Y-m-d', 'n/j/Y', ...]
$timeFormats = $datetime->getTimeFormats(); // e.g. ['H:i', 'g:i a', ...]
```

---

## Notes

- **Source file:** `wire/core/WireDateTime/WireDateTime.php`
- **API variable:** `$datetime` (also `$this->wire()->datetime` or `wire('datetime')`)
- **Multi-language:** `date()`, `strftime()`, and `relativeTimeStr()` automatically translate
  month names, day names, and relative-time vocabulary when LanguageSupport is installed and phrases translated.
- **PHP 8.1 compatibility:** PHP's built-in `strftime()` was deprecated in PHP 8.1; use
  `$datetime->strftime()` or `$datetime->date()` with a `%`-prefixed format string as a replacement
- **`$config->dateFormat`:** When `date()` is called with an empty format string, it falls back
  to the site-wide default from `$config->dateFormat`
- **Choosing the right method:** Use `date()` to format an existing timestamp, `strtotime()` to
  convert a string to a timestamp, and `strtodate()` to convert one date string format to another
