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

		$admin = $this->wire('pages')->get($this->wire('config')->adminRootPageID);
		$page = $this->wire('pages')->get($admin->path . 'page/list/');
		
		if(!$page->id) return;
		$page->of(false);
		$page->removeStatus(Page::statusHidden);
		try {
			$page->save();
			$this->updater->saveSystemVersion(16);
		} catch(\Exception $e) {
			// will try next time
		}
	}
}

