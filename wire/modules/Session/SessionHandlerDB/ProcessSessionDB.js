var refreshSessionTimer = null;

function refreshSessionList() {
	var $icon = $("#submit_session i, #submit_session_copy i");
	$icon.removeClass('fa-refresh').addClass('fa-spin fa-spinner');
	
	$.get("./", function (data) {
		$("#SessionList").html(data);
		refreshSessionTimer = setTimeout('refreshSessionList()', 5000);
		$icon.removeClass('fa-spin fa-spinner').addClass('fa-refresh'); 
	});
}

$(document).ready(function() {
	refreshSessionTimer = setTimeout('refreshSessionList()', 5000); 
}); 
