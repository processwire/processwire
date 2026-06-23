<?php namespace ProcessWire;

/**
 * Tests for ProcessWire DatabaseQuerySelect class
 *
 * Tests SELECT-specific features: clause ordering, select clause options,
 * from table quoting, join/leftjoin, orderby prepend, groupby with HAVING
 * shortcut, limit parsing, SQL caching, dbCache/SQL_NO_CACHE, debug comment,
 * and indent level.
 *
 */
class WireTest_DatabaseQuerySelect extends WireTest {

	protected $table = WireTests::fieldPrefix . 'databasequeryselect';
	protected $originalDbCache = null;
	protected $originalDebug = null;

	public function init() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$charset = $database->escapeTable($this->wire()->config->dbCharset ?: 'utf8mb4');

		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec(
			"CREATE TABLE `$table` (" .
			"`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, " .
			"`name` VARCHAR(64) NOT NULL, " .
			"`qty` INT NOT NULL DEFAULT 0, " .
			"`parent_id` INT NOT NULL DEFAULT 0, " .
			"`status` INT NOT NULL DEFAULT 1, " .
			"PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=$charset"
		);

		$this->originalDbCache = DatabaseQuerySelect::$dbCache;
		$this->originalDebug = $this->wire()->config->debug;
		DatabaseQuerySelect::$dbCache = true;
	}

	public function execute() {
		$this->testClauseOrdering();
		$this->testSelectClause();
		$this->testFromClause();
		$this->testJoinClauses();
		$this->testWhereClause();
		$this->testOrderby();
		$this->testGroupby();
		$this->testLimit();
		$this->testSQLCaching();
		$this->testDbCache();
		$this->testComment();
		$this->testIndentLevel();
		$this->testProperties();
	}

	public function finish() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$database->exec("DROP TABLE IF EXISTS `$table`");

		if($this->originalDbCache !== null) {
			DatabaseQuerySelect::$dbCache = $this->originalDbCache;
		}
		if($this->originalDebug !== null) {
			$this->wire()->config->debug = $this->originalDebug;
		}
	}

	protected function testClauseOrdering() {
		// Full query with all clauses in correct order
		$q = new DatabaseQuerySelect();
		$q->select("id, name")->from("pages")
			->join("t ON t.id=pages.id")
			->leftjoin("f ON f.pages_id=pages.id")
			->where("status>?", 0)
			->groupby("parent_id")
			->orderby("name")
			->limit(25);
		$sql = $q->getQuery();
		$selPos = stripos($sql, 'SELECT');
		$fromPos = stripos($sql, 'FROM');
		$joinPos = stripos($sql, 'JOIN');
		$ljPos = stripos($sql, 'LEFT JOIN');
		$wherePos = stripos($sql, 'WHERE');
		$groupByPos = stripos($sql, 'GROUP BY');
		$orderByPos = stripos($sql, 'ORDER BY');
		$limitPos = stripos($sql, 'LIMIT');
		$this->check('Clause order: SELECT before FROM', true, $selPos < $fromPos);
		$this->check('Clause order: FROM before JOIN', true, $fromPos < $joinPos);
		$this->check('Clause order: JOIN before LEFT JOIN', true, $joinPos < $ljPos);
		$this->check('Clause order: LEFT JOIN before WHERE', true, $ljPos < $wherePos);
		$this->check('Clause order: WHERE before GROUP BY', true, $wherePos < $groupByPos);
		$this->check('Clause order: GROUP BY before ORDER BY', true, $groupByPos < $orderByPos);
		$this->check('Clause order: ORDER BY before LIMIT', true, $orderByPos < $limitPos);

		// Query without optional clauses
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql = $q->getQuery();
		$this->check('Query with only SELECT and FROM', true, stripos($sql, 'SELECT') !== false && stripos($sql, 'FROM') !== false);
		$this->check('Query without WHERE omits it', false, stripos($sql, 'WHERE') !== false);
		$this->check('Query without ORDER BY omits it', false, stripos($sql, 'ORDER BY') !== false);
		$this->check('Query without LIMIT omits it', false, stripos($sql, 'LIMIT') !== false);
	}

	protected function testSelectClause() {
		// select() with comma-separated columns
		$q = new DatabaseQuerySelect();
		$q->select("id, name, status");
		$sql = $q->getSQL('select');
		$this->check('select() with comma-separated columns', true, strpos($sql, 'id, name, status') !== false);

		// select() appends on multiple calls
		$q = new DatabaseQuerySelect();
		$q->select("id")->select("name")->select("status");
		$sql = $q->getSQL('select');
		$this->check('select() appends on multiple calls', true, strpos($sql, 'id,name,status') !== false);

		// select() with count(*) alias
		$q = new DatabaseQuerySelect();
		$q->select("count(*) AS total");
		$sql = $q->getSQL('select');
		$this->check('select() preserves count(*) alias', true, strpos($sql, 'count(*) AS total') !== false);

		// SQL_CALC_FOUND_ROWS comes first in SELECT
		$q = new DatabaseQuerySelect();
		$q->select("SQL_CALC_FOUND_ROWS")->select("id")->select("name");
		$sql = $q->getSQL('select');
		$this->check('SQL_CALC_FOUND_ROWS precedes other columns', true, strpos($sql, 'SELECT SQL_CALC_FOUND_ROWS') === 0);
		$this->check('SQL_CALC_FOUND_ROWS includes other columns', true, strpos($sql, 'id') !== false && strpos($sql, 'name') !== false);

		// SQL_CALC_FOUND_ROWS not first in call order still comes first
		$q = new DatabaseQuerySelect();
		$q->select("id")->select("SQL_CALC_FOUND_ROWS")->select("name");
		$sql = $q->getSQL('select');
		$this->check('SQL_CALC_FOUND_ROWS moved to front regardless of call order', true, strpos($sql, 'SELECT SQL_CALC_FOUND_ROWS') === 0);
	}

	protected function testFromClause() {
		// from() backtick-quotes table names
		$q = new DatabaseQuerySelect();
		$q->from("pages");
		$sql = $q->getSQL('from');
		$this->check('from() backtick-quotes table name', true, strpos($sql, '`pages`') !== false);

		// from() multiple tables
		$q = new DatabaseQuerySelect();
		$q->from("pages")->from("pages_sortfields");
		$sql = $q->getSQL('from');
		$this->check('from() multiple tables both quoted', true, strpos($sql, '`pages`') !== false && strpos($sql, '`pages_sortfields`') !== false);
	}

	protected function testJoinClauses() {
		// join() adds JOIN
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->join("templates ON templates.id=pages.templates_id");
		$sql = $q->getQuery();
		$this->check('join() adds JOIN clause', 'JOIN', $sql, '*=');
		$this->check('join() does not add LEFT', false, stripos($sql, 'LEFT JOIN') !== false);

		// leftjoin() adds LEFT JOIN
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->leftjoin("field_title ON field_title.pages_id=pages.id");
		$sql = $q->getQuery();
		$this->check('leftjoin() adds LEFT JOIN clause', 'LEFT JOIN', $sql, '*=');

		// Multiple joins
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")
			->join("t1 ON t1.id=pages.id")
			->join("t2 ON t2.id=pages.id");
		$sql = $q->getQuery();
		$joinCount = substr_count(strtoupper($sql), 'JOIN') - substr_count(strtoupper($sql), 'LEFT JOIN');
		$this->check('Multiple join() calls add multiple JOINs', 2, $joinCount);
	}

	protected function testWhereClause() {
		// where() first call adds WHERE
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0);
		$sql = $q->getQuery();
		$this->check('where() first call adds WHERE', 'WHERE', $sql, '*=');

		// where() subsequent calls append AND
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("status>?", 0);
		$q->where("name=?", "hello");
		$sql = $q->getQuery();
		$this->check('where() second call appends AND', 'AND', $sql, '*=');

		// where(null) clears all WHERE conditions
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("status>?", 0)->where("name=?", "test");
		$q->where(null);
		$sql = $q->getQuery();
		$this->check('where(null) clears WHERE clause', false, stripos($sql, 'WHERE') !== false);
	}

	protected function testOrderby() {
		// orderby() single column
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->orderby("name");
		$sql = $q->getQuery();
		$this->check('orderby() adds ORDER BY', 'ORDER BY', $sql, '*=');
		$this->check('orderby() includes column name', 'name', $sql, '*=');

		// orderby() multiple calls append
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->orderby("name");
		$q->orderby("created DESC");
		$sql = $q->getQuery();
		$this->check('orderby() multiple calls append', true, strpos($sql, 'name') !== false && strpos($sql, 'created DESC') !== false);

		// orderby() with prepend=true
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->orderby("name");
		$q->orderby("created DESC");
		$q->orderby("priority", true);
		$sql = $q->getQuery();
		$priorityPos = strpos($sql, 'priority');
		$namePos = strpos($sql, 'name');
		$this->check('orderby(prepend=true) places column first', true, $priorityPos < $namePos);

		// orderby() with array
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->orderby(["name", "created DESC"]);
		$sql = $q->getQuery();
		$this->check('orderby(array) adds all columns', true, strpos($sql, 'name') !== false && strpos($sql, 'created DESC') !== false);

		// orderby(null) clears
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->orderby("name")->orderby("created DESC");
		$q->orderby(null);
		$sql = $q->getQuery();
		$this->check('orderby(null) clears ORDER BY', false, stripos($sql, 'ORDER BY') !== false);

		// orderby() with DatabaseQuerySelect object copies its orderby
		$a = new DatabaseQuerySelect();
		$a->orderby("name")->orderby("created DESC");
		$b = new DatabaseQuerySelect();
		$b->select("id")->from("pages");
		$b->orderby($a);
		$sql = $b->getQuery();
		$this->check('orderby(query) copies orderby from query object', true, strpos($sql, 'name') !== false && strpos($sql, 'created DESC') !== false);
	}

	protected function testGroupby() {
		// groupby() single column
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->groupby("parent_id");
		$sql = $q->getQuery();
		$this->check('groupby() adds GROUP BY', 'GROUP BY', $sql, '*=');
		$this->check('groupby() includes column', 'parent_id', $sql, '*=');

		// groupby() multiple columns
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->groupby("parent_id");
		$q->groupby("status");
		$sql = $q->getQuery();
		$this->check('groupby() multiple columns', true, strpos($sql, 'parent_id') !== false && strpos($sql, 'status') !== false);

		// groupby() HAVING shortcut
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->groupby("parent_id");
		$q->groupby("HAVING count>1");
		$sql = $q->getQuery();
		$this->check('groupby(HAVING) adds HAVING clause', 'HAVING', $sql, '*=');
		$this->check('groupby(HAVING) includes condition', 'count>1', $sql, '*=');

		// groupby() multiple HAVING combined with AND
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->groupby("parent_id");
		$q->groupby("HAVING count>1");
		$q->groupby("HAVING status=1");
		$sql = $q->getQuery();
		$this->check('groupby() multiple HAVING combined with AND', 'AND', $sql, '*=');

		// groupby() without any values omits GROUP BY
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql = $q->getQuery();
		$this->check('No groupby() omits GROUP BY', false, stripos($sql, 'GROUP BY') !== false);
	}

	protected function testLimit() {
		// limit() with integer
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->limit(25);
		$sql = $q->getQuery();
		$this->check('limit(25) adds LIMIT 25', 'LIMIT 25', $sql, '*=');

		// limit() with offset string
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->limit("0, 25");
		$sql = $q->getQuery();
		$this->check('limit("0, 25") adds LIMIT 0,25', 'LIMIT 0,25', $sql, '*=');

		// limit() with string integer
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->limit("50");
		$sql = $q->getQuery();
		$this->check('limit("50") adds LIMIT 50', 'LIMIT 50', $sql, '*=');

		// limit() with offset and limit string
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->limit("10, 5");
		$sql = $q->getQuery();
		$this->check('limit("10, 5") adds LIMIT 10,5', 'LIMIT 10,5', $sql, '*=');

		// No limit omits LIMIT clause
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql = $q->getQuery();
		$this->check('No limit() omits LIMIT clause', false, stripos($sql, 'LIMIT') !== false);
	}

	protected function testSQLCaching() {
		// getQuery() caches SQL
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql1 = $q->getQuery();
		$sql2 = $q->getQuery();
		$this->check('getQuery() returns same string on repeated calls', $sql1, $sql2);

		// Adding a clause invalidates cache
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql1 = $q->getQuery();
		$q->where("status>?", 0);
		$sql2 = $q->getQuery();
		$this->check('where() invalidates SQL cache', true, $sql1 !== $sql2);
		$this->check('Cached SQL includes new WHERE', 'WHERE', $sql2, '*=');

		// select() invalidates cache
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql1 = $q->getQuery();
		$q->select("name");
		$sql2 = $q->getQuery();
		$this->check('select() invalidates SQL cache', true, $sql1 !== $sql2);

		// set() on query method invalidates cache
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql1 = $q->getQuery();
		$q->set('where', ['status>0']);
		$sql2 = $q->getQuery();
		$this->check('set() on query method invalidates cache', true, $sql1 !== $sql2);
	}

	protected function testDbCache() {
		$origDbCache = DatabaseQuerySelect::$dbCache;

		// dbCache=false adds SQL_NO_CACHE
		DatabaseQuerySelect::$dbCache = false;
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql = $q->getSQL('select');
		$this->check('dbCache=false adds SQL_NO_CACHE', 'SQL_NO_CACHE', $sql, '*=');

		// dbCache=true omits SQL_NO_CACHE
		DatabaseQuerySelect::$dbCache = true;
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql = $q->getSQL('select');
		$this->check('dbCache=true omits SQL_NO_CACHE', false, stripos($sql, 'SQL_NO_CACHE') !== false);

		// dbCache=false with SQL_CALC_FOUND_ROWS
		DatabaseQuerySelect::$dbCache = false;
		$q = new DatabaseQuerySelect();
		$q->select("SQL_CALC_FOUND_ROWS")->select("id")->from("pages");
		$sql = $q->getSQL('select');
		$this->check('dbCache=false with SQL_CALC_FOUND_ROWS has both', true,
			strpos($sql, 'SQL_CALC_FOUND_ROWS') !== false && strpos($sql, 'SQL_NO_CACHE') !== false);

		DatabaseQuerySelect::$dbCache = $origDbCache;
	}

	protected function testComment() {
		$config = $this->wire()->config;
		$origDebug = $config->debug;

		// Comment appears in debug mode
		$config->debug = true;
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0);
		$q->set('comment', 'Fetch homepage children');
		$sql = $q->getQuery();
		$this->check('Comment appears in debug mode', 'Fetch homepage children', $sql, '*=');

		// Comment suppressed in non-debug mode
		$config->debug = false;
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->set('comment', 'Should not appear');
		$sql = $q->getQuery();
		$this->check('Comment suppressed in non-debug mode', false, strpos($sql, 'Should not appear') !== false);

		// No comment omits comment
		$config->debug = true;
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$sql = $q->getQuery();
		$this->check('No comment omits comment', false, strpos($sql, '/*') !== false);

		$config->debug = $origDebug;
	}

	protected function testIndentLevel() {
		$config = $this->wire()->config;
		$origDebug = $config->debug;

		// setIndentLevel() indents SQL in debug mode
		$config->debug = true;
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0);
		$q->setIndentLevel(2);
		$sql = $q->getQuery();
		$this->check('setIndentLevel(2) indents first line', "\t\t", $sql, '^=');

		// setIndentLevel(0) no indentation
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->setIndentLevel(0);
		$sql = $q->getQuery();
		$this->check('setIndentLevel(0) no indentation', false, strpos($sql, "\t") !== false);

		$config->debug = $origDebug;
	}

	protected function testProperties() {
		// select property returns array
		$q = new DatabaseQuerySelect();
		$q->select("id")->select("name");
		$this->check('select property returns array', true, is_array($q->select));
		$this->check('select property contains columns', 2, count($q->select));

		// from property returns array
		$q = new DatabaseQuerySelect();
		$q->from("pages");
		$this->check('from property returns array', true, is_array($q->from));

		// join property returns array
		$q = new DatabaseQuerySelect();
		$q->join("t ON t.id=pages.id");
		$this->check('join property returns array', true, is_array($q->join));

		// leftjoin property returns array
		$q = new DatabaseQuerySelect();
		$q->leftjoin("f ON f.pages_id=pages.id");
		$this->check('leftjoin property returns array', true, is_array($q->leftjoin));

		// where property returns array
		$q = new DatabaseQuerySelect();
		$q->where("status>?", 0);
		$this->check('where property returns array', true, is_array($q->where));

		// orderby property returns array
		$q = new DatabaseQuerySelect();
		$q->orderby("name");
		$this->check('orderby property returns array', true, is_array($q->orderby));

		// groupby property returns array
		$q = new DatabaseQuerySelect();
		$q->groupby("parent_id");
		$this->check('groupby property returns array', true, is_array($q->groupby));

		// limit property returns array
		$q = new DatabaseQuerySelect();
		$q->limit(25);
		$this->check('limit property returns array', true, is_array($q->limit));

		// comment property returns string
		$q = new DatabaseQuerySelect();
		$q->set('comment', 'test comment');
		$this->check('comment property returns string', 'test comment', $q->comment);
	}
}
