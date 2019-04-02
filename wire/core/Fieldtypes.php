<?php namespace ProcessWire;

/**
 * ProcessWire Fieldtypes
 *
 * #pw-summary Maintains a collection of Fieldtype modules.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */
class Fieldtypes extends WireArray {

	/**
	 * @var bool
	 * 
	 */
	protected $preloaded = false;

	/**
	 * Construct this Fieldtypes object and load all Fieldtype modules 
	 *
 	 */
	public function init() {
		foreach($this->wire('modules') as $module) {
			if(strpos($module->className(), 'Fieldtype') === 0) {
				// if($module instanceof ModulePlaceholder) $module = $this->wire('modules')->get($module->className());
				$this->add($module); 
			}
		}
	}

	/**
	 * Convert all ModulePlaceholders to Fieldtype modules
	 * 
	 */
	protected function preload() {
		if($this->preloaded) return;
		$debug = $this->wire('config')->debug; 
		if($debug) Debug::timer('Fieldtypes.preload'); 
		foreach($this->data as $key => $module) {
			if($module instanceof ModulePlaceholder) {
				$fieldtype = $this->wire('modules')->get($module->className()); 
				$this->data[$key] = $fieldtype; 
			}
		}
		if($debug) Debug::saveTimer('Fieldtypes.preload'); 
		$this->preloaded = true; 
	}

	/**
	 * Per WireArray interface, items added to Fieldtypes must be Fieldtype instances
	 * 
	 * @param Wire|Fieldtype $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Fieldtype || $item instanceof ModulePlaceholder; 
	}

	/**
	 * Per the WireArray interface, keys must be strings (field names)
	 * 
	 * @param string|int $key
	 * @return bool
	 *
	 */
	public function isValidKey($key) {
		return is_string($key); 
	}

	/**
	 * Per the WireArray interface, Fields are indxed by their name
	 * 
	 * @param Fieldtype $item
	 * @return string
	 *
	 */
	public function getItemKey($item) {
		return $item->className();
	}

	/**
	 * Does this WireArray use numeric keys only? 
	 *
	 * @return bool
	 *
	 */
	protected function usesNumericKeys() {
		return false;
	}

	/**
	 * Per the WireArray interface, return a blank copy
	 *
	 * Since Fieldtype is abstract, there is nothing but NULL to return here
	 * 
	 * @return null
	 *
	 */
	public function makeBlankItem() {
		return null; 
	}

	/**
	 * Given a Fieldtype name (or class name) return the instantiated Fieldtype module. 
	 *
	 * If the requested Fieldtype is not already installed, it will be installed here automatically. 
	 *
	 * @param string $key Fieldtype name or class name, or dynamic property of Fieldtypes
	 * @return Fieldtype|null 
	 *
	 */
	public function get($key) {

		if(strpos($key, 'Fieldtype') !== 0) $key = "Fieldtype" . ucfirst($key); 

		if(!$fieldtype = parent::get($key)) {
			$fieldtype = $this->wire('modules')->get($key); 
		}

		if($fieldtype instanceof ModulePlaceholder) {
			$fieldtype = $this->wire('modules')->get($fieldtype->className()); 			
			$this->set($key, $fieldtype); 
		}

		return $fieldtype; 
	}

	/**
	 * Below we account for all get() related functions in WireArray to preload the fieldtypes
	 * 
	 * This ensures there are no ModulePlaceholders present when results from any of these methods.
	 * 
	 */

	public function getArray() { $this->preload(); return parent::getArray(); }
	public function getAll() { $this->preload(); return parent::getAll(); }
	public function getValues() { $this->preload(); return parent::getValues(); }
	public function getRandom($num = 1, $alwaysArray = false) { $this->preload(); return parent::getRandom($num, $alwaysArray);  }
	public function slice($start, $limit = 0) { $this->preload(); return parent::slice($start, $limit);  }
	public function shift() { $this->preload(); return parent::shift(); }
	public function pop() { $this->preload(); return parent::pop(); }
	public function eq($num) { $this->preload(); return parent::eq($num); }
	public function first() { $this->preload(); return parent::first(); }
	public function last() { $this->preload(); return parent::last(); }
	public function sort($properties, $flags = null) { $this->preload(); return parent::sort($properties, $flags); }
	protected function filterData($selectors, $not = false) { $this->preload(); return parent::filterData($selectors, $not); }
	public function makeCopy() { $this->preload(); return parent::makeCopy(); }
	public function makeNew() { $this->preload(); return parent::makeNew(); }
	public function getIterator() { $this->preload(); return parent::getIterator(); }
	public function getNext($item, $strict = true) { $this->preload(); return parent::getNext($item, $strict); }
	public function getPrev($item, $strict = true) { $this->preload(); return parent::getPrev($item, $strict); }
}


