<?php namespace ProcessWire;

// Template file for “home” template used by the homepage
// ------------------------------------------------------
// The #content div in this file will replace the #content div in _main.php
// when the Markup Regions feature is enabled, as it is by default. 
// You can also append to (or prepend to) the #content div, and much more. 
// See the Markup Regions documentation:
// https://processwire.com/docs/front-end/output/markup-regions/

?>

<div id="content">
	<article class="hauptartikel" aria-label="Hauptartikel">
            <h2><?php echo $page->title; ?></h2>
		<?php echo $page->bodycopy; ?>
        </article>
</div>

<div id="sidebar">
</div>
