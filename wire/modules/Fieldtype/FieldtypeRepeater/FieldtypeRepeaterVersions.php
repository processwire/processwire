<?php namespace ProcessWire;

/**
 * PagesVersions implementation for FieldtypeRepeater
 * 
 * ProcessWire 3.x, Copyright 2023 by Ryan Cramer
 * https://processwire.com
 * 
 */
class FieldtypeRepeaterVersions extends Wire {
	
	protected $fieldtype;
	
	public function __construct(FieldtypeRepeater $fieldtype) {
		$this->fieldtype = $fieldtype;
		parent::__construct();
	}
	
	/**
	 * @param Page $page
	 * @return int
	 *
	 */
	public function getPageVersionNum(Page $page) {
		return (int) ((string) $page->get('_repeater_version|_version'));
	}

	/**
	 * Does given page or page+field have nested repeater fields?
	 *
	 * #pw-internal
	 *
	 * @param Page|null $page
	 * @param Field|null $field
	 * @param bool $verify Specify true to also verify actual nested repeater items are present
	 * @return bool
	 * @since 3.0.232
	 *
	 */
	public function hasNestedRepeaterFields($page, ?Field $field = null, $verify = false) {
		$has = false;

		if($field === null) {
			if(!$page instanceof Page) return false;
			foreach($page->fieldgroup as $field) {
				/** @var Field $field */
				if(!$field->type instanceof FieldtypeRepeater) continue;
				if($page instanceof RepeaterPage) {
					$has = true;
				} else {
					$has = $this->hasNestedRepeaterFields($page, $field, $verify);
				}
				if($has) break;
			}
			return $has;
		}

		$template = $this->fieldtype->_getRepeaterTemplate($field);
		$repeaterNames = array();

		foreach($template->fieldgroup as $f) {
			if($f->type instanceof FieldtypeRepeater) {
				$repeaterNames[] = $f->name;
			}
		}

		if($verify && count($repeaterNames) && $page) {
			// page has repeater fields
			$items = $this->getRepeaterPageArray($page, $field, $page->get($field->name));
			foreach($items as $item) {
				foreach($repeaterNames as $name) {
					$nestedItems = $item->get($name);
					if($nestedItems && $nestedItems->count()) $has = true;
					if($has) break;
				}
				if($has) break;
			}
		} else {
			$has = count($repeaterNames) > 0;
		}

		return $has;
	}

	/**
	 * Get the value for given page, field and version
	 *
	 * #pw-internal for FieldtypeDoesVersions interface
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return mixed
	 *
	 */
	public function getPageFieldVersion(Page $page, Field $field, $version) {
		$page->setQuietly('_repeater_version', $version);
		/** @var PagesVersions $pagesVersions */
		$pagesVersions = $this->wire('pagesVersions');
		$value = $pagesVersions ? $pagesVersions->getPageFieldVersion($page, $field, $version) : null;
		// $value['parent_id'] = $this->getRepeaterPageParent($page, $field);
		// value = $this->wakeupValue($page, $field, $value);
		// $page->__unset('_repeater_version');
		return $value;
	}

	/**
	 * Save version of given page field
	 *
	 * #pw-internal for FieldtypeDoesVersions interface
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	public function savePageFieldVersion(Page $page, Field $field, $version) {

		$value = $page->get($field->name); /** @var RepeaterPageArray|FieldsetPage $value */
		$value = $this->getRepeaterPageArray($page, $field, $value);
		
		$page = clone $page;
		$page->setQuietly('_repeater_version', $version);

		$parent = $this->fieldtype->getRepeaterPageParent($page, $field, false);

		if(!$parent || !$parent->id) {
			// setup new version
			$pages = $this->wire()->pages;
			$parent = $this->fieldtype->getRepeaterPageParent($page, $field, true); // create
			$cloneOptions = array('uncacheAll' => false);
			$valueClass = $value->className(true);
			$valueCopy = new $valueClass($parent, $field);
			$this->wire($valueCopy);
			foreach($value as $item) {
				/** @var RepeaterPage $item */
				$itemCopy = $pages->clone($item, $parent, false, $cloneOptions);
				$valueCopy->add($itemCopy);
			}
			$valueCopy->resetTrackChanges();
			$page->set($field->name, $valueCopy);
		}

		$result = $this->fieldtype->savePageField($page, $field);

		return $result;
	}

	/**
	 * Restore version of given page field
	 *
	 * #pw-internal for FieldtypeDoesVersions interface
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	public function restorePageFieldVersion(Page $page, Field $field, $version) {
		if(!$version) return false;

		$pages = $this->wire()->pages;

		/** @var PagesVersions $pagesVersions */
		$pagesVersions = $this->wire('pagesVersions');
		if(!$pagesVersions) return false;

		$pageVersionNum = $this->getPageVersionNum($page);

		if($pageVersionNum) {
			$livePage = $pages->getFresh($page->id);
			if($pageVersionNum == $version) {
				$versionPage = $page;
			} else {
				$versionPage = $pagesVersions->getPageVersion($page, $version);
			}
		} else {
			$versionPage = $pagesVersions->getPageVersion($page, $version);
			$livePage = $page;
		}

		$versionRepeaterParent = $this->fieldtype->getRepeaterPageParent($versionPage, $field, false);
		$liveRepeaterParent = $this->fieldtype->getRepeaterPageParent($livePage, $field, false);

		if(!$versionRepeaterParent || !$versionRepeaterParent->id) {
			$this->error(
				"Version repeater parent not found for page:$page field:$field v:$version"
			);
			return false;
		}

		$itemIDs = array();

		if($liveRepeaterParent->id) {
			$this->fieldtype->deleteRepeaterPage($liveRepeaterParent, null, true);
		}

		list($name,) = explode("-v$version", $versionRepeaterParent->name, 2);
		$versionRepeaterParent->addStatus(Page::statusSystemOverride);
		$versionRepeaterParent->removeStatus(Page::statusSystem);
		$versionRepeaterParent->name = $name;
		$versionRepeaterParent->title = preg_replace('/> v\d+/', '> 0', $versionRepeaterParent->title);
		$versionRepeaterParent->save();
		$pages->editor()->addStatus($versionRepeaterParent, Page::statusSystem);

		foreach($versionRepeaterParent->children('include=all') as $item) {
			/** @var RepeaterPage $item */
			if($item->isHidden() && $item->isUnpublished()) continue;
			$itemIDs[] = $item->id;
		}

		$table = $field->getTable();
		$sql =
			"UPDATE $table SET data=:data, count=:count, parent_id=:parent_id " .
			"WHERE pages_id=:pages_id";

		$query = $this->wire()->database->prepare($sql);
		$query->bindValue(':data', implode(',', $itemIDs));
		$query->bindValue(':count', count($itemIDs));
		$query->bindValue(':parent_id', $versionRepeaterParent->id, \PDO::PARAM_INT);
		$query->bindValue(':pages_id', $versionPage->id, \PDO::PARAM_INT);
		$query->execute();

		$page->offsetUnset($field->name);

		return true;
	}

	/**
	 * Delete version
	 *
	 * #pw-internal for FieldtypeDoesVersions interface
	 *
	 * @param Page $page
	 * @param Field $field
	 * @param int $version
	 * @return bool
	 *
	 */
	public function deletePageFieldVersion(Page $page, Field $field, $version) {
		if(!$version) return false;

		/** @var PagesVersions $pagesVersions */
		$pagesVersions = $this->wire('pagesVersions');
		if(!$pagesVersions) return false;

		if($this->getPageVersionNum($page) == $version) {
			$versionPage = $page;
		} else {
			$versionPage = $pagesVersions->getPageVersion($page, $version);
		}

		if(!$versionPage->id) return false;

		$versionRepeaterParent = $this->fieldtype->getRepeaterPageParent($versionPage, $field, false);
		if(!$versionRepeaterParent->id) return false;

		return $this->fieldtype->deleteRepeaterPage($versionRepeaterParent, null, true) > 0;
	}

	/**
	 * Normalize a value to a RepeaterPageArray
	 * 
	 * @param Page $page
	 * @param Field $field
	 * @param RepeaterPage|RepeaterPageArray $value
	 * @return PageArray
	 *
	 */
	protected function getRepeaterPageArray(Page $page, Field $field, $value) {
		$fieldtype = $field->type; /** @var FieldtypeRepeater $fieldtype */
		if($value instanceof PageArray) {
			// great
		} else if($value instanceof RepeaterPage) {
			$item = $value;
			$value = $fieldtype->getBlankValue($page, $field);
			$value->add($item);
		} else {
			$value = $fieldtype->getBlankValue($page, $field);
		}
		return $value;
	}

}
