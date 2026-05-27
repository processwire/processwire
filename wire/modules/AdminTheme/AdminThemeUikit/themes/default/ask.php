<?php namespace ProcessWire;

/***
 * Runs after an upgrade and asks superuser if they want to use the Konkat Default theme
 * 
 */

if(!defined('PROCESSWIRE')) die();

/** @var Modules $modules */
/** @var Config $config */
/** @var WireInput $input */
/** @var AdminThemeUikit $adminTheme */
/** @var string $themeName */

$moduleEditUrl = $modules->getModuleEditUrl($adminTheme); 
$sanitizer = wire()->sanitizer;
$use = $input->get('use_konkat_default');
$script = 'script';

if($themeName === 'default') {
	// already using Konkat default
	$modules->saveConfig($adminTheme, 'askTheme', 0);
	
} else if($input->get('name') === 'AdminThemeUikit' && $use) {
	// module edit screen
	if($use === 'dismiss') {
		$modules->saveConfig($adminTheme, 'askTheme', 0); 
	} else if($use === 'yes') {
		$adminTheme->themeName = 'default';
		$modules->saveConfig($adminTheme, 'themeName', 'default');
		$modules->saveConfig($adminTheme, 'askTheme', 0);
		$adminTheme->message(__('Updated theme to Konkat Default'), Notice::noGroup); 
		$adminTheme->addExtraMarkup('body', "<$script>$(function() { Inputfields.find('#wrap_themeName') });</$script>");
	}
	
} else {
	
	$btn1 = $modules->get('InputfieldButton');
	/** @var InputfieldButton $btn1 */
	$btn1->attr('id+name', 'ask_theme_yes');
	$btn1->attr('href', $moduleEditUrl . '&use_konkat_default=yes');
	$btn1->value = __('Try it now');
	
	$btn2 = $modules->get('InputfieldButton');
	/** @var InputfieldButton $btn2 */
	$btn2->attr('id+name', 'ask_theme_no');
	$btn2->attr('href', $moduleEditUrl . '&use_konkat_default=dismiss');
	$btn2->setSecondary();
	$btn2->value = __('Dismiss');
	
	$alert =
		$sanitizer->entities1(__('If you want to try it later, go to select the Uikit style theme at:')) . '<br>' .
		'<a href="' . $sanitizer->entities($moduleEditUrl) . '">' .
		$sanitizer->entities1(__('Modules > Configure > AdminThemeUikit')) .
		'</a>';
	
	$script = "
	  <$script>
		$('#ask_theme_no').on('click', function() {
			$(this).closest('.uk-alert').slideUp();
			$.get($(this).parent().attr('href'), { use_konkat_default: 'dismiss' }, function() {}); 
			ProcessWire.alert({ message: '$alert', allowMarkup: true });
			return false;
		});
	  </$script>
	 ";
	
	$adminTheme->message(
		"<strong>" .
			$sanitizer->entities1($this->_('Welcome to ProcessWire')) . ' ' .
			$config->versionName .
		'</strong>' .
		'<br>' .
		'<p>' .
			sprintf(
				$sanitizer->entities1(
					$this->_('This version of ProcessWire comes with an optional new look and feel for the Uikit admin theme designed by %s.')
				), "<a href='https://konkat.studio'>KONKAT Studio</a>"
			) . ' ' .
			$this->_('It features a modern design with light and dark modes, a fixed primary navigation bar, improved search bar, customizable main colors, and more.') .
		'</p>' .
		'<p>' .
			$btn1->render() . ' &nbsp; ' .
			$btn2->render() .
		'</p>' .
		'<p class="detail uk-margin-remove-bottom">' . 
			$sanitizer->entities1(__('You can change this setting any time at: Modules > Configure > AdminThemeUikit')) . 
		'</p>' . 
		$script,
		Notice::noGroup | Notice::markup
	);
}
