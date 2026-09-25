<?php namespace ProcessWire;

/**
 * Tests for WireDatabaseSchemaLog
 *
 * Uses the site's own schema log without removing it, since on a real site it is history that cannot
 * be recreated. Checks only the entries for this test's own tables.
 *
 */
class WireTest_WireDatabaseSchemaLog extends WireTest {

	protected $table = WireTests::fieldPrefix . 'schema_log_a';
	protected $renamed = WireTests::fieldPrefix . 'schema_log_b';
	protected $replayed = WireTests::fieldPrefix . 'schema_log_c';

	public function init() {
		$this->dropTables();
	}

	public function execute() {
		$this->testIsSchemaStatement();
		$this->testParse();
		$this->testRecording();
	}

	public function finish() {
		$this->dropTables();
	}

	protected function dropTables() {
		$database = $this->wire()->database;
		$database->exec("DROP TABLE IF EXISTS `$this->table`");
		$database->exec("DROP TABLE IF EXISTS `$this->renamed`");
		$database->exec("DROP TABLE IF EXISTS `$this->replayed`");
	}

	/**
	 * Check that replaying the log gives the table's MySQL definition
	 *
	 * @param string $table
	 *
	 */
	protected function testReplay($table) {
		$database = $this->wire()->database;
		$creates = $database->schemaLog()->getCreateTables();
		$this->check('replay includes the schema_log table itself', true, isset($creates['tables'][WireDatabaseSchemaLog::table]));
		$this->check('replay has the table', true, isset($creates['tables'][$table]));
		$this->check('replay has the table columns', ['id', 'name', 'qty', 'status'], $creates['columns'][$table]);
		if($database->dialect()->translatesSql()) return; // what follows needs MySQL's own SHOW CREATE TABLE
		// create the replayed definition under another name, and compare what MySQL makes of both
		$create = str_replace("CREATE TABLE `$table`", "CREATE TABLE `$this->replayed`", $creates['tables'][$table]);
		$database->schemaLog()->suspend();
		$database->exec($create);
		$database->schemaLog()->resume();
		$show = function($name) use($database) {
			$row = $database->query("SHOW CREATE TABLE `$name`")->fetch(\PDO::FETCH_NUM);
			return preg_replace('/ AUTO_INCREMENT=\d+/', '', str_replace("`$name`", '`t`', $row[1]));
		};
		$this->check('replayed CREATE TABLE is identical in MySQL', $show($table), $show($this->replayed));
	}

	protected function testIsSchemaStatement() {
		$is = function($sql) { return WireDatabaseSchemaLog::isSchemaStatement($sql); };
		foreach([
			'CREATE TABLE t (id INT)',
			"  create table if not exists `t` (id INT)",
			'ALTER TABLE t ADD x INT',
			'DROP TABLE t',
			'DROP TABLE IF EXISTS a, b',
			'RENAME TABLE a TO b',
			'CREATE INDEX i ON t (x)',
			'CREATE UNIQUE INDEX i ON t (x)',
			'CREATE FULLTEXT INDEX i ON t (x)',
			'DROP INDEX i ON t',
		] as $sql) {
			$this->check("is a schema change: $sql", true, $is($sql));
		}
		foreach([
			'SELECT * FROM t',
			'INSERT INTO t (x) VALUES (1)',
			'UPDATE t SET x=1',
			'DELETE FROM t',
			'CREATE TEMPORARY TABLE t (id INT)',
			'DROP TEMPORARY TABLE t',
			'SHOW CREATE TABLE t',
			'ANALYZE TABLE t',
			'TRUNCATE TABLE t',
			'CREATE TABLESPACE ts',
		] as $sql) {
			$this->check("is not a schema change: $sql", false, $is($sql));
		}
		$this->check('non-string is not a schema change', false, $is(null));
	}

	protected function testParse() {
		$log = $this->wire()->database->schemaLog();
		$ref = new \ReflectionMethod($log, 'parse');
		$ref->setAccessible(true);
		$parse = function($sql) use($log, $ref) { return $ref->invoke($log, $sql); };

		$p = $parse('CREATE TABLE IF NOT EXISTS `db`.`t1` (id INT)');
		$this->check('CREATE TABLE with IF NOT EXISTS and db-qualified name', ['create', ['t1']], [$p['type'], $p['tables']]);
		$p = $parse('ALTER TABLE `t1` ADD x INT');
		$this->check('ALTER TABLE', ['alter', ['t1']], [$p['type'], $p['tables']]);
		$p = $parse('ALTER TABLE t1 RENAME COLUMN a TO b');
		$this->check('ALTER TABLE ... RENAME COLUMN is not a table rename', ['alter', ['t1']], [$p['type'], $p['tables']]);
		$p = $parse('ALTER TABLE t1 RENAME TO t2');
		$this->check('ALTER TABLE ... RENAME TO is a table rename', ['rename', ['t1' => 't2']], [$p['type'], $p['renames']]);
		$p = $parse('DROP TABLE IF EXISTS `a`, b, `db`.`c`');
		$this->check('DROP TABLE with several tables', ['drop', ['a', 'b', 'c']], [$p['type'], $p['tables']]);
		$p = $parse('RENAME TABLE a TO b, `c` TO `d`');
		$this->check('RENAME TABLE with several pairs', ['rename', ['a' => 'b', 'c' => 'd']], [$p['type'], $p['renames']]);
		$p = $parse('CREATE UNIQUE INDEX i ON `t1` (x)');
		$this->check('CREATE INDEX ... ON table', ['index', ['t1']], [$p['type'], $p['tables']]);
		$p = $parse('DROP INDEX i ON t1');
		$this->check('DROP INDEX ... ON table', ['index', ['t1']], [$p['type'], $p['tables']]);
	}

	protected function testRecording() {
		$database = $this->wire()->database;
		$log = $database->schemaLog();
		$a = $this->table;
		$b = $this->renamed;
		$entries = function($table) use($log) {
			$sql = [];
			foreach($log->getEntries() as $entry) if($entry['table'] === $table) $sql[] = $entry['sql'];
			return $sql;
		};
		$has = function(array $sqls, $text) {
			foreach($sqls as $sql) if(stripos($sql, $text) !== false) return true;
			return false;
		};

		$database->exec("CREATE TABLE `$a` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(40) NOT NULL, PRIMARY KEY (`id`))");
		$this->check('schema_log table exists after a schema change', true, $database->tableExists(WireDatabaseSchemaLog::table));
		$this->check('schema_log does not record its own table', [], $entries(WireDatabaseSchemaLog::table));
		$created = $entries($a);
		$this->check('CREATE TABLE is recorded (as a change, or in the baseline if it started the log)', true, count($created) === 1);

		$database->exec("ALTER TABLE `$a` ADD `qty` INT UNSIGNED NOT NULL DEFAULT 0");
		$this->check('ALTER TABLE via exec() is recorded in MySQL syntax', true, $has($entries($a), 'ADD `qty` INT UNSIGNED'));

		$query = $database->prepare("ALTER TABLE `$a` ADD `status` ENUM('on','off') NOT NULL DEFAULT 'on'");
		$this->check('prepared ALTER TABLE is not recorded before it executes', false, $has($entries($a), '`status`'));
		$query->execute();
		$this->check('prepared ALTER TABLE is recorded once executed, keeping ENUM values', true, $has($entries($a), "ENUM('on','off')"));

		$this->testReplay($a);

		$before = count($entries($a));
		try {
			$database->exec("ALTER TABLE `$a` ADD `name` INT"); // duplicate column, fails
		} catch(\Exception $e) {
			// expected
		}
		$this->check('failed ALTER TABLE is not recorded', $before, count($entries($a)));

		$database->exec("RENAME TABLE `$a` TO `$b`");
		$this->check('RENAME TABLE moves earlier entries to the new name', [], $entries($a));
		$this->check('RENAME TABLE keeps the history under the new name', $before + 1, count($entries($b)));
		$this->check('RENAME TABLE itself is recorded', true, $has($entries($b), 'RENAME TABLE'));

		$database->exec("DROP TABLE `$b`");
		$this->check('DROP TABLE removes the table from the log', [], $entries($b));

		$log->suspend();
		$database->exec("CREATE TABLE `$a` (`id` INT NOT NULL)");
		$log->resume();
		$this->check('changes are not recorded while suspended', [], $entries($a));
	}
}
