function InputfieldTextTags($parent) {

	if(typeof $parent === "undefined") $parent = $('.InputfieldForm');
	
	var $inputs = jQuery('.InputfieldTextTagsInput:not(.selectized)', $parent);
	var $selects = jQuery('.InputfieldTextTagsSelect:not(.selectized)', $parent);
	
	if($inputs.length) {
		$inputs.selectize({
			plugins: ['remove_button', 'drag_drop'],
			delimiter: ' ',
			persist: false,
			createOnBlur: true,
			submitOnReturn: false,
			create: function(input) {
				return {
					value: input,
					text: input
				}
			}
		});
	}

	if($selects.length) {
		$selects.each(function() {
			var $select = $(this);
			var configName = $select.attr('data-cfgname');
			var allowUserTags = $select.hasClass('InputfieldTextTagsSelectOnly') ? false : true;
			var tags = [];
			var tagsList = [];
			var n = 0;
			if(configName.length) {
				tags = ProcessWire.config[configName];
			} else {
				tags = $select.attr('data-tags'); 
				tags = JSON.parse(tags);
			}
			for(var tag in tags) {
				var label = tags[tag];
				tagsList[n] = { value: tag, label: label };
				n++;
			}
			$select.selectize({
				plugins: ['remove_button', 'drag_drop'],
				delimiter: ' ',
				persist: true,
				submitOnReturn: false,
				closeAfterSelect: true,
				copyClassesToDropdown: false, 
				createOnBlur: true,
				maxItems: null,
				valueField: 'value',
				labelField: 'label',
				searchField: ['value', 'label'],
				options: tagsList,
				create: function(input) {
					return {
						value: input,
						text: input
					}
				},
				createFilter: function(input) {
					if(allowUserTags) return true;
					allow = false;
					for(var n = 0; n < tags.length; n++) {
						if(typeof tagsList[input] !== "undefined") {
							allow = true;
							break;
						}
					}
					return allow;
				},
				/*
				onDropdownOpen: function($dropdown) {
					$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 100);
				},
				onDropdownClose: function($dropdown) {
					$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 'auto');
				},
				*/
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
			});
		}); 
	}
}

jQuery(document).ready(function($) {
	InputfieldTextTags();
	$(document).on('reloaded', '.InputfieldTextTags', function() { InputfieldTextTags($(this)); }); 
}); 