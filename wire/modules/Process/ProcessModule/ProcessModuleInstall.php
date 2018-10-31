<?php namespace ProcessWire;

/**
 * Class ProcessModuleInstall
 * 
 * Provides methods for internative module installation for ProcessModule
 * 
 */

class ProcessModuleInstall extends Wire {
	
	protected $tempDir = '';

	/**
	 * Returns a temporary directory (path) for use by this object
	 *
	 * @return string|bool Returns false if you specified $create=false, and the dir doesn't exist
	 * @throws WireException If can't create temporary dir
	 * 
	 */
	public function getTempDir() { 
		if(empty($this->tempDir)) $this->tempDir = $this->wire(new WireTempDir($this)); 
		return $this->tempDir->get(); 
	}

	/**
	 * Check that the system supports direct upload and download of modules
	 *
	 * This primarily checks that needed dirs are writable and ZipArchive is available.
	 *
	 * @param bool $notify Specify true to make it queue the relevant reason/error message if upload/download not supported. (default=false)
	 * @return bool
	 *
	 */
	public function canUploadDownload($notify = true) {
		$can = true;
		if(!is_writable($this->config->paths->cache)) {
			if($notify) $this->error($this->_('Make sure /site/assets/cache/ directory is writeable for PHP.'));
			$can = false;
		}
		if(!is_writable($this->config->paths->siteModules)) {
			if($notify) $this->error($this->_('Make sure your site modules directory (/site/modules/) is writeable for PHP.'));
			$can = false;
		}
		if(!class_exists('ZipArchive')) {
			if($notify) $this->error($this->_('ZipArchive class is required and your PHP does not appear to have it.'));
			$can = false;
		}

		return $can;
	}

	/**
	 * Find all module files, recursively in Path
	 * 
	 * @param string $path Omit to use the default (/site/modules/)
	 * @param int $maxLevel Max depth to pursue module files in (recursion level)
	 * @return array of module classname => full pathname to module file
	 * 
	 */
	public function findModuleFiles($path = '', $maxLevel = 4) {
		
		static $level = 0;
		$level++;
		$files = array();
		if(!$path) $path = $this->wire('config')->paths->siteModules;
		
		// find the names of all existing module files, so we can defer to their dirs
		// if a module is being installed that already exists
		
		foreach(new \DirectoryIterator($path) as $file) {
			
			if($file->isDot()) continue; 
			if(substr($file->getBasename(), 0, 1) == '.') continue;
			
			if($file->isDir() && $level < $maxLevel) {
				$_files = $this->findModuleFiles($file->getPathname());
				$files = array_merge($_files, $files); 	
				
			} else if($file->isFile()) {
				$basename = $file->getBasename();
				if(!strpos($basename, '.module')) continue; 
				if(!preg_match('/^([A-Z][a-zA-Z0-9_]+)\.module(?:\.php)?$/', $basename, $matches)) continue; 
				$className = $matches[1]; 
				$files[$className] = $file->getPathname();
				
			}
		}
		
		$level--;
		return $files; 
	}

	/**
	 * Given a list of files from a module (and their temp dir) return the recommended directory name for it to live in
	 *
	 * i.e. /site/modules/[ModuleDir]/
	 *
	 * @param array $files Files found in the module's ZIP file
	 * @param string $modulePath Path where module will live
	 * @return bool|string Returns false if no module files found. Otherwise returns string with module path.  
	 *
	 */
	public function determineDestinationDir(array $files, $modulePath = '') {
		
		$moduleFiles = array(); // all module files found
		$moduleFiles1 = array(); // level1 module files (those in closest dir or subdir)
		$moduleDirs = array(); // all module dirs found
		$moduleDir = ''; // recommended name for module dir (returned by this method)
		if(!$modulePath) $modulePath = $this->wire('config')->paths->siteModules;
		$tempDir = $this->getTempDir();

		foreach($files as $key => $f) {
			// determine which file should be responsible for the name
			if(strpos($f, '/') !== false) {
				$dir = dirname($f);
				if($dir != '.') $moduleDirs[$dir] = $dir;
			}
			if(preg_match('{^(.*?/|)(([A-Z][a-zA-Z0-9_]+)\.module(?:\.php)?)$}', $f, $matches)) {
				$path = $matches[1];
				$name = $matches[3];
				$file = $matches[2];
				$moduleFiles[$name] = $path . $file;
			}
		}

		if(!count($moduleFiles)) {
			return false;
		}

		// determine which module files are at lowest level in dir tree
		$numSlashes = 0;
		while(!count($moduleFiles1) && $numSlashes < 5) {
			foreach($moduleFiles as $name => $file) {
				if(substr_count($file, '/') == $numSlashes) {
					$moduleFiles1[$name] = $name;
				}
			}
			$numSlashes++;
		}
		
		if(count($moduleFiles1) == 1) {
			// if only 1 module file, use that as the dir name
			reset($moduleFiles1);
			$moduleDir = key($moduleFiles1);
			$dir = $modulePath . $moduleDir . '/';
			//$this->message("Determined destination dir to be (1): $dir", Notice::debug); 
			return $dir;
		}
	
		// see if any of the module files match up with one already on the file system,
		// in which case we'll defer to that module for the destination dir
		$moduleFilesAll = $this->findModuleFiles($modulePath); 
		foreach($moduleFiles1 as $name) {
			if(isset($moduleFilesAll[$name])) {
				$moduleDir = dirname($moduleFilesAll[$name]);
				$moduleDir = basename($moduleDir); 
				if($moduleDir == 'modules') $moduleDir = '';
			}
		}
		if($moduleDir) {
			$dir = $modulePath . $moduleDir  . '/';
			//$this->message("Determined destination dir to be (2): $dir", Notice::debug); 
			return $dir;
		}


		// sort by length 
		$sorted = array();
		foreach($moduleFiles1 as $name => $file) {
			$len = strlen($name);
			while(isset($sorted[$len])) $len++;
			$sorted[$len] = $name;
		}
		krsort($sorted); // sort by longest to shortest
		$moduleFiles1 = array();
		foreach($sorted as $name) {
			$moduleFiles1[$name] = $name;
		}
		
		$extractedDir = trim($files[0], '/');

		if(is_dir($tempDir . $extractedDir) && $extractedDir[0] != '.') {

			// ensure it follows class name format
			if(preg_match('/^(A-Z[a-zA-Z0-9_]+)(-[a-z0-9]*)?$/', $extractedDir, $matches)) {
				// reduced to class name, with optional "-text" at the end removed (i.e. GitHub branch name)

				$extractedDir = $matches[1];
				// extractedDir follows the name format of a module
				// determine if it lines up with any of the found modules
				foreach($moduleFiles1 as $name => $file) {
					if($name == $extractedDir) $moduleDir = $name; // FOUND IT
				}

				// if not yet found, determine if they start the same
				if(!$moduleDir) foreach($moduleFiles1 as $name => $file) {
					if(strpos($name, $extractedDir) === 0) {
						$moduleDir = $extractedDir;
						break;
					}
				}

				// if we haven't been able to match to a moduleName, 
				// just use the extractedDir since it follows a class name format
				if(!$moduleDir) {
					$dir = $modulePath . $extractedDir . '/';
					//$this->message("Determined destination dir to be (3): $dir", Notice::debug); 
					return $dir; 
				}
			}
		}

		// if we reach this point, use the shortest module name as the dirname	
		$moduleDir = end($moduleFiles1);
		$dir = $modulePath . $moduleDir . '/';
		//$this->message("Determined destination dir to be (4): $dir", Notice::debug); 
		return $dir; 
	}

	/**
	 * Unzip the module file to tempDir and then copy to destination directory
	 *
	 * @param string $file File to unzip
	 * @param string $destinationDir Directory to copy completed files into. Optionally omit to determine automatically.
	 * @return bool|string Returns destinationDir on success, false on failure
	 * @throws WireException
	 *
	 */
	public function unzipModule($file, $destinationDir = '') {

		$success = false;
		$tempDir = $this->getTempDir();
		$mkdirDestination = false;

		try {
			$files = $this->wire('files')->unzip($file, $tempDir);
			if(is_file($file)) $this->wire('files')->unlink($file, true);
			foreach($files as $f) $this->message("Extracted: $f", Notice::debug); 

		} catch(\Exception $e) {
			$this->error($e->getMessage());
			if(is_file($file)) $this->wire('files')->unlink($file, true);
			return false;
		}

		if(!$destinationDir) {
			$destinationDir = $this->determineDestinationDir($files); 
			if(!$destinationDir) throw new WireException($this->_('Unable to find any module files'));
		}
		$this->message("Destination directory: $destinationDir", Notice::debug); 
		
		$files0 = trim($files[0], '/');
		$extractedDir = is_dir("$tempDir/$files0") && substr($files0, 0, 1) != '.' ? "$files0/" : "";

		// now create module directory and copy files over
		if(is_dir($destinationDir)) {
			// destination dir already there, perhaps an older version of same module?
			// create a backup of it
			$hasBackup = $this->backupDir($destinationDir);
			if($hasBackup) $this->wire('files')->mkdir($destinationDir, true); 
		} else {
			if($this->wire('files')->mkdir($destinationDir, true)) $mkdirDestination = true;
			$hasBackup = false; 
		}

		// label to identify destinationDir in messages and errors
		$dirLabel = str_replace($this->config->paths->root, '/', $destinationDir);

		if(is_dir($destinationDir)) {
			$from = $tempDir . $extractedDir;
			if($this->wire('files')->copy($from, $destinationDir)) {
				$this->message($this->_('Successfully copied files to new directory:') . ' ' . $dirLabel);
				$this->wire('files')->chmod($destinationDir, true);
				$success = true;
			} else {
				$this->error($this->_('Unable to copy files to new directory:') . ' ' . $dirLabel);
				if($hasBackup) $this->restoreDir($destinationDir); 
			}
		} else {
			$this->error($this->_('Could not create directory:') . ' ' . $dirLabel);
		}

		if(!$success) {
			$this->error($this->_('Unable to copy module files:') . ' ' . $dirLabel);
			if($mkdirDestination && !$this->wire('files')->rmdir($destinationDir, true)) {
				$this->error($this->_('Could not delete failed module dir:') . ' ' . $destinationDir, Notice::log);
			}
		}

		return $success ? $destinationDir : false;
	}
	
	protected function backupDir($moduleDir) {
		$dir = rtrim($moduleDir, "/");
		$name = basename($dir);
		$parentDir = dirname($dir);
		$backupDir = "$parentDir/.$name/";
		if(is_dir($backupDir)) wireRmdir($backupDir, true); // if there's already an old backup copy, remove it
		$success = false;
		
		if(is_link(rtrim($moduleDir, '/'))) {
			// module directory is a symbolic link
			// copy files from symlink dir to real backup dir
			$success = $this->wire('files')->copy($moduleDir, $backupDir); 
			// remove symbolic link
			unlink(rtrim($moduleDir, '/'));
			$dir = str_replace($this->wire('config')->paths->root, '/', $moduleDir); 
			$this->warning(sprintf(
				$this->_('Please note that %s was a symbolic link and has been converted to a regular directory'), $dir
			)); 
		} else {
			// module is a regular directory
			// just rename it to become the new backup dir
			if($this->wire('files')->rename($moduleDir, $backupDir)) $success = true; 
		}
		
		if($success) {
			$this->message(sprintf($this->_('Backed up existing %s'), $name) . " => " . str_replace($this->wire('config')->paths->root, '/', $backupDir));
			return true; 
		} else {
			return false;
		}
	}
	
	protected function restoreDir($moduleDir) {
		$dir = rtrim($moduleDir, "/");
		$name = basename($dir);
		$parentDir = dirname($dir);
		$backupDir = "$parentDir/.$name/";
		if(is_dir($backupDir)) {
			wireRmdir($moduleDir, true); // if there's already an old backup copy, remove it
			if(rename($backupDir, $moduleDir)) { 
				$this->message(sprintf($this->_('Restored backup of %s'), $name) . " => $moduleDir");
			}
		}
		return false;
	}
	
	/**
	 * Process a module upload 
	 *
	 * @param string $inputName Optionally specify the name of the $_FILES input to look for (default=upload_module)
	 * @param string $destinationDir Optionally specify destination path for completed unzipped files
	 * @return bool|string Returns destinationDir on success, false on failure.
	 *
	 */
	public function uploadModule($inputName = 'upload_module', $destinationDir = '') {

		if(!$this->canUploadDownload()) {
			$this->error($this->_('Unable to complete upload'));
			return false;
		}

		$tempDir = $this->getTempDir();

		/** @var WireUpload $ul */
		$ul = $this->wire(new WireUpload($inputName));
		$ul->setValidExtensions(array('zip'));
		$ul->setMaxFiles(1);
		$ul->setOverwrite(true);
		$ul->setDestinationPath($tempDir);
		$ul->setExtractArchives(false);
		$ul->setLowercase(false);

		$files = $ul->execute();

		if(count($files)) {
			$file = $tempDir . reset($files);
			$destinationDir = $this->unzipModule($file, $destinationDir);
			if($destinationDir) $this->modules->resetCache();

		} else {
			$this->error($this->_('No uploads found'));
			$destinationDir = false;
		}

		return $destinationDir; 
	}

	/**
	 * Given a URL to a ZIP file, download it, unzip it, and move to /site/modules/[ModuleName]
	 *
	 * @param $url
	 * @param string $destinationDir Optional destination path for files (omit to auto-determine)
	 * @return bool|string Returns destinationDir on success, false on failure.
	 *
	 */
	public function downloadModule($url, $destinationDir = '') {
		
		if(!$this->canUploadDownload()) {
			$this->error($this->_('Unable to complete download'));
			return false;
		}

		if(!preg_match('{^https?://}i', $url)) {
			$this->error($this->_('Invalid download URL specified'));
			return false; 
		}	

		$tempDir = $this->getTempDir();
		$tempName = 'module-temp.zip';
		// if there is a recognizable ZIP filename in the URL, use that rather than module-temp.zip
		if(preg_match('/([-._a-z0-9]+\.zip)$/i', $url, $matches)) $tempName = $matches[1];
		$tempZIP = $tempDir . $tempName;

		// download the zip file and save it in assets directory
		$success = false;
		$http = $this->wire(new WireHttp());

		try {
			$file = $http->download($url, $tempZIP); // throws exceptions on any error
			$this->message(sprintf($this->_('Downloaded ZIP file: %s (%d bytes)'), $url, filesize($file)));
			$destinationDir = $this->unzipModule($file, $destinationDir);
			if($destinationDir) {
				$success = true;
				$this->modules->resetCache();
			}

		} catch(\Exception $e) {
			$this->error($e->getMessage());
			$this->wire('files')->unlink($tempZIP);
		}

		return $success ? $destinationDir : false; 
	}
	

}

