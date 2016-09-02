<?php namespace ProcessWire;

/**
 * Make the /processwire/page/add/ unhidden so that it shows up in Pages nav
 *
 */
class SystemUpdate11 extends SystemUpdate {
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	public function executeAtReady() {
		$moduleID = $this->wire('modules')->getModuleID('ProcessPageAdd');
		try {
			$page = $this->wire('pages')->get("template=admin, process=$moduleID, include=all");
			if($page->id) {
				$page->of(false);
				$page->removeStatus(Page::statusHidden);
				$page->save();
				$this->updater->saveSystemVersion(11);
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
	}
}

