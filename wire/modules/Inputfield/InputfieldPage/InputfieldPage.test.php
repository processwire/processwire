<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldPage module.
 *
 */
class WireTest_InputfieldPage extends WireTest {

	public function execute() {
		$this->testBasicProperties();
		$this->testValueAssignment();
		$this->testTemplateIDsAndSelector();
		$this->testSelectablePages();
		$this->testPageLabels();
		$this->testDelegateInputfieldAndRender();
		$this->testProcessInput();
		$this->testIsEmptyAndIsValidPage();
	}

	protected function newInputfield($name = 'related_page') {
		$f = $this->wire()->modules->get('InputfieldPage');
		$f->attr('name', $name);
		$f->inputfield = 'InputfieldSelect';
		return $f;
	}

	protected function selectablePage() {
		return $this->getTestPage();
	}

	protected function editPage() {
		return $this->wire()->pages->get('/');
	}

	protected function configureForTestPage(InputfieldPage $f) {
		$page = $this->selectablePage();
		$f->parent_id = $page->parent_id;
		$f->template_id = $page->template->id;
		$f->labelFieldName = 'title';
		return $f;
	}

	protected function processInput($f, array $data) {
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldPage', true, $f instanceof InputfieldPage);
		$this->check('default value is PageArray', true, $f->attr('value') instanceof PageArray);
		$this->check('default parent_id is 0', 0, $f->parent_id);
		$this->check('default template_id is 0', 0, $f->template_id);
		$this->check('default template_ids is array', array(), $f->template_ids);
		$this->check('configured inputfield class resolves', 'InputfieldSelect', $f->getSetting('inputfieldClass'));
	}

	protected function testValueAssignment() {
		$pages = $this->wire()->pages;
		$page = $this->selectablePage();
		$home = $pages->get('/');
		$f = $this->newInputfield();

		$f->attr('value', $page->id);
		$value = $f->attr('value');
		$this->check('integer value becomes PageArray', true, $value instanceof PageArray);
		$this->check('integer value PageArray has selected page', true, $value->has($page));

		$f->attr('value', "$page->id|$home->id");
		$value = $f->attr('value');
		$this->check('pipe-separated value becomes PageArray', true, $value instanceof PageArray);
		$this->check('pipe-separated value includes first page', true, $value->has($page));
		$this->check('pipe-separated value includes second page', true, $value->has($home));

		$f->attr('value', $page);
		$this->check('Page value remains Page', true, $f->attr('value') instanceof Page);

		$a = $pages->newPageArray();
		$a->add($page);
		$f->attr('value', $a);
		$this->check('PageArray value remains PageArray', true, $f->attr('value') instanceof PageArray);
	}

	protected function testTemplateIDsAndSelector() {
		$page = $this->selectablePage();
		$f = $this->newInputfield();
		$f->parent_id = $page->parent_id;
		$f->template_id = $page->template->id;

		$this->check('getTemplateIDs returns configured template', array($page->template->id), $f->getTemplateIDs());
		$this->check('getTemplateIDs string returns pipe string', (string) $page->template->id, $f->getTemplateIDs(true));

		$selector = $f->createFindPagesSelector();
		$this->check('selector includes parent_id', "parent_id=$page->parent_id", $selector, '*=');
		$this->check('selector includes template id', 'templates_id=' . $page->template->id, $selector, '*=');
		$this->check('selector includes hidden include mode', 'include=hidden', $selector, '*=');

		$array = $f->createFindPagesSelector(array('getArray' => true));
		$this->check('selector array includes parent_id', $page->parent_id, $array['parent_id']);
		$this->check('selector array includes include mode', 'hidden', $array['include']);
	}

	protected function testSelectablePages() {
		$page = $this->selectablePage();
		$f = $this->configureForTestPage($this->newInputfield());

		$selectable = $f->getSelectablePages($this->editPage());
		$this->check('getSelectablePages returns PageArray', true, $selectable instanceof PageArray);
		$this->check('getSelectablePages includes matching page', true, $selectable->has($page));

		$selectable = $f->getSelectablePages($page);
		$this->check('getSelectablePages excludes edited page itself', false, $selectable->has($page));
	}

	protected function testPageLabels() {
		$page = $this->selectablePage();
		$f = $this->newInputfield();
		$title = $page->getFormatted('title');

		$f->labelFieldName = 'title';
		$this->check('getPageLabel uses labelFieldName', $title, $f->getPageLabel($page));

		$f->labelFieldName = '.';
		$f->labelFieldFormat = '{title} [{name}]';
		$this->check('getPageLabel uses labelFieldFormat', $title . ' [' . $page->name . ']', $f->getPageLabel($page));

		$f->labelFieldName = '';
		$f->labelFieldFormat = '';
		$page->of(false);
		$oldTitle = $page->title;
		$page->title = '';
		$this->check('getPageLabel falls back to page name', $page->name, $f->getPageLabel($page));
		$page->title = $oldTitle;
	}

	protected function testDelegateInputfieldAndRender() {
		$page = $this->selectablePage();
		$f = $this->configureForTestPage($this->newInputfield());
		$f->label = 'Related page';
		$f->attr('value', $page->id);

		$delegate = $f->getInputfield();
		$this->check('getInputfield returns delegate InputfieldSelect', true, $delegate instanceof InputfieldSelect);
		$this->check('delegate has configured name', 'related_page', $delegate->attr('name'));
		$this->check('delegate value has selected id', $page->id, (int) $delegate->attr('value'));

		$html = $f->render();
		$this->check('render includes delegate select', '<select', $html, '*=');
		$this->check('render includes selected page option label', $page->title, $html, '*=');
	}

	protected function testProcessInput() {
		$page = $this->selectablePage();
		$f = $this->configureForTestPage($this->newInputfield('related_page'));
		$this->processInput($f, array('related_page' => $page->id));

		$value = $f->attr('value');
		$this->check('processInput stores PageArray by default', true, $value instanceof PageArray);
		$this->check('processInput selected PageArray has page', true, $value->has($page));

		$f = $this->configureForTestPage($this->newInputfield('related_page'));
		$f->derefAsPage = 1;
		$this->processInput($f, array('related_page' => $page->id));
		$value = $f->attr('value');
		$this->check('derefAsPage processInput stores Page', true, $value instanceof Page);
		$this->check('derefAsPage selected page id matches', $page->id, $value->id);
	}

	protected function testIsEmptyAndIsValidPage() {
		$pages = $this->wire()->pages;
		$page = $this->selectablePage();
		$f = $this->configureForTestPage($this->newInputfield());

		$this->check('empty default value is empty', true, $f->isEmpty());

		$a = $pages->newPageArray();
		$a->add($page);
		$f->attr('value', $a);
		$this->check('PageArray with page is not empty', false, $f->isEmpty());

		$f->derefAsPage = 1;
		$f->attr('value', $page);
		$this->check('derefAsPage Page value is not empty', false, $f->isEmpty());

		$this->check('isValidPage accepts matching page', true, InputfieldPage::isValidPage($page, $f));

		$f->parent_id = 99999999;
		$this->check('isValidPage rejects wrong parent', false, InputfieldPage::isValidPage($page, $f));
	}
}
