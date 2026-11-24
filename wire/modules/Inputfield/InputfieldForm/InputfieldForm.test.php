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
		$this->testLockedDependencies();
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

	/**
	 * showIf/requiredIf conditions on locked or non-editable fields are evaluated at render
	 * 
	 */
	protected function testLockedDependencies() {
		$form = $this->newForm('wire-test-locked-form');
		$options = array('1' => 'One', '2' => 'Two', '3' => 'Three');

		$type = $form->InputfieldSelect;
		$type->attr('name', 'type');
		$type->addOptions($options);
		$type->val('2');
		$type->collapsed = Inputfield::collapsedNoLocked;
		$form->add($type);

		$multi = $form->InputfieldCheckboxes;
		$multi->attr('name', 'multi');
		$multi->addOptions($options);
		$multi->val(array('1', '3'));
		$multi->collapsed = Inputfield::collapsedNoLocked;
		$form->add($multi);

		$secret = $this->addText($form, 'secret');
		$secret->val('wire-test-locked-value');
		$secret->collapsed = Inputfield::collapsedNoLocked;

		$color = $this->addText($form, 'color'); // editable
		$color->val('blue');

		$viewOnly = $this->addText($form, 'view_only');
		$viewOnly->val('yes');
		$viewOnly->editable(false);

		$lockedShowIf = $this->addText($form, 'locked_show_if'); // locked, but has a showIf of its own
		$lockedShowIf->val('1');
		$lockedShowIf->collapsed = Inputfield::collapsedNoLocked;
		$lockedShowIf->showIf = 'color=blue';

		$fieldset = $form->InputfieldFieldset;
		$fieldset->attr('name', 'wire_test_fieldset');
		$form->add($fieldset);

		// name => array(showIf, requiredIf, expected rendered state)
		$tests = array(
			'show_true' => array('type=2', '', 'visible'),
			'show_false' => array('type!=2', '', 'hidden'),
			'show_label' => array('type=Two', '', 'visible'),
			'show_mixed' => array('type=2, color=red', '', 'visible showIf:color=red'),
			'show_mixed_false' => array('type=3, color=red', '', 'hidden'),
			'or_editable' => array('type|color=3', '', 'visible showIf:color=3'),
			'or_locked_match' => array('type|color=2', '', 'visible'),
			'multi_count' => array('multi.count>1', '', 'visible'),
			'multi_has' => array('multi=3', '', 'visible'),
			'multi_not' => array('multi!=3', '', 'hidden'),
			'multi_count_false' => array('multi.count>2', '', 'hidden'),
			'secret_not' => array('secret!=wire-test-locked-value', '', 'hidden'),
			'view_only_dep' => array('view_only=yes', '', 'visible'),
			'editable_only' => array('color=red', '', 'visible showIf:color=red'),
			'depends_show_if' => array('locked_show_if=1', '', 'visible showIf:locked_show_if=1'),
			'or_group' => array('(type=2), (color=red)', '', 'visible showIf:(type=2), (color=red)'),
			'required_true' => array('', 'type=2', 'visible required'),
			'required_false' => array('', 'type=3', 'visible'),
			'required_mixed' => array('', 'type=2, color=red', 'visible requiredIf:color=red required'),
			'hidden_required' => array('type=3', 'color=red', 'hidden'),
		);
		foreach($tests as $name => $test) {
			if($name === 'multi_has') {
				$f = $form->InputfieldText; // in a fieldset, to check nested fields find the form
				$f->attr('name', $name);
				$fieldset->add($f);
			} else {
				$f = $this->addText($form, $name);
			}
			$f->showIf = $test[0];
			$f->requiredIf = $test[1];
			if(strlen($test[1])) $f->required = true;
		}

		$html = $form->render();

		foreach($tests as $name => $test) {
			$state = 'missing';
			if(preg_match("~<li\\b[^>]*\\bid='wrap_Inputfield_$name'[^>]*>~", $html, $m)) {
				$tag = html_entity_decode($m[0], ENT_QUOTES);
				$state = strpos($tag, 'InputfieldStateHidden') && strpos($tag, 'display:none') ? 'hidden' : 'visible';
				if(preg_match("~data-show-if='([^']*)'~", $tag, $a)) $state .= " showIf:$a[1]";
				if(preg_match("~data-required-if='([^']*)'~", $tag, $a)) $state .= " requiredIf:$a[1]";
				if(strpos($tag, 'InputfieldStateRequired')) $state .= ' required';
			}
			$this->check("locked dependency $name: " . ($test[0] ?: "requiredIf $test[1]"), $test[2], $state);
		}

		$f = $form->getChildByName('show_false');
		$this->check('locked dependency leaves showIf setting unchanged', 'type!=2', $f->showIf);
		$f = $form->getChildByName('required_false');
		$this->check('locked dependency leaves required setting unchanged', true, (bool) $f->required);
		$this->check('locked value is not copied into the form', 1, substr_count($html, 'wire-test-locked-value'));
	}
}
