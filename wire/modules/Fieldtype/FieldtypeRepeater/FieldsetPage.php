<?php namespace ProcessWire;

/**
 * FieldsetPage represents Page objects used by the FieldtypeFieldsetPage module
 *
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 */

class FieldsetPage extends RepeaterPage {
	
	/**
	 * Track a change to a property in this object
	 *
	 * The change will only be recorded if change tracking is enabled for this object instance.
	 *
	 * #pw-group-changes
	 *
	 * @param string $what Name of property that changed
	 * @param mixed $old Previous value before change
	 * @param mixed $new New value
	 * @return $this
	 *
	 */
	public function trackChange($what, $old = null, $new = null) {
		if($this->trackChanges()) {
			$forPage = $this->getForPage();
			$forField = $this->getForField();
			if($forPage && $forField) $forPage->trackChange($forField->name);
		}
		return parent::trackChange($what, $old, $new); 
	}

	/**
	 * Get a property
	 * 
	 * @param string $key
	 * @return mixed
	 * 
	 */
	public function get($key) {
	
		// mirror the output formatting state of the owning page
		if($this->forPage) {
			$of = $this->forPage->of();
			if($of != $this->of()) $this->of($of); 
		}
		
		if(strpos($key, 'for_page_') === 0) {
			list(,$property) = explode('for_page_', $key);
			if($property) return $this->getForPage()->get($property); 
		}
		
		return parent::get($key);
	}
	
	/**
	 * Return the page that this repeater item is for
	 *
	 * @return Page
	 *
	 */
	public function getForPage() {

		if(!is_null($this->forPage)) return $this->forPage;

		$prefix = FieldtypeRepeater::repeaterPageNamePrefix;  // for-page-
		$name = $this->name; 

		if(strpos($name, $prefix) === 0) {
			// determine owner page from name in format: for-page-1234
			$forID = (int) substr($name, strlen($prefix));
			$this->forPage = $this->wire('pages')->get($forID);
		} else {
			$this->forPage = $this->wire('pages')->newNullPage();
		}

		return $this->forPage;
	}
	
	/**
	 * Return the field that this repeater item belongs to
	 *
	 * @return Field
	 *
	 */
	public function getForField() {
		
		if(!is_null($this->forField)) return $this->forField;

		$parentName = $this->parent()->name; 
		$prefix = FieldtypeRepeater::fieldPageNamePrefix;  // for-field-

		if(strpos($parentName, $prefix) === 0) {
			// determine field from grandparent name in format: for-field-1234
			$forID = (int) substr($parentName, strlen($prefix));
			$this->forField = $this->wire('fields')->get($forID);
		}

		return $this->forField;
	}
	
}