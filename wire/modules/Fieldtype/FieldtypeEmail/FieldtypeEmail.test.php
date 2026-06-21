<?php namespace ProcessWire;

/**
 * Tests for FieldtypeEmail
 *
 */
class WireTest_FieldtypeEmail extends WireTest {

	protected $fieldName = 'test_email';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;

		$page->set($name, 'user@example.com');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Valid email verified', 'user@example.com', $page->get($name));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->set($name, 'not-an-email');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Invalid email sanitized to blank verified', '', $page->get($name));

		$page->set($name, 'user+tag@mail.example.co.uk');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Complex valid email (plus addressing, subdomain) verified', 'user+tag@mail.example.co.uk', $page->get($name));

		$page->set($name, 'User@Example.COM');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if($val === '') $this->fail('Expected uppercase email to be accepted, got blank');
		$this->li('Uppercase email accepted: ' . var_export($val, true));

		$page->set($name, 'user@example.com');
		$page->save($name);
		$selectors = array(
			"template=test, $name=user@example.com",
			"template=test, $name*=example",
			"template=test, $name\$=.com",
			"template=test, $name^=user",
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
			$field = new EmailField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeEmail');
			$field->label = 'Test Email';
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
