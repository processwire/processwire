<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldURL module.
 *
 */
class WireTest_InputfieldURL extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testSanitizationAndValidation();
		$this->testRelativeUrlOptions();
		$this->testAllowOptions();
		$this->testRender();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'href') {
		$f = $this->wire()->modules->get('InputfieldURL');
		$f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is text', 'text', $f->attr('type'));
		$this->check('default name is href', 'href', $f->attr('name'));
		$this->check('default maxlength is 1024', 1024, $f->attr('maxlength'));
		$this->check('default size is 0', 0, $f->attr('size'));
		$this->check('default label is URL', 'URL', $f->label);
		$this->check('default noRelative is false', 0, $f->noRelative);
		$this->check('default addRoot is false', 0, $f->addRoot);
		$this->check('default allowIDN is false', 0, $f->allowIDN);
		$this->check('default allowQuotes is false', 0, $f->allowQuotes);
	}

	protected function testSanitizationAndValidation() {
		$f = $this->newInputfield();
		$f->val('example.com/path');
		$this->check('missing scheme is prepended', 'http://example.com/path', $f->val());

		$f->getErrors(true);
		$f->val('old.example.com');
		$this->check('valid old URL set', 'http://old.example.com', $f->val());
		$f->val('http://exa mple.com/');
		$this->check('invalid URL preserves previous value', 'http://old.example.com', $f->val());
		$this->check('invalid URL records error', true, count($f->getErrors(true)) > 0);
	}

	protected function testRelativeUrlOptions() {
		$f = $this->newInputfield();
		$f->val('/about/');
		$this->check('relative URL allowed by default', '/about/', $f->val());

		$f = $this->newInputfield();
		$f->noRelative = true;
		$f->getErrors(true);
		$f->val('/about/');
		$this->check('noRelative rejects relative URL', '', $f->val());
		$this->check('noRelative records error', true, count($f->getErrors(true)) > 0);

		$f = $this->newInputfield();
		$f->addRoot = true;
		$f->val('local/path');
		$this->check('addRoot allows sanitizer to make local URL absolute/rooted', true, strlen($f->val()) > 0);
	}

	protected function testAllowOptions() {
		$f = $this->newInputfield();
		$f->allowQuotes = true;
		$f->val('/path/"quoted"/');
		$this->check('allowQuotes preserves double quote', true, strpos($f->val(), '"') !== false);

		$f = $this->newInputfield();
		$f->allowQuotes = false;
		$f->val('/path/"quoted"/');
		$this->check('allowQuotes false strips double quote', false, strpos($f->val(), '"') !== false);
	}

	protected function testRender() {
		$f = $this->newInputfield('website');
		$f->val('https://processwire.com/');
		$html = $f->render();

		$this->check('render returns input element', '<input', $html, '*=');
		$this->check('render includes name attribute', 'name="website"', $html, '*=');
		$this->check('render includes text type', 'type="text"', $html, '*=');
		$this->check('render includes value', 'https://processwire.com/', $html, '*=');

		$f = $this->newInputfield();
		$f->addRoot = true;
		$f->noRelative = false;
		$f->notes = 'Existing notes';
		$f->render();
		$this->check('render does not overwrite existing notes', 'Existing notes', $f->notes);
	}
}
