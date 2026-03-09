/**
 * ProcessWire Page Auto Completion select widget
 *
 * This Inputfield connects the jQuery UI Autocomplete widget with the ProcessWire ProcessPageSearch AJAX API.
 *
 * ProcessWire 3.x (development), Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */
var InputfieldPageAutocomplete = {

	/**
	 * Initialize the given InputfieldPageListSelectMultiple OL by making it sortable
	 *
	 */
	init: function(id, url, labelField, searchField, operator) {
		
		var $value = $('#' + id);
		var $ol = $('#' + id + '_items'); 
		var $input = $('#' + id + '_input'); 
		var $icon = $input.parent().find(".InputfieldPageAutocompleteStatus");
		var $note = $input.parent().find(".InputfieldPageAutocompleteNote"); 
		var numAdded = 0; // counter that keeps track of quantity items added
		var numFound = 0; // indicating number of pages matching during last ajax request
		var disableChars = $input.attr('data-disablechars'); 
		var noList = $input.hasClass('no_list');
		
		function hasDisableChar(str) {
			if(!disableChars || !disableChars.length) return false;
			var disable = false;
			for(var n = 0; n < disableChars.length; n++) {
				if(str.indexOf(disableChars[n]) > -1) {
					disable = true;
					break;
				}
			}
			return disable;
		}
		
		InputfieldPageAutocomplete.setIconPosition($icon, 'left');
		
		if(noList) {
			// specific to single-item autocompletes, where there is no separate "selected" list
			
			$input.attr('data-selectedLabel', $input.val());
			var $remove = $input.siblings('.InputfieldPageAutocompleteRemove');
			InputfieldPageAutocomplete.setIconPosition($remove, 'right');
			
			$remove.on('click', function() {
				$value.val('').trigger('change');
				$input.val('').attr('placeholder', '').attr('data-selectedLabel', '').trigger('change').trigger('focus');
				$input.trigger('keydown');
			});
			
			$input.on('change', function() {
				if($(this).val().length == 0) {
					$remove.hide();
				} else {
					$remove.show();
				}
			});
			
			$input.on('focus', function() {
				var val = $value.val();
				if(!val.length) return;
				if(hasDisableChar(val)) return;
				if($(this).hasClass('added_item')) return;
				$(this).attr('placeholder', $(this).attr('data-selectedLabel'));
				$(this).val('');
			}).on('blur', function() {
				setTimeout(function() { }, 200);
			});
		}
		
		
		$icon.on('click', function() { $input.trigger('focus'); });
		$icon.attr('data-class', $icon.attr('class')); 

		function isAddAllowed() {
			var allowed = $('#_' + id.replace('Inputfield_', '') + '_add_items').length > 0;
			return allowed;
		}

		$input.one('focus', function() {
			InputfieldPageAutocomplete.updateIcons($input.closest('.InputfieldContent'));
			$input.autocomplete({
				minLength: 2,
				source: function(request, response) {
					var term = request.term;

					if(hasDisableChar(term)) {
						response([]);
						return;
					}

					$icon.attr('class', 'fa fa-fw fa-spin fa-spinner');

					if($input.hasClass('and_words') && term.indexOf(' ') > 0) {
						// AND words mode
						term = term.replace(/\s+/, ',');
					}
					term = encodeURIComponent(term);
					var ajaxURL = url + '&' + searchField + operator + term;

					$.getJSON(ajaxURL, function(data) {

						$icon.attr('class', $icon.attr('data-class'));
						numFound = data.total;

						if(data.total > 0) {
							$icon.attr('class', 'fa fa-fw fa-angle-double-down');

						} else if(isAddAllowed()) {
							$icon.attr('class', 'fa fa-fw fa-plus-circle');
							$note.show();

						} else {
							$icon.attr('class', 'fa fa-fw fa-frown-o');
						}

						response($.map(data.matches, function(item) {
							return {
								label: item[labelField],
								value: item[labelField],
								page_id: item.id
							}
						}));
					});
				},
				select: function(event, ui) {
					if(!ui.item) return;
					var $t = $(this);
					if($t.hasClass('no_list')) {
						$t.val(ui.item.label).trigger('change');
						$t.attr('data-selectedLabel', ui.item.label);
						$t.closest('.InputfieldPageAutocomplete')
							.find('.InputfieldPageAutocompleteData').val(ui.item.page_id).trigger('change');
						$t.trigger('blur');
					} else {
						InputfieldPageAutocomplete.pageSelected($ol, ui.item);
						$t.val('').trigger('focus');
					}
					event.stopPropagation();
					return false;
				},
				open: function(event, ui) {
					var $items = $('.ui-autocomplete.ui-front');
					if(!$items.find('a').length) {
						// newer jQuery UI versions use <div> rather than <a>, but we prefer to keep <a>
						$items.find('div').each(function() {
							$(this).parent().html('<a>' + $(this).html() + '</a>');
						});
					}
				}

			}).on('blur', function() {
				var $input = $(this);
				//if(!$input.val().length) $input.val('');
				$icon.attr('class', $icon.attr('data-class'));
				$note.hide();
				if($input.hasClass('no_list')) {
					if($value.val().length || $input.val().length) {
						if($input.hasClass('allow_any') || $input.hasClass('added_item')) {
							// allow value to remain
						} else {
							$input.val($input.attr('data-selectedLabel')).attr('placeholder', '');
						}
					} else {
						$input.val('').attr('placeholder', '').attr('data-selectedLabel', '');
					}
				}
				if($input.hasClass('focus-after-blur')) {
					$input.removeClass('focus-after-blur');
					setTimeout(function() {
						$input.trigger('focus');
					}, 250);
				}

			}).on('keyup', function() {
				$icon.attr('class', $icon.attr('data-class'));

			}).on('keydown', function(event) {
				var $addNote;
				if(event.keyCode == 13) {
					// prevents enter from submitting the form
					event.preventDefault();
					// instead we add the text entered as a new item
					// if there is an .InputfieldPageAdd sibling, which indicates support for this
					if(isAddAllowed()) {
						if($input.val().trim().length < 1) {
							$input.trigger('blur');
							return false;
						}
						numAdded++;
						// new items have a negative page_id
						var page = { page_id: (-1 * numAdded), label: $input.val() };
						// add it to the list
						if(noList) {
							// adding new item while using input as the label
							$value.val(page.page_id);
							$("#_" + id.replace('Inputfield_', '') + '_add_items').val(page.label);
							$input.addClass('added_item').trigger('blur');
							$addNote = $note.siblings(".InputfieldPageAutocompleteNoteAdd");
							if(!$addNote.length) {
								$addNote = $("<div class='notes InputfieldPageAutocompleteNote InputfieldPageAutocompleteNoteAdd'></div>");
								$note.after($addNote);
							}
							$addNote.text($note.attr('data-adding') + ' ' + page.label);
							$addNote.show();

						} else {
							// adding new item to list
							InputfieldPageAutocomplete.pageSelected($ol, page);
							$input.val('').trigger('blur').trigger('focus');
						}
						$note.hide();
					} else {
						$(this).addClass('focus-after-blur').trigger('blur');
					}
					return false;
				}

				if(numAdded && noList) {
					// some other key after an item already added, so remove added item info for potential new one
					$addNote = $note.siblings(".InputfieldPageAutocompleteNoteAdd");
					var $addText = $("#_" + id.replace('Inputfield_', '') + '_add_items');
					if($addNote.length && $addText.val() != $(this).val()) {
						// added value has changed
						$addNote.remove();
						$value.val('');
						$addText.val('');
						$("#_" + id.replace('Inputfield_', '') + '_add_items').val('');
						numAdded--;
					}
				}
			});
		});

		var makeSortable = function($ol) { 
			$ol.sortable({
				// items: '.InputfieldPageListSelectMultiple ol > li',
				axis: 'y',
				update: function(e, data) {
					InputfieldPageAutocomplete.rebuildInput($(this));
					InputfieldPageAutocomplete.triggerChange($ol)
					$ol.trigger('sorted', [ data.item ]);
				},
				start: function(e, data) {
					data.item.addClass('ui-state-highlight');
				},
				stop: function(e, data) {
					data.item.removeClass('ui-state-highlight');
				}
			}); 
			$ol.addClass('InputfieldPageAutocompleteSortable'); 
		};

		$('#' + $ol.attr('id')).on('mouseover', '>li', function() { 
			$(this).removeClass('ui-state-default').addClass('ui-state-hover'); 
			makeSortable($ol); 
		}).on('mouseout', '>li', function() {
			$(this).removeClass('ui-state-hover').addClass('ui-state-default'); 
		}); 

	},
	
	/**
	 * Same as init() but only requires the Inputfield where autocomplete lives
	 *
	 * @param $inputfield
	 *
	 */
	initFromInputfield: function($inputfield) {
		var $a = $inputfield.find(".InputfieldPageAutocompleteData");
		if(!$a.length) return;
		if($a.hasClass('InputfieldPageAutocompleteInit')) return;
		InputfieldPageAutocomplete.init(
			$a.attr('id'),
			$a.attr('data-url'),
			$a.attr('data-label'),
			$a.attr('data-search'),
			$a.attr('data-operator')
		);
		$a.addClass('InputfieldPageAutocompleteInit');
	},

	/**
	 * Set position of icon within parent element
	 * 
	 * @param $icon
	 * @param side Either 'left' or 'right'
	 * 
	 */
	setIconPosition: function($icon, side) {
		if($icon.hasClass('PageAutocompleteIconHidden')) {
			$icon.removeClass('PageAutocompleteIconHidden').show();
		}
		var iconHeight = $icon.height();
		if(iconHeight) {
			var pHeight = $icon.parent().height();
			var iconTop = ((pHeight - iconHeight) / 2);
			$icon.css('top', iconTop + 'px');
			if(side == 'left') {
				$icon.css('left', (iconTop / 2) + 'px');
			} else if(side == 'right') {
				$icon.css('right', (iconTop / 4) + 'px');
			}
		} else {
			// icon is not visible (in a tab or collapsed field), we'll leave it alone
			$icon.hide().addClass('PageAutocompleteIconHidden');
		}
	},

	/**
	 * Callback function executed when a page is selected from PageList
	 *
	 */
	pageSelected: function($ol, page) {

		var dup = false;
	
		$ol.children('li:not(.itemTemplate)').each(function() {
			var v = parseInt($(this).children('.itemValue').text());	
			if(v == page.page_id) dup = $(this);
		});
		
		var $inputText = $('#' + $ol.attr('data-id') + '_input');
		$inputText.trigger('blur');

		if(dup) {
			dup.effect('highlight'); 
			return;
		}
		
		var $li = $ol.children(".itemTemplate").clone();

		$li.removeClass("itemTemplate"); 
		$li.children('.itemValue').text(page.page_id); 
		$li.children('.itemLabel').text(page.label); 

		$ol.append($li);

		InputfieldPageAutocomplete.rebuildInput($ol); 
		InputfieldPageAutocomplete.triggerChange($ol);
	},

	/**
	 * Trigger change event
	 * 
	 * @param $item Any element within the autocomplete Inputfield
	 * 
	 */
	triggerChange: function($item) {
		var $input;
		if($item.hasClass('InputfieldPageAutocompleteData')) {
			$input = $item;
		} else {
			if(!$item.hasClass('Inputfield')) $item = $item.closest('.Inputfield');
			$input = $item.find('.InputfieldPageAutocompleteData')
		}
		$input.trigger('change');
	}, 

	/**
	 * Rebuild the CSV values present in the hidden input[text] field
	 *
	 */
	rebuildInput: function($ol) {
		var id = $ol.attr('data-id');
		var name = $ol.attr('data-name');
		//id = id.substring(0, id.lastIndexOf('_')); 
		var $input = $('#' + id);
		var value = '';
		var addValue = '';
		var max = parseInt($input.attr('data-max'));

		var $children = $ol.children(':not(.itemTemplate)');
		if(max > 0 && $children.length > max) { 
			while($children.length > max) $children = $children.slice(1); 
			$ol.children(':not(.itemTemplate)').replaceWith($children);
		}
	
		$children.each(function() {
			var v = parseInt($(this).children('.itemValue').text());
			if(v === 0) return;
			if(value.length) value += ',';
			value += v;
			if(v < 0) {
				addValue += $(this).children('.itemLabel').text() + "\n";
			}
		}); 
		$input.val(value);

		var $addItems = $('#_' + name + '_add_items'); 
		if($addItems.length > 0) $addItems.val(addValue);
	},

	updateIcons: function($target) {
		// update positions of icons that previously were not calculable
		var $icons = $target.find('.InputfieldPageAutocompleteStatus');
		$icons.each(function() {
			InputfieldPageAutocomplete.setIconPosition($(this), 'left');
		});
		$icons = $target.find('.InputfieldPageAutocompleteRemove');
		$icons.each(function() {
			InputfieldPageAutocomplete.setIconPosition($(this), 'right');
		}); 
	}

}; 

$(document).ready(function() {
	
	$(".InputfieldPageAutocomplete").each(function() {
		InputfieldPageAutocomplete.initFromInputfield($(this));
	});
	
	$(document).on('reloaded', '.InputfieldPageAutocomplete, .InputfieldPage', function() {
		InputfieldPageAutocomplete.initFromInputfield($(this));
	});

	$(document).on('click', '.InputfieldPageAutocomplete ol a.itemRemove', function() {
		// $(".InputfieldPageAutocomplete ol").on('click', 'a.itemRemove', function() {
		var $li = $(this).parent(); 
		var $ol = $li.parent(); 
		var id = $li.children(".itemValue").text();
		$li.remove();
		InputfieldPageAutocomplete.rebuildInput($ol);
		InputfieldPageAutocomplete.triggerChange($ol);
		return false; 
	});
	
	$(document).on('wiretabclick', function(a, $tab) {
		// update positions of icons that previously were not calculable
		InputfieldPageAutocomplete.updateIcons($tab); 
	});
}); 
