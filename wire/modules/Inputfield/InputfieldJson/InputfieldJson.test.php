<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldJson module.
 *
 */
class WireTest_InputfieldJson extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testArrayAndObjectValues();
		$this->testInvalidValueAssignment();
		$this->testProcessInput();
		$this->testProcessInputViewMode();
		$this->testRequiredInput();
		$this->testRender();
		$this->testRenderValue();
		$this->testIsBadJson();
		$this->testConfigInputfields();
		$this->testConstants();
	}

	protected function newInputfield($name = 'json_data') {
		$f = $this->wire()->modules->get('InputfieldJson');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldJson $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function decode($json) {
		return json_decode($json, true);
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldJson', true, $f instanceof InputfieldJson);
		$this->check('default mode is tree', InputfieldJson::modeTree, $f->mode);
		$this->check('default value is blank string', '', $f->val());
		$this->check('default main menu bar disabled', false, (bool) $f->useMainMenuBar);
		$this->check('default navigation bar disabled', false, (bool) $f->useNavigationBar);
		$this->check('default search disabled', false, (bool) $f->useSearch);

		$f->mode = InputfieldJson::modeCode;
		$f->useMainMenuBar = true;
		$f->useNavigationBar = true;
		$f->useSearch = true;
		$this->check('mode property sets code mode', InputfieldJson::modeCode, $f->mode);
		$this->check('useMainMenuBar property sets true', true, (bool) $f->useMainMenuBar);
		$this->check('useNavigationBar property sets true', true, (bool) $f->useNavigationBar);
		$this->check('useSearch property sets true', true, (bool) $f->useSearch);
	}

	protected function testArrayAndObjectValues() {
		$f = $this->newInputfield();
		$data = array(
			'string' => 'Hello World',
			'number' => 123,
			'boolean' => true,
			'null' => null,
			'array' => array(1, 2, 3),
			'object' => array('a' => 'b'),
		);
		$f->val($data);

		$this->check('array value is stored as JSON string', true, is_string($f->val()));
		$this->check('array value JSON decodes to original data', $data, $this->decode($f->val()));

		$object = (object) array('foo' => 'bar', 'baz' => array('qux' => true));
		$f->val($object);
		$this->check('object value is stored as JSON string', true, is_string($f->val()));
		$this->check('object value JSON decodes to associative array', array('foo' => 'bar', 'baz' => array('qux' => true)), $this->decode($f->val()));
	}

	protected function testInvalidValueAssignment() {
		$f = $this->newInputfield();
		$f->val(array('valid' => true));
		$previous = $f->val();

		$f->val('{"broken":');
		$this->check('invalid direct assignment keeps previous value', $previous, $f->val());
		$this->check('invalid direct assignment does not add input error', 0, count($f->getErrors(true)));

		$f->val(123);
		$this->check('non-string direct assignment keeps previous value', $previous, $f->val());
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('settings_json');
		$f->val(array('before' => true));
		$submitted = '{ "after": true, "count": 2 }';
		$this->processInput($f, $submitted);

		$this->check('editable processInput updates valid JSON', array('after' => true, 'count' => 2), $this->decode($f->val()));
		$this->check('editable processInput normalizes valid JSON', '{"after":true,"count":2}', $f->val());
		$this->check('valid JSON processInput has no errors', 0, count($f->getErrors(true)));

		$previous = $f->val();
		$this->processInput($f, '{ "after": ');
		$errors = $f->getErrors(true);
		$this->check('invalid processInput restores previous value', $previous, $f->val());
		$this->check('invalid processInput adds error', true, count($errors) > 0);
		$this->check('invalid processInput error says previous value restored', 'previous value restored', implode(' ', $errors), '*=');

		$f = $this->newInputfield('blank_json');
		$f->val(array('before' => true));
		$this->processInput($f, '');
		$this->check('editable processInput accepts blank value', '', $f->val());

		$f = $this->newInputfield('missing_json');
		$f->val(array('before' => true));
		$data = array();
		$f->processInput(new WireInputData($data));
		$this->check('processInput missing name keeps previous value', array('before' => true), $this->decode($f->val()));
	}

	protected function testProcessInputViewMode() {
		$f = $this->newInputfield('readonly_json');
		$f->mode = InputfieldJson::modeView;
		$f->val(array('before' => true));
		$this->processInput($f, '{"after":true}');

		$this->check('modeView processInput skips submitted value', array('before' => true), $this->decode($f->val()));
		$this->check('modeView processInput has no errors', 0, count($f->getErrors(true)));
	}

	protected function testRequiredInput() {
		$f = $this->newInputfield('required_json');
		$f->required = true;
		$this->processInput($f, '');

		$errors = $f->getErrors(true);
		$this->check('required blank processInput adds error', true, count($errors) > 0);
		$this->check('required blank processInput keeps value blank', '', $f->val());
	}

	protected function testRender() {
		$f = $this->newInputfield('display_json');
		$f->attr('id', 'Inputfield_display_json');
		$f->mode = InputfieldJson::modeCode;
		$f->useMainMenuBar = true;
		$f->useNavigationBar = true;
		$f->useSearch = true;
		$f->val(array('a' => '<tag>', 'b' => '"quote"'));

		$html = $f->render();
		$this->check('render returns json editor container', 'InputfieldJsonContainer', $html, '*=');
		$this->check('render includes hidden textarea', '<textarea', $html, '*=');
		$this->check('render includes field name', 'name="display_json"', $html, '*=');
		$this->check('render loads local InputfieldJson init script', 'InputfieldJson.init', $html, '*=');
		$this->check('render uses configured mode', '"mode":"code"', $html, '*=');
		$this->check('render uses main menu option', '"mainMenuBar":true', $html, '*=');
		$this->check('render uses navigation option', '"navigationBar":true', $html, '*=');
		$this->check('render uses search option', '"search":true', $html, '*=');
		$this->check('render encodes JSON value for textarea', '&lt;tag&gt;', $html, '*=');
		$this->check('render references jsoneditor asset path', 'jsoneditor\\/dist\\/jsoneditor.', $html, '*=');

		$f->mode = 'not-a-mode';
		$this->check('render falls back to default mode for invalid mode', '"mode":"tree"', $f->render(), '*=');
	}

	protected function testRenderValue() {
		$f = $this->newInputfield('view_json');
		$f->attr('id', 'Inputfield_view_json');
		$f->mode = InputfieldJson::modeCode;
		$f->val(array('view' => true));

		$html = $f->renderValue();
		$this->check('renderValue forces view mode', '"mode":"view"', $html, '*=');
		$this->check('renderValue does not leak configured editable mode', false, strpos($html, '"mode":"code"') !== false);
		$this->check('renderValue includes current value', '&quot;view&quot;:true', $html, '*=');

		$this->check('renderValue restores normal render mode after call', '"mode":"code"', $f->render(), '*=');

		$this->processInput($f, '{"changed":true}');
		$this->check('processInput works after renderValue restored mode', array('changed' => true), $this->decode($f->val()));
	}

	protected function testIsBadJson() {
		$f = $this->newInputfield();

		$json = '{ "a": 1, "b": [2, 3] }';
		$error = $f->isBadJson($json);
		$this->check('isBadJson returns blank error for valid JSON object', '', $error);
		$this->check('isBadJson normalizes valid JSON object', '{"a":1,"b":[2,3]}', $json);

		$json = '{ "a": ';
		$error = $f->isBadJson($json);
		$this->check('isBadJson returns error for invalid JSON', true, strlen($error) > 0);
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$f->mode = InputfieldJson::modeForm;
		$f->useMainMenuBar = true;
		$f->useNavigationBar = true;

		$config = $f->getConfigInputfields();
		$this->check('config includes mode field', true, $config->getChildByName('mode') instanceof InputfieldRadios);
		$this->check('config includes menu bar checkbox', true, $config->getChildByName('useMainMenuBar') instanceof InputfieldCheckbox);
		$this->check('config includes navigation checkbox', true, $config->getChildByName('useNavigationBar') instanceof InputfieldCheckbox);
		$this->check('config mode field includes view option', 'view', implode(' ', array_keys($config->getChildByName('mode')->getOptions())), '*=');
		$this->check('config does not expose useSearch field', null, $config->getChildByName('useSearch'));
	}

	protected function testConstants() {
		$this->check('modeTree constant is tree', 'tree', InputfieldJson::modeTree);
		$this->check('modeForm constant is form', 'form', InputfieldJson::modeForm);
		$this->check('modeText constant is text', 'text', InputfieldJson::modeText);
		$this->check('modeCode constant is code', 'code', InputfieldJson::modeCode);
		$this->check('modeView constant is view', 'view', InputfieldJson::modeView);
		$this->check('defaultMode constant is tree', InputfieldJson::modeTree, InputfieldJson::defaultMode);
	}
}
