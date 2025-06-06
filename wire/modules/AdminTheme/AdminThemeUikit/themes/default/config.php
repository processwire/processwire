<?php namespace ProcessWire;

/** @var AdminThemeUikit $adminTheme */
/** @var InputfieldWrapper $inputfields  */
/** @var Config $config */

$settings = $config->AdminThemeUikit;
if(!is_array($settings)) $settings = [];

if($adminTheme->themeName === 'default') {
	$adminTheme->wire()->config->scripts->add($adminTheme->url() . 'themes/default/config.js');
}

$inputfields->label = __('Default theme settings');
$inputfields->description = __('This default theme is created by Diogo Oliveira and Jan Ploch at [KONKAT Studio](https://konkat.studio/).');
$inputfields->icon = 'sliders';

$f = $inputfields->InputfieldRadios;
$f->attr('id+name', 'defaultStyleName');
$f->label = __('Would you like to default to light or dark mode?');
$darkAttr = [];
$value = $adminTheme->get('defaultStyleName');
if(empty($value)) $value = 'auto';
if(!empty($settings['noDarkMode'])) {
	$darkAttr = [ 'disabled' => 'disabled' ]; 
	$value = 'light';
	$f->notes = __('Dark mode has been disabled by `$config->AdminThemeUikit("noDarkMode")`'); 
} else {
	$f->notes = __('Individual users can also choose light/dark/auto mode from the user tools menu.');
}
$f->description = 
	__('This setting is used for users that have not specifically chosen light or dark mode.') . ' ' . 
	__('When “Auto” is selected, the mode will be determined from the user’s browser or OS setting.'); 
$f->addOption('light', __('Light'));
$f->addOption('dark', __('Dark'), $darkAttr);
$f->addOption('auto', __('Auto') . ' ' . 
	'[span.detail] ' . __('(use browser/OS setting)') . ' [/span]', $darkAttr);
$f->optionColumns = 1;
$f->val($value);
$inputfields->add($f);

$f = $inputfields->InputfieldCheckboxes;
$f->attr('id+name', 'defaultToggles');
$f->label = __('Toggles');
$f->addOption('noUserMenu',
	__('Disable light/dark/auto setting in user tools menu?') . ' ' .
	'[span.detail] ' . __('(this prevents users from making their own dark/light mode selection)') . ' [/span]'
);
$togcbxAttr = [];
if(!empty($settings['noTogcbx'])) $togcbxAttr = [ 'disabled' => 'disabled' ];
$f->addOption('useTogcbx', 
	__('Use toggle style checkboxes globally?') . ' ' .
	'[span.detail] ' . __('(use toggle rather than marker style checkboxes)') . ' [/span]',
	$togcbxAttr
);
$f->addOption('use2Colors',
	__('Define separate main color pickers for light mode and dark mode') . ' ' .
	'[span.detail] ' . __('(use for more contrast in light or dark mode)') . ' [/span]',
	[ 'hidden' => 'hidden' ]
);
$value = $adminTheme->get($f->name);
if(is_array($value)) {
	$f->val($value);
	if(in_array('togcbx', $value)) $f->addClass('pw-togcbx', 'wrapClass');
}
$inputfields->add($f);

$f = $inputfields->InputfieldRadios;
$f->attr('id+name', 'defaultMainColor'); 
$f->label = __('Main color'); 
$span = "<span class='defaultMainColorLabel' style='color:#fff;padding:1px 5px 2px 5px;border-radius:4px;background:%s;'>%s</span>";
$f->addOption('red', sprintf($span, '#eb1d61', __('Red')));
$f->addOption('green', sprintf($span, '#14ae85', __('Green')));
$f->addOption('blue', sprintf($span, '#2380e6', __('Blue')));
$f->addOption('custom', __('Custom color pickers…'));
$f->optionColumns = 1; 
$f->entityEncodeText = false;
$value = $adminTheme->get('defaultMainColor');
if(empty($value)) $value = 'red';
$f->val($value);
$inputfields->add($f);

$f = $inputfields->InputfieldText;
$f->attr('id+name', 'defaultMainColorCustom'); 
$f->label = __('Custom main color'); 
$f->attr('type', 'color');
$f->showIf = 'defaultMainColor=custom';
$f->attr('style', 'width: 45px; padding: 1px 4px');
$value = (string) $adminTheme->get($f->attr('name')); 
if(empty($value)) $value = '#eb1d61';
if(ctype_alnum(ltrim($value, '#'))) $f->val($value);
$customColorValue = $value;
$f->columnWidth = 50;
$inputfields->add($f);

$f = $inputfields->InputfieldText;
$f->attr('id+name', 'defaultMainColorCustomDark');
$f->label = __('Custom main color (dark mode)');
$f->attr('type', 'color');
$f->attr('style', 'width: 45px; padding: 1px 4px');
$value = (string) $adminTheme->get($f->attr('name'));
if(empty($value)) $value = $customColorValue;
if(ctype_alnum(ltrim($value, '#'))) $f->val($value);
$f->columnWidth = 50;
$f->showIf = 'defaultMainColor=custom, defaultToggles=use2Colors';
$inputfields->add($f);

$url = $adminTheme->url() . 'themes/default/examples/';
$cssExamples = [
	__('Borderless') => $url . 'borderless.css', 
	__('Masthead') => $url . 'masthead.css', 
	__('Minimal') => $url . 'minimal.css',
];
foreach($cssExamples as $label => $url) {
	$cssExamples[$label] = "[$label]($url)";
}
$cssExamples = __('Examples:') . ' ' . implode(', ', $cssExamples);

$f = $inputfields->InputfieldURL; 
$f->attr('name', 'defaultCustomCssFile');
$f->label = __('Custom CSS file');
$f->icon = 'css3';
$f->description = __('Enter a local URL (without scheme) relative to installation root, i.e. `/site/templates/styles/admin.css`'); 
$f->notes = $cssExamples;
$f->val((string) $adminTheme->get('defaultCustomCssFile'));
$f->allowQuotes = false;
$f->allowIDN = false;
$f->collapsed = Inputfield::collapsedBlank;
$inputfields->add($f);

$f->addHookAfter('processInput', function(HookEvent $e) {
	$f = $e->object; /** @var InputfieldURL $f */
	$value = (string) $f->val();
	if(strpos($value, '//') !== false) {
		$f->error(__('Do not include scheme (http, https) in your URL')); 
		$f->val('');
	} else if($value) {
		$file = $e->wire()->config->paths->root . ltrim($value, '/'); 
		if(!file_exists($file)) {
			$f->error(sprintf(__('File does not exist: %s'), $file));
		}
	}
}); 

if($adminTheme->wire()->config->advanced) {
	$f = $inputfields->InputfieldTextarea;
	$f->attr('name', 'defaultCustomCss');
	$f->label = __('Custom CSS');
	$f->icon = 'css3';
	$f->description = __('Available in advanced mode only.'); 
	$f->notes = $cssExamples;
	$f->attr('style', 'font-family: Monaco, monospace');
	$f->collapsed = Inputfield::collapsedBlank;
	$value = (string) $adminTheme->get('defaultCustomCss');
	$f->val(trim($value));
	$inputfields->add($f);
}
