<?php namespace ProcessWire;

/**
 * Tests for FieldtypePageTable
 *
 */
class WireTest_FieldtypePageTable extends WireTest {

	protected $fieldName = 'test_pagetable';
	protected $itemTemplateName = 'test-pagetable-item';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$itemTemplateName = $this->itemTemplateName;

		$page->of(false);
		foreach($page->get($name) as $item) {
			$page->get($name)->remove($item);
			$pages->delete($item);
		}
		$page->save($name);

		$val = $page->get($name);
		if(!($val instanceof PageTableArray)) $this->fail('Expected PageTableArray, got: ' . get_class($val));
		if($val->count() !== 0) $this->fail('Expected empty PageTableArray, got count: ' . $val->count());
		$this->li('Empty value is PageTableArray (count=0) verified');

		$item1 = $page->get($name)->getNewItem();
		if(!($item1 instanceof Page)) $this->fail('Expected Page from getNewItem(), got: ' . get_class($item1));
		if($item1->template->name !== $itemTemplateName) {
			$this->fail("Expected item template '$itemTemplateName', got: " . $item1->template->name);
		}
		$this->li('getNewItem() returns Page with correct template verified');

		$item1->title = 'First Item';
		$item1->save();
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$items = $page->get($name);
		if($items->count() !== 1) $this->fail('Expected 1 item after save, got: ' . $items->count());
		if($items->first()->title !== 'First Item') {
			$this->fail("Expected title 'First Item', got: " . var_export($items->first()->title, true));
		}
		$this->li("Item saved and retrieved, title='First Item' verified");

		$savedItem = $items->first();
		if(!$savedItem->id) $this->fail('Expected saved item to have an id');
		if($savedItem->parent->id !== $page->id) {
			$this->fail("Expected item parent to be test page (id=$page->id), got: " . $savedItem->parent->id);
		}
		$this->li("Item is a real page: id=$savedItem->id, parent=" . $savedItem->parent->path);

		$found = $pages->find("template=$itemTemplateName, parent=$page->id");
		if(!$found->has($savedItem)) $this->fail('Expected item to be findable via pages()->find()');
		$this->li('Item independently findable via pages()->find() verified');

		$item2 = $page->get($name)->getNewItem();
		$item2->title = 'Second Item';
		$item2->save();
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 2) {
			$this->fail('Expected 2 items, got: ' . $page->get($name)->count());
		}
		$this->li('Two items added, count=2 verified');

		$page->of(false);
		$item = $page->get($name)->first();
		$item->addStatus(Page::statusUnpublished);
		$item->save();

		$page->of(true);
		$countFormatted = $page->get($name)->count();
		$page->of(false);
		$countUnformatted = $page->get($name)->count();

		if($countFormatted >= $countUnformatted) {
			$this->fail("Expected OF=on to exclude unpublished (got $countFormatted), OF=off to include all (got $countUnformatted)");
		}
		$this->li("OF=on excludes unpublished: count=$countFormatted; OF=off includes all: count=$countUnformatted");

		$page->of(false);
		$item = $page->get($name)->first();
		$item->removeStatus(Page::statusUnpublished);
		$item->save();

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$toRemove = $page->get($name)->first();
		$removeId = $toRemove->id;
		$page->get($name)->remove($toRemove);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 1) {
			$this->fail('Expected 1 item after remove(), got: ' . $page->get($name)->count());
		}
		$stillExists = $pages->get($removeId);
		if(!$stillExists->id) {
			$this->fail("Expected removed item page to still exist (remove != delete), but it's gone");
		}
		$this->li("remove() detaches from field but page still exists (id=$removeId) verified");

		foreach($pages->find("template=$itemTemplateName, parent=$page->id, include=all") as $item) {
			$pages->delete($item);
		}
		$pages->delete($stillExists);
		$page->of(false);
		$page->get($name)->removeAll();
		$page->save($name);
		$this->li('Cleanup: all item pages deleted');

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$selectorItem = $page->get($name)->getNewItem();
		$selectorItem->title = 'Selector Test Item';
		$selectorItem->save();
		$page->save($name);
		$selectors = array(
			"template=test, $name.count>0",
			"template=test, $name.title*=Selector Test",
			"template=test, $name.title=Selector Test Item",
			"template=test, $name.template=$itemTemplateName",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$pages->delete($selectorItem);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$page->get($name)->removeAll();
		$page->save($name);
		$p = $pages->findOne("template=test, $name.count=0");
		if($p->id !== $page->id) $this->fail("Selector failed: $name.count=0");
		$this->li("Selector passed: $name.count=0");
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$templates = $this->wire()->templates;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$itemTemplateName = $this->itemTemplateName;
		$itemTemplate = $templates->get($itemTemplateName);

		if(!$itemTemplate) {
			$fieldgroup = new Fieldgroup();
			$fieldgroup->name = $itemTemplateName;
			$fieldgroup->add($fields->get('title'));
			$fieldgroup->save();

			$itemTemplate = new Template();
			$itemTemplate->name = $itemTemplateName;
			$itemTemplate->fieldgroup = $fieldgroup;
			$itemTemplate->save();
			$this->li("Created item template: $itemTemplateName");
		}

		$field = $fields->get($this->fieldName);
		if(!$field) {
			$field = new PageTableField();
			$field->name = $this->fieldName;
			$field->type = $modules->get('FieldtypePageTable');
			$field->label = 'Test PageTable';
			$field->template_id = $itemTemplate->id;
			$field->save();
			$this->li("Created field: $field->name (template: $itemTemplateName, parent: owning page)");
		}

		$fieldgroup = $page->template->fieldgroup;
		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
			$this->li("Added field to fieldgroup: $field->name");
		}
	}
}
