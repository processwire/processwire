/**
 * ProcessWire Admin common javascript
 *
 * Copyright 2016-2024 by Ryan Cramer
 * 
 */

var ProcessWireAdmin = {

	/**
	 * Initialize all
	 * 
	 */
	init: function() {
		this.setupButtonStates();
		this.setupTooltips();
		this.setupDropdowns();
		this.setupNotices();
	},

	setupNotices: function() {
		$(".pw-notice-group-toggle").on('click', function() {
			var $parent = $(this).closest('.pw-notice-group-parent'); 
			var $children = $parent.nextUntil('.pw-notice-group-parent');
			if($parent.hasClass('pw-notice-group-open')) {
				$parent.removeClass('pw-notice-group-open');
				$children.slideUp('fast');
			} else {
				$parent.addClass('pw-notice-group-open');
				$children.slideDown('fast');
			}
			$parent.find('i[data-toggle]').each(function() {
				$(this).toggleClass($(this).attr('data-toggle'));
			}); 
			return false;
		}); 
	}, 
	
	/**
	 * Enable jQuery UI tooltips
	 *
	 */
	setupTooltips: function() {
		$("a.tooltip, .pw-tooltip").tooltip({
			position: {
				my: "center bottom", // bottom-20
				at: "center top"
			}
		}).on('mouseenter', function() {
			var $a = $(this);
			if($a.is('a')) {
				$a.addClass('ui-state-hover');
			} else {
				$a.data('pw-tooltip-cursor', $a.css('cursor'));
				$a.css('cursor', 'pointer');	
			}
			$a.addClass('pw-tooltip-hover');
			$a.css('cursor', 'pointer');
		}).on('mouseleave', function() {
			var $a = $(this);
			$a.removeClass('pw-tooltip-hover ui-state-hover');
			if(!$a.is('a')) {
				$a.css('cursor', $a.data('pw-tooltip-cursor'));
			}
		});
	},
	
	/**
	 * Make buttons utilize the jQuery button state classes
	 *
	 */
	setupButtonStates: function() {
		// jQuery UI button states
		$(document).on('mouseover', '.ui-button', function() {
			var $t = $(this);
			$t.removeClass("ui-state-default").addClass("ui-state-hover");
			if($t.hasClass('ui-priority-secondary')) $t.toggleClass('xui-priority-secondary ui-priority-secondary');
			if($t.hasClass('pw-button-dropdown-main')) {
				$t.siblings('#pw-dropdown-toggle-' + $t.attr('id')).trigger('mouseover');
			}
		}).on('mouseout', '.ui-button', function() {
			var $t = $(this);
			$t.removeClass("ui-state-hover").addClass("ui-state-default");
			if($t.hasClass('xui-priority-secondary')) $t.toggleClass('xui-priority-secondary ui-priority-secondary');
			if($t.hasClass('pw-button-dropdown-main')) {
				$t.siblings('#pw-dropdown-toggle-' + $t.attr('id')).trigger('mouseout');
			}
		}).on('click', '.ui-button', function() {
			$(this).removeClass("ui-state-default").addClass("ui-state-active"); // .effect('highlight', {}, 100); 
		});
		/*
		.on('click', 'a > button', function() {
			var $a = $(this).parent();
			var target = $a.attr('target');
			if(typeof target != "undefined" && target == '_blank') {
				// skip
			} else {
				// make buttons with <a> tags click to the href of the <a>
				window.location = $a.attr('href');
			}
		});
		*/
	},

	/**
	 * Setup dropdown menus
	 * 
	 */
	setupDropdowns: function() {

		// whether or not dropdown positions are currently being monitored
		var dropdownPositionsMonitored = false;

		var hoveredDropdownAjaxItem;

		function setupDropdown() {

			var $a = $(this);
			var $ul;
			
			if($a.hasClass('pw-dropdown-init')) return;
		
			if($a.attr('data-pw-dropdown')) {
				// see if it is specifying a certain <ul>
				// first check if the selector matches a sibling
				$ul = $a.siblings($a.attr('data-pw-dropdown'));
				// if no match for sibling, check entire document
				if(!$ul.length) $ul = $($a.attr('data-pw-dropdown'));
			} else {
				// otherwise use the <ul> that is the sibling
				$ul = $a.siblings('.pw-dropdown-menu');
			}

			$ul.hide();
			$a.data('pw-dropdown-ul', $ul);

			if($a.is('button')) {
				if($a.find('.ui-button-text').length == 0) $a.button();
				if($a.attr('type') == 'submit') {
					$a.on('click', function() {
						$a.addClass('pw-dropdown-disabled');
						setTimeout(function() {
							$a.removeClass('pw-dropdown-disabled');
						}, 2000);
					});
				}
			} else {
				// $ul.css({ 'border-top-right-radius': 0 }); 
			}
			
			// hide nav when an item is selected to avoid the whole nav getting selected
			$ul.find('a').on('click', function() {
				$ul.hide();
				return true;
			});

			// prepend icon to dropdown items that themselves have more items
			$ul.find(".pw-has-items").each(function() {
				var $icon = $("<i class='pw-has-items-icon fa fa-angle-right ui-priority-secondary'></i>");
				$(this).prepend($icon);
			});

			// when the mouse leaves the dropdown menu, hide it
			if($a.hasClass('pw-dropdown-toggle-click')) {
				var timer = null;
				function mouseleaver() {
					if(timer) clearTimeout(timer);
					timer = setTimeout(function() {
						if(($ul.length && $ul[0].matches(':hover')) || ($a.length && $a[0].matches(':hover'))) {
							return;
						}
						$ul.fadeOut('fast');
						$a.removeClass('hover pw-dropdown-toggle-open');
					}, 1000);
				}
				$ul.on('mouseleave', mouseleaver);
				$a.on('mouseleave', mouseleaver);
			} else {
				$ul.on('mouseleave', function() {
					//if($a.is(":hover")) return;
					//if($a.filter(":hover").length) return;
					$ul.hide();
					$a.removeClass('hover');
				});
			}
			$a.addClass('pw-dropdown-init');
		}

		function mouseenterDropdownToggle(e) {
			
			var $a = $(this);
			var $ul = $a.data('pw-dropdown-ul');
			var delay = $a.hasClass('pw-dropdown-toggle-delay') ? 700 : 0;
			var lastOffset = $ul.data('pw-dropdown-last-offset');
			var timeout = $a.data('pw-dropdown-timeout');
			
			if($a.hasClass('pw-dropdown-toggle-click')) {
				if(e.type != 'mousedown') return false;
				$a.removeClass('ui-state-focus');
				if($a.hasClass('pw-dropdown-toggle-open')) {
					$a.removeClass('pw-dropdown-toggle-open hover');
					$ul.hide();
					return;
				} else {
					$('.pw-dropdown-toggle-open').each(function() {
						var $a = $(this);
						var $ul = $a.data('pw-dropdown-ul');
						$ul.trigger('mouseleave');
					});
					$a.addClass('pw-dropdown-toggle-open');
					/*
					$('body').one('click', function() {
						$a.removeClass('pw-dropdown-toggle-open hover');
						$ul.hide();
					});
					*/
				}
			} 
				
			if($a.hasClass('pw-dropdown-disabled')) return;

			timeout = setTimeout(function() {
				if($a.hasClass('pw-dropdown-disabled')) return;
				var offset = $a.offset();
				if(lastOffset != null) {
					if(offset.top != lastOffset.top || offset.left != lastOffset.left) {
						// dropdown-toggle has moved, destroy and re-create
						$ul.menu('destroy').removeClass('pw-dropdown-ready');
					}
				}

				if(!$ul.hasClass('pw-dropdown-ready')) {
					$ul.css('position', 'absolute');
					$ul.prependTo($('body')).addClass('pw-dropdown-ready').menu();
					var position = {my: 'right top', at: 'right bottom', of: $a};
					var my = $ul.attr('data-my');
					var at = $ul.attr('data-at');
					if(my) position.my = my;
					if(at) position.at = at;
					$ul.position(position).css('z-index', 200);
				}

				$a.addClass('hover');
				$ul.show();
				$ul.trigger('pw-show-dropdown', [ $ul ]); 
				$ul.data('pw-dropdown-last-offset', offset);

			}, delay);

			$a.data('pw-dropdown-timeout', timeout);
		}

		function mouseleaveDropdownToggle() {

			var $a = $(this);
			var $ul = $a.data('pw-dropdown-ul');
			var timeout = $a.data('pw-dropdown-timeout');

			if(timeout) clearTimeout(timeout);
			setTimeout(function() {
				// filter(':hover') does not work in jQuery 3.x
				// if($ul.filter(":hover").length) return; 
				var hovered = $ul.filter(function() { return $(this).is(':hover'); });
				if(hovered.length) return;
				$ul.find('ul').hide();
				$ul.hide();
				$a.removeClass('hover');
			}, 50);

			if($("body").hasClass('touch-device')) {
				$(this).attr('data-touchCnt', 0);
			}
		}

		function hoverDropdownAjaxItem($a) {
			var fromAttr = $a.attr('data-from');
			if(!fromAttr) return;
			var $from = $('#' + $a.attr('data-from'));
			if($from.length > 0) setTimeout(function() {
				var fromLeft = $from.offset().left;
				//if($a.attr('id') == 'topnav-page-22') fromLeft--;
				var $ul = $a.closest('li').parent('ul');
				var thisLeft = $ul.offset().left;
				if(thisLeft != fromLeft) $ul.css('left', fromLeft);
			}, 500);
		}

		function mouseenterDropdownAjaxItem() {

			var $a = $(this);
			hoveredDropdownAjaxItem = $a;

			setTimeout(function() {

				// check if user wasn't hovered long enough for this to be their intent
				if(!hoveredDropdownAjaxItem) return;
				if(hoveredDropdownAjaxItem != $a) return;

				$a.addClass('pw-ajax-items-loaded');
				// var url = $a.attr('href');
				var url = $a.attr('data-json');
				var $ul = $a.siblings('ul');
				var setupDropdownHover = false;
				var $itemsIcon = $a.children('.pw-has-items-icon');
				$itemsIcon.removeClass('fa-angle-right').addClass('fa-spinner fa-spin');
				$ul.css('opacity', 0);

				$.getJSON(url, function(data) {
					$itemsIcon.removeClass('fa-spinner fa-spin').addClass('fa-angle-right');
					
					if(!data.list) {
						console.log(data);
						return;
					}

					// now add new event to monitor menu positions
					if(!dropdownPositionsMonitored && data.list.length > 10) {
						dropdownPositionsMonitored = true;
						setupDropdownHover = true;
						$(document).on('mouseenter', 'ul.pw-dropdown-menu a', function() {
							hoverDropdownAjaxItem($(this));
						});
					}

					if(data.add) {
						var addUrl = data.add.url;
						if(addUrl.indexOf('/') !== 0) addUrl = data.url + addUrl;
						var $li = $(
							"<li class='ui-menu-item add'>" +
							"<a href='" + addUrl + "'>" +
							"<i class='fa fa-fw fa-" + data.add.icon + "'></i>" +
							data.add.label + "</a>" +
							"</li>"
						);
						$ul.append($li);
					}
					
					var numSubnavJSON = 0;
					
					// populate the retrieved items
					$.each(data.list, function(n) {
						
						var icon = '';
						var url = '';
						
						if(this.icon) {
							icon = "<i class='ui-priority-secondary fa fa-fw fa-" + this.icon + "'></i>";
						}
						
						if(this.url == 'navJSON') {
							// click triggers another navJSON load
						} else if(this.url.indexOf('/') === 0) {
							url = this.url;
						} else if(this.url.length) {
							url = data.url + this.url;
						}
						
						var $li = $("<li class='ui-menu-item'></li>"); 
						var $a = $("<a>" + icon + this.label + "</a>");
						var $ulSub = null;
						
						if(url.length) $a.attr('href', url);
					
						if(this.navJSON) {
							$a.attr('data-json', this.navJSON).addClass('pw-has-items pw-has-ajax-items');
							$ulSub = $("<ul></ul>").addClass('subnavJSON');
							var $icon = $("<i class='pw-has-items-icon fa fa-angle-right ui-priority-secondary'></i>");
							$a.prepend($icon);
							$li.prepend($a).append($ulSub);
							numSubnavJSON++;
						} else {
							$li.prepend($a);
						}
						
						if(typeof this.className != "undefined" && this.className && this.className.length) {
							$li.addClass(this.className);
						}
						
						$ul.append($li);
					});
					
					$ul.addClass('navJSON').addClass('length' + parseInt(data.list.length)).hide();
					if($ul.children().length) $ul.css('opacity', 1.0);
					if(hoveredDropdownAjaxItem == $a) $ul.fadeIn('fast');
					
					if(numSubnavJSON) {
						var numParents = $ul.parents('ul').length;
						$ul.find('ul.subnavJSON').css('z-index', 200 + numParents);
						$ul.menu({});
					}

					// trigger the first call
					hoverDropdownAjaxItem($a);

				}); // getJSON

			}, 250); // setTimeout

		}

		var $lastTouchClickItem = null;

		function touchClick(e) {
			var $item = $(this);
			var touchCnt = $item.attr('data-touchCnt');
			if($lastTouchClickItem && $item.attr('id') != $lastTouchClickItem.attr('id')) {
				$lastTouchClickItem.attr('data-touchCnt', 0);
			}
			$lastTouchClickItem = $item;
			if(!touchCnt) touchCnt = 0;
			touchCnt++;
			$item.attr('data-touchCnt', touchCnt);
			
			if(touchCnt == 2 || ($item.hasClass('pw-has-ajax-items') && !$item.closest('ul').hasClass('topnav'))) {
				var href = $item.attr('href');
				$item.attr('data-touchCnt', 0);
				if(typeof href != "undefined" && href.length > 1) {
					return true;
				} else {
					$item.trigger('mouseleave');
				}
			} else {
				var datafrom = $item.attr('data-from');	
				if(typeof datafrom == "undefined") var datafrom = '';
				if(datafrom.indexOf('topnav') > -1) {
					var from = datafrom.replace('topnav-', '') + '-';
					$("a.pw-dropdown-toggle.hover:not('." + from + "')").attr('data-touchCnt', 0).trigger('mouseleave');
				}
				$item.mouseenter();
			}
			return false;
		}

		function init() {

			if($("body").hasClass('touch-device')) {
				$(document).on("touchstart", "a.pw-dropdown-toggle, a.pw-has-items", touchClick);
			}

			$(".pw-dropdown-menu").on("click", "a:not(.pw-modal)", function(e) {
				e.stopPropagation();
			});

			$(".pw-dropdown-toggle").each(setupDropdown);
			
			$('.InputfieldForm').on('reloaded', function() {
				$('.pw-dropdown-toggle:not(.pw-dropdown-init)').each(setupDropdown);
			}); 

			$(document)
				.on('mousedown', '.pw-dropdown-toggle-click', mouseenterDropdownToggle)
				.on('mouseenter', '.pw-dropdown-toggle:not(.pw-dropdown-toggle-click)', mouseenterDropdownToggle)
				.on('mouseleave', '.pw-dropdown-toggle:not(.pw-dropdown-toggle-click)', mouseleaveDropdownToggle)
				.on('mouseenter', '.pw-dropdown-menu a.pw-has-ajax-items:not(.pw-ajax-items-loaded)', mouseenterDropdownAjaxItem) // navJSON
				.on('mouseleave', '.pw-dropdown-menu a.pw-has-ajax-items', function() { // navJSON
					hoveredDropdownAjaxItem = null;
				});
		}

		init();
		
	} // setupDropdowns
	
};

if(typeof ProcessWire != "undefined") {
	
	/**
	 * Confirmation dialog
	 * 
	 * ~~~~~
	 * ProcessWire.confirm('Send this message now?', function() {
	 *   console.log('Ok');
	 * }, function() {
	 *   console.log('Cancel');
	 * }); 
	 * ~~~~~
	 * More options and syntax available in ProcessWire 3.0.248+ (only message argument is required):
	 * ~~~~~
	 * ProcessWire.confirm({
	 *   message: '<h2>Send this message now?</h2>', 
	 *   allowMarkup: true,
	 *   funcOk: function() { console.log('Ok') }, 
	 *   funcCancel: function() { console.log('Cancel') }, 
	 *   labelOk: 'Send now',  // Uikit admin only
	 *   labelCancel: 'Cancel send' // Uikit admin only
	 * });
	 * ~~~~~
	 * 
	 * @param message Message to display (or question to ask)
	 * @param funcOk Callback called on "Ok"
	 * @param funcCancel Callback called on "Cancel" (optional)
	 * @param bool Allow markup in confirm message? (default=false)
	 * 
	 */
	ProcessWire.confirm = function(message, funcOk, funcCancel, allowMarkup) {
	
		var settings = {};
		if(typeof message === 'object') {
			settings = message;
			if(typeof settings.funcOk != 'undefined') funcOk = settings.funcOk;
			if(typeof settings.funcCancel != 'undefined') funcCancel = settings.funcCancel;
			if(typeof settings.allowMarkup != 'undefined') allowMarkup = settings.allowMarkup;
			message = settings.message;
		}
		
		if(typeof allowMarkup == "undefined") allowMarkup = false;
		
		if(typeof UIkit != "undefined") {
			var messageHtml = '';
			if(allowMarkup) {
				messageHtml = message;
				message = '<!--message-->';
			} else {
				message = ProcessWire.entities1(message);
			}
			var labels = ProcessWire.config.AdminThemeUikit.labels;
			var options = { i18n: {} };
			if(typeof labels != 'undefined') {
				options.i18n = { ok: labels['ok'], cancel: labels['cancel'] };
			}
			if(typeof settings.labelOk != 'undefined' && settings.labelOk.length) {
				options.i18n['ok'] = settings.labelOk;
			}
			if(typeof settings.labelCancel != 'undefined' && settings.labelCancel.length) {
				options.i18n['cancel'] = settings.labelCancel;
			}
			var modal = UIkit.modal.confirm(message, options);
			if(allowMarkup) {
				$(modal.dialog.$el).find('.uk-modal-body').html(messageHtml);
			}
			modal.then(function() {
				if(funcOk != "undefined") funcOk();
			}, function () {
				if(funcCancel != "undefined") funcCancel();
			});
			
		} else if(typeof vex != "undefined" && typeof funcOk != "undefined") {
			vex.dialog.confirm({
				message: message,
				callback: function(v) {
					if(v) {
						funcOk();
					} else if(typeof funcCancel != "undefined") {
						funcCancel();
					}
				}
			});
		} else if(typeof funcOk != "undefined") {
			if(confirm(message)) {
				funcOk();
			} else if(typeof funcCancel != "undefined") {
				funcCancel();
			}
			
		} else {
			// regular JS confirm behavior
			return confirm(message);
		}
	};
	
	/**
	 * Show an alert dialog box
	 * 
	 * ~~~~~
	 * // simple example
	 * ProcessWire.alert('Please correct your mistakes');
	 *
	 * // verbose example (PW 3.0.248+) only message argument is required
	 * ProcessWire.alert({
	 *   message: '<h2>Please correct your mistakes</h2>', 
	 *   allowMarkup: true, 
	 *   expire: 5000, // 5 seconds
	 *   func: function() { console.log('alert box closed'), 
	 *   labelOk: 'I understand' // Uikit admin only
	 * }); 
	 * ~~~~~
	 * 
	 * @param string message Message to display
	 * @param bool allowMarkup Allow markup in message? (default=false)
	 * @param int expire Automatically close after this many seconds (default=0, off)
	 * @param callable func Function to call when alert box is closed
	 * 
	 * 
	 */
	ProcessWire.alert = function(message, allowMarkup, expire, func) {
	
		var settings = {};
		if(typeof message === 'object') {
			settings = message;
			if(typeof settings.allowMarkup != 'undefined') allowMarkup = settings.allowMarkup;
			if(typeof settings.expire != 'undefined') expire = settings.expire;
			if(typeof settings.func != 'undefined') func = settings.func;
			message = settings.message;
		}
		
		if(typeof allowMarkup == "undefined") allowMarkup = false;
		
		if(typeof UIkit != "undefined") {
			if(!allowMarkup) message = ProcessWire.entities1(message);
			var options = {};
			var labels = ProcessWire.config.AdminThemeUikit.labels;
			if(typeof settings.labelOk != 'undefined' && settings.labelOk.length) {
				options.i18n = { ok: settings.labelOk };
			} else if(typeof labels != 'undefined') {
				options.i18n = { ok: labels['ok'] };
			}
			var alert = UIkit.modal.alert(message, options);
			if(typeof func != 'undefined') alert.then(func);
			if(typeof expire !== 'undefined' && expire > 0) {
				setTimeout(function() {
					$(alert.dialog.$el).find('.uk-modal-close').trigger('click');
				}, expire);
			}
			
		} else if(typeof vex != "undefined") {
			if(allowMarkup) {
				vex.dialog.alert({unsafeMessage: message});
			} else {
				if(message.indexOf('&') > -1 && message.indexOf(';') > 1) {
					// remove entitity encoded sequences since Vex already encodes
					var v = document.createElement('textarea');
					v.innerHTML = message;
					message = v.value;
				}
				vex.dialog.alert(message);
			}
			if(typeof expire !== 'undefined') {
				setTimeout(function() {
					$('.vex-dialog-button-primary').trigger('click');
				}, expire); 
			}
			
		} else {
			alert(message);
		}
	};
	
	/**
	 * Show dialog box prompting user to enter a text value
	 * 
	 * ~~~~~
	 * // simple example
	 * ProcessWire.prompt('Enter your name', 'First and last name', function(value) {
	 *   ProcessWire.alert('You entered: ' + value); 
	 * }); 
	 * 
	 * // verbose example (3.0.248+) all optional except message and func
	 * ProcessWire.prompt({
	 *   message: '<h2>Enter your name</h2>', 
	 *   allowMarkup: true, 
	 *   placeholder: 'First and last name', 
	 *   func: function(value) { ProcessWire.alert('You entered: ' + value); }, 
	 *   labelOk: 'Finished', // Uikit admin only
	 *   labelCancel: 'Skip for now' // Uikit admin only
	 * }); 
	 * ~~~~~
	 * 
	 * @param string message Message to display
	 * @param string placeholder Placeholder or default value text to show in text input
	 * @param callable func Function to call after user clicks "Ok"
	 * @param bool allowMarkup Allow markup in message? (default=false)
	 * 
	 */
	ProcessWire.prompt = function(message, placeholder, func, allowMarkup) {
	
		var settings = {};
		if(typeof message === 'object') {
			settings = message;
			if(typeof settings.placeholder != 'undefined') placeholder = settings.placeholder;
			if(typeof settings.func != 'undefined') func = settings.func;
			if(typeof settings.allowMarkup != 'undefined') allowMarkup = settings.allowMarkup;
			message = settings.message;
		}
		
		if(typeof allowMarkup === 'undefined') allowMarkup = false;
		if(typeof placeholder === 'undefined') placeholder = '';
		
		if(typeof UIkit != 'undefined') {
			if(!allowMarkup) message = ProcessWire.entities1(message);
			var labels = ProcessWire.config.AdminThemeUikit.labels;
			var options = { i18n: {} };
			if(typeof labels != 'undefined') {
				options.i18n = { ok: labels['ok'], cancel: labels['cancel'] };
			}
			if(typeof settings.labelOk != 'undefined' && settings.labelOk.length) {
				options.i18n['ok'] = settings.labelOk;
			}
			if(typeof settings.labelCancel != 'undefined' && settings.labelCancel.length) {
				options.i18n['cancel'] = settings.labelCancel;
			}
			var prompt = UIkit.modal.prompt(message, placeholder, options);
			prompt.then(function(value) {
				if(value !== null) func(value);
			}); 
			return prompt;
			
		} else if(typeof vex == "undefined") {
			alert("prompt function requires UIkit or vex");
			return;
			
		} else {
			return vex.dialog.prompt({
				message: message,
				placeholder: placeholder,
				callback: func
			})
		}
	};
	
	/**
	 * Entity encode given text
	 * 
	 * @param str
	 * @returns {string}
	 * 
	 */
	ProcessWire.entities = function(str) {
		return $('<textarea />').text(str).html();
	};
	
	/**
	 * Entity encode given text without double entity encoding anything
	 * 
	 * @param str
	 * @returns {string}
	 * @since 3.0.248
	 * 
	 */
	ProcessWire.entities1 = function(str) {
		return ProcessWire.entities(ProcessWire.unentities(str));
	};
	
	/**
	 * Decode entities in given string
	 * 
	 * @param sring str
	 * @returns {string}
	 * @since 3.0.248
	 * 
	 */
	ProcessWire.unentities = function(str) {
		return $('<div>').html(str).text();
	}; 
	
	/**
	 * Trim any type of given value and return a trimmed string
	 * 
	 * @param str
	 * @returns {string}
	 * @since 3.0.216
	 * 
	 */
	ProcessWire.trim = function(str) {
		if(typeof str !== 'string') {
			if(typeof str === 'undefined' || str === null || str === '') return '';
			str = str.toString();
		}
		return str.trim();
	}; 
}
