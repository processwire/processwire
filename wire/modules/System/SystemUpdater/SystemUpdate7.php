<?php namespace ProcessWire;

/**
 * Add modified and created dates to modules table
 *
 */
class SystemUpdate7 extends SystemUpdate {
	
	public function execute() {
		
		$query = $this->wire('database')->prepare("SHOW columns FROM `modules` LIKE 'created'"); 
		$query->execute();
		if($query->rowCount() > 0) return true; 
		
		try {
			$sql = 'ALTER TABLE `modules` ADD `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP';
			$this->wire('database')->exec($sql);
			$this->message("Added 'created' column to modules table");
		} catch(\Exception $e) {
			$this->error($e->getMessage());
			return false;
		}
		
		return true; 
	}
}

