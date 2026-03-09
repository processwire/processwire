/**
 * ProcessWire Panels
 * 
 * Alternative to modal windows. Creates iframe panels that load URLs. 
 * Clicking outside the panel closes it. By default, panels load the requested 
 * URL on mouseover of the a.pw-panel toggle link, unless the pw-panel-reload 
 * class is specified (in which case it loads on click).
 * 
 * Copyright 2016 by Ryan Cramer
 * License: MPL 2.0
 * 
 * REQUIREMENTS
 * ============
 * Include the panel.js and panel.css files in the request. 
 * jQuery is required. jQuery UI is strongly recommended but not required.
 * So far tested only in PW admin themes, but should work outside them too.
 * 
 * BASIC USAGE
 * ===========
 * Give any link a "pw-panel" class and it will open in a left-side panel:
 * 
 *   <a class='pw-panel' href='/path/'>Click to open panel</a>
 * 
 * If clickable element is not an <a> tag, give "data-href" attr containing the URL the panel should open: 
 * 
 *   <span class='pw-panel' data-href='/path/'>Click to open panel</span>
 * 
 * To make a panel open on the right rather than the left, append "pw-panel-right" to the class attribute. 
 * 
 *   <a class='pw-panel pw-panel-right' ...
 * 
 * To specify that the panel should reload the page every time it is opened, append "pw-panel-reload" class.
 * Note that this also makes the panel load the URL on "click" of the a.pw-panel link, rather than "mouseover".
 * 
 *   <a class='pw-panel pw-panel-reload' ...
 * 
 * To specify the target width of the panel use the "data-panel-width" attribute (default width is 50%): 
 * 
 *   <a class='pw-panel' data-panel-width='60%' ... (you may also specify value in "px" if preferred)
 *   
 *     
 * TABBED USAGE
 * ============
 * To specify the text that should appear in the tab connected to the panel provide "data-tab-text" attribute:
 * 
 *   <a class='pw-panel' data-tab-text='Panel Title' ... 
 *     
 * To specify an icon, use "data-tab-icon" attribute with font-awesome icon name (excluding the "fa-" part):
 * 
 *   <a class='pw-panel' data-tab-icon='sitemap' ... 
 * 
 * To specify the offset of the tab from top or bottom, specify "data-tab-offset" attribute. Positive integer 
 * indicates offset from top, whereas negative integer indicates offset from bottom. 
 * 
 *   <a class='pw-panel' data-tab-offset='200' ... 
 * 
 * To specify that a tab should appear even when the panel is closed (on left or right side of screen),
 * append the "pw-panel-tab" class. This serves as an alternative to the toggle link. 
 * 
 *   <a class='pw-panel pw-panel-tab' ...
 *   
 * 
 * REFERENCE
 * =========
 * 
 * CSS Classes for panel toggle element: 
 *   pw-panel: Required for anything that triggers a panel to open.
 *   pw-panel-right: Makes panel open on right side rather than left.
 *   pw-panel-reload: Makes panel reload URL it shows every time panel is opened. 
 *   pw-panel-tab: Specify that a tab should appear for a closed panel (on left or right side of screen).
 *   pw-panel-links: Specify that links in panel should open in panel rather than parent window. 
 *     
 * Data Attributes for panel toggle element:
 *   data-panel-id: ID of element to serve as the panel content (if not using a URL). 
 *   data-panel-width: Width of panel in "px" or "%", i.e. "500px" or "50%" (default=55%).
 *   data-tab-text: Text to appear in the panel's tab.
 *   data-tab-icon: Icon name to display in panel's tab (excluding the "fa-" part).
 *   data-tab-offset: Integer tab offset in px from top of bottom of screen (negative number for bottom offset). 
 *   data-href: Use to specify panel URL when element is something other than <a>.
 *   
 *     
 * 
 */
var pwPanels = {

	/**
	 * Quantity of panels initialized
	 * 
	 */
	qty: 0,
	
	/**
	 * Initialize pwPanels, to be called at document.ready
	 *
	 */
	init: function() {
		
		var url = window.location.href;
		
		if(url.indexOf('pw_panel=1') > -1) {
			// initialize inside of a panel
			$(document).on('mouseover', 'a', function() {
				// make links target the parent window
				var $a = $(this);
				var target = $a.attr('target');
				if(typeof target == "undefined" || target.length == 0) {
					$a.attr('target', '_parent');
				}
			});
			
		} else if(url.indexOf('pw_panel=2') > -1) {
			// don't initialize anything (.pw-panel-links class option)
			
		} else {
			// initialize a page with panels in it
			$('.pw-panel').each(function() {
				var $toggler = $(this);
				pwPanels.addPanel($toggler);
			});
		}
	},
	
	/**
	 * Add a new panel 
	 * 
	 * @param toggler Element that toggles the panel
	 * 
	 */
	addPanel: function($toggler) {
		
		var panelURL = $toggler.attr('data-href');
		var panelID = $toggler.attr('data-panel-id');
		var panelContainerID = 'pw-panel-container-' + (++pwPanels.qty);
		
		// allow for use of data-href or href attribute that references URL to load in panel
		if(typeof panelURL == 'undefined' || !panelURL.length) panelURL = $toggler.attr('href');

		if(typeof panelURL != 'undefined' && panelURL.length) {
			var hash = '';
			if(panelURL.indexOf('#') > -1) {
				var parts = panelURL.split('#');
				panelURL = parts[0];
				hash = '#' + parts[1];
			}
			panelURL += (panelURL.indexOf('?') > -1 ? '&' : '?') + 'modal=panel&pw_panel=';

			if($toggler !== null && $toggler.hasClass('pw-panel-links')) {
				panelURL += '2'; // don't update target of links in panel
			} else {
				panelURL += '1'; // update target of links in panel
			}
			panelURL += hash;
		}
		
		var $icon = $('<i />')
			.attr('class', 'pw-panel-icon fa fa-angle-double-left');
		
		var $span = $('<small />').attr('class', 'ui-button-text')
			.append($icon);
		
		var $btn = $('<a />')
			.attr('class', 'pw-panel-button pw-panel-button-closed ui-button ui-state-default')
			.attr('href', panelURL)
			.on('click', pwPanels.buttonClickEvent)
			.on('mouseover', pwPanels.buttonMouseoverEvent)
			.on('mouseout', pwPanels.buttonMouseoutEvent)
			.append($span);
		
		var $panel = $('<div />')
			.attr('id', panelContainerID)
			.attr('class', 'pw-panel-container pw-panel-container-closed')
			.append($btn);
		
		$('body').append($panel);
		
		if(typeof panelID != 'undefined' && panelID.length) {
			// loading an in-page element rather than a URL
			$('#' + panelID).hide().addClass('pw-panel-element'); // class assigned to in-page element
			$panel.addClass('pw-panel-container-element').attr('data-panel-id', panelID);
		}

		// panel toggler
		if($toggler !== null) {
			pwPanels.initToggler($toggler, $btn, $panel);
		} else {
			$panel.addClass('pw-panel-left');
		}
	},

	/**
	 * Initialize the toggler element, plus $btn and $panel with options from toggler element
	 * 
	 * @param $toggler
	 * @param $btn
	 * @param $panel
	 * 
	 */
	initToggler: function($toggler, $btn, $panel) {

		var panelSide = $toggler.hasClass('pw-panel-right') ? 'right' : 'left';
		var text = $toggler.attr('data-tab-text');
		var icon = $toggler.attr('data-tab-icon');
		var offset = $toggler.attr('data-tab-offset');
		var panelWidth = $toggler.attr('data-panel-width');
		var btnPos = panelSide == 'right' ? 'left' : 'right';
		var btnExtraPx = 1;

		$panel.addClass('pw-panel-tab pw-panel-' + panelSide);
		$panel.attr('data-href', $btn.attr('href'));
		if($toggler.hasClass('pw-panel-reload')) $panel.addClass('pw-panel-reload');

		if(typeof offset != "undefined") {
			offset = parseInt(offset);
			if(offset > -1) {
				// positive number indicates offset from top
				$btn.css('top', offset + 'px');
			} else {
				// negative number indicates offset from bottom
				$btn.css('top', 'auto');
				$btn.css('bottom', Math.abs(offset) + 'px');
			}
		}

		if(typeof text != "undefined" && text.length) {
			var $btnText = $btn.children('.ui-button-text');
			var $text = $("<span />").text(text);
			$btnText.html('<span>' + $text.text() + '</span>');
			$btn.addClass('pw-panel-button-text');
			btnExtraPx = 7;
			//$btn.css(btnPos, (-1 * ($btn.height() + 7)) + 'px');
			//$btn.css('top', parseInt($btn.css('top')) + ($btn.width()) + 'px');
		}
	
		if(typeof icon != "undefined" && icon.length) {
			var $icon = $('<i />').addClass('fa fa-fw fa-' + icon);
			var $text = $btn.children('.ui-button-text');
			if($btn.hasClass('pw-panel-button-text')) {
				$text.prepend($icon);
			} else {
				$text.empty().append($icon);
				$btn.css(btnPos, (-1 * ($btn.outerWidth())) + 'px');
			}
		}
		
		if(typeof panelWidth != 'undefined' && panelWidth.length) {
			$panel.css('width', panelWidth)
			$panel.css(panelSide, '-' + panelWidth);
		}
		
		if(panelSide == 'right') {
			// align button to right edge
			//$btn.css('left', (-1 * (btnExtraPx + $btn.height())) + 'px');
		} else {
			// align button to left edge
			$btn.css('right', (-1 * (btnExtraPx + $btn.height())) + 'px');
		}

		if(!$toggler.hasClass('pw-panel-tab')) {
			// if toggler doesn't specify that a tab/button should show, hide our $btn element
			$btn.addClass('pw-panel-button-hidden');
		}

		// delegate events from toggler to pw-panel-button
		$toggler.on('click', function() {
			$btn.trigger('click');
			return false;
		}).on('mouseover', function() {
			$btn.trigger('mouseover');
		}).on('mouseout', function() {
			$btn.trigger('mouseout');
		});
	},

	/**
	 * Populate the panel content (iframe if using URL) and return it
	 * 
	 */
	initPanelContent: function($panel) {
		
		var $content = $panel.find('.pw-panel-content');
		var panelID = $panel.attr('data-panel-id');
		
		if($content.length) {
			return $content;
		} else if(typeof panelID != "undefined") {
			var $panelTarget = $('#' + panelID);
			// var $btn = $panel.find('.pw-panel-button').addClass(panelID + '-pw-panel-button'); // if needed for external trigger
			if($panelTarget.length) {
				$content = $('<div />').addClass('pw-panel-content').css('overflow', 'auto');
				$panel.append($content);
				$content.append($panelTarget);
				$panelTarget.show();
				$panelTarget.trigger('pw-panel-init');
			}
		} else {
			$content = $('<iframe />')
				.addClass('pw-panel-content')
				.attr('src', $panel.attr('data-href'));
			$panel.append($content);
		}
		
		return $content;
	}, 

	/**
	 * Event called on window resize
	 * 
	 */
	windowResizeEvent: function() {
		$(".pw-panel-container-init").each(function() {
			var $panel = $(this);
			if($panel.hasClass('pw-panel-container-open')) return;
			var panelWidth = $panel.width();
			var px = (-1 * panelWidth) + 'px';
			if($panel.hasClass('pw-panel-right')) {
				$panel.css('right', px);
			} else {
				$panel.css('left', px);
			}
		});
	},

	/**
	 * Event called on panel button click 
	 * 
	 * @returns {boolean}
	 * 
	 */
	buttonClickEvent: function() {

		var $btn = $(this);
		var $panel = $btn.closest('.pw-panel-container');
		var $panelContent = $panel.find('.pw-panel-content');
		var isOpen = $panel.hasClass('pw-panel-container-open');
		var isLoaded = $panel.hasClass('pw-panel-container-loaded');
		var panelWidth = $panel.width();
		var panelSide = $panel.hasClass('pw-panel-right') ? 'right' : 'left';
		var hasJQUI = typeof jQuery.ui != "undefined";

		function animateFinished() {
			$panel.toggleClass('pw-panel-container-open pw-panel-container-closed');
			$btn.toggleClass('pw-panel-button-open pw-panel-button-closed');
		}
	
		if($('.pw-panel-container-init').length == 0) {
			// attach window resize event only if no panels have been opened before
			// so that we attach it only if needed, and not more than once
			$(window).on('resize', pwPanels.windowResizeEvent);
		}

		if(isOpen) {

			// close the panel
			
			var px = (-1 * panelWidth) + 'px';
			
			if(hasJQUI) {
				if(panelSide == 'left') {
					$panel.animate({left: px}, 150, animateFinished);
				} else {
					$panel.animate({right: px}, 150, animateFinished);
				}
			} else {
				$panel.css(panelSide, px);
				animateFinished();
			}

			$('body').css('overflow', '');
			
			$("#pw-panel-shade").fadeOut('fast', function() {
				$(this).remove();
			});
			
			$btn.fadeOut('fast', function() {
				$btn.removeClass('ui-state-active');
				$btn.fadeIn('fast');
			});
			
			if(hasJQUI && panelSide == 'left') $panel.resizable('destroy');
			
			if($panel.hasClass('pw-panel-reload')) {
				// force it to create new iframe on every load
				$panel.find('iframe.pw-panel-content').remove();
			}
			
			// trigger panel-closed event
			$(document).trigger('pw-panel-closed', $panel);

		} else {

			// open the panel
			
			if($panel.hasClass('pw-panel-reload') || !isLoaded) {
				// tell the panel to load or reload, since mouseover even didn't
				pwPanels.initPanelContent($panel);
			}
			
			if(hasJQUI) {
				if(panelSide == 'left') {
					$panel.animate({left: 0}, 150, animateFinished);
				} else {
					$panel.animate({right: 0}, 150, animateFinished);
				}
			} else {
				$panel.css(panelSide, 0);
				animateFinished();
			}

			// shade the parent window
			var $shade = $("<div id='pw-panel-shade'>&nbsp;</div>");
			$panel.before($shade).fadeIn('fast');

			// prevent scrolling in parent window
			$('body').css('overflow', 'hidden');

			// if shade is clicked, exit the panel
			$shade.on('click', function() {
				var $panel = $('.pw-panel-container-open');
				if(!$panel.length) return false;
				$panel.find('.pw-panel-button').trigger('click');
			});

			// enable panel to be resized
			// note: resizing only works with left aligned panel
			if(hasJQUI && panelSide == 'left') $panel.resizable({
				handles: 'e',
				start: function(event, ui) {
					// the overlay helps prevent the resizable from losing "grip"
					var $overlay = $('<div />').addClass('pw-panel-resizable-overlay').css({
						position: 'absolute',
						top: 0,
						left: 0,
						width: '100%',
						height: '100%',
						zIndex: 1001, 
						display: 'hidden'
					});
					$panel.append($overlay);
				}, 
				stop: function(event, ui) {
					// pwPanels.lastTabWidth = ui.size.width;
					$('.pw-panel-resizable-overlay').remove();
				}
			});
		
			// indicate that this panel has been opened and is initialized
			$panel.addClass('pw-panel-container-init');
			
			// trigger panel-opened event
			$(document).trigger('pw-panel-opened', $panel);
		}

		return false;
	},

	/**
	 * Event called on panel button mouseover
	 * 
	 */
	buttonMouseoverEvent: function() {

		var $btn = $(this);
		var $panel = $btn.closest('.pw-panel-container');

		$btn.removeClass('ui-state-active').addClass('ui-state-hover');
		
		if($panel.hasClass('pw-panel-container-loaded')) return;
		$panel.addClass('pw-panel-container-loaded');
		
		if(!$panel.hasClass('pw-panel-reload')) {
			// make panel content load on mouseover 
			pwPanels.initPanelContent($panel);
		}
	},

	/**
	 * Event called on panel button mouseout
	 * 
	 */
	buttonMouseoutEvent: function() {

		var $btn = $(this);
		var $panel = $btn.closest('.pw-panel-container');

		if($panel.hasClass('pw-panel-container-open')) $btn.addClass('ui-state-active');
	}

}

jQuery(document).ready(function() {
	pwPanels.init();
});
