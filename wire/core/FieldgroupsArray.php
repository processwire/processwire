<?php namespace ProcessWire;

/**
 * ProcessWire Fieldgroups Array
 *
 * WireArray of Fieldgroup instances as used by Fieldgroups class. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */
class FieldgroupsArray extends WireArray {

	/**
	 * Per WireArray interface, this class only carries Fieldgroup instances
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Fieldgroup;
	}

	/**
	 * Per WireArray interface, items are keyed by their ID
	 *
	 */
	public function getItemKey($item) {
		return $item->id;
	}

	/**
	 * Per WireArray interface, keys must be integers
	 *
	 */
	public function isValidKey($key) {
		return is_int($key);
	}

	/**
	 * Per WireArray interface, return a blank Fieldgroup
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Fieldgroup());
	}

}
