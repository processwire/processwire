# WireRandom

Generates random strings, numbers, arrays, and passwords. Generation methods prefer
cryptographically secure sources when available, falling back to `mt_rand()` only where
needed.

`WireRandom` is a standalone utility class — construct it directly:

```php
$rand = new WireRandom();
```

In a module, wire it for proper context:

```php
$rand = $this->wire(new WireRandom());
```

---

## When to use which method

- **`alphanumeric()` / `alpha()` / `numeric()`** — tokens, slugs, short IDs; crypto-secure by default.
- **`string()`** — when you need a specific character set (hex, DNA sequences, custom alphabets).
- **`pass()`** — human-readable temporary passwords; enforces complexity minimums automatically.
- **`base64()`** — bcrypt salts, URL-safe tokens; uses the bcrypt64 alphabet (`. / A-Z a-z 0-9`).
- **`integer()`** — when you need a number in a range; use `cryptoSecure` option for security-sensitive values.
- **`shuffle()`** — when you need a randomized copy of a string or array without modifying the original.

---

## Random strings

### alphanumeric($length, $options)

The primary string generation method. Generates a random alphanumeric string.

```php
$s = $rand->alphanumeric(10);           // e.g. "aB3kR7mNpQ"
$s = $rand->alphanumeric(0);            // random length (10-40 chars by default)
$s = $rand->alphanumeric(8, ['numeric' => false]); // letters only
$s = $rand->alphanumeric(8, ['alpha' => false]);   // digits only
$s = $rand->alphanumeric(8, ['extras' => ['-', '_']]); // include extra chars
```

Options:

| Option      | Default  | Description                                                     |
|-------------|----------|-----------------------------------------------------------------|
| `alpha`     | `true`   | Allow ASCII alphabetic characters                               |
| `upper`     | `true`   | Allow uppercase letters                                         |
| `lower`     | `true`   | Allow lowercase letters                                         |
| `numeric`   | `true`   | Allow digits 0-9                                                |
| `strict`    | `false`  | Require at least one char from each enabled category            |
| `allow`     | `''`     | Only use these characters (overrides alpha/numeric options)     |
| `disallow`  | `[]`     | Exclude these characters                                        |
| `extras`    | `[]`     | Additional non-alphanumeric characters to include               |
| `require`   | `[]`     | These characters must appear in the result                      |
| `minLength` | `10`     | Minimum length when `$length` is 0                              |
| `maxLength` | `40`     | Maximum length when `$length` is 0                              |
| `noRepeat`  | `false`  | Prevent the same character from appearing consecutively         |
| `noStart`   | `''`     | Characters the string cannot start with                         |
| `noEnd`     | `''`     | Characters the string cannot end with                           |
| `fast`      | `true`*  | Use faster method (*`true` when `random_int()` is available)    |

When `allow` or `extras` contains non-alphanumeric characters, `fast` mode is forced
because the crypto-secure path only supports alphanumeric characters.

### alpha($length, $options)

Shortcut for `alphanumeric()` with `numeric=false`.

```php
$s = $rand->alpha(10); // e.g. "kRmNpQaBde"
```

### numeric($length, $options)

Shortcut for `alphanumeric()` with `alpha=false`.

```php
$s = $rand->numeric(6); // e.g. "384721"
```

### string($length, $characters, $options)

Generate a random string from a specific character set.

```php
$s = $rand->string(8, 'ABCDEF0123456789'); // hex-like: "3F9A2C1B"
$s = $rand->string(12, 'ACGT');            // DNA: "ACGTACGTTACG"
$s = $rand->string(10);                    // default set (letters, digits, symbols)
```

Options:

| Option      | Default | Description                              |
|-------------|---------|------------------------------------------|
| `minLength` | `10`    | Minimum length when `$length` is 0       |
| `maxLength` | `40`    | Maximum length when `$length` is 0       |
| `fast`      | `false` | Use `mt_rand()` instead of crypto-secure |

---

## Random numbers

### integer($min, $max, $options)

Return a random integer. Uses `random_int()` (cryptographically secure) when available.

```php
$n = $rand->integer(1, 100);    // 1–100
$n = $rand->integer();          // 0 to PHP_INT_MAX
$n = $rand->integer(['min' => 1, 'max' => 100]); // options-array form
```

Force cryptographic security (throws `WireException` if unavailable):

```php
$n = $rand->integer(0, 100, ['cryptoSecure' => true]);
```

Get info about which random source was used:

```php
list($value, $type) = $rand->integer(0, 100, ['info' => true]);
// $type is one of: 'random_int', 'mcrypt', 'mt_rand'
```

### cryptoSecure()

Check whether a cryptographically secure random source is available.

```php
if($rand->cryptoSecure()) {
    // random_int() is available; all methods are crypto-secure
}
```

---

## Random arrays

### arrayValue($a)

Get a random value from an array.

```php
$color = $rand->arrayValue(['red', 'green', 'blue']); // e.g. "green"
```

### arrayValues($a, $qty)

Get a randomized copy of an array, or a random subset.

```php
$shuffled = $rand->arrayValues(['a', 'b', 'c', 'd']);   // all 4, shuffled
$subset   = $rand->arrayValues(['a', 'b', 'c', 'd'], 2); // 2 random items
```

### arrayKey($a)

Get a random key from an array.

```php
$key = $rand->arrayKey(['name' => 'John', 'age' => 30]); // "name" or "age"
```

### arrayKeys($a, $qty)

Get randomized keys, or a random subset of keys.

```php
$keys = $rand->arrayKeys(['a' => 1, 'b' => 2, 'c' => 3]);      // all keys, shuffled
$keys = $rand->arrayKeys(['a' => 1, 'b' => 2, 'c' => 3], 2);   // 2 random keys
```

---

## Shuffle

### shuffle($value)

Shuffle a string or array. Unlike PHP's `shuffle()`, this returns a new copy (does not
modify in place), preserves array keys, works with strings, and is cryptographically secure.

```php
$s = $rand->shuffle('Hello');         // e.g. "lloHe"
$a = $rand->shuffle([1, 2, 3, 4]);   // e.g. [2 => 3, 0 => 1, 3 => 4, 1 => 2]
```

---

## Password generation

### pass($options)

Generate a human-readable random password that meets complexity requirements. Useful
for generating temporary passwords for new or reset user accounts.

```php
$password = $rand->pass(); // e.g. "kR7-mNp+Qa"
```

Options:

| Option       | Default                 | Description                                        |
|--------------|-------------------------|----------------------------------------------------|
| `minLength`  | `7`                     | Minimum password length                            |
| `maxLength`  | `15`                    | Maximum password length                            |
| `minLower`   | `1`                     | Minimum lowercase letters                          |
| `minUpper`   | `1`                     | Minimum uppercase letters                          |
| `maxUpper`   | `3`                     | Maximum uppercase letters (0=any, -1=none)         |
| `minDigits`  | `1`                     | Minimum digits                                     |
| `maxDigits`  | `0`                     | Maximum digits (0=any, -1=none)                    |
| `minSymbols` | `0`                     | Minimum non-alphanumeric symbols                   |
| `maxSymbols` | `3`                     | Maximum symbols (0=any, -1=none)                   |
| `useSymbols` | `'@#$%^*…'`             | Characters available as symbols                    |
| `disallow`   | `['O','0','I','1','l']` | Characters excluded for readability                |

`maxLength` is automatically increased if the minimum requirements cannot be satisfied
within the specified length range.

---

## Base64 generation

### base64($requiredLength, $options)

Generate a random base64-encoded string using the bcrypt64 alphabet
(`. / A-Z a-z 0-9`), suitable for password salts and URL-safe tokens.

```php
$salt  = $rand->base64(22);        // 22-char bcrypt-style salt
$token = $rand->base64(32);        // 32-char URL-safe token
$fast  = $rand->base64(16, true);  // fast (non-crypto) mode
```

| Option | Default | Description                                               |
|--------|---------|-----------------------------------------------------------|
| `fast` | `false` | Use `mt_rand()` instead of crypto-secure source           |
| `test` | `false` | Return diagnostic info about each random source attempted |

---

## Notes

- **Source file:** `wire/core/Tools/WireRandom/WireRandom.php`
- Methods prefer cryptographically secure sources when available. `integer()` and methods that use it rely on `random_int()` when available. `base64()` tries byte-oriented secure sources like `random_bytes()` first.
- `shuffle()` supports multibyte strings when `mb_substr` is available.
- `base64()` uses the bcrypt64 alphabet, not standard base64 — the output is NOT compatible with `base64_decode()`.
- `alphanumeric()` with `strict` or `require` options uses a retry loop until requirements are met.
