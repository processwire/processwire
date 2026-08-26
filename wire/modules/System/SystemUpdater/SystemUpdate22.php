<?php namespace ProcessWire;

/**
 * Add index on pages_parents.parents_id
 *
 * The table has only a PRIMARY KEY of (pages_id, parents_id), so a condition on
 * parents_id alone cannot use it, as parents_id is not the leading column. Deleting
 * a page runs "DELETE FROM pages_parents WHERE pages_id=? OR parents_id=?", which
 * therefore scanned the entire table for every page deleted. On a large site that
 * dominated the cost of deleting pages, particularly when deleting a branch or any
 * page using repeaters, since every repeater item is itself a page.
 *
 * With this index the same condition resolves as an index merge of the two, rather
 * than a full scan.
 *
 */
class SystemUpdate22 extends SystemUpdate {

	public function execute() {

		$database = $this->wire()->database;
		$table = 'pages_parents';
		$indexName = 'parents_id';

		if($database->indexExists($table, $indexName)) {
			// already present, nothing to do
			return true;
		}

		try {
			$database->exec("ALTER TABLE `$table` ADD INDEX `$indexName` (`$indexName`)");
			$this->message("Added index $indexName to $table");
		} catch(\Exception $e) {
			$this->error("Unable to add index $indexName to $table - " . $e->getMessage());
			return false;
		}

		return true;
	}
}
