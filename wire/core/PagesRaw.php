<?php namespace ProcessWire;

/**
 * ProcessWire Pages Raw Tools
 *
 * ProcessWire 3.x, Copyright 2021 by Ryan Cramer
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
		$this->pages = $pages;
	}

	/**
	 * Find pages and return raw data from them in a PHP array
	 *
	 * @param string|array|Selectors $selector
	 * @param string|Field|int|array $field Field/property name to get or array of them (or omit to get all)
	 * @param array $options See options for Pages::find
	 *  - `indexed` (bool): Index by page ID? (default=false)
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
	 * @param array $options See options for Pages::find
	 *  - `indexed` (bool): Index by page ID? (default=false)
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
		'findOne' => false,
	);

	/**
	 * @var string|array|Selectors
	 * 
	 */
	protected $selector = '';

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
	protected $customFields = array();

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
	 * IDs of pages to find, becomes array once known
	 * 
	 * @var null|array
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
		$this->pages = $pages;
	}

	/**
	 * @param string|int|array|Selectors
	 * @param string|array|Field $field
	 * @param array $options
	 * 
	 */
	protected function init($selector, $field, $options) {

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
		
		if(empty($field)) {
			$this->getAll = true;
			
		} else if(is_string($field) && strpos($field, ',') !== false) {
			// multiple fields requested in CSV string, we will return an array for each page
			$this->requestFields = explode(',', $field);
			
		} else if(is_array($field)) {
			// one or more fields requested in array, we wil return an array for each page
			$this->requestFields = $field;
			
		} else {
			// one field requested in string or Field object
			$this->requestFields = array($field);
			$this->getMultiple = false;
		}

		if(!$this->getAll) {
			// split request fields into nativeFields and customFields
			$this->splitFields();
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
	 * @param string|array|Selectors $selector
	 * @param string|Field|int|array $field Field/property name or array of of them
	 * @param array $options See options for Pages::find
	 * @return array
	 * @since 3.0.172
	 *
	 */
	public function find($selector, $field = '', $options = array()) {

		$this->init($selector, $field, $options);
		
		// requested native pages table fields/properties
		if(count($this->nativeFields) || $this->getAll) {
			// one or more native pages table column(s) requested
			$this->findNativeFields();
		}
		
		// requested custom fields
		if(count($this->customFields) || $this->getAll) {
			$this->findCustom();
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

		// split request fields into custom fields and native (pages table) fields
		foreach($this->requestFields as $key => $fieldName) {
			
			if(empty($fieldName)) continue;
			if(is_string($fieldName)) $fieldName = trim($fieldName);
			$colName = '';
			$dotCol = false;
			
			if($fieldName === '*') {
				// get all (not yet supported)
				$fieldObject = null;
				
			} else if($fieldName instanceof Field) {
				// Field object
				$fieldObject = $fieldName;
				
			} else if(is_array($fieldName)) {
				// Array where [ 'field' => [ 'subfield'' ]] 
				$colName = $fieldName; // array
				$fieldName = $key;
				$fieldObject = isset($this->customFields[$fieldName]) ? $this->customFields[$fieldName] : null;
				if(!$fieldObject) $fieldObject = $fields->get($fieldName);
				if(!$fieldObject) continue;
				
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
				$fieldObject = isset($this->customFields[$fieldName]) ? $this->customFields[$fieldName] : null;
				if(!$fieldObject) $fieldObject = $fields->get($fieldName);
				
			} else {
				// something we do not recognize
				continue;
			}
			
			if($fieldObject instanceof Field) {
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
	}

	/**
	 * Find raw native fields
	 *
	 */
	protected function findNativeFields() {
		
		$this->ids = array();
		$allNatives = array();
		
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
			}
		}

		if(count($getNatives)) {
			// remove any native data that is present but was not requested
			foreach($this->values as $id => $row) {
				foreach($row as $colName => $value) {
					if(!isset($getNatives[$colName])) {
						unset($this->values[$id][$colName]);
					}
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
			if($this->ids === null) {
				// only find IDs if we didn’t already in the nativeFields section
				$this->ids = $this->findIDs($this->selector, false);
			}
			if(!count($this->ids)) return;
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
		$pageRefCols = array();

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

		if(empty($table)) return;

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
			
		} else {
			foreach($cols as $key => $col) {
				$col = $sanitizer->fieldName($col);
				$col = $database->escapeCol($col);
				if(isset($schema[$col])) {
					$getCols[$col] = $col;
				} else if($fieldtypePage || $fieldtypeRepeater) {
					$pageRefCols[$col] = $col;
				} else {
					// unknown column
				}
			}
			if(count($pageRefCols)) {
				// get just the data column when a field within a Page reference is asked for
				$getCols['data'] = 'data';
			}
			if(count($getCols) === 1 && !$this->getMultiple) {
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
		$colSQL = $getAllCols ? '*' : '`' . implode('`,`', $getCols) . '`';
		if(!$getAllCols && !in_array('pages_id', $getCols)) $colSQL .= ',`pages_id`';
		$sql = "SELECT $colSQL FROM `$table` WHERE pages_id IN($idsCSV) ";
		if(count($sorts)) $sql .= "ORDER BY " . implode(',', $sorts);

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
	}
	
	protected function findCustomFieldtypePage(Field $field, $fieldName, array $pageRefCols) {
		// print_r($values);
		$pageRefIds = array();
		foreach($this->values as $pageId => $row) {
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
		$pageRefRows = $finder->find($pageRefIds, $pageRefCols, $options);

		foreach($this->values as $pageId => $pageRow) {
			if(!isset($pageRow[$fieldName])) continue;
			foreach($pageRow[$fieldName] as $pageRefId) {
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
	
		// if selector was just a page ID, return it in an id indexed array
		if(is_int($selector) || (is_string($selector) && ctype_digit($selector))) {
			$id = (int) $selector;
			return array($id => $id); 
		}

		// if selector is not array of page IDs then let pages.findIDs handle it
		if(!is_array($selector) || !isset($selector[0]) || !ctype_digit((string) $selector[0])) {
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
	
		if($verbose === '*') {
			// get all columns
			$sql = "SELECT * FROM pages WHERE id IN($selector)";
		} else {
			// get just base columns
			$sql = "SELECT id, templates_id, parent_id FROM pages WHERE id IN($selector)";
		}
		
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
}