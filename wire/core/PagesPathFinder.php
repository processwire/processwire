<?php namespace ProcessWire;

/**
 * ProcessWire Pages Path Finder
 * 
 * Enables finding pages by path, optionally including URL segments, 
 * pagination/page numbers and language prefixes. Build for use by
 * the PagesRequest class and ProcessPageView module. 
 * 
 * Note that this does not perform any access control checks, so 
 * if using this class then validate access afterwards when appropriate.
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
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
	 * @var array
	 * 
	 */
	protected $defaults = array(
		'useLanguages' => true,
		'useShortcuts' => true, 
		'useHistory' => true,
		'verbose' => true,
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
	 * Response type codes to response type names
	 * 
	 * @var array
	 * 
	 */
	protected $responseTypes = array(
		200 => 'ok',
		301 => 'permRedirect',
		302 => 'tempRedirect',
		400 => 'pagePathError',
		404 => 'pageNotFound',
		414 => 'pathTooLong',
	);

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
		$this->verbose = $this->options['verbose'];
		$this->methods = array();
		$this->result = $this->getBlankResult(array('request' => $path));
		
		if(empty($this->pageNameCharset)) {
			$this->pageNameCharset = $this->wire()->config->pageNameCharset;
		}
		if(empty($this->useLanguages)) {
			$this->useLanguages = $this->options['useLanguages'] ? $this->languages(true) : array();
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
	 * If given a `$path` argument of `/en/foo/bar/page3` on a site that has default
	 * language homepage segment of `en`, a page living at `/foo/` that accepts
	 * URL segment `bar` and has pagination enabled, it will return the following:
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
	 *  - `useShortcuts` (bool): Allow use of shortcut methods for optimization? (default=true)
	 *     Recommend PagePaths module installed.
	 *  - `useHistory` (bool): Allow use historical path names? (default=true)
	 *     Requires PagePathHistory module installed.
	 *  - `verbose` (bool): Return verbose array of information? (default=true)
	 *     If false, some optional information will be omitted in return value.
	 * @return array
	 *
	 */
	public function get($path, array $options = array()) {
		
		$this->init($path, $options);
		
		// see if we can take a shortcut
		if($this->options['useShortcuts'] && $this->getShortcut($path)) return $this->result;
		
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
	 * Sets a `_pagePathFinder` property to the returned Page, which is an associative 
	 * array containing the same result array returned by the get() method.
	 * 
	 * @param string $path
	 * @param array $options
	 * @return NullPage|Page
	 * 
	 */
	public function getPage($path, array $options = array()) {
		if(!isset($options['verbose'])) $options['verbose'] = false;
		$result = $this->get($path, $options);
		if($result['response'] >= 400) {
			$page = $this->pages->newNullPage();
		} else {
			$template = $this->wire()->templates->get($result['page']['templates_id']);
			$page = $this->pages->getOneById($result['page']['id'], array(
				'template' => $template,
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

		$this->methods[] = 'pagesRow';
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

		if(count($selects)) {
			$selects = implode(', ', $selects);
			$joins = implode(" \n", $joins);
			$wheres = implode(" AND ", $wheres);
			$sql = "SELECT $selects \nFROM pages \n$joins \nWHERE $wheres";
			$query = $database->prepare($sql);

			foreach($binds as $bindKey => $bindValue) {
				$query->bindValue(":$bindKey", $bindValue);
			}

			$query->execute();
			$rowCount = $query->rowCount();
			$row = $query->fetch(\PDO::FETCH_ASSOC);
			$query->closeCursor();

			// multiple matches error (not likely)
			if($rowCount > 1) $row = null;

		} else {
			$row = null;
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
		
		$maxUrlSegmentLength = $this->wire()->config->maxUrlSegmentLength;
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
				if(strlen($name) > $maxUrlSegmentLength) $name = substr($name, 0, $maxUrlSegmentLength);
				$result['urlSegments'][] = $name;
				if($this->verbose) {
					$result['parts'][] = array(
						'type' => 'urlSegment',
						'value' => $name,
						'language' => ''
					);
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
				$statusByLanguage[$language->name] = (int) $row["{$key}_status$language->id"];
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
					if(empty($result['language']['name'])) {
						$result['language']['name'] = $language->name;
					}
				}
				if(!isset($namesByLanguage[$language->name])) $namesByLanguage[$language->name] = array();
				$namesByLanguage[$language->name][] = strlen($nameLanguage) ? $nameLanguage : $nameDefault;
			}
		}
		
		// identify if a pageNum is present, must be applied before creation of urlSegmentStr and path
		$this->applyResultPageNum($parts);

		$langName = empty($result['language']['name']) ? 'default' : $result['language']['name'];

		if(!isset($namesByLanguage[$langName])) $langName = 'default';
		
		$path = '/' . implode('/', $namesByLanguage[$langName]);

		if(count($this->useLanguages)) {
			if($langName === 'default') {
				$result['language']['status'] = $result['page']['status']; 
			} else if(isset($statusByLanguage[$langName])) {
				$result['language']['status'] = $statusByLanguage[$langName];
			}
		}
		
		return $path;
	}

	/**
	 * Prepare $path and convert to array of $parts
	 * 
	 * If language segment detected then remove it and populate language to result
	 * 
	 * @param string $path
	 * @return array|bool
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
			$result['response'] = 414; // 414=URI too long
			$result['errors']['pathLengthMAX'] = "Path length exceeds max allowed $maxPathLength";
			$path = substr($path, 0, $maxPathLength);
		}
		
		$parts = explode('/', trim($path, '/'));

		if(count($parts) > $maxDepth) {
			$parts = array_slice($parts, 0, $maxDepth);
			$result['response'] = 414;
			$result['errors']['pathDepthMAX'] = 'Path depth exceeds config.maxUrlDepth';
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
			array_pop($parts); // removing last part will force a 301
			$result['errors']['indexFile'] = 'Path had index file';
		}
	
		if($result['response'] < 400 && count($badNames)) {
			$result['response'] = 400; // 400=Bad request
			$result['errors']['pathBAD'] = 'Path contains invalid character(s)';
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
		$key = array_search($firstPart, $this->languageSegments());
		if($key === false) return $languages->getDefault();
		
		$segment = array_shift($parts);
		$language = $languages->get($key);
		
		if(!$language || !$language->id) return null;

		$result = &$this->result;
		$result['language']['segment'] = $segment;
		$result['language']['name'] = $language->name;
		
		if($this->verbose) {
			$result['parts'][] = array(
				'type' => 'language',
				'value' => $segment,
				'language' => $language->name
			);
		}

		// reduce to just applicable language
		if($language) $this->useLanguages = array($language);
		
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
			),
			'language' => array(
				'name' => '', // intentionally blank
				'segment' => '',
				'status' => 0,
			),
			'parts' => array(),
			'urlSegments' => array(),
			'urlSegmentStr' => '',
			'pageNum' => 1,
			'pageNumPrefix' => '',
			'scheme' => '',
			'method' => '',
		);

		if(empty($result)) return $blankResult;

		$result = array_merge($blankResult, $result);

		return $result;
	}

	/**
	 * Update paths for template info like urlSegments and pageNum and populate urls property
	 *
	 * @param string $path
	 * @return bool|string
	 *
	 */
	protected function applyResultTemplate($path) {

		$templates = $this->wire()->templates;
		$config = $this->wire()->config;
		$fail = false;
		$result = &$this->result;

		if(empty($result['page']['templates_id']) && $this->isHomePath($path)) {
			$this->applyResultHome();
		}

		$template = $result['page']['templates_id'] ? $templates->get($result['page']['templates_id']) : null;
		$slashUrls = $template ? (int) $template->slashUrls : 0;
		$useTrailingSlash = $slashUrls ? 1 : -1; // 1=yes, 0=either, -1=no
		$https = $template ? (int) $template->https : 0;

		// populate urlSegmentStr property if applicable
		if(empty($result['urlSegmentStr']) && !empty($result['urlSegments'])) {
			$urlSegments = $result['urlSegments'];
			$result['urlSegmentStr'] = count($urlSegments) ? implode('/', $urlSegments) : '';
		}

		// if URL segments are present validate them
		if(strlen($result['urlSegmentStr'])) {
			if($template && ($template->urlSegments || $template->name === 'admin')) {
				if($template->isValidUrlSegmentStr($result['urlSegmentStr'])) {
					$path = rtrim($path, '/') . "/$result[urlSegmentStr]";
					if($result['pageNum'] < 2) $useTrailingSlash = (int) $template->slashUrlSegments;
				} else {
					// ERROR: URL segments did not validate
					$result['errors']['urlSegmentsBAD'] = "Invalid urlSegments for template $template";
					$fail = true;
				}
			} else {
				// template does not allow URL segments
				if($template) $result['errors']['urlSegmentsOFF'] = "urlSegments disabled for template $template";
				$fail = true;
			}
		}

		// if a pageNum is present validate it
		if($result['pageNum'] > 1) {
			if($template && $template->allowPageNum) {
				$maxPageNum = $this->wire()->config->maxPageNum;
				if($maxPageNum && $result['pageNum'] > $maxPageNum && $template->name != 'admin') {
					$result['errors']['pageNumBAD'] = "pageNum exceeds config.maxPageNum $maxPageNum";
					$fail = true;
				}
				$segment = $this->pageNumUrlSegment($result['pageNum'], $result['language']['name']);
				if(strlen($segment)) $path = rtrim($path, '/') . "/$segment";
				$useTrailingSlash = (int) $template->slashPageNum;
			} else {
				// template does not allow page numbers
				$result['errors']['pageNumOFF'] = "pageNum disabled for template $template";
				$fail = true;
			}
		}

		// determine whether path should end with a trailing slash or not
		$path = rtrim($path, '/');
		if($useTrailingSlash > 0) {
			// trailing slash required
			$path .= '/';
		} else if($useTrailingSlash < 0) {
			// trailing slash disallowed
		} else if(substr($result['request'], -1) === '/') {
			// either acceptable, add slash if request had it
			$path .= '/';
		}

		$result['redirect'] = $path;

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
		$home = $this->pages->get($config->rootPageID);
		$template = $home->template;
		$this->result['page'] = array_merge($this->result['page'], array(
			'id' => $config->rootPageID,
			'templates_id' => $template->id,
			'parent_id' => 0,
			'status' => $home->status
		));
		$this->methods[] = 'resultHome';
	}

	/**
	 * Identify and populate language information in result
	 *
	 * @param string $path
	 * @return string $path
	 *
	 */
	protected function applyResultLanguage($path) {

		$result = &$this->result;

		if(!count($this->useLanguages)) return $path;
		if(empty($result['language']['name'])) return $path;

		// if there were any non-default language segments, let that dictate the language
		if(empty($result['language']['segment'])) {
			$useLangName = '';
			foreach($result['parts'] as $key => $part) {
				$langName = $part['language'];
				if(empty($langName) || $langName === 'default') continue;
				$useLangName = $langName;
				break;
			}
			if($useLangName) {
				$segment = $this->languageSegment($useLangName);
				if($segment) $result['language']['segment'] = $segment;
				$result['language']['name'] = $useLangName;
			}
		}

		// prepend the path with the language segment
		if(!empty($result['language']['segment'])) {
			$segment = $result['language']['segment'];
			if($path != "/$segment" && strpos($path, "/$segment/") !== 0) {
				$path = "/$segment$path";
			}
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
		$types = &$this->responseTypes;

		if($path !== false) $path = $this->applyResultLanguage($path);
		if($path !== false) $path = $this->applyResultTemplate($path);
		if($path === false) $result['response'] = 404;

		$response = &$result['response'];
		$language = &$result['language'];

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

		if(empty($result['type']) && isset($types[$response])) {
			if($result['response'] === 404 && !empty($result['redirect'])) {
				// when page found but path not use the 400 response type name w/404
				$result['type'] = 'pagePathError'; 
			} else {
				$result['type'] = $types[$response];
			}
		}

		if(empty($language['name'])) {
			$language['name'] = 'default';
			$language['status'] = 1;
		}

		$result['method'] = implode(',', $this->methods);

		if(!$this->verbose) unset($result['parts']);

		if(empty($result['errors'])) {
			// force errors placeholder to end if there aren’t any
			unset($result['errors']);
			$result['errors'] = array();
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
		$path = trim($path, '/');

		// check for pagination segment, which we don’t want in our path here
		list($pageNum, $pageNumPrefix) = $this->getShortcutPageNum($path);

		if($this->getShortcutPagePaths($path)) {
			$found = true;
		} else if($this->getShortcutGlobalUnique($path)) {
			$found = true;
		}

		if(!$found) return false;

		$this->result['pageNum'] = $pageNum;
		$this->result['pageNumPrefix'] = $pageNumPrefix;
		$this->result = $this->finishResult($path);

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

		$module = $this->pagePathsModule();
		if(!$module) return false;

		$result = &$this->result;
		$info = $module->getPageInfo($path);
		$this->methods[] = 'pagePaths';
		if(!$info) return false;

		$language = $this->language((int) $info['language_id']);
		$path = "/$info[path]";

		unset($info['language_id'], $info['path']);

		$result['page'] = array_merge($result['page'], $info);
		$result['response'] = 200;

		if($language && $language->id) {
			$result['language'] = array_merge($result['language'], array(
				'name' => $language->name,
				'status' => $language->status,
				'segment' => $this->languageSegment($language)
			));
		}

		return true;
	}

	/**
	 * Attempt to match a page with status 'unique' or having parent_id=1
	 *
	 * @param string $path
	 * @return bool
	 *
	 */
	protected function getShortcutGlobalUnique(&$path) {

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

		if(!$row) return false;

		foreach($row as $k => $v) $row[$k] = (int) $v;

		$result = &$this->result;
		$result['page'] = array_merge($result['page'], $row);
		$result['response'] = 200;
		$result['language']['name'] = 'default';
		$this->methods[] = 'globalUnique';

		if($row['parent_id'] === 1) {
			$path = "/$path/";
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
		$this->methods[] = 'pathHistory';

		// if no history found return false
		if(!$info['id']) return false;

		// get page found in history
		$page = $this->pages->getOneById((int) $info['id'], array(
			'template' => (int) $info['templates_id'],
			'parent_id' => $info['parent_id'],
			'autojoin' => false,
		));

		if(!$page->id) return false;

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
	 * Get array of all possible response types indexed by http response code
	 * 
	 * @return array
	 * 
	 */
	public function getResponseTypes() {
		return $this->responseTypes;
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
	 * @var null|array Becomes array once initialized
	 *
	 */
	protected $useLanguages = null;

	/**
	 * Return Languages if installed w/languageSupportPageNames module or blank array if not
	 * 
	 * @param bool $getArray Force return value as an array indexed by language name
	 * @return Languages|array
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
		
		if($value instanceof Page) {
			if($value->className() === 'Language' || wireInstanceOf($value, 'Language')) {
				return $value;
			}
		}
		
		/** @var Languages|array $languages */
		$languages = $this->languages();
		if(!count($languages)) return null;
		
		$id = $this->languageId($value);
		
		return $languages->get($id);
	}

	/**
	 * Get homepage name segments used for each language, indexed by language id
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
				if($name === 'name' && $value === 'home') $value = '';
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
				if($name === 'name' && $value === 'home') $value = '';
				$this->languageSegments[$languageId] = $value;
			}
		}

		return $this->languageSegments;
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
	 * @return Language|null
	 * 
	 */
	protected function segmentLanguage($segment) {
		$segments = $this->languageSegments();
		$languageId = array_search($segment, $segments); 	
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


}