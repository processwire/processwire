# WireApiDocs

Provides access to `API.md` documentation files — locating, reading, and querying them
by class name. Also exposes public methods of any class via reflection. Powers the
`php index.php docs` CLI commands.

Access via the `$wire` API object (lazy-loaded on first access):

```php
$docs = $wire->docs();  // method call
$docs = $wire->docs;    // property access — equivalent

// if $wire is not in scope
$docs = wire()->docs;        // works anywhere
$docs = $this->wire()->docs; // in Wire classes
```

Or construct directly and wire for context:

```php
$docs = new WireApiDocs($wire);
```

---

## Getting docs

### get($get)

The primary method. Return type and format depend on the argument:

```php
// No argument — summary array of all documented classes
$all = $docs->get();
// ['Pages' => 'Retrieves and manipulates pages...', 'Page' => 'A single page...', ...]

// String class name — full markdown docs as a string
$md = $docs->get('Pages');

// Wildcard string — newline-separated list of matching class names
$names = $docs->get('Fieldtype*');

// Array of class names — full docs array, keyed by class name
$arr = $docs->get(['Pages', 'Page']);
// ['Pages' => 'Full Pages markdown...', 'Page' => 'Full Page markdown...']

// Array with wildcard — summary array for matching classes
$arr = $docs->get(['Fieldtype*']);
// ['FieldtypeText' => 'summary...', 'FieldtypeImage' => 'summary...', ...]
```

### getVerbose($get, $options)

Returns an array of arrays. Each entry includes `className`, `classFile`, `apiVarName`,
`isModule`, `docsFile`, and either `docs` (full markdown, for single-class lookups) or
`summary` (first paragraph, for list/wildcard lookups).

```php
$info = $docs->getVerbose('Pages');
// ['Pages' => ['className' => 'Pages', 'classFile' => 'wire/core/Pages/Pages.php',
//              'apiVarName' => 'pages', 'isModule' => false, 'docsFile' => '...', 'docs' => '...']]

$info = $docs->getVerbose(['Fieldtype*']);
// All matching classes, with 'summary' instead of 'docs'
```

Options:

| Option         | Default | Description                                                   |
|----------------|---------|---------------------------------------------------------------|
| `indexByClass` | `true`  | Index result by class name; `false` for plain indexed array   |
| `exclude`      | `[]`    | Property names to remove from each result entry               |
| `rename`       | `[]`    | Map of property names to rename in result entries             |

### getDocs($class)

Alias for `get($class)` — returns full markdown docs string for one class.

```php
$md = $docs->getDocs('Sanitizer');
```

---

## Chapters / TOC

### getChapters($class, $recursive, $getBody)

Get chapters (H2 headings) from a class's API.md.

```php
// Simple list of chapter titles
$titles = $docs->getChapters('Pages');
// ['Finding pages', 'Getting pages', 'Saving pages', ...]

// Recursive — includes H3 sub-chapters
$tree = $docs->getChapters('Pages', true);
// [['title' => 'Finding pages', 'chapters' => [...]], ...]

// With body text per chapter
$full = $docs->getChapters('Pages', false, true);
// [['title' => 'Finding pages', 'body' => '...markdown...'], ...]
```

### getChapterBody($class, $chapter)

Get the body of a single chapter, by index number or title string.

```php
$body = $docs->getChapterBody('Pages', 0);         // first chapter
$body = $docs->getChapterBody('Pages', 'Hooks');   // chapter by title
```

---

## Class inspection

### getMethods($class)

List public methods specific to a class — inherited methods are excluded to keep results
focused on what the class itself provides. `#pw-internal` methods and PHP magic methods
are also excluded. Hookable `___methodName()` methods are returned under their public name.

Each entry has `name`, `hookable` (bool), `group` (string), and `description`.

`group` is the value of the `#pw-group-*` tag on the method (first one if multiple), or
`'advanced'` for `#pw-advanced` methods that have no other group, or empty string if ungrouped.

```php
$methods = $docs->getMethods('Page');
// [
//   ['name' => 'viewable', 'hookable' => true, 'group' => 'access',
//    'description' => 'Returns true if the page is viewable by the current user'],
//   ...
// ]
```

In `methods-text` CLI output, hookable methods are prefixed with `*`.

### getMethod($class, $method)

Get full details for a single method — summary, description, arguments, return type,
and the corresponding section body from the API.md (if documented there).

```php
$info = $docs->getMethod('Pages', 'find');
// [
//   'name'        => 'find',
//   'summary'     => 'Find pages matching a selector...',
//   'description' => 'Longer description from phpdoc...',
//   'details'     => 'Section body from API.md...',
//   'arguments'   => [['name' => 'selector', 'type' => 'string|array|Selectors', ...]],
//   'return'      => ['type' => 'PageArray', 'description' => '...'],
//   'group'       => 'retrieval',
//   'see'         => ['Pages::findOne'],
// ]
```

---

## Class info

### getClassInfo($class)

Get structural information about a class via reflection.

```php
$info = $docs->getClassInfo('WireApiDocs');
// [
//   'name'       => 'WireApiDocs',
//   'parent'     => 'Wire',
//   'interfaces' => ['CliModule'],
//   'traits'     => [],
//   'abstract'   => false,
//   'summary'    => 'Provides methods for retrieving API.md documentation',
//   'body'       => 'The methods of this class can be accessed from $wire->docs()->...',
// ]
```

`interfaces` contains only interfaces implemented directly by the class, not those
inherited from parent classes. `summary` is drawn from the `#pw-summary` tag if present,
otherwise the first prose line of the class phpdoc. `body` is the content of the
`#pw-body = ... #pw-body` block from the class phpdoc (empty string if not present).

---

## Constants

### getConstants($class)

Get public constants declared on a class (not inherited). Descriptions come from phpdoc
docblocks on the constant when present; many PW constants use inline comments and will
return an empty description.

```php
$consts = $docs->getConstants('Inputfield');
// [
//   ['name' => 'collapsedNo',  'value' => 0, 'description' => ''],
//   ['name' => 'collapsedYes', 'value' => 1, 'description' => ''],
//   ...
// ]
```

---

## Properties

### getProperties($class)

Parse `@property`, `@property-read`, and `@property-write` annotations from a class's
phpdoc header. This covers the property-based API surface that `getMethods()` cannot see.

Each entry has `name`, `type`, `access`, `group` (string), and `description`.
`#pw-internal` properties are excluded. `#pw-group-*` and `#pw-advanced` inline tags are
stripped from the description and surfaced in `group` instead (first group wins if multiple;
`'advanced'` is used as the group for `#pw-advanced` when no other group is set).

```php
$props = $docs->getProperties('Page');
// [
//   ['name' => 'viewable', 'type' => 'bool', 'access' => 'read',
//    'group' => 'access',
//    'description' => 'Returns true if the page is viewable by the current user...'],
//   ...
// ]
```

`access` is one of `'read-write'`, `'read'`, or `'write'`.

When a property's description is empty after stripping `#pw-*` tags, `getProperties()`
automatically falls back to the description from a same-named `@method` tag in the same
docblock — useful for classes like `Page` where method descriptions are provided but
property descriptions are omitted.

---

## Group summaries

### getGroupSummaries($class)

Parse `#pw-summary-[group]` tags from a class phpdoc header. These describe what each
`#pw-group-*` grouping means for that class.

```php
$groups = $docs->getGroupSummaries('Page');
// [
//   'access'  => 'Access-control related methods/properties',
//   'output'  => 'Methods for rendering and outputting page content',
//   ...
// ]
```

Group names match the suffix of `#pw-group-*` tags found on methods and properties.

---

## API variable discovery

### getApiVars($className)

Get all API variable names indexed by class name, or look up the variable name for one class.

```php
$all = $docs->getApiVars();
// ['Pages' => 'pages', 'Page' => 'page', 'Sanitizer' => 'sanitizer', ...]

$varName = $docs->getApiVars('Pages');  // 'pages'
$varName = $docs->getApiVars('WireLog'); // '' (not an API var)
```

### isApiVar($className)

Returns the API variable name if the class is an API variable, empty string otherwise.

```php
if($varName = $docs->isApiVar($class)) {
    echo "Accessible as \$$varName";
}
```

---

## Configuration

These methods get or set scanning configuration. Pass an array or string to set;
omit the argument to get the current value. Pass `true` as the second argument to
replace rather than append.

### apiPaths($set, $replace)

Paths scanned for `API.md` files. Defaults to: core classes, core modules, site modules,
site templates, site classes.

```php
$current = $docs->apiPaths();
$docs->apiPaths('/path/to/my/classes/');          // append a path
$docs->apiPaths(['/only/this/path/'], true);       // replace all paths
```

### apiFileNames($set, $replace)

Names of documentation files to look for (default: `['API.md']`).

```php
$docs->apiFileNames('DOCS.md');          // also look for DOCS.md
$docs->apiFileNames(['API.md'], true);   // reset to default
```

### excludeDirNames($set, $replace)

Directory names (or regex patterns) excluded from scanning. Defaults exclude `vendor/`,
`assets/`, and version-numbered directories (`dir-1.2.3`).

```php
$docs->excludeDirNames('node_modules');
```

### reset() — static

Clear all cross-instance caches.

```php
WireApiDocs::reset();
```

---

## CLI

The most common way to use WireApiDocs — run from the ProcessWire root directory:

```
php index.php docs list                                List all classes with API.md docs
php index.php docs list 'Class*'                       List classes matching a wildcard pattern
php index.php docs list-verbose                        List all classes in verbose mode
php index.php docs list-verbose 'Class*'               Verbose list filtered by wildcard
php index.php docs get <class>                         Full API.md docs for a class
php index.php docs toc <class>                         Table of contents for a class
php index.php docs chapter <class> <num>               Chapter body by number (0-based)
php index.php docs chapter <class> 'Title'             Chapter body by title
php index.php docs methods <class>                     Public methods of a class (* prefix = hookable)
php index.php docs method <class> <method>             Full details for a single method
php index.php docs classinfo <class>                   Class info: parent, interfaces, traits
php index.php docs constants <class>                   Public constants for a class
php index.php docs properties <class>                  @property annotations for a class
php index.php docs groups <class>                      #pw-group summary descriptions for a class
php index.php docs vars                                All API variables and their class names
```

Commands return JSON by default. Append `-text` to any command name for plain-text output:

```
php index.php docs list-text
php index.php docs get-text Pages
php index.php docs toc-text Sanitizer
php index.php docs methods-text WireCache
```

---

## Notes

- **Source file:** `wire/core/Tools/WireApiDocs/WireApiDocs.php`
- Accessed as `$wire->docs()` or `$wire->docs` (lazy-loaded on first access).
- API.md files are discovered by scanning for files named `API.md` in the configured paths.
  The class name is inferred from the containing directory name (must match a `ClassName.php`
  in the same directory), or from the H1 heading of the API.md itself.
- Module API.md files are identified via `$modules->isModule()` rather than directory name matching.
- Cross-instance caching uses a static array — results persist for the lifetime of the PHP process. Call `WireApiDocs::reset()` to clear after adding new API.md files.
- `getMethods()` uses PHP reflection; only methods declared on the class itself (not inherited) are included.
- `getMethod()` cross-references the API.md to populate the `details` key — a method documented in both phpdoc and API.md gets richer output.
