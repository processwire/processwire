/**
 * ProcessWire Page Lister
 * 
 */
var ProcessLister = {

	inInit: true, // are we currently in init() method?
	inTimeout: false, // setTimeout variable for use by clearTimeout if needed
	spinner: null, // spinner that shows during ajax calls
	numSubmits: 0, // number of times ProcessLister._submit() method called
	results: null, // shortcut to #ProcessListerResults
	filters: null, // shortcut to #ProcessListerFilters
	lister: null, // shortcut to #ProcessLister
	initialized: false, // has the init() method already been called?
	resetTotal: false, // set to true before submit() to ask Lister to grab a new total
	clickAfterRefresh: '', // 'id' attribute of link to automatically click after a refresh
	refreshRowPageIDs: [], // when set, only the rows representing the given page IDs will be updated during a refresh
	extraSubmitData: {}, // any extra submit data that should be sent during submit()

	/**
	 * Initialize Lister
	 * 
	 */
	init: function() {
		if(ProcessLister.initialized) return;
		ProcessLister.initialized = true;
		if($("#ProcessLister").length == 0) return;

		ProcessLister.spinner = $("<li class='title' id='ProcessListerSpinner'><i class='fa fa-lg fa-spin fa-spinner'></i></li>"); 
		$("#breadcrumbs ul.nav").append(ProcessLister.spinner); 
		
		ProcessLister.filters = $("#ProcessListerFilters"); 
		ProcessLister.results = $("#ProcessListerResults");
		ProcessLister.lister = $("#ProcessLister"); 

		ProcessLister.filters.change(function() { ProcessLister.submit(); }); 
		ProcessLister.results.on('click', '.ProcessListerTable > thead th', ProcessLister.columnSort)

		$(document).on('click', 'a.actions_toggle', ProcessLister.pageClick); 
		$(document).on('click', '.actions a.ajax', ProcessLister.actionClickAjax);
		$("#actions_items_open").attr('disabled', 'disabled').parent('label').addClass('ui-state-disabled'); 

		$(document).on('click', '.MarkupPagerNav a', function() {
			var url = $(this).attr('href'); 
			ProcessLister.submit(url); 
			return false; 
		}); 

		$("#submit_refresh").click(function() {
			ProcessLister.resetTotal = true; 
			ProcessLister.submit();
			$(this).fadeOut("normal", function() {
				$("#submit_refresh").removeClass('ui-state-active').fadeIn();
			}); 
			return false; 
		}); 

		$("#lister_columns").change(function() {
			ProcessLister.submit();
		}); 

		$("#ProcessListerActionsForm").find('script').remove(); // to prevent from running twice after being WireTabbed
		if(ProcessLister.lister.size() > 0) ProcessLister.lister.WireTabs({ items: $(".WireTab") });


		$("#_ProcessListerRefreshTab").html("<i class='fa fa-refresh ui-priority-secondary'></i>")
			.unbind('click')
			.click(function() {
				ProcessLister.resetTotal = true; 
				ProcessLister.submit();
				return false;
			});

		$("#_ProcessListerResetTab").html("<i class='fa fa-rotate-left ui-priority-secondary'></i>")
			.unbind('click')
			.click(function() {
				window.location.href = './?reset=1';
				return false;
			});

		ProcessLister.inInit = false; 
		// if no change events occurred during init, go ahead and submit it now
		if(ProcessLister.numSubmits == 0) ProcessLister.submit();
			else ProcessLister.spinner.fadeOut();
	},
	
	/**
	 * Implementation for table header (th) click event
	 *
	 */
	columnSort: function() {
		$(this).find("span").remove();
		var name = $(this).find('b').text();
		var val = $("#lister_sort").val();

		if(val == name) name = '-' + name; // reverse
		if(name.length < 1) name = val;
		$("#lister_sort").val(name);

		ProcessLister.submit();
	},

	/**
	 * Submit/refresh Lister (public API side)
	 * 
	 * @param url
	 * 
	 */
	submit: function(url) {
		if(ProcessLister.inTimeout) clearTimeout(ProcessLister.inTimeout); 
		ProcessLister.inTimeout = setTimeout(function() {
			ProcessLister._submit(url); 
		}, 250); 
	},

	/**
	 * Submit/refresh Lister (private API side)
	 * 
	 * @param url
	 * @private
	 * 
	 */
	_submit: function(url) {
		
		var refreshAll = true; 
		
		if(ProcessLister.refreshRowPageIDs.length == 0) {
			var $form = ProcessLister.results.find('.InputfieldFormConfirm');
			if($form.length) {
				var msg = InputfieldFormBeforeUnloadEvent(true);
				if(typeof msg != "undefined" && msg.length) {
					if(!confirm(msg)) return false;
				}
			}
		} else {
			refreshAll = false;
		}
		
		ProcessLister.numSubmits++;
		if(typeof url == "undefined") var url = "./";

		ProcessLister.spinner.fadeIn('fast'); 
		
		var submitData = {
			filters: refreshAll ? ProcessLister.filters.val() : '',
			columns: $('#lister_columns').val(),
			sort: $('#lister_sort').val()
		};
		
		for(var key in ProcessLister.extraSubmitData) {
			var val = ProcessLister.extraSubmitData[key];
			submitData[key] = val;
		}
		ProcessLister.extraSubmitData = {};
		
		if(ProcessLister.resetTotal) {
			submitData['reset_total'] = 1;
			ProcessLister.resetTotal = false;
		}
		
		if(ProcessLister.refreshRowPageIDs.length > 0) {
			submitData['row_page_id'] = ProcessLister.refreshRowPageIDs.join(',');
			ProcessLister.resetTotal = false;
		}

		$.ajax({
			url: url, 
			type: 'POST', 
			data: submitData, 
			success: ProcessLister._submitSuccess, 
			error: function(error) {
				ProcessLister.results.html("<p>Error retrieving results: " + error + "</p>"); 
			}
		}); 
	},

	/**
	 * Method called on ajax success
	 * 
	 * @param data
	 * @private
	 * 
	 */
	_submitSuccess: function(data) {
		
		if(ProcessLister.refreshRowPageIDs.length) {
		
			for(var n in ProcessLister.refreshRowPageIDs) {
				var pageID = ProcessLister.refreshRowPageIDs[n];
				// update one row
				var idAttr = "#page" + pageID;
				var $oldRow = $(idAttr).closest('tr');
				var $newRow = $(data).find(idAttr).closest('tr');
				var message = $oldRow.find(".actions_toggle").attr('data-message');
				if($oldRow.length && $newRow.length) {
					$oldRow.replaceWith($newRow);
					$newRow.addClass('row_refreshed_' + pageID); // applicable to refreshed rows only
					$newRow.effect('highlight', 'normal');
					if(message) {
						var $message = $("<span class='row_message notes'>" + message + "</span>");
						$newRow.find(".actions_toggle").addClass('row_message_on').closest('.col_preview, td').append($message);
						setTimeout(function() {
							$message.fadeOut('normal', function() {
								$newRow.find('.actions_toggle').removeClass('row_message_on').click();
							});
						}, 1000);
					}
					if($newRow.find(".Inputfield").length) InputfieldsInit($newRow);
				}
			}
			ProcessLister.refreshRowPageIDs = [];
			
		} else {
			// update entire table
			var sort = $("#lister_sort").val();
			ProcessLister.results.html(data).find("table.ProcessListerTable > thead th").each(function () {
				var $b = $(this).find('b');
				var txt = $b.text();
				$b.remove();
				$(this).find('span').remove();
				var $icon = $(this).find('i');
				if($icon.length) $icon.remove(); // before the html() call
				var label = $(this).html();
				if (txt == sort) {
					$(this).html("<u>" + label + "</u><span>&nbsp;&darr;</span><b>" + txt + "</b>");
				} else if (sort == '-' + txt) {
					$(this).html("<u>" + label + "</u><span>&nbsp;&uarr;</span><b>" + txt + "</b>");
				} else {
					$(this).html(label + "<b>" + txt + "</b>");
				}
				if ($icon.length > 0) $(this).prepend($icon);
			}).end().effect('highlight', 'fast');
			if(ProcessLister.results.find('.Inputfield').length) {
				InputfieldsInit(ProcessLister.results);
			}
		}

		if(ProcessLister.clickAfterRefresh.length > 0) {
			if(ProcessLister.clickAfterRefresh.indexOf('#') < 0 && ProcessLister.clickAfterRefresh.indexOf('.') < 0) {
				// assume ID attribute if no id or class indicated
				ProcessLister.clickAfterRefresh = '#' + ProcessLister.clickAfterRefresh;
			}
			$(ProcessLister.clickAfterRefresh).each(function() {
				var $a = $(this);
				$a.click();
				var $tr = $a.closest('tr');
				$tr.fadeTo(100, 0.1);
				setTimeout(function() { $tr.fadeTo(250, 1.0); }, 250);
			});
			ProcessLister.clickAfterRefresh = '';
		}
		
		ProcessLister.spinner.fadeOut();
		
		setTimeout(function() {
			ProcessLister.results.trigger('loaded');
			ProcessLister.results.find('.Inputfield:not(.reloaded)').addClass('reloaded').trigger('reloaded', [ 'ProcessPageLister' ]);
			$("a.actions_toggle.open").click().removeClass('open'); // auto open items corresponding to "open" get var
			if(typeof AdminDataTable != "undefined") AdminDataTable.init();
			$("a.lister-lightbox", ProcessLister.results).magnificPopup({ type: 'image', closeOnContentClick: true, closeBtnInside: true });
		}, 250);

		var pos = data.indexOf('ProcessListerScript');
		if(pos) {
			var js = data.substring(pos+21);
			if(js != '</div>') {
				pos = js.indexOf('</div>');
				js = js.substring(0, pos);
				// if(config.debug) console.log(js);
				$("body").append('<script>' + js + '</script>');
			}
		}

		if(data.indexOf('</script>') > -1) {
			var d = document.createElement('div');
			d.innerHTML = data;
			var scripts = d.querySelectorAll('.Inputfield script');
			$(scripts).each(function() {
				$.globalEval(this.text || this.textContent || this.innerHTML || '');
			});
		}

		// ProcessLister.results.find(".InputfieldForm").trigger('reloaded');
		
	},

	/**
	 * Queue a row for refresh (internal use)
	 *
	 * @param int pageID
	 * @param string message
	 *
	 */
	_refreshRow: function(pageID, message) {
		if(typeof pageID == "string" && pageID.indexOf('page') > -1) pageID = parseInt(pageID.substring(4));
		if(pageID > 0) {
			if(typeof message != "undefined") {
				$("#page" + pageID).attr('data-message', message);
			}
			ProcessLister.refreshRowPageIDs[pageID] = pageID;
			return true;
		} else {
			return false;
		}
	},

	/**
	 * Refresh the given Lister row, optionally with a message displayed
	 * 
	 * @param int pageID
	 * @param string message
	 * 
	 */
	refreshRow: function(pageID, message) {
		if(ProcessLister._refreshRow(parseInt(pageID), message)) {
			ProcessLister.submit();
		}
	},

	/**
	 * Refresh multiple rows
	 *
	 * @param array pageIDs
	 * @param message
	 *
	 */
	refreshRows: function(pageIDs) {
		var cnt = 0;
		for(var n in pageIDs) {
			var pageID = parseInt(pageIDs[n]);
			if(ProcessLister._refreshRow(pageID, '')) cnt++;
		}
		if(cnt) ProcessLister.submit();
	},

	/**
	 * Refresh all Lister rows
	 * 
	 */
	refreshAll: function() {
		var $refresh = ProcessLister.results.find(".MarkupPagerNavOn a");
		if($refresh.length == 0) $refresh = $("#submit_refresh");
		if($refresh.length == 0) $refresh = $("#_ProcessListerRefreshTab");
		$refresh.click();
	},

	/**
	 * Implementation for an a.actions_toggle page click
	 * 
	 * @returns {boolean}
	 * 
	 */
	pageClick: function() {

		var $toggle = $(this);
		if($toggle.hasClass('row_message_on')) return false;
		var $tr = $toggle.closest('tr'); 
		var $actions = $toggle.next('.actions');
		var $extraActions = $actions.find(".PageExtra").hide();
		var $extraTrigger = $actions.find(".PageExtras");
		var $defaultActions = $actions.find("a:not(.PageExtra):not(.PageExtras)");

		if($tr.is('.open')) {
			$actions.hide();
			$tr.removeClass('open'); 
			return false;
		} else {
			$actions.css('display', 'inline-block');
			$tr.addClass('open');
			if($extraTrigger.hasClass('extras-open')) {
				$extraTrigger.find('i.fa').toggleClass('fa-flip-horizontal');
				$extraTrigger.removeClass('extras-open');
			}
			$extraActions.hide();
			$defaultActions.show();
		}
		
		if($("body").hasClass("AdminThemeDefault")) $extraTrigger.addClass('ui-priority-secondary');
		
		$extraTrigger.unbind('click').click(function() {
			var $t = $(this);
			if($t.hasClass('extras-open')) {
				$extraActions.hide();
				$defaultActions.show();
				$t.removeClass('extras-open');
			} else {
				$defaultActions.hide();
				$extraActions.show();
				$t.addClass('extras-open');
			}
			$t.children('i.fa').toggleClass('fa-flip-horizontal');	
			return false;
		});

		return false; 
	},

	/**
	 * Given a <tr> (or something within it) return the pageID associated with it
	 * 
	 * @param $tr
	 * @returns int
	 * 
	 */
	getPageID: function($tr) {
		if(!$tr.is("tr")) $tr = $tr.closest('tr');
		if(!$tr.length) return 0;
		return parseInt($tr.attr('data-pid'));
		//var $toggle = $tr.find('.actions_toggle');	
		//return parseInt($toggle.attr('id').replace('page', ''));
	},

	/**
	 * Implementation for .actions a.ajax click
	 * 
	 * @returns {boolean}
	 * 
	 */
	actionClickAjax: function() {
		
		var $a = $(this);
		var $toggle = $a.closest('td').find('.actions_toggle');
		var pageID = parseInt($toggle.attr('id').replace('page', ''));
		var $actions = $a.closest('.actions');
		var href = $a.attr('href');
		var actionName = href.match(/\?action=([-_a-zA-Z0-9]+)/)[1]; 
		var $postToken = $("input._post_token"); 
		var tokenName = $postToken.attr('name');
		var tokenValue = $postToken.attr('value');
		var postData = {
			action: actionName,
			id: pageID, 
			ProcessPageLister: 1, // not required for anything in particular
		};
		postData[tokenName] = tokenValue;

		$actions.after("<i class='fa fa-spin fa-spinner ui-priority-secondary'></i>");
		$actions.hide();
		
		$.post(href, postData, function(data) {
			if(typeof data.page != "undefined" || data.action == 'trash') {
				// highlight page mentioned in json return value
				// data.page is returned by ProcessPageClone
				ProcessLister.clickAfterRefresh = '#page' + data.page;
				ProcessLister.resetTotal = true;
			} else {
				// highlight page where action was clicked
				ProcessLister.refreshRowPageIDs[pageID] = pageID;
			}
			if(data.message) $toggle.attr('data-message', data.message);
			ProcessLister.submit();
		}, 'json');
		
		return false;
	}
};

$(document).ready(function() {
	ProcessLister.init();
}); 
