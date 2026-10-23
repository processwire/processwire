<?php namespace ProcessWire;

/** Tests page-cloning access and secure image-file permissions. */
class WireTest_PagePermissions extends WireTest {

	public function execute() {
		$this->testCloneAccess();
		$wire = $this->wire();
		$admin = $wire->user;
		$superuser = $wire->users->get($wire->config->superUserPageID);
		$name = uniqid('wiretest_secure_');
		$template = $page = $reader = null;
		$fields = $files = $images = array();
		try {
			$wire->users->setCurrentUser($superuser);
			$template = $wire->templates->add($name);
			$template->pagefileSecure = 2;
			$template->useRoles = true;
			$template->setRoles(array($wire->config->guestUserRolePageID), 'view');
			$template->setRoles(array($wire->roles->get('superuser')->id), 'edit');
			$template->save();
			foreach(array('hidden', 'permitted', 'context') as $access) {
				$field = $wire->fields->new('FieldtypeImage', $name . '_' . $access);
				$fields[$access] = $field;
				$field->useRoles = true;
				$field->viewRoles = $access === 'hidden' ? array() : array($wire->config->guestUserRolePageID);
				$field->editRoles = array();
				$field->save();
				$template->fieldgroup->add($field);
			}
			$template->fieldgroup->save();
			$context = $template->fieldgroup->getFieldContext($fields['context']);
			$context->viewRoles = array();
			$template->fieldgroup->saveFieldContext($context);
			$page = $wire->pages->newPage(array('template' => $template, 'parent' => 1, 'name' => $name));
			$page->save();
			$fixturePath = __DIR__ . '/Fieldtype/FieldtypeImage/tests/images/';
			foreach($fields as $access => $field) {
				$value = $page->getUnformatted($field->name);
				$value->add($fixturePath . ($access === 'permitted' ? 'test2.png' : 'test1.jpg'));
				$image = $value->last();
				$image->rename($access . '.sample.' . $image->ext);
				$page->save($field->name);
			}
			$page = $wire->pages->getFresh($page->id);
			$page->of(false);
			foreach($fields as $access => $field) {
				$image = $page->getUnformatted($field->name)->first();
				$images[$access] = $image;
				$variation = $image->width(24);
				$nested = $variation->width(16);
				$files[$access] = array('original' => $image->basename, 'variation' => $variation->basename, 'nested variation' => $nested->basename);
				foreach(array('original' => $image, 'variation' => $variation) as $kind => $source) {
					$shortFile = '';
					foreach(array(false, true) as $useSrcExt) {
						$extra = $source->webp(array('useSrcExt' => $useSrcExt));
						$extra->url(false);
						// Also exercise existing long-name files when the resize engine uses short names.
						if(!$extra->exists() && $useSrcExt && $shortFile) $wire->files->copy($shortFile, $extra->filename());
						if(!$extra->exists()) continue;
						if(!$useSrcExt) $shortFile = $extra->filename();
						$key = $kind . ($useSrcExt ? ' long WebP' : ' short WebP');
						$files[$access][$key] = basename($extra->filename());
					}
				}
			}
			$reader = $wire->users->add($name);
			$guest = $wire->users->get($wire->config->guestUserPageID);
			$request = $this->wire(new WireTestable_PagesRequestImageAccess($wire->pages));
			$before = glob($page->filesPath() . '*');
			foreach(array('guest' => $guest, 'reader' => $reader, 'admin' => $superuser) as $label => $user) {
				$wire->users->setCurrentUser($user);
				$this->check("$label can view the fixture page", true, $page->viewable(false));
				foreach($files as $access => $names) {
					foreach($names as $kind => $filename) {
						$expected = $access === 'permitted' || $label === 'admin';
						$this->check("$label access to $access $kind", $expected, $request->allowsForTest($page, $user, $filename));
					}
				}
			}
			$this->check('permission checks do not generate extra files', $before, glob($page->filesPath() . '*'));

			$wire->users->setCurrentUser($guest);
			$unknown = 'unmanaged-image.jpg';
			$wire->files->copy($images['permitted']->filename, $page->filesPath() . $unknown);
			$this->check('unrecognized existing files retain undecided field access', null, $page->viewable($unknown));
			$this->check('nonexistent filenames retain undecided field access', null, $page->viewable('missing-image.jpg'));
			$wire->files->copy($images['permitted']->filename, $page->filesPath() . 'hidden.sample-unrelated.jpg');
			$this->check('filename prefixes alone do not imply image ownership', null, $page->viewable('hidden.sample-unrelated.jpg'));
			$page->of(true);
			$this->check('formatted pages still deny restricted variations', false, $request->allowsForTest($page, $guest, $files['hidden']['variation']));
			$this->check('permission checks preserve output formatting', true, $page->of());
			$page->of(false);
			$images['permitted']->isTemp(true);
			$this->check('guest cannot access a variation of a temporary image', false, $request->allowsForTest($page, $guest, $files['permitted']['variation']));
			$images['permitted']->isTemp(false);

			// Short WebP names omit source extensions, so multiple fields can claim the same file.
			$wire->users->setCurrentUser($superuser);
			foreach(array('hidden' => 'test1.jpg', 'permitted' => 'test2.png') as $access => $fixture) {
				$value = $page->getUnformatted($fields[$access]->name);
				$value->add($fixturePath . $fixture);
				$image = $value->last();
				$image->rename('shared.' . $image->ext);
				$page->save($fields[$access]->name);
				$extra = $image->webp(array('useSrcExt' => false));
				$extra->url(false);
			}
			if($extra->exists()) {
				$wire->users->setCurrentUser($guest);
				$this->check('ambiguous short WebP is denied if any possible owner is restricted', false, $request->allowsForTest($page, $guest, basename($extra->filename())));
			}
		} finally {
			$wire->users->setCurrentUser($admin);
			if($page && $page->id) $wire->pages->delete($page, true);
			if($template && $template->id) {
				$fieldgroup = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($fieldgroup);
			}
			foreach($fields as $field) $wire->fields->delete($field);
			if($reader && $reader->id) $wire->users->delete($reader);
		}
	}

	/** Cloning may copy viewable content, but must not expose unpublished source content. */
	protected function testCloneAccess() {
		$wire = $this->wire();
		$previousUser = $wire->user;
		$superuser = $wire->users->get($wire->config->superUserPageID);
		$name = uniqid('wiretest_clone_access_');
		$previousPost = $wire->input->post->getArray();
		$role = $user = $template = $root = $copy = null;
		$permissionsCreated = array();
		try {
			$wire->users->setCurrentUser($superuser);
			$role = $wire->roles->add($name);
			foreach(array('page-view', 'page-edit', 'page-clone', 'page-clone-tree', 'page-edit-created') as $permissionName) {
				$permission = $wire->permissions->get($permissionName);
				if(!$permission->id) {
					$permission = $wire->permissions->add($permissionName);
					$permissionsCreated[] = $permission;
				}
				$role->addPermission($permission);
			}
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			$template = $wire->templates->add($name);
			$template->useRoles = true;
			$template->setRoles(array($wire->config->guestUserRolePageID, $role->id), 'view');
			foreach(array('edit', 'create', 'add') as $type) $template->setRoles(array($role->id), $type);
			$template->save();
			$template->fieldgroup->add($wire->fields->get('title'));
			$template->fieldgroup->save();
			$root = $wire->pages->new(array('template' => $template, 'parent' => 1, 'name' => $name, 'title' => 'Clone access test'));
			$published = $wire->pages->new(array('template' => $template, 'parent' => $root, 'name' => 'published', 'title' => 'Synthetic published control'));
			$unpublished = $wire->pages->new(array('template' => $template, 'parent' => $root, 'name' => 'unpublished', 'title' => 'Synthetic unpublished content', 'status' => Page::statusUnpublished));
			$ownDraft = $wire->pages->new(array('template' => $template, 'parent' => $root, 'name' => 'own-draft', 'status' => Page::statusUnpublished, 'created_users_id' => $user->id));
			$tree = $wire->pages->new(array('template' => $template, 'parent' => $root, 'name' => 'tree'));
			$branch = $wire->pages->new(array('template' => $template, 'parent' => $tree, 'name' => 'branch'));
			$secret = $wire->pages->new(array('template' => $template, 'parent' => $branch, 'name' => 'secret', 'status' => Page::statusUnpublished));
			$wire->users->setCurrentUser($user);
			$this->check('clone control user is not superuser', false, $user->isSuperuser());
			$this->check('clone control parent allows adding', true, $root->addable());
			$this->check('published source is viewable without requiring a template file', true, $published->viewable(false));
			$this->check('published source cannot be edited', false, $published->editable());
			$this->check('viewable non-editable source remains cloneable', true, $published->cloneable(false));
			$this->check('other user unpublished source is not viewable', false, $unpublished->viewable(false));
			$this->check('other user unpublished source cannot be cloned', false, $unpublished->cloneable(false));
			$this->check('own editable draft remains viewable', true, $ownDraft->viewable(false));
			$this->check('own editable draft remains cloneable', true, $ownDraft->cloneable(false));
			$this->check('visible root can still be copied without children', true, $tree->cloneable(false));
			$this->check('tree with non-viewable grandchild cannot be copied recursively', false, $tree->cloneable(true));
			$this->check('default clone check also protects descendants', false, $tree->cloneable());
			if($wire->modules->isInstalled('ProcessPageClone')) {
				$module = $wire->modules->get('ProcessPageClone');
				$result = $module->processAjax($unpublished, true);
				$this->check('AJAX clone rejects non-viewable source', false, $result['success']);
				$this->check('rejected AJAX clone creates no page', 0, $result['page']);
				$result = $module->processAjax($published, true);
				$copy = $wire->pages->get($result['page']);
				$this->check('AJAX clone preserves viewable source copying', true, $result['success']);
				$this->check('permitted copy is editable by its creator', true, $copy->editable());
				$formModule = $this->wire(new WireTestable_ProcessPageCloneAccess());
				$post = $wire->input->post;
				$post->removeAll();
				$post->set('clone_page_tree', 1);
				$post->set('clone_page_name', 'denied-tree-copy');
				$post->set('clone_page_title', 'Denied tree copy');
				$post->set($wire->session->CSRF->getTokenName(), $wire->session->CSRF->getTokenValue());
				$count = $wire->pages->count("parent=$root->id, include=all");
				$denied = false;
				try {
					$formModule->processTreeForTest($tree);
				} catch(WirePermissionException $e) {
					$denied = true;
				}
				$this->check('form processor rejects tree containing non-viewable content', true, $denied);
				$this->check('rejected tree form creates no partial copy', $count, $wire->pages->count("parent=$root->id, include=all"));
				$post->removeAll();
				foreach($previousPost as $key => $value) $post->set($key, $value);
			}
			$secret->of(false);
			$secret->removeStatus(Page::statusUnpublished);
			$secret->save();
			$this->check('tree containing only viewable pages remains cloneable', true, $tree->cloneable(true));
			$role->removePermission('page-clone-tree');
			$role->save();
			$this->check('recursive cloning still requires tree permission', false, $tree->cloneable(true));
			$this->check('single-page copying does not require tree permission', true, $tree->cloneable(false));
			$wire->users->setCurrentUser($superuser);
			$this->check('superuser retains unpublished source cloning', true, $unpublished->cloneable(false));
		} finally {
			$wire->users->setCurrentUser($superuser);
			$wire->input->post->removeAll();
			foreach($previousPost as $key => $value) $wire->input->post->set($key, $value);
			if($copy && $copy->id) $wire->pages->delete($copy, true);
			if($root && $root->id) $wire->pages->delete($root, true);
			if($template && $template->id) {
				$group = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($group);
			}
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
			foreach($permissionsCreated as $permission) $wire->permissions->delete($permission);
			$wire->users->setCurrentUser($previousUser);
		}
	}
}

/** Runs the normal secure-file authorization decision without sending HTTP headers or bytes. */
class WireTestable_PagesRequestImageAccess extends PagesRequest {
	public function allowsForTest(Page $page, User $user, $filename) {
		$this->requestFile = $filename;
		return $this->checkAccess($page, $user) === $page;
	}
}

/** Exposes the normal form processor while retaining CSRF and authorization checks. */
class WireTestable_ProcessPageCloneAccess extends ProcessPageClone {
	public function processTreeForTest(Page $page) {
		$this->page = $page;
		$this->process();
	}
}
