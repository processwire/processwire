<?php namespace ProcessWire;

/**
 * RepeaterPage represents an individual repeater page item
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class RepeaterPage extends Page {

	/**
	 * Page instance that has this repeater item on it
	 *
	 */
	protected $forPage = null;		

	/**
	 * Field instance that contains this repeater item
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
			$this->forPage = $this->wire('pages')->get($forID); 
		} else {
			// this probably can't occur, but here just in case
			$this->forPage = $this->wire('pages')->newNullPage();
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
	 * @return Field
	 *
	 */
	public function getForField() {
		if(!is_null($this->forField)) return $this->forField;

		$grandparent = $this->parent()->parent();
		$grandparentName = $grandparent->name;
		$prefix = FieldtypeRepeater::fieldPageNamePrefix;  // for-field-

		if(strpos($grandparentName, $prefix) === 0) {
			// determine field from grandparent name in format: for-field-1234
			$forID = (int) substr($grandparentName, strlen($prefix));
			$this->forField = $this->wire('fields')->get($forID); 
		}

		return $this->forField;
	}
	
	public function get($key) {
		$value = parent::get($key);
		if($key === 'depth' && is_null($value)) {
			$value = $this->getDepth();
		}
		return $value;
	}
	
	public function getDepth() {
		if(is_null($this->depth)) {
			$this->depth = 0;
			$name = $this->name;
			while($name[$this->depth] === '-') $this->depth++;
		}
		return $this->depth;		
	}
	
	public function setDepth($depth) {
		$name = $this->name;
		$_name = $name;
		$name = ltrim($name, '-');
		if($depth > 0) $name = str_repeat('-', $depth) . $name;
		if($name !== $_name) $this->name = $name;
	}

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
}

