<?php namespace ProcessWire;

/**
 * Tests for ProcessWire ImageSizerEngineAnimatedGif module.
 *
 */
class WireTest_ImageSizerEngineAnimatedGif extends WireTest {

	protected $installedByTest = false;
	protected $testDir = '';
	protected $fixture = '';

	public function allow() {
		if(!function_exists('gd_info') || !function_exists('imagecreatefromstring')) {
			$this->li('GD PHP extension is not installed');
			return false;
		}
		$modules = $this->wire()->modules;
		if(!$modules->isInstalled('ImageSizerEngineAnimatedGif') &&
			!$modules->isInstallable('ImageSizerEngineAnimatedGif')) {
			$modules->refresh();
		}
		if(!$modules->isInstalled('ImageSizerEngineAnimatedGif') &&
			!$modules->isInstallable('ImageSizerEngineAnimatedGif')) {
			$this->li('ImageSizerEngineAnimatedGif module is not available');
			return false;
		}
		$this->fixture = dirname(__DIR__, 2) . '/Fieldtype/FieldtypeImage/tests/images/GIF-google.gif';
		if(!is_file($this->fixture)) {
			$this->li("Animated GIF fixture not found: $this->fixture");
			return false;
		}
		return true;
	}

	public function init() {
		$modules = $this->wire()->modules;
		if(!$modules->isInstalled('ImageSizerEngineAnimatedGif')) {
			$modules->install('ImageSizerEngineAnimatedGif');
			$this->installedByTest = true;
		}
		$this->testDir = $this->wire()->config->paths->cache . 'WireTests/ImageSizerEngineAnimatedGif/';
		if(!$this->wire()->files->mkdir($this->testDir, true)) {
			$this->fail("Unable to create test directory: $this->testDir");
		}
	}

	public function execute() {
		$filename = $this->testDir . 'animated.gif';
		if(!copy($this->fixture, $filename)) $this->fail("Unable to copy test image: $filename");

		$this->check('source fixture is animated', true, ImageSizer::imageIsAnimatedGif($filename));
		$engine = $this->wire()->modules->get('ImageSizerEngineAnimatedGif');
		$this->check('engine is ImageSizerEngineAnimatedGif', true, $engine instanceof ImageSizerEngineAnimatedGif);
		$engine->prepare($filename, array(
			'cropping' => false,
			'upscaling' => false,
		));
		$this->check('animated GIF resize succeeds', true, $engine->resize(100, 36));

		$size = getimagesize($filename);
		$this->check('resized animated GIF dimensions', array(100, 36), array($size[0], $size[1]));
		$this->check('resized GIF remains animated', true, ImageSizer::imageIsAnimatedGif($filename));
	}

	public function finish() {
		if($this->testDir && is_dir($this->testDir)) {
			foreach(glob($this->testDir . '*') as $file) {
				if(is_file($file)) $this->wire()->files->unlink($file);
			}
		}
		if($this->installedByTest && $this->wire()->modules->isInstalled('ImageSizerEngineAnimatedGif')) {
			$this->wire()->modules->uninstall('ImageSizerEngineAnimatedGif');
		}
	}
}
