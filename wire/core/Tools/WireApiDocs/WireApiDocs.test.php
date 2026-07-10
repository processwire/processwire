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
	}

	public function finish() {
		$this->cleanupFiles();
		WireApiDocs::reset();
	}

	protected function createClassFile($file, $className) {
		return $this->createFile($file, "<?php\nclass $className {}\n");
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
