function initPageEditForm() {

	// instantiate the WireTabs
	$('#ProcessPageEdit:not(.ProcessPageEditSingleField)').WireTabs({
		items: $("#ProcessPageEdit > .Inputfields > .InputfieldWrapper"), 
		id: 'PageEditTabs',
		skipRememberTabIDs: ['ProcessPageEditDelete']
	});
	
	// WireTabs gives each tab link that it creates an ID equal to the ID on the tab content
	// except that the link ID is preceded by an underscore

	// trigger a submit_delete submission. this is necessary because when submit_delete is an <input type='submit'> then 
	// some browsers call it (rather than submit_save) when the enter key is pressed in a text field. This solution
	// by passes that undesirable behavior. 
	$("#submit_delete").click(function() {
		if(!$("#delete_page").is(":checked")) {
			$("#wrap_delete_page label").effect('highlight', {}, 500); 
			return;
		}
		$(this).before("<input type='hidden' name='submit_delete' value='1' />"); 
		$("#ProcessPageEdit").submit();
	}); 

	$(document).on('click', '#AddPageBtn', function() {
		// prevent Firefox from sending two requests for same click
		return false;
	}).on('click', 'button[type=submit]', function(e) {
		// alert user when they try to save and an upload is in progress
		if($('body').hasClass('pw-uploading')) {
			return confirm($('#ProcessPageEdit').attr('data-uploading')); 
		}
	});
	
	if(typeof InputfieldSubmitDropdown != "undefined") {
		var $dropdownTemplate = $("ul.pw-button-dropdown:not(.pw-button-dropdown-init)");
		$("button[type=submit]").each(function() {
			var $button = $(this);
			var name = $button.attr('name');
			if($button.hasClass('pw-no-dropdown')) return;
			if(name.indexOf('submit') == -1 || name.indexOf('_draft') > -1) return;
			if(name.indexOf('_save') == -1 && name.indexOf('_publish') == -1) return;
			InputfieldSubmitDropdown.init($button, $dropdownTemplate);
		});
	}

	var $viewLink = $("#_ProcessPageEditView");
	var $viewMenu = $("#_ProcessPageEditViewDropdown");
	var color = $viewLink.css('color');
	
	$("#_ProcessPageEditViewDropdownToggle").css('color', color);
	
	$viewLink.click(function() {
		var action = $viewLink.attr('data-action');
		if(action == 'this' || action == 'new' || !action.length) return true; 
		$viewMenu.find(".page-view-action-" + action + " > a").click();
		return false;
	}); 

	var $template = $('#template');
	var templateID = $template.val();
	$template.on('change', function() {
		if($(this).val() == templateID) {
			$('.pw-button-dropdown-toggle').trigger('pw-button-dropdown-on');
		} else {
			$('.pw-button-dropdown-toggle').trigger('pw-button-dropdown-off');
		}
	});

	// hide other buttons when on delete tab, and restore them when leaving delete tab
	$(document).on('wiretabclick', function(event, $newTab, $oldTab) {
		if($newTab.attr('id') == 'ProcessPageEditDelete') {
			$(".InputfieldSubmit:not(#wrap_submit_delete):visible").addClass('pw-hidden-tmp').hide();
		} else if($oldTab.attr('id') == 'ProcessPageEditDelete') {
			$(".InputfieldSubmit.pw-hidden-tmp").removeClass('pw-hidden-tmp').show();
		}
	});
}
