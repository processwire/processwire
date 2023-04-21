<?php namespace ProcessWire;

/**
 * AdminThemeRenoHelpers.php
 * 
 * Rendering helper functions for use with for AdminThemeReno
 * Copyright (C) 2015 by Tom Reno (Renobird)
 * http://www.tomrenodesign.com
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * 
 */

require(realpath(__DIR__ . '/../AdminThemeDefault/AdminThemeDefaultHelpers.php')); 

class AdminThemeRenoHelpers extends AdminThemeDefaultHelpers {

	/**
	 * Render runtime notices div#notices
	 *
	 * @param Notices $notices
	 * @param array $options
	 * @return string
	 *
	 */
	public function renderAdminNotices($notices, array $options = array()) {
		$options['messageIcon'] = 'check-circle';
		$options['itemMarkup'] = "\n\t\t<li class='{class}'>{remove}<i class='fa fa-{icon}'></i> {text}</li>";
		return parent::renderAdminNotices($notices, $options); 
	}
	
	/**
	 * Render quicklinks for templates/fields. Designed to be called by renderSideNav()
	 * 
	 * @param Page $page
	 * @param array $items
	 * @param string $title
	 * @param string $json
	 * @return string
	 * 
	 */
	public function renderQuicklinks(Page $page, array $items, $title, $json = '') {
		
		if($json) $json = $page->url . $json;

		$textdomain = str_replace($this->wire('config')->paths->root, '/', $this->wire('modules')->getModuleFile($page->process));
		$out = 
			"<ul class='quicklinks' data-json='$json'>" .
			"<li class='quicklink-close'><i class='fa fa-fw fa-bolt quicklinks-spinner'></i>$title <div class='close'><i class='fa fa-times'></i></div></li>";
		
		foreach($items as $item) {
			if(!empty($item['permission']) && !$this->wire('user')->hasPermission($item['permission'])) continue;
			$label = __($item['label'], $textdomain); // translate from context of Process module
			$url = $page->url . $item['url'];
			$out .= "<li><a href='$url'>$label</a></li>";
		}

		$out .= "</ul>";

		return $out;
	}

	/**
	 * Render top navigation items
	 * 
	 * @return string
	 * 
	 */
	public function renderTopNav() {

		$items = array();
		$class = '';
		$user = $this->wire('user');
		$config = $this->wire("config");
		$adminTheme = $this->wire('adminTheme');
		$fieldName = "avatar_field_" . $user->template->name;
		$adminTheme->$fieldName != '' ?  $avatarField = $adminTheme->$fieldName : $avatarField = '';
		$avatarField != '' ?  $imgField = $user->get($avatarField) : $imgField = '';
		$avatar = "<i class='fa $adminTheme->profile'></i>";
	
		if($user->isLoggedin()) { 
			if($config->debug && $user->isSuperuser()) {
				$debugLabel = __('Debug Mode Tools', '/wire/templates-admin/debug.inc');
				$items[] = array(
					"class" => "",
					"label" => "<i class='fa fa-bug'></i>",
					"link" => "#",
					"attrs" => "title='$debugLabel' onclick=\"$('#debug_toggle').click();return false;\"", 
				);
			}
			if($this->wire('process') != 'ProcessPageList') {
				$treeLabel = $this->_('Tree');
				$items[] = array(
					"class" => "",
					"label" => "<i class='fa fa-sitemap'></i>",
					"link" => $config->urls->admin . 'page/',
					"attrs" => "class='pw-panel' " .
						"data-tab-text='$treeLabel' " .
						"data-tab-icon='sitemap' " .
						"title='$treeLabel'"
				);
			}
		}

		// View site
		$items[] = array(
			"class" => "",
			"label" => "<i class='fa {$adminTheme->home}'></i>",
			"link" => $config->urls->root
		);

		// Search toggle
		$items[] = array(
			"class" => "search-toggle",
			"label" => "<i class='fa fa-search'></i>",
			"link" => "#"
		);

		// Superuser quick links
		if ($this->user->isSuperuser()){
			$items[] = array(
				"class" => "superuser",
				"label" => "<i class='fa fa-bolt'></i>",
				"children" => array(
					"<i class='fa fa-life-ring'></i> " . $this->_('Support Forums')  => array('https://processwire.com/talk/', 'target="_blank"'),
					"<i class='fa fa-book'></i> " . $this->_('Documentation') => array('https://processwire.com/docs/', 'target="_blank"'),
					"<i class='fa fa-github'></i> " . $this->_('Github Repo') => array('https://github.com/processwire/processwire/', 'target="_blank"'),
					"<i class='fa fa-code'></i> " . $this->_('Cheatsheet')  => array('http://cheatsheet.processwire.com', 'target="_blank"'),
					"<i class='fa fa-anchor'></i> " . $this->_('Hooks')  => array('https://processwire.com/api/hooks/', 'target="_blank"'),
				)
			);
		}

		// Avatar field for user information
		if ($imgField != '') {
			$class = 'avatar';
			count($imgField) ? $img = $imgField->first() : $img = $imgField;
			$userImg = $img->size(52,52); // render at 2x for hi-dpi (52x52 for 26x26)
			$avatar = "<img src='$userImg->url' alt='$user->name' />";
		}

		// User information, profile, logout
		$items[] = array(
			"class" => "avatar",
			"label" => "$avatar <span>" . $this->getDisplayName($user) . "</span>",
			"children" => array(
				"<i class='fa fa-user'></i> " . $this->_('Profile') => $config->urls->admin . "profile/",
				"<i class='fa $adminTheme->signout'></i> " . $this->_('Logout') => $config->urls->admin . "login/logout/"
			)
		);

		return $this->topNavItems($items);

	}

	/**
	 * Render top navigation items (hookable)
	 * 
	 * @return string
	 * 
	 */
	public function ___topNavItems(array $items) {
		
		$out = '';
		$defaults = array(
			"class" => "",
			"label" => "<i class='fa fa-question-circle'></i>",
			"link" => "#",
			"attrs" => "",
			"children" => false
		);

		foreach ($items as $item){

			// Create variables for each default key.
			// If $item contains a key listed in the default array, use that value, otherwise use the default.
			foreach ($defaults as $key => $val){
				array_key_exists($key, $item) ? ${$key} = $item[$key] : ${$key} = $val; 
			}

			// Add dropdown class if there are children
			if($children) $class .= " pw-dropdown";

			$out .= "<li class='$class'><a href='$link' $attrs>$label</a>";
				if (is_array($children)){
					$out .= "<ul>";
						foreach($children as $child_label => $child_link){
							$attrs = '';
							$current = ($this->wire('page')->path == $child_link) ? 'class="current"' : ''; // current class
							if (is_array($child_link)){
								$attrs = implode(" ", array_slice($child_link,1)); // any array items other than the 1st are treated as attrs
								$child_link = $child_link[0]; // first item in array is the url
							}
							$out .= "<li><a href='$child_link' $current $attrs>$child_label</a></li>";
						}
					$out .= "</ul>";
				}
			$out .= "</li>";
		}

		return $out;

	}

	/**
	 * Render a side navigation items
	 *
	 * This function designed primarily to be called by the renderSideNavItems() function. 
	 *
	 * @param Page $p
	 * @return string
	 *
	 */
	public function renderSideNavItem(Page $p) {
		
		$isSuperuser = $this->wire('user')->isSuperuser();
		$sanitizer = $this->wire('sanitizer');
		$modules = $this->wire('modules'); 
		$showItem = $isSuperuser;
		$children = $p->numChildren() ? $p->children("check_access=0") : array();
		if($p->name == 'page' && !$children->has("name=list")) {
			// +PW27: ensure the "Tree" page is shown if it is hidden
			$children->prepend($p->child("name=list, include=hidden"));
		}
		$out = '';
		$iconName = $p->name;
		$icon = $this->wire('adminTheme')->$iconName;
		if(!$icon) $icon = 'fa-file-text-o';
		$numViewable = 0; 
		
		if(!$showItem) { 
			$checkPages = count($children) ? $children : array($p); 
			foreach($checkPages as $child) {
				if($child->viewable()) {
					$showItem = true;
					$numViewable++;
					if($numViewable > 1) break; // we don't need to know about any more
				}
			}
		}
		
		// don't bother with a drop-down here if only 1 child
		if($p->name == 'page' && !$isSuperuser && $numViewable < 2) {
			$children = array();
		}
	
		if(!$showItem) return '';
	
		if($p->process) { 
			$moduleInfo = $modules->getModuleInfo($p->process);
			$textdomain = str_replace($this->wire('config')->paths->root, '/', $this->wire('modules')->getModuleFile($p->process));
		} else {
			$moduleInfo = array();
			$textdomain = '';
		}
		
		if(!count($children) && !empty($moduleInfo['nav'])) $children = $moduleInfo['nav'];
		
		$class = strpos($this->wire('page')->path, $p->path) === 0 ? 'current' : ''; // current class
		$class .= count($children) > 0 ? " parent" : ''; // parent class
		$title = $sanitizer->entities1((string) $this->_($p->get('title|name')));
		$currentPagePath = $this->wire('page')->url; // use URL to support sub directory installs

		$out .= "<li>";
	
		if(count($children) && WireArray::iterable($children)) {

			$out .= "<a href='$p->url' class='$class $p->name '><i class='fa {$icon}'></i> $title</a>"; 
			$out .= "<ul>";

			foreach($children as $c) {
				$navJSON = '';
				
				if(is_array($c)) {
					// $c is moduleInfo nav array
					$moduleInfo = array();
					if(isset($c['permission']) && !$this->wire('user')->hasPermission($c['permission'])) continue;
					$segments = $this->input->urlSegments ? implode("/", $this->input->urlSegments) . '/' : '';
					$class = $currentPagePath . $segments == $p->path . $c['url'] ? 'current' : '';
					$title = $sanitizer->entities1(__($c['label'], $textdomain));
					$url = $p->url . $c['url'];
					if(isset($c['navJSON'])) {
						$navJSON = $c['navJSON']; // url part
						$moduleInfo['useNavJSON'] = true;
					}
					$c = $p; // substitute 
					
				} else {
					// $c is a Page object
					if(!$c->process || !$c->viewable()) continue;
					
					$list = array(
						$this->wire('config')->urls->admin . "page/",
						$this->wire('config')->urls->admin . "page/edit/"
					);
					
					in_array($currentPagePath, $list) ? $currentPagePath = $this->wire('config')->urls->admin . "page/list/" : '';
					$class = strlen($currentPagePath) && strpos($currentPagePath, $c->url) === 0 ? 'current' : ''; // child current class
					$name = $c->name;

					$moduleInfo = $c->process ? $modules->getModuleInfo($c->process) : array();
					$title = $this->getPageTitle($c);
					if(!strlen($title)) continue;
					$url = $c->url;
					// The /page/ and /page/list/ are the same process, so just keep them on /page/ instead. 
					if(strpos($url, '/page/list/') !== false) $url = str_replace('/page/list/', '/page/', $url);
				}
				
				$quicklinks = '';
				
				if(!empty($moduleInfo['useNavJSON'])) {
					// NOTE: 'useNavJSON' comes before 'nav' for AdminThemeReno only, since it does not support navJSON beyond one level
					// meaning this bypasses 'nav' if the module happens to also provide navJSON (see ProcessRecentPages example)
					if(empty($navJSON)) $navJSON = "navJSON/";
					$quicklinks = $this->renderQuicklinks($c, array(), $title, $navJSON); 
				} else if(!empty($moduleInfo['nav'])) {
					$quicklinks = $this->renderQuicklinks($c, $moduleInfo['nav'], $title); 
				}
				
				$icon = isset($moduleInfo['icon']) ? $moduleInfo['icon'] : '';
				$toggle = $quicklinks ? "<i class='quicklink-open fa fa-bolt'></i>" : "";
				if($class == 'current' && $icon) $title = "<i class='fa fa-fw fa-$icon current-icon'></i>$title";
				if($quicklinks) $class .= " has-quicklinks";
				$out .= "<li><a href='$url' class='$class' data-icon='$icon'><span>$title</span>$toggle</a>" . $quicklinks;
				$out .= "</li>";
			}

			$out .= "</ul>";

		} else {
			
			$class = $class ? " class='$class $p->name'" : "class='$p->name'";
			$out .= "<a href='$p->url' $class><i class='fa {$icon}'></i> <span>$title</span></a>"; 

		}

		$out .= "</li>";

		return $out; 
	}
	
	/**
	 * Render the the user display name as specified in module config.
	 *
	 * @return string
	 *
	 */
	
	public function getDisplayName(User $user) {
        
        $out = '';
        
        $adminTheme = $this->wire('adminTheme');
        $field_name = "userFields_". $user->template->name;
        trim("$adminTheme->$field_name") == '' ? $adminTheme->$field_name = 'name' : ''; // force to name field if empty
        $userFields = explode(',', "$adminTheme->$field_name");
        
        foreach($userFields as $f){
            $f = trim($f);
            if ($f == 'name'){
                $out .= "{$user->name} "; // can't use wire('fields') to get name field
            } else {
                $field = $this->wire('fields')->get($f);
                if($field instanceof Field && in_array($field->type, array('FieldtypeText', 'FieldtypeConcat', 'FieldtypePageTitle'))){
                    $out .= $this->wire('sanitizer')->entities1($user->$f) . " ";
				}
            }
        }

        return rtrim($out, ' '); // clean up the trailing space. 
    }

	/**
	 * Render all sidenav navigation items, ready to populate in ul#main-nav
	 *
	 * @return string
	 *
	 */
	public function renderSideNavItems() {
		$out = '';
		$admin = $this->wire('pages')->get($this->wire('config')->adminRootPageID); 
	
		foreach($admin->children("check_access=0") as $p) {
			if(!$p->viewable()) continue; 
			$out .= $this->renderSideNavItem($p);
		}
		
		return $out; 
	}
}
