<?php namespace ProcessWire;

/**
 * File management for PagesVersions
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */
class PagesVersionsFiles extends Wire {
	
	const dirPrefix = 'v'; // directory prefix for version files

	/**
	 * @var PagesVersions 
	 * 
	 */	
	protected $pagesVersions; 
	
	/**
	 * Are file hooks disabled?
	 *
	 * @var bool
	 *
	 */
	protected $disableFileHooks = false;

	/**
	 * Value for last mkdir() call
	 *
	 * @var string
	 *
	 */
	protected $lastMkdir = '';

	/**
	 * Construct
	 * 
	 */
	public function __construct(PagesVersions $pagesVersions) {
		$this->pagesVersions = $pagesVersions;
		parent::__construct();
		$pagesVersions->wire($this);
		$this->addHookAfter('PagefilesManager::path, PagefilesManager::url', $this, 'hookPagefilesManagerPath');
		$this->addHookAfter('Pages::savePageOrFieldReady', $this, 'hookPagesSaveReady');
	}
	
	/********************************************************************************
	 * API SUPPORT METHODS FOR FILES BY ENTIRE DIRECTORY
	 *
	 */

	/**
	 * Copy files for given $page into version directory
	 *
	 * @param Page $page
	 * @param int $version
	 * @return bool|int
	 *
	 */
	public function copyPageVersionFiles(Page $page, $version) {
		
		$qty = 0;
		
		if(!$page->hasFilesPath()) {
			// files not applicable
			
		} else if($this->useFilesByField($page)) {
			foreach($this->getFileFields($page) as $field) {
				$qty += $this->copyPageFieldVersionFiles($page, $field, $version);
			}
			
		} else {
			$files = $this->wire()->files;
			$filesManager = $page->filesManager();

			$sourcePath = $filesManager->path();
			$targetPath = $this->versionFilesPath($filesManager->___path(), $version);

			if($sourcePath === $targetPath) {
				// skipping copy 
			} else {
				$qty = $files->copy($sourcePath, $targetPath, ['recursive' => false]);
			}
		}

		return $qty;
	}

	/**
	 * Delete files for given version
	 *
	 * @param Page $page
	 * @param int $version
	 * @return bool
	 *
	 */
	public function deletePageVersionFiles(Page $page, $version) {
		if(!$page->hasFilesPath()) return true;
		$path = $this->versionFilesPath($page->filesManager()->___path(), $version);
		if(!is_dir($path)) return true;
		return $this->wire()->files->rmdir($path, true);
	}

	/**
	 * Restore files from version into live $page
	 *
	 * @param Page $page
	 * @param int $version
	 * @return int
	 *
	 */
	public function restorePageVersionFiles(Page $page, $version) {
		
		$qty = 0;
		
		if(!$page->hasFilesPath()) {
			// files not applicable
		} else if($this->useFilesByField($page)) {
			foreach($this->getFileFields($page) as $field) {
				$qty += $this->restorePageFieldVersionFiles($page, $field, $version);
			}
		} else {
			$filesManager = $page->filesManager();
			$livePath = $filesManager->___path();
			$versionPath = $this->versionFilesPath($livePath, $version);
			if(!is_dir($versionPath)) return 0;
			$this->disableFileHooks = true;
			$filesManager->emptyPath(false, false);
			$qty = $filesManager->importFiles($versionPath);
			$this->disableFileHooks = false;
		}
		
		return $qty;
	}
	
	/********************************************************************************
	 * API SUPPORT METHODS FOR FILES BY FIELD
	 *
	 */

	/**
	 * Copy files for given $page and field into version directory
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return int
	 *
	 */
	public function copyPageFieldVersionFiles(Page $page, Field $field, $version) {

		$fieldtype = $field->type;
		
		if(!$fieldtype instanceof FieldtypeHasFiles) return 0;
		if(!$page->hasFilesPath() || !$version) return 0;

		$files = $this->wire()->files;
		$filesManager = $page->filesManager();
		$pageVersion = $this->pagesVersions->pageVersionNumber($page);
		$livePath = $filesManager->___path();
		$sourcePath = $pageVersion ? $this->versionFilesPath($livePath, $pageVersion) : $livePath;
		$targetPath = $this->versionFilesPath($livePath, $version);
		$qty = 0;
		
		foreach($fieldtype->getFiles($page, $field) as $sourceFile) {
			$sourceFile = $sourcePath . basename($sourceFile);
			$targetFile = $targetPath . basename($sourceFile);
			if($sourceFile === $targetFile) continue;
			if($files->copy($sourceFile, $targetFile)) $qty++;
		}

		return $qty;
	}

	/**
	 * Delete files for given page and field version
	 * 
	 * @todo is this method even needed?
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return int
	 *
	 */
	protected function deletePageFieldVersionFiles(Page $page, Field $field, $version) {
		$fieldtype = $field->type;
		if(!$page->hasFilesPath() || !$version) return 0;
		if(!$fieldtype instanceof FieldtypeHasFiles) return 0;
		$page = $this->pageVersion($page, $version);
		$path = $this->versionFilesPath($page->filesManager()->___path(), $version);
		$files = $this->wire()->files;
		if(!is_dir($path)) return 0;
		$qty = 0;
		foreach($fieldtype->getFiles($page, $field) as $filename) {
			$filename = $path . basename($filename);
			if(!is_file($filename)) continue;
			if($files->unlink($filename)) $qty++;
		}
		return $qty;
	}

	/**
	 * Restore files for given field from version into live $page
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return int
	 *
	 */
	public function restorePageFieldVersionFiles(Page $page, Field $field, $version) {

		$fieldtype = $field->type;

		if(!$fieldtype instanceof FieldtypeHasFiles) return 0;
		if(!$page->hasFilesPath() || !$version) return 0;
		
		$v = $this->pagesVersions->pageVersionNumber($page);

		if($v == $version) {
			// page already has the version number we want to restore
			$versionPage = $page;
			$livePage = $this->pageVersion($page, 0);
		} else if($v) {
			// page is for some other version we do not want
			$livePage = $this->pageVersion($page, 0);
			$versionPage = $this->pageVersion($livePage, $version);
		} else {
			// page is live page and we also need version page
			$versionPage = $this->pageVersion($page, $version);
			$livePage = $page;
		}

		$files = $this->wire()->files;
		$filesManager = $livePage->filesManager();
		$livePath = $filesManager->___path();
		$versionPath = $this->versionFilesPath($livePath, $version);
		$qty = 0;

		if(!is_dir($versionPath)) return 0;

		// clear out live files for this field
		foreach($fieldtype->getFiles($livePage, $field) as $filename) {
			$files->unlink($filename, $livePath);
		}

		// copy version files to live path for this field
		foreach($fieldtype->getFiles($versionPage, $field) as $filename) {
			$basename = basename($filename);
			$sourceFile = $versionPath . $basename;
			$targetFile = $livePath . $basename;
			if($files->copy($sourceFile, $targetFile)) $qty++;
		}

		return $qty;
	}


	/********************************************************************************
	 * UTILITIES
	 *
	 */
	
	protected $fileFieldsCache = [];

	/**
	 * Get all fields that can support files
	 * 
	 * @param Page $page
	 * @param array $options
	 *  - `populated` (bool): Only return populated file fields with 1+ files in them? (default=false)
	 *  - `names` (array): Limit check to these field names or omit for all. (default=[])
	 * @return Field[] Returned fields array is indexed by field name
	 * 
	 */
	public function getFileFields(Page $page, array $options = []) {
		
		$defaults = [
			'populated' => false, 
			'names' => [],
		];
		
		$options = array_merge($defaults, $options);
		$fileFields = [];
		$cacheKey = ($options['populated'] ? "p$page" : "t$page->templates_id");
		
		if(isset($this->fileFieldsCache[$cacheKey]) && empty($options['names'])) {
			return $this->fileFieldsCache[$cacheKey];
		}
		
		foreach($page->template->fieldgroup as $field) {
			if(!$this->fieldSupportsFiles($field)) continue;
			$fieldtype = $field->type;
			if($options['populated'] && $fieldtype instanceof FieldtypeHasFiles) {
				if($fieldtype->hasFiles($page, $field)) $fileFields[$field->name] = $field;
			} else {
				$fileFields[$field->name] = $field;
			}
		}
	
		if(empty($options['names'])) $this->fileFieldsCache[$cacheKey] = $fileFields;
		
		return $fileFields;
	}

	/**
	 * Does given field support files?
	 * 
	 * @param Field|string|int $field
	 * @return bool
	 * 
	 */
	public function fieldSupportsFiles($field) {
		
		if(!$field instanceof Field) {
			$field = $this->wire()->fields->get($field);
			if(!$field) return false;
		}
		
		$fieldtype = $field->type;
		if($fieldtype instanceof FieldtypeHasFiles) return true;
		
		$typeName = $fieldtype->className();
		
		if($typeName === 'FieldtypeTable' || $typeName === 'FieldtypeCombo') {
			// Table or Combo version prior to one that implemented FieldtypeHasFiles
			$version = $this->wire()->modules->getModuleInfoProperty($typeName, 'versionStr');
			if($typeName === 'FieldtypeCombo') return version_compare($version, '0.0.9', '>=');
			return version_compare($version, '0.2.3', '>=');
		}
		
		return false;
	}

	/**
	 * Copy/restore files individually by field for given page?
	 *
	 * - Return true if files should be copied/restored individually by field.
	 * - Returns false if entire page directory should be copied/restored at once.
	 *
	 * @param Page $page
	 * @param Field[]|string[] $names Optionally limit check to these fields
	 * @return bool
	 *
	 */
	public function useFilesByField(Page $page, array $names = []) {
		
		$fileFields = $this->getFileFields($page);
		if(!count($fileFields)) return false;
		
		$useFilesByField = true;
		
		if(count($names)) {
			$a = [];
			foreach($names as $name) $a["$name"] = $name;
			$names = $a;
		} else {
			$names = null;
		}
		
		foreach($fileFields as $field) {
			if($names && !isset($names[$field->name])) continue;
			$fieldtype = $field->type;
			if($fieldtype instanceof FieldtypeHasFiles) {
				// supports individual files
			} else {
				// version of table or combo that doesn't implement FieldtypeHasFiles
				$useFilesByField = false;
				break;
			}
		}
		
		return $useFilesByField;
	}

	/**
	 * Ensure that given page is given version, and return version page if it isn't already
	 *
	 * @param Page $page
	 * @param int $version Page version or 0 to get live page
	 * @return NullPage|Page
	 *
	 */
	protected function pageVersion(Page $page, $version) {
		$v = $this->pagesVersions->pageVersionNumber($page);
		if($v == $version) return $page;
		if($version === 0) return $this->wire()->pages->getFresh($page->id);
		$pageVersion = $this->pagesVersions->getPageVersion($page, $version);
		if(!$pageVersion->id) throw new WireException("Cannot find page $page version $version");
		return $pageVersion;
	}


	/**
	 * Update given files path for version
	 *
	 * #pw-internal
	 *
	 * @param string $path
	 * @param int $version
	 * @return string
	 *
	 */
	public function versionFilesPath($path, $version) {
		if($path instanceof Page) $path = $path->filesManager()->___path();
		$version = (int) $version;
		return $path . self::dirPrefix . $version . '/';
	}

	/**
	 * Get the total size of all files in given version
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @param int $version
	 * @return int
	 * 
	 */
	public function getTotalVersionSize(Page $page, $version = 0) {
		if(!$page->hasFilesPath()) return 0;
		if(!$version) $version = $this->pagesVersions->pageVersionNumber($page);
		if($version) {
			$path = $this->versionFilesPath($page, $version);
		} else {
			$path = $page->filesPath();
		}
		$size = 0;
		foreach(new \DirectoryIterator($path) as $file) {
			if($file->isDir() || $file->isDot()) continue;
			$size += $file->getSize();
		}
		return $size;
	}

	/********************************************************************************
	 * HOOKS
	 *
	 */

	/**
	 * Hook to PagefilesManager::path to update for version directories
	 *
	 * #pw-internal
	 *
	 * @param HookEvent $event
	 *
	 */
	public function hookPagefilesManagerPath(HookEvent $event) {
		if($this->disableFileHooks) return;
		$manager = $event->object; /** @var PagefilesManager $manager */
		$page = $manager->page;
		if(!$page->get(PagesVersions::pageProperty)) return;
		$version = $this->pagesVersions->pageVersionNumber($page);
		if(!$version) return;
		$versionDir = self::dirPrefix . "$version/";
		$event->return .= $versionDir;
		if($event->method == 'path') {
			$path = $event->return;
			if($this->lastMkdir != $path && !is_dir($path)) {
				$this->wire()->files->mkdir($path, true);
			}
		}
	}
	
	/**
	 * Hook Pages::saveReady to restore version files when $action === 'restore'
	 *
	 * #pw-internal
	 *
	 * @param HookEvent $event
	 *
	 */
	public function hookPagesSaveReady(HookEvent $event) {
		$page = $event->arguments(0);
		$info = $this->pagesVersions->pageVersionInfo($page);
		if($info->version && $info->getAction() === PageVersionInfo::actionRestore) {
			$this->restorePageVersionFiles($page, $info->version);
		}
	}

	/**
	 * Hook before Pages::save or Pages::saveField to prevent save on a version page unless $action === 'restore'
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 *
	 */
	public function hookBeforePagesSave(Page $page) {
		if(PagefilesManager::hasPath($page)) {
			// ensures files flagged for deletion get deleted
			// this hook doesn't get called by $pages since we replaced the call
			$page->filesManager->save();
		}
	}

}
