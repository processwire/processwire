/**
 * Provides a basic modal window capability
 * 
 * Use this directly (as described below) or copy and paste into your Process module's
 * javascript file and modify as needed. 
 * 
 * USE 
 * ===
 * 1. In your module call $this->modules->get('JqueryUI')->use('modal'); 
 * 2. For the <a> link or <button> you want to open a modal window, give it the class 
 *    "pw-modal". For a larger modal window, also add class "pw-modal-large". Other options
 *    are "pw-modal-small" and "pw-modal-full". Default is "pw-modal-medium".
 *    If using a <button> the "href" attribute will be pulled from a containing <a> element. 
 *    If button not wrapped with an <a>, it may also be specified in a "data-href" attribute 
 *    on the button.
 *    
 *
 * ATTRIBUTES  
 * ========== 
 * Other attributes you may specify with your a.pw-modal or button.pw-modal:
 * 
 * data-buttons: If you want to use modal window buttons, specify a jQuery selector 
 * that identifies these buttons in the modal window. The original buttons will be 
 * hidden in the modal content and moved outside to the modal window interface.
 * 
 * data-autoclose: If also using data-buttons, this option will make clicking any 
 * of those buttons automatically close the modal window, after the next page loads. 
 * This enables save actions to take place. You may also populate this property with
 * a jQuery selector, in which case autoclose will only take place if the button 
 * matches the given selector. 
 * 
 * data-close: Populate with a selector that matches the button (or buttons) that 
 * should immediately close the window (no follow-up save or anything). 
 * 
 * MODAL CONTENT
 * =============
 * The window opened in the modal may optionally create a button or link with the 
 * class 'pw-modal-cancel'. When clicked, the window will immediately close. This 
 * can also be one of your 'data-buttons' if you want it to. 
 * 
 * EVENTS
 * ======
 * When the window is closed, the a.pw-modal link receives a "pw-modal-closed" event
 * with the arguments (event, ui).
 * 
 * 
 * 
 */

var pwModalWindows = [];

/**
 * Dialog function that returns settings ready for population to jQuery.dialog
 * 
 * Settings include:
 * 	- position
 * 	- width
 * 	- height
 * 
 * @param name Name of modal settings to use: 'full', 'large', 'medium' or 'small'
 * @returns {{position: *[], width: number, height: number}}
 * 
 */
function pwModalWindowSettings(name) {
	
	var modal = ProcessWire.config.modals[name];
	if(typeof modal == "undefined") modal = ProcessWire.config.modals['medium'];
	modal = modal.split(','); 

	// options that can be customized via config.modals, with default values
	var options = {
		modal: true,
		draggable: false,
		resizable: true,
		hide: 250,
		show: 100, 
		hideOverflow: true,
		closeOnEscape: false
	}

	if(modal.length >= 4) {
		for(var n = 4; n < modal.length; n++) {
			var val = modal[n];
			if (val.indexOf('=') < 1) continue;
			val = val.split('=');
			var key = val[0].toString().trim();
			val = val[1].toLowerCase().trim();
			if (typeof options[key] == "undefined") continue;
			if (val == "true" || val == "1") {
				val = true;
			} else if (val == "false" || val == "0") {
				val = false;
			} else {
				val = parseInt(val);
			}
			options[key] = val;
		}
	}

	if(jQuery.ui.version === '1.10.4') {
		var position = [ parseInt(modal[0]), parseInt(modal[1]) ];
	} else {
		var position = { my: "left+" + modal[0] + " top+" + modal[1], at: "left top" };
	}
	
	var width = jQuery(window).width() - parseInt(modal[2]);
	var height = jQuery(window).height() - parseInt(modal[3]);
	
	var settings = {
		modal: options.modal,
		draggable: options.draggable,
		resizable: options.resizable,
		position: position, 
		width: width, 
		height: height,
		hide: options.hide,
		show: options.show, 
		closeOnEscape: options.closeOnEscape,
		closeText: '',
		create: function(event, ui) {
			if(options.hideOverflow) {
				if(typeof parent.jQuery != "undefined") {
					parent.jQuery('body').css('overflow', 'hidden');
				} else {
					parent.document.querySelector('body').style.overflow = 'hidden';
				}
			}
			// replace the jQuery ui close icon with a font-awesome equivalent (for hipdi support)
			var $widget = jQuery(this).dialog("widget");
			jQuery(".ui-dialog-titlebar-close", $widget)
				.css('padding-top', 0)
				.addClass('ui-state-default')
				.prepend("<i class='fa fa-times'></i>")
				.find('.ui-icon').remove();
			if(frameElement) {
				// dialog on top of dialog
				if(typeof parent.jQuery !== 'undefined') {
					// jQuery available in parent
					if(parent.jQuery('.ui-dialog').length) {
						parent.jQuery('.ui-dialog .ui-button').addClass('pw-modal-hidden').hide();
						parent.jQuery('.ui-dialog-buttonpane').css('margin-top', '-10px');
						jQuery('body').css('overflow', 'hidden');
					}
				} else {
					// jQuery NOT available in parent
					if(parent.document.querySelector('.ui-dialog')) {
						var parentButtons = parent.document.querySelectorAll('.ui-dialog .ui-button');
						var i;
						for(i = 0; i < parentButtons.length; i++) {
							parentButtons[i].classList.add('pw-modal-hidden');
							parentButtons[i].style.display = 'none';
						}
						var parentPanes = parent.document.querySelectorAll('.ui-dialog-buttonpane');
						for(i = 0; i < parentPanes.length; i++) {
							parentPanes[i].style.marginTop = '-10px';
						}
						document.querySelector('body').style.overflow = 'hidden';
					}
				}
			}
		},
		beforeClose: function(event, ui) {
			if(typeof parent.jQuery != 'undefined') { 
				if(parent.jQuery('.ui-dialog').length) {
					if(frameElement) {
						// dialog on top of another dialog
						parent.jQuery('.pw-modal-hidden').show();
						jQuery('body').css('overflow', '');
					} else if(options.hideOverflow) {
						parent.jQuery('body').css('overflow', '');
					}
				}
			} else {
				// no jQuery available
				if(frameElement) {
					// dialog on top of another dialog
					var parentModalHidden = parent.document.querySelector('.pw-modal-hidden');
					if(parentModalHidden) parentModalHidden.style.display = 'block';
					document.querySelector('body').style.overflow = '';
				} else if(options.hideOverflow) {
					parent.document.querySelector('body').style.overflow = '';
				}
			}
		}
	}
	
	return settings;
};

/**
 * Open a modal window and return the iframe/dialog object
 * 
 * @param href URL to open
 * @param options Settings to provide to jQuery UI dialog (if additional or overrides)
 * @param size Specify one of: full, large, medium, small (default=medium)
 * @returns jQuery $iframe
 * 
 */
function pwModalWindow(href, options, size) {
	
	var $iframe, url;
	
	// destory any existing pw-modals that aren't currently open
	for(var n = 0; n <= pwModalWindows.length; n++) {
		$iframe = pwModalWindows[n]; 	
		if($iframe == null) continue; 
		if($iframe.dialog('isOpen')) continue;
		$iframe.dialog('destroy').remove();
		pwModalWindows[n] = null;
	}

	if(href.indexOf('modal=') > 0) {
		url = href; 
	} else {
		url = href + (href.indexOf('?') > -1 ? '&' : '?') + 'modal=1';
	}
	if(url.indexOf('%3F')) url = url.replace('%3F', '?');
	$iframe = jQuery('<iframe class="pw-modal-window" frameborder="0" src="' + url + '"></iframe>');
	$iframe.attr('id', 'pw-modal-window-' + (pwModalWindows.length+1));
	pwModalWindows[pwModalWindows.length] = $iframe;
	
	if(typeof size == "undefined" || size.length == 0) size = 'large';
	var settings = pwModalWindowSettings(size);
	
	if(settings == null) {
		alert("Unknown modal setting: " + size);
		return $iframe;
	}
	
	if(typeof options != "undefined") jQuery.extend(settings, options);
	
	$iframe.on('dialogopen', function(event, ui) {
		jQuery(document).trigger('pw-modal-opened', { event: event, ui: ui });
	});
	$iframe.on('dialogclose', function(event, ui) {
		jQuery(document).trigger('pw-modal-closed', { event: event, ui: ui });
	});
	
	$iframe.dialog(settings);
	$iframe.data('settings', settings);
	$iframe.on('load', function() {
		if(typeof settings.title == "undefined" || !settings.title) {
			var title = jQuery('<textarea />').text($iframe.contents().find('title').text()).html();
			$iframe.dialog('option', 'title', title); 
		}
		$iframe.contents().find('form').css('-webkit-backface-visibility', 'hidden'); // to prevent jumping
	}); 
	
	var lastWidth = 0;
	var lastHeight = 0;
	
	function updateWindowSize() {
		var width = jQuery(window).width();
		var height = jQuery(window).height();
		if((width == lastWidth && height == lastHeight) || !$iframe.hasClass('ui-dialog-content')) return;
		var _size = size;
		if(width <= 960 && size != 'full' && size != 'large') _size = 'large';
		if(width <= 700 && size != 'full') _size = 'full';
		var _settings = pwModalWindowSettings(_size);
		var $dialog = $iframe.closest('.ui-dialog');
		if($dialog.length > 0) {
			var $buttonPane = $dialog.find('.ui-dialog-buttonpane');
			var $titleBar = $dialog.find('.ui-dialog-titlebar');
			var subtractHeight = 0;
			if($buttonPane.length) subtractHeight += $buttonPane.outerHeight();
			if($titleBar.length) subtractHeight += $titleBar.outerHeight();
			if(subtractHeight) _settings.height -= subtractHeight;
		}
		$iframe.dialog('option', 'width', _settings.width);
		$iframe.dialog('option', 'height', _settings.height);
		$iframe.dialog('option', 'position', _settings.position);
		$iframe.width(_settings.width).height(_settings.height);
		lastWidth = width;
		lastHeight = height; 
	}
	updateWindowSize();
	
	jQuery(window).on('resize', updateWindowSize);
	
	$iframe.refresh = function() {
		lastWidth = 0; // force update
		lastHeight = 0;
		updateWindowSize();
	};
	
	$iframe.setButtons = function(buttons) {
		$iframe.dialog('option', 'buttons', buttons);
		$iframe.refresh();
	};
	$iframe.setTitle = function(title) {
		$iframe.dialog('option', 'title', jQuery('<textarea />').text(title).html()); 
	}; 

	return $iframe; 
}

/**
 * Event handler for when an action opens a pw modal window 
 * 
 * @param e
 * @returns {boolean}
 * 
 */
function pwModalOpenEvent(e) {
	
	var $a = jQuery(this);
	var _autoclose = $a.attr('data-autoclose');
	var autoclose = _autoclose != null; // whether autoclose is enabled
	var autocloseSelector = autoclose && _autoclose.length > 1 ? _autoclose : ''; // autoclose only enabled if clicked button matches this selector
	var closeSelector = $a.attr('data-close'); // immediately close window (no closeOnLoad) for buttons/links matching this selector
	var closeOnLoad = false;
	var modalSize = 'medium';

	if($a.hasClass('pw-modal-large')) modalSize = 'large';
		else if($a.hasClass('pw-modal-small')) modalSize = 'small';
		else if($a.hasClass('pw-modal-full')) modalSize = 'full';

	var settings = {
		title: $a.attr('title'),
		close: function(e, ui) {
			// abort is true when the "x" button at top right of window is what closed the window
			var abort = typeof e.originalEvent != "undefined" && jQuery(e.originalEvent.target).closest('.ui-dialog-titlebar-close').length > 0;
			var eventData = { 
				event: e, 
				ui: ui, 
				abort: abort 
			};
			$a.trigger('modal-close', eventData); // legacy, deprecated
			$a.trigger('pw-modal-closed', eventData); // new
			jQuery(document).trigger('pw-modal-closed', eventData);
			$spinner.remove();
			// console.log(eventData);
		}
	};

	// attribute holding selector that determines what buttons to show, example: "#content form button.ui-button[type=submit]"
	var buttonSelector = $a.attr('data-buttons');

	// a class of pw-modal-cancel on one of the buttons always does an immediate close
	if(closeSelector == null) closeSelector = '';
	closeSelector += (closeSelector.length > 0 ? ', ' : '') + '.pw-modal-cancel';

	var $spinner = jQuery("<i class='fa fa-spin fa-spinner fa-2x ui-priority-secondary'></i>")
		.css({
			'position': 'absolute',
			'top': (parseInt(jQuery(window).height() / 2) - 80) + 'px',
			'left': (parseInt(jQuery(window).width() / 2) - 20) + 'px',
			'z-index': 9999
		}).hide();

	// first see if there is a specific/override href available
	var href = $a.attr('data-pw-modal-href')
	
	if(href && href.length) {
		// use the data-pw-modal-href attribute
	} else if($a.is('button')) {
		var $aparent = $a.closest('a');
		href = $aparent.length ? $aparent.attr('href') : $a.attr('data-href');
		if(!href) href = $a.find('a').attr('href');
	} else if($a.is('a')) {
		href = $a.attr('href');
	} else {
		// some other element, we require a data-href attribute
		href = $a.attr('data-href');
	}
	
	if(!href) {
		alert("Unable to find href attribute for: " + $a.text());
		return false;
	}

	var $iframe = pwModalWindow(href, settings, modalSize);

	jQuery("body").append($spinner.fadeIn('fast'));
	setTimeout(function() {
		$a.removeClass('ui-state-active');
	}, 500);

	$iframe.on('load', function() {

		var buttons = [];
		var $icontents = $iframe.contents();
		var n = 0;

		$spinner.fadeOut('fast', function() { 
			$spinner.remove(); 
		});

		if(closeOnLoad) {
			// this occurs when item saved and resulting page is loaded
			var $errorItems = $icontents.find(".NoticeError, .ui-state-error");
			if($errorItems.length == 0) {
				// if there are no error messages present, close the window
				if(typeof Notifications != "undefined") {
					var messages = [];
					$icontents.find(".NoticeMessage").each(function() {
						messages[messages.length] = jQuery(this).text();
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
				// console.log('error items prevent modal autoclose:');
				// console.log($errorItems);
			}
		}

		var $body = $icontents.find('body');
		$body.hide();

		// copy buttons in iframe to dialog
		if(buttonSelector) {
			$icontents.find(buttonSelector).each(function() {
				var $button = jQuery(this);
				$button.find(".ui-button-text").removeClass("ui-button-text"); // prevent doubled
				var text = $button.html();
				var skip = false;
				// avoid duplicate buttons
				for(var i = 0; i < buttons.length; i++) {
					if(buttons[i].text == text || text.length < 1) skip = true;
				}
				if(!skip) {
					buttons[n] = {
						'html': text,
						'class': ($button.is('.ui-priority-secondary') ? 'ui-priority-secondary' : ''),
						'click': function(e) {
							// jQuery(e.currentTarget).fadeOut('fast');
							$button.trigger('click');
							if(closeSelector.length > 0 && $button.is(closeSelector)) {
								// immediately close if matches closeSelector
								$iframe.dialog('close');
							}
							if(autoclose) {
								// automatically close on next page load
								jQuery("body").append($spinner.fadeIn());
								if(autocloseSelector.length > 1) {
									closeOnLoad = $button.is(autocloseSelector); // if button matches selector
								} else {
									closeOnLoad = true; // tell it to close window on the next 'load' event
								}
							}
						}
					};
					n++;
				};
				if(!$button.hasClass('pw-modal-button-visible')) $button.hide(); // hide button that is in interface
			});
		} // .pw-modal-buttons

		// render buttons
		if(buttons.length > 0) $iframe.setButtons(buttons);

		$body.fadeIn('fast', function() {
			$body.show(); // for Firefox, which ignores the fadeIn()
		});

	}); // $iframe.load

	return false;
}

/*
 * jQuery Double Tap
 * Developer: Sergey Margaritov (sergey@margaritov.net)
 * Date: 22.10.2013
 * Based on jquery documentation http://learn.jquery.com/events/event-extensions/
 */

(function($){

	$.event.special.pwdoubletap = {
		bindType: 'touchend',
		delegateType: 'touchend',

		handle: function(event) {
			var handleObj   = event.handleObj,
				targetData  = jQuery.data(event.target),
				now         = new Date().getTime(),
				delta       = targetData.lastTouch ? now - targetData.lastTouch : 0,
				delay       = delay == null ? 300 : delay;

			if (delta < delay && delta > 30) {
				targetData.lastTouch = null;
				event.type = handleObj.origType;
				['clientX', 'clientY', 'pageX', 'pageY'].forEach(function(property) {
					event[property] = event.originalEvent.changedTouches[0][property];
				})

				// let jQuery handle the triggering of "doubletap" event handlers
				handleObj.handler.apply(this, arguments);
			} else {
				targetData.lastTouch = now;
			}
		}
	};

})(jQuery);

function pwModalDoubleClick() {
	// double click handler that still enables links within to work as single-click
	var clicks = 0, timer = null, allowClick = false;
	jQuery(document).on('click', '.pw-modal-dblclick a', function() {
		var $a = jQuery(this);
		if(allowClick) {
			allowClick = false;
			return true;
		}
		clicks++;  //count clicks
		if(clicks === 1) {
			timer = setTimeout(function() {
				clicks = 0;
				allowClick = true;
				$a[0].trigger('click');
				return true;
			}, 700);
		} else {
			clearTimeout(timer); // prevent single-click action
			allowClick = false;
			clicks = 0;
			jQuery(this).closest('.pw-modal-dblclick').trigger('dblclick');
		}
		return false;
	});
	jQuery(document).on('dblclick', '.pw-modal-dblclick a', function(e) {
		e.stopPropagation();
		return false;
	});

	var isTouch = (('ontouchstart' in window) || (navigator.MaxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0));

	if(isTouch) {
		jQuery(document).on('pwdoubletap', '.pw-modal-dblclick', pwModalOpenEvent);
	}
}

jQuery(document).ready(function($) {
	
	// enable titles with HTML in ui dialog
	$.widget("ui.dialog", $.extend({}, $.ui.dialog.prototype, {
		_title: function(title) {
			if (!this.options.title ) {
				title.html("&#160;");
			} else {
				title.html(this.options.title);
			}
		}
	}));

	$(document).on('pwdblclick', '.pw-modal-dblclick', pwModalOpenEvent);
	$(document).on('click', '.pw-modal:not(.pw-modal-dblclick):not(.pw-modal-longclick)', pwModalOpenEvent);
	$(document).on('dblclick', '.pw-modal-dblclick', pwModalOpenEvent);
	$(document).on('longclick', '.pw-modal-longclick', pwModalOpenEvent);
	
	pwModalDoubleClick();

});
