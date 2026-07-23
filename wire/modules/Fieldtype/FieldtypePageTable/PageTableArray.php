<?php namespace ProcessWire;

/**
 * PageTableArray
 *
 * PageArray for Page Table fields
 * 
 * @since 3.0.258
 * 
 */
class PageTableArray extends PageArray {

	/**
	 * @var Page|null 
	 * 
	 */
	protected $forPage = null;

	/**
	 * @var PageTableField|null 
	 * 
	 */
	protected $forField = null;

	/**
	 * Set the field that owns this PageTableArray
	 * 
	 * @param Field $field
	 * 
	 */
	public function setForField(Field $field) {
		$this->forField = $field;
	}

	/**
	 * Get the field that owns this PageTableArray
	 *
	 * @return PageTableField|null
	 *
	 */
	public function getForField() {
		return $this->forField;
	}

	/**
	 * Set the page that owns this PageTableArray
	 * 
	 * @param Page $page
	 * 
	 */
	public function setForPage(Page $page) {
		$this->forPage = $page; 
	}

	/**
	 * Get the page that owns this PageTableArray
	 * 
	 * @return Page|null
	 * 
	 */
	public function getForPage() {
		return $this->forPage;
	}
	
	/**
	 * Get new/blank PageTable item for this field and page
	 * 
	 * This also adds the new item to this PageArray. 
	 * 
	 * - Usage: `$item = $page->page_table_field->getNewItem();`
	 * - Execute `$item->save()` after making changes to item. 
	 * - Execute `$page->save('page_table_field')` to save new item to page table.
	 *
	 * ~~~~~
	 * $value = $page->page_table_field; // PageTableArray
	 * $item = $value->getNewItem(); // Page
	 * $item->title = 'Hello world';
	 * $item->save();
	 * $page->save('page_table_field');
	 * ~~~~~
	 *
	 * @return Page
	 * @since 3.0.258
	 *
	 */
	public function getNewItem() {
		if(!$this->forPage) {
			throw new WireException("forPage is not set");
		}
		if(!$this->forField) {
			throw new WireException("forField is not set");
		}
		
		$field = $this->forField;
		$page = $this->forPage;
		
		$templateId = $field->template_id;
		$parentId = $field->parent_id ? $field->parent_id : $page->id;
		if(is_array($templateId)) $templateId = reset($templateId);
		
		$item = $this->wire()->pages->newPage([
			'template' => $templateId,
			'parent' => $parentId,
		]);
		
		$this->add($item);
		
		return $item;
	}
	
	/**
	 * Is given item valid for this PageTableArray?
	 * 
	 * Note: this method will return true when forField/forPage
	 * have not yet been assigned to this PageTableArray, or if
	 * given Page ($item) does not yet have a template or parent.
	 *
	 * @param Page $item Item to validate
	 * @return bool True if item is valid for this PageTableArray, false if not
	 *
	 */
	public function isValidItem($item) {
		if(!parent::isValidItem($item)) return false;

		/** @var Page $item */
		/** @var PageTableField $field */
		$field = $this->forField;
		$page = $this->forPage;
		
		// if no forField or forPage specified yet, not enough info to validate 
		if(!$field || !$page || !$page->id) return true; 
		
		if($item->templates_id && $field->template_id) {
			// verify that item has one of the required templates
			$templateIds = $field->template_id;
			if(!is_array($templateIds)) $templateIds = [(int) $templateIds];
			if(!in_array($item->templates_id, $templateIds)) return false;
		}
		
		// item does not yet have a parent so cannot be validated just yet
		if(!$item->parent_id) return true;
	
		if($field->parent_id) {
			// specific parent required
			if($item->parent_id != $field->parent_id) return false;
		}
	
		return true;
	}
}
