<?php namespace ProcessWire;

/** @var Config $config */
/** @var AdminThemeUikit $adminTheme */
/** @var User $user */
/** @var WireInput $input */
/** @var Page $page */

$themeInfo = $adminTheme->getThemeInfo();
$customCss = $adminTheme->get('defaultCustomCss');
$customCssFile = $adminTheme->get('defaultCustomCssFile');

$mainColors = [ 
	'red' => '#eb1d61', 
	'green' => '#14ae85', 
	'blue' => '#2380e6', 
	'custom' => $adminTheme->get('defaultMainColorCustom'),
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

if($darkMode === 1) {
	$styleName = 'dark';
} else if($darkMode === 0) {
	$styleName = 'light';
} else {
	$styleName = $adminTheme->get('defaultStyleName');
	if(empty($styleName)) $styleName = 'light';
}

$adminTheme->addBodyClass("$styleName-theme");

$mainColor = $adminTheme->get('defaultMainColor'); 
if(empty($mainColor)) $mainColor = 'red';
$adminTheme->addBodyClass("main-color-$mainColor"); 

$mainColorCode = isset($mainColors[$mainColor]) ? $mainColors[$mainColor] : $mainColors['red'];
if(strpos($mainColorCode, '#') === 0 && ctype_alnum(ltrim($mainColorCode, '#'))) {
	$adminTheme->addExtraMarkup('head', 
		"<style id='main-color-custom' type='text/css'>:root { --main-color: $mainColorCode }</style>"
	);
}

if($customCss) {
	$customCss = htmlspecialchars($customCss, ENT_NOQUOTES); 
	$adminTheme->addExtraMarkup('head', "<style id='default-custom-css' type='text/css'>$customCss</style>");
}
