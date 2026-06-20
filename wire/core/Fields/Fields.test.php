<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $fields API variable
 *
 */
class WireTest_Fields extends WireTest {

	protected $prefix = 'wiretests_fields';
	protected $fieldNames = array();
	protected $originalPageValue = null;

	public function init() {
		$this->cleanup();
	}

	public function execute() {
		$fields = $this->wire()->fields;

		$this->check('$fields is Fields', true, $fields instanceof Fields);
		$this->check('$fields->get(title) returns Field', true, $fields->get('title') instanceof Field);
		$this->check('$fields->get(missing) returns null', null, $fields->get($this->name('missing')));
		$this->check('$fields->fieldNameExists(title) returns true', true, $fields->fieldNameExists('title'));
		$this->check('$fields->fieldNameExists(missing) returns false', false, $fields->fieldNameExists($this->name('missing')));
		$this->check('$fields is iterable over Field objects', true, $this->firstFieldIsField());

		$this->testNamesAndFieldtypes();
		$this->testCreateSaveCloneDelete();
		$this->testRawAndFresh();
		$this->testTagsAndFinders();
		$this->testUsageCountsAndContext();
		$this->testFieldMethods();
	}

	public function finish() {
		$this->cleanup();
	}

	protected function testNamesAndFieldtypes() {
		$fields = $this->wire()->fields;
		$names = $fields->getAllNames();
		$namesByName = $fields->getAllNames('name');
		$namesById = $fields->getAllNames('id');
		$ids = $fields->getAllIds();

		$this->check('getAllNames() returns title', true, in_array('title', $names));
		$this->check('getAllNames(name) indexes by field name', 'title', $namesByName['title']);
		$this->check('getAllIds() includes title ID', $fields->get('title')->id, $ids['title']);
		$this->check('getAllNames(id) indexes by field ID', 'title', $namesById[$fields->get('title')->id]);

		$this->check('getFieldtype(text) resolves short lowercase name', 'FieldtypeText', $fields->getFieldtype('text')->className());
		$this->check('getFieldtype(Text) resolves short class-like name', 'FieldtypeText', $fields->getFieldtype('Text')->className());
		$this->check('getFieldtype(FieldtypeText) resolves full name', 'FieldtypeText', $fields->getFieldtype('FieldtypeText')->className());
		$this->check('getFieldtype(missing) returns null', null, $fields->getFieldtype($this->name('missingtype')));

		$this->check('isNative(name) detects system native name', true, $fields->isNative('name'));
		$this->check('isNative(title) false for custom title field', false, $fields->isNative('title'));
		$nativeName = $this->name('native_runtime');
		$fields->setNative($nativeName);
		$this->check('setNative() registers runtime native name', true, $fields->isNative($nativeName));
	}

	protected function testCreateSaveCloneDelete() {
		$fields = $this->wire()->fields;

		$unsavedName = $this->name('unsaved');
		$unsaved = $fields->newField('text', $unsavedName, array(
			'label' => 'Unsaved Test Field',
			'tags' => 'wiretests temp',
		));
		$this->check('newField() returns Field', true, $unsaved instanceof Field);
		$this->check('newField() does not save field', 0, $unsaved->id);
		$this->check('newField() sets field name', $unsavedName, $unsaved->name);
		$this->check('newField() sets FieldtypeText', 'FieldtypeText', $unsaved->type->className());
		$this->check('newField() applies options array', 'Unsaved Test Field', $unsaved->label);
		$fields->save($unsaved);
		$this->fieldNames[] = $unsavedName;
		$this->check('save() persists newField()', true, $unsaved->id > 0);
		$this->check('fieldNameExists() true after save()', true, $fields->fieldNameExists($unsavedName));

		$unsaved->label = 'Updated Unsaved Test Field';
		$this->check('save() updates existing field', true, $fields->save($unsaved));
		$this->check('save() persisted updated label', 'Updated Unsaved Test Field', $fields->get($unsavedName)->label);

		$arrayName = $this->name('array');
		$arrayField = $fields->new(array(
			'type' => 'textarea',
			'name' => $arrayName,
			'label' => 'Array Created Field',
			'tags' => 'wiretests array',
		));
		$this->fieldNames[] = $arrayName;
		$this->check('new(array) creates and saves field', true, $arrayField->id > 0);
		$this->check('new(array) sets FieldtypeTextarea', 'FieldtypeTextarea', $arrayField->type->className());
		$this->check('new(array) applies label', 'Array Created Field', $arrayField->label);

		$stringName = $this->name('string');
		$stringField = $fields->new('text', $stringName, 'String Label Field');
		$this->fieldNames[] = $stringName;
		$this->check('new(type, name, label) creates saved field', true, $stringField->id > 0);
		$this->check('new(type, name, label) applies label', 'String Label Field', $stringField->label);

		$cloneName = $this->name('clone');
		$clone = $fields->clone($stringField, $cloneName);
		$this->fieldNames[] = $cloneName;
		$this->check('clone() returns Field', true, $clone instanceof Field);
		$this->check('clone() saves cloned field', true, $clone->id > 0);
		$this->check('clone() uses requested name', $cloneName, $clone->name);
		$this->check('clone() preserves fieldtype', $stringField->type->className(), $clone->type->className());

		$this->check('delete() removes cloned field', true, $fields->delete($clone));
		unset($this->fieldNames[array_search($cloneName, $this->fieldNames)]);
		$this->check('fieldNameExists() false after delete()', false, $fields->fieldNameExists($cloneName));

		$threw = false;
		try {
			$fields->delete($fields->get('title'));
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('delete() throws for field in use', true, $threw);
	}

	protected function testRawAndFresh() {
		$fields = $this->wire()->fields;
		$field = $fields->get($this->name('unsaved'));
		$savedLabel = $field->label;
		$rawByName = $fields->getRaw($field->name);
		$rawById = $fields->getRaw($field->id);
		$freshByName = $fields->getFresh($field->name);
		$freshById = $fields->getFresh($field->id);

		$this->check('getRaw(name) returns field row array', true, is_array($rawByName));
		$this->check('getRaw(name) includes field ID', $field->id, (int) $rawByName['id']);
		$this->check('getRaw(id) includes field name', $field->name, $rawById['name']);
		$this->check('getRaw() leaves data column encoded', true, is_string($rawByName['data']));
		$this->check('getFresh(name) returns Field', true, $freshByName instanceof Field);
		$this->check('getFresh(id) returns Field', true, $freshById instanceof Field);
		$this->check('getFresh() preserves field name', $field->name, $freshByName->name);
		$this->check('getFresh() decodes field data settings', $savedLabel, $freshByName->label);
		$this->check('getFresh() returns a separate instance', true, $freshByName !== $field);

		$field->label = 'Unsaved cached label';
		$freshAfterMutation = $fields->getFresh($field->name);
		$this->check('getFresh() bypasses unsaved cached changes', $savedLabel, $freshAfterMutation->label);
		$this->check('getRaw(missing) returns null', null, $fields->getRaw($this->name('missing_raw')));
		$this->check('getFresh(missing) returns null', null, $fields->getFresh($this->name('missing_fresh')));

		$field->label = $savedLabel;
	}

	protected function testTagsAndFinders() {
		$fields = $this->wire()->fields;
		$field = $fields->get($this->name('unsaved'));

		$field->setTags('wiretests alpha');
		$fields->save($field);
		$fields->getTags('reset');

		$tags = $fields->getTags();
		$tagFields = $fields->getTags(true);
		$byTag = $fields->findByTag('wiretests');
		$byTagNames = $fields->findByTag('wiretests', true);

		$this->check('getTags() includes saved tag', 'wiretests', $tags['wiretests']);
		$this->check('getTags(true) maps tag to field names', true, in_array($field->name, $tagFields['wiretests']));
		$this->check('findByTag() returns Field objects', true, $byTag[$field->name] instanceof Field);
		$this->check('findByTag(true) returns field names', $field->name, $byTagNames[$field->name]);
		$this->check('getTags(reset) returns empty array', array(), $fields->getTags('reset'));

		$exactText = $fields->findByType('FieldtypeText', array(
			'inherit' => false,
			'valueType' => 'name',
			'indexType' => 'name',
		));
		$this->check('findByType(exact, names) includes text field', $field->name, $exactText[$field->name]);

		$idsByName = $fields->findByType('FieldtypeText', array(
			'inherit' => false,
			'valueType' => 'id',
			'indexType' => 'name',
		));
		$this->check('findByType(valueType=id, indexType=name) indexes by name', $field->id, $idsByName[$field->name]);

		$nonAssocNames = $fields->findByType('FieldtypeText', array(
			'inherit' => false,
			'valueType' => 'name',
			'indexType' => '',
		));
		$this->check('findByType(indexType blank) returns non-associative values', true, in_array($field->name, $nonAssocNames));

		$inherited = $fields->findByType('FieldtypeText', array(
			'inherit' => true,
			'valueType' => 'name',
			'indexType' => 'name',
		));
		$this->check('findByType(inherit=true) includes textarea descendants', $this->name('array'), $inherited[$this->name('array')]);
	}

	protected function testUsageCountsAndContext() {
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();
		$field = $fields->get($this->name('string'));
		$fieldgroup = $page->template->fieldgroup;

		if(!$fieldgroup->hasField($field)) {
			$fieldgroup->add($field);
			$fieldgroup->save();
		}

		$page->of(false);
		$this->originalPageValue = $page->get($field->name);
		$page->set($field->name, 'Fields API test value');
		$page->save($field->name);

		$this->check('getNumRows() counts populated rows', 1, $fields->getNumRows($field, array('page' => $page->id)));
		$this->check('getNumPages() counts populated pages', 1, $fields->getNumPages($field, array('page' => $page->id)));
		$this->check('getNumRows(template) counts by template', true, $fields->getNumRows($field, array('template' => $page->template)) >= 1);
		$this->check('getNumPages(getPageIDs) returns page IDs', true, in_array($page->id, $fields->getNumPages($field, array(
			'template' => $page->template,
			'getPageIDs' => true,
		))));

		$this->check('Field::numFieldgroups() sees test fieldgroup', true, $field->numFieldgroups() >= 1);
		$this->check('Field::getFieldgroups() includes test fieldgroup', true, $field->getFieldgroups()->has($fieldgroup));
		$this->check('Field::getTemplates() includes test template', true, $field->getTemplates()->has($page->template));
		$this->check('Field::getTemplates(true) returns count', true, $field->getTemplates(true) >= 1);

		$context = $fieldgroup->getFieldContext($field);
		$context->label = 'Context Label';
		$this->check('saveFieldgroupContext() saves context label', true, $fields->saveFieldgroupContext($context, $fieldgroup));
		$fieldgroup = $this->wire()->fieldgroups->get($fieldgroup->id);
		$context = $fieldgroup->getFieldContext($field);
		$this->check('saveFieldgroupContext() persisted context label', 'Context Label', $context->label);
	}

	protected function testFieldMethods() {
		$fields = $this->wire()->fields;
		$field = $fields->get($this->name('string'));
		$page = $this->getTestPage();

		$this->check('Field::getLabel() returns label', $field->label, $field->getLabel());
		$field->description = 'Field description';
		$field->notes = 'Field notes';
		$fields->save($field);
		$this->check('Field::getDescription() returns description', 'Field description', $field->getDescription());
		$this->check('Field::getNotes() returns notes', 'Field notes', $field->getNotes());

		$field->addFlag(Field::flagAutojoin);
		$this->check('Field::addFlag() adds flag', true, $field->hasFlag(Field::flagAutojoin));
		$field->removeFlag(Field::flagAutojoin);
		$this->check('Field::removeFlag() removes flag', false, $field->hasFlag(Field::flagAutojoin));

		$field->addTag('Beta');
		$this->check('Field::addTag() adds tag case-insensitively', true, $field->hasTag('beta'));
		$this->check('Field::getTags(true) returns tag string', 'Beta', $field->getTags(true), '*=');
		$field->removeTag('beta');
		$this->check('Field::removeTag() removes tag', false, $field->hasTag('beta'));

		$this->check('Field::viewable() returns boolean', true, is_bool($field->viewable($page)));
		$this->check('Field::editable() returns boolean', true, is_bool($field->editable($page)));
		$this->check('Field::getInputfield() returns Inputfield', true, $field->getInputfield($page) instanceof Inputfield);
		$this->check('Field::editUrl() includes field ID', 'id=' . $field->id, $field->editUrl(), '*=');
		$this->check('Field::editUrl(find) includes sanitized anchor', '#find-label', $field->editUrl('label'), '$=');
	}

	protected function firstFieldIsField() {
		foreach($this->wire()->fields as $field) return $field instanceof Field;
		return false;
	}

	protected function name($suffix) {
		return $this->prefix . '_' . $suffix;
	}

	protected function cleanup() {
		$fields = $this->wire()->fields;
		$page = $this->getTestPage();

		if($page && $page->id) {
			$page->of(false);
			foreach($this->fieldNames as $name) {
				if($page->hasField($name)) {
					$page->set($name, $this->originalPageValue === null ? '' : $this->originalPageValue);
					try {
						$page->save($name);
					} catch(\Exception $e) {
						// Field cleanup below may already have removed the field from this page.
					}
				}
			}
		}

		foreach($this->allNames() as $name) {
			$field = $fields->get($name);
			if(!$field) continue;

			foreach($field->getFieldgroups() as $fieldgroup) {
				/** @var Fieldgroup $fieldgroup */
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

		$fields->getTags('reset');
	}

	protected function allNames() {
		return array(
			$this->name('unsaved'),
			$this->name('array'),
			$this->name('string'),
			$this->name('clone'),
		);
	}
}
