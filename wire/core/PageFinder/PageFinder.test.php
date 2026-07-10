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
	}

	public function finish() {
		$this->cleanupPages();
		$this->cleanupTemplate();
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
		$selectors = $this->wire(new Selectors("parent=$page->parent_id, template=$page->templates_id, include=hidden, limit=1, start=0"));
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
