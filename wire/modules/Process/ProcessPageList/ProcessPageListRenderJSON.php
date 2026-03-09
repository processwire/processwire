<?php namespace ProcessWire;

require_once(dirname(__FILE__) . '/ProcessPageListRender.php');

/**
 * JSON implementation of the Page List rendering
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 */
class ProcessPageListRenderJSON extends ProcessPageListRender {

	/**
	 * System page IDs used in this class
	 * 
	 * @var array
	 * 
	 */
	protected $systemIDs = array();

	/**
	 * @var Role|null 
	 * 
	 */
	protected $guestRole = null;

	/**
	 * Wired to ProcessWire
	 * 
	 */
	public function wired() {
		$config = $this->wire()->config;
		$systemIDs = array(
			$config->http404PageID,
			$config->adminRootPageID,
			$config->trashPageID,
			$config->loginPageID,
		);
		foreach($systemIDs as $id) {
			$this->systemIDs[$id] = $id;
		}
		parent::wired();
	}

	/**
	 * Render page/child
	 * 
	 * @param Page $page
	 * @return array
	 * 
	 */
	public function renderChild(Page $page) {
		
		$config = $this->wire()->config;

		$outputFormatting = $page->outputFormatting;
		$page->setOutputFormatting(true);
		
		$type = '';
		$note = '';
		$label = '';
		$icons = array();
		$class = array();
		$id = $page->id;

		if(isset($this->systemIDs[$id])) {
			$type = 'System';
			if($id == $config->http404PageID) {
				$label = $this->_('404 Page Not Found'); // Label for '404 Page Not Found' page in PageList // Overrides page title if used
			} else if($id == $config->adminRootPageID) {
				$label = $this->_('Admin'); // Label for 'Admin' page in PageList // Overrides page title if used
			} else if($id == $config->trashPageID && isset($this->actionLabels['trash'])) {
				$label = $this->actionLabels['trash']; // Label for 'Trash' page in PageList // Overrides page title if used
			}
			// if label is not overridden by a language pack, make $label blank to use the page title instead
			if(in_array($label, array('Trash', 'Admin', '404 Page Not Found'))) $label = '';
		}
		
		if(!$page->template->filenameExists()) {
			$class[] = 'PageListNoFile';
		}
		
		$accessParent = $page->getAccessParent();
		
		if($accessParent->id) {
			if(!$this->guestRole) $this->guestRole = $this->wire()->roles->getGuestRole();
			$accessTemplate = $accessParent->template;
			$accessGuest = $accessTemplate ? $accessTemplate->hasRole($this->guestRole) : false;
			
			if(!$accessGuest) $class[] = 'PageListNotPublic';

			if($accessParent === $page && $page->parent->id) {
				$parentAccessTemplate = $page->parent->getAccessTemplate();
				if(!$parentAccessTemplate) {
					// ok
				} else if($accessGuest) {
					if(!$parentAccessTemplate->hasRole('guest') && !$page->isTrash()) {
						$class[] = 'PageListAccessOn';
						$icons[] = 'key flip-horizontal';
					}
				} else {
					if($parentAccessTemplate->hasRole('guest')) {
						$class[] = 'PageListAccessOff';
						$icons[] = 'key';
					}
				}
			} 
		}

		if($id == $config->trashPageID) {
			if($this->superuser) {
				$note = "&lt; " . $this->_("Trash open: drag pages below here to trash them"); // Message that appears next to the Trash page when open
			}
			$icons = array('trash-o'); // override any other icons
			$numChildren = $this->numChildren($page, false);
			if($numChildren > 0 && !$this->superuser) {
				// manually count quantity that are listable in the trash
				$numChildren = 0;
				foreach($page->children("include=all") as $child) {
					if($child->listable()) $numChildren++;
				}
			}
			if(strpos($this->qtyType, 'total') !== false) {
				$numTotal = $this->wire()->pages->trasher()->getTrashTotal();
			} else {
				$numTotal = $numChildren;
			}
			
		} else {
			if($page->hasStatus(Page::statusTemp)) $icons[] = 'bolt';
			if($page->hasStatus(Page::statusLocked)) $icons[] = 'lock';
			if($page->hasStatus(Page::statusDraft)) $icons[] = 'paperclip';
			if($page->hasStatus(Page::statusFlagged)) $icons[] = 'exclamation-triangle';
			if($page->hasStatus(Page::statusTrash) && !$page->rootParent()->isTrash()) {
				$icons[] = 'trash';
				$icons[] = 'exclamation-triangle';
			}
			
			$numChildren = $this->numChildren($page, 1);
			$numTotal = strpos($this->qtyType, 'total') !== false ? $page->numDescendants : $numChildren;
		}
		
		if($label === '') $label = $this->getPageLabel($page);
		
		foreach($icons as $icon) {
			$label .= wireIconMarkup("$icon fw PageListStatusIcon"); 
		}

		$a = array(
			'id' => $id,
			'label' => $label,
			'status' => $page->status,
			'numChildren' => $numChildren,
			'numTotal' => $numTotal, 
			'path' => $page->template->slashUrls || $id == 1 ? $page->path() : rtrim($page->path(), '/'),
			'template' => $page->template->name,
			'actions' => array_values($this->getPageActions($page)),
			//'rm' => $this->superuser && $page->trashable(),
		);

		if(count($class)) $a['addClass'] = implode(' ', $class);
		if($type) $a['type'] = $type;
		if($note) $a['note'] = $note;

		$page->setOutputFormatting($outputFormatting);

		return $a;
	}

	/**
	 * Render page list JSON
	 * 
	 * @return string|array
	 * 
	 */
	public function render() {

		$children = array();
		$extraPages = array(); // pages forced to bottom of list
		$config = $this->wire()->config;
		$idTrash = $config->trashPageID;
		$id404 = $config->http404PageID;
		$states = array();
		$showHidden = true;
		
		if(!empty($this->hidePages)) {
			$showHidden = false;
			foreach($this->hidePagesNot as $state) {
				if($state === 'debug' && $config->debug) $states[$state] = $state;
				if($state === 'advanced' && $config->advanced) $states[$state] = $state;
				if($state === 'superuser' && $this->superuser) $states[$state] = $state;
			}
			if(count($states) && $states == $this->hidePagesNot) $showHidden = true;
		}

		foreach($this->children as $page) {
			
			if(!$this->superuser && !$page->listable()) continue;
			
			$id = $page->id;
			
			if(isset($this->hidePages[$id]) && $id !== $idTrash && $id !== 1) {
				// page hidden in page tree
				if(!$showHidden) continue;
			}

			if($id == $id404 && !$this->superuser) {
				// allow showing 404 page, only if it's editable
				if($page->editable()) $extraPages[$id] = $page;
				continue;
			} else if(isset($this->systemIDs[$id])) {
				// system page
				if($this->superuser) $extraPages[$id] = $page;
				continue;
			}

			$children[] = $this->renderChild($page);
		}
	
		// add in the trash page if not present and allowed
		if($this->page->id === 1 && !$this->superuser && !isset($extraPages[$idTrash]) && $this->getUseTrash()) {
			$pageTrash = $this->wire()->pages->get($idTrash);
			if($pageTrash->id && $pageTrash->listable()) {
				$extraPages[$pageTrash->id] = $pageTrash;
			}
		}

		foreach($extraPages as $page) {
			$children[] = $this->renderChild($page);
		}

		$json = array(
			'page' => $this->renderChild($this->page),
			'children' => $children,
			'start' => $this->start,
			'limit' => $this->limit,
		);

		if($this->getOption('getArray')) return $json;
		
		header("Content-Type: application/json;");
		
		return json_encode($json);
	}

}
