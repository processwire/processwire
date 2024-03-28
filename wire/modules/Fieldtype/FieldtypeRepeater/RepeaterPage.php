<?php namespace ProcessWire;

/**
 * RepeaterPage represents an individual repeater page item
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @property-read int $depth
 *
 */

class RepeaterPage extends Page {

	/**
	 * Page instance that has this repeater item on it
	 * 
	 * @var Page|null
	 *
	 */
	protected $forPage = null;		

	/**
	 * Field instance that contains this repeater item
	 * 
	 * @var Field|null
	 * 
	 */
	protected $forField = null;
	
	/**
	 * Depth of this item
	 *
	 * @var int|null
	 *
	 */
	protected $depth = null;

	/**
	 * Set the page that owns this repeater item
	 *
	 * @param Page $forPage
	 * @return $this
	 *
	 */
	public function setForPage(Page $forPage) {
		$this->forPage = $forPage; 
		/* future use
		if($forPage->hasStatus(Page::statusDraft)) {
			if(!$this->hasStatus(Page::statusDraft)) $this->addStatus(Page::statusDraft);
		} else {
			if($this->hasStatus(Page::statusDraft)) $this->removeStatus(Page::statusDraft);
		}
		*/
		return $this;
	}

	/**
	 * Return the page that this repeater item is for
	 *
	 * @return Page
	 *
	 */
	public function getForPage() {

		if(!is_null($this->forPage)) return $this->forPage; 

		// ownerPage is usually set by FieldtypeRepeater::wakeupValue
		// but if this repeater was loaded from somewhere else, that won't 
		// have happened, so we have to determine it from it's location

		
		/** @var Page $parent */
		$parent = $this->parent();
		$parentName = $parent->name;
		$prefix = FieldtypeRepeater::repeaterPageNamePrefix;  // for-page-

		if(strpos($parentName, $prefix) === 0) {
			// determine owner page from parent name in format: for-page-1234
			$forID = (int) substr($parentName, strlen($prefix));
			$this->forPage = $this->wire()->pages->get($forID); 
		} else {
			// this probably can't occur, but here just in case
			$this->forPage = $this->wire()->pages->newNullPage();
		}

		return $this->forPage;
	}

	/**
	 * Set the field that owns this repeater item
	 *
	 * @param Field $forField
	 * @return $this
	 *
	 */
	public function setForField(Field $forField) {
		$this->forField = $forField;
		return $this;
	}

	/**
	 * Return the field that this repeater item belongs to
	 * 
	 * Returns null only if $forField has not been set and cannot be determined from any other
	 * properties of this page. Meaning null return value is not likely.
	 *
	 * @return Field|null 
	 *
	 */
	public function getForField() {
		if($this->forField !== null) return $this->forField;

		// auto-detect forField from its location
		$grandparent = $this->parent()->parent();
		$grandparentName = $grandparent->name;
		$prefix = FieldtypeRepeater::fieldPageNamePrefix;  // for-field-
		$forField = null;
		$fields = $this->wire()->fields;

		if(strpos($grandparentName, $prefix) === 0) {
			// determine field from grandparent name in format: for-field-1234
			$forID = (int) substr($grandparentName, strlen($prefix));
			$forField = $fields->get($forID); 
		} else {
			// page must exist somewhere outside the expected location, so use template
			// name as a secondary way to identify what the field is
			$template = $this->template;
			if($template && strpos($template->name, FieldtypeRepeater::templateNamePrefix) === 0) {
				list(,$fieldName) = explode(FieldtypeRepeater::templateNamePrefix, $template->name, 2);
				$forField = $fields->get($fieldName);
			}
		}
		
		if($forField) $this->forField = $forField;
		
		return $forField;
	}

	/**
	 * For nested repeaters, returns the root level forPage and forField in an array
	 *
	 * @param string $get Specify 'page' or 'field' or omit for array of both
	 * @return array|Page|Field
	 * @since 3.0.132
	 *
	 */
	protected function getForRoot($get = '') {
		$forPage = $this->getForPage();
		$forField = $this->getForField();
		$n = 0;
		while($forPage instanceof RepeaterPage && ++$n < 20) {
			$forField = $forPage->getForField();
			$forPage = $forPage->getForPage();
		}
		if($forPage instanceof RepeaterPage) {
			$forPage = new NullPage();
		}
		if($get === 'page') return $forPage;
		if($get === 'field') return $forField;
		return array('page' => $forPage, 'field' => $forField);
	}

	/**
	 * For nested repeaters, return the root-level field that this repeater item belongs to
	 *
	 * @return Field
	 * @since 3.0.132
	 *
	 */
	public function getForFieldRoot() {
		return $this->getForRoot('field');
	}

	/**
	 * For nested repeaters, return the root-level non-repeater page that this repeater item belongs to
	 *
	 * @return Page
	 * @since 3.0.132
	 *
	 */
	public function getForPageRoot() {
		return $this->getForRoot('page');
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return int|mixed|null
	 * 
	 */
	public function get($key) {
		$value = parent::get($key);
		if($key === 'depth' && is_null($value)) {
			$value = $this->getDepth();
		}
		return $value;
	}

	/**
	 * Get depth 
	 * 
	 * @return int
	 * 
	 */
	public function getDepth() {
		if(is_null($this->depth)) {
			$this->depth = 0;
			$name = $this->name;
			while($name[$this->depth] === '-') $this->depth++;
		}
		return $this->depth;		
	}

	/**
	 * Set depth 
	 * 
	 * @param int $depth
	 * 
	 */
	public function setDepth($depth) {
		$name = $this->name;
		$_name = $name;
		$name = ltrim($name, '-');
		if($depth > 0) $name = str_repeat('-', $depth) . $name;
		if($name !== $_name) $this->name = $name;
		$this->depth = $depth;
	}

	/* @todo
	public function depthParent() { }
	public function depthChildren() { }
	 */ 

	/**
	 * Is this page public?
	 *
	 * In this case, we delegate that decision to the owner page.
	 *
	 * @return bool
	 *
	 */
	public function isPublic() {
		if($this->isUnpublished()) return false;
		return $this->getForPage()->isPublic();
	}

	/**
	 * Is this a ready page?
	 * 
	 * @return bool
	 * 
	 */
	public function isReady() {
		return $this->isUnpublished() && $this->isHidden();
	}

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
	public function trackChange($what, $old = null, $new = null) {
		parent::trackChange($what, $old, $new);
		if($this->trackChanges()) {
			$forPage = $this->getForPage();
			$forField = $this->getForField();
			if($forPage->id && $forField) $forPage->trackChange($forField->name);
		}
		return $this;
	}
	 */
	
	public function getAccessTemplate($type = 'view') {
		$p = $this->getForPageRoot();
		return $p->id ? $p->getAccessTemplate($type) : parent::getAccessTemplate($type);
	}
}
