<?php namespace ProcessWire;

/**
 * ProcessWire Pagefiles
 *
 * #pw-summary Pagefiles is a type of WireArray that contains Pagefile objects. It also acts as the value for multi-file fields in ProcessWire.
 * #pw-body = 
 * The items in a Pagefiles array are `Pagefile` objects, indexed by file basename, i.e. `myfile.pdf`. 
 * Information on most traversal, filtering and manipulation methods can be found in the `WireArray` class that Pagefiles extends. 
 * In the examples below, `$page->files` is an instance of Pagefiles:
 * ~~~~~
 * // Determining if any files are present
 * if($page->files->count()) {
 *   // There are files here
 * }
 * 
 * // Traversing and outputting links to all files
 * foreach($page->files as $name => $pagefile) {
 *   echo "<li><a href='$pagefile->url'>$name: $pagefile->description</a></li>";
 * }
 * 
 * // Adding new file(s)
 * $page->files->add('/path/to/file.pdf'); 
 * $page->files->add('http://domain.com/photo.png'); 
 * $page->save('files');
 * 
 * // Getting file by name 
 * $pagefile = $page->files->getFile('file.pdf');
 * $pagefile = $page->files['file.pdf']; // alternate
 * 
 * // Getting first and last file
 * $pagefile = $page->files->first(); 
 * $pagefile = $page->files->last();
 * ~~~~~
 * 
 * #pw-body
 *
 * Typically a Pagefiles object will be associated with a specific field attached to a Page. 
 * There may be multiple instances of Pagefiles attached to a given Page (depending on what fields are in it's fieldgroup).
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 *
 * @property string $path Returns the full server disk path where files are stored.
 * @property string $url Returns the URL where files are stored.
 * @property Page $page Returns the Page that contains this set of files, same as the getPage() method. #pw-group-other
 * @property Field $field Returns the Field that contains this set of files, same as the getField() method. #pw-group-other
 * @method Pagefiles delete() delete(Pagefile $file) Removes the file and deletes from disk when page is saved. #pw-group-manipulation
 * @method Pagefile|bool clone(Pagefile $item, array $options = array()) Duplicate a file and return it. #pw-group-manipulation
 *
 */

class Pagefiles extends WireArray implements PageFieldValueInterface {

	/**
	 * The Page object associated with these Pagefiles
	 * 
	 * @var Page
	 *
	 */
	protected $page; 

	/**
	 * The Field object associated with these Pagefiles
	 * 
	 * @var Field
	 *
	 */
	protected $field; 

	/**
	 * Items to be deleted when Page is saved
	 * 
	 * @var array
	 *
	 */
	protected $unlinkQueue = array();
	
	/**
	 * Items to be renamed when Page is saved (oldName => newName)
	 *
	 * @var array
	 *
	 */
	protected $renameQueue = array();

	/**
	 * Items to be made non-temp upon page save (like duplicated files)
	 *
	 * @var array
	 *
	 */
	protected $unTempQueue = array();

	/**
	 * IDs of any hooks added in this instance, used by the destructor
	 * 
	 * @var array
	 *
	 */
	protected $hookIDs = array();

	/**
	 * Whether or not this is a formatted value
	 * 
	 * @var bool
	 * 
	 */
	protected $formatted = false;

	/**
	 * @var Template|null
	 * 
	 */
	protected $fieldsTemplate = null;
	
	/**
	 * Construct a Pagefiles object
	 *
	 * @param Page $page The page associated with this Pagefiles instance
	 *
	 */
	public function __construct(Page $page) {
		$this->setPage($page); 
		parent::__construct();
		$this->usesNumericKeys = false;
		$this->indexedByName = true;
	}

	/**
	 * Destruct and ensure that hooks are removed
	 * 
	 */
	public function __destruct() {
		$this->removeHooks();
	}

	/**
	 * Remove hooks to the PagefilesManager instance
	 * 
	 */
	protected function removeHooks() {
		if(count($this->hookIDs) && $this->page && $this->page->filesManager) {
			foreach($this->hookIDs as $id) $this->page->filesManager->removeHook($id); 
		}
	}

	/**
	 * Set the Page these files are assigned to
	 * 
	 * @param Page $page
	 * 
	 */
	public function setPage(Page $page) {
		$this->page = $page; 
		// call the filesmanager, just to ensure paths are where they should be
		$page->filesManager(); 
	}

	/**
	 * Set the field these files are assigned to
	 * 
	 * @param Field $field
	 * 
	 */
	public function setField(Field $field) {
		$this->field = $field; 
	}

	/**
	 * Get the page these files are assigned to
	 * 
	 * @return Page
	 * 
	 */
	public function getPage() {
		return $this->page; 
	}

	/**
	 * Get the field these files are assigned to
	 * 
	 * @return Field|null Returns Field, or null if Field has not yet been assigned. 
	 * 
	 */
	public function getField() {
		return $this->field; 
	}

	/**
	 * Creates a new blank instance of itself. For internal use, part of the WireArray interface. 
	 *
	 * Adapted here so that $this->page can be passed to the constructor of a newly created Pagefiles. 
	 * 
	 * #pw-internal
	 *
	 * @return Pagefiles|Pageimages|WireArray
	 * 
	 */
	public function makeNew() {
		$class = get_class($this); 
		/** @var Pagefiles|Pageimages $newArray */
		$newArray = $this->wire(new $class($this->page)); 
		$newArray->setField($this->field); 
		return $newArray; 
	}

	/**
	 * Make a copy, overriding the default clone method used by WireArray::makeCopy
	 *
	 * This is necessary because our __clone() makes new copies of each Pagefile (deep clone)
	 * and we don't want that to occur for the regular find() and filter() operations that
	 * make use of makeCopy().
	 * 
	 * #pw-internal
	 *
	 * @return Pagefiles
	 *
	 */
	public function makeCopy() {
		$newArray = $this->makeNew();
		foreach($this->data as $key => $value) $newArray[$key] = $value; 
		foreach($this->extraData as $key => $value) $newArray->data($key, $value); 
		$newArray->resetTrackChanges($this->trackChanges());
		foreach($newArray as $item) {
			/** @var Pagefile $item */
			$item->setPagefilesParent($newArray);
		}
		return $newArray; 
	}

	/**
	 * When Pagefiles is cloned, ensure that the individual Pagefile items are also cloned
	 * 
	 * #pw-internal
	 *
	 */
	public function __clone() {
		foreach($this as $key => $pagefile) {
			/** @var Pagefile $pagefile */
			$pagefile = clone $pagefile;
			$pagefile->setPagefilesParent($this);
			$this->set($key, $pagefile); 
		}
		parent::__clone();
	}

	/**
	 * Per the WireArray interface, items must be of type Pagefile
	 * 
	 * #pw-internal
	 * 
	 * @param mixed $item
	 * @return bool
	 *
	 */
	public function isValidItem($item) {
		return $item instanceof Pagefile;
	}

	/**
	 * Per the WireArray interface, items are indexed by Pagefile::basename
	 * 
	 * #pw-internal
	 * 
	 * @param mixed $item
	 * @return string
	 *
	 */
	public function getItemKey($item) {
		return $item->basename; 
	}

	/**
	 * Per the WireArray interface, return a blank Pagefile
	 * 
	 * #pw-internal
	 * 
	 * @return Pagefile
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Pagefile($this, '')); 
	}

	/**
	 * Get a value from this Pagefiles instance
	 *
	 * You may also specify a file's 'tag' and it will return the first Pagefile matching the tag.
	 * 
	 * #pw-internal
	 *
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		if($key == 'page') return $this->getPage(); 
		if($key == 'field') return $this->getField(); 
		if($key == 'url') return $this->url();
		if($key == 'path') return $this->path(); 
		return parent::get($key);
	}

	/**
	 * Get for direct access to properties
	 * 
	 * @param int|string $name
	 * @return bool|mixed|Page|Wire|WireData
	 * 
	 */
	public function __get($name) {
		if(in_array($name, array('page', 'field', 'url', 'path'))) return $this->get($name); 
		return parent::__get($name); 
	}

	/**
	 * Find all Pagefiles matching the given selector string or tag
	 *
	 * @param string $selector
	 * @return Pagefiles New instance of Pagefiles
	 *
	public function find($selector) {
		if(!Selectors::stringHasOperator($selector)) {
			// if there is no selector operator in the strong, consider it a tag first
			$value = $this->findTag($selector); 
			// if it didn't match any tag, then see if it matches in some other way
			if(!count($value)) $value = parent::find($selector); 
		} else {
			// there is an operator so we send it straight to WireArray
			$value = parent::find($selector);		
		}
		return $value; 
	}
	 */

	/**
	 * Add a new Pagefile item or filename
	 * 
	 * If give a filename (string) it will create the new `Pagefile` item from it and add it.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param Pagefile|string $item If item is a string (filename) it will create the new `Pagefile` item from it and add it.
	 * @return $this
	 *
	 */
	public function add($item) {

		if(is_string($item)) {
			/** @var Pagefile $item */
			$item = $this->wire(new Pagefile($this, $item)); 
			
		} else if($item instanceof Pagefile) {
			$page = $this->get('page');	
			if($page && "$page" !== "$item->page") {
				$newItem = clone $item;
				$newItem->setPagefilesParent($this);
				$newItem->install($item->filename);
				$newItem->isTemp(true);
				$this->unTempQueue($newItem);
				$this->message("Copied $item->url to $newItem->url", Notice::debug); 
				$item = $newItem;
			}
		}

		$result = parent::add($item); 
		return $result;
	}

	/**
	 * Make any removals take effect on disk
	 * 
	 * #pw-internal
	 *
	 */
	public function hookPageSave() {
		
		if($this->page && $this->field) {
			if(!$this->page->isChanged($this->field->name)) return $this;
		}
		
		$this->page->filesManager()->uncache();
		
		foreach($this->unTempQueue as $item) {
			$item->isTemp(false);
		}

		foreach($this->unlinkQueue as $item) {
			$item->unlink();
		}
		
		foreach($this->renameQueue as $item) {
			$name = $item->get('_rename'); 
			if(!$name) continue;
			$item->rename($name); 
		}

		$this->unTempQueue = array();
		$this->unlinkQueue = array();
		$this->renameQueue = array();
		$this->removeHooks();
		
		return $this; 
	}
	
	protected function addSaveHook() {
		if(!count($this->unlinkQueue) && !count($this->renameQueue) && !count($this->unTempQueue)) {
			$this->hookIDs[] = $this->page->filesManager->addHookBefore('save', $this, 'hookPageSave');
		}
	}

	/**
	 * Delete a pagefile item
	 * 
	 * Deletes the filename associated with the Pagefile and removes it from this Pagefiles array.
	 * The actual deletion of the file does not take effect until `$page->save()`.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Pagefile|string $item Pagefile or basename
	 * @return $this
	 *
	 */
	public function ___delete($item) {
		return $this->remove($item); 
	}

	/**
	 * Delete/remove a Pagefile item
	 *
	 * Deletes the filename associated with the Pagefile and removes it from this Pagefiles array. 
	 * The actual deletion of the file does not take effect until `$page->save()`.
	 * 
	 * #pw-internal Please use the hookable delete() method for public API
	 *
	 * @param Pagefile $key Item to delete/remove. 
	 * @return $this
	 * @throws WireException
	 *
	 */
	public function remove($key) {
		$item = $key;
		if(is_string($item)) $item = $this->get($item); 
		if(!$this->isValidItem($item)) throw new WireException("Invalid type to {$this->className}::remove(item)"); 
		$this->addSaveHook();
		$this->unlinkQueue[] = $item; 
		parent::remove($item); 
		return $this; 
	}

	/**
	 * Delete all files associated with this Pagefiles instance, leaving a blank Pagefiles instance.
	 * 
	 * The actual deletion of the files does not take effect until `$page->save()`.
	 * 
	 * #pw-group-manipulation
	 *
	 * @return $this
	 *
	 */ 
	public function deleteAll() {
		foreach($this as $item) {
			$this->delete($item); 
		}

		return $this; 
	}

	/**
	 * Queue a rename of a Pagefile
	 * 
	 * This only queues a rename. Rename actually occurs when page is saved. 
	 * Note this differs from the behavior of `Pagefile::rename()`. 
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param Pagefile $item
	 * @param string $name
	 * @return Pagefiles 
	 * @see Pagefile::rename()
	 * 
	 */
	public function rename(Pagefile $item, $name) {
		$item->set('_rename', $name); 
		$this->renameQueue[] = $item; 
		$this->trackChange('renameQueue', $item->name, $name);
		$this->addSaveHook();
		return $this;
	}

	/**
	 * Duplicate the Pagefile and add to this Pagefiles instance
	 * 
	 * After duplicating a file, you must follow up with a save of the page containing it.
	 * Otherwise the file is marked for deletion. 
	 * 
	 * @param Pagefile $item Pagefile item to duplicate
	 * @param array $options Options to modify default behavior:
	 *  - `action` (string): Specify "append", "prepend", "after", "before" or blank to only return Pagefile. (default="after")
	 *  - `pagefiles` (Pagefiles): Pagefiles instance file should be duplicated to. (default=$this)
	 * @return Pagefile|bool Returns new Pagefile or boolean false on fail
	 * 
	 */
	public function ___clone(Pagefile $item, array $options = array()) {
		
		$defaults = array(
			'action' => 'after', 
			'pagefiles' => $this, 
		);
	
		$options = array_merge($defaults, $options);
		/** @var Pagefiles $pagefiles */
		$pagefiles = $options['pagefiles'];
		$itemCopy = false;
		$path = $pagefiles->path();		
		$parts = explode('.', $item->basename(), 2); 
		$n = $path === $this->path() ? 1 : 0;
		
		if($n && preg_match('/^(.+?)-(\d+)$/', $parts[0], $matches)) {
			$parts[0] = $matches[1];
			$n = (int) $matches[2];
			if(!$n) $n = 1;
		}
		
		do {
			$pathname = $n ? ($path . $parts[0] . "-$n." . $parts[1]) : ($path . $item->basename);
		} while(file_exists($pathname) && $n++);
		
		if($this->wire()->files->copy($item->filename(), $pathname)) {
			
			$itemCopy = clone $item;
			$itemCopy->setPagefilesParent($pagefiles);
			$itemCopy->setFilename($pathname);
			$itemCopy->isTemp(true);
			
			switch($options['action']) {
				case 'append': $pagefiles->append($itemCopy); break;
				case 'prepend': $pagefiles->prepend($itemCopy); break;
				case 'before': $pagefiles->insertBefore($itemCopy, $item); break;
				case 'after': $pagefiles->insertAfter($itemCopy, $item); break;
			}
			
			$pagefiles->unTempQueue($itemCopy); 
		} 
		
		return $itemCopy;
	}

	/**
	 * Return the full disk path where files are stored
	 * 
	 * @return string
	 *
	 */
	public function path() {
		return $this->page->filesManager->path();
	}

	/**
	 * Returns the web accessible index URL where files are stored
	 * 
	 * @return string
	 *
	 */
	public function url() {
		return $this->page->filesManager->url();
	}

	/**
	 * Given a basename, this method returns a clean version containing valid characters 
	 * 
	 * #pw-internal
	 *
	 * @param string $basename May also be a full path/filename, but it will still return a basename
	 * @param bool $originalize If true, it will generate an original filename if $basename already exists
	 * @param bool $allowDots If true, dots "." are allowed in the basename portion of the filename. 
	 * @param bool $translate True if we should translate accented characters to ascii equivalents (rather than substituting underscores)
	 * @return string
	 *
	 */ 
	public function cleanBasename($basename, $originalize = false, $allowDots = true, $translate = false) {

		$sanitizer = $this->wire()->sanitizer;
		$basename = function_exists('mb_strtolower') ? mb_strtolower($basename) : strtolower($basename);
		$dot = strrpos($basename, '.'); 
		$ext = $dot ? substr($basename, $dot) : '';
		$basename = basename($basename, $ext);
		while(strpos($basename, '..') !== false) $basename = str_replace('..', '', $basename);
		$test = str_replace(array('-', '_', '.'), '', $basename);
		
		if(!ctype_alnum($test)) {
			if($translate) {
				$basename = $sanitizer->filename($basename, Sanitizer::translate); 
			} else {
				$basename = preg_replace('/[^-_.a-z0-9]/', '_', $basename);
				$basename = $sanitizer->filename($basename);
			}
		}
		
		$basename = strtolower($basename);
		if(!ctype_alnum(ltrim($ext, '.'))) $ext = preg_replace('/[^a-z0-9.]/', '_', $ext); 
		if(!$allowDots && strpos($basename, '.') !== false) $basename = str_replace('.', '_', $basename); 
		$basename .= $ext;

		if($originalize) { 
			$path = $this->path(); 
			$n = 0; 
			$p = pathinfo($basename);
			while(is_file($path . $basename)) {
				$n++;
				$basename = "$p[filename]-$n.$p[extension]"; // @hani
				// $basename = (++$n) . "_" . preg_replace('/^\d+_/', '', $basename); 
			}
		}

		return $basename; 
	}

	/**
	 * Return all Pagefile objects that have the given tag(s).
	 * 
	 * Given tag may be any of the following:
	 * 
	 * - `foo` (single tag): Will return all Pagefile objects having the specified tag.
	 * - `foo|bar|baz` (multiple OR tags): Will return Pagefile objects having at least one of the tags listed.
	 * - `foo,bar,baz` (multiple AND tags): Will return Pagefile objects having ALL of the tags listed (since 3.0.17).
	 * - `['foo','bar','baz']` (multiple AND tags array): Same as above but can be specified as an array (since 3.0.17).
	 *
	 * #pw-group-tags
	 * #pw-changelog 3.0.17 Added support for multiple AND tags and allow tag specified as an array.
	 *
	 * @param string|array $tag
	 * @return Pagefiles New Pagefiles array with items that matched the given tag(s).
	 * @see Pagefiles::getTag(), Pagefile::hasTag(), Pagefile::tags()
	 *
	 */
	public function findTag($tag) {
		$items = $this->makeNew();		
		foreach($this as $pagefile) {
			/** @var Pagefile $pagefile */
			if($pagefile->hasTag($tag)) $items->add($pagefile);
		}
		return $items; 
	}

	/**
	 * Return the first Pagefile that matches the given tag or NULL if no match
	 * 
	 * Given tag may be any of the following:
	 *
	 * - `foo` (single tag): Will return the first Pagefile object having the specified tag.
	 * - `foo|bar|baz` (multiple OR tags): Will return first Pagefile object having at least one of the tags listed.
	 * - `foo,bar,baz` (multiple AND tags): Will return first Pagefile object having ALL of the tags listed (since 3.0.17).
	 * - `['foo','bar','baz']` (multiple AND tags array): Same as above but can be specified as an array (since 3.0.17).
	 *
	 * #pw-group-tags
	 * #pw-changelog 3.0.17 Added support for multiple AND tags and allow tag specified as an array.
	 *
	 * @param string $tag
	 * @return Pagefile|null
	 * @see Pagefiles::findTag(), Pagefile::hasTag(), Pagefile::tags()
	 *
	 */
	public function getTag($tag) {
		$item = null;
		foreach($this as $pagefile) {
			/** @var Pagefile $pagefile */
			if(!$pagefile->hasTag($tag)) continue; 
			$item = $pagefile;
			break;
		}
		return $item;
	}
	
	/**
	 * Get list of tags for all files in this Pagefiles array, or return files matching given tag(s)
	 * 
	 * This method can either return a list of all tags available, or return all files 
	 * matching the given tag or tags (an alias of findTag method).
	 * 
	 * ~~~~~
	 * // Get string of all tags
	 * $tagsString = $page->files->tags(); 
	 * 
	 * // Get array of all tags
	 * $tagsArray = $page->files->tags(true); 
	 * 
	 * // Find all files matching given tag
	 * $pagefiles = $page->files->tags('foobar'); 
	 * ~~~~~
	 *
	 * #pw-group-tags
	 *
	 * @param bool|string|array $value Specify one of the following:
	 *  - Omit to return all tags as a string.
	 *  - Boolean true if you want to return tags as an array (rather than string).
	 *  - Boolean false to return tags as an array, with lowercase enforced.
	 *  - String if you want to return files matching tags (See `Pagefiles::findTag()` method for usage)
	 *  - Array if you want to return files matching tags (See `Pagefiles::findTag()` method for usage)
	 * @return string|array|Pagefiles Returns all tags as a string or an array, or Pagefiles matching given tag(s). 
	 *   When a tags array is returned, it is an associative array where the key and value are both the tag (keys are always lowercase).
	 * @see Pagefiles::findTag(), Pagefile::tags()
	 *
	 */
	public function tags($value = null) {
		
		if($value === null) {
			$returnString = true; 
			$value = true; 	
		} else {
			$returnString = false;
		}
		
		if(is_bool($value)) {
			// return array of tags
			$tags = array();
			foreach($this as $pagefile) {
				/** @var Pagefile $pagefile */
				$tags = array_merge($tags, $pagefile->tags($value));
			}
			if($returnString) $tags = implode(' ', $tags);
			return $tags;
		}
		
		// fallback to behavior of findTag
		return $this->findTag($value); 
	}

	/**
	 * Track a change
	 * 
	 * #pw-internal
	 * 
	 * @param string $what
	 * @param null $old
	 * @param null $new
	 * @return $this
	 * 
	 */
	public function trackChange($what, $old = null, $new = null) {
		if($this->field && $this->page) $this->page->trackChange($this->field->name); 
		$result = parent::trackChange($what, $old, $new); 
		return $result;
	}

	/**
	 * Get the Pagefile having the given basename, or null if not found.
	 * 
	 * @param string $name
	 * @return null|Pagefile
	 * 
	 */
	public function getFile($name) {
		$hasFile = null;
		$name = basename($name);
		foreach($this as $pagefile) {
			/**  @var Pagefile $pagefile */
			if($pagefile->basename == $name) {
				$hasFile = $pagefile;
				break;
			}
		}
		return $hasFile;
	}

	/**
	 * Returns true if the given Pagefile is temporary, not yet published. 
	 * 
	 * You may also provide a 2nd argument boolean to set the temp status or check if temporary AND deletable.
	 * 
	 * #pw-internal
	 *
	 * @param Pagefile $pagefile
	 * @param bool|string $set Optionally set the temp status to true or false, or specify string "deletable" to check if file is temporary AND deletable.
	 * @return bool
	 *
	 */
	public function isTemp(Pagefile $pagefile, $set = null) {

		$isTemp = Pagefile::createdTemp == $pagefile->created;
		$checkDeletable = ($set === 'deletable' || $set === 'deleteable');
		
		if(!is_bool($set)) { 
			// temp status is not being set
			if(!$isTemp) return false; // if not a temp file, we can exit now
			if(!$checkDeletable) return true; // if not checking deletable, we can exit now
		}

		$user = $this->wire()->user;
		$session = $this->wire()->session;
		
		$now = time();
		$pageID = $this->page ? $this->page->id : 0;
		$fieldID = $this->field ? $this->field->id : 0;
		$sessionKey = "tempFiles_{$pageID}_{$fieldID}";
		$tempFiles = $pageID && $fieldID ? $session->get($this, $sessionKey) : array();
		if(!is_array($tempFiles)) $tempFiles = array();
		
		if($isTemp && $checkDeletable) {
			$isTemp = false; 
			if(isset($tempFiles[$pagefile->basename])) {
				// if file was uploaded in this session and still temp, it is deletable
				$isTemp = true; 		
			} else if($pagefile->modified < ($now - 14400)) {
				// if file was added more than 4 hours ago, it is deletable, regardless who added it
				$isTemp = true; 
			}
			// isTemp means isDeletable at this point
			if($isTemp) {
				unset($tempFiles[$pagefile->basename]); 	
				// remove file from session - note that this means a 'deletable' check can only be used once, for newly uploaded files
				// as it is assumed you will be removing the file as a result of this method call
				if(count($tempFiles)) {
					$session->set($this, $sessionKey, $tempFiles);
				} else {
					$session->remove($this, $sessionKey);
				}
			}
		}

		if($set === true) {
			// set temporary status to true
			$pagefile->created = Pagefile::createdTemp;
			$pagefile->modified = $now; 
			$pagefile->createdUser = $user;
			$pagefile->modifiedUser = $user;
			//                          mtime                  atime
			@touch($pagefile->filename, Pagefile::createdTemp, $now);
			$isTemp = true;
			if($pageID && $fieldID) { 
				$tempFiles[$pagefile->basename] = 1; 
				$session->set($this, $sessionKey, $tempFiles); 
			}

		} else if($set === false && $isTemp) {
			// set temporary status to false
			$pagefile->created = $now;
			$pagefile->modified = $now; 
			$pagefile->createdUser = $user;
			$pagefile->modifiedUser = $user;
			@touch($pagefile->filename, $now);
			$isTemp = false;
			
			if(isset($tempFiles[$pagefile->basename])) {
				unset($tempFiles[$pagefile->basename]); 
				if(count($tempFiles)) {
					// set temp files back to session, minus current file
					$session->set($this, $sessionKey, $tempFiles); 
				} else {
					// if temp files is empty, we can remove it from the session
					$session->remove($this, $sessionKey); 
				}
			}
		}

		return $isTemp;
	}

	/**
	 * Remove all deletable temporary pagefiles immediately
	 * 
	 * #pw-internal
	 *
	 * @return int Number of files removed
	 * 
	 */
	public function deleteAllTemp() {
		$removed = array();
		foreach($this as $pagefile) {
			/** @var Pagefile $pagefile */
			if(!$this->isTemp($pagefile, 'deletable')) continue; 
			$removed[] = $pagefile->basename();
			$this->remove($pagefile); 
		}
		if(count($removed) && $this->page && $this->field) {
			$this->page->save($this->field->name, array('quiet' => true)); 
			$this->message(
				"Removed '{$this->field->name}' temp file(s) for page {$this->page->path} - " . 
				implode(', ', $removed), 
				Notice::debug | Notice::log
			); 
		}
		return count($removed); 
	}

	/**
	 * Add Pagefile as item to have temporary status removed when Page is saved
	 * 
	 * #pw-internal
	 * 
	 * @param Pagefile $pagefile
	 * 
	 */
	public function unTempQueue(Pagefile $pagefile) {
		$this->addSaveHook();
		$this->unTempQueue[] = $pagefile;	
	}

	/**
	 * Is the given Pagefiles identical to this one?
	 * 
	 * #pw-internal
	 *
	 * @param WireArray $items
	 * @param bool|int $strict
	 * @return bool
	 *
	 */
	public function isIdentical(WireArray $items, $strict = true) {
		if($strict) return $this === $items;
		return parent::isIdentical($items, $strict);
	}

	/**
	 * Reset track changes
	 * 
	 * #pw-internal
	 * 
	 * @param bool $trackChanges
	 * @return $this
	 * 
	 */
	public function resetTrackChanges($trackChanges = true) {
		$this->unlinkQueue = array();
		if($this->page && $this->page->id && $this->field) {
			$this->page->untrackChange($this->field->name);	
		}
		/** @var Pagefiles $result */
		$result = parent::resetTrackChanges($trackChanges);
		return $result;
	}

	/**
	 * Uncache
	 * 
	 * #pw-internal
	 * 
	 */
	public function uncache() {
		//$this->page = null;		
	}

	/**
	 * Get or set formatted state
	 * 
	 * @param bool|null $set
	 * @return bool
	 * 
	 */
	public function formatted($set = null) {
		if(is_bool($set)) $this->formatted = $set;
		return $this->formatted;
	}

	/**
	 * Get Template object used for Pagefile custom fields, if available (false if not)
	 * 
	 * #pw-internal
	 * 
	 * @return bool|Template
	 * @since 3.0.142
	 * 
	 */
	public function getFieldsTemplate() {
		if($this->fieldsTemplate === null) {
			/** @var Field $field */
			$field = $this->getField();
			if($field) {
				$this->fieldsTemplate = false;
				/** @var FieldtypeFile $fieldtype */
				$fieldtype = $field->type;
				$template = $fieldtype instanceof FieldtypeFile ? $fieldtype->getFieldsTemplate($field) : null;
				if($template) $this->fieldsTemplate = $template;
			}
		}
		return $this->fieldsTemplate;
	}

	/**
	 * Get mock/placeholder Page object used for Pagefile custom fields
	 * 
	 * @return Page
	 * @since 3.0.142
	 * 
	 */
	public function getFieldsPage() {
		$field = $this->getField();
		/** @var FieldtypeFile $fieldtype */
		$fieldtype = $field->type;
		return $fieldtype->getFieldsPage($field);
	}

	/**
	 * Get all filenames associated with this Pagefiles object
	 *
	 * @return array
	 * @since 3.0.233
	 *
	 */
	public function getFiles() {
		$filenames = array();
		foreach($this as $pagefile) {
			/** @var Pagefile $pagefile */
			$filenames = array_merge($filenames, $pagefile->getFiles());
		}
		return $filenames;
	}

	/**
	 * Debug info
	 * 
	 * @return array
	 * 
	 */
	public function __debugInfo() {
		
		$info = array(
			'count' => $this->count(), 
			'page' => $this->page ? $this->page->path() : '?',
			'field' => $this->field ? $this->field->name : '?', 
			'url' => $this->url(),
			'path' => $this->path(), 
			'items' => array(),
		);
		
		foreach($this as $key => $pagefile) {
			/** @var Pagefile $pagefile */
			$info['items'][$key] = $pagefile->__debugInfo();
		}
		
		return $info;
	}

}
