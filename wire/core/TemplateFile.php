<?php namespace ProcessWire;

/**
 * ProcessWire TemplateFile
 *
 * A template file that will be loaded and executed as PHP and its output returned.
 * 
 * ProcessWire 3.x, Copyright 2022 by Ryan Cramer
 * https://processwire.com
 * 
 * @property bool $halt Set to true to halt during render, or use method $this->halt();
 * @property-read string $filename Primary file to render.
 * @property-read array $prependFilename Optional file name(s) used for prepend.
 * @property-read array $appendFilename Optional file name(s) used for append.
 * @property-read string $currentFilename Current file being rendered (whether primary, prepend, append).
 * @property-read bool $trim Whether or not leading/trailing whitespace is trimmed from output (3.0.154+).
 * @method string render()
 * @method bool fileFailed($filename, \Exception $e)
 *
 */

class TemplateFile extends WireData {

	/**
	 * The full path and filename to the PHP template file
	 * 
	 * @var string
	 *
	 */
	protected $filename;

	/**
	 * The current filename being rendered (whether prepend, main, append, etc.)
	 * 
	 * @var string
	 * 
	 */
	protected $currentFilename;

	/**
	 * Optional filenames that are prepended to the render
	 * 
	 * @var array
	 *
	 */
	protected $prependFilename = array();

	/**
	 * Optional filenames that are appended to the render
	 * 
	 * @var array
	 *
	 */
	protected $appendFilename = array(); 

	/**
	 * The saved directory location before render() was called
	 * 
	 * @var string
	 *
	 */
	protected $savedDir;

	/**
	 * Directory to change to before rendering
	 * 
	 * If not set, it will change to the directory that the $filename is in.
	 * If false, no directories will be changed. 
	 * 
	 * @var null|string|bool
	 * 
	 */
	protected $chdir = null;

	/**
	 * Saved ProcessWire instance
	 * 
	 * @var ProcessWire 
	 * 
	 */
	protected $savedInstance; 
	
	/**
	 * Throw exception when main template file doesn’t exist?
	 * 
	 * @var bool
	 * 
	 */
	protected $throwExceptions = true;

	/**
	 * Whether or not the template file called $this->halt()
	 * 
	 * @var bool
	 * 
	 */
	protected $halt = false;

	/**
	 * Last tracked profile event
	 * 
	 * @var mixed
	 * 
	 */
	protected $profilerEvent = null;

	/**
	 * @var WireProfilerInterface|null
	 * 
	 */
	protected $profiler = null;

	/**
	 * Return value from rendered file
	 * 
	 * @var null|mixed
	 * 
	 */
	protected $returnValue = null;

	/**
	 * Trim leading/trailing whitespace from rendered output?
	 * 
	 * @var bool
	 * 
	 */
	protected $trim = true;

	/**
	 * Stack of files that are currently being rendered
	 *
	 * @var array
	 *
	 */
	static protected $renderStack = array();
	
	/**
	 * DEPRECATED: Variables that will be applied globally to this and all other TemplateFile instances
	 *
	 */
	static protected $globals = array();

	/**
	 * Output buffer starting level, set by first TemplateFile instance that gets created
	 * 
	 * @var null|int
	 * 
	 */
	static protected $obStartLevel = null;

	/**
	 * Construct the template file
	 *
	 * @param string $filename Full path and filename to the PHP template file
	 *
	 */
	public function __construct($filename = '') {
		parent::__construct();
		if(self::$obStartLevel === null) self::$obStartLevel = ob_get_level();
		if($filename) $this->setFilename($filename); 
	}

	/**
	 * Sets the template file name, replacing whatever was set in the constructor
	 *
	 * @param string $filename Full path and filename to the PHP template file
	 * @return bool true on success, false if file doesn't exist
	 * @throws WireException if file doesn't exist (unless throwExceptions is disabled)
	 *
	 */
	public function setFilename($filename) {
		if(empty($filename)) return false;
		if(is_file($filename)) {
			$this->filename = $filename;
			return true;
		} else {
			$error = "Filename doesn't exist: $filename";
			if($this->throwExceptions) throw new WireException($error);
			$this->error($error); 
			$this->filename = $filename; // in case it will exist when render() is called
			return false;
		}
	}

	/**
	 * Set a file to prepend to the template file at render time
	 * 
	 * @param string $filename
	 * @return bool Returns true on success, false if file doesn't exist.
	 * @throws WireException if file doesn't exist (unless throwExceptions is disabled)
	 *
	 */
	public function setPrependFilename($filename) {
		if(empty($filename)) return false;
		if(is_file($filename)) {
			$this->prependFilename[] = $filename; 
			return true; 
		} else {
			$error = "Append filename doesn't exist: $filename"; 
			if($this->throwExceptions) throw new WireException($error);
			$this->error($error); 
			return false;
		}
	}

	/**
	 * Set a file to append to the template file at render time
	 * 
	 * @param string $filename
	 * @return bool Returns true on success false if file doesn't exist. 
	 * @throws WireException if file doesn't exist (unless throwExceptions is disabled)
	 *
	 */
	public function setAppendFilename($filename) {
		if(empty($filename)) return false;
		if(is_file($filename)) {
			$this->appendFilename[] = $filename; 
			return true; 
		} else {
			$error = "Prepend filename doesn't exist: $filename";
			if($this->throwExceptions) throw new WireException($error);
			$this->error($error); 
			return false;
		}
	}

	/**
	 * Call this with boolean false to disable exceptions when file doesn’t exist
	 *
	 * @param bool $throwExceptions
	 *
	 */
	public function setThrowExceptions($throwExceptions) {
		$this->throwExceptions = $throwExceptions ? true : false;
	}

	/**
	 * Set whether rendered output should have leading/trailing whitespace trimmed
	 * 
	 * By default whitespace is trimmed so you would call `$templateFile->setTrim(false);` to disable.
	 * 
	 * @param bool $trim
	 * @since 3.0.154
	 * 
	 */
	public function setTrim($trim) {
		$this->trim = (bool) $trim;
	}

	/**
	 * Set the directory to temporarily change to during rendering
	 * 
	 * If not set, it changes to the directory that $filename is in. 
	 * To disable TemplateFile from changing any directories, set to false (3.0.154+).
	 * 
	 * @param string|bool $chdir
	 * 
	 */
	public function setChdir($chdir) {
		$this->chdir = $chdir; 
	}

	/**
	 * Sets a variable to be globally accessable to all other TemplateFile instances (deprecated)
	 *
	 * Note, to set a variable for just this instance, use the set() as inherted from WireData. 
	 * 
	 * #pw-internal
	 *
	 * @param string $name
	 * @param mixed $value
	 * @param bool $overwrite Should the value be overwritten if it already exists? (default true)
	 * @deprecated
	 *
	 */
	public function setGlobal($name, $value, $overwrite = true) {
		// set template variable that will apply across all instances of Template
		if(!$overwrite && isset(self::$globals[$name])) return; 
		self::$globals[$name] = $value; 
	}

	/**
	 * Render the template: execute it and return its output
	 *
	 * @return string|array The output of the Template File
	 * @throws WireException|\Exception Throws WireException if file not exist + any exceptions thrown by included file(s)
	 *
	 */
	public function ___render() {
		
		/** @noinspection PhpIncludeInspection */

		if(!$this->filename) return '';
		
		if(!file_exists($this->filename)) {
			$error = "Template file does not exist: $this->filename";
			if($this->throwExceptions) throw new WireException($error);
			$this->error($error); 
			return '';
		}

		$this->renderReady(); 
	
		// make API variables available to PHP file
		$fuel = array_merge($this->getArray(), self::$globals); // so that script can foreach all vars to see what's there
		extract($fuel); 
		ob_start();
	
		try {
			// include prepend files
			foreach($this->prependFilename as $_filename) {
				if($this->halt) break;
				$this->fileReady($_filename);
				require($_filename);
				$this->fileFinished();
			}
		} catch(\Exception $e) {
			if($this->fileFailed($this->currentFilename, $e)) throw $this->renderFailed($e);
		}
		
		if($this->halt) {
			// if prepend file indicates we should halt, then do not render next file
			$this->returnValue = 0;
		} else {
			// include main file to render
			try {
				$this->fileReady($this->filename);
				$this->returnValue = require($this->filename);
				$this->fileFinished();
			} catch(\Exception $e) {
				if($this->fileFailed($this->filename, $e)) throw $this->renderFailed($e);
			}
		}
	
		try {
			// include append files
			foreach($this->appendFilename as $_filename) {
				if($this->halt) break;
				$this->fileReady($_filename);
				require($_filename);
				$this->fileFinished();
			}
		} catch(\Exception $e) {
			if($this->fileFailed($this->currentFilename, $e)) throw $this->renderFailed($e);
		}
		
		$out = ob_get_contents();
		ob_end_clean();
		
		$this->renderFinished();

		if($this->trim) $out = trim($out); 
		
		if(!strlen($out) && !$this->halt) { 
			if(is_array($this->returnValue)) return $this->returnValue;
			if($this->returnValue && $this->returnValue !== 1) return $this->returnValue;
		}
		
		return $out;
	}

	/**
	 * Prepare to nclude specific file (whether prepend, main or append)
	 * 
	 * @param string $filename
	 * @since 3.0.154
	 * 
	 */
	protected function fileReady($filename) {
		$this->currentFilename = $filename;
		if($this->profiler) {
			$f = str_replace($this->wire()->config->paths->root, '/', $filename);
			$this->profilerEvent = $this->profiler->start($f, $this);
		}
		self::pushRenderStack($filename);
	}

	/**
	 * Clean up after include specific file
	 * 
	 * @since 3.0.154
	 * 
	 */
	protected function fileFinished() {
		$this->currentFilename = '';
		if($this->profiler && $this->profilerEvent) {
			$this->profiler->stop($this->profilerEvent);
		}
		self::popRenderStack();
	}
	
	/**
	 * Called when render of specific file failed with Exception
	 *
	 * #pw-hooker
	 *
	 * @param string $filename
	 * @param \Exception $e
	 * @return bool True if Exception $e should be thrown, false if it should be ignored
	 * @since 3.0.154
	 *
	 */
	protected function ___fileFailed($filename, \Exception $e) {
		$this->fileFinished();
		return true;
	}


	/**
	 * Prepare to render
	 * 
	 * Called right before render about to start
	 * 
	 * @since 3.0.154
	 * 
	 */
	protected function renderReady() {
		
		// ensure that wire() functions in template file map to correct ProcessWire instance
		$this->savedInstance = ProcessWire::getCurrentInstance();
		ProcessWire::setCurrentInstance($this->wire());
		
		$this->profiler = $this->wire()->profiler;
	
		if($this->chdir !== false) {
			$cwd = getcwd();

			if($this->chdir) {
				$chdir = $this->chdir;
			} else {
				$chdir = dirname($this->filename);
			}

			if($chdir === $cwd) {
				// already in required directory
				$this->savedDir = '';
			} else {
				// change to new directory
				$this->savedDir = $cwd;
				chdir($chdir);
			}
		}
	}

	/**
	 * Cleanup after render
	 * 
	 * @since 3.0.154
	 * 
	 */
	protected function renderFinished() {
		
		if($this->currentFilename) {
			$this->fileFinished();
		}
		
		if($this->savedDir && $this->chdir !== false) {
			chdir($this->savedDir);
		}
		
		ProcessWire::setCurrentInstance($this->savedInstance);
	}

	/**
	 * Called when overall render failed
	 * 
	 * @param \Exception $e
	 * @return \Exception
	 * @since 3.0.154
	 * 
	 */
	protected function renderFailed(\Exception $e) {
		$this->renderFinished();
		return $e; 
	}

	/**
	 * Set the current filename being rendered
	 *
	 * @param string $filename
	 * @deprecated Moved to fileReady() and fileFinished()
	 *
	 */
	protected function setCurrentFilename($filename) {
		if(strlen($filename)) {
			$this->fileReady($filename);
		} else {
			$this->fileFinished();
		}
	}

	/**
	 * Get an array of all variables accessible (locally scoped) to the PHP template file
	 * 
	 * @return array
	 *
	 */
	public function getArray() {
		return array_merge($this->wire()->fuel->getArray(), parent::getArray()); 
	}

	/**
	 * Get a set property from the template file, typically to check if a template has access to a given variable
	 *
	 * @param string $key
	 * @return mixed Returns the value of the requested property, or NULL if it doesn't exist
	 *	
	 */
	public function get($key) {
		if($key === 'filename') return $this->filename; 
		if($key === 'appendFilename' || $key === 'appendFilenames') return $this->appendFilename; 
		if($key === 'prependFilename' || $key === 'prependFilenames') return $this->prependFilename;
		if($key === 'currentFilename') return $this->currentFilename; 
		if($key === 'halt') return $this->halt;
		if($key === 'trim') return $this->trim;
		if($value = parent::get($key)) return $value; 
		if(isset(self::$globals[$key])) return self::$globals[$key];
		return null;
	}

	/**
	 * Set a property
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return $this|WireData
	 * 
	 */
	public function set($key, $value) {
		if($key === 'halt') {
			$this->halt($value);
			return $this;
		}
		return parent::set($key, $value);
	}

	/**
	 * Push a filename onto the render stack
	 * 
	 * #pw-internal
	 * 
	 * @param string $filename
	 * 
	 */
	public static function pushRenderStack($filename) {
		self::$renderStack[] = $filename;
	}

	/**
	 * Pop last file off of render stack
	 * 
	 * #pw-internal
	 * 
	 * @return string|null item that was removed, or null if none found
	 * 
	 */
	public static function popRenderStack() {
		$result = array_pop(self::$renderStack); 
		return $result;
	}

	/**
	 * Get the current render stack
	 * 
	 * This contains the files currently being rendered from first to last
	 * 
	 * @return array
	 * 
	 */
	public static function getRenderStack() {
		return self::$renderStack;
	}

	/**
	 * Clear out all pending output buffers
	 * 
	 * @since 3.0.175
	 * @return int Number of output buffers cleaned
	 * 
	 */
	public static function clearAll() {
		$n = 0;
		if(self::$obStartLevel !== null) {
			while(ob_get_level() > self::$obStartLevel) {
				ob_end_clean();
				$n++;
			}
		}
		return $n;
	}

	/**
	 * The string value of a TemplateFile is its PHP template filename OR its class name if no filename is set
	 * 
	 * @return string
	 *	
	 */
	public function __toString() {
		if(!$this->filename) return $this->className();
		return $this->filename; 
	}

	/**
	 * This method can be called by any template file to stop further render inclusions
	 * 
	 * This is preferable to doing an exit; or die() from your template file(s), as it only halts the rendering
	 * of output and doesn't halt the rest of ProcessWire.  
	 * 
	 * Can be called from prepend/append files as well. 
	 * 
	 * USAGE from template file is: return $this->halt();
	 * 
	 * @param bool|string $halt
	 *  If given boolean, it will set the halt status.
	 *  If given string, it will be output (3.0.239+)
	 * @return $this
	 * 
	 */
	public function halt($halt = true) {
		if(is_bool($halt)) {
			$this->halt = $halt ? true : false;
		} else if(is_string($halt)) {
			$this->halt = true;
			echo $halt;
		}
		return $this;
	}
	
}
