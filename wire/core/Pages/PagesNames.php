<?php namespace ProcessWire;

/**
 * ProcessWire Pages Names
 *
 * #pw-headline Pages Names
 * #pw-breadcrumb Pages
 * #pw-var $pages->names
 * #pw-summary This class includes methods for generating and modifying page names.
 * #pw-body = 
 * While these methods are mosty for internal core use, some may at times be useful from the public API as well.
 * You can access methods from this class via the Pages API variable at `$pages->names()`.
 * ~~~~~
 * if($pages->names()->pageNameExists('something')) {
 *   // A page named “something” exists
 * }
 *
 * // generate a globally unique random page name 
 * $name = $pages->names()->uniqueRandomPageName();
 * ~~~~~
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 * 
 */ 

class PagesNames extends Wire {

	/**
	 * @var Pages
	 * 
	 */
	protected $pages; 

	/**
	 * Name for untitled/temporary pages
	 * 
	 * @var string
	 * 
	 */
	protected $untitledPageName = 'untitled';

	/**
	 * Delimiters that can separate words in page names
	 * 
	 * @var array
	 * 
	 */
	protected $delimiters = array('-', '_', '.');

	/**
	 * Default delimiter for separating words in page names
	 * 
	 * @var string
	 * 
	 */
	protected $delimiter = '-';

	/**
	 * Max length for page names
	 * 
	 * @var int
	 * 
	 */
	protected $nameMaxLength = 128;

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 * 
	 */
	public function __construct(Pages $pages) {
		$this->pages = $pages; 
		$pages->wire($this);
		$untitled = $this->wire()->config->pageNameUntitled;
		if($untitled) $this->untitledPageName = $untitled;
		$this->nameMaxLength = Pages::nameMaxLength;
		parent::__construct();
	}
	
	/**
	 * Assign a name to given Page (if it doesn’t already have one)
	 * 
	 * #pw-group-manipulators
	 * 
	 * @param Page $page Page to setup a new name for
	 * @param string $format Optional format string to use for name
	 * @return string Returns page name that was assigned
	 * 
	 */
	public function setupNewPageName(Page $page, $format = '') {
		
		$pageName = $page->name;

		// check if page already has a non-“untitled” name assigned that we should leave alone
		if(strlen($pageName) && !$this->isUntitledPageName($pageName)) return '';
		
		// determine what format should be used for the generated page name
		if(!strlen($format)) $format = $this->defaultPageNameFormat($page);
		
		// generate a page name from determined format
		$pageName = $this->pageNameFromFormat($page, $format);

		// ensure page name is unique	
		$pageName = $this->uniquePageName($pageName, $page);
		$page->setQuietly('_hasUniqueName', true);
		
		// assign to page
		$page->name = $pageName;
		
		// indicate that page has auto-generated name for savePageQuery (provides adjustName behavior for new pages)
		$page->setQuietly('_hasAutogenName', $pageName); 

		return $pageName;
	}

	/**
	 * Does the given page have an auto-generated name (during this request)?
	 * 
	 * #pw-group-informational
	 * 
	 * @param Page $page
	 * @return string|bool Returns auto-generated name if present, or boolean false if not
	 * 
	 */
	public function hasAutogenName(Page $page) {
		$name = $page->get('_hasAutogenName');
		if(empty($name)) $name = false;
		return $name;
	}

	/**
	 * Does the given page have a modified “name” during this request?
	 * 
	 * #pw-group-informational
	 * 
	 * @param Page $page
	 * @param bool|null $set Specify boolean true or false to set whether or not it has an adjusted name, or omit just to get
	 * @return bool
	 * 
	 */
	public function hasAdjustedName(Page $page, $set = null) {
		if(is_bool($set)) $page->setQuietly('_hasAdjustedName', $set);
		return $page->get('_hasAdjustedName') ? true : false;
	}

	/**
	 * Does the given page have an untitled page name?
	 * 
	 * #pw-group-informational
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function isUntitledPageName($name) {
		list($namePrefix,) = $this->nameAndNumber($name);
		return $namePrefix === $this->untitledPageName;
	}

	/**
	 * If given name has a numbered suffix, return array with name (excluding suffix) and the numbered suffix 
	 * 
	 * Returns array like `[ 'name', 123 ]` where `name` is name without the suffix, and `123` is the numbered suffix.
	 * If the name did not have a numbered suffix, then the 123 will be 0 and `name` will be the given `$name`.
	 * 
	 * #pw-group-informational
	 * 
	 * @param string $name
	 * @param string $delimiter Character(s) that separate name and numbered suffix
	 * @return array 
	 * 
	 */
	public function nameAndNumber($name, $delimiter = '') {
		if(empty($delimiter)) $delimiter = $this->delimiter;
		$fail = array($name, 0);
		if(strpos($name, $delimiter) === false) return $fail;
		$parts = explode($delimiter, $name);
		$suffix = array_pop($parts);
		if(!ctype_digit($suffix)) return $fail;
		$suffix = ltrim($suffix, '0');
		return array(implode($delimiter, $parts), (int) $suffix); 
	}

	/**
	 * Does the given name or Page have a number suffix? Returns the number if yes, or false if not
	 * 
	 * #pw-group-informational
	 * 
	 * @param string|Page $name
	 * @param bool $getNamePrefix Return the name prefix rather than the number suffix? (default=false)
	 * @return int|bool|string Returns false if no number suffix, or int for number suffix or string for name prefix (if requested)
	 * 
	 */
	public function hasNumberSuffix($name, $getNamePrefix = false) {
		if($name instanceof Page) $name = $name->name;
		list($namePrefix, $numberSuffix) = $this->nameAndNumber($name);
		if(!$numberSuffix) return false;
		return $getNamePrefix ? $namePrefix : $numberSuffix;
	}

	/**
	 * Get the name format string that should be used for given $page if no name was assigned
	 * 
	 * #pw-group-informational
	 * 
	 * @param Page $page
	 * @param array $options
	 *  - `fallbackFormat` (string): Fallback format if another cannot be determined (default='untitled-time').
	 *  - `parent` (Page|null): Optional parent page to use instead of $page->parent (default=null). 
	 * @return string
	 * 
	 */
	public function defaultPageNameFormat(Page $page, array $options = array()) {
		
		$defaults = array(
			'fallbackFormat' => 'untitled-time',
			'parent' => null, 
		);
		
		$options = array_merge($defaults, $options);
		$parent = $options['parent'] ? $options['parent'] : $page->parent();
		$format = ''; 

		if($parent && $parent->id && $parent->template->childNameFormat) {
			// if format specified with parent template, use that
			$format = $parent->template->childNameFormat;
			
		} else if(strlen("$page->title")) {
			// default format is title (when the page has one)
			$format = 'title';
			
		} else if($this->wire()->languages && $page->title instanceof LanguagesValueInterface) {
			// check for multi-language title
			/** @var LanguagesPageFieldValue $pageTitle */
			$pageTitle = $page->title;
			if(strlen($pageTitle->getDefaultValue())) $format = 'title';
		}
		
		if(empty($format)) {
			if($page->id && $options['fallbackFormat']) {
				$format = $options['fallbackFormat'];
			} else {
				$format = 'untitled-time';
			}
		}
		
		return $format;
	}

	/**
	 * Create a page name from the given format
	 *
	 * - Returns a generated page name that is not yet assigned to the page.
	 * - If no format is specified, it first falls back to page parent template `childNameFormat` property (if present).
	 * - If no format can be determined, it falls back to a randomly generated page name.
	 * - Does not check if page name is already in use.
	 *
	 * Options for $format argument:
	 *
	 * - `title` Build name based on “title” field.
	 * - `field` Build name based on any other field name you choose, replace “field” with any field name.
	 * - `text` Text already in the right format (that’s not a field name) will be used literally, replace “text” with your text.
	 * - `random` Randomly generates a name.
	 * - `untitled` Uses an auto-incremented “untitled” name.
	 * - `untitled-time` Uses an “untitled” name followed by date/time number string. 
	 * - `a|b|c` Builds name from first matching field name, where a|b|c are your field names.
	 * - `{field}` Builds name from the given field name.
	 * - `{a|b|c}` Builds name first matching field name, where a|b|c would be replaced with your field names.
	 * - `date:Y-m-d-H-i` Builds name from current date - replace “Y-m-d-H-i” with desired wireDate() format.
	 * - `string with space` A string that does not match one of the above and has space is assumed to be a wireDate() format.
	 * - `string with /` A string that does not match one of the above and has a “/” slash is assumed to be a wireDate() format.
	 *
	 * For formats above that accept a wireDate() format, see `WireDateTime::date()` method for format details. It accepts PHP
	 * date() format, PHP strftime() format, as well as some other predefined options.
	 * 
	 * #pw-group-generators
	 *
	 * @param Page $page
	 * @param string|array $format Optional format. If not specified, pulls from $page’s parent template.
	 * @param array $options Options to modify behavior. May also be specified in $format argument. 
	 *  - `language` (Language|string): Language to use
	 *  - `format` (string): Optional format to use, if $options were specified in $format argument. 
	 * @return string
	 *
	 */
	public function pageNameFromFormat(Page $page, $format = '', array $options = array()) {
		
		$defaults = array(
			'format' => '', 
			'language' => null, 
		);
		
		if(is_array($format)) {
			$options = $format;
			$format = empty($options['format']) ? '' : $options['format'];
		}
		
		$languages = $this->wire()->languages;
		$sanitizer = $this->wire()->sanitizer;
		
		$options = array_merge($defaults, $options);
		if(!strlen($format)) $format = $this->defaultPageNameFormat($page);
		$format = trim($format);
		$formatType = '';
		$defaultDateFormat = 'ymdHis';
		$name = '';
		
		if($languages && $options['language']) {
			$languages->setLanguage($options['language']); // receives language object, ID or name
			$language = $languages->getLanguage(); // always gets actual Language object
		} else {
			$language = null;
		}
		
		if($format === 'title' && !strlen(trim((string) $page->title))) {
			$format = 'untitled-time';
		}

		if($format === 'title') {
			// title	
			$name = trim((string) $page->title);
			$formatType = 'field';
			
		} else if($format === 'id' && $page->id) {
			// page ID, when it is known
			$name = (string) $page->id; 
			
		} else if($format === 'random') {
			// globally unique randomly generated page name
			$name = $this->uniqueRandomPageName();

		} else if($format === 'untitled') {
			// just untitled
			$name = $this->untitledPageName();
			
		} else if($format === 'untitled-time') {
			// untitled with datetime, i.e. “untitled-0yymmddhhmmss” (note leading 0 differentiates from increment)
			$dateStr = date($defaultDateFormat);
			$name = $this->untitledPageName() . '-0' . $dateStr;

		} else if(strpos($format, '}')) {
			// string with {field_name} to text
			$name = $page->getText($format, true, false);

		} else if(strpos($format, '|')) {
			// field names separated by "|" until one matches
			$name = $page->getUnformatted($format);
			$formatType = 'field'; // Page::hasField() accepts pipes

		} else if(strpos($format, 'date:') === 0) {
			// specified date format
			list(, $format) = explode('date:', $format);
			if(empty($format)) $format = 'Y-m-d H:i:s';
			$name = wireDate(trim($format));
			$formatType = 'date';

		} else if(strpos($format, ' ') !== false || strpos($format, '/') !== false || strpos($format, ':') !== false) {
			// date assumed when spaces, slashes or colon present in format
			$name = wireDate($format);
			$formatType = 'date';

		} else if($sanitizer->fieldName($format) === $format) {
			// single field name or predefined string
			// this can also return null, which falls back to if() statement below
			$name = (string) $page->getUnformatted($format);
			$formatType = 'field';
		}

		if(!strlen($name)) {
			// requested format did not produce a page name, so now fall-back to something else.
			// we either have a field name or some predefined string that is not a field name.
			
			if($formatType === 'field' && $page->hasField($format)) {
				// format involves a field name that is valid for the page
				
				// restore previous language if we had set one
				if($language) $languages->unsetLanguage();

				// if requested in some other language, see if we can get it in default language
				if($language && !$language->isDefault()) {
					$name = $this->pageNameFromFormat($page, $format, array('language' => $languages->getDefault())); 
				}

				// fallback to untitled format if fields required are not present
				if(!strlen($name)) {
					$name = $this->pageNameFromFormat($page, 'untitled'); // no options intended
				}

				// return now to bypass everything that follows since we went recursive
				return $name; 
				
			} else if($formatType === 'date' && $format !== $defaultDateFormat) {
				// if given date format did not resolve to anything, try in our default date format 
				$name = $this->pageNameFromFormat($page, $defaultDateFormat);
				
			} else {
				$name = $format;
			}
		}

		if(strlen($name) > $this->nameMaxLength) $name = $this->adjustNameLength($name);
		
		$utf8 = $this->wire()->config->pageNameCharset === 'UTF8';
		$name = $utf8 ? $sanitizer->pageNameUTF8($name) : $sanitizer->pageName($name, Sanitizer::translate);
		
		if($language) $languages->unsetLanguage();

		return $name;
	}

	/**
	 * Get a unique page name
	 *
	 * 1. If given no arguments, it returns a random globally unique page name.
	 * 2. If given just a $name, it returns that name (if globally unique), or an incremented version of it that is globally unique.
	 * 3. If given both $page and $name, it returns given name if unique in parent, or incremented version that is.
	 * 4. If given just a $page, the name is pulled from $page and behavior is the same as #3 above.
	 *
	 * The returned value is not yet assigned to the given $page, so if it is something different than what
	 * is already on $page, you’ll want to assign it manually after this.
	 * 
	 * #pw-group-generators
	 *
	 * @param string|Page|array $name Name to make unique
	 *  You may optionally substitute the $page argument or $options arguments here, if that is all you need.
	 * @param Page|string|null|array Page to exclude from duplicate check and/or to pull $name or parent from (if not otherwise specified). 
	 *  Note that specifying a Page is important if the page already exists, as it is used as the page to exclude when checking for 
	 *  name collisions, and we want to exclude $page from that check. You may optionally substitute the $options or $name arguments
	 *  here, if that is all you need. If $parent or $name are specified separately from this $page argument, they will override
	 *  whatever parent or name settings are on this $page argument. 
	 * @param array $options 
	 *  - `parent` (Page|null): Optionally specify a different parent if $page does not currently have the parent you want to use.
	 *  - `language` (Language|int): Get unique for this language (if multi-language page names active).
	 *  - `page` (Page|null): If you specified no $page argument, you can optionally bundle it in the $options array. 
	 *  - `name` (string): If you specified no $name argument, you can optionally bundle it in the $options array.
	 * @return string Returns unique name
	 *
	 */
	public function uniquePageName($name = '', $page = null, array $options = array()) {
		
		$defaults = array(
			'name' => '',
			'page' => null, 
			'parent' => null, 
			'language' => null 
		);

		// handle argument substitutions
		if(is_array($page)) {
			// options specified in $page argument
			$options = $page;
			$page = !empty($options['page']) ? $options['page'] : null;
		} else if(is_array($name)) {
			// options specified in $name argument
			$options = $name;
			$name = !empty($options['name']) ? $options['name'] : '';
		} else if($name instanceof Page) {
			// $page argument specified in $name argument
			$_name = is_string($page) ? $page : '';
			$page = $name;
			$name = $_name;
		}
		
		$options = array_merge($defaults, $options);
		
		if(empty($page) && !empty($options['page'])) $page = $options['page'];
		if(empty($name) && !empty($options['name'])) $name = $options['name'];

		if($page) {
			if($options['parent'] === null) $options['parent'] = $page->parent();
			if(!strlen($name)) {
				if($options['language']) {
					$name = $page->get("name$options[language]");
					if(!strlen($name)) $name = $page->name;
				} else {
					$name = $page->name;
				}
			}
			$options['page'] = $page;
		}

		$fallbackFormat = '';
		
		if(!strlen($name)) {
			// no name currently present, so we need to determine what kind of name it should have
			if($page) {
				$fallbackFormat = $page->id ? 'random' : 'untitled-time';
				$format = $this->defaultPageNameFormat($page, array(
					'fallbackFormat' => $fallbackFormat,
					'parent' => $options['parent']
				));
				$name = $this->pageNameFromFormat($page, $format, array('language' => $options['language'])); 
			} else {
				$name = $this->uniqueRandomPageName([ 'confirm' => false ]);
				$fallbackFormat = 'random';
			}
		}
		
		if(strlen($name) > $this->nameMaxLength) $name = $this->adjustNameLength($name);

		$n = 0;
		while($this->pageNameExists($name, $options)) {
			if($fallbackFormat != 'random' && strrpos($name, '-')) {
				list(,$suffix) = explode('-', $name, 2);
				if(strlen($suffix) >= 12 && ctype_digit($suffix)) {
					$fallbackFormat = 'random'; // avoid untitled-time more than once
				}
			}
			if(++$n > 5 || $fallbackFormat === 'random') {
				$name = $this->uniqueRandomPageName([ 'confirm' => false ]);
			} else {
				$name = $this->incrementName($name);
			}
			if(strlen($name) > $this->nameMaxLength) $name = $this->adjustNameLength($name);
		}
		
		return $name;
	}

	/**
	 * If name exceeds maxLength, truncate it, while keeping any numbered suffixes in place
	 * 
	 * #pw-group-manipulators
	 * 
	 * @param string $name
	 * @param int $maxLength
	 * @return string
	 * 
	 */
	public function adjustNameLength($name, $maxLength = 0) {

		if($maxLength < 1) $maxLength = $this->nameMaxLength;
		if(strlen($name) <= $maxLength) return $name;

		$trims = implode('', $this->delimiters);
		$pos = 0;

		list($namePrefix, $numberSuffix) = $this->nameAndNumber($name);

		if($namePrefix !== $name) {
			$numberSuffix = $this->delimiter . $numberSuffix;
			$maxLength -= strlen($numberSuffix);
		} else {
			$numberSuffix = '';	
		}
	
		if(strlen($namePrefix) > $maxLength) {
			$namePrefix = substr($namePrefix, 0, $maxLength);
		}
	
		// find word delimiter closest to end of string
		foreach($this->delimiters as $c) {
			$p = strrpos($namePrefix, $c);
			if((int) $p > $pos) $pos = $p;
		}
		
		// use word delimiter pos as maxLength when it’s relatively close to the end
		if(!$pos || $pos < (strlen($namePrefix) / 1.3)) $pos = $maxLength;
		
		$name = substr($namePrefix, 0, $pos);
		$name = rtrim($name, $trims);

		// append number suffix if there was one
		if($numberSuffix) $name .= $numberSuffix;
		
		return $name;
	}

	/**
	 * Increment the suffix of a page name, or add one if not present
	 * 
	 * #pw-group-manipulators
	 * 
	 * @param string $name
	 * @param int|null $num Number to use, or omit to determine and increment automatically
	 * @return string
	 * 
	 */
	public function incrementName($name, $num = null) {
		
		list($namePrefix, $n) = $this->nameAndNumber($name); 
		
		if($namePrefix !== $name) {
			// name already had an increment
			if($num) {
				// specific number was supplied
				$num = (int) $num;
				$name = $namePrefix . $this->delimiter . $num;
			} else {
				// no number supplied 
				// make sure that any leading zeros are retained before we increment number
				$zeros = '';
				while(strpos($name, $namePrefix . $this->delimiter . "0$zeros") === 0) $zeros .= '0';
				// increment the number
				$name = $namePrefix . $this->delimiter . $zeros . (++$n);
			}
		} else {
			// name does not yet have an increment, so make it "name-1"
			if(!is_int($num) || $num < 1) $num = 1; 
			$name = $namePrefix . $this->delimiter . $num;
		}
		
		return $this->adjustNameLength($name);
	}

	/**
	 * Is the given name is use by a page?
	 * 
	 * If the `multilang` option is used, it checks if the page name exists in any language. 
	 * IF the `language` option is used, it only checks that particular language (regardless of `multilang` option).
	 * 
	 * #pw-group-informational
	 *
	 * @param string $name
	 * @param array $options
	 *  - `page` (Page|int): Ignore this Page or page ID
	 *  - `parent` (Page|int): Limit search to only this parent.
	 *  - `multilang` (bool): Check other languages if multi-language page names supported? (default=false)
	 *  - `language` (Language|int): Limit check to only this language (default=null)
	 * @return int Returns quantity of pages using name, or 0 if name not in use.
	 *
	 */
	public function pageNameExists($name, array $options = array()) {

		$defaults = array(
			'page' => null,
			'parent' => null,
			'language' => null,
			'multilang' => false,
		);

		$options = array_merge($defaults, $options);
		$languages = $options['multilang'] || $options['language'] ? $this->wire()->languages : null;
		if($languages && !$languages->hasPageNames()) $languages = null;
		
		if($this->wire()->config->pageNameCharset == 'UTF8') {
			$name = $this->wire()->sanitizer->pageName($name, Sanitizer::toAscii);
		}

		$wheres = array();
		$binds = array();
		$parentID = $options['parent'] === null ? null : (int) "$options[parent]";
		$pageID = $options['page'] === null ? null : (int) "$options[page]";

		if($languages) {
			foreach($languages as $language) {
				if($options['language'] && "$options[language]" !== "$language") continue;
				$property = $language->isDefault() ? "name" : "name" . (int) $language->id;
				$wheres[] = "$property=:$property";
				$binds[":$property"] = $name;
			}
			$wheres = array('(' . implode(' OR ', $wheres) . ')');
		} else {
			$wheres[] = 'name=:name';
			$binds[':name'] = $name;
		}

		if($parentID) {
			// xxx
			$wheres[] = 'parent_id=:parent_id';
			$binds[':parent_id'] = $parentID; 
		}
		
		if($pageID) {
			$wheres[] = 'id!=:id';
			$binds[':id'] = $pageID;
		}

		$sql = 'SELECT COUNT(*) FROM pages WHERE ' . implode(' AND ', $wheres);
		$query = $this->wire()->database->prepare($sql);

		foreach($binds as $key => $value) {
			$query->bindValue($key, $value);
		}

		$query->execute();
		$qty = (int) $query->fetchColumn();
		$query->closeCursor();

		return $qty;
	}

	/**
	 * Get a random, globally unique page name
	 * 
	 * #pw-group-generators
	 *
	 * @param array $options
	 *  - `page` (Page): If name is or should be assigned to a Page, specify it here. (default=null)
	 *  - `length` (int): Required/fixed length, or omit for random length (default=0).
	 *  - `min` (int): Minimum required length, if fixed length not specified (default=6).
	 *  - `max` (int): Maximum allowed length, if fixed length not specified (default=min*2).
	 *  - `alpha` (bool): Include alpha a-z letters? (default=true)
	 *  - `numeric` (bool): Include numeric digits 0-9? (default=true)
	 *  - `confirm` (bool): Confirm that name is globally unique? (default=true)
	 *  - `parent` (Page|int): If specified, name must only be unique for this parent Page or ID (default=0).
	 *  - `prefix` (string): Prepend this prefix to page name (default='').
	 *  - `suffix` (string): Append this suffix to page name (default='').
	 *
	 * @return string
	 *
	 */
	public function uniqueRandomPageName($options = array()) {

		$defaults = array(
			'page' => null,
			'length' => 0,
			'min' => 6,
			'max' => 0,
			'alpha' => true,
			'numeric' => true,
			'confirm' => true,
			'parent' => 0,
			'prefix' => '',
			'suffix' => '',
		);

		if(is_int($options)) $options = array('length' => $options);
		$options = array_merge($defaults, $options);
		$rand = new WireRandom();
		$this->wire($rand);

		do {
			if($options['length'] < 1) {
				if($options['min'] < 1) $options['min'] = 6;
				if($options['max'] < $options['min']) $options['max'] = $options['min'] * 2;
				if($options['min'] == $options['max']) {
					$length = $options['max'];
				} else {
					$length = mt_rand($options['min'], $options['max']);
				}
			} else {
				$length = (int) $options['length'];
			}

			if($options['alpha'] && $options['numeric']) {
				$name = $rand->alphanumeric($length, array('upper' => false, 'noStart' => '0123456789'));
			} else if($options['numeric']) {
				$name = $rand->numeric($length);
			} else {
				$name = $rand->alpha($length);
			}

			$name = $options['prefix'] . $name . $options['suffix'];

			if($options['confirm']) {
				$qty = $this->pageNameExists($name, array('page' => $options['page']));
			} else {
				$qty = 0;
			}

		} while($qty);

		if($options['page'] instanceof Page) $options['page']->set('name', $name);

		return $name;
	}

	/**
	 * Return the untitled page name string
	 * 
	 * #pw-group-informational
	 * 
	 * @return string
	 * 
	 */
	public function untitledPageName() {
		return $this->untitledPageName;
	}
	
	/**
	 * Does given page have a name that has a conflict/collision?
	 * 
	 * In multi-language environment this applies to default language only. 
	 * 
	 * #pw-group-informational
	 * 
	 * @param Page $page Page to check
	 * @return string|bool Returns string with conflict reason or boolean false if no conflict
	 * @throws WireException If given invalid $options argument
	 * @since 3.0.127
	 * 
	 */
	public function pageNameHasConflict(Page $page) {
		
		$config = $this->wire()->config;
		$usersPageIDs = $config->usersPageIDs;
		$checkUser = in_array($page->parent_id, $usersPageIDs); 
		$reason = '';
		$name = $page->name;
	
		if($config->pageNameCharset == 'UTF8') {
			$name = $this->wire()->sanitizer->pageName($name, Sanitizer::toAscii);
		}
	
		// xxx
		$sql = "SELECT id, status, parent_id FROM pages WHERE name=:name AND id!=:id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':name', $name);
		$query->bindValue(':id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		
		if(!$query->rowCount()) {
			$query->closeCursor();
			return false;
		}
		
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			
			$parentID = (int) $row['parent_id']; 
			$status = (int) $row['status']; 
			
			if($status & Page::statusUnique) {
				// name is already required to be unique globally
				$reason = sprintf($this->_("Another page is using name “%s” and requires it to be globally unique"), $page->name);
				break;
			}
			
			if($checkUser && in_array($parentID, $usersPageIDs)) {
				// username collision
				$reason = sprintf($this->_('Another user is already using the name “%s”'), $page->name);
				break;
			}
			
			if($parentID === $page->parent_id) {
				// name already consumed by another page with same parent
				$reason = sprintf($this->_('Another page with same parent is already using name “%s”'), $page->name);
				break;
			} 
		}
		
		// page requires that it be the only one with this name, so if others have it, then disallow
		if(!$reason && $page->hasStatus(Page::statusUnique)) {
			$reason = sprintf($this->_('Cannot use name “%s” as globally unique because it is already used by other page(s)'), $page->name);
		}
		
		$query->closeCursor();
		
		return $reason ? $reason : false;
	}

	/**
	 * Check given page’s name for conflicts and increment as needed while also triggering a warning notice
	 * 
	 * #pw-group-manipulators
	 * 
	 * @param Page $page
	 * @since 3.0.127
	 * 
	 */
	public function checkNameConflicts(Page $page) {
		
		$checkName = false;
		$checkStatus = false;
		$namePrevious = $page->namePrevious;
		$statusPrevious = $page->statusPrevious;
		$isNew = $page->isNew();
		$nameChanged = !$isNew && $namePrevious !== null && $namePrevious !== $page->name;

		if($isNew || $nameChanged) {
			// new page or changed name
			$checkName = true;
		} else if($statusPrevious !== null && $page->hasStatus(Page::statusUnique) && !($statusPrevious & Page::statusUnique)) {
			// page just received 'unique' status
			$checkStatus = true;
		}
		
		if(!$checkName && !$checkStatus) return;
	
		do {
			
			$conflict = $this->pageNameHasConflict($page);
			if(!$conflict) break;
			
			$this->warning($conflict);
			
			if($checkName) {
				if($nameChanged) {
					// restore previous name
					$page->name = $page->namePrevious;
					$nameChanged = false;
				} else {
					// increment name
					$page->name = $this->incrementName($page->name);
				}
				
			} else if($checkStatus) {
				// remove 'unique' status
				$page->removeStatus(Page::statusUnique);
				break;
			}
			
		} while($conflict);
	}
	
}
