/**
 * pwlink plugin for TinyMCE
 *
 * @param editor
 *
 */
function pwTinyMCE_link(editor) {
	
	var $ = jQuery;
	var $iframe; // set after modalSettings 
	var selection = editor.selection;
	var node = selection.getNode();
	var nodeName = node.nodeName.toUpperCase(); // will typically be 'A', 'IMG' or 'P' 
	var selectionText = selection.getContent({ format: 'text' });
	var selectionHtml = selection.getContent();
	
	var labels = {
		insertLink: 'Insert',
		cancel: 'Cancel'
	};
	
	if(typeof ProcessWire.config.InputfieldTinyMCE.labels !== 'undefined') {
		labels = ProcessWire.config.InputfieldTinyMCE.labels; // translated text labels
	}
	
	function getPageId() {
		var $in = jQuery("#Inputfield_id");
		var pageId;
		if($in.length) {
			pageId = $in.val();
		} else {
			pageId = $("#" + editor.id).closest('.Inputfield').attr('data-pid');
		}
		return pageId;
	}
	
	// action when insert link button is clicked
	function clickInsert($iframe) {
		
		var $i = $iframe.contents();
		var $a = $($('#link_markup', $i).text());
		
		if($a.attr('href') && $a.attr('href').length) {
			if($a.text() === selectionText || !$a.text().length) {
				// if input text has not changed from original, then use the original HTML rather than the text
				$a.html(selectionHtml);
			}
			var html = $('<div />').append($a).html();
			selection.setContent(html);
		}
		
		$iframe.dialog('close');
	}
	
	function getAnchorIds() {
		var $content = $(editor.getContent());	
		var anchors = []; 
		$content.find('a').each(function() {
			var $a = $(this);
			var id = $a.attr('id');
			if(id) anchors.push(id);
		}); 
		return anchors;
	}
	
	function buildModalUrl($existingLink) {
		
		var $textarea = jQuery('#' + editor.id); // get textarea of this instance
		var $langWrapper = $textarea.closest('.LanguageSupport');
		var modalUrl = ProcessWire.config.urls.admin + 'page/link/?modal=1&id=' + getPageId();
		var n;
		
		if($langWrapper.length) {
			// multi-language field
			modalUrl += '&lang=' + $langWrapper.data('language');
		} else {
			// multi-language field in Table
			$langWrapper = $textarea.parents('.InputfieldTable_langTabs').find('li.ui-state-active a')
			if($langWrapper.length && typeof $langWrapper.data('lang') !== 'undefined') {
				modalUrl += '&lang=' + $langWrapper.data('lang');
			} else if(jQuery('#pw-edit-lang').length) {
				modalUrl += '&lang=' + $('#pw-edit-lang').val(); // front-end editor
			}
		}
		
		if($existingLink != null) {
			var attrs = ['href', 'title', 'class', 'rel', 'target'];
			for(n = 0; n < attrs.length; n++) {
				var val = $existingLink.attr(attrs[n]);
				if(val && val.length) modalUrl += '&' + attrs[n] + '=' + encodeURIComponent(val);
			}
		}
		
		// add any anchors to the modal URL
		var anchors = getAnchorIds();
		if(anchors.length > 0) {
			for(n = 0; n < anchors.length; n++) {
				modalUrl += '&anchors[]=' + encodeURIComponent(anchors[n]);
			}
		}
		
		// set link text
		var linkText = ($existingLink && $existingLink.text().length) ? $existingLink.text() : selectionText;
		
		if(nodeName !== 'IMG' && linkText.length) {
			modalUrl += '&text=' + encodeURIComponent(linkText);
		}
	
		return modalUrl;
	}
	
	function buildModalSettings() {
		return {
			title: "<i class='fa fa-link'></i> " + labels.insertLink,
				open: function() {
				/*
				if($(".cke_maximized").length > 0) {
					// the following is required when CKE is maximized to make sure dialog is on top of it
					$('.ui-dialog').css('z-index', 9999);
					$('.ui-widget-overlay').css('z-index', 9998);
				}
				 */
			},
			buttons: [{
				'class': "pw_link_submit_insert",
				'html': "<i class='fa fa-link'></i> " + labels.insertLink,
				'click': function() {
					clickInsert($iframe);
				}
			}, {
				'html': "<i class='fa fa-times-circle'></i> " + labels.cancel,
				'click': function() {
					$iframe.dialog('close');
				},
				'class': 'ui-priority-secondary'
			}]
		};
	}
	
	function iframeLoad($iframe) {
		var $i = $iframe.contents();
		$i.find('#ProcessPageEditLinkForm').data('iframe', $iframe);
		
		// capture enter key in main URL text input
		$('#link_page_url_input', $i).on('keydown', function(event) {
			var $this = $(this);
			var val = $this.val();
			val = typeof val == 'string' ? val.trim() : '';
			if(event.keyCode == 13) {
				event.preventDefault();
				if(val.length > 0) clickInsert($iframe);
				return false;
			}
		});
	}
	
	function init() {
		
		var inlineNodeNames = '/em/strong/i/b/u/s/span/small/abbr/cite/figcaption/';
		var $existingLink = null;
		
		if(nodeName != 'A' && nodeName != 'IMG') {
			var parentNode;
			var parentNodeName;
			do {
				parentNode = node.parentNode;
				if(!parentNode) break;
				parentNodeName = parentNode.nodeName.toUpperCase();
				if(parentNodeName === 'A') {
					// if there is a parent <a> element then expand selection to include all of it
					// this prevents double click on the <em> part of <a href='./'>foo <em>bar</em> baz</a> from
					// just including the 'bar' as the link text
					node = parentNode;
					break;
				} else if(inlineNodeNames.indexOf('/' + parentNodeName + '/') > -1 && $(node).text() === selectionText) {
					// include certain wrapping inline elements for formatting in the selection text
					node = parentNode;
					selection.select(node);
				} else {
					node = parentNode;
				}
			} while(parentNode);
		}
		
		nodeName = node.nodeName.toUpperCase(); // in case it changed above
		
		if(nodeName === 'A') {
			// existing link
			$existingLink = $(node);
			selectionText = $existingLink.text();
			selectionHtml = $existingLink.html();
			selection.select(node);
			
		} else if(nodeName === 'TD' || nodeName === 'TH' || nodeName === 'TR') {
			var firstChar = selectionText.substring(0,1);
			if(firstChar === "\n" || firstChar === "\r") {
				ProcessWire.alert('Your selection includes part of the table. Please try selecting the text again.');
				return;
			}
			
		} else if(nodeName === 'IMG') {
			// linked image
			var $img = $(node);
			$existingLink = $img.parent('a');
			selectionText = node.outerHTML;
			selectionHtml = selectionText;
			
		} else if(selectionText.length < 1) {
			// If not on top of link and there is no text selected - just return (don't load iframe at all)
			return;
			
		} else {
			// new link
		}
		
		// settings for modal window
		var modalUrl = buildModalUrl($existingLink);
		var modalSettings = buildModalSettings();
		
		// create modal window
		$iframe = pwModalWindow(modalUrl, modalSettings, 'medium');
		
		// modal window load event
		$iframe.on('load', function() { iframeLoad($iframe) });
	}

	init();
}

/**
 * Add pwimage to TinyMCE plugin manager
 *
 */
tinymce.PluginManager.add('pwlink', (editor, url) => {
	editor.ui.registry.addButton('pwlink', {
		text: '',
		icon: 'link',
		onAction: function() {
			pwTinyMCE_link(editor);
		}
	});
	// Adds a menu item, which can then be included in any menu via the menu/menubar configuration 
	editor.ui.registry.addMenuItem('pwlink', {
		text: 'Link',
		icon: 'link',
		onAction: function() {
			pwTinyMCE_link(editor);
		}
	});
	// context menu
	editor.ui.registry.addContextMenu('pwlink', {
		update: (element) => !element.href ? '' : 'pwlink'
	});
	// double click on link loads link editor
	editor.on('dblclick', function(e) {
		if(e.target.nodeName === 'A') {
			pwTinyMCE_link(editor);
		}
	}); 
	// Return metadata for the plugin 
	return {
		getMetadata: () => ({ name: 'Link' })
	};
});