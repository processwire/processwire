<?php namespace ProcessWire;

/**
 * Tests for WireDatabaseBackup
 *
 */
class WireTest_WireDatabaseBackup extends WireTest {

	public function execute() {
		$this->testPreflight();
	}

	/**
	 * preflight(): data of a MySQL database that PostgreSQL would reject or change (live, mysql only)
	 *
	 */
	protected function testPreflight() {
		$database = $this->wire()->database;
		$backup = $database->backups();
		if($database->dialect()->name() !== 'mysql') {
			$error = '';
			try {
				$backup->preflight('pgsql');
			} catch(\Exception $e) {
				$error = $e->getMessage();
			}
			$this->check('preflight() checks a MySQL database', true, strpos($error, 'MySQL') !== false);
			return;
		}
		$table = WireTests::fieldPrefix . 'preflight';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("CREATE TABLE `$table` (`id` int unsigned NOT NULL, `u` int unsigned, `s` smallint unsigned, `b` bigint unsigned, " .
			"`m` mediumint unsigned, `t` text CHARACTER SET utf8mb4, `l` text CHARACTER SET latin1, `bl` blob, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$database->exec("INSERT INTO `$table` VALUES (1, 1, 1, 1, 16777215, 'fine', 'fine', 'x')");
		$database->exec("INSERT INTO `$table` VALUES (2, 3000000000, 40000, 18446744073709551615, 1, CONCAT('a', CHAR(0), 'b'), 'caf\xC3\xA9', CONCAT('a', CHAR(0)))");
		$database->exec("INSERT INTO `$table` VALUES (3, 2147483648, 32767, 9223372036854775807, 1, 'fine', 'plain', NULL)");

		$findings = $backup->preflight('pgsql', ['tables' => [$table]]);
		$summary = [];
		foreach($findings as $f) $summary[] = [$f['column'], $f['check'], $f['level'], $f['rows'], $f['example']];
		$this->check('preflight() for PostgreSQL finds each problem, with its rows and an example key', [
			['u', 'range', 'error', 2, ['id' => 2]],
			['s', 'range', 'error', 1, ['id' => 2]],
			['b', 'range', 'error', 1, ['id' => 2]],
			['t', 'nul', 'error', 1, ['id' => 2]],
			['l', 'charset', 'warning', 1, ['id' => 2]],
		], $summary);
		$this->check('each finding names its table', [$table], array_values(array_unique(array_column($findings, 'table'))));
		$this->check('each finding says what to do', true, count(array_filter($findings, function($f) { return strlen($f['message']) > 40; })) === count($findings));
		$this->check('mediumint unsigned fits PostgreSQL integer, and a blob with a NUL byte is bytea', false, count(array_filter($findings, function($f) { return in_array($f['column'], ['m', 'bl'], true); })) > 0);
		$this->check('preflight() for SQLite finds nothing (SQLite stores all of these)', [], $backup->preflight('sqlite', ['tables' => [$table]]));
		$all = $backup->preflight('pgsql');
		$this->check('preflight() checks all tables by default', true, count(array_filter($all, function($f) use($table) { return $f['table'] === $table; })) === 5);
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}
}
