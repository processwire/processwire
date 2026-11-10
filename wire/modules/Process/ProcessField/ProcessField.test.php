<?php namespace ProcessWire;

/** Tests the field administration boundary and legacy field configuration portability. */
class WireTest_ProcessField extends WireTest {
	public function execute() {
		$wire = $this->wire();
		$previousUser = $wire->user;
		$superuser = $wire->users->get($wire->config->superUserPageID);
		$name = uniqid('wiretest_field_admin_');
		$role = $user = $permission = $module = null;
		$createdPermission = false;
		$previousPost = $wire->input->post->getArray();
		$previousHeadline = $wire->processHeadline;
		$hook = null;
		try {
			$wire->users->setCurrentUser($superuser);
			$field = $wire->wire(new Field());
			$field->type = $wire->fieldtypes->get('FieldtypePage');
			$field->name = $name;
			$field->label = 'Original label';
			$field->findPagesCode = 'legacy-synthetic-setting';
			$export = $field->getExportData();
			$this->check('field exports omit the retired legacy setting', false, array_key_exists('findPagesCode', $export));
			$this->check('export does not remove the existing stored setting', 'legacy-synthetic-setting', $field->getTableData()['data']['findPagesCode']);
			$changes = $field->setImportData(array('label' => 'Updated label', 'findPagesCode' => 'replacement-synthetic-setting'));
			$this->check('normal field imports still update labels', 'Updated label', $field->label);
			$this->check('normal field changes remain in the preview', true, isset($changes['label']));
			$this->check('legacy setting is absent from the import preview', false, isset($changes['findPagesCode']));
			$this->check('import preserves an existing legacy setting', 'legacy-synthetic-setting', $field->findPagesCode);
			$fresh = $wire->wire(new Field());
			$fresh->type = $field->type;
			$fresh->name = $name . '_fresh';
			$fresh->setImportData(array('findPagesCode' => 'synthetic-setting'));
			$this->check('imports cannot introduce the setting on a new field', true, empty($fresh->findPagesCode));
			$field->setImportData(array('findPagesCode' => ''));
			$this->check('imports cannot silently clear an existing legacy setting', 'legacy-synthetic-setting', $field->findPagesCode);

			$permission = $wire->permissions->get('field-admin');
			if(!$permission->id) {
				$permission = $wire->permissions->add('field-admin');
				$createdPermission = true;
			}
			$role = $wire->roles->add($name);
			$role->addPermission('page-view');
			$role->addPermission($permission);
			$role->save();
			$user = $wire->users->add($name);
			$user->addRole($role);
			$user->save();
			$this->check('test account holds the former delegated permission', true, $user->hasPermission('field-admin'));
			$this->check('field administration declares no delegated permission', '', ProcessField::getModuleInfo()['permission']);
			$module = $wire->wire(new ProcessField());
			$module->init();
			$hook = $module->addHookBefore('breadcrumb', function($event) { $event->replace = true; });
			$wire->users->setCurrentUser($user);
			$denied = false;
			try { $wire->wire(new ProcessField())->init(); }
			catch(WirePermissionException $e) { $denied = true; }
			$this->check('old delegated permission cannot initialize field administration', true, $denied);
			foreach(array('form', 'preview', 'commit') as $stage) {
				$wire->input->post->removeAll();
				if($stage === 'preview') $wire->input->post->set('import_data', '{}');
				if($stage === 'commit') $wire->input->post->set('submit_commit', 1);
				$denied = false;
				try { $module->executeImport(); }
				catch(WirePermissionException $e) { $denied = true; }
				$this->check("delegated import $stage is denied even on an already initialized module", true, $denied);
			}
			$wire->users->setCurrentUser($superuser);
			$wire->input->post->removeAll();
			$form = $module->executeImport();
			$this->check('superuser can still open the normal field import form', true, strpos($form, 'import_data') !== false);
		} finally {
			$wire->users->setCurrentUser($superuser);
			if($module && $hook) $module->removeHook($hook);
			$wire->wire('processHeadline', $previousHeadline);
			$wire->input->post->removeAll()->setArray($previousPost);
			if($user && $user->id) $wire->users->delete($user);
			if($role && $role->id) $wire->roles->delete($role);
			if($createdPermission && $permission && $permission->id) $wire->permissions->delete($permission);
			$wire->users->setCurrentUser($previousUser);
		}
	}
}
