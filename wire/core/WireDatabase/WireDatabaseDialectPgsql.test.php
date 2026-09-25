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
		$this->testFolding();
	}

	/**
	 * Case- and accent-insensitive text comparisons, as with MySQL's default collations (live, pgsql only)
	 *
	 */
	protected function testFolding() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$dialect = $database->dialect();
		$pdo = $database->pdo();

		$this->check('fold functions are available after connecting', true, $dialect->foldAvailable());
		$this->check('pw_fold() lowercases and removes accents', 'apfel creme brulee zurich strasse', $pdo->query("SELECT pw_fold('Äpfel Crème Brûlée ZÜRICH Straße')")->fetchColumn());
		$this->check('pw_unaccent() removes accents only', 'Apfel Zurich', $pdo->query("SELECT pw_unaccent('Äpfel Zürich')")->fetchColumn());
		$def = (string) $pdo->query("SELECT pg_get_functiondef('pw_fold(text)'::regprocedure)")->fetchColumn();
		$this->check('pw_fold() calls unaccent() schema-qualified (independent of search_path)', 1, preg_match('/"?\w+"?\.unaccent\(\'"?\w+"?\.unaccent\'::regdictionary/', $def));
		$this->check('pw_fold() is IMMUTABLE (usable in indexes)', true, stripos($def, 'IMMUTABLE') !== false);

		// raw SQL in MySQL syntax, on a table with the index types ProcessWire's text fields use
		$table = WireTests::fieldPrefix . 'pgsql_fold';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("CREATE TABLE `$table` (`pages_id` int unsigned NOT NULL, `data` text NOT NULL, `email` varchar(250) NOT NULL DEFAULT '', PRIMARY KEY (`pages_id`), KEY `data_exact` (`data`(250)), KEY `email` (`email`), UNIQUE KEY `uq` (`email`, `pages_id`), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$rows = [[1, 'Hello World', 'Admin@Example.com'], [2, 'Äpfel', ''], [3, 'Crème brûlée', ''], [4, 'Zürich', ''], [5, 'apple', '']];
		foreach($rows as $row) {
			$q = $database->prepare("INSERT INTO `$table` (pages_id, data, email) VALUES (:id, :data, :email)");
			$q->bindValue(':id', $row[0], \PDO::PARAM_INT);
			$q->bindValue(':data', $row[1]);
			$q->bindValue(':email', $row[2]);
			$q->execute();
		}
		$ids = function($where, array $binds = []) use($database, $table) {
			$q = $database->prepare("SELECT pages_id FROM `$table` WHERE $where ORDER BY pages_id");
			foreach($binds as $k => $v) $q->bindValue($k, $v);
			$q->execute();
			return array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
		};
		$this->check('= ignores case', [1], $ids('data=:v', [':v' => 'hello world']));
		$this->check('= ignores accents', [2], $ids('data=:v', [':v' => 'apfel']));
		$this->check('= with a literal', [2], $ids("data='ÄPFEL'"));
		$this->check('!= ignores case and accents', [1, 3, 4, 5], $ids('data!=:v', [':v' => 'APFEL']));
		$this->check('LIKE contains ignores case and accents', [3], $ids('data LIKE :v', [':v' => '%creme%']));
		$this->check('LIKE starts-with ignores accents', [4], $ids('data LIKE :v', [':v' => 'zurich%']));
		$this->check('REGEXP ignores case and accents, keeps its escapes', [1], $ids('data REGEXP :v', [':v' => '^HELLO\\W+world$']));
		$this->check('REGEXP word boundaries', [2, 5], $ids('data REGEXP :v', [':v' => '[[:<:]](apfel|apple)[[:>:]]']));
		$this->check('IN ignores case and accents', [2, 4], $ids('data IN (:a, :b)', [':a' => 'apfel', ':b' => 'ZURICH']));
		$this->check('email lookup ignores case', [1], $ids('email=:v', [':v' => 'admin@example.com']));
		$sorted = $database->query("SELECT data FROM `$table` ORDER BY data")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('ORDER BY sorts by the folded value (accented letters with their base letter)', ['Äpfel', 'apple', 'Crème brûlée', 'Hello World', 'Zürich'], $sorted);

		// indexes on the folded values are used, and look like the MySQL ones to ProcessWire
		$pdo->exec('SET enable_seqscan = off');
		$plan = function($sql, $value) use($database) {
			$q = $database->prepare("EXPLAIN $sql");
			$q->bindValue(':v', $value);
			$q->execute();
			return implode("\n", $q->fetchAll(\PDO::FETCH_COLUMN));
		};
		$eqPlan = $plan("SELECT pages_id FROM `$table` WHERE data=:v", 'apfel');
		$likePlan = $plan("SELECT pages_id FROM `$table` WHERE data LIKE :v", '%creme%');
		$emailPlan = $plan("SELECT pages_id FROM `$table` WHERE email=:v", 'admin@example.com');
		$pdo->exec('SET enable_seqscan = on');
		$this->check('= on text uses the folded hash index', true, strpos($eqPlan, $table . '__data_exact') !== false);
		$this->check('LIKE uses the folded trigram index', true, strpos($likePlan, $table . '__data') !== false);
		$this->check('= on varchar uses the folded btree index', true, strpos($emailPlan, $table . '__email') !== false || strpos($emailPlan, $table . '__uq__fold') !== false);
		$indexes = $database->getIndexes($table, true);
		$this->check('getIndexes() reports folded expression indexes by their column', ['data'], isset($indexes['data_exact']) ? $indexes['data_exact']['columns'] : null);
		$this->check('getIndexes() does not report folded companion indexes', false, isset($indexes['uq__fold']) || isset($indexes['primary__fold']));
		$this->check('getIndexes() still reports the unique key', ['email', 'pages_id'], isset($indexes['uq']) ? $indexes['uq']['columns'] : null);
		$this->check('indexExists() finds a folded index', true, $database->indexExists($table, 'data_exact'));
		$database->exec("ALTER TABLE `$table` DROP INDEX `uq`");
		$this->check('dropping a unique key drops its folded companion', false, (bool) $pdo->query("SELECT to_regclass('\"{$table}__uq__fold\"')")->fetchColumn());
		$database->exec("DROP TABLE IF EXISTS `$table`");

		// through the ProcessWire API, with the examples from processwire-requests#609
		$parent = $this->getTestPage();
		if(!$parent || !$parent->id) return;
		$pages = $this->wire()->pages;
		$titles = ['Hello World', 'Äpfel', 'Crème', 'Zürich'];
		$created = [];
		foreach($titles as $n => $title) {
			$p = $pages->newPage(['template' => $parent->template, 'parent' => $parent, 'name' => "pgsql-fold-$n", 'title' => $title]);
			$pages->save($p);
			$created[] = $p;
		}
		$find = function($selector) use($pages, $parent) {
			return $pages->find("parent=$parent, $selector, include=all")->implode('|', 'title');
		};
		$this->check('title=hello world', 'Hello World', $find('title=hello world'));
		$this->check('title=äpfel', 'Äpfel', $find('title=äpfel'));
		$this->check('title%=HELLO', 'Hello World', $find('title%=HELLO'));
		$this->check('title%=apfel', 'Äpfel', $find('title%=apfel'));
		$this->check('title*=creme', 'Crème', $find('title*=creme'));
		$this->check('title^=zurich', 'Zürich', $find('title^=zurich'));
		$this->check('sort=title', 'Äpfel|Crème|Hello World|Zürich', $find('name^=pgsql-fold-, sort=title'));
		foreach($created as $p) $pages->delete($p, true);

		$users = $this->wire()->users;
		$old = $users->get('name=pgsql-fold-user');
		if($old->id) $users->delete($old);
		$user = $users->add('pgsql-fold-user');
		$user->of(false);
		$user->email = 'fold.test@example.com';
		$users->save($user);
		// stored in mixed case (i.e. imported, or saved before a sanitizer lowercased it), as in processwire-requests#609
		$q = $database->prepare('UPDATE field_email SET data=:email WHERE pages_id=:id');
		$q->bindValue(':email', 'Fold.Test@Example.COM');
		$q->bindValue(':id', $user->id, \PDO::PARAM_INT);
		$q->execute();
		$stored = $database->prepare('SELECT data FROM field_email WHERE pages_id=:id');
		$stored->bindValue(':id', $user->id, \PDO::PARAM_INT);
		$stored->execute();
		$this->check('email is stored in mixed case', 'Fold.Test@Example.COM', $stored->fetchColumn());
		$this->wire()->pages->uncacheAll();
		$found = $users->get('email=fold.test@example.com');
		$this->check('$users->get(email=...) ignores case', $user->id, $found->id);
		$users->delete($user);
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
		$this->check('fetches are not stringified (pdo_mysql on PHP 8.1+ returns native ints too)', false, isset($data['options'][\PDO::ATTR_STRINGIFY_FETCHES]));
		$this->check('given options preserved', \PDO::ERRMODE_EXCEPTION, $data['options'][\PDO::ATTR_ERRMODE]);
		$config->dbSocket = '/tmp';
		$data = WireDatabaseDialectPgsql::connectionConfig($config, []);
		$this->check('socket directory becomes host, with port (socket files are named by port)', 'pgsql:host=/tmp;port=5432;dbname=pwtest', $data['dsn']);
		$config->dbPort = '';
		$data = WireDatabaseDialectPgsql::connectionConfig($config, []);
		$this->check('socket directory without port', 'pgsql:host=/tmp;dbname=pwtest', $data['dsn']);
		$data = WireDatabaseDialectPgsql::connectionConfig($config, [\PDO::ATTR_STRINGIFY_FETCHES => true, 'pgsql' => ['schema' => 'pw']]);
		$this->check('stringify can be turned on explicitly', true, $data['options'][\PDO::ATTR_STRINGIFY_FETCHES]);
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
		$this->check('getRegexEngine names the engine whose word boundaries PostgreSQL accepts', 'HenrySpencer', $dialect->getRegexEngine());
		$this->check('upsert() qualifies bare columns in update expressions',
			'INSERT INTO "t" ("id", "qty") VALUES (:id, :qty) ON CONFLICT ("id") DO UPDATE SET "qty"="t"."qty"+1, "ts"=now()',
			$dialect->upsert('t', ['id', 'qty'], ['qty' => 'qty+1', 'ts' => 'now()'], ['conflict' => ['id']]));
		$this->check('upsert() leaves bound values alone and maps VALUES(col) in expressions',
			'INSERT INTO "t" ("id", "qty") VALUES (:id, :qty) ON CONFLICT ("id") DO UPDATE SET "qty"=:qty2, "n"=excluded."qty"',
			$dialect->upsert('t', ['id', 'qty'], ['qty' => ':qty2', 'n' => 'VALUES(qty)'], ['conflict' => ['id']]));
		// upsert() expressions are MySQL syntax like all other SQL, and its output is not translated afterwards,
		// so the dialect translates them itself: MySQL-escaped literals (as from $database->quote()) must not break out
		$this->check('upsert() converts MySQL-escaped literals in column expressions',
			"INSERT INTO \"t\" (\"id\", \"name\") VALUES (:id, 'x'' OR 1=1 --') ON CONFLICT (\"id\") DO UPDATE SET \"name\"=excluded.\"name\"",
			$dialect->upsert('t', ['id', 'name' => "'x\\' OR 1=1 --'"], ['name'], ['conflict' => ['id']]));
		$this->check('upsert() does not qualify column names inside string literals',
			"INSERT INTO \"t\" (\"id\", \"data\") VALUES (:id, :data) ON CONFLICT (\"id\") DO UPDATE SET \"data\"=CONCAT(\"t\".\"data\", ' more data')",
			$dialect->upsert('t', ['id', 'data'], ['data' => "CONCAT(data, ' more data')"], ['conflict' => ['id']]));
		$this->check('upsert() translates MySQL functions in update expressions',
			'INSERT INTO "t" ("id", "qty") VALUES (:id, :qty) ON CONFLICT ("id") DO UPDATE SET "qty"=(CASE WHEN "t"."qty" > 0 THEN "t"."qty" ELSE 0 END), "n"=COALESCE("t"."n", 1)',
			$dialect->upsert('t', ['id', 'qty'], ['qty' => 'IF(qty > 0, qty, 0)', 'n' => 'IFNULL(n, 1)'], ['conflict' => ['id']]));
		// upsert() output skips translation, so rows values need PostgreSQL quoting, not $database->quote()'s MySQL-style escaping
		$this->check('upsert() rows quote strings PostgreSQL-style (quotes doubled, backslashes literal, NUL dropped)',
			"INSERT INTO \"t\" (\"id\", \"data\") VALUES (1, 'it''s \\ tricky\\'), (2, 'ab') ON CONFLICT (\"id\") DO UPDATE SET \"data\"=excluded.\"data\"",
			$dialect->upsert('t', ['id', 'data'], ['data'], ['conflict' => ['id'], 'rows' => [[1, "it's \\ tricky\\"], [2, "a\0b"]]]));
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
		$mysqlDeadlock = new \PDOException('SQLSTATE[HY000]: General error: 1213 Deadlock found when trying to get lock');
		$mysqlDeadlock->errorInfo = ['HY000', 1213, 'Deadlock found when trying to get lock'];
		$this->check('MySQL-shaped deadlock (errno 1213) is still classified, for code and tests that pass MySQL errors', 'deadlock', $dialect->getRetryableErrorType($mysqlDeadlock));
		$this->check('MySQL-shaped gone-away message is still classified', 'gone-away', $dialect->getRetryableErrorType(new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away')));
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
		$this->check('fetched integers are ints, as with pdo_mysql on PHP 8.1+', 'integer', gettype($row['qty']));
		$this->check('fetched COUNT(*) is an int', 'integer', gettype($database->query("SELECT COUNT(*) FROM `$table`")->fetchColumn()));
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
		$threw = false;
		try { $q->execute([':x' => 1]); } catch(\PDOException $e) { $threw = strpos($e->getMessage(), 'multiple') !== false; }
		$this->check('parameters with multi-statement SQL throw clearly', true, $threw);
		$this->check('parameters with multi-statement SQL executed nothing', false, $database->tableExists("{$table}_3"));

		// expression update through upsert() on a live table
		$sql = $database->dialect()->upsert($table, ['id', 'name', 'qty'], ['qty' => 'qty+10'], ['conflict' => ['id']]);
		$q = $database->prepare($sql);
		$q->bindValue(':id', $id, \PDO::PARAM_INT);
		$q->bindValue(':name', 'a');
		$q->bindValue(':qty', 0, \PDO::PARAM_INT);
		$q->execute();
		$this->check('upsert() expression update adds to the existing value', 16, (int) $database->query("SELECT qty FROM `$table` WHERE id=$id")->fetchColumn());

		// rows with quotes and backslashes (a trailing backslash too) round-trip through the untranslated upsert SQL
		$tricky = ["it's \\ tricky", "ends with \\", "x', 'ON CONFLICT"];
		$rows = [];
		foreach($tricky as $n => $value) $rows[] = [9000 + $n, $value, $n];
		$database->exec($database->dialect()->upsert($table, ['id', 'name', 'qty'], ['name'], ['conflict' => ['id'], 'rows' => $rows]));
		$database->exec($database->dialect()->upsert($table, ['id', 'name', 'qty'], ['name'], ['conflict' => ['id'], 'rows' => $rows]));
		$stored = $database->query("SELECT name FROM `$table` WHERE id >= 9000 ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('upsert() rows with quotes and backslashes store the exact strings (insert, then update)', $tricky, $stored);

		// a MySQL-escaped literal in an upsert() expression is stored as the string it spells
		$database->exec($database->dialect()->upsert($table, ['id' => '9003', 'name' => $database->quote("x' OR 1=1 --"), 'qty' => '5'], ['name'], ['conflict' => ['id']]));
		$this->check('upsert() expression with quote() output stores the exact string', "x' OR 1=1 --", $database->query("SELECT name FROM `$table` WHERE id=9003")->fetchColumn());

		// a NULL bound to a numeric comparison matches nothing (as in MySQL), not the rows holding 0
		$q = $database->prepare("SELECT COUNT(*) FROM `$table` WHERE qty=:q");
		$q->bindValue(':q', null, \PDO::PARAM_NULL);
		$q->execute();
		$this->check('NULL compared to a numeric column matches no rows', 0, (int) $q->fetchColumn());

		// comparing an integer column to a bound value can still use its index
		$database->pdo()->exec('SET enable_seqscan = off');
		$q = $database->prepare("EXPLAIN SELECT id FROM `$table` WHERE id=:id");
		$q->bindValue(':id', '9000');
		$q->execute();
		$plan = implode("\n", $q->fetchAll(\PDO::FETCH_COLUMN));
		$database->pdo()->exec('SET enable_seqscan = on');
		$this->check('integer column compared to a bound value uses the index', true, stripos($plan, 'Index Cond') !== false);

		// the statement reports MySQL's error code, which WireDatabasePDO::execute() checks to repair missing columns
		$q = $database->prepare("SELECT `$table`.nope FROM `$table`");
		try { $q->execute(); } catch(\PDOException $e) { /* expected */ }
		$info = $q->errorInfo();
		$this->check('failed statement errorCode() is MySQL\'s', '42S22', $q->errorCode());
		$this->check('failed statement errorInfo() has MySQL\'s error number and the table.column', true, $info[1] === 1054 && strpos((string) $info[2], "$table.nope") !== false);

		// explicit ids in an upsert() with bound values move the sequence (a follow-up statement, not "multiple statements")
		$q = $database->prepare($database->dialect()->upsert($table, ['id', 'name', 'qty'], ['name'], ['conflict' => ['id']]));
		$q->bindValue(':id', 9500, \PDO::PARAM_INT);
		$q->bindValue(':name', 'bound id');
		$q->bindValue(':qty', 3, \PDO::PARAM_INT);
		$q->execute();
		$database->exec("INSERT INTO `$table` (name, qty) VALUES ('after bound id', 1)");
		$this->check('implicit id after a bound explicit id in upsert() is past it', true, (int) $database->lastInsertId() > 9500);

		// the sequence does not move backwards when the highest rows are gone
		$database->exec("DELETE FROM `$table` WHERE id >= 9000");
		$database->exec("INSERT INTO `$table` (id, name, qty) VALUES (7777, 'low explicit', 1)");
		$database->exec("INSERT INTO `$table` (name, qty) VALUES ('after low explicit', 1)");
		$this->check('implicit id after deleting the highest rows is not reused', true, (int) $database->lastInsertId() > 9500);

		// a table that did not exist when first looked up is looked up again once it does
		$fresh = new WireDatabasePgsqlTranslator($database->pdo());
		$this->check('insert into a missing table has no setval', 1, count($fresh->translateStatements("INSERT INTO `{$table}_4` (id) VALUES (5)")));
		$database->exec("CREATE TABLE `{$table}_4` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))");
		$this->check('the same insert after the table is created gets its setval', 2, count($fresh->translateStatements("INSERT INTO `{$table}_4` (id) VALUES (5)")));
		$database->exec("DROP TABLE IF EXISTS `{$table}_4`");

		// transactions
		$database->beginTransaction();
		$database->exec("INSERT INTO `$table` (name, qty) VALUES ('rollback', 9)");
		$database->rollBack();
		$this->check('rollback undoes insert', 0, (int) $database->query("SELECT COUNT(*) FROM `$table` WHERE name='rollback'")->fetchColumn());

		// a failed statement inside a transaction must not abort it (savepoint per statement, as MySQL behaves)
		$database->beginTransaction();
		try { $database->exec("SELECT nope FROM `$table`"); } catch(\PDOException $e) { /* expected */ }
		$q = $database->prepare("INSERT INTO `$table` (name, qty) VALUES (:n, :q)");
		$q->bindValue(':n', 'dup'); $q->bindValue(':q', 1, \PDO::PARAM_INT); $q->execute();
		try { $q->execute(); } catch(\PDOException $e) { /* duplicate (name, qty), expected */ }
		$ok = true;
		$after = 0;
		try { $after = (int) $database->query("SELECT COUNT(*) FROM `$table` WHERE name='dup'")->fetchColumn(); } catch(\PDOException $e) { $ok = false; }
		$this->check('transaction still usable after a failed exec() and a failed execute()', true, $ok);
		$this->check('work before the failures is still in the transaction', 1, $after);
		$database->commit();
		$this->check('commit after failures kept the successful insert', 1, (int) $database->query("SELECT COUNT(*) FROM `$table` WHERE name='dup'")->fetchColumn());

		// the translator's own catalog lookups must pass through a translating PDO wrapper (the installer's)
		// untouched, rather than being translated again (which looks up their catalog tables, and so on)
		$conn = WireDatabaseDialectPgsql::connectionConfig($this->wire()->config, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
		$wrapper = new WireTestPgsqlTranslatingPDO($conn['dsn'], $conn['user'], $conn['pass'], $conn['options']);
		$wrapper->translator->setFoldAvailable($database->dialect()->foldAvailable()); // same translation settings as the site
		$mysql = "SELECT t.id FROM `$table` t WHERE t.qty = '' AND t.name = 'x'";
		$expected = $database->dialect()->translator()->translateStatements($mysql);
		try {
			$actual = $wrapper->translator->translateStatements($mysql);
		} catch(\Exception $e) {
			$actual = $e->getMessage();
		}
		$this->check('translator catalog lookups through a translating PDO wrapper do not recurse', $expected, $actual);
		$this->check('typed comparison still applied through the wrapper', true, is_array($actual) && strpos($actual[0], '= 0') !== false);
		$this->check('wrapper translated only the caller statement plus its catalog lookups', true, $wrapper->maxDepth <= 2);
		try {
			$actual = $wrapper->translator->translateStatements("SELECT c.column_name FROM information_schema.columns c WHERE c.table_name = '' LIMIT 1");
		} catch(\Exception $e) {
			$actual = $e->getMessage();
		}
		$this->check('schema-qualified table names are left alone', ["SELECT c.column_name FROM information_schema.columns c WHERE c.table_name = '' LIMIT 1"], $actual);

		$database->exec("DROP TABLE IF EXISTS `{$table}_2`");
		$database->exec("DROP TABLE IF EXISTS `{$table}_3`");
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}
}

/**
 * PDO that translates every statement it prepares, the way the installer's PDO wrapper does
 *
 */
class WireTestPgsqlTranslatingPDO extends \PDO {

	/**
	 * @var WireDatabasePgsqlTranslator
	 *
	 */
	public $translator;

	/**
	 * @var int
	 *
	 */
	public $depth = 0;

	/**
	 * @var int
	 *
	 */
	public $maxDepth = 0;

	public function __construct($dsn, $user, $pass, $options) {
		parent::__construct($dsn, $user, $pass, $options);
		$this->translator = new WireDatabasePgsqlTranslator($this);
	}

	#[\ReturnTypeWillChange]
	public function prepare($query, $options = array()) {
		if(++$this->depth > 8) {
			$this->depth = 0;
			throw new \RuntimeException('recursion: a translator catalog lookup re-entered the translator');
		}
		if($this->depth > $this->maxDepth) $this->maxDepth = $this->depth;
		try {
			$statements = $this->translator->translateStatements($query);
			return parent::prepare(array_pop($statements), $options);
		} finally {
			$this->depth--;
		}
	}
}
