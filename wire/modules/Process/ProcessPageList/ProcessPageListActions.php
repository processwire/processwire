<?php namespace ProcessWire;

/**
 * Actions manager for ProcessPageList
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 * @method array getExtraActions(Page $page)
 * @method array getActions(Page $page)
 * @method array processAction(Page $page, $action)
 * 
 */
class ProcessPageListActions extends Wire {
	
	protected $superuser = false;
	protected $useTrash = false;
	
	protected $actionLabels = array(
		'edit' => 'Edit',
		'view' => 'View',
		'add' => 'New',
		'move' => 'Move',
		'empty' => 'Empty',
		'pub' => 'Publish',
		'unpub' => 'Unpublish',
		'hide' => 'Hide',
		'unhide' => 'Unhide',
		'lock' => 'Lock',
		'unlock' => 'Unlock',
		'trash' => 'Trash',
		'restore' => 'Restore',
		'extras' => "<i class='fa fa-angle-right'></i>", 
	);
	
	public function wired() {
		$this->superuser = $this->wire()->user->isSuperuser();
		$settings = $this->wire()->config->ProcessPageList;
		if(is_array($settings) && isset($settings['extrasLabel'])) {
			$this->actionLabels['extras'] = $settings['extrasLabel'];
		}
		parent::wired();
	}

	/**
	 * Set action labels
	 * 
	 * @param array $actionLabels Assoc array of [ name => label ]
	 * 
	 */
	public function setActionLabels(array $actionLabels) {
		$this->actionLabels = array_merge($this->actionLabels, $actionLabels);
	}

	/**
	 * Set whether or not to use trash
	 * 
	 * @param bool $useTrash
	 * 
	 */
	public function setUseTrash($useTrash) {
		$this->useTrash = (bool) $useTrash;
	}
	
	/**
	 * Get an array of available Page actions, indexed by $label => $url
	 *
	 * @param Page $page
	 * @return array of $label => $url
	 *
	 */
	public function ___getActions(Page $page) {
		
		$config = $this->wire()->config;
		$actions = array();
		$adminUrl = $config->urls->admin;

		if($page->id == $config->trashPageID) {

			if($this->superuser) $actions['trash'] = array(
				'cn' => 'Empty',
				'name' => $this->actionLabels['empty'],
				'url' => "{$adminUrl}page/trash/"
			);

		} else {

			if($page->editable()) $actions['edit'] = array(
				'cn' => 'Edit',
				'name' => $this->actionLabels['edit'],
				'url' => "{$adminUrl}page/edit/?id={$page->id}"
			);

			if($page->viewable()) $actions['view'] = array(
				'cn' => 'View',
				'name' => $this->actionLabels['view'],
				'url' => $page->httpUrl
			);

			if($page->addable()) $actions['new'] = array(
				'cn' => 'New',
				'name' => $this->actionLabels['add'],
				'url' => "{$adminUrl}page/add/?parent_id={$page->id}"
			);

			$sortable = $page->parent->sortfield() == 'sort' 
				&& $page->parent->id 
				&& $page->parent->numChildren > 1 && $page->sortable();

			if($page->id > 1 && ($sortable || $page->moveable())) $actions['move'] = array(
				'cn' => 'Move',
				'name' => $this->actionLabels['move'],
				'url' => '#',
			);

			$extras = array();
			if(isset($actions['edit'])) $extras = $this->getExtraActions($page);
			if(count($extras)) {
				$actions['extras'] = array(
					'cn' => 'Extras',
					'name' => $this->actionLabels['extras'], 
					'url' => '#',
					'extras' => $extras,
				);
			}
		}

		return $actions;
	}

	/**
	 * Get an array of available extra Page actions 
	 * 
	 * $returnValue = [ 
	 *   'actionName' => [ 
	 *      'cn' => 'ClassName', 
	 *      'name => 'action label', 
	 *      'url' => 'URL', 
	 *      'ajax' => true 
	 *    ],
	 *   'actionName' => [
	 *      …
	 *   ],
	 * ];
	 *
	 * @param Page $page
	 * @return array of $label => $url
	 *
	 */
	public function ___getExtraActions(Page $page) {

		$extras = array();
		$noSettings = $page->template->noSettings;
		$statusEditable = $page->editable('status', false);
		
		if($page->id == 1 || $page->template == 'admin') return $extras;
		if(!$this->superuser && ($noSettings || !$statusEditable)) return $extras;
		
		$adminUrl = $this->wire()->config->urls->admin . 'page/';
		$locked = $page->isLocked();
		$trash = $page->isTrash();
		$user = $this->wire()->user;

		if(!$locked && !$trash && !$noSettings && $statusEditable) {
			if($page->publishable()) {
				if($page->isUnpublished()) {
					if(!$page->hasStatus(Page::statusFlagged)) {
						$extras['pub'] = array(
							'cn' => 'Publish',
							'name' => $this->actionLabels['pub'],
							'url' => "$adminUrl?action=pub&id=$page->id",
							'ajax' => true,
						);
					}
				} else if(!$page->template->noUnpublish) {
					$extras['unpub'] = array(
						'cn'   => 'Unpublish',
						'name' => $this->actionLabels['unpub'],
						'url'  => "$adminUrl?action=unpub&id=$page->id",
						'ajax' => true,
					);
				}
			}

			if($user->hasPermission('page-hide', $page)) {
				if($page->isHidden()) {
					$extras['unhide'] = array(
						'cn'   => 'Unhide',
						'name' => $this->actionLabels['unhide'],
						'url'  => "$adminUrl?action=unhide&id=$page->id",
						'ajax' => true,
					);
				} else {
					$extras['hide'] = array(
						'cn'   => 'Hide',
						'name' => $this->actionLabels['hide'],
						'url'  => "$adminUrl?action=hide&id=$page->id",
						'ajax' => true,
					);
				}
			}
		}

		if($user->hasPermission('page-lock', $page) && !$trash && $statusEditable) {
			if($locked) {
				$extras['unlock'] = array(
					'cn'    => 'Unlock',
					'name'  => $this->actionLabels['unlock'],
					'url'   => "$adminUrl?action=unlock&id=$page->id",
					'ajax' => true, 
				);
			} else {
				$extras['lock'] = array(
					'cn'    => 'Lock',
					'name'  => $this->actionLabels['lock'],
					'url'   => "$adminUrl?action=lock&id=$page->id",
					'ajax' => true, 
				);
			}
		}

		$trashable = $this->useTrash && $page->trashable();
		$trashIcon = wireIconMarkup('trash-o') . '&nbsp;';
		if($trashable && !$user->isSuperuser()) {
			// do not allow non-superuser ability to trash branches of pages, only individual pages
			if($page->numChildren(1) > 0) $trashable = false;
		}
		if($trashable) {
			$extras['trash'] = array(
				'cn' => 'Trash',
				'name' => $trashIcon . $this->actionLabels['trash'],
				'url' => "$adminUrl?action=trash&id=$page->id",
				'ajax' => true
			);
		} else if($trash && $page->restorable()) {
			$extras['restore'] = array(
				'cn' => 'Restore',	
				'name' => $trashIcon . $this->actionLabels['restore'],
				'url' => "$adminUrl?action=restore&id=$page->id", 
				'ajax' => true
			);
		}

		return $extras;
	}

	/**
	 * Process action
	 * 
	 * @param Page $page
	 * @param string $action
	 * @return array
	 * @throws WireException
	 * 
	 */
	public function ___processAction(Page $page, $action) {

		if($this->wire()->config->demo) {
			$result = array(
				'action' => $action,
				'success' => false, 
				'message' => $this->_('Actions disabled in demo mode'),
				'updateItem' => 0, // id of page to update in output
				'remove' => false,
				'refreshChildren' => false,
				// also available: 'appendItem' => $page->id, which adds a new item below the existing
			);
			return $result;
		}

		$actions = $this->getExtraActions($page);
		$message = '';
		$remove = false;
		$refreshChildren = 0;

		if(isset($actions[$action]) && $page->editable()) {
			$success = true;
			$needSave = true; 

			switch($action) {
				case 'pub':
					$page->removeStatus(Page::statusUnpublished);
					$message = $this->_('Published');
					break;
				case 'unpub':
					$page->addStatus(Page::statusUnpublished);
					$message = $this->_('Unpublished');
					break;
				case 'hide':
					$page->addStatus(Page::statusHidden);
					$message = $this->_('Made hidden');
					break;
				case 'unhide':
					$page->removeStatus(Page::statusHidden);
					$message = $this->_('Unhidden');
					break;
				case 'lock':
					$page->addStatus(Page::statusLocked);
					$message = $this->_('Locked');
					break;
				case 'unlock':
					$page->removeStatus(Page::statusLocked);
					$message = $this->_('Unlocked');
					break;
				case 'trash':
					try {
						$this->wire('pages')->trash($page);
						$message = $this->_('Trashed');
					} catch(\Exception $e) {
						$success = false;
						$message = $e->getMessage();
					}
					$needSave = false;
					$remove = true; 
					$refreshChildren = $this->wire('config')->trashPageID;
					break;
				case 'restore':
					try {
						$this->wire('pages')->restore($page);
						$message = sprintf($this->_('Restored to: %s (reload to see)'), $page->path);
						$refreshChildren = $page->parent->id;
					} catch(\Exception $e) {
						$success = false;
						$message = $e->getMessage();
					}
					$needSave = false;
					$remove = true; 
					break;
				default:
					$success = false;
					$action = 'unknown';
			}
			if($success) try {
				if($needSave) $success = $page->save();
				if(!$success) $message = sprintf($this->_('Error executing: %s'), $message);
			} catch(\Exception $e) {
				$success = false;
				$message = $e->getMessage();
			}
		} else {
			$success = false;
		}

		$result = array(
			'action' => $action,
			'success' => $success,
			'message' => $message,
			'updateItem' => $page->id, // id of page to update in output
			'remove' => $remove, 
			'refreshChildren' => $refreshChildren,
			// also available: 'appendItem' => $page->id, which adds a new item below the existing
		);

		return $result;
	}
}
