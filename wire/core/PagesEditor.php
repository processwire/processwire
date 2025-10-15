<?php namespace ProcessWire;

/**
 * ProcessWire Pages Editor
 * 
 * #pw-headline Pages Editor
 * #pw-var $pages->editor
 * #pw-order-groups add,save,delete,info,order
 * #pw-breadcrumb Pages
 * #pw-summary Implements page editing and manipulation methods for the $pages API variable.
 * #pw-body =
 * Please always use `$pages->method()` rather than `$pages->editor->method()` in cases where there is overlap. 
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 * 
 */ 

class PagesEditor extends Wire {

	/**
	 * Are we currently cloning a page?
	 *
	 * This is greater than 0 only when the clone() method is currently in progress.
	 *
	 * @var int
	 *
	 */
	protected $cloning = 0;
	
	/**
	 * @var Pages
	 * 
	 */
	protected $pages;
	
	/**
	 * Construct
	 * 
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;

		$config = $pages->wire()->config;
		if($config->dbStripMB4 && strtolower($config->dbCharset) != 'utf8mb4') {
			$this->addHookAfter('Fieldtype::sleepValue', $this, 'hookFieldtypeSleepValueStripMB4');
		}
	}

	/**
	 * Are we currently in a page clone?
	 * 
	 * #pw-group-info
	 * 
	 * @param bool $getDepth Get depth (int) rather than state (bool)?
	 * @return bool|int
	 * 
	 */
	public function isCloning($getDepth = false) {
		return $getDepth ? $this->cloning : $this->cloning > 0;
	}
	
	/**
	 * Add a new page using the given template to the given parent
	 *
	 * If no name is specified one will be assigned based on the current timestamp.
	 * 
	 * #pw-group-add
	 *
	 * @param string|Template $template Template name or Template object
	 * @param string|int|Page $parent Parent path, ID or Page object
	 * @param string $name Optional name or title of page. If none provided, one will be automatically assigned based on microtime stamp.
	 * 	If you want to specify a different name and title then specify the $name argument, and $values['title'].
	 * @param array $values Field values to assign to page (optional). If $name is omitted, this may also be 3rd param.
	 * @return Page Returned page has output formatting off.
	 * @throws WireException When some criteria prevents the page from being saved.
	 *
	 */
	public function add($template, $parent, $name = '', array $values = array()) {

		// the $values may optionally be the 3rd argument
		if(is_array($name)) {
			$values = $name;
			$name = isset($values['name']) ? $values['name'] : '';
		}

		if(!is_object($template)) {
			$template = $this->wire()->templates->get($template);
			if(!$template) throw new WireException("Unknown template");
		}

		$options = array('template' => $template, 'parent' => $parent);
		if(isset($values['pageClass'])) {
			$options['pageClass'] = $values['pageClass'];
			unset($values['pageClass']);
		}
		$page = $this->pages->newPage($options); 

		$exceptionMessage = "Unable to add new page using template '$template' and parent '{$page->parent->path}'.";

		if(empty($values['title'])) {
			// no title provided in $values, so we assume $name is $title
			// but if no name is provided, then we default to: Untitled Page
			if(!strlen($name)) $name = $this->_('Untitled Page');
			// the setupNew method will convert $page->title to a unique $page->name
			$page->title = $name;

		} else {
			// title was provided
			$page->title = $values['title'];
			// if name is provided we use it
			// otherwise setupNew will take care of assign it from title
			if(strlen($name)) $page->name = $name;
			unset($values['title']);
		}

		if(isset($values['status'])) {
			$page->status = $values['status'];
			unset($values['status']);
		}

		// save page before setting $values just in case any fieldtypes
		// require the page to have an ID already (like file-based)
		if(!$this->pages->save($page)) throw new WireException($exceptionMessage);

		// set field values, if provided
		if(!empty($values)) {
			unset($values['id'], $values['parent'], $values['template']); // fields that may not be set from this array
			foreach($values as $key => $value) $page->set($key, $value);
			$this->pages->save($page);
		}
		
		// get a fresh copy of the page
		if($page->id) {
			$inserted = $page->_inserted;
			$of = $this->pages->outputFormatting;
			if($of) $this->pages->setOutputFormatting(false);
			$p = $this->pages->getById($page->id, $template, $page->parent_id);
			if($p->id) $page = $p;
			if($of) $this->pages->setOutputFormatting(true);
			$page->setQuietly('_inserted', $inserted);
		}

		return $page;
	}
	
	/**
	 * Is the given page in a state where it can be saved from the API?
	 * 
	 * #pw-group-info
	 *
	 * @param Page $page
	 * @param string $reason Text containing the reason why it can't be saved (assuming it's not saveable)
	 * @param string|Field $fieldName Optional fieldname to limit check to.
	 * @param array $options Options array given to the original save method (optional)
	 * @return bool True if saveable, False if not
	 *
	 */
	public function isSaveable(Page $page, &$reason, $fieldName = '', array $options = array()) {

		$saveable = false;
		$outputFormattingReason = "Call \$page->of(false); before getting/setting values that will be modified and saved.";
		$corrupted = array();

		if($fieldName && is_object($fieldName)) {
			/** @var Field $fieldName */
			$fieldName = $fieldName->name;
			/** @var string $fieldName */
		}

		if($page->hasStatus(Page::statusCorrupted)) {
			$corruptedFields = $page->_statusCorruptedFields;
			foreach($page->getChanges() as $change) {
				if(isset($corruptedFields[$change])) $corrupted[] = $change;
			}
			// if focused on a specific field... 
			if($fieldName && !in_array($fieldName, $corrupted)) $corrupted = array();
		}

		if($page instanceof NullPage) {
			$reason = "Pages of type NullPage are not saveable";
		} else if(!$page->parent_id && $page->id !== 1 && (!$page->parent || $page->parent instanceof NullPage)) {
			$reason = "It has no parent assigned";
		} else if(!$page->template) {
			$reason = "It has no template assigned";
		} else if(!strlen(trim($page->name)) && $page->id != 1) {
			$reason = "It has an empty 'name' field";
		} else if(count($corrupted)) {
			$reason = $outputFormattingReason . " [Page::statusCorrupted] fields: " . implode(', ', $corrupted);
		} else if($page->id == 1 && !$page->template->useRoles) {
			$reason = "Selected homepage template cannot be used because it does not define access.";
		} else if($page->id == 1 && !$page->template->hasRole('guest')) {
			$reason = "Selected homepage template cannot be used because it does not have required 'guest' role in its access settings.";
		} else {
			$saveable = true;
		}

		// check if they could corrupt a field by saving
		if($saveable && $page->outputFormatting) {
			// iternate through recorded changes to see if any custom fields involved
			foreach($page->getChanges() as $change) {
				if($fieldName && $change != $fieldName) continue;
				if($page->template->fieldgroup->getField($change) !== null) {
					$reason = $outputFormattingReason . " [$change]";
					$saveable = false;
					break;
				}
			}
			// iterate through already-loaded data to see if any are objects that have changed
			if($saveable) foreach($page->getArray() as $key => $value) {
				if($fieldName && $key != $fieldName) continue;
				if(!$page->template->fieldgroup->getField($key)) continue;
				if($value instanceof Wire && $value->isChanged()) {
					$reason = $outputFormattingReason . " [$key]";
					$saveable = false;
					break;
				}
			}
		}

		// check for a parent change and whether it is allowed
		if($saveable && $page->id && $page->parentPrevious && empty($options['ignoreFamily'])) {
			// parent has changed, check that the move is allowed
			$saveable = $this->isMoveable($page, $page->parentPrevious, $page->parent, $reason); 
		}
		
		return $saveable;
	}

	/**
	 * Return whether given Page is moveable from $oldParent to $newParent
	 * 
	 * #pw-group-info
	 * 
	 * @param Page $page Page to move
	 * @param Page $oldParent Current/old parent page
	 * @param Page $newParent New requested parent page
	 * @param string $reason Populated with reason why page is not moveable, if return value is false. 
	 * @return bool
	 * 
	 */
	public function isMoveable(Page $page, Page $oldParent, Page $newParent, &$reason) {
		
		if($oldParent->id == $newParent->id) return true; 
		
		$config = $this->wire()->config;
		$moveable = false;
		$isSystem = $page->hasStatus(Page::statusSystem) || $page->hasStatus(Page::statusSystemID);
		$toTrash = $newParent->id > 0 && $newParent->isTrash();
		$wasTrash = $oldParent->id > 0 && $oldParent->isTrash();
		
		// page was moved
		if($page->template->noMove && ($isSystem || (!$toTrash && !$wasTrash))) {
			// make sure the page template allows moves.
			// only move always allowed is to the trash (or out of it), unless page has system status
			$reason = 
				sprintf($this->_('Page using template “%s” is not moveable.'), $page->template->name) . ' ' . 
				"(Template::noMove) [{$oldParent->path} => {$newParent->path}]";

		} else if($newParent->template->noChildren) {
			// check if new parent disallows children
			$reason = sprintf(
				$this->_('Chosen parent “%1$s” uses template “%2$s” that does not allow children.'), 
				$newParent->path, 
				$newParent->template->name
			);

		} else if($newParent->id && $newParent->id != $config->trashPageID && count($newParent->template->childTemplates)
			&& !in_array($page->template->id, $newParent->template->childTemplates)) {
			// make sure the new parent's template allows pages with this template
			$reason = sprintf(
				$this->_('Cannot move “%1$s” because template “%2$s” used by page “%3$s” does not allow children using template “%4$s”.'), 
				$page->name, 
				$newParent->template->name, 
				$newParent->path,
				$page->template->name
			);

		} else if(count($page->template->parentTemplates) && $newParent->id != $config->trashPageID
			&& !in_array($newParent->template->id, $page->template->parentTemplates)) {
			// check for allowed parentTemplates setting
			$reason = sprintf(
				$this->_('Cannot move “%1$s” because template “%2$s” used by new parent “%3$s” is not allowed by moved page template “%4$s”.'),
				$page->name, 
				$newParent->template->name, 
				$newParent->path, 
				$page->template->name
			);

		} else if(count($newParent->children("name=$page->name, id!=$page->id, include=all"))) {
			// check for page name collision
			$reason = sprintf(
				$this->_('Chosen parent “%1$s” already has a page named “%2$s”.'),
				$newParent->path,
				$page->name
			);
			
		} else {
			$moveable = true;
		}
		
		return $moveable;
	}
	
	/**
	 * Is the given page deleteable from the API?
	 *
	 * Note: this does not account for user permission checking.
	 * It only checks if the page is in a state to be deleteable via the API.
	 * 
	 * #pw-group-info
	 *
	 * @param Page $page
	 * @param bool $throw Throw WireException with additional details? 
	 * @return bool True if deleteable, False if not
	 * @throws WireException If requested to do so via $throw argument
	 *
	 */
	public function isDeleteable(Page $page, $throw = false) {

		$error = false;

		if($page instanceof NullPage) {
			$error = "it is a NullPage";
		} else if(!$page->id) {
			$error = "it has no id";
		} else if($page->hasStatus(Page::statusSystemID) || $page->hasStatus(Page::statusSystem)) {
			$error = "it has “system” and/or “systemID” status";
		} else if($page->hasStatus(Page::statusLocked)) {
			$error = "it has “locked” status";
		} else if($page->id === $this->wire()->page->id && $this->wire()->config->installedAfter('2019-04-04')) {
			$error = "it is the current page being viewed, try \$pages->trash() instead";
		}
	
		if($error === false) return true;
		if($throw) throw new WireException("Page $page->path ($page->id) cannot be deleted: $error"); 

		return false;
	}
	
	/**
	 * Auto-populate some fields for a new page that does not yet exist
	 *
	 * Currently it does this:
	 * 
	 * - Assigns a parent if one is not already assigned.
	 * - Sets up a unique page->name based on the format or title if one isn't provided already.
	 * - Assigns a sort value.
	 * - Populates any default values for fields. 
	 * 
	 * #pw-group-add
	 *
	 * @param Page $page
	 * @throws \Exception|WireException|\PDOException if failure occurs while in DB transaction
	 *
	 */
	public function setupNew(Page $page) {

		$parent = $page->parent();

		//  assign parent
		if(!$parent->id) {
			$parentTemplates = $page->template->parentTemplates;
			$parent = null;

			if(!empty($parentTemplates)) {
				$idStr = implode('|', $parentTemplates);
				$parent = $this->pages->get("include=hidden, template=$idStr");
				if(!$parent->id) $parent = $this->pages->get("include=all, template=$idStr");
			}

			if($parent && $parent->id) $page->parent = $parent;
		}

		// assign page name
		if(!strlen($page->name)) {
			$this->pages->setupPageName($page); // call through $pages intended, so it can be hooked
		}

		// assign sort order
		if($page->sort < 0) {
			$page->sort = ($parent->id ? $parent->numChildren() : 0);
		}

		// assign any default values for fields
		foreach($page->template->fieldgroup as $field) {
			/** @var Field $field */
			if($page->isLoaded($field->name)) continue; // value already set
			if(!$page->hasField($field)) continue; // field not valid for page
			if(!strlen((string) $field->get('defaultValue'))) continue; // no defaultValue property defined with Fieldtype config inputfields
			try {
				$blankValue = $field->type->getBlankValue($page, $field);
				if(is_object($blankValue) || is_array($blankValue)) continue; // we don't currently handle complex types
				$defaultValue = $field->type->getDefaultValue($page, $field);
				if(is_object($defaultValue) || is_array($defaultValue)) continue; // we don't currently handle complex types
				if("$blankValue" !== "$defaultValue") {
					$page->set($field->name, $defaultValue);
				}
			} catch(\Exception $e) {
				$this->trackException($e, false, true);
				if($this->wire()->database->inTransaction()) throw $e;
			}
		}
	}
	
	/**
	 * Auto-assign a page name to gven page
	 *
	 * Typically this would be used only if page had no name or if it had a temporary untitled name.
	 *
	 * Page will be populated with the name given. This method will not populate names to pages that
	 * already have a name, unless the name is "untitled"
	 * 
	 * #pw-group-add
	 *
	 * @param Page $page
	 * @param array $options
	 * 	- format: Optionally specify the format to use, or leave blank to auto-determine.
	 * @return string If a name was generated it is returned. If no name was generated blank is returned.
	 *
	 */
	public function setupPageName(Page $page, array $options = array()) {
		return $this->pages->names()->setupNewPageName($page, isset($options['format']) ? $options['format'] : '');
	}
	
	/**
	 * Save a page object and it's fields to database.
	 *
	 * If the page is new, it will be inserted. If existing, it will be updated.
	 *
	 * This is the same as calling $page->save()
	 *
	 * If you want to just save a particular field in a Page, use $page->save($fieldName) instead.
	 * 
	 * #pw-group-save
	 *
	 * @param Page $page Page to save
	 * @param array $options Optional array with the following optional elements:
	 * 	- `uncacheAll` (bool): Whether the memory cache should be cleared (default=true)
	 * 	- `resetTrackChanges` (bool): Whether the page's change tracking should be reset (default=true)
	 * 	- `quiet` (bool): When true, created/modified time+user will use values from $page rather than current user+time (default=false)
	 *	- `adjustName` (bool): Adjust page name to ensure it is unique within its parent (default=true)
	 * 	- `forceID` (integer): Use this ID instead of an auto-assigned on (new page) or current ID (existing page)
	 * 	- `ignoreFamily` (bool): Bypass check of allowed family/parent settings when saving (default=false)
	 *  - `noHooks` (bool): Prevent before/after save hooks from being called (default=false)
	 *  - `noFields` (bool): Bypass saving of custom fields (default=false)
	 *  - `caller` (string): Optional name of calling function (i.e. 'pages.trash'), for internal use (default='') 3.0.235+
	 *  - `callback` (string|callable): Hook method name from $pages or callable to trigger after save. 
	 *     It receives a single $page argument. For internal use. (default='') 3.0.235+
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function save(Page $page, $options = array()) {

		$defaultOptions = array(
			'uncacheAll' => true,
			'resetTrackChanges' => true,
			'adjustName' => true,
			'forceID' => 0,
			'ignoreFamily' => false,
			'noHooks' => false, 
			'noFields' => false, 
			'caller' => '', 
			'callback' => '',
		);

		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		$options = array_merge($defaultOptions, $options);
		$user = $this->wire()->user;
		$languages = $this->wire()->languages;
		$language = null;
		$parentPrevious = $page->parentPrevious;
		$caller = $options['caller'];
		$callback = $options['callback'];
		$useHooks = empty($options['noHooks']);

		// if language support active, switch to default language so that saved fields and hooks don't need to be aware of language
		if($languages && $page->id != $user->id && "$user->language") {
			$language = $user->language;
			$user->setLanguage($languages->getDefault());
		}

		$reason = '';
		$isNew = $page->isNew();
		if($isNew) $this->pages->setupNew($page);

		if(!$this->isSaveable($page, $reason, '', $options)) {
			if($language) $user->setLanguage($language);
			throw new WireException(rtrim("Can’t save page (id=$page->id): $page->path", ": ") . ": $reason");
		}

		if($page->hasStatus(Page::statusUnpublished) && $page->template->noUnpublish) {
			$page->removeStatus(Page::statusUnpublished);
		}

		if($parentPrevious && !$isNew) {
			if($useHooks) $this->pages->moveReady($page);
			if($caller !== 'pages.trash' && $caller !== 'pages.restore') {
				if($page->isTrash() && !$parentPrevious->isTrash()) {
					if($this->pages->trash($page, false)) $callback = 'trashed';
				} else if($parentPrevious->isTrash() && !$page->parent->isTrash()) {
					if($this->pages->restore($page, false)) $callback = 'restored';
				}
			}
		}

		if($options['adjustName'] && !$page->get('_hasUniqueName')) {
			$this->pages->names()->checkNameConflicts($page);
		}
		
		if($page->namePrevious && !$isNew && $page->namePrevious != $page->name) {
			if($useHooks) $this->pages->renameReady($page);
		}

		$result = $this->savePageQuery($page, $options);
		if($result) $result = $this->savePageFinish($page, $isNew, $options);
		if($language) $user->setLanguage($language); // restore language
		
		if($result && !empty($callback) && $useHooks) {
			if(is_string($callback) && ctype_alnum($callback)) {
				$this->pages->$callback($page); // hook method name in $pages
			} else if(is_callable($callback)) {
				$callback($page); // user defined callback
			}
		}
		
		return $result;
	}

	/**
	 * Execute query to save to pages table
	 *
	 * triggers hooks: addReady, saveReady, statusChangeReady (when status changed)
	 *
	 * @param Page $page
	 * @param array $options
	 * @return bool
	 * @throws WireException|\Exception
	 *
	 */
	protected function savePageQuery(Page $page, array $options) {

		$isNew = $page->isNew();
		$database = $this->wire()->database;
		$sanitizer = $this->wire()->sanitizer;
		$config = $this->wire()->config;
		$user = $this->wire()->user;
		$userID = $user ? $user->id : $config->superUserPageID;
		$systemVersion = $config->systemVersion;
		$sql = '';
		
		if(!$page->created_users_id) $page->created_users_id = $userID;
		
		if($page->isChanged('status') && empty($options['noHooks'])) {
			$this->pages->statusChangeReady($page);
		}
		
		if(empty($options['noHooks'])) {
			$extraData = $this->pages->saveReady($page); 
			$this->pages->savePageOrFieldReady($page);
			if($isNew) $this->pages->addReady($page);
		} else {
			$extraData = array();
		}

		if($this->pages->names()->isUntitledPageName($page->name)) {
			$this->pages->setupPageName($page);
		}

		$data = array(
			'parent_id' => (int) $page->parent_id,
			'templates_id' => (int) $page->template->id,
			'name' => $sanitizer->pageName($page->name, Sanitizer::toAscii),
			'status' => (int) $page->status,
			'sort' =>  ($page->sort > -1 ? (int) $page->sort : 0)
		);

		if(is_array($extraData)) foreach($extraData as $column => $value) {
			$column = $database->escapeCol($column);
			$data[$column] = (strtoupper($value) === 'NULL' ? NULL : $value);
		}

		if($isNew) {
			if($page->id) $data['id'] = (int) $page->id;
			$data['created_users_id'] = (int) $userID;
		}

		if($options['forceID']) $data['id'] = (int) $options['forceID'];

		if($page->template->allowChangeUser) {
			$data['created_users_id'] = (int) $page->created_users_id;
		}

		if(empty($options['quiet'])) {
			$sql = 'modified=NOW()';
			$data['modified_users_id'] = (int) $userID;
		} else {
			// quiet option, use existing values already populated to page, when present
			$data['modified_users_id'] = (int) ($page->modified_users_id ? $page->modified_users_id : $userID);
			$data['created_users_id'] = (int) ($page->created_users_id ? $page->created_users_id : $userID);
			if($page->modified > 0) {
				$data['modified'] = date('Y-m-d H:i:s', $page->modified);
			} else if($isNew) {
				$sql = 'modified=NOW()';
			}
			if($page->created > 0) {
				$data['created'] = date('Y-m-d H:i:s', $page->created);
			}
		}

		$page->modified_users_id = $data['modified_users_id'];
		if(isset($data['created_users_id'])) $page->created_users_id = $data['created_users_id'];

		if(!$page->isUnpublished() && ($isNew || ($page->statusPrevious && ($page->statusPrevious & Page::statusUnpublished)))) {
			// page is being published
			if($systemVersion >= 12) {
				$sql .= ($sql ? ', ' : '') . 'published=NOW()';
			}
		}

		foreach($data as $column => $value) {
			$sql .= ", $column=" . (is_null($value) ? "NULL" : ":$column");
		}

		$sql = trim($sql, ", ");

		if($isNew) { 
			if(empty($data['created'])) $sql .= ', created=NOW()';
			$query = $database->prepare("INSERT INTO pages SET $sql");
		}  else {
			$query = $database->prepare("UPDATE pages SET $sql WHERE id=:page_id");
			$query->bindValue(":page_id", (int) $page->id, \PDO::PARAM_INT);
		}

		foreach($data as $column => $value) {
			if(is_null($value)) continue; // already bound above
			$query->bindValue(":$column", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
		}

		$tries = 0;
		$maxTries = 100;

		do {
			$result = false;
			$keepTrying = false;
			try {
				$result = $database->execute($query);
			} catch(\Exception $e) {
				$keepTrying = $this->savePageQueryException($page, $query, $e, $options);
				if(!$keepTrying) throw $e;
			}
		} while($keepTrying && (++$tries < $maxTries));

		if($result && ($isNew || !$page->id)) {
			$page->id = (int) $database->lastInsertId();
			$page->setQuietly('_inserted', time());
		}
		
		if($options['forceID']) $page->id = (int) $options['forceID'];

		return $result;
	}

	/**
	 * Handle Exception for savePageQuery()
	 * 
	 * While setupNew() already attempts to uniqify a page name with an incrementing
	 * number, there is a chance that two processes running at once might end up with
	 * the same number, so we account for the possibility here by re-trying queries
	 * that trigger duplicate-entry exceptions.
	 * 
	 * Example of actual exception text, for reference:
	 * Integrity constraint violation: 1062 Duplicate entry 'background-3552' for key 'name3894_parent_id'
	 * 
	 * @param Page $page
	 * @param \PDOStatement $query
	 * @param \PDOException|\Exception $exception
	 * @param array $options
	 * @return bool True if it should give $query another shot, false if not
	 * 
	 */
	protected function savePageQueryException(Page $page, $query, $exception, array $options) {
		
		$errorCode = $exception->getCode();
		
		// 23000=integrity constraint violation, duplicate entry
		if($errorCode != 23000) return false; 
		
		if(!$this->pages->names()->hasAutogenName($page) && !$options['adjustName']) return false;
		
		$languages = $this->wire()->languages;
		$sanitizer = $this->wire()->sanitizer;

		// account for the duplicate possibly being a multi-language name field
		// i.e. “Duplicate entry 'bienvenido-2-1001' for key 'name1013_parent_id'”
		if($languages && preg_match('/\b(name\d*)_parent_id\b/', $exception->getMessage(), $matches)) {
			$nameField = $matches[1];
		} else {
			$nameField = 'name';
		}
		
		// get either 'name' or 'name123' (where 123 is language ID)
		$pageName = $page->get($nameField);
		$pageName = $this->pages->names()->incrementName($pageName);
		$page->set($nameField, $pageName);
		$query->bindValue(":$nameField", $sanitizer->pageName($pageName, Sanitizer::toAscii));
		
		// indicate that page has a modified name 
		$this->pages->names()->hasAdjustedName($page, true);
		
		return true;
	}

	/**
	 * Save individual Page fields and supporting actions
	 *
	 * triggers hooks: saved, added, moved, renamed, templateChanged
	 *
	 * @param Page $page
	 * @param bool $isNew
	 * @param array $options
	 * @return bool
	 * @throws \Exception|WireException|\PDOException If any field-saving failure occurs while in a DB transaction
	 *
	 */
	protected function savePageFinish(Page $page, $isNew, array $options) {
		
		$changes = $page->getChanges(2);
		$changesValues = $page->getChanges(true);

		// update children counts for current/previous parent
		if($isNew) {
			// new page
			$page->parent->numChildren++;
			
		} else if($page->parentPrevious && $page->parentPrevious->id != $page->parent->id) {
			// parent changed
			$page->parentPrevious->numChildren--;
			$page->parent->numChildren++;
		}
	
		// save any needed updates to pages_parents table
		$this->pages->parents()->save($page);
		
		// if page hasn't changed, don't continue further
		if(!$page->isChanged() && !$isNew) {
			$this->pages->debugLog('save', '[not-changed]', true);
			if(empty($options['noHooks'])) {
				$this->pages->saved($page, array());
				$this->pages->savedPageOrField($page, array());
			}
			return true;
		}

		// if page has a files path (or might have previously), trigger filesManager's save
		if(PagefilesManager::hasPath($page)) $page->filesManager->save();

		// disable outputFormatting and save state
		$of = $page->of();
		$page->of(false);

		// when a page is statusCorrupted, it records what fields are corrupted in _statusCorruptedFields array
		$corruptedFields = $page->hasStatus(Page::statusCorrupted) ? $page->_statusCorruptedFields : array();

		// save each individual Fieldtype data in the fields_* tables
		foreach($page->fieldgroup as $field) {
			/** @var Field $field */
			$fieldtype = $field->type;
			$name = $field->name;
			if($options['noFields'] || isset($corruptedFields[$name]) || !$fieldtype || !$page->hasField($field)) {
				unset($changes[$name]);
				unset($changesValues[$name]); 
			} else {
				try {
					$fieldtype->savePageField($page, $field);
				} catch(\Exception $e) {
					$label = $field->getLabel();
					$message = $e->getMessage();
					if(strpos($message, $label) !== false) $label = $name;
					$error = sprintf($this->_('Error saving field "%s"'), $label) . ' — ' . $message;
					$this->trackException($e, true, $error);
					if($this->wire()->database->inTransaction()) throw $e;
				}
			}
		}

		// return outputFormatting state
		$page->of($of);

		// sortfield for children
		$templateSortfield = $page->template->sortfield;
		if(empty($templateSortfield)) $this->pages->sortfields()->save($page);
		
		if($options['resetTrackChanges']) {
			if($options['noFields']) {
				// reset for only fields that were saved
				foreach($changes as $change) $page->untrackChange($change);
				$page->setTrackChanges(true);
			} else {
				// reset all changes
				$page->resetTrackChanges();
			}
		}

		// determine whether we'll trigger the added() hook
		if($isNew) {
			$page->setIsNew(false);
			$triggerAddedPage = $page;
		} else {
			$triggerAddedPage = null;
		}

		// check for template changes
		if($page->templatePrevious && $page->templatePrevious->id != $page->template->id) {
			// the template was changed, so we may have data in the DB that is no longer applicable
			// find unused data and delete it
			foreach($page->templatePrevious->fieldgroup as $field) {
				if($page->hasField($field)) continue;
				$field->type->deletePageField($page, $field);
				$this->message("Deleted field '$field' on page {$page->url}", Notice::debug);
			}
		}

		if($options['uncacheAll']) $this->pages->uncacheAll($page);

		// determine whether the pages_access table needs to be updated so that pages->find()
		// operations can be access controlled. 
		if($isNew || $page->parentPrevious || $page->templatePrevious) $this->wire(new PagesAccess($page));

		// trigger hooks
		if(empty($options['noHooks'])) {
			$this->pages->saved($page, $changes, $changesValues);
			$this->pages->savedPageOrField($page, $changes);
			if($triggerAddedPage) $this->pages->added($triggerAddedPage);
			if($page->namePrevious && $page->namePrevious != $page->name) $this->pages->renamed($page);
			if($page->parentPrevious) $this->pages->moved($page);
			if($page->templatePrevious) $this->pages->templateChanged($page);
			if(in_array('status', $changes)) $this->pages->statusChanged($page);
		}
		
		if($triggerAddedPage && $page->rootParent()->id === $this->wire()->config->trashPageID) {
			// new page created directly in trash, not a great way to start but that's how it is
			$this->savePageStatus($page, Page::statusTrash);
		}

		$this->pages->debugLog('save', $page, true);

		return true;
	}
	
	/**
	 * TBD Identify if parent changed and call saveParentsTable() where appropriate
	 *
	 * @param Page $page Page to save parent(s) for
	 * @param bool $isNew If page is newly created during this save this should be true, otherwise false
	 *
	protected function savePageParent(Page $page, $isNew) {
		
		if($page->parentPrevious || $page->_forceSaveParents || $isNew) {
			$this->pages->parents()->rebuild($page);
		}
		
		// saveParentsTable option is always true unless manually disabled from a hook
		if($page->parentPrevious && !$isNew && $page->numChildren > 0) {
			// existing page was moved and it has children
			if($page->parent->numChildren == 1) {
				// first child of new parent
				$this->pages->parents()->rebuildPage($page->parent);
			} else {
				$this->pages->parents()->rebuildPage($page);
			}

		} else if(($page->parentPrevious && $page->parent->numChildren == 1) ||
			($isNew && $page->parent->numChildren == 1) ||
			($page->_forceSaveParents)) {
			// page is moved and is the first child of its new parent
			// OR page is NEW and is the first child of its parent
			// OR $page->_forceSaveParents is set (debug/debug, can be removed later)
			$this->pages->parents()->rebuildPage($page->parent);

		} else if($page->parentPrevious && $page->parent->numChildren > 1 && $page->parent->parent_id > 1) {
			$this->pages->parents()->rebuildPage($page->parent->parent);
		}

		if($page->parentPrevious && $page->parentPrevious->numChildren == 0) {
			// $page was moved and its previous parent is now left with no children, this ensures the old entries get deleted
			$this->pages->parents()->rebuild($page->parentPrevious->id);
		}
	}
	 */
	
	/**
	 * Save just a field from the given page 
	 * 
	 * This is the method used by by the `$page->save($field)` method. 
	 *
	 * This function is public, but the preferred manner to call it is with `$page->save($field)`
	 * 
	 * #pw-group-save
	 *
	 * @param Page $page
	 * @param string|Field $field Field object or name (string)
	 * @param array|string $options Specify options: 
	 *  - `quiet` (bool): Specify true to bypass updating of modified_users_id and modified time (default=false). 
	 *  - `noHooks` (bool): Specify true to bypass calling of before/after save hooks (default=false). 
	 * @return bool True on success
	 * @throws WireException
	 * @see Page::save()
	 *
	 */
	public function saveField(Page $page, $field, $options = array()) {

		$reason = '';
		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		
		if($page->isNew()) {
			throw new WireException("Can't save field from a new page - please save the entire page first");
		}
		
		if(!$this->isSaveable($page, $reason, $field, $options)) {
			throw new WireException("Can't save field from page {$page->id}: {$page->path}: $reason");
		}
		
		if($field && (is_string($field) || is_int($field))) {
			$field = $this->wire()->fields->get($field);
		}
		
		if(!$field instanceof Field) {
			throw new WireException("Unknown field supplied to saveField for page {$page->id}");
		}
		
		if(!$page->fieldgroup->hasField($field)) {
			throw new WireException("Page {$page->id} does not have field {$field->name}");
		}

		$value = $page->get($field->name);
		if($value instanceof Pagefiles || $value instanceof Pagefile) $page->filesManager()->save();
		$page->trackChange($field->name);

		if(empty($options['noHooks'])) {
			$this->pages->saveFieldReady($page, $field);
			$this->pages->savePageOrFieldReady($page, $field->name);
		}
		
		if($field->type->savePageField($page, $field)) {
			// if page has a files path (or might have previously), trigger filesManager's save
			if(PagefilesManager::hasPath($page)) $page->filesManager->save();
			$page->untrackChange($field->name);
			if(empty($options['quiet'])) {
				$user = $this->wire()->user;
				$userID = (int) ($user ? $user->id : $this->wire()->config->superUserPageID);
				$database = $this->wire()->database;
				$query = $database->prepare("UPDATE pages SET modified_users_id=:userID, modified=NOW() WHERE id=:pageID");
				$query->bindValue(':userID', $userID, \PDO::PARAM_INT);
				$query->bindValue(':pageID', $page->id, \PDO::PARAM_INT);
				$database->execute($query);
			}
			$return = true;
			if(empty($options['noHooks'])) {
				$this->pages->savedField($page, $field);
				$this->pages->savedPageOrField($page, array($field->name));
			}
		} else {
			$return = false;
		}

		$this->pages->debugLog('saveField', "$page:$field", $return);
		
		return $return;
	}

	/**
	 * Save multiple named fields from given page 
	 * 
	 * ~~~~~
	 * // you can specify field names as array…
	 * $a = $pages->saveFields($page, [ 'title', 'body', 'summary' ]);
	 * 
	 * // …or a CSV string of field names:
	 * $a = $pages->saveFields($page, 'title, body, summary');
	 *
	 * // return value is array of saved field/property names 
	 * print_r($a); // outputs: array( 'title', 'body', 'summary' )
	 * ~~~~~
	 * 
	 * #pw-group-save
	 * 
	 * @param Page $page Page to save
	 * @param array|string|string[]|Field[] $fields Array of field names to save or CSV/space separated field names to save.
	 *   These should only be Field names and not native page property names.
	 * @param array|string $options Optionally specify one or more of the following to modify default behavior:
	 *  - `quiet` (bool): Specify true to bypass updating of modified user and time (default=false).
	 *  - `noHooks` (bool): Prevent before/after save hooks, please also use `$pages->___saveFields()` for call. (default=false)
	 *  - See $options argument in `Pages::save()` for additional options.
	 * @return array Array of saved field names (may also include property names if they were modified)
	 * @throws WireException
	 * @since 3.0.242
	 * 
	 */
	public function saveFields(Page $page, $fields, array $options = array()) {

		$saved = array();
		$quiet = !empty($options['quiet']);
		$noHooks = !empty($options['noHooks']);

		// do not update modified user/time until last save
		if(!$quiet) $options['quiet'] = true;

		if(!is_array($fields)) {
			$fields = explode(' ', str_replace(',', ' ', "$fields"));
		}

		foreach($fields as $key => $field) {
			$field = trim("$field");
			if(empty($field) || !$page->hasField($field)) unset($fields[$key]);
		}

		// save each field
		foreach($fields as $field) {
			if($noHooks) {
				$success = $this->saveField($page, $field, $options);
			} else {
				$success = $this->pages->saveField($page, $field, $options);
			}
			if($success) {
				$saved[$field] = $field;
				$page->untrackChange($field);
			}
		}

		if($quiet) {
			// do not save native properties or update page modified-user/modified
			
		} else {
			// finish by saving the page without fields
			$options['quiet'] = false;
		
			foreach($page->getChanges() as $name) {
				if($page->hasField($name)) continue;
				// add only changed native properties to saved list
				$saved[$name] = $name; 
			}
			
			$options['noFields'] = true;
			
			if($noHooks) {
				$this->save($page, $options);
			} else {
				$this->pages->save($page, $options);
			}
		}
		
		$this->pages->debugLog('saveFields', "$page:" . implode(',', $fields), $saved);

		return $saved;
	}

	/**
	 * Silently add status flag to a Page and save
	 * 
	 * This action does not update the Page modified date. 
	 * It updates the status for both the given instantiated Page object and the value in the DB. 
	 * 
	 * #pw-group-status
	 * 
	 * @param Page $page 
	 * @param int $status Use Page::status* constants
	 * @return bool
	 * @since 3.0.146
	 * @see PagesEditor::setStatus(), PagesEditor::removeStatus()
	 * 
	 */
	public function addStatus(Page $page, $status) {
		if(!$page->hasStatus($status)) $page->addStatus($status);
		return $this->savePageStatus($page, $status) > 0;
	}

	/**
	 * Silently remove status flag from a Page and save
	 * 
	 * This action does not update the Page modified date.
	 * It updates the status for both the given instantiated Page object and the value in the DB. 
	 * 
	 * #pw-group-status
	 * 
	 * @param Page $page
	 * @param int $status Use Page::status* constants
	 * @return bool
	 * @since 3.0.146
	 * @see PagesEditor::setStatus(), PagesEditor::addStatus(), PagesEditor::saveStatus()
	 * 
	 */
	public function removeStatus(Page $page, $status) {
		if($page->hasStatus($status)) $page->removeStatus($status);
		return $this->savePageStatus($page, $status, false, true) > 0; 
	}

	/**
	 * Silently save whatever the given Page’s status currently is
	 * 
	 * This action does not update the Page modified date.
	 * 
	 * #pw-group-status
	 * 
	 * @param Page $page
	 * @return bool
	 * @since 3.0.146
	 * 
	 */
	public function saveStatus(Page $page) {
		return $this->savePageStatus($page, $page->status, false, 2) > 0;
	}

	/**
	 * Add or remove a Page status and commit to DB, optionally recursive with the children, grandchildren, and so on.
	 *
	 * While this can be performed with other methods, this is here just to make it fast for internal/non-api use.
	 * See the trash and restore methods for an example.
	 * 
	 * This action does not update the Page modified date. If given a Page or PageArray, also note that it does not update
	 * the status properties of those instantiated Page objects, it only updates the DB value. 
	 * 
	 * Note: Please use addStatus() or removeStatus() instead, unless you need to perform a recursive add/remove status.
	 * 
	 * #pw-group-status
	 *
	 * @param int|array|Page|PageArray $pageID Page ID, Page, array of page IDs, or PageArray
	 * @param int $status Status per flags in Page::status* constants. Status will be OR'd with existing status, unless $remove is used. 
	 * @param bool $recursive Should the status descend into the page's children, and grandchildren, etc? (default=false)
	 * @param bool|int $remove Should the status be removed rather than added? Use integer 2 to overwrite (default=false)
	 * @return int Number of pages updated
	 *
	 */
	public function savePageStatus($pageID, $status, $recursive = false, $remove = false) {

		$database = $this->wire()->database;
		$rowCount = 0;
		$multi = is_array($pageID) || $pageID instanceof PageArray;
		$page = $pageID instanceof Page ? $pageID : null;
		$status = (int) $status;
		
		if($status < 0 || $status > Page::statusMax) {
			throw new WireException("status must be between 0 and " . Page::statusMax);
		}

		$sqlUpdate = "UPDATE pages SET status=";
	
		if($remove === 2) {
			// overwrite status (internal/undocumented)
			$sqlUpdate .= "status=$status";
			if($page instanceof Page) $page->status = $status;
		} else if($remove) {
			// remove status
			$sqlUpdate .= "status & ~$status";
			if($page instanceof Page) $page->removeStatus($status);
		} else {
			// add status
			$sqlUpdate .= "status|$status";
			if($page instanceof Page) $page->addStatus($status);
		}
		
		if($multi && $recursive) {
			// multiple page IDs combined with recursive option, must be handled individually
			foreach($pageID as $id) {
				$id = $id instanceof Page ? $id : (int) "$id";
				$rowCount += $this->savePageStatus($id, $status, $recursive, $remove);
			}
			// exit early in this case
			return $rowCount; 
			
		} else if($multi) {
			// multiple page IDs without recursive option, can be handled in one query
			$ids = array();
			foreach($pageID as $id) {
				$id = (int) "$id";
				if($id > 0) $ids[$id] = $id;
			}
			if(count($ids)) {
				$query = $database->prepare("$sqlUpdate WHERE id IN(" . implode(',', $ids) . ")");
				$database->execute($query);
				$rowCount = $query->rowCount();
			}
			return $rowCount;
			
		} else {
			// single page ID or Page object
			$pageID = (int) "$pageID";
			$query = $database->prepare("$sqlUpdate WHERE id=:page_id");
			$query->bindValue(":page_id", $pageID, \PDO::PARAM_INT);
			$database->execute($query);
			$rowCount = $query->rowCount();
		}
		
		if(!$recursive) return $rowCount;
		
		// recursive mode assumed from this point forward
		$parentIDs = array($pageID);
		$ids = [];

		do {
			$parentID = array_shift($parentIDs);

			// update all children to have the same status
			$query = $database->prepare("$sqlUpdate WHERE parent_id=:parent_id");
			$query->bindValue(":parent_id", $parentID, \PDO::PARAM_INT);
			$database->execute($query);
			$rowCount += $query->rowCount();
			$query->closeCursor();

			// locate children that themselves have children
			$query = $database->prepare(
				"SELECT pages.id FROM pages " .
				"JOIN pages AS pages2 ON pages2.parent_id=pages.id " .
				"WHERE pages.parent_id=:parent_id " .
				"GROUP BY pages.id " .
				"ORDER BY pages.sort"
			);
			
			$query->bindValue(':parent_id', $parentID, \PDO::PARAM_INT);
			$database->execute($query);
			
			/** @noinspection PhpAssignmentInConditionInspection */
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$id = (int) $row['id'];
				$parentIDs[$id] = $id;
				$ids[$id] = $id;
			}
			
			$query->closeCursor();
			
		} while(count($parentIDs));
		
		if(count($ids)) {
			$rowCount += $this->savePageStatus($ids, $status, false, $remove);
		}

		return $rowCount;
	}
	
	/**
	 * Permanently delete a page and its fields.
	 *
	 * Unlike trash(), pages deleted here are not restorable.
	 *
	 * If you attempt to delete a page with children, and don't specifically set the $recursive param to True, then
	 * this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown.
	 * 
	 * #pw-group-delete
	 *
	 * @param Page $page
	 * @param bool|array $recursive If set to true, then this will attempt to delete all children too.
	 *   If you don't need this argument, optionally provide $options array instead. 
	 * @param array $options Optional settings to change behavior:
	 * - `uncacheAll` (bool): Whether to clear memory cache after delete (default=false)
	 * - `recursive` (bool): Same as $recursive argument, may be specified in $options array if preferred.
	 * @return bool|int Returns true (success), or integer of quantity deleted if recursive mode requested.
	 * @throws WireException on fatal error
	 *
	 */
	public function delete(Page $page, $recursive = false, array $options = array()) {
		
		$defaults = array(
			'uncacheAll' => false, 
			'recursive' => is_bool($recursive) ? $recursive : false,
			// internal use properties:
			// internal recursion level: incremented only by delete operations initiated by this method
			'_level' => 0, 
			// internal delete branch: Page object when deleting a branch
			'_deleteBranch' => false,
		);

		// page IDs for all delete operations, cleared out once no longer recursive
		static $deleted = array(); 
		
		// external recursion level: all recursive delete operations including those initiated from hooks
		static $level = 0; 

		if(is_array($recursive)) $options = $recursive; 	
		$options = array_merge($defaults, $options);

		// check if page already deleted in a recursive call 
		if(isset($deleted[$page->id])) {
			// page already deleted, return result from that call
			return $options['recursive'] ? $deleted[$page->id] : true;
		}

		$this->isDeleteable($page, true); // throws WireException
		
		$numDeleted = 0;
		$numChildren = $page->numChildren;
		$deleteBranch = false;
		$level++;

		if($numChildren) try {
			if(!$options['recursive']) {
				throw new WireException("Can't delete Page $page because it has one or more children.");
			}
			if($options['_level'] === 0) {
				$deleteBranch = true;
				$options['_deleteBranch'] = $page;
				$this->pages->deleteBranchReady($page, $options);
			}
			foreach($page->children('include=all') as $child) {
				/** @var Page $child */
				if(isset($deleted[$child->id])) continue;
				$options['_level']++;
				$result = $this->pages->delete($child, true, $options);
				$options['_level']--;
				if(!$result) throw new WireException("Error doing recursive page delete, stopped by page $child");
				$numDeleted += $result;
			}
		} catch(\Exception $e) {
			$level = 0;
			$deleted = array();
			throw $e;
		}

		// trigger a hook to indicate delete is ready and WILL occur
		$this->pages->deleteReady($page, $options);
		$this->clear($page);
		
		$database = $this->wire()->database;
		$query = $database->prepare("DELETE FROM pages WHERE id=:page_id LIMIT 1"); // QA
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT);
		$query->execute();

		$this->pages->sortfields()->delete($page);
		$page->setTrackChanges(false);
		$page->status = Page::statusDeleted; // no need for bitwise addition here, as this page is no longer relevant
		$numDeleted++;
		$deleted[$page->id] = $numDeleted;
		$this->pages->deleted($page, $options);
		
		if($deleteBranch) $this->pages->deletedBranch($page, $options, $numDeleted);
		if($options['uncacheAll']) $this->pages->uncacheAll($page);
		
		if($level > 0) $level--;
		if($level < 1) {
			// back at root call, reset all tracking
			$deleted = array();
			$level = 0;
		}
		
		$this->pages->debugLog('delete', $page, true);

		return $options['recursive'] ? $numDeleted : true;
	}
	
	/**
	 * Clone an entire page (including fields, file assets, and optionally children) and return it.
	 * 
	 * #pw-group-add
	 *
	 * @param Page $page Page that you want to clone
	 * @param Page|null $parent New parent, if different (default=same parent)
	 * @param bool $recursive Clone the children too? (default=true)
	 * @param array|string $options Optional options that can be passed to clone or save
	 * 	- `forceID` (int): force a specific ID
	 * 	- `set` (array): Array of properties to set to the clone (you can also do this later)
	 * 	- `recursionLevel` (int): recursion level, for internal use only.
	 * @return Page|NullPage the newly cloned page or a NullPage() with id=0 if unsuccessful.
	 * @throws WireException|\Exception on fatal error
	 *
	 */
	public function _clone(Page $page, ?Page $parent = null, $recursive = true, $options = array()) {
		
		$defaults = array(
			'forceID' => 0, 
			'set' => array(), 
			'recursionLevel' => 0, // recursion level (internal use only)
		);

		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		$options = array_merge($defaults, $options);
		if($parent === null) $parent = $page->parent; 

		if(count($options['set']) && !empty($options['set']['name'])) {
			$name = $options['set']['name'];
		} else {
			$name = $this->pages->names()->uniquePageName(array(
				'name' => $page->name, 
				'parent' => $parent
			));
		}
		
		$of = $page->of();
		$page->of(false);

		// Ensure all data is loaded for the page
		foreach($page->fieldgroup as $field) {
			/** @var Field $field */
			if($page->hasField($field->name)) $page->get($field->name);
		}
	
		/** @var User $user */
		$user = $this->wire('user');

		// clone in memory
		$copy = clone $page;
		$copy->setIsNew(true);
		$copy->of(false);
		$copy->setQuietly('_cloning', $page);
		$copy->setQuietly('id', $options['forceID'] > 1 ? (int) $options['forceID'] : 0);
		$copy->setQuietly('numChildren', 0);
		$copy->setQuietly('created', time());
		$copy->setQuietly('modified', time());
		$copy->name = $name;
		$copy->parent = $parent;
		
		if(!isset($options['quiet']) || $options['quiet']) {
			$options['quiet'] = true;
			$copy->setQuietly('created_users_id', $user->id);
			$copy->setQuietly('modified_users_id', $user->id);
		}
		
		// set any properties indicated in options	
		if(count($options['set'])) {
			foreach($options['set'] as $key => $value) {
				$copy->set($key, $value);
				// quiet option required for setting modified time or user
				if($key === 'modified' || $key === 'modified_users_id') $options['quiet'] = true; 
			}
		}

		// tell PW that all the data needs to be saved
		foreach($copy->fieldgroup as $field) {
			if($copy->hasField($field)) $copy->trackChange($field->name);
		}

		$this->pages->cloneReady($page, $copy);
		$this->cloning++;
		$options['ignoreFamily'] = true; // skip family checks during clone
		try {
			$this->pages->save($copy, $options);
		} catch(\Exception $e) {
			$this->cloning--;
			$copy->setQuietly('_cloning', null); 
			$page->of($of);
			throw $e;
		}
		$this->cloning--;

		// check to make sure the clone has worked so far
		if(!$copy->id || $copy->id == $page->id) {
			$copy->setQuietly('_cloning', null);
			$page->of($of);
			return $this->pages->newNullPage();
		}

		// copy $page's files over to new page
		if(PagefilesManager::hasFiles($page)) {
			$copy->filesManager->init($copy);
			$page->filesManager->copyFiles($copy->filesManager->path());
		}

		// if there are children, then recursively clone them too
		if($page->numChildren && $recursive) {
			$start = 0;
			$limit = 200;
			$numChildrenCopied = 0;
			do {
				$children = $page->children("include=all, start=$start, limit=$limit");
				$numChildren = $children->count();
				foreach($children as $child) {
					/** @var Page $child */
					$childCopy = $this->pages->clone($child, $copy, true, array(
						'recursionLevel' => $options['recursionLevel'] + 1,
					));
					if($childCopy->id) $numChildrenCopied++;
				}
				$start += $limit;
				$this->pages->uncacheAll();
			} while($numChildren);
			$copy->setQuietly('numChildren', $numChildrenCopied); 
		}

		$copy->parentPrevious = null;
		$copy->setQuietly('_cloning', null);

		if($options['recursionLevel'] === 0) {
			// update pages_parents table, only when at recursionLevel 0 since parents()->rebuild() already descends 
			/*
			if($copy->numChildren) {
				$copy->setIsNew(true);
				$this->pages->parents()->rebuild($copy);
				$copy->setIsNew(false);
			}
			*/
			// update sort
			if($copy->parent()->sortfield() == 'sort') {
				$this->sortPage($copy, $copy->sort, true);
			}
		}

		$copy->of($of);
		$page->of($of);
		$page->meta()->copyTo($copy->id); 
		$copy->resetTrackChanges();
		$this->pages->cloned($page, $copy);
		$this->pages->debugLog('clone', "page=$page, parent=$parent", $copy);

		return $copy;
	}

	/**
	 * Update page modified/created/published time to now (or given time)
	 * 
	 * #pw-group-save
	 * 
	 * @param Page|PageArray|array $pages May be Page, PageArray or array of page IDs (integers)
	 * @param null|int|string|array $options Omit (null) to update to now, or unix timestamp or strtotime() recognized time string, 
	 *  or if you do not need this argument, you may optionally substitute the $type argument here, 
	 *  or in 3.0.183+ you can also specify array of options here instead:
	 *  - `time` (string|int|null): Unix timestamp or strtotime() recognized string to use, omit for use current time (default=null)
	 *  - `type` (string): One of 'modified', 'created', 'published' (default='modified')
	 *  - `user` (bool|User): True to also update modified/created user to current user, or specify User object to use (default=false)
	 * @param string $type Date type to update, one of 'modified', 'created' or 'published' (default='modified') Added 3.0.147
	 *  Skip this argument if using options array for previous argument or if using the default type 'modified'.
	 * @throws WireException|\PDOException if given invalid format for $modified argument or failed database query
	 * @return bool True on success, false on fail
	 * 
	 */
	public function touch($pages, $options = null, $type = 'modified') {
		
		$defaults = array(
			'time' => (is_string($options) || is_int($options) ? $options : null),
			'type' => $type,
			'user' => false,
		);

		$options = is_array($options) ? array_merge($defaults, $options) : $defaults;
		$database = $this->wire()->database;
		$time = $options['time']; 
		$type = $options['type'];
		$user = $options['user'] === true ? $this->wire()->user : $options['user'];
		$ids = array();
		
		if($time === 'modified' || $time === 'created' || $time === 'published') {
			// time argument was omitted and type supplied here instead
			$type = $time;	
			$time = null;
		}
	
		// ensure $col property is created in this method and not copied directly from $type
		if($type === 'modified') {
			$col = 'modified';
		} else if($type === 'created') {
			$col = 'created';
		} else if($type === 'published') {
			$col = 'published';
		} else {
			throw new WireException("Unrecognized date type '$type' for Pages::touch()");
		}
		
		if($pages instanceof Page) {
			$ids[] = (int) $pages->id;
			
		} else if(WireArray::iterable($pages)) {
			foreach($pages as $page) {
				if(is_int($page)) {
					// page ID integer
					$ids[] = (int) $page;
				} else if($page instanceof Page) {
					// Page object
					$ids[] = (int) $page->id;
				} else if(ctype_digit("$page")) {
					// Page ID string
					$ids[] = (int) "$page";
				} else {
					// invalid
				}
			}
		}
		
		if(!count($ids)) return false;
		
		$sql = "UPDATE pages SET $col=";
		
		if(is_null($time)) {
			$sql .= 'NOW() ';
			
		} else if(is_int($time) || ctype_digit($time)) {
			$time = (int) $time;
			$sql .= ':time ';
			
		} else if(is_string($time)) {
			$time = strtotime($time);
			if(!$time) throw new WireException("Unrecognized time format provided to Pages::touch()");
			$sql .= ':time ';
		}

		if($user instanceof User && ($col === 'modified' || $col === 'created')) {
			$sql .= ", {$col}_users_id=:user ";
		} 
		
		$sql .= 'WHERE id IN(' . implode(',', $ids) . ')';
		$query = $database->prepare($sql);
		if(strpos($sql, ':time')) $query->bindValue(':time', date('Y-m-d H:i:s', $time));
		if(strpos($sql, ':user')) $query->bindValue(':user', $user->id, \PDO::PARAM_INT);
		
		return $database->execute($query);
	}

	/**
	 * Move page to specified parent (work in progress)
	 * 
	 * This method is the same as changing a page parent and saving, but provides a useful shortcut
	 * for some cases with less code. This method:
	 * 
	 * - Does not save the other custom fields of a page (if any are changed). 
	 * - Does not require that output formatting be off (it manages that internally). 
	 * 
	 * #pw-group-order
	 * 
	 * @param Page $child Page that you want to move.
	 * @param Page|int|string $parent Parent to move it under (may be Page object, path string, or ID integer).
	 * @param array $options Options to modify behavior (see PagesEditor::save for options). 
	 * @return bool True on success or false if not necessary.
	 * @throws WireException if given parent does not exist, or move is not allowed
	 *
	 */
	public function move(Page $child, $parent, array $options = array()) {
		
		if(is_string($parent) || is_int($parent)) $parent = $this->pages->get($parent); 
		if(!$parent instanceof Page || !$parent->id) throw new WireException('Unable to locate parent for move');
		
		$options['noFields'] = true;
		$of = $child->of();
		$child->of(false);
		$child->parent = $parent;
		$result = $child->parentPrevious ? $this->pages->save($child, $options) : false;
		if($of) $child->of(true);
		
		return $result;
	}

	/**
	 * Set page $sort value and increment siblings having same or greater sort value 
	 * 
	 * - This method is primarily applicable if configured sortfield is manual “sort” (or “none”).
	 * - This is typically used after a move, sort, clone or delete operation. 
	 * 
	 * #pw-group-order
	 * 
	 * @param Page $page Page that you want to set the sort value for
	 * @param int|null $sort New sort value for page or null to pull from $page->sort
	 * @param bool $after If another page already has the sort, make $page go after it rather than before it? (default=false)
	 * @throws WireException if given invalid arguments
	 * @return int Number of sibling pages that had to have sort adjusted
	 * 
	 */
	public function sortPage(Page $page, $sort = null, $after = false) {
	
		$database = $this->wire()->database;

		// reorder siblings having same or greater sort value, when necessary
		if($page->id <= 1) return 0;
		if(is_null($sort)) $sort = $page->sort;
		
		// determine if any other siblings have same sort value
		$sql = 'SELECT id FROM pages WHERE parent_id=:parent_id AND sort=:sort AND id!=:id';
		$query = $database->prepare($sql);
		$query->bindValue(':parent_id', $page->parent_id, \PDO::PARAM_INT);
		$query->bindValue(':sort', $sort, \PDO::PARAM_INT);
		$query->bindValue(':id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$rowCount = $query->rowCount();
		$query->closeCursor();
	
		// move sort to after if requested
		if($after && $rowCount) $sort += $rowCount;
		
		// update $page->sort property if needed
		if($page->sort != $sort) $page->sort = $sort;
		
		// make sure that $page has the sort value indicated
		$sql = 'UPDATE pages SET sort=:sort WHERE id=:id';
		$query = $database->prepare($sql);
		$query->bindValue(':sort', $sort, \PDO::PARAM_INT);
		$query->bindValue(':id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$sortCnt = $query->rowCount();
		
		// no need for $page to have 'sort' indicated as a change, since we just updated it above
		$page->untrackChange('sort');

		if($rowCount) {
			// update order of all siblings 
			$sql = 'UPDATE pages SET sort=sort+1 WHERE parent_id=:parent_id AND sort>=:sort AND id!=:id';
			$query = $database->prepare($sql);
			$query->bindValue(':parent_id', $page->parent_id, \PDO::PARAM_INT);
			$query->bindValue(':sort', $sort, \PDO::PARAM_INT);
			$query->bindValue(':id', $page->id, \PDO::PARAM_INT);
			$query->execute();
			$sortCnt += $query->rowCount();
		}
	
		// call the sorted hook
		$this->pages->sorted($page, false, $sortCnt);
	
		return $sortCnt;
	}

	/**
	 * Sort one page before another (for pages using manual sort)
	 * 
	 * Note that if given $sibling parent is different from `$page` parent, then the `$pages->save()`
	 * method will also be called to perform that movement. 
	 * 
	 * #pw-group-order
	 * 
	 * @param Page $page Page to move/sort
	 * @param Page $sibling Sibling that page will be moved/sorted before 
	 * @param bool $after Specify true to make $page move after $sibling instead of before (default=false)
	 * @throws WireException When conditions don't allow page insertions
	 * 
	 */
	public function insertBefore(Page $page, Page $sibling, $after = false) {
		$sortfield = $sibling->parent()->sortfield();
		if($sortfield != 'sort') {
			throw new WireException('Insert before/after operations can only be used with manually sorted pages');
		}
		if(!$sibling->id || !$page->id) {
			throw new WireException('New pages must be saved before using insert before/after operations');
		}
		if($sibling->id == 1 || $page->id == 1) {
			throw new WireException('Insert before/after operations cannot involve homepage');
		}
		$page->sort = $sibling->sort;
		if($page->parent_id != $sibling->parent_id) {
			// page needs to be moved first
			$page->parent = $sibling->parent;
			$page->save();
		}
		$this->sortPage($page, $page->sort, $after); 
	}

	/**
	 * Rebuild the “sort” values for all children of the given $parent page, fixing duplicates and gaps
	 * 
	 * If used on a $parent not currently sorted by by “sort” then it will update the “sort” index to be
	 * consistent with whatever the pages are sorted by. 
	 * 
	 * #pw-group-order
	 * 
	 * @param Page $parent
	 * @return int
	 * 
	 */
	public function sortRebuild(Page $parent) {
		
		if(!$parent->id || !$parent->numChildren) return 0;
		$database = $this->wire()->database;
		$sorts = array();
		$sort = 0;
		
		if($parent->sortfield() == 'sort') {
			// pages are manually sorted, so we can find IDs directly from the database
			$sql = 'SELECT id FROM pages WHERE parent_id=:parent_id ORDER BY sort, created';
			$query = $database->prepare($sql);
			$query->bindValue(':parent_id', $parent->id, \PDO::PARAM_INT);
			$query->execute();

			// establish new sort values
			do {
				$id = (int) $query->fetch(\PDO::FETCH_COLUMN);
				if(!$id) break;
				$sorts[] = "($id,$sort)";
			} while(++$sort);

			$query->closeCursor();
			
		} else {
			// children of $parent don't currently use "sort" as sort property
			// so we will update the "sort" of children to be consistent with that
			// of whatever sort property is in use. 
			$o = array('findIDs' => 1, 'cache' => false);
			foreach($parent->children('include=all', $o) as $id) {
				$id = (int) $id;
				$sorts[] = "($id,$sort)";	
				$sort++;
			}
		}

		// update sort values
		$query = $database->prepare(
			'INSERT INTO pages (id,sort) VALUES ' . implode(',', $sorts) . ' ' .
			'ON DUPLICATE KEY UPDATE sort=VALUES(sort)'
		);

		$query->execute();
		
		return count($sorts);
	}

	/**
	 * Replace one page with another (work in progress)
	 * 
	 * #pw-group-save
	 * 
	 * @param Page $oldPage
	 * @param Page $newPage
	 * @return Page
	 * @throws WireException
	 * @since 3.0.189 But not yet available in public API
	 * 
	 */
	protected function replace(Page $oldPage, Page $newPage) {
		
		if($newPage->numChildren) {
			throw new WireException('Page with children cannot replace another');
		}

		$database = $this->wire()->database;
		
		$this->pages->cacher()->uncache($oldPage);
		$this->pages->cacher()->uncache($newPage);
		
		$prevId = $newPage->id;
		$id = $oldPage->id;
		$parent = $oldPage->parent;
		$prevTemplate = $oldPage->template;
		
		$newPage->parent = $parent;
		$newPage->templatePrevious = $prevTemplate;

		$this->clear($oldPage, array(
			'clearParents' => false, 
			'clearAccess' => $prevTemplate->id != $newPage->template->id, 
			'clearSortfield' => false,
		)); 
		
		$binds = array(
			':id' => $id, 
			':parent_id' => $parent->id, 
			':prev_id' => $prevId, 
		);
		
		$sqls = array();
		$sqls[] = 'UPDATE pages SET id=:id, parent_id=:parent_id WHERE id=:prev_id';
	
		foreach($newPage->template->fieldgroup as $field) {
			/** @var Field $field */
			$field->type->replacePageField($newPage, $oldPage, $field);
		}
		
		foreach($sqls as $sql) {
			$query = $database->prepare($sql);
			foreach($binds as $bindKey => $bindValue) {
				if(strpos($sql, $bindKey) === false) continue;
				$query->bindValue($bindKey, $bindValue);
				$query->execute();
			}
		}

		$newPage->id = $id;
		
		$this->save($newPage);
		
		$page = $this->pages->getById($id, $newPage->template, $parent->id);
		
		return $page;
	}

	/**
	 * Clear a page of its data
	 * 
	 * #pw-group-delete
	 * 
	 * @param Page $page
	 * @param array $options
	 * @return bool
	 * @throws WireException
	 * @since 3.0.189
	 * 
	 */
	public function clear(Page $page, array $options = array()) {
		
		$defaults = array(
			'clearMethod' => 'delete', // 'delete' or 'empty'
			'haltOnError' => false,
			'clearFields' => true,
			'clearFiles' => true, 
			'clearMeta' => true, 
			'clearAccess' => true, 
			'clearSortfield' => true,
			'clearParents' => true,
		);

		$options = array_merge($defaults, $options);
		$errors = array();
		$halt = false;

		if($options['clearFields']) {
			foreach($page->fieldgroup as $field) {
				/** @var Field $field  */
				/** @var Fieldtype $fieldtype */
				$fieldtype = $field->type;
				if($options['clearMethod'] === 'delete') {
						$result = $fieldtype->deletePageField($page, $field);
					} else {
						$result = $fieldtype->emptyPageField($page, $field);
					}
				if(!$result) {	
					$errors[] = "Unable to clear field '$field' from page $page";
					$halt = $options['haltOnError'];
					if($halt) break;
				}
			}
		}
		
		if($options['clearFiles'] && !$halt) {
			$error = "Error clearing files for page $page"; 
			try {
				if(PagefilesManager::hasPath($page)) {
					$filesManager = $page->filesManager();
					if(!$filesManager) {
						// $filesManager will be null if page has deleted status
						// so create our own instance
						$filesManager = new PagefilesManager($page);
					}
					if(!$filesManager->emptyAllPaths()) {
						$errors[] = $error;
						$halt = $options['haltOnError'];
					}
				}
			} catch(\Exception $e) {
				$errors[] = $error . ' - ' . $e->getMessage();
				$halt = $options['haltOnError'];
			}
		}

		if($options['clearMeta'] && !$halt) {
			try {
				$page->meta()->removeAll();
			} catch(\Exception $e) {
				$errors[] = "Error clearing meta for page $page";
				$halt = $options['haltOnError'];
			}
		}

		if($options['clearAccess'] && !$halt) {
			/** @var PagesAccess $access */
			$access = $this->wire(new PagesAccess());
			$access->deletePage($page);
		}

		if($options['clearParents'] && !$halt) {
			// delete entirely from pages_parents table
			$this->pages->parents()->delete($page);
		}

		if($options['clearSortfield'] && !$halt) {
			$this->pages->sortfields()->delete($page);
		}
		
		if(count($errors) || $halt) {
			foreach($errors as $error) {
				$this->error($error);
			}
			return false;
		}

		return true;
	}
	
	/**
	 * Prepare options for Pages::new(), Pages::newPage() 
	 * 
	 * Converts given array, selector string, template name, object or int to array of options. 
	 * 
	 * #pw-internal
	 *
	 * @param array|string|int $options
	 * @return array
	 * @since 3.0.191
	 *
	 */
	public function newPageOptions($options) {
		
		if(empty($options)) return array(); 

		$template = null; /** @var Template|null $template */
		$parent = null;
		$class = '';

		if(is_array($options)) {
			// ok
		} else if(is_string($options)) {
			if(strpos($options, '=') !== false) {
				$selectors = new Selectors($options);
				$this->wire($selectors);
				$options = array();
				foreach($selectors as $selector) {
					$options[$selector->field()] = $selector->value;
				}
			} else if(strpos($options, '/') === 0) {
				$options = array('path' => $options);
			} else {
				$options = array('template' => $options);
			}
		} else if(is_object($options)) {
			$options = $options instanceof Template ? array('template' => $options) : array();
		} else if(is_int($options)) {
			$template = $this->wire()->templates->get($options);
			$options = $template ? array('template' => $template) : array();
		} else {
			$options = array();
		}

		// only use property 'parent' rather than 'parent_id'
		if(!empty($options['parent_id']) && empty($options['parent'])) {
			$options['parent'] = $options['parent_id'];
			unset($options['parent_id']);
		}

		// only use property 'template' rather than 'templates_id'
		if(!empty($options['templates_id']) && empty($options['template'])) {
			$options['template'] = $options['templates_id'];
			unset($options['templates_id']);
		}

		// page class (pageClass)
		if(!empty($options['pageClass'])) {
			// ok
			$class = $options['pageClass'];
			unset($options['pageClass']); 
		} else if(!empty($options['class']) && !$this->wire()->fields->get('class')) {
			// alias for pageClass, so long as there is not a field named 'class'
			$class = $options['class'];
			unset($options['class']);
		}

		// identify requested template
		if(isset($options['template'])) {
			$template = $options['template'];
			if(!is_object($template)) {
				$template = empty($template) ? null : $this->wire()->templates->get($template);
			}
			unset($options['template']);
		}

		// convert parent path to parent page object
		if(!empty($options['parent'])) {
			if(is_object($options['parent'])) {
				$parent = $options['parent'];
			} else if(ctype_digit("$options[parent]")) {
				$parent = (int) $options['parent'];
			} else {
				$parent = $this->pages->getByPath($options['parent']);
				if(!$parent->id) $parent = null;
			}
			unset($options['parent']);
		}

		// name and parent can be detected from path, when specified
		if(!empty($options['path'])) {
			$path = trim($options['path'], '/');
			if(strpos($path, '/') === false) $path = "/$path";
			$parts = explode('/', $path); // note index[0] is blank
			$name = array_pop($parts);
			if(empty($options['name']) && !empty($name)) {
				// detect name from path
				$options['name'] = $name;
			}
			if(empty($parent) && !$this->pages->loader()->isLoading()) {
				// detect parent from path
				$parentPath = count($parts) ? implode('/', $parts) : '/';
				$parent = $this->pages->getByPath($parentPath);
				if(!$parent->id) $parent = null;
			}
			unset($options['path']);
		}

		// detect template from parent (when possible)
		if(!$template && !empty($parent) && empty($options['id']) && !$this->pages->loader()->isLoading()) {
			$parent = is_object($parent) ? $parent : $this->pages->get($parent);
			if($parent->id) {
				if(count($parent->template->childTemplates) === 1) {
					$template = $parent->template->childTemplates()->first();
				}
			} else {
				$parent = null;
			}
		}

		// detect parent from template (when possible)
		if($template && empty($parent) && empty($options['id']) && !$this->pages->loader()->isLoading()) { 
			if(count($template->parentTemplates) === 1) {
				$parentTemplates = $template->parentTemplates();
				if($parentTemplates->count()) {
					$numParents = $this->pages->count("template=$parentTemplates, include=all");
					if($numParents === 1) {
						$parent = $this->pages->get("template=$parentTemplates");
						if(!$parent->id) $parent = null;
					}
				}
			}	
		}
	
		// detect class from template
		if(empty($class) && $template) $class = $template->getPageClass();

		if($parent) $options['parent'] = $parent;
		if($template) $options['template'] = $template;
		if($class) $options['pageClass'] = $class;
		
		if(isset($options['id'])) {
			if(ctype_digit("$options[id]") && (int) $options['id'] > 0) {
				$options['id'] = (int) $options['id'];
				if($parent && "$options[id]" === "$parent") unset($options['parent']);
			} else if(((int) $options['id']) === -1) {
				$options['id'] = (int) $options['id']; // special case allowed for access control tests
			} else {
				unset($options['id']);
			}
		}

		return $options;
	}

	/**
	 * Hook after Fieldtype::sleepValue to remove MB4 characters when present and applicable
	 * 
	 * This hook is only used if $config->dbStripMB4 is true and $config->dbEngine is not “utf8mb4”. 
	 * 
	 * #pw-internal
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookFieldtypeSleepValueStripMB4(HookEvent $event) {
		$event->return = $this->wire()->sanitizer->removeMB4($event->return); 
	}
}
