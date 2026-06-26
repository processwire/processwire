<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldCheckbox module.
 *
 */
class WireTest_InputfieldCheckbox extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testCheckedState();
		$this->testValueBehavior();
		$this->testAutocheck();
		$this->testProcessInput();
		$this->testRender();
		$this->testRenderValue();
		$this->testLabelOptions();
		$this->testConstants();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_checkbox') {
		$f = $this->wire()->modules->get('InputfieldCheckbox');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldCheckbox $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default checkedValue is 1', 1, $f->checkedValue);
		$this->check('default uncheckedValue is blank', '', $f->uncheckedValue);
		$this->check('default autocheck is disabled', 0, $f->autocheck);
		$this->check('default label2 is blank', '', $f->label2);
		$this->check('default checkboxLabel is blank', '', $f->checkboxLabel);
		$this->check('default checkboxOnly is false', false, $f->checkboxOnly);
		$this->check('default labelAttrs is array', true, is_array($f->labelAttrs));
	}

	protected function testCheckedState() {
		$f = $this->newInputfield();

		$this->check('new checkbox is unchecked', false, $f->checked());
		$this->check('unchecked checkbox is empty', true, $f->isEmpty());

		$f->checked(true);
		$this->check('checked(true) checks checkbox', true, $f->checked());
		$this->check('checked checkbox is not empty', false, $f->isEmpty());
		$this->check('checked(true) sets checked attr', 'checked', $f->attr('checked'));

		$f->checked(false);
		$this->check('checked(false) unchecks checkbox', false, $f->checked());
		$this->check('checked(false) removes checked attr', null, $f->attr('checked'));

		$f->attr('checked', 'checked');
		$this->check('checked attr controls checked state', true, $f->checked());
		$f->removeAttr('checked');
		$this->check('remove checked attr unchecks', false, $f->checked());
	}

	protected function testValueBehavior() {
		$f = $this->newInputfield();
		$f->attr('value', 'yes');

		$this->check('value attr sets submitted checkedValue', 'yes', $f->checkedValue);
		$this->check('value attr does not check by default', false, $f->checked());
		$this->check('value attr remains available', 'yes', $f->attr('value'));

		$f->checkedValue = 'active';
		$f->uncheckedValue = 'inactive';
		$this->check('checkedValue property changes checked value', 'active', $f->checkedValue);
		$this->check('uncheckedValue property changes unchecked value', 'inactive', $f->uncheckedValue);
	}

	protected function testAutocheck() {
		$f = $this->newInputfield();
		$f->autocheck = 1;
		$f->val('yes');

		$this->check('autocheck val() checks checkbox', true, $f->checked());
		$this->check('autocheck val() stores checkedValue', 'yes', $f->checkedValue);

		$f = $this->newInputfield();
		$f->autocheck = 0;
		$f->val('yes');
		$this->check('without autocheck val() does not check checkbox', false, $f->checked());
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('notify');
		$f->checkedValue = 'active';
		$f->uncheckedValue = 'inactive';

		$this->processInput($f, array('notify' => 'active'));
		$this->check('processInput checked sets checked state', true, $f->checked());
		$this->check('processInput checked sets checkedValue', 'active', $f->val());

		$this->processInput($f, array());
		$this->check('processInput omitted unchecks checkbox', false, $f->checked());
		$this->check('processInput omitted sets uncheckedValue', 'inactive', $f->val());

		$this->processInput($f, array('notify' => 'anything'));
		$this->check('processInput non-empty submitted value checks', true, $f->checked());
		$this->check('processInput uses configured checkedValue', 'active', $f->val());
	}

	protected function testRender() {
		$f = $this->newInputfield('agree');
		$f->label = 'Terms';
		$f->label2 = 'I agree';
		$f->checkedValue = 'yes';
		$f->checked(true);
		$f->labelAttrs = array('class' => 'agree-label');
		$html = $f->render();

		$this->check('render returns checkbox input', '<input type=\'checkbox\'', $html, '*=');
		$this->check('render includes name attribute', 'name="agree"', $html, '*=');
		$this->check('render includes checked attribute', 'checked="checked"', $html, '*=');
		$this->check('render uses checkedValue as value attribute', 'value="yes"', $html, '*=');
		$this->check('render includes label2 text', 'I agree', $html, '*=');
		$this->check('render includes labelAttrs', 'class="agree-label"', $html, '*=');
	}

	protected function testRenderValue() {
		$f = $this->newInputfield();
		$f->checkedValue = 'yes';
		$f->uncheckedValue = 'no';
		$f->checked(true);
		$f->val('yes');
		$html = $f->renderValue();

		$this->check('renderValue checked includes checkedValue', 'yes', $html, '*=');

		$f->checked(false);
		$f->val('no');
		$html = $f->renderValue();
		$this->check('renderValue unchecked includes uncheckedValue', 'no', $html, '*=');
	}

	protected function testLabelOptions() {
		$f = $this->newInputfield();
		$f->label = 'Main label';
		$html = $f->render();
		$this->check('render falls back to main label', 'Main label', $html, '*=');

		$f = $this->newInputfield();
		$f->label = 'Main label';
		$f->checkboxLabel = 'Configured label';
		$f->label2 = 'API label';
		$html = $f->render();
		$this->check('checkboxLabel has priority over label2', 'Configured label', $html, '*=');
		$this->check('checkboxLabel suppresses label2', false, strpos($html, 'API label') !== false);

		$f = $this->newInputfield();
		$f->label = 'Main label';
		$f->checkedValue = 'Checked label';
		$html = $f->render();
		$this->check('custom checkedValue can provide label', 'Checked label', $html, '*=');

		$f = $this->newInputfield();
		$f->label = 'Main label';
		$f->label2 = 'Side label';
		$f->checkboxOnly = true;
		$html = $f->render();
		$this->check('checkboxOnly suppresses side label', false, strpos($html, 'Side label') !== false);
		$this->check('checkboxOnly still renders checkbox', '<input type=\'checkbox\'', $html, '*=');
	}

	protected function testConstants() {
		$this->check('checkedValueDefault constant is 1', 1, InputfieldCheckbox::checkedValueDefault);
		$this->check('uncheckedValueDefault constant is blank', '', InputfieldCheckbox::uncheckedValueDefault);
	}
}
