<?php namespace ProcessWire;

/**
 * ProcessWire Pages Export/Import Helpers
 * 
 * This class is in development and not yet ready for use. 
 * 
 * $options argument for import methods:
 * 
 * - `parent` (Page|int|string): Parent Page, path or ID. (default=0, auto detect from imported page path)
 * - `template` (Template|int|string): Template object, name or ID. (default=0, auto detect from imported page template)
 * - `update` (bool): Update existing Page (rather than create new) if another page already has the same name+parent? (default=true)
 * - `skip` (bool): Skip page update/create if page already exists? (default=false)
 * - `changeTemplate` (bool): Allow template to be changed on updated pages? (default=false)
 * - `changeParent` (bool): Allow parent of existing pages to be changed? (default=false)
 * - `changeName` (bool): Allow name of existing pages to be changed? (default=false)
 * - `changeStatus` (bool): Allow status of existing pages to be changed? (default=true)
 * - `changeSort` (bool): Allow sort and sortfield properties of existing pages to be changed? (default=true)
 * - `saveOptions` (array): The $options agument provided to Pages::save() method. (default=['adjustName'=>true])
 * 
 * Note: all the "change" prefix options require update=true and skip=false options to be set. 
 * 
 *
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesExportImport extends Wire {

	/**
	 * Export given PageArray to a ZIP file
	 * 
	 * @param PageArray $items
	 * @param array $options
	 * @return string|bool Path+filename to ZIP file or boolean false on failure
	 * 
	 */
	public function exportZIP(PageArray $items, array $options = array()) {
		$tempDir = new WireTempDir($this);
		$this->wire($tempDir);
		$tempDir->setRemove(false);
		$path = $tempDir->get();
		$jsonFile = $path . "pages.json";
		$jsonData = $this->exportJSON($items, $options);
		file_put_contents($jsonFile, $jsonData);
		/** @var WireFileTools $files */
		$files = $this->wire('files');
		$zipFileItems = array($jsonFile);
		$zipFileName = $path . 'pages.zip';
		$zipFileInfo = $files->zip($zipFileName, $zipFileItems); 
		foreach($zipFileItems as $file) {
			unlink($file);
		}
		return $zipFileName;
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
		$zipFileItems = $this->wire('files')->unzip($filename, $path); 
		if(empty($zipFileItems)) {
			$pageArray = false;
		} else {
			$jsonFile = $path . "pages.json";
			$jsonData = file_get_contents($jsonFile);
			$pageArray = $this->importJSON($jsonData, $options);
		}
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

		$defaults = array(
			'verbose' => false,
			'fieldNames' => array(), // export only these field names, when specified
		);

		$options = array_merge($defaults, $options);
		$options['verbose'] = false; // TMP option not yet supported

		$a = array(
			'type' => 'ProcessWire:PageArray',
			'version' => $this->wire('config')->version,
			'pagination' => array(),
			'pages' => array(),
			'fields' => array(),
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
			$a['pages'][] = $exportItem;

			// include information about field settings so that warnings can be generated at
			// import time if there are applicable differences in the field settings
			foreach($exportItem['data'] as $fieldName => $value) {
				if(isset($a['fields'][$fieldName])) continue;
				$field = $this->wire('fields')->get($fieldName);
				if(!$field || !$field->type) continue;
				$moduleInfo = $this->wire('modules')->getModuleInfoVerbose($field->type);
				if($options['verbose']) {
					$fieldData = $field->getExportData();
					unset($fieldData['id'], $fieldData['name']);
					$a['fields'][$fieldName] = $fieldData;
				} else {
					$a['fields'][$fieldName] = array(
						'type' => $field->type->className(),
						'version' => $moduleInfo['versionStr']
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
			}

			// include information about template settings so that warnings can be generated
			// at import time if there are applicable differences in the template settings
			if($options['verbose']) {
				if(!isset($templates[$item->template->name])) {
					$templates[$item->template->name] = $item->template->getExportData();
				}
			}
		}

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
		
		$of = $page->of();
		$page->of(false);

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
	
		// standard page settings
		$settings = array(
			'id' => $page->id, // for connection to exported file directories only
			'name' => $page->name,
			'status' => $page->status,
			'sort' => $page->sort,
			'sortfield' => $page->sortfield,
		);

		// verbose page settings
		if(!empty($options['verbose'])) {
			$settings = array_merge($settings, array(
				'parent_id' => $page->parent_id,
				'templates_id' => $page->templates_id,
				'created' => $page->createdStr,
				'modified' => $page->modifiedStr,
				'published' => $page->publishedStr,
				'created_user' => $page->createdUser->name,
				'modified_user' => $page->modifiedUser->name,
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
			'warnings' => array(),
		);
	
		// iterate all fields and export value from each
		foreach($page->template->fieldgroup as $field) {
			
			if(!empty($options['fieldNames']) && !in_array($field->name, $options['fieldNames'])) continue;
			
			$info = $this->getFieldInfo($field); 
			if(!$info['exportable']) {
				$a['warnings'][$field->name] = $info['reason'];
				continue;
			}

			/** @var Field $field */
			/** @var Fieldtype $fieldtype */
			$value = $page->get($field->name);
			$exportValue = $field->type->exportValue($page, $field, $value, array('system' => true));
			$a['data'][$field->name] = $exportValue;
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
		);
		
		$options = array_merge($defaults, $options);
		$pageArray = $this->wire('pages')->newPageArray();
		$count = 0;
		
		// $a has: type (string), version (string), pagination (array), pages (array), fields (array)
		
		if(empty($a['pages'])) return $options['count'] ? 0 : $pageArray;
		
		foreach($a['pages'] as $item) {
			$page = $this->arrayToPage($item, $options);
			$e = $page->errors('string clear');
			$w = $page->warnings('string clear');
			$id = $item['settings']['id'];
			if(strlen($e)) foreach(explode("\n", $e) as $s) $pageArray->error("Page $id: $s");
			if(strlen($w)) foreach(explode("\n", $w) as $s) $pageArray->warning("Page $id: $s");
			$count++;
			if(!$options['count']) $pageArray->add($page);
		}
	
		return $options['count'] ? $count : $pageArray;
	}

	/**
	 * Import an array of page data to a new Page (or update existing page)
	 * 
	 * Provided array ($a) must originate from the pageToArray() method format. 
	 * 
	 * @param array $a
	 * @param array $options
	 * @return Page|NullPage
	 * @throws WireException
	 * 
	 */
	public function arrayToPage(array $a, array $options = array()) {
		
		if(empty($a['type']) || $a['type'] != 'ProcessWire:Page') {
			throw new WireException('Invalid array provided to arrayToPage() method');
		}

		$defaults = array(
			'id' => 0, // ID that new Page should use, or update, if it already exists. (0=create new). Sets update=true.
			'parent' => 0, // Parent Page, path or ID. (0=auto detect from imported page path)
			'template' => '', // Template object, name or ID. (0=auto detect from imported page template)
			'update' => true, // update existing Page (rather than create new) if another page already has the same name+parent?
			'skip' => false, // skip page update/create if page already exists?
			'changeTemplate' => false, // allow template to be changed on updated pages? (requires update=true, skip=false)
			'changeParent' => false, 
			'changeName' => true, 
			'changeStatus' => true, 
			'changeSort' => true, 
			'saveOptions' => array('adjustName' => true), // options passed to Pages::save
			'fieldNames' => array(),  // import only these field names, when specified
		);
		
		$options = array_merge($defaults, $options); 
		$errors = array(); // fatal errors
		$warnings = array(); // non-fatal warnings
		$pages = $this->wire('pages');
		$path = $a['path'];
		$languages = $this->wire('languages');
		$fileFields = array();
		
		if($options['id']) {
			$options['update'] = true;
			$options['skip'] = false;
		}
		
		/** @var Languages $languages */
		if($languages) $languages->setDefault();

		// determine parent
		if($options['parent']) {
			// parent specified in options
			if(is_object($options['parent']) && $options['parent'] instanceof Page) {
				$parent = $options['parent'];
			} else if(ctype_digit("$options[parent]")) {
				$parent = $pages->get((int) $options['parent']); 
			} else {
				$parent = $pages->get('/' . ltrim($options['parent'], '/')); 
			}
			if(!$parent->id) $errors[] = "Specified parent does not exist: $options[parent]";
		} else if(strrpos($path, '/')) {
			// determine parent from imported page path
			$parts = explode('/', trim($path, '/'));
			array_pop($parts); // pop off name
			$parentPath = '/' . implode('/', $parts);
			$parent = $pages->get($parentPath); 
			if(!$parent->id) $errors[] = "Unable to locate parent page: $parentPath";
		} else if($path === '/') {
			// homepage, parent is not applicable
			$parent = new NullPage();	
		} else {
			// parent cannot be determined
			$parent = new NullPage();	
			$errors[] = "Unable to determine parent";
		}
		
		// determine template
		$template = empty($options['template']) ? $a['template'] : $options['template'];
		if(!is_object($template)) {
			$_template = $template;
			$template = $this->wire('templates')->get($template);
			if(!$template) $errors[] = "Unable to locate template: $_template";
		}

		// determine page (new or existing)
		/** @var Page|NullPage $page */
		if(!empty($options['id'])) {
			$page = $pages->get((int) $options['id']); 
			if(!$page->id) {
				$errors[] = "Unable to find specified page to update by ID: $options[id]";
			}
		} else {
			$page = $pages->get($path);
			if($page->id) {
				// updating existing Page
			} else if(wireClassExists($a['class'])) {
				// use specified class
				$page = new $a['class']();
			} else {
				// requested page class does not exist (warning?)
				$page = new Page();
			}
		}
	
		if($page->id) {
			// page laready exists, determine if we should update it
			if($options['skip']) {
				$errors[] = "Skipped update to page because options[skip=true]: $page->path";
			} else if($options['update']) {
				// existing page will be updated
			} else {
				// create new page rather than updating existing page
				$page = new Page();
			}
		}

		// if any errors occurred above, abort
		if(count($errors) || $page instanceof NullPage) {
			foreach($errors as $error) $page->error($error);
			return $page;
		}
		
		// populate page base settings
		$isNew = $page->id == 0;
		$page->of(false);
		if($options['changeTemplate'] || $isNew) $page->template = $template;
		if($options['changeParent'] || $isNew) $page->parent = $parent;
		if($options['changeName'] || $isNew) $page->name = $a['settings']['name'];
		if($options['changeStatus'] || $isNew) $page->status = $a['settings']['status'];
		if($options['changeSort'] || $isNew) {
			$page->sort = $a['settings']['sort'];
			$page->sortfield = $a['settings']['sortfield'];
		}

		// save blank page now if it is new, so that it has an ID
		if($isNew) $pages->save($page, $options['saveOptions']); 
		
		// populate custom fields
		foreach($page->template->fieldgroup as $field) {
			if(count($options['fieldNames']) && !in_array($field->name, $options['fieldNames'])) continue;
			if(!isset($a['data'][$field->name])) {
				$warnings[] = "Skipped field “$field->name” - template “$template” does not have it";
				continue;
			} else if($field->type instanceof FieldtypeFile) {
				$fileFields[] = $field;
				continue;
			}
			try {
				$value = $field->type->importValue($page, $field, $a['data'][$field->name], array('system' => true));
				$page->set($field->name, $value); 
			} catch(\Exception $e) {
				$warnings[] = $e->getMessage();
			}
		}
	
		// handle file fields
		if(count($fileFields)) {
			foreach($fileFields as $field) {
				$this->importFileField($page, $field, $a['data'][$field->name], $options);
			}
		}
		
		$pages->save($page, $options['saveOptions']);

		if($languages) $languages->unsetDefault();
		
		foreach($errors as $error) $page->error($error); 
		foreach($warnings as $warning) $page->warning($warning);
		
		return $page;
	}

	/**
	 * Import a files/images field and populate to given $page
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param array $data Export/sleep value of file field
	 * @param array $options
	 * 
	 */
	protected function importFileField(Page $page, Field $field, array $data, array $options = array()) {
		
		// Expected format of given $data argument: 
		// $data = [
		//      'file1.jpg' => [
		//          'url' => 'http://domain.com/site/assets/files/123/file1.jpg',
		//          'description' => 'file description',
		//          'tags' => 'file tags'
		//      ],
		//      'file2.png' => [ ... see above ... ],
		//      'file3.gif' => [ ... see above ... ],
		// ];
		
		// @todo method needs implementation
		
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
		
		$info = array(
			'exportable' => true,
			'reason' => '',
		);
		
		if($field->type instanceof FieldtypeFile) {
			// we will handle these
			return $info;
		}
	
		try {
			$schema = $field->type->getDatabaseSchema($field);
		} catch(\Exception $e) {
			$info['exportable'] = false;
			$info['reason'] = $e->getMessage();
			return $info;
		}

		if(!isset($schema['xtra']['all']) || $schema['xtra']['all'] !== true) {
			// this fieldtype is storing data outside of the DB or in other unknown tables
			// there's a good chance we won't be able to export/import this into an array
			// @todo check if fieldtype implements its own exportValue/importValue, and if
			// it does then allow the value to be exported
			$info['exportable'] = false;
			$info['reason'] = "Field '$field' cannot be exported because $field->type uses data outside table '$field->table'";
		}
		
		return $info;
	}

}
