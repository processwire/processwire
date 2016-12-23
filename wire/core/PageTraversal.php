<?php namespace ProcessWire;

/**
 * ProcessWire Page Traversal
 *
 * Provides implementation for Page traversal functions.
 * Based upon the jQuery traversal functions. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class PageTraversal {

	/**
	 * Return number of children, optionally with conditions
	 *
	 * Use this over $page->numChildren property when you want to specify a selector or when you want the result to
	 * include only visible children. See the options for the $selector argument. 
	 *
	 * @param Page $page
	 * @param bool|string|int|array $selector 
	 *	When not specified, result includes all children without conditions, same as $page->numChildren property.
	 *	When a string or array, a selector is assumed and quantity will be counted based on selector.
	 * 	When boolean true, number includes only visible children (excludes unpublished, hidden, no-access, etc.)
	 *	When boolean false, number includes all children without conditions, including unpublished, hidden, no-access, etc.
	 * 	When integer 1 number includes viewable children (as opposed to visible, viewable includes hidden pages + it also includes unpublished pages if user has page-edit permission).
	 * @return int Number of children
	 *
	 */
	public function numChildren(Page $page, $selector = null) {
		if(is_bool($selector)) {
			// onlyVisible takes the place of selector
			$onlyVisible = $selector; 
			if(!$onlyVisible) return $page->get('numChildren');
			return $page->_pages('count', "parent_id=$page->id"); 
			
		} else if($selector === 1) { 
			// viewable pages only
			$numChildren = $page->get('numChildren');
			if(!$numChildren) return 0;
			if($page->wire('user')->isSuperuser()) return $numChildren;
			if($page->wire('user')->hasPermission('page-edit')) {
				return $page->_pages('count', "parent_id=$page->id, include=unpublished");
			}
			return $page->_pages('count', "parent_id=$page->id, include=hidden"); 

		} else if(empty($selector) || (!is_string($selector) && !is_array($selector))) {
			return $page->get('numChildren'); 

		} else {
			if(is_string($selector)) {
				$selector = "parent_id=$page->id, $selector";
			} else if(is_array($selector)) {
				$selector["parent_id"] = $page->id;
			}
			return $page->_pages('count', $selector);
		}
	}

	/**
	 * Return this page's children pages, optionally filtered by a selector
	 *
	 * @param Page $page
	 * @param string|array $selector Selector to use, or blank to return all children
	 * @param array $options
	 * @return PageArray|array
	 *
	 */
	public function children(Page $page, $selector = '', $options = array()) {
		if(!$page->numChildren) return $page->_pages()->newPageArray();
		$defaults = array('caller' => 'page.children'); 
		$options = array_merge($defaults, $options); 
		$sortfield = $page->sortfield();
		if(is_array($selector)) {
			// selector is array
			$selector["parent_id"] = $page->id;
			if(isset($selector["sort"])) $sortfield = '';
			if($sortfield) $selector[] = array("sort", $sortfield);
		} else {
			// selector is string
			$selector = trim("parent_id=$page->id, $selector", ", ");
			if(strpos($selector, 'sort=') === false) $selector .= ", sort=$sortfield";
		}
		return $page->_pages('find', $selector, $options); 
	}

	/**
	 * Return the page's first single child that matches the given selector. 
	 *
	 * Same as children() but returns a Page object or NullPage (with id=0) rather than a PageArray
	 *
	 * @param Page $page
	 * @param string|array $selector Selector to use, or blank to return the first child. 
	 * @param array $options
	 * @return Page|NullPage
	 *
	 */
	public function child(Page $page, $selector = '', $options = array()) {
		if(!$page->numChildren) return $page->_pages()->newNullPage();
		$defaults = array('getTotal' => false, 'caller' => 'page.child'); 
		$options = array_merge($defaults, $options); 
		if(is_array($selector)) {
			$selector["limit"] = 1;
			$selector[] = array("start", "0");
		} else {
			$selector .= ($selector ? ', ' : '') . "limit=1";
			if(strpos($selector, 'start=') === false) $selector .= ", start=0"; // prevent pagination
		}
		$children = $this->children($page, $selector, $options); 
		return count($children) ? $children->first() : $page->_pages()->newNullPage();
	}

	/**
	 * Return this page's parent pages, or the parent pages matching the given selector.
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector string to filter parents by
	 * @return PageArray
	 *
	 */
	public function parents(Page $page, $selector = '') {
		$parents = $page->wire('pages')->newPageArray();
		$parent = $page->parent();
		while($parent && $parent->id) {
			$parents->prepend($parent); 	
			$parent = $parent->parent();
		}
		return strlen($selector) ? $parents->filter($selector) : $parents; 
	}

	/**
	 * Return all parent from current till the one matched by $selector
	 *
	 * @param Page $page
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this. 
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @return PageArray
	 *
	 */
	public function parentsUntil(Page $page, $selector = '', $filter = '') {

		$parents = $this->parents($page); 
		$matches = $page->wire('pages')->newPageArray();
		$stop = false;

		foreach($parents->reverse() as $parent) {

			if(is_string($selector) && strlen($selector)) {
				if(ctype_digit("$selector") && $parent->id == $selector) {
					$stop = true;
				} else if($parent->matches($selector)) {
					$stop = true;
				}
				
			} else if(is_array($selector) && !empty($selector)) {
				if($parent->matches($selector)) $stop = true;

			} else if(is_int($selector)) {
				if($parent->id == $selector) $stop = true; 

			} else if($selector instanceof Page && $parent->id == $selector->id) {
				$stop = true; 
			}

			if($stop) break;
			$matches->prepend($parent);
		}

		if(!empty($filter)) $matches->filter($filter); 
		
		return $matches;
	}


	/**
	 * Get the lowest-level, non-homepage parent of this page
	 *
	 * rootParents typically comprise the first level of navigation on a site. 
	 *
	 * @param Page $page
	 * @return Page 
	 *
	 */
	public function rootParent(Page $page) {
		$parent = $page->parent;
		if(!$parent || !$parent->id || $parent->id === 1) return $page; 
		$parents = $this->parents($page);
		$parents->shift(); // shift off homepage
		return $parents->first();
	}

	/**
	 * Return this Page's sibling pages, optionally filtered by a selector. 
	 *
	 * Note that the siblings include the current page. To exclude the current page, specify "id!=$page". 
	 *
	 * @param Page $page
	 * @param string $selector Optional selector to filter siblings by.
	 * @return PageArray
	 *
	 */
	public function siblings(Page $page, $selector = '') {
		$parent = $page->parent();
		$sort = $parent->sortfield(); 
		if(is_array($selector)) {
			$selector["parent_id"] = $page->parent_id;	
			$selector[] = array('sort', $sort);
		} else {
			$selector = "parent_id=$page->parent_id, $selector";
			if(strpos($selector, 'sort=') === false) $selector .= ", sort=$sort";
			$selector = trim($selector, ", ");
		}
		$options = array('caller' => 'page.siblings'); 
		return $page->_pages('find', $selector, $options); 
	}

	/**
	 * Builds the PageFinder options for the _next() method
	 * 
	 * @param Page $page
	 * @param string|array $selector
	 * @param array $options
	 * @return array
	 * 
	 */
	protected function _nextFinderOptions(Page $page, $selector, $options) {
		
		$fo = array(
			'findOne' => $options['all'] ? false : true,
			'startAfterID' => $options['prev'] ? 0 : $page->id,
			'stopBeforeID' => $options['prev'] ? $page->id : 0,
			'returnVerbose' => $options['all'] ? false : true,
		);

		if(!$options['until']) return $fo;
		
		// all code below this specific to the 'until' option
		
		$until = $options['until'];
		/** @var string $until */
		if(is_array($until)) $until = (string) (new Selectors($until));
		
		if(ctype_digit("$until")) {
			// id or Page object
			$stopPage = new WireData();
			$stopPage->set('id', (int) $until);
			
		} else if(strpos($until, '/') === 0) {
			// page path
			$stopPage = $page->_pages('get', $until);
			
		} else if(is_array($selector) || is_array($options['until'])) {
			// either selector or until is an array
			$s = new Selectors($options['until']);
			foreach(new Selectors($selector) as $item) $s->add($item);
			$s->add(new SelectorEqual('limit', 1));
			$stopPage = $page->_pages('find', $s)->first();
			
		} else {
			// selector string
			$stopPage = $page->_pages('find', "$selector, limit=1, $until")->first();
		}
		
		if($stopPage && $stopPage->id) {
			if($options['prev']) {
				$fo['startAfterID'] = $stopPage->id;
				$fo['stopBeforeID'] = $page->id;
			} else {
				$fo['startAfterID'] = $page->id;
				$fo['stopBeforeID'] = $stopPage->id;
			}
		}
		
		return $fo;
	}

	/**
	 * Provides the core logic for next, prev, nextAll, prevAll, nextUntil, prevUntil
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector. When specified, will find nearest sibling(s) that match.
	 * @param array $options Options to modify behavior
	 *  - `prev` (bool): When true, previous siblings will be returned rather than next siblings.
	 *  - `all` (bool): If true, returns all nextAll or prevAll rather than just single sibling (default=false).
	 *  - `until` (string): If specified, returns siblings until another is found matching the given selector (default=false).
	 *  - `qty` (bool): If true, makes it return just the quantity that would match (default=false). 
	 * @return Page|NullPage|PageArray|int Returns one of the following: 
	 *  - `PageArray` if the "all" or "until" option is specified. 
	 *  - `Page|NullPage` in other cases. 
	 *
	 */ 
	protected function _next(Page $page, $selector = '', array $options = array()) {
		
		$defaults = array(
			'prev' => false, // get previous rather than next
			'all' => false, // get multiple/all
			'until' => '', // until selector string ('all' option assumed)
			'qty' => false, // when true, returns just the quantity that would match ('all' option assumed)
		);

		$options = array_merge($defaults, $options);
		$pages = $page->wire('pages');
		$parent = $page->parent();
		
		if($options['until'] || $options['qty']) $options['all'] = true;
		if(!$parent || !$parent->id) return $options['all'] ? $pages->newPageArray() : $pages->newNullPage();
		
		if(is_array($selector)) {
			$selector['parent_id'] = $parent->id; 
		} else {
			$selector = trim("parent_id=$parent->id, $selector", ", ");
		}
		
		$pageFinder = $pages->getPageFinder();
		$pageFinderOptions = $this->_nextFinderOptions($page, $selector, $options);
		$rows = $pageFinder->find($selector, $pageFinderOptions);
		
		if($options['qty']) {
			$result = count($rows);

		} else if(!count($rows)) {
			$result = $options['all'] ? $pages->newPageArray() : $pages->newNullPage();

		} else if($options['all']) {
			$result = $pages->getById($rows, array(
				'parent_id' => $parent->id,
				'cache' => $page->loaderCache
			));
			if($options['all'] && $options['prev']) $result = $result->reverse();
			
		} else {
			$row = reset($rows);
			$result = $pages->getById(array($row['id']), array(
				'template' => $page->wire('templates')->get($row['templates_id']),
				'parent_id' => $row['parent_id'],
				'getOne' => true,
				'cache' => $page->loaderCache
			));
		}
		
		return $result;
	}

	/**
	 * Return the index/position of the given page relative to its siblings
	 * 
	 * @param Page $page
	 * @return int|NullPage|Page|PageArray
	 * 
	 */
	public function index(Page $page) {
		$index = $this->_next($page, '', array('prev' => true, 'all' => true, 'qty' => true));
		return $index;
	}
	
	/**
	 * Return the next sibling page
	 *
	 * @param Page $page
	 * @param string $selector Optional selector. When specified, will find nearest next sibling that matches.
	 * @return Page|NullPage Returns the next sibling page, or a NullPage if none found.
	 *
	 */
	public function next(Page $page, $selector = '') {
		return $this->_next($page, $selector);
	}

	/**
	 * Return the previous sibling page
	 *
	 * @param Page $page
	 * @param string $selector Optional selector. When specified, will find nearest previous sibling that matches.
	 * @return Page|NullPage Returns the previous sibling page, or a NullPage if none found.
	 *
	 */
	public function prev(Page $page, $selector = '') {
		return $this->_next($page, $selector, array('prev' => true));
	}


	/**
	 * Return all sibling pages after this one, optionally matching a selector
	 *
	 * @param Page $page
	 * @param string $selector Optional selector. When specified, will filter the found siblings.
	 * @param array $options Options to pass to the _next() method
	 * @return Page|NullPage Returns all matching pages after this one.
	 *
	 */
	public function nextAll(Page $page, $selector = '', array $options = array()) {
		$defaults = array('all' => true);
		$options = array_merge($options, $defaults);
		return $this->_next($page, $selector, $options);
	}
	
	/**
	 * Return all sibling pages prior to this one, optionally matching a selector
	 *
	 * @param Page $page
	 * @param string $selector Optional selector. When specified, will filter the found siblings.
	 * @param array $options Options to pass to the _next() method
	 * @return Page|NullPage Returns all matching pages after this one.
	 *
	 */
	public function prevAll(Page $page, $selector = '', array $options = array()) {
		$defaults = array(
			'prev' => true, 
			'all' => true
		);
		$options = array_merge($options, $defaults);
		return $this->_next($page, $selector, $options);
	}
	
	/**
	 * Return all sibling pages after this one until matching the one specified
	 *
	 * @param Page $page
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this.
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @param array $options Options to pass to the _next() method
	 * @return PageArray
	 *
	 */
	public function nextUntil(Page $page, $selector = '', $filter = '', array $options = array()) {
		$defaults = array(
			'all' => true, 
			'until' => $selector
		);
		$options = array_merge($options, $defaults);
		return $this->_next($page, $filter, $options);
	}
	
	/**
	 * Return all sibling pages prior to this one until matching the one specified
	 *
	 * @param Page $page
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this.
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @param array $options Options to pass to the _next() method
	 * @return PageArray
	 *
	 */
	public function prevUntil(Page $page, $selector = '', $filter = '', array $options = array()) {
		$defaults = array(
			'prev' => true, 
			'all' => true, 
			'until' => $selector
		);
		$options = array_merge($options, $defaults);
		return $this->_next($page, $filter, $options);
	}
	
	/**
	 * Returns the URL to the page with $options
	 *
	 * You can specify an `$options` argument to this method with any of the following:
	 *
	 * - `pageNum` (int|string): Specify pagination number, or "+" for next pagination, or "-" for previous pagination.
	 * - `urlSegmentStr` (string): Specify a URL segment string to append.
	 * - `urlSegments` (array): Specify array of URL segments to append (may be used instead of urlSegmentStr).
	 * - `data` (array): Array of key=value variables to form a query string.
	 * - `http` (bool): Specify true to make URL include scheme and hostname (default=false).
	 * - `language` (Language): Specify Language object to return URL in that Language.
	 *
	 * You can also specify any of the following for `$options` as shortcuts:
	 *
	 * - If you specify an `int` for options it is assumed to be the `pageNum` option.
	 * - If you specify `+` or `-` for options it is assumed to be the `pageNum` “next/previous pagination” option.
	 * - If you specify any other `string` for options it is assumed to be the `urlSegmentStr` option.
	 * - If you specify a `boolean` (true) for options it is assumed to be the `http` option.
	 *
	 * Please also note regarding `$options`:
	 *
	 * - This method honors template slash settings for page, URL segments and page numbers.
	 * - Any passed in URL segments are automatically sanitized with `Sanitizer::pageNameUTF8()`.
	 * - If using the `pageNum` or URL segment options please also make sure these are enabled on the page’s template.
	 * - The query string generated by any `data` variables is entity encoded when output formatting is on.
	 * - The `language` option requires that the `LanguageSupportPageNames` module is installed.
	 * - The prefix for page numbers honors `$config->pageNumUrlPrefix` and multi-language prefixes as well.
	 *
	 * @param Page $page
	 * @param array|int|string|bool|Language $options Optionally specify options to modify default behavior (see method description).
	 * @return string Returns page URL, for example: `/my-site/about/contact/`
	 * @see Page::path(), Page::httpUrl(), Page::editUrl(), Page::localUrl()
	 *
	 */
	public function urlOptions(Page $page, $options = array()) {

		$config = $page->wire('config');

		$defaults = array(
			'http' => is_bool($options) ? $options : false,
			'pageNum' => is_int($options) || (is_string($options) && in_array($options, array('+', '-'))) ? $options : 1,
			'data' => array(),
			'urlSegmentStr' => is_string($options) ? $options : '',
			'urlSegments' => array(),
			'language' => is_object($options) && $options instanceof Page && $options->className() === 'Language' ? $options : null,
		);

		if(empty($options)) return rtrim($config->urls->root, '/') . $page->path();

		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$template = $page->template;
		$sanitizer = $page->wire('sanitizer');
		$language = null;
		$url = null;

		if(count($options['urlSegments'])) {
			$options['urlSegmentStr'] = implode('/', $options['urlSegments']);
		}

		if($options['language'] && $page->wire('modules')->isInstalled('LanguageSupportPageNames')) {
			if(!is_object($options['language'])) {
				$options['language'] = null;
			} else if(!$options['language'] instanceof Page) {
				$options['language'] = null;
			} else if(strpos($options['language']->className(), 'Language') === false) {
				$options['language'] = null;
			}
			if($options['language']) {
				/** @var Language $language */
				$language = $options['language'];
				// localUrl method provided as hook by LanguageSupportPageNames
				$url = $page->localUrl($language);
			}
		}

		if(is_null($url)) {
			$url = rtrim($config->urls->root, '/') . $page->path();
			if($template->slashUrls === 0 && $page->id > 1) $url = rtrim($url, '/');
		}

		if(is_string($options['urlSegmentStr']) && strlen($options['urlSegmentStr'])) {
			$url = rtrim($url, '/') . '/' . $sanitizer->pagePathNameUTF8(trim($options['urlSegmentStr'], '/'));
			if($template->slashUrlSegments === '' || $template->slashUrlSegments) $url .= '/';
		}

		if($options['pageNum']) {
			if($options['pageNum'] === '+') {
				$options['pageNum'] = $page->wire('input')->pageNum + 1;
			} else if($options['pageNum'] === '-' || $options['pageNum'] === -1) {
				$options['pageNum'] = $page->wire('input')->pageNum - 1;
			}
			if((int) $options['pageNum'] > 1) {
				$prefix = '';
				if($language) {
					$lsp = $page->wire('modules')->get('LanguageSupportPageNames');
					$prefix = $lsp ? $lsp->get("pageNumUrlPrefix$language") : '';
				}
				if(!strlen($prefix)) $prefix = $config->pageNumUrlPrefix;
				$url = rtrim($url, '/') . '/' . $prefix . ((int) $options['pageNum']);
				if($template->slashPageNum) $url .= '/';
			}
		}

		if(count($options['data'])) {
			$query = http_build_query($options['data']);
			if($page->of()) $query = $sanitizer->entities($query);
			$url .= '?' . $query;
		}

		if($options['http']) {
			switch($template->https) {
				case -1: $scheme = 'http'; break;
				case 1: $scheme = 'https'; break;
				default: $scheme = $config->https ? 'https' : 'http';
			}
			$url = "$scheme://" . $page->wire('config')->httpHost . $url;
		}

		return $url;
	}


	/******************************************************************************************************************
	 * LEGACY METHODS
	 * 
	 * Following are legacy methods to support backwards compatibility with previous PW versions that used 
	 * a $siblings argument for next/prev related methods. 
	 * 
	 */

	/**
	 * Return the next sibling page, within a group of provided siblings (that includes the current page)
	 *
	 * This method is the old version of the next() method and is only used if a $siblings argument is provided
	 * to the Page::next() call.  It is much slower than the next() method.
	 *
	 * If given a PageArray of siblings (containing the current) it will return the next sibling relative to the provided PageArray.
	 *
	 * Be careful with this function when the page has a lot of siblings. It has to load them all, so this function is best
	 * avoided at large scale, unless you provide your own already-reduced siblings list (like from pagination)
	 *
	 * When using a selector, note that this method operates only on visible children. If you want something like "include=all"
	 * or "include=hidden", they will not work in the selector. Instead, you should provide the siblings already retrieved with
	 * one of those modifiers, and provide those siblings as the second argument to this function.
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector. When specified, will find nearest next sibling that matches.
	 * @param PageArray $siblings Optional siblings to use instead of the default. May also be specified as first argument when no selector needed.
	 * @return Page|NullPage Returns the next sibling page, or a NullPage if none found.
	 *
	 */
	public function nextSibling(Page $page, $selector = '', PageArray $siblings = null) {
		if(is_object($selector) && $selector instanceof PageArray) {
			// backwards compatible to when $siblings was first argument
			$siblings = $selector;
			$selector = '';
		}
		if(is_null($siblings)) {
			$siblings = $page->parent->children();
		} else if(!$siblings->has($page)) {
			$siblings->prepend($page);
		}

		$next = $page;
		do {
			$next = $siblings->getNext($next, false);
			if(empty($selector) || !$next || $next->matches($selector)) break;
		} while($next && $next->id);
		if(is_null($next)) $next = $page->wire('pages')->newNullPage();
		return $next;
	}


	/**
	 * Return the previous sibling page within a provided group of siblings that contains the current page
	 * 
	 * This method is the old version of the prev() method and is only used if a $siblings argument is provided
	 * to the Page::prev() call. It is much slower than the prev() method. 
	 *
	 * If given a PageArray of siblings (containing the current) it will return the previous sibling relative to the provided PageArray.
	 *
	 * Be careful with this function when the page has a lot of siblings. It has to load them all, so this function is best
	 * avoided at large scale, unless you provide your own already-reduced siblings list (like from pagination)
	 *
	 * When using a selector, note that this method operates only on visible children. If you want something like "include=all"
	 * or "include=hidden", they will not work in the selector. Instead, you should provide the siblings already retrieved with
	 * one of those modifiers, and provide those siblings as the second argument to this function.
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector. When specified, will find nearest previous sibling that matches. 
	 * @param PageArray $siblings Optional siblings to use instead of the default. May also be specified as first argument when no selector needed.
	 * @return Page|NullPage Returns the previous sibling page, or a NullPage if none found. 
	 *
	 */
	public function prevSibling(Page $page, $selector = '', PageArray $siblings = null) {
		if(is_object($selector) && $selector instanceof PageArray) {
			// backwards compatible to when $siblings was first argument
			$siblings = $selector;
			$selector = '';
		}
		if(is_null($siblings)) {
			$siblings = $page->parent->children();
		} else if(!$siblings->has($page)) {
			$siblings->add($page);
		}

		$prev = $page;
		do {
			$prev = $siblings->getPrev($prev, false); 
			if(empty($selector) || !$prev || $prev->matches($selector)) break;
		} while($prev && $prev->id); 
		if(is_null($prev)) $prev = $page->wire('pages')->newNullPage();
		return $prev;
	}

	/**
	 * Return all sibling pages after this one, optionally matching a selector
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector. When specified, will filter the found siblings.
	 * @param PageArray $siblings Optional siblings to use instead of the default. 
	 * @return Page|NullPage Returns all matching pages after this one.
	 *
	 */
	public function nextAllSiblings(Page $page, $selector = '', PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->prepend($page);
		}

		$id = $page->id;
		$all = $page->wire('pages')->newPageArray();
		$rec = false;

		foreach($siblings as $sibling) {
			if($sibling->id == $id) {
				$rec = true;
				continue;
			}
			if($rec) $all->add($sibling);
		}

		if(!empty($selector)) $all->filter($selector); 
		
		return $all;
	}

	/**
	 * Return all sibling pages before this one, optionally matching a selector
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector. When specified, will filter the found siblings.
	 * @param PageArray $siblings Optional siblings to use instead of the default. 
	 * @return Page|NullPage Returns all matching pages before this one.
	 *
	 */
	public function prevAllSiblings(Page $page, $selector = '', PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->add($page);
		}

		$id = $page->id;
		$all = $page->wire('pages')->newPageArray();

		foreach($siblings as $sibling) {
			if($sibling->id == $id) break;
			$all->add($sibling);
		}

		if(!empty($selector)) $all->filter($selector); 
		
		return $all;
	}

	/**
	 * Return all sibling pages after this one until matching the one specified 
	 *
	 * @param Page $page
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this. 
	 * @param string|array $filter Optional selector to filter matched pages by
	 * @param PageArray|null $siblings Optional PageArray of siblings to use instead of all from the page.
	 * @return PageArray
	 *
	 */
	public function nextUntilSiblings(Page $page, $selector = '', $filter = '', PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->prepend($page);
		}

		$siblings = $this->nextAll($page, '', $siblings); 

		$all = $page->wire('pages')->newPageArray();
		$stop = false;

		foreach($siblings as $sibling) {

			if(is_string($selector) && strlen($selector)) {
				if(ctype_digit("$selector") && $sibling->id == $selector) {
					$stop = true;
				} else if($sibling->matches($selector)) {
					$stop = true;
				}
				
			} else if(is_array($selector) && count($selector)) {
				if($sibling->matches($selector)) $stop = true;

			} else if(is_int($selector)) {
				if($sibling->id == $selector) $stop = true; 

			} else if($selector instanceof Page && $sibling->id == $selector->id) {
				$stop = true; 
			}

			if($stop) break;
			
			$all->add($sibling);
		}

		if(!empty($filter)) $all->filter($filter); 
		
		return $all;
	}
	
	/**
	 * Return all sibling pages before this one until matching the one specified 
	 *
	 * @param Page $page
	 * @param string|Page|array $selector May either be a selector or Page to stop at. Results will not include this. 
	 * @param string|array $filter Optional selector string to filter matched pages by
	 * @param PageArray|null $siblings Optional PageArray of siblings to use instead of all from the page.
	 * @return PageArray
	 *
	 */
	public function prevUntilSiblings(Page $page, $selector = '', $filter = '', PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->add($page);
		}

		$siblings = $this->prevAll($page, '', $siblings); 

		$all = $page->wire('pages')->newPageArray();
		$stop = false;

		foreach($siblings->reverse() as $sibling) {

			if(is_string($selector) && strlen($selector)) {
				if(ctype_digit("$selector") && $sibling->id == $selector) {
					$stop = true;
				} else if($sibling->matches($selector)) {
					$stop = true;
				}
				
			} else if(is_array($selector) && count($selector)) {
				if($sibling->matches($selector)) $stop = true;

			} else if(is_int($selector)) {
				if($sibling->id == $selector) $stop = true; 

			} else if($selector instanceof Page && $sibling->id == $selector->id) {
				$stop = true; 
			}

			if($stop) break;
			
			$all->prepend($sibling);
		}

		if(!empty($filter)) $all->filter($filter); 
		
		return $all;
	}

	/**
	 * Return the next or previous sibling page (new fast version)
	 *
	 * @param Page $page
	 * @param bool $getNext Specify true to return next page, or false to return previous.
	 * @param string|array $selector Optional selector. When specified, will find nearest sibling that matches.
	 * @param array $options Options to modify behavior
	 *   - `all` (bool): If true, returns all nextAll or prevAll rather than just single sibling (default=false).
	 *   - `until` (string): If specified, returns all siblings until another is found matching the given selector.
	 * @return Page|NullPage|PageArray Returns the next/prev sibling page, or a NullPage if none found.
	 *   Returns PageArray if 'all' or 'until' option is specified.
	 *
	 */

	/*
	 * KEEPING THIS AROUND AS ALTERNATIVE METHOD FOR SHORT TERM REFERENCE
	 * This method performs worse than _next() in most cases, but if there are millions of siblings,
	 * this method is likely to perform significantly faster. So we may add this back into the logic
	 * if need dictates. However, it can't accommodate all possible sorting scenarios. 
	 * 
	protected function _nextAlternate(Page $page, $selector = '', array $options = array()) {

		$defaults = array(
			'prev' => false,
			'all' => false,
			'until' => '', // selector string
		);

		$options = array_merge($defaults, $options);
		$getNext = !$options['prev'];

		if($options['until']) {
			if(is_array($options['until'])) {
				$selectors = new Selectors($options['until']);
				$options['until'] = (string) $selectors;
			}
			$options['all'] = true; // the 'all' option is assumed with 'until' 
		}

		if(is_array($selector)) {
			$selectors = new Selectors($selector);
			$selector = (string) $selectors;
		}

		$pages = $page->wire('pages');
		$parent = $page->parent();
		$sanitizer = $page->wire('sanitizer');

		if(!$parent || !$parent->id) {
			// homepage or NullPage, quick exit
			return $options['all'] ? $pages->newPageArray() : $pages->newNullPage();
		}

		$sortfield = $parent->sortfield();
		$descending = strpos($sortfield, '-') === 0;
		if($descending) $sortfield = ltrim($sortfield, '-');
		if($getNext === false) $descending = !$descending;
		$operator = $descending ? "<" : ">";
		$value = $sanitizer->selectorValue($page->getUnformatted($sortfield));
		$sortfield2 = $sortfield == 'sort' ? 'sort.value' : $sortfield;
		$countSelector = rtrim("parent_id=$parent->id, $sortfield2=$value, $selector", ", ");
		$sortSelector = $descending ? "sort=-$sortfield" : "sort=$sortfield";
		$uniqueSorts = array('sort', 'id', 'name'); // sorts where same value never appears twice among siblings
		$useSlower = false;
		$isUniqueSort = in_array($sortfield, $uniqueSorts);
		$next = false;
		$nextAll = $options['all'] ? $pages->newPageArray() : false;

		if(!$isUniqueSort) {
			$field = $page->wire('fields')->get($sortfield);
			if($field->type instanceof FieldtypePage) {
				$sortfield2 .= ".name";
				$sortSelector .= ".name";
			}
		} else {
			$field = null;
		}

		// count how many other children have this same exact sort value
		if(!$isUniqueSort && $pages->count($countSelector) > 1) {
			// multiple siblings have the same sort value
			// we will have to load them all to determine where $page fits in there
			$siblings = $parent->children(rtrim("$sortfield2=$value, $selector", ", "));
			if(!$getNext) $siblings = $siblings->reverse();
			foreach($siblings as $sibling) {
				if($next === true) {
					$next = $sibling;
					if($nextAll) {
						$nextAll->add($next);
					} else {
						break;
					}
				} else if($sibling->id == $page->id) {
					$next = true;
				}
			}
			if(!$nextAll && $next && $next instanceof Page) {
				return $next;
			}
		}

		// page id exclusion will be used, so operator can include pages having sort value
		if($nextAll && $nextAll->count() > 1) $operator .= '=';

		// selector that that only matches pages having a higher/lower sortfield value than $page
		$selector = rtrim("parent_id=$parent->id, id!=$page->id, $sortfield2$operator$value, $sortSelector, $selector", ", ");

		if($options['until']) {
			// multiple next/prev sibling pages until a particular one
			$selector = $nextAll->each('id!={id}, ') . $selector;
			// include matches only up until page matching 'until' selector
			$until = $pages->find("$selector, $options[until], limit=1");
			// setup for fast exclusion method
			if($until->count()) {
				$items = $pages->find($selector, array('untilID' => $until->first()->id));
			} else {
				$items = $pages->find($selector);
				// use slower exclusion method when necessary, excluding pages after loaded
				$exclude = false;
				foreach($items as $item) {
					if($exclude) {
						$items->remove($item);
					} else if($item->matches($options['until'])) {
						$exclude = true;
						$items->remove($item);
					}
				}
			}
			return $items;

		} else if($nextAll) {
			// multiple next/prev sibling pages
			$selector = $nextAll->each('id!={id}, ') . $selector;
			return $pages->find($selector);

		} else {
			// single next/prev sibling page
			return $pages->findOne($selector);
		}
	}
	*/

}
