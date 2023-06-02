<?php namespace ProcessWire;

/**
 * ProcessWire Modules: Flags
 *
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 *
 */

class ModulesFlags extends ModulesClass {
	
	/**
	 * Array of module ID => flags (int)
	 *
	 * @var array
	 *
	 */
	protected $moduleFlags = array();

	/**
	 * Get or set flags for module by module ID
	 * 
	 * Omit all arguments to get flags for all modules indexed by module ID.
	 * 
	 * Returns null if given module ID not found.
	 * 
	 * @param int $moduleID This method only accepts module ID
	 * @param int $setValue Flag(s) to set
	 * @return array|mixed|null
	 * 
	 */
	public function moduleFlags($moduleID = null, $setValue = null) {
		if($moduleID === null) return $this->moduleFlags;
		if(!ctype_digit("$moduleID")) $moduleID = $this->moduleID($moduleID);
		if($setValue !== null) {
			$this->moduleFlags[(int) $moduleID] = (int) $setValue;
		} else if(isset($this->moduleFlags[$moduleID])) {
			return $this->moduleFlags[$moduleID];
		}	
		return null;
	}
	
	/**
	 * Get flags for the given module
	 *
	 * @param int|string|Module $id Module to add flag to
	 * @return int|false Returns integer flags on success, or boolean false on fail
	 *
	 */
	public function getFlags($id) {
		$id = ctype_digit("$id") ? (int) $id : $this->modules->getModuleID($id);
		if(isset($this->moduleFlags[$id])) return $this->moduleFlags[$id];
		if(!$id) return false;
		$query = $this->wire()->database->prepare('SELECT flags FROM modules WHERE id=:id');
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		$query->execute();
		if(!$query->rowCount()) return false;
		list($flags) = $query->fetch(\PDO::FETCH_NUM);
		$flags = (int) $flags;
		$this->moduleFlags[$id] = $flags;
		return $flags;
	}

	/**
	 * Does module have flag?
	 *
	 * #pw-internal
	 *
	 * @param int|string|Module $id Module ID, class name or instance
	 * @param int $flag
	 * @return bool
	 * @since 3.0.170
	 *
	 */
	public function hasFlag($id, $flag) {
		$flags = $this->getFlags($id);
		return $flags === false ? false : ($flags & $flag);
	}

	/**
	 * Set module flags
	 *
	 * #pw-internal
	 *
	 * @param string|int $id Module id or class
	 * @param $flags
	 * @return bool
	 *
	 */
	public function setFlags($id, $flags) {
		$flags = (int) $flags;
		$id = ctype_digit("$id") ? (int) $id : $this->modules->getModuleID($id);
		if(!$id) return false;
		if($this->moduleFlags[$id] === $flags) return true;
		$query = $this->wire()->database->prepare('UPDATE modules SET flags=:flags WHERE id=:id');
		$query->bindValue(':flags', $flags);
		$query->bindValue(':id', $id);
		if($this->debug) $this->message("setFlags(" . $this->modules->getModuleClass($id) . ", " . $this->moduleFlags[$id] . " => $flags)");
		$this->moduleFlags[$id] = $flags;
		return $query->execute();
	}

	/**
	 * Add or remove a flag from a module
	 *
	 * #pw-internal
	 *
	 * @param $id int|string|Module $class Module to add flag to
	 * @param $flag int Flag to add (see flags* constants)
	 * @param $add bool $add Specify true to add the flag or false to remove it
	 * @return bool True on success, false on fail
	 *
	 */
	public function setFlag($id, $flag, $add = true) {
		$id = ctype_digit("$id") ? (int) $id : $this->modules->getModuleID($id);
		if(!$id) return false;
		$flag = (int) $flag;
		if(!$flag) return false;
		$flags = $this->getFlags($id);
		if($add) {
			if($flags & $flag) return true; // already has the flag
			$flags = $flags | $flag;
		} else {
			if(!($flags & $flag)) return true; // doesn't already have the flag
			$flags = $flags & ~$flag;
		}
		$this->setFlags($id, $flags);
		return true;
	}
	
	/**
	 * Update module flags if any happen to differ from what's in the given moduleInfo
	 *
	 * @param int $moduleID
	 * @param array $info
	 *
	 */
	public function updateModuleFlags($moduleID, array $info) {

		$flags = (int) $this->getFlags($moduleID);

		if($info['autoload']) {
			// module is autoload
			if(!($flags & Modules::flagsAutoload)) {
				// add autoload flag
				$this->setFlag($moduleID, Modules::flagsAutoload, true);
			}
			if(is_string($info['autoload'])) {
				// requires conditional flag
				// value is either: "function", or the conditional string (like key=value)
				if(!($flags & Modules::flagsConditional)) $this->setFlag($moduleID, Modules::flagsConditional, true);
			} else {
				// should not have conditional flag
				if($flags & Modules::flagsConditional) $this->setFlag($moduleID, Modules::flagsConditional, false);
			}

		} else if($info['autoload'] !== null) {
			// module is not autoload
			if($flags & Modules::flagsAutoload) {
				// remove autoload flag
				$this->setFlag($moduleID, Modules::flagsAutoload, false);
			}
			if($flags & Modules::flagsConditional) {
				// remove conditional flag
				$this->setFlag($moduleID, Modules::flagsConditional, false);
			}
		}

		if($info['singular']) {
			if(!($flags & Modules::flagsSingular)) $this->setFlag($moduleID, Modules::flagsSingular, true);
		} else {
			if($flags & Modules::flagsSingular) $this->setFlag($moduleID, Modules::flagsSingular, false);
		}

		// handle addFlag and removeFlag moduleInfo properties
		foreach(array(0 => 'removeFlag', 1 => 'addFlag') as $add => $flagsType) {
			if(empty($info[$flagsType])) continue;
			if($flags & $info[$flagsType]) {
				// already has the flags
				if(!$add) {
					// remove the flag(s)
					$this->setFlag($moduleID, $info[$flagsType], false);
				}
			} else {
				// does not have the flags
				if($add) {
					// add the flag(s)
					$this->setFlag($moduleID, $info[$flagsType], true);
				}
			}
		}
	}
	
	public function getDebugData() {
		return array(
			'moduleFlags' => $this->moduleFlags
		);
	}
}
