/**
 * ProcessWire Admin Theme jQuery/Javascript for AdminThemeReno
 *
 * By Tom Reno and Ryan Cramer
 * 
 */

var ProcessWireAdminTheme = {

	/**
	 * Initialize the default ProcessWire admin theme
	 *
	 */
	
	init: function() {
		this.setupCloneButton();
		ProcessWireAdmin.init();
		// this.setupFieldFocus();
		this.setupSearch();
		this.setupDropdowns();
		this.setupSidebarNav();
		this.setupSideBarState();
		this.setupSideBarToggle();
		var $body = $("body"); 
		var $html = $("html"); 
		if($body.hasClass('hasWireTabs') && $("ul.WireTabs").length == 0) $body.removeClass('hasWireTabs'); 
		$('#content').removeClass('fouc_fix'); // FOUC fix, deprecated
		$body.removeClass('pw-init').addClass('pw-ready'); 
		$html.removeClass('pw-init').addClass('pw-ready'); 
		// this.browserCheck();
		$("#notices a.notice-remove").click(function() {
			$("#notices").slideUp('fast', function() { $(this).remove(); }); 
		});
	},

	/**
	 * Sidebar Navigation State
	 *
	 */
	setupSidebarNav: function() {
		
		var url = window.location.toString()

		$(document).mouseup(function (e){
		    var quicklinks = $("ul.quicklinks");
		    if (!quicklinks.is(e.target) && quicklinks.has(e.target).length === 0){
		        quicklinks.hide();
		        $('.quicklink-open').removeClass('active');
		        $('#main-nav .current').removeClass('no-arrow');
		    }
		});

		$(document).keydown(function(e) {
			var type = e.target.tagName.toLowerCase();
			var firstClass = e.target.className.split(" ")[0];

			// input, textarea, CKEditor (Inline mode) focused, so do nothing.
			if (type == 'input' || type == 'textarea' || firstClass == 'InputfieldCKEditorInline') return; 
		    
		    switch(e.which) {
		        case 37:
		        state = 'open';
		        break;

		        case 39:
		        state = 'closed';
		        break;

		        default: return;
		    }
		    ProcessWireAdminTheme.setupSideBarState(true, state);

		    e.preventDefault(); 
		});
		
		///////////////////////////////////////////////////////////////////
		
		function closeOpenQuicklinks() {
			$("#main-nav > li > a.open:not(.hover-temp):not(.just-clicked)").each(function() {
				// close sections that are currently open
				var $t = $(this);
				var $u = $t.next('ul:visible');
				if($u.length > 0) {
					if($u.find('.quicklinks-open').length > 0) $u.find('.quicklink-close').click();
					//$u.slideUp('fast');
				}
				//$(this).removeClass('open').removeClass('current'); 
			});
		}

		// this bit of code below monitors single click vs. double click
		// on double click it goes to the page linked by the nav item 
		// on single click it opens or closes the nav
		
		var clickTimer = null, numClicks = 0;
		$("#main-nav a.parent").dblclick(function(e) {
			e.preventDefault();
			
		}).click(function() {
			var $a = $(this);
			$a.addClass('just-clicked'); 
			numClicks++;
			if(numClicks === 1) {
				clickTimer = setTimeout(function() { 
					// single click occurred
					closeOpenQuicklinks();
					$a.toggleClass('open').next('ul').slideToggle(200, function() {
						$a.removeClass('just-clicked'); 
					});
					numClicks = 0; 
				}, 200); 
			} else {
				// double click occurred
				clearTimeout(clickTimer);
				numClicks = 0;
				return true; 
			}
			return false;
				
		});

		///////////////////////////////////////////////////////////////////
		/*
		
		$("#main-nav > li").mouseover(function() {
			// hover actions open hovered item, and close others
			var $li = $(this);
			var $a = $li.children('a');
			var $ul = $li.children('ul');
			if($ul.is(":visible")) {
				// already open
			} else {
				// needs to be opened
				setTimeout(function() {
					if(!$a.hasClass('hover-temp')) return;
					if($a.hasClass('just-clicked')) return;
					closeOpenSections();
					$a.addClass('open').next('ul').slideDown('fast');
				}, 650);
				$a.addClass('hover-temp'); 
			}
		}).mouseout(function() {
			var $a = $(this).children('a');
			$a.removeClass('hover-temp'); 
		});
		*/

		///////////////////////////////////////////////////////////////////
	
		/*
		$("#main-nav li > ul > li > a").hover(function() {
			var $a = $(this);
			var newIcon = $a.attr('data-icon'); 
			if(newIcon.length == 0) return;
			var $icon = $a.parent('li').parent('ul').prev('a').children('i');
			$icon.attr('data-icon', $icon.attr('class'));
			$icon.attr('class', 'fa fa-' + $a.attr('data-icon')); 
			
		}, function() {
			var $a = $(this);
			var newIcon = $a.attr('data-icon');
			if(newIcon.length == 0) return;
			var $icon = $a.parent('li').parent('ul').prev('a').children('i');
			$icon.attr('class', $icon.attr('data-icon'));
		});
		*/

		///////////////////////////////////////////////////////////////////

		var quicklinkTimer = null;
		
		$(".quicklink-open").click(function(event){
			closeOpenQuicklinks();
		
			var $this = $(this);
			$this.parent().addClass('quicklinks-open');
			$this.toggleClass('active').parent().next('ul.quicklinks').toggle();
			$this.parent().parent().siblings().find('ul.quicklinks').hide();
			$this.parent().parent().siblings().find('.quicklink-open').removeClass('active').parent('a').removeClass('quicklinks-open');
			$this.effect('pulsate', 100); 
			event.stopPropagation();
			//psuedo elements are not part of the DOM, need to remove current arrows by adding a class to the current item.
			$('#main-nav .current:not(.open)').addClass('no-arrow');
	
			// below is used to populate quicklinks via ajax json services on Process modules that provide it
			var $ul = $(this).parent().next('ul.quicklinks');
			var jsonURL = $ul.attr('data-json'); 
			if(jsonURL.length > 0 && !$ul.hasClass('json-loaded')) {
				$ul.addClass('json-loaded');
				var $spinner = $ul.find('.quicklinks-spinner');
				var spinnerSavedClass = $spinner.attr('class');
				$spinner.removeClass(spinnerSavedClass).addClass('fa fa-fw fa-spin fa-spinner'); 
				$.getJSON(jsonURL, function(data) {
					if(data.add) {
						var $li = $("<li class='add'><a href='" + data.url + data.add.url + "'><i class='fa fa-fw fa-" + data.add.icon + "'></i>" + data.add.label + "</a></li>");
						$ul.append($li);
					}
					// populate the retrieved items
					$.each(data.list, function(n) {
						var icon = '';
						// if(this.icon) icon = "<i class='fa fa-fw fa-" + this.icon + "'></i>";
						var url = this.url.indexOf('/') === 0 ? this.url : data.url + this.url;
						var $li = $("<li><a style='white-space:nowrap' href='" + url + "'>" + icon + this.label + "</a></li>");
						if(typeof this.className != "undefined" && this.className && this.className.length) $li.addClass(this.className);
						$ul.append($li);
					});
					$spinner.removeClass('fa-spin fa-spinner').addClass(spinnerSavedClass);
					if(data.icon.length > 0) $spinner.removeClass('fa-bolt').addClass('fa-' + data.icon);
				}); 				
			}
			
			return false;
			
		}).mouseover(function() {
			var $this = $(this);
			if($this.parent().hasClass('quicklinks-open')) return;
			$this.addClass('hover-temp'); 
			clearTimeout(quicklinkTimer); 
			quicklinkTimer = setTimeout(function() {
				if($this.parent().hasClass('quicklinks-open')) return;
				if($this.hasClass('hover-temp')) $this.click();
			}, 500); 
				
		}).mouseout(function() {
			$(this).removeClass('hover-temp'); 
		});

		$(".quicklink-close").click(function(){
			$(this).parent().removeClass('quicklinks-open'); 
			$(this).closest('ul.quicklinks').hide().prev('a').removeClass('quicklinks-open'); 
			$('.quicklink-open').removeClass('active');
			$('#main-nav .current').removeClass('no-arrow'); 
			return false;
		});

		$('#main-nav .parent').each(function(){
      		var myHref= $(this).attr('href');
      		if(url.match(myHref)) {
	           $(this).next('ul').show();
			   $(this).addClass('open');
	      	}
		}); 
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
		var $buttons = $("button.head_button_clone, button.head_button, button.head-button");

		// don't continue if no buttons here or if we're in IE
		if($buttons.length == 0 || $.browser.msie) return;

		var $head = $("<div id='head_button'></div>").prependTo("#headline").show();
		$buttons.each(function() {
			var $t = $(this);
			var $a = $t.parent('a'); 
			if($a.length) { 
				$button = $t.parent('a').clone();
				//$head.prepend($button);
				$head.append($button);
			// } else if($t.is('.head_button_clone')) {
			} else if($t.hasClass('head_button_clone') || $t.hasClass('head-button')) {
				$button = $t.clone();
				$button.attr('data-from_id', $t.attr('id')).attr('id', $t.attr('id') + '_copy');
				//$a = $("<a></a>").attr('href', '#');
				$button.click(function() {
					$("#" + $(this).attr('data-from_id')).click(); // .parents('form').submit();
					return false;
				});
				// $head.append($a.append($button));	
				//$head.prepend($a.append($button));
				$head.prepend($button);
			}
		}); 
	},

	/**
	 * Make the first field in any forum have focus, if it is a text field
	 *
	setupFieldFocus: function() {
		// add focus to the first text input, where applicable
		jQuery('#content input[type=text]:visible:enabled:first:not(.hasDatepicker)').each(function() {
			var $t = $(this); 
			if(!$t.val() && !$t.is(".no_focus")) window.setTimeout(function() { $t.focus(); }, 1);
		});
	},
	 */


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
				$status.html("<i class='fa fa-spinner fa-spin'></i>");
			},
			source: function(request, response) {
				var url = $input.parents('form').attr('action') + 'for?get=template_label,title&include=all&admin_search=' + request.term;
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
		}).blur(function() {
			$status.text('');	
		});

		// Search toggle
		var $search = $("#search");

		$input.bind('keypress keydown keyup', function(event){
       		if(event.keyCode == 13) {
       			event.preventDefault(); // Don't submit to the search page on Enter Key
       		}

       		if(event.keyCode == 38) {
       			$search.removeClass("open");
	    		$(this).val(); // close search on arrow up
	    		$input.blur();
	    		$('#ProcessPageSearchAutocomplete').hide().html('');
       		}

       		if(event.keyCode == 8) {
       			if ($.trim($(this).val()) == ''){
       				$status.text(''); // remove status if nothing in the input.
       			}
       		}
       		
    	});

		$(".search-toggle").on("click", function() {
			if (!$search.hasClass('open')){
				$('#masthead').find('ul.open').removeClass('open');
				$input.focus();
			} else {
				$input.blur();
			}
	    	$search.toggleClass("open");
	    	$input.val("");
			return false;
		});

		$(".search-close").on("click", function() {
	    	$search.removeClass("open");
	    	$input.val("");
	    	$input.blur();
			return false;
		});

	
		$('body').click(function(event){

			if (!$search.hasClass('open')) return; // not open, so do nothing
			
			var hide = true;
			var exclude = ['ProcessPageSearchAutocomplete', 'ProcessPageSearchForm', 'search'];
   			if ($.inArray(event.target.id, exclude) != -1) return; // stay open for these targets
   			
   			// check if the event.target was a child element of any of any items in the exclude array.
   			$.each(exclude, function(key, val){
				if ($(event.target).closest('#' + val).length) hide = false; 
			});

   			if (hide){
   				$search.removeClass("open");
    			$input.val("");
    			$input.blur();
   			}
		});
	
	},

	setupDropdowns: function() {

		$('#masthead li.dropdown > a').on('click', function(e){
			$(this).next("ul").toggleClass('open');
			$(this).parent().siblings().find('ul.open').removeClass('open');
			return false;
		});

		$('#masthead li.dropdown > ul li a').on('click', function(e){
			e.stopPropagation();
		});

		$(document).on('click', function(){
			$('#masthead li.dropdown ul').removeClass('open');
		});

		/*
		$("ul.dropdown-menu").each(function() {
			var $ul = $(this).hide();
			var $a = $ul.siblings(".dropdown-toggle"); 

			if($a.is("button")) {
				$a.button();
			} else {
				$ul.css({ 'border-top-right-radius': 0 }); 
			}

			// hide nav when an item is selected to avoid the whole nav getting selected
			$ul.find('a').click(function() {
				$ul.hide();
				return true; 
			});

			$ul.find(".has-items").each(function() {
				var $icon = $("<i class='has-items-icon fa fa-angle-right ui-priority-secondary'></i>");
				$(this).prepend($icon);
			}); 

			var lastOffset = null; 

			$a.mouseenter(function() {
				var offset = $a.offset();	
				if(lastOffset != null) {
					if(offset.top != lastOffset.top || offset.left != lastOffset.left) {
						// dropdown-toggle has moved, destroy and re-create
						$ul.menu('destroy').removeClass('dropdown-ready');
					}
				}	
				if(!$ul.hasClass('dropdown-ready')) {
					$ul.css('position', 'absolute'); 
					$ul.prependTo($('body')).addClass('dropdown-ready').menu();
					var position = { my: 'right top', at: 'right bottom', of: $a };
					var my = $ul.attr('data-my'); 
					var at = $ul.attr('data-at'); 
					if(my) position.my = my; 
					if(at) position.at = at; 
					$ul.position(position).css('z-index', 200);
				}
				$a.addClass('hover'); 
				$ul.show();
				lastOffset = offset; 

			}).mouseleave(function() {
				setTimeout(function() {
					if($ul.is(":hover")) return;
					$ul.find('ul').hide();
					$ul.hide();
					$a.removeClass('hover');
				}, 50); 
			}); 

			$ul.mouseleave(function() {
				if($a.is(":hover")) return;
				$ul.hide();
				$a.removeClass('hover'); 
			}); 

		});
		*/
	}, 

	setupSideBarToggle: function() {
		$(".main-nav-toggle").on("click", function(){
	    	ProcessWireAdminTheme.setupSideBarState(true);
			return false;
		});
	},

	/**
	 * @todo: Refactor this and the corresponding CSS. (Renobird)
	 * 
	 */

	setupSideBarState: function(click, state) {
		if ($("body").hasClass("id-23") || $("body").hasClass("modal")) return false;
   		var name = 'pw_sidebar_state';
   		var state = state || localStorage.getItem(name);
   		var toggle = $(".main-nav-toggle");
   		var sidebar = $("#sidebar");
   		var main = $("#main");
   		var masthead = $("#masthead");
   		var branding = $("#branding");
   		var bug = $("#NotificationBug");
   		var elems = toggle.add(sidebar).add(main).add(masthead).add(branding).add(bug);

   		if (state === null && $(window).width() >= 690) localStorage.setItem(name,"open");
   		
   		if (!click && $(window).width() < 690){
   			localStorage.setItem(name,"closed");
   			state = 'closed';
   		}

   		if (click == true){
			if (state == 'open'){
    			localStorage.setItem(name,"closed");
    			elems.addClass('closed');
    		} else if (state =='closed'){
    			localStorage.setItem(name,"open");
    			elems.removeClass('closed');
    		}

			sidebar.removeClass('hide');
			branding.removeClass('hide');
			main.removeClass('full');
			masthead.removeClass('full');
			toggle.removeClass('full');

		} else{
			
			if (state == 'closed'){
   				elems.addClass('closed');
				sidebar.addClass('hide');
				branding.addClass('hide');
				main.addClass('full');
				masthead.addClass('full');
				toggle.addClass('full');
        	}	
		}
	},

	/**
	 * Give a notice to IE versions we don't support
	 *
	 */
	browserCheck: function() {
		if($.browser.msie && $.browser.version < 8) 
			$("#content .container").html("<h2>ProcessWire does not support IE7 and below at this time. Please try again with a newer browser.</h2>").show();
	}
};

$(document).ready(function() {
	ProcessWireAdminTheme.init();
});