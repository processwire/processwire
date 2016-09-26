<?php namespace ProcessWire;

require_once(dirname(__FILE__) . '/ProcessPageListRender.php');

/**
 * JSON implementation of the Page List rendering
 *
 */
class ProcessPageListRenderJSON extends ProcessPageListRender {

	protected $systemIDs = array();

	public function __construct(Page $page, PageArray $children) {

		parent::__construct($page, $children);

		$this->systemIDs = array(
			$this->config->http404PageID,
			$this->config->adminRootPageID,
			$this->config->trashPageID,
			$this->config->loginPageID,
		);
	}

	public function renderChild(Page $page) {

		$outputFormatting = $page->outputFormatting;
		$page->setOutputFormatting(true);
		$class = '';
		$type = '';
		$note = '';
		$label = '';
		$icons = array();

		if(in_array($page->id, $this->systemIDs)) {
			$type = 'System';
			if($page->id == $this->config->http404PageID) $label = $this->_('404 Page Not Found'); // Label for '404 Page Not Found' page in PageList // Overrides page title if used
			else if($page->id == $this->config->adminRootPageID) $label = $this->_('Admin'); // Label for 'Admin' page in PageList // Overrides page title if used
			else if($page->id == $this->config->trashPageID && isset($this->actionLabels['trash'])) $label = $this->actionLabels['trash']; // Label for 'Trash' page in PageList // Overrides page title if used
			// if label is not overridden by a language pack, make $label blank to use the page title instead
			if(in_array($label, array('Trash', 'Admin', '404 Page Not Found'))) $label = '';
		}

		if($page->getAccessParent() === $page && $page->parent->id) {
			$accessTemplate = $page->getAccessTemplate();
			if($accessTemplate && $accessTemplate->hasRole('guest')) {
				$accessTemplate = $page->parent->getAccessTemplate();
				if($accessTemplate && !$accessTemplate->hasRole('guest') && !$page->isTrash()) {
					$class .= ' PageListAccessOn';
					$icons[] = 'key fa-flip-horizontal';
				}
			} else {
				$accessTemplate = $page->parent->getAccessTemplate();
				if($accessTemplate && $accessTemplate->hasRole('guest')) {
					$class .= ' PageListAccessOff';
					$icons[] = 'key';
				}
			}
		}

		if($page->id == $this->config->trashPageID) {
			$note = "&lt; " . $this->_("Trash open: drag pages below here to trash them"); // Message that appears next to the Trash page when open
			$icons = array('trash-o'); // override any other icons
		} else {
			if($page->hasStatus(Page::statusTemp)) $icons[] = 'bolt';
			if($page->hasStatus(Page::statusLocked)) $icons[] = 'lock';
			if($page->hasStatus(Page::statusDraft)) $icons[] = 'paperclip';
		}

		if(!$label) $label = $this->getPageLabel($page);
		
		if(count($icons)) foreach($icons as $n => $icon) {
			$label .= "<i class='PageListStatusIcon fa fa-fw fa-$icon'></i>";
		}

		$a = array(
			'id' => $page->id,
			'label' => $label,
			'status' => $page->status,
			'numChildren' => $page->numChildren(1),
			'path' => $page->template->slashUrls || $page->id == 1 ? $page->path() : rtrim($page->path(), '/'),
			'template' => $page->template->name,
			//'rm' => $this->superuser && $page->trashable(),
			'actions' => array_values($this->getPageActions($page)),
		);

		if($class) $a['addClass'] = trim($class);
		if($type) $a['type'] = $type;
		if($note) $a['note'] = $note;


		$page->setOutputFormatting($outputFormatting);

		return $a;
	}

	public function render() {

		$children = array();

		$extraPages = array(); // pages forced to bottom of list
		$id404 = $this->wire('config')->http404PageID;

		foreach($this->children as $page) {
			if(!$this->superuser && !$page->listable()) continue;

			if($page->id == $id404 && !$this->superuser) {
				// allow showing 404 page, only if it's editable
				if(!$page->editable()) continue;
			} else if(in_array($page->id, $this->systemIDs)) {
				$extraPages[] = $page;
				continue;
			}

			$child = $this->renderChild($page);
			$children[] = $child;
		}

		if($this->superuser) foreach($extraPages as $page) {
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
