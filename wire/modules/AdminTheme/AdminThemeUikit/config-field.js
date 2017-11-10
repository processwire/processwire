function updateAdminThemeUikitExample() {
	
	var example = $('#_adminThemeExample');
	example.removeClass(
		'InputfieldIsOffset InputfieldIsOffsetSm InputfieldIsOffsetLg ' +
		'InputfieldNoBorder InputfieldHideBorder uk-card uk-card-default ' +
		'InputfieldIsPrimary InputfieldIsSecondary InputfieldIsHighlight InputfieldIsWarning'
	);

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
}

$(document).ready(function() {
	$('#_adminTheme').find('input[type=radio]').change(function() {
		updateAdminThemeUikitExample();
	});
	$('#_adminTheme, #_adminTheme > *').css('background-color', '#fff');
	updateAdminThemeUikitExample();
});
