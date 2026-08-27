<?php namespace ProcessWire;

/**
 * Tests for ProcessWire ProcessController class
 *
 */
class WireTest_ProcessController extends WireTest {

	protected $moduleClass = 'ProcessTestPermission';
	protected $installedByTest = false;
	protected $originalUser = null;
	protected $testUser = null;

	public function init() {
		$modules = $this->wire()->modules;
		$this->originalUser = $this->wire()->user;
		if(!$modules->isInstalled($this->moduleClass)) {
			if(!$modules->isInstallable($this->moduleClass)) $modules->refresh();
			$modules->install($this->moduleClass);
			$this->installedByTest = true;
		}
		$this->testUser = $this->wire()->users->add('wire_test_process_permission');
		$this->testUser->pass = bin2hex(random_bytes(12));
		$this->testUser->save();
	}

	public function execute() {
		$this->wire()->users->setCurrentUser($this->testUser);

		ProcessTestPermission::$allow = false;
		ProcessTestPermission::$calls = 0;
		$this->check('permissionMethod false denies Process execution', false, $this->canGetProcess());
		$this->check('permissionMethod false is evaluated by ProcessController', 1, ProcessTestPermission::$calls);

		ProcessTestPermission::$allow = true;
		ProcessTestPermission::$calls = 0;
		$this->check('permissionMethod true allows Process execution without permission option', true, $this->canGetProcess());
		$this->check('permissionMethod true is evaluated by ProcessController', 1, ProcessTestPermission::$calls);

		$this->wire()->users->setCurrentUser($this->wire()->users->getGuestUser());
		ProcessTestPermission::$allow = true;
		ProcessTestPermission::$calls = 0;
		$this->check('guest remains denied when permissionMethod returns true', false, $this->canGetProcess());
		$this->check('guest denial occurs before permissionMethod', 0, ProcessTestPermission::$calls);
	}

	public function finish() {
		if($this->originalUser) $this->wire()->users->setCurrentUser($this->originalUser);
		if($this->testUser && $this->testUser->id) $this->wire()->users->delete($this->testUser);
		if($this->installedByTest && $this->wire()->modules->isInstalled($this->moduleClass)) {
			$this->wire()->modules->uninstall($this->moduleClass);
		}
	}

	protected function canGetProcess() {
		$controller = $this->wire(new ProcessController());
		$controller->setProcessName($this->moduleClass);
		try {
			return $controller->getProcess() instanceof ProcessTestPermission;
		} catch(ProcessControllerPermissionException $e) {
			return false;
		}
	}
}
