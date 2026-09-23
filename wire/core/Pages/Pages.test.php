<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $pages API variable
 *
 */
class WireTest_Pages extends WireTest {

	protected $childTemplateName = 'pages-test-child';
	protected $createdTemplate = false;
	protected $addedTitleField = false;
	protected $createdPageIDs = array();

	public function init() {
		$templates = $this->wire()->templates;
		$fields = $this->wire()->fields;
		$childTemplate = $templates->get($this->childTemplateName);

		$this->createdTemplate = false;
		$this->addedTitleField = false;
		$this->createdPageIDs = array();

		if(!$childTemplate) {
			$childTemplate = $templates->new($this->childTemplateName);
			$childTemplate->save();
			$this->createdTemplate = true;
			$this->li("Created template: $this->childTemplateName");
		}

		if(!$childTemplate->fieldgroup) $childTemplate->save();

		$titleField = $fields->get('title');
		if($titleField && !$childTemplate->hasField($titleField)) {
			$childTemplate->fieldgroup->add($titleField);
			$childTemplate->fieldgroup->save();
			$this->addedTitleField = true;
		}

		$this->cleanupTestPages();
	}

	public function execute() {
		$this->testFindingPages();
		$this->testCreatingInstances();
		$this->testPageNameFormats();
		$this->testPageNameConflicts();
		$this->testCreatingSavingSortingAndDeletingPages();
		$this->testSortRebuild();
	}

	/**
	 * sortRebuild() must renumber children 0..n-1 in their existing order, removing gaps and duplicates
	 *
	 */
	protected function testSortRebuild() {
		$pages = $this->wire()->pages;
		$database = $this->wire()->database;
		$page = $this->getTestPage();
		$children = array();

		foreach(array('a', 'b', 'c') as $n) {
			$child = $pages->add($this->childTemplateName, $page, array(
				'name' => "pages-test-sort-$n",
				'title' => "Pages Test Sort $n",
				'status' => Page::statusHidden,
			));
			$this->createdPageIDs[$child->id] = $child->id;
			$children[$n] = $child;
		}

		// give the children gapped and duplicated sort values, bypassing the API
		$query = $database->prepare('UPDATE pages SET sort=:sort WHERE id=:id');
		foreach(array('a' => 9, 'b' => 5, 'c' => 5) as $n => $sort) {
			$query->bindValue(':sort', $sort, \PDO::PARAM_INT);
			$query->bindValue(':id', $children[$n]->id, \PDO::PARAM_INT);
			$query->execute();
		}
		$pages->uncacheAll();

		$page->of(false);
		$originalSortfield = $page->sortfield;
		$page->sortfield = 'sort';
		$page->save(array('quiet' => true));

		// the test page may have other children from other tests, so checks are relative to all of them
		$numChildren = count($this->getTestChildSorts());
		$qty = $pages->sort($page, true);
		$this->check('sort(true) reports number of children renumbered', $numChildren, $qty);

		$sorts = $this->getTestChildSorts();
		$this->check('sortRebuild() renumbers children from zero without gaps', range(0, $numChildren - 1), array_values($sorts));
		$this->check('sortRebuild() keeps b (sort 5) before a (sort 9)', true, $sorts[$children['b']->id] < $sorts[$children['a']->id]);
		$this->check('sortRebuild() keeps c (sort 5) before a (sort 9)', true, $sorts[$children['c']->id] < $sorts[$children['a']->id]);
		$this->check('sortRebuild() leaves child names intact', 'pages-test-sort-a', $pages->getFresh($children['a']->id)->name);

		// parent sorted by something other than “sort”: sort values follow that order instead
		$page->sortfield = '-name';
		$page->save(array('quiet' => true));
		$qty = $pages->sort($page, true);
		$this->check('sort(true) with non-sort parent sortfield reports number of children', $numChildren, $qty);
		$sorts = $this->getTestChildSorts();
		$this->check('sortRebuild() with parent sortfield -name puts c before b', true, $sorts[$children['c']->id] < $sorts[$children['b']->id]);
		$this->check('sortRebuild() with parent sortfield -name puts b before a', true, $sorts[$children['b']->id] < $sorts[$children['a']->id]);
		$this->check('sortRebuild() with non-sort parent sortfield renumbers from zero', range(0, $numChildren - 1), array_values($sorts));

		$page->sortfield = $originalSortfield;
		$page->save(array('quiet' => true));

		foreach($children as $child) {
			$id = $child->id;
			$pages->delete($child);
			unset($this->createdPageIDs[$id]);
		}
	}

	/**
	 * Get [ id => sort ] for all children of the test page, ordered by sort
	 *
	 * @return array
	 *
	 */
	protected function getTestChildSorts() {
		$database = $this->wire()->database;
		$page = $this->getTestPage();
		$query = $database->prepare('SELECT id, sort FROM pages WHERE parent_id=:parent_id ORDER BY sort, id');
		$query->bindValue(':parent_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$sorts = array();
		foreach($query->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$sorts[(int) $row['id']] = (int) $row['sort'];
		}
		return $sorts;
	}

	protected function testPageNameConflicts() {
		$pages = $this->wire()->pages;
		$names = $pages->names();
		$database = $this->wire()->database;
		$parent = $this->getTestPage();
		$name = 'pages-test-name-conflict';

		for($i = 0; $i < 20; $i++) {
			$page = $pages->add($this->childTemplateName, $parent, array(
				'name' => $name . ($i ? "-$i" : ''),
				'title' => 'Name conflict fixture',
			));
			$this->createdPageIDs[$page->id] = $page->id;
		}

		$page = $pages->newPage(array('template' => $this->childTemplateName, 'parent' => $parent, 'name' => $name));
		$debugMode = $database->debugMode;
		try {
			$database->queryLog(true);
			$page->save();
			$this->createdPageIDs[$page->id] = $page->id;
			$queries = array_filter($database->queryLog(), function($query) {
				return strpos($query, 'SELECT id, status, parent_id FROM pages WHERE name=') !== false;
			});
		} finally {
			$database->queryLog($debugMode ? 1 : false);
		}
		$this->check('Saving an explicit name avoids scanning every conflicting sibling', true, count($queries) <= 8);
		$this->check('Saving an explicit name falls back before the next sequential suffix', false, $page->name === "$name-20");
		$this->check('Random fallback is conflict-free', false, $names->pageNameHasConflict($page));
		$this->check('Random fallback survives reload', $page->name, $pages->getFresh($page->id)->name);

		$probe = $pages->newPage(array('template' => $this->childTemplateName, 'parent' => $parent, 'name' => "$name-19"));
		$names->checkNameConflicts($probe);
		$this->check('A small number of conflicts still increments an existing numeric suffix', "$name-20", $probe->name);

		$previousName = $page->name;
		$page->name = $name;
		$names->checkNameConflicts($page);
		$this->check('Conflicting rename restores the previous name', $previousName, $page->name);
	}

	public function finish() {
		$this->cleanupTestPages();
		$this->cleanupTemplate();
	}

	protected function testFindingPages() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		$this->check('get() by ID returns correct page', $page->id, $pages->get($page->id)->id);
		$this->check('get() by path returns correct page', $page->id, $pages->get($page->path)->id);

		$bySelector = $pages->get("name=$page->name, template={$page->template->name}");
		$this->check('get() by selector returns correct page', $page->id, $bySelector->id);

		$noMatch = $pages->get('name=nonexistent-page-xyz-12345');
		$this->check('get() returns NullPage when not found', 0, $noMatch->id);
		$this->check('get() not-found result is NullPage instance', true, $noMatch instanceof NullPage);

		$found = $pages->findOne("id=$page->id, include=hidden");
		$this->check('findOne() returns correct page', $page->id, $found->id);

		$notFound = $pages->findOne('name=nonexistent-page-xyz-12345');
		$this->check('findOne() returns NullPage when not found', 0, $notFound->id);

		$results = $pages->find("id=$page->id, include=hidden");
		$this->check('find() returns PageArray', true, $results instanceof PageArray);
		$this->check('find() PageArray contains the test page', true, $results->has($page));

		$n = $pages->count("id=$page->id, include=hidden");
		$this->check('count() returns int', true, is_int($n));
		$this->check('count() finds 1 for known page ID', 1, $n);
		$this->check('count() returns 0 for no match', 0, $pages->count('name=nonexistent-page-xyz-12345'));

		$this->check('has() returns page ID when found', $page->id, $pages->has("id=$page->id, include=hidden"));
		$this->check('has() returns 0 when not found', 0, $pages->has('name=nonexistent-page-xyz-12345'));

		$ids = $pages->findIDs("id=$page->id, include=hidden");
		$this->check('findIDs() returns array', true, is_array($ids));
		$this->check('findIDs() contains page ID', true, in_array($page->id, $ids));

		$idsVerbose = $pages->findIDs("id=$page->id, include=hidden", true);
		$this->check('findIDs(verbose=true) returns nested array', true, is_array($idsVerbose));
		$firstVerbose = reset($idsVerbose);
		$this->check("findIDs(verbose=true) has 'id' key", true, isset($firstVerbose['id']));
		$this->check("findIDs(verbose=true) has 'templates_id' key", true, isset($firstVerbose['templates_id']));

		$rawScalar = $pages->getRaw("id=$page->id", 'name');
		$this->check("getRaw(selector, 'field') returns scalar value", $page->name, $rawScalar);

		$rawArray = $pages->getRaw("id=$page->id", array('name'));
		$this->check("getRaw(selector, ['field']) returns array", true, is_array($rawArray));
		$this->check("getRaw() array has 'name' key", true, isset($rawArray['name']));
		$this->check("getRaw() 'name' matches page name", $page->name, $rawArray['name']);

		$rawResults = $pages->findRaw("id=$page->id, include=hidden", array('name'));
		$this->check('findRaw() returns array indexed by page ID', true, isset($rawResults[$page->id]));
		$this->check("findRaw() value has 'name' key", true, isset($rawResults[$page->id]['name']));
		$this->check("findRaw() 'name' matches page name", $page->name, $rawResults[$page->id]['name']);

		$rawResults = $pages->findRaw("id=$page->id, include=hidden", array(
			'title' => 'renamed',
			'parent.title' => 'parentTitle',
		), array('flat' => true));
		$rawRow = $rawResults[$page->id];
		// cast to string since findRaw() returns raw strings, while $page->title is a
		// LanguagesPageFieldValue object when multi-language support is installed
		$this->check('findRaw(flat) applies root field rename', (string) $page->title, $rawRow['renamed']);
		$this->check('findRaw(flat) applies path-specific field rename', (string) $page->parent->title, $rawRow['parentTitle']);
		$this->check('findRaw(flat) path-specific rename replaces original name', false, isset($rawRow['parent.title']));

		$fresh = $pages->getFresh($page->id);
		$this->check('getFresh(id) returns correct page', $page->id, $fresh->id);
		$fresh2 = $pages->getFresh($page);
		$this->check('getFresh(Page) returns correct page', $page->id, $fresh2->id);
	}

	protected function testCreatingInstances() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		$unsaved = $pages->newPage();
		$this->check('newPage() returns Page instance', true, $unsaved instanceof Page);
		$this->check('newPage() has id=0 (unsaved)', 0, $unsaved->id);

		$unsavedWithTemplate = $pages->newPage(array('template' => $this->childTemplateName, 'parent' => $page));
		$this->check("newPage(['template']) returns Page with template set", $this->childTemplateName, $unsavedWithTemplate->template->name);
		$this->check("newPage(['parent']) returns Page with parent set", $page->id, $unsavedWithTemplate->parent->id);

		$pa = $pages->newPageArray();
		$this->check('newPageArray() returns PageArray', true, $pa instanceof PageArray);
		$this->check('newPageArray() is empty', 0, $pa->count());

		$null1 = $pages->newNullPage();
		$null2 = $pages->newNullPage();
		$this->check('newNullPage() returns NullPage', true, $null1 instanceof NullPage);
		$this->check('newNullPage() id=0', 0, $null1->id);
		$this->check('newNullPage() returns a new instance', true, $null1 !== $null2);
		$this->check('newNullPage(true) returns a new instance', true, $pages->newNullPage(true) !== $null1);
	}

	protected function testPageNameFormats() {
		$page = $this->wire()->pages->newPage();
		$names = $this->wire()->pages->names();

		$page->title = 'Test: me';
		$this->check('pageNameFromFormat() beautifies adjacent title separators', 'test-me', $names->pageNameFromFormat($page, 'title'));

		$page->title = 'National Academy of Science and Technology - The Philippines';
		$this->check('pageNameFromFormat() beautifies existing title separators', 'national-academy-of-science-and-technology-the-philippines', $names->pageNameFromFormat($page, 'title'));

		$this->check('pageNameFromFormat() supports reversed date order', strtolower(wireDate('F-Y')), $names->pageNameFromFormat($page, 'F/Y'));
		$this->check('pageNameFromFormat() normalizes date punctuation', strtolower(wireDate('F-Y')), $names->pageNameFromFormat($page, 'F, Y'));
		$this->check('pageNameFromFormat() supports explicit date punctuation', strtolower(wireDate('F-Y')), $names->pageNameFromFormat($page, 'date:F, Y'));
	}

	protected function testCreatingSavingSortingAndDeletingPages() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		$child1 = $pages->add($this->childTemplateName, $page, array(
			'name' => 'pages-test-child-a',
			'title' => 'Pages Test Child A',
			'status' => Page::statusHidden,
		));
		$this->createdPageIDs[$child1->id] = $child1->id;
		$this->check('add() returns Page with id > 0', true, $child1->id > 0);
		$this->check('add() page has correct template', $this->childTemplateName, $child1->template->name);
		$this->check('add() page has correct parent', $page->id, $child1->parent->id);
		$this->check('add() page has correct name', 'pages-test-child-a', $child1->name);
		$this->check('add() page persists to DB', $child1->id, $pages->getFresh($child1->id)->id);

		$child2 = $pages->new(array(
			'template' => $this->childTemplateName,
			'parent' => $page,
			'name' => 'pages-test-child-b',
			'title' => 'Pages Test Child B',
			'status' => Page::statusHidden,
		));
		$this->createdPageIDs[$child2->id] = $child2->id;
		$this->check('new(array) returns saved Page', true, $child2->id > 0);
		$this->check('new(array) page has correct name', 'pages-test-child-b', $child2->name);

		$child1->of(false);
		$child1->title = 'Pages Test Child A - Updated';
		$pages->save($child1);
		$this->check('save() persists title change', 'Pages Test Child A - Updated', $pages->getFresh($child1->id)->getFormatted('title'));

		$child1->of(false);
		$child1->title = 'Pages Test Child A - saveField';
		$pages->saveField($child1, 'title');
		$this->check('saveField() persists single field', 'Pages Test Child A - saveField', $pages->getFresh($child1->id)->getFormatted('title'));

		$child1->of(false);
		$child1->title = 'Pages Test Child A - saveFields';
		$pages->saveFields($child1, 'title');
		$this->check('saveFields() persists via CSV string', 'Pages Test Child A - saveFields', $pages->getFresh($child1->id)->getFormatted('title'));

		$child1->of(false);
		$child1->title = 'Pages Test Child A - saveFields array';
		$pages->saveFields($child1, array('title'));
		$this->check('saveFields() persists via array', 'Pages Test Child A - saveFields array', $pages->getFresh($child1->id)->getFormatted('title'));

		$beforeModified = $pages->getFresh($child1->id)->modified;
		$pages->touch($child1);
		$this->check('touch() updated or preserved modified timestamp', true, $pages->getFresh($child1->id)->modified >= $beforeModified);

		$child1 = $pages->getFresh($child1->id);
		$cloned = $pages->clone($child1, null, false, array(
			'set' => array('status' => $child1->status | Page::statusUnpublished),
		));
		$this->createdPageIDs[$cloned->id] = $cloned->id;
		$this->check('clone() returns a Page with new id', true, $cloned->id > 0 && $cloned->id !== $child1->id);
		$this->check('clone() page has same parent', $child1->parent->id, $cloned->parent->id);
		$this->check('clone() page has same template', $child1->template->name, $cloned->template->name);
		$this->check('clone() unpublished page has published=0', 0, $cloned->published);
		$this->check('clone() page has no previous name', '', (string) $cloned->namePrevious);
		$this->check('clone() unpublished timestamp matches database', $pages->getFresh($cloned->id)->published, $cloned->published);

		$pages->uncache($child1);
		$pages->uncache(array($child1->id, $child2->id));
		$pages->uncacheAll();
		$this->check('get() still works after uncacheAll()', $child1->id, $pages->get($child1->id)->id);

		$pages->sort($child1, 0);
		$orderedIDs = $this->getTestChildIDs();
		$this->check('sort() moves page to requested first position', $child1->id, reset($orderedIDs));
		$pages->uncacheAll();
		$child1 = $pages->getFresh($child1->id);
		$child2 = $pages->getFresh($child2->id);

		$pages->insertAfter($child1, $child2);
		$orderedIDs = $this->getTestChildIDs();
		$this->check('insertAfter() places page immediately after sibling', array($child2->id, $child1->id), array_values(array_intersect($orderedIDs, array($child1->id, $child2->id))));
		$pages->uncacheAll();
		$child1 = $pages->getFresh($child1->id);
		$child2 = $pages->getFresh($child2->id);

		$pages->insertBefore($child1, $child2);
		$orderedIDs = $this->getTestChildIDs();
		$this->check('insertBefore() places page immediately before sibling', array($child1->id, $child2->id), array_values(array_intersect($orderedIDs, array($child1->id, $child2->id))));

		$pages->sort($cloned, 5);
		$cloned->set('sortPrevious', null);

		$pages->trash($cloned);
		$freshCloned = $pages->getFresh($cloned->id);
		$this->check('trash() page is now in trash', true, $freshCloned->isTrash());
		$this->check('isTrash() false for non-trashed page', false, $child1->isTrash());

		$pages->restore($cloned);
		$pages->uncacheAll();
		$freshCloned2 = $pages->getFresh($cloned->id);
		$this->check('restore() page is no longer in trash', false, $freshCloned2->isTrash());

		$clonedId = $cloned->id;
		$pages->delete($cloned);
		unset($this->createdPageIDs[$clonedId]);
		$this->check('delete() page no longer findable by ID', 0, $pages->get($clonedId)->id);

		$manyCount = 0;
		foreach($pages->findMany("template={$this->childTemplateName}, parent=$page->id, include=all") as $p) {
			$manyCount++;
		}
		$this->check('findMany() iterates pages without error', true, $manyCount >= 2);

		$child1Id = $child1->id;
		$pages->delete($child1);
		unset($this->createdPageIDs[$child1Id]);
		$child2Id = $child2->id;
		$pages->delete($child2);
		unset($this->createdPageIDs[$child2Id]);
		$this->check('cleanup: child1 deleted', 0, $pages->get($child1Id)->id);
		$this->check('cleanup: child2 deleted', 0, $pages->get($child2Id)->id);
	}

	protected function getTestChildIDs() {
		$ids = array();
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		foreach($pages->find("template={$this->childTemplateName}, parent=$page->id, include=all, sort=sort") as $child) {
			$ids[] = $child->id;
		}

		return $ids;
	}

	protected function cleanupTestPages() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();

		foreach(array_reverse($this->createdPageIDs) as $id) {
			$this->cleanupPage($id);
		}
		$this->createdPageIDs = array();

		foreach($pages->find("template={$this->childTemplateName}, parent=$page->id, include=all") as $leftover) {
			$this->cleanupPage($leftover);
		}

		foreach($pages->find("template={$this->childTemplateName}, include=all, status=" . Page::statusTrash) as $leftover) {
			$this->cleanupPage($leftover);
		}
	}

	protected function cleanupPage($item) {
		$pages = $this->wire()->pages;
		$p = $item instanceof Page ? $item : $pages->get((int) $item);

		if(!$p->id) return;
		$pages->delete($p, true);
	}

	protected function cleanupTemplate() {
		$templates = $this->wire()->templates;
		$fieldgroups = $this->wire()->fieldgroups;
		$fields = $this->wire()->fields;
		$titleField = $fields->get('title');
		$childTemplate = $templates->get($this->childTemplateName);

		if($this->createdTemplate) {
			if($childTemplate) {
				$fieldgroup = $childTemplate->fieldgroup;
				$templates->delete($childTemplate);
				if($fieldgroup && $fieldgroup->id) $fieldgroups->delete($fieldgroup);
				$this->li("Deleted template: $this->childTemplateName");
			}
			$this->createdTemplate = false;

		} else if($this->addedTitleField) {
			if($childTemplate && $titleField && $childTemplate->hasField($titleField)) {
				$childTemplate->fieldgroup->remove($titleField);
				$childTemplate->fieldgroup->save();
			}
			$this->addedTitleField = false;
		}
	}
}
