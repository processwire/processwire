<?php namespace ProcessWire;

/**
 * ProcessWire Selectable Option class, for FieldtypeOptions
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * @property int $id
 * @property int $sort
 * @property string $title
 * @property string $value
 * 
 */

class SelectableOption extends WireData { // implements LanguagesValueInterface {
	
	static protected $defaults = array(
		'id' => 0, 
		'sort' => 0,
		'title' => '', 
		'value' => '', 
	);

	/**
	 * Output formatting on/off
	 * 
	 * @var bool
	 * 
	 */
	protected $of = false;
	
	public function __construct() {
		foreach(self::$defaults as $property => $value) {
			$this->set($property, $value); 
		}
	}
	
	static public function isProperty($property) {
		return array_key_exists($property, self::$defaults); 
	}

	/**
	 * Turn output formatting on or off, or get current value
	 * 
	 * @param null|bool $of Omit to return current value, or specify true|false to set
	 * @return bool
	 * 
	 */
	public function of($of = null) {
		if(is_null($of)) return $this->of; 
		$this->of = $of ? true : false; 
		return $this->of; 
	}
	
	public function get($key) {
		
		if($this->of && $key == 'title') return $this->getTitle();
		if($this->of && $key == 'value') return $this->getValue();
		
		return parent::get($key); 	
	}
	
	public function set($key, $value) {
		if(strpos($key, 'title') === 0 || strpos($key, 'value') === 0) {
			if(strpos($value, '|') !== false) {
				$this->error("$key may not contain the character '|'"); 
				$value = str_replace('|', ' ', $value); 
			}
		}
		return parent::set($key, $value); 
	}
	
	/**
	 * Return all values stored in this SelectableOption
	 * 
	 * @param bool $returnHash Makes it return a string hash of all values
	 * @return string|array
	 * 
	 */
	public function values($returnHash = false) {
		$values = array(
			'title' => $this->get('title'),
			'value' => $this->get('value'),
			'sort' => $this->get('sort'), 
			'data' => $this->get('data'), 
		); 
		if($this->wire('languages')) {
			foreach($this->wire('languages') as $language) {
				if($language->isDefault()) continue; 
				$values["title$language"] = $this->get("title$language");
				$values["value$language"] = $this->get("value$language"); 
			}
		}
		if($returnHash) return sha1(print_r($values, true)); 
		return $values; 
	}

	/**
	 * Get the language-aware property
	 * 
	 * @param string $property Either 'title' or 'value'
	 * @return string
	 * 
	 */
	public function getProperty($property) {
		if($this->wire('languages')) {
			$language = $this->wire('user')->language; 
			if($language->isDefault()) {
				$value = parent::get($property); 
			} else {
				$value = parent::get("$property$language"); 
				// fallback to default language title if no title present for language
				if(!strlen($value)) $value = parent::get($property); 
			}
		} else {
			$value = parent::get($property); 
		}
		if($this->of) $value = $this->wire('sanitizer')->entities($value); 
		return $value; 
	}

	/**
	 * Get the language-aware value
	 *
	 * @return string
	 *
	 */
	public function getValue() {
		return $this->getProperty('value'); 
	}

	/**
	 * Get the language-aware title
	 *
	 * @return string
	 *
	 */
	public function getTitle() {
		return $this->getProperty('title'); 
	}
	
	public function __toString() {
		return (string) $this->id;
	}
	
	public function debugInfoSmall() {
		return array(
			'id' => $this->id,
			'title' => $this->getTitle(),
			'value' => $this->getValue(),
		);
	}

	/**
	 * Sets the value for a given language
	 *
	 * @param int|Language $languageID
	 * @param mixed $value
	 *
	public function setLanguageValue($languageID, $value) {
		$language = is_object($languageID) ? $languageID : $this->wire('languages')->get($languageID); 
		if($language && $language->id) {
			if($language->isDefault()) $language = '';
			$this->set("title$language", $value);
		}
	}
	 */

	/**
	 * Given a language, returns the value in that language
	 *
	 * @param Language|int
	 * @return int|string
	 *
	public function getLanguageValue($languageID) {
		$language = is_object($languageID) ? $languageID : $this->wire('languages')->get($languageID);
		if($language && $language->id) {
			if($language->isDefault()) $language = '';
			return $this->get("title$language");
		}
		return '';
	}
	 */
}
