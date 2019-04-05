<?php namespace ProcessWire;

/**
 * ProcessWire Pages Editor
 * 
 * Implements page manipulation methods of the $pages API variable
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
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

	public function __construct(Pages $pages) {
		$this->pages = $pages;

		$config = $pages->wire('config');
		if($config->dbStripMB4 && strtolower($config->dbEngine) != 'utf8mb4') {
			$this->addHookAfter('Fieldtype::sleepValue', $this, 'hookFieldtypeSleepValueStripMB4');
		}
	}

	/**
	 * Are we currently in a page clone?
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
			$template = $this->wire('templates')->get($template);
			if(!$template) throw new WireException("Unknown template");
		}

		$pageClass = wireClassName($template->pageClass ? $template->pageClass : 'Page', true);

		$page = $this->pages->newPage(array(
			'template' => $template,
			'pageClass' => $pageClass
		));
		$page->parent = $parent;

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

		// save page before setting $values just in case any fieldtypes
		// require the page to have an ID already (like file-based)
		if(!$this->pages->save($page)) throw new WireException($exceptionMessage);

		// set field values, if provided
		if(!empty($values)) {
			unset($values['id'], $values['parent'], $values['template']); // fields that may not be set from this array
			foreach($values as $key => $value) $page->set($key, $value);
			$this->pages->save($page);
		}

		return $page;
	}
	
	/**
	 * Is the given page in a state where it can be saved from the API?
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
				if(is_object($value) && $value instanceof Wire && $value->isChanged()) {
					$reason = $outputFormattingReason . " [$key]";
					$saveable = false;
					break;
				}
			}
		}

		// check for a parent change and whether it is allowed
		if($saveable && $page->parentPrevious && empty($options['ignoreFamily'])) {
			// parent has changed, check that the move is allowed
			$saveable = $this->isMoveable($page, $page->parentPrevious, $page->parent, $reason); 
		}
		
		return $saveable;
	}

	/**
	 * Return whether given Page is moveable from $oldParent to $newParent
	 * 
	 * @param Page $page Page to move
	 * @param Page $oldParent Current/old parent page
	 * @param Page $newParent New requested parent page
	 * @param string $reason Populated with reason why page is not moveable, if return false is false. 
	 * @return bool
	 * 
	 */
	public function isMoveable(Page $page, Page $oldParent, Page $newParent, &$reason) {
		
		if($oldParent->id == $newParent->id) return true; 
		
		$config = $this->wire('config');
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
	 * Note: this does not account for user permission checking. It only checks if the page is in a state to be saveable via the API.
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
		} else if($page->id === $this->wire('page')->id && $this->wire('config')->installedAfter('2019-04-04')) {
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

			if($parent->id) $page->parent = $parent;
		}

		// assign page name
		if(!strlen($page->name)) {
			$this->pages->setupPageName($page); // call through $pages intended, so it can be hooked
		}

		// assign sort order
		if($page->sort < 0) {
			$page->sort = $page->parent->numChildren();
		}

		// assign any default values for fields
		foreach($page->template->fieldgroup as $field) {
			if($page->isLoaded($field->name)) continue; // value already set
			if(!$page->hasField($field)) continue; // field not valid for page
			if(!strlen($field->defaultValue)) continue; // no defaultValue property defined with Fieldtype config inputfields
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
				if($this->wire('database')->inTransaction()) throw $e;
			}
		}
	}
	
	/**
	 * Auto-assign a page name to this page
	 *
	 * Typically this would be used only if page had no name or if it had a temporary untitled name.
	 *
	 * Page will be populated with the name given. This method will not populate names to pages that
	 * already have a name, unless the name is "untitled"
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
	 * @param Page $page
	 * @param array $options Optional array with the following optional elements:
	 * 	- `uncacheAll` (boolean): Whether the memory cache should be cleared (default=true)
	 * 	- `resetTrackChanges` (boolean): Whether the page's change tracking should be reset (default=true)
	 * 	- `quiet` (boolean): When true, modified date and modified_users_id won't be updated (default=false)
	 *	- `adjustName` (boolean): Adjust page name to ensure it is unique within its parent (default=false)
	 * 	- `forceID` (integer): Use this ID instead of an auto-assigned on (new page) or current ID (existing page)
	 * 	- `ignoreFamily` (boolean): Bypass check of allowed family/parent settings when saving (default=false)
	 *  - `noHooks` (boolean): Prevent before/after save hooks from being called (default=false)
	 *  - `noFields` (boolean): Bypass saving of custom fields (default=false)
	 * @return bool True on success, false on failure
	 * @throws WireException
	 *
	 */
	public function save(Page $page, $options = array()) {

		$defaultOptions = array(
			'uncacheAll' => true,
			'resetTrackChanges' => true,
			'adjustName' => false,
			'forceID' => 0,
			'ignoreFamily' => false,
			'noHooks' => false, 
			'noFields' => false, 
		);

		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		$options = array_merge($defaultOptions, $options);
		$user = $this->wire('user');
		$languages = $this->wire('languages');
		$language = null;

		// if language support active, switch to default language so that saved fields and hooks don't need to be aware of language
		if($languages && $page->id != $user->id) {
			$language = $user->language && $user->language->id ? $user->language : null;
			if($language) $user->language = $languages->getDefault();
		}

		$reason = '';
		$isNew = $page->isNew();
		if($isNew) $this->pages->setupNew($page);

		if(!$this->isSaveable($page, $reason, '', $options)) {
			if($language) $user->language = $language;
			throw new WireException("Can’t save page {$page->id}: {$page->path}: $reason");
		}

		if($page->hasStatus(Page::statusUnpublished) && $page->template->noUnpublish) {
			$page->removeStatus(Page::statusUnpublished);
		}

		if($page->parentPrevious && !$isNew) {
			if($page->isTrash() && !$page->parentPrevious->isTrash()) {
				$this->pages->trash($page, false);
			} else if($page->parentPrevious->isTrash() && !$page->parent->isTrash()) {
				$this->pages->restore($page, false);
			}
		}

		$this->pages->names()->checkNameConflicts($page);
		if(!$this->savePageQuery($page, $options)) return false;
		$result = $this->savePageFinish($page, $isNew, $options);
		if($language) $user->language = $language; // restore language
		
		return $result;
	}

	/**
	 * Execute query to save to pages table
	 *
	 * triggers hooks: saveReady, statusChangeReady (when status changed)
	 *
	 * @param Page $page
	 * @param array $options
	 * @return bool
	 * @throws WireException|\Exception
	 *
	 */
	protected function savePageQuery(Page $page, array $options) {

		$isNew = $page->isNew();
		$database = $this->wire('database');
		$user = $this->wire('user');
		$config = $this->wire('config');
		$userID = $user ? $user->id : $config->superUserPageID;
		$systemVersion = $config->systemVersion;
		if(!$page->created_users_id) $page->created_users_id = $userID;
		if($page->isChanged('status') && empty($options['noHooks'])) $this->pages->statusChangeReady($page);
		if(empty($options['noHooks'])) {
			$extraData = $this->pages->saveReady($page); 
			$this->pages->savePageOrFieldReady($page);
		} else {
			$extraData = array();
		}
		$sql = '';

		if($this->pages->names()->isUntitledPageName($page->name)) {
			$this->pages->setupPageName($page);
		}

		$data = array(
			'parent_id' => (int) $page->parent_id,
			'templates_id' => (int) $page->template->id,
			'name' => $this->wire('sanitizer')->pageName($page->name, Sanitizer::toAscii),
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

		if(isset($data['modified_users_id'])) $page->modified_users_id = $data['modified_users_id'];
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

		if($result && ($isNew || !$page->id)) $page->id = $database->lastInsertId();
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

		// account for the duplicate possibly being a multi-language name field
		// i.e. “Duplicate entry 'bienvenido-2-1001' for key 'name1013_parent_id'”
		if($this->wire('languages') && preg_match('/\b(name\d*)_parent_id\b/', $exception->getMessage(), $matches)) {
			$nameField = $matches[1];
		} else {
			$nameField = 'name';
		}
		
		// get either 'name' or 'name123' (where 123 is language ID)
		$pageName = $page->get($nameField);
		$pageName = $this->pages->names()->incrementName($pageName);
		$page->set($nameField, $pageName);
		$query->bindValue(":$nameField", $this->wire('sanitizer')->pageName($pageName, Sanitizer::toAscii));
		
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
			$page->parent->numChildren++;
		} else {
			if($page->parentPrevious && $page->parentPrevious->id != $page->parent->id) {
				$page->parentPrevious->numChildren--;
				$page->parent->numChildren++;
			}
		}

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
			$name = $field->name;
			if($options['noFields'] || isset($corruptedFields[$name]) || !$field->type || !$page->hasField($field)) {
				unset($changes[$name]);
				unset($changesValues[$name]); 
			} else {
				try {
					$field->type->savePageField($page, $field);
				} catch(\Exception $e) {
					$error = sprintf($this->_('Error saving field "%s"'), $name) . ' - ' . $e->getMessage();
					$this->trackException($e, true, $error);
					if($this->wire('database')->inTransaction()) throw $e;
				}
			}
		}

		// return outputFormatting state
		$page->of($of);

		if(empty($page->template->sortfield)) $this->pages->sortfields()->save($page);
		
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

		// lastly determine whether the pages_parents table needs to be updated for the find() cache
		// and call upon $this->saveParents where appropriate. 
		if($page->parentPrevious && $page->numChildren > 0) {
			// page is moved and it has children
			$this->saveParents($page->id, $page->numChildren);
			if($page->parent->numChildren == 1) $this->saveParents($page->parent_id, $page->parent->numChildren);

		} else if(($page->parentPrevious && $page->parent->numChildren == 1) ||
			($isNew && $page->parent->numChildren == 1) ||
			($page->_forceSaveParents)) {
			// page is moved and is the first child of it's new parent
			// OR page is NEW and is the first child of it's parent
			// OR $page->_forceSaveParents is set (debug/debug, can be removed later)
			$this->saveParents($page->parent_id, $page->parent->numChildren);
			
		} else if($page->parentPrevious && $page->parent->numChildren > 1 && $page->parent->parent_id > 1) {
			$this->saveParents($page->parent->parent_id, $page->parent->parent->numChildren);
		}

		if($page->parentPrevious && $page->parentPrevious->numChildren == 0) {
			// $page was moved and it's previous parent is now left with no children, this ensures the old entries get deleted
			$this->saveParents($page->parentPrevious->id, 0);
		}

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

		$this->pages->debugLog('save', $page, true);

		return true;
	}
	
	/**
	 * Save just a field from the given page as used by Page::save($field)
	 *
	 * This function is public, but the preferred manner to call it is with $page->save($field)
	 *
	 * @param Page $page
	 * @param string|Field $field Field object or name (string)
	 * @param array|string $options Specify options: 
	 *  - `quiet` (boolean): Specify true to bypass updating of modified_users_id and modified time (default=false). 
	 *  - `noHooks` (boolean): Specify true to bypass calling of before/after save hooks (default=false). 
	 * @return bool True on success
	 * @throws WireException
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
			$field = $this->wire('fields')->get($field);
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
			$page->untrackChange($field->name);
			if(empty($options['quiet'])) {
				$user = $this->wire('user');
				$userID = (int) ($user ? $user->id : $this->wire('config')->superUserPageID);
				$database = $this->wire('database');
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
	 * Save references to the Page's parents in pages_parents table, as well as any other pages affected by a parent change
	 *
	 * Any pages_id passed into here are assumed to have children
	 *
	 * @param int $pages_id ID of page to save parents from
	 * @param int $numChildren Number of children this Page has
	 * @param int $level Recursion level, for debugging.
	 * @return bool
	 *
	 */
	protected function saveParents($pages_id, $numChildren, $level = 0) {

		$pages_id = (int) $pages_id;
		if(!$pages_id) return false;
		$database = $this->wire('database');

		$query = $database->prepare("DELETE FROM pages_parents WHERE pages_id=:pages_id");
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT);
		$query->execute();

		if(!$numChildren) return true;

		$insertSql = '';
		$id = $pages_id;
		$cnt = 0;
		$query = $database->prepare("SELECT parent_id FROM pages WHERE id=:id");

		do {
			if($id < 2) break; // home has no parent, so no need to do that query
			$query->bindValue(":id", $id, \PDO::PARAM_INT);
			$query->execute();
			list($id) = $query->fetch(\PDO::FETCH_NUM);
			$id = (int) $id;
			if($id < 2) break; // no need to record 1 for every page, since it is assumed
			$insertSql .= "($pages_id, $id),";
			$cnt++;

		} while(1);

		if($insertSql) {
			$sql = 
				'INSERT INTO pages_parents (pages_id, parents_id) ' . 
				'VALUES' . rtrim($insertSql, ',') . ' ' . 
				'ON DUPLICATE KEY UPDATE parents_id=VALUES(parents_id)';
			$database->exec($sql);
		}

		// find all children of $pages_id that themselves have children
		$sql = 	
			"SELECT pages.id, COUNT(children.id) AS numChildren " .
			"FROM pages " .
			"JOIN pages AS children ON children.parent_id=pages.id " .
			"WHERE pages.parent_id=:pages_id " .
			"GROUP BY pages.id ";

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT);
		$database->execute($query);

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$this->saveParents($row['id'], $row['numChildren'], $level+1);
		}
		$query->closeCursor();

		return true;
	}

	/**
	 * Sets a new Page status and saves the page, optionally recursive with the children, grandchildren, and so on.
	 *
	 * While this can be performed with other methods, this is here just to make it fast for internal/non-api use.
	 * See the trash and restore methods for an example.
	 *
	 * @param int|array|Page|PageArray $pageID Page ID, Page, array of page IDs, or PageArray
	 * @param int $status Status per flags in Page::status* constants. Status will be OR'd with existing status, unless $remove option is set.
	 * @param bool $recursive Should the status descend into the page's children, and grandchildren, etc?
	 * @param bool $remove Should the status be removed rather than added?
	 * @return int Number of pages updated
	 *
	 */
	public function savePageStatus($pageID, $status, $recursive = false, $remove = false) {

		$status = (int) $status;
		$sql = $remove ? "status & ~$status" : "status|$status";
		$database = $this->wire('database');
		$rowCount = 0;
		$multi = is_array($pageID) || $pageID instanceof PageArray;
		
		if($multi && $recursive) {
			// multiple page IDs combined with recursive option, must be handled individually
			foreach($pageID as $id) {
				$rowCount += $this->savePageStatus((int) "$id", $status, $recursive, $remove);
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
			if(!count($ids)) $ids[] = 0;
			$query = $database->prepare("UPDATE pages SET status=$sql WHERE id IN(" . implode(',', $ids) . ")");
			$database->execute($query);
			return $query->rowCount();
			
		} else {
			// single page ID or Page object
			$pageID = (int) "$pageID";
			$query = $database->prepare("UPDATE pages SET status=$sql WHERE id=:page_id");
			$query->bindValue(":page_id", $pageID, \PDO::PARAM_INT);
			$database->execute($query);
			$rowCount = $query->rowCount();
		}
		
		if(!$recursive) return $rowCount;
		
		// recursive mode assumed from this point forward
		$parentIDs = array($pageID);

		do {
			$parentID = array_shift($parentIDs);

			// update all children to have the same status
			$query = $database->prepare("UPDATE pages SET status=$sql WHERE parent_id=:parent_id");
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
				$parentIDs[] = (int) $row['id'];
			}
			
			$query->closeCursor();
			
		} while(count($parentIDs));
		
		return $rowCount;
	}
	
	/**
	 * Permanently delete a page and it's fields.
	 *
	 * Unlike trash(), pages deleted here are not restorable.
	 *
	 * If you attempt to delete a page with children, and don't specifically set the $recursive param to True, then
	 * this method will throw an exception. If a recursive delete fails for any reason, an exception will be thrown.
	 *
	 * @param Page $page
	 * @param bool|array $recursive If set to true, then this will attempt to delete all children too.
	 *   If you don't need this argument, optionally provide $options array instead. 
	 * @param array $options Optional settings to change behavior:
	 *   - uncacheAll (bool): Whether to clear memory cache after delete (default=false)
	 *   - recursive (bool): Same as $recursive argument, may be specified in $options array if preferred.
	 * @return bool|int Returns true (success), or integer of quantity deleted if recursive mode requested.
	 * @throws WireException on fatal error
	 *
	 */
	public function delete(Page $page, $recursive = false, array $options = array()) {
		
		$defaults = array(
			'uncacheAll' => false, 
			'recursive' => is_bool($recursive) ? $recursive : false,
		);
	
		if(is_array($recursive)) $options = $recursive; 	
		$options = array_merge($defaults, $options);

		$this->isDeleteable($page, true); // throws WireException
		$numDeleted = 0;

		if($page->numChildren) {
			if(!$options['recursive']) {
				throw new WireException("Can't delete Page $page because it has one or more children.");
			} else foreach($page->children("include=all") as $child) {
				/** @var Page $child */
				if($this->pages->delete($child, true, $options)) {
					$numDeleted++;
				} else {
					throw new WireException("Error doing recursive page delete, stopped by page $child");
				}
			}
		}

		// trigger a hook to indicate delete is ready and WILL occur
		$this->pages->deleteReady($page);

		foreach($page->fieldgroup as $field) {
			if(!$field->type->deletePageField($page, $field)) {
				$this->error("Unable to delete field '$field' from page '$page'");
			}
		}

		try {
			if(PagefilesManager::hasPath($page)) $page->filesManager->emptyAllPaths();
		} catch(\Exception $e) {
		}

		/** @var PagesAccess $access */
		$access = $this->wire(new PagesAccess());
		$access->deletePage($page);

		$database = $this->wire('database');

		$query = $database->prepare("DELETE FROM pages_parents WHERE pages_id=:page_id");
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT);
		$query->execute();

		$query = $database->prepare("DELETE FROM pages WHERE id=:page_id LIMIT 1"); // QA
		$query->bindValue(":page_id", $page->id, \PDO::PARAM_INT);
		$query->execute();

		$this->pages->sortfields()->delete($page);
		$page->setTrackChanges(false);
		$page->status = Page::statusDeleted; // no need for bitwise addition here, as this page is no longer relevant
		$this->pages->deleted($page);
		$numDeleted++;
		if($options['uncacheAll']) $this->pages->uncacheAll($page);
		$this->pages->debugLog('delete', $page, true);

		return $options['recursive'] ? $numDeleted : true;
	}
	
	/**
	 * Clone an entire page (including fields, file assets, and optionally children) and return it.
	 *
	 * @param Page $page Page that you want to clone
	 * @param Page $parent New parent, if different (default=same parent)
	 * @param bool $recursive Clone the children too? (default=true)
	 * @param array|string $options Optional options that can be passed to clone or save
	 * 	- forceID (int): force a specific ID
	 * 	- set (array): Array of properties to set to the clone (you can also do this later)
	 * 	- recursionLevel (int): recursion level, for internal use only.
	 * @return Page the newly cloned page or a NullPage() with id=0 if unsuccessful.
	 * @throws WireException|\Exception on fatal error
	 *
	 */
	public function _clone(Page $page, Page $parent = null, $recursive = true, $options = array()) {

		if(is_string($options)) $options = Selectors::keyValueStringToArray($options);
		if(!isset($options['recursionLevel'])) $options['recursionLevel'] = 0; // recursion level
		if($parent === null) $parent = $page->parent; 

		if(isset($options['set']) && isset($options['set']['name']) && strlen($options['set']['name'])) {
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
			if($page->hasField($field->name)) $page->get($field->name);
		}

		// clone in memory
		$copy = clone $page;
		$copy->setQuietly('_cloning', $page);
		$copy->id = isset($options['forceID']) ? (int) $options['forceID'] : 0;
		$copy->setIsNew(true);
		$copy->name = $name;
		$copy->parent = $parent;
		$copy->of(false);
		$copy->set('numChildren', 0);
		
		// set any properties indicated in options	
		if(isset($options['set']) && is_array($options['set'])) {
			foreach($options['set'] as $key => $value) {
				$copy->set($key, $value);
			}
			if(isset($options['set']['modified'])) {
				$options['quiet'] = true; // allow for modified date to be set
				if(!isset($options['set']['modified_users_id'])) {
					// since 'quiet' also allows modified user to be set, make sure that it
					// is still updated, if not specifically set. 
					$copy->modified_users_id = $this->wire('user')->id;
				}
			}
			if(isset($options['set']['modified_users_id'])) {
				$options['quiet'] = true; // allow for modified user to be set
				if(!isset($options['set']['modified'])) {
					// since 'quiet' also allows modified tie to be set, make sure that it
					// is still updated, if not specifically set. 
					$copy->modified = time();
				}
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
			do {
				$children = $page->children("include=all, start=$start, limit=$limit");
				$numChildren = $children->count();
				foreach($children as $child) {
					/** @var Page $child */
					$this->pages->clone($child, $copy, true, array('recursionLevel' => $options['recursionLevel'] + 1));
				}
				$start += $limit;
				$this->pages->uncacheAll();
			} while($numChildren);
		}

		$copy->parentPrevious = null;

		// update pages_parents table, only when at recursionLevel 0 since pagesParents is already recursive
		if($recursive && $options['recursionLevel'] === 0) {
			$this->saveParents($copy->id, $copy->numChildren);
		}
		
		if($options['recursionLevel'] === 0) {
			if($copy->parent()->sortfield() == 'sort') {
				$this->sortPage($copy, $copy->sort, true);
			}
		}

		$copy->setQuietly('_cloning', null);
		$copy->of($of);
		$page->of($of);
		$copy->resetTrackChanges();
		$this->pages->cloned($page, $copy);
		$this->pages->debugLog('clone', "page=$page, parent=$parent", $copy);

		return $copy;
	}

	/**
	 * Update page modification time to now (or the given modification time)
	 * 
	 * @param Page|PageArray|array $pages May be Page, PageArray or array of page IDs (integers)
	 * @param null|int|string $modified Omit to update to now, or specify unix timestamp or strtotime() recognized time string
	 * @throws WireException if given invalid format for $modified argument or failed database query
	 * @return bool True on success, false on fail
	 * 
	 */
	public function touch($pages, $modified = null) {
		
		$ids = array();
		
		if($pages instanceof Page) {
			$ids[] = (int) $pages->id;
		} else {
			foreach($pages as $page) {
				if(is_int($page)) {
					$ids[] = (int) $page;
				} else if($page instanceof Page) {
					$ids[] = (int) $page->id;
				} else {
					// invalid
				}
			}
		}
		
		if(!count($ids)) return false;
		
		$sql = 'UPDATE pages SET modified=';
		if(is_null($modified)) {
			$sql .= 'NOW() ';
		} else if(is_int($modified) || ctype_digit($modified)) {
			$modified = (int) $modified;
			$sql .= ':modified ';
		} else if(is_string($modified)) {
			$modified = strtotime($modified);
			if(!$modified) throw new WireException("Unrecognized time format provided to Pages::touch()");
			$sql .= ':modified ';
		}
		
		$sql .= 'WHERE id IN(' . implode(',', $ids) . ')';
		$query = $this->wire('database')->prepare($sql);
		if(strpos($sql, ':modified')) $query->bindValue(':modified', date('Y-m-d H:i:s', $modified));
		
		return $this->wire('database')->execute($query);
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
	 * @param Page $child Page that you want to move.
	 * @param Page|int|string $parent Parent to move it under (may be Page object, path string, or ID integer).
	 * @param array $options Options to modify behavior (see PagesEditor::save for options). 
	 * @return bool|array True on success or false if not necessary.
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
	 * @param Page $page Page that you want to set the sort value for
	 * @param int|null $sort New sort value for page or null to pull from $page->sort
	 * @param bool $after If another page already has the sort, make $page go after it rather than before it? (default=false)
	 * @throws WireException if given invalid arguments
	 * @return int Number of sibling pages that had to have sort adjusted
	 * 
	 */
	public function sortPage(Page $page, $sort = null, $after = false) {
	
		$database = $this->wire('database');

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
	 * @param Page $parent
	 * @return int
	 * 
	 */
	public function sortRebuild(Page $parent) {
		
		if(!$parent->id || !$parent->numChildren) return 0;
		$database = $this->wire('database');
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
	 * Hook after Fieldtype::sleepValue to remove MB4 characters when present and applicable
	 * 
	 * This hook is only used if $config->dbStripMB4 is true and $config->dbEngine is not “utf8mb4”. 
	 * 
	 * @param HookEvent $event
	 * 
	 */
	protected function hookFieldtypeSleepValueStripMB4(HookEvent $event) {
		$event->return = $this->wire('sanitizer')->removeMB4($event->return); 
	}
}
