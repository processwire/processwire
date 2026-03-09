<?php namespace ProcessWire;

/**
 * ProcessWire FilenameArray
 *
 * Manages array of filenames or file URLs, like for $config->scripts and $config->styles.
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class FilenameArray extends Wire implements \IteratorAggregate, \Countable {

	/**
	 * Array of filenames indexed by MD5 hash of filename
	 * 
	 * @var array
	 * 
	 */
	protected $data = array();

	/**
	 * Add a file
	 * 
	 * @param string $filename
	 * @return $this
	 * 
	 */
	public function add($filename) {
		$key = $this->getKey($filename);
		$this->data[$key] = $filename; 
		return $this; 
	}

	/**
	 * Get key for $filename that excludes query strings
	 * 
	 * @param string $filename
	 * @return string
	 * 
	 */
	protected function getKey($filename) {
		$filename = (string) $filename;
		$pos = strpos($filename, '?'); 
		$key = $pos ? substr($filename, 0, $pos) : $filename;
		return md5($key);
	}

	/**
	 * Prepend $filename to the beginning
	 * 
	 * @param string $filename
	 * @return $this
	 * 
	 */
	public function prepend($filename) {
		$key = $this->getKey($filename);	
		$data = array($key => $filename); 
		foreach($this->data as $k => $v) {
			if($k == $key) continue; 
			$data[$k] = $v; 
		}
		$this->data = $data; 
		return $this; 	
	}

	/**
	 * Append $filename to the end
	 * 
	 * @param string $filename
	 * @return FilenameArray
	 * 
	 */
	public function append($filename) {
		return $this->add($filename); 
	}

	/**
	 * Make iterable 
	 * 
	 * @return \ArrayObject
	 * 
	 */
	#[\ReturnTypeWillChange] 
	public function getIterator() {
		return new \ArrayObject($this->data); 
	}

	/**
	 * Get cache-busting URLs for this FilenameArray
	 * 
	 * This is the same as iterating this FilenameArray except that it appends cache-busting
	 * query strings to the URLs that resolve to physical files. 
	 * 
	 * @param bool|null|string $useVersion See Config::versionUrls() for arument details
	 * @return array
	 * @throws WireException
	 * @see Config::versionUrls()
	 * @since 3.0.227
	 * 
	 */
	public function urls($useVersion = null) {
		return $this->wire()->config->versionUrls($this, $useVersion);
	}

	/**
	 * Make FilenameArray unique (deprecated)
	 * 
	 * @deprecated no longer necessary since the add() function ensures uniqueness
	 * @return FilenameArray
	 * 
	 */
	public function unique() {
		// no longer necessary since the add() function ensures uniqueness
		// $this->data = array_unique($this->data); 	
		return $this; 
	}

	/**
	 * Remove filename
	 * 
	 * @param string $filename
	 * @return $this
	 * 
	 */
	public function remove($filename) {
		$key = $this->getKey($filename); 
		unset($this->data[$key]); 
		return $this; 
	}

	/**
	 * Remove all filenames
	 * 
	 * @return $this
	 * 
	 */
	public function removeAll() {
		$this->data = array();
		return $this; 
	}

	/**
	 * Replace one file with another
	 * 
	 * @param string $oldFile
	 * @param string $newFile
	 * @return $this
	 * @since 3.0.215
	 * 
	 */
	public function replace($oldFile, $newFile) {
		$key = $this->getKey($oldFile);
		if(isset($this->data[$key])) {
			$this->data[$key] = $newFile;
		} else {
			$key = array_search($oldFile, $this->data);
			if($key !== false) {
				$this->data[$key] = $newFile;
			} else {
				$this->add($newFile);
			}
		}
		return $this;
	}

	/**
	 * String value containing print_r() dump of all filenames
	 * 
	 * @return string
	 * 
	 */
	public function __toString() {
		return print_r($this->data, true); 
	}

	/**
	 * Return count of items in this FilenameArray
	 * 
	 * @return int
	 * 
	 */
	#[\ReturnTypeWillChange] 
	public function count() {
		return count($this->data);
	}

}
