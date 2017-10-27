
function ProcessRoleUpdatePermissions(init, $checkbox) {
	
	var $inputfield = $("#wrap_Inputfield_permissions");
	var $checkboxes = $checkbox == null ? $inputfield.find("input.global-permission") : $checkbox;

	if(init) {
		// update row classes to be the same as the label classes
		$checkboxes.each(function() {
			var $input = $(this);
			var $label = $input.closest('label');
			var $row = $input.closest('tr');
			$row.addClass($label.attr('class'));
		});
	}
		
	$checkboxes.each(function() {
		
		var $input = $(this);
		var $label = $input.closest('label');
		var $row = $input.closest('tr');
		var $parent = $('#' + $label.attr('data-parent'));
		var name = $label.text();
		var level = parseInt($label.attr('data-level'));
		var $children = $row.nextAll(".parent-" + $label.attr('id'));
		
		$row.addClass($label.attr('id'));

		if($input.is(":checked")) {
			$children = $children.filter(".level" + (level+1));
			init ? $children.show() : $children.fadeIn();
			$row.addClass('permission-checked');
			if($row.hasClass('permission-page-edit')) {
				if(!$row.find('.template-permissions-open').length) {
					$row.find('.toggle-template-permissions').click();
				}
			}
			
		} else {
			$children.find("input.global-permission:not(:disabled)").removeAttr('checked');
			init ? $children.hide() : $children.fadeOut();
			$row.removeClass('permission-checked');
			if($row.hasClass('permission-page-edit')) {
				if($row.find('.template-permissions-open').length) {
					$row.find('.toggle-template-permissions').click();
				}
			}
		}
	});
}

$(document).ready(function() {

	var $pageView = $("#Inputfield_permissions_36"); 
	if(!$pageView.is(":checked")) $pageView.attr('checked', 'checked'); 

	ProcessRoleUpdatePermissions(true, null);
	
	$("#wrap_Inputfield_permissions").on("click", "input.global-permission, label.checkbox-disabled", function(e) {
	
		if($(this).is("label")) {
			var $label = $(this);
			var $checkbox = $label.children("input");
		} else {
			var $checkbox = $(this);
			var $label = $checkbox.parent();
		}
		
		var alertText = $label.attr('data-alert');
		var confirmText = $label.attr('data-confirm');
		
		if(typeof alertText != "undefined" && alertText.length) {
			ProcessWire.alert(alertText);
			return false;
		} else if(typeof confirmText != "undefined" && confirmText.length) {
			if($checkbox.is(":checked")) {
				if(!confirm(confirmText)) return false;
			}
		}
	
		if($(this).is("input")) {
			var $checkbox = $(this);
			setTimeout(function() {
				ProcessRoleUpdatePermissions(false, $checkbox);
			}, 100);
		}
	});
	
	$(".toggle-template-permissions").click(function() {
		var $div = $(this).closest('tr').find('.template-permissions');
		if($div.hasClass('template-permissions-open')) {
			$div.fadeOut('fast', function() { 
				$div.removeClass('template-permissions-open');
			});
		} else {
			$div.fadeIn('fast', function() {
				$div.addClass('template-permissions-open');
			}); 
		}
		var $icon = $(this).find('i');
		$icon.toggleClass($icon.attr('data-toggle'));
		return false;
	});

	// make some of the open when page loads
	$('.template-permissions-click').each(function() {
		$(this).closest('tr').find('.toggle-template-permissions').click();
		$(this).removeClass('template-permissions-click');
	}); 
	
	$('.permission-title').click(function() {
		$(this).closest('tr').find('.toggle-template-permissions').click();
	}); 

	// ensure checkbox classes are consistent (like for uk-checkbox)
	a = $('input.global-permission:eq(0)'); 
	b = $('<div />').addClass(a.attr('class')).removeClass('permission permission-checked global-permission');
	c = $('input.template-permission').addClass(b.attr('class'));
	
}); 

