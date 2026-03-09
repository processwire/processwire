$(document).ready(function() {
	$(".ui-button").on('mouseenter', function() {
		$(this).removeClass("ui-state-default").addClass("ui-state-hover");
	}).on('mouseleave', function() {
		$(this).removeClass("ui-state-hover").addClass("ui-state-default");
	}).on('click', function() {
		$(this).removeClass("ui-state-default").addClass("ui-state-active");
	});
});
