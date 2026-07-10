<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireApiDocs
 *
 */
class WireTest_WireApiDocs extends WireTest {

	protected $path = '';

	public function init() {
		$this->path = $this->wire()->config->paths->cache . 'WireTests/WireApiDocs/';
		$this->cleanupFiles();
		WireApiDocs::reset();
		$this->wire()->files->mkdir($this->path, true);
	}

	public function execute() {
		$this->createClassFile('Documented/Documented.php', 'Documented');
		$this->createClassFile('Documented/DocumentedExtra.php', 'DocumentedExtra');
		$this->createClassFile('Documented/Nested/DocumentedNested.php', 'DocumentedNested');
		$this->createFile('Documented/API.md', "# Documented\n");
		$this->createClassFile('CustomDocs/CustomDocs.php', 'CustomDocs');
		$this->createFile('CustomDocs/DOCS.md', "# CustomDocs\n");

		$this->createClassFile('Missing/MissingClass.php', 'MissingClass');
		$this->createClassFile('Missing/MissingModule.module', 'MissingModule');
		$this->createClassFile('Missing/MissingModulePhp.module.php', 'MissingModulePhp');
		$this->createFile('Missing/Multiple.php', "<?php\nclass Multiple {}\nclass MissingExtra {}\n");
		$this->createClassFile('Missing/NoMatch.php', 'DifferentClass');
		$this->createFile('Missing/admin.php', "<?php echo 'template';\n");
		$this->createFile('Missing/Anonymous.php', "<?php return new class {};\n");
		$this->createFile('Missing/MissingInterface.php', "<?php interface MissingInterface {}\n");
		$this->createFile('Missing/MissingTrait.php', "<?php trait MissingTrait {}\n");
		$this->createClassFile('Missing/WireTest_Missing.php', 'WireTest_Missing');
		$this->createClassFile('Excluded/ExcludedClass.php', 'ExcludedClass');
		$this->createClassFile('Primary/Primary.php', 'Primary');
		$this->createClassFile('Primary/PrimaryHelper.php', 'PrimaryHelper');
		$this->createClassFile('NoPrimary/Standalone.php', 'Standalone');

		$this->createClassFile('Textformatter/TextformatterSmartypants/Michelf/SmartyPants.php', 'SmartyPants');
		$this->createClassFile('Inputfield/InputfieldDatetime/types/InputfieldDatetimeHtml.php', 'InputfieldDatetimeHtml');
		$this->createClassFile('System/SystemUpdater/SystemUpdater.module', 'SystemUpdater');

		$docs = new WireApiDocs($this->wire());
		$docs->excludeDirNames('Excluded');
		$missing = $docs->findMissingApiFiles($this->path);

		$expected = [
			'CustomDocs',
			'MissingClass',
			'MissingModule',
			'MissingModulePhp',
			'Multiple',
			'Primary',
			'Standalone',
		];

		$this->check('findMissingApiFiles() finds PHP and module classes', $expected, array_keys($missing));
		$this->check('findMissingApiFiles() returns full source file path', $this->path . 'Missing/MissingClass.php', $missing['MissingClass']);
		$this->check('findMissingApiFiles() skips all classes in documented directory', false, isset($missing['DocumentedExtra']));
		$this->check('findMissingApiFiles() skips descendants of documented directory', false, isset($missing['DocumentedNested']));
		$this->check('findMissingApiFiles() skips excluded directories', false, isset($missing['ExcludedClass']));
		$this->check('findMissingApiFiles() skips test classes', false, isset($missing['WireTest_Missing']));
		$this->check('findMissingApiFiles() skips non-class PHP files', false, isset($missing['MissingInterface']));
		$this->check('findMissingApiFiles() requires class and filename match', false, isset($missing['MissingExtra']));
		$this->check('findMissingApiFiles() skips supporting class beside primary class', false, isset($missing['PrimaryHelper']));
		$this->check('findMissingApiFiles() keeps class when directory has no primary class', true, isset($missing['Standalone']));
		$this->check('findMissingApiFiles() skips Smartypants support classes', false, isset($missing['SmartyPants']));
		$this->check('findMissingApiFiles() skips InputfieldDatetime support classes', false, isset($missing['InputfieldDatetimeHtml']));
		$this->check('findMissingApiFiles() skips SystemUpdater directory', false, isset($missing['SystemUpdater']));

		$customDocs = new WireApiDocs($this->wire());
		$customDocs->apiFileNames('DOCS.md');
		$customMissing = $customDocs->findMissingApiFiles($this->path);
		$this->check('findMissingApiFiles() honors configured API filenames', false, isset($customMissing['CustomDocs']));

		$this->testListPendingCli();
	}

	public function finish() {
		$this->cleanupFiles();
		WireApiDocs::reset();
	}

	protected function createClassFile($file, $className) {
		return $this->createFile($file, "<?php\nclass $className {}\n");
	}

	protected function testListPendingCli() {
		$docs = new WireApiDocs($this->wire());
		$docs->apiPaths($this->path, true);
		$docs->excludeDirNames('Excluded');

		$this->check('getCliCommands() includes list-pending', true, isset($docs->getCliCommands()['list-pending']));

		ob_start();
		$docs->executeCli([ 'list-pending' ]);
		$json = ob_get_clean();
		$items = json_decode($json, true);
		$this->check('list-pending returns JSON name and file items', true,
			is_array($items) && isset($items[0]['name'], $items[0]['file']));
		$this->check('list-pending JSON includes pending class', 'CustomDocs', $items[0]['name'] ?? '');

		ob_start();
		$docs->executeCli([ 'list-pending-text' ]);
		$text = ob_get_clean();
		$this->check('list-pending-text includes class and file', true,
			strpos($text, 'CustomDocs: ') === 0 && strpos($text, 'CustomDocs.php') !== false);
	}

	protected function createFile($file, $contents) {
		$file = $this->path . $file;
		$dirname = dirname($file);
		if(!is_dir($dirname)) $this->wire()->files->mkdir($dirname, true);
		file_put_contents($file, $contents);
		return $file;
	}

	protected function cleanupFiles() {
		if($this->path && is_dir($this->path)) {
			$this->wire()->files->rmdir($this->path, true);
		}
	}
}
