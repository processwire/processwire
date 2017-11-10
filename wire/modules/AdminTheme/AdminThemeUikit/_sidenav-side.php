<?php namespace ProcessWire;

/**
 * _sidenav-side.php
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

?><!DOCTYPE html>
<html class="pw pw-sidebar-frame" lang="<?php echo $adminTheme->_('en');
	/* this intentionally on a separate line */ ?>">
<head>
	<?php include($config->paths->adminTemplates . '_head.php'); ?>
	<style type='text/css'>
		#pw-sidenav-bar .pw-search-form .uk-inline, 
		#pw-sidenav-bar .pw-search-input {
			width: 100%;
		}
		html, body {
			height: 100%;
		}
	</style>
	<script>
		$(document).on('mouseover', 'a', ProcessWireAdminTheme.linkTargetMainMouseoverEvent);
	</script>
</head>
<body class='<?php echo $adminTheme->getBodyClass(); ?> uk-background-secondary pw-iframe'>

	<?php if($adminTheme->isLoggedIn): ?>
		<div id='pw-sidenav-bar' class="uk-background-secondary uk-padding-small">
			<ul class='pw-sidebar-nav uk-nav uk-nav-parent-icon' data-uk-nav='animation: false; multiple: true;'>
				<?php echo $adminTheme->renderSidebarNavItems(); ?>
			</ul>	
		</div>	

		<script>ProcessWireAdminTheme.init();</script>
	<?php endif; ?>

</body>
</html>
