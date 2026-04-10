<?php namespace ProcessWire;

/**
 * Options Field (for FieldtypeOptions)
 *
 * Configured with FieldtypeOptions
 * ==============================
 * @property string $inputfieldClass Inputfield class used for input, determines single vs. multi-select behavior.
 *   Examples: 'InputfieldSelect' (single, default), 'InputfieldRadios' (single), 'InputfieldCheckboxes' (multi),
 *   'InputfieldSelectMultiple' (multi), 'InputfieldAsmSelect' (multi sortable), 'InputfieldTextTags' (multi).
 * @property int|array $initValue Pre-selected option ID or array of IDs for required fields (default='').
 *
 * Notes: 
 *   - The selectable options (titles and values) are managed via the field editor in the
 *     admin and stored in a separate database table (fieldtype_options), not as field 
 *     configuration settings.
 *   - All CRUD operations in this class should be followed with a `$field->save()`.
 *
 * Other properties 
 * ==============================
 * @property SelectableOptionManager $manager
 * @property FieldtypeOptions $type
 * @since 3.0.258
 *
 */
class OptionsField extends Field {

	/**
	 * Get options manager
	 * 
	 * @return SelectableOptionManager
	 *
	 */
	public function manager() {
		return $this->type->manager;
	}

	/**
	 * Get field property
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		if($key === 'manager') return $this->type->manager;
		return parent::get($key);
	}

	/**
	 * Return array of current options for this field
	 *
	 * Returned array is indexed by "id$option_id" associative, which is used
	 * as a way to identify existing options vs. new options
	 *
	 * @param array $filters Any of array(property => array) where property is 'id', 'title' or 'value'.
	 * @return SelectableOptionArray|SelectableOption[]
	 * @throws WireException
	 *
	 */
	public function getOptions(array $filters = array()) {
		return $this->manager->getOptions($this, $filters);
	}

	/**
	 * Find by matching title and/or value of all options and return matches
	 *
	 * @param string $property Either 'title' or 'value'. May also be blank (to imply 'either') if operator is '=' or '!='
	 * @param string $operator Selector operator to use
	 * @param string $value Value to find
	 * @return SelectableOptionArray
	 *
	 */
	public function findOptionsByProperty($property, $operator, $value) {
		return $this->manager->findOptionsByProperty($this, $property, $operator, $value);
	}

	/**
	 * Get the string that defines all options for this field
	 *
	 * @param int|string|Language $language Language id, object, or name, if applicable
	 * @return string
	 * @throws WireException if given invalid language
	 *
	 */
	public function getOptionsString($language = '') {
		return $this->manager->getOptionsString($this->getOptions(), $language);
	}

	/**
	 * Set the options string that defines all options for this field
	 *
	 * Should adhere to this format:
	 * One option per line in the format: '123=title' or '123=value|title'
	 * where '123' is the option ID, 'value' is an optional value,
	 * and 'title' is a required title.
	 *
	 * For new options, specify just the option 'title' (or 'value|title') on
	 * its own line. Options should be in the desired sort order.
	 *
	 * Return value is an array of quantities indexed by type, i.e.
	 * ~~~~
	 * [
	 *   'added' => 2,       // quantity of items added
	 *   'updated' => 1,     // quantity of items updated
	 *   'deleted' => 0,     // quantity of items deleted
	 *   'marked' => 0       // quantity of items marked for deletion
	 * ]
	 * ~~~~
	 *
	 * @param string $value
	 * @param bool $allowDelete Allow remove lines in the string to result in deleted options?
	 *   If false, no options will be affected but you can call the getRemovedOptionIDs() method
	 *   to retrieve them for confirmation.
	 * @return array containing [ 'added' => qty, 'updated' => qty, 'deleted' => qty, 'marked' => qty ]
	 *   note: 'marked' means marked for deletion
	 *
	 */
	public function setOptionsString($value, $allowDelete = true) {
		return $this->manager->setOptionsString($this, $value, $allowDelete);
	}

	/**
	 * Set options definition string, but for multi-language
	 *
	 * @param array $values Array of `[ language => string ]`, one for each language,
	 *   where 'language' is language ID or name, and 'string' is options definition string.
	 *   See the setOptionsString() method for details on the format of 'string'.
	 * @param bool $allowDelete Allow removed lines in the string to result in deleted options?
	 *   If false, no options will be affected but you can call the getRemovedOptionIDs() method
	 *   to retrieve them for confirmation.
	 * @return array Array of quantities indexed by: added, updated, deleted, marked
	 *   Note that prior to 3.0.258 there was no return value.
	 * @throws WireException If language support is not installed
	 *
	 */
	public function setOptionsStringLanguages(array $values, $allowDelete = true) {
		$languages = $this->wire()->languages;
		if(!$languages) throw new WireException('Language support not active');
		// convert language name keys to language IDs
		foreach($values as $key => $value) {
			if(ctype_digit("$key")) continue; // language IDs
			$language = $languages->get($key);
			if(!$language || !$language->id) continue;
			unset($values[$key]);
			$values[$language->id] = $value;
		}
		return $this->manager->setOptionsStringLanguages($this, $values, $allowDelete);
	}

	/**
	 * Set selectable options for this field and apply added, deleted, and updated options
	 *
	 * You may find it simpler to use the setOptionsString() method instead.
	 *
	 * Return value is an array of quantities indexed by type, i.e.
	 * ~~~~
	 * [
	 *   'added' => 2,       // quantity of items added
	 *   'updated' => 1,     // quantity of items updated
	 *   'deleted' => 0,     // quantity of items deleted
	 *   'marked' => 0       // quantity of items marked for deletion
	 * ]
	 * ~~~~
	 *
	 * @param array|SelectableOptionArray $options Array of SelectableOption objects
	 *   For new options specify 0 for the 'id' property.
	 * @param bool $allowDelete Allow options to be deleted? If false, the options marked for
	 *   deletion can be retrieved via the getRemovedOptionIDs() method
	 * @return array Array of quantities as described above in method documentation.
	 * @throws WireException
	 *
	 */
	public function setOptions($options, $allowDelete = true) {
		return $this->manager->setOptions($this, $options, $allowDelete);
	}

	/**
	 * Get option IDs found to be removed from the last setOptions() or setOptionsString() call
	 *
	 * These are for options not yet deleted, and that should be deleted after confirmation.
	 * They can be deleted with this $this->deleteOptionIDs() method.
	 *
	 * @return array|int[]
	 *
	 */
	public function getRemovedOptionIDs() {
		return $this->manager->getRemovedOptionIDs();
	}

	/**
	 * Update options for this field
	 *
	 * @param SelectableOption[]|SelectableOptionArray $options
	 * @return int Number of options updated
	 *
	 */
	public function updateOptions($options) {
		return $this->manager->updateOptions($this, $options);
	}

	/**
	 * Delete the given options for this field
	 *
	 * @param SelectableOption[]|SelectableOptionArray $options
	 * @return int Number of options deleted
	 *
	 */
	public function deleteOptions($options) {
		return $this->manager->deleteOptions($this, $options);
	}

	/**
	 * Delete the given option IDs for this field
	 *
	 * @param array $ids
	 * @return int Number of options deleted
	 *
	 */
	public function deleteOptionsByID(array $ids) {
		return $this->manager->deleteOptionsByID($this, $ids);
	}

	/**
	 * Delete all options for this field
	 *
	 * @return int
	 *
	 */
	public function deleteAllOptions() {
		return $this->manager->deleteAllOptionsForField($this);
	}

	/**
	 * Add the given option for to this field
	 *
	 * @param SelectableOption[]|SelectableOptionArray $options
	 * @return int Number of options added
	 *
	 */
	public function addOptions($options) {
		return $this->manager->addOptions($this, $options);
	}

	/**
	 * Make a new/blank SelectableOption and return it
	 * 
	 * @param array $a Optionally populate with associative array containing
	 *   one or more of: id, title, value, or sort
	 * @return SelectableOption
	 * 
	 */
	public function newSelectableOption(array $a = []) {
		return $this->manager->arrayToOption($a); 
	}

	/**
	 * Get a new/blank SelectableOptionArray for this field
	 *
	 * @param Page|null $page Optionally specify page it is for
	 * @return SelectableOptionArray
	 *
	 */
	public function newSelectableOptionArray(?Page $page = null) {
		/** @var SelectableOptionArray $a */
		$a = $this->wire(new SelectableOptionArray());
		$a->setField($this);
		if($page) $a->setPage($page);
		return $a;
	}
}
