<?php namespace ProcessWire;

/**
 * AdminThemeDefaultHelpers.php
 * 
 * Rendering helper functions for use with ProcessWire admin theme.
 * 
 * __('FOR TRANSLATORS: please translate the file /wire/templates-admin/default.php rather than this one.'); 
 *
 */ 

class AdminThemeDefaultHelpers extends WireData {
	
	public function __construct() {
		if($this->wire('input')->get('test_notices')) {
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
		$this->wire('modules')->get('JqueryUI')->use('panel');
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
	 * Get the headline for the current admin page
	 *
	 * @return string
	 *
	 */
	public function getHeadline() {
		$headline = $this->wire('processHeadline'); 
		if(!$headline) $headline = $this->wire('page')->get('title|name'); 
		$headline = $this->wire('sanitizer')->entities1($this->_($headline)); 
		return $headline;
	}

	/**
	 * Render a list of breadcrumbs (list items), excluding the containing <ul>
	 *
	 * @param bool $appendCurrent Whether to append the current title/headline to the breadcrumb trail (default=true)
	 * @return string
	 *
	 */
	public function renderBreadcrumbs($appendCurrent = true) {
		
		$out = '';
		$loggedin = $this->wire('user')->isLoggedin();
		$separator = "<i class='fa fa-angle-right'></i>";
	
		if($loggedin && $this->className() == 'AdminThemeDefaultHelpers') {
			
			if($this->wire('config')->debug && $this->wire('user')->isSuperuser()) {
				$label = __('Debug Mode Tools', '/wire/templates-admin/debug.inc');
				$out .=
					"<li><a href='#' title='$label' onclick=\"$('#debug_toggle').click();return false;\">" .
					"<i class='fa fa-bug'></i></a>$separator</li>";
			}

			if($this->wire('process') != 'ProcessPageList') {
				$url = $this->wire('config')->urls->admin . 'page/';
				$tree = $this->_('Tree');
				$out .=
					"<li><a class='pw-panel' href='$url' data-tab-text='$tree' data-tab-icon='sitemap' title='$tree'>" .
					"<i class='fa fa-sitemap'></i></a>$separator</li>";
			}
		}
		
		foreach($this->wire('breadcrumbs') as $breadcrumb) {
			$title = $breadcrumb->get('titleMarkup');
			if(!$title) $title = $this->wire('sanitizer')->entities1($this->_($breadcrumb->title));
			$out .= "<li><a href='{$breadcrumb->url}'>{$title}</a>$separator</li>";
		}
		
		if($appendCurrent) $out .= "<li class='title'>" . $this->getHeadline() . "</li>";
		
		return $out; 
	}

	/**
	 * Render the populated shortcuts head button or blank when not applicable
	 *
	 * @return string
	 *
	 */
	public function renderAdminShortcuts() {

		$page = $this->wire('page');
		if($page->name != 'page' || $this->wire('input')->urlSegment1) return '';
		$user = $this->wire('user');
		if($this->wire('user')->isGuest() || !$user->hasPermission('page-edit')) return '';
		/** @var ProcessPageAdd $module */
		$module = $this->wire('modules')->getModule('ProcessPageAdd', array('noInit' => true));
		$data = $module->executeNavJSON(array('getArray' => true));
		$items = array();
	
		foreach($data['list'] as $item) {
			$items[] = "<li><a href='$data[url]$item[url]'><i class='fa fa-fw fa-$item[icon]'></i>&nbsp;$item[label]</a></li>";
		}
	
		if(!count($items)) return '';
		$out = implode('', $items); 
		$label = $this->getAddNewLabel();
	
		$out =	
			"<div id='head_button'>" . 	
			"<button class='ui-button pw-dropdown-toggle'><i class='fa fa-angle-down'></i> $label</button>" . 
			"<ul class='pw-dropdown-menu pw-dropdown-menu-rounded' data-at='right bottom+1'>$out</ul>" . 
			"</div>";
	
		return $out; 
	}
	
	/**
	 * Render runtime notices div#notices
	 *
	 * @param array $options See defaults in method
	 * @param Notices $notices
	 * @return string
	 *
	 */
	public function renderAdminNotices($notices, array $options = array()) {

		if($this->wire('user')->isLoggedin() && $this->wire('modules')->isInstalled('SystemNotifications')) {
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
			'closeIcon' => 'times-circle', // icon for close notices link
	
			'listMarkup' => "\n\t<ul id='notices' class='ui-widget'>{out}</ul><!--/notices-->", 
			'itemMarkup' => "\n\t\t<li class='{class}'><div class='container'><p>{remove}<i class='fa fa-fw fa-{icon}'></i> {text}</p></div></li>",
			);

		if(!count($notices)) return '';
		$options = array_merge($defaults, $options); 
		$config = $this->wire('config'); 
		$out = '';
	
		foreach($notices as $n => $notice) {
	
			$text = $notice->text; 
			if($notice->flags & Notice::allowMarkup) {
				// leave $text alone
			} else {
				// unencode entities, just in case module already entity some or all of output
				if(strpos($text, '&') !== false) $text = html_entity_decode($text, ENT_QUOTES, "UTF-8"); 
				// entity encode it
				$text = $this->wire('sanitizer')->entities($text); 
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
			$remove = $n ? '' : "<a class='$options[closeClass]' href='#'><i class='fa fa-$options[closeIcon]'></i></a>";
			
			$replacements = array(
				'{class}' => $class, 
				'{remove}' => $remove, 
				'{icon}' => $notice->icon ? $notice->icon : $icon,
				'{text}' => $text, 
				);
			
			$out .= str_replace(array_keys($replacements), array_values($replacements), $options['itemMarkup']); 
		}
		
		$out = str_replace('{out}', $out, $options['listMarkup']); 
		return $out; 
	}

	/**
	 * Get markup for icon used by the given page
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
		if(!$icon && $p->parent->id != $this->wire('config')->adminRootPageID) $icon = 'file-o ui-priority-secondary';
		if($icon) $icon = "<i class='fa fa-fw fa-$icon'></i>&nbsp;";
		return $icon;
	}
	
	/**
	 * Render a single top navigation item for the given page
	 *
	 * This function designed primarily to be called by the renderTopNavItems() function. 
	 *
	 * @param Page $p
	 * @param int $level Recursion level (default=0)
	 * @return string
	 *
	 */
	public function renderTopNavItem(Page $p, $level = 0) {
	
		$isSuperuser = $this->wire('user')->isSuperuser();
		$showItem = $isSuperuser;
		$children = $p->numChildren && !$level ? $p->children("check_access=0") : array();
		$numChildren = count($children); 
		$out = '';
	
		if(!$showItem) { 
			$checkPages = $numChildren ? $children : array($p); 
			foreach($checkPages as $child) {
				if($child->viewable()) {
					$showItem = true;
					break;
				}
			}
		}
		
		if(!$showItem) return '';

		//$class = strpos($this->wire('page')->path, $p->path) === 0 ? 'on' : '';
		$title = strip_tags((string) $p->title); 
		if(!strlen($title)) $title = $p->name; 
		$title = $this->_($title); // translate from context of default.php
		$out .= "<li>";
	
		if(!$numChildren && $p->template == 'admin' && $p->process) {
			$moduleInfo = $this->wire('modules')->getModuleInfo($p->process); 
			if(!empty($moduleInfo['nav'])) $children = $moduleInfo['nav'];
		}
	
		if(!$level && count($children)) {
	
			$out .= "<a href='$p->url' " . 
				"id='topnav-page-$p' " . 
				"data-from='topnav-page-{$p->parent}' " . 
				"class='page-$p- pw-dropdown-toggle'>" . 
				"$title</a>"; 
			
			$my = 'left-1 top';
			if(in_array($p->name, array('access', 'page', 'module'))) $my = 'left top';
			$out .= "<ul class='pw-dropdown-menu topnav' data-my='$my' data-at='left bottom'>";
	
			if($children instanceof PageArray) foreach($children as $c) {
			
				if(!$c->process) continue; 
				$moduleInfo = $this->wire('modules')->getModuleInfo($c->process); 
				if($isSuperuser) $hasPermission = true;
					else if(!empty($moduleInfo['permissionMethod'])) $hasPermission = $c->viewable();
					else if(!empty($moduleInfo['permission'])) $hasPermission = $this->wire('user')->hasPermission($moduleInfo['permission']);
					else $hasPermission = false;
				
				if(!$hasPermission) continue; 
				
				if(!empty($moduleInfo['nav'])) {
					// process defines its own subnav
					$icon = $this->getPageIcon($c);
					$title = $this->_($c->title); 
					if(!$title) $title = $c->name; 
					$out .= 
						"<li><a class='pw-has-items page-$c-' data-from='topnav-page-$p' href='$c->url'>$icon$title</a>" . 
						"<ul>" . $this->renderTopNavItemArray($c, $moduleInfo['nav']) . "</ul></li>";
					
				} else if(!empty($moduleInfo['useNavJSON'])) {
					// has ajax items
					$title = $this->getPageTitle($c);
					if(!strlen($title)) continue;
					$icon = $this->getPageIcon($c);
					$out .=
						"<li><a class='pw-has-items pw-has-ajax-items page-$c-' " . 
						"data-from='topnav-page-$p' data-json='{$c->url}navJSON/' " .
						"href='$c->url'>$icon$title</a><ul></ul></li>";

				} else {
					// regular nav item
					$out .= $this->renderTopNavItem($c, $level+1);
				}
				
			} else if(is_array($children) && count($children)) {
				$out .= $this->renderTopNavItemArray($p, $children); 
			}
	
			$out .= "</ul>";
	
		} else {
			
			//$class = $class ? " class='$class'" : '';
			$url = $p->url;
			$icon = $level > 0 ? $this->getPageIcon($p) : '';
			
			// The /page/ and /page/list/ are the same process, so just keep them on /page/ instead. 
			if(strpos($url, '/page/list/') !== false) $url = str_replace('/page/list/', '/page/', $url); 
			
			$out .= "<a class='page-$p-' href='$url'>$icon$title</a>"; 
		}
	
		$out .= "</li>";
	
		return $out; 
	}

	/**
	 * Get navigation title for the given page, return blank if page should not be shown
	 * 
	 * @param Page $c
	 * @return string
	 * 
	 */
	protected function getPageTitle(Page $c) {
		if($c->name == 'add' && $c->parent->name == 'page') {
			// ProcessPageAdd: avoid showing this menu item if there are no predefined family settings to use
			$numAddable = $this->wire('session')->getFor('ProcessPageAdd', 'numAddable');
			if($numAddable === null) {
				/** @var ProcessPageAdd $processPageAdd */
				$processPageAdd = $this->wire('modules')->getModule('ProcessPageAdd', array('noInit' => true));
				if($processPageAdd) {
					$addData = $processPageAdd->executeNavJSON(array("getArray" => true));
					$numAddable = $addData['list'];
				}
			}
			if(!$numAddable) return '';
			$title = $this->getAddNewLabel();
		} else {
			$title = $this->_($c->title);
		}
		$title = $this->wire('sanitizer')->entities1($title);
		return $title;
	}

	/**
	 * Renders static navigation from an array coming from getModuleInfo()['nav'] array (see wire/core/Process.php)
	 * 
	 * @param Page $p
	 * @param array $nav
	 * @return string
	 * 
	 */
	protected function renderTopNavItemArray(Page $p, array $nav) {
		// process module with 'nav' property
		$out = '';
		$textdomain = str_replace($this->wire('config')->paths->root, '/', $this->wire('modules')->getModuleFile($p->process));
		
		foreach($nav as $item) {
			if(!empty($item['permission']) && !$this->wire('user')->hasPermission($item['permission'])) continue;
			$icon = empty($item['icon']) ? '' : "<i class='fa fa-fw fa-$item[icon]'></i>&nbsp;";
			$label = __($item['label'], $textdomain); // translate from context of Process module
			if(empty($item['navJSON'])) {
				$out .= "<li><a href='{$p->url}$item[url]'>$icon$label</a></li>";
			} else {
				$item['navJSON'] = $this->wire('sanitizer')->entities($item['navJSON']);
				$out .= 
					"<li>" . 
						"<a class='pw-has-items pw-has-ajax-items' " . 
							"data-from='topnav-page-$p' " . 
							"data-json='{$p->url}$item[navJSON]' " . 
							"href='{$p->url}$item[url]'>" . 
								"$icon$label&nbsp;&nbsp;&nbsp;" . 
						"</a>" . 
						"<ul></ul>" . 
					"</li>";
			}
		}
		return $out; 
	}

	/**
	 * Render all top navigation items, ready to populate in ul#topnav
	 *
	 * @return string
	 *
	 */
	public function renderTopNavItems() {
		
		$cache = $this->wire('session')->getFor('AdminThemeDefault', 'topnav');
		if($cache) {
			$this->renderTopNavMarkCurrent($cache);	
			return $cache;
		}
		
		$out = '';
		$outMobile = '';
		$outTools = '';
		$config = $this->wire('config'); 
		$admin = $this->wire('pages')->get($config->adminRootPageID); 
		$user = $this->wire('user'); 
	
		foreach($admin->children("check_access=0") as $p) {
			if(!$p->viewable()) continue; 
			$out .= $this->renderTopNavItem($p);
			
			$title = $this->getPageTitle($p);
			if(strlen($title)) {
				$icon = $this->getPageIcon($p);
				$outMobile .= "<li><a href='$p->url'>$icon$title</a></li>";
			}
		}
	
		
		// @todo move outTools to separate hookable method, so new tools can be added
		$outTools .=	
			"<li><a href='{$config->urls->root}'><i class='fa fa-fw fa-eye'></i> " . 
			$this->_('View Site') . "</a></li>";
	
		if($user->isLoggedin()) {
			if($user->hasPermission('profile-edit')) {
				$outTools .= 
					"<li><a href='{$config->urls->admin}profile/'><i class='fa fa-fw fa-user'></i> " . 
					$this->_('Profile') . " <small>{$user->name}</small></a></li>";
			}
			$outTools .= 
				"<li><a href='{$config->urls->admin}login/logout/'>" . 
				"<i class='fa fa-fw fa-power-off'></i> " . $this->_('Logout') . "</a></li>";
		}
	
		$outMobile = "<ul id='topnav-mobile' class='pw-dropdown-menu topnav' data-my='left top' data-at='left bottom'>$outMobile$outTools</ul>";
	
		$out .=	
			"<li>" . 
			"<a target='_blank' id='tools-toggle' class='pw-dropdown-toggle' href='{$config->urls->root}'>" . 
			"<i class='fa fa-wrench'></i></a>" . 
			"<ul class='pw-dropdown-menu topnav' data-my='left top' data-at='left bottom'>" . $outTools . 
			"</ul></li>";
	
		$out .=	
			"<li class='collapse-topnav-menu'><a href='$admin->url' class='pw-dropdown-toggle'>" . 
			"<i class='fa fa-lg fa-bars'></i></a>$outMobile</li>";
		
		$this->wire('session')->setFor('AdminThemeDefault', 'topnav', $out);
		$this->renderTopNavMarkCurrent($out);	
		return $out; 
	}

	/**
	 * Identify current "on" items in the topnav and add appropriate class
	 * 
	 * @param $out
	 * 
	 */
	protected function renderTopNavMarkCurrent(&$out) {
		$page = $this->wire('page');
		foreach($page->parents()->and($page) as $p) {
			$out = str_replace("page-$p-", "page-$p- on", $out);
		}
	}
	
	/**
	 * Render the browser <title>
	 *
	 * @return string
	 *
	 */
	public function renderBrowserTitle() {
		$browserTitle = $this->wire('processBrowserTitle'); 
		if(!$browserTitle) $browserTitle = $this->_(strip_tags($this->wire('page')->get('title|name'))) . ' &bull; ProcessWire';
		if(strpos($browserTitle, '&') !== false) $browserTitle = html_entity_decode($browserTitle, ENT_QUOTES, 'UTF-8'); // we don't want to make assumptions here
		$browserTitle = $this->wire('sanitizer')->entities($browserTitle, ENT_QUOTES, 'UTF-8'); 
		if(!$this->wire('input')->get('modal')) {
			$httpHost = $this->wire('config')->httpHost;
			if(strpos($httpHost, 'www.') === 0) $httpHost = substr($httpHost, 4); // remove www
			if(strpos($httpHost, ':')) $httpHost = preg_replace('/:\d+/', '', $httpHost); // remove port
			$browserTitle .= ' &bull; ' . $this->wire('sanitizer')->entities($httpHost);
		}
		return $browserTitle; 
	}
	
	/**
	 * Render the class that will be used in the <body class=''> tag
	 *
	 * @return string
	 *
	 */
	public function renderBodyClass() {
		$page = $this->wire('page');
		$modal = $this->wire('input')->get('modal');
		$bodyClass = '';
		if($modal) $bodyClass .= 'modal ';
		if($modal == 'inline') $bodyClass .= 'modal-inline ';
		$bodyClass .= "id-{$page->id} template-{$page->template->name} pw-init";
		if($this->wire('config')->js('JqueryWireTabs')) $bodyClass .= " hasWireTabs";
		if($this->wire('input')->urlSegment1) $bodyClass .= " hasUrlSegments";
		$bodyClass .= ' ' . $this->wire('adminTheme')->getBodyClass(); 
		return trim($bodyClass); 
	}
	
	/**
	 * Render the required javascript 'config' variable for the document <head>
	 *
	 * @return string
	 *
	 */
	public function renderJSConfig() {

		/** @var Config $config */
		$config = $this->wire('config'); 

		/** @var array $jsConfig */
		$jsConfig = $config->js();
		$jsConfig['debug'] = $config->debug;
	
		$jsConfig['urls'] = array(
			'root' => $config->urls->root, 
			'admin' => $config->urls->admin, 
			'modules' => $config->urls->modules, 
			'core' => $config->urls->core, 
			'files' => $config->urls->files, 
			'templates' => $config->urls->templates,
			'adminTemplates' => $config->urls->adminTemplates,
			); 

		$out = 
			"var ProcessWire = { config: " . wireEncodeJSON($jsConfig, true, $config->debug) . " }; " . 
			"var config = ProcessWire.config; "; // legacy support
		
		return $out;
	}
	
	public function getAddNewLabel() {
		return $this->_('Add New');
	}


}
