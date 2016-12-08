/**
 * ProcessWire Repeater Inputfield Javascript
 *
 * Maintains a collection of fields that are repeated for any number of times.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
	 * Whether or not AdminThemeReno is present
	 * 
	 * @type {bool}
	 * 
	 */
	var isReno = $('body').hasClass('AdminThemeReno');
	
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
				if($r.length) initRepeater($r);
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
	
		var $header = $(this).closest('.InputfieldHeader');
		var $item = $header.parent();

		if($item.hasClass('InputfieldRepeaterNewItem')) {
			// delete new item (noAjaxAdd mode)
			var $numAddInput = $item.children('.InputfieldContent').children('.InputfieldRepeaterAddItem').children('input');
			$numAddInput.attr('value', parseInt($numAddInput.attr('value')-1)); // total number of new items to add, minus 1
			$item.remove();

		} else {
			// delete existing item
			var pageID = $item.attr('data-page');
			var $checkbox = $item.find('#delete_repeater' + pageID);

			if($checkbox.is(":checked")) {
				$checkbox.removeAttr('checked');
				$header.removeClass('ui-state-error').addClass('ui-state-default');
				//if($parent.is('.InputfieldStateCollapsed')) $parent.toggleClass('InputfieldStateCollapsed', 100);
				$item.removeClass('InputfieldRepeaterDeletePending');
			} else {
				$checkbox.attr('checked', 'checked');
				$header.removeClass('ui-state-default').addClass('ui-state-error');
				if(!$item.hasClass('InputfieldStateCollapsed')) {
					$header.find('.toggle-icon').click();
					//$item.toggleClass('InputfieldStateCollapsed', 100);
				}
				$item.addClass('InputfieldRepeaterDeletePending');
			}
			$header.find('.InputfieldRepeaterItemControls').css('background-color', $header.css('background-color'));
		}

		checkMax($item.closest('.InputfieldRepeater'));
		e.stopPropagation();
	};

	/**
	 * Event handler for when the "delete" link is double clicked
	 * 
	 * @param e
	 * 
	 */
	var eventDeleteDblClick = function(e) {
		var $li = $(this).closest('li');
		var undelete = $li.hasClass('InputfieldRepeaterDeletePending');

		function selectAll() {
			$li.parent().children('li').each(function() {
				var $item = $(this);
				var $trashLink = $item.children('.InputfieldHeader').find('.InputfieldRepeaterTrash');
				if($item.hasClass('InputfieldRepeaterDeletePending')) {
					if(undelete) $trashLink.click();
				} else {
					if(!undelete) $trashLink.click();
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
	 * @param e
	 * @returns {boolean}
	 * 
	 */
	var eventCloneClick = function(e) {
		var $item = $(this).closest('.InputfieldRepeaterItem');
		ProcessWire.confirm(ProcessWire.config.InputfieldRepeater.labels.clone, function() {
			var itemID = $item.attr('data-page');
			var $addLink = $item.closest('.InputfieldRepeater').children('.InputfieldContent')
				.children('.InputfieldRepeaterAddItem').find('.InputfieldRepeaterAddLink:eq(0)');
			$addLink.attr('data-clone', itemID).click();
			$('html, body').animate({ scrollTop: $addLink.offset().top - 100}, 250, 'swing');
		});
		return false;
	};

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
		
		if($this.hasClass(toggleOn)) {
			$this.removeClass(toggleOn).addClass(toggleOff);
			$item.addClass('InputfieldRepeaterUnpublished InputfieldRepeaterOff');
			$input.val('-1');
		} else {
			$this.removeClass(toggleOff).addClass(toggleOn);
			$item.removeClass('InputfieldRepeaterUnpublished InputfieldRepeaterOff');
			$input.val('1');
		}
		
		e.stopPropagation();
	};

	/**
	 * Event handler for when a repeater item is about to be opened
	 * 
	 * @param e
	 * 
	 */
	var eventItemOpenReady = function(e) {
		var $item = $(this);
		var $loaded = $item.find(".InputfieldRepeaterLoaded");
		if(parseInt($loaded.val()) > 0) return; // item already loaded
		$item.addClass('InputfieldRepeaterItemLoading');	
	};

	/**
	 * Event handler for when a repeater item is opened (primarily focused on ajax loaded items)
	 * 
	 * @param e
	 * 
	 */
	var eventItemOpened = function(e) {
		var $item = $(this);
		var $loaded = $item.find(".InputfieldRepeaterLoaded");

		updateState($item);

		if(parseInt($loaded.val()) > 0) return; // item already loaded

		$loaded.val('1');

		var $content = $item.find('.InputfieldContent').hide();
		var $repeater = $item.closest('.InputfieldRepeater');
		var pageID = $repeater.attr('data-page'); // $("#Inputfield_id").val();
		var itemID = parseInt($item.attr('data-page'));
		var repeaterID = $repeater.attr('id');
		var fieldName = repeaterID.replace('wrap_Inputfield_', '');
		var ajaxURL = ProcessWire.config.InputfieldRepeater.editorUrl + '?id=' + pageID + '&field=' + fieldName + '&repeater_edit=' + itemID;
		var $spinner = $item.find('.InputfieldRepeaterDrag');
		var $inputfields = $loaded.closest('.Inputfields');

		$spinner.removeClass('fa-arrows').addClass('fa-spin fa-spinner');
		repeaterID = repeaterID.replace(/_repeater\d+$/, '');

		$.get(ajaxURL, function(data) {
			var $inputs = $(data).find('#' + repeaterID + ' > ' +
				'.InputfieldContent > .Inputfields > ' +
				'.InputfieldRepeaterItem > .InputfieldContent > .Inputfields > .InputfieldWrapper > ' +
				'.Inputfields > .Inputfield');
			$inputfields.append($inputs);
			$item.removeClass('InputfieldRepeaterItemLoading');
			InputfieldsInit($inputfields);

			var $repeaters = $inputs.filter('.InputfieldRepeater');
			if($repeaters.length) $repeaters.each(function() {
				initRepeater($(this));
			});

			$content.slideDown('fast', function() {
				$spinner.removeClass('fa-spin fa-spinner').addClass('fa-arrows');
			});
			setTimeout(function() {
				$inputfields.find('.Inputfield').trigger('reloaded', ['InputfieldRepeaterItemEdit']);
			}, 50);

		});
	};

	/**
	 * Event handler for when a repeater item is closed
	 * 
	 * @param e
	 * 
	 */
	var eventItemClosed = function(e) {
		updateState($(this));
	};

	/**
	 * Event handler for "add" link clicks
	 * 
	 * Handles adding repeater items and initializing them
	 * 
	 * @param e
	 * @returns {boolean}
	 * 
	 */
	var eventAddLinkClick = function(e) {
		var $addLink = $(this);
		var $inputfields = $addLink.parent('p').prev('ul.Inputfields');
		var $inputfieldRepeater = $addLink.closest('.InputfieldRepeater');
		var $numAddInput = $addLink.parent().children('input');
		var newItemTotal = 0; // for noAjaxAdd mode
		var useAjax = $addLink.attr('data-noajax').length == 0;
		var cloneID = $addLink.attr('data-clone');

		function addRepeaterItem($addItem) {
			// make sure it has a unique ID
			var id = $addItem.attr('id') + '_';
			while($('#' + id).length > 0) id += '_';
			$addItem.attr('id', id);
			$inputfields.append($addItem);
			$addItem.css('display', 'block');
			adjustItemLabel($addItem, true);
			$addLink.trigger('repeateradd', [ $addItem ]);
		}

		if(typeof cloneID == "undefined" || !cloneID) cloneID = null;
		if(cloneID) $addLink.removeAttr('data-clone');

		if(!useAjax) {
			var $newItem = $inputfields.children('.InputfieldRepeaterNewItem'); // for noAjaxAdd mode, non-editable new item
			newItemTotal = $newItem.length;
			if(newItemTotal > 0) {
				if(newItemTotal > 1) $newItem = $newItem.slice(0, 1);
				var $addItem = $newItem.clone(true)
				addRepeaterItem($addItem);
				$numAddInput.attr('value', newItemTotal);
				checkMax($inputfieldRepeater);
			}
			return false;
		}

		// get addItem from ajax
		var pageID = $inputfieldRepeater.attr('data-page');
		var fieldName = $inputfieldRepeater.attr('id').replace('wrap_Inputfield_', '');
		var $spinner = $addLink.parent().find('.InputfieldRepeaterSpinner');
		var ajaxURL = ProcessWire.config.InputfieldRepeater.editorUrl + '?id=' + pageID + '&field=' + fieldName;

		$spinner.removeClass($spinner.attr('data-off')).addClass($spinner.attr('data-on'));

		if(cloneID) {
			ajaxURL += '&repeater_clone=' + cloneID;
		} else {
			ajaxURL += '&repeater_add=' + $addLink.attr('data-type');
		}

		// determine which page IDs we don't accept for new items (because we already have them rendered)
		var $unpublishedItems = $inputfields.find('.InputfieldRepeaterUnpublished');
		if($unpublishedItems.length) {
			ajaxURL += '&repeater_not=';
			$unpublishedItems.each(function() {
				ajaxURL += $(this).attr('data-page') + ',';
			});
		}

		$.get(ajaxURL, function(data) {
			//console.log(data);
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
			$addItem.find('.InputfieldRepeaterSort').val($inputfields.children().length);
			$('html, body').animate({
				scrollTop: $addItem.offset().top
			}, 500, 'swing');
			updateState($addItem);
			checkMax($inputfieldRepeater);
			$nestedRepeaters = $addItem.find('.InputfieldRepeater');
			if($nestedRepeaters.length) {
				$nestedRepeaters.each(function() {
					initRepeater($(this));
				});
			}
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
		
		var $repeater = $(this).closest('.InputfieldRepeater');
		var $items = $repeater.children('.InputfieldContent').children('.Inputfields').children('.InputfieldRepeaterItem');
		if(!$items.length) return false;
		var $item = $items.eq(0);
		
		if($item.hasClass('InputfieldStateCollapsed')) {
			var label = ProcessWire.config.InputfieldRepeater.labels.openAll;
			var selector = '.InputfieldStateCollapsed';
		} else {
			var label = ProcessWire.config.InputfieldRepeater.labels.collapseAll;
			var selector = '.InputfieldRepeaterItem:not(.InputfieldStateCollapsed)';
		}
		ProcessWire.confirm(label, function() {
			$items.filter(selector).each(function() {
				$(this).children('.InputfieldHeader').find('.toggle-icon').click();	
			});
		});
		return false;
	};
	
	/*** GENERAL FUNCTIONS **********************************************************************************/

	/**
	 * Given an InputfieldRepeaterItem update the label consistent with any present formatting sting
	 * 
	 * Primarily adjusts item count(s) and allowed for {secondary} text appearance
	 * 
	 * @param $item An .InputfieldRepeaterItem
	 * @param bool doIncrement Specify true to increment the item count value (like for new items)
	 * 
	 */
	function adjustItemLabel($item, doIncrement) {

		var $label = $item.children('label');
		var labelHTML = $label.html();
		var _labelHTML = labelHTML;

		if(doIncrement && labelHTML.indexOf('#') > -1) {
			num = $item.siblings('.InputfieldRepeaterItem:visible').length + 1;
			labelHTML = labelHTML.replace(/#[0-9]+/, '#' + num);
		}

		if(labelHTML.indexOf('{') > -1) {
			// parts of the label wrapped in {brackets} get different appearance
			labelHTML = labelHTML.replace(/\{/, '<span class="ui-priority-secondary" style="font-weight:normal">');
			labelHTML = labelHTML.replace(/}/, '</span>');
		}

		if(labelHTML != _labelHTML) {
			$label.html(labelHTML);
		}
	}

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

		var $depth = ui.item.find('.InputfieldRepeaterDepth');
		var depth = -1;
		var prevDepth = parseInt($depth.val());
		var left = ui.position.left;
	
		// AdminThemeReno has something different going on with the left positions, so we adjust for that here
		if(isReno) left -= depthSize;
		
		if(left < 0) {
			depth = prevDepth - Math.round(Math.abs(left) / depthSize);
			// console.log('decrease depth to: ' + depth);

		} else {
			depth = Math.round(left / depthSize) + prevDepth;
			// console.log('increase depth to: ' + depth);
		}

		if(depth < 1) {
			depth = 0;
		} else if(depth > maxDepth) {
			depth = maxDepth;
		}

		if(updateNow) {
			if(depth) {
				ui.item.css('margin-left', (depth * depthSize) + 'px');
			} else {
				ui.item.css('margin-left', 0);
			}

			$depth.val(depth);
			ui.item.children('.InputfieldHeader').removeClass('ui-state-error');
		}

		return depth;
	}
	
	/*** INIT FUNCTIONS **********************************************************************************/

	/**
	 * Initialize repeater item depths 
	 * 
	 * Applies a left-margin to repeater items consistent with with value in 
	 * each item's input.InputfieldRepeaterDepth hidden input. 
	 * 
	 * @param $inputfieldRepeater
	 * 
	 */
	function initDepths($inputfieldRepeater) {
		$inputfieldRepeater.find('.InputfieldRepeaterDepth').each(function() {
			var $depth = $(this);
			var depth = $depth.val();
			var $item = $depth.closest('.InputfieldRepeaterItem');
			var currentLeft = $item.css('margin-left');
			if(currentLeft == 'auto') currentLeft = 0;
			currentLeft = parseInt(currentLeft);
			var targetLeft = depth * depthSize;
			if(targetLeft != currentLeft) {
				$item.css('margin-left', targetLeft + 'px');
			}
		});
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
				ui.item.find('.InputfieldTinyMCE textarea').each(function() {
					tinyMCE.execCommand('mceRemoveControl', false, $(this).attr('id'));
				});
			},

			stop: function(e, ui) {
				if(maxDepth > 0) {
					sortableDepth(ui, maxDepth, true);
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
				ui.item.find('.InputfieldTinyMCE textarea').each(function() {
					tinyMCE.execCommand('mceAddControl', false, $(this).attr('id'));
				});
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
					$header.addClass('ui-state-error');
				} else if($header.hasClass('ui-state-error')) {
					// no problems
					$header.removeClass('ui-state-error');
				}
			};
		} else {
			sortableOptions.axis = 'y';
		}
		// apply "ui-state-focus" class when an item is being dragged
		$(".InputfieldRepeaterDrag", $inputfields).hover(function() {
			$(this).parent('label').addClass('ui-state-focus');
		}, function() {
			$(this).parent('label').removeClass('ui-state-focus');
		});

		$inputfields.sortable(sortableOptions);
	}

	/**
	 * Initialize a repeater
	 * 
	 * @param $this Can be an .InputfieldRepeater or an .InputfieldRepeaterItem
	 * 
	 */
	function initRepeater($this) {

		if($this.hasClass('InputfieldRepeaterItem')) {
			// single repeater item
			var $inputfields = $this;
			var $inputfieldRepeater = $this.closest('.InputfieldRepeater');
			var isItem = true;
		} else {
			// enter repeater
			var $inputfields = $this.find('.Inputfields:eq(0)');
			var $inputfieldRepeater = $this;
			var isItem = false;
		}

		if($inputfields.hasClass('InputfieldRepeaterInit')) return;

		$inputfields.addClass('InputfieldRepeaterInit');

		var renderValueMode = $inputfields.closest('.InputfieldRenderValueMode').length > 0;
		var $clone = $("<i class='fa fa-copy InputfieldRepeaterClone'></i>").css('display', 'block');
		var $delete = $("<i class='fa fa-trash InputfieldRepeaterTrash'></i>");
		var $toggle = $("<i class='fa InputfieldRepeaterToggle' data-on='fa-toggle-on' data-off='fa-toggle-off'></i>");
		var cfg = ProcessWire.config.InputfieldRepeater;
		var allowClone = !$inputfieldRepeater.hasClass('InputfieldRepeaterNoAjaxAdd');

		if(cfg) {
			$toggle.attr('title', cfg.labels.toggle);
			$delete.attr('title', cfg.labels.remove);
			$clone.attr('title', cfg.labels.clone);
		}

		$("input.InputfieldRepeaterDelete", $this).parents('.InputfieldCheckbox').hide();

		function initHeaders($headers) {
			$headers.each(function() {
				var $t = $(this);
				if($t.hasClass('InputfieldRepeaterHeaderInit')) return;
				var icon = 'fa-arrows';
				var $item = $t.parent();
				if($item.hasClass('InputfieldRepeaterNewItem')) {
					// noAjaxAdd mode
					icon = 'fa-plus';
					$t.addClass('ui-priority-secondary');
				}
				$t.addClass('ui-state-default InputfieldRepeaterHeaderInit');
				$t.prepend("<i class='fa fa-fw " + icon + " InputfieldRepeaterDrag'></i>")
				if(!renderValueMode) {
					//if(allowClone) $t.prepend($clone.clone(true));
					var $controls = $("<span class='InputfieldRepeaterItemControls'></span>");
					var $toggleControl = $toggle.clone(true).addClass($t.parent().hasClass('InputfieldRepeaterOff') ? 'fa-toggle-off' : 'fa-toggle-on');
					var $deleteControl = $delete.clone(true);
					var $collapseControl = $t.find('.toggle-icon');
					//$collapseControl.addClass('InputfieldRepeaterCollapse').removeClass('toggle-icon');
					$controls.prepend($collapseControl);
					if(allowClone) $controls.prepend($clone.clone(true));
					$controls.prepend($toggleControl).prepend($deleteControl);
					$t.prepend($controls);
					$controls.css('background-color', $t.css('background-color'));
				}
				adjustItemLabel($item, false);
			});
		}

		if(isItem) {
			initHeaders($this.children('.InputfieldHeader'));
		} else {
			initHeaders($(".InputfieldRepeaterItem > .InputfieldHeader", $this));
		}

		if(renderValueMode) {
			// nothing further needed if only rendering the value
			initDepths($inputfieldRepeater);
			return;
		}

		// hovering the trash gives a preview of what clicking it would do
		$(".InputfieldRepeaterTrash", $this).hover(function() {
			var $label = $(this).closest('label');
			if(!$label.parents().hasClass('InputfieldRepeaterDeletePending')) $label.addClass('ui-state-error');
			$label.find('.InputfieldRepeaterItemControls').css('background-color', $label.css('background-color'));
		}, function() {
			var $label = $(this).closest('label');
			if(!$label.parent().hasClass('InputfieldRepeaterDeletePending')) $label.removeClass('ui-state-error');
			$label.find('.InputfieldRepeaterItemControls').css('background-color', $label.css('background-color'));
		});

		// if we only init'd a single item, now make $inputfields refer to all repeater items for sortable init
		if(isItem) $inputfields = $inputfieldRepeater.find('.Inputfields:eq(0)');

		// setup the sortable
		initSortable($inputfieldRepeater, $inputfields);

		// setup the add links
		$(".InputfieldRepeaterAddLink:not(.InputfieldRepeaterAddLinkInit)", $inputfieldRepeater)
			.addClass('InputfieldRepeaterAddLinkInit')
			.click(eventAddLinkClick);

		// check for maximum items
		if($inputfieldRepeater.hasClass('InputfieldRepeaterMax')) {
			checkMax($inputfieldRepeater);
		}
	}

	/**
	 * When "max items" setting is used, this toggles whether or not "add" links are visible 
	 * 
	 * @todo Make this toggle the clone links as well
	 * @param $inputfieldRepeater .InputfieldRepeater
	 * 
	 */
	function checkMax($inputfieldRepeater) {
		if(!$inputfieldRepeater.hasClass('InputfieldRepeaterMax')) return;
		var max = parseInt($inputfieldRepeater.attr('data-max'));
		if(max <= 0) return;
		var $content = $inputfieldRepeater.children('.InputfieldContent');
		var num = $content.children('.Inputfields').children('li:not(.InputfieldRepeaterDeletePending)').length;
		var $addItem = $content.children('.InputfieldRepeaterAddItem');
		if(num > max) {
			$addItem.hide();
		} else if(!$addItem.is(":visible")) {
			$addItem.show();
		}
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
	 * Initialization for document.ready
	 * 
	 */
	function init() {
		
		$('.InputfieldRepeater').each(function() {
			initRepeater($(this));
		});

		$(document)
			.on('reloaded', '.InputfieldRepeater', eventReloaded)
			.on('click', '.InputfieldRepeaterTrash', eventDeleteClick)
			.on('dblclick', '.InputfieldRepeaterTrash', eventDeleteDblClick)
			.on('click', '.InputfieldRepeaterClone', eventCloneClick)
			.on('dblclick', '.InputfieldRepeaterToggle', eventOpenAllClick)
			.on('click', '.InputfieldRepeaterToggle', eventToggleClick)
			.on('opened', '.InputfieldRepeaterItem', eventItemOpened)
			.on('closed', '.InputfieldRepeaterItem', eventItemClosed)
			.on('openReady', '.InputfieldRepeaterItem', eventItemOpenReady);
	}
	
	init();
}

jQuery(document).ready(function($) {
	InputfieldRepeater($);
});
