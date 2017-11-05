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
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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
	 * Whether or not to track verbose info to $page
	 * 
	 * $page->_markupQA = array('field_name' => array(counts)))
	 * 
	 * @var bool
	 * 
	 */
	protected $verbose = false;

	/**
	 * Whether verbose debug mode is active
	 * 
	 * @var bool
	 * 
	 */
	protected $debug = false;

	/**
	 * Construct
	 * 
	 * @param Page $page
	 * @param Field $field
	 *
	 */
	public function __construct(Page $page = null, Field $field = null) {
		if($page) $this->setPage($page);
		if($field) $this->setField($field);
		$this->assetsURL = $this->wire('config')->urls->assets;
		if($this->wire('config')->debugMarkupQA) {
			$user = $this->wire('user');
			if($user) $this->debug = $user->isSuperuser();
		}
	}

	/**
	 * Enable or disable verbose mode
	 * 
	 * @param bool $verbose
	 * 
	 */
	public function setVerbose($verbose) {
		$this->verbose = $verbose ? true : false;
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

		$config = $this->wire('config');
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

			if($this->verbose && $this->page && $this->field) {
				$info = $this->page->get('_markupQA');	
				if(!is_array($info)) $info = array();
				if(!is_array($info[$this->field->name])) $info[$this->field->name] = array();
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
			if($this->debug) $this->message("MarkupQA absoluteToRelative converted: $_path => $path");
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
	
		$info = $this->verbose ? $this->page->get('_markupQA') : array();
		if(!is_array($info)) $info = array();
		if(isset($info[$this->field->name])) {
			$counts = $info[$this->field->name];	
		} else {
			$counts = array(
				'external' => 0,
				'internal' => 0,
				'relative' => 0,
				'files' => 0,
				'other' => 0,
				'unresolved' => 0,
				'nohttp' => 0, 
			);
		}
		
		$re = '!' . 
			'(<a[^\t<>]*?)' . // 1:"start" which includes the starting <a tag and everything before href attribute
			'([\t ]+href=(?:["\'](?:https?:)?//[^/"\'\s<>]+|["\']))' . // 2:"href" with optional hostname
			'([-_./a-z0-9]*)' . // 3:"path" in ProcessWire page name format
			'([^<>]*>)' . // 4:"end" which includes everything after the path (potential query string, other attrs, etc.)
			'!i'; 
		
		if(!preg_match_all($re, $value, $matches)) return;
		
		$replacements = array();
		$languages = $this->wire('languages');
		if($languages && !$this->wire('modules')->isInstalled('LanguageSupportPageNames')) $languages = null;
		
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
				if($host != $this->wire('config')->httpHost && !in_array($host, $this->wire('config')->httpHosts)) {
					$counts['external']++;
					if($this->debug) $this->message("MarkupQA sleepLinks skipping because hostname: $host");
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
				$file = $this->wire('config')->paths->root . ltrim($path, '/');
				if(file_exists($file)) {
					$counts['files']++;
					continue;
				}
			}
		
			$pageID = $this->wire('pages')->getByPath($path, array(
				'getID' => true,
				'useLanguages' => $languages ? true : false,
				'useHistory' => true
			));
			
			if($pageID) {
				// resolved to a page
				if($languages) {
					$page = $this->wire('pages')->get($pageID);
					/** @var Language $language */
					$language = $this->wire('modules')->get('LanguageSupportPageNames')->getPagePathLanguage($path, $page);
					$pwid = !$language || $language->isDefault() ? $pageID : "$pageID-$language";
				} else {
					$language = null;
					$pwid = $pageID;
				}
				$replacements[$full] = "$start\tdata-pwid=$pwid$href$path$end";
				$counts['internal']++;
				if($this->debug) {
					$langName = $language ? $language->name : 'n/a';
					$this->message(
						"MarkupQA sleepLinks (field=$this->field, page={$this->page->path}, lang=$langName): " . 
						"$full => " . $replacements[$full]
					);
				}
			} else {
				// did not resolve to a page, see if it resolves to a file or directory
				$file = $this->wire('config')->paths->root . ltrim($path, '/');
				if(file_exists($file)) {
					if($this->debug) $this->message("MarkupQA sleepLinks link resolved to a file: $path");
					$counts['files']++;
				} else {
					$parts = explode('/', trim($path, '/'));
					$firstPart = array_shift($parts);
					$test = $this->wire('config')->paths->root . $firstPart; 
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
		if($this->verbose) $this->page->setQuietly('_markupQA', $info);
	}

	/**
	 * Wakeup href attributes, using the data-pwid attribute to update the href attribute as necessary
	 * 
	 * Should be used BEFORE wakeupUrls() is called so that href attributes are relative to "/" rather than
	 * a potential "/subdir/" that wouldn't be recognized as a page path.
	 * 
	 * @param $value
	 * 
	 */
	public function wakeupLinks(&$value) {

		// if there's no data-pwid attribute present, then there's nothing to do here
		if(strpos($value, 'data-pwid=') === false) return;
		
		$re = '!' . 
			'(<a[^\t<>]*?)' . // 1:"start" which includes "<a" and everything up until data-pwid attribute
			'\tdata-pwid=([-\d]+)' . // 2:"pwid" integer of page id ($pageID) referenced by the link
			'([\t ]+href=(?:["\'](?:https?:)?//[^/"\'\s<>]+|["\']))' . // 3:"href" attribute and optional scheme+hostname
			'([-_./a-z0-9]+)' . // 4:"path" in PW page name format
			'([^<>]*>)' . // 5:"end" which includes everything else and closing ">", i.e. query string, other attrs, etc.
			'!i';
		
		if(!preg_match_all($re, $value, $matches)) return;
		
		$replacements = array();
		$languages = $this->wire('languages');
		$rootURL = $this->wire('config')->urls->root;
		
		foreach($matches[2] as $key => $pwid) {
			
			if(strpos($pwid, '-')) {
				list($pageID, $languageID) = explode('-', $pwid);
			} else {
				$pageID = $pwid;
				$languageID = 0;
			}
			
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
			
			$livePath = $this->wire('pages')->getPath((int) $pageID, array(
				'language' => $language
			));
			
			if(strlen($rootURL) > 1) {
				$livePath = rtrim($rootURL, '/') . $livePath;
				$href = ' ' . ltrim($href); // immunity to wakeupUrls(), replacing tab with space
			}
			
			$langName = $this->debug && $language ? $language->name : '';
			
			if($livePath) {
				if($path && substr($path, -1) != '/') {
					// no trailing slash, retain the editors wishes here
					$livePath = rtrim($livePath, '/');
				}
				if(strpos($livePath, '/trash/') !== false) {
					// linked page is in trash, we won't update it but we'll produce a warning
					$this->linkWarning("$path => $livePath (" . $this->_('it is in the trash') . ')');
				} else if($livePath != $path) {
					// path differs from what's in the markup and should be updated
					if($this->debug) $this->warning(
						"MarkupQA wakeupLinks PATH UPDATED (field=$this->field, page={$this->page->path}, " . 
						"language=$langName): $path => $livePath"
					);
					$path = $livePath;
				} else if($this->debug) {
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
	}

	/**
	 * Display and log a warning about a path that didn't resolve
	 * 
	 * @param string $path
	 * @param bool $logWarning
	 * 
	 */
	protected function linkWarning($path, $logWarning = true) {
		if($this->wire('page')->template == 'admin' && $this->wire('process') == 'ProcessPageEdit') {
			$this->warning(sprintf(
				$this->_('Unable to resolve link on page %1$s in field "%2$s": %3$s'), 
				$this->page->path, 
				$this->field->name, 
				$path
			));
		}
		if($this->verbose || $logWarning) {
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
		if(strpos($value, '<img ') !== false && preg_match_all('{(<img [^>]+>)}', $value, $matches)) {
			foreach($matches[0] as $key => $img) {
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
		$user = $this->wire('user');
		$attrStrings = explode(' ', $img); // array of strings like "key=value"

		if($this->verbose) {
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
		foreach($attrStrings as $n => $attr) {

			if(!strpos($attr, '=')) continue;
			list($name, $val) = explode('=', $attr);

			$name = strtolower($name);
			$val = trim($val, "\"' ");

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
					$this->error("Image file no longer exists: " . basename($src) . ")");
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

		if($options['replaceBlankAlt'] && $replaceAlt) {
			// image has a blank alt tag, meaning, we will auto-populate it with current file description, 
			// if output formatting is on
			if($this->page->of()) {
				$alt = $pagefile->description;
				if(strlen($alt)) {
					$alt = $this->wire('sanitizer')->entities1($alt);
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
		
		foreach(array_reverse($variations) as $info) {
			// definitely a variation, attempt to re-create it
			$options = array();
			if($info['crop']) $options['cropping'] = $info['crop'];
			if($info['suffix']) {
				$options['suffix'] = $info['suffix'];
				if(in_array('hidpi', $options['suffix'])) $options['hidpi'] = true;
			}
			/** @var Pageimage $newPagefile */
			$newPagefile = $pagefile->size($info['width'], $info['height'], $options);
			if($newPagefile && is_file($newPagefile->filename())) {
				if(!empty($info['targetName']) && $newPagefile->basename != $info['targetName']) {
					// new name differs from what is in text. Rename file to be consistent with text.
					rename($newPagefile->filename(), $pathname);
				}
				if($this->debug || $this->wire('config')->debug) {
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
		$logText = "$text (page={$this->page->path}, field={$this->field->name})";
		$this->wire('log')->save(self::errorLogName, $logText);
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
}