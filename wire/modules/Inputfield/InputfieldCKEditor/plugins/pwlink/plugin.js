(function() {

	CKEDITOR.plugins.add('pwlink', {
		
		requires: 'dialog,fakeobjects',
		
		init: function(editor) {
			
			var allowed = 'a[!href,target,name,title,rel]';
			var required = 'a[href]';
			
			var classOptions = ProcessWire.config.InputfieldCKEditor.pwlink.classOptions;
			if(classOptions.length) allowed += "(" + classOptions + ")";

			/*
			if ( CKEDITOR.dialog.isTabEnabled( editor, 'link', 'advanced' ) )
				allowed = allowed.replace( ']', ',accesskey,charset,dir,id,lang,name,rel,tabindex,title,type]{*}(*)' );

			if ( CKEDITOR.dialog.isTabEnabled( editor, 'link', 'target' ) )
				allowed = allowed.replace( ']', ',target,onclick]' );
			*/

			// Add the link and unlink buttons.
			editor.addCommand('pwlink', {
				allowedContent: allowed,
				requiredContent: required,
				exec: loadIframeLinkPicker
				}); 

			editor.addCommand('anchor', new CKEDITOR.dialogCommand( 'anchor', {
				allowedContent: 'a[!name,id]',
				requiredContent: 'a[name]'
				}));

			editor.addCommand('unlink', new CKEDITOR.unlinkCommand());
			editor.addCommand('removeAnchor', new CKEDITOR.removeAnchorCommand());

			editor.setKeystroke( CKEDITOR.CTRL + 76 /*L*/, 'pwlink' );
			
			if ( editor.ui.addButton ) {
				editor.ui.addButton( 'PWLink', {
					label: editor.lang.link.toolbar,
					command: 'pwlink',
					toolbar: 'links,10',
					hidpi: true,
					icon: (CKEDITOR.env.hidpi ? this.path + 'images/hidpi/pwlink.png' : this.path + 'images/pwlink.png')
				});
				editor.ui.addButton( 'Unlink', {
					label: editor.lang.link.unlink,
					command: 'unlink',
					toolbar: 'links,20'
				});
				editor.ui.addButton( 'Anchor', {
					label: editor.lang.link.anchor.toolbar,
					command: 'anchor',
					toolbar: 'links,30'
				});
			}

			// On double click we execute the command (= we open modal)
			editor.on( 'doubleclick', function( evt ) {
				var element = CKEDITOR.plugins.link.getSelectedLink( editor ) || evt.data.element;
				if ( element.is( 'a' ) && !element.getAttribute('name') && !element.isReadOnly() ) {
					var $a = jQuery(element.$);
					if($a.children('img').length == 0) {
						evt.cancel(); // prevent CKE's link dialog
						editor.commands.pwlink.exec();
					}
				}
			});

			// prevent CKE's default "Edit Link" from showing in context menu
			editor.on('instanceReady', function(ck) { 
				ck.editor.removeMenuItem('link'); 
			});

			// add context menu item
			if (editor.contextMenu) {
				editor.addMenuItem('pwlinkitem', {
					label: ProcessWire.config.InputfieldCKEditor.pwlink.edit,
					command: 'pwlink',
					group: 'link',
					icon: (CKEDITOR.env.hidpi ? this.path + 'images/hidpi/pwlink.png' : this.path + 'images/pwlink.png')
				});
				editor.contextMenu.addListener(function(element) {
					if ( !element || element.isReadOnly() ) return null;
					var anchor = CKEDITOR.plugins.link.tryRestoreFakeAnchor( editor, element );
					var menu = {};
					if ( !anchor && !( anchor = CKEDITOR.plugins.link.getSelectedLink( editor ) ) ) return null;
					if ( anchor.getAttribute( 'href' ) && anchor.getChildCount() ) menu = { pwlinkitem: CKEDITOR.TRISTATE_OFF };
					return menu;
				});
			}
		}
	}); // ckeditor.plugins.add

	function loadIframeLinkPicker(editor) {

		var $in = jQuery("#Inputfield_id"); 
		if($in.length) {
			var pageID = $in.val();
		} else {
			var pageID = jQuery("#" + editor.name).closest('.Inputfield').attr('data-pid');
		}

		// language support
		var $textarea = jQuery('#' + editor.name); // get textarea of this instance
		var selection = editor.getSelection(true);
		var node = selection.getStartElement();
		var nodeName = node.getName(); // will typically be 'a', 'img' or 'p' 
		var selectionText = selection.getSelectedText();
		var $existingLink = null;
		var anchors = CKEDITOR.plugins.link.getEditorAnchors(editor); 

		if(nodeName == 'a') {
			// existing link
			$existingLink = jQuery(node.$);
			selectionText = node.getHtml();
			selection.selectElement(node);
		} else if(nodeName == 'td' || nodeName == 'th' || nodeName == 'tr') {
			var firstChar = selectionText.substring(0,1);
			if(firstChar == "\n" || firstChar == "\r") {
				ProcessWire.alert('Your selection includes part of the table. Please try selecting the text again.');
				return;
			}
		} else if(nodeName == 'img') {
			// linked image
			var $img = jQuery(node.$);
			$existingLink = $img.parent('a'); 
			selectionText = node.$.outerHTML;

		} else if (selectionText.length < 1) {
			// If not on top of link and there is no text selected - just return (don't load iframe at all)
			return;
		} else {
			// new link
		}
	
		// build the modal URL
		var modalUrl = ProcessWire.config.urls.admin + 'page/link/?id=' + pageID + '&modal=1';
		var $langWrapper = $textarea.closest('.LanguageSupport');
		
		if($langWrapper.length) {
			// multi-language field
			modalUrl += "&lang=" + $langWrapper.data("language");
		} else {
			// multi-language field in Table
			$langWrapper = $textarea.parents('.InputfieldTable_langTabs').find('li.ui-state-active a')
			if($langWrapper.length && typeof $langWrapper.data('lang') != "undefined") {
				modalUrl += "&lang=" + $langWrapper.data('lang');
			} else if(jQuery('#pw-edit-lang').length) {
				modalUrl += "&lang=" + jQuery('#pw-edit-lang').val(); // front-end editor
			}
		}
		
		if($existingLink != null) {
			var attrs = ['href', 'title', 'class', 'rel', 'target']; 
			for(var n = 0; n < attrs.length; n++) {
				var val = $existingLink.attr(attrs[n]); 	
				if(val && val.length) modalUrl += "&" + attrs[n] + "=" + encodeURIComponent(val);
			} 
		}
	
		// add any anchors to the modal URL
		if(anchors.length > 0) {
			for(var n = 0; n < anchors.length; n++) {
				modalUrl += '&anchors[]=' + encodeURIComponent(anchors[n].id); 
			}
		}
	
		// labels
		var insertLinkLabel = ProcessWire.config.InputfieldCKEditor.pwlink.label;
		var cancelLabel = ProcessWire.config.InputfieldCKEditor.pwlink.cancel;
		var $iframe; // set after modalSettings down

		// action when insert link button is clicked
		function clickInsert() {

			var $i = $iframe.contents();
			var $a = jQuery(jQuery("#link_markup", $i).text());
			if($a.attr('href') && $a.attr('href').length) {
				$a.html(selectionText);
				var html = jQuery("<div />").append($a).html();
				editor.insertHtml(html);
			}
		
			$iframe.dialog("close");
		}
	
		// settings for modal window
		var modalSettings = {
			title: "<i class='fa fa-link'></i> " + insertLinkLabel,
			open: function() {
				if(jQuery(".cke_maximized").length > 0) {
					// the following is required when CKE is maximized to make sure dialog is on top of it
					jQuery('.ui-dialog').css('z-index', 9999);
					jQuery('.ui-widget-overlay').css('z-index', 9998);
				}
			},
			buttons: [ {
				'class': "pw_link_submit_insert", 
				'html': "<i class='fa fa-link'></i> " + insertLinkLabel,
				'click': clickInsert
			}, {
				'html': "<i class='fa fa-times-circle'></i> " + cancelLabel,
				'click': function() { $iframe.dialog("close"); },
				'class': 'ui-priority-secondary'
				}
			]
		};
	
		// create modal window
		var $iframe = pwModalWindow(modalUrl, modalSettings, 'medium'); 
	
		// modal window load event
		$iframe.load(function() {
			
			var $i = $iframe.contents();
			$i.find("#ProcessPageEditLinkForm").data('iframe', $iframe);
		
			// capture enter key in main URL text input
			jQuery("#link_page_url_input", $i).keydown(function(event) {
				var $this = jQuery(this);
				var val = jQuery.trim($this.val());
				if (event.keyCode == 13) {
					event.preventDefault();
					if(val.length > 0) clickInsert();
					return false;
				}
			});

		}); // load

	} // function loadIframeLinkPicker(editor) {
	
})();
