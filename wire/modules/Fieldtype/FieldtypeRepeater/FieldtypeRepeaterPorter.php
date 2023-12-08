<?php namespace ProcessWire;

/**
 * Export and Import tools for FieldtypeRepeater
 * 
 */
class FieldtypeRepeaterPorter extends Wire {
	
	/**
	 * Export configuration values for external consumption
	 *
	 * Use this method to externalize any config values when necessary.
	 * For example, internal IDs should be converted to GUIDs where possible.
	 * Most Fieldtype modules can use the default implementation already provided here.
	 *
	 * #pw-group-configuration
	 *
	 * @param Field $field
	 * @param array $data
	 * @return array
	 *
	 */
	public function exportConfigData(Field $field, array $data) {

		$template = $this->wire()->templates->get((int) $data['template_id']);

		$data['template_id'] = 0;
		$data['parent_id'] = 0;
		$data['repeaterFields'] = array();
		$data['fieldContexts'] = array();

		$a = $field->get('repeaterFields');
		if(!is_array($a)) $a = array();

		foreach($a as $fid) {
			$f = $this->wire()->fields->get((int) $fid);
			if(!$f) continue;
			$data['repeaterFields'][] = $f->name;
			$data['fieldContexts'][$f->name] = $template->fieldgroup->getFieldContextArray($f->id);
		}

		return $data;
	}

	/**
	 * Convert an array of exported data to a format that will be understood internally
	 *
	 * This is the opposite of the exportConfigData() method.
	 * Most modules can use the default implementation provided here.
	 *
	 * #pw-group-configuration
	 *
	 * @param Field $field
	 * @param array $data
	 * @var array $errors Errors populated to this array
	 * @return array Data as given and modified as needed. Also included is $data[errors], an associative array
	 *	indexed by property name containing errors that occurred during import of config data.
	 *
	 */
	public function importConfigData(Field $field, array $data, array &$errors) {

		$fieldtype = $field->type;
		if(!$fieldtype instanceof FieldtypeRepeater) return $data;

		$fields = $this->wire()->fields;

		$repeaterFields = array();
		$saveFieldgroup = false;
		$saveFieldgroupContext = false;
		$template = $field->id ? $fieldtype->_getRepeaterTemplate($field) : null;

		if(!empty($data['repeaterFields'])) {
			foreach($data['repeaterFields'] as $name) {
				$f = $fields->get($name);
				if(!$f instanceof Field) {
					$errors[] = "Unable to locate field to add to repeater: $name";
					continue;
				}
				$repeaterFields[] = $f->id;
			}
			$data['repeaterFields'] = $repeaterFields;
		}

		if($template && !empty($data['fieldContexts'])) {
			foreach($data['fieldContexts'] as $name => $contextData) {
				$f = $fields->get($name);
				if(!$f instanceof Field) continue;
				if($template->fieldgroup->hasField($f)) {
					$f = $template->fieldgroup->getFieldContext($f->name);
				}
				$template->fieldgroup->add($f);
				$saveFieldgroup = true;
				if(!empty($contextData)) {
					$template->fieldgroup->setFieldContextArray($f->id, $contextData);
					$saveFieldgroupContext = true;
				}
			}
		}

		if($template) {
			if($saveFieldgroupContext) {
				$template->fieldgroup->saveContext();
			}
			if($saveFieldgroup) {
				$template->fieldgroup->save();
			}
		}

		unset($data['fieldContexts']);

		return $data;
	}

	/**
	 * Export repeater value
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param RepeaterPageArray $value
	 * @param array $options
	 *  - `minimal` (bool): Export a minimal array of just fields and values indexed by repeater page name (default=false)
	 * @return array
	 *
	 */
	public function exportValue(Page $page, Field $field, $value, array $options = array()) {

		$a = array();
		if(!WireArray::iterable($value)) return $a;

		if(!empty($options['minimal']) || !empty($options['FieldtypeRepeater']['minimal'])) {
			// minimal export option includes only fields data

			foreach($value as $p) {
				/** @var Page $p */
				if($p->isUnpublished()) continue;
				$v = array();
				foreach($p->template->fieldgroup as $f) {
					/** @var Field $f */
					if(!$p->hasField($f)) continue;
					$fieldtype = $f->type; /** @var Fieldtype $fieldtype */
					$v[$f->name] = $fieldtype->exportValue($p, $f, $p->getUnformatted($f->name), $options);
				}
				$a[$p->name] = $v;
			}

		} else {
			// regular export
			/** @var PagesExportImport $exporter */
			$exporter = $this->wire(new PagesExportImport());
			$a = $exporter->pagesToArray($value, $options);
		}

		return $a;
	}

	/**
	 * Import repeater value previously exported by exportValue()
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param array $value
	 * @param array $options
	 * @return bool|PageArray
	 * @throws WireException
	 *
	 */
	public function importValue(Page $page, Field $field, $value, array $options = array()) {

		if(empty($value['type']) || $value['type'] != 'ProcessWire:PageArray') {
			throw new WireException("$field->name: Invalid repeater importValue() \$value argument");
		}

		if(!$page->id) {
			$page->trackChange($field->name);
			throw new WireException("$field->name: Repeater will import after page is created");
		}
		
		$fieldtype = $field->type; /** @var FieldtypeRepeater $fieldtype */
		$repeaterParent = $fieldtype->getRepeaterPageParent($page, $field);
		$repeaterTemplate = $fieldtype->_getRepeaterTemplate($field);
		$repeaterPageClass = $fieldtype->getPageClass();
		$repeaterPageArrayClass = $fieldtype->getPageArrayClass();
		$parentPath = $repeaterParent->path();
		$commit = isset($options['commit']) ? (bool) $options['commit'] : true;
		$messages = array();
		$numAdded = 0;
		$changesByField = array();
		$numUpdated = 0;
		$numDeleted = 0;
		$itemsAdded = array();
		$itemsDeleted = array();
		$importItemNames = array();
		$existingValue = $page->get($field->name);

		if(!$existingValue instanceof PageArray) { // i.e. FieldsetPage
			$existingValue = $existingValue->id ? array($existingValue) : array();
		}

		$pages = $this->wire()->pages;

		// update paths for local
		foreach($value['pages'] as $key => $item) {
			$name = $item['settings']['name'];
			if(strpos($name, FieldtypeRepeater::repeaterPageNamePrefix) === 0 && count($value['pages']) == 1) {
				$name = FieldtypeRepeater::repeaterPageNamePrefix . $page->id;  // i.e. FieldsetPage	
				$value['pages'][$key]['settings']['name'] = $name;
			}
			$path = $parentPath . $name . '/';
			$importItemNames[$name] = $name;
			$value['pages'][$key]['path'] = $path;
			$p = $pages->get($path);
			if($p->id) continue; // already exists

			// from this point forward, it is assumed we are creating a new repeater item
			$numAdded++;
			$page->trackChange($field->name);

			if($commit) {
				// create new repeater item, ready to be populated
				/** @var RepeaterPage $p */
				$p = $this->wire(new $repeaterPageClass());
				if($repeaterParent->id) $p->parent = $repeaterParent;
				$p->template = $repeaterTemplate;
				$p->name = $name;
				$p->setForPage($page);
				$p->setForField($field);
				$p->save();
				$itemsAdded[$p->id] = $p;
				if($p->name != $name) $importItemNames[$p->name] = $p->name;
			}
		}

		if($page->get('_importType') == 'update') {
			foreach($existingValue as $p) {
				if(!isset($importItemNames[$p->name])) {
					$itemsDeleted[] = $p;
					$numDeleted++;
				}
			}
		}

		/** @var RepeaterPageArray $pageArray */
		$pageArray = $this->wire(new $repeaterPageArrayClass($page, $field));

		$importOptions = array(
			'commit' => $commit,
			'create' => true,
			'update' => true,
			'delete' => true, // @todo 
			'pageArray' => $pageArray
		);

		/** @var PagesExportImport $importer */
		$importer = $this->wire(new PagesExportImport());
		$pageArray = $importer->arrayToPages($value, $importOptions);

		foreach($pageArray as $p) {
			$changes = $p->get('_importChanges');
			if(!count($changes)) continue;
			if(isset($itemsAdded[$p->id]) || !$p->id) continue;
			$numUpdated++;
			foreach($changes as $fieldName) {
				if(!isset($changesByField[$fieldName])) $changesByField[$fieldName] = 0;
				$changesByField[$fieldName]++;
			}
			$this->wire()->notices->move($p, $pageArray, array('prefix' => "$field->name (id=$p->id): "));
		}

		if($numDeleted && $commit) {
			foreach($itemsDeleted as $p) {
				$pages->delete($p);
			}
		}

		if($numUpdated) {
			$updateCounts = array();
			foreach($changesByField as $fieldName => $count) {
				$updateCounts[] = "$fieldName ($count)";
			}
			$messages[] = "$numUpdated page(s) updated – " . implode(', ', $updateCounts);
		}

		if($numAdded) $messages[] = "$numAdded new page(s) added";
		if($numDeleted) $messages[] = "$numDeleted page(s) DELETED";

		foreach($messages as $message) {
			$pageArray->message("$field->name: $message");
		}

		$pageArray->resetTrackChanges();

		$totalChanges = $numUpdated + $numAdded + $numDeleted;
		if(!$totalChanges) {
			// prevent it from being counted as a change when import code sets the value back to the page
			$page->setQuietly($field->name, $pageArray);
		}

		return $pageArray;
	}

}
