<?php namespace ProcessWire;

/**
 * ProcessWire File Fieldtype (configuration)
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 *
 */
class FieldtypeFileConfiguration extends Wire {

	/**
	 * @var FieldtypeFile
	 *
	 */
	protected $fieldtype;

	/**
	 * Construct
	 *
	 * @param FieldtypeFile $fieldtype
	 *
	 */
	public function __construct(FieldtypeFile $fieldtype) {
		$this->fieldtype = $fieldtype;
		$fieldtype->wire($this);
		parent::__construct();
	}

	/**
	 * Fieldtype file field configuration
	 *
	 * @param Field $field
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldWrapper
	 *
	 */
	public function getConfigInputfields(Field $field, InputfieldWrapper $inputfields) {

		$modules = $this->wire()->modules;
		$input = $this->wire()->input;

		/** @var FieldtypeFile $fieldtype */
		$extensionInfo = $this->fieldtype->getValidFileExtensions($field);
		$fileValidatorsUrl = 'https://processwire.com/modules/category/file-validator/';
	
		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->attr('name', '_files_fieldset');
		$fs->label = $this->_('Files');
		$fs->icon = 'files-o';
		$inputfields->add($fs);

		// extensions
		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'extensions');
		$value = $field->get('extensions');
		if(!$value) $value = $this->fieldtype->get('defaultFileExtensions');
		$f->attr('value', $value);
		$f->attr('rows', 3);
		$f->label = $this->_('Allowed file extensions');
		$f->description = $this->_('Enter all file extensions allowed by this upload field. Separate each extension by a space. No periods or commas. This field is not case sensitive.'); // Valid file extensions description
		$f->icon = 'files-o';
		if(count($extensionInfo['invalid']) && !$input->is('POST')) {
			foreach($extensionInfo['invalid'] as $ext) {
				$error = sprintf(
					$this->_('File extension %s must be removed, whitelisted, or have a [file validator](%s) module installed.'),
					strtoupper($ext),
					$fileValidatorsUrl
				);
				$this->fieldtype->error($error, Notice::allowMarkdown | Notice::noGroup);
			}
		}
		$fs->add($f);

		if(count($extensionInfo['invalid']) || count($extensionInfo['whitelist'])) {
			/** @var array $okExtensions */
			$badExtensions = array_merge($extensionInfo['invalid'], $extensionInfo['whitelist']);
			ksort($badExtensions);
			/** @var InputfieldCheckboxes $f Whitelisted file extensions */
			$f = $modules->get('InputfieldCheckboxes');
			$f->attr('name', 'okExtensions');
			$f->label = $this->_('File extensions to allow without validation (whitelist)');
			$f->icon = 'warning';
			foreach($badExtensions as $ext) $f->addOption($ext);
			$f->description =
				sprintf(
					$this->_('These file extensions need a [file validator module](%s) installed. Unchecked extensions have been disabled for safety.'),
					$fileValidatorsUrl
				) . ' ' .
				$this->_('To ignore and allow the file extension without file validation, check the box next to it (not recommended).') . ' ' .
				$this->_('If you don’t need an extension, please remove it from your valid file extensions list.');
			$f->attr('value', $extensionInfo['whitelist']);
			$fs->add($f);
		} else {
			$field->set('okExtensions', array());
		}

		$maxFiles = (int) $field->get('maxFiles');
		// max files
		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->attr('name', 'maxFiles');
		$f->attr('value', $maxFiles);
		$f->label = $this->_('Maximum number of files');
		$f->icon = 'step-forward';
		$f->appendMarkup = "&nbsp; <span class='description'>" . $this->_('(a value of 0 means no limit)') . "</span>";
		$fs->append($f);

		$this->getConfigInputfieldsOutputFormat($field, $fs);

		require_once($this->wire()->config->paths('InputfieldFile') . 'config.php');
		$configuration = new InputfieldFileConfiguration();
		$this->wire($configuration);
		$descriptionFieldset = $configuration->getConfigInputfieldsDescription($field); 
		$descriptionFieldset->themeOffset = 1;
		$descriptionFieldset->description = 
			$this->_('Each file may have optional description text that is input in the page editor and can be output on the front-end.') . ' ' . 
			$this->_('For instance, it’s common to use the description text as the “alt” attribute when rendering `<img>` tags for image files.'); 
		$inputfields->add($descriptionFieldset);

		// textformatters
		/** @var InputfieldAsmSelect $f */
		$f = $modules->get('InputfieldAsmSelect');
		$f->setAttribute('name', 'textformatters');
		$f->label = $this->_('Text formatters (for file descriptions)');
		$f->description = $this->_('Select one or more text formatters (and their order) that will be applied to the file description when output formatting is active. The HTML Entity Encoder is recommended as a minimum.');
		$f->icon = 'text-width';
		foreach($modules->findbyPrefix('Textformatter', 1) as $moduleName => $moduleInfo) {
			$f->addOption($moduleName, "$moduleInfo[title]");
		}
		if(!is_array($field->get('textformatters'))) {
			$field->set('textformatters', $field->get('entityEncode') ? array('TextformatterEntities') : array());
		}
		$f->attr('value', $field->get('textformatters'));
		$descriptionFieldset->add($f);

		// entity encode (deprecated)
		/** @var InputfieldHidden $f */
		$f = $modules->get("InputfieldHidden");
		$f->attr('name', 'entityEncode');
		$f->attr('value', '');
		if($field->get('entityEncode')) $f->attr('checked', 'checked');
		$descriptionFieldset->add($f);
		$field->set('entityEncode', null);

		$this->getConfigInputfieldsCustomFields($field, $inputfields);
	
		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->attr('name', '_file_tags'); 
		$fs->label = $this->_('File tags'); 
		$fs->description = 
			$this->_('Tags enable you to have predefined or custom 1-word tags to accompany each file.') . ' ' . 
			$this->_('When used, files can have any number of tags.') . ' ' . 
			$this->_('Tags can be useful for categorizing a file.') . ' ' . 
			$this->_('For instance, a tag of `sidebar` might indicate an image you want to automatically display in a sidebar.');
		$fs->icon = 'tags';
		$inputfields->add($fs);

		// use tags
		/** @var InputfieldRadios $f */
		$f = $modules->get("InputfieldRadios");
		$f->attr('name', 'useTags');
		$f->label = $this->_('Use file tags?');
		$f->description = $this->_('When enabled, the field will also contain an option for user selected (or entered) tags for each file.'); // Use tags description
		$f->icon = 'tags';
		$predefinedLabel = $this->_('User selects from list of predefined tags');
		$f->addOption(FieldtypeFile::useTagsOff, $this->_('Tags disabled'));
		$f->addOption(FieldtypeFile::useTagsNormal, $this->_('User enters tags by text input'));
		$f->addOption(FieldtypeFile::useTagsPredefined, $predefinedLabel);
		$f->addOption(FieldtypeFile::useTagsNormal | FieldtypeFile::useTagsPredefined, $predefinedLabel . ' + ' . $this->_('can input their own'));
		$f->attr('value', (int) $field->get('useTags'));
		if(!$f->attr('value')) $f->collapsed = Inputfield::collapsedYes;
		$fs->add($f);

		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'tagsList');
		$f->label = $this->_('Predefined file tags');
		$f->description = $this->_('Enter tags separated by a space. Tags may contain letters, digits, underscores or hyphens.');
		$f->icon = 'tags';
		$f->attr('value', $field->get('tagsList'));
		$f->showIf = 'useTags>1';
		$fs->add($f);
	
		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->attr('name', '_file_tags_example'); 
		$f->label = $this->_('Examples of using tags'); 
		$f->icon = 'code';
		$f->collapsed = Inputfield::collapsedYes;
		$f->entityEncodeText = false;
		$example = array(
			'// ' . $this->_('Display all files with tag “sidebar”'), 
			'foreach($page->images as $file) {',
			'  if($file->hasTag("sidebar")) {', 
			'    echo "<img src=\'$file->url\' alt=\'$file->description\'>";',
			'  }',
			'}',
			'',
			'// ' . $this->_('Same as above using another method'), 
			'foreach($page->images->findTag("sidebar") as $file) {',
			'  echo "<img src=\'$file->url\' alt=\'$file->description\'>";',
			'}',
			'',
			'// ' . $this->_('Get one file having tag'), 
			'$file = $page->images->getTag("hero");',
			'if($file) {',
			'  // ... ',
			'}',
			'',
			'// ' . $this->_('Display tags for all files'), 
			'foreach($page->images as $file) {',
			'  echo "<li>$file->name: $file->tags</li>";',
			'}',
		);
		$docs = array(
			'Pagefiles::findTag()' => 'pagefiles/find-tag/',
			'Pagefiles::getTag()' => 'pagefiles/get-tag/',
			'Pagefile::tags()' => 'pagefile/tags/',
			'Pagefile::hasTag()' => 'pagefile/has-tag/',
		);
		foreach($example as $k => $v) {
			$example[$k] = htmlspecialchars($v);
		}
		$f->value = "<pre style='margin:0'><code>" . implode("\n", $example) . "</code></pre>";
		foreach($docs as $label => $path) {
			$docs[$label] = "<a target='_blank' href='https://processwire.com/api/ref/$path'>$label</a>";
		}
		$f->notes = $this->_('Documentation:') . ' ' . implode(', ', $docs);
		$fs->add($f);

		return $inputfields;
	}

	/**
	 * @param Field $field
	 * @param InputfieldWrapper $fs
	 * 
	 */
	protected function getConfigInputfieldsOutputFormat(Field $field, InputfieldWrapper $fs) {
		
		$modules = $this->wire()->modules;
		
		// output format
		$typeMulti = $this->fieldtype instanceof FieldtypeImage ? 'Pageimages' : 'Pagefiles';
		$typeSingle = $this->fieldtype instanceof FieldtypeImage ? 'Pageimage' : 'Pagefile';
		
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->attr('name', 'outputFormat');
		$f->label = $this->_('Formatted value');
		$f->description = 
			$this->_('Select the type of value you want this field to provide when accessed from the API on the front-end of your site.') . ' ' . 
			sprintf($this->_('When output formatting is off, the value is always a %s array.'), $typeMulti);
		
		$f->addOption(FieldtypeFile::outputFormatAuto,
			$this->_('Automatic') . ' ' .
			'[span.detail] ' . $this->_('(single file or null when max files set to 1, multi-file array of items otherwise)') . ' [/span]'
		);
		
		$f->addOption(FieldtypeFile::outputFormatArray,
			$this->_('Multi-file array of items') . ' ' .
			"[span.detail] ($typeMulti) [/span]"
		);
		
		$f->addOption(FieldtypeFile::outputFormatSingle,
			$this->_('Single-file when populated, null when empty') . ' ' .
			"[span.detail] ($typeSingle) [/span]"
		);
		
		$f->addOption(FieldtypeFile::outputFormatString,
			$this->_('Rendered string of markup/text') . ' ' .
			"[span.detail] " . $this->_('(configurable)') . " [/span]"
		);
		
		$f->attr('value', (int) $field->get('outputFormat'));
		$f->collapsed = Inputfield::collapsedBlank;
		$f->icon = 'magic';
		$f->notes = 
			$this->_('Documentation:') . ' ' .
			'[Pagefiles](https://processwire.com/api/ref/pagefiles/), [Pagefile](https://processwire.com/api/ref/pagefile/)';
		
		if($this->fieldtype instanceof FieldtypeImage) {
			$f->notes .= ", [Pageimages](https://processwire.com/api/ref/pageimages/), [Pageimage](https://processwire.com/api/ref/pageimage/)";
		}
		
		$fs->add($f);

		$examples = array(
			'Pagefiles' => array(
				'foreach($page->field_name as $file) {',
				'  echo "<li><a href=\'$file->url\'>$file->name</a> - $file->description</li>";',
				'}'
			),
			'Pagefile' => array(
				'$file = $page->field_name;',
				'if($file) {',
				'  echo "<p><a href=\'$file->url\'>$file->name</a> - $file->description</p>',
				'}'
			),
			'Pageimages' => array(
				'foreach($page->field_name as $image) {',
				'  $thumb = $image->size(100, 100);',
				'  echo "<a href=\'$image->url\'><img src=\'$thumb->url\' alt=\'$image->description\'></a>";',
				'}'
			),
			'Pageimage' => array(
				'$image = $page->field_name;',
				'if($image) {',
				'  $thumb = $image->size(100, 100);',
				'  echo "<a href=\'$image->url\'><img src=\'$thumb->url\' alt=\'$image->description\'></a>";',
				'}'
			),
		);

		foreach($examples as $key => $example) {
			foreach($example as $k => $v) {
				$v = str_replace('field_name', $field->name, $v);
				$v = htmlspecialchars($v);
				$example[$k] = $v;
			}
			$examples[$key] = '<pre style="margin:0"><code>' . implode("\n", $example) . '</code></pre>';
		}

		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->attr('name', '_exampleMulti');
		$f->label = $this->_('Multi-file usage example');
		$f->showIf = 'outputFormat=' . FieldtypeFile::outputFormatArray . '|' . FieldtypeFile::outputFormatAuto;
		$f->label .= " ($typeMulti)";
		$f->icon = 'code';
		$f->collapsed = Inputfield::collapsedYes;
		if($typeMulti === 'Pageimages') {
			$f->value = $examples['Pageimages'];
		} else {
			$f->value = $examples['Pagefiles'];
		}
		$fs->add($f);

		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->attr('name', '_exampleSingle');
		$f->label = $this->_('Single file usage example');
		$f->showIf = 'outputFormat=' . FieldtypeFile::outputFormatSingle . '|' . FieldtypeFile::outputFormatAuto;
		$f->label .= " ($typeSingle)";
		$f->icon = 'code';
		$f->collapsed = Inputfield::collapsedYes;
		if($typeSingle === 'Pageimage') {
			$f->value = $examples['Pageimage'];
		} else {
			$f->value = $examples['Pagefile'];
		}
		$fs->add($f);
		
		// output string
		$placeholders = array('{url}', '{description}', '{tags}'); 
		$ie = $this->_('i.e.'); // i.e. as in “for example”
		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->attr('name', 'outputString');
		$f->label = $this->_('Rendered string of markup/text');
		if($typeSingle === 'Pageimage') {
			$f->attr('placeholder', "$ie <img src='{url}' alt='{description}' />");
			$placeholders[] = '{width}';
			$placeholders[] = '{height}';
		} else {
			$f->attr('placeholder', "$ie <a href='{url}'>{description}</a>");
		}
		$f->attr('value', $field->get('outputString') ? $field->get('outputString') : '');
		$f->description = $this->_('Provide the rendered string of text you want to output as the value of this field. If the field contains multiple items, this string will be rendered multiple times. If the field contains no items, a blank string will be used.');
		$f->notes = $this->_('You may use any of the following placeholder tags:') . ' ' . implode(', ', $placeholders);
		$f->showIf = "outputFormat=" . FieldtypeFile::outputFormatString;
		$f->icon = 'magic';
		$fs->add($f);
	}

	/**
	 * @param Field $field
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	protected function getConfigInputfieldsCustomFields(Field $field, InputfieldWrapper $inputfields) {
		
		$templates = $this->wire()->templates;
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;
	
		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->attr('name', '_custom_fields');
		$fs->label = $this->_('Custom fields');
		$fs->icon = 'newspaper-o';
		$fs->themeOffset = 1;
		$fs->entityEncodeText = false;
		$fs->description = 
			$this->_('Custom fields enable you to define additional text, number and selection fields to accompany each file as metadata.') . ' ' . 
			$this->_('Use this when you need something more than just a file description and/or tags.') . ' ' . 
			sprintf($this->_('For more information, see: %s.'), 
				"<a target='_blank' href='https://processwire.com/blog/posts/pw-3.0.142/'>" .
					$this->_('How custom fields for files/images work') .
				"</a>"
			);	
		$inputfields->add($fs);
		
		// Custom fields
		$customTplEnabledName = 'field-' . $field->name;
		$customTplDisabledName = 'field-x-' . $field->name;
		$customTpl = $templates->get($customTplEnabledName);
		if(!$customTpl) $customTpl = $templates->get($customTplDisabledName);
		$customVal = $input->is('POST') ? $input->post('_use_custom_fields') : null;
		$editLink = $customTpl ? "<a class='pw-modal' href='$customTpl->editUrl'>$customTpl->name</a>" : "";

		/** @var InputfieldToggle $f */
		$f = $modules->get('InputfieldToggle');
		$f->name('_use_custom_fields');
		$f->label = $this->_('Use custom fields for each file?');
		//$f->labelType = InputfieldToggle::labelTypeEnabled;
		$f->entityEncodeText = false;
		$f->icon = 'toggle-on';
		$f->useReverse = true;
		$fs->add($f);
		
		if($customVal === "1") {
			// enabled
			$f->val(1);
			if($customTpl && $customTpl->name === $customTplEnabledName) {
				// already enabled
			} else if($customTpl && $customTpl->name === $customTplDisabledName) {
				// rename template field-x-name to field-name
				$templates->rename($customTpl, $customTplEnabledName);
				$f->val(0);
			} else if(!$customTpl) {
				// create custom template
				$customTpl = $templates->add($customTplEnabledName, array('noGlobal' => true));
				$this->message(sprintf($this->_('Created custom fields template: %s'), $customTpl->name));
			}
			
		} else if($customVal === "0") {
			// disabled
			$f->val(0);
			if($customTpl) {
				// rename custom template to field-x-name
				$templates->rename($customTpl, $customTplDisabledName);
			}
			
		} else if($customTpl) {
			if($customTpl->name === $customTplEnabledName) {
				$f->description = 
					$this->_('Custom fields are enabled and managed from a template.') . ' ' . 
					$this->_('Use the “Edit custom fields” button below to add or modify the fields you want to maintain for each file.') . ' ' . 
					$this->_('Most text, number and selection fields will work here, but some field types may not.'); 
				$f->val(1);
			
				/** @var InputfieldButton $btn */
				$btn = $modules->get('InputfieldButton');
				$btn->attr('name', '_edit_custom_fields');
				$btn->href = $customTpl->editUrl;
				$btn->addClass('pw-modal');
				$btn->value = $this->_('Edit custom fields');
				$btn->icon = 'pencil';
				$btn->showIf = "$f->name=1";
				$btn->detail = sprintf($this->_('Custom fields template is: %s'), '**' . $customTpl->name . '**');
				$fs->add($btn);
			} else if($customTpl->name === $customTplDisabledName) {
				$f->notes = sprintf($this->_('To permanently disable custom fields for this field, delete template %s.'), $editLink);
				$f->val(0);
			} else {
				$f->val($customTpl ? 1 : 0);
			}
			
		} else {
			$f->val(0);
		}
		if(!$customVal) {
			$f->description = trim(
				$f->description . ' ' . 
				$this->_('When you enable custom fields, save and then return here to configure them.')
			); 
		}
		
	}
		

	/**
	 * Fieldtype file field advanced configuration
	 *
	 * @param Field $field
	 * @param InputfieldWrapper $inputfields
	 * @return InputfieldWrapper
	 *
	 */
	public function getConfigAdvancedInputfields(Field $field, InputfieldWrapper $inputfields) {
		$modules = $this->wire()->modules;
		
		// default value page
		/** @var InputfieldPageListSelect $f */
		$f = $modules->get('InputfieldPageListSelect');
		$f->attr('name', 'defaultValuePage');
		$f->label = $this->_('Page containing default/fallback value for this field');
		$f->description = 
			$this->_('Optionally select a page that will contain the default/fallback value, in this same field.') . ' ' . 
			$this->_('You may wish to create a page specifically for this purpose.') . ' ' . 
			sprintf($this->_('The selected page must have a `%s` field populated with one or more files.'), $field->name);
		$f->attr('value', (int) $field->get('defaultValuePage'));
		$inputfields->add($f);
		
		// inputfield class
		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'inputfieldClass');
		$f->label = $this->_('Inputfield type for files field');
		$f->description = $this->_('The type of field that will be used to collect input for this files field.');
		$f->notes = $this->_('Change this only if instructed to do so by 3rd party Inputfield module instructions.');
		$f->required = true;
		$defaultClass = $this->fieldtype->getDefaultInputfieldClass();

		foreach($modules->findByPrefix('Inputfield') as $fm) {
			if($defaultClass == 'InputfieldFile' && strpos($fm, 'InputfieldImage') === 0) continue;
			if("$fm" == $defaultClass || is_subclass_of(__NAMESPACE__ . "\\$fm", __NAMESPACE__ . "\\$defaultClass")) {
				$f->addOption("$fm", str_replace("Inputfield", '', "$fm"));
			}
		}

		$inputfieldClass = $field->get('inputfieldClass');
		$f->attr('value', $inputfieldClass ? $inputfieldClass : $defaultClass);
		// $f->collapsed = $inputfieldClass && $inputfieldClass != $defaultClass ? Inputfield::collapsedNo : Inputfield::collapsedYes;

		$inputfields->add($f);

		return $inputfields;
	}

	/**
	 * Module config
	 *
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		$modules = $this->wire()->modules;
		$fields = $this->wire()->fields;
		$moduleNames = $modules->findByPrefix('Fieldtype');
		$defaultAllowFieldtypes = $this->fieldtype->getAllowFieldtypes(true);
		$names = array();
		$blacklist = array( // types always disallowed
			'File',
			'Image',
			'Repeater',
			'RepeaterMatrix',
			'PageTable',
			'Options',
			'Comments',
			'Table'
		);
		
		ksort($moduleNames);

		/** @var InputfieldCheckboxes $f */
		$f = $modules->get('InputfieldCheckboxes');
		$f->attr('name', 'allowFieldtypes');
		$f->label = $this->_('Allowed Fieldtype modules for custom fields');
		$f->description = $this->_('Types with strikethrough are not likely to be 100% compatible.');
		$f->optionColumns = 3;
		$f->entityEncodeText = false;

		foreach($moduleNames as $key => $moduleName) {

			list(,$name) = explode('Fieldtype', $moduleName, 2);
			$names[$name] = $name;

			if(in_array($name, $blacklist)) {
				unset($names[$name]);
				continue;
			}

			if(in_array($name, $defaultAllowFieldtypes)) continue; // these are fine

			// check schema of field by finding an example of one
			$allow = false;

			foreach($fields as $field) {
				// @var Field $field 
				if($field->type instanceof FieldtypeFile) continue;
				if(!wireInstanceOf($field->type, $moduleName)) continue;

				// verify that field DB table is responsible for all data created by the field
				$schema = $field->type->getDatabaseSchema($field);
				if(isset($schema['xtra']['all']) && $schema['xtra']['all'] !== true) continue;

				unset($schema['data'], $schema['pages_id'], $schema['keys'], $schema['xtra']);
				// if there's not any other schema required by the Fieldtype, it can be supported here
				if(!count($schema)) $allow = true;
				break;
			}
			if(!$allow) {
				// indicate with strikethrough potential issue with this type
				$names[$name] = "<s>$name</s>";
			}
		}

		foreach($names as $key => $name) {
			$f->addOption($key, $name);
		}

		$f->val($this->fieldtype->getAllowFieldtypes());
		$inputfields->add($f);
	}
}	
