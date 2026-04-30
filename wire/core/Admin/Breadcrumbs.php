<?php namespace ProcessWire;

/**
 * ProcessWire Breadcrumbs
 *
 * Provides basic breadcrumb capability 
 * 
 * This file is licensed under the MIT license.
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 *
 */


/**
 * class Breadcrumbs
 *
 * Holds multiple Breadcrumb items
 *
 */
class Breadcrumbs extends WireArray {

	public function isValidItem($item) {
		return $item instanceof Breadcrumb;
	}

	public function add($item) {

		if($item instanceof Page) {
			$page = $item; 
			$item = $this->wire(new Breadcrumb());
			$item->title = $page->get("title|name"); 
			$item->url = $page->url;
		} else if($item instanceof Breadcrumb) {
			$this->wire($item);
		}
		
		return parent::add($item); 
	}

}


