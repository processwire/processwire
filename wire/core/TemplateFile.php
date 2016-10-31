<?php namespace ProcessWire;

/**
 * ProcessWire TemplateFile
 *
 * A template file that will be loaded and executed as PHP, and it's output returned
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * @property bool $halt
 * @method string render()
 *
 */

class TemplateFile extends WireData {

	/**
	 * The full path and filename to the PHP template file
	 *
	 */
	protected $filename;

	/**
	 * Optional filenames that are prepended to the render
	 *
	 */
	protected $prependFilename = array();

	/**
	 * Optional filenames that are appended to the render
	 *
	 */
	protected $appendFilename = array(); 

	/**
	 * The saved directory location before render() was called
	 *
	 */
	protected $savedDir;

	/**
	 * Directory to change to before rendering
	 * 
	 * If not set, it will change to the directory that the $filename is in
	 * 
	 * @var null|string
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
	 * Throw exceptions when files don't exist?
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
	 * DEPRECATED: Variables that will be applied globally to this and all other TemplateFile instances
	 *
	 */
	static protected $globals = array(); 

	/**
	 * Construct the template file
	 *
	 * @param string $filename Full path and filename to the PHP template file
	 *
	 */
	public function __construct($filename = '') {
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
	 * Set the directory to temporarily change to during rendering
	 * 
	 * If not set, it changes to the directory that $filename is in. 
	 * 
	 * @param string $chdir
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
	 * Start profiling a render
	 * 
	 * @param string $filename
	 * 
	 */
	protected function start($filename) {
		if($this->profiler) {
			$f = str_replace($this->wire('config')->paths->root, '/', $filename);
			$this->profilerEvent = $this->profiler->start($f, $this);
		}
	}

	/**
	 * Stop profiling a render
	 * 
	 */
	protected function stop() {
		if($this->profilerEvent) $this->profiler->stop($this->profilerEvent);
	}
	
	/**
	 * Render the template -- execute it and return it's output
	 *
	 * @return string The output of the Template File
	 * @throws WireException if template file doesn't exist
	 *
	 */
	public function ___render() {

		if(!$this->filename) return '';
		if(!file_exists($this->filename)) {
			$error = "Template file does not exist: '$this->filename'";
			if($this->throwExceptions) throw new WireException($error);
			$this->error($error); 
			return '';
		}

		// ensure that wire() functions in template file map to correct ProcessWire instance
		$this->savedInstance = ProcessWire::getCurrentInstance();
		ProcessWire::setCurrentInstance($this->wire());

		$this->profiler = $this->wire('profiler');
		$this->savedDir = getcwd();	

		if($this->chdir) {
			chdir($this->chdir);
		} else {
			chdir(dirname($this->filename));
		}
		
		$fuel = array_merge($this->getArray(), self::$globals); // so that script can foreach all vars to see what's there
		extract($fuel); 
		ob_start();
		
		foreach($this->prependFilename as $_filename) {
			if($this->halt) break;
			if($this->profiler) $this->start($_filename);
			/** @noinspection PhpIncludeInspection */
			require($_filename);
			if($this->profiler) $this->stop();
		}
		
		if($this->profiler) $this->start($this->filename);
		if($this->halt) {
			$returnValue = 0;
		} else {
			/** @noinspection PhpIncludeInspection */
			$returnValue = require($this->filename);
		}
		if($this->profiler) $this->stop();
		
		foreach($this->appendFilename as $_filename) {
			if($this->halt) break;
			if($this->profiler) $this->start($_filename);
			/** @noinspection PhpIncludeInspection */
			require($_filename);
			if($this->profiler) $this->stop();
		}
		
		$out = "\n" . ob_get_contents() . "\n";
		ob_end_clean();

		if($this->savedDir) chdir($this->savedDir); 
		ProcessWire::setCurrentInstance($this->savedInstance);
		
		$out = trim($out); 
		if(!strlen($out) && !$this->halt && $returnValue && $returnValue !== 1) return $returnValue;
		
		return $out;
	}

	/**
	 * Get an array of all variables accessible (locally scoped) to the PHP template file
	 * 
	 * @return array
	 *
	 */
	public function getArray() {
		return array_merge($this->wire('fuel')->getArray(), parent::getArray()); 
	}

	/**
	 * Get a set property from the template file, typically to check if a template has access to a given variable
	 *
	 * @param string $property
	 * @return mixed Returns the value of the requested property, or NULL if it doesn't exist
	 *	
	 */
	public function get($property) {
		if($property == 'filename') return $this->filename; 
		if($property == 'appendFilename') return $this->appendFilename; 
		if($property == 'prependFilename') return $this->prependFilename; 
		if($property == 'halt') return $this->halt;
		if($value = parent::get($property)) return $value; 
		if(isset(self::$globals[$property])) return self::$globals[$property];
		return null;
	}
	
	public function set($property, $value) {
		if($property == 'halt') {
			$this->halt($value);
			return $this;
		}
		return parent::set($property, $value);
	}

	/**
	 * Call this with boolean false to disable exceptions when file doesn't exist
	 * 
	 * @param bool $throwExceptions
	 * 
	 */
	public function setThrowExceptions($throwExceptions) {
		$this->throwExceptions = $throwExceptions ? true : false;
	}

	/**
	 * The string value of a TemplateFile is it's PHP template filename OR it's class name if no filename is set
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
	 * @param bool $halt
	 * @return $this
	 * 
	 */
	protected function halt($halt = true) {
		$this->halt = $halt ? true : false;
		return $this;
	}


}

