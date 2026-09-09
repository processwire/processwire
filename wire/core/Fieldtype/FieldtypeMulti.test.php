<?php namespace ProcessWire;

/**
 * Tests for FieldtypeMulti
 *
 * Focused on FieldtypeMulti::savePageField(), which deletes and re-inserts all rows for
 * the page as a unit, including its failure/rollback path.
 *
 */
class WireTest_FieldtypeMulti extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'fieldtypemulti';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$database = $this->wire()->database;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$field = $fields->get($name);
		$table = $database->escapeTable($field->getTable());

		// build a non-paginated PageArray, since a paginated value is not saveable
		$items = $pages->newPageArray();
		foreach($pages->find("id>1, limit=3, include=all, sort=id") as $item) $items->add($item);
		if($items->count() < 2) $this->fail('Expected at least 2 pages available for test values');

		// save multiple values: covers the delete + insert loop and commit
		$page->of(false);
		$page->set($name, $items);
		$field->type->savePageField($page, $field);

		$rows = $this->getRows($table, $page->id);
		$this->check('savePageField() inserts a row per value', $items->count(), count($rows));

		$sorts = array();
		$datas = array();
		foreach($rows as $row) {
			$sorts[] = (int) $row['sort'];
			$datas[] = (int) $row['data'];
		}
		$expectSorts = range(0, $items->count() - 1);
		$this->check('savePageField() assigns sequential sort values', $expectSorts, $sorts);
		$this->check('savePageField() stores values in order', $items->explode('id'), $datas);

		// save empty value: covers the early return that deletes without inserting
		$page->set($name, $pages->newPageArray());
		$result = $field->type->savePageField($page, $field);
		$this->check('savePageField() returns true when there are no values', true, $result);
		$this->check('savePageField() removes existing rows when there are no values', 0, count($this->getRows($table, $page->id)));

		// restore values so there is existing data to protect in the failure test below
		$page->set($name, $items);
		$field->type->savePageField($page, $field);
		$numRows = count($this->getRows($table, $page->id));
		$this->check('savePageField() restored rows for failure test', $items->count(), $numRows);

		// failure path: a non-retryable error must roll back, leave no open transaction,
		// and not spend any time on retries
		$page->set($name, $items->slice(0, 1));
		$missingTable = $table . '_missing';
		$database->exec("DROP TABLE IF EXISTS `$missingTable`");
		$database->exec("RENAME TABLE `$table` TO `$missingTable`");
		$exception = null;
		$elapsed = 0;

		try {
			$timer = microtime(true);
			try {
				$field->type->savePageField($page, $field);
			} catch(\Exception $e) {
				$exception = $e;
			}
			$elapsed = microtime(true) - $timer;
		} finally {
			$database->exec("RENAME TABLE `$missingTable` TO `$table`");
		}

		$this->check('savePageField() throws when the query fails', true, $exception !== null);
		$this->check('savePageField() leaves no open transaction after failure', false, $database->inTransaction());
		$this->check('savePageField() does not retry a non-retryable error', true, $elapsed < 0.3);
		$this->check('savePageField() failure left existing rows intact', $numRows, count($this->getRows($table, $page->id)));

		if($exception !== null) {
			$this->li('Exception class on failure: ' . get_class($exception));
		}
	}

	/**
	 * Get rows stored for given page in given field table
	 *
	 * @param string $table
	 * @param int $pageId
	 * @return array
	 *
	 */
	protected function getRows($table, $pageId) {
		$database = $this->wire()->database;
		$query = $database->prepare("SELECT * FROM `$table` WHERE pages_id=:pages_id ORDER BY sort");
		$query->bindValue(':pages_id', (int) $pageId, \PDO::PARAM_INT);
		$query->execute();
		$rows = $query->fetchAll(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		return $rows;
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$field = $fields->get($this->fieldName);

		if(!$field) {
			$field = new Field();
			$field->type = $modules->get('FieldtypePage');
			$field->name = $this->fieldName;
			$field->label = 'Test Multi';
			$field->derefAsPage = FieldtypePage::derefAsPageArray;
			$field->save();
			$this->li("Created field: $field->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
			$this->li("Added field to fieldgroup: $field->name");
		}
	}
}
