<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var AdminThemeUikit $adminTheme */
/** @var User $user */
/** @var array $extras */
/** @var Paths $urls */
/** @var Config $config */
/** @var Notices $notices */

$breadcrumbs = $adminTheme->renderBreadcrumbs();
?>
<script>
	function toggleSidebar() {
		if(parent.toggleSidebarPane != "undefined") {
			parent.toggleSidebarPane();
			return false;
		} else {
			return true;
		}
	}
	function toggleTree() {
		if(parent.toggleTreePane != "undefined") {
			parent.toggleTreePane();
			return false;
		} else {
			return true;
		}
	}
</script>

<header id='pw-masthead' class='uk-background-muted'>
	<div class='pw-container uk-container uk-container-expand'>
		<nav class="uk-navbar-container uk-navbar-transparent" uk-navbar>
			<div class="uk-navbar-left">
				<a onclick='return toggleSidebar()' class='pw-logo-link' href="<?php echo $urls->admin; ?>">
					<img class='pw-logo' src='<?php echo $adminTheme->getLogoURL(); ?>' alt='ProcessWire'>
				</a>
				<?php echo str_replace("uk-breadcrumb", "uk-breadcrumb uk-visible@m", $breadcrumbs); ?>	
			</div>
			<?php if($adminTheme->isLoggedIn): ?>
			<div class="uk-navbar-right">
				<ul class='uk-navbar-nav uk-margin-right'>
					<li>
						<a id="tools-toggle" class="pw-dropdown-toggle" href="<?php echo $urls->admin; ?>profile/">
							<?php echo $adminTheme->renderNavIcon('user') . $user->name; ?>
						</a>
						<ul class="pw-dropdown-menu" data-my="left top" data-at="left bottom" style="display: none;">
							<?php if($config->debug) { ?>
							<li>	
								<a href='#' onclick="$('#debug_toggle').click(); return false;">
									<?php echo $adminTheme->renderNavIcon('bug') . __('Debug'); ?>
								</a>
							</li>
							<?php } ?>
							<?php echo $adminTheme->renderUserNavItems(); ?>
							<li>	
								<a href='#' onclick="return toggleSidebar();">
									<?php echo $adminTheme->renderNavIcon('bars') . __('Navigation sidebar'); ?>
								</a>
							</li>
							<li>	
								<a href='#' onclick="return toggleTree();">
									<?php echo $adminTheme->renderNavIcon('sitemap') . __('Page tree sidebar'); ?>
								</a>
							</li>
						</ul>
					</li>
				</ul>

				<?php $adminTheme->includeFile('_search-form.php'); ?> 
				
			</div>
			<?php endif; // loggedin ?>
		</nav>
	</div>
	<?php echo $adminTheme->renderExtraMarkup('masthead'); ?>
</header>
<?php echo $adminTheme->renderNotices($notices); ?>
<div class='pw-container uk-container uk-container-expand uk-hidden@m uk-margin-top'>
	<?php echo $breadcrumbs; ?>
</div>	
