<?php namespace ProcessWire;

/**
 * ProcessWire Paths
 *
 * Maintains lists of file paths, primarily used by the ProcessWire configuration.
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * @see http://processwire.com/api/variables/config/ Offical $config API variable Documentation
 * 
 * @property string $root Site root: /
 * @property string $templates Site templates: /site/templates/
 * @property string $fieldTemplates Site field templates /site/templates/fields/
 * @property string $adminTemplates Admin theme template files: /wire/templates-admin/ or /site/templates-admin/
 * @property string $modules Core modules: /wire/modules/
 * @property string $siteModules Site-specific modules: /site/modules/
 * @property string $core ProcessWire core files: /wire/core/
 * @property string $site ProcessWire site files /site/
 * @property string $assets Site-specific assets: /site/assets/
 * @property string $cache Site-specific cache: /site/assets/cache/
 * @property string $logs Site-specific logs: /site/assets/logs/
 * @property string $files Site-specific files: /site/assets/files/
 * @property string $tmp Temporary files: /site/assets/tmp/
 * @property string $sessions Session files: /site/assets/sessions/
 *
 * The following properties are only in $config->urls
 * ==================================================
 * @property string $admin Admin URL
 * @property string|null $next URL to next pagination of current page, when applicable (populated by MarkupPagerNav, after render)
 * @property string|null $prev URL to previous pagination of current page, when applicable (populated by MarkupPagerNav, after render)
 * 
 * The following are in $config->urls and equivalent to previously mentioned properties, but include scheme + host
 * ===============================================================================================================
 * @property string $httpRoot
 * @property string $httpTemplates
 * @property string $httpAdminTemplates
 * @property string $httpModules
 * @property string $httpSiteModules
 * @property string $httpAssets
 * @property string $httpFiles 
 * @property string $httpNext
 * @property string $httpPrev
 *
 * The "http" may be optionally prepended to any property accessed from $config->urls (including those you add yourself).
 *
 */

class Paths extends WireData {

	/**
	 * Cached root 
	 * 
	 * @var string
	 * 
	 */
	protected $_root = '';

	/**
	 * Construct the Paths
	 *
	 * @param string $root Path of the root that will be used as a base for stored paths.
	 *
	 */
	public function __construct($root) {
		$this->_root = $root;
		$this->useFuel(false);
	}

	/**
	 * Given a path, normalize it to "/" style directory separators if they aren't already
	 *
	 * @static
	 * @param string $path
	 * @return string
	 *
	 */
	public static function normalizeSeparators($path) {
		if(DIRECTORY_SEPARATOR == '/') return $path; 
		$path = str_replace(DIRECTORY_SEPARATOR, '/', $path); 
		return $path; 
	}

	/**
	 * Set the given path key
	 *
	 * @param string $key
	 * @param mixed $value If the first character of the provided path is a slash, then that specific path will be used without modification.
	 * 	If the first character is anything other than a slash, then the 'root' variable will be prepended to the path.
	 * @return this
	 *
	 */
	public function set($key, $value) {
		$value = self::normalizeSeparators($value); 
		if($key == 'root') {
			$this->_root = $value;
			return $this;
		}
		return parent::set($key, $value); 
	}

	/**
	 * Return the requested path variable
	 *
	 * @param object|string $key
	 * @return mixed|null|string The requested path variable
	 *
	 */
	public function get($key) {
		static $_http = null;
		if($key == 'root') return $this->_root;
		$http = '';
		if(is_object($key)) {
			$key = "$key";
		} else if(strpos($key, 'http') === 0) {
			if(is_null($_http)) {
				$scheme = $this->wire('input')->scheme;
				if(!$scheme) $scheme = 'http';
				$httpHost = $this->wire('config')->httpHost; 
				if($httpHost) $_http = "$scheme://$httpHost";
			}
			$http = $_http;
			$key = substr($key, 4);
			$key[0] = strtolower($key[0]);
		} else if(is_file($key)) {
			$file = $this->normalizeSeparators($key);
			return str_replace($this->wire('config')->paths->root, $this->_root, $file);
		}
			
		if($key == 'root') {
			$value = $http . $this->_root;
		} else {
			$value = parent::get($key);
			if($value === null || !strlen($value)) return $value;
			$pos = strpos($value, '//');
			if($pos !== false && ($pos === 0 || ($pos > 0 && $value[$pos-1] === ':'))) {
				// fully qualified URL
			} else if($value[0] == '/' || (DIRECTORY_SEPARATOR != '/' && $value[1] == ':')) {
				// path specifies its own root
				$value = $http . $value;
			} else {
				// path needs root prepended
				$value = $http . $this->_root . $value;
			}
		}
		return $value; 
	}
}
