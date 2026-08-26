<?php namespace ProcessWire;

/**
 * Change index on pages.parent_id to a composite of (parent_id, sort)
 *
 * Listing the children of a page in manual sort order produces a query of the form
 * "WHERE pages.parent_id=X ORDER BY pages.sort LIMIT n". The index on parent_id alone
 * locates the family but cannot provide the order, so the database sorted every child
 * of that parent in order to return the first few. The cost was a function of how many
 * children the parent had rather than of how many were asked for, and paging through
 * children repeated it for every page of results.
 *
 * Adding sort to the same index lets the rows be read in order, so only as many are
 * read as are returned. As parent_id remains the leading column, lookups that use only
 * parent_id continue to use this index exactly as before.
 *
 */
class SystemUpdate23 extends SystemUpdate {

	public function execute() {

		$database = $this->wire()->database;
		$table = 'pages';
		$indexName = 'parent_id';

		// find the columns currently covered by the index
		$columns = array();

		try {
			$query = $database->prepare("SHOW INDEX FROM `$table` WHERE Key_name=:name");
			$query->bindValue(':name', $indexName);
			$query->execute();
			foreach($query->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$columns[(int) $row['Seq_in_index']] = $row['Column_name'];
			}
			$query->closeCursor();
		} catch(\Exception $e) {
			$this->error("Unable to read index $indexName from $table - " . $e->getMessage());
			return false;
		}

		ksort($columns);
		$columns = array_values($columns);

		// already updated, nothing to do
		if($columns === array('parent_id', 'sort')) return true;

		try {
			if(count($columns)) {
				// replace in a single statement so the index is never absent
				$database->exec(
					"ALTER TABLE `$table` " .
					"DROP INDEX `$indexName`, " .
					"ADD INDEX `$indexName` (`parent_id`,`sort`)"
				);
			} else {
				// index not present at all, so just add it
				$database->exec("ALTER TABLE `$table` ADD INDEX `$indexName` (`parent_id`,`sort`)");
			}
			$this->message("Updated index $indexName on $table to (parent_id, sort)");
		} catch(\Exception $e) {
			$this->error("Unable to update index $indexName on $table - " . $e->getMessage());
			return false;
		}

		return true;
	}
}
