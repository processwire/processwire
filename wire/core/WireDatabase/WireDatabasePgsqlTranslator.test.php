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
		$this->check('IFNULL becomes COALESCE', 'SELECT COALESCE(a, 0)', $t('SELECT IFNULL(a, 0)'));
		$this->check('FIELD() becomes array_position with 0 for absent', 'SELECT id FROM pages ORDER BY COALESCE(array_position(ARRAY[3,1,2], pages.id), 0)', $t('SELECT id FROM pages ORDER BY FIELD(pages.id, 3,1,2)'));
		$this->check('RAND() becomes random()', 'SELECT id FROM pages ORDER BY random()', $t('SELECT id FROM pages ORDER BY RAND()'));
		$this->check('GROUP_CONCAT with ORDER BY and SEPARATOR becomes string_agg', 'SELECT string_agg(t.data::text, \'|\' ORDER BY t.sort) AS "x" FROM t', $t("SELECT GROUP_CONCAT(t.data ORDER BY t.sort SEPARATOR '|') AS `x` FROM t"));
		$this->check('GROUP_CONCAT default separator is comma', "SELECT string_agg(t.data::text, ',') FROM t", $t('SELECT GROUP_CONCAT(t.data) FROM t'));
		$this->check('GROUP_CONCAT DISTINCT', "SELECT string_agg(DISTINCT t.data::text, ',') FROM t", $t('SELECT GROUP_CONCAT(DISTINCT t.data) FROM t'));
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
