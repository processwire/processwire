<?php namespace ProcessWire;

/**
 * ProcessWire Templates
 *
 * WireArray of Template instances as used by Templates class.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class TemplatesArray extends WireArray {

	public function isValidItem($item) {
		return $item instanceof Template;
	}

	public function isValidKey($key) {
		return is_int($key) || ctype_digit($key);
	}

	public function getItemKey($item) {
		return $item->id;
	}

	public function makeBlankItem() {
		return $this->wire(new Template());
	}

}
