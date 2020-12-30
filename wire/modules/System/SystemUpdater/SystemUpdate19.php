<?php namespace ProcessWire;

/**
 * Clear legacy and irrelevant entries from modules table
 *
 */
class SystemUpdate19 extends SystemUpdate {
	public function execute() {
		$this->wire()->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		return 0; // indicates we will update system version ourselves when ready
	}
	public function executeAtReady() {
		$database = $this->wire()->database;
		$modules = $this->wire()->modules;
		$query = $database->prepare('DELETE FROM modules WHERE class=:name');
		$qty = 0;
		foreach(array('ProcessPage', 'InputfieldWrapper', 'FieldtypeNumber') as $name) {
			if($modules->isInstalled($name)) continue;
			$query->bindValue(':name', $name);
			$query->execute();
			if($query->rowCount()) $qty++;
		}
		$query->closeCursor();
		if($qty) $this->message("Removed $qty redundant modules table entries", Notice::debug); 
		$this->updater->saveSystemVersion(19);
	}
}

