<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldHidden module.
 *
 */
class WireTest_InputfieldHidden extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testRender();
		$this->testInitValue();
		$this->testRenderValue();
		$this->testConfigInputfields();
	}

	protected function newInputfield($name = 'token') {
		$f = $this->wire()->modules->get('InputfieldHidden');
		$f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is hidden', 'hidden', $f->attr('type'));
		$this->check('default renderValueAsInput is false', false, $f->renderValueAsInput);
		$this->check('default initValue is blank', '', $f->initValue);
		$this->check('module returns InputfieldHidden', true, $f instanceof InputfieldHidden);
	}

	protected function testRender() {
		$f = $this->newInputfield('referrer');
		$f->value = 'abc123';
		$html = $f->render();

		$this->check('render returns input element', '<input', $html, '*=');
		$this->check('render includes hidden type', 'type="hidden"', $html, '*=');
		$this->check('render includes name attribute', 'name="referrer"', $html, '*=');
		$this->check('render includes value attribute', 'value="abc123"', $html, '*=');
	}

	protected function testInitValue() {
		$f = $this->newInputfield('mode');
		$f->initValue = 'edit';
		$html = $f->render();

		$this->check('render uses initValue when value empty', 'value="edit"', $html, '*=');

		$f->value = 'view';
		$this->check('explicit value wins over initValue', 'value="view"', $f->render(), '*=');
	}

	protected function testRenderValue() {
		$f = $this->newInputfield('token');
		$f->value = 'abc123';
		$html = $f->renderValue();

		$this->check('renderValue default does not render hidden input', false, strpos($html, 'type="hidden"') !== false);

		$f->renderValueAsInput = true;
		$html = $f->renderValue();
		$this->check('renderValueAsInput renders hidden input', 'type="hidden"', $html, '*=');
		$this->check('renderValueAsInput includes value', 'value="abc123"', $html, '*=');
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$f->initValue = 'abc123';
		$config = $f->getConfigInputfields();

		$this->check('config removes collapsed field', null, $config->getChildByName('collapsed'));
		$this->check('config removes columnWidth field', null, $config->getChildByName('columnWidth'));
		$this->check('config includes initValue field', true, $config->getChildByName('initValue') instanceof Inputfield);
		$this->check('config initValue field has current value', 'abc123', $config->getChildByName('initValue')->value);
	}
}
