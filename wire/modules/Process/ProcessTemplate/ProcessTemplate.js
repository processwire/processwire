$(document).ready(function() {

	$("#wrap_filter_system input").click(function() {
		$(this).parents("form").submit();
	}); 	

	$("#filter_field").change(function() {
		$(this).parents("form").submit();
	}); 

	var redirectLoginClick = function() {
		if($("#redirectLogin_-1:checked").size() > 0) $("#wrap_redirectLoginURL").slideDown();
			else $("#wrap_redirectLoginURL").hide();
	}

	var adjustAccessFields = function() {

		var items = [ '#wrap_redirectLogin', '#wrap_guestSearchable' ]; 

		if($("#roles_37").is(":checked")) {
			$("#wrap_redirectLoginURL").hide();

			$(items).each(function(key, value) {
				var $item = $(value);
				if($item.is(".InputfieldStateCollapsed")) {
					$item.hide();
				} else {
					$item.slideUp();
				}
			}); 

			$("input.viewRoles").attr('checked', 'checked'); 	

		} else {

			$(items).each(function(key, value) {
				var $item = $(value); 
				if($item.is(":visible")) return;
				$item.slideDown("fast", function() {
					if(!$item.is(".InputfieldStateCollapsed")) return; 
					$item.find(".InputfieldStateToggle").click();
				}); 
			}); 
			redirectLoginClick();
		}
		
	}; 

	$("#wrap_useRoles input").click(function() {
		if($("#useRoles_1:checked").size() > 0) {
			$("#wrap_redirectLogin").hide();
			$("#wrap_guestSearchable").hide();
			$("#useRolesYes").slideDown();
			$("#wrap_useRoles > label").click();
			$("input.viewRoles").attr('checked', 'checked'); 
		} else {
			$("#useRolesYes").slideUp();
			$("#accessOverrides:visible").slideUp(); 
		}
	});

	if($("#useRoles_0:checked").size() > 0) {
		$("#useRolesYes").hide();
		$("#accessOverrides").hide();
	}
	

	$("#roles_37").click(adjustAccessFields);
	$("input.viewRoles:not(#roles_37)").click(function() {
		// prevent unchecking 'view' for other roles when 'guest' role is checked
		var $t = $(this);
		if($("#roles_37").is(":checked")) return false;
		return true; 
	}); 

	// when edit checked or unchecked, update the createRoles to match since they are dependent
	var editRolesClick = function() { 

		var $editRoles = $("#roles_editor input.editRoles");
		var numChecked = 0;
		
		$editRoles.each(function() { 
			var $t = $(this); 
			if($t.is(":disabled")) return false; 

			var $createRoles = $("input.createRoles[value=" + $t.attr('value') + "]"); 

			if($t.is(":checked")) {
				numChecked++;
				$createRoles.removeAttr('disabled'); 
			} else {
				$createRoles.removeAttr('checked').attr('disabled', 'disabled'); 
			}
		});
		
		if(numChecked) {
			$("#accessOverrides").slideDown();
		} else {
			$("#accessOverrides").hide();
		}

		return true; 
	}; 
	
	var editOrAddClick = function() {
		var numChecked = 0;
		$("#roles_editor input.editRoles").each(function() {
			if(!$(this).is(":disabled") && $(this).is(":checked")) numChecked++;
		});
		$("#roles_editor input.addRoles").each(function() {
			if(!$(this).is(":disabled") && $(this).is(":checked")) numChecked++;
		});
		numChecked > 0 ? $("#wrap_noInherit").slideDown() : $("#wrap_noInherit").hide();
	}; 
	
	$("#roles_editor input.editRoles").click(editRolesClick);
	$("#roles_editor input.editRoles, #roles_editor input.addRoles").click(editOrAddClick); 
		
	editRolesClick();
	editOrAddClick();

	$("#wrap_redirectLogin input").click(redirectLoginClick); 

	adjustAccessFields();
	redirectLoginClick();

	// instantiate the WireTabs
	var $templateEdit = $("#ProcessTemplateEdit"); 
	if($templateEdit.length > 0) {
		$templateEdit.find('script').remove();
		$templateEdit.WireTabs({
			items: $(".Inputfields li.WireTab"),
			id: 'TemplateEditTabs',
			skipRememberTabIDs: ['WireTabDelete']
		});
	}


	// export and import functions	
	$("#export_data").click(function() { $(this).select(); });
	
	$(".import_toggle input[type=radio]").change(function() {
		var $table = $(this).parents('p.import_toggle').next('table');
		var $fieldset = $(this).closest('.InputfieldFieldset'); 
		if($(this).is(":checked") && $(this).val() == 0) {
			$table.hide();
			$fieldset.addClass('ui-priority-secondary');
		} else {
			$table.show();
			$fieldset.removeClass('ui-priority-secondary');
		}
	}).change();
	
	$("#import_form table td:not(:first-child)").each(function() {
		var html = $(this).html();
		var refresh = false; 
		if(html.substring(0,1) == '{') {
			html = '<pre>' + html + '</pre>';
			html = html.replace(/<br>/g, "");
			refresh = true; 
		}
		if(refresh) $(this).html(html);
	}); 
	
	$("#fieldgroup_fields").change(function() {
		$("#_fieldgroup_fields_changed").val('changed'); 
	}); 

}); 
