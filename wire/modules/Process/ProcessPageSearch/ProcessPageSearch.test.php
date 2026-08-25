<?php namespace ProcessWire;

/**
 * Tests for ProcessPageSearch
 *
 */
class WireTest_ProcessPageSearch extends WireTest {

	protected $field;
	protected $fieldgroup;
	protected $role;
	protected $user;
	protected $admin;
	protected $page;

	public function init() {
		$name = 'wire_test_page_search_context';
		$this->admin = $this->wire()->user;
		$this->page = $this->getTestPage();
		$this->fieldgroup = $this->page->template->fieldgroup;

		$this->role = $this->wire()->roles->add($name);
		$this->role->addPermission('page-edit');
		$this->role->save();

		$this->user = $this->wire()->users->add($name);
		$this->user->pass = bin2hex(random_bytes(12));
		$this->user->addRole($this->role);
		$this->user->save();

		$this->field = $this->wire()->fields->new('FieldtypeText', $name, 'Page search context test');
		$this->field->useRoles = true;
		$this->field->viewRoles = array($this->role->id);
		$this->field->save();
		$this->fieldgroup->add($this->field);
		$this->fieldgroup->save();

		$context = $this->fieldgroup->getFieldContext($this->field);
		$context->viewRoles = array();
		$this->fieldgroup->saveFieldContext($context);

		$this->page->of(false);
		$this->page->set($this->field->name, 'context-restricted-value');
		$this->page->save($this->field->name);
	}

	public function execute() {
		$this->wire()->users->setCurrentUser($this->user);
		$this->wire()->pages->uncacheAll();
		$page = $this->wire()->pages->get($this->page->id);
		$matches = $this->wire(new PageArray());
		$matches->add($page);
		$module = $this->wire(new WireTestable_ProcessPageSearch());

		$this->check('global field access is allowed', true, $this->field->viewable());
		$this->check('template-context field access is denied', false, $page->viewable($this->field));

		$json = $module->renderAjaxForTest($matches, array($this->field->name));
		$this->check('AJAX field output omits context-restricted value', false, strpos($json, 'context-restricted-value') !== false);

		$display = array(
			'name' => 'label',
			'format' => '{' . $this->field->name . '} - {title}',
			'textOnly' => true,
		);
		$json = $module->renderAjaxForTest($matches, $display);
		$this->check('AJAX display format omits context-restricted value', false, strpos($json, 'context-restricted-value') !== false);
		$this->check('AJAX display format retains viewable value', true, strpos($json, $page->title) !== false);

		$html = $module->renderTableForTest($matches, array($this->field->name, 'title'));
		$this->check('HTML field output omits context-restricted value', false, strpos($html, 'context-restricted-value') !== false);
		$this->check('HTML field output retains viewable value', true, strpos($html, $page->title) !== false);
	}

	public function finish() {
		if($this->admin) $this->wire()->users->setCurrentUser($this->admin);
		if($this->user && $this->user->id) $this->wire()->users->delete($this->user);
		if($this->role && $this->role->id) $this->wire()->roles->delete($this->role);
		if($this->field && $this->field->id) {
			if($this->fieldgroup && $this->fieldgroup->hasField($this->field)) {
				$this->fieldgroup->remove($this->field);
				$this->fieldgroup->save();
			}
			$this->wire()->fields->delete($this->field);
		}
	}
}

/**
 * Exposes protected render methods required by the test.
 *
 */
class WireTestable_ProcessPageSearch extends ProcessPageSearch {

	public function renderAjaxForTest(PageArray $matches, array $display) {
		return $this->renderMatchesAjax($matches, $display, '');
	}

	public function renderTableForTest(PageArray $matches, array $display) {
		return $this->renderMatchesTable($matches, $display);
	}
}
