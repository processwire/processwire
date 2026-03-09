<?php namespace ProcessWire;

/**
 * InputfieldTinyMCEConfigHelper
 * 
 * Helper for managing configuration settings in TinyMCE
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */
class InputfieldTinyMCEConfigs extends InputfieldTinyMCEClass {
	
	/**
	 * TinyMCE toolbar options
	 * 
	 * Each item in one of the following formats
	 *  - name=Label
	 *  - name:required_plugin=Label
	 *  - name:required_plugin#plugin_anchor=Label
	 * 
	 * Labels adapted from those at https://www.tiny.cloud/docs/tinymce/6/
	 * 
	 * @var string[] 
	 * 
	 */
	protected $mceToolbars = array(
		'aligncenter=Center aligns the current block or image',
		'alignjustify=Full aligns the current block or image',
		'alignleft=Left aligns the current block or image',
		'alignnone=Removes the alignment of the current block or image',
		'alignright=Right alignsOutdents the current list item or block element the current block or image',
		'anchor:anchor#anchor-plugin=Creates/Edits anchor elements',
		'backcolor=Applies background color to selection',
		'blockquote=Applies block quote format to the current block level element',
		'blocks=Dropdown list with block formats to apply to selection',
		'bold=Applies the bold format to the current selection',
		'bullist:list#lists-plugin=Formats the current selection as a bullet list',
		'cancel:save#save-plugin=Cancels/Resets the editor contents to its initial state',
		'code:code#code-plugin=Opens the code dialog',
		'codesample:codesample#code-sample-plugin=Inserts code snippets with syntax highlighting',
		'charmap:charmap=Inserts custom characters into the editor',
		'copy=Copies the current selection into clipboard',
		'cut=Cuts the current selection into clipboard',
		'emoticons:emoticons#emoticons-plugin=Opens the Emojis dialog',
		'fontfamily=Dropdown list with font families to apply to selection',
		'fontsize=Dropdown list with font sizes to apply to selection',
		'forecolor=Applies foreground/text color to selection',
		'fullscreen:fullscreen#full-screen-plugin=Toggles fullscreen mode',
		'h1=Changes current line to the "Heading 1" style',
		'h2=Changes current line to the "Heading 2" style',
		'h3=Changes current line to the "Heading 3" style',
		'h4=Changes current line to the "Heading 4" style',
		'h5=Changes current line to the "Heading 5" style',
		'h6=Changes current line to the "Heading 6" style',
		'help:help#help-plugin=Opens the help dialog',
		'hr=Inserts a horizontal rule into the editor',
		'image:image#image-plugin=Creates/Edits images within the editor [use “pwimage” instead when possible]',
		'indent=Indents the current list item or block element',
		'insertdatetime:insertdatetime#insert-datetime-plugin=Insert date/time',
		'italic=Applies the italic format to the current selection',
		'language=Dropdown list with languages to apply to the selection. This button requires the content_langs option.',
		'lineheight=Dropdown list with line heights to apply to selection',
		'link:link#link-plugin=Creates/Edits links within the editor [use “pwlink” instead when possible]',
		'ltr:directionality#directionality-plugin=Sets the directionality of contents to ltr',
		'media:media#media-plugin=Creates/Edits embedded media elements',
		'nonbreaking:nonbreaking#nonbreaking-space-plugin=Inserts a nonbreaking space into the editor',
		'numlist:list#lists-plugin=Formats the current selection as a numbered list',
		'outdent=Outdents the current list item or block element',
		'openlink:link=Opens the selected link in a new tab',
		//'pagebreak:pagebreak#page-break-plugin=Inserts a pagebreak into the editor',
		'paste=Pastes the current clipboard into the editor',
		'pastetext=Toggles plain text pasting mode on/off. When in plain text mode, all rich content is converted into plain text',
		'preview:preview#preview-plugin=Previews the current editor contents',
		'print=Prints the current editor contents',
		'pwimage:pwimage=Image (ProcessWire)',
		'pwlink:pwlink=Link (ProcessWire)',
		'quickimage:quickbars#quick-toolbars-plugin=Inserts an image from the local machine',
		'quicklink:quickbars#quick-toolbars-plugin=Inserts a link in a quicker way',
		'quicktable:quickbars#quick-toolbars-plugin=Inserts a table 2x2',
		'redo=Redo the last undone operation',
		'remove=Removes (deletes) the selected content or the content before the cursor position',
		'removeformat=Remove formatting from selection',
		//'restoredraft:autosave=Restores to the latest auto saved draft',
		'rtl:directionality#directionality-plugin=Sets the directionality of contents to rtl',
		//'save:save#save-plugin=Saves the current editor contents to a form or ajax call',
		'searchreplace:searchreplace#search-and-replace-plugin=Searches and/or Replaces contents within the editor',
		'selectall=Select all',
		'strikethrough=Strikethrough ',
		'styles=Dropdown list with styles to apply to selection',
		'subscript=Applies subscript format to the current selection',
		'superscript=Applies superscript format to the current selection',
		'table:table#table-plugin=Creates/Edits table elements',
		'template:template#template-plugin=Inserts templates into the editor',
		'underline=Applies the underline format to the current selection',
		'undo=Undo the last operation',
		'unlink:link=Removes links from the current selection',
		'visualaid=Show invisible elements',
		'visualblocks:visualblocks#visual-blocks-plugin=Toggles the visibility of block elements',
		'visualchars:visualchars#visual-characters-plugin=Toggles the visibility of non breaking character elements',
		'wordcount:wordcount#word-count-plugin=Opens a word count dialog showing word and character counts',
	);

	/**
	 * TinyMCE plugin names and descriptions
	 * 
	 * Descriptions adapted from those at https://www.tiny.cloud/docs/tinymce/6/
	 * 
	 * @var string[] 
	 * 
	 */
	protected $mcePlugins = array(
		'advlist' => 
			'Extends the bullist and numlist toolbar controls by adding CSS list-style-type styled number formats and bullet types to the controls. ' . 
			'The Lists (lists) plugin must be activated for the advlist plugin to work.',
		'anchor' => 
			'Adds an anchor/bookmark button to the toolbar that inserts an anchor at the editor’s cursor insertion point.',
		'autolink' => 
			'Automatically creates hyperlinks when a user types a valid, complete URL. For example www.example.com is converted to http://www.example.com. ' . 
			'Note that this option won’t convert incomplete URLs. For example example.com would remain as unlinked text and URLs must include www to be automatically converted.',
		'autoresize' => 
			'Automatically resizes the editor to the content inside it. ' . 
			'It is typically used to prevent the editor from expanding infinitely as a user types into the editable area.',
		// 'autosave' => '',
		'charmap' => 
			'Adds a dialog to the editor with a map of special unicode characters, which cannot be added directly from the keyboard. ' . 
			'The dialog can be invoked via a toolbar button - charmap - or a dedicated menu item added as Insert > Special character.',
		'code' => 
			'Adds a toolbar button that allows a user to edit the HTML code hidden by the WYSIWYG view. ' . 
			'It also adds the menu item Source code under the Tools menu.',
		'codesample' => 	
			'Lets a user insert and embed syntax color highlighted code snippets into the editable area. ' . 
			'It also adds a button to the toolbar which on click will open a dialog box to accept raw code input. ', // NOTE: prism.js required
		'directionality' => 
			'Adds directionality controls to the toolbar, enabling TinyMCE to better handle languages written from right to left. ' . 
			'It also adds a toolbar button for each of its values, ltr for left-to-right text and rtl for right-to-left text.',
		'emoticons' => 
			'Adds a dialog to the editor that lets users insert emoji into TinyMCE’s editable area. ' . 
			'The dialog can be invoked via a toolbar button - emoticons - or a dedicated menu item added as Insert > Emojis... ' . 
			'The emoticons plugin provides an autocompleter for adding emoji without using the toolbar button or menu item. ' . 
			'Adding a colon “:”, followed by at least two characters will open a popup collection showing matching emoji.',
		'fullscreen' => 
			'Adds full screen editing capabilities to TinyMCE. ' . 
			'When the toolbar button is pressed the editable area will fill the browser’s viewport. ' . 
			'The plugin adds a toolbar button and a menu item Fullscreen under the View menu. ' . 
			'Full screen mode can be toggled using Cmd+Shift+F on Mac or Ctrl+Shift+F on Windows or Linux.',
		'help' => 
			'Adds a button and/or menu item that opens a dialog showing two tabs: ' . 
			'1) Handy shortcuts that explains some nice-to-know keyboard shortcuts; ' . 
			'2) List that shows which plugins that have been installed, with links to the doc pages when available.', 
		'image' => 
			'Enables the user to insert an image into TinyMCE’s editable area. ' . 
			'Also adds a toolbar button and an Insert/edit image menu item under the Insert menu.',
		/*
		'importcss' => 
			'Adds the ability to automatically import CSS classes from the CSS file specified in the content_css configuration setting.',
		*/
		'insertdatetime' => 
			'Provides a toolbar control and menu item Insert date/time (under the Insert menu) ' . 
			'that lets a user easily insert the current date and/or time into the editable area at the cursor insertion point.',
		'link' => 
			'Allows a user to link external resources such as website URLs, to selected text in their document.',
		'lists' => 
			'Allows you to add numbered and bulleted lists to TinyMCE. ' . 
			'To enable advanced lists (e.g. alpha numbered lists, square bullets) you should also enable the Advanced List (advlist) plugin. ' . 
			'Also normalizes list behavior between browsers. Enable it if you have problems with consistency making lists.',
		'media' => 
			'Provides users with the ability to add HTML5 video and audio elements to the editable area. ' . 
			'It also adds the Insert/edit video menu item under the Insert menu and adds an Insert/edit video toolbar button.',
		'nonbreaking' => 
			'Adds a button for inserting nonbreaking space entities [code]&nbsp;[/code] at the current caret location (cursor insert point). ' . 
			'It also adds a menu item Nonbreaking space under the Insert menu dropdown and a toolbar button.',
		/*
		'pagebreak' => 
			'This plugin adds page break support and enables a user to insert page breaks in the editable area. ' . 
			'This is useful where a CMS uses a special separator to break content into pages. ' .
			'It also adds a toolbar button and a menu item Page break under the Insert menu dropdown.',
		*/
		'preview' => 
			'Adds a preview button to the toolbar. ' . 
			'Pressing the button opens a dialog box showing the current content in a preview mode. ' . 
			'It also adds a menu item Preview under the File and View menu dropdowns.',
		'pwimage' => 
			'ProcessWire image plugin, required for inserting and editing images within TinyMCE and the ProcessWire environment. ' . 
			'Makes the pwimage toolbar option available. Requires the page editor.',
		'pwlink' => 
			'ProcessWire link plugin, required for inserting and editing links within TinyMCE and the ProcessWire environment. ' . 
			'We recommend also enabling the TinyMCE “link” plugin and using “pwlink” and “unlink” in your toolbar.',
		'quickbars' => 
			'Adds three context toolbars: ' . 
			'1) quick selection, shown when text is selected, providing formatting buttons such as bold, italic, and link; ' . 
			'2) quick insert, shown when a new line is added, providing buttons for inserting objects such as tables and images; ' . 
			'3) quick image, shown when an image or figure is selected, providing image formatting buttons such as alignment options. ' . 
			'Also makes 3 toolbar buttons available: quicklink, quickimage, quicktable.',
		/*
		'save '=> 
			'This plugin adds a save button to the TinyMCE toolbar, which will submit the form that the editor is within.',
		*/	
		'searchreplace' => 
			'Adds search/replace dialogs to TinyMCE. ' . 
			'It also adds a toolbar button and the menu item Find and replace under the Edit menu dropdown.',
		'table' => 
			'Adds table management functionality to TinyMCE, including dialogs, context menus, context toolbars, menu items, and toolbar buttons.',
		'template' => 
			'Adds support for custom templates. ' . 
			'It also adds a menu item Insert template under the Insert menu and a toolbar button.',
		'visualblocks' => 
			'Allows a user to see block level elements in the editable area. ' . 
			'It is similar to WYSIWYG hidden character functionality, but at block level. ' . 
			'It also adds a toolbar button and a menu item Show blocks under the View menu dropdown.',
		'visualchars' => 
			'Adds the ability to see invisible characters like [code]&nbsp;[/code] displayed in the editable area. ' . 
			'It also adds a toolbar control and a menu item Show invisible characters under the View menu.',
		'wordcount' => 
			'Adds the functionality for counting words to the TinyMCE editor by placing a counter on the right edge of the status bar. ' . 
			'Clicking Word Count in the status bar switches between counting words and characters. ' . 
			'A dialog box with both word and character counts can be opened using the menu item situated in the Tools drop-down, or the toolbar button.',
	);

	/**
	 * Get shared text label
	 * 
	 * @param string $name
	 * @return string
	 * 
	 */
	public function label($name) {
		switch($name) {
			case 'example': return $this->_('Example:') . ' ';
			case 'default': return $this->_('Default:') . ' ';
			case 'useDefault': return $this->_('Specify `default` to use the default value.'); 
			case 'custom': return $this->_('custom');
			case 'text': return $this->_('text');
			case 'file': return $this->_('file');
			case 'tinymce': return $this->_('TinyMCE');
			case 'more': return $this->_('More');
			case 'details': return $this->_('Details');
		}
		return $name;
	}

	/**
	 * Get TinyMCE toolbar names and details
	 * 
	 * Returns array of arrays or array of strings
	 * 
	 * @param bool $splitToArray Specify false to return array of strings
	 * @return array|string[]
	 * 
	 */
	public function getMceToolbars($splitToArray = true) {
		if(!$splitToArray) return $this->mceToolbars;
		$detailsUrl = 'https://www.tiny.cloud/docs/tinymce/6/available-toolbar-buttons/';
		$coreLinkId = '#the-core-toolbar-buttons';
		$a = array();
		foreach($this->mceToolbars as $item) {
			$plugin = '';
			if(strpos($item, '=')) {
				list($name, $label) = explode('=', $item, 2);
			} else {
				$name = $item;
				$label = $name;
			}
			if(strpos($name, '#')) {
				list($name, $url) = explode('#', $name, 2);
				$url = $detailsUrl . '#' . $url;
			} else {
				$url = $detailsUrl . $coreLinkId;
			}
			if(strpos($name, ':')) {
				list($name, $plugin) = explode(':', $name, 2);
			}
			
			$a[$name] = array(
				'name' => $name, 
				'label' => $label, 
				'plugin' => $plugin,
				'url' => $url,
			);
		}
		return $a;
	}

	/**
	 * Get skin options (array of name => label)
	 * 
	 * @return string[]
	 * 
	 */
	public function getSkinOptions() {
		$skins = array(
			'tinymce-5' => 'five',
			'tinymce-5-dark' => 'five-dark',
		);
		$path = $this->inputfield->mcePath() . 'skins/ui/';
		foreach(new \DirectoryIterator($path) as $dir) {
			if(!$dir->isDir() || $dir->isDot()) continue;
			$name = $dir->getBasename();
			if(!isset($skins[$name])) $skins[$name] = $name;
		}
		return $skins;
	}

	/**
	 * Get content_css options (array of name=label)
	 * @return string[]
	 * 
	 */
	public function getContentCssOptions() {
		$path = $this->wire()->config->paths($this->inputfield) . 'content_css/';
		$options = array(
			'wire' => 'wire', 
			'wire-dark' => 'wire-dark',
		);
		foreach(new \DirectoryIterator($path) as $file) {
			if($file->isDir() || $file->isDot()) continue;
			if($file->getExtension() !== 'css') continue;
			$name = $file->getBasename('.css');
			if(!isset($options[$name])) $options[$name] = $name;
		}
		return $options;
	}

	/**
	 * Get features options
	 * 
	 * @return array[]
	 * 
	 */
	public function getFeaturesOptions() {
		return array(
			'toolbar' => array(
				'label' => $this->_('Toolbar'),
				'description' =>
					$this->_('Enables the toolbar of icons that provide access most of the rich text editing tools.')
			),
			'menubar' => array(
				'label' => $this->_('Menubar'),
				'description' =>
					$this->_('Enables a separate menubar with drop down menus and text labels, and appears above the toolbar.') . ' ' .
					$this->_('This can optionally be used in addition to, or instead of, the toolbar.')
			),
			/*
			'statusbar' => array(
				'label' => $this->_('Statusbar'), 
				'description' => 
					$this->_('The status bar appears at the bottom of the editor and shows the current element, clickable parents and editor resize control.') . ' ' . 
					$this->_('It also shows a word count when the wordcount plugin is enabled.') . ' ' . 
					$this->_('Required for regular editor, optional for inline editor.')
			),
			*/
			'stickybars' => array(
				'label' => $this->_('Stickybars'),
				'description' =>
					$this->_('Docks the toolbar and menubar to the top of the screen when scrolling until the editor is no longer visible.')
			),
			'spellcheck' => array(
				'label' => $this->_('Spellcheck'),
				'description' =>
					$this->_('The browser spellcheck feature underlines (in red) misspelled or unrecognized words as you type.')
			),
			'purifier' => array(
				'label' => $this->_('Purifier'),
				'description' =>
					sprintf($this->_('Purifies input HTML/markup with [htmlpurifier](%s).'), 'https://github.com/ezyang/htmlpurifier/blob/master/README.md') . ' ' .
					$this->_('Helps to prevent saving potentially dangerous HTML and avoid XSS exploits.') . ' ' .
					$this->_('Though does increase potential to interfere with some intended markup.') . ' ' .
					$this->_('Enabling this is strongly recommended unless all current and future users are trusted explicitly.') . ' ' .
					$this->_('Disable at your own risk.')
			),
			'document' => array(
				'label' => $this->_('Document'), 
				'description' => 
					$this->_('Override the editor default content style to use the document content style.') . ' ' . 
					$this->_('This looks like a sheet of paper, similar to how it might appear in a word processor.')
			),
			'imgUpload' => array(
				'label' => $this->_('ImgUpload'),
				'description' =>
					$this->_('Enables images to be uploaded automatically by dragging and dropping them into the editor.') . ' ' .
					$this->_('Requires that the page being edited has an images field on it.')
			),
			'imgResize' => array(
				'label' => $this->_('Resize'),
				'description' =>
					$this->_('Creates optimized image files automatically when images are resized by dragging their resize handles.') . ' ' .
					$this->_('If not enabled, images can still be resized by dragging their resize handles, but new image files are not generated.') . ' ' .
					$this->_('You can also use the pop-up image dialog to create image sizes and crops either way.')
			),
			'pasteFilter'  => array(
				'label' => $this->_('Pastefilter'), 
				'description' => 
					$this->_('Reduces most pasted content to its basic semantic HTML to avoid messy tags and attributes from ending up in the editor due to a paste operation.') . ' ' . 
					$this->_('Allowed elements and attributes are configurable in the InputfieldTinyMCE module settings.') . ' ' . 
					$this->_('Pastefilter is only applied to content copied externally, from outside the TinyMCE field.')
				
			),
		);

	}

	/**
	 * Get field configuration
	 * 
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldFieldset
	 * 
	 */
	public function getConfigInputfields(InputfieldWrapper $inputfields) {

		$config = $this->wire()->config;
		$modules = $this->wire()->modules;
		
		$defaults = $this->settings()->getDefaults();
		$defaultLabel = $this->label('default');
		$exampleLabel = $this->label('example');
		$isPost = $this->wire()->input->requestMethod('POST');
		$settingsFields = $this->getOtherTinyMCEFields();
		$configurable = $this->inputfield->configurable();
		$field = $this->inputfield->hasField;
		$inContext = $field && ($field->flags & Field::flagFieldgroupContext);
		
		$f = $inputfields->getChildByName('requiredAttr');
		if($f) $f->getParent()->remove($f);
		
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset'); 
		$fieldset->attr('name', '_tinymce'); 
		$fieldset->label = $this->_('TinyMCE editor settings');
		$fieldset->icon = 'keyboard-o';
		$fieldset->themeOffset = 1;
		$inputfields->prepend($fieldset);
	
		if(count($settingsFields) || !$configurable) {
			$f = $fieldset->InputfieldSelect;
			$f->attr('name', 'settingsField');
			$f->label = $this->_('Field to inherit TinyMCE settings from');
			$f->icon = 'cube';
			$f->themeOffset = 1;
			$fieldset->add($f);
			if(count($settingsFields)) {
				$f->description = $this->_('If you select a field here, we will use the settings from the selected field rather than those configured here.');
				$f->notes = $this->_('After changing your selection please Save before making more changes, as it will modify what fields appear below this.');
				$f->addOption('', $this->_('None (configure this field here)'));
				$f->collapsed = Inputfield::collapsedBlank;
				foreach($settingsFields as $field) {
					/** @var Field $field */
					$f->addOption($field->name, $field->getLabel() . " ($field->name)");
				}
				$value = $this->inputfield->settingsField;
				$f->val($value);
				if($value) return $fieldset;
			} else {
				$f->description = 
					$this->_('This field requires an existing TinyMCE field to use the settings from, and there are currently no other TinyMCE fields.') . ' ' . 
					$this->_('Please create a ProcessWire field of type “textarea”, select TinyMCE as the Inputfield type, and configure it.') . ' ' . 
					$this->_('Then return here to select that field to use for this field’s settings.');
				$f->icon = 'warning';
				$f->addOption('', $this->_('None available'));
			}
			if(!$configurable) {
				$f->notes = trim("$f->notes " . $this->_('If no selection is made, the default settings will be used.'));
			}
		}
		
		if(!$configurable) return $fieldset;

		$inlineLabel = $this->_('Inline editor');
		$regularLabel = $this->_('Normal editor');

		$f = $fieldset->InputfieldRadios;
		$f->attr('name', 'inlineMode');
		$f->label = $this->_('Editor mode');
		$f->icon = 'map-signs';
		$f->addOption(0, $regularLabel . ' [span.detail] ' . $this->_('(flexible height, user resizable)') . ' [/span]');
		$f->addOption(1, $inlineLabel . ' [span.detail] ' . $this->_('(variable height that matches content)') . ' [/span]');
		$f->addOption(2, $inlineLabel . ' [span.detail] ' . $this->_('(fixed height that uses height setting)') . ' [/span]');
		$f->attr('value', (int) $this->inputfield->inlineMode);
		$f->description = 
			$this->_('When the inline editor is used, the editor will not be loaded (or have its toolbar visible) until you click in the text.') . ' ' . 
			$this->_('When the normal editor is used, you can optionally select a lazy loading option below.') . ' ' . 
			$this->_('The normal editor includes a status bar and resize handle while the inline editor does not.');
		$f->themeOffset = 1;
		$f->columnWidth = 70; 
		$fieldset->add($f);
		
		$f = $fieldset->InputfieldInteger;
		$f->attr('name', 'height'); 
		$f->label = $this->_('Editor height'); 
		$f->description = $this->_('Enter the initial editor height in pixels.'); 
		$f->val($this->inputfield->height);
		$f->columnWidth = 30;
		$f->icon = 'arrows-v';
		if(!$inContext) $f->showIf = 'inlineMode!=1';
		$f->inputType = 'number';
		$f->appendMarkup = "&nbsp;<span class='detail'>px</span>";
		$fieldset->add($f);
		
		$f = $fieldset->InputfieldRadios;
		$f->attr('name', 'lazyMode');
		$f->label = $this->_('When to load and initialize the normal editor?'); 
		$f->description = $this->_('Using lazy loading can significantly improve performance, especially when there are multiple editors on the same page.'); 
		$f->icon = 'clock-o';
		$offLabel = '[span.detail] (' . $this->_('lazy loading off') . ') [/span]';
		$f->addOption(0, $this->_('Load editor when the page loads') . " $offLabel"); 
		$f->addOption(1, $this->_('Load editor when it becomes visible')); 
		$f->addOption(2, $this->_('Load editor when it is clicked')); 
		$f->val((int) $this->inputfield->lazyMode);
		$f->showIf = 'inlineMode=0';
		$f->themeOffset = 1;
		$fieldset->add($f);

		$f = $fieldset->InputfieldCheckboxes;
		$f->attr('name', 'features');
		$f->label = $this->_('Features');
		$f->icon = 'toggle-on';
		$f->table = true;
		$f->textFormat = Inputfield::textFormatBasic;
		$f->themeOffset = 1;
		foreach($this->getFeaturesOptions() as $name => $info) {
			$f->addOption($name, "**$info[label]** | $info[description]"); 
		}
		$f->val($this->inputfield->features);
		$fieldset->add($f);
		
		/*
		$f = $fieldset->InputfieldTextarea;
		$f->attr('name', 'toolbar'); 
		$f->label = $this->_('Toolbar');
		$f->description = 
			$this->_('Enter the names of tools to use in the toolbar, each separated by a space.') . ' ' . 
			$this->_('For a separator between tools use a “|” pipe character.'); 
		$f->showIf = 'features=toolbar';
		$f->icon = 'wrench';
		$f->val($this->inputfield->toolbar);
		$f->textFormat = Inputfield::textFormatMarkdown;
		$f->rows = 3;
		$f->themeOffset = 1;
		$notes = array();
		$mceToolbars = $this->getMceToolbars(true);
		$toolsByPlugin = array();
		foreach($mceToolbars as $name => $item) {
			$label = $item['label'];
			if($item['plugin']) {
				$label .= " (" . sprintf($this->_('requires “%s” plugin'), $item['plugin']) . ')';
				if(!isset($toolsByPlugin[$item['plugin']])) $toolsByPlugin[$item['plugin']] = array();
				$toolsByPlugin[$item['plugin']][] = $name;
			}
			$label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
			$notes[] = "<a class='pw-tooltip' href='$item[url]' target='_blank' rel='nofollow noopener' title='$label'>$name</a>";
		}
		$f->notes = 
			'<span class="detail">' . $this->_('Toolbar options:') . '</span><br />' . implode(', ', $notes) . '<br /><br />' . 
			'<span class="detail">' . $this->_('Default toolbar:') . '</span><br />' . "$defaults[toolbar]";
		$fieldset->add($f);
		*/
	
		$f = $fieldset->InputfieldTextTags;
		$f->attr('name', 'toolbar');
		$f->label = $this->_('Toolbar');
		$f->description = 
			$this->_('Select or type the names of tools to use in the editor toolbar.') . ' ' . 
			$this->_('When adding new tools note that some tools require you to enable plugins further in these settings.') . ' ' . 
			$this->_('Hover (or click) the linked tool names at the bottom of this field for more details on each tool, including any required plugins.'); 
		if(!$inContext) $f->showIf = 'features=toolbar';
		$f->icon = 'wrench';
		$f->textFormat = Inputfield::textFormatMarkdown;
		$f->allowUserTags = true;
		$f->themeOffset = 1;
		$notes = array();
		$mceToolbars = $this->getMceToolbars(true);
		$toolsByPlugin = array();
		foreach($mceToolbars as $name => $item) {
			$label = $item['label'];
			$f->addTag($name);
			if($item['plugin']) {
				$label .= " (" . sprintf($this->_('requires “%s” plugin'), $item['plugin']) . ')';
				if(!isset($toolsByPlugin[$item['plugin']])) $toolsByPlugin[$item['plugin']] = array();
				$toolsByPlugin[$item['plugin']][] = $name;
			}
			$label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
			$notes[] = "<a class='pw-tooltip' href='$item[url]' target='_blank' rel='nofollow noopener' title='$label'>$name</a>";
		}
		$f->val($this->inputfield->toolbar);
		$f->notes =
			'<span class="detail">' . $this->_('Toolbar details:') . '</span><br />' . implode(', ', $notes) . '<br /><br />' .
			'<span class="detail">' . $this->_('Default toolbar:') . '</span><br />' . "$defaults[toolbar]";
		$fieldset->add($f);


		$f = $fieldset->InputfieldCheckboxes;
		$f->attr('name', 'plugins');
		$f->label = $this->_('Plugins');
		$f->description = $this->_('Select the plugins you want to enable. Many plugins enable specific tools that you can add to your toolbar above.'); 
		$f->icon = 'plug';
		$f->table = true;
		$f->thead = 
			$this->_('Plugin') . '|' . 
			$this->_('Description') . '|' . 
			$this->_('Tools'); 
		$f->textFormat = Inputfield::textFormatBasic;
		$f->themeOffset = 1;
		$moreLabel = strtoupper($this->_('MORE'));
		if($this->inputfield->hasFieldtype) {
			$notes = array(
				'image' => 'For page editing in ProcessWire, you should use the “pwimage” plugin instead (and leave this unchecked).',
				'link' => 'We also recommend enabling the “pwlink” plugin as well (both checked).',
				'pwlink' => 'Should be combined with the “link” plugin.',
			);
		} else {
			$notes = array(
				'pwlink' => 'Recommended only for ProcessWire admin environment use.', 
				'pwimage' => 'For use in ProcessWire’s page editor only. Use the regular “image” plugin for other cases.', 
			); 
		}
		$notes['fullscreen'] = 'Applies to Regular editor mode only, does NOT appear in INLINE mode.';
		foreach($this->mcePlugins as $name => $description) {
			$description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
			$more = "[small] [$moreLabel](https://www.tiny.cloud/docs/tinymce/6/$name/) [/small]";
			$note = isset($notes[$name]) ? '[br][span.detail]NOTE: ' . $notes[$name] . '[/span]': '';
			$toolbars = ' ';
			if(isset($toolsByPlugin[$name])) $toolbars = '[span.notes]' . implode('[br]', $toolsByPlugin[$name]) . '[/span]';
			if(strpos($name, 'pw') === 0) $more = '';
			$f->addOption($name, "**$name**|$description $more $note|$toolbars");
		}
		$f->val(explode(' ', $this->inputfield->plugins));
		$f->notes = $defaultLabel . $defaults['plugins'];
		$fieldset->add($f);

		/*
		$f = $inputfields->getChildByName('rows');
		if($f) {
			$f->getParent()->remove($f);
			$f->description .= ' ' . $this->_('This is what determines the initial height of the editor.'); 
			$fieldset->add($f);
		}
		*/
	
		// external plugins	
		$opts = trim((string) $this->inputfield->extPluginOpts);
		$opts = strlen($opts) ? explode("\n", $opts) : array();
		$f = $fieldset->InputfieldCheckboxes;
		$f->attr('name', 'extPlugins');
		$f->label = $this->_('External plugins to enable');
		$f->description = $this->_('External plugins can be added from this module’s settings and then enabled for this field here.'); 
		$f->icon = 'plug';
		if(count($opts)) {
			foreach($opts as $file) {
				$file = trim("$file");
				$f->addOption($file, basename($file, '.js'));
			}
			$f->val($this->inputfield->extPlugins);
		} else {
			$f->addOption('none', $this->_('There are currently no external plugins to enable'), array('disabled' => 'disabled'));
		}
		$f->collapsed = count($opts) ? Inputfield::collapsedNo : Inputfield::collapsedBlank;
		$fieldset->add($f);

		$optionals = $this->inputfield->optionals;
		if(in_array('headlines', $optionals)) {
			$fieldset->add($this->configHeadlines());
		}
		if(in_array('contextmenu', $optionals)) {
			$fieldset->add($this->configContextmenu($defaults['contextmenu']));
		}
		if(in_array('menubar', $optionals)) {
			$f = $this->configMenubar($defaults['menubar']);
			if(!$inContext) $f->showIf = 'features=menubar';
			$fieldset->add($f);
		}
		if(in_array('removed_menuitems', $optionals)) {
			$f = $this->configRemovedMenuitems($defaults['removed_menuitems']);
			if(!$inContext) $f->showIf = 'features=menubar';
			$fieldset->add($f);
		}
		if(in_array('styleFormatsCSS', $optionals)) {
			$fieldset->add($this->configStyleFormatsCSS());
		}
		if(in_array('invalid_styles', $optionals)) {
			$fieldset->add($this->configInvalidStyles($defaults['invalid_styles']));
		}
		if(in_array('imageFields', $optionals)) {
			$f = $this->configImageFields();
			if(!$inContext) $f->showIf = 'features=imgUpload';
			$fieldset->add($f);
		}

		// identify which settings are being modified by "add_" or "replace_" module settings
		$addDefaults = $this->settings()->getAddDefaults();
		if(count($addDefaults)) {
			foreach($fieldset->children() as $f) {
				$key = $f->name; 
				if($key === 'headlines') $key = 'block_formats';
				if($key === 'inlineMode') $key = 'inline';
				if(isset($addDefaults['replace_' . $key])) {
					$warning = wireIconMarkup('warning') . ' ' . $this->_('Warning: this setting is being overridden by a module JSON setting.') ;
					$f->prependMarkup .= "<p class='ui-state-error-text'>$warning</p>";
				}
				if(isset($addDefaults['add_' . $key]) || isset($addDefaults['append_' . $key])) {
					$f->appendMarkup = trim("$f->appendMarkup\n" . 
						"<p><span class='notes'>" . wireIconMarkup('info-circle') . ' ' . 
						$this->_('This setting is currently being appended to by a module JSON setting.') . 
						'</span></p>'
					);
				}
			}
		}

		$f = $fieldset->InputfieldCheckboxes;
		$f->attr('name', 'toggles');
		$f->label = $this->_('Markup toggles');
		$f->description = $this->_('Controls adjustments made to markup during input processing.'); 
		$f->icon = 'html5';
		$f->addOption(InputfieldTinyMCE::toggleCleanDiv, $this->_('Convert `<div>` tags to `<p>` tags on save?'));
		$f->addOption(InputfieldTinyMCE::toggleCleanP, $this->_('Remove empty `<p>` tags on save?'));
		$f->addOption(InputfieldTinyMCE::toggleCleanNbsp, $this->_('Remove non-breaking spaces on save?'));
		$f->addOption(InputfieldTinyMCE::toggleRemoveStyles, $this->_('Remove `style` attributes from all elements'));
		$f->attr('value', $this->inputfield->toggles);
		$f->collapsed = Inputfield::collapsedYes;
		$f->themeOffset = 1;
		$fieldset->add($f);
	
		if(in_array('settingsJSON', $optionals)) {
			$fs = $fieldset->InputfieldFieldset;
			$fs->attr('name', '_settingsJSON'); 
			$fs->label = $this->_('Custom settings JSON');
			$fs->icon = 'code';
			$fs->themeColor = 'secondary';
			$fs->themeOffset = 1;
			$fieldset->add($fs);
			
			$f1 = $fieldset->InputfieldTextarea;
			$f1->attr('name', 'settingsJSON');
			$f1->label = $this->_('JSON text'); 
			$f1->icon = 'terminal';
			$f1->description = $this->_('Enter JSON of any additional custom settings you’d like to add that are not indicated in the settings above.');
			$f1->collapsed = Inputfield::collapsedBlank;
			$f1->notes = $exampleLabel . '`{ "invalid_styles": "color font-size font-family line-height" }`';
			$value = $this->inputfield->settingsJSON;
			$f1->val($value);
			if($value && !$isPost) {
				$this->tools()->jsonDecode($value, $f1->label); // test decode
			}
			$fs->add($f1);
		
			$f2 = $fieldset->InputfieldURL;
			$f2->attr('name', 'settingsFile');
			$f2->label = 'JSON file';
			$f2->icon = 'file-code-o';
			$f2->description =
				$this->_('Enter the path to a custom settings JSON file relative to the ProcessWire installation root directory.') . ' ' .
				$this->_('Use this to specify custom settings beyond those supported above.');
			$f2->attr('placeholder', '/dir/to/custom-settings.json');
			$exampleUrl = $config->urls($this->inputfield) . 'defaults.json';
			$f2->notes =
				sprintf($this->_('See an example settings JSON file here: [defaults.json](%s).'), $exampleUrl);
			$f2->collapsed = Inputfield::collapsedBlank;
			$value = $this->inputfield->settingsFile;
			$f2->val($value);
			if($value && !$isPost) {
				$value = $config->paths->root . ltrim($value, '/');
				$this->tools()->jsonDecodeFile($value, $f2->label); // test decode
			}
			$fs->add($f2);
			
			if(!$f1->val() && !$f2->val()) $fs->collapsed = Inputfield::collapsedYes;
		}
	
		return $fieldset;
	}

	/**
	 * Module configuration
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		$languages = $this->wire()->languages;
		$config = $this->wire()->config;
		$relativeLabel = $this->_('URL should be relative to ProcessWire installation root.');
		$exampleLabel = $this->label('example');
		$customLabel = $this->label('custom');
		$isPost = $this->wire()->input->requestMethod('POST');
		
		$fieldset = $inputfields->InputfieldFieldset;
		$fieldset->label = $this->_('TinyMCE');
		$fieldset->icon = 'keyboard-o';
		$fieldset->attr('name', '_tinymce');
		$inputfields->add($fieldset);

		$label = $this->_('UI style/skin');
		$icon = 'paint-brush';
		$f = $inputfields->InputfieldRadios;
		$f->attr('name', 'skin');
		$f->label = $label;
		$f->description = $this->_('Select the style to use for the toolbar, menubar, statusbar, etc.');
		$f->notes = $this->_('Not all UI and content style combinations necessarily work well together, so test.');
		$f->optionColumns = 1;
		$f->addOptions($this->getSkinOptions());
		$f->addOption('custom', $customLabel);
		$f->val($this->inputfield->skin);
		$f->icon = $icon;
		//$f->themeOffset = 1;
		$fieldset->add($f);
		
		$f = $inputfields->InputfieldURL;
		$f->attr('name', 'skin_url');
		$f->label = "$label ($customLabel)";
		$f->description =
			$this->_('Enter a URL/path to a directory containing your custom skin.') . ' ' .
			$this->_('This is the directory containing a `skin.css` file and other css files.') . ' ' .
			$relativeLabel;
		$f->placeholder = $exampleLabel . '/site/templates/myskin/';
		$f->notes = sprintf($this->_('You can use the [TinyMCE 5 skin tool](%s) which also works with TinyMCE 6.'), 'https://skin.tiny.cloud/t5/');
		$f->val($this->inputfield->skin_url);
		$f->showIf = 'skin=custom';
		$f->icon = $icon;
		//$f->themeOffset = 1;
		$fieldset->add($f);

		$label = $this->_('Content style');
		$icon = 'css3';
		$f = $inputfields->InputfieldRadios;
		$f->attr('name', 'content_css');
		$f->label = $label;
		$f->description = $this->_('Select the style to use for the editor text/markup that you edit.');
		$f->addOptions($this->getContentCssOptions());
		$f->addOption('custom', $customLabel);
		$f->optionColumns = 1;
		$f->val($this->inputfield->content_css);
		$f->icon = $icon;
		//$f->themeOffset = 1;
		$fieldset->add($f);
		
		$f = $inputfields->InputfieldURL;
		$f->attr('name', 'content_css_url');
		$f->label = "$label ($customLabel)";
		$f->description = $this->_('Enter a URL/path to a custom content CSS file.') . ' ' . $relativeLabel;
		$examplesUrl = 'https://github.com/ryancramerdesign/InputfieldTinyMCE/tree/master/content_css';
		$f->notes = sprintf(
			$this->_('Examples can be found in %s.'), 
			"[" . $config->urls($this->inputfield) . "content_css/]($examplesUrl)"
		);
		$f->val($this->inputfield->content_css_url);
		$f->showIf = 'content_css=custom';
		$f->attr('placeholder', $exampleLabel . '/site/templates/styles/mycontent.css');
		$f->icon = $icon;
		$fieldset->add($f);
		
		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', 'extPluginOpts'); 
		$f->label = $this->_('External plugin files'); 
		$f->icon = 'plug';
		$f->rows = 3;
		$f->description = 
			$this->_('Use this for making custom/external plugins available to your TinyMCE fields.') . ' ' . 
			$this->_('Enter newline-separated URLs to .js files relative to the ProcessWire installation root.') . ' ' . 
			$this->_('Once plugins are populated here, you will also have to enable them for any fields where you want them.'); 
		$f->detail = 
			$this->_('Adding or removing plugin from the API:') . "\n" . 
			'`' . 
			'$tinymce = $modules->get("InputfieldTinyMCE");' . "\n" . 
			'$tinymce->addPlugin("/site/modules/MyModule/myplugin.js");' . "\n" .
			'$tinymce->removePlugin("/site/modules/MyModule/myplugin.js");' .  
			'`' . "\n" . 
			$this->_('Call addPlugin() once at install and removePlugin() once at uninstall.');
		$f->placeholder = "/site/templates/tinymce-plugins/hello-world.js\n/site/modules/SomeModule/some-plugin.js";
		$f->val($this->inputfield->extPluginOpts);
		$f->collapsed = Inputfield::collapsedBlank;
		$f->themeOffset = 1;
		$fieldset->add($f);
		
		$defaults = $this->settings()->getOriginalDefaults();
		$optionals = array(
			$this->configHeadlines(),
			$this->configContextmenu($defaults['contextmenu']),
			$this->configMenubar($defaults['menubar']),
			$this->configRemovedMenuitems($defaults['removed_menuitems']),
			$this->configStyleFormatsCSS(),
			$this->configInvalidStyles($defaults['invalid_styles']),
			$this->configImageFields(), 
		);
		$f = $inputfields->InputfieldCheckboxes;
		$f->attr('name', 'optionals');
		$f->label = $this->_('Optional settings configurable per-field'); 
		$f->icon = 'sliders';
		$f->table = true;
		$f->description = 
			$this->_('Check boxes for additional settings you would like to be configurable individually for *every single TinyMCE field.*') . ' ' . 
			$this->_('Settings NOT checked are configurable here instead (on this screen), and their values apply to all TinyMCE fields.');
		$f->notes = 
			$this->_('After changing your selections here you should save as it will hide or reveal additional fields below this.');
		foreach($optionals as $inputfield) {
			$f->addOption($inputfield->name, "**$inputfield->label**|" . $inputfield->getSetting('summary|description')); 
		}
		// settingsJSON does not have a dedicated configurable here (since we already have defaultsJSON)
		$f->addOption('settingsJSON',
			'**' . $this->_('Custom JSON settings') . '**|' .
			$this->_('Enables you to add custom settings for each field with a JSON file or string.')
		);
		$f->val($this->inputfield->optionals);
		$f->themeOffset = 1;
		$fieldset->add($f);
		foreach($optionals as $f) {
			$f->showIf = "optionals!=$f->name";
			$fieldset->add($f);
		}
		
		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', 'extraCSS'); 
		$f->val($this->inputfield->extraCSS); 
		$f->label = $this->_('Extra CSS styles'); 
		$f->description = 
			$this->_('Enter any additional CSS styles you want to apply in all editors.') . ' ' . 
			$this->_('This simply adds extra CSS to the editor. It does not define selectable styles in the toolbar/menubar.'); 
		$f->icon = 'css3';
		$f->collapsed = Inputfield::collapsedBlank;
		$fieldset->add($f);
		
		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', 'pasteFilter'); 
		$value = $this->inputfield->pasteFilter;
		if(empty($value)) $value = 'default';
		$f->val($value);
		$f->icon = 'paste';
		$f->label = $this->_('Pastefilter whitelist'); 
		$f->attr('rows', 3);
		$f->description = 
			$this->_('Comma-separated string of rules to define a whitelist of tags (and optionally attributes) to keep during a paste operation.') . ' ' .
			$this->_('This setting is used when the “Pastefilter” feature is selected for a given field, and the user pastes in formatted text.') . ' ' .
			$this->_('Enter the string `default` to use the default paste filter configuration. Enter string `text` to paste as plain text.') . ' ' . 
			$this->_('Or specify multiple comma-separated rules like the following.') . "\n\n" . 
			$this->_('Specify `tag` to allow tag without attributes, `tag[attribute]` to allow tag with attribute, `tag[attribute1|attribute2|etc]` to allow tag with multiple attributes.') . ' ' .
			$this->_('Specify `tag[attribute=value]` to allow tag having attribute with specific value, or `tag[attribute=a|b|c]` to allow tag with attribute having any one of multiple values.') . ' ' . 
			$this->_('Specify `foo=bar` to replace tag `foo` with tag `bar`, i.e. `b=strong` and `i=em` are common examples.');
		$f->detail = '**' . $this->_('Default pastefilter whitelist:') . "**\n" . str_replace(',', ', ', InputfieldTinyMCE::defaultPasteFilter);
		$f->collapsed = $value === 'default' ? Inputfield::collapsedYes : Inputfield::collapsedNo;
		$f->themeOffset = 1;
		$fieldset->add($f);

		$exampleUrl = $config->urls($this->inputfield) . 'defaults.json';
		$defaultsDetail =
			$this->label('example') . ' `{ "style_formats_autohide": true }`' . "\n" . 
			$this->_('If you want to force a setting to override a field setting, prefix it with “replace_”, i.e. `{ "replace_toolbar": "styles bold italic" }`.') . "\n" . 
			$this->_('If you want to append to an existing field setting, prefix the setting name with “add_”, i.e. `{ "add_toolbar": "undo redo" }`') . "\n" . 
			sprintf(
				$this->_('See the [TinyMCE docs](%s) for detail on all settings, keeping in mind that you can also use the “replace_” or “add_” prefixes.'), 
				'https://www.tiny.cloud/docs/tinymce/6/'
			) . "\n"  .
			sprintf($this->_('See the default JSON file here: [defaults.json](%s).'), $exampleUrl);
		$label = $this->_('Default setting overrides JSON');
		$f = $inputfields->InputfieldTextarea;
		$f->attr('name', 'defaultsJSON');
		$f->label = "$label " . $this->label('text');
		$f->icon = 'terminal';
		$f->description = $this->_('Enter JSON of any default settings you’d like to override from the module defaults.');
		$f->collapsed = Inputfield::collapsedBlank;
		$f->detail = $defaultsDetail;
		$value = $this->inputfield->defaultsJSON;
		$f->val($value);
		if($value && !$isPost) $this->tools()->jsonDecode($value, 'defaultsJSON'); // test decode
		$f->themeOffset = 1;
		$fieldset->add($f);

		$f = $inputfields->InputfieldURL;
		$f->attr('name', 'defaultsFile');
		$f->label = "$label " . $this->label('file');
		$f->icon = 'file-code-o';
		$f->description = $this->_('Enter the path to a custom defaults JSON file relative to the ProcessWire installation root directory.');
		$f->attr('placeholder', '/dir/to/defaults.json');
		$f->detail = $defaultsDetail;
		$f->collapsed = Inputfield::collapsedBlank;
		$value = $this->inputfield->defaultsFile;
		$f->val($value);
		if($value && !$isPost) $this->tools()->jsonDecodeFile($config->paths->root . $value, 'defaultsFile'); // test decode
		$f->themeOffset = 1;
		$fieldset->add($f);

		if($languages) {
			$fieldset = $inputfields->InputfieldFieldset;
			$fieldset->attr('name', '_langPacks');
			$fieldset->label = $this->_('TinyMCE language translations');
			$fieldset->description = $this->_('Select translation pack to use for each language.');
			$fieldset->icon = 'globe';
			$fieldset->themeOffset = 1;
			$inputfields->add($fieldset);
			$langPacks = array('en_US' => 'en_US');
			foreach(new \DirectoryIterator(__DIR__ . '/langs/') as $file) {
				if($file->isDot() || $file->isDir()) continue;
				$name = $file->getBasename('.js');
				$langPacks[$name] = $name;
			}
			ksort($langPacks);
			foreach($languages as $language) {
				$langName = $language->name;
				$name = "lang_$langName";
				$value = $this->inputfield->get($name);
				if($value === null) {
					$languages->setLanguage($language);	
					$value = $this->settings()->getLanguagePackCode();
					$languages->unsetLanguage();
				}
				$f = $inputfields->InputfieldSelect;
				$f->attr('name', "lang_$language->name");
				$f->label = $language->get('title|name');
				$f->addOptions($langPacks);
				$f->val($value);
				$f->icon = 'language';
				$fieldset->add($f);
			}
		}
		
		$f = $inputfields->InputfieldToggle;
		$f->attr('name', 'debugMode');
		$f->label = $this->_('Debug mode?');
		$f->description = $this->_('When enabled InputfieldTinyMCE.js will use verbose console.log() messages for debugging or development.'); 
		$f->val((bool) $this->inputfield->debugMode);
		$f->collapsed = $f->val() ? false : true;
		$f->icon = 'bug';
		$inputfields->add($f);
	}

	/**
	 * Get other textarea fields that are using TinyMCE
	 * 
	 * @return array
	 * 
	 */
	public function getOtherTinyMCEFields() {
		$hasField = $this->inputfield->hasField;
		$className = $this->inputfield->className();
		$dependentFields = array();
		$a = array();
		foreach($this->wire()->fields->findByType('FieldtypeTextarea') as $field) {
			if($field->get('inputfieldClass') === $className) {
				if($hasField && $hasField->name === $field->name) continue;
				if($field->get('settingsField')) {
					if($hasField && $field->get('settingsField') === $hasField->name) {
						$dependentFields[] = $field;
					}
					continue;
				}
				$a[$field->name] = $field;
			}
		}
		if(count($dependentFields)) {
			// other fields are depending on this one, so this one cannot depend on another
			$a = array();
		}
		return $a;
	}

	/**
	 * @return InputfieldTextarea
	 * 
	 */
	protected function configStyleFormatsCSS() {
		/** @var InputfieldTextarea $f */
		$f = $this->wire()->modules->get('InputfieldTextarea');
		$f->attr('name', 'styleFormatsCSS');
		$f->label = $this->_('Custom style formats CSS');
		$f->description =
			$this->_('This enables you to add additional items to the “Styles” toolbar dropdown as CSS classes.') . ' ' .
			$this->_('Optionally prefix with `#Headings`, `#Blocks`, `#Inline`, `#Align`, or your own `#Id` to add to a Styles dropdown submenu.') . ' ' .
			$this->_('You can use a CSS comment to provide the menu label, i.e. `/\* Red Text \*/`.') . ' ' .
			$this->_('You can omit the class (and optionally styles) if you just want to make the element available in your Styles dropdown, i.e. `ins {}`.') . ' ' .
			$this->_('You can specify any styles in UPPERCASE to also force them into inline styles in the markup, i.e. `span.alert { COLOR: red; }`.') . ' ' . 
			$this->_('To remove all items having same parent (such as all in “Align”) enter `#Align { display:none }`.') . ' ' . 
			$this->_('Or to remove just “Align > Center” (for example) enter `#Align (Center) { display:none }`.'); 
		$f->set('summary', $this->_('Use CSS classes to create custom styles to add to the “Styles” dropdown.'));
		$value = $this->inputfield->styleFormatsCSS;
		$f->val($value);
		$f->notes = $this->label('example') . "\n" .
			"`#Inline span.red-text { color: red; } /\* Red Text \*/`\n" .
			"`#Blocks p.outline { padding: 20px; border: 1px dotted #ccc; } /\* Outline paragraph \*/`\n" .
			"`img.border { border: 1px solid #ccc; padding: 2px; } /\* Image with border \*/`\n" .
			"`#Hello ins {} /\* Insert text \*/`\n" .
			"`#Hello del { text-decoration: line-through; } /\* Delete text \*/`\n" .
			"`#Hello span.alert { BACKGROUND: red; COLOR: white; } /\* Alert text \*/`\n" .
			"`#Headings (Heading 1) { display: none }`"; 
		$f->detail =
			$this->_('Note that this is only for the editor.') . ' ' .
			$this->_('You will likely want to add similar CSS classes (without the #IDs) to your front-end site stylesheet, unless forcing inline styles.');
		$f->themeOffset = 1;
		$f->icon = 'css3';
		$f->collapsed = Inputfield::collapsedBlank;
		$rows = substr_count($value, "\n") + 2;
		if($rows > $f->attr('rows')) $f->attr('rows', $rows);
		return $f;
	}
	
	protected function configInvalidStyles($defaultValue) {
		
		if(is_array($defaultValue)) {
			$defaultValue = $this->formats()->invalidStylesArrayToStr($defaultValue);
		}
		
		/** @var InputfieldTextarea $f */
		$f = $this->wire()->modules->get('InputfieldTextarea'); 
		$f->attr('name', 'invalid_styles');
		$f->attr('rows', 3);
		$f->label = $this->_('Invalid styles');
		$format1 = "`tag=style1|style2|style3`";
		$format2 = "`tag1|tag2|tag3=style`";
		$f->description = 
			$this->_('Space, newline or comma separated list of inline styles that should be disallowed in markup style attributes.') . ' ' . 
			$this->_('Each style is disabled for all elements.') . ' ' . 
			sprintf($this->_('To disable styles for specific elements/tags use the format %s or %s.'), $format1, $format2) . ' ' . 
			$this->label('useDefault');
		$f->set('summary', $this->_('Specify which inline styles are disallowed from appearing in markup (i.e. line-height, font-size, etc.).'));
		$f->notes = 
			$this->label('default') . " `$defaultValue`" . "\n" . 
			$this->label('example') . " `float, font-family, a=color|background-color, table|tr|td=height`";
		$f->detail = $this->_('Use of are commas or newlines is optional.');
			
		$value = $this->inputfield->invalid_styles;
		if(is_array($value)) $value = $this->formats()->invalidStylesArrayToStr($value);
		if($value === $defaultValue) $value = 'default';
		$f->val($value);
		if(empty($value) || $value === 'default') $f->collapsed = Inputfield::collapsedYes;
		$f->themeOffset = 1;
		$f->icon = 'eye-slash';
		$rows = substr_count($value, "\n") + 2; 
		if($rows > $f->attr('rows')) $f->attr('rows', $rows);
		return $f;
	}

	protected function configMenubar($defaultValue) {
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');
		$f->attr('name', 'menubar');
		$f->label = $this->_('Menubar dropdowns');
		$f->icon = 'toggle-down';
		$f->set('summary', $this->_('Specify which tools should appear in the menubar dropdowns.'));
		$f->description =
			$this->_('The top level dropdown items to display in the menubar.') . ' ' . 
			$this->label('useDefault') . ' ' . 
			'[' . $this->label('details') . '](https://www.tiny.cloud/docs/tinymce/6/menus-configuration-options/#menubar)';
		$value = $this->inputfield->menubar;
		if($value === $defaultValue) $value = 'default';
		$f->val($value);
		$f->notes = $this->label('default') . "`$defaultValue`";
		if($value === 'default' || empty($value)) $f->collapsed = Inputfield::collapsedYes;
		$f->themeOffset = 1;
		return $f;
	}
	
	protected function configRemovedMenuitems($defaultValue) {
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');
		$f->attr('name', 'removed_menuitems');
		$f->label = $this->_('Tools to remove from the menubar');
		$f->icon = 'wrench';
		$f->set('summary', $this->_('Specify which tools should be removed from the menubar (when used).'));
		$f->description =
			$this->_('The menubar is built according to module default settings and installed plugins.') . ' ' .
			$this->_('If there are tools you do not want showing in the menubar enter their names here.') . ' ' . 
			$this->label('useDefault');
		$f->notes = $this->label('default') . "`$defaultValue`";
		$value = $this->inputfield->removed_menuitems;
		if($value === $defaultValue) $value = 'default';
		$f->val($value);
		if($value === 'default' || empty($value)) $f->collapsed = Inputfield::collapsedYes;
		$f->themeOffset = 1;
		return $f; 
	}
	
	protected function configContextmenu($defaultValue) {
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');
		$f->attr('name', 'contextmenu');
		$f->label = $this->_('Context menu tools');
		$f->icon = 'sticky-note';
		$f->set('summary', $this->_('Specify which tools should appear in a context menu when you right-click an element.'));
		$f->description =
			$this->_('Tools to show in the context menu that appears when right-clicking an element.') . ' ' .
			$this->_('Only the tools relevant to the element will be shown on right-click.') . ' ' . 
			$this->label('useDefault');
		$value = $this->inputfield->contextmenu;
		if($value === $defaultValue) $value = 'default';
		$f->val($value);
		$f->notes = $this->label('default') . "`$defaultValue`";
		if($value === 'default' || empty($value)) $f->collapsed = Inputfield::collapsedYes;
		$f->themeOffset = 1;
		return $f;
	}

	protected function configHeadlines() {
		/** @var InputfieldCheckboxes $f */
		$f = $this->wire()->modules->get('InputfieldCheckboxes');
		$f->attr('name', 'headlines');
		$f->label = $this->_('Headline options');
		$f->description = $this->_('Select which headlines should be available in the “blocks” and/or “styles” dropdowns.');
		$f->icon = 'university';
		for($n = 1; $n <= 6; $n++) {
			$f->addOption("h$n");
		}
		$f->val($this->inputfield->headlines);
		$f->optionColumns = 1;
		$f->themeOffset = 1;
		return $f;
	}
	
	protected function configImageFields() {
		/** @var InputfieldAsmSelect $f */
		$f = $this->wire()->modules->get('InputfieldAsmSelect');
		$f->attr('name', 'imageFields');
		$f->label = $this->_('Image fields for ImgUpload');
		$f->description = 
			$this->_('Select which image fields are supported (for uploads) when an image is dragged into the editor.');
		$f->notes = 
			$this->_('If no fields are selected then an available images field will be automatically chosen at runtime.') . ' ' . 
			$this->_('If the option labeled “None” is selected, then the feature will be disabled.') . ' ' . 
			$this->_('If multiple image fields match on a given page, the order of the selections above applies.');
		$f->icon = 'picture-o';
		$imageFields = $this->inputfield->imageFields;
		if(!is_array($imageFields)) $imageFields = array();
		if(in_array('x', $imageFields) && count($imageFields) > 1) $imageFields = array('x');
		$f->addOption('x', $this->_('None'));
		foreach($this->wire()->fields->findByType('FieldtypeImage') as $field) {
			if(((int) $field->get('maxFiles')) === 1) continue;
			$f->addOption($field->name);
		}
		$f->attr('value', $imageFields);
		$f->collapsed = Inputfield::collapsedBlank;
		$f->themeOffset = 1;
		return $f;
	}
	
	/**
	 * Add an external plugin .js file
	 * 
	 * @param string $file File must be .js file relative to PW installation root, i.e. /site/templates/mce/myplugin.js
	 * @throws WireException
	 * 
	 */
	public function addPlugin($file) {
		$basename = basename($file);
		$ext = pathinfo($basename, PATHINFO_EXTENSION);
		if($ext !== 'js') throw new WireException("Plugin file does not end in .js ($basename)");
		$pathname = $this->wire()->config->paths->root . ltrim($file, '/');
		if(!is_file($pathname)) throw new WireException("File $pathname does not exist");
		$modules = $this->wire()->modules;
		$data = $modules->getModuleConfigData($this->inputfield);
		$value = isset($data['extPluginOpts']) ? $data['extPluginOpts'] : '';
		$data['extPluginOpts'] = trim("$value\n$file");
		$this->inputfield->set('extPluginOpts', $data['extPluginOpts']);
		$modules->saveModuleConfigData($this->inputfield, $data);
	}

	/**
	 * Remove an external plugin .js file
	 *
	 * @param string $file File must be .js file relative to PW installation root, i.e. /site/templates/mce/myplugin.js
	 * @return bool
	 *
	 */
	public function removePlugin($file) {
		$modules = $this->wire()->modules;
		$data = $modules->getModuleConfigData($this->inputfield);
		$file = trim($file);
		if(empty($data['extPluginOpts'])) return false;
		$value = explode("\n", $data['extPluginOpts']);
		$updated = false;
		foreach($value as $k => $v) {
			if(trim($v) !== $file) continue;
			unset($value[$k]);
			$updated = true;
		}
		$data['extPluginOpts'] = count($value) ? implode("\n", $value) : '';
		if($updated) {
			$modules->saveModuleConfigData($this->inputfield, $data);
			$this->inputfield->extPluginOpts = $data['extPluginOpts']; 
		}
		return $updated;
	}
	
	/**
	 * Convert CKE toolbar to MCE (future use)
	 *
	 * @param string $value
	 * @return string
	 *
	 */
	public function ckeToMceToolbar($value) {
		$value = str_replace(array("\n", ","), array(" - ", " "), $value);
		while(strpos($value, '  ') !== false) $value = str_replace('  ', ' ', $value);
		$tools = array();
		foreach(explode(' ', $value) as $tool) {
			if(isset($this->ckeToMceToolbars[$tool])) {
				$tools[$tool] = $this->ckeToMceToolbars[$tool];
			}
		}
		$value = implode(' ', $tools);
		return $value;
	}

	/**
	 * Translates CKE to MCE toolbar names (not currently used)
	 *
	 * @var string[]
	 *
	 */
	protected $ckeToMceToolbars = array(
		// CKE => MCE
		'About' => 'help',
		'Anchor' => 'anchor',
		'Blockquote' => 'blockquote',
		'Bold' => 'bold',
		'BulletedList' => 'bullist',
		'Copy' => 'copy',
		'CopyFormatting' => '',
		'CreateDiv' => '',
		'Cut' => 'cut',
		'Find' => 'searchreplace',
		'Flash' => '',
		'Format' => 'styles',
		'HorizontalRule' => 'hr',
		'Iframe' => 'pageembed',
		'Image' => 'image',
		'Indent' => 'indent',
		'Italic' => 'italic',
		'JustifyBlock' => 'alignjustify',
		'JustifyCenter' => 'aligncenter',
		'JustifyLeft' => 'alignleft',
		'JustifyRight' => 'alignright',
		'Language' => 'language',
		'Link' => 'link',
		'Maximize' => 'fullscreen',
		'NewPage' => 'newdocument',
		'NumberedList' => 'numlist',
		'Outdent' => 'outdent',
		'PageBreak' => 'pagebreak',
		'Paste' => 'paste',
		'PasteFromWord' => '',
		'PasteText' => 'pastetext',
		'Preview' => 'preview',
		'Print' => 'print',
		'PWImage' => 'pwimage',
		'PWLink' => 'pwlink',
		'Redo' => 'redo',
		'RemoveFormat' => 'removeformat',
		'Replace' => '',
		'Save' => 'save',
		'Scayt' => '', // set browser_spellcheck
		'SelectAll' => 'selectall',
		'ShowBlocks' => 'visualblocks',
		'Smiley' => 'emoticons',
		'Source' => 'code',
		'Sourcedialog' => 'code',
		'SpecialChar' => 'charmap',
		'SpellChecker' => '',
		'Strike' => 'strikethrough',
		'Styles' => '',
		'Subscript' => 'subscript',
		'Superscript' => 'superscript',
		'Table' => 'table',
		'Templates' => 'template',
		'Underline' => 'underline',
		'Undo' => 'undo',
		'Unlink' => 'unlink',
		'-' => '|',
		'#' => ' ',
	);

	/**
	 * CKE setting names (not currently used, for future auto-conversion to MCE)
	 * 
	 * @var string[] 
	 * 
	 */
	protected $ckeSettingNames = array(
		'assetPageID',
		'contentsCss',
		'contentsInlineCss',
		'customOptions',
		'extraAllowedContent',
		'extraPlugins',
		'formatTags',
		'imageFields',
		'inlineMode',
		'removePlugins',
		'stylesSet',
		'toggles',
		'toolbar',
		'useACF',
		'usePurifier',
	);

}
