<?php namespace ProcessWire;

/**
 * ProcessWire Pages Path Finder
 * 
 * #pw-headline Pages Path Finder
 * #pw-var $pages->pathFinder
 * #pw-breadcrumb Pages
 * #pw-summary Enables finding pages by path, optionally with URL segments, pagination numbers, language prefixes, etc.
 * #pw-body = 
 * This is built for use by the PagesRequest class and ProcessPageView module, but can also be useful from the public API.
 * The most useful method is the `get()` method which returns a verbose array of information about the given path. 
 * Methods in this class should be acessed from `$pages->pathFinder()`, i.e. 
 * ~~~~~
 * $result = $pages->pathFinder()->get('/en/foo/bar/page3');
 * ~~~~~
 * Note that PagesPathFinder does not perform any access control checks, so if using this class then validate access 
 * afterwards when appropriate.
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 * 
 * @todo:
 * Determine how to handle case where this class is called before 
 * LanguageSupport module has been loaded (for multi-language page names)
 *
 */

class PagesPathFinder extends Wire {

	/**
	 * @var Pages
	 * 
	 */
	protected $pages;

	/**
	 * Default options for the get() method
	 * 
	 * @var array
	 * 
	 * - useLanguages: Allows for selection of multi-language path paths (requires LanguageSupportPageNames module)
	 * - usePathPaths: Allow use of PagePaths module to attempt to find a shortcut?
	 * - useGlobalUnique: Allow support of shortcut for globally unique page names?
	 * - useExcludeRoots: Exclude paths that do not have a known root segment, improves performance when lots of 404s. 
	 * - useHistory: Allow detecting of previous page paths via the PagePathHistory module?
	 * - verbose: Return more verbose array that includes details on each path segment?
	 * 
	 */
	protected $defaults = array(
		'useLanguages' => true,
		'usePagePaths' => true,
		'useGlobalUnique' => true,
		'useExcludeRoot' => false,
		'useHistory' => true,
		'verbose' => true,
		'test' => false,
	);

	/**
	 * @var array
	 * 
	 */
	protected $options = array();

	/**
	 * @var bool
	 * 
	 */
	protected $verbose = true;

	/**
	 * @var array
	 * 
	 */
	protected $methods = array();

	/**
	 * @var array
	 * 
	 */
	protected $result = array();

	/**
	 * @var Template|null
	 * 
	 */
	protected $template = null;

	/**
	 * @var bool|null
	 * 
	 */
	protected $admin = null;

	/**
	 * @var array|null
	 * 
	 */
	protected $useLanguages = null;

	/**
	 * URL part types (for reference)
	 * 
	 * @var array
	 * 
	 */
	protected $partTypes = array(
		'pageName', // references a page name directly
		'urlSegment', // part of urlSegmentStr after page path
		'pageNum', // pagination number segment
		'language', // language segment prefix
	);

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 * 
	 */
	public function __construct(Pages $pages) {
		$this->pages = $pages;
		parent::__construct();
	}

	/**
	 * Init for new get() 
	 * 
	 * @param string $path
	 * @param array $options
	 * 
	 */
	protected function init($path, array $options) {
		
		$this->options = array_merge($this->defaults, $options);
		$this->methods = array();
		$this->useLanguages = $this->options['useLanguages'] ? $this->languages(true) : array();
		$this->verbose = $this->options['verbose'] || !empty($this->useLanguages);
		$this->result = $this->getBlankResult(array('request' => $path));
		$this->template = null;
		$this->admin = null;
		
		if(empty($this->pageNameCharset)) {
			$this->pageNameCharset = $this->wire()->config->pageNameCharset;
		}
	}
	
	/**
	 * Get verbose array of info about a given page path
	 *
	 * This method accepts a page path (not URL) with optional language segment
	 * prefix, additional URL segments and/or pagination number. It translates
	 * the given path to information about what page it matches, what type of
	 * request it would result in.
	 *
	 * If the `response` property in the return value is 301 or 302, then the
	 * `redirect` property will be populated with a recommend redirect path.
	 * 
	 * Please access this method from `$pages->pathFinder()->get('…');`
	 *
	 * Below is an example when given a `$path` argument of `/en/foo/bar/page3` 
	 * on a site that has default language homepage segment of `en`, a page living 
	 * at `/foo/` that accepts URL segment `bar` and has pagination enabled;
	 * ~~~~~
	 * $array = $pages->pathFinder()->get('/en/foo/bar/page3'); 
	 * ~~~~~
	 * ~~~~~
	 * [
	 *   'request' => '/en/foo/bar/page3',
	 *   'response' => 200, // one of 200, 301, 302, 404, 414
	 *   'type' => 'ok', // response type name
	 *   'errors' => [], // array of error messages indexed by error name
	 *   'redirect` => '/redirect/path/', // suggested path to redirect to or blank
	 *   'page' => [
	 *      'id' => 123, // ID of the page that was found
	 *      'parent_id' => 456,
	 *      'templates_id' => 12,
	 *      'status' => 1,
	 *      'name' => 'foo',
	 *   ],
	 *   'language' => [
	 *      'name' => 'default', // name of language detected
	 *      'segment' => 'en', // segment prefix in path (if any)
	 *      'status' => 1, // language status where 1 is on, 0 is off
	 *    ],
	 *   'parts' => [ // all the parts of the path identified
	 *     [
	 *       'type' => 'language',
	 *       'value' => 'en',
	 *       'language' => 'default'
	 *     ],
	 *     [
	 *       'type' => 'pageName',
	 *       'value' => 'foo',
	 *       'language' => 'default'
	 *     ],
	 *     [
	 *       'type' => 'urlSegment',
	 *       'value' => 'bar',
	 *       'language' => ''
	 *     ],
	 *     [
	 *       'type' => 'pageNum',
	 *       'value' => 'page3',
	 *       'language' => 'default'
	 *     ],
	 *   ],
	 *   'urlSegments' => [ // URL segments identified in order
	 *      'bar',
	 *   ],
	 *   'urlSegmentStr' => 'bar', // URL segment string
	 *   'pageNum' => 3, // requested pagination number
	 *   'pageNumPrefix' => 'page', // prefix used in pagination number
	 *   'scheme' => 'https', // blank if no scheme required, 'https' or 'http' if one of those is required
	 *   'method' => 'pagesRow', // method(s) that were used to find the page
	 * ]
	 * ~~~~~
	 *
	 * @param string $path Page path optionally including URL segments, language prefix, pagination number
	 * @param array $options
	 *  - `useLanguages` (bool): Allow use of multi-language page names? (default=true)
	 *     Requires LanguageSupportPageNames module installed.
	 *  - `useHistory` (bool): Allow use historical path names? (default=true)
	 *     Requires PagePathHistory module installed.
	 *  - `verbose` (bool): Return verbose array of information? (default=true)
	 *     If false, some optional information will be omitted in return value.
	 * @return array
	 * @see PagesPathFinder::getPage()
	 *
	 */
	public function get($path, array $options = array()) {
		
		$this->init($path, $options);
		
		// see if we can take a shortcut
		if($this->getShortcut($path)) return $this->result;
		
		// convert path to array of parts (page names)
		$parts = $this->getPathParts($path);
		
		// find a row matching the path/parts in the pages table
		$row = $this->getPagesRow($parts);
		$path = $this->applyPagesRow($parts, $row);
	
		// if we didn’t match row or matched with URL segments, see if history match available
		if($this->options['useHistory']) {
			if(!$row || ($this->result['page']['id'] === 1 && count($this->result['urlSegments']))) {
				if($this->getPathHistory($this->result['request'])) return $this->result;
			}
		}

		return $this->finishResult($path);
	}

	/**
	 * Given a path, get a Page object or NullPage if not found
	 * 
	 * This method is like the `get()` method except that it returns a `Page`
	 * object rather than an array. It sets a `_pagePathFinder` property to the 
	 * returned Page, which is an associative array containing the same result 
	 * array returned by the `get()` method.
	 * 
	 * Please access this method from `$pages->pathFinder()->getPage('…');`
	 * 
	 * @param string $path
	 * @param array $options
	 * @return NullPage|Page
	 * @see PagesPathFinder::get()
	 * 
	 */
	public function getPage($path, array $options = array()) {
		if(!isset($options['verbose'])) $options['verbose'] = false;
		$result = $this->get($path, $options);
		if($result['response'] >= 400) {
			$page = $this->pages->newNullPage();
		} else {
			$page = $this->pages->getOneById($result['page']['id'], array(
				'template' => $this->getResultTemplate(),
				'parent_id' => $result['page']['parent_id'],
			));
		}
		$page->setQuietly('_pagePathFinder', $result);
		return $page;
	}
	
	
	/**
	 * Find a row for given $parts in pages table
	 * 
	 * @param array $parts
	 * @return array|null
	 * 
	 */
	protected function getPagesRow(array $parts) {
	
		$database = $this->wire()->database;
		
		$selects = array();
		$binds = array();
		$joins = array();
		$wheres = array();

		$lastTable = 'pages';
		
		// build the query to match each part
		foreach($parts as $n => $name) {
			
			$name = $this->pageNameToAscii($name);
			$key = "p$n";
			$table = $n ? $key : 'pages';

			$selects[] = "$table.id AS {$key}_id";
			$selects[] = "$table.parent_id AS {$key}_parent_id";
			$selects[] = "$table.templates_id AS {$key}_templates_id";
			$selects[] = "$table.status AS {$key}_status";
			$selects[] = "$table.name AS {$key}_name";

			$bindKey = "{$key}_name";
			$binds[$bindKey] = $name;

			$whereNames = array("$table.name=:$bindKey");

			foreach($this->useLanguages as $lang) {
				/** @var Language $lang */
				if($lang->isDefault()) continue;
				$whereNames[] = "$table.name$lang->id=:$bindKey";
				$selects[] = "$table.name$lang->id AS {$key}_name$lang->id";
				$selects[] = "$table.status$lang->id AS {$key}_status$lang->id";
			}

			$whereNames = implode(' OR ', $whereNames);

			if($n) {
				$joins[] = "LEFT JOIN pages AS $table ON $table.parent_id=$lastTable.id AND ($whereNames)";
			} else {
				$where = "($table.parent_id=1 AND ($whereNames))";
				$wheres[] = $where;
			}

			$lastTable = $table;
		}
		
		if(!count($selects)) {
			$this->addResultNote('No selects from pagesRow');
			return null; 
		}

		$selects = implode(', ', $selects);
		$joins = implode(" \n", $joins);
		$wheres = implode(" AND ", $wheres);
		$sql = "SELECT $selects \nFROM pages \n$joins \nWHERE $wheres";
		$query = $database->prepare($sql);

		foreach($binds as $bindKey => $bindValue) {
			$query->bindValue(":$bindKey", $bindValue);
		}

		$query->execute();
		$rowCount = (int) $query->rowCount();
		$row = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();

		// multiple matches error (not likely)
		if($rowCount > 1) {
			$row = null;
			$this->addMethod('pagesRow', false, 'Multiple matches');
		} else if($rowCount) {
			$this->addMethod('pagesRow', true, "OK [id:$row[p0_id]]");
		} else {
			$this->addMethod('pagesRow', false, "No rows");
		}

		return $row;
	}

	/**
	 * Apply a found pages table row to the $result and return corresponding path
	 * 
	 * @param array $parts
	 * @param array|null $row
	 * @return string Path string
	 * 
	 */
	protected function applyPagesRow(array $parts, $row) {
	
		$config = $this->wire()->config;
		$maxUrlSegmentLength = $config->maxUrlSegmentLength;
		$maxUrlSegments = $config->maxUrlSegments;
		$result = &$this->result;
		
		// array of [language name] => [ 'a', 'b', 'c' ] (from /a/b/c/)
		$namesByLanguage = array('default' => array());
		$statusByLanguage = array();
		
		// determine which parts matched and which didn’t
		foreach($parts as $n => $name) {

			$key = "p$n";
			$id = $row ? (int) $row["{$key}_id"] : 0;

			if(!$id) {
				// if it didn’t resolve to DB page name then it is a URL segment
				if(strlen($name) > $maxUrlSegmentLength) {
					$name = substr($name, 0, $maxUrlSegmentLength);
					if($config->longUrlResponse >= 300) {
						$result['response'] = $config->longUrlResponse;
						$this->addResultError('urlSegmentLength', 'URL segment length > config.maxUrlSegmentLength');
					}
				}
				if(count($result['urlSegments']) + 1 > $maxUrlSegments) {
					if($config->longUrlResponse >= 300) {
						$this->addResultError('urlSegmentMAX', 'Number of URL segments exceeds config.maxUrlSegments');
						$result['response'] = $config->longUrlResponse;
						break;
					}
				} else {
					$result['urlSegments'][] = $name;
					if($this->verbose) {
						$result['parts'][] = array(
							'type' => 'urlSegment',
							'value' => $name,
							'language' => ''
						);
					}
				}
				continue;
			}

			$names[] = $name;
			$nameDefault = $this->pageNameToUTF8($row["{$key}_name"]);
			$namesByLanguage['default'][] = $nameDefault;

			// this is intentionally re-populated on each iteration 
			$result['page'] = array(
				'id' => $id,
				'parent_id' => (int) $row["{$key}_parent_id"],
				'templates_id' => (int) $row["{$key}_templates_id"],
				'status' => (int) $row["{$key}_status"],
				'name' => $nameDefault, 
			);

			if($this->verbose && $nameDefault === $name) {
				$result['parts'][] = array(
					'type' => 'pageName',
					'value' => $name,
					'language' => 'default'
				);
			}

			foreach($this->useLanguages as $language) {
				/** @var Language $language */
				if($language->isDefault()) continue;
				$nameLanguage = $this->pageNameToUTF8($row["{$key}_name$language->id"]);
				$statusLanguage = (int) $row["{$key}_status$language->id"];
				$statusByLanguage[$language->name] = $statusLanguage;
				if($nameLanguage != $nameDefault && $nameLanguage === $name) {
					if($this->verbose) {
						$result['parts'][] = array(
							'type' => 'pageName',
							'value' => $nameLanguage,
							'language' => $language->name
						);
					}
					// if there is a part in a non-default language, identify that as the
					// intended language for the entire path
					if(empty($result['language']['name'])) $this->setResultLanguage($language);
				}
				if(!isset($namesByLanguage[$language->name])) $namesByLanguage[$language->name] = array();
				$namesByLanguage[$language->name][] = strlen("$nameLanguage") ? $nameLanguage : $nameDefault;
			}
		}
		
		// identify if a pageNum is present, must be applied before creation of urlSegmentStr and path
		$this->applyResultPageNum($parts);

		$langName = empty($result['language']['name']) ? 'default' : $result['language']['name'];
		if(!isset($namesByLanguage[$langName])) $langName = 'default';
		
		$path = '/' . implode('/', $namesByLanguage[$langName]);

		if($langName === 'default') {
			$result['page']['path'] = $path;
		} else {
			$result['page']['path'] = '/' . implode('/', $namesByLanguage['default']);
		}

		if(count($this->useLanguages)) {
			$langStatus = ($langName === 'default' ? 1 : $statusByLanguage[$langName]);
			$this->setResultLanguageStatus($langStatus);
		}
		
		return $path;
	}

	/**
	 * Prepare $path and convert to array of $parts
	 * 
	 * If language segment detected then remove it and populate language to result
	 * 
	 * @param string $path
	 * @return array
	 * 
	 */
	protected function getPathParts($path) {

		$result = &$this->result;
		$config = $this->wire()->config;
		$sanitizer = $this->wire()->sanitizer;
		$maxDepth = $config->maxUrlDepth;
		$maxUrlSegmentLength = $config->maxUrlSegmentLength;
		$maxPartLength = $maxUrlSegmentLength > 128 ? $maxUrlSegmentLength : 128;
		$maxPathLength = ($maxPartLength * $maxUrlSegmentLength) + $maxDepth;
		$badNames = array();
		$path = trim($path, '/');
		$lastPart = '';
		
		if($this->strlen($path) > $maxPathLength) {
			$result['response'] = $config->longUrlResponse; // 414=URI too long
			$this->addResultError('pathLengthMAX', "Path length exceeds max allowed $maxPathLength");
			$path = substr($path, 0, $maxPathLength);
		}
		
		$parts = explode('/', trim($path, '/'));

		if(count($parts) > $maxDepth) {
			$parts = array_slice($parts, 0, $maxDepth);
			$result['response'] = $config->longUrlResponse;
			$this->addResultError('pathDepthMAX', 'Path depth exceeds config.maxUrlDepth');
		} else if($path === '/' || $path === '' || !count($parts)) {
			return array();
		}
		
		foreach($parts as $n => $name) {
			$lastPart = $name;
			if(ctype_alnum($name)) continue;
			$namePrev = $name;
			$name = $sanitizer->pageNameUTF8($name);
			$parts[$n] = $name;
			if($namePrev !== $name) $badNames[$n] = $namePrev;
		}

		if(stripos($lastPart, 'index.') === 0 && preg_match('/^index\.(php\d?|s?html?)$/i', $lastPart)) {
			// index file will be allowed as URL segment, or 301 redirect if not allowed as URL segment
			$this->addResultError('indexFile', 'Path had index file', true);
		}
	
		if($result['response'] < 400 && count($badNames)) {
			$result['response'] = 400; // 400=Bad request
			$this->addResultError('pathBAD', 'Path contains invalid character(s)');
		}
		
		// identify language from parts	and populate to result
		$this->getPathPartsLanguage($parts);

		return $parts;
	}

	/**
	 * Get language that path is in and apply it to result
	 * 
	 * @param array $parts
	 * @return Language|null
	 * 
	 */
	protected function getPathPartsLanguage(array &$parts) {

		if(!count($this->useLanguages) || !count($parts)) return null;
	
		$languages = $this->languages(); /** @var Languages|array $languages */
		
		if(!count($languages)) return null;
	
		$firstPart = reset($parts);
		$languageKey = array_search($firstPart, $this->languageSegments());
		
		if($languageKey === false) {
			// if no language segment present then do not identify language yet
			// allows for page /es/bici/ to still match /bici/ and redirect to /es/bici/
			return null;
			// Comment above and uncomment below to disable language detection without language prefix
			// $language = $languages->getDefault();
			// $segment = $this->languageSegment('default');
		} else {
			$segment = array_shift($parts);
			$language = $languages->get($languageKey);
		}
		
		if(!$language || !$language->id) return null;

		$this->addResultNote("Detected language '$language->name' from first segment '$segment'"); 
		$this->setResultLanguage($language, $segment);
		
		if($this->verbose) {
			$this->result['parts'][] = array(
				'type' => 'language',
				'value' => $segment,
				'language' => $language->name
			);
		}

		// reduce to just applicable language to limit name columns
		// searched for by getPagesRow() method
		$this->useLanguages = array($language);
		
		return $language;
	}


	/*** RESULT *********************************************************************************/

	/**
	 * Build blank result/return value array
	 *
	 * @param array $result Optionally return blank result merged with this one
	 * @return array
	 *
	 */
	protected function getBlankResult(array $result = array()) {

		$blankResult = array(
			'request' => '',
			'response' => 0,
			'type' => '',
			'redirect' => '',
			'errors' => array(),
			'page' => array(
				'id' => 0,
				'parent_id' => 0,
				'templates_id' => 0,
				'status' => 0,
				'path' => '',
			),
			'language' => array(
				'name' => '', // intentionally blank
				'segment' => '',
				'status' => -1, // -1=not yet set
			),
			'parts' => array(),
			'urlSegments' => array(),
			'urlSegmentStr' => '',
			'pageNum' => 1,
			'pageNumPrefix' => '',
			'pathAdd' => '', // valid URL segments, page numbers, trailing slash, etc.
			'scheme' => '',
			'methods' => array(),
			'notes' => array(),
		);

		if(empty($result)) return $blankResult;

		$result = array_merge($blankResult, $result);

		return $result;
	}

	/**
	 * Update paths for template info like urlSegments and pageNum and populate urls property
	 *
	 * @param string $path
	 * @return bool
	 *
	 */
	protected function applyResultTemplate($path) {

		$config = $this->wire()->config;
		$fail = false;
		$result = &$this->result;

		if(empty($result['page']['templates_id']) && $this->isHomePath($path)) {
			$this->applyResultHome();
		}

		$template = $this->getResultTemplate();
		$slashUrls = $template ? (int) $template->slashUrls : 0;
		$useTrailingSlash = $slashUrls ? 1 : -1; // 1=yes, 0=either, -1=no
		$hadTrailingSlash = substr($result['request'], -1) === '/';
		$https = $template ? (int) $template->https : 0;
		$appendPath = '';

		// populate urlSegmentStr property if applicable
		if(empty($result['urlSegmentStr']) && !empty($result['urlSegments'])) {
			$urlSegments = $result['urlSegments'];
			$result['urlSegmentStr'] = count($urlSegments) ? implode('/', $urlSegments) : '';
		}

		// if URL segments are present validate them
		if(strlen($result['urlSegmentStr'])) {
			if($template && ($template->urlSegments || $template->name === 'admin')) {
				if($template->isValidUrlSegmentStr($result['urlSegmentStr'])) {
					$appendPath .= "/$result[urlSegmentStr]";
					if($result['pageNum'] < 2) $useTrailingSlash = (int) $template->slashUrlSegments;
				} else {
					// ERROR: URL segments did not validate
					$this->addResultError('urlSegmentsBAD', "Invalid urlSegments for template $template");
					$fail = true;
				}
			} else {
				// template does not allow URL segments
				if($template) {
					$this->addResultError('urlSegmentsOFF', "urlSegments disabled for template $template");
				}
				$fail = true;
			}
		}

		// if a pageNum is present validate it
		if($result['pageNum'] > 1) {
			if($template && $template->allowPageNum) {
				$maxPageNum = $this->wire()->config->maxPageNum;
				if($maxPageNum && $result['pageNum'] > $maxPageNum && $template->name != 'admin') {
					$this->addResultError('pageNumBAD', "pageNum exceeds config.maxPageNum $maxPageNum");
					$fail = true;
				}
				$segment = $this->pageNumUrlSegment($result['pageNum'], $result['language']['name']);
				if(strlen($segment)) {
					$appendPath .= "/$segment";
				}
				$useTrailingSlash = (int) $template->slashPageNum;
			} else {
				// template does not allow page numbers
				$this->addResultError('pageNumOFF', "pageNum disabled for template $template");
				$fail = true;
			}
		}

		// determine whether path should end with a trailing slash or not
		if($useTrailingSlash > 0) {
			// trailing slash required
			$appendPath .= '/';
			if(!$hadTrailingSlash) $this->addResultNote('Enforced trailing slash');
		} else if($useTrailingSlash < 0) {
			// trailing slash disallowed
			if($hadTrailingSlash) $this->addResultNote('Enforced NO trailing slash');
		} else if($hadTrailingSlash) {
			// either acceptable, add slash if request had it
			$appendPath .= '/';
		}

		$_path = $path;
		if(strlen($appendPath)) $path = rtrim($path, '/') . $appendPath;
		
		if($fail || $_path !== $path || ($hadTrailingSlash && $useTrailingSlash < 0)) {
			if($fail && isset($result['errors']['indexFile']) && count($result['urlSegments']) === 1) {
				// allow for an /index.php or /index.html type urlSegmentStr to redirect rather than fail
				$fail = false;
			}
			$result['redirect'] = '/' . ltrim($path, '/');
		}
		
		$result['pathAdd'] = $appendPath;

		// determine if page requires specific https vs. http scheme
		if($https > 0 && !$config->noHTTPS) {
			// https required
			$result['scheme'] = 'https';
		} else if($https < 0) {
			// http required (https disallowed)
			$result['scheme'] = 'http';
		}

		return !$fail;
	}

	/**
	 * Apply result for homepage match
	 *
	 */
	protected function applyResultHome() {
		$config = $this->wire()->config;
		$home = $this->getHomepage();
		$this->template = $home->template;
		$this->result['page'] = array_merge($this->result['page'], array(
			'id' => $config->rootPageID,
			'templates_id' => $this->template->id,
			'parent_id' => 0,
			'status' => $home->status
		));
		$this->addMethod('resultHome', true);
	}

	/**
	 * Identify and populate language information in result
	 *
	 * @param string $path
	 * @return string $path Path is updated as needed
	 *
	 */
	protected function applyResultLanguage($path) {
		
		if(!count($this->useLanguages)) return $path;

		$result = &$this->result;

		/*
		if(empty($result['language']['name']) && $path != '/') {
			// @todo why?
			return $path;
		}
		*/
	
		// admin does not use LanguageSupportPageNames
		if($this->isResultInAdmin()) {
			return $this->updatePathForLanguage($path, 'default');
		}
		
		// if(empty($result['language']['name'])) $result['language']['name'] = 'default';
		
		// if there were any non-default language segments, let that dictate the language
		if(empty($result['language']['segment'])) {
			$useLangName = count($result['parts']) ? 'default' : $result['language']['name'];
			foreach($result['parts'] as $part) {
				$langName = $part['language'];
				if(empty($langName) || $langName === 'default') continue;
				$useLangName = $langName;
				break;
			}
			$segment = $this->languageSegment($useLangName);
			if($segment) $result['language']['segment'] = $segment;
			$result['language']['name'] = $useLangName;
		}
		
		$langName = $result['language']['name'];

		// prepend the redirect path with the language segment
		if(!empty($langName)) {
			$updatePath = $this->updatePathForLanguage($path);
			$redirect = &$result['redirect']; 
			if(empty($redirect) && $path != $updatePath) $redirect = $updatePath;
			if(!empty($redirect)) $redirect = $this->updatePathForLanguage($redirect);
			$path = $updatePath;
		}
	
		// determine language status if not yet known (likely only needed during shortcuts)
		if($result['language']['status'] < 0 && !empty($langName)) {
			if($langName === 'default') {
				$status = 1;
			} else if($result['page']['id']) {
				$status = $this->getPageLanguageStatus($result['page']['id'], $this->languageId($langName)); 
			} else {
				$status = -1;
			}
			$this->setResultLanguageStatus($status);
		}

		return $path;
	}

	/**
	 * Identify and populate pagination number from $result['urlSegments']
	 *
	 * @param array $parts
	 *
	 */
	protected function applyResultPageNum(array &$parts) {

		$result = &$this->result;
		$urlSegments = $result['urlSegments'];
		if(!count($urlSegments)) return;

		$lastSegment = end($urlSegments);
		if(!ctype_digit(substr($lastSegment, -1))) return;

		foreach($this->pageNumUrlPrefixes() as $languageName => $pageNumUrlPrefix) {
			if(strpos($lastSegment, $pageNumUrlPrefix) !== 0) continue;
			if(!preg_match('!^' . $pageNumUrlPrefix . '(\d+)$!i', $lastSegment, $matches)) continue;
			$segment = $matches[0];
			$pageNum = (int) $matches[1];
			$result['pageNum'] = $pageNum;
			$result['pageNumPrefix'] = $pageNumUrlPrefix;
			array_pop($urlSegments);
			array_pop($parts);
			if($this->verbose) {
				array_pop($result['parts']);
				$result['parts'][] = array(
					'type' => 'pageNum',
					'value' => $segment,
					'language' => (is_string($languageName) ? $languageName : 'default'),
				);
			}
			break;
		}

		$result['urlSegments'] = $urlSegments;
	}

	/**
	 * Finish result/return value
	 *
	 * @param string|bool $path Path string or boolean false when 404
	 * @return array
	 *
	 */
	protected function finishResult($path) {

		$result = &$this->result;
		$types = $this->pages->request()->getResponseCodeNames();

		if($path !== false) $path = $this->applyResultLanguage($path);
		if($path !== false) $path = $this->applyResultTemplate($path);
		if($path === false) $result['response'] = 404;

		$response = &$result['response'];
		$language = &$result['language'];
		$errors = &$result['errors'];
		
		if($response === 404) {
			// page not found
			if(empty($result['errors'])) $result['errors']['pageNotFound'] = "Page not found";

		} else if($response == 414) {
			// path too long

		} else if($result['request'] === $result['redirect'] || empty($result['redirect'])) {
			// blank redirect values mean no redirect is suggested
			$response = 200;
			$result['redirect'] = '';

		} else if($response === 301 || $response === 302) {
			// redirect identified

		} else {
			// redirect suggested
			$response = 301;
		}
		
		if($result['pageNum'] > 1 && ($response === 301 || $response === 302)) {
			// redirect where pageNum is greater than 1
			$response = $this->finishResultRedirectPageNum($response, $result);
		}

		if(empty($language['name'])) {
			// set language property (likely for non-multi-language install)
			$language['name'] = 'default';
			$language['status'] = 1;
			
		} else if($language['name'] != 'default' && !$language['status'] && $result['page']['id']) {
			// page found but not published in language (needs later decision)
			$response = 300; // 300 Multiple Choice
			$errors['languageOFF'] = "Page not active in request language ($language[name])";
			$result['redirect'] = $this->pages->getPath($result['page']['id']); 
			if($result['pathAdd']) $result['redirect'] = rtrim($result['redirect'], '/') . $result['pathAdd'];
		}
		
		if(empty($result['type']) && isset($types[$response])) {
			if($result['response'] === 404 && !empty($result['redirect'])) {
				// when page found but path not use the 400 response type name w/404
				$result['type'] = $types[400];
			} else {
				$result['type'] = $types[$response];
			}
		}

		$result['methods'] = $this->methods;

		if(!$this->options['verbose']) unset($result['parts'], $result['methods']);

		if(empty($errors)) {
			// force errors placeholder to end if there aren’t any
			unset($result['errors']);
			$result['errors'] = array();
		}
		
		if($this->options['test']) {
			// add 'test' to the result that should match regardless of options (except useLanguages option)
			$redirect = ($result['response'] >= 300 && $result['response'] < 400 ? $result['redirect'] : '');
			$pageNumStr = ($result['pageNum'] > 1 ? "/$result[pageNumPrefix]$result[pageNum]" : '');
			$result['test'] = array(
				'response=' . $result['response'],
				'page=' . $result['page']['id'], 
				'redirect=' . $redirect,
				'language=' . $result['language']['name'] . '[' . $result['language']['status'] . ']',
				'uss=' . $result['urlSegmentStr'] . $pageNumStr,
			);
			$result['test'] = implode(', ', $result['test']); 
		}
		
		return $result;
	}

	/*** SHORTCUTS ******************************************************************************/
	
	/**
	 * Attempt to match path to page using shortcuts and return true if shortcut found
	 *
	 * @param string $path
	 * @return bool Return true if shortcut found and result ready, false if not
	 *
	 */
	protected function getShortcut($path) {

		$found = false;
		// $slash = substr($path, -1) === '/' ? '/' : '';
		$path = trim($path, '/'); 
		
		// check for pagination segment, which we don’t want in our path here
		list($pageNum, $pageNumPrefix) = $this->getShortcutPageNum($path);
		
		if(strpos($path, '/') === false) {
			// single directory off root
			$found = $this->getShortcutRoot($path);
		}
	
		if(!$found) {
			if($this->getShortcutExcludeRoot($path)) {
				$found = true;
			} else if($this->getShortcutPagePaths($path)) {
				$found = true;
			}
		}

		if(!$found) return false;

		$this->result['pageNum'] = $pageNum;
		$this->result['pageNumPrefix'] = $pageNumPrefix;
	
		$this->result = $this->finishResult($path);

		return true;
	}

	/**
	 * @param string $path
	 * @return bool
	 * 
	 */
	protected function getShortcutRoot($path) {
		
		if($path === '') {
			$this->setResultLanguage('default');
			$this->setResultLanguageStatus(1);
			$this->applyResultHome();
			return true;
		}
		
		if(count($this->useLanguages)) {
			$languageId = $this->isLanguageSegment($path);
			if($languageId !== false) {
				$langName = $this->languageName($languageId);
				$langStatus = $langName === 'default' ? 1 : $this->getHomepage()->get("status$languageId");
				$this->setResultLanguage($langName, $path); 
				$this->setResultLanguageStatus($langStatus);
				$this->applyResultHome();
				return true;
			}
		}
			
		if($this->getShortcutGlobalUnique($path)) {
			return true;
		}
		
		return false;
	}

	/**
	 * Find out if we can early exit 404 based on the root segment
	 * 
	 * Unlike other shortcuts, this one is an exclusion shortcut: 
	 * Returns false if the root segment matched and further analysis should take place.
	 * Returns true if root segment is not in this site and 404 should be the result. 
	 *
	 * @param string $path
	 * @return bool 
	 *
	 */
	protected function getShortcutExcludeRoot($path) {
		
		if(!$this->options['useExcludeRoot']) return false;
		if(!$this->options['usePagePaths']) return false;
		
		$module = $this->pagePathsModule();
		if(!$module) return false;
		
		$homepage = $this->pages->get((int) $this->wire()->config->rootPageID);
		
		// if root/home template allows URL segments then potentially anything
		// can match the root segment, so this shortcut is not worthwhile
		if($homepage->template->urlSegments) return false;
	
		$config = $this->wire()->config;
		$path = trim($path, '/');
		
		if(strpos($path, '/')) {
			list($segment,) = explode('/', $path, 2);
		} else {
			$segment = $path;
		}
		
		if(strlen($segment) <= $config->maxUrlSegmentLength) {

			if($module->isRootSegment($segment)) {
				$this->addMethod('excludeRoot.paths', true);
				return false; // root segment found
			} else {
				$this->addMethod('excludeRoot.paths', false);
			}

			if($this->options['useHistory']) {
				$module = $this->pagePathHistoryModule();
				if($module && $this->options['useHistory']) {
					if($module->isRootSegment($segment)) {
						$this->addMethod('excludeRoot.history', true);
						return false; // root segment found
					}
					$this->addMethod('excludeRoot.history', false);
				}
			}
			$response = 404;
		} else {
			$response = 414;
		}
		
		// at this point we know given path does not have a valid root segment
		// and cannot possibly match any page so we can safely stop further 
		// processing and return a 404 not found result
		
		$this->result['response'] = $response; 
		$this->addMethod('excludeRoot', $response, 'Early exit for root segment 404');
		
		return true;
	}
		

	/**
	 * Find a shortcut using the PagePaths module
	 *
	 * @param string $path
	 * @return bool Returns true if found, false if not installed or not found
	 *
	 */
	protected function getShortcutPagePaths(&$path) {

		if(!$this->options['usePagePaths']) return false;
		$module = $this->pagePathsModule();
		if(!$module) return false;

		$result = &$this->result;
		$info = $module->getPageInfo($path);
		
		if(!$info) {
			$this->addMethod('pagePaths', false);
			return false;
		}

		$language = $this->language((int) $info['language_id']);
		
		$path = "/$info[path]";

		unset($info['language_id'], $info['path']);

		$result['page']['id'] = $info['id'];
		$result['page']['status'] = $info['status'];
		$result['page']['templates_id'] = $info['templates_id'];
		$result['page']['parent_id'] = $info['parent_id'];

		$result['response'] = 200;

		if($language && $language->id) {
			$status = $language->isDefault() ? $info['status'] : $info["status$language->id"];
			$result['language'] = array_merge($result['language'], array(
				'name' => $language->name,
				'status' => ($language->status < Page::statusUnpublished ? $status : 0),
				'segment' => $this->languageSegment($language)
			));
		}
		
		$this->addMethod('pagePaths', true);

		return true;
	}

	/**
	 * Attempt to match a page with status 'unique' or having parent_id=1
	 * 
	 * This method only proceeds if the path contains no slashes, meaning it is 1-level from root.
	 *
	 * @param string $path
	 * @return bool
	 *
	 */
	protected function getShortcutGlobalUnique(&$path) {
		
		if(!$this->options['useGlobalUnique']) return false;

		$database = $this->wire()->database;
		$unique = Page::statusUnique;

		if(strpos($path, '/') !== false) return false;

		$name = $this->pageNameToAscii($path);
		$columns = array('id', 'parent_id', 'templates_id', 'status');
		$cols = implode(',', $columns);

		$sql = "SELECT $cols FROM pages WHERE name=:name AND (parent_id=1 OR (status & $unique))";
		$query = $database->prepare($sql);
		$query->bindValue(':name', $name);
		$query->execute();

		$row = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();

		if(!$row) {
			$this->addMethod('globalUnique', false);
			return false;
		}
		
		foreach($row as $k => $v) $row[$k] = (int) $v;

		$result = &$this->result;
		$result['page'] = array_merge($result['page'], $row);
		$result['response'] = 200;
		$result['language']['name'] = 'default';
		
		if($row['parent_id'] === 1) {
			$template = $this->wire()->templates->get($row['templates_id']); 
			$slashUrls = $template ? (int) $template->slashUrls : 0;
			$slash = ($slashUrls ? $slashUrls > 0 : substr($path, -1) === '/');
			$path = "/$path" . ($slash ? '/' : '');
		} else {
			// global unique, must redirect to its actual path
			$page = $this->pages->getOneById((int) $row['id'], array(
				'template' => (int) $row['templates_id'],
				'autojoin' => false,
			));
			if(!$page->id) return false; // not likely
			$path = $page->path();
		}

		$result['redirect'] = $path;
		$this->addMethod('globalUnique', true);

		return true;
	}

	/**
	 * Identify shortcut pagination info
	 *
	 * Returns found pagination number, or 1 if first pagination.
	 * Extracts the pagination segment from the path.
	 *
	 * @param string $path
	 * @return array of [ pageNum, pageNumPrefix ]
	 *
	 */
	protected function getShortcutPageNum(&$path) {

		if(!ctype_digit(substr($path, -1))) return array(1, '');

		$pos = strrpos($path, '/');
		$lastPart = $pos ? substr($path, $pos+1) : $path;
		$pageNumPrefix = '';
		$pageNum = 1;

		foreach($this->pageNumUrlPrefixes() as $prefix) {
			if(strpos($lastPart, $prefix) !== 0) continue;
			if(!preg_match('/^' . $prefix . '(\d+)$/', $lastPart, $matches)) continue;
			$pageNumPrefix = $prefix;
			$pageNum = (int) $matches[1];
			break;
		}

		if($pageNumPrefix) {
			$path = $pos ? substr($path, 0, $pos) : '';
			if($pageNum < 2) $pageNum = 1;
		}

		return array($pageNum, $pageNumPrefix);
	}

	/**
	 * Attempt to match page path from PagePathHistory module
	 *
	 * @param string $path
	 * @return bool
	 *
	 */
	protected function getPathHistory($path) {

		if(!$this->options['useHistory']) return false;
		
		$module = $this->pagePathHistoryModule();
		if(!$module) return false;

		$result = &$this->result;
		$info = $module->getPathInfo($path, array('allowUrlSegments' => true));

		// if no history found return false
		if(!$info['id']) {
			$this->addMethod('pathHistory', false);
			return false;
		}

		// get page found in history
		$page = $this->pages->getOneById((int) $info['id'], array(
			'template' => (int) $info['templates_id'],
			'parent_id' => $info['parent_id'],
			'autojoin' => false,
		));

		if(!$page->id) {
			$this->addMethod('pathHistory', false, 'Found row but could not match to page');
			return false;
		}

		$path = $page->path;
		$languageName = $this->languageName($info['language_id']);
		$keys = array('id', 'language_id', 'templates_id', 'parent_id', 'status', 'name');

		foreach($keys as $key) {
			$result['page'][$key] = $info[$key];
		}

		$result['language']['name'] = $languageName;
		$result['response'] = 301;
		$result['redirect'] = $path;

		$urlSegmentStr = $info['urlSegmentStr'];

		if(strlen($urlSegmentStr)) {
			$result['urlSegments'] = explode('/', $info['urlSegmentStr']);
			$this->applyResultPageNum($result['parts']);
		}

		$result = $this->finishResult($path);
		$this->addMethod('pathHistory', true);

		return true;
	}


	/*** UTILITIES ******************************************************************************/
	
	/**
	 * @var string
	 *
	 */
	protected $pageNameCharset = '';

	/**
	 * Get page number segment with given pageNum and and in given language name
	 *
	 * @param int $pageNum
	 * @param string $langName
	 * @return string
	 *
	 */
	protected function pageNumUrlSegment($pageNum, $langName = 'default') {
		$pageNum = (int) $pageNum;
		if($pageNum < 2) return '';
		$a = $this->pageNumUrlPrefixes();
		$prefix = isset($a[$langName]) ? $a[$langName] : $a['default'];
		return $prefix . $pageNum;
	}

	/**
	 * Get pageNum URL prefixes indexed by language name
	 *
	 * @return array
	 *
	 */
	protected function pageNumUrlPrefixes() {
		$config = $this->wire()->config;
		$a = $config->pageNumUrlPrefixes;
		if(!is_array($a)) $a = array();
		$default = $config->pageNumUrlPrefix;
		if(empty($default)) $default = 'page';
		if(empty($a['default'])) $a['default'] = $default;
		return $a;
	}

	/**
	 * Does given path refer to homepage?
	 * 
	 * @param string $path
	 * @return bool
	 * 
	 */
	protected function isHomePath($path) {
		$path = trim($path, '/');
		if($path === '') return true;
		$isHomePath = false;
		foreach($this->languageSegments() as $segment) {
			if($path !== $segment) continue;
			$isHomePath = true;
			break;
		}
		return $isHomePath;	
	}

	/**
	 * Convert ascii page name to UTF-8
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	protected function pageNameToUTF8($name) {
		$name = (string) $name;
		if($this->pageNameCharset !== 'UTF8') return $name;
		if(strpos($name, 'xn-') !== 0) return $name;
		return $this->wire()->sanitizer->pageName($name, Sanitizer::toUTF8);
	}

	/**
	 * Convert UTF-8 page name to ascii
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	protected function pageNameToAscii($name) {
		if($this->pageNameCharset !== 'UTF8') return $name;
		return $this->wire()->sanitizer->pageName($name, Sanitizer::toAscii);
	}

	/**
	 * Get template used by page found in result or null if not yet known 
	 * 
	 * @return null|Template
	 * 
	 */
	protected function getResultTemplate() {
		if(!$this->template && !empty($this->result['page']['templates_id'])) {
			$this->template = $this->wire()->templates->get($this->result['page']['templates_id']); 
		}
		return $this->template;
	}

	/**
	 * Is matched result in admin?
	 * 
	 * @return bool
	 * 
	 */
	protected function isResultInAdmin() {
		if($this->admin !== null) return $this->admin;
		$config = $this->wire()->config;
		if($this->result['page']['templates_id'] === 2) {
			$this->admin = true;
		} else if($this->result['page']['id'] === $config->adminRootPageID) {
			$this->admin = true;
		} else {
			$template = $this->getResultTemplate();
			if(!$template) return false; // may need to detect later
			if(in_array($template->name, $config->adminTemplates, true)) {
				$this->admin = true;
			} else if(in_array($template->name, array('user', 'role', 'permission', 'language'))) {
				$this->admin = true;
			} else {
				$this->admin = false;
			}
		}
		return $this->admin;
	}

	/**
	 * Get string length, using mb_strlen() if available, strlen() if not
	 *
	 * @param string $str
	 * @return int
	 *
	 */
	protected function strlen($str) {
		return function_exists('mb_strlen') ? mb_strlen($str) : strlen($str);
	}

	/**
	 * Add method debug info (verbose mode)
	 * 
	 * @param string $name
	 * @param int|bool $code
	 * @param string $note
	 * 
	 */
	protected function addMethod($name, $code, $note = '') {
		if(!$this->verbose) return;
		if($code === true) $code = 200;
		if($code === false) $code = 404;
		if(empty($note)) {
			if($code === 200) $note = 'OK';
			if($code === 404) $note = 'Not found';
		}
		if($note) $code = "$code $note";
		$this->methods[] = "$name: $code";
	}

	/**
	 * Get homepage
	 * 
	 * @return Page
	 * 
	 */
	protected function getHomepage() {
		return $this->pages->get((int) $this->wire()->config->rootPageID);
	}

	/**
	 * Add named error message to result
	 * 
	 * @param string $name
	 * @param string $message
	 * @param bool $force Force add even if not in verbose mode? (default=false)
	 * 
	 */
	protected function addResultError($name, $message, $force = false) {
		//if(!$this->verbose && !$force) return;
		$this->result['errors'][$name] = $message;
	}

	/**
	 * Add note to result
	 * 
	 * @param string $message
	 * 
	 */
	protected function addResultNote($message) {
		if(!$this->verbose) return;
		$this->result['notes'][] = $message;
	}

	/**
	 * Get default options
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function getDefaultOptions() {
		return $this->defaults;
	}
	
	/*** MODULES **********************************************************************************/
	
	/**
	 * @var PagePaths|null
	 *
	 */
	protected $pagePathsModule = null;

	/**
	 * @var PagePathHistory|null
	 *
	 */
	protected $pagePathHistoryModule = null;

	/**
	 * @var null|PagesPathFinderTests 
	 * 
	 */
	protected $tester = null;

	/**
	 * Get optional PathPaths module instance if it is installed, false if not
	 *
	 * @return bool|PagePaths
	 *
	 */
	protected function pagePathsModule() {
		if($this->pagePathsModule !== null) return $this->pagePathsModule;
		$modules = $this->wire()->modules;
		if($modules->isInstalled('PagePaths')) {
			$this->pagePathsModule = $modules->getModule('PagePaths');
		} else {
			$this->pagePathsModule = false;
		}
		return $this->pagePathsModule;
	}

	/**
	 * Get optional PagePathHistory module instance if it is installed, false if not
	 * 
	 * @return PagePathHistory|bool
	 *
	 */
	protected function pagePathHistoryModule() {
		if($this->pagePathHistoryModule !== null) return $this->pagePathHistoryModule;
		$modules = $this->wire()->modules;
		if($modules->isInstalled('PagePathHistory')) {
			$this->pagePathHistoryModule = $modules->getModule('PagePathHistory');
		} else {
			$this->pagePathHistoryModule = false;
		}
		return $this->pagePathHistoryModule;
	}

	/**
	 * Get PagesPathFinderTests instance
	 * 
	 * #pw-internal
	 * 
	 * @return PagesPathFinderTests
	 * 
	 */
	public function tester() {
		if($this->tester) return $this->tester;
		$this->tester = new PagesPathFinderTests();
		$this->wire($this->tester);
		return $this->tester;
	}
	
	/*** LANGUAGES ******************************************************************************/
	
	/**
	 * Cache for languageSegments() method
	 *
	 * @var array
	 *
	 */
	protected $languageSegments = array();

	/**
	 * Language names indexed by id
	 *
	 * @var array
	 *
	 */
	protected $languageNames = array();

	/**
	 * Set result language by name or ID
	 * 
	 * @param int|string|Language $language
	 * @param string $segment
	 * 
	 */
	protected function setResultLanguage($language, $segment = '') {
		if(is_object($language)) {
			$name = $language->name;
		} else if(ctype_digit("$language")) {
			$id = (int) $language; 
			$name = $this->languageName($id); 
		} else {
			$name = $language; 
		}
		$this->result['language']['name'] = $name;
		if($segment !== '') $this->setResultLanguageSegment($segment);
	}

	/**
	 * Set result language segment
	 *
	 * @param string $segment
	 *
	 */
	protected function setResultLanguageSegment($segment) {
		$this->result['language']['segment'] = $segment;
	}

	/**
	 * Set result language status
	 * 
	 * @param int|bool $status
	 * 
	 */
	protected function setResultLanguageStatus($status) {
		$this->result['language']['status'] = (int) $status;
	}
	
	/**
	 * Get value from page status column
	 *
	 * @param int $pageId
	 * @param int $languageId
	 * @return int
	 *
	 */
	protected function getPageLanguageStatus($pageId, $languageId) {
		$pageId = (int) $pageId;
		$languageId = (int) $languageId;
		$langName = $languageId ? $this->languageName($languageId) : 'default';
		$col = $langName === 'default' ? 'status' : "status$languageId";
		$page = $this->pages->cacher()->getCache((int) $pageId);
		if($page) return $page->get($col);
		$query = $this->wire()->database->prepare("SELECT `$col` FROM pages WHERE id=:id");
		$query->bindValue(':id', $pageId, \PDO::PARAM_INT);
		try {
			$query->execute();
			$status = (int) $query->fetchColumn();
			$query->closeCursor();
		} catch(\Exception $e) {
			$status = -1;
		}
		return $status;
	}


	/**
	 * Return Languages if installed w/languageSupportPageNames module or blank array if not
	 * 
	 * @param bool $getArray Force return value as an array indexed by language name
	 * @return Languages|Language[]
	 *
	 */
	protected function languages($getArray = false) {
		if(is_array($this->useLanguages) && !count($this->useLanguages)) {
			return array();
		}
		$languages = $this->wire()->languages;
		if(!$languages) {
			$this->useLanguages = array();
			return array();
		}
		if(!$languages->hasPageNames()) {
			$this->useLanguages = array();
			return array();
		}
		if($getArray) {
			$a = array();
			foreach($languages as $language) {
				$a[$language->name] = $language;
			}
			$languages = $a;
		}
		return $languages;
	}

	/**
	 * Given a value return corresponding language
	 * 
	 * @param string|int|Language $value
	 * @return Language|null
	 * 
	 */
	protected function language($value) {
	
		$language = null;
		
		if($value instanceof Page) {
			if($value->className() === 'Language' || wireInstanceOf($value, 'Language')) {
				$language = $value;
			}
		} else {
			/** @var Languages|array $languages */
			$languages = $this->languages();
			if(!count($languages)) return null;

			$id = $this->languageId($value);
			$language = $id ? $languages->get($id) : null;
		}
		
		if(!$language || !$language->id) return null;
		
		return $language;
	}

	/**
	 * Get homepage name segments used for each language, indexed by language id
	 * 
	 * #pw-internal
	 *
	 * @return array
	 *
	 */
	public function languageSegments() {

		// use cached value when available
		if(count($this->languageSegments)) return $this->languageSegments;

		$columns = array();
		$languages = $this->languages();
		
		if(!count($languages)) return array();

		foreach($languages as $language) {
			$name = $language->isDefault() ? "name" : "name$language->id";
			$columns[$name] = $language->id;
		}

		// see if homepage already loaded in cache
		$homepage = $this->pages->cacher()->getCache(1);

		// if homepage available, get segments from it
		if($homepage) {
			foreach($columns as $name => $languageId) {
				$value = $homepage->get($name);
				if($name === 'name' && $value === Pages::defaultRootName) $value = '';
				$this->languageSegments[$languageId] = $value;
			}
			
		} else {
			// if homepage not already loaded, pull segments directly from pages table
			$config = $this->wire()->config;
			$database = $this->wire()->database;

			$cols = implode(', ', array_keys($columns));
			$sql = "SELECT $cols FROM pages WHERE id=:id";

			$query = $database->prepare($sql);
			$query->bindValue(':id', (int) $config->rootPageID, \PDO::PARAM_INT);
			$query->execute();

			$row = $query->fetch(\PDO::FETCH_ASSOC);
			$query->closeCursor();

			foreach($row as $name => $value) {
				$languageId = $columns[$name];
				$value = $this->pageNameToUTF8($value);
				if($name === 'name' && $value === Pages::defaultRootName) $value = '';
				$this->languageSegments[$languageId] = $value;
			}
		}

		return $this->languageSegments;
	}

	/**
	 * Is given segment a language segment? Returns language ID if yes, false if no
	 * 
	 * #pw-internal
	 * 
	 * @param string $segment
	 * @return false|int
	 * 
	 */
	public function isLanguageSegment($segment) {
		$key = array_search($segment, $this->languageSegments()); 
		return $key;
	}

	/**
	 * Return homepage name segment used by given language
	 * 
	 * @param string|int|Language $language
	 * @return string
	 * 
	 */
	protected function languageSegment($language) {
		$id = $this->languageId($language);
		$segments = $this->languageSegments();
		return isset($segments[$id]) ? $segments[$id] : '';
	}
	
	/**
	 * Return language identified by homepage name segment
	 * 
	 * @param string $segment
	 * @param bool $getLanguageId
	 * @return Language|null|int
	 * 
	 */
	protected function segmentLanguage($segment, $getLanguageId = false) {
		$segments = $this->languageSegments();
		$languageId = array_search($segment, $segments); 	
		if($getLanguageId) return $languageId ? $languageId : 0;
		return $languageId ? $this->language($languageId) : null;
	}
	
	/**
	 * Return name for given language id or object
	 * 
	 * @param int|string|Language $language
	 * @return string
	 * 
	 */
	protected function languageName($language) {
		$names = $this->languageNames();
		$languageId = $this->languageId($language);
		if(isset($names[$languageId])) return $names[$languageId];
		return 'default';
	}
	
	/**
	 * Return all language names indexed by language id
	 * 
	 * @return array
	 * 
	 */
	protected function languageNames() {
		if(empty($this->languageNames)) {
			foreach($this->languages() as $lang) {
				$this->languageNames[$lang->id] = $lang->name;
			}
		}
		return $this->languageNames;
	}

	/**
	 * Return language id for given value (language name, name column or home segment)
	 * 
	 * @param int|string|Language $language
	 * @return int
	 * 
	 */
	protected function languageId($language) {
		$language = (string) $language;
		if(ctype_digit("$language")) return (int) "$language"; // object or number string
		if($language === 'name' || $language === 'default') {
			// name property translates to default language
			$languages = $this->languages();
			return count($languages) ? $languages->getDefault()->id : 0;
		} else if(strpos($language, 'name') === 0 && ctype_digit(substr($language, 5))) {
			// i.e. name1234 where 1234 is language id
			$language = str_replace('name', '', $language);
		} else if(!ctype_digit($language)) {
			// likely a language name
			$langName = $language;
			$language = null;
			foreach($this->languages() as $lang) {
				if($lang->name === $langName) $language = $lang;
				if($language) break;
			}
			if(!$language) {
				// if not found by language name, try home language segments
				$languageId = 0;
				foreach($this->languageSegments() as $id => $segment) {
					if($segment === $langName) $languageId = (int) $id;
					if($languageId) break;
				}
				if($languageId) return $languageId;
			}
			$language = $language ? $language->id : 0;
		}
		return (int) $language;
	}

	/**
	 * Update given path for result language and return it
	 *
	 * @param string $path
	 * @param string $langName
	 * @return string
	 *
	 */
	protected function updatePathForLanguage($path, $langName = '') {
		
		$result = &$this->result;	
		$config = $this->wire()->config;
		$template = $this->getResultTemplate();
		
		if($template && in_array($template->name, $config->adminTemplates)) {
			return $this->removeLanguageSegment($path);
		}
	
		if(empty($langName)) $langName = $result['language']['name'];
		if(empty($langName)) $langName = 'default';
		
		if($langName === 'default' && ($result['page']['id'] === 1 || $path === '/')) {
			$pageNames = $this->wire()->languages->pageNames();
			if(!$pageNames) return $path; 
			$useSegment = $pageNames->useHomeSegment;
		} else {
			$useSegment = true;
		}
			
		if($useSegment) {
			$path = $this->addLanguageSegment($path, $langName);
		} else {
			$path = $this->removeLanguageSegment($path);
		}
		
		return $path;
	}

	/**
	 * Add language segment
	 * 
	 * @param string $path
	 * @param string|Language|int $language
	 * @return string
	 * 
	 */
	protected function addLanguageSegment($path, $language) {
		if(strpos($path, '/') !== 0) $path = "/$path";
		$segment = $this->languageSegment($language);
		if(!strlen($segment)) return $path;
		if($path === "/$segment" && $this->result['page']['id'] < 2) return $path;
		if(strpos($path, "/$segment/") === 0) return $path;
		return "/$segment$path";
	}

	/**
	 * Remove any language segments present on given path
	 * 
	 * @param string $path
	 * @return string
	 * 
	 */
	protected function removeLanguageSegment($path) {
		if(strpos($path, '/') !== 0) $path = "/$path";
		if($path === '/') return $path;
		$segments = $this->languageSegments();
		$segments[] = Pages::defaultRootName;
		foreach($segments as $segment) {
			if($segment === null || !strlen($segment)) continue;
			if($path !== "/$segment" && strpos($path, "/$segment/") !== 0) continue;
			list(,$path) = explode("/$segment", $path, 2); 
			if($path === '') $path = '/';
			break;
		}
		return $path;
	}
	
	
	/*** ADDITIONAL/HELPER LOGIC ****************************************************************/
	
	/**
	 * Update result for cases where a redirect was determined that involved pagination
	 *
	 * Most of the logic here allows for the special case of admin URLs, which work with either
	 * a custom pageNumUrlPrefix or the original/default one. This is a helper for the
	 * finishResult() method. 
	 *
	 * @param int $response
	 * @var array $result
	 * @return int
	 * @since 3.0.198
	 *
	 */
	protected function finishResultRedirectPageNum($response, &$result) {

		if($result['pageNum'] < 2) return $response;

		if(empty($result['page']['templates_id'])) return $response;
		if($result['page']['status'] >= Page::statusUnpublished) return $response;

		// The config[_pageNumUrlPrefix] property is set by LanguageSupportPageNames
		$pageNumUrlPrefix = $this->wire()->config->get('_pageNumUrlPrefix');
		if(empty($pageNumUrlPrefix)) $pageNumUrlPrefix = 'page';

		// if default/original pageNum prefix not in use then do nothing further
		if($result['pageNumPrefix'] !== $pageNumUrlPrefix) return $response;

		// if request is not for something in the admin then do nothing further
		$adminTemplate = $this->wire()->templates->get('admin');
		if(!$adminTemplate || $result['page']['templates_id'] != $adminTemplate->id) return $response;

		// request is for pagination within admin, where we allow either custom or original/default prefix
		$requestParts = explode('/', trim($result['request'], '/'));
		$redirectParts = explode('/', trim($result['redirect'], '/'));

		$requestPrefix = array_pop($requestParts);
		$redirectPrefix = array_pop($redirectParts);

		$requestPath = implode('/', $requestParts);
		$redirectPath = implode('/', $redirectParts);

		// if something other than pagination prefix differs then do nothing further
		if($requestPath != $redirectPath || $requestPrefix === $redirectPrefix) return $response;

		// only the pagination prefix differs, allow it when in admin
		$result['notes'][] = "Default pagination prefix '$pageNumUrlPrefix' allowed for admin template";
		$response = 200;

		return $response;
	}

}

/**
 * PagesPathFinder Tests
 * 
 * Usage:
 * ~~~~~
 * $tester = $pages->pathFinder()->tester();
 * $a = $tester->testPath('/path/to/page/'); 
 * $a = $tester->testPage(Page $page);
 * $a = $tester->testPages("has_parent!=2");
 * $a = $tester->testPages(PageArray $items); 
 * ~~~~~
 * 
 */
class PagesPathFinderTests extends Wire {

	/**
	 * @return PagesPathFinder
	 * 
	 */
	public function pathFinder() {
		return $this->wire()->pages->pathFinder();
	}
	
	/**
	 * @param string $path
	 * @param int $expectResponse
	 * @return array
	 *
	 */
	public function testPath($path, $expectResponse = 0) {
		$tests = array();
		$testResults = array();
		$results = array();
		$optionSets = array(
			'defaults' => $this->pathFinder()->getDefaultOptions(),
			'noPagePaths' => array('usePagePaths' => false),
			'noGlobalUnique' => array('useGlobalUnique' => false),
			'noHistory' => array('useHistory' => false),
			'excludeRoot' => array('useExcludeRoot' => true),
		);
		foreach($optionSets as $name => $options) {
			$options['test'] = true;
			$result = $this->pathFinder()->get($path, $options);
			$test = $result['test'];
			$results[$name] = $result;
			$tests[$name] = $test;
		}
		$defaultTest = $tests['defaults'];
		foreach(array_keys($optionSets) as $name) {
			$test = $tests[$name];
			$result = $results[$name];
			if($expectResponse && $result['response'] != $expectResponse) {
				$status = "FAIL ($result[response] != $expectResponse)";
			} else {
				$status = ($test === $defaultTest ? 'OK' : 'FAIL');
			}
			$testResults[] = array(
				'name' => $name,
				'status' => $status,
				'test' => $test
			);
		}

		return $testResults;
	}

	/**
	 * @param Page $item
	 * @return array
	 *
	 */
	public function testPage(Page $item) {
		$languages = $this->languages();
		$testResults = array();
		$defaultPath = $item->path();
		if($languages) {
			foreach($languages as $language) {
				/** @var Language $language */
				$path = $item->localPath($language);
				if($language->isDefault() || $path === $defaultPath) {
					$expect = 200;
				} else {
					$expect = $item->get("status$language") > 0 ? 200 : 300;
				}
				$testResults["$language->name:$path"] = $this->testPath($path, $expect);
			}
		} else {
			$path = $item->path();
			$testResults[$path] = $this->testPath($path, 200);
		}
		return $testResults;
	}

	/**
	 * @param string|PageArray $selector
	 * @return array
	 *
	 */
	public function testPages($selector) {
		if($selector instanceof PageArray) {
			$items = $selector;
		} else {
			$items = $this->pages->find($selector);
		}
		$testResults = array();
		foreach($items as $item) {
			$testResults = array_merge($testResults, $this->testPage($item));
		}
		return $testResults;
	}
}
