# WireUpload

File upload handler for ProcessWire. Validates, sanitizes, and saves one or more uploads from either a standard `$_FILES` form post or an AJAX upload (via the `X-Filename` header), optionally extracts ZIP archives, and reports errors in a structured way.

Instantiate with `new WireUpload($name)`, where `$name` is the HTML form field name (the `name` attribute of the `<input type="file">` element). Configure the destination path and allowed extensions, then call `execute()` to process the upload and receive an array of saved filenames.

```php
$u = new WireUpload('cv_file');
$u->setDestinationPath($config->paths->assets . 'uploads/');
$u->setValidExtensions(['pdf', 'doc', 'docx']);
$u->setMaxFileSize(5000000);     // 5 MB
$u->setMaxFiles(1);
$saved = $u->execute();
if(count($u->getErrors())) {
    // handle errors
}
```

Extends [[Wire]] — inherits logging, hooks, language translation, and error notice plumbing.

---

## Configuration (setters)

All setters return `$this` so they are chainable, and they must typically be called **before** `execute()`.

| Method | Type / Default | Description |
|--------|-----------------|-------------|
| `setName($name)` | `string` — constructor arg | Upload form field name. Sanitized as a ProcessWire field name. |
| `setDestinationPath($path)` | `string` (required) | Full filesystem path (with trailing `/`) where files will be saved. The directory must already exist; it is **not** created automatically. |
| `setValidExtensions(array $exts)` | `array` `[]` | Allowed extensions (without periods). Extensions are lowercased on input. |
| `setMaxFiles($n)` | `int` `0` | Maximum number of files accepted. `0` means no limit. |
| `setMaxFileSize($bytes)` | `int` `0` | Maximum size in bytes per file. `0` means no limit. |
| `setOverwrite($bool)` | `bool` `false` | Allow existing files at the destination to be overwritten. When false, a unique numeric suffix (`-1`, `-2`, etc.) is appended to colliding filenames. |
| `setOverwriteFilename($filename)` | `string` `''` | Allow overwriting **only** this specific filename (for single-file uploads). Sets `$overwrite = false` internally so no other file is overwritten. |
| `setLowercase($bool)` | `bool` `true` | Force saved filenames to lowercase. |
| `setTargetFilename($filename)` | `string` `''` | Forces the saved file to use this name (preserving the uploaded extension). Only meaningful for single uploads. |
| `setExtractArchives($bool)` | `bool` `false` | Enables ZIP extraction. When true, `zip` is automatically added to the valid extension list and uploaded ZIPs are decompressed in place into individual files. |
| `setAllowAjax($bool)` | `bool` `false` | Enables AJAX uploads — see "AJAX uploads" below. |

### Disallowed extensions

The property `badExtensions` (no public setter) is seeded from `$config->uploadBadExtensions` (`'php php3 phtml exe cfm shtml asp pl cgi sh'` plus `vbs` and `jsp` by default in the stock config). Any extension starting with `php` is also rejected. You cannot override this list at the API level — it provides a permanent floor on what may be uploaded.

---

## Processing

### execute()

Processes the upload(s) and returns an array of saved filenames (basenames). For a single-file upload, a single-element array is returned. Throws `WireException` if `name` or `destinationPath` is unset.

```php
$u = new WireUpload('photo');
$u->setDestinationPath($page->images->path())
  ->setValidExtensions(['jpg', 'jpeg', 'png', 'gif'])
  ->setMaxFiles(1);

$filenames = $u->execute();
if(count($u->getErrors())) {
    foreach($u->getErrors() as $msg) echo "<p>Error: $msg</p>";
}
```

In `demo` mode (`$config->demo` is truthy), `execute()` returns an empty array without handling files.

### getCompletedFilenames()

Returns the saved filenames array (same as the return value of `execute()`).

```php
foreach($u->getCompletedFilenames() as $filename) {
    echo "Saved: $filename";
}
```

### getOriginalFilenames()

*Since 3.0.212.* Returns an associative array of original (unsanitized) upload basenames, keyed by the corresponding completed (sanitized) basename. Useful when you need to record or display the user-supplied filename even though the saved file may have been renamed.

```php
$saved = $u->execute();
$lookup = $u->getOriginalFilenames();
foreach($saved as $completedName) {
    $original = $lookup[$completedName] ?? $completedName;
    echo "$completedName ← uploaded as $original";
}
```

### getErrors($clear = false)

Returns an array of accumulated error messages. Pass `true` to clear the list after returning it. Also available via inherited `$wire->errors()` access.

### getOverwrittenFiles()

Returns an associative array of files that were backed up because they were overwritten — keys are temporary backup file paths, values are the original destination paths. Backup files are removed in `__destruct()`, which runs when the WireUpload instance is garbage collected.

```php
$u->setOverwrite(true);
$u->execute();
// still in scope — backups preserved
foreach($u->getOverwrittenFiles() as $bakPath => $origPath) {
    echo "Backed up: $bakPath (replaces $origPath)";
}
// when $u is unset/GC'd, $bakPath will be unlinked
```

---

## Filename validation

### validateFilename($value, array $extensions = []) — public

Sanitizes a filename: rejects leading dots (hidden files), runs `$sanitizer->filename()` with `Sanitizer::translate` (which transliterates non-ASCII characters), optionally lowercases, trims underscores, replaces dots in the basename with underscores, and verifies a reasonable extension is present.

Returns the processed filename (string), or boolean `false` on rejection. If `$extensions` is provided, also ensures the extension is in that list (strict comparison).

```php
$safe = $u->validateFilename("Résumé(2024).PDF", ['pdf']);
// → string "resume_2024_.pdf"
$bad = $u->validateFilename(".htaccess");
// → false
```

This method is called internally by `saveUpload()`, but is exposed for callers who want to pre-validate or reuse the logic.

---

## MIME type check

### hasValidMimeType($filename, array $mimeTypes = []) — public

*Since 3.0.258.* Detects the file's MIME type via `finfo_file` (libmagic) and compares it to the expected type stored in `$config->fileContentTypes` (or caller-supplied map). The leading `+` (used in `$config->fileContentTypes` to force download) is stripped before comparison. Some extensions accept multiple MIME types (e.g. `svg` matches any of `image/svg+xml`, `application/xml`, `text/xml`).

Returns `true` if:
- `finfo_open` is not available (no check possible).
- The detected MIME type starts with the expected type.
- For multi-type extensions, at least one allowed type matches.

Returns `false` when the ext is in the map but the detected content type does not match. If the extension is not in the map, returns `true` (no expected type to enforce).

```php
if(!$u->hasValidMimeType('/uploads/myphoto.jpg')) {
    echo "This file does not look like a JPG.";
}
```

Called automatically from `saveUpload()` after a file is successfully moved; if the check fails, the moved file is unlinked and an error is recorded.

---

## AJAX uploads

When `setAllowAjax(true)` has been called and the request contains the `HTTP_X_FILENAME` header (`$u->isAjaxUploading()` returns true), WireUpload reads the upload directly from the `php://input` stream (in 8 MB chunks for memory safety) and constructs a synthetic `$_FILES` array. The upload field name is not used in this mode — only the `HTTP_X_FILENAME` request header supplies the client-provided name (rawurldecoded).

```php
// client-side (JavaScript fetch example)
const data = formData.get('file');
fetch(url, {
    method: 'POST',
    headers: { 'X-Filename': encodeURIComponent(data.name) },
    body: data
});

// server-side
$u = new WireUpload('file');
$u->setAllowAjax(true);
$u->setDestinationPath($path);
$u->setValidExtensions(['jpg', 'png']);
$saved = $u->execute();
```

### isAjaxUploading() — static

*Since 3.0.131.* Returns `true` when the current request appears to be an AJAX upload (the `HTTP_X_FILENAME` header is non-empty). Use this to decide whether to instantiate WireUpload with AJAX enabled.

```php
if(WireUpload::isAjaxUploading()) {
    $u = new WireUpload('file');
    $u->setAllowAjax(true);
    // …
}
```

---

## ZIP archive extraction

When `setExtractArchives(true)` is called, uploading a `.zip` file causes WireUpload to extract its contents instead of saving the ZIP itself. Extraction is performed via `$files->unzip()` into a temporary `.zip_tmp/` subdirectory next to the destination path; each extracted file is then validated (size limit lifted to `10 × maxFileSize` during extraction) and saved individually. The temporary directory and the original ZIP file are removed at the end.

If `maxFileSize` is set, it is also forwarded to `unzip()` as `maxTotalMegabytes` (after byte-to-megabyte conversion) to set the maximum total decompressed size. Only `validExtensions` (minus `zip`) are extracted when provided — see `$files->unzip()` option `extractFiles`.

```php
$u = new WireUpload('bundle');
$u->setDestinationPath($config->paths->assets . 'releases/')
  ->setValidExtensions(['png', 'jpg', 'pdf'])
  ->setExtractArchives(true)
  ->setMaxFileSize(10000000);     // per-file limit
$u->execute();
```

Any error from extraction (e.g. an empty ZIP) is reported via `getErrors()` and the method bails out early rather than propagating the exception.

---

## Hooks

WireUpload overrides [[Wire]]::`error()`, recording the message in the internal `errors` array before forwarding to the parent. This preserves wire-level error notice plumbing while also collecting the messages that `getErrors()` returns.

There are no `___`-prefixed hookable methods defined — the class is not designed to be hooked at specific lifecycle moments. If you need to intercept upload results, consider wrapping the whole `execute()` call in your own code.

---

## Notes

- **Constructor**: `new WireUpload($name)` — `$name` is sanitized as a field name via `$sanitizer->fieldName()`. Setting an empty or invalid name here will throw when `execute()` is called.
- **Demo mode**: When `$config->demo` is truthy, `execute()` returns `[]` immediately. Useful for test/staging environments.
- **Destination directory**: Must exist prior to `execute()`. Unlike earlier documentation statements, the directory is **not** created here. Prepare it yourself (for example using `$files->mkdir()`).
- **Temporary directory**: WireUpload uses `$config->uploadTmpDir`, falling back (on Windows) to `$config->paths->cache . 'uploads/'`, then PHP's `upload_tmp_dir` ini setting, then `sys_get_temp_dir()`. For AJAX uploads a temporary file is opened in this directory via `tempnam()` and moved into place.
- **Overwrite backups**: When `$overwrite` is true and an existing file is replaced, the old file is renamed to `_<name>` (with additional leading underscores if needed to avoid collisions) and tracked in `overwrittenFiles`. Cleanup happens automatically in `__destruct()`. If you need to inspect the backup before the instance is destroyed, call `getOverwrittenFiles()`.
- **Cross-references**: For directory creation helpers see [[WireFileTools]] (`$files->mkdir()`). For sanitization internals see [[Sanitizer]] (`$sanitizer->filename()`, `$sanitizer->fieldName()`). For the unzip backend see [[WireFileTools]] (`$files->unzip()`).
- **Source file:** wire/core/Tools/WireUpload/WireUpload.php


