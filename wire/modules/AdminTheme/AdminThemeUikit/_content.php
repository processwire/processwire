<?php namespace ProcessWire;

/**
 * Primary content (#main > #content)
 * 
 */

if(!defined("PROCESSWIRE")) die();

/** @var string $layout */
/** @var AdminThemeUikit $adminTheme */
/** @var string $content */
/** @var string $headline */
/** @var Page $page */

?>

<main id='main' class='pw-container uk-container uk-container-expand uk-margin uk-margin-large-bottom'>
	<div class='pw-content' id='content'>
		<?php 
		$adminTheme->includeFile('_content-head.php', array('layout' => $layout, 'headline' => $headline)); 
		$adminTheme->includeFile('_content-body.php', array('content' => &$content));
		?>
	</div>
</main>

