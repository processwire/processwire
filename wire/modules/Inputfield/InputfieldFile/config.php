<?php namespace ProcessWire;

/**
 * ProcessWire File Inputfield (configuration)
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 *
 */
class InputfieldFileConfiguration extends Wire {
	
	/**
	 * Configuration settings for InputfieldFile
	 *
	 * @param InputfieldFile|Field $field
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getConfigInputfields($field, $inputfields) {

		$inputfield = $field instanceof Inputfield ? $field : null;

		if($inputfield && $inputfield->hasFieldtype === false) {
			// usage when Inputfield is used independently of any Field
			
			/** @var InputfieldText $f */
			$f = $this->modules->get('InputfieldText');
			$f->attr('name', 'extensions');
			$f->label = $this->_('Allowed file extensions for upload');
			$f->description = $this->_('One or more file extensions separated by a space.');
			$value = $field->get('extensions');
			if(empty($value)) $value = 'pdf jpg jpeg png gif doc docx csv xls xlsx ppt pptx';
			$value = explode(' ', $value);
			foreach($value as $k => $v) $value[$k] = trim($v, '. ');
			$f->val(implode(' ', $value));
			$inputfields->add($f);

			/** @var InputfieldInteger $f */
			$f = $this->modules->get('InputfieldInteger');
			$f->attr('name', 'maxFiles');
			$f->attr('value', (int) $field->get('maxFiles'));
			$f->label = $this->_('Maximum files allowed');
			$f->description = $this->_('0=No limit');
			$f->collapsed = Inputfield::collapsedBlank;
			$inputfields->add($f);
		}
		
		
		/** @var InputfieldFieldset $fs */
		$fs = $this->modules->get('InputfieldFieldset');
		$fs->attr('name', '_file_uploads');
		$fs->label = $this->_('File uploads');
		$fs->icon = 'cloud-upload';
		$fs->themeOffset = 1;
		$inputfields->add($fs);

		/** @var InputfieldCheckbox $f */
		$f = $this->modules->get("InputfieldCheckbox");
		$f->attr('name', 'unzip');
		$f->attr('value', 1);
		$f->setAttribute('checked', $field->get('unzip') ? 'checked' : '');
		$f->label = $this->_('Decompress ZIP files?');
		$f->icon = 'file-zip-o';
		$f->description = $this->_("If checked, ZIP archives will be decompressed and all valid files added as uploads (if supported by the hosting environment). Max files must be set to 0 (no max) in order for ZIP uploads to be functional."); // Decompress ZIP files description
		$f->collapsed = Inputfield::collapsedBlank;
		$fs->add($f);

		/** @var InputfieldCheckbox $f */
		$f = $this->modules->get("InputfieldCheckbox");
		$f->attr('name', 'overwrite');
		$f->label = $this->_('Overwrite existing files?');
		$f->icon = 'exchange';
		$f->description = $this->_('If checked, a file uploaded with the same name as an existing file will replace the existing file (description and tags will remain). If not checked, uploaded filenames will be renamed to be unique.'); // Overwrite description
		$f->notes = $this->_('Please note that when this option is enabled, AJAX-uploaded files are saved with the page immediately at upload, rather than when you click "save". As a result, you may wish to leave this option unchecked unless you have a specific need for it.'); // Overwrite notes
		if($field->get('overwrite')) $f->attr('checked', 'checked');
		$f->collapsed = Inputfield::collapsedBlank;
		$fs->add($f);

		if($inputfield && $inputfield->hasFieldtype === false) {
			$inputfields->add($this->getConfigInputfieldsDescription($field));
		}
	}

	/**
	 * @param Field|InputfieldFile $field
	 * @return InputfieldFieldset
	 * 
	 */
	public function getConfigInputfieldsDescription($field) {
		
		$rows = $field->get('descriptionRows');
		$rows = $rows === null ? 1 : (int) $rows;
	
		/** @var InputfieldFieldset $fs */
		$fs = $this->modules->get('InputfieldFieldset');
		$fs->attr('name', '_file_descriptions');
		$fs->label = $this->_('File descriptions');
		$fs->icon = 'align-left';
	
		/** @var InputfieldInteger $f */
		$f = $this->modules->get('InputfieldInteger');
		$f->attr('name', 'descriptionRows');
		$f->attr('value', $rows);
		$f->label = $this->_('Number of rows for description field?');
		$f->description = $this->_("Enter the number of rows available for the file description field, or enter 0 to not have a description field."); // Number of rows description
		$f->icon = 'text-height';
		$fs->add($f);

		if($this->wire()->languages && $rows >= 1) {
			/** @var InputfieldCheckbox $f */
			$f = $this->modules->get("InputfieldCheckbox");
			$f->attr('name', 'noLang');
			$f->label = $this->_('Disable multi-language descriptions?');
			$f->icon = 'language';
			$f->description = 
				$this->_('By default, descriptions are multi-language when you have Language Support installed.') . ' ' .
				$this->_('If you do not need multi-language descriptions for files, you can disable them here.');
			$f->checked((bool) $field->get('noLang'));
			$f->collapsed = Inputfield::collapsedBlank;
			$fs->add($f);
		}
		
		return $fs;
	}

}
