<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldCheckboxes module.
 *
 */
class WireTest_InputfieldCheckboxes extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testArrayValuesAndInput();
		$this->testRender();
		$this->testLayoutOptions();
		$this->testTableMode();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_checkboxes') {
		$f = $this->wire()->modules->get('InputfieldCheckboxes');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldCheckboxes $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function addColorOptions(InputfieldCheckboxes $f) {
		$f->addOptions(array('red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'));
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default table is false', false, $f->table);
		$this->check('default thead is blank', '', $f->thead);
		$this->check('default optionColumns is 0', 0, $f->optionColumns);
		$this->check('default optionWidth is blank', '', $f->optionWidth);
		$this->check('checkboxes clears select-multiple size attr', null, $f->attr('size'));
		$this->check('checkboxes implements array value interface', true, $f instanceof InputfieldHasArrayValue);
	}

	protected function testArrayValuesAndInput() {
		$f = $this->addColorOptions($this->newInputfield('colors'));
		$f->val(array('red', 'blue'));

		$this->check('val() stores array values', array('red', 'blue'), $f->val());
		$this->check('selected value detected', true, $f->isOptionSelected('red'));
		$this->check('unselected value detected', false, $f->isOptionSelected('green'));

		$this->processInput($f, array('colors' => array('green', 'bogus', 'red')));
		$this->check('processInput filters invalid values', array('green', 'red'), array_values($f->val()));

		$this->processInput($f, array());
		$this->check('processInput omitted value returns empty array', array(), $f->val());
	}

	protected function testRender() {
		$f = $this->addColorOptions($this->newInputfield('colors'));
		$f->val(array('red', 'blue'));
		$html = $f->render();

		$this->check('render returns checkbox inputs', "type='checkbox'", $html, '*=');
		$this->check('render uses array field name', "name='colors[]'", $html, '*=');
		$this->check('render marks selected values checked', "checked='checked'", $html, '*=');
		$this->check('render includes stacked class by default', 'InputfieldCheckboxesStacked', $html, '*=');
		$this->check('render includes option label', 'Green', $html, '*=');
	}

	protected function testLayoutOptions() {
		$f = $this->addColorOptions($this->newInputfield());

		$f->optionColumns = -5;
		$this->check('optionColumns clamps below zero', 0, $f->optionColumns);

		$f->optionColumns = 20;
		$this->check('optionColumns clamps above ten', 10, $f->optionColumns);
		$this->check('columns render class appears', 'InputfieldCheckboxesColumns', $f->render(), '*=');

		$f = $this->addColorOptions($this->newInputfield());
		$f->optionColumns = 1;
		$this->check('optionColumns 1 renders floated class', 'InputfieldCheckboxesFloated', $f->render(), '*=');

		$f = $this->addColorOptions($this->newInputfield());
		$f->optionWidth = '150px';
		$html = $f->render();
		$this->check('optionWidth takes precedence with width class', 'InputfieldCheckboxesWidth', $html, '*=');
		$this->check('optionWidth renders style width', "style='width:150px'", $html, '*=');

		$options = array('short' => 'Short', 'long' => 'Longer label');
		$this->check('optionWidth 1 auto calculates ch unit', '14ch', $f->getOptionWidthCSS('1', $options));
	}

	protected function testTableMode() {
		$f = $this->newInputfield('products');
		$f->table = true;
		$f->thead = 'Product|SKU';
		$f->addOption('a', 'Widget|WDG-001');
		$f->val(array('a'));
		$html = $f->render();

		$this->check('table mode renders table markup', '<table', $html, '*=');
		$this->check('table mode renders heading', 'Product', $html, '*=');
		$this->check('table mode renders option column', 'WDG-001', $html, '*=');
	}
}
