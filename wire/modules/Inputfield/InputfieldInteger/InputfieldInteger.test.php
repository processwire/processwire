<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldInteger module.
 *
 */
class WireTest_InputfieldInteger extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testValueSanitization();
		$this->testMinMaxRange();
		$this->testEmptyVsZero();
		$this->testInputTypeAndRender();
		$this->testInitialValues();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'quantity') {
		$f = $this->wire()->modules->get('InputfieldInteger');
		$f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is text', 'text', $f->attr('type'));
		$this->check('default inputType is text', 'text', $f->inputType);
		$this->check('default min is blank', '', $f->attr('min'));
		$this->check('default max is blank', '', $f->attr('max'));
		$this->check('default step is blank', '', $f->attr('step'));
		$this->check('default size is 10', '10', $f->attr('size'));
		$this->check('default placeholder is blank', '', $f->attr('placeholder'));
		$this->check('default initValue is blank', '', $f->initValue);
		$this->check('default defaultValue is blank', '', $f->defaultValue);
	}

	protected function testValueSanitization() {
		$f = $this->newInputfield();

		$f->val('42');
		$this->check('numeric string becomes integer', 42, $f->val());

		$f->val('-42');
		$this->check('negative value is preserved', -42, $f->val());

		$f->val('12 apples');
		$this->check('non-digit characters are stripped', 12, $f->val());

		$f->val('12.6');
		$this->check('decimal value rounds to nearest integer', 13, $f->val());

		$f->val('1,234.7');
		$this->check('comma-formatted decimal rounds to nearest integer', 1235, $f->val());

		$f->val('');
		$this->check('blank value remains blank', '', $f->val());
	}

	protected function testMinMaxRange() {
		$f = $this->newInputfield();
		$f->attr('min', 10);
		$f->attr('max', 20);
		$f->val(15);
		$this->check('in-range value is accepted', 15, $f->val());

		$f->getErrors(true);
		$f->val(25);
		$this->check('above max preserves previous value', 15, $f->val());
		$this->check('above max records error', true, count($f->getErrors(true)) > 0);

		$f->val(5);
		$this->check('below min preserves previous value', 15, $f->val());
		$this->check('below min records error', true, count($f->getErrors(true)) > 0);

		$f->attr('min', '-2.5');
		$f->attr('max', '99');
		$this->check('min accepts float-like value', -2.5, $f->attr('min'));
		$this->check('max accepts integer-like value', 99, $f->attr('max'));
	}

	protected function testEmptyVsZero() {
		$f = $this->newInputfield();
		$f->val('');
		$this->check('blank value is empty', true, $f->isEmpty());

		$f->val(0);
		$this->check('zero value is not empty', false, $f->isEmpty());
	}

	protected function testInputTypeAndRender() {
		$f = $this->newInputfield('qty');
		$f->inputType = 'number';
		$f->attr('min', 1);
		$f->attr('max', 10);
		$f->attr('step', 2);
		$f->val(5);
		$html = $f->render();

		$this->check('inputType number sets type attr', 'number', $f->attr('type'));
		$this->check('number render includes type number', 'type="number"', $html, '*=');
		$this->check('number render includes min attr', 'min="1"', $html, '*=');
		$this->check('number render includes max attr', 'max="10"', $html, '*=');
		$this->check('number render includes step attr', 'step="2"', $html, '*=');
		$this->check('number render includes value', 'value="5"', $html, '*=');

		$f = $this->newInputfield('qty');
		$f->attr('min', 1);
		$f->attr('max', 10);
		$f->attr('step', 2);
		$html = $f->render();
		$this->check('text render omits min attr', false, strpos($html, 'min=') !== false);
		$this->check('text render omits max attr', false, strpos($html, 'max=') !== false);
		$this->check('text render omits step attr', false, strpos($html, 'step=') !== false);

		$f = $this->newInputfield('qty');
		$f->attr('size', 0);
		$html = $f->render();
		$this->check('size 0 omits size attr', false, strpos($html, 'size=') !== false);
		$this->check('size 0 adds max width class', 'InputfieldMaxWidth', $html, '*=');
	}

	protected function testInitialValues() {
		$f = $this->newInputfield();
		$f->initValue = 7;
		$this->check('render uses initValue when value blank', 'value="7"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->defaultValue = 9;
		$this->check('render uses defaultValue when value blank', 'value="9"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->val(3);
		$f->initValue = 7;
		$this->check('existing value wins over initValue', 'value="3"', $f->render(), '*=');
	}
}
