<?php namespace ProcessWire;

/**
 * ProcessWire Process
 *
 * Process is the base Module class for each part of ProcessWire's web admin.
 * 
 * #pw-summary Process modules are self contained applications that run in the ProcessWire admin. 
 * #pw-summary-views Applicable only to Process modules that are using external output/view files. 
 * #pw-summary-module-interface See the `Module` interface for full details on these methods. 
 * #pw-order-groups common,views,module-interface,hooker
 * #pw-body = 
 * Please be sure to see the `Module` interface for full details on methods you can specify in a Process module. 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @method string|array execute()
 * @method string|array executeUnknown() Called when urlSegment matches no execute[Method], only if implemented.
 * @method Process headline(string $headline)
 * @method Process browserTitle(string $title)
 * @method Process breadcrumb(string $href, string $label)
 * @method void install()
 * @method void uninstall()
 * @method void upgrade($fromVersion, $toVersion)
 * @method Page installPage($name = '', $parent = null, $title = '', $template = 'admin', $extras = array()) #pw-internal
 * @method int uninstallPage() #pw-internal
 * @method string|array executeNavJSON(array $options = array()) #pw-internal @todo
 * @method void ready()
 * @method void setConfigData(array $data)
 * @method void executed($methodName) Hook called after a method has been executed in the Process
 *
 */

abstract class Process extends WireData implements Module {

	/**
	 * Per the Module interface, return an array of information about the Process
	 *
	 * The 'permission' property is specific to Process instances, and allows you to specify the name of a permission
	 * required to execute this process. 
	 * 
	 * Note that you may want your Process module to use the 'page' property defined below. To make use of it, make
	 * sure it is included in your module info, and make sure your Process module either omits install/uninstall methods,
	 * or calls the ones in this class, i.e. 
	 * 
	 * public function ___install() {
	 *   parent::___install(); 
	 * }
	 *
	 */
	
	/*
	public static function getModuleInfo() {
		return array(
			'title' => '',				// printable name/title of module
			'version' => 1, 			// version number of module
			'summary' => '', 			// one sentence summary of module
			'href' => '', 				// URL to more information (optional)
			'permanent' => false, 		// true if module is permanent and thus not uninstallable (3rd party modules should omit this)
	 		'page' => array( 			// optionally install/uninstall a page for this process automatically
	 			'name' => 'page-name', 	// name of page to create
	 			'parent' => 'setup', 	// parent name (under admin) or omit or blank to assume admin root
	 			'title' => 'Title', 	// title of page, or omit to use the title already specified above
	 			)
			),
			'useNavJSON' => true, 		// Supports JSON navigation?
			'nav' => array(				// Optional navigation options for admin theme drop downs
				array(
					'url' => 'action/',
					'label' => 'Some Action', 
					'permission' => 'some-permission', // optional permission required to access this item
					'icon' => 'folder-o', // optional icon
					'navJSON' => 'navJSON/?custom=1' // optional JSON url to get items, relative to page URL that Process module lives on
				),
				array(
					'url' => 'action2/',
					'label' => 'Another Action', 
					'icon' => 'plug',
				),
			),
			'permission' => '', 		// name of permission required to execute this Process (optional)
			'permissions' => array(..),	// see Module.php for details
			'permissionMethod' => '', 	// Optional name of a static method to perform additional permission checks. 
										// It receives array with: wire (PW instance), user (User), page (Page), 
										// info (moduleInfo array), method (requested method)
										// It should return a true or false.
		);
	}
 	*/

	/**
	 * File to use for output view
	 * 
	 * Used when execute methods return an array of vars, or have called setViewVars()
	 * 
	 * @var string
	 * 
	 */
	private $_viewFile = '';

	/**
	 * Variables to send to the output view file, populated only if setViewVars() has been called
	 * 
	 * @var array associative
	 * 
	 */
	private $_viewVars = array();

	/**
	 * Construct
	 * 
	 */
	public function __construct() { 
		parent::__construct();
	}

	/**
	 * Per the Module interface, Initialize the Process, loading any related CSS or JS files
	 * 
	 * #pw-internal
	 *
	 */
	public function init() { 
		$this->wire()->modules->loadModuleFileAssets($this); 
	}

	/**
	 * Execute this Process and return the output. You may have any number of execute[name] methods, triggered by URL segments.
	 * 
	 * When any execute() method returns a string, it us used as the actual output. 
	 * When the method returns an associative array, it is considered an array of variables
	 * to send to the output view layer. Returned array must not be empty, otherwise it cannot
	 * be identified as an associative array. 
	 * 
	 * This execute() method is called when no URL segments are present. You may have any 
	 * number of execute() methods, i.e. `executeFoo()` would be called for the URL `./foo/` 
	 * and `executeBarBaz()` would be called for the URL `./bar-baz/`.
	 *
	 * @return string|array
	 *
	 */
	public function ___execute() { 
		return ''; // if returning output directly
		// return array('name' => 'value'); // if populating a view
	}

	/**
	 * Hookable method automatically called after execute() method has finished.
	 * 
	 * #pw-hooker
	 * 
	 * @param string $method Name of method that was executed
	 * 
	 */
	public function ___executed($method) { }

	/*
	 * Add this method to your Process module if you want a catch-all fallback 
	 * 
	 * It should check $input->urlSegment1 for the method that was requested.
	 * This is commented out here since it is not used by Process modules unless manually added.
	 * 
	 * @since 3.0.133
	 * @return string|array
	 * 
	public function ___executeUnknown() {
	}
	*/

	/**
	 * Get a value stored in this Process
	 * 
	 * #pw-internal
	 * 
	 * @param string $key
	 * @return mixed
	 *
	 */
	public function get($key) {
		if(($value = $this->wire($key)) !== null) return $value; 
		return parent::get($key); 
	}

	/**
	 * Per the Module interface, Process modules only retain one instance in memory
	 * 
	 * #pw-internal
	 *
	 */
	public function isSingular() {
		return true; 
	}

	/**
	 * Per the Module interface, Process modules are not loaded until requested from from the API
	 * 
	 * #pw-internal
	 *
	 */
	public function isAutoload() {
		return false; 
	}

	/**
	 * Set the current primary headline to appear in the admin interface
	 * 
	 * ~~~~~
	 * $this->headline("Hello World"); 
	 * ~~~~~
	 * 
	 * @param string $headline
	 * @return $this
	 *
	 */
	public function ___headline($headline) {
		$this->wire('processHeadline', $headline); 
		return $this; 
	}
	
	/**
	 * Set the current browser title tag
	 * 
	 * ~~~~~
	 * $this->browserTitle("Hello World"); 
	 * ~~~~~
	 *
	 * @param string $title
	 * @return $this
	 *
	 */
	public function ___browserTitle($title) {
		$this->wire('processBrowserTitle', $title);
		return $this;
	}

	/**
	 * Add a breadcrumb
	 * 
	 * ~~~~~
	 * $this->breadcrumb("../", "Widgets"); 
	 * ~~~~~
	 * 
	 * @param string $href URL of breadcrumb
	 * @param string $label Label for breadcrumb
	 * @return $this
	 *
	 */
	public function ___breadcrumb($href, $label) {
		if(is_array($label)) return $this;
		$label = (string) $label;
		$pos = strpos($label, '/'); 
		if($pos !== false && strpos($href, '/') === false) {
			// arguments got reversed, we'll work with it anyway...
			if($pos === 0 || $label[0] == '.' || substr($label, -1) == '/') {
				$_href = $href; 
				$href = $label;
				$label = $_href;
			}
		}
		$this->wire()->breadcrumbs->add(new Breadcrumb($href, $label));
		return $this;
	}

	/**
	 * Per the Module interface, Install the module
	 *
	 * By default a permission equal to the name of the class is installed, unless overridden with 
	 * the 'permission' property in your module information array. 
	 * 
	 * See the `Module` interface and the `install` method there for more details. 
	 * 
	 * #pw-group-module-interface
	 *
	 */
	public function ___install() {
		$info = $this->wire()->modules->getModuleInfoVerbose($this, array('noCache' => true)); 
		// if a 'page' property is provided in the moduleInfo, we will create a page and assign this process automatically
		if(!empty($info['page'])) { // bool, array, or string
			$defaults = array(
				'name' => '', 
				'parent' => null, 
				'title' => '', 
				'template' => 'admin'
			);
			$a = $defaults;
			if(is_array($info['page'])) {
				$a = array_merge($a, $info['page']);
			} else if(is_string($info['page'])) {
				$a['name'] = $info['page'];
			}
			// find any other properties that were specified, which will will send as $extras properties
			$extras = array();
			foreach($a as $key => $value) {
				if(in_array($key, array_keys($defaults))) continue; 
				$extras[$key] = $value; 
			}
			// install the page
			$this->installPage($a['name'], $a['parent'], $a['title'], $a['template'], $extras); 
		}
	}

	/**
	 * Uninstall this Process
	 *
	 * Note that the Modules class handles removal of any Permissions that the Process may have installed.
	 * 
	 * See the `Module` interface and the `uninstall` method there for more details.
	 * 
	 * #pw-group-module-interface
	 *
	 */
	public function ___uninstall() {
		$info = $this->wire()->modules->getModuleInfoVerbose($this, array('noCache' => true));
		// if a 'page' property is provided in the moduleInfo, we will trash pages using this Process automatically
		if(!empty($info['page'])) $this->uninstallPage();
	}

	/**
	 * Called when module version changes
	 *
	 * See the `Module` interface and the `upgrade` method there for more details.
	 * 
	 * #pw-group-module-interface
	 * 
	 * @param int|string $fromVersion Previous version
	 * @param int|string $toVersion New version
	 * @throws WireException if upgrade fails
	 * 
	 */
	public function ___upgrade($fromVersion, $toVersion) {
		// any code needed to upgrade between versions
		if($fromVersion && $toVersion && false === true) {
			throw new WireException('Uncallable exception for phpdoc');
		}
	}

	/**
	 * Install a dedicated page for this Process module and assign it this Process
	 * 
	 * To be called by Process module's ___install() method. 
	 * 
	 * #pw-hooker
	 *
	 * @param string $name Desired name of page, or omit (or blank) to use module name
	 * @param Page|string|int|null Parent for the page, with one of the following:
	 * 	- name of parent, relative to admin root, i.e. "setup"
	 * 	- Page object of parent
	 * 	- path to parent
	 * 	- parent ID
	 * 	- Or omit and admin root is assumed
	 * @param string $title Omit or blank to pull title from module information
	 * @param string|Template Template to use for page (omit to assume 'admin')
	 * @param array $extras Any extra properties to assign (like status)
	 * @return Page Returns the page that was created
	 * @throws WireException if page can't be created
	 *
	 */
	protected function ___installPage($name = '', $parent = null, $title = '', $template = 'admin', $extras = array()) {
		
		$pages = $this->wire()->pages;
		$config = $this->wire()->config;
		$modules = $this->wire()->modules;
		$sanitizer = $this->wire()->sanitizer;
		$languages = $this->wire()->languages;
		
		$info = $modules->getModuleInfoVerbose($this);
		$name = $sanitizer->pageName($name);
		if(!strlen($name)) {
			$name = strtolower(preg_replace('/([A-Z])/', '-$1', str_replace('Process', '', $this->className())));
		}
		$adminPage = $pages->get($config->adminRootPageID); 
		if($parent instanceof Page) {
			// already have what we  need
		} else if(ctype_digit("$parent")) {
			$parent = $pages->get((int) $parent);
		} else if(strpos("$parent", '/') !== false) {
			$parent = $pages->get($parent);
		} else if($parent) {
			$parent = $sanitizer->pageName($parent);
			if(strlen($parent)) $parent = $adminPage->child("include=all, name=$parent");
		}
		if(!$parent || !$parent->id) $parent = $adminPage; // default
		$page = $parent->child("include=all, name=$name"); // does it already exist?
		if($page->id && "$page->process" == "$this") return $page; // return existing copy
		if($languages) $languages->setDefault();
		$page = $pages->newPage($template ? $template : 'admin');
		$page->name = $name; 
		$page->parent = $parent; 
		$page->process = $this;
		$page->title = $title ? $title : $info['title'];
		foreach($extras as $key => $value) $page->set($key, $value); 
		if($languages) $languages->unsetDefault();
		$pages->save($page, array('adjustName' => true)); 
		if(!$page->id) throw new WireException("Unable to create page: $parent->path$name"); 
		$this->message(sprintf($this->_('Created Page: %s'), $page->path)); 
		return $page;
	}

	/**
	 * Uninstall (trash) dedicated pages for this Process module
	 *
	 * If there is more than one page using this Process, it will trash them all.
	 * 
	 * To be called by the Process module's ___uninstall() method. 
	 * 
	 * #pw-hooker
	 * 
	 * @return int Number of pages trashed
	 *
	 */
	protected function ___uninstallPage() {
		$pages = $this->wire()->pages;
		$moduleID = $this->wire()->modules->getModuleID($this);
		if(!$moduleID) return 0;
		$n = 0; 
		foreach($pages->find("process=$moduleID, include=all") as $page) {
			if("$page->process" != "$this") continue; 
			$page->process = null;
			$this->message(sprintf($this->_('Trashed Page: %s'), $page->path)); 
			$pages->trash($page);
			$n++;
		}
		return $n;
	}

	/**
	 * Return JSON data of items managed by this Process for use in navigation
	 * 
	 * Optional/applicable only to Process modules that manage groups of items.
	 * 
	 * This method is only used if your module information array contains a `useNavJSON` property with boolean true. 
	 * 
	 * #pw-internal @todo work on documenting this method further
	 * 
	 * @param array $options For descending classes to modify behavior (see $defaults in method)
	 * @return string|array rendered JSON string or array if `getArray` option is true. 
	 * @throws Wire404Exception if getModuleInfo() doesn't specify useNavJSON=true;
	 * 
	 */
	public function ___executeNavJSON(array $options = array()) {
		
		$sanitizer = $this->wire()->sanitizer;
		$modules = $this->wire()->modules;
		$config = $this->wire()->config;
		$page = $this->wire()->page;
		
		$defaults = array(
			'items' => array(),
			'itemLabel' => 'name', 
			'itemLabel2' => '', // smaller secondary label, when needed
			'edit' => 'edit?id={id}', // URL segment for edit
			'add' => 'add', // URL segment for add
			'addLabel' => __('Add New', '/wire/templates-admin/default.php'),
			'addIcon' => 'plus-circle',
			'iconKey' => 'icon', // property/field containing icon, when applicable
			'icon' => '', // default icon to use for items
			'classKey' => '_class', // property to pull additional class names from. Example class: "separator" or "highlight"
			'labelClassKey' => '_labelClass', // property to pull class for element to wrap label
			'sort' => true, // automatically sort items A-Z?
			'getArray' => false, // makes this method return an array rather than JSON
		);
		
		$options = array_merge($defaults, $options); 
		$moduleInfo = $modules->getModuleInfo($this); 
		if(empty($moduleInfo['useNavJSON'])) {
			throw new Wire404Exception('No JSON nav available', Wire404Exception::codeSecondary);
		}

		$data = array(
			'url' => $page->url,
			'label' => $this->_((string) $page->get('title|name')),
			'icon' => empty($moduleInfo['icon']) ? '' : $moduleInfo['icon'], // label icon
			'add' => array(
				'url' => $options['add'],
				'label' => $options['addLabel'], 
				'icon' => $options['addIcon'], 
			),
			'list' => array(),
		);
		
		if(empty($options['add'])) $data['add'] = null;
		
		foreach($options['items'] as $item) {
			$icon = '';
			if(is_object($item)) {
				$id = $item->id;
				$name = $item->name; 
				$label = (string) $item->{$options['itemLabel']};
				$icon = str_replace(array('icon-', 'fa-'),'', (string) $item->{$options['iconKey']});
				$class = $item->{$options['classKey']};
			} else if(is_array($item)) {
				$id = isset($item['id']) ? $item['id'] : '';
				$name = isset($item['name']) ? $item['name'] : '';
				$label = isset($item[$options['itemLabel']]) ? $item[$options['itemLabel']] : '';
				$class = isset($item[$options['classKey']]) ? $item[$options['classKey']] : '';	
				if(isset($item[$options['iconKey']])) $icon = str_replace(array('icon-', 'fa-'),'', (string) $item[$options['iconKey']]);
			} else {
				$this->error("Item must be object or array: $item"); 
				continue;
			}
			if(empty($icon) && $options['icon']) $icon = $options['icon'];
			$_label = $label;
			$label = $sanitizer->entities1($label);
			while(isset($data['list'][$_label])) $_label .= "_";
		
			if($options['itemLabel2']) {
				$label2 = is_array($item) ? $item[$options['itemLabel2']] : $item->{$options['itemLabel2']}; 
				if(strlen("$label2")) {
					$label2 = $sanitizer->entities1($label2);
					$label .= " <small>$label2</small>";
				}
			}
			
			if(!empty($options['labelClassKey'])) {
				if(is_array($item)) {
					$labelClass = isset($item[$options['labelClassKey']]) ? $item[$options['labelClassKey']] : '';
				} else {
					$labelClass = is_object($item) ? $item->{$options['labelClassKey']} : '';
				}
				if($labelClass) {
					$labelClass = $sanitizer->entities($labelClass);
					$label = "<span class='$labelClass'>$label</span>";
				}
			}
			
			$data['list'][$_label] = array(
				'url' => str_replace(array('{id}', '{name}'), array($id, $name), $options['edit']),
				'label' => $label,
				'icon' => $icon, 
				'className' => $class, 
			);
		}
		// sort alpha, case insensitive
		if($options['sort']) uksort($data['list'], 'strcasecmp');
		$data['list'] = array_values($data['list']); 
		
		if(!empty($options['getArray'])) return $data;

		if($config->ajax) header("Content-Type: application/json");
		
		return json_encode($data);
	}

	/**
	 * Set the file to use for the output view, if different from default.
	 * 
	 * - The default view file for the execute() method would be: ./views/execute.php
	 * - The default view file for an executeFooBar() method would be: ./views/execute-foo-bar.php
	 * - To specify your own view file independently of these defaults, use this method. 
	 * 
	 * #pw-group-views
	 * 
	 * @param string $file File must be relative to the module's home directory.
	 * @return $this
	 * @throws WireException if file doesn't exist
	 * 
	 */
	public function setViewFile($file) {
		if(strpos($file, '..') !== false) throw new WireException("Invalid view file (relative paths not allowed)"); 
		$config = $this->wire()->config;
		if(strpos($file, $config->paths->root) === 0 && is_file($file)) {
			// full path filename already specified, nothing to auto-determine
		} else {
			$path = $config->paths($this->className());
			if($path && strpos($file, $path) !== 0) $file = $path . ltrim($file, '/\\');
			if(!is_file($file)) throw new WireException("View file '$file' does not exist");
		}
		$this->_viewFile = $file;
		return $this;	
	}

	/**
	 * If a view file has been set, this returns the full path to it.
	 * 
	 * #pw-group-views
	 * 
	 * @return string Blank if no view file set, full path and file if set.
	 * 
	 */
	public function getViewFile() {
		return $this->_viewFile;
	}

	/**
	 * Set a variable that will be passed to the output view.
	 * 
	 * You can also do this by having your execute() method(s) return an associative array of 
	 * variables to send to the view file.
	 * 
	 * #pw-group-views
	 * 
	 * @param string|array $key Property to set, or array of `[property => value]` to set (leaving 2nd argument as null)
	 * @param mixed|null $value Value to set
	 * @return $this
	 * @throws WireException if given an invalid type for $key
	 * 
	 */
	public function setViewVars($key, $value = null) {
		if(is_array($key)) {
			$this->_viewVars = array_merge($this->_viewVars, $key);
		} else if(is_string($key)) {
			$this->_viewVars[$key] = $value;
		} else {
			throw new WireException("Invalid setViewVars('key')");
		}
		return $this;
	}

	/**
	 * Get all variables set for the output view
	 * 
	 * #pw-group-views
	 * 
	 * @return array associative
	 * 
	 */
	public function getViewVars() {
		return $this->_viewVars; 
	}

	/**
	 * Return the Page that this process lives on 
	 * 
	 * @return Page|NullPage
	 * 
	 */
	public function getProcessPage() {
		$page = $this->wire()->page; 
		if($page->process === $this) return $page;
		$moduleID = $this->wire()->modules->getModuleID($this);
		if(!$moduleID) return new NullPage();
		$page = $this->wire()->pages->get("process=$moduleID, include=all"); 
		return $page;
	}

	/**
	 * URL to redirect to after non-authenticated user is logged-in, or false if module does not support
	 * 
	 * When supported, module should gather any input GET vars and URL segments that it recognizes,
	 * sanitize them, and return a URL for that request. ProcessLogin will redirect to the returned URL
	 * after user has successfully authenticated. 
	 * 
	 * If module does not support this, or only needs to support an integer 'id' GET var, then this
	 * method can return false. 
	 * 
	 * @param Page $page Requested page
	 * @return bool|string
	 * @sine 3.0.167
	 * 
	 */
	public static function getAfterLoginUrl(Page $page) {
		return false;
	}
}
