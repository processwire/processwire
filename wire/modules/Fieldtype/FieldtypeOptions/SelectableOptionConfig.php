<?php namespace ProcessWire;

/**
 * Inputfields and processing for Select Options Fieldtype
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class SelectableOptionConfig extends Wire {

	/**
	 * @var Field
	 * 
	 */
	protected $field;

	/**
	 * @var Fieldtype
	 * 
	 */
	protected $fieldtype;

	/**
	 * @var InputfieldWrapper
	 * 
	 */
	protected $inputfields;

	/**
	 * @var SelectableOptionManager
	 * 
	 */
	protected $manager;

	/**
	 * Construct
	 * 
	 * @param Field $field
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function __construct(Field $field, InputfieldWrapper $inputfields) {
		$this->field = $field;
		$this->fieldtype = $field->type; 
		$this->inputfields = $inputfields; 
		$this->manager = $this->fieldtype->manager; 
	}
	
	/**
	 * Custom processing for the options string in getConfigInputfields
	 * 
	 * Detects and confirms option deletions. 
	 *
	 * @param Inputfield $inputfield For the _options inputfield
	 * @throws WireException
	 *
	 */
	protected function process(Inputfield $inputfield) {

		$value = $this->wire('input')->post('_options');
		if($this->wire('process') != 'ProcessField' || !$this->wire('user')->isSuperuser()) return;
		$ns = "$this$this->field"; // namespace for session

		if(!is_null($value)) {
			// _options has been posted

			if($this->manager->useLanguages() && $inputfield->getSetting('useLanguages')) {
				// multi-language

				$valuesPerLanguage = array();
				$changed = false;

				foreach($this->wire('languages') as $language) {
					$key = $language->isDefault() ? "_options" : "_options__$language";
					$valuesPerLanguage[$language->id] = $this->wire('input')->post($key);
					$key = $language->isDefault() ? "value" : "value$language";
					if($inputfield->$key != $valuesPerLanguage[$language->id]) $changed = true;
				}

				if($changed) {
					$this->manager->setOptionsStringLanguages($this->field, $valuesPerLanguage, false);
				}

			} else {
				// non multi-language

				if($value != $inputfield->attr('value')) {
					$this->manager->setOptionsString($this->field, $value, false);
				}
			}

			$removedOptionIDs = $this->manager->getRemovedOptionIDs(); // identified for removal
			if(count($removedOptionIDs)) {
				// stuff in session for next request
				$this->wire('session')->set($ns, 'removedOptionIDs', $removedOptionIDs);
			}

			$deleteOptionIDs = $this->wire('input')->post('_delete_options');
			$deleteConfirm = (int) $this->wire('input')->post('_delete_confirm'); 
			if($deleteOptionIDs && $deleteConfirm) {
				// confirmed deleted
				if(!ctype_digit(str_replace(',', '', $deleteOptionIDs))) throw new WireException("Invalid deleteOptionIDs");
				$deleteOptionIDs = explode(',', $deleteOptionIDs);
				foreach($deleteOptionIDs as $key => $value) $deleteOptionIDs[$key] = (int) $value;
				$this->manager->deleteOptionsByID($this->field, $deleteOptionIDs);
			}

		} else {
			// options not posted, check if there are any pending session activities

			$removedOptionIDs = $this->wire('session')->get($ns, 'removedOptionIDs');
			if(wireCount($removedOptionIDs)) {
				
				$f = $this->wire('modules')->get('InputfieldHidden');
				$f->attr('name', '_delete_options'); 
				$f->attr('value', implode(',', $removedOptionIDs)); 
				$this->inputfields->prepend($f); 
				
				// setup for confirmation
				$f = $this->wire('modules')->get('InputfieldCheckbox');
				$f->attr('name', '_delete_confirm');
				$f->label = $this->_('Please confirm that you want to delete options');
				$f->label2 = $this->_n('Delete this option', 'Delete these options', count($removedOptionIDs));
				$this->warning($f->label);
				$removeOptions = $this->manager->getOptionsByID($this->field, $removedOptionIDs);
				$delimiter = $this->_('DELETE:') . ' ';
				$f->description .= $delimiter . $removeOptions->implode("\n$delimiter", 'title');
				// collapse other inputfields since we prefer them to focus on this one only for now
				foreach($this->inputfields as $i) $i->collapsed = Inputfield::collapsedYes;
				// add our confirmation field
				$this->inputfields->prepend($f);
				// was stuffed in session from previous request, unset it now since this is a one time thing
				$this->wire('session')->remove($ns, 'removedOptionIDs');
			}
		}
	}

	/**
	 * Provides the FieldtypeOptions::getConfigInputfields
	 * 
	 * @return InputfieldWrapper
	 * @throws WireException
	 * 
	 */
	public function getConfigInputfields() {

		$inputfields = $this->inputfields;
		$field = $this->field; 
		$options = $this->manager->getOptions($field);
		$modules = $this->wire('modules');

		$labelSingle = $this->_('Single value');
		$labelMulti = $this->_('Multiple values');

		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'inputfieldClass');
		$f->label = $this->_('What should be used for input?');
		$f->description = $this->_('Depending on what input type you choose, the user will be able to select either a single option or multiple options. Some input types (like AsmSelect) also support user-sortable selections. Some input types also provide more settings on the *Input* tab (visible after you save).'); 

		foreach($modules as $module) {
			if(strpos($module->className(), 'Inputfield') !== 0) continue;
			if($module instanceof ModulePlaceholder) {
				$module = $modules->getModule($module->className(), array('noInit' => true));
			}
			if($module instanceof InputfieldSelect) {
				$name = str_replace('Inputfield', '', $module->className());
				if($module instanceof InputfieldSelectMultiple) {
					$name .= " ($labelMulti)";
				} else {
					$name .= " ($labelSingle)";
				}
				$f->addOption($module->className(), $name);
			}
		}
		$value = $field->get('inputfieldClass');
		if(!$value) $value = 'InputfieldSelect';
		$f->attr('value', $value);
		$inputfields->add($f);

		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', '_options');
		$f->label = $this->_('What are the selectable options?');
		$f->description = sprintf($this->_('Enter one selectable option per line. After you save, an ID number will be assigned to each of your options, which you will see as `123=title`, for example. Please see our [instructions for using this field](%s).'), 'https://processwire.com/api/modules/select-options-fieldtype/');
		if($this->manager->useLanguages()) $f->notes = $this->_('Multi-language note: define options in the default language and save before translating them into other languages.'); 
		$f->attr('rows', 10);
		if($this->manager->useLanguages() && count($options)) {
			$f->useLanguages = true;
			foreach($this->wire('languages') as $language) {
				$name = $language->isDefault() ? "value" : "value$language->id";
				$f->set($name, $this->manager->getOptionsString($options, $language));
			}
		} else {
			$f->attr('value', $this->manager->getOptionsString($options));
		}

		$inputfields->add($f);
		$this->process($f); 

		if($options->count() && $field->inputfieldClass && $f = $modules->get($field->inputfieldClass)) {
			$f->attr('name', 'initValue'); 
			$f->label = $this->_('What options do you want pre-selected? (if any)'); 
			$f->collapsed = Inputfield::collapsedBlank;
			$f->description = sprintf($this->_('This field also serves as a preview of your selected input type (%s) and options.'), $field->inputfieldClass); 
			foreach($options as $option) {
				$f->addOption($option->id, $option->title); 
			}
			$f->attr('value', $field->initValue); 
			if(!$this->field->required && !$this->field->requiredIf) {
				$f->notes = $this->_('Please note: your selections here do not become active unless a value is *always* required for this field. See the "required" option on the Input tab of your field settings.');
			} else {
				$f->notes = $this->_('This feature is active since a value is always required.'); 
			}
			$inputfields->add($f); 
			$inputfields->add($this->getInstructions());
		}

		// $this->manager->updateLanguages();

		return $inputfields;
	}
	
	protected function getInstructions() {
		$field = $this->field;
		$f = $this->wire('modules')->get('InputfieldMarkup');
		$f->collapsed = Inputfield::collapsedYes;
		$f->label = $this->_('API usage example'); 
		$f->icon = 'code';
		$f->value = 
			"<pre><code>" .
			"// " . $this->_('Output a single selection:') . 
			"\necho '&lt;h2&gt;' . \$page-&gt;{$field->name}-&gt;title . '&lt;/h2&gt;';" . 
			"\n\n// " . $this->_('Output multiple selection:') . 
			"\nforeach(\$page-&gt;$field->name as \$item) {" .  
			"\n  echo '&lt;li&gt;' . \$item-&gt;title . '&lt;/li&gt;';" . 
			"\n}" . 
			"</code></pre>" . 
			"<p class='detail'>" . 
			$this->_('If you want to output values rather than titles, then replace “title” with “value” in the examples above.') . 
			"</p>";
		return $f;
	}
}

