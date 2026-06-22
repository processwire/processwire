<?php namespace ProcessWire;

/**
 * Tests for FieldtypeText
 *
 */
class WireTest_FieldtypeText extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'headline';

	public function init() {
		$this->ensureField();
	}

	public function execute() {
		$pages = $this->wire()->pages;
		$page = $this->getTestPage();
		$name = $this->fieldName;
		$template = WireTests::templateName;

		$value = 'Hello World ' . mt_rand();
		$this->li('Setting value to: ' . htmlspecialchars($value));
		$page->set($name, $value);
		$page->save($name);

		$page = $pages->getFresh($page->id);
		$freshValue = $page->get($name);
		if($freshValue !== $value) $this->fail("Values don't match: '$freshValue' != '$value'");

		$selectors = array(
			"$name='$value'",
			"$name^=Hello",
			"$name%^=Hello",
			"$name%=World",
			"$name*=World",
			"$name|title~=World",
			"$name~|=Foo Bar World",
			"$name~|*=Wor War Woo",
			"template=$template, $name!=Foobar",
		);

		foreach($selectors as $selector) {
			$p = $pages->get($selector);
			if($p->id !== $page->id) {
				$this->fail("Selector failed: $selector (found page $p->id != $page->id test page)");
			}
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
			$field = new TextField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeText');
			$field->label = 'Headline';
			$field->textformatters = array('TextformatterEntities');
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
