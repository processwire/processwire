/**
 * inputfields.js - JS specific to behavior of ProcessWire Inputfield forms. 
 * 
 * REQUIREMENTS
 * ============
 *  - ProcessWire or a module that uses its Inputfield Forms
 *  - One or more form.InputfieldForm elements
 *  - jQuery is used throughout 
 * 
 * PUBLIC API (3.0.144+)
 * ==========
 * Note: any 'f' below arguments may be an Inputfield $element, "#id" string, ".class" string or field name.
 * Many of the functions also support extra arguments such as a callback function, see Inputfields variable
 * definition for more details on arguments. For functions that show “…” as the arguments, see the full 
 * function definition for details. 
 * 
 * - Inputfields.open(f): Open the given Inputfield if not already open.
 * - Inputfields.close(f): Close the given Inputfield if not already closed. 
 * - Inputfields.toggle(f): Find and toggle the open/closed state of given Inputfield. 
 * - Inputfields.find(f): Find and highlight the given Inputfield (at any depth, in any tab). 
 * - Inputfields.focus(f): Find the given Inputfield and focus its input element (at any depth, in any tab).
 * - Inputfields.highlight(f): Temporarily highlight the given Inputfield with a background color. 
 * - Inputfields.reload(f): Force given Inputfield to reload via ajax (if supported).
 * - Inputfields.redraw(…): Redraw Inputfields after a visibility or width change. 
 * - Inputfields.inView(f): Is the Inputfield currently in the user’s viewable area?
 * - Inputfields.hidden(f): Does the given Inputfield currently have a hidden state?
 * - Inputfields.changed(f): Has given Inputfield changed? (or use 2nd argument to set)
 * - Inputfields.columnWidth(f): Get the column width percent of Inputfield (between 1-100) 
 * - Inputfields.columnWidth(f, w): Set the column width percent of Inputfield, where w is percent width between 1-100.
 * - Inputfields.name(f): Get the name of the given Inputfield
 * - Inputfields.label(f): Get the text label of the given Inputfield
 * - Inputfields.input(f): Get the input element(s) within the given Inputfield
 * - Inputfields.insertBefore(f, ff): Insert Inputfield 'f' before Inputfield 'ff'.
 * - Inputfields.insertAfter(f, ff): Insert Inputfield 'f' after Inputfield 'ff'.
 * - Inputfields.startSpinner(f): Start spinner for Inputfield.
 * - Inputfields.stopSpinner(f): Stop spinner for Inputfield. 
 * - Inputfields.init(target): Manually initialize all .Inputfield elements within target.
 *   Calling init() is only necessary for Inputfields not present during page load. 
 * - This file also contains lots of other functions, but they are not part of the public API. 
 * 
 * CLASSES
 * =======
 * Form classes:
 * - InputfieldForm: Class assigned to all Inputfield forms.
 * - InputfieldFormConfirm: Form that will warn the user if they attempt to navigate away before submit.
 * - InputfieldFormSubmitted: Class assigned to form when the submit button has been pressed. 
 * - InputfieldFormFocusFirst: Class assigned to form that should focus the first field. 
 * - InputfieldFormNoDependencies: Form with dependencies disabled.
 * - InputfieldFormNoWidths: Form with column widths disabled (or handled elsewhere).
 * - InputfieldFormNoHeights: Form where matching of column heights is disabled (or handled elsewhere).
 * - InputfieldFormNoFocus: Form where automatic focus of first field is specificallyl disabled. 
 * - nosubmit: Internal class assigned when submit of form is temporarily disabled due to other events.
 *     
 * Form field (Inputfield) classes:    
 * - Inputfield: Assigned to all Inputfield elements.
 * - Inputfield[Type]: Class where [Type] is replaced by Inputfield type, i.e. "Radios", "Select", etc.
 * - InputfieldWrapper: Assigned to Inputfields that can contain other Inputfield elements. 
 * - InputfieldStateHidden: Item that is current in a hidden state (not visible to user).
 * - InputfieldStateRequired: Item that requires a value in at least one of its inputs. 
 * - InputfieldStateChanged: Item that has changed. 
 * - InputfieldStateShowIf: Item that contains a show-if dependency via a 'data-show-if' attribute.
 * - InputfieldStateRequiredIf: Item that contains a required-if dependency via a 'data-required-if' attribute.
 * - InputfieldStateCollapsed: Item that is collapsed and must be clicked to be opened. 
 * - InputfieldStateWasCollapsed: Item that was previously collapsed. 
 * - InputfieldStateToggling: Item that is currently between an open and collapsed state. 
 * - InputfieldColumnWidth: Item that has some column width smaller than 100%. 
 * - InputfieldColumnWidthFirst: Item that has column width < 100% and is the first in a row of columns.
 * - InputfieldColumnWidthFirstTmp: Temporary/runtime class for column width used internally.
 * - InputfieldNoFocus: Item that should not have its input element automatically focused. 
 * - InputfieldAjaxLoading: Item that is currently loading from an ajax request. 
 * - InputfieldIgnoreChanges: Item that should not be tracked for changes to value.
 * - InputfieldHasUpload: Item that has a file upload input within it. 
 * - InputfieldIsOffset: Item that is offset from others (not supported by all admin themes). 
 * - InputfieldRepeaterItem: Item that represents a Repeater item. 
 * - collapsed9: Item that is not collapsible (always open). 
 * - collapsed10: Item that is collapsed and dynamically loaded by AJAX when opened.
 * - collapsed11: Item that is collapsed only when blank, and dynamically loaded by AJAX when opened.
 * 
 * Children of Inputfield elements have these classes:
 * - InputfieldHeader: Assigned to the header of an Inputfield, typically the <label> element. Always visible.
 * - InputfieldHeaderHidden: When paired with InputfieldHeader classs, indicate header is hidden.
 * - InputfieldStateToggle: Assigned to element that toggles open/collapsed state (typically InputfieldHeader). 
 * - InputfieldContent: Assigned to the content of the Inputfield, wraps any <input> elements. Hidden when collapsed.
 *     
 * Other classes:
 * - Inputfields: Class <ul> or other element that contains .Inputfield children. 
 * - body.InputfieldColumnWidthsInit: Class assigned to body element when column widths initialized. 
 * - i.toggle-icon: Assigned to icon that also works as an .InputfieldStateToggle element. 
 * - maxColHeightSpacer: Used internally for aligning the height of a row of columns, when applicable. 
 * 
 * EVENTS
 * ======
 * Dependency events:
 * - showInputfield: Inputfield is shown as a result of field dependencies, triggered on document element, receives $inputfield as argument.
 * - hideInputfield: Inputfield is hidden as a result of field dependencies, triggered on document element, receives $inputfield as argument.
 * 
 * Visibility events: 
 * - openReady: Called right before Inputfield is opened, triggered on .Inputfield element. 
 * - opened: Called after Inputfield is opened, triggered on .Inputfield element.
 * - closeReady: Called before Inputfield is closed/collapsed, triggered on .Inputfield element.
 * - closed: Called after Inputfield is closed/collapsed, triggered on .Inputfield element.
 * - reload: Can be triggered on an .Inputfield element to force it to reload via ajax (where supported). 
 * - reloaded: Triggered on an .Inputfield element after it has reloaded via ajax. 
 * - resized: Triggered on an .Inputfield element after something has caused it to resize. 
 * - columnWidth: Triggered on .Inputfield when an API call to set column width, receives width percent after event argument.
 * 
 * Other events:
 * - changed: Triggered on an .Inputfield that has an input element within it changed (3.0.184+)
 * - AjaxUploadDone: Triggered on an .Inputfield element after a file has been ajax-uploaded within it. 
 * 
 * ATTRIBUTES
 * ==========
 * Form attributes:
 * - data-colspacing: form elements may have a "data-colspacing" attribute with a 0 or 1 value that indicates space between Inputfield columns. 
 * - data-confirm: form.InputfieldFormConfirm elements have this attribute with an alert message to display when appropriate. 
 * - data-uploading: form elements may have this to indicate a message shown when an action can abort a file that is uploading.
 * 
 * Inputfield attributes:
 * - data-show-if: Contains the selector for a "show if" condition when Inputfield has InputfieldStateShowIf class.
 * - data-required-if: Contains the selector for a "required if" condition when Inputfield has InputfieldStateRequiredIf class.
 * - data-colwidth: Contains the specified column width for the field (percentage) if Inputfield has an InputfieldColumnWidth class.
 * - data-original-width: Used internally to remember the original value of data-colwidth when dependencies change the equation.
 * - id: The Inputfield elements can have an "id" attribute of "wrap_Inputfield_[name]" where [name] is the input name.
 *   This is only the case if a different "id" attribute has not been specifically assigned to it. 
 *
 */

/**
 * Inputfields public API variable 
 * 
 * ProcessWire 3.0.144+
 * 
 */
var Inputfields = {

	/**
	 * Are we currently in debug mode?
	 * 
	 */
	debug: false,

	/**
	 * Are we currently processing dependencies?
	 * 
	 */
	processingIfs: false,

	/**
	 * Are we currently toggling collapsed state or visibility?
	 * 
 	 */	
	toggling: false,

	/**
	 * Toggle behavior (0=standard, 1=consistent)
	 * 
 	 */	
	toggleBehavior: 0, 

	/**
	 * Default duration (in MS) for certain visual animations
	 * 
	 */
	defaultDuration: 0,

	/**
	 * Initialize all Inputfields located within $target
	 *
	 * @param $target
	 *
	 */
	init: function($target) {
		InputfieldsInit($target);
	},

	/**
	 * Toggle the given $inputfield open or closed
	 *
	 * Also triggers these events on $inputfield: openReady, closeReady, opened, closed
	 *
	 * @param object|string $inputfield Inputfield to toggle, or some element within it, or field name
	 * @param bool open Boolean true to open, false to close, or null (or omit) for opposite of current state (default=opposite of current state)
	 * @param int duration How many milliseconds for animation? (default=100)
	 * @param function callback Optional function to call upon completion, receives Inputfield object, open and duration as arguments (default=none)
	 * @returns Returns Inputfield element
	 *
	 */
	toggle: function($inputfield, open, duration, callback) {
		
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return $inputfield;

		var $header = $inputfield.children('.InputfieldHeader, .ui-widget-header');
		var $content = $inputfield.children('.InputfieldContent, .ui-widget-content');
		var $toggleIcon = $header.find('.toggle-icon');
		var isCollapsed = $inputfield.hasClass('InputfieldStateCollapsed');
		var isAjax = $inputfield.hasClass('collapsed10') || $inputfield.hasClass('collapsed11');
		var Inputfields = this;
		var $siblings = null;

		if($inputfield.hasClass('InputfieldAjaxLoading')) return $inputfield;
		if($inputfield.hasClass('InputfieldStateToggling')) return $inputfield;
		
		if(!isAjax && !this.toggling && $inputfield.hasClass('InputfieldColumnWidth')) {
			var $siblings = Inputfields.getAllInRow($inputfield);
			if($siblings.length < 2) $siblings = null;
		}

		if(typeof open == "undefined" || open === null) open = isCollapsed;
		if(typeof duration == "undefined") duration = this.defaultDuration;

		function completed() {
			if(Inputfields.toggling === $inputfield.prop('id')) {
				if($siblings && $siblings.length) {
					//Inputfields.toggle($siblings, open, duration, callback);
					$siblings.each(function() {
						Inputfields.toggle(jQuery(this), open, 0);
					});
				}
				setTimeout(function() { Inputfields.toggling = false; }, 100);
				$siblings = null;
			}
			if(typeof callback != "undefined") callback($inputfield, open, duration);
		}

		function toggled() {
			// jQuery seems to add overflow:hidden, and this interferes with outline CSS property on Inputfields
			if($inputfield.css('overflow') == 'hidden') $inputfield.css('overflow', '');
			$toggleIcon.toggleClass($toggleIcon.attr('data-to')); // data-to=classes to toggle
			$inputfield.removeClass('InputfieldStateToggling');
			Inputfields.redraw($inputfield, 500); 
			// setTimeout('InputfieldColumnWidths()', 500);
			completed();
		}

		function opened() {
			$inputfield.trigger('opened', $inputfield);
			if($inputfield.hasClass('InputfieldColumnWidth')) {
				$inputfield.children('.InputfieldContent').show();
			}
			if($inputfield.prop('id') === Inputfields.toggling && !$inputfield.hasClass('InputfieldNoFocus')) {
				Inputfields.focus($inputfield);
			}
			toggled();
		}

		function closed() {
			if($inputfield.css('overflow') == 'hidden') $inputfield.css('overflow', '');
			$inputfield.trigger('closed', $inputfield);
			if($inputfield.hasClass('InputfieldColumnWidth')) {
				$inputfield.children('.InputfieldContent').hide();
			}
			toggled();
		}

		// check if we need to open parent inputfields first
		if(open && !$inputfield.is(':visible')) {
			// if Inputfield is in a non-visible tab, open the tab
			var $tabContent = $inputfield.parents('.InputfieldWrapper').last();
			if($tabContent.length && !$tabContent.is(':visible')) {
				var $tabButton = jQuery('#_' + $tabContent.attr('id'));
				if($tabButton.length) {
					$tabContent.show();
					setTimeout(function() { $tabButton.click(); }, 25);
				}
			}
			// inputfield is not visible likely due to parents being hidden
			var $collapsedParent = $inputfield.closest('.InputfieldStateCollapsed:not([id=' + $inputfield.attr('id') + '])');
			if($collapsedParent.length) {
				Inputfields.toggle($collapsedParent, true, duration, function($in) {
					Inputfields.toggle($in, true, duration, callback);
				});
			}
		}

		// if open requested and inputfield already open, no action is needed
		if(open && !isCollapsed) {
			completed();
			return $inputfield;
		}

		// if close requested and inputfield already closed, no action is needed
		if(!open && isCollapsed) {
			completed();
			return $inputfield;
		}

		// if ajax loaded, force InputfieldStates() click handler to open this one
		if(isCollapsed && isAjax && !$inputfield.hasClass('InputfieldStateWasCollapsed')) {
			$toggleIcon.click();
			return $inputfield;
		}
	
		if(!this.toggling) this.toggling = $inputfield.prop('id');

		// handle either open or close
		if(open && isCollapsed) {
			$inputfield.addClass('InputfieldStateToggling').trigger('openReady', $inputfield);
			if(duration && jQuery.ui) {
				$inputfield.toggleClass('InputfieldStateCollapsed', duration, opened);
			} else {
				$inputfield.removeClass('InputfieldStateCollapsed');
				opened();
			}
		} else if(!open && !isCollapsed) {
			$inputfield.addClass('InputfieldStateToggling').trigger('closeReady', $inputfield);
			if(duration && jQuery.ui) {
				$inputfield.toggleClass('InputfieldStateCollapsed', duration, closed);
			} else {
				$inputfield.addClass('InputfieldStateCollapsed');
				closed();
			}
		}
		
		return $inputfield;
	},

	/**
	 * Toggle all given $inputfields open or closed
	 *
	 * Also triggers these events on each $inputfield: openReady, closeReady, opened, closed
	 *
	 * @param object $inputfields jQuery object of one or more Inputfields or selector string that matches Inputfields
	 * @param bool open Boolean true to open, false to close, or null (or omit) for opposite of current state (default=opposite of current state)
	 * @param int duration How many milliseconds for animation? (default=100)
	 * @param function callback Optional function to call upon completion, receives Inputfield object, open and duration as arguments (default=none)
	 * @returns Returns jQuery object of Inputfields 
	 * @since 3.0.178
	 *
	 */
	toggleAll: function($inputfields, open, duration, callback) {
		if(typeof $inputfields === "string") $inputfields = jQuery($inputfields);
		var Inputfields = this;
		$($inputfields.get().reverse()).each(function(i, el) {
			Inputfields.toggle($(el), open, duration, callback);
		});
		return $inputfields;
	},

	/**
	 * Open a collapsed Inputfield
	 *
	 * @param $inputfield
	 * @param duration Optional number of milliseconds for animation or 0 for none (default=100)
	 * @param function callback Optional function to call upon completion, receives Inputfield object as argument (default=none)
	 * @return Inputfield
	 *
	 */
	open: function($inputfield, duration, callback) {
		return this.toggle($inputfield, true, duration);
	},

	/**
	 * Close/collapse an open Inputfield
	 *
	 * @param $inputfield
	 * @param duration Optional number of milliseconds for animation or 0 for none (default=100)
	 * @param function callback Optional function to call upon completion, receives Inputfield object as argument (default=none)
	 * @return Inputfield
	 *
	 */
	close: function($inputfield, duration, callback) {
		return this.toggle($inputfield, false, duration);
	},

	/**
	 * Show a currently hidden Inputfield 
	 * 
	 * @param $inputfield
	 * @return Returns the Inputfield element
	 * 
	 */
	show: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(!this.hidden($inputfield)) return $inputfield; // already visible
		$inputfield.removeClass('InputfieldStateHidden').show(); 
		jQuery(document).trigger('showInputfield', $inputfield);
		this.redraw(null, 50);
		return $inputfield;
	},

	/**
	 * Hide a currently visible Inputfield
	 *
	 * @param $inputfield
	 * @return Returns the Inputfield element
	 *
	 */
	hide: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(this.hidden($inputfield)) return $inputfield;
		$inputfield.addClass('InputfieldStateHidden').hide();
		jQuery(document).trigger('hideInputfield', $inputfield);
		this.redraw(null, 50);
		return $inputfield;
	},
	
	/**
	 * Redraw Inputfield elements within $target element, adjusting any widths as necessary
	 * 
	 * @param $target Target within this element or omit for all Inputfields in document
	 * @param int $delay Optionally delay this many ms (default=0)
	 *
	 */
	redraw: function($target, delay) {
		if(typeof delay == "undefined") delay = 0;
		setTimeout(function() {
			if(typeof $target != "undefined" && $target && $target.length) {
				if($target.hasClass('Inputfield')) $target = $target.closest('Inputfields');
				InputfieldColumnWidths($target);
				// jQuery(document).trigger('redrawInputfields', $target);
			} else {
				InputfieldColumnWidths();
				// jQuery(document).trigger('redrawInputfields', null);
			}
			jQuery(window).resize(); // trigger for FormBuilder or similar
		}, delay);
	},

	/**
	 * Reload an Inputfield with an ajax request (when/where supported)
	 *
	 * @param $inputfield
	 * @param function callback Optional function to call upon completion
	 * @return Returns the Inputfield element
	 *
	 */
	reload: function($inputfield, callback) {
		$inputfield = this.inputfield($inputfield);
		if($inputfield.length) {
			if(typeof callback != "undefined") $inputfield.one('reloaded', callback);
			$inputfield.trigger('reload');
		}
		return $inputfield;
	},

	/**
	 * Focus the Inputfield
	 *
	 * If the Inputfield is not visible, this method will track it down and reveal it.
	 * If the Inputfield is collapsed, this method will open it.
	 * If the Inputfield has an input that can be focused, it will be focused.
	 *
	 * @param $inputfield
	 * @param function callback Optional function to call upon completion
	 * @return Returns the Inputfield element
	 *
	 */
	focus: function($inputfield, callback) {
		
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return $inputfield;
		var Inputfields = this;

		if($inputfield.hasClass('InputfieldStateCollapsed') || !$inputfield.is(':visible')) {
			Inputfields.toggle($inputfield, true, 0, function($in, open, duration) {
				Inputfields.focus($in, callback);
			});
			return $inputfield;
		}

		var $input;
		var focused = false;
		var showOnly = false;

		if($inputfield.hasClass('InputfieldNoFocus')) {
			// class indicates does not support focusing
			showOnly = true;
		}

		if(showOnly) {
			$input = jQuery([]);
		} else {
			// find input element within Inputfield
			$input = $inputfield.find(":input:visible:enabled:not(button):not(.InputfieldNoFocus):first");
			// do not attempt to focus absolute positioned inputs or button elements
			if($input.css('position') == 'absolute' || $input.is('button')) $input = jQuery([]);
		}

		if($input.length) {
			var t = $input.attr('type');
			if($input.is('textarea') || t == 'text' || t == 'email' || t == 'url' || t == 'number') {
				$input.focus();
				focused = true;
			}
		}

		if(focused) {
			if(typeof callback != "undefined") callback($inputfield);
		} else if(!this.inView($inputfield)) {
			// item could not be directly focused, see if we can make make it visible 
			Inputfields.find($inputfield, false, callback);
		}
		
		return $inputfield;
	},

	/**
	 * Find the Inputfield and put it in view and open it (if not already)
	 *
	 * If the Inputfield is not visible, this method will track it down and reveal it.
	 * If the Inputfield is collapsed, this method will open it.
	 *
	 * @param $inputfield
	 * @param bool highlight Highlight temporily once found? (default=true)
	 * @param function callback Optional function to call upon completion
	 * @param int level Recursion level (do not specify, for internal use only)
	 * @return Returns the Inputfield element
	 *
	 */
	find: function($inputfield, highlight, callback, level) {
		
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return $inputfield;
		if(typeof highlight == "undefined") highlight = true;
		if(typeof level == "undefined") level = 0;

		// locate the Inputfield
		if($inputfield.hasClass('InputfieldStateCollapsed') || !$inputfield.is(':visible')) {
			var hasNoFocus = $inputfield.hasClass('InputfieldNoFocus');
			// Inputfields.toggle() can call Inputfields.focus(), so prevent the focus by adding this class
			if(!hasNoFocus) $inputfield.addClass('InputfieldNoFocus');
			if($inputfield.hasClass('WireTab') && !$inputfield.is(':visible')) $inputfield = $inputfield.find('.Inputfield');
			this.toggle($inputfield, true, 0, function($in, open, duration) {
				if(level > 9) return;
				var timeout = level > 0 ? 10 * level : 0;
				setTimeout(function() { Inputfields.find($inputfield, highlight, callback, level + 1); }, timeout); 
			});
			// remove the class we added
			if(!hasNoFocus) $inputfield.removeClass('InputfieldNoFocus');
			return $inputfield;
		}

		var completed = function() {
			if(highlight) Inputfields.highlight($inputfield);
			if(typeof callback != "undefined") callback($inputfield);
		}

		setTimeout(function() {
			if(false && Inputfields.inView($inputfield)) {
				completed();
			} else {
				var properties = {
					scrollTop: $inputfield.offset().top - 10
				};
				var options = {
					duration: 100,
					complete: completed
				};
				// scroll to Inputfield
				jQuery('html, body').animate(properties, options);
			}
		}, 100); 
		
		return $inputfield;
	},

	/**
	 * Highlight an Inputfield
	 *
	 * @param $inputfield
	 * @param duration Optionally specify how long to highlight for in ms? (default=1000, 0=forever)
	 * @param cls Optional class to use as highlight class
	 * @return Inputfield
	 *
	 */
	highlight: function($inputfield, duration, cls) {
		$inputfield = this.inputfield($inputfield);
		if(typeof cls == "undefined") {
			cls = $inputfield.hasClass('InputfieldIsHighlight') ? 'InputfieldIsPrimary' : 'InputfieldIsHighlight';
		}
		if(typeof duration == "undefined") {
			duration = 1000;
		}
		$inputfield.addClass(cls);
		if(duration > 0) {
			setTimeout(function() {
				$inputfield.removeClass(cls);
			}, duration);
		}
		return $inputfield;
	},

	/**
	 * Is the Inputfield currently viewable in the user’s viewable area?
	 * 
	 * @param $inputfield
	 * @return bool
	 * 
	 */
	inView: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.is(':visible')) return false;
		var pageTop = jQuery(window).scrollTop();
		var pageBottom = pageTop + jQuery(window).height();
		var inputTop = $inputfield.offset().top;
		var inputBottom = inputTop + $inputfield.height();
		var inView = ((inputTop <= pageBottom) && (inputBottom >= pageTop));
		// var fullyInView = ((pageTop < inputTop) && (pageBottom > inputBottom));
		return inView;
	},

	/**
	 * Get or set column width (perentage) of Inputfield
	 * 
	 * Triggers a 'columnWidth' event on Inputfield element, function receives the width to set as an argument. 
	 * 
 	 * @param $inputfield
	 * @param int value Optionally specify a width to set between 10 and 100 (percent)
	 * @returns {number}|{object} Returns Inputfield when setting width or current width (percent) when getting
	 * 
	 */	
	columnWidth: function($inputfield, value) {
		
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return 0;
		
		if(typeof value != "undefined" && value) {
			// set width
			if(value > 100 || value < 1) value = 100;
			if(value < 100 && !$inputfield.hasClass('InputfieldColumnWidth')) {
				$inputfield.addClass('InputfieldColumnWidth');
			}
			var w = this.columnWidth($inputfield);
			if(w != value) {
				if(!$inputfield.attr('data-original-width')) {
					// remember original width, if there was one
					$inputfield.attr('data-original-width', w);
				}
				$inputfield.attr('data-colwidth', value);
				// delegate actual setting of width to event handler 
				$inputfield.trigger('columnWidth', value);
			}
			return $inputfield;
			
		} else {
			// get width
			if(!$inputfield.hasClass('InputfieldColumnWidth')) return 100;
			var pct = $inputfield.attr('data-colwidth'); // colwidth tracked when NoWidths mode enabled
			if(typeof pct == "undefined" || !pct.length) {
				var style = $inputfield.attr('style');
				if(typeof style == "undefined" || !style) return 100;
				pct = parseInt(style.match(/width:\s*(\d+)/i)[1]);
			} else {
				pct = parseInt(pct);
			}
			// store the original width in another attribute, in case it is needed later 
			if(!$inputfield.attr('data-original-width')) {
				$inputfield.attr('data-original-width', pct);
			}
			if(pct < 1) pct = 100;
			return pct;
		}
	},

	/**
	 * Start a spinner for Inputfield
	 *
	 * @param $inputfield
	 *
	 */
	startSpinner: function($inputfield) {
		
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return;

		var id = $inputfield.attr('id') + '-spinner';
		var $spinner = $('#' + id);
		var $header = $inputfield.children('.InputfieldHeader');
		
		if(!$spinner.length) {
			$spinner = $("<i class='InputfieldSpinner fa fa-spin fa-spinner'></i>");
			$spinner.attr('id', id);
		}
		
		$spinner.css({ 
			float: 'right',
			marginRight: '30px',
			marginTop: '3px'
		});
		
		$header.append($spinner.hide());
		$spinner.fadeIn();
	},

	/**
	 * Stop a spinner for Inputfield
	 *
	 * @param $inputfield
	 *
	 */
	stopSpinner: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return;
		var $spinner = $('#' + $inputfield.attr('id') + '-spinner');
		if($spinner.length) $spinner.fadeOut('fast', function() { $spinner.remove(); }); 
	},

	/**
	 * Does the given Inputfield have a hidden state?
	 * 
	 * Note: if you want to hide an Inputfield, use the hide() method
	 * 
	 * @param $inputfield
	 * @return bool
	 * 
	 */
	hidden: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		return $inputfield.hasClass('InputfieldStateHidden');
	},
	
	/**
	 * Get or set the changed state of the Inputfield
	 *
	 * @param $inputfield
	 * @param bool value Specify true to indicate as changed, false if not. Or omit to only get current changed state.
	 * @return bool
	 *
	 */
	changed: function($inputfield, value) {
		$inputfield = this.inputfield($inputfield);
		if($inputfield.hasClass('InputfieldIgnoreChanges')) return false;
		var changed = $inputfield.hasClass('InputfieldStateChanged');
		if(typeof value == "undefined") return changed;
		if(value && !changed) {
			$inputfield.addClass('InputfieldStateChanged').trigger('change');
			return true;
		} else if(changed) {
			$inputfield.removeClass('InputfieldStateChanged');
			return false;
		}
	},

	/**
	 * Get name attribute for given inputfield
	 * 
	 * @param $inputfield
	 * @returns string
	 * 
	 */
	name: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return '';
		var name = $inputfield.attr('data-name');
		if(typeof name != "undefined" && name && name.length) return name;
		name = '';
		var id = $inputfield.prop('id');
		if(id.indexOf('wrap_Inputfield_') === 0) {
			name = id.replace('wrap_Inputfield_', '');
		} else if(id.indexOf('wrap_') === 0) {
			name = id.substring(5); 
		} else {
			var classes = $inputfield.attr('class').split(' '); 
			for(var n = 0; n < classes.length; n++) {
				if(classes[n].indexOf('Inputfield_') !== 0) continue;
				name = classes[n].substring(11);
				break;
			}
		}
		if(name.length) $inputfield.attr('data-name', name); 
		return name;
	},

	/**
	 * Get header label for given Inputfield
	 * 
	 * @param $inputfield
	 * @return string
	 * 
	 */
	label: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return '';
		var label = $inputfield.attr('data-label');
		if(typeof label != "undefined" && label && label.length) return label;
		var $header = $inputfield.children('.InputfieldHeader');
		if(!$header.length) return '';
		label = $header.text();
		if(label.length) label = jQuery.trim(label);
		return label;
	}, 
	
	/**
	 * Get the input element(s) for the current Inputfield
	 * 
	 * @param $inputfield
	 * 
	 */
	input: function($inputfield) {

		var $input = jQuery([]);
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return $input;
		
		var $header = $inputfield.children('.InputfieldHeader');
		var attrFor = $header.attr('for');
		
		if(attrFor) {
			$input = $inputfield.find(':input[id="' + attrFor + '"]'); 	
			if($input.length) return $input;
		}
		
		var name = this.name($inputfield); 
		if(name.length) $input = $inputfield.find(':input[name="' + name + '"]');

		return $input;
	},

	/**
	 * Move an Inputfield by inserting it before another
	 * 
	 * @param $insertInputfield Inputfield to move
	 * @param $beforeInputfield Move before this Inputfield
	 * @returns Inputfield
	 * 
	 */
	insertBefore: function($insertInputfield, $beforeInputfield) {
		$insertInputfield = this.inputfield($insertInputfield);
		$beforeInputfield = this.inputfield($beforeInputfield);
		$beforeInputfield.before($insertInputfield);
		return $insertInputfield;
	},

	/**
	 * Move an Inputfield by inserting it after another
	 *
	 * @param $insertInputfield Inputfield to move
	 * @param $afterInputfield Move after this Inputfield
	 * @returns Inputfield
	 *
	 */
	insertAfter: function($insertInputfield, $afterInputfield) {
		$insertInputfield = this.inputfield($insertInputfield);
		$afterInputfield = this.inputfield($afterInputfield);
		$afterInputfield.after($insertInputfield);
		return $insertInputfield;
	}, 

	/**
	 * Ensure given argument resolves to an .Inputfield element and return it
	 *
	 * @param $inputfield
	 * @returns object
	 *
	 */
	inputfield: function($inputfield) {
		if(typeof $inputfield == "string") {
			if($inputfield.indexOf('#') !== 0 && $inputfield.indexOf('.') !== 0) {
				var $in = jQuery(':input[name=' + $inputfield + ']');
				if(!$in.length) $in = jQuery(':input[id=' + $inputfield + ']'); 
				if(!$in.length) $in = jQuery(':input[name="' + $inputfield + '[]"]'); // array name
				if(!$in.length) $in = jQuery('#' + $inputfield + '.Inputfield'); 
				$inputfield = $in;
			} else {
				$inputfield = jQuery($inputfield)
			}
		}
		if(!$inputfield.length) return jQuery([]); // empty jQuery object (length=0)
		if(!$inputfield.hasClass('Inputfield')) {
			$inputfield = $inputfield.closest('.Inputfield');
		}
		return $inputfield;
	},

	/**
	 * Execute find, focus or highlight action from URL fragment/hash
	 * 
	 * #find-field_name
	 * #focus-field_name
	 * #highlight-field_name
	 * 
	 * @param hash
	 * @since 3.0.151
	 * 
	 */
	hashAction: function(hash) {
		
		var pos, action, name;
		
		if(hash.indexOf('#') === 0) hash = hash.substring(1);
		pos = hash.indexOf('-');
		if(pos < 3 || hash.length < pos + 2) return;
		if(jQuery('#' + hash).length) return; // maps to existing element ID attribute
		
		action = hash.substring(0, pos);
		name = hash.substring(pos + 1);
		
		if(action === 'find') {
			Inputfields.find(name);
		} else if(action === 'focus') {
			Inputfields.focus(name);
		} else if(action === 'highlight') {
			Inputfields.highlight(name);
		} else {
			// some other action we do not recognize
		}
	},

	/**
	 * Get first Inputfield in row that $inputfield appears in 
	 * 
	 * @param $inputfield
	 * @returns object
	 * @since 3.0.170
	 * 
	 */
	getFirstInRow: function($inputfield) {
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return $inputfield;
		if(!$inputfield.hasClass('InputfieldColumnWidth')) return $inputfield;
		if($inputfield.hasClass('InputfieldColumnWidthFirst')) return $inputfield;
		var $col = $inputfield;
		do {
			$col = $col.prev('.Inputfield');
			if($col.hasClass('InputfieldColumnWidthFirst')) break; // found it
		} while($col.length && $col.hasClass('InputfieldColumnWidth'));
		return $col.hasClass('InputfieldColumnWidthFirst') ? $col : $inputfield;
	},
	
	/**
	 * Get all siblings of $inputfield in an InputfieldColumnWidth width row
	 * 
 	 * @param $inputfield
	 * @param bool andSelf Specify true to also include $inputfield, or omit argument to exclude $inputfield (default=false)
	 * @param bool andHidden Include hidden inputfields also? (default=false)
	 * @returns object
	 * @since 3.0.170
	 * 
	 */	
	getSiblingsInRow: function($inputfield, andSelf, andHidden) {
		$inputfield = this.inputfield($inputfield);
		if(!$inputfield.length) return $inputfield;
		if(typeof andSelf === "undefined") andSelf = false;
		if(typeof andHidden === "undefined") andHidden = false;
		var pct = this.columnWidth($inputfield);
		if(pct < 1 || pct > 99) return jQuery([]); 
		var $col = this.getFirstInRow($inputfield);
		var sel = '';
		while($col.length) {
			if($col.hasClass('InputfieldStateHidden') && !andHidden) {
				// skip hidden inputfield
			} else if(andSelf || $col.prop('id') !== $inputfield.prop('id')) {
				// add inputfield
				sel += (sel.length ? ',' : '') + '#' + $col.prop('id');
			}
			$col = $col.next('.InputfieldColumnWidth');
			if($col.hasClass('InputfieldColumnWidthFirst')) break;
		}
		return sel.length ? jQuery(sel) : $inputfield;	
	},

	/**
	 * Get all Inputfields in row occupied by given $inputfield
	 * 
 	 * @param $inputfield
	 * @returns object
	 * @since 3.0.170
	 * 
	 */	
	getAllInRow: function($inputfield, andHidden) {
		if(typeof andHidden === "undefined") andHidden = false;
		return this.getSiblingsInRow($inputfield, true, andHidden);
	}
	
};

/*******************************************************************************************/

/**
 * Console logging for For debug mode
 * 
 * @param note
 * 
 */
function consoleLog(note) {
	// uncomment the line below to enable debugging console
	if(Inputfields.debug) console.log(note);
}

/******************************************************************************************
 * Setup Inputfield dependencies, to be called once at document.ready
 * 
 * @constructor
 * 
 */
function InputfieldDependencies($target) {
	var $ = jQuery;
	// var allConditions = {};
	
	if(Inputfields.processingIfs) return;
	
	if(typeof $target == "undefined") {
		$target = $(".InputfieldForm:not(.InputfieldFormNoDependencies)");
	} else if($target.hasClass('InputfieldForm')) {
		if($target.hasClass('InputfieldFormNoDependencies')) return;
	} else {
		if($target.closest('.InputfieldFormNoDependencies').length > 0) return;
	}
	
	/**
	 * Trim quotes and spaces from the given value
	 * 
	 * @param value
	 * @returns string
	 * 
	 */
	function trimValue(value) {
		value = jQuery.trim(value);
		var first = value.substring(0,1);
		var last = value.substring(value.length-1, value.length);
		if((first == '"' || first == "'") && first == last) value = value.substring(1, value.length-1);
		return value;
	}

	/**
	 * Remove quotes from value (if present)
	 *
	 * @param value
	 * @return string
	 *
	 */
	function trimParseValue(value) {
		// determine if we need to trim off quotes
		return parseValue(trimValue(value));
	}

	function extractFieldAndSubfield(field) {
		// extract subfield, if there is one
		var subfield = '';
		var dot = field.indexOf('.');
		if(dot > 0) {
			subfield = field.substring(dot+1);
			field = field.substring(0, dot);
		}
		return { field: field, subfield: subfield }
	}
	
	/**
	 * Convert string value to integer or float when appropriate
	 *
	 * @param str string
	 * @param str2 string Optional second value for context
	 * @return string|int|float
	 *
	 */
	function parseValue(str, str2) {

		str = jQuery.trim(str);
		if(str.length > 0 && !jQuery.isNumeric(str)) {
			return str;
		}

		if(str.length == 0) {
			// empty value: should it be a blank or a 0?
			var t = typeof str2;
			if(t != "undefined") {
				// str2 is present for context
				if(t == "integer") return 0;
				if(t == "float") return 0.0;
				return str;
			} else {
				// no context, assume blank
				return str;
			}
		}

		var dot1 = str.indexOf('.');
		var dot2 = str.lastIndexOf('.');

		if(dot1 == -1 && /^-?\d+$/.test(str)) {
			// no dot present, and all numbers so must be integer
			return parseInt(str);
		}

		if(dot2 > -1 && dot1 != dot2) {
			// more than one dot, can't be a float
			return str;
		}

		if(/^-?[\d.]+$/.test(str)) {
			// looks to be a float
			return parseFloat(str);
		}

		return str;
	}
	
	/**
	 * Returns whether or not value matched
	 *
	 * @param field Name of field
	 * @param operator
	 * @param value value to match for
	 * @param conditionValue
	 * @return int 0=value didn't match, 1=value matched
	 *
	 */
	function matchValue(field, operator, value, conditionValue) {
		var matched = 0;

		switch(operator) {
			case '=': if(value == conditionValue) matched++; break;
			case '!=': if(value != conditionValue) matched++; break;
			case '>': if(value > conditionValue) matched++; break;
			case '<': if(value < conditionValue) matched++; break;
			case '>=': if(value >= conditionValue) matched++; break;
			case '<=': if(value <= conditionValue) matched++; break;
			case '*=':
			case '%=': if(value.indexOf(conditionValue) > -1) matched++; break;
		}

		consoleLog('Field ' + field + ' - Current value: ' + value);
		consoleLog('Field ' + field + ' - Matched? ' + (matched > 0 ? 'YES' : 'NO'));

		return matched;
	}

	/**
	 * Find and return a checkbox or radios field named by conditionField
	 *
	 * Returns null if it was unable to locate the field
	 *
	 * @param condition
	 * @param conditionField
	 * @returns {*} Includes field, value and condition properties, or returns null on fail
	 *
	 */
	function getCheckboxFieldAndValue(condition, conditionField, conditionSubfield) {
		// if field isn't present by #id it may be present by #id+value as a checkbox/radio field is

		var $field = null;
		var value;

		consoleLog('getCheckboxFieldAndValue(see-next-line, ' + conditionField + ', ' + conditionSubfield + ')');
		consoleLog(condition)

		// first check if we've got a count subfield, because we'll be counting checked inputs for 
		// those rather than checking the actual values

		if(conditionSubfield == 'count' || conditionSubfield == 'count-checkbox') {
			// count number of matching checked inputs
			consoleLog('Using count checkbox condition');
			$field = $("#wrap_Inputfield_" + conditionField + " :input");
			if($field.length) {
				value = $("#wrap_Inputfield_" + conditionField + " :checked").length;
				condition.subfield = 'count-checkbox';
				return { field: $field, value: value, condition: condition };
			}
			return null;
		}

		// we'll be looking for a specific value in the checkboxes/radios
		consoleLog('Using checkbox value or label comparison option');
		value = [];

		// for loop in case there is a multi-value OR condition
		for(var i = 0; i < condition.values.length; i++) {

			var _conditionValue = new String(condition.values[i]); // original
			var conditionValue = trimValue(_conditionValue.replace(/\s/g, '_')); // spaces converted to "_"
			var fieldID = "#Inputfield_" + conditionField + "_" + conditionValue; // i.e. "Inputfield_mycheckbox_1"
			
			$field = $(fieldID);
			if(!$field.length) {
				fieldID = '#' + conditionField + "_" + conditionValue; // i.e. "mycheckbox_1" (alternate)
				$field = $(fieldID);
			}

			consoleLog('Required condition value: ' + conditionValue);

			if($field.length) {
				// found a matching checkbox/radio field
				var inputType = $field.attr('type');
				var val = '';
				consoleLog("Found " + inputType + " via value " + fieldID);
				if($field.is(":checked")) {
					// checkbox or radio IS checked
					val = $field.val();
					consoleLog(inputType + " IS checked: " + fieldID);
				} else if($field.attr('type') == 'radio') {
					// radio: one we are looking for is NOT checked, but determine which one is checked
					consoleLog(inputType + " is NOT checked: " + fieldID);
					var $checkedField = $field.closest('form').find("input[name=\"" + $field.attr('name') + "\"]:checked");
					if($checkedField.length) {
						val = $checkedField.val();
						consoleLog("Checked value is: " + val);
					}
				} else {
					// checkbox: if the field is not checked then we assume a blank value
					consoleLog(inputType + " is NOT checked: " + fieldID);
				}
				if(val.length) {
					consoleLog('Pushing checked value: ' + val);
					value.push(val);
				}
				continue;
			}

			if(conditionValue.length == 0 || conditionValue.match(/^[0-9]+$/)) {
				// condition value is numeric (like page ID) and didn't match above, so we're going to give up on it
				consoleLog('Unable to locate checkbox ' + fieldID + ', skipping')
				continue;
			}

			// if the above didn't find a checkbox, try to find it by label value
			consoleLog('Attempting to find checkbox by label: ' + conditionValue);
			// note $field now becomes a wrapper rather than an input. We're ok with that.
			$field = $("#wrap_Inputfield_" + conditionField);
			var $checkboxes = $field.find("input:checked");
			for(var cn = 0; cn < $checkboxes.length; cn++) {
				var $checkbox = $checkboxes.eq(cn);
				var $label = $checkbox.closest('label');
				if($label.length) {
					var label = jQuery.trim($label.text());
					if(label == _conditionValue) {
						consoleLog('Matching checked label found: ' + _conditionValue);
						value.push(label);
					} else {
						consoleLog('Matching checked label not found: ' + _conditionValue);
					}
				}
			}
		} // foreach condition.values

		if($field) return {
			field: $field,
			value: value,
			condition: condition
		}

		return null;
	}

	/**
	 * Called when targeted Inputfield has changed
	 * 
	 * @param conditions
	 * @param $fieldToShow
	 * 
	 */
	function inputfieldChange(conditions, $fieldToShow) {

		Inputfields.processingIfs = true;
		
		// Name of the field contained by $fieldToShow
		var fieldNameToShow = $fieldToShow.attr('id').replace(/wrap_Inputfield_/, '');

		if(Inputfields.debug) {
			consoleLog('-------------------------------------------------------------------');
			consoleLog('Field "' + fieldNameToShow + '" detected a change to a dependency field! Beginning dependency checks...');
		}

		// number of changes that were actually made to field visibility
		var numVisibilityChanges = 0;
		var show = true;
		var requiredMatches = 0;
		var notRequiredMatches = 0;

		for(var c = 0; c < conditions.length; c++) {

			// current condition we are checking in this iteration 
			var condition = conditions[c];

			if(Inputfields.debug) {
				consoleLog('----');
				consoleLog('Start Dependency ' + c);
				consoleLog('Condition type: ' + condition.type);
				consoleLog('Field: ' + condition.field);
				if (condition.subfield.length > 0) consoleLog('Subfield: ' + condition.subfield);
				consoleLog('Operator: ' + condition.operator);
				consoleLog('Required value: ' + condition.value);
			}

			// matched contains positive value when condition matches
			var matched = 0;

			// iterate through all OR fields (this will most often just be 1 field)
			for(var fn = 0; fn < condition.fields.length; fn++) {

				var fieldAndSubfield = extractFieldAndSubfield(condition.fields[fn]); 
				var conditionField = fieldAndSubfield.field;
				var conditionSubfield = fieldAndSubfield.subfield;
				var value = null;
				var $field = $("#Inputfield_" + conditionField);
				var hasCheckboxes = false;
				
				// in case they manually specified id property
				if($field.length == 0) $field = $("#" + conditionField);

				if($field.length == 0) {
					// field still not found, perhaps this is a checkbox/radio field which have id properties
					// that contain the values in them as well

					consoleLog('Detected possible checkbox or radio: ' + condition.field + condition.operator + condition.value);
					var fieldAndValue = getCheckboxFieldAndValue(condition, conditionField, conditionSubfield);
					if(fieldAndValue) {
						$field = fieldAndValue.field;
						value = fieldAndValue.value;
						condition = fieldAndValue.condition;
						hasCheckboxes = true;
					}

				} // identification of checkbox/radio fields

				// if we haven't matched a field by now, skip over it
				if($field.length == 0) {
					consoleLog("Unable to locate field: " + conditionField);
					continue;
				}
				
				var $inputfield = $field.closest('.Inputfield');

				// value of the dependency field we are checking (if not already populated above)
				if (value === null) {
					if($field.attr('type') == 'checkbox') {
						value = $field.is(":checked") ? $field.val() : null;
					} else {
						value = $field.val();
					}
				}

				// value will be placed in values so we can handle multiple value checks
				var values = [];

				// prefer blank to null for our checks
				if (value == null) value = '';

				// special case for 'count' subfield condition, 
				// where we take the value's length rather than the value
				if (condition.subfield == 'count') value = value.length;

				// if value is an object, make it in array
				// in either case, convert value to an array called values
				if (typeof value == 'object') {
					// object, convert to array
					values = jQuery.makeArray(value);
				} else if(typeof value == 'array') {
					// array, already
					values = value;
				} else if(typeof value == "string" && $inputfield.hasClass('InputfieldPage') && value.indexOf(',') > -1 && value.match(/^[,0-9]+$/)) {
					// CSV string of page IDs
					values = value.split(',');
				} else {
					// string: single value array
					values[0] = value;
				}

				// determine how many matches will be required
				var numMatchesRequired = 1;
				if (condition.operator == '!=') numMatchesRequired = (values.length * condition.values.length);
				// consoleLog([values, condition.values, numMatchesRequired]);

				// also allow for matching a "0" as an unchecked value, but only if there's isn't already an input with that value
				if(($field.attr('type') == 'checkbox' || $field.attr('type') == 'radio') && !$field.is(":checked")) {
					if($("#Inputfield_" + conditionField + "_0").length == 0 && $('#' + conditionField + '_0').length == 0) {
						values[1] = '0';
					}
				}

				// cycle through the values (most of the time, just 1 value).
				// increment variable 'show' each time a condition matches
				for (var n = 0; n < values.length; n++) {
					for (var i = 0; i < condition.values.length; i++) {
						var v = parseValue(values[n], condition.values[i]);
						matched += matchValue(conditionField, condition.operator, v, condition.values[i]);
					}
				}

				// if requirements met exit the loop
				if(matched >= numMatchesRequired) break;

			} // foreach fields

			consoleLog('----');

			// determine whether to show or hide the field
			if(condition.type == 'show') {
				if(matched >= numMatchesRequired) {
					// show it, which is the default behavior
				} else {
					show = false;
				}

			} else if(condition.type == 'required') {
				if(matched > 0) {
					// make it required it
					requiredMatches++;
				} else {
					notRequiredMatches++;
				}
			}

		} // foreach(conditions)

		// consoleLog('Summary (required/matched): ' + conditions.length + ' / ' + show);

		var required = requiredMatches > 0 && notRequiredMatches == 0;

		if(show) {
			consoleLog('Determined that field "' + fieldNameToShow + '" should be visible.');
			if(Inputfields.hidden($fieldToShow)) {
				Inputfields.show($fieldToShow);
				numVisibilityChanges++;
				consoleLog('Field is now visible.');
			} else {
				consoleLog('Field is already visible.');
			}
		} else {
			consoleLog('Determined that field "' + fieldNameToShow + '" should be hidden.');
			// hide it
			if(Inputfields.hidden($fieldToShow)) {
				consoleLog('Field is already hidden.');
			} else {
				Inputfields.hide($fieldToShow);
				consoleLog('Field is now hidden.');
				numVisibilityChanges++;
			}
			if(required) {
				consoleLog('Field is required but cancelling that since it is not visible.');
				required = false;
			}
		}

		if(required && requiredMatches > 0) {
			consoleLog('Determined that field "' + fieldNameToShow + '" should be required.');
			$fieldToShow.addClass('InputfieldStateRequired').find(":input:visible[type!=hidden]").addClass('required'); // may need to focus a specific input?

		} else if(!required && notRequiredMatches > 0) {
			consoleLog('Determined that field "' + fieldNameToShow + '" should not be required.');
			$fieldToShow.removeClass('InputfieldStateRequired').find(":input.required").removeClass('required');
		}

		if(numVisibilityChanges > 0) {
			consoleLog(numVisibilityChanges + ' visibility changes were made.');
			// Inputfields.redraw(0);
		}

		Inputfields.processingIfs = false;

	} // END inputfieldChange()
	

	/**
	 * Get the conditions for the given condition type 'show' or 'required'
	 * 
	 * This is called only at startup/initialization
	 * 
	 * @param conditionType
	 * @param array conditions
	 * @param $fieldToShow
	 * @returns []
	 * 
	 */
	function setupConditions(conditionType, conditions, $fieldToShow) {

		// find attribute data-show-if or data-required-if
		var selector = $fieldToShow.attr('data-' + conditionType + '-if');

		// if attribute wasn't present, skip...
		if(!selector || selector.length < 1) {
			// consoleLog('#' + $fieldToShow.attr('id') + '.data-' + conditionType + '-if is empty or not present'); 
			return conditions;
		}

		// un-encode entities in the data attribute value (selector)
		selector = $("<div />").html(selector).text();

		consoleLog('-------------------------------------------------------------------');
		consoleLog('Analyzing "' + conditionType + '" selector: ' + selector);

		var fieldNameToShow = $fieldToShow.attr('id').replace('wrap_Inputfield_', ''); 
		
		// separate each key=value component in the selector to parts array
		var parts = selector.match(/(^|,)([^,]+)/g);

		for(var n = 0; n < parts.length; n++) {

			// separate out the field, operator and value
			var part = parts[n];
			var match = part.match(/^[,\s]*([_.|a-zA-Z0-9]+)(=|!=|<=|>=|<|>|%=)([^,]+),?$/);
			if(!match) continue;
			var field = match[1];
			var operator = match[2];
			var value = match[3];
			var subfield = '';
			var fields = []; // if multiple
			var values = [];

			// detect OR selector in field
			if(field.indexOf("|") > -1) {
				consoleLog("OR field dependency: " + field);
				fields = field.split("|");
			} else {
				fields = [field];
			}

			var fieldAndSubfield = extractFieldAndSubfield(field);
			field = fieldAndSubfield.field;
			subfield = fieldAndSubfield.subfield;

			// if(subfield.length && fields.length > 1) {
			//	consoleLog('Error: subfield with OR condition not supported');
			// }

			if(Inputfields.debug) {
				consoleLog("Field: " + field);
				if (subfield.length) consoleLog("Subfield: " + subfield);
				consoleLog("Operator: " + operator);
				consoleLog("value: " + value);
			}

			// detect OR selector | in value
			if(value.indexOf("|") > -1){
				consoleLog("OR value dependency: " + value);
				values = value.split("|");
				for(var i = 0; i < values.length; i++) {
					values[i] = trimParseValue(values[i]);
				}
			} else {
				values = [ trimParseValue([value]) ];
			}

			var op = operator === '=' ? '==' : operator;
			var info = 'if(' + fields.join('|') + ' ' + op + ' ' + values.join('|') + ') ' + conditionType + ' ' + fieldNameToShow;

			// build the condition
			var condition = {
				'type': conditionType,
				'field': field,
				'fields': fields, // if multiple
				'subfield': subfield, // @todo determine if this is needed anymore
				'operator': operator,
				'value': value,
				'values': values,  // if multiple
				'info': info
			};

			// append to conditions array
			conditions[conditions.length] = condition;

			// attach change event handler to all applicable fields
			for(var fn = 0; fn < fields.length; fn++) {
				
				fieldAndSubfield = extractFieldAndSubfield(fields[fn]); 
				var f = fieldAndSubfield.field;

				// locate the dependency inputfield
				var $inputfield = $("#Inputfield_" + f);
				if($inputfield.length == 0) {
					consoleLog("Unable to find inputfield by: #Inputfield_" + f); 
					$inputfield = $("#" + f);
					if($inputfield.length == 0) consoleLog("Unable to find inputfield by: #" + f); 
				}

				// if the dependency inputfield isn't found, locate its wrapper..
				if($inputfield.length == 0) {
					// use any inputs within the wrapper
					$inputfield = $("#wrap_Inputfield_" + f).find(":input");
					if($inputfield.length == 0) consoleLog("Unable to find inputfield by: #wrap_Inputfield_" + f + " :input");
				}
				
				// if the dependency inputfield isn't found, locate its wrapper..
				if($inputfield.length == 0) {
					// use any inputs within the wrapper
					$inputfield = $("#wrap_" + f).find(":input");
					if($inputfield.length == 0) consoleLog("Unable to find inputfield by: #wrap_" + f + " :input");
				}

				// attach change event to dependency inputfield
				if($inputfield.length) {
					consoleLog('Attaching change event for: ' + $inputfield.attr('name'));
					$inputfield.change(function() {
						inputfieldChange(conditions, $fieldToShow);
					});
				} else {
					consoleLog('Failed to find inputfield, no change event attached');
				}
			}
		}
		
		// allConditions[fieldNameToShow] = conditions;

		return conditions;
	}

	/**
	 * Setup dependencies for the given field
	 * 
	 * Process an individual Inputfield.InputfieldShowStateIf and build a list of conditions for $fieldToShow
	 * 
	 * @param $fieldToShow Wrapper of field we are operating on (i.e. #wrap_Inputfield_[name]
	 * 
	 */
	function setupDependencyField($fieldToShow) {
		// Array of conditions required to show a field
		var conditions = [];
		conditions = setupConditions('show', conditions, $fieldToShow); 
		conditions = setupConditions('required', conditions, $fieldToShow); 
		// run the event for the first time to initalize the field
		inputfieldChange(conditions, $fieldToShow);
	}

	/*** Start InputfieldDependencies *************************************************/

	Inputfields.processingIfs = true; 
	$target.each(function() {
		$(this).find(".InputfieldStateShowIf, .InputfieldStateRequiredIf").each(function() {
			setupDependencyField($(this));
		});
	});
	Inputfields.processingIfs = false;
}


/************************************************************************************************
 * Adjust inputfield column widths to fill out each row
 *
 */

function InputfieldColumnWidths($target) {

	var $ = jQuery;
	var hasTarget = true;
	if(typeof $target == "undefined") {
		hasTarget = false;
		$target = $("form.InputfieldForm");
	}

	var colspacing = null; 
	var useHeights = null; 

	/**
	 * Return the current with of $item based on its "style" attribute
	 *
	 */
	function getWidth($item) {
		if($item.hasClass('InputfieldStateHidden')) return 0;
		var pct = $item.attr('data-colwidth'); // colwidth tracked when NoWidths mode enabled
		if(typeof pct ==  "undefined" || !pct.length) {
			var style = $item.attr('style');
			if(typeof style == "undefined" || !style) return $item.width();
			pct = parseInt(style.match(/width:\s*(\d+)/i)[1]);
		} else {
			pct = parseInt(pct);
		}
		// store the original width in another attribute, for later retrieval
		if(!$item.attr('data-original-width')) $item.attr('data-original-width', pct);
		// consoleLog('getWidth(' + $item.attr('id') + '): ' + pct + '%'); 
		return pct;
	}

	/**
	 * Retrieve the original width of $item
	 *
	 */
	function getOriginalWidth($item) {
		var w = parseInt($item.attr('data-original-width'));
		if(w == 0) w = getWidth($item);
		return w;
	}

	/**
	 * Set the width of $item to a given percent
	 *
	 * @param $item
	 * @param pct Percentage (10-100)
	 * @param animate Whether to animate the change (bool)
	 *
	 */
	function setWidth($item, pct, animate) {

		$item.width(pct + "%");

		if(animate) {
			$item.css('opacity', 0.5);
			$item.animate( { opacity: 1.0 }, 150, function() { });
		}

		consoleLog('setWidth(' + $item.attr('id') + ': ' + pct + '%');
	}

	function getHeight($item) {
		return $item.height();
	}

	function setHeight($item, maxColHeight) {
		var h = getHeight($item);
		consoleLog("setHeight: " + $item.find("label").text() + " >> " + maxColHeight + ' (' + h + ')'); 
		if(h == maxColHeight) return;
		if($item.hasClass('InputfieldStateCollapsed')) return;
		var pad = maxColHeight-h; 
		if(pad < 0) pad = 0;
		var $container = $item.children('.InputfieldContent, .ui-widget-content'); 
		if(pad == 0) {
			// do nothing, already the right height
		} else {
			consoleLog('Adjusting ' + $item.attr('id') + ' from ' + h + ' to ' + maxColHeight); 
			var $spacer = $("<div class='maxColHeightSpacer'></div>");
			$container.append($spacer);
			// $container.hide(); // removed per #124
			$spacer.height(pad);
			// $container.show(); // removed per #124
		}
	}
	
	function updateInputfieldRow($firstItem) {

		// find all columns in this row that aren't hidden
		// note that $items excludes $firstItem
		var $items = $firstItem.nextUntil('.InputfieldColumnWidthFirst', '.InputfieldColumnWidth:not(.InputfieldStateHidden)');
		var firstItemHidden = $firstItem.hasClass('InputfieldStateHidden');

		// initalize rowWidth with the width of the first item
		var rowWidth = firstItemHidden ? 0 : getWidth($firstItem);
		var $item = firstItemHidden ? null : $firstItem;
		var itemWidth = $item == null ? 0 : rowWidth;
		var numItems = $items.length;
		var $leadItem;

		if(firstItemHidden) {
			numItems--;
			// item that leads the list, even though it may not be the first (first could be hidden)
			$leadItem = $items.eq(0);
		} else {
			// lead item is first item
			$leadItem = $firstItem; 
		}

		if(useHeights) {
			// remove any spacers already present for adjusting height
			$leadItem.find(".maxColHeightSpacer").remove();
			$items.find(".maxColHeightSpacer").remove();
		}

		// subtract the quantity of items from the maxRowWidth since each item has a 1% margin
		var maxRowWidth = 100 - (numItems * colspacing);

		// keep track of the max column height
		var maxColHeight = useHeights ? getHeight($leadItem) : 0;

		// if our temporary class is in any of the items, remove it
		$items.removeClass("InputfieldColumnWidthFirstTmp");

		// determine the total row width
		// note that rowWidth is already initalized with the $firstItem width
		$items.each(function() {
			$item = $(this);
			itemWidth = getWidth($item);
			rowWidth += itemWidth;
			if(useHeights) {
				var h = getHeight($item);
				if (h > maxColHeight) maxColHeight = h;
			}
		});

		if(useHeights) {
			// ensure that all columns in the same row share the same height
			if(Inputfields.debug) {
				var lab = $leadItem.find("label").text();
				consoleLog('maxColHeight: ' + lab + ' = ' + maxColHeight);
			}

			if(maxColHeight > 0) {
				setHeight($leadItem, maxColHeight);
				$items.each(function() {
					setHeight($(this), maxColHeight);
				});
			}
		}
		
		var originalWidth = 0;
		var leftoverWidth = 0;

		// if the current rowWidth is less than the full width, expand the last item as needed to fill the row
		if(rowWidth < maxRowWidth) {
			consoleLog("Expand width of row because rowWidth < maxRowWidth (" + rowWidth + " < " + maxRowWidth + ')');
			leftoverWidth = (maxRowWidth - rowWidth);
			consoleLog('leftoverWidth: ' + leftoverWidth);
			itemWidth = itemWidth + leftoverWidth;
			if($item == null && !firstItemHidden) $item = $firstItem;
			if($item) {
				originalWidth = getOriginalWidth($item);
				// if the determined width is still less than the original width, then use the original width instead
				if(originalWidth > 0 && itemWidth < originalWidth) itemWidth = originalWidth;
				setWidth($item, itemWidth, true);
			}

		} else if(rowWidth > maxRowWidth) {
			// reduce width of row
			consoleLog("Reduce width of row because rowWidth > maxRowWidth (" + rowWidth + " > " + maxRowWidth + ')');
			if(!firstItemHidden) $items = $firstItem.add($items); // $items.add($firstItem);
			rowWidth = 0;
			$items.each(function() {
				// restore items in row to original width
				$item = $(this);
				itemWidth = getOriginalWidth($item);
				if(itemWidth > 0) setWidth($item, itemWidth, false);
				rowWidth += itemWidth;
			});
			// reduce width of last item as needed
			leftoverWidth = maxRowWidth - rowWidth;
			itemWidth += leftoverWidth;
			originalWidth = getOriginalWidth($item);
			if(originalWidth > 0 && itemWidth < originalWidth) itemWidth = originalWidth;
			setWidth($item, itemWidth, false);
		}

		if(firstItemHidden) {
			// If the first item is not part of the row, setup a temporary class to let the 
			// $leadItem behave in the same way as the first item
			$leadItem.addClass("InputfieldColumnWidthFirstTmp");
		}

	} // updateInputfield

	var numForms = 0;
	$target.each(function() {
		
		var $form = $(this);
		if(!$form.hasClass('InputfieldForm')) {
			var $_form = $form.closest('.InputfieldForm');
			if($_form.length) $form = $_form;
		}
		if($form.hasClass('InputfieldFormNoWidths')) {
			return; // column widths not necessary
		}
		
		colspacing = $form.attr('data-colspacing');
		if(typeof colspacing == 'undefined') {
			colspacing = 1;
		} else {
			colspacing = parseInt(colspacing);
		}
		
		// if no borders, we don't worry about keeping heights aligned since they won't be seen
		useHeights = $form.hasClass('InputfieldFormNoHeights') ? false : true;
		
		// for columns that don't have specific widths defined, add the InputfieldColumnWidthFirst
		// class to them which more easily enables us to exclude them from our operations below
		$(".Inputfield:not(.InputfieldColumnWidth)", $form).addClass("InputfieldColumnWidthFirst");

		// cycle through all first columns in a multi-column row
		$(".InputfieldColumnWidthFirst.InputfieldColumnWidth:visible", $form).each(function() {
			updateInputfieldRow($(this));
		});
		
		numForms++;
	});
	
	if(!numForms) {
		// no need to do anything further

	} else if(!$('body').hasClass('InputfieldColumnWidthsInit')) {
		// initialize monitoring events on first run
		$('body').addClass('InputfieldColumnWidthsInit');
		$(document).on('columnWidth', '.Inputfield', function(e, w) {
			setWidth($inputfield, w, $(this));
			return false;
		}); 

		/*
		var changeTimeout = null;
		var checkInputfieldHeightChange = function() {
			var $this = $(this);
			var checkNow = function() {
				var $item = $this.hasClass('InputfieldColumnWidth') ? $this : $this.closest('.InputfieldColumnWidth');
				var $firstItem = $item.hasClass('InputfieldColumnWidthFirst') ? $item : $item.prev(".InputfieldColumnWidthFirst");
				if($firstItem.length) updateInputfieldRow($firstItem);
			}
			if($this.is(':input')) {
				if(changeTimeout) clearTimeout(changeTimeout);
				changeTimeout = setTimeout(checkNow, 1000);
			} else {
				checkNow();	
			}
		};
	
		$(document).on('change', '.InputfieldColumnWidth :input', checkInputfieldHeightChange);
		$(document).on('AjaxUploadDone', '.InputfieldFileList', checkInputfieldHeightChange);
		$(document).on('heightChanged', '.InputfieldColumnWidth', checkInputfieldHeightChange);
		*/
	} 
}

/**
 * Event to call before a page is unloaded (beforeunload event)
 * 
 * If window should not be unloaded, it returns a string of text with reason why.
 * 
 */
function InputfieldFormBeforeUnloadEvent(e) {
	var $ = jQuery;
	var $changes = $(".InputfieldFormConfirm:not(.InputfieldFormSubmitted) .InputfieldStateChanged");
	if($changes.length == 0) return;
	var msg = $('.InputfieldFormConfirm:eq(0)').attr('data-confirm') + "\n";
	$changes.each(function() {
		var $header = $(this).find(".InputfieldHeader:eq(0)");
		if($header.length) {
			name = $header.text();
		} else {
			name = $(this).attr('data-label');
			if(!name || !name.length) {
				name = $(this).find(':input').attr('name');
			}
		}
		if(name.length) msg += "\n• " + $.trim(name);
	});
	(e || window.event).returnValue = msg; // Gecko and Trident
	return msg; // Gecko and WebKit
}

/**
 * Focus the Inputfield
 * 
 * If the Inputfield is not visible, this method will track it down and reveal it. 
 * If the Inputfield is collapsed, this method will open it. 
 * If the Inputfield has an input that can be focused, it will be focused. 
 * If the Inputfield does not support focus, it will just be ensured to be visible.
 * 
 * @param $inputfield
 * @param function completedCallback Optional callback function to call at completion (since 3.0.144)
 * 
 */
function InputfieldFocus($inputfield, completedCallback) {
	Inputfields.focus($inputfield, completedCallback); 
}

/**
 * Toggle the given $inputfield open or closed
 * 
 * Also triggers these events on $inputfield: openReady, closeReady, opened, closed
 * 
 * @param $inputfield Inputfield to toggle, or some element within it
 * @param open Boolean true to open, false to close, or null (or omit) for opposite of current state (default=opposite of current state)
 * @param duration How many milliseconds for animation? (default=100)
 * @param completedCallback Optional function to call upon completion, receives 3 arguments: $inputfield, open and duration 
 * @returns Returns Inputfield element
 * 
 */
function InputfieldToggle($inputfield, open, duration, completedCallback) {
	Inputfields.toggle($inputfield, open, duration, completedCallback); 	
}

/**
 * Open a collapsed Inputfield
 * 
 * @param $inputfield
 * @param duration Optional number of milliseconds for animation or 0 for none (default=100)
 * 
 */
function InputfieldOpen($inputfield, duration) {
	Inputfields.toggle($inputfield, true, duration);
}

/**
 * Close/collapse an open Inputfield
 * 
 * @param $inputfield
 * @param duration Optional number of milliseconds for animation or 0 for none (default=100)
 * 
 */
function InputfieldClose($inputfield, duration) {
	Inputfields.toggle($inputfield, false, duration);
}

/*****************************************************************************************************
 * Setup the toggles for Inputfields and the animations that occur between opening and closing
 *
 */
function InputfieldStates($target) {

	var hasTarget = true;
	var $ = jQuery;
	
	if(typeof $target == "undefined") {
		$target = $("body");
		hasTarget = false;
	}
	
	function InputfieldStateAjaxClick($li) {
		
		function headerHighlightEffect($header, $li) {
			
			var $spinner = $("<i class='fa fa-spin fa-spinner'></i>");
			var offset = $header.offset();
			var interval;
			var maxRuns = 10;
			var runs = 0;
			var hAdjust = 0.8;
			
			$("body").append($spinner.hide());
			
			if($header.is('a') && $header.closest('ul').hasClass('uk-tab')) hAdjust = 0.1;
			
			$spinner.css({
				position: 'absolute',	
				top: offset.top - ($spinner.height() + 5),
				left: offset.left + ($header.width() / 2) + ($spinner.width() * hAdjust) 
			}).fadeIn();
			
			interval = setInterval(function() {
				if(++runs > maxRuns || !$li.hasClass('InputfieldAjaxLoading')) {
					clearInterval(interval);
					$spinner.fadeOut('normal', function() {
						$spinner.remove();
					});
				}
			}, 500);
		}
		
		// check for ajax rendered Inputfields
		var $parent = $li.children('.InputfieldContent').children('.renderInputfieldAjax');
		var isTab = false;
		if(!$parent.length) {
			$parent = $li.children('.renderInputfieldAjax'); // WireTab
			isTab = true; 
		}
		
		var ajaxURL = $parent.children('input').attr('value');
		if(typeof ajaxURL == "undefined" || ajaxURL.length < 1) return false;
		var $spinner = null;
		var $header;
		
		if(isTab) {
			$header = $('#_' + $li.attr('id')); // WireTab
			headerHighlightEffect($header, $li);
		} else {
			$header = $li.children(".InputfieldHeader");
			$spinner = $("<i class='fa fa-spin fa-spinner'></i>");
			$spinner.css('margin-left', '0.5em');
			$header.append($spinner);
		}

		$li.removeClass('collapsed10 collapsed11').addClass('InputfieldAjaxLoading');
		
		$.get(ajaxURL, function(data) {
			$li.removeClass('InputfieldAjaxLoading InputfieldStateCollapsed');
			var $icon = $li.children('.InputfieldHeader').find('.toggle-icon'); 
			if($icon.length) $icon.toggleClass($icon.attr('data-to'));
			$parent.replaceWith($(data)).hide();
			$parent.slideDown();
			var $inputfields = $li.find('.Inputfield');
			if($inputfields.length) {
				$inputfields.trigger('reloaded', [ 'InputfieldAjaxLoad' ]);
				InputfieldStates($li);	
				InputfieldRequirements($li);
				InputfieldColumnWidths();
			} else {
				$li.trigger('reloaded', [ 'InputfieldAjaxLoad' ]);
				InputfieldColumnWidths();
			}
			if($li.closest('.InputfieldFormNoDependencies').length == 0) {
				InputfieldDependencies($li.parent());
			}
			setTimeout(function() {
				if($spinner) $spinner.fadeOut('fast', function() {
					$spinner.remove();
				});
				if(isTab) {
					$header.effect('highlight', 500);
				} else if(Inputfields.toggleBehavior < 1) {
					$header.click();
				}
			}, 500);
		}, 'html');
		
		return true;
	}
	
	$(".Inputfield:not(.collapsed9) > .InputfieldHeader, .Inputfield:not(.collapsed9) > .ui-widget-header", $target)
		.addClass("InputfieldStateToggle");
	
	// use different icon for open and closed
	var $icon = $(".Inputfields .InputfieldStateCollapsed > .InputfieldHeader i.toggle-icon, .Inputfields .InputfieldStateCollapsed > .ui-widget-header i.toggle-icon", $target);
	$icon.toggleClass($icon.attr('data-to'));
	
	// display a detail with the HTML field name when the toggle icon is hovered
	if(typeof ProcessWire != "undefined") {
		var config = ProcessWire.config;
	} 
	if(typeof config !== "undefined" && config.debug) {
		$('.InputfieldHeader > i.toggle-icon', $target).hover(function() {
			var $label = $(this).parent('label');
			if($label.length == 0) return;
			var forId = $label.attr('for');
			if(!forId) forId = $label.parent().attr('id');
			if(!forId) return;
			var text = forId.replace(/^Inputfield_|wrap_Inputfield_|wrap_/, '');
			if(text.length) {
				var $tip = $("<small class='InputfieldNameTip ui-priority-secondary'>&nbsp;" + text + "&nbsp;</small>");
				$tip.css('float', 'right');
				$label.append($tip);
			}

		}, function() {
			var $label = $(this).parent('label');
			if($label.length == 0) return;
			$label.find('.InputfieldNameTip').remove();
		});
	}

	// no need to apply anything further for ajax-loaded inputfields
	if(hasTarget) return; 

	$(document).on('wiretabclick', function(e, $newTab, $oldTab) {
		if($newTab.hasClass('collapsed10')) InputfieldStateAjaxClick($newTab);
	});
	
	$(document).on('click', '.InputfieldStateToggle, .toggle-icon', function(event, data) {
		
		var $t = $(this);
		var $li = $t.closest('.Inputfield');
		var isIcon = $t.hasClass('toggle-icon');
		var $icon = isIcon ? $t : $li.children('.InputfieldHeader, .ui-widget-header').find('.toggle-icon'); 
		var isCollapsed = $li.hasClass("InputfieldStateCollapsed"); 
		var wasCollapsed = $li.hasClass("InputfieldStateWasCollapsed");
		var duration = 100;
		var isAjax = $li.hasClass('collapsed10') || $li.hasClass('collapsed11');
	
		if(!$li.length) return;
		if($li.hasClass('InputfieldAjaxLoading')) return false;
		if($li.hasClass('InputfieldStateToggling')) return false;
		
		if(typeof data != "undefined") {
			if(typeof data.duration != "undefined") duration = data.duration;
		}

		if(isCollapsed && isAjax) {	
			if(InputfieldStateAjaxClick($li)) return false;
		}
			
		if(isCollapsed || wasCollapsed || isIcon) {
			$li.addClass('InputfieldStateWasCollapsed'); // this class only used here
			Inputfields.toggle($li, null, duration);
		} else if(Inputfields.toggleBehavior === 1) {
			// open/close implied by header label click
			$icon.click();
		} else {
			// Inputfield not collapsible unless toggle icon clicked, so pulsate the toggle icon and focus any inputs
			if(typeof jQuery.ui != 'undefined') {
				var color1 = $icon.css('color');
				var color2 = $li.children('.InputfieldHeader, .ui-widget-header').css('color'); 
				$icon.css('color', color2);
				$icon.effect('pulsate', 300, function () {
					$icon.css('color', color1);
				});
			}
			Inputfields.focus($li);
		}

		return false;
	});

	 // Make the first field in any form have focus, if it is a text field that is blank
	var $focusInputs = $('input.InputfieldFocusFirst'); // input elements only
	if(!$focusInputs.length) {
		$focusInputs = $('#content .InputfieldFormFocusFirst:not(.InputfieldFormNoFocus)')
			.find('input[type=text]:enabled:first:not(.hasDatepicker):not(.InputfieldNoFocus)');
	}
	if($focusInputs.length) $focusInputs.each(function() {
		var $t = $(this);
		// jump to first input, if it happens to be blank
		if($t.val()) return;
		// avoid jumping to inputs that fall "below the fold"
		if($t.offset().top < $(window).height()) {
			window.setTimeout(function () {
				if($t.is(":visible")) $t.focus();
			}, 250);
		}
	});

	// confirm changed forms that user navigates away from before submitting
	$(document).on('change', '.InputfieldForm :input, .InputfieldForm .Inputfield', function() {
		var $this = $(this);
		if($this.hasClass('Inputfield')) {
			// an .Inputfield element
			if($this.hasClass('InputfieldIgnoreChanges')) return false;
			$this.addClass('InputfieldStateChanged').trigger('changed');
			// .InputfieldFormConfirm forms stop change at parent .Inputfield element
			if($this.closest('.InputfieldFormConfirm').length > 0) return false;
		} else {
			// an :input element
			if($this.hasClass('InputfieldIgnoreChanges') || $this.closest('.InputfieldIgnoreChanges').length) return false;
			$this.closest('.Inputfield').addClass('InputfieldStateChanged').trigger('changed');
		}
	});

	$(document).on('submit', '.InputfieldFormConfirm', function() {
		$(this).addClass('InputfieldFormSubmitted');
	});

	// open Inputfields supporting uploads when file dragged in, per @Toutouwai #242
	$(document).on('dragenter', '.InputfieldHasUpload.InputfieldStateCollapsed', function(e) {
		var dt = e.originalEvent.dataTransfer;
		if(dt.types && (dt.types.indexOf ? dt.types.indexOf('Files') !== -1 : dt.types.contains('Files'))) {
			InputfieldOpen($(this));
		}
	});
	
	window.addEventListener("beforeunload", InputfieldFormBeforeUnloadEvent);
}

/*********************************************************************************************
	
function overflowAdjustments() {
	// ensures an overflow-y scroll is set when the content height is less than window height
	// this makes a permanently visible scrollbar, preventing jumps in ui-menus
	var $body = $("body"); 
	var documentHeight = $(document).height();
	var windowHeight = $(window).height();
	
	consoleLog('documentHeight=' + documentHeight + ', windowHeight=' + windowHeight); 

	if(documentHeight > windowHeight) {
		// there is already a scrollbar
		if($body.css('overflow-y') == 'scroll') {
			$body.css('overflow-y', 'visible');
			consoleLog("Setting overflow-y to visible"); 
		}
	} else {
		// force a scrollbar
		$body.css('overflow-y', 'scroll');
		consoleLog("Setting overflow-y to scroll"); 
	}
}
 
 *********************************************************************************************/

/***********************************************************************************************
 * Adjustments for unintended actions, like hitting enter in a text field in a multi-button form
 * 
 */
function InputfieldIntentions() {
	var $ = jQuery;
	
	// adjustments for unintended actions, like hitting enter in a text field in a multi-button form
	$(".InputfieldForm").each(function() {
		var $form = $(this); 
		var numButtons = null;
		var $input = null;
		
		$form.submit(function() {
			if(!$(this).hasClass('nosubmit')) return;
			if(!$input) return;
			
			var $buttons = null;
			var $inputfields = $input.closest(".Inputfields"); 
			
			do {
				// find nearest visible submit button
				$buttons = $inputfields.find("input[type=submit]:visible, button[type=submit]:visible"); 
				if($buttons.length > 0) break;
				$inputfields = $inputfields.parent().closest(".Inputfields"); 
			} while($inputfields.length > 0);

			// scroll to first found button and focus it
			if($buttons.length > 0) {
				var $button = $buttons.eq(0);
				$('html, body').animate({ scrollTop: $button.offset().top }, 'fast');
				$button.focus();
			}
			
			return false;
			
		}).on("focus", "input, select", function() {
			// if more than 1 submit button, prevent form submission while text input or select is focused
			if(numButtons === null) numButtons = $form.find("input[type=submit], button[type=submit]").length;
			if(numButtons < 2) return;
			$form.addClass('nosubmit');
			$input = $(this); 
				
		}).on("blur", "input, select", function() {
			// allow submissions again once they are out of the field
			$form.removeClass('nosubmit');
		}); 
	});

	// prevent dragged in files from loading in the browser (does not interfere with other drag/drop handlers)
	if($("input[type=file]").length) {
		$(document).on({
			dragover: function() {
				if($(this).is("input[type=file]")) return;
				return false;
			},
			drop: function() {
				if($(this).is("input[type=file]")) return;
				return false;
			}
		});
	}
}

/***********************************************************************************/

var InputfieldWindowResizeQueued = false;

function InputfieldWindowResizeActions1() {
	consoleLog('InputfieldWindowResizeActions1()');
	// notify all Inputfields that they have been resized
	// note: event is not triggered at ready() so Inputfield should trigger its own
	// resize event if it needs this as part of setup
	jQuery(".Inputfield").trigger('resized');
}

function InputfieldWindowResizeActions2() {
	consoleLog('InputfieldWindowResizeActions2()');
	InputfieldColumnWidths();
	InputfieldWindowResizeQueued = false;
}

/**
 * Manage required attributes on Inputfields
 * 
 */
function InputfieldRequirements($target) {
	// show tab that input[required] is in, via @Toutouwai 
	jQuery(':input[required]', $target).on('invalid', function() {
		var $input = jQuery(this);
		Inputfields.focus($input);
	});
}

/**
 * Event handler called when 'reload' event is triggered on an Inputfield
 * 
 */
function InputfieldReloadEvent(event, extraData) {
	var $t = $(this);
	var $form = $t.closest('form');
	var fieldName = $t.attr('id').replace('wrap_Inputfield_', '');
	var fnsx = ''; // field name suffix
	var url = $form.attr('action');
	if(fieldName.indexOf('_repeater') > 0) {
		var $repeaterItem = $t.closest('.InputfieldRepeaterItem'); 
		var pageID = $repeaterItem.attr('data-page');
		url = url.replace(/\?id=\d+/, '?id=' + pageID);
		fnsx = $repeaterItem.attr('data-fnsx');
		fieldName = fieldName.replace(/_repeater\d+$/, '');
	}
	url += url.indexOf('?') > -1 ? '&' : '?';
	url += 'field=' + fieldName + '&reloadInputfieldAjax=' + fieldName;
	if(fnsx.length) url += '&fnsx=' + fnsx;
	if(typeof extraData != "undefined") {
		if(typeof extraData['queryString'] != "undefined") {
			url += '&' + extraData['queryString'];
		}
	}
	consoleLog('Inputfield reload: ' + fieldName);
	$.get(url, function(data) {
		var id = $t.attr('id');
		var $content;
		if(data.indexOf('{') === 0) {
			data = JSON.parse(data);
			console.log(data);
			$content = '';
		} else {
			$content = jQuery(data).find("#" + id).children(".InputfieldContent");
			if(!$content.length && id.indexOf('_repeater') > -1) {
				id = 'wrap_Inputfield_' + fieldName;
				$content = jQuery(data).find("#" + id).children(".InputfieldContent");
				if(!$content.length) {
					console.log("Unable to find #" + $t.attr('id') + " in response from " + url);
				}
			}
		}
		if($content.length) {
			$t.children(".InputfieldContent").html($content.html());
			//InputfieldStates($t);
			InputfieldsInit($t);
			$t.trigger('reloaded', ['reload']);
		}
	});
	event.stopPropagation();
}

/**
 * Function for external initialization of new Inputfields content (perhaps ajax loaded)
 * 
 */
function InputfieldsInit($target) {
	InputfieldStates($target);
	InputfieldDependencies($target);
	InputfieldRequirements($target);
	setTimeout(function() { InputfieldColumnWidths(); }, 100);
}

/***********************************************************************************/

jQuery(document).ready(function($) {
	
	InputfieldStates();
	
	InputfieldDependencies($(".InputfieldForm:not(.InputfieldFormNoDependencies)"));
	
	InputfieldIntentions();
	
	setTimeout(function() { InputfieldColumnWidths(); }, 100);
	
	var windowResized = function() {
		if(InputfieldWindowResizeQueued) return;
		InputfieldWindowResizeQueued = true;
		setTimeout('InputfieldWindowResizeActions1()', 1000);
		setTimeout('InputfieldWindowResizeActions2()', 2000); 	
	};

	$(window).resize(windowResized);
	
	$("ul.WireTabs > li > a").click(function() {
		if(InputfieldWindowResizeQueued) return;
		InputfieldWindowResizeQueued = true;
		setTimeout('InputfieldWindowResizeActions1()', 250);
		setTimeout('InputfieldWindowResizeActions2()', 500);
		return true;
	}); 
	
	InputfieldRequirements($('.InputfieldForm'));

	$(document).on('reload', '.Inputfield', InputfieldReloadEvent);
	
	
	if($('.InputfieldForm:not(.InputfieldFormNoWidths)').length) {
		$(document).on('change', '.InputfieldColumnWidth :input', function() {
			InputfieldColumnWidths(); // For fields with immediate height change (e.g. AsmSelect)
			setTimeout(InputfieldColumnWidths, 300); // For fields with delayed height change (e.g. Files delete)
		});
		$(document).on('AjaxUploadDone', '.InputfieldFileList', function() {
			InputfieldColumnWidths();
		});
		$(document).on('heightChanged', '.InputfieldColumnWidth', function() {
			// deprecated, no longer used (?)
			InputfieldColumnWidths();
		});
	}

	if(window.location.hash) {
		Inputfields.hashAction(window.location.hash.substring(1));
	}

	/*
	// for testing: 
	$(document).on('reloaded', '.Inputfield', function(event) {
		console.log($(this).attr('id'));
	});
	*/
}); 
