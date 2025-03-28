<?php namespace ProcessWire;

/**
 * AdminThemeUikit initialization file
 * 
 */

if(!defined("PROCESSWIRE")) die();


/** @var Config $config */
/** @var ProcessWire $wire */
/** @var AdminThemeUikit $adminTheme */
/** @var WireInput $input */

$adminTheme = $config->wire('adminTheme');

/**
 * Configure PW modules for Uikit
 * 
 */

// uk class => width %
$ukGridWidths = array(
	'84%' => '5-6', // 84%-94%
	'80%' => '4-5', // 80%-83%
	'74%' => '3-4', // 74%-79%
	'65%' => '2-3', // 65%-73%
	'58%' => '3-5', // 58%-64%
	'43%' => '1-2', // 43%-57%
	'36%' => '2-5', // 36%-42%
	'27%' => '1-3', // 27%-35%
	'21%' => '1-4', // 21%-26%
	'17%' => '1-5', // 17%-20%
	'5%' => '1-6', // 5%-17%
);

$config->set('inputfieldColumnWidthSpacing', 0); 
$config->js('ukGridWidths', $ukGridWidths);

$config->set('InputfieldForm', array(
	'useOffset' => false, // must be false to support configuration per-field
	'useBorders' => true, // must be true to support configuration per-field
	'ukGridWidths' => $ukGridWidths
));

$config->set('InputfieldRadios', array(
	'wbr' => false
));

$config->set('JqueryWireTabs', array(
	'ulClass' => 'WireTabs',
	'ulAttrs' => 'uk-tab',
	'liActiveClass' => 'uk-active',
	'aActiveClass' => 'pw-active',
	'loadStyles' => false,
	'tooltipAttr' => array(
		'title' => '{tip}', 
		'uk-tooltip' => '', 
	), 
));

$config->set('LanguageTabs', array(
	'jQueryUI' => false, 
	'ulClass' => '',
	'ulAttrs' => 'uk-tab',
	'liActiveClass' => 'uk-active',
	'liDisabledClass' => '',
	'liEmptyClass' => '',
	'aClass' => '',
));

$config->set('MarkupAdminDataTable', array(
	'addClass' => $adminTheme->getClass('table'), 
	'loadStyles' => false,
	'loadScripts' => true,
	'responsiveClass' => '',
	'responsiveAltClass' => '',
));

$config->set('MarkupPagerNav', array(
	'nextItemLabel' => "<i class='fa fa-angle-right'></i>",
	'previousItemLabel' => "<i class='fa fa-angle-left'></i>",
	'currentItemClass' => 'uk-active MarkupPagerNavOn',
	'separatorItemLabel' => '<span>&hellip;</span>',
	'separatorItemClass' => 'uk-disabled MarkupPagerNavSeparator',
	'listMarkup' => "<ul class='uk-pagination MarkupPagerNav'>{out}</ul>",
));

$config->set('ProcessPageList', array(
	'paginationClass' => 'uk-pagination',
	'paginationCurrentClass' => 'uk-active',
	'paginationLinkClass' => 'pw-link',
	'paginationLinkCurrentClass' => 'pw-link-active',
	'paginationHoverClass' => 'pw-link-hover',
	'paginationDisabledClass' => 'uk-disabled',
	// 'extrasLabel' => "<i class='fa fa-caret-right'></i>", 
));

$config->set('ProcessList', array(
	'dlClass' => $adminTheme->getClass('dl'), 
	'dtClass' => '',
	'ddClass' => '',
	'aClass' => '',
	'disabledClass' => 'ui-priority-secondary',
	'showIcon' => true,
));

$buttonClassKey = $config->wire('hooks')->isHooked('InputfieldImage::renderButtons()') ? '_buttonClass' : 'buttonClass'; 
$config->set('InputfieldImage', array(
	// only use custom classes if renderButtons is not hooked
	$buttonClassKey => 'uk-button uk-button-small uk-button-text uk-margin-small-right', 
	'buttonText' => '{out}',
	'selectClass' => $adminTheme->getClass('select-small'),
));

$config->set('InputfieldFile', array(
	'error' => "<span class='ui-state-error-text'>{out}</span>",
));

$config->set('InputfieldSelector', array(
	'selectClass' => $adminTheme->getClass('select') . ' InputfieldSetWidth',
	'inputClass' => $adminTheme->getClass('input') . ' InputfieldSetWidth', 
	'checkboxClass' => $adminTheme->getClass('input-checkbox'), 
));

$config->set('SystemNotifications', array(
	'classCommon' => 'uk-alert', 
	'classMessage' => 'NoticeMessage uk-alert-primary',
	'classWarning' => 'NoticeWarning uk-alert-warning',
	'classError' => 'NoticeError uk-alert-danger',
	'classContainer' => 'pw-container uk-container uk-container-expand',
	'iconRemove' => 'times',
));

/**
 * Inputfield forms markup and classes
 * 
 */

$classes = InputfieldWrapper::getClasses();
$classes['form'] = 'InputfieldFormVertical uk-form-vertical' . ($adminTheme->ukGrid ? ' InputfieldFormNoWidths' : '');
$classes['list'] = 'Inputfields uk-grid uk-grid-collapse uk-grid-match';
$classes['list_clearfix'] = 'uk-clearfix';
$classes['item_column_width_first'] = 'InputfieldColumnWidthFirst uk-first-column';
$classes['item'] = 'Inputfield {class} Inputfield_{name}'; // . ($adminTheme->get('useOffset') ? ' InputfieldIsOffset' : '');
$classes['item_error'] = "InputfieldStateError uk-alert-danger";
InputfieldWrapper::setClasses($classes);

$markup = InputfieldWrapper::getMarkup();
$markup['list'] = "<ul {attrs} uk-grid uk-height-match='target: > .Inputfield:not(.InputfieldStateCollapsed) > .InputfieldContent'>{out}</ul>";
$markup['item_label'] = "<label class='InputfieldHeader uk-form-label' for='{for}'>{out}</label>";
$markup['item_label_hidden'] = "<label class='InputfieldHeader InputfieldHeaderHidden' for='{for}'><span>{out}</span></label>";
$markup['item_content'] = "<div class='InputfieldContent uk-form-controls'>{out}</div>";
InputfieldWrapper::setMarkup($markup);

if(!$config->get('InputfieldWrapper')) $config->set('InputfieldWrapper', array());

if($adminTheme->ukGrid) {
	$config->InputfieldWrapper('useColumnWidth', false); 
} else {
	$config->InputfieldWrapper('useColumnWidth', 2); // 2=use both style='width:%' and data-colwidth attributes
}
