<?php namespace ProcessWire;

/**
 * ProcessWire Pages Raw Tools
 * 
 * #pw-headline Pages Raw
 * #pw-var $pages->raw
 * #pw-breadcrumb Pages
 * #pw-summary Methods for finding and loading raw page data
 * #pw-body =
 * #pw-body
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 */

class PagesRaw extends Wire {

	/**
	 * @var Pages
	 *
	 */
	protected $pages;

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;
	}

	/**
	 * Find pages and return raw data from them in a PHP array
	 * 
	 * @param string|array|Selectors $selector
	 * @param string|array|Field $field Name of field/property to get, or array of them, CSV string, or omit to get all (default='')
	 *  - Optionally use associative array to rename fields in returned value, i.e. `['title' => 'label']` returns 'title' as 'label' in return value.
	 *  - Specify `parent.field_name` or `parent.parent.field_name`, etc. to return values from parent(s). 3.0.193+
	 *  - Specify `references` or `references.field_name`, etc. to also return values from pages referencing found pages. 3.0.193+
	 *  - Specify `meta` or `meta.name` to also return values from page meta data. 3.0.193+
	 * @param array $options See options for Pages::find
	 *  - `objects` (bool): Use objects rather than associative arrays? (default=false)
	 *  - `entities` (bool|array): Entity encode string values? True, or specify array of field names. (default=false)
	 *  - `nulls` (bool): Populate nulls for field values that are not present, rather than omitting them? (default=false) 3.0.198+
	 *  - `indexed` (bool): Index by page ID? (default=true)
	 *  - `flat` (bool|string): Flatten return value as `["field.subfield" => "value"]` rather than `["field" => ["subfield" => "value"]]`?
	 *     Optionally specify field delimiter, otherwise a period `.` will be used as the delimiter. (default=false) 3.0.193+
	 *  - Note the `objects` and `flat` options are not meant to be used together. 
	 * 
	 * @return array
	 * @since 3.0.172
	 *
	 */
	public function find($selector, $field = '', $options = array()) {
		if(!is_array($options)) $options = array('indexed' => (bool) $options);
		$finder = new PagesRawFinder($this->pages);
		$this->wire($finder);
		return $finder->find($selector, $field, $options);
	}

	/**
	 * Get page (no exclusions) and return raw data from it in a PHP array
	 *
	 * @param string|array|Selectors $selector
	 * @param string|Field|int|array $field Field/property name to get or array of them (or omit to get all)
	 * @param array|bool $options See options for Pages::find
	 *  - `objects` (bool): Use objects rather than associative arrays? (default=false)
	 *  - `entities` (bool|array): Entity encode string values? True, or specify array of field names. (default=false)
	 *  - `indexed` (bool): Index by page ID? (default=false)
	 *  - `flat` (bool|string): Flatten return value as `["field.subfield" => "value"]` rather than `["field" => ["subfield" => "value"]]`?
	 *     Optionally specify field delimiter, otherwise a period `.` will be used as the delimiter. (default=false) 3.0.193+
	 * @return array
	 * @since 3.0.172
	 *
	 */
	public function get($selector, $field = '', $options = array()) {
		if(!is_array($options)) $options = array('indexed' => (bool) $options);
		$options['findOne'] = true;
		if(!isset($options['findAll'])) $options['findAll'] = true;
		$values = $this->find($selector, $field, $options);
		return reset($values);
	}

	/**
	 * Get native pages table column value for given page ID
	 *
	 * This can only be used for native 'pages' table columns,
	 * i.e. id, name, templates_id, status, parent_id, etc.
	 *
	 * @param int|array $pageId Page ID or array of page IDs
	 * @param string|array $col Column name you want to get
	 * @return int|string|array|null Returns column value or array of column values if $pageId was an array.
	 *   When array is returned, it is indexed by page ID.
	 * @param array $options
	 *  - `cache` (bool): Allow use of memory cache to retrieve column value when available? (default=true)
	 *     Used only if $pageId is an integer (not used when array of page IDs).
	 * @throws WireException
	 * @since 3.0.190
	 *
	 *
	 */
	public function col($pageId, $col, array $options = array()) {

		$defaults = array(
			'cache' => true
		);

		$options = array_merge($defaults, $options);

		// delegate to cols() method when arguments require it
		if(is_array($col)) {
			return $this->cols($pageId, $col, $options);
		} else if(is_array($pageId)) {
			$value = array();
			foreach($this->cols($pageId, $col) as $id => $a) {
				$value[$id] = $a[$col];
			}
			return $value;
		}

		if(!ctype_alnum($col)) {
			$sanitizer = $this->wire()->sanitizer;
			if($sanitizer->fieldName($col) !== $col) {
				throw new WireException("Invalid column name: $col");
			}
		}

		$pageId = (int) $pageId;

		// use cached value when available
		if($options['cache']) {
			$page = $this->pages->cacher()->getCache($pageId);
			if($page) return $page->getUnformatted($col);
		}

		$database = $this->wire()->database;
		$col = $database->escapeCol($col);

		$query = $database->prepare("SELECT `$col` FROM pages WHERE id=:id");
		$query->bindValue(':id', $pageId, (int) \PDO::PARAM_INT);
		$query->execute();
		$value = $query->rowCount() ? $query->fetchColumn() : null;
		$query->closeCursor();

		return $value;
	}

	/**
	 * Get native pages table columns (plural) for given page ID
	 *
	 * This can only be used for native 'pages' table columns,
	 * i.e. id, name, templates_id, status, parent_id, etc.
	 *
	 * @param int|array $pageId Page ID or array of page IDs
	 * @param array|string $cols Names of columns to get or omit to get all columns
	 * @param array $options
	 *  - `cache` (bool): Allow use of memory cache to retrieve column value when available? (default=true)
	 *     Used only if $pageId is an integer (not used when array of page IDs).
	 * @return array Returns associative array on success or empty array if not found
	 *   If $pageId argument was an array then it returns a page ID indexed array of
	 *   associative arrays, one for each page.
	 * @throws WireException
	 * @since 3.0.190
	 *
	 */
	public function cols($pageId, $cols = array(), array $options = array()) {

		$defaults = array(
			'cache' => true,
		);

		$options = array_merge($defaults, $options);
		$sanitizer = $this->wire()->sanitizer;
		$database = $this->wire()->database;
		$query = null;
		$removeIdInReturn = false;

		if(!is_array($cols)) $cols = empty($cols) ? array() : array($cols);

		foreach($cols as $key => $col) {
			if(!ctype_alnum($col) && $sanitizer->fieldName($col) !== $col) {
				unset($cols[$key]);
			} else {
				$cols[$key] = $database->escapeCol($col);
			}
		}

		if(count($cols)) {
			$colStr = '`' . implode('`,`', $cols) . '`';
			if(is_array($pageId) && !in_array('id', $cols)) {
				$colStr .= ', id';
				$removeIdInReturn = true;
			}
		} else {
			$colStr = '*';
		}

		if(is_array($pageId)) {
			// multi page
			$ids = array();
			foreach($pageId as $id) {
				$id = (int) $id;
				if($id > 0) $ids[$id] = $id;
			}
			$ids = implode(',', $ids);
			$query = $database->prepare("SELECT $colStr FROM pages WHERE id IN($ids)");
			$query->execute();
			$value = array();
			while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
				$id = (int) $row['id'];
				if($removeIdInReturn) unset($row['id']);
				foreach($row as $k => $v) {
					if(ctype_digit("$v")) $row[$k] = (int) $v;
				}
				$value[$id] = $row;
			}

		} else {
			// single page
			$pageId = (int) $pageId;
			$page = ($options['cache'] ? $this->pages->cacher()->getCache($pageId) : null);
			if($page) {
				$value = array();
				foreach($cols as $col) {
					$value[$col] = $page->get($col);
				}
			} else {
				$query = $database->prepare("SELECT $colStr FROM pages WHERE id=:id");
				$query->bindValue(':id', $pageId, (int) \PDO::PARAM_INT);
				$query->execute();
				$value = $query->rowCount() ? $query->fetch(\PDO::FETCH_ASSOC) : array();
			}
		}

		if($query) $query->closeCursor();

		return $value;
	}
}

/**
 * ProcessWire Pages Raw Finder
 *
 */
class PagesRawFinder extends Wire {
	
	/**
	 * @var Pages
	 *
	 */
	protected $pages;

	/**
	 * @var array
	 * 
	 */
	protected $options = array();

	/**
	 * @var array
	 * 
	 */
	protected $defaults = array(
		'indexed' => true,
		'objects' => false, 
		'entities' => false,
		'nulls' => false,
		'findOne' => false,
		'flat' => false,
	);

	/**
	 * @var string|array|Selectors
	 * 
	 */
	protected $selector = '';

	/**
	 * @var bool
	 * 
	 */
	protected $selectorIsPageIDs = false;

	/**
	 * @var array
	 * 
	 */
	protected $requestFields = array();

	/**
	 * @var array
	 * 
	 */
	protected $nativeFields = array();
	
	/**
	 * @var array
	 * 
	 */
	protected $parentFields = array();

	/**
	 * @var array
	 * 
	 */
	protected $childrenFields = array();
	
	/**
	 * @var array
	 *
	 */
	protected $templateFields = array();

	/**
	 * @var array
	 *
	 */
	protected $customFields = array();

	/**
	 * @var array
	 * 
	 */
	protected $runtimeFields = array();

	/**
	 * Fields to rename in returned value, i.e. [ 'title' => 'label' ]
	 * 
	 * @var array
	 * 
	 */
	protected $renameFields = array();

	/**
	 * Temporary fields set to $this->value that should be unset from return value
	 * 
	 * @var array
	 * 
	 */
	protected $unsetFields = array();

	/**
	 * @var array
	 *
	 */
	protected $customCols = array();

	/**
	 * Columns requested as fieldName.col rather than fieldName[col]
	 * 
	 * (not currently accounted for, future use)
	 * 
	 * @var array
	 * 
	 */
	protected $customDotCols = array();

	/**
	 * Results of the raw find
	 * 
	 * @var array
	 * 
	 */
	protected $values = array();

	/**
	 * True to return array indexed by field name for each page, false to return single value for each page
	 * 
	 * @var bool
	 * 
	 */
	protected $getMultiple = true;

	/**
	 * Get all data for pages?
	 * 
	 * @var bool
	 * 
	 */
	protected $getAll = false;

	/**
	 * Get/join the pages_paths table?
	 * 
	 * @var bool
	 * 
	 */
	protected $getPaths = false;

	/**
	 * IDs of pages to find, becomes array once known
	 * 
	 * @var null|array|string
	 * 
	 */
	protected $ids = null;
	
	/**
	 * Construct
	 *
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;
	}

	/**
	 * @param string|int|array|Selectors
	 * @param string|array|Field $field
	 * @param array $options
	 * 
	 */
	protected function init($selector, $field, $options) {
		
		$fields = $this->wire()->fields;
		$selectorString = '';

		$this->selector = $selector;
		$this->options = array_merge($this->defaults, $options);
		$this->values = array();
		$this->requestFields = array();
		$this->customFields = array();
		$this->nativeFields = array();
		$this->customCols = array();
		$this->getMultiple = true;
		$this->getAll = false;
		$this->ids = null;
	
		if(is_array($selector)) {
			$val = reset($selector);
			$key = key($selector);
			if(ctype_digit("$key") && !is_array($val) && ctype_digit("$val")) $this->selectorIsPageIDs = true;
		} else {
			$selectorString = (string) $selector;
		}
		
		if(empty($field) && !$this->selectorIsPageIDs) {
			// check if field specified in selector instead
			$field = array();
			$multi = false;
			if(!$selector instanceof Selectors) {
				$selector = new Selectors($selector); 
				$this->wire($selector);
			}
			foreach($selector as $item) {
				if(!$item instanceof SelectorEqual) continue;
				$name = $item->field();
				if($name !== 'field' && $name !== 'join' && $name !== 'fields') continue;
				if($name !== 'fields' && $fields->get($name)) continue;
				$value = $item->value;
				if(is_array($value)) {
					$field = array_merge($field, $value);
				} else if($value === 'all') {
					$this->getAll = true;
				} else {
					$field[] = $value;
				}
				$selector->remove($item);
				if($name === 'fields') $multi = true;
			}
			$this->selector = $selector;
			if(!$multi && count($field) === 1) $field = reset($field);
		}
		
		if(empty($field)) {
			$this->getAll = true;
			
		} else if(is_string($field) && strpos($field, ',') !== false) {
			// multiple fields requested in CSV string, we will return an array for each page
			$this->requestFields = explode(',', $field);
			foreach($this->requestFields as $k => $v) {
				$this->requestFields[$k] = trim($v);
			}
			
		} else if(is_array($field)) {
			// one or more fields requested in array, we will return an array for each page
			$this->requestFields = array();
			$this->renameFields = array();
			$this->processRequestFieldsArray($field);
			
		} else {
			// one field requested in string or Field object
			$this->requestFields = array($field);
			$this->getMultiple = false;
		}

		if($this->getAll) {
			if($this->wire()->modules->isInstalled('PagePaths')) {
				$this->getPaths = true;
				$this->runtimeFields['url'] = 'url';
				$this->runtimeFields['path'] = 'path';
			}
		} else {
			// split request fields into nativeFields and customFields
			$this->splitFields();
		}
		
		// detect options in selector
		$optionsValues = array();
		foreach(array('objects', 'entities', 'flat', 'nulls', 'options') as $name) {
			if($this->selectorIsPageIDs) continue;
			if($selectorString && strpos($selectorString, "$name=") === false) continue;
			if($fields->get($name)) continue; // if maps to a real field then ignore
			$result = Selectors::selectorHasField($this->selector, $name, array(
				'operator' => '=',
				'verbose' => true,
				'remove' => true,
			));
			$value = $result['value'];
			if($result['result'] && $value && !isset($options[$name])) {
				if($name === 'options') {
					if(is_string($value)) $optionsValues[] = $value;
					if(is_array($value)) $optionsValues = array_merge($optionsValues, $value);
				} else if(is_array($value)) {
					$this->options[$name] = array();
					foreach($value as $v) $this->options[$name][$v] = $v;
				} else if(!ctype_digit("$value")) {
					$this->options[$name] = $value; 
				} else {
					$this->options[$name] = (bool) ((int) $value);
				}
			}
			if(!empty($result['selectors'])) $this->selector = $result['selectors'];
		}
		foreach(array('objects', 'entities', 'flat') as $name) {
			if(in_array($name, $optionsValues)) $this->options[$name] = true;
		}
	}

	/**
	 * Find pages and return raw data from them in a PHP array
	 *
	 * How to use the `$field` argument:
	 *
	 * - If you provide an array for $field then it will return an array for each page, indexed by
	 *   the field names you requested.
	 *
	 * - If you provide a string (field name) or Field object, then it will return an array with
	 *   the values of the 'data' column of that field.
	 *
	 * - You may request field name(s) like `field.subfield` to retrieve a specific column/subfield.
	 *
	 * - You may request field name(s) like `field.*` to return all columns/subfields for `field`,
	 *   in this case, an associative array value will be returned for each page.
	 * 
	 * - If you specify an associative array for the $field argument, you can optionally rename 
	 *   fields in returned value. For example, if you wanted to get the 'title' field but return 
	 *   it as a field named 'headline' in the return value, you would specify the array 
	 *   `[ 'title' => 'headline' ]` for the $field argument. (3.0.176+)
	 *
	 * @param string|array|Selectors $selector
	 * @param string|Field|int|array $field Field/property name or array of of them
	 * @param array $options See options for Pages::find
	 * @return array
	 * @since 3.0.172
	 *
	 */
	public function find($selector, $field = '', $options = array()) {
		
		static $level = 0;
		$level++;

		$this->init($selector, $field, $options);
		
		if(count($this->parentFields) && !isset($this->nativeFields['parent_id'])) {
			// we need parent_id if finding any parent fields
			$this->nativeFields['parent_id'] = 'parent_id';
			$this->unsetFields['parent_id'] = 'parent_id';
		}
		
		if(count($this->templateFields) && !isset($this->nativeFields['templates_id'])) {
			// we need templates_id if finding any template properties
			$this->nativeFields['templates_id'] = 'templates_id';
			$this->unsetFields['templates_id'] = 'templates_id';
		}
		
		// requested native pages table fields/properties
		if(count($this->nativeFields) || $this->getAll || $this->getPaths) {
			// one or more native pages table column(s) requested
			$this->findNativeFields();
		}
		
		// requested custom fields
		if(count($this->customFields) || $this->getAll) {
			$this->findCustom();
		}
		
		// requested runtime fields
		if(count($this->runtimeFields)) {
			$this->findRuntime();
		}
	
		// requested parent fields
		if(count($this->parentFields)) {
			$this->findParent();
		}
		
		// requested template fields
		if(count($this->templateFields)) {
			$this->findTemplate();
		}

		// remove runtime only fields
		if(count($this->unsetFields)) {
			foreach($this->unsetFields as $name) {
				foreach($this->values as $key => $value) {
					unset($this->values[$key][$name]);
				}
			}
		}

		// reduce return value when expected
		if(!$this->getMultiple) {
			foreach($this->values as $id => $row) {
				$this->values[$id] = reset($row);
			}
		}
		
		if(!$this->options['indexed']) {
			$this->values = array_values($this->values);
		}
		
		if(count($this->renameFields)) {
			$this->renames($this->values);
		}	
		
		if($this->options['entities']) {
			if($this->options['objects'] || $level === 1) {
				$this->entities($this->values);
			}
		}
		
		if($this->options['flat']) {
			$delimiter = is_string($this->options['flat']) ? $this->options['flat'] : '.';
			foreach($this->values as $key => $value) {
				if(is_array($value)) {
					$this->values[$key] = $this->flattenValues($value, '', $delimiter);
				}
			}
		}

		if($this->options['nulls']) {
			$this->populateNullValues($this->values);
		}

		if($this->options['objects']) {
			$this->objects($this->values);
		}
	
		$level--;

		return $this->values;
	}
	
	/**
	 * Split requestFields into native and custom field arrays
	 * 
	 * Populates $this->nativeFields, $this->customFields, $this->customCols
	 * 
	 */
	protected function splitFields() {
		
		$fields = $this->wire()->fields;
		$fails = array();
		$runtimeNames = array('meta', 'references');

		// split request fields into custom fields and native (pages table) fields
		foreach($this->requestFields as $key => $fieldName) {
			
			if(empty($fieldName)) continue;
			if(is_string($fieldName)) $fieldName = trim($fieldName);
			
			$colName = '';
			$dotCol = false;
			$fieldObject = null;
			$fullName = $fieldName;
			
			if($fieldName === '*') {
				// get all (not yet supported)
				
			} else if($fieldName instanceof Field) {
				// Field object
				$fieldObject = $fieldName;
				
			} else if(is_array($fieldName)) {
				// Array where [ 'field' => [ 'subfield' ]] 
				$colName = $fieldName; // array
				$fieldName = $key;
				if($fieldName === 'parent' || $fieldName === 'children' || $fieldName === 'template') {
					// passthru
				} else if(in_array($fieldName, $runtimeNames) && !$fields->get($fieldName)) {
					// passthru
					$this->runtimeFields[$fullName] = $fullName;
					continue;
				} else {
					$fieldObject = isset($this->customFields[$fieldName]) ? $this->customFields[$fieldName] : null;
					if(!$fieldObject) $fieldObject = $fields->get($fieldName);
					if(!$fieldObject) continue;
				}
				
			} else if(is_int($fieldName) || ctype_digit("$fieldName")) {
				// Field ID
				$fieldObject = $fields->get((int) $fieldName);
				
			} else if(is_string($fieldName)) {
				// Field name, subfield/column may optionally be specified as field.subfield
				if(strpos($fieldName, '.')) {
					list($fieldName, $colName) = explode('.', $fieldName, 2);
					$dotCol = true;
				} else if(strpos($fieldName, '[')) {
					list($fieldName, $colName) = explode('[', $fieldName, 2); 
					$colName = rtrim($colName, ']');
				}
				if($fieldName === 'parent' || $fieldName === 'children' || $fieldName === 'template') {
					// passthru
				} else if(in_array($fieldName, $runtimeNames) && !$fields->get($fieldName)) {
					// passthru
					$this->runtimeFields[$fullName] = $fullName;
					continue;
				} else {
					$fieldObject = isset($this->customFields[$fieldName]) ? $this->customFields[$fieldName] : null;
					if(!$fieldObject) $fieldObject = $fields->get($fieldName);
				}
				
			} else {
				// something we do not recognize
				$fails[] = $fieldName;
				continue;
			}
			
			if($fieldName === 'parent') {
				$this->parentFields[$fullName] = $colName;
				
			} else if($fieldName === 'children') {
				// @todo not yet supported
				$this->childrenFields[$fullName] = $colName;

			} else if($fieldName === 'template') {
				$this->templateFields[$fullName] = $colName;

			} else if($fullName === 'url' || $fullName === 'path') {
				if($this->wire()->modules->isInstalled('PagePaths')) {
					$this->runtimeFields[$fullName] = $fullName;
					$this->getPaths = true;
				} else {
					$fails[] = "Property '$fullName' requires the PagePaths module be installed";
				}

			} else if($fieldObject instanceof Field) {
				$this->customFields[$fieldName] = $fieldObject;
				if(!empty($colName)) {
					$colNames = is_array($colName) ? $colName : array($colName);
					foreach($colNames as $col) {
						if(!isset($this->customCols[$fieldName])) $this->customCols[$fieldName] = array();
						$this->customCols[$fieldName][$col] = $col;
						if($dotCol) {
							if(!isset($this->customDotCols[$fieldName])) $this->customDotCols[$fieldName] = array();
							$this->customDotCols[$fieldName][$col] = $col;
						}
					}
				}
			} else {
				$this->nativeFields[$fieldName] = $fieldName;
			}
		}
		
		if(count($fails)) $this->unknownFieldsException($fails); 
	}

	/**
	 * Find raw native fields
	 *
	 */
	protected function findNativeFields() {
		
		$this->ids = array();
		$allNatives = array();
		$fails = array();
		$rootUrl = $this->wire()->config->urls->root;
		$templates = $this->wire()->templates;
		$templatesById = array();
		$getPaths = $this->getPaths;
		
		if(empty($this->selector)) return;
		
		foreach($this->findIDs($this->selector, '*') as $row) {
			$id = (int) $row['id'];
			$this->ids[$id] = $id;
			$this->values[$id] = isset($this->values[$id]) ? array_merge($this->values[$id], $row) : $row;
			if(empty($allNatives)) {
				foreach(array_keys($row) as $key) {
					$allNatives[$key] = $key;
				}
			}
		}
		
		if(!count($this->values)) return;
		
		if($this->getAll) $this->nativeFields = $allNatives;
		
		// native columns we will populate into $values
		$getNatives = array();

		foreach($this->nativeFields as $fieldName) {
			if($fieldName === '*' || $fieldName === 'pages' || $fieldName === 'pages.*') {
				// get all columns
				$colName = '';
			} else if(strpos($fieldName, 'pages.') === 0) {
				// pages table column requested by name
				list(,$colName) = explode('.', $fieldName, 2);
			} else {
				// column requested by name on its own
				$colName = $fieldName;
			}
			if(empty($colName)) {
				// get all native pages table columns
				$getNatives = $allNatives;
			} else if(isset($allNatives[$colName])) {
				// get specific native pages table columns
				$getNatives[$colName] = $colName;
			} else {
				// fieldName is not a known field or pages column
				$fails[] = "$fieldName";
			}
		}
		
		if(count($fails)) $this->unknownFieldsException($fails, 'column/field');
		
		if(!count($getNatives) && !$getPaths) return;
		
		// remove any native data that is present but was not requested and populate any runtime fields 
		foreach($this->values as $id => $row) {
			$templateId = (int) $row['templates_id'];
			foreach($row as $colName => $value) {
				if($getPaths && $colName === 'path') {
					// populate path and/or url runtime properties 
					if(!isset($templatesById[$templateId])) $templatesById[$templateId] = $templates->get($templateId);
					$template = $templatesById[$templateId]; /** @var Template $template */
					$slash = $template->slashUrls ? '/' : '';
					$path = strlen("$value") && $value !== '/' ? "$value$slash" : '';
					if(isset($this->runtimeFields['url'])) {
						$this->values[$id]['url'] = $rootUrl . $path;
					}
					if(isset($this->runtimeFields['path'])) {
						$this->values[$id]['path'] = "/$path";
					} else {
						unset($this->values[$id]['path']); 
					}
				} else if(!isset($getNatives[$colName])) {
					unset($this->values[$id][$colName]);
				}
			}
		}
	
	}

	/**
	 * Gateway to finding custom fields whether specific, all or none
	 * 
	 */
	protected function findCustom() {
		if(count($this->customFields)) {
			// one or more custom fields requested
			if($this->ids === null && !empty($this->selector)) {
				// only find IDs if we didn’t already in the nativeFields section
				$this->setIds($this->findIDs($this->selector, false));
			}
			if(empty($this->ids)) return;
			foreach($this->customFields as $fieldName => $field) {
				/** @var Field $field */
				$cols = isset($this->customCols[$fieldName]) ? $this->customCols[$fieldName] : array();
				$this->findCustomField($field, $cols);
			}
		} else if($this->getAll && !empty($this->ids)) {
			$this->findCustomAll();
		}
	}

	/**
	 * Find raw custom field
	 *
	 * @param Field $field
	 * @param array $cols
	 * @throws WireException
	 *
	 */
	protected function findCustomField(Field $field, array $cols) {

		$database = $this->wire()->database;
		$sanitizer = $this->wire()->sanitizer;
		$getArray = true;
		$getCols = array();
		$skipCols = array();
		$getAllCols = false;
		$getExternal = false; // true when request includes columns not in field’s DB schema
		$pageRefCols = array();
		$externalCols = array(); // columns that are external from field’s DB schema

		/** @var FieldtypeMulti $fieldtypeMulti */
		$fieldtype = $field->type;
		$fieldtypeMulti = $field->type instanceof FieldtypeMulti ? $fieldtype : null;
		$fieldtypePage = $fieldtype instanceof FieldtypePage ? $fieldtype : null;
		$fieldtypeRepeater = $fieldtype instanceof FieldtypeRepeater ? $fieldtype : null;
		
		$fieldName = $field->name;
		$schema = $fieldtype->getDatabaseSchema($field);
		$schema = $fieldtype->trimDatabaseSchema($schema, array('trimDefault' => false));
		$table = $database->escapeTable($field->getTable());
		$sorts = array();

		if(empty($table) || empty($schema) || $fieldtype instanceof FieldtypeFieldsetOpen) return;

		if(empty($cols)) { 
			// no cols specified
			$trimSchema = $fieldtype->trimDatabaseSchema($schema, array('trimDefault' => true, 'trimMeta' => true));
			unset($trimSchema['data']); 
			foreach($trimSchema as $key => $value) {
				// multi-language columns do not count as custom schema
				if(strpos($key, 'data') === 0 && ctype_digit(substr($key, 4))) unset($trimSchema[$key]); 
			}	
			if(empty($trimSchema)) {
				// if table doesn’t maintain a custom schema, just get data column
				$getArray = false;
				$getCols[] = 'data';
			} else {
				// table maintains custom schema, get all columns
				$getAllCols = true;
				$skipCols[] = 'pages_id';
			}
			
		} else if(reset($cols) === '*') {
			$getAllCols = true;
			if(wireInstanceOf($field->type, 'FieldtypeOptions')) $getExternal = true;
			
		} else {
			foreach($cols as $col) {
				$col = $sanitizer->name($col);
				if(empty($col)) continue;
				if(isset($schema[$col])) {
					$getCols[$col] = $database->escapeCol($sanitizer->fieldName($col));
				} else if($fieldtypePage || $fieldtypeRepeater) {
					$pageRefCols[$col] = $col;
				} else {
					// unknown or external column
					$getCols['data'] = 'data';
					$externalCols[$col] = $col;
					$getExternal = true;
				}
			}
			if(count($pageRefCols)) {
				// get just the data column when a field within a Page reference is asked for
				$getCols['data'] = 'data';
			}
			if(count($getCols) === 1 && !$this->getMultiple && count($externalCols) < 2) {
				// if only getting single field we will populate its value rather than 
				// its value in an associative array
				$getArray = false;
			}
		}

		if($fieldtypeMulti) {
			$orderByCols = $fieldtypeMulti->get('orderByCols');
			if($fieldtypeMulti->useOrderByCols && !empty($orderByCols)) {
				foreach($orderByCols as $key => $col) {
					$desc = strpos($col, '-') === 0 ? ' DESC' : '';
					$col = $sanitizer->fieldName(ltrim($col, '-'));
					if(!array_key_exists($col, $schema)) continue;
					$sorts[$key] = '`' . $database->escapeCol($col) . '`' . $desc;
				}
			}
			if(empty($sorts) && isset($schema['sort'])) {
				$sorts[] = "`sort`";
			}
		}

		$this->ids(true); // converts this->ids to CSV string
		$idsCSV = &$this->ids;
		if(empty($idsCSV)) return;
		$colSQL = $getAllCols ? '*' : '`' . implode('`,`', $getCols) . '`';
		if(!$getAllCols && !in_array('pages_id', $getCols)) $colSQL .= ',`pages_id`';
		
		$orderby = array();
		if(!count($this->nativeFields)) $orderby[] = "FIELD(pages_id, $idsCSV)";
		if(count($sorts)) $orderby[] = implode(',', $sorts);
		
		$sql = "SELECT $colSQL FROM `$table` WHERE pages_id IN($idsCSV) ";
		if(count($orderby)) $sql .= "ORDER BY " . implode(',', $orderby);
		
		$query = $database->prepare($sql);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			
			$id = $row['pages_id'];
			
			if(!$getAllCols && !isset($getCols['pages_id'])) unset($row['pages_id']);
			
			foreach($skipCols as $skipCol) {
				unset($row[$skipCol]);
			}
			
			if($getAllCols) {
				$value = $row;
			} else if($getArray) {
				$value = array();
				foreach($getCols as $col) {
					$value[$col] = isset($row[$col]) ? $row[$col] : null;
				}
			} else {
				$col = reset($getCols);
				if(empty($col)) $col = 'data';
				$value = $row[$col];
			}
			
			if(!isset($this->values[$id])) {
				// Overall page placeholder array
				$this->values[$id] = array();
			}
		
			if($fieldtypeMulti) {
				// FieldtypeMulti types may contain multiple rows

				/** @var FieldtypeMulti $fieldtype */
				if(!isset($this->values[$id][$fieldName])) {
					$this->values[$id][$fieldName] = array();
				}

				if($fieldtypePage && count($pageRefCols)) {
					// reduce page reference to just the IDs, indexed by IDs
					if(isset($value['data'])) $value = $value['data'];
					$this->values[$id][$fieldName][$value] = $value;
				} else {
					$this->values[$id][$fieldName][] = $value;
				}
				
			} else if($fieldtypeRepeater && count($pageRefCols)) {
				$repeaterIds = isset($value['data']) ? explode(',', $value['data']) : explode(',', $value);
				foreach($repeaterIds as $repeaterId) {
					$this->values[$id][$fieldName][$repeaterId] = $repeaterId;
				}
				
			} else {
				$this->values[$id][$fieldName] = $value;
			}
		}

		$query->closeCursor();

		if(count($pageRefCols)) {
			if($fieldtypePage) {
				$this->findCustomFieldtypePage($field, $fieldName, $pageRefCols);
			} else if($fieldtypeRepeater) {
				$this->findCustomFieldtypePage($field, $fieldName, $pageRefCols);
			}
		}
		
		if($getExternal) {
			if(wireInstanceOf($fieldtype, 'FieldtypeOptions')) {
				$this->findCustomFieldtypeOptions($field, $externalCols, $getArray, $getAllCols);
			}
		}
	}

	/**
	 * Find custom Options fieldtype columns
	 * 
	 * Options field stores its values/titles in separate table.
	 * 
	 * To use, specify one of the following in the fields to get (where field_name is an options field name):
	 * 
	 * - `field_name` to just include the IDs of the selected options for each page.
	 * - `field_name.*` to include all available properties for selected options for each page.
	 * - `field_name.title` to include the selected option titles.
	 * - `field_name.value` to include the selected option values. 
	 *
	 * @param Field $field
	 * @param array $cols
	 * @param bool $getArray
	 * @param bool $getAllCols
	 * @since 3.0.193
	 *
	 */
	protected function findCustomFieldtypeOptions(Field $field, $cols, $getArray, $getAllCols) {
		/** @var FieldtypeOptions $fieldtype */
		$fieldtype = $field->type;
		$fieldName = $field->name;
		$options = $fieldtype->getOptions($field);
		$firstColName = reset($cols);

		foreach($this->values as $pageId => $data) {
			if(!isset($data[$fieldName])) continue;
			foreach($data[$fieldName] as $key => $optionValue) {
				if(is_array($optionValue)) {
					$optionId = (int) $optionValue['data'];
				} else if(ctype_digit("$optionValue")) {
					$optionId = (int) $optionValue;
				} else {
					continue; // not likely
				}
				/** @var SelectableOption $option */
				$option = $options->get((int) $optionId);
				if(!$option) {
					// unknown option
				} else if($getAllCols) {
					$a = $option->getArray();
					unset($a['sort']);
					$this->values[$pageId][$fieldName][$key] = $a;
				} else if($getArray) {
					$this->values[$pageId][$fieldName][$key] = array();
					foreach($cols as $colName) {
						$value = $option->get($colName);
						if(!is_string($value) && !is_int($value)) $value = null;
						$this->values[$pageId][$fieldName][$key][$colName] = $option->get($colName);
					}
				} else {
					$value = $option->get($firstColName); // i.e. title, value, id, title1234, etc.
					$this->values[$pageId][$fieldName][$key] = (is_string($value) || is_int($value) ? $value : null);
				}
			}
		}
	}

	/**
	 * Find and apply values for Page reference fields
	 * 
	 * @param Field $field
	 * @param string $fieldName
	 * @param array $pageRefCols
	 * 
	 */
	protected function findCustomFieldtypePage(Field $field, $fieldName, array $pageRefCols) {
		$pageRefIds = array();
		foreach($this->values as /* $pageId => */ $row) {
			if(!isset($row[$fieldName])) continue;
			$pageRefIds = array_merge($pageRefIds, $row[$fieldName]);
		}

		if(!$this->getMultiple && count($pageRefCols) === 1) {
			$pageRefCols = implode('', $pageRefCols);
		}

		$pageRefIds = array_unique($pageRefIds);
		$finder = new PagesRawFinder($this->pages);
		$this->wire($finder);
		$options = $this->options;
		$options['indexed'] = true;
		$pageRefRows = count($pageRefIds) ? $finder->find($pageRefIds, $pageRefCols, $options) : array();

		foreach($this->values as $pageId => $pageRow) {
			if(!isset($pageRow[$fieldName])) continue;
			foreach($pageRow[$fieldName] as $pageRefId) {
				if(!isset($pageRefRows[$pageRefId])) continue;
				$this->values[$pageId][$fieldName][$pageRefId] = $pageRefRows[$pageRefId];
			}
			if(!$this->getMultiple && $field->get('derefAsPage') > 0) {
				$this->values[$pageId][$fieldName] = reset($this->values[$pageId][$fieldName]); 
			} else if(empty($this->options['indexed'])) {
				$this->values[$pageId][$fieldName] = array_values($this->values[$pageId][$fieldName]);
			}
		}
	}
	
	/**
	 * Find/populate all custom fields
	 *
	 */
	protected function findCustomAll() {

		$idsByTemplate = array();

		foreach($this->ids() as $id) {
			if(!isset($this->values[$id])) continue;
			$row = $this->values[$id];
			$templateId = $row['templates_id'];
			if(!isset($idsByTemplate[$templateId])) $idsByTemplate[$templateId] = array();
			$idsByTemplate[$templateId][$id] = $id;
		}

		foreach($idsByTemplate as $templateId => $pageIds) {
			$template = $this->wire()->templates->get($templateId);
			if(!$template) continue;
			foreach($template->fieldgroup as $field) {
				$this->findCustomField($field, array());
			}
		}
	}

	/**
	 * Find and apply values for parent.[field]
	 * 
	 * @since 3.0.193
	 * 
	 */
	protected function findParent() {
		
		$ids = array();
		
		foreach($this->values as $pageId => $data) {
			$parentId = $data['parent_id'];
			if(!isset($ids[$parentId])) $ids[$parentId] = array();
			$ids[$parentId][] = $pageId;
		}
		
		$finder = new PagesRawFinder($this->pages);
		$this->wire($finder);
		$options = $this->options;
		$options['indexed'] = true;
		$parentFields = array_values($this->parentFields);
		
		if(!$this->getMultiple && count($parentFields) < 2) {
			$parentFields = reset($parentFields);
		}
		
		$rows = $finder->find(array_keys($ids), $parentFields, $options);
		
		foreach($rows as $parentId => $row) {
			foreach($ids[$parentId] as $pageId) {
				$this->values[$pageId]['parent'] = $row;
			}
		}
	}
	
	/**
	 * Find and apply values for template.[property]
	 *
	 * @since 3.0.206
	 *
	 */
	protected function findTemplate() {

		$templates = $this->wire()->templates;
		$templateFields = $this->templateFields;
		$templateData = array();
		$templateIds = array();

		foreach($this->values as /* $pageId => */ $data) {
			$templateId = $data['templates_id'];
			if(!isset($templateIds[$templateId])) $templateIds[$templateId] = $templateId;
		}

		foreach($templateIds as $templateId) {
			$template = $templates->get($templateId);
			$templateData[$templateId] = array();
			foreach($templateFields as /* $fullName => */ $colName) {
				if(empty($colName)) $colName = 'name';
				$value = $template->get($colName);
				if(is_object($value)) continue; // object values not allowed here
				$templateData[$templateId][$colName] = $value;
			}
		}

		if(!$this->getMultiple && count($this->templateFields) < 2) {
			$colName = reset($this->templateFields);
			foreach($templateData as $templateId => $data) {
				$templateData[$templateId] = $data[$colName];
			}
		}
		
		foreach($this->values as $pageId => $data) {
			$templateId = $data['templates_id'];
			$this->values[$pageId]['template'] = $templateData[$templateId];
		}
	}

	/**
	 * Find runtime generated fields
	 *
	 * @since 3.0.193
	 *
	 */
	protected function findRuntime() {

		$runtimeFields = array();
		$fieldNames = $this->runtimeFields;

		unset($fieldNames['url'], $fieldNames['path']);

		if(empty($fieldNames)) return;

		if($this->ids === null) {
			$this->setIds($this->findIDs($this->selector, false));
		}
		
		foreach($fieldNames as $fieldName) {
			$colName = '';
			if(strpos($fieldName, '.')) list($fieldName, $colName) = explode('.', $fieldName, 2);
			if(!isset($runtimeFields[$fieldName])) $runtimeFields[$fieldName] = array();
			if($colName) $runtimeFields[$fieldName][] = $colName;
		}

		if(isset($runtimeFields['meta'])) {
			$this->findMeta($runtimeFields['meta']);
		}

		if(isset($runtimeFields['references'])) {
			$this->findReferences($runtimeFields['references']);
		}
	}

	/**
	 * Populate 'meta' to (form pages_meta table) to the result values
	 *
	 * @param array $names
	 * @since 3.0.193
	 *
	 */
	protected function findMeta(array $names) {

		if(empty($this->ids)) return;
		$this->ids(true);

		$getAll = $this->getAll || in_array('*', $names, true) || empty($names);
		if($getAll) $names = array();

		$sql = "SELECT source_id, name, data FROM pages_meta WHERE source_id IN($this->ids)";
		$query = $this->wire()->database->prepare($sql);
		$query->execute();

		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$id = (int) $row['source_id'];
			$name = $row['name'];
			$data = json_decode($row['data'], true);
			if(!isset($this->values[$id]['meta'])) $this->values[$id]['meta'] = array();
			if($getAll || in_array($name, $names, true)) $this->values[$id]['meta'][$name] = $data;
		}
		
		foreach(array_keys($this->values) as $id) {
			if(!isset($this->values[$id]['meta'])) $this->values[$id]['meta'] = array();
		}

		$query->closeCursor();
	}


	/**
	 * Populate a 'references' to the raw results that includes other pages referencing the found ones
	 * 
	 * To use this specify `references` in the fields to return. Or, to get page references that are 
	 * indexed by field name, specify `references.field` instead. To get something more than the id
	 * of page references, specify properties or fields as `references.field_name` replacing `field_name`
	 * with a page property or field name, i.e. `references.title`. 
	 * 
	 * @param array $colNames
	 * @since 3.0.193
	 * 
	 */
	protected function findReferences(array $colNames) {
		
		$database = $this->wire()->database;
		$pageFields = array();
		
		if(empty($this->ids)) return;
		
		foreach($this->wire()->fields as $field) {
			if($field->type instanceof FieldtypePage) $pageFields[$field->name] = $field;
		}
		
		if(empty($pageFields)) return;
		
		foreach($this->values as $id => $data) {
			$this->values[$id]['references'] = array();
		}

		$showField = array_search('field', $colNames);
		if($showField !== false) {
			unset($colNames[$showField]);
			$showField = true;
		}

		$this->ids(true);
		$fromPageIds = array();
		$findPageIds = array();
		
		foreach($pageFields as $pageField) {
			$fieldName = $pageField->name;
			
			/** @var Field $pageField */
			$table = $pageField->getTable();
			$sql = "SELECT pages_id, data FROM $table WHERE data IN($this->ids)";
			$query = $database->prepare($sql);
			$query->execute();
			
			while($row = $query->fetch(\PDO::FETCH_NUM)) {
				$fromPageId = (int) $row[0]; // pages_id
				$toPageId = (int) $row[1]; // data
				if(!isset($fromPageIds[$toPageId])) $fromPageIds[$toPageId] = array();
				if(!isset($fromPageIds[$toPageId][$fieldName])) $fromPageIds[$toPageId][$fieldName] = array();
				$fromPageIds[$toPageId][$fieldName][] = $fromPageId;
				$findPageIds[] = $fromPageId;
			}
			
			$query->closeCursor();
		}

		if(!count($findPageIds)) return;
		
		if(empty($colNames)) {
			// shortcut: we only need to include the ids 
			foreach($this->values as $toPageId => $data) {
				if(!isset($fromPageIds[$toPageId])) continue;
				if($showField) {
					$references = $fromPageIds[$toPageId];
				} else {
					$references = array();
					foreach($fromPageIds[$toPageId] as /* $fieldName => */ $ids) {
						$references = array_merge($references, $ids);
					}
				}
				if(!$this->options['indexed']) $references = array_values($references);
				$this->values[$toPageId]['references'] = $references;
			}
			return;
		}

		// load properties/fields from found references
		$finder = new PagesRawFinder($this->pages);
		$this->wire($finder);
		$options = $this->options;
		$options['indexed'] = true;
		$colNames = $this->getMultiple || count($colNames) > 1 ? $colNames : reset($colNames);
		$rows = $finder->find($findPageIds, $colNames, $options);
		
		foreach($this->values as $toPageId => $data) {
			if(!isset($fromPageIds[$toPageId])) continue;
			foreach($fromPageIds[$toPageId] as $fieldName => $fromIds) {
				foreach($fromIds as $fromId) {
					if(!isset($rows[$fromId])) continue;
					$row = $rows[$fromId];
					if($showField) {
						if(!isset($this->values[$toPageId]['references'][$fieldName])) {
							$this->values[$toPageId]['references'][$fieldName] = array();
						}
						if($this->options['indexed']) {
							$this->values[$toPageId]['references'][$fieldName][$fromId] = $row;
						} else {
							$this->values[$toPageId]['references'][$fieldName][] = $row;
						}
					} else {
						if($this->options['indexed']) {
							$this->values[$toPageId]['references'][$fromId] = $row;
						} else {
							$this->values[$toPageId]['references'][] = $row;
						}
					}
				}
			}
		}
	}

	/**
	 * Front-end to pages.findIDs that optionally accepts array of page IDs
	 * 
	 * @param array|string|Selectors $selector
	 * @param bool|string $verbose One of true, false, or '*'
	 * @param array $options
	 * @return array
	 * @throws WireException
	 * 
	 */
	protected function findIDs($selector, $verbose, array $options = array()) {
		
		$options = array_merge($this->options, $options); 
		$options['verbose'] = $verbose;
		$options['indexed'] = true;
		$options['joinPath'] = $this->getPaths;
	
		// if selector was just a page ID, return it in an id indexed array
		if(is_int($selector) || (is_string($selector) && ctype_digit($selector))) {
			$id = (int) $selector;
			return array($id => $id); 
		}

		// if selector is not array of page IDs then let pages.findIDs handle it
		if(!is_array($selector) || !isset($selector[0]) || is_array($selector[0]) || !ctype_digit((string) $selector[0])) {
			return $this->pages->findIDs($selector, $options);
		}
		
		// at this point selector is an array of page IDs

		if(empty($verbose)) {
			// if selector already has what is needed and verbose data not needed,
			// then return it now, but make sure it is indexed by ID first
			$a = array();
			foreach($selector as $id) $a[(int) $id] = (int) $id;
			return $a;
		}

		// convert selector to CSV string of page IDs
		$selector = implode(',', array_map('intval', $selector));
		
		$selects = array();
		$joins = array();
		$wheres = array("id IN($selector)");
		
		if($verbose === '*') {
			// get all columns
			$selects[] = 'pages.*';
		} else {
			// get just base columns
			$selects = array('pages.id', 'pages.templates_id', 'pages.parent_id'); 
		}
		
		if($this->getPaths) {
			$selects[] = 'pages_paths.path AS path';
			$joins[] = 'LEFT JOIN pages_paths ON pages_paths.pages_id=pages.id';
		}

		$sql = 
			"SELECT " . implode(', ', $selects) . " " . 
			"FROM pages " .
			(count($joins) ? implode(' ', $joins) . " " : '') . 
			"WHERE " . implode(' ', $wheres);
		
		$query = $this->wire()->database->prepare($sql);
		$query->execute();
		$rows = array();
		
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$id = (int) $row['id'];
			$rows[$id] = $row;
		}
		
		$query->closeCursor();
		
		return $rows;
	}

	/**
	 * Convert associative arrays to objects
	 * 
	 * @param array $values
	 * 
	 */
	protected function objects(&$values) {
		foreach(array_keys($values) as $key) {
			$value = $values[$key];
			if(!is_array($value)) continue;
			reset($value);
			if(is_int(key($value))) continue;
			$this->objects($value);
			$values[$key] = (object) $value;
		}
	}

	/**
	 * Apply entity encoding to all strings in given value, recursively
	 * 
	 * @param mixed $value
	 * 
	 */
	protected function entities(&$value) {
		$prefix = ''; // populate for testing only
		if(is_string($value)) {
			// entity-encode
			$value = $prefix . htmlentities($value, ENT_QUOTES, 'UTF-8');
		} else if(is_array($value)) {
			// iterate and go recursive
			foreach(array_keys($value) as $key) {
				if(is_array($value[$key])) {
					$this->entities($value[$key]);
				} else if(is_string($value[$key])) {
					if($this->options['entities'] === true || $this->options['entities'] === $key || isset($this->options['entities'][$key])) {
						$value[$key] = $prefix . htmlentities($value[$key], ENT_QUOTES, 'UTF-8');
					} 
				}
			}
		} else {
			// leave as-is
		}
	}

	/**
	 * Rename fields on request
	 * 
	 * @param array $values
	 * @since 3.0.167
	 * 
	 */
	protected function renames(&$values) {
		foreach($values as $key => $value) {
			if(!is_array($value)) continue;
			foreach($value as $k => $v) {
				if(is_array($v)) $this->renames($v);
				if(isset($this->renameFields[$k])) {
					unset($values[$key][$k]);
					$name = $this->renameFields[$k];
				} else {
					$name = $k;
				}
				$values[$key][$name] = $v;
			}
		}
	}

	/**
	 * Get or convert $this->ids to/from CSV
	 * 
	 * The point of this is just to minimize the quantity of copies of IDs we are keeping around. 
	 * In case the quantity gets to be huge, it'll be more memory friendly. 
	 * 
	 * @param bool $csv
	 * @return array|string
	 * 
	 */
	protected function ids($csv = false) {
		if($this->ids === null) return $csv ? '' : array();
		if($csv) {
			if(is_array($this->ids)) $this->ids = implode(',', array_map('intval', $this->ids));
		} else if(is_string($this->ids)) {
			// this likely cannot occur with current logic but here in case that changes
			$this->ids = explode(',', $this->ids);
		}
		return $this->ids;
	}

	/**
	 * Set the found IDs and init the $this->values array
	 * 
	 * @param array $ids
	 * @since 3.0.193
	 * 
	 */
	protected function setIds(array $ids) {
		$this->ids = $ids;
		foreach($ids as $id) {
			$this->values[$id] = array();
		}
	}

	/**
	 * Flatten multidimensional values from array['a']['b']['c'] to array['a.b.c']
	 * 
	 * @param array $values
	 * @param string $prefix Prefix for recursive use
	 * @param string $delimiter 
	 * @return array
	 * @since 3.0.193
	 * 
	 */
	protected function flattenValues(array $values, $prefix = '', $delimiter = '.') {
		
		$flat = array();
		
		foreach($values as $key => $value) {
			
			if(!is_array($value)) {
				if(ctype_digit("$key") && $prefix) {
					// integer keys map to array values
					$k = rtrim($prefix, $delimiter);
					if(!isset($flat[$k])) $flat[$k] = array();
					if(!is_array($flat[$k])) $flat[$k] = array($flat[$k]);
					$flat[$k][$key] = $value;
				} else {
					$flat["$prefix$key"] = $value;
				}	
				continue;
			}
			
			$a = $this->flattenValues($value, "$prefix$key$delimiter", $delimiter);
			
			if(!is_int($key)) {
				$flat = $flat + $a;
				continue;
			}
			
			$converted = false;

			// convert categories.1234.title => categories.title = array(1234 => 'title', ...);
			foreach($a as $k => $v) {
				if(strpos($k, "$delimiter$key$delimiter") === false) continue;
				list($k1, $k2) = explode("$delimiter$key$delimiter", $k); 
				unset($a[$k]);
				$kk = "$k1$delimiter$k2";
				if(!isset($flat[$kk])) $flat[$kk] = array();
				$flat[$kk][$key] = $v;
				$converted = true;
			}
			
			if(!$converted) $flat = $flat + $a;
		}
		return $flat;
	}

	/**
	 * Populate null values for requested fields that were not present (the 'nulls' option)
	 * 
	 * Applies only if specific fields were requested. 
	 * 
	 * @var array $values
	 * @since 3.0.198
	 * 
	 */
	protected function populateNullValues(&$values) {
		$emptyValue = array();
		if(count($this->requestFields)) {
			// specific fields requested
			foreach($this->requestFields as $name) {
				if(isset($this->renameFields[$name])) $name = $this->renameFields[$name];
				if(!$this->options['flat'] && strpos($name, '.')) list($name,) = explode('.', $name, 2);
				$emptyValue[$name] = null;
			}
			foreach($values as $key => $value) {
				$values[$key] = array_merge($emptyValue, $value);
			}
		} else {
			// all fields requested
			$templates = $this->wire()->templates;
			$emptyValues = array();
			foreach($values as $key => $value) {
				if(!isset($value['templates_id'])) continue;
				$tid = (int) $value['templates_id'];
				if(isset($emptyValues[$tid])) {
					$emptyValue = $emptyValues[$tid];
				} else {
					$template = $templates->get((int) $value['templates_id']);
					if(!$template) continue;
					$emptyValue = array();
					foreach($template->fieldgroup as $field) {
						$emptyValue[$field->name] = null;
					}
					$emptyValues[$tid] = $emptyValue;
				}
				$values[$key] = array_merge($emptyValue, $value);
			}
		}
	}

	/**
	 * Process given array of values to populate $this->requestFields and $this->renameFields
	 * 
	 * @param array $values
	 * @param string $prefix Prefix for recursive use
	 * @since 3.0.194
	 * 
	 */
	protected function processRequestFieldsArray(array $values, $prefix = '') {
		
		if($prefix) $prefix = rtrim($prefix, '.') . '.';
		
		foreach($values as $key => $value) {
			if(ctype_digit("$key")) {
				// i.e. [ 0 => 'field_name', 1 => 'another_field' ]
				if(is_string($value) && !ctype_digit("$value")) {
					$this->requestFields[] = $prefix . $value;
				} else {
					// error, not supported 
				}
			} else if(is_array($value)) {
				// i.e. [ 'field_name' => [ 'id', 'title' ]
				$this->processRequestFieldsArray($value, $prefix . $key);
				
			} else {
				// rename i.e. [ 'field_name' => 'new_field_name' ]
				$this->requestFields[] = $prefix . $key;
				$this->renameFields[$prefix . $key] = $value;
			}
		}
	}

	protected function unknownFieldsException(array $fieldNames, $context = '') {
		if($context) $context = " $context";
		$s = "Unknown$context name(s) for findRaw: " . implode(', ', $fieldNames);
		throw new WireException($s);
	}
}
