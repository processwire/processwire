<?php namespace ProcessWire;

/**
 * Serves as a multi-language value placeholder for field values that contain a value in more than one language. 
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class LanguagesPageFieldValue extends Wire implements LanguagesValueInterface, \IteratorAggregate {

	/**
	 * Inherit default language value when blank
	 *
	 */
	const langBlankInheritDefault = 0; 

	/**
	 * Don't inherit any value when blank
	 *
	 */
	const langBlankInheritNone = 1; 

	/**
	 * Values per language indexed by language ID
	 *
	 */
	protected $data = array();

	/**
	 * Cached ID of default language page
	 *
	 */
	protected $defaultLanguagePageID = 0;

	/**
	 * @var LanguageSupport|null
	 * 
	 */
	protected $languageSupport = null;

	/**
	 * Reference to Field that this value is for
	 * 
	 * @var Field|null
	 *
	 */
	protected $field = null;

	/**
	 * Reference to Page that this value is for
	 * 
	 * @var Page|null
	 * 
	 */
	protected $page = null; 

	/**
	 * Construct the multi language value
	 *
	 * @param Page|null $page
	 * @param Field|null $field
 	 * @param array|string $values
	 *
	 */
	public function __construct($page = null, $field = null, $values = null) { // #98
		parent::__construct();
	
		if($page) $this->setPage($page);
		if($field) $this->setField($field);
		
		if($page) {
			$page->wire($this);
		} else if($field) {
			$field->wire($this);
		}

		if(!is_array($values)) {
			$values = array('data' => $values); 
		}
		
		$this->importArray($values); 
	}

	/**
	 * Wired to API
	 * 
	 */
	public function wired() {
		parent::wired();
		$this->languageSupport();
	}

	/**
	 * Import array of language values 
	 * 
	 * Indexes may be:
	 * - “data123” where 123 is language ID or “data” for default language
	 * - "en” language name (may be any language name) 
	 * - “123” language ID
	 * 
	 * One index style must be used, you may not combine multiple. 
	 * 
	 * #pw-internal
	 * 
	 * @param array $values
	 * 
	 */
	public function importArray(array $values) {
		
		reset($values);
		$testKey = key($values);
		
		if($testKey === null) return;
		
		if(strpos($testKey, 'data') !== 0) {
			// array does not use "data123" indexes, so work with language ID or language name indexes
			// and convert to "data123" indexes
			$languages = $this->wire()->languages;
			$_values = array();
			foreach($values as $key => $value) {
				if(ctype_digit("$key")) $key = (int) $key;
				$language = $languages->get($key);
				if($language && $language->id) {
					$dataKey = $language->isDefault() ? "data" : "data$language->id";
					$_values[$dataKey] = $value;
				}
			}
			if(count($_values)) $values = $_values;
		}
		
		if(array_key_exists('data', $values)) {
			if(is_null($values['data'])) $values['data'] = '';
			$this->data[$this->defaultLanguagePageID()] = $values['data'];
		}

		$languageSupport = $this->languageSupport();
		if($languageSupport) {
			foreach($languageSupport->otherLanguagePageIDs as $id) {
				$key = 'data' . $id;
				$value = empty($values[$key]) ? '' : $values[$key];
				$this->data[$id] = $value;
			}
		}
	}

	/**
	 * Sets the value for a given language
	 *
	 * @param int|Language|string $languageID Language object, id, or name
	 * @param mixed $value
	 * @return $this
	 *
	 */
	public function setLanguageValue($languageID, $value) {
		if($languageID instanceof Language) $languageID = $languageID->id;
		if(is_string($languageID) && !ctype_digit("$languageID")) {
			$languageID = $this->wire()->languages->get($languageID)->id;
		}
		$existingValue = isset($this->data[$languageID]) ? $this->data[$languageID] : '';
		if($value instanceof LanguagesPageFieldValue) {
			// to avoid potential recursion 
			$value = $value->getLanguageValue($languageID); 
		}
		if($value !== $existingValue) {
			$this->trackChange('data', $existingValue, $value); 
			$this->trackChange('data' . $languageID, $existingValue, $value); 
		}
		$this->data[(int)$languageID] = $value;
		return $this;
	}

	/**
	 * Set multiple language values at once
	 * 
	 * ~~~~~
	 * $page->title->setLanguageValues([
	 *  'default' => 'Hello world',
	 *  'es' => 'Hola Mundo',
	 *  'fr' => 'Hei maailma',
	 * ]);
	 * ~~~~~
	 * 
	 * @param array $values Associative array of values where keys are language names or IDs.
	 * @param bool $reset Reset any languages not specified to blank? (default=false)
	 * @return self
	 * @since 3.0.236
	 * 
	 */
	public function setLanguageValues(array $values, $reset = false) {
		foreach($this->wire()->languages as $language) {
			if(isset($values[$language->id])) {
				$this->setLanguageValue($language->id, $values[$language->id]);
			} else if(isset($values[$language->name])) {
				$this->setLanguageValue($language->id, $values[$language->name]);
			} else if($reset) {
				$this->setLanguageValue($language->id, '');
			}
		}
		return $this;
	}

	/**
	 * Grab language values from Inputfield and populate to this object
	 *
	 * @param Inputfield $inputfield
	 *
	 */
	public function setFromInputfield(Inputfield $inputfield) {

		foreach($this->wire()->languages as $language) {
			/** @var Language $language */
			if($language->isDefault()) {
				$key = 'value';
			} else {
				$key = 'value' . $language->id; 
			}
			$this->setLanguageValue($language->id, $inputfield->$key); 
		}
	}

	/**
	 * Populate language values from this object to given Inputfield
	 *
	 * @param Inputfield $inputfield
	 * @since 3.0.170
	 *
	 */
	public function setToInputfield(Inputfield $inputfield) {
		foreach($this->wire()->languages as $language) {
			/** @var Language $language */
			$key = $language->isDefault() ? "value" : "value$language->id";
			$inputfield->set($key, $this->getLanguageValue($language->id));
		}
	}

	/**
	 * Given a language, returns the value in that language
	 *
	 * @param Language|int|string Language object, id, or name
	 * @return string|mixed
	 *
	 */
	public function getLanguageValue($languageID) {
		if($languageID instanceof Language) $languageID = $languageID->id; 
		if(is_string($languageID) && !ctype_digit("$languageID")) $languageID = $this->wire()->languages->get($languageID)->id;
		$languageID = (int) $languageID; 
		return isset($this->data[$languageID]) ? $this->data[$languageID] : '';
	}

	/**
	 * Returns the value in the default language
	 * 
	 * @return string
	 *
	 */
	public function getDefaultValue() {
		$id = $this->defaultLanguagePageID();
		return isset($this->data[$id]) ? $this->data[$id] : '';
	}

	/**
	 * Get non-empty value in this order: current lang, default lang, other lang, failValue
	 * 
	 * @param string $failValue Value to use if we cannot find a non-empty value
	 * @return string 
	 * @since 3.0.147
	 * 
	 */
	public function getNonEmptyValue($failValue = '') {
		
		$value = (string) $this;
		if(strlen($value)) return $value; 
		
		$value = (string) $this->getDefaultValue();
		if(strlen($value)) return $value;
		
		foreach($this->wire()->languages as $language) {
			$value = $this->getLanguageValue($language->id);
			if(strlen($value)) break;
		}
		
		if(!strlen($value)) $value = $failValue;
		
		return $value;
	}

	/**
	 * The string value is the value in the current user's language
	 * 
	 * @return string
	 *
	 */
	public function __toString() {
		if($this->wire()->hooks->isHooked('LanguagesPageFieldValue::getStringValue()')) {
			return $this->__call('getStringValue', array());
		} else {
			return $this->___getStringValue();
		}	
	}

	/**
	 * Get string value (for hooks)
	 * 
	 * #pw-hooker
	 * 
	 * @return string
	 * 
	 */
	protected function ___getStringValue() {
		
		$template = $this->page->template;
		$language = $this->wire()->user->language; 	
		$defaultValue = (string) $this->data[$this->defaultLanguagePageID()];
		
		if(!$language || !$language->id || $language->isDefault()) return $defaultValue;
		if($template && $template->noLang) return $defaultValue;

		$languageValue = (string) (empty($this->data[$language->id]) ? '' : $this->data[$language->id]); 
		
		if(!strlen($languageValue)) {
			// value is blank
			if($this->field) { 
				if($this->field->get('langBlankInherit') == self::langBlankInheritDefault) {
					// inherit value from default language
					$languageValue = $defaultValue; 
				}
			}
		}
		
		return $languageValue; 
	}

	/**
	 * Set field that value is for
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field; 
	}

	/**
	 * Set page that value is for
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Get page that value is for
	 * 
	 * @return Page|null
	 * 
	 */
	public function getPage() {
		return $this->page; 
	}

	/**
	 * Get field that value is for
	 * 
	 * @return Field|null
	 * 
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		foreach($this->wire()->languages as $language) {
			$info[$language->name] = isset($this->data[$language->id]) ? $this->data[$language->id] : '';
		}
		return $info;	
	}

	/**
	 * Get array of language values stored in here
	 * 
	 * @return array
	 * @since 3.0.188
	 * 
	 */
	public function getArray() {
		return $this->data;
	}

	/**
	 * Get hash of all language values stored in here
	 * 
	 * @param bool $verbose Specify true for the hash to also include page and field
	 * @return string
	 * @since 3.0.188
	 * 
	 */
	public function getHash($verbose = false) {
		$str = '';
		if($verbose) $str .= "[$this->page,$this->field]\n";
		foreach($this->data as $k => $v) {
			if(!is_string($v)) continue;
			$str .= "$k:$v\n";
		}
		return sha1($str);
	}

	/**
	 * Allows iteration of the languages values
	 *
	 * Fulfills \IteratorAggregate interface.
	 *
	 * @return \ArrayObject
	 *
	 */
	#[\ReturnTypeWillChange] 
	public function getIterator() {
		return new \ArrayObject($this->data);
	}

	/**
	 * @return null|LanguageSupport
	 * @throws WireException
	 * @throws WirePermissionException
	 * 
	 */
	protected function languageSupport() {
		if($this->languageSupport) return $this->languageSupport;
		$this->languageSupport = $this->wire()->modules->get('LanguageSupport');
		$this->defaultLanguagePageID = $this->languageSupport->defaultLanguagePageID;
		return $this->languageSupport;
	}

	/**
	 * @return int
	 * 
	 */
	protected function defaultLanguagePageID() {
		if(!$this->defaultLanguagePageID) $this->languageSupport();
		return $this->defaultLanguagePageID; 
	}
}
