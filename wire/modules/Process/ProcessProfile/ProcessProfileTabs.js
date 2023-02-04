$(document).ready(function() {

	// instantiate the WireTabs
	$('#ProcessProfile:not(.ProcessProfileSingleField)').WireTabs({
		items: $("#ProcessProfile > .Inputfields > .InputfieldWrapper"),
		id: 'ProcessProfileTabs'
	});
});
