<?php namespace ProcessWire;

/**
 * ProcessWire Admin Theme Module
 *
 * An abstract module intended as a base for admin themes. 
 *
 * See the Module interface (Module.php) for details about each method. 
 *
 * This file is licensed under the MIT license. 
 * https://processwire.com/about/license/mit/
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property int|string $version Current admin theme version
 * 
 * @method void install()
 * @method void uninstall()
 * @method array getExtraMarkup()
 *
 */

abstract class AdminTheme extends WireData implements Module {

	/**
	 * Per the Module interface, return an array of information about the Module
	 *
	 */
	public static function getModuleInfo() {
		return array(
			'title'    => '',        // printable name/title of module
			'version'  => 1,    // version number of module
			'summary'  => '',    // 1 sentence summary of module
			'href'     => '',        // URL to more information (optional)

			// all admin themes should have this as their autoload selector:
			'autoload' => 'template=admin',
			'singular' => true
		);
	}

	/**
	 * Current admin theme version (cached from module info)
	 *
	 * @var int
	 *
	 */
	protected $version = 0;

	/**
	 * Keeps track of quantity of admin themes installed so that we know when to add profile field
	 *
	 */
	protected static $numAdminThemes = 0;

	/**
	 * Additional classes for body tag
	 *
	 * @var array
	 *
	 */
	protected $bodyClasses = array();

	/**
	 * Extra markup regions
	 * 
	 * @var array
	 * 
	 */
	protected $extraMarkup = array(
		'head' => '',
		'notices' => '',
		'body' => '',
		'masthead' => '',
		'content' => '',
		'footer' => '',
		'sidebar' => '', // sidebar not used in all admin themes
	);
	
	/**
	 * URLs to place in link prerender tags
	 * 
	 * @var array
	 * 
	 */
	protected $preRenderURLs = array();

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		// placeholder
	}

	/**
	 * Initialize the admin theme systme and determine which admin theme should be used
	 *
	 * All admin themes must call this init() method to register themselves. 
	 *
	 */
	public function init() { 
		self::$numAdminThemes++;
		
		$info = $this->wire('modules')->getModuleInfo($this);
		$this->version = $info['version'];

		// if module has been called when it shouldn't (per the 'autoload' conditional)
		// then module author probably forgot the right 'autoload' string, so this 
		// serves as secondary stopgap to keep this module from loading when it shouldn't.
		if(!$this->wire('page') || $this->wire('page')->template != 'admin') return;
		
		if(self::$numAdminThemes > 1 && !$this->wire('fields')->get('admin_theme')) $this->install();

		// if admin theme has already been set, then no need to continue
		if($this->wire('adminTheme')) return; 

		$isCurrent = false;
		$adminTheme = $this->wire('user')->admin_theme; 

		if($adminTheme) {
			// there is user specified admin theme
			// check if this is the one that should be used
			if($adminTheme == $this->className()) $isCurrent = true; 
			
		} else if($this->wire('config')->defaultAdminTheme == $this->className()) {
			// there is no user specified admin theme, so use this one
			$isCurrent = true; 
		}

		// set as an API variable and populate configuration variables
		if($isCurrent) {
			$this->wire('adminTheme', $this); 
			$this->config->paths->set('adminTemplates', $this->config->paths->get($this->className())); 
			$this->config->urls->set('adminTemplates', $this->config->urls->get($this->className())); 
		}

		// adjust $config adminThumbOptions[scale] for auto detect when requested
		$o = $this->wire('config')->adminThumbOptions; 
		if($o && isset($o['scale']) && $o['scale'] === 1) {
			$o['scale'] = $this->wire('session')->hidpi ? 0.5 : 1.0; 
			$this->wire('config')->adminThumbOptions = $o;
		}

		$this->config->js('modals', $this->config->modals); 
		
		if($this->wire('session')->hidpi) $this->addBodyClass('hidpi-device');
		if($this->wire('session')->touch) $this->addBodyClass('touch-device'); 
		$this->addBodyClass($this->className());
	}
	
	public function get($key) {
		if($key == 'version') return $this->version;
		return parent::get($key); 
	}

	/**
	 * Returns true if this admin theme is the one that will be used for this request
	 *
	 */
	public function isCurrent() {
		return $this->wire('adminTheme') === $this; 
	}

	/**
	 * Enables hooks to append extra markup to various sections of the admin page
	 * 
	 * @return array Associative array containing the following properties, any of 
	 * which may be populated or empty: 
	 * 	- head
	 * 	- body
	 * 	- masthead
	 * 	- content
	 * 	- footer
	 * 	- sidebar
	 * 
	 */
	public function ___getExtraMarkup() {
		$parts = $this->extraMarkup;
		$isLoggedin = $this->wire('user')->isLoggedin();
		if($isLoggedin && $this->wire('modules')->isInstalled('InputfieldCKEditor') 
			&& $this->wire('process') instanceof WirePageEditor) {
			// necessary for when CKEditor is loaded via ajax
			$parts['head'] .= "<script>" . 
				"window.CKEDITOR_BASEPATH='" . $this->wire('config')->urls->InputfieldCKEditor . 
				'ckeditor-' . InputfieldCKEditor::CKEDITOR_VERSION . "/';</script>";
		}
		if($isLoggedin && $this->wire('config')->advanced) {
			$parts['footer'] = "<p class='AdvancedMode'><i class='fa fa-flask'></i> " . $this->_('Advanced Mode') . "</p>";
		}
		foreach($this->preRenderURLs as $url) {
			$parts['head'] .= "<link rel='prerender' href='$url'>";
		}
		return $parts; 
	}

	/**
	 * Add extra markup to a region in the admin theme
	 * 
	 * @param string $name
	 * @param string $value
	 * 
	 */
	public function addExtraMarkup($name, $value) {
		if(!empty($this->extraMarkup[$name])) {
			$this->extraMarkup[$name] .= "\n$value";
		} else {
			$this->extraMarkup[$name] = $value;
		}
	}

	/**
	 * Add a <body> class to the admin theme
	 * 
	 * @param string $className
	 * 
	 */
	public function addBodyClass($className) {
		$this->bodyClasses[$className] = $className; 
	}

	/**
	 * Get the body[class] attribute string
	 * 
	 * @return string
	 * 
	 */
	public function getBodyClass() {
		return trim(implode(' ', $this->bodyClasses)); 
	}

	/**
	 * Install the admin theme
	 *
	 * Other admin themes using an install() method must call this install before their own.
	 *
	 */
	public function ___install() { 

		// if we are the only admin theme installed, no need to add an admin_theme field
		if(self::$numAdminThemes == 0) return;

		// install a field for selecting the admin theme from the user's profile
		$field = $this->wire('fields')->get('admin_theme'); 

		$toUseNote = $this->_('To use this theme, select it from your user profile.'); 

		// we already have this field installed, no need to continue
		if($field) {
			$this->message($toUseNote); 
			return;
		}

		// this will be the 2nd admin theme installed, so add a field that lets them select admin theme
		$field = $this->wire(new Field());
		$field->name = 'admin_theme';
		$field->type = $this->wire('modules')->get('FieldtypeModule'); 
		$field->set('moduleTypes', array('AdminTheme')); 
		$field->set('labelField', 'title'); 
		$field->set('inputfieldClass', 'InputfieldRadios'); 
		$field->label = 'Admin Theme';
		$field->flags = Field::flagSystem; 
		$field->save();	

		$fieldgroup = $this->wire('fieldgroups')->get('user'); 
		$fieldgroup->add($field); 
		$fieldgroup->save();

		// make this field one that the user is allowed to configure in their profile
		$data = $this->wire('modules')->getModuleConfigData('ProcessProfile'); 
		$data['profileFields'][] = 'admin_theme';
		$this->wire('modules')->saveModuleConfigData('ProcessProfile', $data); 

		$this->message($this->_('Installed field "admin_theme" and added to user profile settings.')); 
		$this->message($toUseNote); 
	}

	/**
	 * Set a pre-render URL or get currently pre-render URL(s)
	 * 
	 * #pw-internal
	 * 
	 * @param string $url
	 * @return array
	 * 
	 */
	public function preRenderURL($url = '') {
		if(!empty($url)) $this->preRenderURLs[] = $url;
		return $this->preRenderURLs;
	}
	
	public function ___uninstall() { 

		/*
		if(self::$numAdminThemes > 1) return;

		// this is the last installed admin theme
		$field = $this->wire('fields')->get('admin_theme'); 	
		$field->flags = Field::flagSystemOverride; 
		$field->flags = 0; 
		$field->save();

		$fieldgroup = $this->wire('fieldgroups')->get('user'); 
		$fieldgroup->remove($field); 
		$fieldgroup->save();

		$this->wire('fields')->delete($field); 
		$this->message($this->_('Removed field "admin_theme" from system.')); 
		*/
	}
}

