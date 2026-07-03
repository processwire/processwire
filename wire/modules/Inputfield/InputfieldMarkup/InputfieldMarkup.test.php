<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldMarkup module.
 *
 */
class WireTest_InputfieldMarkup extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testContentSources();
		$this->testRenderValue();
		$this->testDescriptionPlacement();
		$this->testChildInputfields();
		$this->testTextformatters();
		$this->testConfigInputfields();
	}

	protected function newInputfield($name = 'help_markup') {
		$f = $this->wire()->modules->get('InputfieldMarkup');
		$f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldMarkup', true, $f instanceof InputfieldMarkup);
		$this->check('default markupText is blank', '', $f->markupText);
		$this->check('default markupFunction is null', null, $f->markupFunction);
		$this->check('default textformatters is array', true, is_array($f->textformatters));
		$this->check('default skipLabel is skipLabelBlank', Inputfield::skipLabelBlank, $f->skipLabel);
	}

	protected function testContentSources() {
		$f = $this->newInputfield();
		$f->attr('value', '<p>First</p>');
		$f->markupFunction = function(InputfieldMarkup $field) {
			return '<p>Second: ' . $field->attr('name') . '</p>';
		};
		$f->markupText = '<p>Third</p>';
		$html = $f->render();

		$this->check('render includes value attribute markup', '<p>First</p>', $html, '*=');
		$this->check('render includes markupFunction output', '<p>Second: help_markup</p>', $html, '*=');
		$this->check('render includes markupText output', '<p>Third</p>', $html, '*=');
		$this->check('content source order is value/function/text', true,
			strpos($html, '<p>First</p>') < strpos($html, '<p>Second: help_markup</p>') &&
			strpos($html, '<p>Second: help_markup</p>') < strpos($html, '<p>Third</p>')
		);
		$this->check('value attribute restored after render', '<p>First</p>', $f->attr('value'));
	}

	protected function testRenderValue() {
		$f = $this->newInputfield();
		$f->markupText = '<p>Display only</p>';

		$this->check('renderValue includes markupText', '<p>Display only</p>', $f->renderValue(), '*=');
		$this->check('renderValue leaves renderValueMode false after render', false, $f->renderValueMode);
	}

	protected function testDescriptionPlacement() {
		$f = $this->newInputfield();
		$f->description = 'Read this first';
		$f->markupText = '<p>Then this</p>';
		$html = $f->render();

		$this->check('render includes description markup', 'Read this first', $html, '*=');
		$this->check('description appears before markupText', true, strpos($html, 'Read this first') < strpos($html, '<p>Then this</p>'));
		$this->check('description is cleared after render', '', $f->description);
	}

	protected function testChildInputfields() {
		$f = $this->newInputfield();
		$f->markupText = '<p>Parent markup</p>';

		$child = $this->wire()->modules->get('InputfieldCheckbox');
		$child->attr('name', 'newsletter');
		$child->label = 'Subscribe to newsletter';
		$f->add($child);

		$html = $f->render();
		$this->check('render includes parent markup', '<p>Parent markup</p>', $html, '*=');
		$this->check('render includes child input name', 'name="newsletter"', $html, '*=');
		$this->check('render includes child label', 'Subscribe to newsletter', $html, '*=');
	}

	protected function testTextformatters() {
		$f = $this->newInputfield();
		$f->markupText = '<b>Encode me</b>';
		$f->textformatters = array('TextformatterEntities');
		$html = $f->render();

		$this->check('textformatter encodes less-than', '&lt;b&gt;Encode me&lt;/b&gt;', $html, '*=');
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$f->markupText = '<p>Configured</p>';
		$f->textformatters = array('TextformatterEntities');
		$config = $f->getConfigInputfields();

		$this->check('config includes markupText field', true, $config->getChildByName('markupText') instanceof Inputfield);
		$this->check('config markupText field has current value', '<p>Configured</p>', $config->getChildByName('markupText')->value);
		$this->check('config includes textformatters field', true, $config->getChildByName('textformatters') instanceof Inputfield);
		$this->check('config textformatters field has current value', array('TextformatterEntities'), $config->getChildByName('textformatters')->value);

		$f = $this->newInputfield();
		$f->hasFieldtype = true;
		$config = $f->getConfigInputfields();
		$this->check('hasFieldtype config omits markupText field', null, $config->getChildByName('markupText'));
		$this->check('hasFieldtype config omits textformatters field', null, $config->getChildByName('textformatters'));
	}
}
