/*
 * Alternate Select Multiple (asmSelect) 2.0 - jQuery Plugin
 * https://processwire.com
 * 
 * Copyright (c) 2009-2019 by Ryan Cramer
 * 
 * Licensed under the MIT license. 
 *
 *
 */
(function($) {

	$.fn.asmSelect = function(customOptions) {

		var options = {

			// General settings
			listType: 'ol',                 // Ordered list 'ol', or unordered list 'ul'
			sortable: false,                // Should the list be sortable?
			addable: true,                  // Can items be added to selection?
			deletable: true,                // Can items be removed from selection? 
			highlight: false,               // Use the highlight feature? 
			fieldset: false,                // Use fieldset support? (for PW Fieldset types)
			animate: false,                 // Animate the the adding/removing of items in the list?
			addItemTarget: 'bottom',        // Where to place new selected items in list: top or bottom
			hideWhenAdded: false,           // Hide the option when added to the list? works only in FF
			hideWhenEmpty: false,           // Hide the <select> when there are no items available to select? 
			debugMode: false,               // Debug mode keeps original select visible 
			jQueryUI: true,                 // use jQuery UI?  
			hideDeleted: true,              // Hide items when deleted. If false, items remain but are marked for deletion
			deletedOpacity: 0.5,            // opacity of deleted item, set to 1.0 to disable opacity adjustment (applicable only if hideDeleted=true)
			deletedPrepend: '-',            // Deleted item values are prepended with this character in the form submission (applicable only if hideDeleted=true)
			useSelect2: true,               // use a separate select for unselected child options? (required by Safari)
			removeWhenAdded: false,         // no longer used (was a separate select that contained removed items)
			highlightTag: '<span></span>',  // Tag to use for highlight element highlight option is in use

			// Labels
			sortLabel: '<span class="asmIcon asmIconSort">&#8597;</span>', // sortable handle/icon
			removeLabel: '<span class="asmIcon asmIconRemove">&times;</span>', // Text used in the "remove" link
			highlightAddedLabel: 'Added: ',             // Text that precedes highlight of added item
			highlightRemovedLabel: 'Removed: ',         // Text that precedes highlight of removed item

			// Classes
			containerClass: 'asmContainer',             // Class for container that wraps this widget
			selectClass: 'asmSelect',                   // Class for the newly created <select>
			optionDisabledClass: 'asmOptionDisabled',   // Class for items that are already selected / disabled
			listClass: 'asmList',                       // Class for the list ($ol)
			listSortableClass: 'asmListSortable',       // Another class given to the list when it is sortable
			listItemClass: 'asmListItem',               // Class for the <li> list items
			listItemLabelClass: 'asmListItemLabel',     // Class for the label text that appears in list items
			listItemDescClass: 'asmListItemDesc',       // Class for optional description text, set a data-desc attribute on the <option> to use it. May contain HTML.
			listItemStatusClass: 'asmListItemStatus',   // Class for optional status text, set a data-status attribute on the <option> to use it. May contain HTML.
			listItemHandleClass: 'asmListItemHandle',   // Class for sort handle
			removeClass: 'asmListItemRemove',           // Class given to the "remove" link
			editClass: 'asmListItemEdit',
			highlightClass: 'asmHighlight',             // Class given to the highlight <span>
			deletedClass: 'asmListItemDeleted',
		
			// Edit option settings
			editLink: '',  // Optional URL options can link to with tag {value} replaced by option value, i.e. /path/to/page/edit?id={$value}
			editLabel: '<span class="ui-icon ui-icon-extlink"></span>', // Text used in the "edit" link (if editLink is populated)
			editLinkOnlySelected: true,  // When true, edit link only appears for items that were already selected
			editLinkModal: true,  // Whether the edit link (if used) should be modal or "longclick" for longclick modal only
			editLinkButtonSelector: 'form button.ui-button:visible', // button selector for finding buttons that should become modal window buttons
	
			// Using parent and child options where parent is selected to reveal child options
			// -------------------------------------------------------------------------------
			// 1. Parent option(s) must have a class attribute of 'asmParent'. While optional, I also recommend the label have an 
			//    ellipsis or something to indicate it is a parent:
			// 
			//    <option value='foo' class='asmParent'>Foo …</option> 
			// 	
			// 2. Child options must come immediately after parent and have a 'data-asmParent' attribute containing 'value' attribute 
			//    of parent. While optional, I also recommend the child option include both parent and child label so that the 
			//    relationship remains clear even after selection, i.e. “Foo > Bar” or “Foo: Bar” or similar:
			// 	
			//    <option value='bar' data-asmParent='foo'>Foo: Bar</option>
			// 	
			// Please Note:
			// • Selecting a parent option does not modify the value of the selection, it only reveals more selectable options.
			// • Up to two levels of parent/child options are supported (more levels may also work but have not been attempted).
			// • The options below define the settings for parent/child options (most will want to leave as-is):
			
			optionParentClass: 'asmParent',         // Class you will assign to a parent item that has one or more child items
			optionParentIcon: '⬇',                 // UTF-8 down-pointing arrow icon added to select parent options, indicating more options to select
			optionChildAttr: 'data-asmParent',      // Attribute you will add to child items with its value pointing to the option.asmParent’s value attribute
			optionParentOpenClass: 'asmParentOpen', // Assigned automatically: Class for a parent option that has its children visible 
			optionChildClass: 'asmChild',           // Assigned automatically: Class for an option that has a parent option 
			optionChildIndent: '&nbsp;&nbsp; ',     // Indent applied to child options
			optionParentLabel: '← Click again to make selection'
			
			};

		$.extend(options, customOptions); 

		return this.each(function(index) {

			var $original = $(this);                // the original select multiple
			var $container;                         // a container that is wrapped around our widget
			var $select;                            // the new select we have created
			var $select2 = null;                    // an extra hidden select for holding non-selected, non-visible options
			var $ol;                                // the list that we are manipulating
			var buildingSelect = false;             // is the new select being constructed right now?
			var ieClick = false;                    // in IE, has a click event occurred? ignore if not
			var ignoreOriginalChangeEvent = false;  // originalChangeEvent bypassed when this is true
			var fieldsetCloseItems = {};            // list-item <li> elements in the $select representing '_END' fieldset options
			var msie = 0;                           // contains the MSIE browser version when MSIE is detected (primarily for MSIE 6 & 7)
			var $highlightSpan = null;              // active highlight span (is removed when option selected)

			/**
			 * Initialize an asmSelect
			 * 
			 */
			function init() {

				// initialize the alternate select multiple
				if(options.deletable && !options.addable) options.hideDeleted = false;
			
				// identify which items were already selected in the original 
				$original.find('option[selected]').addClass('asmOriginalSelected');

				// this loop ensures uniqueness, in case of existing asmSelects placed by ajax (1.0.3)
				while($("#" + options.containerClass + index).length > 0) index++; 

				$select = $("<select></select>")
					.addClass(options.selectClass)
					.addClass($original.attr('class'))
					.attr('name', options.selectClass + index)
					.attr('id', options.selectClass + index); 
				if(!options.addable) $select.hide();

				$selectRemoved = $("<select></select>"); 

				$ol = $("<" + options.listType + "></" + options.listType + ">")
					.addClass(options.listClass)
					.attr('id', options.listClass + index); 

				$container = $("<div></div>")
					.addClass(options.containerClass) 
					.attr('id', options.containerClass + index); 

				buildSelect();

				$select.on('change', selectChangeEvent)
					.on('click', selectClickEvent); 

				$original.on('change', originalChangeEvent)
					.wrap($container).before($select).before($ol);

				if(options.sortable) makeSortable();

				/*
				if(typeof $.browser != "undefined" && typeof $.browser.msie != "undefined") {
					msie = $.browser.msie ? $.browser.version : 0;
				}
				if(msie > 0 && msie < 8) $ol.css('display', 'inline-block'); // Thanks Matthew Hutton
				*/

				if(options.fieldset) {
					setupFieldsets();
					findFieldsetCloseItems($original);
					$original.on('rebuild', function(e) { 
						console.log('asmSelect REBUILD');
						findFieldsetCloseItems($(this)); 
					});
				}

				$original.trigger('init'); 
				
				if(options.editLinkModal === 'longclick') {
					$ol.on('longclick', 'a.asmEditLinkModalLongclick', clickEditLink);
				}
			
				// if select2 exists, give it the appropriate attributes, hide it, and place it after the interactive select
				if($select2 && $select2.length) {
					$select2.addClass($select.attr('class')).removeClass('asmSelect').attr('id', $select.attr('id') + '-helper').hide();
					$select.after($select2);
				}
			}

			/**
			 * Make any items in the selected list sortable
			 * 
			 * Requires jQuery UI sortables, draggables, droppables
			 * 
			 */
			function makeSortable() {
				
				var fieldsetItems = [];
				
				var sortableUpdate = function($ul, e, data) {
					var $option = $('#' + data.item.attr('rel'));
					var updatedOptionId = $option.attr('id');

					$ul.children("li").each(function(n) {
						$option = $('#' + $(this).attr('rel'));
						$original.append($option);
					});

					if(updatedOptionId) {
						triggerOriginalChange(updatedOptionId, 'sort');
					}
				}

				$ol.sortable({
					items: 'li.' + options.listItemClass,
					axis: 'y',
					cancel: 'a.asmEditLinkModalLongclick',
					update: function(e, data) {
						if(data.item.hasClass('asmFieldsetStart')) return;
						sortableUpdate(jQuery(this), e, data);
						$ol.trigger('sorted', [ data.item ]);
					},
					start: function(e, data) {
						if(options.jQueryUI) data.item.addClass('ui-state-highlight'); 
						if(data.item.hasClass('asmFieldsetStart')) {
							var $next = data.item;
							var stopName = data.item.find('.' + options.listItemLabelClass).text() + '_END';
							do {
								if($next.find('.' + options.listItemLabelClass).text() == stopName) break;
								$next = $next.next('li');
								if($next.length && !$next.hasClass('ui-sortable-placeholder')) {
									$next.fadeTo(50, 0.7).slideUp('fast');
									fieldsetItems.push($next); 
								}
							} while($next.length);
						}
					},
					stop: function(e, data) {
						if(options.jQueryUI) data.item.removeClass('ui-state-highlight'); 
						if(data.item.hasClass('asmFieldsetStart')) {
							var $lastItem = data.item;
							
							for(var n = 0; n < fieldsetItems.length; n++) {
								var $item = fieldsetItems[n];
								$lastItem.after($item); 
								$lastItem = $item;
								$item.slideDown('fast').fadeTo('fast', 1.0); 
							}
							fieldsetItems = [];
							setupFieldsets();
							sortableUpdate(jQuery(this), e, data);
						} else {
							setupFieldsets();
						}
					}

				}).addClass(options.listSortableClass); 
			}

			/**
			 * Event called when an option has been selected on the $select we created
			 * 
			 * @param e
			 * @returns {boolean}
			 * 
			 */
			function selectChangeEvent(e) {
				
				// check to make sure it's not an IE screwup, and add it to the list
				if(msie > 0 && msie < 7 && !ieClick) return;
			
				var $select = $(this);
				var $option = $select.children("option:selected");
				
				if($highlightSpan && $highlightSpan.length) $highlightSpan.remove();
			
				// if item is not selectable then do not proceed
				if(!$option.attr('value').length) return false;
				
				if($option.hasClass(options.optionParentClass)) {
					// an option with asmParent class was selected
					parentOptionSelected($select, $option);
					e.stopPropagation();
					return false;
				}
		
				// add the item
				var id = $option.slice(0,1).attr('rel'); 
				addListItem(id); 	
				ieClick = false; 
				triggerOriginalChange(id, 'add'); // for use by user-defined callbacks
			
				if($option.hasClass(options.optionChildClass)) {
					// if an option.asmChild was selected, keep the parent selected afterwards		
					childOptionSelected($select, $option);
				}
			}

			/**
			 * Called by selectChangeEvent() when an option.asmParent is selected to show/hide child options
			 * 
			 * Applicable only if parent/child options are in use
			 * 
			 * @param $select
			 * @param $option
			 * 
			 */
			function parentOptionSelected($select, $option) {
				
				var $sel = $select;
				var isOpenParent = $option.hasClass(options.optionParentOpenClass); // is option an asmParent option that is open?
				
				if(options.useSelect2 && !isOpenParent) $sel = getSelect2();
				
				var $children = $sel.find(
					"option." + options.optionChildClass + 
					"[" + options.optionChildAttr + "='" + $option.attr('value') + "']"
				);
				
				var parentHTML = $option.html();
				var openLabel = ' +' + $children.filter(':not(:disabled)').length + ' ' + options.optionParentIcon;

				if(isOpenParent) {
					// an already-open parent option has been clicked
					hideSelectOptions($children);
					parentHTML = parentHTML.replace(/\+\d+ ./, ''); // note the '.' represents the UTF-8 arrow icon
					// $option.removeClass(options.optionParentOpenClass).removeAttr('selected');
					$option.removeClass(options.optionParentOpenClass).prop('selected', false);
				} else {
					// a closed parent has been clicked
					var indent = options.optionChildIndent;
					if($option.hasClass(options.optionChildClass)) indent += indent; // double indent
					$children.each(function() {
						// indent the child options (if they aren't already)
						var $child = $(this);
						var childHTML = $child.html();
						// if(!$child.is(':disabled') && childHTML.indexOf(options.optionChildIndent) !== 0) {
						if(childHTML.indexOf(options.optionChildIndent) !== 0) {
							$child.html(indent + childHTML);
						}
					});
					showSelectOptions($children, $option);
					// $select.find(':selected').removeAttr('selected');
					$select.find(':selected').prop('selected', false);
					// collapse any existing parents that are open (behave as accordion)
					if(!$option.hasClass(options.optionChildClass)) {
						$select.find('.' + options.optionParentOpenClass).each(function() {
							$(this).prop('selected', true).trigger('change'); // trigger close if any existing open
						});
					}
					// make the parent selected, encouraging them to click to select a child
					// $option.addClass(options.optionParentOpenClass).attr('selected', 'selected');
					$option.addClass(options.optionParentOpenClass).prop('selected', true);
					parentHTML += openLabel;
					var highlightOption = options.highlight;
					options.highlight = true; // temporarily enable, even if not otherwise enabled
					setHighlight(null, options.optionParentLabel, true);
					if(!highlightOption) options.highlight = false; // restore option setting
				}

				$option.html(parentHTML);
			}

			/**
			 * Called by selectChangeEvent() when an option.asmChild is selected
			 * 
			 * Applicable only if parent/child options are in use
			 * 
 			 * @param $select
			 * @param $option
			 * 
			 */	
			function childOptionSelected($select, $option) {
				// if an option.asmChild was selected, keep the parent selected afterwards		
				// $select.find("option[value='" + $option.attr(options.optionChildAttr) + "']").attr('selected', 'selected');
				$select.find("option[value='" + $option.attr(options.optionChildAttr) + "']").prop('selected', true);
			}

			/**
			 * Event called when a <select> is clicked (for IE6)
			 * 
			 * @todo is this still necessary in 2019?
			 * 
			 */
			function selectClickEvent() {
				// IE6 lets you scroll around in a select without it being pulled down
				// making sure a click preceded the change event reduces the chance
				// if unintended items being added. there may be a better solution?
				ieClick = true; 
			}

			/**
			 * Called when a select or option change event was manually triggered on original select[multiple]
			 * 
			 * When such a change event occurs, we rebuild our own visible $select
			 * 
			 * @param e
			 * 
			 */
			function originalChangeEvent(e) {

				if(ignoreOriginalChangeEvent) {
					ignoreOriginalChangeEvent = false; 
					return; 
				}

				$select.empty();
				if(options.useSelect2 && $select2) $select2.empty();
				$ol.empty();
				buildSelect();

				// opera has an issue where it needs a force redraw, otherwise
				// the items won't appear until something else forces a redraw
				// @todo is this still necessary in 2019?
				if(typeof $.browser != "undefined") {
					if ($.browser.opera) $ol.hide().fadeIn("fast");
				}
				
				if(options.fieldset) setupFieldsets();
			}

			/**
			 * Build (or rebuild) the <select> we created for the user to select items from
			 * 
			 */
			function buildSelect() {

				buildingSelect = true; 

				// add a first option to be the home option / default selectLabel
				var title = $original.attr('title'); 
				
				// number of items that are not disabled
				var numActive = 0; 

				if(title === undefined) title = '';
				$select.prepend("<option>" + title + "</option>"); 

				$original.children("option").each(function(n) {

					var $t = $(this); 
					var id; 

					if(!$t.attr('id')) $t.attr('id', 'asm' + index + 'option' + n); 
					id = $t.attr('id'); 

					if($t.is(":selected")) {
						addListItem(id); 
						addSelectOption(id, true);
					} else if($t.is(":disabled")) {
						addSelectOption(id, true);
					} else {
						numActive++;
						addSelectOption(id); 
					}
				});

				// IE6 requires this on every buildSelect()
				if(!options.debugMode) $original.hide(); 
				
				selectFirstItem();
				
				if(options.hideWhenEmpty) { 
					if(numActive > 0) $select.show(); else $select.hide();
				}
				
				buildingSelect = false; 
			}

			/**
			 * Add an <option> to the $select (called only by buildSelect function)
			 * 
			 * @param optionId The 'id' attribute of the option element to add
			 * @param disabled Is the option disabled?
			 * 
			 */
			function addSelectOption(optionId, disabled) {

				if(typeof disabled == "undefined") disabled = false; 

				var $O = $('#' + optionId); // option from source select
				var data_asmParent = options.optionChildAttr; // data-asmParent attribute name
				var $option = $("<option>" + $O.html() + "</option>") // option for new select
					.val($O.val())
					.attr('rel', optionId);
			
				// does the option have the asmParent class?
				if($O.hasClass(options.optionParentClass)) {
					$option.addClass(options.optionParentClass);
				}
				
				// copy disabled state if applicable
				if(disabled) disableSelectOption($option);

				// does source select have a data-asmParent attribute?
				if($O.attr(data_asmParent)) {
					// this is an asmChild option that requires a parent selection before appearing
					
					// add asmChild class
					$option.addClass(options.optionChildClass); 
					
					// copy the data-asmParent attribute to new option
					$option.attr(data_asmParent, $O.attr(data_asmParent));
					
					// check if we should make options hidden until the parent is selected
					if(options.useSelect2) {
						
						// place option in the hidden $select2 rather than $select
						var $sel2 = getSelect2();
						$sel2.append($option);
						
					} else {
						// hide the option (not supported by Safari)
						hideSelectOptions($option);
						$select.append($option);
					} 
					
				} else {
					
					// add option to the select
					$select.append($option); 
				}

			}

			/**
			 * Get the $select2 used for hidden child options that are not currently visible
			 * 
			 * If the $select2 does not yet exist, it creates it
			 * 
			 */
			function getSelect2() {
				// get the select used for hidden options
				if($select2 && $select2.length) return $select2;
				$select2 = $('<select></select>');
				return $select2;
			}

			/**
			 * Hide the given <option> elements
			 * 
			 * @param $options
			 * 
			 */
			function hideSelectOptions($options) {
				// hide the given select <option> elements
				$options.each(function() {
					var $option = $(this);
					if(options.useSelect2) {
						// use separate select to hold non-visible options (default)
						var $sel2 = getSelect2();
						$sel2.append($option);
						if($option.hasClass(options.optionParentOpenClass)) {
							// if option is a parent, also hide any of its children as well
							hideSelectOptions($select.children(
								'option.' + options.optionChildClass + 
								'[' + options.optionChildAttr + '="' + $option.attr('value') + '"]'
							)); 
						}
					} else {
						// hide option using HTML5 hidden attribute (not supported by Safari)
						$option.attr('hidden', 'hidden');
					}
				});
			}

			/**
			 * Show the given <option> elements
			 * 
			 * @param $options The option elements to show
			 * @param $afterOption Make them start to appear after this option element
			 * 
			 */
			function showSelectOptions($options, $afterOption) {
				$options.each(function() {
					var $option = $(this);
					if(options.useSelect2) {
						if(typeof $afterOption != "undefined") {
							$afterOption.after($option);
							$afterOption = $option;
						} else {
							$select.append($option);
						}
					} else {
						$option.removeAttr('hidden');
					}
				}); 
			}

			/**
			 * Select the first item from the visible $select that we created
			 * 
			 */
			function selectFirstItem() {
				$select.children().first().prop("selected", true); 
			}

			/**
			 * Make the given select <option> disabled (indicating it has been selected)
			 * 
			 * @param $option
			 * 
			 */
			function disableSelectOption($option) {

				// because safari is the only browser that makes disabled items look 'disabled'
				// we apply a class that reproduces the disabled look in other browsers

				// $option.addClass(options.optionDisabledClass).attr("selected", false).attr("disabled", true);
				$option.addClass(options.optionDisabledClass).prop("selected", false).prop("disabled", true);
				
				if(options.hideWhenEmpty) {
					if($option.siblings('[disabled!=true]').length < 2) $select.hide();
				}
				if(options.hideWhenAdded) $option.hide();
				if(msie) $select.hide().show(); // this forces IE to update display
			}

			/**
			 * Convert an option[disabled] to be enabled
			 * 
			 * @param $option
			 * 
			 */
			function enableSelectOption($option) {

				$option.removeClass(options.optionDisabledClass).prop("disabled", false);
				
				if(options.hideWhenEmpty) $select.show();
				if(options.hideWhenAdded) $option.show();
				if(msie) $select.hide().show(); // this forces IE to update display
			}

			/**
			 * Add a selected option to the selected list 
			 * 
			 * This creates an <li> from an <option> and adds it to the <ol> selected list
			 *     
			 * @param optionId The 'id' attribute of the <option> to add to the list
			 * 
			 */
			function addListItem(optionId) {

				var $O = $('#' + optionId); 

				if(!$O) return; // this is the first item, selectLabel

				var $removeLink = null;
				if(options.deletable) $removeLink = $("<a></a>")
					.attr("href", "#")
					.addClass(options.removeClass)
					.prepend(options.removeLabel)
					.on('click', function() { 
						dropListItem($(this).parent('li').attr('rel')); 
						return false; 
					}); 

				var $itemLabel = $("<span></span>").addClass(options.listItemLabelClass);

				// optional container where an <option>'s data-status attribute will be displayed
				var $itemStatus = $("<span></span>").addClass(options.listItemStatusClass);
				if($O.attr('data-status')) $itemStatus.html($O.attr('data-status'));
				
				// optional container where an <option>'s data-desc attribute will be displayed
				var $itemDesc = $("<span></span>").addClass(options.listItemDescClass);

				if(options.editLink.length > 0 && ($O.is(':selected') || !options.editLinkOnlySelected)) {
					
					var $editLink = $("<a></a>")
						.html($O.html())
						.attr('href', options.editLink.replace(/\{value\}/, $O.val()))
						.append(options.editLabel);
					if(options.editLinkModal === "longclick") {
						$editLink.addClass('asmEditLinkModalLongclick');
					} else if(options.editLinkModal) {
						$editLink.on('click', clickEditLink);
					}
					
					$itemLabel.addClass(options.editClass).append($editLink);
					
					if($O.attr('data-desc')) {
						var $editLink2 = $("<a></a>")
							.html($O.attr('data-desc'))
							.attr('href', $editLink.attr('href'))
							.append(options.editLabel);
						$itemDesc.addClass(options.editClass).append($editLink2);
						if(options.editLinkModal === "longclick") {
							$editLink2.addClass('asmEditLinkModalLongclick');
						} else if(options.editLinkModal) {
							$editLink2.on('click', clickEditLink);
						}
					}

				} else {
					$itemLabel.html($O.html());
					if($O.attr('data-desc')) $itemDesc.html($O.attr('data-desc')); 
				}

				var $item = $("<li></li>")
					.attr('rel', optionId)
					.addClass(options.listItemClass)
					.append($itemLabel)
					.append($itemDesc)
					.append($itemStatus);
				if($removeLink) $item.append($removeLink);
				$item.hide();

				if(options.jQueryUI) {
					$item.addClass('ui-state-default')
					.on('mouseenter', function() {
						$(this).addClass('ui-state-hover').removeClass('ui-state-default'); 
					}).on('mouseleave', function() {
						$(this).addClass('ui-state-default').removeClass('ui-state-hover'); 
					}); 
					if(options.sortable) {
						// $item.prepend("<span class='" + options.listItemHandleClass + "'></span>");
						if($O.attr('data-handle')) {
							$item.prepend($($O.attr('data-handle')).addClass(options.listItemHandleClass));
						} else {
							$item.prepend($(options.sortLabel).addClass(options.listItemHandleClass));
						}
					}
				}

				if(!buildingSelect) {
					if($O.is(":selected")) return; // already have it
					// $O.attr('selected', true);
					$O.prop('selected', true); 
				}

				if(options.addItemTarget == 'top' && !buildingSelect) {
					$ol.prepend($item); 
					if(options.sortable) $original.prepend($O); 
				} else {
					$ol.append($item); 
					if(options.sortable) $original.append($O); 
				}

				addListItemShow($item); 

				disableSelectOption($("[rel=" + optionId + "]", $select));

				if(!buildingSelect) {
					setHighlight($item, options.highlightAddedLabel); 
					selectFirstItem();
					if(options.sortable) $ol.sortable("refresh"); 	
					if(options.fieldset) {
						var itemName = $O.text();
						if(itemName.indexOf('_END') > 0 && itemName.substring(itemName.length - 4) == '_END') {
							$item.addClass('asmFieldset asmFieldsetEnd'); 
						} else {
							var fieldsetCloseName = itemName + '_END';
							if(typeof fieldsetCloseItems[fieldsetCloseName] != "undefined") {
								$item.addClass('asmFieldset asmFieldsetStart');
								addListItem(fieldsetCloseItems[fieldsetCloseName].attr('id'));
							}
						}
					}
				}

			}

			/**
			 * Reveal an <li> added to the list (called only by addListItem method)
			 * 
			 * This is primarily here to manage the animation of the show, if used. 
			 * 
			 * @param $item An <li> element
			 * 
			 */
			function addListItemShow($item) {
				if(options.animate && !buildingSelect) {
					$item.animate({
						opacity: "show",
						height: "show"
					}, 100, "swing", function() { 
						$item.animate({
							height: "+=2px"
						}, 50, "swing", function() {
							$item.animate({
								height: "-=2px"
							}, 25, "swing"); 
						}); 
					}); 
				} else {
					$item.show();
				}
			}

			/**
			 * Remove an <li> item from the HTML <ol> list
			 * 
			 * @param optionId The option 'id' attribute that the item represents
			 * @param bool highlightItem Highlight the item? 
			 * 
			 */
			function dropListItem(optionId, highlightItem) {

				var $O = $('#' + optionId); 

				if(options.hideDeleted || !$O.hasClass('asmOriginalSelected')) {

					if(typeof highlightItem == "undefined") highlightItem = true; 

					// $O.attr('selected', false);
					$O.prop('selected', false); 
					$item = $ol.children("li[rel=" + optionId + "]");

					dropListItemHide($item); 
					enableSelectOption($("option[rel=" + optionId + "]"));

					if(highlightItem) setHighlight($item, options.highlightRemovedLabel); 

				} else {
					// deleted item remains in list, but marked for deletion
				
					$item = $ol.children("li[rel=" + optionId + "]");
					var value = $O.attr('value'); 
					if(value == "undefined") value = $O.text();
					if($item.hasClass(options.deletedClass)) {
						$item.removeClass(options.deletedClass);
						if(options.deletedOpacity != 1.0) $item.css('opacity', 1.0); 
						$O.attr('value', value.substring(options.deletedPrepend.length)); 
					} else {
						$item.addClass(options.deletedClass);
						if(options.deletedOpacity != 1.0) $item.css('opacity', options.deletedOpacity); 
						$O.attr('value', options.deletedPrepend + value); 
					}
				}

				triggerOriginalChange(optionId, 'drop'); 
			}

			/**
			 * Helper for dropListItem() method that removes visible $item <li> with optional animation
			 * 
			 * This is primarily here to manage the animation of the remove, if used. 
			 * 
			 * @param $item
			 * 
			 */
			function dropListItemHide($item) {
				if(options.animate && !buildingSelect) {
					$prevItem = $item.prev("li");
					$item.animate({
						opacity: "hide",
						height: "hide"
					}, 100, "linear", function() {
						$prevItem.animate({
							height: "-=2px"
						}, 50, "swing", function() {
							$prevItem.animate({
								height: "+=2px"
							}, 100, "swing"); 
						}); 
						$item.remove(); 
					}); 
				} else {
					$item.remove(); 
				}
			}

			/**
			 * Set the contents of the highlight area that appears directly after the visible <select>
			 * 
			 * This method makes the highlight text fade in quickly, then fade out.
			 * Applicable only if options.highlight is in use. 
			 *     
			 * @param $item
			 * @param label
			 * @param bool remain Should highlight text remain until another option is selected? (default=false)
			 * 
			 */
			function setHighlight($item, label, remain) {

				if(!options.highlight) return; 
				if(typeof remain == "undefined") remain = false;
				
				$select.next("#" + options.highlightClass + index).remove();

				var $highlight = $(options.highlightTag)
					.hide()
					.addClass(options.highlightClass)
					.attr('id', options.highlightClass + index);
				
				if($item) {
					$highlight.html(label + $item.children("." + options.listItemLabelClass).slice(0, 1).text());
				} else {
					$highlight.html(label); 
				}
					
				$select.after($highlight); 

				if(remain) {
					$highlight.fadeIn('fast');
					$highlightSpan = $highlight;
				} else {
					$highlight.fadeIn("fast", function() {
						setTimeout(function() {
							$highlight.fadeOut("slow", function() {
								$(this).remove();
							});
						}, 50);
					});
				}
			}

			/**
			 * Trigger a change event on the original select[multiple] so that other scripts can see the change
			 * 
			 * @param optionId The 'id' attribute of the <option> element that is applicable to the change
			 * @param type The action that occured, one of: 'add', 'drop' or 'sort'
			 * 
			 */
			function triggerOriginalChange(optionId, type) {

				ignoreOriginalChangeEvent = true; 
				$option = $("#" + optionId); 

				$original.trigger('change', [{
					'option': $option,
					'value': $option.val(),
					'id': optionId,
					'item': $ol.children("[rel=" + optionId + "]"),
					'type': type
				}]); 
			}

			/**
			 * Called when an edit link is clicked (requires ProcessWire)
			 * 
			 * Handles management of the modal $iframe window for the edit screen.
			 * Applicable only if the edit options are in use.
			 * Requires ProcessWire’s modal.js script be laoded from the 'JqueryUI' module.
			 * 
			 * @param e
			 * @returns {boolean}
			 * 
			 */
			function clickEditLink(e) {

				if(!options.editLinkModal) return true; 
				
				var $asmItem = $(this).parents('.' + options.listItemClass); 
				var href = $(this).attr('href'); 
				var $iframe = pwModalWindow(href, {}, 'medium'); 

				$iframe.on('load', function() {
					// slight delay is necessary in jQuery 3.x, otherwise visible buttons found to be not visible
					setTimeout(function() { iframeLoaded(); }, 100); 
				});
				
				var iframeLoaded = function() {

					var $icontents = $iframe.contents();	
					var buttons = [];
					var buttonCnt = 0;

					$icontents.find(options.editLinkButtonSelector).each(function(n) {

						var $button = $(this);
						var label = $button.text();
						var valid = true; 
						var secondary = $button.is('.ui-priority-secondary'); 

						for(var i = 0; i < buttonCnt; i++) {
							if(label == buttons[i].text) valid = false;
						}

						if(valid) {
							buttons[buttonCnt] = { 
								text: label, 
								'class': (secondary ? 'ui-priority-secondary' : ''),
								click: function() {
									if($button.attr('type') == 'submit') {
										var updated = false;
										$button.trigger('click'); 
										$asmItem.effect('highlight', {}, 500); 
										
										var $asmSetStatus = $icontents.find('#' + options.listItemStatusClass); // first try to find by ID
										if($asmSetStatus.length == 0) $asmSetStatus = $icontents.find(':input.' + options.listItemStatusClass); // then by class, if not ID
										if($asmSetStatus.length > 0) {
											$asmItem.find('.' + options.listItemStatusClass).html($asmSetStatus.eq(0).val());
											updated = true;
										}
										
										var $asmSetDesc = $icontents.find('#' + options.listItemDescClass); // first try to find by ID
										if($asmSetDesc.length == 0) $asmSetDesc = $icontents.find(':input.' + options.listItemDescClass); // then by class, if not ID
										if($asmSetDesc.length > 0) {
											$asmSetDesc = $asmSetDesc.eq(0);
											var asmSetDesc = $('<textarea />').text($asmSetDesc.val()).html();
											var $desc = $asmItem.find('.' + options.listItemDescClass);
											var $descA = $desc.find('a'); // does it have an <a> in there?
											if($descA.length > 0) {
												$descA.html(asmSetDesc);
											} else {
												$desc.html(asmSetDesc);
											}
											updated = true;
										}
										if(updated) $asmItem.trigger('asmItemUpdated');
									}
									$iframe.dialog('close'); 
								}
							}; 
							buttonCnt++;
						}
						$button.hide();
					}); 
					$iframe.setButtons(buttons); 
				}; 
				
				return false; 
			}

			/**
			 * ProcessWire field fieldset indentation setup
			 * 
			 * In ProcessWire asmSelect is often used to manage lists of fields, which can sometimes include fieldset fields.
			 * When a fieldset is present, there are <option> elements to represent the beginning and ending of the fieldset,
			 * and option elements that appear within are indented to clarify they are in a fieldset. 
			 * 
			 * This method is only called if options.fieldset == true;
			 * 
			 */
			function setupFieldsets() {
				$ol.find('span.asmFieldsetIndent').remove();
				$ol.children('li').children('span.' + options.listItemLabelClass).each(function() {
					var $t = $(this);
					var label = $t.text();
					if(label.substring(label.length-4) != '_END') return;
					label = label.substring(0, label.length-4);
					var $li = $(this).closest('li.' + options.listItemClass);
					$li.addClass('asmFieldset asmFieldsetEnd');
					while(1) {
						$li = $li.prev('li.' + options.listItemClass);
						if($li.length < 1) break;
						var $span = $li.children('span.' + options.listItemLabelClass); 
						var label2 = $span.text();
						if(label2 == label) {
							$li.addClass('asmFieldset asmFieldsetStart');
							break;
						}
						$span.prepend($('<span class="asmFieldsetIndent"></span>'));
					}
				});
			}
			
			/**
			 * Find all options with a name that ends with _END and populate to fieldsetCloseItems
			 * 
			 * @param $select
			 * 
			 */
			function findFieldsetCloseItems($select) {
				$select.children('option').each(function() {
					var name = $(this).text();
					if(name.indexOf('_END') > 0 && name.substring(name.length - 4) == '_END') {
						fieldsetCloseItems[name] = $(this);
					}
				});
			}

			// initialize for this iteration
			init();
		});
	};

})(jQuery); 
