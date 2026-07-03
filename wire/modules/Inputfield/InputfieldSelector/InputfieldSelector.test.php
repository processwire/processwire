<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldSelector module.
 *
 */
class WireTest_InputfieldSelector extends WireTest {

	public function execute() {
		$this->testBasicSettings();
		$this->testSelectorInfo();
		$this->testSanitizeSelectorString();
		$this->testProcessInputAndInitValue();
		$this->testRenderRow();
		$this->testRender();
	}

	protected function newInputfield($name = 'selector') {
		$f = $this->wire()->modules->get('InputfieldSelector');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldSelector $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicSettings() {
		$f = $this->newInputfield();
		$defaults = $f->getDefaultSettings();

		$this->check('module returns InputfieldSelector', true, $f instanceof InputfieldSelector);
		$this->check('default name attr is selector', 'selector', $f->attr('name'));
		$this->check('default preview is enabled', 1, $defaults['preview']);
		$this->check('default counter is enabled', 1, $defaults['counter']);
		$this->check('default initValue is blank', '', $defaults['initValue']);
		$this->check('default allowSubselectors is true', true, $defaults['allowSubselectors']);
		$this->check('default maxSelectOptions is 100', 100, $defaults['maxSelectOptions']);

		$f->preview = false;
		$f->limitFields = 'title, body';
		$settings = $f->getSettings();
		$this->check('getSettings reflects changed preview', false, $settings['preview']);
		$this->check('limitFields string converts to array', array('title', 'body'), $f->limitFields);
	}

	protected function testSelectorInfo() {
		$f = $this->newInputfield();
		$f->setup();

		$info = $f->getSelectorInfo('template');
		$this->check('template selector info is select input', 'select', $info['input']);
		$this->check('template selector info has options', true, !empty($info['options']));
		$this->check('template selector info has equals operators', array('=', '!='), $info['operators']);

		$info = $f->getSelectorInfo('limit');
		$this->check('modifier selector info resolves', 'integer', $info['input']);

		$info = $f->getSelectorInfo($this->wire()->fields->get('title'));
		$this->check('field selector info resolves Field object', true, !empty($info['input']));

		$this->check('missing selector info returns blank array', array(), $f->getSelectorInfo('definitely_not_a_field'));
	}

	protected function testSanitizeSelectorString() {
		$f = $this->newInputfield();
		$f->initValue = 'template=basic-page';
		$this->check('sanitizeSelectorString prepends initValue', 'template=basic-page, title%=About', $f->sanitizeSelectorString('title%=About', false));

		$user = $this->wire()->users->get($this->wire()->config->superUserPageID);
		$f = $this->newInputfield();
		$this->check('sanitizeSelectorString converts username to ID for created_users_id', 'created_users_id=' . $user->id, $f->sanitizeSelectorString('created_users_id=' . $user->name, false));

		$f = $this->newInputfield();
		$f->allowSubselectors = false;
		$this->check('disabled subselectors force non-match selector', 'id<0', $f->sanitizeSelectorString('children=[title%=About]', false));
		$this->check('disabled subselectors records error', 'Subselectors are disabled', implode(' ', $f->getErrors(true)), '*=');
	}

	protected function testProcessInputAndInitValue() {
		$f = $this->newInputfield('filter');
		$this->processInput($f, 'title%=About');
		$this->check('processInput stores selector value without initValue', 'title%=About', $f->val());
		$this->check('processInput updates lastSelector without initValue', 'title%=About', $f->lastSelector);

		$f = $this->newInputfield('filter');
		$f->initValue = 'template=basic-page';
		$this->processInput($f, 'title%=About');
		$this->check('processInput keeps editable value separate from initValue', 'title%=About', $f->val());
		$this->check('processInput stores full selector in lastSelector', 'template=basic-page, title%=About', $f->lastSelector);

		$f->attr('value', 'template=basic-page, body%=Text');
		$this->check('set value strips matching initValue from editable value', 'body%=Text', $f->val());
		$this->check('set value preserves full lastSelector', 'template=basic-page, body%=Text', $f->lastSelector);
	}

	protected function testRenderRow() {
		$f = $this->newInputfield();
		$row = $f->renderRow('<select></select>', '<select></select>', '<input>', 'custom-row');

		$this->check('renderRow outputs list item', '<li ', $row, '*=');
		$this->check('renderRow includes selector-row class', 'selector-row', $row, '*=');
		$this->check('renderRow includes custom class', 'custom-row', $row, '*=');
		$this->check('renderRow includes delete link when add/remove allowed', 'delete-row', $row, '*=');

		$f->allowAddRemove = false;
		$row = $f->renderRow('<select></select>', '', '<input>');
		$this->check('renderRow omits delete link when add/remove disabled', false, strpos($row, 'delete-row') !== false);
	}

	protected function testRender() {
		$f = $this->newInputfield('filter');
		$f->initValue = 'template=basic-page';
		$f->attr('value', 'title%=About');
		$f->preview = false;
		$f->counter = false;
		$f->allowBlankValues = true;
		$html = $f->render();

		$this->check('render outputs selector list', 'selector-list', $html, '*=');
		$this->check('render outputs hidden selector value input', 'selector-value', $html, '*=');
		$this->check('render includes initValue preview data', "data-init-value='template=basic-page'", $html, '*=');
		$this->check('render marks preview disabled', 'selector-preview-disabled', $html, '*=');
		$this->check('render marks counter disabled', 'selector-counter-disabled', $html, '*=');
		$this->check('render marks allow blank values', 'allow-blank', $html, '*=');
		$this->check('render includes add field link by default', 'selector-add', $html, '*=');
	}
}

