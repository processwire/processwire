<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldPassword module.
 *
 */
class WireTest_InputfieldPassword extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testRender();
		$this->testRenderValue();
		$this->testProcessInput();
		$this->testValidationRequirements();
		$this->testSetPage();
		$this->testConfigInputfields();
	}

	protected function newInputfield($name = 'pass') {
		$f = $this->wire()->modules->get('InputfieldPassword');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput($f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldPassword', true, $f instanceof InputfieldPassword);
		$this->check('extends InputfieldText', true, $f instanceof InputfieldText);
		$this->check('default type is password', 'password', $f->attr('type'));
		$this->check('default size is 30', 30, $f->attr('size'));
		$this->check('default maxlength is 256', 256, $f->attr('maxlength'));
		$this->check('default minlength is 6', 6, $f->attr('minlength'));
		$this->check('default requirements are letter and digit', array(InputfieldPassword::requireLetter, InputfieldPassword::requireDigit), $f->requirements);
		$this->check('default requireOld is auto', InputfieldPassword::requireOldAuto, $f->requireOld);
		$this->check('default showPass is false', false, $f->showPass);
		$this->check('default unmask is false', false, $f->unmask);
	}

	protected function testRender() {
		$f = $this->newInputfield('pass');
		$f->value = 'Secret123!';
		$html = $f->render();

		$this->check('render includes password input', 'type="password"', $html, '*=');
		$this->check('render includes confirm input', "name='_pass'", $html, '*=');
		$this->check('render includes strength score wrapper', 'pass-scores', $html, '*=');
		$this->check('render omits password value when showPass false', false, strpos($html, 'Secret123!') !== false);
		$this->check('render restores value after hiding it', 'Secret123!', $f->value);

		$f = $this->newInputfield('pass');
		$f->value = 'Secret123!';
		$f->showPass = true;
		$html = $f->render();
		$this->check('showPass render includes password value', 'value="Secret123!"', $html, '*=');
		$this->check('showPass render includes confirm value', "name='_pass' value='Secret123!'", $html, '*=');

		$f = $this->newInputfield('pass');
		$f->unmask = true;
		$html = $f->render();
		$this->check('unmask render includes show password link', 'Show Password', $html, '*=');

		$f = $this->newInputfield('pass');
		$f->complexifyFactor = '0,5';
		$html = $f->render();
		$this->check('comma complexifyFactor renders dot decimal', 'data-factor="0.5"', $html, '*=');
	}

	protected function testRenderValue() {
		$f = $this->newInputfield();
		$f->value = 'Secret123!';
		$this->check('renderValue masks password by default', '<p>******</p>', $f->renderValue());

		$f->showPass = true;
		$this->check('showPass renderValue shows encoded value', '<p>Secret123!</p>', $f->renderValue());

		$f->value = '<secret>';
		$this->check('showPass renderValue entity encodes value', '<p>&lt;secret&gt;</p>', $f->renderValue());
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('pass');
		$this->processInput($f, array(
			'pass' => 'Secret123',
			'_pass' => 'Secret123',
		));
		$this->check('matching valid password is accepted', 'Secret123', $f->value);
		$this->check('matching valid password has no errors', 0, count($f->getErrors(true)));

		$f = $this->newInputfield('pass');
		$this->processInput($f, array(
			'pass' => 'Secret123',
			'_pass' => 'Different123',
		));
		$this->check('mismatched password clears value', '', $f->value);
		$this->check('mismatched password records error', true, count($f->getErrors(true)) > 0);

		$f = $this->newInputfield('pass');
		$f->required = true;
		$this->processInput($f, array(
			'pass' => '',
			'_pass' => '',
		));
		$this->check('required blank password records error', true, count($f->getErrors(true)) > 0);
	}

	protected function testValidationRequirements() {
		$f = $this->newInputfield();
		$f->requirements = array(InputfieldPassword::requireUpperLetter, InputfieldPassword::requireLowerLetter, InputfieldPassword::requireDigit, InputfieldPassword::requireOther);
		$this->check('complex password validates', true, $f->isValidPassword('GoodPass123!'));
		$this->check('complex password has no errors', 0, count($f->getErrors(true)));

		$f = $this->newInputfield();
		$f->requirements = array(InputfieldPassword::requireDigit);
		$this->check('missing digit is invalid', false, $f->isValidPassword('abcdef'));
		$this->check('missing digit records error', true, count($f->getErrors(true)) > 0);

		$f = $this->newInputfield();
		$f->requirements = array(InputfieldPassword::requireNone);
		$this->check('requireNone skips character classes', true, $f->isValidPassword('abcdef'));

		$f = $this->newInputfield();
		$f->requirements = array(InputfieldPassword::requireNone);
		$this->check('requireNone still enforces minlength', false, $f->isValidPassword('abc'));
		$f->getErrors(true);

		$f = $this->newInputfield();
		$this->check('password with tab is invalid', false, $f->isValidPassword("abc123\t"));
	}

	protected function testSetPage() {
		$f = $this->newInputfield();
		$page = new Page();
		$page->addStatus(Page::statusUnpublished);
		$f->setPage($page);
		$f->collapsed = Inputfield::collapsedYes;

		$this->check('setPage unpublished makes password required', true, $f->required);
		$this->check('setPage unpublished prevents collapse', Inputfield::collapsedNo, $f->collapsed);
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$config = $f->getConfigInputfields();

		$this->check('config includes requirements field', true, $config->getChildByName('requirements') instanceof Inputfield);
		$this->check('config includes complexifyBanMode field', true, $config->getChildByName('complexifyBanMode') instanceof Inputfield);
		$this->check('config includes complexifyFactor field', true, $config->getChildByName('complexifyFactor') instanceof Inputfield);
		$this->check('config includes minlength field', true, $config->getChildByName('minlength') instanceof Inputfield);
		$this->check('config includes showPass field for standalone input', true, $config->getChildByName('showPass') instanceof Inputfield);
		$this->check('config includes requireOld field', true, $config->getChildByName('requireOld') instanceof Inputfield);
		$this->check('config includes unmask field', true, $config->getChildByName('unmask') instanceof Inputfield);
		$this->check('config removes placeholder field', null, $config->getChildByName('placeholder'));

		$f = $this->newInputfield();
		$f->hasFieldtype = true;
		$config = $f->getConfigInputfields();
		$this->check('config omits showPass field when hasFieldtype', null, $config->getChildByName('showPass'));
	}
}
