<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldEmail module.
 *
 */
class WireTest_InputfieldEmail extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testSanitizationAndValidation();
		$this->testProcessInput();
		$this->testConfirmationInput();
		$this->testIDNRenderMode();
		$this->testRender();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'email') {
		$f = $this->wire()->modules->get('InputfieldEmail');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldEmail $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is email', 'email', $f->attr('type'));
		$this->check('default name is email', 'email', $f->attr('name'));
		$this->check('default maxlength is 250', 250, $f->attr('maxlength'));
		$this->check('default size is 0', 0, $f->attr('size'));
		$this->check('default confirm is 0', 0, $f->confirm);
		$this->check('default confirmLabel is Confirm', 'Confirm', $f->confirmLabel);
		$this->check('default allowIDN is 0', 0, $f->allowIDN);
	}

	protected function testSanitizationAndValidation() {
		$f = $this->newInputfield();
		$f->val(' user@example.com ');
		$this->check('valid email is trimmed and stored', 'user@example.com', $f->val());

		$f->getErrors(true);
		$f->val('not an email');
		$this->check('invalid email becomes blank', '', $f->val());
		$this->check('invalid email records error', true, count($f->getErrors(true)) > 0);
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('contact_email');
		$this->processInput($f, array('contact_email' => 'new@example.com'));
		$this->check('processInput accepts valid email', 'new@example.com', $f->val());

		$f->val('old@example.com');
		$f->getErrors(true);
		$this->processInput($f, array('contact_email' => 'bad email'));
		$this->check('processInput invalid email preserves previous value', 'old@example.com', $f->val());
		$this->check('processInput invalid email records error', true, count($f->getErrors(true)) > 0);
	}

	protected function testConfirmationInput() {
		$f = $this->newInputfield('email');
		$f->confirm = 1;
		$f->confirmLabel = 'Re-enter email';
		$this->processInput($f, array('email' => 'USER@example.com', '_email_confirm' => 'user@example.com'));
		$this->check('confirmation match is case-insensitive', 'USER@example.com', $f->val());
		$this->check('confirmation match has no errors', 0, count($f->getErrors(true)));

		$f->val('old@example.com');
		$this->processInput($f, array('email' => 'new@example.com', '_email_confirm' => 'other@example.com'));
		$this->check('confirmation mismatch preserves previous value', 'old@example.com', $f->val());
		$this->check('confirmation mismatch records error', true, count($f->getErrors(true)) > 0);
	}

	protected function testIDNRenderMode() {
		$f = $this->newInputfield();
		$f->allowIDN = 1;
		$this->check('allowIDN=1 keeps email input type', 'type="email"', $f->render(), '*=');

		$f = $this->newInputfield();
		$f->allowIDN = 2;
		$html = $f->render();
		$this->check('allowIDN=2 uses text input type', 'type="text"', $html, '*=');
		$this->check('allowIDN=2 adds pattern attribute', 'pattern=', $html, '*=');
	}

	protected function testRender() {
		$f = $this->newInputfield('contact_email');
		$f->val('user@example.com');
		$html = $f->render();

		$this->check('render returns input element', '<input', $html, '*=');
		$this->check('render includes name attribute', 'name="contact_email"', $html, '*=');
		$this->check('render includes email type', 'type="email"', $html, '*=');
		$this->check('render includes value', 'user@example.com', $html, '*=');

		$f = $this->newInputfield('contact_email');
		$f->confirm = 1;
		$f->confirmLabel = 'Re-enter email';
		$html = $f->render();
		$this->check('render confirm input uses confirm name', 'name="_contact_email_confirm"', $html, '*=');
		$this->check('render confirm input uses confirm label placeholder', 'placeholder="Re-enter email"', $html, '*=');
		$this->check('render confirm input uses confirm aria label', 'aria-label="Re-enter email"', $html, '*=');
	}
}
