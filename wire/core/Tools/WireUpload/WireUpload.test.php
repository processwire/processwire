<?php namespace ProcessWire;

/**
 * Tests for ProcessWire WireUpload
 *
 */
class WireTest_WireUpload extends WireTest {

	/**
	 * @var string[]
	 *
	 */
	protected $paths = array();

	public function execute() {
		$this->testFilenameValidation();
		$this->testMimeValidation();
		$this->testSaveUploadViaAjaxPath();
		$this->testOverwriteAndOriginalNames();
		$this->testErrorsAndAjaxDetection();
	}

	public function finish() {
		foreach(array_reverse($this->paths) as $path) {
			if(is_dir($path)) {
				$this->wire()->files->rmdir($path, true);
			} else if(is_file($path)) {
				$this->wire()->files->unlink($path);
			}
		}
		$this->paths = array();
	}

	/**
	 * Test validateFilename().
	 *
	 */
	protected function testFilenameValidation() {
		$upload = $this->wire(new WireUploadTestable('upload_file'));

		$this->check('validateFilename() rejects hidden filename', false, $upload->validateFilename('.htaccess'));
		$this->check('validateFilename() rejects filename without extension', false, $upload->validateFilename('filename'));
		$this->check('validateFilename() lowercases by default', 'report.pdf', $upload->validateFilename('Report.PDF', array('pdf')));
		$this->check('validateFilename() replaces extra basename dots', 'my_report_final.pdf', $upload->validateFilename('my.report.final.pdf', array('pdf')));
		$this->check('validateFilename() rejects disallowed extension list', false, $upload->validateFilename('photo.jpg', array('png')));

		$upload->setLowercase(false);
		$this->check('setLowercase(false) still normalizes extension handling', 'Report_PDF.pdf', $upload->validateFilename('Report.PDF', array('pdf')));
	}

	/**
	 * Test hasValidMimeType().
	 *
	 */
	protected function testMimeValidation() {
		$dir = $this->makeDir('mime');
		$txt = $dir . 'sample.txt';
		$jpg = $dir . 'sample.jpg';
		$this->wire()->files->filePutContents($txt, 'plain text');
		$this->wire()->files->filePutContents($jpg, 'plain text, not jpeg');
		$this->paths[] = $txt;
		$this->paths[] = $jpg;

		$upload = $this->wire(new WireUploadTestable('upload_file'));
		$this->check('hasValidMimeType() accepts matching text/plain', true, $upload->hasValidMimeType($txt, array('txt' => 'text/plain')));
		$this->check('hasValidMimeType() rejects mismatched JPEG content', false, $upload->hasValidMimeType($jpg, array('jpg' => 'image/jpeg')));
		$this->check('hasValidMimeType() returns true for unknown extension', true, $upload->hasValidMimeType($txt, array('pdf' => 'application/pdf')));
		$this->check('hasValidMimeType() returns false for missing file', false, $upload->hasValidMimeType($dir . 'missing.txt', array('txt' => 'text/plain')));
	}

	/**
	 * Test saveUpload() using AJAX move path for CLI compatibility.
	 *
	 */
	protected function testSaveUploadViaAjaxPath() {
		$dir = $this->makeDir('save');
		$tmp = $this->makeTmpFile('upload source');
		$upload = $this->wire(new WireUploadTestable('upload_file'));
		$return = $upload
			->setDestinationPath($dir)
			->setValidExtensions(array('txt'))
			->setMaxFiles(1)
			->setMaxFileSize(1000)
			->setTargetFilename('TargetName.dat');

		$this->check('configuration setters are chainable', $upload, $return);

		$saved = $upload->saveTmpFile($tmp, 'Original Name.TXT');
		$this->check('saveUpload() returns completed filename', 'targetname.txt', $saved);
		$this->check('saveUpload() saves destination file', true, is_file($dir . 'targetname.txt'));
		$this->check('getCompletedFilenames() returns saved filename', array('targetname.txt'), $upload->getCompletedFilenames());
		$this->check('getOriginalFilenames() maps saved to original', 'Original Name.TXT', $upload->getOriginalFilenames()['targetname.txt']);
		$this->check('saved file contains source data', 'upload source', file_get_contents($dir . 'targetname.txt'));
	}

	/**
	 * Test overwrite and collision behavior.
	 *
	 */
	protected function testOverwriteAndOriginalNames() {
		$dir = $this->makeDir('overwrite');
		$this->wire()->files->filePutContents($dir . 'same.txt', 'existing');

		$upload = $this->wire(new WireUploadTestable('upload_file'));
		$upload->setDestinationPath($dir)->setValidExtensions(array('txt'));
		$saved = $upload->saveTmpFile($this->makeTmpFile('new no overwrite'), 'same.txt');

		$this->check('saveUpload() creates unique filename when overwrite disabled', 'same-1.txt', $saved);
		$this->check('existing file remains when overwrite disabled', 'existing', file_get_contents($dir . 'same.txt'));
		$this->check('unique file contains new data', 'new no overwrite', file_get_contents($dir . 'same-1.txt'));

		$upload = $this->wire(new WireUploadTestable('upload_file'));
		$upload->setDestinationPath($dir)->setValidExtensions(array('txt'))->setOverwrite(true);
		$saved = $upload->saveTmpFile($this->makeTmpFile('new overwrite'), 'same.txt');

		$this->check('saveUpload() overwrites requested filename when enabled', 'same.txt', $saved);
		$this->check('overwritten file contains new data', 'new overwrite', file_get_contents($dir . 'same.txt'));
		$this->check('getOverwrittenFiles() records backup', 1, count($upload->getOverwrittenFiles()));
		$backup = key($upload->getOverwrittenFiles());
		$this->check('overwrite backup file exists while upload object is alive', true, is_file($backup));
	}

	/**
	 * Test errors and isAjaxUploading().
	 *
	 */
	protected function testErrorsAndAjaxDetection() {
		$upload = $this->wire(new WireUploadTestable('upload_file'));
		$upload->setDestinationPath($this->makeDir('errors'))->setValidExtensions(array('txt'));
		$saved = $upload->saveTmpFile($this->makeTmpFile('bad extension'), 'script.php');

		$this->check('saveUpload() rejects bad extension', false, $saved);
		$this->check('getErrors() reports invalid extension', true, count($upload->getErrors()) > 0);
		$this->check('getErrors(true) clears errors', true, count($upload->getErrors(true)) > 0);
		$this->check('getErrors() empty after clear', array(), $upload->getErrors());

		$old = isset($_SERVER['HTTP_X_FILENAME']) ? $_SERVER['HTTP_X_FILENAME'] : null;
		unset($_SERVER['HTTP_X_FILENAME']);
		$this->check('isAjaxUploading() false without header', false, WireUpload::isAjaxUploading());
		$_SERVER['HTTP_X_FILENAME'] = 'upload.txt';
		$this->check('isAjaxUploading() true with header', true, WireUpload::isAjaxUploading());
		if($old === null) {
			unset($_SERVER['HTTP_X_FILENAME']);
		} else {
			$_SERVER['HTTP_X_FILENAME'] = $old;
		}
	}

	/**
	 * Make a test directory.
	 *
	 * @param string $name
	 * @return string
	 *
	 */
	protected function makeDir($name) {
		$dir = $this->wire()->config->paths->cache . 'WireUploadTest-' . $name . '-' . mt_rand(100000, 999999) . '/';
		$this->wire()->files->mkdir($dir, true);
		$this->paths[] = $dir;
		return $dir;
	}

	/**
	 * Make a temporary source file.
	 *
	 * @param string $contents
	 * @return string
	 *
	 */
	protected function makeTmpFile($contents) {
		$file = tempnam(sys_get_temp_dir(), 'WireUploadTest');
		$this->wire()->files->filePutContents($file, $contents);
		$this->paths[] = $file;
		return $file;
	}
}

/**
 * Exposes saveUpload() so CLI tests can exercise the AJAX move path.
 *
 */
class WireUploadTestable extends WireUpload {

	public function saveTmpFile($tmpName, $filename) {
		return $this->saveUpload($tmpName, $filename, true);
	}
}
