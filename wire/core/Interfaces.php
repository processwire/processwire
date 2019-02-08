<?php namespace ProcessWire;

/**
 * ProcessWire Interfaces
 *
 * Interfaces used throughout ProcessWire's core.
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

/** 
 * For classes that are saved to a database or disk.
 *
 * Item must have a gettable/settable 'id' property for this interface as well
 * 
 * @property int $id
 * @property string $name
 *
 */
interface Saveable {

	/**
	 * Save the object's current state to database.
	 *
	 */
	public function save(); 

	/**
	 * Get an array of this item's saveable data, should match exact with the table it saves in
	 * 
	 * @return array
	 *
	 */
	public function getTableData();

}

/**
 * For classes that may have their data exported to an array 
 * 
 * Classes implementing this interface are also assumed to be able to accept the same 
 * 
 * 
 */ 
interface Exportable {
	
	/**
	 * Return export data (may be the same as getTableData from Saveable interface)
	 *
	 * @return array
	 *
	 */
	public function getExportData();

	/**
	 * Given an export data array, import it back to the class and return what happened
	 * 
	 * @param array $data
	 * @return array Returns array(
	 * 	[property_name] => array(
	 * 		'old' => 'old value',	// old value, always a string
	 * 		'new' => 'new value',	// new value, always a string
	 * 		'error' => 'error message or blank if no error'
	 * 	)
	 * 
	 */
	public function setImportData(array $data);

}


/**
 * Class HasRoles
 * 
 * @deprecated
 * 
 */
interface HasRoles {
	// To be deleted
}


/**
 * For classes that contain lookup items, as used by WireSaveableItemsLookup
 *
 */
interface HasLookupItems {

	/**
	 * Get all lookup items, usually in a WireArray derived type, but specified by class
	 *
	 */
	public function getLookupItems(); 

	/**
	 * Add a lookup item to this instance
	 *
	 * @param int $item The ID of the item to add 
	 * @param array $row The row from which it was retrieved (in case you want to retrieve or modify other details)
	 *
	 */
	public function addLookupItem($item, array &$row); 
	
}


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
 * Indicates that a given Fieldtype may be used for page titles
 *
 */
interface FieldtypePageTitleCompatible { }


/**
 * Indicates that an Inputfield provides tree selection capabilities
 *
 * In such Inputfields a parent_id refers to the root of the tree rather than an immediate parent.
 *
 */
interface InputfieldPageListSelection { }

/**
 * Indicates that an Inputfield renders a list of items
 *
 */
interface InputfieldItemList { }

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
 * Interface that indicates the required methods for a class to be hookable.
 * 
 * @deprecated
 * 
 */
interface WireHookable { }

/**
 * Interface that indicates a class supports API variable dependency injection and retrieval
 * 
 */
interface WireFuelable {
	
	/**
	 * Get or inject a ProcessWire API variable or fuel a new object instance
	 *
	 * See Wire::wire() for explanation of all options. 
	 *
	 * @param string|WireFuelable $name Name of API variable to retrieve, set, or omit to retrieve entire Fuel object.
	 * @param null|mixed $value Value to set if using this as a setter, otherwise omit.
	 * @param bool $lock When using as a setter, specify true if you want to lock the value from future changes (default=false)
	 * @return mixed|Fuel
	 * @throws WireException
	 *
	 */
	public function wire($name = '', $value = null, $lock = false);

	/**
	 * Set the ProcessWire instance
	 * 
	 * @param ProcessWire $wire
	 * 
	 */
	public function setWire(ProcessWire $wire);

	/**
	 * Get the ProcessWire instance
	 * 
	 * @return ProcessWire
	 * 
	 */
	public function getWire(); 
	
}

/**
 * Interface that indicates the class supports Notice messaging
 * 
 */
interface WireNoticeable {
	/**
	 * Record an informational or 'success' message in the system-wide notices.
	 *
	 * This method automatically identifies the message as coming from this class.
	 *
	 * @param string $text
	 * @param int $flags See Notices::flags
	 * @return $this
	 *
	 */
	public function message($text, $flags = 0);

	/**
	 * Record an non-fatal error message in the system-wide notices.
	 *
	 * This method automatically identifies the error as coming from this class.
	 *
	 * Fatal errors should still throw a WireException (or class derived from it)
	 *
	 * @param string $text
	 * @param int $flags See Notices::flags
	 * @return $this
	 *
	 */
	public function error($text, $flags = 0);
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
 * Interface for Process modules that can edit pages (ProcessPageEdit being the most obvious)
 *
 */
interface WirePageEditor {
	/**
	 * @return Page The current page being edited
	 */
	public function getPage(); 	
}

/**
 * Interface that indicates the object supports its items being paginated
 * 
 */
interface WirePaginatable {
	
	/**
	 * Set the total number of items, if more than are in the WireArray.
	 *
	 * @param int $total
	 * @return $this
	 *
	 */
	public function setTotal($total);

	/**
	 * Get the total number of items in all paginations of the WireArray.
	 * 
	 * If no limit used, this returns total number of items currently in the WireArray.
	 *
	 * @return int
	 *
	 */
	public function getTotal();
	
	/**
	 * Set the limit that was used in pagination.
	 *
	 * @param int $numLimit
	 * @return $this
	 *
	 */
	public function setLimit($numLimit);

	/**
	 * Get the limit that was used in pagination.
	 *
	 * If no limit set, then return number of items currently in this WireArray.
	 *
	 * @return int
	 *
	 */
	public function getLimit();

	/**
	 * Set the starting offset that was used for pagination.
	 *
	 * @param int $numStart;
	 * @return $this
	 *
	 */
	public function setStart($numStart);
	
	/**
	 * Get the starting offset that was used for pagination.
	 *
	 * @return int
	 *
	 */
	public function getStart();

}

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
	 * @return int
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
 * Interface used to indicate that the Fieldtype supports multiple languages
 *
 */
interface FieldtypeLanguageInterface {
	/*
	 * This interface is symbolic only and doesn't require any additional methods, 
	 * however you do need to add an 'implements FieldtypeLanguageInterface' when defining your class. 
	 * 
	 */
}

/**
 * Interface for objects that carry a Field value for a Page
 * 
 * Optional, but enables Page to do some of the work rather than the Fieldtype
 *
 */
interface PageFieldValueInterface {
	
	/**
	 * Get or set formatted state
	 * 
	 * @param bool|null $set Specify bool to set formatted state or omit to retrieve formatted state
	 * @return bool
	 * 
	 */
	public function formatted($set = null);

	/**
	 * Set the Page
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page);

	/**
	 * Set the Field
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field);

	/**
	 * Get the page or null if not set
	 * 
	 * @return Page|null
	 * 
	 */
	public function getPage();

	/**
	 * Get the field or null if not set
	 * 
	 * @return Field|null
	 * 
	 */
	public function getField();
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
 * Inputfields that implement this interface always have a $value attribute that is an array
 *
 */
interface InputfieldHasArrayValue { }

/**
 * Inputfield that has a sortable value (usually in addition to InputfieldHasArrayValue)
 *
 */
interface InputfieldHasSortableValue { }


