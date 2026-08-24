<?php namespace ProcessWire;

/**
 * Tests for ProcessPageEditImageSelect
 *
 */
class WireTest_ProcessPageEditImageSelect extends WireTest {

	public function execute() {
		$module = $this->wire(new WireTestable_ProcessPageEditImageSelect());
		$module->setTestPage($this->wire()->pages->get(1));
		$module->setRte(true);

		$denied = false;
		try {
			$module->processSaveForTest();
		} catch(WirePermissionException $e) {
			$denied = true;
		}

		$this->check('RTE requests cannot invoke non-RTE image save actions', true, $denied);
	}
}

/**
 * Exposes protected state and behavior required by the test.
 *
 */
class WireTestable_ProcessPageEditImageSelect extends ProcessPageEditImageSelect {

	public function setTestPage(Page $page) {
		$this->page = $page;
		$this->masterPage = $page;
	}

	public function setRte($rte) {
		$this->rte = (bool) $rte;
	}

	public function processSaveForTest() {
		return $this->processSave(false);
	}
}
