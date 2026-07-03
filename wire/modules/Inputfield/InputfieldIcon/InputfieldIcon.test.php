<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldIcon module.
 *
 */
class WireTest_InputfieldIcon extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testValueNormalization();
		$this->testProcessInput();
		$this->testRender();
	}

	protected function newInputfield($name = 'test_icon') {
		$f = $this->wire()->modules->get('InputfieldIcon');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldIcon $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldIcon', true, $f instanceof InputfieldIcon);
		$this->check('extends Inputfield', true, $f instanceof Inputfield);
		$this->check('prefix constant is fa dash', 'fa-', InputfieldIcon::prefix);
		$this->check('prefixValue defaults true', true, $f->prefixValue);
		$this->check('init sets header icon', 'puzzle-piece', $f->icon);
	}

	protected function testValueNormalization() {
		$f = $this->newInputfield();

		$f->val('star');
		$this->check('value without prefix normalizes to prefixed icon', 'fa-star', $f->val());

		$f->val('fa-bolt');
		$this->check('value with prefix remains prefixed icon', 'fa-bolt', $f->val());

		$f->val('not-an-icon');
		$this->check('invalid icon value is cleared', '', $f->val());

		$f->val('fa-user');
		$f->prefixValue = false;
		$this->check('prefixValue false returns icon without prefix', 'user', $f->val());

		$f->prefixValue = true;
		$this->check('prefixValue true restores prefixed return value', 'fa-user', $f->val());
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('feature_icon');

		$this->processInput($f, 'fa-star');
		$this->check('processInput stores valid submitted icon', 'fa-star', $f->val());

		$this->processInput($f, 'bogus');
		$this->check('processInput clears invalid submitted icon', '', $f->val());
	}

	protected function testRender() {
		$f = $this->newInputfield('feature_icon');
		$f->val('fa-star');
		$html = $f->render();

		$this->check('render outputs select element', '<select ', $html, '*=');
		$this->check('render includes selected icon option', "value='fa-star'", $html, '*=');
		$this->check('render marks selected icon', "selected='selected'", $html, '*=');
		$this->check('render includes icon search input', 'InputfieldIconSearch', $html, '*=');
		$this->check('render includes icon grid container', 'InputfieldIconAll', $html, '*=');
		$this->check('render sets header icon to selected value', 'fa-star', $f->icon);

		$f = $this->newInputfield('feature_icon');
		$f->required = false;
		$this->check('non-required render includes empty option', "<option value=''></option>", $f->render(), '*=');

		$f = $this->newInputfield('feature_icon');
		$f->required = true;
		$this->check('required render omits empty option', false, strpos($f->render(), "<option value=''></option>") !== false);
	}
}

