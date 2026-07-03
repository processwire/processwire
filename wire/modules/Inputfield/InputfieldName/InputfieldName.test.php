<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldName module.
 *
 */
class WireTest_InputfieldName extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testSanitization();
		$this->testCustomSanitizer();
		$this->testMaxlength();
		$this->testProcessInput();
		$this->testRender();
	}

	protected function newInputfield($name = 'name') {
		$f = $this->wire()->modules->get('InputfieldName');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldName $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldName', true, $f instanceof InputfieldName);
		$this->check('extends InputfieldText', true, $f instanceof InputfieldText);
		$this->check('default type is text', 'text', $f->attr('type'));
		$this->check('default maxlength is page name max length', Pages::nameMaxLength, $f->attr('maxlength'));
		$this->check('default size is full width', 0, $f->attr('size'));
		$this->check('default name attr is name', 'name', $f->attr('name'));
		$this->check('default label is Name', 'Name', $f->label);
		$this->check('default required is true', true, (bool) $f->required);
		$this->check('default sanitizeMethod is name', 'name', $f->sanitizeMethod);
		$this->check('description mentions unsupported characters', 'unsupported characters', $f->description, '*=');
	}

	protected function testSanitization() {
		$sanitizer = $this->wire()->sanitizer;
		$f = $this->newInputfield();

		$value = 'Hello World! Foo';
		$f->val($value);
		$this->check('val() sanitizes spaces and punctuation', $sanitizer->name($value), $f->val());

		$value = 'My.Field-Name 99 (x!)';
		$f->attr('value', $value);
		$this->check('attr(value) preserves allowed name punctuation', $sanitizer->name($value), $f->val());
		$this->check('default sanitizer preserves case', true, strpos($f->val(), 'My.Field-Name') === 0);
	}

	protected function testCustomSanitizer() {
		$f = $this->newInputfield();
		$f->sanitizeMethod = 'pageName';
		$f->init();
		$this->check('init() does not reset custom sanitizeMethod', 'pageName', $f->sanitizeMethod);

		$f->val('Hello World!');
		$this->check('custom pageName sanitizer is used', $this->wire()->sanitizer->pageName('Hello World!'), $f->val());
	}

	protected function testMaxlength() {
		$f = $this->newInputfield();
		$f->attr('maxlength', 8);
		$f->val('Very Long Field Name');

		$this->check('sanitized value is truncated to maxlength', 8, strlen($f->val()));
		$this->check('maxlength truncation happens after sanitization', 'Very_Lon', $f->val());
	}

	protected function testProcessInput() {
		$sanitizer = $this->wire()->sanitizer;

		$f = $this->newInputfield('field_name');
		$this->processInput($f, 'Posted Name!');
		$this->check('processInput() sanitizes submitted value', $sanitizer->name('Posted Name!'), $f->val());
	}

	protected function testRender() {
		$f = $this->newInputfield('field_name');
		$f->val('Render Name!');
		$html = $f->render();

		$this->check('render includes text input type', 'type="text"', $html, '*=');
		$this->check('render includes maxlength', 'maxlength="' . Pages::nameMaxLength . '"', $html, '*=');
		$this->check('render includes sanitized value', 'value="Render_Name_"', $html, '*=');
	}
}
