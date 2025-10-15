<?php namespace ProcessWire;

/**
 * ProcessWire Pages Parents
 *
 * #pw-headline Pages Parents
 * #pw-breadcrumb Pages
 * #pw-var $pages->parents
 * #pw-summary Implements page parents helper methods for the $pages API variable and manages the pages_parents DB table. 
 * #pw-body = 
 * This is not intended for the public API and instead used internally by 
 * the $pages classes, but available at `$pages->parents()->methodName()` if 
 * you want to use anything here. 
 * #pw-body
 * 
 * ~~~~~~
 * // Rebuild the entire pages_parents table
 * $numRows = $pages->parents()->rebuildAll();
 * ~~~~~~
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
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
	 * Construct
	 *
	 * @param Pages $pages
	 *
	 */
	public function __construct(Pages $pages) {
		parent::__construct();
		$this->pages = $pages;
		$this->debug = $pages->debug();
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
			foreach($this->getParents($lastParentID, $o) as $value) {
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
		
		$database = $this->wire()->database;
		
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
			$query = $this->wire()->database->prepare(trim($sql));
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
	 * Check if saved page needs any pages_parents updates and perform them when applicable
	 * 
	 * @param Page $page
	 * @return int Number of rows updated
	 * 
	 */
	public function save(Page $page) {
		
		$numRows = 0;
		
		// homepage not maintained in pages_parents table
		// pages being cloned are not maintained till clone operation finishes
		if($page->id < 2 || !$page->parent) return 0;
		// if($page->_cloning) return 0;
		
		// first check if page parents need any updates
		if($page->isNew()) {
			// newly added page
			if($page->parent->numChildren === 1) {
				// first time parent gets added to pages_parents
				$numRows += $this->addParent($page->parent);
				// $numRows += $this->rebuild($page->parent);
			}
		} else if($page->parentPrevious && $page->parentPrevious->id != $page->parent->id) {
			// existing page with parent changed
			$numRows += $this->movePage($page, $page->parentPrevious, $page->parent); 
			// $this->rebuildAll();
			/*
			if($page->parentPrevious->numChildren === 0) {
				// parent no longer has children and doesn’t need entry
				$numRows += $this->delete($page->parentPrevious);
			}
			if($page->parent->numChildren === 1) {
				// first time parent gets added to pages_parents
				$numRows += $this->rebuild($page->parent);
			}
			*/
		}
		
		return $numRows;
	}

	/**
	 * Rebuild pages_parents table for given page 
	 * 
	 * This descends into both parents, and children that are themselves parents,
	 * and this method already calls the rebuildBranch() method when appropriate.
	 *
	 * @param Page $page
	 * @return int
	 * @since 3.0.156
	 *
	 */
	public function rebuild(Page $page) {

		$pages_id = (int) $page->id;
		$database = $this->wire()->database;
		$inserts = array();
		$rowCount = 0;

		if(!$page->isNew()) $this->clear($page);

		// if page has no children it does not need pages_parents entries
		if(!$page->numChildren) return 0;

		// identify parents to store for $page
		foreach($page->parents() as $parent) {
			$parents_id = (int) $parent->id;
			if($parents_id > 1) $inserts[] = "$pages_id,$parents_id";
		}

		if(count($inserts)) {
			// if parents found to insert, rebuild parents of $page
			$inserts = implode('),(', $inserts);
			$query = $database->prepare("INSERT INTO pages_parents (pages_id, parents_id) VALUES($inserts)");
			$query->execute();
			$rowCount += $query->rowCount();
		}

		// rebuild parents within page’s children
		$rowCount += $this->rebuildBranch($page->id);

		return $rowCount;
	}

	/**
	 * Rebuild pages_parents table for given page (experimental faster alternative/rewrite of rebuild method)
	 * 
	 * @param Page $page
	 * @param Page $oldParent
	 * @param Page $newParent
	 * @return int
	 * @throws WireException
	 * @since 3.0.212
	 * 
	 */
	public function movePage(Page $page, Page $oldParent, Page $newParent) {
		
		$key = "$page,$oldParent,$newParent";
		if($key === $this->movePageLast) return 0;
		$this->movePageLast = $key;
	
		$database = $this->wire()->database;
		$numChildren = $page->numChildren();
		$numRows = 0;
	
		$oldParentIds = $oldParent->parents()->explode('id');
		array_shift($oldParentIds); // shift off id=1 
		$oldParentIds[] = $oldParent->id;
		
		$newParentIds = $newParent->parents()->explode('id');
		array_shift($newParentIds); // shift off id=1 
		$newParentIds[] = $newParent->id;

		// update the one page that moved
		$sql = 'UPDATE pages_parents SET parents_id=:new_parent_id WHERE pages_id=:pages_id AND parents_id=:old_parent_id';
		$query = $database->prepare($sql);
		$query->bindValue(':new_parent_id', $newParent->id, \PDO::PARAM_INT);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->bindValue(':old_parent_id', $oldParent->id, \PDO::PARAM_INT);
		try {
			$query->execute();
		} catch(\Exception $e) {
			if($e->getCode() != 23000) throw $e;
		}
		$numRows += $query->rowCount();

		// find children and descendents of the page that moved
		$sql = 'SELECT pages_id FROM pages_parents WHERE parents_id=:pages_id';
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $page->id, \PDO::PARAM_INT);
		$query->execute();
	
		$ids = array($page->id => $page->id);
		while($row = $query->fetch(\PDO::FETCH_NUM)) {
			$id = (int) $row[0];
			$ids[$id] = $id;
		}
		
		$query->closeCursor();
		
		$inserts = array();

		foreach($ids as $id) {
			foreach($newParentIds as $parentId) {
				if($id === $parentId) continue;
				$inserts[] = "$id,$parentId";
			}
		}
		
		// redundancy to capture specific missing parent situations
		foreach($newParent->parents() as $parent) {
			if($parent->id < 2) continue;
			$inserts[] = "$newParent->id,$parent->id";
			if($parent->parent_id > 1) {
				$grandParent = $parent->parent();
				$inserts[] = "$parent->id,$grandParent->id";
			}
		}

		// if page has children also add it to the inserts list
		if($numChildren) $inserts[] = "$page->id,$newParent->id";

		// delete old parent IDs
		if(count($oldParentIds) && count($ids)) {
			$idStr = implode(',', $ids);
			$oldParentIds = $this->wire()->sanitizer->intArray($oldParentIds);
			$oldParentIdStr = implode(',', $oldParentIds);
			$sql = "DELETE FROM pages_parents WHERE pages_id IN($idStr) AND parents_id IN($oldParentIdStr)";
			$database->exec($sql);
		}

		if(!count($inserts)) return $numRows;
		
		$sql = "INSERT INTO pages_parents SET pages_id=:pages_id, parents_id=:parents_id";
		$query = $database->prepare($sql);
		
		foreach($inserts as $insert) {
			list($id, $parentId) = explode(',', $insert, 2);
			$query->bindValue(':pages_id', $id, \PDO::PARAM_INT);
			$query->bindValue(':parents_id', $parentId, \PDO::PARAM_INT); 
			try {
				if($query->execute()) $numRows++;
			} catch(\Exception $e) {
				if($e->getCode() != 23000) $this->error($e->getMessage());
			}
		}
		
		return $numRows;
	}

	/**
	 * @var string
	 *
	 */
	protected $movePageLast = '';

	/**
	 * Add rows for a new parent in the pages_parents table
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @return int
	 * @since 3.0.212
	 * 
	 */
	protected function addParent(Page $page) {
		
		// if page has no children it does not need pages_parents entries
		if(!$page->numChildren) return 0;
	
		$database = $this->wire()->database;
		$numRows = 0;
		$pageId = (int) $page->id;
		$inserts = array();
		
		// identify parents to store for $page
		foreach($page->parents() as $parent) {
			$parentId = (int) $parent->id;
			if($parentId < 2) continue;
			$inserts[] = array('pages_id' => $pageId, 'parents_id' => $parentId); 
		}
		
		if(!count($inserts)) return 0;
	
		$sql = "INSERT INTO pages_parents SET pages_id=:pages_id, parents_id=:parents_id";
		$query = $database->prepare($sql);
		
		foreach($inserts as $insert) {
			$query->bindValue(':pages_id', $insert['pages_id'], \PDO::PARAM_INT);
			$query->bindValue(':parents_id', $insert['parents_id'], \PDO::PARAM_INT);
			try {
				if($query->execute()) $numRows++;
			} catch(\Exception $e) {
				// ok
			}
		}
		
		return $numRows;
	}

	/**
	 * Rebuild pages_parents branch starting at $fromParent and into all descendents
	 * 
	 * @param Page|int $fromParent From parent Page or ID
	 * @return int Number of rows inserted
	 * 
	 */
	public function rebuildBranch($fromParent) {
		return $this->rebuildAll($fromParent); 
	}

	/**
	 * Rebuild pages_parents table entirely or from branch starting with a parent branch
	 * 
	 * @param int|Page $fromParent Specify parent ID or page to rebuild from that parent, or omit to rebuild all
	 * @return int Number of rows inserted
	 * @since 3.0.156
	 * 
	 */
	public function rebuildAll($fromParent = null) {

		$database = $this->wire()->database;
		$inserts = array();
		$parents = $this->findParentIDs($fromParent ? $fromParent : -2); // find parents within children
		$rowCount = 0; 
	
		foreach($parents as $pages_id => $parents_id) {
			// if(isset($this->excludeIDs[$parents_id])) continue;
			$inserts[] = "$pages_id,$parents_id";
			while(isset($parents[$parents_id])) {
				$parents_id = $parents[$parents_id];
				$inserts[] = "$pages_id,$parents_id";
			}
		}
		
		if(count($parents)) {
			$where = $fromParent ? 'WHERE pages_id IN(' . implode(',', array_keys($parents)) . ')' : '';
			$sql = "DELETE FROM pages_parents $where";
			$database->exec($sql);
		}
	
		if(count($inserts)) {
			$inserts = array_unique($inserts);
			$inserts = implode('),(', $inserts);
			$query = $database->prepare("INSERT INTO pages_parents (pages_id, parents_id) VALUES($inserts)");
			$query->execute();
			$rowCount = $query->rowCount();
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
	public function clear($page) {
		$pages_id = (int) "$page";
		$database = $this->wire()->database;
		$query = $database->prepare("DELETE FROM pages_parents WHERE pages_id=:id");
		$query->bindValue(':id', $pages_id, \PDO::PARAM_INT);
		$query->execute();
		$cnt = $query->rowCount();
		$query->closeCursor();
		return $cnt;
	}

	/**
	 * Delete page entirely from pages_parents table (both as page and parent)
	 *
	 * @param Page|int $page
	 * @return int
	 * @since 3.0.156
	 *
	 */
	public function delete($page) {
		$pages_id = (int) "$page";
		$database = $this->wire()->database;
		$sql = "DELETE FROM pages_parents WHERE pages_id=:pages_id OR parents_id=:parents_id";
		$query = $database->prepare($sql);
		$query->bindValue(':pages_id', $pages_id, \PDO::PARAM_INT);
		$query->bindValue(':parents_id', $pages_id, \PDO::PARAM_INT);
		$query->execute();
		$cnt = $query->rowCount();
		$query->closeCursor();
		return $cnt;
	}

	/**
	 * Tests a page and returns verbose details about what was found
	 * 
	 * For debugging/development purposes to make sure pages_parents 
	 * is working as intended. 
	 * 
	 * #pw-internal
	 * 
	 * @param Page $page
	 * @return array
	 * 
	 */
	public function pageTests(Page $page) {
		
		$id = (int) "$page";
		$path = $page->path;
		$database = $this->wire()->database;
		
		$tests = array(
			'query-for-parents-of-page' => array(
				'notes' => "Query DB for parents of $path. Result will exclude homepage.", 
				'query' => "SELECT parents_id FROM pages_parents WHERE pages_id=$id",
				'timer' => '',
				'count' => 0, 
				'pages' => array(),
				'type' => 'database.query',
			),
			'query-for-pages-having-parent' => array(
				'notes' => "Query DB for pages having parent $path. Result will exclude pages that are not themselves parents.", 
				'query' => "SELECT pages_id FROM pages_parents WHERE parents_id=$id",
				'timer' => '',
				'count' => 0,
				'pages' => array(),
				'type' => 'database.query',
			),
			'pages-find-descendents' => array(
				'notes' => "Use \$pages->find() with selector to find all descendents of $path with no exclusions.",
				'query' => "has_parent=$id, sort=parent_id, sort=id, include=all", 
				'timer' => '',
				'count' => 0, 
				'pages' => array(),
				'type' => 'pages.find',
			),
			'page-children-descendents' => array(
				'notes' => "Use recursive \$page->children() to manually reproduce result from previous test (the two should match)", 
				'query' => "include=all, sort=id", 
				'timer' => '',
				'count' => 0, 
				'pages' => array(),
				'type' => 'descendents',
			),
		);
		
		foreach($tests as $key => $test) {
			$timer = Debug::timer();
			if($test['type'] === 'database.query') {
				$query = $database->prepare($test['query']);
				$query->execute();
				$test['count'] = $query->rowCount();
				while($value = $query->fetchColumn()) {
					$test['pages'][] = "$value: " . $this->pages->getPath($value);
				}
				$query->closeCursor();
				
			} else if($test['type'] === 'pages.find') {
				$this->pages->uncacheAll();
				$items = $this->pages->find($test['query']); 
				$test['count'] = $items->count(); 
				$test['pages'] = $items->explode("{id}: {path}");
				
			} else if($test['type'] === 'descendents') {
				$this->pages->uncacheAll();
				$items = $this->descendents($page, $test['query']); 
				$test['count'] = $items->count(); 
				$test['pages'] = $items->explode("{id}: {path}");
			}
			
			$test['timer'] = Debug::timer($timer);
			$tests[$key] = $test;
		}
		
		return $tests;
	}

	/**
	 * Find descendents of $page by going recursive rather than using pages_parents table (for testing)
	 * 
	 * @param Page $page
	 * @param string $selector
	 * @return PageArray
	 * 
	 */
	protected function descendents(Page $page, $selector = 'include=all') {
		$children = new PageArray();
		foreach($page->children($selector) as $child) {
			$children->add($child); 
			if(!$child->numChildren) continue;
			foreach($this->descendents($child, $selector) as $item) {
				$children->add($item);
			}
		}
		return $children;
	}

}
