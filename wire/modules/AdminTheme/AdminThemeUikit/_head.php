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

$version = $adminTheme->version . 'e';

$config->styles->prepend($config->urls->root . "wire/templates-admin/styles/AdminTheme.css?v=$version");
$config->styles->prepend($adminTheme->getUikitCSS());
$config->styles->append($config->urls->root . "wire/templates-admin/styles/font-awesome/css/font-awesome.min.css?v=$version");

$ext = $config->debug ? "js" : "min.js";
$config->scripts->append($config->urls->root . "wire/templates-admin/scripts/inputfields.$ext?v=$version");
$config->scripts->append($config->urls->root . "wire/templates-admin/scripts/main.$ext?v=$version");
$config->scripts->append($config->urls->adminTemplates . "uikit/dist/js/uikit.min.js?v=$version");
$config->scripts->append($config->urls->adminTemplates . "uikit/dist/js/uikit-icons.min.js?v=$version");
$config->scripts->append($config->urls->adminTemplates . "scripts/main.js?v=$version");

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
	foreach($config->styles as $file) {
		echo "\n\t<link type='text/css' href='$file' rel='stylesheet' />";
	}
	if($adminTheme->maxWidth && strpos($layout, 'sidenav') === false) {
		echo "\n\t<style type='text/css'>.pw-container { max-width: {$adminTheme->maxWidth}px; }</style>";
	}
	foreach($config->scripts as $file) {
		echo "\n\t<script type='text/javascript' src='$file'></script>";
	}
	?>
