
function enablePWImageDialogButtons() {
	var $buttonPane = parent.jQuery(".ui-dialog-buttonpane");
	$buttonPane.find('button').button("enable");
	return;
}

function disablePWImageDialogButtons() {
	var $buttonPane = parent.jQuery(".ui-dialog-buttonpane");
	$buttonPane.find('button').button("disable");
	return;
}

function closePWImageDialog() {
	parent.jQuery('iframe.ui-dialog-content').dialog('close'); 
}

function setupProcessSaveReloaded(fileID, isNew) {
	if(isNew) {
		var offsetTop = parent.jQuery('#' + fileID).offset().top - 20;
		parent.jQuery('html, body').animate({ scrollTop: offsetTop }, 1000, 'swing');
		parent.jQuery('#' + fileID).hide();
		setTimeout(function() { parent.jQuery('#' + fileID).slideDown(); }, 900);
	} else {
		parent.jQuery('#' + fileID).find('img').hide();
		setTimeout(function() {
			parent.jQuery('#' + fileID).find('img').fadeIn('normal', function() {
				parent.jQuery('#' + fileID).find('.gridImage__edit').trigger('click');
			});
			//if($item2.length) $item2.fadeIn();
		}, 500);
	}
	closePWImageDialog();
}

function setupProcessSave(fieldName, fileID, isNew) {
	var finished = false;
	var $inputfield = parent.jQuery('#wrap_Inputfield_' + fieldName);
	if(!$inputfield.length) {
		$inputfield = parent.jQuery('#' + fileID).closest('.InputfieldImage');
	}
	$inputfield.trigger('reload');
	parent.jQuery('.Inputfield').on('reloaded', function() {
		if(finished) return;
		finished = true;
		if(fileID.length > 0) {
			setTimeout(function () {
				setupProcessSaveReloaded(fileID, isNew);
			}, 250);
		}
	});
}

function refreshPageEditField(fieldName) {
	parent.jQuery('#wrap_Inputfield_' + fieldName).trigger('reload');
}

function setupExecuteVariations() {

	$(document).on('click', 'input#delete_all', function(event) {
		if($(this).is(":checked")) {
			$("input.delete").prop('checked', true);
		} else {
			$("input.delete").prop('checked', false);
		}
		event.stopPropagation();
	});

	var magnificOptions = {
		type: 'image',
		closeOnContentClick: true,
		closeBtnInside: true
	};
	$("a.preview").magnificPopup(magnificOptions);

	// update variation counter in parent window
	var $varcnt = $("#varcnt_id");
	var varcntID = $varcnt.val();
	var varcnt = $varcnt.attr('data-cnt');
	window.parent.jQuery("#" + varcntID).text(varcnt);
}

function setupSelectedImage() {
	
	var croppingActive = false;
	var inputPixelsActive = false;
	var $form = $("#selected_image_settings"); 
	var $container = $("#selected_image_container");
	var $img = $("#selected_image");
	var $hidpi = $("#selected_image_hidpi"); 
	var fullWidth; // full/original width when not resized
	var minWidth = 0; //parseInt($("#input_width").data('min'));
	var minHeight = 0; // parseInt($("#input_height").data('min'));
	
	function setupImage($img) {

		var originalWidth = $img.width();
		var maxWidth = 9999; // $("#input_width").data('max');
		var maxHeight = 9999; // $("#input_height").data('max');
		
		
		function updateHidpiCheckbox(w) {
			if(w < (fullWidth - (fullWidth * 0.2))) {
				if(!$hidpi.is(":visible")) $hidpi.closest('label').fadeIn();
				$hidpi.prop('disabled', false); 
			} else {
				$hidpi.prop('disabled', true); 
				if($hidpi.is(":visible")) $hidpi.closest('label').fadeOut();
			}
		}

		function populateResizeDimensions() {
			
			var w = Math.round($img.width());
			var h = Math.round($img.height());
			var $link = $("#wrap_link_original"); 

			if((h >= maxHeight || w >= maxWidth) && $form.hasClass('croppable')) {
				w = maxWidth;
				h = maxHeight;
				// $("#selected_image_link").removeAttr('checked'); // JQM
				$("#selected_image_link").prop('checked', false);
				$link.hide();
			} else {
				if(!$link.is(":visible")) {
					$link.fadeIn();
					if($link.attr('data-was-checked') == 1) {
						// $link.attr('checked', 'checked'); // JQM
						$link.prop('checked', true);
					}
				}
			}

			$("#input_width").val(w);
			$("#input_height").val(h);

			$img.attr('width', w);
			$img.attr('height', h);
			
			updateHidpiCheckbox(w); 
		
			var $latin = $("#latin"); 
			if($latin.is(":visible")) $latin.height(h);

			if(!$form.hasClass('rte')) {
				var $useResize = $("#selected_image_resize");
				if (originalWidth <= w) {
					$useResize.hide();
				} else {
					if (!$useResize.is(":visible")) $useResize.fadeIn();
				}
			}
			
		}

		function setupImageResizable() {
			//$img.resizable('destroy');
			$img.resizable({
				aspectRatio: true,
				handles: "n, ne, e, se, s, sw, w",
				alsoResize: "#selected_image_container",
				maxWidth: maxWidth,
				maxHeight: maxHeight,
				minWidth: 10, //minWidth < 10 ? 10 : minWidth,
				minHeight: 10, //minHeight < 10 ? 10 : minHeight, 
				start: function() {
					$form.addClass('resizing_active'); 
				},
				stop: function() {
					$img.attr('width', $img.width()).attr('height', $img.height());
					if(originalWidth != $img.width()) {
						$img.addClass('resized');
						if(!$form.hasClass('rte')) {
							var $resizeYes = $("#selected_image_resize_yes");
							if (!$resizeYes.is(":checked")) {
								// $resizeYes.attr('checked', 'checked'); // JQM
								$resizeYes.prop('checked', true);
								// $("#selected_image_resize_no").removeAttr('checked'); // JQM
								$("#selected_image_resize_no").prop('checked', false);
							}
						}
					}
					$form.removeClass('resizing_active'); 
					if($("#resize_action").hasClass('on')) $("#resize_action").trigger('click').mouseout();
				},
				resize: populateResizeDimensions
			});
			$img.addClass('resizable_setup');
		}
	
		var cropData = null;
		
		function setupImageCroppable() {
			
			var cropButtons = [ {
				html: $("#button_crop").html(),
				click: function() { $("#button_crop").trigger('click'); }
			}, {
				html: $("#button_cancel_crop").html(),
				click: function() { $("#button_cancel_crop").trigger('click'); },
				'class': 'ui-priority-secondary'
			}];
			
			$(".show_when_crop").hide();
			
			$("#crop_action, .crop_trigger").on('click', function(e) {
				
				var recrop = $(this).attr('data-recrop');
				if(recrop && recrop.length > 0) {
					// redirect to crop original 
					window.location.assign(recrop);
					return true;
				}

				if(!$form.hasClass('croppable')) return;
				if(croppingActive) return false;

				croppingActive = true;
				$("#selected_image_settings").addClass('cropping_active'); 
				$(".hide_when_crop").hide();
				$(".show_when_crop").show();
				if($img.hasClass('resizable_setup')) $img.resizable('destroy');
		
				var cropSettings = {
					autoCrop: true,
					autoCropArea: 0.35,
					zoomable: false,
					rotatable: false,
					maxWidth: $img.attr('data-origwidth'), 
					maxHeight: $img.attr('data-origheight'),
					minCropBoxWidth: (minWidth < 2 ? 0 : minWidth),
					minCropBoxHeight: (minHeight < 2 ? 0 : minHeight),
					minWidth: (minWidth < 2 ? 0 : minWidth),
					minHeight: (minHeight < 2 ? 0 : minHeight), 
					done: function(data) {
						$("#crop_x").val(Math.floor(data.x));
						$("#crop_y").val(Math.floor(data.y));
						$("#crop_w").val(Math.floor(data.width));
						$("#crop_h").val(Math.floor(data.height));
						cropData = data;
					}
				};
			
				// predefined crop settings
				var crop = $img.attr('data-crop');
				if(crop && crop.length > 0) {
					crop = crop.split(',');
					cropSettings.data = {
						x: crop[0],
						y: crop[1],
						width: crop[2],
						height: crop[3]
					}
					setTimeout(function() { 
						disablePWImageDialogButtons(cropButtons); 
					}, 1000); 
				} else {
					disablePWImageDialogButtons(cropButtons);
				}

				$img.cropper(cropSettings);
				setTimeout(function() {
					// adjustment for width/height error on images under 190px in either dimension
					$(".cropper-canvas").width($(".cropper-container").width())
						.height($(".cropper-container").height());
				}, 500); 
				
				var cropCoordinatesChange = function() {
					var data = {
						x: parseInt($("#crop_x").val()),
						y: parseInt($("#crop_y").val()),
						width: parseInt($("#crop_w").val()),
						height: parseInt($("#crop_h").val()),
						rotate: 0
					};
					$img.cropper('setData', data);
				}
				
				$("#crop_coordinates input").on('change', cropCoordinatesChange);
			}); 
			
			function stopCrop() {
				$img.cropper("destroy");
				$(".show_when_crop").hide();
				$(".hide_when_crop").show();
				croppingActive = false;
				$("#selected_image_settings").removeClass('cropping_active'); 
				setupImageResizable();
				enablePWImageDialogButtons();
			}
			
			$("#button_cancel_crop").on('click', function() { stopCrop(); });
			$("#button_crop").on('click', function() { 
				if($form.hasClass('processing')) return false;
				$form.addClass('processing');
				return true; 
			});

			// see if there's a defined pre-crop to start with 
			if($img.attr('data-crop')) {
				$("#crop_action").trigger('click');
			}
		

		}
		
		function inputPixelsChange(event) {
			
			if(inputPixelsActive) return; 
			if($(this).parents("#crop_coordinates").length) return;
			
			inputPixelsActive = true;

			var w, h, 
				abort = false,
				noChange = false, 
				oldWidth = $img.attr('width'),
				oldHeight = $img.attr('height'),
				origWidth = parseInt($img.attr('data-origwidth')),
				origHeight = parseInt($img.attr('data-origheight'));
		
			oldWidth = typeof oldWidth == "undefined" ? $img.width() : parseInt(oldWidth);
			oldHeight = typeof oldHeight == "undefined" ? $img.height() : parseInt(oldHeight);
		
			if($(this).attr('id') == 'input_width') {
				w = parseInt($(this).val());
				h = (origHeight / (origWidth / w));
				if(w == oldWidth) noChange = true;
			} else {
				h = parseInt($(this).val());
				w = Math.round((h / oldHeight) * oldWidth);
				w = (origWidth / (origHeight / h));
				if(h == oldHeight) noChange = true;
			}

			if(w < 1 || h < 1 || noChange) {
				// requested dimension too small, or image already at requested dimension
				abort = 1;
			} else if(maxWidth > 0 && w > maxWidth) {
				// requested dimension exceeds maximum
				abort = 2;
			} else if((minWidth > 1 && w < minWidth) || (minHeight > 1 && h < minHeight)) {
				// requested dimension smaller than minimum allowed
				abort = 3;
			} 
		
			if(abort) {
				$("#input_width").val(Math.round(oldWidth));
				$("#input_height").val(Math.round(oldHeight));
				inputPixelsActive = false;
				return false;
			}
			
			var wRounded = Math.round(w);
			var hRounded = Math.round(h);

			setupImageResizable();
			$("#input_height").val(hRounded);
			$container.width(w).height(h);
			$img.parent('.ui-wrapper').width(w).height(h); 
			$img.width(w).height(h)
				.attr('width', wRounded).attr('height', hRounded)
				.addClass('resized');
			populateResizeDimensions();
			inputPixelsActive = false;
		}
		
		function alignClassChange() {
			var resized = $img.is(".resized");
			$img.attr('class', $(this).val());
			$container.attr('class', $(this).val());
			if(resized) $img.addClass('resized');
			var _float = $container.css('float');
			var $latin = $("#latin");
			if(_float == 'left' || _float == 'right') {
				if(!$latin.is(":visible")) {
					$latin.height($container.height());
					$latin.fadeIn();
				}
			} else {
				if($latin.is(":visible")) $latin.hide();
			}
			setupImageResizable();
		}
		
		function setupImageActions() {
			
			$('#max_action').on('click', function() {
				var origWidth = parseInt($img.attr('data-origwidth')); 
				if(origWidth > maxWidth) origWidth = maxWidth;
				//console.log('origWidth=' + origWidth);
				if(origWidth > $(window).width()) {
					// new width exceeds window size
					$('#content').css('overflow-x', 'auto');
				}
				$("#input_width").val(origWidth).trigger('change');
			});
			
			$('#min_action').on('click', function() {
				var imgWidth = $img.width();
				var imgHeight = $img.height();
				var windowWidth = $(window).width() - 30;
				var windowHeight = $(window).height() - $("#wrap_info").height() - 60;
				var updated = false;
				
				if(imgHeight > windowHeight) {
					$("#input_height").val(windowHeight).trigger('change');
					updated = true; 
				}
				if(imgWidth > windowWidth) {
					$("#input_width").val(windowWidth).trigger('change');
					updated = true; 
				}
				
				if(!updated) {
					// downscale 50%
					$("#input_width").val(Math.ceil(imgWidth / 2)).trigger('change');
				}
			}); 
			
			$("#align_left_action, #align_center_action, #align_right_action").on('click', function() {
				
				var $select = $("#selected_image_class"); 
				var labelKey = $(this).attr('data-label'); 
				
				if($(this).hasClass('on')) {
					// remove alignment
					$select.children("option").removeAttr('selected');
					$(this).removeClass('on');
					
				} else {
					// set alignment
					$(this).siblings('.on').removeClass('on');
					$select.children("option").removeAttr('selected');
					$select.find("option[data-label=" + labelKey + "]").attr('selected', 'selected');
					$(this).addClass('on');
				}
				
				$select.trigger('change');
			});
			
			// set current 'on' alignment icon
			var labelKey = $("#selected_image_class").find("option[selected=selected]").attr('data-label'); 
			if(labelKey) $("#action_icons").find("span[data-label=" + labelKey + "]").addClass('on'); 
			
			$("#resize_action").on('mouseenter', function() {
				if($(this).hasClass('on')) return;
				$("#resize_tips").show();
				$("#input_width, #input_height").addClass('ui-state-highlight'); 
			}).on('mouseleave', function() {
				if($(this).hasClass('on')) return;
				$("#resize_tips").hide();
				$("#input_width, #input_height").removeClass('ui-state-highlight'); 
			}).on('click', function() {
				if($(this).hasClass('on')) {
					$(this).removeClass('on');
					$("#input_width, #input_height").removeClass('ui-state-highlight'); 
				} else {
					$(this).addClass('on');
					$("#input_width, #input_height").addClass('ui-state-highlight'); 
				}
			}); 
			
			$("#description_action").on('click', function() {
				if($(this).hasClass('on')) {
					$(this).removeClass('on'); 
					$("#wrap_description").slideUp('fast');
				} else {
					$(this).addClass('on'); 
					$("#wrap_description").slideDown('fast');
				}
			}); 
	
			/*
			$("#rotate_right_action, #rotate_left_action").on('click', function() {
				$img.resizable('destroy');
				var w = $img.width();
				var h = $img.height();
				var rotate = parseInt($("#selected_image_rotate").val());
				if($(this).is("#rotate_right_action")) rotate += 90;
					else rotate -= 90;
				if(rotate > 270) rotate = 0;
				if(rotate < -270) rotate = 0;
				$("#selected_image_rotate").val(rotate);
				$img.css('margin', 0);
			
				if(w != h) {
					if (Math.abs(rotate) == 90 || Math.abs(rotate) == 270) {
						var diff = (w - h) / 2;
						$img.css('margin-left', (-1 * diff) + 'px');
						$img.css('margin-top', diff + 'px');
						$container.width(h).height(w);
						$img.parent().width(h).height(w);
					} else {
						$container.width(w).height(h);
						$img.parent().width(w).height(h);
					}
				}
				if (Math.abs(rotate) == 90 || Math.abs(rotate) == 270) {
					$("#resize_action, #crop_action, #min_action, #max_action").hide();
				} else {
					$("#resize_action, #crop_action, #min_action, #max_action").show();
				}
				
				$img.removeClass('rotate90 rotate180 rotate270 rotate-90 rotate-180 rotate-270 rotate0')
					.addClass("rotate" + rotate);
				
				// setupImageResizable();
			}); 
			
			$("#flip_vertical_action").on('click', function() {
				$img.removeClass('flip_horizontal').toggleClass('flip_vertical');
				$(this).toggleClass('on'); 
				$("#flip_horizontal_action").removeClass('on');
			});
			$("#flip_horizontal_action").on('click', function() {
				$img.removeClass('flip_vertical').toggleClass('flip_horizontal');
				$(this).toggleClass('on');
				$("#flip_vertical_action").removeClass('on');
			}); 
			 */
		}
		
		function setupImageCaption() {
			$("#selected_image_caption").on('change', function() {
				if($form.hasClass('cropping_active')) return;
				var $caption = $("#caption_preview"); 
				if($(this).is(":checked")) {
					$caption.fadeIn();
				} else if($caption.is(":visible")) {
					$caption.fadeOut();
				}
			}).trigger('change');
		}
		
		function fitImageToWindow() {
			
			var winwidth = $(window).width() - 30;
			var winheight = $(window).height() - ($("#wrap_info").height() + 60);
			
			if($img.width() > winwidth) {
				$img.width(winwidth).css('height', 'auto').removeAttr('height');
				$img.removeAttr('height');
			}
			
			if($img.height() > winheight) {
				$img.removeAttr('width').css('width', 'auto').height(winheight);
			}
			
			$container.width($img.width()).height($img.height());
		}
		
		/*** INIT: setupImage ******************************************************/
			
		// adjust height of wrap_info so that there is no change when crop buttons are turned on
		//var $wrapInfo = $("#wrap_info"); 
		//$wrapInfo.css('min-height', $wrapInfo.height() + 'px'); 
		//$wrapInfo.children("span").css("min-height", $wrapInfo.height() + 'px'); 
		$("#loading_button").hide();
		
		if($img.attr('data-fit')) {
			fitImageToWindow();
		} else {
			$container.width($img.width()).height($img.height());
		}

		// assign change events
		$("#selected_image_settings .input_pixels").on('change', inputPixelsChange);
		$("#selected_image_class").on('change', alignClassChange).trigger('change');
		
		fullWidth = $img.attr('data-origwidth');
		
		populateResizeDimensions();
		setupImageCroppable();
		setupImageActions();
		setupImageCaption();
		
		$("button.submit_save_copy, button.submit_save_replace").on('click', function() {
			$form.addClass('processing'); 
			disablePWImageDialogButtons();
		}); 
	};
	
	/*** INIT **********************************************************************/

	if($img.length > 0) {
		$img = $img.first();
		if($img.width() > 0 && $img.height() > 0) {
			setupImage($img);
		} else {
			$img.on('load', function() {
				$img = $(this);
				setupImage($img);
			});
		}
	}

} // setupSelectedImage()

$(document).ready(function() {
	var $page_id = $("#page_id");
	if($page_id.length > 0) {
		var page_id = $page_id.val();
		$page_id.on("pageSelected", function (event, data) {
			if (data.id == page_id) return;
			window.location = "./?id=" + data.id + "&modal=1";
		});
	}

	if($("#selected_image").length > 0) {
		setTimeout(function() {
			setupSelectedImage();
		}, 250); 
	} else if($("#ImageVariations").length > 0) {
		setupExecuteVariations();
	}

	enablePWImageDialogButtons();

	// prevent enter from submitting any of our forms
	$(window).on('keydown', function(event){
		if(event.keyCode == 13) {
			event.preventDefault();
			return false;
		}
	});

}); 
