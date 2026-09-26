<?php namespace ProcessWire;

/**
 * Tests for the SQLite dialect (live checks run when the site uses sqlite)
 *
 */
class WireTest_WireDatabaseDialectSQLite extends WireTest {

	public function execute() {
		$this->testUnusedParameters();
		$this->testFulltext();
		$this->testOldSiteWithoutMarker();
		$this->testFoldIndex();
		$this->testStatistics();
	}

	/**
	 * A database without planner statistics gets them when connecting, so that SQLite uses its indexes (live, sqlite only)
	 *
	 */
	protected function testStatistics() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'sqlite') return;
		$pdo = $database->pdo();
		$pdo->exec('DROP TABLE IF EXISTS sqlite_stat1');
		$database->dialect()->initConnection($pdo);
		$has = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE name = 'sqlite_stat1'")->fetchColumn();
		$tables = $has ? $pdo->query("SELECT DISTINCT tbl FROM sqlite_stat1 WHERE tbl IN ('pages', 'field_title') ORDER BY tbl")->fetchAll(\PDO::FETCH_COLUMN) : [];
		$this->check('connecting to a database without statistics gathers them', ['field_title', 'pages'], $tables);
		$error = '';
		try {
			$database->dialect()->optimize($pdo);
		} catch(\Exception $e) {
			$error = $e->getMessage();
		}
		$this->check('optimize() (run when the connection closes) works', '', $error);
	}

	/**
	 * $config->dbOptions['sqlite']['foldIndex']: accented exact matches use an index on pw_fold() (live, sqlite only)
	 *
	 */
	protected function testFoldIndex() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'sqlite') return;
		$config = $this->wire()->config;
		$pdo = $database->pdo();
		$dialect = $database->dialect();
		$options = $config->dbOptions;
		$foldIndexes = function() use($pdo) {
			return $pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND substr(name, -6) = '__fold' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
		};
		$this->check('off by default: no fold indexes', [], $foldIndexes());

		$config->dbOptions = array_merge(is_array($options) ? $options : [], ['sqlite' => array_merge(isset($options['sqlite']) ? $options['sqlite'] : [], ['foldIndex' => true])]);
		$dialect->initConnection($pdo);
		$this->check('turned on: the translator uses them', true, $dialect->translator()->foldIndex());
		$this->check('turned on: field_title gets one', true, in_array('field_title__data_exact__fold', $foldIndexes(), true));
		$parent = $this->getTestPage();
		if($parent && $parent->id) {
			$pages = $this->wire()->pages;
			foreach($pages->find("parent=$parent, name^=sqlite-fold-, include=all") as $p) $pages->delete($p, true);
			$a = $pages->newPage(['template' => $parent->template, 'parent' => $parent, 'name' => 'sqlite-fold-a', 'title' => 'Crème brûlée']);
			$pages->save($a);
			$b = $pages->newPage(['template' => $parent->template, 'parent' => $parent, 'name' => 'sqlite-fold-b', 'title' => 'Creme Brulee']);
			$pages->save($b);
			$selector = "parent=$parent, title=crème brûlée, include=all, sort=name";
			$sql = $pages->getPageFinder()->find(new Selectors($selector), ['returnQuery' => true])->getQuery();
			$translated = implode(";\n", $dialect->translateSql($sql));
			$this->check('an accented title= compares pw_fold() values', true, strpos($translated, 'pw_fold(') !== false && stripos($translated, 'COLLATE pw_ci') === false);
			$this->check('title= finds both spellings, as MySQL', 'sqlite-fold-a|sqlite-fold-b', $pages->find($selector)->implode('|', 'name'));
			$pages->delete($a, true);
			$pages->delete($b, true);
		}

		$config->dbOptions = $options;
		$dialect->initConnection($pdo);
		$this->check('turned off again: fold indexes and marker are gone', [[], false], [$foldIndexes(), (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE name='" . WireDatabaseSQLiteTranslator::foldIndexMarker . "'")->fetchColumn()]);
		$this->check('turned off again: the translator does not use them', false, $dialect->translator()->foldIndex());
	}

	/**
	 * A value bound to a named parameter the SQL does not use fails, as on MySQL (live, sqlite only)
	 *
	 * So that SQL written against SQLite does not fail later on MySQL. (DatabaseQuery binds only the values its
	 * SQL uses, i.e. for PageFinder's count of a query with fulltext scores.)
	 *
	 */
	protected function testUnusedParameters() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'sqlite') return;
		$failed = false;
		try {
			$query = $database->prepare('SELECT :a');
			$query->bindValue(':a', 'x');
			$query->bindValue(':b', 'z');
			$query->execute();
		} catch(\PDOException $e) {
			$failed = true;
		}
		$this->check('binding an unused named parameter fails, as on MySQL', true, $failed);
		// SQL that translates to several statements does not support bound parameters: say so rather than binding NULL
		$table = WireTests::fieldPrefix . 'sqlite_params';
		$database->exec("DROP TABLE IF EXISTS `$table`");
		$error = '';
		try {
			$query = $database->prepare("CREATE TABLE `$table` (`id` int NOT NULL, `v` varchar(20), PRIMARY KEY (`id`), KEY `v` (`v`))");
			$query->bindValue(':x', 'y');
			$query->execute();
		} catch(\PDOException $e) {
			$error = $e->getMessage();
		}
		$this->check('bound parameters on SQL that translates to several statements fail', true, strpos($error, 'Bound parameters are not supported') !== false);
		$database->exec("DROP TABLE IF EXISTS `$table`");
	}

	/**
	 * FTS5 fulltext on a site created with it (live, sqlite with the pw_fulltext_v1 marker only)
	 *
	 */
	protected function testFulltext() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'sqlite') return;
		$pdo = $database->pdo();
		if(!$pdo->query("SELECT 1 FROM sqlite_master WHERE name='" . WireDatabaseSQLiteTranslator::fulltextMarker . "'")->fetchColumn()) return;
		$dialect = $database->dialect();

		$this->check('supportsFulltext() on a site created with FTS5', true, $dialect->supportsFulltext());
		$this->check('getStopwords(): none, since every word is indexed', [], $database->getStopwords());
		$this->check('field_title has an FTS5 table', true, isset(WireDatabaseSQLiteTranslator::fulltextKeys($pdo, 'field_title')['data']));
		$this->check('getTables() hides FTS5 tables and the marker', [], array_values(array_filter($database->getTables(false), function($t) {
			return strpos($t, '__fts_') !== false || $t === WireDatabaseSQLiteTranslator::fulltextMarker;
		})));
		$indexes = $database->getIndexes('field_title', true);
		$this->check('getIndexes() reports the FULLTEXT key', ['FULLTEXT', ['data']], isset($indexes['data']) ? [$indexes['data']['type'], $indexes['data']['columns']] : null);

		// through the ProcessWire API: fulltext operators take their MATCH paths
		$parent = $this->getTestPage();
		if(!$parent || !$parent->id) return;
		$pages = $this->wire()->pages;
		foreach($pages->find("parent=$parent, name^=sqlite-fts-, include=all") as $p) $pages->delete($p, true); // left by an interrupted run
		$titles = ['Hello World', 'Crème brûlée', 'Émile Zola', 'Ends with a quote "here"', 'wire_test_repeater item'];
		$created = [];
		foreach($titles as $n => $title) {
			$p = $pages->newPage(['template' => $parent->template, 'parent' => $parent, 'name' => "sqlite-fts-$n", 'title' => $title]);
			$pages->save($p);
			$created[] = $p;
		}
		$find = function($selector) use($pages, $parent) {
			return $pages->find("parent=$parent, name^=sqlite-fts-, $selector, include=all, sort=name")->implode('|', 'title');
		};
		$sql = $pages->getPageFinder()->find(new Selectors("parent=$parent, title~=zola"), ['returnQuery' => true])->getQuery();
		$this->check('title~= builds a MATCH query', true, stripos($sql, 'MATCH(') !== false);
		$this->check('title~=zola', 'Émile Zola', $find('title~=zola'));
		$this->check('title~=hello world', 'Hello World', $find('title~=hello world'));
		$this->check('title~|=hello zola', 'Hello World|Émile Zola', $find('title~|=hello zola'));
		$this->check('title~+=hello', 'Hello World', $find('title~+=hello'));
		$this->check('title~+=hello with a limit (a count query too)', 1, $pages->find("parent=$parent, name^=sqlite-fts-, title~+=hello, include=all, limit=1")->getTotal());
		$this->check('title~*=bru', 'Crème brûlée', $find('title~*=bru'));
		$this->check('title~~=zola', 'Émile Zola', $find('title~~=zola'));
		$this->check('title~|*=zol', 'Émile Zola', $find('title~|*=zol'));
		$this->check('title~|+=zola', 'Émile Zola', $find('title~|+=zola'));
		$this->check('title*=creme', 'Crème brûlée', $find('title*=creme'));
		$this->check('title*+=creme', 'Crème brûlée', $find('title*+=creme'));
		$this->check('title**=world', 'Hello World', $find('title**=world'));
		$this->check('title**+=world', 'Hello World', $find('title**+=world'));
		$this->check('title^=hello', 'Hello World', $find('title^=hello'));
		$this->check('title$=here (trailing punctuation)', 'Ends with a quote "here"', $find('title$=here'));
		$this->check('title#=+zola -hello', 'Émile Zola', $find('title#="+zola -hello"'));
		$this->check('title!~=zola', 'Hello World|Crème brûlée|Ends with a quote "here"|wire_test_repeater item', $find('title!~=zola'));
		$this->check('title~=wire (an underscore is part of a word, as MySQL)', '', $find('title~=wire'));
		$this->check('title~=wire_test_repeater', 'wire_test_repeater item', $find('title~=wire_test_repeater'));
		$this->check('title~="NEAR(" does not raise an FTS5 error', '', $find('title~="NEAR("'));
		// a save through the API keeps the FTS5 table in sync
		$created[0]->setAndSave('title', 'Goodbye World');
		$pages->uncacheAll(); // earlier finds cached their own copies of the page, with the old title
		$this->check('a renamed title is found by its new words only', ['Goodbye World', ''], [$find('title~=goodbye'), $find('title~=hello')]);

		// multi-language title, when LanguageSupport is installed
		$languages = $this->wire()->languages;
		$created[1]->of(false);
		if($languages && $languages->count() > 1 && $created[1]->title instanceof LanguagesValueInterface) {
			$lang = null;
			foreach($languages as $l) if(!$l->isDefault()) { $lang = $l; break; }
			$created[1]->title->setLanguageValue($lang, 'Gebrannte Creme');
			$pages->save($created[1]);
			$this->check('a language column has its own FTS5 table', true, isset(WireDatabaseSQLiteTranslator::fulltextKeys($pdo, 'field_title')["data$lang->id"]));
			$user = $this->wire()->user;
			$saved = $user->language;
			$user->language = $lang;
			// (titles are shown in the user's language too)
			$this->check('title~= in a language searches its column', 'Gebrannte Creme', $find('title~=gebrannte'));
			$user->language = $saved;
		}
		foreach($created as $p) $pages->delete($p, true);
	}

	/**
	 * A database without the marker (created before FTS5 support) keeps LIKE, with no errors (live, sqlite only)
	 *
	 */
	protected function testOldSiteWithoutMarker() {
		$database = $this->wire()->database;
		if($database->dialect()->name() !== 'sqlite') return;
		$pdo = $database->pdo();
		if($pdo->query("SELECT 1 FROM sqlite_master WHERE name='" . WireDatabaseSQLiteTranslator::fulltextMarker . "'")->fetchColumn()) return;
		$this->check('no marker: supportsFulltext() is false', false, $database->dialect()->supportsFulltext());
		$this->check('no marker: getStopwords() is the MySQL list', true, count($database->getStopwords()) > 100);
		$error = '';
		try {
			$this->wire()->pages->findIDs('title~=home, include=all');
		} catch(\Exception $e) {
			$error = $e->getMessage();
		}
		$this->check('no marker: title~= works (LIKE)', '', $error);
	}
}
