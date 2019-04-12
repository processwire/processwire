<?php namespace ProcessWire;

/**
 * ProcessWire Selectable Option Array, for FieldtypeOptions 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class SelectableOptionArray extends WireArray {

	/**
	 * Output formatting on or off
	 * 
	 * @var bool
	 * 
	 */
	protected $of = false;

	/**
	 * Page these options apply to, if applicable
	 * 
	 * @var Page|null
	 * 
	 */
	protected $page = null;

	/**
	 * Field these options apply to (always applicable)
	 * 
	 * @var Field|null
	 * 
	 */	
	protected $field = null;

	/**
	 * Set the these options live on
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Returns page these options are for, if applicable (NullPage otherwise)
	 * 
	 * @return NullPage|Page
	 * 
	 */
	public function getPage() {
		return $this->page ? $this->page : $this->wire('pages')->newNullPage();
	}

	/**
	 * Set the field these options are for
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field; 
	}
	
	/**
	 * Returns Field object these options are for
	 *
	 * @return null|Field
	 *
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Get or set output formatting mode
	 * 
	 * @param bool|null $of Omit to retrieve mode, or specify bool to set it
	 * @return bool Current mode. If also setting mode, returns previous mode. 
	 * 
	 */
	public function of($of = null) {
		$_of = $this->of; 
		if(is_null($of)) return $_of; 
		$this->of = $of ? true : false; 
		foreach($this as $option) $option->of($this->of); 
		return $_of; // whatever previous value was
	}
	
	/**
	 * Provide a default string rendering of these selectable options
	 * 
	 * For debugging or basic usage
	 * 
	 * @return string
	 * 
	 */
	public function render() {
		$of = $this->of(true);
		$out = '<ul><li>' . $this->implode('</li><li>', 'title') . '</li></ul>';
		if(!$of) $this->of(false); 
		return $out; 
	}

	/**
	 * Return string value of these options (pipe separated IDs)
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return $this->implode('|', 'id'); 
	}

	/**
	 * Enables this WireArray to behave like the first item (for getting properties)
	 * 
	 * @param string $property
	 * @return mixed|null
	 * 
	 */
	public function getProperty($property) {
		if(SelectableOption::isProperty($property)) {
			if($this->count()) {
				$option = $this->first();
				return $option->$property;
			}
			return null;
		}
		return parent::getProperty($property); 
	}

	/**
	 * Enables this WireArray to behave like the first item (for setting properties)
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return SelectableOption|SelectableOptionArray
	 *
	 */
	public function setProperty($property, $value) {
		if(SelectableOption::isProperty($property)) {
			if($this->count()) {
				$option = $this->first();
				return $option->set($property, $value);
			}
		}
		// note: there is no parent method for this one
		return $this; 
	}
	
	public function set($key, $value) {
		if(SelectableOption::isProperty($key)) return $this->setProperty($key, $value);
		return parent::set($key, $value); 
	}
	
	public function __set($key, $value) {
		// we must have both this and set() per behavior of WireArray::__set()
		// which throws exceptions if attempting to set a property
		if(SelectableOption::isProperty($key)) return $this->setProperty($key, $value); 
		return parent::__set($key, $value); 
	}
	
	public function isValidItem($item) {
		return $item instanceof SelectableOption;
	}

	public function isValidKey($key) {
		return is_int($key);
	}

	public function getItemKey($item) {
		return $item->id ? $item->id : null;
	}

	public function makeBlankItem() {
		return $this->wire(new SelectableOption());
	}

	/**
	 * Is the given WireArray identical to this one?
	 *
	 * @param WireArray $items
	 * @param bool|int $strict
	 * @return bool
	 *
	 */
	public function isIdentical(WireArray $items, $strict = true) {
		$isIdentical = parent::isIdentical($items, false); // force non-strict
		if($isIdentical && $strict) {
			if($this->of() != $items->of()) $isIdentical = false;
			if($isIdentical && ((string) $this->getPage()) !== ((string) $items->getPage())) $isIdentical = false;
			if($isIdentical && ((string) $this->getField()) !== ((string) $items->getField())) $isIdentical = false;
		}
		return $isIdentical;
	}

	/**
	 * Get SelectableOption by $property matching $value, or boolean false if not found
	 * 
	 * @param string $property May be "title", "value" or "id"
	 * @param string|int $value
	 * @return bool|SelectableOption
	 * 
	 */
	protected function getOptionByProperty($property, $value) {
		$match = false;
		foreach($this as $option) {
			$v = $option->getProperty($property);
			if($v !== $value) continue;
			$match = $option;
			break;
		}
		return $match;
	}

	/**
	 * Is the given value present in these selectable options? 
	 * 
	 * @param string $value
	 * @return SelectableOption|bool Returns SelectableOption if found, or boolean false if not
	 * 
	 */
	public function hasValue($value) {
		return $this->getOptionByProperty('value', $value);
	}

	/**
	 * Is the given title present in these selectable options?
	 * 
	 * @param string $title
	 * @return SelectableOption|bool Returns SelectableOption if found, or boolean false if not
	 * 
	 */
	public function hasTitle($title) {
		return $this->getOptionByProperty('title', $title); 
	}

	/**
	 * Is the given id present in these selectable options?
	 *
	 * @param int $id
	 * @return SelectableOption|bool Returns SelectableOption if found, or boolean false if not
	 *
	 */
	public function hasID($id) {
		return $this->getOptionByProperty('id', (int) $id);
	}
	
	/**
	 * Return debug info for one item from this WireArray
	 *
	 * #pw-internal
	 *
	 * @param mixed $item
	 * @return mixed|null|string
	 *
	 */
	public function debugInfoItem($item) {
		if($item instanceof SelectableOption) $item = $item->debugInfoSmall();
		return $item;
	}
	
	public function __debugInfo() {
		$info = parent::__debugInfo();
		$info['of'] = (int) $this->of;
		if($this->page) $info['page'] = $this->page->path();
		if($this->field) $info['field'] = $this->field->name;
		return $info;
	}


}
