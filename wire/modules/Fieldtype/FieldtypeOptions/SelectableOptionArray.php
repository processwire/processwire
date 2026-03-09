<?php namespace ProcessWire;

/**
 * ProcessWire Selectable Option Array, for FieldtypeOptions 
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * @property string $title
 * @property string $value
 * @property int $id
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
		return $this->page ? $this->page : $this->wire()->pages->newNullPage();
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
		foreach($this as $option) {
			/** @var SelectableOption $option */
			$option->of($this->of);
		}
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
				/** @var SelectableOption|null $option */
				$option = $this->first();
				if($option) return $option->set($property, $value);
			}
		}
		// note: there is no parent method for this one
		return $this; 
	}
	
	public function set($key, $value) {
		if(SelectableOption::isProperty($key)) return $this->setProperty($key, $value);
		return parent::set($key, $value); 
	}
	
	public function __set($property, $value) {
		// we must have both this and set() per behavior of WireArray::__set()
		// which throws exceptions if attempting to set a property
		if(SelectableOption::isProperty($property)) {
			$this->setProperty($property, $value);
		} else {
			parent::__set($property, $value);
		}
	}
	
	public function isValidItem($item) {
		return $item instanceof SelectableOption;
	}

	public function isValidKey($key) {
		return is_int($key);
	}

	public function getItemKey($item) {
		/** @var SelectableOption $item */
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
		/** @var SelectableOptionArray $items */
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
	 * @param bool|null $noValue Value to return if option not present (default=false)
	 * @return bool|null|SelectableOption
	 * 
	 */
	protected function getByProperty($property, $value, $noValue = false) {
		$match = $noValue;
		foreach($this as $option) {
			/** @var SelectableOption $option */
			$v = $option->getProperty($property);
			if("$v" !== "$value") continue;
			$match = $option;
			break;
		}
		return $match;
	}

	/**
	 * Alias of getByProperty
	 * 
	 * Was renamed to getByProperty() but old method name kept in case this class is extended anywhere
	 * 
	 * @param string $property
	 * @param string|int $value
	 * @return bool|SelectableOption
	 * @deprecated 
	 * 
	 */
	protected function getOptionByProperty($property, $value) {
		return $this->getByProperty($property, $value);
	}

	/**
	 * Add option by property (id, name, title) 
	 * 
	 * @param string $property One of id, name or title
	 * @param string|int $value Value to match for above property
	 * @return SelectableOption|false Returns option added or false if not found
	 * @throws WireException
	 * 
	 */
	protected function addByProperty($property, $value) {
		if(!$this->field) {
			throw new WireException("Cannot add by '$property' without \$field");
		}
		$fieldtype = $this->field->type; /** @var FieldtypeOptions $fieldtype */
		$options = $fieldtype->manager->getOptions($this->field, array($property => $value)); 
		$option = $options->first();
		if(!$option) return false;
		$this->add($option);
		return $option;
	}

	/**
	 * Add by option ID
	 * 
	 * @param int $id
	 * @return false|SelectableOption Returns option added on success or false on fail
	 * @throws WireException
	 * 
	 */
	public function addByID($id) {
		return $this->addByProperty('id', $id); 
	}
	
	/**
	 * Add by option value
	 *
	 * @param string $value
	 * @return false|SelectableOption Returns option added on success or false on fail
	 * @throws WireException
	 *
	 */
	public function addByValue($value) {
		return $this->addByProperty('value', $value);
	}
	
	/**
	 * Add by option title
	 *
	 * @param string $title
	 * @return false|SelectableOption Returns option added on success or false on fail
	 * @throws WireException
	 *
	 */
	public function addByTitle($title) {
		return $this->addByProperty('title', $title);
	}

	/**
	 * Get option by ID
	 *
	 * @param int $id
	 * @return SelectableOption|null
	 * @since 3.0.242
	 *
	 */
	public function getByID($id) {
		return $this->getByProperty('id', $id, null);
	}

	/**
	 * Get option by value
	 *
	 * @param string $value
	 * @return SelectableOption|null
	 * @since 3.0.242
	 *
	 */
	public function getByValue($value) {
		return $this->getByProperty('value', $value, null);
	}

	/**
	 * Get option by title
	 *
	 * @param string $title
	 * @return SelectableOption|null
	 * @since 3.0.242
	 *
	 */
	public function getByTitle($title) {
		return $this->getByProperty('title', $title, null);
	}
	
	/**
	 * Remove item by property (value, title, id)
	 *
	 * @param string $property
	 * @param string|int $value
	 * @return bool
	 * @since 3.0.242
	 *
	 */
	protected function removeByProperty($property, $value) {
		$option = $this->getByProperty($property, $value);
		if(!$option) return false;
		$this->remove($option);
		return true;
	}

	/**
	 * Remove option by ID
	 *
	 * @param int $id
	 * @return bool
	 * @since 3.0.242
	 *
	 */
	public function removeByID($id) {
		return $this->removeByProperty('id', $id);
	}

	/**
	 * Remove option by value
	 *
	 * @param string $value
	 * @return bool
	 * @since 3.0.242
	 *
	 */
	public function removeByValue($value) {
		return $this->removeByProperty('value', $value);
	}

	/**
	 * Remove option by title
	 *
	 * @param string $title
	 * @return bool
	 * @since 3.0.242
	 *
	 */
	public function removeByTitle($title) {
		return $this->removeByProperty('title', $title);
	}

	/**
	 * Is the given value present in these selectable options? 
	 * 
	 * @param string $value
	 * @return SelectableOption|bool Returns SelectableOption if found, or boolean false if not
	 * 
	 */
	public function hasValue($value) {
		return $this->getByProperty('value', $value);
	}

	/**
	 * Is the given title present in these selectable options?
	 * 
	 * @param string $title
	 * @return SelectableOption|bool Returns SelectableOption if found, or boolean false if not
	 * 
	 */
	public function hasTitle($title) {
		return $this->getByProperty('title', $title); 
	}

	/**
	 * Is the given id present in these selectable options?
	 *
	 * @param int $id
	 * @return SelectableOption|bool Returns SelectableOption if found, or boolean false if not
	 *
	 */
	public function hasID($id) {
		return $this->getByProperty('id', (int) $id);
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
