<?php namespace ProcessWire;

class LogEntriesArray extends PaginatedArray {
	public function isValidItem($item) {
		return is_array($item); 
	}
	public function makeBlankItem() {
		return array();
	}
	public function getItemKey($item) {
		return null;
	}
}
