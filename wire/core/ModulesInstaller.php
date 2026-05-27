<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Installer
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class ModulesInstaller extends ModulesClass {
	
	/**
	 * Get an associative array [name => path] for all modules that aren’t currently installed.
	 *
	 * #pw-internal
	 *
	 * @return array Array of elements with $moduleName => $pathName
	 *
	 */
	public function getInstallable() {
		return $this->modules->getInstallable();
	}

	/**
	 * Is the given module name installable? (i.e. not already installed)
	 *
	 * #pw-internal
	 *
	 * @param string $class Module class name
	 * @param bool $now Is module installable RIGHT NOW? This makes it check that all dependencies are already fulfilled (default=false)
	 * @return bool True if module is installable, false if not
	 *
	 */
	public function isInstallable($class, $now = false) {
		$installableFiles = $this->modules->installableFiles;
		if(!array_key_exists($class, $installableFiles)) return false;
		if(!wireInstanceOf($class, 'Module')) {
			$nsClass = $this->modules->getModuleClass($class, true);
			if(!wireInstanceOf($nsClass, 'ProcessWire\\Module')) return false;
		}
		if($now) {
			$requires = $this->getRequiresForInstall($class);
			if(count($requires)) return false;
		}
		return true;
	}

	/**
	 * Install the given module name
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @param array|bool $options Optional associative array that can contain any of the following:
	 *  - `dependencies` (boolean): When true, dependencies will also be installed where possible. Specify false to prevent installation of uninstalled modules. (default=true)
	 *  - `resetCache` (boolean): When true, module caches will be reset after installation. (default=true)
	 *  - `force` (boolean): Force installation, even if dependencies can't be met.
	 * @return null|Module Returns null if unable to install, or ready-to-use Module object if successfully installed.
	 * @throws WireException
	 *
	 */
	public function install($class, $options = array()) {

		$defaults = array(
			'dependencies' => true,
			'resetCache' => true,
			'force' => false,
		);

		if(is_bool($options)) {
			// dependencies argument allowed instead of $options, for backwards compatibility
			$dependencies = $options;
			$options = array('dependencies' => $dependencies);
		}

		$options = array_merge($defaults, $options);
		$dependencyOptions = $options;
		$dependencyOptions['resetCache'] = false;

		if(!$this->isInstallable($class)) return null;

		$requires = $this->getRequiresForInstall($class);
		if(count($requires)) {
			$error = '';
			$installable = false;
			if($options['dependencies']) {
				$installable = true;
				foreach($requires as $requiresModule) {
					if(!$this->isInstallable($requiresModule)) $installable = false;
				}
				if($installable) {
					foreach($requires as $requiresModule) {
						if(!$this->modules->install($requiresModule, $dependencyOptions)) {
							$error = $this->_('Unable to install required module') . " - $requiresModule. ";
							$installable = false;
							break;
						}
					}
				}
			}
			if(!$installable) {
				$error = sprintf($this->_('Module %s requires: %s'), $class, implode(', ', $requires)) . ' ' . $error;
				if($options['force']) {
					$this->warning($this->_('Warning!') . ' ' . $error);
				} else {
					throw new WireException($error);
				}
			}
		}

		$database = $this->wire()->database;
		$languages = $this->wire()->languages;
		$config = $this->wire()->config;

		if($languages) $languages->setDefault();

		$pathname = $this->modules->installableFile($class);
		
		if(strpos($class, "\\") === false) {
			$ns = $this->modules->info->getModuleNamespace($class, array(
				'file' => $pathname
			));
			$nsClass = $ns . $class;
		} else {
			$nsClass = $class;
		}
		
		if(!class_exists($nsClass, false)) {
			$this->modules->files->includeModuleFile($pathname, $class);
			$this->modules->files->setConfigPaths($class, dirname($pathname));
		}

		$module = $this->modules->newModule($nsClass, $class);
		if(!$module) return null;
		
		$flags = 0;
		$moduleID = 0;

		if($this->modules->isSingular($module)) $flags = $flags | Modules::flagsSingular;
		if($this->modules->isAutoload($module)) $flags = $flags | Modules::flagsAutoload;

		$sql = "INSERT INTO modules SET class=:class, flags=:flags, data=''";
		if($config->systemVersion >= 7) $sql .= ", created=NOW()";
		$query = $database->prepare($sql, "modules.install($class)");
		$query->bindValue(":class", $class, \PDO::PARAM_STR);
		$query->bindValue(":flags", $flags, \PDO::PARAM_INT);

		try {
			if($query->execute()) $moduleID = (int) $database->lastInsertId();
		} catch(\Exception $e) {
			if($languages) $languages->unsetDefault();
			$this->trackException($e, false, true);
			return null;
		}

		$this->modules->moduleID($class, $moduleID);
		$this->modules->add($module);
		$this->modules->installableFile($class, false); // unset

		// note: the module's install is called here because it may need to know its module ID for installation of permissions, etc. 
		if(method_exists($module, '___install') || method_exists($module, 'install')) {
			try {
				/** @var _Module $module */
				$module->install();

			} catch(\PDOException $e) {
				$error = $this->_('Module reported error during install') . " ($class): " . $e->getMessage();
				$this->error($error);
				$this->trackException($e, false, $error);

			} catch(\Exception $e) {
				// remove the module from the modules table if the install failed
				$moduleID = (int) $moduleID;
				$error = $this->_('Unable to install module') .  " ($class): " . $e->getMessage();
				$ee = null;
				try {
					$query = $database->prepare('DELETE FROM modules WHERE id=:id LIMIT 1'); // QA
					$query->bindValue(":id", $moduleID, \PDO::PARAM_INT);
					$query->execute();
				} catch(\Exception $ee) {
					$this->trackException($e, false, $error)->trackException($ee, true);
				}
				if($languages) $languages->unsetDefault();
				if(is_null($ee)) $this->trackException($e, false, $error);
				return null;
			}
		}

		$info = $this->modules->info->getModuleInfoVerbose($class, array('noCache' => true));
		$sanitizer = $this->wire()->sanitizer;
		$permissions = $this->wire()->permissions;

		// if this module has custom permissions defined in its getModuleInfo()['permissions'] array, install them 
		foreach($info['permissions'] as $name => $title) {
			$name = $sanitizer->pageName($name);
			if(ctype_digit("$name") || empty($name)) continue; // permission name not valid
			$permission = $permissions->get($name);
			if($permission->id) continue; // permision already there
			try {
				$permission = $permissions->add($name);
				$permission->title = $title;
				$permissions->save($permission);
				if($languages) $languages->unsetDefault();
				$this->message(sprintf($this->_('Added Permission: %s'), $permission->name));
				if($languages) $languages->setDefault();
			} catch(\Exception $e) {
				if($languages) $languages->unsetDefault();
				$error = sprintf($this->_('Error adding permission: %s'), $name);
				if($languages) $languages->setDefault();
				$this->trackException($e, false, $error);
			}
		}

		// check if there are any modules in 'installs' that this module didn't handle installation of, and install them
		$label = $this->_('Module Auto Install');

		foreach($info['installs'] as $name) {
			if(!$this->modules->isInstalled($name)) {
				try {
					$this->modules->install($name, $dependencyOptions);
					$this->message("$label: $name");
				} catch(\Exception $e) {
					$error = "$label: $name - " . $e->getMessage();
					$this->trackException($e, false, $error);
				}
			}
		}

		$this->log("Installed module '$module'");
		if($languages) $languages->unsetDefault();
		if($options['resetCache']) $this->modules->info->clearModuleInfoCache();

		return $module;
	}

	/**
	 * Returns whether the module can be uninstalled
	 *
	 * #pw-internal
	 *
	 * @param string|Module $class
	 * @param bool $returnReason If true, the reason why it can't be uninstalled with be returned rather than boolean false.
	 * @return bool|string
	 *
	 */
	public function isUninstallable($class, $returnReason = false) {

		$reason = '';
		$reason1 = $this->_("Module is not already installed");
		$namespace = $this->modules->info->getModuleNamespace($class);
		$class = $this->modules->getModuleClass($class);

		if(!$this->modules->isInstalled($class)) {
			$reason = $reason1 . ' (a)';

		} else {
			$this->modules->includeModule($class);
			if(!wireClassExists($namespace . $class, false)) {
				$reason = $reason1 . " (b: $namespace$class)";
			}
		}

		if(!$reason) {
			// if the moduleInfo contains a non-empty 'permanent' property, then it's not uninstallable
			$info = $this->modules->info->getModuleInfo($class);
			if(!empty($info['permanent'])) {
				$reason = $this->_("Module is permanent");
			} else {
				$dependents = $this->getRequiresForUninstall($class);
				if(count($dependents)) $reason = $this->_("Module is required by other modules that must be removed first");
			}

			if(!$reason && in_array('Fieldtype', wireClassParents($namespace . $class))) {
				foreach($this->wire()->fields as $field) {
					$fieldtype = wireClassName($field->type, false);
					if($fieldtype == $class) {
						$reason = $this->_("This module is a Fieldtype currently in use by one or more fields");
						break;
					}
				}
			}
		}

		if($returnReason && $reason) return $reason;

		return $reason ? false : true;
	}

	/**
	 * Returns whether the module can be deleted (have it's files physically removed)
	 *
	 * #pw-internal
	 *
	 * @param string|Module $class
	 * @param bool $returnReason If true, the reason why it can't be removed will be returned rather than boolean false.
	 * @return bool|string
	 *
	 */
	public function isDeleteable($class, $returnReason = false) {

		$reason = '';
		$class = $this->modules->getModuleClass($class);
		$filename = (string) $this->modules->installableFile($class);
		$dirname = strlen($filename) ? dirname($filename) : '';

		if(empty($filename) || $this->modules->isInstalled($class)) {
			$reason = "Module must be uninstalled before it can be deleted.";

		} else if(is_link($filename) || is_link($dirname) || is_link(dirname($dirname))) {
			$reason = "Module is linked to another location";

		} else if(!is_file($filename)) {
			$reason = "Module file does not exist";

		} else if(strpos($filename, $this->modules->coreModulesPath) === 0) {
			$reason = "Core modules may not be deleted.";

		} else if(!is_writable($filename)) {
			$reason = "We have no write access to the module file, it must be removed manually.";
		}

		if($returnReason && $reason) return $reason;

		return $reason ? false : true;
	}

	/**
	 * Delete the given module, physically removing its files
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @return bool
	 * @throws WireException If module can't be deleted, exception will be thrown containing reason.
	 *
	 */
	public function delete($class) {

		$config = $this->wire()->config;
		$fileTools = $this->wire()->files;

		$class = $this->modules->getModuleClass($class);
		$success = true;
		$reason = $this->isDeleteable($class, true);
		if($reason !== true) throw new WireException($reason);
		$siteModulesPath = $config->paths->siteModules;

		$filename = $this->modules->installableFile($class);
		$basename = basename($filename);

		// double check that $class is consistent with the actual $basename	
		if($basename === "$class.module" || $basename === "$class.module.php") {
			// good, this is consistent with the format we require
		} else {
			throw new WireException("Unrecognized module filename format");
		}

		// now determine if module is the owner of the directory it exists in
		// this is the case if the module class name is the same as the directory name

		$path = dirname($filename); // full path to directory, i.e. .../site/modules/ProcessHello
		$name = basename($path); // just name of directory that module is, i.e. ProcessHello
		$parentPath = dirname($path); // full path to parent directory, i.e. ../site/modules
		$backupPath = $parentPath . "/.$name"; // backup path, in case module is backed up

		// first check that we are still in the /site/modules/ (or another non core modules path)
		$inPath = false; // is module somewhere beneath /site/modules/ ?
		$inRoot = false; // is module in /site/modules/ root? i.e. /site/modules/ModuleName.module

		foreach($this->modules->getPaths() as $key => $modulesPath) {
			if($key === 0) continue; // skip core modules path
			if(strpos("$parentPath/", $modulesPath) === 0) $inPath = true;
			if($modulesPath === $path) $inRoot = true;
		}

		$basename = basename($basename, '.php');
		$basename = basename($basename, '.module');

		$files = array(
			"$basename.module",
			"$basename.module.php",
			"$basename.info.php",
			"$basename.info.json",
			"$basename.config.php",
			"{$basename}Config.php",
		);

		if($inPath) {
			// module is in /site/modules/[ModuleName]/

			$numOtherModules = 0; // num modules in dir other than this one
			$numLinks = 0; // number of symbolic links
			$dirs = array("$path/");

			do {
				$dir = array_shift($dirs);
				$this->message("Scanning: $dir", Notice::debug);

				foreach(new \DirectoryIterator($dir) as $file) {
					if($file->isDot()) continue;
					if($file->isLink()) {
						$numLinks++;
						continue;
					}
					if($file->isDir()) {
						$dirs[] = $fileTools->unixDirName($file->getPathname());
						continue;
					}
					if(in_array($file->getBasename(), $files)) continue; // skip known files
					if(strpos($file->getBasename(), '.module') && preg_match('{(\.module|\.module\.php)$}', $file->getBasename())) {
						// another module exists in this dir, so we don't want to delete that
						$numOtherModules++;
					}
					if(preg_match('{^(' . $basename . '\.[-_.a-zA-Z0-9]+)$}', $file->getBasename(), $matches)) {
						// keep track of potentially related files in case we have to delete them individually
						$files[] = $matches[1];
					}
				}
			} while(count($dirs));

			if(!$inRoot && !$numOtherModules && !$numLinks) {
				// the modulePath had no other modules or directories in it, so we can delete it entirely
				$success = (bool) $fileTools->rmdir($path, true);
				if($success) {
					$this->message("Removed directory: $path", Notice::debug);
					if(is_dir($backupPath)) {
						if($fileTools->rmdir($backupPath, true)) $this->message("Removed directory: $backupPath", Notice::debug);
					}
					$files = array();
				} else {
					$this->error("Failed to remove directory: $path", Notice::debug);
				}
			}
		}

		// remove module files individually 
		foreach($files as $file) {
			$file = "$path/$file";
			if(!file_exists($file)) continue;
			if($fileTools->unlink($file, $siteModulesPath)) {
				$this->message("Removed file: $file", Notice::debug);
			} else {
				$this->error("Unable to remove file: $file", Notice::debug);
			}
		}

		$this->log("Deleted module '$class'");

		return $success;
	}


	/**
	 * Uninstall the given module name
	 *
	 * #pw-group-manipulation
	 *
	 * @param string $class Module name (class name)
	 * @return bool
	 * @throws WireException
	 *
	 */
	public function uninstall($class) {

		$class = $this->modules->getModuleClass($class);
		$reason = $this->modules->isUninstallable($class, true);
		
		if($reason !== true) {
			// throw new WireException("$class - Can't Uninstall - $reason"); 
			return false;
		}

		// check if there are any modules still installed that this one says it is responsible for installing
		foreach($this->getUninstalls($class) as $name) {

			// catch uninstall exceptions at this point since original module has already been uninstalled
			$label = $this->_('Module Auto Uninstall');
			try {
				$this->modules->uninstall($name);
				$this->message("$label: $name");

			} catch(\Exception $e) {
				$error = "$label: $name - " . $e->getMessage();
				$this->trackException($e, false, $error);
			}
		}

		$info = $this->modules->info->getModuleInfoVerbose($class);
		$module = $this->modules->getModule($class, array(
			'noPermissionCheck' => true,
			'noInstall' => true,
			// 'noInit' => true
		));
		if(!$module) return false;

		// remove all hooks attached to this module
		$hooks = $module instanceof Wire ? $module->getHooks() : array();
		foreach($hooks as $hook) {
			if($hook['method'] == 'uninstall') continue;
			$this->message("Removed hook $class => " . $hook['options']['fromClass'] . " $hook[method]", Notice::debug);
			$module->removeHook($hook['id']);
		}

		// remove all hooks attached to other ProcessWire objects
		$hooks = array_merge($this->getHooks('*'), $this->wire()->hooks->getAllLocalHooks());
		foreach($hooks as $hook) {
			/** @var Wire $toObject */
			$toObject = $hook['toObject'];
			$toClass = wireClassName($toObject, false);
			$toMethod = $hook['toMethod'];
			if($class === $toClass && $toMethod != 'uninstall') {
				$toObject->removeHook($hook['id']);
				$this->message("Removed hook $class => " . $hook['options']['fromClass'] . " $hook[method]", Notice::debug);
			}
		}

		if(method_exists($module, '___uninstall') || method_exists($module, 'uninstall')) {
			// note module's uninstall method may throw an exception to abort the uninstall
			/** @var _Module $module */
			$module->uninstall();
		}
		$database = $this->wire()->database;
		$query = $database->prepare('DELETE FROM modules WHERE class=:class LIMIT 1'); // QA
		$query->bindValue(":class", $class, \PDO::PARAM_STR);
		$query->execute();

		// add back to the installable list
		if(class_exists("ReflectionClass")) {
			$reflector = new \ReflectionClass($this->modules->getModuleClass($module, true));
			$this->modules->installableFile($class, $reflector->getFileName());
		}

		$this->modules->moduleID($class, false);
		$this->modules->remove($module);

		$sanitizer = $this->wire()->sanitizer;
		$permissions = $this->wire()->permissions;

		// delete permissions installed by this module
		if(isset($info['permissions']) && is_array($info['permissions'])) {
			foreach($info['permissions'] as $name => $title) {
				$name = $sanitizer->pageName($name);
				if(ctype_digit("$name") || empty($name)) continue;
				$permission = $permissions->get($name);
				if(!$permission->id) continue;
				try {
					$permissions->delete($permission);
					$this->message(sprintf($this->_('Deleted Permission: %s'), $name));
				} catch(\Exception $e) {
					$error = sprintf($this->_('Error deleting permission: %s'), $name);
					$this->trackException($e, false, $error);
				}
			}
		}

		$this->log("Uninstalled module '$class'");
		$this->modules->refresh();

		return true;
	}

	/**
	 * Return an array of other module class names that are uninstalled when the given one is
	 *
	 * #pw-internal
	 *
	 * The opposite of this function is found in the getModuleInfo array property 'installs'.
	 * Note that 'installs' and uninstalls may be different, as only modules in the 'installs' list
	 * that indicate 'requires' for the installer module will be uninstalled.
	 *
	 * @param $class
	 * @return array
	 *
	 */
	public function getUninstalls($class) {

		$uninstalls = array();
		$class = $this->modules->getModuleClass($class);
		if(!$class) return $uninstalls;
		$info = $this->modules->info->getModuleInfoVerbose($class);

		// check if there are any modules still installed that this one says it is responsible for installing
		foreach($info['installs'] as $name) {

			// if module isn't installed, then great
			if(!$this->modules->isInstalled($name)) continue;

			// if an 'installs' module doesn't indicate that it requires this one, then leave it installed
			$i = $this->modules->info->getModuleInfo($name);
			if(!in_array($class, $i['requires'])) continue;

			// add it to the uninstalls array
			$uninstalls[] = $name;
		}

		return $uninstalls;
	}
	
	/**
	 * Return an array of module class names that require the given one
	 *
	 * #pw-internal
	 *
	 * @param string $class
	 * @param bool $uninstalled Set to true to include modules dependent upon this one, even if they aren't installed.
	 * @param bool $installs Set to true to exclude modules that indicate their install/uninstall is controlled by $class.
	 * @return array()
	 *
	 */
	public function getRequiredBy($class, $uninstalled = false, $installs = false) {

		$class = $this->modules->getModuleClass($class);
		$info = $this->modules->info->getModuleInfo($class);
		$dependents = array();

		foreach($this->modules as $module) {
			$c = $this->modules->getModuleClass($module);
			if(!$uninstalled && !$this->modules->isInstalled($c)) continue;
			$i = $this->modules->info->getModuleInfo($c);
			if(!count($i['requires'])) continue;
			if($installs && in_array($c, $info['installs'])) continue;
			if(in_array($class, $i['requires'])) $dependents[] = $c;
		}

		return $dependents;
	}

	/**
	 * Return an array of module class names required by the given one
	 *
	 * Default behavior is to return all listed requirements, whether they are currently met by
	 * the environment or not. Specify TRUE for the 2nd argument to return only requirements
	 * that are not currently met.
	 *
	 * #pw-internal
	 *
	 * @param string $class
	 * @param bool $onlyMissing Set to true to return only required modules/versions that aren't
	 * 	yet installed or don't have the right version. It excludes those that the class says it
	 * 	will install (via 'installs' property of getModuleInfo)
	 * @param null|bool $versions Set to true to always include versions in the returned requirements list.
	 * 	Set to null to always exclude versions in requirements list (so only module class names will be there).
	 * 	Set to false (which is the default) to include versions only when version is the dependency issue.
	 * 	Note versions are already included when the installed version is not adequate.
	 * @return array of strings each with ModuleName Operator Version, i.e. "ModuleName>=1.0.0"
	 *
	 */
	public function getRequires($class, $onlyMissing = false, $versions = false) {

		$class = $this->modules->getModuleClass($class);
		$info = $this->modules->getModuleInfo($class);
		$requires = $info['requires'];
		$currentVersion = 0;

		// quick exit if arguments permit it 
		if(!$onlyMissing) {
			if($versions) foreach($requires as $key => $value) {
				list($operator, $version) = $info['requiresVersions'][$value];
				if(empty($version)) continue;
				if(ctype_digit("$version")) $version = $this->modules->formatVersion($version);
				if(!empty($version)) $requires[$key] .= "$operator$version";
			}
			return $requires;
		}

		foreach($requires as $key => $requiresClass) {

			if(in_array($requiresClass, $info['installs'])) {
				// if this module installs the required class, then we can stop now
				// and we assume it's installing the version it wants
				unset($requires[$key]);
			}

			list($operator, $requiresVersion) = $info['requiresVersions'][$requiresClass];
			$installed = true;

			if($requiresClass == 'PHP') {
				$currentVersion = PHP_VERSION;

			} else if($requiresClass == 'ProcessWire') {
				$currentVersion = $this->wire()->config->version;

			} else if($this->modules->isInstalled($requiresClass)) {
				if(!$requiresVersion) {
					// if no version is specified then requirement is already met
					unset($requires[$key]);
					continue;
				}
				$i = $this->modules->getModuleInfo($requiresClass, array('noCache' => true));
				$currentVersion = $i['version'];
			} else {
				// module is not installed
				$installed = false;
			}

			if($installed && $this->versionCompare($currentVersion, $requiresVersion, $operator)) {
				// required version is installed
				unset($requires[$key]);

			} else if(empty($requiresVersion)) {
				// just the class name is fine
				continue;

			} else if(is_null($versions)) {
				// request is for no versions to be included (just class names)
				$requires[$key] = $requiresClass;

			} else {
				// update the requires string to clarify what version it requires
				if(ctype_digit("$requiresVersion")) $requiresVersion = $this->modules->formatVersion($requiresVersion);
				$requires[$key] = "$requiresClass$operator$requiresVersion";
			}
		}

		return $requires;
	}


	/**
	 * Compare one module version to another, returning TRUE if they match the $operator or FALSE otherwise
	 *
	 * #pw-internal
	 *
	 * @param int|string $currentVersion May be a number like 123 or a formatted version like 1.2.3
	 * @param int|string $requiredVersion May be a number like 123 or a formatted version like 1.2.3
	 * @param string $operator
	 * @return bool
	 *
	 */
	public function versionCompare($currentVersion, $requiredVersion, $operator) {

		if(ctype_digit("$currentVersion") && ctype_digit("$requiredVersion")) {
			// integer comparison is ok
			$currentVersion = (int) $currentVersion;
			$requiredVersion = (int) $requiredVersion;
			$result = false;

			switch($operator) {
				case '=': $result = ($currentVersion == $requiredVersion); break;
				case '>': $result = ($currentVersion > $requiredVersion); break;
				case '<': $result = ($currentVersion < $requiredVersion); break;
				case '>=': $result = ($currentVersion >= $requiredVersion); break;
				case '<=': $result = ($currentVersion <= $requiredVersion); break;
				case '!=': $result = ($currentVersion != $requiredVersion); break;
			}
			return $result;
		}

		// if either version has no periods or only one, like "1.2" then format it to stanard: "1.2.0"
		if(substr_count($currentVersion, '.') < 2) $currentVersion = $this->modules->formatVersion($currentVersion);
		if(substr_count($requiredVersion, '.') < 2) $requiredVersion = $this->modules->formatVersion($requiredVersion);

		return version_compare($currentVersion, $requiredVersion, $operator);
	}

	/**
	 * Return an array of module class names required by the given one to be installed before this one.
	 *
	 * Excludes modules that are required but already installed.
	 * Excludes uninstalled modules that $class indicates it handles via it's 'installs' getModuleInfo property.
	 *
	 * #pw-internal
	 *
	 * @param string $class
	 * @return array()
	 *
	 */
	public function getRequiresForInstall($class) {
		return $this->getRequires($class, true);
	}

	/**
	 * Return an array of module class names required by the given one to be uninstalled before this one.
	 *
	 * Excludes modules that the given one says it handles via it's 'installs' getModuleInfo property.
	 * Module class names in returned array include operator and version in the string.
	 *
	 * #pw-internal
	 *
	 * @param string $class
	 * @return array()
	 *
	 */
	public function getRequiresForUninstall($class) {
		return $this->getRequiredBy($class, false, true);
	}

	/**
	 * Return array of dependency errors for given module name
	 *
	 * #pw-internal
	 *
	 * @param $moduleName
	 * @return array If no errors, array will be blank. If errors, array will be of strings (error messages)
	 *
	 */
	public function getDependencyErrors($moduleName) {

		$moduleName = $this->modules->getModuleClass($moduleName);
		$info = $this->modules->getModuleInfo($moduleName);
		$errors = array();

		if(empty($info['requires'])) return $errors;

		foreach($info['requires'] as $requiresName) {
			$error = '';

			if(!$this->modules->isInstalled($requiresName)) {
				$error = $requiresName;

			} else if(!empty($info['requiresVersions'][$requiresName])) {
				list($operator, $version) = $info['requiresVersions'][$requiresName];
				$info2 = $this->modules->getModuleInfo($requiresName);
				$requiresVersion = $info2['version'];
				if(!empty($version) && !$this->versionCompare($requiresVersion, $version, $operator)) {
					$error = "$requiresName $operator $version";
				}
			}

			if($error) $errors[] = sprintf($this->_('Failed module dependency: %s requires %s'), $moduleName, $error);
		}

		return $errors;
	}
	
	/**
	 * Get URL where an administrator can install given module name
	 *
	 * If module is already installed, it returns the URL to edit the module.
	 *
	 * @param string $className
	 * @return string
	 *
	 */
	public function getModuleInstallUrl($className) {
		if(!is_string($className)) $className = $this->modules->getModuleClass($className);
		$className = $this->wire()->sanitizer->fieldName($className);
		if($this->modules->isInstalled($className)) return $this->modules->getModuleEditUrl($className);
		return $this->wire()->config->urls->admin . "module/installConfirm?name=$className";
	}

}
