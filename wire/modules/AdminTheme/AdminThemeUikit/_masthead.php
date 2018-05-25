<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/** @var AdminThemeUikit $adminTheme */
/** @var User $user */
/** @var array $extras */
/** @var Paths $urls */
/** @var Config $config */
/** @var Notices $notices */

?>
<div id='pw-mastheads'>
	<header id='pw-masthead-mobile' class='pw-masthead uk-hidden uk-background-muted'>
		<div class='pw-container uk-container uk-container-expand<?php if(!$adminTheme->isLoggedIn) echo ' uk-text-center'; ?>'>
				<a href='<?php echo $adminTheme->isLoggedIn ? $config->urls->admin : $config->urls->root; ?>' class='pw-logo-link'>
					<?php echo $adminTheme->getLogo(); ?>
				</a>
		</div>	
	</header>
	<header id='pw-masthead' class='pw-masthead uk-background-muted' data-pw-height='73'>
		<div class='pw-container uk-container uk-container-expand'>
			<nav class='uk-navbar uk-navbar-container uk-navbar-transparent' uk-navbar>
				<div class='uk-navbar-left'>
					<a class="pw-logo-link uk-logo uk-margin-right" href='<?php echo $adminTheme->isLoggedIn ? $config->urls->admin : $config->urls->root; ?>'>
						<?php echo $adminTheme->getLogo(); ?>
					</a>
					<?php if($adminTheme->isLoggedIn): ?>
					<ul class='uk-navbar-nav pw-primary-nav'>
						<?php echo $adminTheme->renderPrimaryNavItems(); ?>
					</ul>	
					<?php endif; ?>
				</div>
				<?php if($adminTheme->isLoggedIn): ?>
				<div class="uk-navbar-right">
					<ul class='uk-navbar-nav uk-margin-right pw-user-nav'>
						<li>
							<a id="tools-toggle" class="pw-dropdown-toggle" href="<?php echo $urls->admin; ?>profile/">
								<?php echo $adminTheme->renderUserNavLabel(); ?>
							</a>
							<ul class="pw-dropdown-menu" data-my="left top" data-at="left bottom" style="display: none;">
								<?php if($config->debug && $adminTheme->isSuperuser && strpos($adminTheme->layout, 'sidenav') === false): ?>
								<li>	
									<a href='#' onclick="$('#debug_toggle').click(); return false;">
										<?php echo $adminTheme->renderNavIcon('bug') . __('Debug', __FILE__); ?>
									</a>
								</li>
								<?php  endif; ?>
								<?php echo $adminTheme->renderUserNavItems(); ?>
							</ul>
						</li>
					</ul>	
				
					<?php include(__DIR__ . '/_search-form.php'); ?>
					
				</div>
				<?php endif; // loggedin ?>
			</nav>
		</div>
	</header>
	<?php 
	if(strpos($adminTheme->layout, 'sidenav') === false) {
		echo $adminTheme->renderNotices($notices);
		echo $adminTheme->renderExtraMarkup('masthead');
	}
	?>
</div>	

