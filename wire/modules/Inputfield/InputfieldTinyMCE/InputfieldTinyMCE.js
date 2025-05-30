/**
 * InputfieldTinyMCE.js
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 * TinyMCE 6.x, Copyright (c) 2022 Ephox Corporation DBA Tiny Technologies, Inc.
 * https://www.tiny.cloud/docs/tinymce/6/
 *
 */ 

/**
 * Handler for image uploads
 *
 * @param blobInfo
 * @param progress
 * @returns {Promise<unknown>}
 *
 */
var InputfieldTinyMCEUploadHandler = (blobInfo, progress) => new Promise((resolve, reject) => {
	
	var editor = tinymce.activeEditor;
	var $inputfield = $('#' + editor.id).closest('.InputfieldTinyMCE');
	var imageFieldName = $inputfield.attr('data-upload-field');
	var $imageInputfield = $('#wrap_Inputfield_' + imageFieldName);
	var pageId = $inputfield.attr('data-upload-page');
	var uploadUrl = ProcessWire.config.urls.admin + 'page/edit/?id=' + pageId + '&InputfieldFileAjax=1&ckeupload=1';
	
	const xhr = new XMLHttpRequest();
	
	xhr.withCredentials = true;
	xhr.upload.onprogress = (e) => { progress(e.loaded / e.total * 100); };
	xhr.open('POST', uploadUrl);
	
	xhr.onload = () => {
		if(xhr.status === 403) {
			reject({ message: 'HTTP Error: ' + xhr.status, remove: true });
			return;
		} else if(xhr.status < 200 || xhr.status >= 300) {
			reject('HTTP Error: ' + xhr.status);
			return;
		}
		
		var response = JSON.parse(xhr.responseText);
		
		if(!response) {
			reject('Invalid JSON in response: ' + xhr.responseText);
			return;
		}
		
		resolve(response.url);
	};
	
	xhr.onerror = () => {
		reject('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
	};
	
	$imageInputfield.trigger('pwimageupload', {
		'name': blobInfo.filename(),
		'file': blobInfo.blob(),
		'xhr': xhr
	});
});

/**
 * InputfieldTinyMCE main 
 * 
 */
var InputfieldTinyMCE = {
	
	/**
	 * Debug mode?
	 * 
	 */
	debug: false, 
	
	/**
	 * Are document events attached?
	 * 
	 */
	eventsReady: false,
	
	/**
	 * Is document ready?
	 * 
	 */
	isDocumentReady: false,
	
	/**
	 * Editor selectors to init at document ready
	 * 
 	 */	
	editorIds: [],
	
	/**
	 * Are we currently processing an editor init? (bool or string)
	 * 
 	 */
	initializing: false,
	
	/**
	 * Allow lazy loaded editor init()? (adjusted by this class at runtime)
	 * 
 	 */	
	allowLazy: true, 
	
	/**
	 * Ccallback functions
	 * 
 	 */	
	callbacks: { onSetup: [], onConfig: [], onReady: [] },
	
	/**
	 * Recognized class names
	 * 
 	 */	
	cls: {
		main: 'InputfieldTinyMCE',
		lazy: 'InputfieldTinyMCELazy',
		inline: 'InputfieldTinyMCEInline',
		normal: 'InputfieldTinyMCENormal',
		loaded: 'InputfieldTinyMCELoaded',
		focused: 'InputfieldTinyMCEFocused',
		editor: 'InputfieldTinyMCEEditor',
		placeholder: 'InputfieldTinyMCEPlaceholder'
	},
	
	/**
	 * Console log
	 * 
 	 * @param a
	 * @param b
	 * 
	 */	
	log: function(a, b) {
		if(!this.debug) return;
		if(typeof b !== 'undefined') {
			if(typeof a === 'string') a = 'TinyMCE ' + a;
			console.log(a, b);
		} else {
			console.log('TinyMCE', a);
		}
	},
	
	/**
	 * Add a setup callback function
	 * 
	 * ~~~~~
	 * InputfieldTinyMCE.onSetup(function(editor) {
	 *   // ... 
	 * }); 
	 * ~~~~~
	 * 
 	 * @param callback
	 * 
	 */	
	onSetup: function(callback) {
		this.callbacks.onSetup.push(callback); 
	},
	
	/**
	 * Add a config callback function
	 * 
	 * ~~~~~
	 * InputfieldTinyMCE.onConfig(function(settings, $editor, $inputfield) {
	 *   // ... 
	 * });
	 * ~~~~~
	 *
	 * @param callback
	 *
	 */
	onConfig: function(callback) {
		this.callbacks.onConfig.push(callback);
	},
	
	/**
	 * Add a ready callback function
	 * 
	 * ~~~~~
	 * InputfieldTinyMCE.onReady(function(editor) {
	 *   // ... 
	 * });
	 * ~~~~~
	 *
	 * @param callback
	 *
	 */
	onReady: function(callback) {
		this.callbacks.onReady.push(callback);
	},
	
	/**
	 * Set editor initializing state
	 * 
	 * @param {boolean|string} initializing Boolean or editor ID
	 * 
	 */
	setInitializing: function(initializing) {
		this.initializing = initializing;
	},
	
	/**
	 * Is editor initializing?
	 * 
 	 * @returns boolean|string False or editor id (string)
	 * 
	 */	
	isInitializing: function() {
		return this.initializing;
	}, 
	
	/**
	 * Modify image dimensions
	 * 
	 * @param editor
	 * @param img
	 * @param width
	 * 
	 */
	imageResized: function(editor, img, width) {
		var t = this;
		var src = img.src;
		var hidpi = img.className.indexOf('hidpi') > -1 ? 1 : 0;
		var basename = src.substring(src.lastIndexOf('/')+1);
		var path = src.substring(0, src.lastIndexOf('/')+1);
		var dot1 = basename.indexOf('.'); 
		var dot2 = basename.lastIndexOf('.');
		var crop = '';
		
		if(basename.indexOf('-cropx') > -1 || basename.indexOf('.cropx') > -1) {
			// example: gonzo_the_great.205x183-cropx38y2-is.jpg
			// use image file as-is
			// @todo re-crop and resize from original?
		} else if(dot1 !== dot2) {
			// extract any existing resize data present to get original file
			// i.e. file.123x456-is-hidpi.jpg => file.jpg
			var basename2 = basename.substring(0, dot1) + basename.substring(dot2);
			src = path + basename2;
		}
		
		var url = ProcessWire.config.urls.admin + 'page/image/resize' + 
			'?json=1' + 
			'&width=' + width + 
			'&hidpi=' + hidpi + 
			'&file=' + src; 
	
		if(typeof ProcessWire.config.PagesVersions !== 'undefined') {
			if(ProcessWire.config.PagesVersions.page == $('#Inputfield_id').val()) {
				url += '&version=' + ProcessWire.config.PagesVersions.version;
			}
		}
		
		t.log('Resizing image to width=' + width, url); 
		
		jQuery.getJSON(url, function(data) {
			editor.dom.setAttrib(img, 'src', data.src); 
			// editor.dom.setAttrib(img, 'width', data.width);
			// editor.dom.setAttrib(img, 'height', data.height);
			t.log('Resized image to width=' + data.width, data.src);
		});
	},
	
	/**
	 * Called when an element has an align class applied to it
	 * 
	 * This function ensures only 1 align class is applied at a time.
	 * 
 	 * @param editor
	 * 
	 */	
	elementAligned: function(editor) {
		var selection = editor.selection;
		var node = selection.getNode();
		var className = node.className;
		var n;
		
		// if only one align class then return now		
		if(className.indexOf('align') === className.lastIndexOf('align')) return;
		
		var alignNames = [];
		var classNames = className.split(' ');
		
		for(n = 0; n < classNames.length; n++) {
			if(classNames[n].indexOf('align') === 0) {
				alignNames.push(classNames[n]);
			}
		}
	
		// pop off last align class, which we will keep
		alignNames.pop(); 
		
		for(n = 0; n < alignNames.length; n++) {
			className = className.replace(alignNames[n], '');
		}
		
		node.className = className.trim();
	},
	
	/**
	 * Lazy load placeholder click event
	 *
	 * @param e
	 * @returns {boolean}
	 *
	 */
	clickPlaceholder: function(e) {
		var t = InputfieldTinyMCE;
		var $placeholder = jQuery(this);
		var $textarea = $placeholder.next('textarea');
		$placeholder.remove();
		t.log('placeholderClick', $placeholder);
		if($textarea.length) t.init('#' + $textarea.attr('id'), 'event.' + e.type);
		return false;
	},
	
	/**
	 * Init/populate lazy load placeholder elements within given target
	 *
	 * @param $placeholders Placeholders are wrapper that has them within
	 *
	 */
	initPlaceholders: function($placeholders) {
		if(!$placeholders.length) return;
		var t = this;
		var $item = $placeholders.first();
		if(!$item.hasClass(t.cls.placeholder)) $placeholders = $item.find('.' + t.cls.placeholder);
		$placeholders.each(function() {
			var $placeholder = jQuery(this);
			var $textarea = $placeholder.next('textarea');
			t.log('initPlaceholder', $placeholder);
			$placeholder.children('.mce-content-body').html($textarea.val());
			$placeholder.on('click touchstart', t.clickPlaceholder);
		});
	},
	
	/**
	 * Init callback function
	 *
	 * @param editor
	 * @param features
	 * 
	 */	
	editorReady: function(editor, features) {
		
		var t = this;
		var $editor = jQuery('#' + editor.id);
		var $inputfield = $editor.closest('.InputfieldTinyMCE');
		var inputTimeout = null;
		
		if(!$inputfield.length) $inputfield = $editor.closest('.Inputfield');
		
		function changed() {
			if(inputTimeout) clearTimeout(inputTimeout);
			inputTimeout = setTimeout(function() {
				$inputfield.trigger('change');
			}, 500);
		}
		
		editor.on('Dirty', function() { changed() });
		editor.on('SetContent', function() { changed() });
		editor.on('input', function() { changed() });
		
		// for image resizes
		if(features.indexOf('imgResize') > -1) {
			editor.on('ObjectResized', function(e, data) {
				// @todo account for case where image in figure is resized, and figure needs its width updated with the image
				if(e.target.nodeName === 'IMG') {
					t.imageResized(editor, e.target, e.width);
					changed();
				}
			});
		}
		
		for(var n = 0; n < t.callbacks.onReady.length; n++) {
			t.callbacks.onReady[n](editor);
		}
		
		editor.on('ExecCommand', function(e, f) {
			if(e.command === 'mceFocus') return;
			t.log('command: ' + e.command, e);
			if(e.command === 'mceToggleFormat') { 
				if(e.value && e.value.indexOf('align') === 0) {
					var editor = this;
					t.elementAligned(editor);
				}
				changed();
			}
		});
		
		/*
		 * uncomment to show inline init effect
		if(jQuery.ui) {
			if($editor.hasClass('InputfieldTinyMCEInline')) {
				$editor.effect('highlight', {}, 500);
			}
		}
		*/

		/*		
		editor.on('ResizeEditor', function(e) {
			// editor resized
			t.log('ResizeEditor');
		}); 
		*/
	},
	
	/**
	 * config.setup handler function
	 * 
 	 * @param editor
	 * 
	 */	
	setupEditor: function(editor) {
		var t = InputfieldTinyMCE;
		var $editor = jQuery('#' + editor.id);
		
		if($editor.hasClass(t.cls.loaded)) {
			t.log('mceInit called on input that is already loaded', editor.id);
		} else {
			$editor.addClass(t.cls.loaded);
		}
		
		for(var n = 0; n < t.callbacks.onSetup.length; n++) {
			t.callbacks.onSetup[n](editor); 
		}
	
		/*
		editor.on('init', function() {
			// var n = performance.now();
			// t.log(editor.id + ': ' +  (n - mceTimer) + ' ms');
		}); 
		*/
	
		/*	
		editor.on('Load', function() {
			// t.log('iframe loaded', editor.id);
		}); 
		*/
	},
	
	/**
	 * Destroy given editors
	 *
	 * @param $editors
	 *
	 */
	destroyEditors: function($editors) {
		var t = this;
		$editors.each(function() {
			var $editor = jQuery(this);
			if(!$editor.hasClass(t.cls.loaded)) return;
			var editorId = $editor.attr('id');
			var editor = tinymce.get(editorId);
			$editor.removeClass(t.cls.loaded).removeClass(t.cls.lazy);
			t.log('destroyEditor', editor.id);
			// $editor.css('display', 'none');
			editor.destroy();
		});
	},
	
	/**
	 * Destroy editors in given wrapper
	 * 
 	 * @param $wrapper
	 * 
	 */	
	destroyEditorsIn($wrapper) {
		this.destroyEditors($wrapper.find('.' + this.cls.loaded));
	}, 
	
	/**
	 * Reset given editors (destroy and re-init)
	 *
	 * @param $editors
	 *
	 */
	resetEditors: function($editors) {
		var t = this;
		t.allowLazy = false;
		$editors.each(function() {
			var $editor = jQuery(this);
			if(!$editor.hasClass(t.cls.loaded)) return;
			var editorId = $editor.attr('id');
			var editor = tinymce.get(editorId);
			editor.destroy();
			$editor.removeClass(t.cls.loaded);
			// t.init('#' + editorId, 'resetEditors');
		});
		t.initEditors($editors);
		t.allowLazy = true;
	},
	
	/**
	 * Initialize given jQuery object editors
	 *
	 * @param $editors
	 *
	 */
	initEditors: function($editors) {
		var t = this;
		$editors.each(function() {
			var $editor = jQuery(this);
			var editorId = $editor.attr('id');
			if($editor.hasClass(t.cls.loaded)) return;
			//t.log('init', id);
			t.init('#' + editorId, 'initEditors');
		});
	},
	
	/**
	 * Find and initialize editors within a wrapper
	 * 
 	 * @param $wrapper
	 * @param selector Optional
	 * 
	 */	
	initEditorsIn: function($wrapper, selector) {
		if(typeof selector === 'undefined') {
			selector = 
				'.' + this.cls.lazy + ':visible, ' +
				'.' + this.cls.editor + 
				':not(.' + this.cls.loaded + ')' + 
				':not(.' + this.cls.lazy + ')' + 
				':not(.' + this.cls.inline + ')';
		}
		var $placeholders = $wrapper.find('.' + this.cls.placeholder);
		var $editors = $wrapper.find(selector);
		if($placeholders.length) this.initPlaceholders($placeholders);
		if($editors.length) this.initEditors($editors);
	},
	
	/**
	 * Get config (config + custom settings)
	 * 
 	 * @param $editor Editor Textarea (Regular) or div (Inline)
	 * @param $inputfield Editor Wrapping .Inputfield element
	 * @param features
	 * @returns {{}}
	 * 
	 */	
	getConfig: function($editor, $inputfield, features) {

		var configName = $inputfield.attr('data-configName');
		var globalConfig = ProcessWire.config.InputfieldTinyMCE;
		var settings = globalConfig.settings.default;
		var namedSettings = globalConfig.settings[configName];
		var dataSettings = $inputfield.attr('data-settings');

		if(typeof settings === 'undefined') {
			settings = {};
		} else {
			settings = jQuery.extend(true, {}, settings);
		}
		
		if(typeof namedSettings === 'undefined') {
			this.log('Can’t find ProcessWire.config.InputfieldTinyMCE.settings.' + configName);
		} else {
			jQuery.extend(settings, namedSettings);
		}
		
		if(typeof dataSettings === 'undefined') {
			dataSettings = null;
		} else if(dataSettings && dataSettings.length > 2) {
			dataSettings = JSON.parse(dataSettings);
			jQuery.extend(settings, dataSettings);
		}
		
		if(settings.inline) settings.content_css = null; // we load this separately for inline mode
		
		for(var n = 0; n < this.callbacks.onConfig.length; n++) {
			this.callbacks.onConfig[n](settings, $editor, $inputfield);
		}
	
		if(features.indexOf('pasteFilter') > -1) {
			if(globalConfig.pasteFilter === 'text') {
				settings.paste_as_text = true;
			} else if(globalConfig.pasteFilter.length) {
				settings.paste_preprocess = this.pastePreprocess;
			}
		}
		/*
		settings.paste_postprocess = function(editor, args) {
			console.log(args.node);
			args.node.setAttribute('id', '42');
		};
		 */
		
		return settings;
	},
	
	/**
	 * Pre-process paste
	 * 
 	 * @param editor
	 * @param args
	 * 
	 */	
	pastePreprocess: function(editor, args) {
	
		var t = InputfieldTinyMCE;
		var allow = ',' + ProcessWire.config.InputfieldTinyMCE.pasteFilter + ',';
		var regexTag = /<([a-z0-9:!\[\]]+)([^>]*)>/gi;
		var regexAttr = /([-_a-z0-9]+)=["']([^"']*)["']/gi;
		var html = args.content;
		var matchTag, matchAttr;
		var removals = [];
		var finds = [];
		var replaces = [];
		var startLength = html.length;
		
		allow = allow.toLowerCase();
		
		startLength = html.length;
		
		if(args.internal) {
			t.log('Skipping pasteFilter for interal copy/paste'); 
			return; // skip filtering for internal copy/paste operations
		}
		
		if(allow === ',text,') {
			t.log('Skipping pasteFilter since paste_as_text settings will be used'); 
			return; // will be processed by paste_as_text setting
		}
		
		while((matchTag = regexTag.exec(html)) !== null) {
			
			var tagOpen = matchTag[0]; // i.e. <strong>, <img src="..">, <h2>, etc.
			var tagName = matchTag[1]; // i.e. 'strong', 'img', 'h2', etc.
			var tagNameLower = tagName.toLowerCase();
			var tagClose = '</' + tagName + '>'; // i.e. </strong>, </h2>
			var tagAttrs = matchTag[2]; // i.e. 'src="a.jpg" alt="alt"'
			var allowAttrs = false;
	
			// first see if we can match a tag replacement		
			var findTagEqual = ',' + tagNameLower + '='; // i.e. ',b=strong'
			var findTagAttr = ',' + tagNameLower + '['; // i.e. ',b[...]'
			var findTagOnly = ',' + tagNameLower + ','; // i.e. ,b
			var posTagEqual = allow.indexOf(findTagEqual);
			
			if(posTagEqual > -1) {
				var rule = allow.substring(posTagEqual + 1); // i.e. b=strong,and,more
				rule = rule.substring(0, rule.indexOf(',')); // i.e. b=strong
				rule = rule.split('=');
				var replaceTag = rule[1];
				finds.push(tagOpen);
				replaces.push('<' + replaceTag + '>');
				finds.push(tagClose);
				replaces.push('</' + replaceTag + '>');
			}
	
			if(allow.indexOf(findTagAttr) > -1) {
				// tag appears in whitelist with attributes
				allowAttrs = true;
			} else if(posTagEqual === -1 && allow.indexOf(findTagOnly) === -1) {
				// tag does not appear in whitelist at all
				removals.push(tagOpen);
				removals.push(tagClose);
				continue;
			} else {
				// tag appears in whitelist (no attributes)
			}
			
			if(tagAttrs.length) {
				// tag has attributes
				if(!allowAttrs) {
					// attributes not allowed, replace with non-attribute tag
					finds.push(tagOpen);
					replaces.push('<' + tagName + '>');
					continue;
				}
			} else {
				// no attributes, nothing further to do
				continue;
			}
				
			var attrRemoves = [];
			
			while((matchAttr = regexAttr.exec(tagAttrs)) !== null) {
				var attrStr = matchAttr[0]; // i.e. alt="hello"
				var attrName = matchAttr[1]; // i.e. alt
				var attrVal = matchAttr[2]; // i.e. hello
				
				if(allow.indexOf(',' + tagName + '[' + attrName + ']') > -1) {
					// matches whitelist of tag with allowed attribute
				} else if(allow.indexOf(',' + tagName + '[' + attrName + '=' + attrVal + ']') > -1) {
					// matches whitelist of tag with allowed attribute having allowed value
				} else {
					// attributes do not match whitelist
					attrRemoves.push(attrStr);
				}
			}
			
			if(attrRemoves.length) {
				var replaceOpenTag = tagOpen;
				for(var n = 0; n < attrRemoves.length; n++) {
					replaceOpenTag = replaceOpenTag.replace(attrRemoves[n], '');
				}
				finds.push(tagOpen);
				replaces.push(replaceOpenTag);
			}
		}
		
		// console.log('removals', removals);
		
		for(var n = 0; n < removals.length; n++) {
			html = html.replace(removals[n], '');
		}
		
		for(var n = 0; n < finds.length; n++) {
			html = html.replace(finds[n], replaces[n]); 
			// console.log(finds[n] + ' => ' + replaces[n]);
		}
		
		while(html.indexOf('< ') > -1) html = html.replace('< ', '<');
		while(html.indexOf(' >') > -1) html = html.replace(' >', '>');
		while(html.indexOf('&nbsp;') > -1) html = html.replace('&nbsp;', ' ', html);
		
		html = html.replaceAll(/<([-a-z0-9]+)[^>]*>\s*<\/\1>/ig, ''); // remove empty tags
		html = html.replaceAll(/<\/p>\s*<br[/ ]*>/ig, '</p>'); // replace </p><br> with </p>
		
		t.log('Completed pasteFilter ' + startLength + ' => ' + html.length + ' bytes'); 
		
		args.content = html;
	}, 
	
	/**
	 * Document ready events
	 * 
	 */
	initDocumentEvents: function() {
		var t = this;

		jQuery(document)
			.on('click mouseover focus touchstart', '.' + t.cls.inline + ':not(.' + t.cls.loaded + ')', function(e) {
				// we initialize the inline editor only when moused over
				// so that a page can handle lots of editors at once without
				// them all being active
				if(InputfieldTinyMCE.isInitializing() !== false) return;
				t.init('#' + this.id, 'event.' + e.type);
			})
			.on('image-edit sort-stop', '.InputfieldTinyMCE', function(e) {
				// all "normal" editors that are also "loaded"
				var $editors = jQuery(this).find('.' + t.cls.normal + '.' + t.cls.loaded);
				if($editors.length) {
					t.log(e.type + '.resetEditors', $editors);
					// force all loaded to reset
					t.resetEditors($editors);
				}
				// all "normal" non-placeholder editors that are not yet "loaded"
				var $editors = jQuery(this).find('.' + t.cls.normal + ':not(.' + t.cls.loaded + '):not(.' + t.cls.placeholder + ')');
				if($editors.length) {
					t.log(e.type + '.initEditors', $editors);
					t.initEditors($editors);
				}
			})
			.on('reload', '.Inputfield', function() {
				var $inputfield = jQuery(this);
				var $editors = $inputfield.find('.' + t.cls.loaded);
				if($editors.length) {
					t.log('reload', $inputfield.attr('id'));
					t.destroyEditors($editors);
				}
			})
			.on('reloaded', '.Inputfield', function(e) {
				t.initEditorsIn(jQuery(this));
				/*
				var $inputfield = $(this);
				var s = '.' + t.cls.editor + ':not(.' + t.cls.loaded + '):not(.' + t.cls.lazy + ')';
				var $editors = $inputfield.find(s);
				if($editors.length) {
					t.log(e.type, $inputfield.attr('id'));
					t.initEditors($editors);
				}
				var $placeholders = $inputfield.find('.' + t.cls.placeholder);
				if($placeholders.length) t.initPlaceholders($placeholders);
				return false;
				 */
			})
			.on('sortstop', function(e) {
				var $editors = jQuery(e.target).find('.' + t.cls.loaded);
				if($editors.length) {
					t.log('sortstop');
					t.resetEditors($editors);
				}
			})
			.on('opened', '.Inputfield', function(e) {
				t.initEditorsIn(jQuery(this));
			})
			.on('clicklangtab wiretabclick', function(e, $newTab) {
				t.initEditorsIn($newTab);
				/*
				var $placeholders = $newTab.find('.' + t.cls.placeholder);
				var $editors = $newTab.find('.' + t.cls.lazy + ':visible');
				t.log(e.type, $newTab.attr('id'));
				if($placeholders.length) t.initPlaceholders($placeholders);
				if($editors.length) t.initEditors($editors);
				 */
			})
			.on('saved', '.' + t.cls.main, function(e) {
				// saved event like when triggered by LRP
				var $t = $(this);
				var $editors = $t.hasClass(t.cls.loaded) ? $t : $t.find('.' + t.cls.loaded);
				if($editors.length) t.destroyEditors($editors);
			})
			.on('saveReady', '.' + t.cls.main, function(e) {
				// saveReady event, such as from LRP
				// manually dump current value to input to ensure it gets saved
				var $editors = $(this).find('.' + t.cls.loaded);
				$editors.each(function() {
					var $editor = $(this);
					if($editor.hasClass(t.cls.inline)) {
						// inline editor, map name property to separate input[name] with Inputfield_ prefix
						var name = 'Inputfield_' + $(this).attr('name');
						var $input = $editor.next('input[name=' + name + ']'); 
						$input.val($editor.html());
					} else {
						// regular editor
						var editor = tinymce.get($(this).attr('id'));
						$(this).val(editor.getContent()); 
					}
				}); 
			});
		
		/*
		.on('sortstart', function() {
			var $editors = $(e.target).find('.InputfieldTinyMCELoaded'); 
			$editors.each(function() {
				var $editor = $(this);
				t.log('sortstart', $editor.attr('id'));
			}); 
		})
		*/
		
		this.eventsReady = true;
	},
	
	/**
	 * Document ready
	 * 
 	 */	
	documentReady: function() {
		var t = this;
		this.debug = ProcessWire.config.InputfieldTinyMCE.debug;
		this.isDocumentReady = true;
		this.log('documentReady', this.editorIds);
		while(this.editorIds.length > 0) {
			var editorId = this.editorIds.shift();
			this.init(editorId, 'documentReady');
		}
		this.initDocumentEvents();
		var $placeholders = jQuery('.' + this.cls.placeholder + ':visible');
		if($placeholders.length) this.initPlaceholders($placeholders);
		
		this.onSetup(function(editor) {
			editor.on('focus', function(e) {
				$(editor.container).closest('.' + t.cls.main).addClass(t.cls.focused);
			});
			editor.on('blur', function(e) {
				$(editor.container).closest('.' + t.cls.main).removeClass(t.cls.focused);
			});
		});
		
		if(this.debug) {
			this.log('qty', 
				'normal=' + jQuery('.' + this.cls.normal).length + ', ' + 
				'inline=' +  jQuery('.' + this.cls.inline).length + ', ' + 
				'lazy=' + jQuery('.' + this.cls.lazy).length + ', ' + 
				'loaded=' + jQuery('.' + this.cls.loaded).length + ', ' + 
				'placeholders=' + $placeholders.length
			);
		}
	},

	/**
	 * Initialize an editor
	 * 
	 * ~~~~~
	 * InputfieldTinyMCE.init('#my-textarea');
	 * ~~~~~
	 *
	 * @param id Editor id or selector string
	 * @param caller Optional name of caller (for debugging purposes)
	 * @returns {boolean}
	 *
	 */
	init: function(id, caller) {
		
		var $editor, config, features, $inputfield, isFront = false,
			selector, isLazy, useLazy, _id = id, t = this;
		
		if(!this.isDocumentReady) {
			this.editorIds.push(id); 
			return true;
		}
	
		this.setInitializing(id);
		
		caller = (t.debug && typeof caller !== 'undefined' ? ' (caller=' + caller + ')' : '');
		
		if(typeof id === 'string') {
			// literal id or selector string
			if(id.indexOf('#') === 0 || id.indexOf('.') === 0) {
				selector = id;
				id = '';
			} else {
				selector = '#' + id;
			}
			$editor = jQuery(selector);
			if(id === '') id = $editor.attr('id');
			
		} else if(typeof id === 'object') {
			// element or jQuery element
			if(id instanceof jQuery) {
				$editor = id;
			} else {
				$editor = jQuery(id);
			}
			id = $editor.attr('id');
			selector = '#' + id;
		}
		
		if(!$editor.length) {
			console.error('Cannot find element to init TinyMCE: ' + _id); 
			this.setInitializing(false);
			return false;
		}
		
		if(id.indexOf('Inputfield_') === 0) {
			$inputfield = jQuery('#wrap_' + id);
		} else if($editor.hasClass('pw-edit-copy')) {
			$inputfield = $editor.closest('.pw-edit-InputfieldTinyMCE');
			isFront = true; // PageFrontEdit
		} else {
			$inputfield = jQuery('#wrap_Inputfield_' + id);
		}
		
		if(!$inputfield.length) {
			$inputfield = $editor.closest('.InputfieldTinyMCE');
			if(!$inputfield.length) $inputfield = $editor.closest('.Inputfield');
		}
		
		features = $inputfield.attr('data-features');
		if(typeof features === 'undefined') features = '';
		
		useLazy = t.allowLazy && features.indexOf('lazyMode') > -1;
		isLazy = $editor.hasClass(t.cls.lazy);
		
		if(useLazy && !isLazy && !$editor.is(':visible') && !$editor.hasClass(t.cls.inline)) {
			$editor.addClass(t.cls.lazy);
			this.log('init-lazy', id + caller);
			this.setInitializing(false);
			return true;
		} else if(isLazy) {
			$editor.removeClass(t.cls.lazy);
		}
		
		this.log('init', id + caller);
		
		config = this.getConfig($editor, $inputfield, features);
		config.selector = selector;
		config.setup = this.setupEditor;
		config.init_instance_callback = function(editor) {
			t.setInitializing('');
			setTimeout(function() { if(t.isInitializing() === '') t.setInitializing(false); }, 100);
			t.log('ready', editor.id); 
			t.editorReady(editor, features);
		}
		
		if(isFront) {
			// disable drag/drop image uploads when PageFrontEdit
			config.images_upload_url = '';
			config.automatic_uploads = false;
			config.paste_data_images = false;
		} else if(features.indexOf('imgUpload') > -1) {
			config.images_upload_handler = InputfieldTinyMCEUploadHandler;
		} 
		
		tinymce.init(config);
		
		return true;
	}
};

jQuery(document).ready(function() {
	InputfieldTinyMCE.documentReady();
}); 

/*
InputfieldTinyMCE.onSetup(function(editor) {
	editor.ui.registry.addButton('hello', {
		icon: 'user',
		text: 'Hello',
		onAction: function() {
			editor.insertContent('Hello World!')
		}
	});
}); 
*/
