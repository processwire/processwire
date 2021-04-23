function InputfieldTextTags($parent) {

	if(typeof $parent === "undefined") $parent = $('.InputfieldForm');
	
	var $inputs = jQuery('.InputfieldTextTagsInput:not(.selectized)', $parent);
	var $selects = jQuery('.InputfieldTextTagsSelect:not(.selectized)', $parent);
	
	var defaults = {
		plugins: [ 'remove_button', 'drag_drop' ],
		delimiter: ' ',
		persist: true, // If false, items created by the user will not show up as available options once they are unselected.
		submitOnReturn: false,
		openOnFocus: true, // Show the dropdown immediately when the control receives focus.
		closeAfterSelect: true, // If true, the dropdown will be closed after a selection is made.
		copyClassesToDropdown: false,
		createOnBlur: true, // If true, when user exits the field (clicks outside of input), a new option is created and selected (if create setting is enabled).
		selectOnTab: true, // If true, the tab key will choose the currently selected item.
		maxItems: null, // The max number of items the user can select. 1 makes the control mono-selection, null allows an unlimited number of items.
		create: function(input) {
			return {
				value: input,
				text: input
			}
		}
	};
	
	if($inputs.length) {
		$inputs.each(function() {
			$input = $(this);
			var o = JSON.parse($input.attr('data-opts'));
			var options = defaults;
			options.delimiter = o.delimiter;
			options.closeAfterSelect = o.closeAfterSelect;
			options.persist = false;
			$input.selectize(options);
		});
	}

	if($selects.length) {
		$selects.each(function() {
			var $select = $(this);
			var o = JSON.parse($select.attr('data-opts'));
			var cfgName = typeof o.cfgName === "undefined" ? '' : o.cfgName;
			var tags = cfgName.length ? ProcessWire.config[cfgName] : o.tags;
			var tagsList = [];
			var n = 0;
			
			for(var tag in tags) {
				var label = tags[tag];
				tagsList[n] = { value: tag, label: label };
				n++;
			}
			
			var options = jQuery.extend(defaults, {
				allowUserTags: o.allowUserTags,
				delimiter: o.delimiter,
				closeAfterSelect: o.closeAfterSelect,
				persist: true,
				valueField: 'value',
				labelField: 'label',
				searchField: [ 'value', 'label' ],
				options: tagsList,
				createFilter: function(input) {
					if(o.allowUserTags) return true;
					allow = false;
					for(var n = 0; n < tags.length; n++) {
						if(typeof tags[input] !== "undefined") {
							allow = true;
							break;
						}
					}
					return allow;
				},
				render: {
					item: function(item, escape) {
						if(typeof item.label === "undefined" || !item.label.length) item.label = item.value;
						return '<div>' + escape(item.label) + '</div>';
					},
					option: function(item, escape) {
						if(typeof item.label === "undefined" || !item.label.length) item.label = item.value;
						return '<div>' + escape(item.label) + '</div>';
					}
				}
				/*
				onDropdownOpen: function($dropdown) {
					$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 100);
				},
				onDropdownClose: function($dropdown) {
					$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 'auto');
				},
				*/
			}); 
				
			$select.selectize(options);
		}); 
	}
}

jQuery(document).ready(function($) {
	InputfieldTextTags();
	$(document).on('reloaded', '.InputfieldTextTags', function() { InputfieldTextTags($(this)); }); 
}); 