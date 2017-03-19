<?php namespace ProcessWire;

/**
 * AdminTheme Framework
 * 
 * The methods in this class may eventually be merged to AdminTheme.php, 
 * but are isolated to this class during development. 
 *
 * @property bool $isSuperuser
 * @property bool $isEditor
 * @property bool $isLoggedIn
 * @property bool $isModal
 * @property bool|int $useAsLogin
 * @method array getUserNavArray()
 *
 */
abstract class AdminThemeFramework extends AdminTheme {

	/**
	 * Is there currently a logged in user?
	 *
	 * @var bool
	 *
	 */
	protected $isLoggedIn = false;

	/**
	 * Is user logged in with page-edit permission?
	 *
	 * @var bool
	 *
	 */
	protected $isEditor = false;

	/**
	 * Is current user a superuser?
	 *
	 * @var bool
	 *
	 */
	protected $isSuperuser = false;

	/**
	 * Is the current request a modal request? 
	 * 
	 * @var bool|string Either false, true, or "inline"
	 * 
	 */
	protected $isModal = false;

	/**
	 * @var Sanitizer
	 * 
	 */
	protected $sanitizer;

	/**
	 * Construct
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->set('useAsLogin', false);
		$this->wire('modules')->get('JqueryUI')->use('panel');
		$this->sanitizer = $this->wire('sanitizer');
	}

	/**
	 * Override get() method from WireData to support additional properties
	 *
	 * @param string $key
	 * @return bool|int|mixed|null|string
	 *
	 */
	public function get($key) {
		switch($key) {
			case 'isSuperuser': $value = $this->isSuperuser; break;
			case 'isEditor': $value = $this->isEditor; break;
			case 'isLoggedIn': $value = $this->isLoggedIn; break;
			case 'isModal': $value = $this->isModal; break;
			default: $value = parent::get($key);
		}
		return $value;
	}

	/**
	 * Initialize and attach hooks
	 *
	 */
	public function init() {
		
		$user = $this->wire('user');
		if(!$user->isLoggedin() && $this->useAsLogin) $this->setCurrent();
		parent::init();
		
		// if this is not the current admin theme, exit now so no hooks are attached
		if(!$this->isCurrent()) return;

		$this->isLoggedIn = $user->isLoggedin();
		$this->isSuperuser = $this->isLoggedIn && $user->isSuperuser();
		$this->isEditor = $this->isLoggedIn && ($this->isSuperuser || $user->hasPermission('page-edit'));
		
		$modal = $this->wire('input')->get('modal');
		if($modal) $this->isModal = $modal == 'inline' ? 'inline' : true; 	

		// test notices when requested
		if($this->wire('input')->get('test_notices')) $this->testNotices();
	}

	/**
	 * Perform a translation, based on text from shared admin file: /wire/templates-admin/default.php
	 *
	 * @param string $text
	 * @return string
	 *
	 */
	public function _($text) {
		static $translate = null;
		if(is_null($translate)) $translate = $this->wire('languages') !== null;
		if($translate === false) return $text;
		$value = __($text, $this->wire('config')->paths->root . 'wire/templates-admin/default.php');
		if($value === $text) $value = parent::_($text);
		return $value;
	}

	/**
	 * Get the current page headline
	 *
	 * @return string
	 *
	 */
	public function getHeadline() {
		$headline = $this->wire('processHeadline');
		if(!$headline) $headline = $this->wire('page')->get('title|name');
		return $this->sanitizer->entities1($headline);
	}

	/**
	 * Get navigation title for the given page, return blank if page should not be shown
	 *
	 * @param Page $p
	 * @return string
	 *
	 */
	public function getPageTitle(Page $p) {

		if($p->name == 'add' && $p->parent->name == 'page') {
			// ProcessPageAdd: avoid showing this menu item if there are no predefined family settings to use
			$numAddable = $this->wire('session')->getFor('ProcessPageAdd', 'numAddable');
			if($numAddable === null) {
				/** @var ProcessPageAdd $processPageAdd */
				$processPageAdd = $this->wire('modules')->getModule('ProcessPageAdd', array('noInit' => true));
				if($processPageAdd) {
					$addData = $processPageAdd->executeNavJSON(array('getArray' => true));
					$numAddable = $addData['list'];
				}
			}
			if(!$numAddable) return '';
			$title = $this->getAddNewLabel();

		} else {
			$title = $this->_($p->title);
		}

		$title = $this->sanitizer->entities1($title);

		return $title;
	}

	/**
	 * Get icon used by the given page
	 *
	 * @param Page $p
	 * @return mixed|null|string
	 *
	 */
	public function getPageIcon(Page $p) {
		$icon = '';
		if($p->template == 'admin') {
			$info = $this->wire('modules')->getModuleInfo($p->process);
			if(!empty($info['icon'])) $icon = $info['icon'];
		}
		// allow for option of an admin field overriding the module icon
		$pageIcon = $p->get('page_icon');
		if($pageIcon) $icon = $pageIcon;
		if(!$icon) switch($p->id) {
			case 22: $icon = 'gears'; break; // Setup
			case 21: $icon = 'plug'; break; // Modules
			case 28: $icon = 'key'; break; // Access
		}
		if(!$icon && $p->parent->id != $this->wire('config')->adminRootPageID) {
			$icon = 'file-o ui-priority-secondary';
		}
		return $icon;
	}

	/**
	 * Get “Add New” button actions
	 *
	 * - Returns array of arrays, each with 'url', 'label' and 'icon' properties.
	 * - Returns empty array if Add New button should not be displayed.
	 *
	 * @return array
	 *
	 */
	public function getAddNewActions() {

		$page = $this->wire('page');
		$process = $this->wire('process');
		$input = $this->wire('input');

		if(!$this->isEditor) return array();
		if($page->name != 'page' || $this->wire('input')->urlSegment1) return array();
		if($input->urlSegment1 || $input->get('modal')) return array();
		if(strpos($process, 'ProcessPageList') !== 0) return array();

		/** @var ProcessPageAdd $module */
		$module = $this->wire('modules')->getModule('ProcessPageAdd', array('noInit' => true));
		$data = $module->executeNavJSON(array('getArray' => true));
		$actions = array();

		foreach($data['list'] as $item) {
			$item['url'] = $data['url'] . $item['url'];
			$actions[] = $item;
		}

		return $actions;
	}

	/**
	 * Get the translated “Add New” label that’s used in a couple spots
	 *
	 * @return string
	 *
	 */
	public function getAddNewLabel() {
		return $this->_('Add New');
	}

	/**
	 * Get the classes that will be used in the <body class=''> tag
	 *
	 * @return string
	 *
	 */
	public function getBodyClass() {

		$page = $this->wire('page');
		$process = $this->wire('process');

		$classes = array(
			"id-{$page->id}",
			"template-{$page->template->name}",
			"pw-init",
			parent::getBodyClass(),
		);

		if($this->isModal) $classes[] = 'modal';
		if($this->isModal === 'inline') $classes[] = 'modal-inline';
		if($this->wire('input')->urlSegment1) $classes[] =  'hasUrlSegments';
		if($process) $classes[] = $process->className();

		return implode(' ', $classes);
	}

	/**
	 * Get Javascript that must be present in the document <head>
	 *
	 * @return string
	 *
	 */
	public function getHeadJS() {

		/** @var Config $config */
		$config = $this->wire('config');
		
		/** @var Paths $urls */
		$urls = $config->urls;

		/** @var array $jsConfig */
		$jsConfig = $config->js();
		$jsConfig['debug'] = $config->debug;

		$jsConfig['urls'] = array(
			'root' => $urls->root,
			'admin' => $urls->admin,
			'modules' => $urls->modules,
			'core' => $urls->core,
			'files' => $urls->files,
			'templates' => $urls->templates,
			'adminTemplates' => $urls->adminTemplates,
		);

		$out =
			"var ProcessWire = { config: " . wireEncodeJSON($jsConfig, true, $config->debug) . " }; " .
			"var config = ProcessWire.config;\n"; // legacy support

		return $out;
	}


	/**
	 * Allow the given Page to appear in admin theme navigation?
	 *
	 * @param Page $p Page to test
	 * @param PageArray|array $children Children of page, if applicable (optional)
	 * @param string|null $permission Specify required permission (optional)
	 * @return bool
	 *
	 */
	public function allowPageInNav(Page $p, $children = array(), $permission = null) {

		if($this->isSuperuser) return true;
		if($p->viewable()) return true;
		$allow = false;

		foreach($children as $child) {
			if($child->viewable()) {
				$allow = true;
				break;
			}
		}

		if($allow) return true;

		if($permission === null && $p->process) {
			// determine permission
			$moduleInfo = $this->wire('modules')->getModuleInfo($p->process);
			if(!empty($moduleInfo['permission'])) $permission = $moduleInfo['permission'];
		}

		if($permission) {
			$allow = $this->wire('user')->hasPermission($permission);
		}

		return $allow;
	}

	/**
	 * Return nav array of primary navigation
	 *
	 * @return array
	 *
	 */
	public function getPrimaryNavArray() {

		$items = array();
		$config = $this->wire('config');
		$admin = $this->wire('pages')->get($config->adminRootPageID);

		foreach($admin->children("check_access=0") as $p) {
			$item = $this->pageToNavArray($p);
			if($item) $items[] = $item;
		}

		return $items;
	}

	/**
	 * Get navigation array from a Process module
	 *
	 * @param array|Module|string $module Module info array or Module object or string
	 * @param Page $p Page upon which the Process module is contained
	 * @return array
	 *
	 */
	public function moduleToNavArray($module, Page $p) {

		$config = $this->wire('config');
		$modules = $this->wire('modules');
		$textdomain = str_replace($config->paths->root, '/', $modules->getModuleFile($p->process));
		$user = $this->wire('user');
		$navArray = array();

		if(is_array($module)) {
			$moduleInfo = $module;
		} else {
			$moduleInfo = $modules->getModuleInfo($module);
		}

		foreach($moduleInfo['nav'] as $navItem) {

			$permission = empty($navItem['permission']) ? '' : $navItem['permission'];
			if($permission && !$user->hasPermission($permission)) continue;

			$navArray[] = array(
				'id' => 0,
				'parent_id' => $p->id,
				'title' => $this->sanitizer->entities1(__($navItem['label'], $textdomain)), // translate from context of Process module
				'name' => '',
				'url' => $p->url . $navItem['url'],
				'icon' => empty($navItem['icon']) ? '' : $navItem['icon'],
				'children' => array(),
				'navJSON' => empty($navItem['navJSON']) ? '' : $p->url . $navItem['navJSON'],
			);
		}

		return $navArray;
	}

	/**
	 * Get a navigation array the given Page, or null if page not allowed in nav
	 *
	 * @param Page $p
	 * @return array|null
	 *
	 */
	public function pageToNavArray(Page $p) {

		$children = $p->numChildren ? $p->children("check_access=0") : array();

		if(!$this->allowPageInNav($p, $children)) return null;

		$navArray = array(
			'id' => $p->id,
			'parent_id' => $p->parent_id,
			'url' => $p->url,
			'name' => $p->name,
			'title' => $this->getPageTitle($p),
			'icon' => $this->getPageIcon($p),
			'children' => array(),
			'navJSON' => '',
		);

		if(!count($children)) {
			// no children available
			if($p->template == 'admin' && $p->process) {
				// see if process module defines its own navigation
				$moduleInfo = $this->wire('modules')->getModuleInfo($p->process);
				if(!empty($moduleInfo['nav'])) {
					$navArray['children'] = $this->moduleToNavArray($moduleInfo, $p);
				}
			} else {
				// The /page/ and /page/list/ are the same process, so just keep them on /page/ instead. 
				if(strpos($navArray['url'], '/page/list/') !== false) {
					$navArray['url'] = str_replace('/page/list/', '/page/', $navArray['url']);
				}
			}
			return $navArray;
		}

		// if we reach this point, then we have a PageArray of children

		$modules = $this->wire('modules');

		foreach($children as $c) {

			if(!$c->process) continue;
			$moduleInfo = $modules->getModuleInfo($c->process);
			$permission = empty($moduleInfo['permission']) ? '' : $moduleInfo['permission'];
			if(!$this->allowPageInNav($c, array(), $permission)) continue;

			$childItem = array(
				'id' => $c->id,
				'parent_id' => $c->parent_id,
				'title' => $this->getPageTitle($c),
				'name' => $c->name,
				'url' => $c->url,
				'icon' => $this->getPageIcon($c),
				'children' => array(),
				'navJSON' => empty($moduleInfo['useNavJSON']) ? '' : $c->url . 'navJSON/',
			);

			if(!empty($moduleInfo['nav'])) {
				$childItem['children'] = $this->moduleToNavArray($moduleInfo, $c);  // @todo should $p be $c?
			}

			$navArray['children'][] = $childItem;

		} // foreach

		return $navArray;
	}

	/**
	 * Get navigation items for the “user” menu
	 *
	 * This is hookable so that something else could add stuff to it.
	 * See the method body for details on format used.
	 *
	 * @return array
	 *
	 */
	public function ___getUserNavArray() {
		$urls = $this->wire('urls');
		$navArray = array();
		
		$navArray[] = array(
			'url' => $urls->root,
			'title' => $this->_('View site'),
			'target' => '_top',
			'icon' => 'eye',
		);
		
		if($this->wire('user')->hasPermission('profile-edit')) $navArray[] = array(
			'url' => $urls->admin . 'profile/',
			'title' => $this->_('Profile'),
			'icon' => 'user',
			'permission' => 'profile-edit',
		);
		
		$navArray[] = array(
			'url' => $urls->admin . 'login/logout/',
			'title' => $this->_('Logout'),
			'target' => '_top',
			'icon' => 'power-off',
		);
		
		return $navArray;
	}

	/**
	 * Get the browser <title>
	 *
	 * @return string
	 *
	 */
	public function getBrowserTitle() {

		$browserTitle = $this->wire('processBrowserTitle');
		$modal = $this->wire('input')->get('modal');

		if(!$browserTitle) {
			if($modal) return $this->wire('processHeadline');
			$browserTitle = $this->_(strip_tags($this->wire('page')->get('title|name'))) . ' • ProcessWire';
		}

		if(!$modal) {
			$httpHost = $this->wire('config')->httpHost;
			if(strpos($httpHost, 'www.') === 0) $httpHost = substr($httpHost, 4); // remove www
			if(strpos($httpHost, ':')) $httpHost = preg_replace('/:\d+/', '', $httpHost); // remove port
			$browserTitle .= " • $httpHost";
		}

		return $this->sanitizer->entities1($browserTitle);
	}

	/**
	 * Test all notice types
	 *
	 */
	public function testNotices() {
		if(!$this->wire('user')->isLoggedin()) return;
		$this->message('Message test');
		$this->message('Message test debug', Notice::debug);
		$this->message('Message test markup <a href="#">example</a>', Notice::allowMarkup);
		$this->warning('Warning test');
		$this->warning('Warning test debug', Notice::debug);
		$this->warning('Warning test markup <a href="#">example</a>', Notice::allowMarkup);
		$this->error('Error test');
		$this->error('Error test debug', Notice::debug);
		$this->error('Error test markup <a href="#">example</a>', Notice::allowMarkup);
	}
	
	/**
	 * Render runtime notices div#notices
	 *
	 * @param array $options See defaults in method
	 * @param Notices $notices
	 * @return string
	 *
	 */
	public function renderNotices($notices, array $options = array()) {

		if(!count($notices)) return '';

		if($this->isLoggedIn && $this->wire('modules')->isInstalled('SystemNotifications')) {
			$systemNotifications = $this->wire('modules')->get('SystemNotifications');
			if(!$systemNotifications->placement) return '';
		}

		$defaults = array(
			'messageClass' => 'NoticeMessage', // class for messages
			'messageIcon' => 'check-square', // default icon to show with notices
			'warningClass' => 'NoticeWarning', // class for warnings
			'warningIcon' => 'exclamation-circle', // icon for warnings
			'errorClass' => 'NoticeError', // class for errors
			'errorIcon' => 'exclamation-triangle', // icon for errors
			'debugClass' => 'NoticeDebug', // class for debug items (appended)
			'debugIcon' => 'bug', // icon for debug notices
			'closeClass' => 'notice-remove', // class for close notices link <a>
			'closeIcon' => 'times', // icon for close notices link
			'listMarkup' => "<ul class='pw-notices' id='notices'>{out}</ul><!--/notices-->",
			'itemMarkup' => "<li class='{class}'>{remove}{icon}{text}</li>"
		);

		$options = array_merge($defaults, $options);
		$config = $this->wire('config');
		$out = '';

		foreach($notices as $n => $notice) {

			$text = $notice->text;
			if($notice->flags & Notice::allowMarkup) {
				// leave $text alone
			} else {
				// unencode + re-encode entities, just in case module already entity some or all of output
				if(strpos($text, '&') !== false) $text = $this->sanitizer->unentities($text);
				$text = $this->sanitizer->entities($text);
			}

			if($notice instanceof NoticeError) {
				$class = $options['errorClass'];
				$icon = $options['errorIcon'];

			} else if($notice instanceof NoticeWarning) {
				$class = $options['warningClass'];
				$icon = $options['warningIcon'];

			} else {
				$class = $options['messageClass'];
				$icon = $options['messageIcon'];
			}

			if($notice->flags & Notice::debug) {
				$class .= " " . $options['debugClass'];
				$icon = $options['debugIcon'];
			}

			// indicate which class the notice originated from in debug mode
			if($notice->class && $config->debug) $text = "{$notice->class}: $text";

			// show remove link for first item only
			if($n) {
				$remove = '';
			} else {
				$removeIcon = $this->renderIcon($options['closeIcon']);
				$removeLabel = $this->_('Close all');
				$remove = "<a class='$options[closeClass]' href='#' title='$removeLabel'>$removeIcon</a>";
			}

			$replacements = array(
				'{class}' => $class,
				'{remove}' => $remove,
				'{icon}' => $this->renderNavIcon($notice->icon ? $notice->icon : $icon),
				'{text}' => $text,
			);

			$out .= str_replace(array_keys($replacements), array_values($replacements), $options['itemMarkup']);
		}

		$out = str_replace('{out}', $out, $options['listMarkup']);
		$out .= $this->renderExtraMarkup('notices');

		return $out;
	}
	
	/**
	 * Render markup for a font-awesome icon
	 *
	 * @param string $icon Name of icon to render, excluding the “fa-” prefix
	 * @param bool $fw Specify true to make fixed width (default=false).
	 * @return string
	 *
	 */
	public function renderIcon($icon, $fw = false) {
		if($fw) $icon .= ' fa-fw';
		return "<i class='fa fa-$icon'></i>";
	}

	/**
	 * Render markup for a font-awesome icon that precedes a navigation label
	 *
	 * This is the same as renderIcon() except that fixed-width is assumed and a "nav-nav-icon"
	 * class is added to it.
	 *
	 * @param string $icon Name of icon to render, excluding the “fa-” prefix
	 * @return string
	 *
	 */
	public function renderNavIcon($icon) {
		return $this->renderIcon("$icon pw-nav-icon", true);
	}
	
	/**
	 * Render an extra markup region
	 *
	 * @param string $for
	 * @return mixed|string
	 *
	 */
	public function renderExtraMarkup($for) {
		static $extras = array();
		if(empty($extras)) $extras = $this->getExtraMarkup();
		return isset($extras[$for]) ? $extras[$for] : '';
	}

	/**
	 * Module Configuration
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {

		/** @var InputfieldCheckbox $f */
		$f = $this->modules->get('InputfieldCheckbox'); 
		$f->name = 'useAsLogin';
		$f->label = $this->_('Use this admin theme for login screen?');
		$f->description = $this->_('When checked, this admin theme will be used on the user login screen.');
		$f->icon = 'sign-in';
		$f->collapsed = Inputfield::collapsedBlank;
		if($this->get('useAsLogin')) $f->attr('checked', 'checked');
		$inputfields->add($f);

		if($f->attr('checked') && $this->input->requestMethod('GET')) {
			$class = $this->className();
			foreach($this->modules->findByPrefix('AdminTheme') as $name) {
				if($name == $class) continue;
				$cfg = $this->modules->getConfig($name);
				if(!empty($cfg['useAsLogin'])) {
					unset($cfg['useAsLogin']);
					$this->modules->saveConfig($name, $cfg);
					$this->message("Removed 'useAsLogin' setting from $name", Notice::debug);
				}
			}
		}
	}
}

