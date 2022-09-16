<?php namespace ProcessWire;

/**
 * Handles import/export for ProcessField
 * 
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 * @method InputfieldForm buildExport()
 * @method InputfieldForm buildImport()
 * @method InputfieldForm buildInputDataForm()
 * @method void processImport()
 * 
 */

class ProcessFieldExportImport extends Wire {
	
	public function __construct() {
		set_time_limit(600); 
	}

	/**
	 * Return export data for all given $exportFields
	 * 
	 * @param array $exportFields field names
	 * @return array
	 * 
	 */
	protected function getExportData(array $exportFields) {
		$data = array();
		foreach($this->wire('fields') as $field) {
			if(!in_array($field->name, $exportFields)) continue;
			$a = $field->getExportData();
			$data[$field->name] = $a;
		}
		return $data; 
	}
	
	/**
	 * Execute export
	 *
	 * @return InputfieldForm
	 *
	 */
	public function ___buildExport() {

		/** @var InputfieldForm $form */
		$form = $this->wire('modules')->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';

		$exportFields = $this->wire('input')->post('export_fields');

		if(empty($exportFields)) {

			$f = $this->wire('modules')->get('InputfieldSelectMultiple');
			$f->attr('id+name', 'export_fields');
			$f->label = $this->_('Select the fields that you want to export');
			$f->icon = 'copy';

			$maxName = 0;
			$maxLabel = 0;
			$numFields = 0;
			
			foreach($this->wire('fields') as $field) {
				if(strlen($field->name) > $maxName) $maxName = strlen($field->name);
				$label = $field->getLabel();
				if(strlen($label) > $maxLabel) $maxLabel = strlen($label);
				$numFields++;
			}
		
			$fieldName = $this->_('NAME');
			$fieldLabel = $this->_('LABEL');
			$fieldType = $this->_('TYPE'); 
			
			$label = $fieldName . ' ' . str_repeat('.', $maxName - strlen($fieldName) + 3) . ' ' .
				$fieldLabel . str_repeat('.', $maxLabel - strlen($fieldLabel) + 3) . ' ' .
				$fieldType;
			
			$f->addOption(0, $label, array('disabled' => 'disabled'));
			
			foreach($this->wire('fields') as $field) {
				$fieldLabel = $field->getLabel();
				$label = $field->name . ' ' . str_repeat('.', $maxName - strlen($field->name) + 3) . ' ' .
					$fieldLabel . str_repeat('.', $maxLabel - strlen($fieldLabel) + 3) . ' ' .
					str_replace('Fieldtype', '', $field->type);
				$f->addOption($field->name, $label);
			}
			
			$f->notes = $this->_('Shift+Click to select multiple in sequence. Ctrl+Click (or Cmd+Click) to select multiple individually. Ctrl+A (or Cmd+A) to select all.');
			$f->attr('size', $numFields+1);
			$form->add($f);

			$f = $this->wire('modules')->get('InputfieldSubmit');
			$f->attr('name', 'submit_export');
			$f->attr('value', $this->_x('Export', 'button'));
			$form->add($f);

		} else {

			$form = $this->wire('modules')->get('InputfieldForm');
			$f = $this->wire('modules')->get('InputfieldTextarea');
			$f->attr('id+name', 'export_data');
			$f->label = $this->_('Export Data');
			$f->description = $this->_('Copy and paste this data into the "Import" box of another installation.');
			$f->notes = $this->_('Click anywhere in the box to select all export data. Once selected, copy the data with CTRL-C or CMD-C.');
			$f->attr('value', wireEncodeJSON($this->getExportData($exportFields), true, true));
			$form->add($f);

			$f = $this->wire('modules')->get('InputfieldButton');
			$f->href = './';
			$f->value = $this->_x('Ok', 'button'); 
			$form->add($f);
		}

		return $form;
	}

	/**
	 * Build Textarea input form to pass JSON data into
	 * 
	 * @return InputfieldForm
	 * 
	 */
	protected function ___buildInputDataForm() {
	
		/** @var InputfieldForm $form */
		$form = $this->modules->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';
		$form->attr('id', 'import_form');
	
		/** @var InputfieldTextarea $f */
		$f = $this->modules->get('InputfieldTextarea');
		$f->attr('name', 'import_data');
		$f->label = $this->_x('Import', 'button');
		$f->icon = 'paste';
		$f->description = $this->_('Paste in the data from an export.');
		$f->description .= "\n**Experimental/beta feature: database backup recommended for safety.**";
		$f->notes = $this->_('Copy the export data from another installation and then paste into the box above with CTRL-V or CMD-V.');
		$form->add($f);

		/** @var InputfieldSubmit $f */
		$f = $this->wire('modules')->get('InputfieldSubmit') ;
		$f->attr('name', 'submit_import');
		$f->attr('value', $this->_('Preview'));
		$form->add($f);
		
		return $form; 
	}

	/**
	 * Execute import
	 *
	 * @return InputfieldForm
	 * @throws WireException if given invalid import data
	 *
	 */
	public function ___buildImport() {
	
		/** @var InputfieldForm $form */
		$form = $this->modules->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';
		$form->attr('id', 'import_form');

		if($form->isSubmitted('submit_commit')) {
			$this->processImport();
			return $form;
		}
		
		$verify = (int) $this->input->get('verify');
		if($verify) {
			$json = $this->session->get('FieldImportData');
		} else {
			$json = $this->input->post('import_data');
		}

		if(!$json) return $this->buildInputDataForm();
		$data = is_array($json) ? $json : wireDecodeJSON($json);
		if(!$data) throw new WireException("Invalid import data");

		$numChangesTotal = 0;
		$numErrors = 0;
		$numExistingFields = 0;
		$numNewFields = 0;
		$notices = $this->wire('notices');
		
		if(!$verify) $notices->removeAll();

		// iterate through data for each field
		foreach($data as $name => $fieldData) {

			unset($fieldData['id']);
			$new = false;
			$name = $this->wire('sanitizer')->fieldName($name);
			$field = $this->wire('fields')->get($name);
			$numChangesField = 0;
			/** @var InputfieldFieldset $fieldset */
			$fieldset = $this->modules->get('InputfieldFieldset');
			$fieldset->label = $name;
			$form->add($fieldset);

			if(!$field) {
				$new = true;
				$field = new Field();
				$this->wire($field); 
				$field->name = $name;
				$fieldset->icon = 'sun-o';
				$fieldset->label .= " [" . $this->_('new') . "]";
			} else {
				$fieldset->icon = 'moon-o';
			}

			/** @var InputfieldMarkup $markup */
			$markup = $this->modules->get('InputfieldMarkup');
			$markup->addClass('InputfieldCheckboxes');
			$markup->value = "";
			$fieldset->add($markup);
			
			$savedFieldData = $field->getExportData();
			try {
				$changes = $field->setImportData($fieldData);
			} catch(\Exception $e) {
				$this->error($e->getMessage());
				$changes = array();
			}
			$field->setImportData($savedFieldData); // restore

			/** @var InputfieldCheckboxes $f */
			$f = $this->wire('modules')->get('InputfieldCheckboxes');
			$f->attr('name', "field_$name");
			$f->label = $this->_('Changes');
			$f->table = true;
			$f->thead = $this->_('Property') . '|';
			if(!$new) $f->thead .= $this->_('Old Value') . '|';
			$f->thead .= $this->_('New Value');

			foreach($changes as $property => $info) {
				
				if(!$new && $property == 'type') {
					$this->error(sprintf($this->_('We recommend changing the type of this field to "%s" manually, then coming back here to apply additional changes.'), $info['new']));
				}
				
				$oldValue = str_replace('|', ' ', $info['old']);
				$newValue = str_replace('|', ' ', $info['new']);
				$numChangesField++;
				$numChangesTotal++;
				
				if($info['error']) {
					$this->error("$name.$property: $info[error]");
					$attr = array();
				} else {
					$attr = array('checked' => 'checked');
				}
				
				if($new) $optionValue = "$property|$newValue";
					else $optionValue = "$property|$oldValue|$newValue";
				
				$f->addOption($property, $optionValue, $attr);
			}

			$errors = array();
			foreach($notices as $notice) {
				if(!$notice instanceof NoticeError) continue;
				$errors[] = $this->wire('sanitizer')->entities1($notice->text);
			}
			
			if(count($errors)) {
				$icon = "<i class='fa fa-exclamation-triangle'></i>";
				$markup->value .= "<ul class='ui-state-error-text'><li>$icon " . implode("</li><li>$icon ", $errors) . '</li></ul>';
				$fieldset->label .= ' (' . sprintf($this->_n('%d error', '%d errors', count($errors)), count($errors)) . ')';
				$numErrors++;
			}
			
			if(!$verify) $notices->removeAll();

			if($numChangesField) {
				$fieldset->description = sprintf($this->_n('Found %d property to apply.', 'Found %d properties to apply.', $numChangesField), $numChangesField);
				if($new) $numNewFields++;
					else $numExistingFields++;
			} else {
				$fieldset->description = $this->_('No changes pending.');
			}

			if(count($errors) || !$numChangesField) {
				$no = ' checked';
				$yes = '';
			} else {
				$yes = ' checked';
				$no = '';
			}

			$importLabel = $this->_('Modify this field?');
			if($new) $importLabel = $this->_('Create this field?');
			
			$markup->value .=
				"<p class='import_toggle'>$importLabel " .
				"<label><input$yes type='radio' name='import_field_$name' value='1' /> " . $this->_x('Yes', 'yes-import') . "</label>" .
				"<label><input$no type='radio' name='import_field_$name' value='0' /> " . $this->_x('No', 'no-import') . "</label>" .
				($no && $numChangesField ? "<span class='detail'>(" . $this->_('click yes to show changes') . ")</span>" : "") .
				"</p>";

			$f->renderReady();
			$markup->value .= $f->render();
			$data[$name] = $fieldData;
		}

		if($numChangesTotal) {
			
			if($verify) {
				$form->description = $this->_('Sometimes it may take two commits before all changes are applied. Please review any pending changes below and commit them as needed.');
			} else {
				$form->description = $this->_('Please review the changes below and commit them when ready. If there are any changes that you do not want applied, uncheck the boxes where appropriate.');
			}
		
			/** @var InputfieldSubmit $f */
			$f = $this->modules->get('InputfieldSubmit');
			$f->attr('name', 'submit_commit');
			$f->attr('value', $this->_('Commit Changes'));
			$f->showInHeader();
			$form->add($f);
			
		} else { 
			
			if($verify) {
				$form->description = $this->_('Your changes have been applied!');
			} else {
				$form->description = $this->_('No changes were found');
			}
			
			$f = $this->modules->get('InputfieldButton');
			$f->href = './';
			$f->value = $this->_x('Ok', 'button'); 
			$form->add($f);
		}

		$this->session->set('FieldImportData', $data);
		if($numErrors) $this->error(sprintf($this->_n('Errors were found in %d field', 'Errors were found in %d fields', $numErrors), $numErrors));
		if($numNewFields) $this->message(sprintf($this->_n('Found %d new field to add', 'Found %d new fields to add', $numNewFields), $numNewFields));
		if($numExistingFields) $this->message(sprintf($this->_n('Found %d existing field to update', 'Found %d existing fields to update', $numExistingFields), $numExistingFields));
		
		return $form;
	}
	
	/**
	 * Commit changed field data 
	 *
	 */
	protected function ___processImport() {

		$data = $this->session->get('FieldImportData');
		if(!$data) throw new WireException("Invalid import data");
		
		$numChangedFields = 0;
		$numAddedFields = 0;
		$skipFieldNames = array();

		// iterate through data for each field
		foreach($data as $name => $fieldData) {

			$name = $this->wire('sanitizer')->fieldName($name);
			
			if(!$this->input->post("import_field_$name")) {
				$skipFieldNames[] = $name;
				unset($data[$name]);
				continue;
			}
			
			$field = $this->wire('fields')->get($name);

			if(!$field) {
				$new = true;
				$field = new Field();
				$field->name = $name;
			} else {
				$new = false;
			}

			unset($fieldData['id']);
			foreach($fieldData as $property => $value) {
				if(!in_array($property, $this->input->post("field_$name"))) {
					unset($fieldData[$property]); 
				}
			}
			
			try {
				$changes = $field->setImportData($fieldData);
				foreach($changes as $key => $info) $this->message($this->_('Saved:') . " $name.$key => $info[new]");
				$field->save();
				if($new) {
					$numAddedFields++;
					$this->message($this->_('Added field') . ' - ' . $name); 
				} else {
					$numChangedFields++;
					$this->message($this->_('Modified field') . ' - ' . $name); 
				}
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
			
			$data[$name] = $fieldData;
		}
		
		$this->session->set('FieldImportSkipNames', $skipFieldNames);
		$this->session->set('FieldImportData', $data); 
		$numSkippedFields = count($skipFieldNames); 
		if($numAddedFields) $this->message(sprintf($this->_n('Added %d field', 'Added %d fields', $numAddedFields), $numAddedFields));
		if($numChangedFields) $this->message(sprintf($this->_n('Modified %d field', 'Modified %d fields', $numChangedFields), $numChangedFields));
		if($numSkippedFields) $this->message(sprintf($this->_n('Skipped %d field', 'Skipped %d fields', $numSkippedFields), $numSkippedFields));
		$this->session->redirect("./?verify=1");
	}


}
