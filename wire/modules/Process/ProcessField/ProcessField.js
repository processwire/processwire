$(document).ready(function() {
	
	var fieldFilterFormChange = function() {
		$("#field_filter_form").submit();
	}; 
	$("#templates_id").change(fieldFilterFormChange); 
	$("#fieldtype").change(fieldFilterFormChange); 
	$("#wrap_show_system input").click(fieldFilterFormChange); 

	var $asmListItemStatus = $("#asmListItemStatus"); 
	
	// setup the column width slider
	var $columnWidth = $("#columnWidth");
	
	function setAsmListItemStatus() {
		var tpl = $asmListItemStatus.attr('data-tpl');
		if(!tpl) return;
		var showIf = $("#Inputfield_showIf").val();
		var required = $("#Inputfield_required").is(":checked") ? true : false;
	
		if(showIf && showIf.length > 0) tpl = "<i class='fa fa-question-circle'></i>" + tpl;
		if(required) tpl = "<i class='fa fa-asterisk'></i>" + tpl; 
		var w = parseInt($columnWidth.val());
		if(w == 100) w = 0;
		if(w > 0) w = w + '%';
			else w = '';
		tpl = tpl.replace('%', w);
		
		$asmListItemStatus.val(tpl);
	}
	
	$("#Inputfield_showIf").change(setAsmListItemStatus);
	$("#Inputfield_required").change(setAsmListItemStatus);
	setAsmListItemStatus();

	if($columnWidth.length > 0) { 
		var $slider = $("<div class='InputfieldColumnWidthSlider'></div>");
		var columnWidthVal = parseInt($("#columnWidth").val());
		$columnWidth.val(columnWidthVal + '%'); 
		$columnWidth.after($slider);
		$slider.slider({
			range: 'min',
			min: 10,
			max: 100,
			value: parseInt($columnWidth.val()),
			slide: function(e, ui) {
				var val = ui.value + '%';
				$columnWidth.val(val).trigger('change');
				setAsmListItemStatus();
			}
		});
		// enables columnWidth to be populated in ProcessTemplate's asmSelect status field
		// $columnWidth.addClass('asmListItemStatus');
		// $("#asmListItemStatus").val($columnWidth.val());
		
		// update the slider if the columnWidth field is changed manually	
		$columnWidth.change(function() {
			var val = parseInt($(this).val());
			if(val > 100) val = 100; 
			if(val < 10) val = 10; 
			$(this).val(val + '%');
			$slider.slider('option', 'value', val); 
		}); 
	}

	// instantiate the WireTabs
	var $fieldEdit = $("#ProcessFieldEdit"); 
	if($fieldEdit.length > 0 && $('li.WireTab').length > 1) {
		$fieldEdit.find('script').remove();
		$fieldEdit.WireTabs({
			items: $(".Inputfields li.WireTab"),
			id: 'FieldEditTabs',
			skipRememberTabIDs: ['delete']
			});
	}

	// change fieldgroup context
	$("#fieldgroupContextSelect").change(function() {
		var field_id = $("#Inputfield_id").val();	
		var fieldgroup_id = $(this).val();
		var href = './edit?id=' + field_id;
		if(fieldgroup_id > 0)  href += '&fieldgroup_id=' + fieldgroup_id;
		window.location = href; 
	});
	
	$("a.fieldFlag").click(function() { return false; });

	$("#export_data").click(function() { $(this).select(); });

	// export and import functions	
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

	// allow processInput to ignore this field when applicable
	$("#wrap_Inputfield_send_templates").find(":input").change(function() {
		$("#_send_templates_changed").val('changed'); 
	});

	// setup access control tab
	$("#viewRoles_37").click(function() {
		// if guest has view, then all have view
		if($(this).is(":checked")) $("input.viewRoles").attr('checked', 'checked');
	});
	$("input.viewRoles:not(#viewRoles_37)").click(function() {
		// prevent unchecking 'view' for other roles when 'guest' role is checked
		if($("#viewRoles_37").is(":checked")) return false;
		return true;
	});
	$("input.editRoles:not(:disabled)").click(function() {
		if($(this).is(":checked")) {
			// if editable is checked, then viewable must also be checked
			$(this).closest('tr').find("input.viewRoles").attr('checked', 'checked'); 
		}
	}); 

	// select-all link for overrides tab
	$(".override-select-all").click(function() {
		var $checkboxes = $(this).closest('table').find("input[type=checkbox]");
		if($(this).hasClass('override-checked')) {
			$checkboxes.removeAttr('checked');
			$(this).removeClass('override-checked'); 
		} else {
			$checkboxes.attr('checked', 'checked');
			$(this).addClass('override-checked');
		}
		return false;
	});


});
