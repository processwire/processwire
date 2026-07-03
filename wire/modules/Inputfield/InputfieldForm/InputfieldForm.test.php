<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldForm module.
 *
 */
class WireTest_InputfieldForm extends WireTest {

	/**
	 * @var string|null
	 *
	 */
	protected $requestMethod;

	public function init() {
		$this->requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testRender();
		$this->testGetFormName();
		$this->testIsSubmitted();
		$this->testProcessInputAndErrors();
		$this->testProcess();
		$this->testRenderOrProcessReadyHook();
	}

	public function finish() {
		if($this->requestMethod === null) {
			unset($_SERVER['REQUEST_METHOD']);
		} else {
			$_SERVER['REQUEST_METHOD'] = $this->requestMethod;
		}
	}

	protected function newForm($id = 'wire-test-form') {
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->attr('id', $id);
		return $form;
	}

	protected function addText(InputfieldForm $form, $name = 'title', $required = false) {
		$f = $form->InputfieldText;
		$f->attr('name', $name);
		$f->label = ucfirst($name);
		$f->required = $required;
		$form->add($f);
		return $f;
	}

	protected function addSubmit(InputfieldForm $form, $name = 'submit_save', $value = 'Save') {
		$f = $form->InputfieldSubmit;
		$f->attr('name', $name);
		$f->val($value);
		$form->add($f);
		return $f;
	}

	protected function setPost(array $data) {
		foreach($data as $key => $value) {
			$this->wire()->input->post->set($key, $value);
		}
		$_SERVER['REQUEST_METHOD'] = 'POST';
	}

	protected function testBasicProperties() {
		$form = $this->newForm();

		$this->check('module returns InputfieldForm', true, $form instanceof InputfieldForm);
		$this->check('extends InputfieldWrapper', true, $form instanceof InputfieldWrapper);
		$this->check('default method is post', 'post', $form->attr('method'));
		$this->check('default action is current directory', './', $form->attr('action'));
		$this->check('protectCSRF defaults true', true, $form->protectCSRF);
		$this->check('prependMarkup defaults blank', '', $form->prependMarkup);
		$this->check('appendMarkup defaults blank', '', $form->appendMarkup);
		$this->check('confirmText has default message', 'unsaved changes', $form->confirmText, '*=');
	}

	protected function testRender() {
		$form = $this->newForm('wire-test-render-form');
		$form->description = 'Form description';
		$form->prependMarkup = '<p class="before">Before</p>';
		$form->appendMarkup = '<p class="after">After</p>';
		$form->addClass('InputfieldFormConfirm');
		$html = $form->render();

		$this->check('render outputs form tag', '<form ', $html, '*=');
		$this->check('render includes method post', 'method="post"', $html, '*=');
		$this->check('render includes action attr', 'action="./"', $html, '*=');
		$this->check('render includes CSRF token for POST', "class='_post_token'", $html, '*=');
		$this->check('render includes landmark for POST', "name='_InputfieldForm'", $html, '*=');
		$this->check('render includes description', 'Form description', $html, '*=');
		$this->check('render includes prepend markup', 'class="before"', $html, '*=');
		$this->check('render includes append markup', 'class="after"', $html, '*=');
		$this->check('render includes confirm data attr', 'data-confirm=', $html, '*=');

		$form = $this->newForm('wire-test-get-form');
		$form->method = 'get';
		$html = $form->render();
		$this->check('GET render omits CSRF token', false, strpos($html, "_post_token") !== false);
		$this->check('GET render omits landmark', false, strpos($html, "name='_InputfieldForm'") !== false);
	}

	protected function testGetFormName() {
		$form = $this->newForm('wire-test-id');
		$this->check('getFormName falls back to id attr', 'wire-test-id', $form->getFormName());

		$form->attr('name', 'wire_test_name');
		$this->check('getFormName prefers name attr', 'wire_test_name', $form->getFormName());

		$form = $this->wire()->modules->get('InputfieldForm');
		$this->check('getFormName falls back to generated id by default', 'InputfieldForm', $form->getFormName(), '^=');
	}

	protected function testIsSubmitted() {
		$form = $this->newForm('wire-test-submit-form');
		$form->protectCSRF = false;
		$this->addText($form, 'title');
		$submit = $this->addSubmit($form, 'submit_save', 'Save');

		$this->setPost(array(
			'_InputfieldForm' => $form->getFormName(),
			'title' => 'Hello',
			'submit_save' => 'Save',
		));

		$this->check('isSubmitted() detects form submit', true, $form->isSubmitted());
		$this->check('isSubmitted(name) returns submit name', 'submit_save', $form->isSubmitted('submit_save'));
		$this->check('isSubmitted(Inputfield) returns submit name', 'submit_save', $form->isSubmitted($submit));
		$this->check('isSubmitted(true) returns clicked submit name', 'submit_save', $form->isSubmitted(true));
		$this->check('isSubmitted(wrong name) returns false', false, $form->isSubmitted('submit_other'));
	}

	protected function testProcessInputAndErrors() {
		$form = $this->newForm();
		$form->protectCSRF = false;
		$this->addText($form, 'title', true);

		$data = array('title' => '');
		$input = new WireInputData($data);
		$result = $form->processInput($input);
		$this->check('processInput returns form wrapper', true, $result instanceof InputfieldWrapper);
		$this->check('getInput returns processed WireInputData', true, $form->getInput() instanceof WireInputData);
		$this->check('required blank field records error', true, count($form->getErrors()) > 0);
		$this->check('getErrors(true) clears errors', true, count($form->getErrors(true)) > 0);
		$this->check('errors clear after getErrors(true)', 0, count($form->getErrors()));

		$form = $this->newForm();
		$form->protectCSRF = false;
		$controller = $this->addText($form, 'controller');
		$dependent = $this->addText($form, 'dependent', true);
		$dependent->showIf = 'controller=show';
		$dependent->requiredIf = 'controller=show';

		$data = array('controller' => 'hide', 'dependent' => '');
		$form->processInput(new WireInputData($data));
		$this->check('hidden requiredIf field is skipped without error', 0, count($form->getErrors(null)));

		$form = $this->newForm();
		$form->protectCSRF = false;
		$controller = $this->addText($form, 'controller');
		$dependent = $this->addText($form, 'dependent', true);
		$dependent->showIf = 'controller=show';
		$dependent->requiredIf = 'controller=show';

		$data = array('controller' => 'show', 'dependent' => '');
		$form->processInput(new WireInputData($data));
		$this->check('shown requiredIf field records error', true, count($form->getErrors(null)) > 0);
	}

	protected function testProcess() {
		$form = $this->newForm('wire-test-process-form');
		$form->protectCSRF = false;
		$this->addText($form, 'title', true);

		$this->setPost(array(
			'_InputfieldForm' => $form->getFormName(),
			'title' => 'Hello',
		));

		$this->check('process() returns true when no errors', true, $form->process());
		$this->check('processed child value available', 'Hello', $form->getValueByName('title'));

		$form = $this->newForm('wire-test-process-error-form');
		$form->protectCSRF = false;
		$this->addText($form, 'title', true);

		$this->setPost(array(
			'_InputfieldForm' => $form->getFormName(),
			'title' => '',
		));

		$this->check('process() returns false when errors exist', false, $form->process());
	}

	protected function testRenderOrProcessReadyHook() {
		$form = $this->newForm('wire-test-hook-form');
		$form->protectCSRF = false;
		$types = array();

		$form->addHookBefore('renderOrProcessReady', function(HookEvent $event) use(&$types) {
			$types[] = $event->arguments(0);
		});

		$form->render();
		$data = array();
		$form->processInput(new WireInputData($data));

		$this->check('renderOrProcessReady hook fires before render', true, in_array('render', $types));
		$this->check('renderOrProcessReady hook fires before process', true, in_array('process', $types));
	}
}
