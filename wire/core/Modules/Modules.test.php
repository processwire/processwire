<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $modules API variable
 *
 */
class WireTest_Modules extends WireTest {

	protected $moduleClass = 'TestModule';
	protected $alreadyInstalled = false;
	protected $installedByTest = false;
	protected $originalConfigData = null;
	protected $refreshProbeName = 'WireTestRefreshProbe';
	protected $refreshProbeDir = '';
	protected $refreshProbeFile = '';

	public function init() {
		$modules = $this->wire()->modules;
		$this->refreshProbeName = 'WireTestRefreshProbe' . mt_rand(100000, 999999);
		$this->alreadyInstalled = $modules->isInstalled($this->moduleClass);
		$this->installedByTest = false;
		$this->originalConfigData = null;
	}

	public function execute() {
		$this->testInstallLifecycle();
		$this->testGettingModules();
		$this->testModuleInfo();
		$this->testStatusChecks();
		$this->testFinders();
		$this->testConfiguration();
		$this->testHelperProperties();
		$this->testModuleFileAssets();
		$this->testRefreshDiscoversNewModuleFile();
		$this->testUninstallLifecycle();
	}

	public function finish() {
		$modules = $this->wire()->modules;
		$this->cleanupRefreshProbe();

		if($this->originalConfigData !== null && $modules->isInstalled($this->moduleClass)) {
			$modules->saveConfig($this->moduleClass, $this->originalConfigData);
			$this->originalConfigData = null;
		}

		if($this->installedByTest && $modules->isInstalled($this->moduleClass)) {
			$modules->uninstall($this->moduleClass);
			$this->installedByTest = false;
		}
	}

	protected function testInstallLifecycle() {
		$modules = $this->wire()->modules;

		if(!$this->alreadyInstalled) {
			$this->check('isInstallable() true when module not yet installed', true, $modules->isInstallable($this->moduleClass));
			$installed = $modules->install($this->moduleClass);
			$this->check('install() returns Module instance', true, $installed instanceof Module);
			$this->installedByTest = true;
			$this->li("Installed $this->moduleClass");
		}

		$this->check('isInstalled() true after install', true, $modules->isInstalled($this->moduleClass));
	}

	protected function testGettingModules() {
		$modules = $this->wire()->modules;
		$moduleClass = $this->moduleClass;
		$m = $modules->get($moduleClass);

		$this->check('get() returns Module instance', true, $m instanceof Module);
		$this->check('get() className() matches', $moduleClass, $m->className());

		$m2 = $modules->$moduleClass;
		$this->check('property access returns Module instance', true, $m2 instanceof Module);

		$mNull = $modules->get('NonExistentModuleXyz123');
		$this->check('get() returns null for non-existent module', null, $mNull);
	}

	protected function testModuleInfo() {
		$modules = $this->wire()->modules;
		$moduleClass = $this->moduleClass;
		$info = $modules->getModuleInfo($moduleClass);

		$this->check('getModuleInfo() returns array', true, is_array($info));
		$this->check("info['name'] matches class", $moduleClass, $info['name']);
		$this->check("info['title'] is non-empty string", true, is_string($info['title']) && strlen($info['title']) > 0);
		$this->check("info['version'] is set", true, isset($info['version']));
		$this->check("info['installed'] is true", true, (bool) $info['installed']);

		$versionStr = $modules->formatVersion($info['version']);
		$this->check("formatVersion(1) returns '0.0.1'", '0.0.1', $versionStr);

		$titleProp = $modules->getModuleInfoProperty($moduleClass, 'title');
		$this->check('getModuleInfoProperty() returns title', $info['title'], $titleProp);

		$infoV = $modules->getModuleInfoVerbose($moduleClass);
		$this->check("getModuleInfoVerbose() has 'versionStr'", true, isset($infoV['versionStr']));
		$this->check("getModuleInfoVerbose() 'versionStr' matches formatVersion()", $versionStr, $infoV['versionStr']);
		$this->check("getModuleInfoVerbose() has non-empty 'file'", true, !empty($infoV['file']));
		$this->check("getModuleInfoVerbose() 'core' identifies core test fixture", true, (bool) $infoV['core']);

		$all = $modules->getModuleInfo('*');
		$this->check("getModuleInfo('*') returns non-empty array", true, count($all) > 0);
		$allFirst = reset($all);
		$this->check("getModuleInfo('*') values are arrays with 'name' key", true, is_array($allFirst) && isset($allFirst['name']));

		$infoTpl = $modules->getModuleInfo('info');
		$this->check("getModuleInfo('info') returns array", true, is_array($infoTpl));
		$this->check("getModuleInfo('info') template has 'title' key", true, array_key_exists('title', $infoTpl));
		$this->check("getModuleInfo('info') template has 'version' key", true, array_key_exists('version', $infoTpl));
	}

	protected function testStatusChecks() {
		$modules = $this->wire()->modules;
		$m = $modules->get($this->moduleClass);

		$this->check('isAutoload() false for TestModule', false, (bool) $modules->isAutoload($m));
		$this->check('isSingular() false for TestModule', false, (bool) $modules->isSingular($m));
		$this->check('isConfigurable() true for TestModule', true, (bool) $modules->isConfigurable($this->moduleClass));
	}

	protected function testFinders() {
		$modules = $this->wire()->modules;
		$moduleClass = $this->moduleClass;

		$byPrefix = $modules->findByPrefix('Inputfield');
		$this->check("findByPrefix('Inputfield') returns non-empty array", true, count($byPrefix) > 0);
		$this->check('findByPrefix keys start with the given prefix', true, strpos(array_key_first($byPrefix), 'Inputfield') === 0);

		$byPrefixTest = $modules->findByPrefix('TestModule');
		$this->check("findByPrefix('TestModule') finds TestModule", true, isset($byPrefixTest[$moduleClass]));
		$byPrefixLoaded = $modules->findByPrefix('TestModule', true);
		$this->check('findByPrefix(load=true) returns Module instances', true, reset($byPrefixLoaded) instanceof Module);

		$cliModules = $modules->findByFlag(Modules::flagsCli);
		$this->check('findByFlag(flagsCli) returns non-empty array', true, count($cliModules) > 0);
		$this->check('findByFlag(flagsCli) includes TestModule', true, isset($cliModules[$moduleClass]));

		$autoloaders = $modules->findByInfo('autoload');
		$this->check("findByInfo('autoload') returns non-empty array (core has autoload modules)", true, count($autoloaders) > 0);

		$byName = $modules->findByInfo('name=' . $moduleClass);
		$this->check('findByInfo(name=TestModule) finds module', true, isset($byName[$moduleClass]));

		$byArray = $modules->findByInfo(array('name' => $moduleClass));
		$this->check("findByInfo(['name'=>...]) finds module", true, isset($byArray[$moduleClass]));

		$byNameLoaded = $modules->findByInfo('name=' . $moduleClass, true);
		$this->check('findByInfo(load=true) returns Module instances', true, reset($byNameLoaded) instanceof Module);
	}

	protected function testConfiguration() {
		$modules = $this->wire()->modules;
		$moduleClass = $this->moduleClass;
		$configData = $modules->getConfig($moduleClass);

		$this->check('getConfig() returns array', true, is_array($configData));
		$this->originalConfigData = $configData;

		$origValue = isset($configData['testValue']) ? $configData['testValue'] : 'Hello World';
		$newValue = 'WireTestsModified_' . mt_rand(1000, 9999);
		$modules->saveConfig($moduleClass, 'testValue', $newValue);
		$this->check('getConfig(module, key) returns saved value', $newValue, $modules->getConfig($moduleClass, 'testValue'));

		$saveData = $modules->getConfig($moduleClass);
		$saveData['testValue'] = $origValue;
		$modules->saveConfig($moduleClass, $saveData);
		$this->check('saveConfig(module, array) restores original value', $origValue, $modules->getConfig($moduleClass, 'testValue'));
		$this->originalConfigData = null;

		$editUrl = $modules->getModuleEditUrl($moduleClass);
		$this->check('getModuleEditUrl() returns non-empty string', true, strlen($editUrl) > 0);
	}

	protected function testHelperProperties() {
		$modules = $this->wire()->modules;

		$this->check('$modules->info is ModulesInfo instance', true, $modules->info instanceof ModulesInfo);
		$this->check('$modules->configs is ModulesConfigs instance', true, $modules->configs instanceof ModulesConfigs);
		$this->check('$modules->flags is ModulesFlags instance', true, $modules->flags instanceof ModulesFlags);
	}

	protected function testRefreshDiscoversNewModuleFile() {
		$modules = $this->wire()->modules;
		$files = $this->wire()->files;
		$config = $this->wire()->config;
		$name = $this->refreshProbeName;

		$this->cleanupRefreshProbe();
		$modules->refresh();

		$this->refreshProbeDir = $config->paths->siteModules . "$name/";
		$this->refreshProbeFile = $this->refreshProbeDir . "$name.module.php";

		if(!is_dir($this->refreshProbeDir)) $files->mkdir($this->refreshProbeDir);
		file_put_contents($this->refreshProbeFile,
			"<?php namespace ProcessWire;\n" .
			"class $name extends WireData implements Module {\n" .
			"\tpublic static function getModuleInfo() { return array('title' => 'WireTest refresh probe', 'version' => 1); }\n" .
			"}\n"
		);

		$modules->refresh();
		$this->check('refresh() discovers newly added module file', true, $modules->isInstallable($name));
		$this->testRefreshInformationFiles();

		$this->cleanupRefreshProbe();
		$modules->refresh();
		$this->check('refresh() removes deleted module file', false, $modules->isInstallable($name));
	}

	protected function testRefreshInformationFiles() {
		$modules = $this->wire()->modules;
		$name = $this->refreshProbeName;
		$file = $this->refreshProbeFile;
		$providedFiles = array($this->refreshProbeDir => array(basename($file)));
		// Keep fixture timestamps older than the request so the same-request retry does not mask the bug.
		touch($file, 1600000000);
		clearstatcache(true, $file);
		$modules->refresh();
		foreach(array('php', 'json') as $format) {
			$infoFile = $this->refreshProbeDir . "$name.info.$format";
			$before = $modules->files->getModuleFileSnapshot($providedFiles);
			foreach(array(100, 101) as $version) {
				$info = array('title' => 'WireTest refresh probe', 'version' => $version);
				$content = $format === 'php' ? '<?php namespace ProcessWire; $info = ' . var_export($info, true) . ';' : json_encode($info);
				file_put_contents($infoFile, $content);
				touch($infoFile, 1600000000 + $version);
				clearstatcache(true, $infoFile);
				$snapshot = $modules->files->getModuleFileSnapshot($providedFiles);
				$this->check("snapshot includes .info.$format from provided module files", true, isset($snapshot[$infoFile]));
				$this->check("snapshot detects .info.$format version $version", false, $before === $snapshot);
				$all = $modules->files->getModuleFileSnapshot();
				$this->check("snapshot includes .info.$format from known module files", true, isset($all[$infoFile]));
				$modules->refresh();
				$actual = $modules->getModuleInfo($name);
				$this->check("refresh() reads .info.$format version $version", $version, $actual['version']);
				$this->check("unchanged .info.$format snapshot is stable", $snapshot, $modules->files->getModuleFileSnapshot($providedFiles));
				$before = $snapshot;
			}
			$this->wire()->files->unlink($infoFile);
			clearstatcache(true, $infoFile);
			$snapshot = $modules->files->getModuleFileSnapshot($providedFiles);
			$this->check("snapshot detects removal of .info.$format", false, $before === $snapshot);
			$modules->refresh();
			$actual = $modules->getModuleInfo($name);
			$this->check("refresh() falls back to internal info after removing .info.$format", 1, $actual['version']);
		}
	}

	/**
	 * Module versions must retain their dotted components in asset cache keys.
	 * Explicit version-URL settings are applied later by FilenameArray::urls().
	 */
	protected function testModuleFileAssets() {
		$wire = $this->wire();
		$config = $wire->config;
		$files = $wire->files;
		$previous = [];
		foreach(['debug', 'useVersionUrls', 'styles', 'scripts'] as $key) $previous[$key] = $config->$key;
		$base = uniqid('WireTestAsset');
		$dir = $config->paths->cache . "$base/";
		$url = $config->urls->cache . "$base/";
		$created = [];
		$classes = [];
		$tokens = [];
		$cases = [
			['1.0.1', null, false],
			['1.0.2', null, false],
			[123, null, false],
			['1.0.2', null, true],
			['1.0.2', true, false],
			['1.0.2', false, false],
			['1.0.2', '?release=assets-123', false],
		];
		$files->mkdir($dir);

		try {
			foreach($cases as $index => $case) {
				list($version, $setting, $debug) = $case;
				$name = $base . "Case$index";
				$class = __NAMESPACE__ . "\\$name";
				$classes[] = $name;
				$info = ['title' => 'WireTest asset probe', 'version' => $version];
				$file = $dir . "$name.module.php";
				$created[] = $file;
				file_put_contents($file, "<?php namespace ProcessWire;\nclass $name extends WireData implements Module {\n" .
					'public static function getModuleInfo() { return ' . var_export($info, true) . "; }\n}\n");
				require_once($file);
				$module = $wire->wire(new $class());
				$config->paths->set($name, $dir);
				$config->urls->set($name, $url);
				$config->debug = $debug;
				$config->useVersionUrls = $setting;
				foreach(['styles', 'scripts'] as $type) $config->$type = $wire->wire(new FilenameArray());
				foreach(['css' => 1, 'js' => 2] as $ext => $offset) {
					foreach(['', '.min'] as $min) {
						$file = "$dir$name$min.$ext";
						$created[] = $file;
						file_put_contents($file, "/* WireTest asset */\n");
						touch($file, 1600000000 + $offset + ($min ? 10 : 0));
						clearstatcache(true, $file);
					}
				}
				$this->check("asset case $index loads CSS and JS", 2, $wire->modules->files->loadModuleFileAssets($module));
				foreach(['styles' => 'css', 'scripts' => 'js'] as $type => $ext) {
					$min = $debug ? '' : '.min';
					$assetURL = "$url$name$min.$ext";
					$mtime = filemtime("$dir$name$min.$ext");
					$queued = array_values(iterator_to_array($config->$type));
					$expected = $assetURL;
					if($setting === null) $expected .= '?v=' . $version . '-' . ($debug ? $mtime : $config->version);
					$this->check("asset case $index queues the expected $ext URL", [$expected], $queued);
					$rendered = $config->$type->urls();
					if($setting === true) {
						$expected .= '?' . base_convert($mtime, 10, 36);
					} else if($setting === false) {
						$expected .= "?v=$config->version";
					} else if(is_string($setting)) {
						$expected .= $setting;
					}
					$this->check("asset case $index renders the expected $ext URL", [$expected], $rendered);
					if($index < 2 && $ext === 'css') $tokens[] = parse_url($rendered[0], PHP_URL_QUERY);
				}
				$this->check("asset case $index is not loaded twice", 0, $wire->modules->files->loadModuleFileAssets($module));
			}
			$this->check('dotted patch releases have different asset cache tokens', false, $tokens[0] === $tokens[1]);
		} finally {
			foreach($previous as $key => $value) $config->$key = $value;
			foreach($classes as $name) {
				$config->paths->remove($name);
				$config->urls->remove($name);
			}
			foreach($created as $file) if(is_file($file)) $files->unlink($file);
			$files->rmdir($dir);
		}
	}

	protected function testUninstallLifecycle() {
		$modules = $this->wire()->modules;

		if(!$this->alreadyInstalled) {
			$modules->uninstall($this->moduleClass);
			$this->check('isInstalled() false after uninstall', false, $modules->isInstalled($this->moduleClass));
			$this->check('isInstallable() true after uninstall (file still on disk)', true, $modules->isInstallable($this->moduleClass));
			$this->installedByTest = false;
			$this->li("Uninstalled $this->moduleClass");
		}
	}

	protected function cleanupRefreshProbe() {
		$files = $this->wire()->files;
		$name = $this->refreshProbeName;
		$dir = $this->refreshProbeDir ? $this->refreshProbeDir : $this->wire()->config->paths->siteModules . "$name/";
		$file = $this->refreshProbeFile ? $this->refreshProbeFile : $dir . "$name.module.php";

		foreach(array('php', 'json') as $format) {
			$infoFile = $dir . "$name.info.$format";
			if(is_file($infoFile)) $files->unlink($infoFile);
		}
		if(is_file($file)) $files->unlink($file);
		if(is_dir($dir)) $files->rmdir($dir);
		$this->refreshProbeDir = '';
		$this->refreshProbeFile = '';
	}
}
