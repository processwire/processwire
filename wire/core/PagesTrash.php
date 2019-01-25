<?php namespace ProcessWire;

/**
 * ProcessWire Pages Trash
 *
 * Implements page trash/restore/empty methods of the $pages API variable
 *
 * ProcessWire 3.x, Copyright 2018 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesTrash extends Wire {

	/**
	 * @var Pages
	 * 
	 */
	protected $pages;

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 * 
	 */
	public function __construct(Pages $pages) {
		$this->pages = $pages;
	}
	
	/**
	 * Move a page to the trash
	 *
	 * If you have already set the parent to somewhere in the trash, then this method won't attempt to set it again.
	 *
	 * @param Page $page
	 * @param bool $save Set to false if you will perform the save() call, as is the case when called from the Pages::save() method.
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function trash(Page $page, $save = true) {
		
		if(!$this->pages->isDeleteable($page) || $page->template->noTrash) {
			throw new WireException("This page (id=$page->id) may not be placed in the trash");
		}
		
		if(!$trash = $this->pages->get($this->config->trashPageID)) {
			throw new WireException("Unable to load trash page defined by config::trashPageID");
		}
		
		$page->addStatus(Page::statusTrash);
		
		if(!$page->parent->isTrash()) {
			$parentPrevious = $page->parent;
			$page->parent = $trash;
		} else if($page->parentPrevious && $page->parentPrevious->id != $page->parent->id) {
			$parentPrevious = $page->parentPrevious;
		} else {
			$parentPrevious = null;
		}
		
		$nameInfo = $this->parseTrashPageName($page->name);
		
		if(!$nameInfo || $nameInfo['id'] != $page->id) {
			// make the name unique when in trash, to avoid namespace collision and maintain parent restore info
			$name = $page->id;
			if($parentPrevious && $parentPrevious->id) {
				$name .= "." . $parentPrevious->id;
				$name .= "." . $page->sort;
			}
			$page->name = ($name . "_" . $page->name);
		
			// do the same for other languages, if present
			$languages = $this->wire('languages');
			if($languages && $this->wire('modules')->isInstalled('LanguageSupportPageNames')) {
				foreach($languages as $language) {
					if($language->isDefault()) continue;
					$langName = $page->get("name$language->id");
					if(!strlen($langName)) continue; 
					$page->set("name$language->id", $name . "_" . $langName);
				}
			}
		}
		
		if($save) $this->pages->save($page);
		$this->pages->editor()->savePageStatus($page->id, Page::statusTrash, true, false);
		if($save) $this->pages->trashed($page);
		$this->pages->debugLog('trash', $page, true);
		
		return true;
	}

	/**
	 * Restore a page from the trash back to a non-trash state
	 *
	 * Note that this method assumes already have set a new parent, but have not yet saved.
	 * If you do not set a new parent, then it will restore to the original parent, when possible.
	 *
	 * @param Page $page
	 * @param bool $save Set to false if you only want to prep the page for restore (i.e. being saved elsewhere)
	 * @return bool
	 *
	 */
	public function restore(Page $page, $save = true) {

		$info = $this->getRestoreInfo($page, true);
		if(!$info['restorable']) return false;
		
		if($page->parent->isTrash()) {
			if($save) $page->save();
		} else {
			$page->removeStatus(Page::statusTrash);
			if($save) $page->save();
			$this->pages->editor()->savePageStatus($page->id, Page::statusTrash, true, true);
			if($save) $this->pages->restored($page);
			$this->pages->debugLog('restore', $page, true);
		}
		
		return true;
	}
	
	/**
	 * Get info needed to restore a Page that is in the trash
	 *
	 * Returns array with the following info:
	 *  - `restorable` (bool): Is the page restorable to a previous known/existing parent?
	 *  - `notes` (array): Any additional notes to explain restore info (like reason why not restorable, or why name changed, etc.)
	 *  - `parent` (Page|NullPage): Parent page that it should restore to
	 *  - `parent_id` (int): ID of parent page that it should restore to
	 *  - `sort` (int): Sort order that should be restored to page
	 *  - `name` (string): Name that should be restored to page’s “name” property.
	 *  - `namePrevious` (string): Previous name, if we had to modify the original name to make it restorable. 
	 *  - `name{id}` (string): Name that should be restored  to language where {id} is language ID (if appliable).
	 *
	 * @param Page $page Page to restore
	 * @param bool $populateToPage Populate this information to given page? (default=false)
	 * @return array
	 *
	 */
	public function getRestoreInfo(Page $page, $populateToPage = false) {

		$info = array(
			'restorable' => false,
			'notes' => array(), 
			'parent' => $this->pages->newNullPage(),
			'parent_id' => 0,
			'sort' => 0,
			'name' => '',
			'namePrevious' => '',
		);
		
		/** @var Languages|array $languages */
		$languages = $this->wire('languages');
		if(!$languages || !$this->wire('modules')->isInstalled('LanguageSupportPageNames')) $languages = array();
		
		// initialize name properties in $info for each language 
		foreach($languages as $language) {
			$info["name$language->id"] = '';
		}
		
		$result = $this->parseTrashPageName($page->name);

		if(!$result || $result['id'] !== $page->id) {
			// page does not have restore info
			$info['notes'][] = 'Page name does not contain restore information';
			return $info;
		}
	
		$name = $result['name'];
		$trashPrefix = $result['prefix']; // pageID.parentID.sort_ prefix for testing other language names later
		$newParent = null;
		$parentID = $result['parent_id'];
		$sort = $result['sort'];

		if($parentID && $parentID != $page->id) { 
			if($page->rootParent()->isTrash()) {
				// no new parent was defined, so use the one in the page name
				$newParent = $this->pages->get($parentID);
				if(!$newParent->id) {
					$newParent = null;
					$info['notes'][] = 'Original parent no longer exists';
				}
			} else {
				$info['notes'][] = 'Page root parent is not trash';
			}
			
		} else if($parentID) {
			$info['notes'][] = "Invalid parent ID: $parentID";
			
		} else {
			// page was likely trashed a long time ago, before this info was stored
			$info['notes'][] = 'Page name does not contain previous parent or sort info';
		}

		$info['parent'] = $newParent ? $newParent : $this->pages->newNullPage();
		$info['parent_id'] = $parentID;
		$info['sort'] = $sort;
	
		// if we have no new parent available we can exit now
		if(!$newParent) {
			$info['notes'][] = 'Unable to determine parent to restore to';
			return $info;
		}	

		// check if there is already a page at the restore location with the same name
		$namePrevious = $name;
		$name = $this->pages->names()->uniquePageName($name, $page, array('parent' => $newParent));
		
		if($name !== $namePrevious) {
			$info['notes'][] = "Name changed from '$namePrevious' to '$name' to be unique in new parent";
			$info['namePrevious'] = $namePrevious;
		}
		
		$info['name'] = $name;
		$info['restorable'] = true;
		
		if($populateToPage) {
			$page->name = $name;
			$page->parent = $newParent; 
			$page->sort = $sort;
		}

		// do the same for other languages, when applicable
		foreach($languages as $language) {
			/** @var Language $language */
			if($language->isDefault()) continue;
			$langName = $page->get("name$language->id");
			if(!strlen($langName)) continue;
			if(strpos($langName, $trashPrefix) === 0) {
				list(,$langName) = explode('_', $langName);
			}
			$langNamePrevious = $langName;
			$langName = $this->pages->names()->uniquePageName($langName, $page, array(
				'parent' => $newParent, 
				'language' => $language
			));
			if($populateToPage) $page->set("name$language->id", $langName);
			$info["name$language->id"] = $langName;
			if($langName !== $langNamePrevious) {
				$info['notes'][] = $language->get('title|name') . ' ' . 
					"name changed from '$langNamePrevious' to '$langName' to be unique in new parent";
			}
		}
		
		return $info;
	}

	/**
	 * Parse a trashed page name into an array of its components
	 * 
	 * @param string $name
	 * @return array|bool Returns array of info if name is a trash/restore name, or boolean false if not
	 * 
	 */
	public function parseTrashPageName($name) {
		
		$info = array(
			'id' => 0,
			'parent_id' => 0, 
			'sort' => 0, 
			'name' => $name,
			'prefix' => '', 
			'note' => '', 
		);
		
		// match "pageID.parentID.sort_name" in page name (1).(2.2)_3
		if(!preg_match('/^(\d+)((?:\.\d+\.\d+)?)_(.+)$/', $name, $matches)) return false;
		
		$info['id'] = (int) $matches[1];
		$info['name'] = $matches[3];
		
		if($matches[2]) {
			// matches[2] contains ".parentID.sort"
			list(, $parentID, $sort) = explode('.', $matches[2]);
			$info['parent_id'] = (int) $parentID;
			$info['sort'] = (int) $sort;
		} else {
			// page was likely trashed a long time ago, before this info was stored
			$info['note'] = 'Page name does not contain previous parent or sort info';
		}
		
		// pageID.parentID.sort_ prefix that can be used with other language names
		$info['prefix'] = $matches[1] . $matches[2] . '_'; 
		
		return $info;
	}

	/**
	 * Delete all pages in the trash
	 *
	 * Populates error notices when there are errors deleting specific pages.
	 *
	 * @param array $options
	 *  - `chunkSize` (int): Pages will be deleted in chunks of this many pages per chunk (default=100).
	 *  - `chunkTimeLimit` (int): Maximum seconds allowed to process deletion of each chunk (default=600). 
	 *  - `chunkLimit' (int): Maximum chunks to process in an emptyTrash() call (default=1000);
	 *  - `pageLimit` (int): Maximum pages to delete per emptyTrash() call (default=0, no limit).
	 *  - `timeLimit` (int): Maximum time (in seconds) to allow for trash empty (default=3600). 
	 *  - `pass2` (bool): Perform a secondary pass using alternate method as a backup? (default=true)
	 *     Note: pass2 is always disabled when a pageLimit is in use or timeLimit has been exceeded. 
	 *  - `verbose` (bool): Return verbose array of information about the trash empty process? For debug/dev purposes (default=false)
	 * @return int|array Returns integer (default) or array in verbose mode. 
	 *  - By default, returns total number of pages deleted from trash. This number is negative or 0 if not 
	 *    all pages could be deleted and error notices may be present.
	 *  - Returns associative array with verbose information if verbose option is chosen. 
	 *
	 */
	public function emptyTrash(array $options = array()) {

		$defaults = array(
			'chunkSize' => 100,
			'chunkTimeLimit' => 600, 
			'chunkLimit' => 100, 
			'pageLimit' => 0, 
			'timeLimit' => 3600, 
			'pass2' => true, 
			'verbose' => false, 
		);
		
		$options = array_merge($defaults, $options);
		$trashPage = $this->getTrashPage();
		$masterSelector = "include=all, children.count=0, status=" . Page::statusTrash;
		$totalDeleted = 0;
		$lastTotalInTrash = 0;
		$chunkCnt = 0;
		$errorCnt = 0;
		$nonTrashIDs = array(); // page IDs that had trash status but did not have trash parent
		$result = array();
		$timer = $options['verbose'] ? Debug::timer() : null;
		$startTime = time();
		$stopTime = $options['timeLimit'] ? $startTime + $options['timeLimit'] : false;
		$stopNow = false;
		$database = $this->wire('database');
		$useTransaction = $database->supportsTransaction();
		$options['stopTime'] = $stopTime; // for pass2
		$timeExpired = false;
		$onlyDirectChildren = true; // limit to direct children at first
		
		if($options['chunkTimeLimit'] > $options['timeLimit']) {
			$options['chunkTimeLimit'] = $options['timeLimit'];
		}
		
		// Empty trash pass1:
		// Operates by finding pages in trash using Page::statusTrash that have no children
		do {
			$selector = $masterSelector;
			
			if($options['chunkTimeLimit']) {
				set_time_limit($options['chunkTimeLimit']);
			}
			
			if(count($nonTrashIDs)) {
				$selector .= ", id!=" . implode('|', $nonTrashIDs);
			}

			if($onlyDirectChildren) {
				// limit to direct children of trash page that themselves have no children
				$selector .= ", parent_id=$trashPage->id";
			} else {
				$totalInTrash = $this->pages->count($selector);
				if(!$totalInTrash || $totalInTrash == $lastTotalInTrash) break;
				$lastTotalInTrash = $totalInTrash;
			}
			
			if($options['chunkSize'] > 0) {
				$selector .= ", limit=$options[chunkSize]";
			}
			
			$items = $this->pages->find($selector);
			$numItems = $items->count();
			$totalItems = $items->getTotal();
			$numDeleted = 0;
			
			if($useTransaction) $database->beginTransaction();
			
			foreach($items as $item) {
			
				// determine if any limits have been reached
				if($stopTime && time() > $stopTime) {
					$stopNow = true;
					$timeExpired = true;
				}
				if($options['pageLimit'] && $totalDeleted >= $options['pageLimit']) {
					$stopNow = true;
				}
				if($stopNow) break;
				
				// if page does not have trash as a parent, then this is a page with trash status
				// that is somewhere else in the page tree (not likely)
				if(!$onlyDirectChildren && $item->rootParent()->id !== $trashPage->id) {
					$nonTrashIDs[$item->id] = $item->id;
					$errorCnt++;
					continue;
				}
			
				// delete the page
				try {
					$numDeleted += $this->pages->delete($item, true);
				} catch(\Exception $e) {
					$this->error($e->getMessage());
					$errorCnt++;
				}
			}

			$totalDeleted += $numDeleted;
			if($useTransaction) $database->commit();
			$this->pages->uncacheAll();
			
			if($options['chunkLimit'] && $chunkCnt >= $options['chunkLimit']) {
				// if chunk limit exceeded then stop now
				$stopNow = true;
				
			} else if($onlyDirectChildren) {
				// move past direct children next if all were loaded in this chunk
				if($totalItems === $numItems || !$numDeleted) $onlyDirectChildren = false;
				
			} else if(!$numDeleted) {
				// if no items deleted (and we're beyond direct children), we should stop now
				$stopNow = true;
			}
			
			if(!$stopNow) $chunkCnt++;
			
		} while(!$stopNow);
		
		// if recording verbose info, populate it for pass1 now
		if($options['verbose']) {
			$result['pass1_cnt'] = $chunkCnt;
			$result['pass1_numDeleted'] = $totalDeleted;
			$result['pass1_numErrors'] = $errorCnt;
			$result['pass1_elapsedTime'] = Debug::timer($timer);
			$result['pass1_timeExpired'] = $timeExpired;
		}
		
		if(count($nonTrashIDs)) {
			// remove trash status from the pages that should not have it
			$this->pages->editor()->savePageStatus($nonTrashIDs, Page::statusTrash, false, true);
		}

		// Empty trash pass2:
		// Operates by finding pages that are children of the Trash and performing recursive delete upon them
		if($options['pass2'] && !$stopNow && !$options['pageLimit']) {
			if($useTransaction) $database->beginTransaction();
			$totalDeleted += $this->emptyTrashPass2($options, $result);
			if($useTransaction) $database->commit();
		}
			
		if($totalDeleted || $options['verbose']) {
			$numTrashChildren = $this->wire('pages')->trasher()->getTrashTotal();
			// return a negative number if pages still remain in trash
			if($numTrashChildren && !$options['verbose']) $totalDeleted = $totalDeleted * -1;
		} else {
			$numTrashChildren = 0;
		}
		
		if($options['verbose']) {
			$result['startTime'] = $startTime;
			$result['elapsedTime'] = Debug::timer($timer);
			$result['pagesPerSecond'] = $totalDeleted ? round($totalDeleted / $result['elapsedTime'], 2) : 0;
			$result['timeExpired'] = !empty($result['pass1_timeExpired']) || !empty($result['pass2_timeExpired']);
			$result['numDeleted'] = $totalDeleted;
			$result['numRemain'] = $numTrashChildren;
			$result['numErrors'] = $errorCnt;
			$result['numMispaced'] = count($nonTrashIDs);
			$result['idsMisplaced'] = $nonTrashIDs;
			$result['options'] = $options;
			return $result;
		}

		return $totalDeleted;
	}

	/**
	 * Secondary pass for trash deletion
	 * 
	 * This works by finding the children of the trash page and performing a recursive delete on them.
	 * 
	 * @param array $options Options passed to emptyTrash() method
	 * @param array $result Verbose array, modified directly
	 * @return int
	 * 
	 */
	protected function emptyTrashPass2(array $options, &$result) {
		
		if($options['chunkTimeLimit']) {
			set_time_limit($options['chunkTimeLimit']);
		}

		$timer = $options['verbose'] ? Debug::timer() : null;
		$numErrors = 0;
		$numDeleted = 0;
		$timeExpired = false;
		$trashPage = $this->getTrashPage();
		$trashPages = $trashPage->children("include=all");

		foreach($trashPages as $t) {
			try {
				// perform recursive delete
				$numDeleted += $this->pages->delete($t, true);
			} catch(\Exception $e) {
				$this->error($e->getMessage());
				$numErrors++;
			}
			if($options['stopTime'] && time() > $options['stopTime']) {
				$timeExpired = true;
				break;
			}
		}

		$this->pages->uncacheAll();

		if($options['verbose']) {
			$result['pass2_numDeleted'] = $numDeleted;
			$result['pass2_numErrors'] = $numErrors;
			$result['pass2_elapsedTime'] = Debug::timer($timer);
			$result['pass2_timeExpired'] = $timeExpired;
		}

		return $numDeleted;
	}

	/**
	 * Get total number of pages in trash
	 * 
	 * @return int
	 * 
	 */
	public function getTrashTotal() {
		return $this->pages->count("include=all, status=" . Page::statusTrash);
	}

	/**
	 * Return the root parent trash page
	 * 
	 * @return Page
	 * @throws WireException if trash page cannot be located (highly unlikely)
	 * 
	 */
	public function getTrashPage() {
		$trashPageID = $this->wire('config')->trashPageID;
		$trashPage = $this->pages->get((int) $trashPageID);
		if(!$trashPage->id || $trashPage->id != $trashPageID) {
			throw new WireException("Cannot find trash page $trashPageID");
		}
		return $trashPage;
	}

}