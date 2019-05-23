<?php namespace ProcessWire;

/**
 * ProcessWire Pagefile
 *
 * #pw-summary Represents a single file item attached to a page, typically via a File Fieldtype.
 * #pw-summary-traversal For the most part you’ll want to traverse from the parent `Pagefiles` object than these methods.
 * #pw-summary-manipulation Remember to follow up any manipulations with a `$pages->save()` call. 
 * #pw-summary-tags Be sure to see the `Pagefiles::getTag()` and `Pagesfiles::findTag()` methods, which enable you retrieve files by tag.
 * #pw-use-constructor
 * #pw-body =
 * Pagefile objects are contained by a `Pagefiles` object. 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 *
 * @property-read string $url URL to the file on the server.
 * @property-read string $httpUrl URL to the file on the server including scheme and hostname.
 * @property-read string $URL Same as $url property but with browser cache busting query string appended. #pw-group-other
 * @property-read string $HTTPURL Same as the cache-busting uppercase “URL” property, but includes scheme and hostname. #pw-group-other
 * @property-read string $filename full disk path to the file on the server. 
 * @property-read string $name Returns the filename without the path, same as the "basename" property.
 * @property-read string $hash Get a unique hash (for the page) representing this Pagefile.
 * @property-read array $tagsArray Get file tags as an array. #pw-group-tags @since 3.0.17
 * @property int $sort Sort order in database. #pw-group-other
 * @property string $basename Returns the filename without the path.
 * @property string $description Value of the file’s description field (string), if enabled. Note you can also set this property directly.
 * @property string $tags Value of the file’s tags field (string), if enabled. #pw-group-tags
 * @property string $ext File’s extension (i.e. last 3 or so characters) 
 * @property-read int $filesize File size (number of bytes).
 * @property int $modified Unix timestamp of when Pagefile (file, description or tags) was last modified. #pw-group-date-time
 * @property-read string $modifiedStr Readable date/time string of when Pagefile was last modified. #pw-group-date-time
 * @property-read int $mtime Unix timestamp of when file (only) was last modified. #pw-group-date-time
 * @property-read string $mtimeStr Readable date/time string when file (only) was last modified. #pw-group-date-time
 * @property int $created Unix timestamp of when file was created. #pw-group-date-time
 * @property-read string $createdStr Readable date/time string of when Pagefile was created #pw-group-date-time
 * @property string $filesizeStr File size as a formatted string, i.e. “123 Kb”. 
 * @property Pagefiles $pagefiles The Pagefiles WireArray that contains this file. #pw-group-other
 * @property Page $page The Page object that this file is part of. #pw-group-other
 * @property Field $field The Field object that this file is part of. #pw-group-other
 * @property array $filedata
 * 
 * @method void install($filename)
 * @method string httpUrl()
 * @method string noCacheURL($http = false)
 * 
 */

class Pagefile extends WireData {

	/**
	 * Timestamp 'created' used by pagefiles that are temporary, not yet published
	 * 
	 */
	const createdTemp = 10; 

	/**
	 * Reference to the owning collection of Pagefiles
	 * 
	 * @var Pagefiles
	 *
	 */
	protected $pagefiles;

	/**
	 * @var PagefileExtra[]
	 * 
	 */
	protected $extras = array(); 

	/**
	 * Extra file data
	 * 
	 * @var array
	 * 
	 */
	protected $filedata = array();

	/**
	 * Construct a new Pagefile
	 * 
	 * ~~~~~
	 * // Construct a new Pagefile, assumes that $page->files is a FieldtypeFile Field
	 * $pagefile = new Pagefile($page->files, '/path/to/file.pdf'); 
	 * ~~~~~
	 *
	 * @param Pagefiles $pagefiles The Pagefiles WireArray that will contain this file. 
	 * @param string $filename Full path and filename to this Pagefile.
	 *
	 */
	public function __construct(Pagefiles $pagefiles, $filename) {

		$this->pagefiles = $pagefiles; 
		if(strlen($filename)) $this->setFilename($filename); 
		$this->set('description', ''); 
		$this->set('tags', ''); 
		$this->set('formatted', false); // has an output formatter been run on this Pagefile?
		$this->set('modified', 0); 
		$this->set('created', 0); 
	}


	/**
	 * Set the filename associated with this Pagefile.
	 *
	 * No need to call this as it's already called from the constructor. 
	 * This exists so that Pagefile/Pageimage descendents can create cloned variations, if applicable. 
	 * 
	 * #pw-internal
	 *
	 * @param string $filename
	 *
	 */
	public function setFilename($filename) {

		$basename = basename($filename); 

		if(DIRECTORY_SEPARATOR != '/') $filename = str_replace('\\' . $basename, '/' . $basename, $filename); // To correct issue with XAMPP in Windows
	
		if($basename != $filename && strpos($filename, $this->pagefiles->path()) !== 0) {
			$this->install($filename); 
		} else {
			$this->set('basename', $basename); 
		}

	}

	/**
	 * Install this Pagefile
	 *
	 * Implies copying the file to the correct location (if not already there), and populating its name.
	 * The given $filename may be local (path) or external (URL). 
	 * 
	 * #pw-hooker
	 *
	 * @param string $filename Full path and filename of file to install, or http/https URL to pull file from.
	 * @throws WireException
	 *
	 */
	protected function ___install($filename) {
	
		$basename = $filename;
		
		if(strpos($basename, '?') !== false) {
			list($basename, $queryString) = explode('?', $basename); 	
			if($queryString) {} // do not use in basename
		} 
	
		if(empty($basename)) throw new WireException("Empty filename");

		$basename = $this->pagefiles->cleanBasename($basename, true, false, true); 
		$pathInfo = pathinfo($basename); 
		$basename = basename($basename, ".$pathInfo[extension]"); 

		$basenameNoExt = $basename; 
		$basename .= ".$pathInfo[extension]"; 

		// ensure filename is unique
		$cnt = 0; 
		while(file_exists($this->pagefiles->path() . $basename)) {
			$cnt++;
			$basename = "$basenameNoExt-$cnt.$pathInfo[extension]";
		}
		
		$destination = $this->pagefiles->path() . $basename; 
		
		if(strpos($filename, '://') === false) {
			if(!is_readable($filename)) throw new WireException("Unable to read: $filename");
			if(!copy($filename, $destination)) throw new WireException("Unable to copy: $filename => $destination");
		} else {
			$http = $this->wire(new WireHttp());
			// note: download() method throws excepton on failure
			$http->download($filename, $destination);
			// download was successful
		}
		
		$this->wire('files')->chmod($destination);
		$this->changed('file');
		parent::set('basename', $basename);
	}

	/**
	 * Sets a value in this Pagefile
	 *
	 * Externally, this would be used to set the file’s basename or description
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return Pagefile|WireData
	 *
	 */
	public function set($key, $value) {
		
		if($key == 'basename') {
			$value = $this->pagefiles->cleanBasename($value, false);
		} else if($key == 'description') {
			return $this->setDescription($value);
		} else if($key == 'modified') {
			$value = ctype_digit("$value") ? (int) $value : strtotime($value);
		} else if($key == 'created') {
			$value = ctype_digit("$value") ? (int) $value : strtotime($value);
		} else if($key == 'tags') {
			$this->tags($value);
			return $this;
		} else if($key == 'filedata') {
			if(is_array($value)) $this->filedata($value);
			return $this;	
		}
		
		if(strpos($key, 'description') === 0 && preg_match('/^description(\d+)$/', $value, $matches)) {
			// check if a language description is being set manually by description123 where 123 is language ID
			$languages = $this->wire('languages'); 
			if($languages) {
				$language = $languages->get((int) $matches[1]); 
				if($language && $language->id) return $this->setDescription($value, $language); 
			}
		}

		return parent::set($key, $value); 
	}

	/**
	 * Get or set filedata
	 * 
	 * Filedata is any additional data that you want to store with the file’s database record. 
	 *
	 *  
	 * - To get a value, specify just the $key argument. Null is returned if request value is not present. 
	 * - To get all values, omit all arguments. An associative array will be returned. 
	 * - To set a value, specify the $key and the $value to set. 
	 * - To set all values at once, specify an associative array for the $key argument. 
	 * - To unset, specify boolean false (or null) for $key, and the name of the property to unset as $value. 
	 * - To unset, you can also get all values, unset it from the retuned array, and set the array back. 
	 * 
	 * #pw-internal
	 * 
	 * @param string|array|false|null $key Specify array to set all file data, or key (string) to set or get a property,
	 *  Or specify boolean false to remove key specified by $value argument.
	 * @param null|string|array|int|float $value Specify a value to set for given property
	 * @return Pagefile|Pageimage|array|string|int|float|bool|null
	 * 
	 */
	public function filedata($key = '', $value = null) {
		$filedata = $this->filedata;
		$changed = false;
		if($key === false || $key === null) {
			// unset property named in $value
			if(!empty($value) && isset($filedata[$value])) {
				unset($this->filedata[$value]);
				$changed = true; 
			}
		} else if(empty($key)) {
			// return all
			return $filedata;
		} else if(is_array($key)) {
			// set all
			if($key != $filedata) {
				$this->filedata = $key;
				$changed = true; 
			}
		} else if($value === null) {
			// return value for key
			return isset($this->filedata[$key]) ? $this->filedata[$key] : null;
		} else {
			// set value for key
			if(!isset($filedata[$key]) || $filedata[$key] != $value) {
				$this->filedata[$key] = $value;
				$changed = true;
			}
		}
		if($changed) {
			$this->trackChange('filedata', $filedata, $this->filedata);
			if($this->page && $this->field) $this->page->trackChange($this->field->name);
		}
		return $this;
	}

	/**
	 * Set a description, optionally parsing JSON language-specific descriptions to separate properties
	 *
	 * @param string|array $value
	 * @param Page|Language Langage to set it for. Omit to determine automatically. 
	 * @return $this
	 *
	 */
	protected function setDescription($value, Page $language = null) {
		
		/** @var Languages $languages */
		$languages = $this->wire('languages');
		
		/** @var Language|null $language */
		
		$field = $this->field; 
		$noLang = $field && $field->get('noLang'); // noLang setting to disable multi-language from InputfieldFile

		if(!is_null($language) && $language->id) {
			$name = "description";
			if(!$language->isDefault() && !$noLang) {
				$name .= $language->id;
			}
			parent::set($name, $value); 
			if($name != 'description' && $this->isChanged($name)) $this->trackChange('description');
			return $this; 
		}

		if(is_array($value)) {
			$values = $value;
		} else {
			// check if it contains JSON?
			$first = substr($value, 0, 1);
			$last = substr($value, -1);
			if(($first == '{' && $last == '}') || ($first == '[' && $last == ']')) {
				$values = json_decode($value, true);
			} else {
				$values = array();
			}
		}
		
		$numChanges = 0;

		if($values && count($values)) {
			$n = 0; 
			foreach($values as $id => $v) {	
				// first item is always default language. this ensures description will still
				// work even if language support is later uninstalled. 
				$name = 'description';
				if($noLang && $n > 0) break;
				$n++; 
				if(ctype_digit("$id")) {
					$id = (int) $id;
					if(!$id) $id = '';
					$name = $n > 0 ? "description$id" : "description";
				} else if($id === 'default') {
					$name = 'description';
				} else if($languages) {
					$language = $languages->get($id); // i.e. "default" or "es"
					if(!$language->id) continue;
					$name = $language->isDefault() ? "description" : "description$language->id";
				}
				parent::set($name, $v);
				if($this->isChanged($name)) $numChanges++;
			}
		} else {
			// no JSON values so assume regular language description
			$languages = $this->wire('languages');
			$language = $languages ? $this->wire('user')->language : null; 

			if($languages && $language && !$noLang && !$language->isDefault()) {
				$name = "description$language->id";
			} else {
				$name = "description";
			}
			parent::set($name, $value);
			if($this->isChanged($name)) $numChanges++;
		}
		
		if($numChanges && !$this->isChanged('description')) $this->trackChange('description');

		return $this;
	}

	/**
	 * Get or set the file’s description (with multi-language support). 
	 * 
	 * When not in a multi-language environment, you can still use this method but we recommend using the simpler method of just
	 * getting/seting the `Pagefile::$description` property directly instead. 
	 * 
	 * ~~~~~
	 * // Get a Pagefile to work with
	 * $pagefile = $page->files->first();
	 * 
	 * // Setting description
	 * $pagefile->description('en', 'Setting English description');
	 * $pagefile->description('de', 'Setting German description');
	 * 
	 * // Getting description for current language (whatever it happens to be)
	 * echo $pagefile->description();
	 * 
	 * // Getting description for language "de"
	 * echo $pagefile->description('de');
	 * ~~~~~
	 * 
	 * #pw-group-common
	 * #pw-group-manipulation
	 * 
	 * @param null|bool|Language|array
	 * - To GET in current user language: Omit arguments or specify null.
	 * - To GET in another language: Specify a Language name, id or object.
	 * - To GET in all languages as a JSON string: Specify boolean true (if LanguageSupport not installed, regular string returned).
	 * - To GET in all languages as an array indexed by language name: Specify boolean true for both arguments.
	 * - To SET for a language: Specify a language name, id or object, plus the $value as the 2nd argument.
	 * - To SET in all languages as a JSON string: Specify boolean true, plus the JSON string $value as the 2nd argument (internal use only).
	 * - To SET in all languages as an array: Specify the array here, indexed by language ID or name, and omit 2nd argument. 
	 * @param null|string $value Specify only when you are setting (single language) rather than getting a value.
	 * @return string|array
	 *
	 */
	public function description($language = null, $value = null) {
		
		if($language === true && $value === true) {
			// return all in array indexed by language name
			/** @var Languages $languages */
			$languages = $this->wire('languages');
			if(!$languages) return array('default' => parent::get('description'));
			$value = array();
			foreach($languages as $language) {
				$value[$language->name] = (string) parent::get("description" . ($language->isDefault() ? '' : $language->id));
			}
			return $value;	
		}

		if(!is_null($value)) {
			// set description mode
			if($language === true) {
				// set all language descriptions
				$this->setDescription($value); 
			} else {
				// set specific language description
				$this->setDescription($value, $language); 
			}
			return $value; 
		}
		
		if(is_array($language)) {
			// set all from array, then return description in current language
			$this->setDescription($language); 	
			$language = null;
			$value = null;
		}

		if((is_string($language) || is_int($language)) && $this->wire('languages')) {
			// convert named or ID'd languages to Language object
			$language = $this->wire('languages')->get($language); 
		}

		if(is_null($language)) {	
			// return description for current user language, or inherit from default if not available
			$user = $this->wire('user'); 
			$value = null;
			if($user->language && $user->language->id) $value = parent::get("description{$user->language}"); 
			if(empty($value)) {
				// inherit default language value
				$value = parent::get("description"); 
			}

		} else if($language === true) {
			// return JSON string of all languages if applicable
			$languages = $this->wire('languages'); 
			if($languages && $languages->count() > 1) {
				$values = array(0 => parent::get("description"));
				foreach($languages as $lang) {
					if($lang->isDefault()) continue; 
					$v = parent::get("description$lang"); 
					if(empty($v)) continue; 
					$values[$lang->id] = $v; 
				}
				$flags = defined("JSON_UNESCAPED_UNICODE") ? JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES : 0; // more fulltext friendly
				$value = json_encode($values, $flags); 
				
			} else {
				// no languages present so just return string with description
				$value = parent::get("description"); 
			}
			
		} else if(is_object($language) && $language->id) {
			// return description for specific language or blank if not available
			if($language->isDefault()) $value = parent::get("description"); 
				else $value = parent::get("description$language"); 
		}

		// we only return strings, so return blank rather than null
		if(is_null($value)) $value = '';

		return $value; 
	}

	/**
	 * Get a value from this Pagefile
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return mixed Returns null if value does not exist
	 *
	 */
	public function get($key) {
		$value = null; 

		if($key == 'name') $key = 'basename';
		if($key == 'pathname') $key = 'filename';

		switch($key) {
			case 'url':
			case 'httpUrl':
			case 'filename':
			case 'description':
			case 'tags':
			case 'ext':
			case 'hash': 
			case 'filesize':
			case 'filesizeStr':
				// 'basename' property intentionally excluded 
				$value = $this->$key();
				break;
			case 'tagsArray': 
				$value = $this->tags(true);
				break;
			case 'URL':
				// nocache url
				$value = $this->noCacheURL();
				break;
			case 'HTTPURL':
				$value = $this->noCacheURL(true);
				break;
			case 'pagefiles': 
				$value = $this->pagefiles; 
				break;
			case 'page': 
				$value = $this->pagefiles->getPage(); 
				break;
			case 'field': 
				$value = $this->pagefiles->getField(); 
				break;
			case 'modified':
			case 'created':
				$value = parent::get($key); 
				if(empty($value)) {
					$value = filemtime($this->filename()); 
					parent::set($key, $value); 
				}
				break;
			case 'modifiedStr':
			case 'createdStr':
				$value = parent::get(str_replace('Str', '', $key));
				$value = wireDate($this->wire('config')->dateFormat, $value);
				break;
			case 'fileData':
			case 'filedata':
				$value = $this->filedata();
				break;
			case 'mtime':
			case 'mtimeStr':
			case 'filemtime':	
			case 'filemtimeStr':	
				$value = filemtime($this->filename()); 
				if(strpos($key, 'Str')) $value = wireDate($this->wire('config')->dateFormat, $value);
				break;
		}
		if(is_null($value)) return parent::get($key); 
		return $value; 
	}

	/**
	 * Hookable no-cache URL
	 * 
	 * #pw-internal
	 * 
	 * @param bool $http Include scheme and hostname?
	 * @return string
	 * 
	 */
	public function ___noCacheURL($http = false) {
		return ($http ? $this->httpUrl() : $this->url()) . '?nc=' . @filemtime($this->filename());
	}

	/**
	 * Return the next sibling Pagefile in the parent Pagefiles, or NULL if at the end.
	 * 
	 * #pw-group-traversal
	 *
	 * @return Pagefile|Wire|null
	 *	
	 */
	public function getNext() {
		return $this->pagefiles->getNext($this); 
	}

	/**
	 * Return the previous sibling Pagefile in the parent Pagefiles, or NULL if at the beginning.
	 * 
	 * #pw-group-traversal
	 *
	 * @return Pagefile|Wire|null
	 *	
	 */
	public function getPrev() {
		return $this->pagefiles->getPrev($this); 
	}

	/**
	 * Return the web accessible URL to this Pagefile.
	 * 
	 * ~~~~~
	 * // Example of using the url method/property
	 * foreach($page->files as $file) {
	 *   echo "<li><a href='$file->url'>$file->description</a></li>";
	 * }
	 * ~~~~~
	 * 
	 * #pw-hooks
	 * #pw-common
	 * 
	 * @return string
	 * @see Pagefile:httpUrl()
	 *
	 */
	public function url() {
		return $this->wire('hooks')->isHooked('Pagefile::url()') ? $this->__call('url', array()) : $this->___url();
	}
	
	/**
	 * Hookable version of url() method
	 * 
	 * @return string
	 *
	 */
	protected function ___url() {
		return $this->pagefiles->url . $this->basename;
	}
	
	/**
	 * Return the web accessible URL (with scheme and hostname) to this Pagefile.
	 * 
	 * @return string
	 * @see Pagefile::url()
	 *
	 */
	public function ___httpUrl() {
		$page = $this->pagefiles->getPage();
		$url = substr($page->httpUrl(), 0, -1 * strlen($page->url())); 
		return $url . $this->url(); 
	}

	/**
	 * Returns the full disk path name filename to the Pagefile.
	 * 
	 * #pw-hooks
	 * #pw-common
	 * 
	 * @return string
	 *
	 */
	public function filename() {
		return $this->wire('hooks')->isHooked('Pagefile::filename()') ? $this->__call('filename', array()) : $this->___filename();
	}

	/**
	 * Hookable version of filename() method
	 *
	 */
	protected function ___filename() {
		return $this->pagefiles->path . $this->basename;
	}

	/**
	 * Returns the basename of this Pagefile (name and extension, without disk path). 
	 * 
	 * @param bool $ext Specify false to exclude the extension (default=true)
	 * @return string
	 *
	 */
	public function basename($ext = true) {
		$basename = parent::get('basename'); 
		if(!$ext) $basename = basename($basename, "." . $this->ext());
		return $basename;
	}

	/**
	 * Get or set the "tags" property, when in use. 
	 * 
	 * ~~~~~
	 * $file = $page->files->first();
	 * $tags = $file->tags(); // Get tags string
	 * $tags = $file->tags(true); // Get tags array
	 * $file->tags("foo bar baz"); // Set tags to be these 3 tags
	 * $tags->tags(["foo", "bar", "baz"]); // Same as above, using array
	 * ~~~~~
	 * 
	 * #pw-group-tags
	 * #pw-group-manipulation
	 *
	 * @param bool|string|array $value Specify one of the following:
	 *   - Omit to simply return the tags as a string. 
	 *   - Boolean true if you want to return tags as an array (rather than string). 
	 *   - Boolean false to return tags as an array, with lowercase enforced. 
	 *   - String or array if you are setting the tags.
	 * @return string|array Returns the current tags as a string or an array. 
	 *   When an array is returned, it is an associative array where the key and value are both the tag (keys are always lowercase).
	 * @see Pagefile::addTag(), Pagefile::hasTag(), Pagefile::removeTag()
	 *
	 */
	public function tags($value = null) {
		if(is_bool($value)) {
			// return array of tags
			$tags = parent::get('tags');
			$tags = str_replace(array(',', '|'), ' ', $tags);
			$_tags = explode(' ', $tags);
			$tags = array();
			foreach($_tags as $key => $tag) {
				$tag = trim($tag);
				if($value === false) $tag = strtolower($tag); // force lowercase
				if(!strlen($tag)) continue;
				$tags[strtolower($tag)] = $tag;
			}
		} else if($value !== null) {
			// set tags
			if(is_array($value)) $value = implode(' ', $value); // convert to string
			$value = $this->wire('sanitizer')->text($value);
			if(strpos($value, "\t") !== false) $value = str_replace("\t", " ", $value);
			// collapse extra whitespace
			while(strpos($value, "  ") !== false) $value = str_replace("  ", " ", $value);
			parent::set('tags', $value);	
			$tags = $value; 
		} else {
			// just get tags string
			$tags = parent::get('tags');
		}
		
		return $tags;
	}
	
	/**
	 * Does this file have the given tag(s)?
	 *
	 * ~~~~~
	 * $file = $page->files->first();
	 * 
	 * if($file->hasTag('foobar')) {
	 *   // file has the "foobar" tag
	 * }
	 *
	 * if($file->hasTag("foo|baz")) {
	 *   // file has either the foo OR baz tag
	 * }
	 * 
	 * if($file->hasTag("foo,baz")) {
	 *  // file has both the foo AND baz tags (since 3.0.17)
	 * }
	 * ~~~~~
	 *
	 * #pw-changelog 3.0.17 Added support for AND mode, where multiple tags can be specified and all must be present to return true.
	 * #pw-changelog 3.0.17 OR mode now returns found tag rather than boolean true.
	 * #pw-group-tags
	 *
	 * @param string $tag Specify one of the following:
	 *  - Single tag without whitespace.
	 *  - Multiple tags separated by a "|" to determine if Pagefile has at least one of the tags.
	 *  - Multiple tags separated by a comma to determine if Pagefile has all of the tags. (since 3.0.17)
	 * @return bool|string True if it has the given tag(s), false if not.
	 * - If multiple tags were specified separated by a "|", then if at least one was present, this method returns the found tag.
	 * - If multiple tags were specified separated by a space or comma, and all tags are present, it returns true. (since 3.0.17)
	 * @see Pagefile::tags(), Pagefile::addTag(), Pagefile::removeTag()
	 *
	 */
	public function hasTag($tag) {

		$tags = $this->tags(false); // all tags in array, lowercase
		if(empty($tags)) return false;
		$modeAND = null;
		$tag = trim(strtolower($tag));
		
		if(strpos($tag, '|') !== false) {
			$findTags = explode('|', $tag);
			$modeAND = false;
		} else if(strpos($tag, ',') !== false) {
			$findTags = explode(',', $tag);
			$modeAND = true;
		} else {
			$findTags = array($tag);
		}

		$numTags = 0;
		$numFound = 0;
		$tagFound = '';

		foreach($findTags as $tag) {
			$tag = trim($tag);
			if(!strlen($tag)) continue;
			$tag = str_replace(' ', '_', $tag);
			$numTags++;
			if(isset($tags[$tag])) {
				$numFound++;
				if($modeAND === false) {
					$tagFound = $tag;
					break;
				}
			}
		}

		if($modeAND === false) {
			// OR mode: must have at least one of given tags, and we return the found tag
			return $numFound > 0 ? $tagFound : false;

		} else if($modeAND === true) {
			// AND mode: must have all of the given tags
			return $numFound == $numTags;
		}

		// single tag
		return $numFound > 0;
	}

	/**
	 * Add the given tag to this file’s tags (if not already present)
	 * 
	 * ~~~~~
	 * $file = $page->files->first();
	 * $file->addTag('foo'); // add single tag 
	 * $file->addTag('foo,bar,baz'); // add multiple tags
	 * $file->addTag(['foo', 'bar', 'baz']); // same as above, using array
	 * ~~~~~
	 * 
	 * #pw-group-tags
	 * #pw-group-manipulation
	 * 
	 * @param string|array $tag Tag to add, or array of tags to add, or CSV string of tags to add.
	 * @return $this
	 * @since 3.0.17
	 * @see Pagefile::tags(), Pagefile::hasTag(), Pagefile::removeTag()
	 * 
	 */
	public function addTag($tag) {
		if(is_array($tag)) {
			$addTags = $tag;
		} else if(strpos($tag, ',') !== false) {
			$addTags = explode(',', $tag);
		} else {
			$addTags = array($tag);
		}
		$tags = $this->tags(true);	
		$numAdded = 0;
		foreach($addTags as $tag) {
			if($this->hasTag($tag)) continue; 
			$tag = $this->wire('sanitizer')->text(trim($tag));
			$tag = str_replace(' ', '_', $tag);
			$tags[strtolower($tag)] = $tag;
			$numAdded++;
		}
		if($numAdded) $this->tags($tags); 
		return $this;
	}

	/**
	 * Remove the given tag from this file’s tags (if present)
	 * 
	 * ~~~~~
	 * $file = $page->files->first();
	 * $file->removeTag('foo'); // remove single tag
	 * $file->removeTag('foo,bar,baz'); // remove multiple tags
	 * $file->removeTag(['foo', 'bar', 'baz']); // same as above, using array
	 * ~~~~~
	 * 
	 * #pw-group-tags
	 * #pw-group-manipulation
	 *
	 * @param string $tag Tag to remove, or array of tags to remove, or CSV string of tags to remove. 
	 * @return $this
	 * @since 3.0.17
	 * @see Pagefile::tags(), Pagefile::hasTag(), Pagefile::addTag()
	 *
	 */
	public function removeTag($tag) {
		$tags = $this->tags(true);
		if(!count($tags)) return $this; // no tags to remove
		if(is_array($tag)) {
			$removeTags = $tag;
		} else if(strpos($tag, ',') !== false) {
			$removeTags = explode(',', $tag); 
		} else {
			$removeTags = array($tag);
		}
		$numRemoved = 0;
		foreach($removeTags as $tag) {
			$tag = strtolower(trim($tag));
			$tag = str_replace(' ', '_', $tag);
			if(!isset($tags[$tag])) continue;
			unset($tags[strtolower($tag)]);
			$numRemoved++;
		}
		if($numRemoved) $this->tags($tags); 
		return $this; 		
	}

	/**
	 * Has the output already been formatted?
	 * 
	 * #pw-internal
	 *
	 */
	public function formatted() {
		return parent::get('formatted') ? true : false;
	}

	/**
	 * Returns the filesize in number of bytes.
	 *
	 * @return int
	 *
	 */
	public function filesize() {
		return @filesize($this->filename()); 
	}

	/**
	 * Returns the filesize in a formatted, output-ready string (i.e. "123 kB")
	 *
	 * @return string
	 *
	 */
	public function filesizeStr() {
		return wireBytesStr($this->filesize()); 
	}

	/**
	 * Returns the file’s extension - "pdf", "jpg", etc.
	 * 
	 * @return string
	 *
	 */
	public function ext() {
		return substr($this->basename(), strrpos($this->basename(), '.')+1);
	}

	/**
	 * When dereferenced as a string, a Pagefile returns its basename
	 * 
	 * @return string
	 *
	 */
	public function __toString() {
		return (string) $this->basename; 
	}

	/**
	 * Return a unique MD5 hash representing this Pagefile.
	 * 
	 * This hash can be counted on to be unique among all files on a given page, regardless of field. 
	 * 
	 * @return string
	 *
	 */
	public function hash() {
		$hash = parent::get('hash');
		if($hash) return $hash; 	
		$this->set('hash', md5($this->basename())); 
		return parent::get('hash'); 
	}

	/**
	 * Delete the physical file on disk, associated with this Pagefile
	 * 
	 * #pw-internal Public API should use removal methods from the parent Pagefiles. 
	 * 
	 * @return bool True on success, false on fail
	 *
	 */
	public function unlink() {
		/** @var WireFileTools $files */
		if(!strlen($this->basename) || !is_file($this->filename)) return true;
		$files = $this->wire('files');
		foreach($this->extras() as $extra) {
			$extra->unlink();
		}
		return $files->unlink($this->filename, true);
	}

	/**
	 * Rename this file 
	 * 
	 * Remember to follow this up with a `$page->save()` for the page that the file lives on. 
	 * 
	 * #pw-group-manipulation
	 * 
 	 * @param string $basename New name to use. Must be just the file basename (no path). 
	 * @return string|bool Returns new name (basename) on success, or boolean false if rename failed.
	 *
	 */
	public function rename($basename) {
		foreach($this->extras() as $extra) {
			$extra->filename(); // init
		}
		$basename = $this->pagefiles->cleanBasename($basename, true); 
		if($this->wire('files')->rename($this->filename, $this->pagefiles->path . $basename, true)) {
			$this->set('basename', $basename); 
			$basename = $this->basename();
			foreach($this->extras() as $extra) {
				$extra->rename();
			}
			return $basename;
		}
		return false; 
	}

	/**
	 * Copy this file to the new specified path
	 * 
	 * #pw-internal
	 *
	 * @param string $path Path (not including basename)
	 * @return bool result of copy() function
	 *
	 */
	public function copyToPath($path) {
		/** @var WireFileTools $files */
		$files = $this->wire('files');
		$result = $files->copy($this->filename(), $path);
		foreach($this->extras() as $extra) {
			if(!$extra->exists()) continue;
			$files->copy($extra->filename, $path);
		}
		return $result;
	}

	/**
	 * Hook called a property changes (from Wire)
	 *
	 * Alert the $pagefiles of the change 
	 * 
	 * #pw-internal
	 * 
	 * @param string $what
	 * @param mixed $old
	 * @param mixed $new
	 *
	 */
	public function ___changed($what, $old = null, $new = null) {
		if(in_array($what, array('description', 'tags', 'file'))) {
			$this->set('modified', time()); 
			$this->pagefiles->trackChange('item');
		}
		parent::___changed($what, $old, $new); 
	}

	/**
	 * Set the parent array container
	 * 
	 * #pw-internal
	 * 
	 * @param Pagefiles $pagefiles
	 * @return $this
	 *
	 */
	public function setPagefilesParent(Pagefiles $pagefiles) {
		$this->pagefiles = $pagefiles; 
		return $this;
	}

	/**
	 * Get or set the temporary status of the Pagefile
	 * 
	 * Returns true if this Pagefile is temporary, not yet published. Or use this to set the temp status. 
	 * 
	 * #pw-internal
	 * 
	 * @param bool $set Optionally set the temp status to true or false
	 * @return bool
	 * 
	 */
	public function isTemp($set = null) {
		return $this->pagefiles->isTemp($this, $set); 
	}

	/**
	 * Get all extras, add an extra, or get an extra
	 * 
	 * #pw-internal
	 * 
	 * @param string $name
	 * @param PagefileExtra $value
	 * @return PagefileExtra[]|PagefileExtra|null
	 * @since 3.0.132
	 * 
	 */
	public function extras($name = null, PagefileExtra $value = null) {
		if($name === null) return $this->extras;
		if($value !== null && $value instanceof PagefileExtra) {
			$this->extras[$name] = $value;
		}
		return isset($this->extras[$name]) ? $this->extras[$name] : null;
	}

	/**
	 * Debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		$filedata = $this->filedata();
		if(empty($filedata)) $filedata = null;
		$info = array(
			'url' => $this->url(),
			'filename' => $this->filename(),
			'filesize' => $this->filesize(),
			'description' => $this->description,
			'tags' => $this->tags, 
			'created' => $this->createdStr,
			'modified' => $this->modifiedStr,
			'filemtime' => $this->mtimeStr,
			'filedata' => $filedata,
		);
		if(empty($info['filedata'])) unset($info['filedata']); 
		return $info;
	}
}

