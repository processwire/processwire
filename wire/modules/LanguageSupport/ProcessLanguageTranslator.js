$(document).ready(function() {
	$("select.TranslationFileSelect").on('dblclick', function() {
		$("#submit_add").trigger('click');	
	});

	var $checkbox = $("input#untranslated");

	if($checkbox.length) {

		$checkbox.on('click', function() {
			if($(this).is(":checked")) {
				$(".Inputfield.translated").fadeOut();
			} else {
				$(".Inputfield.translated").fadeIn();
			}
		});

		if($checkbox.is(":checked")) $(".Inputfield.translated").hide();

		$(":input.translatable").on('blur', function() {
			if($(this).val().length) {
				$(this).closest('.Inputfield').removeClass('untranslated').addClass('translated');
			} else {
				$(this).closest('.Inputfield').removeClass('translated').addClass('untranslated');
			}
		});
	}
}); 
