<?php namespace ProcessWire;

/**
 * PagesVersions
 * 
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 * 
 * #pw-summary Provides a version control API for pages in ProcessWire.
 * #pw-var $pagesVersions
 *
 * #pw-body =
 * ~~~~~
 * // Note: API provided by $pagesVersions API variable
 * // present with PagesVersions module is installed.
 * 
 * // Get page and add a new version of it
 * $page = $pages->get(1234);
 * $page->title = 'New title';
 * $version = $pagesVersions->addPageVersion($page);
 * echo $version; // i.e. "2"
 * 
 * // Get version 2 of a page
 * $pageV2 = $pagesVersions->getPageVersion($page, 2);
 * 
 * // Update a version of a page
 * $pageV2->title = "Updated title";
 * $pagesVersions->savePageVersion($pageV2); 
 * 
 * // Restore version to live page
 * $pagesVersions->restorePageVersion($pageV2);
 * 
 * // Delete page version
 * $pagesVersions->deletePageVersion($pageV2);
 * ~~~~~
 * #pw-body
 * 
 * HOOKABLE METHODS
 * ----------------
 * @method bool allowPageVersions(Page $page)
 * @method bool useTempVersionToRestore(Page $page) #pw-internal
 * 
 * @todo test change of template in version
 * 
 */
class PagesVersions extends Wire implements Module {
	
	/**
	 * Module information
	 * 
	 * #pw-internal
	 *
	 * @return array
	 *
	 */
	public static function getModuleInfo() {
		return [
			'title' => 'Pages Versions',
			'summary' => 'Provides a version control API for pages in ProcessWire.',
			'version' => 2,
			'icon' => self::iconName, 
			'autoload' => true, 
			'singular' => true, 
			'author' => 'Ryan Cramer',
		];
	}

	const versionsTable = 'version_pages';
	const valuesTable = 'version_pages_fields';
	const pageProperty = '_version'; // property for PageVersionInfo instance on page
	const iconName = 'code-fork';
	
	/********************************************************************************
	 * PUBLIC API
	 *
	 */

	/**
	 * Get requested page version in a copy of given page
	 * 
	 * ~~~~~
	 * $page = $pages->get(1234); 
	 * $pageV2 = $pagesVersions->getPageVersion($page, 2);
	 * ~~~~~
	 * 
	 * #pw-group-getting
	 *
	 * @param Page $page Page that version is for
	 * @param int $version Version number to get
	 * @param array $options
	 *  - `names` (array): Optionally load only these field/property names from version.
	 * @return Page|NullPage 
	 *  - Returned page is a clone/copy of the given page updated for version data.
	 *  - Returns a `NullPage` if requested version is not found or not allowed.
	 *
	 */
	public function getPageVersion(Page $page, $version, array $options = []) {
		if(!$this->allowPageVersions($page)) return new NullPage();
		if($this->pageVersionNumber($page)) {
			$page = $this->wire()->pages->getFresh($page->id);
		} else {
			$page = clone $page;
		}
		$page->setTrackChanges(true);
		return ($this->loadPageVersion($page, $version, $options) ? $page : new NullPage());
	}
	
	/**
	 * Load and populate version data to given page
	 *
	 * This is similar to the `getPageVersion()` method except that it populates
	 * the given `$page` rather than populating and returning a cloned copy of it.
	 * 
	 * #pw-group-getting
	 *
	 * @param Page $page
	 * @param int|string|PageVersionInfo $version
	 * @param array $options
	 *  - `names` (array): Optionally load only these field/property names from version.
	 * @return bool True if version data was available and populated, false if not
	 *
	 */
	public function loadPageVersion(Page $page, $version, array $options = []) {

		$defaults = [
			'names' => [],
		];

		$database = $this->wire()->database;
		$table = self::versionsTable;
		$options = array_merge($defaults, $options);
		$partial = count($options['names']) > 0;
		$version = $this->pageVersionNumber($page, $version);
		$of = $page->of();
		
		$sql =
			"SELECT description, data, created, modified, " . 
			"created_users_id, modified_users_id " .
			"FROM $table " .
			"WHERE version=:version AND pages_id=:pages_id";

		$query = $database->prepare($sql);
		$query->bindValue(':version', $version, \PDO::PARAM_INT);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();

		$hasRow = $query->rowCount() > 0;
		$row = $hasRow ? $query->fetch(\PDO::FETCH_ASSOC) : null;

		$query->closeCursor();

		if($row === null) return false;

		$data = json_decode($row['data'], true);

		$info = new PageVersionInfo([
			'version' => (int) $version,
			'created' => $row['created'],
			'modified' => $row['modified'],
			'created_users_id' => (int) $row['created_users_id'],
			'modified_users_id' => (int) $row['modified_users_id'],
			'description' => (string) $row['description'], 
		]);

		if($of) $page->of(false);

		$info->setPage($page);
		$page->setQuietly(self::pageProperty, $info);

		if(is_array($data)) {
			foreach($data as $name => $value) {
				if($partial && !in_array($name, $options['names'])) continue;
				$page->set($name, $value);
			}
		}

		foreach($page->template->fieldgroup as $field) {
			/** @var Field $field */
			if($partial && !in_array($field->name, $options['names'])) continue;
			
			$allow = $this->allowFieldVersions($field);
			if(!$allow) continue;
			
			if($allow instanceof FieldtypeDoesVersions) {
				$value = $allow->getPageFieldVersion($page, $field, $version);
			} else {
				$value = $this->getPageFieldVersion($page, $field, $version);
			}
			
			if($value === null) {
				// value is not present in version
			} else {
				$page->set($field->name, $value);
			}
		}
		
		if($of) $page->of(true);

		return true;
	}


	/**
	 * Get all versions for given page
	 * 
	 * ~~~~~
	 * $page = $pages->get(1234);
	 * $versions = $pagesVersions->getPageVersions($page);
	 * foreach($versions as $p) {
	 *   echo $p->get('_version')->version; // i.e. 2, 3, 4, etc. 
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-getting
	 *
	 * @param Page $page
	 * @param array $options 
	 *  - `getInfo` (bool): Specify true to instead get PageVersionInfo objects (default=false)
	 *  - `sort` (string): Sort by property, one of: 'created', '-created', 'version', '-version' (default='-created')
	 *  - `version` (array): Limit to this version number, for internal use (default=0) 
	 * @return PageVersionInfo[]|Page[] 
	 *  - Returns Array of `Page` objects or array of `PageVersionInfo` objects if `getInfo` requested. 
	 *  - When returning pages, version info is in `$page->_version` value of each page, 
	 *    which is a `PageVersionInfo` object.
	 * @throws WireException
	 *
	 */
	public function getPageVersions(Page $page, array $options = []) {
		
		$defaults = [
			'getInfo' => false, 
			'sort' => '-created',
			'version' => 0, 
		];
		
		$sorts = [
			'created' => 'created ASC',
			'-created' => 'created DESC',
			'modified' => 'modified ASC',
			'-modified' => 'modified DESC',
			'version' => 'version ASC', 
			'-version' => 'version DESC',
		];
	
		$options = array_merge($defaults, $options);
		$database = $this->wire()->database;
		$table = self::versionsTable;
		$rows = [];
		
		if(!$this->allowPageVersions($page)) return [];
		
		if(!isset($sorts[$options['sort']])) $options['sort'] = $defaults['sort'];

		$sql =
			"SELECT version, description, created, modified, " . 
			"created_users_id, modified_users_id, data " . 
			"FROM $table " .
			"WHERE pages_id=:pages_id " . ($options['version'] ? "AND version=:version " : "") . 
			($options['version'] ? "LIMIT 1" : "ORDER BY " . $sorts[$options['sort']]);

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		if($options['version']) $query->bindValue(':version', (int) $options['version'], \PDO::PARAM_INT);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$properties = json_decode($row['data'], true);
			unset($row['data']); 
			$info = new PageVersionInfo($row);
			$info->set('pages_id', $page->id);
			$info->set('properties', $properties);
			$rows[] = $this->wire($info);
		}

		$query->closeCursor();

		if($options['getInfo']) return $rows;
		
		if($page->get(self::pageProperty)) {
			$page = $this->wire()->pages->getFresh($page->id);
		}

		$pageVersions = [];

		foreach($rows as $info) {
			/** @var PageVersionInfo $info */
			$pageCopy = clone $page;
			$info->setPage($pageCopy);
			$pageCopy->setQuietly(self::pageProperty, $info);
			if($this->loadPageVersion($pageCopy, $info->version)) {
				$pageVersions[$info->version] = $pageCopy;
			}
		}

		return $pageVersions;
	}

	/**
	 * Get info for given page and version
	 * 
	 * ~~~~~
	 * // get info for version 2
	 * $info = $pagesVersions->getPageVersionInfo($page, 2);
	 * if($info) {
	 *   echo "Version: $info->version <br />";
	 *   echo "Created: $info->createdStr by {$info->createdUser->name} <br />";
	 *   echo "Description: $info->descriptionHtml";
	 * } else {
	 *   echo "Version does not exist";
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-info
	 *
	 * @param Page $page
	 * @param int $version
	 * @return PageVersionInfo|null
	 * 
	 */
	public function getPageVersionInfo(Page $page, $version) {
		$options = [
			'getInfo' => true, 
			'version' => $version,
		];
		$a = $this->getPageVersions($page, $options);
		return count($a) ? reset($a) : null;
	}

	/**
	 * Get just PageVersionInfo objects for all versions of given page
	 * 
	 * This is the same as using the getPageVersions() method with the `getInfo` option.
	 * 
	 * #pw-group-info
	 * 
	 * ~~~~~
	 * $page = $pages->get(1234);
	 * $infos = $pagesVersions->getPageVersionInfos($page); 
	 * foreach($infos as $info) {
	 *   echo "<li>$info->version: $descriptionHtml</li>"; // i.e. "2: Hello world"
	 * }
	 * ~~~~~
	 * 
	 * #pw-group-getting
	 * 
	 * @param Page $page
	 * @param array $options
	 *  - `sort`: Sort by property, one of: 'created', '-created', 'version', '-version' (default='-created')
	 * @return PageVersionInfo[]
	 * 
	 */
	public function getPageVersionInfos(Page $page, array $options = []) {
		$options['getInfo'] = true;
		return $this->getPageVersions($page, $options);
	}

	/**
	 * Get all pages that have 1 or more versions available
	 * 
	 * #pw-group-getting
	 *
	 * @return PageArray
	 *
	 */
	public function getAllPagesWithVersions() {
		$table = self::versionsTable;
		$sql = "SELECT DISTINCT(pages_id) FROM $table";
		$query = $this->wire()->database->prepare($sql);
		$query->execute();
		$ids = [];
		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			$ids[] = (int) $row[0];
		}
		$query->closeCursor();
		return $this->wire()->pages->getByIDs($ids);
	}

	/**
	 * Does page have the given version?
	 * 
	 * #pw-group-info
	 * 
	 * @param Page $page
	 * @param int|string|PageVersionInfo $version Version number or omit to return quantity of versions
	 * @return bool|int 
	 *  - Returns boolean true or false if a non-empty $version argument was specified. 
	 *  - Returns integer with quantity of versions of no $version was specified. 
	 * 
	 */
	public function hasPageVersion(Page $page, $version = 0) {
		$database = $this->wire()->database();
		$table = self::versionsTable;
		if(empty($version)) {
			return $this->hasPageVersions($page);
		} else if($version instanceof PageVersionInfo) {
			$version = $version->version;
		}
		if(ctype_digit("$version")) {
			$sql = "SELECT version FROM $table WHERE pages_id=:pages_id AND version=:version";
			$query = $database->prepare($sql);
			$query->bindValue(':version', (int) $version, \PDO::PARAM_INT);
		} else {
			// find by name
			$sql = "SELECT version FROM $table WHERE pages_id=:pages_id AND name=:name";
			$query = $database->prepare($sql);
			$query->bindValue(':name', $version);
		}
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$n = $query->rowCount() ? (int) $query->fetchColumn() : 0; 
		$query->closeCursor();
		return $n > 0;
	}

	/**
	 * Return quantity of versions available for given page
	 * 
	 * This is the same as calling the `hasPageVersion()` method 
	 * with $version argument omitted.
	 * 
	 * #pw-group-info
	 * 
	 * @param Page $page
	 * @return int
	 * 
	 */
	public function hasPageVersions(Page $page) {
		$database = $this->wire()->database;
		$table = self::versionsTable;
		$sql = "SELECT COUNT(*) FROM $table WHERE pages_id=:pages_id";
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$qty = (int) $query->fetchColumn();
		$query->closeCursor();
		return $qty;
	}

	/**
	 * Add a new page version and return the added version number
	 * 
	 * ~~~~~
	 * $page = $pages->get(1234);
	 * $version = $pagesVersions->addPageVersion($page); 
	 * echo "Added version $version for page $page";
	 * ~~~~~
	 * 
	 * #pw-group-saving
	 * 
	 * @param Page $page
	 * @param array $options
	 *  - `description` (string): Optional text description for version.
	 *  - `names` (array): Names of fields/properties to include in the version or omit for all.
	 * @return int Version number or 0 if no version created
	 * @throws WireException|\PDOException
	 * 
	 */
	public function addPageVersion(Page $page, array $options = []) {
		
		$defaults = [
			'description' => '', 
			'names' => [], 
			'retry' => 10, // max times to retry
		];

		if(!$this->allowPageVersions($page)) return 0;
		
		$version = $this->getNextPageVersionNumber($page);
		$options = array_merge($defaults, $options);
		$retry = (int) $options['retry'];
		$e = null;
		
		$options['update'] = false;
		$options['returnVersion'] = true;
		
		do {
			try {
				$fail = false;
				$this->savePageVersion($page, $version, $options);
			} catch(\Exception $e) {
				$code = $e->getCode();
				if($code != 23000) throw $e; // 23000=duplicate key
				$fail = true;
				$version++;
			}
		} while($fail && --$retry > 0);
		
		if($fail && $e) throw $e;
		
		return $version;
	}

	/**
	 * Save a page version
	 * 
	 * #pw-group-saving
	 *
	 * @param Page $page
	 * @param int|PageVersionInfo $version Version number or PageVersionInfo
	 *  - If given version number is greater than 0 and version doesn't exist, it will be added. 
	 *  - If 0 or omitted and given page is already a version, its version will be updated. 
	 *  - If 0 or omitted and given page is not a version, this method behaves the same as 
	 *    the `addPageVersion()` method and returns the added version number. 
	 * @param array $options
	 *  - `description` (string): Optional text description for version (default='')
	 *  - `update` (bool): Update version if it already exists (default=true)
	 * @return int|array Returns version number saved or added or 0 on fail
	 * @throws WireException|\PDOException
	 *
	 */
	public function savePageVersion(Page $page, $version = 0, array $options = []) {

		$defaults = [
			'description' => null, 
			'names' => [],
			'copyFiles' => true, // make a copy of the page’s files? internal use only
			'returnNames' => false,  // undocumented option, internal use only
			'update' => true,
		];

		$options = array_merge($defaults, $options);
		$database = $this->wire()->database;
		$copyFilesByField = false;
		$partial = !empty($options['names']);
		$table = self::versionsTable;
		$date = date('Y-m-d H:i:s');
		$user = $this->wire()->user;
		$of = $page->of();

		if(!$this->allowPageVersions($page)) return 0;
		if($of) $page->of(false);
		if(!is_int($version)) $version = $this->pageVersionNumber($page, $version);
		if($version < 1) $version = $this->pageVersionNumber($page);
		
		if(!$version) {
			$version = $this->addPageVersion($page, $options);
			if($of) $page->of(true);
			return $version;
		}
		
		$sql =
			"INSERT INTO $table (version, pages_id, description, " . 
			"data, created, modified, created_users_id, modified_users_id) " .
			"VALUES(:version, :pages_id, :description, " . 
			":data, :created, :modified, :created_users_id, :modified_users_id) ";
		
		if($options['update']) $sql .= 
			"ON DUPLICATE KEY UPDATE " . 
			"data=VALUES(data), modified=VALUES(modified), " . 
			"modified_users_id=VALUES(modified_users_id)";
	
		// update description only if it is being changed
		if($options['update'] && $options['description'] !== null) $sql .= ', ' .
			'description=VALUES(description)';
		
		$data = $this->getNativePagesTableData($page, $options);
		$names = array_keys($data);
		
		$query = $database->prepare($sql);
		$query->bindValue(':version', $version, \PDO::PARAM_INT);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':created', $date);
		$query->bindValue(':modified', $date);
		$query->bindValue(':created_users_id', $user->id, \PDO::PARAM_INT);
		$query->bindValue(':modified_users_id', $user->id, \PDO::PARAM_INT);
		$query->bindValue(':description', (string) $options['description']);
		$query->bindValue(':data', json_encode($data));
		$query->execute();

		if(!$options['copyFiles']) {
			// files will be excluded from the data
			
		} else if($partial) {
			// if only saving some fields in the version then copy by field
			if($this->pagesVersionsFiles->useFilesByField($page, $options['names'])) {
				$copyFilesByField = true;
			} else {
				// page does not support partial version
				$this->pageError($page, $this->_('Partial version not supported (file fields), saved full version'));
				$partial = false;
			}
			
		} else if($this->pagesVersionsFiles->useFilesByField($page)) {
			// page and all its fields support copying files by field
			$copyFilesByField = true;
		} 
	
		if(!$copyFilesByField) {
			// copy all files in directory (fallback)
			$this->pagesVersionsFiles->copyPageVersionFiles($page, $version);
		}

		foreach($page->fieldgroup as $field) {
			
			/** @var Field $field */
			if($partial && !in_array($field->name, $options['names'])) continue;
			
			$allow = $this->allowFieldVersions($field);
			
			if($allow instanceof FieldtypeDoesVersions) {
				$added = $allow->savePageFieldVersion($page, $field, $version);
				
			} else if($allow === true) {
				$added = $this->savePageFieldVersion($page, $field, $version);
				if($added && $copyFilesByField && $this->pagesVersionsFiles->fieldSupportsFiles($field)) {
					$this->pagesVersionsFiles->copyPageFieldVersionFiles($page, $field, $version);
				}
				
			} else {
				// field excluded from version
				$added = false;
			}
			
			if($added) $names[] = $field->name;
		}
		
		if($of) $page->of(true);
		
		return ($options['returnNames'] ? $names : $version);
	}

	/**
	 * Delete specific page version
	 * 
	 * ~~~~~~
	 * // delete version 2 of the page
	 * $page = $pages->get(1234);
	 * $pagesVersions->deletePageVersion($page, 2); 
	 * 
	 * // this does the same thing as above
	 * $pageV2 = $pagesVersions->getPageVersion($page, 2);
	 * $pagesVersions->deletePageVersion($pageV2);
	 * ~~~~~~
	 * 
	 * #pw-group-deleting
	 *
	 * @param Page $page Page to delete version from, or page having the version you want to delete.
	 * @param int $version Version number to delete or omit if given $page is the version you want to delete.
	 * @return int Number of DB rows deleted as part of the deletion process
	 *
	 */
	public function deletePageVersion(Page $page, $version = 0) {
		
		if(!is_int($version)) $version = $this->pageVersionNumber($page, $version);
		
		$database = $this->wire()->database;
		$qty = 0;
		
		if($version < 1) $version = $this->pageVersionNumber($page); 
		if($version < 1) return 0;

		foreach($page->fieldgroup as $field) {
			$allow = $this->allowFieldVersions($field);
			if($allow instanceof FieldtypeDoesVersions) {
				if($allow->deletePageFieldVersion($page, $field, $version)) $qty++;
			} else if($allow) {
				if($this->deletePageFieldVersion($page, $field, $version)) $qty++;
			}
		}

		foreach([self::valuesTable, self::versionsTable] as $table) {
			$sql = "DELETE FROM $table WHERE pages_id=:pages_id AND version=:version";
			$query = $database->prepare($sql);
			$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
			$query->bindValue(':version', $version, \PDO::PARAM_INT);
			$query->execute();
			$qty += $query->rowCount();
		}

		$this->pagesVersionsFiles->deletePageVersionFiles($page, $version);

		return $qty;
	}

	/**
	 * Delete all versions for given page
	 * 
	 * #pw-group-deleting
	 *
	 * @param Page $page
	 * @return int Number of versions deleted
	 *
	 */
	public function deleteAllPageVersions(Page $page) {
		$qty = 0;
		$versions = $this->getPageVersionInfos($page);
		foreach($versions as $v) {
			if($this->deletePageVersion($page, $v['version'])) $qty++;
		}
		return $qty;
	}

	/**
	 * Delete all versions across all pages
	 * 
	 * #pw-group-deleting
	 * 
	 * @param bool $areYouSure Specify true to indicate you are sure you want to do this
	 * @return int Quantity of versions deleted
	 * 
	 */
	public function deleteAllVersions($areYouSure = false) {
		if($areYouSure !== true) throw new WireException(
			"You must specify deleteAllVersions(true) to " . 
			"complete deletion of all versions across all pages"
		);
		$qty = 0;
		$pagesWithVersions = $this->getAllPagesWithVersions();
		foreach($pagesWithVersions as $page) {
			$qty += $this->deleteAllPageVersions($page);
		}
		return $qty;
	}

	/**
	 * Restore a page version to be the live version
	 * 
	 * Note that the restored page is saved to the database, replacing the current live version.
	 * So consider whether you should backup the live version (by using addPageVersion) before 
	 * restoring a version to it. 
	 * 
	 * ~~~~~
	 * // restore version 2 to live page
	 * $page = $pages->get(1234);
	 * $pagesVersions->restore($page, 2); // restore version 2
	 * 
	 * // this does the same as the above
	 * $pageV2 = $pagesVersions->getPageVersion($page, 2);
	 * $pagesVersions->restore($pageV2);
	 * ~~~~~
	 * 
	 * #pw-group-saving
	 *
	 * @param Page $page Page to restore version to or a page that was loaded as a version.
	 * @param int $version Version number to restore. Can be omitted if given $page is already a version.
	 * @param array $options
	 *  - `names` (array): Names of fields/properties to restore or omit for all (default=[])
	 *  - `useTempVersion` (bool): Create a temporary version and restore from that? (default=auto-detect).
	 *     This is necessary for some Fieldtypes like nested repeaters. Use of it is auto-detected so 
	 *     it is not necessary to specify this when using the public API.
	 * @return Page|bool Returns restored version page on success or false on fail
	 * @throws WireException
	 *
	 */
	public function restorePageVersion(Page $page, $version = 0, array $options = []) {
		
		$defaults = [
			'names' => [], 
			'useTempVersion' => null, 
		];

		$options = array_merge($defaults, $options);
		$version = (int) "$version";
		$pageVersion = $this->pageVersionNumber($page);
		$useTempVersion = $options['useTempVersion'];
		$partialRestore = count($options['names']) > 0; 
		$of = $page->of();
		
		if($version < 1) $version = $pageVersion;
		
		if($version < 1) {
			return $this->pageError($page, 
				sprintf($this->_('Cannot restore unknown version %s'), "$version")
			);
		}
		
		if(!$this->allowPageVersions($page)) {
			return $this->pageError($page, 
				$this->_('Restore failed, page does not allow versions')
			);
		}
		
		if($partialRestore && !$this->pageSupportsPartialVersion($page, $options['names'])) {
			return $this->pageError($page,
				$this->_('One or more fields requested does not support partial restore.')
			);
		}

		if($pageVersion) {
			// given page is the one to restore
			$versionPage = $page;
		} else {
			// we need to get the versioned page
			$versionPage = $this->getPageVersion($page, $version);
		}
		
		if(!$versionPage->id) return false;
		if($versionPage->of()) $versionPage->of(false);
		if($of) $page->of(false);

		if($useTempVersion === null) {
			$useTempVersion = $this->useTempVersionToRestore($page);
		}

		if($useTempVersion) {
			// create a temporary version page to restore from
			$useTempVersion = $this->addPageVersion($versionPage);
			if($useTempVersion) {
				$versionPage = $this->getPageVersion($page, $useTempVersion);
				$version = $useTempVersion;
			}
		}
	
		// this action is looked for in the Pages::saveReady hook
		$this->pageVersionInfo($versionPage, 'action', PageVersionInfo::actionRestore);
		
		if(!$partialRestore) {
			// restore all files
			$this->pagesVersionsFiles->restorePageVersionFiles($page, $version);
		}

		foreach($page->fieldgroup as $field) {
			/** @var Field $field */
			$allow = $this->allowFieldVersions($field);
			
			if(!$allow) {
				// field not allowed in versions
				
			} else if($partialRestore && !in_array($field->name, $options['names'])) {
				// partial restore does not include this field
				
			} else if($allow instanceof FieldtypeDoesVersions) {
				// fieldtype handles its own version restore
				$allow->restorePageFieldVersion($page, $field, $version);
				
			} else if($partialRestore && $this->pagesVersionsFiles->fieldSupportsFiles($field)) {
				// restore just files for a particular file field
				$this->pagesVersionsFiles->restorePageFieldVersionFiles($versionPage, $field, $version);
			}
		}

		// save to make it the live page
		$versionPage->save(); 
	
		// live page needs no version property
		$versionPage->__unset(self::pageProperty);
		
		if($useTempVersion && is_int($useTempVersion)) {
			// delete the temporary version page if used
			$this->deletePageVersion($page, $useTempVersion);
		}
		
		if($of) {
			$page->of(true);
			$versionPage->of(true);
		}
		
		return $versionPage;
	}

	/***************************************************************************
	 * INTERNAL API
	 *
	 */
	
	/**
	 * Get the field value for a page field version
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @param array $options
	 *  - `getRaw` (bool): Get raw data rather than page-ready value? (default=false)
	 * @return mixed|null Returns null if version data for field not available, field value otherwise
	 *
	 */
	public function getPageFieldVersion(Page $page, Field $field, $version, array $options = []) {
		
		$defaults = [
			'getRaw' => false, 
		];

		$options = array_merge($defaults, $options);
		$database = $this->wire()->database;
		$table = self::valuesTable;
		$version = (int) "$version";

		$sql =
			"SELECT data FROM $table " .
			"WHERE pages_id=:pages_id AND field_id=:field_id AND version=:version";

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':field_id', $field->id, \PDO::PARAM_INT);
		$query->bindValue(':version', $version, \PDO::PARAM_INT);
		$query->execute();

		if($query->rowCount()) {
			$value = $query->fetchColumn();
			$value = json_decode($value, true);
			if(is_array($value)) {
				if(count($value) === 1 && isset($value['data'])) {
					$value = $value['data'];
				}
			}
			if(!$options['getRaw']) {
				$value = $field->type->wakeupValue($page, $field, $value);
			}
		} else {
			$value = null;
		}

		$query->closeCursor();

		return $value;
	}

	/**
	 * Get native pages table data from page for storage in version
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param array $options
	 *  - `names` (array): Names of native page properties to include or omit for all
	 * @return array
	 *
	 */
	public function getNativePagesTableData(Page $page, array $options = []) {

		$database = $this->wire()->database;
		$names = isset($options['names']) ? $options['names'] : [];
		$partial = !empty($names);

		$sql = "SELECT * FROM pages WHERE id=:id";
		$query = $database->prepare($sql);
		$query->bindValue(':id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$data = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();
		unset($data['id']);

		if($partial) {
			foreach($data as $name => $value) {
				if(!in_array($name, $names)) unset($data[$name]);
			}
		}

		// get live values for any changed properties in memory
		$changes = $page->getChanges();
		if(count($changes)) {
			foreach($data as $name => $value) {
				if(!in_array($name, $changes)) continue;
				$data[$name] = $page->get($name);
			}
		}

		foreach([ 'modified', 'created', 'published' ] as $key) {
			if(!isset($data[$key])) continue;
			if(ctype_digit((string) $data[$key])) continue;
			$data[$key] = strtotime($data[$key]);
		}

		return $data;
	}

	/**
	 * Save page field version
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @param array $options
	 *  - `fromDatabase` (bool): Save from values in database rather than in $page? (not recommended)
	 * @return bool
	 *
	 */
	public function savePageFieldVersion(Page $page, Field $field, $version, array $options = []) {

		$defaults = [
			'fromDatabase' => false,
		];

		$database = $this->wire()->database;
		$table = self::valuesTable;
		$options = array_merge($defaults, $options);
		$version = (int) "$version";
		
		if($options['fromDatabase']) {
			$value = $this->getSleepValueFromDatabase($page, $field);
		} else {
			$value = $this->getSleepValueFromPage($page, $field);
		}
		
		if($value === false) return false;

		$value = json_encode($value);
		$error = '';
	
		if($value === false) {
			// json encode failed
			$error = $this->_('Skipped because unable to encode value'); 
		} else if(strlen($value) > 16777215) { 
			// encoded value exceeds max length of MEDIUMTEXT col 
			$error = $this->_('Skipped because value is too large to save in a version');
		}
	
		if($error) {
			return $this->pageFieldError($page, $field, $error);
		}

		$sql =
			"INSERT INTO $table (pages_id, field_id, version, data) " .
			"VALUES(:pages_id, :field_id, :version, :data) " .
			"ON DUPLICATE KEY UPDATE data=VALUES(data)";

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':field_id', $field->id, \PDO::PARAM_INT);
		$query->bindValue(':version', $version, \PDO::PARAM_INT);
		$query->bindValue(':data', $value);
		
		return $query->execute();
	}

	/**
	 * Get sleep value from given live page
	 *
	 * @param Page $page
	 * @param Field $field
	 * @return array|int|string|false
	 *
	 */
	protected function getSleepValueFromPage(Page $page, Field $field) {
		$value = $page->get($field->name);
		if($value instanceof PaginatedArray && $value->getTotal() > $value->count()) {
			// we only have a partial value
			// return $this->getSleepValueFromDatabase(); // @todo determine if we can do this
			return $this->pageFieldError(
				$page, $field, 
				$this->_('Paginated value cannot be versioned')
			);
		}
		return $field->type->sleepValue($page, $field, $value);
	}

	/**
	 * Get sleep value from the page field’s field_name table data in database
	 * 
	 * @todo method currently not used but may be later
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @return array|int|mixed|string
	 *
	 */
	protected function getSleepValueFromDatabase(Page $page, Field $field) {

		$database = $this->wire()->database;
		$table = $field->getTable();
		$multi = $field->type instanceof FieldtypeMulti;

		$sql =
			"SELECT * FROM `$table` " .
			"WHERE pages_id=:pages_id " .
			($multi ? 'ORDER BY sort' : '');

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();

		$numRows = $query->rowCount();

		if($multi && $numRows) {
			$value = [];
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				unset($value['pages_id']);
				$value[] = $row;
			}
		} else if($numRows) {
			$value = $query->fetch(\PDO::FETCH_ASSOC);
			unset($value['pages_id']);
		} else {
			$fieldtype = $field->type;
			$value = $fieldtype->getBlankValue($page, $field);
			$value = $fieldtype->sleepValue($page, $field, $value);
		}

		$query->closeCursor();

		return $value;
	}

	/**
	 * Delete a page field version
	 * 
	 * This should not be called independently of deletePageVersion() as this 
	 * method does not delete any files connected to the version.
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	protected function deletePageFieldVersion(Page $page, Field $field, $version) {

		$database = $this->wire()->database;
		$table = self::valuesTable;
		$version = (int) "$version";
		
		$sql =
			"DELETE FROM $table " .
			"WHERE pages_id=:pages_id AND field_id=:field_id AND version=:version";

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':field_id', $field->id, \PDO::PARAM_INT);
		$query->bindValue(':version', $version, \PDO::PARAM_INT);
		$query->execute();

		return $query->rowCount() > 0;
	}

	/**
	 * Update a property from the version info in the database
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param int $version
	 * @param string $property
	 * @param string|int $value
	 * @return bool
	 * @throws WireException|\PDOException
	 *
	 */
	public function updateVersionInfoProperty(Page $page, $version, $property, $value) {
		$database = $this->wire()->database;
		$table = self::versionsTable;
		if($property === 'data') {
			if(!is_array($value)) throw new WireException("Value must be array when setting data");
			$value = json_encode($value);
		} else {
			$testInfo = new PageVersionInfo([]);
			$testValue = $testInfo->get($property);
			if($testValue === null) throw new WireException("Unknown property: $property");
		}
		$col = $database->escapeCol($property);
		$sql = "UPDATE $table SET $col=:value WHERE pages_id=:pages_id AND version=:version";
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':version', $version, \PDO::PARAM_INT);
		$query->bindValue(':value', $value);
		return $query->execute();
	}

	/********************************************************************************
	 * UTILITY METHODS
	 *
	 */

	/**
	 * Cache for getUnsupportedFields method
	 * 
	 * @var array 
	 * 
	 */
	protected $unsupportedFields = [ 
		// 0 => [ 'field_name' => Field ] // where 0 is index for all fields
		// 3 => [ 'field_name' => Field ] // where 3 is template ID
	];
	
	/**
	 * Get the version number of given page or 0 if not versioned
	 *
	 * #pw-group-utility
	 *
	 * @param Page $page
	 * @param int|string|PageVersionInfo Optional version argument to use, if omitted it pulls from $page
	 *  - If this argument resolves to a number, that number is returned.
	 *  - If this argument is omitted, the version number is pulled from the $page argument.
	 * @return int
	 *
	 */
	public function pageVersionNumber(Page $page, $version = 0) {

		if(empty($version)) {
			// default

		} else if($version instanceof PageVersionInfo) {
			// PageVersionInfo object
			return $version->version;

		} else if(ctype_digit("$version")) {
			// integer version number
			return (int) $version;
			
		} else if(is_string($version)) {
			// string with v prefix i.e. v2
			$version = ltrim($version, 'v');
			if(ctype_digit($version)) return (int) $version;
		}

		/** @var PageVersionInfo $info */
		$info = $page->get(self::pageProperty);
		if(empty($info) || !$info->version) return 0;

		return $info->version;
	}

	/**
	 * Get or set page version info as stored in $page->_version
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param string|array $key Optional property to get or set, or array of values to set
	 * @param string|int|null $value Specify value to set or omit when getting
	 * @return PageVersionInfo|string|int|null
	 *
	 */
	public function pageVersionInfo(Page $page, $key = '', $value = null) {
		/** @var PageVersionInfo $info */
		$info = $page->get(self::pageProperty);
		if(!$info) {
			$info = new PageVersionInfo();
			$info->setPage($page);
		}
		if(is_array($key)) {
			$info->setArray($key);
		} else if($key && $value !== null) {
			$info->set($key, $value);
		} else if($key) {
			return $info->get($key);
		}
		return $info;
	}

	/**
	 * Get fields where versions are not supported
	 * 
	 * #pw-group-utility
	 * 
	 * @param Page|null $page Page to limit check to or omit for all fields
	 * @return Field[] Returned array of Field objects is indexed by Field name
	 * 
	 */
	public function getUnsupportedFields(?Page $page = null) {
		if($page && !$page->id) return [];
		$templateId = $page ? $page->templates_id : 0;
		if(isset($this->unsupportedFields[$templateId])) {
			return $this->unsupportedFields[$templateId]; 
		}
		$fields = $page ? $page->template->fieldgroup : $this->wire()->fields;
		$unsupported = [];
		foreach($fields as $field) {
			if($this->allowFieldVersions($field)) continue;
			$unsupported[$field->name] = $field;
		}
		$this->unsupportedFields[$templateId] = $unsupported;
		return $unsupported;
	}

	/**
	 * Does field allow versions?
	 *
	 * #pw-internal
	 *
	 * @param Field $field
	 * @return bool|int|FieldtypeDoesVersions
	 *  - Return boolean true if field allows versions
	 *  - Return boolean false if field does not support versions
	 *  - Return int 0 if field does not allow versions because it stores no data
	 *  - Return Fieldtype (FieldtypeDoesVersions) instance if field allows versions and handles internally
	 *
	 */
	public function allowFieldVersions(Field $field) {
		$fieldtype = $field->type;
		$allow = true;
		if($fieldtype instanceof FieldtypeFieldsetOpen) {
			$allow = 0;
		} else if(wireInstanceOf($fieldtype, 'FieldtypeComments')) {
			$allow = false;
		} else if($fieldtype instanceof FieldtypeDoesVersions) {
			$allow = $fieldtype;
		} else {
			$schema = $fieldtype->getDatabaseSchema($field);
			if(isset($schema['xtra']['all'])) {
				$allow = $schema['xtra']['all'] !== false;
			}
		}
		return $allow;
	}

	/**
	 * Is given page allowed to have versions?
	 * 
	 * #pw-hooker
	 * #pw-group-utility
	 *
	 * @param Page $page
	 * @return bool
	 *
	 */
	public function ___allowPageVersions(Page $page) {
		$disallows = [ 'User', 'Role', 'Permission', 'Language' ];
		if(wireInstanceOf($page, $disallows)) return false;
		return true;
	}
	
	/**
	 * Get the fields included with given page version
	 *
	 * #pw-internal
	 *
	 * @param Page|int $page
	 * @param int $version
	 * @return Field[] Array of field objects indexed by field name
	 *
	 */
	public function getPageVersionFields($page, $version) {
		$pageId = (int) "$page";
		$fields = $this->wire()->fields;
		$versionFields = [];
		$table = self::valuesTable;
		$sql = "SELECT field_id FROM $table WHERE pages_id=:pages_id AND version=:version";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':pages_id', $pageId, \PDO::PARAM_INT);
		$query->bindValue(':version', (int) $version, \PDO::PARAM_INT);
		$query->execute();
		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			$fieldId = (int) $row[0];
			$field = $fields->get($fieldId);
			if(!$field) continue;
			$versionFields[$field->name] = $field;
		}
		$query->closeCursor();
		return $versionFields;
	}


	/**
	 * Get next available version number for given page
	 *
	 * #pw-group-utility
	 *
	 * @param Page $page
	 * @return int
	 *
	 */
	public function getNextPageVersionNumber(Page $page) {
		$table = self::versionsTable;
		$sql = "SELECT MAX(version) from $table WHERE pages_id=:pages_id";
		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
		$version = (int) $query->fetchColumn() + 1;
		if($version === 1) $version++; // version 1 is reserved for draft version
		$query->closeCursor();
		return $version;
	}

	/**
	 * Should a temporary version be used during restore?
	 *
	 * This is necessary in some cases, where restoring may involve moving or destroying
	 * fields from the version. An example is nested repeater fields.
	 *
	 * #pw-internal
	 * #pw-hooker
	 *
	 * @param Page $page
	 * @return bool
	 *
	 */
	public function ___useTempVersionToRestore(Page $page) {
		$fieldtype = $this->wire()->fieldtypes->FieldtypeRepeater;
		if(!$fieldtype) return false;
		return $fieldtype->versions()->hasNestedRepeaterFields($page);
	}

	/**
	 * Does given page support partial version save and restore?
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @param array $names Optionally limit check to these field names
	 * @return bool
	 * 
	 */
	public function pageSupportsPartialVersion(Page $page, array $names = []) {
		$fileFields = $this->pagesVersionsFiles->getFileFields($page, [ 'names' => $names ]);
		if(!count($fileFields)) {
			return true;
		} else if($this->pagesVersionsFiles->useFilesByField($page, $names)) {
			return true;
		}
		return false;
	}

	/********************************************************************************
	 * HOOKS
	 *
	 */

	/**
	 * Hook before Pages::save or Pages::saveField 
	 * 
	 * This hook prevents save on a version page unless $action === 'restore'
	 * 
	 * #pw-internal
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookBeforePagesSave(HookEvent $event) {
		
		$page = $event->arguments(0); /** @var Page $page */
		$field = $event->arguments(1);
		$info = $this->pageVersionInfo($page);
		
		if(!$info->version || $info->getAction() === PageVersionInfo::actionRestore) {
			// not a version, or version is being restored
			return;
		}
		
		if($field instanceof Field) {
			// this is the Pages::saveField hook
			$options = [ 'names' => [ $field->name ] ];
		} else {
			// this is the Pages::save hook
			$options = [];
		}
		
		$event->replace = true;
		$event->return = (bool) $this->savePageVersion($page, $info->version, $options);
		$this->pagesVersionsFiles->hookBeforePagesSave($page);
	}

	/**
	 * Hook after page deleted to delete its versions 
	 * 
	 * #pw-internal
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookPageDeleted(HookEvent $event) {
		$page = $event->arguments(0); /** @var Page $page */
		$this->deleteAllPageVersions($page);
	}

	/********************************************************************************
	 * MODULE SUPPORT 
	 * 
	 */

	/**
	 * @var PagesVersionsFiles
	 * 
	 */
	protected $pagesVersionsFiles;

	/**
	 * Module init
	 * 
	 * #pw-internal
	 *
	 */
	public function init() {
		$this->addHookBefore('Pages::save, Pages::saveField', $this, 'hookBeforePagesSave');
		$this->addHook('Pages::deleted', $this, 'hookPageDeleted'); 
		require_once(__DIR__ . '/PagesVersionsFiles.php');
		$this->pagesVersionsFiles = new PagesVersionsFiles($this);
		$this->wire('pagesVersions', $this); // set API var
	}

	/**
	 * ProcessWire API ready
	 * 
	 * #pw-internal
	 * 
	 */
	public function ready() {
		
		$config = $this->wire()->config;

		if(!$config->admin) { 
			// front-end
			$page = $this->wire()->page;
			$version = (int) $this->wire()->input->get('version');
			if($version > 0 && $page->editable()) {
				// page-view on front-end
				$this->loadPageVersion($page, $version);
			}
		}
	}

	/**
	 * Message notification
	 * 
	 * #pw-internal
	 * 
	 * @param string $text
	 * @param int|string $flags
	 * @return self
	 * 
	 */
	public function message($text, $flags = 0) {
		if(empty($flags)) $flags = 'nogroup icon-' . self::iconName;
		return parent::message($text, $flags);
	}
	
	/**
	 * Report an error for specific page and field
	 * 
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param string $error
	 * @return false For code convenience only
	 *
	 */
	public function pageFieldError(Page $page, Field $field, $error) {
		$this->error(sprintf(
			$this->_('Page %d field %s'),
			$page->id,
			$field->name
		) . " - $error");
		return false;
	}

	/**
	 * Report an error for specific page 
	 *
	 * #pw-internal
	 *
	 * @param Page $page
	 * @param string $error
	 * @return false For code convenience only
	 *
	 */
	public function pageError(Page $page, $error) {
		$this->error(sprintf(
				$this->_('Page %d'),
				$page->id
			) . " - $error");
		return false;
	}

	/**
	 * Install version tables
	 * 
	 * #pw-internal
	 * 
	 */
	public function install() {
		$database = $this->wire()->database;
		$config = $this->wire()->config;
		
		$tables = [ 
			self::valuesTable => "
				pages_id INT UNSIGNED NOT NULL,
				field_id INT UNSIGNED NOT NULL, 
				version INT UNSIGNED NOT NULL, 
				data MEDIUMTEXT NOT NULL, 
				PRIMARY KEY (pages_id, field_id, version),
				INDEX(field_id), 
				INDEX(version)
			", 
			self::versionsTable => "
				version INT UNSIGNED NOT NULL,
				pages_id INT UNSIGNED NOT NULL, 
				created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, 
				modified TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, 
				created_users_id INT UNSIGNED NOT NULL, 
				modified_users_id INT UNSIGNED NOT NULL, 
				description TEXT,
				data MEDIUMTEXT NOT NULL,
				PRIMARY KEY(version, pages_id),
				INDEX(created),
				INDEX(modified),
				INDEX(created_users_id)
			",
			/*
			self::namesTable => "
				name VARCHAR(191) NOT NULL,
				pages_id INT UNSIGNED NOT NULL, 
				version INT UNSIGNED NOT NULL,
				PRIMARY KEY(pages_id, version),
				UNIQUE(name, pages_id)
			",
			*/
		];

		foreach($tables as $table => $sql) {
			try {
				$database->exec(
					"CREATE TABLE $table ($sql) " . 
					"ENGINE=$config->dbEngine DEFAULT CHARSET=$config->dbCharset"
				);
			} catch(\Exception $e) {
				$this->error($e->getMessage());
			}
		}
	}

	/**
	 * Uninstall version tables
	 * 
	 * #pw-internal
	 * 
	 */
	public function uninstall() {
		$qty = $this->deleteAllVersions(true);
		if($qty) $this->message("Deleted $qty page versions"); 
		$database = $this->wire()->database;
		$database->exec('DROP TABLE ' . self::valuesTable);
		$database->exec('DROP TABLE ' . self::versionsTable);
	}
}

require_once(__DIR__ . '/PageVersionInfo.php');
