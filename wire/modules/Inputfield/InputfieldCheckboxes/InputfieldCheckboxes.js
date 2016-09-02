jQuery(document).ready(function($) {
	// @awt2542 PR #867
	var lastChecked = null;
	$(document).on('click', '.InputfieldCheckboxes ul input[type=checkbox]', function(e) {
		var $checkboxes = $(this).closest('ul').find('input[type=checkbox]');
		if(!lastChecked) {
			lastChecked = this;
			return;
		}
		if(e.shiftKey) {
			var start = $checkboxes.index(this);
			var end = $checkboxes.index(lastChecked);
			$checkboxes.slice(Math.min(start,end), Math.max(start,end)+ 1).attr('checked', lastChecked.checked);
		}
		lastChecked = this;
	});
	
	// abandon columns if it results in multi-line label wrapping
	$(document).on('resized', '.InputfieldCheckboxes, .InputfieldPage', function(e) {
		$(this).find('.InputfieldCheckboxesColumns').each(function() {
			var $ul = $(this);
			var height = 0;
			var collapseColumns = false;
			$ul.children('li').each(function() {
				var $li = $(this);
				if(collapseColumns) return;
				if(!height) {
					height = $li.height();
					return;
				}
				var diff = Math.abs($li.height() - height); 
				if(diff > 5) collapseColumns = true; 
			});
			if(collapseColumns) {
				$ul.removeClass('InputfieldCheckboxesColumns');	
				$ul.find('li').css('width', '100%');
			}
		});
	});
	$(document).on('reloaded', '.InputfieldCheckboxes, .InputfieldPage', function(e) {
		if($(this).find('.InputfieldCheckboxesColumns').length) $(this).trigger('resized');
	});
	$(".InputfieldCheckboxesColumns").closest('.Inputfield').trigger('resized');
}); 