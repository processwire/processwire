<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die(); 

/** @var Paths $urls */
/** @var AdminThemeUikit $adminTheme */

if($adminTheme->isEditor):
	$searchURL = $urls->admin . 'page/search/live/';
	$helpTerm = $adminTheme->getLabel('search-help'); // Localized term to type for help (3+ chars)
	$helpNote = $adminTheme->getLabel('search-tip');
	?>
	<form class='pw-search-form' data-action='<?php echo $searchURL; ?>' action='<?php echo $searchURL; ?>' method='get'>
		<div class='uk-inline'>
			<span class='uk-form-icon'>
				<span class='pw-search-icon'>
					<?php echo $adminTheme->renderIcon('search'); ?>
				</span>
				<span class='pw-spinner-icon uk-hidden'>
					<?php echo $adminTheme->renderIcon('spinner fa-spin'); ?>
				</span>	
			</span>
			<input type='text' class='pw-search-input uk-input uk-form-width-medium' name='q' data-help-term='<?php echo $helpTerm;?>' data-help-note='<?php echo $helpNote;?>'>
		</div>
		<input class='uk-hidden' type='submit' name='search' value='Search' />
		<input type='hidden' name='show_options' value='1' />
	</form>
<?php endif; ?>
