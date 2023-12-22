<?php namespace ProcessWire;

/**
 * Page Version Info
 * 
 * For pages that are a version, this class represents the `_version` 
 * property of the page. It is also used as the return value for some
 * methods in the PagesVersions class to return version information.
 
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @property int $version Version number
 * @property string $description Version description (not entity encoded)
 * @property-read string $descriptionHtml Version description entity encoded for output in HTML
 * @property int $created Date/time created (unix timestamp)
 * @property-read string $createdStr Date/time created (YYYY-MM-DD HH:MM:SS)
 * @property int $modified Date/time last modified (unix timestamp)
 * @property-read string $modifiedStr Date/time last modified (YYYY-MM-DD HH:MM:SS)
 * @property int $pages_id ID of page this version is for
 * @property Page $page Page this version is for
 * @property int $created_users_id ID of user that created this version
 * @property-read User|NullPage $createdUser User that created this version
 * @property int $modified_users_id ID of user that last modified this version
 * @property-read User|NullPage $modifiedUser User that last modified this version
 * @property array $properties Native page properties array in format [ property => value ]
 * @property-read array $fieldNames Names of fields in this version 
 * @property string $action Populated with action name if info is being used for an action
 *
 */
class PageVersionInfo extends WireData {

	/**
	 * Value for $action property when restoring a page
	 * 
	 */
	const actionRestore = 'restore';

	/**
	 * @var Page
	 *
	 */
	protected $page;

	/**
	 * @param array $data
	 *
	 */
	public function __construct(array $data = []) {
		parent::__construct();
		$defaults = [
			'version' => 0,
			'description' => '', 
			'created' => 0,
			'modified' => 0, 
			'created_users_id' => 0,
			'modified_users_id' => 0,
			'pages_id' => 0, 
			'properties' => [], 
			'action' => '',
		];
		parent::setArray(array_merge($defaults, $data));
	}

	/**
	 * Set property
	 * 
	 * @param string $key
	 * @param string|int|Page $value
	 * @return self
	 * 
	 */
	public function set($key, $value) {
		if($key === 'version' || $key === 'pages_id') {
			$value = (int) $value;
		} else if($key === 'created' || $key === 'modified') {
			if($value) {
				$value = ctype_digit("$value") ? (int) $value : strtotime($value);
			} else {
				$value = 0;
			}
		} else if($key === 'created_users_id' || $key === 'modified_users_id') {
			$value = (int) $value;
		} else if($key === 'page') {
			$this->setPage($value);
		} else if($key === 'description') {
			$value = (string) $value;
		}
		return parent::set($key, $value);
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return mixed|NullPage|Page|User|null
	 * 
	 */
	public function get($key) {
		switch($key) {
			case 'page': return $this->getPage();
			case 'createdUser': return $this->getCreatedUser();
			case 'modifiedUser': return $this->getModifiedUser();
			case 'createdStr': return $this->created > 0 ? date('Y-m-d H:i:s', $this->created) : '';
			case 'modifiedStr': return $this->modified > 0 ? date('Y-m-d H:i:s', $this->modified) : '';
			case 'fieldNames': return $this->getFieldNames();
			case 'descriptionHtml': return $this->wire()->sanitizer->entities(parent::get('description'));
		}
		return parent::get($key);
	}

	/**
	 * Get page that this version is for
	 * 
	 * @return NullPage|Page
	 * 
	 */
	public function getPage() {
		if($this->page) return $this->page;
		if($this->pages_id) {
			$this->page = $this->wire()->pages->get($this->pages_id);
			return $this->page;
		}
		return new NullPage();
	}

	/**
	 * Set page that this version is for
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page;
		if($page->id) parent::set('pages_id', $page->id);
		$page->wire($this);
	}

	/**
	 * Get user that created this version
	 * 
	 * @return NullPage|User
	 * 
	 */
	public function getCreatedUser() {
		return $this->wire()->users->get($this->created_users_id);
	}
	
	/**
	 * Get user that last modified this version
	 *
	 * @return NullPage|User
	 *
	 */
	public function getModifiedUser() {
		$id = $this->modified_users_id;
		return $id ? $this->wire()->users->get($id) : $this->getCreatedUser();
	}

	/**
	 * Get native property names in this version
	 * 
	 * #pw-internal
	 * 
	 * @return string[]
	 * 
	 */
	public function getPropertyNames() {
		return array_keys($this->properties);
	}

	/**
	 * Get field names in this version
	 * 
	 * #pw-internal
	 * 
	 * @return string[]
	 * 
	 */
	public function getFieldNames() {
		$a = parent::get('fieldNames');
		if(!empty($a)) return $a; 
		$a = $this->wire()->pagesVersions->getPageVersionFields($this->pages_id, $this->version);
		$a = array_keys($a);
		parent::set('fieldNames', $a);
		return $a;
	}

	/**
	 * Get field and property names in this version
	 * 
	 * #pw-internal
	 * 
	 * @return string[]
	 * 
	 */
	public function getNames() {
		return array_merge($this->getPropertyNames(), $this->getFieldNames());
	}

	/**
	 * Set action for PagesVersions
	 * 
	 * #pw-internal
	 * 
	 * @param string $action
	 * 
	 */
	public function setAction($action) {
		parent::set('action', $action);
	}

	/**
	 * Get action for PagesVersions
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * 
	 */
	public function getAction() {
		return parent::get('action');
	}

	/**
	 * String value is version number as a string
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return (string) $this->version;
	}
}
