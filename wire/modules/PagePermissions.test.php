<?php namespace ProcessWire;

/** Tests secure image-file permissions for originals and derived files. */
class WireTest_PagePermissions extends WireTest {

	public function execute() {
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
}

/** Runs the normal secure-file authorization decision without sending HTTP headers or bytes. */
class WireTestable_PagesRequestImageAccess extends PagesRequest {
	public function allowsForTest(Page $page, User $user, $filename) {
		$this->requestFile = $filename;
		return $this->checkAccess($page, $user) === $page;
	}
}
