<?php namespace ProcessWire;

/**
 * Primary content head (#main > #content > #pw-content-head)
 *
 */

if(!defined("PROCESSWIRE")) die();

/** @var AdminThemeUikit $adminTheme */
/** @var string $content */
/** @var Page $page */

?>

<div id='pw-content-body'>
	<?php echo $page->get('body') . $content; ?>
</div>	
	
