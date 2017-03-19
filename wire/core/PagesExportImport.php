<?php namespace ProcessWire;

/**
 * ProcessWire Pages Export/Import Helpers
 *
 * ProcessWire 3.x, Copyright 2017 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesExportImport extends Wire {
	
	/**
	 * Given a PageArray export it to a portable PHP array
	 *
	 * @param PageArray $items
	 * @param array $options Additional options to modify behavior
	 * @return array
	 *
	 */
	function pagesToArray(PageArray $items, array $options = array()) {

		$defaults = array(
			'verbose' => false,
		);

		$options = array_merge($defaults, $options);
		$options['verbose'] = false; // TMP option not yet supported

		$a = array(
			'type' => 'ProcessWire:PageArray',
			'version' => $this->wire('config')->version,
			'pagination' => array(),
			'pages' => array(),
			'fields' => array(),
		);
		
		if($items->getLimit()) {
			$pageNum = $this->wire('input')->pageNum;
			$a['pagination'] = array(
				'start' => $items->getStart(),
				'limit' => $items->getLimit(),
				'total' => $items->getTotal(),
				'this' => $pageNum, 
				'next' => ($items->getTotal() > $items->getStart() + $items->count() ? $pageNum+1 : false), 
				'prev' => ($pageNum > 1 ? $pageNum - 1 : false)
			);
		} else {
			unset($a['pagination']);
		}

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
		$templates = array();

		foreach($items as $item) {

			$exportItem = $this->pageToArray($item, $options);
			$a['pages'][] = $exportItem;

			// include information about field settings so that warnings can be generated at
			// import time if there are applicable differences in the field settings
			foreach($exportItem['data'] as $fieldName => $value) {
				if(isset($a['fields'][$fieldName])) continue;
				$field = $this->wire('fields')->get($fieldName);
				if(!$field || !$field->type) continue;
				$moduleInfo = $this->wire('modules')->getModuleInfoVerbose($field->type);
				if($options['verbose']) {
					$fieldData = $field->getExportData();
					unset($fieldData['id'], $fieldData['name']);
					$a['fields'][$fieldName] = $fieldData;
				} else {
					$a['fields'][$fieldName] = array(
						'type' => $field->type->className(),
						'version' => $moduleInfo['versionStr']
					);
				}
				$blankValue = $field->type->getBlankValue($item, $field);
				if(is_object($blankValue)) {
					if($blankValue instanceof Wire) {
						$blankValue = "class:" . $blankValue->className();
					} else {
						$blankValue = "class:" . get_class($blankValue);
					}
				}
				$a['fields'][$fieldName]['blankValue'] = $blankValue;
			}

			// include information about template settings so that warnings can be generated
			// at import time if there are applicable differences in the template settings
			if($options['verbose']) {
				if(!isset($templates[$item->template->name])) {
					$templates[$item->template->name] = $item->template->getExportData();
				}
			}

		}

		if($options['verbose']) $a['templates'] = $templates;

		if($languages) $languages->unsetDefault();

		return $a;
	}
	
	/**
	 * Export Page object to an array
	 *
	 * @param Page $page
	 * @param array $options
	 * @return array
	 *
	 */
	protected function pageToArray(Page $page, array $options) {

		$of = $page->of();
		$page->of(false);

		/** @var Languages $languages */
		$languages = $this->wire('languages');
		if($languages) $languages->setDefault();
	
		// standard page settings
		$settings = array(
			'id' => $page->id, // for connection to exported file directories only
			'name' => $page->name,
			'status' => $page->status,
			'sort' => $page->sort,
			'sortfield' => $page->sortfield,
		);

		// verbose page settings
		if(!empty($options['verbose'])) $settings = array_merge($settings, array(
			'parent_id' => $page->parent_id,
			'templates_id' => $page->templates_id,
			'created' => $page->createdStr,
			'modified' => $page->modifiedStr,
			'published' => $page->publishedStr,
			'created_user' => $page->createdUser->name,
			'modified_user' => $page->modifiedUser->name,
		));
		
		// include multi-language page names and statuses when applicable
		if($languages && $this->wire('modules')->isInstalled('LanguageSupportPageNames')) {
			foreach($languages as $language) {
				if($language->isDefault()) continue;
				$settings["name_$language->name"] = $page->get("name$language->id");
				$settings["status_$language->name"] = $page->get("status$language->id");
			}
		}

		// array of export data
		$a = array(
			'path' => $page->path(),
			'template' => $page->template->name,
			'settings' => $settings, 
			'data' => array(),
			'warnings' => array(),
		);
	
		// iterate all fields and export value from each
		foreach($page->template->fieldgroup as $field) {

			/** @var Field $field */
			/** @var Fieldtype $fieldtype */
			$fieldtype = $field->type;
			$schema = $fieldtype->getDatabaseSchema($field);

			if(!isset($schema['xtra']['all']) || $schema['xtra']['all'] !== true) {
				// this fieldtype is storing data outside of the DB or in other unknown tables
				// there's a good chance we won't be able to export/import this into an array
				// @todo check if fieldtype implements its own exportValue/importValue, and if
				// it does then allow the value to be exported
				$a['warnings'][$field->name] = "Skipped '$field' because $field->type uses data outside table '$field->table'";
				continue;
			}

			$value = $page->get($field->name);
			$exportValue = $fieldtype->exportValue($page, $field, $value, array('system' => true));
			$a['data'][$field->name] = $exportValue;
		}

		if($of) $page->of(true);

		return $a;
	}

}
