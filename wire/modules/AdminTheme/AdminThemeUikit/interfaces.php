<?php namespace ProcessWire;

/**
 * Less parser interface, for documentation purposes only
 *
 */
interface AdminThemeUikitLessInterface {

	/**
	 * @param string $name
	 * @param mixed $value
	 * @return self
	 *
	 */
	public function setOption($name, $value);

	/**
	 * @param array $options
	 * @return object instance of \Less_Parser
	 *
	 */
	public function parser($options = array());

	/**
	 * @param string $file
	 * @return self
	 *
	 */
	public function addFile($file);

	/**
	 * @param array $files
	 * @return self
	 *
	 */
	public function addFiles($files);

	/**
	 * @param string $file
	 * @return bool
	 *
	 */
	public function saveCss($file);

	/**
	 * @return string
	 *
	 */
	public function getCss();
}
