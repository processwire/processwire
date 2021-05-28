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
	 * @param string $url
	 * @return self
	 *
	 */
	public function addFile($file, $url = '');

	/**
	 * @param array $files
	 * @return self
	 *
	 */
	public function addFiles($files);

	/**
	 * @param string $file
	 * @param array $options
	 * @return bool
	 *
	 */
	public function saveCss($file, array $options = array());

	/**
	 * @return string
	 *
	 */
	public function getCss();
}
