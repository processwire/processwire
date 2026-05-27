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
			'source' => '', // wire or site
			'exportTo' => 'file', // 'download', 'file', 'stdout', 'string' or 'view'
			'textdomain' => '',
			'fieldName' => 'language_files',
		];
		
		$options = array_merge($defaults, $options);
	
		if($exportType === 'zip') {
			$options['textdomain'] = ''; // option not applicable to zip
		} else if(empty($options['source'])) {
			$options['source'] = strpos($options['textdomain'], 'site') === 0 ? 'site' : 'wire';
		}
		
		if(!in_array($options['source'], [ 'wire', 'site' ])) {
			$options['source'] = 'wire';
		}
		
		if($exportType === 'zip' && !in_array($options['exportTo'], [ 'download', 'file' ])) {
			$options['exportTo'] = 'file';
		} else if(!in_array($options['exportTo'], [ 'download', 'file', 'stdout', 'string', 'view' ])) {
			$options['exportTo'] = 'file';
		}
		
		$options['fieldName'] = ($options['source'] === 'wire' ? 'language_files' : 'language_files_site');
		
		return $options;
	}
	
	/**
	 * Get export files
	 *
	 * @param string $fieldName
	 * @param string $textdomain
	 * @return array
	 *
	 */
	protected function getExportFiles($fieldName, $textdomain = '') {
		$exportFiles = array();
		if($textdomain) {
			$file = $this->language->translator->textdomainToFilename($textdomain);
			if($file) $exportFiles[] = $file;
		}
		if(!count($exportFiles)) {
			foreach($this->language->$fieldName as $file) {
				$exportFiles[] = $file->filename;
			}
		}
		return $exportFiles;
	}
	
	/**
	 * Export translations to ZIP file
	 * 
	 * @param array $options
	 * - `source` (string): One of 'wire' for core translations, or 'site' for site translations (default='wire')
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
		$exportPath = $language->$fieldName->path();
		$zipname = "{$language->name}-$options[source]";
		$zipfile = "$exportPath$zipname.zip";
		$exportFiles = $this->getExportFiles($fieldName);
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
	 * - `source` (string): One of 'wire' (for core translations) or 'site' (for site translations)
	 * - `textdomain` (string): Limit export to this textdomain (default='')
	 * @return string
	 * @throws WireException
	 * 
	 */
	public function exportCsvStr(array $options = []) {
		$options['exportTo'] = 'stdout';
		ob_start();
		try {
			$this->exportCsv($options);
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
	 * - `source` (string): One of 'wire' (for core translations) or 'site' (for site translations)
	 * - `exportTo` (string): Export to 'download', 'file', 'stdout', 'string', or 'view'? (default='file')
	 *    When `stdout` no http headers are sent, CSV is output directly, and method returns. 
	 *    When `view` http headers ARE sent, CSV is output directly, and method does an exit(0). 
	 *    When `string` no output is echo'd and the CSV is returned as a string.
	 * - `textdomain` (string): Limit export to this textdomain (default='')
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
		$fieldName = $options['fieldName'];
		$textdomains = array();
		$exportPath = $language->$fieldName->path();
		$exportFiles = []; 
		
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
			$exportFiles = $this->getExportFiles($fieldName, $textdomain);
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
			$filename = $language->name . "-$source.csv";
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
		
		foreach($exportFiles as $f) {
		
			$textdomain = $textdomains[$f] ?? basename($f, '.json');
			$data = $language->translator->getTextdomain($textdomain);
			if(empty($data)) continue;
			
			$file = $data['file'];
			$pathname = $config->paths->root . $file;
			$translated =& $data['translations'];
			require_once(__DIR__ . '/LanguageParser.php');
			/** @var LanguageParser $parser */
			$parser = $this->wire(new LanguageParser($language->translator, $pathname)); 
			$untranslated = $parser->getUntranslated();
			$comments = $parser->getComments();
			
			foreach($untranslated as $hash => $text1) {
				$text2 = isset($translated[$hash])  ? $translated[$hash]['text'] : '';
				$comment = $comments[$hash] ?? '';
				if(strpos($comment, '//') !== false) list(, $comment) = explode('//', $comment);
				$columns = array($text1, $text2, trim($comment), $file, $hash);
				fputcsv($fp, $columns);
			}
		}
		
		fclose($fp);
		
		if($exportTo === 'view' || $exportTo === 'download') exit(0);
		if($exportTo === 'stdout') return true;
		
		return $exportFile;
	}
	
	/**
	 * Import and save changes from a translations CSV file
	 *
	 * @param string $csvFile
	 * @param array $options Additional options 
	 *  - `file` (string): Import for this path/file (relative to install root) rather than one in the CSV row.
	 *  - `quiet` (bool): Suppress error and message notifications? (default=false)
	 * @return bool|int Returns false on error or integer on success, where value is number of translations imported
	 * @throws WireException
	 *
	 */
	public function importCsv($csvFile, array $options = array()) {
		
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
			
			if(is_null($translations)) {
				$translations = $translator->getTranslations($textdomain);
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
