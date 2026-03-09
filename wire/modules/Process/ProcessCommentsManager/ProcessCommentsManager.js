$(document).ready(function() {
	
	var ready = false;
	
	$(document).on('click', '.WireTabs a', function($event) {
		if(ready) window.location.href = $(this).attr('href'); 
		return false;
	});

	var $tabs = $('.WireTabs');
	$tabs.css('opacity', 1.0); 
	$tabs.find('.uk-active').removeClass('uk-active');
	$tabs.find('a.on').parent('li').trigger('click').addClass('uk-active');

	$("a.CommentTextEdit").on('click', function() {
		var $textarea = $("<textarea></textarea>");
		var $parent = $(this).closest('.CommentTextEditable');
		$parent.parent('.CommentText').removeClass('CommentTextOverflow');
		$textarea.attr('name', $parent.attr('id')); 
		$textarea.addClass($parent.attr('data-textarea-class'));
		//$textarea.height($parent.height()); 
		$(this).remove(); // remove edit link
		$textarea.val($parent.text()); 
		$parent.after($textarea);
		$parent.remove();
		return false; 
	}); 

	$(".CommentText").on('click', function() {
		$(this).find('a.CommentTextEdit').trigger('click');
		return false;
	}); 

	$(".CommentItem").each(function() {
		var $item = $(this);
		var $table = $item.find(".CommentItemInfo"); 
		var height = $table.height() + 30;
		var $text = $item.find(".CommentText"); 
		if($text.height() > height) {
			$text.addClass('CommentTextOverflow'); 
		}
	});

	/*
	// for cite, email and website editor inputs (@todo)
	$("a.CommentToggleSiblings").on('click', function() {
		$(this).siblings().each(function() {
			var $item = $(this);
			if($item.attr('hidden')) {
				$item.attr('hidden', false);
			} else {
				$item.attr('hidden', true);
			}
		});
	}); 
	*/
	
	$("#CommentLimitSelect").on('change', function() {
		window.location = './?limit=' + parseInt($(this).val());
	});
	$("#CommentListSort").on('change', function() {
		window.location = './?sort=' + $(this).val();
	}); 
	
	function commentCheckboxClicked($checkbox) {
		var $item = $checkbox.closest(".CommentItem");
		if($checkbox.is(":checked")) {
			$item.addClass('CommentChecked'); // .css('background-color', bgcolor);
		} else {
			$item.removeClass('CommentChecked'); // .css('background-color', '');
		}
	};

	$(".CommentCheckbox").on('click', function() {
		commentCheckboxClicked($(this));
	}); 
	
	$("#CommentCheckAll").on('click', function() {
		var $items = $(".CommentCheckbox");
		if($(this).is(":checked")) {
			// $items.attr('checked', 'checked'); // JQM
			$items.prop('checked', true);
		} else {
			// $items.removeAttr('checked'); // JQM
			$items.prop('checked', false);
		}
		$items.each(function() {
			commentCheckboxClicked($(this));
		});
	});
	
	$("#CommentActions").on('change', function() {
		var val = $(this).val();
		if(!val.length) return;
		var $checkedItems = $(".CommentChecked");
		if($checkedItems.length) {
			$checkedItems.each(function() {
				if(val == 'reset-upvotes') {
					// upvotes
					$(this).find(".CommentUpvotes > input").val(0).trigger('change');
				} else if(val == 'reset-downvotes') {
					// downvotes
					$(this).find(".CommentDownvotes > input").val(0).trigger('change');
				} else {
					// status
					// $(this).find(".CommentStatus > input[value='" + val + "']").trigger('click'); // radios
					$(this).find("select.CommentStatus").val(val); // select
				}
			});
			$checkedItems.effect('highlight', 500);
		} else {
			ProcessWire.alert($(this).attr('data-nochecked'));
		}
		$(this).val('');
	});

	$(document).on('change', '.CommentItem :input', function() {
		var $this = $(this);
		if($this.is("[type='checkbox']")) return;
		$(this).closest('.CommentItem').addClass('CommentItemChanged');
	});

	$("#CommentListForm").on('submit', function() {
		$(this).addClass('CommentListFormSubmitted');
	});
	
	window.addEventListener("beforeunload", function(e) {
		if($(".CommentListFormSubmitted").length) return;
		var $changes = $(".CommentItemChanged");
		if($changes.length == 0) return;
		var msg = $("#CommentListForm").attr('data-unsaved');
		(e || window.event).returnValue = msg; // Gecko and Trident
		return msg; // Gecko and WebKit
	});

	// for AdminThemeReno
	var color = $(".WireTabs a.on").css('border-top-color');
	$("#CommentListHeader").css('border-top-color', color);
	
	ready = true; 
}); 
