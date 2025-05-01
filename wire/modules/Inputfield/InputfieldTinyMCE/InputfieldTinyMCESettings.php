<?php namespace ProcessWire;

/**
 * InputfieldTinyMCETools
 *
 * Helper for managing TinyMCE settings and defaults
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 * @method array prepareSettingsForOutput(array $settings)
 *
 */
class InputfieldTinyMCESettings extends InputfieldTinyMCEClass {

	/**
	 * Runtime caches shared among all instances
	 * 
	 * @var array 
	 * 
	 */
	static protected $caches = array(
		'defaults' => array(),
		'settings' => array(), 
		'alignClasses' => array(),
		'renderReadyInline' => array(), 
		'langSettings' => array(), 
		'addDefaults' => array(), 
		'originalDefaults' => array(), 
	);

	/**
	 * Get settings from Inputfield vary from the $defaults
	 *
	 * @param array|null $defaults Default settings Default settings or omit to pull automatically
	 * @param string $cacheKey Optionally cache with this key
	 * @return array
	 *
	 */
	public function getSettings($defaults = null, $cacheKey = '') {

		$inputfield = $this->inputfield;
		
		if($cacheKey && isset(self::$caches['settings'][$cacheKey])) return self::$caches['settings'][$cacheKey];
		if($defaults === null) $defaults = $this->getDefaults();

		$settings = array();
		$features = $inputfield->features;
		$formats = $this->formats();
		
		foreach($defaults as $name => $defaultValue) {
			if($name === 'menubar') {
				if(in_array($name, $features)) {
					$value = $inputfield->get('menubar');
					if(empty($value) || $value === 'default') $value = $defaultValue;
				} else {
					$value = false;
				}
			} else if($name === 'statusbar') {
				$value = true;
			} else if($name === 'browser_spellcheck') {
				$value = in_array('spellcheck', $features);
			} else if($name === 'toolbar') {
				$value = in_array($name, $features) ? $inputfield->get($name) : '';
			} else if($name === 'toolbar_sticky') {
				$value = in_array('stickybars', $features);
			} else if($name === 'content_css') {
				$value = $inputfield->get($name);
				if($value === 'custom') {
					$value = $inputfield->get('content_css_url');
					if(empty($value)) continue;
				}
				$value = $this->getContentCssUrl($value);
			} else if($name === 'directionality') {
				$value = $inputfield->getDirectionality();
			} else if($name === 'style_formats') {
				$value = $formats->getStyleFormats($defaults);
			} else if($name === 'block_formats') {
				$value = $formats->getBlockFormats();
			} else if($name === 'invalid_styles') {
				$value = $formats->getInvalidStyles($inputfield->invalid_styles, $defaultValue);
			} else if($name === 'formats') {
				// overlaps with native formats property so use data rather than get
				$value = $inputfield->data('formats');
			} else if($name === 'templates') {
				// overlaps with API variable
				$value = $inputfield->data($name);
			} else {
				$value = $inputfield->get($name);
				if($value === 'default') $value = $defaultValue;
			}
			if($name === 'removed_menuitems' && strpos($value, 'print') === false) {
				// the print option is not useful in inline mode
				if($inputfield->inlineMode) $value = trim("$value print");
			}
			if($value !== null && $value != $defaultValue) {
				$settings[$name] = $value;
			}
		}

		$this->applySkin($settings, $defaults);
		$this->applyPlugins($settings, $defaults);

		if(isset($defaults['style_formats'])) {
			$styleFormatsCSS = $inputfield->get('styleFormatsCSS');
			if($styleFormatsCSS) {
				$formats->applyStyleFormatsCSS($styleFormatsCSS, $settings, $defaults);
			}
		}

		if($cacheKey) self::$caches['settings'][$cacheKey] = $settings;

		return $settings;
	}

	/**
	 * Default settings for ProcessWire.config.InputfieldTinyMCE
	 *
	 * This should have no field-specific settings (no dynamic values)
	 *
	 * @property string $key
	 * @return array
	 *
	 */
	public function getDefaults($key = '') {
		
		if(!empty(self::$caches['defaults'])) {
			if($key) return isset(self::$caches['defaults'][$key]) ? self::$caches['defaults'][$key] : null;
			return self::$caches['defaults'];
		}

		$config = $this->wire()->config;
		$root = $config->urls->root;
		$url = $config->urls($this->inputfield);
		$tools = $this->tools();

		// root relative, i.e. '/site/modules/InputfieldTinyMCE/'
		$url = substr($url, strlen($root)-1);
		$alignClasses = $this->getAlignClasses();
		$mceSettingNames = $this->inputfield->getSettingNames('tinymce');
		$optionalSettingNames = $this->inputfield->getSettingNames('optionals');
		$optionals = $this->inputfield->optionals;

		// selector of elements that can be used with align commands

		$replacements = array(
			'{url}' => $url,
			'{alignleft}' => $alignClasses['left'], 
			'{aligncenter}' => $alignClasses['center'], 
			'{alignright}' => $alignClasses['right'], 
			'{alignfull}' => $alignClasses['full'],
		);
		
		$json = file_get_contents(__DIR__ . '/defaults.json');
		$json = str_replace(array_keys($replacements), array_values($replacements), $json);
		$defaults = $tools->jsonDecode($json, 'defaults.json');

		// defaults JSON file
		$file = $this->inputfield->defaultsFile;
		if($file) {
			$file = $config->paths->root . ltrim($file, '/');
			$data = $tools->jsonDecodeFile($file, 'default settings file for module');
			if(is_array($data) && !empty($data)) $defaults = array_merge($defaults, $data);
		}
	
		// defaults JSON text
		$json = $this->inputfield->defaultsJSON;
		if($json) {
			$data = $tools->jsonDecode($json, 'defaults JSON module setting'); 
			if(is_array($data) && !empty($data)) $defaults = array_merge($defaults, $data);
		}
	
		// extra CSS module setting
		$extraCSS = $this->inputfield->extraCSS;
		if(strlen($extraCSS)) {
			$contentStyle = isset($defaults['content_style']) ? $defaults['content_style'] : '';
			$contentStyle .= "\n$extraCSS";
			$defaults['content_style'] = $contentStyle;
		}

		// optionals
		foreach($optionalSettingNames as $name) {
			if(in_array($name, $optionals)) continue; // configured with field (not module)
			if(!in_array($name, $mceSettingNames)) continue; // not a direct TinyMCE setting
			$value = $this->inputfield->get($name);
			if($value === 'default' || $value === null) continue;
			if($name === 'invalid_styles' && is_string($value)) {
				$value = $this->formats()->invalidStylesStrToArray($value);
			}
			if(isset($defaults[$name]) && $defaults[$name] !== $value) {
				self::$caches['originalDefaults'][$name] = $defaults[$name];
			}
			$defaults[$name] = $value;
		}	
		
		$languageSettings = $this->getLanguageSettings();
		if(!empty($languageSettings)) $defaults = array_merge($defaults, $languageSettings);
		
		foreach($defaults as $k => $value) {
			if(strpos($k, 'add_') === 0 || strpos($k, 'append_') === 0 || strpos($k, 'replace_') === 0) {
				self::$caches['addDefaults'][$k] = $value;
				unset($defaults[$k]); 
			}
		}
		
		self::$caches['defaults'] = $defaults;
		
		if($key) return isset($defaults[$key]) ? $defaults[$key] : null;
		
		return $defaults;
	}

	/**
	 * Get original defaults from source JSON, prior to being overriden by module default settings
	 * 
	 * @param string $key
	 * @return array|mixed|null
	 * 
	 */
	public function getOriginalDefaults($key = '') {
		$defaults = $this->getDefaults();
		if($key) {
			if(isset(self::$caches['originalDefaults'][$key])) {
				return self::$caches['originalDefaults'][$key];
			} else {
				return isset($defaults[$key]) ? $defaults[$key] : null;
			}
		}
		return array_merge($defaults, self::$caches['originalDefaults']); 
	}

	/**
	 * Get 'add_' or 'replace_' default settings
	 * 
	 * @return array|mixed
	 * 
	 */
	public function getAddDefaults() {
		return self::$caches['addDefaults'];
	}

	/**
	 * Apply plugins settings
	 *
	 * @param array $settings
	 * @param array $defaults
	 *
	 */
	protected function applyPlugins( array &$settings, array $defaults) {
		$extPlugins = $this->inputfield->get('extPlugins');

		if(!empty($extPlugins)) {
			$value = $defaults['external_plugins'];
			foreach($extPlugins as $url) {
				$name = basename($url, '.js');
				$value[$name] = $url;
			}
			$settings['external_plugins'] = $value;
		}

		if(isset($defaults['plugins'])) {
			$plugins = $this->inputfield->get('plugins');
			if(empty($plugins) && !empty($defaults['plugins'])) $plugins = $defaults['plugins'];
			if(!is_array($plugins)) $plugins = explode(' ', $plugins);
			if(!in_array('pwlink', $plugins)) {
				unset($settings['external_plugins']['pwlink']);
				if(isset($settings['menu'])) {
					$settings['menu']['insert']['items'] = str_replace('pwlink', 'link', $settings['menu']['insert']['items']);
				}
			}
			if(!in_array('pwimage', $plugins)) {
				unset($settings['external_plugins']['pwimage']);
				if(isset($settings['menu'])) {
					$settings['menu']['insert']['items'] = str_replace('pwimage', 'image', $settings['menu']['insert']['items']);
				}
			}
			$settings['plugins'] = implode(' ', $plugins);
			if($settings['plugins'] === $defaults['plugins']) unset($settings['plugins']);
		}
	}

	/**
	 * Apply skin or skin_url directly to given settings/defaults
	 * 
	 * @param array $settings
	 * @param array $defaults
	 * 
	 */
	protected function applySkin(&$settings, $defaults) {
		$skin = $this->inputfield->skin;
		if($skin === 'custom') {
			$skinUrl = rtrim($this->inputfield->skin_url, '/');
			if(strlen($skinUrl)) {
				if(strpos($skinUrl, '//') === false) {
					$skinUrl = $this->wire()->config->urls->root . ltrim($skinUrl, '/');
				}
				if(!isset($defaults['skin_url']) || $defaults['skin_url'] != $skinUrl) {
					$settings['skin_url'] = $skinUrl;
				}
				unset($settings['skin']); 
			}
		} else {
			if(isset($defaults['skin']) && $defaults['skin'] != $skin) {
				$settings['skin'] = $skin;
			}
			unset($settings['skin_url']);
		}
	}

	/**
	 * Get image alignment classes
	 * 
	 * @return array
	 * 
	 */
	public function getAlignClasses() {
		if(empty(self::$caches['alignClasses'])) {
			$data = $this->wire()->modules->getModuleConfigData('ProcessPageEditImageSelect');
			self::$caches['alignClasses'] = array(
				'left' => (empty($data['alignLeftClass']) ? 'align_left' : $data['alignLeftClass']),
				'right' => (empty($data['alignRightClass']) ? 'align_right' : $data['alignRightClass']),
				'center' => (empty($data['alignCenterClass']) ? 'align_center' : $data['alignCenterClass']),
				'full' => 'align_full', 
			);
		}
		return self::$caches['alignClasses'];
	}

	/**
	 * Get settings from custom settings file
	 * 
	 * @return array
	 * 
	 */
	protected function getFromSettingsFile() {
		$file = $this->inputfield->get('settingsFile');
		if(empty($file)) return array();
		$file = $this->wire()->config->paths->root . ltrim($file, '/'); 
		return $this->tools()->jsonDecodeFile($file, 'settingsFile');	
	}

	/**
	 * Get settings from custom JSON
	 *
	 * @return array
	 * 
	 */
	protected function getFromSettingsJSON() {
		$json = trim((string) $this->inputfield->get('settingsJSON'));
		if(empty($json)) return array();
		return $this->tools()->jsonDecode($json, 'settingsJSON');
	}

	/**
	 * Get content_css URL
	 * 
	 * @param string $content_css
	 * @return string
	 * 
	 */
	public function getContentCssUrl($content_css = '') {
		
		$config = $this->wire()->config;
		$rootUrl = $config->urls->root;
		$defaultUrl = $config->urls($this->inputfield) . 'content_css/wire.css';
		
		if($this->inputfield->useFeature('document')) {
			$content_css = 'document';
		}

		if(empty($content_css)) {
			if($this->inputfield->useFeature('document')) {
				$content_css = 'document';
			} else {
				$content_css = $this->inputfield->content_css;
			}
		}
		
		if($content_css === 'wire' || empty($content_css)) {
			// default
			$url = $defaultUrl;

		} else if(strpos($content_css, '/') !== false) {
			// custom file
			if(strpos($content_css, $rootUrl) === 0) {
				$url = $content_css;
			} else {
				$url = $rootUrl . ltrim($content_css, '/');
			}
			// $url = $rootUrl . ltrim($content_css, '/');

		} else if($content_css === 'custom') {
			// custom file (alternate/fallback)
			$content_css_url = $this->inputfield->content_css_url;
			if(empty($content_css_url) || strpos($content_css_url, '/') === false) {
				$url = $defaultUrl;
			} else {
				$url = $rootUrl . ltrim($content_css_url, '/');
			}

		} else if($content_css) {
			// defined
			$content_css = basename($content_css, '.css');
			$url = $config->urls($this->inputfield) . "content_css/$content_css.css";
			
		} else {
			$url = $defaultUrl;
		}
	
		if(strpos($url, '.css') === false) {
			$url = rtrim($url, '/') . '/content.css';
		}

		return $url;
	}

	/**
	 * Prepare given settings ready for output
	 *
	 * This converts relative URLs to absolute, etc.
	 *
	 * @param array $settings
	 * @return array
	 *
	 */
	public function ___prepareSettingsForOutput(array $settings) {
		$config = $this->wire()->config;
		$rootUrl = $config->urls->root;
		//$inline = $this->inputfield->inlineMode > 0;

		/*
		if($inline) {
			// content_css not loaded here
			//$settings['content_css'] = '';
		*/
			
		if(isset($settings['content_css'])) {
			// convert content_css setting to URL
			$settings['content_css'] = $this->getContentCssUrl($settings['content_css']);
		}

		if(!empty($settings['external_plugins'])) {
			foreach($settings['external_plugins'] as $name => $url) {
				$settings['external_plugins'][$name] = $rootUrl . ltrim($url, '/');
			}
		}
		if(isset($settings['height'])) {
			$settings['height'] = "$settings[height]px";
		}
	
		if(isset($settings['toolbar']) && is_string($settings['toolbar'])) {
			$splitTools = array('styles', 'blocks'); 
			foreach($splitTools as $name) {
				$settings['toolbar'] = str_replace("$name ", "$name | ", $settings['toolbar']); 
			}
		}
		
		if(empty($settings['invalid_styles'])) {
			// for empty invalid_styles use blank string rather than blank array 
			$settings['invalid_styles'] = '';
		}
		
		if(!empty($settings['content_style'])) {
			// namespace content_style for .mce_content_body
			$contentStyle = $settings['content_style'];
			$contentStyle = str_replace('}', "}\n", $contentStyle);
			$contentStyle = preg_replace('![\s\r\n]+\{!', '{', $contentStyle);
			$lines = explode("\n", $contentStyle);
			foreach($lines as $k => $line) {
				$line = trim($line);
				if(empty($line)) {
					unset($lines[$k]);
				} else if(strpos($line, '.mce-content-body') !== false) {
					continue;
				} else if(strpos($line, '{')) {
					$lines[$k] = ".mce-content-body $line";
				}
			}
			$contentStyle = implode(' ', $lines);
			while(strpos($contentStyle, '  ') !== false) $contentStyle = str_replace('  ', ' ', $contentStyle);
			$contentStyle = str_replace(['{ ', ' }'], ['{', '}'], $contentStyle);
			$contentStyle = str_replace('@', "\\@", $contentStyle);
			$settings['content_style'] = $contentStyle;
		}
	
		/*
		if(isset($settings['plugins']) && is_array($settings['plugins'])) {
			$settings['plugins'] = implode(' ', $settings['plugins']); 
		}
		*/
	
		// ensure blank object properties resolve to {} in JSON rather than []	
		foreach($this->tools()->jsonBlankObjectProperties as $name) {
			if(!isset($settings[$name]) || !empty($settings[$name]) || !is_array($settings[$name])) continue;
			$settings[$name] = (object) $settings[$name];
		}

		return $settings;
	}

	/**
	 * Get language pack code
	 * 
	 * @return string
	 * 
	 */
	public function getLanguagePackCode() {
	
		$default = 'en_US';
		$languages = $this->wire()->languages;
		$sanitizer = $this->wire()->sanitizer;
		$path = __DIR__ . '/langs/';
		
		if(!$languages) return $default;
		
		$language = $this->wire()->user->language;
		
		// attempt to get from module setting
		$value = $this->inputfield->get("lang_$language->name");
		if($value) return $value;
	
		// attempt to get from non-default language name
		if(!$language->isDefault() && is_file("$path$language->name.js")) {
			return $language->name;
		}
	
		// attempt to get from admin theme
		$adminTheme = $this->wire()->adminTheme;
		if($adminTheme) {
			$value = $sanitizer->name($adminTheme->_('en'));
			if($value !== 'en' && is_file("$path$value.js")) return $value;
		}

		$value = $languages->getLocale();
	
		// attempt to get from locale setting
		if($value !== 'C') {
			if(strpos($value, '.')) list($value,) = explode('.', $value, 2);
			if(is_file("$path$value.js")) return $value;
			if(strpos($value, '_')) {
				list($value,) = explode('_', $value, 2);
				if(is_file("$path$value.js")) return $value;
			}
		}
	
		// attempt to get from CKEditor static translation
		$textdomain = '/wire/modules/Inputfield/InputfieldCKEditor/InputfieldCKEditor.module';
		if(is_file($this->wire()->config->paths->root . ltrim($textdomain, '/'))) {
			$value = _x('en', 'language-pack', $textdomain);
			if($value !== 'en') {
				$value = $sanitizer->name($value);
				if($value && is_file("$path$value.js")) return $value;
			}
		}

		return $default;
	}

	/**
	 * Get language pack settings
	 *
	 * @return array
	 * 
	 */
	public function getLanguageSettings() {
		if(!$this->wire()->languages) return array();
		$language = $this->wire()->user->language;
		if(isset(self::$caches['langSettings'][$language->id])) {
			return self::$caches['langSettings'][$language->id];
		}
		$code = $this->getLanguagePackCode();
		if($code === 'en_US') {
			$value = array();
		} else {
			$value = array(
				'language' => $code, 
				'language_url' => $this->wire()->config->urls($this->inputfield) . "langs/$code.js"
			);
		}
		self::$caches['langSettings'][$language->id] = $value;
		return $value;
	}

	/**
	 * Apply 'add_*' settings in $addSettings, plus merge all $addSettings into given $settings 
	 * 
	 * This updates the $settings and $addSettings variables directly
	 * 
	 * @param array $settings
	 * @param array $addSettings
	 * @param array $defaults
	 * 
	 */
	protected function applyAddSettings(array &$settings, array &$addSettings, array $defaults) {
	
		// apply add_style_formats when present
		if(isset($addSettings['add_style_formats'])) {
			$styleFormats = isset($settings['style_formats']) ? $settings['style_formats'] : $defaults['style_formats'];
			$settings['style_formats'] = $this->formats()->mergeStyleFormats($styleFormats, $addSettings['add_style_formats']);
			unset($addSettings['add_style_formats']);
		}
	
		// find other add_* properties, i.e. 'add_formats', 'add_invalid_styles', 'add_plugins'
		// these append rather than replace, i.e. 'add_formats' appends to 'formats'
		// also find any replace_* properties and replace setting values rather than append
		foreach($addSettings as $key => $addValue) {
			if(strpos($key, 'replace_') === 0) {
				list(,$k) = explode('replace_', $key, 2); 
				if(!isset($addSettings[$k]) && $addValue !== null) $addSettings[$k] = $addValue;
				unset($addSettings[$key]); 
				continue;
			}
			if(strpos($key, 'append_') === 0) {
				unset($addSettings[$key]); 
				$key = str_replace('append_', 'add_', $key);
			}
			if(strpos($key, 'add_') !== 0) continue;
			list(,$name) = explode('add_', $key, 2);
			unset($addSettings[$key]); 
			if(isset($settings[$name])) {
				// present in settings
				$value = $settings[$name];
			} else if(isset($defaults[$name])) {
				// present in defaults
				$value = $defaults[$name];
			} else {
				// not present, add it to settings
				$addSettings[$name] = $addValue;
				continue;
			}
			$addSettings[$name] = $this->mergeSetting($value, $addValue);
		}
	
		$settings = array_merge($settings, $addSettings);
	}

	/**
	 * Merge two setting values into one that combines them 
	 * 
	 * @param string|array|mixed $value
	 * @param string|array|mixed $addValue
	 * @return string|array|mixed
	 * 
	 */
	protected function mergeSetting($value, $addValue) {
		if(is_string($value) && is_string($addValue)) {
			$value .= " $addValue";
		} else if(is_array($addValue) && is_array($value)) {
			foreach($addValue as $k => $v) {
				if(is_int($k)) {
					// append
					$value[] = $v;
				} else {
					// append or replace
					$value[$k] = $v;
				}
			}
		} else {
			$value = $addValue;
		}
		return $value;
	}

	/**
	 * Merge all settings in given array and combine those with "add_" prefix
	 * 
	 * @param array $settings1
	 * @param array $settings2 Optionally specify this to merge/combine with those in $settings1
	 * @return array 
	 * 
	 */
	protected function mergeSettings(array $settings1, array $settings2 = array()) {
		$settings = array_merge($settings1, $settings2);
		$addSettings = array();
		foreach($settings1 as $key => $value) {
			if(strpos($key, 'add_') !== 0) continue;
			$addSettings[$key] = $value;
		}
		foreach($settings2 as $key => $value) {
			if(strpos($key, 'add_') !== 0) continue;
			if(isset($addSettings[$key])) {
				$addSettings[$key] = $this->mergeSetting($addSettings[$key], $value);
			} else {
				$addSettings[$key] = $value;
			}
		}
		if(count($addSettings)) $settings = array_merge($settings, $addSettings);
		return $settings;
	}

	/**
	 * Determine which settings go where and apply to Inputfield
	 * 
	 * @param array $addSettings Optionally add this settings on top of those that would otherwise be used
	 * 
	 */
	public function applyRenderReadySettings(array $addSettings = array()) {
	
		$config = $this->wire()->config;
		$inputfield = $this->inputfield;
		$configName = $inputfield->getConfigName();
		
		// default settings
		$defaults = $this->getDefaults();
		$addDefaults = $this->getAddDefaults();
		$fileSettings = $this->getFromSettingsFile();
		$jsonSettings = $this->getFromSettingsJSON();
		
		if(count($fileSettings)) $addDefaults = $this->mergeSettings($addDefaults, $fileSettings);
		if(count($jsonSettings)) $addDefaults = $this->mergeSettings($addDefaults, $jsonSettings);
		if(count($addSettings)) $addDefaults = $this->mergeSettings($addDefaults, $addSettings);
		$addSettings = $addDefaults;

		if($configName && $configName !== 'default') {
			$js = $config->js($inputfield->className());

			// get settings that differ between field and defaults, then set to new named config
			$diffSettings = $this->getSettings($defaults, $configName);
			$mergedSettings = array_merge($defaults, $diffSettings);
			//$contentStyle = isset($mergedSettings['content_style']) ? $mergedSettings['content_style'] : '';

			if(count($addSettings)) {
				// merges $addSettings into $diffSettings
				$this->applyAddSettings($diffSettings, $addSettings, $defaults);
			}

			if(!isset($js['settings'][$configName])) {
				$js['settings'][$configName] = $this->prepareSettingsForOutput($diffSettings);
				$config->js($inputfield->className(), $js);
			}

			// get settings that will go in data-settings attribute 
			// remove settings that cannot be set for field/template context
			unset($mergedSettings['style_formats'], $mergedSettings['content_style'], $mergedSettings['content_css']); 
			$dataSettings = $this->getSettings($mergedSettings);
			$this->applySkin($dataSettings, $defaults);

		} else {
			// no configName in use, data-settings attribute will hold all non-default settings
			$dataSettings = $this->getSettings($defaults);
			//$contentStyle = isset($dataSettings['content_style']) ? $dataSettings['content_style'] : '';
			if(count($addSettings)) {
				$this->applyAddSettings($dataSettings, $addSettings, $defaults);
			}
		}

		if($inputfield->inlineMode) {
			if($inputfield->inlineMode < 2) unset($dataSettings['height']);
			$dataSettings['inline'] = true;
			/*
			if($contentStyle && $adminTheme) {
				$cssName = $configName;
				if(empty($cssName)) {
					$cssName = substr(md5($contentStyle), 0, 4) . strlen($contentStyle);
				}
				$inputfield->addClass("tmcei-$cssName", 'wrapClass');
				if(!isset(self::$caches['renderReadyInline'][$cssName])) {
					// inline mode content_style settings, ensure they are visible before inline init
					//$ns = ".tmcei-$cssName .mce-content-body ";
					//$contentStyle = $ns . str_replace('}', "} $ns", $contentStyle) . '{}';
					//$adminTheme->addExtraMarkup('head', "<style>$contentStyle</style>");
					self::$caches['renderReadyInline'][$cssName] = $cssName;
				}
			}
			*/
		}

		$dataSettings = count($dataSettings) ? $this->prepareSettingsForOutput($dataSettings) : array();
		if($inputfield->renderValueMode) $dataSettings['readonly'] = true;
		
		$features = array('imgUpload', 'imgResize', 'pasteFilter');
		foreach($features as $key => $feature) {
			if(!$inputfield->useFeature($feature)) unset($features[$key]);
		}
		if($inputfield->lazyMode) $features[] = "lazyMode$inputfield->lazyMode";
	
		// if external_plugins is empty it must be an empty object in JSON rather than array
		if(isset($dataSettings['external_plugins']) && empty($dataSettings['external_plugins'])) {
			$dataSettings['external_plugins'] = new \stdClass();
		}
		
		$inputfield->wrapAttr('data-configName', $configName);
		$inputfield->wrapAttr('data-settings', $this->tools()->jsonEncode($dataSettings, 'data-settings', false));
		$inputfield->wrapAttr('data-features', implode(',', $features));
	}

	/**
	 * Apply settings settings to $this->inputfield to inherit from another field
	 * 
	 * This is called from the main InputfieldTinyMCE class. 
	 *
	 * @param string $fieldName Field name or 'fieldName:id' string
	 * @return bool|Field Returns false or field inherited from
	 *
	 */
	public function applySettingsField($fieldName) {

		$fieldId = 0;
		$error = '';
		$hasField = $this->inputfield->hasField;
		$hasPage = $this->inputfield->hasPage;

		if(strpos($fieldName, ':')) {
			list($fieldName, $fieldId) = explode(':', $fieldName);
		} else if(ctype_digit("$fieldName")) {
			$fieldName = (int) $fieldName; // since fields.get also accepts IDs
		}
	
		// no need to inherit from oneself
		if("$fieldName" === "$hasField") return false;

		$field = $this->wire()->fields->get($fieldName);

		if(!$field) {
			$error = "Cannot find settings field '$fieldName'";
		} else if(!$field->type instanceof FieldtypeTextarea) {
			$error = "Settings field '$fieldName' is not of type FieldtypeTextarea";
			$field = null;
		} else if(!wireInstanceOf($field->get('inputfieldClass'), $this->inputfield->className())) {
			$error = "Settings field '$fieldName' is not using TinyMCE";
			$field = null;
		}

		if(!$field && $fieldId && $fieldName) {
			// try again with field ID only, which won't go recursive again
			return $this->applySettingsField($fieldId);
		}

		if(!$field) {
			if($error) $this->error($this->inputfield->attr('name') . ": $error");
			return false;
		}
		
		if($field->flags & Field::flagFieldgroupContext) {
			// field already in fieldgroup context
		} else if($hasPage && $hasPage->template->fieldgroup->hasFieldContext($field)) {
			// get in context of current page template’s fieldgroup, if applicable
			$field = $hasPage->template->fieldgroup->getFieldContext($field->id);
		}

		// identify settings to apply
		$data = array();

		foreach($this->inputfield->getSettingNames(array('tinymce', 'field')) as $name) {
			$value = $field->get($name);
			if($value !== null) $data[$name] = $value;
		}

		// apply settings
		$this->inputfield->data($data);

		return $field;
	}

}
