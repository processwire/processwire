<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldSelect and InputfieldSelectMultiple modules.
 *
 */
class WireTest_InputfieldSelect extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicOptions();
		$this->testOptionsString();
		$this->testOptionQueriesAndAttributes();
		$this->testInsertReplaceRemove();
		$this->testValueHandling();
		$this->testProcessInput();
		$this->testRender();
		$this->testRenderValue();
		$this->testSelectMultiple();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newSelect($name = 'test_select') {
		$f = $this->wire()->modules->get('InputfieldSelect');
		$f->attr('name', $name);
		return $f;
	}

	protected function newSelectMultiple($name = 'test_select_multiple') {
		$f = $this->wire()->modules->get('InputfieldSelectMultiple');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldSelect $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicOptions() {
		$f = $this->newSelect();
		$f->addOption('red', 'Red');
		$f->addOption('green');
		$f->addOptions(array('blue' => 'Blue', 'orange' => 'Orange'));
		$f->addOptions(array('Purple', 'Yellow'), false);

		$options = $f->getOptions();
		$this->check('addOption stores explicit label', 'Red', $options['red']);
		$this->check('addOption uses value as missing label', 'green', $options['green']);
		$this->check('addOptions assoc stores label', 'Blue', $options['blue']);
		$this->check('addOptions non-assoc uses value as key', 'Purple', $options['Purple']);

		$f->setOptions(array('a' => 'Option A', 'b' => 'Option B'));
		$this->check('setOptions replaces previous options', array('a' => 'Option A', 'b' => 'Option B'), $f->getOptions());

		$f->options = "x=X-ray\ny=Yankee";
		$this->check('options string property adds option x', 'X-ray', $f->optionLabel('x'));
		$this->check('options string property adds option y', 'Yankee', $f->optionLabel('y'));
	}

	protected function testOptionsString() {
		$f = $this->newSelect();
		$f->addOptionsString("=\nr=Red\n+g=Green\n++plus=Plus\nb==Blue\n---\ndisabled:tbd=To be decided\nWarm colors\n   o=Orange\n   y=Yellow");

		$options = $f->getOptions();
		$this->check('options string adds blank option', true, array_key_exists('', $options));
		$this->check('options string parses value=label', 'Red', $options['r']);
		$this->check('options string selects + option', true, $f->isOptionSelected('g'));
		$this->check('options string escapes leading plus', true, $f->isOption('plus'));
		$this->check('options string keeps escaped equals in value', true, $f->isOption('b=Blue'));
		$this->check('options string marks disabled option', true, $f->isOptionDisabled('tbd'));
		$this->check('options string separator is not option', false, $f->isOption('---'));
		$this->check('options string creates optgroup option', true, $f->isOption('o'));
		$this->check('options string creates optgroup label', 'Orange', $f->optionLabel('o'));
	}

	protected function testOptionQueriesAndAttributes() {
		$f = $this->newSelect();
		$f->addOption('red', 'Red', array('disabled' => 'disabled'));
		$f->addOption('green', 'Green');

		$this->check('isOption sees existing option', true, $f->isOption('red'));
		$this->check('isOption rejects missing option', false, $f->isOption('blue'));
		$this->check('isOptionDisabled sees disabled attr', true, $f->isOptionDisabled('red'));

		$this->check('optionLabel gets label', 'Red', $f->optionLabel('red'));
		$f->optionLabel('red', 'Dark red');
		$this->check('optionLabel sets label', 'Dark red', $f->optionLabel('red'));
		$this->check('optionLabel missing returns false', false, $f->optionLabel('missing'));

		$f->optionAttributes('green', array('class' => 'ok'));
		$this->check('optionAttributes sets class', 'ok', $f->getOptionAttributes('green')['class']);
		$f->optionAttributes('green', array('data-test' => '1'), true);
		$attrs = $f->getOptionAttributes('green');
		$this->check('optionAttributes append keeps class', 'ok', $attrs['class']);
		$this->check('optionAttributes append adds data attr', '1', $attrs['data-test']);
		$this->check('option attributes string renders class', 'class="ok"', $f->getOptionAttributesString('green'), '*=');
	}

	protected function testInsertReplaceRemove() {
		$f = $this->newSelect();
		$f->setOptions(array('b' => 'B', 'd' => 'D'));
		$f->insertOptionsBefore(array('a' => 'A'), 'b');
		$f->insertOptionsAfter(array('c' => 'C'), 'b');
		$this->check('insert before/after preserves order', array('a', 'b', 'c', 'd'), array_keys($f->getOptions()));

		$f->setOptionAttributes('c', array('data-old' => '1'));
		$this->check('replaceOption returns true when found', true, $f->replaceOption('c', 'cc', 'CC'));
		$this->check('replaceOption replaces value', true, $f->isOption('cc'));
		$this->check('replaceOption removes old value', false, $f->isOption('c'));
		$this->check('replaceOption preserves attributes', '1', $f->getOptionAttributes('cc')['data-old']);
		$this->check('replaceOption returns false when missing', false, $f->replaceOption('missing', 'x'));

		$f->removeOption('cc');
		$this->check('removeOption removes option', false, $f->isOption('cc'));
	}

	protected function testValueHandling() {
		$f = $this->newSelect();
		$f->addOptions(array('0' => 'Zero', '1' => 'One'));

		$this->check('empty select is empty', true, $f->isEmpty());
		$f->val('0');
		$this->check('zero value is not empty when option exists', false, $f->isEmpty());
		$this->check('isOptionSelected detects selected zero', true, $f->isOptionSelected('0'));

		$f = $this->newSelect();
		$f->valueAddOption = true;
		$f->val('purple');
		$this->check('valueAddOption adds API-set value', true, $f->isOption('purple'));
		$this->check('valueAddOption selects API-set value', true, $f->isOptionSelected('purple'));

		$f = $this->newSelect();
		$f->required = true;
		$f->defaultValue = 'green';
		$f->addOptions(array('green' => 'Green'));
		$f->render();
		$this->check('required render applies defaultValue', 'green', $f->val());
	}

	protected function testProcessInput() {
		$f = $this->newSelect('color');
		$f->addOptions(array('red' => 'Red', 'green' => 'Green'));
		$this->processInput($f, array('color' => 'green'));
		$this->check('processInput accepts valid option', 'green', $f->val());

		$this->processInput($f, array('color' => 'purple'));
		$this->check('processInput rejects invalid option', null, $f->val());

		$f->valueAddOption = true;
		$this->processInput($f, array('color' => 'purple'));
		$this->check('processInput does not valueAddOption user input', false, $f->isOption('purple'));
		$this->check('processInput still rejects invalid user input', null, $f->val());

		$f = $this->newSelect('color');
		$f->required = true;
		$f->defaultValue = 'red';
		$f->addOptions(array('red' => 'Red'));
		$this->processInput($f, array('color' => ''));
		$this->check('processInput applies required defaultValue', 'red', $f->val());
	}

	protected function testRender() {
		$f = $this->newSelect('color');
		$f->addOptions(array('red' => 'Red', 'green' => 'Green'));
		$f->val('green');
		$html = $f->render();

		$this->check('render returns select element', '<select', $html, '*=');
		$this->check('render includes name attribute', 'name="color"', $html, '*=');
		$this->check('render includes selected option', "value='green'", $html, '*=');
		$this->check('render marks selected option', "selected='selected'", $html, '*=');
		$this->check('render adds blank first option for optional select', "value=''", $html, '*=');

		$f->required = true;
		$html = $f->render();
		$this->check('required selected render does not add blank option', false, strpos($html, "value=''") !== false);
	}

	protected function testRenderValue() {
		$f = $this->newSelect();
		$f->addOptions(array('red' => 'Red', 'green' => 'Green'));
		$f->val('green');
		$html = $f->renderValue();
		$this->check('renderValue returns selected label', '<p>Green</p>', $html);
	}

	protected function testSelectMultiple() {
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
}
