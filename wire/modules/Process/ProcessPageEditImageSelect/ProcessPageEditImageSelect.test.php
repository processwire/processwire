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
		$this->testRequestTokens($module);
	}

	protected function testRequestTokens(WireTestable_ProcessPageEditImageSelect $module) {
		$input = $this->wire()->input;
		$config = $this->wire()->config;
		$postPrevious = $input->post->getArray();
		$protectPrevious = $config->protectCSRF;
		$config->protectCSRF = true;

		try {
			foreach(array('executeEdit' => 'crop', 'executeResize' => 'resize') as $method => $action) {
				foreach(array(null, 'invalid') as $value) {
					$token = $this->wire()->session->CSRF()->getToken();
					$input->post->removeAll();
					if($action === 'crop') $input->post->set('submit_crop', '1');
					if($value !== null) $input->post->set($token['name'], $value);
					$module->testImageRequested = false;
					$denied = false;
					try {
						$module->$method();
					} catch(WireCSRFException $e) {
						$denied = true;
					} catch(\RuntimeException $e) {
						if(!$module->testImageRequested) throw $e;
					}
					$label = $value === null ? 'missing' : 'invalid';
					$this->check("RTE $action rejects $label token before processing an image", true, $denied && !$module->testImageRequested);
				}

				$token = $this->wire()->session->CSRF()->getToken();
				$input->post->set($token['name'], $token['value']);
				$module->testImageRequested = false;
				try {
					$module->$method();
				} catch(\RuntimeException $e) {
					if(!$module->testImageRequested) throw $e;
				}
				$this->check("RTE $action with a valid token reaches image processing", true, $module->testImageRequested);
			}
		} finally {
			$input->post->removeAll();
			$input->post->setArray($postPrevious);
			$config->protectCSRF = $protectPrevious;
		}
	}
}

/**
 * Exposes protected state and behavior required by the test.
 *
 */
class WireTestable_ProcessPageEditImageSelect extends ProcessPageEditImageSelect {
	public $testImageRequested = false;

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

	public function getPageimage($getVariation = false) {
		$this->testImageRequested = true;
		throw new \RuntimeException('Image processing reached by test');
	}
}
