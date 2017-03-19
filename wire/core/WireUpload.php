<?php namespace ProcessWire;

/**
 * ProcessWire WireUpload
 *
 * Saves uploads of single or multiple files, saving them to the destination path.
 * If the destination path does not exist, it will be created. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireUpload extends Wire {

	/**
	 * Submitted field name for files field
	 * 
	 * @var string
	 * 
	 */
	protected $name;

	/**
	 * Path where files will be saved
	 * 
	 * @var string
	 * 
	 */
	protected $destinationPath;
	
	/**
	 * Max number of files accepted
	 * 
	 * @var int
	 * 
	 */
	protected $maxFiles;

	/**
	 * Max size (in bytes) of uploaded file
	 * 
	 * @var int
	 * 
	 */
	protected $maxFileSize = 0;

	/**
	 * Array of uploaded filenames (basenames)
	 * 
	 * @var array
	 * 
	 */
	protected $completedFilenames = array();

	/**
	 * Allow files to be overwritten?
	 * 
	 * @var bool
	 * 
	 */
	protected $overwrite;
	
	/**
	 * If specified, only this filename may be overwritten
	 * 
	 * @var string
	 * 
	 */
	protected $overwriteFilename = '';

	/**
	 * Enforce lowercase filenames?
	 * 
	 * @var bool
	 * 
	 */
	protected $lowercase = true;

	/**
	 * The filename to save to (if not using uploaded filename)
	 * 
	 * @var string
	 * 
	 */
	protected $targetFilename = '';

	/**
	 * Allow extraction of archives/ZIPs?
	 * 
	 * @var bool
	 * 
	 */
	protected $extractArchives = false;

	/**
	 * Allowed extensions for uploaded filenames
	 * 
	 * @var array
	 * 
	 */
	protected $validExtensions = array();

	/**
	 * Disallowed extensions for uploaded filenames
	 * 
	 * @var array
	 * 
	 */
	protected $badExtensions = array('php', 'php3', 'phtml', 'exe', 'cfm', 'shtml', 'asp', 'pl', 'cgi', 'sh');

	/**
	 * Errors that occurred
	 * 
	 * @var array of strings
	 * 
	 */
	protected $errors = array();

	/**
	 * Allow AJAX uploads?
	 * 
	 * @var bool
	 * 
	 */
	protected $allowAjax = false;

	/**
	 * Array of files (full paths) that were overwritten in format: backed up filename => replaced file name
	 * 
	 * @var array
	 * 
	 */
	protected $overwrittenFiles = array();

	/**
	 * Predefined error message strings indexed by PHP UPLOAD_ERR_* defines
	 * 
	 * @var array
	 * 
	 */
	protected $errorInfo = array();

	/**
	 * Construct with the given input name
	 * 
	 * @param string $name
	 * 
	 */
	public function __construct($name) {

		$this->errorInfo = array(
			UPLOAD_ERR_OK => $this->_('Successful Upload'),
			UPLOAD_ERR_INI_SIZE => $this->_('The uploaded file exceeds the upload_max_filesize directive in php.ini.'),
			UPLOAD_ERR_FORM_SIZE => $this->_('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'),
			UPLOAD_ERR_PARTIAL => $this->_('The uploaded file was only partially uploaded.'),
			UPLOAD_ERR_NO_FILE => $this->_('No file was uploaded.'),
			UPLOAD_ERR_NO_TMP_DIR => $this->_('Missing a temporary folder.'),
			UPLOAD_ERR_CANT_WRITE => $this->_('Failed to write file to disk.'),
			UPLOAD_ERR_EXTENSION => $this->_('File upload stopped by extension.')
			);

		$this->setName($name); 
		$this->maxFiles = 0; // no limit
		$this->overwrite = false; 
		$this->destinationPath = '';

		if($this->config->uploadBadExtensions) {
			$badExtensions = $this->config->uploadBadExtensions; 
			if(is_string($badExtensions) && $badExtensions) $badExtensions = explode(' ', $badExtensions); 
			if(is_array($badExtensions)) $this->badExtensions = $badExtensions; 			
		}	
	}

	/**
	 * Destruct by removing overwritten backup files (if applicable)
	 * 
	 */
	public function __destruct() {
		// cleanup files that were backed up when overwritten
		foreach($this->overwrittenFiles as $bakDestination => $destination) {
			if(is_file($bakDestination)) unlink($bakDestination);
		}
	}

	/**
	 * Execute/process the upload
	 * 
	 * @return array of uploaded filenames
	 * @throws WireException
	 * 
	 */
	public function execute() {

		if(!$this->name) throw new WireException("You must set the name for WireUpload before executing it"); 
		if(!$this->destinationPath) throw new WireException("You must set the destination path for WireUpload before executing it");

		$files = array();

		$f = $this->getPhpFiles();
		if(!$f) return $files;

		if(is_array($f['name'])) {
			// multi file upload
			$cnt = 0;
			foreach($f['name'] as $key => $name) {
				if($this->maxFiles && ($cnt >= $this->maxFiles)) {
					$this->error($this->_('Max file upload limit reached')); 
					break;
				}
				if(!$this->isValidUpload($f['name'][$key], $f['size'][$key], $f['error'][$key])) continue; 
				if(!$this->saveUpload($f['tmp_name'][$key], $f['name'][$key])) continue; 
				$cnt++;
			}

			$files = $this->completedFilenames; 

		} else {
			// single file upload, including ajax
			if($this->isValidUpload($f['name'], $f['size'], $f['error'])) {
				$this->saveUpload($f['tmp_name'], $f['name'], !empty($f['ajax']));  // returns filename or false
				$files = $this->completedFilenames; 
			}
		}

		return $files; 
	}

	/**
	 * Returns PHP's $_FILES or one constructed from an ajax upload
	 * 
	 * @return array|bool
	 * @throws WireException
	 *
	 */
	protected function getPhpFiles() {
		if(isset($_SERVER['HTTP_X_FILENAME']) && $this->allowAjax) return $this->getPhpFilesAjax();
		if(empty($_FILES) || !count($_FILES)) return false; 
		if(!isset($_FILES[$this->name]) || !is_array($_FILES[$this->name])) return false;
		return $_FILES[$this->name]; 	
	}

	/**
	 * Get the directory where files should upload to 
	 * 
	 * @return string 
	 * @throws WireException If no suitable upload directory can be found
	 * 
	 */
	protected function getUploadDir() {
		
		$config = $this->wire('config');
		$dir = $config->uploadTmpDir;
		
		if(!$dir && stripos(PHP_OS, 'WIN') === 0) {
			$dir = $config->paths->cache . 'uploads/';
			if(!is_dir($dir)) wireMkdir($dir);
		}
		
		if(!$dir || !is_writable($dir)) {
			$dir = ini_get('upload_tmp_dir');
		}
		
		if(!$dir || !is_writable($dir)) {
			$dir = sys_get_temp_dir();
		}
		
		if(!$dir || !is_writable($dir)) {
			throw new WireException(
				"Error writing to $dir. Please define \$config->uploadTmpDir and ensure it exists and is writable."
			);
		}
		
		return $dir;
	}

	/**
	 * Handles an ajax file upload and constructs a resulting $_FILES 
	 * 
	 * @return array|bool
	 * @throws WireException
	 *
	 */
	protected function getPhpFilesAjax() {

		if(!$filename = $_SERVER['HTTP_X_FILENAME']) return false; 
		$filename = rawurldecode($filename); // per #1487
		$dir = $this->getUploadDir();
		$tmpName = tempnam($dir, wireClassName($this, false));
		file_put_contents($tmpName, file_get_contents('php://input')); 
		$filesize = is_file($tmpName) ? filesize($tmpName) : 0;
		$error = $filesize ? UPLOAD_ERR_OK : UPLOAD_ERR_NO_FILE;

		$file = array(
			'name' => $filename, 
			'tmp_name' => $tmpName,
			'size' => $filesize,
			'error' => $error,
			'ajax' => true,
			);

		return $file;
	}

	/**
	 * Does the given filename have a valid extension?
	 * 
	 * @param string $name
	 * @return bool
	 * 
	 */
	protected function isValidExtension($name) {
		
		$pathInfo = pathinfo($name); 
		if(!isset($pathInfo['extension'])) return false;
		$extension = strtolower($pathInfo['extension']);

		if(in_array($extension, $this->badExtensions)) return false;
		if(in_array($extension, $this->validExtensions)) return true; 
		
		return false; 
	}

	/**
	 * Is the given upload information valid?
	 * 
	 * Also populates $this->errors
	 * 
	 * @param string $name Filename
	 * @param int $size Size in bytes
	 * @param int $error Error code from PHP
	 * @return bool
	 * 
	 */
	protected function isValidUpload($name, $size, $error) { 
		$valid = false;
		$fname = $this->wire('sanitizer')->name($name); 

		if($error && $error != UPLOAD_ERR_NO_FILE) {
			$this->error($this->errorInfo[$error]); 
		} else if(!$size) {
			$valid = false; // no data
		} else if($name[0] == '.') {
			$valid = false; 
		} else if(!$this->isValidExtension($name)) {
			$this->error(
				"$fname - " . $this->_('Invalid file extension, please use one of:') . ' ' . 
				implode(', ', $this->validExtensions)
			); 
		} else if($this->maxFileSize > 0 && $size > $this->maxFileSize) {
			$this->error("$fname - " . $this->_('Exceeds max allowed file size')); 
		} else {
			$valid = true; 
		}

		return $valid; 
	}

	/**
	 * Check that the destination path exists and populate $this->errors with appropriate message if it doesn't
	 * 
	 * @return bool
	 * 
	 */
	protected function checkDestinationPath() {
		if(!is_dir($this->destinationPath)) {
			$this->error("Destination path does not exist {$this->destinationPath}"); 
			return false;
		}
		return true; 
	}

	/**
	 * Given a filename/path destination, adjust it to ensure it is unique
	 * 
	 * @param string $destination
	 * @return string
	 * 
	 */
	protected function getUniqueFilename($destination) {

		$cnt = 0; 
		$p = pathinfo($destination); 
		$basename = basename($p['basename'], ".$p[extension]"); 

		while(file_exists($destination)) {
			$cnt++; 
			$filename = "$basename-$cnt.$p[extension]"; 
			$destination = "$p[dirname]/$filename"; 
		}
	
		return $destination; 	
	}

	/**
	 * Sanitize/validate a given filename
	 * 
	 * @param string $value Filename
	 * @param array $extensions Allowed file extensions
	 * @return bool|string Returns boolean false if invalid or string of potentially modified filename if valid
	 * 
	 */
	public function validateFilename($value, $extensions = array()) {
		$value = basename($value);
		if($value[0] == '.') return false; // no hidden files
		if($this->lowercase) $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
		$value = $this->wire('sanitizer')->filename($value, Sanitizer::translate); 
		$value = trim($value, "_");

		$p = pathinfo($value);
		if(!isset($p['extension'])) return false;
		$extension = strtolower($p['extension']);
		$basename = basename($p['basename'], ".$extension"); 
		// replace any dots in the basename with underscores
		$basename = trim(str_replace(".", "_", $basename), "_"); 
		$value = "$basename.$extension";

		if(count($extensions)) {
			if(!in_array($extension, $extensions)) $value = false;
		}

		return $value;
	}

	/**
	 * Save the uploaded file
	 * 
	 * @param string $tmp_name Temporary filename
	 * @param string $filename Actual filename
	 * @param bool $ajax Is this an AJAX upload?
	 * @return array|bool|string Boolean false on fail, array of multiple filenames, or string of filename if maxFiles=1
	 * 
	 */
	protected function saveUpload($tmp_name, $filename, $ajax = false) {

		if(!$this->checkDestinationPath()) return false; 
		$filename = $this->getTargetFilename($filename); 
		$filename = $this->validateFilename($filename);
		if($this->lowercase) $filename = strtolower($filename); 
		$destination = $this->destinationPath . $filename;
		$p = pathinfo($destination); 
		$exists = file_exists($destination); 

		if(!$this->overwrite && $filename != $this->overwriteFilename) {
			// overwrite not allowed, so find a new name for it
			$destination = $this->getUniqueFilename($destination); 
			$filename = basename($destination); 
			
		} else if($exists && $this->overwrite) {
			// file already exists in destination and will be overwritten
			// here we back it up temporarily, and we don't remove the backup till __destruct()
			$bakName = $filename; 
			do {
				$bakName = "_$bakName";
				$bakDestination = $this->destinationPath . $bakName;
			} while(file_exists($bakDestination)); 
			rename($destination, $bakDestination);
			$this->overwrittenFiles[$bakDestination] = $destination;
		}

		if($ajax) $success = @rename($tmp_name, $destination);
			else $success = move_uploaded_file($tmp_name, $destination);

		if(!$success) {
			$this->error("Unable to move uploaded file to: $destination");
			if(is_file($tmp_name)) @unlink($tmp_name); 
			return false;
		}

		$this->wire('files')->chmod($destination);

		if($p['extension'] == 'zip' && ($this->maxFiles == 0) && $this->extractArchives) {
			if($this->saveUploadZip($destination)) {
				if(count($this->completedFilenames) == 1) return $this->completedFilenames[0];
			}
			return $this->completedFilenames; 

		} else {
			$this->completedFilenames[] = $filename; 
			return $filename; 
		}
	}

	/**
	 * Save and process an uploaded ZIP file
	 * 
	 * @param string $zipFile
	 * @return array|bool Array of files in the ZIP or boolean false on fail
	 * @throws WireException If ZIP is empty
	 * 
	 */
	protected function saveUploadZip($zipFile) {

		// unzip with command line utility

		$files = array(); 
		$dir = dirname($zipFile) . '/';
		$tmpDir = $dir . '.zip_tmp/';
	
		try {
			$files = $this->wire('files')->unzip($zipFile, $tmpDir); 
			if(!count($files)) {
				throw new WireException($this->_('No files found in ZIP file'));
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage());
			$this->wire('files')->rmdir($tmpDir, true);
			unlink($zipFile); 
			return $files;
		}
	
		$cnt = 0; 

		foreach($files as $file) {
			
			$pathname = $tmpDir . $file;

			if(!$this->isValidUpload($file, filesize($pathname), UPLOAD_ERR_OK)) {
				@unlink($pathname); 
				continue; 
			}

			$basename = $file;
			$basename = $this->validateFilename($basename, $this->validExtensions); 

			if($basename) {
				$destination = $dir . $basename;
				if(file_exists($destination) && $this->overwrite) {
					$bakName = $basename;
					do {
						$bakName = "_$bakName";
						$bakDestination = $dir . $bakName;
					} while(file_exists($bakDestination));
					rename($destination, $bakDestination);
					$this->wire('log')->message("Renamed $destination => $bakDestination");
					$this->overwrittenFiles[$bakDestination] = $destination;
					
				} else {
					$destination = $this->getUniqueFilename($dir . $basename); 
				}
			} else {
				$destination = '';
			}

			if($destination && rename($pathname, $destination)) {
				$this->completedFilenames[] = basename($destination); 
				$cnt++; 
			} else {
				@unlink($pathname); 
			}
		}

		$this->wire('files')->rmdir($tmpDir, true); 
		@unlink($zipFile); 

		if(!$cnt) return false; 
		return true; 	
	}

	/**
	 * Get array of uploaded filenames
	 * 
	 * @return array
	 * 
	 */
	public function getCompletedFilenames() {
		return $this->completedFilenames; 
	}

	/**
	 * Set the target filename, only useful for single uploads
	 * 
	 * @param $filename
	 * 
	 */
	public function setTargetFilename($filename) {
		$this->targetFilename = $filename; 
	}

	/**
	 * Get target filename updated for extension
	 * 
	 * Given a filename, takes its extension and combines it with that if the targetFilename (if set).
	 * Otehrwise returns the filename you gave it.
	 * 
	 * @param string $filename
	 * @return string
	 * 
	 */
	protected function getTargetFilename($filename) {
		if(!$this->targetFilename) return $filename; 
		$pathInfo = pathinfo($filename); 
		$targetPathInfo = pathinfo($this->targetFilename); 
		return rtrim(basename($this->targetFilename, $targetPathInfo['extension']), ".") . "." . $pathInfo['extension'];
	}

	/**
	 * Set the filename that may be overwritten (i.e. myphoto.jpg) for single uploads only
	 * 
	 * @param string $filename
	 * @return $this
	 * 
	 */
	public function setOverwriteFilename($filename) {
		$this->overwrite = false; // required
		if($this->lowercase) $filename = strtolower($filename); 
		$this->overwriteFilename = $filename; 
		return $this; 
	}

	/**
	 * Set allowed file extensions
	 * 
	 * @param array $extensions Array of file extensions (strings), not including periods
	 * @return $this
	 * 
	 */
	public function setValidExtensions(array $extensions) {
		foreach($extensions as $ext) $this->validExtensions[] = strtolower($ext); 
		return $this; 
	}

	/**
	 * Set the max allowed number of uploaded files
	 * 
	 * @param int $maxFiles
	 * @return $this
	 * 
	 */
	public function setMaxFiles($maxFiles) {
		$this->maxFiles = (int) $maxFiles; 
		return $this; 
	}

	/**
	 * Set the max allowed uploaded file size
	 * 
	 * @param int $bytes
	 * @return $this
	 * 
	 */
	public function setMaxFileSize($bytes) {
		$this->maxFileSize = (int) $bytes;
		return $this;
	}

	/**
	 * Set whether or not overwrite is allowed
	 * 
	 * @param bool $overwrite
	 * @return $this
	 * 
	 */
	public function setOverwrite($overwrite) {
		$this->overwrite = $overwrite ? true : false; 
		return $this; 
	}

	/**
	 * Set the destination path for uploaded files
	 * 
	 * @param string $destinationPath Include a trailing slash
	 * @return $this
	 * 
	 */
	public function setDestinationPath($destinationPath) {
		$this->destinationPath = $destinationPath; 
		return $this; 
	}

	/**
	 * Set whether or not ZIP files may be extracted
	 * 
	 * @param bool $extract
	 * @return $this
	 * 
	 */
	public function setExtractArchives($extract = true) {
		$this->extractArchives = $extract; 
		$this->validExtensions[] = 'zip';
		return $this; 
	}

	/**
	 * Set the upload field name (same as that provided to the constructor)
	 * 
	 * @param string $name
	 * @return $this
	 * 
	 */
	public function setName($name) {
		$this->name = $this->wire('sanitizer')->fieldName($name); 
		return $this; 
	}

	/**
	 * Set whether or not lowercase is enforced
	 * 
	 * @param bool $lowercase
	 * @return $this
	 * 
	 */
	public function setLowercase($lowercase = true) {
		$this->lowercase = $lowercase ? true : false; 
		return $this; 
	}

	/**
	 * Set whether or not AJAX uploads are allowed
	 * 
	 * @param bool $allowAjax
	 * @return $this
	 * 
	 */
	public function setAllowAjax($allowAjax = true) {
		$this->allowAjax = $allowAjax ? true : false; 
		return $this; 
	}

	/**
	 * Record an error message
	 * 
	 * @param array|Wire|string $text
	 * @param int $flags
	 * @return $this
	 * 
	 */
	public function error($text, $flags = 0) {
		$this->errors[] = $text; 
		return parent::error($text, $flags); 
	}

	/**
	 * Get error messages
	 * 
	 * @param bool $clear Clear the list of error messages? (default=false)
	 * @return array of strings
	 * 
	 */
	public function getErrors($clear = false) {
		$errors = $this->errors; 
		if($clear) $this->errors = array();
		return $errors;
	}

	/**
	 * Get files that were overwritten (for overwrite mode only)
	 * 
	 * WireUpload keeps a temporary backup of replaced files. The backup will be removed at __destruct()
	 * You may retrieve backed up files temporarily if needed. 
	 * 
	 * @return array associative array of ('backup path/file' => 'replaced basename')
	 * 
	 */
	public function getOverwrittenFiles() {
		return $this->overwrittenFiles;
	}
}


