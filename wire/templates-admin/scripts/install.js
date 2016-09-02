$(document).ready(function() {
	$(".ui-button").hover(function() {
		$(this).removeClass("ui-state-default").addClass("ui-state-hover");
	}, function() {
		$(this).removeClass("ui-state-hover").addClass("ui-state-default");
	}).click(function() {
		$(this).removeClass("ui-state-default").addClass("ui-state-active");
	});
});

