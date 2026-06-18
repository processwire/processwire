<?php namespace ProcessWire;

/**
 * Tests for ProcessWire Fieldtype base classes
 *
 */
class WireTest_Fieldtype extends WireTest {

	public function execute() {
		$this->testModuleIdentityAndValueDefaults();
		$this->testDatabaseSchemaHelpers();
		$this->testQueryHelpers();
		$this->testFieldtypeMultiDefaults();
	}

	/**
	 * Test base module identity and value lifecycle defaults
	 *
	 */
	protected function testModuleIdentityAndValueDefaults() {
		list($fieldtype, $page, $field) = $this->makeBaseFixture();

		$this->check('isSingular() returns true', true, $fieldtype->isSingular());
		$this->check('isAutoload() returns false', false, $fieldtype->isAutoload());
		$this->check('isAdvanced() returns false', false, $fieldtype->isAdvanced());
		$this->check('name property returns class name', 'FieldtypeWireTestBasic', $fieldtype->name);
		$this->check('shortName removes Fieldtype prefix', 'WireTestBasic', $fieldtype->shortName);
		$this->check('__toString() returns class name', 'FieldtypeWireTestBasic', (string) $fieldtype);

		$fieldtype->setLastAccessField($field);
		$this->check('getLastAccessField() returns assigned field', $field, $fieldtype->getLastAccessField());

		$this->check('getBlankValue() default is blank string', '', $fieldtype->getBlankValue($page, $field));
		$this->check('getDefaultValue() default follows blank value', '', $fieldtype->getDefaultValue($page, $field));
		$this->check('getDefaultValue() normalizes null blank value', '', $this->wire(new FieldtypeWireTestNullBlank())->getDefaultValue($page, $field));
		$this->check('sanitizeValue() implementation is used', 'value', $fieldtype->sanitizeValue($page, $field, ' value '));
		$this->check('formatValue() default returns value unchanged', '<b>x</b>', $fieldtype->formatValue($page, $field, '<b>x</b>'));
		$this->check('wakeupValue() default returns value unchanged', 'db-value', $fieldtype->wakeupValue($page, $field, 'db-value'));
		$this->check('sleepValue() default returns value unchanged', 'runtime-value', $fieldtype->sleepValue($page, $field, 'runtime-value'));
		$this->check('exportValue() default delegates to sleepValue()', 'runtime-value', $fieldtype->exportValue($page, $field, 'runtime-value'));
		$this->check('importValue() default delegates to wakeupValue()', 'db-value', $fieldtype->importValue($page, $field, 'db-value'));
		$this->check('isDeleteValue() matches blank value', true, $fieldtype->isDeleteValue($page, $field, ''));
		$this->check('isEmptyValue() follows PHP empty()', true, $fieldtype->isEmptyValue($field, ''));
		$this->check('getFieldClass() default is blank', '', $fieldtype->getFieldClass());

		$inputfield = $fieldtype->getInputfield($page, $field);
		$this->check('getInputfield() default returns InputfieldText', true, $inputfield instanceof InputfieldText);
		$this->check('getConfigInputfields() default returns wrapper', true, $fieldtype->getConfigInputfields($field) instanceof InputfieldWrapper);
		$this->check('getConfigArray() default returns empty array', array(), $fieldtype->getConfigArray($field));
		$this->check('getConfigAllowContext() default returns empty array', array(), $fieldtype->getConfigAllowContext($field));
		$this->check('getFieldSetups() default returns empty array', array(), $fieldtype->getFieldSetups());
	}

	/**
	 * Test schema and schema helper defaults
	 *
	 */
	protected function testDatabaseSchemaHelpers() {
		list($fieldtype,, $field) = $this->makeBaseFixture();
		$schema = $fieldtype->getDatabaseSchema($field);

		$this->check('getDatabaseSchema() includes pages_id column', 'int UNSIGNED NOT NULL', $schema['pages_id']);
		$this->check('getDatabaseSchema() includes default data column', 'int NOT NULL', $schema['data']);
		$this->check('getDatabaseSchema() includes primary key', 'PRIMARY KEY (`pages_id`)', $schema['keys']['primary']);
		$this->check('getDatabaseSchema() marks schema as complete storage', true, $schema['xtra']['all']);

		$verbose = $fieldtype->getDatabaseSchemaVerbose($field);
		$this->check('getDatabaseSchemaVerbose() includes table name', 'field_wiretest_fieldtype', $verbose['table']);
		$this->check('getDatabaseSchemaVerbose() identifies data column', array('data' => 'int NOT NULL'), $verbose['cols']);
		$this->check('getDatabaseSchemaVerbose() parses primary keys', array('pages_id'), $verbose['primaryKeys']);
		$this->check('getDatabaseSchemaVerbose(property) returns requested value', 'pages_id', $fieldtype->getDatabaseSchemaVerbose($field, 'primaryKey'));

		$customSchema = $schema;
		$customSchema['sort'] = 'int unsigned NOT NULL';
		$customSchema['title'] = 'varchar(255) NOT NULL';
		$customSchema['amount'] = 'decimal(10,2) DEFAULT NULL';
		$customSchema['item_id'] = 'int unsigned NOT NULL AUTO_INCREMENT';

		$this->check('trimDatabaseSchema() removes meta and default columns', array(
			'data' => 'int NOT NULL',
			'title' => 'varchar(255) NOT NULL',
			'amount' => 'decimal(10,2) DEFAULT NULL',
			'item_id' => 'int unsigned NOT NULL AUTO_INCREMENT',
		), $fieldtype->trimDatabaseSchema($customSchema));

		$this->check('trimDatabaseSchema(findType) finds type with modifiers', array(
			'data' => 'int NOT NULL',
			'item_id' => 'int unsigned NOT NULL AUTO_INCREMENT',
		), $fieldtype->trimDatabaseSchema($customSchema, array('findType' => 'int')));
		$this->check('trimDatabaseSchema(findType *) finds integer variants', array(
			'data' => 'int NOT NULL',
			'item_id' => 'int unsigned NOT NULL AUTO_INCREMENT',
		), $fieldtype->trimDatabaseSchema($customSchema, array('findType' => '*int')));
		$this->check('trimDatabaseSchema(findDefaultNULL) finds nullable defaults', array('amount' => 'decimal(10,2) DEFAULT NULL'), $fieldtype->trimDatabaseSchema($customSchema, array('findDefaultNULL' => true)));
		$this->check('trimDatabaseSchema(findAutoIncrement) finds auto increment columns', array('item_id' => 'int unsigned NOT NULL AUTO_INCREMENT'), $fieldtype->trimDatabaseSchema($customSchema, array('findAutoIncrement' => true)));
	}

	/**
	 * Test SQL query helper defaults
	 *
	 */
	protected function testQueryHelpers() {
		list($fieldtype,, $field) = $this->makeBaseFixture();

		$query = $this->wire(new DatabaseQuerySelect());
		$fieldtype->getLoadQuery($field, $query);
		$this->check('getLoadQuery() selects field data alias', 'field_wiretest_fieldtype.data AS `wiretest_fieldtype__data`', $query->select[0]);

		$query = $this->wire(new DatabaseQuerySelect());
		$return = $fieldtype->getLoadQueryAutojoin($field, $query);
		$this->check('getLoadQueryAutojoin() defaults to getLoadQuery()', $query, $return);

		$query = $this->wire(new DatabaseQuerySelect());
		$fieldtype->getMatchQuery($query, 'field_wiretest_fieldtype', 'data', '>=', 10);
		$sql = $query->getQuery();
		$this->check('getMatchQuery() adds comparison where', 'field_wiretest_fieldtype.data>=:', $sql, '*=');
		$this->check('getMatchQuery() binds comparison value', array(10), array_values($query->getBindValues()));

		$query = $this->wire(new DatabaseQuerySelect());
		$fieldtype->getMatchQuery($query, 'field_wiretest_fieldtype', 'data', '&', 4);
		$this->check('getMatchQuery() preserves supported bitwise AND operator', 'field_wiretest_fieldtype.data&:', $query->getQuery(), '*=');

		$query = $this->wire(new DatabaseQuerySelect());
		$fieldtype->getMatchQuery($query, 'field_wiretest_fieldtype', 'data', '=', array('red', 'blue'));
		$sql = $query->getQuery();
		$this->check('getMatchQuery() array values use OR grouping', 'field_wiretest_fieldtype.data=:', $sql, '*=');
		$this->check('getMatchQuery() array values use OR operator', ' OR field_wiretest_fieldtype.data=:', $sql, '*=');
		$this->check('getMatchQuery() binds array values', array('red', 'blue'), array_values($query->getBindValues()));

		try {
			$fieldtype->getMatchQuery($this->wire(new DatabaseQuerySelect()), 'field_wiretest_fieldtype', 'data', '|', 4);
			$this->fail('getMatchQuery() should reject unsupported bitwise operator');
		} catch(PageFinderSyntaxException $e) {
			$this->ok('getMatchQuery() rejects unsupported bitwise operator');
		}

		$this->check('getMatchQuerySort() default returns false', false, $fieldtype->getMatchQuerySort($field, $this->wire(new DatabaseQuerySelect()), $field->table, 'data', false));
	}

	/**
	 * Test FieldtypeMulti defaults documented alongside Fieldtype
	 *
	 */
	protected function testFieldtypeMultiDefaults() {
		$page = $this->wire(new Page());
		$field = $this->makeField('wiretest_multi');
		$fieldtype = $this->wire(new FieldtypeWireTestMulti());
		$field->type = $fieldtype;

		$schema = $fieldtype->getDatabaseSchema($field);
		$this->check('FieldtypeMulti schema includes sort column', 'int unsigned NOT NULL', $schema['sort']);
		$this->check('FieldtypeMulti primary key includes pages_id and sort', 'PRIMARY KEY (pages_id, sort)', $schema['keys']['primary']);

		$blank = $fieldtype->getBlankValue($page, $field);
		$this->check('FieldtypeMulti blank value is WireArray', true, $blank instanceof WireArray);
		$this->check('FieldtypeMulti sanitizeValue() keeps WireArray values', $blank, $fieldtype->sanitizeValue($page, $field, $blank));
		$this->check('FieldtypeMulti sanitizeValue() rejects non-WireArray values to blank WireArray', true, $fieldtype->sanitizeValue($page, $field, array('x'))->count() === 0);

		$wakeupValue = $fieldtype->wakeupValue($page, $field, array('red', 'green'));
		$this->check('FieldtypeMulti wakeupValue() returns WireArray', true, $wakeupValue instanceof WireArray);
		$this->check('FieldtypeMulti wakeupValue() adds values', array('red', 'green'), $wakeupValue->getArray());
		$this->check('FieldtypeMulti sleepValue() returns scalar array', array('red', 'green'), $fieldtype->sleepValue($page, $field, $wakeupValue));
	}

	/**
	 * Make base test fixture
	 *
	 * @return array
	 *
	 */
	protected function makeBaseFixture() {
		$page = $this->wire(new Page());
		$field = $this->makeField('wiretest_fieldtype');
		$fieldtype = $this->wire(new FieldtypeWireTestBasic());
		$field->type = $fieldtype;
		return array($fieldtype, $page, $field);
	}

	/**
	 * Make a configured Field object
	 *
	 * @param string $name
	 * @return Field
	 *
	 */
	protected function makeField($name) {
		$field = $this->wire(new Field());
		$field->name = $name;
		$field->id = 12345;
		return $field;
	}
}

/**
 * Concrete Fieldtype test double
 *
 */
class FieldtypeWireTestBasic extends Fieldtype {

	public function sanitizeValue(Page $page, Field $field, $value) {
		if($page && $field) {}
		return trim((string) $value);
	}
}

/**
 * Concrete Fieldtype test double with null blank value
 *
 */
class FieldtypeWireTestNullBlank extends FieldtypeWireTestBasic {

	public function getBlankValue(Page $page, Field $field) {
		if($page && $field) {}
		return null;
	}
}

/**
 * Concrete FieldtypeMulti test double
 *
 */
class FieldtypeWireTestMulti extends FieldtypeMulti {
}
