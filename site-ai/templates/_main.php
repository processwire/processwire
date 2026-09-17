<?php namespace ProcessWire;

// Main output file, called after rendering the page’s template file.
// This is defined by $config->appendTemplateFile in /site/config.php.
//
// This profile uses Markup Regions: a template file can replace, append to,
// prepend to, or remove any element below that has an id attribute. The
// region ids used by this profile are documented in /site/AGENTS.md.
// https://processwire.com/docs/front-end/output/markup-regions/

/** @var Page $page */
/** @var HomePage $home */
/** @var Config $config */

$templatesUrl = $config->urls->templates;
$summary = (string) $page->get('summary'); // entity-encoded by TextformatterEntities
$isHome = $page->id === $home->id;

?><!DOCTYPE html>
<html lang="en">
<head id="html-head">
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= $isHome ? $home->title : "$page->title | $home->title" ?></title>
	<?php if($summary !== ''): ?>
	<meta name="description" content="<?= $summary ?>">
	<?php endif; ?>
	<link rel="stylesheet" href="<?= $templatesUrl ?>styles/base.css">
	<link rel="stylesheet" href="<?= $templatesUrl ?>styles/main.css">
	<script src="<?= $templatesUrl ?>scripts/main.js" defer></script>
</head>
<body id="html-body" class="template-<?= $page->template->name ?>">

	<a class="skip-link" href="#main">Skip to content</a>

	<header id="site-header" class="site-header">
		<div class="container site-header-inner">
			<a class="site-brand" href="<?= $home->url ?>"><?= $home->title ?></a>
			<nav id="site-nav" class="site-nav" aria-label="Main">
				<ul role="list">
					<?php foreach($home->and($home->children) as $item): ?>
					<?php
					$isCurrent = $item->id === $page->id;
					$inSection = !$isCurrent && $item->id !== $home->id && $page->parents->has($item);
					?>
					<li><a href="<?= $item->url ?>"<?= $isCurrent ? ' aria-current="page"' : ($inSection ? ' class="is-active"' : '') ?>><?= $item->id === $home->id ? __('Home') : $item->title ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		</div>
	</header>

	<main id="main" class="site-main">
		<div class="container stack">

			<?php if(!$isHome): ?>
			<nav id="breadcrumbs" class="breadcrumbs" aria-label="Breadcrumb">
				<ol role="list">
					<?php foreach($page->parents as $parent): ?>
					<li><a href="<?= $parent->url ?>"><?= $parent->id === $home->id ? __('Home') : $parent->title ?></a></li>
					<?php endforeach; ?>
					<li><span aria-current="page"><?= $page->title ?></span></li>
				</ol>
			</nav>
			<?php endif; ?>

			<header id="page-header" class="page-header">
				<h1 id="headline"><?= $page->title ?></h1>
				<?php if($summary !== ''): ?>
				<p class="page-summary"><?= $summary ?></p>
				<?php endif; ?>
			</header>

			<div id="content">
				<div class="prose"><?= $page->get('body') ?></div>
			</div>

		</div>
	</main>

	<footer id="site-footer" class="site-footer">
		<div class="container site-footer-inner">
			<p>&copy; <?= date('Y') ?> <?= $home->title ?></p>
			<?php if($page->editable()): ?>
			<p><a href="<?= $page->editUrl() ?>">Edit this page</a></p>
			<?php endif; ?>
		</div>
	</footer>

</body>
</html>
