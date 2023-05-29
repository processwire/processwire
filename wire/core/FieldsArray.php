<?php namespace ProcessWire;

/**
 * ProcessWire Fields Array
 * 
 * WireArray of Field instances, as used by Fields class
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class FieldsArray extends WireArray {

	/**
	 * Per WireArray interface, only Field instances may be added
	 * 
	 * @param Wire $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Field;
	}

	/**
	 * Per WireArray interface, Field keys have to be integers
	 * 
	 * @param int $key
	 * @return bool
	 *
	 */
	public function isValidKey($key) {
		return is_int($key) || ctype_digit($key);
	}

	/**
	 * Per WireArray interface, Field instances are keyed by their ID
	 * 
	 * @param Field $item
	 * @return int
	 *
	 */
	public function getItemKey($item) {
		return $item->id;
	}

	/**
	 * Per WireArray interface, return a blank Field
	 * 
	 * @return Field
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Field());
	}
}
