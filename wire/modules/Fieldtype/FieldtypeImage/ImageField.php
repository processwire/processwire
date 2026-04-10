<?php namespace ProcessWire;

/**
 * Image Field (for FieldtypeImage)
 *
 * FieldtypeImage extends FieldtypeFile, so all FileField settings also apply.
 * See also: FieldtypeFile/FileField.php
 *
 * Configured with InputfieldImage
 * ==============================
 * @property string $gridMode Default admin grid mode: 'grid' (square thumbnails), 'left' (proportional), 'list' (verbose, default='grid').
 * @property string $focusMode Focus point selection: 'on' (focus point), 'zoom' (focus point + zoom), 'off' (disabled, default='on').
 * @property int $maxWidth Maximum width in pixels for uploaded images (0=no limit, default=0).
 * @property int $maxHeight Maximum height in pixels for uploaded images (0=no limit, default=0).
 * @property int $minWidth Minimum width in pixels required for uploaded images (0=no minimum, default=0).
 * @property int $minHeight Minimum height in pixels required for uploaded images (0=no minimum, default=0).
 * @property int $resizeServer How to resize images to max dimensions: 0=client-side when possible, 1=server-side only (default=0).
 * @property float $maxSize Maximum megapixels for client-side resize (0=no limit). e.g. 1.7 ≈ 1600×1000 pixels (default=0).
 * @property int $clientQuality Client-side resize quality for JPEG images, 10–100 (default=90).
 * @property bool|int $maxReject Refuse images that exceed max dimensions rather than resizing them? (default=false).
 * @property bool|int $dimensionsByAspectRatio Swap min/max dimensions for portrait images to accommodate aspect ratio? (default=false).
 *
 * @since 3.0.258
 *
 */
class ImageField extends FileField {
}
