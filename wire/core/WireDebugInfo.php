<?php namespace ProcessWire;

/**
 * Helper class for PHP 5.6+ __debugInfo() methods in Wire classes
 * 
 * Class WireDebugInfo
 * 
 */
class WireDebugInfo extends Wire {

	/**
	 * Get all debug info for the given Wire object
	 * 
	 * @param Wire $obj
	 * @return array
	 * 
	 */
	public function getDebugInfo(Wire $obj) {
		
		$className = $obj->className();
		$info = array();
		
		if(method_exists($this, $className)) {
			$info = array_merge($info, $this->$className($obj));
		}

		$changes = $obj->getChanges();
		if(count($changes)) $info['changes'] = $changes; 
		$hooks = $this->getHooksInfo($obj);
		if(count($hooks)) $info['hooks'] = $hooks; 
	
		return $info;
	}

	/**
	 * Get hooks debug info for the given Wire object
	 * 
	 * @param Wire $obj
	 * @return array
	 * 
	 */
	public function getHooksInfo(Wire $obj) {
		$hooks = array();
		foreach($obj->getHooks() as $hook) {
			list($class, $priority) = explode(':', $hook['id']);
			$key = '';
			$value = '';
			if($hook['options']['before']) $key .= "before ";
			if($hook['options']['type'] == 'property') {
				$key .= "property ";
			} else if($hook['options']['after']) {
				if(wireMethodExists($class, $hook['method']) || wireMethodExists($class, '___' . $hook['method'])) {
					$key .= "after ";
				}
			}
			if($hook['options']['type'] == 'property' || !$hook['options']['allInstances']) {
				$key .= "$class" . '->' . "$hook[method]";
			} else {
				$key .= "$class::$hook[method]";
			}
			$filename = '';
			if(!empty($hook['toObject'])) {
				$value .= $hook['toObject']->className() . "->";
				$ref = new \ReflectionClass($hook['toObject']);
				$filename = $ref->getFileName();
			}
			if(!empty($hook['toMethod'])) {
				if(is_string($hook['toMethod'])) {
					$value .= "$hook[toMethod]()";
				} else if(is_callable($hook['toMethod'])) {
					$ref = new \ReflectionFunction($hook['toMethod']);
					$filename = $ref->getFileName();
					$value = "anonymous function()";
				}
			}
			if($filename) $value .= " in " . basename($filename);
			if(!isset($hooks[$key])) {
				$hooks[$key] = $value;
			} else {
				if(!is_array($hooks[$key])) $hooks[$key] = array($hooks[$key]);
				$hooks[$key][] = $value;
			}
		}
		return $hooks;
	}

	/**
	 * Debug info specific to Page objects
	 * 
	 * @param Page $page
	 * @return array
	 * 
	 */
	protected function Page(Page $page) {

		$info = array(
			'instanceID' => $page->instanceID,
			'id' => $page->id, 
			'name' => $page->name,
			'namePrevious' => '', 
			'path' => $page->path(), 
			'status' => implode(', ', $page->status(true)), 
			'statusPrevious' => 0, 
			'template' => $page->template ? $page->template->name : '', 
			'templatePrevious' => '', 
			'parent' => $page->parent ? $page->parent->path : '', 
			'parentPrevious' => '', 
			'numChildren' => $page->numChildren(), 
			'sort' => $page->sort,
			'sortfield' => $page->sortfield,
			'created' => $page->created,
			'modified' => $page->modified,
			'published' => $page->published, 
			'createdUser' => $page->createdUser ? $page->createdUser->name : $page->created_users_id, 
			'modifiedUser' => $page->modifiedUser ? $page->modifiedUser->name : $page->modified_users_id, 
		);

		if($page->namePrevious) {
			$info['namePrevious'] = $page->namePrevious;
		} else {
			unset($info['namePrevious']);
		}

		if($page->statusPrevious !== null) {
			$info['statusPrevious'] = implode(', ', $page->status(true, $page->statusPrevious));
		} else {
			unset($info['statusPrevious']); 
		}

		if($page->templatePrevious) {
			$info['templatePrevious'] = $page->templatePrevious->name;
		} else {
			unset($info['templatePrevious']);
		}

		if($page->parentPrevious) {
			$info['parentPrevious'] = $page->parentPrevious->path();
		} else {
			unset($info['parentPrevious']);
		}

		if($page->isNew) $info['isNew'] = 1;
		$info['isLoaded'] = (int) $page->isLoaded();
		$info['outputFormatting'] = (int) $page->outputFormatting();
		if($page->quietMode) $info['quietMode'] = 1;

		foreach(array('created', 'modified', 'published') as $key) {
			$info[$key] = wireDate($this->wire('config')->dateFormat, $info[$key]) . " " .
				"(" . wireDate('relative', $info[$key]) . ")";
		}
		
		return $info;
	}
}