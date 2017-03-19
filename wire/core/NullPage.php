<?php namespace ProcessWire;

/**
 * ProcessWire NullPage
 * 
 * #pw-summary NullPage is a type of Page object returned by many API methods to indicate a non-match. 
 * #pw-body = 
 * The simplest way to detect a NullPage is typically by checking the value of the `$page->id` property.
 * If it happens to be 0 then for most practical purposes, you have a NullPage. A NullPage object
 * has all of the same methods and properties as a regular `Page` but there's not much point in 
 * calling upon them since they will always be empty. 
 * ~~~~~
 * $item = $pages->get("featured=1"); 
 * 
 * if(!$item->id) {
 *   // this is a NullPage
 * }
 * 
 * if($item instanceof NullPage) {
 *   // this is a NullPage
 * }
 * ~~~~~
 * #pw-body
 *
 * Placeholder class for non-existant and non-saveable Page.
 * Many API functions return a NullPage to indicate no match. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property int $id The id property will always be 0 for a NullPage. 
 *
 */

class NullPage extends Page {
	/**
	 * #pw-internal
	 * 
	 * @return string
	 * 
	 */
	public function path() { return ''; }

	/**
	 * #pw-internal
	 * 
	 * @param array $options
	 * @return string
	 * 
	 */
	public function url($options = array()) { return ''; }

	/**
	 * #pw-internal
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 * 
	 */
	public function set($key, $value) { return parent::setForced($key, $value); }

	/**
	 * #pw-internal
	 * 
	 * @param string $selector
	 * @return null
	 * 
	 */
	public function parent($selector = '') { return null; }

	/**
	 * #pw-internal
	 * 
	 * @param string $selector
	 * @return PageArray
	 * @throws WireException
	 * 
	 */
	public function parents($selector = '') { return $this->wire('pages')->newPageArray(); }

	/**
	 * #pw-internal
	 * 
	 * @return string
	 * 
	 */
	public function __toString() { return ""; }

	/**
	 * #pw-internal
	 * 
	 * @return bool
	 * 
	 */
	public function isHidden() { return true; }

	/**
	 * #pw-internal
	 * 
	 * @return null
	 * 
	 */
	public function filesManager() { return null; }

	/**
	 * #pw-internal
	 * 
	 * @return NullPage
	 * @throws WireException
	 * 
	 */
	public function ___rootParent() { return $this->wire('pages')->newNullPage(); }

	/**
	 * #pw-internal
	 * 
	 * @param string $selector
	 * @param array $options
	 * @return PageArray
	 * @throws WireException
	 * 
	 */
	public function siblings($selector = '', $options = array()) { return $this->wire('pages')->newPageArray(); }

	/**
	 * #pw-internal
	 * 
	 * @param string $selector
	 * @param array $options
	 * @return PageArray
	 * @throws WireException
	 * 
	 */
	public function children($selector = '', $options = array()) { return $this->wire('pages')->newPageArray(); }

	/**
	 * #pw-internal
	 * 
	 * @param string $type
	 * @return NullPage
	 * @throws WireException
	 * 
	 */
	public function getAccessParent($type = 'view') { return $this->wire('pages')->newNullPage(); }

	/**
	 * #pw-internal
	 * 
	 * @param string $type
	 * @return PageArray
	 * @throws WireException
	 * 
	 */
	public function getAccessRoles($type = 'view') { return $this->wire('pages')->newPageArray(); }

	/**
	 * #pw-internal
	 * 
	 * @param int|Role|string $role
	 * @param string $type
	 * @return bool
	 * 
	 */
	public function hasAccessRole($role, $type = 'view') { return false; }

	/**
	 * #pw-internal
	 * 
	 * @param string $what
	 * @return bool
	 * 
	 */
	public function isChanged($what = '') { return false; }
}

