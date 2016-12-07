
function ProcessRoleUpdatePermissions(init, $checkbox) {
	
	var $inputfield = $("#wrap_Inputfield_permissions");
	var $checkboxes = $checkbox == null ? $inputfield.find(".permission > input[type=checkbox]") : $checkbox;

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
			
		} else {
			$children.find("input:not(:disabled)").removeAttr('checked');
			init ? $children.hide() : $children.fadeOut();
			$row.removeClass('permission-checked');
		}
	});
}

$(document).ready(function() {

	var $pageView = $("#Inputfield_permissions_36"); 
	if(!$pageView.is(":checked")) $pageView.attr('checked', 'checked'); 

	ProcessRoleUpdatePermissions(true, null);
	
	$("#wrap_Inputfield_permissions").on("click", "input[type=checkbox], label.checkbox-disabled", function(e) {
	
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
	
}); 
