/**
 * ProcessWire Selector Inputfield JS
 *
 * Concept by Antti Peisa
 * Code by Ryan Cramer
 * Sponsored by Avoine
 *
 * ProcessWire 3.x (development), Copyright 2015 by Ryan Cramer
 * https://processwire.com
 *
 */

var InputfieldSelector = {

	/**
	 * Saved value of the preview selector shown to users
	 * 
	 */
	selector: '',
	
	/**
	 * Markup for the spinner that appears when ajax events are running
	 * 
	 */
	spinner: "<i class='fa fa-lg fa-spin fa-spinner'></i>",

	/**
	 * Default border-color value, which we later attempt to modify and auto determine from the admin theme
	 * 
	 */
	borderColor: '#eee',

	/**
	 * Initialize InputfieldSelector and attach all needed events
	 * 
	 */
	init: function() {
		$(document).on('change', '.InputfieldSelector select.select-field', InputfieldSelector.changeField); 
		$(document).on('change', '.InputfieldSelector select.select-subfield', InputfieldSelector.changeField); 
		$(document).on('change', '.InputfieldSelector :input:not(.select-field):not(.input-value-autocomplete)', function() {
			InputfieldSelector.changeAny($(this)); 
		}); 
		$(document).on('opened', '.InputfieldSelector', function() {
			InputfieldSelector.normalizeHeightRows($(this)); 
		}); 

		var timeout = null;
		$(document).on('keyup', '.InputfieldSelector input.input-value', function() {
			var $t = $(this); 
			clearTimeout(timeout); 
			if($t.hasClass("input-value-subselect") && InputfieldSelector.valueHasOperator($t.val())) {
				var $preview = $t.closest('.InputfieldContent').find('.selector-preview'); 
				$preview.html('Subselect detected: when done <a href="#" onclick="return false">click here to commit your change</a>.'); 
				return;
			}
			timeout = setTimeout(function() { InputfieldSelector.changeAny($t); }, 100); 
		}); 

		$(document).on('click', '.InputfieldSelector .selector-add', function() {
			InputfieldSelector.addRow($(this)); 
			return false; 
		}); 	

		$(document).on('click', '.InputfieldSelector a.delete-row', InputfieldSelector.deleteRow); 

		$(".InputfieldSelector .selector-preview").hide();

		$(document).on('wiretabclick', function(event, $newTab, $oldTab) {
			var $inputfield = $newTab.find(".InputfieldSelector"); 
			if($inputfield.length == 0) return;
			InputfieldSelector.normalizeHeightRows($inputfield); 
		}); 

		/*
		$(".InputfieldSelector .InputfieldContent").eq(0).each(function() {
			InputfieldSelector.borderColor = $(this).css('border-bottom-color'); 
		}); 
		*/

		// trigger change any event for first item, in case we have one already populated
		//var $rows = $(".InputfieldSelector .selector-row:not(.selector-template-row)"); 
		var $rows = $(".InputfieldSelector .selector-row"); 
		if($rows.length > 0) {
			$rows.eq(0).find(".select-field").each(function() {
				// this ensures any data-template-ids attributes affect the field disabled state at the template-row level
				InputfieldSelector.changeAny($(this)); 
			}); 
			$rows.eq(1).find(".input-value").change(); // first visible row, if present
			$rows.each(function() {
				var $row = $(this); 
				$row.css('border-color', InputfieldSelector.borderColor); // match border color to current admin theme
				InputfieldSelector.normalizeHeightRow($row); 
				var $ac = $row.find(".input-value-autocomplete"); 
				if($ac.length > 0) {
					// setup autocomplete
					var subfield = $row.find(".select-subfield");
					var field = subfield.length ? subfield.val() : $row.find(".select-field").val();
					var name = $row.parents(".InputfieldSelector").find("input.selector-value").attr('name'); // selector-value intentional!
					InputfieldSelector.setupAutocomplete($ac, field, name); 
				}
			}); 
		}

		$(".InputfieldSelector").each(function() {
			if($(this).find(".selector-preview-disabled").length > 0) return;
			// force items to populate previews
			$(this).find(".input-value:eq(0)").change();
		}); 

	},
	
	/**
	 * Disable a <select> option
	 *
	 * @param $option
	 *
	 */
	disableOption: function($option) {
		$option.attr('disabled', 'disabled');
	},

	/**
	 * Enable a <select> option
	 *
	 * @param $option
	 *
	 */
	enableOption: function($option) {
		$option.removeAttr('disabled');

	},

	/**
	 * Does the given value (string) contain a selector operator?
	 *
	 * @param value
	 * @returns {boolean}
	 *
	 */
	valueHasOperator: function(value) {
		var operators = ['=', '<', '>'];
		var hasOperator = false;
		for(n = 0; n < operators.length; n++) {
			var pos = value.indexOf(operators[n]);
			// pos > 0 means there is an operator somewhere, and it's not escaped with a backslash
			if(pos > -1 && value.substring(pos-1, 1) != '\\') {
				hasOperator = true;
				break;
			}
		}
		return hasOperator;
	},

	/**
	 * Add a new InputfieldSelector row
	 * 
	 * @param $context
	 * 
	 */
	addRow: function($context) {
		var $list = $context.parents('.InputfieldSelector').find('.selector-list'); 
		var $row = $list.find('.selector-template-row');
		var $newRow = $row.clone();
		$newRow.removeClass('selector-template-row'); 
		$newRow.find('.opval').html(''); 
		$newRow.find('.select-field').val(''); // .select2();
		$newRow.hide();
		$newRow.find("option[disabled=disabled]").remove();
		$list.append($newRow); 
		$newRow.slideDown('fast');
		InputfieldSelector.normalizeHeightRow($newRow); 
	},

	/**
	 * Delete an InputfieldSelector row
	 * 
	 * @returns {boolean}
	 * 
	 */
	deleteRow: function() {
		var $row = $(this).parents(".selector-row");
		var $selectField = $row.find(".select-field"); 
		if($selectField.val() == 'template') {
			// if template setting is removed, restore any disabled fields
			$row.parents(".InputfieldSelector").find("select.select-field").each(function() {
				// $(this).find("option[disabled=disabled]").removeAttr('disabled'); 
				$(this).find("option[disabled=disabled]").each(function() {
					InputfieldSelector.enableOption($(this)); 
				}); 
			}); 
		}
		var $siblings = $row.siblings();
		$row.slideUp('fast', function() { 
			$row.remove(); 
			InputfieldSelector.changeAny($siblings.eq(0)); 
		}); 
		return false; 
	},

	/**
	 * Toggle all field <select> to use either names or labels
	 *
	 * @param $select
	 *
	 */
	changeFieldToggle: function($select) {
		
		var $rootParent = $select.parents(".InputfieldSelector");
		var currentSetting = $rootParent.hasClass('InputfieldSelector_names') ? 'names' : 'labels';
		var newSetting = (currentSetting === 'labels' ? 'names' : 'labels');

		$rootParent.find(".select-field, .select-subfield").each(function() {
			$(this).find('option').each(function() {
				var name = $(this).attr('data-name'); 
				if(!name) {
					if($(this).attr('value') == 'toggle-names-labels') {
						$(this).html($(this).attr('data-' + currentSetting)); 
					}
					return;
				}
				if(currentSetting == 'labels') {
					$(this).html(name); 
				} else {
					$(this).html($(this).attr('data-label'));
				}
			}); 
		});
		
		$rootParent.removeClass('InputfieldSelector_' + currentSetting)
			.addClass('InputfieldSelector_' + newSetting); 
		$select.val($select.attr('data-selected')); 
		return false; 
	},

	/**
	 * Event called when a field <select> has changed
	 * 
	 * This function initiates an ajax request to get the operator and value (opval) portion of the row.
	 * 
	 * @param $select
	 * 
	 */
	changeField: function($select) {

		//console.log('changeField'); 
		var $select = $(this); 
		var field = $select.val();
		if(!field || field.length == 0) return;
		if(field == 'toggle-names-labels') return InputfieldSelector.changeFieldToggle($select);
		var $row = $select.parents('.selector-row'); 
		var action = 'opval';
		$row.children('.opval').html(''); 
		$select.attr('data-selected', field); // so we can remember previous value

		var $hiddenInput = $select.parents('.InputfieldSelector').find('.selector-value'); // .selector-value intentional!
		var name = $hiddenInput.attr('name'); 
		var type = $select.attr('data-type'); 

		if(field.match(/\.$/)) {
			action = 'subfield';
			if(field.indexOf('@') > -1) field = field.substring(1, field.length-1); 
				else field = field.substring(0, field.length-1); 
			$row.addClass('has-subfield'); 
		} else if(field.match(/\.id$/)) {
			field = 'id';
			action = 'opval';
			type = 'selector';
		} else if($select.is(".select-field")) { 
			$row.children('.subfield').html(''); 
			$row.removeClass('has-subfield'); 
		}

		var url = './?InputfieldSelector=' + action + '&field=' + field + '&type=' + type + '&name=' + name; 
		var $spinner = $(InputfieldSelector.spinner); 

		$row.append($spinner); 

		$.get(url, function(data) {	
			$spinner.remove();
			var $data = $(data); 
			$data.hide();

			if(action == 'opval') {
				var $opval = $row.children('.opval'); 
				$opval.html('').append($data);
				$opval.children(':not(.input-or)').fadeIn('fast'); 

				//$data.fadeIn('fast');
				InputfieldSelector.changeAny($select);
				var $ac = $opval.find(".input-value-autocomplete"); 
				if($ac.length > 0) InputfieldSelector.setupAutocomplete($ac, field, name); 
			} else {
				var $subfield = $row.children('.subfield');
				$subfield.html('').append($data); 
				$data.fadeIn('fast'); 
				//$row.children('.subfield').html(data); 	
			}

			InputfieldSelector.normalizeHeightRow($row); 

			// this ensures that datepickers don't get confused with each other
			$row.closest('.InputfieldContent').find(".hasDatepicker").datepicker('destroy').removeAttr('id').removeClass('hasDatepicker'); 
		}); 
	},

	/**
	 * Normalize the height of a selector row so that all the inputs are visually vertically centered
	 * 
	 * @param $row
	 * 
	 */
	normalizeHeightRow: function($row) {
		InputfieldSelector.normalizeHeight($row.find(":input, i.fa")); 
	},

	/**
	 * Normalize the height of all selector rows so that all the inputs are visually vertically centered
	 *
	 * @param $parent
	 *
	 */
	normalizeHeightRows: function($parent) {
		$parent.find(".selector-row").each(function() {
			InputfieldSelector.normalizeHeightRow($(this)); 
		}); 
	},

	/**
	 * Normalize the height of all the given items so that all the inputs are visually vertically centered
	 *
	 * @param $items
	 *
	 */
	normalizeHeight: function($items) {
		var maxHeight = 0; 
		$items.each(function() {
			$(this).css('margin-top', 0); 
			var h = $(this).outerHeight();
			if(h > maxHeight) maxHeight = h; 
		}); 
		$items.each(function() {
			var h = $(this).outerHeight();
			if(h < maxHeight) {
				var targetHeight = (maxHeight - h) / 2;
				$(this).css('margin-top', targetHeight + 'px'); 
			}
		}); 
	},

	/**
	 * Setup jQuery UI autocomplete for an input
	 * 
	 * @param $item
	 * @param field
	 * @param name
	 * 
	 */
	setupAutocomplete: function($item, field, name) {

		var $counter = $item.parents(".InputfieldSelector").find(".selector-counter"); 

		$item.autocomplete({
			minLength: 2, 
			source: function(request, response) {
				$counter.html(InputfieldSelector.spinner); 
				var url = './?InputfieldSelector=autocomplete&field=' + field + '&name=' + name + '&q=';
				url += request.term; 
				$.getJSON(url, function(data) {
					$counter.html(''); 
					response($.map(data.items, function(item) {
						return { label: item['label'], value: item['value'] }
					})); 
				}); 
			}, 
			select: function(event, ui) {
				if(!ui.item) return;
				var $input = $item.siblings(".input-value"); 
				$input.val(ui.item.value).attr('data-label', ui.item.label); 
				$item.blur().hide();
				setTimeout(function() { 
					$item.val(ui.item.label); 
					//$item.attr('disabled', 'disabled'); 
					$item.fadeIn('fast'); 
				}, 100); 
				InputfieldSelector.changeAny($input); 
			}
		}).focus(function() {
			var $input = $item.siblings(".input-value"); 
			$input.val('');
			$item.val(''); 
			InputfieldSelector.changeAny($item);
			
		}); 
	},

	/**
	 * Event called when an operator or input value is changed
	 * 
	 * Primary goal is to convert the inputs to a selector string
	 * 
	 * @param $item
	 * 
	 */
	changeAny: function($item) {

		var selector = '';
		var selectorURL = ''; // to ProcessPageSearch
		var test = '';
		var selectors = []; 
		var $inputfield = $item.parents('.InputfieldSelector'); 
		var n = 0;
		var showOrNotes = false; 
		var $selectFields = $inputfield.find(".selector-row select.select-field"); 
		var $hiddenInput = $inputfield.find('.selector-value'); 
		var templateIDs = $hiddenInput.attr('data-template-ids');
		templateIDs = templateIDs ? templateIDs.split(',') : []; 

		// iterate each row to build selector string
		$selectFields.each(function() {

			var $select = $(this);
			var $row = $select.parent(".selector-row"); 
			var fieldName = $select.val();

			if(fieldName.length < 1) return;
		
			if(fieldName == 'template') {
				var templateID = parseInt($row.find('.input-value').val()); 
				if(templateID > 0) templateIDs.push(templateID); 
			}

			if($row.is(".has-subfield")) {
				var subfield = $row.find(".select-subfield").val();
				if(subfield.length > 0) { 
					if(subfield.indexOf('.') > 0) {
						// fieldName was already specified with subfield
						if(fieldName.indexOf('@') > -1) fieldName = '@' + subfield; 
							else fieldName = subfield; 
					} else {
						// subfield needs to be appended to fieldName
						fieldName += subfield; 
					}
					// .data is assumed and optional, so lets remove it since it's exteraneous
					if(fieldName.indexOf('.data') > 0) fieldName = fieldName.replace(/\.data$/, ''); 
				}
			}
			
			var $op = $select.siblings('.opval').children('.select-operator');
			var op = $op.val(); 
			var $value = $op.next('.input-value'); 
			var value = $value.val();

			if(op && op.indexOf('"') > -1) {
				// handle: 'is empty' or 'is not empty' operators
				value = ' ';
				$value.attr('disabled', 'disabled'); 
			} else if($value.is(":disabled")) {
				$value.removeAttr('disabled');
			}

			if(typeof value != "undefined") if(value.length) {
				
				if($value.hasClass("input-value-subselect") && InputfieldSelector.valueHasOperator(value)) {
					// value needs to be identified as a sub-selector
					value = '[' + value + ']';

				} else if(value.indexOf(',') > -1 && fieldName != '_custom') {
					// value needs to be quoted
					if(value.indexOf('"') > -1) { 
						if(value.indexOf("'") == -1) value = "'" + value + "'"; 
							else value = '"' + value.replace(/"/g, '') + '"'; // remove quote
					} else {
						value = '"' + value + '"'; 
					}
				}
			}

			var testField = ',' + fieldName + '~' + op + '~'; 
			var testValue = '~' + op + '~' + value + ','; 
			var mayOrValue = value && value.length > 0 && test.indexOf(testField) > -1; 
			var mayOrField = value && value.length > 0 && test.indexOf(testValue) > -1; 
			var $orCheckbox = $row.find(".input-or"); 
			var useOrValue = mayOrValue && $orCheckbox.is(":checked"); 
			var useOrField = mayOrField && $orCheckbox.is(":checked"); 
			var isOrGroup = (useOrField || useOrValue) && fieldName == '_custom';
			
			if(useOrValue) { //  && !$row.is('.has-or-value')) {
				$row.addClass('has-or-value'); 
				$row.find(".select-field, .select-operator, .select-subfield").attr('disabled', 'disabled'); 
			} else if($row.is('.has-or-value')) {
				$row.removeClass('has-or-value'); 
				$row.find(".select-field, .select-operator, .select-subfield").removeAttr('disabled');
			}

			if(useOrField) { //  && !$row.is('.has-or-field')) {
				$row.addClass('has-or-field'); 
				$row.find(".input-value, .select-operator").attr('disabled', 'disabled'); 
			} else if($row.is('.has-or-field')) {
				$row.removeClass('has-or-field'); 
				$row.find(".input-value, .select-operator").removeAttr('disabled'); 
			}

			selectors[n++] = {
				field: fieldName, 
				operator: op, 
				value: value,
				mayOrValue: mayOrValue,
				mayOrField: mayOrField,
				useOrValue: useOrValue, 
				useOrField: useOrField,
				isOrGroup: isOrGroup,
				checkbox: $orCheckbox
				};

			if(mayOrField || mayOrValue) showOrNotes = true; 
			test += ',' + fieldName + '~' + op + '~' + value + ',';
			selector += ',' + fieldName + op + value; // this gets rebuilt later, but is here for querying

		}); // each row

		// hide fields that aren't on one of the indicated templates
		if(templateIDs.length > 0) {
			// template changed, reduce fields to those within the template
			var $masterSelect = null;
			$selectFields.each(function() { 
				var $select = $(this) 
				var numDisabledOptions = 0;
				$select.find('option').each(function() {
					var $option = $(this);
					var templates = $option.attr('data-templates'); 
					if(typeof templates != "undefined" && templates != "*") {
						// $option.removeAttr('disabled'); 
						InputfieldSelector.enableOption($option); 
						var numFound = 0;
						for(i = 0; i < templateIDs.length; i++) {
							if(templates.indexOf('|' + templateIDs[i] + '|') > -1) numFound++;
						}
						//if(templates.indexOf('|' + templatesID + '|') == -1) {
						if(numFound) {
							//$option.removeAttr('disabled'); 
							InputfieldSelector.enableOption($option); 
						} else {
							//if(!$option.is(":selected")) $option.attr('disabled', 'disabled'); 
							if(!$option.is(":selected")) InputfieldSelector.disableOption($option); 
							numDisabledOptions++;
						}
					}
				}); 
				if(numDisabledOptions > 0 && !$select.parent().is(".selector-template-row")) {
					$select.find('option[disabled=disabled]').remove();
				}
			}); 
			
		}

		selector = '';
		for(n = 0; n < selectors.length; n++) {
			var s = selectors[n]; 
			if(s == null || typeof s == "undefined" || typeof s.value == "undefined") continue;  
			if(s.value.length == 0 && !$hiddenInput.is(".allow-blank")) continue; 
			if(selector.length > 0) selector += ', ';
			//if(s.mayOrField || s.mayOrValue) s.checkbox.show();
			for(var i = 0; i < selectors.length; i++) {
				if(i === n) continue; 
				var si = selectors[i]; 
				if(si === null || typeof si == "undefined" || typeof si.value == "undefined") continue; 
				if(si.field == '_custom' && si.isOrGroup) {
					s.isOrGroup = true;
				} else if(si.mayOrField && si.value == s.value && si.operator == s.operator) {
					si.checkbox.show();
					if(si.useOrField) {
						s.field += '|' + si.field; 
						selectors[i] = null;
					}
				} else if(si.mayOrValue && si.field == s.field && si.operator == s.operator) {
					si.checkbox.show();
					if(si.useOrValue) { 
						s.value += '|' + si.value; 
						selectors[i] = null;
					}
				} else if(!si.mayOrValue && !si.mayOrField) {
					si.checkbox.hide();
				}
			}
			if(s.field.indexOf('.') != s.field.lastIndexOf('.')) {
				// convert field.subfield.id to just field.subfield
				s.field = s.field.substring(0, s.field.lastIndexOf('.')); 
			}

			if(s.field == '_custom') {
				if(s.isOrGroup) {
					s.value = s.value.replace('(', '').replace(')', '');
					selector += s.field + '=' + '(' + $.trim(s.value) + ')';
				} else {
					//selector += s.value;
					selector += s.field + '="' + $.trim(s.value) + '"';
				}
			} else {
				selector += s.field + s.operator + $.trim(s.value); 
			}
		}

		var $preview = $item.closest('.InputfieldContent').find('.selector-preview'); 
		var initValue = $preview.attr('data-init-value'); 
		if(initValue && initValue.length) initValue += ', ';

		// update preview display
		if(selector.length > 0 && selector != InputfieldSelector.selector) { 
			if(!$preview.is(".selector-preview-disabled")) {
				$preview.html('<code>' + initValue + selector + '</code>'); 
				$preview.fadeIn(); 
			}
			var $counter = $preview.siblings('.selector-counter'); 
			if($counter.length > 0 && !$counter.is('.selector-counter-disabled')) {
				$counter.html(InputfieldSelector.spinner).fadeIn('fast'); 
				$.post('./?InputfieldSelector=test&name=' + $hiddenInput.attr('name'), { selector: selector }, function(data) {
					$counter.hide();
					$counter.html(data); 
					// $counter.html("<a href='" + config.urls.admin + 'page/search/for?' + selectorURL + "'>" + $counter.text() + "</a>"); 
					$counter.show();
				}); 
			}
		} 

		if($hiddenInput.val() != selector) {
			// update the hidden input where the selector is stored for form submission
			$hiddenInput.val(selector); 
			if(selector.length == 0) {
				$preview.hide();
				$preview.siblings('.selector-counter').html('');
			}
			$hiddenInput.change(); // trigger change
		}

		InputfieldSelector.selector = selector; 

		var $orNotes = $inputfield.find(".or-notes");
		if(showOrNotes) $orNotes.fadeIn();
			else $orNotes.hide();

	}

}; 

$(document).ready(function() {
	InputfieldSelector.init();
}); 
