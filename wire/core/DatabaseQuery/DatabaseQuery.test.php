<?php namespace ProcessWire;

/**
 * Tests for ProcessWire DatabaseQuery class (base class)
 *
 * Since DatabaseQuery is abstract, tests are conducted via DatabaseQuerySelect
 * instances. SELECT-specific features (clause ordering, dbCache, comment, limit
 * parsing, orderby prepend) are tested in DatabaseQuerySelect.test.php.
 *
 */
class WireTest_DatabaseQuery extends WireTest {

	protected $table = WireTests::fieldPrefix . 'databasequery';
	protected $originalDbCache = null;

	public function init() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$charset = $database->escapeTable($this->wire()->config->dbCharset ?: 'utf8mb4');

		$database->exec("DROP TABLE IF EXISTS `$table`");
		$database->exec(
			"CREATE TABLE `$table` (" .
			"`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, " .
			"`name` VARCHAR(64) NOT NULL, " .
			"`qty` INT NOT NULL DEFAULT 0, " .
			"PRIMARY KEY (`id`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=$charset"
		);

		$this->originalDbCache = DatabaseQuerySelect::$dbCache;
		DatabaseQuerySelect::$dbCache = true;

		$database->exec("INSERT INTO `$table` (name, qty) VALUES ('alpha', 10), ('beta', 20), ('gamma', 30)");
	}

	public function execute() {
		$this->testBindValues();
		$this->testBindTypes();
		$this->testBindOptions();
		$this->testUniqueBindKey();
		$this->testFluentInterface();
		$this->testCombiningQueries();
		$this->testSQLGeneration();
		$this->testDebugQuery();
		$this->testExecution();
		$this->testProperties();
		$this->testErrorHandling();
	}

	public function finish() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);
		$database->exec("DROP TABLE IF EXISTS `$table`");

		if($this->originalDbCache !== null) {
			DatabaseQuerySelect::$dbCache = $this->originalDbCache;
		}
	}

	protected function testBindValues() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);

		// bindValue() with colon prefix
		$q = new DatabaseQuerySelect();
		$q->bindValue(':name', 'hello');
		$this->check('bindValue() with colon stores value', 'hello', $q->bindValues()[':name']);

		// bindValue() without colon auto-prepends
		$q = new DatabaseQuerySelect();
		$q->bindValue('status', 1);
		$values = $q->bindValues();
		$this->check('bindValue() without colon auto-prepends colon', true, array_key_exists(':status', $values));
		$this->check('bindValue() without colon stores value', 1, $values[':status']);

		// bindValue() returns $this for chaining
		$q = new DatabaseQuerySelect();
		$result = $q->bindValue(':a', 1)->bindValue(':b', 2);
		$this->check('bindValue() returns $this for chaining', true, $result === $q);

		// bindValue() overwrites same key
		$q = new DatabaseQuerySelect();
		$q->bindValue(':name', 'first');
		$q->bindValue(':name', 'second');
		$this->check('bindValue() overwrites same key', 'second', $q->bindValues()[':name']);

		// bindValueGetKey() returns key starting with colon
		$q = new DatabaseQuerySelect();
		$key = $q->bindValueGetKey('hello');
		$this->check('bindValueGetKey() returns key starting with colon', true, strpos($key, ':') === 0);

		// bindValueGetKey() string value key contains "s"
		$q = new DatabaseQuerySelect();
		$strKey = $q->bindValueGetKey('hello');
		$this->check('bindValueGetKey() string value key contains "s"', true, strpos($strKey, 's') !== false);

		// bindValueGetKey() int value key contains "i"
		$intKey = $q->bindValueGetKey(123);
		$this->check('bindValueGetKey() int value key contains "i"', true, strpos($intKey, 'i') !== false);

		// bindValueGetKey() generates unique keys
		$q = new DatabaseQuerySelect();
		$k1 = $q->bindValueGetKey('hello');
		$k2 = $q->bindValueGetKey('hello');
		$this->check('bindValueGetKey() generates unique keys on same instance', true, $k1 !== $k2);

		// bindValueGetKey() binds the value
		$q = new DatabaseQuerySelect();
		$k = $q->bindValueGetKey('hello');
		$this->check('bindValueGetKey() binds the value', 'hello', $q->bindValues()[$k]);

		// bindValues() get returns array
		$q = new DatabaseQuerySelect();
		$q->bindValue(':a', 1)->bindValue(':b', 2);
		$this->check('bindValues() get returns array', true, is_array($q->bindValues()));

		// bindValues() set merges rather than replaces
		$q = new DatabaseQuerySelect();
		$q->bindValue(':a', 1);
		$q->bindValues([':b' => 2, ':c' => 3]);
		$this->check('bindValues() set merges rather than replaces', 3, count($q->bindValues()));

		// bindValues() set returns $this
		$q = new DatabaseQuerySelect();
		$result = $q->bindValues([':a' => 1]);
		$this->check('bindValues() set returns $this', true, $result === $q);

		// getBindValues() returns array
		$q = new DatabaseQuerySelect();
		$q->bindValue(':a', 1);
		$this->check('getBindValues() returns array', true, is_array($q->getBindValues()));

		// getBindValues(count=true) returns count
		$q = new DatabaseQuerySelect();
		$q->bindValue(':a', 1)->bindValue(':b', 2);
		$this->check('getBindValues(count=true) returns count', 2, $q->getBindValues(['count' => true]));

		// getBindValues(inSQL) filters to referenced params
		$q = new DatabaseQuerySelect();
		$q->where("name=?", "hello")->where("status=:status", [':status' => 1]);
		$sql = $q->getQuery();
		$allCount = $q->getBindValues(['count' => true]);
		$inSqlCount = $q->getBindValues(['inSQL' => $sql, 'count' => true]);
		$this->check('getBindValues(inSQL) returns all when SQL references all', $allCount, $inSqlCount);
		$partialCount = $q->getBindValues(['inSQL' => 'status=:status', 'count' => true]);
		$this->check('getBindValues(inSQL) filters to referenced params only', 1, $partialCount);

		// getBindValues(query=DatabaseQuery) copies to query
		$q = new DatabaseQuerySelect();
		$q->where("name=?", "hello")->where("status>?", 0);
		$target = new DatabaseQuerySelect();
		$count = $q->getBindValues(['query' => $target, 'count' => true]);
		$this->check('getBindValues(query=DatabaseQuery) returns count', 2, $count);
		$this->check('getBindValues(query=DatabaseQuery) copies values to target', 2, $target->getBindValues(['count' => true]));

		// getBindValues(object) as shorthand for query option
		$q = new DatabaseQuerySelect();
		$q->where("name=?", "hello");
		$target2 = new DatabaseQuerySelect();
		$q->getBindValues($target2);
		$this->check('getBindValues(object) copies to query', 1, $target2->getBindValues(['count' => true]));

		// getBindValues(query=PDOStatement) copies to statement
		$q = new DatabaseQuerySelect();
		$q->select("*")->from($table)->where("name=?", "alpha");
		$stmt = $database->prepare($q->getQuery());
		$q->getBindValues(['query' => $stmt]);
		$stmt->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$this->check('getBindValues(query=PDOStatement) binds values to statement', true, $row !== false);

		// copyBindValuesTo() returns count
		$q = new DatabaseQuerySelect();
		$q->where("name=?", "hello")->where("status>?", 0);
		$target = new DatabaseQuerySelect();
		$count = $q->copyBindValuesTo($target);
		$this->check('copyBindValuesTo() returns count', 2, $count);
		$this->check('copyBindValuesTo() copies values to target', 2, $target->getBindValues(['count' => true]));

		// copyBindValuesTo() with inSQL option
		$q = new DatabaseQuerySelect();
		$q->where("name=:name", [':name' => 'hello'])->where("status=:status", [':status' => 1]);
		$target = new DatabaseQuerySelect();
		$count = $q->copyBindValuesTo($target, ['inSQL' => 'name=:name']);
		$this->check('copyBindValuesTo(inSQL) copies only referenced params', 1, $count);
	}

	protected function testBindTypes() {
		// setBindType() with string type names
		$q = new DatabaseQuerySelect();
		$q->bindValue(':name', 'hello', 'string');
		$q->bindValue(':id', 5, 'int');
		$q->bindValue(':flag', true, 'bool');
		$q->bindValue(':empty', null, 'null');
		$types = $q->bindTypes();
		$this->check('setBindType("string") sets PDO::PARAM_STR', \PDO::PARAM_STR, $types[':name']);
		$this->check('setBindType("int") sets PDO::PARAM_INT', \PDO::PARAM_INT, $types[':id']);
		$this->check('setBindType("bool") sets PDO::PARAM_BOOL', \PDO::PARAM_BOOL, $types[':flag']);
		$this->check('setBindType("null") sets PDO::PARAM_NULL', \PDO::PARAM_NULL, $types[':empty']);

		// setBindType() with PDO constants
		$q = new DatabaseQuerySelect();
		$q->bindValue(':name', 'hello', \PDO::PARAM_STR);
		$q->bindValue(':id', 5, \PDO::PARAM_INT);
		$types = $q->bindTypes();
		$this->check('setBindType(PDO::PARAM_STR) sets type', \PDO::PARAM_STR, $types[':name']);
		$this->check('setBindType(PDO::PARAM_INT) sets type', \PDO::PARAM_INT, $types[':id']);

		// bindTypes() get returns array when empty
		$q = new DatabaseQuerySelect();
		$this->check('bindTypes() get returns array when empty', true, is_array($q->bindTypes()));

		// bindTypes() set merges
		$q = new DatabaseQuerySelect();
		$q->bindValue(':a', 1, 'int');
		$q->bindTypes([':b' => \PDO::PARAM_STR]);
		$types = $q->bindTypes();
		$this->check('bindTypes() set merges existing types', 2, count($types));
	}

	protected function testBindOptions() {
		// bindOption() get default prefix
		$q = new DatabaseQuerySelect();
		$this->check('bindOption() default prefix is "pw"', 'pw', $q->bindOption('prefix'));

		// bindOption() get default suffix
		$this->check('bindOption() default suffix is "X"', 'X', $q->bindOption('suffix'));

		// bindOption() get default global
		$this->check('bindOption() default global is false', false, $q->bindOption('global'));

		// bindOption() set prefix
		$q = new DatabaseQuerySelect();
		$q->bindOption('prefix', 'test');
		$this->check('bindOption() set prefix', 'test', $q->bindOption('prefix'));

		// bindOption() set suffix
		$q->bindOption('suffix', 'Y');
		$this->check('bindOption() set suffix', 'Y', $q->bindOption('suffix'));

		// bindOption() set global
		$q->bindOption('global', true);
		$this->check('bindOption() set global', true, $q->bindOption('global'));

		// bindOption(true) get all
		$q = new DatabaseQuerySelect();
		$opts = $q->bindOption(true);
		$this->check('bindOption(true) returns all options', true, is_array($opts) && isset($opts['prefix']));

		// bindOption(true, array) set all
		$q = new DatabaseQuerySelect();
		$q->bindOption(true, ['prefix' => 'custom', 'suffix' => 'Z', 'global' => true]);
		$this->check('bindOption(true, array) sets prefix', 'custom', $q->bindOption('prefix'));
		$this->check('bindOption(true, array) sets suffix', 'Z', $q->bindOption('suffix'));
		$this->check('bindOption(true, array) sets global', true, $q->bindOption('global'));
	}

	protected function testUniqueBindKey() {
		// getUniqueBindKey() returns key starting with colon
		$q = new DatabaseQuerySelect();
		$key = $q->getUniqueBindKey();
		$this->check('getUniqueBindKey() returns key starting with colon', true, strpos($key, ':') === 0);

		// getUniqueBindKey() generates unique keys
		$q = new DatabaseQuerySelect();
		$k1 = $q->getUniqueBindKey();
		$k2 = $q->getUniqueBindKey();
		$this->check('getUniqueBindKey() generates unique keys', true, $k1 !== $k2);

		// getUniqueBindKey() with string value
		$q = new DatabaseQuerySelect();
		$key = $q->getUniqueBindKey(['value' => 'hello']);
		$this->check('getUniqueBindKey() string value key contains "s"', true, strpos($key, 's') !== false);

		// getUniqueBindKey() with int value
		$key = $q->getUniqueBindKey(['value' => 123]);
		$this->check('getUniqueBindKey() int value key contains "i"', true, strpos($key, 'i') !== false);

		// getUniqueBindKey() with array value
		$key = $q->getUniqueBindKey(['value' => [1, 2]]);
		$this->check('getUniqueBindKey() array value key contains "a"', true, strpos($key, 'a') !== false);

		// getUniqueBindKey() with global option includes prefix
		$q = new DatabaseQuerySelect();
		$q->bindOption('global', true);
		$key = $q->getUniqueBindKey(['value' => 'hello']);
		$this->check('getUniqueBindKey() global includes prefix', true, strpos($key, 'pw') !== false);

		// getUniqueBindKey() with custom prefix
		$q = new DatabaseQuerySelect();
		$key = $q->getUniqueBindKey(['value' => null, 'prefix' => 'custom']);
		$this->check('getUniqueBindKey() uses custom prefix when no value', true, strpos($key, 'custom') !== false);

		// getUniqueBindKey() with custom suffix
		$q = new DatabaseQuerySelect();
		$key = $q->getUniqueBindKey(['suffix' => 'Z']);
		$this->check('getUniqueBindKey() uses custom suffix', 'Z', substr($key, -1));

		// getUniqueBindKey() with provided key
		$q = new DatabaseQuerySelect();
		$key = $q->getUniqueBindKey(['key' => 'mykey']);
		$this->check('getUniqueBindKey() with provided key starts with colon', true, strpos($key, ':') === 0);
		$this->check('getUniqueBindKey() with provided key contains key name', true, strpos($key, 'mykey') !== false);
	}

	protected function testFluentInterface() {
		// where() first call adds WHERE clause
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("status>?", 0);
		$sql = $q->getQuery();
		$this->check('where() first call adds WHERE clause', 'WHERE', $sql, '*=');
		$this->check('where() binds implied parameter', 1, count($q->bindValues()));

		// where() subsequent calls append AND
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("status>?", 0);
		$q->where("name=?", "hello");
		$sql = $q->getQuery();
		$this->check('where() subsequent call appends AND', 'AND', $sql, '*=');

		// where() with named params (with colon)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("name=:name", [':name' => 'hello']);
		$this->check('where() named params with colon binds value', 'hello', $q->bindValues()[':name']);

		// where() with named params (without colon, auto-prepended)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("name=:name", ['name' => 'hello']);
		$this->check('where() named params without colon auto-prepends', 'hello', $q->bindValues()[':name']);

		// where() with implied params (?)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("name=? AND status=?", ['hello', 1]);
		$this->check('where() implied params binds all values', 2, count($q->bindValues()));

		// where() with single ? without array
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("status>?", 0);
		$this->check('where() single ? without array binds value', 1, count($q->bindValues()));

		// where(null) clears WHERE clause
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages");
		$q->where("status>?", 0)->where("name=?", "test");
		$q->where(null);
		$sql = $q->getQuery();
		$this->check('where(null) clears WHERE clause', false, stripos($sql, 'WHERE') !== false);

		// where() with no args returns $this
		$q = new DatabaseQuerySelect();
		$result = $q->where();
		$this->check('where() with no args returns $this', true, $result === $q);

		// Method chaining returns $this
		$q = new DatabaseQuerySelect();
		$result = $q->select("id")->from("pages")->where("status>?", 0);
		$this->check('Method chaining returns $this', true, $result === $q);
	}

	protected function testCombiningQueries() {
		// copyTo() copies clauses
		$a = new DatabaseQuerySelect();
		$a->select("id")->from("pages")->where("status>?", 0)->orderby("name");
		$b = new DatabaseQuerySelect();
		$a->copyTo($b);
		$this->check('copyTo() copies clauses to target', $a->getQuery(), $b->getQuery());

		// copyTo() returns item count
		$a = new DatabaseQuerySelect();
		$a->select("id")->from("pages")->where("status>?", 0)->orderby("name");
		$b = new DatabaseQuerySelect();
		$count = $a->copyTo($b);
		$this->check('copyTo() returns item count', 4, $count);

		// copyTo() self returns 0
		$a = new DatabaseQuerySelect();
		$a->select("id")->from("pages");
		$this->check('copyTo() self returns 0', 0, $a->copyTo($a));

		// copyTo() with specific methods
		$a = new DatabaseQuerySelect();
		$a->select("id")->from("pages")->where("status>?", 0)->orderby("name");
		$b = new DatabaseQuerySelect();
		$a->copyTo($b, ['where']);
		$sqlB = $b->getQuery();
		$this->check('copyTo() with specific methods copies only those', true, stripos($sqlB, 'WHERE') !== false);
		$this->check('copyTo() with specific methods excludes others', false, stripos($sqlB, 'ORDER BY') !== false);

		// copyBindValuesTo() copies values after copyTo()
		$a = new DatabaseQuerySelect();
		$a->where("name=?", "hello")->where("status>?", 0);
		$b = new DatabaseQuerySelect();
		$a->copyTo($b);
		$count = $a->copyBindValuesTo($b);
		$this->check('copyBindValuesTo() returns count', 2, $count);
		$this->check('copyBindValuesTo() target has values', 2, $b->getBindValues(['count' => true]));
	}

	protected function testSQLGeneration() {
		// getQuery() returns full SQL containing all clauses
		$q = new DatabaseQuerySelect();
		$q->select("id, name")->from("pages")->where("status>?", 0)->orderby("name")->limit(25);
		$sql = $q->getQuery();
		$this->check('getQuery() returns non-empty string', true, is_string($sql) && strlen($sql) > 0);
		$this->check('getQuery() contains SELECT', 'SELECT', $sql, '*=');
		$this->check('getQuery() contains FROM', 'FROM', $sql, '*=');
		$this->check('getQuery() contains WHERE', 'WHERE', $sql, '*=');
		$this->check('getQuery() contains ORDER BY', 'ORDER BY', $sql, '*=');
		$this->check('getQuery() contains LIMIT', 'LIMIT', $sql, '*=');

		// getSQL() without method returns full query
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0);
		$this->check('getSQL() without method returns full query', $q->getQuery(), $q->getSQL());

		// getSQL() with method returns method SQL only
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0)->orderby("name");
		$whereSql = $q->getSQL('where');
		$this->check('getSQL("where") contains WHERE', 'WHERE', $whereSql, '*=');
		$this->check('getSQL("where") does not contain SELECT', false, stripos($whereSql, 'SELECT') !== false);

		// getQueryMethod() returns method SQL
		$orderbySql = $q->getQueryMethod('orderby');
		$this->check('getQueryMethod("orderby") contains ORDER BY', 'ORDER BY', $orderbySql, '*=');

		// getQueryMethod() with blank returns full query
		$this->check('getQueryMethod("") returns full query', $q->getQuery(), $q->getQueryMethod(''));

		// getQueryMethod() with unknown method returns empty string
		$this->check('getQueryMethod() unknown method returns empty string', '', $q->getQueryMethod('nonexistent'));
	}

	protected function testDebugQuery() {
		// getDebugQuery() populates auto-generated key values inline (strtr path)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("name=?", "hello");
		$debug = $q->getDebugQuery();
		$this->check('getDebugQuery() populates auto-generated key values', true, strpos($debug, "'hello'") !== false);

		// getDebugQuery() populates integer values inline
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0);
		$debug = $q->getDebugQuery();
		$this->check('getDebugQuery() populates integer values', true, preg_match('/status>\s*0/', $debug) === 1);

		// getDebugQuery() populates named param values inline (preg_replace path)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("name=:name", [':name' => 'about']);
		$debug = $q->getDebugQuery();
		$this->check('getDebugQuery() populates named param values', true, strpos($debug, "'about'") !== false);

		// getDebugQuery() preserves $ in named param values (preg_replace_callback fix)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("name=:name", [':name' => 'price $1 each']);
		$debug = $q->getDebugQuery();
		$this->check('getDebugQuery() preserves $ in named param values', true, strpos($debug, '$1') !== false);

		// getDebugQuery() preserves $ in auto-generated key values (strtr path)
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("name=?", 'price $1 each');
		$debug = $q->getDebugQuery();
		$this->check('getDebugQuery() preserves $ in auto-generated key values', true, strpos($debug, '$1') !== false);

		// getDebugQuery() handles backslash in named param values
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("name=:name", [':name' => 'test\\value']);
		$debug = $q->getDebugQuery();
		$this->check('getDebugQuery() handles backslash in values', true, is_string($debug) && strpos($debug, 'test') !== false);
	}

	protected function testExecution() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);

		// prepare() returns PDOStatement with bind values
		$q = new DatabaseQuerySelect();
		$q->select("*")->from($table)->where("qty>=?", 20)->orderby("name");
		$stmt = $q->prepare();
		$this->check('prepare() returns PDOStatement', true, $stmt instanceof \PDOStatement);
		$stmt->execute();
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$this->check('prepare() + execute() returns correct row count', 2, count($rows));
		$this->check('prepare() + execute() first row is beta', 'beta', $rows[0]['name']);

		// execute() returns PDOStatement
		$q = new DatabaseQuerySelect();
		$q->select("*")->from($table)->where("name=?", "alpha");
		$stmt = $q->execute();
		$this->check('execute() returns PDOStatement', true, $stmt instanceof \PDOStatement);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$this->check('execute() returns correct row', 'alpha', $row['name']);

		// execute(returnQuery=false) returns true on success
		$q = new DatabaseQuerySelect();
		$q->select("*")->from($table)->where("name=?", "alpha");
		$result = $q->execute(['returnQuery' => false]);
		$this->check('execute(returnQuery=false) returns true on success', true, $result);

		// execute(throw=false, returnQuery=false) returns false on error
		$q = new DatabaseQuerySelect();
		$q->select("*")->from($table)->where("missing_column_xyz=?", "x");
		$result = $q->execute(['throw' => false, 'returnQuery' => false]);
		$this->check('execute(throw=false) returns false on error', false, $result);
	}

	protected function testProperties() {
		// query property aliases getQuery()
		$q = new DatabaseQuerySelect();
		$q->select("id")->from("pages")->where("status>?", 0);
		$this->check('query property aliases getQuery()', $q->getQuery(), $q->query);

		// sql property aliases getQuery()
		$this->check('sql property aliases getQuery()', $q->getQuery(), $q->sql);

		// bindValues property returns array
		$q = new DatabaseQuerySelect();
		$q->bindValue(':a', 1);
		$this->check('bindValues property returns array', true, is_array($q->bindValues));

		// bindKeys property returns array
		$this->check('bindKeys property returns array', true, is_array($q->bindKeys));

		// bindOptions property returns array
		$this->check('bindOptions property returns array', true, is_array($q->bindOptions));
	}

	protected function testErrorHandling() {
		// Mixing named and implied params throws WireException
		$q = new DatabaseQuerySelect();
		$threw = false;
		try {
			$q->where("name=:name AND id=?", [':name' => 'test', 5]);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('Mixing named and implied params throws WireException', true, $threw);

		// Missing implied param throws WireException
		$q = new DatabaseQuerySelect();
		$threw = false;
		try {
			$q->where("name=? AND status=?", ['hello']);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('Missing implied param throws WireException', true, $threw);

		// Missing named param throws WireException
		$q = new DatabaseQuerySelect();
		$threw = false;
		try {
			$q->where("name=:name", [':other' => 'test']);
		} catch(WireException $e) {
			$threw = true;
		}
		$this->check('Missing named param throws WireException', true, $threw);
	}
}
