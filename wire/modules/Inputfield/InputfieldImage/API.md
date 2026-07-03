# InputfieldImage

Image upload Inputfield for [[FieldtypeImage]] fields. It renders a sortable
thumbnail grid with inline descriptions, focus point controls, image editor
buttons, variation management, optional client-side resizing, and all inherited
[[InputfieldFile]] upload behavior.

```php
$f = $modules->get('InputfieldImage');
$f->name = 'photos';
$f->label = 'Photos';
$f->maxFiles = 5;
$f->extensions = 'JPG JPEG GIF PNG';
$f->maxWidth = 2000;
$f->maxHeight = 2000;
$f->gridMode = 'grid'; // grid, left, or list
```

In normal page editing, this Inputfield is created by [[FieldtypeImage]], which
sets the page, field, value, destination path hook, and field settings. For
standalone file-upload behavior, also read [[InputfieldFile]]. For shared
Inputfield attributes, labels, rendering, processing, errors, and visibility
selectors, see [[Inputfield]].

## Properties

Properties marked `*` default from `$config->adminThumbOptions` and may be
overridden on the Inputfield instance.

| Property | Type | Default | Description |
| --- | --- | --- | --- |
| `extensions` | `string` | `'JPG JPEG GIF PNG'` | Space-separated allowed image extensions. |
| `okExtensions` | `array` | `[]` | Manually whitelisted extensions, such as `['SVG']`. |
| `maxWidth` | `int\|string` | `''` | Maximum uploaded image width in pixels; larger images are resized unless `maxReject` is enabled. |
| `maxHeight` | `int\|string` | `''` | Maximum uploaded image height in pixels; larger images are resized unless `maxReject` is enabled. |
| `maxSize` | `float` | `0.0` | Max megapixels for client-side resize, such as `1.7`; alternative to max width/height. |
| `maxReject` | `bool\|int` | `0` | Reject images that exceed max dimensions rather than resizing them. |
| `minWidth` | `int\|string` | `''` | Minimum uploaded image width in pixels. |
| `minHeight` | `int\|string` | `''` | Minimum uploaded image height in pixels. |
| `dimensionsByAspectRatio` | `bool\|int` | `0` | Swap min/max width and height rules for portrait images. |
| `itemClass` | `string` | `'gridImage ui-widget'` | CSS classes used for each rendered image item. Append rather than replace unless you need full control. |
| `useImageEditor` | `int\|bool` | `1` | Whether crop, focus, variations, and action controls are available. Permission checks can disable it during render/process. |
| `adminThumbScale` | `int\|float` | from config | Deprecated compatibility setting; use `gridSize`. |
| `resizeServer` | `int\|bool` | `0` | `0` allows client-side resize when possible; `1` forces server-side resize. |
| `clientQuality` | `int` | `90` | Client-side JPEG quality percentage. |
| `editFieldName` | `string` | `''` | Field name to use in image editor URLs; blank uses the inputfield name. |
| `gridSize` * | `int` | `130` | Square admin thumbnail size in pixels. Values below 100 or 260+ fall back to 130. |
| `gridMode` * | `string` | `'grid'` | Admin display mode: `grid`, `left`, or `list`. |
| `focusMode` * | `string` | `'on'` | Focus UI mode: `on`, `zoom`, or `off`. |
| `imageSizerOptions` * | `array` | `[]` | Options passed to [[ImageSizer]] for admin thumbnails. |

Inherited [[InputfieldFile]] properties such as `maxFiles`, `maxFilesize`,
`overwrite`, `descriptionRows`, `useTags`, `tagsList`, `noUpload`, `noAjax`, and
`destinationPath` also apply.

## Constants

| Constant | Value | Description |
| --- | --- | --- |
| `defaultGridSize` | `130` | Default admin thumbnail grid size in pixels. |
| `debugRenderValue` | `false` | Development-only flag to force render-value mode. |

## Rendering

### render()

Render the complete image input: image grid, editor controls, upload area, and
hidden data fields.

```php
echo $f->render();
```

### renderList($value)

Render the thumbnail grid list. `$value` is usually a [[Pageimages]] collection.

```php
$html = $f->renderList($page->images);
```

### renderItem(Pageimage $pagefile, $id, $n)

Render one image item, including thumbnail, hover controls, edit fields, action
select, sort input, replace input, rename input, and focus input.

```php
$wire->addHookAfter('InputfieldImage::renderItem', function(HookEvent $event) {
    $event->return .= '<div class="my-extra-tool"></div>';
});
```

### renderUpload($value)

Render the image upload button/drop target. The rendered input name receives
`[]` unless the name already ends with `]`.

### renderButtons(Pageimage $pagefile, $id, $n)

Render the crop/focus/variations button toolbar. Returns an empty string when
`useImageEditor` is false.

### renderAdditionalFields(Pageimage $pagefile, $id, $n)

Empty hook target for adding per-image markup below the description fields.

```php
$wire->addHookAfter('InputfieldImage::renderAdditionalFields', function(HookEvent $event) {
    $image = $event->arguments(0); /** @var Pageimage $image */
    $event->return = "<p>{$image->width} x {$image->height}</p>";
});
```

## Thumbnails

### getAdminThumb(Pageimage $img, $useSizeAttributes = true, $remove = false)

Return admin thumbnail data as an array with keys:

| Key | Description |
| --- | --- |
| `thumb` | The thumbnail [[Pageimage]] used for markup. |
| `attr` | Image attribute array including `src`, `alt`, `data-w`, `data-h`, `data-original`, and `data-focus`. |
| `markup` | `<img>` markup. |
| `amarkup` | Linked `<img>` markup. |
| `error` | Thumbnail generation error text, or blank. |
| `title` | Human-readable title text. |

```php
$thumb = $f->getAdminThumb($pageimage, false);
echo $thumb['markup'];
```

### buildTooltipData(Pageimage $pagefile)

Return tooltip rows as `[label, value]` pairs. Default rows include dimensions,
filesize, variation count, and indicators for hidden status, description, and
tags when present.

```php
$wire->addHookAfter('InputfieldImage::buildTooltipData', function(HookEvent $event) {
    $data = $event->return;
    $data[] = ['EXIF', 'Available'];
    $event->return = $data;
});
```

## Editor Actions

### getImageEditButtons($pagefile, $id, $n, $buttonClass)

Return an array of crop, focus, and variations buttons. Hook after this method
to add or remove editor buttons. Added in 3.0.212.

### getImageThumbnailActions($pagefile, $id, $n, $class)

Return icon-only thumbnail hover actions. The default return value is an empty
array; hooks may add actions. Added in 3.0.212.

### getFileActions(Pageimage $pagefile)

Return actions for the image action dropdown, such as duplicate, hide/unhide,
flip, rotate, grayscale, sepia, reduce 50%, and remove focus where applicable.
The available actions depend on `maxFiles`, image extension, installed image
engines, hidden status, and focus state.

```php
$wire->addHookAfter('InputfieldImage::getFileActions', function(HookEvent $event) {
    $image = $event->arguments(0); /** @var Pageimage $image */
    $actions = $event->return;
    $actions['exif'] = 'Get EXIF data';
    $event->return = $actions;
});
```

### processUnknownFileAction(Pageimage $pagefile, $action, $label)

Hook target for processing custom actions added through `getFileActions()`.
Return `true` on success, `false` on failure, or `null` when not handled.

```php
$wire->addHookAfter('InputfieldImage::processUnknownFileAction', function(HookEvent $event) {
    $image = $event->arguments(0); /** @var Pageimage $image */
    $action = $event->arguments(1);
    if($action === 'exif') {
        $event->message('EXIF action handled for ' . $image->name);
        $event->return = true;
    }
});
```

## Processing

### processInput(WireInputData $input)

Process uploads, deletions, descriptions, tags, sorting, focus values, and image
actions. Image actions are processed only for non-AJAX saves.

### processInputFile(WireInputData $input, Pageimage $pagefile, $n)

Process one image item. This extends [[InputfieldFile]] item processing with
focus-point handling. Empty focus input resets to `50 50 0`. Changed focus
values rebuild variations.

### fileAdded(Pagefile $pagefile)

Validate and post-process a newly uploaded image. Non-SVG images must have
readable dimensions, are checked against min/max dimensions, and may be resized
to max dimensions. SVG files bypass pixel-dimension validation and image editor
operations.

## Notes

- Access via `$modules->get('InputfieldImage')`, or let [[FieldtypeImage]]
  create it automatically for image fields.
- Client-side resize loads `piexif.js` and `PWImageResizer.js` when resize
  limits are configured and `resizeServer=0`.
- Focus values are stored on the [[Pageimage]] as `top left zoom`, for example
  `25 75 0`.
- `renderSingleItem()` is deprecated and no longer used by core.
- SVG may be allowed by adding it to `extensions` and `okExtensions`, but SVG
  does not use the image editor, dimension validation, or raster image actions.
- For upload constraints, overwrite behavior, tags, descriptions, and custom
  Pagefile fields, see [[InputfieldFile]].

**Source files:** `wire/modules/Inputfield/InputfieldImage/InputfieldImage.module`,
`wire/modules/Inputfield/InputfieldImage/config.php`
