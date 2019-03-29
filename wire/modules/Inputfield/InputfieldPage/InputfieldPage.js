/**
 * Initialize InputfieldPage element
 *
 * @param $this
 *
 */
function initInputfieldPage($this) {

	$this.find("p.InputfieldPageAddButton a").click(function() {
		var $input = $(this).parent('p').next('.InputfieldPageAddItems');
		if($input.is(":visible")) $input.slideUp('fast').find(":input").val('');
		else $input.slideDown('fast').parents('.ui-widget-content').slice(0,1).effect('highlight', {}, 500)
		return false;
	});

	initInputfieldPageDependentSelects($this);
}

/**
 * Initialize dependent selects in an .InputfieldPage 
 * 
 * @param $inputfieldPage
 * 
 */
function initInputfieldPageDependentSelects($inputfieldPage) {
	
	/**
	 * Function to be called when a change is made to $select1
	 *
	 * @param $select1 Primary select
	 * @param $select2 Dependent select
	 * @param selector Selector string to find items
	 * @param formatName Name of format sent directly from InputfieldPage to ProcessPageSearch (server side)
	 * @param labelFieldName Name of field to use for labels
	 * @param part Page matching part of selector
	 * @param changed Is this due to a change in $select1? true or false
	 *
	 */
	function selectChanged($select1, $select2, selector, formatName, labelFieldName, part, changed) {

		var v = $select1.val();

		if(v == null) {
			// no values selected
			if($select2.children().length) {
				$select2.children().remove();
				$select2.change();
			}
			return;
		}

		v = v.toString();
		v = v.replace(/,/g, '|'); // if multi-value field, convert commas to pipes

		selector = selector.replace(part, '=' + v);
		selector = selector.replace(/,\s*/g, '&');

		if(selector.indexOf('_LPID')) selector = selector.replace(/_LPID[0-9]+/g, '');

		var url = ProcessWire.config.urls.admin + 'page/search/for?' + selector + '&limit=9999&get=' + labelFieldName;
		if(formatName.length) url += '&format_name=' + formatName;

		$.getJSON(url, {}, function(data) {

			var numSelected = 0;
			$select2.children().addClass('option-tbd'); // mark existing options as to-be-deleted

			for(var n = 0; n < data.matches.length; n++) {

				var selected = false;
				var page = data.matches[n];
				var label = '';

				// first see if we can find the existing option already present
				var $option = $select2.children("[value=" + page.id + "]");

				if($option.length > 0) selected = $option.is(':selected') || $option.is(':checked');
				if(selected) numSelected++;

				$option.remove();

				// determine label
				if(formatName.length) label = page[formatName];
				if(!label.length) label = page[labelFieldName];
				if(!label.length) label = page.name;

				// create <option>
				$option = $("<option value='" + page.id + "'>" + label + "</option>");
				if(selected) $option.attr('selected', 'selected');

				// add the <option> to the <select>
				$select2.append($option);
			}

			// lead with a blank option
			if(!$select2.attr('multiple')) {
				$blankOption = $("<option value=''>&nbsp;</option>");
				if(!numSelected) $blankOption.attr('selected', 'selected');
				$select2.prepend($blankOption);
			}
			$select2.children(".option-tbd").remove();
			if(changed || $select2.closest('.InputfieldAsmSelect').length) {
				// always trigger change event when asmSelect because that’s what forces it to redraw
				$select2.change();
			}
		});
	}

	/**
	 * Initialize an InputfieldPage dependent select from its input.findPagesSelector element
	 * 
	 * The $t argument is one of these: 
	 * <input type='hidden' class='findPagesSelector' data-formatname='' data-label='title' value='parent=page.field'>
	 *     
	 * @param $t 
	 * 
	 */
	function initSelector($t) {
		var selector = $t.val();

		// if there is no "=page." present in the selector, then this can't be a dependent select
		if(selector.indexOf('=page.') == -1) return;

		var labelFieldName = $t.attr('data-label');
		var formatName = $t.attr('data-formatname');

		if(!labelFieldName.length) $labelFieldName = 'name';

		var $wrap = $t.parents(".InputfieldPage");
		var $select2 = $('select#' + $wrap.attr('id').replace(/^wrap_/, '')); // dependent select

		if($select2.length < 1) return;

		var parts = selector.match(/(=page.[_a-zA-Z0-9]+)/g);

		for(var n = 0; n < parts.length; n++) {

			var part = parts[n]; // page matching part of the selector
			var name = part.replace('=page.', '');
			var $select1 = $('#Inputfield_' + name);

			if($select1.length < 1) continue;

			// monitor changes to the dependency field
			$select1.change(function() {
				selectChanged($select1, $select2, selector, formatName, labelFieldName, part, true)
			});

			// determine if select2 needs to be populated
			if($select1.val() && !$select2.val() && $select2.children('option[value!=""]').length < 1) {
				// no options in $select2 select but primary $select1 has something selected…
				// it’s possible there are just no selectable options, but it’s also possible that 
				// this is a new page or the selects are newly added, so let’s find out…
				setTimeout(function() {
					selectChanged($select1, $select2, selector, formatName, labelFieldName, part, false)
				}, 100);
			}
		}
	}
	
	// find all dependent selects and initalize them
	$inputfieldPage.find('.findPagesSelector').each(function() {
		initSelector($(this));
	});
}


/**
 * Document ready
 * 
 */
$(document).ready(function() {
	$(".InputfieldPage").each(function() {
		initInputfieldPage($(this));
	});
	$(document).on("reloaded", ".InputfieldPage", function() {
		initInputfieldPage($(this));
	});
}); 
