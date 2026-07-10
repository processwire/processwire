<?php namespace ProcessWire;

/**
 * Tests for ProcessWire ImageSizerEngineIMagick module.
 *
 */
class WireTest_ImageSizerEngineIMagick extends WireTest {

	protected $testDir = '';
	protected $srgbProfile = '';

	public function allow() {
		if(!class_exists('\\Imagick')) {
			$this->li('Imagick PHP extension is not installed');
			return false;
		}
		$modules = $this->wire()->modules;
		$engine = $modules->get('ImageSizerEngineIMagick');
		if(!$engine instanceof ImageSizerEngineIMagick) {
			$this->li('ImageSizerEngineIMagick module is not available');
			return false;
		}
		if(!$engine->supported('install')) {
			$this->li('ImageSizerEngineIMagick is not supported in this environment');
			return false;
		}
		if(!$engine->supportsFormat('JPEG')) {
			$this->li('Imagick JPEG support is not available');
			return false;
		}
		return true;
	}

	public function init() {
		$config = $this->wire()->config;
		$files = $this->wire()->files;
		$this->testDir = $config->paths->cache . 'WireTests/ImageSizerEngineIMagick/';
		$this->srgbProfile = __DIR__ . '/sRGB2014.icc';
		if(!$files->mkdir($this->testDir, true)) {
			$this->fail("Unable to create test directory: $this->testDir");
		}
	}

	public function execute() {
		$this->testLibraryAndFormatSupport();
		$this->testResizeWithoutSRGBOption();
		$this->testSRGBOptionWithoutProfile();
		$this->testSRGBOptionWithICCProfile();
		$this->testOptionalWideGamutFixture();
	}

	public function finish() {
		if(!$this->testDir || !is_dir($this->testDir)) return;
		$files = $this->wire()->files;
		foreach(glob($this->testDir . '*') as $file) {
			if(is_file($file)) $files->unlink($file);
		}
	}

	protected function engine() {
		return $this->wire()->modules->get('ImageSizerEngineIMagick');
	}

	protected function createJpeg($filename, $color = '#ff0000', $withProfile = false) {
		$im = new \Imagick();
		$im->newImage(40, 40, new \ImagickPixel($color));
		$im->setImageFormat('jpeg');
		$im->setImageCompressionQuality(95);
		if($withProfile) {
			if(!is_file($this->srgbProfile)) $this->fail("sRGB profile not found: $this->srgbProfile");
			$im->profileImage('icc', file_get_contents($this->srgbProfile));
		}
		$result = $im->writeImage($filename);
		$im->clear();
		$im->destroy();
		if(!$result || !is_file($filename)) $this->fail("Unable to create test JPEG: $filename");
		return $filename;
	}

	protected function resizeImage($srcFile, array $options, $suffix) {
		$dstFile = $this->testDir . pathinfo($srcFile, PATHINFO_FILENAME) . "-$suffix.jpg";
		if(!copy($srcFile, $dstFile)) $this->fail("Unable to copy test image to: $dstFile");
		$engine = $this->engine();
		$engine->prepare($dstFile, array_merge(array(
			'quality' => 90,
			'sharpening' => 'none',
			'cropping' => false,
			'upscaling' => false,
		), $options));
		$result = $engine->resize(20, 20);
		if(!$result) $this->fail("ImageSizerEngineIMagick resize failed for: $dstFile");
		if(!is_file($dstFile)) $this->fail("Resize did not leave output file: $dstFile");
		return $dstFile;
	}

	protected function imageProfiles($filename) {
		$im = new \Imagick($filename);
		$profiles = $im->getImageProfiles('*');
		$im->clear();
		$im->destroy();
		return is_array($profiles) ? $profiles : array();
	}

	protected function imageSize($filename) {
		$info = getimagesize($filename);
		if(!is_array($info)) $this->fail("Unable to inspect image size: $filename");
		return array($info[0], $info[1]);
	}

	protected function averageSaturation($filename) {
		$im = new \Imagick($filename);
		$im->resizeImage(1, 1, \Imagick::FILTER_BOX, 1);
		$pixel = $im->getImagePixelColor(0, 0);
		$color = $pixel->getColor(true);
		$im->clear();
		$im->destroy();
		$max = max($color['r'], $color['g'], $color['b']);
		$min = min($color['r'], $color['g'], $color['b']);
		if($max <= 0) return 0.0;
		return ($max - $min) / $max;
	}

	protected function testLibraryAndFormatSupport() {
		$engine = $this->engine();
		$this->check('engine is ImageSizerEngineIMagick', true, $engine instanceof ImageSizerEngineIMagick);
		$this->check('supported install returns true', true, $engine->supported('install'));
		$this->check('supportsFormat JPEG returns true', true, $engine->supportsFormat('JPEG'));
		$this->check('supportsFormat lowercase jpg returns true', true, $engine->supportsFormat('jpg'));
		$this->check('library version is available', true, strlen($engine->getLibraryVersion()) > 0);
	}

	protected function testResizeWithoutSRGBOption() {
		$srcFile = $this->createJpeg($this->testDir . 'plain-source.jpg', '#ff0000', false);
		$outFile = $this->resizeImage($srcFile, array('sRGB' => false), 'nosrgb');

		$this->check('resize without sRGB option creates 20px width', 20, $this->imageSize($outFile)[0]);
		$this->check('resize without sRGB option creates 20px height', 20, $this->imageSize($outFile)[1]);
		$this->check('resize without source profile has no ICC profile', false, array_key_exists('icc', $this->imageProfiles($outFile)));
	}

	protected function testSRGBOptionWithoutProfile() {
		$srcFile = $this->createJpeg($this->testDir . 'plain-srgb-source.jpg', '#00ff00', false);
		$outFile = $this->resizeImage($srcFile, array('sRGB' => true), 'srgb');

		$this->check('sRGB option without source profile resizes image', array(20, 20), $this->imageSize($outFile));
		$this->check('sRGB option without source profile leaves no ICC profile', false, array_key_exists('icc', $this->imageProfiles($outFile)));
	}

	protected function testSRGBOptionWithICCProfile() {
		if(!is_file($this->srgbProfile)) {
			$this->ok('sRGB profile fixture not present, skipping ICC-profile resize check');
			return;
		}

		try {
			$srcFile = $this->createJpeg($this->testDir . 'icc-source.jpg', '#0000ff', true);
		} catch(\Exception $e) {
			$this->ok('Imagick could not attach ICC profile, skipping ICC-profile resize check: ' . $e->getMessage());
			return;
		}

		$this->check('source image has ICC profile', true, array_key_exists('icc', $this->imageProfiles($srcFile)));

		$outFile = $this->resizeImage($srcFile, array('sRGB' => true), 'icc-srgb');
		$this->check('sRGB option with ICC profile resizes image', array(20, 20), $this->imageSize($outFile));
		$this->check('sRGB option strips ICC profile after conversion', false, array_key_exists('icc', $this->imageProfiles($outFile)));
	}

	protected function testOptionalWideGamutFixture() {
		$fixture = __DIR__ . '/tests/images/p3-red.jpg';
		if(!is_file($fixture)) {
			$this->ok('Wide-gamut fixture not present, skipping saturation preservation check');
			return;
		}

		$srcFile = $this->testDir . 'p3-red-source.jpg';
		if(!copy($fixture, $srcFile)) $this->fail("Unable to copy wide-gamut fixture: $fixture");

		$profiles = $this->imageProfiles($srcFile);
		if(!array_key_exists('icc', $profiles)) {
			$this->ok('Wide-gamut fixture has no ICC profile, skipping saturation preservation check');
			return;
		}

		$satBefore = $this->averageSaturation($srcFile);
		$outFile = $this->resizeImage($srcFile, array('sRGB' => true), 'p3-srgb');
		$satAfter = $this->averageSaturation($outFile);
		$delta = abs($satBefore - $satAfter);

		$this->check('wide-gamut sRGB conversion preserves saturation within tolerance', true, $delta < 0.08);
		$this->check('wide-gamut output ICC profile stripped after conversion', false, array_key_exists('icc', $this->imageProfiles($outFile)));
	}
}
