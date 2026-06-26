<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldRadios module.
 *
 */
class WireTest_InputfieldRadios extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testScalarValuesAndInput();
		$this->testRender();
		$this->testLayoutOptions();
		$this->testConfigRenderOptions();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_radios') {
		$f = $this->wire()->modules->get('InputfieldRadios');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldRadios $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function addSizeOptions(InputfieldRadios $f) {
		$f->addOptions(array('s' => 'Small', 'm' => 'Medium', 'l' => 'Large'));
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default optionColumns is 0', 0, $f->optionColumns);
		$this->check('default optionWidth is blank', '', $f->optionWidth);
		$this->check('radios do not implement array value interface', false, $f instanceof InputfieldHasArrayValue);
	}

	protected function testScalarValuesAndInput() {
		$f = $this->addSizeOptions($this->newInputfield('size'));
		$f->val('m');

		$this->check('val() stores scalar value', 'm', $f->val());
		$this->check('selected value detected', true, $f->isOptionSelected('m'));
		$this->check('unselected value detected', false, $f->isOptionSelected('s'));

		$this->processInput($f, array('size' => 'l'));
		$this->check('processInput accepts valid option', 'l', $f->val());

		$this->processInput($f, array('size' => 'bogus'));
		$this->check('processInput rejects invalid option', null, $f->val());
	}

	protected function testRender() {
		$f = $this->addSizeOptions($this->newInputfield('size'));
		$f->val('m');
		$html = $f->render();

		$this->check('render returns radio inputs', "type='radio'", $html, '*=');
		$this->check('render uses scalar field name', "name='size'", $html, '*=');
		$this->check('render marks selected value checked', "checked='checked'", $html, '*=');
		$this->check('render includes stacked class by default', 'InputfieldRadiosStacked', $html, '*=');
		$this->check('render includes option label', 'Medium', $html, '*=');
	}

	protected function testLayoutOptions() {
		$f = $this->addSizeOptions($this->newInputfield());

		$f->optionColumns = -1;
		$this->check('optionColumns clamps below zero', 0, $f->optionColumns);

		$f->optionColumns = 20;
		$this->check('optionColumns clamps above ten', 10, $f->optionColumns);
		$this->check('columns render falls back to floated when more columns than options', 'InputfieldRadiosFloated', $f->render(), '*=');

		$f = $this->addSizeOptions($this->newInputfield());
		$f->optionColumns = 3;
		$this->check('optionColumns 3 renders columns class', 'InputfieldRadiosColumns', $f->render(), '*=');

		$f = $this->addSizeOptions($this->newInputfield());
		$f->optionWidth = '12em';
		$html = $f->render();
		$this->check('optionWidth renders width class', 'InputfieldRadiosWidth', $html, '*=');
		$this->check('optionWidth renders style width', "style='width:12em'", $html, '*=');
	}

	protected function testConfigRenderOptions() {
		$config = $this->wire()->config;
		$old = $config->get('InputfieldRadios');

		$config->InputfieldRadios = array('wbr' => true, 'noSelectLabels' => false);
		$f = $this->newInputfield('choice');
		$f->addOption('long', 'Long Label');
		$html = $f->render();
		$this->check('wbr config inserts word break', '<wbr>', $html, '*=');
		$this->check('noSelectLabels false omits pw-no-select class', false, strpos($html, 'pw-no-select') !== false);

		$config->InputfieldRadios = $old;
	}
}
