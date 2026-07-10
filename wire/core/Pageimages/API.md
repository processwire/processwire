# Pageimages / Pageimage / $page->images

`Pageimages` is the collection class for image fields in ProcessWire — a type of
[[Pagefiles]] that contains [[Pageimage]] objects. It represents the value of
a multi-image [[FieldtypeImage]] field. Each item in the collection is a
`Pageimage` — a single image file with built-in resize, crop, focus, variation
management, and WebP support.

```php
// $page->images is a Pageimages object
// each $image is a Pageimage
foreach($page->images as $image) {
    $thumb = $image->size(200, 200);
    echo "<a href='$image->url'>";
    echo "<img src='$thumb->url' alt='$image->description' />";
    echo "</a>";
}
```

When a FieldtypeImage field has `outputFormat=single` (or `auto` with
`maxFiles=1`), the field returns a single `Pageimage` (or `null`) rather than a
`Pageimages` collection. See [[FieldtypeImage]] for field-level configuration.

---

## Pageimage properties

`Pageimage` extends [[Pagefile]], so all `Pagefile` properties (`url`,
`filename`, `basename`, `description`, `tags`, `filesize`, `created`,
`modified`, etc.) are available in addition to the image-specific ones below.

### Dimensions and appearance

| Property | Type | Description |
|----------|------|-------------|
| `width` | `int` (read-only) | Width of the image in pixels. SVGs without explicit dimensions return `'100%'`. |
| `height` | `int` (read-only) | Height of the image in pixels. SVGs without explicit dimensions return `'100%'`. |
| `ratio` | `float` (read-only) | Width ÷ height. `1.0` = square, `>1` = landscape, `<1` = portrait. (3.0.154+) |
| `ext` | `string` | File extension without dot (`'jpg'`, `'png'`, `'svg'`, …). |
| `alt` | `string` (read-only) | Alias for `description` — convenient in `alt=""` attributes. (3.0.125+) |
| `src` | `string` (read-only) | Alias for `url`. (3.0.125+) |

### Focus

| Property | Type | Description |
|----------|------|-------------|
| `focus` | `array` (read-only) | Associative array with `top`, `left`, `zoom` (percentages 0–100), `default` (bool), and `str` (readable string). |
| `focusStr` | `string` (read-only) | Human-readable string e.g. `"top=50%,left=50%,zoom=0% (default)"`. |
| `hasFocus` | `bool` (read-only) | `true` when custom focus has been set (non-default). |

### Variations

| Property | Type | Description |
|----------|------|-------------|
| `original` | `Pageimage\|null` (read-only) | The original image this variation was resized from, or `null` if this *is* the original. |
| `suffix` | `array` (read-only) | Array of suffix words in the variation filename (e.g. `['hidpi']`). |
| `suffixStr` | `string` (read-only) | Comma-separated suffixes. |
| `error` | `string` (read-only) | Last resize/crop error message, or empty string. |

### WebP

| Property | Type | Description |
|----------|------|-------------|
| `webp` | `PagefileExtra` (read-only) | WebP version helper. Access `->url`, `->filename`, `->exists`, `->filesize`, etc. (3.0.132+) |
| `hasWebp` | `bool` (read-only) | Whether a WebP version currently exists on disk. |
| `webpUrl` | `string` (read-only) | URL to the WebP version (creates it if missing). |
| `webpFilename` | `string` (read-only) | Disk path to the WebP version. |

### Inherited from Pagefile

| Property | Type | Description |
|----------|------|-------------|
| `url` | `string` (read-only) | Web-accessible URL to the image. |
| `httpUrl` | `string` (read-only) | URL with scheme and hostname. |
| `URL` | `string` (read-only) | Same as `url` but with a cache-busting query string. |
| `HTTPURL` | `string` (read-only) | Same as `httpUrl` but with a cache-busting query string. |
| `filename` | `string` (read-only) | Full disk path to the image file. |
| `name` | `string` (read-only) | Basename — same as `basename`. |
| `basename` | `string` | Filename without path. Settable. |
| `description` | `string` | Description / alt text. Settable. |
| `tags` | `string` | Space-separated tags. Settable (requires field tags enabled). |
| `tagsArray` | `array` (read-only) | Tags as an array. |
| `filesize` | `int` (read-only) | File size in bytes. |
| `filesizeStr` | `string` | Human-readable file size (e.g. `"350 KB"`). |
| `created` | `int` | Unix timestamp of creation. |
| `modified` | `int` | Unix timestamp of last modification. |
| `page` | `Page` (read-only) | The Page this image belongs to. |
| `field` | `Field` (read-only) | The Field this image belongs to. |
| `hash` | `string` (read-only) | Unique hash for this file on the page. |

---

## Resize and crop methods

### size($width, $height, $options)

The primary resize/crop method. Returns a **new** `Pageimage` variation — the
original is never modified. Variations are created once and cached on disk; 
subsequent calls with the same dimensions return the cached file.

```php
// Exact size (cropped to fit)
$thumb = $image->size(400, 300);

// Proportional — specify 0 for the dimension to auto-scale
$thumb = $image->size(400, 0);  // width=400, height auto
$thumb = $image->size(0, 300); // height=300, width auto

// Cropping direction as a string
$thumb = $image->size(400, 300, 'north');     // crop from top
$thumb = $image->size(400, 300, 'southeast');  // crop from bottom-right

// Array of options
$thumb = $image->size(400, 300, [
    'cropping'   => 'center',
    'quality'    => 80,
    'upscaling'  => false,
    'sharpening' => 'medium',
]);

// Shortcuts for the $options argument:
$thumb = $image->size(400, 300, 'north');     // string -> cropping
$thumb = $image->size(400, 300, 80);          // int    -> quality
$thumb = $image->size(400, 300, false);        // bool   -> upscaling

// Use a predefined size from $config->imageSizes (3.0.151+)
$thumb = $image->size('landscape');

// If the requested size already matches the original, the original is returned
// (same object, not a clone) unless allowOriginal is explicitly disabled.
```

**Key options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `quality` | `int` | `90` (or config) | JPEG/WEBP quality 1-100. |
| `upscaling` | `bool` | `true` | Allow enlarging smaller images. |
| `cropping` | `string\|bool\|array` | `true` | Crop mode. `true` = auto/center (uses focus if available). Direction strings: `north`/`n`, `south`/`s`, `east`/`e`, `west`/`w`, `northwest`/`nw`, `northeast`/`ne`, `southwest`/`sw`, `southeast`/`se`, `center`. `false` or `''` disables cropping. Pixel coords: `'x100y200'`. Array: `[100, 200]` or `['10%','20%']`. |
| `focus` | `bool` | `true` | Use focus area for crops when both width and height are specified. |
| `suffix` | `string\|array` | none | One or more suffix words appended to the variation filename (e.g. `'thumbnail'`, `['thumb', '2x']`). |
| `forceNew` | `bool` | `false` | Re-create the variation even if a cached file exists. |
| `sharpening` | `string` | `'soft'` | `'none'`, `'soft'`, `'medium'`, or `'strong'`. |
| `autoRotation` | `bool` | `true` | Correct EXIF orientation. |
| `rotate` | `int` | `0` | Rotate 90, 180, or 270 degrees. |
| `flip` | `string` | none | `'vertical'` or `'horizontal'`. |
| `hidpi` | `bool` | `false` | Create a 2x retina variation. Appends `-hidpi` suffix. |
| `hidpiQuality` | `int` | `40` (or config) | Quality for HiDPI variations. |
| `allowOriginal` | `bool` | `false` | Return the original when already at requested dimensions (must be the only option). |
| `cleanFilename` | `bool` | `false` | Strip historical resize info from the filename. |
| `nameWidth` | `int` | null | Override the width number in the variation filename. |
| `nameHeight` | `int` | null | Override the height number in the variation filename. |
| `webpAdd` | `bool` | `false` | Also create a `.webp` variation alongside the primary file. |
| `webpQuality` | `int` | `90` | Quality for the WebP variation. |
| `webpOnly` | `bool` | `false` | Keep only the `.webp` (delete the primary). Requires `webpAdd`. |

> **Note:** ProcessWire does not keep separate copies for different `quality`
> or `upscaling` values. If a variation at the same dimensions already exists,
> changing quality/upscaling has no effect unless you first call
> `removeVariations()` or pass `forceNew => true`.

### width($n, $options) / height($n, $options)

Resize to a specific width (or height) with proportional dimensions. When
called with no argument, returns the current width (or height) in pixels.

```php
$thumb = $image->width(600);    // new Pageimage at 600px wide, height proportional
$px    = $image->width();       // int — width of this image

$thumb = $image->height(400);  // new Pageimage at 400px tall, width proportional
$px    = $image->height();     // int — height of this image
```

### crop($x, $y, $width, $height, $options)

Crop a rectangle from the image starting at pixel position (`$x`, `$y`) with
the given width and height. Returns a new `Pageimage`.

```php
$crop = $image->crop(100, 200, 150, 100);
echo "<img src='$crop->url' />";
```

### maxWidth($n, $options) / maxHeight($n, $options)

Return an image no larger than the given width (or height). If the source is
already smaller, the original is returned (not a copy). Upscaling is
disabled automatically.

```php
$small = $image->maxWidth(1024);
$thumb = $image->maxHeight(300, ['quality' => 80]);
```

### maxSize($width, $height, $options)

Return an image constrained within both a maximum width and height (no
cropping). If the source already fits, the original is returned when
`allowOriginal` is `true`; otherwise a variation is created.

```php
$fit = $image->maxSize(800, 600);
$fit = $image->maxSize(800, 600, ['allowOriginal' => true]);
```

### sizeName($name, $options)

Resize using a predefined size from `$config->imageSizes`. Each named size
must contain at least `width` and `height`, and may include any other `size()`
option.

```php
// In site/config.php: $config->imageSizes = ['landscape' => ['width' => 600, 'height' => 300]];
$thumb = $image->sizeName('landscape');

// Override a predefined option at call time
$thumb = $image->sizeName('landscape', ['quality' => 75]);
```

### hidpiSize($width, $height, $options)

Same as `size()` but the resulting variation is tagged with a `-hidpi` suffix
and uses `hidpiQuality` instead of `quality`.

```php
$retina = $image->hidpiSize(400, 300);
```

---

## Variation methods

### getVariations($options)

Returns a [[Pageimages]] collection of all size variations of this image. Use
`$options` to filter by width, height, suffix, name, etc.

```php
// All variations
$vars = $image->getVariations();
echo count($vars) . " variations\n";

// Variations at exactly 200px wide
$vars = $image->getVariations(['width' => 200]);

// Variations with a specific suffix
$vars = $image->getVariations(['suffix' => 'hidpi']);

// Get variation info arrays instead of Pageimage objects
$infos = $image->getVariations(['info' => true]);
foreach($infos as $name => $info) {
    echo "$info[width]x$info[height] — $info[url]\n";
}
```

**Filter options:** `width`, `height`, `width>=`, `width<=`, `height>=`,
`height<=`, `suffix` (string), `suffixes` (array), `noSuffix`, `noSuffixes`,
`name`, `noName`, `regexName`, `info` (bool), `verbose` (bool|int).

### isVariation($basename, $options)

Check whether a given filename is a variation of this image. Returns an info
array if it is, or `false` if not. The returned array includes `original`,
`url`, `path`, `width`, `height`, `actualWidth`, `actualHeight`, `crop`,
`suffix`, and (for variations-of-variations) `parent` and `suffixAll`.

```php
$info = $image->isVariation('photo.200x200.jpg');
if($info) {
    echo "Variation: {$info['width']}x{$info['height']}, suffixes: " . implode(',', $info['suffix']);
}
```

### rebuildVariations($mode, $suffix, $options)

Delete and recreate existing variations. Useful after replacing an original
image or changing global resize settings.

```php
// Safe rebuild: only non-suffix, non-crop variations (default)
$result = $image->rebuildVariations();
echo "Rebuilt: " . implode(', ', $result['rebuilt']);
echo "Skipped: " . implode(', ', $result['skipped']);

// Rebuild all variations except those with the 'hidpi' suffix
$result = $image->rebuildVariations(2, ['hidpi']);
```

Returns an associative array with `rebuilt`, `skipped`, `errors`, and
`reasons` (each an array).

| Mode | Behavior |
|------|----------|
| `0` | Rebuild non-suffix, non-crop variations + those with suffix in `$suffix` (inclusion). **Safest.** |
| `1` | Rebuild all non-suffix variations + those with suffix in `$suffix` (inclusion). |
| `2` | Rebuild all variations except those with suffix in `$suffix` (exclusion). |
| `3` | Rebuild only variations with a suffix in `$suffix` (only-inclusion). |
| `4` | Rebuild only non-proportional variations (width + height both specified). |

### removeVariations($options)

Delete all (or filtered) variations of this image. Accepts the same filter
options as `getVariations()`, plus:

- `dryRun` (bool): Return the filenames that *would* be deleted without deleting.
- `getFiles` (bool): Return an array of deleted filenames.

```php
// Remove all variations
$image->removeVariations();

// Dry run — see what would be deleted
$files = $image->removeVariations(['dryRun' => true]);

// Remove only variations wider than 1000px
$image->removeVariations(['width>=' => 1000]);
```

### setOriginal($image) / getOriginal()

`setOriginal` marks this image as a variation of the given `Pageimage`.
`getOriginal` returns the original `Pageimage` if this is a variation, or
`null` if this is the original.

```php
$thumb = $image->size(200, 200);
$orig  = $thumb->getOriginal();   // returns $image
$orig  = $thumb->original;         // same via property
```

---

## Focus

### focus($top, $left, $zoom)

Get or set the focus area used by `size()` when both width and height are
specified and the `focus` option is `true` (the default). Focus ensures the
important part of the image is retained during cropping.

- **Get** (no arguments): returns an array with `top`, `left`, `zoom`,
  `default`, and `str`.
- **Set** (two numbers): `focus(25, 70)` -> 25 % from top, 70 % from left.
- **Set** (string): `"25 70"` or `"top=25%, left=70%"` or `"25 70 30"` (with zoom).
- **Set** (array): `['top' => 25, 'left' => 70]` or `[25, 70]`.
- **Check** (`true`): returns `true` if custom focus exists, `false` if default.
- **Pixel mode** (`1`): returns pixel coordinates instead of percentages.
- **Unset** (`false`): remove focus, reverting to default (center).

```php
// Read focus
$focus = $image->focus();
echo "Focus: $focus[top]% top, $focus[left]% left";

// Set focus (percentages)
$image->focus(30, 70);

// Check if custom focus is set
if($image->focus(true)) { /* custom focus exists */ }

// Get focus as pixel coordinates
$px = $image->focus(1);
echo "Center at {$px['left']}, {$px['top']} px";

// Unset focus (revert to default center)
$image->focus(false);

// Set via string
$image->focus("top=30%, left=70%, zoom=0%");
```

> Focus is stored in the image's `filedata` and persists across page saves.

---

## WebP

### webp($options)

Returns a [[PagefileExtra]] object representing the WebP version of this image.
The WebP file is created on-demand when you access `->url` or `->filename`.

```php
$webp = $image->webp();

if($webp->exists()) {
    echo "<source srcset='$webp->url' type='image/webp'>";
}

// File size comparison (the WebP may be smaller)
echo "Original: {$image->filesize} bytes, WebP: {$webp->filesize} bytes";
echo "Savings: {$webp->savingsPct}";
```

**`$options` overrides (3.0.229+):** `useSrcUrlOnSize` (bool, default from
`$config->webpOptions`), `useSrcUrlOnFail` (bool), `quality` (int, default 90).

> When `$config->webpOptions['useSrcUrlOnSize']` is `true` (the default) and the
> WebP file is *larger* than the original, `->url` falls back to the original
> image URL automatically. Likewise `useSrcUrlOnFail` falls back to the original
> URL if WebP creation fails.

### hasWebp / webpUrl / webpFilename

Convenience property shortcuts:

```php
if($image->hasWebp) {
    echo $image->webpUrl;       // URL to the .webp file
    echo $image->webpFilename;  // disk path to the .webp file
}
```

### Creating WebP during resize

Pass `webpAdd => true` to `size()` to create a `.webp` variation alongside the
primary format:

```php
$thumb = $image->size(600, 400, ['webpAdd' => true]);
// $thumb->url           -> 600x400.jpg URL
// $thumb->webp->url     -> 600x400.webp URL
```

---

## Rendering

### render($markup, $options)

Renders markup for this image using a template string with placeholder
replacements. When called on a `Pageimages` collection, it iterates all
images. Available placeholders:

| Placeholder | Replaced with |
|-------------|---------------|
| `{url}` or `{src}` | Image URL |
| `{httpUrl}` | URL with scheme + hostname |
| `{URL}` | URL + cache-busting query string |
| `{HTTPURL}` | httpUrl + cache-busting query string |
| `{description}` or `{alt}` | Image description |
| `{tags}` | Image tags |
| `{width}` | Image width in pixels |
| `{height}` | Image height in pixels |
| `{hidpiWidth}` | HiDPI width |
| `{hidpiHeight}` | HiDPI height |
| `{ext}` | File extension |
| `{class}` | CSS class (`options['class']` or `'pw-pageimage'`) |
| `{original.url}` etc. | Prepend `original.` to reference the full-size original. |

```php
$image = $page->images->first();

// Default:<img src='{url}' alt='{alt}' />
echo $image->render();

// Custom markup
echo $image->render("<img class='pw-image' src='{url}' alt='{alt}'>");

// Resize + custom markup
echo $image->render("<img src='{url}' alt='{alt}'>", ['width' => 300]);

// Options as first argument (markup defaults)
echo $image->render(['width' => 300, 'height' => 200]);

// Width/height as a shorthand string
echo $image->render('300x200');

// Link thumbnail to the original
echo $image->render([
    'markup' => "<a href='{original.url}'><img src='{url}' alt='{alt}'></a>",
    'width'  => 300,
    'height' => 300,
]);
```

**`$options`:** `width`, `height`, `markup` (template string), `link` (bool —
wrap in `<a href='{original.url}'>`), `alt` (override alt text), `class`
(override CSS class), `limit` (int — max images when called on a
`Pageimages` collection), plus any `size()` option.

---

## Pageimages collection methods

In addition to all [[WireArray]] methods (count, first, last, get, each,
filter, sort, slice, etc.) and [[Pagefiles]] methods (add, delete, deleteAll,
rename, findTag, getTag, tags, etc.), `Pageimages` provides:

### add($item)

Add a `Pageimage` or create one from a filename string.

```php
$page->images->add('/tmp/uploaded-photo.jpg');
$page->save('images');
```

### getFile($name)

Returns the `Pageimage` matching the given basename, or `null`. Also finds
variations — e.g., passing `'photo.200x200.jpg'` finds the original `Pageimage`
that the variation belongs to.

```php
$file = $page->images->getFile('photo.200x200.jpg');
```

### getAllVariations()

Returns an associative array of all variation filenames indexed by the
original file basename.

```php
$vars = $page->images->getAllVariations();
print_r($vars);
// [
//   'photo.jpg' => ['photo.200x200.jpg', 'photo.400x300.jpg'],
//   'banner.jpg' => ['banner.1200x400.jpg'],
// ]
```

### render($markup, $options)

Renders markup for every image in the collection. Same placeholder syntax as
`Pageimage::render()`. Accepts `limit` to cap the number of images.

```php
// Default<img> output for all images
echo $page->images->render();

// Custom gallery with thumbnails
echo $page->images->render(
    "<li><a href='{original.url}'><img src='{url}' alt='{alt}'></a></li>",
    ['width' => 300, 'height' => 300, 'limit' => 12]
);
```

---

## Hooks

### Pageimage hooks

| Hook | When | Arguments |
|------|------|-----------|
| `Pageimage::size` | Before/after resize | `$width`, `$height`, `$options` |
| `Pageimage::crop` | Before/after crop | `$x`, `$y`, `$width`, `$height`, `$options` |
| `Pageimage::render` | Before/after render | `$markup`, `$options` |
| `Pageimage::isVariation` | When checking variation | `$basename`, `$options` |
| `Pageimage::rebuildVariations` | Before/after rebuild | `$mode`, `$suffix`, `$options` |
| `Pageimage::createdVariation` | After a new variation is created | `$image` (the new Pageimage), `$data` (array) |
| `Pageimage::filenameDoesNotExist` | When source file is missing | `$filename` — return `true` to proceed |

```php
// Log every new variation
$wire->addHookAfter('Pageimage::createdVariation', function(HookEvent $event) {
    $image = $event->arguments(0); /** @var Pageimage $image */
    $wire->log->save('variations', "Created: $image->basename");
});
```

### Inherited hooks

`Pageimage` inherits hookable `url()`, `filename()`, and `___noCacheURL()`
from [[Pagefile]]. The `url()` and `filename()` methods are overridden in
`Pageimage` to dispatch to the hookable `___url()` / `___filename()` only when
hooks are registered, avoiding overhead when no hooks exist.

```php
// Example: modify image URLs on a CDN
$wire->addHookAfter('Pageimage::url', function(HookEvent $event) {
    $event->return = str_replace(
        $event->wire('config')->urls('root'),
        'https://cdn.example.com/',
        $event->return
    );
});
```

---

## Constructor

`Pageimage` is normally obtained from a field value. To construct directly:

```php
$pageimage = new Pageimage($page->images, '/path/to/file.png');
```

The first argument must be a `Pageimages` instance (not a generic
`Pagefiles`). A `WireException` is thrown if a non-`Pageimages` instance is
provided.

---

## PagefileExtra (WebP helper)

The `webp` property returns a `PagefileExtra` object — the same class that
handles any extra-format version of a file. Key properties:

| Property | Type | Description |
|----------|------|-------------|
| `url` | `string` | URL to the WebP file (creates it if missing). |
| `httpUrl` | `string` | URL with scheme and hostname. |
| `URL` / `HTTPURL` | `string` | Cache-busting URL variants. |
| `filename` | `string` | Full disk path. |
| `basename` | `string` | Filename without path. |
| `ext` | `string` | Extension (`'webp'`). |
| `exists` | `bool` | Whether the WebP file exists on disk. |
| `filesize` | `int` | WebP file size in bytes. |
| `savings` | `int` | Bytes saved vs. the original. |
| `savingsPct` | `string` | Percentage saved (e.g. `'35%'`). |

---

## Notes

- **Access:** `Pageimages` and `Pageimage` instances are obtained from
  [[FieldtypeImage]] field values. Construct `Pageimage` directly only when
  needed — the first argument must be a `Pageimages` instance.
- **Variation caching:** `size()` creates the variation file once and returns
  a clone of the original `Pageimage` with the variation filename. On
  subsequent calls, the same cached file is returned. Use `forceNew` or
  `removeVariations()` to regenerate.
- **Variation filenames** follow the pattern `basename.WxH[crop][suffixes].ext`
  — e.g., `photo.400x300nw-hidpi.jpg`.
- **SVG images:** `size()` returns the original `Pageimage` unchanged (SVGs
  are not resized server-side). Width/height may return `'100%'` for SVGs
  without explicit dimensions.
- **`__toString()`:** `Pageimage` inherits `__toString()` from `Pagefile`,
  which returns the basename (e.g., `photo.jpg`).
- **EXIF orientation:** JPEG images are checked for EXIF orientation, and
  width/height are swapped for portrait orientations (when `autoRotation` is
  enabled, which it is by default).
- **`$config->imageSizerOptions`:** Default resize options are set in
  `site/config.php` and merged with per-call options.
- **`$config->imageSizes`:** Predefined size definitions used by
  `size('name')` and `sizeName('name')`.
- **Error handling:** Check `$image->error` (or `->error` property) after
  `size()` calls. An empty string means success. Errors are also logged to the
  `image-sizer` log file.
- Extends [[Pagefile]], which extends [[WireData]]. See also [[Pagefiles]],
  [[WireArray]], and [[FieldtypeImage]].
- **Source files:** `wire/core/Pageimages/Pageimages.php`,
  `wire/core/Pageimages/Pageimage.php`,
  `wire/core/Pageimages/PageimageVariations.php`,
  `wire/core/Pageimages/PageimageDebugInfo.php`.

