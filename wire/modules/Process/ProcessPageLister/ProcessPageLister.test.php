<?php namespace ProcessWire;

/**
 * Tests for ProcessPageLister bookmark ownership and sharing.
 *
 */
class WireTest_ProcessPageLister extends WireTest {

	public function execute() {
		$this->testSelectorAccess();
		require_once(__DIR__ . '/ListerBookmarks.php');
		$users = $this->wire()->users;
		$owner = null;
		$reader = null;

		try {
			$owner = $users->add(uniqid('wiretest_lister_owner_'));
			$reader = $users->add(uniqid('wiretest_lister_reader_'));
			$ownerBookmarks = new ListerBookmarks($this->getTestPage(), $owner);
			$readerBookmarks = new ListerBookmarks($this->getTestPage(), $reader);
			$ownerBookmarks->saveOwnedBookmarks(array(
				'O1' => array('title' => 'Shared bookmark', 'desc' => 'Shared description', 'share' => true, 'type' => ListerBookmarks::typeOwned),
				'O2' => array('title' => 'Unshared bookmark', 'share' => false, 'type' => ListerBookmarks::typeOwned),
			));

			$sharedID = $owner->id . 'O1';
			$shared = $readerBookmarks->getBookmark($sharedID);
			$this->check('shared bookmark can still be opened by another user', true, is_array($shared));
			$this->check('shared bookmark remains viewable', true, $readerBookmarks->isBookmarkViewable($shared));
			$this->check('another user cannot edit a shared bookmark', false, $readerBookmarks->isBookmarkEditable($shared));
			$this->check('another user cannot delete a shared bookmark', false, $readerBookmarks->deleteBookmarkByID($sharedID));
			$this->check('unshared bookmark is not returned to another user', null, $readerBookmarks->getBookmark($owner->id . 'O2'));

			$own = $ownerBookmarks->getBookmark('O1');
			$this->check('owner can edit their bookmark', true, $ownerBookmarks->isBookmarkEditable($own));
			$this->check('owner can use an explicitly prefixed bookmark URL', true, $ownerBookmarks->isBookmarkEditable($ownerBookmarks->getBookmark($sharedID)));
			$this->check('non-superuser cannot edit public bookmarks', false, $readerBookmarks->isBookmarkEditable(array('type' => ListerBookmarks::typePublic)));

			$superuser = $users->get($this->wire()->config->superUserPageID);
			$adminBookmarks = new ListerBookmarks($this->getTestPage(), $superuser);
			$this->check('superuser can edit public bookmarks', true, $adminBookmarks->isBookmarkEditable(array('type' => ListerBookmarks::typePublic)));
			$this->check('shared private bookmarks are not edited through another account', false, $adminBookmarks->isBookmarkEditable($adminBookmarks->getBookmark($sharedID)));

			$stored = $ownerBookmarks->getUserSettings();
			$this->check('runtime bookmark ownership is not persisted', false, isset($stored['bookmarks']['O1']['owner']));
			$this->check('shared bookmark remains unchanged', 'Shared description', $ownerBookmarks->getBookmark('O1')['desc']);
		} finally {
			if($reader && $reader->id) $users->delete($reader);
			if($owner && $owner->id) $users->delete($owner);
		}
	}

	/** Verify that field restrictions apply before results or totals are selected. */
	protected function testSelectorAccess() {
		$wire = $this->wire();
		$previousUser = $wire->user;
		$superuser = $wire->users->get($wire->config->superUserPageID);
		$name = uniqid('wiretest_lister_access_');
		$role = $user = $root = null;
		$fields = $templates = $pages = $createdPermissions = array();
		try {
			$wire->users->setCurrentUser($superuser);
			$role = $wire->roles->add($name);
			foreach(array('page-view', 'page-edit', 'page-lister') as $permissionName) {
				$permission = $wire->permissions->get($permissionName);
				if(!$permission->id) {
					$permission = $wire->permissions->add($permissionName);
					$createdPermissions[] = $permission;
				}
				$role->addPermission($permission);
			}
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			foreach(array('normal', 'global', 'context', 'inverse', 'refs') as $kind) {
				$field = $wire->fields->new($kind === 'refs' ? 'FieldtypePage' : 'FieldtypeText', $name . '_' . $kind);
				$fields[$kind] = $field;
				if($kind === 'global' || $kind === 'inverse') {
					$field->useRoles = true;
					$field->viewRoles = array();
					$field->editRoles = array();
				}
				if($kind === 'context') $field->tags = $name . '_tag';
				$field->save();
			}
			foreach(array('allowed', 'denied', 'private') as $kind) {
				$template = $wire->templates->add($name . '_' . $kind);
				$templates[$kind] = $template;
				$template->useRoles = true;
				$template->setRoles($kind === 'private' ? array($wire->config->superUserRolePageID) : array($wire->config->guestUserRolePageID, $role->id), 'view');
				$template->setRoles(array($wire->config->superUserRolePageID), 'edit');
				$template->save();
				$template->fieldgroup->add($wire->fields->get('title'));
				foreach($fields as $field) $template->fieldgroup->add($field);
				$template->fieldgroup->save();
			}
			$context = $templates['denied']->fieldgroup->getFieldContext($fields['context']);
			$context->useRoles = true;
			$context->viewRoles = array();
			$context->editRoles = array();
			$templates['denied']->fieldgroup->saveFieldContext($context);
			$context = $templates['allowed']->fieldgroup->getFieldContext($fields['inverse']);
			$context->viewRoles = array($role->id);
			$templates['allowed']->fieldgroup->saveFieldContext($context);
			$root = $wire->pages->new(array('template' => $templates['allowed'], 'parent' => 1, 'name' => $name));
			foreach(array('allowed', 'denied', 'private', 'draft', 'trash') as $kind) {
				$template = $templates[isset($templates[$kind]) ? $kind : 'allowed'];
				$page = $wire->pages->new(array('template' => $template, 'parent' => $root, 'name' => $kind, 'title' => 'Synthetic Lister control'));
				$pages[$kind] = $page;
				foreach($fields as $field) if($field->type instanceof FieldtypeText) $page->set($field->name, 'SYNTHETIC-LISTER-MARKER');
				if($kind === 'draft') $page->addStatus(Page::statusUnpublished);
				$page->save();
				if($kind === 'trash') $wire->pages->trash($page);
			}
			$pages['allowed']->getUnformatted($fields['refs']->name)->add($pages['denied']);
			$pages['allowed']->save($fields['refs']->name);
			$wire->users->setCurrentUser($user);
			$lister = $this->wire(new WireTestable_ProcessPageListerAccess());
			$this->check('Lister access test account is non-superuser', false, $user->isSuperuser());
			$this->check('Lister access test account has required permission', true, $user->hasPermission('page-lister'));
			foreach(array('private', 'draft', 'trash') as $kind) {
				foreach(array('', ', include=all', ', check_access=0') as $modifier) {
					$this->check("Lister excludes $kind source with modifier '$modifier'", 0, $lister->countForTest('id=' . $pages[$kind]->id . $modifier));
				}
			}
			$ids = $pages['allowed']->id . '|' . $pages['denied']->id;
			$base = "id=$ids";
			$normal = $fields['normal']->name;
			$global = $fields['global']->name;
			$contextName = $fields['context']->name;
			$inverse = $fields['inverse']->name;
			$refs = $fields['refs']->name;
			$this->check('normal filters retain permitted pages', 2, $lister->countForTest("$base, $normal%=SYNTHETIC-LISTER-MARKER"));
			$this->check('normal result membership remains unchanged', array($pages['allowed']->id, $pages['denied']->id), $lister->idsForTest("$base, $normal%=SYNTHETIC-LISTER-MARKER"));
			foreach(array('SYNTHETIC-LISTER-MARKER', 'SYNTHETIC-ABSENT') as $value) {
				$this->check('globally restricted matching and nonmatching counts stay zero', 0, $lister->countForTest("$base, $global%=$value"));
			}
			$this->check('template context excludes restricted field matches only', 1, $lister->countForTest("$base, $contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('restricted contexts do not influence result membership', array($pages['allowed']->id), $lister->idsForTest("$base, $contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('globally restricted fields return no page IDs', array(), $lister->idsForTest("$base, $global%=SYNTHETIC-LISTER-MARKER"));
			$this->check('explicit permitted context remains searchable', 1, $lister->countForTest("$base, template={$templates['allowed']->name}, $contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('explicit restricted context contributes no results', 0, $lister->countForTest("$base, template={$templates['denied']->name}, $contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('context override may grant access to a globally restricted field', 1, $lister->countForTest("$base, $inverse%=SYNTHETIC-LISTER-MARKER"));
			$this->check('OR field filters cannot use restricted contexts', 1, $lister->countForTest("$base, $normal|$contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('OR groups cannot use restricted contexts', 1, $lister->countForTest("$base, choice=($contextName%=SYNTHETIC-LISTER-MARKER), choice=($normal%=SYNTHETIC-ABSENT)"));
			$this->check('OR values retain field-context protection', 1, $lister->countForTest("$base, $contextName=SYNTHETIC-LISTER-MARKER|SYNTHETIC-ABSENT"));
			$this->check('OR values inside groups retain context protection', 1, $lister->countForTest("$base, choice=($contextName=SYNTHETIC-LISTER-MARKER|SYNTHETIC-ABSENT)"));
			$this->check('negative comparisons cannot reveal denied contexts', 1, $lister->countForTest("$base, $contextName!=SYNTHETIC-ABSENT"));
			$this->check('named grouped fields retain context protection', 1, $lister->countForTest("$base, choice@$contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('field tag filters cannot bypass context checks', 1, $lister->countForTest("$base, {$name}_tag%=SYNTHETIC-LISTER-MARKER"));
			$this->check('sorting cannot use restricted contexts', 1, $lister->countForTest("$base, sort=$contextName"));
			$this->check('permitted related field query remains usable', 1, $lister->countForTest("$base, $refs.$normal%=SYNTHETIC-LISTER-MARKER"));
			$this->check('permitted subselector remains usable', 1, $lister->countForTest("$base, $refs=[$normal%=SYNTHETIC-LISTER-MARKER]"));
			$this->check('configured permitted predicate retains allowed contexts', 1, $wire->pages->count($lister->configuredSelectorForTest($base, "$contextName%=SYNTHETIC-LISTER-MARKER")));
			$this->check('configured restricted predicate does not bypass field access', 0, $wire->pages->count($lister->configuredSelectorForTest($base, "$global%=SYNTHETIC-LISTER-MARKER")));
			foreach(array("$refs.$contextName%=SYNTHETIC-LISTER-MARKER", "parent.$global%=SYNTHETIC-LISTER-MARKER", "$refs=[$contextName%=SYNTHETIC-LISTER-MARKER]", 'choice=(check_access=0)', 'pass%=SYNTHETIC') as $query) {
				$lister->errors('clear');
				$this->check('unsafe related/nested filter produces no matches: ' . $query, 0, $lister->countForTest("$base, $query"));
				$this->check('rejected filter records an error notice', true, count($lister->errors('clear')) > 0);
			}
			$this->check('getSelector fails closed for bookmark callers without throwing', 'id=0', $lister->configuredSelectorForTest("$base, $refs.$contextName%=SYNTHETIC-LISTER-MARKER"));
			$this->check('configured related-field rejection also fails closed', 'id=0', $lister->configuredSelectorForTest($base, "parent.$global%=SYNTHETIC-LISTER-MARKER"));
			$rejectedOutput = $lister->configuredSelectorForTest("$base, $refs.$contextName%=SYNTHETIC-LISTER-MARKER", '', true);
			$this->check('AJAX renderer displays rejected query as no results', true, strpos($rejectedOutput, 'No results.') !== false);
			$this->check('AJAX renderer includes normal error notice markup', true, strpos($rejectedOutput, 'ui-state-error-text') !== false);
			$lister->errors('clear');
			$wire->users->setCurrentUser($superuser);
			foreach(array('allowed', 'denied') as $kind) {
				$pages[$kind]->of(false);
				$pages[$kind]->setAndSave($contextName, 'SYNTHETIC-LISTER-MARKER title=');
			}
			$wire->users->setCurrentUser($user);
			$this->check('named group with parenthesized selector-like value retains field checks', 1, $lister->countForTest("$base, choice@$contextName%=(title=)"));
			$this->check('named group with literal parenthesized value remains usable', 1, $lister->countForTest("$base, choice@$contextName%=(SYNTHETIC-LISTER-MARKER)"));
			$this->check('empty group follows PageFinder nested-selector semantics', 2, $lister->countForTest("$base, @$contextName=(title=Synthetic Lister control)"));
			$this->check('zero group follows PageFinder nested-selector semantics', 2, $lister->countForTest("$base, 0@$contextName=(title=Synthetic Lister control)"));
			$wire->users->setCurrentUser($superuser);
			$this->check('superuser retains restricted field queries', 2, $lister->countForTest("$base, $global%=SYNTHETIC-LISTER-MARKER"));
		} finally {
			$wire->users->setCurrentUser($superuser);
			foreach(array_reverse($pages) as $page) if($page && $page->id) $wire->pages->delete($page, true);
			if($root && $root->id) $wire->pages->delete($root, true);
			foreach($templates as $template) {
				$group = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($group);
			}
			foreach(array_reverse($fields) as $field) $wire->fields->delete($field);
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
			foreach($createdPermissions as $permission) $wire->permissions->delete($permission);
			$wire->users->setCurrentUser($previousUser);
		}
	}
}

/** Runs the normal Lister validator before querying or counting. */
class WireTestable_ProcessPageListerAccess extends ProcessPageLister {
	public function countForTest($selector) {
		$selector = $this->validateSelector($selector);
		return $this->wire()->pages->count($selector, array('allowCustom' => true));
	}
	public function idsForTest($selector) {
		$ids = $this->wire()->pages->findIDs($this->validateSelector($selector));
		sort($ids);
		return $ids;
	}
	public function configuredSelectorForTest($selector, $initSelector = '', $render = false) {
		$previousInit = $this->initSelector;
		$previousTotal = $this->knownTotal;
		$sessionValues = array();
		foreach(array('selector', 'sort', 'knownTotal', 'loadedFiles', 'loadedJSConfig') as $key) $sessionValues[$key] = $this->sessionGet($key);
		try {
			$this->initSelector = $initSelector;
			$this->sessionSet('selector', $selector);
			$this->sessionSet('sort', 'name');
			return $render ? $this->renderResults() : $this->getSelector(0);
		} finally {
			$this->initSelector = $previousInit;
			$this->knownTotal = $previousTotal;
			foreach($sessionValues as $key => $value) $this->sessionSet($key, $value);
		}
	}
}
