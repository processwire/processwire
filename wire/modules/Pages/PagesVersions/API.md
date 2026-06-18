# PagesVersions / $pagesVersions

Provides a version control API for pages in ProcessWire. When the `PagesVersions` module is installed, it creates the `$pagesVersions` API variable, allowing you to save, retrieve, restore, and delete historical snapshots of page data and files.

Access the API in templates or modules via the `$pagesVersions` variable (already available when the module is installed), or get the module directly:

```php
$pagesVersions;                                  // API variable
$pagesVersions = wire()->pagesVersions;          // Get when not in scope
$pagesVersions = $modules->get('PagesVersions'); // Get from $modules
```

---

## Getting and checking versions

### Get a specific version of a page

`getPageVersion()` returns a cloned `Page` object populated with version data. If the requested version does not exist, it returns a `NullPage` (always check `$page->id`).
The returned page preserves the output-formatting state of the page you pass in.

```php
$page = $pages->get(1234);
$v2 = $pagesVersions->getPageVersion($page, 2);

if($v2->id) {
    echo $v2->title; // title as it was in version 2
}
```

Load version data into an existing page instance with `loadPageVersion()`:

```php
$pagesVersions->loadPageVersion($page, 2);
echo $page->title; // now reflects version 2
```

### List all versions for a page

`getPageVersions()` returns a plain PHP array keyed by version number. Each value is
a cloned `Page` populated with that version's data.

```php
$versions = $pagesVersions->getPageVersions($page); // array keyed by version number
foreach($versions as $versionNum => $versionPage) {
    $info = $versionPage->get('_version'); // PageVersionInfo object
    echo "<li>Version $info->version: $info->name</li>";
}
```

Sort order options: `'created'`, `'-created'`, `'version'`, `'-version'`, `'modified'`,
`'-modified'`. Default is `'-created'` (newest first).

```php
$versions = $pagesVersions->getPageVersions($page, ['sort' => 'version']);
```

### Get version info without loading full pages

`getPageVersionInfos()` accepts the same `sort` option as `getPageVersions()`.

```php
$infos = $pagesVersions->getPageVersionInfos($page);
foreach($infos as $info) {
    echo $info->version;           // int
    echo $info->name;              // string|null
    echo $info->descriptionHtml;   // entity-encoded for safe output
    echo $info->createdStr;        // YYYY-MM-DD HH:MM:SS
    echo $info->createdUser->name; // User object -> user name
}
```

Or get a single version's info:

```php
$info = $pagesVersions->getPageVersionInfo($page, 2);
if($info) {
    echo "Created by {$info->createdUser->name} on {$info->createdStr}";
}
```

### Check whether versions exist

```php
// Does a specific version exist?
if($pagesVersions->hasPageVersion($page, 2)) { /* ... */ }

// Version names are also accepted when the version has a name
if($pagesVersions->hasPageVersion($page, 'draft')) { /* ... */ }

// How many versions does the page have?
$qty = $pagesVersions->hasPageVersions($page); // int

// Find all pages that have any versions
$pagesWithVersions = $pagesVersions->getAllPagesWithVersions(); // PageArray
```

---

## Creating versions

### Add a new version

```php
$page = $pages->get(1234);
$page->title = 'New title';
$versionNum = $pagesVersions->addPageVersion($page);
echo "Created version $versionNum";
```

Add with a name and description:

```php
$versionNum = $pagesVersions->addPageVersion($page, [
    'name' => 'draft',
    'description' => 'Work in progress',
]);
```

### Save (or update) a specific version

`savePageVersion()` can overwrite an existing version or create a new one:

```php
// Overwrite version 2 with the page’s current data
$pagesVersions->savePageVersion($page, 2);

// Create a new version when no version number is given
$pagesVersions->savePageVersion($page);
```

Save only specific fields (partial version):

```php
$pagesVersions->savePageVersion($page, 2, [
    'names' => ['title', 'body'],
]);
```

Partial versions are not supported when the page has file fields that cannot be handled per-field.

---

## Restoring versions

Restore a version so it becomes the live page. Returns the restored live `Page` on
success, or `false` on failure.

```php
$page = $pages->get(1234);
$restored = $pagesVersions->restorePageVersion($page, 2); // Page|false
if($restored) {
    echo "Restored to version 2: $restored->title";
}
```

Restore from a page that is already loaded as a version:

```php
$v2 = $pagesVersions->getPageVersion($page, 2);
$restored = $pagesVersions->restorePageVersion($v2);
```

Restore only specific fields. Fields and native page properties not named in
the `names` option are left at their current live values.

```php
$pagesVersions->restorePageVersion($page, 2, [
    'names' => ['title', 'body'],
]);
```

---

## Renaming versions

Name a version for easier identification:

```php
$pagesVersions->renamePageVersion($page, 2, 'draft');         // set name
$pagesVersions->renamePageVersion($page, 'draft', 'backup');  // rename by existing name
$pagesVersions->renamePageVersion($page, 2, null);            // remove name
```

You can also set a name when creating the version via the `name` option in `addPageVersion()` or `savePageVersion()`.

---

## Deleting versions

```php
// Delete a specific version
$qty = $pagesVersions->deletePageVersion($page, 2); // int rows deleted

// Delete all versions for a page
$qty = $pagesVersions->deleteAllPageVersions($page); // int versions deleted

// Delete EVERY version across the entire site (requires explicit true)
$qty = $pagesVersions->deleteAllVersions(true);
```

When a live page is permanently deleted, its versions are automatically removed by an internal module hook.

---

## PageVersionInfo

When a page is loaded as a version, its `_version` property contains a `PageVersionInfo` object. You can also obtain `PageVersionInfo` objects directly via `getPageVersionInfo()` or `getPageVersionInfos()`.

| Property / Method | Type                 | Description                                  |
|-------------------|----------------------|----------------------------------------------|
| `version`         | `int`                | Version number                               |
| `name`            | `string` or `null`   | Optional version name                        |
| `description`     | `string`             | Plain-text description                       |
| `descriptionHtml` | `string`             | Entity-encoded description                   |
| `created`         | `int`                | Unix timestamp                               |
| `createdStr`      | `string`             | `Y-m-d H:i:s`                                |
| `createdUser`     | `User` or `NullPage` | User who created the version                 |
| `modified`        | `int`                | Unix timestamp                               |
| `modifiedStr`     | `string`             | `Y-m-d H:i:s`                                |
| `modifiedUser`    | `User` or `NullPage` | User who last modified                       |
| `pages_id`        | `int`                | ID of the live page                          |
| `page`            | `Page` or `NullPage` | The live page                                |
| `properties`      | `array`              | Native page properties stored in the version |
| `fieldNames`      | `array`              | Names of fields stored in the version        |

```php
$info = $pagesVersions->getPageVersionInfo($page, 2);
echo $info->version;
echo $info->createdStr;
echo $info->createdUser->name;
```

---

## Hooks

| Hook                                     | When                                                        | Notes                                                      |
|------------------------------------------|-------------------------------------------------------------|------------------------------------------------------------|
| `PagesVersions::allowPageVersions`       | Before allowing versions for a page                         | Return `false` to disable versioning for specific pages    |
| `PagesVersions::useTempVersionToRestore` | Before restoring a page that may need a temporary version   | Return `true` for complex fieldtypes like nested repeaters |

---

## Utility methods

Use `pageVersionNumber()` when accepting flexible user input for a version.
It resolves integers, numeric strings, strings with a `v` prefix, version names,
and `PageVersionInfo` objects to an integer version number.

```php
$version = $pagesVersions->pageVersionNumber($page, 'draft'); // 2, or 0 if not found
$version = $pagesVersions->pageVersionNumber($page, 'v3');    // 3
```

Use `getNextPageVersionNumber()` to preview the next public version number that
would be assigned by `addPageVersion()`.

```php
$next = $pagesVersions->getNextPageVersionNumber($page);
```

Use `getUnsupportedFields()` to find fields that cannot be stored in page
versions, either globally or for a specific page/template.

```php
$unsupported = $pagesVersions->getUnsupportedFields($page); // [field_name => Field]
```

---

## Notes

- Version 1 is reserved for internal draft use. The first user-created version is typically number 2.
- `allowPageVersions()` returns `true` by default except for `User`, `Role`, `Permission`, `Language` and `NullPage` pages.
- Version arguments generally accept an integer version number, numeric string, `vN` string, version name, or `PageVersionInfo` object where supported by the method signature.
- File fields are supported: versions copy the page’s file assets into a subdirectory (`v{N}/`). For modern file-handling fieldtypes, files are copied per-field; otherwise the entire page files directory is copied.
- Versions are stored in two database tables: `version_pages` (metadata and native properties as JSON) and `version_pages_fields` (individual field data as JSON).
- A versioned page returned by `getPageVersion()` is a clone of the live page with version data loaded into it. You can read field values from it exactly like a normal page.
- Saving a versioned page directly does **not** update the live page; it updates the stored version instead (unless the restore action is set via `restorePageVersion()`).
- On the front-end, a request with `?version=N` automatically loads that version into `$page` when the user has edit permission.
- Some fieldtypes (e.g., comments) are excluded from versioning automatically. Fieldtypes implementing `FieldtypeDoesVersions` handle their own version storage.
- **Source files:** `wire/modules/Pages/PagesVersions/PagesVersions.module.php`,
  `PageVersionInfo.php` (version metadata), and `PagesVersionsFiles.php` (file field handling).
