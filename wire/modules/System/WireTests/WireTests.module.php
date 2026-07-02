<?php namespace ProcessWire;

/**
 * ProcessWire Tests
 *
 * Simple framework for running ProcessWire tests from CLI
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 * @property int $testTemplateId
 * @property int $testPageId
 *
 */
class WireTests extends WireData implements Module, ConfigurableModule, CliModule {

	const pageName = 'wire-test';
	const templateName = 'wire-test';
	const fieldPrefix = 'wire_test_';

	/**
	 * Current test name
	 *
	 * @var string
	 *
	 */
	protected $testName = '';

	/**
	 * The /test/ page
	 *
	 * @var Page|null
	 *
	 */
	protected $testPage = null;

	/**
	 * Was the test template created by this runtime?
	 *
	 * @var bool
	 *
	 */
	protected $testTemplateCreated = false;

	/**
	 * Path where tests are located
	 *
	 * @var string
	 *
	 */
	protected $testsPath = '';

	/**
	 * Test timer
	 *
	 * @var string|null
	 *
	 */
	protected $timer = null;

	/**
	 * Number of tests passed
	 *
	 * @var int
	 *
	 */
	protected $passed = 0;

	/**
	 * Number of tests failed
	 *
	 * @var int
	 *
	 */
	protected $failed = 0;

	/**
	 * Names of failed tests
	 *
	 * @var array
	 *
	 */
	protected $failedTestNames = [];

	/**
	 * Number of tests skipped
	 *
	 * @var int
	 *
	 */
	protected $skipped = 0;

	/**
	 * Are we in CLI mode?
	 *
	 * @var bool
	 *
	 */
	protected $cli = false;

	/**
	 * Output string for http mode
	 *
	 * @var string
	 *
	 */
	protected $out = '';

	/**
	 * Current directory
	 *
	 * @var string
	 *
	 */
	protected $cwd = '';

	/**
	 * Test file discovery cache
	 *
	 * @var array|null
	 *
	 */
	protected $testFileRecords = null;

	/**
	 * Is JSON output mode enabled?
	 *
	 * @var bool
	 *
	 */
	protected $jsonOutput = false;

	/**
	 * JSON output run timer
	 *
	 * @var float|null
	 *
	 */
	protected $jsonTimer = null;

	/**
	 * JSON output test results
	 *
	 * @var array
	 *
	 */
	protected $jsonTests = [];

	/**
	 * JSON output top-level messages
	 *
	 * @var array
	 *
	 */
	protected $jsonMessages = [];

	/**
	 * JSON output current test entry
	 *
	 * @var array|null
	 *
	 */
	protected $jsonTest = null;

	/**
	 * Construct
	 *
	 */
	public function __construct() {
		$this->testsPath = __DIR__ . '/tests/';
		$this->setArray([
			'testTemplateId' => 0,
			'testPageId' => 0,
		]);
		parent::__construct();
	}

	/**
	 * Wired to API
	 *
	 */
	public function wired() {
		parent::wired();
		wireTests($this);
		$this->cli = php_sapi_name() === 'cli';
	}

	/**
	 * Set
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return self
	 *
	 */
	public function set($key, $value) {
		if($key === 'testPageId' || $key === 'testTemplateId') $value = (int) $value;
		return parent::set($key, $value);
	}

	/**
	 * Execute (for CliModule interface)
	 *
	 * @param array $args
	 *
	 */
	public function executeCli($args) {
		$this->jsonOutput = in_array('--json', $args, true);
		if($this->jsonOutput) $args = array_values(array_diff($args, [ '--json' ]));
		if(empty($args)) {
			if($this->jsonOutput) {
				$this->resetRunState();
				$this->fail("No test specified");
				$this->summary();
			} else {
				$this->line("\nNo test specified");
			}
		} else {
			$this->runTests($args[0]);
		}
	}

	/**
	 * Initialize new test
	 *
	 * @param string $name
	 *
	 */
	public function initTest($name) {
		$this->testName = $name;
		$this->timer = Debug::timer();
		$this->line('');
		$this->line("-----------------------------------");
		$this->line("TEST: $name:");
	}

	/**
	 * Output a line of text
	 *
	 * @param string $line
	 *
	 */
	public function line($line) {
		if($this->jsonOutput) return;
		if($this->cli) {
			echo "$line\n";
		} else {
			$this->out .= "$line\n";
		}
	}

	/**
	 * Output a list item
	 *
	 * @param string $line
	 *
	 */
	public function li($line) {
		if(strpos($line, "\n")) $line = str_replace("\n", "\n  ", $line);
		$this->addJsonMessage('li', $line);
		$this->line("- $line");
	}

	/**
	 * Output an OK item
	 *
	 * @param string $line
	 *
	 */
	public function ok($line) {
		if($this->jsonOutput) {
			$this->addJsonAssertion([ 'status' => 'ok', 'label' => $line ]);
			return;
		}
		$this->li("OK: $line");
	}

	/**
	 * Output a note
	 *
	 * @param string $note
	 *
	 */
	public function note($note) {
		$this->addJsonMessage('note', $note);
		$this->line($note);
	}

	/**
	 * Indicate test success
	 *
	 * @param string $note Optional note
	 *
	 */
	public function success($note = '') {
		$this->passed++;
		$this->finishJsonTest('pass');
		$this->li("👍 SUCCESS $note " . $this->getElapsed());
	}

	/**
	 * Indicate test fail
	 *
	 * @param string $note Optional note
	 * @param \Throwable|null $exception Optional exception
	 *
	 */
	public function fail($note = '', ?\Throwable $exception = null) {
		$this->failed++;
		$this->addFailedTestName();
		if($this->jsonOutput && !$this->jsonTest) {
			$this->jsonMessages[] = [
				'type' => 'fail',
				'text' => (string) $note,
			];
		}
		$data = [ 'message' => $note ];
		if($exception) $data['exception'] = $exception;
		$this->finishJsonTest('fail', $data);
		$this->li("👎 FAIL $note " . $this->getElapsed());
	}

	/**
	 * Assert that $expectValue and $actualValue satisfy $operator, output ok() on pass or throw on fail
	 *
	 * Supported operators: ===, !==, ==, !=, <, <=, >, >=
	 * String operators (actual vs. expected): *= (contains), ^= (starts with), $= (ends with)
	 *
	 * @param string $testName
	 * @param mixed $expectValue
	 * @param mixed $actualValue
	 * @param string $operator
	 * @throws WireTestException
	 *
	 */
	public function check($testName, $expectValue, $actualValue, $operator = '===') {
		$message = '';
		if($operator === '===') {
			$ok = $expectValue === $actualValue;
		} else if($operator === '!==') {
			$ok = $expectValue !== $actualValue;
		} else if($operator === '==') {
			$ok = $expectValue == $actualValue;
		} else if($operator === '!=') {
			$ok = $expectValue != $actualValue;
		} else if($operator === '<') {
			$ok = $expectValue < $actualValue;
		} else if($operator === '<=') {
			$ok = $expectValue <= $actualValue;
		} else if($operator === '>') {
			$ok = $expectValue > $actualValue;
		} else if($operator === '>=') {
			$ok = $expectValue >= $actualValue;
		} else if($operator === '*=') {
			$ok = strpos((string) $actualValue, (string) $expectValue) !== false;
			$message = "$testName: " . var_export($actualValue, true) . " does not contain " . var_export($expectValue, true);
		} else if($operator === '^=') {
			$ok = str_starts_with((string) $actualValue, (string) $expectValue);
			$message = "$testName: " . var_export($actualValue, true) . " does not start with " . var_export($expectValue, true);
		} else if($operator === '$=') {
			$ok = str_ends_with((string) $actualValue, (string) $expectValue);
			$message = "$testName: " . var_export($actualValue, true) . " does not end with " . var_export($expectValue, true);
		} else {
			throw new WireTestException("Operator '$operator' not supported");
		}
		if(!$ok) {
			if(!$message) $message = "$testName: Expected: " . var_export($expectValue, true) . ", Received: " . var_export($actualValue, true);
			$this->addJsonAssertion([
				'status' => 'fail',
				'label' => $testName,
				'expected' => $expectValue,
				'actual' => $actualValue,
				'operator' => $operator,
				'message' => $message
			]);
			throw new WireTestException($message);
		}
		$this->ok("$testName");
	}

	/**
	 * Output summary of test(s)
	 *
	 */
	public function summary() {
		$total = $this->passed + $this->failed;
		if($this->jsonOutput) {
			$this->renderJsonSummary();
			return;
		}
		if($total < 2 && $this->failed === 0) return;
		$this->line('');
		$this->line("===================================");
		if($this->failed === 0) {
			$this->line("ALL $total TESTS PASSED 👍");
		} else {
			$this->line("RESULTS: {$this->passed} passed, {$this->failed} failed of $total tests");
			$this->line($this->getFailedTestsSummary());
		}
		$this->line("===================================");
	}

	/**
	 * Get elapsed time of last test
	 *
	 * @return string
	 *
	 */
	protected function getElapsed() {
		return $this->timer ? '(' . Debug::timer($this->timer) . 's)' : '';
	}

	/**
	 * Get elapsed seconds of last test without surrounding formatting
	 *
	 * @return string
	 *
	 */
	protected function getElapsedSeconds() {
		return $this->timer ? Debug::timer($this->timer) : '';
	}

	/**
	 * Reset test run state
	 *
	 */
	protected function resetRunState() {
		$this->passed = 0;
		$this->failed = 0;
		$this->failedTestNames = [];
		$this->skipped = 0;
		$this->jsonTests = [];
		$this->jsonMessages = [];
		$this->jsonTest = null;
		$this->jsonTimer = $this->jsonOutput ? microtime(true) : null;
	}

	/**
	 * Add current test name to failed tests list
	 *
	 */
	protected function addFailedTestName() {
		$name = '';
		if($this->jsonTest && !empty($this->jsonTest['name'])) {
			$name = $this->jsonTest['name'];
		} else if($this->testName !== 'all') {
			$name = $this->testName;
		}
		if($name === '') return;
		$this->failedTestNames[$name] = $name;
	}

	/**
	 * Get failed tests summary line
	 *
	 * @return string
	 *
	 */
	protected function getFailedTestsSummary() {
		$names = array_values($this->failedTestNames);
		if(empty($names)) return 'Failed: unknown';
		$label = count($names) === 1 ? 'FAIL' : 'Failed';
		return $label . ': ' . implode(', ', $names);
	}

	/**
	 * Start JSON result entry for current test
	 *
	 * @param array $test
	 *
	 */
	protected function startJsonTest(array $test) {
		if(!$this->jsonOutput) return;
		$this->jsonTest = [
			'name' => $test['name'],
			'file' => $this->getRelativePath($test['file'], $this->wire()->config->paths->root),
			'class' => __NAMESPACE__ . "\\$test[class]",
			'status' => 'run',
			'assertions' => [],
			'messages' => [],
		];
	}

	/**
	 * Finish JSON result entry for current test
	 *
	 * @param string $status
	 * @param array $data
	 *
	 */
	protected function finishJsonTest($status, array $data = []) {
		if(!$this->jsonOutput || !$this->jsonTest) return;
		if($this->jsonTest['status'] !== 'run') {
			if(!empty($data['message'])) $this->addJsonMessage($status, $data['message']);
			return;
		}
		$this->jsonTest['status'] = $status;
		if($status !== 'skip') $this->jsonTest['elapsed'] = $this->getElapsedSeconds();
		foreach($data as $key => $value) {
			if($key === 'exception' && $value instanceof \Throwable) {
				$this->jsonTest[$key] = [
					'class' => get_class($value),
					'message' => $value->getMessage(),
					'code' => $value->getCode(),
				];
			} else {
				$this->jsonTest[$key] = $this->normalizeJsonValue($value);
			}
		}
		if(empty($this->jsonTest['assertions'])) unset($this->jsonTest['assertions']);
		if(empty($this->jsonTest['messages'])) unset($this->jsonTest['messages']);
		$this->jsonTests[] = $this->jsonTest;
		$this->jsonTest = null;
	}

	/**
	 * Add skipped JSON result entry
	 *
	 * @param array $test
	 * @param string $reason
	 *
	 */
	protected function skipJsonTest(array $test, $reason) {
		$this->skipped++;
		if(!$this->jsonOutput) return;
		$this->startJsonTest($test);
		$this->finishJsonTest('skip', [ 'reason' => $reason ]);
	}

	/**
	 * Add JSON assertion to current test
	 *
	 * @param array $assertion
	 *
	 */
	protected function addJsonAssertion(array $assertion) {
		if(!$this->jsonOutput || !$this->jsonTest) return;
		foreach($assertion as $key => $value) {
			$assertion[$key] = $this->normalizeJsonValue($value);
		}
		$this->jsonTest['assertions'][] = $assertion;
	}

	/**
	 * Add JSON diagnostic message to current test
	 *
	 * @param string $type
	 * @param string $text
	 *
	 */
	protected function addJsonMessage($type, $text) {
		if(!$this->jsonOutput || !$this->jsonTest) return;
		$this->jsonTest['messages'][] = [
			'type' => $type,
			'text' => (string) $text,
		];
	}

	/**
	 * Normalize value for JSON output
	 *
	 * @param mixed $value
	 * @return mixed
	 *
	 */
	protected function normalizeJsonValue($value) {
		if($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) return $value;
		if(is_array($value)) {
			foreach($value as $key => $item) $value[$key] = $this->normalizeJsonValue($item);
			return $value;
		}
		if(is_object($value)) {
			return [
				'type' => 'object',
				'class' => get_class($value),
				'string' => method_exists($value, '__toString') ? (string) $value : ''
			];
		}
		if(is_resource($value)) {
			return [
				'type' => 'resource',
				'resourceType' => get_resource_type($value)
			];
		}
		return (string) $value;
	}

	/**
	 * Render JSON summary
	 *
	 */
	protected function renderJsonSummary() {
		$total = $this->passed + $this->failed + $this->skipped;
		$out = [
			'status' => $this->failed ? 'fail' : 'pass',
			'passed' => $this->passed,
			'failed' => $this->failed,
			'skipped' => $this->skipped,
			'total' => $total,
			'elapsed' => $this->jsonTimer ? number_format(microtime(true) - $this->jsonTimer, 4, '.', '') : '',
			'tests' => $this->jsonTests,
		];
		if(count($this->jsonMessages)) $out['messages'] = $this->jsonMessages;
		echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
		if($this->cli && $this->failed) exit(1);
	}

	/**
	 * Run tests
	 *
	 * @param string $name Test name or omit for 'all'
	 *
	 */
	public function runTests($name = 'all') {
		if($this->cli) {
			error_reporting(E_ALL);
			ini_set('display_errors', $this->jsonOutput ? 0 : 1);
		}

		$this->resetRunState();
		$this->testName = $name;
		$this->cwd = getcwd();
		$testFiles = $this->getTestFileRecords($name);
		$numTests = count($testFiles);

		if(!count($testFiles)) {
			if($this->failed === 0) $this->fail("No tests to run");
			$this->summary();
			return;
		}

		$fuel = $this->wire()->fuel->getArray();
		extract($fuel); // place API variables in scope

		$this->line("Running $numTests test(s)");

		foreach($testFiles as $testName => $test) {

			$page = $this->getTestPage(); // get again just in case a test overwrote it
			$page->of(false); // reset output formatting before each test

			$className = $test['name'];
			if(!$modules->isInstalled($className)) {
				// Also allow tests for core classes (e.g. Sanitizer) that aren't installable modules
				$coreClass = __NAMESPACE__ . "\\$className";
				if(!class_exists($coreClass) && !class_exists($className)) {
					$this->skipJsonTest($test, 'Not installed');
					$this->line("Skipping '$className' - not installed");
					continue;
				}
			} else {
				if(!$this->wire()->modules->isInstalled($className)) {
					$this->skipJsonTest($test, 'Not available');
					$this->line("Skipping '$className' - not available");
					continue;
				}
			}

			$testInstance = null;
			$success = false;

			try {
				$testFile = $test['file'];
				$wireTestClassName = __NAMESPACE__ . "\\$test[class]";
				$this->startJsonTest($test);
				$this->initTest($className);
				chdir($test['path']);
				include($testFile);
				if(class_exists($wireTestClassName)) {
					/** @var WireTest $testInstance */
					$testInstance = new $wireTestClassName($this);
					if(!$testInstance->allow()) {
						$this->skipped++;
						$this->finishJsonTest('skip', [ 'reason' => 'Not supported' ]);
						$this->line("Skipping '$className' - not supported");
						continue;
					}
					$testInstance->init();
					$testInstance->execute();
					$success = true;
				}
				$success = true;

			} catch(WireTestException $e) {
				$this->fail($e->getMessage(), $e);

			} catch(\Throwable $t) {
				$this->fail($t->getMessage(), $t);

			} finally {
				if($testInstance) {
					try {
						$testInstance->finish();
					} catch(\Throwable $e) {
						$this->fail("Failed to finish/cleanup: " . $e->getMessage(), $e);
						$success = false;
					}
				}
			}

			if($success) $this->success();
		}

		$this->summary();

		if($this->cwd) chdir($this->cwd);
	}

	/**
	 * Get available commands (for CliModule interface)
	 *
	 * @return array
	 *
	 */
	public function getCliCommands() {
		$commands = [ 'all' => 'Run all tests' ];
		$names = [];
		foreach($this->getTestFileRecords() as $record) {
			$name = $record['name'];
			if(isset($names[$name])) {
				$commands[$record['key']] = "Test $name in " . $this->getRelativePath($record['file'], $this->wire()->config->paths->root);
			} else {
				$commands[$name] = "Test $name";
				$names[$name] = true;
			}
		}
		ksort($commands);
		$commands['/path/to/myfile.php'] = "Run custom test in /path/to/myfile.php";
		$commands['dir/to/myfile.php'] = "Run custom test file (relative to installation root)";
		return $commands;
	}

	/**
	 * Get path where tests are located
	 *
	 * @return string
	 *
	 */
	public function getTestsPath() {
		return $this->testsPath;
	}

	/**
	 * Set the path where tests are located
	 *
	 * @param string $path
	 *
	 */
	public function setTestsPath($path) {
		$this->testsPath = $path;
		$this->testFileRecords = null;
	}

	/**
	 * Get all test files in given path
	 *
	 * @param string $path optional path if something other than the default
	 * @return array
	 *
	 */
	public function getTestFilesFromPath($path = '') {
		$records = $this->getTestFileRecordsFromPath($path);
		$tests = [];
		foreach($records as $record) {
			$name = isset($tests[$record['name']]) ? $record['key'] : $record['name'];
			$tests[$name] = $record['file'];
		}
		return $tests;
	}

	/**
	 * Get test file records corresponding to given name/path/scope
	 *
	 * @param string $name Name of test, path, path+name, directory, or all
	 * @return array
	 *
	 */
	protected function getTestFileRecords($name = 'all') {
		$root = $this->wire()->config->paths->root;
		$name = trim((string) $name);
		if($name === '') $name = 'all';

		if($name === 'all') return $this->discoverTestFiles();

		$path = $this->resolveTestPath($name);
		if($path && is_file($path)) {
			$record = $this->getTestFileRecord($path, true, true);
			if(!$record) $this->fail("Test file does not contain a WireTest class: $path");
			return $record ? [ $record['key'] => $record ] : [];
		}

		if($path && is_dir($path)) return $this->getTestFileRecordsFromPath($path, true);

		if(strpos($name, '/') !== false || strpos($name, DIRECTORY_SEPARATOR) !== false) {
			$this->fail("Test path not found: $name");
			return [];
		}

		$matches = [];
		foreach($this->discoverTestFiles() as $key => $record) {
			if($record['name'] === $name) $matches[$key] = $record;
		}

		if(count($matches) > 1) {
			$files = array_map(function($record) use ($root) {
				return $this->getRelativePath($record['file'], $root);
			}, $matches);
			$this->fail("Test name '$name' is ambiguous, specify path: " . implode(', ', $files));
			return [];
		}

		if(empty($matches)) {
			$this->fail("Test not found: $name");
			return [];
		}

		return $matches;
	}

	/**
	 * Discover all test files from configured roots
	 *
	 * @return array
	 *
	 */
	protected function discoverTestFiles() {
		if($this->testFileRecords !== null) return $this->testFileRecords;

		$records = [];
		foreach($this->getTestDiscoveryPaths() as $path) {
			foreach($this->getTestFileRecordsFromPath($path, true) as $key => $record) {
				$records[$key] = $record;
			}
		}
		ksort($records);
		$this->testFileRecords = $records;
		return $records;
	}

	/**
	 * Get paths searched for tests
	 *
	 * @return array
	 *
	 */
	protected function getTestDiscoveryPaths() {
		$config = $this->wire()->config;
		return [
			$this->getTestsPath(),
			$config->paths->root . 'site/',
			$config->paths->root . 'wire/core/',
			$config->paths->root . 'wire/modules/',
		];
	}

	/**
	 * Get all test file records in given path
	 *
	 * @param string $path
	 * @param bool $recursive
	 * @return array
	 *
	 */
	protected function getTestFileRecordsFromPath($path = '', $recursive = false) {
		if(empty($path)) $path = $this->getTestsPath();
		$path = rtrim($path, '/') . '/';
		if(!is_dir($path)) return [];

		$tests = [];
		$flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS;
		$seenDirs = [];
		$realpath = realpath($path);
		if($realpath !== false) $seenDirs[rtrim(str_replace('\\', '/', $realpath), '/')] = true;

		$iterator = $recursive ?
			new \RecursiveIteratorIterator(
				new \RecursiveCallbackFilterIterator(
					new \RecursiveDirectoryIterator($path, $flags),
					function($file) use (&$seenDirs) {
						if(!$file->isDir()) return true;

						$realpath = $file->getRealPath();
						if($realpath === false) return false;

						$realpath = rtrim(str_replace('\\', '/', $realpath), '/');
						if(isset($seenDirs[$realpath])) return false;
						$seenDirs[$realpath] = true;

						return !$this->isExcludedTestPath($file->getPathname());
					}
				)
			) :
			new \IteratorIterator(new \DirectoryIterator($path));

		foreach($iterator as $file) {
			if(!$file->isFile() || $file->getExtension() !== 'php') continue;
			if($this->isExcludedTestPath($file->getPathname())) continue;
			$allowLegacy = $this->isLegacyTestsPath($file->getPathname(), true);
			$record = $this->getTestFileRecord($file->getPathname(), false, $allowLegacy);
			if(!$record) continue;
			$tests[$record['key']] = $record;
		}

		ksort($tests);
		return $tests;
	}

	/**
	 * Get test file record for given file
	 *
	 * @param string $file
	 * @param bool $allowAnyWireTestClass
	 * @param bool $allowLegacyFlatFile
	 * @return array|null
	 *
	 */
	protected function getTestFileRecord($file, $allowAnyWireTestClass = false, $allowLegacyFlatFile = false) {
		if(!is_file($file)) return null;

		$basename = basename($file, '.php');
		$isTestFile = substr($basename, -5) === '.test' || strpos($basename, 'WireTest_') === 0;
		if(!$isTestFile && !$allowLegacyFlatFile) return null;

		$name = substr($basename, -5) === '.test' ? substr($basename, 0, -5) : $basename;
		if(strpos($name, 'WireTest_') === 0) $name = substr($name, 9);
		if($name === '') return null;

		$class = "WireTest_$name";
		$contents = file_get_contents($file);
		if($contents === false) return null;

		if(!preg_match('/\bclass\s+' . preg_quote($class, '/') . '\b/', $contents)) {
			if(!$allowAnyWireTestClass && !$allowLegacyFlatFile) return null;
			if(preg_match('/\bclass\s+(WireTest_[A-Za-z0-9_]+)\b/', $contents, $matches)) {
				$class = $matches[1];
				$name = substr($class, 9);
			} else if(!$allowLegacyFlatFile) {
				return null;
			}
		}

		$file = str_replace('\\', '/', $file);

		return [
			'name' => $name,
			'class' => $class,
			'file' => $file,
			'path' => dirname($file) . '/',
			'key' => $this->getTestRecordKey($name, $file),
		];
	}

	/**
	 * Return a unique key for a test record
	 *
	 * @param string $name
	 * @param string $file
	 * @return string
	 *
	 */
	protected function getTestRecordKey($name, $file) {
		$root = $this->wire()->config->paths->root;
		$relative = $this->getRelativePath($file, $root);
		return "$name:$relative";
	}

	/**
	 * Resolve CLI test path to absolute path
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	protected function resolveTestPath($name) {
		if(strpos($name, '/') === 0) return $name;
		if(strpos($name, '/') !== false || strpos($name, DIRECTORY_SEPARATOR) !== false) {
			return $this->wire()->config->paths->root . ltrim($name, '/');
		}
		return '';
	}

	/**
	 * Get excluded test path patterns
	 *
	 * @return array
	 *
	 */
	protected function getTestPathExclusions() {
		return [
			'site/assets/',
			'site/templates/styles/',
			'site/templates/scripts/',
			'*/vendor/',
			'*/.*',
			'*.old/',
			'wire/modules/AdminTheme/AdminThemeDefault/',
			'wire/modules/AdminTheme/AdminThemeReno/',
			'wire/modules/AdminTheme/AdminThemeUikit/*/',
			'wire/modules/Inputfield/InputfieldCKEditor/*/',
			'wire/modules/Inputfield/InputfieldTinyMCE/*/',
			'wire/modules/Jquery/JqueryCore/',
			'wire/modules/Jquery/JqueryMagnific/',
			'wire/modules/Jquery/JqueryTableSorter/',
			'wire/modules/Jquery/JqueryUI/',
			'wire/modules/Markup/MarkupHTMLPurifier/htmlpurifier/',
			'wire/modules/Textformatter/TextformatterMarkdownExtra/*/',
			'wire/modules/Textformatter/TextformatterSmartypants/*/',
			'wire/templates-admin/',
			'site/modules/*/*/',
		];
	}

	/**
	 * Is given path excluded from test discovery?
	 *
	 * @param string $path
	 * @return bool
	 *
	 */
	protected function isExcludedTestPath($path) {
		$root = $this->wire()->config->paths->root;
		$relative = $this->getRelativePath($path, $root);
		if($this->isLegacyTestsPath($path)) return false;
		if($this->isHiddenPath($relative)) return true;
		if($this->isOldPath($relative)) return true;
		$isDir = is_dir($path);
		foreach($this->getTestPathExclusions() as $pattern) {
			if($this->matchesTestPathExclusion($pattern, $relative, $isDir)) return true;
		}
		if(strpos($relative, 'site/') === 0 || strpos($relative, 'wire/') === 0) {
			foreach(explode('/', trim($relative, '/')) as $segment) {
				if(preg_match('/-\d+\.\d+(?:\.\d+)?(?:[-._a-z0-9]*)?$/i', $segment)) return true;
			}
		}
		return false;
	}

	/**
	 * Does relative path match an exclusion pattern?
	 *
	 * Directory patterns ending in "/" exclude matching directories recursively.
	 * Patterns with slash-star-slash suffix exclude matching direct child directories recursively.
	 *
	 * @param string $pattern
	 * @param string $relative
	 * @param bool $isDir
	 * @return bool
	 *
	 */
	protected function matchesTestPathExclusion($pattern, $relative, $isDir = false) {
		$pattern = trim(str_replace('\\', '/', $pattern));
		$relative = ltrim(str_replace('\\', '/', $relative), '/');

		if($pattern === '') return false;

		if(substr($pattern, -3) === '/*/') {
			$basePattern = substr($pattern, 0, -3);
			$segmentPaths = $this->getRelativePathSegments($relative);
			$numSegmentPaths = count($segmentPaths);
			foreach($segmentPaths as $n => $segmentPath) {
				if(!$isDir && $n === $numSegmentPaths - 1) continue;
				if(strpos($segmentPath, '/') === false) continue;
				$parent = dirname($segmentPath);
				if($parent === '.') $parent = '';
				if(fnmatch($basePattern, $parent)) return true;
			}
			return false;
		}

		if(substr($pattern, -1) === '/') {
			$dirPattern = rtrim($pattern, '/');
			foreach($this->getRelativePathSegments($relative) as $segmentPath) {
				if(fnmatch($dirPattern, $segmentPath)) return true;
			}
			return false;
		}

		return fnmatch($pattern, $relative);
	}

	/**
	 * Is given relative path hidden by any dot-prefixed segment?
	 *
	 * @param string $relative
	 * @return bool
	 *
	 */
	protected function isHiddenPath($relative) {
		foreach(explode('/', trim($relative, '/')) as $segment) {
			if($segment !== '' && strpos($segment, '.') === 0) return true;
		}
		return false;
	}

	/**
	 * Is given relative path in a .old path or itself a .old file/directory?
	 *
	 * @param string $relative
	 * @return bool
	 *
	 */
	protected function isOldPath($relative) {
		foreach(explode('/', trim($relative, '/')) as $segment) {
			if(substr($segment, -4) === '.old') return true;
		}
		return false;
	}

	/**
	 * Get cumulative relative path segments
	 *
	 * @param string $relative
	 * @return array
	 *
	 */
	protected function getRelativePathSegments($relative) {
		$segments = [];
		$path = '';
		foreach(explode('/', trim($relative, '/')) as $segment) {
			if($segment === '') continue;
			$path = $path === '' ? $segment : "$path/$segment";
			$segments[] = $path;
		}
		return $segments;
	}

	/**
	 * Is given path in the bundled legacy tests path?
	 *
	 * @param string $path
	 * @return bool
	 *
	 */
	protected function isLegacyTestsPath($path, $directChildOnly = false) {
		$path = str_replace('\\', '/', $path);
		$testsPath = rtrim(str_replace('\\', '/', $this->getTestsPath()), '/') . '/';
		if(strpos($path, $testsPath) === 0) {
			return !$directChildOnly || dirname($path) . '/' === $testsPath;
		}
		$realPath = realpath($path);
		if(!$realPath) return false;
		$realPath = str_replace('\\', '/', $realPath);
		$realTestsPath = realpath($testsPath);
		if(!$realTestsPath) return false;
		$realTestsPath = rtrim(str_replace('\\', '/', $realTestsPath), '/') . '/';
		if(strpos($realPath, $realTestsPath) !== 0) return false;
		return !$directChildOnly || dirname($realPath) . '/' === $realTestsPath;
	}

	/**
	 * Get path relative to root
	 *
	 * @param string $path
	 * @param string $root
	 * @return string
	 *
	 */
	protected function getRelativePath($path, $root) {
		$path = str_replace('\\', '/', $path);
		$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
		if(strpos($path, $root) === 0) return substr($path, strlen($root));
		return ltrim($path, '/');
	}

	/**
	 * Get all WireTest class instances
	 *
	 * @param string $path
	 * @return array
	 *
	 */
	public function getWireTestInstances($path = '') {
		$testFiles = $this->getTestFilesFromPath($path);
		$instances = [];
		foreach($testFiles as $basename => $testFile) {
			$s = file_get_contents($testFile);
			if(stripos($s, "WireTest_$basename") === false) continue;
			include_once($testFile);
			$class = __NAMESPACE__ . "\\WireTest_$basename";
			/** @var WireTest $instance */
			$instance = new $class($this);
			if(!$instance->allow()) continue;
			$instances[$basename] = $instance;
		}
		return $instances;
	}

	/**
	 * Get (or create) the template file used by the test page
	 *
	 * @param bool $create Create it if it doesn't exist?
	 * @return Template|false
	 *
	 */
	protected function getTestTemplate($create = true) {
		$name = self::templateName;
		$fields = $this->wire()->fields;
		$templates = $this->wire()->templates;
		$template = $templates->get($name);
		if(!$template) {
			if(!$create) return false;
			$template = $templates->new($name);
			$template->noParents = -1; // only allow 1 page of this type
			$template->save();
			$this->testTemplateCreated = true;
		} else if(!$template->fieldgroup) {
			$template->save();
		}
		$field = $fields->get('title');
		if(!$template->hasField($field)) {
			$template->fieldgroup->add($fields->get('title'));
			$template->fieldgroup->save();
		}
		return $template;
	}

	/**
	 * Get the /test/ page
	 *
	 * @param bool $create Create it if it doesn't exist?
	 * @return Page|false
	 *
	 */
	public function getTestPage($create = true) {
		if($this->testPage) return $this->testPage;
		$pages = $this->wire()->pages;
		$template = $this->getTestTemplate($create);
		if(!$template) return false;
		$page = $pages->get('name=' . self::pageName . ', template=' . self::templateName);
		if($page->id) {
			if($page->isUnpublished()) {
				$page->removeStatus('unpublished');
				$page->save();
			}
			$this->testPage = $page;
			return $page;
		}
		if(!$create) return false;
		if(!$page->id) {
			$page = $pages->new([
				'template' => $template,
				'parent' => '/',
				'status' => 'hidden',
				'name' => self::pageName,
				'title' => 'ProcessWire Tests'
			]);
			$configData = [
				'testPageId' => $page->id,
				'testTemplateId' => $this->testTemplateId,
			];
			if($this->testTemplateCreated) $configData['testTemplateId'] = $template->id;
			$this->wire()->modules->saveConfig($this, $configData);
		}
		$this->testPage = $page;
		return $page;
	}

	/**
	 * Install module
	 *
	 */
	public function install() {

		$testTemplate = $this->getTestTemplate(false);
		if($testTemplate && $testTemplate->id != $this->testTemplateId) {
			throw new WireException("Template $testTemplate->name exists, please remove it to install");
		}

		$testPage = $this->getTestPage(false);
		if($testPage && $testPage->id && $testPage->id != $this->testPageId) {
			throw new WireException("Page $testPage->path exists, please remove it to install");
		}
	
		$fieldNames = [];
		foreach($this->wire()->fields as $field) {
			if(strpos($field->name, self::fieldPrefix) === 0) {
				$fieldNames[] = $field->name;
			}
		}
		if(count($fieldNames)) {
			$fieldNames = implode(', ', $fieldNames);
			throw new WireException("Please delete these fields before installing: $fieldNames"); 
		}

		$testPage = $this->getTestPage();
		if(!$testPage->id) {
			throw new WireException("Unable to create test page");
		}
		$testTemplate = $this->getTestTemplate(false);

		$this->wire()->modules->saveConfig($this, [
			'testPageId' => $testPage->id,
			'testTemplateId' => $testTemplate->id,
		]);
	}

	/**
	 * Uninstall module
	 *
	 */
	public function uninstall() {
		foreach($this->getWireTestInstances() as $wireTest) {
			try {
				$wireTest->uninstall();
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		}
		$page = $this->getTestPage(false);
		if($page && $page->id && $page->id === $this->testPageId) {
			if($this->wire()->pages->delete($page)) {
				$this->message("Deleted test page: $page->name");
			}
		}
		$template = $this->getTestTemplate(false);
		$fieldgroup = $template && $template->id === $this->testTemplateId ? $template->fieldgroup : false;
		if($template && $template->id === $this->testTemplateId) {
			if($this->wire()->templates->delete($template)) {
				$this->message("Deleted template: $template->name");
			}
		}
		if($fieldgroup) {
			if($this->wire()->fieldgroups->delete($fieldgroup)) {
				$this->message("Deleted fieldgroup: $fieldgroup->name");
			}
		}
	
		$fields = $this->wire()->fields;
		foreach($fields as $field) {
			if($field->name === 'wire_test_headline') continue;
			if(strpos($field->name, self::fieldPrefix) === 0) {
				try {
					if($fields->delete($field)) $this->message("Deleted field: $field->name");
				} catch(\Exception $e) {
					$this->error($e->getMessage());
				}
			}
		}  
		$field = $fields->get('wire_test_headline');
		try {
			if($field && $fields->delete($field)) {
				$this->message("Deleted field: $field->name");
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
	}

	/**
	 * Module config
	 *
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		$sanitizer = $this->wire()->sanitizer;
		
		$f = $inputfields->InputfieldMarkup; 
		$f->attr('name', '_test_info'); 
		$f->skipLabel(Inputfield::skipLabelHeader);
		$markdown = file_get_contents(__DIR__ . '/README.md');
		$f->value = $sanitizer->entitiesMarkdown($markdown, [ 'fullMarkdown' => true ]);
		$f->value = str_replace('<table>', '<table class="uk-table uk-table-small uk-table-divider">', $f->value);
		$inputfields->add($f);
		
		/*
		$input = $this->wire()->input;
		$session = $this->wire()->session;
		$tests = array_keys($this->getTestFilesFromPath());
		$lastTestName = $session->getFor($this, 'testName');

		$f = $inputfields->InputfieldSelect;
		$f->attr('name', '_test_name');
		$f->label = 'Select test to run';
		$f->addOption('all', 'All');
		$f->description = "You can also use from CLI:\n`php index.php test ModuleName`";
		foreach($tests as $test) $f->addOption($test, $test);
		if($lastTestName) $f->val($lastTestName);
		$inputfields->add($f);

		$testName = $input->post('_test_name');
		$results = $session->getFor($this, 'results');

		if($testName && ($testName === 'all' || in_array($testName, $tests, true))) {
			$this->runTests($testName);
			$session->setFor($this, 'results', $this->out);
			$session->setFor($this, 'testName', $testName);
		} else if($results) {
			$f = $inputfields->InputfieldMarkup;
			$f->attr('name', '_test_results');
			$f->label = 'Test results';
			$f->val('<pre>' . htmlspecialchars(ltrim($results, "\n-")) . '</pre>');
			$inputfields->add($f);
		}
		*/
	}
}

/**
 * Get the WireTests module instance
 *
 * @param WireTests|null $wireTests Specify to set WireTests instance
 * @return WireTests
 *
 */
function wireTests(?WireTests $wireTests = null) {
	static $module = null;
	if($wireTests !== null) $module = $wireTests;
	if(!$module) $module = wire()->modules->get('WireTests');
	return $module;
}

/**
 * Exception thrown by tests on failed test
 *
 */
class WireTestException extends WireException { }

include_once(__DIR__ . '/WireTest.php');
