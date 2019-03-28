<?php namespace ProcessWire;

/**
 * ProcessWire PageFinder
 *
 * Matches selector strings to pages
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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

		); 

	protected $fieldgroups; 
	protected $getTotal = true; // whether to find the total number of matches
	protected $getTotalType = 'calc'; // May be: 'calc', 'count', or blank to auto-detect. 
	protected $total = 0;
	protected $limit = 0; 
	protected $start = 0;
	protected $parent_id = null;
	protected $templates_id = null;
	protected $checkAccess = true;
	protected $getQueryNumChildren = 0; // number of times the function has been called
	protected $lastOptions = array(); 
	protected $extraOrSelectors = array(); // one from each field must match
	protected $sortsAfter = array(); // apply these sorts after pages loaded 
	protected $reverseAfter = false; // reverse order after load?
	protected $pageArrayData = array(); // any additional data that should be populated back to any resulting PageArray objects
	protected $singlesFields = array( // fields that can only be used by themselves (not OR'd with other fields)
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


	/**
	 * Pre-process the selectors to add Page status checks
	 * 
	 * @param Selectors $selectors
	 * @param array $options
	 *
	 */
	protected function setupStatusChecks(Selectors $selectors, array &$options) {

		$maxStatus = null; 
		$limit = 0; // for getTotal auto detection
		$start = 0;
		$limitSelector = null;
		$checkAccessSpecified = false;
		$hasParents = array(); // requests for parent(s) in the selector
		$hasSort = false; // whether or not a sort is requested

		foreach($selectors as $key => $selector) {

			$fieldName = $selector->field; 

			if($fieldName == 'status') {
				$value = $selector->value; 
				if(!ctype_digit("$value")) {
					// allow use of some predefined labels for Page statuses
					$statuses = Page::getStatuses();
					$selector->value = isset($statuses[$value]) ? $statuses[$value] : 1;
				}
				$not = false;
				if(($selector->operator == '!=' && !$selector->not) || ($selector->not && $selector->operator == '=')) {
					$s = $this->wire(new SelectorBitwiseAnd('status', $selector->value));
					$s->not = true;
					$not = true;
					$selectors[$key] = $s;
	
				} else if($selector->operator == '=' || ($selector->operator == '!=' && $selector->not)) {
					$selectors[$key] = $this->wire(new SelectorBitwiseAnd('status', $selector->value));
					
				} else {
					// some other operator like: >, <, >=, <=
					$not = $selector->not;
				}
				if(!$not && (is_null($maxStatus) || $selector->value > $maxStatus)) $maxStatus = (int) $selector->value; 
				
			} else if($fieldName == 'include' && $selector->operator == '=' && in_array($selector->value, array('hidden', 'all', 'unpublished', 'trash'))) {
				if($selector->value == 'hidden') $options['findHidden'] = true;
					else if($selector->value == 'unpublished') $options['findUnpublished'] = true;
					else if($selector->value == 'trash') $options['findTrash'] = true; 
					else if($selector->value == 'all') $options['findAll'] = true; 
				$selectors->remove($key);

			} else if($fieldName == 'check_access' || $fieldName == 'checkAccess') { 
				$this->checkAccess = ((int) $selector->value) > 0 ? true : false;
				$checkAccessSpecified = true;
				$selectors->remove($key); 

			} else if($fieldName == 'limit') {
				// for getTotal auto detect
				$limit = (int) $selector->value; 	
				$limitSelector = $selector;

			} else if($fieldName == 'start') {
				// for getTotal auto detect
				$start = (int) $selector->value; 	

			} else if($fieldName == 'sort') {
				// sorting is not needed if we are only retrieving totals
				if($options['loadPages'] === false) $selectors->remove($selector);
				$hasSort = true;

			} else if($fieldName == 'parent' || $fieldName == 'parent_id') {
				$hasParents[] = $selector->value;

			} else if($fieldName == 'getTotal' || $fieldName == 'get_total') {
				// whether to retrieve the total, and optionally what type: calc or count
				// this applies only if user hasn't themselves created a field called getTotal or get_total
				if(!$this->wire('fields')->get($fieldName)) {
					if(ctype_digit("$selector->value")) {
						$options['getTotal'] = (bool) $selector->value; 
					} else if(in_array($selector->value, array('calc', 'count'))) {
						$options['getTotal'] = true; 
						$options['getTotalType'] = $selector->value; 
					}
					$selectors->remove($selector); 
				}
			}
		} // foreach($selectors)

		if(!is_null($maxStatus) && empty($options['findAll']) && empty($options['findUnpublished'])) {
			// if a status was already present in the selector, without a findAll/findUnpublished, then just make sure the page isn't unpublished
			if($maxStatus < Page::statusUnpublished) {
				$selectors->add(new SelectorLessThan('status', Page::statusUnpublished));
			}

		} else if($options['findAll']) { 
			// findAll option means that unpublished, hidden, trash, system may be included
			if(!$checkAccessSpecified) $this->checkAccess = false;

		} else if($options['findHidden']) {
			// findHidden option, apply optimizations enabling hidden pages to be loaded
			$selectors->add(new SelectorLessThan('status', Page::statusUnpublished));
			
		} else if($options['findUnpublished']) {
			$selectors->add(new SelectorLessThan('status', Page::statusTrash)); 
			
		} else if($options['findTrash']) { 
			$selectors->add(new SelectorLessThan('status', Page::statusDeleted)); 

		} else {
			// no status is present, so exclude everything hidden and above
			$selectors->add(new SelectorLessThan('status', Page::statusHidden)); 
		}

		if($options['findOne']) {
			// findOne option is never paginated, always starts at 0
			$selectors->add(new SelectorEqual('start', 0)); 
			if(empty($options['startAfterID']) && empty($options['stopBeforeID'])) {
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
		
		if(count($hasParents) == 1 && !$hasSort) {
			// if single parent specified and no sort requested, default to the sort specified with the requested parent
			try {
				$parent = $this->wire('pages')->get(reset($hasParents));
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
			$options['softLimit'] = $limitSelector->value;
			$selectors->remove($limitSelector);
		}
		
		$this->lastOptions = $options; 
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
	 *     may be able to apply  some additional optimizations in certain cases. For instance, if loadPages=false, then
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
	 *  - `allowCustom` (bool): Whether or not to allow _custom='selector string' type values (default=false). 
	 *  - `useSortsAfter` (bool): When true, PageFinder may ask caller to perform sort manually in some cases (default=false). 
	 * @return array|DatabaseQuerySelect
	 * @throws PageFinderException
	 *
	 */
	public function ___find($selectors, array $options = array()) {
		
		if(is_string($selectors) || is_array($selectors)) {
			$selectors = new Selectors($selectors);
		} else if(!$selectors instanceof Selectors) {
			throw new PageFinderException("find() requires Selectors object, string or array");
		}

		$this->fieldgroups = $this->wire('fieldgroups'); 
		$options = array_merge($this->defaultOptions, $options); 

		$this->parent_id = null;
		$this->templates_id = null;
		$this->checkAccess = true; 
		$this->getQueryNumChildren = 0;
		$this->pageArrayData = array();
		$this->setupStatusChecks($selectors, $options);

		// move getTotal option to a class property, after setupStatusChecks
		$this->getTotal = $options['getTotal'];
		$this->getTotalType = $options['getTotalType'] == 'count' ? 'count' : 'calc'; 
		unset($options['getTotal']); // so we get a notice if we try to access it

		$stopBeforeID = (int) $options['stopBeforeID'];
		$startAfterID = (int) $options['startAfterID'];
		$database = $this->wire('database');
		$matches = array(); 
		/** @var DatabaseQuerySelect $query */
		$query = $this->getQuery($selectors, $options);

		//if($this->wire('config')->debug) $query->set('comment', "Selector: " . (string) $selectors); 
		if($options['returnQuery']) return $query; 

		if($options['loadPages'] || $this->getTotalType == 'calc') {

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
							$matches = array(end($matches));
						} else if($options['softLimit']) {
							$matches = array_slice($matches, -1 * $options['softLimit']);
						}
						break;
					}
					
					if($options['returnVerbose']) {
						// determine score for this row
						$score = 0;
						foreach($row as $k => $v) if(strpos($k, '_score') === 0) {
							$score += $v;
							unset($row[$k]);
						}
						$row['score'] = $score; // @todo do we need this anymore?
						$matches[] = $row;
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

		$this->lastOptions = $options; 
		
		if($this->reverseAfter) $matches = array_reverse($matches);

		return $matches; 
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
				
			} else if($field == 'eq' || $field == 'index') { 
				if($this->wire('fields')->get($field)) continue;
				$value = $selector->value; 
				if($value === 'first') {
					$eq = 0;
				} else if($value === 'last') {
					$eq = -1;
				} else {
					$eq = (int) $value;
				}
				$selectors->remove($selector);
				
			} else if(strpos($field, '.owner.') && !$this->wire('fields')->get('owner')) {
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
				if(!$n && $this->wire('pages')->loader()->isNativeColumn($selector->value)) {
					// first iteration only, see if it's a native column and prevent sortsAfter if so
					break;
				}
				if(strpos($selector->value, '.') !== false) {
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
				/** @var Selectors $sel */
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
		/** @var Languages|null $languages */
		$languages = $this->wire('languages');
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
			
			$fieldtype = $this->wire('fieldtypes')->get($fieldName);
			if(!$fieldtype) continue;
			$fieldtypeLang = $languages ? $this->wire('fieldtypes')->get("{$fieldName}Language") : null;
			
			foreach($this->wire('fields') as $f) {
				
				if($findExtends) {
					// allow any Fieldtype that is an instance of given one, or extends it
					if(!wireInstanceOf($f->type, $fieldtype) 
						&& ($fieldtypeLang === null || !wireInstanceOf($f->type, $fieldtypeLang))) continue;
					
				} else {
					// only allow given Fieldtype
					if($f->type !== $fieldtype && ($fieldtypeLang === null || $f->type !== $fieldtypeLang)) continue;
				}
				
				$fName = $subfield ? "$f->name.$subfield" : $f->name;
				
				if($findPerField) {
					if($selectorCopy === null) $selectorCopy = clone $selector;
					$selectorCopy->field = $fName;
					$selectors->replace($selector, $selectorCopy); 
					$count = $this->wire('pages')->count($selectors);
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
	
		/** @var Fields $fields */
		$fields = $this->wire('fields');
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
				if($fields->get($part)) continue; // maps to Field object
				if($fields->isNative($part)) continue; // maps to native property
				if($tags === null) $tags = $fields->getTags(true); // determine tags
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
			$groupName = $this->wire('sanitizer')->fieldName($groupName);
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
				throw new PageFinderSyntaxException("Multi-dot 'a.b.c' type selectors may not be used with OR '|' fields");
			}
			
			$fn = reset($fieldsArray);
			$parts = explode('.', $fn);
			$fieldName = array_shift($parts);
			$field = $this->isPageField($fieldName);
			
			if($field) {
				// we have a workable page field
				/** @var Selectors $_selectors */
				if($options['findAll']) $s = "include=all";
					else if($options['findHidden']) $s = "include=hidden";
					else if($options['findUnpublished']) $s = "include=unpublished";
					else $s = '';
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
				$field = $this->wire('fields')->get($fieldName);
				if(!$field) continue;
				if(!$hasTemplate && $field->template_id) {
					if(is_array($field->template_id)) {
						$templates = array_merge($templates, $field->template_id);
					} else {
						$templates[] = (int) $field->template_id;
					}
				}
				if(!$hasParent && $field->parent_id) {
					if($this->isRepeaterFieldtype($field->type)) { 
						// repeater items not stored directly under parent_id, but as another parent under parent_id. 
						// so we use has_parent instead here
						$selectors->prepend(new SelectorEqual('has_parent', $field->parent_id));
					} else {
						// direct parent: FieldtypePage or similar
						$parents[] = (int) $field->parent_id;
					}
				}
				if($field->findPagesSelector && count($fields) == 1) $findSelector = $field->findPagesSelector;
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
		$whereBindValues = array();
		$cnt = 1;
		$fieldCnt = array(); // counts number of instances for each field to ensure unique table aliases for ANDs on the same field
		$lastSelector = null; 
		$sortSelectors = array(); // selector containing 'sort=', which gets added last
		$subqueries = array();
		$joins = array();
		// $this->extraJoins = array();
		$database = $this->wire('database');
		$this->preProcessSelectors($selectors, $options);
		
		if($options['returnVerbose']) {
			$columns = array('pages.id', 'pages.parent_id', 'pages.templates_id');
		} else if($options['returnParentIDs']) { 
			$columns = array('pages.parent_id AS id');
		} else {
			$columns = array('pages.id');
		}

		/** @var DatabaseQuerySelect $query */
		$query = $this->wire(new DatabaseQuerySelect());
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
			
			$fields = $selector->field; 
			$group = $selector->group; // i.e. @field
			$fields = is_array($fields) ? $fields : array($fields); 
			if(count($fields) > 1) $fields = $this->arrangeFields($fields); 
			$field1 = reset($fields); // first field including optional subfield

			// TODO Make native fields and path/url multi-field and multi-value aware
			if($field1 === 'sort' && $selector->operator === '=') {
				$sortSelectors[] = $selector;
				continue;
			
			} else if($field1 === 'sort' || $field1 === 'page.sort') {
				if(!in_array($selector->operator, array('=', '!=', '<', '>', '>=', '<='))) {
					throw new PageFinderSyntaxException("Property '$field1' may not use operator: $selector->operator");
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
			
			foreach($fields as $n => $fieldName) {

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
				
				$field = $this->wire('fields')->get($fieldName); 

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
						throw new PageFinderSyntaxException("Field does not exist: $fieldName");
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
				$numEmptyValues = 0; 
				$valueArray = $selector->values(true); 
				$fieldtype = $field->type; 
				$operator = $selector->operator;

				foreach($valueArray as $value) {

					// shortcut for blank value condition: this ensures that NULL/non-existence is considered blank
					// without this section the query would still work, but a blank value must actually be present in the field
					$useEmpty = empty($value) || ($value && $operator[0] == '<') || ($value < 0 && $operator[0] == '>');	
					if($subfield == 'data' && $useEmpty && $fieldtype) { // && !$fieldtype instanceof FieldtypeMulti) {
						if(empty($value)) $numEmptyValues++;
						if(in_array($operator, array('=', '!=', '<>', '<', '<=', '>', '>='))) {
							// we only accommodate this optimization for single-value selectors...
							if($this->whereEmptyValuePossible($field, $selector, $query, $value, $whereFields)) {
								if(count($valueArray) > 1 && $operator == '=') $whereFieldsType = 'OR';
								continue;
							}
						}
					}

					/** @var DatabaseQuerySelect $q */
					if(isset($subqueries[$tableAlias])) {
						$q = $subqueries[$tableAlias];
					} else {
						$q = $this->wire(new DatabaseQuerySelect());
					}
					
					$q->set('field', $field); // original field if required by the fieldtype
					$q->set('group', $group); // original group of the field, if required by the fieldtype
					$q->set('selector', $selector); // original selector if required by the fieldtype
					$q->set('selectors', $selectors); // original selectors (all) if required by the fieldtype
					$q->set('parentQuery', $query);
					
					$q = $fieldtype->getMatchQuery($q, $tableAlias, $subfield, $selector->operator, $value); 

					if(count($q->select)) $query->select($q);
					if(count($q->join)) $query->join($q);
					if(count($q->leftjoin)) $query->leftjoin($q);
					if(count($q->orderby)) $query->orderby($q);
					if(count($q->groupby)) $query->groupby($q);

					if(count($q->where)) { 
						$whereBindValues = array_merge($whereBindValues, $q->getBindValues('where'));
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

					$cnt++;
				}

				if($join) {
					$joinType = 'join';

					if(count($fields) > 1 
						|| (count($valueArray) > 1 && $numEmptyValues > 0)
						|| ($subfield == 'count' && !$this->isRepeaterFieldtype($field->type))
						|| ($selector->not && $selector->operator != '!=') 
						|| $selector->operator == '!=') {
						// join should instead be a leftjoin

						$joinType = "leftjoin";

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

		if($where) {
			$query->where("($where)", $whereBindValues);
		} else if(count($whereBindValues)) {
			foreach($whereBindValues as $k => $v) {
				$query->bindValue($k, $v);
			}
		}
		
		$this->getQueryAllowedTemplates($query, $options); 

		// complete the joins, matching up any conditions for the same table
		foreach($joins as $j) {
			$joinType = $j['joinType']; 
			$query->$joinType("$j[table] AS $j[tableAlias] ON $j[tableAlias].pages_id=pages.id AND ($j[join])"); 
		}
	
		if(count($sortSelectors)) {
			foreach(array_reverse($sortSelectors) as $s) {
				$this->getQuerySortSelector($query, $s);
			}
		}
		
		$this->postProcessQuery($query); 
		
		return $query; 
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
			foreach($this->extraOrSelectors as $groupName => $selectorGroup) {
				$n = 0;
				$sql = "\tpages.id IN (\n";
				foreach($selectorGroup as $selectors) {
					$pageFinder = $this->wire(new PageFinder());	
					/** @var DatabaseQuerySelect $query */
					$query = $pageFinder->find($selectors, array(
						'returnQuery' => true, 
						'returnVerbose' => false,
						'findAll' => true
						));
					if($n > 0) $sql .= " \n\tOR pages.id IN (\n";
					$query->set('groupby', array());
					$query->set('select', array('pages.id')); 
					$query->set('orderby', array()); 
					// foreach($this->nativeWheres as $where) $query->where($where);  // doesn't seem to speed anything up, MySQL must already optimize for this
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
	 * @param Selector $selector
	 * @param DatabaseQuerySelect $query
	 * @param string $value The value presumed to be blank (passed the empty() test)
	 * @param string $where SQL where string that will be modified/appended
	 * @return bool Whether or not the query was handled and modified
	 * @throws WireException
	 * 
	 */
	protected function whereEmptyValuePossible(Field $field, $selector, $query, $value, &$where) {
		
		
		// look in table that has no pages_id relation back to pages, using the LEFT JOIN / IS NULL trick
		// OR check for blank value as defined by the fieldtype
		
		$operator = $selector->operator; 
		$database = $this->wire('database');
		static $tableCnt = 0;
		$table = $database->escapeTable($field->table);
		$tableAlias = $table . "__blank" . (++$tableCnt);
		$blankValue = $field->type->getBlankValue(new NullPage(), $field);
		$blankIsObject = is_object($blankValue); 
		if($blankIsObject) $blankValue = '';
		$blankValue = $database->escapeStr($blankValue);
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
		if(!isset($operators[$operator])) return false; 
		if($selector->not) $operator = $operators[$operator]; // reverse
		
		if($operator == '=') {
			// equals
			// non-presence of row is equal to value being blank
			if($field->type->isEmptyValue($field, $value)) {
				$sql = "$tableAlias.pages_id IS NULL OR ($tableAlias.data='$blankValue'";
			} else {
				$sql = "($tableAlias.data='$blankValue'";
			}
			if($value !== "0" && $blankValue !== "0" && !$field->type->isEmptyValue($field, "0")) {
				// if zero is not considered an empty value, exclude it from matching
				// if the search isn't specifically for a "0"
				$sql .= " AND $tableAlias.data!='0'";
			}
			$sql .= ")";
			
		} else if($operator == '!=' || $operator == '<>') {
			// not equals
			// $whereType = 'AND';
			if($value === "0" && !$field->type->isEmptyValue($field, "0")) {
				// may match rows with no value present
				$sql = "$tableAlias.pages_id IS NULL OR ($tableAlias.data!='0'";
				
			} else if($blankIsObject) {
				$sql = "$tableAlias.pages_id IS NOT NULL AND ($tableAlias.data IS NOT NULL";
				
			} else {
				$sql = "$tableAlias.pages_id IS NOT NULL AND ($tableAlias.data!='$blankValue'";
				if($blankValue !== "0" && !$field->type->isEmptyValue($field, "0")) {
					$sql .= " OR $tableAlias.data='0'";
				}
			}
			$sql .= ")";
			
		} else if($operator == '<' || $operator == '<=') {
			// less than 
			if($value > 0 && $field->type->isEmptyValue($field, "0")) {
				// non-rows can be included as counting for 0
				$value = $database->escapeStr($value); 
				$sql = "$tableAlias.pages_id IS NULL OR $tableAlias.data$operator'$value'";
			} else {
				// we won't handle it here
				return false; 
			}
		} else if($operator == '>' || $operator == '>=') {
			if($value < 0 && $field->type->isEmptyValue($field, "0")) {
				// non-rows can be included as counting for 0
				$value = $database->escapeStr($value);
				$sql = "$tableAlias.pages_id IS NULL OR $tableAlias.data$operator'$value'";
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
		$user = $this->wire('user');
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
		
		$hasWhereHook = $this->wire('hooks')->isHooked('PageFinder::getQueryAllowedTemplatesWhere()');

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

		$guestRoleID = $this->wire('config')->guestUserRolePageID; 
		$cacheUserID = $user->id;

		if($user->isGuest()) {
			// guest 
			foreach($this->wire('templates') as $template) {
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

			foreach($this->wire('templates') as $template) {
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
		foreach($this->wire('templates') as $template) {
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
		if($query) {}
		return $where;
	}

	protected function getQuerySortSelector(DatabaseQuerySelect $query, Selector $selector) {

		// $field = is_array($selector->field) ? reset($selector->field) : $selector->field; 
		$values = is_array($selector->value) ? $selector->value : array($selector->value); 	
		$fields = $this->wire('fields'); 
		$pages = $this->wire('pages');
		$database = $this->wire('database');
		$user = $this->wire('user'); 
		$language = $this->wire('languages') && $user->language ? $user->language : null;
		
		foreach($values as $value) {

			$fc = substr($value, 0, 1); 
			$lc = substr($value, -1); 
			$value = trim($value, "-+"); 
			$subValue = '';
			// $terValue = ''; // not currently used, here for future use

			if(strpos($value, ".")) {
				list($value, $subValue) = explode(".", $value, 2); // i.e. some_field.title
				if(strpos($subValue, ".")) {
					list($subValue, $terValue) = explode(".", $subValue, 2);
					$terValue = $this->wire('sanitizer')->fieldName($terValue);
					if(strpos($terValue, ".")) throw new PageFinderSyntaxException("$value.$subValue.$terValue not supported");
				}
				$subValue = $this->wire('sanitizer')->fieldName($subValue);
			}
			$value = $this->wire('sanitizer')->fieldName($value);
			
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
				if($value == 'name' && $language && !$language->isDefault()  && $this->wire('modules')->isInstalled('LanguageSupportPageNames')) {
					// substitute language-specific name field when LanguageSupportPageNames is active and language is not default
					$value = "if(pages.name$language!='', pages.name$language, pages.name)";
				} else {
					$value = "pages." . $database->escapeCol($value);
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
					$blankValue = $field->type->getBlankValue($this->wire('pages')->newNullPage(), $field);
				}

				$query->leftjoin("$table AS $tableAlias ON $tableAlias.pages_id=pages.$idColumn");

				if($subValue === 'count') {
					if($this->isRepeaterFieldtype($field->type)) {
						// repeaters have a native count column that can be used for sorting
						$value = "$tableAlias.count";
					} else {
						// sort by quantity of items
						$value = "COUNT($tableAlias.data)";
					}

				} else if(is_object($blankValue) && ($blankValue instanceof PageArray || $blankValue instanceof Page)) {
					// If it's a FieldtypePage, then data isn't worth sorting on because it just contains an ID to the page
					// so we also join the page and sort on it's name instead of the field's "data" field.
					if(!$subValue) $subValue = 'name';
					$tableAlias2 = "_sort_" . ($useParent ? 'parent' : 'page') . "_$fieldName" . ($subValue ? "_$subValue" : '');
				
					if($this->wire('fields')->isNative($subValue) && $pages->loader()->isNativeColumn($subValue)) {
						$query->leftjoin("pages AS $tableAlias2 ON $tableAlias.data=$tableAlias2.$idColumn");
						$value = "$tableAlias2.$subValue";
						if($subValue == 'name' && $language && !$language->isDefault()
							&& $this->wire('modules')->isInstalled('LanguageSupportPageNames')
						) {
							// append language ID to 'name' when performing sorts within another language and LanguageSupportPageNames in place
							$value = "if($value$language!='', $value$language, $value)";
						}
					} else if($subValue == 'parent') {
						$query->leftjoin("pages AS $tableAlias2 ON $tableAlias.data=$tableAlias2.$idColumn");
						$value = "$tableAlias2.name";
						
					} else {
						$subValueField = $this->wire('fields')->get($subValue);
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
					$value = "$tableAlias." . ($subValue ? $subValue : "data"); ; 
				}
			}
		
			$descending = $fc == '-' || $lc == '-';
			if($this->lastOptions['reverseSort']) $descending = !$descending;
			if($descending) {
				$query->orderby("$value DESC", true);
			} else {
				$query->orderby("$value", true);
			}
		}
	}

	protected function getQueryStartLimit(DatabaseQuerySelect $query) {

		$start = $this->start; 
		$limit = $this->limit;

		if($limit) {
			$limit = (int) $limit;
			$input = $this->wire('input');
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
		
		$database = $this->wire('database'); 

		// determine whether we will include use of multi-language page names
		if($this->modules->isInstalled('LanguageSupportPageNames') && count($this->wire('languages'))) {
			$langNames = array();
			foreach($this->wire('languages') as $language) {
				if(!$language->isDefault()) $langNames[$language->id] = "name" . (int) $language->id;
			}
			if(!count($langNames)) $langNames = null;
		} else {
			$langNames = null;
		}

		if($this->modules->isInstalled('PagePaths') && !$langNames) {
			// @todo add support to PagePaths module for LanguageSupportPageNames
			$pagePaths = $this->modules->get('PagePaths');
			/** @var PagePaths $pagePaths */
			$pagePaths->getMatchQuery($query, $selector); 
			return;
		}

		if($selector->operator !== '=') {
			throw new PageFinderSyntaxException("Operator '$selector->operator' is not supported for path or url unless: 1) non-multi-language; 2) you install the PagePaths module."); 
		}

		if($selector->value == '/') {
			$parts = array();
			$query->where("pages.id=1");
		} else {
			$selectorValue = $selector->value;
			if(is_array($selectorValue)) {
				// only the PagePaths module can perform OR value searches on path/url
				if($langNames) {
					throw new PageFinderSyntaxException("OR values not supported for multi-language 'path' or 'url'"); 
				} else {
					throw new PageFinderSyntaxException("OR value support of 'path' or 'url' requires core PagePaths module");
				}
			}
			if($langNames) $selectorValue = $this->wire('modules')->get('LanguageSupportPageNames')->updatePath($selectorValue); 
			$parts = explode('/', rtrim($selectorValue, '/')); 
			$part = $database->escapeStr($this->wire('sanitizer')->pageName(array_pop($parts), Sanitizer::toAscii)); 
			$sql = "pages.name='$part'";
			if($langNames) foreach($langNames as $name) $sql .= " OR pages.$name='$part'";
			$query->where($sql); 
			if(!count($parts)) $query->where("pages.parent_id=1");
		}

		$alias = 'pages';
		$lastAlias = 'pages';

		/** @noinspection PhpAssignmentInConditionInspection */
		while($n = count($parts)) {
			$part = $database->escapeStr($this->wire('sanitizer')->pageName(array_pop($parts), Sanitizer::toAscii)); 
			if(strlen($part)) {
				$alias = "parent$n";
				//$query->join("pages AS $alias ON ($lastAlias.parent_id=$alias.id AND $alias.name='$part')");
				$sql = "pages AS $alias ON ($lastAlias.parent_id=$alias.id AND ($alias.name='$part'";
				if($langNames) foreach($langNames as $id => $name) {
					// $status = "status" . (int) $id;
					// $sql .= " OR ($alias.$name='$part' AND $alias.$status>0) ";
					$sql .= " OR $alias.$name='$part'";
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
		/** @var WireDatabasePDO $database */
		$database = $this->wire('database'); 
		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');

		foreach($fields as $field) { 

			// the following fields are defined in each iteration here because they may be modified in the loop
			$table = "pages";
			$operator = $selector->operator;
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

			if($field != 'children' && !$this->wire('fields')->isNative($field)) {
				$subfield = $field;
				$field = '_pages';
			}
			
			$isParent = $field === 'parent' || $field === 'parent_id';
			$isChildren = $field === 'children';
			$isPages = $field === '_pages';

			if($isParent || $isChildren || $isPages) {
				// parent, children, pages

				if(($isPages || $isParent) && (!$subfield || in_array($subfield, array('id', 'path', 'url')))) {
					// match by location (id or path)
					// convert parent fields like '/about/company/history' to the equivalent ID
					foreach($values as $k => $v) {
						if(ctype_digit("$v")) continue; 
						$v = $sanitizer->pagePathName($v, Sanitizer::toAscii); 
						if(strpos($v, '/') === false) $v = "/$v"; // prevent a plain string with no slashes
						// convert path to id
						$parent = $this->wire('pages')->get($v);
						$values[$k] = $parent instanceof NullPage ? null : $parent->id;
					}
					$this->parent_id = null;
					if($isParent) {
						$field = 'parent_id'; 
						if(count($values) == 1 && count($fields) == 1 && $selector->operator() === '=') {
							$this->parent_id = reset($values);
						} 
					}

				} else {
					// matching by a parent's native or custom field (subfield)

					if(!$this->wire('fields')->isNative($subfield)) {
						$finder = $this->wire(new PageFinder());
						$finderMethod = 'findIDs';
						$includeSelector = 'include=all';
						if($field === 'children' || $field === '_pages') {
							if($subfield) {
								$s = '';
								if($field === 'children') $finderMethod = 'findParentIDs'; 
								// inherit include mode from main selector
								$includeSelector = trim(
									$selectors->getSelectorByField('include') . ',' . 
									$selectors->getSelectorByField('status') . ',' . 
									$selectors->getSelectorByField('check_access'), ','
								);
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
							"$s$subfield$operator" . $sanitizer->selectorValue($values), ','
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
			} else {
				// primary field is not 'parent', 'children' or 'pages'
			}

			if(count($IDs)) {
				// parentIDs or IDs found via another query, and we don't need to match anything other than the parent ID
				$in = $selector->not ? "NOT IN" : "IN"; 
				$sql .= in_array($field, array('parent', 'parent_id')) ? "$table.parent_id " : "$table.id ";
				$sql .= "$in(" . implode(',', $IDs) . ")";

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
					if(count($values) == 1 && $selector->operator() === '=') $this->templates_id = reset($values);
					if(!ctype_digit("$value")) $value = (($template = $this->wire('templates')->get($value)) ? $template->id : 0); 
				}

				if(in_array($field, array('created', 'modified', 'published'))) {
					// prepare value for created, modified or published date fields
					if(!ctype_digit($value)) $value = strtotime($value); 
					$value = date('Y-m-d H:i:s', $value); 
				}

				if(in_array($field, array('id', 'parent_id', 'templates_id', 'sort'))) {
					$value = (int) $value; 
				}
				
				$isName = $field === 'name' || strpos($field, 'name') === 0; 

				if($isName && $operator == '~=') {
					// handle one or more space-separated full words match to 'name' field in any order
					$s = '';
					foreach(explode(' ', $value) as $word) {
						$word = $database->escapeStr($sanitizer->pageName($word, Sanitizer::toAscii)); 
						$s .= ($s ? ' AND ' : '') . "$table.$field RLIKE '" . '[[:<:]]' . $word . '[[:>:]]' . "'";
					}

				} else if($isName && in_array($operator, array('%=', '^=', '$=', '%^=', '%$=', '*='))) {
					// handle partial match to 'name' field
					$value = $database->escapeStr($sanitizer->pageName($value, Sanitizer::toAscii));
					if($operator == '^=' || $operator == '%^=') $value = "$value%";
						else if($operator == '$=' || $operator == '%$=') $value = "%$value";
						else $value = "%$value%";
					$s = "$table.$field LIKE '$value'";
					
				} else if(!$database->isOperator($operator)) {
					throw new PageFinderSyntaxException("Operator '{$operator}' is not supported for '$field'."); 

				} else {
					if($isName) $value = $sanitizer->pageName($value, Sanitizer::toAscii); 
					$value = $database->escapeStr($value); 
					$s = "$table." . $field . $operator . ((ctype_digit("$value") && $field != 'name') ? ((int) $value) : "'$value'");
				
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
				if($SQL) $SQL .= " OR ($sql)"; 
					else $SQL .= "($sql)";
			}
		}

		if(count($fields) > 1) $SQL = "($SQL)";

		$query->where($SQL); 
		//$this->nativeWheres[] = $SQL; 
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
				$parent = $this->wire('pages')->newNullPage();
				$path = $this->wire('sanitizer')->path($parent_id);
				if($path) $parent = $this->wire('pages')->get('/' . trim($path, '/') . '/');
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

		if(!in_array($selector->operator, array('=', '<', '>', '<=', '>=', '!='))) 
			throw new PageFinderSyntaxException("Operator '{$selector->operator}' not allowed for 'num_children' selector."); 

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
			if($this->wire('fields')->isNative($name)) {
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
			if($this->wire('config')->debug || $this->wire('config')->installed > 1549299319) {
				// debug mode or anything installed after February 4th, 2019
				$f = reset($singles);
				$fs = implode('|', $fields);
				throw new PageFinderSyntaxException("Field '$f' cannot OR with other fields in '$fs'");
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
	 * @return int
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
		$field = null;
		
		if($fieldName === 'parent' || $fieldName === 'children') {
			return $fieldName; // early exit
			
		} else if(is_object($fieldName) && $fieldName instanceof Field) {
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
			$field = $this->wire('fields')->get($fieldName);
			
		} else {
			$field = $this->wire('fields')->get($fieldName);
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
				if(is_object($test) && ($test instanceof Page || $test instanceof PageArray)) {
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
		/** @var array $fields */
		$fields = $data['fields'];
		/** @var string $subfields */
		$subfields = $data['subfields'];
		/** @var Selector $selector */
		$selector = $data['selector'];
		/** @var DatabaseQuerySelect $query */
		$query = $data['query'];
		/** @var Wire|null $value */
		$value = $this->wire($fieldName);
		
		if($value) {
			// found an API var
			if(count($fields) > 1) {
				throw new PageFinderSyntaxException("You may only match 1 API variable at a time");
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
	
		return false;
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
		
		/** @var array $fields */
		$fields = $data['fields'];
		/** @var string $subfields */
		$subfields = $data['subfields'];
		/** @var Selectors $selectors */
		$selectors = $data['selectors'];
		/** @var Selector $selector */
		$selector = $data['selector'];
		/** @var DatabaseQuerySelect $query */
		$query = $data['query'];
		
		if(empty($subfields)) throw new PageFinderSyntaxException("When using owner a subfield is required");

		list($ownerFieldName,) = explode('__owner', $fieldName);
		$ownerField = $this->wire('fields')->get($ownerFieldName);
		if(!$ownerField) return false;
		
		$ownerTypes = array('FieldtypeRepeater', 'FieldtypePageTable', 'FieldtypePage');
		if(!wireInstanceOf($ownerField->type, $ownerTypes)) return false;
		if($selector->get('owner_processed')) return true;
	
		static $ownerNum = 0;
		$ownerNum++;
	
		// determine which templates are using $ownerFieldName
		$templateIDs = array();
		foreach($this->wire('templates') as $template) {
			if($template->hasField($ownerFieldName)) {
				$templateIDs[$template->id] = $template->id;
			}
		}
	
		if(!count($templateIDs)) $templateIDs[] = 0;
		$templateIDs = implode('|', $templateIDs);

		// determine include=mode
		$include = $selectors->getSelectorByField("include");
		$include = $include ? $include->value : 'hidden';
	
		/** @var Selectors $ownerSelectors Build selectors */
		$ownerSelectors = $this->wire(new Selectors("templates_id=$templateIDs, include=$include, get_total=0"));
		$ownerSelector = clone $selector;

		if(count($fields) > 1) {
			// OR fields present
			array_shift($fields);
			$subfields = array($subfields);
			foreach($fields as $name) {
				if(strpos($name, "$fieldName.") === 0) {
					list(,$name) = explode('__owner.', $name); 	
					$subfields[] = $name;
				} else {
					throw new PageFinderSyntaxException(
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
		$ids = $finder->findIDs($ownerSelectors);
		
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
	public function getPageArrayData(PageArray $pageArray = null) {
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
			if($this->wire('fields')->isNative($fieldName)) return true;
		}

		if(count($fieldNames)) {
			$fieldsStr = ':' . implode(':', $fieldNames) . ':';
			if(strpos($fieldsStr, ':parent.') !== false) return true;
			if(strpos($fieldsStr, ':children.') !== false) return true;
			if(strpos($fieldsStr, ':child.') !== false) return true;
		}

		return false;
	}
}

