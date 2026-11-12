<?php namespace ProcessWire;

/**
 * Tests for ProcessPageEditImageSelect
 *
 */
class WireTest_ProcessPageEditImageSelect extends WireTest {

	public function execute() {
		$module = $this->wire(new WireTestable_ProcessPageEditImageSelect());
		$module->setTestPage($this->wire()->pages->get(1));
		$module->setRte(true);

		$denied = false;
		try {
			$module->processSaveForTest();
		} catch(WirePermissionException $e) {
			$denied = true;
		}

		$this->check('RTE requests cannot invoke non-RTE image save actions', true, $denied);
		$this->testRequestTokens($module);
		$this->testFieldAccess();
		$this->testRepeaterFieldAccess();
	}

	/**
	 * Images in repeater items take page access from the page the repeater lives on, while field
	 * access still applies (processwire/processwire-issues#2375)
	 *
	 */
	protected function testRepeaterFieldAccess() {
		$wire = $this->wire();
		$modules = $wire->modules;
		$userPrevious = $wire->user;
		$getPrevious = $wire->input->get->getArray();
		$name = uniqid('wiretest_imgrep_');
		$fields = array();
		$repeater = $template = $page = $role = $user = null;
		$modules->get('FieldtypeRepeater')->getFieldClass();

		try {
			$role = $wire->roles->add($name);
			$role->addPermission('page-view');
			$role->addPermission('page-edit');
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			$template = $wire->templates->add($name);
			$template->useRoles = true;
			$template->setRoles(array($role->id), 'view');
			$template->setRoles(array($role->id), 'edit');
			$template->save();
			foreach(array('open', 'hidden') as $access) {
				$field = $wire->fields->new('FieldtypeImage', $name . '_' . $access);
				if($access === 'hidden') {
					$field->useRoles = true;
					$field->viewRoles = array();
					$field->editRoles = array();
				}
				$field->save();
				$fields[$access] = $field;
			}
			$repeater = new RepeaterField();
			$repeater->name = $name . '_repeater';
			$repeater->type = $modules->get('FieldtypeRepeater');
			$repeater->save();
			$repeaterTemplate = $repeater->type->_getRepeaterTemplate($repeater);
			$repeaterTemplate->set('pageClass', 'RepeaterPage'); // as FieldtypeRepeater::initField() does for existing fields
			$repeaterTemplate->save();
			$repeaterFieldgroup = $repeaterTemplate->fieldgroup;
			foreach($fields as $field) $repeaterFieldgroup->add($field);
			$repeaterFieldgroup->save();
			$repeater->repeaterFields = array($fields['open']->id, $fields['hidden']->id);
			$repeater->save();
			$template->fieldgroup->add($repeater);
			$template->fieldgroup->save();

			$page = $wire->pages->newPage(array('template' => $template, 'parent' => 1, 'name' => $name));
			$page->save();
			$page->of(false);
			$item = $page->get($repeater->name)->getNew();
			$item->save();
			$images = array();
			foreach(array('open' => 'test1.jpg', 'hidden' => 'test2.png') as $access => $filename) {
				$item->getUnformatted($fields[$access]->name)->add(dirname(__DIR__, 2) . '/Fieldtype/FieldtypeImage/tests/images/' . $filename);
				$item->save($fields[$access]->name);
				$images[$access] = $item->getUnformatted($fields[$access]->name)->first();
			}
			$page->get($repeater->name)->add($item);
			$page->save($repeater->name);
			$item = $wire->pages->getFresh($item->id);

			$wire->users->setCurrentUser($user);
			$this->check('limited editor can edit the page the repeater lives on', true, $page->editable());
			$module = $this->wire(new WireTestFieldAccess_ProcessPageEditImageSelect());
			$results = array();
			foreach($images as $access => $image) {
				foreach(array(false, true) as $rte) {
					$module->configure($item, $fields[$access], $image->name, $rte);
					$module->setMasterPage($page);
					$wire->input->get->set('file', $item->id . ',' . $image->name);
					try {
						$found = $module->getPageimage()->name === $image->name;
					} catch(WireException $e) {
						$found = false;
					}
					$results[$access . ($rte ? '-rte' : '-edit')] = $found;
				}
			}
			$this->check('repeater image can be edited by user who can edit the owning page', true, $results['open-edit']);
			$this->check('repeater image can be selected in RTE by user who can edit the owning page', true, $results['open-rte']);
			$this->check('field access still applies to images in repeater items', array(false, false), array($results['hidden-edit'], $results['hidden-rte']));
		} finally {
			$wire->users->setCurrentUser($userPrevious);
			$wire->input->get->removeAll();
			$wire->input->get->setArray($getPrevious);
			if($page && $page->id) $wire->pages->delete($page, true);
			if($template && $template->id) {
				$fieldgroup = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($fieldgroup);
			}
			if($repeater && $repeater->id) $wire->fields->delete($repeater);
			foreach($fields as $field) {
				foreach($field->getFieldgroups() as $fieldgroup) {
					$fieldgroup->remove($field);
					$fieldgroup->save();
				}
				$wire->fields->delete($field);
			}
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
		}
		$this->check('temporary repeater test field removed', null, $wire->fields->get($name . '_repeater'));
	}

	protected function testFieldAccess() {
		$wire = $this->wire();
		$userPrevious = $wire->user;
		$getPrevious = $wire->input->get->getArray();
		$postPrevious = $wire->input->post->getArray();
		$fields = array();
		$template = $page = $role = $user = null;
		$name = uniqid('wiretest_img_');

		try {
			$role = $wire->roles->add($name);
			$role->addPermission('page-view');
			$role->addPermission('page-edit');
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			$template = $wire->templates->add($name);
			$template->useRoles = true;
			$template->setRoles(array($role->id), 'view');
			$template->setRoles(array($role->id), 'edit');
			$template->save();
			$fixtures = array('hidden' => 'test1.jpg', 'readonly' => 'test2.png', 'editable' => 'GIF-google.gif');
			foreach($fixtures as $access => $filename) {
				$field = $wire->fields->new('FieldtypeImage', $name . '_' . $access);
				$fields[$access] = $field;
				$field->useRoles = true;
				$field->viewRoles = $access === 'hidden' ? array() : array($role->id);
				$field->editRoles = $access === 'editable' ? array($role->id) : array();
				$field->save();
				$template->fieldgroup->add($field);
			}
			$template->fieldgroup->save();
			$page = $wire->pages->newPage(array('template' => $template, 'parent' => 1, 'name' => $name));
			$page->save();
			$images = array();
			foreach($fixtures as $access => $filename) {
				$field = $fields[$access];
				$page->getUnformatted($field->name)->add(dirname(__DIR__, 2) . '/Fieldtype/FieldtypeImage/tests/images/' . $filename);
				$page->save($field->name);
			}
			$page = $wire->pages->getFresh($page->id);
			$page->of(false);
			foreach($fields as $access => $field) $images[$access] = $page->getUnformatted($field->name)->first();
			$wire->users->setCurrentUser($user);
			$this->check('limited editor has page edit permission', true, $page->editable());
			$this->check('hidden image field is not viewable', false, $page->viewable($fields['hidden']));
			$this->check('read-only image field is viewable but not editable', true, $page->viewable($fields['readonly']) && !$page->editable($fields['readonly']));
			$module = $this->wire(new WireTestFieldAccess_ProcessPageEditImageSelect());
			$module->configure($page, null, '', true);
			$listed = $module->getImages($page);
			$results = array();
			foreach($images as $access => $image) {
				$field = $fields[$access];
				$key = $page->id . ',' . $image->name;
				$module->configure($page, $field, $image->name, true);
				$wire->input->get->set('file', $key);
				$resolved = true;
				try {
					$module->getPageimage();
				} catch(WireException $e) {
					$resolved = false;
				}
				// Do not rely on the caller supplying an image field in the request.
				$module->configure($page);
				$directResolved = true;
				try {
					$module->getPageimage();
				} catch(WireException $e) {
					$directResolved = false;
				}
				$this->check("$access image direct-edit resolution without a field parameter", $access === 'editable', $directResolved);
				$before = hash_file('sha256', $image->filename);
				$module->configure($page, $field, $image->name);
				$wire->input->post->setArray(array('width' => 16, 'height' => 16, 'use_resize' => 1));
				$denied = false;
				try {
					$module->saveForTest();
				} catch(WireException $e) {
					$denied = true;
				}
				$changed = hash_file('sha256', $image->filename) !== $before;
				$results[$access] = array(isset($listed[$key]), $resolved, $denied, $changed);
				$this->li("$access image: listed=" . (int) isset($listed[$key]) . ', resolved=' . (int) $resolved . ', save denied=' . (int) $denied . ', original changed=' . (int) $changed);
			}
			$this->check('hidden field cannot be listed, resolved or replaced', array(false, false, true, false), $results['hidden']);
			$this->check('read-only field can be selected but not replaced', array(true, true, true, false), $results['readonly']);
			$this->check('editable field can still be selected and replaced', array(true, true, false, true), $results['editable']);

			// RTE reuse does not require edit permission on the source page.
			$template->setRoles(array(), 'edit');
			$this->check('source-page control is no longer editable', false, $page->editable());
			$module->configure($page, $fields['readonly'], '', true);
			$wire->input->get->set('file', $page->id . ',' . $images['readonly']->name);
			$this->check('RTE can still resolve a viewable image on a non-editable source page', $images['readonly']->name, $module->getPageimage()->name);
		} finally {
			$wire->users->setCurrentUser($userPrevious);
			$wire->input->get->removeAll();
			$wire->input->get->setArray($getPrevious);
			$wire->input->post->removeAll();
			$wire->input->post->setArray($postPrevious);
			if($page && $page->id) $wire->pages->delete($page, true);
			if($template && $template->id) {
				$fieldgroup = $template->fieldgroup;
				$wire->templates->delete($template);
				$wire->fieldgroups->delete($fieldgroup);
			}
			foreach($fields as $field) $wire->fields->delete($field);
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
		}
		$this->check('temporary test user removed', 0, (int) $wire->users->get($name)->id);
		$this->check('temporary test role removed', 0, (int) $wire->roles->get($name)->id);
		$this->check('temporary test template removed', null, $wire->templates->get($name));
	}

	protected function testRequestTokens(WireTestable_ProcessPageEditImageSelect $module) {
		$input = $this->wire()->input;
		$config = $this->wire()->config;
		$postPrevious = $input->post->getArray();
		$protectPrevious = $config->protectCSRF;
		$methodPrevious = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
		$config->protectCSRF = true;

		try {
			foreach(array('executeEdit' => 'crop', 'executeResize' => 'resize') as $method => $action) {
				foreach(array(null, 'invalid') as $value) {
					$token = $this->wire()->session->CSRF()->getToken();
					$input->post->removeAll();
					if($action === 'crop') $input->post->set('submit_crop', '1');
					if($value !== null) $input->post->set($token['name'], $value);
					$module->testImageRequested = false;
					$denied = false;
					try {
						$module->$method();
					} catch(WireCSRFException $e) {
						$denied = true;
					} catch(\RuntimeException $e) {
						if(!$module->testImageRequested) throw $e;
					}
					$label = $value === null ? 'missing' : 'invalid';
					$this->check("RTE $action rejects $label token before processing an image", true, $denied && !$module->testImageRequested);
				}

				$token = $this->wire()->session->CSRF()->getToken();
				$input->post->set($token['name'], $token['value']);
				$module->testImageRequested = false;
				try {
					$module->$method();
				} catch(\RuntimeException $e) {
					if(!$module->testImageRequested) throw $e;
				}
				$this->check("RTE $action with a valid token reaches image processing", true, $module->testImageRequested);
			}

			// outdated browser-cached editor plugins request resize by GET without a token (processwire/processwire-issues#2373)
			$_SERVER['REQUEST_METHOD'] = 'GET';
			$input->post->removeAll();
			$module->testImageRequested = false;
			$message = '';
			try {
				$module->executeResize();
			} catch(WireException $e) {
				$message = $e instanceof WireCSRFException ? 'csrf' : $e->getMessage();
			} catch(\RuntimeException $e) {
				if(!$module->testImageRequested) throw $e;
			}
			$this->check('RTE resize by GET without a token explains the outdated editor script', true,
				strpos($message, 'outdated') !== false && !$module->testImageRequested);
		} finally {
			if($methodPrevious === null) {
				unset($_SERVER['REQUEST_METHOD']);
			} else {
				$_SERVER['REQUEST_METHOD'] = $methodPrevious;
			}
			$input->post->removeAll();
			$input->post->setArray($postPrevious);
			$config->protectCSRF = $protectPrevious;
		}
	}
}

/** Uses the real image resolution and save paths with isolated fixture state. */
class WireTestFieldAccess_ProcessPageEditImageSelect extends ProcessPageEditImageSelect {
	public function configure(Page $page, ?Field $field = null, $original = '', $rte = false) {
		$this->page = $this->masterPage = $page;
		$this->field = $field;
		$this->fieldName = $field ? $field->name : '';
		$this->original = $original;
		$this->rte = $rte;
		$this->labels = array('noAccess' => 'No image edit access', 'updating' => 'Updating');
	}

	public function saveForTest() {
		return $this->processSave(true);
	}

	public function setMasterPage(Page $page) {
		$this->masterPage = $page;
	}
}

/**
 * Exposes protected state and behavior required by the test.
 *
 */
class WireTestable_ProcessPageEditImageSelect extends ProcessPageEditImageSelect {
	public $testImageRequested = false;

	public function setTestPage(Page $page) {
		$this->page = $page;
		$this->masterPage = $page;
	}

	public function setRte($rte) {
		$this->rte = (bool) $rte;
	}

	public function processSaveForTest() {
		return $this->processSave(false);
	}

	public function getPageimage($getVariation = false) {
		$this->testImageRequested = true;
		throw new \RuntimeException('Image processing reached by test');
	}
}
