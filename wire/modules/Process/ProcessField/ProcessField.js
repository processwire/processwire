$(document).ready(function() {
	
	var fieldFilterFormChange = function() {
		$("#field_filter_form").trigger('submit');
	}; 
	
	$("#templates_id").on('change', fieldFilterFormChange); 
	$("#fieldtype").on('change', fieldFilterFormChange); 
	$("#wrap_show_system input").on('click', fieldFilterFormChange); 

	var $asmListItemStatus = $("#asmListItemStatus"); 
	
	// setup the primary column width slider
	var $columnWidth = $("#columnWidth"); 
	
	function setAsmListItemStatus() {
		var tpl = $asmListItemStatus.attr('data-tpl');
		if(!tpl) return;
		var showIf = $("#Inputfield_showIf").val();
		var required = $("#Inputfield_required").is(":checked") ? true : false;
	
		if(showIf && showIf.length > 0) tpl = "<i class='fa fa-question-circle'></i>" + tpl;
		if(required) tpl = "<i class='fa fa-asterisk'></i>" + tpl; 
		var w = parseInt($columnWidth.val());
		if(w < 1 || w > 100) w = 100;
		if(w > 0) w = w + '%';
		tpl = tpl.replace('%', w);
		
		$asmListItemStatus.val(tpl);
	}
	
	$("#Inputfield_showIf").on('change', setAsmListItemStatus);
	$("#Inputfield_required").on('change', setAsmListItemStatus);
	setAsmListItemStatus();

	$('.columnWidthInput').each(function() {
		var $columnWidth = $(this);
		var $slider = $("<div class='InputfieldColumnWidthSlider'></div>");
		var columnWidthVal = parseInt($columnWidth.val());
		
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
				if($columnWidth.prop('id') === 'columnWidth') setAsmListItemStatus();
			}
		});
		// enables columnWidth to be populated in ProcessTemplate's asmSelect status field
		// $columnWidth.addClass('asmListItemStatus');
		// $("#asmListItemStatus").val($columnWidth.val());

		// update the slider if the columnWidth field is changed manually	
		$columnWidth.on('change', function() {
			var val = parseInt($(this).val());
			if(val > 100) val = 100;
			if(val < 10) val = 10;
			$(this).val(val + '%');
			$slider.slider('option', 'value', val);
		});
	});

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
	$("#fieldgroupContextSelect").on('change', function() {
		var field_id = $("#Inputfield_id").val();	
		var fieldgroup_id = $(this).val();
		var href = './edit?id=' + field_id;
		if(fieldgroup_id > 0)  href += '&fieldgroup_id=' + fieldgroup_id;
		window.location = href; 
	});
	
	$("a.fieldFlag").on('click', function() { 
		if($(this).attr('href') === '#') return false; 
	});

	$("#export_data").on('click', function() { $(this).trigger('select'); });

	// export and import functions	
	$(".import_toggle input[type=radio]").on('change', function() {
		var $table = $(this).parents('p.import_toggle').next('table');
		var $fieldset = $(this).closest('.InputfieldFieldset'); 
		if($(this).is(":checked") && $(this).val() == 0) {
			$table.hide();
			$fieldset.addClass('ui-priority-secondary');
		} else {
			$table.show();
			$fieldset.removeClass('ui-priority-secondary');
		}
	}).trigger('change');

	// allow processInput to ignore this field when applicable
	$("#wrap_Inputfield_send_templates").find(":input").on('change', function() {
		$("#_send_templates_changed").val('changed'); 
	});

	// setup access control tab
	$("#viewRoles_37").on('click', function() {
		// if guest has view, then all have view
		// if($(this).is(":checked")) $("input.viewRoles").attr('checked', 'checked'); // JQM
		if($(this).is(":checked")) $("input.viewRoles").prop('checked', true);
	});
	$("input.viewRoles:not(#viewRoles_37)").on('click', function() {
		// prevent unchecking 'view' for other roles when 'guest' role is checked
		if($("#viewRoles_37").is(":checked")) return false;
		return true;
	});
	$("input.editRoles:not(:disabled)").on('click', function() {
		if($(this).is(":checked")) {
			// if editable is checked, then viewable must also be checked
			// $(this).closest('tr').find("input.viewRoles").attr('checked', 'checked'); // JQM
			$(this).closest('tr').find("input.viewRoles").prop('checked', true); 
		}
	}); 

	// select-all link for overrides tab
	$(".override-select-all").on('click', function() {
		var $checkboxes = $(this).closest('table').find("input[type=checkbox]");
		if($(this).hasClass('override-checked')) {
			// $checkboxes.removeAttr('checked'); // JQM
			$checkboxes.prop('checked', false);
			$(this).removeClass('override-checked'); 
		} else {
			// $checkboxes.attr('checked', 'checked'); // JQM
			$checkboxes.prop('checked', true);
			$(this).addClass('override-checked');
		}
		return false;
	});

	// update overrides table if anything was changed in a modal
	$(document).on('pw-modal-closed', 'a', function(e, ui) {
		if(!$('#tab-overrides').is(':visible')) return;
		Inputfields.reload('#Inputfield_overrides_table');
	}); 


});
