<?php namespace ProcessWire;

/**
 * default.php
 * 
 * Main markup template file for AdminThemeDefault
 * 
 * __('FOR TRANSLATORS: please translate the file /wire/templates-admin/default.php rather than this one.');
 * 
 * 
 */

/** @var Config $config */
/** @var AdminThemeDefault $adminTheme */
/** @var User $user */
/** @var Modules $modules */
/** @var Notices $notices */
/** @var Page $page */

if(!defined("PROCESSWIRE")) die();

if(!isset($content)) $content = '';

if($user->hasPermission('page-edit')) {
	/** @var ProcessPageSearch $searchForm */
	$searchForm = $modules->get('ProcessPageSearch');
	$searchForm = $searchForm->renderSearchForm();
} else {
	$searchForm = '';
}

$version = $adminTheme->version . 'k';

$config->styles->prepend($config->urls->root . "wire/templates-admin/styles/AdminTheme.css?v=$version"); 
$config->styles->prepend($config->urls->adminTemplates . "styles/" . ($adminTheme->colors ? "main-$adminTheme->colors" : "main-classic") . ".css?v=$version"); 
$config->styles->append($config->urls->root . "wire/templates-admin/styles/font-awesome/css/font-awesome.min.css?v=$version"); 
	
$ext = $config->debug ? "js" : "min.js";
$config->scripts->append($config->urls->root . "wire/templates-admin/scripts/inputfields.$ext?v=$version");
$config->scripts->append($config->urls->root . "wire/templates-admin/scripts/main.$ext?v=$version"); 
$config->scripts->append($config->urls->adminTemplates . "scripts/main.$ext?v=$version");
	
require_once(dirname(__FILE__) . "/AdminThemeDefaultHelpers.php");
/** @var AdminThemeDefaultHelpers $helpers */
$helpers = $this->wire(new AdminThemeDefaultHelpers());
$extras = $adminTheme->getExtraMarkup();

?><!DOCTYPE html>
<html class="pw" lang="<?php echo $helpers->_('en'); 
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
<body class='<?php echo $helpers->renderBodyClass(); ?>'>

	<?php 
	echo $helpers->renderAdminNotices($notices); 
	echo $extras['notices']; 
	?>

	<div id="masthead" class="pw-masthead ui-helper-clearfix">
		<div class="pw-container container">

			<a id='logo' href='<?php echo $config->urls->admin?>'>
                <img width='144' src="<?php echo $config->urls->adminTemplates?>styles/images/logo.png" alt="ProcessWire" />
			</a>

			<?php 
			if($user->isLoggedin()) {
				echo $searchForm;
				echo "\n\n<ul id='topnav'>" . $helpers->renderTopNavItems() . "</ul>";
			}
			echo $extras['masthead']; 
			?>

		</div>
	</div><!--/#masthead-->

	<div id='breadcrumbs'>
		<div class='pw-container container'>

			<?php 
			if($page->process == 'ProcessPageList' || ($page->name == 'lister' && $page->parent->name == 'page')) {
				echo $helpers->renderAdminShortcuts(); 
			}
			?>

			<ul class='nav'><?php echo $helpers->renderBreadcrumbs(); ?></ul>

		</div>
	</div><!--/#breadcrumbs-->

	<div id="content" class="pw-content content pw-fouc-fix">
		<div class="pw-container container">

			<?php 
			$body = $page->get('body');
			if($body) echo $body;
			unset($body);
			echo $content; 
			echo $extras['content'];
			?>

		</div>
	</div><!--/#content-->

	<div id="footer" class="pw-footer footer">
		<div class="pw-container container">
			<p>
			<?php if($user->isLoggedin()): ?>
			<span id='userinfo'>
				<i class='fa fa-user'></i> 
				<?php 
				if($user->hasPermission('profile-edit')): ?> 
				<i class='fa fa-angle-right'></i> <a class='action' href='<?php echo $config->urls->admin; ?>profile/'><?php echo $user->name; ?></a> <i class='fa fa-angle-right'></i>
				<?php endif; ?>
				<a class='action' href='<?php echo $config->urls->admin; ?>login/logout/'><?php echo $helpers->_('Logout'); ?></a>
			</span>
			ProcessWire <?php echo $config->versionName . ' <!--v' . $config->systemVersion; ?>--> &copy; <?php echo date("Y"); ?>
			<?php endif; ?>
			</p>

			<?php 
			echo $extras['footer'];
			if($config->debug && $user->isSuperuser()) {
				include($config->paths->root . '/wire/templates-admin/debug.inc');
			}
			?>
		</div>
	</div><!--/#footer-->

	<?php echo $extras['body']; ?>

</body>
</html>
