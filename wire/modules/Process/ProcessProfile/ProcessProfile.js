$(document).ready(function() {
	// from @horst-n:
	// prevent browser supported autocomplete for password fields (e.g. on Profilepage)
	// to force this, attribute autocomplete='off' needs to be set for the password field
	if($(".FieldtypePassword[autocomplete='off']").length) {	
		// simply set the value empty on document.ready doesn't work in FireFox,
		// but one second later, it works :)
		setTimeout(function() {
			$(".FieldtypePassword[autocomplete='off']").attr('value', '')
				.closest('.Inputfield').removeClass('InputfieldStateChanged'); // @GerardLuskin
		}, 1000);
	}
}); 