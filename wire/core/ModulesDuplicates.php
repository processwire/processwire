<?php namespace ProcessWire;

/**
 * ProcessWire Modules Duplicates
 *
 * Provides functions for managing sitautions where more than one
 * copy of the same module is intalled. This is a helper for the Modules class.
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class ModulesDuplicates extends Wire {

	/**
	 * Array of modules where more than one copy was found
	 *
	 * Associative array of 'ModuleName' => array(path1, path2, ...)
	 *
	 * @var array
	 *
	 */
	protected $duplicates = array();

	/**
	 * Specifies which module file to use in cases where there is more than one
	 *
	 * Array of 'ModuleName' => '/path/to/file/from/pw/root/file.module'
	 *
	 * @var array
	 *
	 */
	protected $duplicatesUse = array();

	/**
	 * Number of new duplicates found while loading modules
	 *
	 * @var int
	 *
	 */
	protected $numNewDuplicates = 0;

	/**
	 * Return quantity of new duplicates found while loading modules
	 * 
	 * @return int
	 * 
	 */
	public function numNewDuplicates() {
		return $this->numNewDuplicates;
	}

	/**
	 * Get the current duplicate in use (string) or null if not specified
	 * 
	 * @param $className
	 * @return string|null Pathname or null
	 * 
	 */
	public function getCurrent($className) {
		return isset($this->duplicatesUse[$className]) ? $this->duplicatesUse[$className] : null;
	}
	
	/**
	 * Does the given module class have a duplicate?
	 * 
	 * @param string $className
	 * @param string $pathname Optionally specify the duplicate to check
	 * @return bool
	 * 
	 */
	public function hasDuplicate($className, $pathname = '') {
		if(!isset($this->duplicates[$className])) return false;
		if($pathname) {
			$rootPath = $this->wire('config')->paths->root;
			if(strpos($pathname, $rootPath) === 0) $pathname = str_replace($rootPath, '/', $pathname);
			return in_array($pathname, $this->duplicates[$className]);
		}
		return true; 
	}

	/**
	 * Add a duplicate to the list
	 * 
	 * @param $className
	 * @param $pathname
	 * @param bool $current Is this the current one in use?
	 *
	 */
	public function addDuplicate($className, $pathname, $current = false) {
		if(!isset($this->duplicates[$className])) $this->duplicates[$className] = array();
		$rootPath = $this->wire('config')->paths->root;
		if(strpos($pathname, $rootPath) === 0) $pathname = str_replace($rootPath, '/', $pathname);
		if(!in_array($pathname, $this->duplicates[$className])) {
			$this->duplicates[$className][] = $pathname;
		}
		if($current) {
			$this->duplicatesUse[$className] = $pathname;
		}
	}

	/**
	 * Add multiple duplicates
	 * 
	 * @param $className
	 * @param array $files
	 * 
	 */
	public function addDuplicates($className, array $files) {
		foreach($files as $file) {
			$this->addDuplicate($className, $file); 
		}
	}

	/**
	 * Add duplicates from module config data
	 * 
	 * @param $className
	 * @param array $data
	 * 
	 */
	public function addFromConfigData($className, array $data) {
		$files = isset($data['-dups']) ? $data['-dups'] : array();
		$using = isset($data['-dups-use']) ? $data['-dups-use'] : '';
		if(count($files)) $this->addDuplicates($className, $files);
		if($using) $this->addDuplicate($className, $using, true); // set current, in-use
	}
	
	/**
	 * Return a list of duplicate modules that were found
	 *
	 * If given a module className, the following is returned:
	 *
	 * Array(
	 *    'files' => array(file1, file2, ...)
	 *    'using' => '/path/to/file/from/pw/root/ModuleName.module' or blank if not defined
	 * )
	 *
	 * If no className is specivied, the following is returned:
	 *
	 * Array(
	 *    'ModuleName' => array(file1, file2, ...),
	 *    'ModuleName' => array(file1, file2, ...),
	 *    ...and so on...
	 * )
	 *
	 * @param string|Module|int $className Optionally return only duplicates for given module name
	 *
	 * @return array
	 *
	 */
	public function getDuplicates($className = '') {

		if(!$className) return $this->duplicates;

		$className = $this->wire('modules')->getModuleClass($className);
		$files = isset($this->duplicates[$className]) ? $this->duplicates[$className] : array();
		$using = isset($this->duplicatesUse[$className]) ? $this->duplicatesUse[$className] : '';
		$rootPath = $this->wire('config')->paths->root;

		foreach($files as $key => $file) {
			$file = rtrim($rootPath, '/') . $file;
			if(!file_exists($file)) {
				unset($files[$key]);
			}
		}

		if(count($files) > 1 && !$using) {
			$using = $this->wire('modules')->getModuleFile($className);
			$using = str_replace($rootPath, '/', $using);
		}

		if(count($files) < 2) {
			// no need to store duplicate info if only 0 or 1
			//unset($this->duplicates[$className], $this->duplicatesUse[$className]); 
			$files = array();
			$using = '';
		}

		return array('files' => $files, 'using' => $using);
	}

	/**
	 * For a module that has duplicates, tell it which file to use
	 *
	 * @param string $className
	 * @param string $pathname Full path and filename to module file
	 *
	 * @throws WireException if given information that can't be resolved
	 *
	 */
	public function setUseDuplicate($className, $pathname) {
		$className = $this->wire('modules')->getModuleClass($className);
		$rootPath = $this->wire('config')->paths->root;
		if(!isset($this->duplicates[$className])) {
			throw new WireException("Module $className does not have duplicates");
		}
		$pathname = str_replace($rootPath, '/', $pathname);
		if(!in_array($pathname, $this->duplicates[$className])) {
			throw new WireException("Duplicate module pathname must be one of: " . implode(" \n", $this->duplicates[$className]));
		}
		if(!file_exists($rootPath . ltrim($pathname, '/'))) {
			throw new WireException("Duplicate module file does not exist: $pathname");
		}
		$this->duplicatesUse[$className] = $pathname;
		$configData = $this->wire('modules')->getModuleConfigData($className);
		$configData['-dups-use'] = $pathname;
		$this->wire('modules')->saveModuleConfigData($className, $configData);
	}

	/**
	 * Update the database so that modules have information on their duplicates
	 *
	 */
	public function updateDuplicates() {

		$rootPath = $this->wire('config')->paths->root;

		// store duplicate information in each module's data field
		foreach($this->getDuplicates() as $moduleName => $files) {
			$dup = $this->getDuplicates($moduleName); // so that we also have 'using' info
			$files = $dup['files'];
			$using = $dup['using'];
			foreach($files as $key => $file) {
				// make files relative to site root, for portability
				$file = str_replace($rootPath, '/', $file);
				$files[$key] = $file;
			}
			$files = array_unique($files);
			$configData = $this->wire('modules')->getModuleConfigData($moduleName);
			if((empty($configData['-dups']) && !empty($files))
				|| (empty($configData['-dups-use']) || $configData['-dups-use'] != $using)
				|| (isset($configData['-dups']) && implode(' ', $configData['-dups']) != implode(' ', $files))
			) {
				$this->duplicates[$moduleName] = $files;
				$this->duplicatesUse[$moduleName] = $using;
				$configData['-dups'] = $files;
				$configData['-dups-use'] = $using;
				$this->wire('modules')->saveModuleConfigData($moduleName, $configData);
			}
		}

		// update any modules that no longer have duplicates
		$removals = array();
		$query = $this->wire('database')->prepare("SELECT `class`, `flags` FROM modules WHERE `flags` & :flag");
		$query->bindValue(':flag', Modules::flagsDuplicate, \PDO::PARAM_INT);
		$query->execute();

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			list($class, $flags) = $row;
			if(empty($this->duplicates[$class])) {
				$flags = $flags & ~Modules::flagsDuplicate;
				$removals[$class] = $flags;
			}
			unset($this->duplicatesUse[$class]); // just in case
		}

		foreach($removals as $class => $flags) {
			$this->wire('modules')->setFlags($class, $flags); 
			$configData = $this->wire('modules')->getModuleConfigData($class);
			unset($configData['-dups'], $configData['-dups-use']);
			$this->wire('modules')->saveModuleConfigData($class, $configData);
		}
	}

	/**
	 * Record a duplicate at runtime
	 *
	 * @param string $basename Name of module
	 * @param string $pathname Path of module
	 * @param string $pathname2 Second path of module
	 * @param array $installed Installed module info array 
	 *
	 */
	public function recordDuplicate($basename, $pathname, $pathname2, &$installed) {
		$rootPath = $this->wire('config')->paths->root;
		// ensure paths start from root of PW install
		if(strpos($pathname, $rootPath) === 0) $pathname = str_replace($rootPath, '/', $pathname);
		if(strpos($pathname2, $rootPath) === 0) $pathname2 = str_replace($rootPath, '/', $pathname2);
		// there are two copies of the module on the file system (likely one in /site/modules/ and another in /wire/modules/)
		if(!isset($this->duplicates[$basename])) {
			$this->duplicates[$basename] = array($pathname, $pathname2); // array(str_replace($rootPath, '/', $this->getModuleFile($basename)));
			$this->numNewDuplicates++;
		}
		if(!in_array($pathname, $this->duplicates[$basename])) {
			$this->duplicates[$basename][] = $pathname;
			$this->numNewDuplicates++;
		}
		if(!in_array($pathname2, $this->duplicates[$basename])) {
			$this->duplicates[$basename][] = $pathname2;
			$this->numNewDuplicates++;
		}
		if(isset($installed[$basename]['flags'])) {
			$flags = $installed[$basename]['flags'];
		} else {
			$flags = $this->wire('modules')->getFlags($basename);
		}
		if($flags & Modules::flagsDuplicate) {
			// flags already represent duplicate status
		} else {
			// make database aware this module has multiple files by adding the duplicate flag
			$this->numNewDuplicates++; // trigger update needed
			$flags = $flags | Modules::flagsDuplicate;
			$this->wire('modules')->setFlags($basename, $flags); 
		}
		$err = sprintf($this->_('There appear to be multiple copies of module "%s" on the file system.'), $basename) . ' ';
		$this->wire('log')->save('modules', $err);
		$user = $this->wire('user');
		if($user && $user->isSuperuser()) {
			$err .= $this->_('Please edit the module settings to tell ProcessWire which one to use:') . ' ' .
				"<a href='" . $this->wire('config')->urls->admin . 'module/edit?name=' . $basename . "'>$basename</a>";
			$this->warning($err, Notice::allowMarkup);
		}
		//$this->message("recordDuplicate($basename, $pathname) $this->numNewDuplicates"); //DEBUG
		//$this->message($this->duplicates[$basename]);//DEBUG
	}

	/**
	 * Populate duplicates info into config data, when applicable
	 *
	 * @param $className
	 * @param array $configData
	 *
	 * @return array Updated configData
	 *
	 */
	public function getDuplicatesConfigData($className, array $configData = array()) {
		// ensure original duplicates info is retained and validate that it is still current
		if(isset($this->duplicates[$className])) {
			foreach($this->duplicates[$className] as $key => $file) {
				$pathname = rtrim($this->wire('config')->paths->root, '/') . $file;
				if(!file_exists($pathname)) {
					unset($this->duplicates[$className][$key]);
				}
			}
			if(count($this->duplicates[$className]) < 2) {
				// no need to store any info for this if there's only 0 or 1
				unset($this->duplicates[$className], $this->duplicatesUse[$className], $configData['-dups'], $configData['-dups-use']);
			} else {
				$configData['-dups'] = $this->duplicates[$className];
				if(isset($this->duplicatesUse[$className])) {
					$pathname = rtrim($this->wire('config')->paths->root, '/') . $this->duplicatesUse[$className];
					if(file_exists($pathname)) {
						$configData['-dups-use'] = $this->duplicatesUse[$className];
					} else {
						unset($configData['-dups-use'], $this->duplicatesUse[$className]);
					}
				}
			}
		} else if(empty($this->duplicates[$className]) && isset($configData['-dups'])) {
			unset($configData['-dups'], $configData['-dups-use']);
		}
		return $configData;
	}
}
