<?php namespace ProcessWire;

/**
 * Add 'published', and 'published_users_id' properties to pages table, and populates them
 *
 */
class SystemUpdate12 extends SystemUpdate {
	
	public function execute() {

		$query = $this->wire('database')->prepare("SHOW columns FROM `pages` LIKE 'published'");
		$query->execute();
		$result = true;
		
		if($query->rowCount() == 0) {

			try {
				$this->wire('database')->exec('ALTER TABLE pages ADD published datetime DEFAULT NULL AFTER `created_users_id`');
				$this->message("Added 'published' column to pages table");
			} catch(\Exception $e) {
				$this->error($e->getMessage());
				$result = false;
			}
			/*
			if($result) try {
				$this->wire('database')->exec('ALTER TABLE pages ADD published_users_id int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `published`');
				$this->message("Added 'published_users_id' column to pages table");
			} catch(\Exception $e) {
				$this->error($e->getMessage());
				$result = false;
			}
			*/
			if($result) try {
				//$sql = 'UPDATE pages SET published=modified, published_users_id=modified_users_id WHERE pages.status<2048 AND published IS NULL';
				$sql = 'UPDATE pages SET published=created WHERE pages.status<2048 AND published IS NULL';
				$query = $this->wire('database')->prepare($sql);
				$query->execute();
				$numRows = $query->rowCount();
				$this->message("Populated values to 'published' for $numRows pages");
				$this->wire('database')->exec('ALTER TABLE pages ADD KEY published (published)');
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		}
		
		$this->addHookAfter('ProcessWire::ready', $this, 'executeAtReady');
		
		return $result;
	}
	
	public function executeAtReady() {
		if($this->wire('fields')->get('published')) {
			$this->error("You have a field named 'published' that conflicts with the Page 'published' property. Please rename your field as soon as possible.");
		}
	}
}

