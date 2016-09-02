<?php namespace ProcessWire;

/**
 * ProcessWire 2.x Admin Markup Template
 *
 * Copyright 2012 by Ryan Cramer
 *
 *
 */

$searchForm = $user->hasPermission('page-edit') ? $modules->get('ProcessPageSearch')->renderSearchForm() : '';
$bodyClass = $input->get->modal ? 'modal' : '';
if(!isset($content)) $content = '';

$config->styles->prepend($config->urls->adminTemplates . "styles/main.css?v=4"); 
$config->styles->append($config->urls->adminTemplates . "styles/inputfields.css?v=4"); 
$config->styles->append($config->urls->adminTemplates . "styles/ui.css?v=4"); 
$config->styles->append($config->urls->root . "wire/templates-admin/styles/font-awesome/css/font-awesome.min.css");
$config->scripts->append($config->urls->adminTemplates . "scripts/inputfields.js?v=5"); 
$config->scripts->append($config->urls->adminTemplates . "scripts/main.js?v=4"); 

$browserTitle = $this->wire('processBrowserTitle'); 
if(!$browserTitle) $browserTitle = __(strip_tags($page->get('title|name')), __FILE__) . ' &bull; ProcessWire';

/*
 * Dynamic phrases that we want to be automatically translated
 *
 * These are in a comment so that they register with the parser, in place of the dynamic __() function calls with page titles. 
 * 
 * __("Pages"); 
 * __("Tree"); 
 * __("Find"); 
 * __("Setup"); 
 * __("Modules"); 
 * __("Access"); 
 * __("Admin"); 
 * __("Site"); 
 * __("View Site"); 
 * __("Languages"); 
 * __("Users"); 
 * __("Roles"); 
 * __("Permissions"); 
 * __("Templates"); 
 * __("Fields"); 
 * __("Add New"); 
 * __("Recent"); 
 * __("Logs");
 * __("Edit");
 * __("Bookmarks");
 * __("Page Edit");
 * __("Page List");
 * 
 */

?>
<!DOCTYPE html>
<html lang="<?php echo __('en', __FILE__); // HTML tag lang attribute
	/* this intentionally on a separate line */ ?>"> 
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title><?php echo $browserTitle; ?></title>

	<script type="text/javascript">
		<?php

		$jsConfig = $config->js();
		$jsConfig['debug'] = $config->debug;
		$jsConfig['urls'] = array(
			'root' => $config->urls->root, 
			'admin' => $config->urls->admin, 
			'modules' => $config->urls->modules, 
			'core' => $config->urls->core, 
			'files' => $config->urls->files, 
			'templates' => $config->urls->templates,
			'adminTemplates' => $config->urls->adminTemplates,
			); 
		?>

		var config = <?php echo json_encode($jsConfig); ?>;
	</script>

	<?php foreach($config->styles->unique() as $file) echo "\n\t<link type='text/css' href='$file' rel='stylesheet' />"; ?>


	<!--[if IE]>
	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->adminTemplates; ?>styles/ie.css" />
	<![endif]-->	

	<!--[if lt IE 8]>
	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->adminTemplates; ?>styles/ie7.css" />
	<![endif]-->

	<?php foreach($config->scripts->unique() as $file) echo "\n\t<script type='text/javascript' src='$file'></script>"; ?>

</head>
<body<?php if($bodyClass) echo " class='$bodyClass'"; ?>>
	<p id='bgtitle'><?php echo __($page->parent->title, __FILE__); ?></p>
	<div id="masthead" class="masthead">
		<div class="container">
			<a id='logo' href='<?php echo $config->urls->admin?>'><img src="<?php echo $config->urls->adminTemplates?>styles/images/logo.gif" alt="ProcessWire" /></a>

			<ul id='topnav' class='nav'><?php include($config->paths->adminTemplates . "topnav.inc"); ?>

			</ul>

			<?php if(!$user->isGuest()): ?>

			<ul id='breadcrumb' class='nav'><?php
				foreach($this->wire('breadcrumbs') as $breadcrumb) {
					$title = __($breadcrumb->title, __FILE__); 
					echo "\n\t\t\t\t<li><a href='{$breadcrumb->url}'>{$title}</a> &gt;</li>";
				}
				?>

			</ul>

			<?php endif; ?>	
			<h1 id='title'><?php echo __(strip_tags($this->fuel->processHeadline ? $this->fuel->processHeadline : $page->get("title|name")), __FILE__); ?></h1>

			<?php echo tabIndent($searchForm, 3); ?>

		</div>
	</div>

	<?php if(count($notices)) include($config->paths->adminTemplates . "notices.inc"); ?>

	<div id="content" class="content fouc_fix">
		<div class="container">

			<?php if(trim($page->summary)) echo "<h2>{$page->summary}</h2>"; ?>

			<?php if($page->body) echo $page->body; ?>

			<?php echo $content?>

		</div>
	</div>


	<div id="footer" class="footer">
		<div class="container">
			<p>
			<?php if(!$user->isGuest()): ?>

			<span id='userinfo'>
				<?php 
				echo $user->name;
				if($user->hasPermission('profile-edit')): ?> / 
				<a class='action' href='<?php echo $config->urls->admin; ?>profile/'><?php echo __('Profile', __FILE__); ?></a> /
				<?php endif; ?>

				<a class='action' href='<?php echo $config->urls->admin; ?>login/logout/'><?php echo __('Logout', __FILE__); ?></a>
			</span>

			<?php endif; ?>

			ProcessWire <?php echo $config->version . ' <!--v' . $config->systemVersion; ?>--> &copy; <?php echo date("Y"); ?> Ryan Cramer 
			</p>

			<?php if($config->debug && $this->user->isSuperuser()) include($config->paths->adminTemplates . "debug.inc"); ?>
		</div>
	</div>

	<a id='sitelink' href='<?php echo $config->urls->root; ?>'><?php echo __('Site', __FILE__); ?></a>

</body>
</html>
