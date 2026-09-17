<?php namespace ProcessWire;

// Template file for the “home” template, used by the homepage
// ------------------------------------------------------------
// Elements with an id attribute replace the matching element in _main.php
// (Markup Regions). See /site/AGENTS.md for the region ids.

/** @var HomePage $page */

$children = $page->children;

?>

<div id="content" class="stack">

	<?= siteImage($page->image, 1600, 'page-image') ?>

	<div class="prose"><?= $page->body ?></div>

	<?php if($children->count()): ?>
	<ul class="card-grid" role="list">
		<?php foreach($children as $child): ?>
		<li class="card">
			<h2 class="card-title"><a href="<?= $child->url ?>"><?= $child->title ?></a></h2>
			<?php if($child->get('summary')): ?>
			<p><?= $child->get('summary') ?></p>
			<?php endif; ?>
		</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>

</div>
