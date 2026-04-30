<?php namespace ProcessWire;

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
 * Inputfields that implement this interface always have a $value attribute that is an array
 *
 */
interface InputfieldHasArrayValue { }

/**
 * Inputfield that doesn’t have an array value by default but can return array value or accept it
 *
 * @since 3.0.176
 *
 */
interface InputfieldSupportsArrayValue {
	/**
	 * @return array
	 *
	 */
	public function getArrayValue();
	
	/**
	 * @param array $value
	 *
	 */
	public function setArrayValue(array $value);
}

/**
 * Inputfield that supports a Page selector for selectable options
 *
 * @since 3.0.176
 *
 */
interface InputfieldSupportsPageSelector {
	/**
	 * Set page selector or test if feature is disabledd
	 *
	 * @param string $selector Selector string or blank string when testing if feature is disabled
	 * @return bool Return boolean false if feature disabled, otherwise boolean true
	 *
	 */
	public function setPageSelector($selector);
}

/**
 * Inputfield that has a text value by default
 *
 * @since 3.0.176
 *
 */
interface InputfieldHasTextValue { }

/**
 * Inputfield that has a sortable value (usually in addition to InputfieldHasArrayValue)
 *
 */
interface InputfieldHasSortableValue { }

/**
 * Inputfield that supports selectable options
 *
 * @since 3.0.176
 *
 */
interface InputfieldHasSelectableOptions {
	/**
	 * Add a selectable option
	 *
	 * @param string|int $value
	 * @param string|null $label
	 * @param array|null $attributes
	 * @return self|$this
	 *
	 */
	public function addOption($value, $label = null, ?array $attributes = null);
	
	/**
	 * Add selectable option with label, optionally for specific language
	 *
	 * @param string|int $value
	 * @param string $label
	 * @param Language|null $language
	 * @return self|$this
	 *
	 */
	public function addOptionLabel($value, $label, $language = null);
}

