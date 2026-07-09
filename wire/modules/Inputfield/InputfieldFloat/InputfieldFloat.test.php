<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldFloat module.
 *
 */
class WireTest_InputfieldFloat extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testValueSanitization();
		$this->testPrecision();
		$this->testDigitsMode();
		$this->testMinMaxRange();
		$this->testEmptyVsZero();
		$this->testInputTypeAndRender();
		$this->testScientificNotation();
		$this->testInitialValues();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'price') {
		$f = $this->wire()->modules->get('InputfieldFloat');
		$f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is text', 'text', $f->attr('type'));
		$this->check('default inputType is text', 'text', $f->inputType);
		$this->check('default precision is 2', 2, $f->precision);
		$this->check('default digits is 0', 0, $f->digits);
		$this->check('default noE is false', 0, $f->noE);
		$this->check('default step attr is blank until number render', '', $f->attr('step'));
		$this->check('default size inherited from integer', '10', $f->attr('size'));
	}

	protected function testValueSanitization() {
		$f = $this->newInputfield();

		$f->val('3.14159');
		$this->check('string float is accepted', 3.14, $f->val());

		$f->val('-42.25');
		$this->check('negative float is preserved', -42.25, $f->val());

		$f->val('12.6 apples');
		$this->check('non-numeric characters are stripped', 12.6, $f->val());

		$f->val('1,234.567');
		$this->check('comma-formatted decimal sanitizes and rounds', 1234.57, $f->val());

		$f->val('');
		$this->check('blank value remains blank', '', $f->val());
	}

	protected function testPrecision() {
		$f = $this->newInputfield();

		$f->precision = 0;
		$f->val('3.6');
		$this->check('precision 0 rounds to integer-like float', 4.0, $f->val());

		$f = $this->newInputfield();
		$f->precision = 3;
		$f->val('3.14159');
		$this->check('precision 3 rounds to three decimals', 3.142, $f->val());

		$f = $this->newInputfield();
		$f->precision = -1;
		$f->val('3.14159');
		$this->check('precision -1 disables rounding', 3.14159, $f->val());

		$f = $this->newInputfield();
		$f->precision = null;
		$f->val('3.14159');
		$this->check('precision null disables configured rounding for strings', 3.14159, $f->val());
	}

	protected function testDigitsMode() {
		$f = $this->newInputfield();
		$f->digits = 8;
		$f->precision = 2;
		$f->val('1234.5');
		$this->check('digits mode accepts numeric string', '1234.5', $f->val());
		$this->check('digits mode text render formats fixed precision', 'value="1234.50"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->digits = 8;
		$f->precision = 2;
		$f->val('1,234.567');
		$this->check('digits mode sanitizes comma-formatted decimal string', '1234.57', $f->val());

		$f = $this->newInputfield();
		$f->digits = 8;
		$html = $f->render();
		$this->check('digits mode text render adds decimal inputmode', 'inputmode="decimal"', $html, '*=');
	}

	protected function testMinMaxRange() {
		$f = $this->newInputfield();
		$f->attr('min', 1.5);
		$f->attr('max', 9.5);
		$f->val(5.25);
		$this->check('in-range float value is accepted', 5.25, $f->val());

		$f->getErrors(true);
		$f->val(10.5);
		$this->check('above max preserves previous value', 5.25, $f->val());
		$this->check('above max records error', true, count($f->getErrors(true)) > 0);

		$f->val(1.25);
		$this->check('below min preserves previous value', 5.25, $f->val());
		$this->check('below min records error', true, count($f->getErrors(true)) > 0);
	}

	protected function testEmptyVsZero() {
		$f = $this->newInputfield();
		$f->val('');
		$this->check('blank value is empty', true, $f->isEmpty());

		$f->val(0.0);
		$this->check('zero float is not empty', false, $f->isEmpty());
	}

	protected function testInputTypeAndRender() {
		$f = $this->newInputfield('price');
		$f->inputType = 'number';
		$f->precision = 2;
		$f->attr('min', 0.25);
		$f->attr('max', 99.95);
		$f->val(12.5);
		$html = $f->render();

		$this->check('inputType number sets type attr', 'number', $f->attr('type'));
		$this->check('number render includes type number', 'type="number"', $html, '*=');
		$this->check('number render includes min attr', 'min="0.25"', $html, '*=');
		$this->check('number render includes max attr', 'max="99.95"', $html, '*=');
		$this->check('number render derives step from precision', 'step=".01"', $html, '*=');
		$this->check('number render includes decimal value', 'value="12.5"', $html, '*=');

		$f = $this->newInputfield();
		$f->inputType = 'number';
		$f->precision = 3;
		$f->attr('step', '0.5');
		$this->check('explicit step is preserved', 'step="0.5"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->inputType = 'number';
		$f->precision = 2;
		$f->attr('step', 'any');
		$this->check('explicit step any is preserved', 'step="any"', $f->render(), '*=');
	}

	protected function testScientificNotation() {
		$f = $this->newInputfield();
		$this->check('hasE detects positive exponent', true, $f->hasE('1.23E3'));
		$this->check('hasE detects negative exponent', true, $f->hasE('1.23E-3'));
		$this->check('hasE rejects non-number E string', false, $f->hasE('Hello'));

		$f->precision = -1;
		$f->val(0.00000015);
		$this->check('scientific notation renders when noE false', 'value="1.5E-7"', $f->render(), '*=');

		$f->noE = true;
		$html = $f->render();
		$this->check('noE render converts exponent to decimal', 'value="0.00000015"', $html, '*=');
	}

	protected function testInitialValues() {
		$f = $this->newInputfield();
		$f->initValue = 7.25;
		$this->check('render uses initValue when value blank', 'value="7"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->defaultValue = 9.75;
		$this->check('render uses defaultValue when value blank', 'value="9"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->val(3.5);
		$f->initValue = 7.25;
		$this->check('existing value wins over initValue', 'value="3.5"', $f->render(), '*=');
	}
}
