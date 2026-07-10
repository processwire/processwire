<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldPageListSelect and InputfieldPageListSelectMultiple
 *
 */
class WireTest_InputfieldPageListSelect extends WireTest {

	public function execute() {
		$this->testSingleValueHandling();
		$this->testSingleRenderAndMarkupValue();
		$this->testMultipleValueHandling();
		$this->testMultipleRenderAndMarkupValue();
	}

	/**
	 * Test single-selection value handling.
	 *
	 */
	protected function testSingleValueHandling() {
		$page = $this->wire()->pages->get(1);
		$f = $this->newSingle('single_page');

		$this->check('single is empty by default', true, $f->isEmpty());
		$f->attr('value', $page);
		$this->check('single accepts Page object value', $page->id, $f->val());
		$this->check('single is not empty with page value', false, $f->isEmpty());

		$f->attr('value', array($page->id, 99999));
		$this->check('single accepts first array value', $page->id, $f->val());

		$data = array('single_page' => '123abc');
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('single processInput() casts submitted value to int', 123, $f->val());
	}

	/**
	 * Test single render and markup value.
	 *
	 */
	protected function testSingleRenderAndMarkupValue() {
		$page = $this->wire()->pages->get(1);
		$f = $this->newSingle('single_render');

		$this->check('single render with default parent_id renders root 0', true, strpos($f->render(), 'data-root="0"') !== false);
		$f->parent_id = '';
		$this->check('single render with empty parent_id returns parent error', true, strpos($f->render(), "class='error'") !== false);

		$f->parent_id = 1;
		$f->labelFieldName = 'title';
		$f->showPath = true;
		$f->required = true;
		$f->attr('value', $page->id);
		$html = $f->render();

		$this->check('single render includes hidden text input', true, strpos($html, "type='text'") !== false);
		$this->check('single render includes root data attribute', true, strpos($html, 'data-root="1"') !== false);
		$this->check('single render includes showPath data attribute', true, strpos($html, 'data-showPath="1"') !== false);
		$this->check('single render disables unselect when required', true, strpos($html, 'data-allowUnselect="0"') !== false);
		$this->check('single render includes label name', true, strpos($html, 'data-labelName="single_render"') !== false);

		$markup = $f->renderMarkupValue($page->id);
		$this->check('single renderMarkupValue() renders paragraph', true, strpos($markup, '<p>') === 0);
		$this->check('single getPageLabel() returns entity-safe label', true, strlen($f->getPageLabel($page)) > 0);
		$this->check('single renderMarkupValue(empty) returns empty string', '', $f->renderMarkupValue(0));
	}

	/**
	 * Test multiple-selection value handling.
	 *
	 */
	protected function testMultipleValueHandling() {
		$f = $this->newMultiple('multi_page');

		$this->check('multiple implements array value interface', true, $f instanceof InputfieldHasArrayValue);
		$this->check('multiple implements sortable value interface', true, $f instanceof InputfieldHasSortableValue);
		$this->check('multiple value defaults to array', true, is_array($f->val()));

		$f->attr('value', array(1, '2', 'bad', 3));
		$this->check('multiple attr(value) stores array before processing', array(1, '2', 'bad', 3), $f->val());

		$data = array('multi_page' => array('3,2,1'));
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('multiple processInput() converts CSV to int array preserving order', array(3, 2, 1), $f->val());

		$data = array('multi_page' => array(''));
		$input = $this->wire(new WireInputData($data));
		$f->processInput($input);
		$this->check('multiple processInput() empty value becomes empty array', array(), $f->val());
	}

	/**
	 * Test multiple render and markup value.
	 *
	 */
	protected function testMultipleRenderAndMarkupValue() {
		$page = $this->wire()->pages->get(1);
		$f = $this->newMultiple('multi_render');

		$this->check('multiple render with default parent_id renders root 0', true, strpos($f->render(), 'data-root="0"') !== false);
		$f->parent_id = '';
		$this->check('multiple render with empty parent_id returns parent error', true, strpos($f->render(), "class='error'") !== false);

		$f->parent_id = 1;
		$f->labelFieldName = 'title';
		$f->attr('value', array($page->id));
		$html = $f->render();

		$this->check('multiple render includes ordered selected list', true, strpos($html, "<ol id='") !== false);
		$this->check('multiple render includes item template', true, strpos($html, 'itemTemplate') !== false);
		$this->check('multiple render includes selected page id', true, strpos($html, "<span class='itemValue'>$page->id</span>") !== false);
		$this->check('multiple render includes root data attribute', true, strpos($html, 'data-root="1"') !== false);
		$this->check('multiple render includes selected label data attribute', true, strpos($html, 'data-selected="Selected"') !== false);
		$this->check('multiple render stores CSV value', true, strpos($html, "value='$page->id'") !== false);

		$markup = $f->renderMarkupValue(array($page->id));
		$this->check('multiple renderMarkupValue() renders list', true, strpos($markup, '<ul><li>') === 0);
		$this->check('multiple renderMarkupValue(empty) returns empty string', '', $f->renderMarkupValue(array()));
	}

	/**
	 * Make single-selection inputfield.
	 *
	 * @param string $name
	 * @return InputfieldPageListSelect
	 *
	 */
	protected function newSingle($name) {
		$f = $this->wire(new InputfieldPageListSelect());
		$f->init();
		$f->attr('id+name', $name);
		$f->label = $name;
		return $f;
	}

	/**
	 * Make multiple-selection inputfield.
	 *
	 * @param string $name
	 * @return InputfieldPageListSelectMultiple
	 *
	 */
	protected function newMultiple($name) {
		$f = $this->wire(new InputfieldPageListSelectMultiple());
		$f->init();
		$f->attr('id+name', $name);
		$f->label = $name;
		return $f;
	}
}
