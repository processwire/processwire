<?php namespace ProcessWire;

/**
 * Tests for FieldtypePage
 *
 */
class WireTest_FieldtypePage extends WireTest {

	protected $fieldName = 'test_page';
	protected $fieldNameOrFalse = 'test_page_or_false';
	protected $fieldNameOrNull = 'test_page_or_null';

	public function init() {
		$this->ensureFields();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$refPage = $pages->get(1);
		if(!$refPage->id) $this->fail('Could not load reference page (id=1)');

		$name = $this->fieldName;
		$page->of(false);
		$page->set($name, $refPage);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if(!($val instanceof PageArray)) $this->fail('Expected PageArray, got: ' . get_class($val));
		if($val->count() !== 1 || $val->first()->id !== $refPage->id) {
			$this->fail("Expected PageArray with id=$refPage->id, got count=$val->count()");
		}
		$this->li('derefAsPage=0: set by Page object, got PageArray with 1 item verified');

		$page->set($name, $refPage->id);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if($val->count() !== 1 || $val->first()->id !== $refPage->id) {
			$this->fail("Expected page id=$refPage->id, got: " . $val->count());
		}
		$this->li('derefAsPage=0: set by page ID verified');

		$page->set($name, null);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if(!($val instanceof PageArray) || $val->count() !== 0) {
			$this->fail('Expected empty PageArray, got: ' . var_export($val, true));
		}
		$this->li('derefAsPage=0: empty value returns empty PageArray verified');

		$page->of(false);
		$page->get($name)->add($refPage);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 1) {
			$this->fail('Expected 1 after add(), got: ' . $page->get($name)->count());
		}
		$this->li('derefAsPage=0: add() verified');

		$page->of(false);
		$page->get($name)->remove($refPage);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		if($page->get($name)->count() !== 0) {
			$this->fail('Expected 0 after remove(), got: ' . $page->get($name)->count());
		}
		$this->li('derefAsPage=0: remove() verified');

		$name1 = $this->fieldNameOrFalse;
		$page->set($name1, $refPage->id);
		$page->save($name1);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name1);
		if(!($val instanceof Page) || $val->id !== $refPage->id) {
			$this->fail("Expected Page with id=$refPage->id, got: " . var_export($val, true));
		}
		$this->li('derefAsPage=1: populated returns Page verified');

		$page->set($name1, null);
		$page->save($name1);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name1);
		if($val !== false) {
			$this->fail('Expected false when empty with derefAsPage=1, got: ' . var_export($val, true));
		}
		$this->li('derefAsPage=1: empty returns false verified');

		$name2 = $this->fieldNameOrNull;
		$page->set($name2, $refPage->id);
		$page->save($name2);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name2);
		if(!($val instanceof Page) || $val->id !== $refPage->id) {
			$this->fail("Expected Page with id=$refPage->id, got: " . var_export($val, true));
		}
		$this->li('derefAsPage=2: populated returns Page verified');

		$page->set($name2, null);
		$page->save($name2);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name2);
		if(!($val instanceof NullPage)) {
			$this->fail('Expected NullPage when empty with derefAsPage=2, got: ' . get_class($val));
		}
		$this->li('derefAsPage=2: empty returns NullPage verified');

		$name = $this->fieldName;
		$page->of(false);
		$page->set($name, $refPage);
		$page->save($name);
		$refTemplateName = $refPage->template->name;
		$selectors = array(
			"template=test, $name=$refPage->id",
			"template=test, $name=$refPage->name",
			"template=test, $name=$refPage->path",
			"template=test, $name.count>0",
			"template=test, $name!=\"\"",
			"template=test, $name.template=$refTemplateName",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, null);
		$page->save($name);
		$p = $pages->findOne("template=test, $name=\"\"");
		if($p->id !== $page->id) $this->fail("Selector failed: $name=\"\"");
		$this->li("Selector passed: $name=\"\"");
	}

	protected function ensureFields() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$fieldtype = $modules->get('FieldtypePage');
		$this->ensureField($fields, $fieldtype, $this->fieldName, 'Test Page', FieldtypePage::derefAsPageArray);
		$this->ensureField($fields, $fieldtype, $this->fieldNameOrFalse, 'Test Page Or False', FieldtypePage::derefAsPageOrFalse);
		$this->ensureField($fields, $fieldtype, $this->fieldNameOrNull, 'Test Page Or NullPage', FieldtypePage::derefAsPageOrNullPage);

		$fieldgroup = $page->template->fieldgroup;
		foreach(array($this->fieldName, $this->fieldNameOrFalse, $this->fieldNameOrNull) as $name) {
			$field = $fields->get($name);
			if(!$fieldgroup->hasField($field)) {
				$fieldgroup->add($field);
				$fieldgroup->save();
				$this->li("Added field to fieldgroup: $field->name");
			}
		}
	}

	protected function ensureField(Fields $fields, Fieldtype $fieldtype, $name, $label, $derefAsPage) {
		$field = $fields->get($name);
		if($field) return;

		$field = new PageField();
		$field->name = $name;
		$field->type = $fieldtype;
		$field->label = $label;
		$field->derefAsPage = $derefAsPage;
		$field->save();
		$this->li("Created field: $field->name");
	}
}
