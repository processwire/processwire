<?php namespace ProcessWire;

/**
 * Tests for FieldtypeFloat
 *
 */
class WireTest_FieldtypeFloat extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'float';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$template = WireTests::templateName;
		$field = $fields->get($name);

		$page->set($name, 3.14);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Basic float (3.14) verified', 3.14, $page->get($name));

		$page->set($name, -2.5);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Negative float (-2.5) verified', -2.5, $page->get($name));

		$page->set($name, 3.14159);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Precision rounding (3.14159 to 3.14) verified', 3.14, $page->get($name));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->set($name, 0.0);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if($val !== 0.0 && $val !== '') {
			$this->fail('Expected 0.0 or blank string, got: ' . var_export($val, true));
		}
		$this->li('Zero value verified: ' . var_export($val, true));

		$field->precision = -1;
		$field->save();
		$page->set($name, 3.14159);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if(round((float) $val, 2) === $val) {
			$this->fail('Expected unrounded value with precision=-1, got: ' . var_export($val, true));
		}
		$this->li("Precision=-1 (no rounding) verified: $val");

		$field->precision = 2;
		$field->save();

		$page->set($name, 3.14);
		$page->save($name);
		$selectors = array(
			"template=$template, $name=3.14",
			"template=$template, $name!=3.15",
			"template=$template, $name>3",
			"template=$template, $name<4",
			"template=$template, $name!=\"\"",
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
			$field = new FloatField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeFloat');
			$field->label = 'Test Float';
			$field->precision = 2;
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
