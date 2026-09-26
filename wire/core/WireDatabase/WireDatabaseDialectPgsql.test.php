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
		$this->testNumberConversion();
		$this->testBackups();
		$this->testMysqlFunctions();
		$this->testSortParity();
		$this->testJson();
		$this->testGaps();
		$this->testFulltext();
		$this->testFulltextUnderscores();
		$this->testOnUpdateNoChange();
		$this->testTranslationCache();
	}

	/**
	 * MySQL's JSON functions (live, pgsql only), with the results WireDatabaseSQLiteTranslator.test.php checked against MySQL 8
	 *
	 */
	protected function testJson() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$v = function($sql) use($database) { return $database->query($sql)->fetchColumn(); };
		$doc = "'" . '{"a":"x","b":[1,2,3],"c":{"d":"e"},"n":null,"t":true,"num":5}' . "'";

		$this->check('supportsJson() is true once the functions exist', true, $database->dialect()->supportsJson());
		$this->check('JSON_UNQUOTE removes quotes', 'hello', $v("SELECT JSON_UNQUOTE('\"hello\"')"));
		$this->check('JSON_UNQUOTE leaves unquoted value alone', 'hello', $v("SELECT JSON_UNQUOTE('hello')"));
		$this->check('JSON_UNQUOTE of JSON_EXTRACT returns value', 'x', $v("SELECT JSON_UNQUOTE(JSON_EXTRACT($doc, '$.a'))"));
		$this->check('JSON_UNQUOTE returns NULL for NULL', null, $v('SELECT JSON_UNQUOTE(NULL)'));
		$this->check('JSON_LENGTH counts object keys', 6, (int) $v("SELECT JSON_LENGTH($doc)"));
		$this->check('JSON_LENGTH counts array items', 3, (int) $v("SELECT JSON_LENGTH($doc, '$.b')"));
		$this->check('JSON_LENGTH of nested object', 1, (int) $v("SELECT JSON_LENGTH($doc, '$.c')"));
		$this->check('JSON_LENGTH of scalar is one', 1, (int) $v("SELECT JSON_LENGTH($doc, '$.a')"));
		$this->check('JSON_LENGTH of empty array is zero', 0, (int) $v("SELECT JSON_LENGTH('[]')"));
		$this->check('JSON_LENGTH returns NULL for missing path', null, $v("SELECT JSON_LENGTH($doc, '$.missing')"));
		$this->check('JSON_LENGTH returns NULL for invalid JSON', null, $v("SELECT JSON_LENGTH('not json')"));
		$this->check('JSON_LENGTH returns NULL for NULL', null, $v('SELECT JSON_LENGTH(NULL)'));
		$this->check('JSON_CONTAINS matches object member', 1, (int) $v("SELECT JSON_CONTAINS($doc, '{\"a\":\"x\"}')"));
		$this->check('JSON_CONTAINS rejects wrong value', 0, (int) $v("SELECT JSON_CONTAINS($doc, '{\"a\":\"y\"}')"));
		$this->check('JSON_CONTAINS finds scalar in array at path', 1, (int) $v("SELECT JSON_CONTAINS($doc, '2', '$.b')"));
		$this->check('JSON_CONTAINS rejects absent scalar', 0, (int) $v("SELECT JSON_CONTAINS($doc, '9', '$.b')"));
		$this->check('JSON_CONTAINS matches array subset', 1, (int) $v("SELECT JSON_CONTAINS($doc, '[1,3]', '$.b')"));
		$this->check('JSON_CONTAINS rejects partial array', 0, (int) $v("SELECT JSON_CONTAINS($doc, '[1,9]', '$.b')"));
		$this->check('JSON_CONTAINS distinguishes string from number', 0, (int) $v("SELECT JSON_CONTAINS($doc, '\"5\"', '$.num')"));
		$this->check('JSON_CONTAINS matches null value', 1, (int) $v("SELECT JSON_CONTAINS($doc, 'null', '$.n')"));
		$this->check('JSON_CONTAINS matches object in array', 1, (int) $v("SELECT JSON_CONTAINS('[{\"k\":1},{\"k\":2}]', '{\"k\":2}')"));
		$this->check('JSON_CONTAINS matches nested object subset', 1, (int) $v("SELECT JSON_CONTAINS('{\"a\":{\"b\":1,\"c\":2}}', '{\"a\":{\"b\":1}}')"));
		$this->check('JSON_CONTAINS returns NULL for missing path', null, $v("SELECT JSON_CONTAINS($doc, '1', '$.missing')"));
		$this->check('JSON_CONTAINS returns NULL for NULL', null, $v("SELECT JSON_CONTAINS(NULL, '1')"));
		$this->check('JSON_EXTRACT reads array index', 2, (int) $v("SELECT JSON_EXTRACT($doc, '$.b[1]')"));
		$this->check('JSON_EXTRACT of a missing path is NULL', null, $v("SELECT JSON_EXTRACT($doc, '$.missing')"));
		$this->check('JSON_EXTRACT does not unwrap arrays (MySQL: NULL)', null, $v("SELECT JSON_EXTRACT($doc, '$.b.x')"));
		// MySQL formats JSON results with a space after ':' and ',', as jsonb does
		$this->check('JSON_SET adds member', '{"a": 1, "b": 2}', $v("SELECT JSON_SET('{\"a\":1}', '$.b', 2)"));
		$this->check('JSON_SET with a string and NULL', '{"a": null, "s": "x"}', $v("SELECT JSON_SET('{\"a\":1}', '$.a', NULL, '$.s', 'x')"));
		$this->check('JSON_INSERT does not overwrite', '{"a": 1}', $v("SELECT JSON_INSERT('{\"a\":1}', '$.a', 2)"));
		$this->check('JSON_REPLACE does not add', '{"a": 1}', $v("SELECT JSON_REPLACE('{\"a\":1}', '$.b', 2)"));
		$this->check('JSON_REMOVE removes member', '{"b": 2}', $v("SELECT JSON_REMOVE('{\"a\":1,\"b\":2}', '$.a')"));
		$this->check('JSON_VALID recognizes valid JSON', 1, (int) $v("SELECT JSON_VALID('{\"a\":1}')"));
		$this->check('JSON_VALID rejects invalid JSON', 0, (int) $v("SELECT JSON_VALID('{a')"));
		$this->check('JSON_ARRAY', '[1, "a", null]', $v("SELECT JSON_ARRAY(1, 'a', NULL)"));
		$this->check('JSON_OBJECT', '{"k": 1}', $v("SELECT JSON_OBJECT('k', 1)"));
		$this->check('JSON_QUOTE', '"a\\"b"', $v("SELECT JSON_QUOTE('a\"b')"));
		// MySQL's containment rules, which descend into arrays at any level (results as MySQL and MariaDB give them)
		foreach([
			['{"a":[1,2]}', '{"a":1}', 1, 'a scalar in an array under a key'],
			['[[1,2],[3,4]]', '[1,2]', 1, 'nested arrays'],
			['[[1,2],[3,4]]', '[1,3]', 1, 'elements from different nested arrays'],
			['[[1]]', '1', 1, 'a scalar two arrays down'],
			['[{"a":[1,2]}]', '{"a":2}', 1, 'an object in an array'],
			['1', '[1]', 0, 'an array in a scalar'],
			['[1,2]', '[]', 1, 'an empty array'],
			['{"a":1}', '{}', 1, 'an empty object'],
			['{"a":{"b":1}}', '{"a":1}', 0, 'a scalar against an object'],
			['[1,2]', '[1,5]', 0, 'a missing element'],
		] as $case) {
			list($target, $candidate, $expect, $label) = $case;
			$q = $database->prepare('SELECT JSON_CONTAINS(:t, :c)');
			$q->bindValue(':t', $target);
			$q->bindValue(':c', $candidate);
			$q->execute();
			$this->check("JSON_CONTAINS: $label", $expect, (int) $q->fetchColumn());
		}

		// the shapes FieldtypeCustom and FormBuilder use, on a JSON column and on a text column
		foreach(['json' => 'JSON', 'text' => 'MEDIUMTEXT'] as $label => $type) {
			$table = WireTests::fieldPrefix . "pgsql_json_$label";
			$database->exec("DROP TABLE IF EXISTS `$table`");
			$database->exec("CREATE TABLE `$table` (`pages_id` int unsigned NOT NULL, `data` $type, PRIMARY KEY (`pages_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			$database->exec("INSERT INTO `$table` (pages_id, data) VALUES (1, '{\"name\":\"Alice\",\"tags\":[\"x\",\"y\"],\"color\":\"green\"}'), (2, '{\"name\":\"\",\"tags\":[]}'), (3, '{\"other\":1}'), (4, '{\"grid\":[[1,2],[3,4]]}')");
			$ids = function($where, array $binds = []) use($database, $table) {
				$q = $database->prepare("SELECT pages_id FROM `$table` WHERE $where ORDER BY pages_id");
				foreach($binds as $k => $val) $q->bindValue($k, $val);
				$q->execute();
				return array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
			};
			$this->check("$label: JSON_UNQUOTE(LOWER(JSON_EXTRACT(data, \"\$.name\")))=?", [1], $ids('JSON_UNQUOTE(LOWER(JSON_EXTRACT(data, "$.name")))=:v', [':v' => 'alice']));
			$this->check("$label: JSON_CONTAINS(data, ?)", [1], $ids('JSON_CONTAINS(data, :v)', [':v' => '{"tags":["y"]}']));
			$this->check("$label: JSON_CONTAINS(data, ?, path)", [1], $ids("JSON_CONTAINS(data, :v, '$.tags')", [':v' => '"x"']));
			$this->check("$label: JSON_CONTAINS(data, ?) with a scalar for an array", [1], $ids('JSON_CONTAINS(data, :v)', [':v' => '{"tags":"x"}']));
			$this->check("$label: JSON_CONTAINS(data, ?) in nested arrays", [4], $ids('JSON_CONTAINS(data, :v)', [':v' => '{"grid":[1,2]}']));
			$this->check("$label: JSON_CONTAINS(data, ?, path) in nested arrays", [4], $ids("JSON_CONTAINS(data, :v, '$.grid')", [':v' => '[1,3]']));
			// FieldtypeCustom's = for page reference and select subfields
			$this->check("$label: JSON_CONTAINS(...)=1 OR JSON_EXTRACT(...)=string", [1], $ids("JSON_CONTAINS(data, :json)=1 OR JSON_EXTRACT(data, '$.color')=:value", [':json' => '{"color":["green"]}', ':value' => 'green']));
			$this->check("$label: JSON_EXTRACT(...)!=string", [], $ids("JSON_EXTRACT(data, '$.color')!=:value", [':value' => 'green']));
			$this->check("$label: JSON_LENGTH(data, path)>0", [1, 2], $ids("JSON_LENGTH(data, '$.name')>0 OR JSON_LENGTH(data, '$.tags')>0"));
			$this->check("$label: NOT JSON_CONTAINS(data, ?, path)", [2, 3, 4], $ids("NOT JSON_CONTAINS(data, :v, '$.tags') OR JSON_CONTAINS(data, :v, '$.tags') IS NULL", [':v' => '"x"']));
			if($label === 'json') {
				// the jsonb column's GIN index serves JSON_CONTAINS() conditions, and is not one of the MySQL indexes
				$database->pdo()->exec('SET enable_seqscan = off');
				$q = $database->prepare("EXPLAIN SELECT pages_id FROM `$table` WHERE JSON_CONTAINS(data, :v, '$.tags')");
				$q->bindValue(':v', '"x"');
				$q->execute();
				$plan = implode("\n", $q->fetchAll(\PDO::FETCH_COLUMN));
				$database->pdo()->exec('SET enable_seqscan = on');
				$this->check('json: JSON_CONTAINS() uses the GIN index', 1, preg_match('/ on ' . $table . '__data__json\b/', $plan));
				$this->check('json: and the partial index of nested documents', true, strpos($plan, $table . '__data__jsonnest') !== false);
				$this->check('json: the GIN index is not reported by getIndexes()', ['PRIMARY'], array_keys($database->getIndexes($table, true)));
			}
			$q = $database->prepare("UPDATE `$table` SET data=JSON_SET(JSON_REMOVE(data, :old), :new, JSON_EXTRACT(data, :old)) WHERE pages_id=1");
			$q->bindValue(':old', '$.name');
			$q->bindValue(':new', '$.title');
			$q->execute();
			$this->check("$label: renaming a subfield keeps its value as JSON", '{"tags": ["x", "y"], "color": "green", "title": "Alice"}', (string) $v("SELECT data FROM `$table` WHERE pages_id=1"));
			$database->exec("DROP TABLE IF EXISTS `$table`");
		}
	}

	/**
	 * ON UPDATE CURRENT_TIMESTAMP, zero dates, UPDATE ... JOIN and foreign keys (live, pgsql only)
	 *
	 */
	protected function testGaps() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$v = function($sql) use($database) { return $database->query($sql)->fetchColumn(); };
		$a = WireTests::fieldPrefix . 'pgsql_gaps_a';
		$b = WireTests::fieldPrefix . 'pgsql_gaps_b';
		$database->exec("DROP TABLE IF EXISTS `$b`");
		$database->exec("DROP TABLE IF EXISTS `$a`");

		// ON UPDATE CURRENT_TIMESTAMP
		$database->exec("CREATE TABLE `$a` (`id` int NOT NULL, `name` varchar(20) NOT NULL DEFAULT '', `d` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`))");
		$database->exec("INSERT INTO `$a` (id, name) VALUES (1, 'x'), (2, 'y')");
		$database->exec("UPDATE `$a` SET ts='2000-01-01 00:00:00'");
		$this->check('ON UPDATE: setting the column itself keeps the value set', '2000-01-01 00:00:00', (string) $v("SELECT ts FROM `$a` WHERE id=1"));
		$database->exec("UPDATE `$a` SET name='x' WHERE id=1");
		$this->check('ON UPDATE: an UPDATE that changes nothing keeps it', '2000-01-01 00:00:00', (string) $v("SELECT ts FROM `$a` WHERE id=1"));
		$database->exec("UPDATE `$a` SET name='z' WHERE id=1");
		$this->check('ON UPDATE: changing another column sets the current time', true, strtotime((string) $v("SELECT ts FROM `$a` WHERE id=1")) > time() - 60);
		$this->check('ON UPDATE: other rows are not touched', '2000-01-01 00:00:00', (string) $v("SELECT ts FROM `$a` WHERE id=2"));
		$database->exec("ALTER TABLE `$a` RENAME COLUMN `ts` TO `changed`");
		$database->exec("UPDATE `$a` SET changed='2000-01-01 00:00:00' WHERE id=2");
		$database->exec("UPDATE `$a` SET name='w' WHERE id=2");
		$this->check('ON UPDATE: a renamed column keeps updating', true, strtotime((string) $v("SELECT changed FROM `$a` WHERE id=2")) > time() - 60);
		$database->exec("ALTER TABLE `$a` RENAME COLUMN `changed` TO `ts`");
		$database->exec("UPDATE `$a` SET ts='2000-01-01 00:00:00', name='y' WHERE id=2");

		// zero dates
		$this->check('a zero date default is NULL', null, $v("SELECT d FROM `$a` WHERE id=1"));
		$database->exec("INSERT INTO `$a` (id, d) VALUES (3, '0000-00-00 00:00:00'), (4, '2020-05-01 10:00:00')");
		$q = $database->prepare("INSERT INTO `$a` (id, d) VALUES (5, :d)");
		$q->bindValue(':d', '0000-00-00 00:00:00');
		$q->execute();
		$this->check('a bound zero date is NULL', null, $v("SELECT d FROM `$a` WHERE id=5"));
		$this->check('= zero date finds the zero dates', 4, (int) $v("SELECT COUNT(*) FROM `$a` WHERE d='0000-00-00 00:00:00'"));
		$this->check('> zero date finds the real dates', 1, (int) $v("SELECT COUNT(*) FROM `$a` WHERE d>'0000-00-00'"));
		// text that happens to be a zero date is text (i.e. a page title '0000-00-00')
		$database->exec("INSERT INTO `$a` (id, name) VALUES (8, 'x8'), (9, 'x9')");
		foreach(['0000-00-00', '0000-00-00 00:00:00'] as $n => $text) {
			$q = $database->prepare("UPDATE `$a` SET name=:name WHERE id=:id");
			$q->bindValue(':name', $text);
			$q->bindValue(':id', 8 + $n, \PDO::PARAM_INT);
			$q->execute();
			$q = $database->prepare("SELECT id FROM `$a` WHERE name=:name");
			$q->bindValue(':name', $text);
			$q->execute();
			$this->check("a text column keeps '$text' (bound) and finds it", [8 + $n], array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN)));
		}
		$database->exec("INSERT INTO `$a` (id, name, d) VALUES (7, '0000-00-00', '0000-00-00')");
		$this->check('a text column keeps a zero date literal while a date column makes it NULL', ['0000-00-00', null], array_values($database->query("SELECT name, d FROM `$a` WHERE id=7")->fetch(\PDO::FETCH_ASSOC)));
		$q = $database->prepare("SELECT COUNT(*) FROM `$a` WHERE d>:v");
		$q->bindValue(':v', '2000-01-01');
		$q->execute();
		$this->check('a bound date compared with a date column', 1, (int) $q->fetchColumn());
		$database->exec("DELETE FROM `$a` WHERE id IN (7, 8, 9)");

		// UPDATE ... JOIN
		$database->exec("CREATE TABLE `$b` (`id` int NOT NULL, `a_id` int NOT NULL, `label` varchar(20) NOT NULL, PRIMARY KEY (`id`))");
		$database->exec("INSERT INTO `$b` (id, a_id, label) VALUES (10, 1, 'one'), (20, 2, 'two')");
		$database->exec("UPDATE `$a` AS x INNER JOIN `$b` AS y ON y.a_id=x.id SET x.name=y.label WHERE y.id=20");
		$this->check('UPDATE ... JOIN updates the joined rows', 'two', $v("SELECT name FROM `$a` WHERE id=2"));
		$this->check('UPDATE ... JOIN leaves other rows', 'z', $v("SELECT name FROM `$a` WHERE id=1"));

		// foreign keys, and their errors as MySQL reports them
		$database->exec("ALTER TABLE `$b` ADD CONSTRAINT `fk_a` FOREIGN KEY (`a_id`) REFERENCES `$a` (`id`) ON DELETE CASCADE");
		$state = '';
		try { $database->exec("INSERT INTO `$b` (id, a_id, label) VALUES (30, 99, 'none')"); } catch(\PDOException $e) { $state = $e->getCode(); }
		$this->check('a foreign key violation has MySQL\'s SQLSTATE', '23000', $state);
		$database->exec("DELETE FROM `$a` WHERE id=1");
		$this->check('ON DELETE CASCADE', 0, (int) $v("SELECT COUNT(*) FROM `$b` WHERE a_id=1"));
		$database->exec("ALTER TABLE `$b` DROP FOREIGN KEY `fk_a`");
		$database->exec("INSERT INTO `$b` (id, a_id, label) VALUES (30, 99, 'none')");
		$this->check('DROP FOREIGN KEY', 1, (int) $v("SELECT COUNT(*) FROM `$b` WHERE id=30"));

		$database->exec("DROP TABLE IF EXISTS `$b`");
		$database->exec("DROP TABLE IF EXISTS `$a`");
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
		$this->check('REGEXP ignores case and keeps its escapes', [1], $ids('data REGEXP :v', [':v' => '^HELLO\\W+world$']));
		$this->check('REGEXP does not ignore accents (MySQL REGEXP follows the collation for case only)', [5], $ids('data REGEXP :v', [':v' => '[[:<:]](apfel|apple)[[:>:]]']));
		$this->check('REGEXP matches accented data with an accented pattern', [2], $ids('data REGEXP :v', [':v' => '^äpfel$']));
		$this->check('NOT REGEXP', [1, 3, 4, 5], $ids('data NOT REGEXP :v', [':v' => '^äpfel$']));
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
		$regexPlan = $plan("SELECT pages_id FROM `$table` WHERE data REGEXP :v", 'zola');
		$pdo->exec('SET enable_seqscan = on');
		$this->check('= on text uses the folded hash index', true, strpos($eqPlan, $table . '__data_exact') !== false);
		$this->check('LIKE uses the folded trigram index', true, strpos($likePlan, $table . '__data') !== false);
		$this->check('REGEXP uses the folded trigram index to narrow rows', true, strpos($regexPlan, $table . '__data') !== false);
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
		// ^= uses REGEXP, which does not fold accents on MySQL either
		$this->check('title^=zurich', '', $find('title^=zurich'));
		$this->check('title^=ZÜR', 'Zürich', $find('title^=ZÜR'));
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

	/**
	 * Strings to numbers as MySQL converts them: the leading number, with its fraction and exponent (live, pgsql only)
	 *
	 */
	protected function testNumberConversion() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$table = WireTests::fieldPrefix . 'pgsql_numconv';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("CREATE TABLE `$table` (`id` int NOT NULL, `v` varchar(20), `i` varchar(20), PRIMARY KEY (`id`))");
		// MySQL's results for MODIFY to DECIMAL(10,2) and to INT
		$values = [1 => ['12.5', '12.50', 13], 2 => ['-3.75x', '-3.75', -4], 3 => ['.5', '0.50', 1], 4 => ['1e3', '1000.00', 1000], 5 => ['abc', '0.00', 0], 6 => ['', '0.00', 0], 7 => ['  +7', '7.00', 7], 8 => ['12abc', '12.00', 12]];
		foreach($values as $id => $row) {
			$q = $database->prepare("INSERT INTO `$table` (id, v, i) VALUES (:id, :v, :i)");
			$q->bindValue(':id', $id, \PDO::PARAM_INT);
			$q->bindValue(':v', $row[0]);
			$q->bindValue(':i', $row[0]);
			$q->execute();
		}
		$database->exec("ALTER TABLE `$table` MODIFY `v` DECIMAL(10,2) NOT NULL DEFAULT 0");
		$database->exec("ALTER TABLE `$table` MODIFY `i` INT NOT NULL DEFAULT 0");
		$rows = $database->query("SELECT id, v, i FROM `$table` ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
		foreach($rows as $row) {
			$id = (int) $row['id'];
			$this->check("MODIFY text to DECIMAL(10,2): '{$values[$id][0]}'", $values[$id][1], (string) $row['v']);
			$this->check("MODIFY text to INT: '{$values[$id][0]}'", $values[$id][2], (int) $row['i']);
		}
		// comparisons of a decimal column with strings (literal and bound)
		$ids = function($where, array $binds = []) use($database, $table) {
			$q = $database->prepare("SELECT id FROM `$table` WHERE $where ORDER BY id");
			foreach($binds as $k => $val) $q->bindValue($k, $val);
			$q->execute();
			return array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
		};
		$this->check("decimal = '.5' (literal)", [3], $ids("v = '.5'"));
		$this->check('decimal = bound \'.5\'', [3], $ids('v = :v', [':v' => '.5']));
		$this->check('decimal = bound \'1e3\'', [4], $ids('v = :v', [':v' => '1e3']));
		$this->check('decimal = bound \'+7\'', [7], $ids('v = :v', [':v' => '+7']));
		$this->check('decimal = bound \'-3.75 dollars\'', [2], $ids('v = :v', [':v' => '-3.75 dollars']));
		// an integer column compared with number strings, as MySQL compares them
		$this->check("int = '2' (literal)", [2], $ids("id = '2'"));
		$this->check("int = '1.5' (literal): no match, not an error", [], $ids("id = '1.5'"));
		$this->check("int = '2.0' (literal)", [2], $ids("id = '2.0'"));
		$this->check("int = ' 2' (literal)", [2], $ids("id = ' 2'"));
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}

	protected function testTranslationCache() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		/** @var WireDatabaseDialectPgsql $dialect */
		$dialect = $database->dialect();
		$pdo = $database->pdo();
		$pdo->exec('CREATE SEQUENCE IF NOT EXISTS pw_schema_version');
		$pdo->query("SELECT nextval('pw_schema_version')");
		$version = function() use($pdo) { return (int) $pdo->query('SELECT last_value FROM pw_schema_version')->fetchColumn(); };
		$table = WireTests::fieldPrefix . 'pgsql_tcache';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$v = $version();
		$database->exec("CREATE TABLE `$table` (`pages_id` int unsigned NOT NULL, `data` text NOT NULL, PRIMARY KEY (`pages_id`), KEY `data` (`data`(250)))");
		$this->check('a schema change moves the schema version on (multiple statements)', true, $version() > $v);
		$v = $version();
		$database->exec("ALTER TABLE `$table` ADD `extra` int NOT NULL DEFAULT 0");
		$this->check('exec() of a schema change moves it on', true, $version() > $v);
		$v = $version();
		$database->prepare("ALTER TABLE `$table` DROP `extra`")->execute();
		$this->check('a prepared schema change moves it on', true, $version() > $v);
		$v = $version();
		$database->query("SELECT * FROM `$table`");
		$database->exec("INSERT INTO `$table` (pages_id, data) VALUES (1, 'x')");
		$this->check('other statements do not', $v, $version());
		$database->beginTransaction();
		$database->exec("ALTER TABLE `$table` ADD `extra` int NOT NULL DEFAULT 0");
		$this->check('a schema change in a transaction waits for the commit', $v, $version());
		$database->commit();
		$this->check('and moves it on after the commit', true, $version() > $v);
		$v = $version();
		$database->beginTransaction();
		$database->exec("ALTER TABLE `$table` DROP `extra`");
		$database->rollBack();
		$this->check('a rolled back schema change does not', $v, $version());

		// the cache file: this request's new translations are added, and the next request uses them
		$file = $this->wire()->config->paths->cache . 'WireDatabasePgsql/translations-wiretest.php';
		if(is_file($file)) unlink($file);
		$rc = new \ReflectionClass($dialect);
		$prop = $rc->getProperty('translationCacheFile');
		$prop->setAccessible(true);
		$was = $prop->getValue($dialect);
		$prop->setValue($dialect, $file);
		$translator = $dialect->translator();
		$translator->loadPersistentCache(array());
		$sql = "SELECT pages_id FROM `$table` WHERE data=:v0 LIMIT 3, 7";
		$translated = $translator->translate($sql);
		// a site's own cache file (named by its key) is not removed by this test's file
		$siteFile = dirname($file) . '/translations-' . md5('wiretest-site') . '.php';
		$this->wire()->files->mkdir(dirname($file) . '/', true);
		$siteExisted = is_file($siteFile);
		if(!$siteExisted) file_put_contents($siteFile, '<?php return array();');
		$dialect->saveTranslationCache();
		$this->check("a test's cache file leaves the site's cache files alone", true, is_file($siteFile));
		if(!$siteExisted && is_file($siteFile)) unlink($siteFile);
		$entries = is_file($file) ? include($file) : null;
		$this->check('saveTranslationCache() writes this request\'s translations', true, is_array($entries) && isset($entries["SELECT pages_id FROM `$table` WHERE data=:pwp0x LIMIT 3, 7"]));
		$next = new WireDatabasePgsqlTranslator();
		$next->loadPersistentCache(is_array($entries) ? $entries : array());
		$this->check('a later request uses them (without the database)', $translated, $next->translate($sql));
		$this->check('as kept, not translated again', array(), $next->persistentCacheAdditions());
		$this->check('clearTranslationCache() moves the version on', true, (function() use($dialect, $version) { $v = $version(); $dialect->clearTranslationCache(); return $version() > $v; })());
		$prop->setValue($dialect, $was);
		if(is_file($file)) unlink($file);
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}

	protected function testFulltext() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$dialect = $database->dialect();
		$pdo = $database->pdo();

		$this->check('full text search is set up after connecting', true, $dialect->supportsFulltext());
		$this->check('getStopwords(): none, since pw_search indexes every word', [], $database->getStopwords());
		$tsquery = function($value, $boolean = true) use($pdo) {
			$q = $pdo->prepare('SELECT pw_tsquery(:v, :b)::text');
			$q->bindValue(':v', $value);
			$q->bindValue(':b', $boolean ? 't' : 'f');
			$q->execute();
			return $q->fetchColumn();
		};
		$this->check('pw_tsquery(): required words and a prefix', "'hello' & 'world':*", $tsquery('+hello +world*'));
		$this->check('pw_tsquery(): words without operators are alternatives', "'hello' | 'world'", $tsquery('hello world'));
		$this->check('pw_tsquery(): a required group', "'apfel' | 'apfel':*", $tsquery('+(>apfel apfel*)'));
		$this->check('pw_tsquery(): a phrase, accents folded', "'creme' <-> 'brulee'", $tsquery('"Crème Brûlée"'));
		$this->check('pw_tsquery(): an excluded word', "'zola' & !'emile'", $tsquery('+zola -emile'));
		$this->check('pw_tsquery(): only excluded words match nothing (as MySQL)', '', $tsquery('-emile'));
		$this->check('pw_tsquery(): natural language mode ignores operators', "'zola' | 'emile'", $tsquery('+zola -emile', false));
		$this->check('pw_tsquery(): words split where MySQL splits them', "'foo' & 'example' & 'com'", $tsquery('+foo.example.com'));
		$this->check('pw_tsquery(): a prefix the parser splits is a phrase of prefixes', "'o':* <-> 'brien':*", $tsquery("+o'brien*"));
		$this->check('pw_tsquery(): an operator without a word is ignored, not the rest of the query', "'bar'", $tsquery('+ foo +bar'));
		$this->check('pw_tsquery(): an operator before a closing paren', "'foo' | 'bar'", $tsquery('(+) foo bar'));
		$this->check('pw_tsquery(): @distance is not a word', "'a' <-> 'b'", $tsquery('"a b" @3'));
		$this->check('pw_tsvector(): words with digits are unaccented too', true, in_array($pdo->query("SELECT pw_tsvector('Crème2') @@ pw_tsquery('creme2', false)")->fetchColumn(), [true, 't', 1, '1'], true));
		$doc = $pdo->query("SELECT pw_tsvector('<p>Émile wrote at foo.example.com</p>')::text")->fetchColumn();
		$this->check('pw_tsvector(): markup skipped, words split and folded', "'at':3 'com':6 'emile':1 'example':5 'foo':4 'wrote':2", $doc);

		// raw SQL in MySQL syntax
		$table = WireTests::fieldPrefix . 'pgsql_fulltext';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("CREATE TABLE `$table` (`pages_id` int unsigned NOT NULL, `data` mediumtext NOT NULL, PRIMARY KEY (`pages_id`), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$rows = [1 => 'Hello World', 2 => 'Crème brûlée recipe', 3 => 'Émile Zola wrote novels', 4 => 'Hello there, Zola fans', 5 => '<p>World news</p>'];
		foreach($rows as $id => $data) {
			$q = $database->prepare("INSERT INTO `$table` (pages_id, data) VALUES (:id, :data)");
			$q->bindValue(':id', $id, \PDO::PARAM_INT);
			$q->bindValue(':data', $data);
			$q->execute();
		}
		$ids = function($sql, $value) use($database) {
			$q = $database->prepare($sql);
			$q->bindValue(':v', $value);
			$q->execute();
			return array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
		};
		$where = "SELECT pages_id FROM `$table` WHERE MATCH(data) AGAINST(:v IN BOOLEAN MODE) ORDER BY pages_id";
		$this->check('MATCH: required words', [1], $ids($where, '+hello +world'));
		$this->check('MATCH: alternatives', [1, 3, 4, 5], $ids($where, 'world zola'));
		$this->check('MATCH: prefix, accents folded', [2], $ids($where, '+creme*'));
		$this->check('MATCH: excluded word', [4], $ids($where, '+zola -emile'));
		$this->check('MATCH: phrase', [3], $ids($where, '+"emile zola"'));
		$this->check('MATCH: words inside markup', [5], $ids($where, '+news'));
		$this->check('MATCH: markup itself is not a word', [], $ids($where, '+p'));
		$this->check('NOT MATCH', [2, 3, 5], $ids("SELECT pages_id FROM `$table` WHERE NOT MATCH(data) AGAINST(:v IN BOOLEAN MODE) ORDER BY pages_id", '+hello'));
		$this->check('MATCH WITH QUERY EXPANSION matches any word', [1, 4, 5], $ids("SELECT pages_id FROM `$table` WHERE MATCH(data) AGAINST(:v WITH QUERY EXPANSION) ORDER BY pages_id", 'hello world'));
		$this->check('MATCH as a score orders by relevance', [1, 4, 5], $ids(
			"SELECT pages_id, MATCH(data) AGAINST(:v IN BOOLEAN MODE) AS score FROM `$table` WHERE MATCH(data) AGAINST(:v IN BOOLEAN MODE) ORDER BY score DESC, pages_id", 'hello world'
		));

		// a negated operator with ordering selects NOT MATCH as a score
		$query = new DatabaseQuerySelect();
		$this->wire($query);
		$query->select('pages_id')->from($table);
		$ft = new DatabaseQuerySelectFulltext($query);
		$this->wire($ft);
		$ft->match($table, 'data', '!~=', 'zola');
		$this->check('!~= with a NOT MATCH score', [1, 2, 5], array_map('intval', $query->execute()->fetchAll(\PDO::FETCH_COLUMN)));
		$pdo->exec('SET enable_seqscan = off');
		$q = $database->prepare("EXPLAIN SELECT pages_id FROM `$table` WHERE MATCH(data) AGAINST(:v IN BOOLEAN MODE)");
		$q->bindValue(':v', '+zola');
		$q->execute();
		$plan = implode("\n", $q->fetchAll(\PDO::FETCH_COLUMN));
		$pdo->exec('SET enable_seqscan = on');
		$this->check('MATCH uses the tsvector index', true, strpos($plan, $table . '__data__fts') !== false);
		$row = $database->query("SELECT * FROM `$table` WHERE pages_id=1")->fetch(\PDO::FETCH_ASSOC);
		$this->check('SELECT * does not return the stored tsvector column', ['pages_id', 'data'], array_keys($row));
		$this->check('getColumns() does not report it', ['pages_id', 'data'], $database->getColumns($table));
		$this->check('the stored column is there', 'tsvector', $pdo->query("SELECT format_type(atttypid, NULL) FROM pg_attribute WHERE attrelid = '\"$table\"'::regclass AND attname = 'data__tsv'")->fetchColumn());
		$database->exec("ALTER TABLE `$table` MODIFY `data` text NOT NULL");
		$this->check('MODIFY keeps the stored column and its index', true, (bool) $pdo->query("SELECT to_regclass('\"{$table}__data__fts\"')")->fetchColumn());
		$this->check('MATCH after MODIFY', [3, 4], $ids("SELECT pages_id FROM `$table` WHERE MATCH(data) AGAINST(:v IN BOOLEAN MODE) ORDER BY pages_id", '+zola'));
		$indexes = $database->getIndexes($table, true);
		$this->check('getIndexes() reports the FULLTEXT key once', ['data'], isset($indexes['data']) ? $indexes['data']['columns'] : null);
		$this->check('getIndexes() does not report the tsvector index', false, isset($indexes['data__fts']));
		$database->exec("ALTER TABLE `$table` DROP INDEX `data`");
		$this->check('dropping the FULLTEXT key drops its tsvector index', false, (bool) $pdo->query("SELECT to_regclass('\"{$table}__data__fts\"')")->fetchColumn());
		$database->exec("ALTER TABLE `$table` ADD FULLTEXT KEY `data` (`data`)");
		$this->check('adding a FULLTEXT key adds its tsvector index', true, (bool) $pdo->query("SELECT to_regclass('\"{$table}__data__fts\"')")->fetchColumn());
		$database->exec("DROP TABLE IF EXISTS `$table`");

		// through the ProcessWire API: fulltext operators now take their MATCH paths
		$parent = $this->getTestPage();
		if(!$parent || !$parent->id) return;
		$pages = $this->wire()->pages;
		$titles = ['Hello World', 'Crème brûlée', 'Émile Zola', 'Ends with a quote "here"'];
		$created = [];
		foreach($titles as $n => $title) {
			$p = $pages->newPage(['template' => $parent->template, 'parent' => $parent, 'name' => "pgsql-fts-$n", 'title' => $title]);
			$pages->save($p);
			$created[] = $p;
		}
		$find = function($selector) use($pages, $parent) {
			return $pages->find("parent=$parent, name^=pgsql-fts-, $selector, include=all, sort=name")->implode('|', 'title');
		};
		$this->check('title~=zola', 'Émile Zola', $find('title~=zola'));
		$this->check('title~=hello world', 'Hello World', $find('title~=hello world'));
		$this->check('title~|=hello zola', 'Hello World|Émile Zola', $find('title~|=hello zola'));
		$this->check('title*=creme', 'Crème brûlée', $find('title*=creme'));
		$this->check('title~*=bru', 'Crème brûlée', $find('title~*=bru'));
		$this->check('title**=world', 'Hello World', $find('title**=world'));
		$this->check('title#=+zola -hello', 'Émile Zola', $find('title#="+zola -hello"'));
		$this->check('title$=here (trailing punctuation)', 'Ends with a quote "here"', $find('title$=here'));
		$this->check('title!~=zola', 'Hello World|Crème brûlée|Ends with a quote "here"', $find('title!~=zola'));
		foreach($created as $p) $pages->delete($p, true);
	}

	/**
	 * "_" is part of a word, as in MySQL's fulltext, and a site set up by pw_fulltext_v1 is brought up to date (live, pgsql only)
	 *
	 */
	protected function testFulltextUnderscores() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql' || !$database->dialect()->supportsFulltext()) return;
		$pdo = $database->pdo();
		$matches = function($doc, $query, $boolean = true) use($pdo) {
			$q = $pdo->prepare('SELECT (pw_tsvector(:d) @@ pw_tsquery(:q, :b))::int');
			$q->bindValue(':d', $doc);
			$q->bindValue(':q', $query);
			$q->bindValue(':b', $boolean ? 't' : 'f');
			$q->execute();
			return (int) $q->fetchColumn();
		};
		$this->check('a word before an underscore does not match the whole word (as MySQL)', 0, $matches('test_fme_content example', 'test'));
		$this->check('a word after an underscore does not match either', 0, $matches('test_fme_content example', '+fme'));
		$this->check('a word with underscores matches', 1, $matches('test_fme_content example', '+test_fme_content'));
		$this->check('a prefix with an underscore', 1, $matches('test_fme_content example', '+test_fme*'));
		$this->check('a phrase with an underscore word', 1, $matches('test_fme_content example', '"test_fme_content example"'));
		$this->check('leading and trailing underscores are part of the word', 1, $matches('def __init__(self)', '+__init__'));
		$this->check('in natural language mode too', [0, 1], [$matches('wire_test_repeater', 'wire', false), $matches('wire_test_repeater', 'wire_test_repeater', false)]);
		$this->check('other separators still split words', 1, $matches('foo-bar.example', '+bar +example'));

		// a site whose stored tsvector columns were made by pw_fulltext_v1's pw_tsvector() (split at "_")
		$table = WireTests::fieldPrefix . 'pgsql_underscore';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("CREATE TABLE `$table` (`pages_id` int unsigned NOT NULL, `data` mediumtext NOT NULL, " .
			"`modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`pages_id`), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$database->exec("INSERT INTO `$table` (pages_id, data) VALUES (1, 'test_fme_content example'), (2, 'plain words')");
		$schema = (string) $pdo->query('SELECT current_schema()')->fetchColumn();
		$s = '"' . str_replace('"', '""', $schema) . '"';
		$current = $pdo->query("SELECT pg_get_functiondef('$s.pw_tsvector(text)'::regprocedure)")->fetchColumn();
		$config = str_replace("'", "''", "$s.pw_search");
		$pdo->exec("CREATE OR REPLACE FUNCTION $s.pw_tsvector(text) RETURNS tsvector LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS " .
			"\$fn\$ SELECT to_tsvector('$config'::regconfig, regexp_replace(\$1, '[-.@:]+|(?<!<)/+', ' ', 'g')) \$fn\$"); // v1
		$pdo->exec("UPDATE \"$table\" SET data = data || ''"); // stored with v1's words
		$pdo->exec("UPDATE \"$table\" SET modified = '2020-01-02 03:04:05'");
		$vector = function($id) use($pdo, $table) { return (string) $pdo->query("SELECT data__tsv::text FROM \"$table\" WHERE pages_id = $id")->fetchColumn(); };
		$this->check('a v1 site has "test" and "fme" as words', true, strpos($vector(1), "'test':1") !== false && strpos($vector(1), "'fme':2") !== false);
		$pdo->exec("DROP FUNCTION IF EXISTS $s." . WireDatabasePgsqlTranslator::fulltextMarker . '()');
		$pdo->exec("CREATE OR REPLACE FUNCTION $s.pw_fulltext_v1() RETURNS int LANGUAGE sql IMMUTABLE AS 'SELECT 1'");
		$result = WireDatabasePgsqlTranslator::setupFulltext(
			function($sql) use($pdo) { $pdo->exec($sql); },
			function($sql) use($pdo) { return $pdo->query($sql)->fetchColumn(); },
			function($sql) use($pdo) { return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC); }
		);
		$this->check('setup of this version on a v1 site succeeds', ['fulltext' => true, 'error' => ''], $result);
		$this->check('the stored tsvector of a row with "_" is rebuilt', false, strpos($vector(1), "'test':1") !== false);
		$this->check('a row without "_" is left as it was', "'plain':1 'words':2", $vector(2));
		$this->check('rebuilding does not touch ON UPDATE CURRENT_TIMESTAMP columns', ['2020-01-02 03:04:05'], array_values(array_unique($pdo->query("SELECT modified::text FROM \"$table\"")->fetchAll(\PDO::FETCH_COLUMN))));
		$ids = function($value) use($database, $table) {
			$q = $database->prepare("SELECT pages_id FROM `$table` WHERE MATCH(data) AGAINST(:v IN BOOLEAN MODE) ORDER BY pages_id");
			$q->bindValue(':v', $value);
			$q->execute();
			return array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
		};
		$this->check('MATCH after the upgrade: a word with underscores', [[1], []], [$ids('+test_fme_content'), $ids('+test')]);
		$this->check('the v1 marker is gone', false, (bool) $pdo->query("SELECT to_regprocedure('$s.pw_fulltext_v1()') IS NOT NULL")->fetchColumn());
		$this->check('the current marker is there', true, (bool) $pdo->query("SELECT to_regprocedure('$s." . WireDatabasePgsqlTranslator::fulltextMarker . "()') IS NOT NULL")->fetchColumn());
		$database->exec("DROP TABLE IF EXISTS `$table`");
		if(strpos((string) $current, "'_'") === false) $pdo->exec($current); // (a failed run left v1's function: put back what was there)
	}

	/**
	 * ON UPDATE CURRENT_TIMESTAMP changes the column only when an UPDATE changes the row, also with a FULLTEXT key (live, pgsql only)
	 *
	 * A stored tsvector column is not computed yet when the trigger runs, so it must not count as a change.
	 *
	 */
	protected function testOnUpdateNoChange() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$pdo = $database->pdo();
		$table = WireTests::fieldPrefix . 'pgsql_onupdate_ft';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec("CREATE TABLE `$table` (`pages_id` int unsigned NOT NULL, `data` mediumtext NOT NULL, " .
			"`modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`pages_id`), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$database->exec("INSERT INTO `$table` (pages_id, data) VALUES (1, 'hello')");
		$database->exec("UPDATE `$table` SET modified = '2020-01-02 03:04:05'");
		$modified = function() use($pdo, $table) { return (string) $pdo->query("SELECT modified::text FROM \"$table\"")->fetchColumn(); };
		$database->exec("UPDATE `$table` SET data = 'hello'");
		$this->check('an UPDATE that changes nothing leaves the timestamp', '2020-01-02 03:04:05', $modified());
		$database->exec("UPDATE `$table` SET data = 'hello there'");
		$this->check('an UPDATE that changes the row sets it', true, $modified() !== '2020-01-02 03:04:05');
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}

	/**
	 * A WireDatabaseBackup round trip (CREATE TABLEs from the schema log), and MySQL's whole-second times (live, pgsql only)
	 *
	 */
	protected function testBackups() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$a = WireTests::fieldPrefix . 'pgsql_bk_text';
		$b = WireTests::fieldPrefix . 'pgsql_bk_misc';
		foreach([$a, $b] as $t) $database->exec("DROP TABLE IF EXISTS `$t`");
		$database->exec("CREATE TABLE `$a` (`pages_id` int unsigned NOT NULL, `data` mediumtext NOT NULL, PRIMARY KEY (`pages_id`), KEY `data_exact` (`data`(250)), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$database->exec("CREATE TABLE `$b` (`id` int unsigned NOT NULL AUTO_INCREMENT, `name` varchar(128) NOT NULL DEFAULT '', `j` JSON, `d` datetime NOT NULL DEFAULT '0000-00-00 00:00:00', `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, `n` decimal(10,2) DEFAULT NULL, `status` enum('on','off') NOT NULL DEFAULT 'on', PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$texts = [1 => "O'Brien \\ back\\slash \"quoted\"", 2 => 'Émile Zola — ünïcödé ✓', 3 => "line1\nline2\ttab", 4 => '', 5 => '<p>Hello World</p>'];
		foreach($texts as $id => $text) {
			$q = $database->prepare("INSERT INTO `$a` (pages_id, data) VALUES (:id, :d)");
			$q->bindValue(':id', $id, \PDO::PARAM_INT);
			$q->bindValue(':d', $text);
			$q->execute();
		}
		foreach([['a', '{"x":[1,2],"s":"it\'s"}', '2020-01-02 03:04:05', '12.50', 'on'], ['b', null, '0000-00-00 00:00:00', null, 'off'], ["c'q", '[]', '2021-06-07 00:00:00.6', '-3.75', 'on']] as $r) {
			$q = $database->prepare("INSERT INTO `$b` (name, j, d, n, status) VALUES (:a, :j, :d, :n, :s)");
			foreach([':a' => $r[0], ':j' => $r[1], ':d' => $r[2], ':n' => $r[3], ':s' => $r[4]] as $k => $val) $q->bindValue($k, $val);
			$q->execute();
		}
		// MySQL's DATETIME and TIMESTAMP hold whole seconds, rounding a fraction
		$this->check('DATETIME rounds a fraction to the second, as MySQL does', '2021-06-07 00:00:01', (string) $database->query("SELECT d FROM `$b` WHERE id=3")->fetchColumn());
		$this->check('TIMESTAMP DEFAULT CURRENT_TIMESTAMP has whole seconds', 1, preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) $database->query("SELECT ts FROM `$b` WHERE id=1")->fetchColumn()));
		$snapshot = function() use($database, $a, $b) {
			return [
				$database->query("SELECT * FROM `$a` ORDER BY pages_id")->fetchAll(\PDO::FETCH_ASSOC),
				$database->query("SELECT id, name, j, d, n, status FROM `$b` ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC),
				$database->getIndexes($a, true), $database->getColumns($b),
			];
		};
		$before = $snapshot();
		$backups = $database->backups();
		$path = $this->wire()->config->paths->cache . 'WireTestsBackups/';
		$this->wire()->files->mkdir($path, true);
		$backups->setPath($path);
		$file = $backups->backup(['tables' => [$a, $b], 'filename' => 'pgsql-backup-test.sql']);
		$this->check('backup of PostgreSQL tables', true, is_string($file) && filesize($file) > 0);
		if(!$file) return;
		$dump = file_get_contents($file);
		$this->check('the dump has MySQL CREATE TABLEs from the schema log (UNSIGNED, prefix length, ENUM)', [1, 1, 1],
			[preg_match('/int(\(\d+\))? unsigned/i', $dump), preg_match('/`data`\(250\)/', $dump), preg_match("/enum\('on','off'\)/i", $dump)]);
		$this->check('the dump has no stored tsvector columns', 0, preg_match('/__tsv/', $dump));
		foreach([$a, $b] as $t) $database->exec("DROP TABLE IF EXISTS `$t`");
		$this->check('restore', true, $backups->restore($file));
		$after = $snapshot();
		$this->check('restored text rows (quotes, backslashes, unicode, newlines, empty)', $before[0], $after[0]);
		$this->check('restored rows (JSON, zero date as NULL, decimals, ENUM)', $before[1], $after[1]);
		$this->check('restored indexes', $before[2], $after[2]);
		$this->check('restored columns', $before[3], $after[3]);
		$database->exec("INSERT INTO `$b` (name) VALUES ('new')");
		$this->check('the sequence continues after the restored ids', 4, (int) $database->query("SELECT id FROM `$b` WHERE name='new'")->fetchColumn());
		$this->check('MATCH works on the restored FULLTEXT key', [2], array_map('intval', $database->query("SELECT pages_id FROM `$a` WHERE MATCH(data) AGAINST('+zola' IN BOOLEAN MODE)")->fetchAll(\PDO::FETCH_COLUMN)));
		$database->exec("UPDATE `$b` SET ts='2000-01-01 00:00:00' WHERE id=1");
		$database->exec("UPDATE `$b` SET name='a2' WHERE id=1");
		$this->check('ON UPDATE CURRENT_TIMESTAMP works after the restore', true, strtotime((string) $database->query("SELECT ts FROM `$b` WHERE id=1")->fetchColumn()) > time() - 60);
		foreach([$a, $b] as $t) $database->exec("DROP TABLE IF EXISTS `$t`");
		unlink($file);
	}

	/**
	 * MySQL functions that module SQL uses, with MySQL's results (checked against MariaDB 12 and MySQL's documentation)
	 *
	 */
	protected function testMysqlFunctions() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$expected = [
			'CURDATE() = DATE(NOW())' => '1',
			'LENGTH(NOW())' => '19',
			'LENGTH(NOW()) = LENGTH(DATE_FORMAT(NOW(), \'%Y-%m-%d %H:%i:%s\'))' => '1',
			'LENGTH(CURTIME())' => '8',
			'LENGTH(UTC_TIMESTAMP())' => '19',
			'LENGTH(CURRENT_DATE())' => '10',
			'LENGTH(SYSDATE())' => '19',
			'YEAR(\'2021-06-07 08:09:10\')' => '2021',
			'MONTH(\'2021-06-07 08:09:10\')' => '6',
			'DAY(\'2021-06-07 08:09:10\')' => '7',
			'DAYOFMONTH(\'2021-06-07\')' => '7',
			'HOUR(\'2021-06-07 08:09:10\')' => '8',
			'MINUTE(\'2021-06-07 08:09:10\')' => '9',
			'SECOND(\'2021-06-07 08:09:10\')' => '10',
			'DAYOFWEEK(\'2021-06-07\')' => '2',
			'DAYOFWEEK(\'2021-06-06\')' => '1',
			'WEEKDAY(\'2021-06-07\')' => '0',
			'WEEKDAY(\'2021-06-06\')' => '6',
			'DAYOFYEAR(\'2021-06-07\')' => '158',
			'QUARTER(\'2021-06-07\')' => '2',
			'DAYNAME(\'2021-06-07\')' => 'Monday',
			'MONTHNAME(\'2021-06-07\')' => 'June',
			'DATE(\'2021-06-07 08:09:10\')' => '2021-06-07',
			'LAST_DAY(\'2021-02-10\')' => '2021-02-28',
			'LAST_DAY(\'2020-02-10\')' => '2020-02-29',
			'DATEDIFF(\'2021-06-07\', \'2021-05-01\')' => '37',
			'DATEDIFF(\'2021-06-07 23:00:00\', \'2021-06-08 01:00:00\')' => '-1',
			'TIMESTAMPDIFF(MONTH, \'2021-01-31\', \'2021-02-28\')' => '0',
			'TIMESTAMPDIFF(MONTH, \'2021-01-15\', \'2021-03-16\')' => '2',
			'TIMESTAMPDIFF(DAY, \'2021-01-31\', \'2021-02-28\')' => '28',
			'TIMESTAMPDIFF(YEAR, \'2000-02-29\', \'2021-02-28\')' => '20',
			'TIMESTAMPDIFF(HOUR, \'2021-06-07 08:00:00\', \'2021-06-07 10:30:00\')' => '2',
			'TIMESTAMPDIFF(MINUTE, \'2021-06-07 08:00:00\', \'2021-06-07 10:30:00\')' => '150',
			'TIMESTAMPDIFF(SECOND, \'2021-06-07 08:00:00\', \'2021-06-07 08:00:59\')' => '59',
			'TIMESTAMPDIFF(WEEK, \'2021-06-01\', \'2021-06-15\')' => '2',
			'TIMESTAMPDIFF(DAY, \'2021-06-15\', \'2021-06-01\')' => '-14',
			'TIMESTAMPDIFF(MONTH, \'2021-03-16\', \'2021-01-15\')' => '-2',
			'STR_TO_DATE(\'07/06/2021\', \'%d/%m/%Y\')' => '2021-06-07',
			'STR_TO_DATE(\'2021-06-07 08:09:10\', \'%Y-%m-%d %H:%i:%s\')' => '2021-06-07 08:09:10',
			'FIND_IN_SET(\'b\', \'a,b,c\')' => '2',
			'FIND_IN_SET(\'B\', \'a,b,c\')' => '2',
			'FIND_IN_SET(\'x\', \'a,b\')' => '0',
			'FIND_IN_SET(NULL, \'a\')' => NULL,
			'FIND_IN_SET(\'a\', \'\')' => '0',
			'INSTR(\'foobar\', \'bar\')' => '4',
			'INSTR(\'FooBar\', \'bar\')' => '4',
			'INSTR(\'abc\', \'x\')' => '0',
			'CHAR_LENGTH(\'é\')' => '1',
			'CHARACTER_LENGTH(\'ab\')' => '2',
			'LENGTH(\'é\')' => '2',
			'LENGTH(123)' => '3',
			'LENGTH(NULL)' => NULL,
			'CONCAT(\'a\', NULL)' => NULL,
			'CONCAT(\'a\', 1, \'b\')' => 'a1b',
			'CONCAT(\'x\')' => 'x',
			'CONCAT_WS(\'-\', \'a\', NULL, \'b\')' => 'a-b',
			'LCASE(\'AbC\')' => 'abc',
			'UCASE(\'AbC\')' => 'ABC',
			'MID(\'abcdef\', 2, 3)' => 'bcd',
			'SPACE(3)' => '   ',
			'TRUNCATE(3.789, 1)' => '3.7',
			'TRUNCATE(-3.789, 1)' => '-3.7',
			'TRUNCATE(1234.5, -2)' => '1200',
			'LOG(EXP(2))' => '2',
			'LOG(2, 8)' => '3',
			'LOG10(1000)' => '3',
			'LOG2(8)' => '3',
			'LN(EXP(1))' => '1',
			'SHA2(\'abc\', 256)' => 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad',
			'SHA2(\'abc\', 512)' => 'ddaf35a193617abacc417349ae20413112e6fa4e89a97ea20a9eeee64b55d39a2192992a274fc1a836ba3c23a3feebbd454d4423643ce80e2a9ac94fa54ca49f',
			'ISNULL(NULL)' => '1',
			'ISNULL(1)' => '0',
			'GREATEST(1, NULL)' => NULL,
			'LEAST(2, 5, 1)' => '1',
			'GREATEST(\'a\', \'b\')' => 'b',
			'CONVERT(\'abc\' USING utf8mb4)' => 'abc',
			'LENGTH(UUID())' => '36',
			'IFNULL(NULL, \'x\')' => 'x',
			'NULLIF(1, 1)' => NULL,
		];
		foreach($expected as $expr => $value) {
			try {
				$actual = $database->query("SELECT $expr")->fetchColumn();
			} catch(\Exception $e) {
				$actual = 'error: ' . $e->getMessage();
			}
			// numbers compare as numbers (PostgreSQL prints numeric results with more digits)
			if($value !== null && $actual !== null && is_numeric($value) && is_numeric($actual) && abs((float) $value - (float) $actual) < 1e-9) $actual = $value;
			$this->check("SELECT $expr", $value, $actual === null ? null : (string) $actual);
		}
	}

	/**
	 * Sorting as MySQL does: NULLs first ascending, and a repeater's item id list joined as its first id (live, pgsql only)
	 *
	 */
	protected function testSortParity() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'pgsql') return;
		$a = WireTests::fieldPrefix . 'pgsql_sort_a';
		$b = WireTests::fieldPrefix . 'pgsql_sort_b';
		foreach([$a, $b] as $t) $database->exec("DROP TABLE IF EXISTS `$t`");
		$database->exec("CREATE TABLE `$a` (`pages_id` int NOT NULL, `items` text NOT NULL, PRIMARY KEY (`pages_id`))");
		$database->exec("CREATE TABLE `$b` (`pages_id` int NOT NULL, `v` int, PRIMARY KEY (`pages_id`))");
		$database->exec("INSERT INTO `$a` (pages_id, items) VALUES (1, '12,13'), (2, '11'), (3, ''), (4, '14')");
		$database->exec("INSERT INTO `$b` (pages_id, v) VALUES (11, 5), (12, 3), (13, 9), (14, NULL)");
		$col = function($sql) use($database) { return array_map(function($v) { return $v === null ? null : (int) $v; }, $database->query($sql)->fetchAll(\PDO::FETCH_COLUMN)); };
		$this->check("a text id list joined to an integer column uses its first id, as MySQL does", [1, 2, 4],
			$col("SELECT a.pages_id FROM `$a` AS a JOIN `$b` AS b ON a.items=b.pages_id ORDER BY a.pages_id"));
		$this->check('sorting by the joined subfield (repeater sort), NULLs first', [4, 1, 2],
			$col("SELECT a.pages_id FROM `$a` AS a LEFT JOIN `$b` AS b ON a.items=b.pages_id WHERE a.pages_id!=3 ORDER BY b.v, a.pages_id"));
		$this->check('descending, NULLs last', [2, 1, 4],
			$col("SELECT a.pages_id FROM `$a` AS a LEFT JOIN `$b` AS b ON a.items=b.pages_id WHERE a.pages_id!=3 ORDER BY b.v DESC, a.pages_id"));
		$this->check('a nullable column of the table itself', [null, 3, 5, 9], $col("SELECT v FROM `$b` ORDER BY v"));
		$this->check('... descending', [9, 5, 3, null], $col("SELECT v FROM `$b` ORDER BY v DESC"));
		foreach([$a, $b] as $t) $database->exec("DROP TABLE IF EXISTS `$t`");
		// through the API: sort by a field some pages have no value for
		$parent = $this->getTestPage();
		if(!$parent || !$parent->id) return;
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$field = $fields->get('pgsql_sort_num');
		if(!$field) {
			$field = new Field();
			$field->type = $this->wire()->modules->get('FieldtypeInteger');
			$field->name = 'pgsql_sort_num';
			$fields->save($field);
		}
		$fieldgroup = $parent->template->fieldgroup;
		$added = !$fieldgroup->hasField($field);
		if($added) { $fieldgroup->add($field); $fieldgroup->save(); }
		$created = [];
		foreach(['b' => 2, 'none' => null, 'a' => 1] as $name => $value) {
			$p = $pages->newPage(['template' => $parent->template, 'parent' => $parent, 'name' => "pgsql-sort-$name", 'title' => $name]);
			if($value !== null) $p->set('pgsql_sort_num', $value);
			$pages->save($p);
			$created[] = $p;
		}
		$this->check('sort=field puts pages without a value first, as on MySQL', 'none|a|b', $pages->find("parent=$parent, name^=pgsql-sort-, sort=pgsql_sort_num, include=all")->implode('|', 'title'));
		$this->check('sort=-field puts them last', 'b|a|none', $pages->find("parent=$parent, name^=pgsql-sort-, sort=-pgsql_sort_num, include=all")->implode('|', 'title'));
		foreach($created as $p) $pages->delete($p, true);
		if($added) { $fieldgroup->remove($field); $fieldgroup->save(); }
		$fields->delete($field);
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
		$this->check('no UPDATE ORDER BY', false, $dialect->supportsUpdateOrderBy());
		$this->check('JSON is not reported before the functions are known to exist (not connected)', false, $dialect->supportsJson());
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
			'INSERT INTO "s" ("id", "lang") VALUES (:id, :lang) ON CONFLICT ("id", "lang") DO UPDATE SET "lang"=excluded."lang", "ts"=localtimestamp(0)',
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
			'INSERT INTO "t" ("id", "qty") VALUES (:id, :qty) ON CONFLICT ("id") DO UPDATE SET "qty"="t"."qty"+1, "ts"=localtimestamp(0)',
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
			"INSERT INTO \"t\" (\"id\", \"data\") VALUES (:id, :data) ON CONFLICT (\"id\") DO UPDATE SET \"data\"=((\"t\".\"data\")::text || (' more data')::text)",
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
		// messages are shaped like pdo_mysql's, since some code matches on them (i.e. PagePathHistory checks for '1054')
		$e = WireDatabaseDialectPgsql::mysqlException($make('42703', 'SQLSTATE[42703]: Undefined column: 7 ERROR:  column t.bar does not exist'));
		$this->check('undefined column message reads like MySQL\'s', 0, strpos($e->getMessage(), "SQLSTATE[42S22]: Column not found: 1054 Unknown column 't.bar' in 'field list'"));
		$e = WireDatabaseDialectPgsql::mysqlException($make('42P01', 'SQLSTATE[42P01]: Undefined table: 7 ERROR:  relation "foo" does not exist'));
		$this->check('undefined table message reads like MySQL\'s', 0, strpos($e->getMessage(), "SQLSTATE[42S02]: Base table or view not found: 1146 Table 'foo' doesn't exist"));
		$e = WireDatabaseDialectPgsql::mysqlException($make('23505', 'SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "pages__name_parent_id"'));
		$this->check('duplicate key message reads like MySQL\'s', 0, strpos($e->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry'));
		$this->check('mapped message keeps the PostgreSQL original', true, strpos($e->getMessage(), 'pages__name_parent_id') !== false);
		$plain = $make('22P02', 'invalid input syntax');
		$this->check('other errors pass through unchanged', true, $plain === WireDatabaseDialectPgsql::mysqlException($plain));
		$dialect = new WireDatabaseDialectPgsql($this->wire()->database);
		$this->check('deadlock is retryable', 'deadlock', $dialect->getRetryableErrorType($make('40P01', 'deadlock detected')));
		$this->check('serialization failure is retryable', 'deadlock', $dialect->getRetryableErrorType($make('40001', 'could not serialize')));
		$this->check('admin shutdown is retryable as gone-away', 'gone-away', $dialect->getRetryableErrorType($make('57P01', 'terminating connection')));
		$this->check('connection exception is retryable as comm-failure', 'comm-failure', $dialect->getRetryableErrorType($make('08006', 'connection failure')));
		$this->check('other errors not retryable', '', $dialect->getRetryableErrorType($make('23505', 'dup')));
		// libpq reports a dropped connection as a general error (HY000) rather than an 08 state
		$this->check('server closed the connection is retryable as gone-away', 'gone-away', $dialect->getRetryableErrorType($make('HY000', 'SQLSTATE[HY000]: General error: 7 server closed the connection unexpectedly')));
		$this->check('no connection to the server is retryable as gone-away', 'gone-away', $dialect->getRetryableErrorType($make('HY000', 'SQLSTATE[HY000]: General error: 7 no connection to the server')));
		$this->check('could not connect is retryable as comm-failure', 'comm-failure', $dialect->getRetryableErrorType($make('HY000', 'SQLSTATE[HY000]: General error: 7 could not connect to server: Connection refused')));
		$this->check('other general errors not retryable', '', $dialect->getRetryableErrorType($make('HY000', 'SQLSTATE[HY000]: General error: 7 something else')));
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
		// the same through bindValue(): the clear error comes from execute(), not a driver error at bind time
		$q = $database->prepare("CREATE TABLE `{$table}_3` (`id` INT NOT NULL, PRIMARY KEY (`id`), KEY `id2` (`id`))");
		$bindError = '';
		try { $q->bindValue(':x', 1); } catch(\PDOException $e) { $bindError = $e->getMessage(); }
		$this->check('bindValue() on multi-statement SQL does not fail on its own', '', $bindError);
		$threw = false;
		try { $q->execute(); } catch(\PDOException $e) { $threw = strpos($e->getMessage(), 'multiple') !== false; }
		$this->check('execute() after bindValue() on multi-statement SQL throws clearly', true, $threw);

		// MODIFY text to a number converts values as MySQL does, and resets NULL/DEFAULT as MySQL's MODIFY does
		$database->exec("DROP TABLE IF EXISTS `{$table}_m`");
		$database->exec("CREATE TABLE `{$table}_m` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `v` VARCHAR(20) NOT NULL DEFAULT '', PRIMARY KEY (`id`))");
		$database->exec("INSERT INTO `{$table}_m` (v) VALUES ('12'), ('abc'), ('')");
		$database->exec("ALTER TABLE `{$table}_m` MODIFY `v` INT NOT NULL DEFAULT 0");
		$this->check('MODIFY VARCHAR to INT converts like MySQL', [12, 0, 0], array_map('intval', $database->query("SELECT v FROM `{$table}_m` ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN)));
		$database->exec("ALTER TABLE `{$table}_m` MODIFY `v` INT");
		$cols = $database->getColumns("{$table}_m", true);
		$this->check('MODIFY without NOT NULL makes the column nullable', true, $cols['v']['null']);
		$database->exec("ALTER TABLE `{$table}_m` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
		$database->exec("INSERT INTO `{$table}_m` (v) VALUES (1)");
		$this->check('MODIFY of the AUTO_INCREMENT key keeps its sequence', 4, (int) $database->lastInsertId());

		// index names over PostgreSQL's 63 bytes: distinct, and reported by their MySQL names
		$long = substr($table . '_' . str_repeat('x', 60), 0, 60);
		$database->exec("DROP TABLE IF EXISTS `$long`");
		$database->exec("DROP TABLE IF EXISTS `{$long}r`");
		$database->exec("CREATE TABLE `$long` (`pages_id` int NOT NULL, `data` text NOT NULL, PRIMARY KEY (`pages_id`), KEY `data_exact` (`data`(250)), KEY `data` (`data`(100)), UNIQUE KEY `uq` (`data`(50), `pages_id`))");
		$indexes = $database->getIndexes($long, true);
		$this->check('long index names are reported by their MySQL names (the folded companion of uq hidden)', ['PRIMARY', 'data', 'data_exact', 'uq'], array_keys($indexes));
		$this->check('indexExists() finds a long index', true, $database->indexExists($long, 'data_exact'));
		$database->exec("ALTER TABLE `$long` RENAME INDEX `data` TO `data2`");
		$this->check('renaming a long index keeps its MySQL name', true, $database->indexExists($long, 'data2') && !$database->indexExists($long, 'data'));
		$database->exec("RENAME TABLE `$long` TO `{$long}r`");
		$this->check('renaming a table with long index names keeps them', ['PRIMARY', 'data2', 'data_exact', 'uq'], array_keys($database->getIndexes("{$long}r", true)));
		$database->exec("ALTER TABLE `{$long}r` DROP INDEX `data_exact`");
		$this->check('dropping a long index', false, $database->indexExists("{$long}r", 'data_exact'));
		$database->exec("DROP TABLE IF EXISTS `{$long}r`");
		$database->exec("DROP TABLE IF EXISTS `{$table}_m`");
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
