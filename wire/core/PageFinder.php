<?php namespace ProcessWire;

/**
 * ProcessWire PageFinder
 *
 * Matches selector strings to pages
 * 
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 * Hookable methods: 
 * =================
 * @method array|DatabaseQuerySelect find(Selectors|string|array $selectors, $options = array())
 * @method DatabaseQuerySelect getQuery($selectors, array $options)
 * @method string getQueryAllowedTemplatesWhere(DatabaseQuerySelect $query, $where)
 * @method void getQueryJoinPath(DatabaseQuerySelect $query, $selector)
 * @method bool|Field getQueryUnknownField($fieldName, array $data);
 * 
 * @property string $includeMode
 * @property bool $checkAccess
 *
 */

class PageFinder extends Wire {

	/**
	 * Options (and their defaults) that may be provided as the 2nd argument to find()
	 *
	 */
	protected $defaultOptions = array(

		/**
		 * Specify that you only want to find 1 page and don't need info for pagination
		 * 	
		 */
		'findOne' => false,
		
		/**
		 * Specify that it's okay for hidden pages to be included in the results	
		 *
		 */
		'findHidden' => false,

		/**
		 * Specify that it's okay for hidden AND unpublished pages to be included in the results
		 *
		 */
		'findUnpublished' => false,
		
		/**
		 * Specify that it's okay for hidden AND unpublished AND trashed pages to be included in the results
		 *
		 */
		'findTrash' => false, 
		
		/**
		 * Specify that no page should be excluded - results can include unpublished, trash, system, no-access pages, etc.
		 *
		 */
		'findAll' => false,

		/**
		 * Always allow these page IDs to be included regardless of findHidden, findUnpublished, findTrash, findAll settings
		 * 
		 */
		'alwaysAllowIDs' => array(),

		/**
		 * This is an optimization used by the Pages::find method, but we observe it here as we may be able
		 * to apply some additional optimizations in certain cases. For instance, if loadPages=false, then 
		 * we can skip retrieval of IDs and omit sort fields.
		 *
		 */
		'loadPages' => true,

		/**
		 * When true, this function returns array of arrays containing page ID, parent ID, template ID and score.
		 * When false, returns only an array of page IDs. returnVerbose=true is required by most usage from Pages 
		 * class. False is only for specific cases. 
		 *
		 */
		'returnVerbose' => true,

		/**
		 * Return parent IDs rather than page IDs? (requires that returnVerbose is false)
		 * 
		 */
		'returnParentIDs' => false,
		
		/**
		 * Return [ page_id => template_id ] IDs array? (cannot be combined with other 'return*' options)
		 * @since 3.0.152
		 *
		 */
		'returnTemplateIDs' => false,

		/**
		 * Return all columns from pages table (cannot be combined with other 'return*' options)
		 * @since 3.0.153
		 * 
		 */
		'returnAllCols' => false,

		/**
		 * Additional options when when 'returnAllCols' option is true
		 * @since 3.0.172 
		 * 
		 */
		'returnAllColsOptions' => array(
			'joinFields' => array(), // names of additional fields to join
			'joinSortfield' => false, // include 'sortfield' in returned columns? (joined from pages_sortfields table)
			'joinPath' => false, // include the 'path' in returned columns (joined from pages_paths table, requires PagePaths module)
			'getNumChildren' => false, // include 'numChildren' in returned columns? (sub-select from pages table)
			'unixTimestamps' => false, // return dates as unix timestamps?
		),

		/**
		 * When true, only the DatabaseQuery object is returned by find(), for internal use. 
		 * 
		 */
		'returnQuery' => false, 

		/**
		 * Whether the total quantity of matches should be determined and accessible from getTotal()
		 *
		 * null: determine automatically (disabled when limit=1, enabled in all other cases)
		 * true: always calculate total
		 * false: never calculate total
		 *
		 */
		'getTotal' => null,

		/**
		 * Method to use when counting total records
		 *
		 * If 'count', total will be calculated using a COUNT(*).
		 * If 'calc, total will calculate using SQL_CALC_FOUND_ROWS.
		 * If blank or something else, method will be determined automatically.
		 * 
		 */
		'getTotalType' => 'calc',

		/**
		 * Only start loading pages after this ID
		 * 
		 */
		'startAfterID' => 0,
		
		/**
		 * Stop and load no more if a page having this ID is found
		 *
		 */
		'stopBeforeID' => 0,

		/**
		 * For internal use with startAfterID or stopBeforeID (when combined with a 'limit=n' selector)
		 * 
		 */
		'softLimit' => 0, 

		/**
		 * Reverse whatever sort is specified
		 * 
		 */
		'reverseSort' => false, 
		
		/**
		 * Allow use of _custom="another selector" in Selectors?
		 * 
		 */
		'allowCustom' => false,

		/**
		 * Use sortsAfter feature where PageFinder lets you perform the sorting manually after the find()
		 * 
		 * When in use, you can access the PageFinder::getSortsAfter() method to retrieve an array of sort
		 * fields that should be sent to PageArray::sort()
		 * 
		 * So far this option seems to add more overhead in most cases (rather than save it) so recommend not
		 * using it. Kept for further experimenting. 
		 * 
		 */
		'useSortsAfter' => false,

		/**
		 * Options passed to DatabaseQuery::bindOptions() for primary query generated by this PageFinder
		 * 
		 */
		'bindOptions' => array(),
	);

	/**
	 * @var Fields
	 * 
	 */
	protected $fields;
	
	/**
	 * @var Pages
	 *
	 */
	protected $pages;
	
	/**
	 * @var Sanitizer
	 *
	 */
	protected $sanitizer;
	
	/**
	 * @var WireDatabasePDO
	 *
	 */
	protected $database;
	
	/**
	 * @var Languages|null
	 *
	 */
	protected $languages;
	
	/**
	 * @var Templates
	 *
	 */
	protected $templates;
	
	/**
	 * @var Config
	 *
	 */
	protected $config;

	/**
	 * Whether to find the total number of matches
	 * 
	 * @var bool
	 * 
	 */
	protected $getTotal = true;

	/**
	 * Method to use for getting total, may be: 'calc', 'count', or blank to auto-detect.
	 * 
	 * @var string
	 * 
	 */
	protected $getTotalType = 'calc';

	/**
	 * Total found
	 * 
	 * @var int
	 * 
	 */
	protected $total = 0;

	/**
	 * Limit setting for pagination 
	 * 
	 * @var int
	 * 
	 */
	protected $limit = 0;

	/**
	 * Start setting for pagination
	 * 
	 * @var int
	 * 
	 */
	protected $start = 0;

	/**
	 * Parent ID value when query includes a single parent
	 * 
	 * @var int|null
	 * 
	 */
	protected $parent_id = null;

	/**
	 * Templates ID value when query includes a single template
	 * @var null
	 * 
	 */
	protected $templates_id = null;

	/**
	 * Check access enabled? Becomes false if check_access=0 or include=all
	 * 
	 * @var bool
	 * 
	 */
	protected $checkAccess = true;

	/**
	 * Include mode (when specified): all, hidden, unpublished
	 * 
	 * @var string
	 * 
	 */
	protected $includeMode = '';

	/**
	 * Number of times the getQueryNumChildren() method has been called
	 * 
	 * @var int
	 * 
	 */
	protected $getQueryNumChildren = 0;

	/**
	 * Options that were used in the most recent find()
	 * 
	 * @var array
	 * 
	 */
	protected $lastOptions = array();

	/**
	 * Extra OR selectors used for OR-groups, array of arrays indexed by group name
	 * 
	 * @var array
	 * 
	 */
	protected $extraOrSelectors = array(); // one from each field must match

	/**
	 * Array of sortfields that should be applied to resulting PageArray after loaded
	 * 
	 * Also see `useSortsAfter` option
	 * 
	 * @var array
	 * 
	 */
	protected $sortsAfter = array();

	/**
	 * Reverse order of pages after load?
	 * 
	 * @var bool
	 * 
	 */
	protected $reverseAfter = false;

	/**
	 * Data that should be conditionally populated back to any resulting PageArray’s data() method
	 * 
	 * @var array
	 * 
	 */
	protected $pageArrayData = array(
		/* may include: 
		'fields' => array()
		'extends' => array()
		'joinFields' => array()
		*/
	);

	/**
	 * The fully parsed/final selectors used in the last find() operation
	 * 
	 * @var Selectors|null
	 * 
	 */
	protected $finalSelectors = null; // Fully parsed final selectors

	/**
	 * Number of Selector objects that have alternate operators
	 * 
	 * @var int
	 * 
	 */
	protected $numAltOperators = 0;

	/**
	 * Cached value from supportsLanguagePageNames() method
	 * 
	 * @var null|bool 
	 * 
	 */
	protected $supportsLanguagePageNames = null;

	/**
	 * Fields that can only be used by themselves (not OR'd with other fields)
	 * 
	 * @var array
	 * 
	 */
	protected $singlesFields = array(
		'has_parent', 
		'hasParent', 
		'num_children',
		'numChildren',
		'children.count',
		'limit',
		'start',
	);
	
	// protected $extraSubSelectors = array(); // subselectors that are added in after getQuery()
	// protected $extraJoins = array();
	// protected $nativeWheres = array(); // where statements for native fields, to be reused in subselects where appropriate.
	
	public function __get($name) {
		if($name === 'includeMode') return $this->includeMode;
		if($name === 'checkAccess') return $this->checkAccess; 
		return parent::__get($name);
	}

	/**
	 * Initialize new find operation and prepare options
	 * 
	 * @param Selectors $selectors
	 * @param array $options
	 * @return array Returns updated options with all present
	 * 
	 */
	protected function init(Selectors $selectors, array $options) {

		$this->fields = $this->wire('fields');
		$this->pages = $this->wire('pages');
		$this->sanitizer = $this->wire('sanitizer');
		$this->database = $this->wire('database');
		$this->languages = $this->wire('languages');
		$this->templates = $this->wire('templates');
		$this->config = $this->wire('config');
		$this->parent_id = null;
		$this->templates_id = null;
		$this->checkAccess = true;
		$this->getQueryNumChildren = 0;
		$this->pageArrayData = array();

		$options = array_merge($this->defaultOptions, $options);
		$options = $this->initSelectors($selectors, $options);

		// move getTotal option to a class property, after initStatusChecks
		$this->getTotal = $options['getTotal'];
		$this->getTotalType = $options['getTotalType'] == 'count' ? 'count' : 'calc';
		
		unset($options['getTotal']); // so we get a notice if we try to access it
		
		$this->lastOptions = $options; 
		
		return $options;
	}

	/**
	 * Initialize the selectors to add Page status checks
	 * 
	 * @param Selectors $selectors
	 * @param array $options
	 * @return array
	 *
	 */
	protected function initSelectors(Selectors $selectors, array $options) {

		$limit = 0; // for getTotal auto detection
		$start = 0;
		$limitSelector = null;
		$startSelector = null;
		$addSelectors = array();
		$hasParents = array(); // requests for parent(s) in the selector
		$hasSort = false; // whether or not a sort is requested
		
		// field names that do not accept array values
		$noArrayFields = array( 
			'status' => 1, // 1: array not allowed for field only
			'include' => 2, // 2: array not allowed for field or value
			'check_access' => 2,
			'checkAccess' => 2,
			'limit' => 1,
			'start' => 2,
			'getTotal' => 2,
			'get_total' => 2,
		);
	
		// include mode names to option names
		$includeOptions = array(
			'hidden' => 'findHidden', 
			'unpublished' => 'findUnpublished',
			'trash' => 'findTrash',
			'all' => 'findAll', 
		);

		foreach($selectors as $key => $selector) {
			
			$fieldName = $selector->field;
			$operator = $selector->operator;
			$value = $selector->value;
			$disallow = '';
			
			if(is_array($fieldName)) {
				foreach($fieldName as $name) {
					if(isset($noArrayFields[$name])) $disallow = "field:$name";
					if($disallow) break;
				}
				$fieldName = $selector->field(); // force string
			} else if(isset($noArrayFields[$fieldName]) && is_array($value)) {
				if($noArrayFields[$fieldName] > 1) $disallow = 'value';
			}

			if($disallow) {
				$this->syntaxError("OR-condition not supported for $disallow in '$selector'");
			}
			
			if($fieldName === 'include') { 
				$value = strtolower($value);
				if($operator !== '=') {
					// disallowed operator for include
					$this->syntaxError("Unsupported operator '$operator' in '$selector'");
				} else if(!isset($includeOptions[$value])) {
					// unrecognized include option
					$useOnly = implode(', ', array_keys($includeOptions));
					$this->syntaxError("Unrecognized '$value' in '$selector' - use only: $useOnly");
				} else {
					// i.e. hidden=findHidden, findUnpublished, findTrash, findAll
					$option = $includeOptions[$value]; 
					$options[$option] = true;
					$this->includeMode = $value;
					$selectors->remove($key);
				}

			} else if($fieldName === 'limit') {
				// for getTotal auto detect
				if(is_array($value)) {
					if(count($value) === 2) {
						// limit and start, i.e. limit=20,10 means start at 20 and limit to 10
						$limit = (int) $value[1];
						if(!$startSelector) {
							// use start value only if it was not previously specified
							$start = (int) $value[0];
							$startSelector = new SelectorEqual('start', $start);
							$addSelectors['start'] = $startSelector;
						}
					} else {
						$limit = (int) $value[0];
					}
					$selector->value = $limit;
				} else {
					$limit = (int) $value;
				}
				$limitSelector = $selector;

			} else if($fieldName === 'start') {
				// for getTotal auto detect
				$start = (int) $value; 	
				$startSelector = $selector;
				unset($addSelectors['start']); // just in case specified twice

			} else if($fieldName === 'sort') {
				// sorting is not needed if we are only retrieving totals
				if($options['loadPages'] === false) $selectors->remove($selector);
				$hasSort = true;

			} else if($fieldName === 'parent' || $fieldName === 'parent_id') {
				$hasParents[] = $value;

			} else if($fieldName === 'getTotal' || $fieldName === 'get_total') {
				// whether to retrieve the total, and optionally what type: calc or count
				// this applies only if user hasn't themselves created a field called getTotal or get_total
				if($this->fields->get($fieldName)) {
					// user has created a field having name 'getTotal' or 'get_total'
					// so we do not provide the getTotal option
				} else {
					if(ctype_digit("$value")) {
						$options['getTotal'] = (bool) ((int) $value);
					} else if($value === 'calc' || $value === 'count') {
						$options['getTotal'] = true; 
						$options['getTotalType'] = $value; 
					} else {
						// warning: unknown getTotal type
						$options['getTotal'] = $value ? true : false;
					}
					$selectors->remove($selector); 
				}
			} else if($fieldName === 'children' || $fieldName === 'child') {
				// i.e. children=/path/to/page|/another/path - convert to IDs
				$values = is_array($value) ? $value : array($value);
				foreach($values as $k => $v) {
					if(ctype_digit("$v")) continue;
					if(strpos($v, '/') !== 0) continue;
					$child = $this->pages->get($v);
					$values[$k] = $child->id;
				}
				$selector->value = count($values) > 1 ? $values : reset($values);
			}
		} // foreach($selectors)
		
		foreach($addSelectors as $selector) {
			$selectors->add($selector);
		}

		// find max status, and update selector to bitwise when needed
		$this->initStatus($selectors, $options);

		if($options['findOne']) {
			// findOne option is never paginated, always starts at 0
			if($startSelector) $selectors->remove($startSelector);
			$selectors->add(new SelectorEqual('start', 0)); 
			if(empty($options['startAfterID']) && empty($options['stopBeforeID'])) {
				if($limitSelector) $selectors->remove($limitSelector);
				$selectors->add(new SelectorEqual('limit', 1));
			}
			// getTotal default is false when only finding 1 page
			if(is_null($options['getTotal'])) $options['getTotal'] = false; 

		} else if(!$limit && !$start) {
			// getTotal is not necessary since there is no limit specified (getTotal=same as count)
			if(is_null($options['getTotal'])) $options['getTotal'] = false; 

		} else {
			// get Total default is true when finding multiple pages
			if(is_null($options['getTotal'])) $options['getTotal'] = true; 
		}
		
		if(count($hasParents) === 1 && !$hasSort) {
			// if single parent specified and no sort requested, default to the sort specified with the requested parent
			try {
				$parent = $this->pages->get(reset($hasParents));
			} catch(\Exception $e) {
				// don't try to add sort
				$parent = null;
			}
			if($parent && $parent->id) {
				$sort = $parent->template->sortfield;
				if(!$sort) $sort = $parent->sortfield;
				if($sort) $selectors->add(new SelectorEqual('sort', $sort));
			}
		}
		
		if(!$options['findOne'] && $limitSelector && ($options['startAfterID'] || $options['stopBeforeID'])) {
			$options['softLimit'] = $limit;
			$selectors->remove($limitSelector);
		}
		
		return $options;
	}

	/**
	 * Initialize status checks
	 * 
	 * @param Selectors $selectors
	 * @param array $options
	 * 
	 */
	protected function initStatus(Selectors $selectors, array $options) {
		
		$maxStatus = null;
		$lessStatus = 0;
		$statuses = array(); // i.e. [ 'hidden' => 1024, 'unpublished' => 2048, ], etc
		$checkAccessSpecified = false;
		$findAll = $options['findAll'];
		$findTrash = $options['findTrash'];
		$findHidden = $options['findHidden'];
		$findUnpublished = $options['findUnpublished'];

		foreach($selectors as $key => $selector) {
			$fieldName = $selector->field();
			
			if($fieldName === 'check_access' || $fieldName === 'checkAccess') {
				if($fieldName === 'checkAccess') $selector->field = 'check_access';
				$this->checkAccess = ((int) $selector->value()) > 0 ? true : false;
				$checkAccessSpecified = true;
				$selectors->remove($key);
				continue;
			} else if($fieldName !== 'status') {
				continue;
			}

			$operator = $selector->operator;
			$values = $selector->values();
			$qty = count($values);
			$not = false;

			// convert status name labels to status integers
			foreach($values as $k => $v) {
				if(ctype_digit("$v")) {
					$v = (int) $v;
				} else {
					// allow use of some predefined labels for Page statuses
					$v = strtolower($v);
					if(empty($statuses)) $statuses = Page::getStatuses();
					$v = isset($statuses[$v]) ? $statuses[$v] : 1;
				}
				$values[$k] = $v;
			}

			if(($operator === '!=' && !$selector->not) || ($selector->not && $operator === '=')) {
				// NOT MATCH condition: replace with bitwise AND NOT selector
				/** @var Selector $s */
				$s = $this->wire(new SelectorBitwiseAnd('status', $qty > 1 ? $values : reset($values)));
				$s->not = true;
				$not = true;
				$selectors[$key] = $s;

			} else if($operator === '=' || ($operator === '!=' && $selector->not)) {
				// MATCH condition: replace with bitwise AND selector
				$selectors[$key] = $this->wire(new SelectorBitwiseAnd('status', $qty > 1 ? $values : reset($values)));

			} else {
				// some other operator like: >, <, >=, <=, &
				$not = $selector->not;
			}

			if($not) {
				// NOT condition does not apply to maxStatus
			} else {
				foreach($values as $v) {
					if($maxStatus === null || $v > $maxStatus) $maxStatus = (int) $v;
				}
			}
		}

		if($findAll) {
			// findAll option means that unpublished, hidden, trash, system may be included
			if(!$checkAccessSpecified) $this->checkAccess = false;
			
		} else if($findHidden) {
			$lessStatus = Page::statusUnpublished;
			
		} else if($findUnpublished) {
			$lessStatus = Page::statusTrash;
			
		} else if($findTrash) {
			$lessStatus = Page::statusDeleted;
			
		} else if($maxStatus !== null) {
			// status already present in the selector, without a findAll/findUnpublished/findHidden: use maxStatus value
			if($maxStatus < Page::statusHidden) {
				$lessStatus = Page::statusHidden;
			} else if($maxStatus < Page::statusUnpublished) {
				$lessStatus = Page::statusUnpublished;
			} else if($maxStatus < Page::statusTrash) {
				$lessStatus = Page::statusTrash;
			}
			
		} else {
			// no status is present, so exclude everything hidden and above
			$lessStatus = Page::statusHidden;
		}

		if($lessStatus) {
			$selectors->add(new SelectorLessThan('status', $lessStatus));
		}
	}

	/**
	 * Return all pages matching the given selector.
	 * 
	 * @param Selectors|string|array $selectors Selectors object, selector string or selector array
	 * @param array $options
	 *  - `findOne` (bool): Specify that you only want to find 1 page and don't need info for pagination (default=false).
	 *  - `findHidden` (bool): Specify that it's okay for hidden pages to be included in the results (default=false). 
	 *  - `findUnpublished` (bool): Specify that it's okay for hidden AND unpublished pages to be included in the
	 *     results (default=false).
	 *  - `findTrash` (bool): Specify that it's okay for hidden AND unpublished AND trashed pages to be included in the
	 *     results (default=false).
	 *  - `findAll` (bool): Specify that no page should be excluded - results can include unpublished, trash, system,
	 *     no-access pages, etc. (default=false)
	 *  - `getTotal` (bool|null): Whether the total quantity of matches should be determined and accessible from
	 *     getTotal() method call. 
	 *     - null: determine automatically (default is disabled when limit=1, enabled in all other cases).
	 *     - true: always calculate total.
	 *     - false: never calculate total.
	 *  - `getTotalType` (string): Method to use to get total, specify 'count' or 'calc' (default='calc').
	 *  - `returnQuery` (bool): When true, only the DatabaseQuery object is returned by find(), for internal use. (default=false)
	 *  - `loadPages` (bool): This is an optimization used by the Pages::find() method, but we observe it here as we
	 *     may be able to apply some additional optimizations in certain cases. For instance, if loadPages=false, then
	 *     we can skip retrieval of IDs and omit sort fields. (default=true)
	 *  - `stopBeforeID` (int): Stop loading pages once a page matching this ID is found. Page having this ID will be
	 *     excluded as well (default=0).
	 *  - `startAfterID` (int): Start loading pages once a page matching this ID is found. Page having this ID will be
	 *     excluded as well (default=0).
	 *  - `reverseSort` (bool): Reverse whatever sort is specified.
	 *  - `returnVerbose` (bool): When true, this function returns array of arrays containing page ID, parent ID,
	 *     template ID and score. When false, returns only an array of page IDs. True is required by most usage from
	 *     Pages class. False is only for specific cases. 
	 *  - `returnParentIDs` (bool): Return parent IDs only? (default=false, requires that 'returnVerbose' option is false).
	 *  - `returnTemplateIDs` (bool): Return [pageID => templateID] array? [3.0.152+ only] (default=false, cannot be combined with other 'return*' options).
	 *  - `returnAllCols` (bool): Return [pageID => [ all columns ]] array? [3.0.153+ only] (default=false, cannot be combined with other 'return*' options).
	 *  - `allowCustom` (bool): Whether or not to allow _custom='selector string' type values (default=false). 
	 *  - `useSortsAfter` (bool): When true, PageFinder may ask caller to perform sort manually in some cases (default=false). 
	 * @return array|DatabaseQuerySelect
	 * @throws PageFinderException
	 *
	 */
	public function ___find($selectors, array $options = array()) {
		
		if(is_string($selectors) || is_array($selectors)) {
			list($s, $selectors) = array($selectors, $this->wire(new Selectors())); 
			/** @var Selectors $selectors */
			$selectors->init($s);
		} else if(!$selectors instanceof Selectors) {
			throw new PageFinderException("find() requires Selectors object, string or array");
		}
		
		$options = $this->init($selectors, $options);
		$stopBeforeID = (int) $options['stopBeforeID'];
		$startAfterID = (int) $options['startAfterID'];
		$database = $this->database;
		$matches = array();
		$query = $this->getQuery($selectors, $options); /** @var DatabaseQuerySelect $query */
		
		if($options['returnQuery']) return $query; 

		if($options['loadPages'] || $this->getTotalType === 'calc') {

			try {
				$stmt = $query->prepare();
				$database->execute($stmt);
				$error = '';
			} catch(\Exception $e) {
				$this->trackException($e, true);
				$error = $e->getMessage();
				$stmt = null;
			}
		
			if($error) {
				$this->log($error); 
				throw new PageFinderException($error); 
			}
		
			if($options['loadPages']) {
				$softCnt = 0; // for startAfterID when combined with 'limit'
				
				/** @noinspection PhpAssignmentInConditionInspection */
				while($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
					
					if($startAfterID > 0) {
						if($row['id'] != $startAfterID) continue;	
						$startAfterID = -1; // -1 indicates that recording may start
						continue;
					}
					
					if($stopBeforeID && $row['id'] == $stopBeforeID) {
						if($options['findOne']) {
							$matches = count($matches) ? array(end($matches)) : array();
						} else if($options['softLimit']) {
							$matches = array_slice($matches, -1 * $options['softLimit']);
						}
						break;
					}
					
					if($options['returnVerbose']) {
						// determine score for this row
						$score = 0.0;
						foreach($row as $k => $v) if(strpos($k, '_score_') === 0) {
							$v = (float) $v; 
							if($v === 111.1 || $v === 222.2 || $v === 333.3) continue; // signal scores of non-match
							$score += $v;
							unset($row[$k]);
						}
						$row['score'] = $score;
						$matches[] = $row;

					} else if($options['returnAllCols']) {
						$matches[(int) $row['id']] = $row;
						
					} else if($options['returnTemplateIDs']) {
						$matches[(int) $row['id']] = (int) $row['templates_id'];

					} else {
						$matches[] = (int) $row['id']; 
					}
				
					if($startAfterID === -1) {
						// -1 indicates that recording may start
						if($options['findOne']) {
							break;
						} else if($options['softLimit'] && ++$softCnt >= $options['softLimit']) {
							break;
						}
					}
				}
			}
			
			$stmt->closeCursor();
		} 
			
		if($this->getTotal) {
			if($this->getTotalType === 'count') {
				$query->set('select', array('COUNT(*)'));
				$query->set('orderby', array()); 
				$query->set('groupby', array()); 
				$query->set('limit', array());
				$stmt = $query->execute();
				$errorInfo = $stmt->errorInfo();
				if($stmt->errorCode() > 0) throw new PageFinderException($errorInfo[2]);
				list($this->total) = $stmt->fetch(\PDO::FETCH_NUM); 
				$stmt->closeCursor();
			} else {
				$this->total = (int) $database->query("SELECT FOUND_ROWS()")->fetchColumn();
			}
			
		} else {
			$this->total = count($matches);
		}
	
		if(!$this->total && $this->numAltOperators) {
			// check if any selectors provided alternate operators to try
			$matches = $this->findAlt($selectors, $options, $matches); 
		}

		$this->lastOptions = $options; 
		
		if($this->reverseAfter) $matches = array_reverse($matches);

		return $matches; 
	}

	/**
	 * Perform an alternate/fallback find when first fails to match and alternate operators available
	 * 
	 * @param Selectors $selectors
	 * @param array $options
	 * @param array $matches
	 * @return array
	 * 
	 */
	protected function findAlt($selectors, $options, $matches) {
		// check if any selectors provided alternate operators to try
		$numAlts = 0;
		foreach($selectors as $key => $selector) {
			$altOperators = $selector->altOperators;
			if(!count($altOperators)) continue;
			$altOperator = array_shift($altOperators);
			$sel = Selectors::getSelectorByOperator($altOperator);
			if(!$sel) continue;
			$selector->copyTo($sel);
			$selectors[$key] = $sel;
			$numAlts++;
		}
		if(!$numAlts) return $matches;
		$this->numAltOperators = 0;
		return $this->___find($selectors, $options);
	}
	
	/**
	 * Same as find() but returns just a simple array of page IDs without any other info
	 *
	 * @param Selectors|string|array $selectors Selectors object, selector string or selector array
	 * @param array $options
	 * @return array of page IDs
	 *
	 */
	public function findIDs($selectors, $options = array()) {
		$options['returnVerbose'] = false; 
		return $this->find($selectors, $options); 
	}

	/**
	 * Returns array of arrays with all columns in pages table indexed by page ID
	 * 
	 * @param Selectors|string|array $selectors Selectors object, selector string or selector array
	 * @param array $options
	 *  - `joinFields` (array): Names of additional fields to join (default=[]) 3.0.172+
	 *  - `joinSortfield` (bool): Include 'sortfield' in returned columns? Joined from pages_sortfields table. (default=false) 3.0.172+
	 *  - `getNumChildren` (bool): Include 'numChildren' in returned columns? Calculated in query. (default=false) 3.0.172+
	 *  - `unixTimestamps` (bool): Return created/modified/published dates as unix timestamps rather than ISO-8601? (default=false) 3.0.172+
	 * @return array|DatabaseQuerySelect
	 * @since 3.0.153
	 * 
	 */
	public function findVerboseIDs($selectors, $options = array()) {
		$hasCustomOptions = count($options) > 0;
		$options['returnVerbose'] = false;
		$options['returnAllCols'] = true;
		$options['returnAllColsOptions'] = $this->defaultOptions['returnAllColsOptions'];
		if($hasCustomOptions) {
			// move some from $options into $options['returnAllColsOptions']
			foreach($options['returnAllColsOptions'] as $name => $default) {
				if(!isset($options[$name])) continue;
				$options['returnAllColsOptions'][$name] = $options[$name];
				unset($options[$name]);
			}
		}
		return $this->find($selectors, $options); 
	}
	
	/**
	 * Same as findIDs() but returns the parent IDs of the pages that matched
	 *
	 * @param Selectors|string|array $selectors Selectors object, selector string or selector array
	 * @param array $options
	 * @return array of page parent IDs
	 *
	 */
	public function findParentIDs($selectors, $options = array()) {
		$options['returnVerbose'] = false;
		$options['returnParentIDs'] = true;
		return $this->find($selectors, $options);
	}

	/**
	 * Find template ID for each page — returns array of template IDs indexed by page ID
	 * 
	 * @param Selectors|string|array $selectors Selectors object, selector string or selector array
	 * @param array $options
	 * @return array 
	 * @since 3.0.152
	 * 
	 */
	public function findTemplateIDs($selectors, $options = array()) {
		$options['returnVerbose'] = false;
		$options['returnParentIDs'] = false;
		$options['returnTemplateIDs'] = true;
		return $this->find($selectors, $options);
	}

	/**
	 * Return a count of pages that match 
	 * 
	 * @param Selectors|string|array $selectors Selectors object, selector string or selector array
	 * @param array $options
	 * @return int
	 * @since 3.0.121
	 * 
	 */
	public function count($selectors, $options = array()) {
		
		$defaults = array(
			'getTotal' => true,
			'getTotalType' => 'count',
			'loadPages' => false, 
			'returnVerbose' => false
		);
		
		$options = array_merge($defaults, $options);
		
		if(!empty($options['startBeforeID']) || !empty($options['stopAfterID'])) {
			$options['loadPages'] = true;
			$options['getTotalType'] = 'calc';
			$count = count($this->find($selectors, $options));
		} else {
			$this->find($selectors, $options);
			$count = $this->total;
		}
		
		return $count;
	}

	/**
	 * Pre-process given Selectors object 
	 * 
	 * @param Selectors $selectors
	 * @param array $options
	 * 
	 */
	protected function preProcessSelectors(Selectors $selectors, $options = array()) {
		
		$sortAfterSelectors = array();
		$sortSelectors = array();
		$start = null;
		$limit = null;
		$eq = null;
		
		foreach($selectors as $selector) {
			$field = $selector->field();
			
			if($field === '_custom') {
				$selectors->remove($selector);
				if(!empty($options['allowCustom'])) {
					$_selectors = $this->wire(new Selectors($selector->value()));
					$this->preProcessSelectors($_selectors, $options);
					/** @var Selectors $_selectors */
					foreach($_selectors as $s) $selectors->add($s);
				} else {
					// use of _custom has not been specifically allowed
				}
				
			} else if($field === 'sort') {
				$sortSelectors[] = $selector;
				if(!empty($options['useSortsAfter']) && $selector->operator == '=' && strpos($selector->value, '.') === false) {
					$sortAfterSelectors[] = $selector;
				}
				
			} else if($field === 'limit') {
				$limit = (int) $selector->value;
				
			} else if($field === 'start') {
				$start = (int) $selector->value; 
				
			} else if($field === 'eq' || $field === 'index') { 
				if($this->fields->get($field)) continue;
				$value = $selector->value; 
				if($value === 'first') {
					$eq = 0;
				} else if($value === 'last') {
					$eq = -1;
				} else {
					$eq = (int) $value;
				}
				$selectors->remove($selector);
				
			} else if(strpos($field, '.owner.') && !$this->fields->get('owner')) {
				$selector->field = str_replace('.owner.', '__owner.', $selector->field()); 
				
			} else if(stripos($field, 'Fieldtype') === 0) {
				$this->preProcessFieldtypeSelector($selectors, $selector); 
			}
		}
		
		if(!is_null($eq)) {
			if($eq === -1) {
				$limit = -1;
				$start = null;
			} else if($eq === 0) {
				$start = 0;
				$limit = 1;
			} else {
				$start = $eq;
				$limit = 1;
			}
		}
		
		if(!$limit && !$start && count($sortAfterSelectors) 
			&& $options['returnVerbose'] && !empty($options['useSortsAfter']) 
			&& empty($options['startAfterID']) && empty($options['stopBeforeID'])) {
			// the `useSortsAfter` option is enabled and potentially applicable
			$sortsAfter = array(); 
			foreach($sortAfterSelectors as $n => $selector) {
				if(!$n && $this->pages->loader()->isNativeColumn($selector->value)) {
					// first iteration only, see if it's a native column and prevent sortsAfter if so
					break;
				}
				if(strpos($selector->value(), '.') !== false) {
					// we don't supports sortsAfter for subfields, so abandon entirely
					$sortsAfter = array();
					break;
				}
				if($selector->operator != '=') {
					// sort property being used for something else that we don't recognize
					continue;
				}
				$sortsAfter[] = $selector->value;
				$selectors->remove($selector);
			}
			$this->sortsAfter = $sortsAfter;
		}
		
		if($limit !== null && $limit < 0) {
			// negative limit value means we pull results from end rather than start
			if($start !== null && $start < 0) {
				// we don't support a double negative, so double negative makes a positive
				$start = abs($start);
				$limit = abs($limit);
			} else if($start > 0) {
				$start = $start - abs($limit);
				$limit = abs($limit);
			} else {
				$this->reverseAfter = true;
				$limit = abs($limit);
			}	
		}
		
		if($start !== null && $start < 0) {
			// negative start value means we start from a value from the end rather than the start
			if($limit) {
				// determine how many pages total and subtract from that to get start
				$o = $options;
				$o['getTotal'] = true;
				$o['loadPages'] = false;
				$o['returnVerbose'] = false;
				$sel = clone $selectors;
				foreach($sel as $s) {
					if($s->field == 'limit' || $s->field == 'start') $sel->remove($s);
				}
				$sel->add(new SelectorEqual('limit', 1));
				$finder = new PageFinder();
				$this->wire($finder);
				$finder->find($sel);
				$total = $finder->getTotal();
				$start = abs($start);
				$start = $total - $start;
				if($start < 0) $start = 0;
			} else {
				// same as negative limit
				$this->reverseAfter = true;
				$limit = abs($start);
				$start = null;
			}
		}
		
		if($this->reverseAfter) {
			// reverse the sorts
			foreach($sortSelectors as $s) {
				if($s->operator != '=' || ctype_digit($s->value)) continue;
				if(strpos($s->value, '-') === 0) {
					$s->value = ltrim($s->value, '-');
				} else {
					$s->value = '-' . $s->value;
				}
			}	
		}
		
		$this->limit = $limit;
		$this->start = $start;
	}

	/**
	 * Pre-process a selector having field name that begins with "Fieldtype"
	 * 
	 * @param Selectors $selectors
	 * @param Selector $selector
	 * 
	 */
	protected function preProcessFieldtypeSelector(Selectors $selectors, Selector $selector) {
	
		$foundFields = null;
		$foundTypes = null;
		$replaceFields = array();
		$failFields = array();
		$languages = $this->languages;
		$fieldtypes = $this->wire()->fieldtypes;
		$selectorCopy = null;
		
		foreach($selector->fields() as $fieldName) {
		
			$subfield = '';
			$findPerField = false;
			$findExtends = false;

			if(strpos($fieldName, '.')) {
				$parts = explode('.', $fieldName);
				$fieldName = array_shift($parts); 
				foreach($parts as $k => $part) {
					if($part === 'fields') {
						$findPerField = true;
						unset($parts[$k]);
					} else if($part === 'extends') {
						$findExtends = true;
						unset($parts[$k]); 
					}
				}
				if(count($parts)) $subfield = implode('.', $parts);
			}
			
			$fieldtype = $fieldtypes->get($fieldName);
			if(!$fieldtype) continue;
			$fieldtypeLang = $languages ? $fieldtypes->get("{$fieldName}Language") : null;
			
			foreach($this->fields as $f) {
				/** @var Field $f */
				if($findExtends) {
					// allow any Fieldtype that is an instance of given one, or extends it
					if(!wireInstanceOf($f->type, $fieldtype) 
						&& ($fieldtypeLang === null || !wireInstanceOf($f->type, $fieldtypeLang))) continue;
					/** potential replacement for the above 2 lines
					if($f->type->className() === $fieldName) {
						// always allowed
					} else if(!wireInstanceOf($f->type, $fieldtype) && ($fieldtypeLang === null || !wireInstanceOf($f->type, $fieldtypeLang))) {
						// this field’s type does not extend the one we are looking for
						continue;
					} else {
						// looks good, but now check operators
						$selectorInfo = $f->type->getSelectorInfo($f);
						// if operator used in selector is not an allowed one, then skip over this field
						if(!in_array($selector->operator(), $selectorInfo['operators'])) continue;
					}
					*/
					
				} else {
					// only allow given Fieldtype
					if($f->type !== $fieldtype && ($fieldtypeLang === null || $f->type !== $fieldtypeLang)) continue;
				}
				
				$fName = $subfield ? "$f->name.$subfield" : $f->name;
				
				if($findPerField) {
					if($selectorCopy === null) $selectorCopy = clone $selector;
					$selectorCopy->field = $fName;
					$selectors->replace($selector, $selectorCopy); 
					$count = $this->pages->count($selectors);
					$selectors->replace($selectorCopy, $selector); 
					if($count) {
						if($foundFields === null) {
							$foundFields = isset($this->pageArrayData['fields']) ? $this->pageArrayData['fields'] : array(); 
						}
						// include only fields that we know will match
						$replaceFields[$fName] = $fName;
						if(isset($foundFields[$fName])) {
							$foundFields[$fName] += $count;
						} else {
							$foundFields[$fName] = $count;
						}
					} else {
						$failFields[$fName] = $fName;
					}
				} else {
					// include all fields (faster)
					$replaceFields[$fName] = $fName;
				}
				
				if($findExtends) {
					if($foundTypes === null) {
						$foundTypes = isset($this->pageArrayData['extends']) ? $this->pageArrayData['extends'] : array();
					}
					$fType = $f->type->className();
					if(isset($foundTypes[$fType])) {
						$foundTypes[$fType][] = $fName;
					} else {
						$foundTypes[$fType] = array($fName);
					}
				}
			}
		}
		
		if(count($replaceFields)) {
			$selector->fields = array_values($replaceFields);
		} else if(count($failFields)) {
			// forced non-match and prevent field-not-found error after this method
			$selector->field = reset($failFields);
		}
		
		if(is_array($foundFields)) {
			arsort($foundFields);
			$this->pageArrayData['fields'] = $foundFields;
		}
		if(is_array($foundTypes)) {
			$this->pageArrayData['extends'] = $foundTypes;
		}
	}


	/**
	 * Pre-process the given selector to perform any necessary replacements
	 *
	 * This is primarily used to handle sub-selections, i.e. "bar=foo, id=[this=that, foo=bar]"
	 * and OR-groups, i.e. "(bar=foo), (foo=bar)"
	 * 
	 * @param Selector $selector
	 * @param Selectors $selectors
	 * @param array $options
	 * @param int $level
	 * @return bool|Selector Returns false if selector should be skipped over by getQuery(), returns Selector otherwise
	 * @throws PageFinderSyntaxException
	 *
	 */
	protected function preProcessSelector(Selector $selector, Selectors $selectors, array $options, $level = 0) {
	
		$quote = $selector->quote;
		$fieldsArray = $selector->fields;
		$hasDoubleDot = false;
		$tags = null;
		
		foreach($fieldsArray as $key => $fn) {
			
			$dot = strpos($fn, '.');
			$parts = $dot ? explode('.', $fn) : array($fn);
			
			// determine if it is a double-dot field (a.b.c)
			if($dot && strrpos($fn, '.') !== $dot) {
				if(strpos($fn, '__owner.') !== false) continue;
				$hasDoubleDot = true;
			} 
	
			// determine if it is referencing any tags that should be coverted to field1|field2|field3
			foreach($parts as $partKey => $part) {
				if($tags !== null && empty($tags)) continue;
				if($this->fields->get($part)) continue; // maps to Field object
				if($this->fields->isNative($part)) continue; // maps to native property
				if($tags === null) $tags = $this->fields->getTags(true); // determine tags
				if(!isset($tags[$part])) continue; // not a tag
				$tagFields = $tags[$part];
				foreach($tagFields as $k => $fieldName) {
					$_parts = $parts;
					$_parts[$partKey] = $fieldName;
					$tagFields[$k] = implode('.', $_parts);
				}
				if(count($tagFields)) {
					unset($fieldsArray[$key]);
					$selector->fields = array_merge($fieldsArray, $tagFields);
				}
			}
		}
		
		if($quote == '[') {
			// selector contains another embedded selector that we need to convert to page IDs
			// i.e. field=[id>0, name=something, this=that]
			$this->preProcessSubSelector($selector, $selectors);

		} else if($quote == '(') {
			// selector contains an OR group (quoted selector)
			// at least one (quoted selector) must match for each field specified in front of it
			$groupName = $selector->group ? $selector->group : $selector->getField('string');
			$groupName = $this->sanitizer->fieldName($groupName);
			if(!$groupName) $groupName = 'none';
			if(!isset($this->extraOrSelectors[$groupName])) $this->extraOrSelectors[$groupName] = array();
			if($selector->value instanceof Selectors) {
				$this->extraOrSelectors[$groupName][] = $selector->value;
			} else {
				if($selector->group) {
					// group is pre-identified, indicating Selector field=value is the OR-group condition
					$s = clone $selector;
					$s->quote = '';
					$s->group = null;
					$groupSelectors = new Selectors();
					$groupSelectors->add($s);
				} else {
					// selector field is group name and selector value is another selector containing OR-group condition
					$groupSelectors = new Selectors($selector->value);
				}
				$this->wire($groupSelectors);
				$this->extraOrSelectors[$groupName][] = $groupSelectors;
			}
			return false;
			
		} else if($hasDoubleDot) {
			// has an "a.b.c" type string in the field, convert to a sub-selector
			
			if(count($fieldsArray) > 1) {
				$this->syntaxError("Multi-dot 'a.b.c' type selectors may not be used with OR '|' fields");
			}
			
			$fn = reset($fieldsArray);
			$parts = explode('.', $fn);
			$fieldName = array_shift($parts);
			$field = $this->isPageField($fieldName);
			
			if($field) {
				// we have a workable page field
				/** @var Selectors $_selectors */
				if($options['findAll']) {
					$s = "include=all";
				} else if($options['findHidden']) {
					$s = "include=hidden";
				} else if($options['findUnpublished']) {
					$s = "include=unpublished";
				} else {
					$s = '';
				}
				/** @var Selectors $_selectors */
				$_selectors = $this->wire(new Selectors($s));
				$_selector = $_selectors->create(implode('.', $parts), $selector->operator, $selector->values);
				$_selectors->add($_selector);
				$sel = new SelectorEqual("$fieldName", $_selectors);
				$sel->quote = '[';
				if(!$level) $selectors->replace($selector, $sel);
				$selector = $sel;
				$sel = $this->preProcessSelector($sel, $selectors, $options, $level + 1);
				if($sel) $selector = $sel;
			} else {
				// not a page field
			}
		}
		
		return $selector;
	}

	/*
	 * This turns out to be a lot slower than preProcessSubSelector(), but kept here for additional experiments
	 * 
	protected function preProcessSubquery(Selector $selector) {
		$finder = $this->wire(new PageFinder());
		$selectors = $selector->getValue();
		if(!$selectors instanceof Selectors) return true; // not a sub-selector
		$subfield = '';
		$fieldName = $selector->field;
		if(is_array($fieldName)) return true; // we don't allow OR conditions for field here
		if(strpos($fieldName, '.')) list($fieldName, $subfield) = explode('.', $fieldName);
		$field = $this->wire('fields')->get($fieldName);
		if(!$field) return true; // does not resolve to a known field

		$query = $finder->find($selectors, array(
			'returnQuery' => true,
			'returnVerbose' => false
		));
		$database = $this->wire('database');
		$table = $database->escapeTable($field->getTable());
		if($subfield == 'id' || !$subfield) {
			$subfield = 'data';
		} else {
			$subfield = $database->escapeCol($this->wire('sanitizer')->fieldName($subfield));
		}
		if(!$table || !$subfield) return true;
		static $n = 0;
		$n++;
		$tableAlias = "_subquery_{$n}_$table";
		$join = "$table AS $tableAlias ON $tableAlias.pages_id=pages.id AND $tableAlias.$subfield IN (" . $query->getQuery() . ")";
		echo $join . "<br />";
		$this->extraJoins[] = $join;
	}
	*/

	/**
	 * Pre-process a Selector that has a [quoted selector] embedded within its value
	 * 
	 * @param Selector $selector
	 * @param Selectors $parentSelectors
	 * 
	 */
	protected function preProcessSubSelector(Selector $selector, Selectors $parentSelectors) {

		// Selector contains another embedded selector that we need to convert to page IDs.
		// Example: "field=[id>0, name=something, this=that]" converts to "field.id=123|456|789"

		$selectors = $selector->getValue();
		if(!$selectors instanceof Selectors) return;
		
		$hasTemplate = false;
		$hasParent = false;
		$hasInclude = false;
		
		foreach($selectors as $s) {
			if(is_array($s->field)) continue;
			if($s->field == 'template') $hasTemplate = true;
			if($s->field == 'parent' || $s->field == 'parent_id' || $s->field == 'parent.id') $hasParent = true;
			if($s->field == 'include' || $s->field == 'status') $hasInclude = true;
		}
		
		if(!$hasInclude) {
			// see if parent selector has an include mode, and copy it over to this one
			foreach($parentSelectors as $s) {
				if($s->field == 'include' || $s->field == 'status' || $s->field == 'check_access') {
					$selectors->add(clone $s);
				}
			}
		}
		
		// special handling for page references, detect if parent or template is defined, 
		// and add it to the selector if available. This makes it faster. 
		if(!$hasTemplate || !$hasParent) {

			$fields = is_array($selector->field) ? $selector->field : array($selector->field);
			$templates = array();
			$parents = array();
			$findSelector = '';
			
			foreach($fields as $fieldName) {
				if(strpos($fieldName, '.') !== false) {
					/** @noinspection PhpUnusedLocalVariableInspection */
					list($unused, $fieldName) = explode('.', $fieldName);
				}
				$field = $this->fields->get($fieldName);
				if(!$field) continue;
				if(!$hasTemplate && ($field->get('template_id') || $field->get('template_ids'))) {
					$templateIds = FieldtypePage::getTemplateIDs($field); 
					if(count($templateIds)) {
						$templates = array_merge($templates, $templateIds);
					}
				}
				if(!$hasParent) {
					/** @var int|null $parentId */
					$parentId = $field->get('parent_id');
					if($parentId) {
						if($this->isRepeaterFieldtype($field->type)) {
							// repeater items not stored directly under parent_id, but as another parent under parent_id. 
							// so we use has_parent instead here
							$selectors->prepend(new SelectorEqual('has_parent', $parentId));
						} else {
							// direct parent: FieldtypePage or similar
							$parents[] = (int) $parentId;
						}
					}
				}
				if($field->get('findPagesSelector') && count($fields) == 1) {
					$findSelector = $field->get('findPagesSelector');
				}
			}
			
			if(count($templates)) $selectors->prepend(new SelectorEqual('template', $templates));
			if(count($parents)) $selectors->prepend(new SelectorEqual('parent_id', $parents));
			
			if($findSelector) {
				foreach(new Selectors($findSelector) as $s) {
					// add everything from findSelector, except for dynamic/runtime 'page.[something]' vars
					if(strpos($s->getField('string'), 'page.') === 0 || strpos($s->getValue('string'), 'page.') === 0) continue;
					$selectors->append($s);
				}
			}
		}
	
		/** @var PageFinder $pageFinder */
		$pageFinder = $this->wire(new PageFinder());
		$ids = $pageFinder->findIDs($selectors);
		$fieldNames = $selector->fields;
		$fieldName = reset($fieldNames);
		$natives = array('parent', 'parent.id', 'parent_id', 'children', 'children.id', 'child', 'child.id');
		
		// populate selector value with array of page IDs
		if(count($ids) == 0) {
			// subselector resulted in 0 matches
			// force non-match for this subselector by populating 'id' subfield to field name(s)
			$fieldNames = array();
			foreach($selector->fields as $key => $fieldName) {
				if(strpos($fieldName, '.') !== false) {
					// reduce fieldName to just field name without subfield name
					/** @noinspection PhpUnusedLocalVariableInspection */
					list($fieldName, $subname) = explode('.', $fieldName); // subname intentionally unused
				}
				$field = $this->isPageField($fieldName);
				if(is_string($field) && in_array($field, $natives)) {
					// prevent matching something like parent_id=0, as that would match homepage
					$fieldName = 'id';
				} else if($field) {
					$fieldName .= '.id';
				} else {
					// non-Page value field
					$selector->forceMatch = false;
				}
				$fieldNames[$key] = $fieldName;
			}
			$selector->fields = $fieldNames;
			$selector->value = 0;
			
		} else if(in_array($fieldName, $natives)) {
			// i.e. parent, parent_id, children, etc
			$selector->value = count($ids) > 1 ? $ids : reset($ids);
			
		} else {
			$isPageField = $this->isPageField($fieldName, true);
			if($isPageField) {
				// FieldtypePage fields can use the "," separation syntax for speed optimization
				$selector->value = count($ids) > 1 ? implode(',', $ids) : reset($ids);
			} else {
				// otherwise use array
				$selector->value = count($ids) > 1 ? $ids : reset($ids);
			}
		}
		
		$selector->quote = '';
	}

	/**
	 * Given one or more selectors, create the SQL query for finding pages.
	 *
	 * @TODO split this method up into more parts, it's too long
	 *
	 * @param Selectors $selectors Array of selectors.
	 * @param array $options 
	 * @return DatabaseQuerySelect 
	 * @throws PageFinderSyntaxException
	 *
	 */
	protected function ___getQuery($selectors, array $options) {

		$where = '';
		$fieldCnt = array(); // counts number of instances for each field to ensure unique table aliases for ANDs on the same field
		$lastSelector = null; 
		$sortSelectors = array(); // selector containing 'sort=', which gets added last
		$subqueries = array();
		$joins = array();
		$database = $this->database;
		$autojoinTables = array();
		$this->preProcessSelectors($selectors, $options);
		$this->numAltOperators = 0;
		
		/** @var DatabaseQuerySelect $query */
		$query = $this->wire(new DatabaseQuerySelect());
		if(!empty($options['bindOptions'])) {
			foreach($options['bindOptions'] as $k => $v) $query->bindOption($k, $v);
		}
	
		if($options['returnAllCols']) {
			$opts = $this->defaultOptions['returnAllColsOptions'];
			if(!empty($options['returnAllColsOptions'])) $opts = array_merge($opts, $options['returnAllColsOptions']);
			$columns = array('pages.*'); 
			if($opts['unixTimestamps']) {
				$columns[] = 'UNIX_TIMESTAMP(pages.created) AS created';
				$columns[] = 'UNIX_TIMESTAMP(pages.modified) AS modified';
				$columns[] = 'UNIX_TIMESTAMP(pages.published) AS published';
			}
			if($opts['joinSortfield']) {
				$columns[] = 'pages_sortfields.sortfield AS sortfield';
				$query->leftjoin('pages_sortfields ON pages_sortfields.pages_id=pages.id');
			}
			if($opts['getNumChildren']) {
				$query->select('(SELECT COUNT(*) FROM pages AS children WHERE children.parent_id=pages.id) AS numChildren');
			}
			if($opts['joinPath']) {
				if(!$this->wire()->modules->isInstalled('PagePaths')) {
					throw new PageFinderException('Requested option for URL or path (joinPath) requires the PagePaths module be installed'); 
				}
				$columns[] = 'pages_paths.path AS path';
				$query->leftjoin('pages_paths ON pages_paths.pages_id=pages.id'); 
			}
			if(!empty($opts['joinFields'])) {
				$this->pageArrayData['joinFields'] = array(); // identify whether each field supported autojoin
				foreach($opts['joinFields'] as $joinField) {
					$joinField = $this->fields->get($joinField);
					if(!$joinField instanceof Field) continue;
					$joinTable = $database->escapeTable($joinField->getTable());
					if(!$joinTable || !$joinField->type) continue;
					if($joinField->type->getLoadQueryAutojoin($joinField, $query)) {
						$autojoinTables[$joinTable] = $joinTable; // added at end if not already joined
						$this->pageArrayData['joinFields'][$joinField->name] = true;
					} else {
						// fieldtype does not support autojoin
						$this->pageArrayData['joinFields'][$joinField->name] = false;
					}
				}
			}
		} else if($options['returnVerbose']) {
			$columns = array('pages.id', 'pages.parent_id', 'pages.templates_id');
		} else if($options['returnParentIDs']) {
			$columns = array('pages.parent_id AS id');
		} else if($options['returnTemplateIDs']) {
			$columns = array('pages.id', 'pages.templates_id');
		} else {
			$columns = array('pages.id');
		}

		$query->select($columns);
		$query->from("pages"); 
		$query->groupby($options['returnParentIDs'] ? 'pages.parent_id' : 'pages.id');
	
		$this->getQueryStartLimit($query);

		foreach($selectors as $selector) {
			
			/** @var Selector $selector */

			if(is_null($lastSelector)) $lastSelector = $selector;
			$selector = $this->preProcessSelector($selector, $selectors, $options);
			if(!$selector || $selector->forceMatch === true) continue;
			if($selector->forceMatch === false) {
				$query->where("1>2"); // force non match
				continue;
			}
			
			$fields = $selector->fields(); 
			$group = $selector->group; // i.e. @field
			if(count($fields) > 1) $fields = $this->arrangeFields($fields); 
			$field1 = reset($fields); // first field including optional subfield
			$this->numAltOperators += count($selector->altOperators);

			// TODO Make native fields and path/url multi-field and multi-value aware
			if($field1 === 'sort' && $selector->operator === '=') {
				$sortSelectors[] = $selector;
				continue;
			
			} else if($field1 === 'sort' || $field1 === 'page.sort') {
				if(!in_array($selector->operator, array('=', '!=', '<', '>', '>=', '<='))) {
					$this->syntaxError("Property '$field1' may not use operator: $selector->operator");
				}
				$selector->field = 'sort';
				$selector->value = (int) $selector->value();
				$this->getQueryNativeField($query, $selector, array('sort'), $options, $selectors); 
				continue;

			} else if($field1 === 'limit' || $field1 === 'start') {
				continue;

			} else if($field1 === 'path' || $field1 === 'url') {
				$this->getQueryJoinPath($query, $selector); 
				continue; 

			} else if($field1 === 'has_parent' || $field1 === 'hasParent') {
				$this->getQueryHasParent($query, $selector); 
				continue; 

			} else if($field1 === 'num_children' || $field1 === 'numChildren' || $field1 === 'children.count') { 
				$this->getQueryNumChildren($query, $selector); 
				continue; 

			} else if($this->hasNativeFieldName($fields)) {
				$this->getQueryNativeField($query, $selector, $fields, $options, $selectors); 
				continue;
			}
			
			// where SQL specific to the foreach() of fields below, if needed. 
			// in this case only used by internally generated shortcuts like the blank value condition
			$whereFields = '';	
			$whereFieldsType = 'AND';
			
			foreach($fields as $fieldName) {

				// if a specific DB field from the table has been specified, then get it, otherwise assume 'data'
				if(strpos($fieldName, '.')) {
					// if fieldName is "a.b.c" $subfields (plural) retains "b.c" while $subfield is just "b"
					list($fieldName, $subfields) = explode('.', $fieldName, 2);
					if(strpos($subfields, '.')) {
						list($subfield) = explode('.', $subfields); // just the first
					} else {
						$subfield = $subfields;
					}
				} else {
					$subfields = 'data';
					$subfield = 'data';
				}
				
				$field = $this->fields->get($fieldName);

				if(!$field) {
					// field does not exist, see if it can be processed in some other way
					$field = $this->getQueryUnknownField($fieldName, array(
						'subfield' => $subfield,
						'subfields' => $subfields, 
						'fields' => $fields, 
						'query' => $query, 
						'selector' => $selector, 
						'selectors' => $selectors
					));
					if($field === true) {
						// true indicates the hook modified query to handle this (or ignore it), and should move to next field
						continue;
					} else if($field instanceof Field) {
						// hook has mapped it to a field and processing of field should proceed
					} else if($field) {
						// mapped it to an API var or something else where we need not continue processing $field or $fields
						break;
					} else {
						$this->syntaxError("Field does not exist: $fieldName");
					}
				}

				// keep track of number of times this table name has appeared in the query
				if(isset($fieldCnt[$field->table])) {
					$fieldCnt[$field->table]++;
				} else {
					$fieldCnt[$field->table] = 0;
				}

				// use actual table name if first instance, if second instance of table then add a number at the end
				$tableAlias = $field->table . ($fieldCnt[$field->table] ? $fieldCnt[$field->table] : '');
				$tableAlias = $database->escapeTable($tableAlias);

				$join = '';
				$joinType = '';
				$numEmptyValues = 0; 
				$valueArray = $selector->values(true); 
				$fieldtype = $field->type; 
				$operator = $selector->operator;
				if($operator === '<>') $operator = '!=';

				foreach($valueArray as $value) {

					// shortcut for blank value condition: this ensures that NULL/non-existence is considered blank
					// without this section the query would still work, but a blank value must actually be present in the field
					$isEmptyValue = $fieldtype->isEmptyValue($field, $value);
					$useEmpty = $isEmptyValue || $operator[0] === '<' || ((int) $value < 0 && $operator[0] === '>') 
						|| ($operator === '!=' && $isEmptyValue === false);	
					if($useEmpty && strpos($subfield, 'data') === 0) { // && !$fieldtype instanceof FieldtypeMulti) {
						if($isEmptyValue) $numEmptyValues++;
						if(in_array($operator, array('=', '!=', '<', '<=', '>', '>='))) {
							// we only accommodate this optimization for single-value selectors...
							if($this->whereEmptyValuePossible($field, $subfield, $selector, $query, $value, $whereFields)) {
								if(count($valueArray) > 1) {
									if($operator == '=') $whereFieldsType = 'OR';
								} else {
									$fieldCnt[$field->table]--;
									if($fieldCnt[$field->table] < 1) unset($fieldCnt[$field->table]);
								}
								continue;
							}
						}
					}

					/** @var DatabaseQuerySelect $q */
					if(isset($subqueries[$tableAlias])) {
						$q = $subqueries[$tableAlias];
					} else {
						$q = $this->wire(new DatabaseQuerySelect());
						// $subqueries[$tableAlias] = $q;
					}

					/** @var PageFinderDatabaseQuerySelect $q */
					$q->set('field', $field); // original field if required by the fieldtype
					$q->set('group', $group); // original group of the field, if required by the fieldtype
					$q->set('selector', $selector); // original selector if required by the fieldtype
					$q->set('selectors', $selectors); // original selectors (all) if required by the fieldtype
					$q->set('parentQuery', $query);
					$q->set('pageFinder', $this);
					$q->set('joinType', $joinType);
					$q->bindOption('global', true); // ensures bound value key are globally unique
					$q->bindOption('prefix', 'pf'); // pf=PageFinder
			
					/*	@todo To be implemented after 3.0.245
					if(strpos($subfields, 'JSON.') === 0) {
						if($this->getMatchQueryJSON($q, $tableAlias, $subfields, $selector->operator, $value)) {
							continue;
						}
					}
					*/
						
					$q = $fieldtype->getMatchQuery($q, $tableAlias, $subfield, $selector->operator, $value);
					$q->copyTo($query, array('select', 'join', 'leftjoin', 'orderby', 'groupby')); 
					$q->copyBindValuesTo($query);
					
					if($q->joinType && $q->joinType != $joinType) {
						$joinType = strtolower((string) $q->joinType);
					}

					if(count($q->where)) { 
						// $and = $selector->not ? "AND NOT" : "AND";
						$and = "AND"; /// moved NOT condition to entire generated $sql
						$sql = ''; 
						foreach($q->where as $w) $sql .= $sql ? "$and $w " : "$w ";
						$sql = "($sql) "; 

						if($selector->operator == '!=') {
							$join .= ($join ? "\n\t\tAND $sql " : $sql); 

						} else if($selector->not) {
							$sql = "((NOT $sql) OR ($tableAlias.pages_id IS NULL))";
							$join .= ($join ? "\n\t\tAND $sql " : $sql);

						} else { 
							$join .= ($join ? "\n\t\tOR $sql " : $sql); 
						}
					}
				}

				if($join) {
					if($joinType === 'leftjoin' 
						|| count($fields) > 1 
						|| !empty($options['startAfterID']) || !empty($options['stopBeforeID'])
						|| (count($valueArray) > 1 && $numEmptyValues > 0)
						|| ($subfield == 'count' && !$this->isRepeaterFieldtype($field->type))
						|| ($selector->not && $selector->operator != '!=') 
						|| $selector->operator == '!=') {
						// join should instead be a leftjoin

						$joinType = 'leftjoin';

						if($where) {
							$whereType = $lastSelector->str == $selector->str ? "OR" : ") AND (";
							$where .= "\n\t$whereType ($join) ";
						} else {
							$where .= "($join) ";
						}
						if($selector->not) {
							// removes condition from join, but ensures we still have a $join
							$join = '1=1'; 
						}
					} else {
						$joinType = 'join';
					}

					// we compile the joins after going through all the selectors, so that we can 
					// match up conditions to the same tables
					if(isset($joins[$tableAlias])) {
						$joins[$tableAlias]['join'] .= " AND ($join) ";
					} else {
						$joins[$tableAlias] = array(
							'joinType' => $joinType, 
							'table' => $field->table, 
							'tableAlias' => $tableAlias, 	
							'join' => "($join)", 
						);
					}

				}

				$lastSelector = $selector; 	
			} // fields
			
			if(strlen($whereFields)) {
				if(strlen($where)) {
					$where = "($where) $whereFieldsType ($whereFields)";
				} else {
					$where .= "($whereFields)";
				}
			}
		
		} // selectors

		if($where) $query->where("($where)");
		
		$this->getQueryAllowedTemplates($query, $options); 

		// complete the joins, matching up any conditions for the same table
		foreach($joins as $j) {
			$joinType = $j['joinType']; 
			$query->$joinType("$j[table] AS $j[tableAlias] ON $j[tableAlias].pages_id=pages.id AND ($j[join])"); 
		}
		
		foreach($autojoinTables as $table) {
			if(isset($fieldCnt[$table])) continue; // already joined
			$query->leftjoin("$table ON $table.pages_id=pages.id");
		}
	
		if(count($sortSelectors)) {
			foreach(array_reverse($sortSelectors) as $s) {
				$this->getQuerySortSelector($query, $s);
			}
		}

		if((!empty($options['startAfterID']) || !empty($options['stopBeforeID'])) && count($query->where)) {
			$wheres = array('(' . implode(' AND ', $query->where) . ')');
			$query->set('where', array());
			foreach(array('startAfterID', 'stopBeforeID') as $key) {
				if(empty($options[$key])) continue;
				$bindKey = $query->bindValueGetKey($options[$key], \PDO::PARAM_INT);
				array_unshift($wheres, "pages.id=$bindKey");
			}
			$query->where(implode("\n OR ", $wheres));
		}
		
		$this->postProcessQuery($query); 
		$this->finalSelectors = $selectors;
		
		return $query; 
	}

	/**
	 * Get match query when data is stored in a JSON DB column (future use)
	 * 
	 * @param PageFinderDatabaseQuerySelect DatabaseQuerySelect $q
	 * @param string $tableAlias
	 * @param string $subfields
	 * @param string $operator
	 * @param string|int|array $value
	 * @return bool
	 * 
	 */
	protected function getMatchQueryJSON(DatabaseQuerySelect $q, $tableAlias, $subfields, $operator, $value) {
		// @todo to be implemented after 3.0.245
		return false;
	}

	/**
	 * Post process a DatabaseQuerySelect for page finder 
	 * 
	 * @param DatabaseQuerySelect $parentQuery
	 * @throws WireException
	 * 
	 */
	protected function postProcessQuery($parentQuery) {
		
		if(count($this->extraOrSelectors)) {
			// there were embedded OR selectors where one of them must match
			// i.e. id>0, field=(selector string), field=(selector string)
			// in the above example at least one 'field' must match
			// the 'field' portion is used only as a group name and isn't 
			// actually used as part of the resulting query or than to know
			// what groups should be OR'd together

			$sqls = array();
			foreach($this->extraOrSelectors as /* $groupName => */ $selectorGroup) {
				$n = 0;
				$sql = "\tpages.id IN (\n";
				foreach($selectorGroup as $selectors) {
					$pageFinder = $this->wire(new PageFinder());	
					/** @var DatabaseQuerySelect $query */
					$query = $pageFinder->find($selectors, array(
						'returnQuery' => true, 
						'returnVerbose' => false,
						'findAll' => true,
						'bindOptions' => array(
							'prefix' => 'pfor', 
							'global' => true, 
						)
					));
					if($n > 0) $sql .= " \n\tOR pages.id IN (\n";
					$query->set('groupby', array());
					$query->set('select', array('pages.id')); 
					$query->set('orderby', array()); 
					$sql .= tabIndent("\t\t" . $query->getQuery() . "\n)", 2);
					$query->copyBindValuesTo($parentQuery, array('inSQL' => $sql));
					$n++;
				}
				$sqls[] = $sql;
			}
			if(count($sqls)) {
				$sql = implode(" \n) AND (\n ", $sqls);
				$parentQuery->where("(\n$sql\n)"); 
			}
		}
	
		/* Possibly move existing subselectors to work like this rather than how they currently are
		if(count($this->extraSubSelectors)) {
			$sqls = array();
			foreach($this->extraSubSelectors as $fieldName => $selectorGroup) {
				$fieldName = $this->wire('database')->escapeCol($fieldName); 
				$n = 0;
				$sql = "\tpages.id IN (\n";
				foreach($selectorGroup as $selectors) {
					$pageFinder = new PageFinder();
					$query = $pageFinder->find($selectors, array('returnQuery' => true, 'returnVerbose' => false));
					if($n > 0) $sql .= " \n\tAND pages.id IN (\n";
					$query->set('groupby', array());
					$query->set('select', array('pages.id'));
					$query->set('orderby', array());
					// foreach($this->nativeWheres as $where) $query->where($where); 
					$sql .= tabIndent("\t\t" . $query->getQuery() . "\n)", 2);
					$n++;
				}
				$sqls[] = $sql;
			}
			if(count($sqls)) {
				$sql = implode(" \n) AND (\n ", $sqls);
				$parentQuery->where("(\n$sql\n)");
			}
		}
		*/
	}

	/**
	 * Generate SQL and modify $query for situations where it should be possible to match empty values
	 * 
	 * This can include equals/not-equals with blank or 0, as well as greater/less-than searches that
	 * can potentially match blank or 0. 
	 * 
	 * @param Field $field
	 * @param string $col
	 * @param Selector $selector
	 * @param DatabaseQuerySelect $query
	 * @param string $value The value presumed to be blank (passed the empty() test)
	 * @param string $where SQL where string that will be modified/appended
	 * @return bool Whether or not the query was handled and modified
	 * 
	 */
	protected function whereEmptyValuePossible(Field $field, $col, $selector, $query, $value, &$where) {
		
		
		// look in table that has no pages_id relation back to pages, using the LEFT JOIN / IS NULL trick
		// OR check for blank value as defined by the fieldtype
		
		static $tableCnt = 0;
	
		$ft = $field->type;
		$operator = $selector->operator; 
		$database = $this->database;
		$table = $database->escapeTable($field->table);
		$tableAlias = $table . "__blank" . (++$tableCnt);
		$blankValue = $ft->getBlankValue(new NullPage(), $field);
		$blankIsObject = is_object($blankValue); 
		$whereType = 'OR';
		$sql = '';
		$operators = array(
			'=' => '!=', 
			'!=' => '=', 
			'<' => '>=', 
			'<=' => '>',
			'>' => '<=', 
			'>=' => '<'
		);
		
		if($blankIsObject) $blankValue = '';
		if(!isset($operators[$operator])) return false; 
		if($selector->not) $operator = $operators[$operator]; // reverse
		
		if($col !== 'data' && !ctype_alnum($col)) {
			// check for unsupported column
			if(!ctype_alnum(str_replace('_', '', $col))) return false; 
		}

		// ask Fieldtype if it would prefer to handle matching this empty value selector
		if($ft->isEmptyValue($field, $selector)) {
			// fieldtype will handle matching the selector in its getMatchQuery
			return false;

		} else if(($operator === '=' || $operator === '!=') && $ft->isEmptyValue($field, $value) && $ft->isEmptyValue($field, '0000-00-00')) {
			// matching empty in date, datetime, timestamp column with equals or not-equals condition
			// non-presence of row is required in order to match empty/blank (in MySQL 8.x)
			$is = $operator === '=' ? 'IS' : 'IS NOT';
			$sql = "$tableAlias.pages_id $is NULL ";

		} else if($operator === '=') {
			// equals
			// non-presence of row is equal to value being blank
			$bindKey = $query->bindValueGetKey($blankValue);
			if($ft->isEmptyValue($field, $value)) {
				// matching an empty value: null or literal empty value
				$sql = "$tableAlias.$col IS NULL OR ($tableAlias.$col=$bindKey";
				if($value === '' && !$ft->isEmptyValue($field, '0') && $field->get('zeroNotEmpty')) {
					// MySQL blank string will also match zero (0) in some cases, so we prevent that here
					// @todo remove the 'zeroNotEmpty' condition for test on dev as it limits to specific fieldtypes is likely unnecessary
					$sql .= " AND $tableAlias.$col!='0'";
				}
			} else {
				// matching a non-empty value
				$sql = "($tableAlias.$col=$bindKey";
			}
			/*
			if($value !== "0" && $blankValue !== "0" && !$ft->isEmptyValue($field, "0")) {
				// if zero is not considered an empty value, exclude it from matching
				// if the search isn't specifically for a "0"
				$sql .= " AND $tableAlias.$col!='0'";
			}
			*/
			$sql .= ")";
			
		} else if($operator === '!=' || $operator === '<>') {
			// not equals
			$whereType = count($selector->fields()) > 1 && $ft->isEmptyValue($field, $value) ? 'OR' : 'AND';
			// alternate and technically more consistent behavior, but doesn't seem useful:
			// $whereType = count($selector->fields()) > 1 ? 'OR' : 'AND'; 
			$zeroIsEmpty = $ft->isEmptyValue($field, "0"); 
			$zeroIsNotEmpty = !$zeroIsEmpty;
			$value = (string) $value;
			$blankValue = (string) $blankValue;
			if($value === '') {
				// match present rows that do not contain a blank string (or 0, when applicable)
				$sql = "$tableAlias.$col IS NOT NULL AND ($tableAlias.$col!=''";
				if($zeroIsEmpty) {
					$sql .= " AND $tableAlias.$col!='0'";
				} else {
					$sql .= " OR $tableAlias.$col='0'";
				}
				$sql .= ')';
				
			} else if($value === "0" && $zeroIsNotEmpty) {
				// may match non-rows (no value present) or row with value=0
				$sql = "$tableAlias.$col IS NULL OR $tableAlias.$col!='0'";

			} else if($value !== "0" && $zeroIsEmpty) {
				// match all rows except empty and those having specific non-empty value
				$bindKey = $query->bindValueGetKey($value);
				$sql = "$tableAlias.$col IS NULL OR $tableAlias.$col!=$bindKey";
				
			} else if($blankIsObject) {
				// match all present rows
				$sql = "$tableAlias.$col IS NOT NULL";
				
			} else {
				// match all present rows that are not blankValue and not given blank value...
				$bindKeyBlank = $query->bindValueGetKey($blankValue);
				$bindKeyValue = $query->bindValueGetKey($value);
				$sql = "$tableAlias.$col IS NOT NULL AND $tableAlias.$col!=$bindKeyValue AND ($tableAlias.$col!=$bindKeyBlank";
				if($zeroIsNotEmpty && $blankValue !== "0" && $value !== "0") {
					// ...allow for 0 to match also if 0 is not considered empty value
					$sql .= " OR $tableAlias.$col='0'";
				}
				$sql .= ")";
			}
			if($ft instanceof FieldtypeMulti && !$ft->isEmptyValue($field, $value)) {
				// when a multi-row field is in use, exclude match when any of the rows contain $value
				$tableMulti = $table . "__multi$tableCnt";
				$bindKey = $query->bindValueGetKey($value);
				$query->leftjoin("$table AS $tableMulti ON $tableMulti.pages_id=pages.id AND $tableMulti.$col=$bindKey");
				$query->where("$tableMulti.$col IS NULL");
			}

		} else if($operator == '<' || $operator == '<=') {
			// less than 
			if($value > 0 && $ft->isEmptyValue($field, "0")) {
				// non-rows can be included as counting for 0
				$bindKey = $query->bindValueGetKey($value);
				$sql = "$tableAlias.$col IS NULL OR $tableAlias.$col$operator$bindKey";
			} else {
				// we won't handle it here
				return false; 
			}
		} else if($operator == '>' || $operator == '>=') {
			if($value < 0 && $ft->isEmptyValue($field, "0")) {
				// non-rows can be included as counting for 0
				$bindKey = $query->bindValueGetKey($value);
				$sql = "$tableAlias.$col IS NULL OR $tableAlias.$col$operator$bindKey";
			} else {
				// we won't handle it here
				return false;
			}
		}

		$query->leftjoin("$table AS $tableAlias ON $tableAlias.pages_id=pages.id");
		$where .= strlen($where) ?  " $whereType ($sql)" : "($sql)";
		
		return true; 
	}

	/**
	 * Determine which templates the user is allowed to view
	 * 
	 * @param DatabaseQuerySelect $query
	 * @param array $options
	 *
	 */
	protected function getQueryAllowedTemplates(DatabaseQuerySelect $query, $options) {
		if($options) {}
		
		// if access checking is disabled then skip this
		if(!$this->checkAccess) return;

		// no need to perform this checking if the user is superuser
		$user = $this->wire()->user;
		if($user->isSuperuser()) return;

		static $where = null;
		static $where2 = null;
		static $leftjoin = null;
		static $cacheUserID = null;
		
		if($cacheUserID !== $user->id) {
			// clear cached values
			$where = null;
			$where2 = null;
			$leftjoin = null;
			$cacheUserID = $user->id;
		}
		
		$hasWhereHook = $this->wire()->hooks->isHooked('PageFinder::getQueryAllowedTemplatesWhere()');

		// if a template was specified in the search, then we won't attempt to verify access
		// if($this->templates_id) return; 

		// if findOne optimization is set, we don't check template access
		// if($options['findOne']) return;

		// if we've already figured out this part from a previous query, then use it
		if(!is_null($where)) {
			if($hasWhereHook) {
				$where = $this->getQueryAllowedTemplatesWhere($query, $where);
				$where2 = $this->getQueryAllowedTemplatesWhere($query, $where2);
			}
			$query->where($where);
			$query->where($where2); 
			$query->leftjoin($leftjoin);
			return;
		}

		// array of templates they ARE allowed to access
		$yesTemplates = array();

		// array of templates they are NOT allowed to access
		$noTemplates = array();

		$guestRoleID = $this->config->guestUserRolePageID; 
		$cacheUserID = $user->id;

		if($user->isGuest()) {
			// guest 
			foreach($this->templates as $template) {
				/** @var Template $template */
				if($template->guestSearchable || !$template->useRoles) {
					$yesTemplates[$template->id] = $template;
					continue; 
				}
				foreach($template->roles as $role) {
					if($role->id != $guestRoleID) continue;
					$yesTemplates[$template->id] = $template;
					break;
				}
			}

		} else {
			// other logged-in user
			$userRoleIDs = array();
			foreach($user->roles as $role) {
				$userRoleIDs[] = $role->id; 
			}

			foreach($this->templates as $template) {
				/** @var Template $template */
				if($template->guestSearchable || !$template->useRoles) {
					$yesTemplates[$template->id] = $template;
					continue; 
				}
				foreach($template->roles as $role) {
					if($role->id != $guestRoleID && !in_array($role->id, $userRoleIDs)) continue; 
					$yesTemplates[$template->id] = $template; 	
					break;
				}
			}
		}

		// determine which templates the user is not allowed to access
		foreach($this->templates as $template) {
			/** @var Template $template */
			if(!isset($yesTemplates[$template->id])) $noTemplates[$template->id] = $template;
		}

		$in = '';
		$yesCnt = count($yesTemplates); 
		$noCnt = count($noTemplates); 

		if($noCnt) {

			// pages_access lists pages that are inheriting access from others. 
			// join in any pages that are using any of the noTemplates to get their access. 
			// we want pages_access.pages_id to be NULL, which indicates that none of the 
			// noTemplates was joined, and the page is accessible to the user. 
			
			$leftjoin = "pages_access ON (pages_access.pages_id=pages.id AND pages_access.templates_id IN(";
			foreach($noTemplates as $template) $leftjoin .= ((int) $template->id) . ",";
			$leftjoin = rtrim($leftjoin, ",") . "))";
			$query->leftjoin($leftjoin);
			$where2 = "pages_access.pages_id IS NULL"; 
			if($hasWhereHook) $where2 = $this->getQueryAllowedTemplatesWhere($query, $where2);
			$query->where($where2); 
		}

		if($noCnt > 0 && $noCnt < $yesCnt) {
			$templates = $noTemplates; 
			$yes = false; 
		} else {
			$templates = $yesTemplates; 
			$yes = true;
		}
	
		foreach($templates as $template) {
			$in .= ((int) $template->id) . ",";
		}

		$in = rtrim($in, ","); 
		$where = "pages.templates_id ";

		if($in && $yes) {
			$where .= "IN($in)";
		} else if($in) {
		 	$where .= "NOT IN($in)";
		} else {
			$where = "<0"; // no match possible
		}

		// allow for hooks to modify or add to the WHERE conditions
		if($hasWhereHook) $where = $this->getQueryAllowedTemplatesWhere($query, $where);
		$query->where($where);	
	}

	/**
	 * Method that allows external hooks to add to or modify the access control WHERE conditions 
	 * 
	 * Called only if it's hooked. To utilize it, modify the $where argument in a BEFORE hook
	 * or the $event->return in an AFTER hook.
	 * 
	 * @param DatabaseQuerySelect $query
	 * @param string $where SQL string for WHERE statement, not including the actual "WHERE"
	 * @return string
	 */
	protected function ___getQueryAllowedTemplatesWhere(DatabaseQuerySelect $query, $where) {
		return $where;
	}

	protected function getQuerySortSelector(DatabaseQuerySelect $query, Selector $selector) {

		// $field = is_array($selector->field) ? reset($selector->field) : $selector->field; 
		$values = is_array($selector->value) ? $selector->value : array($selector->value); 	
		$fields = $this->fields;
		$pages = $this->pages;
		$database = $this->database;
		$user = $this->wire()->user; 
		$language = $this->languages && $user && $user->language ? $user->language : null;
	
		// support `sort=a|b|c` in correct order (because orderby prepend used below)
		if(count($values) > 1) $values = array_reverse($values); 
		
		foreach($values as $value) {

			$fc = substr($value, 0, 1); 
			$lc = substr($value, -1);
			$descending = $fc == '-' || $lc == '-';
			$value = trim($value, "-+"); 
			$subValue = '';
			// $terValue = ''; // not currently used, here for future use
			
			if($this->lastOptions['reverseSort']) $descending = !$descending;

			if(strpos($value, ".")) {
				list($value, $subValue) = explode(".", $value, 2); // i.e. some_field.title
				if(strpos($subValue, ".")) {
					list($subValue, $terValue) = explode(".", $subValue, 2);
					$terValue = $this->sanitizer->fieldName($terValue);
					if(strpos($terValue, ".")) $this->syntaxError("$value.$subValue.$terValue not supported");
				}
				$subValue = $this->sanitizer->fieldName($subValue);
			}
			$value = $this->sanitizer->fieldName($value);
			
			if($value == 'parent' && $subValue == 'path') $subValue = 'name'; // path not supported, substitute name

			if($value == 'random') { 
				$value = 'RAND()';

			} else if($value == 'num_children' || $value == 'numChildren' || ($value == 'children' && $subValue == 'count')) {
				// sort by quantity of children
				$value = $this->getQueryNumChildren($query, $this->wire(new SelectorGreaterThan('num_children', "-1")));

			} else if($value == 'parent' && ($subValue == 'num_children' || $subValue == 'numChildren' || $subValue == 'children')) {
				throw new WireException("Sort by parent.num_children is not currently supported");

			} else if($value == 'parent' && (empty($subValue) || $pages->loader()->isNativeColumn($subValue))) {
				// sort by parent native field only
				if(empty($subValue)) $subValue = 'name';
				$subValue = $database->escapeCol($subValue);
				$tableAlias = "_sort_parent_$subValue";
				$query->join("pages AS $tableAlias ON $tableAlias.id=pages.parent_id");
				$value = "$tableAlias.$subValue";

			} else if($value == 'template') { 
				// sort by template
				$tableAlias = $database->escapeTable("_sort_templates" . ($subValue ? "_$subValue" : '')); 
				$query->join("templates AS $tableAlias ON $tableAlias.id=pages.templates_id"); 
				$value = "$tableAlias." . ($subValue ? $database->escapeCol($subValue) : "name"); 

			} else if($fields->isNative($value) && !$subValue && $pages->loader()->isNativeColumn($value)) {
				// sort by a native field (with no subfield)
				if($value == 'name' && $language && !$language->isDefault()  && $this->supportsLanguagePageNames()) {
					// substitute language-specific name field when LanguageSupportPageNames is active and language is not default
					$value = "if(pages.name$language!='', pages.name$language, pages.name)";
				} else {
					$value = "pages." . $database->escapeCol($value);
				}
				
			} else if(($value === 'path' || $value === 'url') && $this->wire()->modules->isInstalled('PagePaths')) {
				static $pathN = 0;
				$pathN++;
				$pathsTable = "_sort_pages_paths$pathN";
				if($language && !$language->isDefault() && $this->supportsLanguagePageNames()) {
					$query->leftjoin("pages_paths AS $pathsTable ON $pathsTable.pages_id=pages.id AND $pathsTable.language_id=0");
					$lid = (int) $language->id;
					$asc = $descending ? 'DESC' : 'ASC';
					$pathsLangTable = $pathsTable . "_$lid";
					$s = "pages_paths AS $pathsLangTable ON $pathsLangTable.pages_id=pages.id AND $pathsLangTable.language_id=$lid";
					$query->leftjoin($s);
					$query->orderby("if($pathsLangTable.pages_id IS NULL, $pathsTable.path, $pathsLangTable.path) $asc");
					$value = false;
				} else {
					$query->leftjoin("pages_paths AS $pathsTable ON $pathsTable.pages_id=pages.id");
					$value = "$pathsTable.path";
				}

			} else {
				// sort by custom field, or parent w/custom field
				
				if($value == 'parent') {
					$useParent = true;
					$value = $subValue ? $subValue : 'title'; // needs a custom field, not "name"
					$subValue = 'data';
					$idColumn = 'parent_id';
				} else {
					$useParent = false;
					$idColumn = 'id';
				}
				
				$field = $fields->get($value);
				if(!$field) {
					// unknown field
					continue;
				}
				
				$fieldName = $database->escapeCol($field->name); 
				$subValue = $database->escapeCol($subValue);
				$tableAlias = $useParent ? "_sort_parent_$fieldName" : "_sort_$fieldName";
				if($subValue) $tableAlias .= "_$subValue";
				$table = $database->escapeTable($field->table);
				if($field->type instanceof FieldtypePage) {
					$blankValue = new PageArray();
				} else {
					$blankValue = $field->type->getBlankValue($this->pages->newNullPage(), $field);
				}

				$query->leftjoin("$table AS $tableAlias ON $tableAlias.pages_id=pages.$idColumn");
				
				$customValue = $field->type->getMatchQuerySort($field, $query, $tableAlias, $subValue, $descending);
				
				if(!empty($customValue)) {
					// Fieldtype handled it: boolean true (handled by Fieldtype) or string to add to orderby
					if(is_string($customValue)) $query->orderby($customValue, true);
					$value = false;

				} else if($subValue === 'count') {
					if($this->isRepeaterFieldtype($field->type)) {
						// repeaters have a native count column that can be used for sorting
						$value = "$tableAlias.count";
					} else {
						// sort by quantity of items
						$value = "COUNT($tableAlias.data)";
					}

				} else if($blankValue instanceof PageArray || $blankValue instanceof Page) {
					// If it's a FieldtypePage, then data isn't worth sorting on because it just contains an ID to the page
					// so we also join the page and sort on it's name instead of the field's "data" field.
					if(!$subValue) $subValue = 'name';
					$tableAlias2 = "_sort_" . ($useParent ? 'parent' : 'page') . "_$fieldName" . ($subValue ? "_$subValue" : '');
				
					if($this->fields->isNative($subValue) && $pages->loader()->isNativeColumn($subValue)) {
						$query->leftjoin("pages AS $tableAlias2 ON $tableAlias.data=$tableAlias2.$idColumn");
						$value = "$tableAlias2.$subValue";
						if($subValue == 'name' && $language && !$language->isDefault() && $this->supportsLanguagePageNames()) {
							// append language ID to 'name' when performing sorts within another language and LanguageSupportPageNames in place
							$value = "if($value$language!='', $value$language, $value)";
						}
					} else if($subValue == 'parent') {
						$query->leftjoin("pages AS $tableAlias2 ON $tableAlias.data=$tableAlias2.$idColumn");
						$value = "$tableAlias2.name";

					} else {
						$subValueField = $this->fields->get($subValue);
						if($subValueField) {
							$subValueTable = $database->escapeTable($subValueField->getTable());
							$query->leftjoin("$subValueTable AS $tableAlias2 ON $tableAlias.data=$tableAlias2.pages_id");
							$value = "$tableAlias2.data";
							if($language && !$language->isDefault() && $subValueField->type instanceof FieldtypeLanguageInterface) {
								// append language id to data, i.e. "data1234"
								$value .= $language;
							}
						} else {
							// error: unknown field
						}
					}
					
				} else if(!$subValue && $language && !$language->isDefault() && $field->type instanceof FieldtypeLanguageInterface) {
					// multi-language field, sort by the language version
					$value = "if($tableAlias.data$language != '', $tableAlias.data$language, $tableAlias.data)";
					
				} else {
					// regular field, just sort by data column
					$value = "$tableAlias." . ($subValue ? $subValue : "data");
				}
			}
	
			if(is_string($value) && strlen($value)) {
				if($descending) {
					$query->orderby("$value DESC", true);
				} else {
					$query->orderby("$value", true);
				}
			}
		}
	}

	protected function getQueryStartLimit(DatabaseQuerySelect $query) {

		$start = $this->start; 
		$limit = $this->limit;

		if($limit) {
			$limit = (int) $limit;
			$input = $this->wire()->input;
			$sql = '';

			if(is_null($start) && $input) {
				// if not specified in the selector, assume the 'start' property from the default page's pageNum
				$pageNum = $input->pageNum - 1; // make it zero based for calculation
				$start = $pageNum * $limit; 
			}

			if(!is_null($start)) {
				$start = (int) $start;
				$this->start = $start;
				$sql .= "$start,";
			}

			$sql .= "$limit";
			
			if($this->getTotal && $this->getTotalType != 'count') $query->select("SQL_CALC_FOUND_ROWS");
			if($sql) $query->limit($sql); 
		}
	}


	/**
	 * Special case when requested value is path or URL
	 * 
	 * @param DatabaseQuerySelect $query
	 * @param Selector $selector
	 * @throws PageFinderSyntaxException
	 *
	 */ 
	protected function ___getQueryJoinPath(DatabaseQuerySelect $query, $selector) {
		
		$database = $this->database; 
		$modules = $this->wire()->modules;
		$sanitizer = $this->sanitizer;

		// determine whether we will include use of multi-language page names
		if($this->supportsLanguagePageNames()) {
			$langNames = array();
			foreach($this->languages as $language) {
				/** @var Language $language */
				if(!$language->isDefault()) $langNames[$language->id] = "name" . (int) $language->id;
			}
			if(!count($langNames)) $langNames = null;
		} else {
			$langNames = null;
		}

		if($modules->isInstalled('PagePaths')) {
			$pagePaths = $modules->get('PagePaths');
			/** @var PagePaths $pagePaths */
			$pagePaths->getMatchQuery($query, $selector); 
			return;
		}

		if($selector->operator !== '=') {
			$this->syntaxError("Operator '$selector->operator' is not supported for path or url unless: 1) non-multi-language; 2) you install the PagePaths module."); 
		}

		$selectorValue = $selector->value;
		if($selectorValue === '/') {
			$parts = array();
			$query->where("pages.id=1");
		} else {
			if(is_array($selectorValue)) {
				// only the PagePaths module can perform OR value searches on path/url
				if($langNames) {
					$this->syntaxError("OR values not supported for multi-language 'path' or 'url'"); 
				} else {
					$this->syntaxError("OR value support of 'path' or 'url' requires core PagePaths module");
				}
			}
			if($langNames) {
				$module = $this->languages->pageNames();
				if($module) $selectorValue = $module->removeLanguageSegment($selectorValue);
			}
			$parts = explode('/', rtrim($selectorValue, '/')); 
			$part = $sanitizer->pageName(array_pop($parts), Sanitizer::toAscii); 
			$bindKey = $query->bindValueGetKey($part);
			$sql = "pages.name=$bindKey";
			if($langNames) {
				foreach($langNames as $langName) {
					$bindKey = $query->bindValueGetKey($part);
					$langName = $database->escapeCol($langName);
					$sql .= " OR pages.$langName=$bindKey";
				}
			}
			$query->where("($sql)"); 
			if(!count($parts)) $query->where("pages.parent_id=1");
		}

		$alias = 'pages';
		$lastAlias = 'pages';

		/** @noinspection PhpAssignmentInConditionInspection */
		while($n = count($parts)) {
			$n = (int) $n;
			$part = $sanitizer->pageName(array_pop($parts), Sanitizer::toAscii); 
			if(strlen($part)) {
				$alias = "parent$n";
				//$query->join("pages AS $alias ON ($lastAlias.parent_id=$alias.id AND $alias.name='$part')");
				$bindKey = $query->bindValueGetKey($part);
				$sql = "pages AS $alias ON ($lastAlias.parent_id=$alias.id AND ($alias.name=$bindKey";
				if($langNames) foreach($langNames as /* $id => */ $name) {
					// $status = "status" . (int) $id;
					// $sql .= " OR ($alias.$name='$part' AND $alias.$status>0) ";
					$bindKey = $query->bindValueGetKey($part);
					$sql .= " OR $alias.$name=$bindKey";
				}
				$sql .= '))';
				$query->join($sql); 

			} else {
				$query->join("pages AS rootparent$n ON ($alias.parent_id=rootparent$n.id AND rootparent$n.id=1)");
			}
			$lastAlias = $alias; 
		}
	}

	/**
	 * Special case when field is native to the pages table
	 *
	 * TODO not all operators will work here, so may want to add some translation or filtering
	 * 
	 * @param DatabaseQuerySelect $query
	 * @param Selector $selector
	 * @param array $fields
	 * @param array $options
	 * @param Selectors $selectors
	 * @throws PageFinderSyntaxException
	 *
	 */
	protected function getQueryNativeField(DatabaseQuerySelect $query, $selector, $fields, array $options, $selectors) {

		$values = $selector->values(true); 
		$SQL = '';
		$database = $this->database;
		$sanitizer = $this->sanitizer;
		$datetime = $this->wire()->datetime;

		foreach($fields as $field) { 

			// the following fields are defined in each iteration here because they may be modified in the loop
			$table = "pages";
			$operator = $selector->operator;
			$not = $selector->not;
			$compareType = $selectors::getSelectorByOperator($operator, 'compareType');
			$isPartialOperator = ($compareType & Selector::compareTypeFind); 

			$subfield = '';
			$IDs = array(); // populated in special cases where we can just match parent IDs
			$sql = '';

			if(strpos($field, '.')) {
				list($field, $subfield) = explode('.', $field);
				$subfield = $sanitizer->fieldName($subfield);
			}
			
			$field = $sanitizer->fieldName($field);
			if($field == 'sort' && $subfield) $subfield = '';
			if($field == 'child') $field = 'children';

			if($field != 'children' && !$this->fields->isNative($field)) {
				$subfield = $field;
				$field = '_pages';
			}
			
			$isParent = $field === 'parent' || $field === 'parent_id';
			$isChildren = $field === 'children';
			$isPages = $field === '_pages';

			if($isParent || $isChildren || $isPages) {
				// parent, children, pages

				if(($isPages || $isParent) && !$isPartialOperator && (!$subfield || in_array($subfield, array('id', 'path', 'url')))) {
					// match by location (id or path)
					// convert parent fields like '/about/company/history' to the equivalent ID
					foreach($values as $k => $v) {
						if(ctype_digit("$v")) continue; 
						$v = $sanitizer->pagePathName($v, Sanitizer::toAscii); 
						if(strpos($v, '/') === false) $v = "/$v"; // prevent a plain string with no slashes
						// convert path to id
						$parent = $this->pages->get($v);
						$values[$k] = $parent instanceof NullPage ? null : $parent->id;
					}
					$this->parent_id = null;
					if($isParent) {
						if($operator === '=') $IDs = $values;
						$field = 'parent_id'; 
						if(count($values) == 1 && count($fields) == 1 && $operator === '=') {
							$this->parent_id = reset($values);
						} 
					}

				} else {
					// matching by a parent's native or custom field (subfield)

					if(!$this->fields->isNative($subfield)) {
						$finder = $this->wire(new PageFinder());
						$finderMethod = 'findIDs';
						$includeSelector = 'include=all';
						if($field === 'children' || $field === '_pages') {
							if($subfield) {
								$s = '';
								if($field === 'children') $finderMethod = 'findParentIDs'; 
								// inherit include mode from main selector
								$includeSelector = $this->getIncludeSelector($selectors);
							} else if($field === 'children') {
								$s = 'children.id';
							} else {
								$s = 'id';
							}
						} else {
							$s = 'children.count>0, ';
						}
						$IDs = $finder->$finderMethod(new Selectors(ltrim(
							"$includeSelector," . 
							"$s$subfield$operator" . $sanitizer->selectorValue($values), 
							','
						)));
						if(!count($IDs)) $IDs[] = -1; // forced non match
					} else {
						// native
						static $n = 0;
						if($field === 'children') {
							$table = "_children_native" . (++$n);
							$query->join("pages AS $table ON $table.parent_id=pages.id");
						} else if($field === '_pages') {
							$table = 'pages';
						} else {
							$table = "_parent_native" . (++$n);
							$query->join("pages AS $table ON pages.parent_id=$table.id");
						}
						$field = $subfield;
					}
				}
			} else if($field === 'id' && count($values) > 1) { 
				if($operator === '=') {
					$IDs = $values;
				} else if($operator === '!=' && !$not) {
					$not = true;
					$operator = '=';
					$IDs = $values;
				}
				
			} else {
				// primary field is not 'parent', 'children' or 'pages'
			}

			if(count($IDs)) {
				// parentIDs or IDs found via another query, and we don't need to match anything other than the parent ID
				$in = $not ? "NOT IN" : "IN";
				$sql .= in_array($field, array('parent', 'parent_id')) ? "$table.parent_id " : "$table.id ";
				$IDs = $sanitizer->intArray($IDs, array('strict' => true));
				$strIDs = count($IDs) ? implode(',', $IDs) : '-1';
				$sql .= "$in($strIDs)";
				if($subfield === 'sort') $query->orderby("FIELD($table.id, $strIDs)");
				unset($strIDs);

			} else foreach($values as $value) { 

				if(is_null($value)) {
					// an invalid/unknown walue was specified, so make sure it fails
					$sql .= "1>2";
					continue; 
				}

				if(in_array($field, array('templates_id', 'template'))) {
					// convert templates specified as a name to the numeric template ID
					// allows selectors like 'template=my_template_name'
					$field = 'templates_id';
					if(count($values) == 1 && $operator === '=') $this->templates_id = reset($values);
					if(!ctype_digit("$value")) $value = (($template = $this->templates->get($value)) ? $template->id : 0); 
					
				} else if(in_array($field, array('created', 'modified', 'published'))) {
					// prepare value for created, modified or published date fields
					if(!ctype_digit("$value")) {
						$value = $datetime->strtotime($value); 
					}
					if(empty($value)) {
						$value = null;
						if($operator === '>' || $operator === '=>') {
							$value = $field === 'published' ? '1000-01-01 00:00:00' : '1970-01-01 00:00:01';
						}
					} else {
						$value = date('Y-m-d H:i:s', $value);
					}
					
				} else if(in_array($field, array('id', 'parent_id', 'templates_id', 'sort'))) {
					$value = (int) $value; 
				}
				
				$isName = $field === 'name' || strpos($field, 'name') === 0; 
				$isPath = $field === 'path' || $field === 'url';
				$isNumChildren = $field === 'num_children' || $field === 'numChildren';

				if($isName && $operator == '~=') {
					// handle one or more space-separated full words match to 'name' field in any order
					$s = '';
					foreach(explode(' ', $value) as $n => $word) {
						$word = $sanitizer->pageName($word, Sanitizer::toAscii);
						if($database->getRegexEngine() === 'ICU') {
							// MySQL 8.0.4+ uses ICU regex engine where "\\b" is used for word boundary
							$bindKey = $query->bindValueGetKey("\\b$word\\b");
						} else {
							// this Henry Spencer regex engine syntax works only in MySQL 8.0.3 and prior
							$bindKey = $query->bindValueGetKey('[[:<:]]' . $word . '[[:>:]]');
						}
						$s .= ($s ? ' AND ' : '') . "$table.$field RLIKE $bindKey";
					}

				} else if($isName && $isPartialOperator) {
					// handle partial match to 'name' field
					$value = $sanitizer->pageName($value, Sanitizer::toAscii);
					if($operator == '^=' || $operator == '%^=') {
						$value = "$value%";
					} else if($operator == '$=' || $operator == '%$=') {
						$value = "%$value";
					} else {
						$value = "%$value%";
					}
					$bindKey = $query->bindValueGetKey($value);
					$s = "$table.$field LIKE $bindKey";
						
				} else if(($isPath && $isPartialOperator) || $isNumChildren) {
					// match some other property that we need to launch a separate find to determine the IDs
					// used for partial match of path (used when original selector is parent.path%=...), parent.property, etc.
					$tempSelector = trim($this->getIncludeSelector($selectors) . ", $field$operator" . $sanitizer->selectorValue($value), ',');
					$tempIDs = $this->pages->findIDs($tempSelector);
					if(count($tempIDs)) {
						$s = "$table.id IN(" . implode(',', $sanitizer->intArray($tempIDs)) . ')';
					} else {
						$s = "$table.id=-1"; // force non-match
					}
					
				} else if(!$database->isOperator($operator)) {
					$s = '';
					$this->syntaxError("Operator '$operator' is not supported for '$field'.");

				} else if($this->isModifierField($field)) {
					$s = '';
					$this->syntaxError("Modifier '$field' is not allowed here");

				} else if(!$this->pagesColumnExists($field)) {
					$s = '';
					$this->syntaxError("Field '$field' is not a known field, column or selector modifier"); 
					
				} else {
					$not = false;
					if($isName) $value = $sanitizer->pageName($value, Sanitizer::toAscii);
					if($field === 'status' && !ctype_digit("$value")) {
						// named status
						$statuses = Page::getStatuses();
						if(!isset($statuses[$value])) $this->syntaxError("Unknown Page status: '$value'");
						$value = (int) $statuses[$value];
						if($operator === '=' || $operator === '!=') $operator = '&'; // bitwise
						if($operator === '!=') $not = true;
					}
					if($value === null) {
						$s = "$table.$field " . ($not ? 'IS NOT NULL' : 'IS NULL'); 
					} else {
						if(ctype_digit("$value") && $field != 'name') $value = (int) $value;
						$bindKey = $query->bindValueGetKey($value);
						$s = "$table.$field" . $operator . $bindKey;
						if($not) $s = "NOT ($s)";
					}
				
					if($field === 'status' && strpos($operator, '<') === 0 && $value >= Page::statusHidden && count($options['alwaysAllowIDs'])) {
						// support the 'alwaysAllowIDs' option for specific page IDs when requested but would
						// not otherwise appear in the results due to hidden or unpublished status
						$allowIDs = array();
						foreach($options['alwaysAllowIDs'] as $id) $allowIDs[] = (int) $id;
						$s = "($s OR $table.id IN(" . implode(',', $allowIDs) . '))';
					}
				}

				if($selector->not) $s = "NOT ($s)";
				
				if($operator == '!=' || $selector->not) {
					$sql .= $sql ? " AND $s": "$s"; 
				} else {
					$sql .= $sql ? " OR $s": "$s"; 
				}

			}

			if($sql) {
				if($SQL) {
					$SQL .= " OR ($sql)";
				} else {
					$SQL .= "($sql)";
				}
			}
		}

		if(count($fields) > 1) {
			$SQL = "($SQL)";
		}

		$query->where($SQL); 
		//$this->nativeWheres[] = $SQL; 
	}

	/**
	 * Get the include|status|check_access portions from given Selectors and return selector string for them
	 * 
	 * If given $selectors lacks an include or check_access selector, then it will pull from the
	 * equivalent PageFinder setting if present in the original initiating selector. 
	 * 
	 * @param Selectors|string $selectors
	 * @return string
	 * 
	 */
	protected function getIncludeSelector($selectors) {
		
		if(!$selectors instanceof Selectors) $selectors = new Selectors($selectors);
		$a = array();
		
		$include = $selectors->getSelectorByField('include');
		if(empty($include) && $this->includeMode) $include = "include=$this->includeMode";
		if($include) $a[] = $include;
		
		$status = $selectors->getSelectorByField('status');
		if(!empty($status)) $a[] = $status;
		
		$checkAccess = $selectors->getSelectorByField('check_access');
		if(empty($checkAccess) && $this->checkAccess === false && $this->includeMode !== 'all') $checkAccess = "check_access=0";
		if($checkAccess) $a[] = $checkAccess;
		
		return implode(', ', $a);
	}

	/**
	 * Make the query specific to all pages below a certain parent (children, grandchildren, great grandchildren, etc.)
	 * 
	 * @param DatabaseQuerySelect $query
	 * @param Selector $selector
	 *
	 */
	protected function getQueryHasParent(DatabaseQuerySelect $query, $selector) {

		static $cnt = 0; 

		$wheres = array();
		$parent_ids = $selector->value;
		if(!is_array($parent_ids)) $parent_ids = array($parent_ids); 
		
		foreach($parent_ids as $parent_id) {

			if(!ctype_digit("$parent_id")) {
				// parent_id is a path, convert a path to a parent
				$parent = $this->pages->newNullPage();
				$path = $this->sanitizer->path($parent_id);
				if($path) $parent = $this->pages->get('/' . trim($path, '/') . '/');
				$parent_id = $parent->id;
				if(!$parent_id) {
					$query->where("1>2"); // force the query to fail
					return;
				}
			}

			$parent_id = (int) $parent_id;

			$cnt++;

			if($parent_id == 1) {
				// homepage
				if($selector->operator == '!=') {
					// homepage is only page that can match not having a has_parent of 1
					$query->where("pages.id=1");
				} else {
					// no different from not having a has_parent, so we ignore it 
				}
				return;
			}

			// the subquery performs faster than the old method (further below) on sites with tens of thousands of pages
			if($selector->operator == '!=') {
				$in = 'NOT IN';
				$op = '!=';
				$andor = 'AND';
			} else {
				$in = 'IN';
				$op = '=';
				$andor = 'OR';
			}
			$wheres[] = "(" . 
				"pages.parent_id$op$parent_id " . 
				"$andor pages.parent_id $in (" . 
					"SELECT pages_id FROM pages_parents WHERE parents_id=$parent_id OR pages_id=$parent_id" . 
				")" . 
			")";
		}
		
		$andor = $selector->operator == '!=' ? ' AND ' : ' OR ';
		$query->where('(' . implode($andor, $wheres) . ')'); 

		/*
		// OLD method kept for reference
		$joinType = 'join';
		$table = "pages_has_parent$cnt";

		if($selector->operator == '!=') { 
			$joinType = 'leftjoin';
			$query->where("$table.pages_id IS NULL"); 

		}

		$query->$joinType(
			"pages_parents AS $table ON (" . 
				"($table.pages_id=pages.id OR $table.pages_id=pages.parent_id) " . 
				"AND ($table.parents_id=$parent_id OR $table.pages_id=$parent_id) " . 
			")"
		); 
		*/
	}

	/**
	 * Match a number of children count
	 * 
	 * @param DatabaseQuerySelect $query
	 * @param Selector $selector
	 * @return string
	 * @throws WireException
	 *
	 */
	protected function getQueryNumChildren(DatabaseQuerySelect $query, $selector) {

		if(!in_array($selector->operator, array('=', '<', '>', '<=', '>=', '!='))) {
			$this->syntaxError("Operator '$selector->operator' not allowed for 'num_children' selector.");
		}

		$value = (int) $selector->value;
		$this->getQueryNumChildren++; 
		$n = (int) $this->getQueryNumChildren;
		$a = "pages_num_children$n";
		$b = "num_children$n";

		if(	(in_array($selector->operator, array('<', '<=', '!=')) && $value) || 
			(in_array($selector->operator, array('>', '>=', '!=')) && $value < 0) || 
			(($selector->operator == '=' || $selector->operator == '>=') && !$value)) {

			// allow for zero values
			$query->select("COUNT($a.id) AS $b"); 
			$query->leftjoin("pages AS $a ON ($a.parent_id=pages.id)");
			$query->groupby("HAVING COUNT($a.id){$selector->operator}$value"); 

			/* FOR REFERENCE
			$query->select("count(pages_num_children$n.id) AS num_children$n"); 
			$query->leftjoin("pages AS pages_num_children$n ON (pages_num_children$n.parent_id=pages.id)");
			$query->groupby("HAVING count(pages_num_children$n.id){$selector->operator}$value"); 
			*/
			return $b;

		} else {

			// non zero values
			$query->select("$a.$b AS $b"); 
			$query->leftjoin(
				"(" . 
				"SELECT p$n.parent_id, COUNT(p$n.id) AS $b " . 
				"FROM pages AS p$n " . 
				"GROUP BY p$n.parent_id " . 
				"HAVING $b{$selector->operator}$value " . 
				") $a ON $a.parent_id=pages.id"); 

			$where = "$a.$b{$selector->operator}$value"; 
			$query->where($where);

			/* FOR REFERENCE
			$query->select("pages_num_children$n.num_children$n AS num_children$n"); 
			$query->leftjoin(
				"(" . 
				"SELECT p$n.parent_id, count(p$n.id) AS num_children$n " . 
				"FROM pages AS p$n " . 
				"GROUP BY p$n.parent_id " . 
				"HAVING num_children$n{$selector->operator}$value" . 
				") pages_num_children$n ON pages_num_children$n.parent_id=pages.id"); 

			$query->where("pages_num_children$n.num_children$n{$selector->operator}$value");
			*/

			return "$a.$b";
		}

	}

	/**
	 * Arrange the order of field names where necessary
	 * 
	 * @param array $fields
	 * @return array
	 *
	 */
	protected function arrangeFields(array $fields) {
		
		$custom = array();
		$native = array();
		$singles = array();
		
		foreach($fields as $name) {
			if($this->fields->isNative($name)) {
				$native[] = $name;
			} else {
				$custom[] = $name;
			}
			if(in_array($name, $this->singlesFields)) {
				$singles[] = $name;
			}
		}
		
		if(count($singles) && count($fields) > 1) {
			// field in use that may no be combined with others
			if($this->config->debug || $this->config->installed > 1549299319) {
				// debug mode or anything installed after February 4th, 2019
				$f = reset($singles);
				$fs = implode('|', $fields);
				$this->syntaxError("Field '$f' cannot OR with other fields in '$fs'");
			}
		}
		
		return array_merge($native, $custom); 
	}

	/**
	 * Returns the total number of results returned from the last find() operation
	 *
	 * If the last find() included limit, then this returns the total without the limit
	 *
	 * @return int
	 *
	 */
	public function getTotal() {
		return $this->total; 
	}

	/**
	 * Returns the limit placed upon the last find() operation, or 0 if no limit was specified
	 * 
	 * @return int
	 *
	 */
	public function getLimit() {
		return $this->limit === null ? 0 : $this->limit; 
	}

	/**
	 * Returns the start placed upon the last find() operation
	 * 
	 * @return int
	 *
	 */
	public function getStart() {
		return $this->start === null ? 0 : $this->start; 
	}

	/**
	 * Returns the parent ID, if it was part of the selector
	 * 
	 * @return int
	 *
	 */
	public function getParentID() {
		return $this->parent_id; 
	}

	/**
	 * Returns the templates ID, if it was part of the selector
	 * 
	 * @return int|null
	 *
	 */
	public function getTemplatesID() {
		return $this->templates_id; 
	}

	/**
	 * Return array of the options provided to PageFinder, as well as those determined at runtime
	 * 
	 * @return array
	 *
	 */
	public function getOptions() {
		return $this->lastOptions; 
	}

	/**
	 * Returns array of sortfields that should be applied to resulting PageArray after loaded
	 * 
	 * See the `useSortsAfter` option which must be enabled to use this. 
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function getSortsAfter() {
		return $this->sortsAfter;
	}

	/**
	 * Does the given field or fieldName resolve to a field that uses Page or PageArray values?
	 * 
	 * @param string|Field $fieldName Field name or object
	 * @param bool $literal Specify true to only allow types that literally use FieldtypePage::getMatchQuery() 
	 * @return Field|bool|string Returns Field object or boolean true (children|parent) if valid Page field, or boolean false if not
	 * 
	 */
	protected function isPageField($fieldName, $literal = false) {
		
		$is = false;
		
		if($fieldName === 'parent' || $fieldName === 'children') {
			return $fieldName; // early exit
			
		} else if($fieldName instanceof Field) {
			$field = $fieldName;
			
		} else if(is_string($fieldName) && strpos($fieldName, '.')) {
			// check if this is a multi-part field name
			list($fieldName, $subfieldName) = explode('.', $fieldName, 2);
			if($subfieldName === 'id') {
				// id property is fine and can be ignored
			} else {
				// some other property, see if it resolves to a literal Page field
				$f = $this->isPageField($subfieldName, true);
				if($f) {
					// subfield resolves to literal Page field, so we can pass this one through
				} else {
					// some other property, that doesn't resolve to a Page field, we can early-exit now
					return false;
				}
			}
			$field = $this->fields->get($fieldName);
			
		} else {
			$field = $this->fields->get($fieldName);
		}
		
		if($field) {
			if($field->type instanceof FieldtypePage) {
				$is = true;
			} else if(strpos($field->type->className(), 'FieldtypePageTable') !== false) {
				$is = true;
			} else if($this->isRepeaterFieldtype($field->type)) {
				$is = $literal ? false : true;
			} else {
				$test = $field->type->getBlankValue(new NullPage(), $field); 
				if($test instanceof Page || $test instanceof PageArray) {
					$is = $literal ? false : true;
				}
			}
		}
		if($is && $field) $is = $field; 
		return $is;
	}

	/**
	 * Is the given Fieldtype for a repeater?
	 * 
	 * @param Fieldtype $fieldtype
	 * @return bool
	 * 
	 */
	protected function isRepeaterFieldtype(Fieldtype $fieldtype) {
		return wireInstanceOf($fieldtype, 'FieldtypeRepeater'); 
	}

	/**
	 * Is given field name a modifier that does not directly refer to a field or column name?
	 * 
	 * @param string $name
	 * @return string Returns normalized modifier name if a modifier or boolean false if not
	 * 
	 */
	protected function isModifierField($name) {
		
		$alternates = array(
			'checkAccess' => 'check_access',
			'getTotal' => 'get_total',
			'hasParent' => 'has_parent',
		);
		
		$modifiers = array(
			'include',
			'_custom',
			'limit',
			'start',
			'check_access',
			'get_total',
			'count', 
			'has_parent',
		);
		
		if(isset($alternates[$name])) return $alternates[$name];
		$key = array_search($name, $modifiers); 
		if($key === false) return false;
		
		return $modifiers[$key];
	}

	/**
	 * Does the given column name exist in the 'pages' table?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	protected function pagesColumnExists($name) {
		
		if(isset(self::$pagesColumns['all'][$name])) {
			return self::$pagesColumns['all'][$name];
		}
		
		$instanceID = $this->wire()->getProcessWireInstanceID();
		
		if(!isset(self::$pagesColumns[$instanceID])) {
			self::$pagesColumns[$instanceID] = array();
			if($this->supportsLanguagePageNames()) {
				foreach($this->languages as $language) {
					/** @var Language $language */
					if($language->isDefault()) continue;
					self::$pagesColumns[$instanceID]["name$language->id"] = true;
					self::$pagesColumns[$instanceID]["status$language->id"] = true;
				}
			}
		}
		
		if(isset(self::$pagesColumns[$instanceID][$name])) {
			return self::$pagesColumns[$instanceID][$name]; 
		}
		
		self::$pagesColumns[$instanceID][$name] = $this->database->columnExists('pages', $name);
		
		return self::$pagesColumns[$instanceID][$name];
	}

	/**
	 * Data and cache used by the pagesColumnExists method 
	 * 
	 * @var array
	 * 
	 */
	static private $pagesColumns = array(
		// 'instance ID' => [ ... ]
		'all' => array( // available in all instances
			'id' => true,
			'parent_id' => true,
			'templates_id' => true,
			'name' => true,
			'status' => true,
			'modified' => true,
			'modified_users_id' => true,
			'created' => true,
			'created_users_id' => true,
			'published' => true, 
			'sort' => true, 
		),
	);

	/**
	 * Are multi-language page names supported?
	 * 
	 * @return bool
	 * @since 3.0.165
	 * 
	 */
	protected function supportsLanguagePageNames() {
		if($this->supportsLanguagePageNames === null) {
			$languages = $this->languages;
			$this->supportsLanguagePageNames = $languages && $languages->hasPageNames();
		}
		return $this->supportsLanguagePageNames;
	}
	
	/**
	 * Hook called when an unknown field is found in the selector
	 * 
	 * By default, PW will throw a PageFinderSyntaxException but that behavior can be overridden by 
	 * hooking this method and making it return true rather than false. It may also choose to 
	 * map it to a Field by returning a Field object. If it returns integer 1 then it indicates the
	 * fieldName mapped to an API variable. If this method returns false, then it signals the getQuery()
	 * method that it was unable to map it to anything and should be considered a fail.
	 * 
	 * @param string $fieldName
	 * @param array $data Array of data containing the following in it: 
	 *  - `subfield` (string): First subfield
	 *  - `subfields` (string): All subfields separated by period (i.e. subfield.tertiaryfield)
	 *  - `fields` (array): Array of all other field names being processed in this selector. 
	 *  - `query` (DatabaseQuerySelect): Database query select object
	 *  - `selector` (Selector): Selector that contains this field
	 *  - `selectors` (Selectors): All the selectors
	 * @return bool|Field|int
	 * @throws PageFinderSyntaxException
	 * 
	 */
	protected function ___getQueryUnknownField($fieldName, array $data) { 
		
		$_data = array(
			'subfield ' => 'data', 
			'subfields' => 'data', 
			'fields' => array(), 
			'query' => null, 
			'selector' => null, 
			'selectors' => null, 
		);
		
		$data = array_merge($_data, $data); 
		$fields = $data['fields']; /** @var array $fields */
		$subfields = $data['subfields']; /** @var string $subfields */
		$selector = $data['selector']; /** @var Selector $selector */
		$query = $data['query']; /** @var DatabaseQuerySelect $query */
		$value = $this->wire($fieldName); /** @var Wire|null $value */
		
		if($value) {
			// found an API var
			if(count($fields) > 1) {
				$this->syntaxError("You may only match 1 API variable at a time");
			}
			if(is_object($value)) {
				if($subfields == 'data') $subfields = 'id';
				$selector->field = $subfields;
			}
			if(!$selector->matches($value)) {
				$query->where("1>2"); // force non match
			}
			return 1; // indicate no further fields need processing
		}
		
		// not an API var
		
		if($this->getQueryOwnerField($fieldName, $data)) return true;
	
		/** @var bool|int|Field $value Hooks can modify return value to be Field */
		$value = false;
	
		return $value;
	}

	/**
	 * Process an owner back reference selector for PageTable, Page and Repeater fields
	 * 
	 * @param string $fieldName Field name in "fieldName__owner" format
	 * @param array $data Data as provided to getQueryUnknownField method
	 * @return bool True if $fieldName was processed, false if not
	 * @throws PageFinderSyntaxException
	 * 
	 */
	protected function getQueryOwnerField($fieldName, array $data) {
		
		if(substr($fieldName, -7) !== '__owner') return false;
		
		$fields = $data['fields']; /** @var array $fields */
		$subfields = $data['subfields']; /** @var string $subfields */
		$selectors = $data['selectors']; /** @var Selectors $selectors */
		$selector = $data['selector']; /** @var Selector $selector */
		$query = $data['query']; /** @var DatabaseQuerySelect $query */
		
		if(empty($subfields)) $this->syntaxError("When using owner a subfield is required");

		list($ownerFieldName,) = explode('__owner', $fieldName);
		$ownerField = $this->fields->get($ownerFieldName);
		if(!$ownerField) return false;
		
		$ownerTypes = array('FieldtypeRepeater', 'FieldtypePageTable', 'FieldtypePage');
		if(!wireInstanceOf($ownerField->type, $ownerTypes)) return false;
		if($selector->get('owner_processed')) return true;
	
		static $ownerNum = 0;
		$ownerNum++;
	
		// determine which templates are using $ownerFieldName
		$templateIDs = array();
		foreach($this->templates as $template) {
			/** @var Template $template */
			if($template->hasField($ownerFieldName)) {
				$templateIDs[$template->id] = $template->id;
			}
		}
	
		if(!count($templateIDs)) $templateIDs[] = 0;
		$templateIDs = implode('|', $templateIDs);

		// determine include=mode
		$include = $selectors->getSelectorByField('include');
		$include = $include ? $include->value : '';
		if(!$include) $include = $this->includeMode ? $this->includeMode : 'hidden'; 
		
		$selectorString = "templates_id=$templateIDs, include=$include, get_total=0";
	
		if($include !== 'all') {
			$checkAccess = $selectors->getSelectorByField('check_access');
			if($checkAccess && ctype_digit($checkAccess->value)) {
				$selectorString .= ", check_access=$checkAccess->value";
			} else if($this->checkAccess === false) {
				$selectorString .= ", check_access=0";
			}
		}
	
		/** @var Selectors $ownerSelectors Build selectors */
		$ownerSelectors = $this->wire(new Selectors($selectorString));
		$ownerSelector = clone $selector;

		if(count($fields) > 1) {
			// OR fields present
			array_shift($fields);
			$subfields = array($subfields); // 1. subfields is definitely an array…
			foreach($fields as $name) {
				if(strpos($name, "$fieldName.") === 0) {
					list(,$name) = explode('__owner.', $name);
					/** @var array $subfields 2. …but PhpStorm in PHP8 mode can't tell it's an array without this */
					$subfields[] = $name;
				} else {
					$this->syntaxError(
						"When owner is present, group of OR fields must all be '$ownerFieldName.owner.subfield' format"
					);
				}
			}
		}
		
		$ownerSelector->field = $subfields;
		$ownerSelectors->add($ownerSelector);
	
		// use field.count>0 as an optimization?
		$useCount = true;
		
		// find any other selectors referring to this same owner, bundle them in, and remove from source
		foreach($selectors as $sel) {
			if(strpos($sel->field(), "$fieldName.") !== 0) continue;
			$sel->set('owner_processed', true);
			$op = $sel->operator();
			if($useCount && ($sel->not || strpos($op, '!') !== false || strpos($op, '<') !== false)) {
				$useCount = false;
			}
			if($sel === $selector) {
				continue; // skip main
			}	
			$s = clone $sel;
			$s->field = str_replace("$fieldName.", '', $sel->field());
			$ownerSelectors->add($s); 
			$selectors->remove($sel);
		}
	
		if($useCount) {
			$sel = new SelectorGreaterThan("$ownerFieldName.count", 0);
			$ownerSelectors->add($sel);
		}
		
		/** @var PageFinder $finder */
		$finder = $this->wire(new PageFinder());
		$ids = array();
		foreach($finder->findIDs($ownerSelectors) as $id) {
			$ids[] = (int) $id;
		}
		
		if($this->isRepeaterFieldtype($ownerField->type)) {
			// Repeater
			$alias = "owner_parent$ownerNum";
			$names = array();
			foreach($ids as $id) {
				$names[] = "'for-page-$id'";
			}
			$names = empty($names) ? "'force no match'" : implode(",", $names);
			$query->join("pages AS $alias ON $alias.id=pages.parent_id AND $alias.name IN($names)");
		} else {
			// Page or PageTable
			$table = $ownerField->getTable();
			$alias = "owner{$ownerNum}_$table";
			$ids = empty($ids) ? "0" : implode(',', $ids);
			$query->join("$table AS $alias ON $alias.data=pages.id AND $alias.pages_id IN($ids)");
		}

		return true;
	}

	/**
	 * Get data that should be populated back to any resulting PageArray’s data() method
	 * 
	 * @param PageArray|null $pageArray Optionally populate given PageArray
	 * @return array
	 * 
	 */
	public function getPageArrayData(?PageArray $pageArray = null) {
		if($pageArray !== null && count($this->pageArrayData)) {
			$pageArray->data($this->pageArrayData); 
		}
		return $this->pageArrayData; 
	}
	
	/**
	 * Are any of the given field name(s) native to PW system?
	 * 
	 * This is primarily used to determine whether the getQueryNativeField() method should be called.
	 *
	 * @param string|array|Selector $fieldNames Single field name, array of field names or pipe-separated string of field names
	 * @return bool
	 *
	 */
	protected function hasNativeFieldName($fieldNames) {

		$fieldName = null;

		if(is_object($fieldNames)) {
			if($fieldNames instanceof Selector) {
				$fieldNames = $fieldNames->fields();
			} else {
				return false;
			}
		}

		if(is_string($fieldNames)) {
			if(strpos($fieldNames, '|')) {
				$fieldNames = explode('|', $fieldNames);
				$fieldName = reset($fieldNames);
			} else {
				$fieldName = $fieldNames;
				$fieldNames = array($fieldName);
			}
		} else if(is_array($fieldNames)) {
			$fieldName = reset($fieldNames);
		}

		if($fieldName !== null) {
			if(strpos($fieldName, '.')) list($fieldName,) = explode('.', $fieldName, 2);
			if($this->fields->isNative($fieldName)) return true;
		}

		if(count($fieldNames)) {
			$fieldsStr = ':' . implode(':', $fieldNames) . ':';
			if(strpos($fieldsStr, ':parent.') !== false) return true;
			if(strpos($fieldsStr, ':children.') !== false) return true;
			if(strpos($fieldsStr, ':child.') !== false) return true;
		}

		return false;
	}

	/**
	 * Get the fully parsed/final selectors used in the last find() operation
	 * 
	 * Should only be called after a find() or findIDs() operation, otherwise returns null. 
	 * 
	 * #pw-internal
	 * 
	 * @return Selectors|null
	 * @since 3.0.146
	 * 
	 */
	public function getSelectors() {
		return $this->finalSelectors;
	}

	/**
	 * Throw a fatal syntax error
	 * 
	 * @param string $message
	 * @throws PageFinderSyntaxException
	 * 
	 */
	public function syntaxError($message) {
		throw new PageFinderSyntaxException($message); 
	}
}

/**
 * Typehinting class for DatabaseQuerySelect object passed to Fieldtype::getMatchQuery()
 *
 * @property Field $field Original field
 * @property string $group Original group of the field
 * @property Selector $selector Original Selector object
 * @property Selectors $selectors Original Selectors object
 * @property DatabaseQuerySelect $parentQuery Parent database query
 * @property PageFinder $pageFinder PageFinder instance that initiated the query
 * @property string $joinType Value 'join', 'leftjoin', or '' (if not yet known), can be overridden (3.0.237+)
 */
abstract class PageFinderDatabaseQuerySelect extends DatabaseQuerySelect { }
