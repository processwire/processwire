<?php namespace ProcessWire;

/**
 * ProcessWire Temporary Directory Manager
 *
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireTempDir extends Wire {

	/**
	 * File automatically placed in created temp dirs for verification, contains timestamp
	 * 
	 */
	const hiddenFileName = '.wtd';
	
	protected $classRoot = null; 	// example: /site/assets/WireTempDir/ 
	protected $tempDirRoot = null; 	// example: /site/assets/WireTempDir/.SomeName/
	protected $tempDir = null;		// example: /site/assets/WireTempDir/.SomeName/1/
	protected $tempDirMaxAge = 120; // maximum age in seconds
	protected $autoRemove = true; 	// automatially remove temp dir when this class is destructed?
	protected $createdName = '';    // name of runtime created random tempDirRoot, when applicable

	/**
	 * Construct new temp dir
	 * 
	 * While this constructor accepts arguments, if you are in a multi-instance environment you should instead construct
	 * with no arguments, inject the ProcessWire instance, and then call the create() method with those arguments. 
	 * 
	 * @param string|object $name Recommend providing the object that is using the temp dir, but can also be any string 
	 * @param string $basePath Base path where temp dirs should be created. Omit to use default (recommended). 
	 * @throws WireException if given a $root that doesn't exist
	 * 
	 */
	public function __construct($name = '', $basePath = '') {
		if($name) $this->create($name, $basePath); 
	}

	/**
	 * Create the temporary directory
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
	public function create($name = '', $basePath = '') {

		if(!is_null($this->tempDirRoot)) throw new WireException("Temp dir has already been created");
		if(empty($name)) $name = $this->createName();
		if(is_object($name)) $name = wireClassName($name, false);

		if($basePath) {
			// they provide base path
			$basePath = rtrim($basePath, '/') . '/'; // ensure it ends with trailing slash
			if(!is_dir($basePath)) throw new WireException("Provided base path doesn't exist: $basePath");
			if(!is_writable($basePath)) throw new WireException("Provided base path is not writiable: $basePath");

		} else {
			// we provide base path (root)
			$basePath = $this->wire('config')->paths->cache;
			if(!is_dir($basePath)) $this->mkdir($basePath);
		}

		$basePath .= wireClassName($this, false) . '/';
		$this->classRoot = $basePath;
		if(!is_dir($basePath)) $this->mkdir($basePath);

		$this->tempDirRoot = $basePath . ".$name/";
		
		return $this->tempDirRoot;
	}

	/**
	 * Create a randomized name for runtime temp dir
	 * 
	 * @param string $prefix Optional prefix for name
	 * @return string
	 * 
	 */
	public function createName($prefix = '') {
		$pass = new Password();
		$this->wire($pass);
		$len = mt_rand(10, 30);
		$name = microtime() . '.' . $pass->randomBase64String($len, true);
		$a = explode('.', $name);
		shuffle($a);
		$name = $prefix . implode('O', $a);
		$this->createdName = $name;
		return $name;
	}
	
	/**
	 * Set the max age of temp files (default=120 seconds)
	 * 
	 * @param $seconds
	 * @return $this
	 * 
	 */
	public function setMaxAge($seconds) {
		$this->tempDirMaxAge = (int) $seconds; 
		return $this; 
	}

	/**
	 * Call this with 'false' to prevent temp dir from being removed automatically when object is destructed
	 * 
	 * @param bool $remove
	 * @return $this
	 * 
	 */
	public function setRemove($remove = true) {
		$this->autoRemove = (bool) $remove; 	
		return $this;
	}
	
	public function __destruct() {
		if($this->autoRemove) $this->remove();
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
		
		if(is_null($this->tempDirRoot)) throw new WireException("Please call the create() method before the get() method"); 

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
		
		static $classRuns = 0;

		$errorMessage = 'Unable to remove temp dir';
		$success = true; 
	
		if(is_null($this->tempDirRoot) || is_null($this->tempDir)) {
			// nothing to remove
			return true;
		}
	
		if(is_dir($this->tempDir)) {
			// remove temporary directory created by this instance
			if(!$this->rmdir($this->tempDir, true)) {
				$this->log("$errorMessage: $this->tempDir");
				$success = false;
			}
		}
	
		if(is_dir($this->tempDirRoot)) {
			if($this->createdName && strpos($this->tempDirRoot, "/.$this->createdName")) {
				// if tempDirRoot is just for this PW instance, we can remove it now
				$this->rmdir($this->tempDirRoot, true);
			} else {
				// if it is potentially used by multiple instances, then remove only expired files
				$this->removeExpiredDirs($this->tempDirRoot);
			}
		}
		
		if(!$classRuns && is_dir($this->classRoot)) {
			$this->removeExpiredDirs($this->classRoot, 86400); 
		}
	
		$classRuns++;
		
		return $success; 
	}

	/**
	 * Remove expired directories in the given $path
	 * 
	 * Also removes $path if it's found that everything in it is expired.
	 * 
	 * @param string $path
	 * @param int|null Optionally specify a max age to override default setting.
	 * @return bool
	 * 
	 */
	protected function removeExpiredDirs($path, $maxAge = null) {
		
		if(!is_dir($path)) return false;
		if(!is_int($maxAge)) $maxAge = $this->tempDirMaxAge;
		
		$numSubdirs = 0;
		$pathname = '';
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
			$pathname = rtrim($dir->getPathname(), "/\\") . DIRECTORY_SEPARATOR;
			
			// if our .wtd identifier file isn't present, ignore the directory
			if(!is_file($pathname . self::hiddenFileName)) continue;
			
			$removeDir = true;
			$newestFileTime = $this->getNewestModTime($pathname); 
			if($newestFileTime >= $oldestAllowedFileTime) $removeDir = false;
		
			if($removeDir) {
				if(!$this->rmdir($pathname, true)) {
					$this->log("Unable to remove: $path");
					$success = false;
				}
			} else {
				$numSubdirs++;
			}
		}

		if(!$numSubdirs) {
			// if no subdirectories, we can remove the root
			if($this->rmdir($path, true)) {
				$success = true;
			} else {
				$this->log("Unable to remove: $pathname");
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
		if($this->classRoot && is_dir($this->classRoot)) {
			// note: use of $files->rmdir rather than $this->rmdir is intentional
			return $this->wire('files')->rmdir($this->classRoot, true);
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
		if($this->wire('files')->mkdir($dir, $recursive)) {
			$dir = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR;
			file_put_contents($dir . self::hiddenFileName, time());
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
		$dir = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR;
		if(!is_file($dir . self::hiddenFileName)) return false;
		unlink($dir . self::hiddenFileName);
		return $this->wire('files')->rmdir($dir, $recursive);
	}
}
