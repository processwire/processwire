/**
 * ProcessWire Admin common javascript
 *
 * Copyright 2016 by Ryan Cramer
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
		$(".pw-notice-group-toggle").click(function() {
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
		}).hover(function() {
			var $a = $(this);
			if($a.is('a')) {
				$a.addClass('ui-state-hover');
			} else {
				$a.data('pw-tooltip-cursor', $a.css('cursor'));
				$a.css('cursor', 'pointer');	
			}
			$a.addClass('pw-tooltip-hover');
			$a.css('cursor', 'pointer');
		}, function() {
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
		}).on('click', 'a > button', function() {
			var $a = $(this).parent();
			var target = $a.attr('target');
			if(typeof target != "undefined" && target == '_blank') {
				// skip
			} else {
				// make buttons with <a> tags click to the href of the <a>
				window.location = $a.attr('href');
			}
		});
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
					$a.click(function() {
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
			$ul.find('a').click(function() {
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
						if($ul.filter(":hover").length || $a.filter(":hover").length) {
							return;
						}
						$ul.fadeOut('fast');
						$a.removeClass('hover pw-dropdown-toggle-open');
					}, 1000);
				}
				$ul.mouseleave(mouseleaver);
				$a.mouseleave(mouseleaver);
			} else {
				$ul.mouseleave(function() {
					//if($a.is(":hover")) return;
					//if($a.filter(":hover").length) return;
					$ul.hide();
					$a.removeClass('hover');
				});
			}
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
						$ul.mouseleave();
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
				if($ul.filter(":hover").length) return;
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
						$(document).on('hover', 'ul.pw-dropdown-menu a', function() {
							hoverDropdownAjaxItem($(this));
						});
					}

					if(data.add) {
						var $li = $(
							"<li class='ui-menu-item add'>" +
							"<a href='" + data.url + data.add.url + "'>" +
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
					$item.mouseleave();
				}
			} else {
				var datafrom = $item.attr('data-from');	
				if(typeof datafrom == "undefined") var datafrom = '';
				if(datafrom.indexOf('topnav') > -1) {
					var from = datafrom.replace('topnav-', '') + '-';
					$("a.pw-dropdown-toggle.hover:not('." + from + "')").attr('data-touchCnt', 0).mouseleave();
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
	 * if(ProcessWire.confirm('Send this message now?', function() {
	 *   // user clicked Ok
	 * }, function() {
	 *   // user clicked Cancel
	 * }); 
	 * ~~~~~
	 * 
	 * @param message Message to display (or question to ask)
	 * @param funcOk Callback called on "Ok"
	 * @param funcCancel Callback called on "Cancel" (optional)
	 * 
	 */
	ProcessWire.confirm = function(message, funcOk, funcCancel) {
		if(typeof vex != "undefined" && typeof funcOk != "undefined") {
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

	ProcessWire.alert = function(message, allowMarkup) {
		if(typeof allowMarkup == "undefined") var allowMarkup = false;
		if(typeof vex != "undefined") {
			if(allowMarkup) {
				vex.dialog.alert({unsafeMessage: message});
			} else {
				vex.dialog.alert(message);
			}
		} else {
			alert(message);
		}
	};

	ProcessWire.prompt = function(message, placeholder, func) {
		if(typeof vex == "undefined") {
			alert("prompt function requires vex");
			return;
		}
		return vex.dialog.prompt({
			message: message,
			placeholder: placeholder,
			callback: func
		})
	};

	ProcessWire.entities = function(str) {
		return $('<textarea />').text(str).html();
	};
}
