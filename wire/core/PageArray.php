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
 * `$pages->find()` and `$page->children()` are common examples that return PageArray. 
 * 
 * You can create a new PageArray using any of the methods below: 
 * ~~~~~
 * // the most common way to create a new PageArray and add a $page to it
 * $a = new PageArray();
 * $a->add($page);
 * 
 * // ProcessWire 3.0.123+ can also create PageArray like this:
 * $a = PageArray(); // create blank 
 * $a = PageArray($page); // create + add one page
 * $a = PageArray([ $page1, $page2, $page3 ]); // create + add pages
 * ~~~~~
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
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
	 * @var Selectors|string
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
	 * Construct
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->indexedByName = false;
		$this->usesNumericKeys = true;
	}

	/**
	 * Template method that descendant classes may use to validate items added to this WireArray
	 * 
	 * #pw-internal
	 *
	 * @param mixed $item Item to add
	 * @return bool True if item is valid and may be added, false if not
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Page; 
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
			// if this (maybe unreachable) point is reached, then index needs to 
			// be rebuilt because either item is no longer here, or has moved 
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
	 * Per WireArray interface, return a blank Page
	 * 
	 * #pw-internal
	 * 
	 * @return Page
	 *
	 */
	public function makeBlankItem() {
		return $this->wire()->pages->newPage();
	}
	
	/**
	 * Creates a new blank instance of this PageArray, for internal use.
	 *
	 * #pw-internal
	 *
	 * @return PageArray
	 *
	 */
	public function makeNew() {
		$class = get_class($this);
		/** @var PageArray $newArray */
		$newArray = $this->wire(new $class());
		// $newArray->finderOptions($this->finderOptions());
		if($this->lazyLoad) $newArray->_lazy(true);
		return $newArray;
	}

	/**
	 * Import the provided pages into this PageArray.
	 * 
	 * #pw-internal
	 * 
	 * @param array|PageArray|Page $items Pages to import. 
	 * @return PageArray reference to current instance. 
	 *
	 */
	public function import($items) {
		if($items instanceof Page) $items = array($items); 
		if(!self::iterable($items)) return $this; 
		foreach($items as $page) $this->add($page); 
		if($items instanceof PageArray) {
			if(count($items) < $items->getTotal()) {
				$this->setTotal($this->getTotal() + ($items->getTotal() - count($items)));
			}
		}
		return $this;
	}

	/**
	 * Does this PageArray contain the given index or Page?
	 * 
	 * #pw-internal
	 *
	 * @param Page|int $key Page Array index or Page object. 
	 * @return bool True if the index or Page exists here, false if not. 
	 */  
	public function has($key) {
		if($key instanceof Page) {
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
	 * @param Page|PageArray|int $item Page object, PageArray object, or Page ID. 
	 *  - If given a `Page`, the Page will be added. 
	 *  - If given a `PageArray`, it will do the same thing as the `WireArray::import()` method and append all the pages. 
	 *  - If Page `ID`, the Page identified by that ID will be loaded and added to the PageArray. 
	 * @return $this
	 */
	public function add($item) {

		if($this->isValidItem($item)) {
			parent::add($item); 

		} else if($item instanceof PageArray || is_array($item)) {
			return $this->import($item);

		} else if(ctype_digit("$item")) {
			$item = $this->wire()->pages->get("id=$item");
			if($item->id) parent::add($item); 
		}
		
		return $this;
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
		/** @var PageArray $value */
		$value = parent::findRandom($num);
		return $value;
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
		/** @var PageArray $value */
		$value = parent::slice($start, $limit);
		return $value;
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
		/** @var Page $value */
		$value = parent::eq($num);
		return $value;
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
	 * @param Selectors|string $selectors Option to add as string added in 3.0.142
	 * @return $this
	 *
	 */
	public function setSelectors($selectors) {
		if(is_string($selectors) || $selectors instanceof Selectors || $selectors === null) {
			$this->selectors = $selectors;
		}
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
	 * @param bool $getString Specify true to get selector string rather than Selectors object (default=false) added in 3.0.142
	 * @return Selectors|string|null Returns Selectors object if available, or null if not. Always return string if $getString argument is true. 
	 *
	 */
	public function getSelectors($getString = false) {
		if($getString) return (string) $this->selectors;
		if($this->selectors === null) return null;
		if(is_string($this->selectors)) $this->selectors = $this->wire(new Selectors($this->selectors));
		return $this->selectors;
	}

	/**
	 * Filter out Pages that don't match the selector. 
	 * 
	 * This is applicable to and destructive to the WireArray.
	 *
	 * @param string|Selectors|array $selectors Selector string to use as the filter.
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
	 * @param string $selector Selector string to use as the filter.
	 * @return PageArray|PaginatedArray|WireArray reference to current PageArray instance.
	 *
	 */
	public function filter($selector) {
		return parent::filter($selector);
	}

	/**
	 * Filter out pages that DO match the selector (destructive)
	 * 
	 * #pw-internal
	 *
	 * @param string $selector Selector string to use 
	 * @return PageArray|PaginatedArray|WireArray reference to current PageArray instance.
	 *
	 */
	public function not($selector) {
		return parent::not($selector);
	}

	/**
	 * Like the base get() method but can only return Page objects (whether Page or NullPage)
	 * 
	 * @param int|string|array $key Provide any of the following:
	 *  - Key of Page to retrieve.
	 *  - A selector string or selector array, to return the first item that matches the selector.
	 *  - A string containing the "name" property of any Page, and the matching Page will be returned.
	 * @return Page|NullPage
	 * @since 3.0.162
	 * @see WireArray::get()
	 * 
	 */
	public function getPage($key) {
		$value = $this->get($key);
		return $value instanceof Page ? $value : $this->wire()->pages->newNullPage();
	}

	/**
	 * Find all pages in this PageArray that match the given selector (non-destructive)
	 *
	 * This is non destructive and returns a brand new PageArray.
	 * 
	 * #pw-internal
	 *
	 * @param string $selector Selector string.
	 * @return PageArray|WireArray New PageArray instance
	 * @see WireArray::find()
	 *
	 */
	public function find($selector) {
		/** @var PageArray $value */
		$value = parent::find($selector);
		return $value;
	}

	/**
	 * Same as find() method, but returns a single Page rather than PageArray or FALSE if empty.
	 * 
	 * #pw-internal
	 *
	 * @param string $selector
	 * @return Page|bool
	 * @see WireArray::findOne()
	 *
	 */
	public function findOne($selector) {
		/** @var Page|bool $value */
		$value = parent::findOne($selector);
		return $value;
	}

	/**
	 * Same as find() or findOne() methods, but always returns a Page (whether Page or NullPage)
	 *
	 * @param string $selector
	 * @return Page|NullPage
	 * @since 3.0.162
	 *
	 */
	public function findOnePage($selector) {
		$value = parent::findOne($selector);
		return $value instanceof Page ? $value : $this->wire()->pages->newNullPage();
	}

	/**
	 * Get Page from this PageArray having given name, or return NullPage if not present
	 * 
	 * @param string $name
	 * @return NullPage|Page
	 * @since 3.0.162
	 * 
	 */
	public function getPageByName($name) {
		return $this->getPageByProperty('name', $name, true); 
	}

	/**
	 * Get Page from this PageArray having given ID, or return NullPage if not present
	 * 
	 * @param int $id
	 * @return NullPage|Page
	 * @since 3.0.162
	 *
	 */
	public function getPageByID($id) {
		$id = (int) $id;
		if(isset($this->keyIndex[$id])) {
			$k = $this->keyIndex[$id];
			if(isset($this->data[$k]) && $this->data[$k]->id === $id) return $this->data[$k];
		}
		return $this->getPageByProperty('id', (int) $id, true);
	}
	
	/**
	 * Get first found Page object matching property/value, or return NullPage if not present in this PageArray
	 * 
	 * #pw-internal
	 *
	 * @param string $property Name of page property or field
	 * @param string|mixed $value Value to match 
	 * @param bool $strict Match value with strict type enforcement? (default=false)
	 * @return Page|NullPage
	 * @since 3.0.162
	 *
	 */
	public function getPageByProperty($property, $value, $strict = false) {
		$foundPage = null;
		foreach($this->data as $item) {
			if($strict) {
				if($item->get($property) === $value) $foundPage = $item;
			} else {
				if($item->get($property) == $value) $foundPage = $item;
			}
			if($foundPage) break;
		}
		return $foundPage ? $foundPage : $this->wire()->pages->newNullPage();
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
		$disallowed = array('include', 'check_access', 'checkAccess');
		foreach($selectors as $selector) {
			if(in_array($selector->field(), $disallowed)) {
				$selectors->remove($selector);
			}
		}
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
				if($value) $value = $this->getItemPropertyValue($value, $property);
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
	#[\ReturnTypeWillChange] 
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
		$ids = array();
		if($this->lazyLoad) {
			$items = $this;
		} else {
			$items = &$this->data;
		}
		foreach($items as $page) {
			if(!$page instanceof NullPage) $ids[] = $page->id;
		}
		return implode('|', $ids);
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
					/** @var MarkupPagerNav $pager */
					$pager = $this->wire()->modules->get('MarkupPagerNav');
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
	 * @param array|null $options Specify array to set or omit this argument to get
	 * @return array
	 * 
	 */
	public function finderOptions($options = null) {
		if(is_array($options)) $this->finderOptions = $options;
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
		if(!$item instanceof Page) return;
		if(!isset($this->keyIndex[$item->id])) $this->numTotal++;
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
		if(!$item instanceof Page) return;
		if(isset($this->keyIndex[$item->id])) {
			if($this->numTotal) $this->numTotal--;
			unset($this->keyIndex[$item->id]);
		}
	}
}
