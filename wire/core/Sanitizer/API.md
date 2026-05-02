# Sanitizer / $sanitizer

`Sanitizer` provides methods for sanitizing and validating user input, preparing data
for output, and more. Accessed via the `$sanitizer` API variable.

```php
$clean = $sanitizer->text($dirty);
```

---

## Text strings

### text($value)

- **Arguments:** `text($value, $options = [])`
- **Returns:** Single-line plain text, max 255 chars by default
- **Behavior:** Strips tags and newlines.
- **Purpose:** User names, titles, search queries, single-line text fields.

```php
$value = $sanitizer->text($dirty);
$value = $sanitizer->text($dirty, ['maxLength' => 100, 'stripTags' => true]);
```

- **Key options:** `maxLength` (int, default=255), `maxBytes` (int), `stripTags` (bool, default=true),
`allowableTags` (string), `multiLine` (bool), `stripMB4` (bool), `stripQuotes` (bool),
`stripSpace` (bool), `reduceSpace` (bool), `convertEntities` (bool), `truncateTail` (bool),
`newlineReplacement` (string, default=" ").
- **Details:** [text sanitizer](https://processwire.com/api/ref/sanitizer/text/)

### textarea($value)

- **Arguments:** `textarea($value, $options = [])`
- **Returns:** Multi-line plain text, max 16384 chars by default
- **Behavior:** Like `text()` but allows newlines.
- **Purpose:** Plain-text `<textarea>` input.

```php
$value = $sanitizer->textarea($dirty);
$value = $sanitizer->textarea($dirty, ['maxLength' => 5000]);
```

- **Key options:** See `text()` sanitizer.
- **Details:** [textarea sanitizer](https://processwire.com/api/ref/sanitizer/textarea/)

### line($value)

- **Arguments:** `line($value, $maxLength = 0, $options = [])`
- **Returns:** Single-line plain text, no max length by default
- **Behavior:** Same as `text()` but no built-in max length unless you provide one.
- **Purpose:** Text fields where 255 chars is too short.

```php
$value = $sanitizer->line($dirty);        // no limit
$value = $sanitizer->line($dirty, 1000);  // max 1000 chars
```

- **Details:** [line sanitizer](https://processwire.com/api/ref/sanitizer/line/)

### lines($value)

- **Arguments:** `lines($value, $maxLength = 0, $options = [])`
- **Returns:** Multi-line plain text, no max length by default
- **Behavior:** Same as `textarea()` but no built-in max length unless you provide one.
- **Purpose:** Large text areas where 16384 chars is too short.

```php
$value = $sanitizer->lines($dirty, 10000);
```

- **Details:** [lines sanitizer](https://processwire.com/api/ref/sanitizer/lines/)

### string($value)

- **Arguments:** `string($value, $sanitizer = '')`
- **Returns:** String
- **Behavior:** Converts objects (via `__toString`), booleans, arrays, etc. to a string with no
further sanitization unless a sanitizer name is given.
- **Purpose:** When value type is unknown and you need a plain string.

```php
$str = $sanitizer->string($maybeObject);
$str = $sanitizer->string($dirty, 'text');  // cast then sanitize
```

- **Details:** [string sanitizer](https://processwire.com/api/ref/sanitizer/string/)

### date($value)

- **Arguments:** `date($value, $format = null, $options = [])`
- **Returns:** Unix timestamp (int) by default, or formatted string if `$format` given. Returns `null` if value is empty or unparseable.
- **Behavior:** Parses nearly any date or datetime string.
- **Purpose:** Date input from users or form fields.

```php
$ts  = $sanitizer->date('2025-06-01');           // unix timestamp
$str = $sanitizer->date('June 1 2025', 'Y-m-d'); // '2025-06-01'
$ts  = $sanitizer->date($dirty, null, ['min' => '2020-01-01', 'max' => '2030-12-31']);
```

- **Key options:** `min`, `max` (date strings or timestamps for range clamping).
- **Details:** [date sanitizer](https://processwire.com/api/ref/sanitizer/date/)

### json($value)

- **Arguments:** `json($value, $options = [])`
- **Returns:** JSON string (3.0.256+)
- **Behavior:** Encodes a value to a JSON string, pretty-printed by default.
- **Purpose:** Encoding arrays or objects to JSON output.

```php
$json = $sanitizer->json($array);
$json = $sanitizer->json($array, ['pretty' => false]);
```

- **Details:** [json sanitizer](https://processwire.com/api/ref/sanitizer/json/)

---

## Names and identifiers

All name sanitizers support a `$beautify` argument. When `true`, cleans up doubled
separators, leading/trailing punctuation, etc. Use `true` when generating a name for
the first time; use `false` for round-tripping an existing name. Pass
`Sanitizer::translate` (value `2`) to transliterate non-ASCII letters to ASCII.

### name($value)

- **Arguments:** `name($value, $beautify = false, $maxLength = 128, $replacement = '_')`
- **Returns:** String containing only `a-zA-Z0-9`, hyphen, underscore, period
- **Behavior:** Replaces disallowed characters with `$replacement` (default `_`).
- **Purpose:** Generic internal names, config keys, module names.

```php
$name = $sanitizer->name($dirty);                       // Foo_Bar_Baz-123
$name = $sanitizer->name($dirty, true);                 // beautified
$name = $sanitizer->name($dirty, Sanitizer::translate); // transliterate non-ASCII
```

- **Details:** [name sanitizer](https://processwire.com/api/ref/sanitizer/name/)

### fieldName($value)

- **Arguments:** `fieldName($value, $beautify = false, $maxLength = 128)`
- **Returns:** String containing only `a-zA-Z0-9_`
- **Behavior:** Like `name()` but no hyphens or periods (must be valid PHP variable names).
- **Purpose:** ProcessWire field names, dynamically created field names.

```php
$fn = $sanitizer->fieldName('Hello World'); // Hello_World
```

- **Details:** [fieldName sanitizer](https://processwire.com/api/ref/sanitizer/field-name/)

### fieldSubfield($value)

- **Arguments:** `fieldSubfield($value, $limit = 1)`
- **Returns:** Sanitized field name with optional dot-notation subfield(s)
- **Behavior:** Sanitizes each dot-separated segment as a field name.
- **Purpose:** Sanitizing API input that references fields with dot-notation (e.g. `images.first`).

```php
echo $sanitizer->fieldSubfield('a.b.c');    // a.b (one subfield, default)
echo $sanitizer->fieldSubfield('a.b.c', 2); // a.b.c (two subfields)
echo $sanitizer->fieldSubfield('a.b.c', 0); // a (field only)
```

- **Details:** [fieldSubfield sanitizer](https://processwire.com/api/ref/sanitizer/field-subfield/)

### pageName($value)

- **Arguments:** `pageName($value, $beautify = false, $maxLength = 128, $options = [])`
- **Returns:** Lowercase string containing only `a-z0-9`, hyphen, underscore, period
- **Behavior:** Converts to lowercase; optionally transliterates or converts UTF-8.
- **Purpose:** ProcessWire page names — generating or validating names for new pages.

```php
$name = $sanitizer->pageName('Hello World!', true);          // hello-world
$name = $sanitizer->pageName($dirty, Sanitizer::translate);  // transliterate non-ASCII
$name = $sanitizer->pageName($dirty, Sanitizer::toAscii);    // UTF-8 → punycode
$name = $sanitizer->pageName($dirty, Sanitizer::toUTF8);     // punycode → UTF-8
```

- **Types:** `$beautify` accepts `true`, `Sanitizer::translate` (2), `Sanitizer::toAscii` (4), `Sanitizer::toUTF8` (8), `Sanitizer::okUTF8` (16).
- **Details:** [pageName sanitizer](https://processwire.com/api/ref/sanitizer/page-name/)

### pageNameTranslate($value)

- **Arguments:** `pageNameTranslate($value, $maxLength = 128)`
- **Returns:** Lowercase page-name string with non-ASCII letters transliterated to ASCII
- **Behavior:** Shortcut for `pageName($value, Sanitizer::translate)`.
- **Purpose:** Generating page names from multilingual titles.

```php
$name = $sanitizer->pageNameTranslate('Héllo Wörld'); // hello-world
```

- **Details:** [pageNameTranslate sanitizer](https://processwire.com/api/ref/sanitizer/page-name-translate/)

### pageNameUTF8($value)

- **Arguments:** `pageNameUTF8($value, $maxLength = 128)`
- **Returns:** Page name string allowing UTF-8 characters from `$config->pageNameWhitelist`
- **Behavior:** Uses `$config->pageNameWhitelist` to determine which UTF-8 characters are allowed.
- **Purpose:** Multilingual sites using UTF-8 page names.
- **Details:** [pageNameUTF8 sanitizer](https://processwire.com/api/ref/sanitizer/page-name-u-t-f8/)

### pagePathName($value)

- **Arguments:** `pagePathName($value, $beautify = false, $maxLength = 1024)`
- **Returns:** Slash-separated path where each segment is a sanitized page name
- **Behavior:** Splits by `/`, sanitizes each segment as a page name, rejoins.
- **Purpose:** Page paths entered or generated programmatically.

```php
$path = $sanitizer->pagePathName('/Products/Blue Widget!'); // /products/blue-widget
```

- **Details:** [pagePathName sanitizer](https://processwire.com/api/ref/sanitizer/page-path-name/)

### filename($value)

- **Arguments:** `filename($value, $beautify = false, $maxLength = 128)`
- **Returns:** Safe file basename (no directory separators)
- **Behavior:** Strips path separators and unsafe characters; prevents directory traversal.
- **Purpose:** Validating or sanitizing uploaded or user-specified filenames.

```php
$file = $sanitizer->filename('©My File.jpg');  // _My_File.jpg
$file = $sanitizer->filename($dirty, true);    // beautified
```

- **Details:** [filename sanitizer](https://processwire.com/api/ref/sanitizer/filename/)

### templateName($value)

- **Arguments:** `templateName($value, $beautify = false, $maxLength = 128)`
- **Returns:** String containing only `a-zA-Z0-9`, hyphen, underscore
- **Behavior:** Like `name()` but no periods; matches ProcessWire template name rules.
- **Purpose:** Template names.
- **Details:** [templateName sanitizer](https://processwire.com/api/ref/sanitizer/template-name/)

### attrName($value)

- **Arguments:** `attrName($value, $maxLength = 255)`
- **Returns:** Valid HTML attribute name string (3.0.133+)
- **Behavior:** Allows characters valid in HTML attribute names.
- **Purpose:** Sanitizing dynamic HTML attribute names before rendering.

```php
$attr = $sanitizer->attrName('data-my-attr'); // data-my-attr
```

- **Details:** [attrName sanitizer](https://processwire.com/api/ref/sanitizer/attr-name/)

### htmlClass($value)

- **Returns:** Single valid CSS class name string (3.0.212+)
- **Behavior:** Allows `-_:@a-zA-Z0-9`; value must contain at least one letter or digit.
- **Purpose:** Sanitizing a single CSS class name before adding to HTML output.
- **Details:** [htmlClass sanitizer](https://processwire.com/api/ref/sanitizer/html-class/)

### htmlClasses($value)

- **Arguments:** `htmlClasses($value, $getArray = false)`
- **Returns:** Space-separated string of valid CSS class names (3.0.212+), or array if `$getArray` is true
- **Behavior:** Sanitizes each class name and removes duplicates.
- **Purpose:** Sanitizing CSS class lists from user input or configuration.

```php
$classes = $sanitizer->htmlClasses('foo  bar  foo baz'); // 'foo bar baz'
$arr     = $sanitizer->htmlClasses('foo bar', true);     // ['foo', 'bar']
```

- **Details:** [htmlClasses sanitizer](https://processwire.com/api/ref/sanitizer/html-classes/)

### selectorValue($value)

- **Arguments:** `selectorValue($value, $options = [])`
- **Returns:** String or array value safe for use in a ProcessWire selector
- **Behavior:** Quotes and escapes the value so it cannot break selector syntax. An array becomes a pipe-delimited OR value.
- **Purpose:** Always use this when inserting user input into selector strings to prevent injection.

```php
$q = $sanitizer->selectorValue($input->get->text('q'));
$results = $pages->find("title|body%=$q");

$val = $sanitizer->selectorValue(['foo', 'bar']); // "foo|bar"
```

- **Details:** [selectorValue sanitizer](https://processwire.com/api/ref/sanitizer/selector-value/)

### selectorValueAdvanced($value)

- **Arguments:** `selectorValueAdvanced($value, $options = [])`
- **Returns:** String safe for the `#=` advanced search operator
- **Behavior:** Like `selectorValue()` but allows `+-*()` command characters that `#=` understands.
- **Purpose:** Advanced full-text search using the `#=` selector syntax.
- **Details:** [selectorValueAdvanced sanitizer](https://processwire.com/api/ref/sanitizer/selector-value-advanced/)

---

## Character filtering

### alpha($value)

- **Arguments:** `alpha($value, $beautify = false, $maxLength = 1024)`
- **Returns:** String containing only ASCII letters (`a-zA-Z`)
- **Behavior:** Strips all non-letter characters.
- **Purpose:** Values that must contain only letters with no digits or punctuation.

```php
$s = $sanitizer->alpha('abc123!'); // 'abc'
```

- **Details:** [alpha sanitizer](https://processwire.com/api/ref/sanitizer/alpha/)

### alphanumeric($value)

- **Arguments:** `alphanumeric($value, $beautify = false, $maxLength = 1024)`
- **Returns:** String containing only ASCII letters and digits (`a-zA-Z0-9`)
- **Behavior:** Strips all non-alphanumeric characters.
- **Purpose:** Short codes, tokens, identifiers that must be strictly alphanumeric.

```php
$s = $sanitizer->alphanumeric('abc 123!'); // 'abc123'
```

- **Details:** [alphanumeric sanitizer](https://processwire.com/api/ref/sanitizer/alphanumeric/)

### digits($value)

- **Arguments:** `digits($value, $maxLength = 1024)`
- **Returns:** String of ASCII digits only (`0-9`) — returns a string, not integer
- **Behavior:** Strips all non-digit characters.
- **Purpose:** Phone numbers, zip codes, numeric strings that must stay as strings.

```php
$digits = $sanitizer->digits('(800) 555-1234'); // '8005551234'
```

- **Details:** [digits sanitizer](https://processwire.com/api/ref/sanitizer/digits/)

### chars($value)

- **Arguments:** `chars($value, $allow = '', $replacement = '', $collapse = false, $mb = false)`
- **Returns:** String containing only the specified allowed characters
- **Behavior:** Removes or replaces characters not in the `$allow` set. Use `[alpha]` for any `a-zA-Z` letter, `[digit]` for any `0-9` digit.
- **Purpose:** Custom character whitelisting, reformatting structured strings like phone numbers.

```php
$value = $sanitizer->chars('foo123barBaz456', 'barz1');          // '1baraz'
$value = $sanitizer->chars('(800) 555-1234', '[digit]', '.');   // '800.555.1234'
$value = $sanitizer->chars('Decatur, GA 30030', '[alpha]', '-'); // 'Decatur-GA'
```

- **Details:** [chars sanitizer](https://processwire.com/api/ref/sanitizer/chars/)

### word($value)

- **Arguments:** `word($value, $options = [])`
- **Returns:** First word from the string (or words joined by separator option)
- **Behavior:** Extracts word(s) using whitespace and punctuation as boundaries.
- **Purpose:** Extracting a single word or slug-like segment from user input.

```php
$word  = $sanitizer->word('hello world');                        // 'hello'
$words = $sanitizer->word('hello world', ['separator' => '-']); // 'hello-world'
```

- **Details:** [word sanitizer](https://processwire.com/api/ref/sanitizer/word/)

### words($value)

- **Arguments:** `words($value, $options = [])`
- **Returns:** Space-separated string of all words from the value
- **Behavior:** Strips tags, extracts word tokens, rejoins with spaces.
- **Purpose:** Extracting clean words from HTML or mixed-content strings.

```php
$words = $sanitizer->words('<p>Hello <em>World</em>!</p>'); // 'Hello World'
```

- **Details:** [words sanitizer](https://processwire.com/api/ref/sanitizer/words/)

---

## Case conversion

### hyphenCase($value) / kebabCase($value)

- **Arguments:** `hyphenCase($value, $options = [])`
- **Returns:** Lowercase hyphen-separated string
- **Behavior:** Converts camelCase, spaces, and underscores to lowercase hyphen-separated format. `kebabCase()` is an alias.

```php
$value = $sanitizer->hyphenCase('Hello World'); // 'hello-world'
$value = $sanitizer->kebabCase('helloWorld');   // 'hello-world'
```

- **Details:** [hyphenCase sanitizer](https://processwire.com/api/ref/sanitizer/hyphen-case/)

### snakeCase($value)

- **Arguments:** `snakeCase($value, $options = [])`
- **Returns:** Lowercase underscore-separated string
- **Behavior:** Converts camelCase, spaces, and hyphens to lowercase underscore-separated format.

```php
$value = $sanitizer->snakeCase('Hello World'); // 'hello_world'
```

- **Details:** [snakeCase sanitizer](https://processwire.com/api/ref/sanitizer/snake-case/)

### camelCase($value)

- **Arguments:** `camelCase($value, $options = [])`
- **Returns:** camelCase string (first letter lowercase)
- **Behavior:** Converts spaces, hyphens, and underscores to camelCase format.

```php
$value = $sanitizer->camelCase('Hello World'); // 'helloWorld'
```

- **Details:** [camelCase sanitizer](https://processwire.com/api/ref/sanitizer/camel-case/)

### pascalCase($value)

- **Arguments:** `pascalCase($value, $options = [])`
- **Returns:** PascalCase string (first letter uppercase)
- **Behavior:** Like `camelCase()` but the first letter is uppercase.

```php
$value = $sanitizer->pascalCase('Hello World'); // 'HelloWorld'
```

- **Details:** [pascalCase sanitizer](https://processwire.com/api/ref/sanitizer/pascal-case/)

---

## HTML and markup

### entities($str)

- **Returns:** HTML entity-encoded string
- **Behavior:** Wraps PHP `htmlentities()` with ProcessWire defaults (UTF-8, encodes quotes).
- **Purpose:** Always use before outputting user-supplied text in HTML to prevent XSS.

```php
echo $sanitizer->entities($title);
echo $sanitizer->entities($input->post->text('comment'));
```

- **Details:** [entities sanitizer](https://processwire.com/api/ref/sanitizer/entities/)

### entities1($str)

- **Returns:** HTML entity-encoded string (without double-encoding existing entities)
- **Behavior:** Like `entities()` but skips re-encoding content already entity-encoded.
- **Purpose:** Strings that may already contain some entity-encoded content.
- **Details:** [entities1 sanitizer](https://processwire.com/api/ref/sanitizer/entities1/)

### entitiesA($value) / entitiesA1($value)

- **Returns:** Value with all string items entity-encoded, recursively (3.0.194+)
- **Behavior:** Entity-encodes string values in arrays to any depth. `entitiesA1()` does not double-encode.
- **Purpose:** Encoding entire arrays for safe HTML output.

```php
$safe = $sanitizer->entitiesA($array);
```

- **Details:** [entitiesA sanitizer](https://processwire.com/api/ref/sanitizer/entities-a/)

### entitiesMarkdown($str)

- **Arguments:** `entitiesMarkdown($str, $options = false)`
- **Returns:** HTML string with entities encoded and inline Markdown converted
- **Behavior:** Encodes entities then converts a limited Markdown subset: `**strong**`, `*em*`,
`` `code` ``, `~~strikethrough~~`, `[link](url)`. Pass `true` for full Markdown (requires
TextformatterMarkdownExtra module).
- **Purpose:** User-supplied text where basic inline formatting is desirable.

```php
echo $sanitizer->entitiesMarkdown($userText);
echo $sanitizer->entitiesMarkdown($userText, true); // full Markdown
```

- **Details:** [entitiesMarkdown sanitizer](https://processwire.com/api/ref/sanitizer/entities-markdown/)

### unentities($str)

- **Arguments:** `unentities($str, $flags = false)`
- **Returns:** String with HTML entities decoded
- **Behavior:** Decodes HTML entities. Pass `true` for comprehensive mode (decode all, strip remainder).
- **Purpose:** Reading back entity-encoded strings for processing or plain-text storage.

```php
$str = $sanitizer->unentities('&lt;b&gt;Hello&lt;/b&gt;'); // '<b>Hello</b>'
$str = $sanitizer->unentities($str, true); // decode all, strip remainder
```

- **Details:** [unentities sanitizer](https://processwire.com/api/ref/sanitizer/unentities/)

### purify($str)

- **Arguments:** `purify($str, $options = [])`
- **Returns:** Sanitized HTML string
- **Behavior:** Strips disallowed tags and attributes using HTMLPurifier. Requires the
`MarkupHTMLPurifier` module to be installed.
- **Purpose:** Allowing a safe subset of HTML from user-supplied rich text input.

```php
$html = $sanitizer->purify($richText);
```

- **Key options:** Any [HTMLPurifier config option](http://htmlpurifier.org/live/configdoc/plain.html).
- **Details:** [purify sanitizer](https://processwire.com/api/ref/sanitizer/purify/)

### markupToText($value)

- **Arguments:** `markupToText($value, $options = [])`
- **Returns:** Plain text string
- **Behavior:** Strips HTML tags and decodes entities, preserving newlines and basic list structure.
- **Purpose:** Converting stored HTML to plain text for indexing, email, or plain-text display.

```php
$text = $sanitizer->markupToText('<p>Hello <strong>World</strong></p>'); // 'Hello World'
```

- **Details:** [markupToText sanitizer](https://processwire.com/api/ref/sanitizer/markup-to-text/)

### markupToLine($value)

- **Arguments:** `markupToLine($value, $options = [])`
- **Returns:** Single-line plain text string
- **Behavior:** Like `markupToText()` but collapses newlines to spaces and joins list items with `, `.
- **Purpose:** Generating a single-line preview or summary from HTML content.
- **Details:** [markupToLine sanitizer](https://processwire.com/api/ref/sanitizer/markup-to-line/)

---

## Whitespace

### trim($str)

- **Arguments:** `trim($str, $chars = '', $method = 'both')`
- **Returns:** Trimmed string (3.0.124+)
- **Behavior:** Like PHP's `trim()` but also recognizes UTF-8 whitespace variants and HTML whitespace
entities. For standard ASCII whitespace, PHP's own `trim()` is faster.
- **Purpose:** Trimming whitespace from UTF-8 strings that may contain non-ASCII whitespace characters.

```php
$str = $sanitizer->trim($str);           // trim all whitespace
$str = $sanitizer->trim($str, '-_');     // trim specific chars
$str = $sanitizer->trim($str, '', 'r');  // rtrim only
$str = $sanitizer->trim($str, '', 'l');  // ltrim only
```

- **Details:** [trim sanitizer](https://processwire.com/api/ref/sanitizer/trim/)

### removeNewlines($str)

- **Arguments:** `removeNewlines($str, $replacement = ' ')`
- **Returns:** String with newlines removed or replaced
- **Behavior:** Replaces `\r`, `\n`, `\r\n` with `$replacement` (default space).
- **Purpose:** Forcing a string to a single line.

```php
$str = $sanitizer->removeNewlines($str);      // replace with space
$str = $sanitizer->removeNewlines($str, '');  // remove entirely
```

- **Details:** [removeNewlines sanitizer](https://processwire.com/api/ref/sanitizer/remove-newlines/)

### removeWhitespace($str)

- **Arguments:** `removeWhitespace($str, $options = [])`
- **Returns:** String with whitespace removed or replaced (3.0.105+)
- **Behavior:** Removes or replaces all whitespace characters, optionally including HTML whitespace entities.
- **Purpose:** Stripping all whitespace from a string, e.g. before hashing or comparing.

```php
$str = $sanitizer->removeWhitespace($str);                       // remove all
$str = $sanitizer->removeWhitespace($str, ['replace' => ' ']);   // replace with space
$str = $sanitizer->removeWhitespace($str, ['html' => true]);     // include HTML entities
```

- **Details:** [removeWhitespace sanitizer](https://processwire.com/api/ref/sanitizer/remove-whitespace/)

### removeMB4($value)

- **Arguments:** `removeMB4($value, $options = [])`
- **Returns:** String with 4-byte UTF-8 sequences removed
- **Behavior:** Strips emoji and other 4-byte UTF-8 characters.
- **Purpose:** Databases using MySQL's 3-byte `utf8` charset (not `utf8mb4`), which cannot store 4-byte characters.

```php
$str = $sanitizer->removeMB4($str);
```

- **Details:** [removeMB4 sanitizer](https://processwire.com/api/ref/sanitizer/remove-m-b4/)

---

## Truncation and length limits

### truncate($str, $maxLength)

- **Arguments:** `truncate($str, $maxLength, $options = [])`
- **Returns:** Truncated string (3.0.101+)
- **Behavior:** Truncates to `$maxLength` without breaking words, sentences, or blocks depending on
`type`. Appends `more` string (default `…`) if truncated. Falls back to shorter type if preferred
boundary cannot be found.
- **Purpose:** Generating text previews, meta descriptions, card summaries.

```php
$str = $sanitizer->truncate($str, 150);
$str = $sanitizer->truncate($str, 300, 'sentence');
$str = $sanitizer->truncate($str, 300, ['type' => 'sentence', 'more' => '…']);
```

- **Key options:** `type` (`word`, `punctuation`, `sentence`, `block`), `more` (default=`…`),
`maximize`, `visible`, `keepTags`, `keepFormatTags`, `convertEntities`.
- **Details:** [truncate sanitizer](https://processwire.com/api/ref/sanitizer/truncate/)

### trunc($str, $maxLength)

- **Arguments:** `trunc($str, $maxLength, $options = [])`
- **Returns:** Truncated string with no appended ellipsis (3.0.157+)
- **Behavior:** Like `truncate()` but `more` is disabled and `collapseLinesWith` defaults to a space.
- **Purpose:** Truncating text when an ellipsis or trailing marker is not wanted.
- **Details:** [trunc sanitizer](https://processwire.com/api/ref/sanitizer/trunc/)

### maxLength($value, $maxLength)

- **Arguments:** `maxLength($value, $maxLength, $maxBytes = 0)`
- **Returns:** Value constrained to `$maxLength`
- **Behavior:** Works on strings (by character count), arrays (by item count), and integers/floats (by digit count).
- **Purpose:** Enforcing a maximum length on any type of value.

```php
$str = $sanitizer->maxLength($str, 255);
$arr = $sanitizer->maxLength($arr, 10);
```

- **Details:** [maxLength sanitizer](https://processwire.com/api/ref/sanitizer/max-length/)

### maxBytes($value, $maxBytes)

- **Returns:** String truncated to `$maxBytes` bytes (3.0.125+)
- **Behavior:** Truncates to byte length, not character length (relevant for multi-byte UTF-8).
- **Purpose:** Database columns with a byte-length limit rather than a character-length limit.

```php
$str = $sanitizer->maxBytes($str, 512);
```

- **Details:** [maxBytes sanitizer](https://processwire.com/api/ref/sanitizer/max-bytes/)

### minLength($value, $minLength)

- **Arguments:** `minLength($value, $minLength, $padChar = '', $padLeft = false)`
- **Returns:** String of at least `$minLength` characters, or blank string if too short and no pad specified
- **Behavior:** Without `$padChar`: returns blank if value is too short (validation). With `$padChar`: pads the value to the required length (sanitization).
- **Purpose:** Enforcing minimum lengths, generating zero-padded codes.

```php
$str = $sanitizer->minLength($str, 5);             // blank if < 5 chars
$str = $sanitizer->minLength($str, 5, '0');        // pad right with '0'
$str = $sanitizer->minLength($str, 5, '0', true);  // pad left with '0'
```

- **Details:** [minLength sanitizer](https://processwire.com/api/ref/sanitizer/min-length/)

---

## URLs, email, paths

### url($value)

- **Arguments:** `url($value, $options = [])`
- **Returns:** Valid URL string, or blank string if the value cannot be made valid
- **Behavior:** Validates and sanitizes URLs; adds a scheme to domain-looking values if missing.
- **Purpose:** URLs submitted by users — links, redirects, canonical values, etc.

```php
$url = $sanitizer->url($dirty);
echo $sanitizer->entities($url); // always entity-encode URLs for HTML output
```

- **Key options:** `allowRelative` (bool, default=true), `allowIDN` (bool), `allowSchemes` (array),
`disallowSchemes` (array, default=`['file','javascript']`), `requireScheme` (bool, default=true),
`allowQuerystring` (bool, default=true), `maxLength` (int, default=4096).
- **Details:** [url sanitizer](https://processwire.com/api/ref/sanitizer/url/)

### httpUrl($value)

- **Arguments:** `httpUrl($value, $options = [])`
- **Returns:** Valid `http://` or `https://` URL string, or blank string (3.0.129+)
- **Behavior:** Like `url()` but requires an `http://` or `https://` scheme and no relative paths.
- **Purpose:** External URLs that must be absolute and web-accessible.

```php
$url = $sanitizer->httpUrl($dirty);
```

- **Details:** [httpUrl sanitizer](https://processwire.com/api/ref/sanitizer/http-url/)

### email($value)

- **Arguments:** `email($value, $options = [])`
- **Returns:** Valid email address string, or blank string on failure
- **Behavior:** Validates and sanitizes an email address.
- **Purpose:** Email addresses from contact forms or user registration.

```php
$email = $sanitizer->email($dirty);
$email = $sanitizer->email($dirty, ['allowIDN' => true]); // internationalized domain
```

- **Details:** [email sanitizer](https://processwire.com/api/ref/sanitizer/email/)

### emailHeader($value)

- **Arguments:** `emailHeader($value, $headerName = false)`
- **Returns:** String safe for use in an email header
- **Behavior:** Strips newlines that could be used to inject additional email headers.
- **Purpose:** Email subject lines and other header values.

```php
$subject = $sanitizer->emailHeader($dirty);
$header  = $sanitizer->emailHeader($dirty, true); // sanitize as header name
```

- **Details:** [emailHeader sanitizer](https://processwire.com/api/ref/sanitizer/email-header/)

### path($value)

- **Arguments:** `path($value, $options = [])`
- **Returns:** The path string if valid, or boolean `false` if not
- **Behavior:** Validates (does not sanitize) a file system path — must be ASCII with no `..` traversal.
- **Purpose:** Validating user-supplied file paths before use. Use `pagePathName()` for sanitization.

```php
$path = $sanitizer->path($dirty); // '/path/to/file' or false
```

- **Details:** [path sanitizer](https://processwire.com/api/ref/sanitizer/path/)

---

## Numbers

### int($value)

- **Arguments:** `int($value, $options = [])`
- **Returns:** Unsigned integer (min=0 by default)
- **Behavior:** Casts to integer; clamps negative values to 0 unless `min` option overrides.
- **Purpose:** Page IDs, quantities, counts, any positive integer from user input.

```php
$n = $sanitizer->int($dirty);
$n = $sanitizer->int($dirty, ['min' => 1, 'max' => 100, 'blankValue' => 0]);
```

- **Details:** [int sanitizer](https://processwire.com/api/ref/sanitizer/int/)

### intSigned($value)

- **Arguments:** `intSigned($value, $options = [])`
- **Returns:** Signed integer (can be negative)
- **Behavior:** Like `int()` but allows negative values.
- **Purpose:** Offsets, temperatures, any integer that can be negative.
- **Details:** [intSigned sanitizer](https://processwire.com/api/ref/sanitizer/int-signed/)

### intUnsigned($value)

- **Arguments:** `intUnsigned($value, $options = [])`
- **Returns:** Unsigned integer — alias of `int()`
- **Details:** [intUnsigned sanitizer](https://processwire.com/api/ref/sanitizer/int-unsigned/)

### float($value)

- **Arguments:** `float($value, $options = [])`
- **Returns:** Float
- **Behavior:** Parses float values, removing thousands separators; locale-aware formatting available.
- **Purpose:** Prices, measurements, ratings, any decimal number from user input.

```php
$f = $sanitizer->float('1,234.56');         // 1234.56 (float)
$f = $sanitizer->float($dirty, ['precision' => 2, 'min' => 0.0, 'max' => 100.0]);
$s = $sanitizer->float($dirty, ['getString' => 'F']); // non-locale format string
```

- **Key options:** `precision` (int), `min`, `max`, `getString` (format flag), `blankValue`.
- **Details:** [float sanitizer](https://processwire.com/api/ref/sanitizer/float/)

### range($value, $min, $max)

- **Returns:** Value clamped between `$min` and `$max` (3.0.125+). Returns float if either bound is float, otherwise integer.
- **Behavior:** Clamps the value to fall within the specified range.
- **Purpose:** Any numeric input that must fall within a known range.

```php
$n = $sanitizer->range($dirty, 0, 100);    // integer in [0, 100]
$f = $sanitizer->range($dirty, 0.0, 1.0); // float in [0.0, 1.0]
```

- **Details:** [range sanitizer](https://processwire.com/api/ref/sanitizer/range/)

### min($value, $min) / max($value, $max)

- **Returns:** Value clamped at one end (3.0.125+)
- **Behavior:** `min()` ensures value is at least `$min`. `max()` ensures value is at most `$max`.
- **Purpose:** One-sided bounds where only a floor or ceiling is needed.

```php
$n = $sanitizer->min($dirty, 1);   // at least 1
$n = $sanitizer->max($dirty, 100); // at most 100
```

- **Details:** [min sanitizer](https://processwire.com/api/ref/sanitizer/min/) / [max sanitizer](https://processwire.com/api/ref/sanitizer/max/)

---

## Arrays

### array($value)

- **Arguments:** `array($value, $sanitizer = null, $options = [])`
- **Returns:** PHP array
- **Behavior:** Accepts arrays, CSV strings (pipe or comma delimited), or any scalar (becomes first
item). Optionally runs each item through a named sanitizer. Hookable as `___array()`.
- **Purpose:** Multi-value input from checkboxes, multi-select fields, or comma-separated user input.

```php
$arr = $sanitizer->array($dirty);
$arr = $sanitizer->array($dirty, 'text');            // sanitize each item as text
$arr = $sanitizer->array($dirty, 'int');             // sanitize each item as int
$arr = $sanitizer->array('foo,bar,baz', 'pageName'); // ['foo', 'bar', 'baz']
$arr = $sanitizer->array($dirty, null, ['maxItems' => 10]);
```

- **Details:** [array sanitizer](https://processwire.com/api/ref/sanitizer/array/)

### arrayVal($value)

- **Arguments:** `arrayVal($value, $options = [])`
- **Returns:** PHP array without CSV string conversion (3.0.165+)
- **Behavior:** Same as `array()` but a pipe/comma-delimited string stays as a single-item array rather than being parsed.
- **Purpose:** Values that should never be interpreted as CSV.
- **Details:** [arrayVal sanitizer](https://processwire.com/api/ref/sanitizer/array-val/)

### intArray($value)

- **Arguments:** `intArray($value, $options = [])`
- **Returns:** PHP array of unsigned integers
- **Behavior:** Converts CSV strings to array, casts all items to `int`. Negative values become 0. Pass `true` for strict mode (removes non-integers instead of casting).
- **Purpose:** Arrays of page IDs or other integer sets from user input.

```php
$ids = $sanitizer->intArray('1,2,3,foo');  // [1, 2, 3, 0]
$ids = $sanitizer->intArray($dirty, true); // strict: removes non-integers
```

- **Details:** [intArray sanitizer](https://processwire.com/api/ref/sanitizer/int-array/)

### intArrayVal($value)

- **Arguments:** `intArrayVal($value, $options = [])`
- **Returns:** PHP array of integers, no CSV conversion (3.0.165+)
- **Behavior:** Like `intArray()` with `strict` defaulting to `true` and CSV conversion off.
- **Purpose:** Integer arrays that must never be parsed from CSV strings.
- **Details:** [intArrayVal sanitizer](https://processwire.com/api/ref/sanitizer/int-array-val/)

### textArray($value)

- **Arguments:** `textArray($value, $options = [])`
- **Returns:** PHP array of text strings (3.0.256+)
- **Behavior:** Recursively converts objects and nested arrays to a text-only structure.
- **Purpose:** Ensuring all values in a mixed-type array are safe text strings.

```php
$arr = $sanitizer->textArray($mixed);
```

- **Details:** [textArray sanitizer](https://processwire.com/api/ref/sanitizer/text-array/)

### flatArray($value)

- **Arguments:** `flatArray($value, $options = [])`
- **Returns:** Flat (single-dimensional) PHP array (3.0.160+)
- **Behavior:** Flattens a multi-dimensional array to a single dimension.
- **Purpose:** Normalizing nested arrays before processing or storage.

```php
$flat = $sanitizer->flatArray([[1, 2], [3, 4]]); // [1, 2, 3, 4]
```

- **Details:** [flatArray sanitizer](https://processwire.com/api/ref/sanitizer/flat-array/)

### minArray($data)

- **Arguments:** `minArray($data, $allowEmpty = false, $convert = false)`
- **Returns:** PHP array with empty values removed
- **Behavior:** Removes falsy values. Pass specific scalar values or key names as `$allowEmpty`
to preserve them even when empty.
- **Purpose:** Cleaning up arrays before storage or processing.

```php
$arr = $sanitizer->minArray(['a' => 'foo', 'b' => '', 'c' => 0]); // ['a' => 'foo']
$arr = $sanitizer->minArray($data, 0);           // keep integer 0, remove other empties
$arr = $sanitizer->minArray($data, ['a', 'c']);  // keep keys 'a' and 'c' even if empty
```

- **Details:** [minArray sanitizer](https://processwire.com/api/ref/sanitizer/min-array/)

### wordsArray($value)

- **Arguments:** `wordsArray($value, $options = [])`
- **Returns:** PHP array of word strings (3.0.160+)
- **Behavior:** Extracts individual words from a string, stripping punctuation.
- **Purpose:** Keyword extraction, building word lists from user input.

```php
$words = $sanitizer->wordsArray('Hello World!'); // ['Hello', 'World']
$words = $sanitizer->wordsArray($str, ['maxWordLength' => 20, 'keepHyphen' => true]);
```

- **Details:** [wordsArray sanitizer](https://processwire.com/api/ref/sanitizer/words-array/)

### option($value, $allowedValues)

- **Returns:** The value if it is in `$allowedValues`, or `null` if not
- **Behavior:** Whitelist-validates a single value against an array of allowed values.
- **Purpose:** Dropdown, radio, or select inputs where the value must be one of a known set.

```php
$color = $sanitizer->option($input->post->text('color'), ['red', 'green', 'blue']);
```

- **Details:** [option sanitizer](https://processwire.com/api/ref/sanitizer/option/)

### options($values, $allowedValues)

- **Returns:** PHP array containing only values present in `$allowedValues`
- **Behavior:** Filters an array to only allowed values; removes any not in the whitelist.
- **Purpose:** Multi-select or checkbox group inputs where values must come from a known set.

```php
$colors = $sanitizer->options(['red', 'purple', 'blue'], ['red', 'green', 'blue']);
// ['red', 'blue']
```

- **Details:** [options sanitizer](https://processwire.com/api/ref/sanitizer/options/)

---

## Booleans

### bool($value)

- **Returns:** Boolean
- **Behavior:** Recognizes strings `"false"`, `"no"`, `"off"`, `"0"` as false. Non-empty arrays are true.
- **Purpose:** Checkboxes, toggle fields, boolean config values from any input type.

```php
$b = $sanitizer->bool('false'); // false
$b = $sanitizer->bool('1');     // true
$b = $sanitizer->bool('yes');   // true
```

- **Details:** [bool sanitizer](https://processwire.com/api/ref/sanitizer/bool/)

### bit($value)

- **Returns:** Integer `0` or `1` (3.0.125+)
- **Behavior:** Same as `bool()` but returns integer `0` or `1`.
- **Purpose:** Database columns or comparisons requiring integer boolean representation.

```php
$n = $sanitizer->bit('false'); // 0
$n = $sanitizer->bit('yes');   // 1
```

- **Details:** [bit sanitizer](https://processwire.com/api/ref/sanitizer/bit/)

### checkbox($value)

- **Arguments:** `checkbox($value, $yes = true, $no = false)`
- **Returns:** `$yes` if value is truthy, `$no` otherwise (3.0.128+)
- **Behavior:** Validates a checkbox or toggle value; returns configurable yes/no values.
- **Purpose:** HTML checkbox inputs where a specific true/false or 1/0 return type is needed.

```php
$checked = $sanitizer->checkbox($input->post->int('agree'));        // true or false
$checked = $sanitizer->checkbox($input->post->int('agree'), 1, 0); // 1 or 0
```

- **Details:** [checkbox sanitizer](https://processwire.com/api/ref/sanitizer/checkbox/)

---

## Validation

### validate($value, $method)

- **Arguments:** `validate($value, $method, $fallback = null)`
- **Returns:** The value unchanged if already valid, or `$fallback` (default `null`) if the sanitizer modified it (3.0.125+)
- **Behavior:** Applies the named sanitizer. If the result equals the original input, the value
was already valid and is returned as-is. Otherwise returns `$fallback`.
- **Purpose:** Checking that input is already in the expected format before accepting it.

```php
$email = $sanitizer->validate($dirty, 'email'); // email string or null
$alpha = $sanitizer->validate($dirty, 'alpha'); // value or null
```

- **Details:** [validate sanitizer](https://processwire.com/api/ref/sanitizer/validate/)

### valid($value, $method)

- **Arguments:** `valid($value, $method, $strict = false)`
- **Returns:** `true` if value is unchanged by the sanitizer, `false` if it was modified (3.0.125+)
- **Behavior:** Like `validate()` but returns boolean. Pass `true` for `$strict` to also require
the value to already be the correct PHP type (e.g. integer, not string `"1"`).
- **Purpose:** Validation checks before storing or acting on input.

```php
if($sanitizer->valid($dirty, 'email')) { /* valid email */ }
if($sanitizer->valid($dirty, 'int', true)) { /* strict: must already be integer type */ }
```

- **Details:** [valid sanitizer](https://processwire.com/api/ref/sanitizer/valid/)

---

## Utility

### sanitize($value, $method)

- **Returns:** Sanitized value (type depends on method) (3.0.125+)
- **Behavior:** Calls a sanitizer by name. Supports chained methods (underscore-separated) and
max-length shorthand (trailing number).
- **Purpose:** When the sanitizer name is stored in a variable or configuration setting.

```php
$value = $sanitizer->sanitize($dirty, 'text');
$value = $sanitizer->sanitize($dirty, 'text128,entities');
```

- **Details:** [sanitize sanitizer](https://processwire.com/api/ref/sanitizer/sanitize/)

### getAll()

- **Arguments:** `getAll($getReturnTypes = false)`
- **Returns:** Array of sanitizer method names (3.0.165+), or associative array of name → type code if `$getReturnTypes` is true
- **Behavior:** Returns all registered sanitizer names, including any added via hooks.
- **Purpose:** Discovering available sanitizers, building sanitizer-selection UIs.

```php
$names = $sanitizer->getAll();        // ['alpha', 'alphanumeric', ...]
$types = $sanitizer->getAll(true);    // ['alpha' => 's', 'int' => 'i', ...]
```

Return type codes: `s`=string, `i`=integer, `f`=float, `b`=bool, `a`=array, `m`=mixed.

- **Details:** [getAll sanitizer](https://processwire.com/api/ref/sanitizer/get-all/)

### validateFile($filename)

- **Arguments:** `validateFile($filename, $options = [])`
- **Returns:** `true` if valid, `false` if invalid, `null` if no validator available for the file type
- **Behavior:** Validates a file using installed FileValidator modules.
- **Purpose:** Checking uploaded files for safety before further processing.

```php
$valid = $sanitizer->validateFile('/path/to/file.jpg');
```

- **Details:** [validateFile sanitizer](https://processwire.com/api/ref/sanitizer/validate-file/)

### getTextTools()

- **Returns:** `WireTextTools` instance (3.0.101+)
- **Behavior:** Returns the WireTextTools object for more advanced text operations.

```php
$tt   = $sanitizer->getTextTools();
$text = $tt->markupToText($html); // alternative with more options
```

- **Details:** [getTextTools helper](https://processwire.com/api/ref/sanitizer/get-text-tools/)

### getNumberTools()

- **Returns:** `WireNumberTools` instance (3.0.214+)
- **Behavior:** Returns the WireNumberTools object for advanced number formatting operations.
- **Details:** [getNumberTools helper](https://processwire.com/api/ref/sanitizer/get-number-tools/)

---

## Accessing from $input

All sanitizer methods are also available directly on `$input->get`, `$input->post`,
and `$input->cookie`:

```php
$id   = $input->get->int('id');
$name = $input->post->text('name');
$msg  = $input->post->textarea('message');

// 3.0.125+ — pass sanitizer name as second argument
$name = $input->post('name', 'text');
$id   = $input->get('id', 'int');
```

---

## Chaining and shorthand (3.0.125+)

Sanitizers can be chained with underscore separators, and a trailing number implies a
`maxLength` limit:

```php
// chain: run through text() then entities()
$value = $sanitizer->text_entities($dirty);

// max-length shorthand: text sanitizer, max 20 chars
$value = $sanitizer->text20($dirty);

// combine both
$value = $sanitizer->text20_entities($dirty);

// call by name with sanitize()
$value = $sanitizer->sanitize($dirty, 'text,entities');
$value = $sanitizer->sanitize($dirty, 'text128,entities');
```

---

## Adding custom sanitizers

```php
// in /site/ready.php
$sanitizer->addHook('zip', function(HookEvent $event) {
    $sanitizer = $event->object;
    $value = $event->arguments(0);
    $value = $sanitizer->digits($value, 5);
    if(strlen($value) < 5) $value = '';
    $event->return = $value;
});

// use it
$zip = $sanitizer->zip($input->post->text('zip'));
```

---

## Notes

- All sanitizers accept any input type and convert to string (or appropriate type) before
  processing, so you rarely need to cast before calling.
- For front-end output, always call `$sanitizer->entities()` on text values to prevent XSS,
  unless using a method that already entity-encodes (like `entitiesMarkdown()`).
- When inserting user input into selector strings, always use `selectorValue()`.
- The `text()` and `textarea()` sanitizers strip HTML tags; use `purify()` if you need to
  allow a safe subset of HTML from user input.
- Source: `wire/core/Sanitizer/Sanitizer.php`
