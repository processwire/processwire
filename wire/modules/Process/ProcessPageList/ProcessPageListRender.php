<?php namespace ProcessWire;

/**
 * Base class for Page List rendering
 * 
 * @method array getPageActions(Page $page)
 * @method string getPageLabel(Page $page)
 *
 */
abstract class ProcessPageListRender extends Wire {

	protected $page;
	protected $children;
	protected $start;
	protected $limit;
	protected $pageLabelField = 'title';
	protected $actionLabels = array();
	protected $actionTips = array();
	protected $superuser = false;
	protected $actions = null;
	protected $options = array();

	public function __construct(Page $page, PageArray $children) {
		$this->page = $page;
		$this->children = $children;
		$this->start = 0;
		$this->limit = 0;
		$this->superuser = $this->wire('user')->isSuperuser();
		$this->actionLabels = array(
			'edit' => $this->_('Edit'), 	// Edit page action
			'view' => $this->_('View'), 	// View page action
			'add' => $this->_('New'), 	// New page action
			'move' => $this->_('Move'),	// Move page action
			'empty' => $this->_('Empty'),	// Empty trash page action
			'pub' => $this->_('Pub'),	// Publish page action
			'unpub' => $this->_('Unpub'),	// Unpublish page action
			'hide' => $this->_('Hide'), // Hide page action
			'unhide' => $this->_('Unhide'), // Unhide page action
			'lock' => $this->_('Lock'), // Lock page action
			'unlock' => $this->_('Unlock'),  // Unlock page action
			'trash' => $this->_('Trash'), // Trash page action
			'restore' => $this->_('Restore'), // Restore from trash action
		);
		require_once(dirname(__FILE__) . '/ProcessPageListActions.php');
		$this->actions = $this->wire(new ProcessPageListActions($this));
		$this->actions->setActionLabels($this->actionLabels);
	}
	
	public function setOption($key, $value) {
		$this->options[$key] = $value;
		return $this;
	}
	
	public function getOption($key) {
		return isset($this->options[$key]) ? $this->options[$key] : null;
	}

	public function setStart($n) {
		$this->start = (int) $n;
	}

	public function setLimit($n) {
		$this->limit = (int) $n;
	}

	public function setLabel($key, $value) {
		$this->actionLabels[$key] = $value;
	}

	public function setPageLabelField($pageLabelField) {
		$this->pageLabelField = $pageLabelField;
	}
	
	public function actions() {
		return $this->actions;
	}

	/**
	 * Get an array of available Page actions, indexed by $label => $url
	 *
	 * @param Page $page
	 * @return array of $label => $url
	 *
	 */
	public function ___getPageActions(Page $page) {
		return $this->actions->getActions($page); 
	}
	
	/**
	 * Return the Page's label text, whether that originates from the Page's name, headline, title, etc.
	 *
	 * @param Page $page
	 * @return string
	 *
	 */
	public function ___getPageLabel(Page $page) {

		$value = '';
		$icon = $page->getIcon();

		if(strpos($this->pageLabelField, '!') === 0) {
			// exclamation forces this one to be used, rather than template-specific one
			$pageLabelField = ltrim($this->pageLabelField, '!');
		} else {
			// if the page's template specifies a pageLabelField, use that, if pageLabelField doesn't start with "!" as override
			$pageLabelField = trim($page->template->pageLabelField);
		}

		// otherwise use the one specified with this instance
		if(!strlen($pageLabelField)) $pageLabelField = $this->pageLabelField;

		$bracket1 = strpos($pageLabelField, '{'); 
		
		if($bracket1 !== false && $bracket1 < strpos($pageLabelField, '}')) {
	
			// predefined format string
			if($icon) $pageLabelField = str_replace(array("fa-$icon", "icon-$icon", "  "), array('', '', ' '), $pageLabelField);
			// adjust string so that it'll work on a single line, without the markup in it
			$value = $page->getText($pageLabelField, true, true);
			// if(strpos($value, '</li>')) $value = preg_replace('!</li>\s*<li[^>]*>!', ', ', $value); 
			// $value = trim($this->wire('sanitizer')->entities($value));

		} else {
			
			// CSV or space-separated field or fields

			// convert to array
			if(strpos($pageLabelField, ' ')) $fields = explode(' ', $pageLabelField);
				else $fields = array($pageLabelField);

			foreach($fields as $field) {

				if(strpos($field, ".")) {
					list($field, $subfield) = explode(".", $field);

				} else if(strpos($field, 'icon-') === 0 || strpos($field, 'fa-') === 0) {
					// skip over icons, which we now pull directly from page
					continue;

				} else {
					$subfield = '';
				}

				$v = $page->get($field);

				if($subfield && is_object($v)) {
					if($v instanceof WireArray && count($v)) $v = $v->first();
					if($v instanceof Page) {
						$v = $v->getFormatted($subfield); // @esrch PR #965
					} else if($v instanceof Template && $subfield == 'label') {
						$v = $v->getLabel();
					} else if($v instanceof Wire) {
						$v = $v->$subfield;
					} else {
						// unknown
						$v = (string) $v;
					}

				} else if(($field == 'created' || $field == 'modified' || $field == 'published') && ctype_digit("$v")) {
					$v = date($this->wire('config')->dateFormat, (int) $v);
				}

				if(!strlen("$v")) continue;

				$value .=
					"<span class='label_$field'>" .
					htmlspecialchars(strip_tags("$v"), ENT_QUOTES, "UTF-8", false) .
					"</span>";
			}
		}

		$icon = $page->getIcon();
		if($icon) {
			$icon = $this->wire('sanitizer')->name($icon);
			$icon = "<i class='icon fa fa-fw fa-$icon'></i>";
		}

		if(!strlen($value)) $value = $page->get("title|name");

		return $icon . trim($value);
	}

	abstract public function renderChild(Page $page);
	abstract public function render();

	public function getRenderName() {
		return str_replace('ProcessPageListRender', '', $this->className());
	}

	public function getMoreURL() {
		if($this->limit && ($this->page->numChildren(1) > ($this->start + $this->limit))) {
			$start = $this->start + $this->limit;
			return $this->config->urls->admin . "page/list/?&id={$this->page->id}&start=$start&render=" . $this->getRenderName();
		}
		return '';
	}

}

