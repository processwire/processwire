<?php namespace ProcessWire;

/**
 * A WireArray of Inputfield instances, as used by InputfieldWrapper. 
 *
 * The default numeric indexing of a WireArray is not overridden.
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 */

class InputfieldsArray extends WireArray {
	
	public function __construct() {
		parent::__construct();
		$this->usesNumericKeys = true;
		$this->indexedByName = false;
	}

	/**
	 * Per WireArray interface, only Inputfield instances are accepted.
	 * 
	 * @param Wire $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Inputfield;
	}

	/**
	 * Extends the find capability of WireArray to descend into the Inputfield children
	 * 
	 * @param string $selector
	 * @return WireArray|InputfieldsArray
	 *
	 */
	public function find($selector) {
		/** @var WireArray|InputfieldsArray $a */
		$a = parent::find($selector);
		foreach($this as $item) {
			if(!$item instanceof InputfieldWrapper) continue;
			$children = $item->children();
			if(count($children)) $a->import($children->find($selector));
		}
		return $a;
	}

	public function makeBlankItem() {
		return null; // Inputfield is abstract, so there is nothing to return here
	}

}
