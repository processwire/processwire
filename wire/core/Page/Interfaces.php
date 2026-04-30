<?php namespace ProcessWire;

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

