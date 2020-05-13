<?php namespace ProcessWire;

/**
 * ProcessWire Pages Parents
 *
 * Implements page parents helper methods for the $pages API variable
 * and manages the pages_parents DB table. 
 * 
 * This is not intended for the public API and instead used internally by 
 * the $pages classes, but available at $pages->parents()->methodName() if 
 * you want to use anything here. 
 * 
 * ~~~~~~
 * // Rebuild the entire pages_parents table
 * $numRows = $pages->parents()->rebuild();
 * ~~~~~~
 *
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 * 
 * @since 3.0.156
 *
 */
 
class PagesParents extends Wire {
	
	/**
	 * @var Pages
	 *
	 */
	protected $pages;
	
	/**
	 * Debug mode for pages class
	 *
	 * @var bool
	 *
	 */
	protected $debug = false;

	/**
	 * Page parent IDs excluded from pages_parents table
	 * 
	 * Set via $config->parentsTableExcludeIDs
	 * 
	 * @var array
	 * 
	 */
	protected $excludeIDs = array();

	/**
	 * Construct
	 *
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
		$this->pages = $pages;
		$this->debug = $pages->debug();
		
		$excludeIDs = $pages->wire('config')->parentsTableExcludeIDs;
		if(is_array($excludeIDs)) {
			foreach($excludeIDs as $id) {
				$this->excludeIDs[$id] = $id; 
			}
		}
	}
	
	/**
	 * Get parents for given Page and/or specific columns from them
	 *
	 * - Return value has no exclusions for access control or status.
	 * - Return value order is closest parent to furthest.
	 * - This attempts to return all pages in 1 optimized query, making it potentially faster 
	 *   than other methods. 
	 * - When using `column` or `columns` options, specify only one or the other, and include
	 *   column(s) native to pages DB table only, with 1 exception—you may specify `path` as
	 *   a column, which will return the generated page path(s).
	 *
	 * @param Page|int $page Page or page ID
	 * @param array $options
	 *  - `column` (string): Just return array of values from this column (use `columns` option when you need multiple columns). (default='')
	 *  - `columns` (array): Return array of associative arrays containing these columns for each page (not to be combined with `column` option)
	 *  - `indexBy` (string): Return array indexed by column `id` or `parent_id`, applies only if given column/columns (default='')
	 *  - `includePage` (bool): Also include data for given $page in return value? (default=false)
	 *  - `noHome` (bool): Omit homepage from return value (default=false)
	 *  - `joinQty` (int): Number of parents to join in query before going recursive, for internal use (default=4).
	 * @return array|PageArray If given column/columns returns an array, otherwise returns a PageArray
	 * @since 3.0.156
	 *
	 */
	public function getParents($page, array $options = array()) {

		$defaults = array(
			'column' => '', // get 1 column
			'columns' => array(),  // get array multiple columns
			'joinQty' => 4,
			'indexBy' => '', // id or parent_id or blank
			'includePage' => false,
			'noHome' => false,
		);

		$options = array_merge($defaults, $options);
		$values = array();
		$columns = array();
		$joinQty = (int) $options['joinQty'];
		$joins = array();
		$selects = array('pages.parent_id AS parent_id');
		$indexBy = $options['indexBy'] ? $options['indexBy'] : 'id';
		$lastTable = 'pages';
		$database = $this->wire('database');
		$getOneCol = empty($options['columns']) ? $options['column'] : '';
		$getPath = false;
		$getPages = empty($options['columns']) && empty($options['column']);
		$id = (int) "$page";
		$lastParentID = $id;
		$blankReturn = $getPages ? $this->pages->newPageArray() : array();

		if($id <= 0 || (!$options['includePage'] && $id <= 1)) return $blankReturn;

		if($indexBy) {
			$options['columns'][] = $indexBy;
		}

		if($getPages) {
			$options['columns'][] = 'id';
			$options['columns'][] = 'templates_id';
		} else if($indexBy != 'id' && $indexBy != 'parent_id') {
			throw new WireException('indexBy option must be "id" or "parent_id" or blank');
		} else if($getOneCol) {
			$options['columns'][] = $getOneCol;
		}

		foreach($options['columns'] as $col) {
			if($col === 'parent_id') continue; // already a part of our query
			$column = $database->escapeCol($col);
			if($col === $column) $columns[$column] = $column;
		}

		if(!$getPages && isset($columns['path'])) {
			$getPath = true;
			unset($columns['path']);
			$columns['name'] = 'name';
			$columns['id'] = 'id';
		}

		for($n = 0; $n <= $joinQty; $n++) {
			$key = $n ? $n : '';
			$table = "pages$key";
			$selects[] = "$table.parent_id AS parent_id$key";
			foreach($columns as $col) {
				$selects[] = "$table.$col AS $col$key";
			}
			if($n) $joins[] = "LEFT JOIN pages AS $table ON $lastTable.parent_id=$table.id";
			$lastTable = $table;
		}

		$sql =
			'SELECT ' . implode(', ', $selects) . ' ' .
			'FROM pages ' . implode(' ', $joins) . ' ' .
			'WHERE pages.id=:id';

		$query = $database->prepare($sql);
		$query->bindValue(':id', $id, \PDO::PARAM_INT);
		$query->execute();
		$row = $query->fetch(\PDO::FETCH_ASSOC);
		$query->closeCursor();

		if(empty($row)) return $blankReturn;
		$parentID = $id;

		for($n = 0; $n <= $joinQty; $n++) {
			$key = $n ? $n : '';
			$lastParentID = $parentID;
			$parentID = (int) $row["parent_id$key"];
			if(!$n && !$options['includePage']) {
				// skip first
			} else if(count($columns)) {
				$value = array("id" => $lastParentID, "parent_id" => $parentID);
				foreach($columns as $col) {
					$value[$col] = $row["$col$key"];
				}
				$values[] = $value;
			} else {
				$values[] = $parentID;
			}
			if($parentID < 1 || ($parentID < 2 && $options['noHome'])) break;
		}

		if($lastParentID > 1 && count($values) >= $joinQty) {
			// more parents to go, get rest recursively
			$o = $options;
			$o['columns'] = $columns;
			foreach($this->getParents($lastParentID, $o) as $key => $value) {
				$values[] = $value;
			}
		}

		if(!count($values)) return $blankReturn;

		if($getPath) {
			$names = array();
			foreach(array_reverse($values, true) as $key => $value) {
				$name = $value['id'] > 1 ? $value['name'] : '';
				if($name) $names[] = $name;
				$values[$key]['path'] = '/' . implode('/', $names) . '/';
			}
		}

		if($getPages) {
			$values = $this->pages->loader()->getById($values);
		} else if($getOneCol) {
			$a = array();
			foreach($values as $key => $value) {
				$index = empty($options['indexBy']) ? $key : $value[$indexBy];
				$a[$index] = $value[$getOneCol];
			}
			$values = $a;
		} else {
			$a = array();
			foreach($values as $key => $value) {
				$index = empty($options['indexBy']) ? $key : $value[$indexBy];
				$a[$index] = array();
				foreach($options['columns'] as $col) {
					if(isset($value[$col])) $a[$index][$col] = $value[$col];
				}
			}
			$values = $a;
		}

		return $values;
	}
	
	/**
	 * Find all pages that have children 
	 * 
	 * @param array $options
	 * @return array|PageArray
	 * @since 3.0.156
	 * 
	 */
	public function findParents(array $options = array()) {
		
		static $calls = 0;
		
		$defaults = array(
			'minChildren' => 1, // min children to match
			'maxChildren' => 0, // max children to match
			'numChildren' => 0, // exact children to match 
			'parent' => 0, // start from this parent, or negative int for minimum parent ID
			'recursive' => true, // recursively find all (when combined with parent option)
			'start' => 0,
			'limit' => 0, 
			'column' => '', // get this 1 column (native columns only)
			'columns' => array(), // get these columns (native columns only)
			'sortByID' => false, 
			'indexByID' => false, // return value should be indexed by page ID? 
			'useIndexTable' => true, // allow use of pages_parents index table? (much faster)
			'debug' => false, // debug mode makes it also return a debug index w/additional info
			'_level' => 0, // recursion level (internal)
		);
		
		$options = array_merge($defaults, $options);
		$parentID = isset($options['parent']) ? (int) "$options[parent]" : 0;
		$database = $this->wire('database'); /** @var WireDatabasePDO $database */
		$sql = array();
		$bind = array();
		$column = $options['column'];
		$columns = $options['columns'];
		if(empty($columns) && strlen($column)) $columns = array($column);
		$getPages = empty($columns) && !$options['_level'] && !$options['debug']; 
		$forceRecursive = $options['recursive'] && !$options['useIndexTable']; 
		$indexByID = $getPages || $forceRecursive ? true : $options['indexByID'];
		$debug = $options['debug'];
		$timer = $debug && !$options['_level'] ? Debug::timer() : false;
		
		if($debug) {
			$calls++;
			if(!in_array('parent_id', $columns)) $columns[] = 'parent_id';
			if(!in_array('name', $columns)) $columns[] = 'name';
		}
		
		$sql['select'] = "SELECT pages.id, COUNT(children.id) AS numChildren";
		if($getPages) $sql['select'] .= ", pages.templates_id";
	
		foreach($columns as $key => $col) {
			if($col === 'id' || $col === 'numChildren') continue; // already have it
			$col = $database->escapeCol($col); 
			if($col !== $columns[$key]) continue;
			$sql['select'] .= ", pages.$col";
		}
			
		$sql['from'] = "FROM pages";
		$sql['join'] = "JOIN pages AS children ON children.parent_id=pages.id";
		
		if($parentID) {
			$op = '=';
			if($parentID < 0) {
				// minimum parent ID
				$op = '>=';
				$parentID = abs($parentID); 
			}
			$bind[':parent_id'] = $parentID; 
			if($options['recursive'] && $options['useIndexTable']) {
				$sql['join2'] = "JOIN pages_parents ON pages_parents.parents_id$op:parent_id AND pages_parents.pages_id=pages.id";
			} else {
				$sql['where'] = "WHERE pages.parent_id$op:parent_id";
			}
		}
		
		$sql['group'] = "GROUP BY pages.id";
		
		foreach(array('minChildren' => '>=', 'maxChildren' => '<=', 'numChildren' => '=') as $key => $op) {
			$value = (int) $options[$key];
			if(empty($value)) continue;
			if($key === 'minChildren' && $value === 1) continue; // already implied
			$sql['having'] = (empty($sql['having']) ? "HAVING " : "$sql[having] AND ") . "COUNT(children.id)$op:$key";
			$bind[":$key"] = $value;
		}

		if($options['sortByID']) {
			$sql['order'] = "ORDER BY pages.id";
		}
		
		if($options['limit']) {
			$start = (int) $options['start'];
			$limit = (int) $options['limit'];
			$sql['limit'] = "LIMIT $start,$limit";
		}
		
		$query = $database->prepare(implode(' ', $sql));
		foreach($bind as $key => $value) {
			$query->bindValue($key, $value, \PDO::PARAM_INT);
		}
		$database->execute($query);
		$values = array();

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) {
			$id = (int) $row['id'];
			if($column) {
				// single column
				$value = isset($row[$column]) ? $row[$column] : null;
			} else if(!empty($columns)) {
				// multiple columns
				$value = array();
				foreach($columns as $col) {
					if(isset($row[$col])) $value[$col] = $row[$col];
				}
			} else if($getPages) {
				// get pages
				$value = array(
					'id' => $id, 
					'templates_id' => (int) $row['templates_id'],
					'numChildren' => (int) $row['numChildren']
				);
			} else {
				// undefined
				$value = $row;
			}
			if($indexByID) {
				$values[$id] = $value; 
			} else {
				$values[] = $value;
			}
		}
		
		$query->closeCursor();
		
		if($forceRecursive) {
			foreach(array_keys($values) as $pid) {
				$options['parent'] = $pid;
				$options['_level']++;
				foreach($this->findParents($options) as $id => $value) {
					$values[$id] = $value;
				}
				$options['_level']--;
			}
			if($options['sortByID'] && !$options['_level']) {
				ksort($values);
			}
		}
		
		if($getPages) {
			$pageArray = $this->pages->loader()->getById($values, array(
				// since we already have numChildren, we don't need getByID to spend time finding it
				'getNumChildren' => false, 
			)); 
			foreach($pageArray as $page) {
				$page->setQuietly('numChildren', $values[$page->id]['numChildren']); 
			}
			$values = $pageArray;
		} else {
			if($debug && !$options['_level']) {
				$hash = md5(print_r($values, true)); 
				$debugInfo = array(
					'calls' => $calls, 
					'timer' => Debug::timer($timer), 
					'hash' => $hash,
				);
				$calls = 0;
				// add in breadcrumb debug property
				foreach($values as $id => $value) {
					if(!isset($value['breadcrumb'])) $value['breadcrumb'] = array();
					$pid = $value['parent_id'];
					while(isset($values[$pid])) {
						$name = $values[$pid]['name'];
						$values[$id]['breadcrumb'][] = $name;
						$pid = $values[$pid]['parent_id'];
					}
				}
			} else {
				$debugInfo = false;
			}
			if($indexByID && !$options['indexByID']) {
				$values = array_values($values); // convert to regular PHP array
			}
			if($debugInfo) $values['debug'] = $debugInfo;
		}
		
		return $values;
	}

	/**
	 * Find IDs of all pages that are parents for other pages, optionally within a parent
	 * 
	 * Does not rely on pages_parents index table, so can be used for rebuilding it.
	 * 
	 * Faster than findParents() in cases where the pages_parents table cannot be used
	 * and there are potentially hundreds/thousands of parents to find. However, it does
	 * use more memory than findParents() even if it can be potentially a lot faster.
	 * 
	 * ~~~~~ 
	 * // the following two calls should produce identical results (excluding order)
	 * // if they don’t, the index may need to be rebuilt
	 * $pages->parents()->findParentIDs($id); 
	 * $pages->parents()->findParents([
	 *   'parent' => $id,  
	 *   'indexByID' => true,
	 *   'column' => 'parent_id'
	 * ]);
	 * ~~~~~
	 * 
	 * @param null|Page|int $fromParent Specify parent to limit results within, or negative int for minimum parent_id,
	 *   for instance a value of -2 would exclude homepage and items with homepage (id=1) as their parent. 
	 * @return array Returns array in format [ id => parent_id ]
	 * @since 3.0.156
	 * 
	 */
	public function findParentIDs($fromParent = null) {
		
		static $parents = null;
		static $level = 0;
		
		$fromParent = (int) "$fromParent";
		$minParentID = $fromParent < 0 ? abs($fromParent) : 0;
		if($minParentID) $fromParent = 0;
		
		if($parents == null) {
			$parents = array();
			$sql = "
				SELECT parents.id, parents.parent_id 
				FROM pages 
				JOIN pages AS parents on pages.parent_id=parents.id AND parents.parent_id>=:id
				GROUP BY pages.parent_id 
			";
			$query = $this->database->prepare(trim($sql));
			$query->bindValue(':id', $minParentID, \PDO::PARAM_INT); 
			$query->execute();
			while($row = $query->fetch(\PDO::FETCH_NUM)) {
				list($pages_id, $parents_id) = $row;
				$parents[(int) $pages_id] = (int) $parents_id;
			}
			$query->closeCursor();
		}
	
		if($fromParent) {
			// filter out parents not descendents of $fromParent
			$a = array();
			$level++;
			foreach(array_keys($parents, $fromParent) as $id) {
				$a[$id] = $fromParent;
				$a += $this->findParentIDs($id);
			}
			$level--;
			if(!$level) $parents = null;
		} else {
			$a = $parents;
			$parents = null;
		}
		
		return $a;	
	}

	/**
	 * Rebuild pages_parents index for given page (and any children)
	 * 
	 * @param Page $page
	 * @return int
	 * @since 3.0.156
	 * 
	 */
	public function rebuildPage(Page $page) {
		
		$pages_id = (int) $page->id; 
		$database = $this->wire('database'); /** @var WireDatabasePDO $database */
		$inserts = array();
		$rowCount = 0;
		$exclude = false;
		
		if($page->id < 2) return 0;
		if(!$page->_cloning && !$page->isNew()) $this->clearPage($page);
		if(!$page->numChildren) return 0;
		
		foreach($page->parents() as $parent) {
			$parents_id = (int) $parent->id;
			if($parents_id < 2) break;
			$inserts[] = "$pages_id,$parents_id";
			$exclude = isset($this->excludeIDs[$parents_id]); 
			if($exclude) break;
		}
		
		if($exclude) return 0;

		if(count($inserts)) {
			$inserts = implode('),(', $inserts);
			$query = $database->prepare("INSERT INTO pages_parents (pages_id, parents_id) VALUES($inserts)");
			$query->execute();
			$rowCount += $query->rowCount();
		}
		
		if(!isset($this->excludeIDs[$page->id])) {
			$rowCount += $this->rebuild($page->id); 
		}
		
		return $rowCount;
	}

	/**
	 * Clear page from pages_parents index
	 * 
	 * @param Page|int $page
	 * @return int
	 * @since 3.0.156
	 * 
	 */
	public function clearPage($page) {
		$pages_id = (int) "$page";
		$database = $this->wire('database'); /** @var WireDatabasePDO $database */
		$query = $database->prepare("DELETE FROM pages_parents WHERE pages_id=:id");
		$query->bindValue(':id', $pages_id, \PDO::PARAM_INT);
		$query->execute();
		$cnt = $query->rowCount();
		$query->closeCursor();
		return $cnt;
	}

	/**
	 * Rebuild pages_parents table entirely or from branch starting with a parent
	 * 
	 * @param int|Page $fromParent Specify parent ID or page to rebuild from that parent, or omit to rebuild all
	 * @return int Number of rows inserted
	 * @since 3.0.156
	 * 
	 */
	public function rebuild($fromParent = null) {

		$database = $this->wire('database'); /** @var WireDatabasePDO $database */
		$inserts = array();
		$parents = $this->findParentIDs($fromParent ? $fromParent : -2); 
	
		foreach($parents as $pages_id => $parents_id) {
			if(isset($this->excludeIDs[$parents_id])) continue;
			$inserts[] = "$pages_id,$parents_id";
			while(isset($parents[$parents_id])) {
				$parents_id = $parents[$parents_id];
				$inserts[] = "$pages_id,$parents_id";
			}
		}
		
		$inserts = array_unique($inserts);
		$where = $fromParent ? 'WHERE pages_id IN(' . implode(',', array_keys($parents)) . ')' : '';
		$database->exec("DELETE FROM pages_parents $where");

		$inserts = implode('),(', $inserts);
		$query = $database->prepare("INSERT INTO pages_parents (pages_id, parents_id) VALUES($inserts)");
		$query->execute();
		$rowCount = $query->rowCount();
		
		return $rowCount;
	}

	/**
	 * Rebuild pages_parents table (deprecated original version replaced by rebuild method, here for reference only)
	 *
	 * Save references to the Page's parents in pages_parents table, as well as any other pages affected by a parent change
	 * 
	 * #pw-internal
	 *
	 * @param int|Page $pages_id ID of page to save parents from
	 * @param int|bool $hasChildren Does this page have children? Specify true or quantity of children.
	 * @param array $options
	 *  - `debug` (bool): Return debug info array? (default=false)
	 *  - `skipIDs` (array): if pages_id has a parent matching any of these then do not save pages_parents table data for it.
	 *  - `level` (int): Recursion level (internal use)
	 * @return int|array Returns number of records inserted or array when debug option specified
	 * @deprecated
	 *
	 */
	private function saveParents($pages_id, $hasChildren, array $options = array()) {

		$defaults = array(
			'level' => 0, // recursion level
			'skipIDs' => array(),
			'parentsIDs' => array(), // internal recursive use only
			'debug' => false,
		);

		$options = array_merge($defaults, $options);
		$pages_id = (int) "$pages_id";
		$config = $this->wire('config'); /** @var Config $config */
		$database = $this->wire('database'); /** @var WireDatabasePDO $database */
		$debug = $options['debug'] ? array() : false;
		$parentsIDs = empty($options['parentsIDs']) ? array() : $options['parentsIDs'];
		if($debug !== false && !$options['level']) $debug = array('debug' => "saveParentsTable($pages_id)");

		if(!$pages_id) return $debug === false ? 0 : $debug;

		$skipIDs = $config->parentsTableExcludeIDs;
		$skipIDs = is_array($skipIDs) ? array_merge($skipIDs, $options['skipIDs']) : $options['skipIDs'];
		$skipIDs = empty($skipIDs) ? array() : array_flip($skipIDs); // flip for isset() use

		$sql = "DELETE FROM pages_parents WHERE pages_id=:pages_id ";

		if(!$options['level'] && count($skipIDs)) {
			$parentsIDs = array();
			foreach($skipIDs as $id) $parentsIDs[] = (int) $id;
			$sql .= 'OR (pages_id>0 AND parents_id IN(' . implode(',', $parentsIDs) . '))';
		}

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT);
		$query->execute();
		$query->closeCursor();

		// if page has no children, then there is nothing further to do
		if(!$hasChildren) return $debug === false ? 0 : $debug;

		$skip = false;
		$inserts = array();

		if(empty($parentsIDs)) {
			$parentsIDs = $this->getParents($pages_id, array(
				'column' => 'id', // get value of id column only
				'noHome' => true // exclude homepage
			));
		}

		foreach($parentsIDs as $parent_id) {
			$parent_id = (int) $parent_id;
			$skip = isset($skipIDs[$parent_id]);
			if($skip) break;
			$inserts[] = "$pages_id,$parent_id";
			if($debug !== false) $debug[] = $parent_id;
		}

		if($skip) return $debug === false ? 0 : $debug;

		$numInserts = count($inserts);

		if($numInserts) {
			$sql =
				'INSERT INTO pages_parents (pages_id, parents_id) ' .
				'VALUES(' . implode('),(', $inserts) . ') ' .
				'ON DUPLICATE KEY UPDATE parents_id=VALUES(parents_id)';
			$database->exec($sql);
		}

		// find all children of $pages_id that themselves have children
		$sql =
			"SELECT pages.id, pages.name, COUNT(children.id) AS numChildren " .
			"FROM pages " .
			"JOIN pages AS children ON children.parent_id=pages.id " .
			"WHERE pages.parent_id=:pages_id " .
			"GROUP BY pages.id ";

		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT);
		$database->execute($query);
		$rows = array();

		/** @noinspection PhpAssignmentInConditionInspection */
		while($row = $query->fetch(\PDO::FETCH_ASSOC)) $rows[] = $row;
		$query->closeCursor();

		if(count($rows)) {
			array_unshift($parentsIDs, $pages_id);
			$options['parentsIDs'] = $parentsIDs;
			$options['level']++;
			foreach($rows as $row) {
				$result = $this->saveParents($row['id'], $row['numChildren'], $options);
				if($debug === false) {
					$numInserts += $result;
				} else {
					$debug["parents-of-$row[id]"] = $result;
				}
			}
			$options['level']--;
		}

		return $debug === false ? $numInserts : $debug;
	}

	
}