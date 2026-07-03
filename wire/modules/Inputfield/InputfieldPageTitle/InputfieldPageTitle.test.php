<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldPageTitle module.
 *
 */
class WireTest_InputfieldPageTitle extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testInputfieldTextBehavior();
		$this->testCustomRenderReady();
		$this->testReplacementFallback();
		$this->testJavaScriptTrailingDelimiterFix();
	}

	protected function newInputfield($name = 'title') {
		$f = $this->wire()->modules->get('InputfieldPageTitle');
		$f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldPageTitle', true, $f instanceof InputfieldPageTitle);
		$this->check('extends InputfieldText', true, $f instanceof InputfieldText);
		$this->check('default nameField is blank', '', $f->nameField);
		$this->check('default nameDelimiter is blank', '', $f->nameDelimiter);
		$this->check('default nameReplacements is array', array(), $f->nameReplacements);
		$this->check('default maxlength inherited from InputfieldText', 2048, $f->attr('maxlength'));
	}

	protected function testInputfieldTextBehavior() {
		$f = $this->newInputfield();
		$f->val('  Hello World  ');
		$this->check('val() uses inherited text trimming', 'Hello World', $f->val());

		$f->attr('maxlength', 5);
		$f->val('Hello World');
		$this->check('maxlength truncation inherited from InputfieldText', 'Hello', $f->val());
	}

	protected function testCustomRenderReady() {
		$f = $this->newInputfield();
		$f->nameField = 'custom_name';
		$f->nameDelimiter = '-';
		$f->nameReplacements = array('ä' => 'ae');
		$f->renderReady();

		$this->check('custom mode adds wrapper class', 'InputfieldPageTitleCustom', $f->wrapClass(), '*=');
		$this->check('custom mode sets name field wrapper attr', 'custom_name', $f->wrapAttr('data-name-field'));
		$this->check('custom mode sets delimiter wrapper attr', '-', $f->wrapAttr('data-name-delimiter'));

		$config = $this->wire()->config->js('InputfieldPageTitle');
		$this->check('custom replacements are published to JS config', 'ae', $config['replacements']['ä']);
	}

	protected function testReplacementFallback() {
		$f = $this->newInputfield();
		$f->nameField = 'custom_name';
		$f->nameDelimiter = '-';
		$f->renderReady();

		$config = $this->wire()->config->js('InputfieldPageTitle');
		$this->check('fallback replacements publish non-empty JS config', true, !empty($config['replacements']));
	}

	protected function testJavaScriptTrailingDelimiterFix() {
		$dir = dirname(__FILE__);
		$js = file_get_contents($dir . '/InputfieldPageTitle.js');
		$min = file_get_contents($dir . '/InputfieldPageTitle.min.js');

		$this->check('source JS uses slice(-1) for final character', 'name.slice(-1)', $js, '*=');
		$this->check('source JS no longer uses substring(-1)', false, strpos($js, 'name.substring(-1)') !== false);
		$this->check('minified JS uses slice(-1) for final character', 'name.slice(-1)', $min, '*=');
	}
}

