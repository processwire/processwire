<?php namespace ProcessWire;

/**
 * @method string buildExport()
 * @method InputfieldForm buildInputDataForm()
 * @method string buildImport()
 * @method void processImport()
 * 
 */

class ProcessTemplateExportImport extends Wire {

	/** 
	 * @var Templates|Template[] $items 
	 * 
	 */
	protected $items; 
	
	public function wired() {
		parent::wired();
		$this->items = $this->wire()->templates;
	}
	
	protected function getItem($name) {
		return $this->items->get($name); 
	}
	
	protected function getNewItem() {
		$item = $this->wire(new Template());
		return $item;
	}

	/**
	 * Return export data for all given $exportTemplates
	 * 
	 * @param array $exportTemplates template names
	 * @return array
	 * 
	 */
	protected function getExportData(array $exportTemplates) {
		$data = array();
		foreach($this->items as $template) {
			/** @var Template $template */
			if(!in_array($template->name, $exportTemplates)) continue;
			$a = $template->getExportData();
			$data[$template->name] = $a;
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
		
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;
		
		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';

		$exportTemplates = $input->post('export_templates');

		if(empty($exportTemplates)) {

			/** @var InputfieldSelectMultiple $f */
			$f = $modules->get('InputfieldSelectMultiple');
			$f->attr('id+name', 'export_templates');
			$f->label = $this->_('Select the templates that you want to export');
			$f->icon = 'copy';

			$maxName = 0;
			$maxLabel = 0;
			$numTemplates = 0;

			foreach($this->items as $template) {
				/** @var Template $template */
				if(strlen($template->name) > $maxName) $maxName = strlen($template->name);
				$label = $template->getLabel();
				if(strlen($label) > $maxLabel) $maxLabel = strlen($label);
				$numTemplates++;
			}
		
			$templateName = $this->_('NAME') . ' ';
			$templateLabel = $this->_('LABEL') . ' ';
			$numFields = $this->_('FIELDS') . ' ';
			$modified = $this->_('MODIFIED');
			$label = $templateName . ' ' . str_repeat('.', $maxName - strlen($templateName) + 10) . ' ' .
				$templateLabel . str_repeat('.', $maxLabel - strlen($templateLabel) + 10) . ' ' .
				str_pad($numFields, 13, '.') . ' ' .
				$modified;
			$f->addOption(0, $label, array('disabled' => 'disabled'));
			
			foreach($this->items as $template) {
				//if(!is_object($template->fieldgroup)) $this->error("Template: $template has no fieldgroup"); 
				$templateName = $template->name . ' ';
				$templateLabel = $template->getLabel() . ' ';
				if($templateLabel == $templateName) $templateLabel = '';
				$numFields = count($template->fieldgroup) . ' ';
				$modified = $template->modified ? wireRelativeTimeStr($template->modified) : '';
				$label = $templateName . str_repeat('.', $maxName - strlen($templateName) + 10) . ' ' .
					$templateLabel . str_repeat('.', $maxLabel - strlen($templateLabel) + 10) . ' ' . 
					str_pad($numFields, 13, '.') . ' ' . 
					$modified; 
					
				$f->addOption($template->name, $label);
			}
			
			$f->notes = $this->_('Shift+Click to select multiple in sequence. Ctrl+Click (or Cmd+Click) to select multiple individually. Ctrl+A (or Cmd+A) to select all.');
			$f->attr('size', $numTemplates+1);
			$form->add($f);

			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
			$f->attr('name', 'submit_export');
			$f->attr('value', $this->_x('Export', 'button'));
			$form->add($f);

		} else {

			/** @var InputfieldTextarea $f */
			$f = $modules->get('InputfieldTextarea');
			$f->attr('id+name', 'export_data');
			$f->label = $this->_('Export Data');
			$f->description = $this->_('Copy and paste this data into the "Import" box of another installation.');
			$f->notes = $this->_('Click anywhere in the box to select all export data. Once selected, copy the data with CTRL-C or CMD-C.');
			$f->attr('value', wireEncodeJSON($this->getExportData($exportTemplates), true, true));
			$form->add($f);

			/** @var InputfieldButton $f */
			$f = $modules->get('InputfieldButton');
			$f->href = './';
			$f->value = $this->_x('Ok', 'button'); 
			$form->add($f);
		}

		return $form;
	}

	/**
	 * Build Textarea input form to past JSON data into
	 * 
	 * @return InputfieldForm
	 * 
	 */
	protected function ___buildInputDataForm() {
		
		$modules = $this->wire()->modules;
	
		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';
		$form->attr('id', 'import_form');
	
		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'import_data');
		$f->label = $this->_x('Import', 'input');
		$f->icon = 'paste';
		$f->description = $this->_('Paste in the data from an export.');
		$f->description .= "\n**Experimental/beta feature: database backup recommended for safety.**";
		$f->notes = $this->_('Copy the export data from another installation and then paste into the box above with CTRL-V or CMD-V.');
		$form->add($f);

		/** @var InputfieldSubmit $f */
		$f = $modules->get('InputfieldSubmit') ;
		$f->attr('name', 'submit_import');
		$f->attr('value', $this->_('Preview'));
		$form->add($f);
		
		return $form; 
	}

	/**
	 * Execute import
	 *
	 * @return string
	 * @throws WireException if given invalid import data
	 *
	 */
	public function ___buildImport() {
	
		$modules = $this->wire()->modules;
		$session = $this->wire()->session;
		$notices = $this->wire()->notices;
		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;
		
		if($input->post('submit_commit')) {
			$this->processImport();
			return '';
		}
		
		$verify = (int) $input->get('verify');

		if($verify) {
			$json = $session->get($this, 'importData');
		} else {
			$json = $input->post('import_data');
		}

		if(!$json) return $this->buildInputDataForm();
		$data = is_array($json) ? $json : wireDecodeJSON($json);
		if(!$data) throw new WireException("Invalid import data");

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->action = './';
		$form->method = 'post';
		$form->attr('id', 'import_form');

		/*
		$importDataHash1 = md5(print_r($data, true)); 
		$importDataHash2 = $this->input->post('importDataHash'); 
		if($importDataHash2 && $importDataHash1 != $importDataHash2) {
			throw new WireException("Data hashes did not match, aborting"); 
		}
		
		$f = $this->modules->get('InputfieldHidden'); 
		$f->attr('name', 'importDataHash'); 
		$f->attr('value', $importDataHash1); 
		$form->add($f); 
		*/

		$numChangesTotal = 0;
		$numErrors = 0;
		$numExistingTemplates = 0;
		$numNewTemplates = 0;
		
		if(!$verify) $notices->removeAll();

		// iterate through data for each template
		foreach($data as $name => $templateData) {

			$postName = str_replace('-', '__', $name); 
			unset($templateData['id']);
			$new = false;
			$name = $sanitizer->name($name);
			$template = $this->items->get($name);
			$numChangesTemplate = 0;
			/** @var InputfieldFieldset $fieldset */
			$fieldset = $modules->get('InputfieldFieldset');
			$fieldset->label = $name;
			$form->add($fieldset);

			if(!$template) {
				$new = true;
				/** @var Template $template */
				$template = $this->wire(new Template());
				$template->name = $name;
				$fieldset->icon = 'sun-o';
				$fieldset->label .= " [" . $this->_('new') . "]";
			} else {
				$fieldset->icon = 'moon-o';
			}

			/** @var InputfieldMarkup $markup */
			$markup = $modules->get('InputfieldMarkup');
			$markup->addClass('InputfieldCheckboxes');
			$markup->value = "";
			$fieldset->add($markup);
			
			$savedTemplateData = $template->getExportData();
			try {
				$changes = $template->setImportData($templateData);
				$template->setImportData($savedTemplateData); // restore
			} catch(\Exception $e) {
				$changes = array();
				$this->error($e->getMessage());
			}

			/** @var InputfieldCheckboxes $f */
			$f = $modules->get('InputfieldCheckboxes');
			$f->attr('name', "item_$postName");
			$f->label = $this->_('Changes');
			$f->table = true;
			$f->thead = $this->_('Property') . '|';
			if(!$new) $f->thead .= $this->_('Old Value') . '|';
			$f->thead .= $this->_('New Value');

			foreach($changes as $property => $info) {
				if($property === '_lazy' || $property === '_exportMode') continue;
			
				$oldValue = str_replace('|', ' ', $info['old']);
				$newValue = str_replace('|', ' ', $info['new']);
				$numChangesTemplate++;
				$numChangesTotal++;
				
				if($info['error']) {
					$errors = is_array($info['error']) ? $info['error'] : array($info['error']); 
					foreach($errors as $error) $this->error("$name.$property: $error");
					$attr = array();
				} else {
					$attr = array('checked' => 'checked');
				}
				
				if($new) $optionValue = " $property|$newValue";
					else $optionValue = " $property|$oldValue|$newValue";
				
				$f->addOption($property, $optionValue, $attr);
			}

			$errors = array();
			foreach($notices as $notice) {
				if(!$notice instanceof NoticeError) continue;
				$errors[] = $sanitizer->entities1($notice->text);
				$notices->remove($notice); 
			}
			
			if(count($errors)) {
				$icon = wireIconMarkup('exclamation-triangle'); 
				$markup->value .= "<ul class='ui-state-error-text'><li>$icon " . implode("</li><li>$icon ", $errors) . '</li></ul>';
				$fieldset->label .= ' (' . sprintf($this->_n('%d notice', '%d notices', count($errors)), count($errors)) . ')';
				$numErrors++;
			}
			
			//if(!$verify) $notices->removeAll();

			if($numChangesTemplate) {
				$fieldset->description = sprintf(
					$this->_n('Found %d property to apply.', 'Found %d properties to apply.', $numChangesTemplate), 
					$numChangesTemplate
				);
				if($new) {
					$numNewTemplates++;
				} else {
					$numExistingTemplates++;
				}
			} else {
				$fieldset->description = $this->_('No changes pending.');
			}

			if(count($errors) || !$numChangesTemplate) {
				$no = ' checked';
				$yes = '';
			} else {
				$yes = ' checked';
				$no = '';
			}

			$importLabel = $this->_('Modify this template?');
			if($new) $importLabel = $this->_('Create this template?');
		
			$markup->value .=
				"<p class='import_toggle'>$importLabel " .
				"<label><input$yes type='radio' name='import_item_$postName' value='1' /> " . $this->_x('Yes', 'yes-import') . "</label>" .
				"<label><input$no type='radio' name='import_item_$postName' value='0' /> " . $this->_x('No', 'no-import') . "</label>" .
				($no && $numChangesTemplate ? "<span class='detail'>(" . $this->_('click yes to show changes') . ")</span>" : "") .
				"</p>";

			$markup->value .= $f->render();
			// $data[$name] = $templateData;
			$this->errors('clear all'); 
		}

		if($numChangesTotal) {
			
			if($verify) {
				$form->description = $this->_('Sometimes it may take two commits before all changes are applied. Please review any pending changes below and commit them as needed.');
			} else {
				$form->description = $this->_('Please review the changes below and commit them when ready. If there are any changes that you do not want applied, uncheck the boxes where appropriate.');
			}
		
			/** @var InputfieldSubmit $f */
			$f = $modules->get('InputfieldSubmit');
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
		
			/** @var InputfieldButton $f */
			$f = $modules->get('InputfieldButton');
			$f->href = './';
			$f->value = $this->_x('Ok', 'button'); 
			$form->add($f);
		}

		$session->set($this, 'importData', $data);
		if($numErrors) {
			$this->error(sprintf(
				$this->_n('Notices in %d template', 'Notices in %d templates', $numErrors), 
				$numErrors
			));
		}
		if($numNewTemplates) {
			$this->message(sprintf(
				$this->_n('Found %d new template to add', 'Found %d new templates to add', $numNewTemplates), 
				$numNewTemplates
			));
		}
		if($numExistingTemplates) {
			$this->message(sprintf(
				$this->_n('Found %d existing template to update', 'Found %d existing templates to update', $numExistingTemplates), 
				$numExistingTemplates
			));
		}
		
		return $form;
	}
	
	/**
	 * Commit changed field data 
	 *
	 */
	protected function ___processImport() {
		
		$sanitizer = $this->wire()->sanitizer;
		$session = $this->wire()->session;
		$input = $this->wire()->input;

		$data = $session->get($this, 'importData');
		if(!$data) throw new WireException("Invalid import data");
		
		$numChangedItems = 0;
		$numAddedItems = 0;
		$skipNames = array();

		// iterate through data for each field
		foreach($data as $name => $itemData) {

			$name = $sanitizer->name($name);
			$postName = str_replace('-', '__', $name); 
			
			if(!$input->post("import_item_$postName")) {
				$skipNames[] = $name;
				unset($data[$name]);
				continue;
			}
			
			$item = $this->getItem($name);

			if(!$item) {
				$new = true;
				$item = $this->getNewItem();
				$item->name = $name;
			} else {
				$new = false;
			}

			unset($itemData['id']);
			foreach($itemData as $property => $value) {
				if(!in_array($property, $input->post("item_$postName"))) {
					unset($itemData[$property]); 
				}
			}
			
			try {
				$changes = $item->setImportData($itemData);
				foreach($changes as $key => $info) {
					$this->message($this->_('Saved:') . " $name.$key => $info[new]");
				}
				$this->saveItem($item, $changes);
				if($new) {
					$numAddedItems++;
					$this->message($this->_('Added:') . " $name"); 
				} else {
					$numChangedItems++;
					$this->message($this->_('Modified:') . " $name"); 
				}
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
			
			$data[$name] = $itemData;
		}
		
		$session->set($this, 'skipNames', $skipNames); 
		$session->set($this, 'importData', $data); 
		
		$numSkippedItems = count($skipNames); 
		if($numAddedItems) $this->message(sprintf($this->_n('Added %d item', 'Added %d items', $numAddedItems), $numAddedItems));
		if($numChangedItems) $this->message(sprintf($this->_n('Modified %d item', 'Modified %d items', $numChangedItems), $numChangedItems));
		if($numSkippedItems) $this->message(sprintf($this->_n('Skipped %d item', 'Skipped %d items', $numSkippedItems), $numSkippedItems));
		$session->redirect("./?verify=1");
	}

	/**
	 * @param Template $item
	 * @param array $changes
	 * 
	 */
	public function saveItem($item, array $changes) {
		/** @var Fieldgroup $fieldgroup */
		$fieldgroup = $item->fieldgroup;
		if(!$fieldgroup) {
			$fieldgroup = new Fieldgroup();
			$fieldgroup->name = $item->name;
		}
		$fieldgroup->save();
		$fieldgroup->saveContext();
		$item->setFieldgroup($fieldgroup);
		$item->save();
	}

}
