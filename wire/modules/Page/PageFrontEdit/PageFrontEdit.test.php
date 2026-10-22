<?php namespace ProcessWire;

/**
 * Tests for ProcessWire PageFrontEdit.
 *
 */
class WireTest_PageFrontEdit extends WireTest {

	public function allow() {
		if(!$this->wire()->modules->isInstalled('PageFrontEdit')) {
			$this->li('PageFrontEdit is not installed');
			return false;
		}
		return true;
	}

	public function execute() {
		$this->testPageStateAndAjaxUrl();
		$this->testInlineSupported();
		$this->testHookPageEditorBasics();
		$this->testNoEditMarkupCleanup();
		$this->testRenderAssets();
		$this->testInlineResponseAccess();
	}

	protected function testInlineResponseAccess() {
		$wire = $this->wire();
		$admin = $wire->user;
		$postPrevious = $wire->input->post->getArray();
		$outputFormatting = $wire->pages->of();
		$name = uniqid('wiretest_front_');
		$role = $user = null;
		$templates = $pages = $fields = array();
		try {
			$role = $wire->roles->add($name);
			foreach(array('page-view', 'page-edit', 'page-edit-front') as $permission) $role->addPermission($permission);
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			foreach(array('allowed', 'hidden', 'readonly') as $access) {
				$field = $wire->fields->new('FieldtypeText', $name . '_' . $access);
				$fields[$access] = $field;
				$field->useRoles = true;
				$field->viewRoles = $access === 'hidden' ? array() : array($role->id);
				$field->editRoles = $access === 'allowed' ? array($role->id) : array();
				$field->save();
			}
			foreach(array('own', 'other') as $access) {
				$template = $wire->templates->add($name . '_' . $access);
				$templates[$access] = $template;
				$template->useRoles = true;
				$roles = $access === 'own' ? array($role->id) : array($wire->roles->get('superuser')->id);
				$template->setRoles($roles, 'view');
				$template->setRoles($roles, 'edit');
				$template->save();
				foreach($fields as $field) $template->fieldgroup->add($field);
				$template->fieldgroup->save();
				$page = $wire->pages->newPage(array('template' => $template, 'parent' => 1, 'name' => $name . '_' . $access));
				$pages[$access] = $page;
				foreach($fields as $fieldAccess => $field) $page->set($field->name, "synthetic-$access-$fieldAccess");
				$page->save();
			}
			$wire->users->setCurrentUser($user);
			$pfe = $this->wire(new WireTestable_PageFrontEdit());
			$pfe->activateForTest($pages['own']);
			$this->check('response test permitted field is saveable', true, $pfe->inlineIsSaveable($pages['own'], $fields['allowed']));
			$this->check('response test other page is not viewable', false, $pages['other']->viewable(false));
			$this->check('response test hidden field is not viewable', false, $pages['own']->viewable($fields['hidden']));
			$rejected = array();
			foreach($pages as $access => $page) {
				foreach($fields as $fieldAccess => $field) {
					if($access === 'own' && $fieldAccess === 'allowed') continue;
					$rejected[$page->id . '__' . $field->name] = 'rejected-update';
				}
			}
			$data = $pfe->processForTest($rejected);
			$this->check('rejected-only request has no formatted response values', array(), $data['formatted']);
			$this->check('rejected-only request has no unformatted response values', array(), $data['unformatted']);

			$allowedKey = $pages['own']->id . '__' . $fields['allowed']->name;
			$postFields = $rejected + array(
				$allowedKey => 'permitted-update',
				'missingseparator' => 'invalid',
				$pages['own']->id . '__' . $name . '_missing' => 'invalid',
				'0__' . $fields['allowed']->name => 'invalid',
			);
			$data = $pfe->processForTest($postFields);
			$this->check('mixed request returns only the authorized formatted field', array($allowedKey), array_keys($data['formatted']));
			$this->check('mixed request returns only the authorized unformatted field', array($allowedKey), array_keys($data['unformatted']));
			$this->check('permitted save response remains available', 'permitted-update', $data['unformatted'][$allowedKey]);
			foreach($pages as $access => $page) {
				$fresh = $wire->pages->getFresh($page->id);
				foreach($fields as $fieldAccess => $field) {
					$expected = $access === 'own' && $fieldAccess === 'allowed' ? 'permitted-update' : "synthetic-$access-$fieldAccess";
					$this->check("response test $access/$fieldAccess persisted value", $expected, $fresh->getUnformatted($field->name));
				}
			}

			$pfe->failValidationForTest = $fields['allowed']->name;
			$data = $pfe->processForTest(array($allowedKey => 'invalid-update'));
			$this->check('authorized validation failure reports an error', true, strpos($data['error'], 'Fixture validation failure') !== false);
			$this->check('authorized validation failure returns the previous value', 'permitted-update', $data['unformatted'][$allowedKey]);
			$this->check('authorized validation failure does not change storage', 'permitted-update', $wire->pages->getFresh($pages['own']->id)->getUnformatted($fields['allowed']->name));
		} finally {
			$wire->users->setCurrentUser($admin);
			$wire->pages->of($outputFormatting);
			$wire->input->post->removeAll();
			$wire->input->post->setArray($postPrevious);
			foreach($pages as $page) if($page->id) $wire->pages->delete($page, true);
			foreach($templates as $template) {
				$fieldgroup = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($fieldgroup);
			}
			foreach($fields as $field) $wire->fields->delete($field);
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
		}
	}

	protected function editor() {
		return $this->wire()->modules->get('PageFrontEdit');
	}

	protected function testPageStateAndAjaxUrl() {
		$pfe = $this->editor();
		$page = $this->getTestPage();

		$pfe->setPage($page);
		$this->check('setPage() stores edited page', $page->id, $pfe->getPage()->id);
		$this->check('setPage() marks page for front editing', true, (bool) $page->get('_PageFrontEdit'));
		$this->check('getAjaxPostUrl() returns current page URL by default', $this->wire()->page->url, $pfe->getAjaxPostUrl());

		$hookId = $this->wire()->addHookAfter('PageFrontEdit::getAjaxPostUrl', function(HookEvent $event) {
			$event->return .= '?wire_test=1';
		});
		$this->check('getAjaxPostUrl() is hookable', '?wire_test=1', $pfe->getAjaxPostUrl(), '$=');
		$this->wire()->removeHook($hookId);
	}

	protected function testInlineSupported() {
		$pfe = $this->editor();
		$fields = $this->wire()->fields;
		$title = $fields->get('title');
		$images = $fields->get('images');

		$this->check('inlineSupported() supports title/text field', true, $pfe->inlineSupported($title));
		if($images) {
			$this->check('inlineSupported() rejects object-value image field', false, $pfe->inlineSupported($images));
		} else {
			$this->ok('images field not present, skipping image inlineSupported() check');
		}

		$allowed = $pfe->inlineAllowFieldtypes;
		$pfe->inlineAllowFieldtypes = array('FieldtypeInteger');
		$this->check('inlineSupported() honors inlineAllowFieldtypes setting', false, $pfe->inlineSupported($title));
		$pfe->inlineAllowFieldtypes = $allowed;
	}

	protected function testHookPageEditorBasics() {
		$pfe = $this->editor();
		$page = $this->getTestPage();
		$page->set('wire_test_runtime', 'Runtime value');

		$event = $this->event($page, array());
		$pfe->hookPageEditor($event);
		$this->check('Page::edit() with no args returns active state', true, is_bool($event->return));

		$event = $this->event($page, array(false));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(false) returns page', $page->id, $event->return->id);

		$event = $this->event($page, array());
		$pfe->hookPageEditor($event);
		$this->check('Page::edit() reflects disabled state', false, $event->return);

		$event = $this->event($page, array(true));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(true) returns page', $page->id, $event->return->id);

		$event = $this->event($page, array('title', false));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(field, false) returns formatted value', $page->getFormatted('title'), $event->return);

		$event = $this->event($page, array('wire_test_runtime'));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(non-field) delegates to page value', 'Runtime value', $event->return);

		$event = $this->event($page, array(array('bad')));
		$pfe->hookPageEditor($event);
		$this->check('Page::edit(invalid arg) reports invalid argument', 'Invalid argument', $event->return, '*=');
	}

	protected function testNoEditMarkupCleanup() {
		$pfe = $this->editor();
		$page = $this->getTestPage();
		$html = '<html><body><edit title><h1>Title</h1></edit><div class="x" edit="title">Body</div></body></html>';
		$event = new HookEvent(array(
			'object' => $page,
			'method' => 'render',
			'return' => $html,
		));

		$pfe->hookPageRenderNoEdit($event);
		$this->check('hookPageRenderNoEdit() removes opening edit tag', false, strpos($event->return, '<edit'));
		$this->check('hookPageRenderNoEdit() removes closing edit tag', false, strpos($event->return, '</edit>'));
		$this->check('hookPageRenderNoEdit() preserves wrapped markup', '<h1>Title</h1>', $event->return, '*=');
		$this->check('hookPageRenderNoEdit() removes edit attribute', false, strpos($event->return, ' edit='));
	}

	protected function testRenderAssets() {
		$pfe = $this->editor();
		$page = $this->getTestPage();
		$pfe->setPage($page);

		$out = $pfe->renderAssets();

		$this->check('renderAssets() includes PageFrontEdit loader', 'PageFrontEditLoad.js', $out, '*=');
		$this->check('renderAssets() includes edited page id', "value='{$page->id}'", $out, '*=');
		$this->check('renderAssets() includes CSRF token field', $this->wire()->session->CSRF->getTokenName(), $out, '*=');
		$this->check('renderAssets() includes save button markup', 'pw-edit-save', $out, '*=');
	}

	protected function event(Page $page, array $arguments) {
		return new HookEvent(array(
			'object' => $page,
			'method' => 'edit',
			'arguments' => $arguments,
		));
	}
}

/** Exposes processing without the HTTP response/exit wrapper; permission checks stay intact. */
class WireTestable_PageFrontEdit extends PageFrontEdit {
	public $failValidationForTest = '';

	public function activateForTest(Page $page) {
		$this->setPage($page);
		$this->editorAllowed = $page->editable() && $this->wire()->user->hasPermission('page-edit-front', $page);
	}

	public function processForTest(array $fields) {
		return $this->inlineProcessSaveEdits($fields, array('status' => 0, 'error' => '', 'changes' => '', 'formatted' => array(), 'unformatted' => array()));
	}

	protected function getInputfield(Page $page, Field $field) {
		$inputfield = parent::getInputfield($page, $field);
		if($field->name === $this->failValidationForTest) {
			$inputfield->addHookAfter('processInput', function(HookEvent $event) {
				$event->object->error('Fixture validation failure');
			});
		}
		return $inputfield;
	}
}
