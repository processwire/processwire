<?php namespace ProcessWire;

/**
 * Tests for FieldtypeToggle
 *
 */
class WireTest_FieldtypeToggle extends WireTest {

	protected $fieldName = 'test_toggle';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$field = $fields->get($name);

		$page->of(false);

		$page->set($name, 1);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check('Yes (1) verified', 1, $page->get($name));

		$page->set($name, 0);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check('No (0) verified', 0, $page->get($name));

		$page->set($name, 2);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check('Other (2) verified', 2, $page->get($name));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check("Unknown ('') verified", '', $page->get($name));

		$page->set($name, 'yes');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check("Keyword 'yes' stored as 1 verified", 1, $page->get($name));

		$page->set($name, 'no');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check("Keyword 'no' stored as 0 verified", 0, $page->get($name));

		$page->set($name, 'unknown');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check("Keyword 'unknown' stored as '' verified", '', $page->get($name));

		$page->set($name, true);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check('Bool true stored as 1 verified', 1, $page->get($name));

		$page->set($name, false);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check('Bool false stored as 0 verified', 0, $page->get($name));

		$page->set($name, 0);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$noVal = $page->get($name);

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$unknownVal = $page->get($name);

		if($noVal === $unknownVal) {
			$this->fail("Expected 0 (no) and '' (unknown) to be distinct, but both returned: " . var_export($noVal, true));
		}
		$this->li("0 (no) and '' (unknown) are distinct: 0=" . var_export($noVal, true) . ", ''=" . var_export($unknownVal, true));

		$field->formatType = 1;
		$field->save();
		$page->set($name, 1);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(true);
		$this->check('formatType=1 returns bool true for yes verified', true, $page->get($name));

		$page->of(false);
		$field->formatType = 0;
		$field->save();

		$page->set($name, 1);
		$page->save($name);
		$selectors = array(
			"template=test, $name=1",
			"template=test, $name=yes",
			"template=test, $name!=0",
			"template=test, $name!=\"\"",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, 0);
		$page->save($name);
		foreach(array("template=test, $name=0", "template=test, $name=no") as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, '');
		$page->save($name);
		foreach(array("template=test, $name=\"\"", "template=test, $name=unknown") as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$field = $fields->get($name);

		if(!$field) {
			$field = new ToggleField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeToggle');
			$field->label = 'Test Toggle';
			$field->useOther = 1;
			$field->save();
			$this->li("Created field: $field->name");
		}

		$fieldgroup = $page->template->fieldgroup;
		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
			$this->li("Added field to fieldgroup: $fieldgroup->name");
		}
	}
}
