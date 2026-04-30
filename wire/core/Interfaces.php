<?php namespace ProcessWire;

/**
 * ProcessWire Interfaces
 *
 * Interfaces used throughout ProcessWire's core.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * For classes that need to track changes made by other objects. 
 *
 */
interface WireTrackable {

	/**
	 * Turn change tracking ON or OFF
	 *
	 * @param bool|int $trackChanges True to turn on, false to turn off. Integer to specify bitmask.
	 * @return $this
	 *
	 */
	public function setTrackChanges($trackChanges = true);

	/**
	 * Track a change to a property in this object
	 *
	 * The change will only be recorded if self::$trackChanges is true.
	 *
	 * @param string $what Name of property that changed
	 * @param mixed $old Previous value before change
	 * @param mixed $new New value
	 * @return $this
	 *
	 */
	public function trackChange($what, $old = null, $new = null);

	/**
	 * Has the given property changed?
	 *
	 * Applicable only for properties you are tracking while $trackChanges is true.
	 *
	 * @param string $what Name of property, or if left blank, check if any properties have changed.
	 * @return bool
	 *
	 */	
	public function isChanged($what = ''); 

	/**
	 * Return an array of properties that have changed while change tracking was on.
	 *
	 * @param bool $getValues If true, then an associative array will be retuned with field names and previous values.
	 * @return array
	 *
	 */
	public function getChanges($getValues = false);

}


/**
 * Interface that indicates a class contains gettext() like translation methods 
 *
 */
interface WireTranslatable {
	/**
	 * Translate the given text string into the current language if available.
	 *
	 * If not available, or if the current language is the native language, then it returns the text as is.
	 *
	 * @param string $text Text string to translate
	 * @return string
	 *
	 */
	public function _($text);

	/**
	 * Perform a language translation in a specific context
	 *
	 * Used when to text strings might be the same in English, but different in other languages.
	 *
	 * @param string $text Text for translation.
	 * @param string $context Name of context
	 * @return string Translated text or original text if translation not available.
	 *
	 */
	public function _x($text, $context);

	/**
	 * Perform a language translation with singular and plural versions
	 *
	 * @param string $textSingular Singular version of text (when there is 1 item)
	 * @param string $textPlural Plural version of text (when there are multiple items or 0 items)
	 * @param int $count Quantity used to determine whether singular or plural.
	 * @return string Translated text or original text if translation not available.
	 *
	 */
	public function _n($textSingular, $textPlural, $count);
}


/**
 * Interface for ProcessWire database layer
 * 
 */
interface WireDatabase {
	/**
	 * Is the given string a database comparison operator?
	 *
	 * @param string $str 1-2 character opreator to test
	 * @return bool
	 *
	 */
	public function isOperator($str);
}

/**
 * Interface shared by all ProcessWire Null objects
 *
 */
interface WireNull { }

/**
 * Interface WireMatchable
 * 
 * Interface for objects that provide their own matches() method for matching selector strings
 * 
 */
interface WireMatchable {
	
	/**
	 * Does this object match the given Selectors object or string?
	 * 
	 * @param Selectors|string $s
	 * @return bool
	 * 
	 */
	public function matches($s); 
}

/**
 * Interface LanguagesValueInterface
 * 
 * Interface for multi-language fields
 * 
 */
interface LanguagesValueInterface {

	/**
	 * Sets the value for a given language
	 *
	 * @param int|Language $languageID
	 * @param mixed $value
	 *
	 */
	public function setLanguageValue($languageID, $value);

	/**
	 * Given a language, returns the value in that language
	 *
	 * @param Language|int
	 * @return string|mixed
	 *
	 */
	public function getLanguageValue($languageID);

	/**
	 * Given an Inputfield with multi language values, this grabs and populates the language values from it
	 *
	 * @param Inputfield $inputfield
	 *
	 */
	public function setFromInputfield(Inputfield $inputfield);

}

/**
 * Interface for tracking runtime events
 *
 */
interface WireProfilerInterface {
	
	/**
	 * Start profiling an event
	 * 
	 * Return the event array to be used for stop profiling
	 * 
	 * @param string $name Name of event in format "method" or "method.id" or "something"
	 * @param Wire|object|string|null Source of event (may be object instance)
	 * @param array $data
	 * @return mixed Event to be used for stop call
	 * 
	 */
	public function start($name, $source = null, $data = array()); 

	/**
	 * Stop profiling an event
	 * 
	 * @param array|object|string $event Event returned by start() 
	 * @return void
	 * 
	 */
	public function stop($event);

	/**
	 * End of request maintenance
	 * 
	 * @return void
	 * 
	 */
	public function maintenance();
}

/**
 * @deprecated
 *
 */
interface HasRoles {
	// To be deleted
}

/**
 * Interface that indicates the required methods for a class to be hookable.
 *
 * @deprecated
 *
 */
interface WireHookable { }



