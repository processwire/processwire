<?php namespace ProcessWire;

class ListerBookmarks extends Wire {
	
	/**
	 * Indicates public bookmark stored in module settings
	 *
	 */
	const typePublic = 0;

	/**
	 * Indicates user-owned bookmark stored in user meta data
	 *
	 */
	const typeOwned = 1;

	/**
	 * @var ProcessPageLister
	 *
	 */
	protected $lister;

	/**
	 * Cached user settings, becomes array once loaded
	 *
	 * @var null|array
	 *
	 */
	protected $userSettings = null;

	/**
	 * User ID associated with above user settings (just in case it changes)
	 *
	 * @var int
	 *
	 */
	protected $userSettingsID = 0;

	/**
	 * Module config settings, becomes array once loaded
	 *
	 * @var null|array
	 *
	 */
	protected $moduleConfig = null;

	/**
	 * Page that Lister bookmarks are for
	 *
	 * @var Page|null
	 *
	 */
	protected $page;

	/**
	 * User that Lister bookmarks are for
	 *
	 * @var User|null
	 *
	 */
	protected $user;

	/**
	 * Construct
	 *
	 * @param Page $page
	 * @param User $user
	 * 
	 */
	public function __construct(Page $page, User $user) {
		$page->wire($this);
		$this->page = $page;
		$this->user = $user;
		parent::__construct();
	}
	
	/**
	 * Set the Lister page that bookmarks will be for
	 *
	 * @param Page $page
	 *
	 */
	public function setPage(Page $page) {
		$this->page = $page;
	}

	/**
	 * Set user that bookmarks will be for
	 *
	 * @param User $user
	 *
	 */
	public function setUser(User $user) {
		$this->user = $user;
	}
	
	/**
	 * Get owned bookmarks
	 *
	 * @param int $userID
	 * @return array
	 *
	 */
	public function getOwnedBookmarks($userID = 0) {

		$settings = $this->getUserSettings($userID);
		$bookmarks = array();

		if(!isset($settings['bookmarks'])) $settings['bookmarks'] = array();

		foreach($settings['bookmarks'] as $bookmarkID => $bookmark) {
			if(empty($bookmarkID) || empty($bookmark['title'])) continue;
			$bookmarkID = $this->bookmarkStrID($bookmarkID, self::typeOwned);
			$bookmark = $this->wakeupBookmark($bookmark, $bookmarkID, self::typeOwned);
			if(!$bookmark) continue;
			if($userID && $userID != $this->user->id && empty($bookmark['share'])) continue;
			$bookmarks[$bookmarkID] = $bookmark;
		}

		return $bookmarks;
	}

	/**
	 * Save owned bookmarks
	 *
	 * @param array $bookmarks
	 *
	 */
	public function saveOwnedBookmarks(array $bookmarks) {

		$settings = $this->getUserSettings();
		$saveBookmarks = array();

		if(!isset($settings['bookmarks'])) $settings['bookmarks'] = array();

		// prep for save
		foreach($bookmarks as $bookmarkID => $bookmark) {
			if(empty($bookmark['title'])) continue;
			$bookmark = $this->sleepBookmark($bookmark);
			$bookmarkID = $this->bookmarkStrID($bookmarkID, self::typeOwned);
			$saveBookmarks[$bookmarkID] = $bookmark;
		}

		$bookmarks = $saveBookmarks;

		if($settings['bookmarks'] !== $bookmarks) {
			$settings['bookmarks'] = $bookmarks;
			if(empty($bookmarks)) unset($settings['bookmarks']);
			$this->message('Updated owned bookmarks', Notice::debug);
			$this->saveUserSettings($settings);
		}
	}

	/**
	 * Get user’s lister settings for current page
	 *
	 * @param int $userID
	 * @return array
	 *
	 */
	public function getUserSettings($userID = 0) {

		$pageKey = $this->strID($this->page->id);
		$userSettings = array();

		if($userID === $this->user->id) $userID = 0;

		if($userID) {
			// other user
			$user = $this->wire('users')->get((int) $userID);
			if($user && $user->id) {
				$userSettings = $user->meta('lister');
				if(!is_array($userSettings)) $userSettings = array();
			}
		} else if($this->userSettings !== null && $this->user->id === $this->userSettingsID) {
			$userSettings = $this->userSettings;
		} else {
			$userSettings = $this->user->meta('lister');
			if(!is_array($userSettings)) $userSettings = array();
			$this->userSettings = $userSettings;
			$this->userSettingsID = $this->user->id;
		}

		if(!isset($userSettings[$pageKey])) {
			$userSettings[$pageKey] = array();
			if(!$userID) $this->userSettings = $userSettings;
		}

		return $userSettings[$pageKey];
	}

	/**
	 * Save user settings for current page
	 *
	 * @param array $settings
	 * @return bool
	 *
	 */
	public function saveUserSettings(array $settings) {

		$pageKey = $this->strID($this->page->id);
		$userSettings = $this->getUserSettings();
		$userSettings[$pageKey] = $settings;

		foreach($userSettings as $key => $value) {

			if(!$this->isID($key)) continue; // not a pageKey setting

			// remove empty keys and settings
			if(is_array($value)) {
				foreach($value as $k => $v) {
					if(empty($v)) unset($value[$k]); // i.e. an empty $value['bookmarks']
				}
			}

			$userSettings[$key] = $value;
			if(!$this->isValidPageKey($key)) $value = array(); // maintenance
			if(empty($value)) unset($userSettings[$key]);
		}

		// if no changes, exit now
		if($userSettings === $this->userSettings) return false;

		// save user settings
		$this->user->meta('lister', $userSettings);
		$this->userSettings = $userSettings;
		$this->message('Updated user settings', Notice::debug);

		return true;
	}

	/**
	 * Get public bookmarks (from module config)
	 *
	 * @return array
	 *
	 */
	public function getPublicBookmarks() {

		$pageKey = $this->strID($this->page->id);
		$moduleConfig = $this->getModuleConfig();
		$bookmarks = array();

		if(!isset($moduleConfig['bookmarks'][$pageKey])) {
			$moduleConfig['bookmarks'][$pageKey] = array();
		}

		foreach($moduleConfig['bookmarks'][$pageKey] as $bookmarkID => $bookmark) {
			if(empty($bookmarkID) || empty($bookmark['title'])) continue;
			$bookmarkID = $this->bookmarkStrID($bookmarkID, self::typePublic);
			$bookmark = $this->wakeupBookmark($bookmark, $bookmarkID, self::typePublic);
			if($bookmark) $bookmarks[$bookmarkID] = $bookmark;
		}

		$moduleConfig['bookmarks'][$pageKey] = $bookmarks;
		$this->setModuleConfig($moduleConfig);

		return $bookmarks;
	}

	/**
	 * Save public bookmarks (to module config)
	 *
	 * @param array $bookmarks
	 * @return bool
	 *
	 */
	public function savePublicBookmarks(array $bookmarks) {

		$pageKey = $this->strID($this->page->id);
		$moduleConfig = $this->getModuleConfig();
		$saveBookmarks = array();

		if(isset($moduleConfig['bookmarks'][$pageKey])) {
			// if given bookmarks are identical to what is in module config, there are no changes to save
			if($bookmarks === $moduleConfig['bookmarks'][$pageKey]) return false;
		}

		// prep bookmarks for save
		foreach($bookmarks as $bookmarkID => $bookmark) {

			// don't save bookmarks that lack a title or of the wrong type
			if(empty($bookmark['title'])) continue;
			if($bookmark['type'] != self::typePublic) continue;

			// assign IDs for any bookmarks that don't have them
			if(empty($bookmarkID)) {
				$bookmarkID = time();
				while(isset($bookmarks["_$bookmarkID"])) $bookmarkID++;
			}

			$bookmarkID = $this->bookmarkStrID($bookmarkID, self::typePublic);
			$saveBookmarks[$bookmarkID] = $this->sleepBookmark($bookmark);
		}

		$bookmarks = $saveBookmarks;

		if(empty($bookmarks)) {
			// remove if empty...
			unset($moduleConfig['bookmarks'][$pageKey]);
		} else {
			// ...otherwise populate
			$moduleConfig['bookmarks'][$pageKey] = $bookmarks;
		}

		// check if any bookmarks in module config are for pages that no longer exist
		foreach($moduleConfig['bookmarks'] as $key => $bookmarks) {
			if(!$this->isValidPageKey($key)) {
				$this->warning("Removed expired bookmark for page $key", Notice::debug);
				unset($moduleConfig['bookmarks'][$key]);
			}
		}

		// if there are changes, save them
		return $this->saveModuleConfig($moduleConfig);
	}

	/**
	 * Save all bookmarks (whether public or owned)
	 *
	 * @param array $allBookmarks
	 *
	 */
	public function saveBookmarks(array $allBookmarks) {

		// save owned (user) bookmarks
		$ownedBookmarks = $this->filterBookmarksByType($allBookmarks, self::typeOwned);
		$this->saveOwnedBookmarks($ownedBookmarks);

		if($this->user->isSuperuser()) {
			$publicBookmarks = $this->filterBookmarksByType($allBookmarks, self::typePublic);
			$this->savePublicBookmarks($publicBookmarks);
		}
	}

	/**
	 * Get all bookmarks (public and owned)
	 *
	 * @return array
	 *
	 */
	public function getAllBookmarks() {
		$publicBookmarks = $this->getPublicBookmarks();
		$ownedBookmarks = $this->getOwnedBookmarks();
		$allBookmarks = array_merge($publicBookmarks, $ownedBookmarks);
		return $allBookmarks;
	}

	/**
	 * Get configured bookmarks allowed for current user, indexed by bookmark ID (int)
	 *
	 * @return array
	 *
	 */
	public function getBookmarks() {
		$bookmarks = array();
		foreach($this->getAllBookmarks() as $bookmarkID => $bookmark) {
			if(!$this->isBookmarkViewable($bookmark)) continue;
			$bookmarks[$bookmarkID] = $bookmark;
		}
		return $bookmarks;
	}


	/**
	 * Get a bookmark by ID (whether public or owned)
	 *
	 * @param string|int $bookmarkID
	 * @param int|null $type
	 * @return array|null
	 *
	 */
	public function getBookmark($bookmarkID, $type = null) {
		if($type === null && strpos($bookmarkID, $this->typePrefix(self::typeOwned)) !== false) {
			$type = self::typeOwned;
		}
		if($type === self::typeOwned) {
			$prefix = $this->typePrefix(self::typeOwned);
			if(strpos($bookmarkID, $prefix) > 0) {
				// 123O456 where 123 is user ID and 456 is bookmark ID
				list($userID, $bookmarkID) = explode($prefix, $bookmarkID);
				$userID = (int) $userID;
				if($userID === $this->user->id) $userID = 0;
				$bookmarkID = $prefix . ((int) $bookmarkID);
			} else {
				$bookmarkID = $this->_bookmarkID($bookmarkID);
				$userID = 0;
			}
			$bookmarks = $this->getOwnedBookmarks($userID);
			$bookmarkID = $this->bookmarkStrID($bookmarkID, self::typeOwned);

		} else {
			$bookmarks = $this->getPublicBookmarks();
			$bookmarkID = $this->bookmarkStrID($bookmarkID, self::typePublic);
		}

		$bookmark = isset($bookmarks[$bookmarkID]) ? $bookmarks[$bookmarkID] : null;

		return $bookmark;
	}

	/**
	 * Get the URL for a bookmark
	 *
	 * @param string $bookmarkID
	 * @param User|null $user
	 * @return string
	 *
	 */
	public function getBookmarkUrl($bookmarkID, $user = null) {
		if(strpos($bookmarkID, $this->typePrefix(self::typeOwned)) === 0) {
			if($user) $bookmarkID = $user->id . $bookmarkID;
		} else {
			$bookmarkID = $this->intID($bookmarkID);
		}
		return $this->page->url . "bm$bookmarkID";
	}

	/**
	 * Get the URL for a bookmark
	 *
	 * @param string $bookmarkID
	 * @return string
	 *
	 */
	public function getBookmarkEditUrl($bookmarkID) {
		return $this->page->url . "edit-bookmark/?bookmark=$bookmarkID";
	}

	/**
	 * Get the title for the given bookmark ID or bookmark array
	 *
	 * @param int|array $bookmarkID
	 * @return mixed|string
	 * @throws WireException
	 *
	 */
	public function getBookmarkTitle($bookmarkID) {
		if(is_array($bookmarkID)) {
			$bookmark = $bookmarkID;
		} else {
			$bookmark = $this->getBookmark($bookmarkID);
			if(empty($bookmark)) return '';
		}
		$languages = $this->wire('languages');
		$title = $bookmark['title'];
		if($languages) {
			$user = $this->wire('user');
			if(!$user->language->isDefault() && !empty($bookmark["title$user->language"])) {
				$title = $bookmark["title$user->language"];
			}
		}
		return $title;
	}

	/**
	 * Delete a bookmark by ID
	 *
	 * @param int $bookmarkID
	 * @return bool
	 *
	 */
	public function deleteBookmarkByID($bookmarkID) {

		$bookmark = $this->getBookmark($bookmarkID);
		
		if(!$bookmark) return false;
		if(!$this->isBookmarkDeletable($bookmark)) return false;

		if($bookmark['type'] == self::typeOwned) {
			$bookmarks = $this->getOwnedBookmarks();
			unset($bookmarks[$bookmarkID]);
			$this->saveOwnedBookmarks($bookmarks);
		} else {
			$bookmarks = $this->getPublicBookmarks();
			unset($bookmarks[$bookmarkID]);
			$this->savePublicBookmarks($bookmarks);
		}

		return true;
	}

	/**
	 * Filter bookmarks, removing those that are not of the requested type
	 *
	 * @param array $allBookmarks
	 * @param int $type
	 * @return array
	 *
	 */
	public function filterBookmarksByType(array $allBookmarks, $type) {
		$filteredBookmarks = array();
		foreach($allBookmarks as $key => $bookmark) {
			if(!isset($bookmark['type'])) $bookmark['type'] = self::typePublic;
			if($bookmark['type'] != $type) continue;
			$filteredBookmarks[$key] = $bookmark;
		}
		return $filteredBookmarks;
	}

	/**
	 * Filter bookmarks, removing those user does not have access to
	 *
	 * @param array $bookmarks
	 * @return array
	 *
	 */
	public function filterBookmarksByAccess(array $bookmarks) {
		foreach($bookmarks as $key => $bookmark) {
			if(!$this->isBookmarkViewable($bookmark)) unset($bookmarks[$key]);
		}
		return $bookmarks;
	}

	/**
	 * Is the given bookmark editable?
	 *
	 * @param array $bookmark
	 * @return bool
	 *
	 */
	public function isBookmarkEditable(array $bookmark) {
		if($this->user->isSuperuser()) return true;
		if($bookmark['type'] == self::typePublic) return false;
		return true;
	}

	/**
	 * Is the given bookmark viewable?
	 *
	 * @param array $bookmark
	 * @return bool
	 *
	 */
	public function isBookmarkViewable(array $bookmark) {

		if(empty($bookmark['roles'])) return true;
		if($this->user->isSuperuser()) return true;

		$userRoles = $this->user->roles;
		$viewable = false;

		foreach($bookmark['roles'] as $roleID) {
			foreach($userRoles as $userRole) {
				if($userRole->id == $roleID) {
					$viewable = true;
					break;
				}
			}
		}

		return $viewable;
	}

	/**
	 * Is the given bookmark deletable?
	 *
	 * @param array $bookmark
	 * @return bool
	 *
	 */
	public function isBookmarkDeletable(array $bookmark) {
		return $this->isBookmarkEditable($bookmark);
	}

	/**
	 * Get a template array for a bookmark
	 *
	 * @param array $bookmark
	 * @return array
	 *
	 */
	public function _bookmark(array $bookmark = array()) {
		$template = array(
			'id' => '',
			'title' => '',
			'desc' => '',
			'selector' => '',
			'columns' => array(),
			'sort' => '',
			'type' => self::typePublic,
			'roles' => array(),
			'share' => false,
		);

		return empty($bookmark) ? $template : array_merge($template, $bookmark);
	}

	/**
	 * Sanitize a bookmark ID
	 *
	 * @param string|array $bookmarkID
	 * @return string
	 *
	 */
	public function _bookmarkID($bookmarkID) {
		if(is_array($bookmarkID)) {
			$bookmark = $bookmarkID;
			$type = $bookmark['type'];
			$bookmarkID = $bookmark['id'];
		} else {
			$type = self::typePublic;
			$bookmarkID = (string) $bookmarkID;
			$ownedPrefix = $this->typePrefix(self::typeOwned);
			if(strpos($bookmarkID, $ownedPrefix) !== false) {
				list($userID, $bookmarkID) = explode($ownedPrefix, $bookmarkID);
				$userID = empty($userID) ? '' : (int) $userID;
				$bookmarkID = $userID . $ownedPrefix . ((int) $bookmarkID);
				return $bookmarkID;
			} else {
				$bookmarkID = ltrim($bookmarkID, $this->typePrefix(self::typePublic));
			}
		}
		if(!ctype_digit("$bookmarkID")) return '';
		return $this->typePrefix($type) . ((int) $bookmarkID);
	}

	protected function getModuleConfig() {
		if($this->moduleConfig === null) {
			$this->moduleConfig = $this->wire('modules')->getConfig('ProcessPageLister');
		}
		if(!isset($this->moduleConfig['bookmarks'])) $this->moduleConfig['bookmarks'] = array();
		return $this->moduleConfig;
	}

	protected function setModuleConfig(array $moduleConfig) {
		$this->moduleConfig = $moduleConfig;
	}

	protected function saveModuleConfig(array $moduleConfig) {
		if($moduleConfig === $this->moduleConfig) return false;
		$this->wire('modules')->saveConfig('ProcessPageLister', $moduleConfig);
		$this->moduleConfig = $moduleConfig;
		$this->message('Updated module config (bookmarks)', Notice::debug);
		return true;
	}

	protected function wakeupBookmark(array $bookmark, $bookmarkID, $type = null) {

		if(empty($bookmarkID) || empty($bookmark['title'])) return false;

		if($type === null) $type = $this->idType($bookmarkID);
		$bookmarkID = $this->bookmarkStrID($bookmarkID, $type);

		$bookmark = $this->_bookmark($bookmark);
		$bookmark['type'] = $type;
		$bookmark['id'] = $bookmarkID;
		$bookmark['url'] = $this->getBookmarkUrl($bookmarkID);
		$bookmark['editUrl'] = $this->getBookmarkEditUrl($bookmarkID);
		$bookmark['share'] = empty($bookmark['share']) ? false : true;
		//$bookmark['shareUrl'] = $bookmark['url'];

		return $bookmark;
	}

	protected function sleepBookmark(array $bookmark) {
		unset($bookmark['id'], $bookmark['url'], $bookmark['editUrl']);
		if($bookmark['type'] === self::typeOwned) unset($bookmark['roles']);
		if(empty($bookmark['share'])) unset($bookmark['share']);
		return $bookmark;
	}

	/**
	 * Given an id or string key, return an int ID
	 *
	 * @param string|int $val
	 * @return int
	 *
	 */
	public function intID($val) {
		return (int) ltrim($val, '_O');
	}

	/**
	 * Given an id or string key, return an string ID (with leading underscore)
	 *
	 * @param string|int $val
	 * @return int
	 *
	 */
	public function strID($val) {
		return '_' . ltrim($val, '_O');
	}

	/**
	 * Given an id or string key, return an bookmark string ID
	 *
	 * @param string|int $val
	 * @param int $type
	 * @return int
	 *
	 */
	public function bookmarkStrID($val, $type) {
		return ($type === self::typeOwned ? 'O' : '_') . ltrim($val, '_O');
	}

	/**
	 * Does the given string value represent an ID? If yes, return ID, otherwise return false.
	 *
	 * @param string $val
	 * @return bool|int
	 *
	 */
	public function isID($val) {
		$val = trim($val, '_O');
		return ctype_digit($val) ? (int) $val : false;
	}

	/**
	 * Get the type from the given id string
	 *
	 * @param string $val
	 * @return int
	 *
	 */
	public function idType($val) {
		if(strpos($val, 'O') === 0) return self::typeOwned;
		return self::typePublic;
	}

	/**
	 * Get the prefix for the given bookmark type
	 *
	 * @param int $type
	 * @return string
	 *
	 */
	public function typePrefix($type) {
		if($type == self::typePublic) return '_';
		if($type == self::typeOwned) return 'O';
		return '';
	}

	/**
	 * Is the given page ID or key valid and existing?
	 *
	 * @param int|string $val
	 * @return bool
	 *
	 */
	public function isValidPageKey($val) {
		$id = $this->intID($val);
		return $id === $this->page->id || $this->wire('pages')->get($id)->id > 0;
	}

	/**
	 * Return a readable selector from bookmark for output purposes
	 * 
	 * @param array $bookmark
	 * @return string
	 * 
	 */
	public function readableBookmarkSelector(array $bookmark) {
		
		$selector = $bookmark['selector'];
		if(strpos($selector, 'template=') !== false && preg_match('/template=([\d\|]+)/', $selector, $matches)) {
			// make templates readable, for output purposes
			$t = '';
			foreach(explode('|', $matches[1]) as $templateID) {
				$template = $this->wire('templates')->get((int) $templateID);
				$t .= ($t ? '|' : '') . ($template ? $template->name : $templateID);
			}
			$selector = str_replace($matches[0], "template=$t", $selector);
		}
		
		if(!empty($bookmark['sort'])) $selector .= ($selector ? ", " : "") . "sort=$bookmark[sort]";
		
		return $selector;
	}


}