<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Configs
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class ModulesConfigs extends ModulesClass {
	
	/**
	 * Cached module configuration data indexed by module ID
	 *
	 * Values are integer 1 for modules that have config data but data is not yet loaded.
	 * Values are an array for modules have have config data and has been loaded.
	 *
	 */
	protected $configData = array();

	/**
	 * Get or set module configuration data
	 * 
	 * #pw-internal
	 * 
	 * @param int $moduleID
	 * @param array $setData
	 * @return array|int|null Returns one of the following:
	 *  - Array of module config data 
	 *  - Null if requested moduleID is not found
	 *  - Integer 1 if config data is present but must be loaded from DB
	 * 
	 */
	public function configData($moduleID, $setData = null) {
		$moduleID = (int) $moduleID;
		if($setData) {
			$this->configData[$moduleID] = $setData;
			return array();
		} else if(isset($this->configData[$moduleID])) {
			return $this->configData[$moduleID];
		} else {
			return null;
		}
	}
	
	/**
	 * Return the URL where the module can be edited, configured or uninstalled
	 *
	 * If module is not installed, it returns URL to install the module.
	 *
	 * #pw-group-configuration
	 *
	 * @param string|Module $className
	 * @param bool $collapseInfo
	 * @return string
	 *
	 */
	public function getModuleEditUrl($className, $collapseInfo = true) {
		if(!is_string($className)) $className = $this->modules->getModuleClass($className);
		$url = $this->wire()->config->urls->admin . 'module/';
		if(empty($className)) return $url;
		if(!$this->modules->isInstalled($className)) return $this->modules->getModuleInstallUrl($className);
		$url .= "edit/?name=$className";
		if($collapseInfo) $url .= "&collapse_info=1";
		return $url;
	}
	
	/**
	 * Given a module name, return an associative array of configuration data for it
	 *
	 * - Applicable only for modules that support configuration.
	 * - Configuration data is stored encoded in the database "modules" table "data" field.
	 *
	 * ~~~~~~
	 * // Getting, modifying and saving module config data
	 * $data = $modules->getConfig('HelloWorld');
	 * $data['greeting'] = 'Hello World! How are you today?';
	 * $modules->saveConfig('HelloWorld', $data);
	 *
	 * // Getting just one property 'apiKey' from module config data
	 * @apiKey = $modules->getConfig('HelloWorld', 'apiKey');
	 * ~~~~~~
	 *
	 * #pw-group-configuration
	 * #pw-changelog 3.0.16 Changed from more verbose name `getModuleConfigData()`, which can still be used.
	 *
	 * @param string|Module $class
	 * @param string $property Optionally just get value for a specific property (omit to get all config)
	 * @return array|string|int|float Module configuration data, returns array unless a specific $property was requested
	 * @see Modules::saveConfig()
	 * @since 3.0.16 Use method getModuleConfigData() with same arguments for prior versions (can also be used on any version).
	 *
	 */
	public function getConfig($class, $property = '') {

		$emptyReturn = $property ? null : array();
		$className = $class;
		
		if(is_object($className)) $className = wireClassName($className->className(), false);
		
		$id = $this->moduleID($className);
		if(!$id) return $emptyReturn;
	
		$data = isset($this->configData[$id]) ? $this->configData[$id] : null;
		if($data === null) return $emptyReturn; // module has no config data

		if(is_array($data)) {
			// great
		} else {
			// configData===1 indicates data must be loaded from DB
			$configable = $this->isConfigable($className);
			if(!$configable) return $emptyReturn;
			$database = $this->wire()->database;
			$query = $database->prepare("SELECT data FROM modules WHERE id=:id", "modules.getConfig($className)"); // QA
			$query->bindValue(":id", (int) $id, \PDO::PARAM_INT);
			$query->execute();
			$data = $query->fetchColumn();
			$query->closeCursor();
			if(strlen($data)) $data = wireDecodeJSON($data);
			if(empty($data)) $data = array();
			$this->configData[(int) $id] = $data;
		}

		if($property) return isset($data[$property]) ? $data[$property] : null;

		return $data;
	}
	
	/**
	 * Is the given module interactively configurable?
	 *
	 * This method can be used to simply determine if a module is configurable (yes or no), or more specifically
	 * how it is configurable.
	 *
	 * ~~~~~
	 * // Determine IF a module is configurable
	 * if($modules->isConfigurable('HelloWorld')) {
	 *   // Module is configurable
	 * } else {
	 *   // Module is NOT configurable
	 * }
	 * ~~~~~
	 * ~~~~~
	 * // Determine HOW a module is configurable
	 * $configurable = $module->isConfigurable('HelloWorld');
	 * if($configurable === true) {
	 *   // configurable in a way compatible with all past versions of ProcessWire
	 * } else if(is_string($configurable)) {
	 *   // configurable via an external configuration file
	 *   // file is identifed in $configurable variable
	 * } else if(is_int($configurable)) {
	 *   // configurable via a method in the class
	 *   // the $configurable variable contains a number with specifics
	 * } else {
	 *   // module is NOT configurable
	 * }
	 * ~~~~~
	 *
	 * ### Return value details
	 *
	 * #### If module is configurable via external configuration file:
	 *
	 * - Returns string of full path/filename to `ModuleName.config.php` file
	 *
	 * #### If module is configurable because it implements a configurable module interface:
	 *
	 * - Returns boolean `true` if module is configurable via the static `getModuleConfigInputfields()` method.
	 *   This particular method is compatible with all past versions of ProcessWire.
	 * - Returns integer `2` if module is configurable via the non-static `getModuleConfigInputfields()` and requires no arguments.
	 * - Returns integer `3` if module is configurable via the non-static `getModuleConfigInputfields()` and requires `$data` array.
	 * - Returns integer `4` if module is configurable via the non-static `getModuleConfigInputfields()` and requires `InputfieldWrapper` argument.
	 * - Returns integer `19` if module is configurable via non-static `getModuleConfigArray()` method.
	 * - Returns integer `20` if module is configurable via static `getModuleConfigArray()` method.
	 *
	 * #### If module is not configurable:
	 *
	 * - Returns boolean `false` if not configurable
	 *
	 * *This method is named isConfigurableModule() in ProcessWire versions prior to to 3.0.16.*
	 *
	 * #pw-group-configuration
	 *
	 * @param Module|string $class Module name
	 * @param bool $useCache Use caching? This accepts a few options:
	 * 	- Specify boolean `true` to allow use of cache when available (default behavior).
	 * 	- Specify boolean `false` to disable retrieval of this property from getModuleInfo (forces a new check).
	 * 	- Specify string `interface` to check only if module implements ConfigurableModule interface.
	 * 	- Specify string `file` to check only if module has a separate configuration class/file.
	 * @return bool|string|int See details about return values in method description.
	 * @since 3.0.16
	 *
	 * @todo this method has two distinct parts (file and interface) that need to be split in two methods.
	 *
	 */
	public function isConfigurable($class, $useCache = true) {

		$className = $class;
		$moduleInstance = null;
		$namespace = $this->modules->info->getModuleNamespace($className);
		
		if(is_object($className)) {
			$moduleInstance = $className;
			$className = $this->modules->getModuleClass($moduleInstance);
		}
		
		$nsClassName = $namespace . $className;

		if($useCache === true || $useCache === 1 || $useCache === "1") {
			$info = $this->modules->getModuleInfo($className);
			// if regular module info doesn't have configurable info, attempt it from verbose module info
			// should only be necessary for transition period between the 'configurable' property being 
			// moved from verbose to non-verbose module info (i.e. this line can be deleted after PW 2.7)
			if($info['configurable'] === null) {
				$info = $this->modules->getModuleInfoVerbose($className);
			}
			if(!$info['configurable']) {
				if($moduleInstance instanceof ConfigurableModule) {
					// re-try because moduleInfo may be temporarily incorrect for this request because of change in moduleInfo format
					// this is due to reports of ProcessChangelogHooks not getting config data temporarily between 2.6.11 => 2.6.12
					$this->error(
						"Configurable module check failed for $className. " .
						"If this error persists, please do a Modules > Refresh.",
						Notice::debug
					);
					$useCache = false;
				} else {
					return false;
				}
			} else {
				if($info['configurable'] === true) return $info['configurable'];
				if($info['configurable'] === 1 || $info['configurable'] === "1") return true;
				if(is_int($info['configurable']) || ctype_digit("$info[configurable]")) return (int) $info['configurable'];
				if(strpos($info['configurable'], $className) === 0) {
					if(empty($info['file'])) {
						$info['file'] = $this->modules->files->getModuleFile($className);
					}
					if($info['file']) {
						return dirname($info['file']) . "/$info[configurable]";
					}
				}
			}
		}

		if($useCache !== "interface") {
			// check for separate module configuration file
			$dir = dirname($this->modules->files->getModuleFile($className));
			if($dir) {
				$files = array(
					"$dir/{$className}Config.php",
					"$dir/$className.config.php"
				);
				$found = false;
				foreach($files as $file) {
					if(!is_file($file)) continue;
					$config = null; // include file may override
					$this->modules->files->includeModuleFile($file, $className);
					$classConfig = $nsClassName . 'Config';
					if(class_exists($classConfig, false)) {
						$parents = wireClassParents($classConfig, false);
						if(is_array($parents) && in_array('ModuleConfig', $parents)) {
							$found = $file;
							break;
						}
					} else {
						// bypass include_once, because we need to read $config every time
						if(is_null($config)) {
							$classInfo = $this->modules->files->getFileClassInfo($file);
							if($classInfo['class']) {
								// not safe to include because this is not just a file with a $config array
							} else {
								$ns = $this->modules->files->getFileNamespace($file);
								$file = $this->modules->files->compile($className, $file, $ns);
								if($file) {
									/** @noinspection PhpIncludeInspection */
									include($file);
								}
							}
						}
						if(!is_null($config)) {
							// included file specified a $config array
							$found = $file;
							break;
						}
					}
				}
				if($found) return $found;
			}
		}

		// if file-only check was requested and we reach this point, exit with false now
		if($useCache === "file") return false;

		// ConfigurableModule interface checks

		$result = false;

		foreach(array('getModuleConfigArray', 'getModuleConfigInputfields') as $method) {

			$configurable = false;

			// if we have a module instance, use that for our check
			if($moduleInstance instanceof ConfigurableModule) {
				if(method_exists($moduleInstance, $method)) {
					$configurable = $method;
				} else if(method_exists($moduleInstance, "___$method")) {
					$configurable = "___$method";
				}
			}

			// if we didn't have a module instance, load the file to find what we need to know
			if(!$configurable) {
				if(!wireClassExists($nsClassName, false)) {
					$this->modules->includeModule($className);
				}
				$interfaces = wireClassImplements($nsClassName, false);
				if(is_array($interfaces) && in_array('ConfigurableModule', $interfaces)) {
					if(wireMethodExists($nsClassName, $method)) {
						$configurable = $method;
					} else if(wireMethodExists($nsClassName, "___$method")) {
						$configurable = "___$method";
					}
				}
			}

			// if still not determined to be configurable, move on to next method
			if(!$configurable) continue;

			// now determine if static or non-static
			$ref = new \ReflectionMethod(wireClassName($nsClassName, true), $configurable);

			if($ref->isStatic()) {
				// config method is implemented as a static method
				if($method == 'getModuleConfigInputfields') {
					// static getModuleConfigInputfields
					$result = true;
				} else {
					// static getModuleConfigArray
					$result = 20;
				}

			} else if($method == 'getModuleConfigInputfields') {
				// non-static getModuleConfigInputfields
				// we allow for different arguments, so determine what it needs
				$parameters = $ref->getParameters();
				if(count($parameters)) {
					$param0 = reset($parameters);
					if(strpos($param0, 'array') !== false || strpos($param0, '$data') !== false) {
						// method requires a $data array (for compatibility with non-static version)
						$result = 3;
					} else if(strpos($param0, 'InputfieldWrapper') !== false || strpos($param0, 'inputfields') !== false) {
						// method requires an empty InputfieldWrapper (as a convenience)
						$result = 4;
					}
				}
				// method requires no arguments
				if(!$result) $result = 2;

			} else {
				// non-static getModuleConfigArray
				$result = 19;
			}

			// if we make it here, we know we already have a result so can stop now
			break;
		}

		return $result;
	}


	/**
	 * Indicates whether module accepts config settings, whether interactively or API only
	 *
	 * - Returns false if module does not accept config settings.
	 * - Returns integer `30` if module accepts config settings but is not interactively configurable.
	 * - Returns true, int or string if module is interactively configurable, see `Modules::isConfigurable()` return values.
	 *
	 * @param string|Module $class
	 * @param bool $useCache
	 * @return bool|int|string
	 * @since 3.0.179
	 *
	 */
	public function isConfigable($class, $useCache = true) {
		if(is_object($class)) {
			if($class instanceof ConfigModule) {
				$result = 30;
			} else {
				$result = $this->isConfigurable($class, $useCache);
			}
		} else {
			$result = $this->isConfigurable($class, $useCache);
			if(!$result && wireInstanceOf($class, 'ConfigModule')) $result = 30;
		}
		return $result;
	}

	/**
	 * Populate configuration data to a ConfigurableModule
	 *
	 * If the Module has a 'setConfigData' method, it will send the array of data to that.
	 * Otherwise it will populate the properties individually.
	 *
	 * @param Module $module
	 * @param array|null $data Configuration data [key=value], or omit/null if you want it to retrieve the config data for you.
	 * @param array|null $extraData Additional runtime configuration data to merge (default=null) 3.0.169+
	 * @return bool True if configured, false if not configurable
	 *
	 */
	public function setModuleConfigData(Module $module, $data = null, $extraData = null) {

		$configurable = $this->isConfigable($module);
		if(!$configurable) return false;
		
		if(!is_array($data)) $data = $this->getConfig($module);
		if(is_array($extraData)) $data = array_merge($data, $extraData);

		$nsClassName = $module->className(true);
		$moduleName = $module->className(false);

		if(is_string($configurable) && is_file($configurable) && strpos(basename($configurable), $moduleName) === 0) {
			// get defaults from ModuleConfig class if available
			$className = $nsClassName . 'Config';
			$config = null; // may be overridden by included file
			// $compile = strrpos($className, '\\') < 1 && $this->wire('config')->moduleCompile;
			$configFile = '';

			if(!class_exists($className, false)) {
				$configFile = $this->modules->files->compile($className, $configurable);
				// $configFile = $compile ? $this->wire('files')->compile($configurable) : $configurable;
				if($configFile) {
					/** @noinspection PhpIncludeInspection */
					include_once($configFile);
				}
			}

			if(wireClassExists($className)) {
				$parents = wireClassParents($className, false);
				if(is_array($parents) && in_array('ModuleConfig', $parents)) {
					$moduleConfig = $this->wire(new $className());
					if($moduleConfig instanceof ModuleConfig) {
						$defaults = $moduleConfig->getDefaults();
						$data = array_merge($defaults, $data);
					}
				}
			} else {
				// the file may have already been include_once before, so $config would not be set
				// so we try a regular include() next. 
				if(is_null($config)) {
					if(!$configFile) {
						$configFile = $this->modules->files->compile($className, $configurable);
						// $configFile = $compile ? $this->wire('files')->compile($configurable) : $configurable;
					}
					if($configFile) {
						/** @noinspection PhpIncludeInspection */
						include($configFile);
					}
				}
				if(is_array($config)) {
					// alternatively, file may just specify a $config array
					/** @var ModuleConfig $moduleConfig */
					$moduleConfig = $this->wire(new ModuleConfig());
					$moduleConfig->add($config);
					$defaults = $moduleConfig->getDefaults();
					$data = array_merge($defaults, $data);
				}
			}
		}

		if(method_exists($module, 'setConfigData') || method_exists($module, '___setConfigData')) {
			/** @var _Module $module */
			$module->setConfigData($data);
			return true;
		}

		foreach($data as $key => $value) {
			$module->$key = $value;
		}

		return true;
	}

	/**
	 * Save provided configuration data for the given module
	 *
	 * - Applicable only for modules that support configuration.
	 * - Configuration data is stored encoded in the database "modules" table "data" field.
	 *
	 * ~~~~~~
	 * // Getting, modifying and saving module config data
	 * $data = $modules->getConfig('HelloWorld');
	 * $data['greeting'] = 'Hello World! How are you today?';
	 * $modules->saveConfig('HelloWorld', $data);
	 * ~~~~~~
	 *
	 * #pw-group-configuration
	 * #pw-group-manipulation
	 * #pw-changelog 3.0.16 Changed name from the more verbose saveModuleConfigData(), which will still work.
	 *
	 * @param string|Module $class Module or module name
	 * @param array|string $data Associative array of configuration data, or name of property you want to save.
	 * @param mixed|null $value If you specified a property in previous arg, the value for the property.
	 * @return bool True on success, false on failure
	 * @throws WireException
	 * @see Modules::getConfig()
	 * @since 3.0.16 Use method saveModuleConfigData() with same arguments for prior versions (can also be used on any version).
	 *
	 */
	public function saveConfig($class, $data, $value = null) {
		
		$className = $class;
		if(is_object($className)) $className = $className->className();
		
		$moduleName = wireClassName($className, false);
		$id = $this->moduleID($moduleName);
		
		if(!$id) throw new WireException("Unable to find ID for Module '$moduleName'");

		if(is_string($data)) {
			// a property and value have been provided
			$property = $data;
			$data = $this->getConfig($class);
			if(is_null($value)) {
				// remove the property
				unset($data[$property]);
			} else {
				// populate the value for the property
				$data[$property] = $value;
			}
		} else {
			// data must be an associative array of configuration data
			if(!is_array($data)) return false;
		}

		// ensure original duplicates info is retained and validate that it is still current
		$data = $this->modules->duplicates()->getDuplicatesConfigData($moduleName, $data);

		$this->configData[$id] = $data;
		$json = count($data) ? wireEncodeJSON($data, true) : '';
		$database = $this->wire()->database;
		$query = $database->prepare("UPDATE modules SET data=:data WHERE id=:id", "modules.saveConfig($moduleName)"); // QA
		$query->bindValue(":data", $json, \PDO::PARAM_STR);
		$query->bindValue(":id", (int) $id, \PDO::PARAM_INT);
		$result = $query->execute();
		// $this->log("Saved module '$moduleName' config data");

		return $result;
	}

	/**
	 * Get the Inputfields that configure the given module or return null if not configurable
	 *
	 * #pw-internal
	 *
	 * @param string|Module|int $moduleName
	 * @param InputfieldWrapper|null $form Optionally specify the form you want Inputfields appended to.
	 * @return InputfieldWrapper|null
	 *
	 */
	public function getModuleConfigInputfields($moduleName, ?InputfieldWrapper $form = null) {

		$moduleName = $this->modules->getModuleClass($moduleName);
		$configurable = $this->isConfigurable($moduleName);
		
		if(!$configurable) return null;

		/** @var InputfieldWrapper $form */
		if(is_null($form)) $form = $this->wire(new InputfieldWrapper());
		
		$data = $this->getConfig($moduleName);
		$fields = null;

		// check for configurable module interface
		$configurableInterface = $this->isConfigurable($moduleName, "interface");
		if($configurableInterface) {
			if(is_int($configurableInterface) && $configurableInterface > 1 && $configurableInterface < 20) {
				// non-static 
				/** @var ConfigurableModule|Module|_Module $module */
				if($configurableInterface === 2) {
					// requires no arguments
					$module = $this->modules->getModule($moduleName);
					$fields = $module->getModuleConfigInputfields();
				} else if($configurableInterface === 3) {
					// requires $data array
					$module = $this->modules->getModule($moduleName, array('noInit' => true, 'noCache' => true));
					$this->setModuleConfigData($module);
					$fields = $module->getModuleConfigInputfields($data);
				} else if($configurableInterface === 4) {
					// requires InputfieldWrapper
					// we allow for option of no return statement in the method
					$module = $this->modules->getModule($moduleName);
					$fields = $this->wire(new InputfieldWrapper()); /** @var InputfieldWrapper $fields */
					$fields->setParent($form);
					$_fields = $module->getModuleConfigInputfields($fields);
					if($_fields instanceof InputfieldWrapper) $fields = $_fields;
					unset($_fields);
				} else if($configurableInterface === 19) {
					// non-static getModuleConfigArray method
					$module = $this->modules->getModule($moduleName);
					$fields = $this->wire(new InputfieldWrapper()); /** @var InputfieldWrapper $fields */
					$fields->importArray($module->getModuleConfigArray());
					$fields->populateValues($module);
				}
			} else if($configurableInterface === 20) {
				// static getModuleConfigArray method
				$fields = $this->wire(new InputfieldWrapper()); /** @var InputfieldWrapper $fields */
				$fields->importArray(call_user_func(array(wireClassName($moduleName, true), 'getModuleConfigArray')));
				$fields->populateValues($data);
			} else {
				// static getModuleConfigInputfields method
				$nsClassName = $this->modules->info->getModuleNamespace($moduleName) . $moduleName;
				$fields = call_user_func(array($nsClassName, 'getModuleConfigInputfields'), $data);
			}
			if($fields instanceof InputfieldWrapper) {
				foreach($fields as $field) {
					$form->append($field);
				}
			} else if($fields instanceof Inputfield) {
				$form->append($fields);
			} else {
				$this->error("$moduleName.getModuleConfigInputfields() did not return InputfieldWrapper");
			}
		}

		// check for file-based config
		$file = $this->isConfigurable($moduleName, "file");
		if(!$file || !is_string($file) || !is_file($file)) {
			// config is not file-based
		} else {
			// file-based config
			$config = null;
			$ns = $this->modules->info->getModuleNamespace($moduleName);
			$configClass = $ns . $moduleName . "Config";
			if(!class_exists($configClass)) {
				$configFile = $this->modules->files->compile($moduleName, $file, $ns);
				if($configFile) {
					/** @noinspection PhpIncludeInspection */
					include_once($configFile);
				}
			}
			$configModule = null;

			if(wireClassExists($configClass)) {
				// file contains a ModuleNameConfig class
				$configModule = $this->wire(new $configClass());

			} else {
				if(is_null($config)) {
					$configFile = $this->modules->files->compile($moduleName, $file, $ns);
					if($configFile) {
						/** @noinspection PhpIncludeInspection */
						include($configFile); // in case of previous include_once 
					}
				}
				if(is_array($config)) {
					// file contains a $config array
					$configModule = $this->wire(new ModuleConfig());
					$configModule->add($config);
				}
			}

			if($configModule instanceof ModuleConfig) {
				$defaults = $configModule->getDefaults();
				$data = array_merge($defaults, $data);
				$configModule->setArray($data);
				$fields = $configModule->getInputfields();
				if($fields instanceof InputfieldWrapper) {
					foreach($fields as $field) {
						$form->append($field);
					}
					foreach($data as $key => $value) {
						$f = $form->getChildByName($key);
						if(!$f) continue;
						if($f instanceof InputfieldCheckbox && $value) {
							$f->attr('checked', 'checked');
						} else {
							$f->attr('value', $value);
						}
					}
				} else {
					$this->error("$configModule.getInputfields() did not return InputfieldWrapper");
				}
			}
		} // file-based config

		if($form) {
			// determine how many visible Inputfields there are in the module configuration
			// for assignment or removal of flagsNoUserConfig flag when applicable
			$numVisible = 0;
			foreach($form->getAll() as $inputfield) {
				if($inputfield instanceof InputfieldHidden || $inputfield instanceof InputfieldWrapper) continue;
				$numVisible++;
			}
			$flags = $this->modules->flags->getFlags($moduleName);
			if($numVisible) {
				if($flags & Modules::flagsNoUserConfig) {
					$info = $this->modules->info->getModuleInfoVerbose($moduleName);
					if(empty($info['addFlag']) || !($info['addFlag'] & Modules::flagsNoUserConfig)) {
						$this->modules->flags->setFlag($moduleName, Modules::flagsNoUserConfig, false); // remove flag
					}
				}
			} else {
				if(!($flags & Modules::flagsNoUserConfig)) {
					if(empty($info['removeFlag']) || !($info['removeFlag'] & Modules::flagsNoUserConfig)) {
						$this->modules->flags->setFlag($moduleName, Modules::flagsNoUserConfig, true); // add flag
					}
				}
			}
		}

		return $form;
	}

	public function getDebugData() {
		return array(
			'configData' => $this->configData
		);
	}

}
