<?php namespace ProcessWire;

/**
 * Tests for FieldtypeDecimal
 *
 */
class WireTest_FieldtypeDecimal extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'decimal';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$template = WireTests::templateName;

		$page->set($name, '123.45');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("String value ('123.45') verified", '123.45', $page->get($name));

		$page->set($name, 99);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if(!is_string($val)) $this->fail('Expected string type, got: ' . var_export($val, true));
		if((float) $val !== 99.0) $this->fail('Expected value of 99, got: ' . var_export($val, true));
		$this->li('Integer input (99) returned as string: ' . var_export($val, true));

		$page->set($name, '-7.50');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if((float) $val !== -7.5) $this->fail('Expected -7.50, got: ' . var_export($val, true));
		$this->li('Negative value (-7.50) verified: ' . var_export($val, true));

		$page->set($name, '0.10');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if((float) $val !== 0.1) $this->fail('Expected 0.10 exact, got: ' . var_export($val, true));
		$this->li('Exact decimal precision (0.10) verified: ' . var_export($val, true));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->set($name, '0.00');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if($val !== '' && (float) $val !== 0.0) {
			$this->fail("Expected '0.00' or blank string, got: " . var_export($val, true));
		}
		$this->li('Zero value verified: ' . var_export($val, true));

		$page->set($name, '123.45');
		$page->save($name);
		$selectors = array(
			"template=$template, $name=123.45",
			"template=$template, $name>100",
			"template=$template, $name>=123.45",
			"template=$template, $name<200",
			"template=$template, $name<=123.45",
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
			$field = new DecimalField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeDecimal');
			$field->label = 'Test Decimal';
			$field->digits = 10;
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
