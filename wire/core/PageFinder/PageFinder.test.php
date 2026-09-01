<?php namespace ProcessWire;

/**
 * Tests for ProcessWire PageFinder.
 *
 */
class WireTest_PageFinder extends WireTest {

	protected $childTemplateName = 'wire-test-pagefinder';
	protected $createdTemplate = false;
	protected $addedTitleField = false;
	protected $createdPageIDs = array();

	public function init() {
		$this->createdTemplate = false;
		$this->addedTitleField = false;
		$this->createdPageIDs = array();
		$this->ensureChildTemplate();
		$this->cleanupPages();
		$this->createChildPages();
	}

	public function execute() {
		$this->testBasicFindReturnModes();
		$this->testVerboseIDsAndMetadata();
		$this->testSelectorsOptionsAndQuery();
		$this->testIncludeAndAccessModes();
		$this->testCursorAndReverseOptions();
		$this->testExceptionsAndTiming();
		$this->testStrictSqlModes();
	}

	public function finish() {
		$this->cleanupPages();
		$this->cleanupTemplate();
	}

	/**
	 * Core find queries must work under the MySQL 5.7+ default SQL modes
	 * 
	 * ProcessWire used to switch ONLY_FULL_GROUP_BY and STRICT_TRANS_TABLES off in
	 * $config->dbSqlModes. It no longer does, so the queries built here have to be valid with
	 * both enabled. Note that MariaDB does not infer functional dependency on a grouped primary
	 * key the way MySQL 5.7+ does, so grouped queries must aggregate every other column.
	 *
	 */
	protected function testStrictSqlModes() {

		$database = $this->wire()->database;
		$pages = $this->wire()->pages;
		$parent = $this->getTestPage();
		$originalMode = $database->sqlMode();

		try {
			$database->sqlMode('add', 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES');
			$mode = $database->sqlMode();

			if(strpos($mode, 'ONLY_FULL_GROUP_BY') === false) {
				$this->check('ONLY_FULL_GROUP_BY can be enabled for this test', true, false);
				return;
			}

			$this->check('ONLY_FULL_GROUP_BY is active for this test', true, true);

			$sel = "parent=$parent, include=hidden";
			$finder = $this->finder();

			// sorting by a custom field requires a joined table, which is not functionally
			// dependent on the grouped pages.id and so must be aggregated
			$ids = $pages->findIDs("$sel, sort=title");
			$this->check('sort by custom field returns all test pages', 3, count($ids));

			$titles = array();
			foreach($pages->find("$sel, sort=title") as $p) $titles[] = $p->title;
			$this->check('sort by custom field is ascending',
				array('PageFinder Test Alpha', 'PageFinder Test Bravo', 'PageFinder Test Charlie'), $titles);

			$titles = array();
			foreach($pages->find("$sel, sort=-title") as $p) $titles[] = $p->title;
			$this->check('sort by custom field is descending',
				array('PageFinder Test Charlie', 'PageFinder Test Bravo', 'PageFinder Test Alpha'), $titles);

			// sorting by native columns, which are functionally dependent on the grouped column
			$this->check('sort by native name works', 3, count($pages->findIDs("$sel, sort=name")));
			$this->check('sort by native sort works', 3, count($pages->findIDs("$sel, sort=-sort")));
			$this->check('sort by parent name works', 3, count($pages->findIDs("$sel, sort=parent.name")));
			$this->check('sort random works', 3, count($pages->findIDs("$sel, sort=random")));

			// pages load through a grouped autojoin query
			$page = $pages->get(reset($ids));
			$this->check('page loaded under strict modes has its title', true, strlen($page->title) > 0);
			$this->check('page loaded under strict modes has its parent', $parent->id, $page->parent_id);

			// returnAllCols expands pages.* into aggregated columns
			$rows = $finder->findVerboseIDs($sel, array(
				'joinSortfield' => true,
				'getNumChildren' => true,
				'joinFields' => array('title'),
			));
			$this->check('findVerboseIDs() returns all test pages', 3, count($rows));
			$row = reset($rows);
			$this->check('findVerboseIDs() row keeps id column', true, isset($row['id']));
			$this->check('findVerboseIDs() row keeps name column', true, isset($row['name']));
			$this->check('findVerboseIDs() row keeps parent_id column', $parent->id, (int) $row['parent_id']);
			$this->check('findVerboseIDs() row includes numChildren', true, array_key_exists('numChildren', $row));
			$this->check('findVerboseIDs() row includes joined field', true, array_key_exists('title__data', $row));

			$rows = $finder->findVerboseIDs($sel, array('unixTimestamps' => true));
			$row = reset($rows);
			$this->check('findVerboseIDs() returns unix timestamps', true, ctype_digit((string) $row['created']));

			// other return modes, each with their own column set
			$this->check('findParentIDs() works', array($parent->id), $finder->findParentIDs($sel));
			$this->check('findTemplateIDs() works', 3, count($finder->findTemplateIDs($sel)));
			$this->check('count() works', 3, $finder->count($sel));

			// selectors that add aggregate joins
			$this->check('num_children selector works', 0, count($pages->findIDs("$sel, num_children>0")));
			$this->check('children.count selector works', 0, count($pages->findIDs("$sel, children.count>0")));
			$this->check('parent num_children selector works', 1, count($pages->findIDs("id=$parent, include=all, num_children>0")));

			// getTotal via both SQL_CALC_FOUND_ROWS and a separate count query
			$this->check('getTotal by count works', 3, $pages->find("$sel, limit=2")->getTotal());
			$this->check('getTotal by calc works', 3, $pages->find("$sel, limit=2, getTotal=calc")->getTotal());

		} catch(\Exception $e) {
			$this->check('find queries run under strict SQL modes: ' . $e->getMessage(), true, false);
		} finally {
			$database->sqlMode('set', $originalMode);
		}

		$this->check('SQL mode restored after test', $originalMode, $database->sqlMode());
	}

	protected function finder() {
		return $this->wire(new PageFinder());
	}

	protected function ensureChildTemplate() {
		$templates = $this->wire()->templates;
		$fields = $this->wire()->fields;
		$template = $templates->get($this->childTemplateName);

		if(!$template) {
			$template = $templates->new($this->childTemplateName);
			$template->save();
			$this->createdTemplate = true;
			$this->li("Created template: $this->childTemplateName");
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

	protected function createChildPages() {
		$pages = $this->wire()->pages;
		$parent = $this->getTestPage();
		$titles = array(
			'a' => 'PageFinder Test Alpha',
			'b' => 'PageFinder Test Bravo',
			'c' => 'PageFinder Test Charlie',
		);
		$sort = 0;

		foreach($titles as $suffix => $title) {
			$page = $pages->new(array(
				'template' => $this->childTemplateName,
				'parent' => $parent,
				'name' => "pagefinder-test-$suffix",
				'title' => $title,
				'sort' => $sort++,
				'status' => 1,
			));
			$this->createdPageIDs[$page->id] = $page->id;
		}

		$pages->uncacheAll();
	}

	protected function cleanupPages() {
		$pages = $this->wire()->pages;
		$items = $pages->find("template=$this->childTemplateName, name^=pagefinder-test-, include=all");
		foreach($items as $page) {
			$pages->delete($page, true);
		}
		$pages->uncacheAll();
	}

	protected function cleanupTemplate() {
		if(!$this->createdTemplate && !$this->addedTitleField) return;

		$templates = $this->wire()->templates;
		$fields = $this->wire()->fields;
		$template = $templates->get($this->childTemplateName);
		if(!$template) return;

		if($this->addedTitleField && !$this->createdTemplate) {
			$title = $fields->get('title');
			if($title && $template->hasField($title) && !$title->hasFlag(Field::flagGlobal)) {
				$template->fieldgroup->remove($title);
				$template->fieldgroup->save();
			}
		}

		if($this->createdTemplate) {
			$templates->delete($template);
		}
	}

	protected function childIDs($sort = 'sort') {
		$finder = $this->finder();
		$parent = $this->getTestPage();
		return $finder->findIDs("parent=$parent->id, template=$this->childTemplateName, sort=$sort, include=all");
	}

	protected function testBasicFindReturnModes() {
		$page = $this->getTestPage();
		$finder = $this->finder();

		$rows = $finder->find("id=$page->id, include=hidden");
		$this->check('find() returns one verbose row for test page', 1, count($rows));
		$row = reset($rows);
		$this->check('find() verbose row includes id', $page->id, (int) $row['id']);
		$this->check('find() verbose row includes parent_id', $page->parent_id, (int) $row['parent_id']);
		$this->check('find() verbose row includes templates_id', $page->templates_id, (int) $row['templates_id']);
		$this->check('find() verbose row includes score', true, array_key_exists('score', $row));

		$ids = $finder->findIDs("id=$page->id, include=hidden");
		$this->check('findIDs() returns simple ID array', array($page->id), $ids);

		$templateIDs = $finder->findTemplateIDs("id=$page->id, include=hidden");
		$this->check('findTemplateIDs() returns pageID => templateID', array($page->id => $page->templates_id), $templateIDs);

		$parentIDs = $finder->findParentIDs("id=$page->id, include=hidden");
		$this->check('findParentIDs() returns matching parent ID', array($page->parent_id), $parentIDs);

		$this->check('count() returns 1 for exact page ID', 1, $finder->count("id=$page->id, include=hidden"));
		$this->check('count() returns 0 for no match', 0, $finder->count('name=pagefinder-no-such-page'));
	}

	protected function testVerboseIDsAndMetadata() {
		$page = $this->getTestPage();
		$finder = $this->finder();
		$rows = $finder->findVerboseIDs("id=$page->id, include=hidden", array(
			'unixTimestamps' => true,
			'getNumChildren' => true,
			'joinFields' => array('title'),
		));

		$this->check('findVerboseIDs() indexes rows by page ID', true, isset($rows[$page->id]));
		$row = $rows[$page->id];
		$this->check('findVerboseIDs() includes name column', $page->name, $row['name']);
		$this->check('findVerboseIDs() includes status column', true, isset($row['status']));
		$this->check('findVerboseIDs() unixTimestamps returns integer created', true, is_int($row['created']));
		$this->check('findVerboseIDs() getNumChildren includes numChildren', true, isset($row['numChildren']) && is_numeric($row['numChildren']));

		$data = $finder->getPageArrayData();
		$this->check('getPageArrayData() returns array after verbose find', true, is_array($data));

		$pageArray = $this->wire(new PageArray());
		$finder->getPageArrayData($pageArray);
		$this->check('getPageArrayData(PageArray) accepts PageArray argument', true, $pageArray instanceof PageArray);
	}

	protected function testSelectorsOptionsAndQuery() {
		$page = $this->getTestPage();
		$finder = $this->finder();
		// constrained by id because parent+template alone does not uniquely identify the test
		// page, which made the limit=1 result depend on what else exists in the installation
		$selectors = $this->wire(new Selectors("id=$page->id, parent=$page->parent_id, template=$page->templates_id, include=hidden, limit=1, start=0"));
		$ids = $finder->findIDs($selectors, array('getTotalType' => 'count'));

		$this->check('findIDs() accepts Selectors object', array($page->id), $ids);
		$this->check('getLimit() returns last limit', 1, $finder->getLimit());
		$this->check('getStart() returns last start', 0, $finder->getStart());
		$this->check('getParentID() returns selector parent', (int) $page->parent_id, (int) $finder->getParentID());
		$this->check('getTemplatesID() returns selector template ID', (int) $page->templates_id, (int) $finder->getTemplatesID());
		$this->check('includeMode property records include selector', 'hidden', $finder->includeMode);
		$this->check('checkAccess remains enabled for include=hidden', true, $finder->checkAccess);
		$this->check('getSelectors() returns final Selectors object', true, $finder->getSelectors() instanceof Selectors);
		$this->check('getOptions() records returnVerbose false from findIDs()', false, $finder->getOptions()['returnVerbose']);
		$this->check('getTotal() is at least limited result count', true, $finder->getTotal() >= count($ids));

		$query = $finder->find("id=$page->id, include=hidden", array('returnQuery' => true));
		$this->check('returnQuery returns DatabaseQuerySelect', true, $query instanceof DatabaseQuerySelect);
		$this->check('returnQuery SQL selects from pages table', 'FROM `pages`', $query->getQuery(), '*=');

		$arrayIDs = $finder->findIDs(array('id' => $page->id, 'include' => 'hidden'));
		$this->check('findIDs() accepts selector array', array($page->id), $arrayIDs);
	}

	protected function testIncludeAndAccessModes() {
		$page = $this->getTestPage();
		$finder = $this->finder();

		$withoutHidden = $finder->findIDs("id=$page->id");
		$this->check('hidden test page is excluded by default', array(), $withoutHidden);

		$withHidden = $finder->findIDs("id=$page->id, include=hidden");
		$this->check('include=hidden includes hidden test page', array($page->id), $withHidden);
		$this->check('includeMode is hidden after include=hidden', 'hidden', $finder->includeMode);
		$this->check('checkAccess remains true after include=hidden', true, $finder->checkAccess);

		$all = $finder->findIDs("id=$page->id, include=all");
		$this->check('include=all includes hidden test page', array($page->id), $all);
		$this->check('includeMode is all after include=all', 'all', $finder->includeMode);
		$this->check('include=all disables access checks by default', false, $finder->checkAccess);

		$checkAccess = $finder->findIDs("id=$page->id, include=hidden, check_access=0");
		$this->check('check_access=0 still finds requested page', array($page->id), $checkAccess);
		$this->check('check_access=0 disables access checks', false, $finder->checkAccess);

		$finder = $this->finder();
		$allowed = $finder->findIDs("id=$page->id", array('alwaysAllowIDs' => array($page->id)));
		$this->check('alwaysAllowIDs includes otherwise hidden page', array($page->id), $allowed);
	}

	protected function testCursorAndReverseOptions() {
		$ids = $this->childIDs('sort');
		$this->check('fixture created three ordered child pages', 3, count($ids));

		$finder = $this->finder();
		$parent = $this->getTestPage();
		$selector = "parent=$parent->id, template=$this->childTemplateName, sort=sort, include=all";

		$after = $finder->findIDs($selector, array('startAfterID' => $ids[0]));
		$this->check('startAfterID excludes first ID and returns following IDs', array_slice($ids, 1), $after);

		$before = $finder->findIDs($selector, array('stopBeforeID' => $ids[2]));
		$this->check('stopBeforeID excludes stop ID and later IDs', array_slice($ids, 0, 2), $before);

		$reverse = $finder->findIDs($selector, array('reverseSort' => true));
		$this->check('reverseSort reverses selector order', array_reverse($ids), $reverse);

		$one = $finder->findIDs($selector, array('findOne' => true));
		$this->check('findOne option returns one ID', 1, count($one));
		$this->check('findOne option returns first matching ID', $ids[0], reset($one));
	}

	protected function testExceptionsAndTiming() {
		$finder = $this->finder();
		try {
			$finder->findIDs('include=banana');
			$this->fail('Invalid include mode should throw PageFinderSyntaxException');
		} catch(PageFinderSyntaxException $e) {
			$this->ok('Invalid include mode throws PageFinderSyntaxException');
		}

		try {
			$finder->syntaxError('WireTest syntax error');
			$this->fail('syntaxError() should throw PageFinderSyntaxException');
		} catch(PageFinderSyntaxException $e) {
			$this->check('syntaxError() preserves message', 'WireTest syntax error', $e->getMessage());
		}

		$before = PageFinder::getTotalTime();
		$finder->findIDs('template=' . $this->childTemplateName . ', include=all, limit=1', array('testMode' => true));
		$after = PageFinder::getTotalTime();
		$this->check('testMode accumulates non-decreasing total time', true, $after >= $before);
	}
}
