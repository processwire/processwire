<?php namespace ProcessWire;

/** Tests session handler compatibility without replacing the test runner's session. */
class WireTest_WireSessionHandlerAdaptor extends WireTest {

	public function execute() {
		$this->check('Adaptor provides create_sid()', true, method_exists(WireSessionHandlerAdaptor::class, 'create_sid'));
		$this->check('Adaptor provides validateId()', true, method_exists(WireSessionHandlerAdaptor::class, 'validateId'));
		$this->testLegacyHandler();
		$this->testDatabaseValidation();
		$this->testSessionLifecycle();
	}

	protected function testLegacyHandler() {
		$handler = new WireTest_AdaptorMemoryHandler();
		$handler->data = ['known' => 'value|s:4:"test";', 'zero' => '0', 'empty' => '', 'failed' => false];
		$adaptor = new WireSessionHandlerAdaptor($handler);
		$this->check('Legacy handler validates stored data', true, $adaptor->validateId('known'));
		$this->check('Legacy validation reads once', 1, $handler->reads);
		$this->check('Normal read reuses validated data', $handler->data['known'], $adaptor->read('known'));
		$this->check('Validation and read do not duplicate reads', 1, $handler->reads);
		$adaptor->read('known');
		$this->check('Validated read cache is consumed once', 2, $handler->reads);
		$this->check('Unknown legacy ID is rejected', false, $adaptor->validateId('missing'));
		$this->check('Failed legacy read is rejected', false, $adaptor->validateId('failed'));
		$this->check('Legacy empty data does not establish ID existence', false, $adaptor->validateId('empty'));
		$reads = $handler->reads;
		$this->check('Empty collision-check data is preserved', '', $adaptor->read('empty'));
		$this->check('Empty collision check does not duplicate the next read', $reads, $handler->reads);
		$this->check('Nonempty zero string is valid data', true, $adaptor->validateId('zero'));
		$this->check('Cached zero string is preserved', '0', $adaptor->read('zero'));
		$this->check('Read failure remains false', false, $adaptor->read('failed'));

		foreach(['write', 'destroy', 'close', 'open'] as $operation) {
			$handler->data['known'] = 'old';
			$adaptor->validateId('known');
			if($operation === 'write') $adaptor->write('known', 'new');
			if($operation === 'destroy') $adaptor->destroy('known');
			if($operation === 'close') $adaptor->close();
			if($operation === 'open') $adaptor->open('', 'test');
			$reads = $handler->reads;
			$adaptor->read('known');
			$this->check("$operation clears the validated read cache", $reads + 1, $handler->reads);
		}
		$handler->data['known'] = 'old';
		$adaptor->validateId('known');
		$adaptor->read('different');
		$reads = $handler->reads;
		$adaptor->read('known');
		$this->check('Reading another ID clears the validated read cache', $reads + 1, $handler->reads);
		$privateHelper = new class extends WireTest_AdaptorMemoryHandler {
			protected function validateId($id) { throw new WireException('Private helper must not be called'); }
		};
		$privateHelper->data['known'] = 'test';
		$adaptor = new WireSessionHandlerAdaptor($privateHelper);
		$this->check('An existing protected helper is not treated as a callback', true, $adaptor->validateId('known'));
	}

	protected function testDatabaseValidation() {
		$driver = $this->wire()->database->getAttribute(\PDO::ATTR_DRIVER_NAME);
		if(!in_array($driver, ['mysql', 'sqlite'], true)) return;
		if(!class_exists(SessionHandlerDB::class, false)) {
			require_once($this->wire()->config->paths->modules . 'Session/SessionHandlerDB/SessionHandlerDB.module');
		}
		$config = clone $this->wire()->config;
		$config->dbReader = [];
		$options = $config->dbOptions;
		$options[\PDO::ATTR_PERSISTENT] = false;
		$config->dbOptions = $options;
		$database = WireDatabasePDO::getInstance($config);
		$pdo = $database->pdo();
		$known = bin2hex(random_bytes(16));
		$empty = bin2hex(random_bytes(16));
		$missing = bin2hex(random_bytes(16));
		// This connection-local table cannot alter a site's real sessions table.
		$pdo->exec('CREATE TEMPORARY TABLE sessions (id VARCHAR(64) PRIMARY KEY, data TEXT NOT NULL)');
		try {
			$query = $pdo->prepare('INSERT INTO sessions (id, data) VALUES (?, ?)');
			$query->execute([$known, 'test']);
			$query->execute([$empty, '']);
			$handler = new class($database) extends SessionHandlerDB {
				public $reads = 0;
				public function __construct($database) {
					parent::__construct();
					$this->database = $database;
				}
				public function read($id) {
					$this->reads++;
					return parent::read($id);
				}
			};
			$adaptor = new WireSessionHandlerAdaptor($handler);
			$this->check('Database validates existing session', true, $adaptor->validateId($known));
			$this->check('Database validates existing empty session', true, $adaptor->validateId($empty));
			$this->check('Database rejects unknown session', false, $adaptor->validateId($missing));
			$this->check('Database existence validation does not call read()', 0, $handler->reads);
			if($driver === 'mysql') {
				$query = $pdo->prepare('SELECT IS_FREE_LOCK(?)');
				$query->execute([$known]);
				$this->check('Database validation does not acquire the read lock', 1, (int) $query->fetchColumn());
			}
		} finally {
			$pdo->exec($driver === 'mysql' ? 'DROP TEMPORARY TABLE sessions' : 'DROP TABLE temp.sessions');
		}
	}

	protected function testSessionLifecycle() {
		if(!is_callable('proc_open')) {
			$this->ok('Isolated session lifecycle skipped: proc_open unavailable');
			return;
		}
		$command = [PHP_BINARY, __DIR__ . '/WireSessionHandlerAdaptor.fixture.php', '--session-adaptor-fixture'];
		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->check('Isolated session lifecycle process starts', true, is_resource($process));
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$status = proc_close($process);
		$this->check('Isolated session lifecycle has no warnings', '', $error);
		$this->check('Isolated session lifecycle exits successfully', 0, $status);
		$data = json_decode($output, true);
		$this->check('Isolated lifecycle returns results', true, is_array($data));
		foreach($data as $name => $passed) $this->check($name, true, $passed);
	}
}

/** Legacy handler with the original API only, deliberately without the new callbacks. */
class WireTest_AdaptorMemoryHandler extends WireSessionHandler {
	public $data = [];
	public $reads = 0;
	public function read($id) {
		$this->reads++;
		return $this->data[$id] ?? '';
	}
	public function write($id, $data) {
		$this->data[$id] = $data;
		return true;
	}
	public function destroy($id) {
		unset($this->data[$id]);
		return true;
	}
	public function gc($seconds) { return 0; }
}
