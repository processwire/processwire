<?php namespace ProcessWire;

/**
 * ProcessWire Page properties helper
 *
 * For static runtime property detection by the base Page class. 
 * The properties/methods in this class were originally in the base Page class
 * but have been moved here for Page class load time optimization purposes. 
 * Except where indicated, please treat these properties as private to the 
 * Page class. 
 *
 * ProcessWire 3.x, Copyright 2024 by Ryan Cramer
 * https://processwire.com
 *
 */

abstract class PageProperties {
	
	const typePage = 1;
	const typePageArray = 2;
	
	/**
	 * Page class helper object instances (one of each helper per ProcessWire instance, lazy loaded)
	 *
	 * @var array
	 *
	 */
	public static $helpers = array();

	/**
	 * Status string shortcuts, so that status can be specified as a word
	 *
	 * See also: Page::getStatuses() method.
	 *
	 * @var array
	 *
	 */
	public static $statuses = array(
		'reserved' => Page::statusReserved,
		'locked' => Page::statusLocked,
		'systemID' => Page::statusSystemID,
		'system' => Page::statusSystem,
		'unique' => Page::statusUnique,
		'draft' => Page::statusDraft,
		'flagged' => Page::statusFlagged,
		'internal' => Page::statusInternal,
		'temp' => Page::statusTemp,
		'hidden' => Page::statusHidden,
		'unpublished' => Page::statusUnpublished,
		'trash' => Page::statusTrash,
		'deleted' => Page::statusDeleted,
		'systemOverride' => Page::statusSystemOverride,
		'corrupted' => Page::statusCorrupted,
		'max' => Page::statusMax,
		'on' => Page::statusOn,
	);

	/**
	 * Properties that can be accessed, mapped to method of access (excluding custom fields of course)
	 *
	 * Keys are base property name, values are one of:
	 *  - [methodName]: method name that it maps to ([methodName]=actual method name)
	 *  - "s": property name is accessible in $this->settings using same key
	 *  - "p": Property name maps to same property name in $this
	 *  - "m": Property name maps to same method name in $this
	 *  - "n": Property name maps to same method name in $this, but may be overridden by custom field
	 *  - "t": Property name maps to PageTraversal method with same name, if not overridden by custom field
	 *  - [blank]: needs additional logic to be handled ([blank]='')
	 *
	 * @var array
	 *
	 */
	public static $baseProperties = array(
		'accessTemplate' => 'getAccessTemplate',
		'addable' => 'm',
		'child' => 'm',
		'children' => 'm',
		'cloneable' => 'm',
		'created' => 's',
		'createdStr' => '',
		'createdUser' => '',
		'created_users_id' => 's',
		'deletable' => 'm',
		'deleteable' => 'm',
		'editable' => 'm',
		'editUrl' => 'm',
		'fieldgroup' => '',
		'filesManager' => 'm',
		'filesPath' => 'm',
		'filesUrl' => 'm',
		'hasChildren' => 'm',
		'hasFiles' => 'm',
		'hasFilesPath' => 'm',
		'hasLinks' => 't',
		'hasParent' => 'parents',
		'hasReferences' => 't',
		'httpUrl' => 'm',
		'id' => 's',
		'index' => 'n',
		'instanceID' => 'p',
		'isHidden' => 'm',
		'isLoaded' => 'm',
		'isLocked' => 'm',
		'isNew' => 'm',
		'isPublic' => 'm',
		'isTrash' => 'm',
		'isUnpublished' => 'm',
		'links' => 'n',
		'listable' => 'm',
		'modified' => 's',
		'modifiedStr' => '',
		'modifiedUser' => '',
		'modified_users_id' => 's',
		'moveable' => 'm',
		'name' => 's',
		'namePrevious' => 'p',
		'next' => 'm',
		'numChildren' => 's',
		'numParents' => 'm',
		'numDescendants' => 'm',
		'numLinks' => 't',
		'numReferences' => 't',
		'output' => 'm',
		'outputFormatting' => 'p',
		'parent' => 'm',
		'parent_id' => '',
		'parentPrevious' => 'p',
		'parents' => 'm',
		'path' => 'm',
		'prev' => 'm',
		'publishable' => 'm',
		'published' => 's',
		'publishedStr' => '',
		'quietMode' => 'p',
		'references' => 'n',
		'referencing' => 't',
		'render' => '',
		'rootParent' => 'm',
		'siblings' => 'm',
		'sort' => 's',
		'sortPrevious' => 'p',
		'sortable' => 'm',
		'sortfield' => 's',
		'status' => 's',
		'statusPrevious' => 'p',
		'statusStr' => '',
		'template' => 'p',
		'templates_id' => '',
		'templatePrevious' => 'p',
		'trashable' => 'm',
		'url' => 'm',
		'urls' => 'm',
		'viewable' => 'm'
	);

	/**
	 * Alternate names accepted for base properties
	 *
	 * Keys are alternate property name and values are base property name
	 *
	 * @var array
	 *
	 */
	public static $basePropertiesAlternates = array(
		'createdUserID' => 'created_users_id',
		'createdUsersID' => 'created_users_id',
		'created_user_id' => 'created_users_id',
		'editURL' => 'editUrl',
		'fields' => 'fieldgroup',
		'has_parent' => 'hasParent',
		'httpURL' => 'httpUrl',
		'modifiedUserID' => 'modified_users_id',
		'modifiedUsersID' => 'modified_users_id',
		'modified_user_id' => 'modified_users_id',
		'num_children' => 'numChildren',
		'numChildrenVisible' => 'hasChildren',
		'numVisibleChildren' => 'hasChildren',
		'of' => 'outputFormatting',
		'out' => 'output',
		'parentID' => 'parent_id',
		'subpages' => 'children',
		'template_id' => 'templates_id',
		'templateID' => 'templates_id',
		'templatesID' => 'templates_id',
	);

	/**
	 * Method alternates/aliases (alias => actual)
	 *
	 * @var array
	 *
	 */
	public static $baseMethodAlternates = array(
		'descendants' => 'find',
		'descendant' => 'findOne',
	);

	/**
	 * Method/property return types
	 * 
	 * @var array
	 * @since 3.0.175
	 * 
	 */
	public static $traversalReturnTypes = array(
		'parent' => self::typePage,
		'rootParent' => self::typePage,
		'child' => self::typePage,
		'next' => self::typePage,
		'prev' => self::typePage,
		'children' => self::typePageArray,
		'parents' => self::typePageArray,
		'siblings' => self::typePageArray,
	);

	/**
	 * Name and status language properties (populated by LanguagesSupport module when applicable)
	 * 
	 * Keys are language property, values array where index 0 is property name and index 1 is language ID.
	 * ~~~~~
	 * [
	 *   'name1234' => [ 'name', 1234 ],
	 *   'status1234' => [ 'status', 1234 ],
	 *   ...
	 * ]
	 * ~~~~~
	 * 
	 * @var array|null
	 * 
	 */
	public static $languageProperties = array();

	/**
	 * Given a status (flags int) return array of status names
	 * 
	 * @param int $status
	 * @return array
	 * 
	 */
	public static function statusToNames($status) {
		$names = array();
		$remainder = $status;
		foreach(self::$statuses as $name => $value) {
			if($value <= Page::statusOn || $value >= Page::statusMax) continue;
			if($status & $value) {
				$names[$value] = $name;
				$remainder = $remainder & ~$value;
			}
		}
		if($remainder > 1) $names[$remainder] = "unknown-$remainder";
		return $names; 
	}
}
