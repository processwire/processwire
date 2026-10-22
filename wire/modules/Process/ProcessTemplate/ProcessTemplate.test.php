<?php namespace ProcessWire;

/** Tests access to the template import endpoint and its navigation button. */
class WireTest_ProcessTemplate extends WireTest {
	public function execute() {
		$wire = $this->wire();
		$previousUser = $wire->user;
		$superuser = $wire->users->get($wire->config->superUserPageID);
		$post = $wire->input->post;
		$get = $wire->input->get;
		$previousPost = $post->getArray();
		$previousGet = $get->getArray();
		$name = uniqid('wiretest_tadmin_');
		$role = $user = $permission = null;
		$createdPermission = false;
		$breadcrumbHook = null;
		$previousHeadline = $wire->processHeadline;
		try {
			$wire->users->setCurrentUser($superuser);
			$post->removeAll();
			$get->removeAll();
			$permission = $wire->permissions->get('template-admin');
			if(!$permission->id) {
				$permission = $wire->permissions->add('template-admin');
				$createdPermission = true;
			}
			$role = $wire->roles->add($name);
			$role->addPermission('page-view');
			$role->addPermission($permission);
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			$module = $wire->modules->get('ProcessTemplate');
			// CLI tests have no admin breadcrumb service; leave authorization and import handling intact.
			$breadcrumbHook = $module->addHookBefore('breadcrumb', function($event) { $event->replace = true; });
			$this->check('delegated administrator is not superuser', false, $user->isSuperuser());
			$this->check('delegated administrator has template permission', true, $user->hasPermission('template-admin'));
			$wire->users->setCurrentUser($user);
			foreach(array('form', 'preview', 'commit') as $stage) {
				$post->removeAll();
				if($stage === 'preview') $post->set('import_data', '{}');
				if($stage === 'commit') $post->set('submit_commit', 1);
				$denied = false;
				try {
					$module->executeImport();
				} catch(WirePermissionException $e) {
					$denied = true;
				}
				$this->check("delegated administrator cannot access import $stage", true, $denied);
			}
			$post->removeAll();
			$list = $module->execute();
			$this->check('delegated template listing remains available', true, strlen($list) > 0);
			$this->check('delegated listing hides import button', false, strpos($list, 'id="import_button"') !== false);
			$this->check('delegated listing retains export button', true, strpos($list, 'id="export_button"') !== false);
			$wire->users->setCurrentUser($superuser);
			$list = $module->execute();
			$this->check('superuser listing retains import button', true, strpos($list, 'id="import_button"') !== false);
			if(!class_exists(__NAMESPACE__ . '\\ProcessTemplateExportImport', false)) {
				$form = $module->executeImport();
				$this->check('superuser can open import form', true, strpos($form, 'import_data') !== false);
			}
		} finally {
			$wire->users->setCurrentUser($superuser);
			if($breadcrumbHook) $module->removeHook($breadcrumbHook);
			$wire->wire('processHeadline', $previousHeadline);
			$post->removeAll();
			$get->removeAll();
			foreach($previousPost as $key => $value) $post->set($key, $value);
			foreach($previousGet as $key => $value) $get->set($key, $value);
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
			if($createdPermission && $permission) $wire->permissions->delete($permission);
			$wire->users->setCurrentUser($previousUser);
		}
	}
}
