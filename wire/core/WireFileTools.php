<?php namespace ProcessWire;

/**
 * ProcessWire File Tools ($files API variable)
 * 
 * #pw-summary Helpers for working with files and directories. 
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 *
 * @method bool include($filename, array $vars = array(), array $options = array())
 *
 */

class WireFileTools extends Wire {
	
	/**
	 * Create a directory that is writable to ProcessWire and uses the defined $config chmod settings
	 * 
	 * Unlike PHP's `mkdir()` function, this function manages the read/write mode consistent with ProcessWire's
	 * setting `$config->chmodDir`, and it can create directories recursively. Meaning, if you want to create directory /a/b/c/ 
	 * and directory /a/ doesn't yet exist, this method will take care of creating /a/, /a/b/, and /a/b/c/. 
	 * 
	 * The `$recursive` and `$chmod` arguments may optionally be swapped (since 3.0.34).
	 * 
	 * ~~~~~
	 * // Create a new directory in ProcessWire's cache dir
	 * if($files->mkdir($config->paths->cache . 'foo-bar/')) {
	 *   // directory created: /site/assets/cache/foo-bar/
	 * }
	 * ~~~~~
	 * 
	 * @param string $path Directory you want to create
	 * @param bool|string $recursive If set to true, all directories will be created as needed to reach the end.
	 * @param string|null|bool $chmod Optional mode to set directory to (default: $config->chmodDir), format must be a string i.e. "0755"
	 *   If omitted, then ProcessWire's `$config->chmodDir` setting is used instead.
	 * @return bool True on success, false on failure
	 *
	 */
	public function mkdir($path, $recursive = false, $chmod = null) {
		if(!strlen($path)) return false;
		
		if(is_string($recursive) && strlen($recursive) > 2) {
			// chmod argument specified as $recursive argument or arguments swapped
			$_chmod = $recursive;
			$recursive = is_bool($chmod) ? $chmod : false;
			$chmod = $_chmod;
		}
		
		if(!is_dir($path)) {
			if($recursive) {
				$parentPath = substr($path, 0, strrpos(rtrim($path, '/'), '/'));
				if(!is_dir($parentPath) && !$this->mkdir($parentPath, true, $chmod)) return false;
			}
			if(!@mkdir($path)) return false;
		}
		$this->chmod($path, false, $chmod);
		return true;
	}

	/**
	 * Remove a directory and optionally everything within it (recursively)
	 * 
	 * Unlike PHP's `rmdir()` function, this method provides a recursive option, which can be enabled by specifying true 
	 * for the `$recursive` argument. You should be careful with this option, as it can easily wipe out an entire 
	 * directory tree in a flash. 
	 * 
	 * Note that the $options argument was added in 3.0.118.
	 * 
	 * ~~~~~
	 * // Remove directory /site/assets/cache/foo-bar/ and everything in it
	 * $files->rmdir($config->paths->cache . 'foo-bar/', true);
	 * 
	 * // Remove directory after ensuring $pathname is somewhere within /site/assets/
	 * $files->rmdir($pathname, true, [ 'limitPath' => $config->paths->assets ]); 
	 * ~~~~~
	 * 
	 * @param string $path Path/directory you want to remove
	 * @param bool $recursive If set to true, all files and directories in $path will be recursively removed as well (default=false). 
	 * @param array|bool|string $options Optional settings to adjust behavior or (bool|string) for limitPath option:
	 *  - `limitPath` (string|bool|array): Must be somewhere within given path, boolean true for site assets, or false to disable (default=false).
	 *  - `throw` (bool): Throw verbose WireException (rather than return false) when potentially consequential fail (default=false). 
	 * @return bool True on success, false on failure
	 *
	 */
	public function rmdir($path, $recursive = false, $options = array()) { 
		
		$defaults = array(
			'limitPath' => false, 
			'throw' => false, 
		);
		
		if(!is_array($options)) $options = array('limitPath' => $options);
		$options = array_merge($defaults, $options);
		
		// if there's nothing to remove, exit now
		if(!is_dir($path)) return false;

		// verify that path is allowed for this operation
		if(!$this->allowPath($path, $options['limitPath'], $options['throw'])) return false;
	
		// handle recursive rmdir
		if($recursive === true) {
			$files = @scandir($path);
			if(is_array($files)) foreach($files as $file) {
				if($file == '.' || $file == '..' || strpos($file, '..') !== false) continue;
				$pathname = rtrim($path, '/') . '/' . $file;
				if(is_dir($pathname)) {
					$this->rmdir($pathname, $recursive, $options);
				} else {
					$this->unlink($pathname, $options['limitPath'], $options['throw']);
				}
			}
		}
		
		return @rmdir($path);
	}


	/**
	 * Change the read/write mode of a file or directory, consistent with ProcessWire's configuration settings
	 * 
	 * Unless a specific mode is provided via the `$chmod` argument, this method uses the `$config->chmodDir`
	 * and `$config->chmodFile` settings in /site/config.php. 
	 * 
	 * This method also provides the option of going recursive, adjusting the read/write mode for an entire
	 * file/directory tree at once. 
	 * 
	 * The `$recursive` or `$chmod` arguments may be optionally swapped in order (since 3.0.34).
	 * 
	 * ~~~~~
	 * // Update the mode of /site/assets/cache/foo-bar/ recursively
	 * $files->chmod($config->paths->cache . 'foo-bar/', true); 
	 * ~~~~~
	 * 
	 * @param string $path Path or file that you want to adjust mode for (may be a path/directory or a filename).
	 * @param bool|string $recursive If set to true, all files and directories in $path will be recursively set as well (default=false). 
	 * @param string|null|bool $chmod If you want to set the mode to something other than ProcessWire's chmodFile/chmodDir settings,
	 * you may override it by specifying it here. Ignored otherwise. Format should be a string, like "0755".
	 * @return bool Returns true if all changes were successful, or false if at least one chmod failed.
	 * @throws WireException when it receives incorrect chmod format
	 *
	 */
	public function chmod($path, $recursive = false, $chmod = null) {
		
		if(is_string($recursive) && strlen($recursive) > 2) {
			// chmod argument specified as $recursive argument or arguments swapped
			$_chmod = $recursive;
			$recursive = is_bool($chmod) ? $chmod : false;
			$chmod = $_chmod; 
		}

		if(is_null($chmod)) {
			// default: pull values from PW config
			$chmodFile = $this->wire('config')->chmodFile;
			$chmodDir = $this->wire('config')->chmodDir;
		} else {
			// optional, manually specified string
			if(!is_string($chmod)) throw new WireException("chmod must be specified as a string like '0755'");
			$chmodFile = $chmod;
			$chmodDir = $chmod;
		}

		$numFails = 0;

		if(is_dir($path)) {
			// $path is a directory
			if($chmodDir) if(!@chmod($path, octdec($chmodDir))) $numFails++;

			// change mode of files in directory, if recursive
			if($recursive) foreach(new \DirectoryIterator($path) as $file) {
				if($file->isDot()) continue;
				$mod = $file->isDir() ? $chmodDir : $chmodFile;
				if($mod) if(!@chmod($file->getPathname(), octdec($mod))) $numFails++;
				if($file->isDir()) {
					if(!$this->chmod($file->getPathname(), true, $chmod)) $numFails++;
				}
			}
		} else {
			// $path is a file
			$mod = $chmodFile;
			if($mod) if(!@chmod($path, octdec($mod))) $numFails++;
		}

		return $numFails == 0;
	}

	/**
	 * Copy all files recursively from one directory ($src) to another directory ($dst)
	 *
	 * Unlike PHP's `copy()` function, this method performs a recursive copy by default, 
	 * ensuring that all files and directories in the source ($src) directory are duplicated
	 * in the destination ($dst) directory. 
	 * 
	 * This method can also be used to copy single files. If a file is specified for $src, and
	 * only a path is specified for $dst, then the original filename will be retained in $dst.
	 * 
	 * ~~~~~
	 * // Copy everything from /site/assets/cache/foo/ to /site/assets/cache/bar/
	 * $copyFrom = $config->paths->cache . "foo/";
	 * $copyTo = $config->paths->cache . "bar/";
	 * $files->copy($copyFrom, $copyTo); 
	 * ~~~~~
	 * 
	 * @param string $src Path to copy files from, or filename to copy. 
	 * @param string $dst Path (or filename) to copy file(s) to. Directory is created if it doesn't already exist.
	 * @param bool|array $options Array of options: 
	 *  - `recursive` (boolean): Whether to copy directories within recursively. (default=true)
	 *  - `allowEmptyDirs` (boolean): Copy directories even if they are empty? (default=true)
	 *  - `limitPath` (bool|string|array): Limit copy to within path given here, or true for site assets path (default=false).
	 *  - Note that the limitPath option was added in 3.0.118. 
	 *  - If a boolean is specified for $options, it is assumed to be the `recursive` option.
	 * @return bool True on success, false on failure.
	 * @throws WireException if `limitPath` option is used and either $src or $dst is not allowed
	 *
	 */
	public function copy($src, $dst, $options = array()) {

		$defaults = array(
			'recursive' => true,
			'allowEmptyDirs' => true,
			'limitPath' => false, 
		);

		if(is_bool($options)) $options = array('recursive' => $options);
		$options = array_merge($defaults, $options);
	
		if($options['limitPath'] !== false) {
			$this->allowPath($src, $options['limitPath'], true);
			$this->allowPath($dst, $options['limitPath'], true);
		}
		
		if(!is_dir($src)) {
			// just copy a file
			if(!file_exists($src)) return false;
			if(is_dir($dst)) {
				// if only a directory was specified for $dst, then keep same filename but in new dir
				$dir = rtrim($dst, '/');
				$dst .= '/' . basename($src);
			} else {
				$dir = dirname($dst);
			}
			if(!is_dir($dir)) $this->mkdir($dir);
			if(!copy($src, $dst)) return false;
			$this->chmod($dst);
			return true;
		}

		if(substr($src, -1) != '/') $src .= '/';
		if(substr($dst, -1) != '/') $dst .= '/';

		$dir = opendir($src);
		if(!$dir) return false;

		if(!$options['allowEmptyDirs']) {
			$isEmpty = true;
			while(false !== ($file = readdir($dir))) {
				if($file == '.' || $file == '..') continue;
				$isEmpty = false;
				break;
			}
			if($isEmpty) return true;
		}

		if(!$this->mkdir($dst)) return false;

		while(false !== ($file = readdir($dir))) {
			if($file == '.' || $file == '..') continue;
			$isDir = is_dir($src . $file);
			if($options['recursive'] && $isDir) {
				$this->copy($src . $file, $dst . $file, $options);
			} else if($isDir) {
				// skip it, because not recursive
			} else {
				copy($src . $file, $dst . $file);
				$this->chmod($dst . $file);
			}
		}

		closedir($dir);
		
		return true;
	}

	/**
	 * Unlink/delete file with additional protections relative to PHP unlink()
	 * 
	 * - This method requires a full pathname to a file to unlink and does not 
	 *   accept any kind of relative path traversal. 
	 * 
	 * - This method will only unlink files in /site/assets/ if you specify `true` 
	 *   for the `$limitPath` option (recommended).
	 * 
	 * @param string $filename
	 * @param string|bool $limitPath Limit only to files within some starting path? (default=false) 
	 *  - Boolean true to limit unlink operations to somewhere within /site/assets/ (only known always writable path).
	 *  - Boolean false to disable to security feature. (default)
	 *  - An alternative path (string) that represents the starting path (full disk path) to limit deletions to. 
	 *  - An array with multiple of the above string option. 
	 * @param bool $throw Throw exception on error?
	 * @return bool True on success, false on fail
	 * @throws WireException If file is not allowed to be removed or unlink fails
	 * @since 3.0.118
	 * 
	 */
	public function unlink($filename, $limitPath = false, $throw = false) {
		
		if(!$this->allowPath($filename, $limitPath, $throw)) {
			// path not allowed
			return false;
		}
		
		if(!is_file($filename) && !is_link($filename)) {
			// only files or links (that exist) can be deleted
			return false;
		}
		
		if(@unlink($filename)) {
			return true;
		} else {
			if($throw) throw new WireException("Unable to unlink file $filename");
			return false;
		}
	}

	/**
	 * Rename a file or directory and update permissions
	 * 
	 * Note that this method will fail if pathname given by $newName argument already exists. 
	 * 
	 * @param string $oldName Old pathname, must be full disk path. 
	 * @param string $newName New pathname, must be full disk path OR can be basename to assume same path as $oldName. 
	 * @param array|bool|string $options Options array to modify behavior or substitute `limitPath` (bool or string) option here.
	 *  - `limitPath` (bool|string|array): Limit renames to within this path, or boolean TRUE for site/assets, or FALSE to disable (default=false).
	 *  - `throw` (bool): Throw WireException with verbose details on error? (default=false)
	 *  - `chmod` (bool): Adjust permissions to be consistent with $config after rename? (default=true)
	 *  - If given a bool or string for $options the `limitPath` option is assumed. 
	 * @return bool True on success, false on fail (or WireException if throw option specified). 
	 * @throws WireException If error occurs and $throw argument was true.
	 * @since 3.0.118
	 * 
	 */
	public function rename($oldName, $newName, $options = array()) {
		
		$defaults = array(
			'limitPath' => false,
			'throw' => false, 
			'chmod' => true, 
		);
		
		if(!is_array($options)) $options = array('limitPath' => $options);
		$options = array_merge($defaults, $options);
	
		// if only basename was specified for the newName then use path from oldName
		if(basename($newName) === $newName) {
			$newName = dirname($oldName) . '/' . $newName;
		}
		
		try {
			$this->allowPath($oldName, $options['limitPath'], true);
		} catch(\Exception $e) {
			if($options['throw']) throw new WireException("Rename oldName path invalid: " . $e->getMessage());
			return false;
		}
		
		try {
			$this->allowPath($newName, $options['limitPath'], true);
		} catch(\Exception $e) {
			if($options['throw']) throw new WireException("Rename newName path invalid: " . $e->getMessage());
			return false;
		}
		
		if(!file_exists($oldName)) {
			if($options['throw']) throw new WireException("Rename given pathname (oldName) that does not exist: $oldName");
			return false;
		}
		
		if(file_exists($newName)) {
			if($options['throw']) throw new WireException("Rename to pathname (newName) that already exists: $newName");
			return false;
		}
		
		if(!rename($oldName, $newName)) {
			if($options['throw']) throw new WireException("Rename failed: $oldName => $newName");
		}
	
		if($options['chmod']) {
			$this->chmod($newName);
		}
		
		return true;
	}
	
	/**
	 * Allow path or filename to to be used for file manipulation actions?
	 * 
	 * Given path must be a full path (no relative references). If given a $limitPath, it must be a 
	 * directory that already exists. 
	 * 
	 * Note that this method does not indicate whether or not the given pathname exists, only that it is allowed.
	 * As a result this can be used for checking a path before creating something in it too. 
	 * 
	 * #pw-internal
	 * 
	 * @param string $pathname File or directory name to check
	 * @param bool|string|array $limitPath Any one of the following (default=false): 
	 *  - Full disk path (string) that $pathname must be within (whether directly or in subdirectory of).
	 *  - Array of the above.
	 *  - Boolean false to disable (default).
	 *  - Boolean true for site assets path, which is the only known always-writable path in PW. 
	 * @param bool $throw Throw verbose exceptions on error? (default=false).
	 * @return bool True if given pathname allowed, false if not.
	 * @throws WireException when $throw argument is true and function would otherwise return false. 
	 * @since 3.0.118
	 * 
	 */
	public function allowPath($pathname, $limitPath = false, $throw = false) {
		
		if(is_array($limitPath)) {
			// run allowPath() for each of the specified limitPaths
			$allow = false;	
			foreach($limitPath as $dir) {
				if(!is_string($dir) || empty($dir)) continue;
				$allow = $this->allowPath($pathname, $dir, false);
				if($allow) break; // found one that is allowed
			}
			if(!$allow && $throw) {
				throw new WireException("Given pathname is not within any of the paths allowed by limitPath");
			}
			return $allow;
			
		} else if($limitPath === true) {
			// default limitPath
			$limitPath = $this->wire('config')->paths->assets;
			
		} else if($limitPath === false) {
			// no limitPath in use	
			
		} else if(empty($limitPath) || !is_string($limitPath)) { 
			// invalid limitPath argument (wrong type or path does not exist)
			if($throw) throw new WireException("Invalid type for limitPath argument");
			return false;
			
		} else if(!is_dir($limitPath)) {
			if($throw) throw new WireException("$limitPath (limitPath) does not exist");
			return false;
		}
			
		if($limitPath !== false) try {
			// if limitPath can't pass allowPath then neither can $pathname
			$this->allowPath($limitPath, false, true);
		} catch(\Exception $e) {
			if($throw) throw new WireException("Validating limitPath reported: " . $e->getMessage());
			return false;
		}
		
		if(DIRECTORY_SEPARATOR != '/') {
			$pathname = str_replace(DIRECTORY_SEPARATOR, '/', $pathname);
			if(is_string($limitPath)) $limitPath = str_replace(DIRECTORY_SEPARATOR, '/', $limitPath);
			$testname = $pathname;
			if(strpos($pathname, ':')) list(,$testname) = explode(':', $pathname, 2); // reduce to no drive letter, if present
		} else {
			$testname = $pathname;
		}
		
		if(!strlen(trim($testname, '/.')) || substr_count($testname, '/') < 2) {
			// do not allow paths that consist of nothing but slashes and/or dots
			// and do not allow paths off root or lacking absolute path reference
			if($throw) throw new WireException("pathname not allowed: $pathname");
			return false; 
		}
		
		if(strpos($pathname, '..') !== false) {
			// not allowed to traverse anywhere
			if($throw) throw new WireException('pathname may not traverse “../”');
			return false;
		}
		
		if(strpos($pathname, '.') === 0 || empty($pathname)) {
			if($throw) throw new WireException('pathname may not begin with “.”');
			return false;
		}

		$pos = strpos($pathname, '//');
		if($pos !== false && $pos !== strpos($this->wire('config')->paths->assets, '//')) {
			// URLs or accidental extra slashes not allowed, unless they also appear in a known safe system path
			if($throw) throw new WireException('pathname may not contain double slash “//”');
			return false;
		}

		if($limitPath !== false && strpos($pathname, $limitPath) !== 0) {
			// disallow paths that do not begin with limitPath (i.e. /path/to/public_html/site/assets/)
			if($throw) throw new WireException("Given pathname is not within $limitPath (limitPath)");
			return false;
		}
		
		return true;
	}

	/**
	 * Return a new temporary directory/path ready to use for files
	 * 
	 * The directory will be automatically removed after a set period of time (default=120s)
	 * 
	 * #pw-advanced
	 * 
	 * ~~~~~
	 * $td = $files->tempDir('hello-world'); 
	 * $path = (string) $td; // or use $td->get();
	 * file_put_contents($path . 'some-file.txt', 'Hello world'); 
	 * ~~~~~
	 *
	 * @param Object|string $name Provide the object that needs the temp dir, or name your own string
	 * @param array|int $options Options array to modify default behavior:
	 *  - `maxAge` (integer): Maximum age of temp dir files in seconds (default=120)
	 *  - `basePath` (string): Base path where temp dirs should be created. Omit to use default (recommended).
	 *  - Note: if you specify an integer for $options, then 'maxAge' is assumed.
	 * @return WireTempDir If you typecast return value to a string, it is the temp dir path (with trailing slash).
	 * @see WireTempDir
	 *
	 */
	public function tempDir($name, $options = array()) {
		static $tempDirs = array();
		if(isset($tempDirs[$name])) return $tempDirs[$name];
		if(is_int($options)) $options = array('maxAge' => $options);
		$basePath = isset($options['basePath']) ? $options['basePath'] : '';
		$tempDir = new WireTempDir($name, $basePath);
		if(isset($options['maxAge'])) $tempDir->setMaxAge($options['maxAge']);
		$tempDirs[$name] = $tempDir;
		return $tempDir;
	}

	/**
	 * Find all files in the given $path recursively, and return a flat array of all found filenames
	 * 
	 * @param string $path Path to start from (required). 
	 * @param array $options Options to affect what is returned (optional):
	 *  - `recursive` (int): How many levels of subdirectories this method should descend into (default=10). 
	 *  - `extensions` (array): Only include files having these extensions, or omit to include all (default=[]).
	 *  - `excludeDirNames` (array): Do not descend into directories having these names (default=[]).
	 *  - `excludeHidden` (bool): Exclude hidden files? (default=false). 
	 *  - `returnRelative` (bool): Make returned array have filenames relative to given start $path? (default=false)
	 * @return array Flat array of filenames
	 * 
	 */
	public function find($path, array $options = array()) {

		$defaults = array(
			'recursive' => 10, 
			'extensions' => array(),
			'excludeExtensions' => array(), 
			'excludeDirNames' => array(),
			'excludeHidden' => false,
			'returnRelative' => false,
		);

		$path = $this->unixDirName($path);
		if(!is_dir($path) || !is_readable($path)) return array();

		$options = array_merge($defaults, $options);
		if(empty($options['_level'])) {
			// this is a non-recursive call
			$options['_startPath'] = $path;
			$options['_level'] = 0;
			foreach($options['extensions'] as $k => $v) $options['extensions'][$k] = strtolower($v);
		}
		$options['_level']++;
		if($options['recursive'] && $options['_level'] > $options['recursive']) return array();

		$dirs = array();
		$files = array();

		foreach(new \DirectoryIterator($path) as $file) {
			if($file->isDot()) continue;
			$basename = $file->getBasename();
			if($options['excludeHidden'] && strpos($basename, '.') === 0) continue;
			if($file->isDir()) {
				if(!in_array($basename, $options['excludeDirNames'])) $dirs[] = $file->getPathname();
				continue;
			}
			$ext = strtolower($file->getExtension());
			if(!empty($options['extensions']) && !in_array($ext, $options['extensions'])) continue;
			if(!empty($options['excludeExtensions']) && in_array($ext, $options['excludeExtensions'])) continue;
			$filename = $this->unixFileName($file->getPathname());
			// make relative to provided path
			if($options['returnRelative']) {
				$filename = str_replace($options['_startPath'], '', $filename);
			}
				
			$files[] = $filename;
		}

		sort($files);

		foreach($dirs as $dir) {
			$_files = $this->find($dir, $options);
			foreach($_files as $name) {
				$files[] = $name;
			}
		}

		$options['_level']--;

		return $files;
	}

	/**
	 * Unzips the given ZIP file to the destination directory
	 * 
	 * ~~~~~
	 * // Unzip a file 
	 * $zip = $config->paths->cache . "my-file.zip";
	 * $dst = $config->paths->cache . "my-files/";
	 * $items = $files->unzip($zip, $dst);
	 * if(count($items)) {
	 *   // $items is an array of filenames that were unzipped into $dst
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-archives
	 *
	 * @param string $file ZIP file to extract
	 * @param string $dst Directory where files should be unzipped into. Directory is created if it doesn't exist.
	 * @return array Returns an array of filenames (excluding $dst) that were unzipped.
	 * @throws WireException All error conditions result in WireException being thrown.
	 * @see WireFileTools::zip()
	 *
	 */
	public function unzip($file, $dst) {

		$dst = rtrim($dst, '/' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if(!class_exists('\ZipArchive')) throw new WireException("PHP's ZipArchive class does not exist");
		if(!is_file($file)) throw new WireException("ZIP file does not exist");
		if(!is_dir($dst)) $this->mkdir($dst, true);

		$names = array();
		$chmodFile = $this->wire('config')->chmodFile;
		$chmodDir = $this->wire('config')->chmodDir;

		$zip = new \ZipArchive();
		$res = $zip->open($file);
		if($res !== true) throw new WireException("Unable to open ZIP file, error code: $res");

		for($i = 0; $i < $zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);
			if(strpos($name, '..') !== false) continue;
			if($zip->extractTo($dst, $name)) {
				$names[$i] = $name;
				$filename = $dst . ltrim($name, '/');
				if(is_dir($filename)) {
					if($chmodDir) chmod($filename, octdec($chmodDir));
				} else if(is_file($filename)) {
					if($chmodFile) chmod($filename, octdec($chmodFile));
				}
			}
		}

		$zip->close();

		return $names;
	}

	/**
	 * Creates a ZIP file
	 * 
	 * ~~~~~
	 * // Create zip of all files in directory $dir to file $zip
	 * $dir = $config->paths->cache . "my-files/"; 
	 * $zip = $config->paths->cache . "my-file.zip";
	 * $result = $files->zip($zip, $dir); 
	 *  
	 * echo "<h3>These files were added to the ZIP:</h3>";
	 * foreach($result['files'] as $file) {
	 *   echo "<li>" $sanitizer->entities($file) . "</li>";
	 * }
	 * 
	 * if(count($result['errors'])) {
	 *   echo "<h3>There were errors:</h3>";
	 *   foreach($result['errors'] as $error) {
	 *     echo "<li>" . $sanitizer->entities($error) . "</li>";
	 *   }
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-archives
	 *
	 * @param string $zipfile Full path and filename to create or update (i.e. /path/to/myfile.zip)
	 * @param array|string $files Array of files to add (full path and filename), or directory (string) to add.
	 *   If given a directory, it will recursively add everything in that directory.
	 * @param array $options Associative array of options to modify default behavior:
	 *  - `allowHidden` (boolean or array): allow hidden files? May be boolean, or array of hidden files (basenames) you allow. (default=false)
	 *    Note that if you actually specify a hidden file in your $files argument, then that overrides this.
	 *  - `allowEmptyDirs` (boolean): allow empty directories in the ZIP file? (default=true)
	 *  - `overwrite` (boolean): Replaces ZIP file if already present (rather than adding to it) (default=false)
	 *  - `maxDepth` (int): Max dir depth 0 for no limit (default=0). Specify 1 to stay only in dirs listed in $files. 
	 *  - `exclude` (array): Files or directories to exclude
	 *  - `dir` (string): Directory name to prepend to added files in the ZIP
	 * @return array Returns associative array of:
	 *  - `files` (array): all files that were added
	 *  - `errors` (array): files that failed to add, if any
	 * @throws WireException Original ZIP file creation error conditions result in WireException being thrown.
	 * @see WireFileTools::unzip()
	 *
	 */
	public function zip($zipfile, $files, array $options = array()) {
		
		static $depth = 0;

		$defaults = array(
			'allowHidden' => false,
			'allowEmptyDirs' => true,
			'overwrite' => false,
			'maxDepth' => 0, 
			'exclude' => array(), // files or dirs to exclude
			'dir' => '',
			'zip' => null, // internal use: holds ZipArchive instance for recursive use
		);

		$return = array(
			'files' => array(),
			'errors' => array(),
		);
		
		if(!empty($options['zip']) && !empty($options['dir']) && $options['zip'] instanceof \ZipArchive) {
			// internal recursive call
			$recursive = true;
			$zip = $options['zip']; // ZipArchive instance

		} else if(is_string($zipfile)) {
			if(!class_exists('\ZipArchive')) throw new WireException("PHP's ZipArchive class does not exist");
			$options = array_merge($defaults, $options);
			$zippath = dirname($zipfile);
			if(!is_dir($zippath)) throw new WireException("Path for ZIP file ($zippath) does not exist");
			if(!is_writable($zippath)) throw new WireException("Path for ZIP file ($zippath) is not writable");
			if(empty($files)) throw new WireException("Nothing to add to ZIP file $zipfile");
			if(is_file($zipfile) && $options['overwrite'] && !$this->unlink($zipfile)) throw new WireException("Unable to overwrite $zipfile");
			if(!is_array($files)) $files = array($files);
			if(!is_array($options['exclude'])) $options['exclude'] = array($options['exclude']);
			$recursive = false;
			$zip = new \ZipArchive();
			if($zip->open($zipfile, \ZipArchive::CREATE) !== true) throw new WireException("Unable to create ZIP: $zipfile");

		} else {
			throw new WireException("Invalid zipfile argument");
		}

		$dir = strlen($options['dir']) ? rtrim($options['dir'], '/') . '/' : '';

		foreach($files as $file) {
			$basename = basename($file);
			$name = $dir . $basename;
			if($basename[0] == '.' && $recursive) {
				if(!$options['allowHidden']) continue;
				if(is_array($options['allowHidden']) && !in_array($basename, $options['allowHidden'])) continue;
			}
			if(count($options['exclude'])) {
				if(in_array($name, $options['exclude']) || in_array("$name/", $options['exclude'])) continue;
			}
			if(is_dir($file)) {
				if($options['maxDepth'] > 0 && $depth >= $options['maxDepth']) continue;
				$_files = array();
				foreach(new \DirectoryIterator($file) as $f) {
					if($f->isDot()) continue; 
					if($options['maxDepth'] > 0 && $f->isDir() && ($depth+1) >= $options['maxDepth']) continue;
					$_files[] = $f->getPathname();
				}
				if(count($_files)) {
					$zip->addEmptyDir($name);
					$options['dir'] = "$name/";
					$options['zip'] = $zip;
					$depth++;
					$_return = $this->zip($zipfile, $_files, $options);
					$depth--;
					foreach($_return['files'] as $s) $return['files'][] = $s;
					foreach($_return['errors'] as $s) $return['errors'][] = $s;
				} else if($options['allowEmptyDirs']) {
					$zip->addEmptyDir($name);
				}
			} else if(file_exists($file)) {
				if($zip->addFile($file, $name)) {
					$return['files'][] = $name;
				} else {
					$return['errors'][] = $name;
				}
			}
		}

		if(!$recursive) $zip->close();

		return $return;
	}

	/**
	 * Send the contents of the given filename to the current http connection
	 *
	 * This function utilizes the `$config->fileContentTypes` to match file extension
	 * to content type headers and force-download state.
	 *
	 * This function throws a WireException if the file can't be sent for some reason.
	 *
	 * @param string $filename Full path and filename to send
	 * @param array $options Optional options that you may pass in (see `WireHttp::sendFile()` for details) 
	 * @param array $headers Optional headers that are sent (see `WireHttp::sendFile()` for details)
	 * @throws WireException
	 * @see WireHttp::sendFile()
	 *
	 */
	public function send($filename, array $options = array(), array $headers = array()) {
		$this->allowPath($filename, false, true);
		$http = new WireHttp();
		$http->sendFile($filename, $options, $headers);
	}

	/**
	 * Create (overwrite or append) a file, put the $contents in it, and adjust permissions
	 * 
	 * This is the same as PHP’s `file_put_contents()` except that it’s preferable to use this in 
	 * ProcessWire because it adjusts the file permissions configured with `$config->chmodFile`.
	 * 
	 * @param string $filename Filename to write to
	 * @param string|mixed $contents Contents to write to file
	 * @param int $flags Flags to modify behavior:
	 *  - `FILE_APPEND` (constant): Append to file if it already exists .
	 *  - `LOCK_EX` (constant): Acquire exclusive lock to file while writing.
	 * @return int|bool Number of bytes written or boolean false on fail 
	 * @throws WireException if given invalid $filename (since 3.0.118)
	 * 
	 */
	public function filePutContents($filename, $contents, $flags = 0) {
		$this->allowPath($filename, false, true);
		$result = file_put_contents($filename, $contents, $flags); 
		if($result !== false) $this->chmod($filename);
		return $result;
	}

	/**
	 * Given a filename, render it as a ProcessWire template file
	 *
	 * This is a shortcut to using the TemplateFile class.
	 *
	 * File is assumed relative to `/site/templates/` (or a directory within there) unless you specify a full path.
	 * If you specify a full path, it will accept files in or below any of the following:
	 * 
	 * - /site/templates/ 
	 * - /site/modules/
	 * - /wire/modules/
	 *
	 * Note this function returns the output to you, so that you can send the output wherever you want (delayed output).
	 * For direct output, use the `$files->include()` function instead.
	 * 
	 * #pw-group-includes
	 *
	 * @param string $filename Assumed relative to /site/templates/ unless you provide a full path name with the filename.
	 *  If you provide a path, it must resolve somewhere in site/templates/, site/modules/ or wire/modules/.
	 * @param array $vars Optional associative array of variables to send to template file.
	 *  Please note that all template files automatically receive all API variables already (you don't have to provide them).
	 * @param array $options Associative array of options to modify behavior:
	 *  - `defaultPath` (string): Path where files are assumed to be when only filename or relative filename is specified (default=/site/templates/)
	 *  - `autoExtension` (string): Extension to assume when no ext in filename, make blank for no auto assumption (default=php)
	 *  - `allowedPaths` (array): Array of paths that are allowed (default is templates, core modules and site modules)
	 *  - `allowDotDot` (bool): Allow use of ".." in paths? (default=false)
	 *  - `throwExceptions` (bool): Throw exceptions when fatal error occurs? (default=true)
	 *  - `cache` (int|string|Page): Specify non-zero value to cache rendered result for this many seconds, or see the `WireCache::renderFile()` 
	 *     method `$expire` argument for more options you can specify here. (default=0, no cache) *Note: this option added in 3.0.130*
	 * @return string|bool Rendered template file or boolean false on fatal error (and throwExceptions disabled)
	 * @throws WireException if template file doesn't exist
	 * @see WireFileTools::include()
	 *
	 */
	public function render($filename, array $vars = array(), array $options = array()) {

		$paths = $this->wire('config')->paths;
		$defaults = array(
			'defaultPath' => $paths->templates,
			'autoExtension' => 'php',
			'allowedPaths' => array(
				$paths->templates,
				$paths->adminTemplates,
				$paths->modules,
				$paths->siteModules,
				$paths->cache
			),
			'allowDotDot' => false,
			'throwExceptions' => true,
			'cache' => 0, 
		);

		$options = array_merge($defaults, $options);
		$filename = $this->unixFileName($filename);

		// add .php extension if filename doesn't already have an extension
		if($options['autoExtension'] && !strrpos(basename($filename), '.')) {
			$filename .= "." . $options['autoExtension'];
		}

		if(!$options['allowDotDot'] && strpos($filename, '..')) {
			// make path relative to /site/templates/ if filename is not an absolute path
			$error = 'Filename may not have ".."';
			if($options['throwExceptions']) throw new WireException($error);
			$this->error($error);
			return false;
		}

		if($options['defaultPath'] && strpos($filename, './') === 0) {
			$filename = rtrim($options['defaultPath'], '/') . '/' . substr($filename, 2);

		} else if($options['defaultPath'] && strpos($filename, '/') !== 0 && strpos($filename, ':') !== 1) {
			// filename is relative to defaultPath (typically /site/templates/)
			$filename = rtrim($options['defaultPath'], '/') . '/' . $filename;

		} else if(strpos($filename, '/') !== false) {
			// filename is absolute, make sure it's in a location we consider safe
			$allowed = false;
			foreach($options['allowedPaths'] as $path) {
				if(strpos($filename, $path) === 0) {
					$allowed = true;
					break;
				}
			}
			if(!$allowed) {
				$error = "Filename $filename is not in an allowed path." ;
				$error .= ' Paths: ' . implode("\n", $options['allowedPaths']) . '';
				if($options['throwExceptions']) throw new WireException($error);
				$this->error($error);
				return false;
			}
		}
		
		if($options['cache']) {
			/** @var WireCache $cache */
			$cache = $this->wire('cache');
			$o = $options;
			unset($o['cache']); 
			$o['vars'] = $vars; 
			return $cache->renderFile($filename, $options['cache'], $o); 
		}

		// render file and return output
		$t = new TemplateFile();
		$t->setThrowExceptions($options['throwExceptions']);
		$t->setFilename($filename);

		foreach($vars as $key => $value) {
			$t->set($key, $value);
		}
		
		return $t->render();
	}

	/**
	 * Include a PHP file passing it all API variables and optionally your own specified variables
	 *
	 * This is the same as PHP’s `include()` function except for the following:
	 * 
	 * - It receives all API variables and optionally your custom variables
	 * - If your filename is not absolute, it doesn’t look in PHP’s include path, only in the current dir.
	 * - It only allows including files that are part of the PW installation: templates, core modules or site modules
	 * - It will assume a “.php” extension if filename has no extension.
	 *
	 * Note this function produces direct output. To retrieve output as a return value, use the
	 * `$files->render()` function instead.
	 * 
	 * #pw-group-includes
	 *
	 * @param string $filename Filename to include
	 * @param array $vars Optional variables you want to hand to the include (associative array)
	 * @param array $options Array of options to modify behavior:
	 *  - `func` (string): Function to use: include, include_once, require or require_once (default=include)
	 *  - `autoExtension` (string): Extension to assume when no ext in filename, make blank for no auto assumption (default=php)
	 *  - `allowedPaths` (array): Array of start paths include files are allowed from. Note current dir is always allowed.
	 * @return bool Always returns true
	 * @throws WireException if file doesn’t exist or is not allowed
	 *
	 */
	function ___include($filename, array $vars = array(), array $options = array()) {

		$paths = $this->wire('config')->paths;
		$defaults = array(
			'func' => 'include',
			'autoExtension' => 'php',
			'allowedPaths' => array(
				$paths->templates,
				$paths->adminTemplates,
				$paths->modules,
				$paths->siteModules,
				$paths->cache
			)
		);

		$options = array_merge($defaults, $options);
		$filename = trim($filename);

		// add .php extension if filename doesn't already have an extension
		if($options['autoExtension'] && !strrpos(basename($filename), '.')) {
			$filename .= "." . $options['autoExtension'];
		}

		if(strpos($filename, '..') !== false) {
			// if backtrack/relative components, convert to real path
			$_filename = $filename;
			$filename = realpath($filename);
			if($filename === false) throw new WireException("File does not exist: $_filename");
		}
		
		$filename = $this->unixFileName($filename);

		if(strpos($filename, '//') !== false) {
			throw new WireException("File is not allowed (double-slash): $filename");
		}

		if(strpos($filename, './') !== 0) {
			// file does not specify "current directory"
			$slashPos = strpos($filename, '/');
			// If no absolute path specified, ensure it only looks in current directory
			if($slashPos !== 0 && strpos($filename, ':/') === false) $filename = "./$filename";
		}

		if(strpos($filename, '/') === 0 || strpos($filename, ':/') !== false) {
			// absolute path, make sure it's part of PW's installation
			$allowed = false;
			foreach($options['allowedPaths'] as $path) {
				if($this->fileInPath($filename, $path)) $allowed = true;
			}
			if(!$allowed) throw new WireException("File is not in an allowed path: $filename");
		}

		if(!file_exists($filename)) throw new WireException("File does not exist: $filename");

		// extract all API vars
		$fuel = array_merge($this->wire('fuel')->getArray(), $vars);
		extract($fuel);

		// include the file
		TemplateFile::pushRenderStack($filename); 
		$func = $options['func'];
		if($func == 'require') require($filename);
			else if($func == 'require_once') require_once($filename);
			else if($func == 'include_once') include_once($filename);
			else include($filename);
		TemplateFile::popRenderStack();

		return true;
	}

	/**
	 * Same as include() method except that file will not be executed if it as previously been included
	 * 
	 * See the `WireFileTools::include()` method for details, arguments and options.
	 * 
	 * #pw-group-includes
	 * 
	 * @param string $filename
	 * @param array $vars 
	 * @param array $options
	 * @return bool
	 * @see WireFileTools::include()
	 * 
	 */
	function includeOnce($filename, array $vars = array(), array $options = array()) {
		$options['func'] = 'include_once';
		return $this->include($filename, $vars, $options);
	}	
	
	/**
	 * Get the namespace used in the given .php or .module file
	 * 
	 * #pw-advanced
	 *
	 * @param string $file File name or file data (if file data, specify true for 2nd argument)
	 * @param bool $fileIsContents Specify true if the given $file is actually the contents of the file, rather than file name.
	 * @return string Actual found namespace or "\" (root namespace) if none found
	 *
	 */
	public function getNamespace($file, $fileIsContents = false) {

		$namespace = "\\"; // root namespace, if no namespace found
		
		if($fileIsContents) {
			$data = $file;
		} else {
			$data = file_get_contents($file);
			if($data === false) return $namespace;
		}
		
		// if there's no "namespace" keyword in the file, it's not declaring one
		$namespacePos = strpos($data, 'namespace');
		if($namespacePos === false) return $namespace;

		// if file doesn't start with an opening PHP tag, then it's not going to have a namespace declaration
		$phpOpen = strpos($data, '<' . '?');
		if($phpOpen !== 0) return $namespace;
	
		// quick optimization for common ProcessWire namespace usage
		if(strpos($data, '<' . '?php namespace ProcessWire;') === 0) return 'ProcessWire';
	
		$test = substr($data, 0, $namespacePos-1);
		$test = trim(str_replace(array('<' . '?php', '<' . '?', "\n", "\r", "\t", " "), "", $test));
		if(!strlen($test)) {
			// namespace declaration is the first thing in the file (other than php tag and whitespace)
			$namespacePos += 10; // skip over "namespace" word
			$semiPos = strpos($data, ';'); 
			if($semiPos > $namespacePos) {
				$test = substr($data, 0, $semiPos);
				$namespace = substr($test, $namespacePos);
				return trim($namespace, "; ");
			}
		}
		/* for reference, the above (hopefully faster) replaces this regex
		if(preg_match('/^<\?[ph]*[\s\r\n]+namespace\s+([^;]+);/s', $data, $matches)) {
			return trim($matches[1]);
		}
		*/

		// remove anything after a closing php tag
		$phpEnd = strpos($data, '?' . '>');
		if($phpEnd !== false) $data = substr($data, 0, $phpEnd);

		// if there's no 'namespace' word present in the data, nothing is declared
		if(strpos($data, 'namespace') === false) return $namespace;

		// normalize line endings
		if(strpos($data, "\r") !== false) $data = str_replace("\r", "\n", $data);

		while(preg_match('/(^.*[\s\r\n]+)namespace\s+([_a-zA-Z0-9\\\\]+);\s*$/m', $data, $matches)) {

			// $open is everything that comes before the namespace line
			$open = $matches[1];

			// potential namespace, if our checks succeed
			$_namespace = trim($matches[2]);

			// $line is everything preceding the 'namespace' declaration, on the same line as the declaration
			$lastNewlinePos = strrpos($open, "\n");
			if($lastNewlinePos !== false) {
				$line = substr($open, $lastNewlinePos);
			} else {
				$line = $open;
			}

			// determine if line is commented
			$hasComment = strpos($line, '//') !== false;

			if(!$hasComment) {
				// determine if namespace declaration is in a comment block
				$startCommentPos = strrpos($open, '/*');
				$closeCommentPos = strrpos($open, '*/');
				if($startCommentPos !== false && ((int) $closeCommentPos) < $startCommentPos) $hasComment = true;
			}

			if(!$hasComment) {
				// if we've reached this point, we have found a valid namespace
				$namespace = $_namespace;
				break;
			}

			// reduce $data for next preg_match
			$data = str_replace($matches[0], '', $data);
		}

		return $namespace;
	}

	/**
	 * Compile the given file using ProcessWire’s file compiler
	 * 
	 * #pw-internal
	 * 
	 * @param string $file File to compile
	 * @param array $options Optional associative array of the following: 
	 *  - `includes` (bool): Also compile files include()'d from the given $file? (default=true)
	 *  - `namespace` (bool): Compile to make compatible with ProcessWire namespace? (default=true)
	 *  - `modules` (bool): Allow FileCompilerModule module's to process the file as well? (default=false)
	 *  - `skipIfNamespace` (bool): Return source $file if it declares a namespace (default=false)
	 * @return string Full path and filename of compiled file, or returns original `$file` if compilation is not necessary.
	 * @throws WireException if given invalid $file or other fatal error
	 * 
	 */
	public function compile($file, array $options = array()) {
		static $compiled = array();
		if(strpos($file, '/modules/')) {
			// for multi-instance support, use the same compiled version
			// otherwise, require_once() statements in a file may not work as intended
			// applied just to site/modules for the moment, but may need to do site/templates too
			$f = str_replace($this->wire('config')->paths->root, '', $file);
			if(isset($compiled[$f])) return $compiled[$f];
		} else {
			$f = '';
		}
		$compiler = new FileCompiler(dirname($file), $options);
		$compiledFile = $compiler->compile(basename($file));
		if($f) $compiled[$f] = $compiledFile;
		return $compiledFile;
	}

	/**
	 * Compile and include() the given file
	 * 
	 * #pw-internal
	 *
	 * @param string $file File to compile and include
	 * @param array $options Optional associative array of the following:
	 *  - `includes` (bool): Also compile files include()'d from the given $file? (default=true)
	 *  - `namespace` (bool): Compile to make compatible with ProcessWire namespace? (default=true)
	 *  - `modules` (bool): Allow FileCompilerModule module's to process the file as well? (default=false)
	 *  - `skipIfNamespace` (bool): Return source $file if it declares a namespace (default=false)
	 * @throws WireException if given invalid $file or other fatal error
	 * 
	 */
	public function compileInclude($file, array $options = array()) {
		$file = $this->compile($file, $options);	
		TemplateFile::pushRenderStack($file);
		include($file);	
		TemplateFile::popRenderStack();
	}

	/**
	 * Compile and include_once() the given file
	 *
	 * #pw-group-internal
	 *
	 * @param string $file File to compile and include
	 * @param array $options Optional associative array of the following:
	 *  - `includes` (bool): Also compile files include()'d from the given $file? (default=true)
	 *  - `namespace` (bool): Compile to make compatible with ProcessWire namespace? (default=true)
	 *  - `modules` (bool): Allow FileCompilerModule module's to process the file as well? (default=false)
	 *  - `skipIfNamespace` (bool): Return source $file if it declares a namespace (default=false)
	 * @throws WireException if given invalid $file or other fatal error
	 *
	 */
	public function compileIncludeOnce($file, array $options = array()) {
		$file = $this->compile($file, $options);
		TemplateFile::pushRenderStack($file);
		include_once($file);
		TemplateFile::popRenderStack();
	}

	/**
	 * Compile and require() the given file
	 *
	 * #pw-internal
	 *
	 * @param string $file File to compile and include
	 * @param array $options Optional associative array of the following:
	 *  - `includes` (bool): Also compile files include()'d from the given $file? (default=true)
	 *  - `namespace` (bool): Compile to make compatible with ProcessWire namespace? (default=true)
	 *  - `modules` (bool): Allow FileCompilerModule module's to process the file as well? (default=false)
	 *  - `skipIfNamespace` (bool): Return source $file if it declares a namespace (default=false)
	 * @throws WireException if given invalid $file or other fatal error
	 * 
	 */
	public function compileRequire($file, array $options = array()) {
		$file = $this->compile($file, $options);
		TemplateFile::pushRenderStack($file); 
		require($file);
		TemplateFile::popRenderStack();
	}

	/**
	 * Compile and require_once() the given file
	 * 
	 * #pw-internal
	 *
	 * @param string $file File to compile and include
	 * @param array $options Optional associative array of the following:
	 *  - `includes` (bool): Also compile files include()'d from the given $file? (default=true)
	 *  - `namespace` (bool): Compile to make compatible with ProcessWire namespace? (default=true)
	 *  - `modules` (bool): Allow FileCompilerModule module's to process the file as well? (default=false)
	 *  - `skipIfNamespace` (bool): Return source $file if it declares a namespace (default=false)
	 * @throws WireException if given invalid $file or other fatal error
	 *
	 */
	public function compileRequireOnce($file, array $options = array()) {
		TemplateFile::pushRenderStack($file); 
		$file = $this->compile($file, $options);
		require_once($file);
		TemplateFile::popRenderStack();
	}

	/**
	 * Convert given directory name to use unix slashes and enforce trailing or no-trailing slash
	 * 
	 * #pw-group-filenames
	 * 
	 * @param string $dir Directory name to adust (if it needs it)
	 * @param bool $trailingSlash True to force trailing slash, false to force no trailing slash (default=true)
	 * @return string Adjusted directory name
	 * 
	 */
	public function unixDirName($dir, $trailingSlash = true) {
		if(DIRECTORY_SEPARATOR != '/' && strpos($dir, DIRECTORY_SEPARATOR) !== false) {
			$dir = str_replace(DIRECTORY_SEPARATOR, '/', $dir);
		}
		$dir = rtrim($dir, '/');
		if($trailingSlash) $dir .= '/';
		return $dir;
	}

	/**
	 * Convert given file name to use unix slashes (if it isn’t already)
	 * 
	 * #pw-group-filenames
	 *
	 * @param string $file File name to adjust (if it needs it)
	 * @return string Adjusted file name
	 *
	 */
	public function unixFileName($file) {
		return $this->unixDirName($file, false);
	}

	/**
	 * Is given $file name in given $path name? (aka: is $file a subdirectory somewhere within $path)
	 * 
	 * This is purely for string comparison purposes, it does not check if file/path actually exists. 
	 * Note that if $file and $path are identical, this method returns false.
	 * 
	 * #pw-group-filenames
	 * 
	 * @param string $file May be a file or a directory
	 * @param string $path
	 * @return bool
	 * 
	 */
	public function fileInPath($file, $path) {
		$file = $this->unixDirName($file); // use of unixDirName rather than unixFileName intentional
		$path = $this->unixDirName($path);
		if($file === $path || strlen($file) <= strlen($path)) return false;
		return strpos($file, $path) === 0;
	}

	/**
	 * Get the current path / work directory
	 * 
	 * This is like PHP’s getcwd() function except that is in ProcessWire format as unix path with trailing slash.
	 * 
	 * #pw-group-filenames
	 * 
	 * @return string
	 * @since 3.0.130
	 * 
	 */
	public function currentPath() {
		return $this->unixDirName(getcwd());
	}

}