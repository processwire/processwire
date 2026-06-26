<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldSubmit module.
 *
 */
class WireTest_InputfieldSubmit extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testFluentClassMethods();
		$this->testRender();
		$this->testProcessInput();
		$this->testDropdownActions();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_submit') {
		$f = $this->wire()->modules->get('InputfieldSubmit');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldSubmit $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is submit', 'submit', $f->attr('type'));
		$this->check('default value is Submit', 'Submit', $f->attr('value'));
		$this->check('default submitValue is false', false, $f->submitValue);
		$this->check('default text is blank', '', $f->text);
		$this->check('default html is blank', '', $f->html);
		$this->check('default textClass is ui-button-text', 'ui-button-text', $f->textClass);
		$this->check('default header is false', false, $f->header);
		$this->check('default secondary is false', false, $f->secondary);
		$this->check('default small is false', false, $f->small);
		$this->check('default dropdownInputName', '_action_value', $f->dropdownInputName);
		$this->check('default dropdownSubmit is true', true, $f->dropdownSubmit);
		$this->check('default dropdownRequired is false', false, $f->dropdownRequired);
	}

	protected function testFluentClassMethods() {
		$f = $this->newInputfield();

		$this->check('showInHeader() returns same instance', true, $f === $f->showInHeader());
		$this->check('showInHeader() sets header property', true, $f->header);
		$this->check('showInHeader(false) returns same instance', true, $f === $f->showInHeader(false));
		$this->check('showInHeader(false) clears header property', false, $f->header);

		$this->check('setSecondary() returns same instance', true, $f === $f->setSecondary());
		$this->check('setSecondary() sets secondary property', true, $f->secondary);
		$f->secondary = false;
		$this->check('secondary property clears class', false, $f->secondary);

		$this->check('setSmall() returns same instance', true, $f === $f->setSmall());
		$this->check('setSmall() sets small property', true, $f->small);
		$f->setSmall(false);
		$this->check('setSmall(false) clears small property', false, $f->small);
	}

	protected function testRender() {
		$f = $this->newInputfield('save');
		$f->attr('value', 'Save');
		$html = $f->render();

		$this->check('render returns button element', '<button', $html, '*=');
		$this->check('render includes submit type', 'type="submit"', $html, '*=');
		$this->check('render includes name attribute', 'name="save"', $html, '*=');
		$this->check('render includes value attribute', 'value="Save"', $html, '*=');
		$this->check('render displays value text', 'Save', $html, '*=');
		$this->check('render wraps label in textClass span', "class='ui-button-text'", $html, '*=');

		$f = $this->newInputfield();
		$f->attr('value', 'Save');
		$f->text = 'Save changes';
		$html = $f->render();
		$this->check('text property overrides display text', 'Save changes', $html, '*=');
		$this->check('text property does not change submitted value', 'value="Save"', $html, '*=');

		$f = $this->newInputfield();
		$f->attr('value', 'Save');
		$f->text = 'Save changes';
		$f->html = '<strong>Save now</strong>';
		$html = $f->render();
		$this->check('html property overrides text and value for display', '<strong>Save now</strong>', $html, '*=');

		$f = $this->newInputfield();
		$f->icon = 'save';
		$this->check('icon renders font awesome markup', 'fa-save', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->textClass = '';
		$this->check('blank textClass omits span wrapper', false, strpos($f->render(), 'ui-button-text') !== false);

		$f = $this->newInputfield();
		$f->setSmall();
		$this->check('small button wraps output in small tag', '<small><button', $f->render(), '*=');
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('save');
		$f->attr('value', 'Save');

		$this->processInput($f, array());
		$this->check('processInput not clicked keeps submitValue false', false, $f->submitValue);

		$this->processInput($f, array('save' => 'Cancel'));
		$this->check('processInput wrong value keeps submitValue false', false, $f->submitValue);

		$this->processInput($f, array('save' => 'Save'));
		$this->check('processInput clicked stores submitted value', 'Save', $f->submitValue);
	}

	protected function testDropdownActions() {
		$f = $this->newInputfield('submit');
		$f->attr('value', 'Save');
		$f->addActionValue('save_close', 'Save and Close', 'times-circle');
		$f->addActionLink('/admin/page/list/', 'Cancel', 'ban');
		$html = $f->render();

		$this->check('dropdown renders hidden input for action values', "name='_action_value'", $html, '*=');
		$this->check('dropdown renders action value', "data-pw-dropdown-value='save_close'", $html, '*=');
		$this->check('dropdown renders action link', "href='/admin/page/list/'", $html, '*=');
		$this->check('dropdown renders action icon', 'fa-times-circle', $html, '*=');
		$this->check('dropdownSubmit true renders submit data attr', "data-pw-dropdown-submit='1'", $html, '*=');

		$this->processInput($f, array('submit' => 'save_close', '_action_value' => 'save_close'));
		$this->check('dropdownSubmit true accepts dropdown submit value', 'save_close', $f->submitValue);

		$f = $this->newInputfield('submit');
		$f->attr('value', 'Save');
		$f->dropdownSubmit = false;
		$f->dropdownInputName = 'my_action';
		$f->addActionValue('save_close', 'Save and Close');
		$html = $f->render();
		$this->check('custom dropdownInputName renders hidden input', "name='my_action'", $html, '*=');
		$this->check('dropdownSubmit false omits submit data attr', false, strpos($html, "data-pw-dropdown-submit='1'") !== false);

		$this->processInput($f, array('submit' => 'Save', 'my_action' => 'save_close'));
		$this->check('main button click still wins before dropdownInputName', 'Save', $f->submitValue);

		$f = $this->newInputfield('submit');
		$f->attr('value', 'Save');
		$f->dropdownSubmit = false;
		$f->dropdownInputName = 'my_action';
		$f->addActionValue('save_close', 'Save and Close');
		$this->processInput($f, array('my_action' => 'save_close'));
		$this->check('dropdownSubmit false accepts hidden input value', 'save_close', $f->submitValue);

		$f = $this->newInputfield('submit');
		$f->dropdownRequired = true;
		$f->addActionValue('save_close', 'Save and Close');
		$this->check('dropdownRequired true appears in init script', ', true);', $f->render(), '*=');
	}
}
