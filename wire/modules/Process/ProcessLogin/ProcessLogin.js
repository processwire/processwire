$(document).ready(function() {

	// detect whether or not we've got a hidpi display and populate to config.hidpi
	if(window.devicePixelRatio > 1) {
		var hidpi = true
	} else {
		var media = 
			"(-webkit-min-device-pixel-ratio: 1.5), " +
			"(min--moz-device-pixel-ratio: 1.5), " + 
			"(-o-min-device-pixel-ratio: 3/2), " + 
			"(min-resolution: 1.5dppx)";
		var hidpi = window.matchMedia && window.matchMedia(media).matches;
	}
	$("#login_hidpi").val(hidpi ? 1 : 0);

	// via @Toutouwai #84
	$('#ProcessLoginForm').submit(function() {
		var $html = $('html');
		var touch = $html.data('whatintent') == 'touch' || $html.data('whatinput') == 'touch';
		$('#login_touch').val(touch ? 1 : 0);
		$('#login_width').val($(window).width());
	});
	
}); 