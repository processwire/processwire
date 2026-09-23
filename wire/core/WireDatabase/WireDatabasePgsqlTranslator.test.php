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
