<?php namespace ProcessWire;

/**
 * Class FileCompiler
 *
 * @todo determine whether we should make storage in dedicated table rather than using wire('cache').
 * @todo handle race conditions for multiple requests attempting to compile the same file(s).
 * 
 * @method string compile($sourceFile)
 * @method string compileData($data, $sourceFile)
 * 
 */

class FileCompiler extends Wire {

	/**
	 * Compilation options for this FileCompiler instance
	 * 
	 * @var array
	 * 
	 */
	protected $options = array(
		'includes' => true,	// compile include()'d files too?
		'namespace' => true, // compile to make compatible with PW namespace when necessary?
		'modules' => false, // compile using installed FileCompiler modules
		'skipIfNamespace' => false, // skip compiled file if original declares a namespace? (note: file still compiled, but not used)
	);

	/**
	 * Options for ALL FileCompiler instances
	 * 
	 * Values shown below are for reference only as the get overwritten by $config->fileCompilerOptions at runtime.
	 * 
	 * @var array
	 * 
	 */
	protected $globalOptions = array(
		'siteOnly' => false,  // only allow compilation of files in /site/ directory
		'showNotices' => true, // show notices about compiled files to superuser
		'logNotices' => true, // log notices about compiled files and maintenance to file-compiler.txt log. 
		'chmodFile' => '', // mode to use for created files, i.e. "0644"
		'chmodDir' => '',  // mode to use for created directories, i.e. "0755"
		'exclusions' => array(), // exclude files or paths that start with any of these (gets moved to $this->exclusions array)
		'extensions' => array('php', 'module', 'inc'), // file extensions we compile (gets moved to $this->extensions array)
		'cachePath' => '', // path where compiled files are stored (default is /site/assets/cache/FileCompiler/, moved to $this->cachePath)
	);
	
	/**
	 * Path to source files directory
	 *
	 * @var string
	 *
	 */
	protected $sourcePath;

	/**
	 * Path to compiled files directory
	 * 
	 * @var string
	 * 
	 */
	protected $targetPath = null;

	/**
	 * Path to root of compiled files directory (upon which targetPath is based)
	 * 
	 * Set via the $config->fileCompilerOptions['cachePath'] setting. 
	 * 
	 * @var string
	 * 
	 */
	protected $cachePath;

	/**
	 * Files or directories that should be excluded from compilation
	 * 
	 * @var array
	 * 
	 */
	protected $exclusions = array();

	/**
	 * File extensions that we compile and copy
	 * 
	 * @var array
	 * 
	 */
	protected $extensions = array(
		'php',
		'module',
		'inc',
	);

	/**
	 * Detected file namespace (during compileData)
	 * 
	 * @var string
	 * 
	 */
	protected $ns = '';

	/**
	 * String with raw PHP blocks only, and with any quoted values removed. 
	 * 
	 * @var string
	 * 
	 */
	protected $rawPHP = '';

	/**
	 * Same as raw PHP but with all quoted values converted to literal "string"
	 * 
	 * @var string
	 * 
	 */
	protected $rawDequotedPHP = '';
	
	/**
	 * Construct
	 * 
	 * @param string $sourcePath Path where source files are located
	 * @param array $options Indicate which compilations should be performed (default='includes' and 'namespace')
	 * 
	 */
	public function __construct($sourcePath, array $options = array()) {
		
		$this->options = array_merge($this->options, $options);
		$globalOptions = $this->wire('config')->fileCompilerOptions; 
		
		if(is_array($globalOptions)) {
			$this->globalOptions = array_merge($this->globalOptions, $globalOptions);
		}
		
		if(!empty($this->globalOptions['extensions'])) {
			$this->extensions = $this->globalOptions['extensions'];
		}
		
		if(empty($this->globalOptions['cachePath'])) {
			$this->cachePath = $this->wire('config')->paths->cache . $this->className() . '/';
		} else {
			$this->cachePath = rtrim($this->globalOptions['cachePath'], '/') . '/';
		}
		
		if(!strlen(__NAMESPACE__)) {
			// when PW compiled without namespace support
			$this->options['skipIfNamespace'] = false;
			$this->options['namespace'] = true;
		}
		
		if(strpos($sourcePath, '..') !== false) $sourcePath = realpath($sourcePath);
		if(DIRECTORY_SEPARATOR != '/') $sourcePath = str_replace(DIRECTORY_SEPARATOR, '/', $sourcePath);
		$this->sourcePath = rtrim($sourcePath, '/') . '/';
	}

	/**
	 * Initialize paths
	 * 
	 * @throws WireException
	 * 
	 */
	protected function init() {
		
		static $preloaded = false;
		$config = $this->wire('config');
		
		if(!$preloaded) {
			$this->wire('cache')->preloadFor($this);
			$preloaded = true;
		}
		
		if(!empty($this->globalOptions['exclusions'])) {
			$this->exclusions = $this->globalOptions['exclusions'];
		}
		
		$this->addExclusion($config->paths->wire);

		$rootPath = $config->paths->root;
		$targetPath = $this->cachePath; 
		
		if(strpos($this->sourcePath, $targetPath) === 0) {
			// sourcePath is inside the targetPath, correct this 
			$this->sourcePath = str_replace($targetPath, '', $this->sourcePath);
			$this->sourcePath = $rootPath . $this->sourcePath;
		}

		$t = str_replace($rootPath, '', $this->sourcePath);
		if(DIRECTORY_SEPARATOR != '/' && strpos($t, ':')) $t = str_replace(':', '', $t);
		$this->targetPath = $targetPath . trim($t, '/') . '/';
		$this->ns = '';
	}

	/**
	 * Make a directory with proper permissions
	 * 
	 * @param string $path Path of directory to create
	 * @param bool $recursive Default is true
	 * @return bool
	 * 
	 */
	protected function mkdir($path, $recursive = true) {
		$chmod = $this->globalOptions['chmodDir'];
		if(empty($chmod) || !is_string($chmod) || strlen($chmod) < 2) $chmod = null;
		return $this->wire('files')->mkdir($path, $recursive, $chmod);
	}

	/**
	 * Change file to correct mode for FileCompiler
	 * 
	 * @param string $filename
	 * @return bool
	 * 
	 */
	protected function chmod($filename) {
		$chmod = $this->globalOptions['chmodFile'];
		if(empty($chmod) || !is_string($chmod) || strlen($chmod) < 2) $chmod = null;
		return $this->wire('files')->chmod($filename, false, $chmod);
	}

	/**
	 * Initialize the target path, making sure that it exists and creating it if not
	 * 
	 * @throws WireException
	 * 
	 */
	protected function initTargetPath() {
		if(!is_dir($this->targetPath)) {
			if(!$this->mkdir($this->targetPath)) {
				throw new WireException("Unable to create directory $this->targetPath");
			}
		}
	}

	/**
	 * Populate the $this->rawPHP data which contains only raw php without quoted values
	 * 
	 * @param string $data
	 * 
	 */
	protected function initRawPHP(&$data) {
		
		$this->rawPHP = '';
		$this->rawDequotedPHP = '';
		
		$phpOpen = '<' . '?';
		$phpClose = '?' . '>';
		$phpBlocks = explode($phpOpen, $data);
		
		foreach($phpBlocks as $key => $phpBlock) {
			$pos = strpos($phpBlock, $phpClose);
			if($pos !== false) {
				$closeBlock = substr($phpBlock, strlen($phpClose) + 2);
				if(strrpos($closeBlock, '{') && strrpos($closeBlock, '}') && strrpos($closeBlock, '=')
					&& strrpos($closeBlock, '(') && strrpos($closeBlock, ')')
					&& preg_match('/\sif\s*\(/', $closeBlock) 
					&& preg_match('/\$[_a-zA-Z][_a-zA-Z0-9]+/', $closeBlock)) {
					// closeBlock still looks a lot like PHP, leave $phpBlock as-is
					// happens when for example a phpClose is within a PHP string
				} else {
					$phpBlock = substr($phpBlock, 0, $pos);
				}
			}
			$this->rawPHP .= $phpOpen . $phpBlock . $phpClose . "\n";
		}
	
		// remove docblocks/comments
		// $this->rawPHP = preg_replace('!/\*.+?\*/!s', '', $this->rawPHP);
		
		// remove escaped quotes
		$this->rawDequotedPHP = str_replace(array('\\"', "\\'"), '', $this->rawPHP); 
		
		// remove double quoted blocks
		$this->rawDequotedPHP = preg_replace('/([\s(.=,])"[^"]*"/s', '$1"string"', $this->rawDequotedPHP);
		
		// remove single quoted blocks
		$this->rawDequotedPHP = preg_replace('/([\s(.=,])\'[^\']*\'/s', '$1\'string\'', $this->rawDequotedPHP);
	
	}	

	/**
	 * Allow the given filename to be compiled?
	 * 
	 * @param string $filename Full path and filename to compile (this property can be modified by the function).
	 * @param string $basename Just the basename (this property can be modified by the function). 
	 * @return bool 
	 * 
	 */
	protected function allowCompile(&$filename, &$basename) {
		
		if($this->globalOptions['siteOnly']) {
			// only files in /site/ are allowed for compilation
			if(strpos($filename, $this->wire('config')->paths->site) !== 0) {
				// sourcePath is somewhere outside of the PW /site/, and not allowed
				return false;
			}
		}

		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if(!in_array(strtolower($ext), $this->extensions)) {
			if(!strlen($ext) && !is_file($filename)) { 
				foreach($this->extensions as $ext) {
					if(is_file("$filename.$ext")) {
						// assume PHP file extension if none given, for cases like wireIncludeFile
						$filename .= ".$ext";
						$basename .= ".$ext";
					}
				}
			} else {
				return false;
			}
		}

		if(!is_file($filename)) {
			return false;
		}
		
		$allow = true;
		foreach($this->exclusions as $pathname) {
			if(strpos($filename, $pathname) === 0) {
				$allow = false;
				break;
			}
		}

		return $allow; 
	}

	/**
	 * Compile given source file and return compiled destination file
	 * 
	 * @param string $sourceFile Source file to compile (relative to sourcePath given in constructor)
	 * @return string Full path and filename of compiled file. Returns sourceFile is compilation is not necessary.
	 * @throws WireException if given invalid sourceFile
	 * 
	 */
	public function ___compile($sourceFile) {
		
		$this->init();
		
		if(strpos($sourceFile, $this->sourcePath) === 0) {
			$sourcePathname = $sourceFile;
			$sourceFile = str_replace($this->sourcePath, '/', $sourceFile);
		} else {
			$sourcePathname = $this->sourcePath . ltrim($sourceFile, '/');
		}

		if(!$this->allowCompile($sourcePathname, $sourceFile)) return $sourcePathname;

		$this->initTargetPath();

		$cacheName = md5($sourcePathname);
		$sourceHash = md5_file($sourcePathname);
		$targetHash = '';
		
		$targetPathname = $this->targetPath . ltrim($sourceFile, '/');
		$compileNow = true;
		
		if(is_file($targetPathname)) {
			// target file already exists, check if it is up-to-date
			// $targetData = file_get_contents($targetPathname);
			$targetHash = md5_file($targetPathname);
			$cache = $this->wire('cache')->getFor($this, $cacheName);
			if($cache && is_array($cache)) {
				if($cache['target']['hash'] == $targetHash && $cache['source']['hash'] == $sourceHash) {
					// target file is up-to-date 
					$compileNow = false;
				} else {
					// target file changed somewhere else, needs to be re-compiled
					$this->wire('cache')->deleteFor($this, $cacheName);	
				}
				if(!$compileNow && isset($cache['source']['ns'])) {
					$this->ns = $cache['source']['ns'];
				}
			}
		}
		
		if($compileNow) {
			$sourcePath = dirname($sourcePathname);
			$targetPath = dirname($targetPathname);
			$targetData = file_get_contents($sourcePathname);
			if(stripos($targetData, 'FileCompiler=0')) return $sourcePathname; // bypass if it contains this string
			if(strpos($targetData, 'namespace') !== false) $this->ns = $this->wire('files')->getNamespace($targetData, true);
			if(!$this->ns) $this->ns = "\\";
			if(!__NAMESPACE__ && !$this->options['modules'] && $this->ns === "\\") return $sourcePathname;
			set_time_limit(120);
			$this->copyAllNewerFiles($sourcePath, $targetPath); 
			$targetDirname = dirname($targetPathname) . '/';
			if(!is_dir($targetDirname)) $this->mkdir($targetDirname);
			$targetData = $this->compileData($targetData, $sourcePathname);
			if(false !== file_put_contents($targetPathname, $targetData, LOCK_EX)) {
				$this->chmod($targetPathname); 
				$this->touch($targetPathname, filemtime($sourcePathname));
				$targetHash = md5_file($targetPathname);
				$cacheData = array(
					'source' => array(
						'file' => $sourcePathname,
						'hash' => $sourceHash,
						'size' => filesize($sourcePathname), 
						'time' => filemtime($sourcePathname), 
						'ns' => $this->ns, 
					),
					'target' => array(
						'file' => $targetPathname,
						'hash' => $targetHash, 
						'size' => filesize($targetPathname),
						'time' => filemtime($targetPathname),
					)
				);
				$this->wire('cache')->saveFor($this, $cacheName, $cacheData, WireCache::expireNever);
			}
		}
	
		// if source and target are identical, use the source file
		if($targetHash && $sourceHash === $targetHash) {
			return $sourcePathname;
		}
	
		// show notices about compiled files, when applicable
		if($compileNow) {
			$message = $this->_('Compiled file:') . ' ' . str_replace($this->wire('config')->paths->root, '/', $sourcePathname);
			if($this->globalOptions['showNotices']) {
				$u = $this->wire('user');
				if($u && $u->isSuperuser()) $this->message($message);
			}
			if($this->globalOptions['logNotices']) {
				$this->log($message);
			}
		}

		// if source file declares a namespace and skipIfNamespace option in use, use source file
		if($this->options['skipIfNamespace'] && $this->ns && $this->ns != "\\") return $sourcePathname;
		
		return $targetPathname;
	}
	
	/**
	 * Compile the given string of data
	 * 
	 * @param string $data
	 * @param string $sourceFile
	 * @return string
	 * 
	 */
	protected function ___compileData($data, $sourceFile) {
		
		if($this->options['skipIfNamespace'] && $this->ns && $this->ns !== "\\") {
			// file already declares a namespace and options indicate we shouldn't compile
			return $data;
		}

		$this->initRawPHP($data);
			
		if($this->options['includes']) {
			$dataHash = md5($data);
			$this->compileIncludes($data, $sourceFile);
			if(md5($data) != $dataHash) $this->initRawPHP($data);
		}
		
		if($this->options['namespace']) {
			if(__NAMESPACE__) {
				if($this->ns && $this->ns !== "\\") {
					// namespace already present, no need for namespace compilation
				} else {
					$this->compileNamespace($data);
				}
			} else {
				if($this->ns && $this->ns !== "\\") {
					// namespace present in file
					$this->compileNamespace($data);
				}
			}
		}

		if($this->options['modules']) {
			// FileCompiler modules
			$compilers = array();
			foreach($this->wire('modules')->findByPrefix('FileCompiler', true) as $module) {
				if(!$module instanceof FileCompilerModule) continue;
				$runOrder = (int) $module->get('runOrder');
				while(isset($compilers[$runOrder])) $runOrder++;
				$compilers[$runOrder] = $module;
			}
			if(count($compilers)) {
				ksort($compilers);
				foreach($compilers as $module) {
					/** @var FileCompilerModule $module */
					$module->setSourceFile($sourceFile);
					$data = $module->compile($data);
				}
			}
		}
	
		if(!strlen(__NAMESPACE__)) {
			if(strpos($this->rawPHP, "ProcessWire\\")) {
				$data = str_replace(array("\\ProcessWire\\", "ProcessWire\\"), "\\", $data);
			}
		}
		
		if(stripos($data, "FileCompiler=?") !== false) {
			// Allow for a token that gets replaced so a file can detect if it's compiled
			$data = str_replace("FileCompiler=?", "FileCompiler=Yes", $data);
		}
		
		return $data;
	}

	/**
	 * Compile comments so that they can be easily identified by other compiler methods
	 * 
	 * @todo this is a work in progress, not yet in use
	 * 
	 * @param $data
	 * 
	 */
	protected function compileComments(&$data) {
		
		$inComment = false;
		$inPHP = false;
		$lines = explode("\n", $data);
		$numChanges = 0;
		$commentIdentifier = '!PWFC!';
		
		foreach($lines as $key => $line) {
	
			$_line = $line; // original
			$phpOpen = strrpos($line, '<' . '?');
			$phpClose = strrpos($line, '?' . '>');
			
			if($inPHP) {
				if($phpClose !== false && ($phpClose === 0 || $phpClose > (int) $phpOpen)) {
					$inPHP = false;
				}
			} else {
				if($phpOpen !== false && ($phpClose === false || $phpClose < $phpOpen)) {
					$inPHP = true;
				}
			}
			
			if(!$inPHP) continue;
			
			$commentOpen = strpos($line, '/' . '*');
			$commentClose = strpos($line, '*' . '/');
			
			if($inComment) {
				if($commentClose !== false && ($commentOpen === false || $commentOpen < $commentClose)) {
					$inComment = false;
				}
				$line = $commentIdentifier . $line;
			} 

			if($commentOpen !== false) {
				// has an open comment
				if($commentClose !== false) {
					// has a close comment, skip this line
					continue; 
				} else {
					$inComment = true;
				}
			}
			
			if($line !== $_line) {
				$lines[$key] = $line;	
				$numChanges++;
			}
		}
		
		if($numChanges) {
			$data = implode("\n", $lines);
		}
	}

	/**
	 * Compile include(), require() (and variations) to refer to compiled files where possible
	 * 
	 * @param string $data
	 * @param string $sourceFile
	 * 
	 */
	protected function compileIncludes(&$data, $sourceFile) {
		
		// other related to includes
		$rawPHP = $this->rawPHP;
		if(strpos($rawPHP, '__DIR__') !== false) {
			$data = str_replace('__DIR__', "'" . dirname($sourceFile) . "'", $data);
			$rawPHP = str_replace('__DIR__', "'" . dirname($sourceFile) . "'", $rawPHP);
		}
		if(strpos($rawPHP, '__FILE__') !== false) {
			$data = str_replace('__FILE__', "'" . $sourceFile . "'", $data);
			$rawPHP = str_replace('__FILE__', "'" . $sourceFile . "'", $rawPHP);
		}
		
		$optionsStr = $this->optionsToString($this->options);
		
		$funcs = array(
			'include_once',
			'include', 
			'require_once',
			'require',
			'wireIncludeFile',
			'wireRenderFile',
			'TemplateFile',
		);

		// main include regex
		$re = '/^' . 
			'(.*?)' . // 1: open
			'(' . implode('|', $funcs) . ')' . // 2:function
			'([\( ]+)' . // 3: argOpen: open parenthesis and/or space
			'(["\']?[^;\r\n]+)' . // 4:filename, and rest of the statement (file may be quoted or end with closing parens)
			'([;\r\n])' . // 5:close, whatever the last character is on the line
			'/im';
		
		if(!preg_match_all($re, $rawPHP, $matches)) return;
	
		foreach($matches[0] as $key => $fullMatch) {
	
			// if the include statement looks like one of these below then skip compilation for included file
			// include(/*NoCompile*/__DIR__ . '/file.php');
			// include(__DIR__ . '/file.php'/*NoCompile*/); 
			if(strpos($fullMatch, 'NoCompile') !== false) continue;
			
			$open = $matches[1][$key];
			$funcMatch = $matches[2][$key];
			$argOpen = trim($matches[3][$key]);
			$fileMatch = $matches[4][$key];
			$close = $matches[5][$key];
			$argsMatch = '';
			
			if(!$argOpen && strpos($funcMatch, 'include') !== 0 && strpos($funcMatch, 'require') !== 0) {
				// only include, include_once, require, require_once can be used without opening parenthesis
				continue; 
			}
		
			$fileMatchType = $this->compileIncludesFileMatchType($fileMatch, $funcMatch);
			if(!$fileMatchType) continue;
			if(!$this->compileIncludesValidLineOpen($open)) continue;

			if(strpos($fileMatch, '?' . '>')) {
				// move closing PHP tag out of the fileMatch and into the close
				list($fileMatch, $fileMatchExtra) = explode('?' . '>', $fileMatch);
				$close = '?' . '>' . $fileMatchExtra . $close;
				$fileMatch = trim($fileMatch);
			}
			if(substr($fileMatch, -1) == ')') {
				// move the closing parenthesis out of fileMatch and into close
				$fileMatch = substr($fileMatch, 0, -1);
				$close = ")$close";
			} 
			
			if(empty($fileMatch)) continue;
			
			if(empty($argOpen)) {
				// if there was no opening "(", compiler will be adding one, so we'll need an additional corresponding ")"
				$close = ")$close";
			}
			
			$commaPos = strpos($fileMatch, ',');
			if($commaPos) {
				// fileMatch contains additional function arguments
				$argsMatch = substr($fileMatch, $commaPos);
				$fileMatch = substr($fileMatch, 0, $commaPos);
			}
		
			if(strpos($fileMatch, '"') === 0 || strpos($fileMatch, "'") === 0) {
				// fileMatch is quoted string
				if(strpos($fileMatch, './') === 1) {
					// relative to current dir, convert to absolute
					$fileMatch = $fileMatch[0] . dirname($sourceFile) . substr($fileMatch, 2);
				} else if(strpos($fileMatch, '/') === false
					&& strpos($fileMatch, '$') === false
					&& strpos($fileMatch, '(') === false
					&& strpos($fileMatch, '\\') === false) {
					// i.e. include("file.php")
					$fileMatch = $fileMatch[0] . dirname($sourceFile) . '/' . substr($fileMatch, 1);
				}
			}
			
			$fileMatch = str_replace("\t", '', $fileMatch);
			if(strlen($open)) $open .= ' ';
			$ns = __NAMESPACE__ ? "\\ProcessWire" : "";
			$open = rtrim($open) . ' ';
			$newFullMatch = "$open$funcMatch($ns\\wire('files')->compile($fileMatch,$optionsStr)$argsMatch$close";
			$data = str_replace($fullMatch, $newFullMatch, $data);
		}
		
		// replace absolute root path references with runtime generated versions
		$rootPath = $this->wire('config')->paths->root; 
		if(strpos($data, $rootPath)) {
			$ns = __NAMESPACE__ ? "\\ProcessWire" : "";
			$data = preg_replace('%([\'"])' . preg_quote($rootPath) . '([^\'"\s\r\n]*[\'"])%',
				$ns . '\\wire("config")->paths->root . $1$2',
				$data);
		}

	}

	/**
	 * Test the given line $open preceding an include statement for validity
	 * 
	 * @param string $open
	 * @return bool Returns true if valid, false if not
	 * 
	 */
	protected function compileIncludesValidLineOpen($open) {
		if(!strlen($open)) return true;
		$skipMatch = false;
		$test = $open;
		foreach(array('"', "'") as $quote) {
			// skip when words like "require" are in a string
			if(strpos($test, $quote) === false) continue;
			$test = str_replace('\\' . $quote, '', $test); // ignore quotes that are escaped
			if(strpos($test, $quote) === false) continue;
			if(substr_count($test, $quote) % 2 > 0) {
				// there are an uneven number of quotes, indicating that
				// our $funcMatch is likely part of a quoted string
				$skipMatch = true;
				break;
			}
			if($quote == '"' && strpos($test, "'") !== false) {
				// remove quoted apostrophes so they don't confuse the next iteration
				$test = preg_replace('/"[^"\']*\'[^"]*"/', '', $test);
			}
		}
		if(!$skipMatch && preg_match('/^[$_a-zA-Z0-9]+$/', substr($open, -1))) {
			// skip things like: something_include(... and $include
			$skipMatch = true;
		}
		return $skipMatch ? false : true;
	}

	/**
	 * Returns fileMatch type of 'var', 'file', 'func' or boolean false if not valid
	 * 
	 * @param string $fileMatch The $fileMatch var from compileIncludes() method
	 * @param string $funcMatch include function name
	 * @return string|bool 
	 * 
	 */
	protected function compileIncludesFileMatchType($fileMatch, $funcMatch) {

		$fileMatch = trim($fileMatch);
		$isValid = false;

		$phpVarSign = strpos($fileMatch, '$');
		$doubleQuote1 = strpos($fileMatch, '"');
		$doubleQuote2 = strrpos($fileMatch, '"');
		$singleQuote1 = strpos($fileMatch, "'");
		$singleQuote2 = strrpos($fileMatch, "'");
		$parenthesis1 = strpos($fileMatch, '(');
		$parenthesis2 = strrpos($fileMatch, ')');
		$testFile = '';

		if($phpVarSign === 0) {
			// fileMatch starts with a var name, make sure it at least starts in PHP var format
			if(preg_match('/^\$[_a-zA-Z]/', $fileMatch)) $isValid = 'var';
			
		} else if($doubleQuote1 !== false && $doubleQuote2 > $doubleQuote1) {
			// fileMatch has both open and close double quotes with possibly a filename, so validate extension
			$testFile = substr($fileMatch, $doubleQuote1 + 1, $doubleQuote2 - $doubleQuote1 - 1);

		} else if($singleQuote1 !== false && $singleQuote2 > $singleQuote1) {
			// fileMatch has both open and close single quotes with possibly a filename, so validate extension
			$testFile = substr($fileMatch, $singleQuote1 + 1, $singleQuote2 - $singleQuote1 - 1);

		} else if($parenthesis1 > 0 && $parenthesis2 > $parenthesis1) {
			// likely a function call, make sure open parenthesis is preceded by PHP name format
			if(preg_match('/[_a-zA-Z][_a-zA-Z0-9]+\(/', $fileMatch)) $isValid = 'func';

		} else {
			// likely NOT a valid file match, as it doesn't have any of the expected characters
			$isValid = false;
		}

		if($testFile) {
			if(strrpos($testFile, '.')) {
				// test contains a filename that needs extension validated
				$parts = explode('.', $testFile);
				$testExt = array_pop($parts);
				if($testExt && in_array(strtolower($testExt), $this->extensions)) $isValid = 'file';
			} else if($funcMatch == 'wireRenderFile' || $funcMatch == 'wireIncludeFile') {
				// these methods don't require a file extension
				$isValid = 'file';
			}
		}
		
		return $isValid;
	}

	/**
	 * Compile global class/interface/function references to namespaced versions
	 * 
	 * @param string $data
	 * @return bool Whether or not namespace changes were compiled
	 * 
	 */
	protected function compileNamespace(&$data) {

		/*
		$pos = strpos($data, 'namespace');
		if($pos !== false) { 
			if(preg_match('/(^.*)\s+namespace\s+[_a-zA-Z0-9\\\\]+\s*;/m', $data, $matches)) {
				if(strpos($matches[1], '//') === false && strpos($matches[1], '/*') === false) {
					// namespace already present, no need for namespace compilation
					return false;
				}
			}
		}
		*/
		$classes = get_declared_classes();
		$classes = array_merge($classes, get_declared_interfaces());
	
		// also add in all core classes, in case the have not yet been autoloaded
		static $files = null;
		if(is_null($files)) {
			$files = array();
			foreach(new \DirectoryIterator($this->wire('config')->paths->core) as $file) {
				if($file->isDot() || $file->isDir()) continue;
				$basename = $file->getBasename('.php');
				if(strtoupper($basename[0]) == $basename[0]) {
					$name = __NAMESPACE__ ? __NAMESPACE__ . "\\$basename" : $basename;	
					if(!in_array($name, $classes)) $files[] = $name;
				}
			}
		}
		
		// also add in all modules
		foreach($this->wire('modules') as $module) {
			$name = __NAMESPACE__ ? $module->className(true) : $module->className();
			if(!in_array($name, $classes)) $classes[] = $name;
		}
		$classes = array_merge($classes, $files);
		if(!__NAMESPACE__) $classes = array_merge($classes, array_keys($this->wire('modules')->getInstallable()));
		
		$rawPHP = $this->rawPHP;
		$rawDequotedPHP = $this->rawDequotedPHP;
		
		// update classes and interfaces
		foreach($classes as $class) {
			
			if(__NAMESPACE__ && strpos($class, __NAMESPACE__ . '\\') !== 0) continue; // limit only to ProcessWire classes/interfaces
			/** @noinspection PhpUnusedLocalVariableInspection */
			if(strpos($class, '\\') !== false) {
				list($ns, $class) = explode('\\', $class, 2); // reduce to just class without namespace
			} else {
				$ns = '';
			}
			if($ns) {}
			if(stripos($rawDequotedPHP, $class) === false) continue; // quick exit if class name not referenced in data
			
			$patterns = array(
				// 1=open 2=close
				// all patterns match within 1 line only
				"new" => '(new\s+)' . $class . '\s*(\(|;|\))',  // 'new Page(' or 'new Page;' or 'new Page)'
				"function" => '(function\s+[_a-zA-Z0-9]+\s*\([^\\\\)]*?)\b' . $class . '(\s+\$[_a-zA-Z0-9]+)', // 'function(Page $page' or 'function($a, Page $page'
				"::" => '(^|[^_\\\\a-zA-Z0-9"\'])' . $class . '(::)', // constant ' Page::foo' or '(Page::foo' or '=Page::foo' or bitwise open
				"extends" => '(\sextends\s+)' . $class . '(\s|\{|$)', // 'extends Page'
				"implements" => '(\simplements[^{]*?[\s,]+)' . $class . '([^_a-zA-Z0-9]|$)', // 'implements Module' or 'implements Foo, Module'
				"instanceof" => '(\sinstanceof\s+)' . $class . '([^_a-zA-Z0-9]|$)', // 'instanceof Page'
				"$class " => '(\(\s*|,\s*)' . $class . '(\s+\$)', // type hinted '(Page $something' or '($foo, Page $something'
			);
		
			foreach($patterns as $check => $regex) {
				
				if(stripos($rawDequotedPHP, $check) === false) continue;
				if(!preg_match_all('/' . $regex . '/im', $rawDequotedPHP, $matches)) continue;
				
				foreach($matches[0] as $key => $fullMatch) {
					$open = $matches[1][$key];
					$close = $matches[2][$key];
					if(substr($open, -1) == '\\') continue; // if last character in open is '\' then skip the replacement
					$className = __NAMESPACE__ ? '\\' . __NAMESPACE__ . '\\' . $class : '\\' . $class;
					$repl = $open . $className . $close;
					$data = str_replace($fullMatch, $repl, $data);
					$rawPHP = str_replace($fullMatch, $repl, $rawPHP);
					$rawDequotedPHP = str_replace($fullMatch, $repl, $rawDequotedPHP);
				}
			}
		}
	
		// update PW procedural function calls
		$functions = get_defined_functions();
		$hasFunctionExists = strpos($rawDequotedPHP, 'function_exists') !== false; 
		
		foreach($functions['user'] as $function) {
			
			if(__NAMESPACE__) {
				if(stripos($function, __NAMESPACE__ . '\\') !== 0) continue; // limit only to ProcessWire functions
				list($ns, $function) = explode('\\', $function, 2); // reduce to just function name
				$functionName = '\\' . __NAMESPACE__ . '\\' . $function;
			} else {
				if(stripos($function, '\\') !== 0) continue;
				$functionName = '\\' . $function;
				$ns = '';
			}
			if($ns) {}
			/** @noinspection PhpUnusedLocalVariableInspection */
			if(stripos($rawDequotedPHP, $function) === false) continue; // if function name not mentioned in data, quick exit
		
			$n = 0;
			while(preg_match_all('/^(.*?[()!;,@\[=\s.])' . $function . '\s*\(/im', $rawPHP, $matches)) {
				foreach($matches[0] as $key => $fullMatch) {
					$open = $matches[1][$key];
					if(strpos($open, 'function') !== false) continue; // skip function defined with same name
					$repl = $open . $functionName . '(';
					$data = str_replace($fullMatch, $repl, $data);
					$rawPHP = str_replace($fullMatch, $repl, $rawPHP);
				}
				if(++$n > 5) break;
			}
		
			if($hasFunctionExists) {
				$find = 'function_exists\s*\(\s*["\']' . $function . '["\']\s*\)';
				$repl = "function_exists('$functionName')";
				$data = preg_replace("/$find/i", $repl, $data);
			}
		}
		
		// update other function calls
		$ns = __NAMESPACE__ ? "\\ProcessWire" : "";
		if(strpos($rawDequotedPHP, 'class_parents(') !== false) {
			$data = preg_replace('/\bclass_parents\(/', $ns . '\\wireClassParents(', $data);
		}
		if(strpos($rawDequotedPHP, 'class_implements(') !== false) {
			$data = preg_replace('/\bclass_implements\(/', $ns . '\\wireClassImplements(', $data);
		}
		
		return true; 
	}

	/**
	 * Recursively copy all files from $source to $target, but only if $source file is $newer
	 * 
	 * @param string $source
	 * @param string $target
	 * @param bool $recursive
	 * @return int Number of files copied
	 * 
	 */
	protected function copyAllNewerFiles($source, $target, $recursive = true) {
		
		$source = rtrim($source, '/') . '/';
		$target = rtrim($target, '/') . '/';
	
		// don't perform full copies of some directories
		// @todo convert this to use the user definable exclusions list
		if($source === $this->wire('config')->paths->site) return 0;
		if($source === $this->wire('config')->paths->siteModules) return 0;
		if($source === $this->wire('config')->paths->templates) return 0;
		
		if(!is_dir($target)) $this->wire('files')->mkdir($target, true);
		
		$dir = new \DirectoryIterator($source);
		$numCopied = 0;
		
		foreach($dir as $file) {
			
			if($file->isDot()) continue;
			
			$sourceFile = $file->getPathname();
			$targetFile = $target . $file->getBasename();
			
			if($file->isDir()) {
				if($recursive) {
					$numCopied += $this->copyAllNewerFiles($sourceFile, $targetFile, $recursive);
				}
				continue;
			}
			
			$ext = strtolower($file->getExtension());
			if(!in_array($ext, $this->extensions)) continue;
			
			if(is_file($targetFile)) {
				if(filemtime($targetFile) >= filemtime($sourceFile)) {
					$numCopied++;
					continue;
				}
			}
			
			copy($sourceFile, $targetFile);
			$this->chmod($targetFile);
			$this->touch($targetFile, filemtime($sourceFile));
			$numCopied++;
		}
		
		if(!$numCopied) {
			$this->wire('files')->rmdir($target, true);
		}
		
		return $numCopied;
	}

	/**
	 * Get a count of how many files are in the cache
	 * 
	 * @param bool $all Specify true to get a count for all file compiler caches
	 * @param string $targetPath for internal recursion use, public calls should omit this
	 * @return int
	 * 
	 */
	public function getNumCacheFiles($all = false, $targetPath = null) {
		
		if(!is_null($targetPath)) {
			// use it
		} else if($all) {
			$targetPath = $this->cachePath; 
		} else {
			$this->init();
			$targetPath = $this->targetPath;
		}
		
		if(!is_dir($targetPath)) return 0;
		
		$numFiles = 0;
		
		foreach(new \DirectoryIterator($targetPath) as $file) {
			if($file->isDot()) continue;
			if($file->isDir()) {
				$numFiles += $this->getNumCacheFiles($all, $file->getPathname());
			} else {
				$numFiles++;
			}
		}
	
		return $numFiles;
	}

	/**
	 * Clear all file compiler caches
	 * 
	 * @param bool $all Specify true to clear for all FileCompiler caches
	 * @return bool
	 * 
	 */
	public function clearCache($all = false) {
		if($all) {
			$targetPath = $this->cachePath; 
			$this->wire('cache')->deleteFor($this);
		} else {
			$this->init();
			$targetPath = $this->targetPath;
		}
		if(!is_dir($targetPath)) return true;
		return $this->wire('files')->rmdir($targetPath, true);
	}

	/**
	 * Run maintenance on the FileCompiler cache
	 * 
	 * This should be called at the end of each request. 
	 * 
	 * @param int $interval Number of seconds between maintenance runs (default=86400)
	 * @return bool Whether or not it was necessary to run maintenance
	 * 
	 */
	public function maintenance($interval = 86400) {
		
		$this->init();
		$this->initTargetPath();
		$lastRunFile = $this->targetPath . 'maint.last';
		if(file_exists($lastRunFile) && filemtime($lastRunFile) > time() - $interval) {
			// maintenance already run today
			return false;
		}
		$this->touch($lastRunFile);
		$this->chmod($lastRunFile);
		clearstatcache();

		return $this->_maintenance($this->sourcePath, $this->targetPath);
	}

	/**
	 * Implementation for maintenance on a given path
	 * 
	 * Logs maintenance actions to logs/file-compiler.txt
	 * 
	 * @param $sourcePath
	 * @param $targetPath
	 * @return bool
	 * 
	 */
	protected function _maintenance($sourcePath, $targetPath) {

		$sourcePath = rtrim($sourcePath, '/') . '/';
		$targetPath = rtrim($targetPath, '/') . '/';
		$sourceURL = str_replace($this->wire('config')->paths->root, '/', $sourcePath);
		$targetURL = str_replace($this->wire('config')->paths->root, '/', $targetPath);
		$useLog = $this->globalOptions['logNotices'];
		
		//$this->log("Running maintenance for $targetURL (source: $sourceURL)");
	
		if(!is_dir($targetPath)) return false;
		$dir = new \DirectoryIterator($targetPath);

		foreach($dir as $file) {

			if($file->isDot()) continue;
			$basename = $file->getBasename();
			if($basename == 'maint.last') continue; 
			$targetFile = $file->getPathname();
			$sourceFile = $sourcePath . $basename;

			if($file->isDir()) {
				if(!is_dir($sourceFile)) {
					$this->wire('files')->rmdir($targetFile, true);
					if($useLog) $this->log("Maintenance/Remove directory: $targetURL$basename");
				} else {
					$this->_maintenance($sourceFile, $targetFile);
				}
				continue;
			}

			if(!file_exists($sourceFile)) {
				// source file has been deleted
				$this->wire('files')->unlink($targetFile, true);
				if($useLog) $this->log("Maintenance/Remove target file: $targetURL$basename");
				
			} else if(filemtime($sourceFile) > filemtime($targetFile)) {
				// source file has changed
				copy($sourceFile, $targetFile);
				$this->chmod($targetFile);
				$this->touch($targetFile, filemtime($sourceFile));
				if($useLog) $this->log("Maintenance/Copy new version of source file to target file: $sourceURL$basename => $targetURL$basename");
			}
		}
	
		return true; 
	}

	/**
	 * Given an array of $options convert to an PHP-code array() string
	 * 
	 * @param array $options
	 * @return string
	 * 
	 */
	protected function optionsToString(array $options) {
		$str = "array(";
		foreach($options as $key => $value) {
			if(is_bool($value)) {
				$value = $value ? "true" : "false";
			} else if(is_string($value)) {
				$value = '"' . str_replace('"', '\\"', $value) . '"';
			} else if(is_array($value)) {
				if(count($value)) {
					$value = "array('" . implode("',", $value) . "')";
				} else {
					$value = "array()";
				}
			}
			$str .= "'$key'=>$value,";
		}
		$str = rtrim($str, ",") . ")";
		return $str;
	}
	
	/**
	 * Exclude a file or path from compilation
	 *
	 * @param string $pathname
	 *
	 */
	public function addExclusion($pathname) {
		$this->exclusions[] = $pathname;
	}

	/**
	 * Same as PHP touch() but with fallbacks for cases where touch() does not work
	 * 
	 * @param string $filename
	 * @param null|int $time
	 * @return bool
	 * 
	 */
	protected function touch($filename, $time = null) {
		if($time === null) {
			$result = @touch($filename); 
		} else {
			$result = @touch($filename, $time);
			// try again, but without time
			if(!$result) $result = @touch($filename); 
		}
		if(!$result) {
			// lastly try alternative method which should have same affect as touch without $time
			$fp = fopen($filename, 'a');
			$result = $fp !== false ? fclose($fp) : false;
		}
		return $result;
	}

}

