<?php namespace ProcessWire;

/**
 * Default.php
 * 
 * Main markup file for AdminThemeReno
 * Copyright (C) 2015 by Tom Reno (Renobird)
 * http://www.tomrenodesign.com
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 */

/** @var Config $config */
/** @var AdminThemeReno $adminTheme */
/** @var User $user */
/** @var Modules $modules */

if(!defined("PROCESSWIRE")) die();

if(!isset($content)) $content = '';
$version = $adminTheme->version . 'l';
$ext = $config->debug ? "js" : "min.js";

// Search form
$searchForm = $user->hasPermission('page-edit') ? $modules->get('ProcessPageSearch')->renderSearchForm() : '';

// Admin Theme colors
$adminTheme->colors = $adminTheme->colors ? $adminTheme->colors : "main";
$defaultColors = "styles/" . $adminTheme->colors . ".css"; 
$customColors = "AdminTheme/$adminTheme/styles/" . $adminTheme->colors . ".css";
$colorFile = file_exists($config->paths->adminTemplates . $defaultColors) ? $config->urls->adminTemplates . $defaultColors : $config->urls->siteModules . $customColors;

// Styles
$config->styles->prepend($colorFile . "?v=" . $version);
$config->styles->prepend($config->urls->root . "wire/templates-admin/styles/AdminTheme.css?v=$version");
$config->styles->append($config->urls->root . "wire/templates-admin/styles/font-awesome/css/font-awesome.min.css?v=$version");

// Scripts
$config->scripts->append($config->urls->root . "wire/templates-admin/scripts/inputfields.$ext?v=$version");
$config->scripts->append($config->urls->root . "wire/templates-admin/scripts/main.$ext?v=$version");
$config->scripts->append($config->urls->adminTemplates . "scripts/main.$ext?v=$version");

require_once(dirname(__FILE__) . "/AdminThemeRenoHelpers.php");
$helpers = $this->wire(new AdminThemeRenoHelpers());
$extras = $adminTheme->getExtraMarkup();

?>
<!DOCTYPE html>
<html class="pw <?php echo $helpers->renderBodyClass(); ?>" lang="<?php echo $helpers->_('en'); 
	/* this intentionally on a separate line */ ?>">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="google" content="notranslate" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title><?php echo $helpers->renderBrowserTitle(); ?></title>

	<script type="text/javascript"><?php echo $helpers->renderJSConfig(); ?></script>
	<?php foreach($config->styles as $file) echo "\n\t<link type='text/css' href='$file' rel='stylesheet' />"; ?>
	<?php foreach($config->scripts as $file) echo "\n\t<script type='text/javascript' src='$file'></script>"; ?>
	<?php echo $extras['head']; ?>

</head>

<body class="<?php echo $helpers->renderBodyClass(); ?>">
	
	<div id="wrap">
		
		<div id='branding'>
			<a id="logo" href="<?php echo $config->urls->admin?>">
				<img src="<?php echo $config->urls->adminTemplates?>styles/images/logo.png" alt="ProcessWire" />
				<img src="<?php echo $config->urls->adminTemplates?>styles/images/logo-sm.png" class='sm' alt="ProcessWire" />
			</a>
		</div>

		<a href="#" class='main-nav-toggle'><i class="fa fa-bars"></i></a>
		
		<div id="masthead" class="pw-masthead masthead ui-helper-clearfix">
			
			<?php echo $extras['masthead']; ?>

			<ul id="topnav">
				<?php echo $helpers->renderTopNav(); ?>
			</ul>

		</div>

		<div id="search"><?php echo tabIndent($searchForm, 3);?> <a href='#' class='search-close'><i class="fa fa-times"></i></a></div>

		<div id="sidebar" class="mobile">
			<ul id="main-nav">
				<?php echo $helpers->renderSideNavItems($page); ?>
			</ul>
			<?php echo $extras['sidebar']; ?>
		</div>

		<div id="main">
			
			<?php 
			echo $helpers->renderAdminNotices($notices);
			echo $extras['notices'];
			?>
		
			<div id="breadcrumbs">
				<ul class="nav"><?php echo $helpers->renderBreadcrumbs(false); ?></ul>
			</div>

			<div id="headline">
				<?php if(in_array($page->id, array(2,3,8))) echo $helpers->renderAdminShortcuts(); /* 2,3,8=page-list admin page IDs */ ?>
				<h1 id="title"><?php echo $helpers->getHeadline(); ?></h1>
			</div>

			<div id="content" class="pw-content content pw-fouc-fix">

				<?php
				if($page->body) echo $page->body;
				echo $content;
				echo $extras['content'];
				?>

			</div>

			<div id="footer" class="pw-footer footer">
				<p>
					<?php if(!$user->isGuest()): ?>
						<span id="userinfo">
						<?php if($user->hasPermission('profile-edit')): ?> 
							<a class="action" href="<?php echo $config->urls->admin; ?>profile/"><i class="fa <?php echo $adminTheme->profile;?>"></i> <?php echo $helpers->_('Profile'); ?></a> 
						<?php endif; ?>
							<a class="action" href="<?php echo $config->urls->admin; ?>login/logout/"><i class="fa <?php echo $adminTheme->signout;?>"></i> <?php echo $helpers->_('Logout'); ?></a>
						</span>
						ProcessWire <?php echo $config->versionName . ' <!--v' . $config->systemVersion; ?>--> &copy; <?php echo date("Y"); ?>
					<?php endif; ?>
				</p>
				
				<?php
				echo $extras['footer'];
				if($config->debug && $user->isSuperuser()) include($config->paths->root . "wire/templates-admin/debug.inc"); 
				?>

			</div><!--/#footer-->
		</div> <!-- /#main -->
	</div> <!-- /#wrap -->
	<?php echo $extras['body']; ?>
</body>
</html>
