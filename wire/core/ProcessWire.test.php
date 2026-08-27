<?php namespace ProcessWire;

/**
 * Tests for ProcessWire runtime class
 *
 */
class WireTest_ProcessWire extends WireTest {

	protected $path = '';
	protected $sourceFile = '';
	protected $templateCompile = false;

	public function init() {
		$config = $this->wire()->config;
		$this->path = $config->paths->cache . 'WireTests/ProcessWire/';
		$this->sourceFile = $this->path . 'compiled-cwd.php';
		$this->templateCompile = $config->templateCompile;
		$this->cleanupFiles();
		if(!is_dir($this->path)) $this->wire()->files->mkdir($this->path, true);
	}

	public function execute() {
		$config = $this->wire()->config;
		$files = $this->wire()->files;
		$cwd = getcwd();
		$output = '';
		$included = false;
		$obLevel = ob_get_level();

		$files->filePutContents($this->sourceFile, '<?php $probe = new WireData(); echo getcwd();');
		$config->templateCompile = true;
		$compiledFile = $files->compile($this->sourceFile, array('skipIfNamespace' => true));
		$this->check('includeFile() test fixture is compiled', false, $compiledFile === $this->sourceFile);

		$method = new \ReflectionMethod($this->wire(), 'includeFile');
		if(PHP_VERSION_ID < 80100) $method->setAccessible(true);
		try {
			ob_start();
			$included = $method->invoke($this->wire(), $this->sourceFile);
			$output = ob_get_clean();
		} finally {
			while(ob_get_level() > $obLevel) ob_end_clean();
			$config->templateCompile = $this->templateCompile;
		}

		$this->check('includeFile() includes compiled source file', true, $included);
		$this->check('compiled source executes from original file directory', rtrim($this->path, '/'), $output);
		$this->check('includeFile() restores cwd after compiled source', $cwd, getcwd());
	}

	public function finish() {
		$this->wire()->config->templateCompile = $this->templateCompile;
		$this->cleanupFiles();
	}

	protected function cleanupFiles() {
		if(!$this->path) return;
		$compiler = $this->wire(new FileCompiler($this->path, array('skipIfNamespace' => true)));
		$this->wire()->cache->deleteFor($compiler, md5($this->sourceFile));
		$compiler->clearCache();
		if(is_dir($this->path)) $this->wire()->files->rmdir($this->path, true);
	}
}
