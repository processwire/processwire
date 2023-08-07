<?php namespace ProcessWire;

/**
 * AdminThemeUikit CSS
 * 
 * Manages selection of CSS file and determines when CSS file to be recompiled from LESS
 * source files. 
 *
 * @property bool $upgrade Set to true when upgrading core Uikit version. (default=false)
 * @property string $frameworkLessFile Full disk path to LESS file that includes the framework/Uikit.
 * @property array $baseStyles Base style options (default=[ 'reno', 'rock' ])
 * @property string $defaultStyle Default style (default='reno')
 * @property string $defaultCssFile Core CSS file to create when upgrading (relative to module root)
 * @property string $styleDir Directory where base .less files are located (relative to module root)
 * @property array $replacements Array of [find=>replace] for compiled CSS file.
 * 
 * @property string $configPhpHash Hash used internally to detect changes to $config->AdminThemeUikit settings.
 * @property string $configPhpName Name of property in $config that holds custom settings (default='AdminThemeUikit').
 * @property int $requireCssVersion
 * @property int $cssVersion
 *
 * Settings that may be specified in $config->AdminThemeUikit array:
 * 
 * @property string $style Configured style name to use, one of blank (for default), 'reno' or 'rock'.
 * @property bool $recompile Recompile all LESS to CSS now? (set to true for 1 request only)
 * @property bool $compress Compress compiled CSS? (default=true)
 * @property array $customLessFiles Custom .less file(s) to include, relative to PW root.
 * @property string $customCssFile Custom target .css file to compile custom .less file(s) to, relative to PW root.
 * @property array $vars LESS variables to be used when compiling. Eg ['rock-primary' => '#FF0000']
 * @property string $parse LESS string to parse, eg "@rock-primary: #FF0000;"
 * 
 * @since 3.0.179
 * 
 */
class AdminThemeUikitCss extends WireData {

	/**
	 * @var AdminTheme|AdminThemeUikit
	 * 
	 */
	protected $adminTheme;
	
	/**
	 * Construct
	 * 
	 * @param AdminTheme $adminTheme
	 * @param array $options
	 *
	 */
	public function __construct(AdminTheme $adminTheme, array $options = array()) {
		$this->adminTheme = $adminTheme;
		$adminTheme->wire($this);
		$this->setArray(array_merge($this->getDefaults(), $options)); 
		parent::__construct();
	}

	/**
	 * @return array
	 * 
	 */
	public function getDefaults() {
		return array(
			'baseStyles' => array('reno', 'rock'),
			'defaultStyle' => 'reno',
			'frameworkLessFile' => __DIR__ . '/uikit-pw/pw.less',
			'defaultCssFile' => 'uikit-pw/pw.min.css',
			'styleDir' => 'uikit-pw/styles/', 
			'style' => '',
			'upgrade' => false,
			'recompile' => false,
			'compress' => true,
			'customLessFiles' => array('/site/templates/admin.less'),
			'customCssFile' => '/site/assets/admin.css',
			'configPhpName' => $this->adminTheme->className(),
			'configPhpHash' => $this->adminTheme->get('configPhpHash'),
			'replacements' => array(),
			'cssVersion' => (int) $this->adminTheme->get('cssVersion'),
			'requireCssVersion' => 0,
			'vars' => array(),
			'parse' => '',
		);
	}
	
	/**
	 * Get the primary Uikit CSS file URL to use (whether default or custom)
	 *
	 * @param bool $getPath Get disk path rather than URL?
	 * @return string
	 *
	 */
	public function getCssFile($getPath = false) {

		$modules = $this->wire()->modules;

		if(!$modules->isInstalled('Less')) return $this->getDefaultCssFile($getPath);

		if($this->upgrade) {
			$cssFile = $this->getDefaultCssFile(true);
			$cssTime = filemtime($cssFile);
			$lessFiles = array();
			$recompile = true;
		} else {
			$lessFiles = array();
			$lessTime = 0;

			foreach($this->customLessFiles as $file) {
				$file = $this->customFile($file, 'less');
				if(!$file || !is_file($file)) continue;
				$lessFiles[] = $file;
				$mtime = filemtime($file);
				if($mtime > $lessTime) $lessTime = $mtime;
			}
			
			if(!count($lessFiles) && ($this->style === '' || $this->style === $this->defaultStyle)) {
				return $this->getDefaultCssFile($getPath);
			}

			$cssFile = $this->customFile($this->customCssFile, 'css');
			if(!$cssFile) return $this->getDefaultCssFile($getPath);
			
			$cssTime = is_file($cssFile) ? (int) filemtime($cssFile) : 0;
			$recompile = $this->recompile || $lessTime > $cssTime || $this->cssVersion < $this->requireCssVersion; 
			if(!$recompile && $this->configPhpSettingsChanged()) $recompile = true;
		}

		if($recompile) try {
			/** @var AdminThemeUikitLessInterface $less */
			$less = $modules->get('Less');
			$less->setOption('compress', $this->compress);
			$less->addFile($this->frameworkLessFile);
			$less->addFile($this->getAdminStyleLessFile());
			$less->addFiles($lessFiles);
			if(!empty($this->vars)) $less->parser()->ModifyVars($this->vars);
			if(!empty($this->parse)) $less->parser()->parse($this->parse);
			$options = array('replacements' => $this->replacements); 
			if(!$less->saveCss($cssFile, $options)) throw new WireException("Compile error: $cssFile");
			$messages = array(sprintf($this->_('Compiled: %s'), $cssFile));
			$cssTime = filemtime($cssFile);
			if($this->cssVersion < $this->requireCssVersion) {
				$messages[] = "(core CSS v$this->cssVersion => v$this->requireCssVersion)";
				$modules->saveConfig($this->adminTheme, 'cssVersion', $this->requireCssVersion);
				$this->adminTheme->set('cssVersion', $this->requireCssVersion);
			}
			$this->message(implode(' ', $messages), Notice::noGroup | Notice::superuser);
		} catch(\Exception $e) {
			$this->error('LESS - ' . $e->getMessage(), Notice::noGroup | Notice::superuser);
		}
	
		return $getPath ? $cssFile : $this->fileToUrl($cssFile) . "?v=$cssTime";
	}

	/**
	 * Get URL for given full path/file
	 * 
	 * @param string $file
	 * @return string
	 * 
	 */
	protected function fileToUrl($file) {
		$config = $this->wire()->config;
		return $config->urls->root . substr($file, strlen($config->paths->root));
	}

	/**
	 * Get default Uikit CSS file URL or disk path
	 *
	 * @param bool $getPath
	 * @return string
	 *
	 */
	public function getDefaultCssFile($getPath = false) {
		$config = $this->wire()->config;
		$file = $this->defaultCssFile;
		$path = $config->paths($this->adminTheme) . $file;
		if($getPath) return $path;
		$v = $config->debug ? filemtime($path) : $config->version;
		$url = $config->urls($this->adminTheme) . "$file?v=$v" ;
		return $url;
	}

	/**
	 * Have the $config->AdminThemeUikit settings changed?
	 *
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function configPhpSettingsChanged() {
		$settings = $this->wire()->config->get($this->configPhpName);
		unset($settings['recompile']); // recompile is runtime only setting
		$hashNow = md5(print_r($settings, true));
		$hashThen = $this->get('configPhpHash');
		if($hashNow === $hashThen) return false;
		$this->wire()->modules->saveConfig($this->adminTheme, 'configPhpHash', $hashNow);
		return true;
	}

	/**
	 * Apply custom file/path replacements
	 *
	 * @param string $file
	 * @param string $requireExtension Extension to require on given file
	 * @return string
	 *
	 */
	protected function customFile($file, $requireExtension = '') {

		$paths = $this->wire()->config->paths;
		$file = $this->wire()->files->unixFileName($file);
		
		$replacements = array(
			'/site/assets/' => $paths->assets,
			'/site/templates/' => $paths->templates,
			'/site/modules/' => $paths->siteModules,
		);

		if($requireExtension) {
			$ext = pathinfo($file, PATHINFO_EXTENSION);
			if(strtolower($ext) !== strtolower($requireExtension)) return '';
		}

		foreach($replacements as $find => $replace) {
			if(strpos($file, $find) === 0) $file = str_replace($find, $replace, $file);
		}

		if($file && strpos($file, $paths->root) !== 0) {
			$file = $paths->root . ltrim($file, '/');
		}

		return $file;
	}

	/**
	 * Get admin base style file to use
	 *
	 * @return string
	 *
	 */
	public function getAdminStyleLessFile() {

		$config = $this->wire()->config;
		$files = $this->wire()->files;
		$path = $config->paths($this->adminTheme) . $this->styleDir;
		$defaultFile = $path . $this->defaultStyle . '.less';
		$baseStyle = $this->upgrade ? $this->defaultStyle : $this->style;

		if(empty($baseStyle) || $baseStyle === $this->defaultStyle) return $defaultFile;

		if(stripos($baseStyle, '.')) {
			// style is file name relative to installation root path
			$file = $this->customFile($baseStyle, 'less');
			if($file && is_file($file) && $files->allowPath($file, $config->paths->root)) return $file;
		}

		$file = $path . basename($baseStyle) . '.less';
		if(in_array($baseStyle, $this->baseStyles) || is_file($file)) return $file;

		$this->warning(
			"config.{$this->configPhpName}[style]: " .
			sprintf($this->_('Admin base style - file not found: %s'), $file),
			Notice::debug
		);

		return $defaultFile;
	}
	
}
