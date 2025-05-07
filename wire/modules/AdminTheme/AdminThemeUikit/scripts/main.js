/**
 * ProcessWire Admin Theme jQuery/Javascript
 *
 * Copyright 2018 by Ryan Cramer
 *
 */

var ProcessWireAdminTheme = {
	
	/**
	 * Initialization to be called before closing </body> tag
	 * 
	 */
	init: function() {
		this.setupInputfields();
		this.setupTooltips();
		this.checkLayout();
	}, 

	/**
	 * Initialize the default ProcessWire admin theme
	 *
	 */
	ready: function() {
		
		this.setupCloneButton();
		ProcessWireAdmin.init();
		this.setupSearch();
		this.setupSideNav();

		var $body = $("body");

		$(document).on('wiretabclick opened', function(e) {
			$('body').addClass('pw-fake-resize');
			$(window).trigger('resize'); // force Uikit to update grid
			setTimeout(function() { $('body').removeClass('pw-fake-resize'); }, 100);
		});
		
		$('a.notice-remove', '#notices').on('click', function() {
			$('#notices').slideUp('fast', function() { $(this).remove(); });
			return false;
		});
		
		$('a.pw-logo-link').on('click', this.logoClickEvent);
		
		$('#_ProcessPageEditView').on('click', function(e) {
			// Uikit tab blocks this link, so this allows it through
			e.stopPropagation();
		});
	
		var resizeTimer = null;
		$(window).on('resize', function() {
			if(resizeTimer) return;
			resizeTimer = setTimeout(function() {
				ProcessWireAdminTheme.windowResized();
				resizeTimer = null;
			}, 250);
		});
		
		this.setupMasthead();
		this.setupWireTabs();
		
		$body.removeClass("pw-init").addClass("pw-ready");

		/*
		if($('body').hasClass('pw-layout-main') && typeof window.parent.isPresent != "undefined") {
			// send URL to parent window
			console.log('send: href=' + window.location.href);
			parent.window.postMessage('href=' + window.location.href, '*');
		}
		*/
	},

	/**
	 * Setup WireTabs
	 * 
	 */
	setupWireTabs: function() {
		var $tabs = $('.WireTabs');
		if($tabs.length) {
			$(document).on('wiretabclick', function(event, $newTabContent) {
				ProcessWireAdminTheme.wireTabClick($newTabContent);
			});
			setTimeout(function() {
				// identify active tab and trigger event on it
				var $activeTab = $tabs.children('.uk-active');
				if($activeTab.length) {
					var href = $activeTab.find('a').attr('href'); 
					if(href.indexOf('#') === 0) {
						// href points to an #element id in current document
						var $activeContent = $(href);
						if($activeContent.length) ProcessWireAdminTheme.wireTabClick($activeContent);
					}
				}
			}, 500);
		}
	},

	/**
	 * WireTab click, hook handler
	 * 
	 * This primary updates the active tab to add a "pw-tab-muted" class when the background
	 * color of the tab does not match the background color of the tab content. 
	 * 
	 */
	wireTabClick: function($newTabContent) {
		if(!$newTabContent.length) return;
		var $header = null;
		var $inputfield = null;
		if($newTabContent.hasClass('InputfieldWrapper')) {
			$inputfield = $newTabContent.children('.Inputfields').children('.Inputfield').first();
			$header = $inputfield.children('.InputfieldHeader');
		} else if($newTabContent.hasClass('Inputfield')) {
			$inputfield = $newTabContent;
			$header = $newTabContent.children('.InputfieldHeader');
		}
		if(!$header|| !$header.length) return;
		var skip = false;
		var skipClasses = [
			'InputfieldIsPrimary', 
			'InputfieldIsWarning', 
			'InputfieldIsError', 
			'InputfieldIsHighlight',
			'InputfieldIsSuccess'
		];
		for(var n = 0; n < skipClasses.length; n++) {
			if($inputfield.hasClass(skipClasses[n])) {
				skip = true;
				break;
			}
		}
		if(skip) return;
		var hbc = $header.css('background-color').replace(/ /g, ''); // first field header background color
		if(hbc === 'rgb(255,255,255)' || hbc === 'rgba(0,0,0,0)') return;
		var $tab = $('#_' + $newTabContent.attr('id')).parent();
		if(!$tab.length) return;
		if($tab.css('background-color').replace(/ /g, '') != hbc) $tab.addClass('pw-tab-muted');
	}, 

	/**
	 * If layout is one that requires a frame, double check that there is a frame and correct it if not
	 * 
	 */
	checkLayout: function() {
		if($('body').attr('class').indexOf('pw-layout-sidenav') == -1) return; 
		if($('body').hasClass('pw-layout-sidenav-init')) return;
		if(typeof parent == "undefined" || typeof parent.isPresent == "undefined") {
			var href = window.location.href;
			if(href.indexOf('layout=') > -1) {
				href = href.replace(/([?&]layout)=[-_a-zA-Z0-9]+/, '$1=sidenav-init');
			} else {
				href += (href.indexOf('?') > 0 ? '&' : '?') + 'layout=sidenav-init';
			}
			window.location.href = href;
		}
	},

	/**
	 * Called after window successfully resized
	 * 
	 */
	windowResized: function() {
		if($('body').hasClass('pw-fake-resize')) return;
		this.setupMasthead();	
	},

	/**
	 * Setup masthead for mobile or desktop
	 * 
	 */
	setupMasthead: function() {
		var $masthead = $('#pw-masthead');
		var $mastheadMobile = $('#pw-masthead-mobile');
		var width = $(window).width();
		var height = 0;
		var maxHeight = 0;

		if(width > 767) {
			maxHeight = parseInt($masthead.data('pw-height'));
			height = $masthead.children('.pw-container').height();
		} else {
			// force mobile
			height = 999;
		}
		
		if($masthead.hasClass('uk-hidden')) $masthead.removeClass('uk-hidden');
		if(height > maxHeight) {
			// hide masthead, show mobile masthead
			if(!$masthead.hasClass('pw-masthead-hidden')) {
				$masthead.addClass('pw-masthead-hidden').css({
					position: 'absolute',
					top: '-9999px'
				});
				$mastheadMobile.removeClass('uk-hidden');
				$("#offcanvas-toggle").removeClass('uk-hidden');
			}
		} else {
			// show masthead, hide mobile masthead
			if($masthead.hasClass('pw-masthead-hidden')) {
				$mastheadMobile.addClass('uk-hidden');
				$masthead.removeClass('pw-masthead-hidden').css({
					position: 'relative',
					top: 0
				});
				$("#offcanvas-toggle").addClass('uk-hidden');
			}
		}
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
		var $buttons = $("button.pw-head-button, button.head_button_clone");  // head_button_clone is legacy

		// don't continue if no buttons here or if we're in IE
		if($buttons.length == 0) return; // || $.browser.msie) return;

		var $head = $("#pw-content-head-buttons");
		var $lastToggle = null; 
		var $lastButton = null;
		var toggles = {};

		$buttons.each(function() {
			
			var $t = $(this);
			var $a = $t.parent('a');
			var $button;
			
			// console.log($t.attr('id') + ': ' + $t.attr('class'));
			
			if($a.length > 0) {
				
				$button = $t.parent('a').clone(true);
				$head.prepend($button);

			} else if($t.hasClass('pw-head-button') || $t.hasClass('head_button_clone')) {
				
				$button = $t.clone(true);
				$button.attr('data-from_id', $t.attr('id'))
					.attr('id', $t.attr('id') + '_copy')
					.addClass('pw-head-button'); // if not already present
				
				$button.on('click', function() {
					$("#" + $(this).attr('data-from_id')).trigger('click'); 
					return false;
				});
				
				if($button.hasClass('pw-button-dropdown-toggle')) {
					var id = $button.attr('id').replace('pw-dropdown-toggle-', '');	
					toggles[id] = $button;
				} else if($button.hasClass('pw-button-dropdown-main')) {
					var $wrap = $("<span></span>").addClass('pw-button-dropdown-wrap');
					$wrap.append($button).addClass('uk-float-right');
					$head.prepend($wrap);
				} else {
					$button.addClass('uk-float-right');
					$head.prepend($button);
				}
			}
		});
	
		for(var id in toggles) {
			var $toggle = toggles[id];
			var $button = $('#' + id);
			$button.after($toggle);
		}
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
				ul.addClass('pw-dropdown-menu-shorter uk-nav uk-nav-default');
				ul.css('z-index', 9999);
				// Loop over each menu item and customize the list item's html.
				$.each(items, function(index, item) {
					// Menu categories don't get linked so that they don't receive
					// keyboard focus.
					if(item.type != currentType) {
						if(currentType.length) {
							$("<li class='uk-nav-divider'></li>").appendTo(ul);
						}
						$("<li>" + item.type + "</li>").addClass("uk-nav-header").appendTo(ul);
						currentType = item.type;
					}
					that._renderItemData(ul, item);
				});
			},
			_renderItem: function(ul, item) {
				if(item.label == item.template) item.template = '';
				var $label = $("<span></span>").text(item.label).css('margin-right', '3px'); 
				if(item.unpublished) $label.css('text-decoration', 'line-through');
				if(item.hidden) $label.addClass('ui-priority-secondary');
				if(item.icon.length) {
					var $icon = $('<i></i>').addClass('fa fa-fw fa-' + item.icon).css('margin-right', '2px');
					$label.prepend($icon);
				}
				var $a = $("<a></a>")
					.attr('href', item.edit_url)
					.attr('title', item.tip)
					.append($label)
					.append($("<small class='uk-text-muted'></small>").text(item.template));
				
				if(item.edit_url == '#' || !item.edit_url.length) {
					$a.removeAttr('href');
				}
				
				return $("<li></li>").append($a).appendTo(ul);
			}
		});

		$('.pw-search-form').each(function() {
			
			var $form = $(this);
			var $input = $form.find('.pw-search-input');
			var position = { my: 'right top', at: 'right bottom' };
			
			if($form.closest('.uk-offcanvas-bar').length) {
				position.my = 'left top';
				position.at = 'left bottom';
			}
			
			$input.on('click', function(event) {
				// for offcanvas search input, prevents closure of sidebar
				event.stopPropagation();
			});

			$input.adminsearchautocomplete({
				minLength: 2,
				position: position, 
				search: function(event, ui) {
					$form.find(".pw-search-icon").addClass('uk-hidden');
					$form.find(".pw-spinner-icon").removeClass('uk-hidden');
				},
				open: function(event, ui) {
				},
				close: function(event, ui) {
				},
				source: function(request, response) {
					if(request.term === $input.attr('data-help-term')) request.term = 'help';
					var url = $input.parents('form').attr('data-action') + '?q=' + request.term;
					$.getJSON(url, function(data) {
						var len = data.matches.length;
						if(len < data.total) {
							//$status.text(data.matches.length + '/' + data.total);
						} else {
							//$status.text(len);
						}
						$form.find(".pw-search-icon").removeClass('uk-hidden');
						$form.find(".pw-spinner-icon").addClass('uk-hidden');
						response($.map(data.matches, function(item) {
							return {
								label: item.title,
								value: item.title,
								page_id: item.id,
								template: item.template_label ? item.template_label : '',
								edit_url: item.editUrl,
								type: item.type,
								tip: item.tip,
								unpublished: (typeof item.unpublished != "undefined" ? item.unpublished : false), 
								hidden: (typeof item.hidden != "undefined" ? item.hidden : false), 
								locked: (typeof item.locked != "undefined" ? item.locked : false),
								icon: (typeof item.icon != "undefined" ? item.icon : ''),
							}
						}));
					});
				},
				select: function(event, ui) {
					// follow the link if the Enter/Return key is tapped
					$(this).val('');
					if(typeof event.key !== 'undefined') {
						event.preventDefault();
						if(ui.item.edit_url === '#' || ui.item.edit_url.length < 1) return false;
						if(typeof parent.isPresent == "undefined") {
							window.location = ui.item.edit_url;
						} else {
							parent.jQuery('#pw-admin-main')[0].contentWindow.document.location.href = ui.item.edit_url;
						}
					}
				}
			}).on('focus', function() {
				// $(this).siblings('label').find('i').hide(); // hide icon
				setTimeout(function() { $input.attr('placeholder', $input.attr('data-help-note')); }, 1250); 
			}).on('blur', function() {
				$input.attr('placeholder', '');
				// $status.text('');
				// $(this).siblings('label').find('i').show(); // show icon
			});
		}); 

	},

	/**
	 * Initialize sidebar and offcanvas navigation
	 * 
	 * Enables ajax loading support
	 * 
	 */
	setupSideNav: function() {
		$('.pw-sidebar-nav').on('click', 'a', function(event) {
			var $a = $(this);
			if(!$a.hasClass('pw-has-ajax-items')) {
				// as of Uikit beta 34, clicking nav items closes the offcanvas unless the following line is here
				event.stopPropagation();
				return;
			}
			var $ul = $a.closest('li').find('ul');
			var url = $(this).attr('data-json');
			if($ul.hasClass('navJSON')) return false;
			var $spinner = $("<li class='pw-nav-spinner'><i class='fa fa-spin fa-spinner'></i></li>"); 
			$ul.append($spinner);
			$.getJSON(url, function(data) {
				var $a2 = $a.clone();
				var $icon2 = $a2.find('i');
				if(!$icon2.length) {
					$icon2 = $("<i></i>");
					$a2.prepend($icon2);
				}
				$icon2.attr('class', 'fa fa-fw fa-arrow-circle-right pw-nav-icon');
				$a2.removeAttr('data-json').removeAttr('class')
				$a2.find('small').remove(); // i.e. numChildren
				var $li = $("<li></li>").addClass('pw-nav-dup').append($a2);
				$ul.append($li);
				if(data.add) {
					var addUrl = data.add.url;
					if(addUrl.indexOf('/') !== 0) addUrl = data.url + addUrl;
					var $li2 = $(
						"<li class='pw-nav-add'>" +
						"<a href='" + data.url + data.add.url + "'>" +
						"<i class='fa fa-fw fa-" + data.add.icon + " pw-nav-icon'></i>" +
						data.add.label + "</a>" +
						"</li>"
					);
					$ul.append($li2);
				}
				// populate the retrieved items
				$.each(data.list, function(i) {
					if(this.label.indexOf('<span') > -1) {
						// Uikit beta 34 does not like span elements in the label for some reason
						this.label = this.label.replace(/<\/?span[^>]*>/g, '');
					}
					var icon = '';
					var $label = $("<div>" + this.label + "</div>");
					var label = $label.text();
					if(label.length > 30) {
						// truncate label
						var $small = $label.find('small');
						if($small.length) $small.remove();
						label = $label.text();
						label = label.substring(0, 30);
						var n = label.lastIndexOf(' ');
						if(n > 3) label = label.substring(0, n) + '… ';
						$label.html(label);
						if($small.length) $label.append($small);
						//label = $label.html();
					}
					label = $label.html().replace('&nbsp;', ' ');
					if(this.icon) icon = "<i class='fa fa-fw fa-" + this.icon + " pw-nav-icon'></i>";
					var url = this.url.indexOf('/') === 0 ? this.url : data.url + this.url;
					var $a = $("<a href='" + url + "'>" + icon + label + "</a>");
					var $li = $("<li></li>").append($a);
					if(this.navJSON != "undefined" && this.navJSON) {
						$a.addClass('pw-has-items pw-has-ajax-items').attr('data-json', this.navJSON);
						var $ul2 = $("<ul class='uk-nav-sub uk-nav-parent-icon'></ul>");
						$li.addClass('uk-parent').append($ul2);
						UIkit.nav($ul2, { multiple: true });
					}
					if(typeof this.className != "undefined" && this.className && this.className.length) {
						$li.addClass(this.className);
					}
					if($li.hasClass('pw-nav-add') || $li.hasClass('pw-pagelist-show-all')) {
						$ul.children('.pw-nav-dup').after($li.removeClass('separator').addClass('pw-nav-add'));
					} else {
						$ul.append($li);
					}
				});
				$spinner.remove();
				$ul.addClass('navJSON').addClass('length' + parseInt(data.list.length)).hide();
				if($ul.children().length) $ul.css('opacity', 1.0).fadeIn('fast');
			});
			return false;
		}); 
	},

	/**
	 * Initialize Inputfield forms and Inputfields for Uikit
	 * 
	 */
	setupInputfields: function() {
		
		var noGrid = $('body').hasClass('AdminThemeUikitNoGrid'); 

		function initFormMarkup($target) {
			
			// horizontal forms setup (currently not used)
			$("form.uk-form-horizontal").each(function() {
				$(this).find('.InputfieldContent > .Inputfields').each(function() {
					var $content = $(this);
					$content.addClass('uk-form-vertical');
					$content.find('.uk-form-label').removeClass('uk-form-label');
					$content.find('.uk-form-controls').removeClass('uk-form-controls');
				});
				$(this).find('.InputfieldSubmit, .InputfieldButton').each(function() {
					$(this).find('.InputfieldContent').before("<div class='uk-form-label'>&nbsp;</div>");
				});
			});

			// card inputfields setup
			$(".InputfieldNoBorder.uk-card").removeClass('uk-card uk-card-default');

			// offset inputfields setup
			$(".InputfieldIsOffset.InputfieldColumnWidthFirst").each(function() {
				// make all fields in row maintain same offset as first column
				var $t = $(this);
				var $f;
				do {
					$f = $t.next(".InputfieldColumnWidth");
					if(!$f.length || $f.hasClass('InputfieldColumnWidthFirst')) break;
					$f.addClass('InputfieldIsOffset');
					$t = $f;
				} while(true);
			});
	
			// identify first and last rows for each group of inputfields
			$(".Inputfields").each(function() {
				identifyFirstLastRows($(this));
			});

			// update any legacy inputfield declarations
			$(".ui-widget.Inputfield, .ui-widget-header.InputfieldHeader, .ui-widget-content.InputfieldContent")
				.removeClass('ui-widget ui-widget-header ui-widget-content');

			// pagination, if present
			$('.MarkupPagerNav:not(.uk-pagination)').each(function() {
				$(this).addClass('uk-pagination');
			});
			
			 // apply to inputs that don’t already have Uikit classes 
			if(typeof $target == "undefined") $target = $('.InputfieldForm');
			var $selects = $('select:not([multiple]):not(.uk-select)', $target); 
			$selects.addClass('uk-select'); 
			/* add support for the following as needed:
			var $inputs = $('input:not(.uk-input):not(:checkbox):not(:radio):not(:button):not(:submit):not(:hidden)', $target);
			var $textareas = $('textarea:not(.uk-textarea)', $target);
			var $checkboxes = $('input:checkbox:not(.uk-checkbox)', $target);
			var $radios = $('input:radio:not(.uk-radio)', $target);
			*/
		}
		
		function identifyFirstLastRows($inputfields) {
			$(".InputfieldRowFirst", $inputfields).removeClass("InputfieldRowFirst");
			$(".InputfieldRowLast", $inputfields).removeClass("InputfieldRowLast");
			var $in = $inputfields.children(".Inputfield:not(.InputfieldStateHidden)").first();
			if(!$in.length) return; 
			do {
				$in.addClass('InputfieldRowFirst');
				$in = $in.next('.Inputfield:not(.InputfieldStateHidden)');
			} while($in.hasClass('InputfieldColumnWidth') && !$in.hasClass('InputfieldColumnWidthFirst'));
			$in = $inputfields.children('.Inputfield:last-child');
			while($in.length && $in.hasClass('InputfieldStateHidden')) {
				$in = $in.prev('.Inputfield');
			}
			do {
				$in.addClass('InputfieldRowLast');
				if(!$in.hasClass('InputfieldColumnWidth') || $in.hasClass('InputfieldColumnWidthFirst')) break;
				$in = $in.prev('.Inputfield:not(.InputfieldStateHidden)');
			} while($in.hasClass('InputfieldColumnWidth'));
		}

		var ukGridClassCache = [];
		// get or set uk-width class
		// width: may be integer of width, or classes you want to set (if $in is also provided)
		// $in: An optional Inputfield that you want to populate given or auto-determined classes to
		function ukGridClass(width, $in) {
			
			if(noGrid && typeof $in != "undefined") {
				if(typeof width == "string") {
					$in.addClass(width);
				} else {
					$in.css('width', width + '%');
				}
				return '';
			}
			
			var ukGridClassDefault = 'uk-width-1-1';
			var ukGridClass = ukGridClassDefault;
			var widthIsClass = false;
			
			if(typeof width == "string" && typeof $in != "undefined") {
				// class already specified in width argument
				ukGridClass = width;
				widthIsClass = true;
			} else if(!width || width >= 100) {
				// full width
				ukGridClass = ukGridClassDefault;
			} else if(typeof ukGridClassCache[width] != "undefined") {
				// use previously cached value
				ukGridClass = 'uk-width-' + ukGridClassCache[width];
			} else {
				// determine width from predefined setting
				for(var pct in ProcessWire.config.ukGridWidths) {
					var cn = ProcessWire.config.ukGridWidths[pct];
					pct = parseInt(pct);
					if(width >= pct) {
						ukGridClass = cn;
						break;
					}
				}
				if(ukGridClass.length) {
					ukGridClassCache[width] = ukGridClass;
					ukGridClass = 'uk-width-' + ukGridClass;
				}
			}
			
			if(!widthIsClass && ukGridClass && ukGridClass != ukGridClassDefault) {
				ukGridClass += '@m';
			}

			if(typeof $in != "undefined") {
				if(ukGridClass && $in.hasClass(ukGridClass)) {
					// no need to do anything
				} else {
					removeUkGridClass($in);
					if(ukGridClass) $in.addClass(ukGridClass);
				}
			}
			
			return ukGridClass;
		}

		// remove any uk-width- classes from given str or $inputfield object
		function removeUkGridClass(str) {
			var $in = null;
			if(typeof str != "string") {
				$in = str;
				str = $in.attr('class');
			}
			if(str.indexOf('uk-width-') > -1) {
				var cls = str.replace(/uk-width-(\d-\d|expand)[@smxl]*\s*/g, '');
				if($in !== null) $in.attr('class', cls);
			}
			return str;
		}
		
		// update widths and classes for Inputfields having the same parent as given $inputfield
		// this is called when an Inputfield is shown or hidden
		function updateInputfieldRow($inputfield) {
			if(!$inputfield) return;

			var $inputfields = $inputfield.parent().children('.Inputfield');
			var $lastInputfield = null; // last non-hidden Inputfield
			var width = 0; // current combined width of all Inputfields in row
			var widthHidden = 0; // amount of width in row occupied by hidden field(s)
			var w = 0; // current Inputfield width
			var lastW = 0; // last Inputfield non-hidden Inputfield width
			var debug = false; // verbose console.log messages

			function consoleLog(msg, $in) {
				if(!debug) return;
				if(typeof $in == "undefined") $in = $inputfield;	
				var id = $in.attr('id');
				id = id.replace('wrap_Inputfield_', '');
				console.log(id + ' (combined width=' + width + ', w=' + w + '): ' + msg);	
			}
			
			function expandLastInputfield($in) {
				if(typeof $in == "undefined") $in = $lastInputfield;
				if($in) {
					if(noGrid) {
						$in.addClass('InputfieldColumnWidthLast'); 
					} else {
						ukGridClass('InputfieldColumnWidthLast uk-width-expand', $in);
					}
				}
			}
			
			function applyHiddenInputfield() {
				// hidden column, reserve space even though its hidden
				if(debug) consoleLog('A: hidden', $inputfield);
				lastW += w;
				width += w;
				if($lastInputfield && width >= 95) {
					// finishing out row, update last visible column to include the width of the hidden column
					// lastW += widthHidden;
					if(debug) consoleLog('Updating last visible Inputfield to width=' + lastW, $lastInputfield);
					ukGridClass(lastW, $lastInputfield);
					width = 0;
					lastW = 0;
					widthHidden = 0;
					$lastInputfield = null;
				} else {
					widthHidden += w;
				}
			}
			
			function applyFullWidthInputfield() {
				// full width column consumes its own row, so we can reset everything here and exit
				if(debug) consoleLog("Skipping because full-width", $inputfield);
				if(width < 100 && $lastInputfield) expandLastInputfield($lastInputfield);
				$lastInputfield = null;
				widthHidden = 0;
				lastW = 0;
				width = 0;
			}
			
			$inputfields.each(function() {

				$inputfield = $(this);
				
				var isLastColumn = false;
				var isFirstColumn = false;
				var hasWidth = $inputfield.hasClass('InputfieldColumnWidth'); 
				var isNewRow = !hasWidth || $inputfield.hasClass('InputfieldColumnWidthFirst');
				
				if(isNewRow && $lastInputfield && width < 100) {
					// finish out the previous row, letting width expand to 100%
					expandLastInputfield($lastInputfield);
				}
			
				// if column has width defined, pull from its data-colwidth property
				w = hasWidth ? parseInt($inputfield.attr('data-colwidth')) : 0;

				if(!w || w >= 95) {
					// full-width
					applyFullWidthInputfield();
					return;
				}

				if($inputfield.hasClass('InputfieldStateHidden')) {
					// hidden 
					applyHiddenInputfield();
					return;
				}
			
				if(!width || width >= 100) {
					// starting a new row
					width = 0;
					isFirstColumn = true;
					isLastColumn = false;
					if(debug) consoleLog('B: starting new row', $inputfield);
				} else if(width + w > 100) {
					// start new row and update width for last column
					if($lastInputfield) expandLastInputfield($lastInputfield); 
					width = 0;
					isFirstColumn = true;
					if(debug) consoleLog('C: start new row because width would exceed 100%', $inputfield);
				} else if(width + w == 100) {
					// width comes to exactly 100% so make this the last column in the row
					isLastColumn = true; 
					if(debug) consoleLog('D: width is exactly 100%, so this is the last column', $inputfield);
				} else if(width + w >= 95) {
					// width is close enough to 100% so treat it the same
					isLastColumn = true;
					w = 100 - width;
					if(debug) consoleLog('D2: width is close enough to 100%, so this is the last column', $inputfield);
				} else {
					// column that isn't first or last column
					if(debug) consoleLog('E: not first or last column', $inputfield);
				}

				if(isLastColumn) {
					$inputfield.addClass('InputfieldColumnWidthLast');
				} else {
					$inputfield.removeClass('InputfieldColumnWidthLast');
				}
				if(isFirstColumn) {
					$inputfield.addClass('InputfieldColumnWidthFirst');
					widthHidden = 0;
				} else {
					$inputfield.removeClass('InputfieldColumnWidthFirst');
				}
				
				if(isLastColumn) {
					// last column in this row, reset for new row
					$lastInputfield = null;
					width = 0;
					lastW = 0;
					// if there was any width from previous hidden fields in same row, add it to this field
					if(widthHidden) w += widthHidden;
					widthHidden = 0;
				} else {
					$lastInputfield = $inputfield;
					width += w;
					lastW = w;
				}
				
				ukGridClass(w, $inputfield);
			});
			
			if(width < 100 && $lastInputfield) expandLastInputfield($lastInputfield);
			
			// $inputfields.find('.InputfieldColumnWidthLast').removeClass('InputfieldColumnWidthLast');
		} // function updateInputfieldRow
		
		var showHideInputfieldTimer = null;

		// event called when an inputfield is hidden or shown
		var showHideInputfield = function(event, inputfield) {
			var $inputfield = $(inputfield);
			if(event.type == 'showInputfield') {
				$inputfield.removeClass('uk-hidden');
			} else {
				$inputfield.show();
				$inputfield.addClass('uk-hidden');
			}
			updateInputfieldRow($inputfield);
			if(showHideInputfieldTimer) return;
			showHideInputfieldTimer = setTimeout(function() {
				identifyFirstLastRows($inputfield.closest('.Inputfields'));
				var $inputfields = $inputfield.find('.Inputfields');
				if($inputfields.length) {
					$inputfields.each(function() {
						identifyFirstLastRows($(this));
					});
				}
				showHideInputfieldTimer = null;	
			}, 100);
		};

		
		$(document).on('reloaded', function() { initFormMarkup($(this)) }); // function() intentional
		$(document).on('hideInputfield', showHideInputfield);
		$(document).on('showInputfield', showHideInputfield);
		$(document).on('columnWidth', '.Inputfield', function(e, width) {
			ukGridClass(width, $(this)); 
			return false;
		}); 

		$('body').addClass('InputfieldColumnWidthsInit');
		if(ProcessWire.config.adminTheme) {
			Inputfields.toggleBehavior = ProcessWire.config.adminTheme.toggleBehavior;
		}
		initFormMarkup();
	},

	/**
	 * Initialize tooltips, converting jQuery UI tooltips to Uikit tooltips before they get init'd by jQuery
	 * 
	 */
	setupTooltips: function() {
		$('.tooltip, .pw-tooltip').each(function() {
			$(this).removeClass('tooltip pw-tooltip');
			UIkit.tooltip($(this));
		});
	},

	/**
	 * Mouseover event used by _sidenav-side.php and _sidenav-tree.php
	 * 
	 */
	linkTargetMainMouseoverEvent: function() {
		
		var $a = $(this);
		var href = $a.attr('href');
		
		if(href.length < 2) return; // skip '#'
		if($a.attr('target')) return; // already set
		
		/*
		 if(href.indexOf(ProcessWire.config.urls.admin) > -1) {
		 href += (href.indexOf('?') > -1 ? '&' : '?') + 'layout=sidenav-main';
		 $a.attr('href', href);
		 }
		 */
		
		if($a.parent('li').hasClass('PageListActionView')) {
			$a.attr('target', '_top');
		} else {
			$a.attr('target', 'main');
		}
		
	},

	/**
	 * Click event for ProcessWire logo
	 * 
	 */
	logoClickEvent: function() {
		if($('body').hasClass('pw-layout-sidenav-init')) {
			if($('#pw-admin-side').length) {
				// sidebar layout navigation present
				toggleSidebarPane();
			} else {
				// show offcanvas nav
				UIkit.toggle('#offcanvas-nav').toggle();
			}
		} else if(ProcessWire.config.adminTheme.logoAction == 1) {
			// show offcanvas nav
			UIkit.toggle('#offcanvas-nav').toggle();
		} else {
			return true;
		}
		return false;
	}
};
$(document).ready(function() {
	ProcessWireAdminTheme.ready();
});
