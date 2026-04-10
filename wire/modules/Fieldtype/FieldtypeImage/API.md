# FieldtypeImage

Field that stores one or more uploaded images with optional descriptions, tags, and focus points.
FieldtypeImage extends FieldtypeFile — all FieldtypeFile settings and behaviors also apply.

## Value type

The value type depends on the field's `outputFormat` setting (when output formatting is on):

- `outputFormat=auto` (default) — `Pageimages` when `maxFiles` allows multiple; `Pageimage|null` when `maxFiles=1`
- `outputFormat=array` — always `Pageimages`
- `outputFormat=single` — `Pageimage|null`
- `outputFormat=string` — rendered `string` using the `outputString` template

When output formatting is **off**: always a `Pageimages` object.

## Getting and setting values

```php
// Iterate images (Pageimages, multi-image)
foreach($page->images as $image) {
    echo "<img src='$image->url' alt='$image->description'>";
}

// Get single image (when outputFormat=single or auto with maxFiles=1)
$image = $page->hero_image;
if($image) echo "<img src='$image->url' alt='$image->description'>";

// Get a specific image by name
$image = $page->images->get('photo.jpg');

// Add an image from a local path or URL
$page->images->add('/path/to/photo.jpg');
$page->save('images');

// Remove an image
$page->images->delete($page->images->get('photo.jpg'));
$page->save('images');
```

## Pageimage properties

Each item in a `Pageimages` collection is a `Pageimage` object (extends `Pagefile`):

```php
$image->url         // URL to the original image
$image->httpUrl     // Full URL including http(s)://
$image->filename    // Filesystem path to the image
$image->name        // Basename (e.g. 'photo.jpg')
$image->ext         // Extension without dot (e.g. 'jpg')
$image->description // Description / alt text
$image->tags        // Space-separated tags (when tags are enabled)
$image->width       // Original image width in pixels
$image->height      // Original image height in pixels
$image->ratio       // Aspect ratio (width / height) as float, e.g. 1.78 for 16:9
$image->filesize    // File size in bytes
$image->filesizeStr // Human-readable size (e.g. '350 KB')
$image->focus       // Focus point as ['top' => float, 'left' => float, 'zoom' => int]
$image->focusStr    // Focus point as a CSS background-position string (e.g. '60% 40%')
```

## Resizing images

`Pageimage` provides methods to create cached image variations at custom sizes:

```php
// Resize to exact dimensions (cropped to fit)
$thumb = $image->size(300, 200);
echo "<img src='$thumb->url' width='$thumb->width' height='$thumb->height'>";

// Resize by width, proportional height
$resized = $image->width(600);

// Resize by height, proportional width
$resized = $image->height(400);

// Crop at a specific position
$thumb = $image->size(300, 300, ['cropping' => 'north']);

// Disable focus-point cropping (crops from center instead)
$thumb = $image->size(400, 300, ['focus' => false]);

// Custom quality and sharpening
$thumb = $image->size(300, 200, ['quality' => 80, 'sharpening' => 'medium']);
```

Common size options: `quality` (JPEG quality 1–100), `cropping` (true/false/position string such as
'north', 'center', 'southwest', etc.), `focus` (bool, use focus point for cropping), `upscaling` (bool),
`sharpening` ('none'/'soft'/'medium'/'strong'), `rotate` (degrees), `flip` ('x'/'y'), `suffix`
(added to variation filename), `forceNew` (bool, regenerate even if cached).

## Pageimages methods

```php
$images->count()              // Number of images
$images->first()              // First Pageimage, or false if empty
$images->last()               // Last Pageimage, or false if empty
$images->get('photo.jpg')     // Get Pageimage by name, or null if not found
$images->getRandom()          // Returns a random Pageimage, or null
$images->findTag('hero')      // Returns new Pageimages containing all items with that tag
$images->getTag('hero')       // Returns first Pageimage with that tag, or null
$images->add('/path/to/img')  // Add image from local path or URL
$images->delete($image)       // Mark for deletion (call $page->save() after)
```

## Selectors

```php
// Pages that have at least one image
$pages->find('images.count>0');

// Pages with images wider than 1200 pixels
$pages->find('images.width>1200');

// Pages with portrait images (height > width)
$pages->find('images.ratio<1');

// Pages with landscape images
$pages->find('images.ratio>1');

// Pages with a specific image filename
$pages->find('images=hero.jpg');

// Pages whose image description contains a word (fulltext)
$pages->find('images.description%=sunset');

// Pages with images tagged 'hero' (requires tags enabled on field)
$pages->find('images.tags*=hero');
```

Usable subfields: `data` (filename), `description`, `count`, `width`, `height`, `ratio`, `filesize`,
`created`, `modified`, `created_users_id`, `modified_users_id`, `tags` (when enabled), plus any custom
field names.

## Output / markup

```php
// Render image thumbnails with links to originals
foreach($page->images as $image) {
    $thumb = $image->size(300, 200);
    echo "<a href='$image->url'><img src='$thumb->url' alt='$image->description'></a>";
}

// Hero image (focus-point cropping is on by default)
$hero = $page->hero_image;
if($hero) {
    $sized = $hero->size(1200, 600);
    echo "<img src='$sized->url' alt='$hero->description'>";
}

// Background image using the focus point for CSS positioning
$image = $page->images->first();
if($image) {
    echo "<div style='background-image:url($image->url);background-position:$image->focusStr'></div>";
}
```

## Notes

- Supported upload formats by default: GIF, JPG/JPEG, PNG. Add `svg` (or others) to the `extensions` setting if needed.
- Image variations (resized copies) are cached in the same directory as the original and regenerated on demand when missing.
- Variation filenames follow the pattern `original.NNNxNNN[-suffix].ext`.
- `$image->size()` returns the original `Pageimage` unchanged if the requested dimensions match the original exactly.
- Width, height, and ratio are stored as indexed columns in the database, making them efficient for selector queries.
- Focus point coordinates are stored as percentages (0–100) for top and left, plus an optional zoom value.
- FieldtypeImage inherits all FieldtypeFile settings; see `FieldtypeFile/API.md` for the full list.
