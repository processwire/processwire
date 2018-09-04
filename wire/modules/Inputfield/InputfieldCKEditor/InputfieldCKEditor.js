/**
 * InputfieldCKEditor.js
 *
 * Initialization for CKEditor
 *
 */

/**
 * Get ProcessWire config settings for given CKE editor object or name
 *
 * @param editor
 * @returns {*}
 *
 */
function ckeGetProcessWireConfig(editor) {

	var editorName = typeof editor == "string" ? editor : editor.name;
	var configName = editorName.replace('Inputfield_', 'InputfieldCKEditor_');
	var $repeaterItem = '';
	var settings = {};
	
	configName = configName.replace('Inputfield_', 'InputfieldCKEditor_');

	if(typeof ProcessWire.config[configName] == "undefined" && configName.indexOf('_repeater') > 0) {
		configName = configName.replace(/_repeater[0-9]+/, '');
		$repeaterItem = $('#' + editorName).closest('.InputfieldRepeaterItem');
	}
	if(typeof ProcessWire.config[configName] == "undefined" && configName.indexOf('_ckeditor') > 0) {
		configName = configName.replace(/_ckeditor$/, ''); // inline only
	}
	if(typeof ProcessWire.config[configName] == "undefined" && configName.indexOf('__') > 0) {
		configName = configName.replace(/__\d+$/, ''); // remove language-id
	}
	if(typeof ProcessWire.config[configName] == "undefined") {
		settings.error = 'Cannot find CKEditor settings for ' + configName;
	} else {
		settings = ProcessWire.config[configName];
	}

	if($repeaterItem.length) {
		settings['repeaterItem'] = $repeaterItem;
	} else {
		settings['repeaterItem'] = '';
	}
	
	return settings;
}


/**
 * Add external plugins
 * 
 * These are located in:
 * 	/wire/modules/Inputfield/InputfieldCKEditor/plugins/[name]/plugin.js (core external plugins)
 * 	/site/modules/InputfieldCKEditor/plugins/[name]/plugin.js (site external plugins)
 * 
 */
function ckeLoadPlugins() {
	for(var name in ProcessWire.config.InputfieldCKEditor.plugins) {
		var file = ProcessWire.config.InputfieldCKEditor.plugins[name];
		CKEDITOR.plugins.addExternal(name, file, '');
	}
}
ckeLoadPlugins();

/**
 * Event called when an editor is blurred, so that we can check for changes
 * 
 * @param event
 * 
 */
function ckeBlurEvent(event) {
	var editor = event.editor;
	var $textarea = $(editor.element.$);
	if(editor.checkDirty()) {
		// value changed
		if($textarea.length) {
			if($textarea.is("textarea")) $textarea.change();
			$textarea.closest(".Inputfield").addClass('InputfieldStateChanged');
		}
	}
}

/**
 * Event called when an editor is focused
 *
 * @param event
 *
 */
function ckeFocusEvent(event) {
	var editor = event.editor;
	var $textarea = $(editor.element.$);
	$textarea.trigger('pw-focus');
}

/**
 * Event called when an editor is resized vertically
 *
 * @param event
 *
 */
function ckeResizeEvent(event) {
	var editor = event.editor;
	var $textarea = $(editor.element.$);
	if($textarea.length) {
		$textarea.closest(".Inputfield").trigger('heightChanged');
	}
}

/**
 * Handler for fileUploadRequest event
 * 
 * @param event
 * 
 */
function ckeUploadEvent(event) {
	
	var xhr = event.data.fileLoader.xhr;
	var fileLoader = event.data.fileLoader;
	var settings = ckeGetProcessWireConfig(event.editor);
	var uploadFieldName = settings ? settings.pwUploadField : '_unknown';
	var $imageInputfield = $('#Inputfield_' + uploadFieldName); 

	if(typeof settings.repeaterItem != "undefined" && settings.repeaterItem.length) {
		var $repeaterImageField = settings.repeaterItem.find('.InputfieldImage:not(.InputfieldFileSingle)');
		if($repeaterImageField.length) $imageInputfield = $repeaterImageField;
	}

	if($imageInputfield.length) {
		xhr.open("POST", fileLoader.uploadUrl, true);
		$imageInputfield.trigger('pwimageupload', {
			'name': fileLoader.fileName,
			'file': fileLoader.file,
			'xhr': xhr
		});

		// Prevented the default behavior.
		event.stop();
	} else {
		if(typeof settings.error != "undefined" && settings.error.length) {
			ProcessWire.alert(settings.error);
		} else {
			ProcessWire.alert('Unable to find images field for upload');
		}
		event.stop();
		return false;
	}
}

/**
 * Attach events common to all CKEditor instances
 *
 * @param editor CKEditor instance
 *
 */
function ckeInitEvents(editor) {
	
	editor.on('blur', ckeBlurEvent);
	editor.on('focus', ckeFocusEvent);
	editor.on('change', ckeBlurEvent);
	editor.on('resize', ckeResizeEvent);
	editor.on('fileUploadRequest', ckeUploadEvent, null, null, 4); 

	var $textarea = $(editor.element.$);
	var $inputfield = $textarea.closest('.Inputfield.InputfieldColumnWidth');
	
	if($inputfield.length) setTimeout(function() {
		$inputfield.trigger('heightChanged');
	}, 1000);
}


/**
 * Called on saveReady or submit to copy inline contents to a form element in the POST request
 * 
 * @param $inputfield
 * 
 */
function ckeSaveReadyInline($inputfield) {
	if(!$inputfield.length) return;
	var $inlines = $inputfield.hasClass('.InputfieldCKEditorInline') ? $inputfield : $inputfield.find(".InputfieldCKEditorInline");
	if($inlines.length) $inlines.each(function() {
		var $t = $(this);
		var value;
		if($t.hasClass('InputfieldCKEditorLoaded')) {
			var editor = CKEDITOR.instances[$t.attr('id')];
			// getData() ensures there are no CKE specific remnants in the markup
			if(typeof editor != "undefined") {
				if(editor.focusManager.hasFocus) {
					// TMP: CKEditor 4.5.1 / 4.5.2 has documented bug that causes JS error on editor.getData() here
					// this section of code can be removed after they fix it (presumably in 4.5.3)
					editor.focusManager.focus(true);
					editor.focus();
				}
				value = editor.getData();
			}
		} else {
			value = $t.html();
		}
		var $input = $t.next('input');
		$input.attr('value', value);
	});
}

/**
 * Called on saveReady event to force an editor.updateElement() to update original textarea 
 * 
 * @param $inputfield
 * 
 */
function ckeSaveReadyNormal($inputfield) {
	var $normals = $inputfield.hasClass('InputfieldCKEditorNormal') ? $inputfield : $inputfield.find(".InputfieldCKEditorNormal");
	$normals.each(function() {
		var $t = $(this);
		if(!$t.hasClass('InputfieldCKEditorLoaded')) return;
		var editor = CKEDITOR.instances[$t.attr('id')];
		editor.updateElement();
	});
}

/**
 * Mouseover event that activates inline CKEditor instances
 * 
 * @param event
 * 
 */
function ckeInlineMouseoverEvent(event) {
	
	// we initialize the inline editor only when moused over
	// so that a page can handle lots of editors at once without
	// them all being active

	var $t = $(this);
	if($t.hasClass("InputfieldCKEditorLoaded")) return;
	$t.effect('highlight', {}, 500);
	$t.attr('contenteditable', 'true');
	var configName = $t.attr('data-configName');
	var editor = CKEDITOR.inline($(this).attr('id'), ProcessWire.config[configName]);
	ckeInitEvents(editor);
	$t.addClass("InputfieldCKEditorLoaded"); 
}

/**
 * CKEditors hidden in jQuery UI tabs sometimes don't work so this initializes them when they become visible
 *
 */ 
function ckeInitTab(event, ui) {
	var $t = ui.newTab; 
	var $a = $t.find('a'); 
	if($a.hasClass('InputfieldCKEditor_init')) return;
	var editorID = $a.attr('data-editorID');
	var configName = $a.attr('data-configName');
	var editor = CKEDITOR.replace(editorID, config[configName]);
	ckeInitEvents(editor);
	$a.addClass('InputfieldCKEditor_init'); 
	ui.oldTab.find('a').addClass('InputfieldCKEditor_init'); // in case it was the starting one
	var $editor = $("#" + editorID);
	$editor.addClass('InputfieldCKEditorLoaded');
}

/**
 * Initialize a normal CKEditor instance for the given textarea ID
 * 
 * @param editorID
 * 
 */
function ckeInitNormal(editorID) {

	var $editor = $('#' + editorID);
	var $parent = $editor.parent();
	
	if(typeof ProcessWire.config.InputfieldCKEditor.editors[editorID] != "undefined") {
		var configName = ProcessWire.config.InputfieldCKEditor.editors[editorID];
	} else {
		var configName = $editor.attr('data-configName');
	}

	if($parent.hasClass('ui-tabs-panel') && $parent.css('display') == 'none') {
		// CKEditor in a jQuery UI tab (like langTabs)
		var parentID = $editor.parent().attr('id');
		var $a = $parent.closest('.ui-tabs, .langTabs').find('a[href=#' + parentID + ']');
		$a.attr('data-editorID', editorID).attr('data-configName', configName);
		$parent.closest('.ui-tabs, .langTabs').on('tabsactivate', ckeInitTab);
	} else {
		// visible CKEditor
		var editor = CKEDITOR.replace(editorID, ProcessWire.config[configName]);
		ckeInitEvents(editor);
		$editor.addClass('InputfieldCKEditorLoaded');
	}
}

/**
 * Prepare inline editors
 *
 */ 
$(document).ready(function() {

	/**
	 * Override ckeditor timestamp for cache busting
	 * 
	 */
	CKEDITOR.timestamp = ProcessWire.config.InputfieldCKEditor.timestamp;

	/**
	 * Regular editors
	 * 
	 */
	
	for(var editorID in ProcessWire.config.InputfieldCKEditor.editors) {
		ckeInitNormal(editorID);
	}
	
	$(document).on('reloaded', '.InputfieldCKEditor', function() {
		// reloaded event is sent to .Inputfield when the contents of the .Inputfield 
		// have been replaced with new markup
		var $editor = $(this).find('.InputfieldCKEditorNormal:not(.InputfieldCKEditorLoaded)');
		$editor.each(function() {
			ckeInitNormal($(this).attr('id'));
		});
		return false;
	});

	/**
	 * Inline editors
	 * 
	 */

	CKEDITOR.disableAutoInline = true; 
	$(document).on('mouseover', '.InputfieldCKEditorInlineEditor', ckeInlineMouseoverEvent); 
	$(document).on('submit', 'form.InputfieldForm', function() {
		ckeSaveReadyInline($(this));
		// note: not necessary for regular editors since CKE takes care
		// of populating itself to the textarea on it's own during submit
	});

	/**
	 * saveReady event handler
	 *
	 * saveReady is sent by some form-to-ajax page save utils in ProcessWire
	 * found it was necessary for normal CKE instances because a cancelled submit
	 * event was not updating the original textarea, so we do it manually
	 * 
	 */

	$(document).on('saveReady', '.InputfieldCKEditor', function() {
		ckeSaveReadyNormal($(this));
		ckeSaveReadyInline($(this));
	});
}); 
