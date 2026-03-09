<?php namespace ProcessWire;

/**
 * HTML Markup Quality Assurance
 * 
 * Provides runtime quality assurance for markup stored in [textarea] field values. 
 * 
 * 1. Ensures URLs referenced in <a> and <img> tags are relative to actual site root.
 * 2. Ensures local page URLs referenced in <a> tags up-to-date with current $page URL.
 * 3. Identifies and logs <img> tags that point to non-existing files in PW's file system.
 * 4. Re-creates image variations that don't exist, when the original still exists. 
 * 5. Populates blank 'alt' attributes with actual file description. 
 * 
 * - For #1 use the wakeupUrls($value) and sleepUrls($value) methods. 
 * - For #2 use the wakeupHrefs($value) and sleepHrefs($value) methods.
 * - For #3-5 use the checkImgTags($value, $options) method, where $options specifies 3-5.
 * 
 * Runtime errors are logged to: /site/assets/logs/markup-qa-errors.txt
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 */ 

class MarkupQA extends Wire {
	
	const errorLogName = 'markup-qa-errors';

	/**
	 * @var string
	 * 
	 */
	protected $assetsURL = '';

	/**
	 * @var Page
	 * 
	 */
	protected $page;

	/**
	 * @var Field
	 * 
	 */
	protected $field;

	/**
	 * Markup QA custom settings
	 * 
	 * Can be specified in $config->markupQA = [ ... ]
	 * 
	 * - `ignorePaths` (array): Starting paths that should be ignored during link abstraction. Any paths that begin
	 *    with one of these will be left alone. Note these are paths rather than URLs, so if the site runs off a 
	 *    subdirectory, then you should exclude the subdirectory in these paths. 
	 * - `debug` (bool): Show debugging info to superusers? (default=false). May also be specified in $config->debugMarkupQA=true;
	 * - `verbose` (bool): Whether or not to track verbose info to $page: `$page->_markupQA = [ 'field_name' => [ counts ]]`
	 * 
	 * @var array
	 * 
	 */
	protected $settings = array(
		'ignorePaths' => array(),
		'debug' => false,
		'verbose' => false,
	);

	/**
	 * Construct
	 * 
	 * @param Page|null $page
	 * @param Field|null $field
	 *
	 */
	public function __construct(?Page $page = null, ?Field $field = null) {
		parent::__construct();
		if($page) {
			$this->setPage($page);
			$page->wire($this);
		}
		if($field) {
			$this->setField($field);
			if(!$page) $field->wire($this);
		}
		$config = $this->wire()->config;
		$this->assetsURL = $config->urls->assets;
		$settings = $config->markupQA;
		if(is_array($settings) && count($settings)) {
			if(!empty($settings['ignorePaths'])) $this->ignorePaths($settings['ignorePaths']);
			if(!empty($settings['debug'])) $this->debug(true);
			if(!empty($settings['verbose'])) $this->verbose(true);
		}
		if($config->debugMarkupQA) $this->debug(true);
	}

	/**
	 * Set the current Page
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/**
	 * Set the current Field
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field; 
	}

	/**
	 * Get or set paths to ignore for link abstraction
	 * 
	 * To get ignored paths call function with no arguments. Otherwise you are setting them. 
	 * 
	 * @param array|string|null $paths Array of paths or string of one path, or CSV or newline separated string of multiple paths. 
	 * @param bool $replace True to replace all existing paths, or false to merge with existing paths (default=false)
	 * @return array Returns array of current ignore paths
	 * @throws WireException if given invalid $paths argument
	 * 
	 */
	public function ignorePaths($paths = null, $replace = false) {
		if($paths === null) {
			return $this->settings['ignorePaths'];
		} else if(is_string($paths)) {
			// string of one path or CSV/newline separated multiple paths
			$paths = trim($paths);
			if(strpos($paths, "\n")) {
				$paths = explode("\n", $paths);
			} else if(strpos($paths, ",")) {
				$paths = explode(",", $paths);
			} else {
				$paths = strlen($paths) ? array($paths) : array();
			}
			foreach($paths as $k => $v) $paths[$k] = trim($v); // remove any remaining whitespace
		}
		if(!is_array($paths)) throw new WireException('setIgnorePaths() requires array or string');
		if(!$replace) $paths = array_merge($this->settings['ignorePaths'], $paths);
		$this->settings['ignorePaths'] = $paths;
		return $paths;
	}

	/**
	 * Get or set debug status
	 * 
	 * Applies only if current user is a superuser
	 * 
	 * @param bool|null $set Omit this argument to get or specify bool to set
	 * @return bool
	 * 
	 */
	public function debug($set = null) {
		if(is_bool($set)) {
			if($set === true) {
				$user = $this->wire()->user;
				if(!$user || !$user->isSuperuser()) $set = false;
			}
			$this->settings['debug'] = $set;
		}
		return $this->settings['debug'];
	}

	/**
	 * Get or set verbose state
	 * 
	 * Whether or not to set/track verbose information to page, i.e.
	 * `$page->_markupQA = array('field_name' => array(counts))`
	 * 
	 * When getting, if $page or $field have not been populated, verbose is always false. 
	 *
	 * @param bool|null $set Omit this argument to get or specify bool to set
	 * @return bool
	 *
	 */
	public function verbose($set = null) {
		if(is_bool($set)) $this->settings['verbose'] = $set;
		return $this->settings['verbose'] && $this->page && $this->field ? true : false;
	}

	/**
	 * Wakeup URLs in href or src attributes for presentation
	 *
	 * @param $value
	 *
	 */
	public function wakeupUrls(&$value) {
		$this->checkUrls($value, false);
	}

	/**
	 * Sleep URLs in href or src attributes for storage
	 *
	 * @param $value
	 *
	 */
	public function sleepUrls(&$value) {
		$this->checkUrls($value, true);
	}

	/**
	 * Wake URLs for wakeup or sleep, converting root URLs as necessary
	 * 
	 * @param string $value
	 * @param bool $sleep
	 * 
	 */
	protected function checkUrls(&$value, $sleep = false) {

		// see if quick exit possible
		if(stripos($value, 'href=') === false && stripos($value, 'src=') === false) return;

		$config = $this->wire()->config;
		$httpHost = $config->httpHost;
		$rootURL = $config->urls->root;
		$rootHostURL = $httpHost . $rootURL;

		$replacements = array(
			// wakeup => sleep
			" href=\"$rootURL" => "\thref=\"/",
			" href='$rootURL" => "\thref='/",
			" src=\"$rootURL" => "\tsrc=\"/",
			" src='$rootURL" => "\tsrc='/",
		);
		
		if(strpos($value, "//$rootHostURL")) $replacements = array_merge($replacements, array(
			// wakeup => sleep
			" href='http://$rootHostURL" => "\thref='http://$httpHost/",
			" href='https://$rootHostURL" => "\thref='https://$httpHost/",
			" href=\"http://$rootHostURL" => "\thref=\"http://$httpHost/",
			" href=\"https://$rootHostURL" => "\thref=\"https://$httpHost/",
		));
		
		if($sleep) {
			// sleep 
			$value = str_ireplace(array_keys($replacements), array_values($replacements), $value);

			if($this->verbose()) {
				$info = $this->page->get('_markupQA');	
				if(!is_array($info)) $info = array();
				if(!isset($info[$this->field->name]) || !is_array($info[$this->field->name])) $info[$this->field->name] = array();
				$info[$this->field->name]['href'] = substr_count($value, "\thref=");
				$info[$this->field->name]['src'] = substr_count($value, "\tsrc=");
				$this->page->setQuietly('_markupQA', $info);
			}

		} else if(strpos($value, "\t") === false) {
			// wakeup, but nothing necessary (quick exit)
			return;

		} else {
			// wakeup
			$value = str_ireplace(array_values($replacements), array_keys($replacements), $value);
		}
	}

	/**
	 * Convert a relative path to be absolute
	 * 
	 * @param string $path
	 * @return string Returns absolute path, or blank string on error
	 * 
	 */
	protected function relativeToAbsolutePath($path) {
		
		// path is relative, i.e. "something/" or "./something/ or "../something/" or similar
		$_path = $path;
		$page = $this->page;
		$slashUrls = $page->template->slashUrls;

		if(strpos($path, './') === 0) {
			// remove leading "./" reference, making "./something/" => "something/"
			$path = substr($path, 2);
		}

		if(strpos($path, '.') !== 0 && !$slashUrls) {
			// path like "something/"
			// if slashUrls are not in use, then the meaning of "./" is parent rather than page
			$page = $page->parent();
			$slashUrls = $page->template->slashUrls;
		}

		// resolve leading "../" to a $page
		while(strpos($path, '../') === 0) {
			$page = $slashUrls ? $page->parent() : $page->parent()->parent();
			$path = substr($path, 3);
			$slashUrls = $page->template->slashUrls;
			if(!$page->id) break;
		}

		if(!$page->id) {
			// path resolved outside of PW's tree
			$path = '';
		} else if(strlen($path)) {
			// resolve path from $page plus remaining path
			$path = rtrim($page->path, '/') . '/' . ltrim($path, '/');
		} else {
			// resolve path from $page
			$path = $page->path;
		}
		
		if($path && $path != $_path) {
			if($this->debug()) $this->message("MarkupQA absoluteToRelative converted: $_path => $path");
		}

		return $path;
	}

	/**
	 * Sleep href attributes, adding a data-pwid attribute to <a> tags that resolve to a Page
	 * 
	 * Should be used AFTER sleepUrls() has already been called, so that any URLs are already 
	 * relative to "/" rather than potential "/subdir/". 
	 * 
	 * @param string $value
	 * 
	 */
	public function sleepLinks(&$value) {
	
		// if there are no href attributes, there's nothing to sleep 
		// if there is already a data-pwid attribute present, then links are already asleep
		if(strpos($value, 'href=') === false || strpos($value, 'data-pwid=')) return;
	
		$info = $this->verbose() ? $this->page->get('_markupQA') : array();
		if(!is_array($info)) $info = array();
		
		$counts = array(
			'external' => 0,
			'internal' => 0,
			'relative' => 0,
			'files' => 0,
			'other' => 0,
			'unresolved' => 0,
			'nohttp' => 0,
			'ignored' => 0,
		);
		
		if(isset($info[$this->field->name])) {
			$counts = array_merge($counts, $info[$this->field->name]);	
		}
		
		$re = '!' . 
			'(<a[^\t<>]*?)' . // 1:"start" which includes the starting <a tag and everything before href attribute
			'([\t ]+href=(?:["\'](?:https?:)?//[^/"\'\s<>]+|["\']))' . // 2:"href" with optional hostname
			'([-_./a-z0-9]*)' . // 3:"path" in ProcessWire page name format
			'([^<>]*>)' . // 4:"end" which includes everything after the path (potential query string, other attrs, etc.)
			'!i'; 
		
		if(!preg_match_all($re, $value, $matches)) return;
		
		$replacements = array();
		$languages = $this->wire()->languages;
		$debug = $this->debug();
		$config = $this->wire()->config;
		$pages = $this->wire()->pages;
		
		if($languages && !$languages->hasPageNames()) $languages = null;
		
		foreach($matches[3] as $key => $path) {
			
			if(!strlen($path)) continue;
			
			$full = $matches[0][$key];
			$start = $matches[1][$key];
			$href = $matches[2][$key];
			$end = $matches[4][$key];
			$_path = $path; // original unmodified path
		
			if(strpos($href, '//')) {
				// scheme and hostname present
				/** @noinspection PhpUnusedLocalVariableInspection */
				list($x, $host) = explode('//', $href);
				if($host != $config->httpHost && !in_array($host, $config->httpHosts)) {
					$counts['external']++;
					if($debug) $this->message("MarkupQA sleepLinks skipping because hostname: $host");
					// external hostname, which we will skip over
					continue;
				}
			} else if(strpos($href, ':') !== false || strpos($end, ':') === 0) {
				// non http link like mailto: or tel: 
				$counts['nohttp']++;	
				continue;
			}
		
			if(strpos($path, '/') !== 0) {
				// convert relative path to absolute
				$path = $this->relativeToAbsolutePath($path);
				if(!strlen($path)) continue;
				if($path != $_path) $counts['relative']++;
				
			} else if(strrpos($path, '.') > strrpos($path, '/')) {
				// not relative and possibly a filename
				// if this link is to a file that exists, then it's not a page link so skip it
				$file = $config->paths->root . ltrim($path, '/');
				if(file_exists($file)) {
					$counts['files']++;
					continue;
				}
			}
	
			// check if this path is in the ignored paths list
			$ignored = false;
			foreach($this->ignorePaths() as $ignorePath) {
				if(strpos($path, $ignorePath) !== 0) continue;
				if($debug) $this->message("MarkupQA sleepLinks skipped $path because it matches ignored path $ignorePath"); 
				$counts['ignored']++;
				$ignored = true;
				break;
			}
			if($ignored) continue;
	
			// get the page for the path
			$getByPathOptions = array(
				'useLanguages' => $languages ? true : false,
				'allowUrlSegments' => true,
				'useHistory' => true
			);
			$page = $pages->getByPath($path, $getByPathOptions);
			if(!$page->id) {
				// if not found try again with non-urlSegment partial matching
				$getByPathOptions['allowUrlSegments'] = false;
				$page = $pages->getByPath($path, $getByPathOptions);
			}
			$pageID = $page->id;
			
			if($pageID) {
				// resolved to a page
				$urlSegments = $page->get('_urlSegments');
				$urlSegmentStr = is_array($urlSegments) ? implode('/', $urlSegments) : '';
				
				if($languages) {
					/** @var Language $language */
					$language = $languages->pageNames()->getPagePathLanguage($path, $page);
					$pwid = !$language || $language->isDefault() ? $pageID : "$pageID-$language";
				} else {
					$language = null;
					$pwid = $pageID;
				}
				if($urlSegmentStr) {
					// append url segment path to the pwid
					$pwid .= "/$urlSegmentStr";
				}
				$replacements[$full] = "$start\tdata-pwid=$pwid$href$path$end";
				$counts['internal']++;
				if($debug) {
					$langName = $language ? $language->name : 'n/a';
					$this->message(
						"MarkupQA sleepLinks (field=$this->field, page={$this->page->path}, lang=$langName): " . 
						"$full => " . $replacements[$full]
					);
				}
			} else {
				// did not resolve to a page, see if it resolves to a file or directory
				$file = $config->paths->root . ltrim($path, '/');
				if(file_exists($file)) {
					if($debug) $this->message("MarkupQA sleepLinks link resolved to a file: $path");
					$counts['files']++;
				} else {
					$parts = explode('/', trim($path, '/'));
					$firstPart = array_shift($parts);
					$test = $config->paths->root . $firstPart; 
					if(is_dir($test)) {
						// possibly to something in another application, i.e. processwire.com/talk/
						$counts['other']++;
					} else {
						$counts['unresolved']++;
						$this->linkWarning($_path, false);
					}
				}
			}
		}
		
		if(count($replacements)) {
			$value = str_replace(array_keys($replacements), array_values($replacements), $value);
		}
		
		$info[$this->field->name] = $counts;
		if($this->verbose()) $this->page->setQuietly('_markupQA', $info);
	}

	/**
	 * Wakeup href attributes, using the data-pwid attribute to update the href attribute as necessary
	 * 
	 * Should be used BEFORE wakeupUrls() is called so that href attributes are relative to "/" rather than
	 * a potential "/subdir/" that wouldn't be recognized as a page path.
	 * 
	 * @param $value
	 * @return array Returns array of replacements that were made (3.0.184+)
	 * 
	 */
	public function wakeupLinks(&$value) {

		// if there's no data-pwid attribute present, then there's nothing to do here
		if(strpos($value, 'data-pwid=') === false) return array();
		
		$re = '!' . 
			'(<a[^\t<>]*?)' . // 1:"start" which includes "<a" and everything up until data-pwid attribute
			'\tdata-pwid=([-\d]+(?:/[-_./a-z0-9]+)?)' . // 2:"pwid" integer of page id ($pageID) referenced by the link (123-11/urlSegmentStr)
			'([\t ]+href=(?:["\'](?:https?:)?//[^/"\'\s<>]+|["\']))' . // 3:"href" attribute and optional scheme+hostname
			'([-_./a-z0-9]+)' . // 4:"path" in PW page name format
			'([^<>]*>)' . // 5:"end" which includes everything else and closing ">", i.e. query string, other attrs, etc.
			'!i';
		
		if(!preg_match_all($re, $value, $matches)) return array();
		
		$replacements = array();
		$languages = $this->wire()->languages;
		$config = $this->wire()->config;
		$rootURL = $config->urls->root;
		$adminURL = $config->urls->admin;
		$adminPath = $rootURL === '/' ? $adminURL : str_replace($rootURL, '/', $adminURL);
		$debug = $this->debug();
		
		foreach($matches[2] as $key => $pwid) {
			
			if(strpos($pwid, '/')) {
				list($pwid, $urlSegmentStr) = explode('/', $pwid, 2);
			} else {
				$urlSegmentStr = '';
			}
			
			if(strpos($pwid, '-')) {
				list($pageID, $languageID) = explode('-', $pwid);
			} else {
				$pageID = $pwid;
				$languageID = 0;
			}

			$pageID = (int) $pageID;
			$full = $matches[0][$key];
			$start = $matches[1][$key];
			$href = $matches[3][$key];
			$path = $matches[4][$key];
			$end = $matches[5][$key];
			
			if($languages) {
				$language = $languageID ? $languages->get((int) $languageID) : $languages->getDefault();
			} else {
				$language = null;
			}
		
			$livePath = $this->getPagePathFromId($pageID, $language);
			
			if($urlSegmentStr) {
				$livePath = rtrim($livePath, '/') . "/$urlSegmentStr";
				if(substr($path, '-1') === '/') $livePath .= '/';
			}
			
			if(strlen($rootURL) > 1) {
				$livePath = rtrim($rootURL, '/') . $livePath;
				$href = ' ' . ltrim($href); // immunity to wakeupUrls(), replacing tab with space
			}
			
			$langName = $debug && $language ? $language->name : '';
			
			if($livePath) {
				$ignore = false;
				foreach($this->ignorePaths() as $ignorePath) {
					if(strpos($livePath, $ignorePath) !== 0) continue;
					if($debug) $this->message("MarkupQA wakeupLinks path $livePath matches ignored path $ignorePath");
					$ignore = true;
					break;
				}
				if($path && substr($path, -1) != '/') {
					// no trailing slash, retain the editors wishes here
					$livePath = rtrim($livePath, '/');
				}
				if($ignore) {
					// path should be ignored and left as-is
				} else if(strpos($livePath, '/trash/') !== false) {
					// linked page is in trash, we won't update it but we'll produce a warning
					$this->linkWarning("$path => $livePath (" . $this->_('it is in the trash') . ')');
					continue;
				} else if(strpos($livePath, $adminPath) === 0) {
					// do not update paths that point in admin
					$this->linkWarning("$path => $livePath (" . $this->_('points to the admin') . ')');
					continue;
				} else if($livePath != $path) {
					// path differs from what's in the markup and should be updated
					if($debug) $this->warning(
						"MarkupQA wakeupLinks PATH UPDATED (field=$this->field, page={$this->page->path}, " . 
						"language=$langName): $path => $livePath"
					);
					$path = $livePath;
				} else if($debug) {
					$this->message("MarkupQA wakeupLinks no changes (field=$this->field, language=$langName): $path => $livePath");
				}
			} else {
				// did not resolve to a PW page
				$this->linkWarning("wakeup: $path");
			}
			
			$replacements[$full] = "$start$href$path$end";
		}

		if(count($replacements)) {
			$value = str_replace(array_keys($replacements), array_values($replacements), $value);
		}
		
		return $replacements;
	}

	/**
	 * Find pages linking to another
	 * 
	 * @param Page|null $page Page to find links to, or omit to use page specified in constructor
	 * @param array $fieldNames Field names to look in or omit to use field specified in constructor
	 * @param string $selector Optional selector to use as a filter
	 * @param array $options Additional options
	 *  - `getIDs` (bool): Return array of page IDs rather than Page instances. (default=false)
	 *  - `getCount` (bool): Return a total count (int) of found pages rather than Page instances. (default=false)
	 *  - `confirm` (bool): Confirm that the links are present by looking at the actual page field data. (default=true)
	 *     You can specify false for this option to make it perform faster, but with a potentially less accurate result.
	 * @return PageArray|array|int
	 * 
	 */
	public function findLinks(?Page $page = null, $fieldNames = array(), $selector = '', array $options = array()) {
		
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$database = $this->wire()->database;
		
		$defaults = array(
			'getIDs' => false,
			'getCount' => false, 
			'confirm' => true 
		);

		$options = array_merge($defaults, $options);
		
		if($options['getIDs']) {
			$result = array();
		} else if($options['getCount']) {
			$result = 0;
		} else {
			$result = $pages->newPageArray();
		}
		
		if(!$page) $page = $this->page;
		if(!$page) return $result;
		
		if(empty($fieldNames)) {
			if($this->field) $fieldNames[] = $this->field->name;
			if(empty($fieldNames)) return $result;
		}
		
		if($selector === true) $selector = "include=all";
		$op = strlen("$page->id") > 3 ? "~=" : "%=";
		$selector = implode('|', $fieldNames) . "$op'$page->id', id!=$page->id, $selector";
		$selector = trim($selector, ', ');
		
		
		// find pages
		if($options['getCount'] && !$options['confirm']) {
			// just return a count
			return $pages->count($selector);
		} else {
			// find the IDs
			$checkIDs = array();
			$foundIDs = $pages->findIDs($selector);
			if(!count($foundIDs)) return $result;
			if($options['confirm']) {
				$checkIDs = array_flip($foundIDs);
				$foundIDs = array();
			}
		}
		
		// confirm results
		foreach($fieldNames as $fieldName) {
			if(!count($checkIDs)) break;
			$field = $fields->get($fieldName);
			if(!$field) continue;
			$table = $field->getTable();
			$ids = implode(',', array_keys($checkIDs));
			$sql = "SELECT * FROM `$table` WHERE `pages_id` IN($ids)";
			$query = $database->prepare($sql);
			$query->execute();

			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$pageID = (int) $row['pages_id'];
				if(isset($foundIDs[$pageID])) continue;
				$row = implode(' ', $row);
				$find = "data-pwid=$page->id";
				// first check if it might be there
				if(!strpos($row, $find)) continue;
				// then confirm with a more accurate check
				if(!strpos($row, "$find ") && !strpos($row, "$find\t") && !strpos($row, "$find-")) continue;
				// at this point we have confirmed that this item links to $page
				unset($checkIDs[$pageID]);
				$foundIDs[$pageID] = $pageID;
			}
			
			$query->closeCursor();
		}
	
		if(count($foundIDs)) {
			if($options['getIDs']) {
				$result = $foundIDs;
			} else if($options['getCount']) {
				$result = count($foundIDs); 
			} else {
				$result = $pages->getById($foundIDs);
			}
		}
	
		return $result;
	}

	/**
	 * Display and log a warning about a path that didn't resolve
	 * 
	 * @param string $path
	 * @param bool $logWarning
	 * 
	 */
	protected function linkWarning($path, $logWarning = true) {
		if($this->wire()->page->template == 'admin' && $this->wire()->process == 'ProcessPageEdit') {
			$this->warning(sprintf(
				$this->_('Unable to resolve link on page %1$s in field "%2$s": %3$s'), 
				$this->page->path, 
				$this->field->getLabel(), 
				$path
			));
		}
		if($this->verbose() || $logWarning) {
			$this->error("Unable to resolve link: $path");
		}
	}

	/**
	 * Quality assurance for <img> tags
	 *
	 * @param string $value
	 * @param array $options What actions should be performed:
	 *  - replaceBlankAlt (bool): Replace blank alt attributes with file description? (default=true)
	 *  - removeNoExists (bool): Remove references to images that don't exist (or re-create images when possible) (default=true)
	 *  - removeNoAccess (bool): Remove references to images user doesn't have view permission to (default=true)
	 *
	 */
	public function checkImgTags(&$value, array $options = array()) {
		if(strpos($value, '<img ') !== false && preg_match_all('{(<' . 'img [^>]+>)}', $value, $matches)) {
			foreach($matches[0] as $img) {
				$this->checkImgTag($value, $img, $options);
			}
		}
	}

	/**
	 * Quality assurance for one <img> tag
	 *
	 * @param string $value Entire markup
	 * @param string $img Just the found <img> tag
	 * @param array $options What actions should be performed:
	 *  - replaceBlankAlt (bool): Replace blank alt attributes with file description? (default=true)
	 *  - removeNoExists (bool): Remove references to images that don't exist (or re-create images when possible) (default=true)
	 *  - removeNoAccess (bool): Remove references to images user doesn't have view permission to (default=true)
	 *
	 */
	protected function checkImgTag(&$value, $img, array $options = array()) {
		
		$defaults = array(
			'replaceBlankAlt' => true,
			'removeNoExists' => true,
			'removeNoAccess' => true, 
		);
		
		$options = array_merge($defaults, $options);
		$replaceAlt = ''; // exact text to replace for blank alt attribute, i.e. alt=""
		$src = '';
		$user = $this->wire()->user;
		$attrStrings = explode(' ', $img); // array of strings like "key=value"

		if($this->verbose()) {
			$markupQA = $this->page->get('_markupQA');
			if(!is_array($markupQA)) $markupQA = array();
			if(!isset($markupQA[$this->field->name])) $markupQA[$this->field->name] = array();
			$info =& $markupQA[$this->field->name];
		} else {
			$markupQA = null;
			$info = array();
		}

		if(!isset($info['img_unresolved'])) $info['img_unresolved'] = 0;
		if(!isset($info['img_fixed'])) $info['img_fixed'] = 0;
		if(!isset($info['img_noalt'])) $info['img_noalt'] = 0; // blank alt

		// determine current 'alt' and 'src' attributes
		foreach($attrStrings as $attr) {

			if(!strpos($attr, '=')) continue;
			list($name, $val) = explode('=', $attr);

			$name = strtolower($name);
			$val = trim($val, "\"'> ");

			if($name == 'alt' && !strlen($val)) {
				$replaceAlt = $attr;

			} else if($name == 'src') {
				$src = $val;
			}
		}

		// if <img> had no src attr, or if it was pointing to something outside of PW assets, skip it
		if(!$src || strpos($src, $this->assetsURL) === false) return;

		// recognized site image, make sure the file exists
		/** @var Pageimage $pagefile */
		$pagefile = $this->page->filesManager()->getFile($src);

		// if this doesn't resolve to a known pagefile, stop now
		if(!$pagefile) {
			if($options['removeNoExists']) {
				if(file_exists($this->page->filesManager()->path() . basename($src))) {
					// file exists, but we just don't know what it is - leave it alone
				} else {
					$this->error("Image file no longer exists: $src");
					if($this->page->of()) $value = str_replace($img, '', $value);
					$info['img_unresolved']++;
				}
			}
			return;
		}

		if($options['removeNoAccess']) {
			if(($pagefile->page->id != $this->page->id && !$user->hasPermission('page-view', $pagefile->page))
				|| ($pagefile->field && !$pagefile->page->viewable($pagefile->field))) {
				// if the file resolves to another page that the user doesn't have access to view, 
				// OR user doesn't have permission to view the field that $pagefile is in,
				// then we will simply remove the image
				$this->error("Image referenced that user does not have view access to: $src");
				if($this->page->of()) $value = str_replace($img, '', $value);
				return;
			}
		}
		
		/*
		 * @todo potential replacement for 'removeNoAccess' block above
		 * Regarding: https://github.com/processwire/processwire-issues/issues/1548
		 * 
		// if(($pagefile->page->id != $this->page->id && !$user->hasPermission('page-view', $pagefile->page))
		if($options['removeNoAccess']) {
			// if the file resolves to another page that the user doesn't have access to view, 
			// OR user doesn't have permission to view the field that $pagefile is in, remove image
			$page = $pagefile->page;
			$field = $pagefile->field;
			$removeImage = false;
			if(wireInstanceOf($page, 'RepeaterPage')) {
				$page = $page->getForPageRoot();
				$field = $page->getForFieldRoot();
			}
			if($page->id != $this->page->id && !$page->viewable(false)) {
				$this->error("Image on page ($page->id) that user does not have view access to: $src");
				$removeImage = true;
			} else if($field && !$page->viewable($field)) {
				$this->error("Image on page:field ($page->id:$field) that user does not have view access to: $src");
				$removeImage = true;
			}
			if($removeImage) {
				if($this->page->of()) $value = str_replace($img, '', $value);
				return;
			}
		}
		*/

		if($options['replaceBlankAlt'] && $replaceAlt) {
			// image has a blank alt tag, meaning, we will auto-populate it with current file description, 
			// if output formatting is on
			if($this->page->of()) {
				$alt = $pagefile->description;
				if(strlen($alt)) {
					$alt = $this->wire()->sanitizer->entities1($alt);
					$_img = str_replace(" $replaceAlt", " alt=\"$alt\"", $img);
					$value = str_replace($img, $_img, $value);
				}
			}
			$info['img_noalt']++;
		}

		if($options['removeNoExists'] && $pagefile instanceof Pageimage) {
			$result = $this->checkImgExists($pagefile, $img, $src, $value);
			if($result < 0) $info['img_unresolved'] += abs($result);
			if($result > 0) $info['img_fixed'] += $result;
		}
		
		if($markupQA) $this->page->setQuietly('_markupQA', $markupQA);
	}

	/**
	 * Attempt to re-create images that don't exist, when possible
	 *
	 * @param Pageimage $pagefile
	 * @param $img
	 * @param $src
	 * @param $value
	 * @return int Returns 0 on no change, negative count on broken, positive count on fixed
	 *
	 */
	protected function checkImgExists(Pageimage $pagefile, $img, $src, &$value) {
		

		$basename = basename($src);
		$pathname = $pagefile->pagefiles->path() . $basename;

		if(file_exists($pathname)) return 0; // no action necessary

		// file referenced in <img> tag does not exist, and it is not a variation we can re-create
		if($pagefile->basename == $basename) {
			// original file no longer exists
			$this->error("Original image file no longer exists, unable to create new variation ($basename)");
			if($this->page->of()) $value = str_replace($img, '', $value); // remove reference to image, when output formatting is on
			return -1;
		}

		// check if this is a variation that we might be able to re-create
		$info = $pagefile->isVariation($basename);
		if(!$info) {
			// file is not a variation, so we apparently have no source to pull info from
			$this->error("Unrecognized image that does not exist ($basename)");
			if($this->page->of()) $value = str_replace($img, '', $value); // remove reference to image, when output formatting is on
			return -1;
		}

		$info['targetName'] = $basename; 
		$variations = array($info);
		while(!empty($info['parent'])) {
			$variations[] = $info['parent'];
			$info = $info['parent'];
		}
		
		$good = 0;
		$bad = 0;
		$debug = $this->debug() || $this->wire()->config->debug;
		
		foreach(array_reverse($variations) as $info) {
			// definitely a variation, attempt to re-create it
			$options = array();
			if($info['crop']) $options['cropping'] = $info['crop'];
			if($info['suffix']) {
				$options['suffix'] = $info['suffix'];
				if(in_array('hidpi', $options['suffix'])) $options['hidpi'] = true;
			}
			$newPagefile = $pagefile->size($info['width'], $info['height'], $options);
			if($newPagefile && is_file($newPagefile->filename())) {
				if(!empty($info['targetName']) && $newPagefile->basename != $info['targetName']) {
					// new name differs from what is in text. Rename file to be consistent with text.
					rename($newPagefile->filename(), $pathname);
				}
				if($debug) {
					$this->message($this->_('Re-created image variation') . " - $newPagefile->name");
				}
				$pagefile = $newPagefile; // for next iteration
				$good++;
			} else {
				$this->error($this->_('Unable to re-create image variation') . " - $newPagefile->name");
				$bad++;
			}
		}
		
		if($good) return $good;
		if($bad) return -1 * $bad;
		
		return 0;
	}

	/**
	 * Record error message to image-errors log
	 *
	 * @param string $text
	 * @param int $flags
	 * @return $this
	 * 
	 */
	public function error($text, $flags = 0) {
		$logText = "$text (field={$this->field->name}, id={$this->page->id}, path={$this->page->path})";
		$this->wire()->log->save(self::errorLogName, $logText);
		/*
		if($this->wire('modules')->isInstalled('SystemNotifications')) {
			$user = $this->wire('modules')->get('SystemNotifications')->getSystemUser();
			if($user && !$user->notifications->getBy('title', $text)) {
				$no = $user->notifications()->getNew('error');
				$no->title = $text; 
				$no->html = "<p>Field: {$this->field->name}\n<br />Page: <a href='{$this->page->url}'>{$this->page->title}</a></p>";
				$user->notifications->save(); 
			}
		}
		*/
		return $this;
	}

	/**
	 * Get or set a setting
	 * 
	 * @param string $key Setting name to get or set, or omit to get all settings
	 * @param string|array|int|null $value Setting value to set, or omit when getting setting
	 * @return string|array|int|null|$this Returns value of $key
	 * 
	public function setting($key = null, $value = null) {
		if($key === null) return $this->settings; // return all
		if($value === null) return isset($this->settings[$key]) ? $this->settings[$key] : null; // return one
		if($key === 'ignorePaths') return $this->ignorePaths($value); // set specific
		$this->settings[$key] = $value; // set
		return $value;
	}
	 */
	
	/**
	 * Enable or disable verbose mode
	 *
	 * Sets whether or not to set/track verbose information to page, i.e.
	 * `$page->_markupQA = array('field_name' => array(counts))`
	 *
	 * #pw-internal
	 *
	 * @param bool $verbose
	 * @deprecated use verbose() method instead
	 *
	 */
	public function setVerbose($verbose) {
		$this->settings['verbose'] = $verbose ? true : false;
	}

	/**
	 * Given page ID return the path to it
	 * 
	 * @param int $pageID
	 * @param Language|null $language
	 * @return string
	 * @since 3.0.231
	 * 
	 */
	protected function getPagePathFromId($pageID, $language = null) {
		
		$pages = $this->wire()->pages;
		$path = null;
		
		if($this->isPagePathHooked()) {
			$page = $pages->get($pageID);
			if($page->id) {
				if($language && $language->id) {
					$languages = $this->wire()->languages;
					$languages->setLanguage($language);
					$path = $page->path();
					$languages->unsetLanguage();
				} else {
					$path = $page->path();
				}
			}
		}
		
		if($path === null) {
			$path = $pages->getPath($pageID, array(
				'language' => $language
			));
		}
		
		return $path;
	}

	/**
	 * Is the Page::path method hooked in a manner that might affect MarkupQA? 
	 * 
	 * @return bool
	 * @since 3.0.231
	 * 
	 */
	protected function isPagePathHooked() {
		$config = $this->wire()->config;
		$property = '_MarkupQA_pagePathHooked';
		$hooked = $config->get($property);
		if($hooked !== null) return $hooked;
		$hooks = $this->wire()->hooks;
		$hooked = $hooks->isHooked('Page::path()');
		if($hooked) {
			// only consider Page::path hooked if something other than LanguageSupportPageNames hooks it
			$hookItems = $hooks->getHooks($this->page, 'path', WireHooks::getHooksStatic);
			foreach($hookItems as $key => $hook) {
				if(((string) $hook['toObject']) === 'LanguageSupportPageNames') unset($hookItems[$key]);
			}
			$hooked = count($hookItems) > 0;
		}
		$config->setQuietly($property, $hooked);
		return $hooked;
	}

}
