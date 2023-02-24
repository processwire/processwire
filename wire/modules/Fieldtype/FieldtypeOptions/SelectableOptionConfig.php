<?php namespace ProcessWire;

/**
 * Inputfields and processing for Select Options Fieldtype
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
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
		parent::__construct();
		$this->field = $field;
		$fieldtype = $field->type; /** @var FieldtypeOptions $fieldtype */
		$this->fieldtype = $fieldtype; 
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
		
		$modules = $this->wire()->modules;
		$input = $this->wire()->input;
		$user = $this->wire()->user;
		$process = $this->wire()->process;
		$languages = $this->wire()->languages;
		$session = $this->wire()->session;

		$value = $input->post('_options');
		if($process != 'ProcessField' || (!$user->isSuperuser() && !$user->hasPermission('field-admin'))) return;
		$ns = "$this$this->field"; // namespace for session

		if(!is_null($value)) {
			// _options has been posted

			if($this->manager->useLanguages() && $inputfield->getSetting('useLanguages') && $languages) {
				// multi-language

				$valuesPerLanguage = array();
				$changed = false;

				foreach($languages as $language) {
					$key = $language->isDefault() ? "_options" : "_options__$language";
					$valuesPerLanguage[$language->id] = $input->post($key);
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
				$session->set($ns, 'removedOptionIDs', $removedOptionIDs);
			}

			$deleteOptionIDs = $input->post('_delete_options');
			$deleteConfirm = (int) $input->post('_delete_confirm'); 
			if($deleteOptionIDs && $deleteConfirm) {
				// confirmed deleted
				if(!ctype_digit(str_replace(',', '', $deleteOptionIDs))) throw new WireException("Invalid deleteOptionIDs");
				$deleteOptionIDs = explode(',', $deleteOptionIDs);
				foreach($deleteOptionIDs as $key => $value) $deleteOptionIDs[$key] = (int) $value;
				$this->manager->deleteOptionsByID($this->field, $deleteOptionIDs);
			}

		} else {
			// options not posted, check if there are any pending session activities

			$removedOptionIDs = $session->get($ns, 'removedOptionIDs');
			if(wireCount($removedOptionIDs)) {
				/** @var InputfieldHidden $f */
				$f = $modules->get('InputfieldHidden');
				$f->attr('name', '_delete_options'); 
				$f->attr('value', implode(',', $removedOptionIDs)); 
				$this->inputfields->prepend($f); 
				
				// setup for confirmation
				/** @var InputfieldCheckbox $f */
				$f = $modules->get('InputfieldCheckbox');
				$f->attr('name', '_delete_confirm');
				$f->label = $this->_('Please confirm that you want to delete options');
				$f->label2 = $this->_n('Delete this option', 'Delete these options', count($removedOptionIDs));
				$this->warning($f->label);
				$removeOptions = $this->manager->getOptionsByID($this->field, $removedOptionIDs);
				$delimiter = $this->_('DELETE:') . ' ';
				$f->description .= $delimiter . $removeOptions->implode("\n$delimiter", 'title');
				// collapse other inputfields since we prefer them to focus on this one only for now
				foreach($this->inputfields as $i) {
					/** @var Inputfield $i */
					$i->collapsed = Inputfield::collapsedYes;
				}
				// add our confirmation field
				$this->inputfields->prepend($f);
				// was stuffed in session from previous request, unset it now since this is a one time thing
				$session->remove($ns, 'removedOptionIDs');
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

		/** @var InputfieldSelect $f */
		$f = $modules->get('InputfieldSelect');
		$f->attr('name', 'inputfieldClass');
		$f->label = $this->_('What should be used for input?');
		$f->description = $this->_('Depending on what input type you choose, the user will be able to select either a single option or multiple options. Some input types (like AsmSelect) also support user-sortable selections. Some input types also provide more settings on the *Input* tab (visible after you save).'); 
		$f->addOptions($this->fieldtype->getInputfieldClassOptions());
		$value = $field->get('inputfieldClass');
		if(!$value) $value = 'InputfieldSelect';
		$f->attr('value', $value);
		$inputfields->add($f);

		/** @var InputfieldTextarea $f */
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

		$inputfieldClass = $field->get('inputfieldClass');
		if($options->count() && $inputfieldClass && $f = $modules->get($inputfieldClass)) {
			/** @var InputfieldSelect $f */
			$f->attr('name', 'initValue'); 
			$f->label = $this->_('What options do you want pre-selected? (if any)'); 
			$f->collapsed = Inputfield::collapsedBlank;
			$f->description = sprintf($this->_('This field also serves as a preview of your selected input type (%s) and options.'), $inputfieldClass); 
			if(!$f instanceof InputfieldHasArrayValue) $f->addOption('', $this->_('None'));
			foreach($options as $option) {
				$f->addOption($option->id, $option->title); 
			}
			$initValue = $field->get('initValue');
			if($f instanceof InputfieldHasArrayValue && !is_array($initValue) && !empty($initValue)) $initValue = explode(' ', $initValue);
			$f->attr('value', $initValue);
			if(!$field->required && !$field->requiredIf) {
				$f->notes = $this->_('Please note: Your pre-selection is not active, as this field is not a required field. Activate the option “required” in the input tab of the field.');
			} else {
				$f->notes = $this->_('The pre-selection is active because this field is a required field.');
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

