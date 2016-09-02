<?php namespace ProcessWire;

/**
 * Add caches table for WireCache 
 *
 */
class SystemUpdate6 extends SystemUpdate {
	
	public function execute() {
		$query = $this->wire('database')->prepare("SHOW tables LIKE 'caches'"); 
		$query->execute();
		if($query->rowCount() > 0) return true; 
		$dbEngine = $this->wire('config')->dbEngine;
		$dbCharset = $this->wire('config')->dbCharset; 
		
		$sql = "
			CREATE TABLE caches (
				name VARCHAR(128) NOT NULL PRIMARY KEY,
				data MEDIUMTEXT NOT NULL, 
				expires DATETIME NOT NULL, 
				INDEX expires (expires)
			) ENGINE=$dbEngine DEFAULT CHARSET=$dbCharset;
			";
	
		try {
			$this->wire('database')->exec($sql); 
			$this->message("Added caches table"); 	
		} catch(\Exception $e) {
			$this->error($e->getMessage()); 
			return false; 
		}
	}
}

