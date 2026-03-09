$(document).ready(function() {

	/**
	 * Setup a live change event for the delete links
	 *
	 */
	
	// not IE < 9
	$(document).on('change', '.InputfieldFileDelete input', function() {
		setInputfieldFileStatus($(this));

	}).on('dblclick', '.InputfieldFileDelete', function() {
		// enable double-click to delete all
		var $input = $(this).find('input'); 
		var $items = $(this).parents('.InputfieldFileList').find('.InputfieldFileDelete input');
		if($input.is(":checked")) {
			$items.prop('checked', false).trigger('change'); 
		} else {
			$items.prop('checked', true).trigger('change'); 
		}
		return false; 
	}); 

	function setInputfieldFileStatus($t) {
		var $info = $t.parents('.InputfieldFileInfo');	
		// collapsed=items that have no description or tags, so need no visible InputfieldFileData container
		var collapsed = $t.closest('.InputfieldFile').hasClass('InputfieldItemListCollapse');
		if($t.is(":checked")) {
			// not an error, but we want to highlight it in the same manner
			$info.addClass("ui-state-error");
			if(!collapsed) $info.siblings(".InputfieldFileData").slideUp("fast");

		} else {
			$info.removeClass("ui-state-error");
			if(!collapsed) $info.siblings(".InputfieldFileData").slideDown("fast");
		}	
	}

	/**
	 * Make the lists sortable and hoverable
	 *
	 */
	function initSortable($fileLists) { 

		$fileLists.each(function() {
			
			var $this = $(this);
			var qty = $this.children("li").length;
			if($this.closest('.InputfieldRenderValueMode').length) return;
			
			var $inputfield = $this.closest('.Inputfield')
		
			if(qty < 2) {
				// added to support additional controls when multiple items are present 
				// and to hide them when not present
				if(qty == 0) $inputfield.addClass('InputfieldFileEmpty').removeClass('InputfieldFileMultiple InputfieldFileSingle');
					else $inputfield.addClass('InputfieldFileSingle').removeClass('InputfieldFileEmpty InputfieldFileMultiple');
				// if we're dealing with a single item list, then don't continue with making it sortable
				return;
			} else {
				$this.closest('.Inputfield').removeClass('InputfieldFileSingle InputfieldFileEmpty').addClass('InputfieldFileMultiple');
			}

			$this.sortable({
				//axis: 'y', 
				start: function(e, ui) {
					ui.item.children(".InputfieldFileInfo").addClass("ui-state-highlight"); 
				}, 
				stop: function(e, ui) {
					$(this).children("li").each(function(n) {
						$(this).find(".InputfieldFileSort").val(n); 
					}); 
					ui.item.children(".InputfieldFileInfo").removeClass("ui-state-highlight"); 
					// Firefox has a habit of opening a lightbox popup after a lightbox trigger was used as a sort handle
					// so we keep a 500ms class here to keep a handle on what was a lightbox trigger and what was a sort
					$inputfield.addClass('InputfieldFileJustSorted InputfieldStateChanged'); 
					setTimeout(function() { $inputfield.removeClass('InputfieldFileJustSorted'); }, 500); 
				},
				update: function(e, ui) {
					$inputfield.trigger('sorted', [ ui.item ]); 
				}
			});

		}).find(".ui-widget-header, .ui-state-default").on('mouseenter', function() {
			$(this).addClass('ui-state-hover'); 
		}).on('mouseleave', function() {
			$(this).removeClass('ui-state-hover'); 
		});
	}

	function InitOldSchool() {
		$("body").addClass("ie-no-drop"); // ??

		$(document).on('change', '.InputfieldFileUpload input[type=file]', function() {
		
			var $t = $(this);
			var $mask = $t.closest(".InputMask");
			
			if($t.val().length > 1) {
				$mask.addClass("ui-state-disabled");
			} else {
				$mask.removeClass("ui-state-disabled");
			}

			if($mask.next(".InputMask").length > 0) return; // not the last one
		
			var $inputfield = $t.closest('.InputfieldFile');
			var $upload = $t.closest('.InputfieldFileUpload');
			var $list = $inputfield.find('.InputfieldFileList');
			var maxFiles = parseInt($upload.find('.InputfieldFileMaxFiles').val());
			var numFiles = $list.children('li').length + $upload.find('input[type=file]').length + 1;
			var maxFilesize = parseInt($upload.attr('data-maxfilesize'));
			
			var abort = false;
			$upload.find("input[type=file]").each(function() {
				if(typeof this.files[0] !== 'undefined'){
					var size = this.files[0].size;
					if(size > maxFilesize) {
						ProcessWire.alert(
							"File " + this.files[0].name +" is " + size + " bytes which exceeds max allowed size of " + maxFilesize + " bytes"
						);
						$(this).val('').closest('.InputMask').removeClass('ui-state-disabled ui-state-active');
						abort = true;
					}
				}
			});
			if(abort) return false;
			
			if(maxFiles > 0 && numFiles >= maxFiles) {
				// no more files allowed
			} else {
				$upload.find(".InputMask").not(":last").each(function() {
					var $m = $(this);
					if($m.find("input[type=file]").val() < 1) $m.remove();
				});

				// add another input
				var $mask2 = $mask.clone().removeClass("ui-state-disabled");
				var $input = $mask2.find('input[type=file]');
				$input.attr('id', $input.attr('id') + '-');
				$input.val('');
				$mask2.insertAfter($mask);
				$mask2.css('margin-left', '0.5em').removeClass('ui-state-active');
			}
		
			// update file input to contain file name
			var name = $t.val();
			var pos = name.lastIndexOf('/');
			if(pos === -1) pos = name.lastIndexOf('\\');
			name = name.substring(pos+1);
			$mask.find('.ui-button-text').text(name).prepend("<i class='fa fa-fw fa-file-o'></i>");
			$mask.removeClass('ui-state-active');
			
		});
	}

	/**	
	 * Initialize HTML5 uploads 
	 *
	 * By apeisa with additional code by Ryan
	 * 
	 * Based on the great work and examples of Craig Buckler (http://www.sitepoint.com/html5-file-drag-and-drop/)
	 * and Robert Nyman (http://robertnyman.com/html5/fileapi-upload/fileapi-upload.html)
	 * 	
	 */
	function InitHTML5($inputfield) {

		if($inputfield.length > 0) {
			var $target = $inputfield.find(".InputfieldFileUpload"); // just one
		} else {
			var $target = $(".InputfieldFileUpload"); // all 
		}
		$target.closest('.InputfieldContent').each(function (i) {
			if($(this).hasClass('InputfieldFileInit')) return;
			initHTML5Item($(this), i);
			$(this).addClass('InputfieldFileInit');
		});
			
		function initHTML5Item($this, i) {

			var $form = $this.parents('form'); 
			var $repeaterItem = $this.closest('.InputfieldRepeaterItem');
			var $uploadData = $this.find('.InputfieldFileUpload');
			var postUrl = $uploadData.data('posturl');
			
			if($repeaterItem.length) {
				postUrl = $repeaterItem.attr('data-editUrl');
			} else if(!postUrl) {
				postUrl = $form.attr('action');
			}
			
			postUrl += (postUrl.indexOf('?') > -1 ? '&' : '?') + 'InputfieldFileAjax=1';
			var $f = $('#Inputfield_id');
			if($f.length) postUrl += '&eid=' + $f.val();

			// CSRF protection
			var $postToken = $form.find('input._post_token'); 
			var postTokenName = $postToken.attr('name');
			var postTokenValue = $postToken.val();

			var fieldName = $uploadData.data('fieldname');
			if(fieldName.indexOf('[') > -1) fieldName = fieldName.slice(0,-2);

			var extensions = $uploadData.data('extensions').toLowerCase();
			var maxFilesize = $uploadData.data('maxfilesize');
			
			var filesUpload = $this.find("input[type=file]").get(0);
			var dropArea = $this.get(0);
			var $fileList = $this.find(".InputfieldFileList"); 

			if($fileList.length < 1) {
				$fileList = $("<ul class='InputfieldFileList InputfieldFileListBlank'></ul>");
				$this.find('.InputfieldFileListPlaceholder').replaceWith($fileList);
				$this.parent('.Inputfield').addClass('InputfieldFileEmpty'); 
			}

			var fileList = $fileList.get(0);
			var maxFiles = parseInt($this.find('.InputfieldFileMaxFiles').val()); 
			
			$fileList.children().addClass('InputfieldFileItemExisting'); // identify items that are already there

			$this.find('.AjaxUploadDropHere').show();
			
			var doneTimer = null; // for AjaxUploadDone event
			
			function uploadFile(file) {

				var $progressItem = $('<li class="InputfieldFile ui-widget AjaxUpload"><p class="InputfieldFileInfo ui-widget ui-widget-header InputfieldItemHeader"></p></li>'),
					$progressBar = $('<div class="ui-progressbar ui-widget ui-widget-content ui-corner-all" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>'),
					$progressBarValue = $('<div class="ui-progressbar-value ui-widget-header InputfieldItemHeader ui-corner-left" style="width: 0%; "></div>'),
					img,
					reader,
					xhr,
					fileData;
				
				$progressBar.append($progressBarValue);
				$progressItem.append($progressBar);
				
				// Uploading - for Firefox, Google Chrome and Safari
				xhr = new XMLHttpRequest();
				
				// Update progress bar
				xhr.upload.addEventListener("progress", function (evt) {
					if(evt.lengthComputable) {
						var completion = (evt.loaded / evt.total) * 100;
						$progressBarValue.width(completion + "%");
						if(completion > 4) {
							$progressBarValue.html("<span>" + parseInt(completion) + "%</span>");
						}
						$('body').addClass('pw-uploading');
						/*
						// code for freezing progressbar during testing
						$progressBarValue.width("60%");
						if(completion > 50) setTimeout(function() { alert('test'); }, 10); 
						*/
					} else {
						// No data to calculate on
					}
				}, false);

				
				// File uploaded: called for each file
				xhr.addEventListener("load", function() {
					xhr.getAllResponseHeaders();

					var response = JSON.parse(xhr.responseText); 
					if(response.error !== undefined) response = [response];
					
					// note the following loop will always contain only 1 item, unless a file containing more files (ZIP file) was uploaded
					for(var n = 0; n < response.length; n++) {

						var r = response[n]; 
						
						if(r.error) {
							var $pi = $progressItem.clone(); 
							$pi.find(".InputfieldFileInfo").addClass('ui-state-error'); 
							$pi.find(".InputfieldFileStats").text(' - ' + r.message); 
							$pi.find(".ui-progressbar").remove();
							$progressItem.after($pi); 

						} else {

							if(r.replace) {
								var $child = $this.find('.InputfieldFileList').children('li').first();
								if($child.length > 0) $child.slideUp('fast', function() { $child.remove(); });
							}
                           
							// ie10 file field stays populated, this fixes that
							var $input = $this.find('input[type=file]');
							if($input.val()) $input.replaceWith($input.clone(true));

							var $markup = $(r.markup);
							$markup.hide();

							// look for and handle replacements
							if(r.overwrite) {
								var basename = $markup.find('.InputfieldFileName').text();
								var $item = null;
								// find an existing item having the same basename
								$fileList.children('.InputfieldFileItemExisting').each(function() {
									if($item === null && $(this).find('.InputfieldFileName').text() == basename) {
										// filenames match
										$item = $(this);
									}
								});
								if($item !== null) {
									// found replacement
									var $newInfo = $markup.find(".InputfieldFileInfo");
									var $newLink = $markup.find(".InputfieldFileLink"); 
									var $info = $item.find(".InputfieldFileInfo"); 
									var $link = $item.find(".InputfieldFileLink"); 
									$info.html($newInfo.html() + "<i class='fa fa-check'></i>");
									$link.html($newLink.html());
									$item.addClass('InputfieldFileItemExisting'); 
									$item.effect('highlight', 500); 
								} else {
									// didn't find a match, just append
									$fileList.append($markup);
									$markup.slideDown();
									$markup.addClass('InputfieldFileItemExisting');
								}
								
							} else {
								// overwrite mode not active
								$fileList.append($markup);
								$markup.slideDown();
							}
						}
						
						setTimeout(function() {
							var $inputfields = $markup.find('.Inputfield');
							if($inputfields.length) {
								InputfieldsInit($markup.find('.Inputfields'));
								$inputfields.trigger('reloaded', ['InputfieldFileUpload']);
							}
						}, 500); 
						
					} // for

					$progressItem.remove();
					
					if(doneTimer) clearTimeout(doneTimer); 
					doneTimer = setTimeout(function() {
						$('body').removeClass('pw-uploading');
						if(maxFiles != 1 && !$fileList.is('.ui-sortable')) initSortable($fileList); 
						$fileList.trigger('AjaxUploadDone'); // for things like fancybox that need to be re-init'd
					}, 500); 

				}, false);

				// Here we go
				xhr.open("POST", postUrl, true);
				//see:https://github.com/ryancramerdesign/ProcessWire/issues/1487
				//xhr.setRequestHeader("X-FILENAME", unescape(encodeURIComponent(file.name)));
				xhr.setRequestHeader("X-FILENAME", encodeURIComponent(file.name));
				xhr.setRequestHeader("X-FIELDNAME", fieldName);
				xhr.setRequestHeader("Content-Type", "application/octet-stream"); // fix issue 96-Pete
				xhr.setRequestHeader("X-" + postTokenName, postTokenValue);
				xhr.setRequestHeader("X-REQUESTED-WITH", 'XMLHttpRequest');
				xhr.send(file);
				
				// Present file info and append it to the list of files
				fileData = '' + 
					"<i class='fa fa-fw fa-spin fa-spinner'></i> " + 
					'<span class="InputfieldFileName">' + file.name + '</span>' + 
					'<span class="InputfieldFileStats"> &bull; ' + parseInt(file.size / 1024, 10) + " kb</span>";
				
				$progressItem.find('p.ui-widget-header').html(fileData);
				$fileList.append($progressItem);
				var $inputfield = $fileList.closest('.Inputfield');
				$inputfield.addClass('InputfieldStateChanged');
				var numFiles = $inputfield.find('.InputfieldFileItem').length;
				if(numFiles == 1) {
					$inputfield.removeClass('InputfieldFileEmpty').removeClass('InputfieldFileMultiple').addClass('InputfieldFileSingle');
				} else if(numFiles > 1) {
					$inputfield.removeClass('InputfieldFileEmpty').removeClass('InputfieldFileSingle').addClass('InputfieldFileMultiple');
				}
			}
			
	
			function traverseFiles(files) {

				function errorItem(filename, message) { 
					return 	'<li class="InputfieldFile ui-widget AjaxUpload">' + 
						'<p class="InputfieldFileInfo ui-widget ui-widget-header InputfieldItemHeader ui-state-error">&nbsp; ' + filename  + ' ' + 
						'<span class="InputfieldFileStats"> &bull; ' + message + '</span></p></li>';
				}
				
				var errorMsg = '';

				if(typeof files !== "undefined") {
					for(var i=0, l=files.length; i<l; i++) {

						var extension = files[i].name.split('.').pop().toLowerCase();

						if(extensions.indexOf(extension) == -1) {
							if(typeof ProcessWire.config.InputfieldFile.labels['bad-ext'] != "undefined") {
								errorMsg = ProcessWire.config.InputfieldFile.labels['bad-ext'];
								errorMsg = errorMsg.replace('EXTENSIONS', extensions); 
							} else {
								errorMsg = extension + ' is a invalid file extension, please use one of: ' + extensions;
							}
							$fileList.append(errorItem(files[i].name, errorMsg)); 

						} else if(files[i].size > maxFilesize && maxFilesize > 2000000) {
							// I do this test only if maxFilesize is at least 2M (php default). 
							// There might (not sure though) be some issues to get that value so don't want to overvalidate here -apeisa
							var maxKB = parseInt(maxFilesize / 1024, 10);
							if(typeof ProcessWire.config.InputfieldFile.labels['too-big'] != "undefined") {
								errorMsg = ProcessWire.config.InputfieldFile.labels['too-big']; 
								errorMsg = errorMsg.replace('MAX_KB', maxKB); 
							} else {
								var fileSize = parseInt(files[i].size / 1024, 10);
								errorMsg = 'Filesize ' + fileSize +' kb is too big. Maximum allowed is ' + maxKB + ' kb';
							}
							$fileList.append(errorItem(files[i].name, errorMsg)); 

						} else {
							uploadFile(files[i]);
						}
						if(maxFiles == 1) break;
					}
				} else {
					fileList.innerHTML = "No support for the File API in this web browser";
				}	
			}
			
			filesUpload.addEventListener("change", function(evt) {
				traverseFiles(this.files);
				evt.preventDefault();
				evt.stopPropagation();
				this.value = '';
			}, false);

			dropArea.addEventListener("dragleave", function() { 
				$(this).removeClass('ui-state-hover'); 
				$(this).closest('.Inputfield').removeClass('pw-drag-in-file'); 
			}, false);
			dropArea.addEventListener("dragenter", function(evt) {
				evt.preventDefault();
				$(this).addClass('ui-state-hover'); 
				$(this).closest('.Inputfield').addClass('pw-drag-in-file');
			}, false);

			dropArea.addEventListener("dragover", function (evt) {
				if(!$(this).is('ui-state-hover')) {
					$(this).addClass('ui-state-hover');
					$(this).closest('.Inputfield').addClass('pw-drag-in-file');
				}
				evt.preventDefault();
				evt.stopPropagation();
			}, false);
			
			dropArea.addEventListener("drop", function (evt) {
				traverseFiles(evt.dataTransfer.files);
				$(this).removeClass("ui-state-hover").closest('.Inputfield').removeClass('pw-drag-in-file');
				evt.preventDefault();
				evt.stopPropagation();
			}, false);		
		} // initHTML5Item
	} // initHTML5

	/**
	 * Initialize selectize tags
	 * 
	 * @param $inputfields
	 * 
	 */
	function initTags($inputfields) {
	
		$inputfields.each(function() {

			var $inputfield = $(this);
			var $inputs = $inputfield.find('input.InputfieldFileTagsInput:not(.selectized)');
			var $selects = $inputfield.find('input.InputfieldFileTagsSelect:not(.selectized)');
			
			if($inputs.length) {
				$inputs.selectize({
					plugins: ['remove_button', 'drag_drop'],
					delimiter: ' ',
					persist: false,
					createOnBlur: true,
					submitOnReturn: false,
					create: function(input) {
						return {
							value: input,
							text: input
						}
					}
				});
			}

			if($selects.length) {
				if(!$inputfield.hasClass('Inputfield')) $inputfield = $inputfield.closest('.Inputfield');
				var configName = $inputfield.attr('data-configName');
				var settings = ProcessWire.config[configName];
				var options = [];
				if(typeof settings === 'undefined') {
					if(configName.indexOf('_repeater') > -1) {
						configName = configName.replace(/_repeater\d+(_?)/, '$1');
						settings = ProcessWire.config[configName];
						if(typeof settings === 'undefined') settings = null;
					}
				}
				if(settings) {
					for(var n = 0; n < settings['tags'].length; n++) {
						var tag = settings['tags'][n];
						options[n] = {value: tag};
					}
				}
				$selects.selectize({
					plugins: ['remove_button', 'drag_drop'],
					delimiter: ' ',
					persist: true,
					submitOnReturn: false,
					closeAfterSelect: true,
					createOnBlur: true,
					maxItems: null,
					valueField: 'value',
					labelField: 'value',
					searchField: ['value'],
					options: options,
					create: function(input) {
						return {
							value: input,
							text: input
						}
					},
					createFilter: function(input) {
						if(settings.allowUserTags) return true; 
						allow = false;
						for(var n = 0; n < options.length; n++) {
							if(input == options[n]) {
								allow = true;
								break;
							}
						}
						return allow;
					},
					onDropdownOpen: function($dropdown) {
						$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 100);	
					},
					onDropdownClose: function($dropdown) {
						$dropdown.closest('li, .InputfieldImageEdit').css('z-index', 'auto');	
					},
					render: {
						item: function(item, escape) {
							return '<div>' + escape(item.value) + '</div>';
						},
						option: function(item, escape) {
							return '<div>' + escape(item.value) + '</div>';
						}
					}
				});
			}
		});
	}

	/**
	 * MAIN
	 *
	 */

	initSortable($(".InputfieldFileList")); 
	initTags($(".InputfieldFileHasTags")); 
	
	/**
	 * Progressive enchanchment for browsers that support html5 File API
	 * 
	 * #PageIDIndictator.length indicates PageEdit, which we're limiting AjaxUpload to since only ProcessPageEdit has the ajax handler
	 * 
	 */
	var allowAjax = false;
	if (window.File && window.FileList && window.FileReader 
		&& ($("#PageIDIndicator").length > 0 || $('.InputfieldAllowAjaxUpload').length > 0)) {  
		InitHTML5('');  
		allowAjax = true;
	} else {
		InitOldSchool();
	}

	var minContainerWidth = 767; // ...or when the container width is this or smaller
	var resizeActive = false;
	
	var windowResize = function() {
		if(!allowAjax) return;
		$(".AjaxUploadDropHere").each(function() {
			var $t = $(this); 
			if($t.parent().width() <= minContainerWidth) {
				$t.hide();
			} else {
				$t.show();
			}
		}); 
		resizeActive = false;
	}

	if(allowAjax) {
		$(window).on('resize', function() {
			if(resizeActive) return;
			resizeActive = true;
			setTimeout(windowResize, 1000);
		}).trigger('resize');
		$(document).on('AjaxUploadDone', '.InputfieldFileHasTags', function(event) {
			initTags($(this));
		}); 
	}
	
	//$(document).on('reloaded', '.InputfieldFileMultiple, .InputfieldFileSingle', function(event) {
	$(document).on('reloaded', '.InputfieldHasFileList', function(event) {
		initSortable($(this).find(".InputfieldFileList"));
		InitHTML5($(this)); 
		initTags($(this));
		if(allowAjax) windowResize();
	}); 
	
}); 
