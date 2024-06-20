function InputfieldTextTags($parent) {

	if(typeof $parent === "undefined") $parent = jQuery('.InputfieldForm');
	
	var pluginsMulti = [ 'remove_button', 'drag_drop' ];
	var pluginsSingle = []; 
	
	var defaults = {
		delimiter: ' ',
		persist: true, // If false, items created by the user will not show up as available options once they are unselected.
		submitOnReturn: false,
		openOnFocus: true, // Show the dropdown immediately when the control receives focus.
		closeAfterSelect: true, // If true, the dropdown will be closed after a selection is made.
		copyClassesToDropdown: false,
		createOnBlur: false, // If true, when user exits the field (clicks outside of input), a new option is created and selected (if create setting is enabled).
		selectOnTab: true, // If true, the tab key will choose the currently selected item.
		maxItems: null, // The max number of items the user can select. 1 makes the control mono-selection, null allows an unlimited number of items.
		create: function(input) {
			return {
				value: input,
				text: input
			}
		}
	};

	// get the 'render' options for selectize
	function getRenderOptions(addLabel) {
		return {
			item: function(item, escape) {
				if(typeof item.label === "undefined" || !item.label.length) item.label = item.value;
				return '<div>' + escape(item.label) + '</div>';
			},
			option: function(item, escape) {
				if(typeof item.label === "undefined" || !item.label.length) item.label = item.value;
				return '<div>' + escape(item.label) + '</div>';
			},
			option_create: function(data, escape) {
				return '<div class="create">' + addLabel + ' <strong>' + escape(data.input) + '</strong>&hellip;</div>';
			}
		}
	}
	
	// initialize input where all tags are input by the user, there are no predefined selectable tags
	function initInput($input) {
		var o = JSON.parse($input.attr('data-opts'));
		var options = defaults;
		options.delimiter = o.delimiter;
		options.closeAfterSelect = o.closeAfterSelect;
		options.createOnBlur = o.createOnBlur; 
		options.persist = false;
		options.maxItems = (o.maxItems > 0 ? o.maxItems : null);
		options.plugins = (o.maxItems === 1 ? pluginsSingle : pluginsMulti);
		options.render = getRenderOptions(o.addLabel);
		$input.selectize(options);
	}

	// initialize select with predefined selectable tags, optionally with user-entered as well
	function initSelect($select) {
		var o = JSON.parse($select.attr('data-opts'));
		var cfgName = typeof o.cfgName === "undefined" ? '' : o.cfgName;
		var tags = cfgName.length ? ProcessWire.config[cfgName] : o.tags;
		var tagsList = [];
		var n = 0;
		var isPageField = $select.closest('.InputfieldPage').length > 0;

		for(var tag in tags) {
			var label = tags[tag];
			tagsList[n] = { value: tag, label: label };
			n++;
		}

		var options = jQuery.extend(defaults, {
			allowUserTags: o.allowUserTags,
			delimiter: o.delimiter,
			closeAfterSelect: o.closeAfterSelect,
			createOnBlur: o.createOnBlur,
			maxItems: (o.maxItems > 0 ? o.maxItems : null),
			plugins: (o.maxItems === 1 ? pluginsSingle : pluginsMulti),
			persist: true,
			valueField: 'value',
			labelField: 'label',
			searchField: [ 'value', 'label' ],
			'options': tagsList,
			createFilter: function(input) {
				if(o.allowUserTags) return true;
				var allow = false;
				for(var n = 0; n < tags.length; n++) {
					if(typeof tags[input] !== "undefined") {
						allow = true;
						break;
					}
				}
				return allow;
			},
			render: getRenderOptions(o.addLabel)
			/*
			onDropdownOpen: function($dropdown) {
				$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 100);
			},
			onDropdownClose: function($dropdown) {
				$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 'auto');
			},
			*/
		});
		
		if(o.tagsUrl.length) {
			options.load = function(query, callback) {
				if(!query.length) return callback();
				var tagsUrl = o.tagsUrl.replace('{q}', encodeURIComponent(query));
				Inputfields.startSpinner($select);
				jQuery.ajax({
					url: tagsUrl,
					type: 'GET',
					error: function() {
						Inputfields.stopSpinner($select);
						callback(); 
					},
					success: function(items) { 
						for(var n = 0; n < items.length; n++) {
							var item = items[n];
							if(typeof item === "object") {
								if(typeof item.value === 'number' && isPageField) {
									item.value = '_' + item.value.toString()
								} 
								if(typeof item.label === "undefined") {
									item.label = item.value;
									items[n] = item;
								}
							} else {
								var value, label;
								if(isPageField && (typeof item === 'number' || item.match(/^\d+$/))) {
									value = '_' + item;
									label = '' + item
								} else {
									value = item;
									label = item;
								}
								items[n] = { value: value, label: label };
							}
						}
						Inputfields.stopSpinner($select);
						callback(items);
					}
				});
			}
		}

		$select.selectize(options);
	}

	var $inputs = jQuery('.InputfieldTextTagsInput:not(.selectized)', $parent);
	var $selects = jQuery('.InputfieldTextTagsSelect:not(.selectized)', $parent);

	if($inputs.length) {
		$inputs.each(function() {
			$input = jQuery(this);
			initInput($input);
		});
	}

	if($selects.length) {
		$selects.each(function() {
			var $select = jQuery(this);
			initSelect($select);
		}); 
	}
}

jQuery(document).ready(function($) {
	InputfieldTextTags();
	$(document).on('reloaded', '.InputfieldTextTags, .InputfieldPage', function() {
		InputfieldTextTags($(this)); 
	}); 
}); 
