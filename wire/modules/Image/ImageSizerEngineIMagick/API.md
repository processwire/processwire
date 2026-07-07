# ImageSizerEngineIMagick

ImageSizer engine module that upgrades ProcessWire image manipulations to use PHP's
IMagick (ImageMagick) library when available. This replaces the default GD-based image
processing with higher-quality resizing, better color management, and additional features
like WebP output, sepia conversion, and greyscale conversion.

When this module is installed and IMagick is available on the server, ProcessWire
automatically uses it for all image resizing operations. No changes to your template
code are needed — `$image->size(300, 200)` and all other [[Pageimage]] manipulation
methods continue to work as before, but now use ImageMagick under the hood.

```php
// Install the module (auto-detected if IMagick is available)
$modules->get('ImageSizerEngineIMagick');

// All existing image manipulation code benefits automatically:
$thumb = $page->image->size(300, 300);
$wide = $page->image->width(1200);
```

## Properties

Properties are inherited from [[ImageSizerEngine]] and configured via `$config->imageSizerOptions`
in `site/config.php` or per-image options arrays. This module adds no public properties of its own.

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `quality` | `int` | `80` | JPEG/PNG quality (1–100). The IMagick engine defaults to 80, which is roughly equivalent to GD's 90 |
| `sharpening` | `string` | `'soft'` | Sharpening mode: `'none'`, `'soft'`, `'medium'`, or `'strong'` |
| `interlace` | `bool` | `false` | Enable progressive JPEG output |
| `autoRotation` | `bool` | `true` | Auto-rotate based on EXIF orientation |
| `upscaling` | `bool` | `true` | Allow images to be enlarged beyond original dimensions |
| `cropping` | `bool\|string\|array` | `true` | Crop direction or coordinates (see [[ImageSizerEngine]]) |
| `webpAdd` | `bool` | `false` | Also create a WebP copy alongside each variation |
| `webpOnly` | `bool` | `false` | Create only the WebP file (no original format) |
| `webpQuality` | `int` | `90` | WebP image quality (1–100) |
| `enginePriority` | `int` | `1` | Priority among engines (1 = first) |

## Methods

### getLibraryVersion()

Get the installed ImageMagick library version string.

```php
$engine = $modules->get('ImageSizerEngineIMagick');
echo $engine->getLibraryVersion(); // e.g. "ImageMagick 7.1.0-57 n=7.1.0-57"
```

### supportsFormat($format)

Check whether a given image format is supported by IMagick for both source and target.

```php
if($engine->supportsFormat('WEBP')) {
    echo "WebP is supported!";
}
```

The `$format` argument is case-insensitive and should be a format string like `'PNG'`, `'JPG'`,
`'WEBP'`, `'GIF'`, etc. Format suffixes like `'PNG24'` or `'PNG24-alpha'` are stripped to the
base format before lookup. Results are cached in a static array for subsequent calls.

### supported($action = 'imageformat')

Check whether IMagick is available and supports the requested action.

```php
// Check if IMagick is available at all
if($engine->supported('install')) { /* ... */ }

// Check if current image format is supported
if($engine->supported('imageformat')) { /* ... */ }

// Check if WebP is supported
if($engine->supported('webp')) { /* ... */ }
```

The `$action` argument accepts:

| Action | What it checks |
|--------|---------------|
| `'install'` | Whether the PHP `IMagick` class exists |
| `'imageformat'` | Whether the current image's format is supported by IMagick |
| `'webp'` | Whether WebP format is supported |

This is the method ProcessWire core calls to auto-detect whether to use this engine.

### getImagick($filename = '')

Create and return a new `\Imagick` instance, optionally loading an image file.

```php
$imagick = $engine->getImagick('/path/to/image.jpg');
$imagick->resizeImage(300, 200, \Imagick::FILTER_LANCZOS, 1);
```

Throws a `WireException` if the file cannot be loaded.

### reduceByHalf($dstFilename = '')

Reduce image dimensions by 50% using IMagick's `minifyImage()` method. This is
more efficient than a standard resize for a 50% reduction.

```php
$engine->prepare('/path/to/image.jpg');
$engine->reduceByHalf(); // overwrites original
// or save to a different file
$engine->reduceByHalf('/path/to/half.jpg');
```

Returns `true` on success, `false` on failure.

### convertToGreyscale($dstFilename = '')

Convert an image to greyscale.

```php
$engine->prepare('/path/to/image.jpg');
$engine->convertToGreyscale('/path/to/grey.jpg');
```

Returns `true` on success, `false` on failure.

### convertToSepia($dstFilename = '', $sepia = 55)

Apply a sepia tone effect to an image.

```php
$engine->prepare('/path/to/image.jpg');
$engine->convertToSepia('/path/to/sepia.jpg');

// Adjust the sepia strength (threshold: 0–100, default 55)
$engine->convertToSepia('/path/to/sepia.jpg', 80);
```

The `$sepia` argument accepts a value from 0 to 100, where higher values produce a
stronger sepia effect. Internally, 35 is added before passing to `\Imagick::sepiaToneImage()`.

Returns `true` on success, `false` on failure.

## Hooks

### imSaveReady($im, $filename)

Called just before an image is saved to disk, after all transformations have been applied
but before quality/compression settings take effect. The `$im` parameter is the `\Imagick`
object, allowing you to apply final custom adjustments.

```php
$wire->addHookAfter('ImageSizerEngineIMagick::imSaveReady', function($event) {
    $im = $event->arguments(0);    /** @var \IMagick $im */
    $filename = $event->arguments(1); /** @var string $filename */
    
    // Add a watermark or apply a final filter
    // Note: a clone of the original IMagick resource is passed,
    // so changes here affect the WebP copy (if any) too.
});
```

This hook fires for every image variation created. A clone of the IMagick resource is
made before compression, so the original is preserved for WebP output if `webpAdd` is enabled.

### install()

Called when the module is installed. Throws a `WireException` if IMagick is not available
on the server. You can hook this to perform additional setup:

```php
$wire->addHookAfter('ImageSizerEngineIMagick::install', function($event) {
    // Additional install logic
});
```

## Notes

- **Automatic fallback**: ProcessWire's [[ImageSizer]] automatically detects whether
  `ImageSizerEngineIMagick` is installed and supported. If IMagick is not available, it falls
  back to `ImageSizerEngineGD` (the default GD-based engine).

- **Quality difference**: The IMagick engine uses a default quality of 80, which produces
  results visually similar to GD's 90. This is set in the constructor and can be overridden
  via `$config->imageSizerOptions['quality']` in `site/config.php`.

- **Color management**: IMagick provides superior color profile handling compared to GD.
  ICC profiles, EXIF, IPTC, and XMP metadata are all processed and can be stripped or
  preserved. By default, non-ICC metadata is removed to reduce file size, matching GD behavior.

- **Engine priority**: This module sets `enginePriority` to 1 (first). If multiple engines
  are installed, the one with the lowest priority number that passes its `supported()` check
  is used. The `ImageSizerEngineAnimatedGif` module sets priority 9 so it only handles
  animated GIFs when no other engine does.

- **WebP support**: When WebP is supported by the installed IMagick version, the engine can
  automatically create WebP copies alongside standard image variations. Enable with the
  `webpAdd` or `webpOnly` properties.

- **Sharpening**: Unlike GD, the IMagick engine uses `unsharpMaskImage()` for sharpening.
  The sharpening parameters are adjusted based on the final image size (megapixels) and the
  selected sharpening mode.

- **Image formats**: Supported source formats are JPG, JPEG, PNG24, PNG, GIF, and GIF87.
  PNG8 is deliberately excluded due to bugs in some ImageMagick versions. WebP is supported
  as a target format when available.

- **Module by Horst Nogajski**, with contributions by Ryan Cramer. Licensed under MPL 2.0.

- **Source file:** `wire/modules/Image/ImageSizerEngineIMagick/ImageSizerEngineIMagick.module`

