<?php namespace ProcessWire;

/**
 * A type of Page that represents a single Language in ProcessWire
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property LanguageTranslator $translator Get instance of LanguageTranslator for this language
 * @property bool $isDefault Is this the default language?
 * @property bool $isCurrent Is this the current language?
 * @property Pagefiles $language_files Language translation files for /wire/ (language pack)
 * @property Pagefiles $language_files_site Language translation files for /site/ (custom translations per site)
 *
 */

class Language extends Page {

	/**
	 * Whether this Language represents the default
	 *
	 */ 
	protected $isDefaultLanguage = false;

	/**
	 * Construct a new Language instance
	 * 
	 * @param Template $tpl
	 *
	 */
	public function __construct(Template $tpl = null) {
		parent::__construct($tpl);
		if(is_null($tpl)) {
			$this->template = $this->wire('templates')->get('language');
		}
	}

	/**
	 * Get a value from the language page (intercepting translator and isDefault)
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		if($key == 'translator') return $this->translator();
		if($key == 'isDefault' || $key == 'isDefaultLanguage') return $this->isDefaultLanguage;
		if($key == 'isCurrent') return $this->isCurrent();
		return parent::get($key); 
	}

	/**
	 * Return an instance of the LanguageTranslator object prepared for this language
	 * 
	 * #pw-internal
	 * 
	 * @return LanguageTranslator
	 *
	 */
	public function translator() {
		return $this->wire('languages')->translator($this); 
	}	

	/**
	 * Targets this as the default language
	 * 
	 * #pw-internal
	 *
	 */
	public function setIsDefaultLanguage() { 
		$this->isDefaultLanguage = true; 
	}

	/**
	 * Returns whether or not this is the default language
	 * 
	 * @return bool
	 *
	 */
	public function isDefault() {
		return $this->isDefaultLanguage || $this->name == 'default'; 
	}

	/**
	 * Returns whether or not this is the current user’s language
	 * 
	 * @return bool
	 * 
	 */
	public function isCurrent() {
		return $this->id == $this->wire('user')->language->id;
	}

	/**
	 * Return the API variable used for managing pages of this type
	 * 
	 * #pw-internal
	 *
	 * @return Pages|PagesType
	 *
	 */
	public function getPagesManager() {
		return $this->wire('languages');
	}

	/**
	 * Get locale for this language
	 * 
	 * See the `Languages::getLocale()` method for full details.
	 * 
	 * @param int $category Optional category (default=LC_ALL)
	 * @return string|bool
	 * @see Languages::setLocale()
	 * 
	 */
	public function getLocale($category = LC_ALL) {
		return $this->wire('languages')->getLocale($category, $this);
	}

	/**
	 * Set the current locale to use settings defined for this language
	 * 
	 * See the `Languages::setLocale()` method for full details.
	 * 
	 * @param int $category Optional category (default=LC_ALL)
	 * @see Languages::setLocale()
	 * 
	 */
	public function setLocale($category = LC_ALL) {
		return $this->wire('languages')->setLocale($category, $this);
	}
}

