<?php namespace ProcessWire;

/** @var Config $config */
/** @var AdminThemeUikit $adminTheme */
/** @var User $user */
/** @var WireInput $input */
/** @var Page $page */

$themeInfo = $adminTheme->getThemeInfo();
$customCss = $adminTheme->get('defaultCustomCss');
$customCssFile = $adminTheme->get('defaultCustomCssFile');
$toggles = $adminTheme->defaultToggles;
$settings = $config->AdminThemeUikit;

$mainColors = [ 
	'red' => '#eb1d61', 
	'green' => '#14ae85', 
	'blue' => '#2380e6', 
	'custom' => $adminTheme->get('defaultMainColorCustom'),
	'customDark' => $adminTheme->get('defaultMainColorCustomDark'),
];

$config->styles->append($themeInfo['url'] . 'admin.css');
$config->scripts->append($themeInfo['url'] . 'admin.js');

if($customCssFile) {
	$config->styles->append($config->urls->root . ltrim($customCssFile, '/')); 
}

if($page->process == 'ProcessModule' && $input->get('name') === $adminTheme->className()) {
	$darkMode = null;
} else {
	$darkMode = $user->meta('adminDarkMode');
}

if(is_array($settings) && !empty($settings['noDarkMode'])) {
	$styleName = 'light';
	$adminTheme->addBodyClass('pw-no-dark-mode'); 
} else if($darkMode === 1) {
	$styleName = 'dark';
} else if($darkMode === 0) {
	$styleName = 'light';
} else {
	$styleName = $adminTheme->get('defaultStyleName');
	if(empty($styleName)) $styleName = 'light';
}

$adminTheme->addBodyClass("$styleName-theme");
if(in_array('useTogcbx', $toggles) && empty($settings['noTogcbx'])) {
	$adminTheme->addBodyClass("pw-togcbx");
}

$mainColor = $adminTheme->get('defaultMainColor'); 
if(empty($mainColor)) $mainColor = 'red';
$adminTheme->addBodyClass("main-color-$mainColor"); 

$mainColorCode = isset($mainColors[$mainColor]) ? $mainColors[$mainColor] : $mainColors['red'];
if(strpos($mainColorCode, '#') === 0 && ctype_alnum(ltrim($mainColorCode, '#'))) {
	$mainDarkCode = $mainColors['customDark']; 
	$use2Colors = $mainColor === 'custom' && in_array('use2Colors', $adminTheme->defaultToggles);
	if($use2Colors && strpos($mainDarkCode, '#') === 0 && ctype_alnum(ltrim($mainDarkCode, '#'))) {
		$css = "--main-color: light-dark($mainColorCode, $mainDarkCode);";
	} else {
		$css = "--main-color: $mainColorCode";
	}
	$adminTheme->addExtraMarkup('head',
		"<style id='main-color-custom' type='text/css'>:root { $css }</style>"
	);
}

if($customCss) {
	$customCss = htmlspecialchars($customCss, ENT_NOQUOTES); 
	$customCss = str_replace('&gt;', ' > ', $customCss);
	$adminTheme->addExtraMarkup('head', "<style id='default-custom-css' type='text/css'>$customCss</style>");
}
