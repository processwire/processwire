/**
 * Initialize InputfieldToggle Inputfields
 * 
 */
function InputfieldToggleInit() {

	// this becomes true when we are in a click event, used to avoid double calls to handler
	var isClick = false;
	
	// classes for checked input and label
	var inputCheckedClass = 'InputfieldToggleChecked';
	var labelCheckedClass = 'InputfieldToggleCurrent'; 

	// get <label> element for given <input>
	function getLabelFromInput($input) {
		var $label = $input.next('label');
		if(!$label.length) $label = $input.parent('label');
		if(!$label.length) $label = $('label[for=' + $input.attr('id') + ']'); 
		return $label;
	}

	// get <input> element for given <label>
	function getInputFromLabel($label) {
		var $input = $label.prev('input'); // toggle buttons
		if(!$input.length) $input = $label.find('input'); // radios
		if(!$input.length) $input = $('input[id=' + $label.attr('for') + ']');
		return $input;
	}

	// event handler for labels/inputs in a .InputfieldToggleUseDeselect container
	function toggleInputEvent($input) {

		// allow for labels as prev sibling of input or label as parent element of input
		var $label = getLabelFromInput($input);
		var $prevInput = $input.closest('.InputfieldToggle').find('input.' + inputCheckedClass);
		var $prevLabel = $prevInput.length ? getLabelFromInput($prevInput) : null; 

		// check of another item was clicked when an existing selection was in place
		if($prevInput.length && $prevInput.attr('id') != $input.attr('id')) {
			// remove our custom class from existing selection
			// $prevInput.removeClass(inputCheckedClass).removeAttr('checked'); // JQM
			$prevInput.removeClass(inputCheckedClass).prop('checked', false);
			if($prevLabel) $prevLabel.removeClass(labelCheckedClass);
		}

		// check if clicked input was already checked
		if($input.hasClass(inputCheckedClass) && $input.closest('.InputfieldToggleUseDeselect').length) {
			// if clicked input was already checked, now make it un-checked
			// $input.removeAttr('checked').removeClass(inputCheckedClass); // JQM
			$input.prop('checked', false).removeClass(inputCheckedClass);
			$label.removeClass(labelCheckedClass);
			// if this de-select was the first selection in the request, it's necessary to remove
			// the checked attribute again a short while later for some reason
			// setTimeout(function() { $input.removeAttr('checked').trigger('change'); }, 100); // JQM
			setTimeout(function() { $input.prop('checked', false).trigger('change'); }, 100);
		} else {
			// input was just checked (and wasn't before), so add our checked class to the input
			// $input.attr('checked', 'checked').prop('checked', true); // JQM
			$input.prop('checked', true);
			$input.addClass(inputCheckedClass);
			$label.addClass(labelCheckedClass);
			$input.trigger('change');
		}
	}

	function initEvents() {
		$(document).on('change', '.InputfieldToggle input', function() {
			// change event for de-selectable radios
			if(isClick) return false;
			toggleInputEvent($(this));

		}).on('click', '.InputfieldToggle label:not(.InputfieldHeader)', function(event) {
			// click event for de-selectable radios
			if(isClick) return false;
			var $label = $(this);
			var $input = getInputFromLabel($label);
			if(!$input.length) return;
			isClick = true;
			toggleInputEvent($input);
			setTimeout(function() { isClick = false; }, 200);
			if($input.closest('.InputfieldToggleGroup').length) return false;
		});
	}
	
	function initColors() {
		
		var $button = $('.InputfieldToggleHelper > button');
		var $input = $('.InputfieldToggleHelper > input');
		
		if(!$button.length) $button = $('.InputfieldForm button.ui-priority-secondary').eq(0);
		if(!$button.length) $button = $('.InputfieldForm button.ui-button').eq(0);
		if(!$button.length) $button = $('.InputfieldForm button[type=submit]'); 
		if(!$input.length) $input = $('.InputfieldForm input[type=text]').eq(0);
		if(!$button.length || !$input.length) return;
		
		InputfieldToggleSetColors({
			onBg: $button.css('background-color'),
			on: $button.css('color'),
			offBg: $input.css('background-color'),
			off: $input.css('color'),
			border: $input.css('border-bottom-color')
		});
	}
	
	initEvents();
	initColors();
}

/**
 * Set custom colors for InputfieldToggle elements
 *
 */
function InputfieldToggleSetColors(customColors) {
	var colors = { on: '', onBg: '', off: '', offBg: '', border: '', hoverBg: '', hover: '' }
	$.extend(colors, customColors);
	if(!colors.hoverBg && colors.onBg) {
		colors.hoverBg = colors.onBg.replace('rgb(', 'rgba(').replace(')', ',.2)');
		if(!colors.hover) colors.hover = colors.off;
	}
	var style =
		"<style type='text/css'>" +
		'.InputfieldToggleGroup label { ' +
			(colors.offBg ? 'background-color: ' + colors.offBg + '; ' : '') +
			(colors.off ? 'color: ' + colors.off + ';' : '') +
			(colors.border ? 'border-color: ' + colors.border + ';' : '') +
		'} ' +
		'.InputfieldToggleGroup label.InputfieldToggleCurrent, ' + 
		'.InputfieldToggleGroup input:checked + label { ' +
			(colors.onBg ? 'background-color: ' + colors.onBg + '; ' : '') +
			(colors.on ? 'color: ' + colors.on + ';' : '') +
			(colors.onBg ? 'border-color: ' + colors.onBg + '; ' : '') +
		'} ' +
		'.InputfieldToggleGroup label:not(.InputfieldToggleCurrent):hover,' + 
		'.InputfieldToggleGroup input:not(:checked) + label:not(.InputfieldToggleCurrent):hover { ' +
			(colors.hoverBg ? 'background-color: ' + colors.hoverBg + '; ' : '') +
			(colors.hover ? 'color: ' + colors.hover + '; ' : '') +
		'}' +
		"</style>";
	$('head').append(style);
}

jQuery(document).ready(function($) {
	InputfieldToggleInit();
});
