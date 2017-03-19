<?php namespace ProcessWire;

/**
 * Serves as a multi-language value placeholder for field values that contain a value in more than one language. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
	 * Reference to Field that this value is for
	 *
	 */
	protected $field;

	/**
	 * Reference to Page that this value is for
	 * 
	 * @var Page
	 * 
	 */
	protected $page; 

	/**
	 * Construct the multi language value
	 *
	 * @param Page $page
	 * @param Field $field
 	 * @param array|string $values
	 *
	 */
	public function __construct(Page $page, Field $field, $values = null) { // #98
	
		$page->wire($this);
		$this->setPage($page);
		$this->setField($field);

		$languageSupport = $this->wire('modules')->get('LanguageSupport');
		$this->defaultLanguagePageID = $languageSupport->defaultLanguagePageID; 

		if(!is_array($values)) $values = array('data' => $values); 

		if(array_key_exists('data', $values)) {
			$this->data[$this->defaultLanguagePageID] = $values['data']; 
		}

		foreach($languageSupport->otherLanguagePageIDs as $id) {
			$key = 'data' . $id; 	
			$value = empty($values[$key]) ? '' : $values[$key]; 
			$this->data[$id] = $value; 
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
		if(is_object($languageID) && $languageID instanceof Language) $languageID = $languageID->id;
		if(is_string($languageID) && !ctype_digit("$languageID")) $languageID = $this->wire('languages')->get($languageID)->id;
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
	 * Given an Inputfield with multi language values, this grabs and populates the language values from it
	 *
	 * @param Inputfield $inputfield
	 *
	 */
	public function setFromInputfield(Inputfield $inputfield) {

		foreach($this->wire('languages') as $language) {
			if($language->isDefault) {
				$key = 'value';
			} else {
				$key = 'value' . $language->id; 
			}
			$this->setLanguageValue($language->id, $inputfield->$key); 
		}
	}

	/**
	 * Given a language, returns the value in that language
	 *
	 * @param Language|int|string Language object, id, or name
	 * @return int
	 *
	 */
	public function getLanguageValue($languageID) {
		if(is_object($languageID) && $languageID instanceof Language) $languageID = $languageID->id; 
		if(is_string($languageID) && !ctype_digit("$languageID")) $languageID = $this->wire('languages')->get($languageID)->id;
		$languageID = (int) $languageID; 
		return isset($this->data[$languageID]) ? $this->data[$languageID] : '';
	}

	/**
	 * Returns the value in the default language
	 *
	 */
	public function getDefaultValue() {
		return $this->data[$this->defaultLanguagePageID];
	}

	/**
	 * The string value is the value in the current user's language
	 *
	 */
	public function __toString() {
		return $this->wire('hooks')->isHooked('LanguagesPageFieldValue::getStringValue()') ? $this->__call('getStringValue', array()) : $this->___getStringValue();
	}

	protected function ___getStringValue() {
		$language = $this->wire('user')->language; 	
		$defaultValue = (string) $this->data[$this->defaultLanguagePageID];
		if(!$language || !$language->id || $language->isDefault()) return $defaultValue; 
		$languageValue = (string) (empty($this->data[$language->id]) ? '' : $this->data[$language->id]); 
		if(!strlen($languageValue)) {
			// value is blank
			if($this->field) { 
				if($this->field->langBlankInherit == self::langBlankInheritDefault) {
					// inherit value from default language
					$languageValue = $defaultValue; 
				}
			}
		}
		return $languageValue; 
	}

	public function setField(Field $field) {
		$this->field = $field; 
	}
	
	public function setPage(Page $page) {
		$this->page = $page; 
	}
	
	public function __debugInfo() {
		$info = parent::__debugInfo();
		foreach($this->wire('languages') as $language) {
			$info[$language->name] = isset($this->data[$language->id]) ? $this->data[$language->id] : '';
		}
		return $info;	
	}

	/**
	 * Allows iteration of the languages values
	 *
	 * Fulfills \IteratorAggregate interface.
	 *
	 * @return ArrayObject
	 *
	 */
	public function getIterator() {
		return new \ArrayObject($this->data);
	}
}


