/**
 * Manages InputfieldDatetime (text) elements with jQuery UI datepickers
 * 
 */
function InputfieldDatetimeDatepicker($t) {

	var pickerVisible = $t.is(".InputfieldDatetimeDatepicker2");
	var ts = parseInt($t.attr('data-ts')); 
	var tsDate = null;
	var dateFormat = $t.attr('data-dateformat'); 
	var timeFormat = $t.attr('data-timeformat');
	var timeSelect = parseInt($t.attr('data-timeselect'));
	var hasTimePicker = timeFormat.length > 0 && !pickerVisible;
	var showOn = $t.is(".InputfieldDatetimeDatepicker3") ? 'focus' : 'button';
	var ampm = parseInt($t.attr('data-ampm')) > 0; 
	var yearRange = $t.attr('data-yearrange'); 

	if(ts > 1) tsDate = new Date(ts); 

	if(pickerVisible) {
		// datepicker always visible (inline)
		var $datepicker = $("<div></div>"); 
		$t.after($datepicker); 
	} else {
		// datepicker doesn't appear till requested
		var $datepicker = $t; 
	}

	var options = {
		changeMonth: true,
		changeYear: true,
		showOn: showOn,
		buttonText: "&gt;",
		showAnim: 'fadeIn',
		dateFormat: dateFormat,
		gotoCurrent: true,
		defaultDate: tsDate
	}; 
	
	var attrOptions = JSON.parse($t.attr('data-datepicker'));
	
	var customOptions = {};
	
	if(typeof ProcessWire.config.InputfieldDatetimeDatepickerDefaults === 'object') {
		options = $.extend({}, ProcessWire.config.InputfieldDatetimeDatepickerDefaults, options);
	}
	if(typeof ProcessWire.config.InputfieldDatetimeDatepickerOptions === 'object') {
		customOptions = ProcessWire.config.InputfieldDatetimeDatepickerOptions;
	}

	if(yearRange && yearRange.length) options.yearRange = yearRange; 

	if(hasTimePicker) { 
		options.ampm = ampm; 
		options.timeFormat = timeFormat; 
		if(timeSelect > 0) {
			options.controlType = 'select';
			options.oneLine = true;
		}
		if(timeFormat.indexOf('ss') > -1) options.showSecond = true; 
		if(timeFormat.indexOf('m') == -1) options.showMinute = false;
		options = $.extend(options, attrOptions, customOptions);
		$datepicker.datetimepicker(options); 
	} else {
		options = $.extend(options, attrOptions, customOptions);
		$datepicker.datepicker(options); 
	}

	if(pickerVisible) {
		$datepicker.on('change', function(e) {
			var d = $datepicker.datepicker('getDate');
			var str = $.datepicker.formatDate(dateFormat, d); 
			$t.val(str); 
		}); 
	}

	// if using a trigger button, replace with a link icon
	if(showOn == 'button') {
		var $button = $t.next('button.ui-datepicker-trigger');
		if($button.length) {
			var $a = $("<a class='pw-ui-datepicker-trigger' href='#'><i class='fa fa-calendar'></i></a>");
			$button.after($a).hide();
			$a.on('click', function() {
				$button.trigger('click');
				return false;
			});
		}
	}
	
	$t.addClass('initDatepicker');
	
}

/**
 * Manages InputfieldDatetimeSelect elements
 * 
 */
function InputfieldDatetimeSelect() {

	/**
	 * Validate selection in InputfieldDatetime selects
	 *
	 */
	function validate($select) {
		var $parent = $select.parent(),
			$month = $parent.children('.InputfieldDatetimeMonth'),
			month = parseInt($month.val()),
			$day = $parent.children('.InputfieldDatetimeDay'),
			day = parseInt($day.val()),
			$year = $parent.children('.InputfieldDatetimeYear'),
			year = parseInt($year.val()),
			$value = $parent.children('.InputfieldDatetimeValue'),
			date = month && day && year ? new Date(year, month - 1, day) : null,
			errorClass = 'InputfieldDatetimeError';

		if(date && date.getMonth() + 1 != month) {
			// day not valid for month
			day = '';
			$day.val('').addClass(errorClass);
		} else {
			$day.removeClass(errorClass);
		}

		$value.val(date && day ? year + '-' + month + '-' + day : '');
	}

	/**
	 * Called when the Year select has changed in an InputfieldDatetimeSelect
	 *
	 * Enables addition of years before/after when "-" or "+" option is selected
	 *
	 */
	function yearChange($select) {

		var value = $select.val();
		if(value !== '-' && value !== '+') return;

		var $blankOption = $select.find('option[value=""]'),
			$option = $select.find('option[value="' + value + '"]'),
			fromYear = parseInt($select.attr('data-from-year')),
			toYear = parseInt($select.attr('data-to-year')),
			numYears = toYear - fromYear,
			n = 0,
			$o;

		if(numYears < 10) numYears = 10;

		if(value === '-') {
			// add # years prior
			toYear = fromYear-1;
			fromYear = fromYear - numYears;
			for(n = toYear; n >= fromYear; n--) {
				$o = jQuery('<option />').val(n).text(n);
				$select.prepend($o);
			}
			$option.html('&lt; ' + fromYear);
			$select.prepend($option).prepend($blankOption);
			$select.val(toYear);
			$select.attr('data-from-year', fromYear);

		} else if(value === '+') {
			// add # years after
			fromYear = toYear+1;
			toYear += numYears;
			for(n = fromYear; n <= toYear; n++) {
				$o = $('<option />').val(n).text(n);
				$select.append($o);
			}
			$option.html('&gt; ' + toYear);
			$select.append($option);
			$select.val(fromYear);
			$select.attr('data-to-year', toYear);
		}
	}

	jQuery(document).on('change', '.InputfieldDatetimeSelect select', function() {
		var $select = jQuery(this);
		if($select.hasClass('InputfieldDatetimeYear')) yearChange($select);
		validate($select);
	});
}

/**
 * Document ready
 * 
 */
jQuery(document).ready(function($) {

	// init datepickers present when document is ready
	$("input.InputfieldDatetimeDatepicker:not(.InputfieldDatetimeDatepicker3):not(.initDatepicker)").each(function(n) {
		InputfieldDatetimeDatepicker($(this)); 
	});

	// init datepicker that should appear on focus (3) of text input, that wasn't present at document.ready
	$(document).on('focus', 'input.InputfieldDatetimeDatepicker3:not(.hasDatepicker)', function() {
		InputfieldDatetimeDatepicker($(this));
	});

	// init date selects
	InputfieldDatetimeSelect();
	
}); 
