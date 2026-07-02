<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldSelectMultiple modules.
 *
 */
class WireTest_InputfieldSelectMultiple extends WireTest {
	
	public function init() {
		// nothing to set up
	}
	
	public function execute() {
		$f = $this->newSelectMultiple('colors');
		$f->addOptions(array('red' => 'Red', 'green' => 'Green', 'blue' => 'Blue'));
		$f->val(array('blue', 'red'));
		
		$this->check('select multiple has multiple attr', 'multiple', $f->attr('multiple'));
		$this->check('select multiple default size', InputfieldSelectMultiple::defaultSize, $f->attr('size'));
		$this->check('select multiple stores array value', array('blue', 'red'), $f->val());
		$this->check('select multiple selected value', true, $f->isOptionSelected('blue'));
		
		$this->processInput($f, array('colors' => array('green', 'bogus', 'red')));
		$this->check('select multiple filters invalid input', array('green', 'red'), array_values($f->val()));
		
		$html = $f->render();
		$this->check('select multiple renders multiple attr', 'multiple="multiple"', $html, '*=');
		$this->check('select multiple renderValue returns list', '<ul', $f->renderValue(), '*=');
	}
	
	public function finish() {
		// nothing to clean up
	}
	
	protected function newSelectMultiple($name = 'test_select_multiple') {
		$f = $this->wire()->modules->get('InputfieldSelectMultiple');
		$f->attr('name', $name);
		return $f;
	}
	
	protected function processInput(InputfieldSelect $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}
}
