<?php namespace ProcessWire;

/**
 * InputfieldTinyMCE
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * TinyMCE 6.x, Copyright (c) 2023 Ephox Corporation DBA Tiny Technologies, Inc.
 * https://www.tiny.cloud/docs/tinymce/6/
 *
 * TinyMCE settings (these are also Field settings)
 * ------------------------------------------------
 * @property string $plugins Space-separated string of plugins to enable
 * @property string $toolbar Space-separated string of tools to show in toolbar
 * @property string $contextmenu Space-separated string of tools to show in context menu
 * @property string $removed_menuitems Space-separated string of tools to remove from menubar
 * @property string $invalid_styles Space-separated string of invalid inline styles
 * @property string $menubar Space-separated list of top-level menubar items
 * @property int $height Height of editor in pixels
 *
 * Field/Inputfield settings
 * -------------------------
 * @property int $inlineMode Use inline mode? 0=Regular editor, 1=Inline editor, 2=Fixed height inline editor
 * @property int $lazyMode Use lazy-loading mode? 0=Off, 1=Lazy, 2=Extra lazy
 * @property array $toggles Markup toggles, see self::toggle* constants
 * @property array $features General features: toolbar, menubar, statusbar, stickybars, spellcheck, purifier, imgUpload, imgResize, pasteFilter
 * @property array $headlines Allowed headline types
 * @property string $settingsFile Location of optional custom-settings.json settings file (URL relative to PW root URL)
 * @property string $settingsField Alternate field to inherit settings from rather than configure settings with this instance.
 * @property string $settingsJSON JSON with custom settings that override the defaults
 * @property string $styleFormatsCSS Style formats as CSS to parse and apply to style_formats and content_style
 * @property array $extPlugins Additional plugins to enable for this field (URL paths from customPluginOptions) 
 * 
 * Module settings
 * ---------------
 * @property string $content_css Basename of content CSS file to use or "custom" to use custom URL (default='wire')
 * @property string $content_css_url Applies only if $content_css has value "custom"
 * @property string $skin
 * @property string $skin_url
 * @property string $extPluginOpts Newline separated URL paths (relative to PW root) of extra plugin .js files
 * @property string $defaultsFile Location of optional defaults.json file that merges with defaults.json (URL relative to PW root URL)
 * @property string $defaultsJSON JSON that merges with the defaults.json for all instances
 * @property array $optionals Names of optional settings that can be configured per-field
 * @property bool|int $debugMode Makes InputfieldTinyMCE.js use verbose console.log() messages
 * @property string $extraCSS Extra CSS for editor, applies to all editors (appended to TinyMCE content_style setting)
 * @property string $pasteFilter Rule string of elements and attributes allowed during filtered paste
 * @property array $imageFields Names of fields allowed for drag-drop in images
 * There are also `$lang_name=packname` settings in multi-lang sites where "name" is lang name and "packname" is lang pack name
 * 
 * Runtime settings
 * ----------------
 * @property string $configName
 * @property-read bool $readonly Automatically set during renderValue mode
 * @property-read bool $initialized
 * @property array $external_plugins URLs of external plugins, this is also a TinyMCE setting
 * @property-read InputfieldTinyMCESettings $settings
 * @property-read InputfieldTinyMCEConfigs $configs
 * @property-read InputfieldTinyMCETools $tools
 * @property-read InputfieldTinyMCEFormats $formats
 * @property-read null|bool $renderValueMode
 *
 * @method void getModuleConfigInputfields(InputfieldWrapper $inputfields) 
 * 
 */
class InputfieldTinyMCE extends InputfieldTextarea implements ConfigurableModule {
	
	/**
	 * Get module info
	 *
	 * @return array
	 *
	 */
	public static function getModuleInfo() {
		return array(
			'title' => 'TinyMCE',
			'summary' => 'TinyMCE rich text editor version ' . self::mceVersion . '.',
			'version' => 618,
			'icon' => 'keyboard-o',
			'requires' => 'ProcessWire>=3.0.200, MarkupHTMLPurifier',
		);
	}

	/**
	 * TinyMCE version
	 * 
	 */
	const mceVersion = '6.8.2';
	
	const toggleCleanDiv = 2; // remove <div>s
	const toggleCleanP = 4; // remove empty <p> tags	
	const toggleCleanNbsp = 8; // remove &nbsp; entities
	const toggleRemoveStyles = 16; // remove all style attributes
	
	/**
	 * Default configuration for filtered paste
	 *
	 */
	const defaultPasteFilter =
		'p,strong,em,hr,br,u,s,h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,cite,' .
		'a[href|id],a[target=_blank],' . // a[rel=nofollow|noopener|noreferrer],' . 
		'img[src|alt],img[class=align_left|align_right|align_center],' .
		'table[border],thead,tbody,tfoot,tr[rowspan],td[colspan],th[colspan],colgroup,col,' .
		'sup,sub,figure,figcaption,code,pre,b=strong,i=em';

	/**
	 * Have editor scripts loaded in this request?
	 * 
	 * @var bool 
	 * 
	 */
	static protected $loaded = false;

	/**
	 * @var MarkupHTMLPurifier|null 
	 * 
	 */
	static protected $purifier = null;
	
	/**
	 * Name of current JS config key
	 *
	 */
	protected $configName = 'default';

	/**
	 * Instances of InputfieldTinyMCE ConfigHelper, Tools, Settings
	 * 
	 * @var array
	 * 
	 */
	protected $helpers = array();

	/**
	 * Setting names for field, module, tinymce and more
	 * 
	 * field: setting names populated by init(). 
	 * module: setting names populated by __construct(). 
	 * default: setting names that had the value 'default' set prior to init(). 
	 * tinymce: setting names are those native to TinyMCE.
	 * optionals: settings that can be configured with module OR field. 
	 * 
	 * @var array 
	 * 
	 */
	protected $settingNames = array(
		'field' => array(), 
		'module' => array(),
		'default' => array(),
		'tinymce' => array(
			'skin',
			'height',
			'plugins',
			'toolbar',
			'menubar',
			'statusbar',
			'contextmenu',
			'removed_menuitems',
			'external_plugins',
			'invalid_styles',
			'readonly',
			'content_css',
			'content_css_url', // used when content_css=="custom", not part of tinyMCE
			'external_plugins',
			'skin_url',
		),
		'optionals' => array(
			'contextmenu' => 'contextmenu',
			'menubar' => 'menubar',
			'removed_menuitems' => 'removed_menuitems',
			'invalid_styles' => 'invalid_styles',
			'styleFormatsCSS' => 'styleFormatsCSS',
			'settingsJSON' => 'settingsJSON',
			'headlines' => 'headlines',
			'imageFields' => 'imageFields',
		),
	);

	/**
	 * Available options for 'features' setting
	 * 
	 * @var string[] 
	 * 
	 */
	protected $featureNames = array(
		'toolbar',
		'menubar',
		'statusbar',
		'stickybars',
		'spellcheck',
		'purifier',
		'document',
		'imgUpload',
		'imgResize',
		'pasteFilter',
	);

	/**
	 * False when we should inherit settings from another field
	 * 
	 * @var bool 
	 * 
	 */
	protected $configurable = true;

	/**
	 * Is field initialized? (i.e. init method already called)
	 * 
	 * @var bool 
	 * 
	 */
	protected $initialized = false;

	/**
	 * Construct
	 */
	public function __construct() {
		// module settings
		$data = array(
			'skin' => 'oxide',
			'content_css' => 'wire',
			'content_css_url' => '',
			'defaultsFile' => '',
			'defaultsJSON' => '',
			'extPluginOptions' => '',
			'styleFormatsCSS' => '', // optionals
			'extraCSS' => '', 
			'pasteFilter' => 'default', 
			'optionals' => array('settingsJSON'),
			'debugMode' => false, 
		);
	
		foreach(array_keys($data) as $key) {
			$this->settingNames['module'][$key] = $key;
		}
			
		// optionals 
		$data['headlines'] = array('h1','h2','h3','h4','h5','h5','h6');
		$data['menubar'] = 'default';
		$data['contextmenu'] = 'default';
		$data['removed_menuitems'] = 'default';
		$data['invalid_styles'] = 'default';
		$data['imageFields'] = array();
		
		$this->data($data);
		parent::__construct();
	}
	
	/**
	 * Init Inputfield
	 * 
	 * Module settings have already been populated at this point.
	 *
	 */
	public function init() {
		parent::init();
		$this->initialized = true;
	
		$this->attr('rows', 15);
		$optionals = $this->optionals;

		// set module settings that had value 'default' which requires values from getDefaults()
		// that we do not want called until the init() state reached (for defaultsJSON)
		foreach($this->settingNames['default'] as $key) {
			$this->set($key, 'default');
		}
		
		$this->settingNames['default'] = array(); // no longer needed

		// field settings
		$data = array(
			'contentType' => FieldtypeTextarea::contentTypeHTML,
			'inlineMode' => 0,
			'lazyMode' => 1, // 0=off, 1=load when visible, 2=load on click
			'features' => array(
				'toolbar',
				'menubar',
				'statusbar',
				'stickybars',
				'purifier',
				'imgUpload',
				'imgResize',
				'pasteFilter',
			),
			'settingsFile' => '', 
			'settingsField' => '', 
			'settingsJSON' => '', 
			'styleFormatsCSS' => '', 
			'extPlugins' => array(),
			'toggles' => array(
				// self::toggleCleanDiv,
				// self::toggleCleanNbsp,
				// self::toggleCleanP, 
			),
		);
		
		if(!in_array('styleFormatsCSS', $optionals)) unset($data['styleFormatsCSS']);
		if(!in_array('settingsJSON', $optionals)) unset($data['settingsJSON'], $data['settingsFile']);

		$this->data($data);
		$this->settingNames['field'] = array_keys($data);
		$this->settingNames['field'][] = 'headlines'; // optionals
	
		// tinymce settings (from field or module)
		$defaults = $this->settings->getDefaults();
		$settings = array();
		
		foreach($this->settingNames['tinymce'] as $key) {
			// skip over module-wide settings that match TinyMCE setting names
			if($key === 'skin' || $key === 'skin_url') continue;
			if($key === 'content_css' || $key === 'content_css_url') continue;
			if(isset($this->settingNames['optionals'][$key]) && !in_array($key, $optionals)) {
				// setting only configurable at module level
				$this->settingNames['module'][] = $key;
				continue;
			}
			$settings[$key] = $defaults[$key]; 
		}
		
		$this->data($settings);
	}

	/**
	 * Use the named feature?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	public function useFeature($name) {
		if($name === 'inline') return $this->inlineMode > 0;
		return in_array($name, $this->features);
	}
	
	/**
	 * Return path or URL to TinyMCE files
	 * 
	 * @param bool $getUrl
	 * @return string
	 * 
	 */
	public function mcePath($getUrl = false) {
		$config = $this->wire()->config;
		$path = ($getUrl ? $config->urls($this) : __DIR__ . '/');
		return $path . 'tinymce-' . self::mceVersion . '/';
	}

	/**
	 * Set configuration name used to store settings in ProcessWire.config JS
	 * 
	 * i.e. ProcessWire.config.InputfieldTinyMCE.settings.[configName].[settingName]
	 * 
	 * @param string $configName
	 * @return $this
	 * 
	 */
	public function setConfigName($configName) {
		$this->configName = $configName;
		return $this;
	}

	/**
	 * Get configuration name used to store settings in ProcessWire.config JS
	 * 
	 * i.e. ProcessWire.config.InputfieldTinyMCE.settings.[configName].[settingName]
	 * 
	 * @return string
	 * 
	 */
	public function getConfigName() {
		return $this->configName;
	}

	/**
	 * Get or set configurable state
	 * 
	 * - True if Inputfield is configurable (default state). 
	 * - False if it is required that another field be used ($settingsField) to pull settings from. 
	 * - Note this is completely unrelated to the $configName property. 
	 * 
	 * @param bool $set
	 * @return bool
	 * 
	 */
	public function configurable($set = null) {
		if(is_bool($set)) $this->configurable = $set;
		return $this->configurable;
	}

	/**
	 * Get
	 * 
	 * @param $key
	 * @return array|mixed|string|null
	 * 
	 */
	public function get($key) {
		switch($key) {
			case 'tools': 
			case 'settings':
			case 'configs':
			case 'formats': return $this->helper($key);
			case 'initialized': return $this->initialized;
			case 'configName': return $this->configName;
		}
		return parent::get($key);
	}

	/**
	 * Set
	 * 
	 * @param $key
	 * @param $value
	 * @return self
	 * 
	 */
	public function set($key, $value) {
	
		if($this->initialized) {
			if(isset($this->settingNames['optionals'][$key])) {
				// do not set optionals property not allowed for field configuration
				// if not specifically selected in module settings
				if(!in_array($key, $this->optionals)) return $this;
			}
		} else {
			// when not yet initialized, avoid processing any settings with value 'default'
			// since this will prematurely load the getDefaults() before we are ready
			if($value === 'default') {
				$this->settingNames['default'][$key] = $key;
				return $this;
			}
		}
		
		if($key === 'toolbar' && is_string($value)) {
			if(strpos($value, ',') !== false) {
				// $value = $this->configs()->ckeToMceToolbar($value); // convert CKE toolbar
				return $this; // ignore CKE toolbar (which has commas in it)
			}
			if($value === 'default') {
				$value = $this->settings->getDefaults($key);
			} else {
				$value = $this->tools->sanitizeNames($value);
			}
			
		} else if($key === 'invalid_styles') {
			if($value === 'default') $value = $this->settings->getDefaults($key);
			
		} else if($key === 'plugins' || $key === 'contextmenu' || $key === 'removed_menuitems' || $key === 'menubar') {
			if($value === 'default') {
				$value = $this->settings->getDefaults($key);
			} else {
				$value = $this->tools->sanitizeNames($value);
			}
			
		} else if($key === 'configName') {
			return $this->setConfigName($value);
		}
		
		return parent::set($key, $value);
	}

	/**
	 * Get styles to add in <head>
	 * 
	 * @return string
	 * 
	 */
	public function getExtraStyles() {
		$a = array();
		$skin = $this->skin;
		
		if(strpos($skin, 'dark') !== false && strpos($this->content_css, 'dark') === false) {
			// ensure some menubar/toolbar labels are not black-on-black in dark skin + light content_css
			// this was necessary as of TinyMCE 6.2.0
			$a[] = "body .tox-collection__item-label > *:not(code):not(pre) { color: #eee !important; }";
		}

		if($skin && $skin != 'custom') {
			// make dialogs use PW native colors for buttons (without having to make a custom skin for it)
			$buttonSelector = ".tox-dialog .tox-button:not(.tox-button--secondary):not(.tox-button--icon)";
			$a[] = "$buttonSelector { background-color: #3eb998; border-color: #3eb998; }";
			$a[] = "$buttonSelector:hover { background-color: #e83561; border-color: #e83561; }";
		}
		
		return implode(' ', $a);
	}
	
	/**
	 * Render ready that only needs one call for entire request
	 * 
	 */
	protected function renderReadyOnce() {
		
		$modules = $this->wire()->modules;
		$adminTheme = $this->wire()->adminTheme;
		
		$class = $this->className();
		$config = $this->wire()->config;
		$mceUrl = $this->mcePath(true);
		
		$config->scripts->add($mceUrl . 'tinymce.min.js');
		
		$jQueryUI = $modules->get('JqueryUI'); /** @var JqueryUI $jQueryUI */
		$jQueryUI->use('modal');
	
		$css = $this->getExtraStyles();
		if($css && $adminTheme) {
			// note: using a body class (rather than <style>) interferes with TinyMCE inline mode
			// making it leave toolbar/menubar active even when moving out of the field
			$adminTheme->addExtraMarkup('head', "<style>$css</style>"); 
		}
		
		$js = array(
			'settings' => array(
				'default' => $this->settings->prepareSettingsForOutput($this->settings->getDefaults())
			),
			'labels' => array(
				// translatable labels for pwimage and pwlink plugins
				'selectImage' => $this->_('Select image'),
				'editImage' => $this->_('Edit image'),
				'captionText' => $this->_('Your caption text here'),
				'savingImage' => $this->_('Saving image'),
				'cancel' => $this->_('Cancel'),
				'insertImage' => $this->_('Insert image'),
				'selectAnotherImage' => $this->_('Select another'),
				'insertLink' => $this->_('Insert link'),
				'editLink' => $this->_('Edit link'),
			),
			'pwlink' => array(
				// settings specific to pwlink plugin
				'classOptions' => $this->tools->linkConfig('classOptions')
			),
			'pasteFilter' => $this->tools->getPasteFiltersForJS(),
			'debug' => (bool) $this->debugMode,
		);
	
		$config->js($class, $js);
	}

	/**
	 * Render ready
	 *
	 * @param Inputfield|null $parent
	 * @param bool $renderValueMode
	 * @return bool
	 *
	 */
	public function renderReady(?Inputfield $parent = null, $renderValueMode = false) {
		
		if(!self::$loaded) {
			$this->renderReadyOnce();
			self::$loaded = true;
		}

		$this->renderValueMode = $renderValueMode;

		$settingsField = $this->settingsField;
		
		if($settingsField) {
			$this->configName = (string) $settingsField;
			$settingsField = $this->settings->applySettingsField($settingsField);
		}

		$replaceTools = array();
		$upload = $this->useFeature('imgUpload');
		$imageField = $upload ? $this->tools->getImageField() : null;
		$field = $settingsField instanceof Field ? $settingsField : $this->hasField;
		$addSettings = array();
		
		if($this->inlineMode > 0 || $this->lazyMode > 1) {
			$cssFile = $this->settings->getContentCssUrl();
			$this->wire()->config->styles->add($cssFile);
		}

		if($imageField) {
			// custom attributes for images
			$this->addClass('InputfieldHasUpload', 'wrapClass');
			$this->wrapAttr('data-upload-page', $this->hasPage->id);
			$this->wrapAttr('data-upload-field', $imageField->name);
			
		} else {
			// disable drag-drop "data:base64..." images
			$addSettings['paste_data_images'] = false;
			if(!$this->hasPage) {
				// pwimage plugin requires a page editor
				$page = $this->wire()->page;
				$replaceTools['pwimage'] = '';
				if($page->template->name !== 'admin' && !$page->get('_PageFrontEdit')) {
					// pwlink requires admin
					$replaceTools['pwlink'] = 'link';
				}
			}
		}
	
		if(count($replaceTools)) {
			foreach($replaceTools as $find => $replace) {
				$this->plugins = str_replace($find, $replace, $this->plugins);
				$this->toolbar = str_replace($find, $replace, $this->toolbar);
				$this->contextmenu = str_replace($find, $replace, $this->contextmenu);
				$a = $this->external_plugins;
				if(isset($a[$find])) {
					unset($a[$find]);
					$this->external_plugins = $a;
				}
			}
		}
	
		if($field && $field->type instanceof FieldtypeTextarea) {
			if(!$this->configName || $this->configName === 'default') {
				$this->configName = $field->name;
			}
		}
	
		$this->settings->applyRenderReadySettings($addSettings);

		return parent::renderReady($parent, $renderValueMode);
	}
	
	/**
	 * Render Inputfield
	 *
	 * @return string
	 *
	 */
	public function ___render() {
		
		if($this->inlineMode && $this->tools->purifier()) {
			// Inline editor
			$out = $this->renderInline();
		} else {
			// Normal editor
			$out = $this->renderNormal();
		}
		
		return $out;
	}

	/**
	 * Render normal/classic editor
	 * 
	 * @return string
	 * 
	 */
	protected function renderNormal() {
		$this->addClass('InputfieldTinyMCEEditor InputfieldTinyMCENormal');
		$out = parent::___render();
		if($this->lazyMode > 1) {
			$height = ((int) $this->height) . 'px';
			$out = 
				"<div class='InputfieldTinyMCEPlaceholder' style='height:$height'>" . 
					"<div class='mce-content-body'></div>" . 
				"</div>$out";
		} else {
			$out .= $this->renderInitScript();
		}
		return $out;
	}

	/**
	 * Render inline editor
	 * 
	 * @return string
	 * 
	 */
	protected function renderInline() {
		$attrs = $this->getAttributes();
		$inlineFixed = (int) $this->inlineMode > 1; 
		$value = $this->tools->purifyValue($attrs['value']);
		unset($attrs['value'], $attrs['type'], $attrs['rows']);
		$attrs['class'] = 'InputfieldTinyMCEEditor InputfieldTinyMCEInline mce-content-body';
		$attrs['tabindex'] = '0';
		if($inlineFixed && $this->height) {
			$height = ((int) $this->height) . 'px';
			$style = isset($attrs['style']) ? $attrs['style'] : '';
			$attrs['style'] = "overflow:auto;height:$height;$style";
		}
		$attrStr = $this->getAttributesString($attrs);
		$out = "<div $attrStr>$value</div>";
		// optionally turn off lazy-loading mode for inline
		// if(!$this->lazyMode) $out .= $this->renderInitScript();
		return $out;
	}

	/**
	 * Render script to init editor 
	 * 
	 * @return string
	 * 
	 */
	protected function renderInitScript() {
		$id = $this->attr('id');
		$script = 'script';
		$js = "InputfieldTinyMCE.init('#$id', 'module.render'); ";
		return "<$script>$js</$script>";
	}

	/**
	 * Render non-editable value
	 * 
	 * @return string
	 * 
	 */
	public function ___renderValue() {
		if(wireInstanceOf($this->wire->process, 'ProcessPageEdit')) {
			$this->renderValueMode = true; // should be set already, but just in case
			$out = $this->render();
		} else {
			$out =
				"<div class='InputfieldTextareaContentTypeHTML mce-content-body'>" .
					$this->wire()->sanitizer->purify($this->val()) .
				"</div>";
		}
		return $out;
	}

	/**
	 * Process input
	 * 
	 * @param WireInputData $input
	 * @return $this
	 * 
	 */
	public function ___processInput(WireInputData $input) {

		$settingsField = $this->settingsField;
		if($settingsField) $this->settings->applySettingsField($settingsField);
	
		$id = $this->attr('id');
		$name = $this->attr('name');
		$useName = $name;
		$rename = $this->inlineMode && $id && $id !== $name;
		
		if($rename) {
			// in inlineMode TinyMCE uses id attribute for input name
			// $useName = "Inputfield_$name";
			$useName = $id;
			$this->attr('name', $useName);
		}
		
		$value = $input->$useName;
		$valuePrevious = $this->val();
		
		if($value !== null && $value !== $valuePrevious && !$this->readonly) {
			parent::___processInput($input);
			$value = $this->tools->purifyValue($value);
			if($value !== $valuePrevious) {
				$this->val($value);
				$this->trackChange('value');
			}
		}
		
		if($rename) {
			$this->attr('name', $name);
		}
		
		return $this;
	}

	/**
	 * Get all configurable setting names
	 * 
	 * @param array|string $types Types to get, one or more of: 'tinymce', 'field', 'module', 'optionals'
	 * @return string[]
	 * @throws WireException if given unknown setting type
	 * 
	 */
	public function getSettingNames($types) {
		if(!is_array($types)) $types = explode(' ', $types);
		$a = array();
		if(empty($types)) $types = array_keys($this->settingNames);
		foreach($types as $type) {
			if(empty($type)) continue;
			if(!isset($this->settingNames[$type])) {
				throw new WireException("Unknown setting type: $type"); 
			}
			$a = array_merge($a, array_values($this->settingNames[$type])); 
		}
		return $a;
	}
	
	/**
	 * Add an external plugin .js file
	 *
	 * @param string $file File must be .js file relative to PW installation root, i.e. /site/templates/mce/myplugin.js
	 * @throws WireException
	 *
	 */
	public function addPlugin($file) {
		$this->configs->addPlugin($file);
	}

	/**
	 * Remove an external plugin .js file
	 *
	 * @param string $file File must be .js file relative to PW installation root, i.e. /site/templates/mce/myplugin.js
	 * @return bool
	 *
	 */
	public function removePlugin($file) {
		return $this->configs->removePlugin($file);
	}

	/**
	 * Get directionality, either 'ltr' or 'rtl'
	 * 
	 * @return string
	 * 
	 */
	public function getDirectionality() {
		return $this->_x('ltr', 'language-direction'); // change to 'rtl' for right-to-left languages
	}
	
	/**
	 * Get helper
	 * 
	 * @param string $name
	 * @return InputfieldTinyMCEClass
	 * 
	 */
	public function helper($name) {
		if(empty($this->helpers[$name])) {
			$class = $this->className() . ucfirst($name);
			require_once(__DIR__ . "/InputfieldTinyMCEClass.php"); 
			require_once(__DIR__ . "/$class.php");
			$class = "\\ProcessWire\\$class";
			$this->helpers[$name] = new $class($this);
		}
		return $this->helpers[$name];
	}
	
	/**
	 * Get Inputfield configuration settings
	 * 
	 * @return InputfieldWrapper
	 * 
	 */
	public function ___getConfigInputfields() {
		$inputfields = parent::___getConfigInputfields();
		$this->configs->getConfigInputfields($inputfields);
		return $inputfields;
	}

	/**
	 * Module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function ___getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		$this->configs->getModuleConfigInputfields($inputfields);
	}

}
