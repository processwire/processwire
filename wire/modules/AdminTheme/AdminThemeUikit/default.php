<?php namespace ProcessWire;

/**
 * default.php: Main control file for AdminThemeUikit
 * 
 * FileCompiler=0
 * 
 */

/** @var Config $config */
/** @var AdminThemeUikit $adminTheme */
/** @var User $user */
/** @var Modules $modules */
/** @var Notices $notices */
/** @var Page $page */
/** @var Process $process */
/** @var Sanitizer $sanitizer */
/** @var WireInput $input */
/** @var Paths $urls */

if(!defined("PROCESSWIRE")) die();

if($adminTheme->isModal) {
	$layout = 'modal';
} else if($user->isLoggedin() && isset($_GET['layout'])) {
	$layout = $input->get->name('layout');
} else {
	$layout = '';
}

if($layout === 'sidenav-init' || $layout === 'sidenav-tree-init') {
	// sidenav main loader
	include($config->paths->adminTemplates . "_sidenav-init.php");
	
} else if($layout === 'sidenav-side') {
	// sidenav sidebar pane
	$adminTheme->addBodyClass("pw-layout-sidenav-side");
	include($config->paths->adminTemplates . "_sidenav-side.php");

} else if($layout === 'sidenav-tree') {
	// sidenav tree pane
	$adminTheme->addBodyClass("pw-layout-sidenav-tree");
	include($config->paths->adminTemplates . "_sidenav-tree.php");
	
} else {
	// main markup file
	if($user->isLoggedin() && $adminTheme->layout && !$adminTheme->isModal) {
		$layout = $adminTheme->layout;
		$adminTheme->addBodyClass("pw-layout-$layout");
	} else if($layout != 'modal') {
		$layout = '';
	}
	include($config->paths->adminTemplates . "_main.php");
}

