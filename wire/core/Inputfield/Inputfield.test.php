<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Inputfield and InputfieldWrapper.
 *
 */
class WireTest_Inputfield extends WireTest {

	public function execute() {
		$this->testAttributesAndClasses();
		$this->testProcessInput();
		$this->testWrapperCreationAndTraversal();
		$this->testImportArrayAndRemoval();
	}

	protected function testAttributesAndClasses() {
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');

		$this->check('attr(name) sets name attribute', true, $f->attr('name', 'email') === $f);
		$this->check('attr(name) auto-populates default id', 'Inputfield_email', $f->attr('id'));

		$f->attr(array(
			'value' => 'hello@example.com',
			'placeholder' => 'you@example.com',
		));

		$this->check('attr(array) sets value attribute', 'hello@example.com', $f->attr('value'));
		$this->check('attr(array) sets placeholder attribute', 'you@example.com', $f->attr('placeholder'));

		$f->attr('name+id', 'contact_email');
		$this->check('attr(name+id) sets name', 'contact_email', $f->attr('name'));
		$this->check('attr(name+id) sets id', 'contact_email', $f->attr('id'));

		$attrs = $f->attr(true);
		$this->check('attr(true) returns all attributes', true, is_array($attrs) && $attrs['name'] === 'contact_email');

		$f->attr('disabled', true);
		$this->check('attr(boolean true) sets boolean attribute name', 'disabled', $f->attr('disabled'));
		$f->attr('disabled', false);
		$this->check('attr(boolean false) removes boolean attribute', null, $f->attr('disabled'));

		$this->check('val(value) returns $this', true, $f->val('updated@example.com') === $f);
		$this->check('val() gets value attribute', 'updated@example.com', $f->val());

		$f->addClass('alpha beta beta');
		$this->check('addClass() avoids duplicate input classes', 'alpha beta', $f->attr('class'));
		$this->check('hasClass() accepts multiple classes', true, $f->hasClass('alpha beta'));

		$f->addClass('wrap:card, header:card-head, content:card-body, input:gamma');
		$this->check('addClass(formatted) adds wrapper class', true, $f->hasClass('card', 'wrap'));
		$this->check('addClass(formatted) adds header class', true, $f->hasClass('card-head', 'header'));
		$this->check('addClass(formatted) adds content class', true, $f->hasClass('card-body', 'content'));
		$this->check('addClass(formatted) adds input class', true, $f->hasClass('gamma'));

		$f->removeClass('alpha gamma');
		$this->check('removeClass() removes multiple classes', false, $f->hasClass('alpha gamma'));
	}

	protected function testProcessInput() {
		/** @var InputfieldText $f */
		$f = $this->wire()->modules->get('InputfieldText');
		$f->attr('name', 'email');
		$f->val('before@example.com');
		$f->setTrackChanges(true);

		$data = array('email' => 'after@example.com');
		$input = $this->wire(new WireInputData($data));

		$this->check('processInput() returns $this', true, $f->processInput($input) === $f);
		$this->check('processInput() updates value from WireInputData', 'after@example.com', $f->val());
		$this->check('processInput() tracks changed value', true, in_array('value', $f->getChanges(), true));

		/** @var InputfieldForm $form */
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->protectCSRF = false;
		$required = $form->new('text', 'required_email', 'Email', array('required' => true));

		$blankData = array('required_email' => '');
		$form->processInput($this->wire(new WireInputData($blankData)));

		$this->check('InputfieldWrapper::processInput() records required errors', true, count($form->getErrors()) > 0);
		$errorInputfields = $form->getErrorInputfields();
		$this->check(
			'getErrorInputfields() includes required child',
			true,
			isset($errorInputfields['required_email']) && $errorInputfields['required_email'] === $required
		);
	}

	protected function testWrapperCreationAndTraversal() {
		/** @var InputfieldForm $form */
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->protectCSRF = false;

		$magic = $form->InputfieldText;
		$this->check('InputfieldWrapper magic property creates Inputfield', true, $magic instanceof InputfieldText);
		$this->check('InputfieldWrapper magic property does not add child', 0, $form->children()->count());

		$first = $form->new('text', 'first_name', 'First Name', array(
			'required' => true,
			'description' => 'Given name',
		));

		$this->check('new() creates requested short Inputfield type', true, $first instanceof InputfieldText);
		$this->check('new() sets name', 'first_name', $first->attr('name'));
		$this->check('new() sets label', 'First Name', $first->label);
		$this->check('new() applies settings array', true, (bool) $first->required);
		$this->check('new() adds field to wrapper', true, $form->getChildByName('first_name') === $first);
		$this->check('new() sets parent wrapper', true, $first->parent() === $form);
		$this->check('getRootParent() returns form', true, $first->getRootParent() === $form);
		$this->check('getForm() returns containing form', true, $first->getForm() === $form);

		$last = $form->new('text', 'last_name', 'Last Name');
		$form->insertBefore(array('type' => 'text', 'name' => 'middle_name', 'label' => 'Middle Name'), 'last_name');
		$form->insertAfter(array('type' => 'text', 'name' => 'suffix', 'label' => 'Suffix'), 'last_name');

		$names = array();
		foreach($form->children() as $child) $names[] = $child->attr('name');

		$this->check('insertBefore() inserts before named field', array('first_name', 'middle_name', 'last_name', 'suffix'), $names);
		$this->check('insertAfter() inserted field can be found by name', 'Suffix', $form->getByName('suffix')->label);
		$this->check('getValueByName() returns child value', $last->val(), $form->getValueByName('last_name'));
	}

	protected function testImportArrayAndRemoval() {
		/** @var InputfieldForm $form */
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->protectCSRF = false;

		$form->importArray(array(
			'first' => array(
				'type' => 'text',
				'label' => 'First Name',
				'attr' => array(
					'placeholder' => 'Ada',
				),
			),
			'group' => array(
				'type' => 'fieldset',
				'label' => 'Group',
				'children' => array(
					'nested' => array(
						'type' => 'text',
						'label' => 'Nested',
						'attr' => array(
							'value' => 'inside',
						),
					),
					'nested_return' => array(
						'type' => 'text',
						'label' => 'Nested Return',
					),
				),
			),
		));

		$first = $form->getByName('first');
		$nested = $form->getByName('nested');
		$nestedReturn = $form->getByName('nested_return');

		$this->check('importArray() creates named child from array key', true, $first instanceof InputfieldText);
		$this->check('importArray() applies attr subarray', 'Ada', $first->attr('placeholder'));
		$this->check('importArray() does not keep attr subarray as setting', null, $first->getSetting('attr'));
		$this->check('importArray() imports nested wrapper children', true, $nested instanceof InputfieldText);
		$this->check('getByName() finds nested child recursively', 'inside', $nested->val());

		$this->check('remove(name) returns wrapper', true, $form->remove('nested') === $form);
		$this->check('remove(name) removes nested child recursively', null, $form->getByName('nested'));

		$removed = $form->removeByName('nested_return');
		$this->check('removeByName() returns nested removed child', true, $removed === $nestedReturn);
		$this->check('removeByName() removes nested child recursively', null, $form->getByName('nested_return'));
	}
}
