<?php namespace ProcessWire;

/**
 * Tests for FieldtypeTextarea
 *
 */
class WireTest_FieldtypeTextarea extends WireTest {

	protected $fieldName = WireTests::fieldPrefix . 'textarea';

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

		$value = "Line one\nLine two\nLine three";
		$page->set($name, $value);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		if($page->getUnformatted($name) !== $value) {
			$this->fail('Multi-line text mismatch: ' . var_export($page->getUnformatted($name), true));
		}
		$this->li('Multi-line text roundtrip verified');

		$page->set($name, '');
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check("Blank value ('') verified", '', $page->getUnformatted($name));

		$html = '<p>Hello <strong>World</strong></p>';
		$page->set($name, $html);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$this->check('HTML content roundtrip (getUnformatted) verified', $html, $page->getUnformatted($name));

		$field->textformatters = array('TextformatterEntities');
		$field->save();
		$raw = '<p>Hello <strong>World</strong></p>';
		$page->set($name, $raw);
		$page->save($name);
		$page = $pages->getFresh($page->id);
		$page->of(true);
		$formatted = $page->get($name);
		$page->of(false);
		if(strpos($formatted, '<') !== false) {
			$this->fail('Expected entities encoded output, got: ' . var_export($formatted, true));
		}
		if(strpos($formatted, '&lt;') === false) {
			$this->fail('Expected &lt; in entities-encoded output, got: ' . var_export($formatted, true));
		}
		$this->li('TextformatterEntities applied on OF=on verified');

		$rawCheck = $page->getUnformatted($name);
		if($rawCheck !== $raw) {
			$this->fail('getUnformatted() should return raw value, got: ' . var_export($rawCheck, true));
		}
		$this->li('getUnformatted() bypasses Textformatters verified');

		$field->textformatters = array();
		$field->save();

		$page->set($name, 'The quick brown fox jumps over the lazy dog');
		$page->save($name);
		$selectors = array(
			"template=$template, $name*=quick brown",
			"template=$template, $name~=fox lazy",
			"template=$template, $name~|=cat brown bird",
			"template=$template, $name%=quick brown",
			"template=$template, $name^=The quick",
			"template=$template, $name\$=lazy dog",
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
			$field = new TextareaField();
			$field->name = $name;
			$field->type = $modules->get('FieldtypeTextarea');
			$field->label = 'Test Textarea';
			$field->contentType = 0;
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
