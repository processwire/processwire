<?php namespace ProcessWire;

/**
 * Tests for FieldtypeInteger
 *
 */
class WireTest_FieldtypeInteger extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'integer';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$template = WireTests::templateName;

		$page->set($name, 42);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Positive integer (42) verified', 42, $page->get($name));

		$page->set($name, -10);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Negative integer (-10) verified', -10, $page->get($name));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->set($name, 0);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if($val !== 0 && $val !== '') {
			$this->fail('Expected int 0 or blank string, got: ' . var_export($val, true));
		}
		$this->li('Zero value verified: ' . var_export($val, true));

		$page->set($name, 42);
		$page->save($name);
		$selectors = array(
			"template=$template, $name=42",
			"template=$template, $name>40",
			"template=$template, $name>=42",
			"template=$template, $name<100",
			"template=$template, $name<=42",
			"template=$template, $name!=99",
		);
		foreach($selectors as $selector) {
			$p = $pages->get($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, '');
		$page->save($name);
		$p = $pages->get("template=$template, $name=\"\"");
		if($p->id !== $page->id) $this->fail("Selector failed: $name=\"\"");
		$this->li("Selector passed: $name=\"\"");
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$field = $fields->get($name);

		if(!$field) {
			$field = new IntegerField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeInteger');
			$field->label = 'Test Integer';
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
