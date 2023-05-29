function updateAdminThemeUikitExample() {
	
	var example; 
	example = $('#wrap__adminThemeExample');
	if(!example.length) example = $('#_adminThemeExample');
	var input = example.find('input,select,textarea');
	
	example.removeClass(
		'InputfieldIsOffset InputfieldIsOffsetSm InputfieldIsOffsetLg ' +
		'InputfieldNoBorder InputfieldHideBorder uk-card uk-card-default ' +
		'InputfieldIsPrimary InputfieldIsSecondary InputfieldIsHighlight InputfieldIsWarning'
	);
	
	var f = $('#wrap_Inputfield_themeInputSize');
	if(f.length) {
		var v = f.find('input:checked').val();

		if(v == 's') {
			input.removeClass('uk-form-large uk-form-medium').addClass('uk-form-small');
		} else if(v == 'm') {
			input.removeClass('uk-form-large uk-form-small').addClass('uk-form-medium');
		} else if(v == 'l') {
			input.removeClass('uk-form-small uk-form-medium').addClass('uk-form-large');
		} else {
			input.removeClass('uk-form-large uk-form-medium uk-form-small');
		}
	}
	
	var f = $('#wrap_Inputfield_themeInputWidth');
	if(f.length) {
		input.removeClass('uk-form-width-large uk-form-width-medium uk-form-width-small uk-form-width-xsmall'); 
		input.removeClass('InputfieldMaxWidth InputfieldSetWidth');	
		var v = f.find('input:checked').val();

		if(v == 's') {
			input.addClass('uk-form-width-small');
		} else if(v == 'xs') {
			input.addClass('uk-form-width-xsmall');
		} else if(v == 'm') {
			input.addClass('uk-form-width-medium');
		} else if(v == 'l') {
			input.addClass('uk-form-width-large');
		} else if(v == 'f') {
			input.addClass('InputfieldMaxWidth');
		}
		if(v.length && v != 'f') input.addClass('InputfieldSetWidth');
	}

	var f = $('#Inputfield_themeBlank'); 
	if(f.length) {
		if(f.is(':checked')) {
			input.addClass('uk-form-blank');
		} else {
			input.removeClass('uk-form-blank');
		}
	}

	var f = $('#wrap_Inputfield_themeOffset');
	var v = f.find('input:checked').val();

	if(v == 's') {
		example.addClass('InputfieldIsOffset InputfieldIsOffsetSm');
	} else if(v == 'm') {
		example.addClass('InputfieldIsOffset');
	} else if(v == 'l') {
		example.addClass('InputfieldIsOffset InputfieldIsOffsetLg');
	}

	f = $('#wrap_Inputfield_themeBorder');
	v = f.find('input:checked').val();

	if(v == 'card') {
		example.addClass('uk-card uk-card-default');
	} else if(v == 'none') {
		example.addClass('InputfieldNoBorder');
	} else if(v == 'hide') {
		example.addClass('InputfieldHideBorder');
	}

	f = $('#wrap_Inputfield_themeColor');
	v = f.find('input:checked').val();

	if(v == 'primary') {
		example.addClass('InputfieldIsPrimary');
	} else if(v == 'secondary') {
		example.addClass('InputfieldIsSecondary');
	} else if(v == 'highlight') {
		example.addClass('InputfieldIsHighlight');
	} else if(v == 'warning') {
		example.addClass('InputfieldIsWarning');
	}

	var bgcolor = example.css('background-color');
	$('#_adminTheme, #_adminTheme > *').css('background-color', bgcolor);
}

function initAdminThemeUikitColumnWidth() {
	
	var example = $('#wrap__adminThemeExample');
	var example2 = $('#wrap__adminThemeExample2');
	if(!example.length) example = $('#_adminThemeExample');
	if(!example2.length) example2 = $('#_adminThemeExample2');
	
	var $columnWidth = $('#columnWidth');
	var noGrid = $('body').hasClass('AdminThemeUikitNoGrid');
	
	$columnWidth.on('change', function(e) {
		var pct = parseInt($(this).val());
		var minPct = 9;
		var maxPct = 91;
		if(noGrid) minPct = 0;
		if(pct > minPct && pct < maxPct) {
			example.attr('data-colwidth', pct + '%');
			example.addClass('InputfieldColumnWidth');
			example2.addClass('InputfieldColumnWidth');
			if(example2.hasClass('InputfieldStateHidden')) example2.removeClass('InputfieldStateHidden').show();
			example2.attr('data-colwidth', (100 - pct) + '%');
		} else {
			example.removeClass('InputfieldColumnWidth').removeAttr('data-colwidth');
			if(noGrid) example.css('width', '100%');
			example2.removeClass('InputfieldColumnWidth').addClass('InputfieldStateHidden').hide();
		}
		example.trigger('showInputfield', [ example[0] ]);
	}); 
	
	$columnWidth.trigger('change');
}

$(document).ready(function() {
	$('#_adminTheme').find('input[type=radio],input[type=checkbox]').on('change', function() {
		updateAdminThemeUikitExample();
	});
	updateAdminThemeUikitExample();
	initAdminThemeUikitColumnWidth();

});
