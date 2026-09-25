<?php namespace ProcessWire;

/**
 * Tests for MySQL-to-SQLite translation and compatibility functions
 *
 */
class WireTest_WireDatabaseSQLiteTranslator extends WireTest {

	/** @var \PDO */
	protected $pdo;

	/** @var WireDatabaseSQLiteTranslator */
	protected $translator;

	public function allow() {
		return class_exists('\PDO', false) && in_array('sqlite', \PDO::getAvailableDrivers(), true);
	}

	public function init() {
		$pdoClass = class_exists('\\Pdo\\Sqlite', false) ? '\\Pdo\\Sqlite' : '\\PDO'; // PHP 8.4+: Pdo\Sqlite
		$this->pdo = new $pdoClass('sqlite::memory:');
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->translator = new WireDatabaseSQLiteTranslator($this->pdo);
		WireDatabaseSQLiteTranslator::registerFunctions($this->pdo);
	}

	public function execute() {
		$this->testCreateTableAndIndexes();
		$this->testAlterAndRename();
		$this->testInsertForms();
		$this->testUpdateDeleteAndNoOps();
		$this->testShowAndDescribe();
		$this->testExpressions();
		$this->testStringLiterals();
		$this->testFunctions();
		$this->testNamedLocks();
		$this->testIntendedBehaviorRegressions();
		$this->testRebuilds();
		$this->testGroupConcat();
		$this->testCollation();
		$this->testJsonFunctions();
		$this->testFts5Query();
		$this->testSiteDatabase();
	}

	/**
	 * pw_fts5query(): MySQL fulltext query syntax as an FTS5 MATCH expression, checked by what it matches
	 *
	 */
	protected function testFts5Query() {
		$this->check('fts5Query() of nothing matches nothing', '""', WireDatabaseSQLiteTranslator::fts5Query(''));
		$this->check('fts5Query(): required words', '"quick" AND "brown"', WireDatabaseSQLiteTranslator::fts5Query('+quick +brown'));
		$this->check('fts5Query(): alternatives', '"quick" OR "brown"', WireDatabaseSQLiteTranslator::fts5Query('quick brown'));

		$this->pdo->exec('DROP TABLE IF EXISTS fq');
		$this->pdo->exec("CREATE VIRTUAL TABLE fq USING fts5(k UNINDEXED, d, tokenize = \"" . WireDatabaseSQLiteTranslator::fulltextTokenize . "\", prefix = '2 3')");
		$corpus = [
			1 => 'The quick brown fox',
			2 => 'Café crème brûlée',
			3 => 'test_fme_content example',
			4 => 'quick silver lining',
			5 => 'brown bread and butter',
			6 => 'foo-bar baz',
			7 => "O'Brien at foo.example.com",
		];
		foreach($corpus as $k => $d) {
			$q = $this->pdo->prepare('INSERT INTO fq (k, d) VALUES (?, ?)');
			$q->execute([$k, $d]);
		}
		$match = function($query, $boolean = 1) {
			$q = $this->pdo->prepare('SELECT k FROM fq WHERE fq MATCH pw_fts5query(?, ?) ORDER BY k');
			$q->execute([$query, $boolean]);
			return array_map('intval', $q->fetchAll(\PDO::FETCH_COLUMN));
		};
		$cases = [
			// [query, boolean, expected keys, label]
			['quick', 1, [1, 4], 'a word'],
			['quick brown', 1, [1, 4, 5], 'words without operators are alternatives'],
			['+quick +brown', 1, [1], 'required words'],
			['+quick -fox', 1, [4], 'an excluded word'],
			['+quick brown', 1, [1, 4], 'optional words are left out when a word is required (as MySQL)'],
			['-fox', 1, [], 'only excluded words match nothing (as MySQL)'],
			['', 1, [], 'an empty query matches nothing'],
			['qui*', 1, [1, 4], 'a prefix'],
			['"quick brown"', 1, [1], 'a phrase'],
			['"brown quick"', 1, [], 'a phrase is in order'],
			['"quick brown" @3', 1, [1], '@distance is not a word'],
			['+(fox lining) +quick', 1, [1, 4], 'a required group'],
			['cafe', 1, [2], 'accents folded'],
			['CRÈME', 1, [2], 'case and accents folded'],
			['test', 1, [], 'an underscore is part of a word (as MySQL)'],
			['test_fme_content', 1, [3], 'a word with underscores'],
			['test_fme*', 1, [3], 'a prefix with an underscore'],
			['+foo.example.com', 1, [7], 'words split where MySQL splits them are all required'],
			["+o'brien*", 1, [7], 'a prefix the tokenizer splits'],
			['foo-bar', 1, [6], 'a hyphenated term'],
			['+ quick', 1, [1, 4], 'an operator without a word is ignored'],
			['(+) quick', 1, [1, 4], 'an operator before a closing paren'],
			['><~quick', 1, [1, 4], 'weight operators are ignored'],
			['+quick -fox', 0, [1, 4], 'natural language mode: operators are ignored'],
			['quick brown', 0, [1, 4, 5], 'natural language mode: any word'],
			['AND', 1, [5], 'an FTS5 keyword is a word'],
			['NEAR(', 1, [], 'NEAR( is not FTS5 syntax'],
			['"', 1, [], 'a lone quote'],
			[':', 1, [], 'a colon'],
			['^quick', 1, [1, 4], 'a caret'],
			['{quick}', 1, [1, 4], 'braces'],
			['*', 1, [], 'a lone asterisk'],
			['+', 1, [], 'a lone plus'],
			['"unterminated phrase', 1, [], 'an unterminated phrase'],
			['(quick', 1, [1, 4], 'an unterminated group'],
		];
		$actual = [];
		$expected = [];
		foreach($cases as $c) {
			list($query, $boolean, $keys, $label) = $c;
			$expected[$label] = $keys;
			try {
				$actual[$label] = $match($query, $boolean);
			} catch(\PDOException $e) {
				$actual[$label] = 'ERROR ' . $e->getMessage();
			}
		}
		$this->check('pw_fts5query() matches as MySQL boolean and natural language mode do', $expected, $actual);
		$this->check('pw_fts5query(NULL) matches nothing', [], $match(null));
		$this->pdo->exec('DROP TABLE fq');
	}

	/**
	 * MySQL JSON functions that SQLite does not provide
	 *
	 * Results here were compared against MySQL 8 and match.
	 *
	 */
	protected function testJsonFunctions() {

		$doc = "'" . '{"a":"x","b":[1,2,3],"c":{"d":"e"},"n":null,"t":true,"num":5}' . "'";

		$this->check('JSON_UNQUOTE removes quotes', 'hello', $this->value("SELECT JSON_UNQUOTE('\"hello\"')"));
		$this->check('JSON_UNQUOTE leaves unquoted value alone', 'hello', $this->value("SELECT JSON_UNQUOTE('hello')"));
		$this->check('JSON_UNQUOTE of JSON_EXTRACT returns value', 'x',
			$this->value("SELECT JSON_UNQUOTE(JSON_EXTRACT($doc, '$.a'))"));
		$this->check('JSON_UNQUOTE returns NULL for NULL', null, $this->value("SELECT JSON_UNQUOTE(NULL)"));

		$this->check('JSON_LENGTH counts object keys', 6, (int) $this->value("SELECT JSON_LENGTH($doc)"));
		$this->check('JSON_LENGTH counts array items', 3, (int) $this->value("SELECT JSON_LENGTH($doc, '$.b')"));
		$this->check('JSON_LENGTH of nested object', 1, (int) $this->value("SELECT JSON_LENGTH($doc, '$.c')"));
		$this->check('JSON_LENGTH of scalar is one', 1, (int) $this->value("SELECT JSON_LENGTH($doc, '$.a')"));
		$this->check('JSON_LENGTH of empty array is zero', 0, (int) $this->value("SELECT JSON_LENGTH('[]')"));
		$this->check('JSON_LENGTH returns NULL for missing path', null, $this->value("SELECT JSON_LENGTH($doc, '$.missing')"));
		$this->check('JSON_LENGTH returns NULL for invalid JSON', null, $this->value("SELECT JSON_LENGTH('not json')"));
		$this->check('JSON_LENGTH returns NULL for NULL', null, $this->value("SELECT JSON_LENGTH(NULL)"));

		$this->check('JSON_CONTAINS matches object member', 1, (int) $this->value("SELECT JSON_CONTAINS($doc, '{\"a\":\"x\"}')"));
		$this->check('JSON_CONTAINS rejects wrong value', 0, (int) $this->value("SELECT JSON_CONTAINS($doc, '{\"a\":\"y\"}')"));
		$this->check('JSON_CONTAINS finds scalar in array at path', 1, (int) $this->value("SELECT JSON_CONTAINS($doc, '2', '$.b')"));
		$this->check('JSON_CONTAINS rejects absent scalar', 0, (int) $this->value("SELECT JSON_CONTAINS($doc, '9', '$.b')"));
		$this->check('JSON_CONTAINS matches array subset', 1, (int) $this->value("SELECT JSON_CONTAINS($doc, '[1,3]', '$.b')"));
		$this->check('JSON_CONTAINS rejects partial array', 0, (int) $this->value("SELECT JSON_CONTAINS($doc, '[1,9]', '$.b')"));
		$this->check('JSON_CONTAINS distinguishes string from number', 0, (int) $this->value("SELECT JSON_CONTAINS($doc, '\"5\"', '$.num')"));
		$this->check('JSON_CONTAINS matches null value', 1, (int) $this->value("SELECT JSON_CONTAINS($doc, 'null', '$.n')"));
		$this->check('JSON_CONTAINS matches object in array', 1,
			(int) $this->value("SELECT JSON_CONTAINS('[{\"k\":1},{\"k\":2}]', '{\"k\":2}')"));
		$this->check('JSON_CONTAINS matches nested object subset', 1,
			(int) $this->value("SELECT JSON_CONTAINS('{\"a\":{\"b\":1,\"c\":2}}', '{\"a\":{\"b\":1}}')"));
		$this->check('JSON_CONTAINS returns NULL for missing path', null, $this->value("SELECT JSON_CONTAINS($doc, '1', '$.missing')"));
		$this->check('JSON_CONTAINS returns NULL for NULL', null, $this->value("SELECT JSON_CONTAINS(NULL, '1')"));

		// SQL that is already in SQLite syntax must survive translation unchanged, so that
		// code building native SQL for this dialect (i.e. an ON CONFLICT upsert) is not mangled
		$sql = "INSERT INTO `t` (`pages_id`, `data`) VALUES (:pages_id, :data) " .
			"ON CONFLICT (`pages_id`) DO UPDATE SET `data`=excluded.`data`";
		$this->check('native ON CONFLICT upsert is left unchanged', $sql, $this->translate($sql));

		$sql = "INSERT INTO `t` (`a`) VALUES (1) ON CONFLICT DO UPDATE SET `a`=excluded.`a`";
		$this->check('native ON CONFLICT without target is left unchanged', $sql, $this->translate($sql));

		// fulltext search has no SQLite equivalent and must report that clearly
		$error = '';
		try {
			$this->translate("SELECT id FROM t WHERE MATCH(data) AGAINST(:text)");
		} catch(\Exception $e) {
			$error = $e->getMessage();
		}
		$this->check('MATCH ... AGAINST throws a descriptive exception', true, stripos($error, 'not supported by SQLite') !== false);
		$this->check('MATCH ... AGAINST exception names the capability check', true, strpos($error, 'supportsFulltext()') !== false);

		$error = '';
		try {
			$this->translate("SELECT id FROM t WHERE MATCH(a, b) AGAINST ('x' IN BOOLEAN MODE)");
		} catch(\Exception $e) {
			$error = $e->getMessage();
		}
		$this->check('MATCH with multiple columns and boolean mode also throws', true, $error !== '');

		$this->check('SQLite MATCH operator is left alone', true,
			strpos($this->translate("SELECT id FROM t WHERE data MATCH 'x'"), 'MATCH') !== false);

		// provided by SQLite itself, under the same names and path syntax as MySQL
		$this->check('JSON_EXTRACT reads array index', 2, (int) $this->value("SELECT JSON_EXTRACT($doc, '$.b[1]')"));
		$this->check('JSON_SET adds member', '{"a":1,"b":2}', $this->value("SELECT JSON_SET('{\"a\":1}', '$.b', 2)"));
		$this->check('JSON_REMOVE removes member', '{"b":2}', $this->value("SELECT JSON_REMOVE('{\"a\":1,\"b\":2}', '$.a')"));
		$this->check('JSON_VALID recognizes valid JSON', 1, (int) $this->value("SELECT JSON_VALID('{\"a\":1}')"));
	}

	/**
	 * Case- and accent-insensitive comparison, as MySQL collations provide
	 *
	 */
	protected function testCollation() {

		$fold = function($value) { return WireDatabaseSQLiteTranslator::fold($value); };

		$this->check('fold() lowercases ASCII', 'apfel', $fold('APFEL'));
		$this->check('fold() removes accents', 'apfel', $fold('Äpfel'));
		$this->check('fold() handles multiple accents', 'creme brulee', $fold('Crème Brûlée'));
		$this->check('fold() handles Latin Extended-A', 'lodz', $fold('Łódź'));
		$this->check('fold() expands ligatures', 'aeon', $fold('ÆON'));
		$this->check('fold() expands sharp s', 'strasse', $fold('Straße'));
		$this->check('fold() lowercases other scripts', 'αθηνα', $fold('ΑΘΗΝΑ'));
		$this->check('fold() leaves ASCII punctuation alone', 'a-b_c%d', $fold('A-B_C%D'));
		$this->check('fold() accepts non-strings', '5', $fold(5));
		$this->check('fold(false) keeps case', 'Apfel', WireDatabaseSQLiteTranslator::fold('Äpfel', false));

		$this->check('LIKE matches accented value from unaccented pattern', 1,
			(int) $this->value("SELECT 'Äpfelkuchen' LIKE '%apfel%'"));
		$this->check('LIKE matches unaccented value from accented pattern', 1,
			(int) $this->value("SELECT 'apfelkuchen' LIKE '%Äpfel%'"));
		$this->check('LIKE prefix match', 1, (int) $this->value("SELECT 'Émile Zola' LIKE 'emile%'"));
		$this->check('LIKE suffix match', 1, (int) $this->value("SELECT 'Crème brûlée' LIKE '%brulee'"));
		$this->check('LIKE exact match', 1, (int) $this->value("SELECT 'Zürich' LIKE 'zurich'"));
		$this->check('LIKE underscore matches single character', 1, (int) $this->value("SELECT 'Äpfel' LIKE '_pfel'"));
		$this->check('LIKE does not match different word', 0, (int) $this->value("SELECT 'Äpfel' LIKE '%birne%'"));
		$this->check('LIKE with wildcards in middle', 1, (int) $this->value("SELECT 'Crème brûlée' LIKE 'cr%br%'"));
		$this->check('LIKE returns NULL for NULL value', null, $this->value("SELECT NULL LIKE '%a%'"));
		$this->check('LIKE matches numeric value', 1, (int) $this->value("SELECT 12345 LIKE '%234%'"));

		// escaped wildcards (the translator adds ESCAPE '\\' to LIKE)
		$this->check('LIKE escapes percent', 1, (int) $this->value("SELECT '50% off' LIKE '%50\\% off%'"));
		$this->check('LIKE escaped percent is not a wildcard', 0, (int) $this->value("SELECT '50 off' LIKE '%50\\% off%'"));
		$this->check('LIKE escapes underscore', 0, (int) $this->value("SELECT 'a-b' LIKE 'a\\_b'"));

		$this->check('REGEXP ignores accents', 1, (int) $this->value("SELECT 'Äpfel' REGEXP 'apfel'"));
		$this->check('REGEXP ignores case', 1, (int) $this->value("SELECT 'ÄPFEL' REGEXP 'apfel'"));
		$this->check('REGEXP word boundaries still work', 1,
			(int) $this->value("SELECT 'Crème brûlée today' REGEXP '\\\\bbrulee\\\\b'"));
		$this->check('REGEXP non-word-boundary is not folded to word boundary', 0,
			(int) $this->value("SELECT 'brulee' REGEXP '\\\\Bbrulee'"));

		$this->pdo->exec("CREATE TABLE collate_test (data TEXT COLLATE NOCASE)");
		foreach(['Äpfel', 'Apfelkuchen', 'Zürich', 'Émile', 'banana'] as $value) {
			$this->pdo->exec("INSERT INTO collate_test VALUES (" . WireDatabaseSQLiteTranslator::quote($value) . ")");
		}

		$sorted = $this->pdo->query("SELECT data FROM collate_test ORDER BY data COLLATE pw_ci")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('pw_ci sorts accented letters with their base letter',
			'Äpfel,Apfelkuchen,banana,Émile,Zürich', implode(',', $sorted));

		$sorted = $this->pdo->query("SELECT data FROM collate_test ORDER BY data")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('NOCASE (no collation given) sorts accented letters last',
			'Apfelkuchen,banana,Zürich,Äpfel,Émile', implode(',', $sorted));

		$this->check('pw_ci matches accented value in comparison', 1,
			(int) $this->pdo->query("SELECT COUNT(*) FROM collate_test WHERE data='apfel' COLLATE pw_ci")->fetchColumn());
		$this->check('NOCASE does not match accented value in comparison', 0,
			(int) $this->pdo->query("SELECT COUNT(*) FROM collate_test WHERE data='apfel'")->fetchColumn());

		$this->pdo->exec("DROP TABLE collate_test");
	}

	protected function testCreateTableAndIndexes() {
		$sql = $this->translate(
			"CREATE TABLE `items` (" .
			"`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, " .
			"`name` VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL, " .
			"`slug` VARCHAR(80) NOT NULL, " .
			"`body` TEXT NULL, " .
			"`changed` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, " .
			"PRIMARY KEY (`id`), " .
			"KEY `name_idx` (`name`(20)), " .
			"FULLTEXT KEY `body_ft` (`body`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
		);

		$this->check('CREATE TABLE uses INTEGER PRIMARY KEY AUTOINCREMENT', true, strpos($sql, '`id` INTEGER PRIMARY KEY AUTOINCREMENT') !== false);
		$this->check('CREATE TABLE strips UNSIGNED', false, stripos($sql, 'UNSIGNED') !== false);
		$this->check('CREATE TABLE strips ENGINE and CHARSET options', false, stripos($sql, 'ENGINE=') !== false || stripos($sql, 'CHARSET=') !== false);
		$this->check('CREATE TABLE strips ON UPDATE', false, stripos($sql, 'ON UPDATE') !== false);
		$this->check('CREATE TABLE adds COLLATE NOCASE to VARCHAR', true, strpos($sql, 'VARCHAR(80) COLLATE NOCASE') !== false);
		$this->check('CREATE TABLE adds COLLATE NOCASE to TEXT', true, strpos($sql, 'TEXT COLLATE NOCASE') !== false);
		$this->check('CREATE TABLE separates regular index', true, strpos($sql, 'CREATE INDEX `items__name_idx` ON `items` (`name`)') !== false);
		$this->check('CREATE TABLE removes prefix length from index', false, strpos($sql, '`name`(20)') !== false);
		$this->check('CREATE TABLE converts FULLTEXT to regular index', true, strpos($sql, 'CREATE INDEX `items__body_ft` ON `items` (`body`)') !== false);
		$this->pdo->exec($sql);

		$columns = $this->pdo->query("SELECT name, type, \"notnull\", dflt_value, pk FROM pragma_table_info('items')")->fetchAll(\PDO::FETCH_ASSOC);
		$this->check('translated CREATE TABLE executes with five columns', 5, count($columns));
		$this->check('translated autoincrement column has INTEGER type', 'INTEGER', $columns[0]['type']);
		$this->check('translated autoincrement column is primary key', 1, (int) $columns[0]['pk']);

		$indexes = $this->pdo->query("SELECT name FROM pragma_index_list('items') ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('translated CREATE TABLE creates table-prefixed indexes', ['items__body_ft', 'items__name_idx'], $indexes);

		$createIndex = $this->translate('CREATE UNIQUE INDEX IF NOT EXISTS `slug_unique` ON `items` (`slug`(32))');
		$this->check('CREATE INDEX prefixes index with table name', true, strpos($createIndex, '`items__slug_unique`') !== false);
		$this->check('CREATE INDEX preserves UNIQUE', true, strpos($createIndex, 'CREATE UNIQUE INDEX') === 0);
		$this->check('CREATE INDEX preserves IF NOT EXISTS', true, strpos($createIndex, 'IF NOT EXISTS') !== false);
		$this->check('CREATE INDEX strips prefix length', false, strpos($createIndex, '(32)') !== false);
		$this->pdo->exec($createIndex);
		$this->check('translated CREATE INDEX executes', 1, (int) $this->pdo->query("SELECT COUNT(*) FROM pragma_index_list('items') WHERE name='items__slug_unique'")->fetchColumn());
	}

	protected function testAlterAndRename() {
		$this->execMysql('CREATE TABLE `alter_test` (`id` INT NOT NULL, `value` VARCHAR(20), PRIMARY KEY (`id`))');

		$sql = $this->translate('ALTER TABLE `alter_test` ADD COLUMN `required_text` VARCHAR(20) NOT NULL');
		$this->check('ALTER ADD NOT NULL text supplies empty-string default', true, strpos($sql, "DEFAULT ''") !== false);
		$this->pdo->exec($sql);
		$sql = $this->translate('ALTER TABLE `alter_test` ADD `required_num` INT NOT NULL');
		$this->check('ALTER ADD NOT NULL numeric supplies zero default', true, strpos($sql, 'DEFAULT 0') !== false);
		$this->pdo->exec($sql);

		$this->execMysql('ALTER TABLE `alter_test` ADD INDEX `value_idx` (`value`(10))');
		$this->check('ALTER ADD INDEX creates prefixed index', 1, (int) $this->pdo->query("SELECT COUNT(*) FROM pragma_index_list('alter_test') WHERE name='alter_test__value_idx'")->fetchColumn());
		$this->execMysql('ALTER TABLE `alter_test` DROP INDEX `value_idx`');
		$this->check('ALTER DROP INDEX removes prefixed index', 0, (int) $this->pdo->query("SELECT COUNT(*) FROM pragma_index_list('alter_test') WHERE name='alter_test__value_idx'")->fetchColumn());

		$this->execMysql('ALTER TABLE `alter_test` RENAME COLUMN `value` TO `renamed_value`');
		$columns = $this->pdo->query("SELECT name FROM pragma_table_info('alter_test') ORDER BY cid")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('ALTER RENAME COLUMN executes', true, in_array('renamed_value', $columns, true));
		$this->execMysql('ALTER TABLE `alter_test` DROP COLUMN `required_num`');
		$columns = $this->pdo->query("SELECT name FROM pragma_table_info('alter_test') ORDER BY cid")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('ALTER DROP COLUMN executes', false, in_array('required_num', $columns, true));

		$this->execMysql('CREATE TABLE `rename_from` (`id` INT NOT NULL, `value` VARCHAR(20), PRIMARY KEY (`id`), KEY `value_idx` (`value`))');
		$statements = $this->translator->translateStatements('RENAME TABLE `rename_from` TO `rename_to`');
		$this->check('RENAME TABLE recreates prefixed index under new name', true, strpos(implode(";", $statements), 'rename_to__value_idx') !== false);
		$this->execMysql('RENAME TABLE `rename_from` TO `rename_to`');
		$this->check('RENAME TABLE executes', 1, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='rename_to'")->fetchColumn());
		$this->check('RENAME TABLE renames prefixed index', 1, (int) $this->pdo->query("SELECT COUNT(*) FROM pragma_index_list('rename_to') WHERE name='rename_to__value_idx'")->fetchColumn());
		$this->check('RENAME TABLE leaves no index with old prefix', 0, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name LIKE 'rename\\_from\\_\\_%' ESCAPE '\\'")->fetchColumn());

		$this->execMysql('CREATE INDEX `standalone_idx` ON `alter_test` (`renamed_value`)');
		$sql = $this->translate('DROP INDEX `standalone_idx` ON `alter_test`');
		$this->check('DROP INDEX ON targets prefixed index name', 'DROP INDEX IF EXISTS `alter_test__standalone_idx`', $sql);
		$this->pdo->exec($sql);
		$this->check('translated DROP INDEX executes', 0, (int) $this->pdo->query("SELECT COUNT(*) FROM pragma_index_list('alter_test') WHERE name='alter_test__standalone_idx'")->fetchColumn());
	}

	protected function testInsertForms() {
		$this->execMysql(
			'CREATE TABLE `insert_test` (' .
			'`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(20) NOT NULL, `value` INT NOT NULL DEFAULT 0, ' .
			'PRIMARY KEY (`id`), UNIQUE KEY `code` (`code`))'
		);

		$sql = $this->translate("INSERT INTO `insert_test` SET `code`='a', `value`=1");
		$this->check('INSERT SET becomes column/value lists', true, strpos($sql, "(`code`, `value`) VALUES ('a', 1)") !== false);
		$this->pdo->exec($sql);
		$this->check('translated INSERT SET executes', 1, (int) $this->pdo->query("SELECT value FROM insert_test WHERE code='a'")->fetchColumn());

		$sql = $this->translate("INSERT IGNORE INTO `insert_test` (`code`, `value`) VALUES ('a', 99)");
		$this->check('INSERT IGNORE becomes INSERT OR IGNORE', true, strpos($sql, 'INSERT OR IGNORE') === 0);
		$this->pdo->exec($sql);
		$this->check('translated INSERT IGNORE ignores duplicate', 1, (int) $this->pdo->query("SELECT value FROM insert_test WHERE code='a'")->fetchColumn());

		$sql = $this->translate("INSERT INTO `insert_test` (`code`, `value`) VALUES ('a', 2) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
		$this->check('ON DUPLICATE becomes ON CONFLICT', true, strpos($sql, 'ON CONFLICT DO UPDATE SET') !== false);
		$this->check('VALUES(column) becomes excluded.column', true, strpos($sql, 'excluded.`value`') !== false);
		$this->pdo->exec($sql);
		$this->check('translated ON DUPLICATE updates existing row', 2, (int) $this->pdo->query("SELECT value FROM insert_test WHERE code='a'")->fetchColumn());

		$this->execMysql("INSERT INTO `insert_test` (`code`, `value`) VALUES ('b', 3)");
		$this->pdo->exec('CREATE TABLE insert_source (code TEXT, value INTEGER)');
		$this->pdo->exec("INSERT INTO insert_source VALUES ('b', 4), ('c', 5)");
		$sql = $this->translate('INSERT INTO `insert_test` (`code`, `value`) SELECT code, value FROM insert_source ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
		$this->check('INSERT SELECT ON DUPLICATE adds disambiguating WHERE', true, strpos($sql, 'WHERE true ON CONFLICT') !== false);
		$this->pdo->exec($sql);
		$this->check('translated INSERT SELECT updates duplicate', 4, (int) $this->pdo->query("SELECT value FROM insert_test WHERE code='b'")->fetchColumn());
		$this->check('translated INSERT SELECT inserts new row', 5, (int) $this->pdo->query("SELECT value FROM insert_test WHERE code='c'")->fetchColumn());
	}

	protected function testUpdateDeleteAndNoOps() {
		$this->execMysql('CREATE TABLE `limit_test` (`id` INT NOT NULL, `value` VARCHAR(20), PRIMARY KEY (`id`))');
		$this->pdo->exec("INSERT INTO limit_test VALUES (1, 'a'), (2, 'b'), (3, 'c')");

		$sql = $this->translate("UPDATE `limit_test` SET `value`='updated' ORDER BY `id` DESC LIMIT 1");
		$this->check('UPDATE ORDER BY LIMIT uses rowid subquery', true, strpos($sql, 'WHERE rowid IN (SELECT rowid FROM `limit_test`') !== false);
		$this->pdo->exec($sql);
		$this->check('translated UPDATE ORDER BY LIMIT updates selected row', 'updated', $this->pdo->query('SELECT value FROM limit_test WHERE id=3')->fetchColumn());

		$sql = $this->translate('DELETE FROM `limit_test` ORDER BY `id` ASC LIMIT 1');
		$this->check('DELETE ORDER BY LIMIT uses rowid subquery', true, strpos($sql, 'WHERE rowid IN (SELECT rowid FROM `limit_test`') !== false);
		$this->pdo->exec($sql);
		$this->check('translated DELETE ORDER BY LIMIT deletes selected row', [2, 3], array_map('intval', $this->pdo->query('SELECT id FROM limit_test ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN)));

		$statements = $this->translator->translateStatements('TRUNCATE TABLE `limit_test`');
		$this->check('TRUNCATE becomes DELETE', 'DELETE FROM `limit_test`', $statements[0]);
		$this->check('TRUNCATE resets AUTOINCREMENT sequence', "DELETE FROM sqlite_sequence WHERE name='limit_test'", isset($statements[1]) ? $statements[1] : '');
		$this->execMysql('TRUNCATE TABLE `limit_test`');
		$this->check('translated TRUNCATE removes rows', 0, (int) $this->pdo->query('SELECT COUNT(*) FROM limit_test')->fetchColumn());

		$this->check('DO becomes SELECT', 'SELECT 1', trim($this->translate('DO 1')));
		$this->check('SET becomes no-op SELECT', 'SELECT 1', $this->translate("SET NAMES 'utf8mb4'"));
		$this->check('LOCK becomes no-op SELECT', 'SELECT 1', $this->translate('LOCK TABLES items WRITE'));
		$this->check('UNLOCK becomes no-op SELECT', 'SELECT 1', $this->translate('UNLOCK TABLES'));
		$this->check('OPTIMIZE becomes no-op SELECT', 'SELECT 1', $this->translate('OPTIMIZE TABLE items'));
		foreach(['DO 1', "SET NAMES 'utf8mb4'", 'LOCK TABLES items WRITE', 'UNLOCK TABLES', 'OPTIMIZE TABLE items'] as $noOp) {
			$this->pdo->query($this->translate($noOp))->fetchColumn();
		}
		$this->ok('translated DO/SET/LOCK/UNLOCK/OPTIMIZE statements execute');
	}

	protected function testShowAndDescribe() {
		$stmt = $this->pdo->query($this->translate('SHOW COLUMNS FROM `items`'));
		$this->check('SHOW COLUMNS result names match MySQL', ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], $this->columnNames($stmt));
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$this->check('SHOW COLUMNS returns item columns', ['id', 'name', 'slug', 'body', 'changed'], array_column($rows, 'Field'));
		$this->check('SHOW COLUMNS reports auto_increment', 'auto_increment', $rows[0]['Extra']);

		$stmt = $this->pdo->query($this->translate('DESCRIBE `items` `name`'));
		$this->check('DESCRIBE result names match SHOW COLUMNS', ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], $this->columnNames($stmt));
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$this->check('DESCRIBE column filter works', 'name', $row['Field']);

		$stmt = $this->pdo->query($this->translate('SHOW INDEX FROM `items`'));
		$this->check('SHOW INDEX result names match MySQL subset', ['Table', 'Key_name', 'Non_unique', 'Seq_in_index', 'Column_name', 'Index_type'], $this->columnNames($stmt));
		$indexes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$this->check('SHOW INDEX reports PRIMARY', true, in_array('PRIMARY', array_column($indexes, 'Key_name'), true));
		$this->check('SHOW INDEX removes table prefix from index names', true, in_array('name_idx', array_column($indexes, 'Key_name'), true));

		$stmt = $this->pdo->query($this->translate('SHOW CREATE TABLE `items`'));
		$this->check('SHOW CREATE TABLE result names match MySQL', ['Table', 'Create Table'], $this->columnNames($stmt));
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$this->check('SHOW CREATE TABLE identifies requested table', 'items', $row['Table']);
		$this->check('SHOW CREATE TABLE returns MySQL-style CREATE', true, strpos($row['Create Table'], 'CREATE TABLE `items`') === 0);

		$stmt = $this->pdo->query($this->translate('SHOW VARIABLES'));
		$this->check('SHOW VARIABLES result names match MySQL', ['Variable_name', 'Value'], $this->columnNames($stmt));
		$this->check('SHOW VARIABLES empty emulation returns no rows', [], $stmt->fetchAll(\PDO::FETCH_ASSOC));

		$stmt = $this->pdo->query($this->translate("SHOW TABLES LIKE 'items'"));
		$this->check('SHOW TABLES LIKE filters table names', 'items', $stmt->fetchColumn());
	}

	protected function testExpressions() {
		$this->check('RLIKE becomes REGEXP', "SELECT 'Alpha' REGEXP '^a'", $this->translate("SELECT 'Alpha' RLIKE '^a'"));
		$this->check('translated RLIKE executes case-insensitively', 1, (int) $this->pdo->query($this->translate("SELECT 'Alpha' RLIKE '^a'"))->fetchColumn());

		$sql = $this->translate("SELECT 'a_b' LIKE 'a\\\\_b'");
		$this->check('LIKE adds explicit backslash ESCAPE', true, strpos($sql, "ESCAPE '\\'") !== false);
		$this->check('translated LIKE preserves escaped underscore', 1, (int) $this->pdo->query($sql)->fetchColumn());

		$sql = $this->translate("SELECT '2024-01-02 00:00:00' + INTERVAL 2 DAY");
		$this->check('INTERVAL addition becomes date_add', true, strpos($sql, 'date_add(') !== false);
		$this->check('translated INTERVAL addition executes', '2024-01-04 00:00:00', $this->pdo->query($sql)->fetchColumn());
		$sql = $this->translate("SELECT DATE_SUB('2024-01-04', INTERVAL '2' DAY)");
		$this->check('quoted numeric INTERVAL is unquoted safely', true, strpos($sql, "interval_str(2, 'day')") !== false);
		$this->check('translated DATE_SUB executes (DATE input returns DATE, as in MySQL)', '2024-01-02', $this->pdo->query($sql)->fetchColumn());

		$sql = $this->translate("SELECT GROUP_CONCAT(value SEPARATOR '|') FROM insert_source");
		$this->check('GROUP_CONCAT SEPARATOR becomes second argument', true, stripos($sql, "group_concat(value, '|')") !== false);
		$this->check('translated GROUP_CONCAT SEPARATOR executes', '4|5', $this->pdo->query($sql)->fetchColumn());

		$this->check('CAST UNSIGNED becomes INTEGER', 'SELECT CAST(12 AS INTEGER)', $this->translate('SELECT CAST(12 AS UNSIGNED)'));
		$this->check('CAST CHAR becomes TEXT', "SELECT CAST(12 AS TEXT)", $this->translate('SELECT CAST(12 AS CHAR)'));
		$this->check('CAST DECIMAL size becomes NUMERIC', 'SELECT CAST(12 AS NUMERIC)', $this->translate('SELECT CAST(12 AS DECIMAL(10,2))'));
		$this->check('translated CAST executes', 12, (int) $this->pdo->query($this->translate('SELECT CAST(12 AS UNSIGNED)'))->fetchColumn());

		$sql = $this->translate('SELECT SQL_CALC_FOUND_ROWS SQL_NO_CACHE id FROM items');
		$this->check('SQL_CALC_FOUND_ROWS is removed', false, strpos($sql, 'SQL_CALC_FOUND_ROWS') !== false);
		$this->check('SQL_NO_CACHE is removed', false, strpos($sql, 'SQL_NO_CACHE') !== false);
	}

	protected function testStringLiterals() {
		$allBytes = '';
		for($n = 0; $n < 256; $n++) $allBytes .= chr($n);
		$roundTrip = $this->pdo->query($this->translate('SELECT ' . WireDatabaseSQLiteTranslator::quote($allBytes)))->fetchColumn();
		$this->check('quote() round-trips all 256 byte values', $allBytes, $roundTrip);

		$tricky = [
			'', "'", '"', '\\', "tail\\", "line\nfeed", "carriage\rreturn", "nul\0byte", "ctrl\x1abyte",
			"quote' and slash\\", "double\" and slash\\", "日本語 Äpfel",
		];
		$failures = [];
		foreach($tricky as $value) {
			$actual = $this->pdo->query($this->translate('SELECT ' . WireDatabaseSQLiteTranslator::quote($value)))->fetchColumn();
			if($actual !== $value) $failures[] = [bin2hex($value), bin2hex($actual)];
		}
		$this->check('quote() round-trips tricky strings', [], $failures);

		$value = "double quoted value ending in slash\\";
		$escaped = substr(WireDatabaseSQLiteTranslator::quote($value), 1, -1);
		$actual = $this->pdo->query($this->translate('SELECT "' . $escaped . '"'))->fetchColumn();
		$this->check('double-quoted escapeStr value ending in backslash round-trips', $value, $actual);

		$value = "addslashes ' \" \\ \0 end";
		$actual = $this->pdo->query($this->translate("SELECT '" . addslashes($value) . "'"))->fetchColumn();
		$this->check('addslashes-escaped value round-trips', $value, $actual);

		$sql = $this->translate("SELECT DATE_ADD('2024-01-01', INTERVAL '1 OR malicious()' DAY)");
		$this->check('non-numeric quoted INTERVAL amount remains quoted', true, strpos($sql, "interval_str('1 OR malicious()', 'day')") !== false);
	}

	protected function testFunctions() {
		$now = $this->value('SELECT NOW()');
		$this->check('NOW returns MySQL datetime shape', 1, preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $now));
		$this->check('UNIX_TIMESTAMP() returns current timestamp', true, abs(time() - (int) $this->value('SELECT UNIX_TIMESTAMP()')) <= 2);
		$this->check('UNIX_TIMESTAMP(NULL) returns NULL', null, $this->value('SELECT UNIX_TIMESTAMP(NULL)'));

		$ts = strtotime('2024-05-06 13:14:15');
		$this->check('FROM_UNIXTIME formats timestamp', date('Y-m-d H:i:s', $ts), $this->value("SELECT FROM_UNIXTIME($ts)"));
		$this->check('FROM_UNIXTIME supports MySQL format', '2024/05/06', $this->value("SELECT FROM_UNIXTIME($ts, '%Y/%m/%d')"));
		$this->check('DATE_FORMAT supports MySQL format', '2024-05-06 13:14', $this->value("SELECT DATE_FORMAT('2024-05-06 13:14:15', '%Y-%m-%d %H:%i')"));
		$this->check('DATE_ADD adds interval', '2024-05-08 00:00:00', $this->value("SELECT DATE_ADD('2024-05-06 00:00:00', interval_str(2, 'day'))"));
		$this->check('DATE_SUB subtracts interval', '2024-05-04 00:00:00', $this->value("SELECT DATE_SUB('2024-05-06 00:00:00', interval_str(2, 'day'))"));

		$this->check('IF returns true branch', 'yes', $this->value("SELECT IF(1, 'yes', 'no')"));
		$this->check('IF returns false branch', 'no', $this->value("SELECT IF(0, 'yes', 'no')"));
		$this->check('FIELD returns one-based position', 2, (int) $this->value("SELECT FIELD('b', 'a', 'b', 'c')"));
		$this->check('FIELD returns zero when absent', 0, (int) $this->value("SELECT FIELD('x', 'a', 'b', 'c')"));

		$this->check('CONCAT joins arguments', 'abc', $this->value("SELECT CONCAT('a', 'b', 'c')"));
		$this->check('CONCAT returns NULL when any argument is NULL', null, $this->value("SELECT CONCAT('a', NULL, 'c')"));
		$this->check('CONCAT_WS skips NULL values', 'a,c', $this->value("SELECT CONCAT_WS(',', 'a', NULL, 'c')"));
		$this->check('LOWER handles UTF-8', 'äpfel', $this->value("SELECT LOWER('ÄPFEL')"));
		$this->check('UPPER handles UTF-8', 'ÄPFEL', $this->value("SELECT UPPER('äpfel')"));
		$this->check('LOCATE is one-based', 2, (int) $this->value("SELECT LOCATE('bc', 'AbCd')"));
		$this->check('LOCATE returns zero when absent', 0, (int) $this->value("SELECT LOCATE('x', 'abc')"));
		$this->check('SUBSTRING_INDEX positive count', 'a.b', $this->value("SELECT SUBSTRING_INDEX('a.b.c', '.', 2)"));
		$this->check('SUBSTRING_INDEX negative count', 'b.c', $this->value("SELECT SUBSTRING_INDEX('a.b.c', '.', -2)"));

		$this->check('REGEXP is case-insensitive', 1, (int) $this->value("SELECT REGEXP('^ä', 'Äpfel')"));
		$this->check('REGEXP returns zero for delimiter byte', 0, (int) $this->value("SELECT REGEXP(char(1), 'anything')"));
		$this->check('GREATEST returns greatest value', 9, (int) $this->value('SELECT GREATEST(2, 9, 4)'));
		$this->check('LEAST returns least value', 2, (int) $this->value('SELECT LEAST(2, 9, 4)'));
		$this->check('DATABASE defaults to main', 'main', $this->value('SELECT DATABASE()'));
	}

	protected function testNamedLocks() {
		$name = 'wire_test_' . str_replace('.', '_', uniqid('', true));
		$q = $this->pdo->quote($name);
		$first = $this->value("SELECT GET_LOCK($q, 0)");
		$second = $this->value("SELECT GET_LOCK($q, 0)");
		$held = $this->value("SELECT IS_FREE_LOCK($q)");
		$release1 = $this->value("SELECT RELEASE_LOCK($q)");
		$stillHeld = $this->value("SELECT IS_FREE_LOCK($q)");
		$release2 = $this->value("SELECT RELEASE_LOCK($q)");
		$free = $this->value("SELECT IS_FREE_LOCK($q)");
		$release3 = $this->value("SELECT RELEASE_LOCK($q)");

		$this->check('GET_LOCK acquires named lock', 1, (int) $first);
		$this->check('GET_LOCK is re-entrant', 1, (int) $second);
		$this->check('IS_FREE_LOCK reports held lock', 0, (int) $held);
		$this->check('first RELEASE_LOCK decrements re-entrant hold', 1, (int) $release1);
		$this->check('lock remains held after one re-entrant release', 0, (int) $stillHeld);
		$this->check('second RELEASE_LOCK releases lock', 1, (int) $release2);
		$this->check('IS_FREE_LOCK reports released lock', 1, (int) $free);
		$this->check('RELEASE_LOCK on unheld lock returns NULL', null, $release3);
	}

	/**
	 * Intended MySQL behavior currently exposing translator/function defects.
	 *
	 * Results are gathered first so one failure reports every outstanding mismatch.
	 */
	protected function testIntendedBehaviorRegressions() {
		$expected = [
			'insertSelectOrder' => 'ok',
			'groupConcatDistinct' => 'b,c',
			'groupConcatOrderedSeparator' => 'a|b|a',
			'binaryMatchCount' => 1,
			'minusWithoutCommentSpace' => 7,
			'showTablesColumn' => 'Tables_in_main',
			'truncateNextId' => 1,
			'multipleRenameCount' => 2,
			'greatestNull' => null,
			'leastNull' => null,
			'concatWsNullSeparator' => null,
			'substringIndexEmptyDelimiter' => '',
			'dateAddMonthEnd' => '2024-02-29 00:00:00',
		];
		$actual = [];

		try {
			$sql = $this->translate('INSERT INTO `insert_test` (`code`, `value`) SELECT code, value FROM insert_source ORDER BY code ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
			$this->pdo->exec($sql);
			$actual['insertSelectOrder'] = 'ok';
		} catch(\Throwable $e) {
			$actual['insertSelectOrder'] = get_class($e) . ': ' . $e->getMessage();
		}

		try {
			$actual['groupConcatDistinct'] = $this->value("SELECT GROUP_CONCAT(DISTINCT code SEPARATOR ',') FROM insert_source");
		} catch(\Throwable $e) {
			$actual['groupConcatDistinct'] = get_class($e) . ': ' . $e->getMessage();
		}
		$this->pdo->exec("CREATE TABLE group_concat_test (value TEXT, sort INTEGER); INSERT INTO group_concat_test VALUES ('b', 2), ('a', 1), ('a', 3)");
		try {
			$actual['groupConcatOrderedSeparator'] = $this->value("SELECT GROUP_CONCAT(value ORDER BY sort SEPARATOR '|') FROM group_concat_test");
		} catch(\Throwable $e) {
			$actual['groupConcatOrderedSeparator'] = get_class($e) . ': ' . $e->getMessage();
		}
		$this->pdo->exec("CREATE TABLE binary_test (value TEXT COLLATE NOCASE); INSERT INTO binary_test VALUES ('A'), ('a')");
		$actual['binaryMatchCount'] = (int) $this->value("SELECT COUNT(*) FROM binary_test WHERE BINARY value='A'");
		$actual['minusWithoutCommentSpace'] = (int) $this->value('SELECT 5--2');
		$showTables = $this->pdo->query($this->translate('SHOW TABLES'));
		$actual['showTablesColumn'] = $this->columnNames($showTables)[0];
		$this->execMysql('CREATE TABLE truncate_sequence (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))');
		$this->pdo->exec('INSERT INTO truncate_sequence DEFAULT VALUES');
		$this->execMysql('TRUNCATE TABLE truncate_sequence');
		$this->pdo->exec('INSERT INTO truncate_sequence DEFAULT VALUES');
		$actual['truncateNextId'] = (int) $this->pdo->lastInsertId();
		$this->pdo->exec('CREATE TABLE multi_a (id INTEGER); CREATE TABLE multi_c (id INTEGER)');
		$this->pdo->exec($this->translate('RENAME TABLE multi_a TO multi_b, multi_c TO multi_d'));
		$actual['multipleRenameCount'] = (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name IN ('multi_b','multi_d')")->fetchColumn();
		$actual['greatestNull'] = $this->value('SELECT GREATEST(1, NULL)');
		$actual['leastNull'] = $this->value('SELECT LEAST(1, NULL)');
		$actual['concatWsNullSeparator'] = $this->value("SELECT CONCAT_WS(NULL, 'a', 'b')");
		try {
			$actual['substringIndexEmptyDelimiter'] = $this->value("SELECT SUBSTRING_INDEX('abc', '', 1)");
		} catch(\Throwable $e) {
			$actual['substringIndexEmptyDelimiter'] = get_class($e) . ': ' . $e->getMessage();
		}
		$actual['dateAddMonthEnd'] = $this->value("SELECT DATE_ADD('2024-01-31 00:00:00', interval_str(1, 'month'))");

		$this->check('translator matches intended MySQL behavior for regression cases', $expected, $actual);
	}

	protected function translate($sql) {
		return $this->translator->translate($sql);
	}

	protected function execMysql($sql) {
		$qty = 0;
		foreach($this->translator->translateStatements($sql) as $statement) $qty += (int) $this->pdo->exec($statement);
		return $qty;
	}

	/**
	 * Table rebuilds (ALTER TABLE operations SQLite cannot do natively)
	 *
	 */
	protected function testRebuilds() {
		$columns = function($table) {
			return $this->pdo->query("SELECT name FROM pragma_table_info('$table') ORDER BY cid")->fetchAll(\PDO::FETCH_COLUMN);
		};

		// combined ALTER: operations before a rebuild must not be lost
		$this->execMysql('CREATE TABLE `rebuild_combo` (`id` INT UNSIGNED NOT NULL, `value` VARCHAR(20), `old` INT, PRIMARY KEY (`id`), KEY `value` (`value`))');
		$this->pdo->exec("INSERT INTO rebuild_combo VALUES (1, 'a', 5)");
		$this->execMysql('ALTER TABLE `rebuild_combo` ADD COLUMN `added` INT NOT NULL DEFAULT 7, MODIFY `value` VARCHAR(40) NOT NULL, CHANGE `old` `renamed` INT, ADD INDEX `added` (`added`)');
		$this->check('combined ALTER keeps added column with rebuild', ['id', 'value', 'renamed', 'added'], $columns('rebuild_combo'));
		$this->check('combined ALTER copies data and applies defaults', ['1', 'a', '5', '7'], array_map('strval', array_values($this->pdo->query('SELECT * FROM rebuild_combo')->fetch(\PDO::FETCH_ASSOC))));
		$indexes = $this->pdo->query("SELECT name FROM pragma_index_list('rebuild_combo') WHERE origin='c' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
		$this->check('combined ALTER recreates old and adds new indexes', ['rebuild_combo__added', 'rebuild_combo__value'], $indexes);
		$this->check('MODIFY changes column type', 'VARCHAR(40)', $this->pdo->query("SELECT type FROM pragma_table_info('rebuild_combo') WHERE name='value'")->fetchColumn());

		// dropping an indexed column (SQLite refuses natively, MySQL removes it from the index)
		$this->execMysql('ALTER TABLE `rebuild_combo` DROP COLUMN `value`');
		$this->check('DROP indexed COLUMN removes column', ['id', 'renamed', 'added'], $columns('rebuild_combo'));
		$this->check('DROP indexed COLUMN removes its index', 0, (int) $this->pdo->query("SELECT COUNT(*) FROM pragma_index_list('rebuild_combo') WHERE name='rebuild_combo__value'")->fetchColumn());

		// rebuild refuses tables with things it would not preserve (fails before changing anything)
		$this->pdo->exec('CREATE TABLE rebuild_check (id INTEGER PRIMARY KEY, value INT CHECK (value >= 0))');
		$this->pdo->exec('CREATE TABLE rebuild_trigger (id INTEGER PRIMARY KEY, value INT)');
		$this->pdo->exec('CREATE TRIGGER rebuild_trigger_t AFTER INSERT ON rebuild_trigger BEGIN SELECT 1; END');
		foreach(['rebuild_check', 'rebuild_trigger'] as $table) {
			$refused = false;
			try {
				$this->translator->translateStatements("ALTER TABLE `$table` MODIFY `value` BIGINT NOT NULL");
			} catch(\PDOException $e) {
				$refused = true;
			}
			$this->check("rebuild refused for $table", true, $refused);
		}
		$this->check('refused rebuild keeps trigger', 1, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='trigger'")->fetchColumn());
	}

	/**
	 * GROUP_CONCAT forms, including the FieldtypeMulti::getLoadQueryAutojoin() shape
	 *
	 */
	protected function testGroupConcat() {
		$this->pdo->exec("CREATE TABLE gc (pages_id INT, data TEXT, sort INT)");
		$this->pdo->exec("INSERT INTO gc VALUES (1, 'b', 2), (1, 'a', 1), (1, 'c', 3), (1, 'a', 4), (2, NULL, 1), (2, 'x', 2)");
		$this->check('GROUP_CONCAT SEPARATOR', true, in_array($this->value("SELECT GROUP_CONCAT(data SEPARATOR '|') FROM gc WHERE pages_id=1"), ['b|a|c|a', 'a|b|c|a'], true));
		$this->check('GROUP_CONCAT ORDER BY SEPARATOR', 'a|b|c|a', $this->value("SELECT GROUP_CONCAT(gc.data ORDER BY gc.sort SEPARATOR '|') FROM gc WHERE pages_id=1"));
		$this->check('GROUP_CONCAT ORDER BY DESC', 'a,c,b,a', $this->value("SELECT GROUP_CONCAT(data ORDER BY sort DESC) FROM gc WHERE pages_id=1"));
		$this->check('GROUP_CONCAT DISTINCT ORDER BY SEPARATOR (FieldtypeMulti autojoin)', 'a|b|c', $this->value("SELECT GROUP_CONCAT(DISTINCT gc.data ORDER BY gc.sort SEPARATOR '|') FROM gc WHERE pages_id=1"));
		$this->check('GROUP_CONCAT skips NULL values', 'x', $this->value("SELECT GROUP_CONCAT(data ORDER BY sort SEPARATOR '|') FROM gc WHERE pages_id=2"));
		$this->check('GROUP_CONCAT with no values returns NULL', null, $this->value("SELECT GROUP_CONCAT(DISTINCT data ORDER BY sort) FROM gc WHERE pages_id=3"));
		$rows = $this->pdo->query($this->translate("SELECT pages_id, GROUP_CONCAT(data ORDER BY sort SEPARATOR ',') AS d FROM gc GROUP BY pages_id ORDER BY pages_id"))->fetchAll(\PDO::FETCH_KEY_PAIR);
		$this->check('GROUP_CONCAT per group', [1 => 'a,b,c,a', 2 => 'x'], $rows);
		// multi-table DELETE (as used by Fields::deleteFieldDataByTemplate() and ProcessField)
		$this->pdo->exec("CREATE TABLE md_pages (id INTEGER PRIMARY KEY, templates_id INT)");
		$this->pdo->exec("CREATE TABLE md_field (pages_id INT, data TEXT)");
		$this->pdo->exec("INSERT INTO md_pages VALUES (1, 5), (2, 6), (3, 5)");
		$this->pdo->exec("INSERT INTO md_field VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'orphan')");
		$query = $this->pdo->prepare($this->translate('DELETE md_field FROM md_field INNER JOIN md_pages ON md_pages.id=md_field.pages_id WHERE md_pages.templates_id=:templates_id'));
		$query->execute([':templates_id' => 5]);
		$this->check('multi-table DELETE with INNER JOIN', ['2', '4'], array_map('strval', $this->pdo->query('SELECT pages_id FROM md_field ORDER BY pages_id')->fetchAll(\PDO::FETCH_COLUMN)));
		$this->execMysql('DELETE md_field FROM md_field LEFT JOIN md_pages ON md_field.pages_id=md_pages.id WHERE md_pages.id IS NULL');
		$this->check('multi-table DELETE with LEFT JOIN (orphans)', ['2'], array_map('strval', $this->pdo->query('SELECT pages_id FROM md_field')->fetchAll(\PDO::FETCH_COLUMN)));

		// DEFAULT CURRENT_TIMESTAMP uses local time (MySQL uses the server time zone; SQLite's is UTC)
		$this->execMysql('CREATE TABLE `ts_test` (`id` INT NOT NULL, `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`))');
		$this->pdo->exec('INSERT INTO ts_test (id) VALUES (1)');
		$created = strtotime($this->pdo->query('SELECT created FROM ts_test')->fetchColumn());
		$local = strtotime($this->pdo->query("SELECT datetime('now', 'localtime')")->fetchColumn());
		$this->check('DEFAULT CURRENT_TIMESTAMP is local time', true, abs($created - $local) < 5);
		$this->execMysql('ALTER TABLE `ts_test` ADD `modified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
		$this->check('ADD COLUMN with CURRENT_TIMESTAMP default on table with rows', true, strlen((string) $this->pdo->query('SELECT modified FROM ts_test')->fetchColumn()) === 19);
		$this->check('SHOW CREATE TABLE maps local default back to CURRENT_TIMESTAMP', 2, substr_count(WireDatabaseSQLiteTranslator::mysqlCreateTable($this->pdo, 'ts_test'), 'DEFAULT CURRENT_TIMESTAMP'));

		$this->check('DATE_ADD on DATE returns DATE', '2025-02-28', $this->value("SELECT DATE_ADD('2025-01-31', INTERVAL 1 MONTH)"));
		$this->check('DATE_SUB year from leap day clamps', '2023-02-28 10:00:00', $this->value("SELECT DATE_SUB('2024-02-29 10:00:00', INTERVAL 1 YEAR)"));
	}

	/**
	 * Behaviors that need the site's SQLite database connection (skipped when site uses MySQL)
	 *
	 */
	protected function testSiteDatabase() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'sqlite') return;
		$pdo = $database->pdo();
		$table = 'wire_test_sqlite_translator';
		$tableExists = function($name) use($pdo) {
			return (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=" . $pdo->quote($name))->fetchColumn();
		};
		$database->exec("DROP TABLE IF EXISTS `$table`");

		// prepare() of multi-statement SQL executes nothing until execute()
		$query = $database->prepare("CREATE TABLE `$table` (`id` INT UNSIGNED NOT NULL, `value` VARCHAR(20), PRIMARY KEY (`id`), KEY `value` (`value`))");
		$this->check('prepare() of CREATE TABLE with index does not create table', 0, $tableExists($table));
		$query->execute();
		$this->check('execute() creates table', 1, $tableExists($table));
		$query = $database->prepare("INSERT INTO `$table` (id, value) VALUES (1, 'line;\nbreak')");
		$this->check('prepare() of INSERT with ";\n" in a string does not insert', 0, (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn());
		$query->execute();
		$this->check('execute() inserts', 1, (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn());
		$database->exec("INSERT INTO `$table` (id, value) VALUES (2, NULL)");

		// failed rebuild is rolled back completely and leaves no open transaction/savepoint
		$failed = false;
		try {
			$database->exec("ALTER TABLE `$table` MODIFY `value` VARCHAR(20) NOT NULL"); // row 2 has NULL
		} catch(\PDOException $e) {
			$failed = true;
		}
		$this->check('rebuild with invalid data fails', true, $failed);
		$this->check('failed rebuild leaves no temporary table', 0, $tableExists("_rebuild_$table"));
		$this->check('failed rebuild keeps original data', 2, (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn());
		$this->check('failed rebuild leaves no open transaction', true, $database->beginTransaction());
		$database->rollBack();

		// rowCount() counts with the values that were executed
		$id = 1;
		$query = $database->prepare("SELECT id FROM `$table` WHERE id=:id");
		$query->bindParam(':id', $id, \PDO::PARAM_INT);
		$query->execute();
		$id = 999;
		$this->check('rowCount() uses values bound at execute()', 1, $query->rowCount());

		// prepare(sql, true) keeps the SQLite statement class
		$query = $database->prepare("SELECT id FROM `$table`", true);
		$query->execute();
		$this->check('prepare(sql, true) returns SQLite statement', true, $query instanceof WireDatabaseSQLiteStatement);
		$this->check('prepare(sql, true) rowCount() for SELECT', 2, $query->rowCount());

		// DROP TABLE while a SELECT on it is unfinished (i.e. after fetchColumn) closes the cursor and succeeds
		$query = $database->prepare("SELECT COUNT(*) FROM `$table`");
		$query->execute();
		$count = (int) $query->fetchColumn();
		$database->exec("DROP TABLE `$table`");
		$this->check('DROP TABLE succeeds with unfinished SELECT on it', [2, 0], [$count, $tableExists($table)]);

		// $config->dbFile rules
		$config = clone $this->wire()->config;
		$rejects = function($dbFile) use($config) {
			$config->dbFile = $dbFile;
			try {
				WireDatabaseDialectSQLite::connectionConfig($config, []);
			} catch(WireException $e) {
				return true;
			}
			return false;
		};
		$this->check('dbFile with ".." is rejected', true, $rejects('../site.db'));
		$this->check('dbFile in web root outside site/assets/database/ is rejected', true, $rejects($config->paths->assets . 'site.db'));
		$config->dbFile = 'x.sqlite';
		$this->check('relative dbFile is in site/assets/database/', $config->paths->assets . 'database/x.sqlite', WireDatabaseDialectSQLite::databaseFile($config));
	}

	protected function value($sql) {
		return $this->pdo->query($this->translate($sql))->fetchColumn();
	}

	protected function columnNames(\PDOStatement $statement) {
		$names = [];
		for($n = 0; $n < $statement->columnCount(); $n++) {
			$meta = $statement->getColumnMeta($n);
			$names[] = $meta['name'];
		}
		return $names;
	}

	public function finish() {
		$this->pdo = null;
		$this->translator = null;
	}
}
