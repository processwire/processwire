<?php namespace ProcessWire;

/**
 * ProcessWire WireData
 *
 * This is the base data container class used throughout ProcessWire. 
 * It provides get and set access to properties internally stored in a $data array. 
 * Otherwise it is identical to the Wire class. 
 * 
 * #pw-summary WireData is the base data-storage class used by many ProcessWire object types and most modules.
 * #pw-body =
 * WireData is very much like its parent `Wire` class with the fundamental difference being that it is designed
 * for runtime data storage. It provides this primarily through the built-in `get()` and `set()` methods for
 * getting and setting named properties to WireData objects. The most common example of a WireData object is
 * `Page`, the type used for all pages in ProcessWire. 
 * 
 * Properties set to a WireData object can also be set or accessed directly, like `$item->property` or using 
 * array access like `$item[$property]`. If you `foreach()` a WireData object, the default behavior is to
 * iterate all of the properties/values present within it. 
 * #pw-body
 * 
 * May also be accessed as array. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @method WireArray and($items = null)
 *
 */

class WireData extends Wire implements \IteratorAggregate, \ArrayAccess {

	/**
	 * Array where get/set properties are stored
	 *
	 */
	protected $data = array(); 

	/**
	 * Set a value to this object’s data
	 * 
	 * ~~~~~
	 * // Set a value for a property
	 * $item->set('foo', 'bar');
	 * 
	 * // Set a property value directly
	 * $item->foo = 'bar';
	 * 
	 * // Set a property using array access
	 * $item['foo'] = 'bar';
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $key Name of property you want to set
	 * @param mixed $value Value of property
	 * @return $this
	 * @see WireData::setQuietly(), WireData::get()
	 *
	 */
	public function set($key, $value) {
		if($key === 'data') {
			if(!is_array($value)) $value = (array) $value;
			return $this->setArray($value); 
		}
		$v = isset($this->data[$key]) ? $this->data[$key] : null;
		if(!$this->isEqual($key, $v, $value)) $this->trackChange($key, $v, $value); 
		$this->data[$key] = $value; 
		return $this; 
	}

	/**
	 * Same as set() but without change tracking
	 *
	 * - If `$this->trackChanges()` is false, then this is no different than set(), since changes aren't being tracked. 
	 * - If `$this->trackChanges()` is true, then the value will be set quietly (i.e. not recorded in the changes list).
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $key Name of property you want to set
	 * @param mixed $value Value of property
	 * @return $this
	 * @see Wire::trackChanges(), WireData::set()
	 *
	 */
	public function setQuietly($key, $value) {
		$track = $this->trackChanges(); 
		$this->setTrackChanges(false);
		$this->set($key, $value);
		if($track) $this->setTrackChanges(true);
		return $this;
	}

	/**
	 * Is $value1 equal to $value2?
	 *
	 * This template method provided so that descending classes can optionally determine 
 	 * whether a change should be tracked. 
	 * 
	 * #pw-internal
	 *
	 * @param string $key Name of the property/key that triggered the check (see `WireData::set()`)
	 * @param mixed $value1 Comparison value
	 * @param mixed $value2 A second comparison value
	 * @return bool True if values are equal, false if not
	 *
	 */
	protected function isEqual($key, $value1, $value2) {
		if($key) {} // intentional to avoid unused argument notice
		// $key intentionally not used here, but may be used by descending classes
		return $value1 === $value2; 	
	}

	/**
	 * Set an array of key=value pairs
	 * 
	 * This is the same as the `WireData::set()` method except that it can set an array
	 * of properties at once.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param array $data Associative array of where the keys are property names, and values are… values.
	 * @return $this
	 * @see WireData::set()
	 *
	 */
	public function setArray(array $data) {
		foreach($data as $key => $value) $this->set($key, $value); 
		return $this; 
	}

	/**
	 * Provides direct reference access to set values in the $data array
	 * 
	 * @param string $key
	 * @param mixed $value
	 * return $this
	 *
	 */
	public function __set($key, $value) {
		$this->set($key, $value); 
	}

	/**
	 * Retrieve the value for a previously set property, or retrieve an API variable
	 *
	 * - If the given $key is an object, it will cast it to a string. 
	 * - If the given $key is a string with "|" pipe characters in it, it will try all till it finds a non-empty value. 
	 * - If given an API variable name, it will return that API variable unless the class has direct access API variables disabled.
	 * 
	 * ~~~~~
	 * // Retrieve the value of a property
	 * $value = $item->get("some_property"); 
	 * 
	 * // Retrieve the value of the first non-empty property:
	 * $value = $item->get("property1|property2|property2"); 
	 * 
	 * // Retrieve a value using array access
	 * $value = $item["some_property"];
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
 	 * @param string|object $key Name of property you want to retrieve. 
	 * @return mixed|null Returns value of requested property, or null if the property was not found. 
	 * @see WireData::set()
	 *
	 */
	public function get($key) {
		if(is_object($key)) $key = "$key";
		if(array_key_exists($key, $this->data)) return $this->data[$key]; 
		if(strpos($key, '|')) {
			$keys = explode('|', $key); 
			foreach($keys as $k) {
				/** @noinspection PhpAssignmentInConditionInspection */
				if($value = $this->get($k)) return $value;
			}
		}
		return parent::__get($key); // back to Wire
	}

	/**
	 * Get or set a low-level data value
	 * 
	 * Like get() or set() but will only get/set from the WireData's protected $data array. 
	 * This is used to bypass any extra logic a class may have added to its get() or set() 
	 * methods. The benefit of this method over get() is that it excludes API vars and potentially
	 * other things (defined by descending classes) that you may not want. 
	 * 
	 * - To get a value, simply omit the $value argument.
	 * - To set a value, specify both the $key and $value arguments. 
	 * - If you omit a $key and $value, this method will return the entire data array.
	 * 
	 * #pw-group-manipulation
	 * #pw-group-retrieval
	 * 
	 * ~~~~~
	 * // Set a property
	 * $item->data('some_property', 'some value'); 
	 * 
	 * // Get the value of a previously set property
	 * $value = $item->data('some_property'); 
	 * ~~~~~
	 * 
	 * @param string|array $key Property you want to get or set, or associative array of properties you want to set.
	 * @param mixed $value Optionally specify a value if you want to set rather than get. 
	 *  Or Specify boolean TRUE if setting an array via $key and you want to overwrite any existing values (rather than merge).
	 * @return array|WireData|null Returns one of the following: 
	 *   - `mixed` - Actual value if getting a previously set value. 
	 *   - `null` - If you are attempting to get a value that has not been set. 
	 *   - `$this` - If you are setting a value.
	 */
	public function data($key = null, $value = null) {
		if(is_null($key)) return $this->data;
		if(is_array($key)) {
			if($value === true) {
				$this->data = $key;
			} else {
				$this->data = array_merge($this->data, $key);
			}
			return $this;
		} else if(is_null($value)) {
			return isset($this->data[$key]) ? $this->data[$key] : null;
		} else {
			$this->data[$key] = $value; 
			return $this;
		}
	}

	/**
	 * Returns the full array of properties set to this object
	 * 
	 * If descending classes also store data in other containers, they may want to
	 * override this method to include that data as well.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return array Returned array is associative and indexed by property name. 
	 *
	 */
	public function getArray() {
		return $this->data; 
	}

	/**
	 * Get a property via dot syntax: field.subfield (static)
	 *
	 * Static version for internal core use. Use the non-static getDot() instead.
	 * 
	 * #pw-internal
	 *
	 * @param string $key 
	 * @param Wire $from The instance you want to pull the value from
	 * @return null|mixed Returns value if found or null if not
	 *
	 */
	public static function _getDot($key, Wire $from) {
		$key = trim($key, '.');
		if(strpos($key, '.')) {
			// dot present
			$keys = explode('.', $key); // convert to array
			$key = array_shift($keys); // get first item
		} else {
			// dot not present
			$keys = array();
		}
		if($from->wire($key) !== null) return null; // don't allow API vars to be retrieved this way
		if($from instanceof WireData) $value = $from->get($key);
			else if($from instanceof WireArray) $value = $from->getProperty($key);
			else $value = $from->$key;
		if(!count($keys)) return $value; // final value
		if(is_object($value)) {
			if(count($keys) > 1) {
				$keys = implode('.', $keys); // convert back to string
				if($value instanceof WireData) $value = $value->getDot($keys); // for override potential
					else $value = self::_getDot($keys, $value);
			} else {
				$key = array_shift($keys);
				// just one key left, like 'title'
				if($value instanceof WireData) {
					$value = $value->get($key);
				} else if($value instanceof WireArray) {
					if($key == 'count') {
						$value = count($value);
					} else {
						$a = array();
						foreach($value as $v) $a[] = $v->get($key); 	
						$value = $a; 
					}
				}
			}
		} else {
			// there is a dot property remaining and nothing to send it to
			$value = null; 
		}
		return $value; 
	}

	/**
	 * Get a property via dot syntax: field.subfield.subfield
	 *
	 * Some classes descending WireData may choose to add a call to this as part of their 
	 * get() method as a syntax convenience.
	 * 
	 * ~~~~~
	 * $value = $item->get("parent.title"); 
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string $key Name of property you want to retrieve in "a.b" or "a.b.c" format
	 * @return null|mixed Returns value if found or null if not
	 *
	 */
	public function getDot($key) {
		return self::_getDot($key, $this); 
	}

	/**
	 * Provides direct reference access to variables in the $data array
	 *
	 * Otherwise the same as get()
	 *
	 * @param string $key
	 * @return mixed|null
	 *
	 */
	public function __get($key) {
		return $this->get($key); 
	}

	/**
	 * Enables use of $var('key')
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function __invoke($key) {
		return $this->get($key);
	}

	/**
	 * Remove a previously set property
	 * 
	 * ~~~~~
	 * $item->remove('some_property'); 
	 * ~~~~~
	 * 
	 * #pw-group-manipulation
	 *
	 * @param string $key Name of property you want to remove
	 * @return $this
	 *
	 */
	public function remove($key) {
		$value = isset($this->data[$key]) ? $this->data[$key] : null;
		$this->trackChange("unset:$key", $value, null); 
		unset($this->data[$key]); 
		return $this;
	}

	/**
	 * Enables the object data properties to be iterable as an array
	 * 
	 * ~~~~~
	 * foreach($item as $key => $value) {
	 *   // ...
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return \ArrayObject
	 *
	 */
	public function getIterator() {
		return new \ArrayObject($this->data); 
	}

	/**
	 * Does this object have the given property?
	 * 
	 * ~~~~~
	 * if($item->has('some_property')) {
	 *   // the item has some_property
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param string $key Name of property you want to check.
	 * @return bool True if it has the property, false if not.
	 *
	 */
	public function has($key) {
		return ($this->get($key) !== null); 
	}

	/**
	 * Take the current item and append the given item(s), returning a new WireArray
	 *
	 * This is for syntactic convenience in fluent interfaces. 
	 * ~~~~~
	 * if($page->and($page->parents)->has("featured=1")) { 
	 *    // page or one of its parents has a featured property with value of 1
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 *
	 * @param WireArray|WireData|string|null $items May be any of the following: 
	 *   - `WireData` object (or derivative)
	 *   - `WireArray` object (or derivative)
	 *   - Name of any property from this object that returns one of the above. 
	 *   - Omit argument to simply return this object in a WireArray
	 * @return WireArray Returns a WireArray of this object *and* the one(s) given. 
	 * @throws WireException If invalid argument supplied.
	 *
	 */
	public function ___and($items = null) {

		if(is_string($items)) $items = $this->get($items); 

		if($items instanceof WireArray) {
			// great, that's what we want
			$a = clone $items; 
			$a->prepend($this);
		} else if($items instanceof WireData || is_null($items)) {
			// single item
			$className = $this->className(true) . 'Array';
			if(!class_exists($className)) $className = wireClassName('WireArray', true);
			$a = $this->wire(new $className());
			$a->add($this);
			if($items) $a->add($items);
		} else {
			// unknown
			throw new WireException('Invalid argument provided to WireData::and(...)'); 
		}

		return $a; 
	}

	/**
	 * Ensures that isset() and empty() work for this classes properties. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return bool
	 *
	 */
	public function __isset($key) {
		return isset($this->data[$key]);
	}

	/**
	 * Ensures that unset() works for this classes data. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 *
	 */
	public function __unset($key) {
		$this->remove($key); 
	}

	/**
	 * Sets an index in the WireArray.
	 *
	 * For the ArrayAccess interface.
	 * 
	 * #pw-internal
	 *
	 * @param int|string $key Key of item to set.
	 * @param int|string|array|object $value Value of item.
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
	 * @return int|string|array|object Value of item requested, or false if it doesn't exist.
	 * 
	 */
	public function offsetGet($key) {
		$value = $this->get($key);
		return is_null($value) ? false : $value;
	}

	/**
	 * Unsets the value at the given index.
	 *
	 * For the ArrayAccess interface.
	 * 
	 * #pw-internal
	 *
	 * @param int|string $key Key of the item to unset.
	 * @return bool True if item existed and was unset. False if item didn't exist.
	 * 
	 */
	public function offsetUnset($key) {
		if($this->__isset($key)) {
			$this->remove($key);
			return true;
		} else {
			return false;
		}
	}


	/**
	 * Determines if the given index exists in this WireData.
	 *
	 * For the ArrayAccess interface.
	 * 
	 * #pw-internal
	 *
	 * @param int|string $key Key of the item to check for existence.
	 * @return bool True if the item exists, false if not.
	 * 
	 */
	public function offsetExists($key) {
		return $this->__isset($key);
	}

	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		if(count($this->data)) $info['data'] = $this->data; 
		return $info; 
	}

}

