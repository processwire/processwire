<?php namespace ProcessWire;

/**
 * Tests for FieldtypeCheckbox
 *
 */
class WireTest_FieldtypeCheckbox extends WireTest {

	protected $fieldName = 'test_checkbox';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$page = $this->getTestPage();
		$name = $this->fieldName;

		$page->set($name, 1);
		$page->save($name);
		$page = $this->wire()->pages->getFresh($page->id);
		$this->check('Checked value (1) verified', 1, $page->get($name));

		$page->set($name, 0);
		$page->save($name);
		$page = $this->wire()->pages->getFresh($page->id);
		$this->check('Unchecked value (0) verified', 0, $page->get($name));

		$page->set($name, true);
		$page->save($name);
		$page = $this->wire()->pages->getFresh($page->id);
		$this->check('Bool true sanitized to 1 verified', 1, $page->get($name));

		$page->set($name, false);
		$page->save($name);
		$page = $this->wire()->pages->getFresh($page->id);
		$this->check('Bool false sanitized to 0 verified', 0, $page->get($name));
	}

	protected function ensureField() {
		$fields = $this->wire()->fields;
		$modules = $this->wire()->modules;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$field = $fields->get($name);

		if(!$field) {
			$field = new CheckboxField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeCheckbox');
			$field->label = 'Test Checkbox';
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
