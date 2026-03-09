<?php namespace ProcessWire;

/**
 * Base class for Page List rendering
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @method array getPageActions(Page $page)
 * @method string getPageLabel(Page $page, array $options = array())
 * @method int getNumChildren(Page $page, $selector = null) For hooks only, do not call directly
 *
 */
abstract class ProcessPageListRender extends Wire {

	/**
	 * @var Page
	 * 
	 */
	protected $page;

	/**
	 * @var PageArray
	 * 
	 */
	protected $children;

	/**
	 * @var int
	 * 
	 */
	protected $start;

	/**
	 * @var int
	 * 
	 */
	protected $limit;

	/**
	 * @var string Default page label field
	 * 
	 */
	protected $pageLabelField = 'title';

	/**
	 * @var array
	 * 
	 */
	protected $actionLabels = array();

	/**
	 * @var array
	 * 
	 */
	protected $actionTips = array();

	/**
	 * @var bool
	 * 
	 */
	protected $superuser = false;

	/**
	 * @var ProcessPageListActions|null
	 * 
	 */
	protected $actions = null;

	/**
	 * @var array
	 * 
	 */
	protected $options = array();

	/**
	 * @var bool Use trash?
	 * 
	 */
	protected $useTrash = false;

	/**
	 * Page IDs to hide in page list (both keys and values are page IDs)
	 * 
	 * @var array
	 * 
	 */
	protected $hidePages = array();

	/**
	 * Do not hide above pages when current state matches value [ 'debug', 'advanced', 'superuser' ]
	 * 
	 * Both keys and values are the same. 
	 * 
	 * @var array
	 * 
	 */
	protected $hidePagesNot = array();

	/**
	 * @var string Quantity type
	 * 
	 */
	protected $qtyType = '';

	/**
	 * @var bool is ProcessPageListRender::numChildren() hooked?
	 * 
	 */
	protected $numChildrenHook = false;

	/**
	 * @var array Field names for page list labels and versions they should translate to
	 * 
	 */
	protected $translateFields = array(
		'created' => 'createdStr', 
		'modified' => 'modifiedStr', 
		'published' => 'publishedStr',
	);

	/**
	 * Construct
	 *
	 * @param Page $page
	 * @param PageArray $children
	 * 
	 */
	public function __construct(Page $page, PageArray $children) {
		$this->page = $page;
		$this->children = $children;
		$this->start = 0;
		$this->limit = 0;
		parent::__construct();
	}

	/**
	 * Wired to ProcessWire instance
	 * 
	 */
	public function wired() {
		$this->superuser = $this->wire()->user->isSuperuser();
		
		$this->actionLabels = array(
			'edit' => $this->_('Edit'), // Edit page action
			'view' => $this->_('View'), // View page action
			'add' => $this->_('New'), // New page action
			'move' => $this->_('Move'), // Move page action
			'empty' => $this->_('Empty'), // Empty trash page action
			'pub' => $this->_('Pub'), // Publish page action
			'unpub' => $this->_('Unpub'), // Unpublish page action
			'hide' => $this->_('Hide'), // Hide page action
			'unhide' => $this->_('Unhide'), // Unhide page action
			'lock' => $this->_('Lock'), // Lock page action
			'unlock' => $this->_('Unlock'), // Unlock page action
			'trash' => $this->_('Trash'), // Trash page action
			'restore' => $this->_('Restore'), // Restore from trash action
		);
		
		require_once(dirname(__FILE__) . '/ProcessPageListActions.php');
		
		$this->actions = $this->wire(new ProcessPageListActions());
		$this->actions->setActionLabels($this->actionLabels);
		$this->numChildrenHook = $this->wire()->hooks->isMethodHooked($this, 'getNumChildren');
		
		parent::wired();
	}

	/**
	 * Set option
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this
	 * 
	 */
	public function setOption($key, $value) {
		$this->options[$key] = $value;
		return $this;
	}

	/**
	 * Get option
	 * 
	 * @param string $key
	 * @return mixed|null
	 * 
	 */
	public function getOption($key) {
		return isset($this->options[$key]) ? $this->options[$key] : null;
	}

	/**
	 * Set pagination start
	 * 
	 * @param int $n
	 * 
	 */
	public function setStart($n) {
		$this->start = (int) $n;
	}

	/**
	 * Set pagination limit
	 *
	 * @param int $n
	 *
	 */
	public function setLimit($n) {
		$this->limit = (int) $n;
	}

	/**
	 * Set action label by name
	 * 
	 * @param string $key
	 * @param string $value
	 * 
	 */
	public function setLabel($key, $value) {
		$this->actionLabels[$key] = $value;
	}

	/**
	 * Set whether to use trash
	 * 
	 * @param bool $useTrash
	 * 
	 */
	public function setUseTrash($useTrash) {
		$this->useTrash = (bool) $useTrash;
		$this->actions->setUseTrash($this->getUseTrash());
	}

	/**
	 * Set the default page label field/format
	 * 
	 * @param string $pageLabelField
	 * 
	 */
	public function setPageLabelField($pageLabelField) {
		$this->pageLabelField = $pageLabelField;
	}

	/**
	 * Set when pages should be hidden in page list
	 * 
	 * @param array $hidePages IDs of pages that should be hidden
	 * @param array $hidePagesNot Do not hide pages when state matches one or more of 'debug', 'advanced', 'superuser'
	 * 
	 */
	public function setHidePages($hidePages, $hidePagesNot) {
		if(is_array($hidePages)) {
			$this->hidePages = array();
			foreach($hidePages as $id) $this->hidePages[(int) $id] = (int) $id;
		}
		if(is_array($hidePagesNot)) {
			$this->hidePagesNot = array();
			foreach($hidePagesNot as $state) $this->hidePagesNot[$state] = $state;
		}
	}

	/**
	 * Set the quantity type
	 * 
	 * @param string $qtyType
	 * 
	 */
	public function setQtyType($qtyType) {
		$this->qtyType = $qtyType;
	}

	/**
	 * Get the ProcessPageListActions instance
	 * 
	 * @return null|ProcessPageListActions
	 * 
	 */
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
		$actions = $this->actions->getActions($page);
		/*
		 * @todo force 'extras' option to be last
		if(isset($actions['extras'])) {
			$keys = array_keys($actions);
			$lastKey = array_pop($keys);
			if($lastKey !== 'extras') {
				$extras = $actions['extras'];
				unset($actions['extras']);
				$actions['extras'] = $extras; // move to last
			}
		}
		*/
		return $actions;
	}
	
	/**
	 * Return the Page's label text, whether that originates from the Page's name, headline, title, etc.
	 *
	 * @param Page $page
	 * @param array $options
	 *  - `noTags` (bool): If true, HTML will be excluded [other than for icon] in returned text value (default=false)
	 *  - `noIcon` (bool): If true, icon markup will be excluded from returned value (default=false)
	 * @return string
	 *
	 */
	public function ___getPageLabel(Page $page, array $options = array()) {

		$sanitizer = $this->wire()->sanitizer;
		$formatLabel = true;
		$label = $page->getPageListLabel();
		
		if(!empty($label)) {
			// label from custom page class overrides others
			$formatLabel = false;
		} else if(strpos($this->pageLabelField, '!') === 0) {
			// exclamation forces this one to be used, rather than template-specific one
			$label = trim(ltrim($this->pageLabelField, '!'));
		} else {
			// if the page's template specifies a pageLabelField, use that, if pageLabelField doesn't start with "!" as override
			$label = trim($page->template->pageLabelField);
			// otherwise use the one specified with this instance
			if(!strlen($label)) $label = $this->pageLabelField;
		}
		
		if(!strlen($label)) $label = 'name';
		
		$icon = $this->getPageLabelIconMarkup($page, $label); // must be called
		if(!empty($options['noIcon'])) $icon = '';
		
		while(strpos($label, '  ') !== false) $label = str_replace('  ', ' ', $label);
	
		if($formatLabel) {
			$bracket1 = strpos($label, '{');

			if($bracket1 !== false && $bracket1 < strpos($label, '}')) {
				// predefined format string
				// adjust string so that it'll work on a single line, without the markup in it
				$value = $page->getText($label, true, true); // oneLine=true, entities=true
			} else {
				// space delimited list of fields
				$value = $this->getPageLabelDelimited($page, $label, $options);
			}

			if(!strlen($value)) {
				$value = $sanitizer->entities($page->getUnformatted("title|name"));
			}
		} else {
			$value = $label;
		}
		
		if(!empty($options['noTags']) && strpos($value, '<') !== false) {
			$value = strip_tags(str_replace('<', ' <', $value));
		}

		return $icon . trim($value);
	}

	/**
	 * Get page label icon and modify $label to remove existing icon references
	 * 
	 * @param Page $page
	 * @param string $label 
	 * @return string
	 * @since 3.0.163
	 * 
	 */
	protected function getPageLabelIconMarkup(Page $page, &$label) {
		
		$icon = $page->getIcon();

		// remove any existing icon references in label
		if(strpos($label, 'fa-') !== false || strpos($label, 'icon-') !== false) {
			if(preg_match_all('/\b(?:fa|icon)-([-a-z0-9]+)(?:\s*|\b)/', $label, $matches)) {
				foreach($matches[0] as $key => $iconFull) {
					// allow first icon reference to be used if there isn't already one
					if(!$icon) $icon = $matches[1][$key];
					$label = str_replace($iconFull, '', $label);
				}
			}
		}
		
		if($icon) {
			if(!ctype_alnum($icon) && !ctype_alnum(str_replace('-', '', $icon))) {
				$icon = $this->wire()->sanitizer->name($icon);
			}
			$icon = "<i class='icon fa fa-fw fa-$icon'></i>";
		}
		
		return $icon;
	}

	/**
	 * Get page label when label format is space delimited 
	 * 
	 * @param Page $page
	 * @param string $label
	 * @param array $options
	 * @return string
	 * @since 3.0.163
	 * 
	 */
	protected function getPageLabelDelimited(Page $page, $label, array $options) {
		$value = '';
		
		// convert to array
		if(strpos($label, ' ')) {
			$fields = explode(' ', $label);
		} else {
			$fields = array($label);
		}

		foreach($fields as $field) {

			$field = trim($field);

			if(!strlen($field)) {
				continue;

			} else if(strpos($field, ".")) {
				list($field, $subfield) = explode(".", $field);
				if(isset($this->translateFields[$subfield])) $subfield = $this->translateFields[$subfield];

			} else if(strpos($field, 'icon-') === 0 || strpos($field, 'fa-') === 0) {
				// skip over icons, which we now pull directly from page
				continue;

			} else {
				$subfield = '';
			}

			if(isset($this->translateFields[$field])) $field = $this->translateFields[$field];

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
			}

			if(!strlen("$v")) continue;

			if(empty($options['noTags'])) {
				$value .= "<span class='label_$field'>";
			} else if(strlen($value)) {
				$value .= ", ";
				$v = strip_tags("$v");
			}

			$value .= htmlspecialchars("$v", ENT_QUOTES, "UTF-8", false);

			if(empty($options['noTags'])) $value .= "</span>";
		}
		
		return $value;
	}

	/**
	 * Render child item in page list
	 * 
	 * @param Page $page
	 * @return array
	 * 
	 */
	abstract public function renderChild(Page $page);

	/**
	 * Render page list
	 * 
	 * @return string|array
	 * 
	 */
	abstract public function render();

	/**
	 * Get the name of this renderer (i.e. 'JSON')
	 * 
	 * @return string
	 * 
	 */
	public function getRenderName() {
		return str_replace('ProcessPageListRender', '', $this->className());
	}

	/**
	 * Get URL to view more
	 * 
	 * @return string
	 * 
	 */
	public function getMoreURL() {
		if($this->limit && ($this->numChildren($this->page, 1) > ($this->start + $this->limit))) {
			$start = $this->start + $this->limit;
			$config = $this->wire()->config;
			$render = $this->getRenderName();
			return $config->urls->admin . "page/list/?&id={$this->page->id}&start=$start&render=$render";
		}
		return '';
	}

	/**
	 * Get children pages
	 * 
	 * @return PageArray
	 * 
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * Get whether or not to use trash
	 * 
	 * @return bool
	 * 
	 */
	public function getUseTrash() {
		return $this->useTrash; 
	}

	/**
	 * Hook this method if you want to manipulate the numChildren count for pages
	 * 
	 * ~~~~~
	 * $wire->addHookAfter('ProcessPageListRender::getNumChildren', function($event) {
	 *   $page = $event->arguments(0);
	 *   $selector = $event->arguments(1);
	 *   $event->return = $page->numChildren($selector); // your implementation here
	 * }); 
	 * ~~~~~
	 * 
	 * See Page::numChildren() for details on arguments
	 * 
	 * #pw-hooker
	 * 
	 * @param Page $page
	 * @param string|int|bool|null $selector
	 * @return int
	 * 
	 */
	protected function ___getNumChildren(Page $page, $selector = null) {
		return $page->numChildren($selector);
	}

	/**
	 * Return number of children for page
	 * @param Page $page
	 * @param string|int|bool|null $selector
	 * @return int
	 * 
	 */
	public function numChildren(Page $page, $selector = null) {
		return $this->numChildrenHook ? $this->getNumChildren($page, $selector) : $page->numChildren($selector);
	}

}
