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
		if($f.val() === 'custom') {
			if($('body').hasClass('dark-theme')) {
				return $('#defaultMainColorCustomDark').val();
			} else {
				return $('#defaultMainColorCustom').val();
			}
		}
		return $f.closest('label').find('.defaultMainColorLabel').css('background-color');
	}
	
	/**
	 * Update the main color for given rgb or hex code
	 * 
	 * @param value
	 * 
	 */
	function setMainColor(value) {
		if(typeof value === 'undefined') value = getMainColor();
		console.log('setMainColor', value);
		
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
	
	function updateToggles() {
		var $use2Colors = $('#defaultToggles_use2Colors');
		var hidden = !$('#defaultMainColor_custom').prop('checked');
		$use2Colors.parent('label').prop('hidden', hidden);
		
		if($('#defaultToggles_useTogcbx').prop('checked')) {
			$('body').addClass('pw-togcbx');
		} else {
			$('body').removeClass('pw-togcbx'); 
		}
	}
	
	function setButtonColor(value) {
		$('.ui-button').css('background-color', value);
	}
	
	$('#wrap_defaultStyleName').on('input', 'input', function() {
		var styleName = $(this).val();
		$body.removeClass(styleClasses).addClass(styleName + '-theme');
		//$('#defaultMainColor input').eq(0).trigger('input');
		var color = getMainColor();
		setMainColor(color);
		setButtonColor(styleName === 'light' ? 'black' : color); 
	});
	
	$('#wrap_defaultMainColor').on('input', 'input', function() {
		var value = 'main-color-' + $(this).val();
		var color;
		if(value === 'main-color-custom') {
			if($('#defaultToggles_use2Colors').prop('checked')) {
				color = $('body').hasClass('dark-theme') ? $('#defaultMainColorCustomDark').val() : $('#defaultMainColorCustom').val();
			} else {
				color = $('#defaultMainColorCustom').val();
			}
		} else {
			color = $(this).closest('label').find('.defaultMainColorLabel').css('background'); 
		}
		$body.removeClass(colorClasses).addClass(value);
		setMainColor(color);
		updateToggles();
	});
	
	$('#defaultMainColorCustom, #defaultMainColorCustomDark').on('input', function() {
		var value = $(this).val();
		$body.removeClass(colorClasses).addClass('main-color-custom');
		if($(this).attr('id') === 'defaultMainColorCustomDark') {
			if($('body').hasClass('dark-theme')) setMainColor(value); 
		} else {
			if($('body').hasClass('light-theme')) {
				setMainColor(value);
			} else if(!$('#defaultToggles_use2Colors').prop('checked')) {
				setMainColor(value);
			}
		}
	});
	
	$('.ui-button').on('mouseover', function() {
		var color = getCurrentStyleName() === 'dark' ? 'white' : getMainColor();
		$(this).css('background-color', color);
	}).on('mouseout', function() {
		var color = getCurrentStyleName() === 'dark' ? getMainColor() : 'black';
		$(this).css('background-color', color);
	}); 

	/*
	$(document).on('admin-color-change', function() {
		if($('body').hasClass('main-color-custom')) {
			$('#defaultMainColorCustom, #defaultMainColorCustomDark').trigger('input');
		}
	});
	 */
	
	$('#defaultToggles_use2Colors').on('change', function() {
		if($(this).prop('checked')) {
			$('#defaultMainColorCustomDark').trigger('input');
		} else {
			$('#defaultMainColorCustom').trigger('input');
		}
	});
	
	$('#defaultToggles_useTogcbx').on('change', updateToggles); 
	
	updateToggles();
});
