$(document).ready(function() {

	$(".not_installed").parent("a").css('opacity', 0.6).on('click', function() {

		var name = $(this).children(".not_installed").attr('data-name');
		var $btn = $(".install_" + name + ":visible"); 
	
		if($btn.length) {	
			$btn.effect('highlight', 1000);
		} else {
			var color = $(this).css('color'); 
			$(this).closest('tr').find('.requires')
				.attr('data-color', $(this).css('color'))
				.css('color', color)
				.effect('highlight', 1000); 
		}
		
		return false;
	});

	$("button.ProcessModuleSettings").on('click', function() {
		var $a = $(this).parents('tr').find('.ConfigurableModule').parent('a');
		window.location.href = $a.attr('href') + '&collapse_info=1'; 
	}); 

    if($('#modules_form').length > 0) {
        $('#modules_form').WireTabs({
            items: $(".Inputfields li.WireTab"),
			rememberTabs: true
        });
    }
	
	$("select.modules_section_select").on('change', function() {
		var section = $(this).val();
		var $sections = $(this).parent('p').siblings('.modules_section')
		if(section == '') {
			$sections.show();
		} else {
			$sections.hide();
			$sections.filter('.modules_' + section).show();
		}
		document.cookie = $(this).attr('name') + '=' + section;
		return true; 
	}).trigger('change');


	$(document).on('click', '#head_button a', function() { 
		// when check for new modules is pressed, make sure the next screen goes to the 'New' tab
		document.cookie = 'WireTabs=tab_new_modules';
		return true; 
	});
	
	$("#Inputfield_new_seconds").on('change', function() {
		$('#submit_check').removeAttr('hidden').trigger('click');
		$(this).closest('form').trigger('submit');
	}); 
	
	$("#wrap_upload_module").removeClass('InputfieldItemList'); 

}); 
