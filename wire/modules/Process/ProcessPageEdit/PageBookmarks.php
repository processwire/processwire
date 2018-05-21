<?php namespace ProcessWire;

/**
 * Class PageBookmarks
 * 
 * Class for managing Page bookmarks, currently used by ProcessPageEdit and ProcessPageList
 * 
 */

class PageBookmarks extends Wire {

	/**
	 * @var Process
	 * 
	 */
	protected $process;

	/**
	 * @var array
	 * 
	 */
	protected $labels = array();

	/**
	 * @param Process $process
	 * 
	 */
	public function __construct(Process $process) {
		$this->process = $process; 
		$this->labels = array(
			'bookmarks' => $this->_('Bookmarks'),
			'edit-bookmarks' => $this->_('Edit Bookmarks'), 
			'all' => $this->_('all'), 
		);
	}
	
	/**
	 * Initialize/create the $options array for executeNavJSON() in Process modules
	 * 
	 * @param array $options
	 * @return array
	 *
	 */
	public function initNavJSON(array $options = array()) {

		$bookmarkFields = array();
		$bookmarksArray = array();
		$rolesArray = array();
		$data = $this->wire('modules')->getModuleConfigData($this->process);
		$iconKey = isset($options['iconKey']) ? $options['iconKey'] : '_icon';
		$classKey = isset($options['classKey']) ? $options['classKey'] : '_class';
		$options['classKey'] = $classKey;
		$options['iconKey'] = $iconKey;
		if(!isset($options['defaultIcon'])) $options['defaultIcon'] = 'arrow-circle-right';

		foreach($this->wire('user')->roles as $role) {
			if($role->name == 'guest') continue;
			$value = isset($data["bookmarks"]["_$role->id"]) ? $data["bookmarks"]["_$role->id"] : array();
			if(empty($value)) continue;
			$bookmarkFields[$role->name] = $value;
			$rolesArray[$role->name] = $role;
		}
		$bookmarkFields['bookmarks'] = isset($data['bookmarks']["_0"]) ? $data['bookmarks']["_0"] : array();

		$n = 0;
		foreach($bookmarkFields as $name => $bookmarkIDs) {
			$bookmarks = count($bookmarkIDs) ? $this->wire('pages')->getById($bookmarkIDs) : array();
			$role = isset($rolesArray[$name]) ? $rolesArray[$name] : null;
			foreach($bookmarks as $page) {
				if($this->process == 'ProcessPageEdit' && !$page->editable()) continue;
					else if($this->process == 'ProcessPageAdd' && !$page->addable()) continue;
					else if(!$page->listable()) continue;
				if(isset($bookmarksArray[$page->id])) continue;
				$icon = $page->template->getIcon();
				if(!$icon) $icon = $options['defaultIcon'];
				$page->setQuietly($iconKey, $icon);
				$page->setQuietly('_roleName', $role ? $role->name : $this->labels['all']);
				$bookmarksArray[$page->id] = $page;
			}
			$n++;
		}
		
		if(empty($options['add'])) {
			if($this->wire('user')->isSuperuser()) {
				$options['add'] = 'bookmarks/?role=0';
				$options['addLabel'] = $this->labels['bookmarks'];
				$options['addIcon'] = 'bookmark-o';
			} else {
				$options['add'] = null;
			}
		} else if($this->wire('user')->isSuperuser()) {
			$add = $this->wire(new WireData());
			$add->set('_icon', 'bookmark-o');
			$add->set('title', $this->labels['bookmarks']);
			$add->set('id', 'bookmark');
			$add->set($classKey, 'separator');
			array_unshift($bookmarksArray, $add);
		}
			
		if(isset($options['items'])) {
			$options['items'] = $options['items'] + $bookmarksArray;
		} else {
			$options['items'] = $bookmarksArray;
		}

		if(!isset($options['itemLabel'])) $options['itemLabel'] = 'title|name';
		if(!isset($options['sort'])) $options['sort'] = false;
		if(!isset($options['iconKey'])) $options['iconKey'] = '_icon';
	
		if(empty($options['edit'])) {
			$options['edit'] = $this->wire('config')->urls->admin . 'page/edit/?id={id}';
		}

		return $options; 
	}

	/**
	 * Render list of current bookmarks
	 * 
	 * @return string
	 * 
	 */
	public function listBookmarks() {
		
		$config = $this->wire('config');
		$config->styles->add($config->urls->ProcessPageEdit . 'PageBookmarks.css'); 
		$superuser = $this->wire('user')->isSuperuser();
		$out = '';
		$options = $this->initNavJSON();
		$noneHeadline = $this->_('There are currently no bookmarks defined'); 
		
		foreach($options['items'] as $item) {
			/** @var WireData $item */
			if($item->id == 'bookmark') continue;
			$url = str_replace('{id}', $item->id, $options['edit']);
			$icon = $item->_icon ? "<i class='fa fa-fw fa-$item->_icon'></i> " : "";
			$out .= 
				"<li class='$item->_class'>" . 
				"<a href='$url'>$icon" . $this->wire('sanitizer')->entities1($item->get('title|name')) . "</a>" . 
				"</li>";
		}
	
		$icon = "<i class='fa fa-fw fa-lg fa-bookmark-o'></i> ";
		if($out) {
			$out = "<h2>$icon" . $this->labels['bookmarks'] . "</h2><ul class='bookmarks'>$out</ul>";
		} else {
			$out = "<h2>$icon$noneHeadline</h2>";
		}
		
		if($superuser) {
			$button = $this->wire('modules')->get('InputfieldButton');
			$button->href = "./?role=0";
			$button->value = $this->labels['edit-bookmarks'];
			$button->icon = 'edit';
			$button->showInHeader();
			$out .= $button->render();
		}
		
		return $out;	
	}

	/**
	 * Provides the editor for bookmarks and returns InputfieldForm
	 * 
	 * @return InputfieldForm
	 * @throws WirePermissionException|WireException
	 * 
	 */
	public function editBookmarksForm() {
		
		$modules = $this->wire('modules');
		$roleID = $this->wire('input')->get('role');
		if(is_null($roleID) && $this->wire('input')->get('id') == 'bookmarks') $roleID = 0;
		$roleID = (int) $roleID; 

		if(!$this->wire('user')->isSuperuser()) throw new WirePermissionException("Superuser required to define bookmarks");
		$moduleInfo = $modules->getModuleInfo($this->process);
		$this->process->breadcrumb('../', $this->_($moduleInfo['title']));
		$this->process->breadcrumb('./', $this->labels['bookmarks']);
		
		$role = $roleID ? $this->wire('roles')->get($roleID) : $this->wire('pages')->newNullPage();
		if($roleID && !$role->id) throw new WireException("Unknown role");
		$allLabel = $this->_('everyone'); // All roles
		$data = $modules->getModuleConfigData($this->process);

		$headline = $this->labels['edit-bookmarks'];
		$title = sprintf($this->_('Bookmarks for: %s'), ($role->id ? $role->name : $allLabel));
		$this->process->headline($headline);
		$this->process->browserTitle($title);

		$form = $modules->get('InputfieldForm');
		$form->action = "./?role=$role->id";
		$form->addClass('InputfieldFormConfirm');
		$form->description = sprintf($this->_('%s Bookmark Editor'), __($moduleInfo['title'], '/wire/templates-admin/default.php'));
		$form->appendMarkup = "<p style='clear:both' class='detail'><br /><i class='fa fa-info-circle ui-priority-secondary'></i> " . 
			$this->_('Note that only superusers are able to see this editor.') . "</p>";

		$field = $modules->get('InputfieldPageListSelectMultiple');
		$field->attr('name', 'bookmarks');
		$field->label = $title;
		$field->icon = 'bookmark-o';
		$field->startLabel = $this->_('Add Bookmark');
		$field->description = $this->_('Click the "add bookmark" action below to select page(s) to add as bookmarks. If you want the bookmarks to only appear for a specific user role, first select the role above.');

		$class = $role->id ? '' : 'ui-state-disabled';
		$out = "<ul class='PageListActions actions'><li><a class='$class' href='./?role=0'>$allLabel</a></li>";

		foreach($this->wire('roles') as $r) {
			if($r->name == 'guest') continue;
			$class = $r->id == $role->id ? 'ui-state-disabled' : '';
			$o = "<a class='$class' href='./?role=$r->id'>$r->name</a>";
			$out .= "<li>$o</li>";
		}

		$out .= "</ul>";
		$field->prependMarkup = $out;

		if(!isset($data["bookmarks"])) $data["bookmarks"] = array();
		$value = isset($data["bookmarks"]["_$role->id"]) ? $data["bookmarks"]["_$role->id"] : array();
		if(!is_array($value)) $value = array();
		$field->attr('value', $value);
		$form->add($field);

		$submit = $modules->get('InputfieldSubmit');
		$submit->attr('name', 'submit_save_bookmarks');
		$submit->showInHeader();
		$form->add($submit);

		if($this->wire('input')->post('submit_save_bookmarks')) {
			// save bookmarks
			$form->processInput($this->wire('input')->post);
			$bookmarks = $field->attr('value');
			// clear out bookmarks for roles that no longer exist
			foreach($data["bookmarks"] as $_roleID => $_bookmarks) {
				if($_roleID == "_0") continue;
				$r = $this->wire('roles')->get((int) ltrim($_roleID, '_'));
				if(!$r->id) unset($data["bookmarks"][$_roleID]);
			}
			// update bookmarks for role
			$data["bookmarks"]["_$role->id"] = $bookmarks;
			// save to module config data
			$modules->saveModuleConfigData($this->process, $data);
			
			$this->message($this->_('Saved bookmarks'));
			$this->wire('session')->redirect("./?role=$role->id");
		}

		return $form;
	}

	/**
	 * Provides the editor or list for bookmarks and returns rendered markup
	 *
	 * @return string
	 * @throws WirePermissionException
	 *
	 */
	public function editBookmarks() {
		$roleID = $this->wire('input')->get('role');
		if(is_null($roleID)) {
			if($this->wire('input')->get('id') == 'bookmarks') {
				// ok
			} else {
				return $this->listBookmarks();
			}
		}
		return $this->editBookmarksForm()->render();
	}

	/**
	 * Check and update the given process page for hidden/visible status depending on useBookmarks setting
	 * 
	 * @param Page $page
	 * 
	 */
	public function checkProcessPage(Page $page) {
		$hidden = $page->isHidden();
		if($this->process->useBookmarks) {
			if($hidden) {
				$page->removeStatus(Page::statusHidden);
				$page->save();
			}
		} else if(!$hidden) {
			$page->addStatus(Page::statusHidden);
			$page->save();
		}
	}

	/**
	 * Populate any configuration inputfields to the given $inputfields wrapper for $process
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function addConfigInputfields(InputfieldWrapper $inputfields) {
		$field = $this->wire('modules')->get('InputfieldCheckbox');
		$field->attr('name', 'useBookmarks');
		$field->label = $this->_('Allow use of bookmarks?');
		$field->description = $this->_('Bookmarks enable you to create shortcuts to pages from this module, configurable by user role. Useful for large applications.');
		$field->icon = 'bookmark-o';
		if($this->process->useBookmarks) $field->attr('checked', 'checked');
		$inputfields->add($field);
	}
	
}
