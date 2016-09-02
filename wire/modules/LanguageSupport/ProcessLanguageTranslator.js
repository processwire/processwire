$(document).ready(function() {
	$("select.TranslationFileSelect").dblclick(function() {
		$("#submit_add").click();	
	});

	var $checkbox = $("input#untranslated");

	if($checkbox.length) {

		$checkbox.click(function() {
			if($(this).is(":checked")) {
				$(".Inputfield.translated").fadeOut();
			} else {
				$(".Inputfield.translated").fadeIn();
			}
		});

		if($checkbox.is(":checked")) $(".Inputfield.translated").hide();

		$(":input.translatable").blur(function() {
			if($(this).val().length) {
				$(this).closest('.Inputfield').removeClass('untranslated').addClass('translated');
			} else {
				$(this).closest('.Inputfield').removeClass('translated').addClass('untranslated');
			}
		});
	}
}); 