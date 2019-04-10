<?php namespace ProcessWire;

/**
 * ProcessWire configuration paths and URLs
 * 
 * #pw-headline Configuration paths and URLs (Paths class)
 * #pw-summary Maintains lists of file paths or URLs, primarily used by the ProcessWire $config->paths and $urls API variables.
 * #pw-summary-paths-only These properties are only useful when accessed from `$config->paths` as they are not HTTP accessible as URLs. 
 * #pw-summary-urls-only These properties apply only to the `$urls` or `$config->urls`. Do not use them with `$config->paths`. 
 * #pw-summary-pagination These properties apply only to the `$urls` or `$config->urls` and only when pagination is active for the current request.
 * 
 * #pw-body = 
 * The Paths class is used by `$config->paths` and `$config->urls`. The `$config->paths` refers to server disk paths
 * while `$config->urls` refers to web server URLs. All of the same properties are present on both, though several properties
 * are only useful on one or the other (as outlined below). You can access a path or URL like this:
 * ~~~~~
 * $path = $config->paths->templates; // i.e. /path/to/htdocs/site/templates/ 
 * $url = $config->urls->templates; // i.e. /site/templates/
 * ~~~~~
 * The `$config->urls` property can also be accessed more directly via the `$urls` API variable (in PW 3.x+): 
 * ~~~~~
 * $url = $urls->templates; // i.e. /site/templates/
 * ~~~~~
 * For `$config->urls` (or `$urls`), if you prepend `http` to any of the property names (making it camelCase) it will 
 * return the full http/https URL rather then the relative URL: 
 * ~~~~~
 * $httpUrl = $config->urls->httpTemplates; // i.e. https://domain.com/site/templates/
 * $httpUrl = $urls->httpTemplates; // same as above
 * ~~~~~
 * You may optionally add your own properties as well. If you add a path/url without a leading slash “/” it is assumed to 
 * be relative to the `root` property. If it has a leading slash, then it is absolute. 
 * ~~~~~
 * // add new urls properties
 * $urls->set('css', 'site/templates/css/'); // relative to site root
 * $urls->set('uikit', '/uikit/dist/'); // absolute 
 * 
 * // get properties that were set
 * echo $urls->get('css'); // i.e. /site/templates/css/
 * echo $urls->get('uikit'); // i.e. /uikit/dist/
 * echo $urls->get('httpCss'); // i.e. https://domain.com/site/templates/css/
 * echo $urls->get('httpUikit'); // i.e. https://domain.com/uikit/dist/
 * echo $urls->httpUikit; // same as above (using get method call is optional for any of these)
 * ~~~~~
 * Do not set `http` properties directly, as they are dynamically generated from `urls` properties at runtime upon request.
 * 
 * In the examples on this page, you can replace the `$urls` variable with `$config->paths` if you need to get the server path
 * instead of a URL. As indicated earlier, `$urls` can aso be accessed at the more verbose `$config->urls` if you prefer. 
 * 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2019 by Ryan Cramer
 * https://processwire.com
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * @property string $root Site root: / (or subdirectory if site not at domain root)
 * @property string $templates Site templates: /site/templates/
 * @property string $fieldTemplates Site field templates /site/templates/fields/ #pw-group-paths-only
 * @property string $adminTemplates Admin theme template files: /wire/templates-admin/ or /site/templates-admin/ #pw-internal
 * @property string $modules Core modules: /wire/modules/
 * @property string $siteModules Site-specific modules: /site/modules/
 * @property string $core ProcessWire core files: /wire/core/
 * @property string $site ProcessWire site files /site/
 * @property string $assets Site-specific assets: /site/assets/
 * @property string $cache Site-specific cache: /site/assets/cache/ #pw-group-paths-only
 * @property string $logs Site-specific logs: /site/assets/logs/ #pw-group-paths-only
 * @property string $files Site-specific files: /site/assets/files/
 * @property string $tmp Temporary files: /site/assets/tmp/ #pw-group-paths-only
 * @property string $sessions Session files: /site/assets/sessions/ #pw-group-paths-only
 *
 * The following properties are only in $config->urls
 * ==================================================
 * @property string $admin Admin URL #pw-group-urls-only
 * @property string|null $next URL to next pagination of current page, when applicable (populated by MarkupPagerNav, after render) #pw-group-urls-only #pw-group-pagination
 * @property string|null $prev URL to previous pagination of current page, when applicable (populated by MarkupPagerNav, after render) #pw-group-urls-only #pw-group-pagination
 * 
 * The following are in $config->urls and equivalent to previously mentioned properties, but include scheme + host
 * ===============================================================================================================
 * @property-read string $httpRoot Full http/https URL to site root (i.e. https://domain.com/). #pw-group-urls-only
 * @property-read string $httpTemplates  Full http/https URL to site templates (i.e. https://domain.com/site/templates/). #pw-group-urls-only
 * @property-read string $httpAdminTemplates Full http/https URL to admin templates. #pw-internal
 * @property-read string $httpModules Full http/https URL to core (wire) modules. #pw-group-urls-only
 * @property-read string $httpSiteModules Full http/https URL to site modules. #pw-group-urls-only
 * @property-read string $httpAssets Full http/https URL to site assets (i.e. https://domain.com/site/assets/). #pw-group-urls-only
 * @property-read string $httpFiles Full http/https URL to site assets files (i.e. https://domain.com/site/assets/files/). #pw-group-urls-only
 * @property-read string $httpNext Full http/https URL to next pagination of current page (when applicable). #pw-group-urls-only #pw-group-pagination
 * @property-read string $httpPrev Full http/https URL to prev pagination of current page (when applicable). #pw-group-urls-only #pw-group-pagination
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
	 * #pw-internal
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
	 * Set a new path/URL location
	 * 
	 * #pw-group-methods
	 *
	 * @param string $key
	 * @param mixed $value If the first character of the provided path is a slash, then that specific path will be used without modification.
	 * 	If the first character is anything other than a slash, then the 'root' variable will be prepended to the path.
	 * @return Paths|WireData
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
	 * Return the requested path or URL (functionally the same as direct access)
	 * 
	 * #pw-group-methods
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
