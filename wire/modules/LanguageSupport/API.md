# LanguageSupport / $languages

ProcessWire's multi-language system is provided by the **LanguageSupport** module and its
companion modules. When installed, it adds the `$languages` API variable, provides text and
textarea fields that are multi-language aware, and optionally enables language-specific page names
and URLs. All of this integrates transparently — in most cases template code does not need
to be language-aware at all, because field values automatically resolve to the current user's
language.

`$languages` is accessible in templates as `$languages` or `wire()->languages`, and in modules
as `$this->wire()->languages`.

---

## The $languages API variable

### Iterating languages

The most common use — iterate all active (published) languages.

```php
foreach($languages as $language) {
    echo "<li>$language->title ($language->name)";
    if($language->isCurrent()) echo " — current";
    echo "</li>";
}
```

### Getting a specific language

```php
// By name or ID
$de = $languages->get('de');
$de = $languages->get(1234); // by page ID

// Default language
$default = $languages->getDefault();
$default = $languages->default; // property alias

// Current user's language (same as $user->language)
$current = $languages->getLanguage();

// Named language (same as get() but returns null instead of NullPage on miss)
$es = $languages->getLanguage('es');
```

### Finding languages

```php
// All languages except the current user's language
$others = $languages->findOther();

// All languages except the default
$nonDefault = $languages->findNonDefault();

// All languages except a specific one
$others = $languages->findOther($languages->get('de'));
```

---

## Language objects

`Language` extends `Page`, so all standard Page properties and methods are available.
The most commonly used language-specific members are:

| Property / Method | Type     | Description                                           |
|-------------------|----------|-------------------------------------------------------|
| `name`            | `string` | Language name slug, e.g. `'default'`, `'es'`, `'de'`  |
| `title`           | `string` | Human-readable name, e.g. `'English'`, `'Spanish'`    |
| `id`              | `int`    | Page ID of this language                              |
| `isDefault`       | `bool`   | `true` if this is the default language (property)     |
| `isCurrent`       | `bool`   | `true` if this is the active user language (property) |
| `isDefault()`     | `bool`   | Method form of `isDefault`                            |
| `isCurrent()`     | `bool`   | Method form of `isCurrent`                            |

```php
foreach($languages as $language) {
    if($language->isDefault()) continue; // skip default
    // render a language switcher link, etc.
}
```

---

## Active language

The current user's active language is `$user->language`. Changing it affects how
multi-language field values are resolved for the rest of the request, but does not
persist to the database or survive page reloads — it is request-scoped only.

```php
// Get the current language
$language = $user->language;

// Check if user is on the default language
if($user->language->isDefault()) { /* ... */ }
```

### Switching language temporarily

Use these pairs to switch language for a block of code and restore it afterwards.

```php
// Switch to a specific language
$languages->setLanguage('de');
// ... output or logic in German ...
$languages->unsetLanguage(); // restore previous language

// Switch to the default language
$languages->setDefault();
// ... force default-language output ...
$languages->unsetDefault(); // restore previous language
```

`setLanguage()` accepts a language name, ID, or `Language` object. Both pairs remember
one previous state and restore it with the matching unset call. They are intended for
temporary switch/restore pairs, not as a general nested language stack.

---

## Multi-language fields

When **LanguageSupportFields** is installed, multi-language Text, Textarea, and PageTitle
fields become available via `FieldtypeTextLanguage`, `FieldtypeTextareaLanguage`, and 
`FieldtypePageTitleLanguage`. You can convert any existing `FieldtypeText`, `FieldtypeTextarea`, 
or `FieldtypePageTitle` field to use the multi-language equivalent. 

Many other Fieldtypes also support multi-language values, those mentioned are are just the ones 
included with the `LanguageSupportFields` module. The example below demonstrates how to convert 
single-language text fieldtypes to multi-language equivalents. This can also be performed 
interactively in the admin.

```php
// convert title to be multi-language
$field = $fields->get('title');
$field->setFieldtype('FieldtypePageTitleLanguage');
$field->save();

// convert body to be multi-language
$field = $fields->get('body');
$field->setFieldtype('FieldtypeTextareaLanguage');
$field->save();
```

These fields store a value per language and return a
`LanguagesPageFieldValue` object. In string context (output, concatenation, `echo`) it
automatically resolves to the current user's language value, so most template code works
without any changes.

```php
// Resolves to the current user's language automatically
echo $page->title;
echo $page->body;
```

If the current language value is blank, the field falls back to the default language value
(unless the field is configured to leave it blank).

### Reading a specific language's value

```php
$value = $page->title->getLanguageValue($languages->get('de'));
$value = $page->title->getLanguageValue('de'); // by name
$value = $page->title->getLanguageValue(1234); // by language ID

// Default language value regardless of current user language
$default = $page->title->getDefaultValue();

// 1st non-empty value in this order: current lang, default, other
$fallback = $page->title->getNonEmptyValue();
```

### Setting values

```php
// Sets in current user language
$page->title = 'Willkommen';

// Set a single language's value
$page->title->setLanguageValue('de', 'Willkommen');
$page->title->setLanguageValue($languages->get('de'), 'Willkommen');

// Set multiple languages at once (3.0.236+)
$page->title->setLanguageValues([
    'default' => 'Welcome',
    'de'      => 'Willkommen',
    'es'      => 'Bienvenido',
]);

$page->save('title');
```

### Multi-language Fieldtypes

The Fieldtypes that support `LanguagesPageFieldValue` are:

- **FieldtypeTextLanguage** — single-line text with per-language values
- **FieldtypeTextareaLanguage** — multi-line text / rich text with per-language values
- **FieldtypePageTitleLanguage** — same as FieldtypeTextLanguage but intended for page titles

Each of these will get its own `API.md` covering Fieldtype-specific configuration options.

---

## Translation strings

ProcessWire provides three gettext style global functions for translating strings in template 
files and related includes/files. All three are automatically parsed by the translation UI in the admin.

```php
// Basic translation
echo __('Hello world');

// With disambiguation context (use _x() when the same phrase appears
// in different contexts and might need different translations)
echo _x('Open', 'adjective: an open door');
echo _x('Open', 'verb: click to open');

// Singular / plural
$count = count($items);
echo sprintf(_n('Found one item', 'Found %d items', $count), $count);

// Provide notes to the translator with PHP comment on the same line
echo __('Hello world'); // Primary notes to translator // Secondary notes
```

In PHP classes extending `Wire` (like module files or custom Page classes) should
use these methods instead:

- `$this->_('text')`
- `$this->_x('text', 'context')` 
- `$this->_n('singular', 'plural')`

```php
class MyClass extends WireData {
    public function myMethod() {
        echo $this->_('Hello world');
        echo $this->_x('Open', 'adjective: an open door');
        echo $this->_n('Found one', 'Found multiple', $count);
    }
}
```
When multi-language support is not installed, all the above mentioned translation functions
or methods return the original string unchanged, so they are safe to use in sites that may
or may not have multi-language support installed.

### Gotchas

#1: Whether in template files or in classes, there can only be one (1) 
translation call per line:
```php
// BAD: this is not translatable
echo __('Foo') . __('Bar'); 

// GOOD: use one call per line to ensure it's translatable
echo __('Foo') . 
     __('Bar'); 
```
#2: You cannot have variable values in translation strings. Instead, use `sprintf()`.
```php
// BAD: this is not translatable
echo __("Hello $user->name"); 

// GOOD: this can be translated
echo sprintf(__('Hello %s'), $user->name); 
```

#3: A translation call cannot span more than one line. Use long lines or split into multiple calls:
```php
// BAD: this does not work
echo __('Now is the time for all good men ' . 
        'to come to the "aid of their country.'); 

// GOOD: it's long but it works and is easy to translate
echo __('Now is the time for all good men to come to the aid of their country.');

// GOOD: this works also
echo __('Now is the time for all good men') . ' ' . 
     __('to come to the aid of their country.');
```

---

## Multi-language page names and URLs

The optional **LanguageSupportPageNames** module enables language-specific page name slugs,
so each language can have its own URL segment. When installed, `Page` gains several additional
methods.

```php
// URL to this page in a specific language
echo $page->localUrl('de');           // e.g. /de/produkte/
echo $page->localUrl($languages->get('de')); // same

// Path relative to install root (without scheme/host)
echo $page->localPath('de');          // e.g. /de/produkte/

// Full URL with scheme and host
echo $page->localHttpUrl('de');       // e.g. https://example.com/de/produkte/

// Language-specific page name slug
echo $page->localName('de');          // e.g. "produkte"
```

`localUrl()`, `localPath()`, and `localHttpUrl()` all accept a `Language` object, language
name string, or language ID. When called with no argument they return the current user's
language URL.

### Getting and setting language page names

```php
// Get name for a specific language
echo $page->getLanguageName('es');              // "hola"

// Get names for all languages
$names = $page->getLanguageName();              // ['default' => 'hello', 'es' => 'hola']

// Get names for specific languages
$names = $page->getLanguageName(['es', 'de']);  // ['es' => 'hola', 'de' => 'hallo']

// Set name for a specific language
$page->setLanguageName('es', 'hola');
$page->setName('hola', 'es');  // equivalent alternative

// Set names for multiple languages at once
$page->setLanguageName([
    'default' => 'hello',
    'es'      => 'hola',
    'de'      => 'hallo',
]);

$page->save();
```

### Getting and setting language page status

Each language can be individually active or inactive on a page. If the default language is
inactive, the page is not publicly viewable in any language.

```php
// Check if page is active in a specific language
$active = $page->getLanguageStatus('es');           // true or false

// Get status for all languages
$statuses = $page->getLanguageStatus();             // ['default' => true, 'es' => true, 'fr' => false]

// Get status for specific languages
$statuses = $page->getLanguageStatus(['es', 'fr']); // ['es' => true, 'fr' => false]

// Set active status for a specific language
$page->setLanguageStatus('es', true);
$page->setLanguageStatus('fr', false);

// Set status for multiple languages at once
$page->setLanguageStatus([
    'default' => true,
    'es'      => true,
    'fr'      => false,
]);

$page->save();
```

`getLanguageName()`, `setLanguageName()`, `getLanguageStatus()`, and `setLanguageStatus()`
all require **LanguageSupportPageNames** and were added in 3.0.236.

---

## Locale

The locale is set once at boot time based on the default language settings and does not
change automatically when `$user->language` is changed. Call `setLocale()` explicitly if
you need locale-sensitive behaviour (sorting, number formatting, etc.) to match the active
language.

```php
// Set locale to whatever is configured for the current user's language
$languages->setLocale();

// Set locale for a specific language
$languages->setLocale(LC_ALL, 'de_DE.UTF-8');
$languages->setLocale(LC_ALL, $languages->get('de')); // pulls locale from language settings

// Set locale for a specific category only
$languages->setLocale(LC_TIME, 'de_DE.UTF-8');

// Try multiple locales in order until one succeeds
$languages->setLocale(LC_ALL, ['de_DE.UTF-8', 'de_DE', 'de']);

// Get the current locale
echo $languages->getLocale();           // e.g. "de_DE.UTF-8"
echo $languages->getLocale(LC_TIME);   // locale for a specific category
```

`Language` objects proxy these methods:

```php
$languages->get('de')->setLocale();
$locale = $languages->get('de')->getLocale();
```

---

## Multi-language Inputfields

When building forms with `InputfieldForm` or `InputfieldWrapper` — in a module's 
`getModuleConfigInputfields()`, a Fieldtype's `getConfigInputfields()`, a `Process` module, 
or anywhere else Inputfields are used — you can enable per-language inputs on supported 
Inputfield types (Text, Textarea, Options, File, Image, and others) by setting 
`useLanguages = true`.

```php
$f = $modules->get('InputfieldText'); /** @var InputfieldText $f */
$f->name  = 'greeting';
$f->label = $this->_('Greeting message');

if($languages) {
    $f->useLanguages = true; // show a tab per language in the rendered input
}
```

When `useLanguages` is `true`, the Inputfield renders a separate input tab for each
installed language. The default language value is in `$f->value`; other languages are in
`$f->value{languageId}` properties:

```php
// Populate values before rendering (e.g. from stored module config data)
$f->value = $this->greeting; // default language
if($languages) {
    foreach($languages as $lang) {
        if($lang->isDefault()) continue;
        $value = (string) $this->get("greeting$lang->id");
        $f->set("value$lang->id", $value); 
    }
}
```

### setLanguageValue() and getLanguageValue() methods

When LanguageSupport is installed, Inputfields gain `setLanguageValue()` and
`getLanguageValue()` methods as cleaner alternatives to direct property access:

```php
// Populate (replaces the foreach above)
foreach($languages as $lang) {
    $key = $lang->isDefault() ? "greeting" : "greeting$lang->id";
    $value = (string) $this->get($key);
    $f->setLanguageValue($lang, $value);
}

// Read back after processInput(), though not typically necessary
foreach($languages as $lang) {
    $value = $f->getLanguageValue($lang); // string
}
```

Both methods accept a `Language` object, language name string, or language ID.

Note that when it comes to Module or Fieldtype/Inputfield configuration methods,
ProcessWire will take care processing the input, then populating and saving it to 
the Module for you. 

### Bridging to LanguagesPageFieldValue

When working with multi-language page field values directly, `LanguagesPageFieldValue`
provides two methods to transfer values between itself and an Inputfield:

```php
$langValue = $page->title; // LanguagesPageFieldValue

// Populate an Inputfield from a LanguagesPageFieldValue
$langValue->setToInputfield($f);

// Read values back from an Inputfield into a LanguagesPageFieldValue
$langValue->setFromInputfield($f);
```

These are used internally by the LanguageSupport module when rendering and processing
field inputs on page edit forms.


## Creating and managing language translations

Language packs consist of JSON translation files stored in each language page's files
directory. The directory path is `{assetsPath}/files/{languagePageId}/`, where
`languagePageId` is the page ID of the language (e.g. `site/assets/files/1012/`).

### Translation file format and naming

Each JSON file covers one source PHP file. The filename is derived from the source file
path by replacing path separators with `--` and the extension with `-{ext}`:

| Source file                                 | JSON filename                                       |
|---------------------------------------------|-----------------------------------------------------|
| `wire/modules/PagePaths.module`             | `wire--modules--pagepaths-module.json`              |
| `site/templates/_main.php`                  | `site--templates--_main-php.json`                   |
| `site/modules/MyModule/MyModule.module.php` | `site--modules--mymodule--mymodule-module-php.json` |

Each JSON file has the structure:

```json
{
    "file": "site/templates/_main.php",
    "textdomain": "site--templates--_main-php",
    "translations": {
        "7dce122004969d56ae2e0245cb754d35": { "text": "Bearbeiten" },
        "3ee693d376f73e2bfa34e985c30bec66": { "text": "Abmelden (%s)" }
    }
}
```

The keys in `translations` are MD5 hashes of the original English text.

### Setting translations programmatically

Use `LanguageTranslator` to read and write individual translations directly. Access the
translator for a language via `$language->translator`.

```php
$language = $languages->get('de');
$translator = $language->translator;

// Convert a file path to its textdomain
$textdomain = $translator->filenameToTextdomain('site/templates/_main.php');
// "site--templates--_main-php"

// Set a translation (value is the translated text)
$translator->setTranslation($textdomain, 'Edit', 'Bearbeiten');
$translator->setTranslation($textdomain, 'Logout (%s)', 'Abmelden (%s)');

// Save the textdomain JSON file
$translator->saveTextdomain($textdomain);

// Read a translation back
echo $translator->getTranslation($textdomain, 'Edit'); // "Bearbeiten"

// Get all translations for a textdomain
$data = $translator->getTranslations($textdomain);
// ['hash' => ['text' => 'translated'], ...]
```

### Exporting and importing via LanguagePorter (3.0.264+)

`LanguagePorter` provides a clean API for importing and exporting translations and 
language packs. Access it via the `porter()` method or property on any `Language` object:

```php
$language = $languages->get('de');
$porter = $language->porter;
```

**Export to CSV:**

The `source` option accepts `'wire'`, `'site'`, or any root-relative subdirectory path
(`'site/templates/'`, `'wire/core/'`, `'site/modules/MyModule/'`). Absolute paths are
also accepted and normalized automatically. The output filename reflects the source:
`site/templates/` → `de-site-templates.csv`.

The `scope` option controls which files are included:
- `'registered'` (default): only files already attached to the language page's
  `language_files` or `language_files_site` fields. These are known translation
  textdomains, whether or not every phrase has been translated yet.
- `'all'`: scans the source directory recursively for eligible `.php`, `.module`,
  and `.inc` files containing translation calls (`__()`, `_x()`, `_n()`, `$this->_()`
  etc.), even files with no existing translations. Hidden files, dash-prefixed entries,
  `site/assets/`, and files marked `__(file-not-translatable)` are skipped.

`scope='all'` is the right choice when you want to produce a complete list of every
translatable phrase in a directory — for example, to hand off to an AI agent for full
translation of a site.

```php
// Export registered /wire/ translations to a file (default behaviour)
$csvFile = $porter->exportCsv();                              // de-wire.csv
$csvFile = $porter->exportCsv(['source' => 'site']);          // de-site.csv
$csvFile = $porter->exportCsv(['source' => 'site/templates']); // de-site-templates.csv

// Discover and export ALL translatable phrases in a directory
$csvFile = $porter->exportCsv(['source' => 'site', 'scope' => 'all']); // de-site.csv

// Narrow scope to a subdirectory — all phrases in site/templates/
$csvFile = $porter->exportCsv(['source' => 'site/templates', 'scope' => 'all']);

// Return CSV as a string instead of writing a file
$csvStr = $porter->exportCsv(['source' => 'site', 'scope' => 'all', 'exportTo' => 'string']);

// Output CSV directly to stdout — useful in CLI scripts
$porter->exportCsv(['source' => 'site', 'exportTo' => 'stdout']);

// Export a single textdomain (source is inferred from it automatically)
$csvFile = $porter->exportCsv(['textdomain' => 'site--templates--_main-php']);
```

**Export to ZIP:**

```php
// Export /wire/ translations to a ZIP file (returns full path to the file)
$zipFile = $porter->exportZip(); // de-wire.zip

// Export /site/ translations
$zipFile = $porter->exportZip(['source' => 'site']); // de-site.zip
```

**Import from CSV:**

Language packs are often distributed as CSV files. The CSV format has columns
`original, translated, description, file, hash` (the header row uses language names,
e.g. `en,de,description,file,hash`):

```csv
en,de,description,file,hash
Edit,Bearbeiten,,wire/modules/Page/PageEdit/ProcessPageEdit.module,7dce12...
"Logout (%s)","Abmelden (%s)",,wire/templates/_main.php,3ee693...
```

```php
// Import from a file path — returns integer count of rows processed, or false on error
$count = $porter->importCsv('/path/to/de-wire.csv');

// Import from a CSV string directly (no need to write it to a file first)
$count = $porter->importCsvStr($csvStr);

// importCsv() also accepts a CSV string — it detects the difference automatically
$count = $porter->importCsv($csvStr);

// Suppress notifications (useful in scripts)
$count = $porter->importCsv('/path/to/de-wire.csv', ['quiet' => true]);
```

**Install a ZIP language pack:**

```php
// Install /wire/ translations from a ZIP
$language->language_files->add('/path/to/de-wire.zip');
$language->save('language_files');

// Install /site/ translations from a ZIP
$language->language_files_site->add('/path/to/de-site.zip');
$language->save('language_files_site');
```

---

## Notes

- **Source files:** `wire/modules/LanguageSupport/` — key files are `Languages.php` (the
  `$languages` class), `Language.php`, `LanguagesPageFieldValue.php`,
  `LanguageSupportPageNames.module`, `LanguagePorter.php` (CSV/ZIP import/export),
  `LanguageTranslator.php` (runtime translation lookup), and
  `wire/core/Functions/LanguageFunctions.php` (translation functions)
- **Module prerequisites:** LanguageSupport must be installed for `$languages` to be
  available; LanguageSupportFields must be installed for multi-language field values;
  LanguageSupportPageNames must be installed for `localUrl()` and language-specific slugs
- **Language pages:** each language is a Page under `/processwire/setup/languages/`
  (where `/processwire/` is the admin URL); they have the built-in `language` template 
  and can carry custom fields.
- **`__()` in modules:** translation functions work the same way in modules as in
  templates — the textdomain is auto-detected from the calling file path. In modules you
  should use the `$this->_()` versions rather than the procedural ones.
- **Default language fallback:** when a multi-language field value is blank in the current
  language, it falls back to the default language value by default; this behaviour is
  configurable per field (`langBlankInherit` setting)
- **Language changes are request-scoped:** changing `$user->language` or calling
  `setLanguage()` / `setDefault()` affects only the current request and does not save to
  the database
- **Bundling translations with a module:** module authors can ship translation CSV files in
  a `languages/` subdirectory so users can install them from the module's config screen. See
  the [HelloWorld](https://github.com/ryancramerdesign/Helloworld) and
  [ProcessHello](https://github.com/ryancramerdesign/ProcessHello) modules for examples, and the
  [multi-language translations guide](https://processwire.com/docs/modules/development/multi-language-translations/)
  for the full workflow.
