/**
 * Helper class for InputfieldSubmit to support dropdown items for the submit button
 * 
 * ProcessWire 3.x, Copyright 2015 by Ryan Cramer
 * https://processwire.com
 * License: MPL 2.0
 *
 */
var InputfieldSubmitDropdown = {

	/**
	 * Click event for dropdown item
	 * 
	 * @returns {boolean}
	 * 
	 */
	click: function() {
		
		var $a = $(this);
		var href = $a.attr('href');
		var $dropdown = $a.closest('.pw-button-dropdown');
		var $button;
		
		if(!$dropdown.length) return true;
		
		$button = $dropdown.data('button');
		
		if($a.hasClass('pw-button-dropdown-default')) {
			
			// just click the button
			
		} else {
			
			var value = $a.attr('data-dropdown-value');
			var selector = $dropdown.attr('data-dropdown-input');
			
			if(!value) return true;

			if(selector) {
				// populate a hidden input with dropdown value
				var $input = $(selector);
				if(!$input.length) return true;
				$input.val(value);
			} else if(href.length > 1) {
				// link to URL
				return true;
			}
			
			// populate button 'value' attribute
			if($button) $button.attr('value', value);
		}

		if(!$button) return true;
		
		// if any inputs are currently focused, blur them
		$(":input:focus").blur();
	
		// click the button
		$button.click();

		return false;	
	},

	/**
	 * Counter for tracking quantity of dropdowns
	 * 
	 */
	dropdownCnt: 0,

	/**
	 * Initialize the given dropdown
	 * 
	 * @param $dropdown An instance of ul.pw-button-dropdown
	 * @param $mainButton An instance of button
	 * 
	 */
	initDropdown: function($dropdown, $mainButton) {
		
		var $toggleButton = $("<button><i class='fa fa-angle-down'></i></button>")
			.attr('id', 'pw-dropdown-toggle-' + $mainButton.attr('id'));
		
		$mainButton.after($toggleButton);
		$toggleButton.button();

		var $dropdownTemplate = null;
		if($dropdown.hasClass('pw-button-dropdown-template')) {
			$dropdownTemplate = $dropdown;
			$dropdown = $dropdownTemplate.clone();
			$dropdownTemplate.hide();
		}

		InputfieldSubmitDropdown.dropdownCnt++;
		var dropdownCntClass = 'pw-button-dropdown-' + InputfieldSubmitDropdown.dropdownCnt;

		$dropdown.addClass('dropdown-menu pw-dropdown-menu shortcuts pw-button-dropdown-init ' + dropdownCntClass);
		$dropdown.data('button', $mainButton);

		var $buttonText = $mainButton.find('.ui-button-text');
		var labelText = $.trim($buttonText.text());
		var labelHTML = $buttonText.html();

		$dropdown.find('a').each(function() {
			var $a = $(this);
			if($dropdownTemplate) {
				var html = $a.html();
				if(html.indexOf('%s') > -1) $a.html(html.replace('%s', labelText));
			}
			$a.click(InputfieldSubmitDropdown.click);
		});

		/*
		 // add first item to be same as default button action
		 var $li = $('<li></li>');
		 var $a = $('<a></a>').attr('href', '#default').append(labelHTML).addClass('pw-button-dropdown-default');
		 $a.click(pwButtonDropdownClick);
		 var $icon = $a.find('i');

		 if(!$icon.length) {
		 $icon = "<i class='fa fa-fw fa-check-square'></i>&nbsp;";
		 $a.prepend($icon);
		 } else {
		 $icon.addClass('fa-fw');
		 }
		 $dropdown.prepend($li.append($a));
		 */

		$mainButton.addClass('pw-button-dropdown-main');
		$toggleButton.after($dropdown)
			.addClass('dropdown-toggle dropdown-toggle-click pw-dropdown-toggle pw-button-dropdown-toggle')
			.attr('data-dropdown', '.' + dropdownCntClass);
		if($mainButton.hasClass('ui-priority-secondary')) $toggleButton.addClass('ui-priority-secondary');
		if($mainButton.hasClass('head_button_clone')) $toggleButton.addClass('head_button_clone');

		$toggleButton.click(function() {
			return false;
		});
		
	},

	/**
	 * Initialize button(s) to dropdown
	 * 
	 * @param buttonSelector String selector to find button(s) or jQuery object of button instances
	 * @param $dropdownTemplate Optionally specify template to use (for cases where multiple buttons share same dropdown)
	 * @returns {boolean} False if dropdowns not setup, true if they were
	 * 
	 */
	init: function(buttonSelector, $dropdownTemplate) {
	
		// don't use dropdowns when on touch device or in modal window
		if($('body').hasClass('touch-device') || $('body').hasClass('modal')) {
			$("ul.pw-button-dropdown").hide();
			return false;
		}
		
		var $buttons = (typeof buttonSelector == "string") ? $(buttonSelector) : buttonSelector; 
		
		$buttons.each(function() {
			var $button = $(this);
			if(typeof $dropdownTemplate != "undefined") {
				$dropdownTemplate.addClass('pw-button-dropdown-template');
				InputfieldSubmitDropdown.initDropdown($dropdownTemplate, $button);	
			} else {
				var $dropdown = $('#' + $(this).prop('id') + '_dropdown');
				if($dropdown.length) InputfieldSubmitDropdown.initDropdown($dropdown, $button);
			}
		});
		
		return true; 
	}
	
}