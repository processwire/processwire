<?php namespace ProcessWire;

/**
 * Tests for FieldtypeURL
 *
 */
class WireTest_FieldtypeURL extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'url';

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

		$page->set($name, 'https://processwire.com/docs/');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Absolute URL verified', 'https://processwire.com/docs/', $page->get($name));

		$page->set($name, '/local/path/');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Relative URL verified', '/local/path/', $page->get($name));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->set($name, 'javascript:alert(1)');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Dangerous URL scheme (javascript:) sanitized to blank verified', '', $page->get($name));

		$field->noRelative = 1;
		$field->save();
		$page->set($name, '/should/be/rejected/');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('noRelative=1 rejects relative URL verified', '', $page->get($name));

		$page->set($name, 'https://processwire.com/');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Absolute URL still accepted with noRelative=1 verified', 'https://processwire.com/', $page->get($name));

		$field->noRelative = 0;
		$field->save();

		$selectors = array(
			"template=$template, $name^=https://",
			"template=$template, $name*=processwire.com",
			"template=$template, $name\$=.com/",
			"template=$template, $name%=processwire",
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
			$field = new URLField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeURL');
			$field->label = 'Test URL';
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
