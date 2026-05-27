<?php namespace ProcessWire;

/**
 * Clear legacy and irrelevant entries from modules table
 *
 */
class SystemUpdate19 extends SystemUpdateAtReady {
	public function update() {
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
		return true;
	}
}
