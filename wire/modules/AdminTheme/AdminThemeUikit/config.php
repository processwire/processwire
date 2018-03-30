<?php namespace ProcessWire;

if(!defined("PROCESSWIRE")) die();

/**
 * Implementation for Uikit admin theme getConfigInputfields method
 * 
 * @param AdminTheme|AdminThemeUikit $adminTheme
 * @param InputfieldWrapper $inputfields
 * 
 */
function AdminThemeUikitConfig(AdminTheme $adminTheme, InputfieldWrapper $inputfields) {

	$defaultFileNote = __('When blank, the default file used.') . ' ';
	$defaultFileDesc = __('Enter path relative to homepage URL.');
	$recommendedLabel = __('(RECOMMENDED)'); 
	$experimentalLabel = __('(EXPERIMENTAL)'); 
	$defaultLabel = __('(default)');
	$exampleLabel = __('example'); 

	$modules = $adminTheme->wire('modules');
	$session = $adminTheme->wire('session');
	$layout = $adminTheme->layout;
	$userTemplateURL = $inputfields->wire('config')->urls->admin . 'setup/template/edit?id=3';
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Masthead and navigation');
	$fieldset->icon = 'navicon';
	$fieldset->collapsed = Inputfield::collapsedYes;
	$inputfields->add($fieldset);

	/** @var InputfieldSelect $f */
	$f = $modules->get('InputfieldSelect');
	$f->attr('name', 'userAvatar');
	$f->label = __('User avatar');
	$f->icon = 'user-circle';
	$f->addOption('gravatar', __('Gravatar (external service that determines avatar from email)'));
	$f->description = __('Select an image field, Gravatar, or icon to show for the user avatar in the masthead.');
	$numImgFields = 0;
	foreach($modules->wire('templates')->get('user')->fieldgroup as $field) {
		if(!$field->type instanceof FieldtypeImage) continue;
		$f->addOption("$field->id:$field->name", sprintf(__('Image field: %s'), $field->name));
		$numImgFields++;
	}
	if(!$numImgFields) {
		$f->notes = __('There are no image fields present on the “user” template at present, so only icons and Gravatar are shown.') . ' ';
	}
	$f->notes .= sprintf(__('You may add image fields to your user template [here](%s).'), $userTemplateURL);
	
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
		$f->addOption("icon.$icon", sprintf(__('Icon: %s'), $icon));
	}
	$f->attr('value', $adminTheme->get('userAvatar'));
	$fieldset->add($f);

	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'userLabel');
	$f->label = __('User navigation label format');
	$f->description = 
		__('This label appears next to the user avatar image/icon.') . ' ' . 
		__('Specify field(s) and format to use for the user label, or blank for no user label.') . ' ' . 
		sprintf(__('Use any fields/properties from your [user](%s) template surrounded in {brackets}.'), $userTemplateURL) . ' ' . 
		__('Use {Name} for capitalized name, which is the default setting, or use {name} for lowercase name.');
	$f->notes = __('Examples: “{name}”, “{Name}”, “{title}”, “{first_name} {last_name}”, “{company.title}”, etc.');
	$f->attr('value', $adminTheme->userLabel);
	$fieldset->add($f);

	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'logoAction');
	$f->label = __('Masthead logo click action');
	$f->addOption(0, __('Admin root page list'));
	$f->addOption(1, __('Open offcanvas navigation'));
	$f->attr('value', (int) $adminTheme->logoAction);
	$fieldset->add($f);

	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Layout');
	$fieldset->icon = 'newspaper-o';
	$fieldset->collapsed = Inputfield::collapsedYes; 
	$inputfields->add($fieldset);

	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$f->attr('id+name', 'layout');
	$f->label = __('Interface type');
	$f->addOption('', __('Traditional with masthead navigation') . 
		' [span.detail] ' . $recommendedLabel . ' [/span]');
	$opt = __('Page tree navigation in sidebar');
	$f->addOption('sidenav-tree', $opt . ' ' . __('(left)') . 
		'* [span.detail] ' . $experimentalLabel . ' [/span]');
	$f->addOption('sidenav-tree-alt', $opt . ' ' . __('(right)') . 
		'* [span.detail] ' . $experimentalLabel . ' [/span]'); 
	// $f->addOption('sidenav', __('Sidebar navigation (left) + page tree navigation (right)'));
	$f->attr('value', $layout);
	$f->notes = __('*Sidebar layouts not compatible with SystemNotifications module and may have issues with other modules.');
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
	
	$f = $modules->get('InputfieldInteger');
	$f->attr('name', 'maxWidth'); 
	$f->label = __('Maximum layout width'); 
	$f->description = __('Specify the maximum width of the layout (in pixels) or 0 for no maximum.'); 
	$f->notes = __('Applies to traditional interface only.'); 
	$f->attr('value', $adminTheme->maxWidth); 
	$fieldset->add($f); 

	$testURL = $modules->wire('config')->urls->admin . 'profile/?test_notices';
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'groupNotices'); 
	$f->label = __('Notifications style');
	$f->notes = __('Does not apply if the SystemNotifications module is installed.'); 
	$f->addOption(1, __('Group by type with expand/collapse control') . " ([$exampleLabel]($testURL=group-on))");
	$f->addOption(0, __('Always show all') . " ([$exampleLabel]($testURL=group-off))"); 
	$f->attr('value', (int) $adminTheme->groupNotices); 
	$fieldset->appendMarkup .= "<script>$('#wrap_Inputfield_groupNotices .InputfieldContent').find('a').addClass('pw-modal');</script>";
	$modules->get('JqueryUI')->use('modal');
	$fieldset->add($f); 

	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Custom files');
	$fieldset->collapsed = Inputfield::collapsedBlank;
	$fieldset->icon = 'files-o';
	$inputfields->add($fieldset);

	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'cssURL');
	$f->attr('value', $adminTheme->get('cssURL'));
	$f->label = __('Primary CSS file');
	$f->description = $defaultFileDesc . ' ' . 
		__('We do not recommend changing this unless you are an admin theme developer.'); 
	$f->notes = $defaultFileNote . " " .
		"[uikit.pw.css](" . $modules->wire('config')->urls('AdminThemeUikit') . "uikit/dist/css/uikit.pw.css)";
	$f->collapsed = Inputfield::collapsedBlank;
	$f->icon = 'file-code-o';
	$fieldset->add($f);

	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'logoURL');
	$f->attr('value', $adminTheme->get('logoURL'));
	$f->label = __('Logo image file');
	$f->description = $defaultFileDesc;
	$f->notes = $defaultFileNote . 
		__('File should be PNG, GIF, JPG or SVG, on transparent background, and at least 100px in both dimensions.');
	$f->collapsed = Inputfield::collapsedBlank;
	$f->icon = 'file-image-o';
	$fieldset->add($f);

	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Form field visibility settings');
	$fieldset->description =
		__('These settings affect all form fields in the admin.') . ' ' .
		__('Any of these settings (and others) may also be specified individually for a given field.') . ' ' .
		__('If you specify a setting here, it will override individual field settings.') . ' ' .
		__('See: Setup > Fields > [any field] > Input (tab) > Admin Theme Settings.');
	$fieldset->icon = 'flask';
	$fieldset->collapsed = Inputfield::collapsedYes;
	$inputfields->add($fieldset);

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
	$f->label = __('Input types that should have no border');
	$f->description = __('This setting applies to any selected types when used at 100% width.');
	$f->icon = 'low-vision';
	$f->set('themeOffset', true);
	foreach($types as $className => $name) {
		$f->addOption($className, $name);
	}
	$f->attr('value', $adminTheme->noBorderTypes);
	$fieldset->add($f);

	/** @var InputfieldAsmSelect $f */
	/*
	$f = $modules->get('InputfieldAsmSelect');
	$f->attr('name', 'cardTypes');
	$f->label = __('Input types that should use the “Card” style');
	$f->description = __('This field is an example of the card style.');
	$f->notes = __('Does not apply to types selected to have no border.');
	$f->icon = 'list-alt';
	$f->set('themeBorder', 'card');
	$f->set('themeOffset', true);
	foreach($types as $className => $name) {
		$f->addOption($className, $name);
	}
	$f->attr('value', $adminTheme->cardTypes);
	$fieldset->add($f);
	*/

	/** @var InputfieldAsmSelect $f */
	$f = $modules->get('InputfieldAsmSelect');
	$f->attr('name', 'offsetTypes');
	$f->label = __('Input types that should be offset with a additional top/bottom margin.');
	$f->description = __('As an example, the fields in this fieldset are using this option.'); 
	$f->set('themeOffset', true); 
	$f->icon = 'arrows-v';
	foreach($types as $className => $name) {
		$f->addOption($className, $name);
	}
	$f->attr('value', $adminTheme->offsetTypes);
	// $f->showIf = 'useOffset=0';
	$fieldset->add($f);

	/** @var InputfieldCheckboxes $f */
	/*
	$f = $modules->get('InputfieldCheckbox');
	$f->attr('name', 'useOffset');
	$f->label = __('Vertically offset ALL input types?');
	$f->description =
		__('When checked, a vertical margin is added to every field.') . ' ' .
		__('This may provide additional clarity in some cases, but consumes more vertical space.');
	$f->collapsed = Inputfield::collapsedBlank;
	$f->icon = 'arrows-v';
	if($adminTheme->useOffset) $f->attr('checked', 'checked');
	$fieldset->add($f);
	*/

	/*
	// The following is just for development/testing 
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = 'Test fieldset';
	$inputfields->add($fieldset);

	$f = $modules->get('InputfieldRadios');
	$f->attr('name', 'test_radios');
	$f->label = 'Test radios';
	$f->addOption(1, 'Option 1');
	$f->addOption(2, 'Option 2');
	$f->addOption(3, 'Option 3');
	$f->columnWidth = 35;
	$fieldset->add($f);
	
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text0');
	$f->label = 'Test text 0';
	//$f->showIf = 'test_radios=1';
	$f->columnWidth = 65;
	$fieldset->add($f);
	
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text1');
	$f->label = 'Test text 1';
	$f->columnWidth = 20;
	$fieldset->add($f);

	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text2');
	$f->label = 'Test text 2';
	//$f->showIf = 'test_radios=1|2';
	$f->columnWidth = 20;
	$fieldset->add($f);

	// These inputfields should appear as a second row
	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text3');
	$f->label = 'Test text 3';
	$f->columnWidth = 20;
	$f->showIf = 'test_radios=1';
	$fieldset->add($f);

	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text4');
	$f->label = 'Test text 4';
	$f->columnWidth = 20;
	$fieldset->add($f);

	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text5');
	$f->label = 'Test text 5';
	//$f->showIf = 'test_radios=3';
	$f->columnWidth = 20;
	$fieldset->add($f);

	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text6');
	$f->label = 'Test text 6';
	//$f->showIf = 'test_radios=3';
	$f->columnWidth = 75;
	$fieldset->add($f);

	$f = $modules->get('InputfieldText');
	$f->attr('name', 'test_text7');
	$f->label = 'Test text 7';
	//$f->showIf = 'test_radios=3';
	$f->columnWidth = 25;
	$fieldset->add($f);
	*/

}