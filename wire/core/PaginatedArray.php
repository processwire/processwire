<?php namespace ProcessWire;

/**
 * ProcessWire Paginated WireArray
 * 
 * Like WireArray, but with the additional methods and properties needed for WirePaginatable interface.
 * 
 * #pw-summary PaginatedArray is a type of WireArray that supports pagination of items within it. 
 * #pw-body = 
 * Here you will see methods specific to the pagination aspects of this class only. For full details on
 * available methods outside of pagination, please see the `WireArray` class. The most common type of
 * PaginatedArray is a `PageArray`. 
 * #pw-body
 * #pw-summary-manipulation In most cases you will not need these manipulation methods as core API calls already take care of this. 
 *
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

class PaginatedArray extends WireArray implements WirePaginatable {

	/**
	 * Total number of items, including those here and others that aren't, but may be here in pagination.
	 *
	 * @var int
	 *
	 */
	protected $numTotal = 0;

	/**
	 * If this WireArray is a partial representation of a larger set, this will contain the max number of items allowed to be
	 * present/loaded in the WireArray at once.
	 *
	 * May vary from count() when on the last page of a result set.
	 * As a result, paging routines should refer to their own itemsPerPage rather than count().
	 * Applicable for paginated result sets. This number is not enforced for adding items to this WireArray.
	 *
	 * @var int
	 *
	 */
	protected $numLimit = 0;

	/**
	 * If this WireArray is a partial representation of a larger set, this will contain the starting result number if previous results preceded it.
	 *
	 * @var int
	 *
	 */
	protected $numStart = 0;

	/**
	 * Set the total number of items, if more than are in the WireArray.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param int $total
	 * @return $this
	 *
	 */
	public function setTotal($total) {
		$this->numTotal = (int) $total;
		return $this;
	}

	/**
	 * Get the total number of items in the WireArray, including all paginations.
	 *
	 * If no limit is used, this returns total number of items currently in the WireArray,
	 * which would be the same as the `WireArray::count()` value. But when a limit is 
	 * used, this number will typically be larger than the count, as it includes all 
	 * items across all paginations, whether currently present or not. 
	 * 
	 * #pw-group-retrieval
	 *
	 * @return int Total number of items across all paginations. 
	 *
	 */
	public function getTotal() {
		return $this->numTotal;
	}

	/**
	 * Set the limit that was used in pagination.
	 * 
	 * #pw-group-manipulation
	 *
	 * @param int $numLimit
	 * @return $this
	 *
	 */
	public function setLimit($numLimit) {
		$this->numLimit = (int) $numLimit; 
		return $this; 
	}

	/**
	 * Get the limit that was used in pagination.
	 *
	 * If no limit was set, then it returns the number of items currently in this WireArray.
	 * 
	 * #pw-group-retrieval
	 *
	 * @return int
	 *
	 */
	public function getLimit() {
		return $this->numLimit; 
	}

	/**
	 * Set the starting offset number to use for pagination.
	 * 
	 * This is typically the current page number (minus 1) multiplied by limit setting. 
	 * 
	 * #pw-group-manipulation
	 *
	 * @param int $numStart
	 * @return $this
	 *
	 */
	public function setStart($numStart) {
		$this->numStart = (int) $numStart; 
		return $this; 
	}

	/**
	 * Get the starting offset number that was used for pagination.
	 * 
	 * #pw-group-retrieval
	 * 
	 * @return int
	 *
	 */
	public function getStart() {
		return $this->numStart; 
	}

	/**
	 * Get a property of the PageArray
	 *
	 * These map to functions from the array and are here for convenience.
	 * Properties include count, total, start, limit, last, first, keys, values,
	 * These can also be accessed by direct reference, i.e. `$items->limit`. 
	 * 
	 * Please see the `WireArray::getProperty()` method for full details on 
	 * non-pagination related properties that can be retrieved through this. 
	 * 
	 * #pw-group-other
	 *
	 * @param string $property Name of property you want to retrieve, can be any of the following:
	 *   - `count` (int): Count of items currently present.
	 *   - `total` (int): Total quantity of items across all paginations.
	 *   - `start` (int): Current start index for pagination.
	 *   - `limit` (int): Current limit used for pagination.
	 *   - `last` (mixed): Last item in the WireArray.
	 *   - `first` (mixed): First item in the WireArray. 
	 * @return mixed Value of requested property. 
	 *
	 */
	public function getProperty($property) {
		static $properties = array(
			// property => method to map to
			'total' => 'getTotal',
			'start' => 'getStart',
			'limit' => 'getLimit',
		);
		if(!in_array($property, $properties)) return parent::getProperty($property);
		$func = $properties[$property];
		return $this->$func();
	}

	/**
	 * Get a "1 to 10 of 50" type of string useful for pagination headlines.
	 * 
	 * This returns a string of `1 to 10 of 30` (items) or `1 of 10` (pages) for example.
	 * 
	 * ~~~~~
	 * // Get string like "Items 1 to 25 of 500"
	 * echo $items->getPaginationString('Items');
	 * 
	 * // Get string like "Page 1 of 10"
	 * echo $items->getPaginationString('Page', true);
	 * ~~~~~
	 * 
	 * #pw-group-other
	 * 
	 * @param string $label Label to identify item type, i.e. "Items" or "Page", etc. (default=empty).
	 * @param bool $usePageNum Specify true to show page numbers rather than item numbers (default=false). 
	 *   Omit to use the default item numbers. 
	 * @return string Formatted string
	 * 
	 */
	public function getPaginationString($label = '', $usePageNum = false) {
		
		$count = $this->count();
		$start = $this->getStart();
		$limit = $this->getLimit();
		$total = $this->getTotal();
		
		if($usePageNum) {
			
			$pageNum = $start ? ($start / $limit) + 1 : 1;
			$totalPages = ceil($total / $limit); 
			if(!$totalPages) $pageNum = 0;
			$str = sprintf($this->_('%1$s %1$d of %2$d'), $label, $pageNum, $totalPages); // Page quantity, i.e. Page 1 of 3
			
		} else {

			if($count > $limit) $count = $limit;
			$end = $start + $count;
			if($end > $total) $total = $end;
			$start++; // make 1 based rather than 0 based...
			if($end == 0) $start = 0; // ...unless there are no items
			$str = sprintf($this->_('%1$s %2$d to %3$d of %4$d'), $label, $start, $end, $total); // Pagination item quantity, i.e. Items 1 to 10 of 50
		}
		
		return trim($str); 
	}


	/**
	 * debugInfo PHP 5.6+ magic method
	 *
	 * @return array
	 *
	 */
	public function __debugInfo() {
		$info = parent::__debugInfo();
		if($this->getLimit()) $info['pager'] = $this->getPaginationString();
		$info['total'] = $this->getTotal();
		$info['start'] = $this->getStart();
		$info['limit'] = $this->getLimit();
		return $info;
	}
}