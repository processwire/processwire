<?php namespace ProcessWire;

/**
 * Tests for ProcessWire TemplateFile
 *
 */
class WireTest_TemplateFile extends WireTest {

	protected $path = '';
	protected $files = array();
	protected $fileFailedHookID = '';

	public function init() {
		$this->path = $this->wire()->config->paths->cache . 'WireTests/TemplateFile/';
		$this->cleanupFiles();
		if(!is_dir($this->path)) $this->wire()->files->mkdir($this->path, true);
	}

	public function execute() {
		$this->check('TemplateFile class exists', true, class_exists('ProcessWire\\TemplateFile'));

		$this->testBasicRender();
		$this->testFalseyValues();
		$this->testPrependAppend();
		$this->testTrimAndReturnValue();
		$this->testHalt();
		$this->testMissingFiles();
		$this->testChdir();
		$this->testRenderStack();
		$this->testFileFailedHook();
		$this->testToStringAndProperties();
	}

	public function finish() {
		$this->removeFileFailedHook();
		$this->cleanupFiles();
	}

	protected function testBasicRender() {
		$file = $this->createFile('basic.php', "<?php echo \"Hello \$headline\";");
		$t = $this->templateFile($file);
		$t->set('headline', 'TemplateFile');

		$this->check('render() returns captured output', 'Hello TemplateFile', $t->render());
		$this->check('set() variable remains readable', 'TemplateFile', $t->get('headline'));
		$this->check('filename property returns primary file', $file, $t->get('filename'));
	}

	protected function testFalseyValues() {
		$t = $this->templateFile();
		$t->set('zero', 0);
		$t->set('false', false);
		$t->set('empty', '');

		$this->check('get() preserves integer zero', 0, $t->get('zero'));
		$this->check('get() preserves boolean false', false, $t->get('false'));
		$this->check('get() preserves empty string', '', $t->get('empty'));
		$this->check('get() returns null for missing value', null, $t->get('missing'));
	}

	protected function testPrependAppend() {
		$prepend = $this->createFile('prepend.php', "<?php echo 'prepend:' . \$value . '|';");
		$main = $this->createFile('main.php', "<?php echo 'main:' . \$value . '|';");
		$append = $this->createFile('append.php', "<?php echo 'append:' . \$value;");
		$t = $this->templateFile($main);

		$this->check('setPrependFilename() accepts existing file', true, $t->setPrependFilename($prepend));
		$this->check('setAppendFilename() accepts existing file', true, $t->setAppendFilename($append));
		$this->check('prependFilename property contains file', $prepend, $t->get('prependFilename')[0]);
		$this->check('appendFilename property contains file', $append, $t->get('appendFilename')[0]);

		$t->set('value', 'ok');
		$this->check('render() includes prepend, main and append in order', 'prepend:ok|main:ok|append:ok', $t->render());
	}

	protected function testTrimAndReturnValue() {
		$spaces = $this->createFile('spaces.php', "<?php echo \"  padded  \";");
		$t = $this->templateFile($spaces);
		$this->check('render() trims output by default', 'padded', $t->render());

		$t = $this->templateFile($spaces);
		$t->setTrim(false);
		$this->check('setTrim(false) preserves whitespace', '  padded  ', $t->render());
		$this->check('trim property reflects setTrim(false)', false, $t->get('trim'));

		$returnArray = $this->createFile('return-array.php', "<?php return array('ok' => true, 'n' => 2);");
		$this->check('render() returns array when file returns array without output', array('ok' => true, 'n' => 2), $this->templateFile($returnArray)->render());

		$returnString = $this->createFile('return-string.php', "<?php return 'returned string';");
		$this->check('render() returns non-empty return value without output', 'returned string', $this->templateFile($returnString)->render());
	}

	protected function testHalt() {
		$prepend = $this->createFile('halt-prepend.php', "<?php return \$this->halt('halted');");
		$main = $this->createFile('halt-main.php', "<?php echo 'main';");
		$append = $this->createFile('halt-append.php', "<?php echo 'append';");
		$t = $this->templateFile($main);
		$t->setPrependFilename($prepend);
		$t->setAppendFilename($append);

		$this->check('halt() in prepend stops main and append files', 'halted', $t->render());
		$this->check('halt property is true after halt()', true, $t->get('halt'));

		$t = $this->templateFile($main);
		$t->halt(true);
		$this->check('external halt skips main file', '', $t->render());
	}

	protected function testMissingFiles() {
		$missing = $this->path . 'missing.php';

		$t = $this->templateFile();
		$t->setThrowExceptions(false);
		$this->check('setFilename(missing) returns false when exceptions disabled', false, $t->setFilename($missing));
		$this->check('render() returns blank for missing main file when exceptions disabled', '', $t->render());

		$t = $this->templateFile();
		$t->setThrowExceptions(false);
		$this->check('setPrependFilename(missing) returns false when exceptions disabled', false, $t->setPrependFilename($missing));
		$this->check('setAppendFilename(missing) returns false when exceptions disabled', false, $t->setAppendFilename($missing));

		$this->check('setFilename(missing) throws by default', "Filename doesn't exist", $this->exceptionMessage(function() use ($missing) {
			$this->templateFile()->setFilename($missing);
		}), '*=');

		$this->check('setPrependFilename(missing) reports prepend filename', "Prepend filename doesn't exist", $this->exceptionMessage(function() use ($missing) {
			$this->templateFile()->setPrependFilename($missing);
		}), '*=');

		$this->check('setAppendFilename(missing) reports append filename', "Append filename doesn't exist", $this->exceptionMessage(function() use ($missing) {
			$this->templateFile()->setAppendFilename($missing);
		}), '*=');
	}

	protected function testChdir() {
		$file = $this->createFile('cwd.php', "<?php echo getcwd();");
		$cwd = getcwd();

		$this->check('render() changes cwd to template file directory by default', rtrim($this->path, '/'), $this->templateFile($file)->render());
		$this->check('render() restores cwd after render', $cwd, getcwd());

		$t = $this->templateFile($file);
		$t->setChdir(false);
		$this->check('setChdir(false) keeps original cwd during render', $cwd, $t->render());
		$this->check('setChdir(false) leaves cwd unchanged after render', $cwd, getcwd());
	}

	protected function testRenderStack() {
		$file = $this->createFile('render-stack.php',
			"<?php echo in_array(__FILE__, \\ProcessWire\\TemplateFile::getRenderStack()) ? 'in-stack' : 'missing';"
		);

		$this->check('getRenderStack() includes current file during render', 'in-stack', $this->templateFile($file)->render());
		$this->check('getRenderStack() empty after render', array(), TemplateFile::getRenderStack());
	}

	protected function testFileFailedHook() {
		$suppressed = $this->createFile('throws-suppressed.php', "<?php echo 'before'; throw new \\Exception('WireTests suppressed exception');");
		$throws = $this->createFile('throws.php', "<?php throw new \\Exception('WireTests exception');");
		$t = $this->templateFile($suppressed);

		$this->fileFailedHookID = $t->addHookAfter('fileFailed', function(HookEvent $event) {
			$event->return = false;
		});

		$this->check('fileFailed hook can suppress exception', 'before', $t->render());
		$this->check('getRenderStack() empty after suppressed exception', array(), TemplateFile::getRenderStack());
		$this->removeFileFailedHook($t);

		$this->check('render() throws file exception by default', 'WireTests exception', $this->exceptionMessage(function() use ($throws) {
			$this->templateFile($throws)->render();
		}), '*=');
	}

	protected function testToStringAndProperties() {
		$file = $this->createFile('to-string.php', "<?php echo 'string';");
		$t = $this->templateFile($file);

		$this->check('__toString() returns filename when set', $file, (string) $t);
		$this->check('__toString() returns class name when no filename set', 'TemplateFile', (string) $this->templateFile());
		$this->check('set(halt) updates halt property', true, $t->set('halt', true)->get('halt'));
	}

	protected function templateFile($filename = '') {
		return $this->wire(new TemplateFile($filename));
	}

	protected function createFile($name, $contents) {
		$filename = $this->path . $name;
		file_put_contents($filename, $contents);
		$this->files[$filename] = $filename;
		return $filename;
	}

	protected function cleanupFiles() {
		$files = $this->wire()->files;
		foreach($this->files as $file) {
			if(is_file($file)) $files->unlink($file, true);
		}
		$this->files = array();
		if($this->path && is_dir($this->path)) $files->rmdir($this->path, true);
	}

	protected function exceptionMessage(\Closure $callback) {
		try {
			$callback();
		} catch(\Exception $e) {
			return $e->getMessage();
		}
		return '';
	}

	protected function removeFileFailedHook($templateFile = null) {
		if(!$this->fileFailedHookID) return;
		if($templateFile) $templateFile->removeHook($this->fileFailedHookID);
		$this->fileFailedHookID = '';
	}
}
