<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldFieldset module.
 *
 */
class WireTest_InputfieldFieldset extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testChildRendering();
		$this->testNestedFieldsets();
		$this->testEmptyFieldsetOutput();
		$this->testHiddenEmptyFieldset();
	}

	protected function newFieldset($label = 'Address') {
		$f = $this->wire()->modules->get('InputfieldFieldset');
		$f->label = $label;
		return $f;
	}

	protected function newText($name, $label, $value = '') {
		$f = $this->wire()->modules->get('InputfieldText');
		$f->attr('name', $name);
		$f->label = $label;
		if($value !== '') $f->value = $value;
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newFieldset();

		$this->check('module returns InputfieldFieldset', true, $f instanceof InputfieldFieldset);
		$this->check('fieldset extends InputfieldWrapper', true, $f instanceof InputfieldWrapper);
		$this->check('fieldset label is settable', 'Address', $f->label);
		$f->collapsed = Inputfield::collapsedYes;
		$this->check('fieldset collapsed state is settable', Inputfield::collapsedYes, $f->collapsed);
	}

	protected function testChildRendering() {
		$f = $this->newFieldset('Address');
		$f->description = 'Enter your address details';
		$f->icon = 'home';
		$f->collapsed = Inputfield::collapsedYes;
		$f->add($this->newText('street', 'Street', '123 Main'));
		$f->add($this->newText('city', 'City', 'Austin'));
		$html = $f->render();

		$this->check('direct render includes first child name', 'name="street"', $html, '*=');
		$this->check('direct render includes second child name', 'name="city"', $html, '*=');
		$this->check('children count includes added fields', 2, count($f->children()));

		$form = $this->wire()->modules->get('InputfieldForm');
		$form->add($f);
		$html = $form->render();
		$this->check('form render includes fieldset label', 'Address', $html, '*=');
		$this->check('form render includes fieldset description', 'Enter your address details', $html, '*=');
	}

	protected function testNestedFieldsets() {
		$personal = $this->newFieldset('Personal Information');
		$address = $this->newFieldset('Address');
		$address->collapsed = Inputfield::collapsedYes;
		$address->add($this->newText('street', 'Street'));
		$personal->add($address);
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->add($personal);
		$html = $form->render();

		$this->check('nested render includes outer label', 'Personal Information', $html, '*=');
		$this->check('nested render includes inner label', 'Address', $html, '*=');
		$this->check('nested render includes child input', 'name="street"', $html, '*=');
		$this->check('nested fieldset count is one', 1, count($personal->children()));
	}

	protected function testEmptyFieldsetOutput() {
		$f = $this->newFieldset('Empty Section');
		$html = $f->render();

		$this->check('empty fieldset direct render returns newline', "\n", $html);

		$form = $this->wire()->modules->get('InputfieldForm');
		$form->add($f);
		$html = $form->render();
		$this->check('parent wrapper includes empty fieldset label', 'Empty Section', $html, '*=');
	}

	protected function testHiddenEmptyFieldset() {
		$f = $this->newFieldset('Hidden Empty Section');
		$f->collapsed = Inputfield::collapsedHidden;
		$form = $this->wire()->modules->get('InputfieldForm');
		$form->add($f);
		$html = $form->render();

		$this->check('collapsedHidden empty fieldset omits label', false, strpos($html, 'Hidden Empty Section') !== false);
	}
}
