<?php namespace ProcessWire;

/**
 * default.php: Main control file for AdminThemeUikit
 * 
 * FileCompiler=0
 * 
 */

if(!defined("PROCESSWIRE")) die();

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

if($adminTheme->isModal) {
	$layout = 'modal';
} else if($user->isLoggedin() && isset($_GET['layout'])) {
	$layout = $input->get->name('layout');
} else {
	$layout = '';
}

$content .= $adminTheme->renderExtraMarkup('content');
$vars = array('layout' => $layout, 'content' => &$content);

if(strpos($layout, 'sidenav') === 0 && $layout != 'sidenav-main') {
	include(__DIR__ . '/_sidenav/default.php');
} else {
	// main markup file
	if($user->isLoggedin() && $adminTheme->layout && !$adminTheme->isModal) {
		$vars['layout'] = $adminTheme->layout;
		$adminTheme->addBodyClass("pw-layout-$vars[layout]");
	} else if($layout != 'modal') {
		$vars['layout'] = '';
	}
	$adminTheme->includeFile('_main.php', $vars); 
}

