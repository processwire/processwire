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
		$this->testCreatingSavingSortingAndDeletingPages();
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

		$found = $pages->findOne("id=$page->id");
		$this->check('findOne() returns correct page', $page->id, $found->id);

		$notFound = $pages->findOne('name=nonexistent-page-xyz-12345');
		$this->check('findOne() returns NullPage when not found', 0, $notFound->id);

		$results = $pages->find("id=$page->id");
		$this->check('find() returns PageArray', true, $results instanceof PageArray);
		$this->check('find() PageArray contains the test page', true, $results->has($page));

		$n = $pages->count("id=$page->id");
		$this->check('count() returns int', true, is_int($n));
		$this->check('count() finds 1 for known page ID', 1, $n);
		$this->check('count() returns 0 for no match', 0, $pages->count('name=nonexistent-page-xyz-12345'));

		$this->check('has() returns page ID when found', $page->id, $pages->has("id=$page->id"));
		$this->check('has() returns 0 when not found', 0, $pages->has('name=nonexistent-page-xyz-12345'));

		$ids = $pages->findIDs("id=$page->id");
		$this->check('findIDs() returns array', true, is_array($ids));
		$this->check('findIDs() contains page ID', true, in_array($page->id, $ids));

		$idsVerbose = $pages->findIDs("id=$page->id", true);
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

		$rawResults = $pages->findRaw("id=$page->id", array('name'));
		$this->check('findRaw() returns array indexed by page ID', true, isset($rawResults[$page->id]));
		$this->check("findRaw() value has 'name' key", true, isset($rawResults[$page->id]['name']));
		$this->check("findRaw() 'name' matches page name", $page->name, $rawResults[$page->id]['name']);

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
		$this->check('save() persists title change', 'Pages Test Child A - Updated', $pages->getFresh($child1->id)->title);

		$child1->of(false);
		$child1->title = 'Pages Test Child A - saveField';
		$pages->saveField($child1, 'title');
		$this->check('saveField() persists single field', 'Pages Test Child A - saveField', $pages->getFresh($child1->id)->title);

		$child1->of(false);
		$child1->title = 'Pages Test Child A - saveFields';
		$pages->saveFields($child1, 'title');
		$this->check('saveFields() persists via CSV string', 'Pages Test Child A - saveFields', $pages->getFresh($child1->id)->title);

		$child1->of(false);
		$child1->title = 'Pages Test Child A - saveFields array';
		$pages->saveFields($child1, array('title'));
		$this->check('saveFields() persists via array', 'Pages Test Child A - saveFields array', $pages->getFresh($child1->id)->title);

		$beforeModified = $pages->getFresh($child1->id)->modified;
		$pages->touch($child1);
		$this->check('touch() updated or preserved modified timestamp', true, $pages->getFresh($child1->id)->modified >= $beforeModified);

		$cloned = $pages->clone($child1);
		$this->createdPageIDs[$cloned->id] = $cloned->id;
		$this->check('clone() returns a Page with new id', true, $cloned->id > 0 && $cloned->id !== $child1->id);
		$this->check('clone() page has same parent', $child1->parent->id, $cloned->parent->id);
		$this->check('clone() page has same template', $child1->template->name, $cloned->template->name);

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
