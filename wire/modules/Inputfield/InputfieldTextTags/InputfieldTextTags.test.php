<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldTextTags module.
 *
 */
class WireTest_InputfieldTextTags extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testTagList();
		$this->testSelectableOptions();
		$this->testValueHandling();
		$this->testProcessInput();
		$this->testRender();
		$this->testTagsArrayHelper();
	}

	protected function newInputfield($name = 'test_tags') {
		$f = $this->wire()->modules->get('InputfieldTextTags');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldTextTags $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldTextTags', true, $f instanceof InputfieldTextTags);
		$this->check('implements text value interface', true, $f instanceof InputfieldHasTextValue);
		$this->check('implements array value support interface', true, $f instanceof InputfieldSupportsArrayValue);
		$this->check('implements page selector interface', true, $f instanceof InputfieldSupportsPageSelector);
		$this->check('implements selectable options interface', true, $f instanceof InputfieldHasSelectableOptions);
		$this->check('implements sortable value interface', true, $f instanceof InputfieldHasSortableValue);
		$this->check('default tagsList is array', array(), $f->tagsList);
		$this->check('default tagsUrl is blank', '', $f->tagsUrl);
		$this->check('allowUserTags defaults to int 0', 0, $f->allowUserTags);
		$this->check('closeAfterSelect defaults to int 1', 1, $f->closeAfterSelect);
		$this->check('maxItems defaults to 0', 0, $f->maxItems);
		$this->check('maxSelectedItems maps to maxItems', 0, $f->maxSelectedItems);

		$f->maxSelectedItems = 2;
		$this->check('setting maxSelectedItems updates maxItems', 2, $f->maxItems);
	}

	protected function testTagList() {
		$f = $this->newInputfield();
		$f->addTag('foo');
		$f->addTag('bar', 'This is Bar');

		$this->check('addTag() uses tag as default label', 'foo', $f->getTagLabel('foo'));
		$this->check('addTag() stores explicit label', 'This is Bar', $f->getTagLabel('bar'));
		$this->check('unknown tag label is blank when user tags disallowed', '', $f->getTagLabel('missing'));

		$f->allowUserTags = 1;
		$this->check('unknown tag label returns tag when user tags allowed', 'missing', $f->getTagLabel('missing'));

		$f->setTagLabel('bar', 'Updated Bar');
		$this->check('setTagLabel() updates label', 'Updated Bar', $f->getTagLabel('bar'));

		$f->removeTag('foo');
		$this->check('removeTag() removes tag', false, isset($f->getTagsList()['foo']));

		$f->setTagsList("red=Red\nblue=Blue\ngreen");
		$this->check('setTagsList() string parses explicit label', 'Red', $f->getTagLabel('red'));
		$this->check('setTagsList() string uses tag as implicit label', 'green', $f->getTagLabel('green'));
		$this->check('getTagsList(false) returns tag definition string', 'blue=Blue', $f->getTagsList(null, false), '*=');
		$this->check('getTagLabels() aliases getTagsList()', $f->getTagsList(), $f->getTagLabels());
	}

	protected function testSelectableOptions() {
		$f = $this->newInputfield();
		$f->addOption('a', 'A');
		$f->addOptions(array('b' => 'B', 'c' => 'C'));

		$this->check('addOption() maps to addTag()', 'A', $f->getTagLabel('a'));
		$this->check('addOptions() maps associative values', 'B', $f->getTagLabel('b'));

		$f->addOptionLabel('c', 'See');
		$this->check('addOptionLabel() maps to set tag label', 'See', $f->getTagLabel('c'));

		$f = $this->newInputfield();
		$f->addOptions(array('red', 'blue'));
		$this->check('addOptions() numeric array uses numeric keys as tags', array(0 => 'red', 1 => 'blue'), $f->getTagsList());
	}

	protected function testValueHandling() {
		$f = $this->newInputfield();
		$f->val(array('foo', 'bar'));
		$this->check('array value stores delimiter string', 'foo bar', $f->val());
		$this->check('arrayValue returns associative tags', array('foo' => 'foo', 'bar' => 'bar'), $f->arrayValue);

		$f->setArrayValue(array('baz', 'qux'));
		$this->check('setArrayValue() stores delimiter string', 'baz qux', $f->val());
		$this->check('getArrayValue() returns selected tags', array('baz' => 'baz', 'qux' => 'qux'), $f->getArrayValue());

		$f->delimiter = 'c';
		$f->val(array('New York', 'Los Angeles'));
		$this->check('comma delimiter stores comma separated tags', 'New York,Los Angeles', $f->val());
		$this->check('comma delimiter parses array values', array('New York' => 'New York', 'Los Angeles' => 'Los Angeles'), $f->arrayValue);

		$f->delimiter = 'p';
		$f->val(array('101', '202'));
		$this->check('pipe delimiter stores pipe separated tags', '101|202', $f->val());
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('tags');
		$f->setTagsList(array('red' => 'Red', 'blue' => 'Blue'));
		$this->processInput($f, 'red green blue');
		$this->check('processInput removes unknown predefined-list tags', 'red blue', $f->val());
		$this->check('invalid predefined-list tag records error', true, count($f->getErrors(true)) > 0);

		$f = $this->newInputfield('tags');
		$f->setTagsList(array('red' => 'Red'));
		$f->allowUserTags = 1;
		$this->processInput($f, 'red green');
		$this->check('processInput keeps unknown tags when allowed', 'red green', $f->val());

		$f = $this->newInputfield('tags');
		$f->tagsUrl = '/find-tags/?q={q}';
		$this->processInput($f, 'red green');
		$this->check('processInput preserves ajax tags without local list', 'red green', $f->val());

		$f = $this->newInputfield('tags');
		$f->allowUserTags = 1;
		$f->maxItems = 2;
		$this->processInput($f, 'one two three');
		$this->check('processInput applies maxItems limit', 'one two', $f->val());
	}

	protected function testRender() {
		$f = $this->newInputfield('tags');
		$f->setTagsList(array('red' => 'Red', '2024' => 'Year 2024'));
		$f->val(array('red', '2024'));
		$html = $f->render();

		$this->check('render returns input element', '<input ', $html, '*=');
		$this->check('render includes select class for selectable tags', 'InputfieldTextTagsSelect', $html, '*=');
		$this->check('render encodes numeric tags with underscore', '_2024', $html, '*=');
		$this->check('render includes JSON options data', 'data-opts=', $html, '*=');

		$f = $this->newInputfield('tags');
		$f->allowUserTags = 1;
		$html = $f->render();
		$this->check('free input render uses input class', 'InputfieldTextTagsInput', $html, '*=');
		$this->check('free input render omits select-only class', false, strpos($html, 'InputfieldTextTagsSelectOnly') !== false);
	}

	protected function testTagsArrayHelper() {
		$field = $this->wire(new Field());
		$field->set('tagsList', array('foo' => 'Foo', 'bar' => 'Bar'));

		$this->check('tagsArray() maps known labels and unknown tags', array('foo' => 'Foo', 'baz' => 'baz'), InputfieldTextTags::tagsArray($field, 'foo baz'));
		$this->check('tagsArray() null returns all configured tags', array('foo' => 'Foo', 'bar' => 'Bar'), InputfieldTextTags::tagsArray($field));
	}
}

