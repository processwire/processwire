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

	public function init() {
		$modules = $this->wire()->modules;
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
		$this->testUninstallLifecycle();
	}

	public function finish() {
		$modules = $this->wire()->modules;

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
}
