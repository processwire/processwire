jQuery(document).ready(function() {
	$('#ProcessPagesExportImport').WireTabs({
		items: $(".Inputfields li.WireTab"),
		id: 'ProcessPagesExportImportTabs'
	});
	
	$(document).on('change', 'input.import-confirm', function() {
		var $item = $(this).closest('.import-form-item');
		if(!$(this).val().length) {
			$item.addClass('import-form-item-fail');
		} else {
			$item.removeClass('import-form-item-fail');
		}
	});

	// select all in export_json field on focus
	$('#export_json').on('focus', function(e) {
		$(this).one('mouseup', function() {
			$(this).select();
			return false;
		}).select();
	});
});
