<?php namespace ProcessWire;

// Shared functions for template files.
// Included once from _init.php. Add site-wide helper functions here.

/**
 * Render an image as a <figure>, or a placeholder when there is no image
 *
 * Accepts a single Pageimage, or a Pageimages array (in which case the first
 * image is used). Images are reduced to $maxWidth but never enlarged.
 *
 * When there is no image, returns siteImagePlaceholder(), which is a blank
 * string when placeholders are disabled.
 *
 * @param Pageimage|Pageimages|null $image
 * @param int $maxWidth Maximum width in pixels
 * @param string $class Optional class attribute for the <figure>
 * @return string
 *
 */
function siteImage($image, $maxWidth = 1200, $class = '') {
	if($image instanceof Pageimages) $image = $image->first();
	if(!$image instanceof Pageimage) return siteImagePlaceholder($class);
	$sanitizer = wire()->sanitizer;
	if($image->width > $maxWidth) $image = $image->width($maxWidth);
	// entities1() does not double-encode descriptions already encoded by TextformatterEntities
	$alt = $sanitizer->entities1((string) $image->description);
	$class = $class === '' ? '' : " class='" . $sanitizer->entities($class) . "'";
	return
		"<figure$class>" .
			"<img src='$image->url' width='$image->width' height='$image->height' alt='$alt' loading='lazy'>" .
		"</figure>";
}

/**
 * Render a placeholder where an image is missing, or a blank string when placeholders are off
 *
 * Controlled by $config->siteImagePlaceholders in /site/config.php:
 * true shows placeholders to everyone, 'editors' shows them only to users
 * who can edit the current page, and false disables them.
 *
 * @param string $class Optional class attribute for the <figure>, added before "image-placeholder"
 * @return string
 *
 */
function siteImagePlaceholder($class = '') {
	$setting = wire()->config->siteImagePlaceholders;
	if($setting === null) $setting = true;
	if(!$setting) return '';
	if($setting === 'editors') {
		$page = wire()->page;
		if(!$page || !$page->editable()) return '';
	}
	$sanitizer = wire()->sanitizer;
	$class = $sanitizer->entities(trim("$class image-placeholder"));
	$label = $sanitizer->entities(__('Image'));
	$description = $sanitizer->entities(__('Image placeholder: add an image to replace it'));
	return
		"<figure class='$class' role='img' aria-label='$description'>" .
			"<span class='image-placeholder-label' aria-hidden='true'>$label</span>" .
		"</figure>";
}
