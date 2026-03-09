<?php namespace ProcessWire;

/**
 * ProcessWire Repeater Page Array 
 *
 * Special PageArray for use by repeaters that includes a `getNewItem()` method
 * for adding new items to the repeater. 
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
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
	 * @var RepeaterField
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
	 * #pw-internal
	 * 
	 * @param Page $parent
	 * @deprecated use setForPage() instead
	 * 
	 */
	public function setParent(Page $parent) { $this->forPage = $parent; }

	/**
	 * Set page this RepeaterPageArray is for
	 *
	 * @param Page $forPage
	 * @since 3.0.188
	 *
	 */
	public function setForPage(Page $forPage) { $this->forPage = $forPage; }

	/**
	 * Get parent
	 * 
	 * #pw-internal
	 * 
	 * @return Page
	 * @deprecated use getForPage() instead
	 * 
	 */
	public function getParent() { return $this->forPage; }

	/**
	 * Get page this RepeaterPageArray is for
	 * 
	 * @return Page
	 * @since 3.0.188
	 * 
	 */
	public function getForPage() { return $this->forPage; }
	
	/**
	 * Set repeater field this RepeaterPageArray is for
	 *
	 * @param RepeaterField $field
	 * @since 3.0.188
	 *
	 */
	public function setForField(Field $field) { $this->field = $field; }

	/**
	 * Set field (alias of setForField)
	 * 
	 * #pw-internal
	 * 
	 * @param RepeaterField $field
	 * 
	 */
	public function setField(Field $field) { $this->field = $field; }

	/**
	 * Get repeater field this RepeaterPageArray is for
	 * 
	 * @return RepeaterField
	 * @since 3.0.188
	 * 
	 */
	public function getForField() {
		return $this->field;
	}
	
	/**
	 * Get field (alias of getForField)
	 * 
	 * #pw-internal
	 *
	 * @return Field
	 *
	 */
	public function getField() { return $this->field; }

	/**
	 * Alias of getNewItem() kept for backwards compatibility
	 * 
	 * #pw-internal
	 * 
	 * @return Page
	 *
	 */
	public function getNew() { return $this->getNewItem(); }

	/**
	 * Return a new repeater item ready for use
	 *
 	 * This method differs from `FieldtypeRepeater::getBlankRepeaterPage()` in the following ways: 
	 * 
	 * 1. It returns an already existing readyPage, if it exists (otherwise it creates a new page)
	 * 2. The returned page is in a non-hidden published state, so will appear as soon as it is saved.
	 * 3. It appends the new item to this RepeaterPageArray. 
	 *
	 * Please note: 
	 * 
	 * - This method has no relation/similarity to the `makeNew()` method.
	 * - After making changes to the returned item, you must still `$item->save()` the item.
	 * - When finished adding items you must `$page->save()` or `$page->save('repeater_field_name');` 
	 *   the page that has this repeater field. 
	 * - If previously added but unsaved items (aka "ready items") exist in the repeater, they will
	 *   be recycled and returned by this method rather than creating a new item.
	 * 
	 * ~~~~~
	 * $item = $page->repeater_field->getNewItem(); // get new repeater item 
	 * $item->title = 'My new item'; // set field value(s) as needed
	 * $item->save(); // save the item
	 * $page->save('repeater_field'); // save the page that has the repeater
	 * ~~~~~
	 *
	 * @return RepeaterPage
	 *
	 */
	public function getNewItem() {
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
			$page->sort = $this->count();
			$this->add($page);
		} else {
			$page->sort = $this->count();
			$this->trackChange('add');
		}

		$page->of(false);
		$page->removeStatus(Page::statusUnpublished); 
		$page->removeStatus(Page::statusHidden); 

		if($of) $this->forPage->of(true);
		
		$this->forPage->trackChange($this->field->name);

		return $page;
	}

	/**
	 * Creates a new blank instance of a RepeaterPageArray. For internal use. 
	 * 
	 * Note that this method has no relation/similarity to the getNewItem()/getNew() methods.
	 * 
	 * #pw-internal
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
	 * #pw-internal
	 * 
	 * @param int|string $name
	 * @return bool|mixed|Page|Wire|WireData
	 * 
	 */
	public function __get($name) {
		if($name === 'parent') return $this->getForPage();
		return parent::__get($name);
	}

	/**
	 * Debug info
	 * 
	 * #pw-internal
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
