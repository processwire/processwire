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
/** @var string $content */
/** @var string $layout */

if(!defined("PROCESSWIRE")) die();

if($layout === 'sidenav-init' || $layout === 'sidenav-tree-init') {
	// sidenav main loader
	include(__DIR__ . '/_sidenav-init.php');

} else if($layout === 'sidenav-side') {
	// sidenav sidebar pane
	$adminTheme->addBodyClass("pw-layout-sidenav-side");
	include(__DIR__ . '/_sidenav-side.php'); 

} else if($layout === 'sidenav-tree') {
	// sidenav tree pane
	$adminTheme->addBodyClass("pw-layout-sidenav-tree");
	include(__DIR__ . '/_sidenav-tree.php'); 
}
	
