# Pagefiles

A `Pagefiles` object is a `WireArray` collection of `Pagefile` objects. It is the
value type for multi-file fields in ProcessWire (created by [[FieldtypeFile]])
and provides methods for adding, removing, renaming, duplicating, and finding
files by tag or basename.

```php
// $page->files is a Pagefiles instance
foreach($page->files as $name => $file) {
    echo "<li><a href=\'$file->url\'>$name</a> — $file->description</li>";
}
```

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `path` | `string` | Full server disk path to the directory that stores the files. |
| `url` | `string` | Web-accessible URL to the directory that stores the files. |
| `page` | `Page` | The `Page` object that owns this file collection. |
| `field` | `Field` | The `Field` object that defines this file collection. |

## Retrieval

`Pagefiles` extends [[WireArray]], so all traversal, filtering, slicing, sorting,
and counting methods from `WireArray` are available. The methods below are specific
to file collections.

### getFile($name)

Get a `Pagefile` by its basename. Returns `null` if not found. Array access with
the basename is equivalent.

```php
$file = $page->files->getFile('document.pdf');
$file = $page->files['document.pdf']; // equivalent
```

### findTag($tag)

Return a new `Pagefiles` collection containing only files that match the given
tag expression.

```php
// Single tag
$downloads = $page->files->findTag('download');

// OR: files with any of these tags
$featured = $page->files->findTag('featured|hero|gallery');

// AND: files with all of these tags
$hero = $page->files->findTag('featured,hero');
```

### getTag($tag)

Return the first `Pagefile` matching the given tag expression, or `null` if none
match. Accepts the same tag syntax as `findTag()`.

```php
$hero = $page->files->getTag('hero');
```

### tags($value)

When called without arguments, returns all tags used by files in this collection
as a space-separated string. Pass `true` to receive an associative array. Pass a
tag string to retrieve files matching those tags (alias of `findTag()`).

```php
$allTags = $page->files->tags();       // "download featured pdf"
$tagsArray = $page->files->tags(true); // ['download' => 'download', ...]

$downloads = $page->files->tags('download');
```

## Manipulation

Destructive changes are queued until the owning page is saved. Call
`$page->save('field_name')` to persist additions, deletions, renames, and
duplications.

### add($item)

Add a new file to the collection. Accepts a local path, an HTTP(S) URL, or an
existing `Pagefile` object. The file is copied into the page's files directory
and given a sanitized, unique basename.

```php
$page->files->add('/path/to/document.pdf');
$page->files->add('https://example.com/image.png');
$page->save('files');
```

When adding a `Pagefile` that belongs to a different page, the file is copied
into the current page's files directory and marked as temporary until saved.

### delete($item)

Queue a `Pagefile` (or its basename) for deletion. The file is removed from the
collection immediately and deleted from disk when the page is saved.

```php
$page->files->delete('old-document.pdf');
$page->save('files');
```

### deleteAll()

Queue every file in the collection for deletion.

```php
$page->files->deleteAll();
$page->save('files');
```

### rename($item, $name)

Queue a rename of a `Pagefile`. The actual rename occurs when the page is saved.

```php
$file = $page->files->getFile('document.pdf');
$page->files->rename($file, 'report.pdf');
$page->save('files');
```

### clone($item, $options)

Duplicate a `Pagefile` and insert it into the collection. Returns the new
`Pagefile` or `false` on failure. The duplicated file is temporary until the page
is saved.

```php
$file = $page->files->getFile('document.pdf');
$copy = $page->files->clone($file);
$copy->description = 'Copy of document.pdf';
$page->save('files');
```

Available options:

| Option | Type | Default | Description |
| --- | --- | --- | --- |
| `action` | `string` | `'after'` | Where to insert: `'append'`, `'prepend'`, `'after'`, `'before'`, or blank to return without inserting. |
| `pagefiles` | `Pagefiles` | `$this` | Target `Pagefiles` instance for the duplicate. |

## Paths

### path()

Return the full disk path where the files are stored.

```php
$path = $page->files->path();
```

### url()

Return the web-accessible URL where the files are stored.

```php
$url = $page->files->url();
```

## File metadata

### cleanBasename($basename, $originalize = false, $allowDots = true, $translate = false)

Sanitize a filename for use in this collection. Usually called internally when
files are added, but available for custom file handling.

```php
$safe = $page->files->cleanBasename('My File.PDF', true);
```

### getFiles()

Return a flat array of disk paths for every file in the collection, including
any extra files managed by `PagefileExtra`.

```php
$paths = $page->files->getFiles();
```

## Hooks

`Pagefiles` provides two hookable mutation methods:

| Hook | When | Arguments |
| --- | --- | --- |
| `Pagefiles::delete` | Before a file is queued for deletion | `Pagefile $file` |
| `Pagefiles::clone` | Before a file is duplicated | `Pagefile $item`, `array $options` |

```php
$wire->addHookBefore('Pagefiles::delete', function(HookEvent $event) {
    $pagefiles = $event->object; // Pagefiles
    $file = $event->arguments(0); // Pagefile
    // log deletion, check permissions, etc.
});
```

Individual file URLs and filenames can be hooked on the `Pagefile` class. See
[[Pagefile]] for details.

## Notes

- `Pagefiles` extends [[WireArray]], so all filtering, sorting, iteration, and
  array-access features from `WireArray` are available.
- Items are keyed by basename, so `$page->files['report.pdf']` works.
- Files are not actually deleted, renamed, or committed until the owning page is
  saved.
- Adding a `Pagefile` that belongs to another page copies the file into the
  current page's files directory.
- For single-file fields, [[FieldtypeFile]] may return a `Pagefile` object
  directly when output formatting is on.
- **Source file:** `wire/core/Pagefiles/Pagefiles.php`

