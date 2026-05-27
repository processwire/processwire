<?php namespace ProcessWire;

/**
 * _main.php: Main markup template file for AdminThemeUikit
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
/** @var string $layout */
/** @var Process $process */
	
if($adminTheme->themeName && $adminTheme->themeName != 'original') {
	$themeInfo = $adminTheme->getThemeInfo();
	if(!empty($themeInfo)) include("$themeInfo[path]ready.php");
}

$adminTheme->renderExtraMarkup('x'); // forces it to cache
if(!isset($content)) $content = '';

?><!DOCTYPE html>
<html class="pw" lang="<?php echo $adminTheme->_('en');
	/* this intentionally on a separate line */ ?>">
<head>
	<?php 
	$adminTheme->includeFile('_head.php', array('layout' => $layout));
	echo $adminTheme->renderExtraMarkup('head'); 
	?>
</head>
<body class='<?php echo $adminTheme->getBodyClass(); ?>'>

	<?php
	if($layout == 'sidenav') {
		$adminTheme->includeFile('_sidenav-masthead.php');
		
	} else if($layout == 'sidenav-tree' || $layout == 'sidenav-tree-alt') {
		// masthead not rendered in this frame
		echo $adminTheme->renderNotices($notices);
		echo "<div class='uk-margin-small'></div>";
		
	} else if($layout == 'modal') {
		// no masthead
		echo $adminTheme->renderNotices($notices);
		
	} else {
		$adminTheme->includeFile('_masthead.php');
	}
	
	$headline = $adminTheme->getHeadline();
	$headlinePos = strpos($content, "$headline</h1>");
	if($headlinePos && $headlinePos > 500) $headline = '';
	
	$adminTheme->includeFile('_content.php', array(
		'headline' => $headline, 
		'content' => &$content, 
		'layout' => $layout
	));
	
	if(!$adminTheme->isModal) {
		$adminTheme->includeFile('_footer.php');
		if($adminTheme->isLoggedIn && strpos($layout, 'sidenav') !== 0) {
			$adminTheme->includeFile('_offcanvas.php');
		}
	}
	
	echo $adminTheme->renderExtraMarkup('body');
	$adminTheme->includeFile('_body-scripts.php', array('layout' => $layout));
	?>
	
</body>
</html><?php
