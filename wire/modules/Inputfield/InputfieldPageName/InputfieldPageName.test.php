<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldPageName module.
 *
 */
class WireTest_InputfieldPageName extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testSanitization();
		$this->testProcessInput();
		$this->testUrlPreview();
		$this->testReplacements();
		$this->testConfigInputfields();
	}

	protected function newInputfield($name = 'name') {
		$f = $this->wire()->modules->get('InputfieldPageName');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput($f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function expectedSanitized($value) {
		$sanitizer = $this->wire()->sanitizer;
		$config = $this->wire()->config;
		return $config->pageNameCharset === 'UTF8' ? $sanitizer->pageNameUTF8($value) : $sanitizer->pageName($value);
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();
		$config = $this->wire()->config;

		$this->check('module returns InputfieldPageName', true, $f instanceof InputfieldPageName);
		$this->check('extends InputfieldName', true, $f instanceof InputfieldName);
		$this->check('default label is Name', 'Name', $f->label);
		$this->check('default icon is angle-double-right', 'angle-double-right', $f->icon);
		$this->check('autocomplete uses pw-page-name', 'pw-page-name', $f->attr('autocomplete'));
		$this->check('slashUrls defaults to 1', 1, $f->slashUrls);
		$this->check('sanitizeMethod follows pageNameCharset',
			$config->pageNameCharset === 'UTF8' ? 'pageNameUTF8' : 'pageName',
			$f->sanitizeMethod
		);
	}

	protected function testSanitization() {
		$f = $this->newInputfield();
		$f->val('Hello World!');
		$this->check('val() sanitizes page name', $this->expectedSanitized('Hello World!'), $f->val());

		$f->val('About/Us?Now');
		$this->check('val() sanitizes URL punctuation', $this->expectedSanitized('About/Us?Now'), $f->val());
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('page_name');
		$page = $this->getTestPage();
		$template = $page->template;
		$noLang = $template->noLang;
		$template->noLang = 1;
		$f->editPage = $page;

		try {
			$this->processInput($f, 'New Page Name!');
			$this->check('processInput() sanitizes submitted value', $this->expectedSanitized('New Page Name!'), $f->val());
		} finally {
			$template->noLang = $noLang;
		}

		$f = $this->newInputfield('page_name');
		$f->attr('disabled', 'disabled');
		$f->val('existing');
		$this->processInput($f, 'changed');
		$this->check('disabled processInput() preserves value', 'existing', $f->val());
	}

	protected function testUrlPreview() {
		$pages = $this->wire()->pages;
		$parent = $pages->get('/');

		$f = $this->newInputfield('name');
		$f->parentPage = $parent;
		$f->val('team');
		$html = $f->render();

		$this->check('render includes URL preview paragraph', 'InputfieldPageNameURL', $html, '*=');
		$this->check('render includes slashUrls data attribute', "data-slashUrls='1'", $html, '*=');
		$this->check('render includes preview strong element', '<strong></strong>', $html, '*=');

		$f = $this->newInputfield('name');
		$f->parentPage = $parent;
		$f->slashUrls = false;
		$html = $f->render();
		$this->check('slashUrls false updates data attribute', "data-slashUrls='0'", $html, '*=');
	}

	protected function testReplacements() {
		$defaults = InputfieldPageName::getDefaultReplacements();
		$this->check('default replacements include accented mapping', 'a', $defaults['ä']);

		$str = "ä=a\nö=o\ninvalid line\nü = u";
		$replacements = InputfieldPageName::replacementStringToArray($str);
		$this->check('replacementStringToArray parses first mapping', 'a', $replacements['ä']);
		$this->check('replacementStringToArray trims whitespace', 'u', $replacements['ü']);
		$this->check('replacementStringToArray ignores malformed lines', false, isset($replacements['invalid line']));

		$str = InputfieldPageName::replacementArrayToString(array('ä' => 'a', 'ö' => 'o'));
		$this->check('replacementArrayToString includes first mapping', 'ä=a', $str, '*=');
		$this->check('replacementArrayToString includes second mapping', 'ö=o', $str, '*=');
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$configFields = $f->getModuleConfigInputfields(array());

		if($this->wire()->config->pageNameCharset === 'UTF8') {
			$this->check('UTF8 config omits replacements field', null, $configFields->getChildByName('replacements'));
		} else {
			$this->check('ASCII config includes replacements field', true, $configFields->getChildByName('replacements') instanceof Inputfield);
		}
	}
}
