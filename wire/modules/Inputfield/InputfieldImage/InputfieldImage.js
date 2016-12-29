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
	var retryGridItems = [];

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
			cancel: ".InputfieldImageEdit"
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
		var wideItems = [];
		var ni = 0, wi = 0;
		var $inputfields;
	
		if(typeof $inputfield == "undefined") {
			$inputfields = $(".InputfieldImage.Inputfield");
		} else {
			$inputfields = $inputfield;
		}
	
		$inputfields.removeClass('InputfieldImageNarrow');
		
		$inputfields.each(function() {
			var $item = $(this);
			var width = $item.width();
			if(width < 1) return;
			if(width <= 500) {
				narrowItems[ni] = $item;
				ni++;
			}
		});
		
		for(var n = 0; n < ni; n++) {
			var $item = narrowItems[n];	
			$item.addClass('InputfieldImageNarrow');
		}
	}

	/**
	 * Window resize event
	 * 
	 */
	function windowResize() {
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
		
		if($el.closest('.InputfieldImageEditAll').length) return;
		
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
			//.addClass('magnificInit');

		// move all of the .ImageData elements to the edit panel
		$edit.find(".InputfieldImageEdit__edit")
			.attr("data-current", $el.attr("id"))
			.append($el.find(".ImageData").children().not(".InputfieldFileSort"));
	}

	/**
	 * Tear down the InputfieldImageEdit panel
	 *
	 * @param $edit
	 *
	 */
	function tearDownEdit($edit) {
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
			$(this).css('width', pct + '%');
			$(this).siblings('.ImageData').css('width', dataPct + '%');
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
				var $item = retryGridItems.pop();
				setGridSizeItem($item, gridSize, ragged);
			}
		}, 150); 
	}

	/**
	 * Update a gridImage__overflow item for the setGridSize() method
	 * 
	 * @param $item
	 * @param gridSize
	 * @param ragged
	 * 
	 */
	function setGridSizeItem($item, gridSize, ragged) {
		
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

		var w = $img.width();
		var h = $img.height();
		//if(!w) w = gridSize; // parseInt($img.attr('data-w'));
		//if(!h) h = gridSize; // parseInt($img.attr('data-h'));
		
		if(ragged) {
			$img.attr('height', gridSize).removeAttr('width');
		} else if(w >= h) {
			$img.attr('height', gridSize).removeAttr('width');
		} else if(h > w) {
			$img.attr('width', gridSize).removeAttr('height');
		} else {
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
				retryGridItems.push($item);
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
			
			$a.parent().children('.' + activeClass).removeClass(activeClass);
			$a.addClass(activeClass);
			
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
		
		$slider.slider({
			'min': min,
			'max': max, 
			'value': getCookieData($inputfield, 'size'),
			'range': 'min',
			'slide': function(event, ui) {
				
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
			},
			'start': function(event, ui) {
				if($inputfield.find(".InputfieldImageEdit:visible").length) {
					$inputfield.find(".InputfieldImageEdit__close").click();
				}
			}, 
			'stop': function(event, ui) {
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
		$.cookie('InputfieldImage', data);
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
		
		if(!size) size = $gridImages.attr('data-gridsize');
		size = parseInt(size);
		
		//console.log('initInputfield');
		//console.log($inputfield);
		setGridSize($inputfield, size, ragged);
		
		if($inputfield.hasClass('InputfieldImageEditAll') || mode == 'list') {
			var listSize = getCookieData($inputfield, 'listSize');
			setListSize($inputfield, listSize);
		}
	
		if(!$inputfield.hasClass('InputfieldImageInit')) {
			$inputfield.addClass('InputfieldImageInit');
			
			if($inputfield.hasClass('InputfieldRenderValueMode')) {
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

			setupDropzone($this);
			if(maxFiles != 1) setupDropInPlace($fileList);
			//setupDropHere();

			$fileList.children().addClass('InputfieldFileItemExisting'); // identify items that are already there

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
				return '<li>' + message + '</li>';
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
			 * @param $el
			 *
			 */
			function setupDropzone($el) {
				
				// Dropzone remains even after a 'reloaded' event, since it is the InputfieldContent container
				if($el.hasClass('InputfieldImageDropzoneInit')) return;

				var el = $el.get(0);

				el.addEventListener("dragleave", function() {
					$el.removeClass('ui-state-hover');
				}, false);
				
				el.addEventListener("dragenter", function() {
					$el.addClass('ui-state-hover');
				}, false);

				el.addEventListener("dragover", function(evt) {
					if(!$el.is('ui-state-hover')) $el.addClass('ui-state-hover');
					evt.preventDefault();
					evt.stopPropagation();
					return false;
				}, false);

				el.addEventListener("drop", function(evt) {
					traverseFiles(evt.dataTransfer.files);
					$el.removeClass("ui-state-hover");
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
					}, 1000); 
				}
				
				function drop(evt) {
					if(noDropInPlace()) return;
					
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
			 * 
			 */
			function uploadFile(file) {
			
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
				xhr = new XMLHttpRequest();

				// Update progress bar
				xhr.upload.addEventListener("progress", function(evt) {
					if(!evt.lengthComputable) return;
					$('body').addClass('pw-uploading');
					$progressBar.attr("value", parseInt((evt.loaded / evt.total) * 100));
					$spinner.css('display', 'block');
				}, false);

				// File uploaded: called for each file
				xhr.addEventListener("load", function() {
					xhr.getAllResponseHeaders();
					var response = $.parseJSON(xhr.responseText),
						wasZipFile = response.length > 1;
					if(response.error !== undefined) response = [response];
					// response = [{error: "Invalid"}];

					// note the following loop will always contain only 1 item, unless a file containing more files (ZIP file) was uploaded
					for(var n = 0; n < response.length; n++) {

						var r = response[n];
						
						if(r.error) {
							$errorParent.append(errorItem(r.message));
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
							// re-open replaced item
							$markup.find(".gridImage__edit").click();
							$markup.find(".InputfieldFileReplace").val(uploadReplace.file);
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

				// Here we go
				xhr.open("POST", postUrl, true);
				xhr.setRequestHeader("X-FILENAME", encodeURIComponent(file.name));
				xhr.setRequestHeader("X-FIELDNAME", fieldName);
				xhr.setRequestHeader("Content-Type", "application/octet-stream"); // fix issue 96-Pete
				xhr.setRequestHeader("X-" + postTokenName, postTokenValue);
				xhr.setRequestHeader("X-REQUESTED-WITH", 'XMLHttpRequest');
				xhr.send(file);

				// Present file info and append it to the list of files
				if(uploadReplace.item) {
					uploadReplace.item.replaceWith($progressItem);
					uploadReplace.item = $progressItem;
				} else if($uploadBeforeItem && $uploadBeforeItem.length) {
					$uploadBeforeItem.before($progressItem); 
				} else {
					$fileList.append($progressItem);
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

			/**
			 * Traverse files queued for upload
			 * 
			 * @param files
			 * 
			 */
			function traverseFiles(files) {

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

					} else if(files[i].size > maxFilesize && maxFilesize > 2000000) {
						// I do this test only if maxFilesize is at least 2M (php default). 
						// There might (not sure though) be some issues to get that value so don't want to overvalidate here -apeisa
						var filesizeKB = toKilobyte(files[i].size),
							maxFilesizeKB = toKilobyte(maxFilesize);

						message = 'Filesize ' + filesizeKB + ' kb is too big. Maximum allowed is ' + maxFilesizeKB + ' kb';
						$errorParent.append(errorItem(message, files[i].name));

					} else {
						uploadFile(files[i]);
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
