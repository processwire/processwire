$(document).ready(function() {
	
	var colorClasses = 'main-color-red main-color-blue main-color-green main-color-custom';
	var styleClasses = 'light-theme dark-theme';
	var $body = $('body');
	
	/**
	 * Get current style, 'light' or 'dark'
	 * 
	 * @returns {string|string}
	 * 
	 */
	function getCurrentStyleName() {
		if($body.hasClass('dark-theme')) return 'dark';
		if($body.hasClass('light-theme')) return 'light';
		var bgcolor = $('#pw-mastheads').css('background-color');
		bgcolor = bgcolor.replace(/[^0-9]/g, '').substring(0, 3);
		$body.removeClass('auto-theme');
		var styleName = bgcolor === '000' ? 'dark' : 'light';
		$body.addClass(styleName + '-theme');
		return styleName;
	}
	
	/**
	 * Get the main color code (hex or rgb), whether predefined or custom
	 * 
	 * @returns {string}
	 * 
	 */
	function getMainColor() {
		var $wrap = $('#wrap_defaultMainColor');
		var $f = $wrap.find('input:checked');
		if($f.val() === 'custom') return $('#defaultMainColorCustom').val();
		return $f.closest('label').find('.defaultMainColorLabel').css('background-color');
	}
	
	/**
	 * Update the main color for given rgb or hex code
	 * 
	 * @param value
	 * 
	 */
	function setMainColor(value) {
		$('#main-color-custom').remove();
		$('.pw-logo-native').css('color', value);
		
		var styleName = getCurrentStyleName();
		setButtonColor(styleName === 'dark' ? value : 'black'); 
		
		$('head').append(
			"<style type='text/css' id='main-color-custom'>" +
				":root { " +
					".main-color-custom { " +
						"--main-color: " + value  + "; " +
					"} " +
			"} " +
			"</style>");
	}
	
	function setButtonColor(value) {
		$('.ui-button').css('background-color', value);
	}
	
	$('#wrap_defaultStyleName').on('input', 'input', function() {
		var styleName = $(this).val();
		$body.removeClass(styleClasses).addClass(styleName + '-theme');
		$('#defaultMainColor').trigger('input');
		setButtonColor(styleName === 'light' ? 'black' : getMainColor()); 
	});
	
	$('#wrap_defaultMainColor').on('input', 'input', function() {
		var value = 'main-color-' + $(this).val();
		var color = $(this).closest('label').find('.defaultMainColorLabel').css('background') || $('#defaultMainColorCustom').val();
		$body.removeClass(colorClasses).addClass(value);
		setMainColor(color);
	});
	
	$('#defaultMainColorCustom').on('input', function() {
		var value = $(this).val();
		$body.removeClass(colorClasses).addClass('main-color-custom');
		setMainColor(value);
	});
	
	$('.ui-button').on('mouseover', function() {
		var color = getCurrentStyleName() === 'dark' ? 'white' : getMainColor();
		$(this).css('background-color', color);
	}).on('mouseout', function() {
		var color = getCurrentStyleName() === 'dark' ? getMainColor() : 'black';
		$(this).css('background-color', color);
	}); 
});
