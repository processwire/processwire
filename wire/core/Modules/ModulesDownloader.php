<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Downloader (for install or upgrade)
 *
 * Methods in this class can be accessed from:
 *
 *  - `$modules->downloader()->...` (when $modules is in scope)
 *  - `wire()->modules->downloader()->...`, (anywhere)
 *  - `$this->wire()->modules->downloader()->...` (in ProcessWire classes)
 *
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 *
 */
class ModulesDownloader extends ModulesClass {

	/**
	 * @var WireTempDir|null
	 *
	 */
	private $tempDir = null;

	/**
	 * Download a module ZIP from the modules directory, URL, or local path, and extract it to /site/modules/
	 *
	 * @param string $module Module class name (from PW modules directory), ZIP URL, or local ZIP path
	 * @param array $options Options to modify default behavior:
	 * - `destinationDir` (string): Destination directory/path for extracted module files
	 * - `install` (bool): Also install module after downloading?
	 * @return array Returns an array with the following keys:
	 * - `module` (string): Module class name (from PW modules directory)
	 * - `downloaded` (bool): True if module was downloaded
	 * - `installed` (bool): True if module was installed
	 * - `source` (string): URL or local path of module ZIP file
	 * - `destination` (string): Destination directory/path for extracted module files
	 * - `messages` (array): Array of messages
	 * - `errors` (array): Array of errors
	 *
	 */
	public function download($module, array $options = array()) {
		
		$sanitizer = $this->wire()->sanitizer;
		$config = $this->wire()->config;
		$errors = [];
		$moduleLocation = '';
		$locationType = '';
		$install = !empty($options['install']);

		$result = [
			'module' => '',
			'downloaded' => false,
			'installed' => false,
			'source' => '',
			'destination' => '',
			'messages' => [],
			'errors' => [],
		];

		$paths = [
			$config->paths->cache,
			$config->paths->siteModules,
		];

		foreach($paths as $path) {
			if(is_writable($path)) continue;
			$errors[] = "The path $path is not writeable";
		}

		if(!class_exists('ZipArchive')) {
			$errors[] = 'ZipArchive class is required and PHP does not have it enabled';
		}
		
		if(count($errors)) {
			// cannot proceed
		} else if($sanitizer->name($module) === $module) {
			// download from directory
			$locationType = 'directory';
			$result['module'] = $module;
		} else if(strpos($module, '://') !== false) {
			// download from URL
			$moduleLocation = $module;
			$locationType = 'url';
		} else if(strpos($module, DIRECTORY_SEPARATOR) !== false || strtolower(pathinfo($module, PATHINFO_EXTENSION)) === 'zip') {
			// download from local path
			if(strtolower(pathinfo($module, PATHINFO_EXTENSION)) !== 'zip') {
				$errors[] = "Module file must be a .zip file: $module";
			} else if(file_exists($module)) {
				// great
				$moduleLocation = $module;
				$locationType = 'path';
			} else {
				$errors[] = "Module file not found: $module";
			}
		} else {
			$errors[] = "Invalid module name or URL: $module";
		}

		if(count($errors)) {
			$result['errors'] = $errors;

		} else if($locationType === 'directory') {
			// download from ProcessWire modules directory
			$this->downloadFromDirectory($module, $result, $options);

		} else if($locationType === 'url') {
			// download from zip file at http URL
			$this->downloadFromUrl($moduleLocation, $result, $options);

		} else if($locationType === 'path') {
			// download from local disk path
			$this->downloadFromLocalPath($moduleLocation, $result, $options);
		}

		if($install && $result['downloaded'] && $result['module']) {
			$installed = $this->modules->install($result['module']);
			$result['installed'] = (bool) $installed;
			if($installed) {
				$result['messages'][] = "Installed module: $result[module]";
			} else {
				$result['errors'][] = "Unable to install module: $result[module]";
			}
		}

		return $result;
	}

	/**
	 * Download module from the ProcessWire modules directory
	 *
	 * @param string $moduleName
	 * @param array $result
	 * @param array $options
	 * @return void
	 *
	 */
	protected function downloadFromDirectory($moduleName, array &$result, array $options = array()) {
		$directoryInfo = $this->getModuleDirectoryInfo($moduleName);
		if(empty($directoryInfo['download_url'])) {
			$error = empty($directoryInfo['error']) ? "Module directory has no download URL for: $moduleName" : $directoryInfo['error'];
			$result['errors'][] = $error;
			return;
		}
		if(empty($options['destinationDir'])) {
			$options['destinationDir'] = $this->wire()->config->paths->siteModules . $moduleName . '/';
		}
		$this->downloadFromUrl($directoryInfo['download_url'], $result, $options);
	}

	/**
	 * Download module from ZIP URL
	 *
	 * @param string $url
	 * @param array $result
	 * @param array $options
	 * @return void
	 *
	 */
	protected function downloadFromUrl($url, array &$result, array $options = array()) {
		if(!preg_match('{^https?://}i', $url)) {
			$result['errors'][] = "Invalid download URL specified: $url";
			return;
		}

		$tempDir = $this->getTempDir();
		$tempName = 'module-temp.zip';
		if(preg_match('/([-._a-z0-9]+\.zip)$/i', $url, $matches)) $tempName = $matches[1];
		$tempZIP = $tempDir . $tempName;
		$http = $this->wire(new WireHttp());

		try {
			$file = $http->download($url, $tempZIP);
			$result['source'] = $url;
			$result['messages'][] = sprintf('Downloaded ZIP file: %s (%d bytes)', $url, filesize($file));
			$this->unzipModule($file, $result, $options, true);
		} catch(\Exception $e) {
			$result['errors'][] = $e->getMessage();
			$this->wire()->files->unlink($tempZIP);
		}
	}

	/**
	 * Download module from local ZIP path
	 *
	 * @param string $path
	 * @param array $result
	 * @param array $options
	 * @return void
	 *
	 */
	protected function downloadFromLocalPath($path, array &$result, array $options = array()) {
		$result['source'] = $path;
		$this->unzipModule($path, $result, $options, false);
	}

	/**
	 * Returns a temporary directory (path) for use by this object
	 *
	 * @return string
	 *
	 */
	public function getTempDir() {
		if(empty($this->tempDir)) $this->tempDir = $this->wire(new WireTempDir($this));
		return $this->tempDir->get();
	}

	/**
	 * Get module information from the ProcessWire modules directory
	 *
	 * @param string $moduleName
	 * @return array
	 *
	 */
	public function getModuleDirectoryInfo($moduleName) {
		$config = $this->wire()->config;
		$sanitizer = $this->wire()->sanitizer;
		$moduleName = $sanitizer->name($moduleName);
		$url = trim($config->moduleServiceURL, '/') . "/$moduleName/?apikey=" . $sanitizer->name($config->moduleServiceKey);
		$http = $this->wire(new WireHttp());
		$data = $http->get($url);

		if(empty($data)) {
			return [ 'status' => 'error', 'error' => 'Error retrieving data from web service URL - ' . $http->getError() ];
		}

		$data = json_decode($data, true);
		if(empty($data)) {
			return [ 'status' => 'error', 'error' => 'Error decoding JSON from web service' ];
		}
		if($data['status'] !== 'success') {
			$error = isset($data['error']) ? $data['error'] : 'Unknown error reported by web service';
			return [ 'status' => 'error', 'error' => "Error reported by web service: $error" ];
		}

		return $data;
	}

	/**
	 * Get local and remote update information for module
	 *
	 * @param string $moduleName
	 * @return array
	 *
	 */
	public function getModuleUpdateInfo($moduleName) {
		$modules = $this->modules;
		$config = $this->wire()->config;
		$moduleName = $this->wire()->sanitizer->name($moduleName);
		$info = $modules->getModuleInfoVerbose($moduleName);
		$file = empty($info['file']) ? '' : $info['file'];
		$localVersion = empty($info['versionStr']) ? '' : $info['versionStr'];
		$siteModulesPath = $config->paths->siteModules;
		$inSiteModules = $file && strpos($file, $siteModulesPath) === 0;

		if(!$localVersion && !empty($info['version'])) {
			$localVersion = $modules->formatVersion($info['version']);
		}

		$result = array(
			'module' => $moduleName,
			'installed' => $modules->isInstalled($moduleName),
			'site' => $inSiteModules,
			'file' => $file,
			'localVersion' => $localVersion,
			'remoteVersion' => '',
			'hasUpdate' => false,
			'downloadUrl' => '',
			'remote' => array(),
			'errors' => array(),
		);

		if(!$moduleName) {
			$result['errors'][] = 'Module name is required';
			return $result;
		}
		if(!$result['installed']) {
			$result['errors'][] = "Module is not installed: $moduleName";
			return $result;
		}
		if(!$inSiteModules) {
			$result['errors'][] = "Module is not installed in /site/modules/: $moduleName";
			return $result;
		}

		$remote = $this->getModuleDirectoryInfo($moduleName);
		$result['remote'] = $remote;

		if(empty($remote['status']) || $remote['status'] !== 'success') {
			$result['errors'][] = empty($remote['error']) ? "Unable to retrieve module directory info for: $moduleName" : $remote['error'];
			return $result;
		}

		$result['remoteVersion'] = empty($remote['module_version']) ? '' : $remote['module_version'];
		$result['downloadUrl'] = empty($remote['download_url']) ? '' : $remote['download_url'];

		if(!$result['remoteVersion']) {
			$result['errors'][] = "Module directory has no version for: $moduleName";
		} else if(!$localVersion) {
			$result['errors'][] = "Unable to determine local module version for: $moduleName";
		} else {
			$result['hasUpdate'] = version_compare($localVersion, $result['remoteVersion'], '<');
		}

		if(!$result['downloadUrl']) {
			$result['errors'][] = "Module directory has no download URL for: $moduleName";
		}

		return $result;
	}

	/**
	 * Get update information for site modules
	 *
	 * @param string $moduleName Optional module name, or omit for all installed site modules
	 * @param bool $updatesOnly Return only modules with updates available when $moduleName omitted
	 * @return array
	 *
	 */
	public function getModuleUpdates($moduleName = '', $updatesOnly = true) {
		if($moduleName) return $this->getModuleUpdateInfo($moduleName);

		$updates = array();
		foreach($this->modules->getArray() as $module) {
			if(!$module instanceof Module) continue;
			$name = $module->className();
			$info = $this->getModuleUpdateInfo($name);
			if($updatesOnly && empty($info['hasUpdate'])) continue;
			$updates[$name] = $info;
		}

		return $updates;
	}

	/**
	 * Download update for installed site module
	 *
	 * @param string $moduleName
	 * @param array $options Options: force (bool)
	 * @return array
	 *
	 */
	public function updateModule($moduleName, array $options = array()) {
		$force = !empty($options['force']);
		$updateInfo = $this->getModuleUpdateInfo($moduleName);
		$result = array(
			'module' => $updateInfo['module'],
			'updated' => false,
			'fromVersion' => $updateInfo['localVersion'],
			'toVersion' => $updateInfo['remoteVersion'],
			'download' => array(),
			'messages' => array(),
			'errors' => array(),
		);

		if(count($updateInfo['errors'])) {
			$result['errors'] = $updateInfo['errors'];
			return $result;
		}
		if(!$updateInfo['hasUpdate'] && !$force) {
			$result['errors'][] = "Module is already up-to-date: $updateInfo[module] ($updateInfo[localVersion])";
			return $result;
		}

		$destinationDir = dirname($updateInfo['file']) . '/';
		$download = $this->download($updateInfo['downloadUrl'], array(
			'destinationDir' => $destinationDir
		));

		$result['download'] = $download;
		$result['messages'] = $download['messages'];
		$result['errors'] = $download['errors'];
		$result['updated'] = $download['downloaded'] && !count($download['errors']);

		if($result['updated']) {
			$result['messages'][] = "Updated module: $result[module] ($result[fromVersion] => $result[toVersion])";
		}

		return $result;
	}

	/**
	 * Find all module files recursively in path
	 *
	 * @param string $path Omit to use the default /site/modules/
	 * @param int $maxLevel Max depth to pursue module files in
	 * @return array
	 *
	 */
	public function findModuleFiles($path = '', $maxLevel = 4) {
		static $level = 0;
		$level++;
		$files = array();

		if(!$path) $path = $this->wire()->config->paths->siteModules;

		foreach(new \DirectoryIterator($path) as $file) {
			if($file->isDot()) continue;
			if(substr($file->getBasename(), 0, 1) == '.') continue;

			if($file->isDir() && $level < $maxLevel) {
				$_files = $this->findModuleFiles($file->getPathname(), $maxLevel);
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
	 * Given a list of files from a module ZIP file, return the recommended directory name
	 *
	 * @param array $files
	 * @param string $modulePath
	 * @return bool|string
	 *
	 */
	public function determineDestinationDir(array $files, $modulePath = '') {
		$moduleFiles = array();
		$moduleFiles1 = array();
		$moduleDirs = array();
		$moduleDir = '';

		if(!$modulePath) $modulePath = $this->wire()->config->paths->siteModules;
		$tempDir = $this->getTempDir();

		foreach($files as $f) {
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

		if(!count($moduleFiles)) return false;

		$numSlashes = 0;
		while(!count($moduleFiles1) && $numSlashes < 5) {
			foreach($moduleFiles as $name => $file) {
				if(substr_count($file, '/') == $numSlashes) $moduleFiles1[$name] = $name;
			}
			$numSlashes++;
		}

		if(count($moduleFiles1) == 1) {
			reset($moduleFiles1);
			$moduleDir = key($moduleFiles1);
			return $modulePath . $moduleDir . '/';
		}

		$moduleFilesAll = $this->findModuleFiles($modulePath);
		foreach($moduleFiles1 as $name) {
			if(isset($moduleFilesAll[$name])) {
				$moduleDir = dirname($moduleFilesAll[$name]);
				$moduleDir = basename($moduleDir);
				if($moduleDir == 'modules') $moduleDir = '';
			}
		}
		if($moduleDir) return $modulePath . $moduleDir . '/';

		$sorted = array();
		foreach($moduleFiles1 as $name => $file) {
			$len = strlen($name);
			while(isset($sorted[$len])) $len++;
			$sorted[$len] = $name;
		}
		krsort($sorted);
		$moduleFiles1 = array();
		foreach($sorted as $name) $moduleFiles1[$name] = $name;

		$extractedDir = trim($files[0], '/');
		if(is_dir($tempDir . $extractedDir) && $extractedDir[0] != '.') {
			if(preg_match('/^([A-Z][a-zA-Z0-9_]+)(-[a-z0-9]*)?$/', $extractedDir, $matches)) {
				$extractedDir = $matches[1];
				foreach($moduleFiles1 as $name => $file) {
					if($name == $extractedDir) $moduleDir = $name;
				}
				if(!$moduleDir) foreach($moduleFiles1 as $name => $file) {
					if(strpos($name, $extractedDir) === 0) {
						$moduleDir = $extractedDir;
						break;
					}
				}
				if(!$moduleDir) return $modulePath . $extractedDir . '/';
			}
		}

		$moduleDir = end($moduleFiles1);
		return $modulePath . $moduleDir . '/';
	}

	/**
	 * Unzip module file to temp dir and copy to destination directory
	 *
	 * @param string $zipFile
	 * @param array $result
	 * @param array $options
	 * @param bool $deleteZip
	 * @return bool|string
	 *
	 */
	public function unzipModule($zipFile, array &$result = array(), array $options = array(), $deleteZip = false) {
		$config = $this->wire()->config;
		$success = false;
		$tempDir = $this->getTempDir();
		$mkdirDestination = false;
		$fileTools = $this->wire()->files;
		$destinationDir = empty($options['destinationDir']) ? '' : $options['destinationDir'];

		try {
			$files = $fileTools->unzip($zipFile, $tempDir, [
				'requireFiles' => [ '![a-zA-Z0-9]+\.(module|module\.php)$!' ]
			]);
			if($deleteZip && is_file($zipFile)) $fileTools->unlink($zipFile, true);
			$qty = count($files);
			if($qty < 100 && $config->debug) {
				foreach($files as $f) $result['messages'][] = "Extracted: $f";
			} else {
				$result['messages'][] = "Extracted $qty " . ($qty === 1 ? 'file' : 'files');
			}
		} catch(\Exception $e) {
			$result['errors'][] = $e->getMessage();
			if($deleteZip && is_file($zipFile)) $fileTools->unlink($zipFile, true);
			return false;
		}

		$moduleNames = $this->getModuleNamesFromFiles($files);
		if(!$result['module'] && count($moduleNames) === 1) $result['module'] = reset($moduleNames);

		if(!$destinationDir) {
			$destinationDir = $this->determineDestinationDir($files);
			if(!$destinationDir) {
				$result['errors'][] = 'Unable to find any module files';
				return false;
			}
		}
		$result['messages'][] = "Destination directory: $destinationDir";

		$files0 = trim($files[0], '/');
		$extractedDir = is_dir("$tempDir/$files0") && substr($files0, 0, 1) != '.' ? "$files0/" : "";

		if(is_dir($destinationDir)) {
			$hasBackup = $this->backupDir($destinationDir, $result);
			if($hasBackup) $fileTools->mkdir($destinationDir, true);
		} else {
			if($fileTools->mkdir($destinationDir, true)) $mkdirDestination = true;
			$hasBackup = false;
		}

		$dirLabel = str_replace($config->paths->root, '/', $destinationDir);

		if(is_dir($destinationDir)) {
			$from = $tempDir . $extractedDir;
			if($fileTools->copy($from, $destinationDir)) {
				$result['messages'][] = "Successfully copied files to new directory: $dirLabel";
				$fileTools->chmod($destinationDir, true);
				$success = true;
			} else {
				$result['errors'][] = "Unable to copy files to new directory: $dirLabel";
				if($hasBackup) $this->restoreDir($destinationDir, $result);
			}
		} else {
			$result['errors'][] = "Could not create directory: $dirLabel";
		}

		if(!$success) {
			$result['errors'][] = "Unable to copy module files: $dirLabel";
			if($mkdirDestination && !$fileTools->rmdir($destinationDir, true)) {
				$this->error("Could not delete failed module dir: $destinationDir", Notice::log);
			}
		}

		if($success) {
			$result['downloaded'] = true;
			$result['destination'] = $destinationDir;
			$this->modules->refresh();
		}

		return $success ? $destinationDir : false;
	}

	/**
	 * Return module class names found in extracted ZIP file list
	 *
	 * @param array $files
	 * @return array
	 *
	 */
	protected function getModuleNamesFromFiles(array $files) {
		$names = array();
		foreach($files as $file) {
			if(preg_match('{^(.*?/|)([A-Z][a-zA-Z0-9_]+)\.module(?:\.php)?$}', $file, $matches)) {
				$names[$matches[2]] = $matches[2];
			}
		}
		return $names;
	}

	/**
	 * Create a backup of a module directory
	 *
	 * @param string $moduleDir
	 * @param array $result
	 * @return bool
	 *
	 */
	protected function backupDir($moduleDir, array &$result = array()) {
		$files = $this->wire()->files;
		$config = $this->wire()->config;
		$dir = rtrim($moduleDir, '/');
		$name = basename($dir);
		$parentDir = dirname($dir);
		$backupDir = "$parentDir/.$name/";
		if(is_dir($backupDir)) $files->rmdir($backupDir, true);
		$success = false;

		if(is_link(rtrim($moduleDir, '/'))) {
			$success = $files->copy($moduleDir, $backupDir);
			unlink(rtrim($moduleDir, '/'));
			$dir = str_replace($config->paths->root, '/', $moduleDir);
			$result['messages'][] = "Please note that $dir was a symbolic link and has been converted to a regular directory";
		} else {
			if($files->rename($moduleDir, $backupDir)) $success = true;
		}

		if($success) {
			$result['messages'][] = "Backed up existing $name => " . str_replace($config->paths->root, '/', $backupDir);
			return true;
		}

		return false;
	}

	/**
	 * Restore a module directory
	 *
	 * @param string $moduleDir
	 * @param array $result
	 * @return bool
	 *
	 */
	protected function restoreDir($moduleDir, array &$result = array()) {
		$dir = rtrim($moduleDir, '/');
		$name = basename($dir);
		$parentDir = dirname($dir);
		$backupDir = "$parentDir/.$name/";
		if(is_dir($backupDir)) {
			$this->wire()->files->rmdir($moduleDir, true);
			if(rename($backupDir, $moduleDir)) {
				$result['messages'][] = "Restored backup of $name => $moduleDir";
				return true;
			}
		}
		return false;
	}
}
