/**
 * ProcessWire Admin Theme jQuery/Javascript
 *
 * Copyright 2016 by Ryan Cramer
 * 
 */

var ProcessWireAdminTheme = {
	
	/**
	 * Initialize the default ProcessWire admin theme
	 *
	 */
	init: function() {
		// fix annoying fouc with this particular button
		var $button = $("#head_button > button.dropdown-toggle").hide();

		this.setupCloneButton();
		ProcessWireAdmin.init();
		this.setupSearch();
		this.setupMobile();
		
		var $body = $("body");
		if($body.hasClass('hasWireTabs') && $("ul.WireTabs").length == 0) $body.removeClass('hasWireTabs'); 
		$('#content').removeClass('fouc_fix'); // FOUC fix, deprecated
		$body.removeClass("pw-init").addClass("pw-ready"); 
		
		if($button.length > 0) $button.show();
	},


	/**
	 * Clone a button at the bottom to the top 
	 *
	 */
	setupCloneButton: function() {
		// no head_button in modal view
		if($("body").is(".modal")) return;

		// if there are buttons in the format "a button" without ID attributes, copy them into the masthead
		// or buttons in the format button.head_button_clone with an ID attribute.
		// var $buttons = $("#content a[id=''] button[id=''], #content button.head_button_clone[id!='']");
		// var $buttons = $("#content a:not([id]) button:not([id]), #content button.head_button_clone[id!=]"); 
		var $buttons = $("button.head_button_clone, button.head-button"); 

		// don't continue if no buttons here or if we're in IE
		if($buttons.length == 0) return; // || $.browser.msie) return;

		var $head = $("#head_button"); 
		if($head.length == 0) $head = $("<div id='head_button'></div>").prependTo("#breadcrumbs .container");
		
		$buttons.each(function() {
			var $t = $(this);
			var $a = $t.parent('a'); 
			if($a.length > 0) { 
				$button = $t.parent('a').clone(true);
				$head.prepend($button);
			} else if($t.hasClass('head_button_clone') || $t.hasClass('head-button')) {
				$button = $t.clone(true);
				$button.attr('data-from_id', $t.attr('id')).attr('id', $t.attr('id') + '_copy');
				//$a = $("<a></a>").attr('href', '#');
				$button.click(function() {
					$("#" + $(this).attr('data-from_id')).click(); // .parents('form').submit();
					return false;
				});
				//$head.prepend($a.append($button));
				$head.prepend($button);	
			}
			if($button.hasClass('dropdown-toggle') && $button.attr('data-dropdown')) {
				
				
			}
		}); 
		$head.show();
	},
	
	/**
	 * Make the site search use autocomplete
	 * 
	 */
	setupSearch: function() {

		$.widget( "custom.adminsearchautocomplete", $.ui.autocomplete, {
			_renderMenu: function(ul, items) {
				var that = this;
				var currentType = "";
				$.each(items, function(index, item) {
					if (item.type != currentType) {
						ul.append("<li class='ui-widget-header'><a>" + item.type + "</a></li>" );
						currentType = item.type;
					}
					ul.attr('id', 'ProcessPageSearchAutocomplete'); 
					that._renderItemData(ul, item);
				});
			},
			_renderItemData: function(ul, item) {
				if(item.label == item.template) item.template = '';
				ul.append("<li><a href='" + item.edit_url + "'>" + item.label + " <small>" + item.template + "</small></a></li>"); 
			}
		});
		
		var $input = $("#ProcessPageSearchQuery"); 
		var $status = $("#ProcessPageSearchStatus"); 
		
		$input.adminsearchautocomplete({
			minLength: 2,
			position: { my : "right top", at: "right bottom" },
			search: function(event, ui) {
				$status.html("<img src='" + ProcessWire.config.urls.modules + "Process/ProcessPageList/images/loading.gif'>");
			},
			open: function(event, ui) {
				$("#topnav").hide();
			},
			close: function(event, ui) {
				$("#topnav").show();
			},
			source: function(request, response) {
				var url = $input.parents('form').attr('data-action') + 'for?get=template_label,title&include=all&admin_search=' + request.term;
				$.getJSON(url, function(data) {
					var len = data.matches.length; 
					if(len < data.total) $status.text(data.matches.length + '/' + data.total); 
						else $status.text(len); 
					response($.map(data.matches, function(item) {
						return {
							label: item.title,
							value: item.title,
							page_id: item.id,
							template: item.template_label ? item.template_label : '',
							edit_url: item.editUrl,
							type: item.type
						}
					}));
				});
			},
			select: function(event, ui) { }
		}).focus(function() {
			$(this).siblings('label').find('i').hide(); // hide icon
		}).blur(function() {
			$status.text('');	
			$(this).siblings('label').find('i').show(); // show icon
		});
		
	},

	setupMobile: function() {
		// collapse or expand the topnav menu according to whether it is wrapping to multiple lines
		var collapsedTopnavAtBodyWidth = 0;
		var collapsedTabsAtBodyWidth = 0;

		var windowResize = function() {

			// top navigation
			var $topnav = $("#topnav"); 
			var $body = $("body"); 
			var height = $topnav.height();

			if(height > 50) {
				// topnav has wordwrapped
				if(!$body.hasClass('collapse-topnav')) {
					$body.addClass('collapse-topnav'); 
					collapsedTopnavAtBodyWidth = $body.width();
				}
			} else if(collapsedTopnavAtBodyWidth > 0) {
				// topnav is on 1 line
				var width = $body.width();
				if($body.hasClass('collapse-topnav') && width > collapsedTopnavAtBodyWidth) {
					$body.removeClass('collapse-topnav'); 
					collapsedTopnavAtBodyWidth = 0;
				}
			}

			$topnav.children('.collapse-topnav-menu').children('a').click(function() {
				if($(this).is(".hover")) {
					// already open? close it. 
					$(this).mouseleave();
				} else {
					// open it again
					$(this).mouseenter();
				}
				return false;
			}); 

			// wiretabs
			var $wiretabs = $(".WireTabs"); 
			if($wiretabs.length < 1) return;

			$wiretabs.each(function() {
				var $tabs = $(this);
				var height = $tabs.height();
				if(height > 65) {
					if(!$body.hasClass('collapse-wiretabs')) {
						$body.addClass('collapse-wiretabs'); 
						collapsedTabsAtBodyWidth = $body.width();
						// console.log('collapse wiretabs'); 
					}
				} else if(collapsedTabsAtBodyWidth > 0) {
					var width = $body.width();
					if($body.hasClass('collapse-wiretabs') && width > collapsedTabsAtBodyWidth) {
						$body.removeClass('collapse-wiretabs'); 
						collapsedTabsAtBodyWidth = 0;
						// console.log('un-collapse wiretabs'); 
					}
				}
			}); 
		};

		windowResize();
		$(window).resize(windowResize);

	}, 

};

$(document).ready(function() {
	ProcessWireAdminTheme.init();

	$("#notices a.notice-remove").click(function() {
		$("#notices").slideUp('fast', function() { $(this).remove(); }); 
	}); 
}); 
