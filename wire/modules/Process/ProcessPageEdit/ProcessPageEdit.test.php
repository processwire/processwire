<?php namespace ProcessWire;

/**
 * Tests for unlocking pages in the editor and page list, including trashed children.
 *
 */
class WireTest_ProcessPageEdit extends WireTest {

	public function execute() {
		require_once(dirname(__DIR__) . '/ProcessPageList/ProcessPageListActions.php');
		$wire = $this->wire();
		$userPrevious = $wire->user;
		$postPrevious = $wire->input->post->getArray();
		$parent = $child = null;

		try {
			$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));
			$parent = $wire->pages->add($this->getTestTemplate(), $this->getTestPage(), [
				'name' => 'wire-test-unlock-' . uniqid(), 'title' => 'Unlock test',
			]);
			$child = $wire->pages->add($this->getTestTemplate(), $parent, [
				'name' => 'locked-child', 'title' => 'Locked child',
			]);
			$child->addStatus(Page::statusLocked);
			$child->save();

			$editor = $wire->wire(new WireTestable_ProcessPageEdit());
			$marker = "name='status[]' value='0'";
			$this->check('editable status render includes one empty-selection marker', 1, substr_count($editor->renderStatusForTest($child), $marker));
			$this->check('hidden status field does not emit the marker', false, strpos($editor->renderStatusForTest($child, Inputfield::collapsedHidden), $marker) !== false);
			$this->check('read-only status field does not emit the marker', false, strpos($editor->renderStatusForTest($child, Inputfield::collapsedNoLocked), $marker) !== false);
			$this->check('unopened AJAX status field does not emit the marker', false, strpos($editor->renderStatusForTest($child, Inputfield::collapsedYesAjax), $marker) !== false);

			$this->saveStatus($child, null);
			$child = $wire->pages->getFresh($child->id);
			$this->check('omitted POST status does not unlock a page even when the field exists', true, $child->isLocked());
			$this->saveStatus($child, [0]);
			$child = $wire->pages->getFresh($child->id);
			$this->check('editor can clear the only checked status', false, $child->isLocked());
			$child->of(false);
			$child->addStatus(Page::statusHidden);
			$child->addStatus(Page::statusUnpublished);
			$child->save();
			$statusPrevious = $child->status;
			$this->saveStatus($child, null);
			$child = $wire->pages->getFresh($child->id);
			$this->check('omitted POST status preserves statuses on an unlocked page', $statusPrevious, $child->status);
			$this->saveStatus($child, [0]);
			$child = $wire->pages->getFresh($child->id);
			$this->check('explicit empty selection clears editable statuses', false, $child->isHidden() || $child->isUnpublished());
			$actions = $wire->wire(new ProcessPageListActions());
			$this->check('page outside trash still offers Lock', true, isset($actions->getExtraActions($child)['lock']));
			$child->of(false);
			$child->addStatus(Page::statusLocked);
			$child->save();
			$this->saveStatus($child, [Page::statusHidden]);
			$child = $wire->pages->getFresh($child->id);
			$this->check('unlock can retain another selected status', true, !$child->isLocked() && $child->isHidden());

			$child->of(false);
			$child->removeStatus(Page::statusHidden);
			$child->addStatus(Page::statusLocked);
			$child->save();
			$wire->pages->trash($parent);
			$child = $wire->pages->getFresh($child->id);
			$this->check('locked descendant retains its lock in the trash', true, $child->isTrash() && $child->isLocked());

			$this->saveStatus($child, [Page::statusLocked]);
			$this->check('checked Locked status still prevents editing', true, $wire->pages->getFresh($child->id)->isLocked());
			$this->saveStatus($child, 'invalid');
			$this->check('malformed status submission does not unlock a page', true, $wire->pages->getFresh($child->id)->isLocked());
			$this->saveStatus($child, [0], false);
			$this->check('form without a status field cannot unlock a page', true, $wire->pages->getFresh($child->id)->isLocked());

			$superuser = $wire->user;
			$wire->users->setCurrentUser($wire->users->get($wire->config->guestUserPageID));
			$actions = $wire->wire(new ProcessPageListActions());
			$this->check('unauthorized user has no Unlock action', false, isset($actions->getExtraActions($child)['unlock']));
			$this->check('unauthorized Unlock request is denied', false, $actions->processAction($child, 'unlock')['success']);
			$this->saveStatus($child, [0]);
			$this->check('editor still requires page-lock permission', true, $wire->pages->getFresh($child->id)->isLocked());
			$wire->users->setCurrentUser($superuser);

			$this->saveStatus($child, [0]);
			$child = $wire->pages->getFresh($child->id);
			$this->check('editor can unlock a trashed child', false, $child->isLocked());
			$this->check('editor unlock preserves trash status', true, $child->isTrash());
			$actions = $wire->wire(new ProcessPageListActions());
			$this->check('unlocked trash page has no Lock action', false, isset($actions->getExtraActions($child)['lock']));
			$this->check('direct Lock request in trash is denied', false, $actions->processAction($child, 'lock')['success']);

			$child->of(false);
			$child->addStatus(Page::statusLocked);
			$child->save();
			$this->check('locked trash page offers Unlock', true, isset($actions->getExtraActions($child)['unlock']));
			$this->check('page-list Unlock succeeds in trash', true, $actions->processAction($child, 'unlock')['success']);
			$child = $wire->pages->getFresh($child->id);
			$this->check('page-list unlock persists and preserves trash status', true, !$child->isLocked() && $child->isTrash());
			$this->check('branch can be deleted after unlocking its child', 2, $wire->pages->delete($parent, true));
			$parent = $child = null;
		} finally {
			$wire->users->setCurrentUser($userPrevious);
			$wire->input->post->removeAll();
			$wire->input->post->setArray($postPrevious);
			if($child && $child->id) {
				$child = $wire->pages->getFresh($child->id);
				if($child->id) {
					$child->of(false);
					$child->removeStatus(Page::statusLocked);
					$child->save();
				}
			}
			if($parent && $parent->id) $wire->pages->delete($parent, true);
		}
	}

	protected function saveStatus(Page $page, $status, $includeStatus = true) {
		$post = $this->wire()->input->post;
		$post->removeAll();
		$post->set('submit_save', 1);
		if($status !== null) $post->set('status', $status);
		$editor = $this->wire(new WireTestable_ProcessPageEdit());
		$editor->saveStatusForTest($page, $includeStatus);
	}
}

/**
 * Exercises the real status processing and save, without redirecting a CLI request.
 *
 */
class WireTestable_ProcessPageEdit extends ProcessPageEdit {

	public function saveStatusForTest(Page $page, $includeStatus) {
		$this->prepareForTest($page, $includeStatus);
		$this->processSave();
	}

	public function renderStatusForTest(Page $page, $collapsed = Inputfield::collapsedNo) {
		$this->prepareForTest($page, true);
		$this->form->getChildByName('status')->collapsed = $collapsed;
		return $this->form->render();
	}

	protected function prepareForTest(Page $page, $includeStatus) {
		foreach(['modules', 'input', 'config', 'user', 'sanitizer', 'session', 'pages', 'languages'] as $api) {
			$this->$api = $this->wire()->wire($api);
		}
		$this->page = $page;
		$this->page->of(false);
		$this->id = $page->id;
		$this->isTrash = $page->isTrash();
		$this->set('noticeLocked', 'This page is locked for edits');
		$this->form = $this->modules->get('InputfieldForm');
		// Call the save handler directly; request/CSRF validation is outside this test.
		$this->form->protectCSRF = false;
		if($includeStatus) $this->form->add($this->buildFormStatus());
	}

	protected function ___processSaveRedirect($redirectUrl = '') {}
}
