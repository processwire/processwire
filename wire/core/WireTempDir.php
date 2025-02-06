<?php namespace ProcessWire;

/**
 * ProcessWire Temporary Directory Manager
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireTempDir extends Wire {

	/**
	 * File automatically placed in created temp dirs for verification, contains timestamp
	 * 
	 */
	const hiddenFileName = '.wtd';

	/**
	 * True when remove method has been called at least once
	 * 
	 * This is to ensure later instances don’t perform redundant cleanup tasks. 
	 * 
	 * @var bool
	 * 
	 */
	static protected $maintenanceCompleted = false;	
	
	protected $classRoot = null; 	// example: /site/assets/WireTempDir/ 
	protected $tempDirRoot = null; 	// example: /site/assets/WireTempDir/.SomeName/
	protected $tempDir = null;		// example: /site/assets/WireTempDir/.SomeName/1/
	protected $tempDirMaxAge = 120; // maximum age in seconds for files in a user-specified name
	protected $cleanMaxAge = 86400; // age after which files an be assumed to be abandoned and need cleanup
	protected $autoRemove = true; 	// automatically remove temp dir when this class is destructed?
	protected $createdName = '';    // name of runtime created random tempDirRoot, when applicable


	/**
	 * Construct 
	 * 
	 * @param string|object $name DEPRECATED (Call 'init' method instead)
	 * @param string $basePath DEPRECATED (Call 'init' method instead)
	 * 
	 */
	public function __construct($name = '', $basePath = '') {
		parent::__construct();
		if($name) $this->init($name, $basePath); 
	}

	/**
	 * Destruct
	 * 
	 */
	public function __destruct() {
		if($this->autoRemove) $this->remove();
	}

	/**
	 * Initialize temporary directory
	 * 
	 * This method should only be called once per instance of this class. If you specified a $name argument
	 * in the constructor, then you should not call this method because it will have already been called. 
	 * 
	 * @param string|object $name Recommend providing the object that is using the temp dir, but can also be any string
	 * @param string $basePath Base path where temp dirs should be created. Omit to use default (recommended).
	 * @throws WireException if given a $root that doesn't exist
	 * @return string Returns the root of the temporary directory. Use the get() method to get a dir for use. 
	 *
	 */
	public function init($name = '', $basePath = '') {

		if(!is_null($this->tempDirRoot)) throw new WireException("Temp dir has already been created");
		if(empty($name)) $name = $this->createName();
		if(is_object($name)) $name = wireClassName($name, false);
		
		if($basePath && !$this->wire()->files->allowPath($basePath, true)) {
			$this->log("Given base path $basePath is not within ProcessWire assets so has been replaced");
			$basePath = '';
		}

		$basePath = $this->classRootPath(true, $basePath);
		$this->classRoot = $basePath;
		$this->tempDirRoot = $basePath . ".$name/";
		
		return $this->tempDirRoot;
	}

	/**
	 * Return the class root path for cache files (i.e. /path/to/site/assets/cache/WireTempDir/)
	 * 
	 * @param bool $createIfNotExists Create the directory if it does not exist? (default=false)
	 * @param string $basePath Path to start from (default=/path/to/site/assets/cache/)
	 * @return string
	 * @throws WireException
	 * @since 3.0.175
	 * 
	 */
	protected function classRootPath($createIfNotExists = false, $basePath = '') {
		if($basePath) {
			// they provide base path
			$basePath = rtrim($basePath, '/') . '/'; // ensure it ends with trailing slash
			if(!is_dir($basePath)) throw new WireException("Provided base path doesn't exist: $basePath");
			if(!is_writable($basePath)) throw new WireException("Provided base path is not writiable: $basePath");
		} else {
			// we provide base path (root)
			$basePath = $this->wire()->config->paths->cache; 
			if($createIfNotExists && !is_dir($basePath)) $this->mkdir($basePath);
		}
		$basePath .= wireClassName($this, false) . '/'; // i.e. /path/to/site/assets/cache/WireTempDir/
		if($createIfNotExists && !is_dir($basePath)) $this->mkdir($basePath);
		return $basePath;
	}

	/**
	 * Create a randomized name for runtime temp dir
	 * 
	 * @param string $prefix Optional prefix for name
	 * @return string
	 * 
	 */
	public function createName($prefix = '') {
		$random = new WireRandom();
		$len = $random->integer(10, 20);
		$name = $prefix . str_replace(' ', 'T', microtime()) . 'R' . $random->alphanumeric($len);
		$this->createdName = $name;
		return $name;
	}
	
	/**
	 * Set the max age of temp files and/or maintenance cleanup max age 
	 * 
	 * #pw-internal
	 * 
	 * @param int|null $tempDirMaxAge Temp dir max age in seconds (default=120)
	 * @param int|null $cleanMaxAge Maintenance cleanup max age in seconds (default=86400) 3.0.175+
	 * @return $this
	 * 
	 */
	public function setMaxAge($tempDirMaxAge = null, $cleanMaxAge = null) {
		if(is_int($tempDirMaxAge)) $this->tempDirMaxAge = $tempDirMaxAge;
		if(is_int($cleanMaxAge)) $this->cleanMaxAge = $cleanMaxAge; 
		return $this; 
	}

	/**
	 * Call this with 'false' to prevent temp dir from being removed automatically when object is destructed
	 * 
	 * If you do this, then you accept responsibility for removing the directory by calling $tempDir->remove(); 
	 * If you do not remove it yourself, WireTempDir will remove as part of the daily maintenance. 
	 * 
	 * @param bool $remove
	 * @return $this
	 * 
	 */
	public function setRemove($remove = true) {
		$this->autoRemove = (bool) $remove; 	
		return $this;
	}
	
	/**
	 * Returns a temporary directory (path) 
	 *
	 * @param string $id Optional identifier to use (default=autogenerate)
	 * @return string Returns path
	 * @throws WireException If can't create temporary dir
	 *
	 */
	public function get($id = '') {
		
		static $level = 0;
		
		if(is_null($this->tempDirRoot)) $this->init();

		// first check if cached result from previous call
		if(!is_null($this->tempDir) && file_exists($this->tempDir)) return $this->tempDir;

		// find unique temp dir
		$level++;
		$n = 0;
		do {
			if($id) {
				$tempDir = $this->tempDirRoot . $id . ($n ? "$n/" : "/");
				if(!$n) $id .= "-"; // i.e. id-1, for next iterations
			} else {
				$tempDir = $this->tempDirRoot . "$n/";
			}
			if(!is_dir($tempDir)) break;
			$n++;
			/*
			if($exists) {
				// check if we can remove existing temp dir
				$time = filemtime($tempDir);
				if($time < time() - $this->tempDirMaxAge) { // dir is old and can be removed
					if($this->rmdir($tempDir, true)) $exists = false;
				}
			}
			*/
		} while(1);

		// create temp dir
		if(!$this->mkdir($tempDir, true)) {
			clearstatcache();
			if(!is_dir($tempDir) && !$this->mkdir($tempDir, true)) {
				if($level < 5) {
					// try again, recursively
					clearstatcache();
					$tempDir = $this->get($id . "L$level");
				} else {
					$level--;
					throw new WireException("Unable to create temp dir: $tempDir");
				}
			}
		}

		// cache result
		$this->tempDir = $tempDir;
		$level--;
		
		return $tempDir;
	}
	
	/**
	 * Removes the temporary directory created by this object
	 * 
	 * Note that the directory is automatically removed when this object is destructed.
	 * 
	 * @return bool 
	 *
	 */
	public function remove() {
		
		$errorMessage = 'Unable to remove temp dir';
		$success = true; 
	
		if(is_null($this->tempDirRoot) || is_null($this->tempDir)) {
			// nothing to remove
			return true;
		}
	
		if($this->tempDir && is_dir($this->tempDir)) {
			// remove temporary directory created by this instance
			if(!$this->rmdir($this->tempDir, true)) {
				$this->log("$errorMessage: $this->tempDir");
				$success = false;
			}
		}
	
		if($this->tempDirRoot && is_dir($this->tempDirRoot)) {
			if($this->createdName && strpos($this->tempDirRoot, "/.$this->createdName")) {
				// if tempDirRoot is just for this PW instance, we can remove it now
				$this->rmdir($this->tempDirRoot, true);
			} else {
				// if it is potentially used by multiple instances, then remove only expired files
				$this->removeExpiredDirs($this->tempDirRoot, $this->tempDirMaxAge);
			}
		}
		
		if(!self::$maintenanceCompleted && $this->classRoot && is_dir($this->classRoot)) {
			$this->removeExpiredDirs($this->classRoot, $this->cleanMaxAge); 
		}
	
		self::$maintenanceCompleted = true;
		
		return $success; 
	}

	/**
	 * Remove expired directories in the given $path
	 * 
	 * Also removes $path if it's found that everything in it is expired.
	 * 
	 * @param string $path
	 * @param int Max age in seconds
	 * @return bool
	 * 
	 */
	protected function removeExpiredDirs($path, $maxAge) {
		
		if(!is_dir($path)) return false;
		if(!is_int($maxAge)) $maxAge = $this->tempDirMaxAge;
		
		$numSubdirs = 0;
		$oldestAllowedFileTime = time() - $maxAge;
		$success = true;
		
		foreach(new \DirectoryIterator($path) as $dir) {
			
			if(!$dir->isDir() || $dir->isDot()) continue;
		
			// if the directory itself is not expired, then nothing in it is either
			if($dir->getMTime() >= $oldestAllowedFileTime) {
				$numSubdirs++;
				continue;
			}
			
			// old dir found: check times on files/dirs within that dir
			$pathname = $this->wire()->files->unixDirName($dir->getPathname());
			if(!$this->isTempDir($pathname)) continue;
			
			$removeDir = true;
			$newestFileTime = $this->getNewestModTime($pathname); 
			if($newestFileTime >= $oldestAllowedFileTime) $removeDir = false;
		
			if($removeDir) {
				if(!$this->rmdir($pathname, true)) {
					$this->log("Unable to remove (B): $pathname");
					$success = false;
				}
			} else {
				$numSubdirs++;
			}
		}

		if(!$numSubdirs && $path != $this->classRoot && $this->isTempDir($path)) {
			// if no subdirectories, we can remove the root
			if($this->rmdir($path, true)) {
				$success = true;
			} else {
				$this->log("Unable to remove (A): $path");
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get the newest modification time of a file in $path, recursively
	 * 
	 * @param string $path Path to start from
	 * @param int $maxDepth 
	 * @return int
	 * 
	 */
	protected function getNewestModTime($path, $maxDepth = 5) {
		static $level = 0;
		$level++;
		// check if any files in the directory are newer than maxAge
		$newest = filemtime($path);
		foreach(new \DirectoryIterator($path) as $file) {
			if($file->isDot()) continue;
			$mtime = $file->getMTime();
			if($mtime > $newest) $newest = $mtime;
			if($level < $maxDepth && $file->isDir()) {
				$mtime = $this->getNewestModTime($file->getPathname(), $maxDepth);
				if($mtime > $newest) $newest = $mtime;
			}
		}
		$level--;
		return $newest;
	}

	/**
	 * Clear all temporary directories created by this class
	 * 
	 */
	public function removeAll() {
		$classRoot = $this->classRoot;
		if(empty($classRoot)) $classRoot = $this->classRootPath(false);
		if($classRoot && is_dir($classRoot)) {
			// note: use of $files->rmdir rather than $this->rmdir is intentional
			return $this->wire()->files->rmdir($classRoot, true);
		}
		return false;
	}	

	/**
	 * Accessing this object as a string returns the temp dir
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return $this->get();
	}

	/**
	 * Create a temporary directory
	 * 
	 * @param string $dir
	 * @param bool $recursive
	 * @return bool
	 * 
	 */
	protected function mkdir($dir, $recursive = false) {
		/** @var WireFileTools $files */
		$files = $this->wire('files');
		$dir = $files->unixDirName($dir);
		if($files->mkdir($dir, $recursive)) {
			$files->filePutContents($dir . self::hiddenFileName, time());
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Remove a temporary directory
	 * 
	 * @param string $dir
	 * @param bool $recursive
	 * @return bool
	 * 
	 */
	protected function rmdir($dir, $recursive = false) {
		$files = $this->wire()->files;
		$dir = $files->unixDirName($dir);
		if(!strlen($dir) || !is_dir($dir)) return true;
		if(!$this->isTempDir($dir)) return false;
		if(is_file($dir . self::hiddenFileName)) $this->wire('files')->unlink($dir . self::hiddenFileName, true);
		return $files->rmdir($dir, $recursive, true);
	}

	/**
	 * Is given directory/path created by this class?
	 * 
	 * @param string $dir
	 * @return bool
	 * 
	 */
	protected function isTempDir($dir) {
		$files = $this->wire()->files;
		if(!strlen($dir) || !is_dir($dir)) {
			// if given a non-directory return false
			return false;
		}
		if($this->classRoot && $files->fileInPath($dir, $this->classRoot)) {
			// dir is within classRoot path
			return true;
		}
		return false;
	}
	
	/**
	 * Perform maintenance by cleaning up old temporary directories
	 * 
	 * Note: This is done automatically if any temporary directories are created during the request.
	 *
	 * @throws WireException
	 * @return bool
	 * @since 3.0.175
	 *
	 */
	public function maintenance() {
		if(self::$maintenanceCompleted) return true;
		$classRoot = $this->classRoot ? $this->classRoot : $this->classRootPath(false);
		$result = $this->removeExpiredDirs($classRoot, $this->cleanMaxAge);
		self::$maintenanceCompleted = true;
		return $result;
	}

	
	/**
	 * @deprecated Use init() method instead
	 * @param string $name
	 * @param string $basePath
	 * @return string
	 *
	 */
	public function create($name = '', $basePath = '') {
		return $this->init($name, $basePath);
	}


}
