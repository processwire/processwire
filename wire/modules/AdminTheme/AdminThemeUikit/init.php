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

/**
 * Configure PW modules for Uikit
 * 
 */

// uk class => width %
$ukGridWidths = array(
	'80%' => '4-5',
	'75%' => '3-4',
	'70%' => '2-3',
	'64%' => '2-3',
	'60%' => '3-5',
	'50%' => '1-2',
	'45%' => '1-2',
	'40%' => '2-5',
	'34%' => '1-3', 
	'33%' => '1-3',
	'32%' => '1-3',
	'30%' => '1-3',
	'25%' => '1-4',
	'20%' => '1-5',
	'16%' => '1-6',
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
	'liDisabledClass' => 'uk-disabled',
	'liEmptyClass' => '',
	'aClass' => '',
));

$config->set('MarkupAdminDataTable', array(
	'addClass' => 'uk-table uk-table-divider uk-table-justify uk-table-small',
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
	'dlClass' => 'uk-description-list uk-description-list-divider',
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
));

$config->set('InputfieldFile', array(
	'error' => "<span class='ui-state-error-text'>{out}</span>",
));

$config->set('InputfieldSelector', array(
	'selectClass' => 'uk-select',
	'inputClass' => 'uk-input', 
	'checkboxClass' => 'uk-checkbox'
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
$classes['form'] = 'InputfieldFormNoWidths InputfieldFormVertical uk-form-vertical';
$classes['list'] = 'Inputfields uk-grid-collapse uk-grid-match';
$classes['item_column_width_first'] = 'InputfieldColumnWidthFirst uk-first-column';
$classes['item'] = 'Inputfield {class} Inputfield_{name}'; // . ($adminTheme->get('useOffset') ? ' InputfieldIsOffset' : '');
$classes['item_error'] = "InputfieldStateError uk-alert-danger";
InputfieldWrapper::setClasses($classes);

$markup = InputfieldWrapper::getMarkup();
$markup['list'] = "<ul {attrs} uk-grid uk-height-match='target: > .Inputfield:not(.InputfieldStateCollapsed) > .InputfieldContent'>{out}</ul>";
$markup['item_label'] = "<label class='InputfieldHeader uk-form-label' for='{for}'>{out}</label>";
$markup['item_label_hidden'] = "<label class='InputfieldHeader InputfieldHeaderHidden'><span>{out}</span></label>";
$markup['item_content'] = "<div class='InputfieldContent uk-form-controls'>{out}</div>";
InputfieldWrapper::setMarkup($markup);

if(!$config->get('InputfieldWrapper')) $config->set('InputfieldWrapper', array());
$config->InputfieldWrapper('useColumnWidth', false);

