<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldTextarea module
 *
 * Tests textarea-specific behavior: rows property, rendering (render/renderValue),
 * maxlength behavior with/without fieldtype, HTML content type, multiline value
 * handling, and inherited properties from InputfieldText.
 *
 */
class WireTest_InputfieldTextarea extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testRender();
		$this->testRenderValue();
		$this->testRenderValueHTML();
		$this->testRowsAttribute();
		$this->testMultilineValue();
		$this->testMaxlengthStandalone();
		$this->testMaxlengthWithFieldtype();
		$this->testContentType();
		$this->testInheritedProperties();
		$this->testStripTags();
		$this->testNoTrim();
		$this->testMinlengthValidation();
		$this->testConstants();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_textarea') {
		$f = $this->wire()->modules->get('InputfieldTextarea');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput($f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		$input = new WireInputData($data);
		return $f->processInput($input);
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		// Default rows is 5
		$this->check('default rows is 5', 5, $f->attr('rows'));

		// Default contentType is 0 (unknown)
		$this->check('default contentType is 0', 0, $f->contentType);

		// Default maxlength for standalone is 0 (null hasFieldtype)
		$this->check('default maxlength is 0 for null hasFieldtype', 0, $f->attr('maxlength'));

		// val() getter/setter works
		$f->val('Hello World');
		$this->check('val() sets value', 'Hello World', $f->val());
	}

	protected function testRender() {
		$f = $this->newInputfield('my_textarea');
		$f->val('Hello World');
		$html = $f->render();

		// render() returns <textarea element
		$this->check('render() returns <textarea', true, strpos($html, '<textarea') !== false);

		// render() includes name attribute
		$this->check('render() includes name attribute', 'name="my_textarea"', $html, '*=');

		// render() includes rows attribute
		$this->check('render() includes rows attribute', 'rows="5"', $html, '*=');

		// render() includes value content (entity encoded)
		$this->check('render() includes value content', 'Hello World', $html, '*=');

		// render() does not include <input
		$this->check('render() does not include <input', false, strpos($html, '<input') !== false);

		// render() does not include type attribute
		$this->check('render() does not include type attribute', false, strpos($html, 'type="text"') !== false);
	}

	protected function testRenderValue() {
		// renderValue with plain text
		$f = $this->newInputfield();
		$f->val('Hello World');
		$html = $f->renderValue();
		$this->check('renderValue() includes value', 'Hello World', $html, '*=');

		// renderValue converts newlines to <br>
		$f = $this->newInputfield();
		$f->val("Line 1\nLine 2");
		$html = $f->renderValue();
		$this->check('renderValue() converts newlines to <br>', true, strpos($html, '<br') !== false);

		// renderValue entity-encodes content
		$f = $this->newInputfield();
		$f->val('A & B');
		$html = $f->renderValue();
		$this->check('renderValue() entity-encodes content', true, strpos($html, '&amp;') !== false);
	}

	protected function testRenderValueHTML() {
		// renderValue with HTML content type
		$f = $this->newInputfield();
		$f->contentType = FieldtypeTextarea::contentTypeHTML;
		$f->val('<p>Hello <strong>World</strong></p>');
		$html = $f->renderValue();
		$this->check('renderValue() HTML wraps in div', 'InputfieldTextareaContentTypeHTML', $html, '*=');
		$this->check('renderValue() HTML preserves paragraph tag', '<p>', $html, '*=');

		// renderValue with plain text content type (default)
		$f = $this->newInputfield();
		$f->val('<p>Hello</p>');
		$html = $f->renderValue();
		$this->check('renderValue() plain text encodes HTML tags', true, strpos($html, '&lt;') !== false);
	}

	protected function testRowsAttribute() {
		// Set rows
		$f = $this->newInputfield();
		$f->rows = 10;
		$this->check('rows property sets attribute', 10, $f->attr('rows'));

		// Render includes custom rows
		$html = $f->render();
		$this->check('render() includes custom rows', 'rows="10"', $html, '*=');
	}

	protected function testMultilineValue() {
		// Multi-line value preserves newlines
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$value = "Line 1\nLine 2\nLine 3";
		$f->val($value);
		$this->check('multiline value preserves newlines', $value, $f->val());

		// CRLF is converted to LF
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->val("Line 1\r\nLine 2");
		$this->check('CRLF converted to LF', "Line 1\nLine 2", $f->val());
	}

	protected function testMaxlengthStandalone() {
		// Standalone (hasFieldtype=false): maxlength truncates value
		$f = $this->newInputfield();
		$f->set('hasFieldtype', false);
		$f->attr('maxlength', 10);
		$f->val(str_repeat('a', 100));
		$this->check('standalone maxlength truncates value', 10, strlen($f->val()));
	}

	protected function testMaxlengthWithFieldtype() {
		// With Fieldtype: maxlength does NOT truncate, but processInput warns
		$ft = $this->wire()->fieldtypes->get('FieldtypeTextarea');
		$f = $this->newInputfield();
		$f->set('hasFieldtype', $ft);
		$f->attr('maxlength', 10);
		$f->val(str_repeat('a', 100));
		$this->check('fieldtype maxlength does not truncate', 100, strlen($f->val()));

		// processInput reports error when exceeding maxlength
		$f2 = $this->newInputfield('test_ft_ml');
		$f2->set('hasFieldtype', $ft);
		$f2->attr('maxlength', 5);
		$this->processInput($f2, 'abcdefghij');
		$errors = $f2->getErrors(true);
		$this->check('fieldtype maxlength warns on processInput', true, count($errors) > 0);
		$this->check('fieldtype maxlength error mentions maximum', 'maximum', $errors[0], '*=');
	}

	protected function testContentType() {
		// Default contentType is unknown
		$f = $this->newInputfield();
		$this->check('default contentType is unknown', FieldtypeTextarea::contentTypeUnknown, $f->contentType);
		$this->check('default isContentTypeHTML is false', false, $f->isContentTypeHTML());

		// Set contentType to HTML
		$f->contentType = FieldtypeTextarea::contentTypeHTML;
		$this->check('contentType HTML sets property', FieldtypeTextarea::contentTypeHTML, $f->contentType);
		$this->check('isContentTypeHTML returns true for HTML', true, $f->isContentTypeHTML());

		// Set contentType to image HTML
		$f->contentType = FieldtypeTextarea::contentTypeImageHTML;
		$this->check('isContentTypeHTML returns true for image HTML', true, $f->isContentTypeHTML());

		// Set contentType back to unknown
		$f->contentType = FieldtypeTextarea::contentTypeUnknown;
		$this->check('isContentTypeHTML returns false for unknown', false, $f->isContentTypeHTML());
	}

	protected function testInheritedProperties() {
		// placeholder inherited from InputfieldText
		$f = $this->newInputfield();
		$f->placeholder = 'Enter text';
		$this->check('placeholder property works', 'Enter text', $f->attr('placeholder'));

		// stripTags inherited
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->stripTags = true;
		$f->val('<b>Hello</b>');
		$this->check('stripTags inherited from InputfieldText', 'Hello', $f->val());

		// showCount inherited
		$f = $this->newInputfield();
		$f->showCount = InputfieldText::showCountChars;
		$this->check('showCount inherited from InputfieldText', 1, $f->getSetting('showCount'));

		// noTrim inherited
		$f = $this->newInputfield();
		$f->set('hasFieldtype', false);
		$f->attr('maxlength', 100);
		$f->noTrim = true;
		$f->val('  hello  ');
		$this->check('noTrim inherited from InputfieldText', '  hello  ', $f->val());
	}

	protected function testStripTags() {
		// stripTags removes HTML
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->stripTags = true;
		$f->val('<b>Hello</b> <script>alert(1)</script>');
		$this->check('stripTags removes HTML tags', 'Hello alert(1)', $f->val());
	}

	protected function testNoTrim() {
		// noTrim with hasFieldtype=false preserves whitespace
		$f = $this->newInputfield();
		$f->set('hasFieldtype', false);
		$f->attr('maxlength', 100);
		$f->noTrim = true;
		$f->val('  hello  ');
		$this->check('noTrim=true preserves whitespace', '  hello  ', $f->val());

		// Default trims whitespace
		$f = $this->newInputfield();
		$f->set('hasFieldtype', false);
		$f->attr('maxlength', 100);
		$f->noTrim = false;
		$f->val('  hello  ');
		$this->check('noTrim=false trims whitespace', 'hello', $f->val());
	}

	protected function testMinlengthValidation() {
		// minlength validation inherited
		$f = $this->newInputfield('test_ml_min');
		$f->attr('minlength', 10);
		$f->attr('maxlength', 0);
		$this->processInput($f, 'short');
		$errors = $f->getErrors(true);
		$this->check('minlength validation inherited', true, count($errors) > 0);
	}

	protected function testConstants() {
		$this->check('defaultRows is 5', 5, InputfieldTextarea::defaultRows);
	}
}
