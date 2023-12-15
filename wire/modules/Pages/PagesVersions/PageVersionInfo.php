<?php namespace ProcessWire;

/**
 * Page Version Info
 * 
 * For pages that are a version, this class represents 
 * the `_version` property of the page.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @property int $version
 * @property string $description
 * @property int $created
 * @property int $modified
 * @property int $pages_id
 * @property Page $page
 * @property int $created_users_id
 * @property int $modified_users_id
 * @property-read User|NullPage $createdUser
 * @property-read User|NullPage $modifiedUser
 * @property-read string $createdStr
 * @property-read string $modifiedStr
 * @property string $action
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
