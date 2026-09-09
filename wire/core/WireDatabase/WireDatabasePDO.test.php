<?php namespace ProcessWire;

/**
 * Tests for ProcessWire $database API variable
 *
 */
class WireTest_WireDatabasePDO extends WireTest {

	protected $table = WireTests::fieldPrefix . 'databasepdo';
	protected $originalDebugMode = null;
	protected $originalRetryOptions = false;
	protected $originalCli = null;

	public function init() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);

		$database->exec("DROP TABLE IF EXISTS `$table`");
		$charset = $database->escapeTable($this->wire()->config->dbCharset ?: 'utf8mb4');
		$database->exec(
			"CREATE TABLE `$table` (" .
			"`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, " .
			"`name` VARCHAR(64) NOT NULL, " .
			"`qty` INT NOT NULL DEFAULT 0, " .
			"`note` TEXT NULL, " .
			"PRIMARY KEY (`id`), " .
			"KEY `name_idx` (`name`)" .
			") ENGINE=InnoDB DEFAULT CHARSET=$charset"
		);
	}

	public function execute() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);

		$this->originalDebugMode = $database->debugMode;

		// ===== CONNECTION =====

		$this->check('$database is WireDatabasePDO', true, $database instanceof WireDatabasePDO);
		$this->check('pdo() returns PDO instance', true, $database->pdo() instanceof \PDO);
		$this->check('$database->pdo property returns PDO instance', true, $database->pdo instanceof \PDO);
		$this->check('getAttribute() returns driver name', true, is_string($database->getAttribute(\PDO::ATTR_DRIVER_NAME)));

		$dsn = WireDatabasePDO::dsn([
			'name' => 'pwtest',
			'host' => 'localhost',
			'port' => 3307,
		]);
		$this->check('dsn() builds mysql DSN with dbname', true, strpos($dsn, 'mysql:dbname=pwtest;host=localhost') === 0);
		$this->check('dsn() includes port when present', true, strpos($dsn, ';port=3307') !== false);

		$socketDsn = WireDatabasePDO::dsn([
			'name' => 'pwtest',
			'socket' => '/tmp/mysql.sock',
		]);
		$this->check('dsn() uses unix_socket when socket present', 'mysql:unix_socket=/tmp/mysql.sock;dbname=pwtest;', $socketDsn);

		// ===== SANITIZATION =====

		$this->check('escapeTable() keeps safe table name', 'foo_bar123', $database->escapeTable('foo_bar123'));
		$this->check('escapeTable() replaces unsafe characters', 'foo_bar_', $database->escapeTable('foo-bar!'));
		$this->check('escapeCol() aliases escapeTable()', 'col_name_', $database->escapeCol('col-name!'));
		$this->check('escapeTableCol() sanitizes table.column', 'foo_bar.baz_qux', $database->escapeTableCol('foo-bar.baz-qux'));
		$this->check('isOperator() accepts comparison operator', true, $database->isOperator('>=', WireDatabasePDO::operatorTypeComparison));
		$this->check('isOperator() rejects bitwise operator when comparison required', false, $database->isOperator('&', WireDatabasePDO::operatorTypeComparison));
		$this->check('isOperator() accepts bitwise operator', true, $database->isOperator('&', WireDatabasePDO::operatorTypeBitwise));
		$this->check('isOperator(get=true) returns operator string', '!=', $database->isOperator('!=', WireDatabasePDO::operatorTypeAny, true));
		$this->check('escapeOperator() returns valid operator', '<=', $database->escapeOperator('<='));
		$this->check('escapeOperator() returns fallback for invalid operator', '=', $database->escapeOperator('bad'));

		$quoted = $database->quote("O'Reilly");
		$this->check('quote() surrounds escaped value with quotes', "'", $quoted, '^=');
		$this->check('quote() escapes apostrophe', true, strpos($quoted, "O\\'Reilly") !== false || strpos($quoted, "O''Reilly") !== false);
		$this->check('escapeStr() omits surrounding quotes', false, strpos($database->escapeStr("O'Reilly"), "'") === 0);
		$this->check('escapeLike() escapes wildcard characters', '100\\%\\_match', $database->escapeLike('100%_match'));

		// ===== QUERIES =====

		$query = $database->prepare("INSERT INTO `$table` (name, qty, note) VALUES (:name, :qty, :note)");
		$query->bindValue(':name', 'alpha');
		$query->bindValue(':qty', 10, \PDO::PARAM_INT);
		$query->bindValue(':note', 'first');
		$this->check('execute(PDOStatement) returns true for insert', true, $database->execute($query));
		$id = (int) $database->lastInsertId();
		$this->check('lastInsertId() returns inserted ID', true, $id > 0);

		$row = $database->query("SELECT name, qty, note FROM `$table` WHERE id=$id")->fetch(\PDO::FETCH_ASSOC);
		$this->check('query() fetches inserted row name', 'alpha', $row['name']);
		$this->check('query() fetches inserted row qty', 10, (int) $row['qty']);

		$affected = $database->exec("UPDATE `$table` SET qty=qty+5 WHERE id=$id");
		$this->check('exec() returns affected row count', 1, $affected);
		$this->check('exec() update persisted', 15, (int) $database->query("SELECT qty FROM `$table` WHERE id=$id")->fetchColumn());

		$statement = $database->prepare("SELECT id FROM `$table` WHERE name=:name", true, 'WireTests statement class');
		$this->check('prepare(true) returns WireDatabasePDOStatement', true, $statement instanceof WireDatabasePDOStatement);
		$statement->bindValue(':name', 'alpha');
		$this->check('WireDatabasePDOStatement execute() succeeds', true, $statement->execute());
		$this->check('WireDatabasePDOStatement fetchColumn() returns row ID', $id, (int) $statement->fetchColumn());

		$badQuery = $database->prepare("SELECT * FROM `$table` WHERE missing_column=:value");
		$badQuery->bindValue(':value', 'x');
		$this->check('execute(PDOStatement, throw=false) returns false on query error', false, $database->execute($badQuery, false, 0));

		// ===== TRANSACTIONS =====

		$this->check('supportsTransaction() returns boolean', true, is_bool($database->supportsTransaction($table)));
		$this->check('allowTransaction() returns boolean', true, is_bool($database->allowTransaction($table)));

		if($database->allowTransaction($table)) {
			$this->check('beginTransaction() returns true', true, $database->beginTransaction());
			$this->check('inTransaction() true after begin', true, $database->inTransaction());
			$database->exec("INSERT INTO `$table` (name, qty) VALUES ('rollback-test', 1)");
			$this->check('rollBack() returns true', true, $database->rollBack());
			$this->check('inTransaction() false after rollback', false, $database->inTransaction());
			$this->check('rollBack() undoes inserted row', 0, (int) $database->query("SELECT COUNT(*) FROM `$table` WHERE name='rollback-test'")->fetchColumn());

			$this->check('beginTransaction() returns true for commit test', true, $database->beginTransaction());
			$database->exec("INSERT INTO `$table` (name, qty) VALUES ('commit-test', 2)");
			$this->check('commit() returns true', true, $database->commit());
			$this->check('commit() persists inserted row', 1, (int) $database->query("SELECT COUNT(*) FROM `$table` WHERE name='commit-test'")->fetchColumn());
		}

		// inTransaction() must answer without establishing a new connection: a connection
		// that does not exist has no transaction, and reconnecting here would throw from
		// inside retry handlers when the server is unavailable
		$database->closeConnection();
		$this->check('inTransaction() false after closeConnection()', false, $database->inTransaction());
		$writerProperty = new \ReflectionProperty($database, 'writer');
		$writer = $writerProperty->getValue($database);
		$this->check('inTransaction() does not reconnect a closed connection', null, $writer['pdo']);

		// ===== SCHEMA =====

		$tables = $database->getTables(false);
		$this->check('getTables(false) includes test table', true, in_array($table, $tables, true));
		$this->check('tableExists() true for test table', true, $database->tableExists($table));
		$this->check('tableExists() false for missing table', false, $database->tableExists($table . '_missing'));

		$columns = $database->getColumns($table);
		$this->check('getColumns() includes id', true, in_array('id', $columns, true));
		$this->check('getColumns() includes name', true, in_array('name', $columns, true));

		$verboseColumns = $database->getColumns($table, true);
		$this->check('getColumns(verbose) indexes by column name', true, isset($verboseColumns['id']));
		$this->check('getColumns(table, column) returns one column info', 'name', $database->getColumns($table, 'name')['name']);
		$this->check('columnExists(table, column) true for existing column', true, $database->columnExists($table, 'name'));
		$this->check('columnExists(table.column) true for existing column', true, $database->columnExists("$table.name"));
		$this->check('columnExists(..., getInfo=true) returns array', true, is_array($database->columnExists($table, 'name', true)));
		$this->check('columnExists() false for missing column', false, $database->columnExists($table, 'missing_column'));

		$indexes = $database->getIndexes($table);
		$this->check('getIndexes() includes PRIMARY', true, in_array('PRIMARY', $indexes, true));
		$this->check('getIndexes() includes named index', true, in_array('name_idx', $indexes, true));
		$this->check('getIndexes(verbose) includes columns', ['name'], $database->getIndexes($table, true)['name_idx']['columns']);
		$this->check('getPrimaryKey() returns id', 'id', $database->getPrimaryKey($table));
		$this->check('indexExists() true for name_idx', true, $database->indexExists($table, 'name_idx'));
		$this->check('indexExists(..., getInfo=true) returns array', true, is_array($database->indexExists($table, 'name_idx', true)));
		$this->check('indexExists() false for missing index', false, $database->indexExists($table, 'missing_idx'));

		$this->check('renameColumn() renames note to body', true, $database->renameColumn($table, 'note', 'body'));
		$this->check('columnExists() true for renamed column', true, $database->columnExists($table, 'body'));
		$this->check('renameColumns() renames body back to note', 1, $database->renameColumns($table, ['body' => 'note']));
		$this->check('columnExists() true after renameColumns()', true, $database->columnExists($table, 'note'));

		// ===== INFO AND CUSTOM =====

		$this->check('getVersion() returns non-empty string', true, strlen($database->getVersion()) > 0);
		$this->check('getVersion(true) starts with numeric version', true, preg_match('/^\d+(\.\d+){1,2}/', $database->getVersion(true)) === 1);
		$this->check('getServerType() returns non-empty string', true, strlen($database->getServerType()) > 0);
		$this->check('getRegexEngine() returns known engine name', true, in_array($database->getRegexEngine(), ['ICU', 'HenrySpencer'], true));
		$this->check('getEngine() returns string', true, is_string($database->getEngine()));
		$this->check('getCharset() returns string', true, is_string($database->getCharset()));
		$this->check('getMaxIndexLength() returns positive integer', true, $database->getMaxIndexLength() > 0);
		$this->check('getVariable(version) returns value', true, strlen((string) $database->getVariable('version', false, false)) > 0);
		$this->check('getTime() returns ISO-like string', true, preg_match('/^\d{4}-\d{2}-\d{2}/', $database->getTime()) === 1);
		$this->check('getTime(true) returns timestamp', true, is_int($database->getTime(true)) && $database->getTime(true) > 0);

		$this->check('getStopwords(myisam) returns array', true, is_array($database->getStopwords('myisam')));
		$this->check('isStopword(myisam) recognizes common stopword', true, $database->isStopword('the', 'myisam'));

		$backups = $database->backups();
		$this->check('backups() returns WireDatabaseBackup', true, $backups instanceof WireDatabaseBackup);

		$mode = $database->sqlMode();
		$this->check('sqlMode() get returns string', true, is_string($mode));

		$database->queryLog(true);
		$database->query("SELECT 1", 'WireTests queryLog');
		$log = $database->queryLog();
		$this->check('queryLog(true) starts and resets query log', true, count($log) >= 1);
		$this->check('queryLog() includes logged query', true, strpos(end($log), 'SELECT 1') !== false);
		$this->check('queryLog(false) stops query logging', true, $database->queryLog(false));
		$this->check('queryLog() ignores entries when stopped', false, $database->queryLog('SELECT 2'));

		// ===== RETRYABLE ERROR CLASSIFICATION =====

		$dialect = $database->dialect();
		$this->check('dialect() returns WireDatabaseDialect', true, $dialect instanceof WireDatabaseDialect);

		$e = $this->fakePDOException('Deadlock found when trying to get lock; try restarting transaction', '40001', 1213);
		$this->check('getRetryableErrorType() deadlock by SQLSTATE 40001', 'deadlock', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('Deadlock found when trying to get lock; try restarting transaction', 'HY000', 1213);
		$this->check('getRetryableErrorType() deadlock by errno 1213 alone', 'deadlock', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('Deadlock found when trying to get lock; try restarting transaction', 1213);
		$this->check('getRetryableErrorType() deadlock by integer code', 'deadlock', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('Communication link failure', '08S01', 1053);
		$this->check('getRetryableErrorType() comm-failure by SQLSTATE 08S01', 'comm-failure', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('Lost connection to MySQL server during query', 'HY000', 2013);
		$this->check('getRetryableErrorType() comm-failure by errno 2013', 'comm-failure', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('MySQL server has gone away', 'HY000', 2006);
		$this->check('getRetryableErrorType() gone-away by errno 2006', 'gone-away', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('MySQL server has gone away', 'HY000');
		$this->check('getRetryableErrorType() gone-away by message alone', 'gone-away', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException("Unknown column 'foo.bar' in 'field list'", '42S22', 1054);
		$this->check('getRetryableErrorType() blank for unknown column error', '', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException("Duplicate entry 'x' for key 'PRIMARY'", '23000', 1062);
		$this->check('getRetryableErrorType() blank for duplicate entry error', '', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException('Incorrect string value', 'HY000', 1366);
		$this->check('getRetryableErrorType() blank for generic HY000 error', '', $dialect->getRetryableErrorType($e));

		// reconnect attempts while the server is unavailable must classify as retryable,
		// otherwise a retry loop dies on its first reconnect attempt during an outage
		$e = $this->fakePDOException('SQLSTATE[HY000] [2002] Connection refused', 2002);
		$this->check('getRetryableErrorType() comm-failure for errno 2002 connection refused', 'comm-failure', $dialect->getRetryableErrorType($e));

		$e = $this->fakePDOException("SQLSTATE[HY000] [2003] Can't connect to MySQL server on 'db' (111)", 'HY000', 2003);
		$this->check('getRetryableErrorType() comm-failure for errno 2003 cannot connect', 'comm-failure', $dialect->getRetryableErrorType($e));

		// ===== RETRY POLICY =====

		$config = $this->wire()->config;
		$this->originalRetryOptions = $config->dbRetryOptions;
		$this->originalCli = $config->cli;

		// default policy
		$config->dbRetryOptions = array();
		$config->cli = false;
		$this->check('retryDelay() first deadlock retry waits 100ms', 100000, $database->retryDelay('deadlock', 0));
		$this->check('retryDelay() second comm-failure retry waits 200ms', 200000, $database->retryDelay('comm-failure', 1));
		$this->check('retryDelay() gone-away retry also delayed', 100000, $database->retryDelay('gone-away', 0));
		$this->check('retryDelay() false once default maxTries reached', false, $database->retryDelay('deadlock', 3));
		$this->check('retryDelay() false for blank error type', false, $database->retryDelay('', 0));

		// explicit per-call maxTries overrides configured policy
		$this->check('retryDelay() explicit maxTries extends retries', 400000, $database->retryDelay('comm-failure', 3, 5));
		$this->check('retryDelay() explicit maxTries=0 disables retries', false, $database->retryDelay('comm-failure', 0, 0));

		// configured maxTries, delay and cap
		$config->dbRetryOptions = array('maxTries' => 5, 'delayMs' => 1000, 'maxDelayMs' => 2500);
		$this->check('retryDelay() honors configured delayMs below cap', 2000000, $database->retryDelay('deadlock', 1));
		$this->check('retryDelay() caps single delay at maxDelayMs', 2500000, $database->retryDelay('deadlock', 2));
		$this->check('retryDelay() allows retry up to configured maxTries', 2500000, $database->retryDelay('deadlock', 4));
		$this->check('retryDelay() false past configured maxTries', false, $database->retryDelay('deadlock', 5));

		// CLI-extended retries apply to connection-loss errors only
		$config->dbRetryOptions = array('maxTries' => 3, 'cliMaxTries' => 10);
		$config->cli = true;
		$this->check('retryDelay() CLI extends comm-failure retries', true, is_int($database->retryDelay('comm-failure', 9)));
		$this->check('retryDelay() CLI extends gone-away retries', true, is_int($database->retryDelay('gone-away', 5)));
		$this->check('retryDelay() CLI comm-failure exhausted at cliMaxTries', false, $database->retryDelay('comm-failure', 10));
		$this->check('retryDelay() CLI does not extend deadlock retries', false, $database->retryDelay('deadlock', 3));
		$config->cli = false;
		$this->check('retryDelay() cliMaxTries ignored for web requests', false, $database->retryDelay('comm-failure', 3));

		$config->dbRetryOptions = $this->originalRetryOptions;
		$config->cli = $this->originalCli;
		$this->originalRetryOptions = false;
		$this->originalCli = null;
	}

	/**
	 * Make a PDOException carrying the given code and optional MySQL error number
	 *
	 * @param string $message
	 * @param string|int $code SQLSTATE string or driver error number
	 * @param int|null $errno MySQL error number for errorInfo[1], omit for no errorInfo
	 * @return \PDOException
	 *
	 */
	protected function fakePDOException($message, $code, $errno = null) {
		$e = new \PDOException($message);
		$property = new \ReflectionProperty($e, 'code');
		$property->setValue($e, $code);
		if($errno !== null) $e->errorInfo = array(is_string($code) ? $code : 'HY000', $errno, $message);
		return $e;
	}

	public function finish() {
		$database = $this->wire()->database;
		$table = $database->escapeTable($this->table);

		if($database->inTransaction()) $database->rollBack();
		$database->exec("DROP TABLE IF EXISTS `$table`");

		if($this->originalDebugMode !== null) {
			$database->setDebugMode($this->originalDebugMode);
		}

		// restore config in case execute() did not reach its own restore
		$config = $this->wire()->config;
		if($this->originalRetryOptions !== false) $config->dbRetryOptions = $this->originalRetryOptions;
		if($this->originalCli !== null) $config->cli = $this->originalCli;
	}
}
