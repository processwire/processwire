<?php namespace ProcessWire;

/**
 * ProcessWire Pages Loader Cache
 * 
 * #pw-headline Pages Loader Cache
 * #pw-var $pages->cacher
 * #pw-breadcrumb Pages 
 * #pw-summary Implements page caching of loaded pages and PageArrays for $pages API variable
 * #pw-body =
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesLoaderCache extends Wire {
	
	/**
	 * Pages that have been cached, indexed by ID
	 *
	 */
	protected $pageIdCache = array();

	/**
	 * Cached selector strings and the PageArray that was found.
	 *
	 */
	protected $pageSelectorCache = array();

	/**
	 * [ 'cache group name' => [ page IDs ] ]
	 * 
	 * @var array
	 * 
	 */
	protected $cacheGroups = array();

	/**
	 * @var Pages
	 * 
	 */
	protected $pages;

	/**
	 * Construct
	 * 
	 * @param Pages $pages
	 * 
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;
	}

	/**
	 * Get cache status
	 * 
	 * Returns count of each cache type, or contents of each cache type of verbose option is specified. 
	 * 
	 * #pw-group-get
	 * 
	 * @param bool|null $verbose Specify true to get contents of cache, false to get string counts, or omit for array of counts
	 * @return array|string
	 * @since 3.0.198
	 * 
	 */
	public function getCacheStatus($verbose = null) {
		$a = array(
			'pages' => ($verbose ? $this->pageIdCache : count($this->pageIdCache)), 
			'selectors' => ($verbose ? $this->pageSelectorCache : count($this->pageSelectorCache)), 
			'groups' => ($verbose ? $this->cacheGroups : count($this->cacheGroups)),
		);
		return ($verbose === false ? "pages=$a[pages], selectors=$a[selectors], groups=$a[groups]" : $a);
	}
	
	/**
	 * Given a Page ID, return it if it's cached, or NULL of it's not.
	 *
	 * If no ID is provided, then this will return an array copy of the full cache.
	 *
	 * You may also pass in the string "id=123", where 123 is the page_id
	 * 
	 * #pw-group-get
	 *
	 * @param int|string|null $id
	 * @return Page|array|null
	 *
	 */
	public function getCache($id = null) {
		if(!$id) return $this->pageIdCache;
		if(!ctype_digit("$id")) $id = str_replace('id=', '', $id);
		if(ctype_digit("$id")) $id = (int) $id;
		if(!isset($this->pageIdCache[$id])) return null;
		$page = $this->pageIdCache[$id]; /** @var Page $page */
		$of = $this->pages->loader()->getOutputFormatting();
		if(!$of && $page === $this->wire()->page) return $page; // skip of() adjustment
		$page->of($of);
		return $page;
	}

	/**
	 * Is given page ID in the cache?
	 * 
	 * #pw-group-get
	 * 
	 * @param int page ID
	 * @return bool
	 * @since 3.0.243
	 * 
	 */
	public function hasCache($id) {
		return isset($this->pageIdCache[$id]);
	}

	/**
	 * Cache the given page in memory
	 * 
	 * #pw-group-save
	 *
	 * @param Page $page
	 * @return void
	 *
	 */
	public function cache(Page $page) {
		if($page->id) $this->pageIdCache[$page->id] = $page;
	}

	/**
	 * Cache given page into a named group that it can be uncached with
	 * 
	 * #pw-group-save
	 * 
	 * @param Page $page
	 * @param string $groupName
	 * @since 3.0.198
	 * 
	 */
	public function cacheGroup(Page $page, $groupName) {
		if(!$page->id) return;
		if(!isset($this->cacheGroups[$groupName])) $this->cacheGroups[$groupName] = array();
		$this->pageIdCache[$page->id] = $page;
		$this->cacheGroups[$groupName][] = $page->id;
	}

	/**
	 * Remove the given page from the cache.
	 *
	 * Note: does not remove pages from selectorCache. Call uncacheAll to do that.
	 * 
	 * #pw-group-remove
	 *
	 * @param Page|int $page Page to uncache or ID of page (prior to 3.0.153 only Page object was accepted)
	 * @param array $options Additional options to modify behavior:
	 *   - `shallow` (bool): By default, this method also calls $page->uncache(). To prevent call to $page->uncache(), set 'shallow' => true.
	 * @return bool True if page was uncached, false if it didn't need to be
	 *
	 */
	public function uncache($page, array $options = array()) {
		if($page instanceof Page) {
			$pageId = $page->id; 
		} else {
			$pageId = is_int($page) ? $page : (int) "$page"; 
			$page = isset($this->pageIdCache[$pageId]) ? $this->pageIdCache[$pageId] : null;
		}
		if(empty($options['shallow']) && $page) {
			$page->uncache();
		}
		if(isset($this->pageIdCache[$pageId])) {
			unset($this->pageIdCache[$pageId]);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Remove all pages from the cache
	 * 
	 * #pw-group-remove
	 *
	 * @param Page|null $page Optional Page that initiated the uncacheAll
	 * @param array $options Additional options to modify behavior:
	 *   - `shallow` (bool): By default, this method also calls $page->uncache(). To prevent call to $page->uncache(), set 'shallow' => true.
	 * @return int Number of pages uncached
	 *
	 */
	public function uncacheAll(?Page $page = null, array $options = array()) {

		if($page) {} // to ignore unused parameter inspection
		$user = $this->wire()->user;
		$language = $this->wire()->languages ? $user->language : null;
		$cnt = 0;

		$this->pages->sortfields(true); // reset

		if($this->wire()->config->debug) {
			$this->pages->debugLog('uncacheAll', 'pageIdCache=' . count($this->pageIdCache) . ', pageSelectorCache=' . 
				count($this->pageSelectorCache));
		}

		foreach($this->pageIdCache as $id => $page) {
			if($id == $user->id || ($language && $language->id == $id)) continue;
			if($page->numChildren) continue; 
			if(empty($options['shallow'])) $page->uncache();
			unset($this->pageIdCache[$page->id]);
			$cnt++;
		}

		$this->pageIdCache = array();
		$this->pageSelectorCache = array();
		$this->cacheGroups = array();

		Page::$loadingStack = array();
		Page::$instanceIDs = array();
		
		return $cnt;
	}

	/**
	 * Uncache pages that were cached with given group name
	 * 
	 * #pw-group-remove
	 * 
	 * @param string $groupName
	 * @param array $options
	 * @return int
	 * @since 3.0.198
	 * 
	 */
	public function uncacheGroup($groupName, array $options = array()) {
		$qty = 0;
		if(!isset($this->cacheGroups[$groupName])) return 0;
		foreach($this->cacheGroups[$groupName] as $pageId) {
			if(!isset($this->pageIdCache[$pageId])) continue;
			$page = $this->pageIdCache[$pageId];
			if($page && empty($options['shallow'])) $page->uncache();
			unset($this->pageIdCache[$pageId]);
			$qty++;
		}
		unset($this->cacheGroups[$groupName]); 
		return $qty;
	}

	/**
	 * Cache the given selector string and options with the given PageArray
	 * 
	 * #pw-group-save
	 *
	 * @param string $selector
	 * @param array $options
	 * @param PageArray $pages
	 * @return bool True if pages were cached, false if not
	 *
	 */
	public function selectorCache($selector, array $options, PageArray $pages) {

		// get the string that will be used for caching
		$selector = $this->getSelectorCache($selector, $options, true);

		// optimization: don't cache single pages that have an unpublished status or higher
		if(count($pages) && !empty($options['findOne']) && $pages->first()->status >= Page::statusUnpublished) return false;

		$this->pageSelectorCache[$selector] = clone $pages;

		return true;
	}

	/**
	 * Convert an options array to a string
	 * 
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function optionsArrayToString(array $options) {
		$str = '';
		ksort($options);
		foreach($options as $key => $value) {
			if(is_array($value)) {
				$value = $this->optionsArrayToString($value);
			} else if(is_object($value)) {
				if(method_exists($value, '__toString')) {
					$value = (string) $value;
				} else {
					$value = wireClassName($value);
				}
			}
			$str .= "[$key:$value]";
		}
		return $str;
	}

	/**
	 * Retrieve any cached page IDs for the given selector and options OR false if none found.
	 *
	 * You may specify a third param as TRUE, which will cause this to just return the selector string (with hashed options)
	 * 
	 * #pw-group-get
	 *
	 * @param string $selector
	 * @param array $options
	 * @param bool $returnSelector default false
	 * @return array|null|string|PageArray
	 *
	 */
	public function getSelectorCache($selector, $options, $returnSelector = false) {

		if(count($options)) {
			$optionsHash = $this->optionsArrayToString($options);
			$selector .= "," . $optionsHash;
		} else {
			$selector .= ",";
		}

		// optimization to use consistent conventions for commonly interchanged names
		$selector = str_replace(
			array(
				'path=/,',
				'parent=/,'
			),
			array(
				'id=1,',
				'parent_id=1,'
			),
			$selector
		);

		// optimization to filter out common status checks for pages that won't be cached anyway
		if(!empty($options['findOne'])) {
			$selector = str_replace(
				array(
					'status<' . Page::statusUnpublished,
					'status<' . Page::statusMax,
					'start=0',
					'limit=1',
					',',
					' '
				),
				'',
				$selector
			);
			$selector = trim($selector, ", ");
		}

		// cache non-default languages separately
		if($this->wire()->languages) {
			$language = $this->wire()->user->language;
			if($language && !$language->isDefault && $language->name != 'default') {
				$selector .= ", _lang=$language->id"; // for caching purposes only, not recognized by PageFinder
			}
		}

		if($returnSelector) return $selector;
		if(isset($this->pageSelectorCache[$selector])) return $this->pageSelectorCache[$selector];

		return null;
	}
}
