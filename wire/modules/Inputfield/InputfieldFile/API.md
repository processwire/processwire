# InputfieldFile

One or more file uploads with sortable ordering, inline descriptions, optional
tags, drag-and-drop AJAX uploading, and ZIP archive decompression. This is the
base Inputfield used by [[FieldtypeFile]] and extended by [[InputfieldImage]].

```php
$f = $modules->get('InputfieldFile');
$f->name = 'documents';
$f->label = 'Documents';
$f->extensions = 'pdf doc docx xls xlsx';
$f->maxFiles = 5;
$form->add($f);
```

When `InputfieldFile` is produced by a `FieldtypeFile` field, ProcessWire sets
the `hasPage`, `hasField`, `hasFieldtype`, value, destination path hook, and
field settings automatically. Standalone use should set at least `name`,
`extensions`, `value`/`destinationPath` when processing uploads, and add the
inputfield to an [[InputfieldForm]] so the form can receive the upload enctype.

For shared Inputfield API such as attributes, labels, collapsed states, showIf,
rendering, processing, errors, and wrapper classes, see [[Inputfield]].

## Properties

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `extensions` | `string` | `''` | Space-separated list of allowed file extensions, such as `'pdf jpg png'`. |
| `okExtensions` | `array` | `[]` | Extensions manually whitelisted even if otherwise flagged as problematic. Added in 3.0.167. |
| `maxFiles` | `int` | `0` | Maximum files allowed. `0` means no limit; `1` enables single-file replacement behavior. |
| `maxFilesize` | `int` | PHP limit | Maximum file size in bytes. Accepts shorthand strings through `setMaxFilesize()`, and is capped to PHP `upload_max_filesize`. |
| `useTags` | `bool\|int` | `0` | Enable tags per file. `0` off, `1` freeform tags, `8` predefined tags, `9` predefined plus freeform. |
| `tagsList` | `string` | `''` | Space-separated predefined tags used when `useTags >= 8`. |
| `unzip` | `bool\|int` | `0` | Decompress uploaded ZIP archives and add contained files. Only functional when `maxFiles=0`. |
| `overwrite` | `bool\|int` | `0` | Replace existing files with the same name. AJAX uploads in overwrite mode are saved immediately. |
| `descriptionRows` | `int` | `1` | Rows for the description input. `0` disables descriptions; values greater than `1` render a textarea. |
| `destinationPath` | `string` | `''` | Destination disk path for uploads. Usually supplied automatically by [[FieldtypeFile]]. |
| `itemClass` | `string` | auto | CSS classes applied to each rendered file item `<li>`. |
| `noUpload` | `bool\|int` | `0` | Set to `1` to disable uploading and render only existing file data. |
| `noLang` | `bool\|int` | `0` | Disable multi-language descriptions when Language Support is installed. |
| `noAjax` | `bool\|int` | `0` | Disable AJAX drag-and-drop uploading. |
| `uploadOnlyMode` | `int` | `0` | Upload-only behavior from request state. `1` hides existing list; `2` also prevents temp status. |
| `noCollapseItem` | `bool\|int` | `0` | Prevent individual file items from collapsing. |
| `noShortName` | `bool\|int` | `0` | Display full basenames rather than shortened names. |
| `noCustomButton` | `bool\|int` | `false` | Use the browser-native file input instead of ProcessWire's styled button. |
| `value` | `Pagefiles\|Pagefile\|null` | `null` | Current file value. Usually a [[Pagefiles]] collection; single-output field contexts can use a [[Pagefile]]. |

## Methods

### getDisplayBasename(Pagefile $pagefile, $maxLength = 25)

Return a basename for display in the file list. Long names are shortened unless
`noShortName` is true.

```php
$name = $f->getDisplayBasename($pagefile);
```

### getWireUpload()

Return the current [[WireUpload]] instance, creating it on first call. This is
most useful for inspecting upload errors after processing.

```php
$upload = $f->getWireUpload();
foreach($upload->getErrors() as $error) {
    $log->save('uploads', $error);
}
```

### isEmpty()

Return `true` when the value contains no files.

```php
if($f->isEmpty()) {
    echo "No files uploaded.";
}
```

### getItemInputfields(?Pagefile $item = null)

Return an [[InputfieldWrapper]] containing custom fields configured for the
Pagefile field's template. Pass a Pagefile to populate values for that item, or
omit the argument to prepare those inputs for render-ready state.

```php
$inputfields = $f->getItemInputfields($pagefile);
if($inputfields) echo $inputfields->render();
```

Returns `false` when the underlying file field has no custom fields template or
when the current value cannot provide one.

### setMaxFilesize($filesize)

Set the maximum file size in bytes. Accepts an integer or shorthand strings like
`'30m'`, `'2g'`, or `'500k'`. The stored value is capped to PHP's
`upload_max_filesize`.

```php
$f->setMaxFilesize('50m');
$f->setMaxFilesize(1048576);
```

## Hooks

| Hook | When | Arguments |
| --- | --- | --- |
| `InputfieldFile::renderItem` | Rendering one file item. | `$pagefile`, `$id`, `$n` |
| `InputfieldFile::renderList` | Rendering the list of files. | `$value` |
| `InputfieldFile::renderUpload` | Rendering the upload area. | `$value` |
| `InputfieldFile::fileAdded` | After a file is added to the value. | `$pagefile` |
| `InputfieldFile::extractMetadata` | Extracting metadata before replacement/overwrite. | `$pagefile`, `$metadata` |
| `InputfieldFile::processInputAddFile` | Adding one uploaded file into the value. | `$filename` |
| `InputfieldFile::processInputDeleteFile` | Deleting one file item from submitted input. | `$pagefile` |
| `InputfieldFile::processInputFile` | Processing description, tags, sort, replace, rename, delete for one item. | `$input`, `$pagefile`, `$n` |
| `InputfieldFile::processItemInputfields` | Processing custom fields for one Pagefile item. | `$pagefile`, `$inputfields`, `$id`, `$input` |

```php
$wire->addHookAfter('InputfieldFile::fileAdded', function(HookEvent $event) {
    $pagefile = $event->arguments(0); /** @var Pagefile $pagefile */
    if(!$pagefile->description) {
        $name = pathinfo($pagefile->name, PATHINFO_FILENAME);
        $pagefile->description = ucwords(str_replace(['-', '_'], ' ', $name));
    }
});
```

```php
$wire->addHookBefore('InputfieldFile::processInput', function(HookEvent $event) {
    $inputfield = $event->object; /** @var InputfieldFile $inputfield */
    if($inputfield->name === 'documents') {
        $inputfield->extensions = 'pdf docx';
    }
});
```

## Notes

- Adding `InputfieldFile` to an [[InputfieldForm]] with `method=post` automatically
  sets the form `enctype` to `multipart/form-data`.
- `renderUpload()` appends `[]` to the file input name when the name does not
  already end with `]`.
- AJAX uploads are enabled by default. Set `noAjax=1` to render without the
  drag-and-drop target.
- `unzip=1` adds `zip` to the allowed extensions only when `maxFiles=0`.
- `maxFiles=1` allows replacement of the current file and sets the upload max to
  one file.
- Overwrite mode preserves existing description, tags, and filedata where
  possible, and refuses overwrites that would replace a file owned by another
  field on the same page.
- Tags use [[FieldtypeFile]] constants: `useTagsOff`, `useTagsNormal`,
  `useTagsPredefined`, or `useTagsNormal | useTagsPredefined`.
- Pagefile custom fields are configured through the file field's template. Use
  `getItemInputfields()` only when the value has a real Page/Field context.
- [[InputfieldImage]] extends this class and adds image-specific rendering,
  editing, focus point, and variation behavior.

**Source file:** `wire/modules/Inputfield/InputfieldFile/InputfieldFile.module`
