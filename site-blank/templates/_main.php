<?php namespace ProcessWire;

// Optional main output file, called after rendering page’s template file. 
// This is defined by $config->appendTemplateFile in /site/config.php, and
// is typically used to define and output markup common among most pages.
// 	
// When the Markup Regions feature is used, template files can prepend, append,
// replace or delete any element defined here that has an "id" attribute. 
// https://processwire.com/docs/front-end/output/markup-regions/
	
/** @var Page $page */
/** @var Pages $pages */
/** @var Config $config */

$home = $pages->get('/'); // homepage

?><!DOCTYPE html>
<html lang="en">
	<head id="html-head">
		<meta http-equiv="content-type" content="text/html; charset=utf-8" />
		<title><?php echo $page->title; ?></title>
		<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates; ?>styles/main.css" />
		<script src="<?php echo $config->urls->templates; ?>scripts/main.js"></script>
	</head>
	<body id="html-body">

		<p id="topnav">
			<?php echo $home->and($home->children)->implode(" / ", "<a href='{url}'>{title}</a>"); ?>
		</p>
		
		<hr />
		
		<h1 id="headline">
			<?php if($page->parents->count()): // breadcrumbs ?>
				<?php echo $page->parents->implode(" &gt; ", "<a href='{url}'>{title}</a>"); ?> &gt;
			<?php endif; ?>
			<?php echo $page->title; // headline ?>
		</h1>
		
		<div id="content">
			Default content
		</div>
	
		<?php if($page->hasChildren): ?>
		<ul> 
			<?php echo $page->children->each("<li><a href='{url}'>{title}</a></li>"); // subnav ?>
		</ul>	
		<?php endif; ?>
		
		<?php if($page->editable()): ?>
		<p><a href='<?php echo $page->editUrl(); ?>'>Edit this page</a></p>
		<?php endif; ?>
	
	</body>
</html>
