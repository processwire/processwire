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
	
	$("form#ProcessProfile").submit(function() {
		var $form = $(this);
		var $inputfields = $(".InputfieldStateChanged.InputfieldPassRequired");
		if(!$inputfields.length) return;
		var $pass = $('#_old_pass');
		if($pass.val().length) return;
		
		var pwAlert = ProcessWire.config.ProcessProfile.passRequiredAlert; 
		if(pwAlert.length && typeof vex != "undefined") {
			// use vex to display dialog box where they can enter password
			vex.dialog.open({
				message: pwAlert, 
				input: "<input type='password' placeholder='" + $pass.attr('placeholder') + "' id='_old_pass_confirm' />", 
				callback: function(data) {
					if(!data) return;
					var val = $('#_old_pass_confirm').val();	
					if(val.length) {
						$pass.val(val);
						setTimeout(function() { $('#submit_save_profile').click(); }, 200);
					}
				}
			});
		} else {
			// reveal the password field then focus it
			var $passWrap = $pass.closest('.InputfieldPassword');
			if($passWrap.hasClass('InputfieldStateCollapsed')) {
				setTimeout(function() {
					$passWrap.find('.InputfieldHeader').click();
				}, 200);
			}
			setTimeout(function() { $pass.focus(); }, 400);
		}
		return false;
	}); 
}); 