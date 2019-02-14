<?php namespace ProcessWire;

/**
 * Class ProcessPageListerBookmarks
 * 
 * Helper class for managing ProcessPageLister bookmarks
 * 
 */
class ProcessPageListerBookmarks extends Wire {
	
	protected $lister; 
	
	public function __construct(ProcessPageLister $lister) {
		$this->lister = $lister;
	}

	/**
	 * Get configured bookmarks allowed for current user
	 * 
	 * @return array
	 * 
	 */
	public function getBookmarks() {

		$page = $this->wire('page');
		$key = "_$page->id";
		$data = $this->wire('modules')->getModuleConfigData('ProcessPageLister');
		$_bookmarks = isset($data['bookmarks'][$key]) ? $data['bookmarks'][$key] : array();
		$bookmarks = array();

		foreach($_bookmarks as $n => $bookmark) {
			$n = (int) ltrim($n, '_');
			$bookmark['url'] = $this->wire('page')->url . "?bookmark=$n";
			$bookmarks[$n] = $bookmark;
		}

		if(!$this->wire('user')->isSuperuser()) {
			$userRoles = $this->wire('user')->roles;
			foreach($bookmarks as $n => $bookmark) {
				$allowBookmark = false;
				if(empty($bookmark['roles'])) {
					$allowBookmark = true; 
				} else foreach($bookmark['roles'] as $roleID) {
					foreach($userRoles as $userRole) {
						if($userRole->id == $roleID) {
							$allowBookmark = true;
							break;
						}
					}
				}
				if(!$allowBookmark) unset($bookmarks[$n]);
			}
		}

		return $bookmarks;
	}

	/**
	 * Build the bookmarks tab and form contained within it
	 *
	 * @return InputfieldForm
	 *
	 */
	public function buildBookmarkListForm() {

		/** @var InputfieldForm $form */
		$form = $this->modules->get('InputfieldForm');
		$form->attr('id', 'tab_bookmarks');
		$form->method = 'post';
		$form->action = './edit-bookmark/#tab_bookmarks';
		$form->class .= ' WireTab';
		$form->attr('title', $this->_x('Bookmarks', 'tab'));

		$user = $this->wire('user');
		$bookmarks = $this->getBookmarks();
		$superuser = $user->isSuperuser();
		$languages = $this->wire('languages');
		$languageID = $languages && !$user->language->isDefault() ? $user->language->id : '';

		if($superuser) {
			$fieldset = $this->buildBookmarkEditForm(0, $bookmarks);
			if(count($bookmarks)) $fieldset->collapsed = Inputfield::collapsedYes;
			$form->add($fieldset);
		}

		$f = $this->wire('modules')->get('InputfieldMarkup');
		$f->label = $form->attr('title');
		$f->icon = 'bookmark-o';
		$form->add($f);

		if(!count($bookmarks)) return $form;

		// render table of current bookmarks
		$table = $this->wire('modules')->get('MarkupAdminDataTable');
		$table->setID('table_bookmarks');
		$table->setSortable(false);
		$headerRow = array($this->_x('Bookmark', 'bookmark-th'));
		if($superuser) {
			$headerRow[] = $this->_x('Selector', 'bookmark-th');
			$headerRow[] = $this->_x('Columns', 'bookmark-th');
			$headerRow[] = $this->_x('Access', 'bookmark-th');
			$headerRow[] = $this->_x('Action', 'bookmark-th');
		}
		$table->headerRow($headerRow);
		foreach($bookmarks as $n => $bookmark) {
			$row = array();
			$title = $bookmark['title']; 
			if($languageID && !empty($bookmark["title$languageID"])) $title = $bookmark["title$languageID"];
			$row["$title\0"] = $bookmark['url'];
			if($superuser) {
				$selector = $bookmark['selector'];
				if(strpos($selector, 'template=') !== false && preg_match('/template=([\d\|]+)/', $selector, $matches)) {
					// make templates readable, for output purposes
					$t = '';
					foreach(explode('|', $matches[1]) as $templateID) {
						$template = $this->wire('templates')->get((int) $templateID);
						$t .= ($t ? '|' : '') . ($template ? $template->name : $templateID);
					}
					$selector = str_replace($matches[0], "template=$t", $selector);
				}
				if($bookmark['sort']) $selector .= ($selector ? ", " : "") . "sort=$bookmark[sort]";
				$row[] = $selector;
				$row[] = implode(', ', $bookmark['columns']);
				if(count($bookmark['roles']) < 2 && ((int) reset($bookmark['roles'])) === 0) {
					$row[] = $this->_x('all', 'bookmark-roles');
				} else if(count($bookmark['roles'])) {
					$row[] = $this->wire('pages')->getById($bookmark['roles'])->implode(', ', 'name');
				}
				$row[$this->_x('Edit', 'bookmark-action')] = "./edit-bookmark/?n=$n";
			}
			$table->row($row);
		}
		if($superuser) $f->appendMarkup = "<p class='detail'>" . $this->_('Superuser note: other users can see and click bookmarks, but may not add or edit them.') . "</p>";
		$f->value = $table->render();

		return $form;
	}

	/**
	 * Build the form needed to edit/add bookmarks
	 *
	 * @param int $bookmarkID Specify bookmark ID if editing existing bookmark
	 * @param array $bookmarks Optionally include list of all bookmarks to prevent this method from having to re-load them
	 * @return InputfieldWrapper
	 *
	 */
	protected function buildBookmarkEditForm($bookmarkID = 0, $bookmarks = array()) {
		
		$languages = $this->wire('languages');
		$fieldset = $this->wire('modules')->get('InputfieldFieldset');

		if($bookmarkID) {
			if(empty($bookmarks)) $bookmarks = $this->getBookmarks();
			$bookmark = isset($bookmarks[$bookmarkID]) ? $bookmarks[$bookmarkID] : array();
			if(empty($bookmark)) $bookmarkID = 0;
			$fieldset->label = $this->_('Edit Bookmark');
		} else {
			$bookmark = array();
			$fieldset->label = $this->_('Add New Bookmark');
		}

		$fieldset->icon = $bookmarkID ? 'bookmark-o' : 'plus-circle';
		if(!$bookmarkID) $fieldset->description = $this->_('Creates a new bookmark matching your current filters, columns and order.');

		$f = $this->wire('modules')->get('InputfieldText');
		$f->attr('name', 'bookmark_title');
		$f->label = $this->_x('Title', 'bookmark-editor'); // Bookmark title
		$f->required = true; 
		if($languages) $f->useLanguages = true;
		$fieldset->add($f);

		if($bookmarkID) {
			// editing existing bookmark
			$f->attr('value', $bookmark['title']);
			if($languages) foreach($languages as $language) {
				if($language->isDefault()) continue;
				$f->attr("value$language", isset($bookmark["title$language"]) ? $bookmark["title$language"] : "");
			}

			$f = $this->wire('modules')->get('InputfieldSelector');
			$f->attr('name', 'bookmark_selector');
			$f->label = $this->_x('What pages should this bookmark show?', 'bookmark-editor');
			$selector = $bookmark['selector'];
			if($bookmark['sort']) $selector .= ", sort=$bookmark[sort]";
			if($this->lister->initSelector && strpos($selector, $this->lister->initSelector) !== false) {
				$selector = str_replace($this->lister->initSelector, '', $selector); // ensure that $selector does not contain initSelector
			}
			if($this->lister->template) $f->initTemplate = $this->lister->template;
			$default = $this->lister->className() == 'ProcessPageLister';
			$f->preview = false;
			$f->allowSystemCustomFields = true;
			$f->allowSystemTemplates = true;
			$f->allowSubfieldGroups = $default ? false : true;
			$f->allowSubselectors = $default ? false : true;
			$f->showFieldLabels = $this->lister->useColumnLabels ? 1 : 0;
			$f->initValue = $this->lister->initSelector;
			$f->attr('value', $selector);
			$fieldset->add($f);

			$f = $this->lister->buildColumnsField();
			$f->attr('name', 'bookmark_columns');
			$f->attr('value', $bookmark['columns']);
			$f->label = $this->_x('Columns', 'bookmark-editor');
			$fieldset->add($f);
		}

		$f = $this->wire('modules')->get('InputfieldAsmSelect');
		$f->attr('name', 'bookmark_roles');
		$f->label = $this->_x('Access', 'bookmark-editor');
		$f->icon = 'key';
		$f->description = $this->_('What user roles will see this bookmark? If no user roles are selected, then all roles with permission to use this Lister can view the bookmark.');
		foreach($this->wire('roles') as $role) {
			if($role->name != 'guest') $f->addOption($role->id, $role->name);
		}
		if($bookmarkID) $f->attr('value', $bookmark['roles']);
		$f->collapsed = Inputfield::collapsedBlank;
		$fieldset->add($f);

		if($bookmarkID) {

			/** @var InputfieldAsmSelect $f */
			$f = $this->wire('modules')->get('InputfieldAsmSelect');
			$f->attr('name', 'bookmarks_sort');
			$f->label = $this->_('Bookmarks sort order');
			$f->icon = 'sort';
			$f->setAsmSelectOption('removeLabel', '');
			$value = array();
			foreach($bookmarks as $n => $b) {
				$f->addOption($n, $b['title']);
				$value[] = $n;
			}
			$f->attr('value', $value);
			$f->collapsed = Inputfield::collapsedYes;
			$fieldset->add($f);

			$f = $this->wire('modules')->get('InputfieldCheckbox');
			$f->attr('name', 'delete_bookmark');
			$f->label = $this->_x('Delete', 'bookmark-editor');
			$f->label2 = $this->_('Delete this bookmark?');
			$f->icon = 'trash-o';
			$f->attr('value', $bookmarkID);
			$f->collapsed = Inputfield::collapsedYes;
			$fieldset->add($f);
		}

		$submit = $this->wire('modules')->get('InputfieldSubmit');
		$submit->attr('name', 'submit_bookmark');
		$submit->icon = 'bookmark-o';
		$fieldset->add($submit);

		return $fieldset;
	}

	/**
	 * Implementation for editing a bookmark, URL segment: ./edit-bookmark/?n=bookmarkID
	 *
	 * @return string
	 * @throws WirePermissionException
	 *
	 */
	public function editBookmark() {

		if(!$this->wire('user')->isSuperuser()) throw new WirePermissionException("Only superuser can edit bookmarks");

		if($this->wire('input')->post('bookmark_title')) return $this->saveBookmark();

		$bookmarkID = $this->wire('input')->get->int('n');

		$form = $this->wire('modules')->get('InputfieldForm');
		$form->attr('action', './');

		$fieldset = $this->buildBookmarkEditForm($bookmarkID);
		$form->add($fieldset);
		$this->lister->headline($fieldset->label);

		$f = $this->wire('modules')->get('InputfieldHidden');
		$f->attr('name', 'bookmark_id');
		$f->attr('value', $bookmarkID);
		$form->add($f);

		// prevent Lister JS from initializing for this url segment
		$form->appendMarkup = '<script>ProcessLister.initialized = true;</script>';

		return $form->render();
	}

	/**
	 * Save a bookmark posted by ./edit-bookmark/
	 *
	 * Performs redirect after saving
	 *
	 */
	protected function saveBookmark() {

		$input = $this->wire('input');
		$sanitizer = $this->wire('sanitizer');
		$page = $this->wire('page');

		$bookmarkID = $input->post->int('bookmark_id');
		$bookmarkTitle = $input->post->text('bookmark_title');

		if(!$bookmarkID && empty($bookmarkTitle)) {
			$this->wire('session')->redirect('../#tab_bookmarks');
			return;
		}

		$bookmarkSort = '';
		$textOptions = array('maxLength' => 1024, 'stripTags' => false); 
		$bookmarkSelector = $bookmarkID ? $input->post->text('bookmark_selector', $textOptions) : $this->lister->getSelector();
		if(preg_match('/\bsort=([-_.a-zA-Z]+)/', $bookmarkSelector, $matches)) $bookmarkSort = $matches[1];
		$bookmarkSelector = preg_replace('/\b(include|sort|limit)=[^,]+,?/', '', $bookmarkSelector);
		if($this->lister->initSelector && strpos($bookmarkSelector, $this->lister->initSelector) !== false) {
			// ensure that $selector does not contain initSelector
			$bookmarkSelector = str_replace($this->lister->initSelector, '', $bookmarkSelector);
		}
		$bookmarkSelector = str_replace(', , ', ', ', $bookmarkSelector);

		if($bookmarkID) {
			$bookmarkColumns = $input->post('bookmark_columns');
			foreach($bookmarkColumns as $cnt => $column) {
				$column = $sanitizer->name($column);
				if(empty($column)) {
					unset($bookmarkColumns[$cnt]);
				} else {
					$bookmarkColumns[$cnt] = $column;
				}
			}
			$bookmarkColumns = array_values($bookmarkColumns);
		} else {
			$bookmarkColumns = $this->lister->columns;
		}

		$bookmark = array(
			'title' => $bookmarkTitle,
			'selector' => trim($bookmarkSelector, ", "),
			'columns' => $bookmarkColumns,
			'sort' => $bookmarkSort,
			'roles' => $input->post->intArray('bookmark_roles')
		);
		
		$languages = $this->wire('languages');
		if($languages) foreach($languages as $language) {
			if($language->isDefault()) continue;
			$bookmark["title$language"] = $input->post->text("bookmark_title__$language"); 
		}

		$data = $this->wire('modules')->getModuleConfigData('ProcessPageLister');
		$_bookmarks = isset($data['bookmarks']) ? $data['bookmarks'] : array();

		foreach($_bookmarks as $pageID => $bookmarks) {
			// remove bookmarks for Lister pages that no longer exist
			$pageID = (int) ltrim($pageID, '_');
			if($pageID == $page->id) continue;
			if(!$this->wire('pages')->get($pageID)->id) unset($_bookmarks[$pageID]);
		}

		$bookmarks = isset($_bookmarks["_$page->id"]) ? $_bookmarks["_$page->id"] : array();

		if($bookmarkID) {
			$n = $bookmarkID;
		} else {
			$n = time();
			while(isset($bookmarks[$n])) $n++;
		}

		$bookmarks["_$n"] = $bookmark;

		// update sort order of all bookmarks
		if($input->post->bookmarks_sort) {
			$sorted = array();
			foreach($input->post->intArray('bookmarks_sort') as $bmid) {
				$bm = $bookmarks["_$bmid"];
				$sorted["_$bmid"] = $bm;
			}
			$bookmarks = $sorted;
		}

		if($bookmarkID && $input->post('delete_bookmark') == $bookmarkID) {
			unset($bookmarks["_$n"]);
			$this->message(sprintf($this->_('Deleted bookmark: %s'), $bookmarkTitle));
			$bookmarkID = 0;
		} else {
			$this->message(sprintf($this->_('Saved bookmark: %s'), $bookmarkTitle));
		}

		$_bookmarks["_$page->id"] = $bookmarks;
		$data['bookmarks'] = $_bookmarks;

		$this->wire('modules')->saveModuleConfigData('ProcessPageLister', $data);
		$this->wire('session')->redirect("../?bookmark=$bookmarkID#tab_bookmarks");
	}
}