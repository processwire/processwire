<?php namespace ProcessWire;

/**
 * Tests for WireDatabaseSchemaReplay
 *
 * The replay works on statements only, so these tests need no database. WireDatabaseSchemaLog.test.php
 * checks a replayed table against MySQL's own SHOW CREATE TABLE.
 *
 */
class WireTest_WireDatabaseSchemaReplay extends WireTest {

	public function execute() {
		$this->testColumns();
		$this->testKeys();
		$this->testTables();
		$this->testFailures();
		$this->testIndexPrefixLengths();
	}

	/**
	 * Replay statements and return the CREATE TABLE of one table (or null) with whitespace collapsed
	 *
	 * @param array $statements
	 * @param string $table
	 * @return string|null
	 *
	 */
	protected function replay(array $statements, $table) {
		$replay = new WireDatabaseSchemaReplay();
		foreach($statements as $sql) $replay->apply($sql);
		$creates = $replay->getCreateTables();
		return isset($creates[$table]) ? preg_replace('/\s+/', ' ', $creates[$table]) : null;
	}

	protected function testColumns() {
		$create = "CREATE TABLE `t` (`pages_id` INT UNSIGNED NOT NULL, `data` TEXT NOT NULL, PRIMARY KEY (`pages_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
		$sql = $this->replay([$create, "ALTER TABLE t ADD COLUMN sort INT UNSIGNED NOT NULL DEFAULT 0 AFTER pages_id"], 't');
		$this->check('ADD COLUMN ... AFTER places the column', true, strpos($sql, '`pages_id` INT UNSIGNED NOT NULL, sort INT UNSIGNED NOT NULL DEFAULT 0, `data` TEXT') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t ADD `status` ENUM('on','off') NOT NULL FIRST"], 't');
		$this->check('ADD ... FIRST places the column first, keeping ENUM values', true, strpos($sql, "( `status` ENUM('on','off') NOT NULL, `pages_id`") !== false);
		$sql = $this->replay([$create, "ALTER TABLE t MODIFY data MEDIUMTEXT NOT NULL"], 't');
		$this->check('MODIFY replaces the definition in place', true, strpos($sql, '`pages_id` INT UNSIGNED NOT NULL, data MEDIUMTEXT NOT NULL, PRIMARY KEY') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t CHANGE pages_id pid INT UNSIGNED NOT NULL"], 't');
		$this->check('CHANGE renames the column in its keys too', true, strpos($sql, 'PRIMARY KEY (`pid`)') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t RENAME COLUMN data TO body"], 't');
		$this->check('RENAME COLUMN keeps the definition', true, strpos($sql, '`body` TEXT NOT NULL') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t ADD x INT, ADD y INT", "ALTER TABLE t DROP COLUMN x, DROP y"], 't');
		$this->check('several specs in one ALTER, and DROP [COLUMN]', false, strpos($sql, 'x INT') !== false || strpos($sql, 'y INT') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t ENGINE=MyISAM", "ALTER TABLE t COMMENT='hi'"], 't');
		$this->check('table options are replaced or added', true, strpos($sql, 'ENGINE=MyISAM DEFAULT CHARSET=utf8mb4') !== false && strpos($sql, "COMMENT='hi'") !== false);
	}

	protected function testKeys() {
		$create = "CREATE TABLE t (a INT NOT NULL, b VARCHAR(20), c INT, INDEX (a), UNIQUE (b))";
		$sql = $this->replay([$create], 't');
		$this->check('unnamed keys are named after their first column', true, strpos($sql, 'INDEX `a` (a)') !== false && strpos($sql, 'UNIQUE KEY `b` (b)') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t ADD KEY (a, c)"], 't');
		$this->check('an unnamed key whose name is taken gets _2', true, strpos($sql, 'KEY `a_2` (a, c)') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t CHANGE a d INT NOT NULL"], 't');
		$this->check('an unnamed key keeps its name when its column is renamed', true, strpos($sql, 'INDEX `a` (`d`)') !== false);
		$sql = $this->replay([$create, "ALTER TABLE t RENAME INDEX b TO bb", "ALTER TABLE t DROP INDEX a"], 't');
		$this->check('RENAME INDEX and DROP INDEX', true, strpos($sql, 'UNIQUE KEY `bb` (b)') !== false && strpos($sql, '`a` (a)') === false);
		$sql = $this->replay([$create, "CREATE INDEX ci ON t (c)", "CREATE UNIQUE INDEX cu ON t (c)", "DROP INDEX ci ON t"], 't');
		$this->check('CREATE [UNIQUE] INDEX ... ON and DROP INDEX ... ON', true, strpos($sql, 'UNIQUE KEY `cu` (c)') !== false && strpos($sql, '`ci`') === false);
		$sql = $this->replay([$create, "ALTER TABLE t ADD PRIMARY KEY (a)", "ALTER TABLE t DROP PRIMARY KEY, ADD PRIMARY KEY (a, c)"], 't');
		$this->check('DROP PRIMARY KEY and ADD PRIMARY KEY', true, strpos($sql, 'PRIMARY KEY (a, c)') !== false && substr_count($sql, 'PRIMARY KEY') === 1);
		$sql = $this->replay([$create, "ALTER TABLE t DROP COLUMN c"], 't');
		$this->check('DROP COLUMN leaves unrelated keys', true, strpos($sql, 'INDEX `a` (a)') !== false);
		$sql = $this->replay(["CREATE TABLE t (a INT, KEY ka (a))", "ALTER TABLE t ADD b INT", "ALTER TABLE t DROP COLUMN a"], 't');
		$this->check('DROP COLUMN drops its single-column keys', false, strpos($sql, 'ka'));
	}

	protected function testTables() {
		$this->check('CREATE TABLE IF NOT EXISTS', true, $this->replay(["CREATE TABLE IF NOT EXISTS `t` (a INT)"], 't') !== null);
		$this->check('temporary tables are ignored', null, $this->replay(["CREATE TEMPORARY TABLE t (a INT)"], 't'));
		$this->check('RENAME TABLE', [null, true], [
			$this->replay(["CREATE TABLE t (a INT)", "RENAME TABLE t TO u"], 't'),
			strpos((string) $this->replay(["CREATE TABLE t (a INT)", "RENAME TABLE t TO u"], 'u'), 'CREATE TABLE `u`') === 0,
		]);
		$this->check('ALTER TABLE ... RENAME TO', true, $this->replay(["CREATE TABLE t (a INT)", "ALTER TABLE t RENAME TO u"], 'u') !== null);
		$this->check('DROP TABLE', null, $this->replay(["CREATE TABLE t (a INT)", "DROP TABLE IF EXISTS t"], 't'));
		$this->check('a table created again after a DROP starts over', false, strpos($this->replay(["CREATE TABLE t (a INT)", "DROP TABLE t", "CREATE TABLE t (b INT)"], 't'), '(a INT)'));
	}

	protected function testFailures() {
		$replay = new WireDatabaseSchemaReplay();
		$replay->apply("CREATE TABLE t (a INT, b INT, KEY ab (a, b))");
		$replay->apply("CREATE TABLE u (a INT)");
		$this->check('a column of a multi-column key is not dropped by guessing', false, $replay->apply("ALTER TABLE t DROP COLUMN b"));
		$this->check('an unrecognized statement fails', false, $replay->apply("ALTER TABLE u PARTITION BY HASH(a)"));
		$this->check('an ALTER of an unknown table fails', false, $replay->apply("ALTER TABLE v ADD b INT"));
		$this->check('failed tables are left out of getCreateTables()', [], array_keys($replay->getCreateTables()));
		$this->check('failed tables are listed with a reason', ['t', 'u', 'v'], array_keys($replay->getFailedTables()));
		$replay->apply("DROP TABLE t");
		$replay->apply("CREATE TABLE t (a INT)");
		$this->check('a table created again after a DROP is no longer failed', ['t'], array_keys($replay->getCreateTables()));
	}

	protected function testIndexPrefixLengths() {
		$add = function($sql) { return preg_replace('/\s+/', ' ', WireDatabaseSchemaReplay::addIndexPrefixLengths($sql)); };
		$sql = $add("CREATE TABLE `x` (`a` text NOT NULL, `b` varchar(20), PRIMARY KEY (`a`), KEY `ab` (`a`,`b`), UNIQUE KEY `c` (`a`(50)), FULLTEXT KEY `f` (`a`)) ENGINE=InnoDB");
		$this->check('prefix length added to text columns in keys', true, strpos($sql, 'PRIMARY KEY (`a`(191))') !== false && strpos($sql, 'KEY `ab` (`a`(191),`b`)') !== false);
		$this->check('existing prefix length kept', true, strpos($sql, 'UNIQUE KEY `c` (`a`(50))') !== false);
		$this->check('FULLTEXT keys left alone', true, strpos($sql, 'FULLTEXT KEY `f` (`a`)') !== false);
		$this->check('table options kept', true, substr($sql, -15) === ') ENGINE=InnoDB');
		$sql = "CREATE TABLE `y` (`a` varchar(20), KEY `a` (`a`))";
		$this->check('a table without text keys is returned as given', $sql, WireDatabaseSchemaReplay::addIndexPrefixLengths($sql));
		$this->check('mediumblob counts as text', true, strpos($add("CREATE TABLE z (a MEDIUMBLOB, KEY a (a))"), '`a`(191)') !== false);
	}
}
