<?php namespace ProcessWire;

/**
 * Tests for the PostgreSQL dialect
 *
 * Construction-only checks run on any database; live checks run when the site uses pgsql.
 *
 */
class WireTest_WireDatabaseDialectPgsql extends WireTest {

	public function execute() {
		$this->testConnectionConfig();
		$this->testCapabilitiesAndUpsert();
		$this->testErrorMapping();
		$this->testLive();
	}

	protected function testConnectionConfig() {
		$config = $this->wire(new Config());
		$config->dbName = 'pwtest';
		$config->dbUser = 'u';
		$config->dbPass = 'p';
		$config->dbHost = 'localhost';
		$config->dbPort = 5432;
		$config->dbSocket = '';
		$config->dbOptions = [];
		$data = WireDatabaseDialectPgsql::connectionConfig($config, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$this->check('DSN uses host and port', 'pgsql:host=localhost;port=5432;dbname=pwtest', $data['dsn']);
		$this->check('user passed', 'u', $data['user']);
		$this->check('password passed', 'p', $data['pass']);
		$this->check('fetches are stringified by default (MySQL returns strings)', true, $data['options'][\PDO::ATTR_STRINGIFY_FETCHES]);
		$this->check('given options preserved', \PDO::ERRMODE_EXCEPTION, $data['options'][\PDO::ATTR_ERRMODE]);
		$config->dbSocket = '/tmp';
		$data = WireDatabaseDialectPgsql::connectionConfig($config, []);
		$this->check('socket directory becomes host, with port (socket files are named by port)', 'pgsql:host=/tmp;port=5432;dbname=pwtest', $data['dsn']);
		$config->dbPort = '';
		$data = WireDatabaseDialectPgsql::connectionConfig($config, []);
		$this->check('socket directory without port', 'pgsql:host=/tmp;dbname=pwtest', $data['dsn']);
		$data = WireDatabaseDialectPgsql::connectionConfig($config, [\PDO::ATTR_STRINGIFY_FETCHES => false, 'pgsql' => ['schema' => 'pw']]);
		$this->check('stringify can be turned off', false, $data['options'][\PDO::ATTR_STRINGIFY_FETCHES]);
		$this->check('pgsql settings array is not passed to PDO', false, isset($data['options']['pgsql']));
		$this->check('dialectClass resolves pgsql', 'ProcessWire\\WireDatabaseDialectPgsql', WireDatabasePDO::dialectClass('pgsql'));
		$this->check('dialectClass resolves postgresql alias', 'ProcessWire\\WireDatabaseDialectPgsql', WireDatabasePDO::dialectClass('postgresql'));
	}

	protected function testCapabilitiesAndUpsert() {
		$dialect = new WireDatabaseDialectPgsql($this->wire()->database);
		$this->check('name', 'pgsql', $dialect->name());
		$this->check('translates SQL', true, $dialect->translatesSql());
		$this->check('no FOUND_ROWS', false, $dialect->supportsFoundRows());
		$this->check('no fulltext (M1)', false, $dialect->supportsFulltext());
		$this->check('no UPDATE ORDER BY', false, $dialect->supportsUpdateOrderBy());
		$this->check('JSON supported', true, $dialect->supportsJson());
		$this->check('transactions supported', true, $dialect->supportsTransaction('x'));
		$this->check('no compare collation (M1)', '', $dialect->compareCollation('Äpfel'));
		$this->check('no sort collation (M1)', '', $dialect->sortCollation());
		$this->check('quote() is MySQL-style for the translator', "'a\\'b'", $dialect->quote("a'b"));
		$this->check('quoteIdentifier uses double quotes', '"data"', $dialect->quoteIdentifier('data'));
		$this->check('translateSql returns statements', ['SELECT 1 FROM "t"'], $dialect->translateSql('SELECT 1 FROM `t`'));
		$this->check('upsert() emits ON CONFLICT with target',
			'INSERT INTO "t" ("pages_id", "data") VALUES (:pages_id, :data) ON CONFLICT ("pages_id") DO UPDATE SET "data"=excluded."data"',
			$dialect->upsert('t', ['pages_id', 'data'], ['data'], ['conflict' => ['pages_id']]));
		$this->check('upsert() multi-column target and expression update',
			'INSERT INTO "s" ("id", "lang") VALUES (:id, :lang) ON CONFLICT ("id", "lang") DO UPDATE SET "lang"=excluded."lang", "ts"=now()',
			$dialect->upsert('s', ['id', 'lang'], ['lang', 'ts' => 'now()'], ['conflict' => ['id', 'lang']]));
		$this->check('upsert() output passes through the translator unchanged',
			$dialect->upsert('t', ['a', 'b'], ['b'], ['conflict' => ['a']]),
			$dialect->translateSql($dialect->upsert('t', ['a', 'b'], ['b'], ['conflict' => ['a']]))[0]);
		$threw = false;
		try {
			$dialect->upsert('nonexistent_table_xyz', ['a', 'b'], ['b']);
		} catch(WireDatabaseException $e) {
			$threw = strpos($e->getMessage(), 'conflict target') !== false;
		}
		$this->check('upsert() without target and without schema throws', true, $threw);
		$this->check('sqlMode get is blank', '', $dialect->sqlMode('get'));
		$this->check('sqlMode set is accepted', true, $dialect->sqlMode('set', 'STRICT_ALL_TABLES'));
		$this->check('getServerType', 'PostgreSQL', $dialect->getServerType());
		$this->check('getRegexEngine', 'POSIX', $dialect->getRegexEngine());
		$this->check('getMaxIndexLength', 250, $dialect->getMaxIndexLength());
		$this->check('getVariable ft_min_word_len', '1', $dialect->getVariable('ft_min_word_len'));
		$this->check('getVariable unknown is null', null, $dialect->getVariable('no_such_variable'));
	}

	protected function testErrorMapping() {
		$make = function($state, $message) {
			$e = new \PDOException($message);
			$e->errorInfo = [$state, 7, $message];
			return $e;
		};
		$e = WireDatabaseDialectPgsql::mysqlException($make('42P01', 'SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation "foo" does not exist'));
		$this->check('undefined table maps to 42S02', '42S02', $e->errorInfo[0]);
		$this->check('undefined table errorCode() is 42S02', '42S02', $e->getCode());
		$this->check('undefined table message names table', true, strpos($e->getMessage(), "'foo'") !== false);
		$e = WireDatabaseDialectPgsql::mysqlException($make('42703', 'SQLSTATE[42703]: Undefined column: 7 ERROR:  column "bar" does not exist'));
		$this->check('undefined column maps to 42S22', '42S22', $e->errorInfo[0]);
		$this->check('undefined column message names column', true, strpos($e->getMessage(), "'bar'") !== false);
		$e = WireDatabaseDialectPgsql::mysqlException($make('42703', 'SQLSTATE[42703]: Undefined column: 7 ERROR:  column t.bar does not exist'));
		$this->check('undefined qualified column names column', true, strpos($e->getMessage(), "'t.bar'") !== false);
		$e = WireDatabaseDialectPgsql::mysqlException($make('23505', 'SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "pages__name_parent_id"'));
		$this->check('unique violation maps to 23000', '23000', $e->errorInfo[0]);
		$this->check('unique violation errno is 1062', 1062, $e->errorInfo[1]);
		$e = WireDatabaseDialectPgsql::mysqlException($make('42P07', 'ERROR:  relation "t" already exists'));
		$this->check('duplicate table maps to 42S01', '42S01', $e->errorInfo[0]);
		$e = WireDatabaseDialectPgsql::mysqlException($make('42701', 'ERROR:  column "x" of relation "t" already exists'));
		$this->check('duplicate column maps to 42S21', '42S21', $e->errorInfo[0]);
		$plain = $make('22P02', 'invalid input syntax');
		$this->check('other errors pass through unchanged', true, $plain === WireDatabaseDialectPgsql::mysqlException($plain));
		$dialect = new WireDatabaseDialectPgsql($this->wire()->database);
		$this->check('deadlock is retryable', 'deadlock', $dialect->getRetryableErrorType($make('40P01', 'deadlock detected')));
		$this->check('serialization failure is retryable', 'deadlock', $dialect->getRetryableErrorType($make('40001', 'could not serialize')));
		$this->check('admin shutdown is retryable as gone-away', 'gone-away', $dialect->getRetryableErrorType($make('57P01', 'terminating connection')));
		$this->check('connection exception is retryable as comm-failure', 'comm-failure', $dialect->getRetryableErrorType($make('08006', 'connection failure')));
		$this->check('other errors not retryable', '', $dialect->getRetryableErrorType($make('23505', 'dup')));
	}

	protected function testLive() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$table = WireTests::fieldPrefix . 'pgsql_dialect';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("DROP TABLE IF EXISTS `{$table}_2`");
		$database->exec("DROP TABLE IF EXISTS `{$table}_3`");
		$database->exec("CREATE TABLE `$table` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(64) NOT NULL, `qty` INT NOT NULL DEFAULT 0, PRIMARY KEY (`id`), KEY `name_idx` (`name`), UNIQUE KEY `uq` (`name`, `qty`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$this->check('tableExists', true, $database->tableExists($table));
		$this->check('tableExists false for missing', false, $database->tableExists($table . '_missing'));
		$this->check('getTables includes table', true, in_array($table, $database->getTables(false), true));
		$cols = $database->getColumns($table, true);
		$this->check('getColumns names', ['id', 'name', 'qty'], array_keys($cols));
		$this->check('getColumns type for varchar', 'varchar(64)', $cols['name']['type']);
		$this->check('getColumns int type', 'int', $cols['qty']['type']);
		$this->check('getColumns null flag', false, $cols['name']['null']);
		$this->check('getColumns extra auto_increment', 'auto_increment', $cols['id']['extra']);
		$this->check('getColumns plain list', ['id', 'name', 'qty'], $database->getColumns($table));
		$this->check('columnExists true', true, $database->columnExists($table, 'qty'));
		$this->check('columnExists false', false, $database->columnExists($table, 'nope'));
		$idx = $database->getIndexes($table, true);
		$this->check('getIndexes has PRIMARY', true, isset($idx['PRIMARY']) && $idx['PRIMARY']['columns'] === ['id']);
		$this->check('getIndexes de-prefixes names', true, isset($idx['name_idx']) && $idx['name_idx']['unique'] === false);
		$this->check('getIndexes unique multi-column', ['name', 'qty'], isset($idx['uq']) ? $idx['uq']['columns'] : null);
		$this->check('indexExists', true, $database->indexExists($table, 'uq'));
		$this->check('indexExists false', false, $database->indexExists($table, 'nope'));
		$this->check('getPrimaryKey', 'id', $database->getPrimaryKey($table));

		$q = $database->prepare("INSERT INTO `$table` (name, qty) VALUES (:n, :q)");
		$q->bindValue(':n', 'a');
		$q->bindValue(':q', 1, \PDO::PARAM_INT);
		$q->execute();
		$id = (int) $database->lastInsertId();
		$this->check('lastInsertId works without a sequence name', true, $id > 0);
		$row = $database->query("SELECT id, qty FROM `$table` WHERE id=$id")->fetch(\PDO::FETCH_ASSOC);
		$this->check('fetched values are strings (STRINGIFY_FETCHES)', 'string', gettype($row['qty']));
		$this->check('rowCount after SELECT', 1, $database->query("SELECT id FROM `$table`")->rowCount());
		$this->check('getTime returns datetime string', true, (bool) preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $database->getTime()));
		$this->check('getTime timestamp is near now', true, abs(time() - $database->getTime(true)) < 5);
		$this->check('getVersion is 16+', true, version_compare($database->getVersion(true), '16.0', '>='));

		// dialect upsert with explicit conflict target
		$sql = $database->dialect()->upsert($table, ['id', 'name', 'qty'], ['qty'], ['conflict' => ['id']]);
		$q = $database->prepare($sql);
		$q->bindValue(':id', $id, \PDO::PARAM_INT);
		$q->bindValue(':name', 'a');
		$q->bindValue(':qty', 5, \PDO::PARAM_INT);
		$q->execute();
		$this->check('upsert updated qty', 5, (int) $database->query("SELECT qty FROM `$table` WHERE id=$id")->fetchColumn());
		// dialect upsert with target found by introspection
		$sql = $database->dialect()->upsert($table, ['id', 'name', 'qty'], ['qty']);
		$this->check('upsert without target introspects the primary key', true, strpos($sql, 'ON CONFLICT ("id")') !== false);
		// translated ON DUPLICATE KEY (third-party style) with introspected key
		$database->exec("INSERT INTO `$table` (id, name, qty) VALUES ($id, 'a', 6) ON DUPLICATE KEY UPDATE qty=VALUES(qty)");
		$this->check('translated ON DUPLICATE KEY UPDATE works', 6, (int) $database->query("SELECT qty FROM `$table` WHERE id=$id")->fetchColumn());
		// explicit id then implicit id
		$database->exec("INSERT INTO `$table` (id, name, qty) VALUES (500, 'x', 0)");
		$database->exec("INSERT INTO `$table` (name, qty) VALUES ('y', 0)");
		$this->check('implicit insert after explicit id gets id > 500', true, (int) $database->lastInsertId() > 500);

		// MySQL error codes surface
		$state = '';
		try { $database->query("SELECT nope FROM `$table`"); } catch(\PDOException $e) { $state = $e->errorInfo[0]; }
		$this->check('unknown column surfaces as 42S22', '42S22', $state);
		$state = '';
		try { $database->exec("INSERT INTO `$table` (id, name, qty) VALUES ($id, 'a', 6)"); } catch(\PDOException $e) { $state = $e->errorInfo[0]; }
		$this->check('duplicate key surfaces as 23000', '23000', $state);
		$state = '';
		try { $q = $database->prepare("SELECT nope FROM `{$table}_missing`"); $q->execute(); } catch(\PDOException $e) { $state = $e->errorInfo[0]; }
		$this->check('unknown table surfaces as 42S02 at execute', '42S02', $state);

		// prepare() of multi-statement DDL executes on execute(), refuses bound params
		$q = $database->prepare("CREATE TABLE `{$table}_2` (`id` INT NOT NULL, `v` VARCHAR(10), PRIMARY KEY (`id`), KEY `v` (`v`))");
		$this->check('prepare of multi-statement DDL does not run yet', false, $database->tableExists("{$table}_2"));
		$q->execute();
		$this->check('execute runs all statements', true, $database->indexExists("{$table}_2", 'v'));
		$q = $database->prepare("CREATE TABLE `{$table}_3` (`id` INT NOT NULL, PRIMARY KEY (`id`), KEY `id2` (`id`))");
		$q->bindValue(':x', 1);
		$threw = false;
		try { $q->execute(); } catch(\PDOException $e) { $threw = strpos($e->getMessage(), 'multiple') !== false; }
		$this->check('bound params with multi-statement SQL throws clearly', true, $threw);

		// transactions
		$database->beginTransaction();
		$database->exec("INSERT INTO `$table` (name, qty) VALUES ('rollback', 9)");
		$database->rollBack();
		$this->check('rollback undoes insert', 0, (int) $database->query("SELECT COUNT(*) FROM `$table` WHERE name='rollback'")->fetchColumn());

		// failed statement inside a transaction aborts it (known difference)
		$database->beginTransaction();
		try { $database->exec("SELECT nope FROM `$table`"); } catch(\PDOException $e) { /* expected */ }
		$state = '';
		try { $database->exec("SELECT 1"); } catch(\PDOException $e) { $state = $e->errorInfo[0]; }
		$database->rollBack();
		$this->check('statement after failure in transaction reports 25P02', '25P02', $state);

		$database->exec("DROP TABLE IF EXISTS `{$table}_2`");
		$database->exec("DROP TABLE IF EXISTS `{$table}_3`");
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}
}
