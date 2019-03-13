<?php namespace ProcessWire;

/**
 * ProcessPageSearch: Live Search (for PW admin)
 * 
 * @method renderList(array $items, $prefix = 'pw-search', $class = 'list')
 * @method renderItem(array $item, $prefix = 'pw-search', $class = 'list')
 * @method string|array execute($getJSON = true)
 * 
 * @todo support searching repeaters
 * 
 */

class ProcessPageSearchLive extends Wire {

	/**
	 * Reference to ProcessPageSearch, if available
	 * 
	 * @var Process|ProcessPageSearch
	 * 
	 */
	protected $process;

	/**
	 * Properties to skip in selectors
	 * 
	 * @var array
	 * 
	 */
	protected $skipProperties = array(
		'include',
		'check_access',
		'checkaccess',
	);

	/**
	 * Template for live search settings
	 * 
	 * @var array
	 * 
	 */
	protected $liveSearchDefaults = array(
		'type' => '', // type of search, if not pages, i.e. "templates", "fields", "modules", "comments", etc. 
		'property' => '', // property to search for within type, or blank if no specific property
		'operator' => '%=',
		'q' => '', // query text to find
		'selectors' => array(),
		'template' => null, 
		'multilang' => true,
		'language' => '', // language name
		'edit' => true,
		'start' => 0, 
		'limit' => 15,
		'verbose' => false, 
		'debug' => false,
		'help' => false,
	);

	/**
	 * Template for individual live search result items
	 * 
	 * @var array
	 * 
	 */
	protected $itemTemplate = array(
		'id' => 0,
		'url' => '', // required
		'name' => '',
		'title' => '', // required
		'subtitle' => '',
		'summary' => '',
		'icon' => '',
		'group' => '',
		'status' => 0, 
		'modified' => 0, 
	);

	/**
	 * Allowed operators
	 * 
	 * @var array
	 * 
	 */
	protected $allowOperators = array(
		'=', '==', '!=', '*=', '~=', '%=', '^=', '$=', '<=', '>=', '<', '>'
	);

	/**
	 * Operator to use for single-word matches (if not overridden)
	 * 
	 * @var string
	 * 
	 */
	protected $singleWordOperator = '%=';

	/**
	 * Operator to use for multi-word matches (if not overridden)
	 * 
	 * @var string
	 * 
	 */
	protected $multiWordOperator = '~=';

	/**
	 * Default fields to search for pages
	 * 
	 * @var array
	 * 
	 */
	protected $defaultPageSearchFields = array('title');

	/**
	 * Are we currently in “view all” mode?
	 * 
	 * @var bool
	 * 
	 */
	protected $isViewAll = false;

	/**
	 * Order to render results in, by search type
	 * 
	 * @var array
	 * 
	 */
	protected $searchTypesOrder = array();

	/**
	 * Search types that are specifically excluded
	 * 
	 * @var array
	 * 
	 */
	protected $noSearchTypes = array();

	/**
	 * PaginatedArray to use for pagination, when applicable for “view all” mode
	 * 
	 * @var null|PaginatedArray
	 * 
	 */
	protected $pagination = null;

	/**
	 * Shared translation labels, defined in constructor
	 * 
	 * @var array
	 * 
	 */
	protected $labels = array();

	/**
	 * Construct
	 *
	 * @param Process|ProcessPageSearch $process
	 * @param array $liveSearch
	 * 
	 */
	public function __construct(Process $process = null, array $liveSearch = array()) {
		
		if($process) {
			$process->wire($this);
			if($process instanceof ProcessPageSearch) $this->process = $process;
			$a = explode(' ', $process->searchFields2);
			if(count($a)) $this->defaultPageSearchFields = $a;
		}
		
		if(!empty($liveSearch)) {
			$this->liveSearchDefaults = array_merge($this->liveSearchDefaults, $liveSearch);
		}
		
		$this->labels = array(
			'missing-query' => $this->_('No search specified'),
			'pages' => $this->_('Pages'),
			'trash' => $this->_('Trash'),
			'modules' => $this->_('Modules'), 
			'view-all' => $this->_('View All'),
			'search-results' => $this->_('Search Results'),
		);
		
		parent::__construct();
	}

	/**
	 * Set order of search types
	 * 
	 * @param array $types Names of types, in order
	 * 
	 */
	public function setSearchTypesOrder(array $types) {
		$this->searchTypesOrder = $types; 
	}

	/**
	 * Set types that should be excluded unless specifically asked for
	 * 
	 * @param array $types Names of types to exclude
	 * 
	 */
	public function setNoSearchTypes(array $types) {
		$this->noSearchTypes = $types;
	}

	/**
	 * Set default operators to use for searches (if query does not specify operator)
	 * 
	 * @param string $singleWordOperator
	 * @param string $multiWordOperator
	 * 
	 */
	public function setDefaultOperators($singleWordOperator, $multiWordOperator = '') {
		$this->singleWordOperator = $singleWordOperator;
		$this->multiWordOperator = empty($multiWordOperator) ? $singleWordOperator : $multiWordOperator;
	}
	
	/**
	 * Initialize live search
	 *
	 * @param array $presets Additional info to populate in liveSearchInfo
	 * @return array Current liveSearchInfo
	 *
	 */
	protected function init(array $presets = array()) {

		/** @var WireInput $input */
		$input = $this->wire('input');
		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		/** @var Fields $fields */
		$fields = $this->wire('fields');
		/** @var Templates $templates */
		$templates = $this->wire('templates');
		/** @var User $user */
		$user = $this->wire('user');
		/** @var Languages $languages */
		$languages = $this->wire('languages');

		$type = isset($presets['type']) ? $presets['type'] : '';
		$language = isset($presets['language']) ? $presets['language'] : '';
		$property = isset($presets['property']) ? $presets['property'] : '';
		$operator = isset($presets['operator']) ? $presets['operator'] : '';
		$template = isset($presets['template']) ? $presets['template'] : '';
		$limit = isset($presets['limit']) ? (int) $presets['limit'] : $this->liveSearchDefaults['limit'];
		$start = isset($presets['start']) ? (int) $presets['start'] : ($input->pageNum() - 1) * $limit;
		$selectors = array();
		$replaceOperator = '';
		$opHolders = array('<=' => '~@LT=', '>=' => '~@GT=', '<' => '~@LT', '>' => '~@GT');  // operator placeholders
		
		$q = empty($presets['q']) ? $input->get('q') : $presets['q'];
		if(empty($q)) $q = $input->get('admin_search'); // legacy name
		if(strpos($q, '~@') !== false) $q = str_replace('~@', '', $q); // disallow placeholder prefix
		if(empty($operator)) $q = str_replace(array_keys($opHolders), array_values($opHolders), $q);
		$q = $sanitizer->text($q, array('reduceSpace' => true));
		
		if($user->isSuperuser() && strpos($q, 'DEBUG') !== false) {
			$q = str_replace('DEBUG', '', $q);
			$presets['debug'] = true;
		}
		
		if(empty($q)) {
			// if no query, we've got nothing to do
			return $this->liveSearchDefaults;
		}

		if(empty($operator)) {
			// operator may be bundled into query: $q
			if(strpos($q, '~@') !== false) {
				foreach($opHolders as $op => $placeholder) { // <=, >=, <, >
					if(strpos($q, $placeholder) === false) continue;
					$replaceOperator = $placeholder;
					$operator = $op;
					break;
				}
				
			} else if(strpos($q, '==') !== false) {
				// forced equals operator
				$replaceOperator = '==';
				$operator = '=';

			} else if(strpos($q, '=') !== false) {
				// regular equals or other w/equals
				$replaceOperator = '=';
				if(preg_match('/([%~*^$<>!]{1,2}=)/', $q, $matches)) {
					if(in_array($matches[1], $this->allowOperators)) {
						$operator = $matches[1];
						$replaceOperator = $operator;
					}
				} else {
					// regular equals, use default operator	
				}
			}
			
			if($replaceOperator) {
				$q = str_replace($replaceOperator, ':', $q);
			}
		}
		
		if(empty($operator) || !in_array($operator, $this->allowOperators)) {
			$operator = strpos($q, ' ') ? $this->multiWordOperator : $this->singleWordOperator;
		}

		// check if type and property may be part of query: $q
		if(empty($type) && empty($property) && strpos($q, ':')) {
			// Search specifies a specific type "type:text", i.e. "users:ryan"
			list($type, $q) = explode(':', $q, 2);
			// live search type: pages, users, modules, fields, templates, comments, etc.
			$type = $sanitizer->name($type);
			if(strpos($type, '.') !== false) {
				// live search type includes a property, i.e. "pages.body", "users.first_name", etc. 
				list($type, $property) = explode('.', $type, 2);
			}
			if($type === 'pages') {
				// ok
			} else if($type) {
				// check if type refers to a template name or language
				$template = true;
				$language = true;
			} else {
				// search all types
			}
		} else if($type) {
			$template = true;
			$language = true;
		}
		
		if($language === true) {
			// check if type refers to a language
			$language = $languages ? $languages->get($type) : null;
			if($language && $language->id) {
				$language = $language->name;
				$template = null;
				$type = '';
			} else {
				$language = '';
			}
		}
		
		if($template === true) {
			// check if type refers to template name or language
			$template = $templates->get($type);
		}
		
		if($template && $template instanceof Template) {
			// does search type match the name of a template?
			$selectors[] = "template=$template->name";
			$type = '';
			// $type = 'pages';
		}
		
		$type = $sanitizer->name($type);
		$property = $sanitizer->fieldName($property);
		$q = trim($q);
		$value = $sanitizer->selectorValue($q);
		$lp = strtolower($property);

		if($property && ($fields->isNative($property) || $fields->get($property)) && !in_array($lp, $this->skipProperties)) {
			// we recognize this property as searchable, so add it to the selector
			if($lp == 'status' && !$user->isSuperuser() && $value > Page::statusHidden) $value = Page::statusHidden;
			$selectors[] = $property . $operator . $value;
		} else {
			// we did not recognize the property, so use field(s) defined in module instead
			$selectors[] = implode('|', $this->defaultPageSearchFields) . $operator . $value;
		}

		$liveSearch = array_merge($this->liveSearchDefaults, $presets, array(
			'type' => $type,
			'property' => $property,
			'operator' => $operator,
			'q' => $q,
			'selectors' => $selectors,
			'template' => $template,
			'multilang' => $this->wire('languages') ? true : false,
			'language' => $language, 
			'start' => $start, 
			'limit' => $limit,
			'help' => strtolower($q) === 'help',
		));
		
		if($this->isViewAll) {
			// variables for pagination
			$input->whitelist('type', $type);
			$input->whitelist('property', $property);
			$input->whitelist('operator', $operator);
			$input->whitelist('q', $q);
			if(!empty($liveSearch['language'])) $input->whitelist('language', $liveSearch['language']); 
		}

		return $liveSearch;
	}

	/**
	 * Execute live search and return JSON result
	 * 
	 * @param bool $getJSON Get results as JSON string? Specify false to get array instead.
	 * @return string|array
	 * 
	 */
	public function ___execute($getJSON = true) {

		/** @var WireInput $input */
		$input = $this->wire('input');

		$liveSearch = $this->init();

		if((int) $input->get('version') > 1) {
			// version 2+ keep results in native format, for future use
			$items = $this->find($liveSearch);
		} else {
			// version 1 is currently used by PW admin themes
			$items = $this->convertItemsFormat($this->find($liveSearch));
		}
		
		$result = array(
			'matches' => &$items
		);

		return $getJSON ? json_encode($result) : $items;
	}

	/**
	 * Render output for landing page to view all items of a particular type
	 * 
	 * Expects these GET vars to be present: 
	 *  - type
	 *  - operator
	 *  - property
	 *  - q
	 * 
	 * @return string
	 * @throws WireException
	 * 
	 */
	public function executeViewAll() {
	
		/** @var WireInput $input */
		$input = $this->wire('input');
		$this->isViewAll = true;
		
		$type = $input->get->pageName('type');
		$operator = $input->get('operator');
		$property = $input->get->fieldName('property');
		$language = $input->get->pageName('language');
		$q = $input->get->text('q');
		$this->pagination = new PaginatedArray();
		$this->wire($this->pagination);
		
		if(empty($q)) {
			$this->error($this->labels['missing-query']);
			return '';
		}
		
		if(false && ($type == 'pages' || $type == 'trash')) {
			// let Lister handle it
			$results = array();
		} else {
			$liveSearch = $this->init(array(
				'type' => $type,
				'property' => $property,
				'operator' => $operator,
				'q' => $q,
				'limit' => $this->liveSearchDefaults['limit'],
				'verbose' => true,
				'language' => $language, 
			));
			$results = $this->find($liveSearch);
		}
		
		if($this->process) {
			if($type) {
				$this->process->headline($this->pagination->getPaginationString(array(
					'label' => $this->labels['search-results'] . " - " . ucfirst($type),
					'count' => count($results)
				)));
			} else {
				$this->process->headline($this->labels['search-results']); 
			}
		}
	
		$out = $this->renderList($results); 
		
		return $out; 
	}
	
	/**
	 * Perform find of types, pages, modules
	 * 
	 * Result format that this find method expects from modules it calls the search() method from:
	 *
	 * $result = array(
	 *   'title' => 'Title of these items, used as the group label except where overridden item "group" property',
	 *   'url' => 'URL to view all items', // if omitted, one will be provided automatically
	 *   'total' => 999, // non-paginated total quantity (can be omitted if pagination not supported)
	 *   'items' => [
	 *     [
	 *      // required properties
	 *     'title' => 'Title of item',
	 *     'url' => 'URL to view or edit the item',
	 *      // optional properties:
	 *     'id' => 0, 
	 *     'name' => 'Name of item', 
	 *     'icon' => 'Optional icon name to represent the item, i.e. "gear" or "fa-gear"', 
	 *     'group' => 'Optionally group with other items having this group name, overrides $result[title]', 
	 *     'status' => int, // if item is a Page, status of page using Page::status* constants 
	 *     'summary' => 'Summary or description of item or excerpt of text that matched', // (recommended)
	 *     'subtitle' => 'Secondary title of item', // (recommended)
	 *     'modified' => int, // last modified date of item 
	 *     ], 
	 *     [ ... ], [ ... ], etc. 
	 *   )
	 * );
	 *
	 * @param array $liveSearch
	 * @return array Array of matches
	 *
	 */
	protected function find(array &$liveSearch) {

		$items = array();
		$user = $this->wire('user');
		$userLanguage = null;
		$q = $liveSearch['q'];
		$type = $liveSearch['type'];
		$foundTypes = array();
		$modulesInfo = array();
		$help = $liveSearch['help'];
		
		/** @var Modules $modules */
		$modules = $this->wire('modules');
		
		/** @var Languages $languages */
		$languages = $this->wire('languages');
		
		if($languages && $liveSearch['language']) {
			// change current user to have requested language, temporarily
			$language = $languages->get($liveSearch['language']);
			if($language && $language->id) {
				$userLanguage = $user->language;	
				$user->language = $language;
			}
		}
		
		if($type != 'pages' && $type != 'trash') {
			$modulesInfo = $modules->getModuleInfo('*', array('verbose' => true));
		}
		
		foreach($modulesInfo as $info) {

			if(empty($info['searchable'])) continue;
			$name = $info['name'];
			$thisType = $info['searchable'];
	
			if(!$this->useType($thisType, $type)) continue;
			if($type && $this->isViewAll && $type != $thisType) continue;
			if($type && stripos($thisType, $type) === false) continue;
			if(!empty($liveSearch['template']) && !empty($liveSearch['property'])) continue;
			if(!$user->isSuperuser() && !$modules->hasPermission($name, $user)) continue;
		
			$foundTypes[] = $thisType;
			$module = null;
			$result = array();
			$timer = null;
			
			try {
				/** @var SearchableModule $module */
				$module = $modules->getModule($name, array('noInit' => true));
				if(!$module) continue;
				$result = $module->search($q, $liveSearch); // see method phpdoc for $result format
				
			} catch(\Exception $e) {
				// ok
			}
			
			if(!$module || (empty($result['items']) && empty($liveSearch['help']))) continue;
			if(empty($result['total'])) $result['total'] = count($result['items']);
		
			if(!in_array($thisType, $this->searchTypesOrder)) $this->searchTypesOrder[] = $thisType;
			$order = array_search($thisType, $this->searchTypesOrder);
			$order = $order * 100;
			
			$title = empty($result['title']) ? "$info[title]" : "$result[title]";
			$n = $liveSearch['start'];
			$item = null;
			
			if($help) {
				foreach($result['items'] as $key => $item) {
					if($item['name'] != 'help') unset($result['items'][$key]); 
				}
				$result['items'] = array_merge($this->makeHelpItems($result, $thisType), $result['items']); 
			}
			
			foreach($result['items'] as $item) {
				$n++;
				$item = array_merge($this->itemTemplate, $item);
				$item['group'] = empty($item['group']) ? "$title" : "$item[group]";
				if(empty($item['group'])) $item['group'] = $title;
				$item['n'] = "$n/$result[total]";
				$items[$order] = $item;
				$order++;
			}
			
			//if($n && $n < $result['total'] && !$this->isViewAll && !$help) {
			if($n && $n < $result['total'] && !$help) {
				$url = isset($result['url']) ? $result['url'] : '';
				$items[$order] = $this->makeViewAllItem($liveSearch, $thisType, $item['group'], $result['total'], $url); 
			}
			
			if($this->isViewAll && $this->pagination && $type && !$help) {
				$this->pagination->setTotal($result['total']); 
				$this->pagination->setLimit($liveSearch['limit']);
				$this->pagination->setStart($liveSearch['start']); 
			}
		}
		
		if($type && !$help && !count($foundTypes) && !in_array($type, array('pages', 'trash', 'modules'))) {
			if(empty($liveSearch['template']) && !count($foundTypes)) {
				// if no types matched, and it’s going to skip pages, assume type is a property, and do a pages search
				$liveSearch = $this->init(array(
					'q' => $liveSearch['q'], 
					'type' => 'pages', 
					'property' => $type, 
					'operator' => $liveSearch['operator']
				));
				$type = 'pages';
			}
		}
		
		if(empty($type) || $type === 'pages' || $type === 'trash' || $liveSearch['template']) {
			// include pages in the search results
			if(!in_array('pages', $this->searchTypesOrder)) $this->searchTypesOrder[] = 'pages';
			$order = array_search('pages', $this->searchTypesOrder) * 100;
			foreach($this->findPages($liveSearch) as $item) {
				$items[$order++] = $item;
			}
		}

		// use built-in modules search when appropriate
		if($this->useType('modules', $type) && $this->wire('user')->isSuperuser()) {
			if(!in_array('modules', $this->searchTypesOrder)) $this->searchTypesOrder[] = 'modules';
			$order = array_search('modules', $this->searchTypesOrder) * 100;
			foreach($this->findModules($liveSearch, $modulesInfo) as $item) {
				$items[$order++] = $item;
			}
		}
		
		// add a debug item if requested to
		if(!empty($liveSearch['debug'])) {
			array_unshift($items, $this->makeDebugItem($liveSearch));
		}
		
		if($userLanguage) {
			// restore original language to user
			$user->language = $userLanguage;
		}
		
		ksort($items);
		
		if($help) $items = array_merge($this->makeHelpItems(array(), 'help'), $items); 

		return $items;
	}


	/**
	 * Find pages for live search
	 * 
	 * @param array $liveSearch
	 * @return array
	 * 
	 */
	protected function findPages(array &$liveSearch) {

		$user = $this->wire('user');
		$superuser = $user->isSuperuser();
		$pages = $this->wire('pages');
		$config = $this->wire('config');
		
		if(!empty($liveSearch['help'])) {
			$result = array('title' => 'pages', 'items' => array(), 'properties' => array('name', 'title'));
			if($this->wire('fields')->get('body')) $result['properties'][] = 'body';
			$result['properties'][] = $this->_('or any field name');
			return $this->makeHelpItems($result, 'pages');	
		}
		
		// a $pages->find() search will be included in the live search
		$selectors = &$liveSearch['selectors'];
		$selectors[] = "start=$liveSearch[start], limit=$liveSearch[limit]";

		if($this->process) {
			$repeaterID = $this->process->getRepeatersPageID();
			if($repeaterID) $selectors[] = "has_parent!=$repeaterID";
		}

		if($superuser) {
			// superuser only
			$selectors[] = "include=all";
		} else if($user->hasPermission('page-edit')) {
			// admin search mode and user has some kind of page-edit permission
			$selectors[] = "include=unpublished";
			// $selectors[] = "template=$editableTemplates";
			// $selectors[] = "status<" . Page::statusTrash;
		} else {
			// only show regular, non-hidden, non-unpublished pages
		}

		$selector = implode(', ', $selectors);
		if($this->process) $selector = $this->process->findReady($selector);
		
		$titles = array();
		$items = array();
		$matches = array('pages' => array(), 'trash' => array());
	
		try {
			if($this->useType('pages', $liveSearch['type'])) {
				$selector .= ', templates_id!=' . implode('|', $config->userTemplateIDs); // users are searched separately
				$items['pages'] = $pages->find("$selector, status<" . Page::statusTrash);
			}
		} catch(\Exception $e) {
		}
		try {
			if($superuser && $this->useType('trash', $liveSearch['type'])) {
				$items['trash'] = $pages->find("$selector, status>=" . Page::statusTrash);
			}
		} catch(\Exception $e) {
		}

		foreach($items as $type => $pageItems) {
			
			$n = $liveSearch['start'];
			$total = $pageItems->getTotal();
			$item = array();
			
			foreach($pageItems as $page) {
				/** @var Page $page */
				if(!$superuser && $page->isUnpublished() && !$page->editable()) continue;
				$isAdmin = $page->template == 'admin';
				$title = (string) $page->get('title|name');
				
				$item = array(
					'id' => $page->id,
					'name' => $page->name,
					'title' => $title,
					'subtitle' => $page->template->name,
					'summary' => $page->path,
					'url' => !$isAdmin && $page->editable() ? $page->editUrl(array('language' => true)) : $page->url(),
					'icon' => $page->getIcon(),
					'group' => '',
					'n' => (++$n) . '/' . $total,
					'modified' => $page->modified,
					'status' => $page->status,
				);
				
				if(!isset($titles[$title])) $titles[$title] = 0;
				$titles[$title]++;
				$item['group'] = $this->labels[$type];
				$matches[$type][] = $item;
			}
			
			if(!empty($item) && $total > count($matches[$type])) {
				$matches[$type][] = $this->makeViewAllItem($liveSearch, $type, $item['group'], $total, '');
			}
		}

		// merge all the matches together
		if(empty($matches['trash'])) {
			$matches = $matches['pages'];
		} else {
			$matches = array_merge($matches['pages'], $matches['trash']);
		}

		// if there any colliding titles, add modified date to the subtitle
		foreach($titles as $title => $qty) {
			if($qty < 2) continue;
			foreach($matches as $key => $item) {
				if($item['title'] !== $title) continue;
				$matches[$key]['subtitle'] .= " (" . wireRelativeTimeStr($item['modified'], true) . ")";
			}
		}

		return $matches;
	}

	/**
	 * Allow this search type?
	 * 
	 * @param string $type Type to check
	 * @param string $requestType Type specifically requested by user
	 * @return bool
	 * 
	 */
	protected function useType($type, $requestType = '') {
		if($requestType) return $type === $requestType; 
		return !in_array($type, $this->noSearchTypes); 
	}


	/**
	 * Find modules matching query
	 * 
	 * @param array $liveSearch
	 * @param array $modulesInfo
	 * @return array
	 * 
	 */
	protected function findModules(array &$liveSearch, array &$modulesInfo) {
		
		$q = $liveSearch['q'];
		$groupLabel = $this->labels['modules'];
		$items = array();
		$forceMatch = false;
		
		if(!empty($liveSearch['help'])) {
			$info = $this->wire('modules')->getModuleInfoVerbose('ProcessPageSearch');
			$properties = array();
			foreach(array_keys($info) as $property) {
				$value = $info[$property];
				if(!is_array($value)) $properties[$property] = $property;
			}
			$exclude = array('id', 'file', 'versionStr', 'core');
			foreach($exclude as $key) unset($properties[$key]); 
			$result = array(
				'title' => 'Modules',
				'items' => array(),
				'properties' => $properties
			);
			$items = $this->makeHelpItems($result, 'modules');
			return $items;
		}

		if($liveSearch['type'] === 'modules' && !empty($liveSearch['property'])) {
			// searching for custom module property
			$forceMatch = true;
			$infos = $this->wire('modules')->findByInfo(
				$liveSearch['property'] . $liveSearch['operator'] . 
				$this->wire('sanitizer')->selectorValue($q), 2
			);
		} else {
			// text-matching for all modules
			$infos = &$modulesInfo;
		}
			
		foreach($infos as $info) {
			$id = isset($info['id']) ? $info['id'] : 0;
			$name = $info['name'];
			$title = $info['title'];
			$summary = isset($info['summary']) ? $info['summary'] : '';
			if(!$forceMatch) {
				$searchText = "$name $title $summary";
				if(stripos($searchText, $q) === false) continue;
			}
			$item = array(
				'id' => $id,
				'name' => $name,
				'title' => $title,
				'subtitle' => $name,
				'summary' => $summary, 
				'url' => $this->wire('config')->urls->admin . "module/edit?name=$name",
				'group' => $groupLabel,
			);
			$item = array_merge($this->itemTemplate, $item);
			$items[] = $item;
		}
		
		$total = count($items); 
		$n = 0;
		foreach($items as $key => $item) {
			$n++;
			$items[$key]['n'] = "$n/$total";
		}
		
		return $items;
	}
	
	/**
	 * Convert items from native live search format (v2) to v1 format
	 * 
	 * v1 format is used by ProcessWire admin themes. 
	 * 
	 * @param array $items
	 * @return array
	 * 
	 */
	protected function convertItemsFormat(array $items) {
		
		$converted = array();
		$sanitizer = $this->wire('sanitizer');
		
		foreach($items as $item) {
			$a = array(
				'id' => $item['id'],
				'name' => (string) $item['name'],
				'title' => (string) $item['title'],
				'template_label' => (string) $item['subtitle'],
				'tip' => (string) $item['summary'],
				'editUrl' => (string) $item['url'],
				'type' => (string) $sanitizer->entities($item['group']),
				'icon' => isset($item['icon']) ? $item['icon'] : '',
			);
			
			if(!empty($item['status'])) {
				if($item['status'] & Page::statusUnpublished) $a['unpublished'] = true;	
				if($item['status'] & Page::statusHidden) $a['hidden'] = true;
				if($item['status'] & Page::statusLocked) $a['locked'] = true;
			}
			
			$converted[] = $a;
		}
	
		return $converted;
	}

	/**
	 * Make a search result item that displays debugging info
	 * 
	 * @param array $liveSearch
	 * @return array
	 * 
	 */
	protected function makeDebugItem($liveSearch) {
		$liveSearch['user_language'] = $this->wire('user')->language->name;
		$summary = print_r($liveSearch, true);
		return array_merge($this->itemTemplate, array(
			'id' => 0,
			'name' => 'debug',
			'title' => implode(', ', $liveSearch['selectors']),
			'subtitle' => $liveSearch['q'],
			'summary' => $summary,
			'url' => '#',
			'group' => 'Debug',
		));
	}
	
	/**
	 * Make a search result item that displays property info
	 *
	 * @param array $result Result array returned by a SearchableModule::search() method
	 * @param string $type
	 * @return array
	 *
	 */
	protected function makeHelpItems(array $result, $type) {
		
		$items = array();
		$helloLabel = $this->_('test');
		$usage1desc = $this->wire('sanitizer')->unentities($this->_('Searches %1$s for: %2$s')); 
		$usage2desc = $this->wire('sanitizer')->unentities($this->_('Searches “%1$s” property of %2$s for: %3$s')); 
		
		if($type === 'help') {
			$operators = ProcessPageSearch::getOperators();
			$summary = 
				$this->_('Examples use the “=” equals operator.') . " \n" . 
				$this->_('In some cases you can also use these:') . "\n";
			foreach($operators as $op => $label) {
				$summary .= "$op " . rtrim($label, '*') . "\n";
			}
			$items[] = array(
				'title' => $this->_('operators:'),
				'subtitle' => implode(', ', array_keys($operators)), 
				'summary' => $summary, 
				'group' => 'help',
				'url' => 'https://processwire.com/api/selectors/#operators'
			);
			if($this->wire('user')->isSuperuser() && $this->process) {
				$items[] = array(
					'title' => $this->_('configure'),
					'subtitle' => $this->_('Click here to configure search settings'),
					'url' => $url = $this->wire('modules')->getModuleEditUrl('ProcessPageSearch'),
					'group' => 'help',
				);
			}
			foreach($items as $key => $item) $items[$key] = array_merge($this->itemTemplate, $item); 
			return $items;
		}
		
		// include any items from result that had the name "help"
		foreach($result['items'] as $item) {
			if($item['name'] == 'help') $items[] = $item;
		}

		$items[] = array(
			'title' => "$type=$helloLabel",
			'subtitle' => sprintf($usage1desc, $type, $helloLabel),
		);
		
		if(!empty($result['properties'])) {
		
			if($type == 'pages' || $type == 'modules') {
				$property = 'title';
			} else if($type == 'fields' || $type == 'templates') {
				$property = 'label';
			} else {
				$property = reset($result['properties']);
			}
			
			$items[] = array(
				'title' => "$type.$property=$helloLabel",
				'subtitle' => sprintf($usage2desc, $property, $type, $helloLabel)
			);
			
			if($type === 'pages') {
				$items[] = array(
					'title' => "$property=$helloLabel", 
					'subtitle' => $this->_('Same as above (shorter syntax if no names collide)')
				);
				$templateName = 'basic-page';
				$items[] = array(
					'title' => "$templateName=$helloLabel",
					'subtitle' => sprintf($this->_('Limit results to template: %s'), $templateName),
				);
				$items[] = array(
					'title' => "$templateName.$property=$helloLabel",
					'subtitle' => sprintf($this->_('Limit results to %s field on template'), $property)
				);
				
			} else if($type === 'templates') {
				$fieldName = 'images';
				$items[] = array(
					'title' => "templates.fields=$fieldName",
					'subtitle' => sprintf($this->_('Find templates that have field: %s'), $fieldName)
				);
			} else if($type === 'fields') {
				$items[] = array(
					'title' => "fields.settings=ckeditor",
					'subtitle' => $this->_('Find fields with “ckeditor” in settings'),
				);
			}
			
			$properties = implode(', ', $result['properties']);

			if(strlen($properties) > 50) {
				$properties = $this->wire('sanitizer')->truncate($properties, 50) . ' ' . $this->_('(hover for more)');
			}

			$summary =
				sprintf($this->_('The examples use the “%s” property.'), $property) . "\n" .
				$this->_('You can also use any of these properties:') . "\n • " .
				implode("\n • ", $result['properties']);

			$items[] = array(
				'title' => $this->_('properties'),
				'subtitle' => $properties,
				'summary' => $summary
			);
		}
		
		$group = sprintf($this->_('%s help'), $type);
		
		foreach($items as $key => $item) {
			$item['name'] = 'help';
			$item['group'] = $group;
			$items[$key] = array_merge($this->itemTemplate, $item); 
		}
	
		return $items;
	}

	/**
	 * Make a search result item that displays a “view all” link
	 * 
	 * @param array $liveSearch
	 * @param string $type
	 * @param string $group
	 * @param int $total
	 * @param string $url If module provides its own view-all URL
	 * @return array
	 * 
	 */
	protected function makeViewAllItem(&$liveSearch, $type, $group, $total, $url = '') {
		
		if(!empty($url)) {
			// use provided url
		} else if($type == 'pages' || $type == 'trash' || !empty($liveSearch['template'])) {
			$url = $this->wire('page')->url();
			$url .= "?q=" . urlencode($liveSearch['q']) . "&live=1";
			if($type == 'trash') $url .= "&trash=1";
			if(!empty($liveSearch['template'])) {
				$url .= "&template=" . $liveSearch['template']->name;
			}
			if(!empty($liveSearch['property'])) {
				$url .= "&field=" . urlencode($liveSearch['property']);
			}
			if(!empty($liveSearch['operator'])) {
				$url .= "&operator=" . urlencode($liveSearch['operator']);
			}
		} else {
			$url = $this->wire('page')->url() . 'live/' . 
				'?q=' . urlencode($liveSearch['q']) .
				'&type=' . urlencode($type) .
				'&property=' . urlencode($liveSearch['property']) .
				'&operator=' . urlencode($liveSearch['operator']);
		}
		
		return array_merge($this->itemTemplate, array(
			'id' => 0,
			'name' => 'view-all',
			'title' => $this->labels['view-all'],
			'subtitle' => sprintf($this->_('%d items'), $total),
			'summary' => '',
			'url' => $url,
			'group' => $group,
		));
	}

	/**
	 * Render “view all” list
	 * 
	 * @param array $items
	 * @param string $prefix For CSS classes, default is "pw-search"
	 * @param string $class Class name for list, default is "list" which translates to "pw-search-list"
	 * @return string HTML markup
	 * 
	 */
	protected function ___renderList(array $items, $prefix = 'pw-search', $class = 'list') {

		$pagination = $this->pagination->renderPager();
		$group = '';
		$groups = array();
		$totals = array();
		$icon = wireIconMarkup('angle-right');
	
		foreach($items as $item) {
			if($item['group'] != $group) {
				$group = $item['group'];
				$groups[$group] = ''; 
			}
			if(empty($totals[$group]) && isset($item['n'])) {
				list(, $total) = explode('/', $item['n']);
				$totals[$group] = (int) $total;
			}
			if($item['name'] === 'view-all') {
				if($pagination) continue;
				$groupLabel = $this->wire('sanitizer')->entities($group);
				$groups[$group] .= 
					"<p><a class='$prefix-view-all' href='$item[url]'>" . 
					"$item[title] $icon $groupLabel (" . $totals[$group] . ")</a></p>";
			} else {
				$groups[$group] .= $this->renderItem($item, $prefix) . '<hr />';
			}
		}
		
		$totalGroups = array();
		foreach($groups as $group => $content) {
			$total = $totals[$group];
			$totalGroups["$group ($total)"] = $content;
			unset($groups[$group]); 
		}
		
		$wireTabs = $this->wire('modules')->get('JqueryWireTabs');
		
		return
			"<div class='pw-search-$class'>" . 
				$pagination . 
				$wireTabs->render($totalGroups) . 
				$pagination . 
			"</div>";
	} 
	/**
	 * Render an item for the “view all” list
	 * 
	 * @param array $item
	 * @param string $prefix For CSS classes, default is "pw-search"
	 * @param string $class Class name for item, default is "item" which translates to "pw-search-item"
	 * @return string HTML markup
	 * 
	 */
	protected function ___renderItem(array $item, $prefix = 'pw-search', $class = 'item') {
	
		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		
		foreach(array('title', 'subtitle', 'summary', 'url') as $key) {
			if(isset($item[$key])) {
				$item[$key] = $sanitizer->entities($item[$key]);
			} else {
				$item[$key] = '';
			}
		}
		
		$title = "<strong class='$prefix-title'>$item[title]</strong>";
		$subtitle = empty($item['subtitle']) ? '' : "<br /><em class='$prefix-subtitle'>$item[subtitle]</em> ";
		$summary = empty($item['summary']) ? '' : "<br /><span class='$prefix-summary'>$item[summary]</span> ";
		
		return "\n\t<div class='$prefix-$class'><p><a href='$item[url]'>$title</a> $subtitle $summary</p></div>";
	}

}