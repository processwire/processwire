<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var AdminThemeUikit $adminTheme */
/** @var Paths $urls */
/** @var Config $config */

?>

<!-- OFFCANVAS NAV TOGGLE -->
<a id='offcanvas-toggle' class='uk-hidden' href='#offcanvas-nav' uk-toggle='target: #offcanvas-nav'>
	<?php echo $adminTheme->renderIcon('bars fa-lg'); ?>
</a>

<!-- OFFCANVAS NAVIGATION -->
<div id="offcanvas-nav" class="uk-offcanvas" uk-offcanvas>
	<div class="uk-offcanvas-bar">
		<p id="offcanvas-nav-header">
			<a id="offcanvas-nav-close" href='#offcanvas-nav' class='uk-text-muted' onclick='return false;' data-uk-toggle>
				<i class='fa fa-times uk-float-right uk-margin-small-top'></i>
			</a>
			<img class='pw-logo' width='200' style='margin-left:-5px' src='<?php echo $adminTheme->url(); ?>uikit-pw/images/logo.png' />
		</p>	
		<?php $adminTheme->includeFile('_search-form.php'); ?>
		<ul class='pw-sidebar-nav uk-nav uk-nav-parent-icon uk-margin-small-top' data-uk-nav='animation: false; multiple: true;'>
			<?php echo $adminTheme->renderSidebarNavItems(); ?>
		</ul>	
	</div>
</div>

