<?php namespace ProcessWire;

/**
 * ProcessWire PagefilesManager
 *
 * #pw-summary Manages files and file directories for a page independent of a particular field.
 * #pw-summary-static These methods are not connected with a particular instance and may only be called statically.
 * #pw-body =
 * Files in ProcessWire are always connected with a particular `Field` on a `Page`. This is typically
 * a `FieldtypeFile` field or a `FieldtypeImage` field, which exist as `Pagefiles` or `Pageimages`
 * values on the Page. Sometimes it is necessary to manage all files connected with a page as a
 * group, and this files manager class provides that. Likewise, something needs to manage the paths
 * and URLs where these files live, and that is where this files manager comes into play as well.
 * 
 * **Summary of what PagefilesManager does**
 *
 * - Provides methods for movement of all files connected with a page as a group.
 * - Ensures that file directories for a page are created (and removed) when applicable.
 * - Manages secured vs. normal page file paths (see `$config->pagefileSecure`).
 * - Manages extended vs. normal page file paths (see `$config->pagefileExtendedPaths`). 
 * 
 * **How to access the Page files manager**
 * 
 * The Page files manager can be accessed from any page’s `Page::filesManager()` method or property.
 * 
 * ~~~~~
 * // Example of getting a Page’s dedicated file path and URL
 * $filesPath = $page->filesManager->path();
 * $filesURL = $page->filesManager->url();
 * ~~~~~
 * 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @method save() #pw-hooker
 * @property string $path 
 * @property string $url 
 * @property Page $page Page that this files manager is for. 
 * 
 *
 */

class PagefilesManager extends Wire {

	/**
	 * Default prefix for secure paths when not defined by config.php
	 *
	 */
	const defaultSecurePathPrefix = '.';

	/**
	 * Prefix to all extended path directories
	 *
	 */
	const extendedDirName = '0/';

	/**
	 * Name of file that maintains the last modification time independent of directory (LATER/FUTURE)
	 * 
	const metaFileName = '.pw';
	 */

	/**
	 * Count of renamed paths when changing between pagefileSecure and non-pagefileSecure
	 * 
	 * @var int
	 * 
	 */
	static $numRenamedPaths = 0;

	/**
	 * Reference to the Page object this PagefilesManager is managing
	 * 
	 * @var Page
	 *
	 */
	protected $page;

	/**
	 * Cached copy of $path, once it has been determined
	 * 
	 * @var string|null
	 *
	 */
	protected $path = null;

	/**
	 * Cached copy of $url, once it has been determined
	 * 
	 * @var string|null
	 *
	 */
	protected $url = null;
	
	/**
	 * Construct the PagefilesManager and ensure all needed paths are created
	 *
	 * @param Page $page
	 *
	 */
	public function __construct(Page $page) {
		parent::__construct();
		$page->wire($this);
		$this->init($page); 
	}

	/**
	 * Initialize the PagefilesManager with a Page
	 *
 	 * Same as construct, but separated for when cloning a page
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 *
	 */
	public function init(Page $page) {
		$this->setPage($page); 
		$this->path = null; // to uncache, if this PagefilesManager has been cloned
		$this->createPath();
	}

	/**
	 * Set the page 
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 *
	 */
	public function setPage(Page $page) {
		$this->page = $page; 
	}

	/** 
	 * Get an array of all published filenames on the current Page.
	 * 
	 * @return array Array of file basenames
	 *
	 */
	public function getFiles() {
		$files = array();
		foreach(new \DirectoryIterator($this->path()) as $file) {
			if($file->isDot() || $file->isDir()) continue; 
			// if($file->isFile()) $files[] = $file->getBasename(); // PHP 5.2.2
			if($file->isFile()) $files[] = $file->getFilename();
		}
		return $files; 
	}
	
	/**
	 * Get the Pagefile object containing the given filename.
	 * 
	 * @param string $name Name of file to get: 
	 *   - If given a URL or path, this will traverse to other pages. 
	 *   - If given a basename, it will stay with current page.
	 * @return Pagefile|Pageimage|null Returns Pagefile or Pageimage object if found, or null if not.
	 *
	 */
	public function getFile($name) {
		$pagefile = null;
		if(strpos($name, '/') !== false) {
			$pageID = self::dirToPageID($name);
			if(!$pageID) return null;
			if($pageID == $this->page->id) {
				$page = $this->page;
			} else {
				$page = $this->wire()->pages->get($pageID);
			}
			if(!$page->id) return null;
		} else {
			$page = $this->page;
		}
		foreach($page->fieldgroup as $field) {
			/** @var Field $field */
			if(!$field->type instanceof FieldtypeFile) continue;
			$pagefiles = $page->get($field->name);
			// if mapping to single file, ask it for the parent array
			if($pagefiles instanceof Pagefile) $pagefiles = $pagefiles->pagefiles;
			if($pagefiles instanceof Pagefiles) $pagefile = $pagefiles->getFile($name);
			if(!$pagefile) continue;
			break;
		}
		return $pagefile;
	}


	/**
	 * Recursively copy all files in $fromPath to $toPath, for internal use
	 *
	 * @param string $fromPath Path to copy from
	 * @param string $toPath Path to copy to
	 * @param bool $rename Rename files rather than copy? (makes this perform like a move rather than copy)
	 * @return int Number of files copied
	 *
	 */
	protected function _copyFiles($fromPath, $toPath, $rename = false) {

		if(!is_dir($toPath)) return 0; 

		$numCopied = 0;
		$fromPath = rtrim($fromPath, '/') . '/';
		$toPath = rtrim($toPath, '/') . '/';
	
		foreach(new \DirectoryIterator($fromPath) as $file) {
			if($file->isDot()) continue; 
			
			if($file->isDir()) {
				$fromDir = $file->getPathname();
				$toDir = $toPath . $file->getFilename() . '/';
				if($rename && rename($fromDir, $toDir)) {
					$numCopied++;
				} else {
					$this->_createPath($toDir); 
					$numCopied += $this->_copyFiles($fromDir, $toDir, $rename); 
					if($rename) wireRmdir($fromDir, true); // this line not likely to ever be executed
				}
				
			} else if($file->isFile()) {
				$fromFile = $file->getPathname();
				$toFile = $toPath . $file->getFilename();
				$success = $rename ? rename($fromFile, $toFile) : copy($fromFile, $toFile);
				if($success) { 
					$numCopied++;
					// $this->message("Copied $fromFile => $toFile", Notice::debug); 
				} else {
					$this->error("Failed to copy: $fromFile"); 
				}
			}
		}

		return $numCopied; 
	}

	/**
	 * Recursively copy all files managed by this PagefilesManager into a new path.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param $toPath string Path to copy files into. 
	 * @return int Number of files/directories copied. 
	 *
	 */
	public function copyFiles($toPath) {
		return $this->_copyFiles($this->path(), $toPath); 
	}

	/**
	 * Copy/import files from given path into the page’s files directory
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $fromPath Path to copy/import files from. 
	 * @param bool $move Move files into directory rather than copy?
	 * @return int Number of files/directories copied.
	 * @since 3.0.114
	 * 
	 */
	public function importFiles($fromPath, $move = false) {
		return $this->_copyFiles($fromPath, $this->path(), $move); 
	}

	/**
	 * Replace all page’s files with those from given path
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param string $fromPath
	 * @param bool $move Move files to destination rather than copy? (default=false)
	 * @return int Number of files/directories copied.
	 * @throws WireException if given a path that does not exist.
	 * @since 3.0.114
	 * 
	 * 
	 */
	public function replaceFiles($fromPath, $move = false) {
		if(!is_dir($fromPath)) throw new WireException("Path does not exist: $fromPath"); 
		$this->emptyPath();
		return $this->_copyFiles($fromPath, $this->path(), $move);
	}

	/**
	 * Recursively move all files managed by this PagefilesManager into a new path.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param $toPath string Path to move files into.
	 * @return int Number of files/directories moved.
	 *
	 */
	public function moveFiles($toPath) {
		$this->_createPath($toPath); 
		return $this->_copyFiles($this->path(), $toPath, true); 
	}

	/**
	 * Create a directory with proper permissions, for internal use. 
	 *
	 * @param string $path Path to create
	 * @return bool True on success, false if not
	 *
	 */
	protected function _createPath($path) {
		if(empty($path)) return false;
		if(is_dir("$path")) return true; 
		return $this->wire()->files->mkdir($path, true); 
	}

	/**
	 * Create the directory path where published files will be stored.
	 * 
	 * #pw-internal
	 *
	 * @return bool True on success, false if not
	 *
	 */
	public function createPath() {
		return $this->_createPath($this->path()); 
	}

	/**
	 * Empty out the published files (delete all of them)
	 * 
	 * #pw-group-manipulation
	 * 
	 * @param bool $rmdir Remove the directory too? (default=false)
	 * @param bool $recursive Recursively do the same for subdirectories? (default=true)
	 * @return bool True on success, false on error (since 3.0.17, previous versions had no return value).
	 *
	 */
	public function emptyPath($rmdir = false, $recursive = true) {
		$files = $this->wire()->files;
		$path = $this->path();
		if(!is_dir($path)) return true;
		$errors = 0;
		if($recursive) {
			// clear out path and everything below it
			if(!$files->rmdir($path, true, true)) $errors++;
			if(!$rmdir) $this->_createPath($path); 
		} else {
			// only clear out files in path
			foreach(new \DirectoryIterator($path) as $file) {
				if($file->isDot() || $file->isDir()) continue; 
				if(!$files->unlink($file->getPathname(), true)) $errors++;
			}
			if($rmdir) {
				$files->rmdir($path, false, true); // will not be successful if other dirs within it
			}
		}
		return $errors === 0;
	}

	/**
	 * Empties all file paths related to the Page, and removes the directories
	 * 
	 * This is the same as calling the `PagefilesManager:emptyPath()` method with the
	 * `$rmdir` and `$recursive` options as both true.
	 * 
	 * #pw-group-manipulation
	 * 
	 * @return bool True on success, false on error (since 3.0.17, previous versions had no return value). 
	 *
	 */
	public function emptyAllPaths() {
		return $this->emptyPath(true); 
	}


	/**
	 * Get the published path for files
	 * 
	 * #pw-hooks
	 * 
	 * @return string
	 * @throws WireException if attempting to access this on a Page that doesn't yet exist in the database
 	 *
	 */
	public function path() {
		return $this->wire()->hooks->isHooked('PagefilesManager::path()') ? $this->__call('path', array()) : $this->___path();
	}
	
	/**
	 * Get the published path (for use with hooks)
	 * 
	 * #pw-internal
	 * 
	 * @return string
	 * @throws WireException if attempting to access this on a Page that doesn't yet exist in the database
	 *
	 */
	public function ___path() {
		if(is_null($this->path)) {
			if(!$this->page->id) throw new WireException("New page '{$this->page->url}' must be saved before files can be accessed from it");
			$this->path = self::_path($this->page);
		}
		return $this->path;
	}

	/**
	 * Get the published URL for files
	 * 
	 * #pw-hooks
	 * 
	 * @return string
	 * @throws WireException if attempting to access this on a Page that doesn't yet exist in the database
	 *
	 */
	public function url() {
		return $this->wire()->hooks->isHooked('PagefilesManager::url()') ? $this->__call('url', array()) : $this->___url();
	}

	/**
	 * Return the auto-determined URL, hookable version
	 * 
	 * #pw-internal
 	 *
	 * Note: your hook can get the $page from $event->object->page; 
	 * 
	 * @return string
	 * @throws WireException if attempting to access this on a Page that doesn't yet exist in the database
	 *
	 */
	public function ___url() {
		if(!is_null($this->url)) return $this->url;
		$config = $this->wire()->config;
		if(strpos($this->path(), $config->paths->files . self::extendedDirName) !== false) {
			$this->url = $config->urls->files . self::_dirExtended($this->page->id); 
		} else {
			$this->url = $config->urls->files . $this->page->id . '/';
		}
		return $this->url;
	}

	/**
	 * For hooks to listen to on page save action, for file-specific operations
	 * 
	 * Executed before a page draft/published assets are moved around, when changes to files may be best to execute.
	 * 
	 * There are no arguments or return values here. 
	 * Hooks may retrieve the Page object being saved from `$event->object->page`. 
	 * 
	 * #pw-hooker
	 *
	 */
	public function ___save() { }

	/**	
	 * Uncache/unload any data that should be unloaded with the page
	 * 
	 * #pw-internal
	 *
	 */
	public function uncache() {
		$this->url = null;
		$this->path = null;
		// $this->page = null;
	}
	
	/**
	 * Handle non-function versions of some properties
	 * 
	 * @param string $name
	 * @return mixed
	 *
	 */
	public function __get($name) {
		if($name === 'path') return $this->path();
		if($name === 'url') return $this->url();
		if($name === 'page') return $this->page; 
		return parent::__get($name);
	}

	/**
	 * Returns true if Page has a files path that exists.
	 *
	 * This is a way to for `$pages` API functions (or others) to check if they should attempt to use 
	 * a $page's filesManager, thus ensuring directories aren't created for pages that don't need them.
	 * 
	 * #pw-group-static
	 *
	 * @param Page $page
	 * @return bool True if a path exists for the page, false if not. 
	 *
	 */
	static public function hasPath(Page $page) {
		return is_dir(self::_path($page)); 
	}

	/**
	 * Returns true if Page has a path and files, false if not.
	 * 
	 * #pw-group-static
	 *
	 * @param Page $page
	 * @return bool True if $page has a path and files
	 *
	 */
	static public function hasFiles(Page $page) {
		if(!self::hasPath($page)) return false;
		$dir = opendir(self::_path($page));
		if(!$dir) return false; 
		$has = false; 
		while(!$has && ($f = readdir($dir)) !== false) $has = $f !== '..' && $f !== '.';
		closedir($dir);
		// while(!$has && ($f = readdir($dir)) !== false) $has = $f !== '..' && $f !== '.' && !$f !== self::metaFileName;
		return $has; 
	}

	/**
	 * Does given Page have the given file? Checks in a silent manner, not creating anything.
	 * 
	 * @param Page $page
	 * @param string $file Filename (basename) excluding path
	 * @param bool $getPathname Return the full pathname to the file (rather than true) if it exists? (default=false)
	 * @return bool|string
	 * @since 3.0.166
	 * 
	 */
	static public function hasFile(Page $page, $file, $getPathname = false) {
		$path = self::_path($page); 
		if(!is_dir($path)) return false;
		$file = basename($file);
		$file = str_replace(array('\\', '/', '..'), '', $file);
		$pathname = $path . $file;
		if(!file_exists($pathname)) return false;
		if($getPathname) return $pathname;
		return true;
	}

	/**
	 * Get the files path for a given page (whether it exists or not).
	 * 
	 * #pw-group-static
	 *
	 * @param Page $page
	 * @param bool $extended Whether to force use of extended paths, primarily for recursive use by this function only.
	 * @return string 
 	 *
	 */
	static public function _path(Page $page, $extended = false) {

		$config = $page->wire()->config;
		$path = $config->paths->files; 
		
		$securePrefix = $config->pagefileSecurePathPrefix; 
		if(!strlen($securePrefix)) $securePrefix = self::defaultSecurePathPrefix;
		
		if($extended) {
			$publicPath = $path . self::_dirExtended($page->id); 
			$securePath = $path . self::_dirExtended($page->id, $securePrefix); 
		} else {
			$publicPath = $path . $page->id . '/';
			$securePath = $path . $securePrefix . $page->id . '/';
		}
		
		$secureFiles = $page->secureFiles();
		
		if($secureFiles === false) {
			// use the public path, renaming a secure path to public if it exists
			if(is_dir($securePath) && !is_dir($publicPath)) {
				$page->wire()->files->rename($securePath, $publicPath);
				self::$numRenamedPaths++;
			}
			$filesPath = $publicPath;
			
		} else if($secureFiles === null) {
			$filesPath = $publicPath;
			
		} else {
			// use the secure path, renaming the public to secure if it exists
			$hasSecurePath = is_dir($securePath);
			if(is_dir($publicPath) && !$hasSecurePath) {
				$page->wire()->files->rename($publicPath, $securePath);
				self::$numRenamedPaths++;

			} else if(!$hasSecurePath && self::defaultSecurePathPrefix != $securePrefix) {
				// we track this just in case the prefix was newly added to config.php, this prevents 
				// losing track of the original directories
				if($extended) {
					$securePath2 = $path . self::_dirExtended($page->id, self::defaultSecurePathPrefix);
				} else {
					$securePath2 = $path . self::defaultSecurePathPrefix . $page->id . '/';
				}
				if(is_dir($securePath2)) {
					// if the secure path prefix has been changed from undefined to defined
					$page->wire()->files->rename($securePath2, $securePath);
					self::$numRenamedPaths++;
				}
			}
			$filesPath = $securePath;
		}
		
		if(!$extended && $config->pagefileExtendedPaths && !is_dir($filesPath)) {
			// if directory doesn't exist and extended mode is possible, specify use of the extended one
			$filesPath = self::_path($page, true); 
		}
		
		return $filesPath; 
	}

	/**
	 * Get all potential disk paths for given Page files (not yet in use)
	 * 
	 * @todo FOR FUTURE USE
	 * @param Page $page
	 * @return string[]
	 * 
	static public function _paths(Page $page) {
		$config = $page->wire()->config;
		$path = $config->paths->files;
		$securePrefix = $config->pagefileSecurePathPrefix;
		$useSecure = $page->secureFiles();
		$useExtended = $config->pagefileExtendedPaths;
		$useUnique = $config->pagefileUnique && $page->hasStatus(Page::statusUnique);
		
		if(!strlen($securePrefix)) $securePrefix = self::defaultSecurePathPrefix;
		
		$paths = array(
			'current' => '',
			'normal' => $path . "$page->id/",
			'unique' => $path . "0/$page->name/",
			'extended' => $path . self::_dirExtended($page->id),
			'secure' => $path . $securePrefix . "$page->id/",
			'secureUnique' => $path . "0/$securePrefix$page->name/",
			'secureExtended' => $path . self::_dirExtended($page->id, $securePrefix),
		);

		if($useUnique) {
			// use unique page name paths
			$paths['current'] = ($useSecure ? $paths['secureUnique'] : $paths['unique']); 
			
		} else if($useSecure) {
			// use secure files
			$paths['current'] = ($useExtended ? $paths['secureExtended'] : $paths['secure']);
			
		} else {
			// use normal path
			$paths['current'] = ($useExtended ? $paths['extended'] : $paths['normal']);
		}
		
		return $paths;	
	}
	 */

	/**
	 * Scan all paths for page and make sure only the correct one exists (not yet in use)
	 * 
	 * Also crates and moves files when necessary.
	 * 
	 * @todo FOR FUTURE USE
	 * @param Page $page
	 * 
	private function verifyPaths(Page $page) {
		$paths = self::_paths($page);
		$current = $paths['current'];
		$currentExists = is_dir($current);
		unset($paths['current']); 
		foreach($paths as $path) {
			if(!is_dir($path)) continue;
			if(!$currentExists) {
				$this->_createPath($current);
				$currentExists = true;
			}
			$this->_copyFiles($path, $current);
			$this->wire()->files->rmdir($path, true);
			self::$numRenamedPaths++;
		}
	}
	 */

	/**
	 * Get quantity of renamed paths to to pagefileSecure changes
	 * 
	 * #pw-internal
	 * 
	 * @param bool $reset Also reset to 0?
	 * @return int
	 * @since 3.0.166
	 * 
	 */
	static public function numRenamedPaths($reset = false) {
		$num = self::$numRenamedPaths;
		if($reset) self::$numRenamedPaths = 0;
		return $num;
	}

	/**
	 * Generate the directory name (after /site/assets/files/)
	 * 
	 * #pw-internal
	 *
	 * @param int $id
	 * @param string $securePrefix Optional prefix to use for last segment in path
	 * @return string
	 *
	 */
	static public function _dirExtended($id, $securePrefix = '') {
		
		$len = strlen($id);

		if($len > 3) {
			if($len % 2 === 0) {
				$id = "0$id"; // ensure all segments are 2 chars
				$len++;
			}
			$path = chunk_split(substr($id, 0, $len-3), 2, '/') . $securePrefix . substr($id, $len-3);

		} else if($len < 3) {
			$path = $securePrefix . str_pad($id, 3, "0", STR_PAD_LEFT);

		} else {
			$path = $securePrefix . $id;
		}
		
		return self::extendedDirName . $path . '/';	
	}

	/**
	 * Given a dir (URL or path) to a files directory, return the page ID associated with it.
	 * 
	 * #pw-group-static
	 *
	 * @param string $dir May be extended or regular directory, path or URL.
	 * @return int
	 *
	 */ 
	static public function dirToPageID($dir) {

		$parts = explode('/', $dir); 
		$pageID = '';
		$securePrefix = wire()->config->pagefileSecurePathPrefix; 
		if(!strlen($securePrefix)) $securePrefix = self::defaultSecurePathPrefix;

		foreach(array_reverse($parts) as $key => $part) {
			$part = ltrim($part, $securePrefix); 
			if(!ctype_digit($part)) {
				if(!$key) continue; // first item, likely a filename, skip it
				break; // not first item means end of ID sequence
			}
			$pageID = $part  . $pageID; 
		}

		return (int) $pageID; 
	}

	/**
	 * Return a path where temporary files can be stored unique to this ProcessWire instance
	 * 
	 * @return string
	 *
	 */
	public function getTempPath() {
		static $wtd = null;
		if(is_null($wtd)) {
			$wtd = new WireTempDir();
			$this->wire($wtd);
			$wtd->setMaxAge(3600);
			$name = $wtd->createName('PFM');
			$wtd->init($name);
		}
		return $wtd->get();
		// if(is_null($wtd)) $wtd = $this->wire(new WireTempDir($this->className() . $this->page->id));
		// return $wtd->get();
	}

	/**
	 * Have this page’s files had modifications since last isModified(true) call? (FUTURE USE)
	 *
	 * Please note the following:
	 *
	 * - This only takes into account files in the actual directory and not subdirectories unless
	 *   the $recursive option is true.
	 *
	 * - This method always returns true the first time it has been called on a given path.
	 *
	 * @param bool $reset Reset to current time if modified? Ensures future calls return false until modified again. (default=false)
	 * @param bool $recursive Descend into directories (max 1 level)? (default=false)
	 * @param string $path Path to check if not default, primarily for internal recursive use. (default='')
	 * @return bool True if files in directory have been modified since last reset, or false if not
	 *
	public function isModified($reset = false, $recursive = false, $path = '') {
		$files = $this->wire('files');
		$path = empty($path) ? $this->path() : $files->unixDirName($path);
		$file = $path . self::metaFileName;
		if(!file_exists($file)) {
			touch($file);
			$files->chmod($file);
			$isModified = true;
		} else {
			$fileTime = filemtime($file);
			$pathTime = filemtime($path);
			$isModified = $pathTime > $fileTime;
			if($isModified && $reset) touch($file);
		}
		if($recursive && !$isModified) {
			$dirs = array();
			foreach(new \DirectoryIterator($path) as $item) {
				if($item->isDot() || !$item->isDir()) continue;
				$dirs[] = $item->getPathname();
			}
			foreach($dirs as $dir) {
				$isModified = $this->isModified($reset, false, $dir);
				if($isModified) break;
			}
		}
		return $isModified;
	}
	 */
}
