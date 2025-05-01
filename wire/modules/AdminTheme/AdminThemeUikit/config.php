<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

class AdminThemeUikitConfigHelper extends Wire {

	/**
	 * @var AdminThemeUikit
	 * 
	 */
	protected $adminTheme;

	/**
	 * Construct
	 *
	 * @param AdminThemeUikit $adminTheme
	 * 
	 */
	public function __construct(AdminThemeUikit $adminTheme) {
		$this->adminTheme = $adminTheme;
		$adminTheme->wire($this);
		parent::__construct();
	}

	/**
	 * Implementation for Uikit admin theme getConfigInputfields method for module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function configModule(InputfieldWrapper $inputfields) {

		$adminTheme = $this->adminTheme;
		$defaultFileNote = $this->_('When blank, the default file used.') . ' ';
		$defaultFileDesc = $this->_('Enter path relative to homepage URL.');
		$recommendedLabel = $this->_('(RECOMMENDED)');
		$experimentalLabel = $this->_('(EXPERIMENTAL)');
		$exampleLabel = $this->_('example');

		$modules = $this->wire()->modules;
		$session = $this->wire()->session;
		$config = $this->wire()->config;
		
		$layout = $adminTheme->layout;
		$userTemplateURL = $config->urls->admin . 'setup/template/edit?id=3';

		$adminTheme->getThemeInfo(); // init
		$themeInfos = $adminTheme->themeInfos;
		
		if(count($themeInfos)) {
			$configFiles = [];
			$f = $inputfields->InputfieldRadios;
			$f->attr('id+name', 'themeName');
			$f->label = $this->_('Theme name');
			$f->notes = 
				$this->_('After changing the theme, please submit/save before configuring it.') . ' ' . 
				$this->_('When using `admin.less` customization, you should use the “Original” theme.');
			$f->icon = 'photo';
			foreach($themeInfos as $name => $info) {
				$f->addOption($name, ucfirst($name), [ 'data-url' => $info['url'] ]);
				$configFile = $info['path'] . 'config.php';
				if(is_file($configFile)) $configFiles[$name] = $configFile;
			}
			$f->addOption('', $this->_('Original'));
			$value = $adminTheme->themeName;
			$f->val($value);
			$f->themeOffset = 1; 
			$inputfields->add($f);
			
			foreach($configFiles as $name => $configFile) {
				$fs = $inputfields->InputfieldFieldset;
				$inputfields->add($fs);
				$fs->themeOffset = 1;
				$fs->attr('name', "_theme_$name"); 
				$fs->label = $this->_('Theme style settings:') . ' ' .  $name;
				$fs->showIf = "themeName=$name";
				$this->wire()->files->render($configFile, [ 'inputfields' => $fs ]); 
			}
		}

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Masthead + navigation');
		$fieldset->icon = 'navicon';
		$fieldset->set('themeOffset', true);
		$fieldset->collapsed = Inputfield::collapsedYes;
		$inputfields->add($fieldset);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'userAvatar');
		$f->label = $this->_('User avatar');
		$f->icon = 'user-circle';
		$f->addOption('gravatar', $this->_('Gravatar (external service that determines avatar from email)'));
		$f->description = $this->_('Select an image field, Gravatar, or icon to show for the user avatar in the masthead.');
		$numImgFields = 0;
		foreach($modules->wire('templates')->get('user')->fieldgroup as $field) {
			if(!$field->type instanceof FieldtypeImage) continue;
			$f->addOption("$field->id:$field->name", sprintf($this->_('Image field: %s'), $field->name));
			$numImgFields++;
		}
		if(!$numImgFields) {
			$f->notes = $this->_('There are no image fields present on the “user” template at present, so only icons and Gravatar are shown.') . ' ';
		}
		$f->notes .= sprintf($this->_('You may add image fields to your user template [here](%s).'), $userTemplateURL);

		$icons = array(
			'user-circle',
			'user-circle-o',
			'user',
			'user-o',
			'user-secret',
			'vcard',
			'vcard-o',
			'child',
			'female',
			'male',
			'paw',
		);
		foreach($icons as $icon) {
			$f->addOption("icon.$icon", sprintf($this->_('Icon: %s'), $icon));
		}
		$f->attr('value', $adminTheme->get('userAvatar'));
		$fieldset->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'userLabel');
		$f->label = $this->_('User navigation label format');
		$f->icon = 'user-circle';
		$f->description =
			$this->_('This label appears next to the user avatar image/icon.') . ' ' .
			$this->_('Specify field(s) and format to use for the user label, or blank for no user label.') . ' ' .
			sprintf($this->_('Use any fields/properties from your [user](%s) template surrounded in {brackets}.'), $userTemplateURL) . ' ' .
			$this->_('Use {Name} for capitalized name, which is the default setting, or use {name} for lowercase name.');
		$f->notes = $this->_('Examples: “{name}”, “{Name}”, “{title}”, “{first_name} {last_name}”, “{company.title}”, etc.');
		$f->attr('value', $adminTheme->userLabel);
		$fieldset->add($f);
		
		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'logoURL');
		$f->attr('value', $adminTheme->get('logoURL'));
		$f->label = $this->_('Logo image file');
		$f->description = $defaultFileDesc;
		$f->notes = $defaultFileNote .
			$this->_('File should be PNG, GIF, JPG or SVG, on transparent background, and at least 100px in both dimensions.') . ' ' . 
			sprintf($this->_('If using SVG, you may optionally append “?uk-svg” to URL to make it add the [uk-svg](%s) attribute.'), 'https://getuikit.com/docs/svg');  
		$f->collapsed = Inputfield::collapsedBlank;
		$f->icon = 'file-image-o';
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'logoAction');
		$f->label = $this->_('Logo click action');
		$f->icon = 'mouse-pointer';
		$f->addOption(0, $this->_('Admin root page list'));
		$f->addOption(1, $this->_('Open offcanvas navigation'));
		$f->attr('value', (int) $adminTheme->logoAction);
		$f->collapsed = Inputfield::collapsedBlank;
		$f->optionColumns = 1;
		$fieldset->add($f);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Layout + interface');
		$fieldset->icon = 'newspaper-o';
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->set('themeOffset', true);
		$inputfields->add($fieldset);
	
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('id+name', 'layout');
		if(empty($layout)) $f->showIf = "themeName=''";
		$f->label = $this->_('Layout type');
		$f->addOption('', $this->_('Traditional with masthead navigation') .
			' [span.detail] ' . $recommendedLabel . ' [/span]');
		$opt = $this->_('Page tree navigation in sidebar');
		$f->addOption('sidenav-tree', $opt . ' ' . $this->_('(left)') .
			'* [span.detail] ' . $experimentalLabel . ' [/span]');
		$f->addOption('sidenav-tree-alt', $opt . ' ' . $this->_('(right)') .
			'* [span.detail] ' . $experimentalLabel . ' [/span]');
		// $f->addOption('sidenav', $this->_('Sidebar navigation (left) + page tree navigation (right)'));
		$f->attr('value', $layout);
		$f->notes = $this->_('*Sidebar layouts not compatible with SystemNotifications module and may have issues with other modules.');
		$f->columnWidth = 50;
		$fieldset->add($f);

		$lastLayout = $session->getFor($adminTheme, 'lastLayout');
		if($lastLayout != $layout) {
			$o = '[script]';
			if(strpos($layout, 'sidenav') === 0) {
				$o .=
					"if(typeof parent.isPresent != 'undefined') {" .
					"   parent.location.href = './?layout=sidenav-init';" .
					"} else {" .
					"   window.location.href = './?layout=sidenav-init';" .
					"}";
			} else {
				$o .=
					"if(typeof parent.isPresent != 'undefined') {" .
					"   parent.location.href = './edit?name=$adminTheme->className';" .
					"}";
			}
			$o .= '[/script]';
			$f->appendMarkup = str_replace(array('[', ']'), array('<', '>'), $o);
		}

		if(empty($_POST)) $session->setFor($adminTheme, 'lastLayout', $layout);
		
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('id+name', 'ukGrid');
		$f->label = $this->_('Inputfield column widths');
		$f->notes = $this->_('Choose option B if you are having any trouble achieving intended Inputfield column widths.');
		$f->addOption(1, 'A: ' . $this->_('Uikit uk-width classes (up-to 6 columns)'));
		$f->addOption(0, 'B: ' . $this->_('Percentage-based widths (additional flexibility)'));
		$f->attr('value', (int) $adminTheme->get('ukGrid'));
		$f->columnWidth = 50;
		$fieldset->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'maxWidth');
		$f->label = $this->_('Maximum layout width');
		$f->description = $this->_('Specify the maximum width of the layout (in pixels) or 0 for no maximum.');
		$f->notes = $this->_('Applies to traditional layout only.');
		$f->attr('value', $adminTheme->maxWidth);
		$f->columnWidth = 50;
		$fieldset->add($f);

		$testURL = $modules->wire('config')->urls->admin . 'profile/?test_notices';
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'groupNotices');
		$f->label = $this->_('Notifications style');
		$f->notes = $this->_('Does not apply if the SystemNotifications module is installed.');
		$f->addOption(1, $this->_('Group by type with expand/collapse control') . " ([$exampleLabel]($testURL=group-on))");
		$f->addOption(0, $this->_('Always show all') . " ([$exampleLabel]($testURL=group-off))");
		$f->attr('value', (int) $adminTheme->groupNotices);
		$f->columnWidth = 50;
		$fieldset->appendMarkup .= "<script>$('#wrap_Inputfield_groupNotices .InputfieldContent').find('a').addClass('pw-modal');</script>";
		/** @var JqueryUI $jQueryUI */
		$jQueryUI = $modules->get('JqueryUI');
		$jQueryUI->use('modal');
		$fieldset->add($f);
		
		$f = $inputfields->getChildByName('useAsLogin');
		if($f) $f->collapsed = Inputfield::collapsedNo;
		
		/** @var InputfieldFieldset $fieldset2 */
		$fieldset2 = $modules->get('InputfieldFieldset');
		$fieldset2->label = $this->_('Advanced');
		$fieldset2->collapsed = Inputfield::collapsedBlank;
		$fieldset2->description = 
			$this->_('Most advanced settings are available from the `$config->AdminThemeUikit` settings array.') . ' ' . 
			$this->_('You can find it in /wire/config.php. Copy to your /site/config.php file to modify it.');
		$fieldset->add($fieldset2);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'cssURL');
		$f->attr('value', $adminTheme->get('cssURL'));
		$f->label = $this->_('Primary CSS file');
		$f->description = $defaultFileDesc . ' ' .
			$this->_('We do not recommend changing this unless you are an admin theme developer.') . ' ' . 
			$this->_('Warning: this will override custom `$config->AdminThemeUikit` settings, base style and custom styles.'); 
		$f->notes = $defaultFileNote . " " .
			"[uikit.pw.css](" . $modules->wire('config')->urls('AdminThemeUikit') . "uikit/dist/css/uikit.pw.css)";
		$f->icon = 'file-code-o';
		$f->collapsed = Inputfield::collapsedBlank;
		$fieldset2->add($f);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = $this->_('Forms + input');
		$fieldset->description =
			$this->_('These settings affect all form fields in the admin.') . ' ' .
			$this->_('Any of these settings (and others) may also be specified individually for a given field.') . ' ' .
			$this->_('If you specify a setting here, it will override individual field settings.') . ' ' .
			$this->_('See: Setup > Fields > [any field] > Input (tab) > Admin Theme Settings.');
		$fieldset->icon = 'edit';
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->set('themeOffset', true);
		$inputfields->add($fieldset);

		/** @var InputfieldMarkup $e1 */
		$e1 = $modules->get('InputfieldMarkup');
		$e1->label = $this->_('Input size examples');
		$e1->columnWidth = 25;

		/** @var InputfieldMarkup $e2 */
		$e2 = $modules->get('InputfieldMarkup');
		$e2->label = $this->_('Select size examples');
		$e2->columnWidth = 25;

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'inputSize');
		$f->label = $this->_('Default input size');
		$f->description = $this->_('This affects the appearance of many text, textarea and select fields.');
		$f->notes = $this->_('Not all Inputfields support the “small” or “large” options.'); 
		$f->icon = 'expand';
		$f->columnWidth = 50;
		$sizes = array(
			's' => array($this->_('Small'), 'uk-form-small'),
			'm' => array($this->_('Medium (default/recommended)'), ''),
			'l' => array($this->_('Large'), 'uk-form-large'),
		);
		foreach($sizes as $value => $info) {
			$f->addOption($value, $info[0]);
			$label = explode(' ', $info[0]); 
			$label = reset($label);
			$e1->value .= "<div class='uk-margin-small'><input disabled class='uk-input $info[1]' value='$label' /></div>";
			$e2->value .= "<div class='uk-margin-small'><select disabled class='uk-select $info[1]'><option>$label</option></select></div>";
		}
		$f->attr('value', $adminTheme->get('inputSize'));
		$fieldset->add($f);
		$fieldset->add($e1);
		$fieldset->add($e2);

		$types = $modules->findByPrefix('Inputfield');
		ksort($types);
		$skipTypes = array('Button', 'Submit', 'Form', 'Hidden');
		foreach($types as $key => $name) {
			$name = str_replace('Inputfield', '', $name);
			if(in_array($name, $skipTypes)) {
				unset($types[$key]);
			} else {
				$types[$key] = $name;
			}
		}

		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->attr('name', 'noBorderTypes');
		$f->label = $this->_('Input types that should have no border');
		$f->description = $this->_('This setting applies to any selected types when used at 100% width.');
		$f->icon = 'low-vision';
		$f->set('themeOffset', true);
		$f->collapsed = Inputfield::collapsedBlank;
		foreach($types as $className => $name) {
			$f->addOption($className, $name);
		}
		$f->attr('value', $adminTheme->noBorderTypes);
		$fieldset->add($f);

		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->attr('name', 'offsetTypes');
		$f->label = $this->_('Input types that should be offset with additional top/bottom margin.');
		$f->description = $this->_('As an example, the fields in this fieldset are using this option.');
		$f->set('themeOffset', true);
		$f->icon = 'arrows-v';
		$f->collapsed = Inputfield::collapsedBlank;
		foreach($types as $className => $name) {
			$f->addOption($className, $name);
		}
		$f->attr('value', $adminTheme->offsetTypes);
		$fieldset->add($f);
		
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'toggleBehavior');
		$f->label = $this->_('Inputfield label toggle behavior'); 
		$f->icon = 'eye-slash';
		$f->description = 
			$this->_('Inputfield elements in ProcessWire can have an open or closed state.') . ' ' . 
			$this->_('This setting determines what happens when a user clicks any Inputfield label, which appears as a header above the input.') . ' ' . 
			$this->_('The “Standard” option makes a click of a label on an open Inputfield focus the input element, a standard HTML form behavior.') . ' ' . 
			$this->_('While a click of a closed Inputfield label will open and then focus the Inputfield (and close it when clicked again).') . ' ' . 
			$this->_('The “Consistent” option makes the Inputfield label always behave consistently as an open/close toggle, regardless of what state the Inputfield is in.');
		$f->notes = $this->_('Regardless of what setting you choose, the toggle icon in the upper right of each Inputfield always toggles the open/closed state.'); 
		$f->addOption(0, $this->_('Standard'));
		$f->addOption(1, $this->_('Consistent')); 
		$f->optionColumns = 1;
		$f->val($adminTheme->toggleBehavior); 
		$fieldset->add($f);	
		
		/** @var InputfieldCheckboxes $f */
		/*
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'useOffset');
		$f->label = $this->_('Vertically offset ALL input types?');
		$f->description =
			$this->_('When checked, a vertical margin is added to every field.') . ' ' .
			$this->_('This may provide additional clarity in some cases, but consumes more vertical space.');
		$f->collapsed = Inputfield::collapsedBlank;
		$f->icon = 'arrows-v';
		if($adminTheme->useOffset) $f->attr('checked', 'checked');
		$fieldset->add($f);
		*/
		
		/** @var InputfieldAsmSelect $f */
		/*
		$f = $modules->get('InputfieldAsmSelect');
		$f->attr('name', 'cardTypes');
		$f->label = $this->_('Input types that should use the “Card” style');
		$f->description = $this->_('This field is an example of the card style.');
		$f->notes = $this->_('Does not apply to types selected to have no border.');
		$f->icon = 'list-alt';
		$f->set('themeBorder', 'card');
		$f->set('themeOffset', true);
		foreach($types as $className => $name) {
			$f->addOption($className, $name);
		}
		$f->attr('value', $adminTheme->cardTypes);
		$fieldset->add($f);
		*/
		
		if($this->wire('input')->get('tests')) {
			$this->configTests($inputfields);
		}
	}
	
	/**
	 * Uikit configuration for Inputfield
	 *
	 * @param Inputfield $inputfield
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function configInputfield(Inputfield $inputfield, InputfieldWrapper $inputfields) {

		if($inputfield instanceof InputfieldWrapper) return;
		if(!$inputfield->hasFieldtype || !$inputfield->hasField) return;

		$field = $inputfield->hasField;

		$modules = $this->wire()->modules;
		$config = $this->wire()->config;

		$autoLabel = $this->_('Auto');
		$noneLabel = $this->_('None');

		/** @var InputfieldFieldset $fieldsetVisibility */
		$fieldsetVisibility = $inputfields->getChildByName('visibility');
		if(!$fieldsetVisibility) return;

		// if our fieldset is already present, remove it and add again (so that last added stays)
		$test = $inputfields->getChildByName('_adminTheme');
		if($test) $test->getParent()->remove($test);

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->attr('id+name', '_adminTheme');
		$fieldset->label = $this->_('Admin theme settings') . ' ' . 
			'(' . str_replace('AdminTheme', '', $this->adminTheme->className()) . ')';
		$fieldset->collapsed = Inputfield::collapsedYes;
		$fieldset->icon = 'flask';
		$fieldset->description =
			$this->_('These settings affect how this field appears when used with the Uikit admin theme.') . ' ' .
			$this->_('When choosing the “Auto” option, the settings will be determined at runtime.') . ' ' .
			$this->_('The “Margin” setting applies only to 100% width fields.') . ' ' . 
			$this->_('The “Auto” option is the recommended default for all settings.'); 
		$fieldsetParent = $fieldsetVisibility->getParent();
		$fieldsetParent->insertAfter($fieldset, $fieldsetVisibility);

		$xSmallLabel = $this->_('Extra small');
		$smallLabel = $this->_('Small');
		$mediumLabel = $this->_('Medium');
		$largeLabel = $this->_('Large');
		
		$isSelect1 = $inputfield instanceof InputfieldSelect && !$inputfield instanceof InputfieldHasArrayValue;
		$isText1 = $inputfield instanceof InputfieldText && !$inputfield instanceof InputfieldTextarea;
		$isTextarea = $inputfield instanceof InputfieldTextarea && !$inputfield instanceof InputfieldCKEditor;
		$useInputSize = $isText1 || $isTextarea || $isSelect1;
		$useInputWidth = $isText1 || $isSelect1;
		
		if($useInputSize && $useInputWidth) {
			$columnWidth = 20;
		} else if($useInputSize || $useInputWidth) {
			$columnWidth = 25;
		} else {
			$columnWidth = 33;
		}

		if($useInputSize) {
			/** @var InputfieldRadios $f */
			$f = $modules->get('InputfieldRadios');
			$f->attr('name', 'themeInputSize');
			$f->label = $this->_('Input size');
			$f->icon = 'expand';
			$f->addOption('', $autoLabel);
			$f->addOption('s', $smallLabel);
			$f->addOption('m', $mediumLabel);
			$f->addOption('l', $largeLabel);
			$value = $field ? $field->get('themeInputSize') : '';
			$f->attr('value', $value);
			$f->columnWidth = $columnWidth;
			$fieldset->add($f);
		}
		if($useInputWidth) {
			/** @var InputfieldRadios $f */
			$f = $modules->get('InputfieldRadios');
			$f->attr('name', 'themeInputWidth');
			$f->label = $this->_('Input width');
			$f->icon = 'arrows-h';
			$f->addOption('', $autoLabel);
			$f->addOption('xs', $xSmallLabel);
			$f->addOption('s', $smallLabel);
			$f->addOption('m', $mediumLabel);
			$f->addOption('l', $largeLabel);
			$f->addOption('f', $this->_('Fill'));
			$value = $field ? $field->get('themeInputWidth') : '';
			$f->attr('value', $value);
			$f->columnWidth = $columnWidth;
			$fieldset->add($f);
		}

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'themeOffset');
		$f->label = $this->_('Margin');
		$f->icon = 'arrows-v';
		$f->addOption('', $autoLabel);
		$f->addOption('s', $smallLabel);
		$f->addOption('m', $mediumLabel);
		$f->addOption('l', $largeLabel);
		$f->addOption('none', $noneLabel);
		$value = $field ? $field->get('themeOffset') : '';
		if($value == 1) $value = 'm';
		$f->attr('value', $value);
		$f->columnWidth = $columnWidth;
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'themeBorder');
		$f->label = $this->_('Border');
		$f->addOption('', $autoLabel);
		$f->addOption('line', $this->_x('Outline', 'border'));
		$f->addOption('card', $this->_x('Card', 'border'));
		$f->addOption('hide', $this->_x('Transparent', 'border'));
		$f->addOption('none', $noneLabel);
		$f->columnWidth = $columnWidth;
		$f->icon = 'low-vision';
		$value = $field ? $field->get('themeBorder') : '';
		$f->attr('value', $value);
		$fieldset->add($f);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'themeColor');
		$f->label = $this->_('Color');
		$f->icon = 'eyedropper';
		$f->addOption('', $autoLabel);
		$f->addOption('primary', $this->_x('Primary', 'color'));
		$f->addOption('secondary', $this->_x('Secondary', 'color'));
		$f->addOption('highlight', $this->_x('Highlight', 'color'));
		$f->addOption('warning', $this->_x('Warning', 'color'));
		//$f->addOption('danger', $this->_x('Danger', 'color'));
		$f->addOption('none', $noneLabel);
		$value = $field ? $field->get('themeColor') : '';
		$f->attr('value', $value);
		$f->columnWidth = $columnWidth;
		$f->showIf = 'themeBorder!=none';
		$fieldset->add($f);

		if($inputfield instanceof InputfieldText && !$inputfield instanceof InputfieldCKEditor) {
			/** @var InputfieldCheckbox $f */
			$f = $modules->get('InputfieldCheckbox');
			$f->attr('name', 'themeBlank');
			$f->label = $this->_('Minimize the styling of form controls');
			if($field->get('themeBlank')) $f->attr('checked', 'checked');
			$fieldset->add($f);
		}

		if($inputfield instanceof InputfieldSelect) {
			$exampleType = 'InputfieldSelect';
		} else if($inputfield instanceof InputfieldTextarea) {
			$exampleType = 'InputfieldTextarea';
		} else if($inputfield instanceof InputfieldText) {
			$exampleType = 'InputfieldText';
		} else {
			$exampleType = 'InputfieldMarkup';
		}
		
		/** @var Inputfield $f */
		$f = $modules->get($exampleType);
		$f->attr('id+name', '_adminThemeExample');
		$f->label = $this->_('Example');
		$text = $this->_('This field simply demonstrates the settings you selected above.');
		if($f instanceof InputfieldSelect) {
			$f->addOption('', $text);
			$f->addOption('Lorem');
			$f->addOption('Ipsum');
			$f->addOption('Dolor');
			$f->addOption('Sit');
			$f->addOption('Amet');
		} else if($f instanceof InputfieldText) {
			$f->value = $text;
		} else {
			$f->value = "<p>$text</p>";
			$f->collapsed = Inputfield::collapsedYes;
		}
		$f->icon = 'snowflake-o';
		$fieldset->add($f);

		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup'); 
		$f->attr('id+name', '_adminThemeExample2');
		$f->label = $this->_('Another field');
		$f->value = "<p>Lorem ipsum dolor</p>";
		$fieldset->add($f);
		
		$f = $inputfields->getChildByName('columnWidth');
		if($f) $f->notes = sprintf(
			$this->_('Open the “%s” field above for a live example of column width.'), $fieldset->label
		). ' ' . $f->notes;

		$config->scripts->add($config->urls($this->adminTheme) . 'config-field.js');
	}

	/**
	 * Create tests for Inputfields widths and showIf
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function configTests(InputfieldWrapper $inputfields) {

		$form = $inputfields->getForm();
		if($form) {
			$form->action .= '&tests=1';
			$this->wire()->session->addHookBefore('redirect', function(HookEvent $event) {
				$url = $event->arguments(0);
				$url .= '&tests=1';
				$event->arguments(0, $url);
			});
		}
		
		$modules = $this->wire()->modules;
	
		// TEST 1 ----------------------------------
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = 'Unusual widths tests';
		$inputfields->add($fieldset);
		
		$widths = array(
			73, 27,
			11, 89,
			37, 63,
			22, 22, 37, 19 // does not work with uk-width classes
		);
	
		foreach($widths as $n => $width) {
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->attr('name', "_w$n"); 
			$f->label = "Width $width%";
			$f->columnWidth = $width;
			$fieldset->add($f);
		}
		
		// TEST 2 ----------------------------------
	
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = 'Show-if and widths tests';
		$inputfields->add($fieldset);
	
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'test_radios');
		$f->label = 'Row 1 Col 1/4 (test_radios)';
		$f->description = 'Select option to reveal column(s)';
		$f->addOption(1, 'Option 1');
		$f->addOption(2, 'Option 2');
		$f->addOption(3, 'Option 3');
		$f->columnWidth = 25;
		$fieldset->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'test_checkbox');
		$f->label = 'Row 1 Col 2/4 (test_checkbox)';
		$f->description = 'Revealed by option 1. Check box to reveal column 3';
		$f->columnWidth = 25;
		$f->showIf = 'test_radios=1';
		$fieldset->add($f);
		
		$columns = array(
			// row 1
			array(
				'label' => 'Row 1 Col 3/4',
				'width' => 25, 
				'showIf' => 'test_radios=1, test_checkbox=1'
			),
			array(
				'label' => 'Row 1 Col 4/4',
				'width' => 25,
				'showIf' => 'test_radios=1|2'
			),
			// row 2
			array(
				'label' => 'Row 2 Col 1/4',
				'width' => 25,
			),
			array(
				'label' => 'Row 2 Col 2/4',
				'width' => 25,
			),
			array(
				'label' => 'Row 2 Col 3/4',
				'width' => 25,
			),
			array(
				'label' => 'Row 2 Col 4/4',
				'width' => 25,
				'showIf' => 'test_radios=3',
			),
			// row 3
			array(
				'label' => 'Row 3 Col 1/2',
				'width' => 75, 
				'showIf' => 'test_radios=3'
			),
			array(
				'label' => 'Row 3 Col 2/2',
				'width' => 25
			)
			
		);

		foreach($columns as $n => $col) {
			/** @var InputfieldText $f */
			$f = $modules->get('InputfieldText');
			$f->attr('name', "_test_text$n");
			$f->label = $col['label'];
			$f->columnWidth = $col['width'];
			if(isset($col['showIf'])) {
				$f->showIf = $col['showIf'];
				$f->notes = $col['showIf'];
			}
			$f->notes .= " ($col[width]%)";
			$fieldset->add($f);
		}
		
		// TEST 3 -----------------------------------------------


		/** @var InputfieldFieldset $fieldset */		
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->label = '33% widths tests w/show-if';
		$inputfields->add($fieldset);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', '_t1');
		$f->label = 'Col 1/3';
		$f->description = 'Always visible';
		$f->columnWidth = 33;
		$f->notes = "($f->columnWidth%)";
		$fieldset->add($f);

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'test_select');
		$f->description = 'Choose option 2';
		$f->label = 'Col 2/3 (test_select)';
		$f->addOption(1, 'Option 1');
		$f->addOption(2, 'Option 2');
		$f->addOption(3, 'Option 3');
		$f->columnWidth = 33;
		$f->notes = "($f->columnWidth%)";
		$f->value = 2;
		$fieldset->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', '_t2');
		$f->label = 'Col 3/3';
		$f->description = 'Revealed by option 2';
		$f->columnWidth = 33;
		$f->showIf = 'test_select=2';
		$f->notes = $f->showIf . " ($f->columnWidth%)";
		$fieldset->add($f);
	}
	
}
