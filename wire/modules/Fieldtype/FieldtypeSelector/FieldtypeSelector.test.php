<?php namespace ProcessWire;

/**
 * Tests for FieldtypeSelector
 *
 */
class WireTest_FieldtypeSelector extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'selector';

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

		$selector = 'template=basic-page, sort=-created, limit=10';
		$page->set($name, $selector);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('Selector string roundtrip verified', $selector, $page->get($name));

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->get($name));

		$page->set($name, 'id>0, limit=1');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$storedSelector = $page->get($name);
		$results = $pages->find($storedSelector);
		if(!($results instanceof PageArray)) {
			$this->fail('Expected PageArray from stored selector, got: ' . get_class($results));
		}
		$this->li('Stored selector executed successfully, found ' . $results->count() . ' page(s)');

		$field->initValue = 'template=admin';
		$field->save();

		$userPart = 'sort=name';
		$page->set($name, $userPart);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$val = $page->get($name);
		if(strpos($val, 'template=admin') === false) {
			$this->fail("Expected initValue prefix 'template=admin' in returned value, got: " . var_export($val, true));
		}
		$this->li('initValue prefix present in returned value: ' . var_export($val, true));

		$page->of(false);
		$rawVal = $page->getUnformatted($name);
		if(strpos($rawVal, 'template=admin') === false) {
			$this->fail('Expected initValue in getUnformatted() too (applied at wakeup), got: ' . var_export($rawVal, true));
		}
		$this->li('getUnformatted() also includes initValue prefix (wakeup, not format-time) verified');

		$field->initValue = '';
		$field->save();

		$page->set($name, 'template=basic-page, sort=title, limit=10');
		$page->save($name);
		$selectors = array(
			"template=$template, $name*=\"template=basic-page\"",
			"template=$template, $name~=basic-page",
			"template=$template, $name^=\"template=basic-page\"",
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
			$field = new SelectorField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeSelector');
			$field->label = 'Test Selector';
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
