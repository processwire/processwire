<?php namespace ProcessWire;

/**
 * Tests for WireDatabaseDialect SQL helpers
 *
 * The dialect-specific checks construct each dialect directly, so they run regardless of
 * which database the test site uses. The live checks run against the site's own database.
 *
 */
class WireTest_WireDatabaseDialect extends WireTest {

	protected $table = WireTests::fieldPrefix . 'dialect_upsert';

	public function init() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$charset = $database->escapeTable($this->wire()->config->dbCharset ?: 'utf8mb4');
		$database->exec(
			"CREATE TABLE `$table` (" .
			"`pages_id` INT UNSIGNED NOT NULL, " .
			"`sort` INT UNSIGNED NOT NULL DEFAULT 0, " .
			"`data` VARCHAR(64) NOT NULL DEFAULT '', " .
			"`qty` INT NOT NULL DEFAULT 0, " .
			"PRIMARY KEY (`pages_id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=$charset"
		);
	}

	public function execute() {
		$this->testUpsertMySQL();
		$this->testUpsertSQLite();
		$this->testUpsertLive();
	}

	public function finish() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}

	/**
	 * MySQL dialect: output is pinned so that migrated core call sites keep issuing the same SQL
	 *
	 */
	protected function testUpsertMySQL() {

		$dialect = new WireDatabaseDialectMySQL($this->wire()->database);

		$this->check('MySQL upsert() binds list columns by column name',
			'INSERT INTO `t` (`pages_id`, `data`) VALUES (:pages_id, :data) ' .
			'ON DUPLICATE KEY UPDATE `data`=VALUES(`data`)',
			$dialect->upsert('t', ['pages_id', 'data'], ['data'], ['conflict' => ['pages_id']])
		);

		$this->check('MySQL upsert() ignores conflict target',
			$dialect->upsert('t', ['pages_id', 'data'], ['data'], ['conflict' => ['pages_id']]),
			$dialect->upsert('t', ['pages_id', 'data'], ['data'])
		);

		$this->check('MySQL upsert() accepts column => expression for inserted values',
			'INSERT INTO `modules` (`class`, `data`, `flags`) VALUES (:name, :data, :flags) ' .
			'ON DUPLICATE KEY UPDATE `data`=VALUES(`data`)',
			$dialect->upsert('modules', ['class' => ':name', 'data' => ':data', 'flags' => ':flags'], ['data'], ['conflict' => ['class']])
		);

		$this->check('MySQL upsert() accepts NULL and literal expressions',
			"INSERT INTO `t` (`pages_id`, `data`) VALUES (:page_id, NULL) " .
			"ON DUPLICATE KEY UPDATE `data`=VALUES(`data`)",
			$dialect->upsert('t', ['pages_id' => ':page_id', 'data' => 'NULL'], ['data'])
		);

		$this->check('MySQL upsert() accepts column => expression for updates',
			'INSERT INTO `s` (`id`, `data`) VALUES (:id, :data) ' .
			'ON DUPLICATE KEY UPDATE `data`=VALUES(`data`), `ts`=NOW()',
			$dialect->upsert('s', ['id', 'data'], ['data', 'ts' => 'NOW()'], ['conflict' => ['id']])
		);

		$this->check('MySQL upsert() inserts multiple rows',
			'INSERT INTO `pages` (`id`, `sort`) VALUES (1, 5), (2, 6) ' .
			'ON DUPLICATE KEY UPDATE `sort`=VALUES(`sort`)',
			$dialect->upsert('pages', ['id', 'sort'], ['sort'], ['conflict' => ['id'], 'rows' => [[1, 5], [2, 6]]])
		);

		$this->check('MySQL upsert() throws when nothing to update', true, $this->throws(function() use($dialect) {
			$dialect->upsert('t', ['pages_id', 'data'], []);
		}));

		$this->check('MySQL upsert() throws when no columns', true, $this->throws(function() use($dialect) {
			$dialect->upsert('t', [], ['data']);
		}));

		$this->check('MySQL upsert() throws when row length differs from columns', true, $this->throws(function() use($dialect) {
			$dialect->upsert('t', ['id', 'sort'], ['sort'], ['rows' => [[1, 5], [2]]]);
		}));

		$this->check('MySQL upsert() throws when update column is not inserted', true, $this->throws(function() use($dialect) {
			$dialect->upsert('t', ['pages_id', 'data'], ['other']);
		}));

		$this->check('MySQL upsert() rows quote strings and render null and bool',
			"INSERT INTO `t` (`id`, `name`, `flag`, `note`) VALUES (1, 'O\\'Reilly', 1, NULL) " .
			'ON DUPLICATE KEY UPDATE `name`=VALUES(`name`)',
			$dialect->upsert('t', ['id', 'name', 'flag', 'note'], ['name'], ['rows' => [[1, "O'Reilly", true, null]]])
		);

		$this->check('MySQL upsert() rows reject non-scalar values', true, $this->throws(function() use($dialect) {
			$dialect->upsert('t', ['id', 'sort'], ['sort'], ['rows' => [[1, ['not', 'scalar']]]]);
		}));

		$this->check('MySQL upsert() rows do not treat strings as SQL expressions',
			"INSERT INTO `t` (`id`, `ts`) VALUES (1, 'NOW()') ON DUPLICATE KEY UPDATE `ts`=VALUES(`ts`)",
			$dialect->upsert('t', ['id', 'ts'], ['ts'], ['rows' => [[1, 'NOW()']]])
		);

		$this->check('MySQL upsert() sanitizes identifiers',
			'INSERT INTO `bad_table` (`bad_col`) VALUES (:bad_col) ON DUPLICATE KEY UPDATE `bad_col`=VALUES(`bad_col`)',
			$dialect->upsert('bad`table', ['bad`col'], ['bad`col'])
		);
	}

	/**
	 * SQLite dialect: native ON CONFLICT with the conflict target that the translator cannot infer
	 *
	 */
	protected function testUpsertSQLite() {

		$dialect = new WireDatabaseDialectSQLite($this->wire()->database);

		$this->check('SQLite upsert() emits ON CONFLICT with conflict target',
			'INSERT INTO `t` (`pages_id`, `data`) VALUES (:pages_id, :data) ' .
			'ON CONFLICT (`pages_id`) DO UPDATE SET `data`=excluded.`data`',
			$dialect->upsert('t', ['pages_id', 'data'], ['data'], ['conflict' => ['pages_id']])
		);

		$this->check('SQLite upsert() emits multi-column conflict target',
			'INSERT INTO `p` (`pages_id`, `language_id`, `path`) VALUES (:pages_id, :language_id, :path) ' .
			'ON CONFLICT (`pages_id`, `language_id`) DO UPDATE SET `path`=excluded.`path`',
			$dialect->upsert('p', ['pages_id', 'language_id', 'path'], ['path'], ['conflict' => ['pages_id', 'language_id']])
		);

		$this->check('SQLite upsert() omits conflict target when not given',
			'INSERT INTO `t` (`pages_id`, `data`) VALUES (:pages_id, :data) ' .
			'ON CONFLICT DO UPDATE SET `data`=excluded.`data`',
			$dialect->upsert('t', ['pages_id', 'data'], ['data'])
		);

		$this->check('SQLite upsert() passes update expressions through',
			'INSERT INTO `s` (`id`, `data`) VALUES (:id, :data) ' .
			'ON CONFLICT (`id`) DO UPDATE SET `data`=excluded.`data`, `ts`=NOW()',
			$dialect->upsert('s', ['id', 'data'], ['data', 'ts' => 'NOW()'], ['conflict' => ['id']])
		);

		$this->check('SQLite upsert() inserts multiple rows',
			'INSERT INTO `pages` (`id`, `sort`) VALUES (1, 5), (2, 6) ' .
			'ON CONFLICT (`id`) DO UPDATE SET `sort`=excluded.`sort`',
			$dialect->upsert('pages', ['id', 'sort'], ['sort'], ['conflict' => ['id'], 'rows' => [[1, 5], [2, 6]]])
		);
	}

	/**
	 * Live checks on the test site's own database
	 *
	 */
	protected function testUpsertLive() {

		$database = $this->wire()->database;
		$dialect = $database->dialect();
		$table = $database->escapeTable($this->table);
		$conflict = ['conflict' => ['pages_id']];

		// insert, then update the same row
		$sql = $dialect->upsert($table, ['pages_id', 'data', 'qty'], ['data', 'qty'], $conflict);
		foreach([['a', 1], ['b', 2]] as $n => $values) {
			$query = $database->prepare($sql);
			$query->bindValue(':pages_id', 1, \PDO::PARAM_INT);
			$query->bindValue(':data', $values[0]);
			$query->bindValue(':qty', $values[1], \PDO::PARAM_INT);
			$this->check("live upsert() executes (run $n)", true, $query->execute());
		}
		$row = $this->row(1);
		$this->check('live upsert() leaves one row for the key', 1, $this->count());
		$this->check('live upsert() updated data column', 'b', $row['data']);
		$this->check('live upsert() updated qty column', 2, (int) $row['qty']);

		// update with an expression rather than the inserted value
		$sql = $dialect->upsert($table, ['pages_id', 'data'], ['qty' => 'qty+1'], $conflict);
		for($n = 0; $n < 2; $n++) {
			$query = $database->prepare($sql);
			$query->bindValue(':pages_id', 2, \PDO::PARAM_INT);
			$query->bindValue(':data', "c$n");
			$this->check("live upsert() with expression executes (run $n)", true, $query->execute());
		}
		$row = $this->row(2);
		$this->check('live upsert() expression update incremented qty', 1, (int) $row['qty']);
		$this->check('live upsert() expression update left data as inserted', 'c0', $row['data']);

		// update from a separate bound value
		$sql = $dialect->upsert($table, ['pages_id' => ':pid', 'data' => ':d'], ['data' => ':d2'], $conflict);
		$query = $database->prepare($sql);
		$query->bindValue(':pid', 1, \PDO::PARAM_INT);
		$query->bindValue(':d', 'ignored');
		$query->bindValue(':d2', 'from-d2');
		$this->check('live upsert() with bound update expression executes', true, $query->execute());
		$this->check('live upsert() bound update expression applied', 'from-d2', $this->row(1)['data']);

		// multiple rows, mixing an existing and a new key
		$sql = $dialect->upsert($table, ['pages_id', 'sort'], ['sort'], $conflict + ['rows' => [[1, 10], [3, 30]]]);
		$this->check('live upsert() multi-row executes', true, $database->exec($sql) !== false);
		$this->check('live upsert() multi-row updated existing row', 10, (int) $this->row(1)['sort']);
		$this->check('live upsert() multi-row kept other columns of existing row', 'from-d2', $this->row(1)['data']);
		$this->check('live upsert() multi-row inserted new row', 30, (int) $this->row(3)['sort']);
		$this->check('live upsert() total rows', 3, $this->count());
	}

	/**
	 * @param int $pagesId
	 * @return array
	 *
	 */
	protected function row($pagesId) {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$query = $database->prepare("SELECT pages_id, sort, data, qty FROM `$table` WHERE pages_id=:pages_id");
		$query->bindValue(':pages_id', $pagesId, \PDO::PARAM_INT);
		$query->execute();
		$row = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		return is_array($row) ? $row : [];
	}

	/**
	 * @return int
	 *
	 */
	protected function count() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		return (int) $database->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
	}

	/**
	 * Does the given callable throw a WireDatabaseException?
	 *
	 * @param callable $func
	 * @return bool
	 *
	 */
	protected function throws($func) {
		try {
			$func();
		} catch(WireDatabaseException $e) {
			return true;
		}
		return false;
	}
}
