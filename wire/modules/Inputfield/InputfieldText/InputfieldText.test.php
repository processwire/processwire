<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldText module
 *
 * Tests properties, validation (minlength, maxlength, pattern), value
 * processing (stripTags, noTrim, truncation), rendering (render, renderValue),
 * initValue, showCount constants, and autocomplete behavior.
 *
 */
class WireTest_InputfieldText extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testValueProcessing();
		$this->testStripTags();
		$this->testNoTrim();
		$this->testMinlengthValidation();
		$this->testMaxlengthValidation();
		$this->testPatternValidation();
		$this->testRender();
		$this->testRenderValue();
		$this->testInitValue();
		$this->testShowCount();
		$this->testSizeAttribute();
		$this->testRequiredAttr();
		$this->testConstants();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_input') {
		$f = $this->wire()->modules->get('InputfieldText');
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

		// Default type is text
		$this->check('default type is text', 'text', $f->attr('type'));

		// Default maxlength is 2048
		$this->check('default maxlength is 2048', 2048, $f->attr('maxlength'));

		// Default size is 0
		$this->check('default size is 0', 0, $f->attr('size'));

		// Default placeholder is empty
		$this->check('default placeholder is empty', '', $f->attr('placeholder'));

		// Default pattern is empty
		$this->check('default pattern is empty', '', $f->attr('pattern'));

		// Default minlength is 0
		$this->check('default minlength is 0', 0, $f->attr('minlength'));

		// val() getter/setter
		$f->val('Hello World');
		$this->check('val() sets value', 'Hello World', $f->val());

		// placeholder
		$f->placeholder = 'Enter text';
		$this->check('placeholder property sets attribute', 'Enter text', $f->attr('placeholder'));
	}

	protected function testValueProcessing() {
		// Value is trimmed by default
		$f = $this->newInputfield();
		$f->val('  hello  ');
		$this->check('value is trimmed by default', 'hello', $f->val());

		// Value with maxlength is truncated
		$f = $this->newInputfield();
		$f->attr('maxlength', 10);
		$f->val(str_repeat('a', 100));
		$this->check('value truncated to maxlength', 10, strlen($f->val()));

		// Value with maxlength=0 is not truncated
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->val(str_repeat('a', 5000));
		$this->check('maxlength=0 allows long values', 5000, strlen($f->val()));
	}

	protected function testStripTags() {
		// stripTags=false (default) keeps tags
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->val('<b>Hello</b>');
		$this->check('stripTags=false keeps HTML tags', true, strpos($f->val(), '<b>') !== false);

		// stripTags=true removes tags
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->stripTags = true;
		$f->val('<b>Hello</b> <script>alert(1)</script>');
		$this->check('stripTags=true removes HTML tags', 'Hello alert(1)', $f->val());
		$this->check('stripTags=true removes script tags', false, strpos($f->val(), '<') !== false);
	}

	protected function testNoTrim() {
		// noTrim=true preserves whitespace
		$f = $this->newInputfield();
		$f->attr('maxlength', 100);
		$f->noTrim = true;
		$f->val('  hello  ');
		$this->check('noTrim=true preserves whitespace', '  hello  ', $f->val());

		// noTrim=false (default) trims whitespace
		$f = $this->newInputfield();
		$f->attr('maxlength', 100);
		$f->noTrim = false;
		$f->val('  hello  ');
		$this->check('noTrim=false trims whitespace', 'hello', $f->val());
	}

	protected function testMinlengthValidation() {
		// Value shorter than minlength triggers error
		$f = $this->newInputfield();
		$f->attr('minlength', 5);
		$f->attr('maxlength', 0);
		$f->val('Hi');
		$this->processInput($f, 'Hi');
		$errors = $f->getErrors(true);
		$this->check('minlength validation triggers error', true, count($errors) > 0);
		$this->check('minlength error mentions minimum', 'minimum', $errors[0], '*=');

		// Value meeting minlength has no error
		$f = $this->newInputfield();
		$f->attr('minlength', 5);
		$f->attr('maxlength', 0);
		$f->val('Hello');
		$this->processInput($f, 'Hello');
		$this->check('minlength validation passes when met', 0, count($f->getErrors(true)));

		// Empty value with minlength but not required has no error
		$f = $this->newInputfield();
		$f->attr('minlength', 5);
		$f->attr('maxlength', 0);
		$f->val('');
		$this->processInput($f, '');
		$this->check('minlength not enforced on empty non-required field', 0, count($f->getErrors(true)));
	}

	protected function testMaxlengthValidation() {
		// Value exceeding maxlength triggers error
		$f = $this->newInputfield();
		$f->attr('maxlength', 5);
		$this->processInput($f, 'abcdefghij');
		$errors = $f->getErrors(true);
		$this->check('maxlength validation triggers error', true, count($errors) > 0);
		$this->check('maxlength error mentions maximum', 'maximum', $errors[0], '*=');

		// Value within maxlength has no error
		$f = $this->newInputfield();
		$f->attr('maxlength', 10);
		$this->processInput($f, 'hello');
		$this->check('maxlength validation passes when within limit', 0, count($f->getErrors(true)));
	}

	protected function testPatternValidation() {
		// Value not matching pattern triggers error
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->pattern = '^[a-z]+$';
		$f->val('abc123');
		$this->processInput($f, 'abc123');
		$errors = $f->getErrors(true);
		$this->check('pattern validation triggers error', true, count($errors) > 0);
		$this->check('pattern error mentions pattern', 'pattern', $errors[0], '*=');

		// Value matching pattern has no error
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->pattern = '^[a-z]+$';
		$f->val('abc');
		$this->processInput($f, 'abc');
		$this->check('pattern validation passes when matching', 0, count($f->getErrors(true)));

		// Pattern with # delimiter is escaped
		$f = $this->newInputfield();
		$f->attr('maxlength', 0);
		$f->pattern = '^[0-9]+#[0-9]+$';
		$f->val('123#456');
		$this->processInput($f, '123#456');
		$this->check('pattern with # delimiter is escaped', 0, count($f->getErrors(true)));
	}

	protected function testRender() {
		$f = $this->newInputfield('my_text');
		$f->val('Hello World');
		$html = $f->render();

		// render() returns input element
		$this->check('render() returns <input element', true, strpos($html, '<input') !== false);

		// render() includes name attribute
		$this->check('render() includes name attribute', 'name="my_text"', $html, '*=');

		// render() includes value
		$this->check('render() includes value', 'Hello World', $html, '*=');

		// render() includes type=text
		$this->check('render() includes type=text', 'type="text"', $html, '*=');

		// render() includes maxlength
		$this->check('render() includes maxlength', 'maxlength', $html, '*=');
	}

	protected function testRenderValue() {
		// renderValue with content
		$f = $this->newInputfield();
		$f->val('Hello World');
		$html = $f->renderValue();
		$this->check('renderValue() returns <p> element', '<p>', $html, '^=');
		$this->check('renderValue() includes value', 'Hello World', $html, '*=');

		// renderValue with empty value returns empty string
		$f = $this->newInputfield();
		$f->val('');
		$this->check('renderValue() empty returns empty string', '', $f->renderValue());
	}

	protected function testInitValue() {
		// initValue shows when value is empty
		$f = $this->newInputfield('init_test');
		$f->attr('value', '');
		$f->initValue = 'default text';
		$html = $f->render();
		$this->check('initValue appears when value is empty', 'default text', $html, '*=');

		// initValue does not override actual value
		$f = $this->newInputfield('init_test2');
		$f->attr('value', 'actual value');
		$f->initValue = 'default text';
		$html = $f->render();
		$this->check('initValue does not override actual value', 'actual value', $html, '*=');
		$this->check('initValue not in output when value set', false, strpos($html, 'default text') !== false);
	}

	protected function testShowCount() {
		// showCount defaults to 0 (none)
		$f = $this->newInputfield();
		$this->check('showCount default is 0', 0, $f->getSetting('showCount'));

		// Set showCount to character counter
		$f->showCount = InputfieldText::showCountChars;
		$this->check('showCountChars sets value 1', 1, $f->getSetting('showCount'));

		// Set showCount to word counter
		$f->showCount = InputfieldText::showCountWords;
		$this->check('showCountWords sets value 2', 2, $f->getSetting('showCount'));
	}

	protected function testSizeAttribute() {
		// size=0 renders with InputfieldMaxWidth class
		$f = $this->newInputfield();
		$f->attr('size', 0);
		$html = $f->render();
		$this->check('size=0 adds InputfieldMaxWidth class', 'InputfieldMaxWidth', $html, '*=');

		// size>0 does not add InputfieldMaxWidth
		$f = $this->newInputfield();
		$f->attr('size', 20);
		$html = $f->render();
		$this->check('size>0 omits InputfieldMaxWidth class', false, strpos($html, 'InputfieldMaxWidth') !== false);
		$this->check('size>0 includes size attribute', 'size="20"', $html, '*=');
	}

	protected function testRequiredAttr() {
		// requiredAttr default is false/0
		$f = $this->newInputfield();
		$this->check('requiredAttr default is 0', 0, $f->getSetting('requiredAttr'));

		// Set requiredAttr
		$f->requiredAttr = 1;
		$this->check('requiredAttr can be set', 1, $f->getSetting('requiredAttr'));
	}

	protected function testConstants() {
		$this->check('defaultMaxlength is 2048', 2048, InputfieldText::defaultMaxlength);
		$this->check('showCountNone is 0', 0, InputfieldText::showCountNone);
		$this->check('showCountChars is 1', 1, InputfieldText::showCountChars);
		$this->check('showCountWords is 2', 2, InputfieldText::showCountWords);
	}
}
