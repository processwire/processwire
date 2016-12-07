/**
 * ProcessWire Repeater Inputfield Javascript
 *
 * Maintains a collection of fields that are repeated for any number of times.
 *
 * ProcessWire 3.x (development), Copyright 2015 by Ryan Cramer
 * https://processwire.com
 *
 */

var InputfieldRepeaterDepthSize = 50;

/**
 * Delete click event (single item)
 * 
 */
function InputfieldRepeaterDeleteClick(e) {
	
	var $parent = $(this).parent('label').parent('li');

	if($parent.is('.InputfieldRepeaterNewItem')) {
		// delete new item (noAjaxAdd mode)
		var $numAddInput = $parent.parent().parent().find('.InputfieldRepeaterAddItem').children('input');
		$numAddInput.attr('value', parseInt($numAddInput.attr('value')-1)); // total number of new items to add, minus 1
		$parent.remove();

	} else {
		// delete existing item
		var $checkbox = $parent.find('.InputfieldRepeaterDelete');

		if($checkbox.is(":checked")) {
			$checkbox.removeAttr('checked');
			$parent.children('label').removeClass('ui-state-error').addClass('ui-state-default');
			//if($parent.is('.InputfieldStateCollapsed')) $parent.toggleClass('InputfieldStateCollapsed', 100);
			$parent.removeClass('InputfieldRepeaterDeletePending');
		} else {
			$checkbox.attr('checked', 'checked');
			$parent.children('label').removeClass('ui-state-default').addClass('ui-state-error');
			if(!$parent.hasClass('InputfieldStateCollapsed')) $parent.toggleClass('InputfieldStateCollapsed', 100);
			$parent.addClass('InputfieldRepeaterDeletePending');
		}
	}
	
	InputfieldRepeaterCheckMax($parent.closest('.InputfieldRepeater'));
	
	e.stopPropagation();
}

/**
 * Delete double-click event (multi-item)
 * 
 */
function InputfieldRepeaterDeleteDblClick(e) {
	
	var $li = $(this).closest('li');
	var undelete = $li.hasClass('InputfieldRepeaterDeletePending');
	
	function selectAll() {
		$li.parent().children('li').each(function() {
			var $item = $(this);
			var $trashLink = $item.children('.InputfieldHeader').children('.InputfieldRepeaterTrash');
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
}

function InputfieldRepeaterCloneClick(e) {
	var $item = $(this).closest('.InputfieldRepeaterItem');
	ProcessWire.confirm(ProcessWire.config.InputfieldRepeater.labels.clone, function() {
		var itemID = $item.attr('data-page');
		var $addLink = $item.closest('.InputfieldRepeater').children('.InputfieldContent')
			.children('.InputfieldRepeaterAddItem').find('.InputfieldRepeaterAddLink:eq(0)');
		$addLink.attr('data-clone', itemID).click();
		$('html, body').animate({ scrollTop: $addLink.offset().top - 100}, 250, 'swing');
	});
	return false;
}

/**
 * Event handler for the "publish" toggle in the header of each repeater item
 * 
 */
function InputfieldRepeaterToggleClick(e) {
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
}

/**
 * Prepares for open of ajax loaded item (Inputfields "openReady" event handler)
 * 
 */
function InputfieldRepeaterItemOpenReady(e) {
	var $item = $(this);
	var $loaded = $item.find(".InputfieldRepeaterLoaded");
	if(parseInt($loaded.val()) > 0) return; // item already loaded
	$item.addClass('InputfieldRepeaterItemLoading');	
}

/**
 * Remember which repeater items are open 
 * 
 */
function InputfieldRepeaterUpdateState($item) {
	if($item.closest('.InputfieldRepeaterRememberOpen').length) {
		var val = '';
		$(".InputfieldRepeaterItem:not(.InputfieldStateCollapsed)").each(function() {
			var id = parseInt($(this).attr('data-page'));
			if(id > 0) {
				val += id + '|';
			}
		});
		$.cookie('repeaters_open', val);
	}
}

function InputfieldRepeaterCheckMax($inputfield) {
	if(!$inputfield.hasClass('InputfieldRepeaterMax')) return;
	var max = parseInt($inputfield.attr('data-max'));
	if(max <= 0) return;
	var $content = $inputfield.children('.InputfieldContent');
	var num = $content.children('.Inputfields').children('li:not(.InputfieldRepeaterDeletePending)').length;
	var $addItem = $content.children('.InputfieldRepeaterAddItem');
	if(num > max) {
		$addItem.hide();
	} else if(!$addItem.is(":visible")) {
		$addItem.show();
	}
}

function InputfieldRepeaterCheckDepths($inputfield) {
	$inputfield.find('.InputfieldRepeaterDepth').each(function() {
		var $depth = $(this);
		var depth = $depth.val();
		var $item = $depth.closest('.InputfieldRepeaterItem');
		var currentLeft = $item.css('margin-left');
		if(currentLeft == 'auto') currentLeft = 0;
		currentLeft = parseInt(currentLeft);
		var targetLeft = depth * InputfieldRepeaterDepthSize;
		if(targetLeft != currentLeft) {
			$item.css('margin-left', targetLeft + 'px');
			$item.data('lastLeft', targetLeft);
		}
	});
}

/**
 * Event called when repeater item is collapsed
 * 
 */
function InputfieldRepeaterItemClosed(e) {
	InputfieldRepeaterUpdateState($(this));
}

/**
 * Handles load of ajax editable items (Inputfields "opened" event handler)
 * 
 */
function InputfieldRepeaterItemOpened(e) {
	
	var $item = $(this);
	var $loaded = $item.find(".InputfieldRepeaterLoaded");

	InputfieldRepeaterUpdateState($item);
	
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
			InputfieldRepeaterInit($(this));
		});
		
		$content.slideDown('fast', function() {
			$spinner.removeClass('fa-spin fa-spinner').addClass('fa-arrows');
		});
		setTimeout(function() {
			$inputfields.find('.Inputfield').trigger('reloaded', ['InputfieldRepeaterItemEdit']);
		}, 50);
			
	});
}

/**
 * Update a repeater label for the given repeater $item, optionally incrementing the index number
 * 
 */
function InputfieldRepeaterAdjustLabel($item, doIncrement) {

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
 * Initialize a repeater field
 * 
 */
function InputfieldRepeaterInit($this) {

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
	
	//if(!$inputfields.length) return;
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
	
	function setupRepeaterHeaders($headers) {
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
				if(allowClone) $t.prepend($clone.clone(true));
				$t.prepend($toggle.clone(true).addClass($t.parent().hasClass('InputfieldRepeaterOff') ? 'fa-toggle-off' : 'fa-toggle-on'));
				$t.prepend($delete.clone(true));
			}
			InputfieldRepeaterAdjustLabel($item, false);
		});
	}

	if(isItem) {
		setupRepeaterHeaders($this.children('.InputfieldHeader'));
	} else {
		setupRepeaterHeaders($(".InputfieldRepeaterItem > .InputfieldHeader", $this));
	}
	
	if(renderValueMode) return;

	$(".InputfieldRepeaterDrag", $this).hover(function() {
		$(this).parent('label').addClass('ui-state-focus');
	}, function() {
		$(this).parent('label').removeClass('ui-state-focus');
	});

	$(".InputfieldRepeaterTrash", $this).hover(function() {
		var $label = $(this).parent('label');
		if(!$label.parent().is('.InputfieldRepeaterDeletePending')) $label.addClass('ui-state-error');
	}, function() {
		var $label = $(this).parent('label');
		if(!$label.parent().is('.InputfieldRepeaterDeletePending')) $label.removeClass('ui-state-error');
	});
	
	if(isItem) {
		// if we only init'd a single item, now make $inputfields refer to all repeater items for sortable init
		$inputfields = $this.closest('.InputfieldRepeater').find('.Inputfields:eq(0)');
	}
	
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

	var maxDepth = parseInt($inputfieldRepeater.attr('data-depth'));
	if(maxDepth > 0) {
		InputfieldRepeaterCheckDepths($inputfieldRepeater); 
		sortableOptions.grid = [ InputfieldRepeaterDepthSize, 1 ];
		sortableOptions.beforeStop = function(event, ui) {
			var lastLeft = ui.item.data('lastLeft');
			if(!lastLeft) lastLeft = 0;
			var left = lastLeft + ui.position.left;
			left -= InputfieldRepeaterDepthSize / 2;
			if(left > 25 && left < InputfieldRepeaterDepthSize) left = InputfieldRepeaterDepthSize;
			var depth = Math.round(left / InputfieldRepeaterDepthSize);
			if(depth < 1) depth = 0;
			if(depth > maxDepth) depth = maxDepth;
			if(depth) {
				ui.item.css('margin-left', (depth * InputfieldRepeaterDepthSize) + 'px');
			} else {
				ui.item.css('margin-left', 0);
			}
			ui.item.find('.InputfieldRepeaterDepth').val(depth);
			ui.item.data('lastLeft', left);
			ui.item.children('.InputfieldHeader').removeClass('ui-state-error');
		};
		sortableOptions.sort = function(event, ui) {
			var lastLeft = ui.item.data('lastLeft');
			if(!lastLeft) lastLeft = 0;
			var left = lastLeft + ui.position.left;
			var $header = ui.item.children('.InputfieldHeader');
			if(left > (InputfieldRepeaterDepthSize * maxDepth) + (InputfieldRepeaterDepthSize / 2)) {
				// beyond max depth allowed
				$header.addClass('ui-state-error');
			} else if($header.hasClass('ui-state-error')) {
				$header.removeClass('ui-state-error');
			}
		};
	} else {
		sortableOptions.axis = 'y';
	}
	
	$inputfields.sortable(sortableOptions);

	var $addLinks = $(".InputfieldRepeaterAddLink:not(.InputfieldRepeaterAddLinkInit)", $this);
	$addLinks.addClass('InputfieldRepeaterAddLinkInit');
	$addLinks.click(function() {

		var $addLink = $(this);
		var $inputfields = $(this).parent('p').prev('ul.Inputfields');
		var $numAddInput = $(this).parent().children('input');
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
			//$addItem.find('.InputfieldRepeaterTrash').click(InputfieldRepeaterDeleteClick);
			InputfieldRepeaterAdjustLabel($addItem, true);
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
				InputfieldRepeaterCheckMax($inputfieldRepeater);
			}
			
		} else {
			// get addItem from ajax
			var pageID = $addLink.closest('.InputfieldRepeater').attr('data-page'); // $("#Inputfield_id").val();
			var fieldName = $addLink.closest('.InputfieldRepeater').attr('id').replace('wrap_Inputfield_', '');
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
					// console.log("Can't find item: .InputfieldRepeaterItem.InputfieldRepeaterUnpublished");
					return;
				}
				addRepeaterItem($addItem);
				$addItem.wrap("<div />"); // wrap for inputfields.js $target
				InputfieldsInit($addItem.parent());
				InputfieldRepeaterInit($addItem);
				$addItem.unwrap(); // unwrap div once item initialized
				//$addItem.find('input.InputfieldRepeaterPublish').attr('value', 1);
				$addItem.find('.Inputfield').trigger('reloaded', [ 'InputfieldRepeaterItemAdd' ]);
				$addItem.find('.InputfieldRepeaterSort').val($inputfields.children().length);
				$('html, body').animate({
					scrollTop: $addItem.offset().top
				}, 500, 'swing');
				InputfieldRepeaterUpdateState($addItem);
				InputfieldRepeaterCheckMax($inputfieldRepeater);
				$nestedRepeaters = $addItem.find('.InputfieldRepeater'); 
				if($nestedRepeaters.length) {
					$nestedRepeaters.each(function() {
						InputfieldRepeaterInit($(this));
					});
				}
			});
		}
		
		return false;
	});
	
	//$(".InputfieldRepeaterUnpublished").children('.InputfieldHeader').addClass('ui-priority-secondary');
	
	if($inputfieldRepeater.hasClass('InputfieldRepeaterMax')) InputfieldRepeaterCheckMax($inputfieldRepeater);

}

$(document).ready(function() {
	
	$(".InputfieldRepeater").each(function() {
		InputfieldRepeaterInit($(this));
	});
	
	$(document).on('reloaded', '.InputfieldRepeater', function(event, source) {
		if(typeof source != "undefined") {
			if(source == 'InputfieldRepeaterItemEdit' || source == 'InputfieldRepeaterItemAdd') {
				event.stopPropagation();
				var $r = $(this).find(".InputfieldRepeater");
				if($r.length) InputfieldRepeaterInit($r);
				return;
			}
		}
		InputfieldRepeaterInit($(this));
	});
	
	$(document)
		.on('click', '.InputfieldRepeaterTrash', InputfieldRepeaterDeleteClick)
		.on('dblclick', '.InputfieldRepeaterTrash', InputfieldRepeaterDeleteDblClick)
		.on('click', '.InputfieldRepeaterClone', InputfieldRepeaterCloneClick)
		.on('click', '.InputfieldRepeaterToggle', InputfieldRepeaterToggleClick)
		.on('opened', '.InputfieldRepeaterItem', InputfieldRepeaterItemOpened)
		.on('closed', '.InputfieldRepeaterItem', InputfieldRepeaterItemClosed)
		.on('openReady', '.InputfieldRepeaterItem', InputfieldRepeaterItemOpenReady);
}); 

