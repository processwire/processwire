<?php namespace ProcessWire;

/**
 * Tests for ProcessWire InputfieldPageAutocomplete module.
 *
 */
class WireTest_InputfieldPageAutocomplete extends WireTest {

	/**
	 * @var User
	 *
	 */
	protected $previousUser;

	public function init() {
		$users = $this->wire()->users;
		$config = $this->wire()->config;
		$this->previousUser = $this->wire()->user;
		$users->setCurrentUser($users->get($config->superUserPageID));
	}

	public function execute() {
		$this->testBasicProperties();
		$this->testSelectedPages();
		$this->testAjaxUrl();
		$this->testRenderList();
		$this->testRender();
		$this->testProcessInput();
		$this->testConfigInputfields();
	}

	public function finish() {
		if($this->previousUser && $this->previousUser->id) {
			$this->wire()->users->setCurrentUser($this->previousUser);
		}
	}

	protected function newInputfield($name = 'related_pages') {
		$f = $this->wire()->modules->get('InputfieldPageAutocomplete');
		$f->attr('name', $name);
		return $f;
	}

	protected function processInput(InputfieldPageAutocomplete $f, $value) {
		$name = $f->attr('name');
		$data = array($name => $value);
		return $f->processInput(new WireInputData($data));
	}

	protected function testBasicProperties() {
		$f = $this->newInputfield();

		$this->check('module returns InputfieldPageAutocomplete', true, $f instanceof InputfieldPageAutocomplete);
		$this->check('implements array value interface', true, $f instanceof InputfieldHasArrayValue);
		$this->check('implements sortable value interface', true, $f instanceof InputfieldHasSortableValue);
		$this->check('default parent_id is 0', 0, $f->parent_id);
		$this->check('default template_id is 0', 0, $f->template_id);
		$this->check('default template_ids is array', array(), $f->template_ids);
		$this->check('default labelFieldName is title', 'title', $f->labelFieldName);
		$this->check('default searchFields is title', 'title', $f->searchFields);
		$this->check('default operator is percent equals', '%=', $f->operator);
		$this->check('default useList is true', true, $f->useList);
		$this->check('default allowAnyValue is false', false, $f->allowAnyValue);
		$this->check('default allowUnpub is null', null, $f->allowUnpub);
		$this->check('default usePageEdit is blank', '', $f->usePageEdit);
		$this->check('default hideDeleted is false', false, $f->hideDeleted);
	}

	protected function testSelectedPages() {
		$page = $this->getTestPage();
		$home = $this->wire()->pages->get('/');
		$f = $this->newInputfield();
		$f->val(array($page->id, $home->id));

		$selected = $f->getSelectedPages();
		$this->check('getSelectedPages returns PageArray', true, $selected instanceof PageArray);
		$this->check('getSelectedPages includes first selected page', true, $selected->has($page));
		$this->check('getSelectedPages includes second selected page', true, $selected->has($home));

		$f = $this->newInputfield();
		$f->parent_id = $page->parent_id;
		$f->template_id = $page->template->id;
		$f->val(array($page->id, $home->id));
		$selected = $f->getSelectedPages();
		$this->check('getSelectedPages respects parent/template restriction', true, $selected->has($page));
		$this->check('getSelectedPages excludes nonmatching parent/template', false, $selected->has($home));
	}

	protected function testAjaxUrl() {
		$modules = $this->wire()->modules;
		$pps = $modules->get('ProcessPageSearch');
		$page = $this->getTestPage();
		$f = $this->newInputfield('related_pages');
		$f->parent_id = $page->parent_id;
		$f->template_id = $page->template->id;
		$f->allowUnpub = 0;

		$url = $f->getAjaxUrl();
		$name = 'autocomplete_related_pages';
		$selector = $pps->getForSelector($name);

		$this->check('getAjaxUrl returns ProcessPageSearch URL', '/page/search/for?', $url, '*=');
		$this->check('getAjaxUrl includes selector name', "for_selector_name=$name", $url, '*=');
		$this->check('getAjaxUrl adds limit', 'limit=50', $url, '*=');
		$this->check('getAjaxUrl adds label field get parameter', 'get=title', $url, '*=');
		$this->check('stored ajax selector includes parent_id', "parent_id=$page->parent_id", $selector, '*=');
		$this->check('stored ajax selector includes template id', 'templates_id=' . $page->template->id, $selector, '*=');
		$this->check('allowUnpub false adds unpublished status constraint', 'status<' . Page::statusUnpublished, $selector, '*=');

		$f = $this->newInputfield('related_pages');
		$f->parent_id = $page->parent_id;
		$f->findPagesSelector = 'template=' . $page->template->name . ', sort=title';
		$f->allowUnpub = 1;
		$f->getAjaxUrl();
		$selector = $pps->getForSelector($name);
		$this->check('findPagesSelector plus parent uses has_parent', 'has_parent=' . $page->parent_id, $selector, '*=');
		$this->check('allowUnpub true omits unpublished status constraint', false, strpos($selector, 'status<' . Page::statusUnpublished) !== false);

		$f = $this->newInputfield('related_pages');
		$f->labelFieldFormat = '{title} [{name}]';
		$url = $f->getAjaxUrl();
		$this->check('labelFieldFormat adds format_name parameter', 'format_name=autocomplete_related_pages', $url, '*=');
	}

	protected function testRenderList() {
		$page = $this->getTestPage();
		$f = $this->newInputfield('related_pages');
		$f->val(array($page->id));

		$item = $f->renderListItem('A & B', $page->id, 'customClass', $page);
		$this->check('renderListItem returns list item', '<li ', $item, '*=');
		$this->check('renderListItem includes selected value', "<span class='itemValue'>$page->id</span>", $item, '*=');
		$this->check('renderListItem entity encodes label', 'A &amp; B', $item, '*=');
		$this->check('renderListItem includes custom class', 'customClass', $item, '*=');

		$list = $f->renderList();
		$this->check('renderList returns ordered list', '<ol ', $list, '*=');
		$this->check('renderList includes template item', 'itemTemplate', $list, '*=');
		$this->check('renderList includes selected page id', (string) $page->id, $list, '*=');

		$f->hideDeleted = true;
		$this->check('renderList hideDeleted adds class', "class='hideDeleted'", $f->renderList(), '*=');
	}

	protected function testRender() {
		$page = $this->getTestPage();
		$f = $this->newInputfield('related_pages');
		$f->val(array($page->id));
		$html = $f->render();

		$this->check('render includes hidden data input', 'InputfieldPageAutocompleteData', $html, '*=');
		$this->check('render appends array brackets to base name', "name='related_pages[]'", $html, '*=');
		$this->check('render includes visible autocomplete input', "id='Inputfield_related_pages_input'", $html, '*=');
		$this->check('render includes selected page id value', "value='$page->id'", $html, '*=');
		$this->check('render includes has_list class by default', 'has_list', $html, '*=');

		$f = $this->newInputfield('related_pages');
		$f->maxSelectedItems = 1;
		$f->val(array($page->id));
		$html = $f->render();
		$this->check('maxSelectedItems=1 switches to no_list', 'no_list', $html, '*=');
		$this->check('maxSelectedItems=1 sets data max', "data-max='1'", $html, '*=');

		$f = $this->newInputfield('related_pages[]');
		$html = $f->render();
		$this->check('name already ending brackets renders double brackets', "name='related_pages[][]'", $html, '*=');
	}

	protected function testProcessInput() {
		$f = $this->newInputfield('related_pages');
		$this->processInput($f, array('1015,1'));
		$this->check('processInput normalizes comma string to ID array', array(1015, 1), $f->val());

		$f = $this->newInputfield('related_pages');
		$this->processInput($f, '');
		$this->check('processInput blank value becomes empty array', array(), $f->val());
	}

	protected function testConfigInputfields() {
		$f = $this->newInputfield();
		$configFields = $f->getConfigInputfields();

		$this->check('config includes operator field', true, $configFields->getChildByName('operator') instanceof Inputfield);
		$this->check('config includes searchFields field', true, $configFields->getChildByName('searchFields') instanceof Inputfield);
		$this->check('config includes usePageEdit field', true, $configFields->getChildByName('usePageEdit') instanceof Inputfield);
		$this->check('config includes hideDeleted field', true, $configFields->getChildByName('hideDeleted') instanceof Inputfield);
	}
}

