<?php namespace ProcessWire;

/**
 * ProcessWire File Compiler base module
 *
 * Provides the base class for FileCompiler modules
 * 
 * FileCompiler modules must use the name format: FileCompiler[Name].module
 * For example, FileCompilerTags.module
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 * 
 * @property int $runOrder Order that the module executes in relative to other FileCompiler modules. 
 *
 */

abstract class FileCompilerModule extends WireData implements Module, ConfigurableModule {

	/**
	 * Return an array of module information
	 *
	 * Array is associative with the following fields:
	 * - title: An alternate title, if you don't want to use the class name.
	 * - version: an integer that indicates the version number, 101 = 1.0.1
	 * - summary: a summary of the module (1 paragraph max)
	 *
	 * @return array
	 *
	public static function getModuleInfo();
	 */

	/**
	 * Full path/filename this compiler is acting upon, if needed
	 * 
	 * @var string
	 * 
	 */
	protected $sourceFile = '';
	
	public function __construct() {
		$this->set('runOrder', 0);
	}
	
	/**
	 * Optional method to initialize the module.
	 *
	 * This is called after ProcessWire's API is fully ready for use and hooks
	 *
	 */
	public function init() { }
	
	/**
	 * The compile method processes the contents of a file
	 * 
	 * 1. If you want to compile the entire contents of the file, override this method and don't parent::compile().
	 * 2. If you only want to compile non-PHP sections of the file, implement the compileMarkup() method instead.
	 * 
	 * @param string $data
	 * @return string
	 * 
	 */
	public function compile($data) {

		// extract markup sections
		$openPos = strpos($data, '<?');
		$closePos = strpos($data, '?>');

		if($closePos === false) {
			// document contains no closing PHP tag
			if($openPos === false) {
				// document also has no open PHP tag: must be all markup
				return $this->compileMarkup($data);
			} else if($openPos === 0) {
				// document is all PHP
				return $data;
			}
		}

		$trimFront = false;
		if($openPos !== 0) {
			$data = '?>' . $data;
			$trimFront = true;
		}
		$data .= '<?';

		if(!preg_match_all('!\?>(.+?)<\?!s', $data, $matches)) return array();

		foreach($matches[1] as $key => $markup) {
			$_markup = $this->compileMarkup($markup);
			if($_markup !== $markup) {
				$data = str_replace("?>$markup<?", "?>$_markup<?", $data);
			}
		}

		if($trimFront) $data = substr($data, 2);
		$data = substr($data, 0, -2);

		return $data;
	}

	/**
	 * Compile a section of markup
	 * 
	 * @param string $data
	 * @return string
	 * 
	 */
	public function compileMarkup($data) {
		return $data;
	}
	
	/**
	 * Perform any installation procedures specific to this module, if needed.
	 *
	 */
	public function ___install() { }

	/**
	 * Perform any uninstall procedures specific to this module, if needed.
	 *
	 */
	public function ___uninstall() { }

	public function isSingular() {
		return false;
	}

	public function isAutoload() {
		return false;
	}
	
	public function setSourceFile($file) {
		$this->sourceFile = $file;
	}

	/**
	 * Get the source file (full path and filename) that this module is acting upon
	 *
	 * @return string
	 *
	 */
	public function getSourceFile() {
		return $this->sourceFile;
	}

	/**
	 * Configure the FileCompiler module
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		$f = $this->wire('modules')->get('InputfieldInteger');
		$f->attr('name', 'runOrder');
		$f->attr('value', (int) $this->get('runOrder'));
		$f->label = $this->_('Runtime execution order');
		$f->description = $this->_('Order that this module runs in relative to other FileCompiler modules. Lower numbers run first.');
		$inputfields->add($f);
	}

}
