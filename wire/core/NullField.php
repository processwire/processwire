<?php namespace ProcessWire;

/**
 * ProcessWire NullField
 *
 * Represents a Field object that doesn't exist. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class NullField extends Field {
	public function get($key) {
		if($key == 'id') return 0;
		if($key == 'name') return '';
		return parent::get($key); 
	}
}
