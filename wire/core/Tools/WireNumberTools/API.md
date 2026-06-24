# WireNumberTools

Tools for working with numbers, including unique number generation, random integers,
and byte size conversions. Access via `$sanitizer->getNumberTools()` or by instantiating
directly.

```php
// Via $sanitizer
$numTools = $sanitizer->getNumberTools();
$n = $numTools->uniqueNumber();

// Direct instantiation
$numTools = new WireNumberTools();
$bytes = $numTools->strToBytes('10M');
```

The `bytesToStr()` method is also available as a convenience function:

```php
echo wireBytesToStr(1048576); // "1.0 MB"
```

## Unique number generation

### uniqueNumber($options)

Generates an incrementing installation-unique integer backed by a database table.
Each call returns a number guaranteed to be unique across all time and requests.

```php
$n = $numTools->uniqueNumber(); // 1, 2, 3, ...
```

The counter is stored in the database, so uniqueness is guaranteed even across
concurrent requests and server restarts.

#### Namespaced unique numbers

Use namespaces to maintain separate independent counters:

```php
// Create/use a separate counter for invoices
$invoiceNum = $numTools->uniqueNumber('invoices');

// Same using options array
$invoiceNum = $numTools->uniqueNumber(['namespace' => 'invoices']);
```

Each namespace gets its own database table (`unique_num_<namespace>`).

#### Get last number without generating a new one

```php
$lastInvoice = $numTools->uniqueNumber(['namespace' => 'invoices', 'getLast' => true]);
// Returns 0 if no numbers have been generated yet
```

#### Reset a namespace

```php
$numTools->uniqueNumber(['namespace' => 'invoices', 'reset' => true]);
```

Resets by dropping the namespace's database table. Requires a namespace — you cannot
reset the default (unnamed) namespace. Returns `0`.

#### Options

| Option      | Default | Description                                                    |
|-------------|---------|----------------------------------------------------------------|
| `namespace` | `''`    | Separate counter namespace (table name characters: `_a-zA-Z0-9`) |
| `getLast`   | `false` | Return last generated number instead of generating new one      |
| `reset`     | `false` | Drop namespace table and reset counter (namespace required)      |

**Note:** The method auto-creates the database table if it doesn't exist. Every 10
entries, old rows are cleaned up, keeping only the most recent 10 in the table.
Throws `WireException` if unable to generate a unique number.

## Random numbers

### randomInteger($min, $max, $throw)

Returns a cryptographically secure random integer between `$min` and `$max` (inclusive).

```php
$n = $numTools->randomInteger(1, 100);      // random int 1-100
$n = $numTools->randomInteger(0, PHP_INT_MAX); // random int in full range
```

When `$throw` is `true`, a `WireException` is thrown if a cryptographically secure
random number cannot be generated. When `false` (default), it falls back to a
non-cryptographic source.

```php
try {
    $token = $numTools->randomInteger(0, PHP_INT_MAX, true);
} catch(WireException $e) {
    // crypto source unavailable
}
```

## Byte size conversion

### strToBytes($value, $unit)

Convert a human-readable size string to bytes.

```php
$bytes = $numTools->strToBytes('10M');       // 10485760
$bytes = $numTools->strToBytes('2 GB');      // 2147483648
$bytes = $numTools->strToBytes('512kb');     // 524288
$bytes = $numTools->strToBytes('1.5 GB');    // 1610612736
$bytes = $numTools->strToBytes('1024');      // 1024 (already bytes, no unit)
```

Case, spaces, and commas are ignored. Only the first letter of the unit is used
(`b`=bytes, `k`=kilobytes, `m`=megabytes, `g`=gigabytes, `t`=terabytes).

An optional `$unit` parameter forces interpretation:

```php
$bytes = $numTools->strToBytes(512, 'MB'); // 536870912
$bytes = $numTools->strToBytes('512', 'k'); // 524288
```

### bytesToStr($bytes, $options)

Convert a byte count to a human-readable string with appropriate units.

```php
echo $numTools->bytesToStr(1048576);        // "1.0 MB"
echo $numTools->bytesToStr(1536);           // "1.5 kB"
echo $numTools->bytesToStr(1073741824);     // "1.0 GB"
echo $numTools->bytesToStr(0);              // "0 bytes"
echo $numTools->bytesToStr(1);              // "1 byte"
echo $numTools->bytesToStr(512);            // "512 bytes"
```

Unit boundaries use binary values (1024-based): 1024 bytes = 1 kB, 1024 kB = 1 MB,
1024 MB = 1 GB, 1024 GB = 1 TB. By default, 1 decimal place is used for values of
1 kB or higher.

#### Compact output (`small` option)

```php
echo $numTools->bytesToStr(1048576, ['small' => true]);  // "1MB" (no space)
echo $numTools->bytesToStr(1048576, ['small' => 1]);     // "1 MB" (space, but no decimals if zero)
```

#### Force specific unit (`type` option)

```php
echo $numTools->bytesToStr(1048576, ['type' => 'k']); // "1024 kB"
echo $numTools->bytesToStr(1048576, ['type' => 'b']); // "1048576 bytes"
```

#### Custom labels

```php
echo $numTools->bytesToStr(1048576, [
    'labels' => ['m' => 'MB', 'k' => 'KB', 'bytes' => 'B', 'byte' => 'B', 'b' => 'B']
]);
```

#### Custom number formatting

```php
echo $numTools->bytesToStr(1048576, [
    'decimals' => 2,
    'decimal_point' => ',',
    'thousands_sep' => '.'
]);
// "1,00 MB"
```

#### Options

| Option           | Default  | Description                                                       |
|------------------|----------|-------------------------------------------------------------------|
| `decimals`       | `null`   | Decimal places (null = auto: 1 for kB+, 0 for bytes)              |
| `decimal_point`  | `null`   | Decimal point character (null = locale-detected)                  |
| `thousands_sep`  | `null`   | Thousands separator (null = locale-detected)                      |
| `small`          | `false`  | Compact output: `true` = no space, `1` = space but trim decimals  |
| `labels`         | `[]`     | Custom unit labels keyed by: `b`, `byte`, `bytes`, `k`, `m`, `g`, `t` |
| `type`           | `''`     | Force unit: `bytes`/`b`, `kilobytes`/`k`, `megabytes`/`m`, `gigabytes`/`g`, `terabytes`/`t` |

### locale($key)

Get number formatting properties from the current locale. In multi-language environments,
return values are affected by the user's current language.

```php
$locale = $numTools->locale();               // full locale array
$dp = $numTools->locale('decimal_point');     // e.g. "."
$ts = $numTools->locale('thousands_sep');     // e.g. ","
$cs = $numTools->locale('currency_symbol');   // e.g. "$"

// Clear cached locale for current language
$numTools->locale('clear');
```

Common locale properties: `decimal_point`, `thousands_sep`, `currency_symbol`,
`int_curr_symbol`, `mon_decimal_point`, `mon_thousands_sep`, `positive_sign`,
`negative_sign`. See PHP's `localeconv()` for the full list.

## Notes

- `WireNumberTools` is accessed via `$sanitizer->getNumberTools()` or by direct instantiation with `new WireNumberTools()`.
- `uniqueNumber()` creates database tables prefixed with `unique_num` — ensure the DB user has CREATE TABLE permissions.
- `uniqueNumber()` auto-cleans the table every 10 entries, keeping only the most recent rows.
- `randomInteger()` delegates to `WireRandom` internally and prefers `random_int()` (cryptographic) when available.
- `bytesToStr()` uses `$this->_()` for unit labels, so labels are translatable in multi-language installations.
- `strToBytes()` handles negative values and decimal values correctly.
- The `locale()` method caches results per language and supports a `'clear'` key to reset the cache.
- **Source file:** `wire/core/Tools/WireNumberTools/WireNumberTools.php`.
