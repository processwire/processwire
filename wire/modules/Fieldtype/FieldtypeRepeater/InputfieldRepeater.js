/**
 * ProcessWire Repeater Inputfield Javascript
 *
 * Maintains a collection of fields that are repeated for any number of times.
 *
 * ProcessWire 3.x (development), Copyright 2015 by Ryan Cramer
 * https://processwire.com
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
	e.stopPropagation();
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
	
	if(!$item.closest('.InputfieldRepeaterRememberOpen').length) return;
	
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
		var isItem = true;
	} else {
		// enter repeater
		var $inputfields = $this.find('.Inputfields:eq(0)');
		var isItem = false;
	}
	
	//if(!$inputfields.length) return;
	if($inputfields.hasClass('InputfieldRepeaterInit')) return;
	$inputfields.addClass('InputfieldRepeaterInit');

	var renderValueMode = $inputfields.closest('.InputfieldRenderValueMode').length > 0;
	var $delete = $("<i class='fa fa-trash InputfieldRepeaterTrash'></i>").css('display', 'block');
	var $toggle = $("<i class='fa InputfieldRepeaterToggle' data-on='fa-toggle-on' data-off='fa-toggle-off'></i>");
	var cfg = ProcessWire.config.InputfieldRepeater;

	if(cfg) {
		$toggle.attr('title', cfg.labels.toggle);
		$delete.attr('title', cfg.labels.remove);
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
	
	$inputfields.sortable({
		items: '> li:not(.InputfieldRepeaterNewItem)',
		axis: 'y',
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
	});


	var $addLinks = $(".InputfieldRepeaterAddLink:not(.InputfieldRepeaterAddLinkInit)", $this);
	$addLinks.addClass('InputfieldRepeaterAddLinkInit');
	$addLinks.click(function() {

		var $addLink = $(this);
		var $inputfields = $(this).parent('p').prev('ul.Inputfields');
		var $numAddInput = $(this).parent().children('input');
		var newItemTotal = 0; // for noAjaxAdd mode
	
		function addRepeaterItem($addItem) {
			// make sure it has a unique ID
			var id = $addItem.attr('id') + '_';
			while($('#' + id).size() > 0) id += '_';
			$addItem.attr('id', id);
			$inputfields.append($addItem);
			$addItem.css('display', 'block');
			//$addItem.find('.InputfieldRepeaterTrash').click(InputfieldRepeaterDeleteClick);
			InputfieldRepeaterAdjustLabel($addItem, true);
			$addLink.trigger('repeateradd', [ $addItem ]);
		}

		var useAjax = $addLink.attr('data-noajax').length == 0;
		
		if(!useAjax) {
			var $newItem = $inputfields.children('.InputfieldRepeaterNewItem'); // for noAjaxAdd mode, non-editable new item
			newItemTotal = $newItem.length;
			if(newItemTotal > 0) {
				if(newItemTotal > 1) $newItem = $newItem.slice(0, 1);
				var $addItem = $newItem.clone(true)
				addRepeaterItem($addItem);
				$numAddInput.attr('value', newItemTotal);
			}
			
		} else {
			// get addItem from ajax
			var pageID = $addLink.closest('.InputfieldRepeater').attr('data-page'); // $("#Inputfield_id").val();
			var fieldName = $addLink.closest('.InputfieldRepeater').attr('id').replace('wrap_Inputfield_', '');
			var ajaxURL = ProcessWire.config.InputfieldRepeater.editorUrl + '?id=' + pageID + '&field=' + fieldName + '&repeater_add=' + $addLink.attr('data-type') + '&repeater_not=';
			var $spinner = $addLink.parent().find('.InputfieldRepeaterSpinner');
			
			$spinner.removeClass($spinner.attr('data-off')).addClass($spinner.attr('data-on'));	
		
			// determine which page IDs we don't accept for new items (because we already have them rendered)
			$inputfields.find('.InputfieldRepeaterUnpublished').each(function() { 
				ajaxURL += $(this).attr('data-page') + ',';
			});
			
			$.get(ajaxURL, function(data) {
				$spinner.removeClass($spinner.attr('data-on')).addClass($spinner.attr('data-off'));
				var $addItem = $(data).find(".InputfieldRepeaterItem.InputfieldRepeaterUnpublished");
				if(!$addItem.length) {
					// error
					// console.log("Can't find item: .InputfieldRepeaterItem.InputfieldRepeaterUnpublished");
					return;
				}
				//console.log($addItem);
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
				}, 500);
				InputfieldRepeaterUpdateState($addItem);
			});
		}
		
		return false;
	});
	
	//$(".InputfieldRepeaterUnpublished").children('.InputfieldHeader').addClass('ui-priority-secondary');

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
	$(document).on('click', '.InputfieldRepeaterTrash', InputfieldRepeaterDeleteClick);
	$(document).on('click', '.InputfieldRepeaterToggle', InputfieldRepeaterToggleClick);
	$(document).on('opened', '.InputfieldRepeaterItem', InputfieldRepeaterItemOpened);
	$(document).on('closed', '.InputfieldRepeaterItem', InputfieldRepeaterItemClosed);
	$(document).on('openReady', '.InputfieldRepeaterItem', InputfieldRepeaterItemOpenReady);

}); 

