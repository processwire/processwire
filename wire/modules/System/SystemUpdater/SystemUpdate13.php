<?php namespace ProcessWire;

/**
 * Make the /processwire/page/edit/ unhidden so that it shows up in Pages nav, plus update the label
 *
 */
class SystemUpdate13 extends SystemUpdate {
	
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	
	public function executeAtReady() {
		$moduleID = $this->wire('modules')->getModuleID('ProcessPageEdit');
		/** @var Languages $languages */
		$languages = $this->wire('languages');
		try {
			if($languages) $languages->setDefault();
			$page = $this->wire('pages')->get("template=admin, process=$moduleID, include=all");
			if($page->id) {
				$page->of(false);
				//$page->removeStatus(Page::statusHidden);
				if($page->title == 'Edit Page') $page->title = 'Edit';
				$page->save();
				$this->updater->saveSystemVersion(13);
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
		if($languages) $languages->unsetDefault();
	}
}

