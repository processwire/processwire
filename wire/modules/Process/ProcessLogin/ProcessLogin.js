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

	var maxSeconds = 300, // max age for login form before refreshing it (300=5min)
		queryMatch = location.search.toString().match(/[?&]r=(\d+)/), // match from query string
		queryTime = (queryMatch ? parseInt(queryMatch[1]) : 0), // query string time ?r=123456789
		clientTime = Math.floor(new Date().getTime() / 1000), // client UTC time
		serverTime = parseInt($('#login_start').val()), // server UTC time
		requestTime = (queryTime > serverTime ? queryTime : serverTime), // request time 
		startTime = (requestTime > clientTime ? clientTime : requestTime); // determined start time
	
	// force refresh of login form if 5 minutes go by without activity
	var watchTime = function() {
		var ts = Math.floor(new Date().getTime() / 1000);
		var elapsedSeconds = ts - startTime;
		if(elapsedSeconds > maxSeconds) {
			window.location.href = './?r=' + ts;
		}
	};
	
	// reload immediately if we received browser cached login form watchTime(); 
	watchTime();
	
	var interval = setInterval(watchTime, 5000);
	
	$('#login_name, #login_pass').on('keydown', function() {
		clearInterval(interval);
		interval = setInterval(watchTime, 5000);
	});
	
	// via @Toutouwai #84
	$('#ProcessLoginForm').on('submit', function() {
		var $html = $('html');
		var touch = $html.data('whatintent') == 'touch' || $html.data('whatinput') == 'touch';
		clearInterval(interval);
		$('#login_touch').val(touch ? 1 : 0);
		$('#login_width').val($(window).width());
	});

}); 
