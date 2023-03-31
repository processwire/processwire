<?php namespace ProcessWire;

/**
 * class Breadcrumb
 *
 * Holds a single breadcrumb item with URL and title
 *
 */
class Breadcrumb extends WireData {
	public function __construct($url = '', $title = '') {
		$this->set('url', $url);
		$this->set('title', $title);
		$this->set('titleMarkup', '');
		parent::__construct();
	}
}
