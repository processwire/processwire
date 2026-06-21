<?php namespace ProcessWire;

/**
 * Tests for FieldtypeRepeater
 *
 */
class WireTest_FieldtypeRepeater extends WireTest {

	protected $fieldName = 'test_repeater';
	protected $subFieldName = 'headline';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$subTextField = $fields->get($this->subFieldName);

		$page->of(false);
		foreach($page->get($name) as $item) {
			$page->get($name)->remove($item);
		}
		$page->save($name);

		$val = $page->get($name);
		if(!($val instanceof RepeaterPageArray)) $this->fail('Expected RepeaterPageArray, got: ' . get_class($val));
		$this->li('Empty value is RepeaterPageArray verified');

		$item1 = $page->get($name)->getNewItem();
		if(!($item1 instanceof RepeaterPage)) $this->fail('Expected RepeaterPage from getNewItem(), got: ' . get_class($item1));
		$item1->set($subTextField->name, 'First Item');
		$item1->save();
		$page->save($name);
		$this->li('getNewItem() returns RepeaterPage verified');

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$items = $page->get($name);
		if($items->count() !== 1) $this->fail('Expected 1 item after add, got: ' . $items->count());
		if($items->first()->get($subTextField->name) !== 'First Item') {
			$this->fail("Expected 'First Item', got: " . var_export($items->first()->get($subTextField->name), true));
		}
		$this->li("Item value '$subTextField->name' = 'First Item' verified");

		$item2 = $page->get($name)->getNewItem();
		$item2->set($subTextField->name, 'Second Item');
		$item2->save();
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 2) {
			$this->fail('Expected 2 items, got: ' . $page->get($name)->count());
		}
		$this->li('Two items added, count=2 verified');

		$item = $page->get($name)->first();
		if($item->getForPage()->id !== $page->id) {
			$this->fail("Expected getForPage() to return page id=$page->id, got: " . $item->getForPage()->id);
		}
		if($item->getForField()->name !== $name) {
			$this->fail("Expected getForField() to return field '$name', got: " . $item->getForField()->name);
		}
		$this->li('getForPage() and getForField() verified');

		$item = $page->get($name)->first();
		$item->addStatus(Page::statusUnpublished);
		$item->save();

		$page->of(true);
		$countFormatted = $page->get($name)->count();

		$page->of(false);
		$countUnformatted = $page->get($name)->count();

		if($countFormatted >= $countUnformatted) {
			$this->fail("Expected OF=on to exclude unpublished items (got $countFormatted), OF=off to include all (got $countUnformatted)");
		}
		$this->li("OF=on excludes unpublished: count=$countFormatted; OF=off includes all: count=$countUnformatted");

		$page->of(false);
		$item = $page->get($name)->first();
		$item->removeStatus(Page::statusUnpublished);
		$item->save();

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$toRemove = $page->get($name)->first();
		$page->get($name)->remove($toRemove);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 1) {
			$this->fail('Expected 1 item after remove, got: ' . $page->get($name)->count());
		}
		$this->li('remove() item verified, count=1');

		$byIndex = $page->get($name)->eq(0);
		if(!($byIndex instanceof RepeaterPage)) $this->fail('Expected RepeaterPage from eq(0), got: ' . get_class($byIndex));
		$this->li("eq(0) index access verified: '" . $byIndex->get($subTextField->name) . "'");

		$page->of(false);
		$items = $page->get($name);
		foreach($items as $item) $items->remove($item);
		$page->save($name);
		$item = $page->get($name)->getNewItem();
		$item->set($subTextField->name, 'Selector Test Item');
		$item->save();
		$page->save($name);
		$sub = $subTextField->name;
		$selectors = array(
			"template=test, $name.count>0",
			"template=test, $name.count=1",
			"template=test, $name.$sub*=Selector Test",
			"template=test, $name.$sub~=Item",
			"template=test, $name.$sub^=Selector",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page = $pages->getFresh($page->id);
		$page->of(false);
		$items = $page->get($name);
		foreach($items as $item) $items->remove($item);
		$page->save($name);
		$p = $pages->findOne("template=test, $name.count=0");
		if($p->id !== $page->id) $this->fail("Selector failed: $name.count=0");
		$this->li("Selector passed: $name.count=0");
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypeRepeater');
		$fieldtype->getFieldClass();
		$modules->get('FieldtypeText');

		$subTextField = $this->ensureTextField();
		$field = $fields->get($this->fieldName);

		if(!$field) {
			$field = new RepeaterField();
			$field->name = $this->fieldName;
			$field->type = $fieldtype;
			$field->label = 'Test Repeater';
			$field->save();
			$this->li("Created field: $field->name");
		}

		$repeaterTemplate = $fieldtype->_getRepeaterTemplate($field);
		$repeaterFieldgroup = $repeaterTemplate->fieldgroup;
		if(!$repeaterFieldgroup->hasField($subTextField)) {
			$repeaterFieldgroup->add($subTextField);
			$repeaterFieldgroup->save();
		}

		if(!in_array($subTextField->id, $field->repeaterFields)) {
			$field->repeaterFields = array($subTextField->id);
			$field->save();
			$this->li("Repeater template: $repeaterTemplate->name, sub-fields: $subTextField->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
			$this->li("Added field to fieldgroup: $field->name");
		}
	}

	protected function ensureTextField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$field = $fields->get($this->subFieldName);

		if($field) return $field;

		$field = new TextField();
		$field->name = $this->subFieldName;
		$field->type = $modules->get('FieldtypeText');
		$field->label = 'Headline';
		$field->textformatters = array('TextformatterEntities');
		$field->save();
		$this->li("Created sub-field: $field->name");

		return $field;
	}
}
