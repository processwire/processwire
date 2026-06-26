<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldAsmSelect module.
 *
 */
class WireTest_InputfieldAsmSelect extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testArrayAndSortableValues();
		$this->testAsmOptions();
		$this->testRender();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = 'test_asm_select') {
		$f = $this->wire()->modules->get('InputfieldAsmSelect');
		$f->attr('name', $name);
		return $f;
	}

	protected function addOptions(InputfieldAsmSelect $f) {
		$f->addOptions(array('news' => 'News', 'events' => 'Events', 'blog' => 'Blog'));
		return $f;
	}

	protected function processInput(InputfieldAsmSelect $f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function decodeAsmOptions($html) {
		if(!preg_match('/data-asmopt="([^"]*)"/', $html, $matches)) return array();
		$json = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
		$options = json_decode($json, true);
		return is_array($options) ? $options : array();
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('asm select clears select-multiple size attr', null, $f->attr('size'));
		$this->check('asm select implements array value interface', true, $f instanceof InputfieldHasArrayValue);
		$this->check('asm select implements sortable value interface', true, $f instanceof InputfieldHasSortableValue);
		$this->check('default hideDeleted is enabled', 1, $f->hideDeleted);
		$this->check('default usePageEdit is disabled', 0, $f->usePageEdit);
	}

	protected function testArrayAndSortableValues() {
		$f = $this->addOptions($this->newInputfield('categories'));
		$f->val(array('events', 'news'));

		$this->check('val() preserves selected order', array('events', 'news'), $f->val());
		$this->check('selected item detected', true, $f->isOptionSelected('events'));

		$this->processInput($f, array('categories' => array('blog', 'bogus', 'news')));
		$this->check('processInput filters invalid values and preserves order', array('blog', 'news'), array_values($f->val()));
	}

	protected function testAsmOptions() {
		$f = $this->addOptions($this->newInputfield());
		$f->setAsmSelectOptions(array(
			'sortable' => false,
			'animate' => true,
			'addItemTarget' => 'top',
		));
		$html = $f->render();
		$options = $this->decodeAsmOptions($html);

		$this->check('setAsmSelectOptions renders changed sortable option', false, $options['sortable']);
		$this->check('setAsmSelectOptions renders changed animate option', true, $options['animate']);
		$this->check('setAsmSelectOptions renders addItemTarget option', 'top', $options['addItemTarget']);

		$f = $this->addOptions($this->newInputfield());
		$f->sortable = false;
		$f->editLink = '/admin/page/edit/?id={value}';
		$options = $this->decodeAsmOptions($f->render());
		$this->check('property setter updates asm option', false, $options['sortable']);
		$this->check('editLink property renders asm option', '/admin/page/edit/?id={value}', $options['editLink']);
	}

	protected function testRender() {
		$f = $this->addOptions($this->newInputfield('categories'));
		$f->val(array('events', 'news'));
		$html = $f->render();

		$this->check('render returns select element', '<select', $html, '*=');
		$this->check('render includes multiple attr', 'multiple="multiple"', $html, '*=');
		$this->check('render includes data-asmopt attr', 'data-asmopt=', $html, '*=');
		$this->check('render includes array field name', 'name="categories[]"', $html, '*=');
		$this->check('render includes selected option', "selected='selected'", $html, '*=');

		// Selected options are moved to the end of the select output so asmSelect can preserve order.
		$this->check('selected events appears after unselected blog', true, strpos($html, "value='events'") > strpos($html, "value='blog'"));
		$this->check('selected news appears after selected events', true, strpos($html, "value='news'") > strpos($html, "value='events'"));
	}
}
