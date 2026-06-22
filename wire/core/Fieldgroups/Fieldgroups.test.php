<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $fieldgroups API variable
 *
 */
class WireTest_Fieldgroups extends WireTest {

	protected $prefix = WireTests::fieldPrefix . 'fieldgroups';
	protected $fieldNames = array();
	protected $fieldgroupNames = array();

	public function init() {
		$this->cleanup();
	}

	public function execute() {
		$fieldgroups = $this->wire()->fieldgroups;

		$this->check('$fieldgroups is Fieldgroups', true, $fieldgroups instanceof Fieldgroups);
		$this->check('$fieldgroups->get(basic-page) returns Fieldgroup', true, $fieldgroups->get('basic-page') instanceof Fieldgroup);
		$this->check('$fieldgroups->get(missing) returns null', null, $fieldgroups->get($this->name('missing')));
		$this->check('$fieldgroups is iterable over Fieldgroup objects', true, $this->firstFieldgroupIsFieldgroup());

		$this->testCreateAndLookup();
		$this->testMembership();
		$this->testTemplatesAndDeleteProtection();
		$this->testContext();
		$this->testCloneExportImport();
	}

	public function finish() {
		$this->cleanup();
	}

	protected function testCreateAndLookup() {
		$fieldgroups = $this->wire()->fieldgroups;
		$fields = $this->wire()->fields;
		$alpha = $this->createField('alpha', 'Alpha Field');
		$beta = $this->createField('beta', 'Beta Field');

		$unsavedName = $this->name('unsaved');
		$unsaved = $fieldgroups->newFieldgroup($unsavedName, array('title', $alpha));
		$this->fieldgroupNames[] = $unsavedName;
		$this->check('newFieldgroup() returns Fieldgroup', true, $unsaved instanceof Fieldgroup);
		$this->check('newFieldgroup() does not save fieldgroup', 0, $unsaved->id);
		$this->check('newFieldgroup() sanitizes and sets name', $unsavedName, $unsaved->name);
		$this->check('newFieldgroup(addFields) accepts Field object', true, $unsaved->hasField($alpha));
		$this->check('newFieldgroup(addFields) accepts field name', true, $unsaved->hasField('title'));
		$unsaved->save();
		$this->check('Fieldgroup::save() persists new fieldgroup', true, $unsaved->id > 0);
		$this->check('$fieldgroups->get(name) returns saved fieldgroup', $unsaved->id, $fieldgroups->get($unsavedName)->id);
		$this->check('$fieldgroups->get(id) returns saved fieldgroup', $unsavedName, $fieldgroups->get($unsaved->id)->name);

		$savedName = $this->name('saved');
		$saved = $fieldgroups->new($savedName, array($beta->id));
		$this->fieldgroupNames[] = $savedName;
		$this->check('$fieldgroups->new() creates and saves fieldgroup', true, $saved->id > 0);
		$this->check('$fieldgroups->new(addFields) accepts field ID', true, $saved->hasField($beta));

		$threw = false;
		try {
			$fieldgroups->newFieldgroup($savedName);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('newFieldgroup() throws for duplicate name', true, $threw);

		$fieldNames = $fieldgroups->getFieldNames($unsaved);
		$this->check('getFieldNames(object) returns title indexed by ID', 'title', $fieldNames[$fields->get('title')->id]);
		$this->check('getFieldNames(object) returns temp field indexed by ID', $alpha->name, $fieldNames[$alpha->id]);

		$basic = $fieldgroups->get('basic-page');
		$fieldNamesByName = $fieldgroups->getFieldNames('basic-page');
		$this->check('getFieldNames(name) returns existing field', 'title', $fieldNamesByName[$fields->get('title')->id]);
		$fieldNamesById = $fieldgroups->getFieldNames($basic->id);
		$this->check('getFieldNames(id) returns existing field', 'title', $fieldNamesById[$fields->get('title')->id]);
	}

	protected function testMembership() {
		$fieldgroups = $this->wire()->fieldgroups;
		$fields = $this->wire()->fields;
		$fieldgroup = $fieldgroups->get($this->name('unsaved'));
		$alpha = $fields->get($this->name('alpha'));
		$beta = $fields->get($this->name('beta'));
		$gamma = $this->createField('gamma', 'Gamma Field');

		$this->check('getField(name) returns Field', $alpha->id, $fieldgroup->getField($alpha->name)->id);
		$this->check('getField(id) returns Field', $alpha->name, $fieldgroup->getField($alpha->id)->name);
		$this->check('getField(Field) returns Field', $alpha->id, $fieldgroup->getField($alpha)->id);
		$this->check('getField(missing) returns null', null, $fieldgroup->getField($this->name('missing')));
		$this->check('hasField(existing) returns true', true, $fieldgroup->hasField($alpha));
		$this->check('hasField(missing) returns false', false, $fieldgroup->hasField($beta));
		$this->check('get(fields) returns Fieldgroup itself', true, $fieldgroup->get('fields') === $fieldgroup);
		$this->check('fields_id includes temp field ID', true, in_array($alpha->id, $fieldgroup->fields_id));

		$fieldgroup->add($gamma);
		$fieldgroup->save();
		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('add() + save() persists field membership', true, $fieldgroup->hasField($gamma));

		$fieldgroup->insertBefore($gamma, $alpha);
		$fieldgroup->save();
		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('WireArray order changes persist after save()', $gamma->name, $this->fieldNamesInGroup($fieldgroup)[1]);

		$this->check('softRemove() removes field from memory', true, $fieldgroup->softRemove($gamma) instanceof Fieldgroup);
		$this->check('softRemove() does not queue removedFields', null, $fieldgroup->removedFields);
		$fieldgroup->save();
		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('softRemove() + save() removes membership', false, $fieldgroup->hasField($gamma));

		$fieldgroup->add($gamma);
		$fieldgroup->save();
		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('remove() queues field removal', true, $fieldgroup->remove($gamma));
		$this->check('remove() leaves field available until save()', true, $fieldgroup->hasField($gamma));
		$this->check('remove() records removedFields', true, $fieldgroup->removedFields->has($gamma));
		$fieldgroup->save();
		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('remove() + save() removes membership even without templates', false, $fieldgroup->hasField($gamma));
		$this->check('remove(missing) returns false', false, $fieldgroup->remove($this->name('missing')));
		$this->check('softRemove(missing) returns false', false, $fieldgroup->softRemove($this->name('missing')));
	}

	protected function testTemplatesAndDeleteProtection() {
		$fieldgroups = $this->wire()->fieldgroups;
		$basic = $fieldgroups->get('basic-page');

		$this->check('getTemplates() returns TemplatesArray', true, $basic->getTemplates() instanceof TemplatesArray);
		$this->check('getTemplates() includes basic-page template', true, $basic->getTemplates()->has($this->wire()->templates->get('basic-page')));
		$this->check('getNumTemplates() returns count', true, $basic->getNumTemplates() >= 1);
		$this->check('numTemplates() aliases getNumTemplates()', $basic->getNumTemplates(), $basic->numTemplates());

		$threw = false;
		try {
			$fieldgroups->delete($basic);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('delete() throws for fieldgroup used by template', true, $threw);
	}

	protected function testContext() {
		$fieldgroups = $this->wire()->fieldgroups;
		$fields = $this->wire()->fields;
		$fieldgroup = $fieldgroups->get($this->name('unsaved'));
		$alpha = $fields->get($this->name('alpha'));

		$context = $fieldgroup->getFieldContext($alpha);
		$this->check('getFieldContext() returns clone', true, $context !== $alpha);
		$this->check('getFieldContext() marks fieldgroup context flag', true, (bool) ($context->flags & Field::flagFieldgroupContext));
		$this->check('hasFieldContext() false before context save', false, $fieldgroup->hasFieldContext($alpha));

		$context->label = 'Alpha Context Label';
		$context->description = 'Alpha Context Description';
		$context->columnWidth = 50;
		$this->check('saveFieldContext() saves default context', true, $fieldgroup->saveFieldContext($context));

		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('hasFieldContext() true after context save', true, $fieldgroup->hasFieldContext($alpha));
		$context = $fieldgroup->getFieldContext($alpha);
		$this->check('getFieldContext() applies context label', 'Alpha Context Label', $context->label);
		$this->check('getFieldContext() applies context description', 'Alpha Context Description', $context->description);
		$this->check('getFieldContext() applies context columnWidth', 50, $context->columnWidth);
		$this->check('global Field label unchanged by context', 'Alpha Field', $fields->get($alpha->name)->label);
		$this->check('getField(name, true) applies context', 'Alpha Context Label', $fieldgroup->getField($alpha->name, true)->label);
		$this->check('getFieldContextArray(field) includes label', 'Alpha Context Label', $fieldgroup->getFieldContextArray($alpha->id)['label']);

		$namespace = 'wiretestsns';
		$namespaceContext = $fieldgroup->getFieldContext($alpha, $namespace);
		$namespaceContext->label = 'Alpha Namespaced Label';
		$namespaceContext->notes = 'Alpha Namespaced Notes';
		$this->check('saveFieldContext(namespace) saves namespaced context', true, $fieldgroup->saveFieldContext($namespaceContext, $namespace));

		$fieldgroup = $fieldgroups->get($fieldgroup->id);
		$this->check('hasFieldContext(namespace) returns true', true, $fieldgroup->hasFieldContext($alpha, $namespace));
		$namespaceContext = $fieldgroup->getFieldContext($alpha, $namespace);
		$this->check('getFieldContext(namespace) applies namespaced label', 'Alpha Namespaced Label', $namespaceContext->label);
		$this->check('getFieldContext(namespace) applies namespaced notes', 'Alpha Namespaced Notes', $namespaceContext->notes);
		$this->check('getFieldContext(namespace) does not merge default label', 'Alpha Namespaced Label', $namespaceContext->label);
		$this->check('getFieldContextArray(namespace) returns namespaced data', 'Alpha Namespaced Label', $fieldgroup->getFieldContextArray($alpha->id, $namespace)['label']);

		$this->check('saveContext() saves existing context arrays', true, $fieldgroup->saveContext() >= 1);
	}

	protected function testCloneExportImport() {
		$fieldgroups = $this->wire()->fieldgroups;
		$fields = $this->wire()->fields;
		$source = $fieldgroups->get($this->name('unsaved'));
		$alpha = $fields->get($this->name('alpha'));
		$delta = $this->createField('delta', 'Delta Field');

		$cloneName = $this->name('clone');
		$clone = $fieldgroups->clone($source, $cloneName);
		$this->fieldgroupNames[] = $cloneName;
		$this->check('clone() returns Fieldgroup', true, $clone instanceof Fieldgroup);
		$this->check('clone() saves cloned fieldgroup', true, $clone->id > 0);
		$this->check('clone() uses requested name', $cloneName, $clone->name);
		$this->check('clone() preserves field membership', true, $clone->hasField($alpha));
		$this->check('clone() preserves context data', 'Alpha Context Label', $clone->getFieldContext($alpha)->label);

		$export = $source->getExportData();
		$this->check('getExportData() includes fields list', true, in_array($alpha->name, $export['fields']));
		$this->check('getExportData() includes context data', 'Alpha Context Label', $export['contexts'][$alpha->name]['label']);

		$importName = $this->name('import');
		$import = $fieldgroups->new($importName, array('title'));
		$this->fieldgroupNames[] = $importName;
		unset($export['id']);
		$export['name'] = $importName;
		$export['fields'][] = $delta->name;
		$result = $import->setImportData($export);
		$this->check('setImportData() reports field changes', true, isset($result['fields']));
		$import->save();
		$import->saveContext();
		$import = $fieldgroups->get($import->id);
		$this->check('setImportData() + save() adds imported field', true, $import->hasField($delta));
		$this->check('setImportData() + saveContext() imports context', 'Alpha Context Label', $import->getFieldContext($alpha)->label);
	}

	protected function createField($suffix, $label) {
		$fields = $this->wire()->fields;
		$name = $this->name($suffix);
		$field = $fields->get($name);
		if(!$field) {
			$field = $fields->new('text', $name, $label);
			$this->fieldNames[] = $name;
		} else if(!in_array($name, $this->fieldNames)) {
			$this->fieldNames[] = $name;
		}
		return $field;
	}

	protected function firstFieldgroupIsFieldgroup() {
		foreach($this->wire()->fieldgroups as $fieldgroup) return $fieldgroup instanceof Fieldgroup;
		return false;
	}

	protected function fieldNamesInGroup(Fieldgroup $fieldgroup) {
		$names = array();
		foreach($fieldgroup as $field) {
			/** @var Field $field */
			$names[] = $field->name;
		}
		return $names;
	}

	protected function name($suffix) {
		return $this->prefix . '_' . $suffix;
	}

	protected function cleanup() {
		$fieldgroups = $this->wire()->fieldgroups;
		$fields = $this->wire()->fields;

		foreach($this->allFieldgroupNames() as $name) {
			$fieldgroup = $fieldgroups->get($name);
			if(!$fieldgroup) continue;
			try {
				$fieldgroups->delete($fieldgroup);
			} catch(\Exception $e) {
				// If a previous failed run somehow attached a template, leave it visible.
			}
		}

		foreach($this->allFieldNames() as $name) {
			$field = $fields->get($name);
			if(!$field) continue;

			foreach($field->getFieldgroups() as $fieldgroup) {
				/** @var Fieldgroup $fieldgroup */
				if(strpos($fieldgroup->name, $this->prefix . '_') !== 0) continue;
				if($fieldgroup->hasField($field)) {
					$fieldgroup->remove($field);
					$fieldgroup->save();
				}
			}

			$field = $fields->get($name);
			if($field && $field->id && !$field->numFieldgroups()) {
				try {
					$fields->delete($field);
				} catch(\Exception $e) {
					// Leave cleanup failures visible as test failures when they affect assertions.
				}
			}
		}
	}

	protected function allFieldNames() {
		return array(
			$this->name('alpha'),
			$this->name('beta'),
			$this->name('gamma'),
			$this->name('delta'),
		);
	}

	protected function allFieldgroupNames() {
		return array(
			$this->name('unsaved'),
			$this->name('saved'),
			$this->name('clone'),
			$this->name('import'),
		);
	}
}
