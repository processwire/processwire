<?php namespace ProcessWire;

/**
 * Extra extension for Pagefile or Pageimage objects
 *
 * Properties 
 * ==========
 * @property string $url Local URL/path to file 
 * @property string $httpUrl Full HTTP URL with scheme and host
 * @property string $URL No-cache version of url
 * @property string $HTTPURL No-cache version of httpUrl
 * @property string $filename Full disk path/file
 * @property string $pathname Alias of filename
 * @property string $basename Just the basename without path
 * @property string $extension File extension
 * @property string $ext Alias of extension
 * @property bool $exists Does the file exist?
 * @property int $filesize Size of file in bytes
 * @property Pagefile|Pageimage $pagefile Source Pageimage object
 * 
 * The following properties affect the behavior of the URL-related methods
 * =======================================================================
 * @property bool $useSrcUrlOnFail Use source Pagefile URL if extra image does not exist and cannot be created? (default=false)
 * @property bool $useSrcUrlOnSize Use source Pagefile URL if extra file is larger than source file? (default=false)
 *
 * Hookable methods 
 * ================
 * @method bool create()
 * 
 */

class PagefileExtra extends WireData {

	/**
	 * @var Pagefile|Pageimage
	 * 
	 */
	protected $pagefile;

	/**
	 * @var string
	 * 
	 */
	protected $extension = '';

	/**
	 * Previous filename, if it changed
	 * 
	 * @var string
	 * 
	 */
	protected $filenamePrevious = '';
	
	/**
	 * Construct
	 *
	 * @param Pagefile|Pageimage $pagefile
	 * @param $extension
	 * 
	 */
	public function __construct(Pagefile $pagefile, $extension) {
		$pagefile->wire($this);	
		$this->setPagefile($pagefile);
		$this->setExtension($extension);
		$this->useSrcUrlOnFail = true;
		$this->useSrcUrlOnSize = false;
		return parent::__construct();
	}

	/**
	 * Set Pagefile instance this extra is connected to
	 * 
	 * @param Pagefile $pagefile
	 * 
	 */
	public function setPagefile(Pagefile $pagefile) {
		$this->pagefile = $pagefile;
	}

	/**
	 * Set extension for this extra
	 * 
	 * @param $extension
	 * 
	 */
	public function setExtension($extension) {
		$this->extension = $extension;
	}

	/**
	 * Does the extra file currently exist?
	 * 
	 * @param bool $clear Clear stat cache before checking? (default=false)
	 * @return bool
	 * 
	 */
	public function exists($clear = false) {
		if($clear) clearstatcache();
		return is_readable($this->filename());
	}

	/**
	 * Return the file size in bytes
	 * 
	 * @return int
	 * 
	 */
	public function filesize() {
		return (int) @filesize($this->filename());
	}

	/**
	 * Return the full server disk path to the extra file, whether it exists or not
	 * 
	 * @return string
	 * 
	 */
	public function filename() {
		$pathinfo = pathinfo($this->pagefile->filename());
		$filename = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.' . $this->extension;
		if(empty($this->filenamePrevious)) $this->filenamePrevious = $filename;
		return $filename;
	}

	/**
	 * Return just the basename (no path)
	 * 
	 * @return string
	 * 
	 */
	public function basename() {
		return basename($this->filename()); 
	}

	/**
	 * Return the URL to the extra file, creating it if it does not already exist
	 * 
	 * @param bool $fallback Allow falling back to source Pagefile URL when appropriate?
	 * @return string
	 * 
	 */
	public function url($fallback = true) {
		if(!$this->exists()) {
			$this->create(); 
			if($fallback && !$this->exists() && $this->useSrcUrlOnFail) {
				// return original pagefile URL if the extra cannot be created
				return $this->pagefile->url(); 
			}
		}
		if($fallback && $this->useSrcUrlOnSize && $this->filesize() > $this->pagefile->filesize()) {
			$url = $this->pagefile->url();
		} else {
			$pathinfo = pathinfo($this->pagefile->url());
			$url = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.' . $this->extension;
		}
		
		return $url;
	}

	/**
	 * Return the HTTP URL to the extra file
	 * 
	 * @return string
	 * 
	 */
	public function httpUrl() {
		return str_replace($this->pagefile->url(), $this->url(), $this->pagefile->httpUrl());
	}

	/**
	 * Unlink/delete the extra file
	 * 
	 * @return bool
	 * 
	 */
	public function unlink() {
		if(!$this->exists()) return false;
		return $this->wire('files')->unlink($this->filename());
	}

	/**
	 * Rename the extra file to be consistent with Pagefile name
	 * 
	 * @return bool
	 * 
	 */
	public function rename() {
		if(!$this->exists()) return false;
		if(!$this->filenamePrevious) return false;
		return $this->wire('files')->rename($this->filenamePrevious, $this->filename());
	}

	/**
	 * Create the extra file
	 * 
	 * Must be implemented by a hook or by descending class
	 * 
	 * @return bool Returns true on success, false on fail
	 * 
	 */
	public function ___create() { 
		return false;
	}

	/**
	 * Get property
	 * 
	 * @param string $key
	 * @return bool|int|mixed|null|string
	 * 
	 */
	public function get($key) {
		switch($key) {
			case 'exists':
				$value = $this->exists();
				break;
			case 'filesize':	
				$value = $this->filesize();
				break;
			case 'url':
				$value = $this->url();
				break;
			case 'filename':
			case 'pathname':	
				$value = $this->filename();
				break;
			case 'filenamePrevious':	
				$value = $this->filenamePrevious && $this->filenamePrevious != $this->filename() ? $this->filenamePrevious : '';
				break;
			case 'basename':
				$value = $this->basename();
				break;
			case 'ext':	
			case 'extension':
				$value = $this->extension;
				break;
			case 'URL':
			case 'HTTPURL':
				$value = str_replace($this->pagefile->url(), $this->url(), $this->pagefile->$key);
				break;
			case 'pagefile':	
				$value = $this->pagefile;
				break;
			default:	
				$value = parent::get($key);
				if($value === null) $value = $this->pagefile->get($key);
		}
		return $value;
	}

	/**
	 * @return string
	 * 
	 */
	
	public function __toString() {
		return $this->basename();
	}
}