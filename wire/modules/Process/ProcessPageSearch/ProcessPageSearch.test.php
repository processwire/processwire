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
		$this->role->addPermission('page-view');
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
		$this->testLiveFieldSearch();
	}

	protected function testLiveFieldSearch() {
		$wire = $this->wire();
		$templates = $pages = array();
		$hidden = null;
		$name = uniqid('wiretest_live_');
		$wire->users->setCurrentUser($this->admin);
		try {
			$hidden = $wire->fields->new('FieldtypeText', $name);
			$hidden->useRoles = true;
			$hidden->viewRoles = $hidden->editRoles = array();
			$hidden->save();
			foreach(array('allowed', 'denied') as $access) {
				$template = $wire->templates->add($name . '_' . $access);
				$templates[$access] = $template;
				$template->useRoles = true;
				$template->setRoles(array($this->role->id), 'view');
				$template->setRoles(array($this->role->id), 'edit');
				$template->save();
				$template->fieldgroup->add($wire->fields->get('title'));
				$template->fieldgroup->add($this->field);
				$template->fieldgroup->add($hidden);
				$template->fieldgroup->save();
				if($access === 'denied') {
					$context = $template->fieldgroup->getFieldContext($this->field);
					$context->viewRoles = array();
					$template->fieldgroup->saveFieldContext($context);
				}
				$p = $wire->pages->newPage(array('template' => $template, 'parent' => 1, 'name' => $name . '_' . $access));
				$pages[$access] = $p;
				$p->title = 'Live search fixture';
				$p->set($this->field->name, 'wiretest-live-match');
				$p->set($hidden->name, 'wiretest-live-match');
				$p->save();
			}
			$wire->users->setCurrentUser($this->user);
			$this->check('live-search fixture page is viewable', true, $pages['denied']->viewable(false));
			$this->check('live-search fixture field is context restricted', false, $pages['denied']->viewable($this->field));
			$process = $this->wire(new ProcessPageSearch());
			$live = new WireTestable_ProcessPageSearchLive($process);
			$fieldName = $this->field->name;
			foreach(array('wiretest-live-match', 'wiretest-live-miss') as $value) {
				$matches = $live->searchForTest("pages.$hidden->name^=$value", 1);
				$this->check('globally restricted field yields no matches or pagination: ' . $value, array(), $matches);
				$matches = $live->searchForTest($templates['denied']->name . ".$fieldName^=$value", 1);
				$this->check('context-restricted field yields no matches or pagination: ' . $value, array(), $matches);
			}
			$matches = $live->searchForTest("$hidden->name^=wiretest-live-match", 1, true);
			$this->check('field-name shorthand cannot bypass permissions', array(), $matches);
			$matches = $live->searchForTest(array('type' => 'pages', 'property' => $hidden->name, 'operator' => '^=', 'q' => 'wiretest-live-match'), 1);
			$this->check('separate property parameters cannot bypass permissions', array(), $matches);
			$matches = $live->searchForTest("pages.$fieldName^=wiretest-live-match", 1);
			$this->check('cross-template search only returns the permitted context', array($pages['allowed']->id), array_column($matches, 'id'));
			$this->check('cross-template result count excludes restricted context', '1/1', $matches[0]['n']);
			$matches = $live->searchForTest($templates['allowed']->name . ".$fieldName^=wiretest-live-match");
			$this->check('template-specific permitted field remains searchable', array($pages['allowed']->id), array_column($matches, 'id'));
			$matches = $live->searchForTest('pages.id=' . $pages['allowed']->id);
			$this->check('native property search remains available', array($pages['allowed']->id), array_column($matches, 'id'));
			$info = $live->initForTest('pages.pass^=wiretest-live-match');
			$this->check('password fields are excluded before querying', true, in_array('templates_id=0', $info['selectors']));

			// A template context can also grant access denied by the global field settings.
			$wire->users->setCurrentUser($this->admin);
			$context = $templates['allowed']->fieldgroup->getFieldContext($hidden);
			$context->viewRoles = array($this->role->id);
			$templates['allowed']->fieldgroup->saveFieldContext($context);
			$wire->users->setCurrentUser($this->user);
			$matches = $live->searchForTest("pages.$hidden->name^=wiretest-live-match", 1);
			$this->check('a globally restricted field is searchable in an explicitly permitted context', array($pages['allowed']->id), array_column($matches, 'id'));
		} finally {
			$wire->users->setCurrentUser($this->admin);
			foreach($pages as $p) if($p->id) $wire->pages->delete($p, true);
			foreach($templates as $template) {
				$fieldgroup = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($fieldgroup);
			}
			if($hidden && $hidden->id) $wire->fields->delete($hidden);
			$wire->users->setCurrentUser($this->user);
		}
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

/** Exercises live query construction and page results without rendering the admin UI. */
require_once(__DIR__ . '/ProcessPageSearchLive.php');
class WireTestable_ProcessPageSearchLive extends ProcessPageSearchLive {
	public function initForTest($query, $limit = 15) {
		$presets = is_array($query) ? $query : array('q' => $query);
		$presets['limit'] = $limit;
		return $this->init($presets);
	}

	public function searchForTest($query, $limit = 15, $fullSearch = false) {
		$info = $this->initForTest($query, $limit);
		return $fullSearch ? $this->find($info) : $this->findPages($info);
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
