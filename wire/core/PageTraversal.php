<?php namespace ProcessWire;

/**
 * ProcessWire Page Traversal
 *
 * Provides implementation for Page traversal functions.
 * Based upon the jQuery traversal functions. 
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
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
	 * @param array $options
	 *  - `descendants` (bool): Use descendants rather than direct children
	 * @return int Number of children
	 *
	 */
	public function numChildren(Page $page, $selector = null, array $options = array()) {
	
		$descendants = empty($options['descendants']) ? false : true;
		$parentType = $descendants ? 'has_parent' : 'parent_id';
		
		if(is_bool($selector)) {
			// onlyVisible takes the place of selector
			$onlyVisible = $selector; 
			$numChildren = $page->get('numChildren');
			if(!$numChildren) {
				return 0;
			} else if($onlyVisible) {
				return $page->_pages('count', "$parentType=$page->id"); 
			} else if($descendants) {
				return $this->numDescendants($page);
			} else {
				return $numChildren;
			}
			
		} else if($selector === 1) { 
			// viewable pages only
			$numChildren = $page->get('numChildren');
			if(!$numChildren) return 0;
			$user = $page->wire()->user;
			if($user->isSuperuser()) {
				if($descendants) return $this->numDescendants($page);
				return $numChildren;
			} else if($user->hasPermission('page-edit')) {
				return $page->_pages('count', "$parentType=$page->id, include=unpublished");
			} else {
				return $page->_pages('count', "$parentType=$page->id, include=hidden");
			}

		} else if(empty($selector) || (!is_string($selector) && !is_array($selector))) {
			// no selector provided
			if($descendants) return $this->numDescendants($page);
			return $page->get('numChildren');

		} else {
			// selector string or array provided
			if(is_string($selector)) {
				$selector = "$parentType=$page->id, $selector";
			} else if(is_array($selector)) {
				$selector[$parentType] = $page->id;
			}
			return $page->_pages('count', $selector);
		}
	}

	/**
	 * Return number of descendants, optionally with conditions
	 *
	 * Use this over $page->numDescendants property when you want to specify a selector or when you want the result to
	 * include only visible descendants. See the options for the $selector argument.
	 *
	 * @param Page $page
	 * @param bool|string|int|array $selector
	 *	When not specified, result includes all descendants without conditions, same as $page->numDescendants property.
	 *	When a string or array, a selector is assumed and quantity will be counted based on selector.
	 * 	When boolean true, number includes only visible descendants (excludes unpublished, hidden, no-access, etc.)
	 *	When boolean false, number includes all descendants without conditions, including unpublished, hidden, no-access, etc.
	 * 	When integer 1 number includes viewable descendants (as opposed to visible, viewable includes hidden pages + it also includes unpublished pages if user has page-edit permission).
	 * @return int Number of descendants
	 *
	 */
	public function numDescendants(Page $page, $selector = null) {
		if($selector === null) {
			return $page->_pages('count', "has_parent=$page->id, include=all");
		} else {
			return $this->numChildren($page, $selector, array('descendants' => true));
		}
	}
	
	/**
	 * Return this page's children pages, optionally filtered by a selector
	 *
	 * @param Page $page
	 * @param string|array $selector Selector to use, or blank to return all children
	 * @param array $options
	 * @return PageArray
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
	 * @param string|array|bool $selector Optional selector string to filter parents by or boolean true for reverse order
	 * @return PageArray
	 *
	 */
	public function parents(Page $page, $selector = '') {
		$parents = $page->wire()->pages->newPageArray();
		$parent = $page->parent();
		$method = $selector === true ? 'add' : 'prepend';
		while($parent && $parent->id) {
			$parents->$method($parent); 	
			$parent = $parent->parent();
		}
		return !is_bool($selector) && strlen($selector) ? $parents->filter($selector) : $parents; 
	}

	/**
	 * Return number of parents (depth relative to homepage) that this page has, optionally filtered by a selector
	 * 
	 * For example, homepage has 0 parents and root level pages have 1 parent (which is the homepage), and the
	 * number increases the deeper the page is in the pages structure. 
	 * 
	 * @param Page $page
	 * @param string $selector Optional selector to filter by (default='')
	 * @return int Number of parents
	 * 
	 */
	public function numParents(Page $page, $selector = '') {
		$num = 0;
		$parent = $page->parent();
		while($parent && $parent->id) {
			if($selector !== '' && !$parent->matches($selector)) continue;
			$num++;
			$parent = $parent->parent();
		}
		return $num;
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
		$matches = $page->wire()->pages->newPageArray();
		$stop = false;

		foreach($parents->reverse() as $parent) {
			/** @var Page $parent */

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
	 * Get include mode specified in selector or blank if none
	 * 
	 * @param string|array|Selectors $selector
	 * @return string
	 * 
	 */
	protected function _getIncludeMode($selector) {
		if(is_string($selector) && strpos($selector, 'include=') === false) return '';
		if(is_array($selector)) return isset($selector['include']) ? $selector['include'] : '';
		$selector = $selector instanceof Selectors ? $selector : new Selectors($selector);
		$include = $selector->getSelectorByField('include');
		return $include ? $include->value() : '';
	}

	/**
	 * Builds the PageFinder options for the _next() method
	 * 
	 * @param Page $page
	 * @param string|array|Selectors $selector
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
			'alwaysAllowIDs' => array(),
		);
		
		if($page->isUnpublished() || $page->isHidden()) {
			// allow next() to still move forward even though it is hidden or unpublished
			$includeMode = $this->_getIncludeMode($selector);
			if(!$includeMode || ($includeMode === 'hidden' && $page->isUnpublished())) {
				$fo['alwaysAllowIDs'][] = $page->id;
			}
		}

		if(!$options['until']) return $fo;
	
		/***************************************************************
		 * All code below this specific to the 'until' option
		 * 
		 */ 
		
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
			$findOptions = $options['prev'] ? array() : array('startAfterID' => $page->id);
			$stopPage = $page->_pages('find', "$selector, limit=1, $until", $findOptions)->first();
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
	 * @param string|array|Selectors $selector Optional selector. When specified, will find nearest sibling(s) that match.
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
		$pages = $page->wire()->pages;
		$parent = $page->parent();
		
		if($options['until'] || $options['qty']) $options['all'] = true;
		if(!$parent || !$parent->id) {
			if($options['qty']) return 0;
			return $options['all'] ? $pages->newPageArray() : $pages->newNullPage();
		}
		
		if(is_array($selector)) {
			$selector['parent_id'] = $parent->id; 
		} else if(is_string($selector)) {
			$selector = trim("parent_id=$parent->id, $selector", ", ");
		} else if($selector instanceof Selectors) {
			$selector->add(new SelectorEqual('parent_id', $parent->id));
		} else {
			throw new WireException('Selector must be string, array or Selectors object');
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
			if($options['prev']) $result = $result->reverse();
			
		} else {
			$row = reset($rows);
			if($row && !empty($row['id'])) {
				$result = $pages->getById(array($row['id']), array(
					'template' => $page->wire()->templates->get($row['templates_id']),
					'parent_id' => $row['parent_id'],
					'getOne' => true,
					'cache' => $page->loaderCache
				));
			} else {
				$result = $pages->newNullPage();
			}
		}
		
		return $result;
	}

	/**
	 * Return the index/position of the given page relative to its siblings
	 * 
	 * If given a hidden or unpublished page, that page would not usually be part of the group of siblings. 
	 * As a result, such pages will return what the value would be if they were visible (as of 3.0.121). This
	 * may overlap with the index of other pages, since indexes are relative to visible pages, unless you
	 * specify an include mode (see next paragraph). 
	 * 
	 * If you want this method to include hidden/unpublished pages as part of the index numbers, then 
	 * specify boolean true for the $selector argument (which implies "include=all") OR specify a 
	 * selector of "include=hidden", "include=unpublished" or "include=all". 
	 * 
	 * @param Page $page
	 * @param string|array|bool|Selectors $selector Selector to apply or boolean true for "include=all" (since 3.0.121).
	 *  - Boolean true to include hidden and unpublished pages as part of the index numbers (same as "include=all").
	 *  - An "include=hidden", "include=unpublished" or "include=all" selector to include them in the index numbers.
	 *  - A string selector or selector array to filter the criteria for the returned index number.
	 * @return int Returns index number (zero-based)
	 * 
	 */
	public function index(Page $page, $selector = '') {
		if($selector === true) $selector = "include=all";
		$index = $this->_next($page, $selector, array('prev' => true, 'all' => true, 'qty' => 'index'));
		return $index;
	}
	
	/**
	 * Return the next sibling page
	 *
	 * @param Page $page
	 * @param string|array|Selectors $selector Optional selector. When specified, will find nearest next sibling that matches.
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
	 * @param string|array|Selectors $selector Optional selector. When specified, will find nearest previous sibling that matches.
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
	 * @param string|array|Selectors $selector Optional selector. When specified, will filter the found siblings.
	 * @param array $options Options to pass to the _next() method
	 * @return PageArray Returns all matching pages after this one.
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
	 * @param string|array|Selectors $selector Optional selector. When specified, will filter the found siblings.
	 * @param array $options Options to pass to the _next() method
	 * @return PageArray Returns all matching pages after this one.
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
	 * @param string|Page|array|Selectors $selector May either be a selector or Page to stop at. Results will not include this.
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
	 * - `pageNum` (int|string|bool): Specify pagination number, "+" for next pagination, "-" for previous pagination, or true for current.
	 * - `urlSegmentStr` (string|bool): Specify a URL segment string to append, or true (3.0.155+) for current. 
	 * - `urlSegments` (array|bool): Specify regular array of URL segments to append (may be used instead of urlSegmentStr). 
	 *    Specify boolean true for current URL segments (3.0.155+). 
	 *    Specify associative array (in 3.0.155+) to make both keys and values part of the URL segment string.  
	 * - `data` (array): Array of key=value variables to form a query string.
	 * - `http` (bool): Specify true to make URL include scheme and hostname (default=false).
	 * - `scheme` (string): Like the http option, makes URL include scheme and hostname, but you specify scheme with this, i.e. 'https' (3.0.178+)
	 * - `host` (string): Hostname to force use of, i.e. 'world.com' or 'hello.world.com'. The 'http' option is implied when host specified. (3.0.178+)
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

		$config = $page->wire()->config;
		$template = $page->template;

		$defaults = array(
			'http' => is_bool($options) ? $options : false,
			'scheme' => '', 
			'host' => '', 
			'pageNum' => is_int($options) || (is_string($options) && in_array($options, array('+', '-'))) ? $options : 1,
			'data' => array(),
			'urlSegmentStr' => (is_string($options) && !in_array($options, array('+', '-'))) ? $options : '',
			'urlSegments' => array(),
			'language' => is_object($options) && wireInstanceOf($options, 'Language') ? $options : null,
		);

		if(empty($options)) {
			$url = rtrim($config->urls->root, '/') . $page->path();
			if($template->slashUrls === 0 && $page->id > 1) $url = rtrim($url, '/');
			return $url;
		}

		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$sanitizer = $page->wire()->sanitizer;
		$input = $page->wire()->input;
		$languages = $page->wire()->languages;
		$language = null;
		$url = null;
		
		if($options['urlSegments'] === true || $options['urlSegmentStr'] === true) {
			$options['urlSegments'] = $input->urlSegments();
		}
		
		if($options['pageNum'] === true) {
			$options['pageNum'] = $input->pageNum();
		}

		if(is_array($options['urlSegments']) && count($options['urlSegments'])) {
			$str = '';
			reset($options['urlSegments']); 
			if(is_string(key($options['urlSegments']))) {
				// associative array converts to key/value style URL segments
				foreach($options['urlSegments'] as $key => $value) {
					$str .= "$key/$value/";
					if(is_int($key)) $str = ''; // abort assoc array option if any int key found
					if($str === '') break;
				}
			}
			if(strlen($str)) {
				$options['urlSegmentStr'] = rtrim($str, '/');
			} else {
				$options['urlSegmentStr'] = implode('/', $options['urlSegments']);
			}
		}

		if($options['language'] && $languages && $languages->hasPageNames()) {
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
			if($template->slashUrlSegments > -1) $url .= '/';
		}

		if($options['pageNum']) {
			if($options['pageNum'] === '+') {
				$options['pageNum'] = $input->pageNum + 1;
			} else if($options['pageNum'] === '-' || $options['pageNum'] === -1) {
				$options['pageNum'] = $input->pageNum - 1;
			}
			if((int) $options['pageNum'] > 1) {
				$prefix = '';
				if($language && $languages && $languages->hasPageNames()) {
					$prefix = (string) $languages->pageNames()->get("pageNumUrlPrefix$language");
				}
				if(!strlen($prefix)) $prefix = $config->pageNumUrlPrefix;
				$url = rtrim($url, '/') . '/' . $prefix . ((int) $options['pageNum']);
				if(((int) $template->slashPageNum) === 1) $url .= '/';
			}
		}

		if(count($options['data'])) {
			$query = http_build_query($options['data']);
			if($page->of()) $query = $sanitizer->entities($query);
			$url .= '?' . $query;
		}

		if($options['scheme']) {
			$scheme = strtolower($options['scheme']);
			if(strpos($scheme, '://') === false) $scheme .= '://';
			if($scheme === 'https://' && $config->noHTTPS) $scheme = 'http' . '://';
			$host = $options['host'] ? $options['host'] : $config->httpHost;
			$url = "$scheme$host$url";
			
		} else if($options['http'] || $options['host']) {
			$mode = $config->noHTTPS ? -1 : $template->https; 
			switch($mode) {
				case -1: $scheme = 'http'; break;
				case 1: $scheme = 'https'; break;
				default: $scheme = $config->https ? 'https' : 'http';
			}
			$host = $options['host'] ? $options['host'] : $config->httpHost;
			$url = "$scheme://$host$url";
		}

		return $url;
	}
	
	/**
	 * Return all URLs that this page can be accessed from (excluding URL segments and pagination)
	 *
	 * This includes the current page URL, any other language URLs (for which page is active), and
	 * any past (historical) URLs the page was previously available at (which will redirect to it).
	 *
	 * - Returned URLs do not include additional URL segments or pagination numbers.
	 * - Returned URLs are indexed by language name, i.e. “default”, “fr”, “es”, etc.
	 * - If multi-language URLs not installed, then index is just “default”.
	 * - Past URLs are indexed by language; then ISO-8601 date, i.e. “default;2016-08-11T07:44:43-04:00”,
	 *   where the date represents the last date that URL was considered current.
	 * - If PagePathHistory core module is not installed then past/historical URLs are excluded.
	 * - You can disable past/historical or multi-language URLs by using the $options argument.
	 *
	 * @param Page $page
	 * @param array $options Options to modify default behavior:
	 *  - `http` (bool): Make URLs include current scheme and hostname (default=false).
	 *  - `past` (bool): Include past/historical URLs? (default=true)
	 *  - `languages` (bool): Include other language URLs when supported/available? (default=true).
	 *  - `language` (Language|int|string): Include only URLs for this language (default=null). 
	 *     Note: the `languages` option must be true if using the `language` option. 
	 * @return array
	 *
	 */
	public function urls(Page $page, $options = array()) {

		$defaults = array(
			'http' => false,
			'past' => true,
			'languages' => true,
			'language' => null, 
		);

		$modules = $page->wire()->modules;
		$options = array_merge($defaults, $options);
		$languages = $options['languages'] ? $page->wire()->languages : null;
		$slashUrls = $page->template->slashUrls;
		$httpHostUrl = $options['http'] ? $page->wire()->input->httpHostUrl() : '';
		$urls = array();
		
		if($options['language'] && $languages) {
			if(!$options['language'] instanceof Page) {
				$options['language'] = $languages->get($options['language']);
			}
			if($options['language'] && $options['language']->id) {
				$languages = array($options['language']);
			}
		}

		// include other language URLs
		if($languages && $languages->hasPageNames()) {
			foreach($languages as $language) {
				/** @var Language $language */
				if(!$language->isDefault() && !$page->get("status$language")) continue;
				$urls[$language->name] = $page->localUrl($language);
			}
		} else {
			$urls = array('default' => $page->url());
		}

		// add in historical URLs
		if($options['past'] && $modules->isInstalled('PagePathHistory')) {
			/** @var PagePathHistory $history */
			$history = $modules->get('PagePathHistory');
			$rootUrl = $page->wire()->config->urls->root;
			$pastPaths = $history->getPathHistory($page, array(
				'language' => $options['language'],
				'verbose' => true	
			)); 
			foreach($pastPaths as $pathInfo) {
				$key = '';
				if(!empty($pathInfo['language'])) {
					/** @var Language $language */
					$language = $pathInfo['language'];
					if($options['languages']) {
						$key .= $language->name . ';';
					} else {
						// they asked to have multi-language excluded
						if(!$language->isDefault()) continue;
					}
				} 
				$key .= wireDate('c', $pathInfo['date']);
				$urls[$key] = $rootUrl . ltrim($pathInfo['path'], '/');
			}
		}

		// update URLs for current expected slash and http settings
		foreach($urls as $key => $url) {
			if($url !== '/') $url = $slashUrls ? rtrim($url, '/') . '/' : rtrim($url, '/');
			if($options['http']) $url = $httpHostUrl . $url;	
			$urls[$key] = $url;
		}
		
		return $urls;
	}
	
	/**
	 * Return the URL necessary to edit page
	 *
	 * - We recommend checking that the page is editable before outputting the editUrl().
	 * - If user opens URL in their browser and is not logged in, they must login to account with edit permission.
	 * - This method can also be accessed by property at `$page->editUrl` (without parenthesis).
	 *
	 * ~~~~~~
	 * if($page->editable()) {
	 *   echo "<a href='$page->editUrl'>Edit this page</a>";
	 * }
	 * ~~~~~~
	 *
	 * @param Page $page
	 * @param array|bool|string $options Specify true for http option, specify name of field to find (3.0.151+), or use $options array:
	 *  - `http` (bool): True to force scheme and hostname in URL (default=auto detect).
	 *  - `language` (Language|bool): Optionally specify Language to start editor in, or boolean true to force current user language.
	 *  - `find` (string): Name of field to find in the editor (3.0.151+)
	 *  - `vars` (array): Additional variables to include in query string (3.0.239+)
	 * @return string URL for editing this page
	 *
	 */
	public function editUrl(Page $page, $options = array()) {

		$config = $page->wire()->config;
		$adminTemplate = $page->wire()->templates->get('admin'); /** @var Template $adminTemplate */
		$https = $adminTemplate && ($adminTemplate->https > 0) && !$config->noHTTPS;
		$url = ($https && !$config->https) ? 'https://' . $config->httpHost : '';
		$url .= $config->urls->admin . "page/edit/?id=$page->id";
		$optionsArray = is_array($options) ? $options : array();

		if($options === true || (is_array($options) && !empty($options['http']))) {
			if(strpos($url, '://') === false) {
				$url = ($https || $config->https ? 'https' : 'http' ) . '://' . $config->httpHost . $url;
			}
		}

		$languages = $page->wire()->languages;
		if($languages) {
			$language = $page->wire()->user->language;
			if(empty($optionsArray['language'])) {
				if($page->wire()->page->template->id == $adminTemplate->id) $language = null;
			} else if($optionsArray['language'] instanceof Page) {
				$language = $optionsArray['language'];
			} else if($optionsArray['language'] !== true) {
				$language = $languages->get($optionsArray['language']);
			}
			if($language && $language->id) $url .= "&language=$language->id";
		}
		
		$version = (int) ((string) $page->get('_version|_repeater_version'));
		if($version) $url .= "&version=$version";
		
		if(!empty($optionsArray['vars'])) {
			$url .= '&' . http_build_query($optionsArray['vars']); 
		}

		$append = $page->wire()->session->getFor($page, 'appendEditUrl');

		if($append) $url .= $append;

		if($options) {
			if(is_string($options)) {
				$find = $options;
			} else if(is_array($options) && !empty($options['find'])) {
				$find = $options['find'];
			} else $find = '';
			if($find && strpos($url, '#') === false) {
				$url .= '#find-' . $page->wire()->sanitizer->fieldName($find);
			}
		}

		return $url;
	}
	
	/**
	 * Returns the URL to the page, including scheme and hostname
	 *
	 * - This method is just like the `$page->url()` method except that it also includes scheme and hostname.
	 *
	 * - This method can also be accessed at the property `$page->httpUrl` (without parenthesis).
	 *
	 * - It is desirable to use this method when some page templates require https while others don't.
	 *   This ensures local links will always point to pages with the proper scheme. For other cases, it may
	 *   be preferable to use `$page->url()` since it produces shorter output.
	 *
	 * ~~~~~
	 * // Generating a link to this page using httpUrl
	 * echo "<a href='$page->httpUrl'>$page->title</a>";
	 * ~~~~~
	 *
	 * @param Page $page
	 * @param array $options For details on usage see `Page::url()` options argument.
	 * @return string Returns full URL to page, for example: `https://processwire.com/about/`
	 * @see Page::url(), Page::localHttpUrl()
	 *
	 */
	public function httpUrl(Page $page, $options = array()) {
		
		$template = $page->template();
		if(!$template) return '';
		
		if(is_array($options)) unset($options['http']);
		if($options === true || $options === false) $options = array();
		
		$url = $page->url($options);
		if(strpos($url, '://')) return $url;
		
		$config = $page->wire()->config;
		$mode = $template->https;
		
		if($mode > 0 && $config->noHTTPS) $mode = 0;
		
		switch($mode) {
			case -1: $scheme = 'http'; break;
			case 1: $scheme = 'https'; break;
			default: $scheme = $config->https ? 'https' : 'http';
		}
		
		$url = "$scheme://$config->httpHost$url";
		
		return $url;
	}

	/**
	 * Return pages that are referencing the given one by way of Page references
	 * 
	 * @param Page $page
	 * @param string|bool $selector Optional selector to filter results by or boolean true as shortcut for `include=all`. 
	 * @param Field|string $field Limit to follower pages using this field, 
	 *   - or specify boolean TRUE to make it return array of PageArrays indexed by field name. 
	 * @param bool $getCount Specify true to return counts rather than PageArray(s)
	 * @return PageArray|array|int
	 * @throws WireException Highly unlikely
	 * 
	 */
	public function references(Page $page, $selector = '', $field = '', $getCount = false) {
		/** @var FieldtypePage $fieldtype */
		$fieldtype = $page->wire()->fieldtypes->get('FieldtypePage');	
		if(!$fieldtype) throw new WireException('Unable to find FieldtypePage');
		if($selector === true) $selector = "include=all";
		return $fieldtype->findReferences($page, $selector, $field, $getCount); 
	}
	
	/**
	 * Return number of VISIBLE pages that are following (referencing) the given one by way of Page references
	 * 
	 * Note that this excludes hidden, unpublished and otherwise non-accessible pages (access control). 
	 * If you do not want to exclude these, use the numFollowers() function instead, OR specify "include=all" for
	 * the $selector argument. 
	 *
	 * @param Page $page
	 * @param string $selector Filter count by this selector
	 * @param string|Field|bool $field Limit count to given Field or specify boolean true to return array of counts.
	 * @return int|array Returns count, or array of counts (if $field==true)
	 *
	 */
	public function hasReferences(Page $page, $selector = '', $field = '') {
		return $this->references($page, $selector, $field, true);
	}

	/**
	 * Return number of ANY pages that are following (referencing) the given one by way of Page references
	 * 
	 * @param Page $page
	 * @param string $selector Filter count by this selector
	 * @param string|Field|bool $field Limit count to given Field or specify boolean true to return array of counts. 
	 * @return int|array Returns count, or array of counts (if $field==true)
	 * 
	 */
	public function numReferences(Page $page, $selector = '', $field = '') {
		if(stripos($selector, "include=") === false) $selector = rtrim("include=all, $selector", ', ');
		return $this->hasReferences($page, $selector, $field); 
	}

	/**
	 * Return pages that this page is referencing by way of Page reference fields
	 * 
	 * @param Page $page
	 * @param bool|Field|string|int $field Limit results to requested field, or specify boolean true to return array indexed by field names.
	 * @param bool $getCount Specify true to return count(s) rather than pages. 
	 * @return PageArray|int|array
	 * 
	 */
	public function referencing(Page $page, $field = false, $getCount = false) {
		$fieldName = '';
		$byField = null;
		if(is_bool($field) || is_null($field)) {
			$byField = $field ? true : false;
		} else if(is_string($field)) {
			$fieldName = $page->wire()->sanitizer->fieldName($field);
		} else if(is_int($field)) {
			$field = $page->wire()->fields->get($field);
			if($field) $fieldName = $field->name;
		} else if($field instanceof Field) {
			$fieldName = $field->name;
		}

		// results
		$fieldCounts = array(); // counts indexed by field name (if count mode)
		$pages = $page->wire()->pages;
		$items = $pages->newPageArray();
		$itemsByField = array();
		
		foreach($page->template->fieldgroup as $f) {
			/** @var Field $f */
			if($fieldName && $field->name != $fieldName) continue;
			if(!$f->type instanceof FieldtypePage) continue;
			if($byField) $itemsByField[$f->name] = $pages->newPageArray();
			$value = $page->get($f->name);
			if($value instanceof Page && $value->id) {
				$items->add($value);
				if($byField) $itemsByField[$f->name]->add($value);
				$fieldCounts[$f->name] = 1;
			} else if($value instanceof PageArray && $value->count()) {
				$items->import($value);
				if($byField) $itemsByField[$f->name]->import($value);
				$fieldCounts[$f->name] = $value->count();
			} else {
				unset($itemsByField[$f->name]);
			}
		}
		
		if($getCount) return $byField ? $fieldCounts : $items->count();
		if($byField) return $itemsByField;
		
		return $items;
	}

	/**
	 * Return number of pages this one is following (referencing) by way of Page references
	 * 
	 * @param Page $page
	 * @param bool $field Optionally limit to field, or specify boolean true to return array of counts per field. 
	 * @return int|array
	 * 
	 */
	public function numReferencing(Page $page, $field = false) {
		return $this->referencing($page, $field, true); 
	}

	/**
	 * Find other pages linking to the given one by way contextual links is textarea/html fields
	 * 
	 * @param Page $page
	 * @param string $selector
	 * @param bool|string|Field $field
	 * @param array $options
	 *  - `getIDs` (bool): Return array of page IDs rather than Page instances. (default=false)
	 *  - `getCount` (bool): Return a total count (int) of found pages rather than Page instances. (default=false)
	 *  - `confirm` (bool): Confirm that the links are present by looking at the actual page field data. (default=true)
	 *     You can specify false for this option to make it perform faster, but with a potentially less accurate result.
	 * @return PageArray|array|int
	 * @throws WireException
	 * 
	 */
	public function links(Page $page, $selector = '', $field = false, array $options = array()) {
		/** @var FieldtypeTextarea $fieldtype */
		$fieldtype = $page->wire()->fieldtypes->get('FieldtypeTextarea');
		if(!$fieldtype) throw new WireException('Unable to find FieldtypeTextarea');
		return $fieldtype->findLinks($page, $selector, $field, $options); 
	}

	/**
	 * Return total found number of pages linking to this one with no exclusions
	 * 
	 * @param Page $page
	 * @param bool $field
	 * @return int
	 * 
	 */
	public function numLinks(Page $page, $field = false) {
		return $this->links($page, true, $field, array('getCount' => true));
	}

	/**
	 * Return total number of pages visible to current user linking to this one
	 * 
	 * @param Page $page
	 * @param bool $field
	 * @return array|int|PageArray
	 * 
	 */
	public function hasLinks(Page $page, $field = false) {
		return $this->links($page, '', $field, array('getCount' => true));
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
	 * @param PageArray|null $siblings Optional siblings to use instead of the default. May also be specified as first argument when no selector needed.
	 * @return Page|NullPage Returns the next sibling page, or a NullPage if none found.
	 *
	 */
	public function nextSibling(Page $page, $selector = '', ?PageArray $siblings = null) {
		if($selector instanceof PageArray) {
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
			/** @var Page $next */
			$next = $siblings->getNext($next, false);
			if(empty($selector) || !$next || $next->matches($selector)) break;
		} while($next->id);
		if(is_null($next)) $next = $page->wire()->pages->newNullPage();
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
	 * @param PageArray|null $siblings Optional siblings to use instead of the default. May also be specified as first argument when no selector needed.
	 * @return Page|NullPage Returns the previous sibling page, or a NullPage if none found. 
	 *
	 */
	public function prevSibling(Page $page, $selector = '', ?PageArray $siblings = null) {
		if($selector instanceof PageArray) {
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
			/** @var Page $prev */
			$prev = $siblings->getPrev($prev, false); 
			if(empty($selector) || !$prev || $prev->matches($selector)) break;
		} while($prev->id); 
		if(is_null($prev)) $prev = $page->wire()->pages->newNullPage();
		return $prev;
	}

	/**
	 * Return all sibling pages after this one, optionally matching a selector
	 *
	 * @param Page $page
	 * @param string|array $selector Optional selector. When specified, will filter the found siblings.
	 * @param PageArray|null $siblings Optional siblings to use instead of the default. 
	 * @return PageArray Returns all matching pages after this one.
	 *
	 */
	public function nextAllSiblings(Page $page, $selector = '', ?PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->prepend($page);
		}

		$id = $page->id;
		$all = $page->wire()->pages->newPageArray();
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
	 * @param PageArray|null $siblings Optional siblings to use instead of the default. 
	 * @return PageArray
	 *
	 */
	public function prevAllSiblings(Page $page, $selector = '', ?PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->add($page);
		}

		$id = $page->id;
		$all = $page->wire()->pages->newPageArray();

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
	public function nextUntilSiblings(Page $page, $selector = '', $filter = '', ?PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->prepend($page);
		}

		$siblings = $this->nextAllSiblings($page, '', $siblings); 
		$all = $page->wire()->pages->newPageArray();
		$stop = false;

		foreach($siblings as $sibling) {
			/** @var Page $sibling */

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
	public function prevUntilSiblings(Page $page, $selector = '', $filter = '', ?PageArray $siblings = null) {

		if(is_null($siblings)) {
			$siblings = $page->parent()->children();
		} else if(!$siblings->has($page)) {
			$siblings->add($page);
		}

		$siblings = $this->prevAllSiblings($page, '', $siblings); 
		$all = $page->wire()->pages->newPageArray();
		$stop = false;

		foreach($siblings->reverse() as $sibling) {
			/** @var Page $sibling */

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
