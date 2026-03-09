<?php namespace ProcessWire;

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

$version = $config->version;
$rootUrl = $config->urls->root;
$themeUrl = $adminTheme->url();
$styles = $config->styles;
$scripts = $config->scripts;

$styles->prepend($rootUrl . "wire/templates-admin/styles/AdminTheme.css?v=$version");
$styles->prepend($adminTheme->getUikitCSS());
$styles->append($rootUrl . "wire/templates-admin/styles/font-awesome/css/font-awesome.min.css?v=$version");

$ext = $config->debug ? "js" : "min.js";
$scripts->append($rootUrl . "wire/templates-admin/scripts/inputfields.$ext?v=$version");
$scripts->append($rootUrl . "wire/templates-admin/scripts/main.$ext?v=$version");
$scripts->append($themeUrl . "uikit/dist/js/uikit.min.js?v=$version");
$scripts->append($themeUrl . "uikit/dist/js/uikit-icons.min.js?v=$version");
$scripts->append($themeUrl . "scripts/main.$ext?v=$version");

?>

	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="google" content="notranslate" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<title><?php echo $adminTheme->getBrowserTitle(); ?></title>

	<script>
		<?php echo $adminTheme->getHeadJS(); ?>
	</script>

	<?php
	foreach($styles->urls() as $file) {
		echo "\n\t<link type='text/css' href='$file' rel='stylesheet' />";
	}
	if($adminTheme->maxWidth && strpos($layout, 'sidenav') === false) {
		echo "\n\t<style type='text/css'>.pw-container { max-width: {$adminTheme->maxWidth}px; }</style>";
	}
	foreach($scripts->urls() as $file) {
		echo "\n\t<script type='text/javascript' src='$file'></script>";
	}
