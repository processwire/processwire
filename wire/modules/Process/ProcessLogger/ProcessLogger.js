var ProcessLogger = {

	timer: null,
	pageNum: 1,

	submitFilters: function(updateOnlyNew) {
		if(typeof updateOnlyNew == "undefined") updateOnlyNew = false;
		var filters = {
			q: $("#Inputfield_q").val(),
			date_from: $("#Inputfield_date_from").val(),
			date_to: $("#Inputfield_date_to").val(),
			time: 0
		};
		if(updateOnlyNew) filters.time = $("#ProcessLogPage").attr('data-time');
		ProcessLogger.startSpinner(!updateOnlyNew);
		$.getJSON('./', filters, function(data) {
			if(!updateOnlyNew || (data.qty > -1 && ProcessLogger.pageNum == 1)) {
				$("#ProcessLogEntries").html(data.out).effect('highlight', 500); 
			}
			if(data.note.length > 0) {
				if(typeof Notifications != "undefined") {
					Notifications.message(data.note, '', 'tree'); 
				}
				$("#ProcessLogHeadline").find('.notes').html('+ ' + data.note);	
			}
			ProcessLogger.setupLogEntries();
			ProcessLogger.stopSpinner(!updateOnlyNew);
			$("#ProcessLogPage").attr('data-time', data.time);
		});
	},

	setupLogEntries: function() {
		
		ProcessLogger.pageNum = parseInt($("#ProcessLogPage").attr('data-page'));
		if(ProcessLogger.timer) clearTimeout(ProcessLogger.timer); 
		ProcessLogger.timer = setTimeout('ProcessLogger.submitFilters(true)', 5000);
	
		if(ProcessLogger.pageNum == 1) {
			$(".ProcessLogNew").each(function () {
				var $tr = $(this).closest('tr');
				$tr.hide().fadeIn('normal', function() {
					$tr.effect('highlight', 1000);
				});
				$(this).removeClass('ProcessLogNew');
			});
		}
	},

	paginationClick: function() {
		// pagination link clicked
		clearTimeout(ProcessLogger.timer); 
		ProcessLogger.startSpinner(true);
		$.getJSON($(this).attr('href'), function(data) {
			$("#ProcessLogEntries").html(data.out);
			ProcessLogger.setupLogEntries();
			ProcessLogger.stopSpinner(true);
		});
		return false;
	},
	
	startSpinner: function(tree) {
		if(typeof tree == "undefined") tree = true;
		if(tree) {
			$("#ProcessLogSpinner").addClass('fa-spin fa-spinner').removeClass('fa-tree');
		} else {
			$("#FieldsetTools").find('i.fa-sun-o').addClass('fa-spin');
		}
	},
	
	stopSpinner: function(tree) {
		if(typeof tree == "undefined") tree = true;
		if(tree) {
			$("#ProcessLogSpinner").removeClass('fa-spin fa-spinner').addClass('fa-tree');
		} else {
			$("#FieldsetTools").find('i.fa-sun-o').removeClass('fa-spin');
		}
	},
	
	filterChange: function() {
		clearTimeout(ProcessLogger.timer);
		ProcessLogger.timer = setTimeout('ProcessLogger.submitFilters(false)', 500);
	},

	init: function() {

		$("#Inputfield_q").keyup(ProcessLogger.filterChange); 
		$("#Inputfield_date_to, #Inputfield_date_from").change(ProcessLogger.filterChange); 

		$(document).on('click', '.MarkupPagerNav a', ProcessLogger.paginationClick); 
		
		ProcessLogger.setupLogEntries();
	}
}

$(document).ready(function() {
	ProcessLogger.init();	
}); 