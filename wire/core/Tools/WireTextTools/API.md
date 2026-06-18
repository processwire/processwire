# WireTextTools

Text and markup manipulation tools: HTML-to-text conversion, string truncation,
placeholder replacement, diff generation, and more. Used internally by `$sanitizer`
and throughout the ProcessWire core.

Access via `$sanitizer` (recommended) or directly:

```php
$tools = $sanitizer->getTextTools(); // preferred — reuses the shared instance
$tools = new WireTextTools();        // or construct directly
```

`$sanitizer` also exposes the most common methods as direct shortcuts
(`$sanitizer->truncate()`, `$sanitizer->markupToText()`, etc.), so you don't need to
go through `getTextTools()` for everyday use.

---

## HTML to text

### markupToText($str, $options)

Convert HTML to readable plain text. More useful than `strip_tags()`: handles paragraph
separation, list bullets, headline formatting, and entity conversion.

```php
$text = $tools->markupToText($html);
$text = $tools->markupToText($html, ['linksToMarkdown' => true]);
$text = $tools->markupToText($html, ['keepTags' => ['em', 'strong']]);
```

Options:

| Option               | Default                          | Description                                           |
|----------------------|----------------------------------|-------------------------------------------------------|
| `keepTags`           | `[]`                             | Tags to preserve                                      |
| `clearTags`          | `['script','style','object']`    | Tags whose content is also removed (not just the tag) |
| `splitBlocks`        | `"\n\n"`                         | Separator inserted between block elements             |
| `convertEntities`    | `true`                           | Convert HTML entities to plain text                   |
| `listItemPrefix`     | `'• '`                           | Prefix for `<li>` items                               |
| `linksToUrls`        | `true`                           | Convert `<a href>` to `text (url)` format             |
| `linksToMarkdown`    | `false`                          | Convert links to `[text](url)` Markdown format        |
| `uppercaseHeadlines` | `false`                          | UPPERCASE headline text                               |
| `underlineHeadlines` | `true`                           | Underline headlines with `=` or `-`                   |
| `collapseSpaces`     | `true`                           | Collapse redundant whitespace                         |
| `replacements`       | `['&nbsp;' => ' ']`              | Additional string replacements to apply               |

### collapse($str, $options)

Flatten HTML or multiline text into a single-line plain-text string.

```php
$oneLine = $tools->collapse($multiLineText);
$oneLine = $tools->collapse($html, ['collapseLinesWith' => ' | ']);
```

| Option              | Default | Description                                |
|---------------------|---------|--------------------------------------------|
| `stripTags`         | `true`  | Strip HTML tags                            |
| `keepTags`          | `[]`    | Tags to keep when stripping                |
| `collapseLinesWith` | `' '`   | What to replace newlines/block breaks with |
| `endBlocksWith`     | `''`    | Marker inserted before paragraph/header breaks before collapsing |
| `linksToUrls`       | `false` | Convert links to `(url)` format            |
| `convertEntities`   | `true`  | Convert HTML entities                      |

---

## Truncation

### truncate($str, $maxLength, $options)

Truncate a string without breaking words, with intelligent fallback through truncation
types: `block` → `sentence` → `punctuation` → `word`.

```php
$s = $tools->truncate($str, 150);                          // word boundary
$s = $tools->truncate($str, 300, 'sentence');              // sentence boundary
$s = $tools->truncate($html, 200, ['visible' => true]);    // count visible chars only
$s = $tools->truncate($str, ['type' => 'block', 'maxLength' => 500, 'more' => ' […]']);
```

The `type` specifies the preferred truncation point. If a match can't be found within
`maxLength`, it falls back to the next simpler type automatically.

| Option              | Default   | Description                                                        |
|---------------------|-----------|--------------------------------------------------------------------|
| `type`              | `'word'`  | Preferred point: `'word'`, `'punctuation'`, `'sentence'`, `'block'`|
| `maxLength`         | `255`     | Maximum character length (when options array passed as 2nd arg)    |
| `visible`           | `false`   | Count visible characters only — markup and entities don't count    |
| `maximize`          | `true`    | Include as much content as possible before the truncation point    |
| `trim`              | `',;/ '`  | Characters to trim from the truncated end                          |
| `more`              | `'…'`     | Appended when string is truncated without ending in punctuation    |
| `keepTags`          | `[]`      | HTML tags to preserve in the result                                |
| `keepFormatTags`    | `false`   | Keep inline formatting tags (`em`, `strong`, `span`, etc.)         |
| `collapseLinesWith` | `' … '`   | String used to collapse line breaks                                |
| `convertEntities`   | `false`   | Convert HTML entities                                              |
| `noEndSentence`     | `'Mr. Mrs. ...'` | Space-separated list of words that don't end a sentence     |

The `visible` option is particularly useful when truncating HTML — without it, markup
characters count toward `maxLength` even though they're not visible to readers.

---

## Placeholder replacement

### populatePlaceholders($str, $vars, $options)

Replace `{placeholder}` tags with values. When `$vars` is a `Page`, subfield and OR-tag
syntax are supported.

```php
// Array vars
$result = $tools->populatePlaceholders('Hello {first_name}!', ['first_name' => 'Ryan']);

// Page vars — subfields and OR tags work
$result = $tools->populatePlaceholders('{title} by {author.name}', $page);
$result = $tools->populatePlaceholders('{display_name|title|name}', $page); // OR: first non-empty
```

`{field1|field2|field3}` with a Page object returns the first non-empty value among
the listed fields — useful as a fallback chain.

| Option            | Default | Description                                                        |
|-------------------|---------|--------------------------------------------------------------------|
| `tagOpen`         | `'{'`   | Opening tag character(s)                                           |
| `tagClose`        | `'}'`   | Closing tag character(s)                                           |
| `recursive`       | `false` | If a replacement value itself contains tags, populate those too    |
| `removeNullTags`  | `true`  | Remove tags that resolve to null (field not present on object)     |
| `removeEmptyTags` | `true`  | Remove tags that resolve to empty string, `false`, or `null`       |
| `entityEncode`    | `false` | Entity-encode replacement values                                   |
| `entityDecode`    | `false` | Entity-decode replacement values                                   |
| `allowMarkup`     | `true`  | Allow HTML in replacement values (uses `getMarkup()` on Pages)     |

When `$vars` is a `Page` and `allowMarkup` is `true`, `$page->getMarkup($field)` is
called (formatted output). Use `'allowMarkup' => false` to get `$page->getText($field)`.

### findPlaceholders($str, $options)

Find all `{placeholder}` tags in a string.

```php
$tags = $tools->findPlaceholders('Hello {name}, welcome to {site}');
// ['name' => '{name}', 'site' => '{site}']

$has = $tools->findPlaceholders($str, ['has' => true]); // bool
```

### hasPlaceholders($str)

Returns `true` if the string contains any `{placeholder}` tags.

---

## Visible length

### getVisibleLength($str)

Count visible characters, excluding markup tags and HTML entities.

```php
$len = $tools->getVisibleLength('Hello <strong>world</strong>'); // 11
$len = $tools->getVisibleLength('Price: &pound;10');             // 10
```

---

## Diff

### diffMarkup($old, $new, $options)

Generate an HTML diff showing insertions and deletions between two strings.

```php
$diff = $tools->diffMarkup('The quick brown fox', 'The slow brown fox');
// "The <del>quick</del> <ins>slow</ins> brown fox"
```

| Option         | Default                | Description                                         |
|----------------|------------------------|-----------------------------------------------------|
| `ins`          | `'<ins>{out}</ins>'`   | Markup template for inserted text                   |
| `del`          | `'<del>{out}</del>'`   | Markup template for deleted text                    |
| `entityEncode` | `true`                 | Entity-encode the surrounding (unchanged) text      |
| `split`        | `'\s+'`                | Regex used to split strings into diffable tokens    |

---

## Tag fixing

### fixUnclosedTags($str, $remove, $options)

Remove or close unclosed HTML tags.

```php
$clean = $tools->fixUnclosedTags($html);          // remove all instances of unclosed tags
$fixed = $tools->fixUnclosedTags($html, false);   // close unclosed tags at end of string
```

When `$remove` is `true` (default), all tags of the unclosed type are stripped.
When `false`, closing tags are appended at the end.

---

## Punctuation

### getPunctuationChars($sentence)

Return an array of punctuation characters.

```php
$all = $tools->getPunctuationChars();        // [',', ':', '.', '?', '!', ...]
$end = $tools->getPunctuationChars(true);    // sentence-ending only: ['.', '?', '!']
```

---

## Word alternates

### getWordAlternates($word, $options)

Get alternate forms of a word (plurals, stems, synonyms). Returns an empty array unless
a module hooks `WireTextTools::___wordAlternates()` to provide an implementation. This
is the integration point for search-enhancement modules.

```php
$alternates = $tools->getWordAlternates('running'); // e.g. ['run', 'runs'] via a hook
```

---

## Escape characters

### findReplaceEscapeChars(&$str, $escapeChars, $options)

Temporarily replace backslash-escaped characters with placeholders so processing steps
don't misinterpret literal characters. Restore them by replacing the returned map.

```php
$str = 'Hello \*world\*';
$placeholders = $tools->findReplaceEscapeChars($str, ['*']);
// ... process $str ...
$str = str_replace(array_keys($placeholders), array_values($placeholders), $str);
```

The returned map is keyed by generated placeholder and valued by the escaped
character. The map is per escaped character in `$escapeChars`, not necessarily
per occurrence, so repeated escaped `\*` characters can share one placeholder.

Useful options:

| Option            | Default  | Description                                               |
|-------------------|----------|-----------------------------------------------------------|
| `escapePrefix`    | `'\\'`   | Escape character prefix                                   |
| `restoreEscape`   | `false`  | Restore the escape prefix along with the escaped char     |
| `gluePrefix`      | `'{ESC'` | Placeholder prefix                                        |
| `glueSuffix`      | `'}'`    | Placeholder suffix                                        |
| `unescapeUnknown` | `false`  | Remove escape prefix from chars not in `$escapeChars`     |
| `removeUnknown`   | `false`  | Remove unknown escaped chars entirely                     |

---

## Multibyte-safe string wrappers

WireTextTools includes wrappers for common PHP string functions. They use mbstring
when available and fall back to PHP's native string functions otherwise.

```php
$len = $tools->strlen('café');       // 4 when mbstring is available
$part = $tools->substr('café', 2);   // fé
$pos = $tools->strpos('café', 'f');  // 2
$text = $tools->strtolower('HELLO'); // hello
```

Available wrappers:

- `substr($str, $start, $length = null)`
- `strpos($haystack, $needle, $offset = 0)`
- `stripos($haystack, $needle, $offset = 0)`
- `strrpos($haystack, $needle, $offset = 0)`
- `strripos($haystack, $needle, $offset = 0)`
- `strlen($str)`
- `strtolower($str)`
- `strtoupper($str)`
- `substrCount($haystack, $needle)`
- `strstr($haystack, $needle, $beforeNeedle = false)`
- `stristr($haystack, $needle, $beforeNeedle = false)`
- `strrchr($haystack, $needle)`
- `trim($str, $chars = '')`
- `ltrim($str, $chars = '')`
- `rtrim($str, $chars = '')`

---

## Notes

- **Source file:** `wire/core/Tools/WireTextTools/WireTextTools.php`
- `$sanitizer` wraps the most common methods (`truncate()`, `markupToText()`, etc.) as direct shortcuts — use those for everyday calls and only reach for `getTextTools()` when you need methods not on Sanitizer.
- `markupToText()` on `WireTextTools` is newer and more capable than the `Sanitizer` version; `$sanitizer->markupToText()` internally delegates to it.
- `truncate()` strips HTML by default. Use `keepTags` or `keepFormatTags` to preserve formatting, or `visible=true` to count only visible characters toward the length.
- `populatePlaceholders()` with a `Page` supports dot-notation subfields (`{author.name}`) and OR-fallback chains (`{display_name|title|name}`).
- `getWordAlternates()` returns an empty array by default — it only produces results when a module implements `___wordAlternates()` via hook.
