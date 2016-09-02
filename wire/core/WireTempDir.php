<?php namespace ProcessWire;

/**
 * ProcessWire Temporary Directory Manager
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class WireTempDir extends Wire {
	
	protected $classRoot = null; 	// example: /site/assets/WireTempDir/ 
	protected $tempDirRoot = null; 	// example: /site/assets/WireTempDir/.SomeName/
	protected $tempDir = null;		// example: /site/assets/WireTempDir/.SomeName/1/
	protected $tempDirMaxAge = 120; // maximum age in seconds
	protected $autoRemove = true; 	// automatially remove temp dir when this class is destructed?

	/**
	 * Construct new temp dir
	 * 
	 * @param string|object $name Recommend providing the object that is using the temp dir, but can also be any string 
	 * @param string $basePath Base path where temp dirs should be created. Omit to use default (recommended). 
	 * @throws WireException if given a $root that doesn't exist
	 * 
	 */
	public function __construct($name, $basePath = '') {
		
		if(is_object($name)) $name = wireClassName($name, false); 
		if(empty($name) || !is_string($name)) throw new WireException("A valid name (string) must be provided"); 
		
		if($basePath) {
			// they provide base path
			$basePath = rtrim($basePath, '/') . '/'; // ensure it ends with trailing slash
			if(!is_dir($basePath)) throw new WireException("Provided base path doesn't exist: $basePath"); 
			if(!is_writable($basePath)) throw new WireException("Provided base path is not writiable: $basePath"); 
			
		} else {
			// we provide base path (root)
			$basePath = $this->wire('config')->paths->cache;
			if(!is_dir($basePath)) $this->wire('files')->mkdir($basePath);
		}
		
		$basePath .= wireClassName($this, false) . '/';
		$this->classRoot = $basePath; 
		if(!is_dir($basePath)) $this->wire('files')->mkdir($basePath); 
		
		$this->tempDirRoot = $basePath . ".$name/";
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
	 * @return string Returns path
	 * @throws WireException If can't create temporary dir
	 *
	 */
	public function get() {

		// first check if cached result from previous call
		if(!is_null($this->tempDir) && file_exists($this->tempDir)) return $this->tempDir;

		// find unique temp dir
		$n = 0;
		do {
			$n++;
			$tempDir = $this->tempDirRoot . "$n/";
			$exists = is_dir($tempDir);
			if($exists) {
				// check if we can remove existing temp dir
				$time = filemtime($tempDir);
				if($time < time() - $this->tempDirMaxAge) { // dir is old and can be removed
					if($this->wire('files')->rmdir($tempDir, true)) $exists = false;
				}
			}
		} while($exists);

		// create temp dir
		if(!$this->wire('files')->mkdir($tempDir, true)) {
			clearstatcache();
			if(!is_dir($tempDir) && !$this->wire('files')->mkdir($tempDir, true)) {
				throw new WireException($this->_('Unable to create temp dir') . " - $tempDir");
			}
		}

		// cache result
		$this->tempDir = $tempDir;
	
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

		$errorMessage = $this->_('Unable to remove temp dir');
		$success = true; 
	
		if(is_null($this->tempDirRoot) || is_null($this->tempDir)) {
			// nothing to remove
			return true;
		}
	
		if(is_dir($this->tempDir)) {
			// remove temporary directory created by this instance
			if(!wireRmdir($this->tempDir, true)) {
				$this->error("$errorMessage - $this->tempDir");
				$success = false;
			}
		}
	
		if(is_dir($this->tempDirRoot)) {
			// remove temporary directories created by other instances (like if one had failed at some point)
			$numSubdirs = 0;
			$pathname = '';
			foreach(new \DirectoryIterator($this->tempDirRoot) as $dir) {
				if(!$dir->isDir() || $dir->isDot()) continue;
				if($dir->getMTime() < (time() - $this->tempDirMaxAge)) {
					// old dir found
					$pathname = $dir->getPathname();
					if(!wireRmdir($pathname, true)) {
						$this->error("$errorMessage - $pathname");
						$success = false;
					}
				} else {
					$numSubdirs++;
				}
			}
			if(!$numSubdirs) {
				// if no subdirectories, we can remove the root
				if(wireRmdir($this->tempDirRoot, true)) {
					$success = true; 
				} else {
					$this->error("$errorMessage - $pathname");
					$success = false;
				}
			}
		}
		
		return $success; 
	}

	/**
	 * Clear all temporary directories created by this class
	 * 
	 */
	public function removeAll() {
		if($this->classRoot && is_dir($this->classRoot)) return wireRmdir($this->classRoot, true); 
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
}
