<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireTempDir
 *
 */
class WireTest_WireTempDir extends WireTest {

	/**
	 * @var WireTempDir[]
	 *
	 */
	protected $tempDirs = array();

	public function execute() {
		$this->testCreateNameAndInit();
		$this->testGetAndRemove();
		$this->testNamedDirectoryCollision();
		$this->testStringConversionAndMaintenance();
	}

	public function finish() {
		foreach($this->tempDirs as $tempDir) {
			$tempDir->remove();
		}
		$this->tempDirs = array();
	}

	/**
	 * Test createName() and init().
	 *
	 */
	protected function testCreateNameAndInit() {
		$tempDir = $this->newTempDir();
		$name = $tempDir->createName('wiretest');

		$this->check('createName() includes requested prefix', 'wiretest', substr($name, 0, 8));
		$this->check('createName() returns non-empty unique-ish name', true, strlen($name) > 20);

		$root = $tempDir->init('WireTempDirTest');
		$this->check('init() returns class root plus named root', true, strpos($root, '/WireTempDir/.WireTempDirTest/') !== false);

		try {
			$tempDir->init('WireTempDirTestAgain');
			$this->fail('init() should throw when called twice');
		} catch(WireException $e) {
			$this->ok('init() throws when called twice');
		}
	}

	/**
	 * Test get() and remove().
	 *
	 */
	protected function testGetAndRemove() {
		$name = 'WireTempDirTest' . $this->wire()->datetime->date('YmdHis') . mt_rand(1000, 9999);
		$tempDir = $this->newTempDir($name);
		$path = $tempDir->get('alpha');

		$this->check('get(id) creates directory', true, is_dir($path));
		$this->check('get(id) includes requested id', true, strpos($path, '/alpha/') !== false);
		$this->check('get(id) creates marker file', true, is_file($path . WireTempDir::hiddenFileName));
		$this->check('get(id) returns cached path on repeat call', $path, $tempDir->get('beta'));

		$this->wire()->files->filePutContents($path . 'example.txt', 'hello');
		$this->check('test file was written into temp dir', true, is_file($path . 'example.txt'));
		$this->check('remove() reports success', true, $tempDir->remove());
		$this->check('remove() removes created temp dir', false, is_dir($path));
	}

	/**
	 * Test named roots and collision handling.
	 *
	 */
	protected function testNamedDirectoryCollision() {
		$name = 'WireTempDirCollision' . $this->wire()->datetime->date('YmdHis') . mt_rand(1000, 9999);
		$first = $this->newTempDir($name);
		$second = $this->newTempDir($name);

		$firstPath = $first->get('same');
		$secondPath = $second->get('same');

		$this->check('same named root first path exists', true, is_dir($firstPath));
		$this->check('same named root second path exists', true, is_dir($secondPath));
		$this->check('same id collision creates different path', true, $firstPath !== $secondPath);
		$this->check('collision path gets numeric suffix', true, strpos($secondPath, '/same-1/') !== false);
	}

	/**
	 * Test __toString(), setMaxAge(), setRemove() and maintenance().
	 *
	 */
	protected function testStringConversionAndMaintenance() {
		$tempDir = $this->newTempDir();
		$this->check('setMaxAge() is chainable', $tempDir, $tempDir->setMaxAge(60, 3600));
		$this->check('setRemove() is chainable', $tempDir, $tempDir->setRemove(false));

		$path = (string) $tempDir;
		$this->check('__toString() creates and returns temp dir path', true, is_dir($path));
		$this->check('__toString() path has marker file', true, is_file($path . WireTempDir::hiddenFileName));
		$this->check('maintenance() returns boolean', true, is_bool($tempDir->maintenance()));
	}

	/**
	 * Create tracked temp dir instance.
	 *
	 * @param string $name
	 * @return WireTempDir
	 *
	 */
	protected function newTempDir($name = '') {
		$tempDir = $this->wire(new WireTempDir($name));
		$tempDir->setRemove(false);
		$this->tempDirs[] = $tempDir;
		return $tempDir;
	}
}
