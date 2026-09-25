<?php namespace ProcessWire;

/**
 * Tests for MySQL-to-PostgreSQL translation
 *
 * Translation checks run on any database (they only construct the translator). Live checks
 * run when the test site itself uses the pgsql dialect.
 *
 */
class WireTest_WireDatabasePgsqlTranslator extends WireTest {

	/** @var WireDatabasePgsqlTranslator */
	protected $translator;

	public function init() {
		$this->translator = new WireDatabasePgsqlTranslator(); // no PDO: schema-unaware translations only
	}

	public function execute() {
		$this->testLiteralsAndIdentifiers();
		$this->testExpressions();
		$this->testDdl();
		$this->testDml();
		$this->testShow();
		$this->testFolding();
		$this->testJson();
	}

	/**
	 * Case- and accent-insensitive text comparisons and sorting (pw_fold), as MySQL's default collations
	 *
	 */
	/**
	 * MySQL JSON functions, translated to the pw_json_*() functions over jsonb (see setupJson())
	 *
	 */
	protected function testJson() {
		$tr = new WireDatabasePgsqlTranslator();
		$t = function($sql) use($tr) { return implode(";\n", $tr->translateStatements($sql)); };
		$sql = "SELECT JSON_EXTRACT(data, '$.a') FROM t";
		$this->check('JSON functions are left alone until the functions exist', $sql, $t($sql));
		$tr->setJsonAvailable(true);
		$this->check('JSON_EXTRACT', "SELECT pw_json_extract(pw_json(data), '$.a') FROM t", $t($sql));
		// the shapes FieldtypeCustom and FormBuilder use (processwire-requests#609)
		$this->check('JSON_UNQUOTE(LOWER(JSON_EXTRACT())) compares text',
			"SELECT id FROM t WHERE pw_json_unquote(lower((pw_json_extract(pw_json(data), '$.name'))::text))=:v",
			$t('SELECT id FROM t WHERE JSON_UNQUOTE(LOWER(JSON_EXTRACT(data, "$.name")))=:v'));
		$this->check('JSON_CONTAINS as a condition is made boolean',
			'SELECT id FROM t WHERE (pw_json_contains(pw_json(data), pw_json(:v))) <> 0',
			$t('SELECT id FROM t WHERE JSON_CONTAINS(data, :v)'));
		$this->check('JSON_CONTAINS with a path',
			"SELECT id FROM t WHERE (pw_json_contains(pw_json(data), pw_json(:v), '$.tags')) <> 0 AND id>1",
			$t("SELECT id FROM t WHERE JSON_CONTAINS(data, :v, '$.tags') AND id>1"));
		$this->check('JSON_LENGTH with a path',
			"SELECT id FROM t WHERE pw_json_length(pw_json(data), '$.name')>0",
			$t("SELECT id FROM t WHERE JSON_LENGTH(data, '$.name')>0"));
		$this->check('renaming a subfield: JSON_SET(JSON_REMOVE(), JSON_EXTRACT()) keeps the extracted value as JSON',
			'UPDATE t SET data=pw_json_set(pw_json(pw_json_remove(pw_json(data), :old)), :new, pw_json_value(pw_json_extract(pw_json(data), :old)))',
			$t('UPDATE t SET data=JSON_SET(JSON_REMOVE(data, :old), :new, JSON_EXTRACT(data, :old))'));
		$this->check('JSON_SET with several path/value pairs applies them in order',
			"SELECT pw_json_set(pw_json(pw_json_set(pw_json(d), '$.a', pw_json_value(1))), '$.b', pw_json_value('x')) FROM t",
			$t("SELECT JSON_SET(d, '$.a', 1, '$.b', 'x') FROM t"));
		$this->check('JSON_INSERT and JSON_REPLACE',
			"SELECT pw_json_insert(pw_json(d), '$.a', pw_json_value(1)), pw_json_replace(pw_json(d), '$.a', pw_json_value(2)) FROM t",
			$t("SELECT JSON_INSERT(d, '$.a', 1), JSON_REPLACE(d, '$.a', 2) FROM t"));
		$this->check('JSON_REMOVE with several paths',
			"SELECT pw_json_remove(pw_json(pw_json_remove(pw_json(d), '$.a')), '$.b') FROM t",
			$t("SELECT JSON_REMOVE(d, '$.a', '$.b') FROM t"));
		$this->check('JSON_ARRAY and JSON_OBJECT',
			"SELECT jsonb_build_array(pw_json_value(1), pw_json_value('a')), jsonb_build_object(('k')::text, pw_json_value(true)) FROM t",
			$t("SELECT JSON_ARRAY(1, 'a'), JSON_OBJECT('k', true) FROM t"));
		$this->check('JSON_QUOTE', "SELECT to_jsonb(('a\"b')::text)::text FROM t", $t("SELECT JSON_QUOTE('a\"b') FROM t"));
		$this->check('JSON_VALID as a condition is made boolean',
			'SELECT id FROM t WHERE (pw_json_valid(data)) <> 0',
			$t('SELECT id FROM t WHERE JSON_VALID(data)'));
		// on a jsonb column, JSON_CONTAINS() as a condition is narrowed by the GIN and nested-document indexes, then exact
		$tr->setSchemaCache(['field_c' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'jsonb']]]);
		$this->check('JSON_CONTAINS on a jsonb column as a condition: indexed jsonpath or nested document, then exact',
			'SELECT pages_id FROM field_c WHERE ((data @@ pw_json_contains_path(pw_json(:v)) OR pw_json_nested(data)) AND pw_json_contains(data, pw_json(:v)) = 1)',
			$t('SELECT pages_id FROM field_c WHERE JSON_CONTAINS(data, :v)'));
		$this->check('JSON_CONTAINS with a key path: the path as an object for the index, the path itself for the exact test',
			"SELECT f.pages_id FROM field_c AS f WHERE ((f.data @@ pw_json_contains_path(jsonb_build_object('tags', pw_json(:v))) OR pw_json_nested(f.data)) AND pw_json_contains(f.data, pw_json(:v), '$.tags') = 1) AND f.pages_id>1",
			$t("SELECT f.pages_id FROM field_c AS f WHERE JSON_CONTAINS(f.data, :v, '$.tags') AND f.pages_id>1"));
		$this->check('JSON_CONTAINS with a nested quoted key path',
			"SELECT pages_id FROM field_c WHERE ((data @@ pw_json_contains_path(jsonb_build_object('a', jsonb_build_object('b c', pw_json('1')))) OR pw_json_nested(data)) AND pw_json_contains(data, pw_json('1'), '$.a.\"b c\"') = 1)",
			$t("SELECT pages_id FROM field_c WHERE JSON_CONTAINS(data, '1', '$.a.\"b c\"')"));
		$this->check('NOT JSON_CONTAINS keeps the function (NULL for a missing path stays NULL)',
			"SELECT pages_id FROM field_c WHERE NOT (pw_json_contains(pw_json(data), pw_json('1'), '$.a')) <> 0",
			$t("SELECT pages_id FROM field_c WHERE NOT JSON_CONTAINS(data, '1', '$.a')"));
		$this->check('NOT (JSON_CONTAINS(...)) too',
			"SELECT pages_id FROM field_c WHERE NOT ((pw_json_contains(pw_json(data), pw_json(:v))) <> 0)",
			$t('SELECT pages_id FROM field_c WHERE NOT (JSON_CONTAINS(data, :v))'));
		// JSON_EXTRACT() compared with an SQL string: the string is a JSON string, as MySQL converts it
		$this->check('JSON_EXTRACT = bound value compares it as a JSON string',
			"SELECT pages_id FROM field_c WHERE pw_json_contains(pw_json(data), pw_json(:json))=1 OR pw_json_extract(pw_json(data), '$.color')=pw_json_value((:value)::text)",
			$t("SELECT pages_id FROM field_c WHERE JSON_CONTAINS(data, :json)=1 OR JSON_EXTRACT(data, '$.color')=:value"));
		$this->check('a string literal on either side',
			"SELECT id FROM t WHERE pw_json_value(('green')::text) <> pw_json_extract(pw_json(d), '$.c')",
			$t("SELECT id FROM t WHERE 'green' <> JSON_EXTRACT(d, '$.c')"));
		$this->check('a number compares as a JSON number (unchanged)',
			"SELECT id FROM t WHERE pw_json_extract(pw_json(d), '$.n')>5",
			$t("SELECT id FROM t WHERE JSON_EXTRACT(d, '$.n')>5"));
		$this->check('JSON_CONTAINS compared to a value keeps the function (its 1/0/NULL result is used)',
			'SELECT pages_id FROM field_c WHERE pw_json_contains(pw_json(data), pw_json(:v))=1',
			$t('SELECT pages_id FROM field_c WHERE JSON_CONTAINS(data, :v)=1'));
		$this->check('JSON_CONTAINS with an array index path keeps the function',
			"SELECT pages_id FROM field_c WHERE (pw_json_contains(pw_json(data), pw_json(:v), '$.a[0]')) <> 0",
			$t("SELECT pages_id FROM field_c WHERE JSON_CONTAINS(data, :v, '$.a[0]')"));
		$statements = $tr->translateStatements('CREATE TABLE `field_c` (`pages_id` int NOT NULL, `data` JSON, PRIMARY KEY (`pages_id`))');
		$this->check('a JSON column gets a GIN index for containment', 'CREATE INDEX "field_c__data__json" ON "field_c" USING gin ("data" jsonb_path_ops)', isset($statements[1]) ? $statements[1] : null);
		$this->check('a JSON column also gets a partial index of documents with nested arrays', 'CREATE INDEX "field_c__data__jsonnest" ON "field_c" ((1)) WHERE pw_json_nested("data")', isset($statements[2]) ? $statements[2] : null);
		$this->check('ALTER ADD a JSON column adds its indexes', [
			'ALTER TABLE "field_c" ADD COLUMN "extra" jsonb',
			'CREATE INDEX "field_c__extra__json" ON "field_c" USING gin ("extra" jsonb_path_ops)',
			'CREATE INDEX "field_c__extra__jsonnest" ON "field_c" ((1)) WHERE pw_json_nested("extra")',
		], $tr->translateStatements('ALTER TABLE field_c ADD extra JSON'));
		$this->check('existing jsonb columns get the partial index concurrently',
			['CREATE INDEX CONCURRENTLY IF NOT EXISTS "field_c__data__jsonnest" ON "public"."field_c" ((1)) WHERE pw_json_nested("data")'],
			WireDatabasePgsqlTranslator::jsonIndexStatements('public', 'field_c', 'data', true));
		$tr->setSchemaCache(['field_c' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'jsonb']]]);
		$statements = $tr->translateStatements('ALTER TABLE field_c MODIFY data MEDIUMTEXT');
		$this->check('MODIFY away from JSON drops its indexes first (they cannot index text)', ['DROP INDEX IF EXISTS "field_c__data__json"', 'DROP INDEX IF EXISTS "field_c__data__jsonnest"'], array_slice($statements, 0, 2));
		// long names are shortened with a hash (see indexName()): made and dropped under the same names, MySQL names recorded
		$long = 'field_' . str_repeat('a', 50);
		$created = $tr->translateStatements("ALTER TABLE `$long` ADD `data` JSON");
		$gin = $tr->indexName($long, 'data__json');
		$nest = $tr->indexName($long, 'data__jsonnest');
		$this->check('long names: both JSON indexes are made under their shortened names', [true, true], [
			(bool) preg_grep('/^CREATE INDEX "' . preg_quote($gin, '/') . '" /', $created), (bool) preg_grep('/^CREATE INDEX "' . preg_quote($nest, '/') . '" /', $created)]);
		$this->check('long names: and their MySQL names are recorded', [
			'COMMENT ON INDEX "' . $gin . '" IS \'pw_index:data__json\'', 'COMMENT ON INDEX "' . $nest . '" IS \'pw_index:data__jsonnest\''], array_values(preg_grep('/^COMMENT ON INDEX/', $created)));
		$tr->setSchemaCache([$long => ['primary' => [], 'identity' => null, 'columns' => ['data' => 'jsonb']]]);
		$dropped = $tr->translateStatements("ALTER TABLE `$long` MODIFY `data` MEDIUMTEXT");
		$this->check('long names: MODIFY drops them under the same names', ['DROP INDEX IF EXISTS "' . $gin . '"', 'DROP INDEX IF EXISTS "' . $nest . '"'], array_slice($dropped, 0, 2));
		$tr->setJsonAvailable(false);
		$this->check('JSON functions left alone again when unavailable', $sql, $t($sql));
	}

	protected function testFolding() {
		$tr = new WireDatabasePgsqlTranslator();
		$t = function($sql) use($tr) { return implode(";\n", $tr->translateStatements($sql)); };
		$tr->setSchemaCache([
			'field_title' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'text']],
			'field_email' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'character varying']],
			'pages' => ['primary' => ['id'], 'identity' => 'id', 'columns' => ['id' => 'integer', 'name' => 'character varying', 'templates_id' => 'integer']],
			'caches' => ['primary' => ['name'], 'identity' => null, 'columns' => ['name' => 'character varying', 'data' => 'text']],
		]);
		$sql = "SELECT pages_id FROM field_title WHERE data=:v";
		$this->check('folding is off until enabled (the functions must exist)', 'SELECT pages_id FROM field_title WHERE data=:v', $t($sql));
		$tr->setFoldAvailable(true);

		// comparisons
		$this->check('= on a text column folds both sides', 'SELECT pages_id FROM field_title WHERE pw_fold(data)=pw_fold(:v)', $t($sql));
		$this->check('!= with a literal on a qualified varchar column',
			"SELECT f.pages_id FROM field_email AS f WHERE pw_fold(f.data)!=pw_fold('Admin@Example.com')",
			$t("SELECT f.pages_id FROM field_email AS f WHERE f.data!='Admin@Example.com'"));
		$this->check('empty-string checks stay plain (nothing to fold, and cheaper)',
			"SELECT pages_id FROM field_title WHERE data!='' AND data IS NOT NULL",
			$t("SELECT pages_id FROM field_title WHERE data!='' AND data IS NOT NULL"));
		$this->check('LIKE folds both sides (and needs no ILIKE)',
			'SELECT f.pages_id FROM field_title AS f WHERE pw_fold(f.data) LIKE pw_fold(:p) AND pw_fold(f.data) NOT LIKE pw_fold(:q)',
			$t('SELECT f.pages_id FROM field_title AS f WHERE f.data LIKE :p AND f.data NOT LIKE :q'));
		$this->check('REGEXP ignores case but not accents, as on MySQL; a folded match first lets the trigram index narrow the rows',
			'SELECT f.pages_id FROM field_title AS f WHERE (pw_fold(f.data) ~* pw_unaccent(:p) AND f.data ~* :p) AND f.data !~* :q',
			$t('SELECT f.pages_id FROM field_title AS f WHERE f.data REGEXP :p AND f.data NOT REGEXP :q'));
		$this->check('IN list folds every value',
			"SELECT id FROM pages WHERE pw_fold(name) IN (pw_fold('home'), pw_fold(:n))",
			$t("SELECT id FROM pages WHERE name IN ('home', :n)"));
		$this->check('numeric columns are not folded', 'SELECT id FROM pages WHERE templates_id=2 AND id IN (1, 2)', $t('SELECT id FROM pages WHERE templates_id=2 AND id IN (1, 2)'));
		$this->check('column-to-column comparisons (joins) are not folded, so they keep their indexes',
			'SELECT p.id FROM pages AS p JOIN field_email AS e ON e.data=p.name',
			$t('SELECT p.id FROM pages AS p JOIN field_email AS e ON e.data=p.name'));
		$this->check('UPDATE and DELETE conditions fold too',
			"UPDATE caches SET data=:d WHERE pw_fold(name)=pw_fold(:n);\nDELETE FROM caches WHERE pw_fold(name) LIKE pw_fold('Mod%')",
			$t('UPDATE caches SET data=:d WHERE name=:n') . ";\n" . $t("DELETE FROM caches WHERE name LIKE 'Mod%'"));

		// sorting
		$this->check('ORDER BY a text column sorts by the folded value',
			'SELECT pages_id FROM field_title ORDER BY pw_fold(data) DESC, pages_id',
			$t('SELECT pages_id FROM field_title ORDER BY data DESC, pages_id'));
		$this->check('ORDER BY a joined text column under GROUP BY folds its any_value()',
			'SELECT pages.id FROM pages LEFT JOIN field_title AS f ON f.pages_id=pages.id GROUP BY pages.id ORDER BY pw_fold(any_value(f.data))',
			$t('SELECT pages.id FROM pages LEFT JOIN field_title AS f ON f.pages_id=pages.id GROUP BY pages.id ORDER BY f.data'));
		$this->check('SELECT DISTINCT keeps its ORDER BY (PostgreSQL requires it in the select list)',
			'SELECT DISTINCT data FROM field_title ORDER BY data',
			$t('SELECT DISTINCT data FROM field_title ORDER BY data'));

		// indexes on text columns are built on the folded value
		$statements = $tr->translateStatements("CREATE TABLE `field_title` (`pages_id` int(10) unsigned NOT NULL, `data` text NOT NULL, PRIMARY KEY (`pages_id`), KEY `data_exact` (`data`(255)), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$this->check('prefix index on an unbounded text column becomes a hash index on the folded value (equality)',
			'CREATE INDEX "field_title__data_exact" ON "field_title" USING hash (pw_fold("data"))', $statements[1]);
		$this->check('FULLTEXT KEY becomes a trigram index on the folded value',
			'CREATE INDEX "field_title__data" ON "field_title" USING gin (pw_fold("data") gin_trgm_ops)', $statements[2]);
		$this->check('index on a varchar column is a btree on the folded value (equality and sort)',
			'CREATE INDEX "f__name" ON "f" (pw_fold("name"))',
			$tr->translateStatements('CREATE TABLE `f` (`name` varchar(250) NOT NULL, KEY `name` (`name`(191)))')[1]);
		$statements = $tr->translateStatements('CREATE TABLE `pages` (`id` int unsigned NOT NULL AUTO_INCREMENT, `parent_id` int NOT NULL, `name` varchar(128) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `name_parent_id` (`name`,`parent_id`))');
		$this->check('a unique key keeps its exact index (uniqueness as before) plus a folded companion for lookups',
			['CREATE UNIQUE INDEX "pages__name_parent_id" ON "pages" ("name", "parent_id")', 'CREATE INDEX "pages__name_parent_id__fold" ON "pages" (pw_fold("name"), "parent_id")'],
			array_slice($statements, 1));
		$statements = $tr->translateStatements('CREATE TABLE `caches` (`name` varchar(250) NOT NULL, `data` mediumtext NOT NULL, PRIMARY KEY (`name`))');
		$this->check('a text primary key gets a folded companion index',
			'CREATE INDEX "caches__primary__fold" ON "caches" (pw_fold("name"))', isset($statements[1]) ? $statements[1] : null);
		$this->check('a multi-column key with an unbounded text column folds its prefix',
			'CREATE INDEX "t__k" ON "t" ("pages_id", pw_fold(left("data", 100)))',
			$tr->translateStatements('CREATE TABLE `t` (`pages_id` int NOT NULL, `data` text NOT NULL, KEY `k` (`pages_id`, `data`(100)))')[1]);
		$this->check('DROP INDEX drops the folded companion too',
			['DROP INDEX IF EXISTS "t__u"', 'DROP INDEX IF EXISTS "t__u__fold"'], $tr->translateStatements('DROP INDEX u ON t'));
		$this->check('ALTER TABLE DROP INDEX drops the folded companion too',
			['DROP INDEX IF EXISTS "t__u"', 'DROP INDEX IF EXISTS "t__u__fold"'], $tr->translateStatements('ALTER TABLE t DROP INDEX u'));
		$this->check('ALTER TABLE RENAME INDEX renames the folded companion too',
			['ALTER INDEX "t__a" RENAME TO "t__b"', 'ALTER INDEX IF EXISTS "t__a__fold" RENAME TO "t__b__fold"'], $tr->translateStatements('ALTER TABLE t RENAME INDEX a TO b'));
		$this->check('DROP PRIMARY KEY drops its folded companion too',
			['ALTER TABLE "t" DROP CONSTRAINT IF EXISTS "t_pkey"', 'DROP INDEX IF EXISTS "t__primary__fold"'], $tr->translateStatements('ALTER TABLE t DROP PRIMARY KEY'));
		$tr->setSchemaCache(['caches' => ['primary' => ['name'], 'identity' => null, 'columns' => ['name' => 'character varying', 'data' => 'text']]]);
		$this->check('ALTER TABLE ADD INDEX on a known text column is folded',
			'CREATE INDEX "caches__n" ON "caches" (pw_fold("name"))', $tr->translateStatements('ALTER TABLE caches ADD INDEX n (name)')[0]);

		$tr->setFoldAvailable(false);
		$this->check('folding off again restores plain comparisons', 'SELECT pages_id FROM field_title WHERE data=:v', $t($sql));
	}

	protected function testDml() {
		$t = function($sql) { return $this->translator->translateStatements($sql); };
		// pretend schema for the schema-aware parts (no server needed)
		$this->translator->setSchemaCache([
			'caches' => ['primary' => ['name'], 'identity' => null],
			'modules' => ['primary' => ['id'], 'identity' => 'id'],
			'pages' => ['primary' => ['id'], 'identity' => 'id'],
			'field_title' => ['primary' => ['pages_id'], 'identity' => null],
		]);

		$this->check('INSERT SET becomes column/value lists', 'INSERT INTO modules (class, data) VALUES (:name, :data)', $t('INSERT INTO modules SET class=:name, data=:data')[0]);
		$this->check('INSERT IGNORE becomes ON CONFLICT DO NOTHING', "INSERT INTO t (a) VALUES (1) ON CONFLICT DO NOTHING", $t('INSERT IGNORE INTO t (a) VALUES (1)')[0]);
		$this->check('ON DUPLICATE KEY UPDATE uses the primary key as conflict target',
			'INSERT INTO caches ("name", "data", "expires") VALUES (:name, :data, :expires) ON CONFLICT ("name") DO UPDATE SET "data"=excluded."data", "expires"=excluded."expires"',
			$t('INSERT INTO caches (`name`, `data`, `expires`) VALUES (:name, :data, :expires) ON DUPLICATE KEY UPDATE `data`=VALUES(`data`), `expires`=VALUES(`expires`)')[0]);
		$this->check('ON DUPLICATE KEY UPDATE with expression qualifies bare columns', 'INSERT INTO caches (name, hits) VALUES (:n, 1) ON CONFLICT ("name") DO UPDATE SET hits=caches.hits+1', $t('INSERT INTO caches (name, hits) VALUES (:n, 1) ON DUPLICATE KEY UPDATE hits=hits+1')[0]);
		$this->check('ON DUPLICATE KEY UPDATE without known key throws', true, $this->throwsMatching(function() use($t) { $t('INSERT INTO unknown_table (a) VALUES (1) ON DUPLICATE KEY UPDATE a=VALUES(a)'); }, '/conflict target/'));
		$this->check('REPLACE INTO becomes upsert of all non-key columns', 'INSERT INTO caches (name, data) VALUES (:n, :d) ON CONFLICT ("name") DO UPDATE SET data=excluded.data', $t('REPLACE INTO caches (name, data) VALUES (:n, :d)')[0]);
		$statements = $t("INSERT INTO `pages` (`id`, `parent_id`, `name`) VALUES('1', '0', 'home')");
		$this->check('explicit id into identity table adds setval statement', 2, count($statements));
		$setval = function($table, $col) { return "SELECT setval(s::regclass, GREATEST((SELECT MAX(\"$col\") FROM \"$table\"), pg_sequence_last_value(s::regclass), 1)) FROM pg_get_serial_sequence('\"$table\"', '$col') AS s"; };
		$this->check('setval statement never moves the sequence backwards (after the highest rows were deleted)', $setval('pages', 'id'), $statements[1]);
		$this->check('insert without id has no setval', 1, count($t('INSERT INTO pages (parent_id, name) VALUES (1, :n)')));
		$this->check('native ON CONFLICT (dialect upsert output) passes through unchanged',
			'INSERT INTO "t" ("pages_id", "data") VALUES (:pages_id, :data) ON CONFLICT ("pages_id") DO UPDATE SET "data"=excluded."data"',
			$t('INSERT INTO "t" ("pages_id", "data") VALUES (:pages_id, :data) ON CONFLICT ("pages_id") DO UPDATE SET "data"=excluded."data"')[0]);
		$native = "INSERT INTO \"t\" (\"id\", \"data\") VALUES (1, 'ends with \\'), (2, 'x') ON CONFLICT (\"id\") DO UPDATE SET \"data\"='z'";
		$this->check('native upsert whose PostgreSQL literals end in a backslash still passes through', [$native], $t($native));
		$native = 'INSERT INTO "pages" ("id", "name") VALUES (:id, :name) ON CONFLICT ("id") DO UPDATE SET "name"=excluded."name"';
		$this->check('native upsert with an explicit identity value gets a setval follow-up', [$native, $setval('pages', 'id')], $t($native));
		$this->check('native upsert without the identity column has no setval', 1, count($t('INSERT INTO "pages" ("parent_id", "name") VALUES (:p, :name) ON CONFLICT ("id") DO UPDATE SET "name"=excluded."name"')));
		$this->check('INSERT SELECT ON DUPLICATE', 'INSERT INTO caches (name, data) SELECT name, data FROM other ON CONFLICT ("name") DO UPDATE SET data=excluded.data', $t('INSERT INTO caches (name, data) SELECT name, data FROM other ON DUPLICATE KEY UPDATE data=VALUES(data)')[0]);

		$this->check('DELETE LIMIT 1 becomes ctid subquery', 'DELETE FROM modules WHERE ctid IN (SELECT ctid FROM modules WHERE id=:id LIMIT 1)', $t('DELETE FROM modules WHERE id=:id LIMIT 1')[0]);
		$this->check('DELETE ORDER BY LIMIT', 'DELETE FROM t WHERE ctid IN (SELECT ctid FROM t WHERE a=1 ORDER BY b LIMIT 2)', $t('DELETE FROM t WHERE a=1 ORDER BY b LIMIT 2')[0]);
		$this->check('multi-table DELETE becomes USING', 'DELETE FROM "field_title" USING pages WHERE pages.id=field_title.pages_id AND pages.status=1', $t('DELETE field_title FROM field_title JOIN pages ON pages.id=field_title.pages_id WHERE pages.status=1')[0]);
		$this->check('UPDATE ORDER BY LIMIT becomes ctid subquery', 'UPDATE t SET a=1 WHERE ctid IN (SELECT ctid FROM t WHERE b=2 ORDER BY c DESC LIMIT 1)', $t('UPDATE t SET a=1 WHERE b=2 ORDER BY c DESC LIMIT 1')[0]);
		$this->check('UPDATE ORDER BY without LIMIT drops ORDER BY', 'UPDATE t SET a=a+1 WHERE b=2', $t('UPDATE t SET a=a+1 WHERE b=2 ORDER BY a DESC')[0]);
		$this->check('UPDATE LIMIT without ORDER BY', 'UPDATE t SET a=1 WHERE ctid IN (SELECT ctid FROM t WHERE b=2 LIMIT 1)', $t('UPDATE t SET a=1 WHERE b=2 LIMIT 1')[0]);

		$this->check('SET NAMES is a no-op', 'SELECT 1', $t("SET NAMES 'utf8'")[0]);
		$this->check('SET FOREIGN_KEY_CHECKS is a no-op', 'SELECT 1', $t('SET FOREIGN_KEY_CHECKS=0')[0]);
		$this->check('LOCK TABLES is a no-op', 'SELECT 1', $t('LOCK TABLES t WRITE')[0]);
		$this->check('UNLOCK TABLES is a no-op', 'SELECT 1', $t('UNLOCK TABLES')[0]);
		$this->check('OPTIMIZE is a no-op', 'SELECT 1', $t('OPTIMIZE TABLE t')[0]);
		$this->check('DO expr becomes SELECT', 'SELECT 1', $t('DO 1')[0]);
	}

	protected function testShow() {
		$t = function($sql) { return $this->translator->translateStatements($sql)[0]; };
		$this->check('SHOW TABLES', "SELECT table_name AS \"Tables_in_db\" FROM information_schema.tables WHERE table_schema=current_schema() AND table_type='BASE TABLE' ORDER BY table_name", $t('SHOW TABLES'));
		$this->check('SHOW TABLES LIKE', "SELECT table_name AS \"Tables_in_db\" FROM information_schema.tables WHERE table_schema=current_schema() AND table_type='BASE TABLE' AND table_name ILIKE 'field\\_%' ORDER BY table_name", $t("SHOW TABLES LIKE 'field\\_%'"));
		$columns = $t('SHOW COLUMNS FROM pages');
		$this->check('SHOW COLUMNS has MySQL column names', true, strpos($columns, 'AS "Field"') !== false && strpos($columns, 'AS "Type"') !== false && strpos($columns, 'AS "Extra"') !== false);
		$this->check('SHOW COLUMNS WHERE Field', true, strpos($t("SHOW COLUMNS FROM pages WHERE Field='id'"), "WHERE \"Field\"='id'") !== false);
		$this->check('SHOW COLUMNS LIKE', true, strpos($t("SHOW COLUMNS FROM `pages` LIKE 'published'"), "\"Field\" ILIKE 'published'") !== false);
		$this->check('DESCRIBE becomes SHOW COLUMNS', $columns, $t('DESCRIBE pages'));
		$index = $t('SHOW INDEX FROM pages');
		$this->check('SHOW INDEX has MySQL column names', true, strpos($index, 'AS "Key_name"') !== false && strpos($index, 'AS "Seq_in_index"') !== false);
		$this->check('SHOW INDEX WHERE Key_name', true, strpos($t("SHOW INDEX FROM pages WHERE Key_name=:name"), 'WHERE "Key_name"=:name') !== false);
		$this->check('SHOW TABLE STATUS', true, strpos($t("SHOW TABLE STATUS WHERE name='pages'"), "'PostgreSQL' AS \"Engine\"") !== false);
		$this->check('SHOW VARIABLES returns empty result shape', "SELECT NULL AS \"Variable_name\", NULL AS \"Value\" WHERE false", $t("SHOW VARIABLES WHERE Variable_name='version'"));
		$this->check('SHOW CREATE TABLE throws until supported', true, $this->throwsMatching(function() use($t) { $t('SHOW CREATE TABLE pages'); }, '/SHOW CREATE TABLE/'));
	}

	protected function testDdl() {
		$t = function($sql) { return $this->translator->translateStatements($sql); };

		$statements = $t("CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0',
  `name` varchar(128) CHARACTER SET ascii NOT NULL,
  `status` int(10) unsigned NOT NULL DEFAULT '1',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created` timestamp NOT NULL DEFAULT '2015-12-18 06:09:00',
  `published` datetime DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_parent_id` (`name`,`parent_id`),
  KEY `parent_id` (`parent_id`,`sort`),
  KEY `status` (`status`)
) ENGINE=MyISAM AUTO_INCREMENT=1010 DEFAULT CHARSET=utf8;");
		$this->check('CREATE TABLE splits into table plus one statement per index', 4, count($statements));
		$this->check('CREATE TABLE pages',
			"CREATE TABLE \"pages\" (\n" .
			"  \"id\" integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,\n" .
			"  \"parent_id\" integer NOT NULL DEFAULT '0',\n" .
			"  \"name\" varchar(128) NOT NULL,\n" .
			"  \"status\" integer NOT NULL DEFAULT '1',\n" .
			"  \"modified\" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n" .
			"  \"created\" timestamp NOT NULL DEFAULT '2015-12-18 06:09:00',\n" .
			"  \"published\" timestamp DEFAULT NULL,\n" .
			"  \"sort\" integer NOT NULL DEFAULT '0',\n" .
			"  PRIMARY KEY (\"id\")\n" .
			")",
			$statements[0]);
		$this->check('unique key becomes CREATE UNIQUE INDEX with table-prefixed name',
			'CREATE UNIQUE INDEX "pages__name_parent_id" ON "pages" ("name", "parent_id")', $statements[1]);
		$this->check('multi-column key', 'CREATE INDEX "pages__parent_id" ON "pages" ("parent_id", "sort")', $statements[2]);
		$this->check('single-column key', 'CREATE INDEX "pages__status" ON "pages" ("status")', $statements[3]);

		$statements = $t("CREATE TABLE `field_title` (`pages_id` int(10) unsigned NOT NULL, `data` text NOT NULL, PRIMARY KEY (`pages_id`), KEY `data_exact` (`data`(255)), FULLTEXT KEY `data` (`data`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$this->check('prefix index on text column becomes left() expression index',
			'CREATE INDEX "field_title__data_exact" ON "field_title" (left("data", 255))', $statements[1]);
		$this->check('FULLTEXT KEY becomes trigram GIN index',
			'CREATE INDEX "field_title__data" ON "field_title" USING gin ("data" gin_trgm_ops)', $statements[2]);
		$this->translator->setTrigramAvailable(false);
		$this->check('FULLTEXT KEY is skipped when trigram unavailable (table only, no index statement)', 1, count($t("CREATE TABLE `f` (`pages_id` int NOT NULL, `data` text NOT NULL, PRIMARY KEY (`pages_id`), FULLTEXT KEY `data` (`data`))")));
		$this->translator->setTrigramAvailable(true);
		$this->check('prefix index on varchar column is a plain column index',
			'CREATE INDEX "f__name" ON "f" ("name")', $t('CREATE TABLE `f` (`name` varchar(250) NOT NULL, KEY `name` (`name`(191)))')[1]);

		$statements = $t("CREATE TABLE `t` (`a` tinyint(1) NOT NULL DEFAULT 0, `b` mediumint(9), `c` bigint(20) unsigned, `d` float, `e` double, `f` decimal(10,2), `g` mediumtext, `h` longtext, `i` char(32), `j` date, `k` time, `l` blob, `m` enum('x','y') DEFAULT 'x', `n` json, `o` bool, `p` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT '', `q` int(11) NOT NULL DEFAULT -1, `r` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp())");
		$this->check('type map',
			"CREATE TABLE \"t\" (\n" .
			"  \"a\" smallint NOT NULL DEFAULT 0,\n  \"b\" integer,\n  \"c\" bigint,\n  \"d\" real,\n  \"e\" double precision,\n  \"f\" numeric(10,2),\n" .
			"  \"g\" text,\n  \"h\" text,\n  \"i\" varchar(32),\n  \"j\" date,\n  \"k\" time,\n  \"l\" bytea,\n  \"m\" text DEFAULT 'x',\n  \"n\" jsonb,\n  \"o\" smallint,\n" .
			"  \"p\" varchar(250) DEFAULT '',\n  \"q\" integer NOT NULL DEFAULT -1,\n  \"r\" timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP\n)",
			$statements[0]);
		$this->check('CREATE TABLE IF NOT EXISTS preserved', 'CREATE TABLE IF NOT EXISTS "t" (', $t('CREATE TABLE IF NOT EXISTS `t` (`a` int)')[0], '^=');
		$this->check('unnamed KEY uses first column as name', 'CREATE INDEX "t__a" ON "t" ("a")', $t('CREATE TABLE t (a int, KEY (a))')[1]);
		$this->check('inline PRIMARY KEY on column', "CREATE TABLE \"t\" (\n  \"id\" integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,\n  PRIMARY KEY (\"id\")\n)", $t('CREATE TABLE t (id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY)')[0]);

		// PostgreSQL truncates names over 63 bytes, so long ones get a hashed tail and keep their MySQL name in a comment
		$long = 'field_' . str_repeat('x', 52);
		$statements = $t("CREATE TABLE `$long` (`pages_id` int NOT NULL, `data` text NOT NULL, PRIMARY KEY (`pages_id`), KEY `data_exact` (`data`(250)), KEY `data` (`data`(100)))");
		$hashed = $this->translator->indexName($long, 'data_exact');
		$this->check('a long index name fits in 63 bytes', true, strlen($hashed) === 63);
		$this->check('long index names stay distinct', true, $hashed !== $this->translator->indexName($long, 'data'));
		$this->check('a short index name is unchanged', 't__a', $this->translator->indexName('t', 'a'));
		$this->check('a long index is created under its hashed name', 'CREATE INDEX "' . $hashed . '" ON "' . $long . '" (left("data", 250))', $statements[1]);
		$this->check('a long index records its MySQL name', 'COMMENT ON INDEX "' . $hashed . '" IS \'pw_index:data_exact\'', $statements[2]);
		$this->check('DROP INDEX finds the hashed name', 'DROP INDEX IF EXISTS "' . $hashed . '"', $t("DROP INDEX data_exact ON `$long`")[0]);
		$this->check('CREATE INDEX', 'CREATE INDEX "t__a_idx" ON "t" ("a")', $t('CREATE INDEX a_idx ON t (a)')[0]);
		$this->check('CREATE UNIQUE INDEX IF NOT EXISTS', 'CREATE UNIQUE INDEX IF NOT EXISTS "t__u" ON "t" ("a", "b")', $t('CREATE UNIQUE INDEX IF NOT EXISTS u ON t (a, b)')[0]);
		$this->check('DROP INDEX ON', 'DROP INDEX IF EXISTS "t__a_idx"', $t('DROP INDEX a_idx ON t')[0]);
		$this->check('DROP TABLE IF EXISTS quotes identifier', 'DROP TABLE IF EXISTS "t"', $t('DROP TABLE IF EXISTS `t`')[0]);
		$this->check('TRUNCATE restarts identity', 'TRUNCATE TABLE "t" RESTART IDENTITY', $t('TRUNCATE TABLE `t`')[0]);
		$this->check('TRUNCATE without TABLE keyword', 'TRUNCATE TABLE "t" RESTART IDENTITY', $t('TRUNCATE t')[0]);

		$this->check('ALTER ADD column', 'ALTER TABLE "t" ADD COLUMN "x" text NOT NULL DEFAULT \'\'', $t("ALTER TABLE `t` ADD `x` MEDIUMTEXT NOT NULL DEFAULT ''")[0]);
		$this->check('ALTER ADD column AFTER ignored', 'ALTER TABLE "t" ADD COLUMN "x" integer NOT NULL DEFAULT 0', $t('ALTER TABLE t ADD x INT UNSIGNED NOT NULL DEFAULT 0 AFTER y')[0]);
		$this->check('ALTER ADD NOT NULL without default supplies one for text', 'ALTER TABLE "t" ADD COLUMN "x" text NOT NULL DEFAULT \'\'', $t('ALTER TABLE t ADD x TEXT NOT NULL')[0]);
		$this->check('ALTER ADD NOT NULL without default supplies one for numbers', 'ALTER TABLE "t" ADD COLUMN "x" integer NOT NULL DEFAULT 0', $t('ALTER TABLE t ADD x INT NOT NULL')[0]);
		$this->check('ALTER ADD INDEX', 'CREATE INDEX "t__x" ON "t" ("x")', $t('ALTER TABLE t ADD INDEX x (x)')[0]);
		$this->check('ALTER ADD UNIQUE', 'CREATE UNIQUE INDEX "t__u" ON "t" ("x")', $t('ALTER TABLE t ADD UNIQUE `u` (`x`)')[0]);
		$this->check('ALTER DROP INDEX', 'DROP INDEX IF EXISTS "t__x"', $t('ALTER TABLE t DROP INDEX x')[0]);
		$this->check('ALTER DROP COLUMN', 'ALTER TABLE "t" DROP COLUMN "x"', $t('ALTER TABLE t DROP x')[0]);
		$this->check('ALTER RENAME COLUMN', 'ALTER TABLE "t" RENAME COLUMN "a" TO "b"', $t('ALTER TABLE t RENAME COLUMN a TO b')[0]);
		$this->check('ALTER MODIFY type and null (no DEFAULT given: none, as MySQL MODIFY redefines the column)', 'ALTER TABLE "t" ALTER COLUMN "data" DROP DEFAULT, ALTER COLUMN "data" TYPE text USING "data"::text, ALTER COLUMN "data" SET NOT NULL', $t('ALTER TABLE t MODIFY `data` MEDIUMTEXT NOT NULL')[0]);
		$this->check('ALTER MODIFY with default', 'ALTER TABLE "t" ALTER COLUMN "ip" DROP DEFAULT, ALTER COLUMN "ip" TYPE varchar(45) USING "ip"::varchar(45), ALTER COLUMN "ip" SET NOT NULL, ALTER COLUMN "ip" SET DEFAULT \'\'', $t("ALTER TABLE t MODIFY ip VARCHAR(45) NOT NULL DEFAULT ''")[0]);
		$this->check('ALTER MODIFY nullable drops NOT NULL', 'ALTER TABLE "t" ALTER COLUMN "x" DROP DEFAULT, ALTER COLUMN "x" TYPE integer USING "x"::integer, ALTER COLUMN "x" DROP NOT NULL', $t('ALTER TABLE t MODIFY x INT NULL')[0]);
		$this->check('ALTER CHANGE renames then modifies', ['ALTER TABLE "t" RENAME COLUMN "a" TO "b"', 'ALTER TABLE "t" ALTER COLUMN "b" DROP DEFAULT, ALTER COLUMN "b" TYPE text USING "b"::text, ALTER COLUMN "b" SET NOT NULL'], $t('ALTER TABLE t CHANGE a b TEXT NOT NULL'));
		$this->check('ALTER ADD PRIMARY KEY', 'ALTER TABLE "t" ADD PRIMARY KEY ("a", "b")', $t('ALTER TABLE t ADD PRIMARY KEY (a, b)')[0]);
		$this->check('ALTER DROP PRIMARY KEY', 'ALTER TABLE "t" DROP CONSTRAINT IF EXISTS "t_pkey"', $t('ALTER TABLE t DROP PRIMARY KEY')[0]);
		$this->check('ALTER DROP PRIMARY KEY, ADD PRIMARY KEY in one statement', ['ALTER TABLE "t" DROP CONSTRAINT IF EXISTS "t_pkey"', 'ALTER TABLE "t" ADD PRIMARY KEY ("a", "b")'], $t('ALTER TABLE t DROP PRIMARY KEY, ADD PRIMARY KEY(a, b)'));
		$this->check('ALTER table options are no-ops', 'SELECT 1', $t('ALTER TABLE t ENGINE=InnoDB')[0]);
		$this->check('ALTER RENAME TO (no PDO: table only)', 'ALTER TABLE "a" RENAME TO "b"', $t('ALTER TABLE a RENAME TO b')[0]);
		$this->check('RENAME TABLE (no PDO: table only)', 'ALTER TABLE "a" RENAME TO "b"', $t('RENAME TABLE a TO b')[0]);
		$this->check('ALTER MODIFY without null spec makes the column nullable with no default, as MySQL does', 'ALTER TABLE "t" ALTER COLUMN "data" DROP DEFAULT, ALTER COLUMN "data" TYPE varchar(191) USING "data"::varchar(191), ALTER COLUMN "data" DROP NOT NULL', $t('ALTER TABLE t MODIFY data VARCHAR(191)')[0]);
		// with known column types: text to number converts as MySQL does ('' and 'abc' become 0); keys and identity columns keep NOT NULL and their sequence
		$this->translator->setSchemaCache(['m' => ['primary' => ['id'], 'identity' => 'id', 'columns' => ['id' => 'integer', 'data' => 'text', 'n' => 'integer']]]);
		$this->check('ALTER MODIFY text to integer converts values as MySQL does',
			'ALTER TABLE "m" ALTER COLUMN "data" DROP DEFAULT, ALTER COLUMN "data" TYPE integer USING (CASE WHEN "data"::text ~ \'^\s*-?[0-9]\' THEN substring("data"::text from \'-?[0-9]+\')::numeric ELSE 0 END)::integer, ALTER COLUMN "data" SET NOT NULL',
			$t('ALTER TABLE m MODIFY data INT NOT NULL')[0]);
		$this->translator->setSchemaCache(['m' => ['primary' => ['id'], 'identity' => 'id', 'columns' => ['id' => 'integer', 'data' => 'integer', 'n' => 'integer']]]);
		$this->check('ALTER MODIFY of the identity primary key keeps NOT NULL and its sequence',
			'ALTER TABLE "m" ALTER COLUMN "id" TYPE bigint USING "id"::bigint',
			$t('ALTER TABLE m MODIFY id BIGINT UNSIGNED')[0]);
	}

	protected function testExpressions() {
		$t = function($sql) { return $this->translate($sql); };

		// comparison operators and patterns
		$this->check('LIKE becomes ILIKE (MySQL collations are case-insensitive)', "SELECT 1 FROM t WHERE data ILIKE '%foo%'", $t("SELECT 1 FROM t WHERE data LIKE '%foo%'"));
		$this->check('NOT LIKE becomes NOT ILIKE', "SELECT 1 FROM t WHERE data NOT ILIKE :v", $t("SELECT 1 FROM t WHERE data NOT LIKE :v"));
		$this->check('RLIKE becomes ~*', 'SELECT 1 FROM t WHERE data ~* :v', $t('SELECT 1 FROM t WHERE data RLIKE :v'));
		$this->check('REGEXP becomes ~*', 'SELECT 1 FROM t WHERE data ~* :v', $t('SELECT 1 FROM t WHERE data REGEXP :v'));
		$this->check('NOT RLIKE becomes !~*', 'SELECT 1 FROM t WHERE data !~* :v', $t('SELECT 1 FROM t WHERE data NOT RLIKE :v'));
		$this->check('BINARY x becomes plain x (comparison is already case-sensitive)', 'SELECT 1 FROM t WHERE data=:v', $t('SELECT 1 FROM t WHERE BINARY data=:v'));

		// select modifiers and LIMIT
		$this->check('SQL_CALC_FOUND_ROWS removed', 'SELECT pages.id FROM pages', $t('SELECT SQL_CALC_FOUND_ROWS pages.id FROM pages'));
		$this->check('LIMIT offset,count becomes LIMIT count OFFSET offset', 'SELECT id FROM pages LIMIT 10 OFFSET 5', $t('SELECT id FROM pages LIMIT 5,10'));
		$this->check('LIMIT with spaces around comma', 'SELECT id FROM pages LIMIT 10 OFFSET 5 ', $t('SELECT id FROM pages LIMIT 5 , 10 '));
		$this->check('plain LIMIT unchanged', 'SELECT id FROM pages LIMIT 10', $t('SELECT id FROM pages LIMIT 10'));
		$this->check('FOUND_ROWS() throws a clear error', true, $this->throwsMatching(function() use($t) { $t('SELECT FOUND_ROWS()'); }, '/supportsFoundRows/'));
		$this->check('MATCH AGAINST throws a clear error', true, $this->throwsMatching(function() use($t) { $t('SELECT 1 FROM t WHERE MATCH(data) AGAINST(:v IN BOOLEAN MODE)'); }, '/supportsFulltext/'));

		// boolean context
		$this->check('bitwise test in WHERE gets <> 0', 'SELECT id FROM pages WHERE (parent_id=1 OR ((status & 1024) <> 0))', $t('SELECT id FROM pages WHERE (parent_id=1 OR (status & 1024))'));
		$this->check('bitwise test already compared is left alone', 'SELECT id FROM pages WHERE (status & 1024)=0', $t('SELECT id FROM pages WHERE (status & 1024)=0'));
		$this->check('WHERE 0 becomes WHERE false', 'SELECT 1 WHERE false', $t('SELECT 1 WHERE 0'));
		$this->check('WHERE 1 becomes WHERE true', 'SELECT 1 WHERE true', $t('SELECT 1 WHERE 1'));

		// functions
		$this->check('UNIX_TIMESTAMP(col) becomes extract epoch', 'SELECT (extract(epoch from pages.created))::bigint AS created FROM pages', $t('SELECT UNIX_TIMESTAMP(pages.created) AS created FROM pages'));
		$this->check('UNIX_TIMESTAMP() becomes extract epoch from now()', 'SELECT (extract(epoch from now()))::bigint', $t('SELECT UNIX_TIMESTAMP()'));
		$this->check('FROM_UNIXTIME(x) becomes to_timestamp', 'SELECT to_timestamp(:ts)::timestamp', $t('SELECT FROM_UNIXTIME(:ts)'));
		$this->check('IF(a,b,c) becomes CASE', 'SELECT (CASE WHEN a>1 THEN 1 ELSE 2 END)', $t('SELECT IF(a>1, 1, 2)'));
		$this->check('BETWEEN 0 AND 1 keeps its numbers', 'SELECT id FROM t WHERE sort BETWEEN 0 AND 1 AND x=1', $t('SELECT id FROM t WHERE sort BETWEEN 0 AND 1 AND x=1'));
		$this->check('simple CASE WHEN values stay values', "SELECT CASE status WHEN 1 THEN 'on' WHEN 0 THEN 'off' END FROM t WHERE true", $t("SELECT CASE status WHEN 1 THEN 'on' WHEN 0 THEN 'off' END FROM t WHERE 1"));
		$this->check('searched CASE WHEN conditions are still made boolean', "SELECT CASE WHEN (status & 1) <> 0 THEN 'on' ELSE 'off' END FROM t", $t("SELECT CASE WHEN status & 1 THEN 'on' ELSE 'off' END FROM t"));
		$this->check('conditions inside IN (SELECT ...) are made boolean', 'SELECT id FROM t WHERE id IN (SELECT pages_id FROM u WHERE ((data & 1) <> 0))', $t('SELECT id FROM t WHERE id IN (SELECT pages_id FROM u WHERE (data & 1))'));
		$this->check('conditions inside EXISTS (SELECT ...) are made boolean', 'SELECT id FROM t WHERE EXISTS (SELECT 1 FROM u WHERE (u.status & 2) <> 0)', $t('SELECT id FROM t WHERE EXISTS (SELECT 1 FROM u WHERE u.status & 2)'));
		$this->check('NOT on a bit test', 'SELECT id FROM t WHERE NOT ((status & 1024) <> 0)', $t('SELECT id FROM t WHERE NOT (status & 1024)'));
		$this->check('IF() with an integer condition gets a boolean test (bit test, constant)', 'SELECT (CASE WHEN (status & 1) <> 0 THEN 1 ELSE 2 END), (CASE WHEN true THEN 1 ELSE 2 END) FROM pages', $t('SELECT IF(status & 1, 1, 2), IF(1, 1, 2) FROM pages'));
		$this->check('LOCATE(sub, str[, pos]) becomes position()/strpos()', "SELECT position('b' in 'abc'), (CASE WHEN strpos(substring('abcb' from 3), 'b') = 0 THEN 0 ELSE strpos(substring('abcb' from 3), 'b') + 3 - 1 END)", $t("SELECT LOCATE('b', 'abc'), LOCATE('b', 'abcb', 3)"));
		$this->check('SUBSTRING_INDEX(str, delim, count) becomes an array slice', "SELECT array_to_string((string_to_array('a.b.c', '.'))[1:2], '.'), array_to_string((string_to_array('a.b.c', '.'))[cardinality(string_to_array('a.b.c', '.')) - 1 + 1:], '.')", $t("SELECT SUBSTRING_INDEX('a.b.c', '.', 2), SUBSTRING_INDEX('a.b.c', '.', -1)"));
		$this->check('IFNULL becomes COALESCE', 'SELECT COALESCE(a, 0)', $t('SELECT IFNULL(a, 0)'));
		$this->check('FIELD() becomes array_position with 0 for absent', 'SELECT id FROM pages ORDER BY COALESCE(array_position(ARRAY[3,1,2], pages.id), 0)', $t('SELECT id FROM pages ORDER BY FIELD(pages.id, 3,1,2)'));
		$this->check('RAND() becomes random()', 'SELECT id FROM pages ORDER BY random()', $t('SELECT id FROM pages ORDER BY RAND()'));
		$this->check('GROUP_CONCAT with ORDER BY and SEPARATOR becomes string_agg', 'SELECT string_agg(t.data::text, \'|\' ORDER BY t.sort) AS "x" FROM t', $t("SELECT GROUP_CONCAT(t.data ORDER BY t.sort SEPARATOR '|') AS `x` FROM t"));
		$this->check('GROUP_CONCAT default separator is comma', "SELECT string_agg(t.data::text, ',') FROM t", $t('SELECT GROUP_CONCAT(t.data) FROM t'));
		$this->check('GROUP_CONCAT DISTINCT', "SELECT string_agg(DISTINCT t.data::text, ',') FROM t", $t('SELECT GROUP_CONCAT(DISTINCT t.data) FROM t'));
		$this->check('GROUP_CONCAT DISTINCT with ORDER BY on the same expression orders by the cast expression (PostgreSQL requires it in the argument list)', "SELECT string_agg(DISTINCT t.data::text, '|' ORDER BY t.data::text DESC) FROM t", $t("SELECT GROUP_CONCAT(DISTINCT t.data ORDER BY t.data DESC SEPARATOR '|') FROM t"));
		$this->check('CAST AS SIGNED becomes bigint', 'SELECT CAST(x AS bigint)', $t('SELECT CAST(x AS SIGNED)'));
		$this->check('CAST AS UNSIGNED becomes bigint', 'SELECT CAST(x AS bigint)', $t('SELECT CAST(x AS UNSIGNED INTEGER)'));
		$this->check('CAST AS CHAR becomes text', 'SELECT CAST(x AS text)', $t('SELECT CAST(x AS CHAR)'));
		$this->check('INTERVAL n UNIT becomes quoted interval', "SELECT now() - INTERVAL '5 day'", $t('SELECT NOW() - INTERVAL 5 DAY'));
		$this->check('INTERVAL with bound amount uses multiplication', "SELECT now() - (INTERVAL '1 day' * :n)", $t('SELECT NOW() - INTERVAL :n DAY'));
		$this->check('DATE_SUB becomes subtraction', "SELECT (ts - INTERVAL '1 hour') FROM t", $t('SELECT DATE_SUB(ts, INTERVAL 1 HOUR) FROM t'));
		$this->check('DATE_ADD becomes addition', "SELECT (ts + INTERVAL '2 month') FROM t", $t('SELECT DATE_ADD(ts, INTERVAL 2 MONTH) FROM t'));
		$this->check('GET_LOCK becomes advisory lock', 'SELECT (CASE WHEN pg_try_advisory_lock(hashtext(:id)) THEN 1 ELSE 0 END)', $t('SELECT GET_LOCK(:id, :seconds)'));
		$this->check('RELEASE_LOCK becomes advisory unlock', 'SELECT (CASE WHEN pg_advisory_unlock(hashtext(:id)) THEN 1 ELSE 0 END)', $t('SELECT RELEASE_LOCK(:id)'));
		$this->check('IS_FREE_LOCK checks pg_locks', "SELECT (CASE WHEN EXISTS (SELECT 1 FROM pg_locks WHERE locktype='advisory' AND objid=hashtext(:id)::oid) THEN 0 ELSE 1 END)", $t('SELECT IS_FREE_LOCK(:id)'));
		$this->check('DO expr becomes SELECT expr', 'SELECT (CASE WHEN pg_advisory_unlock(hashtext(:id)) THEN 1 ELSE 0 END)', $t('DO RELEASE_LOCK(:id)'));
		$this->check('DATABASE() becomes current_database()', 'SELECT current_database()', $t('SELECT DATABASE()'));
		$this->check('LAST_INSERT_ID() becomes lastval()', 'SELECT lastval()', $t('SELECT LAST_INSERT_ID()'));
		$this->check('CONCAT passes through', "SELECT CONCAT(a, 'x')", $t("SELECT CONCAT(a, 'x')"));
		$this->check('NOW() lowercased', 'SELECT now()', $t('SELECT NOW()'));
		$this->check('DATE_FORMAT %Y-%m-%d becomes to_char', "SELECT to_char(ts, 'YYYY-MM-DD') FROM t", $t("SELECT DATE_FORMAT(ts, '%Y-%m-%d') FROM t"));
		$this->check('DATE_FORMAT time parts', "SELECT to_char(ts, 'HH24:MI:SS') FROM t", $t("SELECT DATE_FORMAT(ts, '%H:%i:%s') FROM t"));

		// GROUP BY with ORDER BY on a joined column (ONLY_FULL_GROUP_BY equivalent)
		$this->check('ORDER BY joined column under GROUP BY is wrapped in any_value()',
			'SELECT pages.id FROM pages LEFT JOIN field_title AS _sort_title ON _sort_title.pages_id=pages.id GROUP BY pages.id ORDER BY any_value(_sort_title.data) DESC',
			$t('SELECT pages.id FROM pages LEFT JOIN field_title AS _sort_title ON _sort_title.pages_id=pages.id GROUP BY pages.id ORDER BY _sort_title.data DESC'));
		$this->check('ORDER BY grouped-table column is left alone',
			'SELECT pages.id FROM pages GROUP BY pages.id ORDER BY pages.name',
			$t('SELECT pages.id FROM pages GROUP BY pages.id ORDER BY pages.name'));
		$this->check('ORDER BY aggregate is left alone',
			'SELECT pages.id FROM pages LEFT JOIN field_images AS i ON i.pages_id=pages.id GROUP BY pages.id ORDER BY COUNT(i.data)',
			$t('SELECT pages.id FROM pages LEFT JOIN field_images AS i ON i.pages_id=pages.id GROUP BY pages.id ORDER BY COUNT(i.data)'));
		$this->check('ORDER BY without GROUP BY is left alone',
			'SELECT pages.id FROM pages LEFT JOIN field_title AS t ON t.pages_id=pages.id ORDER BY t.data',
			$t('SELECT pages.id FROM pages LEFT JOIN field_title AS t ON t.pages_id=pages.id ORDER BY t.data'));
		$this->check('SELECT joined columns under GROUP BY are wrapped in any_value() and keep their result names',
			'SELECT false AS "isLoaded", pages.templates_id AS templates_id, pages.*, any_value(pages_sortfields.sortfield) AS "sortfield", (SELECT COUNT(*) FROM pages AS children WHERE children.parent_id=pages.id) AS "numChildren", any_value(field_title.data) AS "title__data" FROM pages LEFT JOIN pages_sortfields ON pages_sortfields.pages_id=pages.id LEFT JOIN field_title ON field_title.pages_id=pages.id WHERE pages.id=:id GROUP BY pages.id',
			$t('SELECT false AS isLoaded, pages.templates_id AS templates_id, pages.*, pages_sortfields.sortfield, (SELECT COUNT(*) FROM pages AS children WHERE children.parent_id=pages.id) AS numChildren, field_title.data AS "title__data" FROM pages LEFT JOIN pages_sortfields ON pages_sortfields.pages_id=pages.id LEFT JOIN field_title ON field_title.pages_id=pages.id WHERE pages.id=:id GROUP BY pages.id'));
		$this->check('SELECT joined column without GROUP BY is left alone',
			'SELECT pages.id, t.data FROM pages LEFT JOIN field_title AS t ON t.pages_id=pages.id',
			$t('SELECT pages.id, t.data FROM pages LEFT JOIN field_title AS t ON t.pages_id=pages.id'));
		$this->check('HAVING on a select alias uses the aliased expression',
			'SELECT p1.parent_id, COUNT(p1.id) AS num_children1 FROM pages AS p1 GROUP BY p1.parent_id HAVING (COUNT(p1.id))>0',
			$t('SELECT p1.parent_id, COUNT(p1.id) AS num_children1 FROM pages AS p1 GROUP BY p1.parent_id HAVING num_children1>0'));
		$this->check('HAVING alias inside a derived table subquery is resolved too',
			'SELECT pages.id FROM pages LEFT JOIN (SELECT p1.parent_id, COUNT(p1.id) AS num_children1 FROM pages AS p1 GROUP BY p1.parent_id HAVING (COUNT(p1.id))>0 ) pages_num_children1 ON pages_num_children1.parent_id=pages.id WHERE pages_num_children1.num_children1>0 GROUP BY pages.id',
			$t('SELECT pages.id FROM pages LEFT JOIN (SELECT p1.parent_id, COUNT(p1.id) AS num_children1 FROM pages AS p1 GROUP BY p1.parent_id HAVING num_children1>0 ) pages_num_children1 ON pages_num_children1.parent_id=pages.id WHERE pages_num_children1.num_children1>0 GROUP BY pages.id'));
		$this->check('ORDER BY joined column inside a subquery under GROUP BY is wrapped too',
			'SELECT id FROM (SELECT pages.id FROM pages LEFT JOIN field_title AS t ON t.pages_id=pages.id GROUP BY pages.id ORDER BY any_value(t.data)) sub',
			$t('SELECT id FROM (SELECT pages.id FROM pages LEFT JOIN field_title AS t ON t.pages_id=pages.id GROUP BY pages.id ORDER BY t.data) sub'));
		$this->check('HAVING on a non-alias is left alone',
			'SELECT p.parent_id, COUNT(p.id) AS n FROM pages AS p GROUP BY p.parent_id HAVING COUNT(p.id)>0 AND p.parent_id>1',
			$t('SELECT p.parent_id, COUNT(p.id) AS n FROM pages AS p GROUP BY p.parent_id HAVING COUNT(p.id)>0 AND p.parent_id>1'));
		// MySQL coerces strings compared to numeric columns; PostgreSQL rejects them
		$this->translator->setSchemaCache([
			'field_int' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'integer']],
			'field_dec' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'numeric']],
			'field_date' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'timestamp without time zone']],
			'field_text' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'text']],
			'unique_num' => ['primary' => ['id'], 'identity' => 'id', 'columns' => ['id' => 'bigint']],
		]);
		// bound values are coerced to the column's own type family, so that an index on the column can be used, and NULL stays NULL
		$num = function($p) { return "(CASE WHEN ($p)::text IS NULL THEN NULL WHEN ($p)::text ~ '^\\s*-?[0-9]' THEN substring(($p)::text from '-?[0-9]+')::bigint ELSE 0 END)"; };
		$setval = function($table, $col) { return "SELECT setval(s::regclass, GREATEST((SELECT MAX(\"$col\") FROM \"$table\"), pg_sequence_last_value(s::regclass), 1)) FROM pg_get_serial_sequence('\"$table\"', '$col') AS s"; };
		$numDec = function($p, $cast = 'numeric') { return "(CASE WHEN ($p)::text IS NULL THEN NULL WHEN ($p)::text ~ '^\\s*-?[0-9]+(\\.[0-9]+)?' THEN substring(($p)::text from '-?[0-9]+(?:\\.[0-9]+)?')::$cast ELSE 0 END)"; };
		$this->check('numeric column compared to empty string compares to 0',
			"SELECT pages.id FROM pages LEFT JOIN field_dec AS f ON f.pages_id=pages.id WHERE (f.data IS NOT NULL AND (f.data!=0 AND f.data!='0')) GROUP BY pages.id",
			$t("SELECT pages.id FROM pages LEFT JOIN field_dec AS f ON f.pages_id=pages.id WHERE (f.data IS NOT NULL AND (f.data!='' AND f.data!='0')) GROUP BY pages.id"));
		$this->check('numeric column compared to a non-numeric string compares to 0',
			"SELECT pages.id FROM pages LEFT JOIN field_int AS o ON o.pages_id=pages.id AND o.data=0 WHERE o.data IS NULL",
			$t("SELECT pages.id FROM pages LEFT JOIN field_int AS o ON o.pages_id=pages.id AND o.data='Red' WHERE o.data IS NULL"));
		$this->check('numeric column compared to a bound value coerces it MySQL-style',
			'SELECT pages.id FROM pages LEFT JOIN field_int AS f ON f.pages_id=pages.id WHERE (f.data IS NULL OR (f.data=' . $num(':s0X') . '))',
			$t('SELECT pages.id FROM pages LEFT JOIN field_int AS f ON f.pages_id=pages.id WHERE (f.data IS NULL OR (f.data=:s0X))'));
		$this->check('numeric column compared to a number is left alone',
			'SELECT pages.id FROM pages JOIN field_int AS f ON f.pages_id=pages.id AND f.data>42',
			$t('SELECT pages.id FROM pages JOIN field_int AS f ON f.pages_id=pages.id AND f.data>42'));
		$this->check('text column comparisons are left alone',
			"SELECT pages.id FROM pages JOIN field_text AS t ON t.pages_id=pages.id AND t.data!='' AND t.data=:v",
			$t("SELECT pages.id FROM pages JOIN field_text AS t ON t.pages_id=pages.id AND t.data!='' AND t.data=:v"));
		$this->check('timestamp column with LIKE is cast to text',
			'SELECT pages.id FROM pages JOIN field_date AS d ON d.pages_id=pages.id AND (((d.data)::text ILIKE :p ))',
			$t('SELECT pages.id FROM pages JOIN field_date AS d ON d.pages_id=pages.id AND ((d.data LIKE :p ))'));
		$this->check('numeric column with RLIKE is cast to text',
			'SELECT pages.id FROM pages JOIN field_int AS f ON f.pages_id=pages.id WHERE (f.data)::text ~* :p',
			$t('SELECT pages.id FROM pages JOIN field_int AS f ON f.pages_id=pages.id WHERE f.data RLIKE :p'));
		$this->check('table without alias resolves its own name',
			'SELECT id FROM field_int WHERE field_int.data!=0',
			$t("SELECT id FROM field_int WHERE field_int.data!=''"));
		$this->check('unqualified columns are typed when the statement has one table (SELECT, UPDATE, DELETE)',
			["SELECT pages_id FROM field_int WHERE data=0 OR (data)::text ILIKE :p", "UPDATE field_int SET data=0 WHERE pages_id=" . $num(':id'), "DELETE FROM field_int WHERE data=0"],
			[$t("SELECT pages_id FROM field_int WHERE data='' OR data LIKE :p"), $t("UPDATE field_int SET data='' WHERE pages_id=:id"), $t("DELETE FROM field_int WHERE data='Red'")]);
		$this->check('unqualified columns are left alone when the statement joins several tables',
			"SELECT f.pages_id FROM field_int AS f JOIN field_text AS t ON t.pages_id=f.pages_id WHERE data=''",
			$t("SELECT f.pages_id FROM field_int AS f JOIN field_text AS t ON t.pages_id=f.pages_id WHERE data=''"));
		$this->check('numeric (decimal) column compared to a bound value casts to numeric',
			'SELECT pages_id FROM field_dec WHERE data>' . $numDec(':v'),
			$t('SELECT pages_id FROM field_dec WHERE data>:v'));
		$this->check('positional ? parameters are not rewritten (the placeholder would be repeated)',
			'SELECT pages_id FROM field_int WHERE data=? AND pages_id=?',
			$t('SELECT pages_id FROM field_int WHERE data=? AND pages_id=?'));
		$this->check('a subquery on another table is typed by its own table, not the outer one',
			'SELECT pages_id FROM field_int WHERE pages_id IN (SELECT pages_id FROM field_text WHERE data=:v)',
			$t('SELECT pages_id FROM field_int WHERE pages_id IN (SELECT pages_id FROM field_text WHERE data=:v)'));
		$this->check('the subquery is still typed by its own table',
			'SELECT pages_id FROM field_text WHERE pages_id IN (SELECT pages_id FROM field_int WHERE data=0)',
			$t("SELECT pages_id FROM field_text WHERE pages_id IN (SELECT pages_id FROM field_int WHERE data='')"));
		$this->check('DELETE ... LIMIT keeps boolean and typed conversions in its WHERE',
			'DELETE FROM field_int WHERE ctid IN (SELECT ctid FROM field_int WHERE ((data & 1024) <> 0) AND data!=0 LIMIT 1)',
			$t("DELETE FROM field_int WHERE (data & 1024) AND data!='' LIMIT 1"));
		$this->check('UPDATE ... ORDER BY ... LIMIT keeps boolean conversions in its WHERE',
			'UPDATE field_int SET data=1 WHERE ctid IN (SELECT ctid FROM field_int WHERE ((data & 1024) <> 0) ORDER BY pages_id LIMIT 5)',
			$t('UPDATE field_int SET data=1 WHERE (data & 1024) ORDER BY pages_id LIMIT 5'));
		$this->check('multi-table DELETE gets typed comparisons',
			'DELETE FROM "field_int" AS "f" USING pages WHERE pages.id=f.pages_id AND f.data=0',
			$t("DELETE f FROM field_int AS f JOIN pages ON pages.id=f.pages_id WHERE f.data=''"));
		$this->check('mixed NULL and explicit identity values still get setval',
			"INSERT INTO unique_num (id) VALUES (DEFAULT), (9);\n" . $setval('unique_num', 'id'),
			$t('INSERT INTO unique_num (id) VALUES (NULL), (9)'));
		$this->check('typed translation before the schema changes', "SELECT pages_id FROM field_int WHERE data=0", $t("SELECT pages_id FROM field_int WHERE data=''"));
		$this->translator->setSchemaCache(['field_int' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'text']]]);
		$this->check('changing the schema cache drops translations that depended on the old schema',
			"SELECT pages_id FROM field_int WHERE data=''",
			$t("SELECT pages_id FROM field_int WHERE data=''"));
		$this->translator->setSchemaCache(['field_int' => ['primary' => ['pages_id'], 'identity' => null, 'columns' => ['pages_id' => 'integer', 'data' => 'integer']]]);
		$this->check('INSERT SET id=null into an identity column becomes DEFAULT without setval',
			'INSERT INTO unique_num (id) VALUES (DEFAULT)',
			$t('INSERT INTO unique_num SET id=null'));
		$this->check('INSERT VALUES with NULL for the identity column becomes DEFAULT',
			'INSERT INTO unique_num (id) VALUES (DEFAULT)',
			$t('INSERT INTO unique_num (id) VALUES (NULL)'));
		$this->check('MySQL double-quoted alias becomes an identifier', 'SELECT t.data AS "title__data" FROM t', $t('SELECT t.data AS "title__data" FROM t'));
		$this->check('mixed-case alias is quoted so its case survives (Postgres folds unquoted names)', 'SELECT (SELECT COUNT(*) FROM pages) AS "numChildren", false AS "isLoaded" FROM pages', $t('SELECT (SELECT COUNT(*) FROM pages) AS numChildren, false AS isLoaded FROM pages'));
		$this->check('lowercase alias is left unquoted', 'SELECT pages.templates_id AS templates_id FROM pages', $t('SELECT pages.templates_id AS templates_id FROM pages'));
		$this->check('references to a quoted alias are quoted too, for table and column aliases (PageFinder count subqueries)',
			'SELECT p.id, sub."numX" FROM pages p LEFT JOIN (SELECT "F_3".pages_id, COUNT("F_3".pages_id) AS "numX" FROM field_f AS "F_3" GROUP BY "F_3".pages_id) sub ON sub.pages_id=p.id ORDER BY "numX"',
			$t('SELECT p.id, sub.numX FROM pages p LEFT JOIN (SELECT F_3.pages_id, COUNT(F_3.pages_id) AS numX FROM field_f AS F_3 GROUP BY F_3.pages_id) sub ON sub.pages_id=p.id ORDER BY numX'));
		$this->check('alias references match case-insensitively, as in MySQL',
			'SELECT (SELECT COUNT(*) FROM pages) AS "numChildren" FROM pages ORDER BY "numChildren"',
			$t('SELECT (SELECT COUNT(*) FROM pages) AS numChildren FROM pages ORDER BY numchildren'));
		$this->check('a column in WHERE with the name of a select alias is the column (MySQL WHERE cannot see select aliases)',
			'SELECT id AS "ID" FROM t WHERE ID > 5',
			$t('SELECT id AS ID FROM t WHERE ID > 5'));
		$this->check('a derived table alias used in the outer WHERE is quoted (it is a column there)',
			'SELECT sub."numX" FROM (SELECT COUNT(*) AS "numX" FROM t) sub WHERE "numX" > 1',
			$t('SELECT sub.numX FROM (SELECT COUNT(*) AS numX FROM t) sub WHERE numX > 1'));
		$this->check('table alias references in another case are quoted with the alias case',
			'SELECT "F_3".pages_id FROM field_f AS "F_3" WHERE "F_3".data=1',
			$t('SELECT F_3.pages_id FROM field_f AS F_3 WHERE f_3.data=1'));
		$this->check('a function with the same name as a quoted alias is not quoted', 'SELECT COUNT(*) AS "Count" FROM t ORDER BY "Count"', $t('SELECT COUNT(*) AS Count FROM t ORDER BY Count'));
		$this->check('MySQL double-quoted string value stays a string', "SELECT 'x' AS a FROM t WHERE b='y'", $t('SELECT "x" AS a FROM t WHERE b="y"'));
	}

	/**
	 * Translate to a single statement string (multiple statements are joined with ";\n")
	 *
	 * @param string $sql
	 * @return string
	 *
	 */
	protected function translate($sql) {
		return implode(";\n", $this->translator->translateStatements($sql));
	}

	/**
	 * Does the callable throw a PDOException whose message matches the regex?
	 *
	 * @param callable $func
	 * @param string $regex
	 * @return bool
	 *
	 */
	protected function throwsMatching($func, $regex) {
		try {
			$func();
		} catch(\PDOException $e) {
			return (bool) preg_match($regex, $e->getMessage());
		}
		return false;
	}

	protected function testLiteralsAndIdentifiers() {
		$this->check('backtick identifiers become double quotes',
			'SELECT "id" FROM "pages" WHERE "pages"."id"=1',
			$this->translate('SELECT `id` FROM `pages` WHERE `pages`.`id`=1'));
		$this->check('mixed-case identifier keeps its case when quoted',
			'SELECT "Key_name" FROM "t"',
			$this->translate('SELECT `Key_name` FROM `t`'));
		$this->check('unquoted identifiers pass through',
			'SELECT id FROM pages',
			$this->translate('SELECT id FROM pages'));
		$this->check('MySQL backslash-escaped quote becomes doubled quote',
			"SELECT 'O''Reilly'",
			$this->translate("SELECT 'O\\'Reilly'"));
		$this->check('double-quoted MySQL string becomes single-quoted literal',
			"SELECT 'text'",
			$this->translate('SELECT "text"'));
		$this->check('escaped backslash becomes one backslash (standard_conforming_strings)',
			"SELECT 'a\\b'",
			$this->translate("SELECT 'a\\\\b'"));
		$this->check('escaped newline becomes literal newline',
			"SELECT 'a\nb'",
			$this->translate("SELECT 'a\\nb'"));
		$this->check('escaped percent keeps its backslash for LIKE',
			"SELECT 'a\\%b'",
			$this->translate("SELECT 'a\\%b'"));
		$this->check('NUL byte is removed (Postgres text cannot hold it)',
			"SELECT 'ab'",
			$this->translate("SELECT 'a\\0b'"));
		$this->check('quote() escapes MySQL-style for later translation',
			"'O\\'Reilly \\\\ \\n'",
			WireDatabasePgsqlTranslator::quote("O'Reilly \\ \n"));
		$this->check('quote() round-trips through translation',
			"SELECT 'O''Reilly \\ \n'",
			$this->translate('SELECT ' . WireDatabasePgsqlTranslator::quote("O'Reilly \\ \n")));
		$this->check('named parameters pass through', 'SELECT :i0X, :name', $this->translate('SELECT :i0X, :name'));
		$this->check('positional parameters pass through', 'SELECT ?', $this->translate('SELECT ?'));
		$this->check('line comment removed (replaced by a space)', 'SELECT 1  ', $this->translate("SELECT 1 -- comment"));
		$this->check('block comment removed', 'SELECT 1   FROM t', $this->translate("SELECT 1 /* x */ FROM t"));
		$first = $this->translator->translateStatements('SELECT 1');
		$this->check('translateStatements returns array of one statement', ['SELECT 1'], $first);
		$this->check('second call returns same result', $first, $this->translator->translateStatements('SELECT 1'));
		$this->check('indexName() prefixes with table', 'pages__parent_id', $this->translator->indexName('pages', 'parent_id'));
		$this->check('quoteId() double-quotes and doubles embedded quotes', '"a""b"', $this->translator->quoteId('a"b'));
	}
}
