<?php namespace ProcessWire;

class SystemUpdate10 extends SystemUpdate {

	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	
	public function executeAtReady() {
		try {
			// install new ProcessLogger module
			if($this->wire('languages')) $this->wire('languages')->setDefault();
			
			$this->modules->resetCache();
			$this->modules->install('ProcessLogger');
			$this->modules->install('InputfieldIcon');
			$this->message("Installed ProcessLogger and added: Setup > Logs");
			$this->message("Installed InputfieldIcon");
		
			// we moved default WireTempDir into /site/assets/cache/, so remove old temp dir and any files in it
			$path = $this->wire('config')->paths->assets . 'WireTempDir/';
			if(is_dir($path)) wireRmdir($path, true);
			
			$this->updater->saveSystemVersion(10);
			if($this->wire('languages')) $this->wire('languages')->unsetDefault();
			
		} catch(\Exception $e) {
			$this->error($e->getMessage()); 
		}
	}
}
