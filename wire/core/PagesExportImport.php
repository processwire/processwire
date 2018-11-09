<?php namespace ProcessWire;

/**
 * ProcessWire Pages Export/Import Helpers
 * 
 * This class is in development and not yet ready for use. 
 * 
 * $options argument for import methods:
 * 
 *  - `commit` (bool): Commit/save the changes now? (default=true). Specify false to perform a test import.
 *  - `update` (bool): Allow update of existing pages? (default=true)
 *  - `create` (bool): Allow creation of new pages? (default=true)
 *  - `parent` (Page|string|int): Parent Page, path or ID. Omit to use import data (default=0).
 *  - `template` (Template|string|int): Template object, name or ID. Omit to use import data (default=0).
 *  - `fieldNames` (array): Import only these field names, or omit to use all import data (default=[]).
 *  - `changeStatus` (bool): Allow status to be changed aon existing pages? (default=true)
 *  - `changeSort` (bool): Allow sort and sortfield to be changed on existing pages? (default=true)
 * 
 * Note: all the "change" prefix options require update=true. 
 * 
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesExportImport extends Wire {

	/**
	 * Get the path where ZIP exports are stored
	 * 
	 * @param string $subdir Specify a subdirectory name if you want it to create it. 
	 *   If it exists, it will create a numbered version of the subdir to ensure it is unique. 
	 * @return string
	 * 
	 */
	public function getExportPath($subdir = '') {
	
		/** @var WireFileTools $files */
		$files = $this->wire('files');
		$path = $this->wire('config')->paths->assets . 'backups/' . $this->className() . '/';
		
		$readmeText = "When this file is present, files and directories in here are auto-deleted after a short period of time.";
		$readmeFile = $this->className() . '.txt';
		$readmeFiles = array();
		
		if(!is_dir($path)) {
			$files->mkdir($path, true);
			$readmeFiles[] = $path . $readmeFile;
		}
		
		if($subdir) {
			$n = 0;
			do {
				$_path = $path . $subdir . ($n ? "-$n" : '') . '/';
			} while(++$n && is_dir($_path)); 
			$path = $_path;
			$files->mkdir($path, true);
			$readmeFiles[] = $path . $readmeFile;
		}
		
		foreach($readmeFiles as $file) {
			file_put_contents($file, $readmeText);
			$files->chmod($readmeFile); 
		}
		
		return $path; 
	}

	/**
	 * Remove files and directories in /site/assets/backups/PagesExportImport/ that are older than $maxAge
	 * 
	 * @param int $maxAge Maximum age in seconds
	 * @return int Number of files/dirs removed
	 * 
	 */
	public function cleanupFiles($maxAge = 3600) {

		/** @var WireFileTools $files */
		$files = $this->wire('files');
		$path = $this->getExportPath();
		$qty = 0;
		
		foreach(new \DirectoryIterator($path) as $file) {
			
			if($file->isDot()) continue;
			if($file->getBasename() == $this->className() . '.txt') continue; // we want this file to stay
			if($file->getMTime() >= (time() - $maxAge)) continue; // not expired
			
			$pathname = $file->getPathname();
			
			if($file->isDir()) {
				$testFile = rtrim($pathname, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->className() . '.txt';
				if(!is_file($testFile)) continue; 
				if($files->rmdir($pathname, true)) {
					$this->message($this->_('Removed old directory') . " - $pathname", Notice::debug); 
					$qty++;
				}
			} else {
				if($files->unlink($pathname, true)) {
					$this->message($this->_('Removed old file') . " - $pathname", Notice::debug); 
					$qty++;
				}
			}
		}
		
		return $qty; 
	}

	/**
	 * Export given PageArray to a ZIP file
	 * 
	 * @param PageArray $items
	 * @param array $options
	 * @return string|bool Path+filename to ZIP file or boolean false on failure
	 * 
	 */
	public function exportZIP(PageArray $items, array $options = array()) {
		
		/** @var WireFileTools $files */
		$files = $this->wire('files');
		
		$options['exportTarget'] = 'zip';
		$zipPath = $this->getExportPath();
		if(!is_dir($zipPath)) $files->mkdir($zipPath, true); 
		
		$tempDir = new WireTempDir($this);
		$this->wire($tempDir);
		$tmpPath = $tempDir->get();
		$jsonFile = $tmpPath . "pages.json";
		$zipItems = array($jsonFile);
		$data = $this->pagesToArray($items, $options);
	
		// determine other files to add to ZIP
		foreach($data['pages'] as $key => $item) {
			if(!isset($item['_filesPath'])) continue;
			$zipItems[] = $item['_filesPath'];
			unset($data['pages'][$key]['_filesPath']);
		}
	
		// write out the pages.json file
		file_put_contents($jsonFile, wireEncodeJSON($data, true, true));

		$n = 0;
		do {
			$zipName = $zipPath . 'pages' . ($n ? "-$n" : '') . '.zip';
		} while(++$n && file_exists($zipName)); 
		
		// @todo report errors from zipInfo
		$zipInfo = $files->zip($zipName, $zipItems, array(
			'maxDepth' => 1, 
			'allowHidden' => false, 
			'allowEmptyDirs' => false
		)); 
		if($zipInfo) {} // ignore
		
		$files->unlink($jsonFile, true); 
		
		return $zipName;
	}

	/**
	 * Import ZIP file to create pages
	 * 
	 * @param string $filename Path+filename to ZIP file 
	 * @param array $options
	 * @return PageArray|bool
	 * 
	 */
	public function importZIP($filename, array $options = array()) {
		
		$tempDir = new WireTempDir($this);
		$this->wire($tempDir);
		$path = $tempDir->get();
		$options['filesPath'] = $path; 
		
		$zipFileItems = $this->wire('files')->unzip($filename, $path); 
		
		if(empty($zipFileItems)) return false;
		
		$jsonFile = $path . "pages.json";
		$jsonData = file_get_contents($jsonFile);
		$data = json_decode($jsonData, true);
		if($data === false) return false;
		
		$pageArray = $this->arrayToPages($data, $options);
		
		return $pageArray;	
	}

	/**
	 * Export a PageArray to JSON string
	 * 
	 * @param PageArray $items
	 * @param array $options
	 * @return string|bool JSON string of pages or boolean false on error
	 * 
	 */
	public function exportJSON(PageArray $items, array $options = array()) {
		$defaults = array(
			'exportTarget' => 'json'
		);
		$options = array_merge($defaults, $options); 
		$data = $this->pagesToArray($items, $options); 
		$data = wireEncodeJSON($data, true, true); 
		return $data;
	}

	/**
	 * Import a PageArray from a JSON string 
	 * 
	 * Given JSON string must be one previously exported by the exportJSON() method in this class.
	 * 
	 * @param string $json
	 * @param array $options
	 * @return PageArray|bool 
	 * 
	 */
	public function importJSON($json, array $options = array()) {
		$data = json_decode($json, true); 
		if($data === false) return false;
		$pageArray = $this->arrayToPages($data, $options);
		return $pageArray;	
	}
	
	/**
	 * Given a PageArray export it to a portable PHP array
	 *
	 * @param PageArray $items
	 * @param array $options Additional options to modify behavior
	 * @return array
	 *
	 */
	public function pagesToArray(PageArray $items, array $options = array()) {
	
		/** @var Config $config */
		$config = $this->wire('config');

		$defaults = array(
			'verbose' => false,
			'fieldNames' => array(), // export only these field names, when specified
		);

		$options = array_merge($defaults, $options);
		$options['verbose'] = false; // TMP option not yet supported

		$a = array(
			'type' => 'ProcessWire:PageArray',
			'created' => date('Y-m-d H:i:s'), 
			'version' => $config->version,
			'user' => $this->wire('user')->name,
			'host' => $config->httpHost,
			'pages' => array(),
			'fields' => array(),
			'urls' => array(
				'root' => $config->urls->root,
				'assets' => $config->urls->assets
			),
			'timer' => Debug::timer(), 
			// 'pagination' => array(),
		);
		
		if($items->getLimit()) {
			$pageNum = $this->wire('input')->pageNum;
			$a['pagination'] = array(
				'start' => $items->getStart(),
				'limit' => $items->getLimit(),
				'total' => $items->getTotal(),
				'this' => $pageNum, 
				'next' => ($items->getTotal() > $items->getStart() + $items->count() ? $pageNum+1 : false), 
				'prev' => ($pageNum > 1 ? $pageNum - 1 : false)
			);
		} else {
			unset($a['pagination']);
		}

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
		$templates = array();

		foreach($items as $item) {

			$exportItem = $this->pageToArray($item, $options);
			$a['pages'][$exportItem['path']] = $exportItem;

			// include information about field settings so that warnings can be generated at
			// import time if there are applicable differences in the field settings
			foreach($exportItem['data'] as $fieldName => $value) {
				$fieldNames = array($fieldName);
				if(is_array($value) && !empty($value['type']) && $value['type'] == 'ProcessWire:PageArray') {
					// nested PageArray, pull in fields from it as well
					foreach(array_keys($value['fields']) as $fieldName) $fieldNames[] = $fieldName;
				}
				foreach($fieldNames as $fieldName) {
					if(isset($a['fields'][$fieldName])) continue;
					$field = $this->wire('fields')->get($fieldName);
					if(!$field || !$field->type) continue;
					$moduleInfo = $this->wire('modules')->getModuleInfoVerbose($field->type);
					if($options['verbose']) {
						$fieldData = $field->getExportData();
						unset($fieldData['name']);
						$a['fields'][$fieldName] = $fieldData;
					} else {
						$a['fields'][$fieldName] = array(
							'type' => $field->type->className(),
							'label' => $field->label,
							'version' => $moduleInfo['versionStr'],
							'id' => $field->id
						);
					}
					$blankValue = $field->type->getBlankValue($item, $field);
					if(is_object($blankValue)) {
						if($blankValue instanceof Wire) {
							$blankValue = "class:" . $blankValue->className();
						} else {
							$blankValue = "class:" . get_class($blankValue);
						}
					}
					$a['fields'][$fieldName]['blankValue'] = $blankValue;
					foreach($field->type->getImportValueOptions($field) as $k => $v) {
						if(isset($a['fields'][$fieldName][$k])) continue;
						$a['fields'][$fieldName][$k] = $v;
					}
				}
			}

			// include information about template settings so that warnings can be generated
			// at import time if there are applicable differences in the template settings
			if($options['verbose']) {
				if(!isset($templates[$item->template->name])) {
					$templates[$item->template->name] = $item->template->getExportData();
				}
			}
		}
	
		// sort by path to ensure parents are created before their children
		ksort($a['pages']); 
		$a['pages'] = array_values($a['pages']); 
		$a['timer'] = Debug::timer($a['timer']); 

		if($options['verbose']) $a['templates'] = $templates;

		if($languages) $languages->unsetDefault();

		return $a;
	}
	
	/**
	 * Export Page object to an array
	 *
	 * @param Page $page
	 * @param array $options
	 * @return array
	 *
	 */
	protected function pageToArray(Page $page, array $options) {
		
		$defaults = array(
			'exportTarget' => '',
		);
		$options = array_merge($defaults, $options); 
		
		$of = $page->of();
		$page->of(false);

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
		$numFiles = 0;
	
		// standard page settings
		$settings = array(
			'id' => $page->id, // for connection to exported file directories only
			'name' => $page->name,
			'status' => $page->status,
			'sort' => $page->sort,
			'sortfield' => $page->sortfield,
			'created' => $page->createdStr,
			'modified' => $page->modifiedStr,
		);

		// verbose page settings
		if(!empty($options['verbose'])) {
			$settings = array_merge($settings, array(
				'parent_id' => $page->parent_id,
				'templates_id' => $page->templates_id,
				'created_user' => $page->createdUser->name,
				'modified_user' => $page->modifiedUser->name,
				'published' => $page->publishedStr,
			));
		}
		
		// include multi-language page names and statuses when applicable
		if($languages && $this->wire('modules')->isInstalled('LanguageSupportPageNames')) {
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$settings["name_$language->name"] = $page->get("name$language->id");
				$settings["status_$language->name"] = $page->get("status$language->id");
			}
		}

		// array of export data
		$a = array(
			'type' => 'ProcessWire:Page',
			'path' => $page->path(),
			'class' => $page->className(true), 
			'template' => $page->template->name,
			'settings' => $settings, 
			'data' => array(),
			// 'warnings' => array(),
		);
		
		$exportValueOptions = array(
			'system' => true, 
			'caller' => $this, 
			'FieldtypeFile' => array(
				'noJSON' => true
			),
			'FieldtypeImage' => array(
				'variations' => true, 
			),				
		);
	
		// iterate all fields and export value from each
		foreach($page->template->fieldgroup as $field) {
			/** @var Field $field */
			
			if(!empty($options['fieldNames']) && !in_array($field->name, $options['fieldNames'])) continue;
			
			$info = $this->getFieldInfo($field); 
			if(!$info['exportable']) continue;

			$value = $page->getUnformatted($field->name);
			$exportValue = $field->type->exportValue($page, $field, $value, $exportValueOptions);
			
			$a['data'][$field->name] = $exportValue;
			
			if($field->type instanceof FieldtypeFile && $value) {
				$numFiles += count($value);
			}
		}
		
		if($numFiles && $options['exportTarget'] == 'zip') {
			$a['_filesPath'] = $page->filesManager()->path();
		}

		if($of) $page->of(true);
		if($languages) $languages->unsetDefault();

		return $a;
	}

	/**
	 * Import an array of page data to create or update pages
	 * 
	 * Provided array ($a) must originate from the pagesToArray() method format. 
	 *
	 * @param array $a
	 * @param array $options
	 * @return PageArray|bool
	 * @throws WireException
	 *
	 */
	public function arrayToPages(array $a, array $options = array()) {
		
		if(empty($a['type']) || $a['type'] != 'ProcessWire:PageArray') {
			throw new WireException("Invalid array provided to arrayToPages() method");
		}

		$defaults = array(
			'count' => false,  // Return count of imported pages, rather than PageArray (reduced memory requirements)
			'pageArray' => null, 
		);
		
		$options = array_merge($defaults, $options);
		if(!empty($options['pageArray']) && $options['pageArray'] instanceof PageArray) {
			$pageArray = $options['pageArray'];
		} else {
			$pageArray = $this->wire('pages')->newPageArray();
		}
		$count = 0;
		
		// $a has: type (string), version (string), pagination (array), pages (array), fields (array)
		
		if(empty($a['pages'])) return $options['count'] ? 0 : $pageArray;

		// @todo generate warnings from this import info
		$info = $this->getImportInfo($a); 
		if($info) {}
		
		if(isset($a['url'])) $options['originalRootUrl'] = $a['url'];
		if(isset($a['host'])) $options['originalHost'] = $a['host'];
		
		foreach($a['pages'] as $item) {
			$page = $this->arrayToPage($item, $options);
			$id = $item['settings']['id'];
			$this->wire('notices')->move($page, $pageArray, array('prefix' => "Page $id: ")); 
			if(!$options['count']) $pageArray->add($page);
			$count++;
		}
	
		return $options['count'] ? $count : $pageArray;
	}

	/**
	 * Import an array of page data to a new Page (or update existing page)
	 * 
	 * Provided array ($a) must originate from the pageToArray() method format. 
	 * 
	 * Returns a Page on success or a NullPage on failure. Errors, warnings and messages related to the 
	 * import can be pulled from `$page->errors()`, `$page->warnings()` and `$page->messages()`. 
	 * 
	 * The following options may be used with the `$options` argument:
	 *  - `commit` (bool): Commit/save the changes now? (default=true). Specify false to perform a test run.
	 *  - `update` (bool): Allow update of existing pages? (default=true)
	 *  - `create` (bool): Allow creation of new pages? (default=true)
	 *  - `parent` (Page|string|int): Parent Page, path or ID. Omit to use import data (default=0).
	 *  - `template` (Template|string|int): Template object, name or ID. Omit to use import data (default=0).
	 *  - `fieldNames` (array): Import only these field names, or omit to use all import data (default=[]).
	 *  - `changeStatus` (bool): Allow status to be changed aon existing pages? (default=true)
	 *  - `changeSort` (bool): Allow sort and sortfield to be changed on existing pages? (default=true)
	 *  - `replaceTemplates` (array): Array of import-data template name to replacement template name (default=[])
	 *  - `replaceFields` (array): Array of import-data field name to replacement field name (default=[]) 
	 *  - `originalRootUrl` (string): Original root URL (not including hostname)
	 *  - `originalHost` (string): Original hostname 
	 * 
	 * The following options are for future use and not currently applicable:
	 *  - `changeTemplate` (bool): Allow template to be changed on existing pages? (default=false)
	 *  - `changeParent` (bool): Allow parent to be changed on existing pages? (default=false)
	 *  - `changeName` (bool): Allow name to be changed on existing pages? (default=false)
	 *  - `replaceParents` (array): Array of import-data parent path to replacement parent path (default=[])
	 * 
	 * @param array $a
	 * @param array $options Options to modify default behavior, see method description. 
	 * @return Page|NullPage
	 * @throws WireException
	 * 
	 */
	public function arrayToPage(array $a, array $options = array()) {
		
		if(empty($a['type']) || $a['type'] != 'ProcessWire:Page') {
			throw new WireException('Invalid array provided to arrayToPage() method');
		}
	
		/** @var Config $config */
		$config = $this->wire('config');

		$defaults = array(
			'id' => 0, // ID that new Page should use, or update, if it already exists. (0=create new). Sets update=true.
			'parent' => 0, // Parent Page, path or ID. (0=auto detect from imported page path)
			'template' => '', // Template object, name or ID. (0=auto detect from imported page template)
			'update' => true, // allow update of existing pages?
			'create' => true,  // allow creation of new pages?
			'delete' => false, // allow deletion of pages? (@todo)
			'changeTemplate' => false, // allow template to be changed on updated pages? (requires update=true)
			'changeParent' => false, 
			'changeName' => true, 
			'changeStatus' => true, 
			'changeSort' => true, 
			'saveOptions' => array('adjustName' => true, 'quiet' => true), // options passed to Pages::save
			'fieldNames' => array(),  // import only these field names, when specified
			'replaceFields' => array(), // array of import-data field name to replacement page field name
			'replaceTemplates' => array(), // array of import-data template name to replacement page template name
			'replaceParents' => array(), // array of import-data parent path to replacement parent path
			'filesPath' => '', // path where file field directories are located when importing from zip (internal use)
			'originalHost' => $config->httpHost, 
			'originalRootUrl' => $config->urls->root,
			'commit' => true, // commit the import? If false, changes aren't saved (dry run). 
			'debug' => false, 
		);
		
		$options = array_merge($defaults, $options); 
		$errors = array(); // fatal errors
		$warnings = array(); // non-fatal warnings
		$messages = array(); // informational
		$pages = $this->wire('pages');
		$languages = $this->wire('languages');
		$missingFields = array();
		
		if($options['id']) {
			$options['update'] = true;
			$options['create'] = false;
		}
		
		/** @var Languages $languages */
		if($languages) $languages->setDefault();

		// determine parent and template
		$page = $this->importGetPage($a, $options, $errors); 
		$parent = $page->id ? $page->parent : $this->importGetParent($a, $options, $errors); 
		$template = $page->id ? $page->template : $this->importGetTemplate($a, $options, $errors);
		
		$isNew = $page->id == 0 && !$page instanceof NullPage;
		$page->setTrackChanges(true); 	
		$page->setQuietly('_importPath', $a['path']); 
		$page->setQuietly('_importType', $isNew ? 'create' : 'update');
		$page->setQuietly('_importTemplate', $template); 
		$page->setQuietly('_importParent', $parent); 
		$page->setQuietly('_importOriginalID', $a['settings']['id']); // original/external ID
		
		// if any errors occurred above, abort
		if(count($errors) && !$page instanceof NullPage) $page = new NullPage(); 
	
		// if we were only able to create a NullPage, abort now
		if($page instanceof NullPage) {
			foreach($errors as $error) $page->error($error);
			if($languages) $languages->unsetDefault();
			return $page;
		}

		$page->of(false);
		$this->importPageSettings($page, $a['settings'], $options); 
		$changes = $page->getChanges();

		// save blank page now if it is new, so that it has an ID
		if($isNew && $options['commit']) {
			$pages->save($page, $options['saveOptions']);
		}

		// populate custom fields
		foreach($a['data'] as $name => $value) {
			
			if(count($options['fieldNames']) && !in_array($name, $options['fieldNames'])) continue;
			if(isset($options['replaceFields'][$name])) $name = $options['replaceFields'][$name];
			
			$field = $this->wire('fields')->get($name); 
			
			if(!$field) {
				if(is_array($value) && !count($value)) continue;
				if(!is_array($value) && !strlen($value)) continue;
				$missingFields[$name] = $name;
				continue;
			}
			
			$fieldInfo = $this->getFieldInfo($field);
			
			if(!$fieldInfo['exportable']) {
				// field cannot be imported
				$warnings[] = $fieldInfo['reason'];
			} else {
				// proceed with import of field
				try {
					$this->importFieldValue($page, $field, $value, $options);
				} catch(\Exception $e) {
					$warnings[] = $e->getMessage();
				}
			}
		}
		
		if(count($missingFields)) {
			$warnings[] = "Skipped fields (not found): " . implode(', ', $missingFields); 
		}
	
		$changes = array_unique(array_merge($changes, $page->getChanges())); 
	
		if($options['commit']) {
			$pages->save($page, $options['saveOptions']);
		}

		if($languages) $languages->unsetDefault();
		
		foreach($errors as $error) $page->error($error); 
		foreach($warnings as $warning) $page->warning($warning);
		foreach($messages as $message) $page->message($message); 
		
		$page->setQuietly('_importChanges', $changes);
		$page->setQuietly('_importMissingFields', $missingFields); 
		
		return $page;
	}

	/**
	 * Get the page to import to
	 * 
	 * @param array $a Import data
	 * @param array $options Import settings
	 * @param array $errors Errors array
	 * @return NullPage|Page
	 * 
	 */
	protected function importGetPage(array &$a, array &$options, array &$errors) {
		
		/** @var Pages $pages */
		$pages = $this->wire('pages');
		$path = $a['path'];
		
		/** @var Page|NullPage $page */
		
		if(!empty($options['id'])) {
			$page = $pages->get((int) $options['id']);
			if(!$page->id) {
				$errors[] = "Unable to find specified page to update by ID: $options[id]";
			}
			
		} else {
			if(isset($a['_importToID'])) {
				// if provided with ID added by getImportInfo() method
				$id = (int) $a['_importToID'];
				$page = $id ? $pages->get($id) : new NullPage();
			} else {
				$page = $pages->get($path);
			}
			if($page->id && !$options['update']) {
				// create new page rather than updating existing page
				$errors[] = "Skipped update to existing page because update option is disabled";
			} else if($page->id) {
				// update of existing page allowed
			} else if(!$options['create']) {
				// creation of new pages is not allowed
				$errors[] = "Skipped create of new page because create option is disabled";
			} else if(wireClassExists($a['class'])) {
				// use specified class
				$page = new $a['class']();
			} else {
				// requested page class does not exist (warning?)
				$warnings[] = "Unable to locate Page class '$a[class]', using Page class instead";
				$page = new Page();
			}
		}
		
		return $page;
	}

	/**
	 * Get the Page Template to use for import
	 * 
	 * @param array $a Import data
	 * @param array $options Import options
	 * @param array $errors Errors array
	 * @return Template|null
	 * 
	 */
	protected function importGetTemplate(array &$a, array &$options, array &$errors) {
		$template = empty($options['template']) ? $a['template'] : $options['template'];
		$name = is_object($template) ? $template->name : $template;
		if(isset($options['replaceTemplates'][$name])) $template = $options['replaceTemplates'][$name];
		$_template = $template;
		if(is_object($template)) {
			// ok
		} else {
			$template = $this->wire('templates')->get($template);
		}
		if($template) {
			$options['template'] = $template;
			$a['template'] = (string) $template;
		} else {
			$errors[] = "Unable to locate template: $_template";
		}
		return $template; 
	}

	/**
	 * Get the parent of the page being imported
	 * 
	 * @param array $a Import data
	 * @param array $options Import options
	 * @param array $errors Errors array
	 * @return Page|NullPage
	 * 
	 */
	protected function importGetParent(array &$a, array &$options, array &$errors) {
		// determine parent
		static $previousPaths = array();
		$usePrevious = true;
		$pages = $this->wire('pages'); 
		$path = $a['path'];
		
		if($options['parent']) {
			// parent specified in options
			if(is_object($options['parent']) && $options['parent'] instanceof Page) {
				$parent = $options['parent'];
			} else if(ctype_digit("$options[parent]")) {
				$parent = $pages->get((int) $options['parent']);
			} else {
				$parent = $pages->get('/' . ltrim($options['parent'], '/'));
			}
			if($parent->id) {
				$options['changeParent'] = true;
				$path = $parent->path . $a['settings']['name'] . '/';
				$a['path'] = $path;
			} else {
				$errors[] = "Specified parent does not exist: $options[parent]";
			}
		} else if(strrpos($path, '/')) {
			// determine parent from imported page path
			$parts = explode('/', trim($path, '/'));
			array_pop($parts); // pop off name
			$parentPath = '/' . implode('/', $parts);
			if(strlen($parentPath) > 1) $parentPath .= '/';
			if(isset($options['replaceParents'][$parentPath])) {
				$parentPath = $options['replaceParents'][$parentPath];
			}
			$parent = $pages->get($parentPath);
			if(!$parent->id) {
				$foundParent = false;
				if(!$options['commit']) {
					// check if the parent will be created by the import
					if(isset($previousPaths[$parentPath])) {
						$foundParent = true; 
					}
				}
				if(!$foundParent) {
					$errors[] = "Unable to locate parent page: $parentPath";
					$usePrevious = false;
				}
			}
		} else if($path === '/') {
			// homepage, parent is not applicable
			$parent = new NullPage();
		} else {
			// parent cannot be determined
			$parent = new NullPage();
			$errors[] = "Unable to determine parent";
		}
		
		if($parent->id) {
			$options['parent'] = $parent;
		}

		if($usePrevious){
			$key = rtrim($path, '/');
			if($key) $previousPaths[$path] = true;
		}
		
		return $parent;
	}

	/**
	 * Import native page settings
	 * 
	 * @param Page $page
	 * @param array $settings Contents of the import data 'settings' array
	 * @param array $options
	 * 
	 */
	protected function importPageSettings(Page $page, array $settings, array $options) {
		
		$isNew = $page->get('_importType') == 'create';
		
		// we don't currently allow template changes on existing pages	
		if(!$isNew) $options['changeTemplate'] = false;
		$template = $options['template'];
		$parent = $options['parent'];
		$languages = $this->wire('languages');
		$langProperties = array();

		// populate page base settings
		if($options['changeTemplate'] || $isNew) {
			if(!$page->template || $page->template->name != $template->name) $page->template = $template;
		}
		if($options['changeParent'] || $isNew) {
			if($parent && $page->parent->id != $parent->id) $page->parent = $parent;
		}
		if($options['changeStatus'] || $isNew) {
			if($page->status != $settings['status']) $page->status = $settings['status'];
			$langProperties[] = 'status';
		}
		if($options['changeName'] || $isNew) {
			if($page->name != $settings['name']) $page->name = $settings['name'];
			$langProperties[] = 'name';
		}
		if($options['changeSort'] || $isNew) {
			if($page->sort != $settings['sort']) $page->sort = $settings['sort'];
			if($page->sortfield != $settings['sortfield']) $page->sortfield = $settings['sortfield'];
		}
		
		foreach(array('created', 'modified', 'published') as $dateType) {
			if(isset($settings[$dateType])) {
				$page->set($dateType, strtotime($settings[$dateType]));
			}
		}

		if($languages && count($langProperties)) {
			foreach($langProperties as $property) {
				foreach($languages as $language) {
					if($language->isDefault()) continue;
					$remoteKey = "{$property}_$language->name";
					$localKey = "{$property}$language->id";
					if(!isset($settings[$remoteKey])) continue;
					if($settings[$remoteKey] != $page->get($localKey)) {
						$page->set($localKey, $settings[$remoteKey]);
					}
				}
			}
		}
	}

	/**
	 * Import value for a single field
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param array|string|int|float $importValue
	 * @param array $options Looks only at 'commit' option to determine when testing 
	 * 
	 */
	protected function importFieldValue(Page $page, Field $field, $importValue, array $options) {
		
		if($field->type instanceof FieldtypeFile) {
			// file fields (cannot be accessed until page exists)
			if($page->id) {
				$this->importFileFieldValue($page, $field, $importValue, $options);
				return;
			} else if(!empty($importValue)) {
				$page->trackChange($field->name);
			}
		}
		
		$fieldtypeImportDefaults = array(
			// supports testing before commit (populates notices to returned Wire).
			'test' => false, 
			// returns the value that should set back to Page? (false=return value for notices only).
			// when false, it also indicates the Fieldtype::importValue() handles the actual commit to DB of import data.
			'returnsPageValue' => true, 
			// indicates Fieldtype::importValue() would like an 'exportValue' of the current value from Page in $options
			'requiresExportValue' => false, 
		);
		
		$fieldtypeImportOptions = array_merge($fieldtypeImportDefaults, $field->type->getImportValueOptions($field));
		
		$o = array(
			'importType' => $page->get('_importType'), 
			'system' => true,
			'caller' => $this, 
			'commit' => $options['commit'], 
			'test' => !$options['commit'],
			'originalHost' => $options['originalHost'],
			'originalRootUrl' => $options['originalRootUrl'],
		);
		
		// fake-commit for more verbose testing of certain fieldtypes
		$fakeCommit = $options['commit'] || !empty($fieldtypeImportOptions['test']);
		
		if($page->get('_importType') == 'create' && !$options['commit'] && !$fakeCommit) {
			// test import on a new page, so value will always be used
			$page->trackChange($field->name);
			return;
		}
		
		$pageValue = $page->getUnformatted($field->name);
		$exportValue = $pageValue === null || !$page->id ? null : $field->type->exportValue($page, $field, $pageValue, $o);
		
		if(is_array($importValue) && is_array($exportValue)) {
			// use regular '==' only for array comparisons
			if($exportValue == $importValue) return;
		} else {
			// use '===' for all other value comparisons
			if($exportValue === $importValue) return;
		}

		// at this point, values appear to be different
		if($fieldtypeImportOptions['requiresExportValue']) $o['exportValue'] = $exportValue;
		
		if($options['commit'] || $fakeCommit) {
			$commitException = false;
			try {
				$pageValue = $field->type->importValue($page, $field, $importValue, $o);
			} catch(\Exception $e) {
				$warning = $e->getMessage();
				$page->warning((strpos($warning, "$field:") === 0 ? '' : "$field: ") . $warning);
				if($options['commit'] && $fieldtypeImportOptions['restoreOnException'] && $page->id) {
					$commitException = true;
					try {
						$pageValue = $field->type->importValue($page, $field, $exportValue, $o);
						$page->warning("$field: Attempted to restore previous value");
					} catch(\Exception $e) {
						$commitException = true;
					}
				}
			}
			if(!$commitException) {
				if($pageValue !== null && $fieldtypeImportOptions['returnsPageValue']) {
					$page->set($field->name, $pageValue);
				} else if(!$fieldtypeImportOptions['returnsPageValue']) {
					$page->trackChange("{$field->name}__");
				}
			}
			if(is_object($pageValue) && $pageValue instanceof Wire) {
				// movie notices from the pageValue to the page
				$this->wire('notices')->move($pageValue, $page); 
			}
		} else {
			// test import on existing page, avoids actually setting value to the page
			$page->trackChange($field->name); 
		}
		
		if($options['debug']) {
			if(is_string($exportValue)) $exportValue = strlen($exportValue) . " bytes\n" . $exportValue;
			if(is_string($importValue)) $importValue = strlen($importValue) . " bytes\n" . $importValue;
			$this->message("$field->name OLD: <pre>" . htmlentities(print_r($exportValue, true)) . "</pre>", Notice::allowMarkup);
			$this->message("$field->name NEW: <pre>" . htmlentities(print_r($importValue, true)) . "</pre>", Notice::allowMarkup);
		}
	}

	/**
	 * Import a files/images field and populate to given $page
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param array $data Export value of file field
	 * @param array $options
	 * 
	 */
	protected function importFileFieldValue(Page $page, Field $field, array $data, array $options = array()) {
		
		// Expected format of given $data argument: 
		// $data = [
		//      'file1.jpg' => [
		//          'url' => 'http://domain.com/site/assets/files/123/file1.jpg',
		//          'description' => 'file description',
		//          'tags' => 'file tags',
		//          'variations' => [ 'file1.260x0.jpg' => 'http://domain.com/site/assets/files/123/file1.260x0.jpg' ]
		//      ],
		//      'file2.png' => [ ... see above ... ],
		//      'file3.gif' => [ ... see above ... ],
		// ];

		/** @var Pagefiles $pagefiles */
		$pagefiles = $page->get($field->name); 
		if(!$pagefiles || !$pagefiles instanceof Pagefiles) {
			$page->warning("Unable to import files to field '$field->name' because it is not a files field"); 
			return;
		}
		
		$filesAdded = array();
		$filesUpdated = array();
		$filesRemoved = array();
		$variationsAdded = array();
		
		$maxFiles = (int) $field->get('maxFiles'); 
		$languages = $this->wire('languages'); 
		$filesPath = $pagefiles->path();
		/** @var null|WireHttp $http */
		$http = null; 
		$pageID = $page->get('_importOriginalID'); 
		
		foreach($data as $fileName => $fileInfo) {
		
			/** @var Pagefile $pagefile */
			$pagefile = $pagefiles->get($fileName); 
			$isNew = false;
			
			if(!$pagefile) {
				// new file, needs to be added
				$isNew = true;
				try {
					if($options['commit']) {
						if(empty($options['filesPath'])) {
							// importing from ZIP where files are located under filesPath option
							$pagefiles->add($fileInfo['url']);
						} else {
							// importing from URL
							$pagefiles->add("$options[filesPath]$pageID/$fileName");
						}
						$pagefile = $pagefiles->last();
						if(!$pagefile) throw new WireException("Unable to add file $fileInfo[url]");
						if($maxFiles === 1 && $pagefiles->count() > 1) {
							$pagefiles->remove($pagefiles->first()); // file replacement
						}
					} else {
						$pagefile = null;
					}
					$filesAdded[] = $fileName;
				} catch(\Exception $e) {
					$page->warning($e->getMessage()); 	
					$pagefile = null;
				}
				if(!$pagefile) continue;
			}
			
			$pagefile->setTrackChanges(true);
			$variations = array();
			
			// description, tags, etc. 
			foreach($fileInfo as $key => $value) {
				if($key == 'url') continue;
				if($key == 'size') continue;
				if($key == 'variations') {
					$variations = $value;
					continue;
				}
				if($key == 'description') {
					$oldValue = $languages ? $pagefile->description(true, true) : $pagefile->get('description');
				} else {
					$oldValue = $pagefile->get($key);
				}
				if($value == $oldValue) {
					continue; // no differences
				}
				if(empty($value) && empty($oldValue)) {
					continue; // no differences
				}
				if($key == 'description') {
					$pagefile->description($value);
					if(!$pagefile->isChanged($key)) continue;
				} else if($options['commit']) {
					$pagefile->set($key, $value);
					if(!$pagefile->isChanged($key)) continue;
				}
				if(!isset($filesUpdated[$key])) $filesUpdated[$key] = array();
				if(!$isNew) {
					$filesUpdated[$key][] = $fileName;
					if($options['debug']) {
						$this->message("$field->name: $pagefile->name ($key) OLD: <pre>" . 
							print_r($oldValue, true) . "</pre>", Notice::allowMarkup);
						$this->message("$field->name: $pagefile->name ($key) NEW: <pre>" . 
							print_r($value, true) . "</pre>", Notice::allowMarkup); 
					}
				}
			}
		
			// image variations
			foreach($variations as $name => $url) {
				
				$targetFile = $filesPath . $name;
				$sourceFile = empty($options['filesPath']) ? '' : "$options[filesPath]$pageID/$name";
				$targetExists = file_exists($targetFile); 
				$sourceExists = $sourceFile ? file_exists($sourceFile) : false;
				
				if($sourceExists && $targetExists) {
					// skip because they are likely the same
					if(filesize($sourceFile) == filesize($targetFile)) continue; 
				} else if($targetExists) {
					// target already exists so skip it (since we don't have a way to check size)
					continue; 	
				}
				
				if(!$options['commit']) {
					$variationsAdded[] = $name;
					continue; 
				}
				
				if($sourceExists) {
					// copy variation from options[filesPath]
					if($this->wire('files')->copy($sourceFile, $targetFile)) {
						$variationsAdded[] = $name;
					} else {
						$page->warning("Unable to copy file (image variation): $sourceFile");
					}
				} else {
					// download variation via http
					try {
						if(is_null($http)) $http = $this->wire(new WireHttp());
						$http->download($url, $targetFile);
						$variationsAdded[] = $name;
					} catch(\Exception $e) {
						$page->warning("Error downloading file (image variation): $url - " . $e->getMessage());
					}
				}
			}
		}
	
		// determine removed files
		foreach($pagefiles as $pagefile) {
			if(isset($data[$pagefile->name])) continue; 
			$filesRemoved[] = $pagefile->name; 
			if($options['commit']) $pagefiles->remove($pagefile); 
		}
	
		// summarize all of the above
		$numAdded = count($filesAdded);
		$numUpdated = count($filesUpdated);
		$numRemoved = count($filesRemoved); 
		$numVariations = count($variationsAdded); 
		$numTotal = $numAdded + $numUpdated + $numRemoved; // intentionally excludes numVariations
		
		if($numTotal > 0) {
			$pagefiles->trackChange('value');
			if($options['commit']) $page->set($field->name, $pagefiles); 
			$page->trackChange($field->name);
			if($numAdded) $page->message("$field->name: " . 
				sprintf($this->_n('Added %d file', 'Added %d files', $numAdded), $numAdded) . ": " . 
				implode(', ', $filesAdded)
			); 
			if($numUpdated) {
				foreach($filesUpdated as $property => $files) {
					$numFiles = count($files); 
					$page->message("$field->name: " . 
						sprintf($this->_n('Updated %s for %d file', 'Updated %s for %d files', $numFiles), $property, $numFiles) . ': ' . 
						implode(', ', $files)
					);
				}
			}
			if($numRemoved) $page->message("$field->name: " . 
				sprintf($this->_n('Removed %d file', 'Removed %d files', $numRemoved), $numRemoved) . ": " . 
				implode(', ', $filesRemoved)
			); 
		}
		
		if($numVariations) {
			$addedType = $http === null ? 'ZIP copy' : 'HTTP download'; 
			$page->trackChange($field->name); 
			$page->message("$field->name (variation): " .
				sprintf(
					$this->_n('Added %d file via %s', 'Added %d files via %s', $numVariations), 
					$numVariations, $addedType
				) . ": " . implode(', ', $variationsAdded)
			); 
		}
	}
	/**
	 * Return array of info about the import data
	 * 
	 * This also populates the given import data ($a) with an '_info' property, which is an array containing 
	 * all of the import info returned by this method. For each item in the 'pages' index it also populates
	 * an '_importToID' property containing the ID of the existing local page to update, or 0 if it should be
	 * a newly created page. 
	 *
	 * Return value:
	 * ~~~~~
	 * array(
	 *   'numNew' => 0,
	 *   'numExisting' => 0,
	 *   'missingParents' => [ '/path/to/parent/' ],
	 *   'missingTemplates' => [ 'basic-page-hello' ],
	 *   'missingFields' => [ 'some_field', 'another_field' ],
	 *   'missingFieldsTypes' => [ 'some_field' => 'FieldtypeText', 'another_field' => 'FieldtypeTextarea' ]
	 *   'mismatchedFields' => [ 'some_field' => 'FieldtypeText' ] // field name => expected type
	 *   'missingTemplateFields' => [ 'template_name' => [ 'field1', 'field2', etc ] ]
	 * );
	 * ~~~~~
	 *
	 * @param array $a Import data array
	 * @return array
	 *
	 */
	public function getImportInfo(array &$a) {

		$missingTemplateFields = array();
		$missingFieldsTypes = array();
		$missingTemplates = array();
		$mismatchedFields = array();
		$missingParents = array();
		$missingFields = array();
		$templateNames = array();
		$parentPaths = array();
		$pagePaths = array();
		$numExisting = 0;
		$numNew = 0;

		/** @var Pages $pages */
		$pages = $this->wire('pages');
		/** @var Fields $fields */
		$fields = $this->wire('fields');
		/** @var Sanitizer $sanitizer */
		$sanitizer = $this->wire('sanitizer');
		/** @var PageFinder $pageFinder */
		$pageFinder = $this->wire(new PageFinder());
		
		// Identify missing fields
		foreach($a['fields'] as $fieldName => $fieldInfo) {
			// Note: $fieldInfo [ 'type' => 'FieldtypeText', 'version' => '1.0.0', 'blankValue' => '' ]
			$field = $fields->get($fieldName);
			if(!$field) {
				$missingFields[] = $fieldName;
				$missingFieldsTypes[$fieldName] = $fieldInfo['type'];
			} else if($fieldInfo['type'] != $field->type->className()) {
				$mismatchedFields[$fieldName] = $fieldInfo['type'];
			}
		}

		// Determine which pages are new and which are existing
		foreach($a['pages'] as $key => $item) {
			$path = $sanitizer->pagePathNameUTF8($item['path']);
			if($item['path'] !== $path) continue; 
			$pagePaths[$path] = $item['settings']['id'];
			if($path != '/') {
				$parts = explode('/', trim($path, '/'));
				array_pop($parts);
				$parentPath = '/' . implode('/', $parts);
				if(count($parts)) $parentPath .= '/';
				$parentPaths[$parentPath] = $parentPath;
			}
			$templateName = $item['template'];
			if(!isset($templateNames[$templateName])) {
				$templateNames[$templateName] = array_keys($item['data']);
			}
			
			$pageIDs = $pageFinder->findIDs(new Selectors("path=$path, include=all")); 
			
			if(!count($pageIDs)) {
				// no match
				$pageID = 0;
			} else if(count($pageIDs) > 1) {
				// more than one match, use another method
				$pageID = $pages->get($path)->id;
			} else {
				// found
				$pageID = reset($pageIDs); 
			}
			
			$a['pages'][$key]['_importToID'] = $pageID; // populate local ID 
			$pageID ? $numExisting++ : $numNew++;
		}

		// determine which templates are missing, and which fields are missing from templates
		foreach($templateNames as $templateName => $fieldNames) {
			$template = $this->wire('templates')->get($templateName);
			if($template) {
				// template exists
				$missingTemplateFields[$templateName] = array();
				foreach($fieldNames as $fieldName) {
					if(isset($missingFields[$fieldName]) || !$template->hasField($fieldName)) {
						$missingTemplateFields[$templateName][] = $fieldName;
					}
				}
			} else {
				// template does not exist
				$missingTemplates[] = $templateName;
			}
		}

		// determine which parents are missing
		foreach($parentPaths as $key => $path) {
			if(isset($pagePaths[$path])) {
				// this parent already exists or will be created during import
			} else {
				$parentID = $pages->getByPath($path, array('getID' => true));
				if(!$parentID) $missingParents[] = $path;
			}
		}
		/*
		foreach($missingParents as $key => $path) {
			// remove parents that are children of another missing parent
			foreach($missingParents as $k => $p) {
				if($key === $k) continue;
				if(strlen($path) > strlen($p)) {
					if(strpos($path, $p) === 0) unset($missingParents[$key]); 
				} else {
					if(strpos($p, $path) === 0) unset($missingParents[$k]);
				}
				
			}
		}
		*/

		$info = array(
			'numNew' => $numNew,
			'numExisting' => $numExisting,
			'missingParents' => $missingParents,
			'missingFields' => $missingFields,
			'missingFieldsTypes' => $missingFieldsTypes,
			'mismatchedFields' => array(),
			'missingTemplates' => $missingTemplates,
			'missingTemplateFields' => $missingTemplateFields
		);
		
		$a['_info'] = $info;
		
		return $info;
	}

	/**
	 * Returns array of information about given Field
	 * 
	 * Populates the following indexes: 
	 *  - `exportable` (bool): True if field is exportable, false if not. 
	 *  - `reason` (string): Reason why field is not exportable (when exportable==false). 
	 * 
	 * @param Field $field
	 * @return array
	 * 
	 */
	public function getFieldInfo(Field $field) {
		
		static $cache = array();
		
		if(isset($cache[$field->id])) return $cache[$field->id];
		
		$fieldtype = $field->type;
		$exportable = true;
		$reason = '';
		
		$extraType = wireInstanceOf($fieldtype, array(
			'FieldtypeFile',
			'FieldtypeRepeater',
			'FieldtypeComments',
		));
		
		if($extraType) {
			// extra identified types are allowed
			
		} else if($fieldtype instanceof FieldtypeFieldsetOpen || $fieldtype instanceof FieldtypeFieldsetClose) {
			// fieldsets not exportable
			$reason = 'Nothing to export/import for fieldsets';
			$exportable = false;
			
		} else {
			// test to see if exportable
			try {
				$importInfo = $fieldtype->getImportValueOptions($field); 
			} catch(\Exception $e) {
				$exportable = false;
				$reason = $e->getMessage();
				$importInfo = false;
			}

			if($exportable && $importInfo && !$importInfo['importable']) {
				// this fieldtype is storing data outside of the DB or in other unknown tables
				// there's a good chance we won't be able to export/import this into an array
				// @todo check if fieldtype implements its own exportValue/importValue, and if
				// it does then allow the value to be exported
				$exportable = false;
				$reason = "Field '$field' cannot be used because $field->type indicates imports are not supported";
			}
		}
		
		if(!$exportable && empty($reason)) $reason = 'Export/import not supported';

		$info = array(
			'exportable' => $exportable,
			'reason' => $reason,
		);

		$cache[$field->id] = $info;
		
		return $info;
	}

}
