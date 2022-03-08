<?php namespace ProcessWire;

/**
 * Primary content head (#main > #content > #pw-content-head)
 *
 */

if(!defined("PROCESSWIRE")) die();

/** @var string $layout */
/** @var AdminThemeUikit $adminTheme */
/** @var string $headline */
/** @var Page $page */

?>

<header id='pw-content-head'>
			
	<?php if($layout != 'sidenav' && $layout != 'modal') echo $adminTheme->renderBreadcrumbs(); ?>
	
	<div id='pw-content-head-buttons' class='uk-float-right uk-visible@s'>
		<?php echo $adminTheme->renderAddNewButton(); ?>
	</div>

	<?php
	if($headline !== '' && !$adminTheme->isModal) {
		echo "<h1 id='pw-content-title' class='uk-margin-remove-top'>$headline</h1>";
	}
	?>
			
</header>	
		
