<?php namespace ProcessWire;

/**
 * ProcessWire PageArray
 *
 * PageArray provides an array-like means for storing PageReferences and is utilized throughout ProcessWire. 
 * 
 * #pw-summary PageArray is a paginated type of WireArray that holds multiple Page objects. 
 * #pw-body =
 * **Please see the `WireArray` and `PaginatedArray` types for available methods**, as they are not 
 * repeated here, except where PageArray has modified or extended those types in some manner.
 * The PageArray type is functionally identical to WireArray and PaginatedArray except that it is focused
 * specifically on managing Page objects. 
 * 
 * PageArray is returned by all API methods in ProcessWire that can return more than one page at once. 
 * `$pages->find()` and `$page->children()` are common examples. 
 * 
 * The recommended way to create a new PageArray is to use the `$pages->newPageArray()` method: 
 * ~~~~~
 * $pageArray = $pages->newPageArray();
 * ~~~~~
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @method string getMarkup($key = null) Render a simple/default markup value for each item #pw-internal
 * @property Page|null $first First item
 * @property Page|null $last Last item
 * @property Page[] $data #pw-internal
 *
 */

class PageArray extends PaginatedArray implements WirePaginatable {

	/**
	 * Reference to the selectors that led to this PageArray, if applicable
	 *
	 * @var Selectors
	 *
	 */
	protected $selectors = null;

	/**
	 * Options that were passed to $pages->find() that led to this PageArray, when applicable.
	 * 
	 * Applies only for lazy loading result sets.
	 * 
	 * @var array
	 * 
	 */
	protected $finderOptions = array();

	/**
	 * Is this a lazy-loaded PageArray?
	 * 
	 * @var bool
	 * 
	 */
	protected $lazyLoad = false;

	/**
	 * Index of item keys of page_id => data key
	 * 
	 * @var array
	 * 
	 */
	protected $keyIndex = array();

	/**
	 * Template mehod that descendant classes may use to validate items added to this WireArray
	 * 
	 * #pw-internal
	 *
	 * @param mixed $item Item to add
	 * @return bool True if item is valid and may be added, false if not
	 *
	 */
	public function isValidItem($item) {
		return is_object($item) && $item instanceof Page; 
	}

	/**
	 * Validate the key used to add a Page
	 *
	 * PageArrays are keyed by an incremental number that does NOT relate to the Page ID.
	 * 
	 * #pw-internal
	 *
	 * @param string|int $key
	 * @return bool True if key is valid and may be used, false if not
	 *
	 */
	public function isValidKey($key) {
		return ctype_digit("$key");
	}

	/**
	 * Get the array key for the given Page item
	 *
	 * This method is used internally by the add() and prepend() methods.
	 *
	 * #pw-internal
	 *
	 * @param Page $item Page to get key for
	 * @return string|int|null Found key, or null if not found.
	 *
	 */
	public function getItemKey($item) {
		if(!$item instanceof Page) return null;	
		if(!$this->duplicateChecking) return parent::getItemKey($item);
		
		// first see if we can determine key from our index
		$id = $item->id;
		if(isset($this->keyIndex[$id])) {
			// given item exists in this PageArray (or at least has)
			$key = $this->keyIndex[$id];
			if(isset($this->data[$key])) {
				$page = $this->data[$key];
				if($page->id === $id) {
					// found it
					return $key; 
				}
			}
			// if this point is reached, then index needs to be rebuilt
			// because either item is no longer here, or has moved
			$this->keyIndex = array();
			foreach($this->data as $key => $page) {
				$this->keyIndex[$page->id] = $key;
			}
			return isset($this->keyIndex[$id]) ? $this->keyIndex[$id] : null;
		} else {
			// page is not present here
			return null;
		}
	}

	/**
	 * Does this PageArray use numeric keys only? (yes it does)
	 * 
	 * Defined here to override the slower check in WireArray
	 * 
	 * @return bool
	 *
	 */
	protected function usesNumericKeys() {
		return true;
	}

	/**
	 * Per WireArray interface, return a blank Page
	 * 
	 * #pw-internal
	 * 
	 * @return Page
	 *
	 */
	public function makeBlankItem() {
		return $this->wire('pages')->newPage();
	}

	/**
	 * Import the provided pages into this PageArray.
	 * 
	 * #pw-internal
	 * 
	 * @param array|PageArray|Page $pages Pages to import. 
	 * @return PageArray reference to current instance. 
	 *
	 */
	public function import($pages) {
		if(is_object($pages) && $pages instanceof Page) $pages = array($pages); 
		if(!self::iterable($pages)) return $this; 
		foreach($pages as $page) $this->add($page); 
		if($pages instanceof PageArray) {
			if(count($pages) < $pages->getTotal()) $this->setTotal($this->getTotal() + ($pages->getTotal() - count($pages))); 
		}
		return $this;
	}

	/*
	public function get($key) {
		if(ctype_digit("$key")) return parent::get($key); 
		@todo check if selector, then call findOne(). If it returns null, return a NullPage instead. 
		return null;
	}
	*/

	/**
	 * Does this PageArray contain the given index or Page?
	 * 
	 * #pw-internal
	 *
	 * @param Page|int $key Page Array index or Page object. 
	 * @return bool True if the index or Page exists here, false if not. 
	 */  
	public function has($key) {
		if(is_object($key) && $key instanceof Page) {
			return $this->getItemKey($key) !== null;
		}
		return parent::has($key); 
	}


	/**
	 * Add one or more Page objects to this PageArray.
	 * 
	 * Please see the `WireArray::add()` method for more details. 
	 * 
	 * ~~~~~
	 * // Add one page
	 * $pageArray->add($page); 
	 *
	 * // Add multiple pages 
	 * $pageArray->add($pages->find("template=basic-page")); 
	 * 
	 * // Add page by ID
	 * $pageArray->add(1005); 
	 * ~~~~~
	 *
	 * @param Page|PageArray|int $page Page object, PageArray object, or Page ID. 
	 *  - If given a `Page`, the Page will be added. 
	 *  - If given a `PageArray`, it will do the same thing as the `WireArray::import()` method and append all the pages. 
	 *  - If Page `ID`, the Page identified by that ID will be loaded and added to the PageArray. 
	 * @return $this
	 */
	public function add($page) {

		if($this->isValidItem($page)) {
			parent::add($page); 
			$this->numTotal++;

		} else if($page instanceof PageArray || is_array($page)) {
			return $this->import($page);

		} else if(ctype_digit("$page")) {
			$page = $this->wire('pages')->get("id=$page");
			if($page->id) {
				parent::add($page); 
				$this->numTotal++;
			}
		}
		return $this;
	}


	/**
	 * Sets an index in the PageArray.
	 * 
	 * #pw-internal
	 *
	 * @param int $key Key of item to set.
	 * @param Page $value Value of item. 
	 * @return $this
	 * 
	 */
	public function set($key, $value) {
		$has = $this->has($key); 
		parent::set($key, $value); 
		if(!$has) $this->numTotal++;
		return $this; 
	}

	/**
	 * Prepend a Page to the beginning of the PageArray. 
	 * 
	 * #pw-internal
	 *
	 * @param Page|PageArray $item 
	 * @return WireArray This instance.
	 * 
	 */
	public function prepend($item) {
		parent::prepend($item);
		// note that WireArray::prepend does a recursive call to prepend with each item,
		// so it's only necessary to increase numTotal if the given item is Page (vs. PageArray)
		if($item instanceof Page) $this->numTotal++; 
		return $this; 
	}


	/**
	 * Remove the given Page or key from the PageArray. 
	 * 
	 * #pw-internal
	 * 
	 * @param int|Page $key
	 * @return $this This PageArray instance
	 * 
	 */
	public function remove($key) {

		// if a Page object has been passed, determine its key
		if($this->isValidItem($key)) {
			$key = $this->getItemKey($key);
		} 
		if($this->has($key)) {
			parent::remove($key);
			$this->numTotal--;
		}

		return $this; 
	}

	/**
	 * Shift the first Page off of the PageArray and return it. 
	 * 
	 * #pw-internal
	 * 
	 * @return Page|NULL
	 * 
	 */
	public function shift() {
		if($this->numTotal) $this->numTotal--; 
		return parent::shift(); 
	}

	/**
	 * Pop the last page off of the PageArray and return it. 
	 * 
	 * #pw-internal
	 *
	 * @return Page|NULL 
	 * 
	 */ 
	public function pop() {
		if($this->numTotal) $this->numTotal--; 
		return parent::pop();
	}

	/**
	 * Get one or more random pages from this PageArray.
	 *
	 * If one item is requested, the item is returned (unless $alwaysArray is true).
	 * If multiple items are requested, a new WireArray of those items is returned.
	 * 
	 * #pw-internal
	 *
	 * @param int $num Number of items to return. Optional and defaults to 1.
	 * @param bool $alwaysArray If true, then method will always return a container of items, even if it only contains 1.
	 * @return Page|PageArray Returns value of item, or new PageArray of items if more than one requested.
	 */
	public function getRandom($num = 1, $alwaysArray = false) {
		return parent::getRandom($num, $alwaysArray);
	}

	/**
	 * Get a quantity of random pages from this PageArray.
	 *
	 * Unlike getRandom() this one always returns a PageArray (or derived type).
	 * 
	 * #pw-internal
	 *
	 * @param int $num Number of items to return
	 * @return PageArray|WireArray New PageArray instance
	 *
	 */
	public function findRandom($num) {
		return parent::findRandom($num);
	}

	/**
	 * Get a slice of the PageArray.
	 *
	 * Given a starting point and a number of items, returns a new PageArray of those items.
	 * If $limit is omitted, then it includes everything beyond the starting point.
	 * 
	 * #pw-internal
	 *
	 * @param int $start Starting index.
	 * @param int $limit Number of items to include. If omitted, includes the rest of the array.
	 * @return PageArray|WireArray New PageArray instance
	 *
	 */
	public function slice($start, $limit = 0) {
		return parent::slice($start, $limit);
	}

	/**
	 * Returns the item at the given index starting from 0, or NULL if it doesn't exist.
	 *
	 * Unlike the index() method, this returns an actual item and not another PageArray.
	 * 
	 * #pw-internal
	 *
	 * @param int $num Return the nth item in this WireArray. Specify a negative number to count from the end rather than the start.
	 * @return Page|Wire|null Returns Page object or null if not present
	 *
	 */
	public function eq($num) {
		return parent::eq($num);
	}

	/**
	 * Returns the first item in the PageArray or boolean FALSE if empty.
	 * 
	 * #pw-internal
	 *
	 * @return Page|bool
	 *
	 */
	public function first() {
		return parent::first();
	}

	/**
	 * Returns the last item in the PageArray or boolean FALSE if empty.
	 * 
	 * #pw-internal
	 *
	 * @return Page|bool
	 *
	 */
	public function last() {
		return parent::last();
	}

	/**
	 * Set the Selectors that led to this PageArray, if applicable
	 * 
	 * #pw-internal
	 *
	 * @param Selectors $selectors
	 * @return $this
	 *
	 */
	public function setSelectors(Selectors $selectors) {
		$this->selectors = $selectors; 
		return $this;
	}

	/**
	 * Return the Selectors that led to this PageArray, or null if not set/applicable.
	 * 
	 * Use this to retrieve the Selectors that were used to find this group of pages, 
	 * if dealing with a PageArray that originated from a database operation. 
	 * 
	 * ~~~~~
	 * $products = $pages->find("template=product, featured=1, sort=-modified, limit=10"); 
	 * echo $products->getSelectors(); // outputs the selector above
	 * ~~~~~
	 * 
	 * @return Selectors|null Returns Selectors object if available, or null if not. 
	 *
	 */
	public function getSelectors() {
		return $this->selectors; 
	}

	/**
	 * Filter out Pages that don't match the selector. 
	 * 
	 * This is applicable to and destructive to the WireArray.
	 *
	 * @param string|Selectors|array $selectors AttributeSelector string to use as the filter.
	 * @param bool|int $not Make this a "not" filter? Use int 1 for "not all". (default is false)
	 * @return PageArray|WireArray reference to current [filtered] PageArray
	 *
	 */
	protected function filterData($selectors, $not = false) {
		if(is_string($selectors) && $selectors[0] === '/') $selectors = "path=$selectors";
		return parent::filterData($selectors, $not); 
	}

	/**
	 * Filter out pages that don't match the selector (destructive)
	 * 
	 * #pw-internal
	 *
	 * @param string $selector AttributeSelector string to use as the filter.
	 * @return PageArray|PaginatedArray|WireArray reference to current PageArray instance.
	 *
	 */
	public function filter($selector) {
		return parent::filter($selector);
	}

	/**
	 * Filter out pages that don't match the selector (destructive)
	 * 
	 * #pw-internal
	 *
	 * @param string $selector AttributeSelector string to use as the filter.
	 * @return PageArray|PaginatedArray|WireArray reference to current PageArray instance.
	 *
	 */
	public function not($selector) {
		return parent::not($selector);
	}

	/**
	 * Find all pages in this PageArray that match the given selector (non-destructive)
	 *
	 * This is non destructive and returns a brand new PageArray.
	 * 
	 * #pw-internal
	 *
	 * @param string $selector AttributeSelector string.
	 * @return PageArray|WireArray New PageArray instance
	 *
	 */
	public function find($selector) {
		return parent::find($selector);
	}

	/**
	 * Same as find, but returns a single Page rather than PageArray or FALSE if empty.
	 * 
	 * #pw-internal
	 *
	 * @param string $selector
	 * @return Page|bool
	 *
	 */
	public function findOne($selector) {
		return parent::findOne($selector);
	}

	/**
	 * Prepare selectors for filtering
	 *
	 * Template method for descending classes to modify selectors if needed
	 *
	 * @param Selectors $selectors
	 *
	 */
	protected function filterDataSelectors(Selectors $selectors) { 
		// @todo make it remove references to include= statements since not applicable in-memory
		parent::filterDataSelectors($selectors);
	}

	/**
	 * Get the value of $property from $item
	 *
	 * Used by the WireArray::sort method to retrieve a value from a Wire object. 
	 * If output formatting is on, we turn it off to ensure that the sorting
	 * is performed without output formatting.
	 *
	 * @param Wire $item
	 * @param string $property
	 * @return mixed
	 *
	 */
	protected function getItemPropertyValue(Wire $item, $property) {

		if($item instanceof Page) {
			$value = $item->getUnformatted($property); 
		} else if(strpos($property, '.') !== false) {
			$value = WireData::_getDot($property, $item);
		} else if($item instanceof WireArray) {
			/** @var PageArray $item */
			$value = $item->getProperty($property); 
			if(is_null($value)) {
				$value = $item->first();
				$value = $this->getItemPropertyValue($value, $property);
			}
		} else {
			$value = $item->$property;
		}

		if(is_array($value)) $value = implode('|', $value); 

		return $value;
	}

	/**
	 * Allows iteration of the PageArray.
	 * 
	 * #pw-internal
	 *
	 * @return Page[]|\ArrayObject|PageArrayIterator
	 *
	 */
	public function getIterator() {
		if($this->lazyLoad) return new PageArrayIterator($this->data, $this->finderOptions);	
		return parent::getIterator();
	}

	/**
	 * PageArrays always return a string of the Page IDs separated by pipe "|" characters
	 *
	 * Pipe charactesr are used for compatibility with Selector OR statements
	 *
	 */
	public function __toString() {
		$s = '';
		foreach($this as $key => $page) $s .= "$page|";
		$s = rtrim($s, "|"); 
		return $s; 
	}

	/**
	 * Render a simple/default markup value for each item in this PageArray.
	 * 
	 * For testing/debugging purposes.
	 * 
	 * #pw-internal
	 * 
	 * @param string|callable $key
	 * @return string
	 * 
	 */
	public function ___getMarkup($key = null) {
		if($key && !is_string($key)) {
			$out = $this->each($key);
		} else if(strpos($key, '{') !== false && strpos($key, '}')) {
			$out = $this->each($key);
		} else {
			if(empty($key)) $key = "<li>{title|name}</li>";
			$out = $this->each($key);
			if($out) {
				$out = "<ul>$out</ul>";
				if($this->getLimit() && $this->getTotal() > $this->getLimit()) {
					$pager = $this->wire('modules')->get('MarkupPagerNav');
					$out .= $pager->render($this);
				}
			}
		}
		return $out; 
	}


	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		$info['selectors'] = (string) $this->selectors; 
		if(!wireCount($info['selectors'])) unset($info['selectors']);
		return $info;
	}

	/**
	 * Get or set $options array used by $pages->find() for this PageArray
	 * 
	 * #pw-internal
	 * 
	 * @param array $options
	 * @return array
	 * 
	 */
	public function finderOptions(array $options = array()) {
		$this->finderOptions = $options;
		return $this->finderOptions;
	}

	/**
	 * Get or set Lazy loading state of this PageArray
	 * 
	 * #pw-internal
	 * 
	 * @param bool|null $lazy
	 * @return bool
	 * 
	 */
	public function _lazy($lazy = null) {
		if(is_bool($lazy)) $this->lazyLoad = $lazy;
		return $this->lazyLoad;
	}

	/**
	 * Track an item added
	 *
	 * @param Wire|mixed $item
	 * @param int|string $key 
	 *
	 */
	protected function trackAdd($item, $key) {
		parent::trackAdd($item, $key);
		$this->keyIndex[$item->id] = $key;
	}

	/**
	 * Track an item removed
	 *
	 * @param Wire|mixed $item
	 * @param int|string $key
	 *
	 */
	protected function trackRemove($item, $key) {
		parent::trackRemove($item, $key);
		unset($this->keyIndex[$item->id]);
	}
}


