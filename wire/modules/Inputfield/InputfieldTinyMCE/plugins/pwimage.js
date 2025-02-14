/**
 * pwimage plugin for TinyMCE
 * 
 * @param editor
 * 
 */
function pwTinyMCE_image(editor) {
	
	var $ = jQuery;
	var modalUrl = ProcessWire.config.urls.admin + 'page/image/';
	var $link = null; // if img is wrapped in link, this is it
	var $figcaption = null; // <figcaption> element when applicable, null otherwise
	var $figure = null; // <figure> element when applicable, null otherwise
	var selection = editor.selection;
	var node = selection.getNode(); // <img> or <body> if inserting new image
	var nodeParent = node.parentNode; // <p>, <a> or <figure> 
	var nodeParentName = nodeParent.nodeName.toUpperCase(); // P, A, or FIGURE
	var nodeGrandparent = nodeParent.parentNode; // <figure> or <body> or <p>, <figure> only if nodeParent is <a>
	var nodeGrandparentName = nodeGrandparent ? nodeGrandparent.nodeName.toUpperCase() : ''; // FIGURE or BODY or P
	
	var labels = {
		captionText: 'Caption text', 
		savingImage: 'Saving', 
		insertImage: 'Insert',
		selectImage: 'Select',
		selectAnotherImage: 'Select another', 
		cancel: 'Cancel'
	};
	
	if(typeof ProcessWire.config.InputfieldTinyMCE.labels !== 'undefined') {
		labels = ProcessWire.config.InputfieldTinyMCE.labels; // translated text labels
	}

	/**
	 * Insert image
	 * 
	 * @param src The src attribute for the image to insert
	 * @param $i iframe contents from ProcessPageEditImageSelect to obtain image settings from
	 * 
	 */
	function insertImage(src, $i) {
		var $img = $('#selected_image', $i);
		var width = $img.attr('width');
		var alt = $("#selected_image_description", $i).val();
		var caption = $("#selected_image_caption", $i).is(":checked");
		var $hidpi = $("#selected_image_hidpi", $i);
		var hidpi = $hidpi.is(":checked") && !$hidpi.is(":disabled");
		var cls = $img
			.removeClass('ui-resizable No Alignment resizable_setup')
			.removeClass('rotate90 rotate180 rotate270 rotate-90 rotate-180 rotate-270')
			.removeClass('flip_vertical flip_horizontal')
			.attr('class');
		var $linkToLarger = $('#selected_image_link', $i);
		var linkToLargerHref = $linkToLarger.is(":checked") ? $linkToLarger.val() : ''; // link to larger version
		var $insertElement = $('<img />').attr('src', src).attr('alt', alt);
		
		if(hidpi) $insertElement.addClass('hidpi');
		
		// note: class is added to $figure (rather than <img>) when this is a caption
		if(caption === false && cls.length) $insertElement.addClass(cls);
		
		if(width > 0 && $img.attr('data-nosize') != '1') $insertElement.attr('width', width);
		
		if($link) {
			// img was wrapped in an <a>...</a> and/or <figure>
			if(linkToLargerHref) {
				// @todo verify this works and doesn't need similar solution to CKE
				// $link.attr('href', link).attr('data-cke-saved-href', link); // populate existing link with new href
			} else if($linkToLarger.attr('data-was-checked') == 1) {
				// box was checked but no longer is
				$link = null;
			}
			if($link !== null) {
				$link.append($insertElement);
				$insertElement = $link;
			}
		} else if(linkToLargerHref) {
			$insertElement = $("<a />").attr('href', linkToLargerHref).append($insertElement);
		}
		
		if(caption) {
			var $figure = $('<figure />');
			// $figure.css('width', width + 'px');
			if(cls.length) $figure.addClass(cls);
			if(!$figcaption) {
				$figcaption = $('<figcaption />');
				if(alt.length > 1) {
					$figcaption.append(alt);
				} else {
					$figcaption.append(labels.captionText);
				}
			}
			$figure.append($figcaption);
			$figure.prepend($insertElement);
			$insertElement = $figure;
		}
		
		// select the entire element surrounding the image so that we replace it all
		if(nodeGrandparentName === 'FIGURE') {
			// nodeParent is <a> while nodeGrandparent is <figure>
			selection.select(nodeGrandparent);
		} else if(nodeParentName === 'A' || nodeParentName === 'FIGURE') {
			// @todo check if this works in inline mode
			selection.select(nodeParent);
		}
		
		var html = $insertElement[0].outerHTML;
		// if(figureNodeSafari) figureNodeSafari.remove(); // Safari inserts an extra <figure>, so remove the original 
		selection.setContent(html);
		// editor.insertContent(html);
		// editor.fire('change');
	}
	
	/**
	 * Click of the "Insert image" button
	 * 
	 * @param $iframe The <iframe> element containing ProcessPageEditImageSelect
	 * 
	 */
	function insertImageButtonClick($iframe) {
		var $i = $iframe.contents();
		var $img = $('#selected_image', $i);
		var width = $img.attr('width');
		var height = $img.attr('height');
		var file = $img.attr('src');
		var imagePageId = $('#page_id', $i).val();
		var hidpi = $("#selected_image_hidpi", $i).is(":checked") ? 1 : 0;
		var rotate = parseInt($("#selected_image_rotate", $i).val());
		var version = 0;
		
		$iframe.dialog('disable');
		$iframe.setTitle(labels.savingImage); // Saving Image
		$img.removeClass("resized");
		
		if(!width) width = $img.width();
		if(!height) height = $img.height();
		
		file = file.substring(file.lastIndexOf('/')+1);
		
		if(typeof ProcessWire.config.PagesVersions !== 'undefined') {
			if(ProcessWire.config.PagesVersions.page == imagePageId) {
				version = ProcessWire.config.PagesVersions.version;
			}
		}
		
		var resizeUrl = modalUrl + 'resize' +
			'?id=' + imagePageId +
			'&file=' + file +
			'&width=' + width +
			'&height=' + height +
			'&hidpi=' + hidpi + 
			'&version=' + version;
		
		if(rotate) resizeUrl += '&rotate=' + rotate;
		
		if($img.hasClass('flip_horizontal')) {
			resizeUrl += '&flip=h';
		} else if($img.hasClass('flip_vertical')) {
			resizeUrl += '&flip=v';
		}
		
		$.get(resizeUrl, function(data) {
			var $div = $("<div></div>").html(data);
			var src = $div.find('#selected_image').attr('src');
			insertImage(src, $i);
			$iframe.dialog('close');
		});
	}
	
	/**
	 * Image editor iframe load event (ProcessPageEditImageSelect)
	 *
	 * @param $iframe
	 *
	 */
	function iframeLoad($iframe) {
		// when iframe loads, pull the contents into $i 
		var $i = $iframe.contents();
		var $img = $('#selected_image', $i);
		var buttons = [];
		
		if($img.length > 0) {
			buttons = [ {
				// Insert image button
				html: "<i class='fa fa-camera'></i> " + labels.insertImage,
				click:  function() {
					insertImageButtonClick($iframe);
				}
			}, {
				// Select another image button
				html: "<i class='fa fa-folder-open'></i> " + labels.selectAnotherImage, // "Select Another Image", 
				class: 'ui-priority-secondary',
				click: function() {
					var $i = $iframe.contents();
					var imagePageId = $('#page_id', $i).val();
					var version = 0;
					if(typeof ProcessWire.config.PagesVersions !== 'undefined') {
						if(ProcessWire.config.PagesVersions.page == imagePageId) {
							version = ProcessWire.config.PagesVersions.version;
						}
					}
					$iframe.attr('src', modalUrl + '?id=' + imagePageId + '&modal=1&version=' + version);
					$iframe.setButtons({});
				}
			} ];
			
		} else {
			// no #selected_image element on page
			// duplicate buttons in iframe and add a cancel button
			$('button.pw-modal-button, button[type=submit]:visible', $i).each(function() {
				var $button = $(this);
				var button = {
					html: $button.html(),
					click: function() {
						$button.trigger('click');
					}
				}
				buttons.push(button);
				if(!$button.hasClass('pw-modal-button-visible')) $button.hide();
			});
		}
		
		buttons.push({
			// cancel button
			html: "<i class='fa fa-times-circle'></i> " + labels.cancel, // "Cancel",
			class: 'ui-priority-secondary',
			click: function() { $iframe.dialog('close'); }
		});
		
		$iframe.setButtons(buttons);
		var title = $i.find('title').html();
		if(title.length) $iframe.setTitle(title);
	}
	
	/**
	 * build query string for initial load of ProcessPageEditImageSelect
	 *
	 * @returns {string}
	 *
	 */
	function buildQueryString() {
		var imgWidth, imgHeight, imgDescription, imgLink, imgClass, nodeClass, hidpi, file = '';
		var $editor = $('#' + editor.id);
		var $inputfield = $editor.closest('.Inputfield');
		var $node = $(node);
		var $in = $("#Inputfield_id");
		var pageId = $in.length ? $in.val() : $inputfield.attr('data-pid');
		var editPageId = pageId;
		var src = $node.attr('src');
		var queryString;
		var $repeaterItem = $inputfield.closest('.InputfieldRepeaterItem');
		
		if(src) {
			imgClass = $node.attr('class'); // class for img only
			nodeClass = $figure ? $figure.attr('class') : $node.attr('class'); // class for img OR figure
			hidpi = imgClass && imgClass.indexOf('hidpi') > -1;
			imgWidth = $node.attr('width');
			imgHeight = $node.attr('height');
			imgDescription = $node.attr('alt');
			imgLink = nodeParentName === 'A' ? $(nodeParent).attr('href') : '';
			var parts = src.split('/');
			file = parts.pop();
			parts = parts.reverse();
			var pathPageId = '';
			// pull page_id out of img[src]
			for(var n = 0; n < parts.length; n++) {
				// accounts for either /1/2/3/ or /123/ format
				if(parts[n].match(/^\d+$/)) {
					pathPageId = parts[n] + pathPageId;
				} else if(pathPageId.length) {
					break;
				}
			}
			if(pathPageId.length) pageId = parseInt(pathPageId);
		}
		
		if($repeaterItem.length && $repeaterItem.find('.InputfieldImage').length) {
			var dataPageAttr = $repeaterItem.attr('data-page');
			if(typeof dataPageAttr !== 'undefined') pageId = parseInt(dataPageAttr);
		}
		
		queryString = '?id=' + pageId + '&edit_page_id=' + editPageId + '&modal=1';
		
		if(file.length) queryString += "&file=" + file;
		if(imgWidth) queryString += "&width=" + imgWidth;
		if(imgHeight) queryString += "&height=" + imgHeight;
		if(nodeClass && nodeClass.length) queryString += "&class=" + encodeURIComponent(nodeClass);
		
		queryString += '&hidpi=' + (hidpi ? '1' : '0');
		
		if(imgDescription && imgDescription.length) {
			queryString += "&description=" + encodeURIComponent(imgDescription);
		}
		
		if($figcaption) queryString += "&caption=1";
		if(imgLink && imgLink.length) queryString += "&link=" + encodeURIComponent(imgLink);
		
		queryString += ('&winwidth=' + ($(window).width() - 30));
		
		if(typeof ProcessWire.config.PagesVersions !== 'undefined') {
			if(ProcessWire.config.PagesVersions.page == pageId) {
				queryString += '&version=' + ProcessWire.config.PagesVersions.version;
			}
		}
		
		return queryString;
	} 
	
	/**
	 * Initialize
	 *
	 */
	function init() {

		// identify if we are in a <figure>
		if(nodeGrandparentName === 'FIGURE') {
			$figure = $(nodeGrandparent.outerHTML);
		} else if(nodeParentName === 'FIGURE') {
			$figure = $(nodeParent.outerHTML);
		}
		
		if($figure) {
			$figcaption = $figure.find('figcaption');
			$figure.find('img').remove();
		}
	
		// identify if we are in an <a>
		if(nodeParentName === 'A') {
			$link = $(nodeParent.outerHTML);
			$link.find('img').remove();
		}
	
		// create iframe dialog box
		var modalSettings = {
			title: "<i class='fa fa-fw fa-folder-open'></i> " + labels.selectImage, // "Select Image", 
			open: function() {
				// if(jQuery(".cke_maximized").length > 0) {
				// the following is required when CKE is maximized to make sure dialog is on top of it
				//	jQuery('.ui-dialog').css('z-index', 9999);
				//	jQuery('.ui-widget-overlay').css('z-index', 9998);
				// }
			}
		};
		
		var $iframe = pwModalWindow(modalUrl + buildQueryString(), modalSettings, 'large');
		$iframe.on('load', function() { iframeLoad($iframe); });
	}	
	
	init();
}

/**
 * Add pwimage to TinyMCE plugin manager
 *
 */ 
tinymce.PluginManager.add('pwimage', (editor, url) => {
	editor.ui.registry.addButton('pwimage', {
		text: '',
		icon: 'image',
		onAction: function() { 
			pwTinyMCE_image(editor); 
		}
	});
	// Adds a menu item, which can then be included in any menu via the menu/menubar configuration 
	editor.ui.registry.addMenuItem('pwimage', {
		text: 'Image',
		icon: 'image',
		onAction: function() { 
			pwTinyMCE_image(editor); 
		}
	});
	// context menu
	editor.ui.registry.addContextMenu('pwimage', {
		update: (element) => !element.src ? '' : 'pwimage'
	});
	// double click on image loads image editor
	editor.on('dblclick', function(e) {
		if(e.target.nodeName === 'IMG') {
			pwTinyMCE_image(editor);
		}
	});
	// Return metadata for the plugin 
	return {
		getMetadata: () => ({ name: 'Image' })
	};
});
