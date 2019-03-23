
/*****************************************************************************************************************
 * ProcessWire InputfieldImage
 * 
 * Copyright 2017 by ProcessWire
 * 
 */
function InputfieldImage($) {
	
	// When uploading a file in place: .gridItem that file(s) will be placed before
	var $uploadBeforeItem = null;

	// When replacing a file: .gridImage item that is being replaced
	var uploadReplace = {
		file: '',	// basename of file being replaced
		item: null, // the .gridImage item that is being replaced
		edit: null // the .InputfieldImageEdit.gridImage item (edit panel) that is being replaced
	};
	
	// Base options for MagnificPopup
	var magnificOptions = {
		type: 'image',
		closeOnContentClick: true,
		closeBtnInside: true
	};

	// data pulled from InputfieldImage cookie
	var cookieData = null;
	
	// grid items to retry for sizing by setGridSize() methods	
	var retryGridItems = []; // i.e. [ { item: $item, gridSize: 123 } ]

	// true when the grid is being resized with the slider
	var gridSliding = false;

	/**
	 * Whether or not AJAX drag/drop upload is allowed?
	 * 
	 * @returns bool
	 */
	function useAjaxUpload() {
		var isFileReaderSupport = window.File && window.FileList && window.FileReader;
		var isAjaxUpload = $('.InputfieldAllowAjaxUpload').length > 0;
		var isPageIDIndicator = $("#PageIDIndicator").length > 0;
		return (isFileReaderSupport && (isPageIDIndicator || isAjaxUpload));
	}

	/**
	 * Throttle the given function for a threshold
	 *
	 * @param fn
	 * @param threshhold
	 * @param scope
	 * @returns {Function}
	 *
	 */
	function throttle(fn, threshhold, scope) {
		threshhold || (threshhold = 250);
		var last, deferTimer;
		return function() {
			var context = scope || this;
			var now = +new Date(), args = arguments;
			if(last && now < last + threshhold) {
				clearTimeout(deferTimer);
				deferTimer = setTimeout(function() {
					last = now;
					fn.apply(context, args);
				}, threshhold);
			} else {
				last = now;
				fn.apply(context, args);
			}
		};
	}

	/**
	 * Helper function for inversing state of checkboxes
	 *
	 * @param index
	 * @param old
	 * @returns {boolean}
	 *
	 */
	function inverseState(index, old) {
		return !old;
	}

	/**
	 * Make an element sortable
	 *
	 * @param $el
	 *
	 */
	function setupSortable($el) {
		if($el.hasClass('ui-sortable')) {
			$el.sortable('destroy');
			// re-build sort indexes
			$el.children("li").each(function(n) {
				var $sort = $(this).find("input.InputfieldFileSort");
				$sort.val(n);
			});
		}
		var timer;
		var sortableOptions = {
			items: "> .gridImage",
			start: function(e, ui) {
				var size = getCookieData($el.closest('.Inputfield'), 'size');
				ui.placeholder.append($("<div/>").css({
					display: "block",
					height: size + "px",
					width: size + "px"
				}));
				// Prevent closing, if this really meant to be a click
				timer = window.setTimeout(function() {
					closeEdit($el, null);
				}, 100);
				$el.addClass('InputfieldImageSorting');
			},
			stop: function(e, ui) {
				var $this = $(this);
				if(timer !== null) {
					ui.item.find(".InputfieldImageEdit__edit").click();
					clearTimeout(timer);
				}
				$this.children("li").each(function(n) {
					var $sort = $(this).find(".InputfieldFileSort");
					if($sort.val() != n) $sort.val(n).change();
				});
				$el.removeClass('InputfieldImageSorting');
			},
			cancel: ".InputfieldImageEdit,.focusArea,input,textarea,button,select,option"
		};

		$el.sortable(sortableOptions);
	}

	/**
	 * Setup MagnificPopup plugin for renderValue mode
	 *
	 * @param $el
	 *
	 */
	function setupMagnificForRenderValue($el) {
		var options = $.extend(true, {}, magnificOptions);
		options.callbacks = {
			elementParse: function(item) {
				var src = $(item.el).attr('data-original');
				if(typeof src == "undefined" || !src) src = $(item.el).attr('src');
				item.src = src;
			}
		};
		options.gallery = {
			enabled: true
		};
		//console.log('setupMagnificForRenderValue');
		$el.find("img").magnificPopup(options);
	}
	
	/**
	 * Setup MagnificPopup plugin for Single mode
	 *
	 * @param $el
	 *
	 */
	function setupMagnificForSingle($el) {
		var options = $.extend(true, {}, magnificOptions);
		options.callbacks = {
			elementParse: function(item) {
				item.src = $(item.el).attr('src');
			}
		};
		options.gallery = {
			enabled: false
		};
		$el.find("img").magnificPopup(options);
	}

	/**
	 * Manage image per row issues
	 *
	 * @param $parent
	 * @returns {*}
	 *
	 */
	function findEditedElement($parent) {
		return $parent.find(".InputfieldImageEdit--active");
	}

	/**
	 * Return the &__edit element inside an .InputfieldImageEdit 
	 *
	 * @param $edit
	 *
	 */
	function findEditMarkup($edit) {
		return $("#" + $edit.find(".InputfieldImageEdit__edit").attr("data-current"));
	}

	/**
	 * Sets the checkbox delete state of all items to have the same as that of $input
	 *
	 * @param $input
	 *
	 */
	function setDeleteStateOnAllItems($input) {
		var checked = $input.is(":checked");
		var $items = $input.parents('.gridImages').find('.gridImage__deletebox');
		if(checked) {
			$items.prop("checked", "checked").change();
		} else {
			$items.removeAttr("checked").change();
		}
	}

	/**
	 * Update the grid of images, adjusting placement of editor and number of images per row
	 *
	 */
	function updateGrid($inputfield) {
		if(typeof $inputfield == "undefined") {
			var $gridImages = $(".gridImages");
		} else {
			var $gridImages = $inputfield.find(".gridImages");
		}
		$gridImages.each(function() {
			var $grid = $(this),
				$edit = findEditedElement($grid);
			if($edit.length) {
				// getImagesPerRow($grid);
				moveEdit(findEditMarkup($edit), $edit);
			}
		});
	};
	
	function checkInputfieldWidth($inputfield) {
		
		var narrowItems = [];
		var mediumItems = [];
		var wideItems = [];
		var ni = 0, mi = 0, wi = 0;
		var $inputfields;
	
		if(typeof $inputfield == "undefined") {
			$inputfields = $(".InputfieldImage.Inputfield");
		} else {
			$inputfields = $inputfield;
		}
	
		$inputfields.removeClass('InputfieldImageNarrow InputfieldImageMedium InputfieldImageWide');
		
		$inputfields.each(function() {
			var $item = $(this);
			var width = $item.width();
			if(width < 1) return;
			if(width <= 500) {
				narrowItems[ni] = $item;
				ni++;
			} else if(width <= 900) {
				mediumItems[mi] = $item;
				mi++;
			} else {
				wideItems[wi] = $item;
				wi++;
			}
		});
		
		for(var n = 0; n < ni; n++) {
			var $item = narrowItems[n];	
			$item.addClass('InputfieldImageNarrow');
		}
		for(var n = 0; n < mi; n++) {
			var $item = mediumItems[n];
			$item.addClass('InputfieldImageMedium');
		}
		for(var n = 0; n < wi; n++) {
			var $item = wideItems[n];
			$item.addClass('InputfieldImageWide');
		}
	}

	/**
	 * Window resize event
	 * 
	 */
	function windowResize() {
		$('.focusArea.focusActive').each(function() {
			var $edit = $(this).closest('.InputfieldImageEdit, .gridImage'); 
			if($edit.length) stopFocus($edit);
		}); 
		updateGrid();
		checkInputfieldWidth();
	}

	/**
	 * Updates outer class of item to match that of its "delete" checkbox
	 *
	 * @param $checkbox
	 *
	 */
	function updateDeleteClass($checkbox) {
		if($checkbox.is(":checked")) {
			$checkbox.parents('.ImageOuter').addClass("gridImage--delete");
		} else {
			$checkbox.parents('.ImageOuter').removeClass("gridImage--delete");
		}
	}

	/**
	 * 
	 *
	 * @param $el
	 *
	function markForDeletion($el) {
		$el.parents('.gridImage').toggleClass("gridImage--delete");
		$el.find("input").prop("checked", inverseState);
	}
	 */

	/**
	 * Setup the InputfieldImageEdit panel
	 *
	 * @param $el
	 * @param $edit
	 *
	 */
	function setupEdit($el, $edit) {
		
		if($el.closest('.InputfieldImageEditAll').length) return; // edit all mode
		
		var $img = $edit.find(".InputfieldImageEdit__image");
		var $thumb = $el.find("img");
		
		$img.attr({
			src: $thumb.attr("data-original"),
			"data-original": $thumb.attr("data-original"),
			alt: $thumb.attr("alt")
		});

		var options = $.extend(true, {}, magnificOptions);
		options.callbacks = {
			elementParse: function(item) {
				item.src = $(item.el).attr('data-original');
			}
		};
		options.gallery = {
			enabled: true
		};
		
		$edit.attr('id', 'edit-' + $el.attr('id'));
		// this part creates a gallery of all images
		var $items = $edit.parents(".gridImages")
			.find(".gridImage")
			.not($el)
			.find("img")
			.add($img)
			.magnificPopup(options);

		// move all of the .ImageData elements to the edit panel
		$edit.find(".InputfieldImageEdit__edit")
			.attr("data-current", $el.attr("id"))
			.append($el.find(".ImageData").children().not(".InputfieldFileSort"));
	}

	/**
	 * Setup image for a draggable focus area and optional zoom slider
	 * 
	 * @param $edit Image editor container (.InputfieldImageEdit or .gridImage)
	 * 
	 */
	function startFocus($edit) {
		
		var $img, $el, $thumb, $input, $focusArea, $focusCircle, $inputfield, 
			focusData = null, gridSize, mode, $zoomSlider, $zoomBox, lastZoomPercent = 0,
			useZoomFocus = false;
		
		$inputfield = $edit.closest('.Inputfield');
		gridSize = getCookieData($inputfield, 'size');
		mode = getCookieData($inputfield, 'mode');
		
		if($inputfield.hasClass('InputfieldImageFocusZoom')) useZoomFocus = true;
		
		if($edit.hasClass('gridImage')) {
			// list mode
			$el = $edit;
			$img = $edit.find('.gridImage__overflow').find('img');
			$thumb = $img;
		} else {
			// thumbnail click for editor mode
			$el = $('#' + $edit.attr('data-for'));
			$img = $edit.find('.InputfieldImageEdit__image');
			$thumb = $el.find('.gridImage__overflow').find('img');
		}
	
		// get the focus object, optionally for a specific focusStr
		function getFocus(focusStr) {
			
			if(typeof focusStr == "undefined") {
				if(focusData !== null) return focusData;
				var $input = $edit.find('.InputfieldImageFocus');
				var focusStr = $input.val();
			}
			
			var a = focusStr.split(' ');
			var top =  (typeof a[0] == "undefined" ? 50.0 : parseFloat(a[0]));
			var left = (typeof a[1] == "undefined" ? 50.0 : parseFloat(a[1]));
			var zoom = (typeof a[2] == "undefined" ? 0 : parseInt(a[2]));
			
			focusData = {
				'top': top > 100 ? 100 : top, 
				'left': left > 100 ? 100 : left, 
				'zoom': zoom > 100 ? 0 : zoom
			};
			
			return focusData;
		}
	
		// get focus string
		function getFocusStr(focusObj) {
			if(typeof focusObj == "undefined") focusObj = getFocus();
			return focusObj.top + ' ' + focusObj.left + ' ' + focusObj.zoom;
		}
	
		// get single focus property: top left or zoom
		function getFocusProperty(property) {
			var focus = getFocus();	
			return focus[property];
		}

		// set focus for top left and zoom
		function setFocus(focusObj) {
			focusData = focusObj;
			var focusStr = focusObj.top + ' ' + focusObj.left + ' ' + focusObj.zoom;
			$thumb.attr('data-focus', focusStr); // for consumption outside startFocus()
			$input = $edit.find('.InputfieldImageFocus');
			if(focusStr != $input.val()) {
				$input.val(focusStr).trigger('change');
			}
		}

		// set just one focus property (top, left or zoom)
		function setFocusProperty(property, value) {
			var focus = getFocus();
			focus[property] = value;
			setFocus(focus);
		}
		
		 // Set the position of the draggable focus item
		function setFocusDragPosition() {
			var focus = getFocus();
			var $overlay = $focusCircle.parent();
			var w = $overlay.width();
			var h = $overlay.height();
			var x = Math.round((focus.left / 100) * w);
			var y = Math.round((focus.top / 100) * h);

			if(x < 0) x = 0;
			if(y < 0) y = 0;
			if(x > w) x = w; // horst: just to be on the safe side with following or actual code changes
			if(y > h) y = h; 
			
			$focusCircle.css({
				'top': y + 'px',
				'left': x + 'px'
			});
		}
	
		// setup focus area (div that contains all the focus stuff)
		$focusArea = $img.siblings('.focusArea'); 
		if(!$focusArea.length) {
			$focusArea = $('<div />').addClass('focusArea');
			$img.after($focusArea);
		}
		$focusArea.css({
			'height': $img.height() + 'px',
			'width': $img.width() + 'px',
			'background-color': 'rgba(0,0,0,0.7)'
		}).addClass('focusActive');

		// set the draggable circle for focus
		$focusCircle = $focusArea.find('.focusCircle'); 
		if(!$focusCircle.length) {
			$focusCircle = $("<div />").addClass('focusCircle');
			$focusArea.append($focusCircle);
		}
	
		// indicate active state for focusing, used by stopFocus()
		$img.parent().addClass('focusWrap');
	
		// set the initial position for the focus circle 
		setFocusDragPosition();
	
		// function called whenever the slider is moved or circle is dragged with zoom active
		var zoomSlide = function(zoomPercent) {

			//var zoomBoxSize, focusCircleSize, focus, top, left, scale, faWidth, faHeight;
			var zoomBoxSize, focus, faWidth, faHeight;

			// if no zoomPercent argument provided, use the last one
			if(typeof zoomPercent == "undefined") zoomPercent = lastZoomPercent;
			lastZoomPercent = zoomPercent;
			faWidth = $focusArea.width();
			faHeight = $focusArea.height();

			if(faWidth > faHeight) {
				$zoomBox.height((100 - zoomPercent) + '%'); // set width in percent
				zoomBoxSize = $zoomBox.height(); // get width in pixels
				$zoomBox.width(zoomBoxSize); // match width to ensure square zoom box
			} else {
				$zoomBox.width((100 - zoomPercent) + '%'); // set width in percent
				zoomBoxSize = $zoomBox.width(); // get width in pixels
				$zoomBox.height(zoomBoxSize); // match width to ensure square zoom box
			}

			// apply the zoom box position
			focus = getFocus();
			var crop = getFocusZoomCropDimensions(focus.left, focus.top, zoomPercent, faWidth, faHeight, zoomBoxSize);
			$zoomBox.css({
				'top': crop.top + 'px',
				'left': crop.left + 'px',
				'background-position': '-' + crop.left + 'px -' + crop.top + 'px',
				'background-size': faWidth + 'px ' + faHeight + 'px'
			});

			// save zoom percent
			focus.zoom = zoomPercent;
			setFocusProperty('zoom', focus.zoom);

			// update the preview if in gride mode
			if(mode == 'grid') setGridSizeItem($thumb.parent(), gridSize, false, focus);

		}; // zoomSlide
	
		// function called when the focus item is dragged
		var dragEvent = function(event, ui) {
			var $this = $(this);
			var circleSize = $this.outerHeight();
			var w = $this.parent().width();
			var h = $this.parent().height();
			var top = ui.position.top > 0 ? ui.position.top : 0;
			var left = ui.position.left > 0 ? ui.position.left : 0;
			top = top > 0 ? ((top / h) * 100) : 0;
			left = left > 0 ? ((left / w) * 100) : 0;
			var newFocus = {
				'top': top,
				'left': left,
				'zoom': getFocusProperty('zoom')
			};
			setFocus(newFocus);
			if(useZoomFocus) {
				zoomSlide(newFocus.zoom);
			} else if(mode == 'grid') {
				setGridSizeItem($thumb.parent(), gridSize, false, newFocus);
			}
		}; // dragEvent
	
		// make draggable and attach events
		$focusCircle.draggable({
			containment: 'parent',
			drag: dragEvent,
			stop: dragEvent
		});

		if(useZoomFocus) {
			// setup the focus zoom slider
			var zoom = getFocusProperty('zoom');
			$zoomSlider = $("<div />").addClass('focusZoomSlider').css({
				'margin-top': '5px'
			});

			$zoomBox = $("<div />").addClass('focusZoomBox').css({
				'position': 'absolute',
				'background': 'transparent',
				'background-image': 'url(' + $img.attr('src') + ')'
			});
			
			$focusArea.prepend($zoomBox);
			$img.after($zoomSlider);
			$thumb.attr('src', $img.attr('src'));
			$zoomSlider.slider({
				min: 0,
				max: 50,
				value: zoom,
				range: 'max',
				slide: function(event, ui) {
					zoomSlide(ui.value); 
				}
			});
			zoomSlide(zoom);
		} else {
			$focusArea.css('background-color', 'rgba(0,0,0,0.5)'); 
		}

	}
	
	function stopFocus($edit) {
		$focusCircle = $edit.find('.focusCircle');
		if($focusCircle.length) {
			var $focusWrap = $focusCircle.closest('.focusWrap');
			$focusWrap.find('.focusZoomSlider').slider('destroy').remove();
			$focusWrap.find('.focusZoomBox').remove();
			$focusWrap.removeClass('focusWrap');
			$focusCircle.draggable('destroy');
			$focusCircle.parent().removeClass('focusActive');
			$focusCircle.remove();
			var $button = $edit.find('.InputfieldImageButtonFocus');
			if($button.length) {
				$icon = $button.find('i');
				$icon.removeClass('focusIconActive').toggleClass($icon.attr('data-toggle'));
			}
		}
	}

	/**
	 * Get focus zoom position for either X or Y 
	 * 
	 * A variation from Horst's PHP version in ImageSizerEngine, here simplified for square preview areas.
	 *
	 * @param focusPercent Left or Top percentage
	 * @param sourceDimension Width or Height of source image
	 * @param cropDimension Width or Height of cropped image
	 * @returns {number}
	 *
	 */
	function getFocusZoomPosition(focusPercent, sourceDimension, cropDimension) {
		var focusPX = parseInt(sourceDimension * focusPercent / 100);
		var position = parseInt(focusPX - (cropDimension / 2));
		var maxPosition = parseInt(sourceDimension - cropDimension);

		if(0 > position) position = 0;
		if(maxPosition < position) position = maxPosition;

		return position;
	}

	/**
	 * Get focus zoom crop dimensions (a variation from Horst's PHP version in ImageSizerEngine, here simplified for square preview areas)
	 *
	 * @param focusLeft Left percent
	 * @param focusTop Top percent
	 * @param zoomPercent Zoom percent
	 * @param faWidth Width of the thumbnail image
	 * @param faHeight Height of the thumbnail image
	 * @param zoomBoxSize Width and Height of the ZoomArea
	 * @returns {{left: number, top: number, width: number, height: number}}
	 *
	 */
	function getFocusZoomCropDimensions(focusLeft, focusTop, zoomPercent, faWidth, faHeight, zoomBoxSize) {

		// calculate the max crop dimensions in percent
		var percentW = zoomBoxSize / faWidth * 100; // calculate percentage of the crop width in regard of the original width
		var percentH = zoomBoxSize / faHeight * 100; // calculate percentage of the crop height in regard of the original height

		// use the smaller crop dimension
		var maxDimension = percentW >= percentH ? faWidth : faHeight;

		// calculate the zoomed dimensions
		var cropDimension = maxDimension - (maxDimension * zoomPercent / 100); // to get the final crop Width and Height, the amount for zoom-in needs to get stripped out

		// calculate the crop positions
		var posLeft = getFocusZoomPosition(focusLeft, faWidth, cropDimension); // calculate the x-position
		var posTop = getFocusZoomPosition(focusTop, faHeight, cropDimension); // calculate the y-position
		// var percentLeft = posLeft / faWidth * 100;
		// var percentTop = posTop / faHeight * 100;

		return {
			'left': posLeft,
			'top': posTop,
			'width': cropDimension,
			'height': cropDimension
		};
	}

	/**
	 * Get focus zoom position for either X or Y, intended for use with getFocusZoomCropDimensions4GridviewSquare()
	 * 
	 * via Horst
	 *
	 * @param focusPercent Left or Top percentage
	 * @param sourceDimPX Width or Height from the full image
	 * @param gridViewPX Width and Height from the square GridView-Thumbnail
	 * @param zoomPercent Zoom percent
	 * @param scale
	 * @param smallestSidePX the smallest Dimension from the full image
	 * @returns {number}
	 *
	 */
	function getFocusZoomPosition4GridviewSquare(focusPercent, sourceDimPX, gridViewPX, zoomPercent, scale, smallestSidePX) {
		var sourceDimPX = sourceDimPX * scale;                 // is used to later get the position in pixel
		var gridViewPercent = gridViewPX / sourceDimPX * 100;  // get percent of the gridViewBox in regard to the current image side size (width|height)
		var adjustPercent = gridViewPercent / 2;               // is used to calculate position from the circle center point to [left|top] percent
		var posPercent = focusPercent - adjustPercent;         // get adjusted position in percent

		var posMinVal = 0;
		var posMaxVal = 100 - gridViewPercent;
		if(posPercent <= posMinVal) posPercent = 0;
		if(posPercent >= posMaxVal) posPercent = posMaxVal;

		var posPX = sourceDimPX / 100 * posPercent / scale;
		posPX = -1 * parseInt(posPX);

		//console.log(['gridView1:', 'sourceDimPX='+sourceDimPX, 'gridViewPX='+gridViewPX, 'gridViewPercent='+gridViewPercent, 'adjustPercent='+adjustPercent, 'minVal='+posMinVal, 'maxVal='+posMaxVal, 'posPercent='+posPercent, 'posPX='+posPX]);
		return posPX;
	}

	/**
	 * Get focus zoom clip rect for the square GridView-Thumbnails
	 * 
	 * via Horst
	 *
	 * @param focusLeft Left percent
	 * @param focusTop Top percent
	 * @param zoomPercent Zoom percent
	 * @param w Width of the thumbnail image
	 * @param h Height of the thumbnail image
	 * @param gridViewSize Dimension of the GridView-Thumbnail
	 * @param scale
	 * @returns {{transformLeft: number, transformTop: number, scale: number}}
	 *
	 */
	function getFocusZoomCropDimensions4GridviewSquare(focusLeft, focusTop, zoomPercent, w, h, gridViewSize, scale) {
		var smallestSidePX = w >= h ? h : w;
		var posLeft = getFocusZoomPosition4GridviewSquare(focusLeft, w, gridViewSize, zoomPercent, scale, smallestSidePX);
		var posTop = getFocusZoomPosition4GridviewSquare(focusTop, h, gridViewSize, zoomPercent, scale, smallestSidePX);
		var transformLeft = parseInt(posLeft);
		var transformTop  = parseInt(posTop);
		return {
			'transformLeft': transformLeft,
			'transformTop': transformTop,
			'scale': scale
		};
	}

	/**
	 * Tear down the InputfieldImageEdit panel
	 *
	 * @param $edit
	 *
	 */
	function tearDownEdit($edit) {
		stopFocus($edit);
		$edit.off('click', '.InputfieldImageButtonFocus');
		$inputArea = $edit.find(".InputfieldImageEdit__edit");
		if($inputArea.children().not(".InputfieldFileSort").length) {
			var $items = $inputArea.children();
			//console.log('tearDown moving items back to #' + $inputArea.attr('data-current'));
			$("#" + $inputArea.attr("data-current")).find(".ImageData").append($items);
		}
	}

	/**
	 * Close the InputfieldImageEdit panel
	 *
	 * @param $parent
	 * @param $not
	 *
	 */
	function closeEdit($parent, $not) {
		var $edit;

		if($parent) {
			$edit = $parent.find(".InputfieldImageEdit--active");
		} else if($not) {
			$edit = $(".InputfieldImageEdit--active").not($not.find(".InputfieldImageEdit--active"));
		} else {
			$edit = $(".InputfieldImageEdit--active");
		}
		
		if($edit.length) {
			tearDownEdit($edit);
			$edit.removeClass("InputfieldImageEdit--active").removeAttr('id');
			$('#' + $edit.attr('data-for')).removeClass('gridImageEditing');
		}
		
		$(".InputfieldImageEdit__replace").removeClass("InputfieldImageEdit__replace"); 
	}

	/**
	 * Move the edit panel, placing it correctly in the grid were necessary
	 *
	 * @param $el
	 * @param $edit
	 *
	 */
	function moveEdit($el, $edit) {
		
		if(!$el || !$el.length) return;
		
		//getImagesPerRow($el.parent());

		var $children = $el.parent().children().not(".InputfieldImageEdit");
		//var	perRow = parseInt($el.parent().attr("data-per-row"));
		//var	index = $children.index($el);
	
		var lastTop = 0;
		var found = false;
		var $insertBeforeItem = null;
	
		$children.each(function() {
			if($insertBeforeItem) return;
			var $item = $(this);
			var top = $item.offset().top;
			if(found && top != lastTop) {
				$insertBeforeItem = $item;
			} else if($item.attr('id') == $el.attr('id')) {
				found = true;
			}
			lastTop = top;
		}); 
	
		if($insertBeforeItem) {
			$edit.insertBefore($insertBeforeItem);
		} else {
			$edit.insertAfter($children.eq($children.length - 1));
		}

		/*
		for(var i = 0; i < 30; i++) {
			if(index % perRow !== perRow - 1) {
				index++;
			} else {
				continue;
			}
		}

		index = Math.min(index, $children.length - 1); // Do not excede number of items
		$edit.insertAfter($children.eq(index));
		*/

		var $arrow = $edit.find(".InputfieldImageEdit__arrow");
		if($arrow.length) $arrow.css("left", $el.position().left + ($el.outerWidth() / 2) + "px");
	}
	
	/*** GRID INITIALIZATION ****************************************************************************/
	
	/**
	 * Initialize non-upload related events
	 *
	 */
	function initGridEvents() {

		// resize window event
		$(window).resize(throttle(windowResize, 200));

		// click or double click trash event
		$(document).on('click dblclick', '.gridImage__trash', function(e) {
			var $input = $(this).find("input");
			$input.prop("checked", inverseState).change();
			if(e.type == "dblclick") {
				setDeleteStateOnAllItems($input);
				e.preventDefault();
				e.stopPropagation();
			}
		});

		// change of "delete" status for an item event
		$(document).on("change", ".gridImage__deletebox", function() {
			updateDeleteClass($(this));
		});
		
		// click on "edit" link event
		$(document).on('click', '.gridImage__edit', function(e) {
			
			var $el = $(this).closest(".gridImage");
			if(!$el.length) return;
			if($el.closest('.InputfieldImageEditAll').length) return false;
			
			var $all = $el.closest(".gridImages");
			var $edit = $all.find(".InputfieldImageEdit");
			
			if($el.hasClass('gridImageEditing')) {
				// if item already has its editor open, then close it
				$edit.find(".InputfieldImageEdit__close").click();	
				
			} else {
				moveEdit($el, $edit);
				tearDownEdit($edit);
				setupEdit($el, $edit);

				$edit.addClass("InputfieldImageEdit--active").attr('data-for', $el.attr('id'));
				$all.find('.gridImageEditing').removeClass('gridImageEditing');
				$el.addClass('gridImageEditing');
			}
		
		}).on('click', '.InputfieldImageEditAll img', function(e) {
			e.stopPropagation();
			e.preventDefault();
			$.magnificPopup.close();
			var options = $.extend(true, {}, magnificOptions);
			var $img = $(this);
			options['items'] = {
				src: $img.attr('data-original'),
				title: $img.attr('alt')
			};
			$.magnificPopup.open(options);
			return true;
			
		}).on('click', '.InputfieldImageButtonFocus', function() {
			
			var $button = $(this);
			var $icon = $button.find('i');
			var $edit = $button.closest('.InputfieldImageEdit, .gridImage'); 
			var $focusCircle = $edit.find('.focusCircle');
			
			if($focusCircle.length) {
				// stops focus
				stopFocus($edit);
			} else {
				// starts focus
				startFocus($edit);
				$icon.addClass('focusIconActive');
				$icon.toggleClass($icon.attr('data-toggle'));
			}
		}); 

		$(document).on("click", function(e) {
			var $el = $(e.target);

			if($el.closest(".InputfieldImageEdit").length) {
				closeEdit(null, $el.parents(".gridImages"));
				
			} else if($el.is("input, textarea") && $el.closest(".InputfieldImageEditAll").length) {
				// clicked input in "edit all" mode, disable sortable, focus it then assign a blur event
				$el.focus().one('blur', function() {
					$el.closest('.gridImages').sortable('enable'); // re-enable sortable on blur
				});
				$el.closest('.gridImages').sortable('disable'); // disable sortable on focus
				
			} else if($el.closest(".gridImage__inner").length) {
				closeEdit(null, $el.parents(".gridImages"));
				
			} else if($el.closest(".mfp-container").length) {
				// magnific popup container
				return;
				
			} else if($el.closest(".ui-dialog").length) {
				// jQuery UI dialog
				return;
				
			} else if($el.is(".mfp-close")) {
				// magnific popup close button
				return;

			} else if($el.is("a.remove")) {
				// selectize 
				return;
				
			} else {
				// other
				closeEdit(null, null);
			}
		});

		// close "edit" panel
		$(document).on("click", ".InputfieldImageEdit__close", function(e) {
			closeEdit($(this).parents(".gridImages"), null);
		});
		
		// Warn about cropping after Inputfield has changed since crop may cause 
		// InputfieldContent to be reloaded which could cause changes to be lost
		$(document).on("change", ".InputfieldImage", function() {
			$(this).find('.InputfieldImageButtonCrop:not(.pw-modal-dblclick)')
				.addClass('pw-modal-dblclick ui-state-disabled');
			
		}).on("click", ".InputfieldImageButtonCrop.ui-state-disabled", function(e) {
			var $button = $(this);
			var $list = $button.closest('.gridImages');
			if(!$list.hasClass('gridImagesAlerted')) {
				ProcessWire.alert(ProcessWire.config.InputfieldImage.labels.changes);
				$list.addClass('gridImagesAlerted');
			}
			setTimeout(function() {
				$button.removeClass('ui-state-active');
			}, 500);
			return false;
		});

		$(".ImagesGrid").on("click", "button.pw-modal", function(e) {
			e.preventDefault();
		});
		
		setupEditableFilename();
		checkInputfieldWidth();
		
		/*
		// Longclick .gridItem to open magnific popup
		// Stops working as soon as an "Edit" panel has been opened, and 
		// also prevents any image zooms from working. :-/
		$(document).on('longclick', '.gridImage__edit', function() {
			var $img = $(this).closest('.gridImage').find('img');
			console.log($img.attr('data-original'));
			var options = magnificOptions;
			options['items'] = {
				src: $img.attr('data-original'),
				title: $img.attr('alt')
			};
			$.magnificPopup.open(options, 0);
			return false;
		});
		*/
	}
	
	/**
	 * Make the file basename editable
	 *
	 * @param $inputfield
	 *
	 */
	function setupEditableFilename() {

		$(document).on('click', '.InputfieldImageEdit__name', function(e) {

			var $span = $(this).children('span');
			var $input = $span.closest('.gridImage, .InputfieldImageEdit').find('.InputfieldFileRename');
			var $list = $span.closest('.gridImages');

			$list.sortable('disable');
			$input.val($span.text());

			$span.on('keypress', function(e) {
				if(e.which == 13) {
					$span.blur();
					return false;
				}
				return true;
			});

			$span.attr('autocomplete', 'off')
				.attr('autocorrect', 'off')
				.attr('autocapitalize', 'off')
				.attr('spellcheck', 'false');

			$span.focus().on('blur', function() {
				var val = $(this).text();
				if($.trim(val).length < 1) {
					$span.text($input.val());
				} else if(val != $input.val()) {
					$input.val(val).change();
					$list.closest('.Inputfield').trigger('change');
					//console.log('changed to: ' + val);
				}
				$span.off('keypress');
				$list.sortable('enable');
			});
		});
	}

	/**
	 * Set size of image thumbnails in "List" mode as a percent (between approximately 15% and 40%)
	 * 
	 * @param $inputfield
	 * @param pct
	 * 
	 */
	function setListSize($inputfield, pct) {
		pct = Math.floor(pct);
		$inputfield.find(".gridImage__overflow").each(function() {
			var dataPct = 100 - pct; 
			var $this = $(this);
			$this.css('width', pct + '%');
			$this.siblings('.ImageData').css('width', dataPct + '%');
			$this.find('img').css({
				top: 0,
				left: 0,
				transform: 'none',
			});
		});
		setCookieData($inputfield, 'listSize', pct);
	}

	/**
	 * Set size of image thumbnails in grid
	 * 
	 * @param $inputfield
	 * @param gridSize
	 * @param ragged Whether to use ragged right or not (same as "left" mode)
	 * 
	 */
	function setGridSize($inputfield, gridSize, ragged) {
		
		if(!gridSize) return;
	
		var size = gridSize + 'px';
		var $gridImages = $inputfield.find('.gridImages');
	
		/*
		if(typeof ragged == "undefined") {
			ragged = getCookieData($inputfield, 'ragged'); 
		}
		*/
		if(typeof ragged == "undefined" || ragged == null) ragged = $gridImages.attr('data-ragged') ? true : false;
		
		if(ragged) {
			$gridImages.attr('data-ragged', 1);
		} else {
			$gridImages.removeAttr('data-ragged');
		}
		
		$gridImages.find(".gridImage__overflow").each(function() {
			setGridSizeItem($(this), gridSize, ragged);
		});
		
		$gridImages.find(".gridImage__edit, .gridImage__resize").css('line-height', size); 
		$gridImages.attr('data-size', gridSize);
		setCookieData($inputfield, 'size', gridSize); 

		if(retryGridItems.length) setTimeout(function() {
			while(retryGridItems.length) {
				var item = retryGridItems.pop();
				setGridSizeItem(item.item, item.gridSize, ragged);
			}
		}, 150);
	}

	/**
	 * Update a gridImage__overflow item for the setGridSize() method
	 * 
	 * @param $item
	 * @param gridSize
	 * @param ragged
	 * @param focus optional
	 * 
	 */
	function setGridSizeItem($item, gridSize, ragged, focus) {
		
		if($item.hasClass('gridImage__overflow')) {
			var $img = $item.children('img');	
		} else if($item.is('img')) {
			var $img = $item;
			$item = $img.closest('.gridImage__overflow');
		} else {
			return;
		}
		
		if(!gridSize) {
			$img.removeAttr('width').removeAttr('height');
			$item.width('auto').height('auto');
			return;
		}
		
		var zoom = 0;
		var w = $img.width();
		var h = $img.height();
		var dataW = parseInt($img.attr('data-w'));
		var dataH = parseInt($img.attr('data-h'));
		if(!w) w = dataW;
		if(!h) h = dataH;
		
		if(!ragged && typeof focus == "undefined") {
			var focusStr = $img.attr('data-focus');
			if(typeof focusStr == "undefined") focusStr = '50.0 50.0 0';
			var focusArray = focusStr.split(' ');
			focus = { 
				top: parseFloat(focusArray[0]), 
				left: parseFloat(focusArray[1]), 
				zoom: parseInt(focusArray[2]) 
			};
		}	
		if(!ragged) zoom = focus.zoom;
		
		if(ragged) {
			// show full thumbnail (not square)
			$img.attr('height', gridSize).removeAttr('width');
			$img.css({
				'max-height': '100%',
				'max-width': 'none',
				'top': '50%',
				'left': '50%',
				'transform': 'translate3d(-50%, -50%, 0)'
			});
			
		} else if(zoom > 0 && $item.closest('.InputfieldImageFocusZoom').length && !gridSliding) { 
			// focus with zoom
			if(w >= h) {
				var maxHeight = '100%';
				var maxWidth = 'none';
				if(w == dataW) {
					// scale full dimensions proportionally to gridSize
					h = gridSize;	
					w = (h / dataH) * dataW
				}
			} else {
				var maxHeight = 'none';
				var maxWidth = '100%';
				if(h == dataH) {
					// scale full dimensions proportionally to gridSize
					w = gridSize;
					h = (w / dataW) * dataH;
				}
			}
		
			var scale = 1 + ((zoom / 100) * 2);
			var crop = getFocusZoomCropDimensions4GridviewSquare(focus.left, focus.top, zoom, w, h, gridSize, scale);
			$img.css({
				'left': '0px',
				'top': '0px',
				'transform-origin': '0px 0px',
				'transform': 'scale(' + crop.scale + ') translate3d(' + crop.transformLeft + 'px, ' + crop.transformTop + 'px, 0)',
				'max-width': maxWidth,
				'max-height': maxHeight
			});

		} else if(w >= h) {
			// image width greater than height
			$img.attr('height', gridSize).removeAttr('width');
			if(focus.left < 1) focus.left = 0.001;
			$img.css({
				'max-height': '100%',
				'max-width': 'none',
				'top': '50%',
				'left': focus.left + '%',
				'transform': 'translate3d(-' + focus.left + '%, -50%, 0)'
			});
		} else if(h > w) {
			// image height greater tahn width
			$img.attr('width', gridSize).removeAttr('height');
			if(focus.top < 1) focus.top = 0.001;
			$img.css({
				'max-height': 'none',
				'max-width': '100%',
				'top': focus.top + '%',
				'left': '50%',
				'transform': 'translate3d(-50%, -' + focus.top + '%, 0)'
			});
		} else {
			// perfectly square image
			$img.css({
				'max-height': '100%',
				'max-width': 'none',
				'top': '50%',
				'left': '50%',
				'transform': 'translate3d(-50%, -50%, 0)'
			});
			$img.removeAttr('width').attr('height', gridSize);
		}

		var w = $img.width();
		// if(!w) w = $img.attr('data-w');

		if(w) {
			$item.css({
				width: (ragged ? w + 'px' : gridSize + 'px'),
				height: gridSize + 'px'
			});
		} else {
			var tries = $item.attr('data-tries');
			if(!tries) tries = 0;
			if(typeof tries == "undefined") tries = 0;
			tries = parseInt(tries);
			if(tries > 3) {
				// no more attempts
				$item.css({
					width: gridSize + 'px',
					height: gridSize + 'px'
				});
			} else {
				retryGridItems.push({ item: $item, gridSize: gridSize });
				$item.attr('data-tries', tries + 1); 
			}
		}
	}

	/**
	 * Setup the toggle between image LIST (edit all) mode and image GRID mode
	 * 
	 * @param $target
	 * 
	 */
	function setupImageListToggle($target) {
		
		if($target.find('.InputfieldImageListToggle').length) return;
		
		var $list = $("<a class='InputfieldImageListToggle' href='list'></a>").append("<i class='fa fa-th-list'></i>");
		var $left = $("<a class='InputfieldImageListToggle' href='left'></a>").append("<i class='fa fa-tasks'></i>");
		var $grid = $("<a class='InputfieldImageListToggle' href='grid'></a>").append("<i class='fa fa-th'></i>");
		var activeClass = 'InputfieldImageListToggle--active';
		var defaultMode = ''; 
		//var $gridLg = $("<a class='InputfieldImageListToggle' href='grid-lg'></a>").append("<i class='fa fa-th-large'></i>");
		//var gridSize = $target.find('.gridImages').attr('data-gridsize');
		//var hrefPrev = '';
		//var sizePrev = gridSize;
		
		var toggleClick = function(e) {
			
			var $a = $(this);
			var $inputfield = $a.closest('.Inputfield');
			var href = $a.attr('href');
			var size;
			var $aPrev = $a.parent().children('.' + activeClass);
			var hrefPrev = $aPrev.attr('href');
			
			$aPrev.removeClass(activeClass);
			$a.addClass(activeClass);
			stopFocus($inputfield);
			
			if(href == 'list') {
				if(!$inputfield.hasClass('InputfieldImageEditAll')) {
					$inputfield.find(".InputfieldImageEdit--active .InputfieldImageEdit__close").click();
					$inputfield.addClass('InputfieldImageEditAll');
				}
				size = getCookieData($inputfield, 'listSize');
				setListSize($inputfield, size);
				setCookieData($inputfield, 'mode', 'list');
			} else if(href == 'left') {
				$inputfield.removeClass('InputfieldImageEditAll');
				size = getCookieData($inputfield, 'size');
				setGridSize($inputfield, size, true);
				setCookieData($inputfield, 'mode', 'left');
				updateGrid();
			} else if(href == 'grid') {
				$inputfield.removeClass('InputfieldImageEditAll');
				size = getCookieData($inputfield, 'size');
				setGridSize($inputfield, size, false);
				setCookieData($inputfield, 'mode', 'grid');
				if(hrefPrev == 'left') setTimeout(function() {
					// because width/height aren't immediately available for img, so run again in this case
					setGridSize($inputfield, size, false);
				}, 100);
			}

			//hrefPrev = href; //hrefPrev == href && href != 'left' && href != 'list' ? '' : href;
			//sizePrev = size;
			setupSortable($inputfield.find('.gridImages'));
			$a.blur();
			
			return false;
		};
		
		$list.click(toggleClick);
		$left.click(toggleClick);
		$grid.click(toggleClick);
		
		if($target.hasClass('InputfieldImage')) {
			$target.find('.InputfieldHeader').append($list).append($left).append($grid);
			defaultMode = getCookieData($target, 'mode');
		} else {
			$(".InputfieldImage .InputfieldHeader", $target).append($list).append($left).append($grid);
		}

		if(defaultMode == 'list') {
			$list.click();
		} else if(defaultMode == 'left') {
			$left.click();
		} else {
			// grid, already clicked
		}
	
		/*
		if($target.hasClass('InputfieldImageEditAll')) {
			$list.addClass(activeClass);
			//hrefPrev = 'list';
		} else {
			$grid.addClass(activeClass);
			//hrefPrev = 'grid';
		}
		*/
	}
	
	function setupSizeSlider($inputfield) {
		
		var $header = $inputfield.children('.InputfieldHeader');
		if($header.children('.InputfieldImageSizeSlider').length) return;
		
		var $gridImages = $inputfield.find('.gridImages');
		var gridSize = $gridImages.attr('data-gridsize'); 
		var min = gridSize / 2;
		var max = gridSize * 2;
		var $slider = $('<span class="InputfieldImageSizeSlider"></span>');
	
		$header.append($slider);
		
		var sizeSlide = function(event, ui) {
			var value = ui.value;
			var minPct = 15;
			var divisor = Math.floor(gridSize / minPct);
			var v = value - min;
			var listSize = Math.floor(minPct + (v / divisor));

			if($inputfield.hasClass('InputfieldImageEditAll')) {
				setCookieData($inputfield, 'size', value);
				setListSize($inputfield, listSize);
			} else {
				setCookieData($inputfield, 'listSize', listSize);
				setGridSize($inputfield, value);
			}
		};
		
		$slider.slider({
			'min': min,
			'max': max, 
			'value': getCookieData($inputfield, 'size'),
			'range': 'min',
			'slide': sizeSlide,
			'start': function(event, ui) {
				gridSliding = true;
				if($inputfield.find(".InputfieldImageEdit:visible").length) {
					$inputfield.find(".InputfieldImageEdit__close").click();
				}
			}, 
			'stop': function(event, ui) {
				gridSliding = false;
				sizeSlide(event, ui); 
				updateGrid($inputfield);
			}
		});
	}

	/**
	 * Set cookie data for this Inputfield
	 * 
	 * @param $inputfield
	 * @param property
	 * @param value
	 * 
	 */
	function setCookieData($inputfield, property, value) {
		var data = getCookieData($inputfield); // get all InputfieldImage data
		var id = $inputfield.attr('id');
		var name = id ? id.replace('wrap_Inputfield_', '')  : '';
		if(!name.length || typeof value == "undefined") return;
		if(data[name][property] == value) return; // if already set with same value, exit now
		data[name][property] = value; 
		// $.cookie('InputfieldImage', data);
		$.cookie('InputfieldImage', data, {
			secure: (window.location.protocol.indexOf("https:") === 0)
		});
		cookieData = data;
		//console.log('setCookieData(' + property + ', ' + value + ')');
	}

	/**
	 * Get cookie data for this Inputfield
	 * 
	 * @param $inputfield
	 * @param property
	 * @returns {*}
	 * 
	 */
	function getCookieData($inputfield, property) {
		
		if(cookieData && typeof property == "undefined") return cookieData;
	
		var id = $inputfield.attr('id'); 
		var name = id ? id.replace('wrap_Inputfield_', '') : 'na';
		var data = cookieData ? cookieData : $.cookie('InputfieldImage');
		var value = null;	

		if(!data) var data = {};
	
		// setup default values
		if(typeof data[name] == "undefined") data[name] = {};
		if(typeof data[name].size == "undefined") data[name].size = parseInt($inputfield.find('.gridImages').attr('data-size'));
		if(typeof data[name].listSize == "undefined") data[name].listSize = 23;
		if(typeof data[name].mode == "undefined") data[name].mode = $inputfield.find('.gridImages').attr('data-gridMode');
		//if(typeof data[name].ragged == "undefined") data[name].ragged = $inputfield.find('.gridImages').attr('data-ragged') ? true : false;
		
		if(cookieData == null) cookieData = data; // cache
	
		// determine what to return
		if(typeof property == "undefined") {
			// return all cookie data
			value = data;
		} else if(property === true) {
			// return all data for $inputfield
			value = data[name];
		} else if(typeof data[name][property] != "undefined") {
			// return just one property
			value = data[name][property];
		}
		
		//console.log('getCookieData(' + property + ') ...');
		//console.log(value);
		
		return value;
	}

	/**
	 * Initialize an .InputfieldImage for lightbox (magnific) and sortable
	 * 
	 * @param $inputfield
	 * 
	 */
	function initInputfield($inputfield) {
		
		if($inputfield.hasClass('InputfieldStateCollapsed')) return;
		
		var maxFiles = parseInt($inputfield.find(".InputfieldImageMaxFiles").val());
		var $gridImages = $inputfield.find('.gridImages');
		var size = getCookieData($inputfield, 'size');
		var mode = getCookieData($inputfield, 'mode');
		var ragged = mode == 'left' ? true : false;
		var renderValueMode = $inputfield.hasClass('InputfieldRenderValueMode');
		
		if(!size) size = $gridImages.attr('data-gridsize');
		size = parseInt(size);
		
		//console.log('initInputfield');
		//console.log($inputfield);
		
		if(!renderValueMode && ($inputfield.hasClass('InputfieldImageEditAll') || mode == 'list')) {
			var listSize = getCookieData($inputfield, 'listSize');
			setListSize($inputfield, listSize);
		} else {
			setGridSize($inputfield, size, ragged);
		}
	
		if(!$inputfield.hasClass('InputfieldImageInit')) {
			$inputfield.addClass('InputfieldImageInit');
			
			if(renderValueMode) {
				return setupMagnificForRenderValue($inputfield);

			} else if(maxFiles == 1) {
				$inputfield.addClass('InputfieldImageMax1');
				setupMagnificForSingle($inputfield);

			} else {
				setupSortable($gridImages);
			}

			setupImageListToggle($inputfield);
			setupSizeSlider($inputfield);
		}
		
		checkInputfieldWidth($inputfield);
	
		$inputfield.on('change', '.InputfieldFileActionSelect', function() {
			var $note = $(this).next('.InputfieldFileActionNote');
			if($(this).val().length) {
				$note.fadeIn();
			} else {
				$note.hide();
			}
		}); 
	}

	/*** UPLOAD **********************************************************************************/

	/**
	 * Initialize non-HTML5 uploads
	 *
	 */
	function initUploadOldSchool() {
		$("body").addClass("ie-no-drop");

		$(".InputfieldImage.InputfieldFileMultiple").each(function() {
			var $field = $(this),
				maxFiles = parseInt($field.find(".InputfieldFileMaxFiles").val()),
				$list = $field.find('.gridImages'),
				$uploadArea = $field.find(".InputfieldImageUpload");

			$uploadArea.on('change', 'input[type=file]', function() {
				var $t = $(this),
					$mask = $t.parent(".InputMask");

				if($t.val().length > 1) $mask.addClass("ui-state-disabled");
				else $mask.removeClass("ui-state-disabled");

				if($t.next("input.InputfieldFile").length > 0) return;
				var numFiles = $list.children('li').length + $uploadArea.find('input[type=file]').length + 1;
				if(maxFiles > 0 && numFiles >= maxFiles) return;

				$uploadArea.find(".InputMask").not(":last").each(function() {
					var $m = $(this);
					if($m.find("input[type=file]").val() < 1) $m.remove();
				});

				// add another input
				var $i = $mask.clone().removeClass("ui-state-disabled");
				$i.children("input[type=file]").val('');
				$i.insertAfter($mask);
			});
		});
	}

	/**
	 * Initialize HTML5 uploads
	 *
	 * By apeisa with additional code by Ryan and LostKobrakai
	 *
	 * Based on the great work and examples of Craig Buckler (http://www.sitepoint.com/html5-file-drag-and-drop/)
	 * and Robert Nyman (http://robertnyman.com/html5/fileapi-upload/fileapi-upload.html)
	 *
	 */
	function initUploadHTML5($inputfield) {
	
		// target is one or more .InputfieldImageUpload elements
		var $target;

		if($inputfield.length > 0) {
			// Inputfield provided, target it
			$target = $inputfield.find(".InputfieldImageUpload"); 
		} else {
			// No Inputfield provided, focus on all 
			$target = $(".InputfieldImageUpload"); 
		}

		// initialize each found item
		$target.each(function(i) {
			var $this = $(this);
			var $content = $this.closest('.InputfieldContent');
			if($this.hasClass('InputfieldImageInitUpload')) return;
			initHTML5Item($content, i);
			$this.addClass('InputfieldImageInitUpload');
		}); 

		/**
		 * Initialize an "InputfieldImage > .InputfieldContent" item
		 * 
		 * @param $this
		 * @param i
		 */
		function initHTML5Item($this, i) {

			var $form = $this.parents('form');
			var $repeaterItem = $this.closest('.InputfieldRepeaterItem');
			var postUrl = $repeaterItem.length ? $repeaterItem.attr('data-editUrl') : $form.attr('action');
			
			postUrl += (postUrl.indexOf('?') > -1 ? '&' : '?') + 'InputfieldFileAjax=1';

			// CSRF protection
			var $postToken = $form.find('input._post_token');
			var postTokenName = $postToken.attr('name');
			var postTokenValue = $postToken.val();
			var $errorParent = $this.find('.InputfieldImageErrors').first();
			
			var fieldName = $this.find('.InputfieldImageUpload').data('fieldname');
			fieldName = fieldName.slice(0, -2); // trim off the trailing "[]"

			var $inputfield = $this.closest('.Inputfield.InputfieldImage');
			var extensions = $this.find('.InputfieldImageUpload').data('extensions').toLowerCase();
			var maxFilesize = $this.find('.InputfieldImageUpload').data('maxfilesize');
			var filesUpload = $this.find("input[type=file]").get(0);
			var $fileList = $this.find(".gridImages");
			var fileList = $fileList.get(0);
			var gridSize = $fileList.data("gridsize");
			var doneTimer = null; // for AjaxUploadDone event
			var maxFiles = parseInt($this.find('.InputfieldImageMaxFiles').val());
			var resizeSettings = getClientResizeSettings($inputfield);
			var useClientResize = resizeSettings.maxWidth > 0 || resizeSettings.maxHeight > 0 || resizeSettings.maxSize > 0;

			setupDropzone($this);
			if(maxFiles != 1) setupDropInPlace($fileList);
			//setupDropHere();

			$fileList.children().addClass('InputfieldFileItemExisting'); // identify items that are already there
		
			$inputfield.on('pwimageupload', function(event, data) {
				traverseFiles([ data.file ], data.xhr); 
			}); 

			/**
			 * Setup the .AjaxUploadDropHere 
			 * 
			function setupDropHere() {
				$dropHere = $this.find('.AjaxUploadDropHere');
				$dropHere.show(); 
					.click(function() {
					var $i = $(this).find('.InputfieldImageRefresh');
					if($i.is(":visible")) {
						$i.hide().siblings('span').show();
						$(this).find('input').val('0');
					} else {
						$i.show().siblings('span').hide();
						$(this).find('input').val('1');
					}
				});
			}
			 */

			/**
			 * Render and return markup for an error item
			 * 
			 * @param message
			 * @param filename
			 * @returns {string}
			 * 
			 */
			function errorItem(message, filename) {
				if(typeof filename !== "undefined") message = '<b>' + filename + ':</b> ' + message;
				var icon = "<i class='fa fa-fw fa-warning'></i> ";
				return '<li>' + icon + message + '</li>';
			}

			/**
			 * Given a filename, return the basename 
			 * 
			 * @param str
			 * @returns {string}
			 * 
			 */
			function basename(str) {
				var base = new String(str).substring(str.lastIndexOf('/') + 1);
				if(base.lastIndexOf(".") != -1) base = base.substring(0, base.lastIndexOf("."));
				return base;
			}
			
			/**
			 * Setup the dropzone where files are dropped
			 *
			 * @param $el .InputfieldContent element
			 *
			 */
			function setupDropzone($el) {
				
				// Dropzone remains even after a 'reloaded' event, since it is the InputfieldContent container
				if($el.hasClass('InputfieldImageDropzoneInit')) return;

				var el = $el.get(0);
				var $inputfield = $el.closest('.Inputfield');
				
				function dragStart() {
					if($inputfield.hasClass('pw-drag-in-file')) return;
					$el.addClass('ui-state-hover');
					$inputfield.addClass('pw-drag-in-file');
				}
				
				function dragStop() {
					if(!$inputfield.hasClass('pw-drag-in-file')) return;
					$el.removeClass('ui-state-hover');
					$inputfield.removeClass('pw-drag-in-file');
				}

				el.addEventListener("dragleave", function() {
					dragStop();
				}, false);
				
				el.addEventListener("dragenter", function(evt) {
					evt.preventDefault();
					dragStart();
				}, false);

				el.addEventListener("dragover", function(evt) {
					if(!$el.is('ui-state-hover')) dragStart();
					evt.preventDefault();
					evt.stopPropagation();
					return false;
				}, false);

				el.addEventListener("drop", function(evt) {
					traverseFiles(evt.dataTransfer.files);
					dragStop();
					evt.preventDefault();
					evt.stopPropagation();
					return false;
				}, false);
			
				$el.addClass('InputfieldImageDropzoneInit');
			}

			/**
			 * Support for drag/drop uploading an image at a place within the grid
			 * 
			 * @param $el 
			 * 
			 */
			function setupDropInPlace($gridImages) {
			
				var $i = null; // placeholder .gridItem
				var haltDrag = false; // true when drag should be halted
				var timer = null; // for setTimeout
				var $inputfield = $gridImages.closest('.Inputfield');
				
				function addInputfieldClass() {
					$inputfield.addClass('pw-drag-in-file');
				}
				function removeInputfieldClass() {
					$inputfield.removeClass('pw-drag-in-file');
				}
				
				function getCenterCoordinates($el) {
					var offset = $el.offset();
					var width = $el.width();
					var height = $el.height();

					var centerX = offset.left + width / 2;
					var centerY = offset.top + height / 2;
					
					return {
						clientX: centerX,
						clientY: centerY
					}
				}
				
				function noDropInPlace() {
					return $gridImages.find(".InputfieldImageEdit--active").length > 0;
				}

				function dragEnter(evt) {
					if(noDropInPlace()) return;
					evt.preventDefault();
					evt.stopPropagation();
					addInputfieldClass();
					haltDrag = false;
					if($i == null) {
						var gridSize = $gridImages.attr('data-size') + 'px';
						var $o = $("<div/>").addClass('gridImage__overflow');
						if($gridImages.closest('.InputfieldImageEditAll').length) {
							$o.css({ width: '100%', height: gridSize });
						} else {
							$o.css({ width: gridSize, height: gridSize });
						}
						$i = $("<li/>").addClass('ImageOuter gridImage gridImagePlaceholder').append($o);
						$gridImages.append($i);
					}
					// close editor if it is opened
					var coords = getCenterCoordinates($i);
					$i.simulate("mousedown", coords);
				}
				
				function dragOver(evt) {
					if(noDropInPlace()) return;
					evt.preventDefault();
					evt.stopPropagation();
					addInputfieldClass();
					haltDrag = false;
					if($i == null) return;
					// $('.gridImage', $gridImages).trigger('drag');
					var coords = {
						clientX: evt.originalEvent.clientX,
						clientY: evt.originalEvent.clientY
					};
					$i.simulate("mousemove", coords);
				}
				
				function dragEnd(evt) {
					if(noDropInPlace()) return;
					evt.preventDefault();
					evt.stopPropagation();
					if($i == null) return false;
					haltDrag = true;
					if(timer) clearTimeout(timer);
					timer = setTimeout(function() {
						if(!haltDrag || $i == null) return;
						$i.remove();
						$i = null;
						removeInputfieldClass();
					}, 1000); 
				}
				
				function drop(evt) {
					if(noDropInPlace()) return;
				
					removeInputfieldClass();
					// console.log(evt.originalEvent.dataTransfer.files);
					
					haltDrag = false;

					var coords = {
						clientX: evt.clientX,
						clientY: evt.clientY
					};

					$i.simulate("mouseup", coords);
					
					$uploadBeforeItem = $i.next('.gridImage');
					$i.remove();
					$i = null;
				}
			
				if($gridImages.length && !$gridImages.hasClass('gridImagesInitDropInPlace')) {
					$gridImages.on('dragenter', dragEnter);
					$gridImages.on('dragover', dragOver);
					$gridImages.on('dragleave', dragEnd);
					$gridImages.on('drop', drop); 
					$gridImages.addClass('gridImagesInitDropInPlace'); // not necessary
				}
			}
			
			/**
			 * Upload file
			 * 
			 * @param file
			 * @param extension (optional)
			 * @param xhrsub (optional replacement for xhr)
			 * 
			 */
			function uploadFile(file, extension, xhrsub) {
			
				var labels = ProcessWire.config.InputfieldImage.labels;
				var filesizeStr = parseInt(file.size / 1024, 10) + '&nbsp;kB';
				var tooltipMarkup = '' +
					'<div class="gridImage__tooltip">' + 
						'<table><tbody><tr>' + 
							'<th>' + labels.dimensions + '</th>' + 
							'<td class="dimensions">' + labels.na + '</td>' +
						'</tr><tr>' + 
							'<th>' + labels.filesize + '</th>' + 
							'<td>' + filesizeStr + '</td>' + 
						'</tr><tr>' + 
							'<th>' + labels.variations + '</th>' + 
							'<td>0</td>' + 
						'</tr></tbody></table>' + 
					'</div>';
				

				var $progressItem = $('<li class="gridImage gridImageUploading"></li>'),
					$tooltip = $(tooltipMarkup),
					$imgWrapper = $('<div class="gridImage__overflow"></div>'),
					$imageData = $('<div class="ImageData"></div>'),
					$hover = $("<div class='gridImage__hover'><div class='gridImage__inner'></div></div>"),
					$progressBar = $("<progress class='gridImage__progress' min='-1' max='100' value='0'></progress>"),
					$edit = $('<a class="gridImage__edit" title="' + file.name + '"><span>&nbsp;</span></a>'),
					$spinner = $('<div class="gridImage__resize"><i class="fa fa-spinner fa-spin fa-2x fa-fw"></i></div>'),
					reader,
					xhr,
					fileData,
					fileUrl = URL.createObjectURL(file),
					$fileList = $inputfield.find(".gridImages"), 
					singleMode = maxFiles == 1,
					size = getCookieData($inputfield, 'size'), 
					listSize = getCookieData($inputfield, 'listSize'), 
					listMode = $inputfield.hasClass('InputfieldImageEditAll'), 
					$img = $('<img height="' + size + '" alt="">');
				// $img = $('<img width="184" height="130" alt="">');

				$imgWrapper.append($img);
				$hover.find(".gridImage__inner").append($edit);
				$hover.find(".gridImage__inner").append($spinner.css('display', 'none'));
				$hover.find(".gridImage__inner").append($progressBar);
				$imageData.append($('' + 
					'<h2 class="InputfieldImageEdit__name">' + file.name + '</h2>' +
					'<span class="InputfieldImageEdit__info">' + filesizeStr + '</span>')
				);

				if(listMode) {
					$imgWrapper.css('width', listSize + "%");
					$imageData.css('width', (100 - listSize) + '%');
				} else {
					$imgWrapper.css({
						width: size + "px",
						height: size + "px"
					});
				}

				$progressItem
					.append($tooltip)
					.append($imgWrapper)
					.append($hover)
					.append($imageData);

				$img.attr({
					src: fileUrl,
					"data-original": fileUrl
				});

				img = new Image();
				img.addEventListener('load', function() {
					$tooltip.find(".dimensions").html(this.width + "&nbsp;&times;&nbsp;" + this.height);
					var factor = Math.min(this.width, this.height) / size;
					$img.attr({
						width: this.width / factor,
						height: this.height / factor
					});
				}, false);
				img.src = fileUrl;

				// Uploading - for Firefox, Google Chrome and Safari
				if(typeof xhrsub != "undefined") {
					xhr = xhrsub;
				} else {
					xhr = new XMLHttpRequest();
				}

				// Update progress bar
				function updateProgress(evt) {
					if(typeof evt != "undefined") {
						if(!evt.lengthComputable) return;
						$progressBar.attr("value", parseInt((evt.loaded / evt.total) * 100));
					}
					$('body').addClass('pw-uploading');
					$spinner.css('display', 'block');
				}
				xhr.upload.addEventListener("progress", updateProgress, false);

				// File uploaded: called for each file
				xhr.addEventListener("load", function() {
					xhr.getAllResponseHeaders();
					var response = $.parseJSON(xhr.responseText);
					if(typeof response.ajaxResponse != "undefined") response = response.ajaxResponse; // ckeupload
					var	wasZipFile = response.length > 1;
					if(response.error !== undefined) response = [response];
					// response = [{error: "Invalid"}];

					// note the following loop will always contain only 1 item, unless a file containing more files (ZIP file) was uploaded
					for(var n = 0; n < response.length; n++) {

						var r = response[n];
						
						if(r.error) {
							$errorParent.append(errorItem(r.message));
							if(n == (response.length-1)) $progressItem.hide();
							continue;
						}

						var $item = null;
						var $markup = $(r.markup).hide();
						
						

						// IE 10 fix
						var $input = $this.find('input[type=file]');
						if($input.val()) $input.replaceWith($input.clone(true));

						// look for replacements
						if(r.overwrite) $item = $fileList.children('#' + $markup.attr('id'));
						// if(r.replace || maxFiles == 1) $item = $fileList.children('.InputfieldImageEdit:eq(0)');
						
						if(maxFiles == 1 || r.replace) {
							$item = $fileList.children('.gridImage:eq(0)');
						} else if(uploadReplace.item && response.length == 1) { // && !singleMode) {
							$item = uploadReplace.item;
						}
						
						// Insert the markup
						if($item && $item.length) {
							$item.replaceWith($markup);
						} else if($uploadBeforeItem && $uploadBeforeItem.length) {
							$uploadBeforeItem.before($markup); 
							$uploadBeforeItem = $markup;
						} else if(n === 0) {
							$progressItem.replaceWith($markup);
						} else {
							$fileList.append($markup);
						}
						

						// Show Markup
						if(listMode) {
							$markup.find('.gridImage__overflow').css('width', listSize + '%');
						} else {
							$markup.find('.gridImage__overflow').css({
								'height': size + 'px',
								'width': size + 'px'
							});
							$markup.find('img').hide();
						}
						$markup.fadeIn(150, function() {
							$markup.find('img').fadeIn();
							if(listMode) {
								setListSize($inputfield, listSize);
							} else {
								setGridSize($inputfield, size);
							}
						}).css("display", "");
						$markup.addClass('InputfieldFileItemExisting');

						if($item && $item.length) $markup.effect('highlight', 500);
						if($progressItem.length) $progressItem.remove();
						
						if(uploadReplace.item && maxFiles != 1) {
							// indicate replacement for processing
							$markup.find(".InputfieldFileReplace").val(uploadReplace.file);
							// update replaced file name (visually) if extensions are the same
							var $imageEditName = $markup.find(".InputfieldImageEdit__name");
							var uploadNewName = $imageEditName.text();
							var uploadNewExt = uploadNewName.substring(uploadNewName.lastIndexOf('.')+1).toLowerCase();
							uploadNewName = uploadNewName.substring(0, uploadNewName.lastIndexOf('.')); // remove ext
							var uploadReplaceName = uploadReplace.file;
							if(uploadReplaceName.indexOf('?') > -1) {
								uploadReplaceName = uploadReplaceName.substring(0, uploadReplaceName.indexOf('?'));
							}
							var uploadReplaceExt = uploadReplaceName.substring(uploadReplaceName.lastIndexOf('.')+1).toLowerCase();
							uploadReplaceName = uploadReplaceName.substring(0, uploadReplaceName.lastIndexOf('.')); // remove ext
							if(uploadReplaceExt == uploadNewExt) {
								$imageEditName.children('span').text(uploadReplaceName).removeAttr('contenteditable');
							}
							// re-open replaced item
							$markup.find(".gridImage__edit").click();
						}
				
						// reset uploadReplace data
						uploadReplace.file = '';
						uploadReplace.item = null
						uploadReplace.edit = null;
					}

					if(doneTimer) clearTimeout(doneTimer);
					$uploadBeforeItem = null;
					
					doneTimer = setTimeout(function() {
						if(maxFiles != 1) {
							setupSortable($fileList);
						} else {
							setupMagnificForSingle($inputfield);
						}
						$('body').removeClass('pw-uploading');
						$fileList.trigger('AjaxUploadDone'); // for things like fancybox that need to be re-init'd
					}, 500);
					
					$inputfield.trigger('change').removeClass('InputfieldFileEmpty');

				}, false);
		
				// close editor, if open
				if(uploadReplace.edit) {
					uploadReplace.edit.find('.InputfieldImageEdit__close').click();
				} else if($inputfield.find(".InputfieldImageEdit:visible").length) {
					$inputfield.find(".InputfieldImageEdit__close").click();
				}
				
				// Present file info and append it to the list of files
				if(uploadReplace.item) {
					uploadReplace.item.replaceWith($progressItem);
					uploadReplace.item = $progressItem;
				} else if($uploadBeforeItem && $uploadBeforeItem.length) {
					$uploadBeforeItem.before($progressItem);
				} else {
					$fileList.append($progressItem);
				}

				// Here we go
				function sendUpload(file, imageData) {
					if(typeof xhrsub == "undefined") {
						xhr.open("POST", postUrl, true);
					}
					xhr.setRequestHeader("X-FILENAME", encodeURIComponent(file.name));
					xhr.setRequestHeader("X-FIELDNAME", fieldName);
					if(uploadReplace.item) xhr.setRequestHeader("X-REPLACENAME", uploadReplace.file); 
					xhr.setRequestHeader("Content-Type", "application/octet-stream"); // fix issue 96-Pete
					xhr.setRequestHeader("X-" + postTokenName, postTokenValue);
					xhr.setRequestHeader("X-REQUESTED-WITH", 'XMLHttpRequest');
					if(typeof imageData != "undefined" && imageData != false) {
						xhr.send(imageData); 
					} else {
						xhr.send(file);
					}
					
					updateGrid();
					$inputfield.trigger('change');
					var numFiles = $inputfield.find('.InputfieldFileItem').length;
					if(numFiles == 1) {
						$inputfield.removeClass('InputfieldFileEmpty').removeClass('InputfieldFileMultiple').addClass('InputfieldFileSingle');
					} else if(numFiles > 1) {
						$inputfield.removeClass('InputfieldFileEmpty').removeClass('InputfieldFileSingle').addClass('InputfieldFileMultiple');
					}
				}
				
				updateProgress();
			
				var ext = file.name.substring(file.name.lastIndexOf('.')+1).toLowerCase();
				if(useClientResize && (ext == 'jpg' || ext == 'jpeg' || ext == 'png' || ext == 'gif')) {
					var resizer = new PWImageResizer(resizeSettings);
					$spinner.addClass('pw-resizing');
					resizer.resize(file, function(imageData) {
						$spinner.removeClass('pw-resizing');
						// resize completed, start upload
						sendUpload(file, imageData);
					});
				} else {
					sendUpload(file);
				}
			}

			/**
			 * Traverse files queued for upload
			 * 
			 * @param files
			 * 
			 */
			function traverseFiles(files, xhr) {

				var toKilobyte = function(i) {
					return parseInt(i / 1024, 10);
				};
				
				if(typeof files === "undefined") {
					fileList.innerHTML = "No support for the File API in this web browser";
					return;
				}
				
				for(var i = 0, l = files.length; i < l; i++) {
				
					var extension = files[i].name.split('.').pop().toLowerCase();
					var message;

					if(extensions.indexOf(extension) == -1) {
						message = extension + ' is a invalid file extension, please use one of:  ' + extensions;
						$errorParent.append(errorItem(message, files[i].name));

					} else if(!useClientResize && files[i].size > maxFilesize && maxFilesize > 2000000) {
						// I do this test only if maxFilesize is at least 2M (php default). 
						// There might (not sure though) be some issues to get that value so don't want to overvalidate here -apeisa
						var filesizeKB = toKilobyte(files[i].size),
							maxFilesizeKB = toKilobyte(maxFilesize);

						message = 'Filesize ' + filesizeKB + ' kb is too big. Maximum allowed is ' + maxFilesizeKB + ' kb';
						$errorParent.append(errorItem(message, files[i].name));
						
					} else if(typeof xhr != "undefined") {
						uploadFile(files[i], extension, xhr);

					} else {
						uploadFile(files[i], extension);
					}
					
					if(maxFiles == 1) break;
				}
			}

			filesUpload.addEventListener("change", function(evt) {
				traverseFiles(this.files);
				evt.preventDefault();
				evt.stopPropagation();
				this.value = '';
			}, false);
			
		}
		

		/**
		 * Setup dropzone within an .InputfieldImageEdit panel so one can drag/drop new photo into existing image enlargement
		 * 
		 * This method populates the uploadReplace variable
		 * 
		 */
		function setupEnlargementDropzones() {
			var sel = ".InputfieldImageEdit__imagewrapper img";
			$(document).on("dragenter", sel, function() {
				var $this = $(this);	
				if($this.closest('.InputfieldImageMax1').length) return;
				var src = $this.attr('src');
				var $edit = $this.closest(".InputfieldImageEdit");
				var $parent = $this.closest(".InputfieldImageEdit__imagewrapper");
				$parent.addClass('InputfieldImageEdit__replace');
				uploadReplace.file = new String(src).substring(src.lastIndexOf('/') + 1);
				uploadReplace.item = $('#' + $edit.attr('data-for')); 
				uploadReplace.edit = $edit;
			}).on("dragleave", sel, function() {
				var $this = $(this);
				if($this.closest('.InputfieldImageMax1').length) return;
				var $parent = $this.closest(".InputfieldImageEdit__imagewrapper");
				$parent.removeClass('InputfieldImageEdit__replace');
				uploadReplace.file = '';
				uploadReplace.item = null;
				uploadReplace.edit = null;
			});
		}
		setupEnlargementDropzones();
		
	} // initUploadHTML5
	
	function getClientResizeSettings($inputfield) {
		
		var settings = {
			maxWidth: 0,
			maxHeight: 0,
			maxSize: 0, 
			quality: 1.0,
			autoRotate: true,
			debug: ProcessWire.config.debug
		};
	
		var data = $inputfield.attr('data-resize');

		if(typeof data != "undefined" && data.length) {
			data = data.split(';');
			settings.maxWidth = data[0].length ? parseInt(data[0]) : 0;
			settings.maxHeight = data[1].length ? parseInt(data[1]) : 0;
			settings.maxSize = data[2].length ? parseFloat(data[2]) : 0;
			settings.quality = parseFloat(data[3]);
		}
		
		return settings;
	}
	
	/**
	 * Initialize InputfieldImage
	 * 
	 */
	function init() {
		
		// initialize all grid images for sortable and render value mode (if applicable)
		$('.InputfieldImage.Inputfield').each(function() {
			initInputfield($(this));
		});

		initGridEvents()
	
		// Initialize Upload 
		if(useAjaxUpload()) {
			initUploadHTML5('');
		} else {
			initUploadOldSchool();
		}
		
		$(document).on('reloaded', '.InputfieldImage', function() {
			var $inputfield = $(this);
			initInputfield($inputfield);
			initUploadHTML5($inputfield);
			//console.log('InputfieldImage reloaded');
		}).on('wiretabclick', function(e, $newTab, $oldTab) {
			$newTab.find(".InputfieldImage").each(function() {
				initInputfield($(this));
			});
		}).on('opened', '.InputfieldImage', function() {
			//console.log('InputfieldImage opened');
			initInputfield($(this));
		});
	}
	
	init();
}

jQuery(document).ready(function($) {
	InputfieldImage($);
});
