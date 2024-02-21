<?php namespace ProcessWire;

/**
 * ProcessWire File Tools ($files API variable)
 * 
 * #pw-summary Helpers for working with files and directories. 
 * #pw-var-files
 *
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 *
 * @method bool include($filename, array $vars = array(), array $options = array())
 *
 */

class WireFileTools extends Wire {

	/**
	 * Active file data (as used by getCSV for example)
	 * 
	 * @var array
	 * 
	 */
	protected $data = array();

	/**
	 * Destruct
	 * 
	 */
	public function __destruct() {
		foreach($this->data as $value) {
			if(isset($value['fp'])) fclose($value['fp']); 
		}
	}
	
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
	 * #pw-group-manipulation
	 * 
	 * @param string $path Directory you want to create
	 * @param bool|string $recursive If set to true, all directories will be created as needed to reach the end.
	 * @param string|null|bool $chmod Optional mode to set directory to (default: $config->chmodDir), format must be a string i.e. "0755"
	 *   If omitted, then ProcessWire's `$config->chmodDir` setting is used instead.
	 * @return bool True on success, false on failure
	 *
	 */
	public function mkdir($path, $recursive = false, $chmod = null) {
		if(!strlen("$path")) return false;
		
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
			if(!@mkdir($path)) {
				return $this->filesError(__FUNCTION__, "Unable to mkdir $path");
			}
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
	 * #pw-group-manipulation
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
					$this->rmdir($pathname, true, $options);
				} else {
					$this->unlink($pathname, $options['limitPath'], $options['throw']);
				}
			}
		}
		
		if(@rmdir($path)) {
			return true;
		} else {
			return $this->filesError(__FUNCTION__, "Unable to rmdir: $path", $options);
		}
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
	 * #pw-group-manipulation
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
			$chmodFile = $this->wire()->config->chmodFile;
			$chmodDir = $this->wire()->config->chmodDir;
		} else {
			// optional, manually specified string
			if(!is_string($chmod)) {
				$this->filesException(__FUNCTION__, "chmod must be specified as a string like '0755'");
			}
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
	 * #pw-group-manipulation
	 * 
	 * @param string $src Path to copy files from, or filename to copy. 
	 * @param string $dst Path (or filename) to copy file(s) to. Directory is created if it doesn't already exist.
	 * @param bool|array $options Array of options: 
	 *  - `recursive` (bool): Whether to copy directories within recursively. (default=true)
	 *  - `allowEmptyDirs` (boolean): Copy directories even if they are empty? (default=true)
	 *  - `limitPath` (bool|string|array): Limit copy to within path given here, or true for site assets path.
	 *     The limitPath option requires core 3.0.118+. (default=false).
	 *  - `hidden` (bool): Also copy hidden files/directories within given $src directory? (applies only if $src is dir)
	 *     The hidden option requires core 3.0.180+. (default=true)
	 *  - If a boolean is specified for $options, it is assumed to be the `recursive` option.
	 * @return bool True on success, false on failure.
	 * @throws WireException if `limitPath` option is used and either $src or $dst is not allowed
	 *
	 */
	public function copy($src, $dst, $options = array()) {

		$defaults = array(
			'recursive' => true,
			'hidden' => true, 
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
				if(!$options['hidden'] && strpos(basename($file), '.') === 0) continue;
				$isEmpty = false;
				break;
			}
			if($isEmpty) return true;
		}

		if(!$this->mkdir($dst)) return false;

		while(false !== ($file = readdir($dir))) {
			if($file == '.' || $file == '..') continue;
			if(!$options['hidden'] && strpos(basename($file), '.') === 0) continue;
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
	 * - This method will be limited to unlink files only in /site/assets/ if you 
	 *   specify `true` for the `$limitPath` option (recommended).
	 * 
	 * #pw-group-manipulation
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
			return $this->filesError(__FUNCTION__, "Given filename is not a file or link: $filename");
		}
		
		if(@unlink($filename)) {
			return true;
		} else {
			return $this->filesError(__FUNCTION__, "Unable to unlink file: $filename", $throw);
		}
	}

	/**
	 * Rename a file or directory and update permissions
	 * 
	 * Note that this method will fail if pathname given by $newName argument already exists.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $oldName Old pathname, must be full disk path. 
	 * @param string $newName New pathname, must be full disk path OR can be basename to assume same path as $oldName. 
	 * @param array|bool|string $options Options array to modify behavior or substitute `limitPath` (bool or string) option here.
	 *  - `limitPath` (bool|string|array): Limit renames to within this path, or boolean TRUE for site/assets, or FALSE to disable (default=false).
	 *  - `throw` (bool): Throw WireException with verbose details on error? (default=false)
	 *  - `chmod` (bool): Adjust permissions to be consistent with $config after rename? (default=true)
	 *  - `copy` (bool): Use copy-then-delete method rather than a file system rename. (default=false) 3.0.178+
	 *  - `retry` (bool): Retry with 'copy' method if regular rename files, applies only if copy option is false. (default=true) 3.0.178+
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
			'copy' => false, 
			'retry' => true,
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
			return $this->filesError(__FUNCTION__, '$oldName path invalid: ' . $e->getMessage(), $options);
		}
		
		try {
			$this->allowPath($newName, $options['limitPath'], true);
		} catch(\Exception $e) {
			return $this->filesError(__FUNCTION__, 'Rename $newName path invalid: ' . $e->getMessage(), $options);
		}
		
		if(!file_exists($oldName)) {
			return $this->filesError(__FUNCTION__, 'Given pathname ($oldName) that does not exist: ' . $oldName, $options);
		}
		
		if(file_exists($newName)) {
			return $this->filesError(__FUNCTION__, 'Rename to pathname ($newName) that already exists: ' . $newName, $options);
		}

		if($options['copy']) {
			// will use recursive copy method only
			$success = false; 
		} else if($options['retry']) {
			// consider any error/warnings from rename a non-event since we can retry
			$success = @rename($oldName, $newName); 
		} else {
			// use real rename only
			$success = rename($oldName, $newName);
		}
	
		if(!$success && ($options['retry'] || $options['copy'])) {
			$opt = array(
				'limitPath' => $options['limitPath'],
				'throw' => $options['throw'],
			);
			if($this->copy($oldName, $newName, $opt)) {
				$success = true;
				if(is_dir($oldName)) {
					if(!$this->rmdir($oldName, true, $opt)) {
						$this->filesError(__FUNCTION__, 'Unable to rmdir source ($oldName): ' . $oldName);
					}
				} else {
					if(!$this->unlink($oldName, $opt['limitPath'], $opt['throw'])) {
						$this->filesError(__FUNCTION__, 'Unable to unlink source ($oldName): ' . $oldName);
					}
				}
			}
		}
	
		if($success) {
			if($options['chmod']) $this->chmod($newName);
		} else {
			$this->filesError(__FUNCTION__, "Failed: $oldName => $newName", $options);
		}
		
		return $success;
	}

	/**
	 * Rename by first copying files to destination and then deleting source files
	 * 
	 * The operation is considered successful so long as the source files were able to be copied to the destination.
	 * If source files cannot be deleted afterwards, the warning is logged, plus a warning notice is also shown when in debug mode.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $oldName Old pathname, must be full disk path.
	 * @param string $newName New pathname, must be full disk path OR can be basename to assume same path as $oldName.
	 * @param array $options See options for rename() method
	 * @return bool
	 * @throws WireException
	 * @since 3.0.178
	 * 
	 */
	public function renameCopy($oldName, $newName, $options = array()) {
		$options['copy'] = true;
		return $this->rename($oldName, $newName, $options);
	}

	/**
	 * Does the given file/link/dir exist?
	 * 
	 * Thie method accepts an `$options` argument that can be specified as an array
	 * or a string (space or comma separated). The examples here demonstrate usage as 
	 * a string since it is the simplest for readability.
	 *
	 * - This function may return false for symlinks pointing to non-existing 
	 *   files, unless you specify `link` as the `type`.
	 * - Specifying `false` for the `readable` or `writable` argument disables the 
	 *   option from being used, it doesn’t perform a NOT condition.
	 * - The `writable` option may also be written as `writeable`, if preferred.
	 * 
	 * ~~~~~
	 * // 1. check if exists
	 * $exists = $files->exists('/path/file.ext');
	 * 
	 * // 2. check if exists and is readable (or writable)
	 * $exists = $files->exists('/path/file.ext', 'readable');
	 * $exists = $files->exists('/path/file.ext', 'writable');
	 * 
	 * // 3. check if exists and is file, link or dir
	 * $exists = $files->exists('/path/file.ext', 'file');
	 * $exists = $files->exists('/path/file.ext', 'link');
	 * $exists = $files->exists('/path/file.ext', 'dir');
	 * 
	 * // 4. check if exists and is writable file or dir
	 * $exists = $files->exists('/path/file.ext', 'writable file');
	 * $exists = $files->exists('/path/dir/', 'writable dir');
	 * 
	 * // 5. check if exists and is readable and writable file
	 * $exists = $files->exists('/path/file.ext', 'readable writable file');
	 * ~~~~~
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $filename
	 * @param array|string $options Can be specified as array or string:
	 *  - `type` (string): Verify it is of type: 'file', 'link', 'dir' (default='')
	 *  - `readable` (bool): Verify it is readable? (default=false)
	 *  - `writable` (bool): Also verify the file is writable? (default=false)
	 *  - `writeable` (bool): Alias of writable (default=false)
	 *  - When specified as string, you can use any combination of the words: 
	 *    `readable, writable, file, link, dir` (separated by space or comma). 
	 * @return bool
	 * @throws WireException if given invalid or unrecognized $options
	 * @since 3.0.180
	 * 
	 * 
	 */
	public function exists($filename, $options = '') {
		
		$defaults = array(
			'type' => '',
			'readable' => false,
			'writable' => false,
			'writeable' => false, // alias of writable
		);
	
		if($options === '') {
			$options = $defaults;
			
		} else if(is_array($options)) {
			$options = array_merge($defaults, $options);
			if(!empty($options['type'])) $options['type'] = strtolower(trim($options['type']));
			
		} else if(is_string($options)) {
			$types = array('file', 'link', 'dir');
			if(strpos($options, ',') !== false) $options = str_replace(',', ' ', $options);
			foreach(explode(' ', $options) as $option) {
				$option = strtolower(trim($option));
				if(empty($option)) continue;
				if(isset($defaults[$option])) {
					// readable, writable
					$defaults[$option] = true;
				} else if(in_array($option, $types, true)) {
					// file, dir, link
					if(empty($defaults['type'])) $defaults['type'] = $option;
				} else {
					throw new WireException("Unrecognized option: $option");
				}
			}
			$options = $defaults;
			
		} else {
			throw new WireException('Invalid $options argument');
		}
	
		if($options['readable'] && !is_readable($filename)) {
			$exists = false;
		} else if(($options['writable'] || $options['writeable']) && !is_writable($filename)) {
			$exists = false;
		} else if($options['type'] === '') {
			$exists = $options['readable'] ? true : file_exists($filename);
		} else if($options['type'] === 'file') {
			$exists = is_file($filename);
		} else if($options['type'] === 'link') {
			$exists = is_link($filename);
		} else if($options['type'] === 'dir') {
			$exists = is_dir($filename);
		} else {
			throw new WireException("Unrecognized 'type' option: $options[type]"); 
		}
		
		return $exists;
	}

	/**
	 * Get size of file or directory (in bytes)
	 * 
	 * @param string $path File or directory path
	 * @param array|bool $options Options array, or boolean true for getString option:
	 *  - `getString` (bool): Get string that summarizes bytes, kB, MB, etc.? (default=false)
	 * @return int|string
	 * @since 3.0.214
	 * 
	 */
	public function size($path, $options = array()) {
		if(is_bool($options)) $options = array('getString' => $options);
		$size = 0;
		$path = realpath($path);
		if(!is_string($path) || $path === '' || !file_exists($path)) return 0;
		if(is_dir($path)) {
			$dir = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
			foreach(new \RecursiveIteratorIterator($dir) as $file) {
				try {
					$size += $file->getSize();
				} catch(\Exception $e) {
					// ok
				}
			}
		} else {
			$size = (int) filesize($path);
		}
		return empty($options['getString']) ? $size : wireBytesStr($size);
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
			if(!$allow) {
				$this->filesError(__FUNCTION__, "Given pathname is not within any of the paths allowed by limitPath", $throw);
			}
			return $allow;
			
		} else if($limitPath === true) {
			// default limitPath
			$limitPath = $this->wire()->config->paths->assets;
			
		} else if($limitPath === false) {
			// no limitPath in use	
			
		} else if(empty($limitPath) || !is_string($limitPath)) { 
			// invalid limitPath argument (wrong type or path does not exist)
			return $this->filesError(__FUNCTION__, "Invalid type for limitPath argument", $throw);
			
		} else if(!is_dir($limitPath)) {
			return $this->filesError(__FUNCTION__, "$limitPath (limitPath) does not exist", $throw);
		}
			
		if($limitPath !== false) try {
			// if limitPath can't pass allowPath then neither can $pathname
			$this->allowPath($limitPath, false, true);
		} catch(\Exception $e) {
			return $this->filesError(__FUNCTION__, "Validating limitPath reported: " . $e->getMessage(), $throw, $e); 
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
			return $this->filesError(__FUNCTION__, "pathname not allowed: $pathname", $throw);
		}
		
		if(strpos($pathname, '..') !== false) {
			// not allowed to traverse anywhere
			return $this->filesError(__FUNCTION__, 'pathname may not traverse “../”', $throw);
		}
		
		if(strpos($pathname, '.') === 0 || empty($pathname)) {
			return $this->filesError(__FUNCTION__, 'pathname may not begin with “.”', $throw);
		}

		$pos = strpos($pathname, '//');
		if($pos !== false && $pos !== strpos($this->wire()->config->paths->assets, '//')) {
			// URLs or accidental extra slashes not allowed, unless they also appear in a known safe system path
			return $this->filesError(__FUNCTION__, 'pathname may not contain double slash “//”', $throw);
		}

		if($limitPath !== false && strpos($pathname, $limitPath) !== 0) {
			// disallow paths that do not begin with limitPath (i.e. /path/to/public_html/site/assets/)
			return $this->filesError(__FUNCTION__, "Given pathname is not within $limitPath (limitPath)", $throw);
		}
		
		return true;
	}

	/**
	 * Return a new temporary directory/path ready to use for files
	 * 
	 * - The temporary directory will be automatically removed at the end of the request.
	 * - Temporary directories are not http accessible. 
	 * - If you call this with the same non-empty `$name` argument more than once in the 
	 *   same request, the same `WireTempDir` instance will be returned. 
	 * 
	 * #pw-advanced
	 * 
	 * ~~~~~
	 * $tempDir = $files->tempDir(); 
	 * $path = $tempDir->get(); 
	 * file_put_contents($path . 'some-file.txt', 'Hello world'); 
	 * ~~~~~
	 *
	 * @param Object|string $name Any one of the following: (default='')
	 *  - Omit this argument for auto-generated name, 3.0.178+ 
	 *  - Name/word that you specify using fieldName format, i.e. [_a-zA-Z0-9].
	 *  - Object instance that needs the temp dir.
	 * @param array|int $options Deprecated argument. Call `WireTempDir` methods if you need more options.
	 * @return WireTempDir Returns a WireTempDir instance. If you typecast return value to a string, 
	 *    it is the temp dir path (with trailing slash).
	 * @see WireTempDir
	 *
	 */
	public function tempDir($name = '', $options = array()) {
		static $tempDirs = array();
		if($name && isset($tempDirs[$name])) return $tempDirs[$name];
		if(is_int($options)) $options = array('maxAge' => $options);
		$basePath = isset($options['basePath']) ? $options['basePath'] : '';
		$tempDir = new WireTempDir();
		$this->wire($tempDir);
		if(isset($options['remove']) && $options['remove'] === false) $tempDir->setRemove(false);
		$tempDir->init($name, $basePath); 
		if(isset($options['maxAge'])) $tempDir->setMaxAge($options['maxAge']);
		if($name) $tempDirs[$name] = $tempDir;
		return $tempDir;
	}

	/**
	 * Find all files in the given $path recursively, and return a flat array of all found filenames
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $path Path to start from (required). 
	 * @param array $options Options to affect what is returned (optional):
	 *  - `recursive` (int|bool): How many levels of subdirectories this method should descend into beyond the 1 given.
	 *     Specify 1 to remain at the one directory level given, or 2+ to descend into subdirectories. (default=10)
	 *     In 3.0.180+ you may also specify true for no limit, or false to disable descending into any subdirectories.
	 *  - `extensions` (array|string): Only include files having these extensions, or omit to include all (default=[]).
	 *     In 3.0.180+ the extensions argument may also be a string (space or comma separated). 
	 *  - `excludeDirNames` (array): Do not descend into directories having these names (default=[]).
	 *  - `excludeHidden` (bool): Exclude hidden files? (default=false). 
	 *  - `allowDirs` (bool): Allow directories in returned files (except for '.' and '..')? Note that returned 
	 *     directories have a trailing slash. (default=false) 3.0.180+
	 *  - `returnRelative` (bool): Make returned array have filenames relative to given start $path? (default=false)
	 * @return array Flat array of filenames
	 * @since 3.0.96
	 * 
	 */
	public function find($path, array $options = array()) {

		$defaults = array(
			'recursive' => 10, 
			'extensions' => array(),
			'excludeExtensions' => array(), 
			'excludeDirNames' => array(),
			'excludeHidden' => false,
			'allowDirs' => false, 
			'returnRelative' => false,
		);

		$path = $this->unixDirName($path);
		if(!is_dir($path) || !is_readable($path)) return array();

		$options = array_merge($defaults, $options);
		
		if(empty($options['_level'])) {
			// this is a non-recursive call
			$options['_startPath'] = $path;
			$options['_level'] = 0;
			if(!is_array($options['extensions'])) {
				if($options['extensions']) {
					$options['extensions'] = preg_replace('/[,;\.\s]+/', ' ', $options['extensions']);
					$options['extensions'] = explode(' ', $options['extensions']); 
				} else {
					$options['extensions'] = array();
				}
			}
			foreach($options['extensions'] as $k => $v) {
				$options['extensions'][$k] = strtolower(trim($v));
			}
		}
		
		$options['_level']++;
		if($options['recursive'] && $options['recursive'] !== true) {
			if($options['_level'] > $options['recursive']) return array();
		}
			
		$dirs = array();
		$files = array();

		foreach(new \DirectoryIterator($path) as $file) {
			
			if($file->isDot()) continue;
			
			$basename = $file->getBasename();
			$ext = strtolower($file->getExtension());
			
			if($file->isDir()) {
				$dir = $this->unixDirName($file->getPathname());
				if($options['allowDirs']) {
					if($options['returnRelative'] && strpos($dir, $options['_startPath']) === 0) {
						$dir = substr($dir, strlen($options['_startPath']));
					}
					$files[$dir] = $dir;
				}
				if($options['recursive'] === false || $options['recursive'] < 1) continue;
				if(!in_array($basename, $options['excludeDirNames'])) $dirs[$dir] = $file->getPathname();
				continue;
			}
			
			if($options['excludeHidden'] && strpos($basename, '.') === 0) continue;
			if(!empty($options['extensions']) && !in_array($ext, $options['extensions'])) continue;
			if(!empty($options['excludeExtensions']) && in_array($ext, $options['excludeExtensions'])) continue;

			$filename = $this->unixFileName($file->getPathname());
			if($options['returnRelative'] && strpos($filename, $options['_startPath']) === 0) {
				$filename = substr($filename, strlen($options['_startPath']));
			}
				
			$files[] = $filename;
		}

		foreach($dirs as $key => $dir) {
			$_files = $this->find($dir, $options);
			if(count($_files)) {
				foreach($_files as $name) {
					$files[] = $name;
				}
			} else {
				// no files found in directory
				if($options['allowDirs'] && count($options['extensions']) && isset($files[$key])) {
					// remove directory if it didn't match any files having requested extension
					unset($files[$key]);
				}
			}
		}

		$options['_level']--;
		
		if(!$options['_level']) sort($files);

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

		if(!class_exists('\ZipArchive')) $this->filesException(__FUNCTION__, "PHP's ZipArchive class does not exist");
		if(!is_file($file)) $this->filesException(__FUNCTION__, "ZIP file does not exist");
		if(!is_dir($dst)) $this->mkdir($dst, true);

		$names = array();
		$chmodFile = $this->wire()->config->chmodFile;
		$chmodDir = $this->wire()->config->chmodDir;

		$zip = new \ZipArchive();
		$res = $zip->open($file);
		if($res !== true) $this->filesException(__FUNCTION__, "Unable to open ZIP file, error code: $res");

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
			if(!class_exists('\ZipArchive')) $this->filesException(__FUNCTION__, "PHP's ZipArchive class does not exist");
			$options = array_merge($defaults, $options);
			$zippath = dirname($zipfile);
			if(!is_dir($zippath)) $this->filesException(__FUNCTION__, "Path for ZIP file ($zippath) does not exist");
			if(!is_writable($zippath)) $this->filesException(__FUNCTION__, "Path for ZIP file ($zippath) is not writable");
			if(empty($files)) $this->filesException(__FUNCTION__, "Nothing to add to ZIP file $zipfile");
			if(is_file($zipfile) && $options['overwrite'] && !$this->unlink($zipfile)) $this->filesException(__FUNCTION__, "Unable to overwrite $zipfile");
			if(!is_array($files)) $files = array($files);
			if(!is_array($options['exclude'])) $options['exclude'] = array($options['exclude']);
			$recursive = false;
			$zip = new \ZipArchive();
			if($zip->open($zipfile, \ZipArchive::CREATE) !== true) $this->filesException(__FUNCTION__, "Unable to create ZIP: $zipfile");

		} else {
			$this->filesException(__FUNCTION__, "Invalid zipfile argument");
			return array(); // not reachable
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
	 * This function utilizes the `$config->fileContentTypes` to match file extension to content type headers 
	 * and force-download state.
	 *
	 * This function throws a `WireException` if the file can’t be sent for some reason. Set the `throw` option to
	 * false if you want it to instead return integer 0 when errors occur. 
	 * 
	 * #pw-group-http
	 *
	 * @param string|bool $filename Full path and filename to send or boolean false if provided in `$options[data]`.
	 * @param array $options Optional options to modify default behavior: 
	 *   - `exit` (bool): Halt program execution after file send (default=true).
	 *   - `partial` (bool): Allow use of partial downloads via HTTP_RANGE requests? Since 3.0.131 (default=true)
	 *   - `forceDownload` (bool|null): Whether file should force download, or null to let content-type header decide (default=null). 
	 *   - `downloadFilename` (string): Filename you want the download to show on user’s computer, or omit to use existing (default='').
	 *   - `headers` (array): The $headers argument to this method can also be provided as an option right here (default=[]). Since 3.0.131.
	 *   - `data` (string): String of data to send rather than file, $filename argument must be false (default=''). Since 3.0.132.
	 *   - `limitPath` (string|bool): Prefix disk path $filename must be within, false to disable, true for site/assets (default=false). Since 3.0.169.
	 *   - `throw` (bool): Throw exceptions on error? When false, it will instead return integer 0 on errors (default=true). Since 3.0.169.
	 * @param array $headers Optional headers that are sent, below are the defaults:
	 *   - `pragma`: public
	 *   - `expires`: 0
	 *   - `cache-control`: must-revalidate, post-check=0, pre-check=0
	 *   - `content-type`: {content-type} (replaced with actual content type)
	 *   - `content-transfer-encoding`: binary
	 *   - `content-length`: {filesize} (replaced with actual filesize)
	 *   - To remove a header completely, make its value NULL.
	 *   - If preferred, the above headers can be specified in `$options[headers]` instead.
	 * @return int Returns bytes sent, only if `exit` option is false (since 3.0.169)
	 * @throws WireException
	 * @see WireHttp::sendFile()
	 *
	 */
	public function send($filename, array $options = array(), array $headers = array()) {
		$defaults = array('limitPath' => $this->wire()->getStatus() === 32, 'throw' => true);
		$options = array_merge($defaults, $options);
		if($filename && !$this->allowPath($filename, $options['limitPath'], $options['throw'])) return 0;
		$http = new WireHttp();
		$this->wire($http);
		try {
			$result = $http->sendFile($filename, $options, $headers);
		} catch(\Exception $e) {
			$this->filesError(__FUNCTION__, $e->getMessage(), $options, $e);
			$result = 0;
		}
		return $result;	
	}

	/**
	 * Create (overwrite or append) a file, put the $contents in it, and adjust permissions
	 * 
	 * This is the same as PHP’s `file_put_contents()` except that it’s preferable to use this in 
	 * ProcessWire because it adjusts the file permissions configured with `$config->chmodFile`.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $filename Filename to write to
	 * @param string|mixed $contents Contents to write to file
	 * @param int $flags Flags to modify behavior:
	 *  - `FILE_APPEND` (constant): Append to file if it already exists.
	 *  - `LOCK_EX` (constant): Acquire exclusive lock to file while writing.
	 * @return int|bool Number of bytes written or boolean false on fail 
	 * @throws WireException if given invalid $filename (since 3.0.118)
	 * @see WireFileTools::fileGetContents()
	 * 
	 */
	public function filePutContents($filename, $contents, $flags = 0) {
		$this->allowPath($filename, false, true);
		$result = file_put_contents($filename, $contents, $flags); 
		if($result === false) {
			$this->filesError(__FUNCTION__, "Unable to write: $filename");
		} else {
			$this->chmod($filename);
		}
		return $result;
	}

	/**
	 * Get contents of file
	 * 
	 * This is the same as PHP’s `file_get_contents()` except that the arguments are simpler and 
	 * it may be preferable to use this in ProcessWire for future cases where the file system may be 
	 * abstracted from the installation.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @param string $filename Full path and filename to read
	 * @param int $offset The offset where the reading starts on the original stream. Negative offsets count from the end of the stream.
	 * @param int $maxlen Maximum length of data read. The default is to read until end of file is reached.
	 * @return bool|string Returns the read data (string) or boolean false on failure.
	 * @since 3.0.167
	 * @see WireFileTools::filePutContents()
	 * 
	 */
	public function fileGetContents($filename, $offset = 0, $maxlen = 0) {
		if($offset && $maxlen) {
			return file_get_contents($filename, false, null, $offset, $maxlen); 
		} else if($offset) {
			return file_get_contents($filename, false, null, $offset); 
		} else if($maxlen) {
			return file_get_contents($filename, false, null, 0, $maxlen); 
		} else {
			return file_get_contents($filename);
		}
	}

	/**
	 * Get next row from a CSV file
	 * 
	 * This simplifies the reading of a CSV file by abstracting file-open, get-header, get-rows and file-close 
	 * operations into a single method call, where all those operations are handled internally. All you have to 
	 * do is keep calling the `$files->getCSV($filename)` method until it returns false. This method will also
	 * skip over blank rows by default, unlike PHP’s fgetcsv() which will return a 1-column row with null value.
	 * 
	 * This method should always be used in a loop, meaning you must keep calling it until it returns false. 
	 * Otherwise a CSV file may be unintentionally left open. If you can't do that then use getAllCSV() instead.
	 * 
	 * For the method `$options` argument note that the `length`, `separator`, `enclosure` and `escape` options
	 * all correspond to the identically named PHP [fgetcsv](https://www.php.net/manual/en/function.fgetcsv.php)
	 * arguments.
	 * 
	 * Example foods.csv file (first row is header):
	 * ~~~~~
	 * Food,Type,Color
	 * Apple,Fruit,Red
	 * Banana,Fruit,Yellow
	 * Spinach,Vegetable,Green
	 * ~~~~~
	 * Example of reading the foods.csv file above:
	 * ~~~~~
	 * while($row = $files->getCSV('/path/to/foods.csv')) {
	 *   echo "Food: $row[Food] ";
	 *   echo "Type: $row[Type] "; 
	 *   echo "Color: $row[Color] ";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-CSV
	 * 
	 * @param string $filename CSV filename to read from
	 * @param array $options
	 *  - `header` (bool|array): Indicate whether CSV has header and how it should be used (default=true): 
	 *     True to treat first line as header and return rows as associative arrays indexed by the header values. 
	 *     False to indicate there is no header and/or to indicate it should return regular non-associative PHP arrays for rows. 
	 *     Array to use it as the header and return rows as associative arrays indexed by your values.
	 *  - `length` (int): Optional. When specified, must be greater than the longest line (in characters) to be found in the CSV file 
	 *     (allowing for trailing line-end characters). Otherwise the line is split in chunks of length characters, unless the split 
	 *     would occur inside an enclosure. Omitting this parameter (or setting it to 0, or null in PHP 8.0.0 or later) the maximum 
	 *     line length is not limited, which is slightly slower. (default=0)
	 *  - `separator` (string): The field separator/delimiter, one single-byte character only. (default=',')
	 *  - `enclosure` (string): The field enclosure character, one single-byte character only. (default='"')
	 *  - `escape` (string): The escape character, at most one single-byte character. An empty string ("") disables the proprietary 
	 *     escape mechanism. (default="\\")
	 *  - `blanks` (bool): Allow blank rows? (default=false)
	 *  - `convert` (bool): Convert digit-only strings to integers? (default=false)
	 * @return array|false Returns array for next row or boolean false when there are no more rows.
	 * @see https://www.php.net/manual/en/function.fgetcsv.php
	 * @see getAllCSV()
	 * @since 3.0.197
	 * 
	 */
	public function getCSV($filename, array $options = array()) {
		
		$defaults = array(
			'header' => true, // or array 
			'length' => 0,
			'separator' => ',',
			'enclosure' => '"', 
			'escape' => "\\", 
			'convert' => false,
			'blanks' => false, 
		);
		
		$options = array_merge($defaults, $options);
		$dataKey = "csv:$filename";
		$header = false;
		$row = false;
		
		if(isset($this->data[$dataKey])) {
			// file is open
			$fp = $this->data[$dataKey]['fp'];
			$header = $this->data[$dataKey]['header'];
			$row = $this->data[$dataKey]['nextRow'];
			if($row === false) {
				// EOF, close file and return false
				fclose($fp);
				unset($this->data[$dataKey]);
			} else {
				$this->data[$dataKey]['nextRow'] = $this->fgetcsv($fp, $options);
			}
			
		} else if(($fp = fopen($filename, "r")) !== false) {
			// open new file
			if($options['header'] === true) {
				// get header row and row after it
				$header = $this->fgetcsv($fp, $options);
				if($header !== false) {
					$row = $this->fgetcsv($fp, $options);
					foreach($header as $key => $value) {
						$header[$key] = trim($value);
					}
				}
			} else {
				// get row only
				$header = $options['header'];
				$row = $this->fgetcsv($fp, $options);
			}
			if($row === false) {
				// file has no rows
				fclose($fp);
			} else {
				// store for next call
				$this->data[$dataKey] = array(
					'fp' => $fp,
					'header' => $header,
					'nextRow' => $this->fgetcsv($fp, $options)
				);
			}
		}
		
		if($row === false) return false;
		
		if(empty($options['blanks']) && (empty($row) || (count($row) === 1 && $row[0] === null))) {
			// per fgetcsv() does, a blank line in CSV file returns as array with single null field
			// rather than accepting that behavior, we just move on to the next non-blank row
			return $this->getCSV($filename, $options);
		}
		
		if(is_array($header)) {
			// index row by header
			$a = array();
			foreach($header as $key => $name) {
				$a[$name] = isset($row[$key]) ? $row[$key] : '';	
			}
			$row = $a;
		}
		
		if($options['convert']) {
			// convert digit-only strings to integers
			foreach($row as $key => $value) {
				if(ctype_digit($value)) $row[$key] = (int) $value;
			}
		}

		return $row;
	}

	/**
	 * Get all rows from a CSV file
	 * 
	 * This simplifies the reading of a CSV file by abstracting file-open, get-header, get-rows and file-close
	 * operations into a single method call, where all those operations are handled internally. All you have to
	 * do is call the `$files->getAllCSV($filename)` method once and it will return an array of arrays (one per row). 
	 * This method will also skip over blank rows by default, unlike PHP’s fgetcsv() which will return a 1-column row 
	 * with null value.
	 * 
	 * This method is limited by available memory, so when working with potentially large files, you should use the 
	 * `$files->getCSV()` method instead, which reads the CSV file row-by-row rather than all at once.
	 * 
	 * Note for the method `$options` argument that the `length`, `separator`, `enclosure` and `escape` options
	 * all correspond to the identically named PHP [fgetcsv](https://www.php.net/manual/en/function.fgetcsv.php)
	 * arguments.
	 *
	 * Example foods.csv file (first row is header):
	 * ~~~~~
	 * Food,Type,Color
	 * Apple,Fruit,Red
	 * Banana,Fruit,Yellow
	 * Spinach,Vegetable,Green
	 * ~~~~~
	 * Example of reading the foods.csv file above:
	 * ~~~~~
	 * $rows = $files->getAllCSV('/path/to/foods.csv'); 
	 * foreach($rows as $row) {
	 *   echo "Food: $row[Food] ";
	 *   echo "Type: $row[Type] ";
	 *   echo "Color: $row[Color] ";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-CSV
	 *
	 * @param string $filename CSV filename to read from
	 * @param array $options
	 *  - `header` (bool|array): Indicate whether CSV has header and how it should be used (default=true):
	 *     True to treat first line as header and return rows as associative arrays indexed by the header values.
	 *     False to indicate there is no header and/or to indicate it should return regular non-associative PHP arrays for rows.
	 *     Array to use it as the header and return rows as associative arrays indexed by your values.
	 *  - `length` (int): Optional. When specified, must be greater than the longest line (in characters) to be found in the CSV file
	 *     (allowing for trailing line-end characters). Otherwise the line is split in chunks of length characters, unless the split
	 *     would occur inside an enclosure. Omitting this parameter (or setting it to 0, or null in PHP 8.0.0 or later) the maximum
	 *     line length is not limited, which is slightly slower. (default=0)
	 *  - `separator` (string): The field separator/delimiter, one single-byte character only. (default=',')
	 *  - `enclosure` (string): The field enclosure character, one single-byte character only. (default='"')
	 *  - `escape` (string): The escape character, at most one single-byte character. An empty string ("") disables the proprietary
	 *     escape mechanism. (default="\\")
	 *  - `convert` (bool): Convert digit-only strings to integers? (default=false)
	 *  - `blanks` (bool): Allow blank rows? (default=false)
	 * @return array
	 * @see https://www.php.net/manual/en/function.fgetcsv.php
	 * @see getCSV()
	 * @since 3.0.197
	 *
	 */
	public function getAllCSV($filename, array $options = array()) {
		$rows = array();
		while(false !== ($row = $this->getCSV($filename, $options))) {
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * PHP’s fgetcsv function in an internal options method
	 * 
	 * #pw-internal
	 * 
	 * @param $fp
	 * @param $options
	 * @return array|false
	 * @since 3.0.197
	 * 
	 */
	protected function fgetcsv($fp, $options) {
		$defaults = array(
			'length' => 0,
			'separator' => ',',
			'enclosure' => '"',
			'escape' => "\\",
		);
		$options = array_merge($defaults, $options);
		return fgetcsv($fp, $options['length'], $options['separator'], $options['enclosure'], $options['escape']); 
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

		$paths = $this->wire()->config->paths;
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
			if($options['throwExceptions']) $this->filesException(__FUNCTION__, $error);
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
				$error .= ' Paths: ' . implode("\n", $options['allowedPaths']);
				if($options['throwExceptions']) $this->filesException(__FUNCTION__, $error);
				$this->error($error);
				return false;
			}
		}
		
		if($options['cache']) {
			$cache = $this->wire()->cache;
			$o = $options;
			unset($o['cache']); 
			$o['vars'] = $vars; 
			return $cache->renderFile($filename, $options['cache'], $o); 
		}

		// render file and return output
		$t = $this->wire(new TemplateFile()); /** @var TemplateFile $t */
		$t->setThrowExceptions($options['throwExceptions']);
		$t->setFilename($filename);

		foreach($vars as $key => $value) {
			$t->data($key, $value);
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
	public function ___include($filename, array $vars = array(), array $options = array()) {

		$paths = $this->wire()->config->paths;
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

		// need to use different name than $options
		$options = array_merge($defaults, $options);
		$filename = trim($filename);

		// add .php extension if filename doesn't already have an extension
		if($options['autoExtension'] && !strrpos(basename($filename), '.')) {
			$filename .= '.' . $options['autoExtension'];
		}

		if(strpos($filename, '..') !== false) {
			// if backtrack/relative components, convert to real path
			$_filename = $filename;
			$filename = realpath($filename);
			if($filename === false) $this->filesException(__FUNCTION__, "File does not exist: $_filename");
		}
		
		$filename = $this->unixFileName($filename);

		if(strpos($filename, '//') !== false) {
			$this->filesException(__FUNCTION__, "File is not allowed (double-slash): $filename");
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
				if($allowed) break;
			}
			if(!$allowed) $this->filesException(__FUNCTION__, "File is not in an allowed path: $filename");
		}

		if(!file_exists($filename)) $this->filesException(__FUNCTION__, "File does not exist: $filename");

		// remember options[func] and $filename outside this method because of the extract() which can overwrite
		$this->includeFunc = $options['func'];
		$this->includeFile = $filename;
		
		// extract all API vars
		$fuel = array_merge($this->wire()->fuel->getArray(), $vars);
		extract($fuel);

		// include the file
		TemplateFile::pushRenderStack($this->includeFile);
		if($this->includeFunc === 'require') {
			require($this->includeFile);
		} else if($this->includeFunc === 'require_once') {
			require_once($this->includeFile);
		} else if($this->includeFunc === 'include_once') {
			include_once($this->includeFile);
		} else {
			include($this->includeFile);
		}
		TemplateFile::popRenderStack();

		return true;
	}

	protected $includeFunc = '';
	protected $includeFile = '';

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
	 * @since 3.0.96
	 * 
	 */
	public function includeOnce($filename, array $vars = array(), array $options = array()) {
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
			$data = trim($file);
		} else {
			$data = file_get_contents($file);
			if($data === false) return $namespace;
			$data = trim($data);
		}

		// if there's no "namespace" keyword in the file, it's not declaring one
		$namespacePos = strpos($data, 'namespace');
		if($namespacePos === false) return $namespace;

		// quick optimization for common ProcessWire namespace usage
		if(strpos($data, '<' . '?php namespace ProcessWire;') === 0) return 'ProcessWire';

		// if file doesn't start with an opening PHP tag, then it's not going to have a namespace declaration
		$phpOpen = strpos($data, '<' . '?');
		if($phpOpen !== 0) {
			// file does not begin with opening php tag	
			// note: this fails for PHP files executable on their own (like shell scripts)
			return $namespace;
		}

		// find where line ends after "namespace ..." keyword
		foreach(array("\n", "\r", ";") as $c) {
			$eol = strpos($data, $c, $namespacePos);
			if($eol !== false) break;
		}
		
		// get everything that appears before "namespace", and after "namespace" on same line
		$head = $eol === false ? $data : substr($data, 0, $eol);
		$headPrev = $head;
		
		// single line comment(s) appear before namespace
		if(strpos($head, '//') !== false) {
			$head = preg_replace('!//[^\r\n]*!', '', $head);
		}

		// single or multi-line comments before namespace
		if(strpos($head, '/' . '*') !== false) {
			$head = preg_replace('!/\*.*\*/!s', '', $head);
		}

		// declare(...); is the one statement allowed to appear before namespace in PHP files
		if(strpos($head, 'declare')) {
			$head = preg_replace('/declare[ ]*\(.+?\)[ ]*;\s*/s', '', $head);
		}
		
		// replace cleaned up head in data
		if($head !== $headPrev) {
			$data = str_replace($headPrev, $head, $data);
		}

		$namespacePos = strpos($data, 'namespace'); // get fresh position
		if($namespacePos === false) return $namespace; // was likely in a comment
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
			$f = str_replace($this->wire()->config->paths->root, '', $file);
			if(isset($compiled[$f])) return $compiled[$f];
		} else {
			$f = '';
		}
		/** @var FileCompiler $compiler */
		$compiler = $this->wire(new FileCompiler(dirname($file), $options));
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

	/**
	 * Report/log/throw an error
	 * 
	 * #pw-internal
	 * 
	 * @param string $method
	 * @param string $msg
	 * @param bool|array $throw Throw exception? May be boolean or array with 'throw' index containing boolean.
	 * @param \Exception|null $e Previous exception, if applicable
	 * @return bool Always returns boolean false (so it can be used in error return statements)
	 * @throws WireFilesException
	 * @since 3.0.178
	 * 
	 */
	public function filesError($method, $msg, $throw = false, $e = null) {
		if(is_array($throw)) $throw = isset($throw['throw']) ? $throw['throw'] : false;
		$msg = "$method: $msg";
		$this->log($msg, array('name' => 'files-errors'));
		if($throw) {
			if($e) throw new WireFilesException($msg, $e->getCode(), $e);
			throw new WireFilesException($msg);
		} else if($this->wire()->config->debug) {
			$this->warning($msg, Notice::debug);
		}
		return false;
	}

	/**
	 * Throw a files exception
	 * 
	 * #pw-internal
	 * 
	 * @param string $method
	 * @param string $msg
	 * @param \Exception|null $e
	 * @throws WireFilesException
	 * @since 3.0.178
	 * 
	 */
	public function filesException($method, $msg, $e = null) {
		$this->filesError($method, $msg, true, $e);
	}
	
	/**
	 * Log a message for this class
	 *
	 * #pw-internal
	 *
	 * @param string $str Text to log, or omit to return the `$log` API variable.
	 * @param array $options Optional extras to include, see Wire::___log()
	 * @return WireLog
	 *
	 */
	public function ___log($str = '', array $options = array()) {
		if(empty($options['name'])) $options['name'] = 'files';
		return parent::___log($str, $options);
	}


}
