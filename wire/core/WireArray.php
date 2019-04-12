<?php namespace ProcessWire;

/**
 * ProcessWire WireArray
 *
 * WireArray is the base array access object used in the ProcessWire framework.
 * 
 * Several methods are duplicated here for syntactical convenience and jQuery-like usability. 
 * Many methods act upon the array and return $this, which enables WireArrays to be used for fluent interfaces.
 * WireArray is the base of the PageArray (subclass) which is the most used instance. 
 *
 * @todo can we implement next() and prev() like on Page, as alias to getNext() and getPrev()?
 * @todo narrow down to one method of addition and removal, especially for removal, i.e. make shift() run through remove()
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @method WireArray and($item)
 * @method static WireArray new($items = array()) 
 * @property int $count Number of items
 * @property Wire|null $first First item
 * @property Wire|null $last Last item
 * @property array $keys All keys used in this WireArray
 * @property array $values All values used in this WireArray
 *
 * #pw-order-groups traversal,retrieval,manipulation,info,output-rendering,other-data-storage,changes,fun-tools,hooker
 * #pw-summary WireArray is the base iterable array type used throughout the ProcessWire framework.
 * 
 * #pw-body = 
 * **Nearly all collections of items in ProcessWire are derived from the WireArray type.** 
 * This includes collections of pages, fields, templates, modules and more. As a result, the WireArray class is one 
 * you will be interacting with regularly in the ProcessWire API, whether you know it or not. 
 * 
 * Below are all the public methods you can use to interact with WireArray types in ProcessWire. In addition to these
 * methods, you can also treat WireArray types like regular PHP arrays, in that you can `foreach()` them and get or 
 * set elements using array syntax, i.e. `$value = $items[$key];` to get an item or `$items[] = $item;` to add an item. 
 * #pw-body
 *
 */

class WireArray extends Wire implements \IteratorAggregate, \ArrayAccess, \Countable {

	/**
	 * Basic type managed by the WireArray for data storage
	 * 
	 * @var Wire[]
	 *
	 */
	protected $data = array();

	/**
	 * Any extra user defined data to accompany the WireArray
	 *
	 * See the data() method. Note these are not under change tracking. 
	 *
	 */
	protected $extraData = array();

	/**
	 * Array containing the items that have been removed from this WireArray while trackChanges is on
	 * 
	 * @see getRemovedKeys()
	 *
	 */
	protected $itemsRemoved = array(); 

	/**
	 * Array containing the items that have been added to this WireArray while trackChanges is on
	 * 
	 * @see getRemovedKeys()
	 *
	 */
	protected $itemsAdded = array();

	/**
	 * Prevent addition of duplicates?
	 * 
	 * Applies only to non-associative WireArray types.
	 * 
	 * @var bool
	 * 
	 */
	protected $duplicateChecking = true;

	/**
	 * Flags for PHP sort functions
	 * 
	 * @var int
	 * 
	 */
	protected $sortFlags = 0; // 0 == SORT_REGULAR

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		if($this->className() === 'WireArray') $this->duplicateChecking = false;	
	}

	/**
	 * Is the given item valid for storange in this array?
	 * 
	 * Template method that descending classes may use to validate items added to this WireArray
	 * 
	 * #pw-group-info
	 *
	 * @param mixed $item Item to test for validity
	 * @return bool True if item is valid and may be added, false if not
	 *
	 */
	public function isValidItem($item) {
		if($item instanceof Wire) return true;
		$className = $this->className();
		if($className === 'WireArray' || $className === 'PaginatedArray') return true;
		return false;
	}

	/**
	 * Is the given item key valid for use in this array?
	 * 
	 * Template method that descendant classes may use to validate the key of items added to this WireArray
	 * 
	 * #pw-group-info
	 *
	 * @param string|int $key Key to test
	 * @return bool True if key is valid and may be used, false if not
	 *
	 */
	public function isValidKey($key) {
		// unused $key intentional for descending class/template purposes
		if($key) {}
		return true; 
	}

	/**
	 * Is the given WireArray identical to this one?
	 * 
	 * #pw-group-info
	 * 
	 * @param WireArray $items
	 * @param bool|int $strict Use strict mode? Optionally specify one of the following: 
	 * 	`true` (boolean): Default. Compares items, item object instances, order, and any other data contained in WireArray.
	 * 	`false` (boolean): Compares only that items in the WireArray resolve to the same order and values (though not object instances).
	 * @return bool True if identical, false if not. 
	 * 
	 */
	public function isIdentical(WireArray $items, $strict = true) {
		if($items === $this) return true; 
		if($items->className() != $this->className()) return false;
		if(!$strict) return ((string) $this) === ((string) $items); 
		$a1 = $this->getArray();
		$a2 = $items->getArray();
		if($a1 === $a2) {
			// all items match
			$d1 = $this->data();
			$d2 = $items->data();
			if($d1 === $d2) {
				// all data matches
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Get the array key for the given item
	 * 
	 * - This is a template method that descendant classes may use to find a key from the item itself, or null if disabled. 
	 * - This method is used internally by the add() and prepend() methods. 
	 * 
	 * #pw-internal
	 *
	 * @param object|Wire $item Item to get key for
	 * @return string|int|null Found key, or null if not found. 
	 *
	 */
	public function getItemKey($item) {
		// in this base class, we don't make assumptions how the key is determined
		// so we just search the array to see if the item is already here and 
		// return it's key if it is here
		$key = array_search($item, $this->data, true); 
		return $key === false ? null : $key; 
	}

	/**
	 * Get a new/blank item of the type that this WireArray holds
	 * 
	 * #pw-internal
	 *
	 * @throws WireException If class doesn't implement this method. 
	 * @return Wire|null
	 *
	 */
	public function makeBlankItem() {
		$class = wireClassName($this, false); 
		if($class != 'WireArray' && $class != 'PaginatedArray') {
			throw new WireException("Class '$class' doesn't yet implement method 'makeBlankItem()' and it needs to.");
		}
		return null;
	}

	/**
	 * Creates a new blank instance of this WireArray, for internal use. 
	 * 
	 * #pw-internal
	 *
	 * @return WireArray
	 *
	 */
	public function makeNew() {
		$class = get_class($this); 
		$newArray = $this->wire(new $class()); 
		return $newArray; 
	}

	/**
	 * Creates a new populated copy/clone of this WireArray
	 *
	 * Same as a clone, except that descending classes may wish to replace the 
	 * clone call a manually created WireArray to prevent deep cloning.
	 * 
	 * #pw-internal
	 *
	 * @return WireArray
	 *
	 */
	public function makeCopy() {
		return clone $this; 
	}

	/**
	 * Import the given item(s) into this WireArray.
	 * 
	 * - Adds imported items to the end of the WireArray. 
	 * - Skips over any items already present in the WireArray (when duplicateChecking is enabled)
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param array|WireArray $items Items to import.
	 * @return $this 
	 * @throws WireException If given items not compatible with the WireArray
	 *
	 */
	public function import($items) {

		if(!is_array($items) && !self::iterable($items)) {
			throw new WireException('WireArray cannot import non arrays or non-iterable objects');
		}
	
		foreach($items as $key => $value) {
			if($this->duplicateChecking) {
				if(($k = $this->getItemKey($value)) !== null) $key = $k;
				if(isset($this->data[$key])) continue; // won't overwrite existing keys
				$this->set($key, $value);
			} else {
				$this->add($value); 
			}
		}

		return $this;
	}

	/**
	 * Add an item to the end of the WireArray.
	 * 
	 * ~~~~~
	 * $items->add($item); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param int|string|array|object|Wire|WireArray $item Item to add. 
	 * @return $this
	 * @throws WireException If given an item that can't be stored by this WireArray.
	 * @see WireArray::prepend(), WireArray::append()
	 * 
	 */
	public function add($item) {

		if(!$this->isValidItem($item)) {
			if($item instanceof WireArray) {
				foreach($item as $i) $this->prepend($i); 
				return $this; 
			} else {
				throw new WireException("Item added to " . get_class($this) . " is not an allowed type"); 
			}
		}

		$key = null;
		if($this->duplicateChecking && ($key = $this->getItemKey($item)) !== null) {
			// avoid two copies of the same item, re-add it to the end 
			if(isset($this->data[$key])) unset($this->data[$key]); 
			$this->data[$key] = $item; 
		} else {
			$this->data[] = $item;
			end($this->data);
			$key = key($this->data);
		}

		$this->trackChange("add", null, $item); 
		$this->trackAdd($item, $key); 
		return $this;
	}

	/**
	 * Insert an item either before or after another
 	 *
	 * Provides the implementation for the insertBefore and insertAfter functions
	 * 
	 * @param int|string|array|object $item Item you want to insert
	 * @param int|string|array|object $existingItem Item already present that you want to insert before/afer
	 * @param bool $insertBefore True if you want to insert before, false if after
	 * @return $this
	 * @throws WireException if given an invalid item
	 *
	 */
	protected function _insert($item, $existingItem, $insertBefore = true) {

		if(!$this->isValidItem($item)) throw new WireException("You may not insert this item type"); 
		$data = array();
		$this->add($item); // first add the item, then we'll move it
		$itemKey = $this->getItemKey($item); 

		foreach($this->data as $key => $value) {
			if($value === $existingItem) {
				// found $existingItem, so insert $item and then insert $existingItem
				if($insertBefore) { 
					$data[$itemKey] = $item; 
					$data[$key] = $value; 
				} else {
					$data[$key] = $value; 
					$data[$itemKey] = $item; 
				}
				
			} else if($value === $item) {
				// skip over it since the above is doing the insert 
				continue; 

			} else {
				// continue populating existing data
				$data[$key] = $value; 
			}
		}
		$this->data = $data; 
		return $this; 
	}

	/**
	 * Insert an item before an existing item
	 * 
	 * ~~~~~
	 * $items->insertBefore($newItem, $existingItem); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|string|int $item Item you want to insert.
	 * @param Wire|string|int $existingItem Item already present that you want to insert before.
	 * @return $this
	 *
	 */
	public function insertBefore($item, $existingItem) {
		return $this->_insert($item, $existingItem, true); 
	}

	/**
	 * Insert an item after an existing item
	 * 
	 * ~~~~~
	 * $items->insertAfter($newItem, $existingItem); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|string|int $item Item you want to insert
	 * @param Wire|string|int $existingItem Item already present that you want to insert after
	 * @return $this
	 *
	 */
	public function insertAfter($item, $existingItem) {
		return $this->_insert($item, $existingItem, false); 
	}

	/**
	 * Replace one item with the other
	 * 
	 * - The order of the arguments does not matter. 
	 * - If both items are already present, they will change places. 
	 * - If one item is not already present, it will replace the one that is. 
	 * - If neither item is present, both will be added at the end.
	 * 
	 * ~~~~~
	 * $items->replace($existingItem, $newItem); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|string|int $itemA
	 * @param Wire|string|int $itemB
	 * @return $this
	 *
	 */
	public function replace($itemA, $itemB) {
		$a = $this->get($itemA);
		$b = $this->get($itemB);
		if($a && $b) {
			// swap a and b
			$data = $this->data; 
			foreach($data as $key => $value) {
				$k = null;
				if($value === $a) {
					if(method_exists($b, 'getItemKey')) {
						$k = $b->getItemKey();
					} else {
						$k = $this->getItemKey($b);
					}
					$value = $b; 
				} else if($value === $b) {
					if(method_exists($a, 'getItemKey')) {
						$k = $a->getItemKey();
					} else {
						$k = $this->getItemKey($a);
					}
					$value = $a; 
				}
				if($k !== null) $key = $k;
				$data[$key] = $value;
			}
			$this->data = $data; 
		
		} else if($a) {
			// b not already in array, so replace a with b
			$this->_insert($itemB, $a); 
			$this->remove($a); 

		} else if($b) {
			// a not already in array, so replace b with a
			$this->_insert($itemA, $b); 
			$this->remove($b);
		}
		return $this; 
	}

	/**
	 * Set an item by key in the WireArray.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param int|string $key Key of item to set.
	 * @param int|string|array|object|Wire $value Item value to set.
	 * @throws WireException If given an item not compatible with this WireArray. 
	 * @return $this
	 *
	 */
	public function set($key, $value) {

		if(!$this->isValidItem($value)) throw new WireException("Item '$key' set to " . get_class($this) . " is not an allowed type"); 
		if(!$this->isValidKey($key)) throw new WireException("Key '$key' is not an allowed key for " . get_class($this));

		$this->trackChange($key, isset($this->data[$key]) ? $this->data[$key] : null, $value); 
		$this->data[$key] = $value; 
		$this->trackAdd($value, $key); 
		return $this; 
	}

	/**
	 * Enables setting of WireArray elements in object notation.
	 *
	 * Example: $myArray->myElement = 10; 
	 * Not applicable to numerically indexed arrays.
	 *
	 * @param int|string $property Key of item to set. 
	 * @param int|string|array|object Value of item to set. 
	 * @throws WireException
	 *
	 */
	public function __set($property, $value) {
		if($this->getProperty($property)) throw new WireException("Property '$property' is a reserved word and may not be set by direct reference."); 
		$this->set($property, $value); 
	}

	/**
	 * Ensures that isset() and empty() work for this classes properties. 
	 * 
	 * @param string|int $key
	 * @return bool
	 *
	 */
	public function __isset($key) {
		return isset($this->data[$key]);
	}

	/**
	 * Ensures that unset() works for this classes data. 
	 * 
	 * @param int|string $key
	 *
	 */
	public function __unset($key) {
		$this->remove($key); 
	}
	
	/**
	 * Like set() but accepts an array or WireArray to set multiple values at once
	 * 
	 * #pw-group-manipulation
	 *
	 * @param array|WireArray $data Array or WireArray of data that you want to set. 
	 * @return $this
	 *
	 */
	public function setArray($data) {
		if(self::iterable($data)) {
			foreach($data as $key => $value) $this->set($key, $value); 
		}
		return $this; 
	}


	/**
	 * Returns the value of the item at the given index, or null if not set. 
	 *
	 * You may also specify a selector, in which case this method will return the same result as 
	 * the `WireArray::findOne()` method. See the $key argument description for more details on 
	 * what can be provided. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @param int|string|array $key Provide any of the following: 
	 *  - Key of item to retrieve. 
	 *  - Array of keys, in which case an array of matching items will be returned, indexed by your keys.
	 *  - A selector string or selector array, to return the first item that matches the selector. 
	 *  - A string of text with "{var}" tags in it that will be populated with any matching properties from this WireArray. 
	 *  - A string like "foobar[]" which returns an array of all "foobar" properties from each item in the WireArray. 
	 *  - A string containing the "name" property of any item, and the matching item will be returned. 
	 * @return WireData|Page|mixed|array|null Value of item requested, or null if it doesn't exist.
	 * @throws WireException
	 *
	 */
	public function get($key) {

		// if an object was provided, get its key
		if(is_object($key)) {
			/** @var object $key */
			$key = $this->getItemKey($key);
			/** @var string|int $key */
		}

		// if given an array of keys, return all matching items
		if(is_array($key)) { 
			/** @var array $key */
			if(ctype_digit(implode('', array_keys($key)))) {
				$items = array();
				foreach($key as $k) {
					$item = $this->get($k);
					$items[$k] = $item;
				}
				return $items;
			} else {
				// selector array
				$item = $this->findOne($key);
				if($item === false) $item = null;
				return $item;
			}
		}

		// check if the index is set and return it if so
		if(isset($this->data[$key])) return $this->data[$key]; 

		// check if key contains a selector
		if(Selectors::stringHasSelector($key)) {
			$item = $this->findOne($key);
			if($item === false) $item = null;
			return $item;
		}
		
		if(strpos($key, '{') !== false && strpos($key, '}')) {
			// populate a formatted string with {tag} vars
			return wirePopulateStringTags($key, $this);
		}
	
		// check if key is requesting a property array: i.e. "name[]"
		if(strpos($key, '[]') !== false && substr($key, -2) == '[]') {
			return $this->explode(substr($key, 0, -2));
		}

		// if the WireArray uses numeric keys, then it's okay to
		// match a 'name' field if the provided key is a string
		$match = null;
		if(is_string($key) && $this->usesNumericKeys()) {
			$match = $this->getItemThatMatches('name', $key);
		}

		return $match; 
	}

	/**
	 * Enables derefencing of WireArray elements in object notation. 
	 *
	 * Example: $myArray->myElement
	 * Not applicable to numerically indexed arrays. 
	 * Fuel properties and hooked properties have precedence with this type of call.
	 * 
	 * @param int|string $property 
	 * @return Wire|WireData|Page|mixed|bool Value of item requested, or false if it doesn't exist. 
	 *
	 */
	public function __get($property) {
		$value = parent::__get($property); 
		if(is_null($value)) $value = $this->getProperty($property);
		if(is_null($value)) $value = $this->get($property); 
		return $value; 
	}

	/**
	 * Get a predefined property of the array, or extra data that has been set.
	 *
	 * Default properties include;
	 * 
	 * - `count` (int): Number of items present in this WireArray.
	 * - `last` (mixed): Last item in this WireArray.
	 * - `first` (mixed): First item in this WireArray.
	 * - `keys` (array): Keys used in this WireArray.
	 * - `values` (array): Values present in this WireArray.
	 * 
	 * These can also be accessed by direct reference. 
	 * 
	 * ~~~~~
	 * // Get count
	 * $count = $items->getProperty('count'); 
	 * 
	 * // Same as above using direct access property
	 * $count = $items->count; 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string $property Name of property to retrieve
	 * @return Wire|mixed
	 *
	 */
	public function getProperty($property) {
		static $properties = array(
			// property => method to map to
			'count' => 'count',	
			'last' => 'last',
			'first' => 'first',
			'keys' => 'getKeys',
			'values' => 'getValues',
			);
		
		if(!in_array($property, $properties)) return $this->data($property); 
		$func = $properties[$property];
		return $this->$func();
	}

	/**
	 * Return the first item in this WireArray having a property named $key with $value, or NULL if not found. 
	 *
	 * Used internally by get() and has() methods. 
	 *
	 * @param string $key Property to match. 
	 * @param string|int|object $value $value to match.
	 * @return Wire|null
	 *
	 */
	protected function getItemThatMatches($key, $value) {
		if(ctype_digit("$key")) return null;
		$item = null;
		foreach($this->data as $wire) {
			if(is_object($wire)) { 
				if($wire->$key === $value) {
					$item = $wire; 
					break;
				}
			} else {
				if($wire === $value) {
					$item = $wire; 
					break;
				}
			}
		}
		return $item; 
	}

	/**
	 * Does this WireArray have the given item, index, or match the given selector?
	 *
	 * If the WireArray uses numeric keys, then this will also match a WireData object's "name" field.
	 * 
	 * ~~~~~
	 * // See if it has a given $item
	 * if($items->has($item)) {
	 *   // Has the given $item
	 * }
	 * 
	 * // See if it has an object with a "name" property matching our text
	 * if($items->has("name=something")) {
	 *   // Has an item with a "name" property equal to "something"
	 * }
	 * 
	 * // Same as above, but works since "name" is assumed for many types
	 * if($items->has("something")) {
	 *   // It has it
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * #pw-group-info
	 * 
	 * @param int|string|Wire $key Key of item to check or selector.
	 * @return bool True if the item exists, false if not. 
	 * 
	 */ 
	public function has($key) {

		if(is_object($key)) {
			/** @var object|Wire $key */
			$key = $this->getItemKey($key);
			/** @var int|string $key */
		}
		
		if(is_array($key)) {
			// match selector array
			return $this->findOne($key) ? true : false;
		}

		if(array_key_exists($key, $this->data)) return true; 

		$match = null;
		if(is_string($key)) {

			if(Selectors::stringHasOperator($key)) {
				$match = $this->findOne($key); 

			} else if($this->usesNumericKeys()) {
				$match = $this->getItemThatMatches('name', $key); 
			}

		} 

		return $match ? true : false; 
	}

	/**
	 * Get a PHP array of all the items in this WireArray with original keys maintained 
	 * 
	 * #pw-group-retrieval
	 *
	 * @return array Copy of the array that WireArray uses internally. 
	 * @see WireArray::getValues()
	 * 
	 */
	public function getArray() {
		return $this->data; 
	}

	/**
	 * Returns all items in the WireArray (for syntax convenience)
	 * 
	 * This is for syntax convenience, as it simply returns this instance of the WireArray.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return $this
	 * 
	 */
	public function getAll() {
		return $this;
	}


	/**
	 * Returns a regular PHP array of all keys used in this WireArray.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return array Keys used in the WireArray.
	 * 
	 */
	public function getKeys() {
		return array_keys($this->data); 
	}

	/**
	 * Returns a regular PHP array of all values used in this WireArray.
	 * 
	 * Unlike the `WireArray::getArray()` method, this does not attempt to maintain original 
	 * keys of the items. The returned array is reindexed from 0. 
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return array|Wire[] Values used in the WireArray.
	 * @see WireArray::getArray()
	 * 
	 */
	public function getValues() {
		return array_values($this->data); 
	}

	/**
	 * Get a random item from this WireArray. 
	 *
	 * - If one item is requested (default), the item is returned (unless `$alwaysArray` argument is true).
	 * - If multiple items are requested, a new `WireArray` of those items is returned. 
	 * - We recommend using this method when you just need 1 random item, and using the `WireArray::findRandom()` method
	 *   when you need multiple random items. 
	 * 
	 * ~~~~~
	 * // Get a single random item
	 * $randomItem = $items->getRandom();
	 * 
	 * // Get 3 random items
	 * $randomItems = $items->getRandom(3); 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param int $num Number of items to return. Optional and defaults to 1. 
	 * @param bool $alwaysArray If true, then method will always return an array of items, even if it only contains 1 item.
	 * @return WireArray|Wire|mixed|null Returns value of item, or new WireArray of items if more than one requested. 
	 * @see WireArray::findRandom(), WireArray::findRandomTimed()
	 * 
	 */
	public function getRandom($num = 1, $alwaysArray = false) {
		$items = $this->makeNew(); 
		$count = $this->count();
		if(!$count) {
			if($num == 1 && !$alwaysArray) return null;
			return $items; 
		}
		$keys = array_rand($this->data, ($num > $count ? $count : $num)); 
		if($num == 1 && !$alwaysArray) return $this->data[$keys]; 
		if(!is_array($keys)) $keys = array($keys); 
		foreach($keys as $key) $items->add($this->data[$key]); 
		$items->setTrackChanges(true); 
		return $items; 
	}

	/**
	 * Find a specified quantity of random elements from this WireArray. 
	 *
	 * Unlike `WireArray::getRandom()` this method always returns a WireArray (or derived type).
	 * 
	 * ~~~~~
	 * // Get 3 random items
	 * $randomItems = $items->findRandom(3); 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param int $num Number of items to return 
	 * @return WireArray
	 * @see WireArray::getRandom(), WireArray::findRandomTimed()
	 *
	 */
	public function findRandom($num) {
		return $this->getRandom((int) $num, true);
	}

	/**
	 * Find a quantity of random elements from this WireArray based on a timed interval (or user provided seed).
	 *
	 * If no `$seed` is provided, today's date (day) is used to seed the random number
	 * generator, so you can use this function to rotate items on a daily basis.
	 * 
	 * _Idea and implementation provided by [mindplay.dk](https://twitter.com/mindplaydk)_
	 * 
	 * ~~~~~
	 * // Get same 3 random items per day
	 * $randomItems = $items->findRandomTimed(3); 
	 * 
	 * // Get same 3 random items per hour
	 * $randomItems = $items->findRandomTimed('YmdH'); 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param int $num The amount of items to extract from the given list
	 * @param int|string $seed Optionally provide one of the following: 
	 *   - A PHP [date()](http://php.net/manual/en/function.date.php) format string.
	 *   - A number used to see the random number generator.
	 *   - The default is the PHP date format "Ymd" which makes it randomize once daily. 
	 * @return WireArray
	 * @see WireArray::findRandom()
	 *
	 */
	public function findRandomTimed($num, $seed = 'Ymd') {

		if(is_string($seed)) $seed = crc32(date($seed));
		srand($seed);
		$keys = $this->getKeys();
		$items = $this->makeNew();
		
		while(count($keys) > 0 && count($items) < $num) {
			$index = rand(0, count($keys)-1);
			$key = $keys[$index];
			$items->add($this->get($key));
			array_splice($keys, $index, 1);
		}

		return $items;
	}

	/**
	 * Get a slice of the WireArray.
	 *
	 * Given a starting point and a number of items, returns a new WireArray of those items. 
	 * If `$limit` is omitted, then it includes everything beyond the starting point. 
	 * 
	 * ~~~~~
	 * // Get first 3 items
	 * $myItems = $items->slice(0, 3); 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param int $start Starting index. 
	 * @param int $limit Number of items to include. If omitted, includes the rest of the array.
	 * @return WireArray Returns a new WireArray.
	 *
	 */
	public function slice($start, $limit = 0) {
		if($limit) $slice = array_slice($this->data, $start, $limit); 
			else $slice = array_slice($this->data, $start); 
		$items = $this->makeNew(); 
		$items->import($slice); 
		$items->setTrackChanges(true); 
		return $items; 
	}

	/**
	 * Prepend an item to the beginning of the WireArray.
	 * 
	 * ~~~~~
	 * // Add item to beginning
	 * $items->prepend($item);
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|WireArray|mixed $item Item to prepend. 
	 * @return $this This instance.
	 * @throws WireException
	 * @see WireArray::append()
	 *
	 */
	public function prepend($item) {

		if(!$this->isValidItem($item)) {
			if($item instanceof WireArray) {
				foreach($item as $i) $this->prepend($i); 
				return $this; 
			} else {
				throw new WireException("Item prepend to " . get_class($this) . " is not an allowed type"); 
			}
		}

		if($this->duplicateChecking && ($key = $this->getItemKey($item)) !== null) {
			// item already present
			$a = array($key => $item); 
			$this->data = $a + $this->data; // UNION operator for arrays
			// $this->data = array_merge($a, $this->data); 
		} else { 
			// new item
			array_unshift($this->data, $item); 
			reset($this->data);
			$key = key($this->data);
		}
		//if($item instanceof Wire) $item->setTrackChanges();
		$this->trackChange('prepend', null, $item); 
		$this->trackAdd($item, $key); 
		return $this; 
	}

	/**
	 * Append an item to the end of the WireArray 
	 * 
	 * This is a functionally identical alias of the `WireArray::add()` method here for
	 * naming consistency with the `WireArray::prepend()` method. 
	 * 
	 * ~~~~~
	 * // Add item to end 
	 * $items->append($item); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|WireArray|mixed $item Item to append. 
	 * @return $this This instance.
	 * @see WireArray::prepend(), WireArray::add()
	 *
	 */
	public function append($item) {
		$this->add($item); 
		return $this; 
	}

	/**
	 * Unshift an element to the beginning of the WireArray (alias for prepend)
	 * 
	 * This is for consistency with PHP's naming convention of the `array_unshift()` method.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|WireArray|mixed $item Item to prepend. 
	 * @return $this This instance.
	 * @see WireArray::shift(), WireArray::prepend()
	 *
	 */
	public function unshift($item) {
		return $this->prepend($item); 
	}

	/**
	 * Shift an element off the beginning of the WireArray and return it
	 * 
	 * Consistent with behavior of PHP's `array_shift()` method. 
	 * 
	 * #pw-group-manipulation
	 * #pw-group-retrieval
	 *
	 * @return Wire|mixed|null Item shifted off the beginning or NULL if empty.
	 * @see WireArray::unshift()
	 *
	 */
	public function shift() {
		reset($this->data);
		$key = key($this->data);
		$item = array_shift($this->data); 
		if(is_null($item)) return $item;
		$this->trackChange('shift', $item, null);
		$this->trackRemove($item, $key); 
		return $item; 
	}

	/**
	 * Push an item to the end of the WireArray.
	 * 
	 * Same as `WireArray::add()` and `WireArray::append()`, but here for syntax convenience.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Wire|mixed $item Item to push. 
	 * @return $this This instance.
	 * @see WireArray::pop()
	 *
	 */
	public function push($item) {
		$this->add($item); 	
		return $this; 
	}

	/**
	 * Pop an element off the end of the WireArray and return it
	 * 
	 * #pw-group-retrieval
	 * #pw-group-manipulation
	 * 
	 * @return Wire|mixed|null Item popped off the end or NULL if empty.
	 *
	 */
	public function pop() {
		end($this->data);
		$key = key($this->data);
		$item = array_pop($this->data); 
		if(is_null($item)) return $item;
		$this->trackChange('pop', $item, null);
		$this->trackRemove($item, $key); 
		return $item; 
	}

	/**
	 * Shuffle/randomize this WireArray
	 * 
	 * #pw-group-manipulation
	 *
	 * @return $this This instance.
	 *
	 */
	public function shuffle() {

		$keys = $this->getKeys(); 
		$data = array();

		// shuffle the keys rather than the original array in case it's associative
		// because PHP's shuffle reindexes the array
		shuffle($keys); 
		foreach($keys as $key) {
			$data[$key] = $this->data[$key]; 
		}
		
		$this->trackChange('shuffle', $this->data, $data); 

		$this->data = $data; 

		return $this; 
	}

	/**
	 * Returns a new WireArray of the item at the given index. 
	 *  
	 * Unlike `WireArray::get()` this returns a new WireArray with a single item, or a blank WireArray if item doesn't exist. 
	 * Applicable to numerically indexed WireArray only.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param int $num Index number
	 * @return WireArray
	 * @see WireArray::eq()
	 *
	 */
	public function index($num) {
		return $this->slice($num, 1); 
	}

	/**
	 * Returns the item at the given index starting from 0, or NULL if it doesn't exist.
	 *  
	 * Unlike the `WireArray::index()` method, this returns an actual item and not another WireArray.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param int $num Return the n'th item in this WireArray. Specify a negative number to count from the end rather than the start.
	 * @return Wire|null
	 * @see WireArray::index()
	 *
	 */
	public function eq($num) {
		$num = (int) $num; 
		$item = array_slice($this->data, $num, 1); 
		$item = count($item) ? reset($item) : null;
		return $item; 
	}
	
	/**
	 * Returns the first item in the WireArray or boolean false if empty. 
	 *
	 * Note that this resets the internal WireArray pointer, which would affect other active iterations. 
	 * 
	 * ~~~~~
	 * $item = $items->first();
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * #pw-group-retrieval
	 *
	 * @return Wire|mixed|bool
	 *
	 */
	public function first() {
		return reset($this->data);
	}
	
	/**
	 * Returns the last item in the WireArray or boolean false if empty.
	 *
	 * Note that this resets the internal WireArray pointer, which would affect other active iterations.
	 * 
	 * ~~~~~
	 * $item = $items->last();
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * #pw-group-retrieval
	 * 
	 * @return Wire|mixed|bool
	 *
	 */
	public function last() {
		return end($this->data); 
	}

	/**
	 * Removes the given item or index from the WireArray (if it exists).
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param int|string|Wire $key Item to remove (object), or index of that item. 
	 * @return $this This instance.
	 *
	 */
	public function remove($key) {

		if(is_object($key)) {
			$key = $this->getItemKey($key); 
		}

		if($this->has($key)) {
			$item = $this->data[$key];
			unset($this->data[$key]); 
			$this->trackChange("remove", $item, null); 
			$this->trackRemove($item, $key); 
			
		}

		return $this;
	}

	/**
	 * Removes multiple identified items at once
	 * 
	 * #pw-group-manipulation
	 *
	 * @param array|Wire|string|WireArray $items Items to remove
	 * @return $this
	 * 
	 */
	public function removeItems($items) {
		if(!self::iterable($items)) $items = array($items);
		foreach($items as $item) $this->remove($item); 
		return $this;
	}

	/**
	 * Removes all items from the WireArray, leaving it blank
	 * 
	 * #pw-group-manipulation
	 * 
	 * @return $this
	 *
	 */
	public function removeAll() {
		foreach($this as $key => $value) {
			$this->remove($key); 
		}
		return $this; 
	}

	/**
	 * Remove an item without any record of the event or telling anything else. 
	 * 
	 * #pw-internal
	 *
	 * @param int|string|Wire $key Index of item or object instance of item. 
	 * @return $this This instance. 
	 *
	 */
	public function removeQuietly($key) {
		if(is_object($key)) $key = $this->getItemKey($key);
		unset($this->data[$key]);
		return $this;
	}

	/**
	 * Sort this WireArray by the given properties. 
	 *
	 * - Sort properties can be given as a string in the format `name, datestamp` or as an array of strings, 
	 *   i.e. `["name", "datestamp"]`.
	 * 
	 * - You may also specify the properties as `property.subproperty`, where property resolves to a Wire derived object
	 *   in each item, and subproperty resolves to a property within that object.
	 * 
	 * - Prepend or append a minus "-" to reverse the sort (per field).
	 * 
	 * ~~~~~
	 * // Sort newest to oldest
	 * $items->sort("-created"); 
	 * 
	 * // Sort by last_name then first_name
	 * $items->sort("last_name, first_name"); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string|array $properties Field names to sort by (CSV string or array). 
	 * @param int|null $flags Optionally specify sort flags (see sortFlags method for details). 
	 * @return $this reference to current instance.
	 */
	public function sort($properties, $flags = null) {
		$_flags = $this->sortFlags; // remember
		if(is_int($flags)) $this->sortFlags($flags);
		$result = $this->_sort($properties);
		if(is_int($flags) && $flags !== $_flags) $this->sortFlags($_flags); // restore
		return $result;
	}

	/**
	 * Sort this WireArray by the given properties (internal use)
	 * 
	 * This function contains additions and modifications by @niklaka.
	 *
	 * $properties can be given as a sortByField string, i.e. "name, datestamp" OR as an array of strings, i.e. array("name", "datestamp")
	 * You may also specify the properties as "property.subproperty", where property resolves to a Wire derived object, 
	 * and subproperty resolves to a property within that object. 
	 * 
	 * @param string|array $properties Field names to sort by (comma separated string or an array). Prepend or append a minus "-" to reverse the sort (per field).
	 * @param int $numNeeded *Internal* amount of rows that need to be sorted (optimization used by filterData)
	 * @return $this reference to current instance.
	 */
	protected function _sort($properties, $numNeeded = null) {

		// string version is used for change tracking
		$propertiesStr = is_array($properties) ? implode(',', $properties) : $properties;
		if(!is_array($properties)) $properties = preg_split('/\s*,\s*/', $properties);

		// shortcut for random (only allowed as the sole sort property)
		// no warning/error for issuing more properties though
		// TODO: warning for random+more properties (and trackChange() too)
		if($properties[0] == 'random') return $this->shuffle();
		
		$data = $this->stableSort($this, $properties, $numNeeded);
		$this->trackChange("sort:$propertiesStr", $this->data, $data);
		$this->data = $data; 

		return $this;
	}

	/**
	 * Get or set sort flags that affect behavior of any sorting functions
	 * 
	 * The following constants may be used when setting the sort flags:
	 * 
	 * - `SORT_REGULAR` compare items normally (don’t change types)
	 * - `SORT_NUMERIC` compare items numerically
	 * - `SORT_STRING` compare items as strings
	 * - `SORT_LOCALE_STRING` compare items as strings, based on the current locale
	 * - `SORT_NATURAL` compare items as strings using “natural ordering” like natsort()
	 * - `SORT_FLAG_CASE` can be combined (bitwise OR) with SORT_STRING or SORT_NATURAL to sort strings case-insensitively
	 *
	 * For more details, see `$sort_flags` argument at: https://www.php.net/manual/en/function.sort.php
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param bool $sortFlags Optionally specify flag(s) to set
	 * @return int Returns current flags
	 * @since 3.0.129
	 * 
	 */
	public function sortFlags($sortFlags = false) {
		if(is_int($sortFlags)) $this->sortFlags = $sortFlags;
		return $this->sortFlags;
	}

	/**
	 * Sort given array by first given property.
	 *
	 * This function contains additions and modifications by @niklaka.
	 *
	 * @param array|WireArray &$data Reference to an array to sort.
	 * @param array $properties Array of properties: first property is used now and others in recursion, if needed.
	 * @param int $numNeeded *Internal* amount of rows that need to be sorted (optimization used by filterData)
	 * @return array Sorted array (at least $numNeeded items, if $numNeeded is given)
	 */
	protected function stableSort(&$data, $properties, $numNeeded = null) {

		$property = array_shift($properties);

		$unidentified = array();
		$sortable = array();
		$reverse = false;
		$subProperty = '';

		if(substr($property, 0, 1) == '-' || substr($property, -1) == '-') {
			$reverse = true; 
			$property = trim($property, '-'); 
		}

		$pos = strpos($property, ".");
		if($pos) {
			$subProperty = substr($property, $pos+1); 
			$property = substr($property, 0, $pos); 
		}

		foreach($data as $item) {
			/** @var Wire $item */
			$key = $this->getItemPropertyValue($item, $property); 

			// if item->property resolves to another Wire, then try to get the subProperty from that Wire (if it exists)
			if($key instanceof Wire && $subProperty) {
				$key = $this->getItemPropertyValue($key, $subProperty);
			}

			// check for items that resolve to blank
			if(is_null($key) || (is_string($key) && !strlen(trim($key)))) {
				$unidentified[] = $item;
				continue; 
			}

			$key = (string) $key; 

			// ensure numeric sorting if the key is a number
			if(ctype_digit("$key")) $key = (int) $key; 

			if(isset($sortable[$key])) {
				// key resolved to the same value that another did, so keep them together by converting this index to an array
				// this makes the algorithm stable (for equal keys the order would be undefined)
				if(is_array($sortable[$key])) $sortable[$key][] = $item; 
					else $sortable[$key] = array($sortable[$key], $item); 
			} else { 
				$sortable[$key] = $item; 
			}
		}

		// sort the items by the keys we collected
		if($reverse) krsort($sortable, $this->sortFlags);
			else ksort($sortable, $this->sortFlags); 

		// add the items that resolved to no key to the end, as an array
		$sortable[] = $unidentified; 

		// restore sorted array to lose sortable keys and restore proper keys
		$a = array();
		foreach($sortable as $key => $value) {
			if(is_array($value)) {
				// if more properties to sort by exist, use them for this sub-array
				$n = null;
				if($numNeeded) $n = $numNeeded - count($a); 
				if(count($properties)) $value = $this->stableSort($value, $properties, $n);
				foreach($value as $k => $v) {
					$newKey = $this->getItemKey($v); 
					$a[$newKey] = $v; 
					// are we done yet?
					if($numNeeded && count($a) > $numNeeded) break;
				}
			} else {
				$newKey = $this->getItemKey($value); 
				$a[$newKey] = $value; 	
			}
			// are we done yet?
			if($numNeeded && count($a) > $numNeeded) break;
		}

		return $a;
	}

	/**
	 * Get the value of $property from $item
	 *
	 * Used by the WireArray::sort method to retrieve a value from a Wire object. 
	 * Primarily here as a template method so that it can be overridden. 
	 * Lets it prepare the Wire for any states needed for sorting. 
	 *
	 * @param Wire $item
	 * @param string $property
	 * @return mixed
	 *
	 */
	protected function getItemPropertyValue(Wire $item, $property) {
		if(strpos($property, '.') !== false) return WireData::_getDot($property, $item); 
		return $item->$property; 
	}

	/**
	 * Filter out Wires that don't match the selector. 
	 * 
	 * This is applicable to and destructive to the WireArray.
	 * This function contains additions and modifications by @niklaka.
	 *
	 * @param string|array|Selectors $selectors Selector string|array to use as the filter.
	 * @param bool|int $not Make this a "not" filter? Use int 1 for “not all” mode as if selectors had brackets around it. (default is false)
	 * @return $this reference to current [filtered] instance
	 *
	 */
	protected function filterData($selectors, $not = false) {

		if(is_object($selectors) && $selectors instanceof Selectors) {
			// fantastic
		} else {
			if(!is_array($selectors) && ctype_digit("$selectors")) $selectors = "id=$selectors";
			$selector = $selectors; 
			$selectors = $this->wire(new Selectors()); 
			$selectors->init($selector);
		}
		
		$this->filterDataSelectors($selectors); 

		$sort = array();
		$start = 0;
		$limit = null;
		$eq = null;
		$notAll = $not === 1;
		if($notAll) $not = true;

		// leave sort, limit and start away from filtering selectors
		foreach($selectors as $selector) {
			$remove = true; 
			$field = $selector->field;

			if($field === 'sort') {
				// use all sort selectors
				$sort[] = $selector->value; 

			} else if($field === 'start') { 
				// use only the last start selector
				$start = (int) $selector->value;

			} else if($field === 'limit') {
				// use only the last limit selector
				$limit = (int) $selector->value;

			} else if(($field === 'index' || $field == 'eq') && !$this->wire('fields')->get($field)) {
				// eq or index properties
				switch($selector->value) {
					case 'first': $eq = 0; break;
					case 'last': $eq = -1; break;
					default: $eq = (int) $selector->value;
				}

			} else {
				// everything else is to be saved for filtering
				$remove = false;
			}

			if($remove) $selectors->remove($selector);
		}
		
		// now filter the data according to the selectors that remain
		foreach($this->data as $key => $item) {
			$qty = 0;
			$qtyMatch = 0;	
			foreach($selectors as $selector) {
				$qty++;
				if(is_array($selector->field)) {
					$value = array();
					foreach($selector->field as $field) $value[] = (string) $this->getItemPropertyValue($item, $field);
				} else {
					$value = (string) $this->getItemPropertyValue($item, $selector->field);
				}
				if($not === $selector->matches($value) && isset($this->data[$key])) {
					$qtyMatch++;
					if($notAll) continue; // will do this outside the loop of all in $selectors match
					$this->trackRemove($this->data[$key], $key);
					unset($this->data[$key]);
				}
			}
			if($notAll && $qty && $qty === $qtyMatch) {
				$this->trackRemove($this->data[$key], $key);
				unset($this->data[$key]);
			}
		}

		if(!is_null($eq)) {
			if($eq === -1) {
				$limit = -1;
				$start = null;
			} else if($eq === 0) {
				$start = 0;
				$limit = 1;
			} else {
				$start = $eq;
				$limit = 1;
			}
		}
		
		if($limit < 0 && $start < 0) {
			// we don't support double negative, so double negative makes a positive 
			$start = abs($start);
			$limit = abs($limit);
		} else {
			if($limit < 0) {
				if($start) {
					$start = $start - abs($limit);
					$limit = abs($limit);
				} else {
					$start = count($this->data) - abs($limit);
					$limit = count($this->data);
				}
			}
			if($start < 0) {
				$start = count($this->data) - abs($start);
			}
		}

		// if $limit has been given, tell sort the amount of rows that will be used
		if(count($sort)) $this->_sort($sort, $limit ? $start+$limit : null); 
		if($start || $limit) {
			$this->data = array_slice($this->data, $start, $limit, true);
		}

		$this->trackChange("filterData:$selectors"); 
		return $this; 
	}

	/**
	 * Prepare selectors for filtering
	 * 
	 * Template method for descending classes to modify selectors if needed
	 * 
	 * @param Selectors $selectors
	 * 
	 */
	protected function filterDataSelectors(Selectors $selectors) { }

	/**
	 * Filter this WireArray to only include items that match the given selector (destructive)
	 * 
	 * ~~~~~
	 * // Filter $items to contain only those with "featured" property having value 1
	 * $items->filter("featured=1"); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string|array|Selectors $selector Selector string or array to use as the filter. 
	 * @return $this reference to current instance.
	 * @see filterData
	 *
	 */
	public function filter($selector) {
		// Same as filterData, but for public interface without the $not option. 
		return $this->filterData($selector, false); 
	}

	/**
	 * Filter this WireArray to only include items that DO NOT match the selector (destructive)
	 * 
	 * ~~~~~
	 * // returns all pages that don't have a 'nonav' variable set to a positive value. 
	 * $pages->not("nonav"); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string|array|Selectors $selector 
	 * @return $this reference to current instance.
	 * @see filterData
	 *
	 */
	public function not($selector) {
		// Same as filterData, but for public interface with the $not option specifically set to "true".
		return $this->filterData($selector, true); 
	}

	/**
	 * Like the not() method but $selector evaluated as if it had (brackets) around it 
	 *
	 * #pw-internal Until we've got a better description for what this does
	 *
	 * @param string|array|Selectors $selector
	 * @return $this reference to current instance.
	 * @see filterData
	 *
	 */
	public function notAll($selector) {
		return $this->filterData($selector, 1); 
	}

	/**
	 * Find all items in this WireArray that match the given selector.
	 * 
	 * This is non destructive and returns a brand new WireArray.
	 * 
	 * ~~~~~
	 * // Find all items with a title property containing the word "foo"
	 * $matches = $items->find("title%=foo"); 
	 * if($matches->count()) {
	 *   echo "Found $matches->count items"; 
	 * } else {
	 *   echo "Sorry, no items were found";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string|array|Selectors $selector 
	 * @return WireArray 
	 *
	 */
	public function find($selector) {
		$a = $this->makeCopy();
		if(empty($selector)) return $a;
		$a->filter($selector); 	
		return $a; 
	}

	/**
	 * Find a single item by selector
	 * 
	 * This is the same as `WireArray::find()` except that it returns a single
	 * item rather than a new WireArray of items. 
	 * 
	 * ~~~~~
	 * // Get an item with name "foo-bar"
	 * $item = $items->findOne("name=foo-bar"); 
	 * if($item) {
	 *   // item was found
	 * } else {
	 *   // item was not found 
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string|array|Selectors $selector
	 * @return Wire|bool Returns item from WireArray or false if the result is empty.
	 * @see WireArray::find()
	 *
	 */
	public function findOne($selector) {
		return $this->find($selector)->first();
	}

	/**	
	 * Determines if the given item iterable as an array.
	 *
	 * - Returns true for arrays and WireArray derived objects. 
	 * - Can be called statically like this `WireArray::iterable($a)`.
	 * 
	 * #pw-group-info
	 * 
	 * @param mixed $item Item to check for iterability. 
	 * @return bool True if item is an iterable array or WireArray (or subclass of WireArray).
	 *
	 */
	public static function iterable($item) {
		if(is_array($item)) return true;
		if($item instanceof WireArray) return true;
		return false;
	}

	/**
	 * Allows iteration of the WireArray. 
	 * 
	 * - Fulfills PHP's IteratorAggregate interface so that you can traverse the WireArray. 
	 * - No need to call this method directly, just use PHP's `foreach()` method on the WireArray.
	 * 
	 * ~~~~~
	 * // Traversing a WireArray with foreach:
	 * foreach($items as $item) {
	 *   // ... 
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * 
	 * @return \ArrayObject|Wire[]
	 *
	 */
	public function getIterator() {
		return new \ArrayObject($this->data); 
	}

	/**
	 * Returns the number of items in this WireArray.
	 *
	 * Fulfills PHP's Countable interface, meaning it also enables this WireArray to be used with PHP's `count()` function. 
	 * 
	 * ~~~~~
	 * // These two are the same
	 * $qty = $items->count();
	 * $qty = count($items); 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return int
	 * 
	 */
	public function count() {
		return count($this->data); 
	}

	/**
	 * Sets an index in the WireArray.
	 *
	 * For the \ArrayAccess interface. 
	 * 
	 * #pw-internal
	 * 
	 * @param int|string $key Key of item to set.
	 * @param Wire|mixed $value Value of item. 
	 * 
	 */
	public function offsetSet($key, $value) {
		$this->set($key, $value); 
	}

	/**
	 * Returns the value of the item at the given index, or false if not set.
	 * 
	 * #pw-internal
	 *
	 * @param int|string $key Key of item to retrieve. 
	 * @return Wire|mixed|bool Value of item requested, or false if it doesn't exist. 
	 * 
	 */
	public function offsetGet($key) {
		if($this->offsetExists($key)) {
			return $this->data[$key];
		} else {
			return false;
		}
	}
	
	/**
	 * Unsets the value at the given index. 
	 *
	 * For the \ArrayAccess interface.
	 * 
	 * #pw-internal
	 *
	 * @param int|string $key Key of the item to unset. 
	 * @return bool True if item existed and was unset. False if item didn't exist. 
	 * 
	 */
	public function offsetUnset($key) {
		if($this->offsetExists($key)) {
			$this->remove($key); 
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Determines if the given index exists in this WireArray. 	
	 *
	 * For the \ArrayAccess interface.
	 * 
	 * #pw-internal
	 * 
	 * @param int|string $key Key of the item to check for existance.
	 * @return bool True if the item exists, false if not.
	 * 
	 */
	public function offsetExists($key) {
		return array_key_exists($key, $this->data);
	}

	/**
	 * Returns a string representation of this WireArray.
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		$s = '';
		foreach($this as $key => $value) {
			if(is_array($value)) $value = "array(" . count($value) . ")";
			$s .= "$value|";
		}
		$s = rtrim($s, '|'); 
		return $s; 
	}

	/**
	 * Return a new reversed version of this WireArray.
	 * 
	 * #pw-group-retrieval
	 *
	 * @return WireArray
	 *
	 */ 
	public function reverse() {
		$a = $this->makeNew();
		$a->import(array_reverse($this->data, true)); 
		return $a; 
	}

	/**
	 * Return a new array that is unique (no two of the same elements)
	 * 
	 * This is the equivalent to PHP's [array_unique()](http://php.net/manual/en/function.array-unique.php) function. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @param int $sortFlags Sort flags per PHP's `array_unique()` function (default=`SORT_STRING`)
	 * @return WireArray 
	 *
	 */
	public function unique($sortFlags = SORT_STRING) {
		$a = $this->makeNew();	
		$a->import(array_unique($this->data, $sortFlags)); 
		return $a; 
	}


	/**
	 * Clears out any tracked changes and turns change tracking ON or OFF
	 * 
	 * #pw-internal
	 *
	 * @param bool $trackChanges True to turn change tracking ON, or false to turn OFF. Default of true is assumed. 
	 * @return Wire|WireArray
	 *
	 */
	public function resetTrackChanges($trackChanges = true) {
		$this->itemsAdded = array();
		$this->itemsRemoved = array();
		return parent::resetTrackChanges($trackChanges); 
	}

	/**
	 * Track an item added
	 *
	 * @param Wire|mixed $item
	 * @param int|string $key 
 	 *
	 */
	protected function trackAdd($item, $key) {
		if($key) {}
		if($this->trackChanges()) $this->itemsAdded[] = $item;
	}

	/**
	 * Track an item removed
	 *
	 * @param Wire|mixed $item
	 * @param int|string $key
 	 *
	 */
	protected function trackRemove($item, $key) {
		if($key) {}
		if($this->trackChanges()) $this->itemsRemoved[] = $item; 
	}

	/**
	 * Return array of all items added to this WireArray (while change tracking is enabled)
	 * 
	 * #pw-group-changes
	 *
	 * @return array|Wire[]
	 *
	 */
	public function getItemsAdded() {
		return $this->itemsAdded; 
	}

	/**
	 * Return array of all items removed from this WireArray (when change tracking is enabled)
	 * 
	 * #pw-group-changes
	 *
	 * @return array|Wire[]
	 *
	 */
	public function getItemsRemoved() {
		return $this->itemsRemoved; 
	}

	/**
	 * Given an item, get the item that comes after it in the WireArray
	 * 
	 * #pw-group-retrieval
	 *
	 * @param Wire $item 
	 * @param bool $strict If false, string comparison will be used rather than exact instance comparison.
	 * @return Wire|null Returns next item if found, or null if not
	 *
	 */
	public function getNext($item, $strict = true) {
		if(!$this->isValidItem($item)) return null;
		$key = $this->getItemKey($item); 
		$useStr = false;
		if($key === null) {
			if($strict) return null;
			$key = (string) $item;	
			$useStr = true;
		}
		$getNext = false; 
		$nextItem = null;
		foreach($this->data as $k => $v) {
			if($getNext) {
				$nextItem = $v; 	
				break;
			}
			if($useStr) $k = (string) $v;
			if($k === $key) $getNext = true; 
		}
		return $nextItem; 
	}

	/**
	 * Given an item, get the item before it in the WireArray
	 * 
	 * #pw-group-retrieval
	 *
	 * @param Wire $item
	 * @param bool $strict If false, string comparison will be used rather than exact instance comparison.
	 * @return Wire|null Returns item that comes before given item, or null if not found 
	 *
	 */
	public function getPrev($item, $strict = true) {
		if(!$this->isValidItem($item)) return null;
		$key = $this->getItemKey($item);
		$useStr = false;
		if($key === null) {
			if($strict) return null;
			$key = (string) $item;
			$useStr = true;
		}
		$prevItem = null; 
		$lastItem = null;
		foreach($this->data as $k => $v) {
			if($useStr) $k = (string) $v;
			if($k === $key) {
				$prevItem = $lastItem; 
				break;
			}
			$lastItem = $v; 
		}
		return $prevItem; 
	}

	/**
	 * Does this WireArray use numeric keys only? 
	 *
	 * We determine this by creating a blank item and seeing what the type is of it's key. 
	 * 
	 * #pw-internal
	 * 
	 * @return bool
	 *
	 */
	protected function usesNumericKeys() {

		static $testItem = null;
		static $usesNumericKeys = null; 

		if(!is_null($usesNumericKeys)) return $usesNumericKeys; 
		if(is_null($testItem)) $testItem = $this->makeBlankItem(); 
		if(is_null($testItem)) return true; 

		$key = $this->getItemKey($testItem); 
		$usesNumericKeys = is_int($key) ? true : false;
		return $usesNumericKeys; 
	}

	/**
	 * Combine all elements into a delimiter-separated string containing the given property from each item
	 *
	 * Similar to PHP's `implode()` function.
	 * 
	 * #pw-link [Introduction of implode method](https://processwire.com/talk/topic/5098-new-wirearray-api-additions-on-dev/)
	 * #pw-group-retrieval
	 * #pw-group-fun-tools
	 * #pw-group-output-rendering
	 * 
	 * @param string $delimiter The delimiter to separate each item by (or the glue to tie them together).
	 *	If not needed, this argument may be omitted and $property supplied first (also shifting $options to 2nd arg).
	 * @param string|callable $property The property to retrieve from each item, or a function that returns the value to store.
	 *	If a function/closure is provided it is given the $item (argument 1) and the $key (argument 2), and it should 
	 *	return the value (string) to use. If delimiter is omitted, this becomes the first argument. 
	 * @param array $options Optional options to modify the behavior:
	 *  - `skipEmpty` (bool): Whether empty items should be skipped (default=true)
	 *  - `prepend` (string): String to prepend to result. Ignored if result is blank. 
	 *  - `append` (string): String to append to result. Ignored if result is blank. 
	 *  - Please note that if delimiter is omitted, $options becomes the second argument. 
	 * @return string
	 * @see WireArray::each(), WireArray::explode()
	 *
	 */
	public function implode($delimiter, $property = '', array $options = array()) {

		$defaults = array(
			'skipEmpty' => true, 
			'prepend' => '', 
			'append' => ''
			);

		if(!count($this->data)) return '';
			
		$firstItem = reset($this->data);
		$itemIsObject = is_object($firstItem);

		if(!is_string($delimiter) && is_callable($delimiter)) {
			// first delimiter argument omitted and a function was supplied 
			// property is assumed to be blank
			if(is_array($property)) $options = $property; 
			$property = $delimiter; 
			$delimiter = '';
		} else if($itemIsObject && (empty($property) || is_array($property))) {
			// delimiter was omitted, forcing $property to be first arg
			if(is_array($property)) $options = $property; 
			$property = $delimiter; 
			$delimiter = '';
		}

		$options = array_merge($defaults, $options); 
		$isFunction = !is_string($property) && is_callable($property); 
		$str = '';
		$n = 0;

		foreach($this as $key => $item) {
			if($isFunction) {
				$value = $property($item, $key);
			} else if(strlen($property) && $itemIsObject) {
				$value = $item->get($property);
			} else {
				$value = $item;
			}
			if(is_array($value)) $value = 'array(' . count($value) . ')';
			$value = (string) $value; 
			if(!strlen($value) && $options['skipEmpty']) continue; 
			if($n) $str .= $delimiter; 
			$str .= $value; 
			$n++;
		}

		if(strlen($str) && ($options['prepend'] || $options['append'])) {
			$str = "$options[prepend]$str$options[append]";
		}

		return $str; 
	}

	/**
	 * Return a plain array of the requested property from each item
	 *
	 * You may provide an array of properties as the $property, in which case it will return an
	 * array of associative arrays with all requested properties for each item.
	 *
	 * You may also provide a function as the $property. That function receives the $item
	 * as the first argument and $key as the second. It should return the value that will be stored.
	 *
	 * The keys of the returned array remain consistent with the original WireArray.
	 * 
	 * #pw-link [Introduction of explode method](https://processwire.com/talk/topic/5098-new-wirearray-api-additions-on-dev/)
	 * #pw-group-retrieval
	 * #pw-group-fun-tools
	 *
	 * @param string|callable|array $property Property or properties to retrieve, or callable function that should receive items.
	 * @param array $options Options to modify default behavior:
	 *  - `getMethod` (string): Method to call on each item to retrieve $property (default = "get")
	 *  - `key` (string|null): Property of Wire objects to use for key of array, or omit (null) for non-associative array (default).
	 * @return array
	 * @see WireArray::each(), WireArray::implode()
	 *
	 */
	public function explode($property = '', array $options = array()) {
		$defaults = array(
			'getMethod' => 'get', // method used to get value from each item
			'key' => null,
		);
		$options = array_merge($defaults, $options);
		$getMethod = $options['getMethod'];
		$isArray = is_array($property);
		$isFunction = !$isArray && !is_string($property) && is_callable($property);
		$values = array();
		foreach($this as $key => $item) {
			if(!is_object($item)) {
				$values[$key] = $item;
				continue;
			}
			if(!empty($options['key']) && is_string($options['key'])) {
				$key = $item->get($options['key']);	
				if(!is_string($key) || !is_int($key)) $key = (string) $key;	
				if(!strlen($key)) continue;
				if(isset($values[$key])) continue;
			}
			if($isFunction) {
				$values[$key] = $property($item, $key);
			} else if($isArray) {
				$values[$key] = array();
				foreach($property as $p) {
					$values[$key][$p] = $getMethod == 'get' ? $item->get($p) : $item->$getMethod($p);
				}
			} else {
				$values[$key] = $getMethod == 'get' ? $item->get($property) : $item->$getMethod($property);
			}
		}
		return $values;
	}

	/**
	 * Return a new copy of this WireArray with the given item(s) appended
	 *
	 * Primarily for syntax convenience in fluent interfaces. 
	 * 
	 * ~~~~~
	 * if($page->parents->and($page)->has($featured)) { 
	 *   // either $page or its parents has the $featured page
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * #pw-group-fun-tools
	 * #pw-link [Introduction of and method](https://processwire.com/talk/topic/5098-new-wirearray-api-additions-on-dev/)
	 *
	 * @param Wire|WireArray $item Item(s) to append
	 * @return WireArray New WireArray containing this one and the given item(s). 
	 *
	 */
	public function ___and($item) {
		$a = $this->makeCopy();
		$a->add($item); 
		return $a; 
	}

	/**
	 * Store or retrieve an extra data value in this WireArray
	 *
	 * The data() function is exactly the same thing that it is in jQuery: <http://api.jquery.com/data/>. 
	 *
	 * ~~~~~~
	 * // set a data value named 'foo' to value 'bar'
	 * $a->data('foo', 'bar'); 
	 * 
	 * // retrieve the previously set data value
	 * $bar = $a->data('foo'); 
	 * 
	 * // get all previously set data
	 * $all = $a->data(); 
	 * ~~~~~~
	 * 
	 * #pw-group-other-data-storage
	 * #pw-link [Introduction of data method](https://processwire.com/talk/topic/5098-new-wirearray-api-additions-on-dev/)
	 *
	 * @param string|null|array|bool $key Name of data property you want to get or set, or: 
	 *  - Omit to get all data properties. 
	 *  - Specify associative array of [property => value] to set multiple properties. 
	 *  - Specify associative array and boolean TRUE for $value argument to replace all data with the new array given in $key.
	 *  - Specify regular array of property names to return multiple properties. 
	 *  - Specify boolean FALSE to unset property name specified in $value argument. 
	 * @param mixed|null|bool $value Value of data property you want to set. Omit when getting properties. 
	 *  - Specify boolean TRUE to replace all data with associative array of data given in $key argument. 
	 * @return WireArray|mixed|array|null Returns one of the following, depending on specified arguments: 
	 *  - `mixed` when getting a single property: whatever you set is what you will get back.
	 *  - `null` if the property you are trying to get does not exist in the data.
	 *  - `$this` reference to this WireArray if you were setting a value. 
	 *  - `array` of all data if you specified no arguments or requested multiple keys.
	 *
	 */
	public function data($key = null, $value = null) {
		if($key === null && $value === null) {
			// get all properties
			return $this->extraData;
		} else if(is_array($key)) {
			// get or set multiple properties
			if($value === true) {
				// replace all data with data in given $key array
				$this->extraData = $key;
			} else {
				// test if array is associative
				if(ctype_digit(implode('0', array_keys($key)))) {
					// regular, non-associative array, GET only requested properties
					$a = array();
					foreach($key as $k) {
						$a[$k] = isset($this->extraData[$k]) ? $this->extraData[$k] : null;
					}
					return $a;
				} else if(count($key)) {
					// associative array, setting multiple values to extraData
					$this->extraData = array_merge($this->extraData, $key);
				}
			}
		} else if($key === false && is_string($value)) {
			// unset a property
			unset($this->extraData[$value]); 
			
		} else if($value === null) {
			// get a property
			return isset($this->extraData[$key]) ? $this->extraData[$key] : null;
		} else {
			// set a property
			$this->extraData[$key] = $value;
		}
		return $this; 
	}

	/**
	 * Remove a property/value previously set with the WireArray::data() method. 
	 * 
	 * #pw-group-other-data-storage
	 *
	 * @param string $key Name of property you want to remove
	 * @return $this
	 *
	 */
	public function removeData($key) {
		unset($this->extraData[$key]); 
		return $this;
	}

	/**
	 * Enables use of $var('key')
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __invoke($key) {
		if(in_array($key, array('first', 'last', 'count'))) return $this->$key();
		if(is_int($key) || ctype_digit($key)) {
			if($this->usesNumericKeys()) {
				// if keys are already numeric, we use them
				return $this->get((int) $key); 
			} else {
				// if keys are not numeric, we delegete numbers to eq(n)
				return $this->eq((int) $key);
			}
		} else if(is_callable($key) || (is_string($key) && strpos($key, '{') !== false && strpos($key, '}'))) {
			return $this->each($key);
		}
		return $this->get($key);
	}

	/**
	 * Handler for when an unknown/unhooked method call is executed
	 * 
	 * If interested in hooking this, please see the `Wire::callUnknown()` method for more 
	 * details on the purpose and potential hooking implementation of this method. 
	 * 
	 * The implementation built-in to WireArray provides a couple of handy capabilities to all 
	 * WireArray derived classes (assuming that `$items` is an instance of any WireArray): 
	 * 
	 * - It enables you to call `$items->foobar()` and receive a regular PHP array 
	 *   containing the value of the "foobar" property from each item in this WireArray. 
	 *   It is equivalent to calling `$items->explode('foobar')`. Of course, substitute 
	 *   "foobar" with the name of any property present on items in the WireArray. 
	 * 
	 * - It enables you to call `$items->foobar(", ")` and receive a string containing
	 *   the value of the "foobar" property from each item, delimited by the string you
	 *   provided as an argument (a comma and space ", " in this case). This is equivalent
	 *   to calling `$items->implode(", ", "foobar")`. 
	 * 
	 * - Also note that if you call `$items->foobar(", ", $options)` where $options is an 
	 *   array, it is equivalent to `$items->implode(", ", "foobar", $options)`. 
	 * 
	 * ~~~~~
	 * // Get array of all "title" values from each item 
	 * $titlesArray = $items->title(); 
	 * 
	 * // Get a newline separated string of all "title" values from each item
	 * $titlesString = $items->title("\n"); 
	 * ~~~~~
	 * 
	 * #pw-hooker
	 * #pw-group-fun-tools
	 *
	 * @param string $method Requested method name
	 * @param array $arguments Arguments provided to the method
	 * @return null|mixed
	 * @throws WireException
	 *
	 */
	protected function ___callUnknown($method, $arguments) {
		
		if(!isset($arguments[0])) {
			// explode the property to an array
			return $this->explode($method);
			
		} else if(is_string($arguments[0])) {
			// implode the property identified by $method and glued by $arguments[0]
			// with optional $options as second argument
			$delimiter = $arguments[0];
			$options = array();
			if(isset($arguments[1]) && is_array($arguments[1])) $options = $arguments[1]; 
			return $this->implode($delimiter, $method, $options); 
			
		} else {
			// fail
			return parent::___callUnknown($method, $arguments); 
		}
	}

	/**
	 * Perform an action upon each item in the WireArray
	 * 
	 * This is typically used to execute a function for each item, or to build a string 
	 * or array from each item. 
	 * 
	 * ~~~~~
	 * // Generate navigation list of page children: 
	 * echo $page->children()->each(function($child) {
	 *   return "<li><a href='$child->url'>$child->title</a></li>";
	 * });
	 * 
	 * // If 2 arguments specified to custom function(), 1st is the key, 2nd is the value
	 * echo $page->children()->each(function($key, $child) {
	 *   return "<li><a href='$child->url'>$key: $child->title</a></li>";
	 * });
	 * 
	 * // Same as above using different method (template string):
	 * echo $page->children()->each("<li><a href='{url}'>{title}</a></li>");
	 * 
	 * // If WireArray used to hold non-object items, use only {key} and/or {value}
	 * echo $items->each('<li>{key}: {value}</li>');
	 * 
	 * // Get an array of all "title" properties 
	 * $titles = $page->children()->each("title"); 
	 * 
	 * // Get array of "title" and "url" properties. Returns an array
	 * // containing an associative array for each item with "title" and "url"
	 * $properties = $page->children()->each(["title", "url"]); 
	 * ~~~~~
	 * 
	 * #pw-group-traversal
	 * #pw-group-output-rendering
	 * #pw-group-fun-tools
	 * 
	 * @param callable|string|array|null $func Accepts any of the following:
	 * 1. Callable function that each item will be passed to as first argument. If this 
	 *    function returns a string, it will be appended to that of the other items and 
	 *    the result returned by this each() method.
	 * 2. Markup or text string with variable {tags} within it where each {tag} resolves
	 *    to a property in each item. This each() method will return the concatenated result.
	 * 3. A property name (string) common to items in this WireArray. The result will be 
	 *    returned as an array. 
	 * 4. An array of property names common to items in this WireArray. The result will be
	 *    returned as an array of associative arrays indexed by property name. 
	 *   	
	 * @return array|null|string|WireArray Returns one of the following (related to numbers above):
	 *   - `$this` (1a): WireArray if given a function that has no return values (if using option #1 in arguments). 
	 *   - `string` (1b): Containing the concatenated results of all function calls, if your function 
	 *     returns strings (if using option #1 in arguments). 
	 *   - `string` (2): Returns the processed and concatenated result (string) of all items in your 
	 *     template string (if using option #2 in arguments). 
	 *   - `array` (3): Returns regular PHP array of the property values for each item you requested
	 *     (if using option #3 in arguments). 
	 *   - `array` (4): Returns an array of associative arrays containing the property values for each item
	 *     you requested (if using option #4 in arguments). 
	 * @see WireArray::implode(), WireArray::explode()
	 * 
	 */
	public function each($func = null) {
		$result = null; // return value, if it's detected that one is desired
		if(is_callable($func)) {
			$funcInfo = new \ReflectionFunction($func);
			$useIndex = $funcInfo->getNumberOfParameters() > 1;
			foreach($this as $index => $item) {
				$val = $useIndex ? $func($index, $item) : $func($item);
				if($val && is_string($val)) {
					// function returned a string, so we assume they are wanting us to return the result
					if(is_null($result)) $result = '';
					// if returned value resulted in {tags}, go ahead and parse them
					if(strpos($val, '{') !== false && strpos($val, '}')) {
						if(is_object($item)) {
							$val = wirePopulateStringTags($val, $item);
						} else {
							$val = wirePopulateStringTags($val, array('key' => $index, 'value' => $item));
						}
					}
					$result .= $val;
				}
			}
		} else if(is_string($func) && strpos($func, '{') !== false && strpos($func, '}')) {
			// string with variables
			$result = '';
			foreach($this as $key => $item) {
				if(is_object($item)) {
					$result .= wirePopulateStringTags($func, $item);
				} else {
					$result .= wirePopulateStringTags($func, array('key' => $key, 'value' => $item));
				}
			}
		} else {
			// array or string or null
			if(is_null($func)) $func = 'name';
			$result = $this->explode($func);
		}
	
		return $result === null ? $this : $result;
	}

	/**
	 * Divide this WireArray into $qty slices and return array of them (each being another WireArray)
	 * 
	 * This is not destructive to the original WireArray as it returns new WireArray objects. 
	 *
	 * #pw-group-retrieval
	 * #pw-group-traversal
	 * 
	 * @param int $qty Number of slices
	 * @return array Array of WireArray objects
	 * 
	 */
	public function slices($qty) {
		$slices = array();
		if($qty < 1) return $slices;
		$total = $this->count();
		$limit = $total ? ceil($total / $qty) : 0;
		$start = 0;
		for($n = 0; $n < $qty; $n++) {
			if($start < $total) {
				$slice = $this->slice($start, $limit);
			} else {
				$slice = $this->makeNew();
			}
			$slices[] = $slice;
			$start += $limit;
		}
		return $slices;
	}

	/**
	 * Set the current duplicate checking state
	 * 
	 * Applies only to non-associative WireArray types. 
	 * 
	 * @param bool $value True to enable dup check, false to disable
	 * 
	 */
	public function setDuplicateChecking($value) {
		if(!$this->usesNumericKeys()) return;
		$this->duplicateChecking = (bool) $value;
	}

	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
	
		$info = array(
			'count' => $this->count(),
			'items' => array(),
		);
		
		$info = array_merge($info, parent::__debugInfo());
		
		if(count($this->data)) {
			$info['items'] = array();
			foreach($this->data as $key => $value) {
				if(is_object($value) && $value instanceof Wire) $key = $value->className() . ":$key";
				$info['items'][$key] = $this->debugInfoItem($value);
			}
		}

		if(count($this->extraData)) $info['extraData'] = $this->extraData;
		
		$trackers = array(
			'itemsAdded' => $this->itemsAdded, 
			'itemsRemoved' => $this->itemsRemoved
		);
		
		foreach($trackers as $key => $value) {
			if(!count($value)) continue;
			$info[$key] = array();
			foreach($value as $k => $v) {
				$info[$key][] = $this->debugInfoItem($v); 
			}
		}
		
		return $info;
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
		if(is_object($item)) {
			if($item instanceof Page) {
				$item = $item->debugInfoSmall();
			} else if($item instanceof WireData) {
				$_item = $item;
				$item = $item->get('name');
				if(!$item) $item = $_item->get('id');
				if(!$item) $item = $_item->className();
			} else {
				// keep $value as it is
			}
		}
		return $item;
	}

	/**
	 * Static method caller, primarily for support of WireArray::new() method
	 * 
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 * @throws WireException
	 * 
	 */
	public static function __callStatic($name, $arguments) {
		$class = get_called_class();
		if($name === 'new') {
			$n = count($arguments);
			if($n === 0) {
				// no items specified
				$items = null;
			} else if($n === 1) {
				$items = reset($arguments);
				if(is_array($items) || $items instanceof WireArray) {
					// multiple items specified in one argument				
				} else {
					// one item specified
					$items = array($items);
				}
			} else {
				// multiple items specified as arguments
				$items = $arguments;
			}
			return self::newInstance($items, $class);
		} else {
			throw new WireException("Unrecognized static method: $class::$name()");
		}
	}

	/**
	 * Create new instance of this class
	 * 
	 * Method for internal use, use `$a = WireArray::new($items)` or `$a = WireArrray($items)` instead. 
	 * 
	 * #pw-internal 
	 * 
	 * @param array|WireArray|null $items Items to add or omit (null) for none
	 * @param string $class Class name to instantiate or omit for called class
	 * @return WireArray
	 * 
	 */
	public static function newInstance($items = null, $class = '') {
		if(empty($class)) $class = get_called_class();
		/** @var WireArray $a */
		$a = new $class();
		if($items instanceof WireArray) {
			$items->wire($a);
			$a->import($items);
		} else if(is_array($items)) {
			if(ctype_digit(implode('0', array_keys($items)))) {
				$a->import($items);
			} else {
				$a->setArray($items);
			}
		} else if($items !== null) {
			$a->add($items);
		}
		return $a;
	}
}
