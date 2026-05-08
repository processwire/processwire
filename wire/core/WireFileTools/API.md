# WireFileTools / $files

`$files` is the API variable for file and directory operations in ProcessWire.
It includes dedicated methods for creating and removing directories, reading, 
writing, copying, moving, finding and deleting files, reading and creating ZIP files,
reading CSV files, sending files as downloads, including and rendering template files, 
and more. 

Prefer `$files` over equivalent PHP functions (`file_put_contents`, `mkdir`, `copy`, etc.)
wherever possible, because `$files` methods automatically apply the read/write permissions
configured in `$config->chmodFile` and `$config->chmodDir`. This keeps newly created files
and directories consistent with the rest of the ProcessWire installation.

`$files` is accessible in templates as `$files`, `wire()->files`, or `files()`
(if functions API enabled); and in modules as `$this->wire()->files`.

---

## Creating and removing directories

### $files->mkdir($path)

Create a directory using ProcessWire's configured directory permissions.

- **Arguments:** `mkdir(string $path, bool $recursive = false, string $chmod = null)`
- **Returns:** `bool`
- Applies `$config->chmodDir` automatically.
- `$recursive = true` creates all intermediate directories (like `mkdir -p`).
- Swap `$recursive` and `$chmod` arguments if preferred (since 3.0.34).

~~~~~php
// Create a single directory
$files->mkdir($config->paths->cache . 'my-module/');

// Create nested directories in one call
$files->mkdir($config->paths->cache . 'my-module/images/', true);
~~~~~

---

### $files->rmdir($path)

Remove a directory, optionally including all contents.

- **Arguments:** `rmdir(string $path, bool $recursive = false, array $options = [])`
- **Returns:** `bool`
- Be careful with `$recursive = true` — it will delete everything inside.

~~~~~php
// Remove an empty directory
$files->rmdir($config->paths->cache . 'my-module/');

// Remove a directory and everything inside it
$files->rmdir($config->paths->cache . 'my-module/', true);

// Restrict to within site/assets/ for safety
$files->rmdir($path, true, ['limitPath' => $config->paths->assets]);
~~~~~

**Key options:**

| Option | Description |
|---|---|
| `limitPath` | Restrict deletion to within this path. `true` = site/assets/ (default=false) |
| `throw` | Throw `WireException` instead of returning false on error (default=false) |

---

## Reading and writing files

### $files->filePutContents($filename, $contents)

Write content to a file and apply ProcessWire's configured file permissions.

- **Arguments:** `filePutContents(string $filename, mixed $contents, int $flags = 0)`
- **Returns:** `int|bool` — bytes written, or `false` on failure
- Equivalent to PHP's `file_put_contents()` but applies `$config->chmodFile` automatically.
- `$flags` may be `FILE_APPEND` and/or `LOCK_EX` (same as PHP).

~~~~~php
// Write (overwrite if exists)
$files->filePutContents($config->paths->cache . 'my-data.txt', 'Hello world');

// Append to an existing file
$files->filePutContents($config->paths->cache . 'my-log.txt', "New line\n", FILE_APPEND);

// Append with exclusive lock
$files->filePutContents($logFile, $line, FILE_APPEND | LOCK_EX);
~~~~~

---

### $files->fileGetContents($filename)

Read the contents of a file.

- **Arguments:** `fileGetContents(string $filename, int $offset = 0, int $maxlen = 0)`
- **Returns:** `string|false`
- Equivalent to PHP's `file_get_contents()`.

~~~~~php
$content = $files->fileGetContents($config->paths->cache . 'my-data.txt');

// Read only first 1024 bytes
$preview = $files->fileGetContents($filename, 0, 1024);
~~~~~

---

### $files->exists($filename)

Check whether a file or directory exists, with optional type and access checks.

- **Arguments:** `exists(string $filename, array|string $options = '')`
- **Returns:** `bool`
- The `$options` argument may be an array or a space/comma-separated string.
- Available since 3.0.180.

~~~~~php
// Does it exist at all?
$files->exists('/path/to/file.txt');

// String-style option checks
$files->exists('/path/to/file.txt', 'readable');
$files->exists('/path/to/file.txt', 'writable');
$files->exists('/path/to/file.txt', 'file');
$files->exists('/path/to/dir/', 'dir');
$files->exists('/path/to/link', 'link');

// Combine checks
$files->exists('/path/to/file.txt', 'readable writable file');
$files->exists('/path/to/dir/', 'writable dir');
~~~~~

---

### $files->size($path)

Get the size in bytes of a file or directory (directories are summed recursively).

- **Arguments:** `size(string $path, array|bool $options = [])`
- **Returns:** `int|string` — bytes as integer, or formatted string if `getString` option used
- Available since 3.0.214.

~~~~~php
// get bytes integer
$bytes = $files->size($config->paths->cache . 'my-module/');

// get string (e.g. "1.2 MB"): getString option implied boolean true
$str   = $files->size($config->paths->cache . 'my-module/', true);
~~~~~

---

## Copying, moving, and deleting

### $files->copy($src, $dst)

Copy a file or recursively copy a directory to a new location.

- **Arguments:** `copy(string $src, string $dst, bool|array $options = [])`
- **Returns:** `bool`
- Applies `$config->chmodFile` and `$config->chmodDir` to copied items.
- If `$src` is a file and `$dst` is a directory, the filename is preserved under `$dst`.
- Destination directory is created automatically if it does not exist.

~~~~~php
// Copy a directory recursively
$files->copy(
    $config->paths->cache . 'my-module/',
    $config->paths->cache . 'my-module-backup/'
);

// Copy a single file into a directory (keeps filename)
$files->copy('/path/to/source.txt', $config->paths->cache . 'dest-dir/');

// Copy a single file with a new name
$files->copy('/path/to/source.txt', '/path/to/dest.txt');
~~~~~

**Key options:**

| Option           | Description                                                                      |
|------------------|----------------------------------------------------------------------------------|
| `recursive`      | Copy subdirectories? (default=true)                                              |
| `hidden`         | Include hidden files/dirs? (default=true)                                        |
| `allowEmptyDirs` | Copy empty directories? (default=true)                                           |
| `limitPath`      | Restrict to within given path (string) or true for /site/assets/ (default=false) |

---

### $files->rename($oldName, $newName)

Rename or move a file or directory, updating permissions afterwards.

- **Arguments:** `rename(string $oldName, string $newName, array|bool|string $options = [])`
- **Returns:** `bool`
- If `$newName` is only a basename (no directory part), the source directory is assumed.
- Falls back to a copy-then-delete method automatically if a filesystem rename fails.
- Available since 3.0.118.

~~~~~php
// Rename a file (just the name, same directory)
$files->rename('/path/to/old-name.txt', 'new-name.txt');

// Move a file to a different directory
$files->rename('/path/to/file.txt', '/new/path/file.txt');
~~~~~

**Key options:**

| Option      | Description                                                                      |
|-------------|----------------------------------------------------------------------------------|
| `throw`     | Throw `WireException` on error (default=false)                                   |
| `chmod`     | Re-apply permissions after rename (default=true)                                 |
| `copy`      | Use the copy-then-delete strategy, rather than filesystem rename (default=false) |
| `retry`     | Retry with 'copy' method if regular rename fails. (default=true)                 |
| `limitPath` | Restrict to within given path (string) or true for /site/assets/ (default=false) |

---

### $files->renameCopy($oldName, $newName)

Same as `rename()` but always uses the copy-then-delete strategy. Success is determined
by whether the copy succeeded, even if the source deletion fails (a warning is logged).
This can be useful on servers and/or file systems that don't support (or don't allow) a rename. 
Available since 3.0.178.

~~~~~php
$files->renameCopy('/path/to/old.txt', '/path/to/new.txt');
~~~~~

---

### $files->unlink($filename)

Delete a file, with safety checks not present in PHP's `unlink()`.

- **Arguments:** `unlink(string $filename, string|bool $limitPath = false, bool $throw = false)`
- **Returns:** `bool`
- Rejects relative path traversal (`../`).
- Pass `true` for `$limitPath` to restrict deletion to within `/site/assets/`.
- Available since 3.0.118.

~~~~~php
// Delete a file
$files->unlink($config->paths->cache . 'my-module/temp.txt');

// Delete with safety restriction to site/assets/
$files->unlink($filename, true);

// Delete within a specific path
$files->unlink($filename, $config->paths->cache);
~~~~~

---

## Permissions

### $files->chmod($path)

Set file/directory permissions consistent with ProcessWire's configuration.

- **Arguments:** `chmod(string $path, bool $recursive = false, string $chmod = null)`
- **Returns:** `bool`
- Uses `$config->chmodFile` and `$config->chmodDir` by default.
- Most `$files` methods call `chmod()` automatically, so direct use is rarely needed.

~~~~~php
// Apply PW's configured permissions to a file or directory
$files->chmod($config->paths->cache . 'my-module/');

// Apply recursively to all files and subdirectories
$files->chmod($config->paths->cache . 'my-module/', true);

// Apply a specific mode
$files->chmod('/path/to/file.txt', false, '0644');
~~~~~

---

## Finding files

### $files->find($path)

Recursively find all files in a directory and return a flat array of full path filenames.

- **Arguments:** `find(string $path, array $options = [])`
- **Returns:** `array` of filenames (sorted)
- Available since 3.0.96.

~~~~~php
// All files under a directory
$allFiles = $files->find($config->paths->templates);

// Only PHP files
$phpFiles = $files->find($config->paths->templates, [
    'extensions' => ['php'],
]);

// Only PHP and module files, exclude hidden files and a specific directory
$files->find($config->paths->siteModules, [
    'extensions' => 'php module', // string or array
    'excludeHidden' => true,
    'excludeDirNames' => ['_notes'],
]);

// Relative filenames (from the given start path)
$relFiles = $files->find($config->paths->templates, [
    'returnRelative' => true,
]);
~~~~~

**Key options:**

| Option            | Type            | Description |
|-------------------|-----------------|---|
| `extensions`      | array or string | Only include files with these extensions (default=all) |
| `recursive`       | int or bool     | Depth limit; `true` = unlimited, `false` = no subdirs (default=10) |
| `excludeHidden`   | bool            | Skip hidden files (starting with `.`) (default=false) |
| `excludeDirNames` | array           | Skip directories with these names (default=[]) |
| `allowDirs`       | bool            | Include directory entries (with trailing slash) in results (default=false) |
| `returnRelative`  | bool            | Return paths relative to the start `$path` (default=false) |

---

## CSV files

### $files->getCSV($filename)

Read the next row from a CSV file. Call repeatedly in a loop until it returns `false`.
Handles file open/close automatically. Skips blank rows by default.

- **Arguments:** `getCSV(string $filename, array $options = [])`
- **Returns:** `array|false` — associative array for each row, or `false` at end of file
- Available since 3.0.197. For small files, `getAllCSV()` may be simpler.

~~~~~php
// foods.csv has a header row: Food,Type,Color
while($row = $files->getCSV('/path/to/foods.csv')) {
    echo "$row[Food] is a $row[Type] and is $row[Color]\n";
}

// No header row — returns indexed arrays
while($row = $files->getCSV('/path/to/data.csv', ['header' => false])) {
    echo $row[0];
}

// Custom header (for files with no header row)
$header = ['Name', 'Age', 'City'];
while($row = $files->getCSV('/path/to/data.csv', ['header' => $header])) {
    echo $row['Name'];
}
~~~~~

**Key options:**

| Option        | Description                                                                        |
|---------------|------------------------------------------------------------------------------------|
| `header`      | `true` = first row is header (default); `false` = no header; array = use as header |
| `separator`   | Field delimiter (default=`,`)                                                      |
| `enclosure`   | Field enclosure character (default=`"`)                                            |
| `convert`     | Convert digit-only strings to integers? (default=false)                            |
| `blanks`      | Return blank rows? (default=false)                                                 |

---

### $files->getAllCSV($filename)

Read all rows from a CSV file at once and return an array of arrays. Accepts the same
options as `getCSV()`. Use `getCSV()` instead for large files to avoid memory limits.

- **Arguments:** `getAllCSV(string $filename, array $options = [])`
- **Returns:** `array` of row arrays

~~~~~php
$rows = $files->getAllCSV('/path/to/foods.csv');
foreach($rows as $row) {
    echo "$row[Food]: $row[Color]\n";
}
~~~~~

---

## Archives

### $files->zip($zipfile, $files)

Create a ZIP archive.

- **Arguments:** `zip(string $zipfile, array|string $files, array $options = [])`
- **Returns:** `array` with `files` (added) and `errors` keys
- `$files` may be an array of filenames or a single directory path string.
- Throws `WireException` on fatal errors (bad path, can't create ZIP, etc.).

~~~~~php
// Zip all files from a directory
$result = $files->zip(
    $config->paths->cache . 'backup.zip',
    $config->paths->cache . 'my-module/'
);
echo count($result['files']) . " files added";

// Zip specific files
$result = $files->zip(
    $config->paths->cache . 'export.zip',
    ['/path/to/file1.txt', '/path/to/file2.txt']
);
~~~~~

**Key options:**

| Option        | Description                                                    |
|---------------|----------------------------------------------------------------|
| `overwrite`   | Replace existing ZIP file? (default=false)                     |
| `allowHidden` | Include hidden files? (default=false)                          |
| `maxDepth`    | Max subdirectory depth, 0 for no limit (default=0)             |
| `exclude`     | Files or directories to exclude (default=[])                   |
| `dir`         | Directory name to prepend to files inside the ZIP (default='') |

---

### $files->unzip($zipFile, $destinationPath)

Extract a ZIP file to a directory. Applies ProcessWire's configured permissions.
Requires the `FileValidatorZip` core module to be installed.

- **Arguments:** `unzip(string $zipFile, string $destinationPath, array $options = [])`
- **Returns:** `array` of extracted filenames (excluding destination path)
- Throws `WireException` on all error conditions.

~~~~~php
$dst = $config->paths->cache . 'extracted/';
$extracted = $files->unzip($config->paths->cache . 'archive.zip', $dst);

foreach($extracted as $filename) {
    echo $filename . "\n"; // relative to $dst
}
~~~~~

**Key options (3.0.254+):**

| Option              | Description                                                                    |
|---------------------|--------------------------------------------------------------------------------|
| `extractFiles`      | Only extract files matching these names or `!regex!` patterns (default=[])     |
| `extractExtensions` | Only extract these file extensions (default=[])                                |
| `ignoreFiles`       | Skip files matching these names or patterns (default=['.DS_Store','__MACOSX']) |
| `maxFiles`          | Max number of files allowed in ZIP (default=1000)                              |
| `maxFileMegabytes`  | Max uncompressed size per file in MB (default=20)                              |
| `maxTotalMegabytes` | Max total uncompressed size in MB (default=100)                                |
| `test`              | Dry run — return what would be extracted without writing (default=false)       |

---

## Sending files

### $files->send($filename)

Send a file to the browser as a download or inline display, and halt execution.
Uses `$config->fileContentTypes` to determine the content type and download behavior.

- **Arguments:** `send(string|bool $filename, array $options = [], array $headers = [])`
- **Returns:** `int` bytes sent (only if `exit` option is `false`)
- Throws `WireException` on error unless `throw` option is `false`.

~~~~~php
// Send a file for download
$files->send($config->paths->files . '1234/report.pdf');

// Force download with a specific download filename
$files->send($path, ['downloadFilename' => 'My Report.pdf']);

// Send without halting execution
$bytes = $files->send($path, ['exit' => false]);

// Send string data directly (no file on disk needed)
$files->send(false, [
    'data' => 'Hello world',
    'downloadFilename' => 'hello.txt',
]);
~~~~~

**Key options:**

| Option             | Description                                                                     |
|--------------------|---------------------------------------------------------------------------------|
| `exit`             | Halt execution after sending? (default=true)                                    |
| `forceDownload`    | Force download prompt? `null` = let content type decide (default=null)          |
| `downloadFilename` | Override the download filename shown to the user (default='')                   |
| `partial`          | Support HTTP range requests for partial downloads? (default=true)               |
| `limitPath`        | Restrict to within this path for security (default=true in page-render context) |
| `data`             | Send this string instead of a file (`$filename` must be `false`)                |

---

## Template includes

### $files->render($filename)

Render a PHP file as a ProcessWire template file and return the output as a string.
Assumes path relative to `/site/templates/` unless an absolute path is given.
All API variables (`$pages`, `$config`, `$user`, etc.) are available inside the file.

- **Arguments:** `render(string $filename, array $vars = [], array $options = [])`
- **Returns:** `string|bool` — rendered output, or `false` on fatal error

~~~~~php
// Render from /site/templates/partials/card.php
$html = $files->render('partials/card.php', ['title' => 'Hello']);

// Use in template output
echo $files->render('partials/header.php');

// With caching (3.0.130+)
echo $files->render('partials/nav.php', [], ['cache' => 3600]);
~~~~~

**Key options:**

| Option            | Description                                                                    |
|-------------------|--------------------------------------------------------------------------------|
| `defaultPath`     | Base path for relative filenames (default=/site/templates/)                    |
| `allowedPaths`    | Paths the file must be inside (default: templates, core/site modules, cache)   |
| `cache`           | Cache rendered result for this many seconds (default=0, no cache)              |
| `throwExceptions` | Throw on fatal error? (default=true)                                           |

---

### $files->include($filename)

Include a PHP file, outputting directly. All ProcessWire API variables are available
inside the included file. Assumes relative to current directory for relative filenames.

- **Arguments:** `include(string $filename, array $vars = [], array $options = [])`
- **Returns:** `bool` — always `true`
- Use `render()` instead when you want to capture the output as a string.

~~~~~php
$files->include('partials/footer.php');
$files->include('partials/nav.php', ['items' => $page->children]);
~~~~~

---

### $files->includeOnce($filename)

Same as `include()` but skips execution if the file has already been included.

~~~~~php
$files->includeOnce('partials/component.php', $vars);
~~~~~

---

## Temporary directories

### $files->tempDir()

Get a temporary directory path that is automatically removed at the end of the request.
Temp directories are not HTTP-accessible.

- **Arguments:** `tempDir(string|object $name = '', array $options = [])`
- **Returns:** `WireTempDir` — casts to string as the temp path (with trailing slash)
- Calling with the same non-empty `$name` in the same request returns the same instance.

~~~~~php
// Auto-named temp dir (3.0.178+)
$tempDir = $files->tempDir();
$path = (string) $tempDir; // e.g. /site/assets/cache/WireTempDir/.../

// Write to the temp dir using $files for correct permissions
$files->filePutContents($path . 'output.txt', $data);

// Named temp dir (same instance returned on repeated calls in same request)
$tempDir = $files->tempDir('my-module');
$path = (string) $tempDir;
~~~~~

---

## Path utilities

### $files->unixDirName($dir)

Normalize a directory path to use forward slashes and ensure a trailing slash.

- **Arguments:** `unixDirName(string $dir, bool $trailingSlash = true)`
- **Returns:** `string`

~~~~~php
$dir = $files->unixDirName('C:\\some\\path'); // → "C:/some/path/"
~~~~~

---

### $files->unixFileName($file)

Normalize a file path to use forward slashes (no trailing slash).

~~~~~php
$file = $files->unixFileName('C:\\some\\path\\file.txt'); // → "C:/some/path/file.txt"
~~~~~

---

### $files->fileInPath($file, $path)

Check whether `$file` is located somewhere inside `$path` (string comparison only,
does not check if the path exists). Returns `false` if they are identical.

~~~~~php
$files->fileInPath('/site/assets/cache/foo/bar.txt', '/site/assets/'); // true
$files->fileInPath('/site/assets/', '/site/assets/');                   // false
~~~~~

---

### $files->currentPath()

Get the current working directory as a unix-format path with trailing slash.

~~~~~php
$cwd = $files->currentPath(); // e.g. "/var/www/html/processwire/"
~~~~~

---

## Path validation

### $files->allowPath($pathname)

Check whether a pathname is valid for file manipulation. Returns `true` if the path
is safe to use, `false` (or throws) if not. Blocks relative traversal (`../`), double
slashes, paths off the root, etc.

- **Arguments:** `allowPath(string $pathname, bool|string|array $limitPath = false, bool $throw = false)`
- **Returns:** `bool`

~~~~~php
// Allow within any absolute path
$files->allowPath('/var/www/html/site/assets/cache/file.txt');

// Restrict to within site/assets/
$files->allowPath($path, true);

// Restrict to a specific directory
$files->allowPath($path, $config->paths->cache);

// Throw on disallowed path instead of returning false
$files->allowPath($path, $config->paths->cache, true);
~~~~~

---

## Notes

- `$files` methods automatically apply `$config->chmodFile` and `$config->chmodDir`.
  Prefer `$files` over native PHP file functions wherever those permissions matter.
- The `limitPath` option available on `unlink()`, `rmdir()`, `rename()`, `copy()` etc.
  is a recommended safety guard for any code accepting user-influenced paths. Pass
  `true` to restrict to `/site/assets/` or a path string to restrict to a custom location.
- `send()`, `render()`, and `include()` restrict included/sent files to within known safe
  paths by default. Use `allowedPaths` to extend this if needed.
- Source file: `wire/core/WireFileTools/WireFileTools.php`.
