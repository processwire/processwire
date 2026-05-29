<?php namespace ProcessWire;

/**
 * ProcessWire API Docs
 * 
 * #pw-summary Provides methods for retrieving API.md documentation
 * #pw-body = 
 * The methods of this class can be accessed from `$wire->docs()->...`
 * 
 * 
 * 
 * #pw-body
 * 
 * ProcessWire 3.x, Copyright 2026 by Ryan Cramer
 * https://processwire.com
 * 
 */
class WireApiDocs extends Wire implements CliModule {
	
	/**
	 * Caches to persist across multiple instances
	 * 
	 * @var array 
	 * 
	 */
	static protected $caches = [];
	
	/**
	 * Paths to search for API files
	 * 
	 * @var array 
	 * 
	 */
	protected $apiPaths = [];
	
	/**
	 * Directory names to exclude 
	 * 
	 * @var array|string[] 
	 * 
	 */
	protected $excludeDirNames = [];
	
	/**
	 * API file names to look for (default=['API.md'])
	 * 
	 * @var string[] 
	 * 
	 */
	protected $apiFileNames = [ 'API.md' ];
	
	/**
	 * Array of debug info (when debug mode is on)
	 * 
	 * @var array 
	 * 
	 */
	protected $debugInfo = [];
	
	/**
	 * Debug mode enabled?
	 * 
	 * @var bool 
	 * 
	 */
	protected $debug = false;
	
	/**
	 * Verbose mode enabled?
	 * 
	 * @var bool 
	 * 
	 */
	protected $verbose = false;
	
	/**
	 * Construct
	 * 
	 * @param ProcessWire|null $wire ProcessWire instance (optional)
	 * 
	 */
	public function __construct(?Wire $wire = null) {
		parent::__construct();
		if($wire) $wire->wire($this);
	}
	
	/**
	 * Wired to API
	 * 
	 */
	public function wired() {
		parent::wired();
		$paths = $this->wire()->config->paths;
		
		$this->apiPaths([
			$paths->core,
			$paths->modules,
			$paths->siteModules,
			$paths->templates,
			$paths->classes,
		]);
		
		$this->excludeDirNames([
			'vendor', // i.e. vendor/*
			'assets', // i.e. site/assets/*
			'!\-\d!' // i.e. "dir-1.2.3"
		]);
	}
	
	/**
	 * Get API docs
	 * 
	 * Return value depends on given `$get` argument:
	 * 
	 * - With no arguments, this method returns array.
	 * - With an array argument, it returns an array.
	 * - With a string argument, it returns a string
	 * - Array return values always indexed by class name. 
	 * 
	 * ~~~~~
	 * $api = $wire->docs(); // get instance
	 * 
	 * // array return values
	 * $api->get(); // get summary array of all documented classes
	 * $api->get(['Pages']); // get docs array for single class
	 * $api->get(['Pages', 'Fields']); // get docs array for multiple classes
	 * $api->get(['Fieldtype*']); // get summary array for classes matching wildcard
	 * 
	 * // string return values
	 * $api->get('') // get names of all documented classes
	 * $api->get('Pages'); // get docs string for a single class
	 * $api->get('Fieldtype*'); // get names of all classes matching wildcard
	 * 
	 * // example of "docs array" return value
	 * return [ 
	 *   'Pages' => 'Full Pages docs in markdown format…',  
	 *   'Page' => 'Full Page docs in markdown format…', 
	 *    ...
	 * ];
	 * 
	 * // example of "summary array" return value
	 * return [
	 *   'Pages' => 'Summary of Pages',
	 *   'Page' => 'Summary of Page', …
	 * ];
	 * 
	 * // example of "summary string" return value
	 * return 
	 *   "Pages: Summary of Pages class \n" . 
	 *   "Page: Summary of Page class \n …";
	 * 
	 * // example of "docs string" return value
	 * return "Full API docs in markdown format…"
	 * ~~~~~
	 *
	 * @param array|string $get
	 * @return array|string
	 *
	 */
	public function get($get = []) {
		if(!$this->isWired()) $this->wired();
		
		$cacheKey = $this->getCacheKey($get);
		$docs = $this->getCache($cacheKey);
		if($docs !== null) {
			$this->debugInfo("Cache hit for key: $cacheKey");
			return $docs;
		}
		
		$findMode = strpos($cacheKey, '*') !== false;
		$listMode = empty($get) || $findMode;
		$getArray = true;
		$docs = [];
		
		if(!is_array($get)) {
			$get = [ $get ];
			if(!$this->verbose) $getArray = false;
		}
		
		$apiFiles = $this->findApiFiles();
		
		if($findMode) {
			$apiFiles = $this->filterApiFiles($get, $apiFiles);
		}
		
		if($listMode) {
			$return = $this->docsList($apiFiles, $getArray);
			
		} else {
			foreach($get as $name) {
				if(!isset($apiFiles[$name])) continue;
				$doc = $this->getClassApiDocs($name, $apiFiles[$name]);
				if($doc !== false) $docs[$name] = $doc;
			}
			$return = $getArray ? $docs : (string) reset($docs);
		}
		
		$this->setCache($cacheKey, $return);
		
		return $return;
	}
	
	/**
	 * Same as get() method but with verbose array return value
	 * 
	 * Return value is always an array of arrays, indexed by class name. 
	 * Each array includes:
	 * 
	 * - `className` (string): Name of class
	 * - `classFile` (string): Full path to class file
	 * - `apiVarName` (string): API variable name (blank if not applicable)
	 * - `isModule` (bool): Is this a module?
	 * - `docsFile` (string): Full path to API.md docs file
	 * - `docs` (string): Contents of API.md docs file in markdown format
	 *
	 * If getting all classes or using wildcards, the full `docs` key 
	 * is replaced with `summary`, which is just the first paragraph of the docs:
	 * 
	 * - `summary` (string): Brief bummary header from API.md
	 *
	 * @param array|string $get
	 * @param array $options 
	 *  - `exclude` (array): Names of properties to exclude from the result
	 *  - `rename` (array): Mapping of property names to new names
	 *  - `indexByClass` (bool): Index by class name? Specify false for plain PHP array (default=true)
	 * @return array
	 * 
	 */
	public function getVerbose($get = [], array $options = []) {
		$defaults = [
			'indexByClass' => true,
			'exclude' => [],
			'rename' => [],
		];
		$options = array_merge($defaults, $options);
		try {
			$this->verbose = true;
			$docs = $this->get($get);
		} finally {
			$this->verbose = false;
		}
		
		if(!$options['indexByClass']) $docs = array_values($docs);
		
		return $this->filterVerbose($docs, $options);
	}
	
	/**
	 * Filter according to options in verbose mode
	 * 
	 * @param array $docs
	 * @param array $options
	 * @return array
	 * 
	 */
	protected function filterVerbose(array $docs, array $options) {
		$exclude = !empty($options['exclude']) ? $options['exclude'] : [];
		$rename = !empty($options['rename']) ? $options['rename'] : [];
		
		if(count($exclude) || count($rename)) {
			foreach($docs as $key => $item) {
				foreach($rename as $from => $to) {
					if(isset($item[$from])) {
						$value = $item[$from];
						unset($item[$from]);
						$item[$to] = $value;
					}
				}
				foreach($exclude as $k) unset($item[$k]);
				$docs[$key] = $item;
			}
		}
		
		return $docs;
	}
	
	/**
	 * Get list of API docs in alternate array format (used by AgentTools)
	 * 
	 * @param array $get
	 * @return array
	 * 
	 */
	public function getList(array $get = []): array {
		return $this->getVerbose($get, [
			'indexByClass' => false,
			'rename' => [ 
				'className' => 'name',
				'docsFile' => 'file',
				'summary' => 'summary', // changes order to end
			], 
			'exclude' => [
				'apiVarName',
				'classFile',
				'isModule',
			],
		]);
	}
	
	/**
	 * Get markdown docs for one class
	 * 
	 * @param string $class
	 * @return string
	 * 
	 */
	public function getDocs($class) {
		return $this->get($class);
	}
	
	/**
	 * Get chapters for one class 
	 * 
	 * If given just a `$class` it returns a plain PHP array of chapter titles like this:
	 * ~~~~~
	 * [ 
	 *   0 => 'H2 Chapter title', 
	 *   1 => 'Another H2 chapter title',
	 *   ...
	 * ],
	 * ~~~~~
	 * 
	 * If `$recursive` is true, then it returns this:
	 * ~~~~~
	 * [
	 *   [
	 *     'title' => 'H2 Chapter title', 
	 *     'chapters' => [
	 *       [
	 *         'title' => 'H3 Chapter title',
	 *         'chapters' => [],
	 *       ],
	 *       [
	 *         'title' => 'Another H3 Chapter title',
	 *         'chapters' => [],
	 *       ],
	 *     ],
	 *   ],
	 *   [
	 *     'title' => 'Another H2 chapter title',
	 *     'chapters' => [],
	 *   ],
	 * ]
	 * ~~~~~
	 * 
	 * If `$getBody` is true then the return value is like the above except that there is also
	 * a `body` key along every `title` that contains the body text of the chapter. 
	 * 
	 * @param string $class
	 * @param bool $recursive Recurse into sub-chapters?
	 * @param bool $getBody Also return body of chapters?
	 * @return array
	 * 
	 */
	public function getChapters($class, $recursive = false, $getBody = false) {
		if(is_array($class)) $class = reset($class);
		if(!is_string($class)) return [];
		$docs = $this->get($class);
		$chapters = $this->extractChapters($docs, 2, $recursive, $getBody);
		return $chapters;
	}
	
	/**
	 * Get a chapter body/text from the given chapter index or title
	 * 
	 * @param string $class Chapter index number of title (title can also refer to subchapters)
	 * @param string|int $chapter
	 * 
	 */
	public function getChapterBody($class, $chapter) {
		if(ctype_digit("$chapter")) {
			$chapter = (int) $chapter;
			$chapters = $this->getChapters($class, false, true);
			return $chapters[$chapter] ?? [];
		}
		$docs = $this->get($class);
		$docs = preg_replace('/#\s{2,}/', '# ', $docs);
		if(strpos($docs, '# ' . $chapter) === false) return '';
		list($before, $body) = explode('# ' . $chapter, $docs, 2);
		$a = explode("\n", $before);
		$last = array_pop($a);
		$hLevel = substr_count($last, '#') + 1;
		$h = str_repeat('#', $hLevel);
		list($body,) = explode("\n$h ", $body, 2);
		while($hLevel > 1) {
			$hLevel--;
			$h = str_repeat('#', $hLevel);
			if(strpos($body, "\n$h ") !== false) {
				list($body,) = explode("\n$h ", $body, 2);
			}
		}
		return trim($body);
	}
	
	/**
	 * Extract chapters from given $body and return in array
	 * 
	 * @param string $body
	 * @param int $hLevel
	 * @param bool $recursive Recurse into sub-chapters?
	 * @param bool $getBody
	 * @return array
	 * 
	 */
	protected function extractChapters($body, $hLevel = 2, $recursive = true, $getBody = false) {
		$hash = "\n" . str_repeat('#', $hLevel);
		if(strpos($body, "$hash ") === false) return [];
		$parts = explode("$hash ", $body);
		array_shift($parts); // first part is headline/intro
		$chapters = [];
		foreach($parts as $part) {
			[ $title, $body ] = explode("\n", $part, 2);
			if($recursive || $getBody) {
				$chapter = ['title' => $title];
			} else {
				$chapter = $title;
			}
			if($recursive && $hLevel < 6 && strpos($body, "$hash# ") !== false) {
				list($body, $rest) = explode("$hash# ", $body, 2);
				if($getBody) $chapter['body'] = trim($body);
				$nextHash = "\n" . str_repeat('#', $hLevel + 1);
				$subchapters = $this->extractChapters("$nextHash $rest", $hLevel + 1, $recursive, $getBody);
				$chapter['chapters'] = $subchapters;
			} else if($getBody) {
				$chapter['body'] = trim($body);
			}
			$chapters[] = $chapter;
		}
		return $chapters;
	}
	
	/**
	 * Get key used for caching get() result
	 * 
	 * @param array|string $get
	 * @return string
	 * 
	 */
	protected function getCacheKey($get) {
		$cacheKey = 
			($this->verbose ? 'v' : '') .
			($this->debug ? 'd' : '') . 
			(is_array($get) ? 'a:' . implode('|', $get) : "s:$get") . '-' . 
			implode('|', $this->apiFileNames) . '-' . 
			implode('|', $this->excludeDirNames) . '-' . 
			implode('|', $this->apiPaths);
		$suffix = strpos($cacheKey, '*') !== false ? '*' : '';
		$cacheKey = sha1($cacheKey) . $suffix;
		return $cacheKey;
	}
	
	/**
	 * Get cache key for file scanning locations
	 * 
	 * @param string $prefix
	 * @param array bonus Any extra bonus material to include in cache key
	 * @return string
	 * 
	 */
	protected function getScanCacheKey($prefix, array $bonus = []) {
		return sha1($prefix . '-' . 
			implode('|', $this->apiFileNames) . '-' . 
			implode('|', $this->excludeDirNames) . '-' . 
			implode('|', $this->apiPaths) . '-' . 
			implode('|', $bonus)
		);
	}
	
	/**
	 * Identify what class is represented by given API.md file or false if unknown
	 * 
	 * #pw-internal
	 * 
	 * @param string $file
	 * @return string Returns class name if valid, false otherwise
	 * 
	 */
	public function getApiFileClass($file) {

		if(is_readable($file)) {
			$data = file_get_contents($file);
			if(strpos($data, '# ') === false) {
				$error = "No H1 found in API.md file header";
			} else if(!preg_match('!^#\s+([A-Z][a-zA-Z0-9]+)!m', $data, $matches)) {
				$error = "No class name found in API.md file H1";
			} else {
				$class = $matches[1];
				$file = dirname($file) . "/$class.php";
				if(file_exists($file)) return $class; 
				$error = "Class file does not exist";
			}
		} else {
			$error = "File is not readable";
		}
		
		if($error) $this->debugInfo("$error: $file");
		
		return false;
	}
	
	/**
	 * Find all API files (e.g., API.md) and also build cache of module files
	 * 
	 * #pw-internal
	 * 
	 * @param string|array $paths Paths to search for API files (default=$this->apiPaths)
	 * @return array
	 * 
	 */
	protected function findApiFiles($paths = []) {
		
		if(empty($paths)) $paths = $this->apiPaths;
		if(!is_array($paths)) $paths = [ $paths ];
		
		$filesCacheKey = $this->getScanCacheKey('apiFiles', $paths);
		$apiFiles = $this->getCache($filesCacheKey); 
		
		if($apiFiles !== null) return $apiFiles;
		
		$files = $this->wire()->files; 
		$modules = $this->wire()->modules;
		$apiFiles = [];
		$moduleFiles = [];
		
		$foundFiles = $files->find($paths, [
			'names' => $this->apiFileNames,
			'excludeDirNames' => $this->excludeDirNames,
		]);
		
		foreach($foundFiles as $file) {
			$dirname = dirname($file);
			$class = basename($dirname);
			
			if(strpos($file, '/modules/') !== false && $modules->isModule($class)) {
				$moduleFile = $modules->getModuleFile($class, [ 'real' => false ]);
				$moduleFiles[$class] = $moduleFile;
				
			} else if(!file_exists("$dirname/$class.php")) {
				// API.md might be a coincidental file name or dirname may 
				// differ from class name. Attempt discover class from API.md
				$class = $this->getApiFileClass($file);
				if($class === false) continue;
			}
			
			$apiFiles[$class] = $file;
		}
		
		$this->setCache($filesCacheKey, $apiFiles);
		$modulesCacheKey = $this->getScanCacheKey('moduleFiles');
		$this->setCache($modulesCacheKey, $moduleFiles);
		
		return $apiFiles;
	}
	
	/**
	 * Get module files that have their own API.md files, indexed by module class names
	 * 
	 * #pw-internal
	 * 
	 * @return array
	 * 
	 */
	public function getModuleFiles(): array {
		$modulesCacheKey = $this->getScanCacheKey('moduleFiles');
		$moduleFiles = $this->getCache($modulesCacheKey); 
		if($moduleFiles === null) {
			$this->findApiFiles(); // also caches moduleFiles
			$moduleFiles = $this->getCache($modulesCacheKey);
		}
		return $moduleFiles;
	}
	
	/**
	 * Filter given API files by given filters
	 * 
	 * #pw-internal
	 * 
	 * @param array $filters Array of filters, where each filter is a wildcard style pattern
	 * @param array $apiFiles
	 * @return array Filtered API files
	 * 
	 */
	protected function filterApiFiles(array $filters, array $apiFiles): array {
		foreach($apiFiles as $name => $file) {
			$found = false;
			foreach($filters as $filter) {
				$a = explode('*', $filter);
				foreach($a as $k => $v) $a[$k] = preg_quote($v);
				$filter = implode('.*', $a);
				$found = preg_match('/^' . $filter . '$/', $name);
				if($found) break;
			}
			if(!$found) unset($apiFiles[$name]);
		}
		return $apiFiles;
	}
	
	/**
	 * Get list/summary of API files
	 * 
	 * #pw-internal
	 * 
	 * @param array $apiFiles API files to summarize or omit to use default
	 * @param bool $getArray Get as an array? Specify false to get string (default=true)
	 * @param int $summaryMax Maximum length of summary
	 * @return array|string
	 * 
	 */
	protected function docsList(array $apiFiles = [], $getArray = true, $summaryMax = 200) {
		
		$sanitizer = $this->wire()->sanitizer;
		$rootPath = $this->wire()->config->paths->root;
		$rootPathLen = strlen($rootPath);
		$items = [];
		
		if(empty($apiFiles)) $apiFiles = $this->findApiFiles();
		
		foreach($apiFiles as $name => $file) {
			
			if(!is_readable($file)) continue;
			
			$contents = file($file);
			
			if($getArray || $this->verbose) {
				$summary = [];
				foreach($contents as $line) {
					$line = trim($line);
					if(empty($line)) continue;
					if(strpos($line, '# ') === 0) continue; // skip h1
					if(strpos($line, '##') === 0) break; // <h2> marks end of summary
					if(strpos($line, '---') === 0) break; // <hr> also marks end of summary
					if(strlen($line)) $summary[] = $line;
				}
				$summary = implode(' ', $summary);
				if(strlen($summary) > $summaryMax) {
					$summary = $sanitizer->truncate($summary, $summaryMax, ['type' => 'sentence']);
				}
			} else {
				$summary = '';
			}
			
			if($this->verbose) {
				$isModule = $this->isModule($name);
				$apiVarName = $this->isApiVar($name);
				if($isModule) {
					$classFile = $isModule;
				} else {
					$classFile = dirname($file) . "/$name.php"; 
				}
				$docsFile = $file;
				if(strpos($docsFile, $rootPath) === 0) $docsFile = substr($docsFile, $rootPathLen);
				if(strpos($classFile, $rootPath) === 0) {
					$classFile = substr($classFile, $rootPathLen);
				} else {
					$classFile = dirname($docsFile) . '/' . basename($classFile);
				}
				$items[$name] = [
					'className' => $name,
					'classFile' => $classFile,
					'apiVarName' => $apiVarName,
					'isModule' => (bool) $isModule,
					'docsFile' => $docsFile,
					'summary' => $summary, 
				];
			} else {
				$items[$name] = $getArray ? $summary : $name;
			}
		}
		
		return $getArray ? $items : implode("\n", $items) . "\n";
	}
	
	/**
	 * Get all API variable names indexed by class name
	 * 
	 * #pw-internal
	 *
	 * @param string $className Optional class name to get API var name for (default='')
	 * @return string|array Returns string of API var if given class name, array otherwise
	 * 
	 */
	public function getApiVars($className = '') {
		$apiVars = $this->getCache('apiVars');
		if(!is_array($apiVars)) {
			$apiVars = [];
			foreach($this->wire()->fuel as $name => $apiVar) {
				if(!$apiVar instanceof Wire) continue;
				$apiVars[wireClassName($apiVar)] = $name;
			}
			$this->setCache('apiVars', $apiVars);
		}
		if($className) {
			$apiVars = (string) ($apiVars[$className] ?? '');
		}
		return $apiVars;
	}
	
	/**
	 * Returns API var name if given class represents an API var (blank if not)
	 * 
	 * #pw-internal
	 * 
	 * @param string $className
	 * @return string
	 */
	public function isApiVar($className) {
		return $this->getApiVars($className);
	}
	
	/**
	 * Return module file if given class represents a module (blank if not)
	 * 
	 * #pw-internal
	 * 
	 * @param string $name
	 * @return string
	 */
	public function isModule($name) {
		$moduleFiles = $this->getModuleFiles();
		return $moduleFiles[$name] ?? '';
	}
	
	/**
	 * Get API docs for given class and API filename
	 * 
	 * @param string string $name Class name
	 * @param string string $filename Docs file name (API.md)
	 * @return array|false|string Returns array in verbose mode, false on fail, or string of docs
	 * 
	 */
	protected function getClassApiDocs(string $name, string $filename) {
		$rootPath = $this->wire()->config->paths->root;
		
		if(!is_readable($filename)) return false;
		$docs = file_get_contents($filename);
		
		if($this->debug) $docs = $this->wire()->sanitizer->truncate($docs, 300);
		if(!$this->verbose) return $docs;
		
		$classPath = dirname($filename) . "/";
		$moduleFile = $this->isModule($name);
		$classFile = $moduleFile ? $moduleFile : $classPath . wireClassName($name) . '.php';
		$docsFile = $filename;
		
		if(strpos($docsFile, $rootPath) === 0) {
			$docsFile = substr($docsFile, strlen($rootPath));
		}
		
		if(strpos($classFile, $rootPath) === 0) {
			$classFile = substr($classFile, strlen($rootPath));
		} else {
			$classFile = dirname($docsFile) . '/' . basename($classFile);
		}
		
		return [
			'className' => $name,
			'classFile' => $classFile, 
			'apiVarName' => $this->isApiVar($name),
			'isModule' => (bool) $moduleFile,
			'docsFile' => $docsFile,
			'docs' => $docs,
		];
	}
	
	/**
	 * Set value to cache
	 * 
	 * @param string $key
	 * @param mixed $value
	 * 
	 */
	protected function setCache($key, $value) {
		self::$caches[$key] = $value;
	}
	
	/**
	 * Get cached value or null if not cached
	 * 
	 * @param string $key
	 * @return mixed|null
	 * 
	 */
	protected function getCache($key) {
		return self::$caches[$key] ?? null;
	}
	
	/**
	 * Reset/clear all persistent caches
	 *
	 */
	public static function reset() {
		self::$caches = [];
	}
	
	/**
	 * Get or set an array property
	 *
	 * @param string $property
	 * @param array|string|null $set
	 * @param bool $replace
	 * @return array
	 *
	 */
	protected function getOrSetArray($property, $set = null, $replace = false) {
		if(is_string($set) && !empty($set)) $set = [ $set ];
		if(is_array($set)) {
			if($replace) {
				$this->$property = $set;
			} else {
				$this->$property = array_merge($this->$property, $set);
			}
		}
		return $this->$property;
	}
	
	/**
	 * Get or set paths to scan for API files
	 *
	 * @param array|string|null $set Specify array or string (one item) to add, omit to get
	 * @param bool $replace Replace existing items? Omit to append to existing.
	 * @return array
	 *
	 */
	public function apiPaths($set = null, $replace = false) {
		return $this->getOrSetArray('apiPaths', $set, $replace);
	}
	
	/**
	 * Get or set API file names (default=['API.md'])
	 *
	 * @param array|string|null $set Specify array or string (one item) to add, omit to get
	 * @param bool $replace Replace existing items? Omit to append to existing.
	 * @return array
	 *
	 */
	public function apiFileNames($set = null, $replace = false) {
		return $this->getOrSetArray('apiFileNames', $set, $replace);
	}
	
	/**
	 * Get or set directory names to exclude (can also include regex patterns of dir names)
	 *
	 * @param array|string|null $set Specify array or string (one item) to add, omit to get
	 * @param bool $replace Replace existing items? Omit to append to existing.
	 * @return array
	 *
	 */
	public function excludeDirNames($set = null, $replace = false) {
		return $this->getOrSetArray('excludeDirNames', $set, $replace);
	}
	
	/**
	 * Get or set debug info
	 *
	 * @param array|string|null $set Specify array or string to add, omit to get
	 * @return array
	 *
	 */
	public function debugInfo($set = null) {
		if(!$this->debug) return [];
		return $this->getOrSetArray('debugInfo', $set, false);
	}
	
	/**
	 * Get or set debug mode
	 *
	 * @param bool|null $debug
	 * @return bool
	 *
	 */
	public function debug($debug = null) {
		if(is_bool($debug)) $this->debug = $debug;
		return $this->debug;
	}
	
	/**
	 * Execute given command
	 *
	 * Output: standard output. This ensures CLI users see line-by-line output
	 * rather than everything at once when execution finishes.
	 *
	 * No need for a trailing newline in the output: ProcessWire adds one already.
	 *
	 * @param array $args Command line arguments passed, excluding module/cli name
	 *
	 */
	public function executeCli(array $args) {
		if(empty($args)) return;
		
		$out = 'Not found';
		$invalid = 'Invalid syntax';
		$error = false;
		$action = strtolower($args[0]);
		
		switch($action) {
			case 'list': 
				$out = $this->get($args[1] ?? ''); 
				break;
			case 'list-json': 
				$out = $this->getList(isset($args[1]) ? [ $args[1] ] : []); 
				break;
			case 'list-json-verbose': 
				if(!empty($args[1]) && strpos($args[1], '*') === false) $args[1] = "*$args[1]";
				$out = array_values($this->getVerbose(isset($args[1]) ? [ $args[1] ] : [])); 
				break;
			case 'get': 
				$out = (isset($args[1]) ? $this->get($args[1]) : $invalid); 
				break;
			case 'get-json': 
				$out = (isset($args[1]) ? $this->getVerbose($args[1]) : $invalid); 
				if(is_array($out)) $out = reset($out);
				break;
			case 'toc':	
			case 'toc-json':	
				$class = $args[1] ?? '';
				if($class) $out = $this->getChapters($class);
				if($class && $action === 'toc') $out = implode("\n", $out);
				break;
			case 'body':
				$class = $args[1] ?? '';
				$chapter = $args[2] ?? '';
				$out = $class && $chapter ? $this->getChapterBody($class, $chapter) : ''; 
				break;
		}
		
		if(empty($out)) {
			$out = 'Not found';
			$error = true;
		} else if($out === $invalid) {
			$error = true;
		}
	
		if(is_array($out)) {
			$out = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		
		echo $out;
		
		if($error) exit(1);
	}
	
	/**
	 * Get array of allowed commands
	 *
	 * This is used only for rendering help when user enters no command or
	 * when they request a list of commands.
	 *
	 * Returned array keys are command names and values are 1-line labels
	 * Or it can be a regular PHP array of command names if labels are not needed.
	 * Or it can just be a string of whatever you want, and ProcessWire will output
	 * it as-is.
	 *
	 * @return string[]|string Example: `[ 'hello' => 'Hello World' ]` or `[ 'hello' ]`
	 *
	 */
	public function getCliCommands() {
		return [ 
			'list' => 'List classes with API docs as string',
			'list \'Class*\'' => 'List classes matching wildcard pattern as string',
			'list-json' => 'List classes with API docs in JSON',
			'list-json \'Class*\'' => 'List classes matches wildcard pattern in JSON',
			'list-json-verbose' => 'List classes with API docs in JSON verbose mode',
			'list-json-verbose \'Class*\'' => 'List classes matching pattern in verbose JSON',
			'get Class' => 'Get API docs for given Class',
			'get-json Class ' => 'Get JSON API docs for given Class',
			'toc Class ' => 'Get table of contents for given Class (string)',
			'toc-json Class' => 'Get table of contents for given Class (json)',
			'body Class num' => 'Get body for given Class and chapter number',
			'body Class \'title\'' => 'Get body for given Class and chapter title',
		];
	}
}
