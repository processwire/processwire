# FieldtypeFile

Field that stores one or more uploaded files with optional descriptions and tags.

## Value type

The value type depends on the field's `outputFormat` setting (when output formatting is on):

- `outputFormat=auto` (default) — `Pagefiles` when `maxFiles` allows multiple; `Pagefile|null` when `maxFiles=1`
- `outputFormat=array` — always `Pagefiles`
- `outputFormat=single` — `Pagefile|null`
- `outputFormat=string` — rendered `string` using the `outputString` template

When output formatting is **off**: always a `Pagefiles` object.

## Getting and setting values

All "setting" values or other manipulations should take place only when $page output formatting is OFF.

```php
// Iterate files (Pagefiles, multi-file)
foreach($page->files_field as $file) {
    echo "<li><a href='$file->url'>$file->name</a> ($file->filesizeStr)</li>";
}

// Get single file (when outputFormat=single or auto with maxFiles=1)
$file = $page->files_field;
if($file) echo "<a href='$file->url'>$file->description</a>";

// Get a specific file by name
$file = $page->files_field->get('document.pdf');

// Add a file from a local path or URL
$page->files_field->add('/path/to/file.pdf');
$page->save('files_field');

// Remove a file (actual delete occurs on save)
$pagefile = $page->files_field->get('document.pdf');
if($pagefile) {
    $page->files_field->delete($pagefile);
    $page->save('files_field'); 
}

// Remove all files (actual delete occurs on save)
$page->files_field->deleteAll();

// Rename file (actual rename occurs on save)
$pagefile = $page->files_field->get('document.pdf');
if($pagefile) {
    $page->files_field->rename($pagefile, 'hello.pdf');
    $page->save('files_field'); 
}

// Duplicate a file
$pagefile = $page->files_field->get('document.pdf');
if($pagefile) {
    $copy = $page->files_field->clone($pagefile);
    $copy->description = 'Copy of document.pdf'; // optional
    $page->save('files_field');
}

```

## Pagefile properties

Each item in a `Pagefiles` collection is a `Pagefile` object:

```php
$file->url          // URL to the file
$file->httpUrl      // Full URL including http(s)://
$file->filename     // Filesystem path to the file
$file->name         // Basename (e.g. 'document.pdf')
$file->ext          // Extension without dot (e.g. 'pdf')
$file->description  // Description text (output-formatted when OF is on)
$file->tags         // Space-separated tags string (when tags are enabled)
$file->filesize     // File size in bytes
$file->filesizeStr  // Human-readable size (e.g. '1.2 MB')
$file->created      // Unix timestamp of when file was added
$file->modified     // Unix timestamp of last modification
$file->page         // Page this file belongs to
```

## Pagefiles methods

```php
$files->count()              // Number of files
$files->first()              // First Pagefile, or false if empty
$files->last()               // Last Pagefile, or false if empty
$files->get('file.pdf')      // Get Pagefile by name, or null if not found
$files->findTag('sidebar')   // Returns new Pagefiles containing all items with tag
$files->getTag('hero')       // Returns first Pagefile with that tag, or null
$files->add('/path/to/file') // Add a file from a local path or URL
$files->delete($f)           // Mark Pagefile ($f) for deletion (call $page->save() after)
$files->deleteAll()          // Mark all files for deletion (call $page->save() after)
$files->rename($f, 'x.pdf')  // Rename Pagefile ($f) to x.pdf (call $page->save() after)
$copy = $files->clone($f)    // Duplicate Pagefile ($f) and return Pagefile copy (call $page->save() after)
```
Note: Above are examples but Pagefiles also inherits all WireArray methods.
Please use delete() and deleteAll() rather than WireArray remove() and removeAll(). 


## Selectors

```php
// Pages that have at least one file
$pages->find('files_field.count>0');

// Pages with a specific filename
$pages->find('files_field=brochure.pdf');

// Pages whose file description contains a word (fulltext)
$pages->find('files_field.description%=annual');

// Pages with files larger than 1 MB
$pages->find('files_field.filesize>1048576');

// Pages with files created after a date
$pages->find('files_field.created>2025-01-01');

// Pages with files tagged 'sidebar' (requires tags enabled on field)
$pages->find('files_field.tags*=sidebar');

// Pages with a custom subfield value (requires custom fields enabled on field)
$pages->find('files_field.custom_subfield=value');
```

Usable subfields: `data` (filename), `description`, `count`, `filesize`, `created`, `modified`,
`created_users_id`, `modified_users_id`, `tags` (when enabled), plus any custom field names.

## Output / markup

```php
// Render a list of download links (multi-files field)
$out = '';
foreach($page->files_field as $file) {
    $out .= "<li><a href='$file->url'>$file->description</a> ($file->filesizeStr)</li>";
}
if($out) echo "<ul>$out</ul>";

// Get a file by tag and link it
$pdf = $page->files_field->getTag('brochure');
if($pdf) echo "<a href='$pdf->url'>Download brochure</a>";

// Display only files matching a specific tag
foreach($page->files_field->findTag('sidebar') as $file) {
    echo "<a href='$file->url'>$file->name</a><br>";
}
```

## Notes

- Files are stored at `/site/assets/files/{page_id}/` on the filesystem.
- `outputFormat=auto` (default) returns `Pagefiles` unless `maxFiles=1`, in which case it returns `Pagefile|null`.
- `outputFormat=string` renders each file using the `outputString` template (supports `{url}`, `{description}`, `{tags}` placeholders). When there are multiple files, the string is rendered once per file and concatenated.
- The `defaultValuePage` setting causes an empty field to fall back to files from another specified page.
- Custom per-file metadata (beyond description and tags) is enabled via the "Custom fields" feature, which creates a template named `field-{fieldname}`.
- Tags require the `useTags` setting to be enabled; predefined tags are configured via `tagsList`.
- Text formatters (e.g., `TextformatterEntities`) are applied to file descriptions when output formatting is on.