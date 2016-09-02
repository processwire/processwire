<?php namespace ProcessWire;

/**
 * This init file is called before ProcessWire starts rendering the page or executing the process
 *
 * This is a place to attach hooks or modify render-specific settings before they are used. 
 *
 */

$config->inputfieldColumnWidthSpacing = 0; // percent spacing between columns

$markup = InputfieldWrapper::getMarkup(); 
$markup['item_label'] = "\n\t\t<label class='InputfieldHeader' for='{for}'>{out}</label>";
$markup['item_label_hidden'] = "\n\t\t<label class='InputfieldHeader InputfieldHeaderHidden'><span>{out}</span></label>";
$markup['item_content'] = "\n\t\t<div class='InputfieldContent'>\n{out}\n\t\t</div>";
InputfieldWrapper::setMarkup($markup); 

$classes = InputfieldWrapper::getClasses();
$classes['item'] = "Inputfield {class} Inputfield_{name}";
$classes['item_error'] = "InputfieldStateError";
InputfieldWrapper::setClasses($classes); 

wire()->addHookBefore('MarkupPagerNav::render', null, 'hookMarkupPagerNavRender'); 

/**
 * Change the default prev/next links for MarkupPagerNav
 *
 */
function hookMarkupPagerNavRender(HookEvent $event) {
	$options = $event->arguments(1);
	if(!isset($options['nextItemLabel'])) {
		$options['nextItemLabel'] = "<i class='fa fa-angle-right'></i>";
		$options['previousItemLabel'] = "<i class='fa fa-angle-left'></i>";
		$options['separatorItemLabel'] = "<span class='detail'>&hellip;</span>";
		$event->arguments(1, $options); 
	}
}

