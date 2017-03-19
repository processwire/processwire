<?php namespace ProcessWire;

/**
 * Re-enable the Pages > Tree navigation item
 *
 */
class SystemUpdate16 extends SystemUpdate {
	
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}

	public function executeAtReady() {

		$pages = $this->wire('pages');
		$admin = $pages->get($this->wire('config')->adminRootPageID);
		$pageList = $pages->get($admin->path . 'page/list/');
		if(!$pageList->id) return;
		$pageList->of(false);
		$pageList->removeStatus(Page::statusHidden);
		try {
			$pageList->save();
			$pageAdd = $pages->get($admin->path . 'page/add/');
			if($pageAdd->id) $pages->insertBefore($pageList, $pageAdd);
			$this->updater->saveSystemVersion(16);
		} catch(\Exception $e) {
			// will try next time
		}
	}
}

