<?php namespace ProcessWire;

/**
 * ProcessWire Templates
 *
 * Manages and provides access to all the Template instances
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Manages and provides access to all the Templates.
 *
 * @method TemplatesArray find($selector) Return the templates matching the the given selector query. #pw-internal
 * @method bool save(Template $template) Save the given Template.
 * @method bool delete() delete(Template $template) Delete the given Template. Note that this will throw a fatal error if the template is in use by any pages.
 * @method bool|Saveable|Template clone(Saveable $item, $name = '') #pw-internal
 * @method array getExportData(Template $template) Export Template data for external use. #pw-advanced
 * @method array setImportData(Template $template, array $data) Given an array of Template export data, import it to the given Template. #pw-advanced
 * @method void fileModified(Template $template) Hook called when a template detects that its file has been modified. #pw-hooker
 * @method array getTags($getTemplateNames = false) Get tags for all templates (3.0.179+) #pw-advanced
 *
 */
class Templates extends WireSaveableItems {

	/**
	 * Reference to all the Fieldgroups
	 * 
	 * @var Fieldgroups
	 *
	 */
	protected $fieldgroups = null; 

	/**
	 * WireArray of all Template instances
	 * 
	 * @var TemplatesArray
	 *
	 */
	protected $templatesArray = null;
	
	/**
	 * Templates that had changed files during this request
	 *
	 * @var array Array of Template objects indexed by id
	 *
	 */
	protected $fileModTemplates = array();

	/**
	 * Cached template ID to page class names (for getPageClass method)
	 * 
	 * @var array
	 * 
	 */
	protected $pageClassNames = array();

	/**
	 * Construct the Templates
	 *
	 * @param Fieldgroups $fieldgroups Reference to the Fieldgroups
	 *
	 */
	public function __construct(Fieldgroups $fieldgroups) {
		parent::__construct();
		$fieldgroups->wire($this);
		$this->fieldgroups = $fieldgroups;
	}

	/**
	 * Initialize the TemplatesArray and populate
	 * 
	 * #pw-internal
	 *
	 */
	public function init() {
		$this->getWireArray();
	}

	/**
	 * Return the WireArray that this DAO stores it's items in
	 * 
	 * #pw-internal
	 *
	 */
	public function getAll() {
		if($this->useLazy()) $this->loadAllLazyItems();
		return $this->getWireArray();
	}
	
	/**
	 * Get WireArray container that items are stored in
	 * 
	 * #pw-internal
	 *
	 * @return WireArray|TemplatesArray
	 * @since 3.0.194
	 *
	 */
	public function getWireArray() {
		if($this->templatesArray === null) {
			$this->templatesArray = $this->wire(new TemplatesArray());
			$this->load($this->templatesArray); 
		}
		return $this->templatesArray;
	}

	/**
	 * Make an item and populate with given data
	 * 
	 * #pw-internal
	 *
	 * @param array $a Associative array of data to populate
	 * @return Saveable|Wire|Template
	 * @since 3.0.146
	 *
	 */
	public function makeItem(array $a = array()) {

		/** @var Template $template */
		$template = $this->wire(new Template());
		$template->loaded(false);
	
		if(!empty($a['data'])) { 
			if(is_string($a['data'])) $a['data'] = $this->decodeData($a['data']);
		} else {
			unset($a['data']);
		}
		
		foreach(array('id', 'name', 'fieldgroups_id', 'flags', 'cache_time') as $key) {
			if(!isset($a[$key])) continue;
			$value = $key === 'name' ? $a[$key] : (int) $a[$key];
			$template->setRaw($key, $value); 
			unset($a[$key]);
		}
		
		foreach($a as $key => $value) {
			$template->set($key, $value);
		}
		
		$template->loaded(true);
		$template->resetTrackChanges(true);
		
		return $template;
	}

	/**
	 * Load all lazy items
	 * 
	 * #pw-internal
	 * 
	 * @since 3.0.194
	 * 
	 */
	public function loadAllLazyItems() {
		if(!$this->useLazy()) return;
		$this->wire()->fieldgroups->loadAllLazyItems();
		parent::loadAllLazyItems();
	}

	/**
	 * Return a new blank item 
	 * 
	 * @return Template
	 * 
	 * #pw-internal
	 *
	 */
	public function makeBlankItem() {
		return $this->wire(new Template()); 
	}

	/**
	 * Return the name of the table that this DAO stores item records in
	 * 
	 * #pw-internal
	 *
	 */
	public function getTable() {
		return 'templates';
	}

	/**
	 * Return the field name that fields should initially be sorted by
	 * 
	 * #pw-internal
	 *
	 */
	public function getSort() {
		return $this->getTable() . ".name";
	}

	/**
	 * Add and save new template (and fieldgroup) with given name and return it
	 * 
	 * @param string $name
	 * @param array $properties Any additional properties to add to template
	 * @return Template
	 * @throws WireException if given invalid template name or template already exists
	 * @since 3.0.170
	 * 
	 */
	public function add($name, array $properties = array()) {
		
		if(!is_string($name)) {
			throw new WireException("You must specify the template name to add"); 
		}
		
		$saniName = $this->wire()->sanitizer->templateName($name);
		
		if(empty($saniName)) {
			throw new WireException("Invalid template name: $name"); 
		}
		
		$name = $saniName;
		$template = $this->get($name);
		
		if($template) {
			throw new WireException("Template '$name' cannot be added because it already exists");
		}
	
		$fieldgroups = $this->wire()->fieldgroups;
		$fieldgroup = $fieldgroups->get($name);
		
		if(!$fieldgroup) {
			$fieldgroup = new Fieldgroup();
			$this->wire($fieldgroup);
			$fieldgroup->name = $name;
			$fieldgroups->save($fieldgroup);
		}
		
		$template = new Template();
		$this->wire($template);
		$template->name = $name;
		$template->fieldgroup = $fieldgroup;
		foreach($properties as $key => $value) $template->set($key, $value);
		$this->save($template);
		
		return $template;
	}

	/**
	 * Get a template by name or ID
	 * 
	 * Given a template ID or name, return the matching template or NULL if not found.
	 * 
	 * @param string|int $key Template name or ID
	 * @return Template|null|string
	 *
	 */
	public function get($key) {
		if($key === 'path') return $this->wire()->config->paths->templates;
		return parent::get($key);
	}

	/**
	 * Save a Template
	 * 
	 * ~~~~~
	 * $templates->save($template); 
	 * ~~~~~
	 *
	 * @param Saveable|Template $item Template to save
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function ___save(Saveable $item) {
		
		// If the template's fieldgroup has changed, then we delete data that's no longer applicable to the new fieldgroup. 

		$isNew = $item->id < 1; 

		if(!$item->fieldgroup) {
			throw new WireException("Template '$item' cannot be saved because it has no fieldgroup assigned");
		}
		if(!$item->fieldgroup->id) {
			throw new WireException("You must save Fieldgroup '{$item->fieldgroup->name}' before adding to Template '$item'");
		}

		$rolesChanged = $item->isChanged('useRoles');

		if($this->wire()->pages->get('/')->template->id == $item->id) {
			if(!$item->useRoles) {
				throw new WireException("Template '$item' is used by the homepage and thus must manage access");
			}
			if(!$item->hasRole('guest')) {
				throw new WireException("Template '$item' is used by the homepage and thus must have the 'guest' role assigned.");
			}
		}
		
		if(!$item->isChanged('modified')) $item->modified = time();

		$result = parent::___save($item); 

		if($result && !$isNew && $item->fieldgroupPrevious && $item->fieldgroupPrevious->id != $item->fieldgroup->id) {
			// the fieldgroup has been changed
			// remove data from all fields that are not part of the new fieldgroup
			/** @var FieldsArray $removeFields */
			$removeFields = $this->wire(new FieldsArray());
			foreach($item->fieldgroupPrevious as $field) {
				if(!$item->fieldgroup->has($field)) {
					$removeFields->add($field); 
				}
			}
			if(count($removeFields)) { 
				foreach($removeFields as $field) {
					/** @var Field $field */
					$field->type->deleteTemplateField($item, $field); 
				}
				/*
				$pages = $this->fuel('pages')->find("templates_id={$item->id}, check_access=0, status<" . Page::statusMax); 
				foreach($pages as $page) {
					foreach($removeFields as $field) {
						$field->type->deletePageField($page, $field); 
						if($this->fuel('config')->debug) $this->message("Removed field '$field' on page '{$page->url}'"); 
					}
				}
				*/
			}
		}

		if($rolesChanged) { 
			/** @var PagesAccess $access */
			$access = $this->wire(new PagesAccess());
			$access->updateTemplate($item); 
		}
	
		$cache = $this->wire()->cache;
		$cache->maintenance($item);

		return $result; 
	}

	/**
	 * Delete a Template
	 * 
	 * @param Template|Saveable $item Template to delete
	 * @return bool True on success, false on failure
	 * @throws WireException Thrown when you attempt to delete a template in use, or a system template. 
	 *
	 */
	public function ___delete(Saveable $item) {
		
		$name = $item->name;
		$id = $item->id;
		
		if($item->flags & Template::flagSystem) {
			throw new WireException("Can't delete template '$name' because it is a system template.");
		}
		
		$cnt = $item->getNumPages();
		
		if($cnt > 0) {
			throw new WireException("Can't delete template '$name' because it is used by $cnt pages.");
		}

		$return = parent::___delete($item);

		if($return) {
			$fieldgroups = $this->wire()->fieldgroups;
			$fieldgroup = $fieldgroups->get($name);
			if($fieldgroup) {
				// also delete fieldgroup, if not used by any other templates
				$cnt = 0;
				foreach($this as $t) {
					/** @var Template $t */
					if($t->id != $id && $t->fieldgroup->id == $fieldgroup->id) $cnt++;
				}
				if(!$cnt) $fieldgroups->delete($fieldgroup);
			}
		}
		
		$cache = $this->wire()->cache;
		$cache->maintenance($item); 
		
		return $return;
	}

	/**
	 * Clone the given Template
	 *
	 * Note that this also clones the Fieldgroup if the template being cloned has its own named fieldgroup.
	 * 
	 * @todo: clone the fieldgroup context settings too. 
	 *
	 * @param Template|Saveable $item Template to clone
	 * @param string $name Name of new template that will be created, or omit to auto-assign. 
	 * @return bool|Template $item Returns the new Template on success, or false on failure
	 *
	 */
	public function ___clone(Saveable $item, $name = '') {

		$original = $item;
		/** @var Template $item */
		$item = clone $item; 

		if($item->flags & Template::flagSystem) {
			// we want to avoid creating clones that have system flags
			$item->flags = $item->flags | Template::flagSystemOverride; 
			$item->flags = $item->flags & ~Template::flagSystem;
			$item->flags = $item->flags & ~Template::flagSystemOverride;
		}

		$item->id = 0; // note this must be after removing system flags

		$fieldgroup = $item->fieldgroup; 

		if($fieldgroup->name == $item->name) {
			// if the fieldgroup and the item have the same name, we'll also clone the fieldgroup
			$fieldgroup = $this->wire()->fieldgroups->clone($fieldgroup, $name); 	
			$item->fieldgroup = $fieldgroup;
		}

		/** @var Template|bool $item */
		$item = parent::___clone($item, $name);

		if($item && $item->id && !$item->altFilename) { 
			// now that we have a clone, lets also clone the template file, if it exists
			$config = $this->wire()->config;
			$files = $this->wire()->files;
			$path = $config->paths->templates; 
			$ext = $config->templateExtension ? $config->templateExtension : 'php';
			$file = "$path$item->name.$ext";
			if($original->filenameExists() && is_writable($path) && !$files->exists($file)) { 
				if($files->copy($original->filename, $file)) $item->filename = $file;
			}
		}

		return $item;
	}

	/**
	 * Rename given template (and its fieldgroup, and file, when possible)
	 * 
	 * Given template must have its previous 'name' still present, and new name provided in $name
	 * argument to this method. 
	 * 
	 * @param Template $template
	 * @param string $name New name to use
	 * @since 3.0.170
	 * @throws WireException if rename cannot be completed
	 * 
	 */
	public function rename(Template $template, $name) {
		
		$config = $this->wire()->config;
		$saniName = $this->wire()->sanitizer->templateName($name);
		
		if(empty($saniName)) throw new WireException("Invalid template name: $name");
	
		$name = $saniName;
		$basename = "$template->name.$config->templateExtension";
		$filename = $template->filenameExists() ? $template->filename() : '';
		$fieldgroup = $template->fieldgroup;
		$t = $this->get($name);
		
		if($t instanceof Template && $t->id != $template->id) {
			throw new WireException("Template '$name' already exists");
		}
		
		if($fieldgroup->name === $template->name) {
			// rename fieldgroup too
			$fg = $this->wire()->fieldgroups->get($name);
			if($fg && $fg->id != $fieldgroup->id) throw new WireException("Fieldgroup '$name' already exists"); 
			$fieldgroup->name = $name;
			$this->wire()->fieldgroups->save($fieldgroup);
		}
		
		$template->name = $name;
		$this->save($template);
		
		if($filename && basename($filename) === $basename) { 
			$newFilename = $config->paths->templates . $name . $config->templateExtension;
			if(is_readable($filename) && is_writable($filename) && !file_exists($newFilename)) {
				// rename file
				$this->wire()->files->rename($filename, $newFilename);
			}
		}
	}

	/**
	 * Return the number of pages using the provided Template
	 * 
	 * @param Template $tpl Template you want to get count for 
	 * @return int Total number of pages in use by given Template
	 *
	 */
	public function getNumPages(Template $tpl) {
		$database = $this->wire()->database;
		$query = $database->prepare("SELECT COUNT(*) AS total FROM pages WHERE templates_id=:template_id"); // QA
		$query->bindValue(":template_id", $tpl->id, \PDO::PARAM_INT);
		$query->execute();
		return (int) $query->fetchColumn();	
	}

	/**
	 * Overridden from WireSaveableItems to retain specific keys
	 * 
	 * @param array $value
	 * @return string
	 *
	 */
	protected function encodeData(array $value) {
		return wireEncodeJSON($value, array('slashUrls', 'compile')); 	
	}

	/**
	 * Export Template data for external use
	 * 
	 * #pw-advanced
	 * 
	 * @param Template $template Template you want to export
	 * @return array Associative array of export data
	 *
	 */
	public function ___getExportData(Template $template) {

		$template->set('_exportMode', true); 
		$data = $template->getTableData();

		// flatten
		foreach($data['data'] as $key => $value) {
			$data[$key] = $value;
		}

		// remove unnecessary
		unset($data['data'], $data['modified']);

		// convert fieldgroup to guid
		$fieldgroup = $this->wire()->fieldgroups->get((int) $data['fieldgroups_id']);
		if($fieldgroup) $data['fieldgroups_id'] = $fieldgroup->name;

		// convert family settings to guids
		foreach(array('parentTemplates', 'childTemplates') as $key) {
			if(!isset($data[$key])) continue;
			$values = array();
			foreach($data[$key] as $id) {
				if(ctype_digit("$id")) $id = (int) $id;
				$t = $this->get($id);
				if($t) $values[] = $t->name;
			}
			$data[$key] = $values;
		}

		// convert roles to guids
		if($template->useRoles) {
			$roles = $this->wire()->roles;
			foreach(array('roles', 'editRoles', 'addRoles', 'createRoles') as $key) {
				if(!isset($data[$key])) continue;
				$values = array();
				foreach($data[$key] as $id) {
					$role = $id instanceof Role ? $id : $roles->get((int) $id);
					$values[] = $role->name;
				}
				$data[$key] = $values;
			}
		}

		// convert pages to guids
		if(((int) $template->cache_time) != 0) {
			if(!empty($data['cacheExpirePages'])) {
				$pages = $this->wire()->pages;
				$values = array();
				foreach($data['cacheExpirePages'] as $id) {
					$page = $pages->get((int) $id);
					if(!$page->id) continue;
					$values[] = $page->path;
				}
			}
		}

		$fieldgroupData = array('fields' => array(), 'contexts' => array());
		if($template->fieldgroup) $fieldgroupData = $template->fieldgroup->getExportData();
		$data['fieldgroupFields'] = $fieldgroupData['fields'];
		$data['fieldgroupContexts'] = $fieldgroupData['contexts'];
		unset($data['_lazy'], $data['_exportMode']); 

		$template->set('_exportMode', false); 
		return $data;
	}

	/**
	 * Given an array of Template export data, import it to the given Template
	 * 
	 * ~~~~~~
	 * // Example of return value
	 * $returnValue = array(
	 *   'property_name' => array(
	 *     'old' => 'old value', // old value (in string comparison format)
	 *     'new' => 'new value', // new value (in string comparison format)
	 *     'error' => 'error message or blank if no error' // error message (string) or messages (array)
	 *   ), 
	 *   'another_property_name' => array(
	 *     // ...
	 *   ) 
	 * );
	 * ~~~~~~
	 *
	 * #pw-advanced
	 * 
	 * @param Template $template Template you want to import to
	 * @param array $data Import data array (must have been exported from getExportData() method).
	 * @return array Returns array with list of changes (see example in method description)
	 *
	 */
	public function ___setImportData(Template $template, array $data) {
		
		$fieldgroups = $this->wire()->fieldgroups;

		$template->set('_importMode', true); 
		$fieldgroupData = array();
		$changes = array();
		$_data = $this->getExportData($template);

		if(isset($data['fieldgroupFields'])) $fieldgroupData['fields'] = $data['fieldgroupFields'];
		if(isset($data['fieldgroupContexts'])) $fieldgroupData['contexts'] = $data['fieldgroupContexts'];
		unset($data['fieldgroupFields'], $data['fieldgroupContexts'], $data['id']);

		foreach($data as $key => $value) {
			if($key == 'fieldgroups_id' && !ctype_digit("$value")) {
				$fieldgroup = $fieldgroups->get($value);
				if(!$fieldgroup) {
					/** @var Fieldgroup $fieldgroup */
					$fieldgroup = $this->wire(new Fieldgroup());
					$fieldgroup->name = $value;
				}
				$oldValue = $template->fieldgroup ? $template->fieldgroup->name : '';
				$newValue = $fieldgroup->name;
				$error = '';
				try {
					$template->setFieldgroup($fieldgroup);
				} catch(\Exception $e) {
					$this->trackException($e, false);
					$error = $e->getMessage();
				}
				if($oldValue != $fieldgroup->name) {
					if(!$fieldgroup->id) $newValue = "+$newValue";
					$changes['fieldgroups_id'] = array(
						'old' => $template->fieldgroup->name,
						'new' => $newValue,
						'error' => $error
					);
				}
			}

			$template->errors("clear");
			$oldValue = isset($_data[$key]) ? $_data[$key] : '';
			$newValue = $value;
			if(is_array($oldValue)) $oldValue = wireEncodeJSON($oldValue, true, false);
			else if(is_object($oldValue)) $oldValue = (string) $oldValue;
			if(is_array($newValue)) $newValue = wireEncodeJSON($newValue, true, false);
			else if(is_object($newValue)) $newValue = (string) $newValue;

			// everything else
			if($oldValue == $newValue || (empty($oldValue) && empty($newValue))) {
				// no change needed
			} else {
				// changed
				try {
					$template->set($key, $value);
					if($key == 'roles') $template->getRoles(); // forces reload of roles (and resulting error messages)
					$error = $template->errors('clear');
				} catch(\Exception $e) {
					$this->trackException($e, false);
					$error = array($e->getMessage());
				}
				$changes[$key] = array(
					'old' => $oldValue,
					'new' => $newValue,
					'error' => (count($error) ? $error : array())
				);
			}
		}

		if(count($fieldgroupData)) {
			$_changes = $template->fieldgroup->setImportData($fieldgroupData);
			if($_changes['fields']['new'] != $_changes['fields']['old']) {
				$changes['fieldgroupFields'] = $_changes['fields'];
			}
			if($_changes['contexts']['new'] != $_changes['contexts']['old']) {
				$changes['fieldgroupContexts'] = $_changes['contexts'];
			}
		}

		$template->errors('clear');
		$template->set('_importMode', false); 

		return $changes;
	}

	/**
	 * Return the parent page that this template assumes new pages are added to
	 *
	 * - This is based on family settings, when applicable.
	 * - It also takes into account user access, if requested (see arg 1).
	 * - If there is no defined parent, NULL is returned.
	 * - If there are multiple defined parents, a NullPage is returned (use $getAll to get them).
	 * 
	 * @param Template $template
	 * @param bool $checkAccess Whether or not to check for user access to do this (default=false).
	 * @param bool|int $getAll Specify true to return all possible parents (makes method always return a PageArray)
	 *   Or specify int of maximum allowed `Page::status*` constant for items in returned PageArray (since 3.0.138). 
	 * @return Page|NullPage|null|PageArray
	 *
	 */
	public function getParentPage(Template $template, $checkAccess = false, $getAll = false) {
		
		$pages = $this->wire()->pages;
		
		$foundParents = $pages->newPageArray();
		$maxStatus = is_int($getAll) && $getAll ? ($getAll * 2) : 0;
		$earlyExit = false;

		if($template->noParents == -1) {
			// only 1 page of this type allowed 
			if($this->getNumPages($template) > 0) $earlyExit = true;
		} else if($template->noParents == 1) {
			// no parents allowed
			$earlyExit = true;
		} else if(!count($template->parentTemplates)) {
			// no parent templates defined
			$earlyExit = true;
		}
		
		if($earlyExit) return $getAll ? $foundParents : null;

		$childTestPage = $checkAccess ? $pages->newPage($template) : null;

		foreach($template->parentTemplates as $parentTemplateID) {

			$parentTemplate = $this->get((int) $parentTemplateID);
		
			// if parent template does not exist or not allow children, skip it
			if(!$parentTemplate || $parentTemplate->noChildren) continue;

			// if the parent template doesn't have this as an allowed child template, skip it 
			if(!in_array($template->id, $parentTemplate->childTemplates)) continue;

			// sort=status ensures that a non-hidden page is given preference to a hidden page
			$include = $checkAccess ? "unpublished" : "all";
			$selector = "templates_id=$parentTemplate->id, include=$include, sort=status";
			
			if($maxStatus) {
				$selector .= ", status<$maxStatus";
			} else if(!$getAll && !$checkAccess) {
				$selector .= ", limit=2";
			}
			
			foreach($pages->find($selector) as $parentPage) {
				if($checkAccess && !$parentPage->addable($childTestPage)) continue;
				$foundParents->add($parentPage);
				$earlyExit = !$getAll && $foundParents->count() > 1;
				if($earlyExit) break;
			}
		
			if($earlyExit) break;
		}
		
		if($getAll) return $foundParents; // always returns PageArray (populated or not)
		
		$qty = $foundParents->count();
		if($qty > 1) return $pages->newNullPage(); // multiple possible parents
		if($qty === 1) return $foundParents->first(); // one possible parent
		
		return null; // no parents
	}

	/**
	 * Return all possible parent pages for the given template, if predefined
	 * 
	 * @param Template $template
	 * @param bool $checkAccess Specify true to exclude parent pages that user doesn't have access to add pages to (default=false)
	 * @param int $maxStatus Max allowed `Page::status*` constant (default=0 which means not applicable). Since 3.0.138
	 * @return PageArray
	 * 
	 */
	public function getParentPages(Template $template, $checkAccess = false, $maxStatus = 0) {
		$getAll = $maxStatus ? $maxStatus : true;
		return $this->getParentPage($template, $checkAccess, $getAll);
	}
	
	/**
	 * Get class name to use for pages using given Template
	 * 
	 * Note that value can be different from the `$template->pageClass` property, since it is determined at runtime.
	 * If it is different, then it is at least a class that extends the one defined by pageClass. 
	 *
	 * @param Template $template
	 * @param bool $withNamespace Include namespace? (default=true)
	 * @return string Returned class name includes namespace
	 * @since 3.0.152
	 *
	 */
	public function getPageClass(Template $template, $withNamespace = true) {

		if(isset($this->pageClassNames[$template->id])) {
			// use cached value when present
			$pageClass = $this->pageClassNames[$template->id];
			if(!$withNamespace) $pageClass = wireClassName($pageClass, false);
			return $pageClass;
		}

		$corePageClass = __NAMESPACE__ . "\\Page";
		$cacheable = true;
		
		// first check for class defined with Template 'pageClass' setting
		$pageClass = $template->pageClass;

		if($pageClass && $pageClass !== 'Page') {
			// page has custom class assignment in its template
			$nsPageClass = wireClassName($pageClass, true);
			// is this custom class available for instantiation?
			if(class_exists($nsPageClass)) {
				// class is available for use and has a namespace
				$pageClass = $nsPageClass;
			} else if(class_exists("\\$pageClass") && wireInstanceOf("\\$pageClass", $corePageClass)) {
				// class appears to be available in root namespace and it extends PW’s Page class (legacy)
				$pageClass = "\\$pageClass";
			} else {
				// class is not available for instantiation
				$this->warning(
					"Template '$template' page class '$pageClass' is not available", 
					Notice::debug | Notice::superuser | Notice::admin
				);
				$pageClass = '';
				// do not cache because maybe class will be available later
				$cacheable = false; 
			}
		}
	
		$config = $this->wire()->config;
		$usePageClasses = $config->usePageClasses;

		if(empty($pageClass) || $pageClass === 'Page') {
			// if no custom Page class available, use default Page class with namespace
			if($usePageClasses) {
				// custom classes enabled
				if(!isset($this->pageClassNames[0])) {
					// index 0 holds cached default page class
					$defaultPageClass = __NAMESPACE__ . "\\DefaultPage";
					if(!class_exists($defaultPageClass) || !wireInstanceOf($defaultPageClass, $corePageClass)) {
						$defaultPageClass = $corePageClass;
					}
					$this->pageClassNames[0] = $defaultPageClass;
				}
				$pageClass = $this->pageClassNames[0];
			} else {
				$pageClass = $corePageClass;
			}
		}

		// determine if custom class available (3.0.152+)
		if($usePageClasses) {
			$customPageClass = '';
			// repeaters support a field-name based name strategy
			/** @var RepeaterField $field */
			if(strpos($template->name, 'repeater_') === 0) {
				$field = $this->wire()->fields->get(ltrim(strstr($template->name, '_'), '_')); 
				if($field && wireInstanceOf($field->type, 'FieldtypeRepeater')) {
					$customPageClass = $field->type->getCustomPageClass($field);
				}
			}
			if($customPageClass) {
				$pageClass = $customPageClass;
			} else {
				// generate a CamelCase name + 'Page' from template name, i.e. 'blog-post' => 'BlogPostPage'
				$className = ucwords(str_replace(array('-', '_', '.'), ' ', $template->name));
				$className = __NAMESPACE__ . "\\" . str_replace(' ', '', $className) . 'Page';
				if(class_exists($className) && wireInstanceOf($className, $corePageClass)) {
					$pageClass = $className;
				}
			}
		}

		if($cacheable && $template->id) $this->pageClassNames[$template->id] = $pageClass;
		
		if(!$withNamespace) $pageClass = wireClassName($pageClass, false);

		return $pageClass;
	}

	/**
	 * Get all tags used by templates
	 * 
	 * @param bool $getTemplateNames Get arrays of template names for each tag? (default=false)
	 * @return array In return value both key and value are the tag
	 * @since 3.0.176 + hookable in 3.0.179
	 * 
	 */
	public function ___getTags($getTemplateNames = false) {
		$tags = array();
		foreach($this as $template) {
			/** @var Template $template */
			$templateTags = $template->tags;
			if(empty($templateTags)) continue;
			$templateTags = explode(' ', $templateTags);
			foreach($templateTags as $tag) {
				if(empty($tag)) continue;
				if($getTemplateNames) {
					if(!isset($tags[$tag])) $tags[$tag] = array();
					$tags[$tag][$template->name] = $template->name;
				} else {
					$tags[$tag] = $tag;
				}
			}
		}
		ksort($tags);
		return $tags;
	}


	/**
	 * Set a Permission for a Template for and specific Role
	 * 
	 * Note: you must also save() the template to commit the change. 
	 * 
	 * #pw-internal
	 *
	 * @param Template $template
	 * @param Permission|string|int $permission
	 * @param Role|string|int $role
	 * @param bool $revoke Specify true to revoke the permission, or omit to add the permission
	 * @param bool $test When true, no changes are made but return value still applicable
	 * @return bool True if an update was made (or would be made), false if not
	 * @throws WireException If given unknown Role or Permission
	 *
	 */
	public function setTemplatePermissionByRole(Template $template, $permission, $role, $revoke = false, $test = false) {

		if(!$template->useRoles) throw new WireException("Template $template does not have access control enabled"); 
		
		$defaultPermissions = array('page-view', 'page-edit', 'page-create', 'page-add');
		$updated = false;

		if(is_string($role) || is_int($role)) $role = $this->wire()->roles->get($role);
		if(!$role instanceof Role) throw new WireException("Unknown role for Template::setPermissionByRole");

		if(is_string($permission) && in_array($permission, $defaultPermissions)) {
			$permissionName = $permission;
		} else if($permission instanceof Permission) {
			$permissionName = $permission->name;
		} else {
			$permission = $this->wire()->permissions->get($permission);
			$permissionName = $permission ? $permission->name : '';
		}

		if(in_array($permissionName, $defaultPermissions)) {
			// use pre-defined view/edit/create/add roles
			$roles = $template->getRoles($permissionName);
			$has = $roles->has($role);
			if($revoke) {
				if($has) {
					if($test) return true;
					$roles->remove($role);
					$template->setRoles($roles, $permissionName);
					$updated = true;
				}
			} else if(!$has) {
				if($test) return true;
				$roles->add($role);
				$template->setRoles($roles, $permissionName);
				$updated = true; 
			}

		} else if($permission instanceof Permission) {
			$rolesPermissions = $template->get('rolesPermissions');
			if(!is_array($rolesPermissions)) $rolesPermissions = array();
			$rolePermissions = isset($rolesPermissions["$role->id"]) ? $rolesPermissions["$role->id"] : array();
			$_rolePermissions = $rolePermissions;
			if($revoke) {
				$key = array_search("$permission->id", $rolePermissions);
				if($key !== false) unset($rolePermissions[$key]);
				if(!in_array("-$permission->id", $rolePermissions)) $rolePermissions[] = "-$permission->id";
			} else {
				$key = array_search("-$permission->id", $rolePermissions);
				if($key !== false) unset($rolePermissions[$key]);
				if(!in_array("$permission->id", $rolePermissions)) $rolePermissions[] = "$permission->id";
			}
			if($rolePermissions !== $_rolePermissions) {
				if($test) return true;
				$rolesPermissions["$role->id"] = $rolePermissions;
				$template->set('rolesPermissions', $rolesPermissions);
				$updated = true;
			}

		} else {
			throw new WireException("Unknown permission for Templates::setPermissionByRole");
		}
		
		return $updated; 
	}

	/**
	 * Hook called when a Template detects that its file has changed
	 * 
	 * Note that the hook is not called until something in the system (like a page render) asks for the template’s filename.
	 * That’s because it would not be efficient for PW to check the file for every template in the system on every request. 
	 * 
	 * #pw-hooker
	 * 
	 * @param Template $template
	 * @since 3.0.141
	 * 
	 */
	public function ___fileModified(Template $template) {
		if(empty($this->fileModTemplates)) {
			// add hook on first call
			$this->addHookAfter('ProcessWire::finished', $this, '_hookFinished');
		}
		$this->fileModTemplates[$template->id] = $template;
	}
	
	/**
	 * Saves templates that had modified files to update 'modified' and 'ns' properties after the request is complete
	 *
	 * #pw-internal
	 *
	 * @param HookEvent $e
	 * @since 3.0.141
	 *
	 */
	public function _hookFinished(HookEvent $e) {
		foreach($this->fileModTemplates as /* $id => */ $template) {
			if($template->isChanged('modified') || $template->isChanged('ns')) {
				$template->save();
			}
		}
		$this->fileModTemplates = array();
	}

	/**
	 * FUTURE USE: Is the parent/child relationship allowed?
	 * 
	 * By default this method returns an associative array containing the following:
	 * 
	 *  - `allowed` (bool): Is the relationship allowed?
	 *  - `reasons` (array): Array of strings containing reasons why relationship is or is not allowed. 
	 * 
	 * If you specify the `false` for the `verbose` option then this method just returns a boolean.
	 *
	 * @param Template|Page $parent Parent Template or Page to test.
	 * @param Template|Page $child Child Template or Page to test.
	 * @param array $options Options to modify default behavior:
	 *  - `verbose` (bool): Return verbose array. When false, returns boolean rather than array (default=true). 
	 *  - `strict` (bool): Disallow relationships that do not match rules, even if relationship already exists (default=false).
	 *     Note that this option only applies if method is given Page objects rather than Template objects. 
	 * @return array|bool Returns associative array by default, or bool if the verbose option is false.
	 * @throws WireException if given invalid argument
	 *
	public function allowRelationship($parent, $child, array $options = array()) {
		
		$defaults = array(
			'verbose' => true, 
			'strict' => false, 
		);
	
		$options = array_merge($defaults, $options);
		$parentPage = null;
		$childPage = null;
		
		if($child instanceof Template) {
			$childTemplate = $child;
		} else if($child instanceof Page) {
			$childPage = $child;
			$childTemplate = $child->template;
		} else {
			throw new WireException('Invalid argument for child');
		}
		
		if($parent instanceof Template) {
			$parentTemplate = $parent;
		} else if($parent instanceof Page) {
			$parentPage = $parent;
			$parentTemplate = $parent->template;
		} else {
			throw new WireException('Invalid argument for parent');
		}

		$reasonsNo = array();
		$reasonsYes = array();
		$isAlreadyParent = $parentPage && $childPage && $childPage->parent_id == $parentPage->id;
		$isAlreadyParentNote = "parent/child allowed because relationship already exists";
		
		if($isAlreadyParent) {
			if($options['strict']) {
				// in strict mode, existing relationships are ignored and we stick only to the rules
				$isAlreadyParent = false;
			} else {
				$reasonsYes[] = "Given child page ($childPage) already has this parent ($parentPage)";
			}
		}

		if($parentTemplate->noChildren) {
			$reason = "Parent template “$parentTemplate” specifies “no children”";
			if($isAlreadyParent) {
				$reasonsYes[] = "$reason - $isAlreadyParentNote";
			} else {
				$reasonsNo[] = $reason;
			}
		}
		
		if($childTemplate->noMove) {
			$reason = "Child template “$childTemplate” specifies “no move”";
			if($isAlreadyParent) {
				$reasonsYes[] = "$reason - $isAlreadyParentNote";
			} else {
				$reasonsNo[] = $reason;
			}
		}
		
		if($childTemplate->noParents > 0) {
			$reason = "Child template “$childTemplate” specifies “no parents” option";
			if($isAlreadyParent) {
				$reasonsYes[] = "$reason - $isAlreadyParentNote";
			} else {
				$reasonsNo[] = $reason;
			}
		} 
		
		if(count($parentTemplate->childTemplates)) { 
			if(in_array($childTemplate->id, $parentTemplate->childTemplates)) {
				$reasonsYes[] = "Parent template “$parentTemplate” specifically allows children of “$childTemplate”";
			} else {
				$reasonsNo[] = "Parent template “$parentTemplate” does not allow children using template “$childTemplate”";
			}
		} 
		
		if(count($childTemplate->parentTemplates)) { 
			if(in_array($parentTemplate->id, $childTemplate->parentTemplates)) {
				$reasonsYes[] = "Child template “$childTemplate” specifically allows parents using template “$parentTemplate”";
			} else {
				$reasonsNo[] = "Child template “$childTemplate” does not allow parents using template “$parentTemplate”";
			}
		}

		$allowed = count($reasonsNo) ? false : true;
		
		if($options['verbose']) {
			return array(
				'allowed' => $allowed,
				'reasons' => $allowed ? $reasonsYes : $reasonsNo, 
			);
		}
		
		return $allowed; 
	}
	 */
	
}
