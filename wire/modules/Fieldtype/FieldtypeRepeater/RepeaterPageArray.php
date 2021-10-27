<?php namespace ProcessWire;

/**
 * ProcessWire Repeater Page Array 
 *
 * Special PageArray for use by repeaters that includes a getNewItem() method
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
 * https://processwire.com
 *
 */

class RepeaterPageArray extends PageArray {

	/**
	 * The page that contains the repeater field (not the parent in the repeaters structure)
	 * 
	 * @var Page
	 *
	 */ 
	protected $forPage = null;

	/**
	 * The repeater field (from $this->fields API var)
	 * 
	 * @var Field
	 *
	 */
	protected $field = null;

	/**
	 * Construct
	 *
	 * @param Page $parent
	 * @param Field $field
	 * 
	 */
	public function __construct(Page $parent, Field $field) {
		$this->setForPage($parent);
		$this->setForField($field); 
		parent::__construct();
	}

	/**
	 * Set parent
	 * 
	 * @param Page $parent
	 * @deprecated use setForPage() instead
	 * 
	 */
	public function setParent(Page $parent) { $this->forPage = $parent; }

	/**
	 * Set for page
	 *
	 * @param Page $forPage
	 * @since 3.0.188
	 *
	 */
	public function setForPage(Page $forPage) { $this->forPage = $forPage; }

	/**
	 * Get parent
	 * 
	 * @return Page
	 * @deprecated use getForPage() insteada
	 * 
	 */
	public function getParent() { return $this->forPage; }

	/**
	 * Get for page
	 * 
	 * @return Page
	 * @since 3.0.188
	 * 
	 */
	public function getForPage() { return $this->forPage; }
	
	/**
	 * Set field
	 *
	 * @param Field $field
	 * @since 3.0.188
	 *
	 */
	public function setForField(Field $field) { $this->field = $field; }

	/**
	 * Set field (alias of setForField)
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) { $this->field = $field; }

	/**
	 * Get field
	 * 
	 * @return Field
	 * @since 3.0.188
	 * 
	 */
	public function getForField() {
		return $this->field;
	}
	
	/**
	 * Get field (alias of getForField)
	 *
	 * @return Field
	 *
	 */
	public function getField() { return $this->field; }

	/**
	 * Alias of getNewItem() kept for backwards compatibility
	 * 
	 * @return Page
	 *
	 */
	public function getNew() { return $this->getNewItem(); }

	/**
	 * Return a new repeater item ready for use
	 *
 	 * If there are ready items, it will return the first ready item
	 * Otherwise it'll create a new item
	 *
 	 * This method is different from FieldtypeRepeater::getBlankRepeaterPage: 
	 * 1. It returns an already existing readyPage, if it exists (otherwise it creates a new page)
	 * 2. The returned page is in a non-hidden published state, so will appear as soon as it is saved
	 *
	 * Note that this method has no relation/similarity to the makeNew() method.
	 *
	 * @return Page
	 *
	 */
	public function getNewItem() {
		/** @var FieldtypeRepeater $fieldtype */
		$fieldtype = $this->field->type;
		$page = null;
		$of = $this->forPage->of(false); 

		// first try to get a ready item, if available
		foreach($this as $item) {
			if($item->isUnpublished() && $item->isHidden()) {
				$page = $item;
				break;
			}
		}

		if(is_null($page)) { 
			// no ready item available, get a new one
			$page = $fieldtype->getBlankRepeaterPage($this->forPage, $this->field); 
			$this->add($page);
		} else {
			$this->trackChange('add');
		}

		$page->of(false);
		$page->removeStatus(Page::statusUnpublished); 
		$page->removeStatus(Page::statusHidden); 
		$page->sort = $this->count();

		if($of) $this->forPage->of(true);

		return $page;
	}

	/**
	 * Creates a new blank instance of a RepeaterPageArray. For internal use. 
	 * 
	 * Note that this method has no relation/similarity to the getNewItem()/getNew() methods.
	 *
	 * @return WireArray
	 *
	 */
	public function makeNew() {
		$class = get_class($this);
		$newArray = $this->wire(new $class($this->forPage, $this->field));
		return $newArray;
	}
	
	/**
	 * Track an item added
	 *
	 * @param Wire|mixed $item
	 * @param int|string $key
	 *
	 */
	protected function trackAdd($item, $key) {
		/** @var RepeaterPage $item */
		$item->traversalPages($this);
		parent::trackAdd($item, $key);
	}

	/**
	 * Track an item removed
	 *
	 * @param Wire|mixed $item
	 * @param int|string $key
	 *
	 */
	protected function trackRemove($item, $key) {
		/** @var RepeaterPage $item */
		if($item->traversalPages() === $this) $item->traversalPages(false);
		parent::trackRemove($item, $key);
	}

	/**
	 * Get direct property
	 * 
	 * @param int|string $key
	 * @return bool|mixed|Page|Wire|WireData
	 * 
	 */
	public function __get($key) {
		if($key === 'parent') return $this->getForPage();
		return parent::__get($key);
	}

	/**
	 * Debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		$info = array(
			'field' => $this->field ? $this->field->debugInfoSmall() : '', 
		);
		if($this->forPage && $this->forPage->id) {
			$info['forPage'] = $this->forPage->debugInfoSmall(); 
		}
		$info = array_merge($info, parent::__debugInfo());
		return $info;
	}
	
}

