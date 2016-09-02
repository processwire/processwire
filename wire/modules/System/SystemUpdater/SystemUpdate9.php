<?php namespace ProcessWire;

class SystemUpdate9 extends SystemUpdate {

	public function execute() {
		if($this->wire('config')->systemVersion < 8) return 0; // we'll wait till next request
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	
	public function executeAtReady() {
		try {
			if($this->wire('languages')) $this->wire('languages')->setDefault();
			$this->modules->resetCache();
			$this->modules->install('ProcessRecentPages');
			$this->message("Added: Pages > Recent ");
			$this->updater->saveSystemVersion(9);
			if($this->wire('languages')) $this->wire('languages')->unsetDefault();
		} catch(\Exception $e) {
			$this->error($e->getMessage()); 
		}
	}
}
