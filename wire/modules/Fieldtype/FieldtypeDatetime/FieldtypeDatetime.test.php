<?php namespace ProcessWire;

/**
 * Tests for FieldtypeDatetime
 *
 */
class WireTest_FieldtypeDatetime extends WireTest {

	protected $fieldName = 'test_datetime';

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

		$ts = mktime(14, 30, 0, 4, 8, 2026);
		$page->set($name, $ts);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$val = $page->get($name);
		if(!is_int($val)) $this->fail('Expected int timestamp with OF off, got: ' . var_export($val, true));
		if($val !== $ts) $this->fail("Timestamp mismatch: expected $ts, got $val");
		$this->li("Unix timestamp roundtrip verified: $val");

		$page->set($name, '2026-04-08 14:30:00');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check("String input ('2026-04-08 14:30:00') stored as timestamp verified", $ts, $page->get($name));

		$page->set($name, new \DateTime('2026-04-08 14:30:00'));
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check('DateTime object input stored as timestamp verified', $ts, $page->get($name));

		$page->of(true);
		$val = $page->get($name);
		if(!is_string($val) || $val === '') $this->fail('Expected formatted string with OF on, got: ' . var_export($val, true));
		$this->li("Formatted output (OF on) returns string: '$val'");

		$field->dateOutputFormat = 'j F Y';
		$field->save();
		$page = $pages->getFresh($page->id);
		$page->of(true);
		$this->check("dateOutputFormat 'j F Y' applied correctly", '8 April 2026', $page->get($name));

		$field->dateOutputFormat = 'Y-m-d';
		$field->save();
		$page->of(false);

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(false);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->of(true);
		$this->check("Blank value stays '' with OF on verified", '', $page->get($name));

		$page->of(false);
		$page->set($name, '2026-04-08 14:30:00');
		$page->save($name);
		$selectors = array(
			"template=test, $name=2026-04-08",
			"template=test, $name=2026-04-08 14:30:00",
			"template=test, $name!=2026-04-09",
			"template=test, $name^=2026-04",
			"template=test, $name%=2026-04-08",
			"template=test, $name>2026-01-01",
			"template=test, $name>=2026-04-08",
			"template=test, $name<2027-01-01",
			"template=test, $name!=\"\"",
		);
		foreach($selectors as $selector) {
			$p = $pages->findOne($selector);
			if($p->id !== $page->id) $this->fail("Selector failed: $selector");
			$this->li("Selector passed: $selector");
		}

		$page->set($name, '');
		$page->save($name);
		$p = $pages->findOne("template=test, $name=\"\"");
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
			$field = new DatetimeField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeDatetime');
			$field->label = 'Test Datetime';
			$field->dateOutputFormat = 'Y-m-d';
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
