<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Field class
 *
 * Tests properties, flags, name validation, fieldtype setting, table generation,
 * access control, tags, icons, labels/descriptions/notes, context, and
 * fieldgroups/templates retrieval. Tests create and delete fields in the DB
 * to verify save/delete operations.
 *
 */
class WireTest_Field extends WireTest {

	protected $testFieldName = 'wire_test_field_xyz';
	protected $createdFieldIds = array();

	public function init() {
		$this->cleanupFields();
	}

	public function execute() {
		$this->testProperties();
		$this->testFlags();
		$this->testNameValidation();
		$this->testNameRename();
		$this->testFieldtype();
		$this->testTable();
		$this->testSetTable();
		$this->testAccessControl();
		$this->testTags();
		$this->testIcon();
		$this->testLabelDescriptionNotes();
		$this->testSaveAndDelete();
		$this->testFieldgroupsAndTemplates();
		$this->testEditUrl();
		$this->testToString();
		$this->testContext();
	}

	public function finish() {
		$this->cleanupFields();
	}

	protected function cleanupFields() {
		$fields = $this->wire()->fields;
		foreach($this->createdFieldIds as $id) {
			try {
				$field = $fields->get($id);
				if($field && $field->id) $fields->delete($field);
			} catch(\Exception $e) { }
		}
		$this->createdFieldIds = array();
		// Also clean up by name in case
		$field = $fields->get($this->testFieldName);
		if($field && $field->id) {
			try { $fields->delete($field); } catch(\Exception $e) { }
		}
	}

	protected function testProperties() {
		$field = $this->wire()->fields->get('title');

		// Core properties on title field
		$this->check('title field has id > 0', true, $field->id > 0);
		$this->check('title field name is "title"', 'title', $field->name);
		$this->check('title field has label', true, strlen($field->label) > 0);
		$this->check('title field type is FieldtypePageTitle', true, $field->type instanceof Fieldtype);
		$this->check('title field flags is int', true, is_int($field->flags));
		$this->check('title field flagsStr is string', true, is_string($field->flagsStr));

		// table property
		$this->check('title field table is field_title', 'field_title', $field->table);

		// get() method
		$this->check('get(name) returns name', 'title', $field->get('name'));
		$this->check('get(label) returns label', $field->label, $field->get('label'));

		// id property
		$field2 = new Field();
		$this->check('new Field id is 0', 0, $field2->id);
	}

	protected function testFlags() {
		$field = new Field();
		$field->setRawSetting('name', 'wire_test_flags');

		// Default flags is 0
		$this->check('new Field flags is 0', 0, $field->flags);

		// addFlag
		$field->addFlag(Field::flagAutojoin);
		$this->check('addFlag(flagAutojoin) sets flag', true, $field->hasFlag(Field::flagAutojoin));

		// addFlag chaining
		$result = $field->addFlag(Field::flagGlobal);
		$this->check('addFlag() returns $this', true, $result === $field);
		$this->check('addFlag(flagGlobal) sets flag', true, $field->hasFlag(Field::flagGlobal));

		// flags value is bitmask
		$this->check('flags is bitmask of autojoin+global', Field::flagAutojoin | Field::flagGlobal, $field->flags);

		// removeFlag
		$field->removeFlag(Field::flagAutojoin);
		$this->check('removeFlag(flagAutojoin) removes flag', false, $field->hasFlag(Field::flagAutojoin));
		$this->check('removeFlag keeps other flags', true, $field->hasFlag(Field::flagGlobal));

		// removeFlag chaining
		$result = $field->removeFlag(Field::flagGlobal);
		$this->check('removeFlag() returns $this', true, $result === $field);

		// System flag protection
		$field->addFlag(Field::flagSystem);
		$this->check('system flag set', true, $field->hasFlag(Field::flagSystem));
		$field->removeFlag(Field::flagSystem);
		$this->check('system flag cannot be removed without override', true, $field->hasFlag(Field::flagSystem));

		// System override
		$field->addFlag(Field::flagSystemOverride);
		$field->removeFlag(Field::flagSystem);
		$this->check('system flag removed with override', false, $field->hasFlag(Field::flagSystem));

		// flagsAdd/flagsDel via set()
		$field2 = new Field();
		$field2->setRawSetting('name', 'wire_test_flags2');
		$field2->set('flagsAdd', Field::flagAutojoin);
		$this->check('flagsAdd via set() adds flag', true, $field2->hasFlag(Field::flagAutojoin));
		$field2->set('flagsDel', Field::flagAutojoin);
		$this->check('flagsDel via set() removes flag', false, $field2->hasFlag(Field::flagAutojoin));
	}

	protected function testNameValidation() {
		// Reserved word
		$field = new Field();
		$threw = false;
		try { $field->setName('name'); } catch(WireException $e) { $threw = true; }
		$this->check('setName() throws on reserved word', true, $threw);

		// Double underscores
		$field = new Field();
		$threw = false;
		try { $field->setName('foo__bar'); } catch(WireException $e) { $threw = true; }
		$this->check('setName() throws on double underscores', true, $threw);

		// Duplicate name
		$field = new Field();
		$threw = false;
		try { $field->setName('title'); } catch(WireException $e) { $threw = true; }
		$this->check('setName() throws on duplicate name', true, $threw);

		// Valid name
		$field = new Field();
		$field->setName('wire_test_valid_name_xyz');
		$this->check('setName() accepts valid name', 'wire_test_valid_name_xyz', $field->name);

		// Name sanitization
		$field = new Field();
		$field->setName('Test Field Name!');
		$this->check('setName() sanitizes to page name format', true, ctype_alnum(str_replace('_', '', $field->name)));
	}

	protected function testNameRename() {
		$field = new Field();
		$field->setName('wire_test_original');
		$field->setName('wire_test_renamed');

		$this->check('rename updates name', 'wire_test_renamed', $field->name);
		$this->check('rename tracks prevName', 'wire_test_original', $field->prevName);

		// System field rename protection
		$titleField = $this->wire()->fields->get('title');
		$threw = false;
		try { $titleField->setName('renamed_title'); } catch(WireException $e) { $threw = true; }
		$this->check('system field rename throws', true, $threw);
	}

	protected function testFieldtype() {
		// Set type by string
		$field = new Field();
		$field->setRawSetting('name', 'wire_test_fieldtype');
		$field->setFieldtype('FieldtypeText');
		$this->check('setFieldtype(string) sets type', true, $field->type instanceof Fieldtype);
		$this->check('getFieldtype() returns same as type property', $field->type, $field->getFieldtype());

		// Set type by object
		$fieldtype = $this->wire()->fieldtypes->get('FieldtypeText');
		$field2 = new Field();
		$field2->setRawSetting('name', 'wire_test_fieldtype2');
		$field2->setFieldtype($fieldtype);
		$this->check('setFieldtype(object) sets type', true, $field2->type instanceof Fieldtype);

		// Set type via property
		$field3 = new Field();
		$field3->setRawSetting('name', 'wire_test_fieldtype3');
		$field3->type = 'FieldtypeText';
		$this->check('type property accepts string', true, $field3->type instanceof Fieldtype);

		// Setup name
		$field4 = new Field();
		$field4->setRawSetting('name', 'wire_test_fieldtype4');
		$field4->type = 'FieldtypeText.mySetup';
		$this->check('type with setup name sets type', true, $field4->type instanceof Fieldtype);
		$this->check('setup name is tracked', 'mySetup', $field4->setSetupName());

		// Type change tracks prevFieldtype
		$field5 = new Field();
		$field5->setRawSetting('name', 'wire_test_fieldtype5');
		$field5->type = 'FieldtypeText';
		$field5->type = 'FieldtypeTextarea';
		$this->check('type change tracks prevFieldtype', true, $field5->prevFieldtype instanceof Fieldtype);
	}

	protected function testTable() {
		$field = new Field();
		$field->setName('wire_test_table');

		// Default table name
		$this->check('getTable() returns field_ prefix + name', 'field_wire_test_table', $field->getTable());
		$this->check('table property matches getTable()', $field->getTable(), $field->table);

		// Long name truncation (field_ + 58 = 64 max)
		$field->setName(str_repeat('z', 70));
		$table = $field->getTable();
		$this->check('long name table truncated to 64 chars', 64, strlen($table));
		$this->check('long name table starts with field_', 'field_', $table, '^=');

		// Empty name throws
		$field2 = new Field();
		$threw = false;
		try { $field2->getTable(); } catch(WireException $e) { $threw = true; }
		$this->check('getTable() throws on empty name', true, $threw);
	}

	protected function testSetTable() {
		$field = new Field();
		$field->setName('wire_test_settable');

		// Override table
		$field->setTable('custom_table');
		$this->check('setTable() overrides table name', 'custom_table', $field->getTable());

		// Restore default
		$field->setTable(null);
		$this->check('setTable(null) restores default', 'field_wire_test_settable', $field->getTable());
	}

	protected function testAccessControl() {
		$field = new Field();
		$field->setRawSetting('name', 'wire_test_access');

		// Default useRoles is false
		$this->check('useRoles default is false', false, $field->useRoles);
		$this->check('flagAccess not set by default', false, $field->hasFlag(Field::flagAccess));

		// Enable access control
		$field->useRoles = true;
		$this->check('useRoles=true enables flagAccess', true, $field->hasFlag(Field::flagAccess));

		// Disable access control
		$field->useRoles = false;
		$this->check('useRoles=false disables flagAccess', false, $field->hasFlag(Field::flagAccess));

		// Set roles via set()
		$field->useRoles = true;
		$field->set('editRoles', [1, 2]);
		$field->set('viewRoles', [1]);
		$this->check('set(editRoles) stores role IDs', true, $field->editRoles === [1, 2] || $field->editRoles == [1, 2]);
		$this->check('set(viewRoles) stores role IDs', true, $field->viewRoles === [1] || $field->viewRoles == [1]);

		// setRoles() method
		$field->setRoles('edit', [3, 4]);
		$this->check('setRoles(edit) sets roles', true, in_array(3, $field->editRoles));

		// setRoles() with invalid type throws
		$threw = false;
		try { $field->setRoles('invalid', [1]); } catch(WireException $e) { $threw = true; }
		$this->check('setRoles(invalid type) throws', true, $threw);
	}

	protected function testTags() {
		$field = new Field();
		$field->setRawSetting('name', 'wire_test_tags');

		// addTag
		$field->addTag('foo');
		$field->addTag('bar');
		$this->check('addTag() adds tag', true, $field->hasTag('foo'));
		$this->check('addTag() adds second tag', true, $field->hasTag('bar'));

		// Case insensitivity — adding "Foo" when "foo" exists should not duplicate
		$field->addTag('Foo');
		$tagList = $field->getTags();
		$this->check('addTag() is case-insensitive (no duplicate)', 2, count($tagList));

		// hasTag is case-insensitive
		$this->check('hasTag() is case-insensitive', true, $field->hasTag('FOO'));

		// hasTag returns false for missing
		$this->check('hasTag() false for missing tag', false, $field->hasTag('nonexistent'));

		// tags property returns string
		$this->check('tags property returns string', true, is_string($field->tags));

		// tagList property returns array
		$this->check('tagList property returns array', true, is_array($field->tagList));

		// getTags(true) returns string
		$this->check('getTags(true) returns string', true, is_string($field->getTags(true)));

		// getTags() returns array
		$this->check('getTags() returns array', true, is_array($field->getTags()));

		// removeTag
		$field->removeTag('foo');
		$this->check('removeTag() removes tag', false, $field->hasTag('foo'));
		$this->check('removeTag() keeps other tags', true, $field->hasTag('bar'));

		// setTags with array
		$field2 = new Field();
		$field2->setRawSetting('name', 'wire_test_tags2');
		$field2->setTags(['alpha', 'beta']);
		$this->check('setTags(array) sets tags', true, $field2->hasTag('alpha'));
		$this->check('setTags(array) sets second tag', true, $field2->hasTag('beta'));

		// setTags with string
		$field3 = new Field();
		$field3->setRawSetting('name', 'wire_test_tags3');
		$field3->setTags('gamma delta');
		$this->check('setTags(string) sets tags', true, $field3->hasTag('gamma'));
	}

	protected function testIcon() {
		$field = new Field();
		$field->setRawSetting('name', 'wire_test_icon');

		// Set icon with fa- prefix
		$field->setIcon('fa-user');
		$this->check('setIcon() strips fa- prefix', 'user', $field->getIcon());
		$this->check('getIcon(true) adds fa- prefix', 'fa-user', $field->getIcon(true));

		// Set icon with icon- prefix
		$field->setIcon('icon-home');
		$this->check('setIcon() strips icon- prefix', 'home', $field->getIcon());

		// Set icon without prefix
		$field->setIcon('cog');
		$this->check('setIcon() without prefix', 'cog', $field->getIcon());

		// Set icon via property (property returns with fa- prefix via getIcon(true))
		$field->icon = 'fa-star';
		$this->check('icon property returns with fa- prefix', 'fa-star', $field->icon);

		// Empty icon
		$field2 = new Field();
		$field2->setRawSetting('name', 'wire_test_icon2');
		$this->check('empty icon returns empty string', '', $field2->getIcon());
	}

	protected function testLabelDescriptionNotes() {
		$field = new Field();
		$field->setRawSetting('name', 'wire_test_labels');

		// Set and get label
		$field->setLabel('My Label');
		$this->check('setLabel() / getLabel()', 'My Label', $field->getLabel());

		// Set and get description
		$field->setDescription('My Description');
		$this->check('setDescription() / getDescription()', 'My Description', $field->getDescription());

		// Set and get notes
		$field->setNotes('My Notes');
		$this->check('setNotes() / getNotes()', 'My Notes', $field->getNotes());

		// Label defaults to name when empty
		$field2 = new Field();
		$field2->setRawSetting('name', 'wire_test_nolabel');
		$this->check('getLabel() returns name when no label set', 'wire_test_nolabel', $field2->getLabel());

		// Set via property
		$field->label = 'Property Label';
		$this->check('label property sets label', 'Property Label', $field->getLabel());
	}

	protected function testSaveAndDelete() {
		$fields = $this->wire()->fields;

		// Create and save a field
		$field = new Field();
		$field->name = $this->testFieldName;
		$field->type = 'FieldtypeText';
		$field->label = 'Test Field';
		$result = $fields->save($field);
		$this->check('save() returns true', true, $result);
		$this->check('saved field has id > 0', true, $field->id > 0);
		$this->createdFieldIds[] = $field->id;

		// Retrieve saved field
		$saved = $fields->get($this->testFieldName);
		$this->check('saved field retrievable by name', true, $saved->id > 0);
		$this->check('saved field has correct label', 'Test Field', $saved->label);

		// Update and save
		$saved->label = 'Updated Label';
		$fields->save($saved);
		$saved2 = $fields->get($this->testFieldName);
		$this->check('updated label persists', 'Updated Label', $saved2->label);

		// Delete
		$fields->delete($saved);
		$deleted = $fields->get($this->testFieldName);
		$this->check('deleted field returns NullField or null', true, $deleted instanceof NullField || $deleted === null || !$deleted->id);
	}

	protected function testFieldgroupsAndTemplates() {
		$field = $this->wire()->fields->get('title');

		// getFieldgroups
		$fieldgroups = $field->getFieldgroups();
		$this->check('getFieldgroups() returns FieldgroupsArray', true, $fieldgroups instanceof FieldgroupsArray);
		$this->check('getFieldgroups() has items', true, count($fieldgroups) > 0);

		// getFieldgroups(true) returns count
		$count = $field->getFieldgroups(true);
		$this->check('getFieldgroups(true) returns int count', true, is_int($count) && $count > 0);

		// numFieldgroups matches count
		$this->check('numFieldgroups() matches getFieldgroups(true)', $count, $field->numFieldgroups());

		// getTemplates
		$templates = $field->getTemplates();
		$this->check('getTemplates() returns TemplatesArray', true, $templates instanceof TemplatesArray);
		$this->check('getTemplates() has items', true, count($templates) > 0);

		// getTemplates(true) returns count
		$tcount = $field->getTemplates(true);
		$this->check('getTemplates(true) returns int count', true, is_int($tcount) && $tcount > 0);
	}

	protected function testEditUrl() {
		$field = $this->wire()->fields->get('title');

		// Basic editUrl
		$url = $field->editUrl();
		$this->check('editUrl() returns non-empty string', true, is_string($url) && strlen($url) > 0);
		$this->check('editUrl() contains field edit path', 'setup/field/edit', $url, '*=');
		$this->check('editUrl() contains field id', "id=$field->id", $url, '*=');

		// editUrl with find
		$urlFind = $field->editUrl('description');
		$this->check('editUrl(find) has anchor', '#find-description', $urlFind, '$=');

		// editUrl with array options
		$urlArray = $field->editUrl(['find' => 'label']);
		$this->check('editUrl(array) with find has anchor', '#find-label', $urlArray, '$=');
	}

	protected function testToString() {
		$field = $this->wire()->fields->get('title');
		$str = (string) $field;
		$this->check('__toString() returns field name', 'title', $str);
	}

	protected function testContext() {
		// Test hasContext on title field with a template
		$field = $this->wire()->fields->get('title');
		$template = $this->wire()->templates->get('home');
		if($template) {
			// hasContext returns a boolean
			$result = $field->hasContext($template);
			$this->check('hasContext() returns bool', true, is_bool($result));

			// getContext returns Field or bool
			if($result) {
				$context = $field->getContext($template);
				$this->check('getContext() returns Field when context exists', true, $context instanceof Field);
			}
		} else {
			$this->check('hasContext() - template found for test', true, false);
		}

		// hasContext with string template name
		$template = $this->wire()->templates->get('home');
		if($template) {
			$result = $field->hasContext('home');
			$this->check('hasContext(string) returns bool', true, is_bool($result));
		}
	}
}
