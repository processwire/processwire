function InputfieldPageTableDialog() {

	var $a = $(this);
	var url = $a.attr('data-url');
	var title = $a.attr('data-title'); 
	var closeOnSave = true; 
	var $container = $(this).parents('.InputfieldPageTableContainer'); 
	var dialogPageID = 0;
	var noclose = parseInt($container.attr('data-noclose')); 
	var modalSettings = {
		close: function(event, ui) {
			if(dialogPageID > 0) {
				var ajaxURL = $container.attr('data-url') + '&InputfieldPageTableAdd=' + dialogPageID;
				var sort = $container.siblings(".InputfieldPageTableSort").val();
				if(typeof sort != "undefined" && sort.length) ajaxURL += '&InputfieldPageTableSort=' + sort.replace(/\|/g, ',');
				$.get(ajaxURL, function(data) {
					$container.html(data);
					$container.find(".Inputfield").trigger('reloaded', ['InputfieldPageTable']);
					$container.effect('highlight', 500, function() {
						var $table = $container.find('table');
						$table.find('tbody').css('overflow', 'visible');
						InputfieldPageTableSortable($table);
						
						// restore appearnace of any items marked for deletion
						var deleteIDs = $container.siblings("input.InputfieldPageTableDelete").eq(0).val().split('|');
						if(deleteIDs.length) {
							for(var n = 0; n < deleteIDs.length; n++) {
								var deleteID = deleteIDs[n];
								$table.find("tr[data-id=" + deleteID + "]")
									.addClass('InputfieldPageTableDelete ui-state-error-text ui-state-disabled');
							}
						}
					});
				});
			}
		}
	}
	var $iframe = pwModalWindow(url, modalSettings, 'large');
	var closeOnSaveReady = false;
	
	if($a.is('.InputfieldPageTableAdd')) closeOnSave = false; 

	$iframe.load(function() {

		var buttons = []; 	
		//$dialog.dialog('option', 'buttons', {}); 
		var $icontents = $iframe.contents();
		var n = 0;
		// var title = $icontents.find('title').text();

		dialogPageID = $icontents.find('#Inputfield_id').val(); // page ID that will get added if not already present

		// hide things we don't need in a modal context
		$icontents.find('#wrap_Inputfield_template, #wrap_template, #wrap_parent_id').hide();
		//$icontents.find('#breadcrumbs ul.nav, #_ProcessPageEditDelete, #_ProcessPageEditChildren').hide();
		$icontents.find('#_ProcessPageEditDelete, #_ProcessPageEditChildren').hide();

		closeOnSave = noclose == 0 && $icontents.find('#ProcessPageAdd').length == 0; 
		
		if(closeOnSave && closeOnSaveReady) {
			if($icontents.find(".NoticeError, .NoticeWarning, .ui-state-error").length == 0) {
				if(typeof Notifications != "undefined") {
					var messages = [];
					$icontents.find(".NoticeMessage").each(function() {
						messages[messages.length] = $(this).text();
					});
					if(messages.length > 0) setTimeout(function() {
						for(var i = 0; i < messages.length; i++) {
							Notifications.message(messages[i]);
						}
					}, 500);
				}
				$iframe.dialog('close');
				return;
			} else {
				// errors occurred, so keep it open
			}
		}
	
		// copy buttons in iframe to dialog
		$icontents.find("#content form button.ui-button[type=submit]").each(function() {
			var $button = $(this); 
			var text = $button.text();
			var skip = false;
			// avoid duplicate buttons
			for(var i = 0; i < buttons.length; i++) {
				if(buttons[i].text == text || text.length < 1) skip = true; 
			}
			if(!skip) {
				buttons[n] = {
					'text': text, 
					'class': ($button.is('.ui-priority-secondary') ? 'ui-priority-secondary' : ''), 
					'click': function() {
						$button.click();
						if(closeOnSave) closeOnSaveReady = true; 
						if(!noclose) closeOnSave = true; // only let closeOnSave happen once
					}
				};
				n++;
			}; 
			$button.hide();
		}); 

		$iframe.setButtons(buttons); 
	}); 

	return false; 
}

function InputfieldPageTableUpdate($table) {
	var value = '';
	if(!$table.is('tbody')) $table = $table.find('tbody'); 
	$table.find('tr').each(function() {
		var pageID = $(this).attr('data-id'); 
		if(value.length > 0) value += '|';
		value += pageID; 
	}); 
	var $container = $table.parents('.InputfieldPageTableContainer'); 
	var $input = $container.siblings('.InputfieldPageTableSort'); 
	$input.val(value); 
}

function InputfieldPageTableSortable($table) {
	
	$table.find('tbody').sortable({
		axis: 'y',
		start: function(event, ui) {
			var widths = [];
			var n = 0;
			$table.find('thead').find('th').each(function() {
				widths[n] = $(this).width();
				n++;
			});
			n = 0;
			ui.helper.find('td').each(function() {
				$(this).attr('width', widths[n]);
				n++;
			});
		},
		stop: function(event, ui) {
			InputfieldPageTableUpdate($(this)); 
		}
	});

}

function InputfieldPageTableDelete() {
	var $row = $(this).closest('tr'); 
	$row.toggleClass('InputfieldPageTableDelete ui-state-error-text ui-state-disabled'); 
	var ids = '';
	$row.parents('tbody').children('tr').each(function() {
		var $tr = $(this); 
		var id = $tr.attr('data-id'); 
		if($tr.is('.InputfieldPageTableDelete')) ids += (ids.length > 0 ? '|' : '') + id;
	}); 

	var $input = $(this).parents('.InputfieldPageTableContainer').siblings('input.InputfieldPageTableDelete'); 
	$input.val(ids); 
	
	return false; 
}

$(document).ready(function() {

	$(document).on('click', '.InputfieldPageTableAdd, .InputfieldPageTableEdit', InputfieldPageTableDialog); 
	$(document).on('click', 'a.InputfieldPageTableDelete', InputfieldPageTableDelete); 
	$(document).on('dblclick', '.InputfieldPageTable .AdminDataTable td', function() {
		$(this).closest('tr').find('.InputfieldPageTableEdit').click();
	}); 

	InputfieldPageTableSortable($(".InputfieldPageTable table"));
	
	$(document).on('reloaded', '.InputfieldPageTable', function() {
		InputfieldPageTableSortable($(this).find(".InputfieldPageTableContainer > table"));
	});
	
	$(document).on('click', '.InputfieldPageTableOrphansAll', function() {
		var $checkboxes = $(this).closest('.InputfieldPageTableOrphans').find('input'); 
		if($checkboxes.eq(0).is(":checked")) $checkboxes.removeAttr('checked'); 
			else $checkboxes.attr('checked', 'checked'); 
		return false;
	}); 
}); 
