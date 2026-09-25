<?php namespace ProcessWire;

/**
 * Cross-database parity tests for text matching and sorting.
 *
 */
class WireTest_PageFinderParity extends WireTest {

	protected $templateName = 'wire-test-pagefinder-parity';
	protected $parentName = 'wire-test-pagefinder-parity';
	protected $userName = 'wire-test-pagefinder-parity-user';
	protected $createdTemplate = false;
	protected $addedTitleField = false;
	protected $parentPageID = 0;
	protected $userID = 0;
	protected $titles = array(
		'Hello World',
		'Äpfel',
		'Apfelkuchen',
		'Crème brûlée',
		'Zürich',
		'Émile Zola',
		'apple',
		'banana',
	);

	public function init() {
		$this->createdTemplate = false;
		$this->addedTitleField = false;
		$this->parentPageID = 0;
		$this->userID = 0;
		$this->ensureTemplate();
		$this->cleanupPages();
		$this->cleanupUser();
		$this->createPages();
		$this->createUser();
	}

	public function execute() {
		$this->testTitleMatching();
		$this->testSortOrder();
		$this->testUserEmailMatching();
		$this->testNumericMatching();
	}

	public function finish() {
		$this->cleanupUser();
		$this->cleanupPages();
		$this->cleanupTemplate();
	}

	protected function ensureTemplate() {
		$templates = $this->wire()->templates;
		$fields = $this->wire()->fields;
		$template = $templates->get($this->templateName);

		if(!$template) {
			$template = $templates->new($this->templateName);
			$template->save();
			$this->createdTemplate = true;
		} else if(!$template->fieldgroup) {
			$template->save();
		}

		$title = $fields->get('title');
		if($title && !$template->hasField($title)) {
			$template->fieldgroup->add($title);
			$template->fieldgroup->save();
			$this->addedTitleField = true;
		}
	}

	protected function createPages() {
		$pages = $this->wire()->pages;
		$parent = $pages->new(array(
			'template' => $this->templateName,
			'parent' => $this->getTestPage(),
			'name' => $this->parentName,
			'title' => 'PageFinder parity test',
			'status' => 1,
		));
		$this->parentPageID = $parent->id;

		foreach($this->titles as $sort => $title) {
			$pages->new(array(
				'template' => $this->templateName,
				'parent' => $parent,
				'name' => 'parity-' . $sort,
				'title' => $title,
				'sort' => $sort,
				'status' => 1,
			));
		}

		$pages->uncacheAll();
	}

	protected function createUser() {
		$user = $this->wire()->users->new($this->userName, array(
			'email' => 'Parity.User@Example.com',
		));
		$this->userID = $user->id;
	}

	protected function cleanupPages() {
		$pages = $this->wire()->pages;
		$parent = $pages->get("name=$this->parentName, template=$this->templateName, include=all");
		if($parent->id) $pages->delete($parent, true);
		$pages->uncacheAll();
		$this->parentPageID = 0;
	}

	protected function cleanupUser() {
		$users = $this->wire()->users;
		$user = $users->get("name=$this->userName, include=all");
		if($user->id) $users->delete($user);
		$this->userID = 0;
	}

	protected function cleanupTemplate() {
		if(!$this->createdTemplate && !$this->addedTitleField) return;

		$templates = $this->wire()->templates;
		$fields = $this->wire()->fields;
		$template = $templates->get($this->templateName);
		if(!$template) return;

		if($this->addedTitleField && !$this->createdTemplate) {
			$title = $fields->get('title');
			if($title && $template->hasField($title) && !$title->hasFlag(Field::flagGlobal)) {
				$template->fieldgroup->remove($title);
				$template->fieldgroup->save();
			}
		}

		if($this->createdTemplate) $templates->delete($template);
	}

	protected function testTitleMatching() {
		$all = $this->titles;
		$notHello = array_values(array_diff($all, array('Hello World')));
		$notApfel = array_values(array_diff($all, array('Äpfel')));
		$cases = array(
			array(
				'label' => '= same case',
				'selector' => 'title=Hello World',
				'expect' => array('Hello World'),
			),
			array(
				'label' => '= different ASCII case',
				'selector' => 'title=hello world',
				'expect' => array('Hello World'),
			),
			array(
				'label' => '= accented value in different case',
				'selector' => 'title=äpfel',
				'expect' => array('Äpfel'),
			),
			array(
				'label' => '= unaccented value against accented data',
				'selector' => 'title=apfel',
				'expect' => array('Äpfel'),
				'differs' => array(
					'sqlite' => array(array(), 'SQLite keeps indexed ASCII = comparisons on NOCASE'),
				),
			),
			array(
				'label' => '!= same case',
				'selector' => 'title!=Hello World',
				'expect' => $notHello,
			),
			array(
				'label' => '!= different ASCII case',
				'selector' => 'title!=hello world',
				'expect' => $notHello,
			),
			array(
				'label' => '!= accented value in different case',
				'selector' => 'title!=äpfel',
				'expect' => $notApfel,
			),
			array(
				'label' => '!= unaccented value against accented data',
				'selector' => 'title!=apfel',
				'expect' => $notApfel,
				'differs' => array(
					'sqlite' => array($all, 'SQLite keeps indexed ASCII != comparisons on NOCASE'),
				),
			),
			array(
				'label' => '%= different ASCII case',
				'selector' => 'title%=HELLO',
				'expect' => array('Hello World'),
			),
			array(
				'label' => '%= unaccented value',
				'selector' => 'title%=apfel',
				'expect' => array('Äpfel', 'Apfelkuchen'),
			),
			array(
				'label' => '%= accented value',
				'selector' => 'title%=äpfel',
				'expect' => array('Äpfel', 'Apfelkuchen'),
			),
			// MySQL matches ^= and $= with REGEXP, which follows the collation for case but not
			// for accents (so ^=zurich misses "Zürich" even though %=zurich finds it). Databases
			// without fulltext (SQLite, PostgreSQL) use LIKE for these operators, which folds accents.
			array(
				'label' => '^= case and accent',
				'selector' => 'title^=zurich',
				'expect' => array(),
				'differs' => array(
					'sqlite' => array(array('Zürich'), 'no fulltext on SQLite, so ^= uses LIKE, which folds accents; MySQL uses REGEXP'),
					'pgsql' => array(array('Zürich'), 'no fulltext on PostgreSQL, so ^= uses LIKE, which folds accents; MySQL uses REGEXP'),
				),
			),
			array(
				'label' => '^= second accented value',
				'selector' => 'title^=emile',
				'expect' => array(),
				'differs' => array(
					'sqlite' => array(array('Émile Zola'), 'no fulltext on SQLite, so ^= uses LIKE, which folds accents; MySQL uses REGEXP'),
					'pgsql' => array(array('Émile Zola'), 'no fulltext on PostgreSQL, so ^= uses LIKE, which folds accents; MySQL uses REGEXP'),
				),
			),
			array(
				'label' => '$= case and accent',
				'selector' => 'title$=brulee',
				'expect' => array(),
				'differs' => array(
					'sqlite' => array(array('Crème brûlée'), 'no fulltext on SQLite, so $= uses LIKE, which folds accents; MySQL uses REGEXP'),
					'pgsql' => array(array('Crème brûlée'), 'no fulltext on PostgreSQL, so $= uses LIKE, which folds accents; MySQL uses REGEXP'),
				),
			),
			array(
				'label' => '*= case and accent',
				'selector' => 'title*=creme',
				'expect' => array('Crème brûlée'),
			),
			array(
				'label' => '~= lowercase word',
				'selector' => 'title~=zola',
				'expect' => array('Émile Zola'),
			),
			array(
				'label' => '~= uppercase word',
				'selector' => 'title~=ZOLA',
				'expect' => array('Émile Zola'),
			),
		);

		foreach($cases as $case) $this->runTitleCase($case);
	}

	protected function runTitleCase(array $case) {
		$dialect = $this->wire()->database->dialect()->name();
		$expect = $case['expect'];
		$reason = '';
		if(isset($case['differs'][$dialect])) {
			$expect = $case['differs'][$dialect][0];
			$reason = ' (' . $case['differs'][$dialect][1] . ')';
		}
		$actual = $this->findTitles($case['selector'], 'sort');
		$this->check($case['label'] . $reason, $expect, $actual);
	}

	protected function testSortOrder() {
		$mysqlAscending = array(
			'Äpfel',
			'Apfelkuchen',
			'apple',
			'banana',
			'Crème brûlée',
			'Émile Zola',
			'Hello World',
			'Zürich',
		);
		$mysqlDescending = array_reverse($mysqlAscending);
		$sqliteAscending = array(
			'Apfelkuchen',
			'apple',
			'banana',
			'Crème brûlée',
			'Hello World',
			'Zürich',
			'Äpfel',
			'Émile Zola',
		);
		$dialect = $this->wire()->database->dialect()->name();
		$ascending = $this->findTitles('', 'title');
		$descending = $this->findTitles('', '-title');

		// PostgreSQL sorts text by its folded value (pw_fold), so these titles order as on MySQL
		// whatever the database collation: their folded forms are plain lowercase ASCII.
		$expectAscending = $mysqlAscending;
		if($dialect === 'sqlite' && !$this->sqliteUnicodeSort()) $expectAscending = $sqliteAscending;
		$this->check('sort=title follows declared database ordering', $expectAscending, $ascending);
		$this->check('sort=-title reverses declared database ordering', array_reverse($expectAscending), $descending);
	}

	protected function testUserEmailMatching() {
		$dialect = $this->wire()->database->dialect()->name();
		$users = $this->wire()->users;
		$parentID = (int) $this->wire()->config->usersPageID;
		$cases = array(
			array(
				'label' => 'user email original case',
				'selector' => "parent=$parentID, email=Parity.User@Example.com",
				'expect' => $this->userID,
			),
			array(
				'label' => 'user email lowercase',
				'selector' => "parent=$parentID, email=parity.user@example.com",
				'expect' => $this->userID,
			),
		);

		foreach($cases as $case) {
			$expect = $case['expect'];
			$reason = '';
			if(isset($case['differs'][$dialect])) {
				$expect = $case['differs'][$dialect][0];
				$reason = ' (' . $case['differs'][$dialect][1] . ')';
			}
			$user = $users->get($case['selector']);
			$this->check($case['label'] . $reason, $expect, (int) $user->id);
		}
	}

	protected function testNumericMatching() {
		$pageID = $this->wire()->pages->get("parent=$this->parentPageID, name=parity-3, include=all")->id;
		$actual = $this->findTitles("id=$pageID", 'sort');
		$this->check('native numeric id comparison remains exact', array('Crème brûlée'), $actual);
	}

	protected function findTitles($selector, $sort) {
		$selector = trim($selector);
		if(strlen($selector)) $selector .= ', ';
		$selector = "parent=$this->parentPageID, " . $selector . "sort=$sort, include=all";
		$titles = array();
		foreach($this->wire()->pages->find($selector) as $page) {
			$titles[] = (string) $page->title;
		}
		return $titles;
	}

	protected function sqliteUnicodeSort() {
		$options = $this->wire()->config->dbOptions;
		return is_array($options) && !empty($options['sqlite']['unicodeSort']);
	}
}
