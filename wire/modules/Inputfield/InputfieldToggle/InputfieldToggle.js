function InputfieldToggleInit() {

	// this becomes true when we are in a click event, used to avoid double calls to handler
	var isClick = false; 

	// event handler for labels/inputs in a .InputfieldToggleUseDeselect container
	function toggleInputEvent($input) {

		var cls = 'InputfieldToggleChecked';
		
		// allow for labels as prev sibling of input or label as parent element of input
		// var $label = $input.prev('label').length ? $input.prev('label') : $input.closest('label');
		var $prevInput = $input.closest('.Inputfield').find('input.' + cls);
		// var $prevLabel = $prevInput.prev('label').length ? $prevInput.prev('label') : $prevInput.closest('label');

		// check of another item was clicked when an existing selection was in place
		if($prevInput.length && $prevInput.attr('id') != $input.attr('id')) {
			// remove our custom class from existing selection
			$prevInput.removeClass(cls).removeAttr('checked');
		}

		// check if clicked input was already checked
		if($input.hasClass(cls) && $input.closest('.InputfieldToggleUseDeselect').length) {
			// if clicked input was already checked, now make it un-checked
			$input.removeAttr('checked').removeClass(cls);
			// if this de-select was the first selection in the request, it's necessary to remove
			// the checked attribute again a short while later for some reason
			setTimeout(function() { $input.removeAttr('checked') }, 100);
		} else {
			// input was just checked (and wasn't before), so add our checked class to the input
			// $input.attr('checked', 'checked').addClass(cls);
			$input.addClass(cls);
		}
	}
	
	$(document).on('change', '.InputfieldToggle input', function() {
		// change event for de-selectable radios
		if(isClick) return false;
		toggleInputEvent($(this));

	}).on('click', '.InputfieldToggle label:not(.InputfieldHeader)', function() {
		// click event for de-selectable radios
		if(isClick) return false;
		var $label = $(this);
		var $input = $label.prev('input.InputfieldToggleChecked'); // toggle buttons
		if(!$input.length) $input = $label.children('input.InputfieldToggleChecked'); // radios
		if(!$input.length) return;
		isClick = true;
		toggleInputEvent($input);
		setTimeout(function() { isClick = false; }, 200);
	}); 
	
	// button style for default toggle button group
	// inherit colors from existing inputs and buttons
	var $button = $('button.ui-button:eq(0)');
	var $input = $('.InputfieldForm input[type=text]:eq(0)');
	if($button.length && $input.length) {
		var onBgcolor, onColor, offBgcolor, offColor, borderColor, style;
		onBgcolor = $button.css('background-color');
		onColor = $button.css('color');
		offBgcolor = $input.css('background-color');
		offColor = $input.css('color');
		borderColor = $input.css('border-color');
		style =
			"<style type='text/css'>" +
				'.InputfieldToggleGroup label { ' + 
					'background-color: ' + offBgcolor + '; ' + 
					'color: ' + offColor + ';' + 
					'border-color: ' + borderColor + ';' + 
				'} ' + 
				'.InputfieldToggleGroup input:checked + label { ' +
					'background-color: ' + onBgcolor + '; ' +
					'color: ' + onColor + ';' +
					'border-color: ' + onBgcolor + '; ' + 
				'} ' +
			"</style>";
			
		$('body').append(style);
	}
}


jQuery(document).ready(function($) {
	InputfieldToggleInit();
});