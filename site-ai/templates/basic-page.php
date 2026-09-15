<?php namespace ProcessWire;

// Template file for pages using the “basic-page” template
// -------------------------------------------------------
// Elements with an id attribute replace the matching element in _main.php
// (Markup Regions). See /site/AGENTS.md for the region ids.

/** @var BasicPagePage $page */

$children = $page->children;

?>

<div id="content" class="stack">

	<?= siteImage($page->image, 1200, 'page-image') ?>

	<div class="prose"><?= $page->body ?></div>

	<?php if($children->count()): ?>
	<nav class="subnav" aria-label="In this section">
		<ul role="list">
			<?php foreach($children as $child): ?>
			<li><a href="<?= $child->url ?>"><?= $child->title ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php endif; ?>

</div>
