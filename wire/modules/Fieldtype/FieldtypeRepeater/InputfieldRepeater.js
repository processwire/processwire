/**
 * ProcessWire Repeater Inputfield Javascript
 *
 * Maintains a collection of fields that are repeated for any number of times.
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 */

function InputfieldRepeater($) {

	/**
	 * When depth is used, this indicates the indent (in pixels) used to show a single depth increment
	 * 
	 * @type {number}
	 * 
	 */
	var depthSize = 50;

	/**
	 * Whether or not AdminThemeDefault is present
	 * 
	 * @type {bool}
	 * 
	 */
	var isAdminDefault = $('body').hasClass('AdminThemeDefault');

	/**
	 * Event timer for double clicks
	 * 
	 */
	var doubleClickTimer = null;

	/**
	 * True when an ajax request is currently processing a newly added item
	 * 
	 */
	var currentlyAddingItem = false;

	/**
	 * Timeout tracker for insert actions
	 * 
	 */
	var insertTimeout = null;
	
	/**
	 * Page version, if PagesVersions active
	 * 
	 * @type {number}
	 * 
	 */
	var pageVersion = 0;
	
	/**
	 * Non-false when we are toggling family item visibility
	 *
	 * @type {boolean|number}
	 *
	 */
	var togglingItemVisibility = false;
	
	/*** EVENTS ********************************************************************************************/

	/**
	 * Event handler for when an .InputfieldRepeater "reloaded" event is triggered
	 * 
	 * @param event
	 * @param source
	 * 
	 */
	var eventReloaded = function(event, source) {
		if(typeof source != "undefined") {
			if(source == 'InputfieldRepeaterItemEdit' || source == 'InputfieldRepeaterItemAdd') {
				event.stopPropagation();
				var $r = $(this).find(".InputfieldRepeater");
				if($r.length) $r.each(function() { initRepeater($(this)) });
				return;
			}
		}
		initRepeater($(this));
	};

	/**
	 * Event handler for when the "delete" action is clicked
	 * 
	 * @param e
	 * 
	 */
	var eventDeleteClick = function(e) {

		var $this = $(this);
		var $header = $this.closest('.InputfieldHeader');
		var $item = $header.parent();

		if(isActionDisabled($this)) return false;

		if($item.hasClass('InputfieldRepeaterNewItem')) {
			// delete new item (noAjaxAdd mode)
			var $numAddInput = $item.children('.InputfieldContent').children('.InputfieldRepeaterAddItem').children('input');
			$numAddInput.attr('value', parseInt($numAddInput.attr('value') - 1)); // total number of new items to add, minus 1
			$item.remove();

		} else {
			// delete existing item
			var pageID = $item.attr('data-page');
			var $checkbox = $item.find('#delete_repeater' + pageID);

			if($checkbox.is(":checked")) {
				// $checkbox.removeAttr('checked'); // JQM
				$checkbox.prop('checked', false);
				$header.removeClass('ui-state-error').addClass('ui-state-default');
				//if($parent.is('.InputfieldStateCollapsed')) $parent.toggleClass('InputfieldStateCollapsed', 100);
				$item.removeClass('InputfieldRepeaterDeletePending').trigger('repeaterundelete');
			} else {
				// $checkbox.attr('checked', 'checked'); // JQM
				$checkbox.prop('checked', true);
				$header.removeClass('ui-state-default').addClass('ui-state-error');
				if(!$item.hasClass('InputfieldStateCollapsed')) {
					$header.find('.toggle-icon').trigger('click');
					//$item.toggleClass('InputfieldStateCollapsed', 100);
				}
				$item.addClass('InputfieldRepeaterDeletePending').trigger('repeaterdelete'); 
				$item.closest('.Inputfield').addClass('InputfieldStateChanged');
			}
			$header.find('.InputfieldRepeaterItemControls').css('background-color', $header.css('background-color'));
		}

		checkMinMax($item.closest('.InputfieldRepeater'));
		e.stopPropagation();
	};

	/**
	 * Event handler for when the "delete" link is double clicked
	 * 
	 */
	var eventDeleteDblClick = function() {
	
		var $this = $(this);
		var $li = $(this).closest('li');
		var undelete = $li.hasClass('InputfieldRepeaterDeletePending');

		if(isActionDisabled($this)) return false;

		function selectAll() {
			$li.parent().children('li').each(function() {
				var $item = $(this);
				var $trashLink = $item.children('.InputfieldHeader').find('.InputfieldRepeaterTrash');
				if($item.hasClass('InputfieldRepeaterDeletePending')) {
					if(undelete) $trashLink.trigger('click');
				} else {
					if(!undelete) $trashLink.trigger('click');
				}
			});
		}

		if(undelete) {
			selectAll();
		} else {
			ProcessWire.confirm(ProcessWire.config.InputfieldRepeater.labels.removeAll, selectAll);
		}
	};

	/**
	 * Event handler for when the "clone" repeater item action is clicked
	 * 
	 * @returns {boolean}
	 * 
	 */
	var eventCloneClick = function() {
		var $this = $(this);
		if(isActionDisabled($this)) return false;
		var $item = $this.closest('.InputfieldRepeaterItem');
		ProcessWire.confirm(ProcessWire.config.InputfieldRepeater.labels.clone, function() {
			var itemID = $item.attr('data-page');
			var $addLink = $item.closest('.InputfieldRepeater').children('.InputfieldContent')
				.children('.InputfieldRepeaterAddItem').find('.InputfieldRepeaterAddLink').first();
			// $('html, body').animate({ scrollTop: $addLink.offset().top - 100}, 250, 'swing');
			
			$item.siblings('.InputfieldRepeaterInsertItem').remove();
			var depth = getItemDepth($item);
			var $newItem = $item.siblings('.InputfieldRepeaterNewItem').clone();
			var $nextItem = $item.next('.InputfieldRepeaterItem');
			var nextItemDepth = $nextItem.length ? getItemDepth($nextItem) : depth;
			var $prevItem = $item.prev('.InputfieldRepeaterItem');
			var prevItemDepth = $prevItem.length ? getItemDepth($prevItem) : depth;
			var insertBefore = depth < nextItemDepth;
			if(depth < nextItemDepth) insertBefore = true;
			$newItem.addClass('InputfieldRepeaterInsertItem').attr('id', $newItem.attr('id') + '-clone');
			$newItem.find('.InputfieldHeader').html("<i class='fa fa-spin fa-spinner'></i>");
			if(insertBefore) {
				depth = getInsertBeforeItemDepth($item);
				$newItem.addClass('InputfieldRepeaterInsertItemBefore');
				$newItem.insertBefore($item);
			} else {
				depth = getInsertAfterItemDepth($item);
				$newItem.addClass('InputfieldRepeaterInsertItemAfter');
				$newItem.insertAfter($item);
			}
			setItemDepth($newItem, depth);
			$newItem.show();
			$addLink.attr('data-clone', itemID).trigger('click');
		});
		return false;
	};

	/**
	 * Event when the copy/clone/paste action is clicked
	 * 
	 * @returns {boolean}
	 * 
	 */
	var eventCopyCloneClick = function() {

		if(isActionDisabled($(this))) return false;

		var labels = ProcessWire.config.InputfieldRepeater.labels;
		var $item = $(this).closest('.InputfieldRepeaterItem');
		var itemID = $item.attr('data-page');
		var $inputfield = $item.closest('.InputfieldRepeater');
		var fieldName = $inputfield.attr('data-name');
		var cookieName = copyPasteCookieName(fieldName); 
		var copyValue = jQuery.cookie(cookieName);
		var itemLabel = getItemLabel($item).text();
		var pasteID = copyValue ? parseInt(copyValue.item) : '';
		var pasteDisabled = copyValue ? '' : 'disabled ';
		var pasteSelected = pasteID > 0 ? 'selected ' : '';
		var note = '';
		
		if(pasteID > 0) {
			note = "<div style='margin-top:8px'><i class='fa fa-paste fa-fw'></i>" + labels.copyInMemory + ' (id ' + pasteID + ')</div>';
		}
		
		var input = 
			'<option value="copy">' + labels.copy + '</option>' + 
			'<option value="clone-before">' + labels.cloneBefore + '</option>' +
			'<option value="clone-after">' + labels.cloneAfter + '</option>' + 
			'<option ' + pasteDisabled + 'value="paste-before">' + labels.pasteBefore + '</option>' + 
			'<option ' + pasteDisabled + pasteSelected + 'value="paste-after">' + labels.pasteAfter + '</option>' + 
			'<option ' + pasteDisabled + 'value="clear">' + labels.clear + '</option>';
		
		if(note.length) note = "<span class='detail'>" + note + "</span>";
		
		var options = {
			message: labels.selectAction + ' (id ' + itemID + ')', // message displayed at top
			input: '<select name="action" class="uk-select">' + input + '</select>' + note, // HTML content that is to be displayed
			callback: function(value) {
				var action = value.action;
				if(action === 'copy') {
					copyRepeaterItem($item);
					$item.fadeOut('fast', function() { $item.fadeIn('fast') }); 
					$inputfield.addClass('InputfieldRepeaterCanPaste');
				} else if(action === 'clone-before') {
					cloneRepeaterItem($item, true);
				} else if(action === 'clone-after') {
					cloneRepeaterItem($item, false);
				} else if(action === 'paste-before') {
					pasteRepeaterItem($item, true);
				} else if(action === 'paste-after') {
					pasteRepeaterItem($item, false);
				} else if(action === 'clear') {
					jQuery.cookie(cookieName, null);
					$inputfield.removeClass('InputfieldRepeaterCanPaste');
				} else {
					console.log('unknown action: ' + action);
				}
			},
		};
		
		// open the add-type selection dialog
		vex.dialog.open(options);
		
		return false;
	};

	var eventSettingsClick = function(e) {
		var $this = $(this);
		var $item = $this.closest('.InputfieldRepeaterItem');
	
		// find .InputfieldRepeaterSettings, if applicable (like with RM)
		// .InputfieldRepeaterItem > .InputfieldContent > .Inputfields > .InputfieldRepeaterSettings
		var $settingsParent = $item.children('.InputfieldContent').children('.Inputfields');
		var $settings = $settingsParent.children('.InputfieldRepeaterSettings'); // ajax loaded item
		
		if(!$settings.length) {
			// already open item has more layers
			// .InputfieldRepeaterItem > .InputfieldContent > .Inputfields > .InputfieldWrapper > .Inputfields > .InputfieldRepeaterSettings
			$settingsParent = $settingsParent.children('.InputfieldWrapper').children('.Inputfields');
			$settings = $settingsParent.children('.InputfieldRepeaterSettings');
		}
		
		if($item.hasClass('InputfieldStateCollapsed')) {
			$this.closest('.InputfieldHeader').trigger('click');
		}
		
		if($settings.is(':visible')) {
			$settings.slideUp('fast');
			$this.addClass('ui-priority-secondary');
		} else {
			$settings.slideDown('fast');
			$this.removeClass('ui-priority-secondary');
		}
		return false
	}
	
	/**
	 * Event handler for when the repeater item "on/off" toggle is clicked
	 * 
	 * @param e
	 * 
	 */
	var eventToggleClick = function(e) {
		var $this = $(this);
		var toggleOn = $this.attr('data-on');
		var toggleOff = $this.attr('data-off');
		var $item = $this.closest('.InputfieldRepeaterItem');
		var $input = $item.find('.InputfieldRepeaterPublish');
		
		if(doubleClickTimer) clearTimeout(doubleClickTimer);
		doubleClickTimer = setTimeout(function() {
			if(isActionDisabled($this)) return false;
			if($this.hasClass(toggleOn)) {
				$this.removeClass(toggleOn).addClass(toggleOff);
				$item.addClass('InputfieldRepeaterUnpublished InputfieldRepeaterOff');
				$input.val('-1');
			} else {
				$this.removeClass(toggleOff).addClass(toggleOn);
				$item.removeClass('InputfieldRepeaterUnpublished InputfieldRepeaterOff')
					.addClass('InputfieldRepeaterWasUnpublished');
				$input.val('1');
			}
			$input.trigger('change');
			checkMinMax($item.closest('.InputfieldRepeater'));
		}, 250); 
			
		e.stopPropagation();
	};

	/**
	 * Event handler for when a repeater item is about to be opened
	 * 
	 */
	var eventItemOpenReady = function() {
		var $item = $(this);
		var $loaded = $item.find(".InputfieldRepeaterLoaded");
		if(parseInt($loaded.val()) > 0) return; // item already loaded
		$item.addClass('InputfieldRepeaterItemLoading');	
	};
	
	/**
	 * Toggle visibility of children/siblings
	 * 
	 * @param $item
	 * @param open
	 * 
	 */
	function toggleItemFamilyVisibility($item, open) {
		var $inputfield = $item.closest('.InputfieldRepeater');
		
		if(!$inputfield.hasClass('InputfieldRepeaterFamilyToggle')) return;
		if(!$inputfield.hasClass('InputfieldRepeaterDepth')) return;
		if($inputfield.hasClass('InputfieldRepeaterAccordion')) false;
		
		var depth = getItemDepth($item);
		var $nextItem = $item.next('.InputfieldRepeaterItem');
		
		if($nextItem.length) {
			var nextDepth = getItemDepth($nextItem);
			if(nextDepth > depth) {
				// child item
				togglingItemVisibility = nextDepth;
				open ? Inputfields.open($nextItem) : Inputfields.close($nextItem);
			} else if(nextDepth === depth && depth > 0 && togglingItemVisibility) {
				// next sibling item
				open ? Inputfields.open($nextItem) : Inputfields.close($nextItem);
			} else {
				// finished
				togglingItemVisibility = false;
			}
		} else {
			togglingItemVisibility = false;
		}
	}
	
	/**
	 * Called when an item has finished opening
	 * 
	 * @param $item
	 * 
	 */
	function itemOpenComplete($item) {
		toggleItemFamilyVisibility($item, true);
	}

	/**
	 * Event handler for when a repeater item is opened (primarily focused on ajax loaded items)
	 * 
	 */
	var eventItemOpened = function() {
		
		var $item = $(this);
		var $loaded = $item.find(".InputfieldRepeaterLoaded");
		
		updateState($item);

		if(parseInt($loaded.val()) > 0) {
			updateAccordion($item);
			toggleItemFamilyVisibility($item, true);
			return; // item already loaded
		}

		$loaded.val('1');

		var $content = $item.find('.InputfieldContent').hide();
		var $repeater = $item.closest('.InputfieldRepeater');
		var pageID = $repeater.attr('data-page'); // $("#Inputfield_id").val();
		var itemID = parseInt($item.attr('data-page'));
		var repeaterID = $repeater.attr('id');
		var fieldName = getRepeaterFieldName($repeater);
		var ajaxURL = ProcessWire.config.InputfieldRepeater.editorUrl + '?id=' + pageID + '&field=' + fieldName + '&repeater_edit=' + itemID;
		var $spinner = $item.find('.InputfieldRepeaterDrag');
		var $inputfields = $loaded.closest('.Inputfields');
		var contextStr = $repeater.attr('data-context');
		
		if($repeater.hasClass('InputfieldRenderValueMode')) ajaxURL += '&inrvm=1';
		if($repeater.hasClass('InputfieldNoDraft')) ajaxURL += '&nodraft=1';	
		if(pageVersion) ajaxURL += '&version=' + pageVersion;

		var iconName = $item.attr('data-icon');
		if(typeof iconName === 'undefined' || !iconName) iconName = 'fa-arrows';
		$spinner.removeClass(iconName).addClass('fa-spin fa-spinner');
		repeaterID = repeaterID.replace(/_repeater\d+$/, '').replace('_LPID' + pageID, '');
		
		if(typeof contextStr !== 'undefined' && contextStr.length) {
			repeaterID = repeaterID.replace(contextStr, '');
		}

		$.get(ajaxURL, function(data) {
			var $inputs = $(data).find('#' + repeaterID + ' > ' +
				'.InputfieldContent > .Inputfields > ' +
				'.InputfieldRepeaterItem > .InputfieldContent > .Inputfields > .InputfieldWrapper > ' +
				'.Inputfields > .Inputfield');

			$inputfields.append($inputs);
			$item.removeClass('InputfieldRepeaterItemLoading');
			InputfieldsInit($inputfields);

			var $repeaters = $inputs.find('.InputfieldRepeater');
			if($repeaters.length) {
				// nested
				$repeaters.each(function() {
					initRepeater($(this));
				});
			} else {
				$item.find('.InputfieldRepeaterSettings').hide();
			}

			$content.slideDown('fast', function() {
				$spinner.removeClass('fa-spin fa-spinner').addClass(iconName);
				updateAccordion($item);
			});
			
			setTimeout(function() {
				$inputfields.find('.Inputfield').trigger('reloaded', ['InputfieldRepeaterItemEdit']);
				itemOpenComplete($item);
			}, 50);
			
			runScripts(data);	

		});
	};

	/**
	 * Event handler for when a repeater item is closed
	 * 
	 */
	var eventItemClosed = function() {
		updateState($(this));
		toggleItemFamilyVisibility($(this), false);
	};

	/**
	 * Event handler for "add" link clicks
	 * 
	 * Handles adding repeater items and initializing them
	 * 
	 * @returns {boolean}
	 * 
	 */
	var eventAddLinkClick = function() {
		currentlyAddingItem = true;
		
		var $addLink = $(this);
		var $inputfields = $addLink.parent('p').prev('ul.Inputfields');
		var $inputfieldRepeater = $addLink.closest('.InputfieldRepeater');
		var $numAddInput = $addLink.parent().children('input');
		var newItemTotal = 0; // for noAjaxAdd mode
		var useAjax = $addLink.attr('data-noajax').length == 0;
		var cloneID = $addLink.attr('data-clone');
		var pageID = 0;
		var depth = 0;
		var redoSortAll = false;
		var inputfieldPageID = parseInt($inputfieldRepeater.attr('data-page'));

		function addRepeaterItem($addItem) {
			// make sure it has a unique ID
			var id = $addItem.attr('id') + '_';
			while($('#' + id).length > 0) id += '_';
			$addItem.attr('id', id);
			var $insertItem = $inputfields.children('.InputfieldRepeaterInsertItem');
			if($insertItem.length) {
				depth = getItemDepth($insertItem);
				$addItem.addClass('InputfieldStateCollapsed')
				var $toggleIcon = $addItem.children('.InputfieldHeader').find('.toggle-icon');
				$toggleIcon.toggleClass($toggleIcon.attr('data-to')); 
				$insertItem.replaceWith($addItem);
				redoSortAll = true;
			} else {
				$inputfields.append($addItem);
			}
			$addItem.css('display', 'block');
			adjustItemLabel($addItem, true);
			$addLink.trigger('repeateradd', [ $addItem ]);
		}

		if(typeof cloneID == "undefined" || !cloneID) cloneID = null;
		
		if(cloneID) {
			$addLink.removeAttr('data-clone');
			// when data-clone contains pageID:itemID it is from a previous copy operation
			if(cloneID.indexOf(':') > 0) {
				var a = cloneID.split(':');
				pageID = parseInt(a[0]); // for copy/paste
				cloneID = parseInt(a[1]);
			}
		}

		if(!useAjax) {
			var $newItem = $inputfields.children('.InputfieldRepeaterNewItem'); // for noAjaxAdd mode, non-editable new item
			newItemTotal = $newItem.length;
			if(newItemTotal > 0) {
				if(newItemTotal > 1) $newItem = $newItem.slice(0, 1);
				var $addItem = $newItem.clone(true);
				if(depth) setItemDepth($addItem, depth);
				addRepeaterItem($addItem);
				$numAddInput.attr('value', newItemTotal);
				checkMinMax($inputfieldRepeater);
			}
			currentlyAddingItem = false;
			return false;
		}

		
		// get addItem from ajax
		if(!pageID) pageID = inputfieldPageID;
		var fieldName = getRepeaterFieldName($inputfieldRepeater);
		var $spinner = $addLink.parent().find('.InputfieldRepeaterSpinner');
		var ajaxURL = ProcessWire.config.InputfieldRepeater.editorUrl + '?id=' + pageID + '&field=' + fieldName;
		
		if(pageVersion) ajaxURL += '&version=' + pageVersion;

		$spinner.removeClass($spinner.attr('data-off')).addClass($spinner.attr('data-on'));

		if(cloneID) {
			ajaxURL += '&repeater_clone=' + cloneID + '&repeater_clone_to=' + inputfieldPageID;
		} else {
			ajaxURL += '&repeater_add=' + $addLink.attr('data-type');
		}

		// determine which page IDs we don't accept for new items (because we already have them rendered)
		// var $unpublishedItems = $inputfields.find('.InputfieldRepeaterUnpublished, .InputfieldRepeaterWasUnpublished'); // :not(.InputfieldRepeaterMinItem)');
		var $existingItems = $inputfields.find('.InputfieldRepeaterItem:not(.InputfieldRepeaterNewItem)'); 
		if($existingItems.length) {
			ajaxURL += '&repeater_not=';
			$existingItems.each(function() {
				ajaxURL += $(this).attr('data-page') + ',';
			});
		}

		$.get(ajaxURL, function(data) {
			$spinner.removeClass($spinner.attr('data-on')).addClass($spinner.attr('data-off'));
			var $addItem = $(data).find(".InputfieldRepeaterItemRequested");
			if(!$addItem.length) {
				// error
				return;
			}
			addRepeaterItem($addItem);
			$addItem.wrap("<div />"); // wrap for inputfields.js $target
			InputfieldsInit($addItem.parent());
			initRepeater($addItem);
			$addItem.unwrap(); // unwrap div once item initialized
			$addItem.find('.Inputfield').trigger('reloaded', [ 'InputfieldRepeaterItemAdd' ]);
			if(cloneID) {
				$addItem.find('.Inputfield').trigger('cloned', [ 'InputfieldRepeaterItemAdd' ]);
				// next line can remove 9/2019, as 'cloned' support will have been in InputfieldTable for awhile
				$addItem.find('.InputfieldTableRowID').val(0); 
			}
			if(redoSortAll) {
				$inputfields.children('.InputfieldRepeaterItem').each(function(n) {
					setItemSort($(this), n);
				}); 
			} else {
				setItemSort($addItem, $inputfields.children().length); 
			}
			if(depth) setItemDepth($addItem, depth);
			if($addItem.hasClass('InputfieldStateCollapsed')) {
				// ok
			} else if(!$inputfieldRepeater.hasClass('InputfieldRepeaterNoScroll')) {
				$('html, body').animate({
					scrollTop: $addItem.offset().top
				}, 500, 'swing');
			}
			updateState($addItem);
			checkMinMax($inputfieldRepeater);
			updateAccordion($addItem);
			var $nestedRepeaters = $addItem.find('.InputfieldRepeater');
			if($nestedRepeaters.length) {
				$nestedRepeaters.each(function() {
					initRepeater($(this));
				});
			}
			runScripts(data);
			
			setTimeout(function() { currentlyAddingItem = false; }, 500);
		});

		return false;
	};

	/**
	 * Event handler for the "open all" or "collapse all" functions
	 * 
	 * @param e
	 * @returns {boolean}
	 * 
	 */
	var eventOpenAllClick = function(e) {
		
		e.stopPropagation();
		e.preventDefault();
		
		if(doubleClickTimer) clearTimeout(doubleClickTimer);
		
		if($(this).closest('.InputfieldRepeater').hasClass('InputfieldRepeaterAccordion')) return false;
		
		var $repeater = $(this).closest('.InputfieldRepeater');
		var $items = $repeater.children('.InputfieldContent').children('.Inputfields').children('.InputfieldRepeaterItem');
		if(!$items.length) return false;
		var $item = $items.eq(0);
		var label, selector;
		
		if($item.hasClass('InputfieldStateCollapsed')) {
			label = ProcessWire.config.InputfieldRepeater.labels.openAll;
			selector = '.InputfieldStateCollapsed';
		} else {
			label = ProcessWire.config.InputfieldRepeater.labels.collapseAll;
			selector = '.InputfieldRepeaterItem:not(.InputfieldStateCollapsed)';
		}
		ProcessWire.confirm(label, function() {
			$items.filter(selector).each(function() {
				$(this).children('.InputfieldHeader').find('.toggle-icon').trigger('click');	
			});
		});
		return false;
	};

	/**
	 * Click the "insert before" button event
	 * 
	 */
	var eventInsertBeforeClick = function(e) {
		var $item = $(this).closest('.InputfieldRepeaterItem');
		eventInsertClick($item, true);
		e.stopPropagation();
	};

	/**
	 * Click the "insert after" button event
	 * 
	 */
	var eventInsertAfterClick = function(e) {
		var $item = $(this).closest('.InputfieldRepeaterItem');
		eventInsertClick($item, false);
		e.stopPropagation();
	};

	/**
	 * Handler for either insert before or insert after click events
	 * 
	 */
	function eventInsertClick($item, insertBefore) {
		if(currentlyAddingItem) return false;
		currentlyAddingItem = true;
		if(insertTimeout) clearTimeout(insertTimeout);
		
		var depth = getInsertItemDepth($item, insertBefore);
		var $oldInsertItem = $item.siblings('.InputfieldRepeaterInsertItem');
		if($oldInsertItem.length) $oldInsertItem.remove();
		var $insertItem = $item.siblings('.InputfieldRepeaterNewItem').clone()
			.removeClass('.InputfieldRepeaterNewItem').addClass('InputfieldRepeaterInsertItem');
		$insertItem.attr('id', $insertItem.attr('id') + '-placeholder');
		$insertItem.find('.InputfieldHeader').html("<i class='fa fa-spin fa-spinner'></i>");
		if(insertBefore) {
			$insertItem.insertBefore($item);
		} else {
			$insertItem.insertAfter($item);
		}
		if(depth > 0) setItemDepth($insertItem, depth);
		$insertItem.show();
		
		if(!insertBefore && !$item.hasClass('InputfieldStateCollapsed')) scrollToItem($insertItem);
		$insertItem.children('.InputfieldHeader').effect('highlight', {}, 500);
		var $addLinks = $item.parent('.Inputfields').siblings('.InputfieldRepeaterAddItem').find('.InputfieldRepeaterAddLink');
		if($addLinks.length === 1) {
			// add new item now
			$addLinks.eq(0).trigger('click');
		} else if($addLinks.length > 1) {
			// we need to know what type of link to add (i.e. matrix)
			$item.trigger('repeaterinsert', [ $insertItem, $item, insertBefore ]);
			currentlyAddingItem = false;
		}
	}

	/**
	 * Event called when the "Paste" link in the footer is clicked
	 * 
	 */
	var eventPasteClick = function(e) {
		var $inputfield = $(this).closest('.InputfieldRepeater');
		// use the InputfieldRepeaterNewItem as our substitute for a contextual item
		var $newItem = $inputfield.children('.InputfieldContent').children('.Inputfields').children('.InputfieldRepeaterNewItem');	
		pasteRepeaterItem($newItem, false);
		return false;
	}; 

	/**
	 * Event when mouseout of insert before/after action
	 * 
	 */
	var eventInsertMouseout = function(e) {
		if(currentlyAddingItem) return;
		if(insertTimeout) clearTimeout(insertTimeout);
		var $action = $(this);
		var $newItem = $action.data('newItem');
		$action.removeClass('hov');
		// var $newItem = $action.closest('.Inputfields').children('.InputfieldRepeaterInsertItem');
		if($newItem && $newItem.length) {
			if($newItem.hasClass('hov')) return;
			$newItem.remove();
		}
	};

	/**
	 * Event when mouseover of insert before/after action
	 * 
	 */
	var eventInsertMouseover = function(e) {
		
		if(currentlyAddingItem) return;
		if(insertTimeout) clearTimeout(insertTimeout);
		
		var $action = $(this);
		var insertBefore = $action.hasClass('InputfieldRepeaterInsertBefore');
		var $item = $(this).closest('.InputfieldRepeaterItem');
		var depth = 0;
		
		$item.siblings('.InputfieldRepeaterInsertItem').remove();
		
		var $newItem = $item.siblings('.InputfieldRepeaterNewItem').clone();
		$newItem.addClass('InputfieldRepeaterInsertItem').attr('id', $newItem.attr('id') + '-insert'); 
		
		if(insertBefore) {
			depth = getInsertBeforeItemDepth($item);
			$newItem.addClass('InputfieldRepeaterInsertItemBefore');//.insertBefore($item);
			$newItem.addClass('hov');
		} else {
			depth = getInsertAfterItemDepth($item);
			$newItem.addClass('InputfieldRepeaterInsertItemAfter');//.insertAfter($item);
		}
		
		$newItem.find('.InputfieldRepeaterItemControls').hide();
		$newItem.find('.InputfieldRepeaterItemLabel').text(ProcessWire.config.InputfieldRepeater.labels.insertHere);
		
		$action.addClass('hov').data('newItem', $newItem);
		
		setItemDepth($newItem, depth);
		
		insertTimeout = setTimeout(function() {
			insertTimeout = null;
			if(!$action.hasClass('hov')) {
				$newItem.remove();
				return;
			} else if(insertBefore) {
				$newItem.insertBefore($item);
			} else {
				$newItem.addClass('hov').insertAfter($item);
			}
			//$newItem.addClass('hov');
			$newItem.on('mouseover', function() {
				$(this).addClass('hov');
			}).on('click', function(e) {
				e.stopPropagation();
				eventInsertClick($item, insertBefore);
			}).on('mouseout', function() {
				$(this).removeClass('hov').remove();
			});
			$newItem.slideDown();
		}, 1000); 
	};
	
	
	/*** GENERAL FUNCTIONS **********************************************************************************/
	
	/**
	 * Returns whether or not the given icon action is disabled
	 * 
	 * @param $this The '.fa-' icon that represents the action
	 * @returns {boolean}
	 * 
	 */
	function isActionDisabled($this) {
		if($this.hasClass('pw-icon-disabled')) {
			ProcessWire.alert(ProcessWire.config.InputfieldRepeater.labels.disabledMinMax);
			return true;
		}
		return false;
	}
	
	function updateAccordion($item) {
		
		if(!$item.closest('.InputfieldRepeater').hasClass('InputfieldRepeaterAccordion')) return false;
	
		var itemID = $item.attr('id');
		var useScroll = false;
		var $siblings = $item.parent().children('.InputfieldRepeaterItem');
		var itemHasPassed = false;
		var hasOpen = false;
		
		$siblings.each(function() {
			var $sibling = $(this);
			if($sibling.attr('id') == itemID) {
				itemHasPassed = true;
				return;
			}
			if($sibling.hasClass('InputfieldStateCollapsed')) return;
			if(!$sibling.is(':visible')) return;
			if(!itemHasPassed) useScroll = true;
			$sibling.children('.InputfieldHeader').find('.toggle-icon').trigger('click', [ { duration: 0 }]);
			hasOpen = true;
		});
		
		if(useScroll && hasOpen) {
			scrollToItem($item);
		}
		
		return true;
	}

	/**
	 * Given an InputfieldRepeaterItem update the label consistent with any present formatting string
	 * 
	 * Primarily adjusts item count(s) and allowed for {secondary} text appearance
	 * 
	 * @param $item An .InputfieldRepeaterItem
	 * @param {boolean} doIncrement Specify true to increment the item count value (like for new items)
	 * 
	 */
	function adjustItemLabel($item, doIncrement) {

		var $label;
		$label = $item.children('.InputfieldHeader').find('.InputfieldRepeaterItemLabel');
		if(typeof $label == "undefined") $label = $item.children('label');
		var labelHTML = $label.html();
		var _labelHTML = labelHTML;

		if(typeof labelHTML != "undefined") {
			if(doIncrement && labelHTML.indexOf('#') > -1) {
				var num = $item.siblings('.InputfieldRepeaterItem:visible').length + 1;
				labelHTML = labelHTML.replace(/#[0-9]+/, '#' + num);
			}

			while(labelHTML.indexOf('}') > -1) {
				// parts of the label wrapped in {brackets} get different appearance
				labelHTML = labelHTML.replace(/\{/, '<span class="ui-priority-secondary" style="font-weight:normal">');
				labelHTML = labelHTML.replace(/}/, '</span>');
			}

			if(labelHTML != _labelHTML) {
				$label.html(labelHTML);
			}
		}
	}
	
	function getItemLabel($item) {
		return $item.children('.InputfieldHeader').children('.InputfieldRepeaterItemLabel');
	}
	
	function getRepeaterFieldName($inputfield) {
		if(!$inputfield.hasClass('InputfieldRepeater')) $inputfield = $inputfield.closest('.InputfieldRepeater');
		if(!$inputfield.length) return '';
		var fieldName = $inputfield.attr('data-name');
		if(typeof fieldName === 'undefined') {
			fieldName = $inputfield.attr('id').replace('wrap_Inputfield_', '');
			if(fieldName.indexOf('_LPID') > -1) fieldName = fieldName.replace(/_LPID\d+$/, '');
			console.log('Warning: repeater inputfield lacks data-name so used fallback', $inputfield);
		}
		return fieldName;
	}
	
	/*** SORT FUNCTIONS ***********************************************************************************/
	
	function setItemSort($item, sort) {
		var $input = getItemSortInput($item);
		if($input.length) $input.val(sort);
	}
	
	function getItemSort($item) {
		var $input = getItemSortInput($item);
		if($input.length) return parseInt($input.val());
		return -1;
	}
	
	function getItemSortInput($item) {
		if(!$item.hasClass('InputfieldRepeaterItem')) $item = $item.closest('.InputfieldRepeaterItem');
		return $item.children('.InputfieldContent').children('.Inputfields')
			.children('.InputfieldRepeaterItemSort').find('.InputfieldRepeaterSort');
	}

	/**
	 * Is item allowed to be sorted to its current position?
	 * 
	 * @param $item
	 * 
	 */
	function sortableItemAllowed($item) {
		if($item.hasClass('InputfieldRepeaterMatrixItem')) {
			if(typeof InputfieldRepeaterMatrixTools !== "undefined") {
				return InputfieldRepeaterMatrixTools.sortableItemAllowed($item);
			}
		}
		return true;
	}

	/*** DEPTH FUNCTIONS **********************************************************************************/
	
	/**
	 * Determine the sortable depth of a repeater item and either return it or apply it
	 * 
	 * @param ui The 'ui' argument provided to jQuery UI sortable events
	 * @param maxDepth Maximum allowed depth
	 * @param updateNow Specify true to apply the determined depth now or false to just return it. 
	 * @returns {number} Depth integer value between 0 and maxDepth
	 * 
	 */
	function sortableDepth(ui, maxDepth, updateNow) {

		var $wrap = ui.item.children('.InputfieldContent').children('.Inputfields').children('.InputfieldRepeaterItemDepth');
		var $depth = $wrap.find('input');
		var depth = -1;
		var prevDepth = parseInt($depth.val());
		var left = ui.position.left;
	
		if(left < 0) {
			depth = prevDepth - Math.round(Math.abs(left) / depthSize);
			// console.log('decrease depth to: ' + depth);

		} else {
			depth = Math.round(left / depthSize) + prevDepth;
			// console.log('increase depth to: ' + depth);
		}

		if(updateNow) {
			depth = setItemDepth(ui.item, depth, maxDepth);
			ui.item.children('.InputfieldHeader').removeClass('ui-state-error');
		}

		return depth;
	}

	/**
	 * Set repeater item depth
	 * 
	 * @param $item Repeater item
	 * @param int depth Depth to set
	 * @param int maxDepth Max depth (you can optionally omit this if depth is already validated for the max)
	 * @param bool noValidate Specify true to prevent depth validation, otherwise omit
	 * @returns int Returns adjusted depth or -1 on fail
	 * 
	 */
	function setItemDepth($item, depth, maxDepth, noValidate) {
		
		noValidate = typeof noValidate === "undefined" ? false : noValidate;
		
		if(depth < 1) depth = 0;
		if(typeof maxDepth !== 'undefined' && depth > maxDepth) depth = maxDepth;
		if(!$item.hasClass('InputfieldRepeaterItem')) $item = $item.closest('.InputfieldRepeaterItem');
		if(!$item.length) return -1;
	
		var $depthInput = $item.children('.InputfieldContent').children('.Inputfields')
			.children('.InputfieldRepeaterItemDepth').find('input');
		
		if(!$depthInput.length && !$item.hasClass('InputfieldRepeaterNewItem')) {
			console.log('Cannot find depth input for ' + $item.attr('id'));
		}

		if(!noValidate && $item.closest('.InputfieldRepeater').hasClass('InputfieldRepeaterFamilyFriendly')) {
			var $prevItem = $item.prev('.InputfieldRepeaterItem:not(.InputfieldRepeaterNewItem)'); 
			if($prevItem.length) {
				var prevItemDepth = parseInt($prevItem.attr('data-depth'));
				if(depth - prevItemDepth > 1) depth = prevItemDepth + 1;
			} else {
				depth = 0;
			}
		}
	
		$depthInput.val(depth);
		$item.attr('data-depth', depth);
		
		if(depth > 0) {
			$item.css('padding-left', (depth * depthSize) + 'px');
			$item.addClass('InputfieldRepeaterItemHasDepth');
		} else {
			$item.css('padding-left', 0);
			$item.removeClass('InputfieldRepeaterItemHasDepth');
		}
		
		return depth;
	}

	/**
	 * Get repeater item depth
	 * 
	 * @param $item Repeater item
	 * @returns int Returns depth or -1 on fail
	 * 
	 */
	function getItemDepth($item) {
		if(!$item.hasClass('InputfieldRepeaterItem')) $item = $item.closest('.InputfieldRepeaterItem');
		if(!$item.length) return -1;
		return parseInt($item.attr('data-depth'));
	}

	/**
	 * Get depth for a new item if it were to be inserted before/after given $contextItem
	 * 
	 * @param $contextItem
	 * @param insertBefore
	 * @returns {Number}
	 * 
	 */
	function getInsertItemDepth($contextItem, insertBefore) {
		var depth = 0;
		if(insertBefore) {
			depth = getItemDepth($contextItem);
		} else {
			var $nextItem = $contextItem.next('.InputfieldRepeaterItem');
			depth = getItemDepth($contextItem);
			if($nextItem.hasClass('InputfieldRepeaterNewItem')) {
				// the default hidden new item is not useful for identifying depth
				if(!$nextItem.hasClass('InputfieldRepeaterInsertItem')) $nextItem = null;
			}
			var nextDepth = $nextItem && $nextItem.length ? getItemDepth($nextItem) : depth;
			if(nextDepth > depth) depth = nextDepth;
		}
		return depth;
	}
	
	function getInsertBeforeItemDepth($item) {
		return getInsertItemDepth($item, true);
	}
	
	function getInsertAfterItemDepth($item) {
		return getInsertItemDepth($item, false);
	}

	/**
	 * Get all depth children for given repeater item
	 * 
	 * @param $item Repeater item
	 * @returns {Array}
	 * 
	 */
	function getDepthChildren($item) {
		
		var children = [];
		var n = 0;
		var startDepth = parseInt($item.attr('data-depth'));
		var pageId = $item.attr('data-page');
		var pageIdClass = 'Inputfield_repeater_item_' + pageId;

		// ui.sortable adds additional copies of $item, so make sure we have the last one
		while($item.hasClass(pageIdClass)) {
			var $nextItem = $item.next('.InputfieldRepeaterItem:not(.InputfieldRepeaterNewItem)');
			if(!$nextItem.length || !$nextItem.hasClass(pageIdClass)) break;
			$item = $nextItem;
		}
		
		do {
			// var $child = $item.next('.InputfieldRepeaterItem:not(.' + pageIdClass + '):not(.InputfieldRepeaterNewItem)');
			var $child = $item.next('.InputfieldRepeaterItem:not(.InputfieldRepeaterNewItem)');
			if(!$child.length) break;
			
			var childDepth = parseInt($child.attr('data-depth'));
			if(!childDepth || childDepth <= startDepth) break;
			
			$item = $child;
			children[n] = $child;
			n++;
			
		} while(true);
		
		return children;
	}
	
	/*** INIT FUNCTIONS **********************************************************************************/

	/**
	 * Initialize repeater item depths 
	 * 
	 * Applies a left-margin to repeater items consistent with with value in 
	 * each item's '.InputfieldRepeaterItemDepth input' hidden input. 
	 * 
	 * @param $inputfieldRepeater
	 * 
	 */
	function initDepths($inputfieldRepeater) {
		$inputfieldRepeater.find('.InputfieldRepeaterItemDepth').each(function() {
			var $wrap = $(this);
			var $depth = $wrap.find('input');
			var depth = $depth.val();
			var $item = $depth.closest('.InputfieldRepeaterItem');
			var currentLeft = $item.css('padding-left');
			if(currentLeft == 'auto') currentLeft = 0;
			currentLeft = parseInt(currentLeft);
			var targetLeft = depth * depthSize;
			if(targetLeft != currentLeft) {
				$item.css('padding-left', targetLeft + 'px');
			}
			if(targetLeft > 0) {
				$item.addClass('InputfieldRepeaterItemHasDepth');
			} else {
				$item.removeClass('InputfieldRepeaterItemHasDepth');
			}
		});
		// $inputfieldRepeater.children('.InputfieldContent').css('position', 'relative');
		$inputfieldRepeater.children('.InputfieldContent').children('.Inputfields').css('position', 'relative');
	}

	/**
	 * Make a repeater sortable
	 * 
	 * @param $inputfieldRepeater The parent .InputfieldRepeater 
	 * @param $inputfields The .Inputfields parent of the sortable items
	 * 
	 */
	function initSortable($inputfieldRepeater, $inputfields) {

		var maxDepth = parseInt($inputfieldRepeater.attr('data-depth'));
		var depthChildren = [];
		var startDepth = 0;
		var familyFriendly = $inputfieldRepeater.hasClass('InputfieldRepeaterFamilyFriendly');
		var sortableOptions = {
			items: '> li:not(.InputfieldRepeaterNewItem)',
			handle: '.InputfieldRepeaterDrag',
			start: function(e, ui) {
				ui.item.find('.InputfieldHeader').addClass("ui-state-highlight");
			
				// CKEditor doesn't like being sorted, do destroy when sort starts, and reload after sort
				ui.item.find('textarea.InputfieldCKEditorNormal.InputfieldCKEditorLoaded').each(function() {
					$(this).removeClass('InputfieldCKEditorLoaded');
					var editor = CKEDITOR.instances[$(this).attr('id')];
					editor.destroy();
					CKEDITOR.remove($(this).attr('id'));
				});

				// TinyMCE instances don't like to be dragged, so we disable them temporarily
				/*
				ui.item.find('.InputfieldTinyMCE textarea').each(function() {
					tinyMCE.execCommand('mceRemoveControl', false, $(this).attr('id'));
				});
				*/
			
				if(familyFriendly && maxDepth > 0) {
					// remember and hide depth children
					startDepth = parseInt(ui.item.attr('data-depth'));
					depthChildren = getDepthChildren(ui.item);
					for(var n = 0; n < depthChildren.length; n++) {
						depthChildren[n].slideUp('fast');
					}
				}
			},

			stop: function(e, ui) {
				if(maxDepth > 0) {
					sortableDepth(ui, maxDepth, true);
				}
				
				if(!sortableItemAllowed(ui.item)) return false;
				
				// update/move and show depth children
				if(maxDepth > 0 && familyFriendly && depthChildren.length) {
					var $item = ui.item;
					var stopDepth = parseInt($item.attr('data-depth'));
					var diffDepth = stopDepth - startDepth;
					for(var n = 0; n < depthChildren.length; n++) {
						var $child = depthChildren[n];
						if(diffDepth != 0) {
							var itemDepth = getItemDepth($child);
							setItemDepth($child, itemDepth + diffDepth, maxDepth, true);
						}
						$item.after($child);
						$child.slideDown('fast');
						$item = $child;
					}
					depthChildren = [];
				}

				ui.item.find('.InputfieldHeader').removeClass("ui-state-highlight");
				$(this).children().each(function(n) {
					$(this).find('.InputfieldRepeaterSort').slice(0,1).attr('value', n);
				});

				// Re-enable CKEditor instances
				ui.item.find('textarea.InputfieldCKEditorNormal:not(.InputfieldCKEditorLoaded)').each(function() {
					$(this).closest('.InputfieldCKEditor').trigger('reloaded', [ 'InputfieldRepeaterSort' ]);
				});

				// Re-enable the TinyMCE instances
				/*
				ui.item.find('.InputfieldTinyMCE textarea').each(function() {
					tinyMCE.execCommand('mceAddControl', false, $(this).attr('id'));
				});
				 */
				
				$(this).closest('.InputfieldRepeater').trigger('sorted', [ ui.item ]);
			},
			
			update: function(e, ui) {
				$inputfieldRepeater.addClass('InputfieldStateChanged');
			}
		};

		if(maxDepth > 0) {
			initDepths($inputfieldRepeater);
			sortableOptions.grid = [ depthSize, 1 ];
			sortableOptions.sort = function(event, ui) {
				var depth = sortableDepth(ui, 99, false);
				var $header = ui.item.children('.InputfieldHeader');
				if(depth > maxDepth) {
					// beyond max depth allowed
					$header.addClass('ui-state-error InputfieldRepeaterItemOOB'); // OOB: Out Of Bounds
				} else if($header.hasClass('ui-state-error')) {
					// no problems
					$header.removeClass('ui-state-error InputfieldRepeaterItemOOB');
				}
			};
		} else {
			sortableOptions.axis = 'y';
		}
		// apply "ui-state-focus" class when an item is being dragged
		$(".InputfieldRepeaterDrag", $inputfields).on('mouseenter', function() {
			$(this).parent('label').addClass('ui-state-focus');
		}).on('mouseleave', function() {
			$(this).parent('label').removeClass('ui-state-focus');
		});

		$inputfields.sortable(sortableOptions);
	}

	/**
	 * Initialize the .InputfieldHeader for .InputfieldRepeaterItem elements
	 * 
	 * @param $headers The .InputfieldHeader elements
	 * @param $inputfieldRepeater The parent .InputfieldRepeater
	 * @param {boolean} renderValueMode Whether or not this is value-only rendering mode
	 * 
	 */
	function initHeaders($headers, $inputfieldRepeater, renderValueMode) {
		
		var $clone = $("<i class='fa fa-copy InputfieldRepeaterClone'></i>").css('display', 'block');
		// var $paste = $("<i class='fa fa-paste InputfieldRepeaterPaste'></i>").css('display', 'block');
		var $delete = $("<i class='fa fa-trash InputfieldRepeaterTrash'></i>");
		var $toggle = $("<i class='fa InputfieldRepeaterToggle' data-on='fa-toggle-on' data-off='fa-toggle-off'></i>");
		var $insertAfter = $("<i class='fa fa-download InputfieldRepeaterInsertAfter'></i>");
		var $insertBefore = $("<i class='fa fa-upload InputfieldRepeaterInsertBefore'></i>"); 
		var cfg = ProcessWire.config.InputfieldRepeater;
		var allowClone = !$inputfieldRepeater.hasClass('InputfieldRepeaterNoAjaxAdd');
		var allowSettings = $inputfieldRepeater.hasClass('InputfieldRepeaterHasSettings');

		if(cfg) {
			$toggle.attr('title', cfg.labels.toggle);
			$delete.attr('title', cfg.labels.remove);
			$clone.attr('title', cfg.labels.clone);
			// $paste.attr('title', 'Paste'); // @todo
			$insertBefore.attr('title', cfg.labels.insertBefore);
			$insertAfter.attr('title', cfg.labels.insertAfter);
		}
		
		if(allowSettings) {
			$inputfieldRepeater.find('.InputfieldRepeaterSettings').hide();
		}

		$headers.each(function() {
			var $t = $(this);
			if($t.hasClass('InputfieldRepeaterHeaderInit')) return;
			var $item = $t.parent();
			var icon = $item.attr('data-icon'); 
			if(typeof icon === "undefined" || !icon.length) icon = 'fa-arrows';
			if(icon.indexOf('fa-') !== 0) icon = 'fa-' + icon;
			if($item.hasClass('InputfieldRepeaterNewItem')) {
				// noAjaxAdd mode
				icon = 'fa-plus-circle';
				$t.addClass('ui-priority-secondary');
			}
			$t.addClass('ui-state-default InputfieldRepeaterHeaderInit');
			$t.prepend("<i class='fa fa-fw " + icon + " InputfieldRepeaterDrag'></i>");
			if(!renderValueMode) {
				var $controls = $("<span class='InputfieldRepeaterItemControls'></span>");
				var $toggleControl = $toggle.clone(true)
					.addClass($t.parent().hasClass('InputfieldRepeaterOff') ? 'fa-toggle-off' : 'fa-toggle-on');
				var $deleteControl = $delete.clone(true);
				var $collapseControl = $t.find('.toggle-icon');
				var $insertBeforeControl = $insertBefore.clone(true);
				var $insertAfterControl = $insertAfter.clone(true);
				$controls.prepend($collapseControl);
				$controls.prepend($insertBeforeControl);
				$controls.prepend($insertAfterControl);
				var $closestRepeater = $t.closest('.InputfieldRepeater');
				if($closestRepeater.hasClass('InputfieldRepeaterHasSettings')) { // intentionally not using allowSettings var
					var $settingsToggle = $("<i class='fa fa-gear InputfieldRepeaterSettingsToggle ui-priority-secondary'></i>")
						.attr('title', cfg.labels.settings); 
					$controls.prepend($settingsToggle);
				}
				if(allowClone || !$closestRepeater.hasClass('InputfieldRepeaterNoAjaxAdd')) {
					$controls.prepend($clone.clone(true));
					// $controls.prepend($paste.clone(true));
				}
				$controls.prepend($toggleControl);
				$controls.prepend($deleteControl);
				$t.prepend($controls);
				$controls.css('background-color', $t.css('background-color'));
			}
			adjustItemLabel($item, false);
		});
	}

	/**
	 * Initialize a repeater
	 * 
	 * @param $this Can be an .InputfieldRepeater or an .InputfieldRepeaterItem
	 * 
	 */
	function initRepeater($this) {
		var $inputfields, $inputfieldRepeater, isItem;

		if($this.hasClass('InputfieldRepeaterItem')) {
			// single repeater item
			$inputfields = $this;
			$inputfieldRepeater = $this.closest('.InputfieldRepeater');
			isItem = true;
		} else {
			// enter repeater
			$inputfields = $this.find('.Inputfields').first();
			$inputfieldRepeater = $this;
			isItem = false;
		}

		if($inputfields.hasClass('InputfieldRepeaterInit')) return;
		if($('body').hasClass('touch-device')) $inputfieldRepeater.addClass('InputfieldRepeaterLoudControls');
		
		var renderValueMode = $inputfields.closest('.InputfieldRenderValueMode').length > 0;

		$inputfields.addClass('InputfieldRepeaterInit');
		
		//$("input.InputfieldRepeaterDelete", $this).parents('.InputfieldCheckbox').hide();

		if(isItem) {
			initHeaders($this.children('.InputfieldHeader'), $inputfieldRepeater, renderValueMode);
		} else {
			initHeaders($(".InputfieldRepeaterItem > .InputfieldHeader", $this), $inputfieldRepeater, renderValueMode);
		}

		if(renderValueMode) {
			// nothing further needed if only rendering the value
			initDepths($inputfieldRepeater);
			return;
		}

		// hovering the trash gives a preview of what clicking it would do
		$(".InputfieldRepeaterTrash", $this).on('mouseenter', function() {
			var $label = $(this).closest('label');
			if(!$label.parents().hasClass('InputfieldRepeaterDeletePending')) $label.addClass('ui-state-error');
			$label.find('.InputfieldRepeaterItemControls').css('background-color', $label.css('background-color'));
		}).on('mouseleave', function() {
			var $label = $(this).closest('label');
			if(!$label.parent().hasClass('InputfieldRepeaterDeletePending')) $label.removeClass('ui-state-error');
			$label.find('.InputfieldRepeaterItemControls').css('background-color', $label.css('background-color'));
		});

		// if we only init'd a single item, now make $inputfields refer to all repeater items for sortable init
		if(isItem) $inputfields = $inputfieldRepeater.find('.Inputfields').first();

		// setup the sortable
		initSortable($inputfieldRepeater, $inputfields);

		// setup the add links
		$(".InputfieldRepeaterAddLink:not(.InputfieldRepeaterAddLinkInit)", $inputfieldRepeater)
			.addClass('InputfieldRepeaterAddLinkInit')
			.on('click', eventAddLinkClick);

		// check for maximum items
		if($inputfieldRepeater.hasClass('InputfieldRepeaterMax')) {
			checkMinMax($inputfieldRepeater);
		}
	}

	/**
	 * When "max items" setting is used, this toggles whether or not "add" links are visible 
	 * 
	 * @param $inputfieldRepeater .InputfieldRepeater
	 * 
	 */
	function checkMinMax($inputfieldRepeater) {

		if(!$inputfieldRepeater.hasClass('InputfieldRepeaterMax') 
			&& !$inputfieldRepeater.hasClass('InputfieldRepeaterMin')) return;
		
		var max = parseInt($inputfieldRepeater.attr('data-max'));
		var min = parseInt($inputfieldRepeater.attr('data-min'));
		
		if(max <= 0 && min <= 0) return;
		
		var $content = $inputfieldRepeater.children('.InputfieldContent');
		var num = $content.children('.Inputfields')
			.children('li:not(.InputfieldRepeaterDeletePending):not(.InputfieldRepeaterOff):visible').length;
		var $addItem = $content.children('.InputfieldRepeaterAddItem');
		var cloneChange = '';
		var trashChange = '';
	
		if(max > 0) {
			if(num >= max) {
				$addItem.hide();
				cloneChange = 'hide';
			} else if(!$addItem.is(":visible")) {
				$addItem.show();
				cloneChange = 'show';
			}
		}
		
		if(min > 0) {
			if(num <= min) {
				trashChange = 'hide';	
				$content.addClass('InputfieldRepeaterTrashHidden');
			} else if($content.hasClass('InputfieldRepeaterTrashHidden')) {
				$content.removeClass('InputfieldRepeaterTrashHidden');
				trashChange = 'show';
			}
		}

		if(cloneChange.length || trashChange.length) {
			var $items = $content.children('.Inputfields').children('.InputfieldRepeaterItem');
			if(cloneChange.length) {
				// update the visibility of clone actions 
				$items.each(function() {
					var $clone = $(this).children('.InputfieldHeader').find('.InputfieldRepeaterClone');
					if(cloneChange === 'show') {
						$clone.removeClass('pw-icon-disabled');
					} else {
						$clone.addClass('pw-icon-disabled');
					}
				});
			}
			if(trashChange.length) {
				// update visibility of trash actions
				$items.each(function() {
					var $header = $(this).children('.InputfieldHeader');
					var $trash = $header.find('.InputfieldRepeaterTrash');
					var $toggle = $header.find('.InputfieldRepeaterToggle.fa-toggle-on');
					if(trashChange === 'show') {
						$trash.removeClass('pw-icon-disabled');
						$toggle.removeClass('pw-icon-disabled');
					} else {
						$trash.addClass('pw-icon-disabled');
						$toggle.addClass('pw-icon-disabled');
					}
				});
				if(trashChange == 'hide') {
					$content.children('.Inputfields').children('li.InputfieldRepeaterDeletePending').each(function() {
						var $trash = $(this).children('.InputfieldHeader').find('.InputfieldRepeaterTrash');
						$trash.removeClass('pw-icon-disabled');
					});
				}
			}
		}
	}

	/**
	 * Scroll to repeater item
	 * 
	 * @param $item
	 */
	function scrollToItem($item) {
		if($item.closest('.InputfieldRepeater').hasClass('InputfieldRepeaterNoScroll')) return;
		$('html, body').animate({scrollTop: $item.offset().top - 10}, 250, 'swing');
	}

	/**
	 * Run any scripts in the given HTML ajax data since jQuery will strip them
	 * 
	 * @param data
	 * 
	 */
	function runScripts(data) {
		// via owzim and Toutouwai 
		if(data.indexOf('</script>') == -1) return;
		var d = document.createElement('div');
		d.innerHTML = data;
		var scripts = d.querySelectorAll('.Inputfield script');
		$(scripts).each(function() {
			$.globalEval(this.text || this.textContent || this.innerHTML || '');
		});
	}

	/**
	 * Update state of the remembered open repeaters
	 * 
	 * Note: this records state for all repeaters on the page in cookie 'repeaters_open'
	 * that are configured to be remembered. 
	 * 
	 * @param $item .InputfieldRepeaterItem
	 * 
	 */
	function updateState($item) {
		if($item.closest('.InputfieldRepeaterRememberOpen').length < 1) return;
		var val = '';
		$(".InputfieldRepeaterItem:not(.InputfieldStateCollapsed)").each(function() {
			var id = parseInt($(this).attr('data-page'));
			if(id > 0) {
				val += id + '|';
			}
		});
		$.cookie('repeaters_open', val);
	}

	/**
	 * Clone a repeater item in place
	 * 
	 * @param $item
	 * @param pasteValue Optional cookie object value that was previously copied
	 * 
	 */
	function cloneRepeaterItem($item, insertBefore, pasteValue) {
		
		if(typeof pasteValue === "undefined") pasteValue = null;
		
		var actionName = pasteValue === null ? 'clone' : 'paste';
		var $addLink = $item.closest('.InputfieldRepeater').children('.InputfieldContent')
			.children('.InputfieldRepeaterAddItem').find('.InputfieldRepeaterAddLink').first();
		// $('html, body').animate({ scrollTop: $addLink.offset().top - 100}, 250, 'swing');

		$item.siblings('.InputfieldRepeaterInsertItem').remove();
		
		var depth = getItemDepth($item);
		var $newItem = $item.hasClass('InputfieldRepeaterNewItem') ? $item.clone() : $item.siblings('.InputfieldRepeaterNewItem').clone();
		var $nextItem = $item.next('.InputfieldRepeaterItem');
		var nextItemDepth = $nextItem.length ? getItemDepth($nextItem) : depth;
		var $prevItem = $item.prev('.InputfieldRepeaterItem');
		var prevItemDepth = $prevItem.length ? getItemDepth($prevItem) : depth;
	
		if(typeof insertBefore === "undefined") {
			insertBefore = depth < nextItemDepth;
		}
		
		$newItem.addClass('InputfieldRepeaterInsertItem').attr('id', $newItem.attr('id') + '-' + actionName); // .removeClass('InputfieldRepeaterNewItem); ?
		$newItem.find('.InputfieldHeader').html("<i class='fa fa-spin fa-spinner'></i>");
		
		if(insertBefore) {
			depth = getInsertBeforeItemDepth($item);
			$newItem.addClass('InputfieldRepeaterInsertItemBefore');
			$newItem.insertBefore($item);
		} else {
			depth = getInsertAfterItemDepth($item);
			$newItem.addClass('InputfieldRepeaterInsertItemAfter');
			$newItem.insertAfter($item);
		}
		
		setItemDepth($newItem, depth);
		
		$newItem.show();
		
		if(actionName === 'paste') {
			// data-clone attribute with 'pageID:itemID' indicates page ID and item ID to clone
			$addLink.attr('data-clone', pasteValue.page + ':' + pasteValue.item).trigger('click');
		} else {
			// current page ID is implied when only itemID is supplied
			$addLink.attr('data-clone', $item.attr('data-page')).trigger('click');
		}
	}

	/**
	 * Paste previously copied item
	 * 
	 * @param $item Item to insert before or after
	 * @param insertBefore True to insert before, false to insert after
	 * 
	 */
	function pasteRepeaterItem($item, insertBefore) {
		var $inputfield = $item.closest('.InputfieldRepeater');
		var fieldName = $inputfield.attr('data-name');
		var cookieName = copyPasteCookieName(fieldName);
		var copyValue = jQuery.cookie(cookieName); 
		if(copyValue) cloneRepeaterItem($item, insertBefore, copyValue);
	}

	/**
	 * Copy a repeater item to memory
	 * 
	 * @param $item
	 * 
	 */
	function copyRepeaterItem($item) {
		var $title = $('#Inputfield_title');
		var $name = $('#Inputfield__pw_page_name');
		var $inputfield = $item.closest('.InputfieldRepeater');
		var fieldName = $inputfield.attr('data-name');
		var copyValue = {
			page: parseInt($inputfield.attr('data-page')),
			item: parseInt($item.attr('data-page')),
			field: fieldName,
		};
		var cookieName = copyPasteCookieName(fieldName);
		jQuery.cookie(cookieName, copyValue);
	}

	/**
	 * Get the copy/paste cookie name
	 * 
	 * @param fieldName
	 * @returns {string}
	 * 
	 */
	function copyPasteCookieName(fieldName) {
		return fieldName + '_copy';
	}

	/**
	 * Initialization for document.ready
	 * 
	 */
	function init() {
		
		if(typeof ProcessWire.config.PagesVersions !== 'undefined') {
			pageVersion = ProcessWire.config.PagesVersions.version;
		}
		
		$('.InputfieldRepeater').each(function() {
			initRepeater($(this));
		});

		$(document)
			.on('reloaded', '.InputfieldRepeater', eventReloaded)
			.on('click', '.InputfieldRepeaterTrash', eventDeleteClick)
			.on('dblclick', '.InputfieldRepeaterTrash', eventDeleteDblClick)
			//.on('click', '.InputfieldRepeaterClone', eventCloneClick)
			.on('click', '.InputfieldRepeaterClone', eventCopyCloneClick)
			.on('click', '.InputfieldRepeaterPaste', eventPasteClick)
			.on('click', '.InputfieldRepeaterSettingsToggle', eventSettingsClick)
			.on('dblclick', '.InputfieldRepeaterToggle', eventOpenAllClick)
			.on('click', '.InputfieldRepeaterToggle', eventToggleClick)
			.on('opened', '.InputfieldRepeaterItem', eventItemOpened)
			.on('closed', '.InputfieldRepeaterItem', eventItemClosed)
			.on('openReady', '.InputfieldRepeaterItem', eventItemOpenReady)
			.on('click', '.InputfieldRepeaterInsertBefore', eventInsertBeforeClick)
			.on('click', '.InputfieldRepeaterInsertAfter', eventInsertAfterClick)
			.on('mouseover', '.InputfieldRepeaterInsertBefore', eventInsertMouseover)
			.on('mouseover', '.InputfieldRepeaterInsertAfter', eventInsertMouseover)
			.on('mouseout', '.InputfieldRepeaterInsertBefore', eventInsertMouseout)
			.on('mouseout', '.InputfieldRepeaterInsertAfter', eventInsertMouseout);
	}
	
	init();
}

jQuery(document).ready(function($) {
	InputfieldRepeater($);
});
