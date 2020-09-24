<?php namespace ProcessWire;

/**
 * Base class for FileValidator modules
 * 
 * To create a FileValidator module: 
 * 
 * 1. Create a class that extends this and follow the naming convention: FileValidator[Something].
 * 2. Place in file: /site/modules/FileValidator[Something].module (or in module-specific directory).
 * 3. Copy the getModuleInfo() method out of this class and update as appropriate.
 * 4. Implement an isValidFile($filename) method, and you are done. 
 * 
 * EXAMPLE: /site/modules/FileValidatorHTML.module
 * 
 * class FileValidatorHTML extends FileValidatorModule {
 *     public static function getModuleInfo() {
 *       return array(
 *         'title' => 'Validate HTML files',
 *         'version' => 1, 
 *         'autoload' => false, 
 *         'singular' => false, 
 *         'validates' => array('html', 'htm'),
 *         'requires' => 'ProcessWire>=2.5.24',
 *       );
 *     }
 *     protected function isValidFile($filename) {
 *         $valid = false;
 *         // some code to validate $filename and set $valid
 *         return $valid;
 *     }
 * }
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * Class FileValidatorModule
 * 
 */
abstract class FileValidatorModule extends WireData implements Module {
	
	/**
	 * Get module information
	 * 
	 * FileValidator modules should provide their own getModuleInfo() with the 
	 * key part being the 'validates' property (see below). 
	 * 
	 * @return array
	 * 
	 */
	public static function getModuleInfo() {
		return array(
			'title' => 'File Validator',
			'version' => 1,
			'author' => 'Your name here',
			'summary' => 'Validates and/or sanitizes files of type [insert file type(s) here]',
			'singular' => false,
			'autoload' => false,
			'requires' => 'ProcessWire>=2.5.24',

			// Required of all FileValidator modules: specify extensions they validate as array.
			// Extensions should be lowercase. To specify a regex, start and end with a "/" slash.
			// If module wants to validate all file types, it should specify: "/.*/"
				
			'validates' => array(
				'xyz',		// filename.xyz
				'123',		// filename.123
				'/^x+$/',	// filename.x, filename.xx, filename.xxx, etc.
			)
		);
	}
	
	/**
	 * Page object associated with files, if applicable
	 *
	 * @var Page|null
	 *
	 */
	protected $_page = null;

	/**
	 * Page object associated with files, if applicable
	 *
	 * @var Field|null
	 *
	 */
	protected $_field = null;

	/**
	 * Pagefile or Pageimage object associated with files, if applicable
	 *
	 * @var Pagefile|Pageimage|null
	 *
	 */
	protected $_pagefile = null;

	/**
	 * Is the given file valid? (this is the method modules should implement)
	 * 
	 * This method should return:
	 * 	- boolean TRUE if file is valid
	 * 	- boolean FALSE if file is not valid
	 * 	- integer 1 if file is valid as a result of sanitization performed by this method (if supported by module)
	 * 	
	 * If the file can be made valid by sanitization, this method may also choose to do that (perhaps if configured 
	 * to do so) and return integer 1 after doing so. 
	 * 
	 * If method wants to explain why the file is not valid, it should call $this->error('reason why not valid'). 
	 * 
	 * @param string $filename Full path and filename to the file
	 * @return bool|int 
	 * 
	 */
	abstract protected function isValidFile($filename);

	/**
	 * Is the given file valid?
	 * 
	 * FileValidator modules should not implement this method, as it only serves as a front-end to isValid()
	 * for logging purposes. 
	 * 
	 * @param string $filename
	 * @return bool|int Returns TRUE if valid, FALSE if not, or integer 1 if valid as a result of sanitization.
	 * 
	 */
	final public function isValid($filename) {
		$valid = $this->isValidFile($filename);
		$filename = str_replace($this->wire('config')->paths->root, '/', $filename); // convert to shorter URL format
		$message = str_replace('FileValidator', '', $this->className()) . ": ";
		if($valid) {
			if($valid === 1) {
				$message .= $this->_('VALID (via sanitization)') . ": $filename";
			} else {
				$message .= $this->_('VALID') . ": $filename";
			}
		} else {
			$message .= $this->_('INVALID') . ": $filename";
			$errors = $this->errors('array'); 
			if(count($errors)) $message .= " - " . implode(', ', $errors); 
		}
		$this->log($message);
		return $valid;
	}

	/**
	 * Get the Page associated with any isValid() calls
	 * 
	 * If not applicable, it will be a NullPage()
	 * 
	 * @return NullPage|Page
	 * 
	 */
	public function getPage() {
		$page = $this->_page ? $this->_page : null;
		if(!$page && $this->_pagefile) $page = $this->_pagefile->page;
		return $page;
	}

	/**
	 * Get the Field object associated with any isValid() calls
	 *
	 * If not applicable, it will be null.
	 *
	 * @return null|Field
	 *
	 */
	public function getField() {
		$field = $this->_field ? $this->_field : null;
		if(!$field && $this->_pagefile) return $this->_pagefile->field;
		return $field;
	}

	/**
	 * Get the Pagefile or Pageimage object associated with any isValid() calls
	 * 
	 * If not applicable, it will be null.
	 * 
	 * @return Pagefile|null
	 * 
	 */
	public function getPagefile() {
		$pagefile = $this->_pagefile ? $this->_pagefile : null;
		return $pagefile;
	}
	
	public function setPage(Page $page) {
		$this->_page = $page;
	}
	
	public function setField(Field $field) {
		$this->_field = $field;
	}
	
	public function setPagefile(Pagefile $pagefile) {
		$this->_pagefile = $pagefile;
	}

	/**
	 * Log a message for this class
	 *
	 * Message is saved to a log file in ProcessWire's logs path to a file with
	 * the same name as the class, converted to hyphenated lowercase.
	 *
	 * @param string $str Text to log, or omit to just return the name of the log
	 * @param array $options Optional extras to include:
	 * 	- url (string): URL to record the with the log entry (default=auto-detect)
	 * 	- name (string): Name of log to use (default=auto-detect)
	 * @return WireLog|null
	 *
	 */
	public function ___log($str = '', array $options = array()) {
		if(empty($options['name'])) $options['name'] = 'file-validator';
		return parent::___log($str, $options);
	}
	
}