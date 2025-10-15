<?php namespace ProcessWire;

/**
 * ProcessWire Pages Loader
 * 
 * #pw-headline Pages Loader
 * #pw-var $pages->loader
 * #pw-breadcrumb Pages
 * #pw-summary Implements page finding/loading methods for the $pages API variable.
 * #pw-body =
 * Please always use `$pages->method()` rather than `$pages->loader->method()` in cases where there is overlap.
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesLoader extends Wire {
	
	/**
	 * Controls the outputFormatting state for pages that are loaded
	 *
	 */
	protected $outputFormatting = false;

	/**
	 * Autojoin allowed?
	 *
	 * @var bool
	 *
	 */
	protected $autojoin = true;
	
	/**
	 * @var Pages
	 * 
	 */
	protected $pages;

	/**
	 * Columns native to pages table
	 * 
	 * @var array
	 * 
	 */
	protected $nativeColumns = array();

	/**
	 * Total number of pages loaded by getById()
	 * 
	 * @var int
	 * 
	 */
	protected $totalPagesLoaded = 0;

	/**
	 * Last used instance of PageFinder
	 * 
	 * @var PageFinder|null
	 * 
	 */
	protected $lastPageFinder = null;

	/**
	 * Debug mode for pages class
	 * 
	 * @var bool
	 * 
	 */
	protected $debug = false;

	/**
	 * Are we currenty loading pages?
	 * 
	 * @var bool
	 * 
	 */
	protected $loading = false;

	/**
	 * Page instance ID
	 * 
	 * @var int
	 * 
	 */
	static protected $pageInstanceID = 0;

	/**
	 * Construct
	 * 
	 * @param Pages $pages
	 * 
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;
		$this->debug = $pages->debug();
	}
	
	/**
	 * Set whether loaded pages have their outputFormatting turned on or off
	 *
	 * By default, it is turned on.
	 * 
	 * #pw-group-settings
	 *
	 * @param bool $outputFormatting
	 *
	 */
	public function setOutputFormatting($outputFormatting = true) {
		$this->outputFormatting = $outputFormatting ? true : false;
	}

	/**
	 * Get whether loaded pages have their outputFormatting turned on or off
	 * 
	 * #pw-group-settings
	 *
	 * @return bool
	 *
	 */
	public function getOutputFormatting() {
		return $this->outputFormatting;
	}
	
	/**
	 * Enable or disable use of autojoin for all queries
	 *
	 * Default should always be true, and you may use this to turn it off temporarily, but
	 * you should remember to turn it back on
	 * 
	 * #pw-group-settings
	 *
	 * @param bool $autojoin
	 *
	 */
	public function setAutojoin($autojoin = true) {
		$this->autojoin = $autojoin ? true : false;
	}

	/**
	 * Get whether autojoin is enabled for page loading queries
	 * 
	 * #pw-group-settings
	 * 
	 * @return bool
	 * 
	 */
	public function getAutojoin() {
		return $this->autojoin;
	}

	/**
	 * Normalize a selector string 
	 * 
	 * This is to reduce the number of unique selectors that produce the same result. 
	 * It is helpful with caching results, so that we don't cache the same results multiple
	 * times because they used slightly different selectors. 
	 * 
	 * @param string $selector
	 * @param bool $convertIDs Normalize to integer ID or array of integer IDs when possible (default=true)
	 * @return array|int|string
	 * 
	 */
	protected function normalizeSelectorString($selector, $convertIDs = true) {
		
		$selector = trim($selector, ', ');

		if(ctype_digit($selector)) {
			// normalize to page ID (int)
			$selector = (int) $selector;

		} else if($selector === '/' || $selector === 'path=/') {
			// normalize selectors that indicate homepage to just be ID 1
			$selector = (int) $this->wire()->config->rootPageID;

		} else if($selector[0] === '/') {
			// if selector begins with a slash, it is referring to a path
			$selector = "path=$selector";
			
		} else if(strpos($selector, ',') === false) {
			// there is just one “key=value” or “value” selector that needs further processing
			if(strpos($selector, 'id=')) {
				if($convertIDs) {
					// string like id=123 or id=123|456|789 converted to int or int-array
					$s = substr($selector, 3); // skip over 'id='
					if(ctype_digit($s)) {
						// id=123
						$selector = (int) $s;
					} else if(strpos($selector, '|') && ctype_digit(str_replace('|', '', $s))) {
						// id=123|456|789
						$a = explode('|', $s);
						foreach($a as $k => $v) $a[$k] = (int) $v;
						$selector = $a;
					}
				}
			} else if(!Selectors::stringHasOperator($selector)) {
				// no operator indicates this is just referring to a page name
				$sanitizer = $this->wire()->sanitizer;
				if($sanitizer->pageNameUTF8($selector) === $selector) {
					// sanitized value consistent with a page name
					// optimize selector rather than determining value here
					$selector = 'name=' . $sanitizer->selectorValue($selector);
				}
			}
		}
		
		if(is_int($selector) || ctype_digit("$selector")) {
			// page ID integer
			if($convertIDs) {
				$selector = (int) $selector;
			} else {
				$selector = "id=$selector";
			}
		}
		
		/** @var array|int|string $selector */

		return $selector;
	}
	
	/**
	 * Normalize a selector
	 * 
	 * This is to reduce the number of unique selectors that produce the same result.
	 * It is helpful with caching results, so that we don't cache the same results multiple
	 * times because they used slightly different selectors.
	 * 
	 * @param string|int|array $selector
	 * @param bool $convertIDs Convert ID-only selectors to integers or arrays of integers?
	 * @return array|int|string
	 * 
	 */
	protected function normalizeSelector($selector, $convertIDs = true) {
		
		if(empty($selector)) return '';
	
		if(is_int($selector)) {
			if(!$convertIDs) $selector = "id=$selector"; 
		} else if(is_string($selector)) {
			$selector = $this->normalizeSelectorString($selector, $convertIDs);
		} else if(is_array($selector)) {
			// array that is not associative, not selector array, and consists of only numbers
			if($this->isIdArray($selector)) {
				if(!$convertIDs) $selector = 'id=' . implode('|', $selector);
			}
		}

		return $selector;
	}

	/**
	 * Is this an array of IDs? Also sanitizes to all integers when true
	 * 
	 * @param array $a
	 * @return bool
	 * 
	 */
	protected function isIdArray(array &$a) {
		if(ctype_digit(implode('', array_keys($a))) && !is_array(reset($a)) && ctype_digit(implode('', $a))) {
			// regular array of page IDs, we delegate that to getById() method, but with access/visibility control
			foreach($a as $k => $v) $a[$k] = (int) $v;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Helper for find() method to attempt to shortcut the find when possible
	 * 
	 * @param string|array|Selectors $selector
	 * @param array $options
	 * @param array $loadOptions
	 * @return bool|Page|PageArray Returns boolean false when no shortcut available
	 * 
	 */
	protected function findShortcut($selector, $options, $loadOptions) {
		
		if(empty($selector)) {
			return $this->pages->newPageArray($loadOptions);
		}
		
		$value = false;
		$filter = empty($options['findAll']);
		$selector = $this->normalizeSelector($selector, true); 
	
		if(is_array($selector)) {
			if($this->isIdArray($selector)) {
				$value = $this->getById($selector, $loadOptions);
				$filter = true;
			}	
				
		} else if(is_int($selector)) {
			// page ID integer
			$value = $this->getById(array($selector), $loadOptions);
		}
	
		if($value) {
			if($filter) {
				$includeMode = isset($options['include']) ? $options['include'] : '';
				$value = $this->filterListable($value, $includeMode, $loadOptions);
			}
			if($this->debug) {
				$this->pages->debugLog('find', $selector . " [optimized]", $value);
			}
		}

		return $value;
	}

	/**
	 * Given a Selector string, return the Page objects that match in a PageArray.
	 *
	 * Non-visible pages are excluded unless an `include=hidden|unpublished|all` mode is specified in the selector string,
	 * or in the `$options` array. If 'all' mode is specified, then non-accessible pages (via access control) can also be included.
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|int|array|Selectors $selector Specify selector (standard usage), but can also accept page ID or array of page IDs.
	 * @param array|string $options Optional one or more options that can modify certain behaviors. May be assoc array or key=value string.
	 *	- `findOne` (bool): Apply optimizations for finding a single page.
	 *  - `findAll` (bool): Find all pages with no exclusions (same as include=all option).
	 *  - `findIDs` (bool|int): Makes method return raw array rather than PageArray, specify one of the following:
	 *      • `true` (bool): return array of [ [id, templates_id, parent_id] ] for each page.
	 *      • `1` (int): Return just array of just page IDs, [id, id, id]
	 *      • `2` (int): Return all pages table columns in associative array for each page (3.0.153+).
	 *      • `3` (int): Same as 2 + dates are unix timestamps + has 'pageArray' key w/blank PageArray for pagination info (3.0.172+).
	 *      • `4` (int): Same as 3 + return PageArray instead if one is available in cache (3.0.172+).
	 *	- `getTotal` (bool): Whether to set returning PageArray's "total" property (default: true except when findOne=true)
	 *  - `cache` (bool): Allow caching of selectors and pages loaded (default=true). Also sets loadOptions[cache]. 
	 *  - `allowCustom` (bool): Whether to allow use of "_custom=new selector" in selectors (default=false). 
	 *  - `lazy` (bool): Makes find() return Page objects that don't have any data populated to them (other than id and template). 
	 *	- `loadPages` (bool): Whether to populate the returned PageArray with found pages (default: true).
	 *	   The only reason why you'd want to change this to false would be if you only needed the count details from
	 *	   the PageArray: getTotal(), getStart(), getLimit, etc. This is intended as an optimization for Pages::count().
	 * 	   Does not apply if $selectorString argument is an array.
	 *  - `caller` (string): Name of calling function, for debugging purposes, i.e. pages.count
	 * 	- `include` (string): Inclusion mode of 'hidden', 'unpublished' or 'all'. Default=none. Typically you would specify this
	 * 	   directly in the selector string, so the option is mainly useful if your first argument is not a string.
	 *  - `stopBeforeID` (int): Stop loading pages once page matching this ID is found (default=0).
	 *  - `startAfterID` (int): Start loading pages once page matching this ID is found (default=0).
	 * 	- `loadOptions` (array): Assoc array of options to pass to getById() load options. (does not apply when 'findIds' > 3). 
	 *  - `joinFields` (array): Names of fields to autojoin, or empty array to join none; overrides field autojoin settings (default=null) 3.0.172+
	 * @return PageArray|array
	 *
	 */
	public function find($selector, $options = array()) {

		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);

		$loadOptions = isset($options['loadOptions']) && is_array($options['loadOptions']) ? $options['loadOptions'] : array();
		$loadPages = array_key_exists('loadPages', $options) ? (bool) $options['loadPages'] : true; 
		$caller = isset($options['caller']) ? $options['caller'] : 'pages.find';
		$lazy = empty($options['lazy']) ? false : true;
		$findIDs = isset($options['findIDs']) ? $options['findIDs'] : false;
		$debug = $this->debug && !$lazy;
		$allowShortcuts = $loadPages && !$lazy && (!$findIDs || $findIDs === 4); 
		$joinFields = isset($options['joinFields']) ? $options['joinFields'] : array();
		$cachePages = isset($options['cache']) ? $options['cache'] : true;
		
		if($cachePages) {
			$options['cache'] = $cachePages;
			$loadOptions['cache'] = $cachePages;
		} else if(!isset($loadOptions['cache'])) {
			$loadOptions['cache'] = false;
		}
		
		if($allowShortcuts) {
			$pages = $this->findShortcut($selector, $options, $loadOptions);
			if($pages) return $pages;
		}
		
		if($selector instanceof Selectors) {
			$selectors = $selector;
		} else {
			$selector = $this->normalizeSelector($selector, false); 
			$selectors = $this->wire(new Selectors()); /** @var Selectors $selectors */
			$selectors->init($selector);
		}
		
		if(isset($options['include']) && in_array($options['include'], array('hidden', 'unpublished', 'all'))) {
			$selectors->add(new SelectorEqual('include', $options['include']));
		}

		$selectorString = is_string($selector) ? $selector : (string) $selectors;

		// check whether the joinFields option will be used
		if(!$lazy && !$findIDs) {
			$fields = $this->wire()->fields;
			// support the joinFields option when selector contains 'field=a|b|c' or 'join=a|b|c'
			foreach(array('field', 'join') as $name) {
				if(strpos($selectorString, "$name=") === false || $fields->get($name)) continue; 
				foreach($selectors as $selector) {
					if($selector->field() !== $name) continue;
					$joinFields = array_merge($joinFields, $selector->values());
					$selectors->remove($selector);
				}
			}
			if(count($joinFields)) {
				unset($options['include']); // because it was moved into $selectors earlier
				return $this->findMin($selectors, array_merge($options, array('joinFields' => $joinFields)));
			}
		} 
		
		// see if this has been cached and return it if so
		if($allowShortcuts) {
			$pages = $this->pages->cacher()->getSelectorCache($selectorString, $options);
			if($pages !== null) {
				if($debug) $this->pages->debugLog('find', $selectorString, $pages . ' [from-cache]');
				return $pages;
			}
		}
		
		$pageFinder = $this->pages->getPageFinder();
		$pagesInfo = array();
		$pagesIDs = array();
	
		if($debug) Debug::timer("$caller($selectorString)", true);
		$profiler = $this->wire()->profiler;
		$profilerEvent = $profiler ? $profiler->start("$caller($selectorString)", "Pages") : null;
		
		if(($lazy || $findIDs) && strpos($selectorString, 'limit=') === false) $options['getTotal'] = false;
	
		if($lazy) {
			// [ pageID => templateID ]
			$pagesIDs = $pageFinder->findTemplateIDs($selectors, $options); 
			
		} else if($findIDs === 1) {
			// [ pageID ]
			$pagesIDs = $pageFinder->findIDs($selectors, $options);
			
		} else if($findIDs === 2) {
			// [ pageID => [ all pages columns ] ]
			$pagesInfo = $pageFinder->findVerboseIDs($selectors, $options);
			
		} else if($findIDs === 3 || $findIDs === 4) {
			// [ pageID => [ all pages columns + sortfield + dates as unix timestamps ],
			// 'pageArray' => PageArray(blank but with pagination info populated) ] ]
			$options['joinSortfield'] = true;
			$options['getNumChildren'] = true;
			$options['unixTimestamps'] = true;
			$pagesInfo = $pageFinder->findVerboseIDs($selectors, $options);
			
		} else {
			// [ [ 'id' => 3, 'templates_id' => 2, 'parent_id' => 1, 'score' => 1.123 ]
			$pagesInfo = $pageFinder->find($selectors, $options);
		}
		
		if($debug && empty($loadOptions['caller'])) {
			$loadOptions['caller'] = "$caller($selectorString)";
		}

		// note that we save this pagination state here and set it at the end of this method
		// because it's possible that more find operations could be executed as the pages are loaded
		$total = $pageFinder->getTotal();
		$limit = $pageFinder->getLimit();
		$start = $pageFinder->getStart();
		
		if($lazy) {
			// lazy load: create empty pages containing only id and template
			$templates = $this->wire()->templates;
			$pages = $this->pages->newPageArray($loadOptions);
			$pages->finderOptions($options);
			$pages->setDuplicateChecking(false);
			$loadPages = false;
			$cachePages = false;
			$template = null;
			$templatesByID = array();
			$loading = $this->loading;

			if(!$loading) $this->loading = true;
			foreach($pagesIDs as $id => $templateID) {
				if(isset($templatesByID[$templateID])) {
					$template = $templatesByID[$templateID];
				} else {
					$template = $templates->get($templateID);
					$templatesByID[$templateID] = $template;
				}
				$page = $this->pages->newPage($template);
				$page->_lazy($id);
				$page->of($this->outputFormatting);
				$page->loaderCache = false;
				$pages->add($page);
			}

			if(!$loading) $this->loading = false;
			$pages->setDuplicateChecking(true);
			if(count($pagesIDs)) $pages->_lazy(true);
			unset($template, $templatesByID);

		} else if($findIDs) {
			
			$loadPages = false;
			$cachePages = false;
			// PageArray for hooks or for findIDs==3 option
			$pages = $this->pages->newPageArray($loadOptions); 

		} else if($loadPages) {
			// parent_id is null unless a single parent was specified in the selectors
			$templates = $this->wire()->templates;
			$parent_id = $pageFinder->getParentID();
			$idsSorted = array();
			$idsByTemplate = array();
			$scores = array();

			// organize the pages by template ID
			foreach($pagesInfo as $page) {
				$tpl_id = (int) $page['templates_id'];
				$id = (int) $page['id'];
				if(!isset($idsByTemplate[$tpl_id])) $idsByTemplate[$tpl_id] = array();
				$idsByTemplate[$tpl_id][] = $id;
				$idsSorted[] = $id;
				if(!empty($page['score'])) $scores[$id] = (float) $page['score'];
			}

			if(count($idsByTemplate) > 1) {
				// perform a load for each template, which results in unsorted pages
				// @todo use $idsUnsorted array rather than $unsortedPages PageArray
				$unsortedPages = $this->pages->newPageArray($loadOptions);
				foreach($idsByTemplate as $tpl_id => $ids) {
					$opt = $loadOptions;
					$opt['template'] = $templates->get($tpl_id);
					$opt['parent_id'] = $parent_id;
					$unsortedPages->import($this->getById($ids, $opt));
				}

				// put pages back in the order that the selectorEngine returned them in, while double checking that the selector matches
				$pages = $this->pages->newPageArray($loadOptions);
				foreach($idsSorted as $id) {
					foreach($unsortedPages as $page) {
						if($page->id == $id) {
							$pages->add($page);
							break;
						}
					}
				}
			} else {
				// there is only one template used, so no resorting is necessary	
				$pages = $this->pages->newPageArray($loadOptions);
				reset($idsByTemplate);
				$opt = $loadOptions;
				$opt['template'] = $templates->get(key($idsByTemplate));
				$opt['parent_id'] = $parent_id;
				$pages->import($this->getById($idsSorted, $opt));
			}
			
			$sortsAfter = $pageFinder->getSortsAfter();
			if(count($sortsAfter)) $pages->sort($sortsAfter);
			
			if(count($scores)) {
				foreach($pages as $page) {
					$score = isset($scores[$page->id]) ? $scores[$page->id] : 0; 
					$page->setQuietly('_pfscore', $score); 
				}
			}

		} else {
			$pages = $this->pages->newPageArray($loadOptions);
		}

		$pageFinder->getPageArrayData($pages); 
		$pages->setTotal($total);
		$pages->setLimit($limit);
		$pages->setStart($start);
		$pages->setSelectors($selectorString);
		$pages->setTrackChanges(true);
		$this->lastPageFinder = $pageFinder; 

		if($loadPages && $cachePages) {
			if(strpos($selectorString, 'sort=random') !== false) {
				if($selectors->getSelectorByFieldValue('sort', 'random')) $cachePages = false;
			}
			if($cachePages) {
				$this->pages->cacher()->selectorCache($selectorString, $options, $pages);
			}
		}

		if($debug) {
			$this->pages->debugLog('find', $selectorString, $pages);
			$count = $pages->count();
			$note = ($count == $total ? $count : $count . "/$total") . " page(s)";
			if($count) {
				$note .= ": " . $pages->first()->path;
				if($count > 1) $note .= " ... " . $pages->last()->path;
			}
			if(substr($caller, -1) !== ')') $caller .= "($selectorString)";
			Debug::saveTimer($caller, $note);
			foreach($pages as $item) {
				if($item->_debug_loader) continue;
				$item->setQuietly('_debug_loader', $caller);
			}
		}
		
		if($profilerEvent) $profiler->stop($profilerEvent);

		if($this->pages->hasHook('found()')) $this->pages->found($pages, array(
			'pageFinder' => $pageFinder,
			'pagesInfo' => $pagesInfo,
			'options' => $options
		));
		
		if($findIDs) {
			if($findIDs === 3 || $findIDs === 4) $pagesInfo['pageArray'] = $pages;
			return $findIDs === 1 ? $pagesIDs : $pagesInfo;
		}

		return $pages;
	}

	/**
	 * Minimal find for reduced or delayed overload in some circumstances
	 * 
	 * This combines the page finding and page loading operation into a single operation
	 * and single query, unlike a regular find() which finds matching page IDs in one 
	 * query and then loads them in a separate query. As a result this method does not
	 * need to call the getByIds() method to load pages, as it is able to load them itself. 
	 * 
	 * This strategy may eventually replace the “find() + getByIds()” strategy, but for the
	 * moment is only used when the `$pages->find()` method specifies `field=name` in 
	 * the selector. In that selector, `name` can be any field name, or group of them, i.e.
	 * `title|date|summary`, or a non-existing field like `none` to specify that no fields 
	 * should be autojoin (for fastest performance). 
	 * 
	 * Note that while this might reduce overhead in some cases, it can also increase the 
	 * overall request time if you omit fields that are actually used on the resulting pages.
	 * For instance, if the `title` field is an autojoin field (as it is by default), and 
	 * we do a `$pages->find('template=blog-post, field=none');` and then render a list of
	 * blog post titles, then we have just increased overhead because PW would have to 
	 * perform a separate query to load each blog-post page’s title. On the other hand, if 
	 * we render a list of blog post titles with date and summary, and the date and summary 
	 * fields are not configured as autojoin fields, then we can specify all those that we 
	 * use in our rendered list to greatly improve performance, like this: 
	 * `$pages->find('template=blog-post, field=title|date|summary');`.
	 * 
	 * While this method combines what find() and getById() do in one query, there does not
	 * appear to be any overhead benefit when the two strategies are dealing with identical
	 * conditions, like the same autojoin fields.
	 * 
	 * #pw-group-retrieve
	 * 
	 * @param string|array|Selectors $selector
	 * @param array $options
	 *  - `cache` (bool): Allow pulling from and saving results to cache? (default=true)
	 *  - `joinFields` (array): Names of fields to also join into the page load
	 * @return PageArray
	 * @throws WireException
	 * @since 3.0.172
	 * 
	 */
	public function findMin($selector, array $options = array()) {

		$useCache = isset($options['cache']) ? $options['cache'] : true;
		$templates = $this->wire()->templates;
		$languages = $this->wire()->languages;
		$languageIds = array();
		$templatesById = array();
		$tmpAutojoinFields = array(); // fields to autojoin temporarily, just during this method call

		if($languages) foreach($languages as $language) $languageIds[$language->id] = $language->id;
		
		$options['findIDs'] = $useCache ? 4 : 3;
		$joinFields = isset($options['joinFields']) ? $options['joinFields'] : array();
		$rows = $this->find($selector, $options);
		
		// if PageArray was already available in cache, return it now
		if($rows instanceof PageArray) return $rows;
	
		/** @var PageArray $pageArray */
		$pageArray = $rows['pageArray'];
		$pageArray->setTrackChanges(false);
		$paginationTotal = $pageArray->getTotal();
	
		/** @var array $joinResults PageFinder sets which fields supported autojoin true|false */
		$joinResults = $pageArray->data('joinFields');
		
		unset($rows['pageArray']);

		foreach($rows as $row) {
			
			$page = $useCache ? $this->pages->getCache($row['id']) : null;
			$tid = (int) $row['templates_id'];
			
			if($page) {
				$pageArray->add($page);
				continue;
			}
		
			if(isset($templatesById[$tid])) {
				$template = $templatesById[$tid]; 
			} else {
				$template = $templates->get($tid);
				if(!$template) continue;
				$templatesById[$tid] = $template;
			}
			
			$sortfield = $template->sortfield;
			if(empty($sortfield) && isset($row['sortfield'])) $sortfield = $row['sortfield'];
			
			$set = array(
				'pageClass' => $template->getPageClass(),
				'isLoaded' => false,
				'id' => $row['id'],
				'template' => $template,
				'parent_id' => $row['parent_id'],
				'sortfield' => $sortfield,
			);
		
			unset($row['templates_id'], $row['parent_id'], $row['id'], $row['sortfield']);
			
			$page = $this->pages->newPage($set);
			$page->instanceID = ++self::$pageInstanceID;
			
			if($languages) {
				foreach($languageIds as $id) {
					$key = "name$id";
					if(isset($row[$key]) && strpos($row[$key], 'xn-') === 0) {
						$page->setName($row[$key], $key);
						unset($row[$key]);
					}
				}
			}

			foreach($row as $key => $value) {
				if(strpos($key, '__')) {
					if($value === null) {
						// $row[$key] = 'null'; // ensure detected by later isset in foreach($joinFields)
						$row[$key] = new NullField();
					} else {
						$page->setFieldValue($key, $value, false);
					}
				} else {
					$page->setForced($key, $value);
				}
			}
			
			foreach($joinFields as $joinField) {
				if(empty($joinResults[$joinField])) continue; // field did not support autojoin
				if(!$template->fieldgroup->hasField($joinField)) continue;
				$field = $page->getField($joinField);
				if(!$field || !$field->type) continue;
				$v = isset($row["{$joinField}__data"]) ? $row["{$joinField}__data"] : null;
				if($v instanceof NullField) $v = null;
				// if(isset($row["{$joinField}__data"])) {
				if($v !== null) {
					if(!$field->hasFlag(Field::flagAutojoin)) {
						$field->addFlag(Field::flagAutojoin);
						$tmpAutojoinFields[$field->id] = $field;
					}
				} else {
					// set blank values where joinField didn't appear on page row 
					$blankValue = $field->type->getBlankValue($page, $field);
					$page->setFieldValue($field->name, $blankValue, false);
				}
			}

			$page->setIsLoaded(true);
			$page->setIsNew(false);
			$page->resetTrackChanges(true);
			$page->setOutputFormatting($this->outputFormatting);
			$this->totalPagesLoaded++;

			$pageArray->add($page);
			
			if($useCache) $this->pages->cache($page);
		}

		$pageArray->setTotal($paginationTotal);
		$pageArray->resetTrackChanges(true);
		
		foreach($tmpAutojoinFields as $field) { /** @var Field $field */
			$field->removeFlag(Field::flagAutojoin)->untrackChange('flags');
		}
		
		if($useCache) {
			$selectorString = $pageArray->getSelectors(true);
			$this->pages->cacher()->selectorCache($selectorString, $options, $pageArray);
		}

		return $pageArray;
	}


	/**
	 * Like find() but returns only the first match as a Page object (not PageArray)
	 *
	 * This is functionally similar to the get() method except that its default behavior is to
	 * filter for access control and hidden/unpublished/etc. states, in the same way that the
	 * find() method does. You can add an `include=` to your selector with value `hidden`, 
	 * `unpublished` or `all` to change this behavior, just like with find(). 
	 * 
	 * Unlike the find() method, this method performs a secondary runtime access check by calling 
	 * `$page->viewable()` with the found $page, and returns a `NullPage` if the page is not
	 * viewable with that call. In 3.0.142+, an `include=` mode of `all` or `unpublished` will 
	 * override this, where appropriate.
	 * 
	 * This method also accepts an `$options` array, whereas `Pages::get()` does not.
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|int|array|Selectors $selector
	 * @param array|string $options See $options for `Pages::find`
	 * @return Page|NullPage
	 *
	 */
	public function findOne($selector, $options = array()) {
		
		if(empty($selector)) return $this->pages->newNullPage();
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		
		$defaults = array(
			'findOne' => true, // find only one page
			'getTotal' => false, // don't count totals
			'caller' => 'pages.findOne'
		);
		
		$options = array_merge($defaults, $options);
		$items = $this->pages->find($selector, $options);
		$page = $items->first();

		if(isset($options['findAll']) && $options['findAll'] === true) {
			// page is always allowed through when findAll=true
		} else if(isset($options['include']) && $options['include'] === 'all') {
			// page is always allowed through when include=all
		} else if($page && !$page->viewable(false)) {
			// page found but is not viewable, check if include mode was specified and would allow the page
			$include = isset($options['include']) ? strtolower($options['include']) : null;
			$checkAccess = true;
			$selectors = $items->getSelectors();
			if($selectors) {
				if($include === null) {
					$include = $selectors->getSelectorByField('include');
					if($include) $include = strtolower($include->value());
				}
				$checkAccess = $selectors->getSelectorByField('check_access');
				if(!$checkAccess) $checkAccess = $selectors->getSelectorByField('checkAccess');
				$checkAccess = $checkAccess ? (bool) $checkAccess->value() : true;
			}
			if(!$include) {
				// there was no “include=” selector present
				if($checkAccess === true) $page = null;
			} else if($include === 'all') {
				// allow $page to pass through with include=all mode
			} else if($include === 'unpublished' && $page->isUnpublished() && $checkAccess) {
				// check if user would have access without unpublished status
				$status = $page->status;
				$page->setQuietly('status', $status & ~Page::statusUnpublished);
				$viewable = $page->viewable(false);
				$page->setQuietly('status', $status); // restore
				if(!$viewable) $page = null;
			} else {
				if($checkAccess === true) $page = null;
			}
		}

		return $page && $page->id ? $page : $this->pages->newNullPage();
	}
	
	/**
	 * Find pages and cache the result for specified period of time
	 *
	 * Use this when you want to cache a slow or complex page finding operation so that it doesn’t
	 * have to be repated for every web request. Note that this only caches the find operation
	 * and not the loading of the found pages.
	 *
	 * ~~~~~
	 * $items = $pages->findCache("title%=foo"); // 60 seconds (default)
	 * $items = $pages->findCache("title%=foo", 3600); // 1 hour
	 * $items = $pages->findCache("title%=foo", "+1 HOUR");  // same as above
	 * ~~~~~
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|array|Selectors $selector
	 * @param int|string|bool|null $expire When the cache should expire, one of the following:
	 *  - Max age integer (in seconds).
	 *  - Any string accepted by PHP’s `strtotime()` that specifies when the cache should be expired.
	 *  - Any `WireCache::expire…` constant or anything accepted by the `WireCache::get()` $expire argument.
	 * @param array $options Options to pass to `$pages->getByIDs()`, or:
	 *  - `findIDs` (bool): Return just the page IDs rather then the actual pages? (default=false)
	 * @return PageArray|array
	 * @since 3.0.218
	 *
	 */
	public function findCache($selector, $expire = 60, $options = array()) {

		$user = $this->wire()->user;
		$cache = $this->wire()->cache;
		$ns = 'pages.findCache';
		$items = null;

		if(is_string($selector)) {
			$selectorStr = $selector;
			$selectors = $selector;
		} else {
			$selectors = $this->wire(new Selectors($selector));
			$selectorStr = (string) $selectors;
		}

		$rolesStr = (string) $user->roles;
		if(strpos($rolesStr, '|')) {
			$rolesArray = explode('|', $rolesStr);
			sort($rolesArray);
			$rolesStr = implode('|', $rolesArray);
		}

		$optionsStr = '';
		foreach($options as $key => $value) {
			if(!is_string($value)) {
				if(is_array($value)) $value = print_r($value, true);
				$value = (string) $value;
			}
			$optionsStr .= "$key==$value,";
		}

		$cacheName = "$rolesStr\r$selectorStr\r$optionsStr";
		$pageNum = $this->wire()->input->pageNum();
		if($pageNum > 1 && Selectors::selectorHasField($selectors, 'limit')) {
			if(!Selectors::selectorHasField($selectors, 'start')) $cacheName .= "\r$pageNum";
		}
		$cacheName = md5($cacheName);
		$data = $cache->getFor($ns, $cacheName, $expire);
		
		if(!empty($data) && $data['selector'] === $selectorStr && $data['roles'] === $rolesStr) {
			$ids = $data['pages'];
		} else {
			$ids = null;
			if(strpos($selectorStr, 'template') !== false && empty($options['template'])) {
				$info = Selectors::selectorHasField($selectors, array('template', 'templates_id'), array('verbose' => true));
				if($info['result']) $options['template'] = $this->wire()->templates->get($info['value']);
				echo "template=$options[template]\n";
			}
		}

		if($ids === null) {
			if(empty($options['findIDs'])) {
				$items = $this->find($selectors, $options);
				$ids = $items->explode('id');
			} else {
				$ids = $this->pages->findIDs($selectors, $options);
			}
			$data = array(
				'selector' => $selectorStr,
				'roles' => $rolesStr,
				'pages' => $ids
			);
			$cache->saveFor($ns, $cacheName, $data, $expire);
			
		} else if(empty($options['findIDs'])) {
			$items = $this->pages->getByIDs($ids, $options);
		}
		
		if(!empty($options['findIDs'])) return $ids;

		foreach($items as $item) {
			if($item instanceof NullPage || $item->status & Page::statusTrash) {
				$items->remove($item);
			}
		}

		return $items;
	}

	/**
	 * Returns the first page matching the given selector with no exclusions
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|int|array|Selectors $selector
	 * @param array $options See Pages::find method for options
	 * @return Page|NullPage Always returns a Page object, but will return NullPage (with id=0) when no match found
	 *
	 */
	public function get($selector, $options = array()) {
		
		if(empty($selector)) return $this->pages->newNullPage();
		
		if(is_int($selector)) {
			$getCache = true;
		} else if(is_string($selector) && (ctype_digit($selector) || strpos($selector, 'id=') === 0)) {
			$getCache = true;
		} else {
			$getCache = false;
		}
		
		if($getCache) {
			// if cache is possible, allow user-specified options to dictate whether cache is allowed
			if(isset($options['loadOptions']) && isset($options['loadOptions']['getFromCache'])) {
				$getCache = (bool) $options['loadOptions']['getFromCache'];
			}
			if($getCache) {
				$page = $this->pages->getCache($selector); // selector is either 123 or id=123
				if($page) return $page;
			}
		}
		
		$defaults = array(
			'findOne' => true, // find only one page
			'findAll' => true, // no exclusions
			'getTotal' => false, // don't count totals
			'caller' => 'pages.get'
		);
		
		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		$page = $this->pages->find($selector, $options)->first();
		if(!$page) $page = $this->pages->newNullPage();
		
		return $page;
	}

	/**
	 * Is there any page that matches the given $selector in the system? (with no exclusions)
	 *
	 * - This can be used as an “exists” or “getID” type of method.
	 * - Returns ID of first matching page if any exist, or 0 if none exist (returns array if `$verbose` is true).
	 * - Like with the `get()` method, no pages are excluded, so an `include=all` is not necessary in selector.
	 * - If you need to quickly check if something exists, this method is preferable to using a count() or get().
	 *
	 * When `$verbose` option is used, an array is returned instead. Verbose return array includes all columns
	 * from the matching row in the pages table.
	 * 
	 * #pw-group-retrieve
	 * 
	 * @param string|int|array|Selectors $selector
	 * @param bool $verbose Return verbose array with all pages columns rather than just page id? (default=false)
	 * @param array $options Additional options to pass in find() $options argument (not currently applicable)
	 * @return array|int
	 * @since 3.0.153
	 * 
	 */
	public function has($selector, $verbose = false, array $options = array()) {
	
		$defaults = array(
			'findOne' => true, // find only one page
			'findAll' => true, // no exclusions
			'findIDs' => $verbose ? 2 : 1, // 2=all cols, 1=IDs only
			'getTotal' => false, // don't count totals
			'caller' => 'pages.has',
		);

		$options = count($options) ? array_merge($defaults, $options) : $defaults;
		if(empty($selector)) return $verbose ? array() : 0;

		if((is_string($selector) || is_int($selector)) && !$verbose) {
			// see if any matching page is already in the cache
			$page = $this->pages->getCache($selector);
			if($page) return $page->id;
		}
		
		$items = $this->pages->find($selector, $options);
		
		if($verbose) {
			$value = count($items) ? reset($items) : array();
		} else {
			$value = count($items) ? (int) reset($items) : 0;
		}
		
		return $value; 
	}
	
	/**
	 * Given an array or CSV string of Page IDs, return a PageArray
	 *
	 * Optionally specify an $options array rather than a template for argument 2. When present, the 'template' and 'parent_id' arguments may be provided
	 * in the given `$options` array. These options may be specified:
	 *
	 * LOAD OPTIONS (argument 2 array):
	 * 
	 * - `cache` (bool): Place loaded pages in memory cache? (default=true)
	 * - `getFromCache` (bool): Allow use of previously cached pages in memory (rather than re-loading it from DB)? (default=true)
	 * - `template` (Template): See $template argument for details. (default=null)
	 * - `parent_id` (int): See $parent_id argument for details (default=null)
	 * - `getNumChildren` (bool): Specify false to disable retrieval and population of 'numChildren' Page property. (default=true)
	 * - `getOne` (bool): Specify true to return just one Page object, rather than a PageArray. (default=false)
	 * - `autojoin` (bool): Allow use of autojoin option? (default=true)
	 * - `joinFields` (array): Autojoin the field names specified in this array, regardless of field settings, requires `autojoin=true`. (default=empty)
	 * - `joinSortfield` (bool): Whether the 'sortfield' property will be joined to the page. (default=true)
	 * - `findTemplates` (bool): Determine which templates will be used (when no template specified) for more specific autojoins. (default=true)
	 * - `pageClass` (string): Class to instantiate Page objects with. Leave blank to determine from template. (default=auto-detect)
	 * - `pageArrayClass` (string): PageArray-derived class to store pages in (when 'getOne' is false). (default=PageArray)
	 * - `pageArray` (PageArray): Optional predefined PageArray to populate to (default=null)
	 * - `page` (Page): Existing Page object to populate (also requires the getOne option to be true). (default=null)
	 * - `caller` (string): Name of calling function, for debugging purposes (default=blank).
	 *
	 * Use the `$options` array for potential speed optimizations:
	 * - Specify a 'template' with your call, when possible, so that this method doesn't have to determine it separately.
	 * - Specify false for 'getNumChildren' for potential speed optimization when you know for certain pages will not have children.
	 * - Specify false for 'autojoin' for potential speed optimization in certain scenarios (can also be a bottleneck, so be sure to test).
	 * - Specify false for 'joinSortfield' for potential speed optimization when you know the Page will not have children or won't need to know the order.
	 * - Specify false for 'findTemplates' so this method doesn't have to look them up. Potential speed optimization if you have few autojoin fields globally.
	 * - Note that if you specify false for 'findTemplates' the pageClass is assumed to be 'Page' unless you specify something different for the 'pageClass' option.
	 * 
	 * #pw-group-retrieve
	 *
	 * @param array|WireArray|string|int $_ids Array of page IDs, comma or pipe-separated string of IDs, or single page ID (string or int)
	 *  or in 3.0.156+ array of associative arrays where each in format: [ 'id' => 123, 'templates_id' => 456 ]
	 * @param Template|array|string|int|null $template Specify a template to make the load faster, because it won't have to attempt to join all possible fields... 
	 *  just those used by the template. Optionally specify an $options array instead, see the method notes above.
	 * @param int|null $parent_id Specify a parent to make the load faster, as it reduces the possibility for full table scans.
	 *	This argument is ignored when an options array is supplied for the $template.
	 * @return PageArray|Page|NullPage Returns Page only if the 'getOne' option is specified, otherwise always returns a PageArray.
	 * @throws WireException
	 *
	 */
	public function getById($_ids, $template = null, $parent_id = null) {

		$options = array(
			'cache' => true,
			'getFromCache' => true,
			'template' => null,
			'parent_id' => null,
			'getNumChildren' => true,
			'getOne' => false,
			'autojoin' => true,
			'findTemplates' => true,
			'joinSortfield' => true,
			'joinFields' => array(),
			'page' => null, 
			'pageClass' => '',  // blank = auto detect
			'pageArray' => null, // PageArray to populate to
			'pageArrayClass' => 'PageArray',
			'caller' => '', 
		);
	
		$templates = $this->wire()->templates;
		$database = $this->wire()->database;
		$idsByTemplate = array();
		$loading = $this->loading;

		if(is_array($template)) {
			// $template property specifies an array of options
			$options = array_merge($options, $template);
			$template = $options['template'];
			$parent_id = $options['parent_id'];
			if("$options[cache]" === "1") $options['cache'] = true;
		} else if(!is_null($template) && !$template instanceof Template) {
			throw new WireException('getById argument 2 must be Template or $options array');
		}

		if(!is_null($parent_id) && !is_int($parent_id)) {
			// convert Page object or string to integer id
			$parent_id = (int) ((string) $parent_id);
		}

		if(!is_null($template) && !is_object($template)) {
			// convert template string or id to Template object
			$template = $templates->get($template);
		}

		if(is_string($_ids)) {
			// convert string of IDs to array
			$_ids = trim($_ids, '|, ');
			if(ctype_digit($_ids)) {
				$_ids = array((int) $_ids); // single ID: "123"
			} else if(strpos($_ids, '|')) {
				$_ids = explode('|', $_ids); // pipe-separated IDs: "123|456|789"
			} else if(strpos($_ids, ',')) {
				$_ids = explode(',', $_ids); // comma-separated IDs: "123,456,789"
			} else {
				$_ids = array(); // unrecognized ID string: fail
			}
		} else if(is_int($_ids)) {
			$_ids = array($_ids);
		}

		if(!WireArray::iterable($_ids) || !count($_ids)) {
			// return blank if $_ids isn't iterable or is empty
			return $options['getOne'] ? $this->pages->newNullPage() : $this->pages->newPageArray($options);
		}

		if(is_object($_ids)) $_ids = $_ids->getArray(); // ArrayObject or the like

		$loaded = array(); // array of id => Page objects that have been loaded
		$ids = array(); // sanitized version of $_ids

		// sanitize ids and determine which pages we can pull from cache
		foreach($_ids as $key => $id) {
			
			if(!is_int($id)) {
				if(is_array($id)) {
					if(!isset($id['id'])) continue;
					$tid = isset($id['templates_id']) ? (int) $id['templates_id'] : 0;
					$id = (int) $id['id'];
					if($tid) {
						if(!isset($idsByTemplate[$tid])) $idsByTemplate[$tid] = array();
						$idsByTemplate[$tid][] = $id;
					}
				} else {
					$id = trim($id);
					if(!ctype_digit($id)) continue;
					$id = (int) $id;
				}
			}
			
			if($id < 1) continue;
			
			$key = (int) $key;
			
			if($options['getOne'] && is_object($options['page'])) {
				// single page that will be populated directly
				$loaded[$id] = ''; 
				$ids[$key] = $id;

			} else if($options['getFromCache'] && $page = $this->pages->getCache($id)) {
				// page is already available in the cache	
				if($template && $page->template->id != $template->id) {
					// do not load: does not match specified template
				} else if($parent_id && $page->parent_id != $parent_id) {
					// do not load: does not match specified parent_id
				} else {
					$loaded[$id] = $page;
				}

			} else if(isset(Page::$loadingStack[$id])) {
				// if the page is already in the process of being loaded, point to it rather than attempting to load again.
				// the point of this is to avoid a possible infinite loop with autojoin fields referencing each other.
				$p = Page::$loadingStack[$id];
				if($p) {
					$loaded[$id] = $p;
					// cache the pre-loaded version so that other pages referencing it point to this instance rather than loading again
					$this->pages->cache($loaded[$id]);
				}

			} else {
				$loaded[$id] = ''; // reserve the spot, in this order
				$ids[$key] = $id; // queue id to be loaded
			}
		}

		$idCnt = count($ids); // idCnt contains quantity of remaining page ids to load
		if(!$idCnt) {
			// if there are no more pages left to load, we can return what we've got
			if($options['getOne']) {
				$page = count($loaded) ? reset($loaded) : null;
				return $page instanceof Page ? $page : $this->pages->newNullPage();
			}
			$pages = $this->pages->newPageArray($options);
			$pages->setDuplicateChecking(false);
			$pages->import($loaded);
			$pages->setDuplicateChecking(true);
			return $pages;
		}

		if(!$loading) $this->loading = true;

		if(count($idsByTemplate)) {
			// ok
		} else if($template === null && $options['findTemplates']) {

			// template was not defined with the function call, so we determine
			// which templates are used by each of the pages we have to load

			$sql = 'SELECT id, templates_id FROM pages';
			if($idCnt == 1) {
				$query = $database->prepare("$sql WHERE id=:id");
				$query->bindValue(':id', (int) reset($ids), \PDO::PARAM_INT); 
			} else {
				$ids = array_map('intval', $ids);
				$sql = "$sql WHERE id IN(" . implode(',', $ids) . ")";
				$query = $database->prepare($sql);
			}

			$result = $database->execute($query);
			if($result) {
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $query->fetch(\PDO::FETCH_NUM)) {
					list($id, $templates_id) = $row;
					$id = (int) $id;
					$templates_id = (int) $templates_id;
					if(!isset($idsByTemplate[$templates_id])) $idsByTemplate[$templates_id] = array();
					$idsByTemplate[$templates_id][] = $id;
				}
			}
			$query->closeCursor();

		} else if($template === null) {
			// no template provided, and autojoin not needed (so we don't need to know template)
			$idsByTemplate = array(0 => $ids);

		} else {
			// template was provided
			$idsByTemplate = array($template->id => $ids);
		}

		foreach($idsByTemplate as $templates_id => $ids) {

			if($templates_id && (!$template || $template->id != $templates_id)) {
				$template = $templates->get($templates_id);
			}

			if($template) {
				$fields = $template->fieldgroup;
			} else {
				$fields = $this->wire()->fields;
			}

			/** @var DatabaseQuerySelect $query */
			$query = $this->wire(new DatabaseQuerySelect());
			$sortfield = $template ? $template->sortfield : '';
			$joinSortfield = empty($sortfield) && $options['joinSortfield'];
			
			// note that "false AS isLoaded" triggers the setIsLoaded() function in Page intentionally
			$select = 'false AS isLoaded, pages.templates_id AS templates_id, pages.*, ';
			if($joinSortfield) {
				$select .= 'pages_sortfields.sortfield, ';
			}
			if($options['getNumChildren']) {
				$select .= "\n(SELECT COUNT(*) FROM pages AS children WHERE children.parent_id=pages.id) AS numChildren";
			}

			$query->select(rtrim($select, ', '));
			$query->from('pages');
			if($joinSortfield) $query->leftjoin('pages_sortfields ON pages_sortfields.pages_id=pages.id');

			if($options['autojoin'] && $this->autojoin) {
				foreach($fields as $field) {
					/** @var Field $field */
					if(!empty($options['joinFields']) && in_array($field->name, $options['joinFields'])) {
						// joinFields option specified to force autojoin this field
					} else {
						// check if autojoin not enabled for field
						if(!($field->flags & Field::flagAutojoin)) continue; 
						// non-fieldgroup, autojoin only if global flag is set
						if($fields instanceof Fields && !($field->flags & Field::flagGlobal)) continue; 
					}
					$table = $database->escapeTable($field->table);
					// check autojoin not allowed, otherwise merge in the autojoin query
					$fieldtype = $field->type;
					if(!$fieldtype || !$fieldtype->getLoadQueryAutojoin($field, $query)) continue; 
					// complete autojoin
					$query->leftjoin("$table ON $table.pages_id=pages.id"); // QA
				}
			}
			
			if(count($ids) > 1) {
				$ids = array_map('intval', $ids);
				$query->where('pages.id IN(' . implode(',', $ids) . ')');
			} else {
				$id = reset($ids);
				$query->where('pages.id=:id');
				$query->bindValue(':id', (int) $id, \PDO::PARAM_INT);
			}

			if(!is_null($parent_id)) {
				$query->where('pages.parent_id=:parent_id');
				$query->bindValue(':parent_id', (int) $parent_id, \PDO::PARAM_INT);
			}
			
			if($template) {
				$query->where('pages.templates_id=:templates_id');
				$query->bindValue(':templates_id', (int) $template->id, \PDO::PARAM_INT);
			}

			$query->groupby('pages.id');
			$stmt = $query->prepare();
			$database->execute($stmt);

			$class = $options['pageClass'];
			if(empty($class)) $class = $template ? $template->getPageClass() : __NAMESPACE__ . "\\Page";

			// page to populate, if provided in 'getOne' mode
			/** @var Page|null $_page */
			$_page = $options['getOne'] && $options['page'] instanceof Page ? $options['page'] : null;

			try {
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
					if($_page) {
						// populate provided Page object
						$page = $_page;
						$page->set('template', $template ? $template : (int) $row['templates_id']);
						if(!$page->get('parent_id')) $page->set('parent_id', (int) $row['parent_id']); 
					} else {
						// create new Page object
						$pageTemplate = $template ? $template : $templates->get((int) $row['templates_id']); 
						$pageClass = empty($options['pageClass']) && $pageTemplate ? $pageTemplate->getPageClass() : $class; 
						$page = $this->pages->newPage(array(
							'pageClass' => $pageClass,
							'template' => $pageTemplate ? $pageTemplate : $row['templates_id'],
							'parent' => $row['parent_id'], 
						));
					}
					unset($row['templates_id'], $row['parent_id']);
					$page->loaderCache = $options['cache'];
					foreach($row as $key => $value) $page->set($key, $value);
					$page->instanceID = ++self::$pageInstanceID;
					$page->setIsLoaded(true);
					$page->setIsNew(false);
					$page->resetTrackChanges(true);
					$page->setOutputFormatting($this->outputFormatting);
					$loaded[$page->id] = $page;
					if($options['cache'] === true) {
						$this->pages->cache($page);
					} else if($options['cache']) {
						$this->pages->cacher()->cacheGroup($page, $options['cache']);
					}
					$this->totalPagesLoaded++;
				}
			} catch(\Exception $e) {
				$error = $e->getMessage() . " [pageClass=$class, template=$template]";
				$user = $this->wire()->user;
				if($user && $user->isSuperuser()) $this->error($error);
				$this->wire()->log->error($error);
				$this->trackException($e, false);
			}

			$stmt->closeCursor();
			$template = null;
		}

		if($options['getOne']) {
			if(!$loading) $this->loading = false;
			$page = count($loaded) ? reset($loaded) : null;
			return $page instanceof Page ? $page : $this->pages->newNullPage();
		}
		
		$pages = $this->pages->newPageArray($options);
		$pages->setDuplicateChecking(false);
		$pages->import($loaded);
		$pages->setDuplicateChecking(true);
		if(!$loading) $this->loading = false;

		// debug mode only
		if($this->debug) {
			$page = $this->wire()->page;
			if($page && $page->template == 'admin') {
				if(empty($options['caller'])) {
					$_template = is_null($template) ? '' : ", $template";
					$_parent_id = is_null($parent_id) ? '' : ", $parent_id";
					if(count($_ids) > 10) {
						$_ids = '[' . reset($_ids) . '…' . end($_ids) . ', ' . count($_ids) . ' pages]';
					} else {
						$_ids = count($_ids) > 1 ? "[" . implode(',', $_ids) . "]" : implode('', $_ids);
					}
					$options['caller'] = "pages.getById($_ids$_template$_parent_id)";
				}
				foreach($pages as $item) {
					$item->setQuietly('_debug_loader', $options['caller']);
				}
			}
		}
		

		return $pages;
	}

	/**
	 * Find page(s) by name
	 * 
	 * This method is optimized just for finding pages by name and it does
	 * not perform any filtering or access checking.
	 * 
	 * #pw-group-retrieve
	 * 
	 * @param string $name Match this page name
	 * @param array $options
	 *  - `parent' (int|Page): Match this parent ID (default=0)
	 *  - `parentName` (string): Match this parent name (default='')
	 *  - `getArray` (bool): Get PHP info array rather than Page|NullPage|PageArray? (default=false)
	 *  - `getOne` (bool|int): Get just one match of Page or NullPage? (default=false)
	 *     When true, if multiple pages match then NullPage will be returned. To instead return
	 *     the first match, specify int `1` instead of boolean true.
	 * @return array|NullPage|Page|PageArray
	 * 
	 */
	public function findByName($name, array $options = array()) {
		
		$defaults = array(
			'parent' => 0, 
			'parentName' => '',
			'getArray' => false,
			'getOne' => false,
		);
		
		$options = array_merge($defaults, $options);
		$getArray = $options['getArray'];
		$getOne = $options['getOne'];
		
		$blankRow = array(
			'id' => 0,
			'templates_id' => 0,
			'parent_id' => 0,
		);
		
		$joins = array();
		
		$selects = array(
			'pages.id',
			'pages.parent_id',
			'pages.templates_id',
		);
		
		$wheres = array(
			'pages.name=:name',
		);
		
		$binds = array(
			'name' => $name,
		);
		
		if($options['parent']) {
			$wheres[] = 'pages.parent_id=:parentId';
			$binds['parentId'] = (int) "$options[parent]";
		}
			
		if($options['parentName']) {
			$joins[] = 'JOIN pages AS parent ON pages.parent_id=parent.id AND parent.name=:parentName';
			$binds['parentName'] = $options['parentName'];
		}
		
		$sql = 
			'SELECT ' . implode(', ', $selects) . ' ' . 
			'FROM pages ' . implode(' ', $joins) . ' ' . 
			'WHERE ' . implode(' AND ', $wheres) . ' ';
		
		if($getOne) $sql .= 'LIMIT 2';
		
		$query = $this->wire()->database->prepare($sql);
		foreach($binds as $bindKey => $bindValue) {
			$query->bindValue(":$bindKey", $bindValue);
		}
		
		$query->execute();
		$rowCount = (int) $query->rowCount();
		$rows = array();
		
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$rows[] = $row;
		}
		
		$query->closeCursor();
		
		if($getOne === 1 && $rowCount > 1) {
			// multiple rows found but only first one requested
			$rowCount = 1;
		}
	
		if($rowCount === 0) {
			// no rows matched
			if($getOne) {
				return $getArray ? $blankRow : $this->pages->newNullPage();
			} else {
				return $getArray ? array() : $this->pages->newPageArray();
			}
		} else if($rowCount === 1) {
			// one row matched
			if($getOne) {
				return $getArray ? reset($rows) : $this->pages->getByIDs($rows, array('getOne' => true));
			} else {
				return $getArray ? $rows : $this->pages->getByIDs($rows);
			}
		} else {
			// multiple rows matched
			if($getOne) {
				// return blank (multiple not allowed here)
				return $getArray ? $blankRow : $this->pages->newNullPage();
			} else {
				// return all
				return $getArray ? $rows : $this->pages->getByIDs($rows);
			}
		}
	}

	/**
	 * Given an ID return a path to a page, without loading the actual page
	 *
	 * Please note
	 * ===========
	 * 1) Always returns path in default language, unless a language argument/option is specified.
	 * 2) Path may be different from 'url' as it doesn't include $config->urls->root at the beginning.
	 * 3) In most cases, it's preferable to use $page->path() rather than this method. This method is
	 *    here just for cases where a path is needed without loading the page.
	 * 4) It's possible for there to be Page::path() hooks, and this method completely bypasses them,
	 *    which is another reason not to use it unless you know such hooks aren't applicable to you.
	 * 
	 * #pw-group-retrieve
	 *
	 * @param int|Page $id ID of the page you want the path to
	 * @param null|array|Language|int|string $options Specify $options array or Language object, id or name. Allowed options:
	 *  - language (int|string|anguage): To retrieve in non-default language, specify language object, ID or name (default=null)
	 *  - useCache (bool): Allow pulling paths from already loaded pages? (default=true)
	 *  - usePagePaths (bool): Allow pulling paths from PagePaths module, if installed? (default=true)
	 * @return string Path to page or blank on error/not-found
	 *
	 */
	public function getPath($id, $options = array()) {
		
		$modules = $this->wire()->modules;
		$database = $this->wire()->database;
		$languages = $this->wire()->languages;
		$config = $this->wire()->config;

		$defaults = array(
			'language' => null,
			'useCache' => true,
			'usePagePaths' => true
		);

		if(!is_array($options)) {
			// language was specified rather than $options
			$defaults['language'] = $options;
			$options = array();
		}

		$options = array_merge($defaults, $options);

		if($id instanceof Page) {
			if($options['useCache']) return $id->path();
			$id = $id->id;
		}

		$id = (int) $id;
		if(!$id || $id < 0) return '';

		if($languages && !$languages->hasPageNames()) $languages = null;
		
		$language = $options['language'];
		$languageID = 0;
		$homepageID = (int) $config->rootPageID;

		if(!empty($language) && $languages) {
			if(is_string($language) || is_int($language)) $language = $languages->get($language);
			if(!$language->isDefault()) $languageID = (int) $language->id;
		}

		// if page is already loaded and cache allowed, then get the path from it
		if($options['useCache'] && $page = $this->pages->getCache($id)) {
			/** @var Page $page */
			if($languageID) $languages->setLanguage($language);
			$path = $page->path();
			if($languageID) $languages->unsetLanguage();
			return $path;

		} else if($id === $homepageID && $languages && !$languageID) {
			// default language in multi-language environment, let $page handle it since there is additional 
			// hooked logic there provided by LanguageSupportPageNames
			$page = $this->pages->get($homepageID);
			$languages->setDefault();
			$path = $page->path();
			$languages->unsetDefault();
			return $path;
		}

		// if PagePaths module is installed, and not in multi-language environment, attempt to get from PagePaths module
		if(!$languages && !$languageID && $options['usePagePaths'] && $modules->isInstalled('PagePaths')) {
			/** @var PagePaths $pagePaths */
			$pagePaths = $modules->get('PagePaths');
			$path = $pagePaths->getPath($id);
			if($path) return $path;
		}

		$path = '';
		$templatesID = 0;
		$parentID = $id;
		$maxParentID = $language ? 0 : 1;
		$cols = 'parent_id, templates_id, name';
		if($languageID) $cols .= ", name$languageID"; // col=3
		$query = $database->prepare("SELECT $cols FROM pages WHERE id=:parent_id");

		do {
			$query->bindValue(":parent_id", (int) $parentID, \PDO::PARAM_INT);
			$database->execute($query);
			$row = $query->fetch(\PDO::FETCH_NUM);
			if(!$row) {
				$path = '';
				break;
			}
			$parentID = (int) $row[0];
			$templatesID = (int) $row[1];
			$name = empty($row[3]) ? $row[2] : $row[3];

			if($parentID) {
				// non-homepage
				$path = $name . '/' . $path;
			} else {
				// homepage
				if($name !== Pages::defaultRootName && !empty($name)) {
					$path = $name . '/' . $path;
				}
			}

		} while($parentID > $maxParentID);

		if(!strlen($path) || $path === '/') return $path;
		$path = trim($path, '/');

		if($templatesID) {
			$template = $this->wire()->templates->get($templatesID);
			if($template->slashUrls) $path .= '/';
		}

		return '/' . ltrim($path, '/');
	}
	
	/**
	 * Get a page by its path, similar to $pages->get('/path/to/page/') but with more options
	 * 
	 * #pw-group-retrieve
	 *
	 * Please note
	 * ===========
	 * 1) There are no exclusions for page status or access. If needed, you should validate access
	 *    on any page returned from this method.
	 * 2) In a multi-language environment, you must specify the $useLanguages option to be true, if you
	 *    want a result for a $path that is (or might be) a multi-language path. Otherwise, multi-language
	 *    paths will make this method return a NullPage (or 0 if getID option is true).
	 * 3) Partial paths may also match, so long as the partial path is completely unique in the site.
	 *    If you don't want that behavior, double check the path of the returned page.
	 * 4) See also the newer/more capable `$pages->pathFinder()` methods `get('/path/')` and `getPage('/path/')`.
	 *
	 * @param string $path
	 * @param array|bool $options array of options (below), or specify boolean for $useLanguages option only.
	 *  - `getID` (bool): Specify true to just return the page ID (default=false)
	 *  - `useLanguages` (bool): Specify true to allow retrieval by language-specific paths (default=false)
	 *  - `useHistory` (bool): Allow use of previous paths used by the page, if PagePathHistory module is installed (default=false)
	 *  - `allowUrl` (bool): Allow getting page by path OR url? Specify false to find only by path. This option only applies if
	 *     the site happens to run from a subdirectory. (default=true) 3.0.184+
	 *  - `allowPartial` (bool): Allow partial paths to match? (default=true) 3.0.184+
	 *  - `allowUrlSegments` (bool): Allow paths with URL segments to match? When true and page match cannot be found, the closest
	 *     parent page that allows URL segments will be returned. Found URL segments are populated to a `_urlSegments` array
	 *     property on the returned page object. This also cancels the allowPartial setting. (default=false) 3.0.184+
	 * @return Page|int
	 * @see PagesPathFinder::get(), PagesPathFinder::getPage()
	 *
	 */
	public function getByPath($path, $options = array()) {

		$modules = $this->wire()->modules;
		$sanitizer = $this->wire()->sanitizer;
		$config = $this->wire()->config;
		$database = $this->wire()->database;

		$defaults = array(
			'getID' => false,
			'useLanguages' => false,
			'useHistory' => false,
			'allowUrl' => true,
			'allowPartial' => true,
			'allowUrlSegments' => false,
			'_isRecursive' => false,
		);

		if(!is_array($options)) {
			$defaults['useLanguages'] = (bool) $options;
			$options = array();
		}
		
		$options = array_merge($defaults, $options);
		if(isset($options['getId'])) $options['getID'] = $options['getId']; // case alternate
		$homepageID = (int) $config->rootPageID;
		$rootUrl = $this->wire()->config->urls->root;

		if($options['allowUrl'] && $rootUrl !== '/' && strpos($path, $rootUrl) === 0) {
			// root URL is subdirectory and path has that subdirectory
			$rootName = trim($rootUrl, '/');
			if(strpos($rootName, '/')) {
				// root URL has multiple levels of subdirectories, remove them from path
				list(,$path) = explode(rtrim($rootUrl, '/'), $path, 2);
			} else {
				// one subdirectory, see if a page has the same name
				$query = $database->prepare('SELECT id FROM pages WHERE parent_id=1 AND name=:name');
				$query->bindValue(':name', $rootName);
				$query->execute();
				if($query->rowCount() > 0) {
					// leave subdirectory in path because page in site also matches subdirectory name
				} else {
					// remove root URL subdirectory from path 
					list(,$path) = explode(rtrim($rootUrl, '/'), $path, 2);
				}
				$query->closeCursor();
			}
		}

		if($path === '/') {
			// this can only be homepage
			return $options['getID'] ? $homepageID : $this->getById($homepageID, array('getOne' => true));
		} else if(empty($path)) {
			// path is empty and cannot match anything
			return $options['getID'] ? 0 : $this->pages->newNullPage();
		}

		$_path = $path;
		$path = $sanitizer->pagePathName($path, Sanitizer::toAscii);
		$pathParts = explode('/', trim($path, '/'));
		$_pathParts = $pathParts;
		
		$languages = $options['useLanguages'] ? $this->wire()->languages : null;
		if($languages && !$languages->hasPageNames()) $languages = null;

		$langKeys = array(':name' => 'name');
		if($languages) {
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$languageID = (int) $language->id;
				$langKeys[":name$languageID"] = "name$languageID";
			}
		}

		$pageID = 0;
		$templatesID = 0;
		$parentID = 0;

		if($options['allowPartial'] && !$options['allowUrlSegments']) {
			// first see if we can find a single page just having the name that's the last path part
			// this is an optimization if the page name happens to be globally unique in the system, which is often the case
			$name = end($pathParts);
			$binds = array(':name' => $name);
			$wheres = array();
			$numParts = count($pathParts);

			// can match 'name' or 'name123' cols where 123 is language ID
			foreach($langKeys as $bindKey => $colName) {
				$wheres[] = "$colName=$bindKey";
				$binds[$bindKey] = $name;
			}
			$sql = 'SELECT id, templates_id, parent_id FROM pages WHERE (' . implode(' OR ', $wheres) . ') ';

			if($numParts == 1) {
				$sql .= ' AND (parent_id=:parent_id ';
				$binds[':parent_id'] = $homepageID;
				if($languages) {
					$sql .= 'OR id=:homepage_id ';
					$binds[':homepage_id'] = $homepageID;
				}
				$sql .= ') ';
			}

			$sql .= 'LIMIT 2';
			$query = $database->prepare($sql);
			foreach($binds as $key => $value) $query->bindValue($key, $value);
			$database->execute($query);
			$numRows = $query->rowCount();
			if($numRows == 1) {
				// if only 1 page matches then we’ve found what we’re looking for
				list($pageID, $templatesID, $parentID) = $query->fetch(\PDO::FETCH_NUM);
			} else if($numRows == 0) {
				// no page can possibly match last segment
			} else if($numRows > 1) {
				// multiple pages match
			}
			$query->closeCursor();
		}

		if(!$pageID) {
			// multiple pages have the name or partial path match is not allowed
			// build a query joining all the path parts
			$joins = array();
			$wheres = array();
			$binds = array();
			$n = 0;
			$lastAlias = "pages";
			$lastPart = array_pop($pathParts);

			while(count($pathParts)) {
				$n++;
				$alias = "_pages$n";
				$part = array_pop($pathParts);
				$whereORs = array();
				foreach($langKeys as $bindKey => $colName) {
					$bindKey .= "_$n";
					$whereORs[] = "$alias.$colName=$bindKey";
					$binds[$bindKey] = $part;
				}
				$where = '(' . implode(' OR ', $whereORs) . ')';
				$joins[] = "\nJOIN pages AS $alias ON $lastAlias.parent_id=$alias.id AND $where";
				//$wheres[] = $where; // appears to be redundant as where only needed in join
				$lastAlias = $alias;
			}

			$isRootParent = !$n;
			// there were no pathParts, so we are matching just a rootParent
			if($isRootParent) $wheres[] = "pages.parent_id=1";

			$whereORs = array();
			foreach($langKeys as $bindKey => $colName) {
				$whereORs[] = "pages.$colName=$bindKey";
				$binds[$bindKey] = $lastPart;
			}
			$wheres[] = '(' . implode(' OR ', $whereORs) . ')';

			$sql =
				'SELECT pages.id, pages.templates_id, pages.parent_id, pages.name '  .
				'FROM pages ' . implode(' ', $joins) . " \n" .
				'WHERE (' . implode(' AND ', $wheres) . ') ';

			$query = $database->prepare($sql);
			foreach($binds as $key => $value) $query->bindValue($key, $value);
			$database->execute($query);
			$rowCount = $query->rowCount();
			
			if($rowCount === 1) {
				// just one page matched
				$row = $query->fetch(\PDO::FETCH_NUM); 
				list($pageID, $templatesID, $parentID, ) = $row;
				
			} else if($rowCount > 1 && $isRootParent) {
				// multiple pages matched off root
				// use either 'default' language match or first matching language
				$rows = array();
				while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
					$rows[] = $row;
					if($row['name'] !== $lastPart) continue;
					$rows = array($row); // force use of only this row (default language)
					break;
				}
				$row = reset($rows);
				list($pageID, $templatesID, $parentID) = array($row['id'], $row['templates_id'], $row['parent_id']); 
				
			} else if($rowCount > 1) {
				// multiple pages matched somewhere in site, we need a stronger tool (pagesPathFinder)
				$pathFinder = $this->pages->pathFinder();
				$info = $pathFinder->get($_path, array(
					'useLanguages' => $options['useLanguages'], 
					'useHistory' => $options['useHistory'], 
				));
				if(!empty($info['page']['id'])) {
					// pathFinder found a match
					if(count($info['urlSegments']) && !$options['allowUrlSegments']) {
						// found URL segments and they weren't allowed by options
					} else {
						$pageID = $info['page']['id'];
						$templatesID = $info['page']['templates_id'];
						$parentID = $info['page']['parent_id'];
					}
				}
			} else if($isRootParent) {
				// no page matches possible, maybe a URL segment for homepage?
				
			} else {
				// no match found yet
			}
			
			$query->closeCursor();
		}

		if(!$pageID && $options['useHistory'] && $modules->isInstalled('PagePathHistory')) {
			// if finding failed, check if there is a previous path it lived at, if history module available 
			$pph = $modules->get('PagePathHistory'); /** @var PagePathHistory $pph */
			$page = $pph->getPage($sanitizer->pagePathNameUTF8($_path));
			if($page->id) return $options['getID'] ? $page->id : $page;
		}

		if(!$pageID && $options['allowUrlSegments'] && !$options['_isRecursive'] && count($_pathParts)) {
			// attempt to match parent pages that allow URL segments
			$pathParts = $_pathParts;
			$urlSegments = array();
			$recursiveOptions = array_merge($options, array(
				'getID' => false,
				'allowUrlSegments' => false,
				'allowPartial' => false,
				'_isRecursive' => true
			));

			do {
				$urlSegment = array_pop($pathParts);
				array_unshift($urlSegments, $urlSegment);
				$path = '/' . implode('/', $pathParts);
				$page = $this->getByPath($path, $recursiveOptions);
			} while(count($pathParts) && !$page->id);

			if($page->id) {
				if($page->template->urlSegments) {
					// matched page template allows URL segments
					$page->setQuietly('_urlSegments', $urlSegments);
					if(!$options['getID']) return $page;
					$pageID = $page->id;
				} else {
					// page template does not allow URL segments, so path cannot match
					$pageID = 0;
				}
			}
		}

		if($options['getID']) return (int) $pageID;
		if(!$pageID) return $this->pages->newNullPage();

		return $this->getById((int) $pageID, array(
			'template' => $templatesID ? $this->wire()->templates->get((int) $templatesID) : null,
			'parent_id' => (int) $parentID,
			'getOne' => true
		));
	}

	/**
	 * Get a fresh, non-cached copy of a Page from the database
	 *
	 * This method is the same as `$pages->get()` except that it skips over all memory caches when loading a Page.
	 * Meaning, if the Page is already in memory, it doesn’t use the one in memory and instead reloads from the DB.
	 * Nor does it place the Page it loads in any memory cache. Use this method to load a fresh copy of a page
	 * that you might need to compare to an existing loaded copy, or to load a copy that won’t be seen or touched
	 * by anything in ProcessWire other than your own code.
	 *
	 * ~~~~~
	 * $p1 = $pages->get(1234);
	 * $p2 = $pages->get($p1->path);
	 * $p1 === $p2; // true: same Page instance
	 *
	 * $p3 = $pages->getFresh($p1);
	 * $p1 === $p3; // false: same Page but different instance
	 * ~~~~~
	 *
	 * #pw-group-retrieve
	 *
	 * @param Page|string|array|Selectors|int $selectorOrPage Specify Page to get copy of, selector or ID
	 * @param array $options Options to modify behavior
	 * @return Page|NullPage
	 * @since 3.0.172
	 *
	 */
	public function getFresh($selectorOrPage, $options = array()) {
		if(!isset($options['cache'])) $options['cache'] = false;
		if(!isset($options['loadOptions'])) $options['loadOptions'] = array();
		if(!isset($options['caller'])) $options['caller'] = 'pages.loader.getFresh';
		$options['loadOptions']['getFromCache'] = false;
		if(!isset($options['loadOptions']['cache'])) $options['loadOptions']['cache'] = false;
		$selector = $selectorOrPage instanceof Page ? $selectorOrPage->id : $selectorOrPage;
		return $this->get($selector, $options);
	}

	/**
	 * Load total number of children from DB for given page
	 * 
	 * #pw-group-retrieve
	 * 
	 * @param int|Page $page Page or Page ID
	 * @return int
	 * @throws WireException
	 * @since 3.0.172
	 * 
	 */
	public function getNumChildren($page) {
		$pageId = $page instanceof Page ? $page->id : (int) $page;
		$sql = 'SELECT COUNT(*) FROM pages WHERE parent_id=:id';
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':id', $pageId, \PDO::PARAM_INT);
		$query->execute();
		$numChildren = (int) $query->fetchColumn(); 
		$query->closeCursor();
		return $numChildren;
	}
	
	/**
	 * Count and return how many pages will match the given selector string
	 * 
	 * #pw-group-retrieve
	 *
	 * @param string|array|Selectors $selector Specify selector, or omit to retrieve a site-wide count.
	 * @param array|string $options See $options in Pages::find
	 * @return int
	 *
	 */
	public function count($selector = '', $options = array()) {
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		if(empty($selector)) {
			if(empty($options)) {
				// optimize away a simple site-wide total count
				$query = $this->wire()->database->query("SELECT COUNT(*) FROM pages");
				$count = (int) $query->fetch(\PDO::FETCH_COLUMN);
				$query->closeCursor();
				return (int) $count;
			} else {
				// no selector string, but options specified
				$selector = "id>0";
			}
		}
		$options['loadPages'] = false;
		$options['getTotal'] = true;
		$options['caller'] = 'pages.count';
		$options['returnVerbose'] = false;
		//if($this->wire('config')->debug) $options['getTotalType'] = 'count'; // test count method when in debug mode
		if(is_string($selector)) {
			$selector .= ", limit=1";
		} else if(is_array($selector)) {
			$selector['limit'] = 1;
		} else if($selector instanceof Selectors) {
			$selector->add(new SelectorEqual('limit', 1));
		}
		return $this->pages->find($selector, $options)->getTotal();
	}


	/**
	 * Preload/Prefetch fields for page together as a group (experimental)
	 * 
	 * This is an optimization that enables you to load the values for multiple fields into
	 * a page at once, and often in a single query. This is similar to the `joinFields` option
	 * when loading a page, or the `autojoin` option configured with a field, except that it 
	 * can be used after a page is already loaded. It provides a performance improvement
	 * relative lazy-loading of fields individually as they are accessed. 
	 * 
	 * Preload works only with Fieldtypes that do not override the core’s loading methods. 
	 * Preload also does not work with FieldtypeMulti types at present, except for the Page
	 * Fieldtype when configured to load a single page. Though it can be enabled for testing 
	 * purposes using the `useFieldtypeMulti` $options argument. 
	 * 
	 * NOTE: This function is currently experimental, recommended for testing only.
	 * 
	 * #pw-group-preload
	 * 
	 * @param Page $page Page to preload fields for
	 * @param array $fieldNames Names of fields to preload
	 * @param array $options 
	 *  - `debug` (bool): Specify true to include additional debug info in return value (default=false). 
	 *  - `useFieldtypeMulti` (bool): Enable FieldtypeMulti for testing purposes (default=false).
	 *  - `loadPageRefs` (bool): Optimization to early load pages in page reference fields? (default=true)
	 * @return array Array containing what was loaded and skipped
	 * @since 3.0.243
	 * 
	 */
	public function preloadFields(Page $page, array $fieldNames, $options = array()) {
		
		$defaults = [
			'debug' => is_bool($options) ? $options : false, 
			'useFieldtypeMulti' => false, 
			'loadPageRefs' => true, 
		];
		
		static $level = 0;

		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$debug = $options['debug'];
		$database = $this->wire()->database;
		$fieldNames = array_unique($fieldNames);
		$fields = $page->wire()->fields;
		$loadFields = [];
		$loadedFields = [];
		$selects = [];
		$joins = [];
		$numJoins = 0;
		$maxJoins = 60;
		
		$log = [
			'loaded' => [], 
			'skipped' => [], 
			'blank' => [], 
			'queries' => 1, 
		];
		
		if(!$page->id || !$page->template) return $log;
	
		foreach($fieldNames as $fieldKey => $fieldName) {

			// identify which fields to load and which to skip
			$field = $fields->get($fieldName);
			$fieldName = $field ? $field->name : '';
			$fieldNames[$fieldKey] = $fieldName; 
			$error = $field ? $this->skipPreloadField($page, $field, $options) : 'Field not found';

			if($error) {
				unset($fieldNames[$fieldKey]);
				if($fieldName) $log['skipped'][] = "$fieldName ($error)";
				continue;
			}

			$fieldtype = $field->type;
			$schema = $fieldtype->trimDatabaseSchema($fieldtype->getDatabaseSchema($field));
			$numJoins += count($schema);
			
			if($numJoins >= $maxJoins) break;
			
			$loadFields[$fieldName] = $field;
			$table = $field->getTable();
		
			// build selects and joins
			foreach(array_keys($schema) as $colName) {
				if($options['useFieldtypeMulti'] && $fieldtype instanceof FieldtypeMulti) {
					$sep = FieldtypeMulti::multiValueSeparator;
					$orderBy = "ORDER BY $table.sort";
					$selects[] = "GROUP_CONCAT($table.$colName $orderBy SEPARATOR '$sep') AS `{$table}__$colName`";
				} else {
					$selects[] = "$table.$colName AS {$table}__$colName";
				}
				$joins[$table] = "LEFT JOIN $table ON $table.pages_id=pages.id";
			}
			
			unset($fieldNames[$fieldKey]);
		}
		
		if(!count($selects)) return $log;

		$trackChanges = $level ? null : $page->trackChanges();
		if($trackChanges) $page->setTrackChanges(false);
		
		$level++;
		$timer = $debug ? Debug::timer() : false;

		// build and execute the query
		$sql = 
			'SELECT ' . implode(",\n", $selects) . ' ' .
			"\nFROM pages " .
			"\n" . implode(" \n", $joins) . ' ' .
			"\nWHERE pages.id=:pid";
	
		$query = $database->prepare($sql);
		$query->bindValue(':pid', $page->id, \PDO::PARAM_INT);
		$query->execute();
		
		$data = [];
		$row = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		
		// combine data from DB into column groups by field name
		if($row) {
			foreach($row as $key => $value) {
				list($table, $colName) = explode('__', $key, 2);
				list(, $fieldName) = explode('_', $table, 2);
				if(!isset($data[$fieldName])) $data[$fieldName] = [];
				$data[$fieldName][$colName] = $value;
			}
		}

		// wake up loaded values and populate to $page
		$pageIds = [];
		
		foreach($data as $fieldName => $sleepValue) {
			if(!isset($loadFields[$fieldName])) {
				unset($data[$fieldName]);
				continue;
			}
			$field = $loadFields[$fieldName];
			$fieldtype = $field->type;
			$cols = array_keys($sleepValue);
			if(count($cols) === 1 && array_key_exists('data', $sleepValue)) {
				$sleepValue = $sleepValue['data'];
			}	
			if($sleepValue === null) {
				unset($data[$fieldName]); 
				continue; // force to getBlankValue in loop below this
			}
			if($options['useFieldtypeMulti'] && $fieldtype instanceof FieldtypeMulti) { 
				if(strrpos($sleepValue, FieldtypeMulti::multiValueSeparator)) {
					$sleepValue = explode(FieldtypeMulti::multiValueSeparator, $sleepValue);
				}
			}
			if($fieldtype instanceof FieldtypePage && $sleepValue && $options['loadPageRefs']) {
				if(!is_array($sleepValue)) $sleepValue = [ $sleepValue ];
				foreach($sleepValue as $pageId) {
					$pageId = (int) $pageId;
					if(!$pageId) continue;
					if($this->pages->cacher()->hasCache($pageId)) continue;
					$parentId = $field->get('parent_id');
					$templateId = FieldtypePage::getTemplateIDs($field, true);
					if(!ctype_digit("$parentId")) $parentId = 0;
					if(!ctype_digit("$templateId")) $templateId = 0;
					$groupKey = "$parentId,$templateId";
					if(!isset($pageIds[$groupKey])) $pageIds[$groupKey] = [];
					$pageIds[$groupKey][$pageId] = $pageId; 
				}
			}
			
			$data[$fieldName] = $sleepValue;
		}
	
		// preload all pages in template or parent groups
		if(count($pageIds)) {
			foreach($pageIds as $groupKey => $ids) {
				list($parentId, $templateId) = explode(',', $groupKey);
				$this->pages->getByID($ids, [ 'template' => $templateId, 'parent_id' => $parentId ]); 
			}
		}
		
		foreach($data as $fieldName => $sleepValue) {
			$field = $loadFields[$fieldName];
			$fieldtype = $field->type;
			$value = $fieldtype->wakeupValue($page, $field, $sleepValue);
			$page->_parentSet($field->name, $value);
			$loadedFields[$field->name] = $fieldName;
			unset($loadFields[$field->name]);
			$log['loaded'][] = $fieldName;
		}
	
		// any remaining loadFields not present in DB should get blank value
		foreach($loadFields as $field) {
			$value = $field->type->getBlankValue($page, $field); 
			$fieldName = $field->name;
			$page->_parentSet($fieldName, $value);
			$log['blank'][] = $fieldName;
		}
	
		// go recursive for any remaining fields
		if(count($fieldNames)) {
			$result = $this->preloadFields($page, $fieldNames, $options);
			foreach($log as $key => $value) {
				if(is_array($value)) {
					$log[$key] = array_merge($value, $result[$key]);
				} else if(is_int($value)) {
					$log[$key] += $result[$key];
				}
			}
		}
	
		$level--;
		
		if($debug && $timer && !$level) $log['timer'] = Debug::timer($timer);
		
		if($trackChanges) $page->setTrackChanges($trackChanges);
		
		return $log;
	}

	/**
	 * Preload all supported fields for given page (experimental)
	 * 
	 * NOTE: This function is currently experimental, recommended for testing only.
	 * 
	 * #pw-group-preload
	 * 
	 * @param Page $page Page to preload fields for
	 * @param array $options 
	 *  - `debug` (bool): Specify true to return array of debug info (default=false).
	 *  - `skipFieldNames` (array): Optional names of fields to skip over (default=[]). 
	 *  - See the `PagesLoader::preloadFields()` method for additional options. 
	 * @return array Array of details 
	 * @since 3.0.243
	 * 
	 */
	public function preloadAllFields(Page $page, $options = array()) {
		$fieldNames = [];
		$skipFieldNames = isset($options['skipFieldNames']) ? $options['skipFieldNames'] : false;
		foreach($page->template->fieldgroup as $field) {
			if($skipFieldNames && in_array($field->name, $skipFieldNames)) continue;
			$fieldNames[] = $field->name;
		}
		return $this->preloadFields($page, $fieldNames, $options);
	}

	/**
	 * Skip preloading of this field or fieldtype?
	 * 
	 * Returns populated string with reason if yes, or blank string if no. 
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function skipPreloadField(Page $page, Field $field, array $options) {
		
		static $fieldtypeErrors = [];

		$useFieldtypeMulti = isset($options['useFieldtypeMulti']) ? $options['useFieldtypeMulti'] : false;
		$error = '';

		if($page->_parentGet($field->name) !== null) {
			$error = 'Already loaded';
		} else if(!$page->template->fieldgroup->hasField($field)) {
			$error = "Template '$page->template' does not have field";
		} else if(!$field->getTable()) {
			$error = 'Field has no table';
		}
		
		if($error) return $error;

		$fieldtype = $field->type;
		$shortName = $fieldtype->shortName;
		$cacheName = $shortName;
		
		if($fieldtype instanceof FieldtypePage) {
			$cacheName .= $field->get('derefAsPage');
		}
		
		if(isset($fieldtypeErrors[$cacheName])) {
			return $fieldtypeErrors[$cacheName];
		}
		
		// fieldtype status not yet known
		$schema = $fieldtype->getDatabaseSchema($field);
		$xtra = isset($schema['xtra']) ? $schema['xtra'] : [];

		if($fieldtype instanceof FieldtypeMulti) {
			if($useFieldtypeMulti) {
				// allow group_concat for FieldtypeMulti
			} else if($fieldtype instanceof FieldtypePage && $field->get('derefAsPage') > 0) {
				// allow single-page matches
			} else {
				$error = "$shortName: Unsupported without useFieldtypeMulti=true";
			}
		} else if($fieldtype instanceof FieldtypeFieldsetOpen) {
			$error = 'Fieldset: Unsupported';
		}

		if(!$error && isset($xtra['all']) && $xtra['all'] === false) {
			if($shortName !== 'Repeater' && $shortName !== 'RepeaterMatrix') {
				$error = "$shortName: External storage";
			}
		}
		
		if(!$error) {
			$ref = new \ReflectionClass($fieldtype);
			// identify parent class that implements loadPageField method
			$info = $ref->getMethod('___loadPageField'); 
			$class = wireClassName($info->class); 
			// whitelist of classes with custom loadPageField methods we support
			$rootClasses = [ 
				'Fieldtype', 
				'FieldtypeMulti', 
				'FieldtypeTextarea', 
				'FieldtypeTextareaLanguage' 
			];
			if(!in_array($class, $rootClasses)) {
				$error = "$shortName: Has custom loader";
			}
		}

		$fieldtypeErrors[$cacheName] = $error;
		
		return $error;
	}

	/**
	 * Remove pages from already-loaded PageArray aren't visible or accessible
	 *
	 * @param PageArray $items
	 * @param string $includeMode Optional inclusion mode:
	 * 	- 'hidden': Allow pages with 'hidden' status'
	 * 	- 'unpublished': Allow pages with 'unpublished' or 'hidden' status
	 * 	- 'all': Allow all pages (not much point in calling this method)
	 * @param array $options loadOptions
	 * @return PageArray
	 *
	 */
	protected function filterListable(PageArray $items, $includeMode = '', array $options = array()) {
		if($includeMode === 'all') return $items;
		$itemsAllowed = $this->pages->newPageArray($options);
		foreach($items as $item) {
			if($includeMode === 'unpublished') {
				$allow = $item->status < Page::statusTrash;
			} else if($includeMode === 'hidden') {
				$allow = $item->status < Page::statusUnpublished;
			} else {
				$allow = $item->status < Page::statusHidden;
			}
			if($allow) $allow = $item->listable(); // confirm access
			if($allow) $itemsAllowed->add($item);
		}
		$itemsAllowed->resetTrackChanges(true);
		return $itemsAllowed;
	}

	/**
	 * Returns an array of all columns native to the pages table
	 * 
	 * #pw-group-native
	 * 
	 * @return array of column names, also indexed by column name
	 * 
	 */
	public function getNativeColumns() {
		if(empty($this->nativeColumns)) {
			$query = $this->wire()->database->prepare("SELECT * FROM pages WHERE id=:id");
			$query->bindValue(':id', $this->wire()->config->rootPageID, \PDO::PARAM_INT);
			$query->execute();
			$row = $query->fetch(\PDO::FETCH_ASSOC);
			foreach(array_keys($row) as $colName) {
				$this->nativeColumns[$colName] = $colName;
			}
			$query->closeCursor();
		}
		return $this->nativeColumns;	
	}

	/**
	 * Get value of of a native column in pages table for given page ID
	 * 
	 * #pw-group-retrieve
	 * #pw-group-native
	 *
	 * @param int|Page $id Page ID
	 * @param string $column
	 * @return int|string|bool Returns int/string value on success or boolean false if no matching row
	 * @since 3.0.156
	 * @throws \PDOException|WireException
	 *
	 */
	public function getNativeColumnValue($id, $column) {
		$id = (is_object($id) ? (int) "$id" : (int) $id);
		if($id < 1) return false;
		$database = $this->wire()->database;
		if($database->escapeCol($column) !== $column) throw new WireException("Invalid column name: $column");
		$query = $database->prepare("SELECT `$column` FROM pages WHERE id=:id");
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		$query->execute();
		$value = $query->fetchColumn();
		$query->closeCursor();
		if(ctype_digit("$value") && strpos($column, 'name') !== 0) $value = (int) $value;
		return $value;
	}

	/**
	 * Is the given column name native to the pages table?
	 * 
	 * #pw-group-native
	 * 
	 * @param $columnName
	 * @return bool
	 * 
	 */
	public function isNativeColumn($columnName) {
		$nativeColumns = $this->getNativeColumns();
		return isset($nativeColumns[$columnName]);
	}

	/**
	 * Get or set debug state
	 * 
	 * #pw-group-debug
	 * 
	 * @param bool|null $debug
	 * @return bool
	 * 
	 */
	public function debug($debug = null) {
		$value = $this->debug;
		if(!is_null($debug)) $this->debug = (bool) $debug;
		return $value;
	}

	/**
	 * Return the total quantity of pages loaded by getById()
	 * 
	 * #pw-group-info
	 * 
	 * @return int
	 * 
	 */
	public function getTotalPagesLoaded() {
		return $this->totalPagesLoaded;
	}

	/**
	 * Get last used instance of PageFinder (for debugging purposes)
	 * 
	 * #pw-group-debug
	 * 
	 * @return PageFinder|null
	 * @since 3.0.146
	 * 
	 */
	public function getLastPageFinder() {
		return $this->lastPageFinder;
	}

	/**
	 * Are we currently loading pages?
	 * 
	 * #pw-group-info
	 * 
	 * @return bool
	 * @since 3.0.195
	 * 
	 * 
	 */
	public function isLoading() {
		return $this->loading;
	}
	
}
