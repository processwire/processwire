<?php namespace ProcessWire;

/**
 * Class ProcessPageListerBookmarks
 * 
 * Helper class for managing ProcessPageLister bookmarks
 * 
 */
class ProcessPageListerBookmarks extends Wire {

	/**
	 * @var ProcessPageLister
	 *
	 */
	protected $lister;

	/**
	 * @var ListerBookmarks
	 *
	 */
	protected $bookmarks;

	/**
	 * @var Page
	 *
	 */
	protected $page;

	/**
	 * @var User
	 *
	 */
	protected $user;

	/**
	 * Construct
	 *
	 * @param ProcessPageLister $lister
	 *
	 */
	public function __construct(ProcessPageLister $lister) {
		require_once(__DIR__ . '/ListerBookmarks.php');
		$this->lister = $lister;
		$this->page = $lister->wire()->page;
		$this->user = $lister->wire()->user;
		$this->bookmarks = new ListerBookmarks($this->page, $this->user);
		parent::__construct();
	}

	/**
	 * @return ListerBookmarks
	 *
	 */
	public function bookmarks() {
		return $this->bookmarks;
	}

	/**
	 * Set the Lister page that bookmarks will be for
	 *
	 * @param Page $page
	 *
	 */
	public function setPage(Page $page) {
		$this->page = $page;
		$this->bookmarks->setPage($page);
	}

	/**
	 * Set user that bookmarks will be for
	 *
	 * @param User $user
	 *
	 */
	public function setUser(User $user) {
		$this->user = $user;
		$this->bookmarks->setUser($user);
	}

	/**
	 * Build the bookmarks tab and form contained within it
	 *
	 * @return InputfieldForm
	 *
	 */
	public function buildBookmarkListForm() {

		$sanitizer = $this->wire()->sanitizer;
		$pages = $this->wire()->pages;
		$modules = $this->wire()->modules;
		$languages = $this->wire()->languages;
		$user = $this->wire()->user;

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->attr('id', 'tab_bookmarks');
		$form->method = 'post';
		$form->action = './edit-bookmark/#tab_bookmarks';
		$form->class .= ' WireTab';
		$form->attr('title', $this->_x('Bookmarks', 'tab'));

		$publicBookmarks = $this->bookmarks->getPublicBookmarks();
		$ownedBookmarks = $this->bookmarks->getOwnedBookmarks();
		$numBookmarks = count($publicBookmarks) + count($ownedBookmarks);

		$languageID = $languages && !$user->language->isDefault() ? $user->language->id : '';

		$addBookmarkFieldset = $this->buildBookmarkEditForm(0);
		$addBookmarkFieldset->collapsed = $numBookmarks ? Inputfield::collapsedYes : Inputfield::collapsedNo;
		$form->add($addBookmarkFieldset);

		$bookmarksByType = array(
			ListerBookmarks::typeOwned => $ownedBookmarks,
			ListerBookmarks::typePublic => $publicBookmarks,
		);

		$iconsByType = array(
			ListerBookmarks::typeOwned => 'user-circle-o',
			ListerBookmarks::typePublic => 'bookmark-o',
		);

		$typeLabels = array(
			ListerBookmarks::typeOwned => $this->_('My bookmarks'),
			ListerBookmarks::typePublic => $this->_('Public bookmarks')
		);

		foreach($bookmarksByType as $bookmarkType => $bookmarks) {

			if(empty($bookmarks)) continue;

			/** @var InputfieldMarkup $f */
			$f = $modules->get('InputfieldMarkup');
			$f->label = $typeLabels[$bookmarkType];
			$f->icon = $iconsByType[$bookmarkType];

			$headerRow = array(
				0 => $this->_x('Bookmark', 'bookmark-th'),
				1 => $this->_x('Description', 'bookmark-th'),
				2 => $this->_x('Access', 'bookmark-th'),
				3 => $this->_x('Actions', 'bookmark-th'),
			);

			/** @var MarkupAdminDataTable $table */
			$table = $modules->get('MarkupAdminDataTable');
			$table->setID('table_bookmarks_' . $bookmarkType);
			$table->setSortable(false);
			$table->setEncodeEntities(false);
			$table->headerRow($headerRow);

			foreach($bookmarks as $bookmarkID => $bookmark) {

				$row = array();
				if(!$this->bookmarks->isBookmarkViewable($bookmark)) continue;

				// title column
				$title = $bookmark['title'];
				if($languageID && !empty($bookmark["title$languageID"])) $title = $bookmark["title$languageID"];
				$title = $sanitizer->entities($title);
				$viewUrl = $this->bookmarks->getBookmarkUrl($bookmarkID, $this->user);
				$row["$title\0"] = $viewUrl;

				// description column
				$desc = $bookmark['desc'];
				if($languageID && !empty($bookmark["desc$languageID"])) $desc = $bookmark["desc$languageID"];
				if(empty($desc)) {
					$selector = $this->bookmarks->readableBookmarkSelector($bookmark);
					$columns = implode(', ', $bookmark['columns']);
					$desc = "$selector ($columns)";
				}
				$row[] = $sanitizer->entities($desc);

				// access column (public bookmarks only)
				if($bookmark['type'] == ListerBookmarks::typePublic) {
					if(count($bookmark['roles']) < 2 && ((int) reset($bookmark['roles'])) === 0) {
						$row[] = $this->_x('all', 'bookmark-roles');
					} else if(count($bookmark['roles'])) {
						$row[] = $pages->getById($bookmark['roles'])->implode(', ', 'name');
					}
				} else {
					$row[] = $this->_('you');
				}

				// actions column
				$actions = array();
				$actions[] = "<a href='$viewUrl'>" . $this->_x('View', 'bookmark-action') . "</a>";
				if($this->bookmarks->isBookmarkEditable($bookmark)) {
					$editUrl = $this->bookmarks->getBookmarkEditUrl($bookmarkID);
					$actions[] = "<a href='$editUrl'>" . $this->_x('Modify', 'bookmark-action') . "</a>";
					if($this->bookmarks->isBookmarkDeletable($bookmark)) {
						$actions[] = "<a href='$editUrl&delete=1'>" . $this->_x('Delete', 'bookmark-action') . "</a>";
					}
				}

				$actions = implode(' &nbsp;/&nbsp; ', $actions);
				$row[] = $actions;

				$table->row($row);
			}

			$f->value = $table->render();

			$form->add($f);
		}

		return $form;
	}

	/**
	 * Build form for deleting a bookmark
	 *
	 * @param int $bookmarkID Bookmark ID
	 *
	 * @return InputfieldFieldset
	 * @throws WireException
	 * @throws WirePermissionException
	 *
	 */
	protected function buildBookmarkDeleteForm($bookmarkID) {
		
		$modules = $this->wire()->modules;

		$bookmark = $this->bookmarks->getBookmark($bookmarkID);
		if(!$bookmark) throw new WireException('Unknown bookmark');

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');
		$fieldset->icon = 'trash-o';
		$fieldset->label = $this->_('Please check the box to confirm you want to delete this bookmark');

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'delete_bookmark');
		$f->label = $this->_('Delete this bookmark?');
		$f->icon = 'trash-o';
		$f->attr('value', $bookmarkID);
		$fieldset->add($f);

		/** @var InputfieldSubmit $submit */
		$submit = $modules->get('InputfieldSubmit');
		$submit->attr('name', 'submit_delete_bookmark');
		$submit->icon = 'trash-o';
		$fieldset->add($submit);

		/** @var InputfieldButton $cancel */
		$cancel = $modules->get('InputfieldButton');
		$cancel->href = '../#tab_bookmarks';
		$cancel->setSecondary(true);
		$cancel->attr('value', $this->_('Cancel'));
		$fieldset->add($cancel);

		return $fieldset;
	}

	/**
	 * Build the form needed to edit/add bookmarks
	 *
	 * @param int $bookmarkID Specify bookmark ID if editing existing bookmark
	 *
	 * @return InputfieldWrapper
	 *
	 */
	protected function buildBookmarkEditForm($bookmarkID = 0) {

		$modules = $this->wire()->modules;
		$languages = $this->wire()->languages;
		$user = $this->wire()->user;

		/** @var InputfieldFieldset $fieldset */
		$fieldset = $modules->get('InputfieldFieldset');

		if($bookmarkID) {
			$bookmark = $this->bookmarks->getBookmark($bookmarkID);
			if(!$bookmark) throw new WireException("Unknown bookmark");
			if(!$this->bookmarks->isBookmarkEditable($bookmark)) throw new WirePermissionException('Bookmark is not editable');
			$fieldset->label = $this->_('Edit Bookmark');
		} else {
			$bookmark = array();
			$fieldset->label = $this->_('Add New Bookmark');
			$fieldset->description = $this->_('Creates a new bookmark matching your current filters, columns and order.');
		}

		$fieldset->icon = $bookmarkID ? 'bookmark-o' : 'plus-circle';

		/** @var InputfieldText $titleField */
		$titleField = $modules->get('InputfieldText');
		$titleField->attr('name', 'bookmark_title');
		$titleField->label = $this->_x('Title', 'bookmark-editor'); // Bookmark title
		$titleField->required = true;
		if($languages) $titleField->useLanguages = true;
		$fieldset->add($titleField);

		/** @var InputfieldText $descField */
		$descField = $modules->get('InputfieldText');
		$descField->attr('name', 'bookmark_desc');
		$descField->label = $this->_x('Description', 'bookmark-editor'); // Bookmark title
		if($languages) $descField->useLanguages = true;
		$fieldset->add($descField);

		if($bookmarkID) {
			// editing existing bookmark
			$titleField->attr('value', $bookmark['title']);
			$descField->attr('value', $bookmark['desc']);

			if($languages) {
				foreach($languages as $language) {
					/** @var Language $language */
					if($language->isDefault()) continue;
					$titleField->attr("value$language", isset($bookmark["title$language"]) ? $bookmark["title$language"] : "");
					$descField->attr("value$language", isset($bookmark["desc$language"]) ? $bookmark["desc$language"] : "");
				}
			}

			if($user->isSuperuser()) {
				/** @var InputfieldSelector $f */
				$f = $modules->get('InputfieldSelector');
				$f->attr('name', 'bookmark_selector');
				$f->label = $this->_x('What pages should this bookmark show?', 'bookmark-editor');
				$selector = $bookmark['selector'];
				if($bookmark['sort']) $selector .= ", sort=$bookmark[sort]";
				if($this->lister->initSelector) { 
					$initSelector = $this->lister->initSelector;
					if(strpos($selector, $initSelector) === false) {
						$initSelector = trim(preg_replace('![,\s]*\binclude=(all|unpublished|hidden)\b!i', '', $initSelector), ', ');
					}
					if(strpos($selector, $initSelector) !== false) {
						$selector = str_replace($initSelector, '', $selector); // ensure that $selector does not contain initSelector
					}
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

			/** @var InputfieldHidden $f */
			$f = $modules->get('InputfieldHidden');
			$f->attr('name', 'bookmark_type');
			$f->attr('value', (int) $bookmark['type']);
			$fieldset->add($f);

		} else {
			// add new bookmark
			if($user->isSuperuser()) {
				/** @var InputfieldRadios $f */
				$f = $modules->get('InputfieldRadios');
				$f->attr('name', 'bookmark_type');
				$f->label = $this->_('Bookmark type');
				$f->addOption(ListerBookmarks::typeOwned, $this->_('Private'));
				$f->addOption(ListerBookmarks::typePublic, $this->_('Public'));
				$f->attr('value', isset($bookmark['type']) ? (int) $bookmark['type'] : ListerBookmarks::typeOwned);
				$f->optionColumns = 1;
				$fieldset->add($f);
			}
		}

		if($user->isSuperuser()) {
			/** @var InputfieldAsmSelect $f */
			$f = $modules->get('InputfieldAsmSelect');
			$f->attr('name', 'bookmark_roles');
			$f->label = $this->_x('Access', 'bookmark-editor');
			$f->icon = 'key';
			$f->description = $this->_('What user roles will see this bookmark? If no user roles are selected, then all roles with permission to use this Lister can view the bookmark.');
			foreach($this->wire()->roles as $role) {
				if($role->name != 'guest') $f->addOption($role->id, $role->name);
			}
			if($bookmarkID) $f->attr('value', $bookmark['roles']);
			$f->collapsed = Inputfield::collapsedBlank;
			$f->showIf = 'bookmark_type=' . ListerBookmarks::typePublic;
			$fieldset->add($f);
		}

		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->attr('name', 'bookmark_share');
		$f->label = $this->_('Allow other users to access this bookmark URL?');
		$f->description = $this->_('If you send the bookmark URL to someone else that is already logged in to the admin, they can view the bookmark if you check this box.');
		if($bookmarkID) {
			$f->notes = sprintf(
				$this->_('Shareable bookmark URL: [View](%s)'),
				$this->page->httpUrl() . str_replace($this->page->url, '', $this->bookmarks->getBookmarkUrl($bookmarkID, $this->user))
			);
		}
		if(empty($bookmark['share'])) {
			$f->collapsed = Inputfield::collapsedYes;
		} else {
			$f->attr('checked', 'checked');
		}
		$f->showIf = 'bookmark_type=' . ListerBookmarks::typeOwned;
		$fieldset->add($f);

		if($bookmarkID) {
			$bookmarks = $bookmark['type'] == ListerBookmarks::typePublic ? $this->bookmarks->getPublicBookmarks() : $this->bookmarks->getOwnedBookmarks();
			// option for changing the order of bookmarks
			if(count($bookmarks) > 1) {
				/** @var InputfieldAsmSelect $f */
				$f = $modules->get('InputfieldAsmSelect');
				$f->attr('name', 'bookmarks_sort');
				$f->label = $this->_('Order');
				$f->icon = 'sort';
				$f->setAsmSelectOption('removeLabel', '');
				$value = array();
				foreach($bookmarks as $bmid => $bm) {
					$f->addOption($bmid, $bm['title']);
					$value[] = $bmid;
				}
				$f->attr('value', $value);
				$fieldset->add($f);
			}
		}

		/** @var InputfieldSubmit $submit */
		$submit = $modules->get('InputfieldSubmit');
		$submit->attr('name', 'submit_save_bookmark');
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
	public function executeEditBookmark() {

		$input = $this->wire()->input;
		$modules = $this->wire()->modules;
		$session = $this->wire()->session;

		$deleteBookmarkID = $this->bookmarks->_bookmarkID($input->post('delete_bookmark'));
		if($deleteBookmarkID) {
			$session->CSRF()->validate();
			if($this->bookmarks->deleteBookmarkByID($deleteBookmarkID)) {
				$this->message($this->_('Deleted bookmark'));
			} else {
				$this->error($this->_('Bookmark is not deletable'));
			}
			$this->redirectToBookmarks();
			return '';
		}

		if($input->post('bookmark_title')) {
			$session->CSRF()->validate();
			$this->executeSaveBookmark();
			return '';
		}

		$bookmarkID = $this->bookmarks->_bookmarkID($input->get('bookmark'));
		$bookmark = $this->bookmarks->getBookmark($bookmarkID);

		if(!$bookmark) $this->redirectToBookmarks();
		if(!$this->bookmarks->isBookmarkEditable($bookmark)) throw new WirePermissionException("Bookmark not editable");

		/** @var InputfieldForm $form */
		$form = $modules->get('InputfieldForm');
		$form->attr('action', './');

		if($input->get('delete')) {
			$fieldset = $this->buildBookmarkDeleteForm($bookmarkID);
			$this->lister->headline($bookmark['title']);
		} else {
			$fieldset = $this->buildBookmarkEditForm($bookmarkID);
			$this->lister->headline($fieldset->label);
		}
		$form->add($fieldset);

		/** @var InputfieldHidden $f */
		$f = $modules->get('InputfieldHidden');
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
	protected function executeSaveBookmark() {

		$input = $this->wire()->input;
		$sanitizer = $this->wire()->sanitizer;
		$languages = $this->wire()->languages;

		$bookmarkID = $this->bookmarks->_bookmarkID($input->post('bookmark_id'));
		$bookmarkTitle = $input->post->text('bookmark_title');
		$bookmarkDesc = $input->post->text('bookmark_desc');

		if(!$bookmarkID && empty($bookmarkTitle)) {
			$this->redirectToBookmarks();
			return;
		}

		if($bookmarkID) {
			$existingBookmark = $this->bookmarks->getBookmark($bookmarkID);
			if(!$existingBookmark || !$this->bookmarks->isBookmarkEditable($existingBookmark)) {
				throw new WirePermissionException("Bookmark not editable");
			}
		} else {
			$existingBookmark = null;
		}

		$bookmarkSort = '';
		$textOptions = array(
			'maxLength' => 1024,
			'stripTags' => false
		);

		if($this->user->isSuperuser()) {
			$bookmarkSelector = $bookmarkID ? $input->post->text('bookmark_selector', $textOptions) : $this->lister->getSelector();
		} else {
			$bookmarkSelector = $existingBookmark ? $existingBookmark['selector'] : $this->lister->getSelector();
		}

		if(preg_match('/\bsort=([-_.a-zA-Z]+)/', $bookmarkSelector, $matches)) $bookmarkSort = $matches[1];
		$bookmarkSelector = preg_replace('/\b(include|sort|limit)=[^,]+,?/', '', $bookmarkSelector);

		if($this->lister->initSelector && strpos($bookmarkSelector, $this->lister->initSelector) !== false) {
			// ensure that $selector does not contain initSelector
			$bookmarkSelector = str_replace($this->lister->initSelector, '', $bookmarkSelector);
		}

		$bookmarkSelector = str_replace(', , ', ', ', $bookmarkSelector);
		$bookmarkSelector = trim($bookmarkSelector, ', ');

		if($bookmarkID && $this->user->isSuperuser()) {
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
		} else if($bookmarkID && $existingBookmark) {
			$bookmarkColumns = $existingBookmark['columns'];
		} else {
			$bookmarkColumns = $this->lister->columns;
		}

		$bookmark = $this->bookmarks->_bookmark(array(
			'id' => $bookmarkID,
			'title' => $bookmarkTitle,
			'desc' => $bookmarkDesc,
			'selector' => $bookmarkSelector,
			'columns' => $bookmarkColumns,
			'sort' => $bookmarkSort,
			'share' => $input->post('bookmark_share') ? true : false
		));

		if($this->user->isSuperuser()) {
			$bookmark['type'] = $input->post->int('bookmark_type');
			$bookmark['roles'] = $input->post->intArray('bookmark_roles');
		} else {
			$bookmark['type'] = ListerBookmarks::typeOwned;
			$bookmark['roles'] = array();
		}

		if($languages) {
			foreach($languages as $language) {
				/** @var Language $language */
				if($language->isDefault()) continue;
				$bookmark["title$language"] = $input->post->text("bookmark_title__$language");
				$bookmark["desc$language"] = $input->post->text("bookmark_desc__$language");
			}
		}

		if($bookmark['type'] == ListerBookmarks::typeOwned) {
			$bookmarks = $this->bookmarks->getOwnedBookmarks();
		} else {
			$bookmarks = $this->bookmarks->getPublicBookmarks();
		}

		$typePrefix = $this->bookmarks->typePrefix($bookmark['type']);
		if(!$bookmarkID) $bookmarkID = $typePrefix . time(); // new bookmark
		$bookmarks[$bookmarkID] = $bookmark;

		// update sort order of all bookmarks
		if($input->post('bookmarks_sort')) {
			$sorted = array();
			foreach($input->post->array('bookmarks_sort') as $bmid) {
				$bmid = $this->bookmarks->_bookmarkID($bmid);
				if(!isset($bookmarks[$bmid])) continue;
				$bm = $bookmarks[$bmid];
				$sorted[$bmid] = $bm;
			}
			$bookmarks = $sorted;
		}

		if($bookmark['type'] == ListerBookmarks::typeOwned) {
			$this->bookmarks->saveOwnedBookmarks($bookmarks);
		} else {
			$this->bookmarks->savePublicBookmarks($bookmarks);
		}

		$this->redirectToBookmarks($bookmarkID);
	}

	protected function redirectToBookmarks($bookmarkID = '') {
		$url = $this->page->url;
		$session = $this->wire()->session;
		if($bookmarkID) {
			$session->location($url . "?bookmark=$bookmarkID#tab_bookmarks");
		} else {
			$session->location($url . '#tab_bookmarks');
		}
	}

	public function _bookmark(array $bookmark = array()) {
		return $this->bookmarks->_bookmark($bookmark);
	}
	
	public function _bookmarkID($bookmarkID) {
		return $this->bookmarks->_bookmarkID($bookmarkID);
	}

	public function getBookmark($bookmarkID, $type = null) {
		return $this->bookmarks->getBookmark($bookmarkID, $type);
	}
	
	public function getBookmarks() {
		return $this->bookmarks->getBookmarks();
	}
	
	public function isBookmarkViewable($bookmark) {
		return $this->bookmarks->isBookmarkViewable($bookmark);
	}
}
