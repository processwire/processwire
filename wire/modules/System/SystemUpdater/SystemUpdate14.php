<?php namespace ProcessWire;

/**
 * Update pages/edit and pages/list to be hidden if bookmarks aren't active
 *
 */
class SystemUpdate14 extends SystemUpdate {

	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}

	public function executeAtReady() {
		
		$admin = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
		$info = array(
			'ProcessPageEdit' => $admin->path . 'page/edit/', 
			// 'ProcessPageList' => $admin->path . 'page/list/',
		);
	
		$numCompleted = 0;
		foreach($info as $moduleName => $pagePath) {
			$configData = $this->wire('modules')->getModuleConfigData($moduleName);
			if(!empty($configData['bookmarks'])) {
				$numCompleted++;
				continue;
			}
			$page = $this->wire('pages')->get($pagePath);
			if(!$page->id) {
				$numCompleted++;
				continue;
			}
			$page->of(false);
			$page->addStatus(Page::statusHidden);
			try {
				$page->save();
				$numCompleted++;
			} catch(\Exception $e) {
			}
		}
	
		if($numCompleted >= count($info)) {
			$this->updater->saveSystemVersion(14);
		}
	}
}

