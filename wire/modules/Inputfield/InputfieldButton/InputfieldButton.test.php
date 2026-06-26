<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldButton module.
 *
 */
class WireTest_InputfieldButton extends WireTest {

	public function init() {
		// nothing to set up
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testRenderButton();
		$this->testOuterLink();
		$this->testInnerLink();
	}

	public function finish() {
		// nothing to clean up
	}

	protected function newInputfield($name = null) {
		$f = $this->wire()->modules->get('InputfieldButton');
		if($name !== null) $f->attr('name', $name);
		return $f;
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('default type is button', 'button', $f->attr('type'));
		$this->check('default name is button', 'button', $f->attr('name'));
		$this->check('default value is Button', 'Button', $f->attr('value'));
		$this->check('default href is blank', '', $f->attr('href'));
		$this->check('default aclass is blank', '', $f->aclass);
		$this->check('default target is blank', '', $f->target);
		$this->check('default linkInner is false', false, $f->linkInner);
	}

	protected function testRenderButton() {
		$f = $this->newInputfield('view');
		$f->attr('value', 'View Page');
		$html = $f->render();

		$this->check('render returns button element', '<button', $html, '*=');
		$this->check('render uses button type', 'type="button"', $html, '*=');
		$this->check('render includes value text', 'View Page', $html, '*=');
		$this->check('render does not wrap in link without href', false, strpos($html, '<a ') !== false);
	}

	protected function testOuterLink() {
		$f = $this->newInputfield('view');
		$f->attr('value', 'View Page');
		$f->href = '/example/';
		$f->aclass = 'pw-modal custom-link';
		$f->target = '_blank';
		$html = $f->render();

		$this->check('href is restored after render', '/example/', $f->href);
		$this->check('outer link wraps button', '<a class="InputfieldButtonLink pw-modal custom-link" href="/example/" target="_blank" tabindex=\'-1\'><button', $html, '*=');
		$this->check('outer link keeps button type', 'type="button"', $html, '*=');
		$this->check('outer link includes button text', 'View Page', $html, '*=');
	}

	protected function testInnerLink() {
		$f = $this->newInputfield('view');
		$f->attr('value', 'View Page');
		$f->href = '/example/';
		$f->aclass = 'inner-link';
		$f->linkInner = true;
		$html = $f->render();

		$this->check('inner link keeps button outer element', '<button', $html, '*=');
		$this->check('inner link places anchor inside button', '<a class="InputfieldButtonLink inner-link" href="/example/">', $html, '*=');
		$this->check('inner link includes button text in anchor', '>View Page</span></a></button>', $html, '*=');
	}
}
