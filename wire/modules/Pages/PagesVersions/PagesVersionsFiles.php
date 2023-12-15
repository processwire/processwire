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
	 * API SUPPORT METHODS
	 *
	 */

	/**
	 * Copy files for given $page into version directory
	 *
	 * @param Page $page
	 * @param $version
	 * @return bool|int
	 *
	 */
	public function copyPageVersionFiles(Page $page, $version) {

		if(!$page->hasFilesPath()) return 0;

		$files = $this->wire()->files;
		$filesManager = $page->filesManager();

		$sourcePath = $filesManager->path();
		$targetPath = $this->versionFilesPath($filesManager->___path(), $version);

		if($sourcePath === $targetPath) {
			// skipping copy 
			$qty = 0;
		} else {
			$qty = $files->copy($sourcePath, $targetPath, [ 'recursive' => false ]);
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
		if(!$page->hasFilesPath()) return 0;
		$filesManager = $page->filesManager();
		$livePath = $filesManager->___path();
		$versionPath = $this->versionFilesPath($livePath, $version);
		if(!is_dir($versionPath)) return 0;
		$this->disableFileHooks = true;
		$filesManager->emptyPath(false, false);
		$qty = $filesManager->importFiles($versionPath);
		$this->disableFileHooks = false;
		return $qty;
	}
	
	/********************************************************************************
	 * UTILITIES
	 *
	 */
	
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
