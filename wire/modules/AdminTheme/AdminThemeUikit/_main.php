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
/** @var Process $process */
/** @var Sanitizer $sanitizer */
/** @var WireInput $input */
/** @var Paths $urls */
/** @var string $layout */

if(!defined("PROCESSWIRE")) die();

$adminTheme->renderExtraMarkup('x'); // forces it to cache
if(!isset($content)) $content = '';

?><!DOCTYPE html>
<html class="pw" lang="<?php echo $adminTheme->_('en');
	/* this intentionally on a separate line */ ?>">
<head>
	<?php 
	include($config->paths->adminTemplates . '_head.php');
	echo $adminTheme->renderExtraMarkup('head'); 
	?>
</head>
<body class='<?php echo $adminTheme->getBodyClass(); ?>'>

	<?php
	if($layout == 'sidenav') {
		include(__DIR__ . "/_sidenav-masthead.php");
		
	} else if($layout == 'sidenav-tree' || $layout == 'sidenav-tree-alt') {
		// masthead not rendered in this frame
		echo $adminTheme->renderNotices($notices);
		echo "<div class='uk-margin-small'></div>";
		
	} else if($layout == 'modal') {
		// no masthead
		echo $adminTheme->renderNotices($notices);
		
	} else {
		include(__DIR__ . "/_masthead.php");
	}
	?>

	<!-- MAIN CONTENT -->
	<main id='main' class='pw-container uk-container uk-container-expand uk-margin uk-margin-large-bottom'>
		<div class='pw-content' id='content'>
			
			<header id='pw-content-head'>
				
				<?php if($layout != 'sidenav' && $layout != 'modal') echo $adminTheme->renderBreadcrumbs(); ?>

				<div id='pw-content-head-buttons' class='uk-float-right uk-visible@s'>
					<?php echo $adminTheme->renderAddNewButton(); ?>
				</div>

				<?php 
				$headline = $adminTheme->getHeadline();
				$headlinePos = strpos($content, ">$headline</h1>");
				if(!$adminTheme->isModal && ($headlinePos === false || $headlinePos < 500)) {
					echo "<h1 class='uk-margin-remove-top'>$headline</h1>";
				}
				?>
				
			</header>	
			
			<div id='pw-content-body'>
				<?php
				echo $page->get('body');
				echo $content;
				echo $adminTheme->renderExtraMarkup('content');
				?>
			</div>	
			
		</div>
	</main>

	<?php
	if(!$adminTheme->isModal) {
		include(__DIR__ . '/_footer.php');
		if($adminTheme->isLoggedIn && strpos($layout, 'sidenav') !== 0) include(__DIR__ . '/_offcanvas.php');
	}
	echo $adminTheme->renderExtraMarkup('body');
	?>
	
	<script>
		<?php	
		if(strpos($layout, 'sidenav-tree') === 0) {
			echo "if(typeof parent.isPresent != 'undefined'){";
			if(strpos($process, 'ProcessPageList') === 0) {
				echo "parent.hideTreePane();";
			} else {
				echo "if(!parent.isMobileWidth() && parent.treePaneHidden()) parent.showTreePane();";
			}
			if($process == 'ProcessPageEdit' && ($input->get('s') || $input->get('new'))) {
				echo "parent.refreshTreePane(" . ((int) $input->get('id')) . ");";
			}
			echo "}";
		}
		?>
		ProcessWireAdminTheme.init();
	</script>

</body>
</html>
