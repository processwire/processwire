<?php namespace ProcessWire;

/**
 * _main.php: Main markup template file for AdminThemeUikit
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
/** @var Process $proc;ess */
/** @var Sanitizer $sanitizer */
/** @var WireInput $input */
/** @var Paths $urls */
/** @var string $layout */
/** @var Process $process */

if(!defined("PROCESSWIRE")) die();
$adminTheme->vars = get_defined_vars();

$adminTheme->renderExtraMarkup('x'); // forces it to cache
if(!isset($content)) $content = '';

?><!DOCTYPE html>
<html class="pw" lang="<?php echo $adminTheme->_('en');
	/* this intentionally on a separate line */ ?>">
<head>
	<?php
	echo $adminTheme->render('_head.php');
	echo $adminTheme->renderExtraMarkup('head');
	?>
</head>
<body class='<?php echo $adminTheme->getBodyClass(); ?>'>

	<?php
	if($layout == 'sidenav') {
		echo $adminTheme->render("_sidenav-masthead.php");
	} else if($layout == 'sidenav-tree' || $layout == 'sidenav-tree-alt') {
		// masthead not rendered in this frame
		echo $adminTheme->renderNotices($notices);
		echo "<div class='uk-margin-small'></div>";
	} else if($layout == 'modal') {
		// no masthead
		echo $adminTheme->renderNotices($notices);
	} else {
		echo $adminTheme->render("_masthead.php");
	}
	
	// main content
	echo $adminTheme->render("_maincontent.php");

	if(!$adminTheme->isModal) {
		echo $adminTheme->render('_footer.php');
		if($adminTheme->isLoggedIn && strpos($layout, 'sidenav') !== 0) {
			echo $adminTheme->render('_offcanvas.php');
		}
	}
	echo $adminTheme->renderExtraMarkup('body');

	echo $adminTheme->render('_bodyscripts.php');

	?>
</body>
</html>
