<?php namespace ProcessWire;

/**
 * Language Translations Porter (Exporter/Importer)
 * 
 * #pw-body = 
 * ~~~~~
 * $language = $languages->get('de');
 * $porter = $language->porter;
 * 
 * // CSV exporting
 * $csvFile = $porter->exportCsv(); // export all /wire/* translations
 * $csvFile = $porter->exportCsv([ 'source' => 'site' ]); // export all /site/* translations
 * $csvStr = $porter->exportCsv([ 'source' => 'site', 'exportTo' => 'string' ]); // return string
 * 
 * // CSV importing
 * $numChanges = $porter->importCsv($csvFile);
 * 
 * // ZIP exporting
 * $zipFile = $porter->exportZip(); // export all /wire/* translations
 * $zipFile = $porter->exportZip([ 'source' => 'site' ]); // export all /site/* translations
 * ~~~~~
 * #pw-body
 * 
 * @since 3.0.264
 * 
 */

class LanguagePorter extends Wire {

	protected $csvImportLabel = 'CSV Import:';
	protected $quiet = false; // suppress notifications when true
	protected $exportExtensions = [ 'php', 'module', 'inc' ];
	protected $siteSegment = 'site';
	
	/**
	 * Informationa bout last export
	 *
	 * @var int[] 
	 * 
	 */
	protected $lastExportInfo = [
		'total' => 0, 
		'translated' => 0, 
		'untranslated' => 0, 
		'exported' => 0, 
		// ...plus all $options
	];
	
	/**
	 * @var Language
	 * 
	 */
	protected $language;
	
	/**
	 * Construct
	 * 
	 * @param Language $language
	 * 
	 */
	public function __construct(Language $language) {
		parent::__construct();
		$this->language = $language;
		$language->wire($this);
		$parts = explode('/', rtrim($this->wire()->config->paths->site, '/'));
		$this->siteSegment = array_pop($parts);
		$this->lastExportInfo = array_merge($this->lastExportInfo, $this->exportOptions([]));
	}

	/**
	 * Wired to API
	 * 
	 * #pw-internal
	 * 
	 */	
	public function wired() {
		parent::wired();
		$this->csvImportLabel = $this->_('CSV Import:') . ' ';
	}

	/**
	 * Prepare export options
	 * 
	 * @param array $options
	 * @param string $exportType 'csv' or 'zip'
	 * @return array|string[]
	 * 
	 */
	protected function exportOptions(array $options, $exportType = 'csv') {
		$defaults = [
			'source' => '', // root-relative source directory, i.e. wire, site, site/modules/
			'scope' => 'registered', // registered or all
			'exportTo' => 'file', // 'download', 'file', 'stdout', 'string' or 'view'
			'textdomain' => '', // limit to just this textdomain
			'fieldName' => 'language_files',
			'include' => 'all', // 'translated', 'untranslated' or 'all'
			'limit' => 0, // limit to this many phrases, or 0 for no limit
			'start' => 0, // offset to start at
		];
		
		$options = array_merge($defaults, $options);
	
		if($exportType === 'zip') {
			$options['textdomain'] = ''; // option not applicable to zip
		}
		
		$options['source'] = $this->normalizeExportSource($options['source'], $options['textdomain']);
		if($options['scope'] !== 'all') $options['scope'] = 'registered';
		
		if($exportType === 'zip' && !in_array($options['exportTo'], [ 'download', 'file' ])) {
			$options['exportTo'] = 'file';
		} else if(!in_array($options['exportTo'], [ 'download', 'file', 'stdout', 'string', 'view' ])) {
			$options['exportTo'] = 'file';
		}
		
		$options['fieldName'] = strpos($options['source'], 'wire/') === 0 ? 'language_files' : 'language_files_site';
		
		return $options;
	}
	
	/**
	 * Normalize export source to a root-relative directory name
	 *
	 * @param string $source
	 * @param string $textdomain
	 * @return string
	 * @author GPT 5.5/Codex
	 *
	 */
	protected function normalizeExportSource($source, $textdomain = '') {
		$config = $this->wire()->config;
		$siteSegment = $this->siteSegment;
		$source = trim(str_replace('\\', '/', (string) $source));
		if($source === '') {
			$source = $textdomain === '' || strpos($textdomain, 'wire--') === 0 ? 'wire' : $siteSegment;
		}
		$root = str_replace('\\', '/', $config->paths->root);
		if(strpos($source, $root) === 0) $source = substr($source, strlen($root));
		if(strpos($source, str_replace('\\', '/', $config->paths->site)) === 0) {
			$source = "$siteSegment/" . substr($source, strlen(str_replace('\\', '/', $config->paths->site)));
		}
		if(strpos($source, str_replace('\\', '/', $config->paths->wire)) === 0) {
			$source = 'wire/' . substr($source, strlen(str_replace('\\', '/', $config->paths->wire)));
		}
		$source = ltrim($source, '/');
		$parts = [];
		foreach(explode('/', $source) as $part) {
			if($part === '' || $part === '.') continue;
			if($part === '..') return 'wire/';
			$parts[] = $part;
		}
		$source = implode('/', $parts);
		if($source === 'site' || strpos($source, 'site/') === 0) {
			$source = $siteSegment . substr($source, 4);
		}
		if($source !== 'wire' && $source !== $siteSegment && strpos($source, 'wire/') !== 0 && strpos($source, "$siteSegment/") !== 0) {
			$source = 'wire';
		}
		if(substr($source, -1) !== '/') $source .= '/';
		return $source;
	}

	/**
	 * Does given root-relative file live within export source?
	 *
	 * @param string $file
	 * @param string $source
	 * @return bool
	 * @author GPT 5.5/Codex
	 *
	 */
	protected function fileInExportSource($file, $source) {
		$file = ltrim(str_replace('\\', '/', (string) $file), '/');
		return strpos($file, $source) === 0;
	}

	/**
	 * Get source name safe for an export filename
	 *
	 * @param string $source
	 * @return string
	 * @author GPT 5.5/Codex
	 *
	 */
	protected function exportSourceName($source) {
		return trim(str_replace('/', '-', trim($source, '/')), '-');
	}

	/**
	 * Get export files
	 *
	 * @param string $fieldName
	 * @param string $textdomain
	 * @param string $source
	 * @return array
	 *
	 */
	protected function getExportFiles($fieldName, $textdomain = '', $source = '') {
		$exportFiles = array();
		if($textdomain) {
			$file = $this->language->translator->textdomainToFilename($textdomain);
			if($file) {
				$data = $this->language->translator->getTextdomain($textdomain);
				if(!$source || empty($data['file']) || $this->fileInExportSource($data['file'], $source)) {
					$exportFiles[] = $file;
				}
			}
		}
		if(!count($exportFiles)) {
			foreach($this->language->$fieldName as $file) {
				if($source) {
					$textdomain = basename($file->filename, '.json');
					$data = $this->language->translator->getTextdomain($textdomain);
					if(empty($data['file']) || !$this->fileInExportSource($data['file'], $source)) continue;
				}
				$exportFiles[] = $file->filename;
			}
		}
		return $exportFiles;
	}

	/**
	 * Find all translatable source files under given source directory
	 *
	 * @param string $source Root-relative directory like "wire/" or "site/modules/"
	 * @return array
	 * @throws WireException
	 * @author GPT 5.5/Codex
	 *
	 */
	protected function findTranslatableFiles($source) {
		$config = $this->wire()->config;
		$path = $config->paths->root . $source;
		if(!is_dir($path)) throw new WireException(sprintf($this->_('%s does not exist or is not a directory'), $source));
		$files = $this->findTranslatableFilesPath($path, 0);
		ksort($files);
		return $files;
	}

	/**
	 * Recursive helper for findTranslatableFiles
	 *
	 * @param string $path
	 * @param int $level
	 * @return array
	 * @author GPT 5.5/Codex
	 *
	 */
	protected function findTranslatableFilesPath($path, $level) {
		$files = [];
		$dirs = [];
		$root = str_replace('\\', '/', $this->wire()->config->paths->root);
		if($level > 20) return $files;
		try {
			$dirIterator = new \DirectoryIterator($path);
		} catch(\Exception $e) {
			$this->warning($e->getMessage());
			return $files;
		}
		foreach($dirIterator as $file) {
			if($file->isDot()) continue;
			$basename = $file->getBasename();
			$c = substr($basename, 0, 1);
			if($c === '.' || $c === '-' || $c === '\\') continue;
			$pathname = str_replace('\\', '/', $file->getPathname());
			if($file->isDir()) {
				if(strpos($pathname, "/$this->siteSegment/assets/") !== false) continue;
				$dirs[] = $pathname;
				continue;
			}
			$ext = strtolower($file->getExtension());
			if(!in_array($ext, $this->exportExtensions)) continue;
			$text = file_get_contents($pathname);
			if($text === false || !$this->fileHasTranslatableText($text, $pathname)) continue;
			$relative = ltrim(str_replace($root, '', $pathname), '/');
			$files[$relative] = $relative;
		}
		foreach($dirs as $dir) {
			$files = array_merge($files, $this->findTranslatableFilesPath($dir, $level + 1));
		}
		return $files;
	}

	/**
	 * Does file text contain translation calls?
	 *
	 * @param string $text
	 * @param string $pathname
	 * @return bool
	 * @author GPT 5.5/Codex
	 *
	 */
	protected function fileHasTranslatableText($text, $pathname) {
		if($pathname === __FILE__ || strpos($text, '__(file-not-translatable)') !== false) return false;
		foreach([ '$this->_(', '$this->_n(', '$this->_x(' ] as $find) {
			if(strpos($text, $find) !== false) return true;
		}
		foreach([ '__(', '_n(', '_x(' ] as $find) {
			$pos = strpos($text, $find);
			if($pos === false) continue;
			$c = $pos > 0 ? substr($text, $pos - 1, 1) : '';
			if(!ctype_alnum($c) && $c != '_') return true;
		}
		return false;
	}
	
	/**
	 * Export translations to ZIP file
	 *
	 * ZIP export packages existing JSON translation files that are already attached to the
	 * language page. It does not scan source files or discover untranslated phrases like
	 * exportCsv([ 'scope' => 'all' ]) does. Use ZIP for distributing existing language
	 * packs, and CSV scope=all for creating a complete translation worksheet from source.
	 * 
	 * @param array $options
	 * - `source` (string): Root-relative source directory, i.e. 'wire', 'site', 'site/modules/' (default='wire')
	 * - `exportTo` (string): Export to 'download' or 'file'? (default='file')
	 * @return string Return value depends on `exportTo` option:
	 * - When `file` it returns full path/filename to ZIP file on success, blank string on fail.
	 * - When `download` this does not return (exit implied). 
	 * 
	 */
	public function exportZip(array $options = []) {
		$language = $this->language;
		$options = $this->exportOptions($options, 'zip');
		$fieldName = $options['fieldName'];
		$source = $options['source'];
		$exportPath = $language->$fieldName->path();
		$zipname = $language->name . '-' . $this->exportSourceName($source);
		$zipfile = "$exportPath$zipname.zip";
		$exportFiles = $this->getExportFiles($fieldName, '', $source);
		$info = wireZipFile($zipfile, $exportFiles, array("overwrite" => true));
		if(!count($info['files'])) {
			$this->error("Error adding files to ZIP");
			return '';
		} else if($options['exportTo'] === 'download') {
			wireSendFile($zipfile);
			exit(0);
		}
		return $zipfile;
	}
	
	/**
	 * Export translations CSV as a string and return it
	 * 
	 * @param array $options
	 * - `source` (string): Root-relative source directory, i.e. 'wire', 'site', 'site/modules/'
	 * - `textdomain` (string): Limit export to this textdomain (default='')
	 * - `include` (string): Include 'translated', 'untranslated' or 'all' (default='all')
	 * - `limit` (int): Limit to this many phrases, or 0 for no limit (default=0)
	 * - `start` (int): Offset to start at, typically combined with limit (default=0)
	 * @return string
	 * @throws WireException
	 * 
	 */
	public function exportCsvStr(array $options = []) {
		$options['exportTo'] = 'stdout';
		ob_start();
		try {
			$this->exportCsv($options);
			$this->lastExportInfo['exportTo'] = 'string';
			return (string) ob_get_clean();
		} catch(\Throwable $e) {
			ob_end_clean();
			throw $e;
		}
	}
	
	/**
	 * Export translations CSV
	 * 
	 * @param array $options
	 * - `source` (string): Root-relative source directory, i.e. 'wire', 'site', 'site/modules/'
	 * - `scope` (string): Export 'registered' translation files, or discover and export 'all' translatable files? (default='registered')
	 * - `exportTo` (string): Export to 'download', 'file', 'stdout', 'string', or 'view'? (default='file')
	 *    When `stdout` no http headers are sent, CSV is output directly, and method returns. 
	 *    When `view` http headers ARE sent, CSV is output directly, and method does an exit(0). 
	 *    When `string` no output is echo'd and the CSV is returned as a string.
	 * - `textdomain` (string): Limit export to this textdomain (default='')
	 * - `include` (string): Include 'translated', 'untranslated' or 'all' (default='all')
	 * - `limit` (int): Limit to this many phrases, or 0 for no limit (default=0)
	 * - `start` (int): Offset to start at, typically combined with limit (default=0)
	 * @return string|bool Return value depends on `exportTo` option:
	 * - When `file` it returns full path/filename to CSV file
	 * - When `string` this method returns a string of the CSV output.
	 * - When `download` or `view` this method does not return (exit implied).
	 * - When `stdout` this method returns boolean true.
	 * @throws WireException Throws exception on all errors
	 * 
	 */
	public function exportCsv(array $options = []) {
		
		$config = $this->wire()->config;
		$options = $this->exportOptions($options, 'csv');
		if($options['exportTo'] === 'string') return $this->exportCsvStr($options);
		
		$language = $this->language;
		$textdomain = $options['textdomain'];
		$exportTo = $options['exportTo'];
		$source = $options['source'];
		$scope = $options['scope'];
		$fieldName = $options['fieldName'];
		$textdomains = array();
		$exportPath = $language->$fieldName->path();
		$exportFiles = []; 
		$totalPhrases = 0;
		$numExported = 0;
		$numTranslated = 0;
		$numUntranslated = 0;
		
		if($textdomain) {
			$file = $language->translator->textdomainToFilename($textdomain);
			if($file) {
				$textdomains[$file] = $textdomain;
				$exportFiles[] = $file;	
			} else {
				$textdomain = '';
			}
		}
		
		if(!count($exportFiles)) {
			if($scope === 'all' && !$textdomain) {
				$exportFiles = $this->findTranslatableFiles($source);
			} else {
				$exportFiles = $this->getExportFiles($fieldName, $textdomain, $source);
			}
			if(!count($exportFiles)) {
				throw new WireException('No translation files specified to export');
			}
		}
		
		if($textdomain) {
			// i.e. es-modulename.csv
			$parts = explode('--', $textdomain);
			$basename = array_pop($parts);
			$parts = explode('-', $basename);
			$basename = array_shift($parts);
			$filename = "$language->name-$basename.csv";
		} else {
			// i.e. es-site.csv or es-wire.csv
			$filename = $language->name . '-' . $this->exportSourceName($source) . '.csv';
		}
		
		$exportFile = $exportTo === 'file' ? $exportPath . $filename : 'php://output';
		$fp = fopen($exportFile, 'w');
		if($fp === false) throw new WireException("Unable to open $exportFile for writing");
		
		if(!$config->cli) {
			if($exportTo === 'view') {
				header("Content-type: text/plain");
			} else if($exportTo === 'download') {
				header("Content-type: application/force-download");
				header("Content-Transfer-Encoding: Binary");
				header("Content-disposition: attachment; filename=$filename");
			}
		}
		
		$defaultCol = $language->name == 'en' ? 'default' : 'en';
		$columns = array($defaultCol, $language->name, 'description', 'file', 'hash');
		fputcsv($fp, $columns);
		
		require_once(__DIR__ . '/LanguageParser.php');
		$numMatched = 0;
		
		foreach($exportFiles as $f) {
			if($scope === 'all') {
				$textdomain = $language->translator->filenameToTextdomain($f);
			} else {
				$textdomain = $textdomains[$f] ?? basename($f, '.json');
			}
			$data = $language->translator->getTextdomain($textdomain);
			if(empty($data) && $scope !== 'all') continue;
			
			$file = empty($data['file']) ? $f : $data['file'];
			if(!$this->fileInExportSource($file, $source)) continue;
			$pathname = $config->paths->root . $file;
			$translated = empty($data['translations']) ? [] : $data['translations'];
			/** @var LanguageParser $parser */
			$parser = $this->wire(new LanguageParser($language->translator, $pathname)); 
			$untranslated = $parser->getUntranslated(); // all found phrases (in untranslated form), indexed by hash
			$comments = $parser->getComments();
			
			foreach($untranslated as $hash => $textUntranslated) {
				$totalPhrases++;
				$textTranslated = isset($translated[$hash])  ? $translated[$hash]['text'] : '';
				$isTranslated = strlen($textTranslated) > 0;
				if($isTranslated) $numTranslated++; else $numUntranslated++;
				if($options['include'] === 'translated' && !$isTranslated) continue;
				if($options['include'] === 'untranslated' && $isTranslated) continue;
				if($numMatched++ < $options['start']) continue;
				if($options['limit'] && $numExported >= $options['limit']) continue;
				$comment = $comments[$hash] ?? '';
				if(strpos($comment, '//') !== false) list(, $comment) = explode('//', $comment);
				$columns = array($textUntranslated, $textTranslated, trim($comment), $file, $hash);
				fputcsv($fp, $columns);
				$numExported++;
			}
		}
		
		fclose($fp);
		
		$this->lastExportInfo = array_merge([
			'total' => $totalPhrases,
			'translated' => $numTranslated,
			'untranslated' => $numUntranslated,
			'exported' => $numExported,
		], $options);
		
		if($exportTo === 'view' || $exportTo === 'download') exit(0);
		if($exportTo === 'stdout') return true;
		
		return $exportFile;
	}
	
	/**
	 * Get array of information about last successful CSV export
	 * 
	 * @return array
	 *
	 */
	public function getLastExportInfo() {
		return $this->lastExportInfo;
	}
	
	/**
	 * Import and save changes from a translations CSV string
	 *
	 * @param string $csvStr
	 * @param array $options Additional options
	 * - `file` (string): Import for this path/file (relative to install root) rather than one in the CSV row.
	 * - `quiet` (bool): Suppress error and message notifications? (default=false)
	 * @return bool|int Returns false on error or integer on success, where value is number of translations imported
	 * @throws WireException
	 *
	 */
	public function importCsvStr($csvStr, array $options = []) {
		$files = $this->wire()->files;
		$tempDir = $files->tempDir();
		$path = $tempDir->get();
		$file = $path . 'import.csv';
		$files->filePutContents($file, $csvStr);
		try {
			$result = $this->importCsv($file, $options);
		} finally {
			$files->unlink($file);
		}
		return $result;
	}
	
	/**
	 * Import and save changes from a translations CSV file (or string)
	 *
	 * @param string $csvFile CSV file or CSV string
	 * @param array $options Additional options 
	 *  - `file` (string): Import for this path/file (relative to install root) rather than one in the CSV row.
	 *  - `quiet` (bool): Suppress error and message notifications? (default=false)
	 * @return bool|int Returns false on error or integer on success, where value is number of translations imported
	 * @throws WireException
	 *
	 */
	public function importCsv($csvFile, array $options = array()) {

		if(strpos($csvFile, ',') !== false && strpos($csvFile, "\n") !== false) {
			// this is a CSV string rather than a CSV file
			return $this->importCsvStr($csvFile, $options);
		}

		$defaults = array(
			'file' => '',
			'quiet' => false,
		);
		
		$options = array_merge($defaults, $options);
		$language = $this->language;
		$this->quiet = $options['quiet'];
		
		$fp = fopen($csvFile, "r");
		
		if($fp === false) {
			if(!$this->quiet) {
				$this->error($this->csvImportLabel . "Unable to open: $csvFile");
			}
			return false;
		}
		
		$keys = array(
			'original',
			'translated',
			'file',
			'hash',
		);
		
		$n = 0;
		$header = array();
		$translator = new LanguageTranslator($language);
		$textdomain = '';
		$lastTextdomain = '';
		$lastFile = '';
		$numChanges = 0;
		$numTotal = 0;
		$numGross = 0;
		$translations = null;
		$optionsFileBasename = '';
		$halt = false;
		
		$this->wire($translator);
		
		if(!empty($options['file'])) {
			$options['file'] = ltrim($this->wire()->files->unixFileName($options['file']), '/');
			$optionsFileBasename = basename($options['file']);
		}
		
		while(($csvData = fgetcsv($fp, 8192, ",")) !== FALSE) {
			
			if(++$n === 1) {
				// header row
				$header = $csvData;
				foreach($header as $key => $value) {
					$header[$key] = strtolower($value);
				}
				// make sure everything we need is present
				foreach($keys as $k => $key) {
					if($k > 1 && !in_array($key, $header)) {
						if($key === 'file' && !empty($options['file'])) {
							// default file provided so not required in CSV data
						} else {
							if(!$this->quiet) {
								$this->error($this->csvImportLabel . "CSV data missing required column '$key'");
							}
							$halt = true;
						}
					}
				}
				if($halt) break;
				continue;
			}
			
			$row = array();
			foreach($header as $key => $name) {
				if($key === 0) $name = 'original';
				if($key === 1) $name = 'translated';
				$row[$name] = $csvData[$key];
			}
			
			if($options['file']) {
				if(empty($row['file'])) {
					$row['file'] = $options['file'];
				} else {
					$rowFileBasename = basename($row['file']);
					if($rowFileBasename === $optionsFileBasename) {
						// i.e. site/modules/Hello/Hello.module
						$row['file'] = $options['file'];
					} else {
						// i.e. site/modules/Hello/World.module 
						$row['file'] = dirname($options['file']) . '/' . $rowFileBasename;
					}
				}
			}
			
			if(empty($row['original']) || empty($row['file'])) continue;
			
			$file = $row['file'];
			$hash = $row['hash'];
			// $textOriginal = $row['original'];
			$textTranslated = $row['translated'];
			$textdomain = $translator->filenameToTextdomain($file);
			
			if(!$translator->textdomainFileExists($textdomain)) {
				$textdomain = $translator->addFileToTranslate($file, false, false);
			}

			if(!$textdomain) {
				if(!$this->quiet) {
					$this->warning($this->csvImportLabel . sprintf(
						$this->_('Unrecognized textdomain for file: %s'),
						$this->wire()->sanitizer->entities($file)
					));
				}
				continue;
			}

			if(is_null($translations)) {
				$translations = $translator->getTranslations($textdomain);
			}
			
			if($textdomain != $lastTextdomain) {
				if(!$lastFile) $lastFile = $file;
				if(!$lastTextdomain) $lastTextdomain = $textdomain;
				$this->importCsvSaveTextdomain($translator, $lastTextdomain, $lastFile, $numChanges);
				$translations = $translator->getTranslations($textdomain);
				$numChanges = 0;
			}
			
			$translation = $translations[$hash] ?? array('text' => '');
			if($translation['text'] != $textTranslated) {
				$translator->setTranslationFromHash($textdomain, $hash, $textTranslated);
				$numChanges++;
				$numTotal++;
			}
			
			$lastTextdomain = $textdomain;
			$lastFile = $file;
			$numGross++;
		}
		
		if($numChanges) {
			$this->importCsvSaveTextdomain($translator, $textdomain, $lastFile, $numChanges);
		}
		
		$language->save();
		
		fclose($fp);
		
		if(!$this->quiet) {
			$this->message(
				$this->csvImportLabel . 
				sprintf($this->_('%d total translations, %d total changes'), $numGross, $numTotal), 
				Notice::noGroup
			);
		}
		
		return $halt ? false : $numGross;
	}
	
	/**
	 * Save a textdomain, helper for processCSV method
	 *
	 * @param LanguageTranslator $translator
	 * @param string $textdomain
	 * @param string $filename
	 * @param int $numChanges
	 *
	 */
	protected function importCsvSaveTextdomain(LanguageTranslator $translator, $textdomain, $filename, $numChanges) {
		if($filename) { /* ignore, not currently used */ }
		$file = $translator->textdomainToFilename($textdomain);
		if($numChanges) {
			try {
				$translator->saveTextdomain($textdomain);
				if(!$this->quiet) $this->message(
					$this->csvImportLabel . 
					sprintf($this->_('Saved %d change(s) for file: %s'), $numChanges, $file), 
					Notice::noGroup
				);
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		} else {
			// no changes
		}
		$translator->unloadTextdomain($textdomain);
	}
	
}
