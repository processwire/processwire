<?php namespace ProcessWire;

/**
 * ProcessWire PagesType
 *
 * #pw-summary Provides an interface to the Pages class but specific to a given page class/type, with predefined parent and template. 
 * #pw-body = 
 * This class is primarily used by the core as an alternative to `$pages`, providing an API for other Page types like 
 * `User`, `Role`, `Permission`, and `Language`. The `$users`, `$roles`, `$permissions` and `$languages` API variables 
 * are all instances of `PagesType`. This class is typically not instantiated on its own and instead acts as a base class
 * which is extended. 
 * 
 * #pw-body
 * #pw-use-constructor
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * @method Page add($name)
 * @method bool save(Page $page)
 * @method bool delete(Page $page, $recursive = false)
 * 
 * @method saveReady(Page $page)
 * @method saved(Page $page, array $changes = array(), $values = array())
 * @method added(Page $page)
 * @method deleteReady(Page $page)
 * @method deleted(Page $page)
 * 
 *
 */

class PagesType extends Wire implements \IteratorAggregate, \Countable {

	/**
	 * First template defined for use in this PagesType (legacy)
	 * 
	 * @var Template
	 * 
	 */
	protected $template = null;

	/**
	 * Templates defined for use in this PagesType
	 * 
	 * @var array of Template objects indexed by template id
	 * 
	 */
	protected $templates = array();

	/**
	 * ID of the first parent page used by this PagesType (legacy)
	 * 
	 * @var int
	 *
	 */
	protected $parent_id = null;

	/**
	 * Parent IDs defined for use in this PagesType
	 * 
	 * @var array of page IDs indexed by ID.
	 * 
	 */
	protected $parents = array();

	/**
	 * Class name to instantiate pages as
	 * 
	 * Default=blank, which makes it pull from the $template->pageClass property instead. 
	 * 
	 * @var string
	 * 
	 */
	protected $pageClass = '';
	
	/**
	 * Construct this PagesType manager for the given parent and template
	 *
	 * @param ProcessWire $wire
	 * @param Template|int|string|array $templates Template object or array of template objects, names or IDs
	 * @param int|Page|array $parents Parent ID or array of parent IDs (may also be Page or array of Page objects)
	 *
	 */
	public function __construct(ProcessWire $wire, $templates = array(), $parents = array()) {
		$this->setWire($wire);
		$this->addTemplates($templates);
		$this->addParents($parents); 
		$wire->pages->types($this);
		parent::__construct();
	}

	/**
	 * Add one or more templates that this PagesType represents
	 * 
	 * #pw-group-family
	 * 
	 * @param array|int|string $templates Single or array of Template objects, IDs, or names
	 * 
	 */
	public function addTemplates($templates) {
		if(WireArray::iterable($templates)) {
			// array already provided
			foreach($templates as $template) {
				if(is_int($template) || !$template instanceof Template) $template = $this->wire('templates')->get($template);
				if(!$template) continue;
				$this->templates[$template->id] = $template;
			}
		} else {
			// single template object, id, or name provided
			if($templates instanceof Template) {
				$this->templates[$templates->id] = $templates;
			} else {
				// template id or template name
				$template = $this->wire('templates')->get($templates);
				if($template) $this->templates[$template->id] = $template;
			}
		}
		if(empty($this->template)) $this->template = reset($this->templates); 
	}

	/**
	 * Add one or more of parents that this PagesType represents
	 * 
	 * #pw-group-family
	 * 
	 * @param array|int|string|Page $parents Single or array of Page objects, IDs, or paths
	 * 
	 */
	public function addParents($parents) {
		if(!WireArray::iterable($parents)) $parents = array($parents);
		foreach($parents as $parent) {
			if(is_int($parent)) {
				$id = $parent;
			} else if(is_string($parent) && ctype_digit($parent)) {
				$id = (int) $parent;
			} else if(is_string($parent)) {
				$parent = $this->wire('pages')->get($parent, array('loadOptions' => array('autojoin' => false)));
				$id = $parent->id;
			} else if(is_object($parent) && $parent instanceof Page) {
				$id = $parent->id;
			} else {
				$id = 0;
			}
			if($id) {
				$this->parents[$id] = $id;
			}
		}
		if(empty($this->parent_id)) $this->parent_id = reset($this->parents); // legacy deprecated
	}
		

	/**
	 * Convert the given selector string to qualify for the proper page type
	 *
	 * @param string $selectorString
	 * @return string
	 *
	 */
	protected function selectorString($selectorString) {
		if(ctype_digit("$selectorString")) $selectorString = "id=$selectorString"; 
		if(strpos($selectorString, 'sort=') === false && !preg_match('/\bsort=/', $selectorString)) {
			$template = reset($this->templates);
			if($template->sortfield) {
				$sortfield = $template->sortfield;
			} else {
				$sortfield = $this->getParent()->sortfield;
			}
			if(!$sortfield) $sortfield = 'sort';
			$selectorString = trim($selectorString, ", ") . ", sort=$sortfield";
		}
		if(count($this->parents)) $selectorString .= ", parent_id=" . implode('|', $this->parents);
		if(count($this->templates)) $selectorString .= ", templates_id=" . implode('|', array_keys($this->templates));
		return $selectorString; 
	}

	/**
	 * Each loaded page is passed through this function for additional checks if needed
	 * 
	 * @param Page $page
	 *	
	 */
	protected function loaded(Page $page) { }

	/**
	 * Is the given page a valid type for this class?
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @return bool
	 *
	 */
	public function isValid(Page $page) {

		// quick exit when possible
		if($this->template && $this->template->id === $page->template->id) {
			if($this->parent_id && $this->parent_id === $page->parent_id) return true;
		}
	
		$validTemplate = $this->hasValidTemplate($page);
		if(!$validTemplate && count($this->templates)) {
			$validTemplates = implode(', ', array_keys($this->templates));
			$this->error("Page $page->path must have template: $validTemplates");
			return false;
		}
		
		$validParent = $this->hasValidParent($page);
		if(!$validParent && count($this->parents)) {
			$validParents = implode(', ', $this->parents);
			$this->error("Page $page->path must have parent: $validParents");
			return false;
		}
		
		return true; 
	}

	/**
	 * Does given Page use a template managed by this type?
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @return bool
	 * @since 3.0.128
	 * 
	 */
	public function hasValidTemplate(Page $page) {
		$tid = (int) $page->templates_id;
		if($this->template && count($this->templates) === 1) {
			return $this->template->id === $tid;
		}
		$valid = false;
		foreach($this->templates as $template) {
			if($tid !== $template->id) continue;
			$valid = true;
			break;
		}
		return $valid;
	}

	/**
	 * Does given Page have a parent managed by this type?
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @return bool
	 * @since 3.0.128
	 *
	 */
	public function hasValidParent(Page $page) {
		$parent_id = (int) $page->parent_id; 
		if($this->parent_id && $this->parent_id === $parent_id) return true;
		$valid = false;
		foreach($this->parents as $parent_id) {
			if($parent_id !== $page->parent_id) continue;
			$valid = true;
			break;
		}
		return $valid;
	}

	/**
	 * Does given Page have a Page class name managed by this type?
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @return bool
	 * @since 3.0.128
	 *
	 */
	public function hasValidClass(Page $page) {
		$pageClass = $page->className();
		if($this->pageClass && $pageClass === $this->pageClass) return true;
		$valid = false;
		foreach($this->templates as $template) {
			/** @var Template $template */
			if($template->pageClass) {
				// template specifies a class 
				if($template->pageClass === $pageClass) $valid = true;
			} else {
				// template specifies NO Page class, which implies "Page" as a class name is valid
				if($pageClass === 'Page') $valid = true;
			}
			if($valid) break;	
		}
		return $valid;
	}

	/**
	 * Get options that will be passed to Pages::getById()
	 * 
	 * @param array $loadOptions Optionally specify options to merge with and override defaults
	 * @return array
	 * 
	 */
	protected function getLoadOptions(array $loadOptions = array()) {
		$_loadOptions = array(
			'pageClass' => $this->getPageClass(),
			//'getNumChildren' => false, 
			'joinSortfield' => false,
			'joinFields' => $this->getJoinFieldNames()
		);
		if(count($loadOptions)) $_loadOptions = array_merge($_loadOptions, $loadOptions);
		return $_loadOptions; 
	}

	/**
	 * Given a Selector string, return the Page objects that match in a PageArray. 
	 *
	 * @param string $selectorString
	 * @param array $options Options to modify default behavior:
	 *  - `findOne` (bool): apply optimizations for finding a single page and include pages with 'hidden' status
	 * @return PageArray
	 * @see Pages::find()
	 *
	 */
	public function find($selectorString, $options = array()) {
		if(!isset($options['findAll'])) $options['findAll'] = true;
		if(!isset($options['loadOptions'])) $options['loadOptions'] = array();
		$options['loadOptions'] = $this->getLoadOptions($options['loadOptions']); 
		if(empty($options['caller'])) $options['caller'] = $this->className() . ".find($selectorString)";
		$pages = $this->wire('pages')->find($this->selectorString($selectorString), $options);
		/** @var PageArray $pages */
		foreach($pages as $page) {
			if(!$this->isValid($page)) {
				$pages->remove($page);
			} else {
				$this->loaded($page);
			}
		}
		return $pages; 
	}

	/**
	 * Given a Selector string, return the page IDs that match
	 * 
	 * @param string $selectorString
	 * @param array $options
	 * @return array
	 * @since 3.0.128
	 * @see Pages::findIDs()
	 * 
	 */
	public function findIDs($selectorString, $options = array()) {
		if(!isset($options['findAll'])) $options['findAll'] = true;
		if(empty($options['caller'])) $options['caller'] = $this->className() . ".findIDs($selectorString)";
		$ids = $this->wire('pages')->findIDs($this->selectorString($selectorString), $options);
		return $ids;
	}

	/**
	 * Get the first match of your selector string
	 * 
	 * @param string|int $selectorString
	 * @return Page|NullPage|null
	 * 
	 */
	public function get($selectorString) {
		
		$options = $this->getLoadOptions(array('getOne' => true));
		if(empty($options['caller'])) {
			$caller = $this->className() . ".get($selectorString)";
			$options['caller'] = $caller;
		} else {
			$caller = $options['caller'];
		}

		if(ctype_digit("$selectorString")) {
			// selector string contains a page ID
			if(count($this->templates) == 1 && count($this->parents) == 1) {
				// optimization for when there is only 1 template and 1 parent
				$options['template'] = $this->template;
				$options['parent_id'] = $this->parent_id; 
				$page = $this->wire('pages')->getById(array((int) $selectorString), $options);
				return $page ? $page : $this->wire('pages')->newNullPage();
			} else {
				// multiple possible templates/parents
				$page = $this->wire('pages')->getById(array((int) $selectorString), $options); 
				return $page; 
			}
			
		} else if(strpos($selectorString, '=') === false) { 
			// selector string contains no operators, so it is a page name or path
			if(strpos($selectorString, '/') === false) {
				// selector string contains no operators or slashes, so we assume it to be a page ame
				$s = $this->sanitizer->name($selectorString);
				if($s === $selectorString) $selectorString = "name=$s";
			} else {
				// page path, can pass through
			}
			
		} else {
			// selector string with operators, can pass through
		}
		
		$page = $this->pages->get($this->selectorString($selectorString), array(
			'caller' => $caller, 
			'loadOptions' => $options
		)); 
		if($page->id && !$this->isValid($page)) $page = $this->wire('pages')->newNullPage();
		if($page->id) $this->loaded($page);
		
		return $page; 
	}

	/**
	 * Save a page object and its fields to database. 
	 *
	 * - This is the same as calling $page->save()
	 * - If the page is new, it will be inserted. If existing, it will be updated. 
	 * - If you want to just save a particular field in a Page, use `$page->save($fieldName)` instead. 
	 *
	 * @param Page $page
	 * @return bool True on success
	 * @throws WireException
	 *
	 */
	public function ___save(Page $page) {
		if(!$this->isValid($page)) throw new WireException($this->errors('first'));
		return $this->wire('pages')->save($page);
	}
	
	/**
	 * Permanently delete a page and its fields. 
	 *
	 * Unlike `$pages->trash()`, pages deleted here are not restorable. 
	 *
	 * If you attempt to delete a page with children, and don’t specifically set the `$recursive` argument to `true`, then 
	 * this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown.
	 *
	 * @param Page $page
	 * @param bool $recursive If set to true, then this will attempt to delete all children too. 
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function ___delete(Page $page, $recursive = false) {
		if(!$this->isValid($page)) throw new WireException($this->errors('first'));
		return $this->pages->delete($page, $recursive);
	}

	/**
	 * Adds a new page with the given $name and returns it
	 *
	 * - If the page has any other fields, they will not be populated, only the name will.
	 * - Returns a `NullPage` on error, such as when a page of this type already exists with the same name/parent.
	 *
	 * @param string $name Name to use for the new page
	 * @return Page|NullPage
	 *
	 */
	public function ___add($name) {
		
		$parent = $this->getParent();

		$page = $this->wire('pages')->newPage(array(
			'pageClass' => $this->getPageClass(),
			'template' => $this->template
		)); 
		$page->parent = $parent; 
		$page->name = $name; 
		$page->sort = $parent->numChildren; 

		try {
			$this->save($page); 

		} catch(\Exception $e) {
			$this->trackException($e, false);
			$page = $this->wire('pages')->newNullPage();
		}

		return $page; 
	}

	/**
	 * Make it possible to iterate all pages of this type per the \IteratorAggregate interface.
	 *
	 * Only recommended for page types that don't contain a lot of pages. 
	 * 
	 * #pw-internal
	 *
	 */
	public function getIterator() {
		return $this->find("id>0, sort=name", array(
			'caller' => $this->className() . '.getIterator()'
		)); 
	}

	/**
	 * Get the template used by this type (or first template if there are multiple)
	 * 
	 * #pw-group-family
	 * 
	 * @return Template
	 * 
	 */
	public function getTemplate() {
		return $this->template; 
	}

	/**
	 * Get the templates (plural) used by this type
	 * 
	 * #pw-group-family
	 * 
	 * @return array|Template[] Array of Template objects indexed by template ID. 
	 * 
	 */
	public function getTemplates() {
		return count($this->templates) ? $this->templates : array($this->template);
	}

	/**
	 * Get the parent page ID used by this type (or first parent ID if there are multiple)
	 * 
	 * #pw-group-family
	 * 
	 * @return int
	 * 
	 */
	public function getParentID() {
		return $this->parent_id; 
	}

	/**
	 * Get the parent page IDs used by this type
	 * 
	 * #pw-group-family
	 * 
	 * @return array Array of parent page IDs (integers)
	 * 
	 */
	public function getParentIDs() {
		return count($this->parents) ? $this->parents : array($this->parent_id); 
	}

	/**
	 * Get the parent Page object (or first parent Page object if there are multiple)
	 * 
	 * #pw-group-family
	 * 
	 * @return Page|NullPage
	 * 
	 */
	public function getParent() {
		return $this->wire('pages')->get($this->parent_id);
	}

	/**
	 * Get the parent Page objects in a PageArray
	 * 
	 * #pw-group-family
	 * 
	 * @return PageArray
	 * 
	 */
	public function getParents() {
		if(count($this->parents)) {
			return $this->wire('pages')->getById($this->parents);
		} else {
			$parent = $this->getParent();
			$parents = $this->wire('pages')->newPageArray();
			$parents->add($parent);
			return $parents; 
		}
	}

	/**
	 * Set the PHP class name to use for Page objects of this type
	 * 
	 * #pw-group-family
	 * 
	 * @param string $class
	 * 
	 */
	public function setPageClass($class) {
		$this->pageClass = $class;
	}

	/**
	 * Get the PHP class name used by Page objects of this type
	 * 
	 * #pw-group-family
	 * 
	 * @return string
	 * 
	 */
	public function getPageClass() {
		if($this->pageClass) return $this->pageClass;
		if($this->template && $this->template->pageClass) return $this->template->pageClass;
		return 'Page';
	}

	/**
	 * Return the number of pages in this type matching the given selector string
	 * 
	 * @param string $selectorString Optional, if omitted then returns count of all pages of this type
	 * @param array $options Options to modify default behavior (see $pages->count method for details)
	 * @return int
	 * @see Pages::count()
	 * 
	 */
	public function count($selectorString = '', array $options = array()) {
		if(empty($selectorString) && empty($options) && count($this->parents) == 1) {
			return $this->getParent()->numChildren();
		}
		$selectorString = $this->selectorString($selectorString); 
		$defaults = array('findAll' => true); 
		$options = array_merge($defaults, $options); 
		return $this->wire('pages')->count($selectorString, $options); 
	}

	/**
	 * Get names of fields that should always be autojoined
	 * 
	 * @return array
	 * 
	 */
	protected function getJoinFieldNames() {
		return array();
	}

	/*********************************************************************************************
	 * HOOKS
	 * 
	 */

	/**
	 * Hook called just before a page of this type is saved
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page The page about to be saved
	 * @return array Optional extra data to add to pages save query.
	 * @since 3.0.128
	 *
	 */
	public function ___saveReady(Page $page) { 
		if($page) {}
		return array(); 
	}

	/**
	 * Hook called after a page of this type is successfully saved
	 *
	 * #pw-hooker
	 *
	 * @param Page $page The page that was saved
	 * @param array $changes Array of field names that changed
	 * @param array $values Array of values that changed, if values were being recorded, see Wire::getChanges(true) for details.
	 * @since 3.0.128
	 *
	 */
	public function ___saved(Page $page, array $changes = array(), $values = array()) { }

	/**
	 * Hook called when a new page of this type has been added
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page
	 * @since 3.0.128
	 *
	 */
	public function ___added(Page $page) { }

	/**
	 * Hook called when a page is about to be deleted, but before data has been touched
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page
	 * @since 3.0.128
	 *
	 */
	public function ___deleteReady(Page $page) { }

	/**
	 * Hook called when a page and its data have been deleted
	 * 
	 * #pw-hooker
	 *
	 * @param Page $page
	 * @since 3.0.128
	 *
	 */
	public function ___deleted(Page $page) { }

}
