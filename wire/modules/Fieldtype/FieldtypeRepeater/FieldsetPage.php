<?php namespace ProcessWire;

/**
 * FieldsetPage represents Page objects used by the FieldtypeFieldsetPage module
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class FieldsetPage extends RepeaterPage {

	/**
	 * Is the getOf() method in progress?
	 * 
	 * @var bool
	 * 
	 */
	protected $getOf = null;
	
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
		if($this->forPage && !$this->getOf && !$this->isNew) {
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
	 * Get property in formatted (true) or unformatted (false) state
	 * 
	 * @param string $key
	 * @param bool $of
	 * @return mixed
	 * @since 3.0.215
	 * 
	 */
	protected function getOf($key, $of) {
		$this->getOf = true;
		if($this->of() != $of) {
			$this->of($of);
			$value = parent::get($key);
			$this->of(!$of);
		} else {
			$value = $this->get($key);
		}
		$this->getOf = false;
		return $value;
	}
	
	/**
	 * Get the unformatted value of a field, regardless of current output formatting state
	 *
	 * @param string $key Field or property name to retrieve
	 * @return mixed
	 *
	 */
	public function getUnformatted($key) {
		return $this->getOf($key, false);
	}
	
	/**
	 * Get the formatted value of a field, regardless of output formatting state
	 *
	 * @param string $key Field or property name to retrieve
	 * @return mixed
	 *
	 */
	public function getFormatted($key) {
		return $this->getOf($key, true);
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
			$this->forPage = $this->wire()->pages->get($forID);
		} else {
			$this->forPage = $this->wire()->pages->newNullPage();
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

		$parent = $this->parent();
		$parentName = $parent ? $parent->name : ''; 
		$prefix = FieldtypeRepeater::fieldPageNamePrefix;  // for-field-

		if(strpos($parentName, $prefix) === 0) {
			// determine field from grandparent name in format: for-field-1234
			$forID = (int) substr($parentName, strlen($prefix));
			$this->forField = $this->wire()->fields->get($forID);
		}

		return $this->forField;
	}
	
}
